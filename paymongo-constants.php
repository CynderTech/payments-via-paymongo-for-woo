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

const PAYMENT_METHODS_WITH_INTENT = array(
    PAYMONGO_CARD,
    PAYMONGO_PAYMAYA,
    PAYMONGO_ATOME,
    PAYMONGO_BPI,
    PAYMONGO_BILLEASE,
);

const SERVER_PAYMENT_METHOD_TYPES = array(
    PAYMONGO_PAYMAYA => 'paymaya',
    PAYMONGO_ATOME => 'atome',
    PAYMONGO_BPI => 'dob',
    PAYMONGO_BILLEASE => 'billease',
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
