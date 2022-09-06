<?php
/**
 * PHP version 7
 * 
 * PayMongo - GrabPay Payment Method
 * 
 * @category Plugin
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */

namespace Cynder\PayMongo;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayMongo - GrabPay Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_GrabPay_Gateway extends Cynder_PayMongo_Ewallet_Gateway
{
    /**
     * Starting point of the payment gateway
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->ewallet_type = 'grab_pay';
        $this->icon_name = 'grabpay';
        $this->id = 'paymongo_' . $this->ewallet_type;
        $this->has_fields = true;
        $this->method_title = 'GrabPay Gateway via PayMongo';
        $this->method_description = 'Simple and easy payments with GrabPay.';

        parent::__construct();
    }

    /**
     * Payment Gateway Settings Page Fields
     * 
     * @since 1.0.0
     * 
     * @return void
     */
    public function initFormFields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable GrabPay Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which '
                    . 'the user sees during checkout.',
                'default'     => 'GrabPay via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which'
                    . ' the user sees during checkout.',
                'default'     => 'Simple and easy payments via GrabPay.',
            ),
        );
    }
}
