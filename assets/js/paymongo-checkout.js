jQuery(document).ready(function ($) {
    function Checkout() {
        this.form = null;
        this.init();
    }

    Checkout.prototype.init = function () {
        $(document.body).on('cynder_paymongo_init_checkout_form', this.initForm.bind(this));
        $(document.body).on('cynder_paymongo_show_errors', this.showErrors.bind(this));
    }

    Checkout.prototype.initForm = function (e, form) {
        this.form = form;
    }

    Checkout.prototype.scrollToNotices = function () {
        var scrollElement = $(
            ".woocommerce-NoticeGroup, .woocommerce-NoticeGroup"
        );

        if (!scrollElement.length && this.form) {
            scrollElement = this.form;
        }
        $.scroll_to_notices(scrollElement);
    }

    Checkout.prototype.showErrors = function (e, errorString) {
        console.log('Motha', errorString);

        // Remove notices from all sources
        $(
            ".woocommerce-error, .woocommerce-message, .paymongo-error"
        ).remove();
        $(".blockUI").remove();

        this.form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-PayMongoErrors">' + errorString + '</div>');
        this.scrollToNotices();
    }

    new Checkout();
});