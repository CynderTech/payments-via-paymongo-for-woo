<?php
/**
 * PHP version 7
 * 
 * PayMongo - GCash Payment Method
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
 * PayMongo - GCash Payment Method Class
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Gcash_Gateway extends CynderPayMongoPaymentIntentGateway
{
    /**
     * Starting point of the payment gateway
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->id = PAYMONGO_GCASH;
        $this->method_title = 'GCash Gateway via PayMongo';
        $this->method_description = 'Simple and easy payments with GCash.';

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
                'label'       => 'Enable GCash Gateway via PayMongo',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which ' .
                                 'the user sees during checkout.',
                'default'     => 'GCash via PayMongo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which ' .
                                 'the user sees during checkout.',
                'default'     => 'Simple and easy payments via GCash.',
            ),
        );
    }
}
