<?php

namespace Stripe_Net_Revenue;

final class Settings {

	/**
	 * Instance of this class.
	 *
	 * @var Settings
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 50 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Stripe Auditor', 'snrfa' ),
			__( 'Stripe Auditor', 'snrfa' ),
			'manage_options',
			'snrfa-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'snrfa_options_group', 'snrfa_stripe_secret_key', array(
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
		) );
	}

	/**
	 * Sanitize the API key
	 */
	public function sanitize_api_key( $key ) {
		return sanitize_text_field( trim( $key ) );
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stripe Net Revenue Auditor', 'snrfa' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'snrfa_options_group' ); ?>
				<?php do_settings_sections( 'snrfa_options_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Stripe Secret Key (sk_live_...)', 'snrfa' ); ?></th>
						<td>
							<input type="password" name="snrfa_stripe_secret_key" value="<?php echo esc_attr( get_option( 'snrfa_stripe_secret_key' ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Enter your Stripe Secret Key to fetch transaction fees and net revenue.', 'snrfa' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function get_api_key() {
		return get_option( 'snrfa_stripe_secret_key' );
	}
}
