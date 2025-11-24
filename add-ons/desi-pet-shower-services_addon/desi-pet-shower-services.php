<?php
/**
 * Plugin Name:       Desi Pet Shower – Serviços Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin Desi Pet Shower Base. Adiciona cadastro de serviços (padrão, extras e pacotes) e integração com o agendamento, incluindo cálculo automático do valor total com variações por porte.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-services-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'DPS_SERVICES_PLUGIN_FILE' ) ) {
    define( 'DPS_SERVICES_PLUGIN_FILE', __FILE__ );
}

/**
 * Carrega o text domain do Services Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 10).
 */
function dps_services_load_textdomain() {
    load_plugin_textdomain( 'dps-services-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_services_load_textdomain', 1 );

// Inclui o arquivo principal do add-on localizado na subpasta
require_once plugin_dir_path( __FILE__ ) . 'dps_service/desi-pet-shower-services-addon.php';