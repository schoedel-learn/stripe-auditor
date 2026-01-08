<?php

namespace Stripe_Net_Revenue;

use Stripe\StripeClient;

final class StripeConnector {
    private static $instance = null;
    private $stripe_client = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Retrieve the key from our Settings page
        $api_key = get_option( 'snrfa_stripe_secret_key' );

        if ( $api_key ) {
            // Initialize Stripe - check if class exists first
            if ( class_exists( 'Stripe\\StripeClient' ) ) {
                $this->stripe_client = new StripeClient( $api_key );
            }
        }
    }

    public function get_client() {
        return $this->stripe_client;
    }
}
