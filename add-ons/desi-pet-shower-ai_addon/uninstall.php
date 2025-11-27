<?php
/**
 * Uninstall script for DPS AI Add-on.
 *
 * Este arquivo é executado automaticamente pelo WordPress quando o plugin
 * é desinstalado através da interface administrativa.
 *
 * Remove todas as opções e dados criados pelo plugin no banco de dados.
 *
 * @package DPS_AI_Addon
 * @since 1.2.0
 */

// Segurança: verificar se está sendo chamado pelo WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove as opções de configurações do AI Add-on
delete_option( 'dps_ai_settings' );

// Remove capability específica de todos os roles
$capability = 'dps_use_ai_assistant';
$roles      = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( $role && $role->has_cap( $capability ) ) {
        $role->remove_cap( $capability );
    }
}

// Remove quaisquer transients criados pelo plugin
// Formato: dps_ai_ctx_{client_id}_{hash}
// Nota: $wpdb->options é uma propriedade interna segura do WordPress
// que contém o nome da tabela de options com o prefixo correto.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessário para limpeza de transients na desinstalação
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_dps_ai_%',
        '_transient_timeout_dps_ai_%'
    )
);

// Limpar cache de objeto se disponível
if ( function_exists( 'wp_cache_flush' ) ) {
    wp_cache_flush();
}
