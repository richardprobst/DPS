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

// Load classes to test
require_once dirname(__DIR__) . '/includes/class-dps-ai-email-parser.php';
require_once dirname(__DIR__) . '/includes/class-dps-ai-prompts.php';
require_once dirname(__DIR__) . '/includes/class-dps-ai-analytics.php';

echo "DPS AI Add-on Test Bootstrap loaded successfully\n";
