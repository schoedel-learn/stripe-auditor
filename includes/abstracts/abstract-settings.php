<?php
/**
 * Abstract Settings Base Class
 *
 * This class provides the foundation for platform-specific settings pages.
 * Each platform (WooCommerce, EDD, SureCart) will extend this class.
 *
 * @package Stripe_Net_Revenue
 */

namespace Stripe_Net_Revenue\Abstracts;

abstract class Abstract_Settings {

	/**
	 * Instance of this class.
	 *
	 * @var Abstract_Settings
	 */
	protected static $instance = null;

	/**
	 * Option name for storing the Stripe API key
	 *
	 * @var string
	 */
	protected $option_name = 'snrfa_stripe_secret_key';

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	protected $page_slug = 'snrfa-settings';

	/**
	 * Settings option group
	 *
	 * @var string
	 */
	protected $option_group = 'snrfa_options_group';

	/**
	 * Get instance of this class.
	 *
	 * @return Abstract_Settings
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Constructor - sets up hooks
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 50 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu
	 * Must be implemented by child class to specify parent menu
	 */
	abstract public function add_settings_page();

	/**
	 * Get the page title for the settings page
	 *
	 * @return string
	 */
	protected function get_page_title() {
		return __( 'Stripe Net Revenue Auditor', 'snrfa' );
	}

	/**
	 * Get the menu title for the settings page
	 *
	 * @return string
	 */
	protected function get_menu_title() {
		return __( 'Stripe Auditor', 'snrfa' );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( $this->option_group, $this->option_name, array(
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
		) );
	}

	/**
	 * Sanitize the API key
	 *
	 * @param string $key The API key to sanitize
	 * @return string
	 */
	public function sanitize_api_key( $key ) {
		return sanitize_text_field( trim( $key ) );
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( $this->option_group ); ?>
				<?php do_settings_sections( $this->option_group ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Stripe Secret Key (sk_live_...)', 'snrfa' ); ?></th>
						<td>
							<input type="password" name="<?php echo esc_attr( $this->option_name ); ?>" value="<?php echo esc_attr( get_option( $this->option_name ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Enter your Stripe Secret Key to fetch transaction fees and net revenue.', 'snrfa' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the stored API key
	 *
	 * @return string
	 */
	public function get_api_key() {
		return get_option( $this->option_name );
	}
}
