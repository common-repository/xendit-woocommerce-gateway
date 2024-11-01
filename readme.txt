=== Plugin Name ===
Contributors: Xendit
Donate link: #
Tags: xendit, woocommerce, payment, gateway
Requires at least: 3.0.1
Tested up to: 5.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Xendit Payment Gateway for Woocommerce

== Description ==

This plugin will not be updated in the future and will be <strong>deprecated</strong> soon. Please update or install <a href='https://wordpress.org/plugins/woo-xendit-virtual-accounts/'>WooCommerce - Xendit</a> plugin to accept single transaction and subscription with Credit Cards and Online Debit Card without breaking your current payment flow.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.5.3 =
* Fix public key

= 1.5.2 =
* Add uninstall script to clean up cache data

= 1.5.1 =
* Add deprecated notice for upcoming plugin merge with Xendit Virtual Accounts

= 1.5.0 =
* New feature: Enable merchant to change payment description in checkout page

= 1.4.1 =
* Remove tokenization feature on end user account
* Remove unnecessary echo on payment script

= 1.4.0 =
* New feature: Enable merchant to change payment method name

= 1.3.14 =
* Record store name in API request

= 1.3.13 =
* Add failure insight for processor error

= 1.3.12 =
* Add failure reason insight

= 1.3.11 =
* Fix faulty amount validation

= 1.3.10 =
* Fix js error for unupdated JS file

= 1.3.9 =
* Fix refund feature

= 1.3.8 =
* Automatically handle 3DS requirement depending on account permission

= 1.3.7 =
* Add alert when changing API key

= 1.3.6 =
* Improve logging

= 1.3.5 =
* Remove descriptor field for now

= 1.3.4 =
* Change creds field to password

= 1.3.3 =
* Remove title and description form

= 1.3.2 =
* Subscription feature bugfix. Unable to charge consequent order

= 1.3.1 =
* Implement fixes for CVN issue

= 1.3.0 =
* Implement redirection for 3DS functionality
* Fix minor logo issue

= 1.2.3 =
* Update plugin description

= 1.2.2 =
* Improve performance to bring better experience in paying through credit cards

= 1.2.1 =
* Improve error handling

= 1.0 =
* Initial version.

== Upgrade Notice ==

= 1.0 =
Initial version.
