<?php
/**
 * Uninstall handler for Stripe Net Revenue Auditor.
 *
 * WordPress runs this file when the plugin is deleted from the admin.
 *
 * IMPORTANT:
 * - This intentionally does NOT delete per-order meta by default, because that can be expensive on large stores.
 * - If you need to remove order meta too, uncomment the relevant section below (or provide a Pro cleanup tool).
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete plugin options.
delete_option('snrfa_stripe_secret_key');

// Defensive cleanup for cursor option names (older/newer builds).
delete_option('snrfa_clear_all_cursor');
delete_option('snrfa_clear_all_last_id');
delete_option('snrfa_warm_cursor');
delete_option('snrfa_warm_last_id');

// Unschedule background warm cron if present.
if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
    $hook = 'snrfa_warm_cache_cron';
    // Unschedule all occurrences.
    while (true) {
        $ts = wp_next_scheduled($hook);
        if (!$ts) {
            break;
        }
        wp_unschedule_event($ts, $hook);
    }
}

// Best-effort cleanup of transients stored in the options table.
// Note: This only clears transients stored in wp_options. If an external object cache is used,
// those entries will expire naturally.
global $wpdb;
if (
    !empty($wpdb)
    && is_object($wpdb)
    && isset($wpdb->options)
    && method_exists($wpdb, 'esc_like')
    && method_exists($wpdb, 'prepare')
    && method_exists($wpdb, 'query')
) {
    $like = $wpdb->esc_like('_transient_snrfa_txn_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));

    $like_timeout = $wpdb->esc_like('_transient_timeout_snrfa_txn_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout));
}

/*
// Optional: Remove cached order meta (can be expensive on stores with lots of orders).
// if ( function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_order' ) ) {
// 	$meta_key = '_snrfa_stripe_net_revenue';
// 	$cursor   = array();
// 	$limit    = 200;
// 	while ( true ) {
// 		$args = array(
// 			'limit'   => $limit,
// 			'orderby' => 'date',
// 			'order'   => 'DESC',
// 		);
// 		if ( ! empty( $cursor['ts'] ) ) {
// 			$dt = gmdate( 'Y-m-d H:i:s', (int) $cursor['ts'] );
// 			$args['date_created'] = '<=' . $dt;
// 		}
// 		$orders = wc_get_orders( $args );
// 		if ( empty( $orders ) ) {
// 			break;
// 		}
// 		foreach ( $orders as $order ) {
// 			if ( is_object( $order ) && method_exists( $order, 'delete_meta_data' ) && method_exists( $order, 'save' ) ) {
// 				$order->delete_meta_data( $meta_key );
// 				$order->save();
// 			}
// 		}
// 		$last = end( $orders );
// 		if ( ! is_object( $last ) || ! method_exists( $last, 'get_date_created' ) ) {
// 			break;
// 		}
// 		$dc = $last->get_date_created();
// 		if ( ! $dc || ! method_exists( $dc, 'getTimestamp' ) ) {
// 			break;
// 		}
// 		$cursor['ts'] = (int) $dc->getTimestamp();
// 	}
// }
*/
