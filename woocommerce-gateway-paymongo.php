<?php
/*
 * Plugin Name: WooCommerce PayMongo Payment Gateway
 * Plugin URI: 
 * Description: Take credit card payments on your store.
 * Author: CynderTech Corp.
 * Author URI: https://cynder.io
 * Version: 1.0.0
 *
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function woocommerce_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'PayMongo requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-paymongo' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function paymongo_init_gateway_class() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_missing_wc_notice' );
		return;
	}

	define( 'WC_PAYMONGO_MAIN_FILE', __FILE__ );
	define( 'WC_PAYMONGO_VERSION', '1.0.0' );
	define( 'WC_PAYMONGO_BASE_URL',  'https://api.paymongo.com/v1' );
	

	if ( ! class_exists( 'WC_PayMongo' ) ) :
		class WC_PayMongo {
			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			private function __clone() {}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			private function __wakeup() {}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				add_action( 'admin_init', array( $this, 'install' ) );
				$this->init();
			}

			/**
			 * Initialize PayMongo plugin
			 * 
			 * @since 1.0.0
			 */
			public function init() {
				require_once dirname(__FILE__).'/classes/wc-paymongo-gateway.php';
				require_once dirname(__FILE__).'/classes/wc-paymongo-gcash-gateway.php';
				require_once dirname(__FILE__).'/classes/wc-paymongo-grabpay-gateway.php';
				require_once dirname(__FILE__).'/classes/wc-paymongo-webhook-handler.php';
				require_once dirname(__FILE__).'/classes/wc-paymongo-logger.php';

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );
				}
			}

			/**
			 * Registers Payment Gateways
			 * 
			 * @since 1.0.0
			 */
			public function add_gateways( $methods ) {
				$methods[] = 'WC_PayMongo_Gateway'; 
				$methods[] = 'WC_PayMongo_Gcash_Gateway';
				$methods[] = 'WC_PayMongo_GrabPay_Gateway';
				
				return $methods;
			}

			/**
			 * Registers Payment Gateways
			 * 
			 * @since 1.0.0
			 */
			public function filter_gateway_order_admin( $sections ) {
				unset( $sections['paymongo'] );
				unset( $sections['paymongo_gcash'] );
				unset( $sections['paymongo_grabpay'] );


				$sections['paymongo'] = __('PayMongo - Credit/Debit Card', 'woocommerce-gateway-paymongo' );
				$sections['paymongo_gcash'] = __( 'PayMongo - GCash', 'woocommerce-gateway-paymongo' );
				$sections['paymongo_grabpay'] = __( 'PayMongo - GrabPay', 'woocommerce-gateway-paymongo' );

				return $sections;
			}

			/**
			 * Install/Update function
			 * 
			 * @since 1.0.0
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_PAYMONGO_VERSION !== get_option( 'wc_paymongo_version' ) ) ) {
					do_action( 'woocommerce_paymongo_updated' );

					if ( ! defined( 'WC_PAYMONGO_INSTALLING' ) ) {
						define( 'WC_PAYMONGO_INSTALLING', true );
					}

					$this->update_plugin_version();
				}
			}

			/**
			 * Updates Plugin Version
			 * 
			 * @since 1.0.0
			 */
			public function update_plugin_version() {
				delete_option( 'wc_paymongo_version' );
				update_option( 'wc_paymongo_version', WC_PAYMONGO_VERSION );
			}

		}
	
		WC_PayMongo::get_instance();
	endif;
}

add_action( 'plugins_loaded', 'paymongo_init_gateway_class' );
