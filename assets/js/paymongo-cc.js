jQuery(document).ready(function ($) {
    function CCForm() {
        this.init();
    }

    CCForm.prototype.init = function () {
        $(document.body).on('payment_method_selected', this.paymentMethodSelected.bind(this));
    }

    CCForm.prototype.paymentMethodSelected = function () {
        var paymentMethod = $('input[name=payment_method]:checked').val(); 

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== 'paymongo') return;

        this.addLoader();

        this.createPaymentIntent();
    }

    CCForm.prototype.addLoader = function () {
        $(".wc_payment_method .payment_box.payment_method_paymongo").append(
            '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
        );
    }

    CCForm.prototype.createPaymentIntent = function () {
        var amount = $('tr.order-total > td').text().slice(1);

        var args = [
            Number(amount),
            this.onPaymentIntentCreationResponse.bind(this),
        ];

        $(document.body).trigger('cynder_paymongo_create_payment_intent', args);
    }

    CCForm.prototype.onPaymentIntentCreationResponse = function (err, response) {
        /** Needs better error handling */
        if (err) return console.log(err);

        console.log(response);
    }

    new CCForm();
});