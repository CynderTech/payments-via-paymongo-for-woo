<?php
/**
 * PHP version 7
 * 
 * PayMongo - Credit Card Payment Method
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

/**
 * PayMongo - Credit Card Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_PayMaya extends WC_Payment_Gateway
{
    /**
     * Singleton instance
     * 
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $_instance;

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
        $this->id = 'paymongo_paymaya';
        $this->has_fields = true;
        $this->method_title = 'PayMaya Payments via PayMongo';
        $this->method_description = 'Simple and easy payments '
            . 'with PayMaya';

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
     * Payment Gateway Settings Page Fields
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function initFormFields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable PayMaya Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => 'Title',
                'description' => 'This controls the title that ' .
                                 'the user sees during checkout.',
                'default'     => 'PayMaya via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description that ' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments.',
            ),
        );
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

        $order = wc_get_order($orderId);
        $paymentIntentId = $order->get_meta('paymongo_payment_intent_id');

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

        wc_get_logger()->log('info', 'Billing address ' . wc_print_r($billing_address, true));

        if (count($billing_address) > 0) {
            $billing['address'] = $billing_address;
        }

        $paymentMethodPayload = json_encode(
            array(
                'data' => array(
                    'attributes' => array(
                        'type' => 'paymaya',
                        'billing' => $billing,
                    ),
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

        $attachPayload = json_encode(
            array(
                'data' => array(
                    'attributes' =>array(
                        'payment_method' => $paymentMethodId,
                        'return_url' => get_home_url() . '/?wc-api=cynder_paymongo_catch_redirect&order=' . $orderId . '&intent=' . $paymentIntentId
                    ),
                ),
            )
        );
        
        $attachArgs = array(
            'body' => $attachPayload,
            'method' => "POST",
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_key),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
        );

        $attachResponse = wp_remote_post(
            CYNDER_PAYMONGO_BASE_URL . '/payment_intents/' . $paymentIntentId . '/attach',
            $attachArgs
        );

        if (is_wp_error($attachResponse)) {
            wc_get_logger()->log('error', '[Processing Payment] Order ID: ' . $orderId . ' - Response error ' . wc_print_r(json_decode($attachResponse['body'], true), true));
            return wc_add_notice('Payment processing error. Please contact side administrator for further details.', 'error');
        }

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[process_payment] Attach payment intent response ' . wc_print_r(json_decode($attachResponse['body'], true), true));
        }

        $attachResponsePayload = json_decode($attachResponse['body'], true);

        if (isset($attachResponsePayload['errors'])) {
            for ($i = 0; $i < count($attachResponsePayload['errors']); $i++) {
                wc_add_notice($attachResponsePayload['errors'][$i]['detail'], 'error');
            }

            return;
        }

        $responseAttr = $attachResponsePayload['data']['attributes'];
        $status = $responseAttr['status'];

        wc_get_logger()->log('info', 'Payment intent status ' . $status);

        /** For regular payments, process as is */
        if ($status == 'succeeded') {
            // we received the payment
            $payments = $responseAttr['payments'];
            $order->payment_complete($payments[0]['id']);
            wc_reduce_stock_levels($orderId);

            // Sending invoice after successful payment
            $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);

            // Empty cart
            $woocommerce->cart->empty_cart();

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
    }
    
    /**
     * Get Icon for checkout page
     * 
     * @return string
     */
    public function get_icon() // phpcs:ignore
    {
        $icons_str = '<img class="paymongo-method-logo paymongo-cards-icon" src="'
            . CYNDER_PAYMONGO_PLUGIN_URL
            . '/assets/images/paymaya.png" alt="'
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
