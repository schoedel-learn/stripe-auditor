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

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Stripe Net Revenue', 'snrfa'); ?></h1>
            <p class="snrfa-report-note">
                <?php echo esc_html__('Note: Median values are estimated for performance on large stores.', 'snrfa'); ?>
            </p>

            <form method="get" class="snrfa-report-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>"/>

                <label>
                    <?php esc_html_e('Start date', 'snrfa'); ?>
                    <input type="date" name="start" value="<?php echo esc_attr($start); ?>"/>
                </label>
                <label>
                    <?php esc_html_e('End date', 'snrfa'); ?>
                    <input type="date" name="end" value="<?php echo esc_attr($end); ?>"/>
                </label>
                <label>
                    <?php esc_html_e('Status', 'snrfa'); ?>
                    <input type="text" name="status" value="<?php echo esc_attr($status); ?>"
                           placeholder="wc-completed"/>
                </label>
                <label>
                    <?php esc_html_e('Transaction', 'snrfa'); ?>
                    <input type="text" name="transaction" value="<?php echo esc_attr($txn); ?>"
                           placeholder="ch_... or pi_..."/>
                </label>
                <label>
                    <?php esc_html_e('Group by', 'snrfa'); ?>
                    <select name="group">
                        <option value="day" <?php selected($group, 'day'); ?>><?php esc_html_e('Day', 'snrfa'); ?></option>
                        <option value="week" <?php selected($group, 'week'); ?>><?php esc_html_e('Week', 'snrfa'); ?></option>
                        <option value="month" <?php selected($group, 'month'); ?>><?php esc_html_e('Month', 'snrfa'); ?></option>
                        <option value="year" <?php selected($group, 'year'); ?>><?php esc_html_e('Year', 'snrfa'); ?></option>
                    </select>
                </label>

                <?php submit_button(__('Run', 'snrfa'), 'secondary', '', false); ?>
            </form>

            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Orders scanned:', 'snrfa'); ?></strong> <?php echo esc_html((string)$orders_scanned); ?>
                    &mdash;
                    <strong><?php esc_html_e('Transactions with cached Stripe Net data:', 'snrfa'); ?></strong> <?php echo esc_html((string)$count_with_cache); ?>
                </p>
                <table class="widefat striped snrfa-report-table">
                    <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Total net', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($net_total)); ?></td>
                        <td><strong><?php esc_html_e('Total fees', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($fee_total)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Average net (mean)', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($net_avg)); ?></td>
                        <td><strong><?php esc_html_e('Average fees (mean)', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($fee_avg)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Median net', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($net_median)); ?></td>
                        <td><strong><?php esc_html_e('Median fees', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($fee_median)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Highest net', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($net_max)); ?></td>
                        <td><strong><?php esc_html_e('Highest fee', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($fee_max)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Lowest net', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($net_min)); ?></td>
                        <td><strong><?php esc_html_e('Lowest fee', 'snrfa'); ?></strong></td>
                        <td><?php echo esc_html($fmt($fee_min)); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <h2><?php esc_html_e('Grouped results', 'snrfa'); ?></h2>
            <table class="widefat striped snrfa-report-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Period', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Count', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Net total', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Net avg', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Net median', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Net high', 'snrfa'); ?></th>
                    <th><?php esc_html_e('Net low', 'snrfa'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No cached data found for the selected filters.', 'snrfa'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $period => $d) :
                        $c = (int)$d['count'];
                        $avg = $c ? (int)round($d['net_total'] / $c) : null;
                        $med = (null !== $d['net_hist_min'] && null !== $d['net_hist_max']) ? Stats::approx_median_from_histogram($d['net_hist'], $c, $d['net_hist_min'], $d['net_hist_max'], self::MEDIAN_BUCKETS) : null;
                        $high = $d['net_max'];
                        $low = $d['net_min'];
                        ?>
                        <tr>
                            <td><?php echo esc_html($period); ?></td>
                            <td><?php echo esc_html((string)$c); ?></td>
                            <td><?php echo esc_html($fmt($d['net_total'])); ?></td>
                            <td><?php echo esc_html($fmt($avg)); ?></td>
                            <td><?php echo esc_html($fmt($med)); ?></td>
                            <td><?php echo esc_html($fmt($high)); ?></td>
                            <td><?php echo esc_html($fmt($low)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

