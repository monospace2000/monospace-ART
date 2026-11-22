=== monospace View As ===
Contributors: monospace
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

See the site exactly as one user sees it - read-only. No emails, orders, comments or saved changes happen for them.

== Description ==

A red **View As** button in the admin toolbar (and on every row of the Users screen) shows the site as a chosen user sees it. Unlike "log in as" plugins you stay logged in as yourself: only who the page is drawn for changes, and everything that would act for them is refused with a browser alert.

* Read-only: no email is sent from anything, nothing is saved to the user's account, no checkout or payment, no comment, no password or profile change. Every request that is not a plain page view is refused unless it is on a short allow-list.
* With WooCommerce: you can add to the cart and apply coupons, in a throw-away cart that is deleted when you switch back; the customer's own cart is never touched.
* The toolbar turns red and holds only a Switch back button while you view; switching keeps the page you are on.
* Works on phones (the toolbar's swipe row) and in wp-admin.
* Settings > View As: who can use it, how long it lasts, the alert text, read-only AJAX actions to allow, where a start from an admin screen lands, how many people the pulldown lists, and which toolbar item the button sits next to.

Administrators (and anyone with the capability that lets you use View As) can never be viewed.

== Installation ==

Upload the folder to `/wp-content/plugins/` and activate it. Nothing else to set up.

== Changelog ==

= 1.1.0 =
* Settings screen; works without WooCommerce and without other plugins.

= 1.0.0 =
* First version.
