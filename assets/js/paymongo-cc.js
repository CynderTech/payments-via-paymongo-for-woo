jQuery(document).ready(function ($) {
    function CCForm() {
        this.init();
    }

    CCForm.prototype.init = function () {
        $(document.body).on('payment_method_selected', this.payment_method_selected.bind(this));
    }

    CCForm.prototype.payment_method_selected = function () {
        var paymentMethod = $('input[name=payment_method]:checked').val(); 

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== 'paymongo') return;

        console.log('Initialize CC form');
    }

    new CCForm();
});