<?php

use Cynder\PayMongo\ErrorsTrait;

beforeEach(function () {
    $this->trait = $this->getObjectForTrait(ErrorsTrait::class);
});

it('should return correct user error', function () {
    expect($this->trait->getUserError('PI001'))->toBe('Your payment did not proceed due to an error. Please try again or contact the merchant and/or site administrator. (Error Code: PI001)');
});

it('should return correct log error', function () {
    expect($this->trait->getLogError('PI001', ['1']))->toBe('No payment method ID found while processing payment for order ID 1.');
});

it('should return a generic user error for unknown error code', function () {
    expect($this->trait->getUserError('UNKNOWN'))->toBe('An unknown error occured. Please contact your site administrator.');
});

it('should return a a generic log error for unknown error code', function () {
    expect($this->trait->getLogError('UNKNOWN'))->toBe('Unknown error occured. (Error Code: UNKNOWN)');
});