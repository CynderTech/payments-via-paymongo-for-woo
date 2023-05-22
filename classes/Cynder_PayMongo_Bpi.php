<?php
/**
 * PHP version 7
 * 
 * PayMongo - Bpi Payment Method
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

namespace Cynder\PayMongo;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayMongo - Bpi Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Bpi extends CynderPayMongoPaymentIntentGateway
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
        $this->id = PAYMONGO_BPI;
        $this->method_title = 'BPI Payments via PayMongo';
        $this->method_description = 'Simple and easy payments '
            . 'with BPI Direct Online Banking';
        $this->hasDetailsPayload = true;

        parent::__construct();
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
                'label'       => 'Enable BPI Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => 'Title',
                'description' => 'This controls the title that ' .
                                 'the user sees during checkout.',
                'default'     => 'BPI DOB via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description that ' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments.',
            ),
        );
    }

    public function generatePaymentMethodDetailsPayload($order)
    {
        $bankCode = $this->testmode ? 'test_bank_one' : 'bpi';

        return array(
            'bank_code' => $bankCode,
        );
    }
    
    /**
     * Get Icon for checkout page
     * 
     * @return string
     */
    public function get_icon() // phpcs:ignore
    {
        $icons_str = '<img class="paymongo-method-logo paymongo-bpi-icon" src="https://assets-global.website-files.com/60411749e60be86afb89d2f0/62417131ea248c5ad3ca5c9b_BPI.png" alt="'
            . $this->title
            .'" />';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }
}
