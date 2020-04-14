function getUrlVars() {
  var vars = {};
  var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (
    m,
    key,
    value
  ) {
    vars[key] = value;
  });
  return vars;
}

jQuery(document).ready(function ($) {
  const paymongoForm = {
    isOrderPay: $(document.body).hasClass("woocommerce-order-pay"),
    checkoutForm: $(document.body).hasClass("woocommerce-order-pay")
      ? $("#order_review")
      : $("form.woocommerce-checkout"),
    init: function () {
      paymongoForm.setUpCleave();
      paymongoForm.checkErrors();

      if (paymongoForm.isOrderPay) {
        paymongoForm.checkoutForm.on(
          "click",
          'input[name="payment_method"]',
          paymongoForm.payment_method_selected
        );

        paymongoForm.checkoutForm.on("submit", paymongoForm.onSubmit);
      } else {
        paymongoForm.checkoutForm.on(
          "checkout_place_order",
          paymongoForm.onSubmit
        );
      }
    },
    checkErrors: function () {
      const params = getUrlVars();
      if (params.paymongo === "gcash_failed") {
        paymongoForm.showError("Failed to authorize GCash transaction");
      }

      if (params.paymongo === "grabpay_failed") {
        paymongoForm.showError("Failed to authorize GrabPay transaction");
      }
    },

    onSubmit: function (e) {
      e.preventDefault(e);

      // if default paymongo
      if ($("#payment_method_paymongo_payment_gateway").attr("checked")) {
        const errors = paymongoForm.validateCardFields() || [];

        if (errors.length) {
          paymongoForm.showErrors(errors);
          return false;
        }

        paymongoForm.createPaymentIntent();
      }

      if ($("#payment_method_paymongo_gcash_payment_gateway").attr("checked")) {
        paymongoForm.createSource("gcash");
      }

      if (
        $("#payment_method_paymongo_grabpay_payment_gateway").attr("checked")
      ) {
        paymongoForm.createSource("grabpay");
      }

      return false;
    },
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
          if (
            res &&
            res.data &&
            res.data.attributes &&
            res.data.attributes.status &&
            res.data.attributes.status === "succeeded"
          ) {
            paymongoForm.checkoutForm.submit();
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

      // add payment intent field
      if (!paymongoForm.checkoutForm.find("#paymongo_client_key").length) {
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
      $(element).append(
        '<div class="paymongo-loading"><div class="paymongo-roller"><div /><div /><div /><div /><div /><div /><div /><div /></div></div>'
      );
    },
    removeLoader: function () {
      $(".paymongo-loading").remove();
    },
    createPaymentIntent: function () {
      paymongoForm.addLoader(
        ".wc_payment_method .payment_box.payment_method_paymongo_payment_gateway"
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
          "This payment option will empty your cart " +
            "and generate an order with pending status.\n" +
            "You can view the order in My Account > Orders\n\n" +
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
        paymongo_params.billing_address_1 || $("#billing_address_1").val();
      const line2 =
        paymongo_params.billing_address_2 || $("#billing_address_2").val();
      const city = paymongo_params.billing_city || $("#billing_city").val();
      const state = paymongo_params.billing_state || $("#billing_state").val();
      const country =
        paymongo_params.billing_country || $("#billing_country").val();
      const postal_code =
        paymongo_params.billing_postcode || $("#billing_postcode").val();
      const name = paymongoForm.getName();
      const email = paymongo_params.billing_email || $("#billing_email").val();
      const phone = paymongo_params.billing_phone || $("#billing_phone").val();

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

      jQuery.ajax({
        url: "https://api.paymongo.com/v1/payment_methods",
        data: JSON.stringify({ data: { attributes: payload } }),
        method: "POST",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          Authorization: "Basic " + btoa(paymongo_params.publicKey),
        },
        success: function (response) {
          $("#paymongo_ccNo").val(null);
          $("#paymongo_expdate").val(null);
          $("#paymongo_cvv").val(null);
          paymongoForm.attachPaymentMethod(response, paymentIntent);
        },
        error: paymongoForm.onFail,
      });

      return false;
    },
    onFail: function (err) {
      if (err.responseJSON && err.responseJSON.errors) {
        const errors = paymongoForm.parsePayMongoErrors(
          err.responseJSON.errors
        );

        paymongoForm.showErrors(errors);
      }
    },
    getName: function () {
      const firstName =
        paymongo_params.billing_first_name || $("#billing_first_name").val();
      const lastName =
        paymongo_params.billing_last_name || $("#billing_last_name").val();

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
        $("#paymongo_ccNo").cleave({
          creditCard: true,
        });
      }

      if ($("#paymongo_expdate").length) {
        $("#paymongo_expdate").cleave({
          date: true,
          datePattern: ["m", "y"],
        });
      }

      if ($("#paymongo_cvv").length) {
        $("#paymongo_cvv").cleave({
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
      $(".paymongo-error").remove();
      $(".woocommerce-notices-wrapper:first").append(
        '<div class="woocommerce-error paymongo-error">' + message + "</div>"
      );
    },
    showErrors: function (errors) {
      // Remove notices from all sources
      $(".woocommerce-error, .woocommerce-message").remove();
      $(".blockUI").remove();
      paymongoForm.removeLoader();

      if (!errors.length) return;

      let messages = '<ul class="woocommerce-error">';

      for (let x = 0; x < errors.length; x++) {
        messages += "<li>" + errors[x] + "</li>";
        if (x === errors.length) {
          messages += "</ul>";
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

      const errors = [];

      for (let x = 0; x < paymongoErrors.length; x++) {
        errors.push(
          paymongoErrors[x].detail + " (CODE: " + paymongoErrors[x].code + ")"
        );
      }

      return errors;
    },
  };

  paymongoForm.init();
});
