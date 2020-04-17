<?php
/**
 * PHP version 7
 * 
 * PayMongo - Credit Card Payment Method
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <developers@paymongo.com>
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
 * @author   PayMongo <developers@paymongo.com>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class WC_PayMongo_Gateway extends WC_Payment_Gateway
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
        $this->id = 'paymongo_payment_gateway';
        $this->icon = 'https://b.paymongocdn.com/images/logo-with-text.png';
        $this->has_fields = true;
        $this->method_title = 'PayMongo';
        $this->method_description = 'Simple and easy payments.';

        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->secret_key = $this->testmode ?
            $this->get_option('test_secret_key')
            : $this->get_option('secret_key');
        $this->public_key = $this->testmode ?
            $this->get_option('test_public_key')
            : $this->get_option('public_key');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
        add_action('wp_enqueue_scripts', array($this, 'paymentScripts'));
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
                'label'       => 'Enable PayMongo Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => 'Title',
                'description' => 'This controls the title which' .
                                 'the user sees during checkout.',
                'default'     => 'PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments.',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in' .
                                 ' test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_public_key' => array(
                'title'       => 'Test Public Key',
                'type'        => 'text'
            ),
            'test_secret_key' => array(
                'title'       => 'Test Secret Key',
                'type'        => 'password',
            ),
            'public_key' => array(
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            'secret_key' => array(
                'title'       => 'Live Secret Key',
                'type'        => 'password'
            ),
            'webhook' => array(
                'title' => 'IMPORTANT! Setup Webhook Resource',
                'type' => 'title',
                'description' => 'Create a Webhook resource using curl command ' .
                            'or any API tools like Postman. ' . 
                            'Copy the <b>secret_key</b> '.
                            'and put it in the field below. <p>Use this URL: <b><i>'
                            . add_query_arg(
                                'wc-api',
                                'wc_paymongo',
                                trailingslashit(get_home_url())
                            ) . '</b></i></p>'
            ),
            'webhook_secret' => array(
                'title'       => 'Webhook Secret Key',
                'type'        => 'password',
                'description' => 'This is the secret_key returned when you ' .
                                 'created the webhook using live keys',
                'default'     => '',
            ),
            'test_webhook_secret' => array(
                'title'       => 'Test Webhook Secret Key',
                'type'        => 'password',
                'description' => 'This is the secret_key returned when you ' .
                                 'created the webhook using test keys',
                'default'     => '',
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
        if (!$this->testmode && ! is_ssl()) {
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

        wp_register_style(
            'paymongo',
            plugins_url('assets/css/paymongo-styles.css', WC_PAYMONGO_MAIN_FILE),
            array(),
            WC_PAYMONGO_VERSION
        );
        wp_enqueue_script(
            'cleave',
            'https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.5.9/cleave.min.js'
        );
        wp_enqueue_script(
            'jquery_cleave',
            plugins_url('assets/js/cleave.js', WC_PAYMONGO_MAIN_FILE)
        );
        wp_register_script(
            'woocommerce_paymongo',
            plugins_url('assets/js/paymongo.js', WC_PAYMONGO_MAIN_FILE),
            array('cleave', 'jquery', 'jquery_cleave')
        );
        wp_localize_script('woocommerce_paymongo', 'paymongo_params', $paymongoVar);
            
        wp_enqueue_style('paymongo');
        wp_enqueue_script('woocommerce_paymongo');
    }


    /**
     * Renders Payment fields for checkout page
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function payment_fields() // phpcs:ignore
    {
        if ($this->description) {
            if ($this->testmode) {
                $this->description .= ' TEST MODE ENABLED. In test mode,' .
                ' you can use the card numbers listed in the ' .
                '<a href="'.
                'https://developers.paymongo.com/docs/testing' .
                '" target="_blank" rel="noopener noreferrer">documentation</a>.';
                $this->description  = trim($this->description);
            }
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }

        echo '<fieldset id="wc-'.esc_attr($this->id).'-cc-form"' .
            ' class="wc-credit-card-form wc-payment-form" '.
            'style="background:transparent;">';
    
        do_action('woocommerce_credit_card_form_start', $this->id);

        echo '<div class="form-row form-row-wide">';
        echo '<label>Card Number <span class="required">*</span></label>';
        echo '<input id="paymongo_ccNo" class="paymongo_ccNo" type="text"' .
             ' autocomplete="off"></div>';
        echo '<div class="form-row form-row-first">';
        echo '<label>Expiry Date <span class="required">*</span></label>';
        echo '<input id="paymongo_expdate" class="paymongo_expdate" ' .
            'type="text" autocomplete="off" placeholder="MM / YY"></div>';
        echo '<div class="form-row form-row-last">';
        echo '<label>Card Code (CVC) <span class="required">*</span></label>';
        echo '<input id="paymongo_cvv" class="paymongo_cvv"' .
             ' type="password" autocomplete="off" placeholder="CVC">';
        echo '</div><div class="clear"></div>';
    
        do_action('woocommerce_credit_card_form_end', $this->id);
    
        echo '<div class="clear"></div></fieldset>';
    }

    public function validate_fields() // phpcs:ignore
    {
        return true;
    }

    /**
     * Creates PayMongo Payment Intent
     * 
     * @param string $orderId WooCommerce Order ID
     * 
     * @return void
     * 
     * @link  https://developers.paymongo.com/reference#the-payment-intent-object
     * @since 1.0.0
     */
    public function createPaymentIntent($orderId)
    {
        $order = wc_get_order($orderId);
        $payload = json_encode(
            array(
                'data' => array(
                    'attributes' =>array(
                        'amount' => intval($order->get_total() * 100, 32),
                        'payment_method_allowed' => array('card'),
                        'currency' => $order->get_currency(),
                        'description' => $order->get_order_key(),
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

        $response = wp_remote_post(WC_PAYMONGO_BASE_URL . '/payment_intents', $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if ($body
                && array_key_exists('data', $body)
                && array_key_exists('attributes', $body['data'])
                && array_key_exists('status', $body['data']['attributes'])
                && $body['data']['attributes']['status'] == 'awaiting_payment_method'
            ) {
                $clientKey = $body['data']['attributes']['client_key'];
                wp_send_json(
                    array(
                        'result' => 'success',
                        'payment_client_key' => $clientKey,
                        'payment_intent_id' => $body['data']['id'],
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
     * Creates PayMongo Payment Intent
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

        if (!isset($_POST['paymongo_client_key'])
            || !isset($_POST['paymongo_intent_id'])
        ) {
            return $this->createPaymentIntent($orderId);
        }

        // we need it to get any order details
        $order = wc_get_order($orderId);

        $args = array(
            'method' => "GET",
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_key),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
        );

        // get payment intent status
        $response = wp_remote_get(
            WC_PAYMONGO_BASE_URL . '/payment_intents/' .
            $_POST['paymongo_intent_id'],
            $args
        );
    
        // var_dump($response);
        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if ($body['data']['attributes']['status'] == 'succeeded') {
                // we received the payment
                $order->payment_complete($body['data']['id']);
                wc_reduce_stock_levels($orderId);

                // some notes to customer
                $order->add_order_note('Your order has been paid, Thank You!', true);

                // Empty cart
                $woocommerce->cart->empty_cart();

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                $messages = WC_PayMongo_Error_Handler::parseErrors($body['errors']);
                wc_add_notice($messages, 'error');
                return;
            }

        } else {
                wc_add_notice('Connection error.', 'error');
                return;
        }
    }
}
