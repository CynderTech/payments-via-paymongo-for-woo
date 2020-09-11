function getUrlVars() {
    var vars = {};
    var parts = window.location.href.replace(
        /[?&]+([^=&]+)=([^&]*)/gi,
        function (m, key, value) {
            vars[key] = value;
        }
    );
    return vars;
}

jQuery(document).ready(function ($) {
    const paymongoForm = {
        initialized: false,
        isOrderPay: $(document.body).hasClass("woocommerce-order-pay"),
        checkoutForm: $(document.body).hasClass("woocommerce-order-pay")
            ? $("#order_review")
            : $("form.woocommerce-checkout"),
        init: function () {
            if (paymongoForm.initialized) return;
            if (paymongoForm.isOrderPay) {
                // paymongoForm.checkoutForm.on("submit", paymongoForm.onSubmit);
            } else {
                // paymongoForm.checkoutForm.on(
                //     "checkout_place_order_paymongo checkout_place_order_paymongo_gcash checkout_place_order_paymongo_grabpay",
                //     paymongoForm.onSubmit
                // );
            }
            paymongoForm.initialized = true;
        },
        checkErrors: function () {
            const params = getUrlVars();
            if (params.paymongo === "gcash_failed") {
                paymongoForm.showError("Failed to authorize GCash transaction");
            }

            if (params.paymongo === "grabpay_failed") {
                paymongoForm.showError(
                    "Failed to authorize GrabPay transaction"
                );
            }
        },

        // onSubmit: function (e) {
        //     e.preventDefault(e);

        //     var paymentMethod = $('input[name=payment_method]:checked').val(); 

        //     // if default paymongo
        //     if (paymentMethod == "paymongo") {
        //         const errors = paymongoForm.validateCardFields() || [];

        //         if (errors.length) {
        //             paymongoForm.showErrors(errors);
        //             return false;
        //         }

        //         paymongoForm.createPaymentIntent();
        //     }

        //     if (paymentMethod == "paymongo_gcash") {
        //         paymongoForm.createSource("gcash");
        //     }

        //     if (paymentMethod == "paymongo_grabpay") {
        //         paymongoForm.createSource("grabpay");
        //     }

        //     return false;
        // },
        attachPaymentMethod: function (response, paymentIntent) {
            jQuery.ajax({
                url:
                    "https://api.paymongo.com/v1/payment_intents/" +
                    paymentIntent.payment_intent_id +
                    "/attach",
                data: JSON.stringify({
                    data: {
                        attributes: {
                            client_key: paymentIntent.payment_client_key,
                            payment_method: response.data.id,
                        },
                    },
                }),
                method: "POST",
                headers: {
                    accept: "application/json",
                    "content-type": "application/json",
                    Authorization: "Basic " + btoa(paymongo_params.publicKey),
                },
                success: function (res) {
                    if (!res || !res.data || !res.data.attributes) {
                        return paymongoForm.onFail("Response is invalid");
                    }

                    const attributes = res.data.attributes;
                    const status = attributes.status;

                    if (status === "succeeded") {
                        return paymongoForm.checkoutForm.submit();
                    }

                    if (
                        status === "awaiting_next_action" &&
                        attributes.next_action
                    ) {
                        paymongoForm.setThreeDSListener(
                            paymentIntent.payment_intent_id,
                            paymentIntent.payment_client_key
                        );
                        paymongoForm.checkoutForm.append(
                            '<div id="paymongo-3ds-modal" class="paymongo-modal modal">' +
                                '<iframe src="' +
                                attributes.next_action.redirect.url +
                                '" />' +
                                "</div>"
                        );

                        $("#paymongo-3ds-modal").modal({
                            fadeDuration: 200,
                        });
                    }
                },
                error: paymongoForm.onFail,
            });
        },
        onPaymentIntentSuccess: function (res) {
            if (res.result && res.result === "error") {
                const errors = paymongoForm.parsePayMongoErrors(res.errors);
                paymongoForm.showErrors(errors);
                return;
            }

            if (res.result && res.result === "failure" && res.messages) {
                paymongoForm.showErrors(res.messages, true);
                return;
            }

            if (res.result && res.result === "success" && res.redirect) {
                return window.location.replace(res.redirect);
            }

            // add payment intent field
            if (
                !paymongoForm.checkoutForm.find("#paymongo_client_key").length
            ) {
                paymongoForm.checkoutForm.append(
                    '<input type="hidden" id="paymongo_client_key" name="paymongo_client_key"/>'
                );
            }

            if (!paymongoForm.checkoutForm.find("#paymongo_intent_id").length) {
                paymongoForm.checkoutForm.append(
                    '<input type="hidden" id="paymongo_intent_id" name="paymongo_intent_id"/>'
                );
            }

            paymongoForm.checkoutForm
                .find("#paymongo_client_key")
                .val(res.payment_client_key);
            paymongoForm.checkoutForm
                .find("#paymongo_intent_id")
                .val(res.payment_intent_id);

            if (paymongoForm.isOrderPay) {
                paymongoForm.checkoutForm.off("submit", paymongoForm.onSubmit);
            } else {
                paymongoForm.checkoutForm.off(
                    "checkout_place_order",
                    paymongoForm.onSubmit
                );
            }

            paymongoForm.createPaymentMethod(res);
        },
        addLoader: function (element) {
            console.log($(element));
            $(element).append(
                '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
            );
        },
        removeLoader: function () {
            $(".paymongo-loading").remove();
        },
        createPaymentIntent: function () {
            paymongoForm.addLoader(
                ".wc_payment_method .payment_box.payment_method_paymongo"
            );

            jQuery.post(
                paymongoForm.isOrderPay
                    ? paymongo_params.order_pay_url
                    : wc_checkout_params.checkout_url,
                paymongoForm.checkoutForm.serialize(),
                paymongoForm.onPaymentIntentSuccess
            );

            return false;
        },
        createSource: function (type) {
            if (
                !window.confirm(
                    "This will redirect you to the payment website\n" +
                        "Do you want to proceed?"
                )
            ) {
                return;
            }

            jQuery.post(
                (paymongoForm.isOrderPay
                    ? paymongo_params.order_pay_url
                    : wc_checkout_params.checkout_url) +
                    "&" +
                    type +
                    "=true",
                paymongoForm.checkoutForm.serialize(),
                paymongoForm.onCreateSourceSuccess
            );

            return false;
        },
        createPaymentMethod: function (paymentIntent) {
            const ccNo = $("#paymongo_ccNo").val();
            const [expMonth, expYear] = $("#paymongo_expdate").val().split("/");
            const cvc = $("#paymongo_cvv").val();

            const line1 =
                paymongo_params.billing_address_1 ||
                $("#billing_address_1").val();
            const line2 =
                paymongo_params.billing_address_2 ||
                $("#billing_address_2").val();
            const city =
                paymongo_params.billing_city || $("#billing_city").val();
            const state =
                paymongo_params.billing_state || $("#billing_state").val();
            const country =
                paymongo_params.billing_country || $("#billing_country").val();
            const postal_code =
                paymongo_params.billing_postcode ||
                $("#billing_postcode").val();
            const name = paymongoForm.getName();
            const email =
                paymongo_params.billing_email || $("#billing_email").val();
            const phone =
                paymongo_params.billing_phone || $("#billing_phone").val();

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

            $.ajax({
                url: "https://api.paymongo.com/v1/payment_methods",
                data: JSON.stringify({ data: { attributes: payload } }),
                method: "POST",
                headers: {
                    accept: "application/json",
                    "content-type": "application/json",
                    Authorization: "Basic " + btoa(paymongo_params.publicKey),
                },
                success: function (response) {
                    paymongoForm.attachPaymentMethod(response, paymentIntent);
                },
                error: paymongoForm.onFail,
            });

            return false;
        },
        onFail: function (err) {
            if ($(document).find("#paymongo_client_key").length) {
                $("#paymongo_client_key").remove();
            }

            if ($(document).find("#paymongo_intent_id").length) {
                $("#paymongo_intent_id").remove();
            }

            if (err.responseJSON && err.responseJSON.errors) {
                const errors = paymongoForm.parsePayMongoErrors(
                    err.responseJSON.errors
                );

                return paymongoForm.showErrors(errors);
            }

            if (typeof err === "string") {
                return paymongoForm.showError(err);
            }
        },
        getName: function () {
            const firstName =
                paymongo_params.billing_first_name ||
                $("#billing_first_name").val();
            const lastName =
                paymongo_params.billing_last_name ||
                $("#billing_last_name").val();

            let name = firstName + " " + lastName;
            let companyName =
                paymongo_params.billing_company || $("#billing_company").val();

            if (companyName && companyName.length) {
                name = name + " - " + companyName;
            }

            return name;
        },
        onCreateSourceSuccess: (res) => {
            if (res.result && res.result === "error") {
                const errors = paymongoForm.parsePayMongoErrors(res.errors);
                paymongoForm.showErrors(errors);
                return;
            }

            if (res.result && res.result === "failure" && res.messages) {
                paymongoForm.showErrors(res.messages, true);
                return;
            }

            if (!res.checkout_url) {
                return paymongoForm.showError(
                    "Failed to get Gcash Link, Please try again"
                );
            }
            const checkoutUrl = res.checkout_url || null;
            if (checkoutUrl) {
                window.location.replace(checkoutUrl);
            }
        },
        setUpCleave: function () {
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
                var expDate = new Cleave("#paymongo_cvv", {
                    blocks: [4],
                });
            }
        },
        scrollToNotices: function () {
            var scrollElement = $(
                ".woocommerce-NoticeGroup, .woocommerce-NoticeGroup"
            );

            if (!scrollElement.length) {
                scrollElement = paymongoForm.checkoutForm;
            }
            $.scroll_to_notices(scrollElement);
        },
        showError: (message) => {
            $(
                ".woocommerce-error, .woocommerce-message, .paymongo-error"
            ).remove();
            $(".blockUI").remove();
            paymongoForm.removeLoader();
            paymongoForm.checkoutForm.prepend(
                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-PayMongoErrors">' +
                    '<div class="woocommerce-error paymongo-error">' +
                    message +
                    "</div>" +
                    "</div>"
            );
            paymongoForm.scrollToNotices();
        },
        showErrors: function (errors, predefined) {
            // Remove notices from all sources
            $(
                ".woocommerce-error, .woocommerce-message, .paymongo-error"
            ).remove();
            $(".blockUI").remove();
            paymongoForm.removeLoader();

            if (!errors.length) return;

            let messages = '<ul class="woocommerce-error">';

            /**
             * If errors are predefined HTML, particularly coming from the
             * response of WP or Woo, show it directly.
             */
            if (typeof predefined !== 'undefined' && predefined) {
                messages = errors;
            } else {
                for (let x = 0; x < errors.length; x++) {
                    messages += "<li>" + errors[x] + "</li>";
                    if (x === errors.length) {
                        messages += "</ul>";
                    }
                }
            }

            paymongoForm.checkoutForm.prepend(
                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-PayMongoErrors">' +
                    messages +
                    "</div>"
            );
            // Lose focus for all fields
            paymongoForm.checkoutForm
                .find(".input-text, select, input:checkbox")
                .trigger("validate")
                .blur();

            paymongoForm.scrollToNotices();
        },
        validateCardFields: function (payload) {
            const errors = [];
            const ccNo = $("#paymongo_ccNo").val();
            const expDate = $("#paymongo_expdate").val();
            const [expMonth, expYear] = expDate.split("/");
            const cvc = $("#paymongo_cvv").val();

            if (!ccNo) {
                errors.push("<b>Card Number<b> is required.");
            }

            if (!expDate) {
                errors.push("<b>Expiration Date</b> is required.");
            } else if (expDate.indexOf("/") > -1) {
                if (!expMonth) {
                    errors.push("<b>Expiration Month</b> is required.");
                }

                if (!expYear) {
                    errors.push("<b>Expiration Year</b> is required.");
                }
            } else {
                errors.push("<b>Expiration date</b> is invalid ('MM/YY')");
            }

            if (!cvc) {
                errors.push("<b>CVC</b> is required.");
            }

            return errors;
        },
        parsePayMongoErrors: function (paymongoErrors) {
            if (!paymongoErrors || !paymongoErrors.length) {
                paymongoForm.showError("Something went wrong.");
            }

            return paymongoErrors.map(({ detail, sub_code }) => {
                const invalidSubcodes = [
                    'processor_blocked',
                    'lost_card',
                    'stolen_card',
                    'blocked'
                ];

                if (invalidSubcodes.includes(sub_code)) return 'Something went wrong. Please try again.';
                
                if (!detail.includes('details.')) return detail;

                return detail
                    .split(' ')
                    .reduce((message, part) => {
                        if (!part.includes('details.')) {
                            if (!message) {
                                return part;
                            } else {
                                return message + ' ' + part;
                            }
                        }

                        const field = part
                            .split('.')
                            .pop()
                            .split('_')
                            .map((fieldPart, index) => {
                                if (index === 0) return fieldPart[0].toUpperCase() + fieldPart.slice(1);

                                return fieldPart;
                            })
                            .join(' ');
                        
                        return message + ' ' + field;
                    }, '');
            });
        },
        setThreeDSListener: function (intentId, clientKey) {
            window.addEventListener("message", (ev) => {
                if (ev.data === "3DS-authentication-complete") {
                    jQuery.ajax({
                        url:
                            "https://api.paymongo.com/v1/payment_intents/" +
                            intentId,
                        data: {
                            client_key: clientKey,
                        },
                        method: "GET",
                        headers: {
                            accept: "application/json",
                            "content-type": "application/json",
                            Authorization:
                                "Basic " + btoa(paymongo_params.publicKey),
                        },
                        success: function (response) {
                            var paymentIntent = response.data;
                            var paymentIntentStatus =
                                paymentIntent.attributes.status;

                            if (paymentIntentStatus === "succeeded") {
                                paymongoForm.checkoutForm.submit();
                            } else if (
                                paymentIntentStatus ===
                                "awaiting_payment_method"
                            ) {
                                $.modal.close();
                                paymongoForm.onFail(
                                    paymentIntent.attributes.last_payment_error
                                        .failed_message
                                );
                            }
                        },
                        error: paymongoForm.onFail,
                    });
                }
            });
        },
    };

    // initialize form on payment method select
    // $(document).on(
    //     "change",
    //     "#payment_method_paymongo, #payment_method_paymongo_gcash, #payment_method_paymongo_grabpay",
    //     function () {
    //         if (this.checked) {
    //             paymongoForm.setUpCleave();
    //             paymongoForm.init();
    //         }
    //     }
    // );

    // setup cleave on form focus
    // $(document).on("focus", "#paymongo_expdate, #paymongo_ccNo", function () {
    //     paymongoForm.setUpCleave();
    // });

    // check if one of the payment methods is already selected
    // if (
    //     $(document).find(
    //         "#payment_method_paymongo:checked, #payment_method_paymongo_gcash:checked, #payment_method_paymongo_grabpay:checked"
    //     ).length
    // ) {
    //     paymongoForm.init();
    // }
});
