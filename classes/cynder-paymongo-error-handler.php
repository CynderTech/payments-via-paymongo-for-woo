<?php
/**
 * PHP version 7
 * 
 * Helper for PayMongo errors
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
 * Parse PayMongo Errors
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <devops@cynder.io>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Error_Handler
{
    /**
     * Build Error messages from PayMongo errors
     * 
     * @param array $errors PayMongo Errors body
     * 
     * @return string
     */
    public static function parseErrors($errors)
    {
        if (!isset($errors) || count($errors) === $errors) {
            return '<div class="woocommerce-error">'
                . 'Something went wrong. Unable to retrieve error record'
                . '</div>';
        }

        $messages = '<ul class="woocommerce-error">';

        foreach ($errors as $error) {
            $messages .= '<li>' .
                $error['detail'] .
                ' (code: ' . $error['code'] . ')' .
                '</li>';
        }

        $messages .= '</ul>';

        return $messages;
    }
}