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
        $main_settings = get_option('woocommerce_paymongo_settings');
        $this->testmode = (
            !empty($main_settings['testmode'])
            && 'yes' === $main_settings['testmode']
        ) ? true : false;
        $this->secret_key = $this->testmode ? 
            $main_settings['test_secret_key']
            : $main_settings['secret_key'];
        $this->webhook_secret = $this->testmode ?
            get_option('paymongo_test_webhook_secret_key')
            : get_option('paymongo_webhook_secret_key');

        add_action(
            'woocommerce_api_cynder_paymongo',
            array($this, 'checkForWebhook')
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
            Cynder_PayMongo_Logger::log(
                'Incoming webhook failed validation: ' . print_r($requestBody, true)
            );
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
        $decoded = json_decode($payload, true);
        $eventData = $decoded['data']['attributes'];
        $sourceData = $eventData['data'];

        if ($eventData['type'] == 'source.chargeable') {
            $order = $this->getOrderBySource($sourceData);

            if (!order) {
                status_header(404);
                die();
            }

            return $this->createPaymentRecord($sourceData, $order);
        }

        Cynder_PayMongo_Logger::log('Invalid event type = ' . $source_id);
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

        $response = wp_remote_post(CYNDER_PAYMONGO_BASE_URL . '/payments', $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);
            
            if (array_key_exists('errors', $body) && $body['errors'][0]) {
                status_header($response['response']['code']);
                Cynder_PayMongo_Logger::log('Payment failed: ' . $body);
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
                Cynder_PayMongo_Logger::log('Payment failed: ' . $response['body']);
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
     * Get Order by source record
     *
     * @param string $source Source object from $payload
     *
     * @return object,bool
     *
     * @since 1.0.0
     */
    public function getOrderBySource($source)
    {
        $source_id = $source['id'];

        $orders = wc_get_orders(
            array(
                'limit' => 1, // Query all orders
                'meta_key' => 'source_id', // The postmeta key field
                'meta_value' => $source_id, // The comparison argument
            )
        );

        if (empty($orders)) {
            Cynder_PayMongo_Logger::log(
                'Failed to find order with source_id = ' . $source_id
            );
            
            return false;
        }
            
        return $orders[0];
    }
}

Cynder_PayMongo_Webhook_Handler::getInstance();
