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
function plugin_dir_path($file)
{
    return dirname($file) . '/';
}

function plugin_basename($file)
{
    return basename(dirname($file)) . '/' . basename($file);
}

function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
    echo "  ✓ add_action('$tag')\n";
    return true;
}

function add_filter($tag, $callback, $priority = 10, $accepted_args = 1)
{
    echo "  ✓ add_filter('$tag')\n";
    return true;
}

function __($text, $domain = 'default')
{
    return $text;
}

function _e($text, $domain = 'default')
{
    echo $text;
}

function esc_html__($text, $domain = 'default')
{
    return htmlspecialchars($text);
}

function esc_html_e($text, $domain = 'default')
{
    echo htmlspecialchars($text);
}

function esc_attr($text)
{
    return htmlspecialchars($text, ENT_QUOTES);
}

function esc_url($url)
{
    return $url;
}

function admin_url($path = '')
{
    return 'wp-admin/' . ltrim($path, '/');
}

function current_user_can($capability)
{
    return true;
}

function apply_filters($tag, $value)
{
    return $value;
}

function is_admin()
{
    return true;
}

function get_option($option, $default = false)
{
    return $default;
}

function update_option($option, $value, $autoload = null)
{
    return true;
}

function delete_option($option)
{
    return true;
}

function register_setting($option_group, $option_name, $args = array())
{
    return true;
}

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
{
    return true;
}

function sanitize_text_field($str)
{
    return trim(strip_tags($str));
}

function settings_fields($option_group)
{
    echo "<!-- settings_fields -->";
}

function do_settings_sections($page)
{
    echo "<!-- do_settings_sections -->";
}

function submit_button()
{
    echo '<input type="submit" value="Save Changes" />';
}

function wp_nonce_url($url, $action)
{
    return $url . '&_wpnonce=dummy';
}

function wp_die($message)
{
    throw new Exception($message);
}

function check_admin_referer($action)
{
    return true;
}

function wp_get_referer()
{
    return '';
}

function wp_safe_redirect($location)
{
    return true;
}

function esc_html($text)
{
    return htmlspecialchars((string)$text);
}

function esc_js($text)
{
    return addslashes((string)$text);
}

function wp_unslash($value)
{
    return $value;
}

// Mock WooCommerce
class WooCommerce
{
    public static function instance()
    {
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

$missing = [];
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ $class\n";
    } else {
        $missing[] = $class;
        echo "  - $class NOT FOUND\n";
    }
}

// If dependencies are missing, the plugin should still have loaded without fatals.
// In that case, skip the rest of the boot tests.
if (function_exists('snrfa_vendor_loaded') && !snrfa_vendor_loaded()) {
    echo "\nStep 3: Vendor dependencies missing - skipping integration boot tests (expected).\n\n";
    echo "=== ALL TESTS PASSED ===\n";
    echo "✓ Plugin can be loaded without fatal errors even when dependencies are missing\n";
    exit(0);
}

if (!empty($missing)) {
    echo "\n  ✗ ERROR: Some required classes were not loaded.\n";
    exit(1);
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

