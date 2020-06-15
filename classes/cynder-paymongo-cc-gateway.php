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
class Cynder_PayMongo_CC_Gateway extends WC_Payment_Gateway
{
    /**
     * Credit Card Singleton instance
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
        $this->id = 'paymongo_cc_payment_gateway';
        $this->has_fields = true;
        $this->method_title = 'Card Payments via PayMongo';
        $this->method_description = 'Simple and easy payments with Credit/Debit Cards';

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
                'label'       => 'Enable Card Payments Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which ' .
                    'the user sees during checkout.',
                'default'     => 'Visa/MasterCard via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which ' .
                    'the user sees during checkout.',
                'default'     => 'Simple and easy payments with Visa or MasterCard',
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
            plugins_url('assets/css/paymongo-styles.css', CYNDER_PAYMONGO_MAIN_FILE),
            array(),
            CYNDER_PAYMONGO_VERSION
        );
        wp_enqueue_script(
            'cleave',
            plugins_url('assets/js/cleave.min.js', CYNDER_PAYMONGO_MAIN_FILE),
        );
        wp_register_script(
            'woocommerce_paymongo',
            plugins_url('assets/js/paymongo.js', CYNDER_PAYMONGO_MAIN_FILE),
            array( 'jquery', 'cleave')
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

        echo '<fieldset id="cynder-'.esc_attr($this->id).'-cc-form"' .
            ' class="cynder-credit-card-form cynder-payment-form" '.
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

        $response = wp_remote_post(CYNDER_PAYMONGO_BASE_URL . '/payment_intents', $args);

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
            CYNDER_PAYMONGO_BASE_URL . '/payment_intents/' .
            $_POST['paymongo_intent_id'],
            $args
        );

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
                $messages = Cynder_PayMongo_Error_Handler::parseErrors($body['errors']);
                wc_add_notice($messages, 'error');
                return;
            }

        } else {
            wc_add_notice('Connection error.', 'error');
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
        $icons_str = '<img src="' . CYNDER_PAYMONGO_PLUGIN_URL . '/assets/images/cards.png" class="paymongo-cards-icon" alt="'. $this->title .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Custom Credit Card order received text.
     *
     * @param string   $text  Default text.
     * @param Cynder_Order $order Order data.
     *
     * @return string
     */
    public function orderReceivedText( $text, $order )
    {
        if ($order && $this->id === $order->get_payment_method()) {
            return esc_html__(
                'Thank You! Order has been received.'
                .' Waiting for Credit Card Confirmation',
                'woocommerce'
            );
        }

        return $text;
    }
}
