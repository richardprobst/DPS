<?php
/**
 * PHPUnit bootstrap file for DPS Base plugin tests.
 *
 * @package DesiPetShower
 */

// Composer autoloader.
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
    require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Define WordPress constants for testing environment.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

// Mock WordPress functions used by the helpers.

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return stripslashes_deep( $value );
    }
}

if ( ! function_exists( 'stripslashes_deep' ) ) {
    function stripslashes_deep( $value ) {
        return is_array( $value )
            ? array_map( 'stripslashes_deep', $value )
            : stripslashes( $value );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( (string) $str ) );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $value ) {
        return rtrim( $value, '/\\' ) . '/';
    }
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
    function get_stylesheet_directory() {
        return '/tmp/nonexistent-theme';
    }
}

// Load helper classes under test.
require_once dirname( __DIR__ ) . '/includes/class-dps-money-helper.php';
require_once dirname( __DIR__ ) . '/includes/class-dps-phone-helper.php';
require_once dirname( __DIR__ ) . '/includes/class-dps-base-template-engine.php';

echo "DPS Base Test Bootstrap loaded successfully\n";
