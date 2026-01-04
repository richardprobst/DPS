<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Groomers Add-on
 *
 * Remove role de groomer, tabelas customizadas, post meta e options relacionados.
 *
 * @package Desi_Pet_Shower_Groomers
 * @since 1.4.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove role de groomer
remove_role( 'dps_groomer' );

// Remove post meta de groomers vinculados a agendamentos
// Meta key correta usada pelo add-on: _dps_groomers
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_dps_groomers' ], [ '%s' ] );

// Remove user metas relacionados aos groomers
$groomer_metas = [
    '_dps_groomer_status',
    '_dps_groomer_phone',
    '_dps_groomer_commission_rate',
    '_dps_staff_type',
    '_dps_is_freelancer',
    '_dps_work_start',
    '_dps_work_end',
    '_dps_work_days',
];
foreach ( $groomer_metas as $meta_key ) {
    $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove commission metas de agendamentos
$commission_metas = [
    '_dps_staff_commissions',
    '_dps_commission_generated',
    '_dps_commission_date',
];
foreach ( $commission_metas as $meta_key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Remove tabela de tokens
$tokens_table = $wpdb->prefix . 'dps_groomer_tokens';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$tokens_table}" );

// Remove options relacionados
$options_to_delete = [
    'dps_groomer_tokens_db_version',
    'dps_groomers_staff_migration_done',
];
foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// Remove transients
$transient_like = $wpdb->esc_like( '_transient_dps_groomer' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_groomer' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );

// Remove CPT de avaliações e seus posts
$review_posts = get_posts( [
    'post_type'      => 'dps_groomer_review',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );
foreach ( $review_posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Remove review metas
$review_metas = [
    '_dps_review_groomer_id',
    '_dps_review_rating',
    '_dps_review_name',
    '_dps_review_appointment_id',
];
foreach ( $review_metas as $meta_key ) {
    $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $meta_key ], [ '%s' ] );
}

// Limpa o cron job de limpeza de tokens
wp_clear_scheduled_hook( 'dps_groomer_cleanup_tokens' );
