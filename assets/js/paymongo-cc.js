jQuery(document).ready(function ($) {
    function CCForm() {
        this.payment_client_key = null;
        this.payment_intent_id = null;
        this.init();
    }

    CCForm.prototype.set = function(key, value) {
        this[key] = value;
    }

    CCForm.prototype.get = function(key) {
        return this[key];
    }

    CCForm.prototype.init = function () {
        $(document.body).on('payment_method_selected', this.paymentMethodSelected.bind(this));
    }

    CCForm.prototype.paymentMethodSelected = function () {
        var paymentMethod = $('input[name=payment_method]:checked').val(); 

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== 'paymongo') return;

        this.createPaymentIntent();
    }

    CCForm.prototype.createPaymentIntent = function () {
        var amount = $('tr.order-total > td').text().slice(1);

        var args = [
            Number(amount),
            this.onPaymentIntentCreationResponse.bind(this),
        ];

        this.addLoader();

        $(document.body).trigger('cynder_paymongo_create_payment_intent', args);
    }

    CCForm.prototype.onPaymentIntentCreationResponse = function (err, response) {
        this.removeLoader();
        /** Needs better error handling */
        if (err) return console.log(err);

        this.parseResponse(response);
    }

    CCForm.prototype.parseResponse = function (response) {
        if (response.result && response.result === 'error') {
            console.log('On error', response);
            // const errors = paymongoForm.parsePayMongoErrors(response.errors);
            // paymongoForm.showErrors(errors);
            // return;
        }

        if (response.result && response.result === "failure" && response.messages) {
            console.log('On failure', response);
            // paymongoForm.showErrors(response.messages, true);
            // return;
        }

        if (response.result && response.result === 'success') {
            this.set('payment_client_key', response.payment_client_key);
            this.set('payment_intent_id', response.payment_intent_id);
        }
    }

    CCForm.prototype.addLoader = function () {
        $(".wc_payment_method .payment_box.payment_method_paymongo").append(
            '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
        );
    }

    CCForm.prototype.removeLoader = function () {
        $(".paymongo-loading").remove();
    };

    new CCForm();
});