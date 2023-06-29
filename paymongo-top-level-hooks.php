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

use Cynder\PayMongo\Utils;
use GuzzleHttp\Exception\ClientException;
use Paymongo\Phaymongo\PaymongoException;
use Paymongo\Phaymongo\Phaymongo;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function cynder_paymongo_create_intent($orderId) {
    $utils = new Utils();

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
        (!in_array($paymentMethod, PAYMONGO_PAYMENT_METHODS))
    ) return;

    $amount = floatval($order->get_total());

    if (!is_float($amount)) {
        $errorMessage = 'Invalid amount';
        wc_get_logger()->log('error', '[Create Payment Intent] ' . $errorMessage);
        throw new Exception(__($errorMessage, 'woocommerce'));
    }

    $pkKey = $testMode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
    $skKey = $testMode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
    $publicKey = get_option($pkKey);
    $secretKey = get_option($skKey);
    $client = new Phaymongo($publicKey, $secretKey);

    $genericErrorMessage = 'Something went wrong with the payment. Please try another payment method. If issue persist, contact support.';

    try {
        $shopName = get_bloginfo('name');
        $paymentIntent = $client->paymentIntent()->create(
            floatval($amount),
            ['card', 'paymaya', 'atome', 'dob', 'billease', 'gcash', 'grab_pay'],
            $shopName . ' - ' . $orderId,
            array(
                'agent' => 'cynder_woocommerce',
                'version' => CYNDER_PAYMONGO_VERSION,
                'store_name' => $shopName,
                'order_id' => strval($orderId),
                'customer_id' => strval($order->get_customer_id()),
            )
        );

        if ($debugMode) {
            wc_get_logger()->log('info', '[Create Payment Intent] Response ' . wc_print_r($paymentIntent, true));
        }
    
        if ($paymentIntent
            && array_key_exists('attributes', $paymentIntent)
            && array_key_exists('status', $paymentIntent['attributes'])
            && $paymentIntent['attributes']['status'] == 'awaiting_payment_method'
        ) {
            $clientKey = $paymentIntent['attributes']['client_key'];

            $existingIntentId = $order->get_meta(PAYMONGO_PAYMENT_INTENT_META_KEY);
            $existingClientKey = $order->get_meta(PAYMONGO_CLIENT_KEY_META_KEY);

            if (isset($existingIntentId) && $existingIntentId !== '') {
                $order->add_meta_data(PAYMONGO_PAYMENT_INTENT_META_KEY . '_old', $existingIntentId);
            }

            if (isset($existingClientKey) && $existingClientKey !== '') {
                $order->add_meta_data(PAYMONGO_CLIENT_KEY_META_KEY . '_old', $existingClientKey);
            }

            $order->update_meta_data(PAYMONGO_PAYMENT_INTENT_META_KEY, $paymentIntent['id']);
            $order->update_meta_data(PAYMONGO_CLIENT_KEY_META_KEY, $clientKey);
            $order->save_meta_data();
        } else {
            wc_get_logger()->log('error', '[Create Payment Intent] ' . json_encode($paymentIntent['errors']));
            throw new Exception(__($genericErrorMessage, 'woocommerce'));
        }
    } catch (PaymongoException $e) {
        $formatted_messages = $e->format_errors();
        $utils->log('error', '[Create Payment Intent] Response - ' . join(',', $formatted_messages));
        throw new Exception(__($genericErrorMessage, 'woocommerce'));
    }
}

add_action('woocommerce_checkout_order_processed', 'cynder_paymongo_create_intent');

function cynder_paymongo_catch_redirect() {
    $utils = new Utils();

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

    $pkKey = $testMode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
    $skKey = $testMode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
    $publicKey = get_option($pkKey);
    $secretKey = get_option($skKey);
    $client = new Phaymongo($publicKey, $secretKey);

    $orderId = $_GET['order'];
    $order = wc_get_order($orderId);

    try {
        $paymentIntent = $client->paymentIntent()->retrieveById($paymentIntentId);

        if ($debugMode) {
            wc_get_logger()->log('info', '[Catch Redirect][Response] ' . wc_print_r($paymentIntent, true));
        }

        $responseAttr = $paymentIntent['attributes'];
        $status = $responseAttr['status'];
        $intentAmount = $responseAttr['amount'];

        /** If payment intent status is succeeded or processing, just empty cart and redirect to confirmation page */
        if ($status === 'succeeded' || $status === 'processing') {
            if ($status === 'succeeded') {
                $payment = $responseAttr['payments'][0];

                $utils->completeOrder($order, $payment['id'], $sendInvoice);
                $utils->trackPaymentResolution('successful', $payment['id'], floatval($intentAmount) / 100, $order->get_payment_method(), $testMode);
                $utils->callAction('cynder_paymongo_successful_payment', $payment);
            }

            // Empty cart
            $utils->emptyCart();

            // Redirect to the thank you page
            wp_redirect($order->get_checkout_order_received_url());
        } else if ($status === 'awaiting_payment_method' || $status === 'awaiting_next_action') {
            wc_add_notice('Something went wrong with the payment. Please try another payment method. If issue persist, contact support.', 'error');
            wp_redirect($order->get_checkout_payment_url());
        }
    } catch (PaymongoException $e) {
        /** 
         * Log the error but confirm the order placement. This will fallback
         * to the webhooks for proper resolution.
         */
        $formatted_messages = $e->format_errors();
        $utils->log('error', '[Catch Redirect for Payment Intent] Order ID: ' . $order->get_id() . ' - Response: ' . join(',', $formatted_messages));
        wp_redirect($order->get_checkout_order_received_url());
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