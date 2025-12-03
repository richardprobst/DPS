<?php
/**
 * Rotina de desinstalação do plugin DPS by PRObst - Communications Add-on
 *
 * Remove options de configuração de comunicações e cron jobs.
 *
 * @package Desi_Pet_Shower_Communications
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove cron jobs relacionados a comunicações
wp_clear_scheduled_hook( 'dps_comm_send_appointment_reminder' );
wp_clear_scheduled_hook( 'dps_comm_send_post_service' );

// Remove options
$options = [
    'dps_communications_settings',
    'dps_comm_whatsapp_enabled',
    'dps_comm_email_enabled',
    'dps_comm_sms_enabled',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_comm' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_comm' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
