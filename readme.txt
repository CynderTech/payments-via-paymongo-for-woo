=== Payments via PayMongo for WooCommerce ===
Contributors: pickmeshop
Tags: payments, credit card, gcash, grabpay
Requires at least: 5.0
Tested up to: 5.7
Requires PHP: 5.6
Stable tag: 1.5.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Take payments on your store using PayMongo.

== Description ==

Accept Visa, MasterCard, GCash and GrabPay directly on your store with the PayMongo Payment Gateway for WooCommerce

** 1.5.x UPDATE INSTRUCTIONS **

If you are coming from a plugin version lower than 1.5.0, you need to do the following for the plugin to work properly.

1. Go to the [webhook tool](https://paymongo-webhook-tool.meeco.dev).
2. On the Retrieve tab, enter your PayMongo secret key and click on the Retrieve Webhooks button.
3. Get the corresponding hook ID (starts with hook_) for your domain and copy it.
4. On the Update tab, enter your PayMongo secret key on the first field and the hook ID on the second field.
5. Click on Update Webhook.

**Notes:**
* Paymongo API **only** supports the **PHP (Philippine Peso)** currency at the moment. Prices for your shop should be configured to PHP for the plugin to work.
* Paymongo API can **only** process at least P100.00 for the total amount to be paid.
* Some WordPress themes/plugins may not be compatible with this plugin. To isolate which theme/plugin, you may follow [this guide](https://docs.woocommerce.com/document/woocommerce-self-service-guide/#section-4). Once isolated, you may contact CynderTech [here](https://cynder.atlassian.net/servicedesk/customer/portal/1/group/1/create/1) to address your concerns.

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
    * **Note:** The main API key settings of the whole plugin is inside the card payment option subsection. **You need to configure the plugin in this section even though you're not gonna accept credit/debit card payments through PayMongo**.

**Test Configuration** *(Optional but highly recommended)*

Before accepting actual payments for transactions, it is ideal to test payment workflows using the ***test mode***. Below are the steps to configure your plugin in test mode.

1. Under the **Test Environment** subsection, enter your test public key and test secret key in their respective fields. You may obtain your test keys by registering an account at [PayMongo](https://paymongo.com).
2. Click the **Save Changes** button.
3. Proceed with testing by ordering items from your store and head to checkout.

**Live Configuration**

1. Under the **Live Environment** subsection, enter your live public key and live secret key in their respective fields. You may obtain your live keys by registering an account at [PayMongo](https://paymongo.com) **and have it verified**.
2. Click the **Save Changes** button.
3. Done.

**E-wallets Configuration**

For e-wallet transactions, you need to specify a webhook secret key in the e-wallet settings. You can generate one using Cynder's [Webhook Secret Key Generator](https://paymongo-webhook-tool.meeco.dev). A convenience link is provided just below the field. After generating a webhook secret key using the key generator, paste it on the webhook secret key field.

* **Note:** The webhook secret key field is **ONLY REQUIRED** if you would be enabling GCash and/or GrabPay as payment method/s for your platform. This allows PayMongo to notify WooCommerce about payment processing using these methods after payment has been authorized by the customer.

* **Note:** The key generator requires a specific URL provided to you by the plugin under the webhook secret key field (ex. ***https://example.com/?wc-api=cynder_paymongo***), as well as your test secret key.

* **Note:** If you've generated a key for the same domain prior to the current update, you can retrieve it using the Retrieve section of the key generator.

* **Note:** Setting the webhook secret key for either GCash or GrabPay configures both options. You can just enable or disable each payment option depending on your needs.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Screenshots ==

1. PayMongo Gateways on WooCommerce Payment settings
2. The settings panel used to configure the gateway.
3. Normal checkout with PayMongo.
4. Checking out with GCash.
5. Checking out with GrabPay.

== Changelog ==

= 1.5.2 =
*Release Date - 6 April 2021*

[FIX] Increase timeout for Paymongo API requests from default 5sec to 60sec
[CHANGE] Added redundancy initializer for CC fields in checkout
[CHORE] Removed unnecessary logs

= 1.5.1 =
*Release Date - 31 March 2021*

[FIX] Order statuses now reflect payment statuses (ex. failed payments now marks orders as failed)
[FIX] Credit card fields not properly initializing on checkout load
[CHANGE] Updated settings interface for all PayMongo payment methods
[CHORE] Remove unnecessary files

= 1.4.7 =
*Release Date - 15 February 2021*

[CHORE] Tested for WooCommerce v5.0.0
[FIX] Enable scripts for unsecured live environment but display a warning

= 1.4.6 =
*Release Date - 4 February 2021*

[FIX] 100% discount coupons should be processed properly

= 1.4.5 =
*Release Date - 28 January 2021*

[FIX] Optional fields for Paymongo source creation have been modified for e-wallet transactions
[FIX] JS front-end assets are only being loaded on checkout and order pay pages

= 1.4.4 =
*Release Date - 11 November 2020*

[CHANGE] Error message for payment method ID not found is changed to a user-friendly text

= 1.4.3 =
*Release Date - 5 November 2020*

[CHANGE] Paymongo dashboard description format now displays store name and order ID

= 1.4.2 =
*Release Date - 22 October 2020*

[FIX] Fixed redirect for failed e-wallet transactions
[FIX] Fixed credit card validation on order pay page

= 1.4.1 =
*Release Date - 18 October 2020*

[FIX] Fixed error handling for credit card transactions

= 1.4.0 =
*Release Date - 18 October 2020*

[FIX] Applying discounts to total amount on checkout
[FIX] Fixed race conditions for JS-dependent workflows due to network issues
[FIX] Order ID is now being attached to payment records on Paymongo dashboard

= 1.3.7 =
*Release Date - 21 September 2020*

[FIX] Total amount is now based on cart totals

= 1.3.6 =
*Release Date - 19 September 2020*

[FIX] Total amount parser fix

= 1.3.4 =
*Release Date - 18 September 2020*

[FIX] Validation message for total amount less than 100

= 1.3.0 =
*Release Date - 18 September 2020*

[FIX] Re-enabled e-wallet (GCash/GrabPay) functionality
[FIX] Proper handling for amount validation which causes infinite loading for credit card payment option
[REFACTOR] Resectioning of webhook secret key field to e-wallet section (shared for both GCash and GrabPay)

= 1.2.1 =
*Release Date - 15 September 2020*

[FIX] Fixed issue on payment status while processing

= 1.2.0 =
*Release Date - 15 September 2020*

* [REFACTOR] Credit card workflow refactor fixing a couple of issues, including a critical issue for double-charging
* [REFACTOR] 3DS payment authorization is now redirect-based instead of being shown in a modal; jQuery modal removed as a dependency
* [REFACTOR] Broken up JS assets to several files, decoupling logic from UI manipulations

= 1.1.5 =
*Release Date - 12 August 2020*

* [CHORE] Changed Webhook Tool domain: https://paymongo-webhook-tool.meeco.dev

= 1.1.4 =
*Release Date - 12 August 2020*

* [FIX] fixed error parsing for PayMongo errors

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