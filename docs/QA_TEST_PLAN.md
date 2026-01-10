# QA Test Plan (What to test)

This document is for QA/engineering validation of **Stripe Net Revenue Auditor (free core)**.

It focuses on **what to test** (coverage) rather than prescribing how to test.

---

## Using this plan in GitHub Projects (recommended workflow)

If you want a remote engineer to run QA and **comment on progress/issues**, track QA using a GitHub Project with Issues.

### Suggested setup

1. Create a new **GitHub Project** (Projects v2) for this repo.
2. Create a label set in the repo (optional but helpful):
    - `qa`
    - `hpos`
    - `gateway-woopayments`
    - `gateway-stripe`
    - `performance`
    - `security`
3. Create one Issue per test area (see “Project items” below).
4. Add all Issues to the GitHub Project.
5. Assign the engineer and have them comment results directly on each Issue.

Why Issues (not only draft items):

- Issues support threaded comments + history.
- Easy to tag failures and link PRs.

### Project items (copy/paste)

Create these as GitHub Issues and add them to your Project (each one is a “what to test” bucket).

1. QA: Installation / Activation / Deactivation
2. QA: Settings page (WooCommerce → Stripe Auditor)
3. QA: Permissions + nonces (admin-post actions)
4. QA: Orders list column placement + formatting (legacy + HPOS)
5. QA: Orders list behavior by order type (paid / missing / unresolvable)
6. QA: PaymentIntent vs Charge IDs (pi_ vs ch_)
7. QA: Caching correctness + performance (meta + transients)
8. QA: Cache clear actions (recent + clear all batched)
9. QA: Warm cache actions (manual + background cron)
10. QA: Report screen filters + grouping + no Stripe calls
11. QA: UX/UI consistency (WP-native)
12. QA: Error handling resilience (missing key, Stripe errors)

### Issue template (copy/paste)

Use this as the body for each QA Issue:

**Environment**

- WP version:
- WC version:
- PHP version:
- Orders mode: legacy / HPOS
- Gateway: WooPayments / Stripe for WooCommerce

**Checklist**

- [ ] …

**Notes / Issues found**

- …

**Evidence (optional)**

- Screenshots / logs / video links

---

## Scope

### In scope (free core)

- WooCommerce admin Orders list column: **Stripe Net**
- Stripe transaction lookup + caching behavior
- Settings screen under WooCommerce
- Cache controls (clear/warm + background warm)
- Admin report screen (aggregations + filters)
- Safety + performance behaviors (no excessive Stripe calls)

### Out of scope (planned Pro)

- Payout reconciliation
- Refund/dispute ledger
- Background sync / queue workers beyond current warm-cache
- Accounting exports beyond the current report

---

## Test environments

Test at least:

- WordPress latest stable
- WooCommerce latest stable
- PHP 8.0+ (8.1/8.2 preferred)
- Two WooCommerce modes:
    - Legacy orders table
    - HPOS enabled (WooCommerce → Settings → Advanced → Features)

Gateways to test:

1. WooPayments (first)
2. WooCommerce Stripe Payment Gateway (“Stripe for WooCommerce”) (second)

---

## Core acceptance criteria

The plugin is considered shippable when:

- No fatal errors on activation/deactivation.
- No PHP warnings/notices in normal admin flows.
- Orders screen remains performant (no repeated Stripe calls for the same order once cached).
- Cache management actions are safe (nonces, permissions, confirmations).
- Report page functions without calling Stripe.
- Behavior is stable in both legacy Orders list and HPOS Orders list.

---

## Test matrix (what to verify)

### 1) Installation / Activation / Deactivation

- Activate with WooCommerce active → no fatal.
- Activate without WooCommerce active → plugin should not boot integrations; should show a helpful notice.
- Deactivate → no fatal, no leftover scheduled events that cause repeated errors.

### 2) Settings page (WooCommerce → Stripe Auditor)

- Page loads for admin users.
- Stripe Secret Key field:
    - accepts value and saves
    - stored value is not printed as plain text elsewhere
- Diagnostics panel renders and is readable.
- Header actions present:
    - Support
    - Clear cache
    - Warm cache
- Body actions present (secondary):
    - Clear ALL
    - Start background warm
    - Stop background warm

### 3) Permissions + nonces

- Cache actions require appropriate permissions.
- Directly hitting admin-post endpoints without nonce fails safely.

Endpoints:

- `snrfa_clear_cache`
- `snrfa_clear_cache_all`
- `snrfa_warm_cache`
- `snrfa_warm_cache_start`
- `snrfa_warm_cache_stop`

### 4) Orders list column: placement + formatting

Verify on:

- WooCommerce → Orders (legacy)
- WooCommerce → Orders (HPOS)

Expected:

- Column label: **Stripe Net**
- Column positioned next to the gross total column (adjacent to order total).
- Cell formatting:
    - Fee shown (red)
    - Net shown (green)
    - “No Stripe ID” / “N/A” shown muted

### 5) Orders list column: behavior by order type

Create/locate orders representing:

- A Stripe paid order with a stored Stripe transaction id
- An order with no Stripe transaction id
- An order with a Stripe transaction id that cannot be resolved (invalid/removed)

Expected:

- Stripe paid order shows Fee/Net.
- Missing id shows “No Stripe ID”.
- Unresolvable shows “N/A”.

### 6) PaymentIntent vs Charge transaction IDs

Identify how the gateway stores transaction ids:

- `pi_...` (PaymentIntent)
- `ch_...` (Charge)

Expected:

- If the order has `pi_...`, plugin attempts to resolve it and still shows Fee/Net.
- If it cannot resolve, it fails gracefully (N/A) and does not fatal.

### 7) Caching correctness + performance

Validate caching layers:

- Order meta cache: `_snrfa_stripe_net_revenue`
- Transient cache: `snrfa_txn_{txn_id}`

Expected:

- First view of an order row may trigger a Stripe lookup.
- Subsequent reloads should use cached data (no repeated Stripe calls for same order).
- Cache persists across page refreshes.

### 8) Cache clear actions

- “Clear Stripe Net cache” clears caches for recent orders (safe default).
- “Clear ALL Stripe Net cache”:
    - requires confirmation
    - runs in batches
    - completes without timeouts on moderate datasets

Verify post-clear:

- Orders list shows values repopulate after reload.

### 9) Warm cache actions

- Manual warm cache:
    - runs in batches
    - repopulates data for older orders
- Background warm cache:
    - schedules and continues processing
    - stop button actually stops

### 10) Report screen (WooCommerce → Stripe Net Revenue)

- Page loads.
- Filters work:
    - date range
    - status
    - transaction id
    - group by (day/week/month/year)
- Report uses cached data and does not call Stripe.
- Median note displays.

### 11) UX / UI consistency (WP-native)

- No inline styling regressions on settings/report pages.
- Buttons and tables use WP-native classes.
- Orders list column styling is applied via CSS and remains readable.

### 12) Error handling and resilience

Simulate failure cases:

- Missing or invalid Stripe key
- Stripe API returns error (network, auth)

Expected:

- Plugin should not fatal.
- Orders list should show N/A or muted state.
- Admin should remain usable.

---

## Regression checklist (quick)

Before signing off a build:

- Activation OK
- Orders list loads quickly and column renders
- One Stripe-paid order shows Fee/Net
- One missing-id order shows “No Stripe ID”
- Cache clear + warm cache run without errors
- Report page loads and filters run

---

## Notes for QA

- This plugin is designed as a free core. Avoid testing Pro features here.
- Refunds are separate transactions/rows in Stripe; current free core focuses on fee/net per transaction.
