<?php

namespace Stripe_Net_Revenue;

use Exception;

final class StripeFetcher
{

    private $connector;

    public function __construct(StripeConnector $connector)
    {
        $this->connector = $connector;
    }

    public function fetch_transactions($limit = 10)
    {
        $client = $this->connector->get_client();
        if (!$client) {
            return array();
        }

        try {
            $charges = $client->charges->all(array('limit' => $limit));
            return $charges->data;
        } catch (Exception $e) {
            error_log('Stripe Auditor Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Fetch the Stripe Balance Transaction for a specific transaction ID.
     *
     * WooCommerce gateways may store either:
     * - Charge IDs: ch_...
     * - PaymentIntent IDs: pi_...
     *
     * This method resolves pi_... to its latest charge when possible.
     *
     * @param string $transaction_id Charge ID (ch_...) or PaymentIntent ID (pi_...)
     * @return mixed|null Stripe BalanceTransaction object or null on failure
     */
    public function get_transaction_details($transaction_id)
    {
        $client = $this->connector->get_client();

        if (!$client || empty($transaction_id)) {
            return null;
        }

        try {
            // If WooCommerce stored a PaymentIntent ID, resolve to the latest charge.
            if (0 === strpos($transaction_id, 'pi_')) {
                $intent = $client->paymentIntents->retrieve(
                    $transaction_id,
                    array('expand' => array('latest_charge.balance_transaction'))
                );

                if (isset($intent->latest_charge) && isset($intent->latest_charge->balance_transaction)) {
                    return $intent->latest_charge->balance_transaction;
                }

                return null;
            }

            // Default: treat as a Charge ID.
            $charge = $client->charges->retrieve($transaction_id, array('expand' => array('balance_transaction')));

            return $charge->balance_transaction;
        } catch (Exception $e) {
            error_log('Stripe Fetch Error: ' . $e->getMessage());
            return null;
        }
    }
}
