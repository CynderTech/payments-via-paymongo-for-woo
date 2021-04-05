<?php
/**
 * PHP version 7
 * 
 * PayMongo - GCash Payment Method
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
 * PayMongo - Ewallet Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Ewallet_Gateway extends WC_Payment_Gateway
{
    /**
     * Ewallet Singleton instance
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
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Starting point of the payment gateway
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $this->supports = array(
            'products'
        );

        $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
        $this->testmode = (!empty($testMode) && $testMode === 'yes') ? true : false;

        $skKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
        $this->secret_key = get_option($skKey);

        $pkKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
        $this->public_key = get_option($pkKey);

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->initFormFields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
    }

    public function is_billing_value_set($value) {
        return isset($value) && $value !== '';
    } 

    /**
     * Creates E-Wallet source
     * 
     * @param string $orderId Order Id provided by woocommerce
     * 
     * @return void
     * 
     * @link  https://developers.paymongo.com/reference#the-sources-object
     * @since 1.0.0
     */
    public function process_payment($orderId) // phpcs:ignore
    {
        $order = wc_get_order($orderId);

        $billing = array();

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

        $billing_address = array();

        $billing_address_1 = $order->get_billing_address_1();
        $has_billing_address_1 = $this->is_billing_value_set($billing_address_1);

        if ($has_billing_address_1) {
            $billing_address['line1'] = $billing_address_1;
        }

        $billing_address_2 = $order->get_billing_address_2();
        $has_billing_address_2 = $this->is_billing_value_set($billing_address_2);

        if ($has_billing_address_2) {
            $billing_address['line2'] = $billing_address_2;
        }

        $billing_city = $order->get_billing_city();
        $has_billing_city = $this->is_billing_value_set($billing_city);

        if ($has_billing_city) {
            $billing_address['city'] = $billing_city;
        }

        $billing_state = $order->get_billing_state();
        $has_billing_state = $this->is_billing_value_set($billing_state);

        if ($has_billing_state) {
            $billing_address['state'] = $billing_state;
        }

        $billing_country = $order->get_billing_country();
        $has_billing_country = $this->is_billing_value_set($billing_country);

        if ($has_billing_country) {
            $billing_address['country'] = $billing_country;
        }

        $billing_postcode = $order->get_billing_postcode();
        $has_billing_postcode = $this->is_billing_value_set($billing_postcode);

        if ($has_billing_postcode) {
            $billing_address['postal_code'] = $billing_postcode;
        }


        if (count($billing_address) > 0) {
            $billing['address'] = $billing_address;
        }

        $attributes = array(
            'type' => $this->ewallet_type,
            'amount' => intval($order->get_total() * 100, 32),
            'currency' => $order->get_currency(),
            'description' => get_bloginfo('name') . ' - ' . $orderId,
            'redirect' => array(
                'success' => get_home_url() . '/?wc-api=cynder_paymongo_catch_source_redirect&order=' . $orderId . '&status=success',
                'failed' => get_home_url() . '/?wc-api=cynder_paymongo_catch_source_redirect&order=' . $orderId . '&status=failed',
            ),
        );

        if (count($billing) > 0) {
            $attributes['billing'] = $billing;
        }

        wc_get_logger()->log('info', 'Attributes ' . json_encode($attributes));

        $payload = json_encode(
            array(
                'data' => array(
                    'attributes' => $attributes,
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

        wc_get_logger()->log('info', 'Request Args ' . json_encode($args));

        $response = wp_remote_post(CYNDER_PAYMONGO_BASE_URL . '/sources', $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if ($body
                && array_key_exists('data', $body)
                && array_key_exists('attributes', $body['data'])
                && array_key_exists('status', $body['data']['attributes'])
                && $body['data']['attributes']['status'] == 'pending'
            ) {
                $order->add_meta_data('source_id', $body['data']['id']);
                $order->update_status('pending');
                
                // cynder_reduce_stock_levels($orderId);
                // $woocommerce->cart->empty_cart();
                $attributes = $body['data']['attributes'];

                return array(
                    'result' => 'success',
                    'redirect' => $attributes['redirect']['checkout_url'],
                );
            } else {
                for ($i = 0; $i < count($body['errors']); $i++) {
                    $error = $body['errors'][$i];
                    $code = $error['code'];
                    $message = $error['detail'];

                    if ($code == 'parameter_below_minimum') {
                        $message = 'Amount cannot be less than P100.00';
                    }

                    wc_add_notice($message, 'error');
                }

                return;
            }
        } else {
            wc_get_logger()->log('error', '[Processing Payment] Response error ' . json_encode($response));
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
        $icon_name = isset($this->icon_name) ? $this->icon_name : $this->ewallet_type;

        $icons_str = '<img src="' 
            . CYNDER_PAYMONGO_PLUGIN_URL
            . '/assets/images/' . $icon_name . '.png" class="paymongo-method-logo paymongo-cards-icon" alt="'
            . $this->title .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Custom E-wallet order received text.
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