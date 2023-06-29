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

namespace Cynder\PayMongo;

use GuzzleHttp\Exception\ClientException;
use Paymongo\Phaymongo\PaymongoException;
use Paymongo\Phaymongo\Phaymongo;
use PostHog\PostHog;
use WC_Payment_Gateway;
use WC_Order;

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

    private $client;

    protected $testmode;
    protected $public_key;
    protected $secret_key;
    protected $webhook_secret;
    protected $sendInvoice;
    protected $debugMode;
    protected $utils;

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

        $pkKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
        $skKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
        $this->public_key = get_option($pkKey);
        $this->secret_key = get_option($skKey);

        $wsKey = $this->testmode ? 'paymongo_test_webhook_secret_key' : 'paymongo_webhook_secret_key';

        $this->webhook_secret = get_option($wsKey);

        $sendInvoice = get_option('woocommerce_cynder_paymongo_send_invoice_after_payment');
        $this->sendInvoice = (!empty($sendInvoice) && $sendInvoice === 'yes') ? true : false;

        $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
        $this->debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;

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

        $this->client = new Phaymongo($this->public_key, $this->secret_key);
        $this->utils = new Utils();
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

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[checkForWebhook] Headers ' . ' ' . wc_print_r($requestHeaders, true));
        }

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
        $resourceAttributes = $resourceData['attributes'];
        $paymentIntentId = $resourceAttributes['payment_intent_id'];
        $resourceMetadata = $resourceAttributes['metadata'];

        $shopName = get_bloginfo('name');
        $orderId = $resourceMetadata['order_id'];
        $order = wc_get_order($orderId);

        if (!$order) {
            $this->utils->log('error', 'No order found for order ID ' . $orderId);
            status_header(400);
            die();
        }

        $customer = $order->get_customer_id();

        if ($resourceMetadata['agent'] !== 'cynder_woocommerce' || !isset($paymentIntentId) || empty($paymentIntentId)) {
            $this->utils->log('error', 'No payment intent ID found for payment ID ' . $resourceData['id']);
            return;
        }

        $metaKeysToCheck = array('store_name', 'customer_id');

        $metadataMap = array(
            'store_name' => array(
                'tag' => 'shop',
                'value' => $shopName,
            ),
            'customer_id' => array(
                'tag' => 'customer ID',
                'value' => strval($customer),
            ),
        );

        foreach ($metaKeysToCheck as $key) {
            $originalValue = $resourceMetadata[$key];
            $metadataMapItem = $metadataMap[$key];
            $metaValue = $metadataMapItem['value'];
            $metaTag = $metadataMapItem['tag'];

            if ($originalValue !== $metaValue) {
                $this->utils->log('warning', 'Paymen Intent ID ' . $paymentIntentId . ' did not originate from ' . $metaTag . ' ' . $metaValue . ' but originated from ' . $metaTag . ' ' . $originalValue);
                status_header(400);
                die();
            }
        }

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[processWebhook] Webhook payload ' . wc_print_r($decoded, true));
        }

        $validEventTypes = [
            'payment.paid',
            'payment.failed',
        ];

        if (in_array($eventData['type'], $validEventTypes)) {
            $sourceType = $resourceAttributes['source']['type'];
            $amount = $resourceAttributes['amount'];

            if ($eventData['type'] === 'payment.paid') {
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $paymentIntentId);

                if (!$order) {
                    wc_get_logger()->log('error', '[processWebhook] No order found with payment intent ID ' . $paymentIntentId);
                    return;
                }

                if ($this->debugMode) {
                    $this->utils->log('info', '[processWebhook] Found Order ID! ' . $order->get_id());
                }

                wc_get_logger()->log('info', '[processWebhook] event: payment.paid with payment intent ID ' . $paymentIntentId);

                /**
                 * Only process unpaid orders -- this would happen if payment intent has processing
                 * status on redirect from the payment authorization page back to the woocommerce shop
                 * 
                 * Any paid orders should be ignored
                 */
                if (!$order->is_paid()) {
                    $this->utils->completeOrder($order, $resourceData['id'], $this->sendInvoice);

                    $this->utils->trackPaymentResolution('successful', $resourceData['id'], floatval($amount) / 100, $order->get_payment_method(), $this->testmode);

                    $this->utils->callAction('cynder_paymongo_successful_payment', $resourceData);
                }
                return;
            }

            if ($eventData['type'] === 'payment.failed') {
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $paymentIntentId);

                wc_get_logger()->log('info', '[processWebhook] event: payment.failed with payment intent ID ' . $paymentIntentId);

                if (!$order) {
                    wc_get_logger()->log('error', '[processWebhook] No order found with payment intent ID ' . $paymentIntentId);
                    return;
                }

                /**
                 * Only unpaid orders should be processed for failed payments
                 */
                if (!$order->is_paid()) {
                    $order->update_status('failed', 'Payment failed', true);
    
                    $this->utils->trackPaymentResolution('failed', $resourceData['id'], floatval($amount) / 100, $order->get_payment_method(), $this->testmode);
    
                    $this->utils->callAction('cynder_paymongo_failed_payment', $resourceData);
                }

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

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[isValidRequest] Raw Signature ' . wc_print_r($rawSignature, true));
        }

        // get saved webhook secret
        $webhookSecret = $this->webhook_secret;
        
        // hashed rawSignature
        $encryptedSignature = hash_hmac('sha256', $rawSignature, $webhookSecret);

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[isValidRequest] Encrypted Signature ' . wc_print_r($encryptedSignature, true));
        }

        $requestSignature = $this->testmode ?
            $this->getFromPayMongoSignature('test', $headers)
            : $this->getFromPayMongoSignature('live', $headers);

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[isValidRequest] Request Signature ' . wc_print_r($requestSignature, true));
        }

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
        $signature = $headers["paymongo-signature"];
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
                        strtolower(str_replace('_', ' ', substr($name, 5)))
                    );
                    $headers[$headerKey] = $value;
                }
            }
        } else {
            $originalHeaders = getallheaders();

            foreach($originalHeaders as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }

        return $headers;
    }

    /** 
     * Get Order by meta values
     *
     * @param string $metaKey Metadata key
     * @param string $metaValue Metadata value
     *
     * @return WC_Order
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
