<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Stats Add-on
 *
 * Remove transients de cache de estatísticas e options.
 *
 * @package Desi_Pet_Shower_Stats
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove options
delete_option( 'dps_stats_settings' );

// Remove todos os transients de estatísticas
$transient_like = $wpdb->esc_like( '_transient_dps_stats' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_stats' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
