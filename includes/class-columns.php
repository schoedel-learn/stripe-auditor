<?php

namespace Stripe_Net_Revenue;

final class Columns {

	private $fetcher;

	public function __construct() {
		$connector     = StripeConnector::get_instance();
		$this->fetcher = new StripeFetcher( $connector );

		// Register Columns
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column_header' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'populate_column_content' ), 10, 2 );

		// HPOS Support (High Performance Order Storage)
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column_header' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'populate_column_content_hpos' ), 10, 2 );
	}

	public function add_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( 'order_total' === $key ) {
				$new_columns['snrfa_net_revenue'] = __( 'Stripe Net', 'snrfa' );
			}
		}
		return $new_columns;
	}

	public function populate_column_content( $column, $post_id ) {
		if ( 'snrfa_net_revenue' === $column ) {
			$order = wc_get_order( $post_id );
			$this->render_cell_data( $order );
		}
	}

	public function populate_column_content_hpos( $column, $order ) {
		if ( 'snrfa_net_revenue' === $column ) {
			$this->render_cell_data( $order );
		}
	}

	private function render_cell_data( $order ) {
		// 1. Get the Stripe Charge ID from the Order Meta
		$charge_id = $order->get_transaction_id();

		if ( empty( $charge_id ) ) {
			echo '<span style="color:#aaa;">' . esc_html__( 'No Stripe ID', 'snrfa' ) . '</span>';
			return;
		}

		// 2. Try to get cached data from Transient
		$cache_key = 'snrfa_txn_' . $charge_id;
		$txn_data  = get_transient( $cache_key );

		if ( false === $txn_data ) {
			// 3. Fetch Real Data from Stripe if not cached
			$txn = $this->fetcher->get_transaction_details( $charge_id );

			if ( $txn ) {
				$txn_data = array(
					'fee'      => $txn->fee,
					'net'      => $txn->net,
					'currency' => $txn->currency,
				);
				// Cache for 24 hours (86400 seconds)
				set_transient( $cache_key, $txn_data, 86400 );
			}
		}

		if ( ! empty( $txn_data ) ) {
			$fee = StripeFormatter::format_currency( $txn_data['fee'], $txn_data['currency'] );
			$net = StripeFormatter::format_currency( $txn_data['net'], $txn_data['currency'] );

			echo '<div style="font-size: 11px;">';
			echo '<span style="color: #a00;">' . esc_html__( 'Fee:', 'snrfa' ) . ' -' . $fee . '</span><br>';
			echo '<span style="color: #46b450; font-weight: bold;">' . esc_html__( 'Net:', 'snrfa' ) . ' ' . $net . '</span>';
			echo '</div>';
		} else {
			echo '<span style="color:#aaa;">' . esc_html__( 'N/A', 'snrfa' ) . '</span>';
		}
	}
}
