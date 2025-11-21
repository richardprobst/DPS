<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Backup Add-on
 *
 * Remove options de configuração e arquivos de backup (opcional).
 *
 * @package Desi_Pet_Shower_Backup
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove options
$options = [
    'dps_backup_settings',
    'dps_last_backup_date',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Opcionalmente, remove arquivos de backup gerados
// NOTA: Comentado por padrão para segurança - arquivos de backup podem ser valiosos
// $upload_dir = wp_upload_dir();
// $backup_dir = $upload_dir['basedir'] . '/dps-backups/';
// if ( is_dir( $backup_dir ) ) {
//     // Código para remover diretório e arquivos
// }

// Remove transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_dps_backup%' 
     OR option_name LIKE '_transient_timeout_dps_backup%'"
);
