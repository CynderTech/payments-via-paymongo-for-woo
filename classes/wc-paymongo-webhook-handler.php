<?php

class WC_Paymongo_Webhook_Handler extends WC_Payment_Gateway {
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
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	
	public function __construct() {
		$main_settings = get_option('woocommerce_paymongo_payment_gateway_settings');
		$this->testmode = (!empty($main_settings['testmode']) && 'yes' === $main_settings['testmode']) ? true : false;
		$this->secret_key = $this->testmode ? $main_settings['test_secret_key'] : $main_settings['secret_key'];
		$webhook_secret_key = ($this->testmode ? 'test_' : '') . 'webhook_secret';
		$this->webhook_secret = !empty($main_settings[$webhook_secret_key]) ? $main_settings[$webhook_secret_key] : false;
		add_action('woocommerce_api_wc_paymongo', array($this, 'check_for_webhook'));
	}

	public function check_for_webhook() {
		if (('POST' !== $_SERVER['REQUEST_METHOD'])
			|| !isset($_GET['wc-api'])
			|| ('wc_paymongo' !== $_GET['wc-api'])
		) {
			status_header(400);
			die();
		}

		$request_body = file_get_contents('php://input');
		$request_headers = $this->get_request_headers();

		// Validate it to make sure it is legit.
		if ($this->is_valid_request($request_body, $request_headers)) {
			$this->process_webhook($request_body);
			status_header(200);
			die();
		} else {
			WC_Paymongo_Logger::log('Incoming webhook failed validation: ' . print_r($request_body, true));
			status_header(400);
			die();
		}
	}

	public function process_webhook($payload) {
		$decoded = json_decode($payload, true);
		$eventData = $decoded['data']['attributes'];
		$sourceData = $eventData['data'];

		if ($eventData['type'] == 'source.chargeable') {
			$order = $this->get_order_by_source($sourceData);

			if (!order) {
				status_header(404);
				die();
			}

			return $this->create_payment_record($sourceData, $order);
		}

		WC_Paymongo_Logger::log('Invalid event type = ' . $source_id);
		status_header(422);
		die();
	}

	public function create_payment_record($source, $order) {
		$createPaymentPayload = array(
			'data' => array(
				'attributes' => array(
					'amount' => intval($order->get_total() * 100, 32),
					'currency' => $order->get_currency(),
					'description' => $order->get_order_key(),
					'source' => array(
						'id' => $source['id'],
						'type' => 'source'
					),
				),
			),
		);

		$args = array(
			'body' => json_encode($createPaymentPayload),
			'method' => "POST",
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode($this->secret_key),
				'accept' => 'application/json',
				'content-type' => 'application/json'
			),
		);

		$response = wp_remote_post(WC_PAYMONGO_BASE_URL . '/payments', $args);

		if(!is_wp_error($response)) {
			$body = json_decode($response['body'], true);
			$status = $body['data']['attributes']['status'];
			
			if ($body['errors'] && $body['errors'][0]) {
				status_header($response['response']['code']);
				WC_Paymongo_Logger::log('Payment failed: ' . $body);
			}

			if ($status == 'paid') {
				$order->payment_complete($body['data']['id']);

				status_header(200);
				die();
			}

			if ($status == 'failed') {
				WC_Paymongo_Logger::log('Payment failed: ' . $response['body']);
				$order->update_status($status);
				status_header(400);
				die();
			}
		} else {
			status_header(422);
			die();
		}

		die();
	}

	// Check if sender is actually paymongo
	public function is_valid_request($payload, $headers) {
		// manually created raw signature
		$rawSignature = $this->assemble_signature($payload, $headers);

		// get saved webhook secret
		$webhookSecret = $this->webhook_secret;
		
		// hashed rawSignature
		$encryptedSignature = hash_hmac('sha256', $rawSignature, $webhookSecret);

		$requestSignature = $this->testmode ? $this->get_from_paymongo_signature('test', $headers) : $this->get_from_paymongo_signature('live', $headers);
		
		return $encryptedSignature == $requestSignature;
	}

	// Assemble Raw Signature
	public function assemble_signature($payload, $headers) {
		$timestamp = $this->get_from_paymongo_signature('timestamp', $headers);
		
		$raw = $timestamp . '.' . $payload;

		return $raw;
	}

	/** 
	* Get Property from Paymongo-Signature Header
	* @param key('timestamp', 'live', 'test')
	*/
	public function get_from_paymongo_signature($key, $headers) {
		$signature = $headers["Paymongo-Signature"];
		$explodedSignature = explode(',', $signature);

		if ($key == 'timestamp') {
			$explodedTimestamp = explode('=', $explodedSignature[0]);
			return $explodedTimestamp[1];
		}

		if ($key == 'test') {
			$explodedTest = explode('=', $explodedSignature[1]);
			return $explodedTest[1];
		}

		if ($key == 'live') {
			$explodedLive = explode('=', $explodedSignature[2]);
			return $explodedLive[1];
		}
	}

	// get request headers
	public function get_request_headers() {
		if (!function_exists('getallheaders')) {
			$headers = array();

			foreach ($_SERVER as $name => $value) {
				if ('HTTP_' === substr($name, 0, 5)) {
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

	// Get Order by Source
	public function get_order_by_source($source) {
		$source_id = $source['id'];

		$orders = wc_get_orders(
			array(
				'limit' => 1, // Query all orders
				'meta_key' => 'source_id', // The postmeta key field
				'meta_value' => $source_id, // The comparison argument
			)
		);

		if (empty($orders)) {
			WC_Paymongo_Logger::log('Failed to find order with source_id = ' . $source_id);
			
			return false;
		}
			
		return $orders[0];
	}
}

WC_Paymongo_Webhook_Handler::get_instance();
