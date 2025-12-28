<?php
/**
 * Plugin Name:       DPS by PRObst – Assinaturas Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Pacotes mensais de banho com frequência semanal ou quinzenal. Agendamentos automáticos e controle de renovação.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-subscription-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_subscription_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Assinaturas requer o plugin base DPS by PRObst para funcionar.', 'dps-subscription-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_subscription_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Subscription Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_subscription_load_textdomain() {
    load_plugin_textdomain( 'dps-subscription-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
}
add_action( 'init', 'dps_subscription_load_textdomain', 1 );

// Inclui o arquivo principal da extensão localizado na pasta dps_subscription
require_once plugin_dir_path( __FILE__ ) . 'dps_subscription/desi-pet-shower-subscription-addon.php';
