<?php

const PAYMONGO_CARD = 'paymongo';
const PAYMONGO_GCASH = 'paymongo_gcash';
const PAYMONGO_GRABPAY = 'paymongo_grab_pay';
const PAYMONGO_PAYMAYA = 'paymongo_paymaya';
const PAYMONGO_ATOME = 'paymongo_atome';
const PAYMONGO_BPI = 'paymongo_bpi';
const PAYMONGO_BILLEASE = 'paymongo_billease';

const PAYMONGO_PAYMENT_METHODS = array(
    PAYMONGO_CARD,
    PAYMONGO_GCASH,
    PAYMONGO_GRABPAY,
    PAYMONGO_PAYMAYA,
    PAYMONGO_ATOME,
    PAYMONGO_BPI,
    PAYMONGO_BILLEASE,
);

const PAYMONGO_PAYMENT_METHOD_LABELS = array(
    PAYMONGO_CARD => 'Credit/Debit Card via PayMongo',
    PAYMONGO_GCASH => 'GCash via PayMongo',
    PAYMONGO_GRABPAY => 'GrabPay via PayMongo',
    PAYMONGO_PAYMAYA => 'Maya via PayMongo',
    PAYMONGO_ATOME => 'Atome via PayMongo',
    PAYMONGO_BPI => 'BPI Direct Onling Banking via PayMongo',
    PAYMONGO_BILLEASE => 'BillEase via PayMongo',
);

const PAYMONGO_PAYMENT_INTENT_META_KEY = 'paymongo_payment_intent_id';
const PAYMONGO_CLIENT_KEY_META_KEY = 'paymongo_client_key';