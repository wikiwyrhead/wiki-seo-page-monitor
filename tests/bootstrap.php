<?php
/**
 * PHPUnit Bootstrap File
 */

// Define test environment
define('SEO_MONITOR_TESTS', true);

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Mock WordPress functions for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Provide minimal WordPress function stubs so PHPUnit tests can run
if (!function_exists('add_action')) {
    function add_action($hook, $callback) { return true; }
    function add_menu_page() { return true; }
    function add_submenu_page() { return true; }
    function register_rest_route() { return true; }
    function wp_enqueue_script() { return true; }
    function wp_enqueue_style() { return true; }
    function wp_localize_script() { return true; }
    function wp_create_nonce() { return 'test_nonce'; }
    function rest_url($path = '') { return 'https://example.com/wp-json/' . $path; }
    function plugin_dir_path($file) { return __DIR__ . '/../'; }
    function plugin_dir_url($file) { return 'https://example.com/wp-content/plugins/seo-page-monitor/'; }
    // Simple in-memory option store for tests
    global $seo_monitor_test_options;
    $seo_monitor_test_options = array();

    function get_option($key, $default = false) {
        global $seo_monitor_test_options;
        if (array_key_exists($key, $seo_monitor_test_options)) {
            return $seo_monitor_test_options[$key];
        }
        return $default;
    }

    function update_option($key, $value, $autoload = true) {
        global $seo_monitor_test_options;
        $seo_monitor_test_options[$key] = $value;
        return true;
    }

    function add_option($key, $value = '', $deprecated = '', $autoload = true) {
        return update_option($key, $value, $autoload);
    }

    function delete_option($key) {
        global $seo_monitor_test_options;
        if (isset($seo_monitor_test_options[$key])) {
            unset($seo_monitor_test_options[$key]);
            return true;
        }
        return false;
    }
    function current_user_can($capability) { return true; }
    function esc_url_raw($url) { return $url; }
    function sanitize_text_field($str) { return strip_tags($str); }
    function rest_ensure_response($data) { return $data; }
    function register_activation_hook($file, $callback) { return true; }
    function register_deactivation_hook($file, $callback) { return true; }
    function register_uninstall_hook($file, $callback) { return true; }
    function current_time($format = 'mysql', $gmt = 0) { if ($format === 'mysql') return date('Y-m-d H:i:s'); return date($format); }
    function wp_next_scheduled($hook) { return false; }
    function wp_schedule_single_event($timestamp, $hook) { return true; }
}

// Load plugin file
require_once __DIR__ . '/../seo-page-monitor.php';

echo "Bootstrap loaded for SEO Page Monitor tests\n";
