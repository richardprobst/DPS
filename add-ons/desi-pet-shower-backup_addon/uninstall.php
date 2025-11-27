<?php
/**
 * Rotina de desinstalação do plugin Desi Pet Shower - Backup Add-on.
 *
 * Remove options de configuração e transients criados pelo add-on.
 * Arquivos de backup NÃO são removidos por segurança.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.0.0
 */

// Impede execução direta - apenas via desinstalação do WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove options criadas pelo add-on
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

// Remove transients relacionados ao backup
$transient_like = $wpdb->esc_like( '_transient_dps_backup' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_dps_backup' ) . '%';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options é seguro
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE %s 
     OR option_name LIKE %s",
    $transient_like,
    $transient_timeout_like
) );
