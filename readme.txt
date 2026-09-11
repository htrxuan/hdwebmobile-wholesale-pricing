=== HDWebmobile Wholesale Pricing ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, wholesale, b2b, wholesale pricing, trade pricing
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Role-based wholesale prices with a gated application form. The wholesale role is only ever granted by an admin approval.

== Description ==

HDWebmobile Wholesale Pricing gives your trade customers their own pricing. Set a wholesale price on each product's "Product data" panel, or a single store-wide percentage discount. Logged-in customers who hold the **Wholesale Customer** role automatically see wholesale prices everywhere -- shop, product page, cart and checkout. Everyone else sees your normal retail prices.

Customers ask for wholesale access through an application form (on the My Account dashboard, or the `[hdws_apply]` shortcode). You review applications on one screen and approve or reject each one.

= Why this plugin exists =
Wholesale and "lead capture" plugins have repeatedly turned their registration flow into a privilege-escalation hole. "WooCommerce Wholesale Lead Capture" (versions before 2.0.3.2) shipped CVE-2026-27542: an **unauthenticated privilege escalation to administrator** through the registration/lead flow, because role information was taken from the request.

This plugin makes that class of bug impossible by construction:

* **The application form never assigns a role.** Submitting it only inserts a row with status `pending` for the *current logged-in user*. `HDWS_Repository::create_application()` takes the applicant's user id as its first argument, and the only caller passes `get_current_user_id()`. No field, and no method anywhere in the plugin, accepts a user id, a role, or a capability from a request.
* **One approval path.** The wholesale role is added in exactly one private line of code, reached only through `HDWS_Repository::approve()`, which is called only from an admin handler that checks the `manage_woocommerce` capability **and** a nonce, and reads only an application id.
* **The role is harmless by design.** The Wholesale Customer role is registered with the single `read` capability -- a customer-equivalent. Even if it were somehow assigned by mistake, it cannot manage, edit, publish, or upload anything.
* **Pricing is gated on the verified role only.** The wholesale price swap checks `wp_get_current_user()` server-side. There is no cookie, query parameter, or form field that turns wholesale pricing on.

= Key Features =
* Per-product wholesale price, or a store-wide percentage discount as a fallback
* Wholesale prices apply consistently across shop, product, cart and checkout
* Customer application form on My Account and via `[hdws_apply]`
* One-screen review queue: Approve grants the role, Reject removes it
* The Wholesale Customer role is a plain customer-level role with no admin power

= Limitations (please read before installing) =
* One wholesale price per product (or the global percentage) -- no per-customer or per-quantity price tiers in this version
* Wholesale pricing follows the account, not a "wholesale mode" toggle -- a wholesale customer always sees wholesale prices while logged in
* Approval is manual; there is no automatic approval rule

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-wholesale-pricing` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Wholesale Pricing** to set a store-wide discount and review applications. Set per-product wholesale prices on each product's "Product data" panel.

== How to Use ==

= 1. Set wholesale prices =
On a product's "Product data" panel (Pricing section) enter a "Wholesale price", or set a store-wide percentage on the Wholesale Pricing tab to cover every product that has no specific wholesale price.

= 2. Customers apply =
A logged-in customer submits the application form on their My Account dashboard (or wherever you place `[hdws_apply]`).

= 3. You approve =
On **WooCommerce > HDWebmobile > Wholesale Pricing**, click Approve next to an application. That customer now holds the Wholesale Customer role and sees wholesale prices while logged in.

== Screenshots ==

1. The Wholesale Pricing tab: store-wide discount and the pending-applications queue.
2. The wholesale price field on the Product data panel.
3. The customer application form on My Account.

== Changelog ==

= 1.0.0 =
* Initial release: role-based wholesale pricing, per-product price or global percentage, a gated application/approval workflow where the wholesale role is granted only by an admin.
