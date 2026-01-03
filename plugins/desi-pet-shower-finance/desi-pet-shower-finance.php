<?php
/**
 * Arquivo de compatibilidade retroativa para o add-on financeiro.
 * 
 * Este arquivo existe apenas para manter compatibilidade com versões anteriores
 * que possam referenciar diretamente 'desi-pet-shower-finance.php'.
 * O arquivo principal do add-on é 'desi-pet-shower-finance-addon.php'.
 * 
 * IMPORTANTE: Este arquivo NÃO deve ter cabeçalho de plugin WordPress para
 * evitar que o add-on apareça duplicado na lista de plugins.
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
