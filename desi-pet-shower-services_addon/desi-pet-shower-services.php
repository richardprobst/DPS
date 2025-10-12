<?php
/**
 * Plugin Name: Desi Pet Shower - Serviços Add-on
 * Description: Add-on para o plugin Desi Pet Shower Base. Adiciona cadastro de serviços (padrão, extras e pacotes) e integração com o agendamento, incluindo cálculo automático do valor total com variações por porte.
 * Version: 1.1.0
 * Author: PRObst
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inclui o arquivo principal do add-on localizado na subpasta
require_once plugin_dir_path( __FILE__ ) . 'dps_service/desi-pet-shower-services-addon.php';