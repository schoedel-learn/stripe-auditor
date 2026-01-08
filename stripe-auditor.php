<?php
/**
 * Plugin Name: Stripe Net Revenue Auditor
 * Description: See your true net profit by automatically deducting Stripe fees in WooCommerce.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: snrfa
 */

defined( 'ABSPATH' ) || exit;

// 1. Load Composer Autoloader
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
} else {
	// Autoloader not found - show admin notice and exit
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>Stripe Net Revenue Auditor:</strong> Composer autoloader not found. Please run <code>composer install</code> in the plugin directory.';
		echo '</p></div>';
	} );
	return;
}

// 2. Initialize the Plugin
function snrfa_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		// Show admin notice that WooCommerce is required
		add_action( 'admin_notices', 'snrfa_woocommerce_missing_notice' );
		return;
	}

	// Verify required classes are loaded
	if ( ! class_exists( 'Stripe_Net_Revenue\\Integrations\\WooCommerce_Settings' ) ||
	     ! class_exists( 'Stripe_Net_Revenue\\Integrations\\WooCommerce_Integration' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Stripe Net Revenue Auditor:</strong> Required classes not found. Please reinstall the plugin.';
			echo '</p></div>';
		} );
		return;
	}

	if ( is_admin() ) {
		// Load WooCommerce Settings and Integration
		Stripe_Net_Revenue\Integrations\WooCommerce_Settings::get_instance();
		new Stripe_Net_Revenue\Integrations\WooCommerce_Integration();
	}
}
add_action( 'plugins_loaded', 'snrfa_init' );

/**
 * Display admin notice when WooCommerce is not active
 */
function snrfa_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong>Stripe Net Revenue Auditor</strong> requires WooCommerce to be installed and activated.
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">Install WooCommerce</a>
		</p>
	</div>
	<?php
}

/**
 * Add Settings link to Plugin Action Links
 */
function snrfa_add_settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=snrfa-settings">' . __( 'Settings', 'snrfa' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'snrfa_add_settings_link' );
