<?php
// Test to identify the actual error - comprehensive test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Loading autoloader...\n";
require_once 'vendor/autoload.php';
echo "Autoloader loaded.\n";

// Mock ALL WordPress functions used in the plugin
function get_option($option, $default = false) {
    echo "  get_option('$option') called\n";
    return $default;
}
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
    echo "  add_action('$tag') called\n";
}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    echo "  add_filter('$tag') called\n";
}
function __($text, $domain) { return $text; }
function esc_html__($text, $domain) { return $text; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename($file); }
function is_admin() { return true; }
function wc_get_order($id) { return null; }
function get_transient($key) { return false; }
function set_transient($key, $value, $exp) { return true; }
function sanitize_text_field($str) { return $str; }
function register_setting($group, $name, $args = []) {}
function add_submenu_page($parent, $title, $menu, $cap, $slug, $cb = '', $pos = null) {}
function settings_fields($group) {}
function do_settings_sections($page) {}
function submit_button() {}
function esc_attr($text) { return $text; }
function esc_html_e($text, $domain) { echo $text; }
function date_i18n($format, $timestamp = false, $gmt = false) { return date($format, $timestamp); }

class WooCommerce {
    public static function instance() { return new self(); }
}

// Check if classes exist
echo "\nChecking if classes are autoloaded:\n";
echo "  Stripe_Net_Revenue\\Settings: " . (class_exists('Stripe_Net_Revenue\\Settings') ? 'YES' : 'NO') . "\n";
echo "  Stripe_Net_Revenue\\Columns: " . (class_exists('Stripe_Net_Revenue\\Columns') ? 'YES' : 'NO') . "\n";
echo "  Stripe_Net_Revenue\\StripeConnector: " . (class_exists('Stripe_Net_Revenue\\StripeConnector') ? 'YES' : 'NO') . "\n";
echo "  Stripe_Net_Revenue\\StripeFetcher: " . (class_exists('Stripe_Net_Revenue\\StripeFetcher') ? 'YES' : 'NO') . "\n";
echo "  Stripe_Net_Revenue\\StripeFormatter: " . (class_exists('Stripe_Net_Revenue\\StripeFormatter') ? 'YES' : 'NO') . "\n";
echo "  Stripe\\StripeClient: " . (class_exists('Stripe\\StripeClient') ? 'YES' : 'NO') . "\n";

// Try to instantiate in the same order as the plugin
echo "\nCreating Settings instance...\n";
try {
    $settings = Stripe_Net_Revenue\Settings::get_instance();
    echo "Settings created successfully.\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}

echo "\nCreating Columns instance...\n";
try {
    $columns = new Stripe_Net_Revenue\Columns();
    echo "Columns created successfully.\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nAll tests passed!\n";
