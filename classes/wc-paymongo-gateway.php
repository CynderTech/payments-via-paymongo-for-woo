<?php
/**
 * PHP version 7
 * 
 * PayMongo - Credit Card Payment Method
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <developers@paymongo.com>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayMongo - Credit Card Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <developers@paymongo.com>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class WC_PayMongo_Gateway extends WC_Payment_Gateway
{
    /**
     * Singleton instance
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
        $this->id = 'paymongo_payment_gateway';
        $this->icon = 'https://b.paymongocdn.com/images/logo-with-text.png';
        $this->has_fields = false;
        $this->method_title = 'PayMongo';
        $this->method_description = 'Simple and easy payments.';

        $this->supports = array(
            'products'
        );

        $this->initFormFields();

        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->secret_key = $this->testmode ?
            $this->get_option('test_secret_key')
            : $this->get_option('secret_key');
        $this->public_key = $this->testmode ?
            $this->get_option('test_public_key')
            : $this->get_option('public_key');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
    }

    /**
     * Payment Gateway Settings Page Fields
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function initFormFields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable PayMongo Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => 'Title',
                'description' => 'This controls the title which' .
                                 'the user sees during checkout.',
                'default'     => 'Main Settings',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments.',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in' .
                                 ' test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_public_key' => array(
                'title'       => 'Test Public Key',
                'type'        => 'text'
            ),
            'test_secret_key' => array(
                'title'       => 'Test Secret Key',
                'type'        => 'password',
            ),
            'public_key' => array(
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            'secret_key' => array(
                'title'       => 'Live Secret Key',
                'type'        => 'password'
            ),
            'webhook' => array(
                'title' => 'IMPORTANT! Setup Webhook Resource',
                'type' => 'title',
                'description' => 'Create a Webhook resource using curl command ' .
                            'or any API tools like Postman. ' . 
                            'Copy the <b>secret_key</b> '.
                            'and put it in the field below. <p>Use this URL: <b><i>'
                            . add_query_arg(
                                'wc-api',
                                'wc_paymongo',
                                trailingslashit(get_home_url())
                            ) . '</b></i></p>'
            ),
            'webhook_secret' => array(
                'title'       => 'Webhook Secret Key',
                'type'        => 'password',
                'description' => 'This is the secret_key returned when you ' .
                                 'created the webhook using live keys',
                'default'     => '',
            ),
            'test_webhook_secret' => array(
                'title'       => 'Test Webhook Secret Key',
                'type'        => 'password',
                'description' => 'This is the secret_key returned when you ' .
                                 'created the webhook using test keys',
                'default'     => '',
            ),
        );
    }
}
