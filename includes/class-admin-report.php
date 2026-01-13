<?php

namespace Stripe_Net_Revenue;

/**
 * UI lint (flat/minimal WP-native):
 * - Prefer WP admin classes/components (wrap, form-table, widefat, notices)
 * - Keep copy short and skimmable
 * - Escape all output (esc_html/esc_attr/esc_url)
 * - Avoid heavy custom CSS/layout and avoid extra Stripe calls on render
 */

/**
 * Lightweight admin report for net revenue totals.
 *
 * This avoids Stripe API calls by reading cached order meta.
 */
final class Admin_Report
{

    private const MENU_SLUG = 'snrfa-net-revenue-report';
    private const META_KEY = '_snrfa_stripe_net_revenue';

    private const REPORT_PAGE_SIZE = 500;
    private const REPORT_MAX_PAGES = 20; // hard stop to prevent runaway admin requests
    private const MEDIAN_BUCKETS = 64;

    public static function register_menu()
    {
        add_submenu_page(
                'woocommerce',
                esc_html__('Stripe Net Revenue', 'snrfa'),
                esc_html__('Stripe Net Revenue', 'snrfa'),
                'manage_woocommerce',
                self::MENU_SLUG,
                array(__CLASS__, 'render_page')
        );
    }

    private static function parse_get($key)
    {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    private static function group_key($timestamp, $group)
    {
        switch ($group) {
            case 'week':
                return gmdate('o-\\WW', $timestamp);
            case 'month':
                return gmdate('Y-m', $timestamp);
            case 'year':
                return gmdate('Y', $timestamp);
            case 'day':
            default:
                return gmdate('Y-m-d', $timestamp);
        }
    }

    /**
     * Render a dashboard card with consistent styling.
     *
     * @param string $title Card title
     * @param string $value Main value to display
     * @param string $color Border color (e.g., 'emerald', 'rose', 'slate', 'amber')
     * @param string $icon SVG icon path data
     * @param string $extra_info Additional information text
     * @return void
     */
    private static function render_card($title, $value, $color, $icon, $extra_info = '')
    {
        $border_color = 'snrfa-border-' . $color . '-400';
        $icon_bg = 'snrfa-bg-' . $color . '-100';
        $icon_color = 'snrfa-text-' . $color . '-600';
        ?>
        <div class="snrfa-bg-white snrfa-rounded-lg snrfa-shadow-sm snrfa-p-6 snrfa-border-l-4 <?php echo esc_attr($border_color); ?>">
            <div class="snrfa-flex snrfa-items-start snrfa-justify-between">
                <div class="snrfa-flex-1">
                    <div class="snrfa-flex snrfa-items-center snrfa-gap-2 snrfa-mb-2">
                        <div class="snrfa-p-2 snrfa-rounded-lg <?php echo esc_attr($icon_bg); ?>">
                            <svg class="snrfa-h-5 snrfa-w-5 <?php echo esc_attr($icon_color); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($icon); ?>"/>
                            </svg>
                        </div>
                        <p class="snrfa-text-sm snrfa-font-medium snrfa-text-gray-600"><?php echo esc_html($title); ?></p>
                    </div>
                    <p class="snrfa-text-3xl snrfa-font-bold snrfa-text-gray-900"><?php echo esc_html($value); ?></p>
                    <?php if ($extra_info) : ?>
                        <p class="snrfa-text-xs snrfa-text-gray-500 snrfa-mt-2"><?php echo wp_kses_post($extra_info); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to view this report.', 'snrfa'));
        }

        $start = self::parse_get('start');
        $end = self::parse_get('end');
        $status = self::parse_get('status');

        // New filters.
        $txn = self::parse_get('transaction');
        $group = self::parse_get('group');
        if (empty($group)) {
            $group = 'day';
        }

        $args = array(
                'limit' => self::REPORT_PAGE_SIZE,
                'orderby' => 'date',
                'order' => 'DESC',
        );

        if ($status) {
            $args['status'] = $status;
        }

        if ($start) {
            $args['date_created'] = '>=' . $start;
        }
        if ($end) {
            $args['date_created'] = (isset($args['date_created']) ? $args['date_created'] . '...' : '') . '<=' . $end;
        }

        $currency = '';
        $orders_scanned = 0;
        $rows = array();

        // Streaming overall stats.
        $count_with_cache = 0;
        $fee_total = 0;
        $net_total = 0;
        $fee_min = null;
        $fee_max = null;
        $net_min = null;
        $net_max = null;

        $fee_hist = array();
        $net_hist = array();
        $fee_hist_min = null;
        $fee_hist_max = null;
        $net_hist_min = null;
        $net_hist_max = null;

        // Cursor pagination: move backward through time to avoid offsets.
        $cursor_ts = null;
        $page = 0;
        while ($page < self::REPORT_MAX_PAGES) {
            $query_args = $args;

            // If paging, move the effective end boundary backward by cursor.
            if ($cursor_ts) {
                // If a user supplied a date range, we keep the start bound and tighten the end bound.
                if (!empty($end)) {
                    // End was supplied; tighten it.
                    $query_args['date_created'] = ($start ? '>=' . $start . '...' : '') . '<=' . gmdate('Y-m-d', $cursor_ts);
                } else {
                    // No end supplied; just page backward.
                    $query_args['date_created'] = '<=' . gmdate('Y-m-d', $cursor_ts);
                }
            }

            $orders = function_exists('wc_get_orders') ? wc_get_orders($query_args) : array();
            if (empty($orders)) {
                break;
            }

            $orders_scanned += count($orders);

            // Track new cursor based on last order date.
            $last_order = end($orders);
            if (is_object($last_order) && method_exists($last_order, 'get_date_created')) {
                $dc = $last_order->get_date_created();
                if ($dc && method_exists($dc, 'getTimestamp')) {
                    $cursor_ts = (int)$dc->getTimestamp();
                }
            }

            foreach ($orders as $order) {
                if (!is_object($order) || !method_exists($order, 'get_meta')) {
                    continue;
                }

                $data = $order->get_meta(self::META_KEY, true);
                if (empty($data) || !is_array($data) || !isset($data['fee'], $data['net'], $data['currency'], $data['txn_id'])) {
                    continue;
                }

                if ($txn && $data['txn_id'] !== $txn) {
                    continue;
                }

                $fee = (int)$data['fee'];
                $net = (int)$data['net'];
                $currency = $currency ? $currency : (string)$data['currency'];

                $count_with_cache++;
                $fee_total += $fee;
                $net_total += $net;
                $fee_min = (null === $fee_min) ? $fee : min($fee_min, $fee);
                $fee_max = (null === $fee_max) ? $fee : max($fee_max, $fee);
                $net_min = (null === $net_min) ? $net : min($net_min, $net);
                $net_max = (null === $net_max) ? $net : max($net_max, $net);

                // Expand histogram bounds dynamically.
                $fee_hist_min = (null === $fee_hist_min) ? $fee : min($fee_hist_min, $fee);
                $fee_hist_max = (null === $fee_hist_max) ? $fee : max($fee_hist_max, $fee);
                $net_hist_min = (null === $net_hist_min) ? $net : min($net_hist_min, $net);
                $net_hist_max = (null === $net_hist_max) ? $net : max($net_hist_max, $net);

                // Add to histograms.
                $fee_hist = Stats::histogram_add($fee, $fee_hist_min, $fee_hist_max, self::MEDIAN_BUCKETS, $fee_hist);
                $net_hist = Stats::histogram_add($net, $net_hist_min, $net_hist_max, self::MEDIAN_BUCKETS, $net_hist);

                // Grouped rollups (keep only aggregates + small per-group lists for median/high/low).
                $created_ts = time();
                if (method_exists($order, 'get_date_created')) {
                    $dc = $order->get_date_created();
                    if ($dc && method_exists($dc, 'getTimestamp')) {
                        $created_ts = (int)$dc->getTimestamp();
                    }
                }
                $key = self::group_key($created_ts, $group);
                if (!isset($rows[$key])) {
                    $rows[$key] = array(
                            'count' => 0,
                            'net_total' => 0,
                            'net_min' => null,
                            'net_max' => null,
                            'net_hist' => array(),
                            'net_hist_min' => null,
                            'net_hist_max' => null,
                    );
                }
                $rows[$key]['count']++;
                $rows[$key]['net_total'] += $net;
                $rows[$key]['net_min'] = (null === $rows[$key]['net_min']) ? $net : min($rows[$key]['net_min'], $net);
                $rows[$key]['net_max'] = (null === $rows[$key]['net_max']) ? $net : max($rows[$key]['net_max'], $net);
                $rows[$key]['net_hist_min'] = (null === $rows[$key]['net_hist_min']) ? $net : min($rows[$key]['net_hist_min'], $net);
                $rows[$key]['net_hist_max'] = (null === $rows[$key]['net_hist_max']) ? $net : max($rows[$key]['net_hist_max'], $net);
                $rows[$key]['net_hist'] = Stats::histogram_add($net, $rows[$key]['net_hist_min'], $rows[$key]['net_hist_max'], self::MEDIAN_BUCKETS, $rows[$key]['net_hist']);
            }

            $page++;
            if (!empty($txn)) {
                break;
            }
        }

        $fee_avg = $count_with_cache ? (int)round($fee_total / $count_with_cache) : null;
        $net_avg = $count_with_cache ? (int)round($net_total / $count_with_cache) : null;
        $fee_median = (null !== $fee_hist_min && null !== $fee_hist_max) ? Stats::approx_median_from_histogram($fee_hist, $count_with_cache, $fee_hist_min, $fee_hist_max, self::MEDIAN_BUCKETS) : null;
        $net_median = (null !== $net_hist_min && null !== $net_hist_max) ? Stats::approx_median_from_histogram($net_hist, $count_with_cache, $net_hist_min, $net_hist_max, self::MEDIAN_BUCKETS) : null;

        $fmt = function ($amount) use ($currency) {
            if ($amount === null) {
                return '—';
            }
            return $currency ? StripeFormatter::format_currency($amount, $currency) : (string)$amount;
        };

        krsort($rows);

        // Enqueue our custom CSS file with utility classes
        wp_enqueue_style(
            'snrfa-admin-report',
            plugins_url('assets/admin-report.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

        ?>
        <div class="wrap">
            <h1 class="snrfa-text-3xl snrfa-font-bold snrfa-mb-2"><?php echo esc_html__('Stripe Net Revenue', 'snrfa'); ?></h1>
            <p class="snrfa-text-gray-600 snrfa-mb-6">
                <?php echo esc_html__('Note: Median values are estimated for performance on large stores.', 'snrfa'); ?>
            </p>

            <!-- Filter Section -->
            <div class="snrfa-bg-white snrfa-p-6 snrfa-rounded-lg snrfa-shadow-sm snrfa-mb-6">
                <h2 class="snrfa-text-lg snrfa-font-semibold snrfa-mb-4"><?php esc_html_e('Filters', 'snrfa'); ?></h2>
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>"/>
                    
                    <div class="snrfa-grid snrfa-grid-cols-1 md:snrfa-grid-cols-2 lg:snrfa-grid-cols-3 snrfa-gap-4 snrfa-mb-4">
                        <div>
                            <label for="filter-start" class="snrfa-block snrfa-text-sm snrfa-font-medium snrfa-text-gray-700 snrfa-mb-1">
                                <?php esc_html_e('Start date', 'snrfa'); ?>
                            </label>
                            <input id="filter-start" type="date" name="start" value="<?php echo esc_attr($start); ?>" 
                                   class="snrfa-w-full snrfa-px-3 snrfa-py-2 snrfa-border snrfa-border-gray-300 snrfa-rounded-md snrfa-focus-outline-none snrfa-focus-ring-2 snrfa-focus-ring-slate-500"/>
                        </div>
                        
                        <div>
                            <label for="filter-end" class="snrfa-block snrfa-text-sm snrfa-font-medium snrfa-text-gray-700 snrfa-mb-1">
                                <?php esc_html_e('End date', 'snrfa'); ?>
                            </label>
                            <input id="filter-end" type="date" name="end" value="<?php echo esc_attr($end); ?>" 
                                   class="snrfa-w-full snrfa-px-3 snrfa-py-2 snrfa-border snrfa-border-gray-300 snrfa-rounded-md snrfa-focus-outline-none snrfa-focus-ring-2 snrfa-focus-ring-slate-500"/>
                        </div>
                        
                        <div>
                            <label for="filter-status" class="snrfa-block snrfa-text-sm snrfa-font-medium snrfa-text-gray-700 snrfa-mb-1">
                                <?php esc_html_e('Order Status', 'snrfa'); ?>
                            </label>
                            <select id="filter-status" name="status" class="snrfa-w-full snrfa-px-3 snrfa-py-2 snrfa-border snrfa-border-gray-300 snrfa-rounded-md snrfa-focus-outline-none snrfa-focus-ring-2 snrfa-focus-ring-slate-500">
                                <option value=""><?php esc_html_e('All Statuses', 'snrfa'); ?></option>
                                <option value="wc-pending" <?php selected($status, 'wc-pending'); ?>><?php esc_html_e('Pending', 'snrfa'); ?></option>
                                <option value="wc-processing" <?php selected($status, 'wc-processing'); ?>><?php esc_html_e('Processing', 'snrfa'); ?></option>
                                <option value="wc-on-hold" <?php selected($status, 'wc-on-hold'); ?>><?php esc_html_e('On Hold', 'snrfa'); ?></option>
                                <option value="wc-completed" <?php selected($status, 'wc-completed'); ?>><?php esc_html_e('Completed', 'snrfa'); ?></option>
                                <option value="wc-cancelled" <?php selected($status, 'wc-cancelled'); ?>><?php esc_html_e('Cancelled', 'snrfa'); ?></option>
                                <option value="wc-refunded" <?php selected($status, 'wc-refunded'); ?>><?php esc_html_e('Refunded', 'snrfa'); ?></option>
                                <option value="wc-failed" <?php selected($status, 'wc-failed'); ?>><?php esc_html_e('Failed', 'snrfa'); ?></option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter-transaction" class="snrfa-block snrfa-text-sm snrfa-font-medium snrfa-text-gray-700 snrfa-mb-1">
                                <?php esc_html_e('Transaction ID', 'snrfa'); ?>
                            </label>
                            <input id="filter-transaction" type="text" name="transaction" value="<?php echo esc_attr($txn); ?>" 
                                   placeholder="ch_... or pi_..."
                                   class="snrfa-w-full snrfa-px-3 snrfa-py-2 snrfa-border snrfa-border-gray-300 snrfa-rounded-md snrfa-focus-outline-none snrfa-focus-ring-2 snrfa-focus-ring-slate-500"/>
                        </div>
                        
                        <div>
                            <label for="filter-group" class="snrfa-block snrfa-text-sm snrfa-font-medium snrfa-text-gray-700 snrfa-mb-1">
                                <?php esc_html_e('Group by', 'snrfa'); ?>
                            </label>
                            <select id="filter-group" name="group" class="snrfa-w-full snrfa-px-3 snrfa-py-2 snrfa-border snrfa-border-gray-300 snrfa-rounded-md snrfa-focus-outline-none snrfa-focus-ring-2 snrfa-focus-ring-slate-500">
                                <option value="day" <?php selected($group, 'day'); ?>><?php esc_html_e('Day', 'snrfa'); ?></option>
                                <option value="week" <?php selected($group, 'week'); ?>><?php esc_html_e('Week', 'snrfa'); ?></option>
                                <option value="month" <?php selected($group, 'month'); ?>><?php esc_html_e('Month', 'snrfa'); ?></option>
                                <option value="year" <?php selected($group, 'year'); ?>><?php esc_html_e('Year', 'snrfa'); ?></option>
                            </select>
                        </div>
                        
                        <div class="snrfa-flex snrfa-items-end">
                            <?php submit_button(__('Run Report', 'snrfa'), 'primary', '', false, array('class' => 'button-primary snrfa-w-full')); ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Scan Info -->
            <div class="snrfa-bg-blue-50 snrfa-border-l-4 snrfa-border-blue-400 snrfa-p-4 snrfa-mb-6">
                <div class="snrfa-flex">
                    <div class="snrfa-flex-shrink-0">
                        <svg class="snrfa-h-5 snrfa-w-5 snrfa-text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="snrfa-ml-3">
                        <p class="snrfa-text-sm snrfa-text-blue-700">
                            <strong><?php esc_html_e('Orders scanned:', 'snrfa'); ?></strong> <?php echo esc_html((string)$orders_scanned); ?>
                            &nbsp;•&nbsp;
                            <strong><?php esc_html_e('Transactions with cached data:', 'snrfa'); ?></strong> <?php echo esc_html((string)$count_with_cache); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Cards -->
            <div class="snrfa-grid snrfa-grid-cols-1 md:snrfa-grid-cols-2 lg:snrfa-grid-cols-4 snrfa-gap-6 snrfa-mb-8">
                <?php
                // Net Revenue Card - emerald/green with trending up icon
                $net_extra = sprintf(
                    '%s %s &nbsp;•&nbsp; %s %s',
                    esc_html__('Avg:', 'snrfa'),
                    esc_html($fmt($net_avg)),
                    esc_html__('Median:', 'snrfa'),
                    esc_html($fmt($net_median))
                );
                self::render_card(
                    __('Net Revenue', 'snrfa'),
                    $fmt($net_total),
                    'emerald',
                    'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
                    $net_extra
                );

                // Total Fees Card - rose/red with credit card icon
                $fee_extra = sprintf(
                    '%s %s &nbsp;•&nbsp; %s %s',
                    esc_html__('Avg:', 'snrfa'),
                    esc_html($fmt($fee_avg)),
                    esc_html__('Median:', 'snrfa'),
                    esc_html($fmt($fee_median))
                );
                self::render_card(
                    __('Total Fees', 'snrfa'),
                    $fmt($fee_total),
                    'rose',
                    'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                    $fee_extra
                );

                // High Values Card - slate/blue with arrow up icon
                $high_extra = sprintf(
                    '%s %s',
                    esc_html__('Fee:', 'snrfa'),
                    esc_html($fmt($fee_max))
                );
                self::render_card(
                    __('Highest Values', 'snrfa'),
                    sprintf('%s %s', esc_html__('Net:', 'snrfa'), esc_html($fmt($net_max))),
                    'slate',
                    'M7 11l5-5m0 0l5 5m-5-5v12',
                    $high_extra
                );

                // Low Values Card - amber/yellow with arrow down icon
                $low_extra = sprintf(
                    '%s %s',
                    esc_html__('Fee:', 'snrfa'),
                    esc_html($fmt($fee_min))
                );
                self::render_card(
                    __('Lowest Values', 'snrfa'),
                    sprintf('%s %s', esc_html__('Net:', 'snrfa'), esc_html($fmt($net_min))),
                    'amber',
                    'M17 13l-5 5m0 0l-5-5m5 5V6',
                    $low_extra
                );
                ?>
            </div>

            <!-- Grouped Results Table -->
            <div class="snrfa-bg-white snrfa-rounded-lg snrfa-shadow-sm snrfa-overflow-hidden">
                <div class="snrfa-px-6 snrfa-py-4 snrfa-border-b snrfa-border-gray-200">
                    <h2 class="snrfa-text-xl snrfa-font-semibold snrfa-text-gray-900"><?php esc_html_e('Grouped Results', 'snrfa'); ?></h2>
                </div>
                <div class="snrfa-overflow-x-auto">
                    <table class="snrfa-min-w-full snrfa-divide-y snrfa-divide-gray-200">
                        <thead class="snrfa-bg-gray-50">
                            <tr>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Period', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Count', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Net Total', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Net Avg', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Net Median', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Net High', 'snrfa'); ?></th>
                                <th class="snrfa-px-6 snrfa-py-3 snrfa-text-left snrfa-text-xs snrfa-font-medium snrfa-text-gray-500 snrfa-uppercase snrfa-tracking-wider"><?php esc_html_e('Net Low', 'snrfa'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="snrfa-bg-white snrfa-divide-y snrfa-divide-gray-200">
                            <?php if (empty($rows)) : ?>
                                <tr>
                                    <td colspan="7" class="snrfa-px-6 snrfa-py-4 snrfa-text-center snrfa-text-sm snrfa-text-gray-500"><?php esc_html_e('No cached data found for the selected filters.', 'snrfa'); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($rows as $period => $d) :
                                    $c = (int)$d['count'];
                                    $avg = $c ? (int)round($d['net_total'] / $c) : null;
                                    $med = (null !== $d['net_hist_min'] && null !== $d['net_hist_max']) ? Stats::approx_median_from_histogram($d['net_hist'], $c, $d['net_hist_min'], $d['net_hist_max'], self::MEDIAN_BUCKETS) : null;
                                    $high = $d['net_max'];
                                    $low = $d['net_min'];
                                    ?>
                                    <tr class="snrfa-hover-bg-gray-50 snrfa-focus-within-bg-gray-50 snrfa-transition-colors">
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-font-medium snrfa-text-gray-900"><?php echo esc_html($period); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-text-gray-700"><?php echo esc_html((string)$c); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-font-semibold snrfa-text-green-600"><?php echo esc_html($fmt($d['net_total'])); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-text-gray-700"><?php echo esc_html($fmt($avg)); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-text-gray-700"><?php echo esc_html($fmt($med)); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-text-gray-700"><?php echo esc_html($fmt($high)); ?></td>
                                        <td class="snrfa-px-6 snrfa-py-4 snrfa-whitespace-nowrap snrfa-text-sm snrfa-text-gray-700"><?php echo esc_html($fmt($low)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

