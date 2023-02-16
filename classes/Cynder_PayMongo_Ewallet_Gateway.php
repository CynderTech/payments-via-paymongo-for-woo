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

namespace Cynder\PayMongo;

use WC_Payment_Gateway;
use Paymongo\Phaymongo\Phaymongo;
use Cynder\PayMongo\Source;

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

    private $client;

    protected $testmode;
    protected $secret_key;
    protected $public_key;
    protected $debugMode;
    protected $source;
    protected $utils;
    protected $ewallet_type;
    protected $icon_name;

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

        $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
        $this->debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->initFormFields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        wc_get_logger()->log('info', 'WALLET TYPE ' . $this->ewallet_type);

        $this->client = new Phaymongo($this->public_key, $this->secret_key, []);
        $this->utils = new Utils();
        $this->source = new Source($this->ewallet_type, $this->utils, $debugMode, $testMode, $this->client, CYNDER_PAYMONGO_VERSION);
    }

    /** Override this function on certain wallets */
    public function initFormFields() {

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

        return $this->source->processPayment($order);
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
            . '/assets/images/' . $this->icon_name . '.png" class="paymongo-method-logo paymongo-cards-icon" alt="'
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
