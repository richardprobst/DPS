<?php
/**
 * Agenda access and AJAX guards.
 *
 * Centralizes the operational permission contract and protects JSON responses
 * from bootstrap noise that contains only BOM/whitespace.
 *
 * @package DPS_Agenda_Addon
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Access {

    /**
     * Returns whether the current user can operate the Agenda.
     *
     * @return bool
     */
    public static function can_operate() {
        return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'dps_manage_appointments' ) );
    }

    /**
     * Returns whether the current user can access sensitive surfaces.
     *
     * @return bool
     */
    public static function can_manage_sensitive() {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    /**
     * Enforces the operational AJAX contract.
     *
     * @param string $nonce_action Nonce action.
     * @param string $nonce_key    Nonce field key.
     * @return void
     */
    public static function enforce_operational_ajax( $nonce_action, $nonce_key = 'nonce' ) {
        self::enforce_ajax_request( $nonce_action, $nonce_key, true );
    }

    /**
     * Enforces the sensitive/admin AJAX contract.
     *
     * @param string $nonce_action Nonce action.
     * @param string $nonce_key    Nonce field key.
     * @return void
     */
    public static function enforce_sensitive_ajax( $nonce_action, $nonce_key = 'nonce' ) {
        self::enforce_ajax_request( $nonce_action, $nonce_key, false );
    }

    /**
     * Validates capability and nonce for Agenda AJAX requests.
     *
     * @param string $nonce_action      Nonce action.
     * @param string $nonce_key         Nonce field key.
     * @param bool   $allow_operational Whether to use the operational contract.
     * @return void
     */
    private static function enforce_ajax_request( $nonce_action, $nonce_key, $allow_operational ) {
        $is_allowed = $allow_operational ? self::can_operate() : self::can_manage_sensitive();

        if ( ! $is_allowed ) {
            wp_send_json_error(
                [
                    'message'    => __( 'Permissão negada.', 'dps-agenda-addon' ),
                    'error_code' => 'forbidden',
                ]
            );
        }

        self::clean_whitespace_only_output_buffers();

        $nonce = isset( $_POST[ $nonce_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_send_json_error(
                [
                    'message'    => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ),
                    'error_code' => 'invalid_nonce',
                ]
            );
        }
    }

    /**
     * Clears output buffers that contain only BOM bytes and whitespace.
     *
     * This preserves real notices/warnings while hardening JSON endpoints
     * against bootstrap noise produced by other components.
     *
     * @return void
     */
    private static function clean_whitespace_only_output_buffers() {
        while ( ob_get_level() > 0 ) {
            $buffer = ob_get_contents();

            if ( false === $buffer ) {
                break;
            }

            $normalized = str_replace( "\xEF\xBB\xBF", '', (string) $buffer );

            if ( '' !== trim( $normalized ) ) {
                break;
            }

            ob_end_clean();
        }
    }
}
