<?php

namespace Stripe_Net_Revenue;

/**
 * Lightweight admin report for net revenue totals.
 *
 * This avoids Stripe API calls by reading cached order meta.
 */
final class Admin_Report
{

    private const MENU_SLUG = 'snrfa-net-revenue-report';
    private const META_KEY = '_snrfa_stripe_net_revenue';

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

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to view this report.', 'snrfa'));
        }

        $start = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : '';
        $end = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : '';
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        $args = array(
            'limit' => 200,
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

        $orders = function_exists('wc_get_orders') ? wc_get_orders($args) : array();

        $total_fee = 0;
        $total_net = 0;
        $count_with_cache = 0;
        $currency = '';

        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_meta')) {
                continue;
            }
            $data = $order->get_meta(self::META_KEY, true);
            if (empty($data) || !is_array($data) || !isset($data['fee'], $data['net'], $data['currency'])) {
                continue;
            }
            $total_fee += (int)$data['fee'];
            $total_net += (int)$data['net'];
            $currency = $currency ? $currency : (string)$data['currency'];
            $count_with_cache++;
        }

        $fee_fmt = $currency ? StripeFormatter::format_currency($total_fee, $currency) : (string)$total_fee;
        $net_fmt = $currency ? StripeFormatter::format_currency($total_net, $currency) : (string)$total_net;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Stripe Net Revenue', 'snrfa'); ?></h1>
            <p style="max-width: 900px;">
                <?php echo esc_html__('This report sums cached Stripe fee/net values stored on orders. If you have not viewed recent orders yet, some orders may not have cached values. Open WooCommerce → Orders to populate cache, or use Pro add-ons for background syncing.', 'snrfa'); ?>
            </p>

            <form method="get" style="margin: 12px 0;">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>"/>
                <label>
                    <?php esc_html_e('Start date', 'snrfa'); ?>
                    <input type="date" name="start" value="<?php echo esc_attr($start); ?>"/>
                </label>
                <label style="margin-left: 12px;">
                    <?php esc_html_e('End date', 'snrfa'); ?>
                    <input type="date" name="end" value="<?php echo esc_attr($end); ?>"/>
                </label>
                <label style="margin-left: 12px;">
                    <?php esc_html_e('Status', 'snrfa'); ?>
                    <input type="text" name="status" value="<?php echo esc_attr($status); ?>"
                           placeholder="wc-completed"/>
                </label>
                <?php submit_button(__('Filter', 'snrfa'), 'secondary', '', false); ?>
            </form>

            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Orders scanned:', 'snrfa'); ?></strong> <?php echo esc_html((string)count($orders)); ?>
                    &mdash;
                    <strong><?php esc_html_e('Orders with cached Stripe Net data:', 'snrfa'); ?></strong> <?php echo esc_html((string)$count_with_cache); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Total Stripe fees:', 'snrfa'); ?></strong> <?php echo esc_html($fee_fmt); ?>
                    <br/>
                    <strong><?php esc_html_e('Total Stripe net:', 'snrfa'); ?></strong> <?php echo esc_html($net_fmt); ?>
                </p>
            </div>
        </div>
        <?php
    }
}

