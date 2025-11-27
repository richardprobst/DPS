<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Client Portal Add-on
 *
 * Remove tabela de tokens, post meta de autenticação de clientes, options,
 * transients e cron jobs.
 *
 * @package Desi_Pet_Shower_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove tabela de tokens do portal
$table_name = $wpdb->prefix . 'dps_portal_tokens';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabela própria do plugin
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Remove option de versão da tabela
delete_option( 'dps_portal_tokens_db_version' );

// Remove page criada pelo plugin (opcional - comentado para preservar)
// $page_id = get_option( 'dps_portal_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove post meta de autenticação (metadados legados)
$meta_keys = [
    'dps_client_login',
    'dps_client_password',
    'dps_client_password_hash',
];

foreach ( $meta_keys as $meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Limpeza de desinstalação
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove options
delete_option( 'dps_portal_page_id' );
delete_option( 'dps_portal_login_page_id' );

// Remove cron job de limpeza de tokens
wp_clear_scheduled_hook( 'dps_portal_cleanup_tokens' );

// Remove transients de tokens gerados
$transient_like         = $wpdb->esc_like( '_transient_dps_portal' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_portal' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Limpeza de desinstalação
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );

// Remove transients de tentativas de login
$login_transient_like = $wpdb->esc_like( '_transient_dps_client_login_attempts_' ) . '%';
$login_timeout_like   = $wpdb->esc_like( '_transient_timeout_dps_client_login_attempts_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Limpeza de desinstalação
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $login_transient_like,
    $login_timeout_like
) );
