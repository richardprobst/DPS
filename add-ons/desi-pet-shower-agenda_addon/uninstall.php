<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Agenda Add-on
 *
 * Remove pages criadas, options e cron jobs agendados.
 *
 * @package Desi_Pet_Shower_Agenda
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove pages criadas pelo plugin (opcional - comentado para preservar)
// $page_ids = [
//     get_option( 'dps_agenda_page_id' ),
//     get_option( 'dps_charges_page_id' ),
// ];
// foreach ( $page_ids as $page_id ) {
//     if ( $page_id ) {
//         wp_delete_post( $page_id, true );
//     }
// }

// Remove options
delete_option( 'dps_agenda_page_id' );
delete_option( 'dps_charges_page_id' );

// Remove cron jobs
wp_clear_scheduled_hook( 'dps_agenda_send_reminders' );

// Remove post meta de versionamento de agendamentos
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_dps_appointment_version' ], [ '%s' ] );

// Remove transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_agenda%' 
     OR option_name LIKE '_transient_timeout_dps_agenda%'"
);
