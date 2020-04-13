<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_PayMongo_Gcash_Gateway extends WC_Payment_Gateway {
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
	 * Starting point of the payment gateway
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id = 'paymongo_gcash_payment_gateway';
		$this->icon = 'https://b.paymongocdn.com/images/logo-with-text.png';
		$this->has_fields = true;
		$this->method_title = 'PayMongo GCash Gateway';
		$this->method_description = 'Simple and easy payments with GCash.';

		$this->supports = array(
			'products'
		);

		$this->init_form_fields();

		$this->init_settings();

		$main_settings = get_option('woocommerce_paymongo_payment_gateway_settings');

		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->testmode = (!empty($main_settings['testmode']) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->public_key = !empty($main_settings['public_key']) ? $main_settings['public_key'] : '';
		$this->secret_key = !empty($main_settings['secret_key']) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = !empty($main_settings['statement_descriptor']) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->public_key = ! empty( $main_settings['test_public_key'] ) ? $main_settings['test_public_key'] : '';
			$this->secret_key = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));		add_action('woocommerce_api_paymongo_gcash', array($this, 'gcash_handler'));
		add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

		if ( 'yes' === $this->enabled ) {
			add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'order_received_text' ), 10, 2 );
		}
	}

	/**
	 * Payment Gateway Settings Page Fields
	 * 
	 * @since 1.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable PayMongo GCash Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'GCash - PayMongo',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Simple and easy payments.',
			),
		);
	}

	/**
	 * Registers scripts and styles for payment gateway
	 * 
	 * @since 1.0.0
	 */
	public function payment_scripts() { 
		// we need JavaScript to process a token only on cart/checkout pages, right?
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}
	
		// if our payment gateway is disabled, we do not have to enqueue JS too
		if ( 'no' === $this->enabled ) {
			return;
		}
	
		// no reason to enqueue JavaScript if API keys are not set
		if (!$this->testmode && (empty( $this->secret_key ) || empty( $this->public_key )) ) {
			return;
		}
	
		// do not work with card detailes without SSL unless your website is in a test mode
		if ( ! $this->testmode && ! is_ssl() ) {
			return;
		}
		
		if (!wp_script_is('woocommerce_paymongo', 'enqueued')) {
			wp_register_script('woocommerce_paymongo', plugins_url('assets/js/paymongo.js', WC_PAYMONGO_MAIN_FILE), array('jquery'));
			wp_localize_script('woocommerce_paymongo', 'paymongo_params', array(
				'publicKey' => $this->public_key,
				'billing_first_name' => $order->get_billing_first_name(),
				'billing_last_name' => $order->get_billing_last_name(),
				'billing_address_1' => $order->get_billing_address_1(),
				'billing_address_2' => $order->get_billing_address_2(),
				'billing_state' => $order->get_billing_state(),
				'billing_city' => $order->get_billing_city(),
				'billing_postcode' => $order->get_billing_postcode(),
				'billing_country' => $order->get_billing_country(),
			));
				
			wp_enqueue_style('paymongo');
			wp_enqueue_script('woocommerce_paymongo');
		}
	}

	/**
	 * Creates PayMongo GCash source
	 * 
	 * @link https://developers.paymongo.com/reference#the-sources-object
	 * @since 1.0.0
	 */
	public function process_payment($order_id) {
		global $woocommerce;

		$order = wc_get_order($order_id);

		$isPayForOrder = isset($_GET['pay_for_order']) && 'true' === $_GET['pay_for_order'];

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
							'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
							'email' => $order->get_billing_email(),
							'phone' => $order->get_billing_phone(),
						),
						'redirect' => array(
							'success' => add_query_arg('paymongo', 'gcash_pending', $this->get_return_url($order)),
							'failed' => add_query_arg('paymongo', 'gcash_failed', $isPayForOrder ? $order->get_checkout_payment_url() : wc_get_checkout_url()),
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

		$response = wp_remote_post(WC_PAYMONGO_BASE_URL . '/sources', $args);

		if(!is_wp_error($response)) {
			$body = json_decode($response['body'], true);

			if (
				$body
				&& array_key_exists('data', $body)
				&& array_key_exists('attributes', $body['data'])
				&& array_key_exists('status', $body['data']['attributes'])
				&& $body['data']['attributes']['status'] == 'pending') {

				$order->add_meta_data('source_id', $body['data']['id']);
				$order->update_status('pending');
				wc_reduce_stock_levels($order_id);
				$woocommerce->cart->empty_cart();

				wp_send_json(
					array(
						'result' => 'success',
						'checkout_url' => $body['data']['attributes']['redirect']['checkout_url'],
					)
				);

				return;
			} else {
				wp_send_json(
					array(
						'result' => 'error',
						'data' => $body,
					)
				);
				return;
			}
		} else {
			wp_send_json(
				array(
					'result' => 'error',
				)
			);
			return;
		}
	}

	/**
	 * Custom GCash order received text.
	 *
	 * @param string   $text Default text.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function order_received_text( $text, $order ) {
		if ( $order && $this->id === $order->get_payment_method() ) {
			return esc_html__( 'Thank You! Order has been received. Waiting for GCash Confirmation', 'woocommerce' );
		}

		return $text;
	}
}