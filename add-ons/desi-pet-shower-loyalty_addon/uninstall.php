<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Loyalty Add-on
 *
 * Remove tabela de referrals, CPT de campanhas, post meta de pontos e créditos, e options.
 *
 * @package Desi_Pet_Shower_Loyalty
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove tabela de referrals
$table = $wpdb->prefix . 'dps_referrals';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// Remove CPT de campanhas
$campaigns = get_posts( [
    'post_type'      => 'dps_campaign',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'any',
] );

foreach ( $campaigns as $campaign_id ) {
    wp_delete_post( $campaign_id, true );
}

// Remove post meta relacionado a loyalty (pontos, créditos, códigos de referência)
$meta_keys = [
    'dps_loyalty_points',
    'dps_loyalty_points_log',
    '_dps_credit_balance',
    '_dps_referral_code',
];

foreach ( $meta_keys as $meta_key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove options
delete_option( 'dps_loyalty_settings' );

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_loyalty' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_loyalty' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
