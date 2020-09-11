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
class Cynder_PayMongo_Gateway extends WC_Payment_Gateway
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
        $this->id = 'paymongo';
        $this->has_fields = true;
        $this->method_title = 'Card Payments via PayMongo';
        $this->method_description = 'Simple and easy payments '
            . 'with Credit/Debit Card';

        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
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
        $webhookUrl = add_query_arg(
            'wc-api',
            'cynder_paymongo',
            trailingslashit(get_home_url())
        );

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
                'description' => 'This controls the title that ' .
                                 'the user sees during checkout.',
                'default'     => 'Credit Card via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description that ' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments.',
            ),
            'live_env' => array(
                'title' => 'Live Environment',
                'type' => 'title',
                'description' => 'Use live keys for actual payments'
            ),
            'public_key' => array(
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            'secret_key' => array(
                'title'       => 'Live Secret Key',
                'type'        => 'password'
            ),
            'webhook_secret' => array(
                'title'       => 'Webhook Secret Key',
                'type'        => 'password',
                'description' => 'Provide a secret key to enable'
                    . ' <b>GCash</b> or <b>GrabPay</b> Payments<br>'
                    . '<a target="_blank" href="https://paymongo-webhook-tool.meeco.dev?url=' 
                    . $webhookUrl
                    . '">Click this to generate a webhook secret</a>'
                    . ' or use this URL: <b>'
                    . $webhookUrl,
                'default'     => '',
            ),
            'test_env' => array(
                'title' => 'Test Environment',
                'type' => 'title',
                'description' => 'Use test keys the PayMongo payments'
                    .' without actual transactions'
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in' .
                                 ' test mode using <b>Test API keys</b>.',
                'default'     => 'yes',
            ),
            'test_public_key' => array(
                'title'       => 'Test Public Key',
                'type'        => 'text'
            ),
            'test_secret_key' => array(
                'title'       => 'Test Secret Key',
                'type'        => 'password',
            ),
           
            'test_webhook_secret' => array(
                'title'       => 'Test Webhook Secret Key',
                'type'        => 'password',
                'description' => 'Provide a secret key to enable'
                    . ' <b>GCash</b> or <b>GrabPay</b> Payments<br>'
                    . '<a target="_blank" href="https://paymongo-webhook-tool.meeco.dev?url=' 
                    . $webhookUrl
                    . '">Click this to generate a webhook secret</a>'
                    . ' or use this URL: <b>'
                    . $webhookUrl,
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

        $paymongoClient = array();
        $paymongoClient['home_url'] = get_home_url();
        $paymongoClient['public_key'] = $this->public_key;

        $paymongoCc = array();
        $paymongoCc['isCheckout'] = is_checkout() && !is_checkout_pay_page();
        $paymongoCc['isOrderPay'] = is_checkout_pay_page();

        // Order Pay Page
        if (isset($_GET['pay_for_order']) && 'true' === $_GET['pay_for_order']) {
            $orderId = wc_get_order_id_by_order_key(urldecode($_GET['key']));
            $order = wc_get_order($orderId);
            $paymongoCc['order_pay_url'] = $order->get_checkout_payment_url();
            $paymongoCc['total_amount'] = floatval($order->get_total());
            $paymongoCc['billing_first_name'] = $order->get_billing_first_name();
            $paymongoCc['billing_last_name'] = $order->get_billing_last_name();
            $paymongoCc['billing_address_1'] = $order->get_billing_address_1();
            $paymongoCc['billing_address_2'] = $order->get_billing_address_2();
            $paymongoCc['billing_state'] = $order->get_billing_state();
            $paymongoCc['billing_city'] = $order->get_billing_city();
            $paymongoCc['billing_postcode'] = $order->get_billing_postcode();
            $paymongoCc['billing_country'] = $order->get_billing_country();
            $paymongoCc['billing_email'] = $order->get_billing_email();
            $paymongoCc['billing_phone'] = $order->get_billing_phone();
        }

        wp_register_style(
            'paymongo',
            plugins_url('assets/css/paymongo-styles.css', CYNDER_PAYMONGO_MAIN_FILE),
            array(),
            CYNDER_PAYMONGO_VERSION
        );
        wp_register_style(
            'jqueryModal',
            plugins_url(
                'assets/css/jquery.modal.min.css',
                CYNDER_PAYMONGO_MAIN_FILE
            ),
            array(),
            CYNDER_PAYMONGO_VERSION
        );
        wp_enqueue_script(
            'cleave',
            plugins_url('assets/js/cleave.min.js', CYNDER_PAYMONGO_MAIN_FILE)
        );
        wp_enqueue_script(
            'jqueryModal',
            plugins_url('assets/js/jquery.modal.min.js', CYNDER_PAYMONGO_MAIN_FILE)
        );
        // wp_register_script(
        //     'woocommerce_paymongo',
        //     plugins_url('assets/js/paymongo.js', CYNDER_PAYMONGO_MAIN_FILE),
        //     array('jquery', 'cleave', 'jqueryModal')
        // );
        // wp_localize_script('woocommerce_paymongo', 'paymongo_params', $paymongoVar);

        wp_register_script(
            'woocommerce_paymongo_cc',
            plugins_url('assets/js/paymongo-cc.js', CYNDER_PAYMONGO_MAIN_FILE),
            array('jquery')
        );
        wp_localize_script('woocommerce_paymongo_cc', 'cynder_paymongo_cc_params', $paymongoCc);

        wp_register_script(
            'woocommerce_paymongo_client',
            plugins_url('assets/js/paymongo-client.js', CYNDER_PAYMONGO_MAIN_FILE),
            array('jquery')
        );
        wp_localize_script('woocommerce_paymongo_client', 'cynder_paymongo_client_params', $paymongoClient);

        wp_enqueue_style('paymongo');
        wp_enqueue_style('jqueryModal');
        // wp_enqueue_script('woocommerce_paymongo');
        wp_enqueue_script('woocommerce_paymongo_client');
        wp_enqueue_script('woocommerce_paymongo_cc');
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

        echo '<fieldset id="cynder-'.esc_attr($this->id).'-form"' .
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

        $response = wp_remote_post(
            CYNDER_PAYMONGO_BASE_URL . '/payment_intents',
            $args
        );

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

        wc_get_logger()->log('info', 'Payment Intent ID ' . $_POST['cynder_paymongo_intent_id']);
        wc_get_logger()->log('info', 'Payment Method ID ' . $_POST['cynder_paymongo_method_id']);

        return array('result' => 'failure',);

        // // we need it to get any order details
        // $order = wc_get_order($orderId);

        // $args = array(
        //     'method' => "GET",
        //     'headers' => array(
        //         'Authorization' => 'Basic ' . base64_encode($this->secret_key),
        //         'accept' => 'application/json',
        //         'content-type' => 'application/json'
        //     ),
        // );

        // // get payment intent status
        // $response = wp_remote_get(
        //     CYNDER_PAYMONGO_BASE_URL . '/payment_intents/' .
        //     $_POST['paymongo_intent_id'],
        //     $args
        // );

        // if (!is_wp_error($response)) {
        //     $body = json_decode($response['body'], true);
        //     $responseAttr = $body['data']['attributes'];
        //     $status = $responseAttr['status'];

        //     if ($status == 'succeeded') {
        //         // we received the payment
        //         $payments = $responseAttr['payments'];
        //         $order->payment_complete($payments[0]['id']);
        //         wc_reduce_stock_levels($orderId);

        //         // Sending invoice after successful payment
        //         $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);

        //         // Empty cart
        //         $woocommerce->cart->empty_cart();

        //         // Redirect to the thank you page
        //         return array(
        //             'result' => 'success',
        //             'redirect' => $this->get_return_url($order)
        //         );
        //     } else {
        //         wc_get_logger()->log('error', json_encode($body));
        //         // $messages = Cynder_PayMongo_Error_Handler::parseErrors(
        //         //     $body['errors']
        //         // );
        //         $errors = array_map(function ($error) {
        //             return Cynder_PayMongo_Error_Handler::parseError($error);
        //         }, $body['errors']);

        //         $messages = Cynder_PayMongo_Error_Handler::printErrors($errors);

        //         wc_add_notice($messages, 'error');
        //         return;
        //     }

        // } else {
        //     wc_add_notice('Connection error.', 'error');
        //     return;
        // }
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
            . '/assets/images/cards.png" alt="'
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
                'Thank You! Order has been received.'
                .' Waiting for Credit Card Confirmation',
                'woocommerce'
            );
        }

        return $text;
    }
}
