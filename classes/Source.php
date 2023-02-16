<?php

namespace Cynder\PayMongo;

use Cynder\PayMongo\ErrorsTrait;
use Paymongo\Phaymongo\PaymongoException;
use Paymongo\Phaymongo\PaymongoUtils;

class Source {
    use ErrorsTrait;

    protected $type;
    protected $utils;
    protected $debug_mode;
    protected $test_mode;
    protected $client;
    protected $plugin_version;

    public function __construct($type, $utils, $debug_mode, $test_mode, $client, $plugin_version)
    {
        $this->type = $type;
        $this->utils = $utils;
        $this->debug_mode = $debug_mode;
        $this->test_mode = $test_mode;
        $this->client = $client;
        $this->plugin_version = $plugin_version;
    }

    public function processPayment($order) {
        $amount = floatval($order->get_total());
        $order_id = $order->get_id();

        $this->utils->trackProcessPayment($amount, $order->get_payment_method(), $this->test_mode);

        $billing = PaymongoUtils::generateBillingObject($order, 'woocommerce');

        $success_url = $this->utils->getSourceReturnUrl('success', $order_id, $this->plugin_version);
        $failed_url = $this->utils->getSourceReturnUrl('failed', $order_id, $this->plugin_version);

        try {
            $source = $this->client->source()->create($amount, $this->type, $success_url, $failed_url, $billing, array('agent' => 'cynder_woocommerce', 'version' => $this->plugin_version));

            $order->add_meta_data('source_id', $source['id']);
            $order->update_status('pending');

            return array(
                'result' => 'success',
                'redirect' => $source['attributes']['redirect']['checkout_url'],
            );
        } catch (PaymongoException $e) {
            $formatted_messages = $e->format_errors();

            foreach ($formatted_messages as $message) {
                $this->utils->log('error', $this->getLogError('PI003', ['POST /sources', $message]));
                $this->utils->addNotice('error', $message);
            }

            return null;
        }
    }
}