<?php

use Cynder\PayMongo\Source;
use Paymongo\Phaymongo\PaymongoException;

it('should return checkout url', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_total' => '100',
        'get_id' => '1',
        'get_payment_method' => 'paymongo_gcash',
        'get_billing_first_name' => 'John',
        'get_billing_last_name' => 'Doe',
        'get_billing_email' => 'john.doe@example.com',
        'get_billing_phone' => '123456',
        'get_billing_address_1' => '1',
        'get_billing_address_2' => '2',
        'get_billing_city' => '3',
        'get_billing_state' => '4',
        'get_billing_country' => '5',
        'get_billing_postcode' => '6',
    ]);
    $mockOrder
        ->shouldReceive('add_meta_data')
        ->withArgs(['source_id', '2']);
    $mockOrder
        ->shouldReceive('update_status')
        ->withArgs(['pending']);

    $mockUtils = \Mockery::mock();
    $mockUtils
        ->shouldReceive('trackProcessPayment')
        ->withArgs([100, 'paymongo_gcash', false]);

    $mockUtils
        ->shouldReceive('getSourceReturnUrl')
        ->withArgs(['success', '1', '1.0'])
        ->andReturn('https://some-domain.com/success');
    
    $mockUtils
        ->shouldReceive('getSourceReturnUrl')
        ->withArgs(['failed', '1', '1.0'])
        ->andReturn('https://some-domain.com/failed');
    
    $mockSource = \Mockery::mock();
    $mockSource
        ->shouldReceive('create')
        ->withArgs([
            100,
            'gcash',
            'https://some-domain.com/success',
            'https://some-domain.com/failed',
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '123456',
                'address' => [
                    'line1' => '1',
                    'line2' => '2',
                    'city' => '3',
                    'state' => '4',
                    'country' => '5',
                    'postal_code' => '6',
                ]
            ],
            [
                'agent' => 'cynder_woocommerce',
                'version' => '1.0',
            ]
        ])
        ->andReturn([
            'id' => '2',
            'attributes' => [
                'redirect' => [
                    'checkout_url' => 'https://some-payment-gateway.com/checkout',
                ],
            ],
        ]);

    $mockClient = \Mockery::mock();
    $mockClient->shouldReceive('source')->andReturn($mockSource);

    $source = new Source('gcash', $mockUtils, false, false, $mockClient, '1.0');
    $returnObj = $source->processPayment($mockOrder);

    expect($returnObj)->toBe(['result' => 'success', 'redirect' => 'https://some-payment-gateway.com/checkout']);
});

it('should show and log errors after creating source', function () {
    $mockOrder = \Mockery::mock();
    $mockOrder->shouldReceive([
        'get_total' => '100',
        'get_id' => '1',
        'get_payment_method' => 'paymongo_gcash',
        'get_billing_first_name' => 'John',
        'get_billing_last_name' => 'Doe',
        'get_billing_email' => 'john.doe@example.com',
        'get_billing_phone' => '123456',
        'get_billing_address_1' => '1',
        'get_billing_address_2' => '2',
        'get_billing_city' => '3',
        'get_billing_state' => '4',
        'get_billing_country' => '5',
        'get_billing_postcode' => '6',
    ]);
    $mockOrder
        ->shouldReceive('add_meta_data')
        ->withArgs(['source_id', '2']);
    $mockOrder
        ->shouldReceive('update_status')
        ->withArgs(['pending']);

    $mockUtils = \Mockery::mock();
    $mockUtils
        ->shouldReceive('trackProcessPayment')
        ->withArgs([100, 'paymongo_gcash', false]);

    $mockUtils
        ->shouldReceive('getSourceReturnUrl')
        ->withArgs(['success', '1', '1.0'])
        ->andReturn('https://some-domain.com/success');
    
    $mockUtils
        ->shouldReceive('getSourceReturnUrl')
        ->withArgs(['failed', '1', '1.0'])
        ->andReturn('https://some-domain.com/failed');

    $mockUtils
        ->shouldReceive('log')
        ->withArgs(['error', 'Response payload from Paymongo API for endpoint POST /sources: some error']);

    $mockUtils
        ->shouldReceive('addNotice')
        ->withArgs(['error', 'some error']);
    
    $mockSource = \Mockery::mock();
    $mockSource
        ->shouldReceive('create')
        ->withArgs([
            100,
            'gcash',
            'https://some-domain.com/success',
            'https://some-domain.com/failed',
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '123456',
                'address' => [
                    'line1' => '1',
                    'line2' => '2',
                    'city' => '3',
                    'state' => '4',
                    'country' => '5',
                    'postal_code' => '6',
                ]
            ],
            [
                'agent' => 'cynder_woocommerce',
                'version' => '1.0',
            ]
        ])
        ->andThrow(new PaymongoException([['detail' => 'some error']]));

    $mockClient = \Mockery::mock();
    $mockClient->shouldReceive('source')->andReturn($mockSource);

    $source = new Source('gcash', $mockUtils, false, false, $mockClient, '1.0');
    $returnObj = $source->processPayment($mockOrder);

    expect($returnObj)->toBeNull();
});