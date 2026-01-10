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
    private const OPTION_CURSOR = 'snrfa_clear_all_cursor';

    /**
     * Register admin-post handlers.
     *
     * @return void
     */
    public static function register()
    {
        add_action('admin_post_snrfa_clear_cache', array(__CLASS__, 'handle_clear_cache_recent'));
        add_action('admin_post_snrfa_clear_cache_all', array(__CLASS__, 'handle_clear_cache_all'));
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

        $cursor = (int)get_option(self::OPTION_CURSOR, 0);
        $cleared = self::clear_orders_meta_batch($cursor, self::BATCH_SIZE);

        // Advance cursor.
        update_option(self::OPTION_CURSOR, $cursor + self::BATCH_SIZE, false);

        // If we cleared fewer than the batch size, we likely reached the end.
        if ($cleared < self::BATCH_SIZE) {
            delete_option(self::OPTION_CURSOR);
            self::redirect_back();
        }

        // Continue batching by redirecting back to the same action.
        $next = wp_nonce_url(
            admin_url('admin-post.php?action=snrfa_clear_cache_all&confirm=1'),
            'snrfa_clear_cache_all'
        );
        wp_safe_redirect($next);
        exit;
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
     * Clear order meta cache in batches.
     *
     * @param int $offset
     * @param int $limit
     * @return int number of orders processed
     */
    private static function clear_orders_meta_batch($offset, $limit)
    {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders(
            array(
                'limit' => $limit,
                'offset' => $offset,
                'orderby' => 'date',
                'order' => 'DESC',
            )
        );

        if (empty($orders)) {
            return 0;
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

        return count($orders);
    }
}
