# LocalWP Setup (Stripe Net Revenue Auditor)

This is the recommended local testing environment for click-testing admin UI, caching behavior, and WooCommerce order
list performance.

It complements (not replaces) the fast CLI smoke tests in `docs/LOCAL_TESTING.md`.

---

## 0) Prereqs

- macOS
- LocalWP installed
- A Stripe account (for a *test* key)

Optional (but recommended):

- A persistent object cache plugin (Redis Object Cache) if you want performance realism

---

## 1) Create a LocalWP site

1. Open LocalWP
2. Create a new site ("Preferred" environment is fine)
3. Set a memorable WP admin user/password

---

## 2) Install WooCommerce

1. WP Admin → Plugins → Add New
2. Install + activate **WooCommerce**
3. Run the WooCommerce onboarding wizard (basic settings only)

---

## 3) Install this plugin from your working copy (Option B: symlink)

This workflow keeps your LocalWP site always using your current git working copy.

### 3.1 Find your LocalWP site path

In LocalWP:

- Select your site → **Site info** → confirm the local site path.

You need the WordPress folder that contains:

- `app/public/wp-content/plugins/`

### 3.2 Create the symlink

From a terminal:

```bash
# Example: adjust the LocalWP site path to your site
ln -s /Users/schoedel/Projects/stripe-auditor /path/to/localwp/site/app/public/wp-content/plugins/stripe-auditor
```

If the folder already exists (previous copy install), remove it first:

```bash
rm -rf /path/to/localwp/site/app/public/wp-content/plugins/stripe-auditor
```

Then create the symlink again.

### 3.3 Activate the plugin

WP Admin → Plugins → Installed Plugins → Activate **Stripe Net Revenue Auditor**

---

## 4) Install and test with WooPayments (FIRST)

### 4.1 Install WooPayments

1. WP Admin → Plugins → Add New
2. Install + activate **WooPayments**
3. Go to: **Payments → Overview** or **WooCommerce → Settings → Payments**

### 4.2 Enable test mode

WooPayments supports test mode. Enable it so you can place test orders without real charges.

If WooPayments prompts you to connect an account:

- Prefer connecting to a Stripe test account / sandbox flow.
- If connection flow is too heavy for local testing, skip ahead to the Stripe for WooCommerce section below.

### 4.3 Place a test order

1. WP Admin → Products → Add New
2. Create a simple product (e.g., “Test Product” for $10)
3. Visit the storefront product page and purchase it with WooPayments (test card)

### 4.4 Verify Stripe Net Revenue Auditor

1. WP Admin → WooCommerce → Stripe Auditor
2. Set your Stripe test secret key: `sk_test_...`
3. WP Admin → WooCommerce → Orders

Expected:

- A **Stripe Net** column next to the gross order total
- For your WooPayments test order, the column should show Fee + Net
- If it shows **No Stripe ID**, confirm WooPayments stored the transaction id on the order

---

## 5) Install and test with Stripe for WooCommerce (SECOND)

This is often the easiest way to ensure Stripe transaction ids are stored in a predictable way.

### 5.1 Install Stripe for WooCommerce

1. WP Admin → Plugins → Add New
2. Install + activate **WooCommerce Stripe Payment Gateway** (often shown as “Stripe for WooCommerce”)

### 5.2 Configure the gateway in test mode

1. WP Admin → WooCommerce → Settings → Payments
2. Enable **Stripe**
3. Click **Manage**
4. Enable **Test mode**
5. Enter Stripe keys:
    - Publishable key: `pk_test_...`
    - Secret key: `sk_test_...`

### 5.3 Place a test order

1. Place another order through checkout using the Stripe payment method (test card)

### 5.4 Verify Stripe Net Revenue Auditor

- WP Admin → WooCommerce → Orders
- Confirm the Stripe order row shows Fee + Net

---

## 6) Plugin acceptance checklist (manual)

### 6.1 Orders list

- Confirm the Stripe Net column placement and alignment
- Confirm outputs:
    - Fee line (red)
    - Net line (green)
    - No Stripe ID / N/A (muted)

### 6.2 Caching

- Load Orders once → then reload and compare speed
- WP Admin → WooCommerce → Stripe Auditor:
    - Clear Stripe Net cache (recent)
    - Warm Stripe Net cache
    - Clear ALL Stripe Net cache (confirm prompt)
    - Start/Stop background warm cache

### 6.3 Report

- WP Admin → WooCommerce → Stripe Net Revenue
- Run filters:
    - Day/week/month/year grouping
    - Status filter
    - Transaction filter

Expected:

- Report uses cached meta.
- Report does not call Stripe.

---

## 7) Debug & troubleshooting

### 7.1 If Orders show “No Stripe ID”

- Confirm the order was paid with the gateway you’re testing (WooPayments or Stripe).
- Confirm the gateway stores a transaction id on the order.

### 7.2 If Orders show “N/A”

- Confirm the Stripe key is correct.
- Confirm the transaction id exists and Stripe API is accessible.
- Try clearing cache and reloading.

### 7.3 If the page feels slow

- Run a warm-cache job.
- Consider object caching.
- Pro add-ons can disable Stripe calls on list screens via the `snrfa_stripe_call_allowed` filter.

---

## 8) Safety

- Use test keys on local/staging.
- Don’t test live keys on a machine you don’t control.

