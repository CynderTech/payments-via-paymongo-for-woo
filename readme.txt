=== Payments via PayMongo for WooCommerce ===
Contributors: Cyndertech Corp.
Tags: credit card, gcash, grabpay
Requires at least: 5.0
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: 1.1.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using PayMongo.

== Description ==

Accept Visa, MasterCard, GCash and GrabPay directly on your store with the PayMongo Payment Gateway for WooCommerce

== Disclaimer ==

This plugin was developed by CynderTech Corp. in collaboration with PayMongo Philippines, Inc.

All product and company names are trademarks™ or registered® trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them.

= Simple and easy payments on your store =

The PayMongo plugin extends WooCommerce enabling you to take payment using PayMongo's Payment API

== Installation ==

This gateway requires WooCommerce 3.9.3 and above.

= Automatic installation =

To install the "PayMongo Payment Gateway for WooCommerce" plugin

1. Log in to your WordPress dashboard
2. Navigate to the Plugins menu and click Add New.
3. In the search field type “PayMongo Payment Gateway for WooCommerce” and click Search Plugins.
4. Once you found the "PayMongo Payment Gateway for WooCommerce", click Install Now

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Setup and Configuration =

This step assumes you have already installed the plugin through Wordpress Dashboard or Manual installation

2. Activate the plugin
3. Go to WooCommerce "Settings" Page
4. Select the "Payments" Tab
5. Look for the PayMongo Method and Press "Set up"
6. Enter your Public Key and Secret Key
6.1 GCash/GrabPay Only - Generate a webhook secret using the link provided
6.2 Copy the Webhook Secret and Paste it in the field
7. Click "Save"
8. Enable the Payment Gateways you want to use (Credit Card, GCash or GrabPay)

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Screenshots ==

1. PayMongo Gateways on WooCommerce Payment settings
2. The settings panel used to configure the gateway.
3. Normal checkout with PayMongo.
4. Checking out with GCash.
5. Checking out with GrabPay.

== Changelog ==

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