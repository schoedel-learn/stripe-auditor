<?php
/**
 * Abstract Integration Base Class
 *
 * This class provides the foundation for platform-specific integrations.
 * Each platform (WooCommerce, EDD, SureCart) will extend this class to add
 * net revenue columns to their order/transaction lists.
 *
 * @package Stripe_Net_Revenue
 */

namespace Stripe_Net_Revenue\Abstracts;

use Stripe_Net_Revenue\StripeConnector;
use Stripe_Net_Revenue\StripeFetcher;
use Stripe_Net_Revenue\StripeFormatter;

abstract class Abstract_Integration {

	/**
	 * Stripe fetcher instance
	 *
	 * @var StripeFetcher
	 */
	protected $fetcher;

	/**
	 * Cache expiration time in seconds (24 hours)
	 *
	 * @var int
	 */
	protected $cache_expiration = 86400;

	/**
	 * Constructor - sets up the fetcher and hooks
	 */
	public function __construct() {
		$connector     = StripeConnector::get_instance();
		$this->fetcher = new StripeFetcher( $connector );

		$this->register_hooks();
	}

	/**
	 * Register platform-specific hooks
	 * Must be implemented by child class
	 */
	abstract protected function register_hooks();

	/**
	 * Add column header to the list table
	 * Must be implemented by child class
	 *
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	abstract public function add_column_header( $columns );

	/**
	 * Get the Stripe charge ID from an order/transaction
	 * Must be implemented by child class
	 *
	 * @param mixed $order The order/transaction object
	 * @return string|null The Stripe charge ID or null
	 */
	abstract protected function get_charge_id( $order );

	/**
	 * Render the net revenue cell data
	 * This is shared across all platforms
	 *
	 * @param mixed $order The order/transaction object
	 */
	protected function render_cell_data( $order ) {
		// 1. Get the Stripe Charge ID
		$charge_id = $this->get_charge_id( $order );

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
				// Cache for configured time
				set_transient( $cache_key, $txn_data, $this->cache_expiration );
			}
		}

		if ( ! empty( $txn_data ) ) {
			$this->render_net_revenue_output( $txn_data );
		} else {
			echo '<span style="color:#aaa;">' . esc_html__( 'N/A', 'snrfa' ) . '</span>';
		}
	}

	/**
	 * Render the formatted net revenue output
	 *
	 * @param array $txn_data Transaction data with fee, net, and currency
	 */
	protected function render_net_revenue_output( $txn_data ) {
		$fee = StripeFormatter::format_currency( $txn_data['fee'], $txn_data['currency'] );
		$net = StripeFormatter::format_currency( $txn_data['net'], $txn_data['currency'] );

		echo '<div style="font-size: 11px;">';
		echo '<span style="color: #a00;">' . esc_html__( 'Fee:', 'snrfa' ) . ' -' . $fee . '</span><br>';
		echo '<span style="color: #46b450; font-weight: bold;">' . esc_html__( 'Net:', 'snrfa' ) . ' ' . $net . '</span>';
		echo '</div>';
	}

	/**
	 * Get the column name/key
	 *
	 * @return string
	 */
	protected function get_column_name() {
		return 'snrfa_net_revenue';
	}

	/**
	 * Get the column header label
	 *
	 * @return string
	 */
	protected function get_column_label() {
		return __( 'Stripe Net', 'snrfa' );
	}
}
