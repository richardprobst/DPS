<?php
/**
 * Executado durante a desinstalação do plugin.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 */

// Se não foi chamado pelo WordPress, sair.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remover cron jobs agendados.
wp_clear_scheduled_hook( 'dps_send_agenda_notification' );
wp_clear_scheduled_hook( 'dps_send_daily_report' );
wp_clear_scheduled_hook( 'dps_send_weekly_inactive_report' );

// Remover todas as options do add-on.
$options = [
    // Configurações gerais de push.
    'dps_push_settings',
    'dps_push_vapid_keys',
    // Configurações de relatórios por email.
    'dps_push_emails_agenda',
    'dps_push_emails_report',
    'dps_push_agenda_time',
    'dps_push_report_time',
    'dps_push_weekly_day',
    'dps_push_weekly_time',
    'dps_push_inactive_days',
    // Flags de habilitação.
    'dps_push_agenda_enabled',
    'dps_push_report_enabled',
    'dps_push_weekly_enabled',
    // Integração Telegram.
    'dps_push_telegram_token',
    'dps_push_telegram_chat',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remover user meta de todos os usuários (inscrições push).
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_dps_push_subscriptions'"
);
