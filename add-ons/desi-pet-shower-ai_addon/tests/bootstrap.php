<?php
/**
 * PHPUnit bootstrap file for DPS AI Add-on tests
 *
 * @package DPS_AI_Addon
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Define WordPress constants for testing environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

// Mock WordPress functions that are used in tests
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addslashes($text);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return $key;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        // Simplified version - remove dangerous tags
        $allowed_tags = '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre>';
        return strip_tags($data, $allowed_tags);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        global $_wp_filters;
        if (isset($_wp_filters[$tag])) {
            foreach ($_wp_filters[$tag] as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        global $_wp_filters;
        $_wp_filters[$tag][] = $callback;
    }
}

// Mock logger functions
if (!function_exists('dps_ai_log_debug')) {
    function dps_ai_log_debug($message, $context = []) {
        // No-op for tests
    }
}

if (!function_exists('dps_ai_log_info')) {
    function dps_ai_log_info($message, $context = []) {
        // No-op for tests
    }
}

if (!function_exists('dps_ai_log_warning')) {
    function dps_ai_log_warning($message, $context = []) {
        // No-op for tests
    }
}

if (!function_exists('dps_ai_log_error')) {
    function dps_ai_log_error($message, $context = []) {
        // No-op for tests
    }
}

// Mock WordPress database functions
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return stripslashes_deep($value);
    }
}

if (!function_exists('stripslashes_deep')) {
    function stripslashes_deep($value) {
        return is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            parse_str($args, $parsed_args);
        }

        if (is_array($defaults) && $defaults) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

// Mock get_option for tests
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $_test_options;
        return isset($_test_options[$option]) ? $_test_options[$option] : $default;
    }
}

// Mock update_option for tests
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $_test_options;
        $_test_options[$option] = $value;
        return true;
    }
}

// Mock get_transient for tests
if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $_test_transients;
        return isset($_test_transients[$transient]) ? $_test_transients[$transient] : false;
    }
}

// Mock set_transient for tests
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $_test_transients;
        $_test_transients[$transient] = $value;
        return true;
    }
}

// Mock delete_transient for tests
if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $_test_transients;
        unset($_test_transients[$transient]);
        return true;
    }
}

// Initialize global storage for mocks
global $_test_options, $_test_transients;
$_test_options = [];
$_test_transients = [];

// Load classes to test (only pure classes without WordPress dependencies)
require_once dirname(__DIR__) . '/includes/class-dps-ai-email-parser.php';
require_once dirname(__DIR__) . '/includes/class-dps-ai-prompts.php';
require_once dirname(__DIR__) . '/includes/class-dps-ai-analytics.php';

echo "DPS AI Add-on Test Bootstrap loaded successfully\n";
