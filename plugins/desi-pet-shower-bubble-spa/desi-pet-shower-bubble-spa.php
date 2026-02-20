<?php
/**
 * Plugin Name:       desi.pet by PRObst – Bubble Spa Deluxe
 * Plugin URI:        https://www.probst.pro
 * Description:       Jogo estilo Zuma temático de Banho & Tosa — estoure bolhas e impeça que a sujeira chegue ao ralo! 🫧
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-bubble-spa
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DPS_BUBBLE_SPA_VERSION', '1.0.0' );
define( 'DPS_BUBBLE_SPA_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_BUBBLE_SPA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Verifica se o plugin base está ativo.
 */
function dps_bubble_spa_check_base_plugin(): bool {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'Bubble Spa Deluxe requer o plugin base desi.pet by PRObst ativo.', 'dps-bubble-spa' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', 'dps_bubble_spa_check_base_plugin', 1 );

/**
 * Carrega text domain.
 */
function dps_bubble_spa_load_textdomain(): void {
    load_plugin_textdomain( 'dps-bubble-spa', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_bubble_spa_load_textdomain', 1 );

/**
 * Inicializa o add-on.
 */
function dps_bubble_spa_init(): void {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        return;
    }
    require_once DPS_BUBBLE_SPA_DIR . 'includes/class-dps-bubble-spa-addon.php';
    DPS_Bubble_Spa_Addon::get_instance();
}
add_action( 'init', 'dps_bubble_spa_init', 5 );
