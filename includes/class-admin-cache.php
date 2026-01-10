<?php

namespace Stripe_Net_Revenue;

use Throwable;
use wpdb;

/**
 * Admin cache management.
 */
final class Admin_Cache
{
    private const META_KEY = '_snrfa_stripe_net_revenue';
    private const BATCH_SIZE = 200;

    // Cursors store the last processed order timestamp + ID.
    private const OPTION_CLEAR_CURSOR = 'snrfa_clear_all_cursor';
    private const OPTION_WARM_CURSOR = 'snrfa_warm_cursor';

    /**
     * Safety limits for warm-cache runs.
     */
    private const MAX_STRIPE_CALLS_PER_REQUEST = 25;
    private const STRIPE_CALL_DELAY_USEC = 50000; // 50ms

    private const CRON_HOOK_WARM = 'snrfa_warm_cache_cron';

    /**
     * Register admin-post handlers.
     *
     * @return void
     */
    public static function register()
    {
        add_action('admin_post_snrfa_clear_cache', array(__CLASS__, 'handle_clear_cache_recent'));
        add_action('admin_post_snrfa_clear_cache_all', array(__CLASS__, 'handle_clear_cache_all'));
        add_action('admin_post_snrfa_warm_cache', array(__CLASS__, 'handle_warm_cache'));

        // Background warm cache controls.
        add_action('admin_post_snrfa_warm_cache_start', array(__CLASS__, 'handle_warm_cache_start'));
        add_action('admin_post_snrfa_warm_cache_stop', array(__CLASS__, 'handle_warm_cache_stop'));

        // Cron handler.
        add_action(self::CRON_HOOK_WARM, array(__CLASS__, 'run_warm_cache_cron'));
    }

    /**
     * Clear caches for recent orders only (safe default).
     *
     * @return void
     */
    public static function handle_clear_cache_recent()
    {
        self::assert_permissions_and_nonce('snrfa_clear_cache');

        self::clear_transients();
        self::clear_recent_order_meta(self::BATCH_SIZE);

        self::redirect_back();
    }

    /**
     * Clear caches for ALL orders (advanced).
     *
     * This is intentionally batched to reduce the risk of timeouts.
     * Each request clears up to BATCH_SIZE orders starting from an offset cursor.
     *
     * @return void
     */
    public static function handle_clear_cache_all()
    {
        self::assert_permissions_and_nonce('snrfa_clear_cache_all');

        // Strong confirmation.
        if (empty($_GET['confirm']) || '1' !== (string)$_GET['confirm']) {
            wp_die(esc_html__('Confirmation missing. Please use the settings page button to run this action.', 'snrfa'));
        }

        self::clear_transients();

        $cursor = get_option(self::OPTION_CLEAR_CURSOR, array());
        $processed = self::clear_orders_meta_batch_by_cursor($cursor, self::BATCH_SIZE);

        if (empty($processed['count'])) {
            delete_option(self::OPTION_CLEAR_CURSOR);
            self::redirect_back();
        }

        update_option(self::OPTION_CLEAR_CURSOR, $processed['cursor'], false);

        // Continue batching.
        $next = wp_nonce_url(
            admin_url('admin-post.php?action=snrfa_clear_cache_all&confirm=1'),
            'snrfa_clear_cache_all'
        );
        wp_safe_redirect($next);
        exit;
    }

    /**
     * Warm the cache by backfilling Stripe Net data onto order meta.
     *
     * This is batched to avoid timeouts and Stripe rate limits.
     *
     * @return void
     */
    public static function handle_warm_cache()
    {
        self::assert_permissions_and_nonce('snrfa_warm_cache');

        $cursor = get_option(self::OPTION_WARM_CURSOR, array());
        $processed = self::warm_orders_batch_by_cursor($cursor, self::BATCH_SIZE);

        if (empty($processed['count'])) {
            delete_option(self::OPTION_WARM_CURSOR);
            self::redirect_back();
        }

        update_option(self::OPTION_WARM_CURSOR, $processed['cursor'], false);

        $next = wp_nonce_url(
            admin_url('admin-post.php?action=snrfa_warm_cache'),
            'snrfa_warm_cache'
        );
        wp_safe_redirect($next);
        exit;
    }

    /**
     * Start background warm-cache via WP-Cron.
     */
    public static function handle_warm_cache_start()
    {
        self::assert_permissions_and_nonce('snrfa_warm_cache_start');

        // Ensure cursor exists (initialize from scratch).
        if (!get_option(self::OPTION_WARM_CURSOR, false)) {
            update_option(self::OPTION_WARM_CURSOR, array(), false);
        }

        if (!wp_next_scheduled(self::CRON_HOOK_WARM)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK_WARM);
        }

        self::redirect_back();
    }

    /**
     * Stop background warm-cache.
     */
    public static function handle_warm_cache_stop()
    {
        self::assert_permissions_and_nonce('snrfa_warm_cache_stop');

        // Clear cursor and unschedule.
        delete_option(self::OPTION_WARM_CURSOR);

        $ts = wp_next_scheduled(self::CRON_HOOK_WARM);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK_WARM);
        }

        self::redirect_back();
    }

    /**
     * Cron callback: run one warm-cache batch and reschedule if there is still work.
     */
    public static function run_warm_cache_cron()
    {
        // If WooCommerce isn't available, bail.
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $cursor = get_option(self::OPTION_WARM_CURSOR, false);
        if (false === $cursor) {
            // Not running.
            return;
        }

        $processed = self::warm_orders_batch_by_cursor(is_array($cursor) ? $cursor : array(), self::BATCH_SIZE);

        if (empty($processed['count'])) {
            // Done.
            delete_option(self::OPTION_WARM_CURSOR);
            return;
        }

        update_option(self::OPTION_WARM_CURSOR, $processed['cursor'], false);

        // Reschedule next batch shortly.
        wp_schedule_single_event(time() + 30, self::CRON_HOOK_WARM);
    }

    private static function assert_permissions_and_nonce($nonce_action)
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'snrfa'));
        }
        check_admin_referer($nonce_action);
    }

    private static function redirect_back()
    {
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=snrfa-settings'));
        exit;
    }

    /**
     * Clear plugin transients.
     *
     * @return void
     */
    private static function clear_transients()
    {
        global $wpdb;
        if (empty($wpdb) || !$wpdb instanceof wpdb) {
            return;
        }

        $like = $wpdb->esc_like('_transient_snrfa_txn_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));

        $like_timeout = $wpdb->esc_like('_transient_timeout_snrfa_txn_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout));
    }

    /**
     * Clear cached meta for a limited set of recent orders.
     *
     * @param int $limit
     * @return void
     */
    private static function clear_recent_order_meta($limit)
    {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $orders = wc_get_orders(
            array(
                'limit' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
            )
        );

        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            if (is_object($order) && method_exists($order, 'delete_meta_data') && method_exists($order, 'save')) {
                $order->delete_meta_data(self::META_KEY);
                try {
                    $order->save();
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Get a batch of orders using a date+ID cursor.
     *
     * Cursor shape: array( 'ts' => int, 'id' => int )
     *
     * @return array{orders:array,cursor:array}
     */
    private static function get_orders_by_cursor($cursor, $limit)
    {
        if (!function_exists('wc_get_orders')) {
            return array('orders' => array(), 'cursor' => array());
        }

        $args = array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // If we have a cursor timestamp, fetch orders at/before that date.
        if (is_array($cursor) && !empty($cursor['ts'])) {
            // Use RFC3339 date string.
            $dt = gmdate('Y-m-d H:i:s', (int)$cursor['ts']);
            $args['date_created'] = '<=' . $dt;
        }

        $orders = wc_get_orders($args);
        if (empty($orders)) {
            return array('orders' => array(), 'cursor' => array());
        }

        // If we have a cursor id, skip any orders with the same timestamp and higher/equal ID
        // by filtering in PHP. This avoids offset.
        if (is_array($cursor) && !empty($cursor['id']) && !empty($cursor['ts'])) {
            $filtered = array();
            foreach ($orders as $order) {
                if (!is_object($order) || !method_exists($order, 'get_id')) {
                    continue;
                }

                $id = (int)$order->get_id();
                $ts = 0;
                if (method_exists($order, 'get_date_created')) {
                    $dc = $order->get_date_created();
                    if ($dc && method_exists($dc, 'getTimestamp')) {
                        $ts = (int)$dc->getTimestamp();
                    }
                }

                // Accept if strictly older timestamp, or same timestamp but lower ID.
                if ($ts < (int)$cursor['ts'] || ($ts === (int)$cursor['ts'] && $id < (int)$cursor['id'])) {
                    $filtered[] = $order;
                }
            }
            $orders = $filtered;
        }

        // New cursor is based on the last order in this batch.
        $last = end($orders);
        $new_cursor = array();
        if (is_object($last) && method_exists($last, 'get_id')) {
            $new_cursor['id'] = (int)$last->get_id();
            $new_cursor['ts'] = time();
            if (method_exists($last, 'get_date_created')) {
                $dc = $last->get_date_created();
                if ($dc && method_exists($dc, 'getTimestamp')) {
                    $new_cursor['ts'] = (int)$dc->getTimestamp();
                }
            }
        }

        return array('orders' => $orders, 'cursor' => $new_cursor);
    }

    /**
     * @return array{count:int,cursor:array}
     */
    private static function clear_orders_meta_batch_by_cursor($cursor, $limit)
    {
        $result = self::get_orders_by_cursor($cursor, $limit);
        $orders = $result['orders'];
        if (empty($orders)) {
            return array('count' => 0, 'cursor' => array());
        }

        foreach ($orders as $order) {
            if (is_object($order) && method_exists($order, 'delete_meta_data') && method_exists($order, 'save')) {
                $order->delete_meta_data(self::META_KEY);
                try {
                    $order->save();
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }

        return array('count' => count($orders), 'cursor' => $result['cursor']);
    }

    /**
     * @return array{count:int,cursor:array}
     */
    private static function warm_orders_batch_by_cursor($cursor, $limit)
    {
        $result = self::get_orders_by_cursor($cursor, $limit);
        $orders = $result['orders'];
        if (empty($orders)) {
            return array('count' => 0, 'cursor' => array());
        }

        $connector = StripeConnector::get_instance();
        $fetcher = new StripeFetcher($connector);

        $stripe_calls = 0;

        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_transaction_id')) {
                continue;
            }

            $txn_id = $order->get_transaction_id();
            if (empty($txn_id)) {
                continue;
            }

            // Skip if already cached for this txn.
            if (method_exists($order, 'get_meta')) {
                $existing = $order->get_meta(self::META_KEY, true);
                if (is_array($existing) && isset($existing['txn_id']) && $existing['txn_id'] === $txn_id) {
                    continue;
                }
            }

            // Rate limit Stripe API calls per request.
            if ($stripe_calls >= self::MAX_STRIPE_CALLS_PER_REQUEST) {
                break;
            }

            // Allow add-ons to veto Stripe calls in certain contexts.
            if (function_exists('snrfa_stripe_call_allowed') && !snrfa_stripe_call_allowed(true, array('source' => 'admin_cache_warm', 'txn_id' => $txn_id))) {
                continue;
            }

            $txn = $fetcher->get_transaction_details($txn_id);
            $stripe_calls++;

            if (self::STRIPE_CALL_DELAY_USEC > 0) {
                usleep(self::STRIPE_CALL_DELAY_USEC);
            }

            if (!$txn) {
                continue;
            }

            $txn_data = array(
                'fee' => $txn->fee,
                'net' => $txn->net,
                'currency' => $txn->currency,
                'txn_id' => $txn_id,
                'updated' => time(),
            );

            if (method_exists($order, 'update_meta_data') && method_exists($order, 'save')) {
                $order->update_meta_data(self::META_KEY, $txn_data);
                try {
                    $order->save();
                } catch (Throwable $e) {
                    // ignore
                }
            }

            set_transient('snrfa_txn_' . $txn_id, $txn_data, 86400);

            /**
             * Action: Fired after a Stripe transaction is cached on an order.
             *
             * Pro/add-ons can use this to maintain their own tables/exports.
             *
             * @param int $order_id
             * @param array $txn_data
             */
            if (function_exists('do_action') && method_exists($order, 'get_id')) {
                do_action('snrfa_after_txn_cached', (int)$order->get_id(), $txn_data);
            }
        }

        return array('count' => count($orders), 'cursor' => $result['cursor']);
    }
}
