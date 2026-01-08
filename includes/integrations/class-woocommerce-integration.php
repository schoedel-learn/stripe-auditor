<?php
/**
 * WooCommerce Integration Class
 *
 * Adds Stripe net revenue columns to WooCommerce order lists.
 * Supports both legacy post-based orders and HPOS (High Performance Order Storage).
 *
 * @package Stripe_Net_Revenue
 */

namespace Stripe_Net_Revenue\Integrations;

use Stripe_Net_Revenue\Abstracts\Abstract_Integration;

final class WooCommerce_Integration extends Abstract_Integration {

	/**
	 * Register WooCommerce-specific hooks
	 */
	protected function register_hooks() {
		// Legacy post-based orders
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column_header' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'populate_column_content' ), 10, 2 );

		// HPOS Support (High Performance Order Storage)
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column_header' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'populate_column_content_hpos' ), 10, 2 );
	}

	/**
	 * Add column header to WooCommerce order list
	 *
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	public function add_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			// Insert after 'order_total' column
			if ( 'order_total' === $key ) {
				$new_columns[ $this->get_column_name() ] = $this->get_column_label();
			}
		}
		return $new_columns;
	}

	/**
	 * Populate column content for legacy post-based orders
	 *
	 * @param string $column Column name
	 * @param int    $post_id Post ID
	 */
	public function populate_column_content( $column, $post_id ) {
		if ( $this->get_column_name() === $column ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				$this->render_cell_data( $order );
			}
		}
	}

	/**
	 * Populate column content for HPOS orders
	 *
	 * @param string $column Column name
	 * @param object $order  WC_Order object
	 */
	public function populate_column_content_hpos( $column, $order ) {
		if ( $this->get_column_name() === $column ) {
			$this->render_cell_data( $order );
		}
	}

	/**
	 * Get the Stripe charge ID from a WooCommerce order
	 *
	 * @param mixed $order WooCommerce order object
	 * @return string|null The Stripe charge ID or null
	 */
	protected function get_charge_id( $order ) {
		if ( ! $order ) {
			return null;
		}
		return $order->get_transaction_id();
	}
}
