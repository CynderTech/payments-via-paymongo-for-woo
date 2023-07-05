<?php
/**
 * PHP version 7
 * Plugin Name: Payments via PayMongo for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/payments-via-paymongo-for-woo/
 * Description: Take credit card, GCash, GrabPay and PayMaya payments via PayMongo.
 * Author: CynderTech
 * Author URI: http://cynder.io
 * Version: 1.12.4
 * Requires at least: 5.3.2
 * Tested up to: 6.2
 * WC requires at least: 3.9.3
 * WC tested up to: 7.8.2
 *
 * @category Plugin
 * @package  CynderTech
 * @author   CynderTech <hello@cynder.io>
 * @license  GPLv3 (https://www.gnu.org/licenses/gpl-3.0.html)
 * @link     n/a
 */

include_once 'paymongo-constants.php';
require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';

use PostHog\PostHog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function Woocommerce_Missing_Cynder_notice()
{
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(
        esc_html__(
            'PayMongo requires WooCommerce to be '
            . 'installed and active. You can download %s here.',
            'woocommerce-gateway-paymongo'
        ),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    ) . '</strong></p></div>';
}

/**
 * Initialize Paymongo Gateway Class
 *
 * @return string
 */
function Paymongo_Init_Gateway_class()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'Woocommerce_Missing_Cynder_notice');
        return;
    }

    define('CYNDER_PAYMONGO_MAIN_FILE', __FILE__);
    define('CYNDER_PAYMONGO_VERSION', '1.12.4');
    define(
        'CYNDER_PAYMONGO_PLUGIN_URL',
        untrailingslashit(
            plugins_url(
                basename(plugin_dir_path(__FILE__)),
                basename(__FILE__)
            )
        )
    );

    PostHog::init('phc_zC7px2IrSCO7SlSVEb250VISscWfwvBPafWJOYJsUhv', array('host' => 'https://app.posthog.com'));
    

    if (!class_exists('Cynder_PayMongo')) :
        /**
         * Paymongo Class
         * 
         * @category Class
         * @package  PayMongo
         * @author   PayMongo <devops@cynder.io>
         * @license  n/a (http://127.0.0.0)
         * @link     n/a
         * @phpcs:disable Standard.Cat.SniffName
         */
        class Cynder_PayMongo
        {
            /**
             * *Singleton* instance of this class
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
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            public function __clone()
            {
                // empty
            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup()
            {
                // empty
            }

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            private function __construct()
            {
                add_action('admin_init', array($this, 'install'));
                $this->init();
            }

            /**
             * Initialize PayMongo plugin
             * 
             * @return void
             * 
             * @since 1.0.0
             */
            public function init()
            {
                include_once 'paymongo-top-level-hooks.php';
                include_once dirname(__FILE__) . '/classes/Cynder_PayMongo_Webhook_Handler.php';

                add_filter(
                    'woocommerce_payment_gateways',
                    array($this, 'addGateways')
                );

                if (version_compare(WC_VERSION, '3.4', '<')) {
                    add_filter(
                        'woocommerce_get_sections_checkout',
                        array($this, 'filterGatewayOrderAdmin')
                    );
                }
            }

            /**
             * Registers Payment Gateways
             * 
             * @param $methods array of methods
             * 
             * @return array
             * 
             * @since 1.0.0
             */
            public function addGateways($methods)
            {
                $methods[] = 'Cynder\\PayMongo\\CynderPayMongoGateway';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_Gcash_Gateway';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_GrabPay_Gateway';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_PayMaya';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_Atome';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_Bpi';
                $methods[] = 'Cynder\\PayMongo\\Cynder_PayMongo_BillEase';
                
                return $methods;
            }

            /**
             * Registers Payment Gateways
             * 
             * @param array $sections array of sections
             * 
             * @return array
             * 
             * @since 1.0.0
             */
            public function filterGatewayOrderAdmin($sections) 
            {
                foreach (PAYMONGO_PAYMENT_METHODS as $method) {
                    unset($sections[$method]);
                }

                $gatewayName = 'woocommerce-gateway-paymongo';

                foreach (PAYMONGO_PAYMENT_METHOD_LABELS as $method => $label) {
                    $sections[$method] = __($label, $gatewayName);
                }

                return $sections;
            }

            /**
             * Install/Update function
             * 
             * @return void
             * 
             * @since 1.0.0
             */
            public function install()
            {
                if (!is_plugin_active(plugin_basename(__FILE__))) {
                    return;
                }

                if (!defined('IFRAME_REQUEST')) {
                    do_action('woocommerce_paymongo_updated');

                    if (!defined('CYNDER_PAYMONGO_INSTALLING')) {
                        define('CYNDER_PAYMONGO_INSTALLING', true);
                    }

                    $this->updatePluginVersion();
                }
            }

            /**
             * Updates Plugin Version
             * 
             * @return void
             * 
             * @since 1.0.0
             */
            public function updatePluginVersion()
            {
                delete_option('cynder_paymongo_version');
                update_option('cynder_paymongo_version', CYNDER_PAYMONGO_VERSION);
            }

        }
    
        Cynder_PayMongo::getInstance();
    endif;
}

add_action('plugins_loaded', 'paymongo_init_gateway_class');
