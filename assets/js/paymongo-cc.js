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
        
        if(cynder_paymongo_cc_params.isCheckout) {
            $('form.woocommerce-checkout').on(
                'checkout_place_order_paymongo',
                this.createPaymentMethod.bind(this)
            );
        } else if (cynder_paymongo_cc_params.isOrderPay) {
            $('#order_review').on('submit', this.createPaymentMethod.bind(this));
        } else {
            alert('Paymongo cannot find the checkout form. Initialization failed. Try to refresh the page.');
        }
    }

    CCForm.prototype.paymentMethodSelected = function () {
        var paymentMethod = $('input[name=payment_method]:checked').val(); 

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== 'paymongo') return;

        // this.createPaymentIntent();
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

        var data = this.parseWcResponse(response);

        this.set('payment_client_key', data.payment_client_key);
        this.set('payment_intent_id', data.payment_intent_id);
    }

    CCForm.prototype.createPaymentMethod = function (e) {
        e.preventDefault(e);

        console.log('Creating payment method');

        var payload = {};

        var args = [
            payload,
            this.onPaymentMethodCreationResponse.bind(this),
        ];

        this.addLoader();

        $(document.body).trigger('cynder_paymongo_create_payment_method', args);

        return false;
    }

    CCForm.prototype.onPaymentMethodCreationResponse = function (err, response) {
        this.removeLoader();

        /** Needs better error handling */
        if (err) return console.log(err);

        console.log(response);
    }

    CCForm.prototype.parseWcResponse = function (response) {
        const result = response.result;

        if (result && result === 'error') {
            console.log('On error', response);
            // const errors = paymongoForm.parsePayMongoErrors(response.errors);
            // paymongoForm.showErrors(errors);
            return null;
        }

        if (result && result === "failure" && response.messages) {
            console.log('On failure', response);
            // paymongoForm.showErrors(response.messages, true);
            return null;
        }

        if (result && result === 'success') {
            delete response.result;
            return response;
        }

        return null;
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