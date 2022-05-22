<?php

const PAYMONGO_CARD = 'paymongo';
const PAYMONGO_GCASH = 'paymongo_gcash';
const PAYMONGO_GRABPAY = 'paymongo_grab_pay';
const PAYMONGO_PAYMAYA = 'paymongo_paymaya';
const PAYMONGO_ATOME = 'paymongo_atome';

const PAYMONGO_PAYMENT_METHODS = array(
    PAYMONGO_CARD,
    PAYMONGO_GCASH,
    PAYMONGO_GRABPAY,
    PAYMONGO_PAYMAYA,
    PAYMONGO_ATOME,
);

const PAYMENT_METHODS_WITH_INTENT = array(
    PAYMONGO_CARD,
    PAYMONGO_PAYMAYA,
    PAYMONGO_ATOME
);

const SERVER_PAYMENT_METHOD_TYPES = array(
    'paymongo_paymaya' => 'paymaya',
    'paymongo_atome' => 'atome',
);