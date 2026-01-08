<?php
/**
 * Test settings page access WITHOUT WooCommerce
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Settings Access Test (WITHOUT WooCommerce) ===\n\n";

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
    echo "  ✓ Submenu page added:\n";
    echo "    - Title: '$menu_title'\n";
    echo "    - Parent: '$parent_slug'\n";
    echo "    - Capability: $capability\n";
    return true;
}

echo "Loading plugin WITHOUT WooCommerce class...\n";
echo "-------------------------------------------\n";

// DO NOT define WooCommerce class
echo "WooCommerce class exists: " . (class_exists('WooCommerce') ? 'YES' : 'NO') . "\n\n";

require_once __DIR__ . '/stripe-auditor.php';
snrfa_init();

echo "\nResult:\n";
if (count($submenu_pages) > 0) {
    echo "  ✗ ERROR: Settings page should NOT be registered without WooCommerce!\n";
    echo "    Found: " . $submenu_pages[0]['parent'] . "\n";
} else {
    echo "  ✓ CORRECT: Settings page was NOT registered (WooCommerce is required)\n";
    echo "  ✓ Plugin correctly prevents initialization without WooCommerce\n";
}

echo "\n=== TEST COMPLETED ===\n";
