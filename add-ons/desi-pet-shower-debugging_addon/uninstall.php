<?php
/**
 * Rotina de desinstalação do add-on DPS Debugging.
 *
 * @package DPS_Debugging_Addon
 */

// Se não for chamado pelo WordPress, aborta
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove opções do add-on
delete_option( 'dps_debugging_options' );
delete_option( 'dps_debugging_restore_state' );

// Remove opções de site (para multisite)
delete_site_option( 'dps_debugging_options' );
delete_site_option( 'dps_debugging_restore_state' );
