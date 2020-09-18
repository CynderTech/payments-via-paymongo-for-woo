jQuery(document).ready(function ($) {
    const BASE_API_URL = 'https://api.paymongo.com/v1';

    function PaymongoClient() {
        this.init();
    }

    PaymongoClient.prototype.init = function () {
        $(document.body).on('cynder_paymongo_create_payment_intent', this.createPaymentIntent.bind(this));
        $(document.body).on('cynder_paymongo_create_payment_method', this.createPaymentMethod.bind(this));
        $(document.body).on('cynder_paymongo_parse_client_errors', this.parseErrors.bind(this));
    }

    PaymongoClient.prototype.createPaymentIntent = function (e, amount, callback) {
        var payload = {
            amount: amount,
        };

        $.ajax({
            url: cynder_paymongo_client_params.home_url + '/?wc-api=cynder_paymongo_create_intent',
            method: 'POST',
            headers: this.getHeaders(),
            data: JSON.stringify(payload),
            success: function (data) {
                callback(null, data);
            },
            error: callback
        });
    }

    PaymongoClient.prototype.createPaymentMethod = function (e, payload, callback) {
        $.ajax({
            url: BASE_API_URL + '/payment_methods',
            method: 'POST',
            headers: this.getHeaders(true),
            data: this.buildPayload(payload),
            success: this.parseResponse.bind(this, callback),
            error: this.parseError.bind(this, callback),
        });
    }

    PaymongoClient.prototype.parseResponse = function (callback, response) {
        if (!response || !(response || {}).data || !((response || {}).data || {}).attributes) {
            console.log(response);
            /** Mimicking error structure from PayMongo API */
            return callback([
                {
                    detail: 'Invalid response from Paymongo API'
                }
            ]);
        }

        return callback(null, response.data);
    }

    PaymongoClient.prototype.parseError = function (callback, err) {
        return callback(err.responseJSON);
    }

    PaymongoClient.prototype.getHeaders = function(hasKey) {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (hasKey) {
            headers['Authorization'] = "Basic " + btoa(cynder_paymongo_client_params.public_key)
        }

        return headers;
    }

    PaymongoClient.prototype.buildPayload = function (payload) {
        return JSON.stringify({ data: { attributes: payload } });
    }

    PaymongoClient.prototype.parseErrors = function (e, errors, callback) {
        const errorMessages = errors.map(({ code, detail, sub_code }) => {
            const invalidSubcodes = [
                'processor_blocked',
                'lost_card',
                'stolen_card',
                'blocked'
            ];

            if (invalidSubcodes.includes(sub_code)) return 'Something went wrong. Please try again.';

            /** Hardcoded value for now */
            if (detail.includes('10000') && code === 'parameter_below_minimum') {
                return 'Amount cannot be less than P100.00';
            }
            
            if (!detail.includes('details.')) return detail;

            return detail
                .split(' ')
                .reduce((message, part) => {
                    if (!part.includes('details.')) {
                        if (!message) {
                            return part;
                        } else {
                            return message + ' ' + part;
                        }
                    }

                    const field = part
                        .split('.')
                        .pop()
                        .split('_')
                        .map((fieldPart, index) => {
                            if (index === 0) return fieldPart[0].toUpperCase() + fieldPart.slice(1);

                            return fieldPart;
                        })
                        .join(' ');
                    
                    return message + ' ' + field;
                }, '');
        });

        return callback(errorMessages);
    }

    new PaymongoClient();
});