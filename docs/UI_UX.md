# UI/UX Design System (Stripe Net Revenue Auditor)

This plugin targets a **flat, minimal, modern, polished** admin experience.

The primary constraint is: **it should feel native to WordPress + WooCommerce**, not like a separate app.

---

## Goals

- **Fast scanning** in WooCommerce order lists.
- **Low cognitive load** on settings/report screens.
- **Performance is UX**: avoid unnecessary Stripe calls on admin screens.
- **WP.org friendly**: no nagware patterns.

---

## Principles (what we follow)

### 1) Native WordPress admin first

Prefer WordPress admin primitives over custom UI:

- Containers: `wrap`, `wp-heading-inline`, `hr.wp-header-end`
- Tables: `form-table`, `widefat`, `striped`
- Notices: `notice notice-info|warning|error`
- Buttons/links: `page-title-action`, `button button-primary`, `button button-secondary`

Avoid heavy custom CSS layouts.

### 2) Flat, minimal design

- No gradients, heavy shadows, or decorative borders.
- Use whitespace for separation.
- Use subtle color sparingly and semantically:
    - muted grey = unknown / N/A
    - red = fees
    - green = net

### 3) Make the admin screens feel “calm”

- Prefer short labels.
- Keep the number of actions visible at once small.
- Don’t overwhelm with paragraphs—use bullet lists and short helper text.

### 4) Performance is UX

- Prefer order meta cache over transient over Stripe API.
- Use batching for warm/clear-all operations.
- Avoid API calls in order list rendering when possible.

### 5) Safety for destructive operations

- Show clear language and confirmation prompts.
- When possible, make the safe action the default (e.g., “Clear cache” vs “Clear ALL”).

### 6) Accessibility + security

- Escape output (`esc_html`, `esc_attr`, `esc_url`).
- Use nonces on admin actions.
- Don’t rely on color alone; keep labels like “Fee:” and “Net:”.

### 7) WP.org-safe Pro messaging

In free core:

- One link is fine: “Pro / Add-ons”.
- A short mention in readme/settings is fine.

Avoid:

- Pop-ups
- Repeated nags
- Locking UI behind “Upgrade”

---

## Recommendations for this plugin (what to do next)

These are concrete, opinionated recommendations aligned with the goals in this doc and with how WordPress/WooCommerce
admin screens are expected to feel.

### Keep the “WP header bar” pattern (already good)

- Continue using `wrap`, `wp-heading-inline`, and `hr.wp-header-end` on admin pages.
- Use `page-title-action` links for a **small number** of primary actions.

**Recommendation:** cap header actions to ~3–4. If we keep more (Support + 4 cache buttons), consider grouping:

- Keep: **Support**, **Clear cache**, **Warm cache**
- Move advanced actions into the page body (secondary buttons):
    - **Clear ALL**
    - **Start background warm** / **Stop background warm**

This reduces visual noise while keeping power features accessible.

### Prefer WP-native tables and avoid fixed widths

- Use: `widefat striped` tables (no `style="max-width: ..."`)
- Prefer semantic classes like `column-primary` over hard-coded width styles.

Result: better cross-version consistency, better responsiveness, and less “custom app” feel.

### Diagnostics should be “informational, not dominant”

- Keep diagnostics in a `notice notice-info` block.
- Keep the content short.
- If we add more diagnostics later, consider a collapsible pattern (link to “Show diagnostics”) to keep the screen calm.

### Order list column: optimize for scanning

- Keep the output to 2 lines:
    - Fee
    - Net
- Use muted grey text for “No Stripe ID” and “N/A”.
- Keep it non-blocking; never add spinners or async loaders on the order list.

### Report page: compact filters + dense results

- Filters should feel like WooCommerce reports: compact, inline inputs.
- Results should be a standard `widefat striped` table.
- Avoid custom charting in free core; add charts in Pro if needed.

### Inline styles: minimize, but don’t over-engineer

Preferred order:

1. Use WP classes (best)
2. If needed, use a very small admin CSS file with scoped selectors (next best)
3. Avoid per-element inline styles except for tiny exceptions (e.g., subtle opacity on helper text)

If we remove more inline styles, do it by adding a **tiny** `admin.css` that only targets this plugin’s pages.

### Copywriting (polished + minimal)

- Use short labels (2–4 words).
- Keep helper text one sentence where possible.
- Favor “what happens” over “what it is”.

Examples:

- “Warm cache” → “Warm cache (batch)” (optional)
- “Clear ALL cache” → keep as-is but ensure it is visually secondary

### Pro messaging (WP.org-safe)

- Keep one “Pro / Add-ons” link in the plugin row.
- In settings, if we add Pro info, keep it to a single small paragraph or a single link.

---

## UI surfaces and what “good” looks like

### Orders list column (`Stripe Net`)

- Two-line compact display: Fee and Net.
- Font small enough for density, but readable.
- Always non-blocking: show “No Stripe ID” / “N/A” instead of spinners.

### Settings page

- Use the WP heading bar pattern.
- Keep actions in the heading area as “page-title-action” links.
- Diagnostics: a small info notice with a table is OK.

### Report page

- Filtering UI should be compact.
- Results table should use standard WP tables.

---

## Implementation checklist (for PR review)

- [ ] New UI uses WP admin classes (no fancy custom framework)
- [ ] Output is escaped
- [ ] Copy is short + skimmable
- [ ] Destructive actions require confirmation
- [ ] Order list rendering does not add unnecessary API calls
- [ ] Pro mention is minimal and non-disruptive
