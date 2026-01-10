# Local Testing Guide (Stripe Net Revenue Auditor)

This guide describes a practical local testing setup for the free core plugin.

It’s designed to keep testing **fast** (smoke tests) while still validating the most important behavior in a **real
WordPress + WooCommerce** environment.

---

## 1) Quick local smoke tests (CLI)

These tests run without a WordPress install. They catch syntax errors and basic boot regressions.

Run from the plugin root:

```bash
php test_activation.php
php test_settings_without_wc.php
php test_stats.php

# PHP syntax lint (excludes vendor/)
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n 1 php -l
```

What these cover:

- Plugin file loads without fatal errors.
- Core classes are available (when vendor/ exists).
- Basic settings/menu hooks register.
- Stats math (median/approx median) stays correct.

Limitations:

- These tests do not render real WP admin screens.
- They do not talk to Stripe.

---

## 2) Recommended real-environment testing (macOS)

### Option A (recommended): LocalWP

LocalWP is the fastest path to a realistic WordPress + WooCommerce admin where you can click around.

For a full step-by-step LocalWP environment setup, see:

- `docs/LOCALWP_SETUP.md`

Checklist:

1. Create a new WordPress site in LocalWP.
2. Install and activate WooCommerce.
3. Install and activate **Stripe Net Revenue Auditor**:
    - Use the plugin folder (this repo) as a local plugin.
4. In WP Admin, go to:
    - **WooCommerce → Stripe Auditor**
5. Enter a **Stripe test secret key** (recommended) or live key (be careful):
    - `sk_test_...` (test)
6. Confirm the Diagnostics box shows:
    - WooCommerce active
    - Dependencies loaded
    - Stripe key saved

---

## 3) Test cases to run in WP Admin

If you are collaborating with other engineers/QA, the coverage checklist lives in:

- `docs/QA_TEST_PLAN.md`

### 3.1 Orders list column (primary feature)

1. Go to **WooCommerce → Orders**.
2. Verify the **Stripe Net** column is present next to the gross total column.
3. Open several orders and confirm the column displays:
    - Fee
    - Net

Expected behavior:

- If transaction id is missing: shows **“No Stripe ID”**.
- If Stripe fetch/cached data is missing/unavailable: shows **“N/A”**.

### 3.2 Caching behavior (performance)

The plugin uses:

- **Order meta** (`_snrfa_stripe_net_revenue`) as the fast cache
- **Transients** (`snrfa_txn_{txn_id}`) as a secondary cache

Manual cache controls are on the settings screen:

- **Clear Stripe Net cache** (recent orders)
- **Clear ALL Stripe Net cache** (batched, confirmation required)
- **Warm Stripe Net cache** (batched)
- **Start/Stop background warm cache** (WP-Cron)

Test:

1. Load Orders screen once (this should populate cache for visible orders).
2. Reload Orders screen.
3. It should be noticeably faster and should not re-fetch everything.

### 3.3 Warm cache (manual)

1. Go to **WooCommerce → Stripe Auditor**.
2. Click **Warm Stripe Net cache**.
3. Watch that the action progresses in batches (may redirect).

Expected:

- It should not time out on large stores because it batches.

### 3.4 Background warm cache (WP-Cron)

1. Click **Start background warm cache**.
2. Ensure traffic hits the site (WP-Cron needs visits) or use a cron runner.
3. Verify it continues to process batches.
4. Click **Stop background warm cache**.

Expected:

- Stop clears the cursor / unschedules the cron.

### 3.5 Report page (aggregation)

1. Go to **WooCommerce → Stripe Net Revenue**.
2. Run filters:
    - date range
    - status
    - transaction
    - group by day/week/month/year

Expected:

- Uses cached order meta.
- Does not call Stripe.

---

## 4) Stripe testing notes

### 4.1 Recommended approach

- Use a Stripe **test** key on a staging/local site.
- Place a few test orders via a Stripe-enabled gateway so WooCommerce stores transaction ids.

### 4.2 Common gotcha

Some gateways store a PaymentIntent id (`pi_...`) instead of a Charge id (`ch_...`).

The plugin attempts to resolve `pi_...` → latest charge balance transaction.

---

## 5) Performance / scale testing (optional)

If you’re testing on a larger dataset:

- Enable an object cache (Redis/Memcached) if available.
- Prefer the background warm cache feature to avoid admin slowdowns.
- Consider disabling Stripe calls on list screens (this is planned for Pro via the Core API filter
  `snrfa_stripe_call_allowed`).

---

## 6) Troubleshooting

### Vendor/dependency errors

If you see an admin notice about missing dependencies:

- Confirm the plugin release contains `vendor/`.

### WooCommerce missing

If WooCommerce is not installed/active:

- The plugin will display a notice and won’t register WooCommerce screens.

---

## 7) Support

Support portal:

- https://help.opshub.app/

---

## 8) UI/UX conformance checklist (flat, minimal, modern)

Before each release, do a quick UI pass to ensure we stay consistent with the project’s design principles.

Reference:

- `docs/UI_UX.md`

Checklist:

- Settings screen uses standard WP admin components (`wrap`, `wp-heading-inline`, `page-title-action`, `form-table`).
- Buttons/links are minimal and don’t feel like a custom app.
- Destructive actions require confirmation and use clear wording.
- Orders list column stays compact (2-line Fee/Net) and uses muted styling for “No Stripe ID” / “N/A”.
- No admin screen triggers excessive Stripe calls (verify cache behavior; use warm cache tools).
- Pro/Add-on mentions are present but non-disruptive (no nagware patterns).
