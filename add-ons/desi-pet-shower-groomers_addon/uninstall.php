<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Groomers Add-on
 *
 * Remove role de groomer e post meta relacionado.
 *
 * @package Desi_Pet_Shower_Groomers
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove role de groomer
remove_role( 'dps_groomer' );

// Remove post meta de groomers vinculados a agendamentos
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'appointment_groomer_id' ], [ '%s' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => 'appointment_groomers' ], [ '%s' ] );

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_groomer' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_groomer' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
