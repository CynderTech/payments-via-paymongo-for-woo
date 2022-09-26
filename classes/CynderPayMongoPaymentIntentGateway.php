<?php
/**
 * PHP version 7
 * 
 * PayMongo - Payment Intent Base Class
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

namespace Cynder\PayMongo;

use PostHog\PostHog;
use WC_Payment_Gateway;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayMongo - Payment Intent Base Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class CynderPayMongoPaymentIntentGateway extends WC_Payment_Gateway
{
    /**
     * Singleton instance
     * 
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $_instance;

    public $hasDetailsPayload = false;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if (null === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Starting point of the payment gateway
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
        $this->testmode = (!empty($testMode) && $testMode === 'yes') ? true : false;

        $skKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
        $this->secret_key = get_option($skKey);

        $pkKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
        $this->public_key = get_option($pkKey);

        $sendInvoice = get_option('woocommerce_cynder_paymongo_send_invoice_after_payment');
        $this->sendInvoice = (!empty($sendInvoice) && $sendInvoice === 'yes') ? true : false;

        $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
        $this->debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        if ('yes' === $this->enabled) {
            add_filter(
                'woocommerce_thankyou_order_received_text',
                array($this, 'orderReceivedText'),
                10,
                2
            );
        }
    }

    /**
     * Override for certain payment methods
     */
    public function initFormFields() {

    }

    /**
     * Override for certain payment methods
     */
    public function getPaymentMethodId($orderId) {
        $order = wc_get_order($orderId);

        $attributes = array(
            'type' => SERVER_PAYMENT_METHOD_TYPES[$this->id],
            'billing' => $this->generateBillingPayload($order),
        );

        if ($this->hasDetailsPayload) {
            $attributes['details'] = $this->generatePaymentMethodDetailsPayload($order);
        }

        $paymentMethodPayload = json_encode(
            array(
                'data' => array(
                    'attributes' => $attributes,
                ),
            )
        );

        $paymentMethodArgs = array(
            'body' => $paymentMethodPayload,
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_key),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
        );

        $paymentMethodResponse = wp_remote_post(
            CYNDER_PAYMONGO_BASE_URL . '/payment_methods',
            $paymentMethodArgs
        );

        if (is_wp_error($paymentMethodResponse)) {
            wc_get_logger()->log('error', '[Processing Payment] Order ID: ' . $orderId . ' - Response error ' . wc_print_r(json_decode($paymentMethodResponse['body'], true), true));
            return wc_add_notice('Payment processing error. Please contact side administrator for further details.', 'error');
        }

        $paymentMethodResponsePayload =  json_decode($paymentMethodResponse['body'], true);

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[process_payment] Payment method response ' . wc_print_r($paymentMethodResponsePayload, true));
        }

        if (isset($paymentMethodResponsePayload['errors'])) {
            for ($i = 0; $i < count($paymentMethodResponsePayload['errors']); $i++) {
                wc_add_notice($paymentMethodResponsePayload['errors'][$i]['detail'], 'error');
            }

            return;
        }

        $paymentMethodId = $paymentMethodResponsePayload['data']['id'];

        return $paymentMethodId;
    }

    public function generateBillingPayload($order) {
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $has_billing_first_name = $this->is_billing_value_set($billing_first_name);
        $has_billing_last_name = $this->is_billing_value_set($billing_last_name);

        if ($has_billing_first_name && $has_billing_last_name) {
            $billing['name'] = $billing_first_name . ' ' . $billing_last_name;
        }

        $billing_email = $order->get_billing_email();
        $has_billing_email = $this->is_billing_value_set($billing_email);

        if ($has_billing_email) {
            $billing['email'] = $billing_email;
        }

        $billing_phone = $order->get_billing_phone();
        $has_billing_phone = $this->is_billing_value_set($billing_phone);

        if ($has_billing_phone) {
            $billing['phone'] = $billing_phone;
        }

        $billing_address = generate_billing_address($order);

        if ($this->debugMode) {
            wc_get_logger()->log('info', 'Billing address ' . wc_print_r($billing_address, true));
        }

        if (count($billing_address) > 0) {
            $billing['address'] = $billing_address;
        }

        return $billing;
    }

    /** Override on certain payment methods */
    public function generatePaymentMethodDetailsPayload($order) {
        return array();
    }

    public function is_billing_value_set($value) {
        return isset($value) && $value !== '';
    }

    /**
     * Process PayMongo Payment
     *
     * @param string $orderId WooCommerce Order ID
     *
     * @return void
     *
     * @link  https://developers.paymongo.com/reference#the-payment-intent-object
     * @since 1.0.0
     */
    public function process_payment($orderId) // phpcs:ignore
    {
        global $woocommerce;
    
        $paymentMethodId = $this->getPaymentMethodId($orderId);

        if (!isset($paymentMethodId)) {
            $errorMessage = '[Processing Payment] No payment method ID found.';
            $userMessage = 'Your payment did not proceed due to an error. Rest assured that no payment was made. You may refresh this page and try again.';
            wc_get_logger()->log('error', $errorMessage);
            return wc_add_notice($userMessage, 'error');
        }

        $order = wc_get_order($orderId);
        $paymentIntentId = $order->get_meta('paymongo_payment_intent_id');

        if ($this->debugMode) {
            wc_get_logger()->log('info', 'Customer ID ' . $order->get_customer_id());
        }

        $amount = floatval($order->get_total());

        PostHog::capture(array(
            'distinctId' => base64_encode(get_bloginfo('wpurl')),
            'event' => 'process payment',
            'properties' => array(
                'amount' => $amount,
                'payment_method' => $order->get_payment_method(),
                'sandbox' => $this->testmode ? 'true' : 'false',
            ),
        ));

        $payload = json_encode(
            array(
                'data' => array(
                    'attributes' =>array(
                        'payment_method' => $paymentMethodId,
                        'return_url' => get_home_url() . '/?wc-api=cynder_paymongo_catch_redirect&order=' . $orderId . '&intent=' . $paymentIntentId . '&agent=cynder_woocommerce&version=' . CYNDER_PAYMONGO_VERSION
                    ),
                ),
            )
        );
        
        $args = array(
            'body' => $payload,
            'method' => "POST",
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_key),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post(
            CYNDER_PAYMONGO_BASE_URL . '/payment_intents/' . $paymentIntentId . '/attach',
            $args
        );

        if (!is_wp_error($response)) {
            if ($this->debugMode) {
                wc_get_logger()->log('info', '[process_payment] Response ' . wc_print_r($response, true));
            }

            $body = json_decode($response['body'], true);

            if (isset($body['errors'])) {
                for ($i = 0; $i < count($body['errors']); $i++) {
                    wc_add_notice($body['errors'][$i]['detail'], 'error');
                }

                return;
            }

            $responseAttr = $body['data']['attributes'];
            $status = $responseAttr['status'];

            /** For regular payments, process as is */
            if ($status == 'succeeded') {
                // we received the payment
                $payments = $responseAttr['payments'];
                $intentAmount = $responseAttr['amount'];
                $order->payment_complete($payments[0]['id']);
                wc_reduce_stock_levels($orderId);

                // Sending invoice after successful payment if setting is enabled
                if ($this->sendInvoice) {
                    $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);
                }

                // Empty cart
                $woocommerce->cart->empty_cart();

                PostHog::capture(array(
                    'distinctId' => base64_encode(get_bloginfo('wpurl')),
                    'event' => 'successful payment',
                    'properties' => array(
                        'payment_id' => $payments[0]['id'],
                        'amount' => floatval($intentAmount) / 100,
                        'payment_method' => $order->get_payment_method(),
                        'sandbox' => $this->testmode ? 'true' : 'false',
                    ),
                ));

                do_action('cynder_paymongo_successful_payment', $payments[0]);

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else if ($status === 'awaiting_next_action') {
                /** For 3DS-enabled cards, redirect to authorization page */
                return array(
                    'result' => 'success',
                    'redirect' => $responseAttr['next_action']['redirect']['url']
                );
            }
        } else {
            wc_get_logger()->log('error', '[Processing Payment] ID: ' . $paymentIntentId . ' - Response error ' . json_encode($response));
            return wc_add_notice('Connection error. Check logs.', 'error');
        }
    }

    /**
     * Get Icon for checkout page
     * 
     * @return string
     */
    public function get_icon() // phpcs:ignore
    {
        $icons_str = '<img class="paymongo-method-logo payment-method-' . $this->id . '" src="'
            . CYNDER_PAYMONGO_PLUGIN_URL
            . '/assets/images/' . $this->id .'.png" alt="'
            . $this->title
            .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Custom Credit Card order received text.
     *
     * @param string       $text  Default text.
     * @param Cynder_Order $order Order data.
     *
     * @return string
     */
    public function orderReceivedText( $text, $order )
    {
        if ($order && $this->id === $order->get_payment_method()) {
            return esc_html__(
                'Thank You! Order has been received.',
                'woocommerce'
            );
        }

        return $text;
    }
}
