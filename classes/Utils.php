<?php

namespace Cynder\PayMongo;

use PostHog\PostHog;

class Utils {
    public function log($level, $message) {
        wc_get_logger()->log($level, $message);
    }

    public function addNotice($level, $message) {
        wc_add_notice($message, $level);
    }

    public function humanize($message) {
        return wc_print_r($message, true);
    }

    public function callAction($action, ...$args) {
        call_user_func('do_action', array_merge([$action], $args));
    }

    public function sendInvoice($order_id) {
        global $woocommerce;
        $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order_id);
    }

    public function reduceStockLevels($order_id) {
         wc_reduce_stock_levels($order_id);
    }

    public function emptyCart() {
        global $woocommerce;
        $woocommerce->cart->empty_carty();
    }

    public function completeOrder($order, $payment_id, $send_invoice) {
        global $woocommerce;
        $order_id = $order->get_id();

        $order->payment_complete($payment_id);
        wc_reduce_stock_levels($order_id);

        if ($send_invoice) {
            $woocommerce->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order_id);
        }
    }

    public function trackProcessPayment($amount, $payment_method, $test_mode) {
        PostHog::capture(array(
            'distinctId' => base64_encode(get_bloginfo('wpurl')),
            'event' => 'process payment',
            'properties' => array(
                'amount' => $amount,
                'payment_method' => $payment_method,
                'sandbox' => $test_mode ? 'true' : 'false',
            ),
        ));
    }

    public function trackPaymentResolution($status, $payment_id, $amount, $payment_method, $test_mode) {
        PostHog::capture(array(
            'distinctId' => base64_encode(get_bloginfo('wpurl')),
            'event' => $status . ' payment',
            'properties' => array(
                'payment_id' => $payment_id,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'sandbox' => $test_mode ? 'true' : 'false',
            ),
        ));
    }

    public function getSourceReturnUrl($status, $order_id, $plugin_version) {
        return get_home_url() . '/?wc-api=cynder_paymongo_catch_source_redirect&order=' . $order_id . '&status=' . $status . '&agent=cynder_woocommerce&version=' . $plugin_version;
    }
}