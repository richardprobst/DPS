<?php
/**
 * Maintenance routines for the Registration add-on.
 *
 * @package DPS_Registration_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedules and runs persistent event cleanup.
 */
class DPS_Registration_Maintenance {

    /**
     * Daily cleanup hook.
     *
     * @var string
     */
    const CLEANUP_CRON = 'dps_registration_events_cleanup';

    /**
     * Registers cleanup hooks.
     *
     * @return void
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'maybe_schedule_cleanup' ) );
        add_action( self::CLEANUP_CRON, array( __CLASS__, 'cleanup' ) );
    }

    /**
     * Schedules daily cleanup if missing.
     *
     * @return void
     */
    public static function maybe_schedule_cleanup() {
        if ( ! wp_next_scheduled( self::CLEANUP_CRON ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_CRON );
        }
    }

    /**
     * Deletes expired registration event rows.
     *
     * @return void
     */
    public static function cleanup() {
        if ( class_exists( 'DPS_Registration_Storage' ) ) {
            DPS_Registration_Storage::delete_expired_records();
        }
    }

    /**
     * Clears scheduled cleanup.
     *
     * @return void
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( self::CLEANUP_CRON );
    }
}
