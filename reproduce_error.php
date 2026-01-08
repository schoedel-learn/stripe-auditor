<?php
// Mock WordPress environment
define('ABSPATH', __DIR__ . '/');
define('WPINC', 'wp-includes');

function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_basename($file) { return $file; }
function __($text, $domain) { return $text; }
function _e($text, $domain) { echo $text; }
function esc_html__($text, $domain) { return $text; }
function esc_html_e($text, $domain) { echo $text; }
function esc_attr($text) { return $text; }
function is_admin() { return true; }
function get_option($option, $default = false) { return $default; }
function register_setting($option_group, $option_name, $args = array()) {}
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {}
function sanitize_text_field($str) { return $str; }
function settings_fields($option_group) {}
function do_settings_sections($page) {}
function submit_button() {}
function wc_get_order($order_id) { return null; }
function get_transient($transient) { return false; }
function set_transient($transient, $value, $expiration = 0) { return true; }
function date_i18n($format, $timestamp_with_offset = false, $gmt = false) { return date($format, $timestamp_with_offset); }

class WooCommerce {
    public static function instance() { return new self(); }
}

// Load the plugin
try {
    require_once 'stripe-auditor.php';
    echo "Plugin loaded successfully.\n";
    
    // Simulate init
    if (function_exists('snrfa_init')) {
        snrfa_init();
        echo "snrfa_init executed successfully.\n";
    }
} catch (Throwable $e) {
    echo "Fatal Error caught: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
