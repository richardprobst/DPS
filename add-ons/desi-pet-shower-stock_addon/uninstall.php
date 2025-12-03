<?php
/**
 * Rotina de desinstalação do plugin DPS by PRObst - Stock Add-on
 *
 * Remove CPT de estoque, capability customizada e options.
 *
 * @package Desi_Pet_Shower_Stock
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove todos os itens de estoque (CPT)
$items = get_posts( [
    'post_type'      => 'dps_stock_item',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'any',
] );

foreach ( $items as $item_id ) {
    wp_delete_post( $item_id, true );
}

// Remove capability customizada
$capability = 'dps_manage_stock';
$roles = [ 'administrator', 'dps_reception' ];

foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( $role ) {
        $role->remove_cap( $capability );
    }
}

// Remove options
delete_option( 'dps_stock_alerts' );

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_stock' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_stock' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
