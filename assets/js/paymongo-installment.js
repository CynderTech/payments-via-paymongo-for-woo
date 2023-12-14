jQuery(document).ready(function($) {
    function CCForm() {
        this.form = null;
        this.method_field_name = "cynder_paymongo_method_id";
        this.method_field_selector = "input#" + this.method_field_name;
        this.init();
    }

    CCForm.prototype.set = function(key, value) {
        this[key] = value;
    };

    CCForm.prototype.get = function(key) {
        return this[key];
    };

    CCForm.prototype.init = function() {
        $(document.body).on(
            "payment_method_selected",
            this.initializeCcFields.bind(this)
        );
        $(document.body).on("updated_checkout", this.initializeCcFields.bind(this));

        let form;

        if (cynder_paymongo_cc_params.isCheckout) {
            form = $("form.woocommerce-checkout");
            form.on(
                "checkout_place_order_paymongo_card_installment",
                this.onSubmit.bind(this)
            );
            form.on("change", this.onChange.bind(this));
            this.form = form;
            $(document.body).trigger("cynder_paymongo_init_checkout_form", [form]);
        } else if (cynder_paymongo_cc_params.isOrderPay) {
            form = $("#order_review");
            form.on("submit", this.onSubmit.bind(this));
            form.on("change", this.onChange.bind(this));
            this.form = form;
            $(document.body).trigger("cynder_paymongo_init_checkout_form", [form]);
        } else {
            alert(
                "Paymongo cannot find the checkout form. Initialization failed. Try to refresh the page."
            );
        }
    };

    CCForm.prototype.initializeCcFields = function() {
        var paymentMethod = $("input[name=payment_method]:checked").val();

        /** If payment method is not CC, don't initialize form */
        if (paymentMethod !== "paymongo_card_installment") return;

        this.addLoader();

        setTimeout(
            function() {
                this.initCleave();
            }.bind(this),
            500
        );
    };

    CCForm.prototype.initCleave = function() {
        if ($("#paymongo_cc_installment_ccNo").length) {
            var ccNo = new Cleave("#paymongo_cc_installment_ccNo", {
                creditCard: true,
            });
        }

        if ($("#paymongo_cc_installment_expdate").length) {
            var expDate = new Cleave("#paymongo_cc_installment_expdate", {
                date: true,
                datePattern: ["m", "y"],
            });
        }

        if ($("#paymongo_cc_installment_cvv").length) {
            var cvv = new Cleave("#paymongo_cc_installment_cvv", {
                blocks: [4],
            });
        }

        this.removeLoader();
    };

    CCForm.prototype.onChange = function(e) {
        const userLocale =
            navigator.languages && navigator.languages.length ?
            navigator.languages[0] :
            navigator.language;

        const formatNumber = new Intl.NumberFormat(userLocale);

        const cc_installment_tenure = $(
            "input[name='paymongo_cc_installment_tenure']:checked"
        ).val();
        const cc_installment_issuer = $("#paymongo_cc_installment_issuer").val();

        const installment_data = $("input[name='installment-data']").val();

        const formattedInstallmentData = JSON.parse(installment_data);

        if (formattedInstallmentData) {
            let bank_list = "";

            const uniqueIds = [];

            const filteredBankData = formattedInstallmentData.filter((element) => {
                const isDuplicate = uniqueIds.includes(element.issuer_id);

                if (!isDuplicate) {
                    uniqueIds.push(element.issuer_id);

                    return true;
                }

                return false;
            });

            if (filteredBankData) {
                filteredBankData.forEach((cc_banks, i) => {
                    bank_list = bank_list.concat(`
	<option key=${i} id=${i} value=${cc_banks.issuer_id} ${
			i == 0 && "checked"
		  }>${cc_banks.issuer_name}</option>
	`);
                });

                const hasOptions =
                    $("#paymongo_cc_installment_issuer option").length > 0;

                if (!hasOptions) {
                    $("#paymongo_cc_installment_issuer").append(bank_list);
                }
            }

            if (cc_installment_issuer) {
                const bank = formattedInstallmentData.find(
                    (data) => data.issuer_id == cc_installment_issuer
                );

                $("#cc_bank_name").html(bank.issuer_name);
                $("#cc_bank_interest_rate").html(
                    `${bank.bank_interest_rate} Interest Rate`
                );

                if (bank.image_url) {
                    $("#cc_bank_logo").attr("src", bank.image_url);
                    $("#cc_bank_logo_div").addClass("mr-1");
                    $("#cc_bank_logo").addClass("s-7");
                } else {
                    $("#cc_bank_logo").removeAttr("src");
                    $("#cc_bank_logo_div").removeClass("mr-1");
                    $("#cc_bank_logo").removeClass("s-7");
                }
            }

            const selectedBank = formattedInstallmentData.filter(
                (item) => item.issuer_id == cc_installment_issuer
            );

            if (selectedBank) {
                let list = "";

                selectedBank.forEach((selected_installment_period, i) => {
                    list =
                        list.concat(`<li key=${i} class="woocommerce-PaymentMethod woocommerce-PaymentMethod--paymongo wc_payment_method payment_method_paymongo_paymongo">
	<input id="paymongo_cc_installment_tenure_${
	  selected_installment_period.tenure
	}" type="radio" class="input-radio" name="paymongo_cc_installment_tenure" value=${
			  selected_installment_period.tenure
			} ${i == 0 && "checked"} />
	<label id="paymongo_cc_installment_tenure_${
	  selected_installment_period.tenure
	}" class="" for="paymongo_cc_installment_tenure_${
			  selected_installment_period.tenure
			}">${formatNumber.format(
			  selected_installment_period.tenure
			)} Months (${
			  selected_installment_period.processing_fee_percent
			} Processing Fee) <span class="tenure-label">${
			  selected_installment_period.monthly_installment
			} / monthly</span></label>
	</li>`);
                });

                const hasList =
                    $("#installment_list li").length === selectedBank.length;

                if (!hasList) {
                    $("#installment_list li").remove();
                    $("#installment_list").append(list);
                }
            }
        }

        const updateInstallmentDetails = (detail) => {
            $("#auth_amount").html(detail.auth_amount);
            $("#interest_amount_charged").html(detail.interest_amount_charged);
            $("#bank_interest_rate").html(detail.bank_interest_rate);
            $("#processing_fee_value").html(detail.processing_fee_value);
            $("#processing_fee_percent").html(detail.processing_fee_percent);
            $("#loan_amount").html(detail.loan_amount);
            $("#monthly_installment").html(detail.monthly_installment);
            $("#cc_terms_and_conditions").html(detail.terms_and_conditions);
            $("#cc_tenure").html(`${formatNumber.format(detail.tenure)} months`);
        };

        if (cc_installment_tenure && cc_installment_issuer) {
            const cc_installment_tenure = $(
                "input[name='paymongo_cc_installment_tenure']:checked"
            ).val();
            const cc_installment_issuer = $("#paymongo_cc_installment_issuer").val();
            const selectedPlan = formattedInstallmentData.find(
                (item) =>
                item.issuer_id == cc_installment_issuer &&
                item.tenure == cc_installment_tenure
            );

            updateInstallmentDetails(selectedPlan);
        }
        
        $("#paymongo_cc_installment_issuer").change(function() {
            if (
                $("input[name='paymongo_cc_installment_tc']:checked").val() == "yes"
            ) {
                $("input[name='paymongo_cc_installment_tc']").trigger("click");
            }
            $("#installment_list li:first input[type='radio']").trigger("click");
        });
    };

    CCForm.prototype.onSubmit = function(e) {
        const form = this.form;

        var paymentMethod = $("input[name=payment_method]:checked").val();

        if (paymentMethod !== "paymongo_card_installment") {
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
    };

    CCForm.prototype.createPaymentMethod = function() {
        const ccNo = $("#paymongo_cc_installment_ccNo").val();
        const [expMonth, expYear] = $("#paymongo_cc_installment_expdate")
            .val()
            .split("/");
        const cvc = $("#paymongo_cc_installment_cvv").val();

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

        const cc_installment_tenure =
            cynder_paymongo_cc_params.paymongo_cc_installment_tenure ||
            $("input[name='paymongo_cc_installment_tenure']:checked").val();
        const cc_installment_issuer =
            cynder_paymongo_cc_params.paymongo_cc_installment_issuer ||
            $("#paymongo_cc_installment_issuer").val();

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
            payment_method_options: {
                card: {
                    installments: {
                        plan: {
                            issuer_id: cc_installment_issuer,
                            tenure: cc_installment_tenure,
                        },
                    },
                },
            },
        };

        var args = [payload, this.onPaymentMethodCreationResponse.bind(this)];

        this.addLoader();

        $(document.body).trigger("cynder_paymongo_create_payment_method", args);

        return false;
    };

    CCForm.prototype.getName = function() {
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
    };

    CCForm.prototype.onPaymentMethodCreationResponse = function(err, data) {
        this.removeLoader();

        let errors = [];

        const cc_installment_tc = $(
            "input[name='paymongo_cc_installment_tc']:checked"
        ).val();

        const acceptTC = cc_installment_tc === "yes" ? true : false;

        if (err) {
            errors = errors.concat(err.errors);
        }

        if (!acceptTC) {
            errors = [
                ...errors,
                {
                    code: "parameter_required",
                    detail: "Please accept the terms and conditions before to proceed.",
                },
            ];
        }

        if (errors.length > 0) {
            return this.showClientErrors(errors);
        }

        var form = this.form;

        let methodField = form.find(this.method_field_selector);
        const hasMethod = methodField.length;

        if (!hasMethod) {
            form.append(
                '<input type="hidden" id="' +
                this.method_field_name +
                '" name="' +
                this.method_field_name +
                '"/>'
            );
            methodField = form.find(this.method_field_selector);
        }

        methodField.val(data.id);

        form.submit();
    };

    CCForm.prototype.showClientErrors = function(errors) {
        const args = [errors, this.onClientErrorParsed.bind(this)];

        $(document.body).trigger("cynder_paymongo_parse_client_errors", args);
    };

    CCForm.prototype.onClientErrorParsed = function(errorMessages) {
        const errorHtml = errorMessages.reduce((html, errorMessage, index) => {
            let newHtml = html + "<li>" + errorMessage + "</li>";

            if (index === errorMessages.length - 1) {
                newHtml = newHtml + "</ul>";
            }

            return newHtml;
        }, '<ul class="woocommerce-error">');

        return $(document.body).trigger("cynder_paymongo_show_errors", [errorHtml]);
    };

    CCForm.prototype.addLoader = function() {
        $(".wc_payment_method > .payment_box").append(
            '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
        );
    };

    CCForm.prototype.removeLoader = function() {
        $(".paymongo-loading").remove();
    };

    new CCForm();
});