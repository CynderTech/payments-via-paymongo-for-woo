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
    public static function printErrors($errors)
    {
        if (!isset($errors) || count($errors) === $errors) {
            return '<div class="woocommerce-error">'
                . 'Something went wrong. Unable to retrieve error record'
                . '</div>';
        }

        $messages = '<ul class="woocommerce-error">';

        foreach ($errors as $error) {
            $messages .= '<li>' . $error . '</li>';
        }

        $messages .= '</ul>';

        return $messages;
    }

    public static function parseError($error) {
        if (!$error['detail']) return $error;

        $detail = $error['detail'];
        $details = explode(' ', $detail);

        $field = ucwords(str_replace('_', ' ', end(explode('.', $details[0]))));

        $details[0] = $field;

        return implode(' ', $details);
    }
}