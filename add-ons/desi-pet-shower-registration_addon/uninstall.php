<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Registration Add-on
 *
 * Remove page de cadastro público e options de configuração.
 *
 * @package Desi_Pet_Shower_Registration
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove page criada pelo plugin (opcional - comentado para preservar)
// $page_id = get_option( 'dps_registration_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove options
$options = [
    'dps_registration_page_id',
    'dps_registration_google_maps_key',
    'dps_registration_settings',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_registration' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_registration' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
