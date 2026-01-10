<?php

namespace Stripe_Net_Revenue;

use Throwable;

final class Columns
{

    private $fetcher;

    /**
     * Keep this consistent with Abstract_Integration/Admin_Cache.
     */
    private const ORDER_META_KEY = '_snrfa_stripe_net_revenue';

    public function __construct()
    {
        $connector = StripeConnector::get_instance();
        $this->fetcher = new StripeFetcher($connector);

        // Register Columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_column_header'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_column_content'), 10, 2);

        // HPOS Support (High Performance Order Storage)
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_column_header'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'populate_column_content_hpos'), 10, 2);
    }

    public function add_column_header($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_total' === $key) {
                $new_columns['snrfa_net_revenue'] = __('Stripe Net', 'snrfa');
            }
        }
        return $new_columns;
    }

    public function populate_column_content($column, $post_id)
    {
        if ('snrfa_net_revenue' === $column) {
            $order = wc_get_order($post_id);
            $this->render_cell_data($order);
        }
    }

    public function populate_column_content_hpos($column, $order)
    {
        if ('snrfa_net_revenue' === $column) {
            $this->render_cell_data($order);
        }
    }

    private function render_cell_data($order)
    {
        if (!is_object($order) || !method_exists($order, 'get_transaction_id')) {
            echo '<span class="snrfa-net__muted">' . esc_html__('N/A', 'snrfa') . '</span>';
            return;
        }

        // 1. Get the Stripe Charge/Transaction ID from the Order.
        $charge_id = (string)$order->get_transaction_id();
        $charge_id = sanitize_text_field($charge_id);

        if (empty($charge_id)) {
            echo '<span class="snrfa-net__muted">' . esc_html__('No Stripe ID', 'snrfa') . '</span>';
            return;
        }

        // 2. Try to read cached data from order meta first (fastest).
        $txn_data = null;
        if (method_exists($order, 'get_meta')) {
            $stored = $order->get_meta(self::ORDER_META_KEY, true);
            if (is_array($stored) && !empty($stored['txn_id']) && $stored['txn_id'] === $charge_id) {
                $txn_data = $stored;
            }
        }

        // 3. Fall back to transient cache.
        if (empty($txn_data)) {
            $cache_key = 'snrfa_txn_' . $charge_id;
            $txn_data = get_transient($cache_key);

            if (false === $txn_data) {
                // 4. Fetch Real Data from Stripe if not cached.
                if (function_exists('snrfa_stripe_call_allowed') && !snrfa_stripe_call_allowed(true, array('source' => 'orders_list_legacy', 'txn_id' => $charge_id))) {
                    $txn = null;
                } else {
                    $txn = $this->fetcher->get_transaction_details($charge_id);
                }

                if ($txn) {
                    $txn_data = array(
                        'fee' => $txn->fee,
                        'net' => $txn->net,
                        'currency' => $txn->currency,
                        'txn_id' => $charge_id,
                        'updated' => time(),
                    );

                    // Cache for 24 hours (86400 seconds)
                    set_transient($cache_key, $txn_data, 86400);

                    // Persist to order meta so subsequent list loads avoid API calls.
                    if (method_exists($order, 'update_meta_data') && method_exists($order, 'save')) {
                        try {
                            $order->update_meta_data(self::ORDER_META_KEY, $txn_data);
                            $order->save();
                        } catch (Throwable $e) {
                            // ignore
                        }
                    }

                    // Let add-ons react to caching.
                    if (function_exists('do_action') && method_exists($order, 'get_id')) {
                        do_action('snrfa_after_txn_cached', (int)$order->get_id(), $txn_data);
                    }
                }
            }
        }

        if (!empty($txn_data) && is_array($txn_data) && isset($txn_data['fee'], $txn_data['net'], $txn_data['currency'])) {
            $fee = StripeFormatter::format_currency($txn_data['fee'], $txn_data['currency']);
            $net = StripeFormatter::format_currency($txn_data['net'], $txn_data['currency']);

            echo '<div class="snrfa-net">';
            echo '<span class="snrfa-net__fee">' . esc_html__('Fee:', 'snrfa') . ' -' . esc_html($fee) . '</span><br>';
            echo '<span class="snrfa-net__net">' . esc_html__('Net:', 'snrfa') . ' ' . esc_html($net) . '</span>';
            echo '</div>';
        } else {
            echo '<span class="snrfa-net__muted">' . esc_html__('N/A', 'snrfa') . '</span>';
        }
    }
}
