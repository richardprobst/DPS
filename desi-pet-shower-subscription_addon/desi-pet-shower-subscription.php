<?php
/*
 * Plugin Name: Desi Pet Shower – Assinaturas Add-on
 * Description: Add-on para o plugin base do Desi Pet Shower. Permite cadastrar pacotes mensais de banho com frequências semanal ou quinzenal. Gera automaticamente os agendamentos do mês, controla pagamento e permite renovação.
 * Version:     1.0.0
 * Author:      PRObst
 * License:     GPL-2.0+
 * Text Domain: dps-subscription-addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inclui o arquivo principal da extensão localizado na pasta dps_subscription
require_once plugin_dir_path( __FILE__ ) . 'dps_subscription/desi-pet-shower-subscription-addon.php';