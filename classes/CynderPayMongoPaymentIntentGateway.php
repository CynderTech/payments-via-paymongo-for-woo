<?php
/**
 * PHP version 7
 * 
 * PayMongo - Payment Intent Base Class
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

namespace Cynder\PayMongo;

use GuzzleHttp\Exception\ClientException;
use Paymongo\Phaymongo\PaymongoUtils;
use Paymongo\Phaymongo\Phaymongo;
use PostHog\PostHog;
use WC_Payment_Gateway;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayMongo - Payment Intent Base Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class CynderPayMongoPaymentIntentGateway extends WC_Payment_Gateway
{
    /**
     * Singleton instance
     * 
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $_instance;

    private $client;

    public $hasDetailsPayload = false;

    protected $testmode;
    protected $secret_key;
    protected $public_key;
    protected $sendInvoice;
    protected $debugMode;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if (null === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Starting point of the payment gateway
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        $testMode = get_option('woocommerce_cynder_paymongo_test_mode');
        $this->testmode = (!empty($testMode) && $testMode === 'yes') ? true : false;

        $skKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_secret_key' : 'woocommerce_cynder_paymongo_secret_key';
        $this->secret_key = get_option($skKey);

        $pkKey = $this->testmode ? 'woocommerce_cynder_paymongo_test_public_key' : 'woocommerce_cynder_paymongo_public_key';
        $this->public_key = get_option($pkKey);

        $sendInvoice = get_option('woocommerce_cynder_paymongo_send_invoice_after_payment');
        $this->sendInvoice = (!empty($sendInvoice) && $sendInvoice === 'yes') ? true : false;

        $debugMode = get_option('woocommerce_cynder_paymongo_debug_mode');
        $this->debugMode = (!empty($debugMode) && $debugMode === 'yes') ? true : false;

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        if ('yes' === $this->enabled) {
            add_filter(
                'woocommerce_thankyou_order_received_text',
                array($this, 'orderReceivedText'),
                10,
                2
            );
        }

        $this->client = new Phaymongo($this->public_key, $this->secret_key);
    }

    /**
     * Override for certain payment methods
     */
    public function initFormFields() {

    }

    /**
     * Override for certain payment methods
     */
    public function getPaymentMethodId($orderId) {
        $order = wc_get_order($orderId);

        $cbArgs = array(
            SERVER_PAYMENT_METHOD_TYPES[$this->id],
        );

        if ($this->hasDetailsPayload) {
            array_push($cbArgs, $this->generatePaymentMethodDetailsPayload($order));
        } else {
            array_push($cbArgs, null);
        }

        array_push($cbArgs, PaymongoUtils::generateBillingObject($order, 'woocommerce'));

        try {
            $paymentMethod = call_user_func_array(array($this->client->paymentMethod(), 'create'), $cbArgs);

            if ($this->debugMode) {
                wc_get_logger()->log('info', '[process_payment] Payment method response ' . wc_print_r($paymentMethod, true));
            }
    
            if (isset($paymentMethod['errors'])) {
                for ($i = 0; $i < count($paymentMethod['errors']); $i++) {
                    wc_add_notice($paymentMethod['errors'][$i]['detail'], 'error');
                }
    
                return;
            }
    
            $paymentMethodId = $paymentMethod['id'];
    
            return $paymentMethodId;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            wc_get_logger()->log('error', '[Processing Payment] Order ID: ' . $orderId . ' - Response error ' . wc_print_r(json_decode($response->getBody()->__toString(), true), true));
            return wc_add_notice('Payment processing error. Please contact side administrator for further details.', 'error');
        }
    }

    /** Override on certain payment methods */
    public function generatePaymentMethodDetailsPayload($order) {
        return array();
    }

    /**
     * Process PayMongo Payment
     *
     * @param string $orderId WooCommerce Order ID
     *
     * @return void
     *
     * @link  https://developers.paymongo.com/reference#the-payment-intent-object
     * @since 1.0.0
     */
    public function process_payment($orderId) // phpcs:ignore
    {
        global $woocommerce;
    
        $paymentMethodId = $this->getPaymentMethodId($orderId);

        if ($this->debugMode) {
            wc_get_logger()->log('info', '[Process Payment] Created payment method ID ' . $paymentMethodId);
        }

        if (!isset($paymentMethodId)) {
            $errorMessage = '[Processing Payment] No payment method ID found.';
            $userMessage = 'Your payment did not proceed due to an error. Rest assured that no payment was made. You may refresh this page and try again.';
            wc_get_logger()->log('error', $errorMessage);
            return wc_add_notice($userMessage, 'error');
        }

        $order = wc_get_order($orderId);
        $paymentIntentId = $order->get_meta('paymongo_payment_intent_id');

        if ($this->debugMode) {
            wc_get_logger()->log('info', 'Customer ID ' . $order->get_customer_id());
        }

        $amount = floatval($order->get_total());

        PostHog::capture(array(
            'distinctId' => base64_encode(get_bloginfo('wpurl')),
            'event' => 'process payment',
            'properties' => array(
                'amount' => $amount,
                'payment_method' => $order->get_payment_method(),
                'sandbox' => $this->testmode ? 'true' : 'false',
            ),
        ));

        $returnUrl = get_home_url() . '/?wc-api=cynder_paymongo_catch_redirect&order=' . $orderId . '&intent=' . $paymentIntentId . '&agent=cynder_woocommerce&version=' . CYNDER_PAYMONGO_VERSION;

        try {
            $paymentIntent = $this->client->paymentIntent()->attachPaymentMethod($paymentIntentId, $paymentMethodId, $returnUrl);

            if ($this->debugMode) {
                wc_get_logger()->log('info', '[process_payment] Response ' . wc_print_r($paymentIntent, true));
            }

            if (isset($paymentIntent['errors'])) {
                for ($i = 0; $i < count($paymentIntent['errors']); $i++) {
                    wc_add_notice($paymentIntent['errors'][$i]['detail'], 'error');
                }

                return;
            }

            $responseAttr = $paymentIntent['attributes'];
            $status = $responseAttr['status'];

            /** For regular payments, process as is */
            if ($status == 'succeeded') {
                // we received the payment
                $payments = $responseAttr['payments'];
                $intentAmount = $responseAttr['amount'];
                $order->payment_complete($payments[0]['id']);
                wc_reduce_stock_levels($orderId);

                // Sending invoice after successful payment if setting is enabled
                if ($this->sendInvoice) {
                    $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($orderId);
                }

                // Empty cart
                $woocommerce->cart->empty_cart();

                PostHog::capture(array(
                    'distinctId' => base64_encode(get_bloginfo('wpurl')),
                    'event' => 'successful payment',
                    'properties' => array(
                        'payment_id' => $payments[0]['id'],
                        'amount' => floatval($intentAmount) / 100,
                        'payment_method' => $order->get_payment_method(),
                        'sandbox' => $this->testmode ? 'true' : 'false',
                    ),
                ));

                do_action('cynder_paymongo_successful_payment', $payments[0]);

                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else if ($status === 'awaiting_next_action') {
                /** For 3DS-enabled cards, redirect to authorization page */
                return array(
                    'result' => 'success',
                    'redirect' => $responseAttr['next_action']['redirect']['url']
                );
            }
        } catch (ClientException $e) {
            $response = $e->getResponse();
            wc_get_logger()->log('error', '[Processing Payment] Payment Intent ID: ' . $paymentIntentId . ' - Response error ' . wc_print_r(json_decode($response->getBody()->__toString(), true), true));
            return wc_add_notice('Connection error. Check logs.', 'error');
        }
    }

    /**
     * Get Icon for checkout page
     * 
     * @return string
     */
    public function get_icon() // phpcs:ignore
    {
        $icons_str = '<img class="paymongo-method-logo payment-method-' . $this->id . '" src="'
            . CYNDER_PAYMONGO_PLUGIN_URL
            . '/assets/images/' . $this->id .'.png" alt="'
            . $this->title
            .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Custom Credit Card order received text.
     *
     * @param string       $text  Default text.
     * @param Cynder_Order $order Order data.
     *
     * @return string
     */
    public function orderReceivedText( $text, $order )
    {
        if ($order && $this->id === $order->get_payment_method()) {
            return esc_html__(
                'Thank You! Order has been received.',
                'woocommerce'
            );
        }

        return $text;
    }
}
