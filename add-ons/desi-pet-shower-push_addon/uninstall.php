<?php
/**
 * Rotina de desinstalação do plugin DPS by PRObst - Push Add-on
 *
 * Remove cron jobs agendados e options de configuração.
 *
 * @package Desi_Pet_Shower_Push
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove todos os cron jobs do plugin (nomes corretos usados no código)
$cron_hooks = [
    'dps_send_agenda_notification',
    'dps_send_daily_report',
    'dps_send_weekly_inactive_report',
];

foreach ( $cron_hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
}

// Remove options de configuração (nomes corretos usados no código)
$options = [
    // Options atuais
    'dps_push_emails_agenda',
    'dps_push_emails_report',
    'dps_push_agenda_time',
    'dps_push_report_time',
    'dps_push_weekly_day',
    'dps_push_weekly_time',
    'dps_push_telegram_token',
    'dps_push_telegram_chat',
    // Novas options v1.1.0
    'dps_push_agenda_enabled',
    'dps_push_report_enabled',
    'dps_push_weekly_enabled',
    'dps_push_inactive_days',
    // Options legacy (fallback)
    'dps_push_agenda_hour',
    'dps_push_report_hour',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_push' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_push' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
