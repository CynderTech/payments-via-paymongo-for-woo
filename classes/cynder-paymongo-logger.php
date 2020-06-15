<?php
/**
 * PHP version 7
 * 
 * Handles all incoming webhooks for PayMongo
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
 * Log all things!
 * 
 * @category Class
 * @package  PayMongo
 * @author   PayMongo <developers@paymongo.com>
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class Cynder_PayMongo_Logger
{

    public static $logger;
    const CYNDER_LOG_FILENAME = 'woocommerce-gateway-paymongo';

    /**
     * Utilize WC logger class
     *
     * @param string $message   Message to be logged
     * @param int    $startTime Start Time
     * @param int    $endTime   End Time
     * 
     * @return void
     */
    public static function log($message, $startTime = null, $endTime = null)
    {
        if (!class_exists('Cynder_Logger')) {
            return;
        }

        if (apply_filters('cynder_paymongo_logging', true, $message)) {
            if (empty(self::$logger)) {
                self::$logger = cynder_get_logger();
            }

            $settings = get_option('woocommerce_paymongo_settings');

            if (empty($settings)
                || isset($settings['logging'])
                && 'yes' !== $settings['logging']
            ) {
                return;
            }

            if (! is_null($startTime)) {

                $formatted_startTime  = date_i18n(
                    get_option('date_format') . ' g:ia',
                    $startTime
                );
                $endTime             = is_null($endTime) ?
                    current_time('timestamp')
                    : $endTime;
                $formatted_endTime   = date_i18n(
                    get_option('date_format') . ' g:ia',
                    $endTime
                );
                $elapsed_time         = round(abs($endTime - $startTime) / 60, 2);

                $log_entry  = "\n" .
                    '====PayMongo Version: ' . CYNDER_PAYMONGO_VERSION . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_startTime . '====' . "\n"
                    . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_endTime 
                    . ' (' . $elapsed_time . ')====' . "\n\n";

            } else {
                $log_entry  = "\n" 
                    . '====PayMongo Version: ' . CYNDER_PAYMONGO_VERSION . '====' . "\n";
                $log_entry .= '====Start Log====' 
                    . "\n" . $message . "\n"
                    . '====End Log====' . "\n\n";
            }

            self::$logger->debug(
                $log_entry,
                array('source' => self::CYNDER_LOG_FILENAME)
            );
        }
    }
}
