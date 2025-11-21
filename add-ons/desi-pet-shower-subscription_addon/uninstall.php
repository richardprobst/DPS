<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Subscription Add-on
 *
 * Remove CPT de assinaturas e post meta relacionado.
 *
 * @package Desi_Pet_Shower_Subscription
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove todos as assinaturas (CPT)
$subscriptions = get_posts( [
    'post_type'      => 'dps_subscription',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'    => 'any',
] );

foreach ( $subscriptions as $subscription_id ) {
    wp_delete_post( $subscription_id, true );
}

// Remove options
delete_option( 'dps_subscription_settings' );

// Remove transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_subscription%' 
     OR option_name LIKE '_transient_timeout_dps_subscription%'"
);
