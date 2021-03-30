<?php
/**
 * PHP version 7
 * 
 * Handles all incoming webhooks for PayMongo
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
 * PHP version 7
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Webhook_Handler extends WC_Payment_Gateway
{
    /**
     * Singleton
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
     * Starting point of the webhook handler
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
        $this->testmode = (!empty($testMode) && $testMode === 'yes') ? true : false;

        $skKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
        $this->secret_key = get_option($skKey);

        $wsKey = $this->testmode ? 'paymongo_test_webhook_secret_key' : 'paymongo_webhook_secret_key';

        $this->webhook_secret = get_option($wsKey);

        add_action(
            'woocommerce_api_cynder_paymongo',
            array($this, 'checkForWebhook')
        );

        add_filter(
            'woocommerce_order_data_store_cpt_get_orders_query',
            array($this, 'queryOrderBySource'),
            10,
            2
        );
    }

    /**
     * Check incoming request for PayMongo request data
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function checkForWebhook()
    {
        if (('POST' !== $_SERVER['REQUEST_METHOD'])
            || !isset($_GET['wc-api'])
            || ('cynder_paymongo' !== $_GET['wc-api'])
        ) {
            status_header(400);
            die();
        }

        $requestBody = file_get_contents('php://input');
        $requestHeaders = $this->getRequestHeaders();

        // Validate it to make sure it is legit.
        if ($this->isValidRequest($requestBody, $requestHeaders)) {
            $this->processWebhook($requestBody);
            status_header(200);
            die();
        } else {
            wc_get_logger()->log('error', '[checkForWebhook] ' . ' ' . wc_print_r($requestBody, true));
            status_header(400);
            die();
        }
    }

    /**
     * Actual Processing of webhook request
     * 
     * @param string $payload JSON String
     * 
     * @return void;
     * 
     * @link  https://developers.paymongo.com/docs/webhooks-2#section-2-respond-to-the-webhook-event
     * @since 1.0.0
     */
    public function processWebhook($payload)
    {
        global $woocommerce;

        $decoded = json_decode($payload, true);
        $eventData = $decoded['data']['attributes'];
        $resourceData = $eventData['data'];

        // wc_get_logger()->log('info', '[processWebhook] Webhook payload ' . wc_print_r($decoded, true));

        $validEventTypes = [
            'source.chargeable',
            'payment.paid',
            'payment.failed',
        ];

        if (in_array($eventData['type'], $validEventTypes)) {
            if ($eventData['type'] === 'source.chargeable') {
                $order = $this->getOrderByMeta('source_id', $resourceData['id']);

                return $this->createPaymentRecord($resourceData, $order);
            }

            $sourceType = $resourceData['attributes']['source']['type'];

            if ($eventData['type'] === 'payment.paid' && $sourceType !== 'gcash' && $sourceType !== 'grab_pay') {
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $resourceData['attributes']['payment_intent_id']);

                $order->payment_complete($resourceData['id']);
                $orderId = $order->get_id();
                wc_reduce_stock_levels($orderId);
        
                // Sending invoice after successful payment
                $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);
                return;
            }

            if ($eventData['type'] === 'payment.failed' && $sourceType !== 'gcash' && $sourceType !== 'grab_pay') {
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $resourceData['attributes']['payment_intent_id']);

                $order->update_status('failed', 'Payment failed', true);
                return;
            }

            wc_get_logger()->log('info', '[processWebhook] Passthrough event type ' . $eventData['type'] . ' with source type ' . $sourceType);
            return;
        }

        wc_get_logger()->log('error', '[processWebhook] Invalid event type = ' . $eventData['type']);
        status_header(422);
        die();
    }

    /**
     * Creates PayMongo Payment Record
     * 
     * @param array $source Source data from event data sent by paymongo
     * @param array $order  Order data from woocommerce database
     * 
     * @return void
     * 
     * @link  https://developers.paymongo.com/reference#payment-source
     * @since 1.0.0
     */
    public function createPaymentRecord($source, $order)
    {
        global $woocommerce;

        $createPaymentPayload = array(
            'data' => array(
                'attributes' => array(
                    'amount' => intval($order->get_total() * 100, 32),
                    'currency' => $order->get_currency(),
                    'description' => get_bloginfo('name') . ' - ' . $order->get_id(),
                    'source' => array(
                        'id' => $source['id'],
                        'type' => 'source'
                    ),
                ),
            ),
        );

        // wc_get_logger()->log('info', 'Payment payload ' . wc_print_r($createPaymentPayload, true));

        $args = array(
            'body' => json_encode($createPaymentPayload),
            'method' => "POST",
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->secret_key),
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
        );

        $response = wp_remote_post(CYNDER_PAYMONGO_BASE_URL . '/payments', $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);
            
            if (array_key_exists('errors', $body) && $body['errors'][0]) {
                status_header($response['response']['code']);
                wc_get_logger()->log('Payment failed: ' . $body);
                die();
            }

            $status = $body['data']['attributes']['status'];

            if ($status == 'paid') {
                $order->payment_complete($body['data']['id']);

                // Sending invoice after successful payment
                $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order->get_order_number());

                status_header(200);
                die();
            }

            if ($status == 'failed') {
                wc_get_logger()->log('Payment failed: ' . $response['body']);
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

    /**
     * Checks if request is from paymongo servers
     * 
     * @param array $payload Source data from event data sent by paymongo
     * @param array $headers Request headers
     * 
     * @return bool
     * 
     * @link  https://developers.paymongo.com/docs/webhooks-2#section-3-securing-a-webhook-optional-but-highly-recommended
     * @since 1.0.0
     */
    public function isValidRequest($payload, $headers)
    {
        // manually created raw signature
        $rawSignature = $this->assembleSignature($payload, $headers);

        // get saved webhook secret
        $webhookSecret = $this->webhook_secret;
        
        // hashed rawSignature
        $encryptedSignature = hash_hmac('sha256', $rawSignature, $webhookSecret);

        $requestSignature = $this->testmode ?
            $this->getFromPayMongoSignature('test', $headers)
            : $this->getFromPayMongoSignature('live', $headers);
        

        return $encryptedSignature == $requestSignature;
    }

    /**
     * Combines timestamp and payload to be hashed
     * 
     * @param array $payload Source data from event data sent by paymongo
     * @param array $headers request headers
     * 
     * @return string
     * 
     * @link  https://developers.paymongo.com/docs/webhooks-2#section-3-securing-a-webhook-optional-but-highly-recommended
     * @since 1.0.0
     */
    public function assembleSignature($payload, $headers)
    {
        $timestamp = $this->getFromPayMongoSignature('timestamp', $headers);
        
        $raw = $timestamp . '.' . $payload;

        return $raw;
    }

    /**
     * Get Property from PayMongo-Signature Header
     *
     * @param string $key     values('timestamp', 'live', 'test')
     * @param array  $headers request headers
     * 
     * @return string
     * 
     * @link  https://developers.paymongo.com/docs/webhooks-2#section-3-securing-a-webhook-optional-but-highly-recommended
     * @since 1.0.0
     */
    public function getFromPayMongoSignature($key, $headers)
    {
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

    /** 
     * Gets request headers
     *
     * @return array
     * 
     * @since 1.0.0
     */
    public function getRequestHeaders()
    {
        if (!function_exists('getallheaders')) {
            $headers = array();

            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $headerKey = str_replace(
                        ' ',
                        '-',
                        ucwords(
                            strtolower(str_replace('_', ' ', substr($name, 5)))
                        )
                    );
                    $headers[$headerKey] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }

    /** 
     * Get Order by meta values
     *
     * @param string $metaKey Metadata key
     * @param string $metaValue Metadata value
     *
     * @return object,bool
     *
     * @since 1.5.0
     */
    public function getOrderByMeta($metaKey, $metaValue)
    {
        // wc_get_logger()->log('info', 'Meta key ' . $metaKey);
        // wc_get_logger()->log('info', 'Meta value ' . $metaValue);

        $queryParams = array('limit' => 1);
        $queryParams[$metaKey] = $metaValue;

        $orders = wc_get_orders($queryParams);

        if (empty($orders)) {
            wc_get_logger()->log('error', '[getOrderBySource] Failed to find order with metadata ID ' . $metaKey . ' ' . $metaValue);
            return false;
        }

        return $orders[0];
    }

    public function queryOrderBySource($query, $query_vars) {
        $validPaymongoMeta = ['source_id', 'paymongo_payment_intent_id'];

        foreach ($validPaymongoMeta as $metaKey) {
            if ( ! empty( $query_vars[$metaKey] ) ) {
                $query['meta_query'][] = array(
                    'key' => $metaKey,
                    'value' => esc_attr( $query_vars[$metaKey] ),
                );
            }
        }

        // wc_get_logger()->log('info', 'Query ' . wc_print_r($query, true));

        return $query;
    }
}

Cynder_PayMongo_Webhook_Handler::getInstance();
