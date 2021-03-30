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

function cynder_paymongo_create_intent($orderId) {
    $ccSettings = get_option('woocommerce_paymongo_settings');
    
    $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
    $testMode = (!empty($testMode) && $testMode === 'yes') ? true : false;

    $order = wc_get_order($orderId);

    $paymentMethod = $order->get_payment_method();

    $hasPaymentMethod = isset($paymentMethod) && $paymentMethod !== '' && $paymentMethod !== null;

    /**
     * Don't create a payment intent for the following scenarios:
     * 
     * 1. Paymongo plugin is disabled
     * 2. Has no payment method (ex. 100% discounts)
     * 3. Payment method is not Paymongo credit card
     */
    if ($ccSettings['enabled'] !== 'yes' || !$hasPaymentMethod || $paymentMethod !== 'paymongo') return;

    $amount = floatval($order->get_total());

    if (!is_float($amount)) {
        $errorMessage = 'Invalid amount';
        wc_get_logger()->log('error', '[Create Payment Intent] ' . $errorMessage);
        throw new Exception(__($errorMessage, 'woocommerce'));
    }

    $skKey = $testMode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
    $secretKey = get_option($skKey);

    $payload = json_encode(
        array(
            'data' => array(
                'attributes' =>array(
                    'amount' => floatval($amount * 100),
                    'payment_method_allowed' => array('card'),
                    'currency' => 'PHP', // hard-coded for now
                    'description' => get_bloginfo('name') . ' - ' . $orderId
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

    $genericErrorMessage = 'Something went wrong with the payment. Please try another payment method. If issue persist, contact support.';

    if (!is_wp_error($response)) {
        $body = json_decode($response['body'], true);

        if ($body
            && array_key_exists('data', $body)
            && array_key_exists('attributes', $body['data'])
            && array_key_exists('status', $body['data']['attributes'])
            && $body['data']['attributes']['status'] == 'awaiting_payment_method'
        ) {
            $clientKey = $body['data']['attributes']['client_key'];
            $order->add_meta_data('paymongo_payment_intent_id', $body['data']['id']);
            $order->add_meta_data('paymongo_client_key', $clientKey);
            $order->save_meta_data();
        } else {
            wc_get_logger()->log('error', '[Create Payment Intent] ' . json_encode($body['errors']));
            throw new Exception(__($genericErrorMessage, 'woocommerce'));
        }
    } else {
        wc_get_logger()->log('error', '[Create Payment Intent] ' . json_encode($response->get_error_messages()));
        throw new Exception(__($genericErrorMessage, 'woocommerce'));
    }
}

add_action('woocommerce_checkout_order_processed', 'cynder_paymongo_create_intent');

function cynder_paymongo_catch_redirect() {
    global $woocommerce;

    wc_get_logger()->log('info', 'Params ' . json_encode($_GET));

    $paymentIntentId = $_GET['intent'];

    if (!isset($paymentIntentId)) {
        /** Check payment intent ID */
    }

    $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
    $testMode = (!empty($testMode) && $testMode === 'yes') ? true : false;

    $skKey = $testMode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
    $secretKey = get_option($skKey);

    $args = array(
        'method' => 'GET',
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($secretKey),
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

    /** If payment intent status is succeeded or processing, just empty cart and redirect to confirmation page */
    if ($status === 'succeeded' || $status === 'processing') {
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


function cynder_paymongo_catch_source_redirect() {
    $orderId = $_GET['order'];
    $status = $_GET['status'];

    $order = wc_get_order($orderId);

    if ($status === 'success') {
        wp_redirect($order->get_checkout_order_received_url());
    } else if ($status === 'failed') {
        wc_add_notice('Something went wrong with the payment. Please try another payment method. If issue persist, contact support.', 'error');
        wp_redirect($order->get_checkout_payment_url());
    }
}

add_action(
    'woocommerce_api_cynder_paymongo_catch_source_redirect',
    'cynder_paymongo_catch_source_redirect'
);

function add_webhook_settings($settings, $current_section) {
    if ($current_section === 'paymongo_gcash' || $current_section === 'paymongo_grab_pay' || $current_section === 'paymongo') {
        $webhookUrl = add_query_arg(
            'wc-api',
            'cynder_paymongo',
            trailingslashit(get_home_url())
        );

        $settings_webhooks = array(
            array(
                'name' => 'API Settings',
                'id' => 'paymongo_api_settings_title',
                'type' => 'title',
                'desc' => 'PayMongo API settings'
            ),
            array(
                'id' => 'live_env',
                'title' => 'Live Environment',
                'type' => 'title',
                'description' => 'Use live keys for actual payments'
            ),
            array(
                'id'          => 'woocommerce_cynder_paymongo_public_key',
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            array(
                'id'          => 'woocommerce_cynder_paymongo_secret_key',
                'title'       => 'Live Secret Key',
                'type'        => 'text'
            ),
            array(
                'name' => 'Live Webhook Secret',
                'id' => 'paymongo_webhook_secret_key',
                'type' => 'text',
                'desc_tip' => 'This is required to properly process payments and update order statuses accordingly',
                'desc' => '<a target="_blank" href="https://paymongo-webhook-tool.meeco.dev?url=' 	
                . $webhookUrl	
                . '">Go here to generate a webhook secret</a>',
            ),
            array(
                'id' => 'live_env_end',
                'type' => 'sectionend'
            ),
            array(
                'id' => 'test_env',
                'title' => 'Test Environment',
                'type' => 'title',
                'desc' => 'Use the plugin in <b>Test Mode</b><br/>In test mode, you can transact using the PayMongo payment methods in checkout without actual payments'
            ),
            array(
                'id' => 'woocommerce_cynder_paymongo_test_mode',
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'desc' => 'Place the payment gateway in test mode using <b>Test API keys</b>',
                'default'     => 'yes',
            ),
            array(
                'id'          => 'woocommerce_cynder_paymongo_test_public_key',
                'title'       => 'Test Public Key',
                'type'        => 'text'
            ),
            array(
                'id'          => 'woocommerce_cynder_paymongo_test_secret_key',
                'title'       => 'Test Secret Key',
                'type'        => 'text'
            ),
            array(
                'name' => 'Test Webhook Secret',
                'id' => 'paymongo_test_webhook_secret_key',
                'type' => 'text',
                'desc_tip' => 'This is required to properly process payments and update order statuses accordingly',
                'desc' => '<a target="_blank" href="https://paymongo-webhook-tool.meeco.dev?url=' 	
                . $webhookUrl	
                . '">Go here to generate a webhook secret</a>',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'paymongo_api_settings',
            ),
        );

        return $settings_webhooks;
    } else {
        return $settings;
    }
}

add_filter(
    'woocommerce_get_settings_checkout',
    'add_webhook_settings',
    10,
    2
);

function update_cynder_paymongo_plugin() {
    $oldVersion = get_option('cynder_paymongo_version');

    /**
     * Prior to 1.4.8, API settings are in credit/debit card screen only
     * 
     * Updating the plugin to 1.4.8 or higher moves the settings as shared ones on
     * all PayMongo payment methods
     */
    if (version_compare($oldVersion, '1.5.0', '<')) {
        $mainPluginSettings = get_option('woocommerce_paymongo_settings');

        /** Migrate old settings to new settings */
        $settingsToMigrage = array(
            'public_key' => 'woocommerce_cynder_paymongo_public_key',
            'secret_key' => 'woocommerce_cynder_paymongo_secret_key',
            'test_public_key' => 'woocommerce_cynder_paymongo_test_public_key',
            'test_secret_key' => 'woocommerce_cynder_paymongo_test_secret_key',
            'testmode' => 'woocommerce_cynder_paymongo_test_mode'
        );

        foreach ($settingsToMigrage as $oldKey => $newKey) {
            $newSetting = get_option($newKey);

            if (!$newSetting) {
                update_option($newKey, $mainPluginSettings[$oldKey], true);
            }
        }
    }
}

add_action('woocommerce_paymongo_updated', 'update_cynder_paymongo_plugin');