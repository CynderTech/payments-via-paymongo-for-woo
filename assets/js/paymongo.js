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
    },
    showError: (message) => {
      $(".paymongo-error").remove();
      $(".woocommerce-notices-wrapper:first").append(
        '<div class="woocommerce-error paymongo-error">' + message + "</div>"
      );
    },
    onSubmit: function (e) {
      e.preventDefault(e);

      // if default paymongo
      if ($("#payment_method_paymongo_payment_gateway").attr("checked")) {
        paymongoForm.createPaymentIntent();
      }

      if ($("#payment_method_paymongo_gcash_payment_gateway").attr("checked")) {
        paymongoForm.createSource("gcash");
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
    createSource: function () {
      console.log("createSource");
      jQuery.post(
        wc_checkout_params.checkout_url + "&gcash=true",
        paymongoForm.checkoutForm.serialize(),
        paymongoForm.onCreateSourceSuccess
      );

      return false;
    },
    createPaymentMethod: function (paymentIntent) {
      const ccNo = $("#paymongo_ccNo").val();
      const [expMonth, expYear] = $("#paymongo_expdate").val().split("/");
      const cvc = $("#paymongo_cvv").val();

      const line1 = paymongo_params.billing_address_1;
      const line2 = paymongo_params.billing_address_2;
      const city = paymongo_params.billing_city;
      const state = paymongo_params.billing_state;
      const country = paymongo_params.billing_country;
      const postal_code = paymongo_params.billing_postcode;
      const name = paymongoForm.getName();
      const email = paymongo_params.billing_email;
      const phone = paymongo_params.billing_phone;

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
      paymongoForm.removeLoader();
      $(".blockUI").remove();
    },
    getName: function () {
      let name =
        paymongo_params.billing_first_name +
        " " +
        paymongo_params.billing_last_name;
      let companyName = paymongo_params.billing_company;

      if (companyName && companyName.length) {
        name = name + " - " + companyName;
      }

      return name;
    },
    onCreateSourceSuccess: (res) => {
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
  };

  paymongoForm.init();
});
