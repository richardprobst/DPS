<?php
/**
 * Gerenciamento de configurações do Push Add-on.
 *
 * Responsável por validar e salvar todas as configurações do add-on,
 * incluindo notificações push, relatórios por email e integração Telegram.
 *
 * @package DPS_Push_Addon
 * @since   2.0.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de configurações do Push Add-on.
 *
 * @since 2.0.0
 */
class DPS_Push_Settings {

    /**
     * Processa salvamento de configurações via POST.
     *
     * Valida nonce, capabilities, sanitiza todos os inputs e salva
     * as configurações. Redireciona com mensagem de status.
     *
     * @since 2.0.0
     */
    public function maybe_handle_save() {
        if ( ! isset( $_POST['dps_push_action'] ) || 'save_settings' !== $_POST['dps_push_action'] ) {
            return;
        }

        $nonce = isset( $_POST['dps_push_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_push_settings' ) ) {
            set_transient( 'dps_push_settings_error', __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-push-addon' ), 30 );
            $this->redirect_after_save( false );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            set_transient( 'dps_push_settings_error', __( 'Você não tem permissão para alterar estas configurações.', 'dps-push-addon' ), 30 );
            $this->redirect_after_save( false );
            return;
        }

        // --- Notificações push -------------------------------------------------
        $settings = [
            'notify_new_appointment' => ! empty( $_POST['notify_new_appointment'] ),
            'notify_status_change'   => ! empty( $_POST['notify_status_change'] ),
            'notify_rescheduled'     => ! empty( $_POST['notify_rescheduled'] ),
        ];

        update_option( DPS_Push_Addon::OPTION_KEY, $settings );

        // --- Relatórios por email ----------------------------------------------
        $emails_agenda_raw = isset( $_POST['dps_push_emails_agenda'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_agenda'] ) ) : '';
        $emails_report_raw = isset( $_POST['dps_push_emails_report'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_push_emails_report'] ) ) : '';

        $emails_agenda = $this->validate_email_list( $emails_agenda_raw );
        $emails_report = $this->validate_email_list( $emails_report_raw );

        $agenda_time = $this->validate_time( isset( $_POST['dps_push_agenda_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_agenda_time'] ) ) : '08:00' );
        $report_time = $this->validate_time( isset( $_POST['dps_push_report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_report_time'] ) ) : '19:00' );
        $weekly_time = $this->validate_time( isset( $_POST['dps_push_weekly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_time'] ) ) : '08:00' );

        $allowed_days   = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
        $weekly_day_raw = isset( $_POST['dps_push_weekly_day'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_weekly_day'] ) ) : 'monday';
        $weekly_day     = in_array( $weekly_day_raw, $allowed_days, true ) ? $weekly_day_raw : 'monday';

        $inactive_days = isset( $_POST['dps_push_inactive_days'] ) ? absint( $_POST['dps_push_inactive_days'] ) : 30;
        $inactive_days = max( 7, min( 365, $inactive_days ) );

        // --- Telegram ----------------------------------------------------------
        $telegram_token = isset( $_POST['dps_push_telegram_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_token'] ) ) : '';
        $telegram_chat  = isset( $_POST['dps_push_telegram_chat'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_push_telegram_chat'] ) ) : '';

        // --- Salvar options ----------------------------------------------------
        update_option( 'dps_push_emails_agenda', $emails_agenda );
        update_option( 'dps_push_emails_report', $emails_report );
        update_option( 'dps_push_agenda_time', $agenda_time );
        update_option( 'dps_push_report_time', $report_time );
        update_option( 'dps_push_weekly_day', $weekly_day );
        update_option( 'dps_push_weekly_time', $weekly_time );
        update_option( 'dps_push_inactive_days', $inactive_days );
        update_option( 'dps_push_telegram_token', $telegram_token );
        update_option( 'dps_push_telegram_chat', $telegram_chat );

        update_option( 'dps_push_agenda_enabled', ! empty( $_POST['dps_push_agenda_enabled'] ) );
        update_option( 'dps_push_report_enabled', ! empty( $_POST['dps_push_report_enabled'] ) );
        update_option( 'dps_push_weekly_enabled', ! empty( $_POST['dps_push_weekly_enabled'] ) );

        // Reagendar crons com novos horários.
        $email_reports = DPS_Email_Reports::get_instance();
        $email_reports->reschedule_all_crons();

        $this->redirect_after_save( true );
    }

    /**
     * Redireciona após salvar configurações.
     *
     * @since 2.0.0
     * @param bool $success Se o salvamento foi bem sucedido.
     */
    private function redirect_after_save( $success = true ) {
        $referer = wp_get_referer();

        if ( $referer ) {
            $redirect_url = remove_query_arg( [ 'updated', 'error' ], $referer );
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura para redirecionamento.
            $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

            if ( 'dps-integrations-hub' === $page ) {
                $redirect_url = admin_url( 'admin.php?page=dps-integrations-hub&tab=push' );
            } else {
                $redirect_url = admin_url( 'admin.php?page=dps-push-notifications' );
            }
        }

        $redirect_url = add_query_arg(
            $success ? 'updated' : 'error',
            '1',
            $redirect_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Valida e filtra lista de emails separados por vírgula.
     *
     * @since 2.0.0
     * @param string $input Lista de emails.
     * @return string Lista de emails válidos.
     */
    private function validate_email_list( $input ) {
        if ( empty( $input ) ) {
            return '';
        }
        $emails       = array_map( 'trim', explode( ',', $input ) );
        $valid_emails = array_filter( $emails, 'is_email' );
        return implode( ', ', $valid_emails );
    }

    /**
     * Valida horário no formato HH:MM.
     *
     * @since 2.0.0
     * @param string $time Horário.
     * @return string Horário válido ou padrão '08:00'.
     */
    private function validate_time( $time ) {
        if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
            return $time;
        }
        return '08:00';
    }
}
