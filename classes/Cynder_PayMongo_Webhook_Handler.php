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

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[processWebhook] Webhook payload ' . wc_print_r($decoded, true));
        }

        $validEventTypes = [
            'source.chargeable',
            'payment.paid',
            'payment.failed',
        ];

        if (in_array($eventData['type'], $validEventTypes)) {
            if ($eventData['type'] === 'source.chargeable') {
                $sourceId = $resourceData['id'];
                $order = $this->getOrderByMeta('source_id', $sourceId);

                if (!$order) {
                    wc_get_logger()->log('error', '[processWebhook] No order found with source ID ' . $sourceId);
                    return;
                }

                wc_get_logger()->log('info', '[processWebhook] event: source.chargeable with source ID ' . $sourceId);

                return $this->createPaymentRecord($resourceData, $order);
            }

            $sourceType = $resourceData['attributes']['source']['type'];
            $amount = $resourceData['attributes']['amount'];

            if ($eventData['type'] === 'payment.paid' && $sourceType !== 'gcash' && $sourceType !== 'grab_pay') {
                $paymentIntentId = $resourceData['attributes']['payment_intent_id'];
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $paymentIntentId);

                if (!$order) {
                    wc_get_logger()->log('error', '[processWebhook] No order found with payment intent ID ' . $paymentIntentId);
                    return;
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

            if ($eventData['type'] === 'payment.failed' && $sourceType !== 'gcash' && $sourceType !== 'grab_pay') {
                $paymentIntentId = $resourceData['attributes']['payment_intent_id'];
                $order = $this->getOrderByMeta('paymongo_payment_intent_id', $paymentIntentId);

                wc_get_logger()->log('info', '[processWebhook] event: payment.failed with payment intent ID ' . $paymentIntentId);

                $order->update_status('failed', 'Payment failed', true);

                $this->utils->trackPaymentResolution('failed', $resourceData['id'], floatval($amount) / 100, $order->get_payment_method(), $this->testmode);

                $this->utils->callAction('cynder_paymongo_failed_payment', $resourceData);

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
     * @param object $order  Order data from woocommerce database
     * 
     * @return void
     * 
     * @link  https://developers.paymongo.com/reference#payment-source
     * @since 1.0.0
     */
    public function createPaymentRecord($source, $order)
    {
        $amount = floatval($order->get_total());
        $payment_method = $order->get_payment_method();

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[Create Payment] Creating payment for ' . $source['id'] . ' to the amount of ' . $amount);
        }

        try {
            $payment = $this->client->payment()->create($amount, $source['id'], 'source', get_bloginfo('name') . ' - ' . $order->get_id(), null, array('agent' => 'cynder_woocommerce', 'version' => CYNDER_PAYMONGO_VERSION));

            $attributes = $payment['attributes'];
            $status = $attributes['status'];
            $amount = $attributes['amount'];

            if ($status == 'paid') {
                $this->utils->completeOrder($order, $payment['id'], $this->sendInvoice);

                $this->utils->trackPaymentResolution('successful', $payment['id'], $amount, $payment_method, $this->testmode);

                $this->utils->callAction('cynder_paymongo_successful_payment', $payment);

                status_header(200);
                die();
            }

            if ($status == 'failed') {
                wc_get_logger()->log('info', 'Payment failed: ' . wc_print_r($payment, true));
                $order->update_status($status);

                $this->utils->trackPaymentResolution('failed', $payment['id'], $amount, $payment_method, $this->testmode);

                $this->utils->callAction('cynder_paymongo_failed_payment', $payment);

                status_header(400);
                die();
            }
        } catch (PaymongoException $e) {
            $formatted_messages = $e->format_errors();

            $this->utils->log('error', '[Creating Payment] Order ID: ' . $order->get_id() . ' - Response: ' . join(',', $formatted_messages));
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
