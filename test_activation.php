<?php
/**
 * Comprehensive test to simulate WordPress plugin activation
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== WordPress Plugin Activation Test ===\n\n";

// Define WordPress constants
define('ABSPATH', __DIR__ . '/');
define('WPINC', 'wp-includes');

// Mock WordPress functions
function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function plugin_basename($file) {
    return basename(dirname($file)) . '/' . basename($file);
}

function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
    echo "  ✓ add_action('$tag')\n";
    return true;
}

function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    echo "  ✓ add_filter('$tag')\n";
    return true;
}

function __($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function esc_html__($text, $domain = 'default') {
    return htmlspecialchars($text);
}

function esc_html_e($text, $domain = 'default') {
    echo htmlspecialchars($text);
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES);
}

function is_admin() {
    return true;
}

function get_option($option, $default = false) {
    return $default;
}

function register_setting($option_group, $option_name, $args = array()) {
    return true;
}

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
    return true;
}

function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

function settings_fields($option_group) {
    echo "<!-- settings_fields -->";
}

function do_settings_sections($page) {
    echo "<!-- do_settings_sections -->";
}

function submit_button() {
    echo '<input type="submit" value="Save Changes" />';
}

function wc_get_order($order_id) {
    return null;
}

function get_transient($transient) {
    return false;
}

function set_transient($transient, $value, $expiration = 0) {
    return true;
}

function date_i18n($format, $timestamp_with_offset = false, $gmt = false) {
    return date($format, $timestamp_with_offset ?: time());
}

// Mock WooCommerce
class WooCommerce {
    public static function instance() {
        return new self();
    }
}

echo "Step 1: Loading plugin file...\n";
try {
    require_once __DIR__ . '/stripe-auditor.php';
    echo "  ✓ Plugin file loaded successfully\n\n";
} catch (Throwable $e) {
    echo "  ✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "    File: " . $e->getFile() . "\n";
    echo "    Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Step 2: Checking if classes are loaded...\n";
$classes = [
    'Stripe_Net_Revenue\\Settings',
    'Stripe_Net_Revenue\\Columns',
    'Stripe_Net_Revenue\\StripeConnector',
    'Stripe_Net_Revenue\\StripeFetcher',
    'Stripe_Net_Revenue\\StripeFormatter',
    'Stripe\\StripeClient'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ $class\n";
    } else {
        echo "  ✗ $class NOT FOUND\n";
        exit(1);
    }
}

echo "\nStep 3: Simulating plugin initialization (snrfa_init)...\n";
try {
    snrfa_init();
    echo "  ✓ Plugin initialized successfully\n\n";
} catch (Throwable $e) {
    echo "  ✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "    File: " . $e->getFile() . "\n";
    echo "    Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Step 4: Testing Settings link function...\n";
try {
    $links = ['deactivate' => 'Deactivate'];
    $result = snrfa_add_settings_link($links);
    if (is_array($result) && count($result) > 1) {
        echo "  ✓ Settings link added successfully\n\n";
    } else {
        echo "  ✗ Settings link function failed\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "  ✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "    File: " . $e->getFile() . "\n";
    echo "    Line: " . $e->getLine() . "\n";
    exit(1);
}

echo "=== ALL TESTS PASSED ===\n";
echo "✓ Plugin can be activated without fatal errors\n";
