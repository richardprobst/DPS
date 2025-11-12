<?php
/**
 * Plugin Name:       Desi Pet Shower – Financeiro Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Este plugin fornece uma aba de controle financeiro para o Desi Pet Shower. Ele cria e gerencia as tabelas de transações e parcelas, permitindo registrar receitas, despesas, pagamentos parciais e gerar documentos financeiros. Para manter compatibilidade e evitar conflitos com versões antigas, o código principal da lógica está em 'desi-pet-shower-finance-addon.php'.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-finance-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Sair se o acesso for direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inclui a classe principal do add-on se ainda não estiver carregada.
if ( ! class_exists( 'DPS_Finance_Addon' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'desi-pet-shower-finance-addon.php';
}

// Instancia a classe uma única vez
if ( class_exists( 'DPS_Finance_Addon' ) && ! isset( $GLOBALS['dps_finance_addon'] ) ) {
    $GLOBALS['dps_finance_addon'] = new DPS_Finance_Addon();
}
