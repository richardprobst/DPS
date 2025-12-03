<?php
/**
 * Arquivo de desinstalação do White Label Add-on.
 *
 * Este arquivo é executado quando o plugin é desinstalado via painel admin.
 * Remove todas as opções e dados criados pelo add-on.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Se não for chamado pelo WordPress, sair
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove todas as opções criadas pelo add-on.
 */
function dps_whitelabel_uninstall() {
    // Lista de opções a remover
    $options = [
        'dps_whitelabel_settings',
        'dps_whitelabel_smtp',
        'dps_whitelabel_login',
        'dps_whitelabel_admin_bar',
        'dps_whitelabel_maintenance',
        'dps_whitelabel_activity_log',
        'dps_whitelabel_dashboard',
    ];

    // Remove cada opção
    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Remove diretório de cache de CSS customizado
    $upload_dir = wp_upload_dir();
    $css_dir    = $upload_dir['basedir'] . '/dps-whitelabel/';
    
    if ( is_dir( $css_dir ) ) {
        // Remove arquivos
        $files = glob( $css_dir . '*' );
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
        
        // Remove diretório
        rmdir( $css_dir );
    }

    // Limpa qualquer cache transient
    delete_transient( 'dps_whitelabel_css_cache' );
}

// Executa a desinstalação
dps_whitelabel_uninstall();
