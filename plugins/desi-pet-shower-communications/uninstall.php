<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Communications Add-on
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

// Remove options (incluindo a option correta dps_comm_settings)
$options = [
    'dps_comm_settings',           // Option principal do add-on
    'dps_communications_settings', // Nome legado (caso exista)
    'dps_whatsapp_number',         // Número do WhatsApp da equipe
    'dps_comm_whatsapp_enabled',
    'dps_comm_email_enabled',
    'dps_comm_sms_enabled',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients de forma segura usando prepared statements
$transient_like = $wpdb->esc_like( '_transient_dps_comm' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_comm' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
