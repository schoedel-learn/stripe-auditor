<?php
/**
 * WooCommerce Settings Class
 *
 * Extends the abstract settings class to add settings page under WooCommerce menu.
 *
 * @package Stripe_Net_Revenue
 */

namespace Stripe_Net_Revenue\Integrations;

use Stripe_Net_Revenue\Abstracts\Abstract_Settings;

final class WooCommerce_Settings extends Abstract_Settings {

	/**
	 * Add settings page under WooCommerce menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			$this->get_page_title(),
			$this->get_menu_title(),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}
}
