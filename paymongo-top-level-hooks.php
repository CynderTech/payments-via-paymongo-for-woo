<?php
/**
 * PHP version 7
 * 
 * PayMongo - Top Level Hooks File
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function cynder_paymongo_create_intent() {
    $requestBody = file_get_contents('php://input');
    $decoded = json_decode($requestBody, true);
    $amount = floatval($decoded['amount']);

    if (!is_float($amount)) {
        return wp_send_json(
            array('error' => 'Invalid amount'),
            400
        );
    }

    $pluginSettings = get_option('woocommerce_paymongo_settings');

    if ($pluginSettings['enabled'] !== 'yes') {
        return wp_send_json(
            array('error' => 'Payment gateway must be enabled'),
            400
        );
    }

    $secretKeyProp = $pluginSettings['testmode'] === 'yes' ? 'test_secret_key' : 'secret_key';
    $secretKey = $pluginSettings[$secretKeyProp];

    $payload = json_encode(
        array(
            'data' => array(
                'attributes' =>array(
                    'amount' => floatval($amount * 100),
                    'payment_method_allowed' => array('card'),
                    'currency' => 'PHP', // hard-coded for now
                ),
            ),
        )
    );

    $args = array(
        'body' => $payload,
        'method' => "POST",
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($secretKey),
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
    );

    $response = wp_remote_post(
        CYNDER_PAYMONGO_BASE_URL . '/payment_intents',
        $args
    );

    /** Enable for debugging purposes */
    // wc_get_logger()->log('info', json_encode($response));

    if (!is_wp_error($response)) {
        $body = json_decode($response['body'], true);

        if ($body
            && array_key_exists('data', $body)
            && array_key_exists('attributes', $body['data'])
            && array_key_exists('status', $body['data']['attributes'])
            && $body['data']['attributes']['status'] == 'awaiting_payment_method'
        ) {
            $clientKey = $body['data']['attributes']['client_key'];
            return wp_send_json(
                array(
                    'result' => 'success',
                    'payment_client_key' => $clientKey,
                    'payment_intent_id' => $body['data']['id'],
                )
            );
        } else {
            wp_send_json(
                array(
                    'result' => 'error',
                    'errors' => $body['errors'],
                )
            );
            return;
        }
    } else {
        wp_send_json(
            array(
                'result' => 'failure',
                'messages' => $response->get_error_messages(),
            )
        );
        return;
    }
}

add_action(
    'woocommerce_api_cynder_paymongo_create_intent',
    'cynder_paymongo_create_intent'
);

function cynder_paymongo_catch_redirect() {
    global $woocommerce;

    wc_get_logger()->log('info', 'Params ' . json_encode($_GET));

    $paymentIntentId = $_GET['intent'];

    if (!isset($paymentIntentId)) {
        /** Check payment intent ID */
    }

    $paymentGatewaId = 'paymongo';
    $paymentGateways = WC_Payment_Gateways::instance();

    $paymongoGateway = $paymentGateways->payment_gateways()[$paymentGatewaId];
    $testMode = $paymongoGateway->get_option('testmode');
    $authOptionKey = $testMode === 'yes' ? 'test_secret_key' : 'secret_key';
    $authKey = $paymongoGateway->get_option($authOptionKey);

    $args = array(
        'method' => 'GET',
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($authKey),
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
    );

    $response = wp_remote_get(
        CYNDER_PAYMONGO_BASE_URL . '/payment_intents/' . $paymentIntentId,
        $args
    );

    /** Enable for debugging */
    wc_get_logger()->log('info', '[Catch Redirect][Response] ' . json_encode($response));

    if (is_wp_error($response)) {
        /** Handle errors */
        return;
    }

    $body = json_decode($response['body'], true);

    $responseAttr = $body['data']['attributes'];
    $status = $responseAttr['status'];

    $orderId = $_GET['order'];
    $order = wc_get_order($orderId);

    if ($status === 'succeeded') {
        // we received the payment
        $payments = $responseAttr['payments'];
        $order->payment_complete($payments[0]['id']);
        wc_reduce_stock_levels($orderId);

        // Sending invoice after successful payment
        $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);

        // Empty cart
        $woocommerce->cart->empty_cart();

        // Redirect to the thank you page
        wp_redirect($order->get_checkout_order_received_url());
    } else if ($status === 'awaiting_payment_method') {
        wc_add_notice('Something went wrong with the payment. Please try another payment method. If issue persist, contact support.', 'error');
        wp_redirect($order->get_checkout_payment_url());
    }
}

add_action(
    'woocommerce_api_cynder_paymongo_catch_redirect',
    'cynder_paymongo_catch_redirect'
);
