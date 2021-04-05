<?php
/**
 * PHP version 7
 * Plugin Name: Payments via PayMongo for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/payments-via-paymongo-for-woo/
 * Description: Take credit card, GCash and GrabPay payments via PayMongo.
 * Author: CynderTech
 * Author URI: http://cynder.io
 * Version: 1.5.2
 * Requires at least: 5.3.2
 * Tested up to: 5.7
 * WC requires at least: 3.9.3
 * WC tested up to: 5.1.0
 *
 * @category Plugin
 * @package  CynderTech
 * @author   CynderTech <hello@cynder.io>
 * @license  GPLv3 (https://www.gnu.org/licenses/gpl-3.0.html)
 * @link     n/a
 */

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
    define('CYNDER_PAYMONGO_VERSION', '1.5.2');
    define('CYNDER_PAYMONGO_BASE_URL',  'https://api.paymongo.com/v1');
    define(
        'CYNDER_PAYMONGO_PLUGIN_URL',
        untrailingslashit(
            plugins_url(
                basename(plugin_dir_path(__FILE__)),
                basename(__FILE__)
            )
        )
    );
    

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
            private function __clone()
            {
                // empty
            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            private function __wakeup()
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
                $fileDir = dirname(__FILE__);
                include_once $fileDir.'/classes/cynder-paymongo-gateway.php';
                include_once $fileDir.'/classes/cynder-paymongo-ewallet-base.php';
                include_once $fileDir.'/classes/cynder-paymongo-gcash-gateway.php';
                include_once $fileDir.'/classes/cynder-paymongo-grabpay-gateway.php';
                include_once $fileDir.'/classes/cynder-paymongo-webhook-handler.php';
                include_once 'paymongo-top-level-hooks.php';

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
                $methods[] = 'Cynder_PayMongo_Gateway';
                $methods[] = 'Cynder_PayMongo_Gcash_Gateway';
                $methods[] = 'Cynder_PayMongo_GrabPay_Gateway';
                
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
                unset($sections['paymongo']);
                unset($sections['paymongo_gcash']);
                unset($sections['paymongo_grab_pay']);

                $gatewayName = 'woocommerce-gateway-paymongo';
                $sections['paymongo'] = __(
                    'Credit/Debit Card via PayMongo',
                    $gatewayName
                );

                $sections['paymongo_gcash'] = __(
                    'GCash via PayMongo',
                    $gatewayName
                );

                $sections['paymongo_grab_pay'] = __(
                    'GrabPay via PayMongo',
                    $gatewayName
                );

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
