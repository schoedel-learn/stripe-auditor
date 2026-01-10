<?php
/**
 * Plugin Name: Stripe Net Revenue Auditor
 * Description: See your true net profit by automatically deducting Stripe fees in WooCommerce.
 * Version: 1.0.0
 * Author: Schoedel Design AI
 * Author URI: https://schoedel.design
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: snrfa
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Core constants for add-ons and compatibility checks.
if (!defined('SNRFA_VERSION')) {
    define('SNRFA_VERSION', '1.0.0');
}
if (!defined('SNRFA_PLUGIN_FILE')) {
    define('SNRFA_PLUGIN_FILE', __FILE__);
}
if (!defined('SNRFA_WEBSITE_URL')) {
    // Support / tickets portal.
    define('SNRFA_WEBSITE_URL', 'https://help.opshub.app/');
}

// ---- Core API (for Pro/add-ons) ----

/**
 * Whether a Pro add-on is currently active.
 *
 * Pro add-ons should define SNRFA_PRO_VERSION and/or the Stripe_Net_Revenue_Pro\\Bootstrap class.
 *
 * @return bool
 */
function snrfa_is_pro_active()
{
    return (defined('SNRFA_PRO_VERSION') && SNRFA_PRO_VERSION) || class_exists('Stripe_Net_Revenue_Pro\\Bootstrap');
}

/**
 * Return the active Pro version if present.
 *
 * @return string
 */
function snrfa_get_pro_version()
{
    return defined('SNRFA_PRO_VERSION') ? (string)SNRFA_PRO_VERSION : '';
}

/**
 * Filterable gate for whether a Stripe API call should be performed in the current context.
 *
 * Pro add-ons can return false to prevent Stripe calls on list screens, etc.
 *
 * @param bool $default
 * @param array $context
 * @return bool
 */
function snrfa_stripe_call_allowed($default, $context = array())
{
    return (bool)apply_filters('snrfa_stripe_call_allowed', (bool)$default, (array)$context);
}

/**
 * Fired after the free core loads its constants.
 *
 * Pro/add-ons can hook into this early to register filters.
 */
if (function_exists('do_action')) {
    do_action('snrfa_core_loaded');
}

/**
 * Get the support URL for this plugin.
 *
 * This is used in readme/docs and can be reused by Pro add-ons.
 *
 * @return string
 */
function snrfa_get_support_url()
{
    /**
     * Filter the support URL used by Stripe Net Revenue Auditor.
     *
     * @param string $url Default support URL.
     */
    return apply_filters('snrfa_support_url', SNRFA_WEBSITE_URL);
}

/**
 * Runtime flag for whether bundled dependencies are available.
 *
 * A WordPress.org release zip should include `vendor/`.
 * This is a soft-fail mechanism for incomplete installs.
 *
 * @var bool
 */
$GLOBALS['snrfa_vendor_loaded'] = false;

/**
 * Check whether the plugin’s bundled dependencies were loaded.
 *
 * @return bool
 */
function snrfa_vendor_loaded()
{
    return !empty($GLOBALS['snrfa_vendor_loaded']);
}

// 1. Load Composer Autoloader (bundled in the plugin release).
$snrfa_autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($snrfa_autoload_path)) {
    require_once $snrfa_autoload_path;
    $GLOBALS['snrfa_vendor_loaded'] = true;
} else {
    // Dependencies missing. Don’t tell users to run Composer; a WP.org release should bundle vendor/.
    add_action(
            'admin_notices',
            function () {
                if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html__('Stripe Net Revenue Auditor', 'snrfa') . ':</strong> ';
                echo esc_html__('This installation is missing required dependencies. Please reinstall the plugin from a complete release package.', 'snrfa');
                echo '</p></div>';
            }
    );
}

// Load translations.
function snrfa_load_textdomain()
{
    load_plugin_textdomain('snrfa', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'snrfa_load_textdomain');

// 2. Initialize the Plugin
function snrfa_init()
{
    // If dependencies weren’t loaded, don’t attempt to boot integrations.
    if (!snrfa_vendor_loaded()) {
        return;
    }

    // Register admin helpers (cache tools, diagnostics, reports).
    if (is_admin()) {
        if (class_exists('Stripe_Net_Revenue\\Admin_Cache')) {
            Stripe_Net_Revenue\Admin_Cache::register();
        }
        if (class_exists('Stripe_Net_Revenue\\Admin_Report')) {
            add_action('admin_menu', array('Stripe_Net_Revenue\\Admin_Report', 'register_menu'), 60);
        }
    }

    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        // Show admin notice that WooCommerce is required
        add_action('admin_notices', 'snrfa_woocommerce_missing_notice');
        return;
    }

    // Verify required classes are loaded
    if (!class_exists('Stripe_Net_Revenue\\Integrations\\WooCommerce_Settings') ||
            !class_exists('Stripe_Net_Revenue\\Integrations\\WooCommerce_Integration')) {
        add_action('admin_notices', function () {
            if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Stripe Net Revenue Auditor', 'snrfa') . ':</strong> ';
            echo esc_html__('Required classes not found. Please reinstall the plugin.', 'snrfa');
            echo '</p></div>';
        });
        return;
    }

    if (is_admin()) {
        // Load WooCommerce Settings and Integration
        Stripe_Net_Revenue\Integrations\WooCommerce_Settings::get_instance();
        new Stripe_Net_Revenue\Integrations\WooCommerce_Integration();
    }
}

add_action('plugins_loaded', 'snrfa_init');

/**
 * Display admin notice when WooCommerce is not active
 */
function snrfa_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php echo esc_html__('Stripe Net Revenue Auditor', 'snrfa'); ?></strong>
            <?php echo esc_html__('requires WooCommerce to be installed and activated.', 'snrfa'); ?>
            <a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>">
                <?php echo esc_html__('Install WooCommerce', 'snrfa'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Add action links to the Plugins screen.
 *
 * - Settings: Only when WooCommerce is active (settings page is registered under WooCommerce)
 * - Support: Always shown; points to a filterable support URL
 * - Pro/Add-ons: Always shown; currently points at the support portal (update to a separate add-ons page later if desired)
 */
function snrfa_add_settings_link($links)
{
    // Settings link only makes sense if WooCommerce is active.
    if (class_exists('WooCommerce')) {
        $settings_url = admin_url('admin.php?page=snrfa-settings');
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'snrfa') . '</a>';
        array_unshift($links, $settings_link);
    } else {
        $plugins_url = admin_url('plugins.php');
        $plugins_link = '<a href="' . esc_url($plugins_url) . '">' . esc_html__('WooCommerce required', 'snrfa') . '</a>';
        array_unshift($links, $plugins_link);
    }

    // Support link.
    $support_url = function_exists('snrfa_get_support_url') ? snrfa_get_support_url() : '';
    $support_link = '';
    if (!empty($support_url)) {
        $support_link = '<a href="' . esc_url($support_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support', 'snrfa') . '</a>';
    }

    // Pro/Add-ons link (currently same destination as support portal).
    $pro_url = defined('SNRFA_WEBSITE_URL') ? SNRFA_WEBSITE_URL : $support_url;
    $pro_link = '';
    if (!empty($pro_url)) {
        // If you later publish a separate add-ons landing page, update this link to point there.
        $pro_link = '<a href="' . esc_url($pro_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Pro / Add-ons', 'snrfa') . '</a>';
    }

    // Append secondary links (keep Settings first).
    if ($support_link) {
        $links[] = $support_link;
    }
    if ($pro_link) {
        $links[] = $pro_link;
    }

    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'snrfa_add_settings_link');

/**
 * Enqueue minimal admin CSS for plugin screens.
 */
function snrfa_enqueue_admin_assets($hook)
{
    // Only enqueue on our settings page and report page.
    $allowed = array(
            'woocommerce_page_snrfa-settings',
            'woocommerce_page_snrfa-net-revenue-report',
        // WooCommerce Orders list (legacy + HPOS)
            'edit.php',
            'woocommerce_page_wc-orders',
    );
    if (!in_array((string)$hook, $allowed, true)) {
        return;
    }

    // Narrow edit.php further: only enqueue on shop_order list.
    if ('edit.php' === (string)$hook && isset($_GET['post_type']) && 'shop_order' !== (string)$_GET['post_type']) {
        return;
    }

    wp_enqueue_style(
            'snrfa-admin',
            plugin_dir_url(__FILE__) . 'includes/admin.css',
            array(),
            defined('SNRFA_VERSION') ? SNRFA_VERSION : '1.0.0'
    );
}

add_action('admin_enqueue_scripts', 'snrfa_enqueue_admin_assets');

