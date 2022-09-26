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

use PostHog\PostHog;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function cynder_paymongo_create_intent($orderId) {
    $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
    $testMode = (!empty($testMode) && $testMode === 'yes') ? true : false;
    
    $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
    $debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;
    
    $order = wc_get_order($orderId);

    $paymentMethod = $order->get_payment_method();

    $hasPaymentMethod = isset($paymentMethod) && $paymentMethod !== '' && $paymentMethod !== null;
    $paymentMethodSettings = get_option("woocommerce_{$paymentMethod}_settings");

    /**
     * Don't create a payment intent for the following scenarios:
     * 
     * 1. Payment method setting is disabled
     * 2. Has no payment method (ex. 100% discounts)
     * 3. Payment method does not belong to methods that needs payment intents
     */
    if (
        $paymentMethodSettings['enabled'] !== 'yes' ||
        !$hasPaymentMethod ||
        (!in_array($paymentMethod, PAYMENT_METHODS_WITH_INTENT))
    ) return;

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
                    'payment_method_allowed' => ['card', 'paymaya', 'atome', 'dob', 'billease'],
                    'currency' => 'PHP', // hard-coded for now
                    'description' => get_bloginfo('name') . ' - ' . $orderId,
                    'metadata' => array(
                        'agent' => 'cynder_woocommerce',
                        'version' => CYNDER_PAYMONGO_VERSION,
                    )
                ),
            ),
        )
    );

    if ($debugMode) {
        wc_get_logger()->log('info', '[Create Payment Intent] Payload ' . wc_print_r($payload, true));
    }

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

    if ($debugMode) {
        wc_get_logger()->log('info', '[Create Payment Intent] Response ' . wc_print_r($response['body'], true));
    }

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

    $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
    $debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;

    $sendInvoice = get_option('woocommerce_cynder_paymongo_send_invoice_after_payment');
    $sendInvoice = (!empty($sendInvoice) && $sendInvoice === 'yes') ? true : false;

    if ($debugMode) {
        wc_get_logger()->log('info', '[Catch Redirect][Payload] ' . wc_print_r($_GET, true));
    }

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

    if ($debugMode) {
        wc_get_logger()->log('info', '[Catch Redirect][Response] ' . json_encode($response));
    }

    if (is_wp_error($response)) {
        /** Handle errors */
        return;
    }

    $body = json_decode($response['body'], true);

    $responseAttr = $body['data']['attributes'];
    $status = $responseAttr['status'];
    $intentAmount = $responseAttr['amount'];

    $orderId = $_GET['order'];
    $order = wc_get_order($orderId);

    /** If payment intent status is succeeded or processing, just empty cart and redirect to confirmation page */
    if ($status === 'succeeded' || $status === 'processing') {
        if ($status === 'succeeded') {
            $payment = $responseAttr['payments'][0];
            $order->payment_complete($payment['id']);
            $orderId = $order->get_id();
            wc_reduce_stock_levels($orderId);

            // Sending invoice after successful payment if setting is enabled
            if ($sendInvoice) {
                $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);
            }

            PostHog::capture(array(
                'distinctId' => base64_encode(get_bloginfo('wpurl')),
                'event' => 'successful payment',
                'properties' => array(
                    'payment_id' => $payment['id'],
                    'amount' => floatval($intentAmount) / 100,
                    'payment_method' => $order->get_payment_method(),
                    'sandbox' => $testMode ? 'true' : 'false',
                ),
            ));

            do_action('cynder_paymongo_successful_payment', $payment);
        }

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
    if (in_array($current_section, PAYMONGO_PAYMENT_METHODS)) {
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
                'id' => 'test_env_end',
                'type' => 'sectionend'
            ),
            array(
                'id' => 'paymongo_misc',
                'title' => 'Other Options',
                'type' => 'title',
            ),
            array(
                'id' => 'woocommerce_cynder_paymongo_debug_mode',
                'title'       => 'Debug mode',
                'label'       => 'Enable Debug Mode',
                'type'        => 'checkbox',
                'desc_tip' => 'This enables additional logs in WC logger for developer analysis',
                'desc' => 'Enable additional logs',
                'default'     => 'no',
            ),
            array(
                'id' => 'woocommerce_cynder_paymongo_send_invoice_after_payment',
                'title' => 'Send Invoice',
                'desc' => 'Enables automatic invoice sending after payment',
                'desc_tip' => 'This enables automatic sending of an invoice to the customer via e-mail after payment is resolved',
                'type' => 'checkbox',
                'default' => 'yes',
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

function cynder_paymongo_notices() {
    $version = get_option('cynder_paymongo_version');

    if (version_compare($version, '1.5.0', '<')) {
        echo '<div class="notice notice-warning">'
        . '<p><strong>You are using an outdated version of the PayMongo payment plugin</strong>. Please upgrade immediately using '
        . '<a target="_blank" href="https://cynder.atlassian.net/servicedesk/customer/portal/1/article/709656577">this guide</a>.</p>'
        . '</div>';
    }
}

add_action('admin_notices', 'cynder_paymongo_notices');