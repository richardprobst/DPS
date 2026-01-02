<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Payment Add-on
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
$transient_like_1 = $wpdb->esc_like( '_transient_dps_payment' ) . '%';
$timeout_like_1 = $wpdb->esc_like( '_transient_timeout_dps_payment' ) . '%';
$transient_like_2 = $wpdb->esc_like( '_transient_dps_mercadopago' ) . '%';
$timeout_like_2 = $wpdb->esc_like( '_transient_timeout_dps_mercadopago' ) . '%';

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s
     OR option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like_1,
    $timeout_like_1,
    $transient_like_2,
    $timeout_like_2
) );
