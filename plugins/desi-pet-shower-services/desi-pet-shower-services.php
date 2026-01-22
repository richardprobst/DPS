<?php
/**
 * Plugin Name:       desi.pet by PRObst – Serviços Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Catálogo de serviços com preços por porte. Cadastre banhos, tosas, extras e pacotes com cálculo automático.
 * Version:           1.5.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-services-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_services_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-services-addon' );
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

/**
 * Registra o hook de ativação para popular os serviços padrão.
 * 
 * IMPORTANTE: Este hook DEVE ser registrado no arquivo principal do plugin
 * (este arquivo) e não dentro do construtor da classe, pois o WordPress
 * processa hooks de ativação ANTES do hook 'init' rodar, e a classe
 * DPS_Services_Addon só é instanciada no hook 'init'.
 */
register_activation_hook( __FILE__, [ 'DPS_Services_Addon', 'activate' ] );
