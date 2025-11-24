<?php
/**
 * Plugin Name:       Desi Pet Shower – Assinaturas Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin base do Desi Pet Shower. Permite cadastrar pacotes mensais de banho com frequências semanal ou quinzenal. Gera automaticamente os agendamentos do mês, controla pagamento e permite renovação.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-subscription-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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