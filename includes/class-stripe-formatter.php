<?php

namespace Stripe_Net_Revenue;

final class StripeFormatter {

	public function format_amount( $amount, $currency ) {
		return self::format_currency( $amount, $currency );
	}

	public static function format_currency( $amount_cents, $currency = 'USD' ) {
		// Convert cents to dollars (assuming non-zero-decimal currency for MVP)
		$amount = $amount_cents / 100;

		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount, array( 'currency' => $currency ) );
		}

		return '$' . number_format( $amount, 2 );
	}

	public function format_date( $timestamp ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
