<?php

namespace Cynder\PayMongo;

function is_billing_value_set($value) {
    return isset($value) && $value !== '';
}

function generate_billing_address($order) {
    $billing_address = array();

    $billing_address_1 = $order->get_billing_address_1();
    $has_billing_address_1 = is_billing_value_set($billing_address_1);

    if ($has_billing_address_1) {
        $billing_address['line1'] = $billing_address_1;
    }

    $billing_address_2 = $order->get_billing_address_2();
    $has_billing_address_2 = is_billing_value_set($billing_address_2);

    if ($has_billing_address_2) {
        $billing_address['line2'] = $billing_address_2;
    }

    $billing_city = $order->get_billing_city();
    $has_billing_city = is_billing_value_set($billing_city);

    if ($has_billing_city) {
        $billing_address['city'] = $billing_city;
    }

    $billing_state = $order->get_billing_state();
    $has_billing_state = is_billing_value_set($billing_state);

    if ($has_billing_state) {
        $billing_address['state'] = $billing_state;
    }

    $billing_country = $order->get_billing_country();
    $has_billing_country = is_billing_value_set($billing_country);

    if ($has_billing_country) {
        $billing_address['country'] = $billing_country;
    }

    $billing_postcode = $order->get_billing_postcode();
    $has_billing_postcode = is_billing_value_set($billing_postcode);

    if ($has_billing_postcode) {
        $billing_address['postal_code'] = $billing_postcode;
    }

    return $billing_address;
}