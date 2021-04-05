jQuery(document).ready(function ($) {
    function CCForm() {
        this.form = null;
        this.method_field_name = 'cynder_paymongo_method_id';
        this.method_field_selector = 'input#' + this.method_field_name;
        this.init();
    }

    CCForm.prototype.set = function(key, value) {
        this[key] = value;
    }

    CCForm.prototype.get = function(key) {
        return this[key];
    }

    CCForm.prototype.init = function () {
        $(document.body).on('payment_method_selected', this.initializeCcFields.bind(this));
        $(document.body).on('updated_checkout', this.initializeCcFields.bind(this));

        let form;
        
        if(cynder_paymongo_cc_params.isCheckout) {
            form = $('form.woocommerce-checkout');
            form.on(
                'checkout_place_order_paymongo',
                this.onSubmit.bind(this)
            );
            this.form = form;
            $(document.body).trigger('cynder_paymongo_init_checkout_form', [form]);
        } else if (cynder_paymongo_cc_params.isOrderPay) {
            form = $('#order_review');
            form.on('submit', this.onSubmit.bind(this));
            this.form = form;
            $(document.body).trigger('cynder_paymongo_init_checkout_form', [form]);
        } else {
            alert('Paymongo cannot find the checkout form. Initialization failed. Try to refresh the page.');
        }
    }

    CCForm.prototype.initializeCcFields = function () {
        var paymentMethod = $('input[name=payment_method]:checked').val();

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== 'paymongo') return;

        this.addLoader();
        
        setTimeout(function () {
            this.initCleave();
        }.bind(this), 500);
    }

    CCForm.prototype.initCleave = function () {
        if ($("#paymongo_ccNo").length) {
            var ccNo = new Cleave("#paymongo_ccNo", {
                creditCard: true,
            });
        }

        if ($("#paymongo_expdate").length) {
            var expDate = new Cleave("#paymongo_expdate", {
                date: true,
                datePattern: ["m", "y"],
            });
        }

        if ($("#paymongo_cvv").length) {
            var cvv = new Cleave("#paymongo_cvv", {
                blocks: [4],
            });
        }

        this.removeLoader();
    }

    CCForm.prototype.onSubmit = function (e) {
        const form = this.form;

        var paymentMethod = $('input[name=payment_method]:checked').val();

        if (paymentMethod !== 'paymongo') {
            return form.submit();
        }

        const hasMethod = form.find(this.method_field_selector).length;

        if (hasMethod) {
            this.removeLoader();
            return true;
        }

        if (cynder_paymongo_cc_params.isOrderPay) {
            e.preventDefault();
        }

        return this.createPaymentMethod();
    }

    CCForm.prototype.createPaymentMethod = function () {
        const ccNo = $("#paymongo_ccNo").val();
        const [expMonth, expYear] = $("#paymongo_expdate").val().split("/");
        const cvc = $("#paymongo_cvv").val();

        const line1 =
            cynder_paymongo_cc_params.billing_address_1 ||
            $("#billing_address_1").val();
        const line2 =
            cynder_paymongo_cc_params.billing_address_2 ||
            $("#billing_address_2").val();
        const city =
            cynder_paymongo_cc_params.billing_city || $("#billing_city").val();
        const state =
            cynder_paymongo_cc_params.billing_state || $("#billing_state").val();
        const country =
            cynder_paymongo_cc_params.billing_country || $("#billing_country").val();
        const postal_code =
            cynder_paymongo_cc_params.billing_postcode ||
            $("#billing_postcode").val();
        const name = this.getName();
        const email =
            cynder_paymongo_cc_params.billing_email || $("#billing_email").val();
        const phone =
            cynder_paymongo_cc_params.billing_phone || $("#billing_phone").val();

        const payload = {
            type: "card",
            details: {
                card_number: ccNo.replace(/ /g, ""),
                exp_month: parseInt(expMonth),
                exp_year: parseInt(expYear),
                cvc: cvc,
            },
            billing: {
                address: {
                    line1: line1,
                    line2: line2,
                    city: city,
                    state: state,
                    country: country,
                    postal_code: postal_code,
                },
                name: name,
                email: email,
                phone: phone,
            },
        };

        var args = [
            payload,
            this.onPaymentMethodCreationResponse.bind(this),
        ];

        this.addLoader();

        $(document.body).trigger('cynder_paymongo_create_payment_method', args);

        return false;
    }

    CCForm.prototype.getName = function () {
        const firstName =
            cynder_paymongo_cc_params.billing_first_name ||
            $("#billing_first_name").val();
        const lastName =
            cynder_paymongo_cc_params.billing_last_name ||
            $("#billing_last_name").val();

        let name = firstName + " " + lastName;
        let companyName =
            cynder_paymongo_cc_params.billing_company || $("#billing_company").val();

        if (companyName && companyName.length) {
            name = name + " - " + companyName;
        }

        return name;
    }

    CCForm.prototype.onPaymentMethodCreationResponse = function (err, data) {
        this.removeLoader();

        if (err) {
            return this.showClientErrors(err.errors);
        }

        var form = this.form;
 
        let methodField = form.find(this.method_field_selector);        
        const hasMethod = methodField.length;

        if (!hasMethod) {
            form.append('<input type="hidden" id="' + this.method_field_name + '" name="' + this.method_field_name + '"/>');
            methodField = form.find(this.method_field_selector);
        }

        methodField.val(data.id);

        form.submit();
    }

    CCForm.prototype.showClientErrors = function (errors) {
        const args = [
            errors,
            this.onClientErrorParsed.bind(this),
        ];

        $(document.body).trigger('cynder_paymongo_parse_client_errors', args);
    }

    CCForm.prototype.onClientErrorParsed = function (errorMessages) {
        const errorHtml = errorMessages.reduce((html, errorMessage, index) => {
            let newHtml = html + '<li>' + errorMessage + '</li>';

            if (index === (errorMessages.length - 1)) {
                newHtml = newHtml + '</ul>';
            }

            return newHtml;
        }, '<ul class="woocommerce-error">');

        return $(document.body).trigger('cynder_paymongo_show_errors', [errorHtml]);
    }

    CCForm.prototype.addLoader = function () {
        $(".wc_payment_method > .payment_box").append(
            '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
        );
    }

    CCForm.prototype.removeLoader = function () {
        $(".paymongo-loading").remove();
    }

    new CCForm();
});