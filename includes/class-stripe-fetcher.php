<?php

namespace Stripe_Net_Revenue;

final class StripeFetcher {

	private $connector;

	public function __construct( StripeConnector $connector ) {
		$this->connector = $connector;
	}

	public function fetch_transactions( $limit = 10 ) {
		$client = $this->connector->get_client();
		if ( ! $client ) {
			return array();
		}

		try {
			$charges = $client->charges->all( array( 'limit' => $limit ) );
			return $charges->data;
		} catch ( \Exception $e ) {
			error_log( 'Stripe Auditor Error: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Fetch the Stripe Balance Transaction for a specific Charge ID
	 */
	public function get_transaction_details( $charge_id ) {
		$client = $this->connector->get_client();

		if ( ! $client || empty( $charge_id ) ) {
			return null;
		}

		try {
			// 1. Get the Charge
			$charge = $client->charges->retrieve( $charge_id, array( 'expand' => array( 'balance_transaction' ) ) );

			// 2. Extract the Balance Transaction (where the fee data lives)
			return $charge->balance_transaction;

		} catch ( \Exception $e ) {
			error_log( 'Stripe Fetch Error: ' . $e->getMessage() );
			return null;
		}
	}
}
