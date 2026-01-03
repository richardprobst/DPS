<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Base
 *
 * Este arquivo é executado quando o plugin é desinstalado via WordPress admin.
 * Remove todos os dados criados pelo plugin, incluindo CPTs, tabelas, options, roles e capabilities.
 *
 * @package DPS_by_PRObst
 */

// Se o arquivo não for chamado pelo WordPress, aborta.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove todos os posts dos Custom Post Types criados pelo plugin.
 *
 * @param string $post_type Tipo de post a ser removido.
 */
function dps_uninstall_remove_cpt_posts( $post_type ) {
    global $wpdb;

    // Busca todos os IDs de posts deste tipo
    $post_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        $post_type
    ) );

    if ( empty( $post_ids ) ) {
        return;
    }

    $ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

    // Remove post meta em lote
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$ids_placeholder})",
        ...$post_ids
    ) );

    // Remove posts em lote
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})",
        ...$post_ids
    ) );
}

// Remove Custom Post Types e todos os posts associados
$dps_post_types = [ 'dps_cliente', 'dps_pet', 'dps_agendamento' ];
foreach ( $dps_post_types as $post_type ) {
    dps_uninstall_remove_cpt_posts( $post_type );
}

// Remove tabela de logs
global $wpdb;
$table_name = $wpdb->prefix . 'dps_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Remove options do plugin
$dps_options = [
    'dps_logger_min_level',
    'dps_logger_db_version',
    'dps_pets_cache_keys',
];

foreach ( $dps_options as $option ) {
    delete_option( $option );
}

// Remove transients de cache
$transient_like = $wpdb->esc_like( '_transient_dps_' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_' ) . '%';

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );

// Remove capabilities customizadas
$dps_capabilities = [
    'dps_manage_appointments',
    'dps_manage_clients',
    'dps_manage_pets',
];

// Remove capabilities do administrador
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
    foreach ( $dps_capabilities as $cap ) {
        $admin_role->remove_cap( $cap );
    }
}

// Remove role de recepção
remove_role( 'dps_reception' );

// Limpa cache de rewrite rules
