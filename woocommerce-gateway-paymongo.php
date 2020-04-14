<?php
/**
 * PHP version 7
 * Plugin Name: WooCommerce PayMongo Payment Gateway
 * Plugin URI: 
 * Description: Take credit card payments on your store.
 * Author: PayMongo
 * Author URI: http://paymongo.com
 * Version: 1.0.0
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <developers@paymongo.com>
 * @license  n/a (http://127.0.0.0)
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
function Woocommerce_Missing_WC_notice()
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
        add_action('admin_notices', 'Woocommerce_Missing_WC_notice');
        return;
    }

    define('WC_PAYMONGO_MAIN_FILE', __FILE__);
    define('WC_PAYMONGO_VERSION', '1.0.0');
    define('WC_PAYMONGO_BASE_URL',  'https://api.paymongo.com/v1');
    

    if (!class_exists('WC_PayMongo')) :
        /**
         * Paymongo Class
         * 
         * @category Class
         * @package  PayMongo
         * @author   PayMongo <developers@paymongo.com>
         * @license  n/a (http://127.0.0.0)
         * @link     n/a
         * @phpcs:disable Standard.Cat.SniffName
         */
        class WC_PayMongo
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
                include_once $fileDir.'/classes/wc-paymongo-gateway.php';
                include_once $fileDir.'/classes/wc-paymongo-gcash-gateway.php';
                include_once $fileDir.'/classes/wc-paymongo-grabpay-gateway.php';
                include_once $fileDir.'/classes/wc-paymongo-webhook-handler.php';
                include_once $fileDir.'/classes/wc-paymongo-error-handler.php';
                include_once $fileDir.'/classes/wc-paymongo-logger.php';

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
                $methods[] = 'WC_PayMongo_Gateway'; 
                $methods[] = 'WC_PayMongo_Gcash_Gateway';
                $methods[] = 'WC_PayMongo_GrabPay_Gateway';
                
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
                unset($sections['paymongo_grabpay']);

                $gatewayName = 'woocommerce-gateway-paymongo';
                $sections['paymongo'] = __(
                    'PayMongo - Credit/Debit Card',
                    $gatewayName
                );
                $sections['paymongo_gcash'] = __(
                    'PayMongo - GCash', 
                    $gatewayName
                );
                $sections['paymongo_grabpay'] = __(
                    'PayMongo - GrabPay', 
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

                if (!defined('IFRAME_REQUEST')
                    && (WC_PAYMONGO_VERSION !== get_option('wc_paymongo_version'))
                ) {
                    do_action('woocommerce_paymongo_updated');

                    if (!defined('WC_PAYMONGO_INSTALLING')) {
                        define('WC_PAYMONGO_INSTALLING', true);
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
                delete_option('wc_paymongo_version');
                update_option('wc_paymongo_version', WC_PAYMONGO_VERSION);
            }

        }
    
        WC_PayMongo::getInstance();
    endif;
}

add_action('plugins_loaded', 'paymongo_init_gateway_class');
