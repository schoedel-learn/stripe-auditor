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

use Stripe_Net_Revenue\Admin_Diagnostics;

abstract class Abstract_Settings
{

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
    public static function get_instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Constructor - sets up hooks
     */
    protected function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'), 50);
        add_action('admin_init', array($this, 'register_settings'));
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
    protected function get_page_title()
    {
        return __('Stripe Net Revenue Auditor', 'snrfa');
    }

    /**
     * Get the menu title for the settings page
     *
     * @return string
     */
    protected function get_menu_title()
    {
        return __('Stripe Auditor', 'snrfa');
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting($this->option_group, $this->option_name, array(
                'sanitize_callback' => array($this, 'sanitize_api_key'),
        ));
    }

    /**
     * Sanitize the API key
     *
     * @param string $key The API key to sanitize
     * @return string
     */
    public function sanitize_api_key($key)
    {
        return sanitize_text_field(trim($key));
    }

    /**
     * Render the settings page
     *
     * UI lint (flat/minimal WP-native):
     * - Prefer WP admin classes/components (wrap, form-table, widefat, notices)
     * - Keep actions minimal (page-title-action)
     * - Escape output (esc_html/esc_attr/esc_url)
     * - Avoid heavy custom CSS/layout
     */
    public function render_settings_page()
    {
        $support_url = '';
        if (function_exists('snrfa_get_support_url')) {
            $support_url = snrfa_get_support_url();
        }

        $cache_clear_url = '';
        $cache_clear_all_url = '';
        $cache_warm_url = '';
        $cache_warm_start_url = '';
        $cache_warm_stop_url = '';
        if (function_exists('wp_nonce_url') && function_exists('admin_url')) {
            $cache_clear_url = wp_nonce_url(
                    admin_url('admin-post.php?action=snrfa_clear_cache'),
                    'snrfa_clear_cache'
            );
            $cache_clear_all_url = wp_nonce_url(
                    admin_url('admin-post.php?action=snrfa_clear_cache_all&confirm=1'),
                    'snrfa_clear_cache_all'
            );
            $cache_warm_url = wp_nonce_url(
                    admin_url('admin-post.php?action=snrfa_warm_cache'),
                    'snrfa_warm_cache'
            );
            $cache_warm_start_url = wp_nonce_url(
                    admin_url('admin-post.php?action=snrfa_warm_cache_start'),
                    'snrfa_warm_cache_start'
            );
            $cache_warm_stop_url = wp_nonce_url(
                    admin_url('admin-post.php?action=snrfa_warm_cache_stop'),
                    'snrfa_warm_cache_stop'
            );
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($this->get_page_title()); ?></h1>
            <?php if (!empty($support_url)) : ?>
                <a class="page-title-action" href="<?php echo esc_url($support_url); ?>" target="_blank"
                   rel="noopener noreferrer">
                    <?php esc_html_e('Support', 'snrfa'); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($cache_clear_url)) : ?>
                <a class="page-title-action" href="<?php echo esc_url($cache_clear_url); ?>">
                    <?php esc_html_e('Clear Stripe Net cache', 'snrfa'); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($cache_warm_url)) : ?>
                <a class="page-title-action" href="<?php echo esc_url($cache_warm_url); ?>"
                   onclick="return confirm('<?php echo esc_js(__('This will warm the Stripe Net cache by processing orders in batches. It may take a while on large stores. Continue?', 'snrfa')); ?>');">
                    <?php esc_html_e('Warm Stripe Net cache', 'snrfa'); ?>
                </a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php
            // Diagnostics panel.
            if (class_exists('\\Stripe_Net_Revenue\\Admin_Diagnostics')) {
                $items = Admin_Diagnostics::get_items();
                if (!empty($items)) {
                    echo '<div class="notice notice-info">';
                    echo '<p><strong>' . esc_html__('Diagnostics', 'snrfa') . '</strong></p>';
                    echo '<table class="widefat striped">';
                    echo '<tbody>';
                    foreach ($items as $item) {
                        echo '<tr>';
                        echo '<td class="column-primary"><strong>' . esc_html($item['label']) . '</strong></td>';
                        echo '<td>' . esc_html($item['value']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                    echo '<p class="snrfa-help">' . esc_html__('Tip: if the orders list feels slow, make sure object caching is enabled and try clearing the cache after changing keys.', 'snrfa') . '</p>';
                    echo '</div>';
                }
            }
            ?>

            <?php if (!empty($cache_clear_all_url) || !empty($cache_warm_start_url) || !empty($cache_warm_stop_url)) : ?>
                <div class="snrfa-actions">
                    <?php if (!empty($cache_clear_all_url)) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($cache_clear_all_url); ?>"
                           onclick="return confirm('<?php echo esc_js(__('Warning: this will clear cached Stripe Net data for ALL orders. This may take a while on large stores. Continue?', 'snrfa')); ?>');">
                            <?php esc_html_e('Clear ALL Stripe Net cache', 'snrfa'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($cache_warm_start_url)) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($cache_warm_start_url); ?>"
                           onclick="return confirm('<?php echo esc_js(__('This will start a background cache warm job using WP-Cron. Your site must receive traffic for WP-Cron to run. Continue?', 'snrfa')); ?>');">
                            <?php esc_html_e('Start background warm cache', 'snrfa'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($cache_warm_stop_url)) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($cache_warm_stop_url); ?>"
                           onclick="return confirm('<?php echo esc_js(__('This will stop the background cache warm job. Continue?', 'snrfa')); ?>');">
                            <?php esc_html_e('Stop background warm cache', 'snrfa'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields($this->option_group); ?>
                <?php do_settings_sections($this->option_group); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Stripe Secret Key (sk_live_...)', 'snrfa'); ?></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr($this->option_name); ?>"
                                   value="<?php echo esc_attr(get_option($this->option_name)); ?>"
                                   class="regular-text"/>
                            <p class="description"><?php esc_html_e('Enter your Stripe Secret Key to fetch transaction fees and net revenue.', 'snrfa'); ?></p>
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
    public function get_api_key()
    {
        return get_option($this->option_name);
    }
}
