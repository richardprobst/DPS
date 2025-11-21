<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Finance Add-on
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
delete_option( 'dps_finance_db_version' );

// Remove quaisquer transients relacionados
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_fin%' 
     OR option_name LIKE '_transient_timeout_dps_fin%'"
);
