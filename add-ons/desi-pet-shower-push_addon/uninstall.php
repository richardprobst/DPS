<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Push Add-on
 *
 * Remove cron jobs agendados e options de configuração.
 *
 * @package Desi_Pet_Shower_Push
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove todos os cron jobs do plugin
$cron_hooks = [
    'dps_push_daily_schedule',
    'dps_push_daily_finance_report',
    'dps_push_weekly_inactive_pets',
];

foreach ( $cron_hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
}

// Remove options de configuração
$options = [
    'dps_push_settings',
    'dps_push_recipients',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_push' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_push' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
