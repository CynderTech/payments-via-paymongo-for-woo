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
 * PayMongo - GCash Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Gcash_Gateway extends WC_Payment_Gateway
{
    /**
     * GCash Singleton instance
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
    public function __construct()
    {
        $this->id = 'paymongo_gcash_payment_gateway';
        $this->has_fields = true;
        $this->method_title = 'GCash Gateway via PayMongo';
        $this->method_description = 'Simple and easy payments with GCash.';

        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();

        $mainSettings = get_option('woocommerce_paymongo_payment_gateway_settings');

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = (
            !empty($mainSettings['testmode']) 
            && 'yes' === $mainSettings['testmode']
        ) ? true : false;
        $this->public_key = !empty($mainSettings['public_key']) ?
            $mainSettings['public_key'] : '';
        $this->secret_key = !empty($mainSettings['secret_key']) ?
            $mainSettings['secret_key'] : '';
        $this->statement_descriptor = !empty($mainSettings['statement_descriptor']) ?
            $mainSettings['statement_descriptor'] : '';

        if ($this->testmode) {
            $this->public_key = !empty($mainSettings['test_public_key']) ?
                $mainSettings['test_public_key'] : '';
            $this->secret_key = !empty($mainSettings['test_secret_key']) ?
                $mainSettings['test_secret_key'] : '';
        }

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
        add_action('wp_enqueue_scripts', array($this, 'paymentScripts'));

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
                'label'       => 'Enable GCash Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which ' .
                                 'the user sees during checkout.',
                'default'     => 'GCash via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which ' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments via GCash.',
            ),
        );
    }

    /**
     * Registers scripts and styles for payment gateway
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function paymentScripts() 
    { 
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
    
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }
    
        // no reason to enqueue JavaScript if API keys are not set
        if (!$this->testmode
            && (empty($this->secret_key)
            || empty($this->public_key))
        ) {
            return;
        }
    
        // disable without SSL unless your website is in a test mode
        if (!$this->testmode && !is_ssl()) {
            return;
        }

        $paymongoVar = array();
        $paymongoVar['publicKey'] = $this->public_key;

        // Order Pay Page
        if (isset($_GET['pay_for_order']) && 'true' === $_GET['pay_for_order']) {
            $orderId = wc_get_order_id_by_order_key(urldecode($_GET['key']));
            $order = wc_get_order($orderId);
            $paymongoVar['order_pay_url'] = $order->get_checkout_payment_url();
            $paymongoVar['billing_first_name'] = $order->get_billing_first_name();
            $paymongoVar['billing_last_name'] = $order->get_billing_last_name();
            $paymongoVar['billing_address_1'] = $order->get_billing_address_1();
            $paymongoVar['billing_address_2'] = $order->get_billing_address_2();
            $paymongoVar['billing_state'] = $order->get_billing_state();
            $paymongoVar['billing_city'] = $order->get_billing_city();
            $paymongoVar['billing_postcode'] = $order->get_billing_postcode();
            $paymongoVar['billing_country'] = $order->get_billing_country();
            $paymongoVar['billing_email'] = $order->get_billing_email();
            $paymongoVar['billing_phone'] = $order->get_billing_phone();
        }
        
        if (!wp_script_is('woocommerce_paymongo', 'enqueued')) {
            wp_register_script(
                'woocommerce_paymongo',
                plugins_url('assets/js/paymongo.js', CYNDER_PAYMONGO_MAIN_FILE),
                array('jquery')
            );
            wp_localize_script(
                'woocommerce_paymongo',
                'paymongo_params',
                $paymongoVar
            );
                
            wp_enqueue_style('paymongo');
            wp_enqueue_script('woocommerce_paymongo');
        }
    }

    /**
     * Creates PayMongo GCash source
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
        global $woocommerce;

        $order = wc_get_order($orderId);


        $payload = json_encode(
            array(
                'data' => array(
                    'attributes' =>array(
                        'type' => 'gcash',
                        'amount' => intval($order->get_total() * 100, 32),
                        'currency' => $order->get_currency(),
                        'description' => $order->get_order_key(),
                        'billing' => array(
                            'address' => array(
                                'line1' => $order->get_billing_address_1(),
                                'line2' => $order->get_billing_address_2(),
                                'city' => $order->get_billing_city(),
                                'state' => $order->get_billing_state(),
                                'country' => $order->get_billing_country(),
                                'postal_code' => $order->get_billing_postcode(),
                            ),
                            'name' => $order->get_billing_first_name() 
                                . ' ' . $order->get_billing_last_name(),
                            'email' => $order->get_billing_email(),
                            'phone' => $order->get_billing_phone(),
                        ),
                        'redirect' => array(
                            'success' => add_query_arg(
                                'paymongo',
                                'grabpay_pending',
                                $this->get_return_url($order)
                            ),
                            'failed' => add_query_arg(
                                'paymongo',
                                'gcash_failed',
                                $order->get_checkout_payment_url()
                            ),
                        ),
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
        );

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

                wp_send_json(
                    array(
                        'result' => 'success',
                        'checkout_url' => $attributes['redirect']['checkout_url'],
                    )
                );

                return;
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

    /**
     * Get Icon for checkout page
     * 
     * @return string
     */
    public function get_icon() // phpcs:ignore
    {
        $icons_str = '<img src="' 
            . CYNDER_PAYMONGO_PLUGIN_URL
            . '/assets/images/gcash.png" class="paymongo-cards-icon" alt="'
            . $this->title .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }


    /**
     * Custom GCash order received text.
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
                'Thank You!Order has been received.'
                .' Waiting for GCash Confirmation',
                'woocommerce'
            );
        }

        return $text;
    }
}
