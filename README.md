# HDWebmobile Wholesale Pricing

Role-based wholesale prices for WooCommerce, with a gated application form. The wholesale role is only ever granted by an admin approving an application — never by any request.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-wholesale-pricing/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Set a wholesale price per product, or a single store-wide percentage discount. Logged-in customers who hold the **Wholesale Customer** role automatically see wholesale prices across shop, product, cart and checkout. Customers request access through an application form; you approve or reject each one on a single screen.

## Why this plugin exists

Wholesale / lead-capture plugins keep turning their registration flow into a privilege-escalation hole. "WooCommerce Wholesale Lead Capture" (< 2.0.3.2) shipped CVE-2026-27542 — unauthenticated privilege escalation to administrator, because role information was taken from the request.

This plugin makes that impossible by construction:

* **The application form never assigns a role** — it only inserts a `pending` row for `get_current_user_id()`. No field or method anywhere accepts a user id, role, or capability from a request.
* **One approval path** — the wholesale role is added in a single private line reached only through `HDWS_Repository::approve()`, called only from an admin handler behind `manage_woocommerce` + a nonce, taking only an application id.
* **The role is harmless by design** — registered with only the `read` capability.
* **Pricing is gated on the verified role only** — `wp_get_current_user()` checked server-side; no cookie / param / field turns it on.

## Features

* Per-product wholesale price, or a store-wide percentage fallback
* Consistent across shop, product, cart and checkout
* Application form on My Account and via `[hdws_apply]`
* One-screen Approve / Reject queue
* Wholesale Customer role has no admin power

## Limitations

* One wholesale price per product (or the global percentage) — no per-customer or per-quantity tiers
* Pricing follows the account, not a toggle
* Approval is manual

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-wholesale-pricing`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Wholesale Pricing**.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
