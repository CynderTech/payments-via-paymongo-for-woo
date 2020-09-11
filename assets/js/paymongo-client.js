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
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            data: JSON.stringify(payload),
            success: function (data) {
                callback(null, data);
            },
            error: callback
        });
    }

    PaymongoClient.prototype.createPaymentMethod = function (e, payload, callback) {
        console.log('Sending payload', payload);

        return callback(null, 'Here');

        // $.ajax({
        //     url: "https://api.paymongo.com/v1/payment_methods",
        //     data: JSON.stringify({ data: { attributes: payload } }),
        //     method: "POST",
        //     headers: {
        //         'Content-Type': 'application/json',
        //         'Accept': 'application/json'
        //     },
        //     headers: {
        //         accept: "application/json",
        //         "content-type": "application/json",
        //         Authorization: "Basic " + btoa(cynder_paymongo_client_params.public_key),
        //     },
        //     success: function (response) {
        //         paymongoForm.attachPaymentMethod(response, paymentIntent);
        //     },
        //     error: paymongoForm.onFail,
        // });
    }

    new PaymongoClient();
});