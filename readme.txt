=== Payments via PayMongo for WooCommerce ===
Contributors: pickmeshop
Tags: payments, credit card, gcash, grabpay
Requires at least: 5.0
Tested up to: 6.2
Requires PHP: 5.6
Stable tag: 1.12.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Take payments on your store using PayMongo.

== Description ==

Accept Visa, MasterCard, GCash, GrabPay, Maya, Atome, BPI, and BillEase directly on your store with the PayMongo Payment Gateway for WooCommerce

** 1.6.x UPDATE INSTRUCTIONS **

If you are coming from a plugin version lower than 1.6.x, please follow [this guide](https://cynder.atlassian.net/servicedesk/customer/portal/1/article/709656577).

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
3. Choose a payment option and click the **Set Up** button associated to it.
    * **Note:** The plugin settings starting from API settings is shared. **You need to configure the plugin once with any payment option and just enable any number of payment options**.

**Test Configuration** *(Optional but highly recommended)*

Before accepting actual payments for transactions, it is ideal to test payment workflows using the ***test mode***. Below are the steps to configure your plugin in test mode.

1. Under the **Test Environment** subsection, enter your test public key and test secret key in their respective fields. You may obtain your test keys by registering an account at [PayMongo](https://paymongo.com).
2. Click the **Save Changes** button.
3. Proceed with testing by ordering items from your store and head to checkout.

**Live Configuration**

1. Under the **Live Environment** subsection, enter your live public key and live secret key in their respective fields. You may obtain your live keys by registering an account at [PayMongo](https://paymongo.com) **and have it verified**.
2. Click the **Save Changes** button.
3. Done.

**Webhooks Configuration**

For the plugin to work, you need to specify a webhook secret key in the corresponding settings fields. You can generate one using Cynder's [Webhook Secret Key Generator](https://paymongo-webhook-tool.meeco.dev). A convenience link is provided just below the field. After generating a webhook secret key using the key generator, paste it on the webhook secret key fields.

* **Note:** Webhook secret keys can be created using the PayMongo secret keys. You need to generate 2 separate webhook secret keys using production and sandbox secret keys if you want to test the plugin first and later put it to production mode.

* **Note:** The key generator requires a specific URL provided to you by the plugin under the webhook secret key field (ex. ***https://example.com/?wc-api=cynder_paymongo***), as well as your PayMongo secret key.

* **Note:** If you've generated a key for the same domain prior to the current update, you can retrieve it using the Retrieve section of the key generator.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Screenshots ==

1. PayMongo Gateways on WooCommerce Payment settings
2. The settings panel used to configure the gateway.
3. Payment methods as displayed in checkout page.

== Changelog ==

= 1.12.4 =
*Release Date - July 5, 2023*
[FIX] Fix dependency issue
[CHORE] Compatibility tests with WC 7.8.2

= 1.12.3 =
*Release Date - June 30, 2023*
[FIX] Fix multi-shop API key usage
[FIX] Create payment intent error handling
[CHORE] Compatibility tests with WC 7.8.1

= 1.12.2 =
*Release Date - May 22, 2023
[FIX] Fix BPI DOB issue on production mode

= 1.12.1 =
*Release Date - May 19, 2023*
[FIX] Fix crashes after checkout due to illegal offset type on action hooks
[FIX] Fix irrelevant payment webhooks processing from PayMongo Pages and Links
[FIX] Fix BPI payment method detail generation
[CHORE] Compatibility tests with WC 7.7.0

= 1.12.0 =
*Release Date - May 8, 2023*
[FEATURE] GCash and GrabPay is now using Payment Intent workflow
[FIX] Fix fatal errors
[CHORE] House cleaning and code maintenance
[CHORE] Compatibility tests with WC 7.6.1 & WP 6.2

= 1.11.2 =
*Release Date - September 28, 2022*
[FIX] Fix webhook issues

= 1.11.1 =
*Release Date - September 27, 2022*
[CHORE] Added telemetry filter for sandbox environments

= 1.11.0 =
*Release Date - September 27, 2022*
[FEATURE] Added simple custom hooks for successful/failed payments
[CHORE] Added telemetry
[CHORE] Compatibility tests for WC 6.9.4

= 1.10.0 =
*Release Date - September 6, 2022*
[FEATURE] Added BillEase payment method
[CHORE] Compatibility tests for WC 6.8.2

= 1.9.1 =
*Release Date - June 21, 2022*
[FEATURE] Added BPI DOB payment method
[FEATURE] Added option to disable automatic invoice sending after payment resolution
[CHORE] Compatibility tests for WP 6.0
[CHORE] Compatibility tests for WC 6.6.0

= 1.8.1 =
*Release Date - May 16, 2022*
[FEATURE] Added Atome payment method
[CHORE] Compatibility tests for WC 6.4.1
[CHORE] Updated PayMaya logo to Maya logo

= 1.7.5 =
*Release Date - March 28, 2022*
[FIX] Fix fatal error on webhook processing if no order found

= 1.7.4 =
*Release Date - February 15, 2022*
[FIX] PayMaya billing details fix
[CHORE] Compatibility tests for WC 6.2.0

= 1.7.3 =
*Release Date - January 27, 2022*
[FIX] PayMaya intent creation fix
[CHORE] Compatibility tests for WP 5.9 and WC 6.1.1

= 1.7.2 =
*Release Date - 1 December 2021*
[FIX] Remove more dangling commas

= 1.7.1 =
*Release Date - 1 December 2021*
[FIX] Remove dangling comma for backwards compatibility

= 1.7.0 =
*Release Date - 26 November 2021*

[FEATURE] Add metadata tags for PayMongo analytics
[FIX] Fix header workaround to avoid capitalization
[CHORE] Add PayMaya logo

= 1.6.3 =
*Release Date - 22 November 2021*

[FEATURE] Include PayMaya payment method

= 1.6.2 =
*Release Date - 10 November 2021*

[FIX] Error handling for e-wallet webhooks

= 1.6.1 =
*Release Date - 26 October 2021*

[FIX] Fix header parser for webhooks verification
[FIX] Fix failed payments logs
[CHORE] Add debuggers for webhooks verification

= 1.6.0 =
*Release Date - 24 May 2021*

[FIX] Added payment resolution redundancy for credit card payments
[FEATURE] Added debug mode toggle in admin dashboard settings, including debug messages for analysis
[CHORE] Added admin dashboard notice for 1.5.x update

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