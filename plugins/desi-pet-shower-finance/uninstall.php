<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Finance Add-on
 *
 * Remove tabelas customizadas, options e dados relacionados.
 *
 * @package Desi_Pet_Shower_Finance
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove tabelas customizadas
$tables = [
    $wpdb->prefix . 'dps_transacoes',
    $wpdb->prefix . 'dps_parcelas',
    $wpdb->prefix . 'dps_finance_audit_log',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove pages criadas pelo plugin (opcional - comentado para preservar)
// $page_id = get_option( 'dps_fin_docs_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove options
delete_option( 'dps_fin_docs_page_id' );
delete_option( 'dps_transacoes_db_version' );
delete_option( 'dps_parcelas_db_version' );
delete_option( 'dps_finance_audit_db_version' );

// Remove quaisquer transients relacionados
$transient_like = $wpdb->esc_like( '_transient_dps_fin' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_fin' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
