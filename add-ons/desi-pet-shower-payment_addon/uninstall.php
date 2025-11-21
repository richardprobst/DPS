<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Payment Add-on
 *
 * Remove options de configuração de pagamento (tokens e chaves).
 *
 * @package Desi_Pet_Shower_Payment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove options de configuração sensíveis
$options = [
    'dps_mercadopago_access_token',
    'dps_pix_key',
    'dps_mercadopago_webhook_secret',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_payment%' 
     OR option_name LIKE '_transient_timeout_dps_payment%'
     OR option_name LIKE '_transient_dps_mercadopago%' 
     OR option_name LIKE '_transient_timeout_dps_mercadopago%'"
);
