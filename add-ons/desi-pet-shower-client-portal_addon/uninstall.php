<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Client Portal Add-on
 *
 * Remove post meta de autenticação de clientes e options.
 *
 * @package Desi_Pet_Shower_Client_Portal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove page criada pelo plugin (opcional - comentado para preservar)
// $page_id = get_option( 'dps_portal_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove post meta de autenticação
$meta_keys = [
    'dps_client_login',
    'dps_client_password',
    'dps_client_password_hash',
];

foreach ( $meta_keys as $meta_key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove options
delete_option( 'dps_portal_page_id' );
delete_option( 'dps_portal_login_page_id' );

// Remove transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_portal%' 
     OR option_name LIKE '_transient_timeout_dps_portal%'"
);
