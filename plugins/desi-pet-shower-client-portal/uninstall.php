<?php
/**
 * Rotina de desinstalacao do plugin desi.pet by PRObst - Client Portal Add-on.
 *
 * Remove tabela de tokens, post meta de autenticacao de clientes, options
 * persistentes e cron jobs.
 *
 * @package Desi_Pet_Shower_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove tabela de tokens do portal.
$table_name = $wpdb->prefix . 'dps_portal_tokens';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabela propria do plugin.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Remove option de versao da tabela.
delete_option( 'dps_portal_tokens_db_version' );

// Remove page criada pelo plugin (opcional - comentado para preservar).
// $page_id = get_option( 'dps_portal_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove post meta de autenticacao (metadados legados).
$meta_keys = [
    'dps_client_login',
    'dps_client_password',
    'dps_client_password_hash',
];

foreach ( $meta_keys as $meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Limpeza de desinstalacao.
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove options do portal.
delete_option( 'dps_portal_page_id' );
delete_option( 'dps_portal_login_page_id' );
delete_option( 'dps_portal_sessions' );
delete_option( 'dps_portal_2fa_state' );
delete_option( 'dps_portal_rate_limits' );
delete_option( 'dps_portal_invalid_token_attempts' );

// Remove cron job de limpeza de tokens.
wp_clear_scheduled_hook( 'dps_portal_cleanup_tokens' );
