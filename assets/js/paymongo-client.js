jQuery(document).ready(function ($) {
    function PaymongoClient() {
        this.init();
    }

    PaymongoClient.prototype.init = function () {
        $(document.body).on('cynder_paymongo_create_payment_intent', this.createPaymentIntent.bind(this));
        $(document.body).on('cynder_paymongo_create_payment_method', this.createPaymentMethod.bind(this));
    }

    PaymongoClient.prototype.createPaymentIntent = function (e, amount, callback) {
        var payload = {
            amount: amount,
        };

        $.post({
            url: cynder_paymongo_client_params.home_url + '/?wc-api=cynder_paymongo_create_intent',
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
            url: "https://api.paymongo.com/v1/payment_methods",
            data: this.buildPayload(payload),
            method: "POST",
            headers: this.getHeaders(),
            success: function (response) {
                return callback(null, response);
            },
            error: callback,
        });
    }

    PaymongoClient.prototype.getHeaders = function(hasKey) {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (!hasKey) {
            headers['Authorization'] = "Basic " + btoa(cynder_paymongo_client_params.public_key)
        }

        return headers;
    }

    PaymongoClient.prototype.buildPayload = function (payload) {
        return JSON.stringify({ data: { attributes: payload } });
    }

    new PaymongoClient();
});