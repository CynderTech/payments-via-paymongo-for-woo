<?php

namespace Cynder\PayMongo;

use Exception;

trait ErrorsTrait {
    public $user_errors = [
        'generic_payment_error' => 'Your payment did not proceed due to an error. Please try again or contact the merchant and/or site administrator.',
        'generic_user_error' => 'An unknown error occured. Please contact your site administrator.',
        'generic_log_error' => 'Unknown error occured. (Error Code: %s)',
    ];

    public $log_errors = [
        'PI001' => 'No payment method ID found while processing payment for order ID %s.',
        'PI002' => 'No payment intent ID found while processing payment for order ID %s.',
        'PI003' => 'Response payload from Paymongo API for endpoint %s: %s'
    ];

    private $error_hashmap = [
        'PI001' => 'generic_payment_error',
        'PI002' => 'generic_payment_error',
    ];

    public function getUserError($code, $data = []) {
        try {
            $user_error_key = $this->error_hashmap[$code] ?? null;

            if (!isset($user_error_key)) throw new Exception('Undefined user code');

            $string_data = array_merge($data, ['Error Code: ' . $code]);
            $final_error = sprintf($this->user_errors[$user_error_key] . ' (%s)', ...$string_data);
        } catch (Exception $e) {
            $final_error = $this->user_errors['generic_user_error'];
        } finally {
            return $final_error;
        }
    }

    public function getLogError($code, $data = []) {
        try {
            $log_error_key = $this->log_errors[$code] ?? null;

            if (!isset($log_error_key)) throw new Exception('Undefined log code');

            $final_error = sprintf($log_error_key, ...$data);
        } catch (Exception $e) {
            $final_error = sprintf($this->user_errors['generic_log_error'], $code);
        } finally {
            return $final_error;
        }
    }
}