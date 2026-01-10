<?php

namespace Stripe_Net_Revenue;

/**
 * Admin diagnostics helpers.
 *
 * Centralizes small “status” checks used in the settings UI.
 */
final class Admin_Diagnostics
{

    /**
     * Build a set of diagnostic items for display.
     *
     * @return array<int, array{label:string,value:string,type:string}>
     */
    public static function get_items()
    {
        $items = array();

        $items[] = array(
            'label' => __('WooCommerce active', 'snrfa'),
            'value' => class_exists('WooCommerce') ? __('Yes', 'snrfa') : __('No', 'snrfa'),
            'type' => class_exists('WooCommerce') ? 'good' : 'bad',
        );

        $vendor_loaded = function_exists('snrfa_vendor_loaded') ? snrfa_vendor_loaded() : false;
        $items[] = array(
            'label' => __('Dependencies loaded', 'snrfa'),
            'value' => $vendor_loaded ? __('Yes', 'snrfa') : __('No', 'snrfa'),
            'type' => $vendor_loaded ? 'good' : 'bad',
        );

        $key = get_option('snrfa_stripe_secret_key');
        $items[] = array(
            'label' => __('Stripe key saved', 'snrfa'),
            'value' => !empty($key) ? __('Yes', 'snrfa') : __('No', 'snrfa'),
            'type' => !empty($key) ? 'good' : 'warn',
        );

        // Cache strategy summary.
        $items[] = array(
            'label' => __('Caching strategy', 'snrfa'),
            'value' => __('Order meta + transients (order list optimized)', 'snrfa'),
            'type' => 'good',
        );

        $items[] = array(
            'label' => __('Core version', 'snrfa'),
            'value' => defined('SNRFA_VERSION') ? SNRFA_VERSION : __('Unknown', 'snrfa'),
            'type' => 'neutral',
        );

        return $items;
    }
}

