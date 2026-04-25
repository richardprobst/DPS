<?php
/**
 * Rotina de desinstalação do plugin desi.pet by PRObst - Registration Add-on
 *
 * Remove page de cadastro público e options de configuração.
 *
 * @package Desi_Pet_Shower_Registration
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

require_once __DIR__ . '/includes/class-dps-registration-storage.php';

// Remove page criada pelo plugin (opcional - comentado para preservar)
// $page_id = get_option( 'dps_registration_page_id' );
// if ( $page_id ) {
//     wp_delete_post( $page_id, true );
// }

// Remove options - inclui todas as options utilizadas pelo add-on
$options = array(
    'dps_registration_page_id',
    'dps_registration_google_maps_key',
    'dps_registration_settings',
    'dps_google_api_key',
    'dps_registration_recaptcha_enabled',
    'dps_registration_recaptcha_site_key',
    'dps_registration_recaptcha_secret_key',
    'dps_registration_recaptcha_threshold',
    'dps_registration_confirm_email_subject',
    'dps_registration_confirm_email_body',
    'dps_registration_api_enabled',
    'dps_registration_api_key_hash',
    'dps_registration_api_rate_key_per_hour',
    'dps_registration_api_rate_ip_per_hour',
    DPS_Registration_Storage::OPTION_DB_VERSION,
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove resíduos de transient de versões antigas do add-on.
$transient_prefixes = array(
    '_transient_dps_registration',
    '_transient_timeout_dps_registration',
    '_transient_dps_reg_rate_',
    '_transient_timeout_dps_reg_rate_',
    '_transient_dps_reg_msg_',
    '_transient_timeout_dps_reg_msg_',
    '_transient_dps_reg_api_',
    '_transient_timeout_dps_reg_api_',
);

foreach ( $transient_prefixes as $prefix ) {
    $like_pattern = $wpdb->esc_like( $prefix ) . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_pattern
        )
    );
}

DPS_Registration_Storage::drop_tables();

// Limpa scheduled events (cron)
wp_clear_scheduled_hook( 'dps_registration_confirmation_reminder' );
