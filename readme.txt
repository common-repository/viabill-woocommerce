=== ViaBill - WooCommerce ===
Contributors: viabill
Tags: viabill, woocommerce, gateway, payment
Requires at least: 5.0
Tested up to: 6.5.2
Requires PHP: 5.6
Stable tag: 1.1.52
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

ViaBill Gateway for WooCommerce.

== Description ==

ViaBill provides a seamless financing solution for thousands of merchants and customers across the world. We’re increasing average order sizes, providing low transaction costs and putting the customer in focus so they can get what they want when they want it; all of which benefits, you, the merchant.

ViaBill - WooCommerce is a plugin that allows you to make payments via ViaBill platform. You will be able to capture and refund your ViaBill orders directly in your back office and you can even activate the PriceTag. The PriceTag notifies the shoppers about the ability to finance their purchases long before they reach the checkout page, which ensures a higher conversion-rate and increased average basket size. The ViaBill PriceTag can be activated in the extension too.

== Installation ==

= Minimum Requirements =

* WooCommerce 3.3 or greater.
* PHP version 5.6 or greater (PHP 7.4 or greater is recommended).
* SSL must be installed on your site and active on your Checkout pages.

= Install =

1. Visit Plugins > Add New
2. Search for "ViaBill - WooCommerce"
3. Activate ViaBill - WooCommerce plugin

For more installation options check the [official WordPress documentation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation) about installing plugins.

== Changelog ==
= 1.1.52 =
* Sanity check the success, cancel and callback URLs before making a payment request.

= 1.1.51 =
* Added the apiKey to the support form, plus a link to reset it.

= 1.1.50 =
* Better error handling, in case a transaction fails on the server side.

= 1.1.49 =
* Removed extra register_compatibility function call.

= 1.1.48 =
* Added support for the WooCommerce Checkout Blocks.

= 1.1.47 =
* Restored the default pricetag position, with the optional first: pseudo selector
* Fixed the closing pricetag DIV bug

= 1.1.46 =
* Changed inclusion paths.
* More HPOS compatibility Changes.
* Fine tuning the pricetag placement.

= 1.1.45 =
* Added the Tax ID as a ViaBill account registration field.

= 1.1.44 =
* Changed the way the gateway redirection takes place in the checkout page.

= 1.1.42 =
* Conflict with 3rd party payment processors is treated differently (checkout hide is not the preferred approach).

= 1.1.41 =
* TBYB only refinement.

= 1.1.40 =
* HPOS (High-performance order storage) compatibility declaration.

= 1.1.39 =
* Improved shortcodes for in-place pricetag display.
* Added the :after modifier for the pricetag position.

= 1.1.38 =
* Added shortcodes for the various pricetags.

= 1.1.37 =
* Removed call to PriceTag configuration.
* Test and adjust for WP 6.4

= 1.1.36 =
* Added the missing name field in the Viabill registration request.

= 1.1.35 =
* Fixed the TBYB capture issue.

= 1.1.34 =
* Fixed the plugin activation issue.

= 1.1.33 =
* Hide "Pay in 30 Days" payment option in Spanish stores.

= 1.1.32 =
* Differentiate checkout page PriceTags
* New TBYB Logos

= 1.1.31 =
* Fixed the "total order" amount format
* Sanitized phone number for TBYB orders
* Updated the "forgot password" URL

= 1.1.30 =
* Try before you buy

== Changelog ==
= 1.1.25 =
* Sanitize phone number

= 1.1.24 =
* Improved cart info 

= 1.1.23 =
* Added cart info during checkout

= 1.1.22 =
* Changed the way transaction id is generated,
* Translation fixes

= 1.1.21 =
* Made logo language specific

= 1.1.20 =
* Changed pre-fill customer info encoding

= 1.1.19 =
* Added missing logo file

= 1.1.18 =
* Added Checkout Hide options, Order status after payment, new Logo image

= 1.1.17 =
* Changed Logo Size

= 1.1.16 =
* Changed Logo

= 1.1.15 =
* Minor improvements

= 1.1.14 =
* Support for pre-fill feature

= 1.1.13 =
* Support for older versions

= 1.1.12 =
* Minor improvements

= 1.1.11 =
* Handle conflicts with third party plugins that offer Viabill payment method

= 1.1.10 =
* Add customer info during checkout call
* Add platform info during notifications call
* Add support page with essential debug info
* Fixed bug with the PriceTags dynamic placement

= 1.1.9 =
* Changes to plugin support info.

= 1.1.8 =
* Edit payment gateway compatibility.
* Fix ViaBill status saving.
* Fix strings and spelling issues.
* Add translations.

= 1.1.7 =
* Fix status refresh error handling.
* Fix ViaBill PriceTags settings save.
* Add logger data checks.

= 1.1.6 =
* Fix signature validation.
* Fix ViaBill status being reset.
* Fix payment method icon display.
* Add status check to prevent order status rewrites.
* Move database update notice to a field in settings.

= 1.1.5 =
* Fix ViaBill PriceTag position input field.
* Fix script cache busting.

= 1.1.4 =
* Move ViaBill PriceTag settings to separate settings page.
* Add style input for PriceTags.
* Add jQuery selector position input for PriceTags.

= 1.1.3 =
* Fix excessive queries while checking for other payment gateways.
* Fix partial refunding issues.
* Fix order status handling on gateway callback.
* Add ViaBill status refresh button.

= 1.1.2 =
* Add Spanish translation.
* Add support for currency Euro.
* Add global admin notice if plugin is currently in test mode.
* Add more data to the log.

= 1.1.1 =
* Swap coalescing operator with ternary to keep backwards compatibility of PHP versions.

= 1.1.0 =
* Improved plugin's error logging.
* Fixed order cancelling.
* Add setting to hide all unapproved ViaBill orders.
* Remove use of custom order statuses and set new orders to use default WooCommerce statuses.
* Add setting to automatically refund orders through ViaBill when switching order status to "Refunded".
* Add warning if shop uses more than 2 decimal places.
* Switch order number and transaction ID.
* Add setting to disable on-hold email notifications.
* Add deactivation check for payment gateway with same identifier.

= 1.0.5 =
* Update callback to use 'woocommerce_api_' hook and accept both GET and POST requests.
* Use order ID as transaction ID instead of order key.

= 1.0.4 =
* Remove changelog.txt since we're using readme.txt for the changelog.
* Switch order status to 'on-hold' when starting the approval process.
* Add auto-capture option.
* Edit admin settings.

= 1.0.3 =
* Update code based on WordPress.org codding standards
* Fix missing ajax notice when errors happen
* Fix logger not working when enabled
* Fix textdomain loading too late

= 1.0.2 =
* Added check to deactivate the plugin if minimum required versions of WooCommerce and PHP are not met.

= 1.0.1 =
* Fix issues with decimals when capturing orders.

= 1.0.0 =
Initial stable release.

= 0.9.1 =
Beta release.

== Frequently Asked Questions ==

= Configuration =

An account is required to implement ViaBill. You can either log in to your ViaBill account or create one during the installation. Although ViaBill's plugin is free, additional transaction fees will apply, based on your country. For more information please visit www.viabill.com.

When all necessary information is entered, you will be redirected to the main settings of the plugin. The configuration is divided by sections, which helps to quickly find and manage settings of each plugin feature. Below you will find an explanation of the most important configurations:

1. "Enable" Allows you to enable and disable the ViaBill.
2. "ViaBill Test Mode" Allows you to use our playground for test transactions. This mode is for testing purposes only! Remember to disable this function for live web shops.
3. "Debug log" Allows you to generate a record of interactions while testing the integration.
4. "PriceTag" Allows you to enable or disable the PriceTag. There are three places where the PriceTag can be displayed, each of them can be turned separately on and off.

== New ViaBill orders ==

Once an order has been created, the order will get status "Pending payment". The order status will automatically change once the customer completes his purchase. New ViaBill orders will have the status "Approved by ViaBill" in your order list.

== How to capture a ViaBill order ==

After receiving your ViaBill order, the order can be captured by clicking on it and select "Capture". Afterward, you will be able to type in the amount you would capture. The order status will change to "Captured with ViaBill" once the order has been successfully captured.

== How to refund a ViaBill order ==

To refund an order, the order has to be captured in the first place. To do a refund, select the order and click the "Refund" button afterward. You will be able to type in the amount you would refund. The order status will change to "Refunded" once the order has been successfully refunded.

= Support =

Need any help or want to learn more about ViaBill? Feel free to [contact us](tech@viabill.com) if you need help to start implementing ViaBill into your store or if you have any further questions.
