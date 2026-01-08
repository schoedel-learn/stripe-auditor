<?php
/**
 * Test settings page access with and without WooCommerce
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Settings Access Test ===\n\n";

// Define WordPress constants
define('ABSPATH', __DIR__ . '/');

// Mock WordPress functions
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
function __($text, $domain = 'default') { return $text; }
function esc_html__($text, $domain = 'default') { return htmlspecialchars($text); }
function esc_html_e($text, $domain = 'default') { echo htmlspecialchars($text); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function is_admin() { return true; }
function get_option($option, $default = false) { return $default; }
function register_setting($option_group, $option_name, $args = array()) { return true; }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function settings_fields($option_group) { echo "<!-- settings_fields -->"; }
function do_settings_sections($page) { echo "<!-- do_settings_sections -->"; }
function submit_button() { echo '<input type="submit" value="Save Changes" />'; }

$submenu_pages = [];

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
    global $submenu_pages;
    $submenu_pages[] = [
        'parent' => $parent_slug,
        'title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'slug' => $menu_slug
    ];
    echo "  ✓ Submenu page added: '$menu_title' under '$parent_slug' (requires: $capability)\n";
    return true;
}

// Test 1: Without WooCommerce
echo "Test 1: Loading plugin WITHOUT WooCommerce\n";
echo "-------------------------------------------\n";
require_once __DIR__ . '/stripe-auditor.php';
snrfa_init();

// Manually trigger admin_menu hook to simulate WordPress
echo "Triggering admin_menu hook...\n";
$settings = Stripe_Net_Revenue\Settings::get_instance();
$settings->add_settings_page();

if (count($submenu_pages) > 0) {
    echo "\n  Settings page registered successfully!\n";
    echo "  Parent menu: " . $submenu_pages[0]['parent'] . "\n";
    echo "  Required capability: " . $submenu_pages[0]['capability'] . "\n";
} else {
    echo "\n  ✗ ERROR: Settings page was NOT registered!\n";
}

// Test 2: With WooCommerce
echo "\n\nTest 2: Simulating WITH WooCommerce\n";
echo "-------------------------------------------\n";

// Reset
$submenu_pages = [];

// Mock WooCommerce
class WooCommerce {
    public static function instance() { return new self(); }
}

// Reinitialize Settings to test with WooCommerce
$settings = Stripe_Net_Revenue\Settings::get_instance();
$settings->add_settings_page();

if (count($submenu_pages) > 0) {
    echo "\n  Settings page registered successfully!\n";
    echo "  Parent menu: " . $submenu_pages[0]['parent'] . "\n";
    echo "  Required capability: " . $submenu_pages[0]['capability'] . "\n";
} else {
    echo "\n  ✗ ERROR: Settings page was NOT registered!\n";
}

echo "\n=== ALL TESTS COMPLETED ===\n";
