<?php

function cynder_paymongo_create_intent() {
    $requestBody = file_get_contents('php://input');
    $decoded = json_decode($requestBody, true);
    $amount = floatval($decoded['amount']);

    if (!is_float($amount)) {
        return wp_send_json(
            array('error' => 'Invalid amount'),
            400
        );
    }

    $pluginSettings = get_option('woocommerce_paymongo_settings');

    if ($pluginSettings['enabled'] !== 'yes') {
        return wp_send_json(
            array('error' => 'Payment gateway must be enabled'),
            400
        );
    }

    $secretKeyProp = $pluginSettings['testmode'] === 'yes' ? 'test_secret_key' : 'secret_key';
    $secretKey = $pluginSettings[$secretKeyProp];

    $payload = json_encode(
        array(
            'data' => array(
                'attributes' =>array(
                    'amount' => floatval($amount * 100),
                    'payment_method_allowed' => array('card'),
                    'currency' => 'PHP', // hard-coded for now
                ),
            ),
        )
    );

    $args = array(
        'body' => $payload,
        'method' => "POST",
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($secretKey),
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
    );

    $response = wp_remote_post(
        CYNDER_PAYMONGO_BASE_URL . '/payment_intents',
        $args
    );

    wc_get_logger()->log('info', json_encode($response));

    if (!is_wp_error($response)) {
        $body = json_decode($response['body'], true);

        if ($body
            && array_key_exists('data', $body)
            && array_key_exists('attributes', $body['data'])
            && array_key_exists('status', $body['data']['attributes'])
            && $body['data']['attributes']['status'] == 'awaiting_payment_method'
        ) {
            $clientKey = $body['data']['attributes']['client_key'];
            return wp_send_json(
                array(
                    'result' => 'success',
                    'payment_client_key' => $clientKey,
                    'payment_intent_id' => $body['data']['id'],
                )
            );
        } else {
            wp_send_json(
                array(
                    'result' => 'error',
                    'errors' => $body['errors'],
                )
            );
            return;
        }
    } else {
        wp_send_json(
            array(
                'result' => 'failure',
                'messages' => $response->get_error_messages(),
            )
        );
        return;
    }
}

add_action(
    'woocommerce_api_cynder_paymongo_create_intent',
    'cynder_paymongo_create_intent'
);