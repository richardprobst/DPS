<?php
/**
 * Plugin Name:       desi.pet by PRObst - Space Groomers
 * Plugin URI:        https://www.probst.pro
 * Description:       Joguinho tematico "Space Groomers: Invasao das Pulgas" para engajar clientes no portal. Canvas + JS puro, sem dependencias pesadas.
 * Version:           1.5.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-game
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DPS_GAME_VERSION', '1.5.0' );
define( 'DPS_GAME_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_GAME_URL', plugin_dir_url( __FILE__ ) );
define( 'DPS_GAME_BRAND_NAME', 'Desi Pet Shower' );
define( 'DPS_GAME_DISPLAY_NAME', 'Desi Pet Shower: Space Groomers' );
define( 'DPS_GAME_TAGLINE', 'Banho em ordem, pet brilhando.' );

/**
 * Verifica se o plugin base esta ativo.
 */
function dps_game_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Space Groomers requer o plugin base desi.pet by PRObst ativo.', 'dps-game' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}

add_action( 'plugins_loaded', function () {
    if ( ! dps_game_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega textdomain.
 */
function dps_game_load_textdomain() {
    load_plugin_textdomain( 'dps-game', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_game_load_textdomain', 1 );

/**
 * Inicializa o add-on.
 */
function dps_game_init() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        return;
    }
    require_once DPS_GAME_DIR . 'includes/class-dps-game-progress-service.php';
    require_once DPS_GAME_DIR . 'includes/class-dps-game-ecosystem-service.php';
    require_once DPS_GAME_DIR . 'includes/class-dps-game-rest.php';
    require_once DPS_GAME_DIR . 'includes/class-dps-game-addon.php';
    DPS_Game_Addon::get_instance();
}
add_action( 'init', 'dps_game_init', 5 );
