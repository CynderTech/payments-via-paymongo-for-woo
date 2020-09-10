jQuery(document).ready(function ($) {
    function PaymongoClient() {
        this.init();
    }

    PaymongoClient.prototype.init = function () {
        $(document.body).on('cynder_paymongo_create_payment_intent', this.createPaymentIntent.bind(this));
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
            success: callback,
            error: callback
        });
    }

    new PaymongoClient();
});