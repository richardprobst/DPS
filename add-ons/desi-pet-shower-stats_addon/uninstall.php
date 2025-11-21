<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Stats Add-on
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
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_stats%' 
     OR option_name LIKE '_transient_timeout_dps_stats%'"
);
