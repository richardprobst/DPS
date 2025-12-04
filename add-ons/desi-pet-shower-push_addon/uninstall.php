<?php
/**
 * Executado durante a desinstalação do plugin.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 */

// Se não foi chamado pelo WordPress, sair.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remover options
delete_option( 'dps_push_settings' );
delete_option( 'dps_push_vapid_keys' );

// Remover user meta de todos os usuários
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_dps_push_subscriptions'"
);
