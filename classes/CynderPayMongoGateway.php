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

namespace Cynder\PayMongo;

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
class CynderPayMongoGateway extends CynderPayMongoPaymentIntentGateway
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
        $this->id = PAYMONGO_CARD;
        $this->has_fields = true;
        $this->method_title = 'Card Payments via PayMongo';
        $this->method_description = 'Simple and easy payments '
            . 'with Credit/Debit Card';

        parent::__construct();

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
        );
    }
    
    public function getPaymentMethodId($orderId)
    {
        $paymentMethodId = $_POST['cynder_paymongo_method_id'];
        return $paymentMethodId;
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
        $isCheckout = is_checkout() && !is_checkout_pay_page();
        $isOrderPay = is_checkout_pay_page();

        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!$isCheckout && !$isOrderPay) {
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

        $unsecure = !$this->testmode && !is_ssl();

        // if unsecure and in live mode, show warning
        if ($unsecure) {
            wc_add_notice('WARNING: This website is not secured to transact using PayMongo.', 'error');
        }

        $paymongoVar = array();
        $paymongoVar['publicKey'] = $this->public_key;

        $paymongoClient = array();
        $paymongoClient['home_url'] = get_home_url();
        $paymongoClient['public_key'] = $this->public_key;

        $paymongoCc = array();
        $paymongoCc['isCheckout'] = $isCheckout;
        $paymongoCc['isOrderPay'] = $isOrderPay;
        $paymongoCc['total_amount'] = WC()->cart->get_totals()['total'];

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
        wp_enqueue_script(
            'cleave',
            plugins_url('assets/js/cleave.min.js', CYNDER_PAYMONGO_MAIN_FILE)
        );

        wp_register_script(
            'woocommerce_paymongo_checkout',
            plugins_url('assets/js/paymongo-checkout.js', CYNDER_PAYMONGO_MAIN_FILE),
            array('jquery')
        );

        wp_register_script(
            'woocommerce_paymongo_cc',
            plugins_url('assets/js/paymongo-cc.js', CYNDER_PAYMONGO_MAIN_FILE),
            array('jquery', 'cleave')
        );
        wp_localize_script('woocommerce_paymongo_cc', 'cynder_paymongo_cc_params', $paymongoCc);

        wp_register_script(
            'woocommerce_paymongo_client',
            plugins_url('assets/js/paymongo-client.js', CYNDER_PAYMONGO_MAIN_FILE),
            array('jquery')
        );
        wp_localize_script('woocommerce_paymongo_client', 'cynder_paymongo_client_params', $paymongoClient);

        wp_enqueue_style('paymongo');
        wp_enqueue_script('woocommerce_paymongo_checkout');
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

        $pluginDir = plugin_dir_path(CYNDER_PAYMONGO_MAIN_FILE);

        include $pluginDir . '/classes/cc-fields.php';

        do_action('woocommerce_credit_card_form_end', $this->id);

        echo '<div class="clear"></div></fieldset>';
    }

    public function validate_fields() // phpcs:ignore
    {
        return true;
    }
}
