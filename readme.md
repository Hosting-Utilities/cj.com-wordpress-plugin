=== Tracking Code for cj.com (on WooCommerce checkout) ===
Contributors: russell578
Tags: affiliate marketing, affiliate, advertising, CJ, Commision Junction
Requires at least: 4.9.9
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: trunk
License: Specialized License
License URI: https://wp-overwatch.com/legal/cj-tracking-code-license.txt

Installs the tracking code for cj.com

== Description ==
CJ Affiliate (previously Commision Junction) is an affiliate service that can bring money and customers to your website. Learn more at [cj.com](cj.com).

This plugin sends the necessary data to participate in the CJ Affiliate network. Tracks WooCommerce activity and successful Gravity Forms submission.

*WooCommerce or Gravity Forms is required,* if you want the tracking code added to a different plugin, shoot me an email at ([russell@wp-overwatch.com](mailto:russell@wp-overwatch.com)).

== Installation ==

1) Add the plugin through the 'Plugins' menu in WordPress

2) When prompted, activate the plugin

3) Go to *Settings > CJ Tracking Code*

4) Enter in your account info

5) The tracking data will then be sent for WooCommerce customers, and on successful Gravity Form submissions.
Supports [WooCommerce 3.0+](https://wordpress.org/plugins/woocommerce/#installation), and [Gravity Forms 2.4+](https://www.gravityforms.com/)

<br/>

**Extending the Plugin**

The following filter is available for extending the plugin

`cj_settings`

This filter allows you to conditionally determine what the CID, type, and tag ID should be. This is useful when you have want to use multiple tag IDs and types.

Basic Example:

`
add_filter('cj_settings', function($account_data, $submission_method, $order){

    // make changes to $account_data here

    return $account_data;
}, 10, 3);

`

Example 2:
`
add_filter('cj_settings', function($settings, $submission_method, $order_or_form_data, $order_or_form_id){

    // make changes to the settings here
    // Note: return false if you don't want to send any data to CJ for this transaction

    return $settings;
}, 10, 4);

`

The first parameter is an array containing all of the settings to filter.
More settings may be added later, but the array currently holds:

* 'enterprise_id' - the enterprise ID
* 'action_tracker_id' - the action tracker id
* 'tag_id' - no longer used
* 'cid' - no longer used
* 'type' - no longer used
* 'notate_urls' - no longer used
* 'notate_order_data' - should we add additional info to order notes (on supported integrations)
* 'other_params' - Not currently used. If ever used again it will contain additional items to submit to CJ. These will appear in the CJ dashboard.
* 'storage_mechanism' - Either 'woo_session' or 'cookies'. Both options work. There is normally no need to change this.
<!--* 'limit_gravity_forms' - When true enables the next setting
* 'enabled_gravity_forms' - when limit_gravity_forms is true, CJ will only be active for forms IDs in this array. Otherwise it is active on all payment forms.
* 'blank_field_handling' - can be set to either 'ignore_blank_fields', 'ignore_0_dollar_items', or 'report_all_fields' to determine if those type of fields should be reported to CJ. The default is to report all items.-->

The second parameter will be either "woocommerce" or "gravity_forms" depending on which one initiated everything (more integrations will be added later as they're requested).

Based on the above parameter the next 2 will either be the form data and form id or the order data and order id.


If you need me to add additional filters, or if you have any questions, shoot me an email at russell@wp-overwatch.com.

== Screenshots ==

1. The settings page. Once filled out, the cj.com tracking code will be added to the thank you page of each order placed.

== Changelog ==

= version 3.3 =
- Add server side cookie implementation
- Allow you to choose which implementation you would like to use. See the settings page for more info on the different implementations.
- WordPress 6.0 compatibility

= version 3.2 =
Add tag ID

= version 3.1 =
Fix a problem in 3.0 that was breaking all Ajax requests until the cj settings were saved

= version 3.0 =
THIS CHANGE BREAKS BACKWARDS COMPATIBILITY.
CJ has new documentation they have been handing out that is completely different from what they have online.
This update follows the new documentation, introduces new fields that will need to be filled out on the settings page, and is an all around overhaul of how the tracking code is implemented. This version has been verified with the lead client integration engineer at CJ to be in compliance with their systems. All users will need to recertify themselves with the new system.

= version 2.12 =
Allow Google Tag Manager and other scripts to read the cjevent cookie (HttpOnly=false)
Always use non-www version of domain in cookies
Remove extraneous CSS in the settings page
Remove "Did not recieve a PublisherID" notice from WooCommerce notes. CJ no longer seems to use a publisher ID (but if you do still have a setup that uses them, they will still be recorded).
On the settings page add inline CSS to make sure the summary elements dropdowns arrows always appear.
Add method=IMG to the conversion tag URL. Let me know if CJ is asking you to use a different tracking method and I can add support for it.

= version 2.11 =
Make the cookie storage mechanism work with caching plugins
And make it the default storage mechanism
Add a setting to change the duration of the cjevent cookie
Add a settings link on the plugin page
The contact form now outputs some of the debug info into a nice table instead of using var_dumps
Display a warning when WPFC_CACHE_QUERYSTRING is true (from the fastest cache plugin), and makes it easier to add compatibility features/warnings in the future
Minor textual changes to the settings page

= version 2.10 =
Puts the plugin in conformance with CJ's new rules on what characters are allowed in item names
In WordPress 5.5+ the plugin will only send data to CJ in production environments (the environment is determined by the wp_get_environment_type function)
Gravity Form Integration fixes:
 * The setting to limit which forms are used wasn't saving
 * It was sending a CJ Event of 1 instead of the actual CJ Event
 * Decimal numbers were being ignored when calculating the coupon discount
 * It wasn't telling you which checkbox/radio button were selected before

= version 2.9 =
More bug fixes for the Gravity Forms integration
The gravity Forms integration should no longer be using the form ID for the item name. Previously it was used on unfilled out fields and radio fields.

= version 2.8 =
Gravity Forms integration changes:
* Stop reporting fields that were not filled in
* Add support for the Gravity Forms coupon add-on
* Fixes some items that were not getting reported correctly
* Add a note to form entries of the CJ URL used

WooCommerce Integration changes:
* Compatibility with WooCommerce product bundles plugin

Settings page changes:
 * Hide WooCommerce or Gravity Form options when the plugin is not enabled
 * Send additional information when submitting a ticket (plugin versions, multisite info)
 * Replace some checkboxes with toggle switches
 * Add "Remove Plugin Data" button
 * Other misc improvements

= version 2.7 =
Add fix to trigger WooCommerce Sessions on non-WooCommerce pages (for storing the publisherCID and cjevent).
Change the duration of cookies in the Gravity Forms integration to 120 days.
When the cookie storage mechanism was used, the publishercid was not getting retreived.

= version 2.6 =
Add cj_account_info filter, for cases where the account info needs to change depending on what is being purchased
= version 2.5 =
Add contact us form and add mention of Hosting Utilities (my new suite of tools for managing WordPress sites).
= version 2.4 =
Makes cookies last for 120 days when using cookie storage instead of the default storage mechanism (CJ requires the cookies to last for 120 days).
= version 2.3 =
Fixes a security issue where the cjevent was not being properly escaped.
Also, this update adds an alternate storage mechanism to aid in debugging problems.
= version 2.2 =
Allow CJ tracking codes to be turned off for gravity forms or WooCommerce. Previously the tracking code was always added if the associated plugin was enabled.
Also fixes sending additional query parameter to CJ. I don't think that feature was ever actually working.
Fixing unit tests
= version 2.1 =
Fixing a fatal bug. The endswith function was never defined causing issues.
= version 2.0 =
Adding Gravity Forms support
= version 1.4.0 =
The item name is now set to SKU code if present, otherwise, the product title is used
Grabbing the cjevent is now a case-insensitive process
= version 1.3.0 =
Added support for additional currencies. Thanks to [kennyhunter16](https://wordpress.org/support/users/kennyhunter16/), the currency is now detected from the order that was placed.
= version 1.2.0 =
cj.com added some new required fields. This update brings the plugin into conformation with their documentation at https://developers.cj.com/docs/tracking-integration/advanced-integration
= version 1.1.0 =
Orders that originated from cj.com are now marked as such in the order notes
= version 1.0.0 =
Initial commit

== Upgrade Notice ==

= 3.3 =
Uses the S2S implementation to talk with CJ. If you were experiencing problems with 404 errors, this update will fix that. If you were previously using a 3.x version of this plugin and weren't experiencing any problems, you're able to use the superior proxy implementation. After installing the update go to the settings page and switch the implementation to "proxy" to continue using that implementation.
