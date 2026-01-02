<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Services Add-on
 *
 * Remove CPT de serviços e post meta relacionado.
 *
 * @package Desi_Pet_Shower_Services
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove todos os serviços (CPT)
$services = get_posts( [
    'post_type'      => 'dps_service',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'any',
] );

foreach ( $services as $service_id ) {
    wp_delete_post( $service_id, true );
}

// Remove post meta de serviços vinculados a agendamentos
$meta_keys = [
    'appointment_services',
    'appointment_total_value',
    'appointment_services_details',
];

foreach ( $meta_keys as $meta_key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_service' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_service' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
