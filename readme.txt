=== Payments via PayMongo for WooCommerce ===
Contributors: pickmeshop
Tags: payments, credit card, gcash, grabpay
Requires at least: 5.0
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: 1.1.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using PayMongo.

== Description ==

Accept Visa, MasterCard, GCash and GrabPay directly on your store with the PayMongo Payment Gateway for WooCommerce

== Disclaimer ==

This plugin was developed by [CynderTech Corp.](https://www.cynder.io) in collaboration with [PayMongo Philippines, Inc.](https://paymongo.com).

All product and company names are trademarks™ or registered® trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them.

= Simple and easy payments on your store =

The PayMongo plugin extends WooCommerce enabling you to take payments using [PayMongo's Payment API](https://developers.paymongo.com/).

== Installation ==

This gateway requires WooCommerce 3.9.3 and above.

= Automatic installation =

1. Log in to your WordPress dashboard.
2. Navigate to the **Plugins** page from the left sidebar.
3. Click the **Add New** button on the upper left corner of the main panel.
4. In the search box on the upper right corner of the main panel, type ***PayMongo Payment Gateway for WooCommerce*** and wait for it to load.
5. Once you found the **PayMongo Payment Gateway for WooCommerce** plugin item, click **Install Now**.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Setup and Configuration =

*This step assumes you have already installed the plugin on your WordPress deployment, alongside the WooCommerce plugin*

1. Activate the plugin.
2. From the left sidebar, navigate to **WooCommerce** > **Settings** > **Payments**.
3. Look for the **Card Payments via PayMongo** option and click the **Set Up** button.
    * **Note:** The main configuration settings of the whole plugin are inside the card payment option subsection. **You need to configure the plugin in this section even though you're not gonna accept credit/debit card payments through PayMongo**.

**Test Configuration** *(Optional but highly recommended)*

Before accepting actual payments for transactions, it is ideal to test payment workflows using the ***test mode***. Below are the steps to configure your plugin in test mode.

1. Under the **Test Environment** subsection, enter your test public key and test secret key in their respective fields. You may obtain your test keys by registering an account at [PayMongo](https://paymongo.com).
2. Generate a webhook secret key [^1] using Cynder's [Webhook Secret Key Generator](https://paymongo-wsk-generator.cynder.io). A convenience link is provided just below the field. After generating a webhook secret key using the key generator, paste it on the webhook secret key field.
    * **Note:** The key generator requires a specific URL provided to you by the plugin under the webhook secret key field (ex. ***https://example.com/?wc-api=cynder_paymongo***), as well as your test secret key.
3. Click the **Save Changes** button.
4. Proceed with testing by ordering items from your store and head to checkout.

**Live Configuration**

1. Under the **Live Environment** subsection, enter your live public key and live secret key in their respective fields. You may obtain your live keys by registering an account at [PayMongo](https://paymongo.com) **and have it verified**.
2. Generate a webhook secret key [^1] using Cynder's [Webhook Secret Key Generator](https://paymongo-wsk-generator.cynder.io). A convenience link is provided just below the field. After generating a webhook secret key using the key generator, paste it on the webhook secret key field.
    * **Note:** The key generator requires a specific URL provided to you by the plugin under the webhook secret key field (ex. ***https://example.com/?wc-api=cynder_paymongo***), as well as your live secret key.
3. Click the **Save Changes** button.
4. Done.

[^1]: The webhook secret key field is **ONLY REQUIRED** if you would be enabling GCash and/or GrabPay as payment method/s for your platform. This allows PayMongo to notify WooCommerce about payment processing using these methods after payment has been authorized by the customer.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Screenshots ==

1. PayMongo Gateways on WooCommerce Payment settings
2. The settings panel used to configure the gateway.
3. Normal checkout with PayMongo.
4. Checking out with GCash.
5. Checking out with GrabPay.

== Changelog ==

= 1.1.3 =
*Release Date - 22 July 2020*

* [FIX] fixed failure to retrieve GCash link error
* [FIX] fixed missing client_key parameter error
* [FIX] fixed issue on disabling of other payment options such as PayPal, etc.
* [CHANGE] GCash icon change

= 1.1.2 =
*Release Date - 21 July 2020*

* [FIX] fix icon styles 


= 1.1.1 =
*Release Date - 10 July 2020*

* [CHANGE] Now sends invoice through e-mail on successful payment
* [CHANGE] Now uses *Payment ID* instead of *Payment Intent ID*

= 1.1.0 =
*Release Date - 27 June 2020*

* [FIX]    3D Secure Implementation
* [FIX]    Oversized Payment Method Logos
* [CHANGE] Change Settings Layout
* [CHANGE] Included link to Webhook Secret Generator