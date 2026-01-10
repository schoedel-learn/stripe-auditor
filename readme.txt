=== Stripe Net Revenue Auditor ===
Contributors: (wordpress.org username)
Tags: woocommerce, stripe, fees, reporting, revenue, profit
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop guessing your profit. See Stripe fees and net revenue next to each WooCommerce order.

== Description ==
WooCommerce tells you what a customer paid. Stripe tells you what you *actually received* after fees. If you sell a lot, those fees add up—and reconciling them in spreadsheets is annoying.

Stripe Net Revenue Auditor adds a "Stripe Net" column to the WooCommerce orders list that shows:

* **Fee** - the Stripe processing fee for the transaction
* **Net** - the amount Stripe paid out to you for that transaction

This lets you quickly spot low-margin orders, reconcile deposits, and sanity-check profitability without leaving WordPress.

=== How it works ===
1. You enter your **Stripe Secret Key** on the plugin settings screen.
2. For each order in the WooCommerce admin list, the plugin reads the order’s stored payment transaction ID.
3. It fetches the related Stripe balance transaction to get the fee + net amounts.
4. Results are cached for performance.

=== Important notes / limitations ===
* Requires WooCommerce.
* This plugin only *displays* fee/net amounts in the admin; it does **not** change checkout totals.
* Accuracy depends on the payment data stored on the order by your payment gateway.
* Refunds, disputes, multiple captures, and multi-transaction orders may require additional logic (planned).
* The "Stripe Net" value is calculated per transaction (gross transaction amount minus Stripe fee), not per product line item.

=== Performance & caching ===
This plugin is designed to be lightweight, but fetching Stripe data can be slow if done for many orders at once.

To keep your WooCommerce Orders screen fast, the plugin uses:
* **Order meta caching** (fastest): once fee/net is known for an order, it’s stored on the order for quick reuse.
* **Transient caching**: fee/net is also cached by Stripe transaction ID to reduce repeat calls.

The settings page includes two cache controls:
* **Clear Stripe Net cache**: clears cached values for recent orders (safe default).
* **Clear ALL Stripe Net cache**: clears cached values for all orders (advanced; batched and may take time on large stores).

Tips:
* Use a persistent object cache (Redis/Memcached) if your site has a high order volume.
* If you change Stripe keys or notice stale values, go to **WooCommerce → Stripe Auditor** and click **Clear Stripe Net cache**.

=== Reporting ===
The plugin adds a basic totals screen at **WooCommerce → Stripe Net Revenue**.
This report uses cached order meta and does not call Stripe. It is intended for quick internal checks and filtering.

=== Free core vs Pro add-ons ===
This plugin is designed as a **free core** with optional **Pro add-ons**.

**Support (free + paid)**
If you need help with setup, troubleshooting, or want to suggest an improvement, support is available here:
https://schoedel.design/shop/

TODO: Replace the URL above with the real support ticket system URL once it’s finalized.

**Free (this plugin)**
* Adds the "Stripe Net" column to WooCommerce order lists.
* Fetches and caches Stripe fee/net per transaction.
* Basic settings screen for entering a Stripe key.

**Planned Pro add-ons (separate plugins, not bundled)**
* Automated background syncing of fees/net into order meta (faster lists, better reporting).
* Support for refunds/disputes and more complex Stripe flows.
* Deeper reporting dashboards (net revenue summaries, date ranges, export).
* Support for additional e-commerce platforms.

**How we’ll distinguish Free vs Pro**
* Pro features will ship as **separate add-on plugins** that depend on the free core.
* The free core will not include paywalls or hidden functionality; it remains fully usable.
* Pro add-ons will add their own admin screens and clearly label features as Pro.

This plugin is developed by Schoedel Design AI.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/stripe-auditor/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **WooCommerce → Stripe Auditor** and enter your Stripe Secret Key.
4. Go to **WooCommerce → Orders** and look for the **Stripe Net** column.

== Frequently Asked Questions ==

= How do I test this plugin safely? =
Use a staging site and a Stripe test key. Then check a few orders in **WooCommerce → Orders** and confirm the "Stripe Net" column shows Fee/Net.

= Does this require WooCommerce? =
Yes.

= Does this change my checkout totals? =
No. It only adds admin reporting information.

= Where is the Stripe Secret Key stored? =
In the WordPress options table.

= What Stripe key should I use? =
Use a key that has permission to read the relevant payment objects for your Stripe account. For best security, consider a restricted key with read-only permissions where available.

= Why do some orders show "No Stripe ID" or "N/A"? =
If the order doesn’t have a stored Stripe transaction ID, or if Stripe can’t be queried for that transaction, the plugin cannot calculate fee/net for that row.

== Screenshots ==
1. WooCommerce orders list showing the "Stripe Net" column (fee + net).
2. Settings page under WooCommerce.

== Changelog ==

= 1.0.0 =
* Initial release: display Stripe fee and net revenue as an orders list column in WooCommerce.
