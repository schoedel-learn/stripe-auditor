# Stripe Net Revenue Auditor — Add-ons / Pro Integration Contract

This document describes how **separate add-on plugins** (including a paid “Pro” add-on) should integrate with the free
core plugin **Stripe Net Revenue Auditor**.

The goals:

- Keep the **free core** fully functional and WP.org-safe.
- Allow add-ons to extend behavior without forking core files.
- Provide a stable set of **hooks, filters, and helpers**.

> Note: All contracts below are considered **public** once released. Add new hooks freely, but try not to remove or
> change existing ones.

---

## 0) Free vs Paid separation (recommended)

WordPress.org reviewers generally expect the **free plugin** to remain fully usable and not behave like “nagware”.

Recommended approach:

- **Free core** stays on WordPress.org.
- **Pro features ship as separate add-on plugins** (not bundled, not hidden behind paywalls).
- Free core exposes stable hooks/filters (documented below) so add-ons can extend behavior.

### 0.1 What belongs in Free

Keep these in the free core:

- Orders list column display
- Caching to order meta + transients
- Basic reporting that uses cached data
- Manual cache tools (clear/warm) and diagnostics

### 0.2 What belongs in Pro / paid add-ons

Put the heavier/high-value features here:

- Background syncing/queues
- Deeper reports + exports
- Handling complex Stripe flows (refunds/disputes/multi-capture)
- “No Stripe calls on list screens” mode (pre-sync required)

### 0.3 WP.org-safe UI/marketing guidelines

In the free core:

- ✅ It’s okay to include a single **“Pro / Add-ons”** link.
- ✅ It’s okay to mention paid add-ons in the readme/settings page **briefly**.
- ❌ Avoid popups, repeated dismissible nags, or locking UI behind “Upgrade to Pro”.
- ❌ Don’t block core features if Pro isn’t installed.

---

## 1) How add-ons should detect the free core

### 1.1 Constants

The free core defines:

- `SNRFA_VERSION` (string)
- `SNRFA_PLUGIN_FILE` (absolute path)

Add-ons should check:

- `defined('SNRFA_VERSION')`

### 1.2 Helper functions

The free core provides:

- `snrfa_is_pro_active(): bool`
- `snrfa_get_pro_version(): string`
- `snrfa_get_support_url(): string`

Add-ons can safely call these functions **after** the core plugin file has loaded.

### 1.3 Recommended pattern (bootstrap)

In your add-on main plugin file:

- Bail early if the core isn’t active.
- Optionally display an admin notice.

Example:

```php
if (!defined('ABSPATH')) { exit; }

add_action('plugins_loaded', function () {
    if (!defined('SNRFA_VERSION')) {
        // Core not active.
        return;
    }

    // Add-on bootstrap.
});
```

---

## 2) Declaring a Pro add-on

A Pro add-on should define at least one of:

- `define('SNRFA_PRO_VERSION', 'x.y.z');`
- Provide a class: `Stripe_Net_Revenue_Pro\Bootstrap`

This enables:

- `snrfa_is_pro_active()` to return `true`
- `snrfa_get_pro_version()` to return the version

---

## 3) Public hooks and filters

### 3.1 Action: `snrfa_core_loaded`

**When it fires:** Immediately after the core constants and Core API helpers are defined.

**Use it for:** Registering filters early.

```php
add_action('snrfa_core_loaded', function () {
    // Register filters.
});
```

### 3.2 Filter: `snrfa_stripe_call_allowed`

**Signature:**

```php
apply_filters('snrfa_stripe_call_allowed', bool $allowed, array $context): bool
```

**Purpose:** Allow add-ons to prevent Stripe API calls in certain contexts (e.g., on large order list screens).

**Contexts currently used by core:**

- `source: orders_list` (Abstract_Integration)
- `source: orders_list_legacy` (legacy Columns renderer)
- `source: admin_cache_warm` (Admin warm-cache batcher)

The context array may include:

- `txn_id` (string)

Example: disable Stripe calls on orders list and rely on background syncing:

```php
add_filter('snrfa_stripe_call_allowed', function ($allowed, $context) {
    if (!is_array($context)) {
        return $allowed;
    }

    if (!empty($context['source']) && in_array($context['source'], array('orders_list', 'orders_list_legacy'), true)) {
        return false;
    }

    return $allowed;
}, 10, 2);
```

### 3.3 Action: `snrfa_after_txn_cached`

**Signature:**

```php
do_action('snrfa_after_txn_cached', int $order_id, array $txn_data)
```

**When it fires:** After fee/net data is cached (order meta and/or transient) by the core.

**Use it for:**

- Maintaining your own reporting tables
- Triggering exports
- Recording analytics

**Shape of `$txn_data`:**

```php
array(
  'fee'      => int|float|string, // Stripe fee amount in minor units (usually int)
  'net'      => int|float|string,
  'currency' => string,
  'txn_id'   => string,
  'updated'  => int,              // unix timestamp
)
```

Notes:

- Treat `fee/net` as **Stripe minor units** unless you explicitly convert.
- Core only fires this action when it has a valid Stripe response.

---

## 4) Data storage contracts

### 4.1 Order meta key

The core stores cached Stripe fee/net on WC orders using:

- `_snrfa_stripe_net_revenue`

### 4.2 Transient key prefix

The core uses a transient per Stripe transaction id:

- `snrfa_txn_{txn_id}`

---

## 5) Versioning and compatibility

Add-ons should:

- Require at least a minimum core version, e.g.:

```php
if (defined('SNRFA_VERSION') && version_compare(SNRFA_VERSION, '1.0.0', '<')) {
    // Incompatible.
}
```

- Avoid calling internal classes directly unless you’re prepared to track changes.
- Prefer the hooks/filters above.

---

## 6) What should be “Pro” (recommended)

To keep the free core fast and WP.org-friendly, Pro add-ons are a good fit for:

- Background syncing (scheduled warm-cache / queue workers)
- Stripe refunds/disputes/multi-capture handling
- Advanced reporting dashboards (aggregation + export)
- Multi-store / multi-account support

The free core should remain:

- Orders list column + basic caching
- Basic report using cached data
- Manual cache management controls

---

## 7) Support link

The core support URL is filterable:

- `snrfa_support_url`

and defaults to:

- https://help.opshub.app/

---

## 8) Minimal Pro add-on scaffold (example)

This is a tiny “shape” of a Pro add-on that:

- defines `SNRFA_PRO_VERSION`
- hooks into caching events
- optionally disables Stripe calls on the Orders list

```php
<?php
/**
 * Plugin Name: Stripe Net Revenue Auditor Pro
 * Description: Pro add-on for Stripe Net Revenue Auditor.
 * Version: 0.1.0
 */

defined('ABSPATH') || exit;

define('SNRFA_PRO_VERSION', '0.1.0');

add_action('plugins_loaded', function () {
    if (!defined('SNRFA_VERSION')) {
        // Core not active.
        return;
    }

    // Example: disable Stripe calls on list screens.
    add_filter('snrfa_stripe_call_allowed', function ($allowed, $context) {
        $source = is_array($context) && isset($context['source']) ? (string)$context['source'] : '';
        if (in_array($source, array('orders_list', 'orders_list_legacy'), true)) {
            return false;
        }
        return $allowed;
    }, 10, 2);

    // Example: react after a transaction is cached.
    add_action('snrfa_after_txn_cached', function ($order_id, $txn_data) {
        // Write to a custom table, enqueue export, etc.
    }, 10, 2);
});
```

---

## 9) Pro roadmap (add-on modules)

This section is a practical, product-oriented roadmap of **separate Pro add-ons** that build on the free core.

These are based on recurring Stripe + WooCommerce pain points:

- "My Stripe payouts don’t match my WooCommerce orders"
- "Refunds/disputes/chargebacks make my net numbers wrong"
- "Don’t slow down the Orders screen"
- "I need exports for accounting"

Each module below is designed to plug into the Core API hooks/filters documented above.

> Tip: Keep Pro features in **separate plugins** so the WP.org free core stays simple and fast.

### P0 (first paid releases)

#### P0.1 Background Sync + Zero-Stripe-Calls Mode

**User problem:** High-volume stores can’t afford Stripe API calls during admin list loads.

**What it does:**

- Scheduled background sync that backfills fee/net for orders.
- Option to disable Stripe calls on order list screens entirely.
- Sync status + queue health page.

**Core API integration:**

- Filter `snrfa_stripe_call_allowed`:
    - return `false` for `source: orders_list` and `source: orders_list_legacy`
- Action `snrfa_after_txn_cached`:
    - maintain a Pro table/index for reporting and quick lookups

**Data model notes:**

- Can keep using core order meta `_snrfa_stripe_net_revenue`.
- Optionally add a custom table for faster date-range reporting at scale.

---

#### P0.2 Payout Reconciliation (Orders ⇄ Stripe Payouts)

**User problem:** Stripe payouts rarely equal the sum of Woo orders for a given period.

**What it does:**

- List Stripe payouts.
- For a selected payout, show:
    - included balance transactions
    - linked WooCommerce orders
    - fees, net, adjustments
- Export reconciliation results.

**Core API integration:**

- Action `snrfa_after_txn_cached`:
    - record `order_id -> txn_id` mapping in Pro storage
- Optional: run background Stripe sync to fetch `payout_id` for each balance transaction.

**Data model notes:**

- Pro table suggestion: `wp_snrfa_pro_ledger` with columns like:
    - `order_id`, `txn_id`, `currency`, `fee`, `net`, `payout_id`, `type`, `created`, `updated`

---

#### P0.3 Refunds + Disputes Adjustments

**User problem:** Refunds and disputes create additional Stripe transactions; simple fee/net per charge isn’t enough.

**What it does:**

- Retrieve and store refunds/disputes for a charge/payment intent.
- Represent adjustments as separate ledger rows.
- Show an “Order Stripe Ledger” admin box.

**Core API integration:**

- Action `snrfa_after_txn_cached`:
    - enqueue background lookup for refunds/disputes
- Filter `snrfa_stripe_call_allowed`:
    - keep list views fast; do deep Stripe calls in background jobs

**Data model notes:**

- Ledger table should support rows like:
    - `charge`, `refund`, `dispute`, `dispute_fee`

---

#### P0.4 Exports (CSV) for Accounting

**User problem:** Merchants need to move net/fees into QuickBooks/Xero/spreadsheets.

**What it does:**

- CSV export by date range and status.
- Export either:
    - per-order
    - per-transaction
    - per-payout reconciliation

**Core API integration:**

- Uses cached meta and/or Pro table. No extra Stripe calls needed once synced.

---

### P1 (second wave)

#### P1.1 Multi-transaction orders (multi-capture / split tender)

**User problem:** Some orders have more than one Stripe transaction.

**What it does:**

- Sum multiple charges/captures per order.
- Display itemized capture list.

**Core API integration:**

- Action `snrfa_after_txn_cached` for ingest
- Background recon to discover additional Stripe objects per order

---

#### P1.2 FX + settlement currency reporting

**User problem:** Multi-currency stores want net totals in settlement currency and want to see FX fees.

**What it does:**

- Store additional Stripe balance transaction fields where applicable.
- Report effective FX impacts.

---

#### P1.3 Woo Subscriptions net analytics

**User problem:** Subscription businesses need net MRR after Stripe fees.

**What it does:**

- Renewal net revenue reports.
- Cohort and churn-aware summaries.

---

### P2 (advanced / enterprise)

#### P2.1 Stripe Connect / Multi-account

**User problem:** Platforms/marketplaces need to reconcile across connected accounts.

**What it does:**

- Per-account mapping, per-payout reconciliation, and role-based access.

---

## 10) Roadmap guardrails

To keep the free core WP.org-friendly:

- Prefer adding new Pro behavior via **separate plugins** + the Core API hooks.
- Avoid adding heavy tables or background jobs to the free core.
- Keep any "Pro" messaging minimal and non-disruptive.
