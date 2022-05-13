Hello, this is an unofficial <a href="wordpress.org/plugins/code-for-cj-affiliate-network/">cj.com affiliate network plugin</a> for use in making your WordPress site integrate with CJ's system.

I have open sourced this plugin and left it to you, the community, to help each other resolve problems with the plugin. But I will be happy to explain where in the plugin you will need to make changes and any other questions about the code. Just open up an issue.

Making changes to this plugin
---

While I am no longer making changes to this plugin myself, I will respond quickly to any issues and pull requests, so feel free to reach out to me. If you are not a developer yourself, you will need to hire one as I won't make any changes to the plugin for you. Once there are an outstanding amount of changes to the plugin I will update the plugin in the WordPress repository to incorporate the changes.

WooCommerce Integration
----
The code for this integration is located in woocommerce/wc_tags.php. You can ignore the other files in the woocommerce folder, I need to clean those up. Within that file there is a CJPluginWooTag class. There are 2 different functions in there for calculating the total. The `getCartSubtotal` and `getOrderSubtotal` functions. `getCartSubtotal` is for the site page tag that is sent to CJ on each page load, while getOrderSubtotal is the exact same thing but for when an order has been placed. They calculate the totals using completely different WooCommerce functions (using the current cart contents vs using the order that was just made). The `getDiscount` and the `getItems` functions operate differently. Instead of being split up into seperate functions, with those, the `isThankYouPage` function is used to tell you if you need to calculate from the cart or from the order. `isThankYouPage` will return true if we're dealing with the order page and false if it is some other other page on the site.


Gravity Forms Integration
----
The Gravity Forms integration operates in the same way as WooCommerce one, but you will need to change gravity-forms/gravity-forms.php. Since Gravity Form pages are for the most part single pages, we only worry about sending the data when the form has been submitted (`$this->isThankYouPage()` is true).
