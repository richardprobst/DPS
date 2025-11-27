<?php
/**
 * Plugin Name:       Desi Pet Shower – Serviços Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin Desi Pet Shower Base. Adiciona cadastro de serviços (padrão, extras e pacotes) e integração com o agendamento, incluindo cálculo automático do valor total com variações por porte.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-services-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base Desi Pet Shower está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_services_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Serviços requer o plugin base Desi Pet Shower para funcionar.', 'dps-services-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_services_check_base_plugin() ) {
        return;
    }
}, 1 );

if ( ! defined( 'DPS_SERVICES_PLUGIN_FILE' ) ) {
    define( 'DPS_SERVICES_PLUGIN_FILE', __FILE__ );
}

/**
 * Carrega o text domain do Services Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_services_load_textdomain() {
    load_plugin_textdomain( 'dps-services-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_services_load_textdomain', 1 );

// Inclui o arquivo principal do add-on localizado na subpasta
require_once plugin_dir_path( __FILE__ ) . 'dps_service/desi-pet-shower-services-addon.php';