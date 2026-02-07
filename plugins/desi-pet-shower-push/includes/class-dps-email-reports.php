<?php
/**
 * Classe de Relatórios por Email para o DPS.
 *
 * Implementa envio automático de relatórios por email e Telegram:
 * - Resumo diário de agendamentos
 * - Relatório financeiro diário
 * - Relatório semanal de pets inativos
 *
 * @package DPS_Push_Addon
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Relatórios por Email.
 */
class DPS_Email_Reports {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Email_Reports|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Email_Reports
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor.
     */
    private function __construct() {
        // Cron hooks
        add_action( 'dps_send_agenda_notification', [ $this, 'send_agenda_notification' ] );
        add_action( 'dps_send_daily_report', [ $this, 'send_daily_report' ] );
        add_action( 'dps_send_weekly_inactive_report', [ $this, 'send_weekly_inactive_report' ] );

        // Hook para Telegram
        add_action( 'dps_send_push_notification', [ $this, 'send_to_telegram' ], 10, 2 );

        // Reagendar crons após salvar configurações de horário.
        add_action( 'update_option_dps_push_agenda_time', [ $this, 'reschedule_agenda_cron' ] );
        add_action( 'update_option_dps_push_report_time', [ $this, 'reschedule_report_cron' ] );
        add_action( 'update_option_dps_push_weekly_time', [ $this, 'reschedule_weekly_cron' ] );
        add_action( 'update_option_dps_push_weekly_day', [ $this, 'reschedule_weekly_cron' ] );

        // Reagendar crons quando habilitação mudar.
        add_action( 'update_option_dps_push_agenda_enabled', [ $this, 'reschedule_agenda_cron' ] );
        add_action( 'update_option_dps_push_report_enabled', [ $this, 'reschedule_report_cron' ] );
        add_action( 'update_option_dps_push_weekly_enabled', [ $this, 'reschedule_weekly_cron' ] );

        // Agenda crons que estão faltando (fallback caso ativação não tenha sido executada).
        // Executado em admin_init para não impactar performance do frontend.
        add_action( 'admin_init', [ $this, 'maybe_schedule_crons' ] );
    }

    /**
     * Agenda crons se estiverem faltando (fallback).
     * 
     * Este método garante que os crons sejam agendados mesmo que o hook
     * de ativação não tenha sido executado corretamente.
     *
     * @since 1.3.1
     */
    public function maybe_schedule_crons() {
        $this->schedule_crons();
    }

    /**
     * Ativa os cron jobs.
     */
    public function activate() {
        $this->schedule_crons();
    }

    /**
     * Desativa os cron jobs.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'dps_send_agenda_notification' );
        wp_clear_scheduled_hook( 'dps_send_daily_report' );
        wp_clear_scheduled_hook( 'dps_send_weekly_inactive_report' );
    }

    /**
     * Agenda os cron jobs.
     */
    public function schedule_crons() {
        // Agenda diária
        if ( get_option( 'dps_push_agenda_enabled', true ) && ! wp_next_scheduled( 'dps_send_agenda_notification' ) ) {
            $time = $this->get_next_daily_timestamp( get_option( 'dps_push_agenda_time', '08:00' ) );
            wp_schedule_event( $time, 'daily', 'dps_send_agenda_notification' );
        }

        // Relatório financeiro
        if ( get_option( 'dps_push_report_enabled', true ) && ! wp_next_scheduled( 'dps_send_daily_report' ) ) {
            $time = $this->get_next_daily_timestamp( get_option( 'dps_push_report_time', '19:00' ) );
            wp_schedule_event( $time, 'daily', 'dps_send_daily_report' );
        }

        // Relatório semanal
        if ( get_option( 'dps_push_weekly_enabled', true ) && ! wp_next_scheduled( 'dps_send_weekly_inactive_report' ) ) {
            $time = $this->get_next_weekly_timestamp(
                get_option( 'dps_push_weekly_day', 'monday' ),
                get_option( 'dps_push_weekly_time', '08:00' )
            );
            wp_schedule_event( $time, 'weekly', 'dps_send_weekly_inactive_report' );
        }
    }

    /**
     * Calcula o próximo timestamp para horário diário.
     *
     * @param string $time Horário no formato HH:MM.
     * @return int Timestamp.
     */
    private function get_next_daily_timestamp( $time ) {
        $timezone = $this->get_wp_timezone();
        $now = new DateTimeImmutable( 'now', $timezone );
        list( $hour, $minute ) = explode( ':', $time );
        $scheduled = $now->setTime( (int) $hour, (int) $minute, 0 );

        if ( $scheduled <= $now ) {
            $scheduled = $scheduled->modify( '+1 day' );
        }

        return $scheduled->getTimestamp();
    }

    /**
     * Calcula o próximo timestamp para horário semanal.
     *
     * @param string $day  Dia da semana em inglês.
     * @param string $time Horário no formato HH:MM.
     * @return int Timestamp.
     */
    private function get_next_weekly_timestamp( $day, $time ) {
        $timezone = $this->get_wp_timezone();
        $now = new DateTimeImmutable( 'now', $timezone );
        list( $hour, $minute ) = explode( ':', $time );

        $scheduled = $now->modify( "next {$day}" )->setTime( (int) $hour, (int) $minute, 0 );

        return $scheduled->getTimestamp();
    }

    /**
     * Obtém o timezone do WordPress.
     *
     * @return DateTimeZone
     */
    private function get_wp_timezone() {
        $timezone_string = get_option( 'timezone_string' );
        if ( $timezone_string ) {
            return new DateTimeZone( $timezone_string );
        }

        $offset = (float) get_option( 'gmt_offset', 0 );
        $hours = (int) $offset;
        $minutes = abs( ( $offset - $hours ) * 60 );
        $sign = $offset >= 0 ? '+' : '-';

        return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes ) );
    }

    /**
     * Envia o resumo diário de agendamentos.
     */
    public function send_agenda_notification() {
        if ( ! get_option( 'dps_push_agenda_enabled', true ) ) {
            return;
        }

        $recipients = $this->get_recipients( 'dps_push_emails_agenda' );
        if ( empty( $recipients ) ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $appointments = $this->get_appointments_for_date( $today );

        $subject = sprintf(
            __( '📅 Agenda do dia %s - %d agendamento(s)', 'dps-push-addon' ),
            date_i18n( 'd/m/Y', strtotime( $today ) ),
            count( $appointments )
        );

        $html = $this->build_agenda_html( $appointments, $today );
        $html = apply_filters( 'dps_push_notification_content', $html, $appointments );

        $recipients = apply_filters( 'dps_push_notification_recipients', $recipients );

        $this->send_emails( $recipients, $subject, $html );

        // Enviar para Telegram
        do_action( 'dps_send_push_notification', $this->build_agenda_text( $appointments, $today ), 'agenda' );

        $this->log( 'info', 'Agenda diária enviada', [ 'recipients' => count( $recipients ), 'appointments' => count( $appointments ) ] );
    }

    /**
     * Envia o relatório financeiro diário.
     */
    public function send_daily_report() {
        if ( ! get_option( 'dps_push_report_enabled', true ) ) {
            return;
        }

        $recipients = $this->get_recipients( 'dps_push_emails_report' );
        if ( empty( $recipients ) ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $appointments = $this->get_appointments_for_date( $today );
        $transactions = $this->get_transactions_for_date( $today );

        $subject = sprintf(
            __( '💰 Relatório Financeiro - %s', 'dps-push-addon' ),
            date_i18n( 'd/m/Y', strtotime( $today ) )
        );

        $html = $this->build_report_html( $appointments, $transactions, $today );
        $html = apply_filters( 'dps_daily_report_html', $html, $appointments, $transactions );

        $recipients = apply_filters( 'dps_daily_report_recipients', $recipients );

        $this->send_emails( $recipients, $subject, $html );

        // Enviar para Telegram
        do_action( 'dps_send_push_notification', $this->build_report_text( $appointments, $transactions, $today ), 'report' );

        $this->log( 'info', 'Relatório financeiro enviado', [ 'recipients' => count( $recipients ), 'transactions' => count( $transactions ) ] );
    }

    /**
     * Envia o relatório semanal de pets inativos.
     */
    public function send_weekly_inactive_report() {
        if ( ! get_option( 'dps_push_weekly_enabled', true ) ) {
            return;
        }

        $recipients = $this->get_recipients( 'dps_push_emails_report' );
        if ( empty( $recipients ) ) {
            return;
        }

        $inactive_days = (int) get_option( 'dps_push_inactive_days', 30 );
        $inactive_pets = $this->get_inactive_pets( $inactive_days );

        if ( empty( $inactive_pets ) ) {
            return;
        }

        $subject = sprintf(
            __( '🐾 %d pet(s) sem atendimento há mais de %d dias', 'dps-push-addon' ),
            count( $inactive_pets ),
            $inactive_days
        );

        $html = $this->build_inactive_pets_html( $inactive_pets, $inactive_days );

        $recipients = apply_filters( 'dps_weekly_inactive_report_recipients', $recipients );

        $this->send_emails( $recipients, $subject, $html );

        // Enviar para Telegram
        do_action( 'dps_send_push_notification', $this->build_inactive_pets_text( $inactive_pets, $inactive_days ), 'weekly' );

        $this->log( 'info', 'Relatório semanal enviado', [ 'recipients' => count( $recipients ), 'inactive_pets' => count( $inactive_pets ) ] );
    }

    /**
     * Obtém destinatários de uma opção.
     *
     * @param string $option_key Chave da opção.
     * @return array Lista de emails válidos.
     */
    private function get_recipients( $option_key ) {
        $raw = get_option( $option_key, get_option( 'admin_email' ) );
        if ( is_array( $raw ) ) {
            $emails = $raw;
        } else {
            $emails = array_map( 'trim', explode( ',', $raw ) );
        }

        return array_filter( $emails, 'is_email' );
    }

    /**
     * Obtém agendamentos para uma data.
     *
     * @param string $date Data no formato Y-m-d.
     * @return array Lista de agendamentos.
     */
    private function get_appointments_for_date( $date ) {
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'appointment_date',
                    'value' => $date,
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
        ];

        return get_posts( $args );
    }

    /**
     * Obtém transações para uma data.
     *
     * @param string $date Data no formato Y-m-d.
     * @return array Lista de transações.
     */
    private function get_transactions_for_date( $date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Verificar se tabela existe (usando prepare para evitar SQL Injection).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return [];
        }

        // Sanitizar a data para garantir formato válido.
        $sanitized_date = sanitize_text_field( $date );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized_date ) ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe.
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE DATE(data) = %s ORDER BY data ASC", $sanitized_date )
        );
    }

    /**
     * Obtém pets inativos.
     *
     * @param int $days Dias de inatividade.
     * @return array Lista de pets inativos.
     */
    private function get_inactive_pets( $days ) {
        global $wpdb;

        // Sanitizar e validar dias (deve ser um inteiro positivo).
        $days = absint( $days );
        if ( $days < 1 ) {
            $days = 30;
        }

        $cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );

        $inactive = [];
        foreach ( $pets as $pet_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $last_appt = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(pm.meta_value) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'appointment_date'
                 AND p.post_type = 'dps_agendamento'
                 AND p.post_status = 'publish'
                 AND EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} pm2
                     WHERE pm2.post_id = pm.post_id
                     AND pm2.meta_key = 'appointment_pet_id'
                     AND pm2.meta_value = %d
                 )",
                $pet_id
            ) );

            if ( ! $last_appt || $last_appt < $cutoff_date ) {
                $pet = get_post( $pet_id );
                if ( $pet ) {
                    $inactive[] = [
                        'pet'       => $pet,
                        'last_date' => $last_appt,
                    ];
                }
            }
        }

        return $inactive;
    }

    /**
     * Constrói HTML da agenda.
     *
     * @param array  $appointments Agendamentos.
     * @param string $date         Data.
     * @return string HTML.
     */
    private function build_agenda_html( $appointments, $date ) {
        $count = count( $appointments );
        $html  = $this->get_email_header( '📅', __( 'Agenda do Dia', 'dps-push-addon' ), date_i18n( 'l, d \d\e F \d\e Y', strtotime( $date ) ) );

        // Card de resumo
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">';
        $html .= '<tr><td style="background:#d4e4ff;border-radius:12px;padding:20px;text-align:center;">';
        $html .= '<div style="font-size:32px;font-weight:700;color:#0b6bcb;">' . $count . '</div>';
        $html .= '<div style="font-size:13px;color:#001c3a;margin-top:4px;">' . sprintf( _n( 'agendamento', 'agendamentos', $count, 'dps-push-addon' ), $count ) . '</div>';
        $html .= '</td></tr></table>';

        if ( empty( $appointments ) ) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
            $html .= '<tr><td style="padding:24px;background:#f2f3fa;border-radius:12px;text-align:center;color:#43474e;font-size:14px;">';
            $html .= esc_html__( 'Nenhum agendamento para hoje. Aproveite para organizar a agenda!', 'dps-push-addon' );
            $html .= '</td></tr></table>';
        } else {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;border:1px solid #c3c6cf;">';
            $html .= '<tr style="background:#0b6bcb;">';
            $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Horário', 'dps-push-addon' ) . '</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Pet', 'dps-push-addon' ) . '</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Cliente', 'dps-push-addon' ) . '</th>';
            $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Status', 'dps-push-addon' ) . '</th>';
            $html .= '</tr>';

            foreach ( $appointments as $i => $appt ) {
                $time      = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $status    = get_post_meta( $appt->ID, 'appointment_status', true );

                $pet    = get_post( $pet_id );
                $client = get_post( $client_id );

                $bg = ( $i % 2 === 0 ) ? '#ffffff' : '#f2f3fa';

                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="padding:12px 16px;font-size:14px;color:#191c20;font-weight:600;">' . esc_html( $time ) . '</td>';
                $html .= '<td style="padding:12px 16px;font-size:14px;color:#191c20;">' . esc_html( $pet ? $pet->post_title : '-' ) . '</td>';
                $html .= '<td style="padding:12px 16px;font-size:14px;color:#43474e;">' . esc_html( $client ? $client->post_title : '-' ) . '</td>';
                $html .= '<td style="padding:12px 16px;">' . $this->get_status_badge( $status ?: 'pendente' ) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= $this->get_email_footer();
        return $html;
    }

    /**
     * Constrói texto da agenda para Telegram.
     *
     * @param array  $appointments Agendamentos.
     * @param string $date         Data.
     * @return string Texto.
     */
    private function build_agenda_text( $appointments, $date ) {
        $text = "📅 *Agenda do Dia*\n";
        $text .= date_i18n( 'd/m/Y', strtotime( $date ) ) . "\n\n";

        if ( empty( $appointments ) ) {
            $text .= "Nenhum agendamento para hoje.";
        } else {
            $text .= count( $appointments ) . " agendamento(s):\n\n";
            foreach ( $appointments as $appt ) {
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet = get_post( $pet_id );
                $text .= "• {$time} - " . ( $pet ? $pet->post_title : 'Pet' ) . "\n";
            }
        }

        return $text;
    }

    /**
     * Constrói HTML do relatório financeiro.
     *
     * @param array  $appointments  Agendamentos.
     * @param array  $transactions  Transações.
     * @param string $date          Data.
     * @return string HTML.
     */
    private function build_report_html( $appointments, $transactions, $date ) {
        $total_receitas = 0;
        $total_despesas = 0;

        foreach ( $transactions as $trans ) {
            if ( $trans->tipo === 'receita' ) {
                $total_receitas += (float) $trans->valor;
            } else {
                $total_despesas += (float) $trans->valor;
            }
        }

        $saldo = $total_receitas - $total_despesas;

        $html = $this->get_email_header( '💰', __( 'Relatório Financeiro', 'dps-push-addon' ), date_i18n( 'l, d \d\e F \d\e Y', strtotime( $date ) ) );

        // Cards de resumo (3 colunas: receitas, despesas, saldo)
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">';
        $html .= '<tr>';

        // Receitas
        $html .= '<td width="33%" style="padding:0 6px 0 0;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="background:#a8f5b5;border-radius:12px;padding:16px;text-align:center;">';
        $html .= '<div style="font-size:11px;color:#00210a;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">📈 ' . esc_html__( 'Receitas', 'dps-push-addon' ) . '</div>';
        $html .= '<div style="font-size:20px;font-weight:700;color:#1a7a3a;">' . DPS_Money_Helper::format_currency_from_decimal( $total_receitas ) . '</div>';
        $html .= '</td></tr></table></td>';

        // Despesas
        $html .= '<td width="33%" style="padding:0 3px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="background:#ffdad6;border-radius:12px;padding:16px;text-align:center;">';
        $html .= '<div style="font-size:11px;color:#410002;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">📉 ' . esc_html__( 'Despesas', 'dps-push-addon' ) . '</div>';
        $html .= '<div style="font-size:20px;font-weight:700;color:#ba1a1a;">' . DPS_Money_Helper::format_currency_from_decimal( $total_despesas ) . '</div>';
        $html .= '</td></tr></table></td>';

        // Saldo
        $saldo_bg    = $saldo >= 0 ? '#d4e4ff' : '#ffdea3';
        $saldo_color = $saldo >= 0 ? '#0b6bcb' : '#8b6914';
        $saldo_label_color = $saldo >= 0 ? '#001c3a' : '#2c1f00';
        $html .= '<td width="33%" style="padding:0 0 0 6px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="background:' . $saldo_bg . ';border-radius:12px;padding:16px;text-align:center;">';
        $html .= '<div style="font-size:11px;color:' . $saldo_label_color . ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">💵 ' . esc_html__( 'Saldo', 'dps-push-addon' ) . '</div>';
        $html .= '<div style="font-size:20px;font-weight:700;color:' . $saldo_color . ';">' . DPS_Money_Helper::format_currency_from_decimal( $saldo ) . '</div>';
        $html .= '</td></tr></table></td>';

        $html .= '</tr></table>';

        // Resumo de atendimentos
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">';
        $html .= '<tr><td style="background:#f2f3fa;border-radius:12px;padding:16px;">';
        $html .= '<span style="font-size:14px;color:#191c20;">📋 <strong>' . count( $appointments ) . '</strong> ' . esc_html__( 'atendimento(s) realizado(s) no dia', 'dps-push-addon' ) . '</span>';
        $html .= '</td></tr></table>';

        // Lista de transações
        if ( ! empty( $transactions ) ) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">';
            $html .= '<tr><td style="font-size:15px;font-weight:600;color:#191c20;padding-bottom:12px;">📊 ' . esc_html__( 'Transações', 'dps-push-addon' ) . '</td></tr></table>';

            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;border:1px solid #c3c6cf;">';
            $html .= '<tr style="background:#0b6bcb;">';
            $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Descrição', 'dps-push-addon' ) . '</th>';
            $html .= '<th style="padding:12px 16px;text-align:right;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Valor', 'dps-push-addon' ) . '</th>';
            $html .= '</tr>';

            foreach ( $transactions as $i => $trans ) {
                $is_receita = $trans->tipo === 'receita';
                $color = $is_receita ? '#1a7a3a' : '#ba1a1a';
                $prefix = $is_receita ? '+' : '-';
                $bg = ( $i % 2 === 0 ) ? '#ffffff' : '#f2f3fa';

                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="padding:12px 16px;font-size:14px;color:#191c20;">' . esc_html( $trans->descricao ) . '</td>';
                $html .= '<td style="padding:12px 16px;text-align:right;font-size:14px;font-weight:600;color:' . $color . ';">' . $prefix . ' ' . DPS_Money_Helper::format_currency_from_decimal( (float) $trans->valor ) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= $this->get_email_footer();
        return $html;
    }

    /**
     * Constrói texto do relatório para Telegram.
     *
     * @param array  $appointments  Agendamentos.
     * @param array  $transactions  Transações.
     * @param string $date          Data.
     * @return string Texto.
     */
    private function build_report_text( $appointments, $transactions, $date ) {
        $total_receitas = 0;
        $total_despesas = 0;

        foreach ( $transactions as $trans ) {
            if ( $trans->tipo === 'receita' ) {
                $total_receitas += (float) $trans->valor;
            } else {
                $total_despesas += (float) $trans->valor;
            }
        }

        $text = "💰 *Relatório Financeiro*\n";
        $text .= date_i18n( 'd/m/Y', strtotime( $date ) ) . "\n\n";
        $text .= "📋 " . count( $appointments ) . " atendimento(s)\n";
        $text .= "💵 Receitas: " . DPS_Money_Helper::format_currency_from_decimal( $total_receitas ) . "\n";
        $text .= "💸 Despesas: " . DPS_Money_Helper::format_currency_from_decimal( $total_despesas ) . "\n";
        $text .= "📊 Saldo: " . DPS_Money_Helper::format_currency_from_decimal( $total_receitas - $total_despesas );

        return $text;
    }

    /**
     * Constrói HTML do relatório de pets inativos.
     *
     * @param array $inactive_pets Pets inativos.
     * @param int   $days          Dias de inatividade.
     * @return string HTML.
     */
    private function build_inactive_pets_html( $inactive_pets, $days ) {
        $count = count( $inactive_pets );
        $html  = $this->get_email_header(
            '🐾',
            __( 'Pets Inativos', 'dps-push-addon' ),
            sprintf( __( 'Pets sem atendimento há mais de %d dias', 'dps-push-addon' ), $days )
        );

        // Card de alerta
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">';
        $html .= '<tr><td style="background:#ffdea3;border-radius:12px;padding:20px;text-align:center;">';
        $html .= '<div style="font-size:32px;font-weight:700;color:#8b6914;">' . $count . '</div>';
        $html .= '<div style="font-size:13px;color:#2c1f00;margin-top:4px;">' . sprintf( _n( 'pet inativo', 'pets inativos', $count, 'dps-push-addon' ), $count ) . '</div>';
        $html .= '</td></tr></table>';

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;overflow:hidden;border:1px solid #c3c6cf;">';
        $html .= '<tr style="background:#0b6bcb;">';
        $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Pet', 'dps-push-addon' ) . '</th>';
        $html .= '<th style="padding:12px 16px;text-align:left;color:#ffffff;font-size:13px;font-weight:600;">' . esc_html__( 'Último Atendimento', 'dps-push-addon' ) . '</th>';
        $html .= '</tr>';

        foreach ( $inactive_pets as $i => $item ) {
            $bg = ( $i % 2 === 0 ) ? '#ffffff' : '#f2f3fa';
            $last_date = $item['last_date'] ? date_i18n( 'd/m/Y', strtotime( $item['last_date'] ) ) : esc_html__( 'Nunca', 'dps-push-addon' );

            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="padding:12px 16px;font-size:14px;color:#191c20;font-weight:500;">' . esc_html( $item['pet']->post_title ) . '</td>';
            $html .= '<td style="padding:12px 16px;font-size:14px;color:#43474e;">' . $last_date . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        // Dica de reengajamento
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">';
        $html .= '<tr><td style="background:#f2f3fa;border-radius:12px;padding:16px;font-size:13px;color:#43474e;">';
        $html .= '💡 <strong>' . esc_html__( 'Dica:', 'dps-push-addon' ) . '</strong> ';
        $html .= esc_html__( 'Considere enviar uma mensagem ou oferta especial para os tutores desses pets!', 'dps-push-addon' );
        $html .= '</td></tr></table>';

        $html .= $this->get_email_footer();
        return $html;
    }

    /**
     * Constrói texto de pets inativos para Telegram.
     *
     * @param array $inactive_pets Pets inativos.
     * @param int   $days          Dias de inatividade.
     * @return string Texto.
     */
    private function build_inactive_pets_text( $inactive_pets, $days ) {
        $text = "🐾 *Pets Inativos*\n";
        $text .= "Sem atendimento há mais de {$days} dias\n\n";
        $text .= count( $inactive_pets ) . " pet(s):\n\n";

        foreach ( array_slice( $inactive_pets, 0, 10 ) as $item ) {
            $last = $item['last_date'] ? date_i18n( 'd/m', strtotime( $item['last_date'] ) ) : 'nunca';
            $text .= "• " . $item['pet']->post_title . " (último: {$last})\n";
        }

        if ( count( $inactive_pets ) > 10 ) {
            $text .= "\n... e mais " . ( count( $inactive_pets ) - 10 ) . " pet(s)";
        }

        return $text;
    }

    /**
     * Gera o cabeçalho padrão M3 do email.
     *
     * @since 1.4.0
     * @param string $icon     Emoji do ícone.
     * @param string $title    Título do relatório.
     * @param string $subtitle Subtítulo (data/contexto).
     * @return string HTML.
     */
    private function get_email_header( $icon, $title, $subtitle ) {
        $site_name = get_bloginfo( 'name' );

        $html  = '<!DOCTYPE html>';
        $html .= '<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
        $html .= '<title>' . esc_html( $title ) . '</title>';
        $html .= '</head>';
        $html .= '<body style="margin:0;padding:0;background:#f8f9ff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">';

        // Wrapper de centralização
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9ff;">';
        $html .= '<tr><td align="center" style="padding:32px 16px;">';

        // Container principal (card)
        $html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #c3c6cf;">';

        // Header com cor primária
        $html .= '<tr><td style="background:#0b6bcb;padding:28px 32px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td>';
        $html .= '<div style="font-size:28px;margin-bottom:4px;">' . $icon . '</div>';
        $html .= '<div style="font-size:22px;font-weight:700;color:#ffffff;margin-bottom:4px;">' . esc_html( $title ) . '</div>';
        $html .= '<div style="font-size:14px;color:#d4e4ff;">' . esc_html( $subtitle ) . '</div>';
        $html .= '</td>';
        $html .= '<td style="text-align:right;vertical-align:top;">';
        $html .= '<div style="font-size:12px;color:#d4e4ff;font-weight:500;">' . esc_html( $site_name ) . '</div>';
        $html .= '</td></tr></table>';
        $html .= '</td></tr>';

        // Início da área de conteúdo
        $html .= '<tr><td style="padding:28px 32px;">';

        return $html;
    }

    /**
     * Gera o rodapé padrão M3 do email.
     *
     * @since 1.4.0
     * @return string HTML.
     */
    private function get_email_footer() {
        $html  = '</td></tr>'; // Fecha a área de conteúdo

        // Rodapé
        $html .= '<tr><td style="background:#f2f3fa;padding:20px 32px;border-top:1px solid #c3c6cf;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="font-size:12px;color:#43474e;line-height:1.6;">';
        $html .= esc_html__( 'Este email foi enviado automaticamente pelo', 'dps-push-addon' );
        $html .= ' <strong style="color:#0b6bcb;">desi.pet by PRObst</strong>';
        $html .= '</td>';
        $html .= '<td style="text-align:right;font-size:11px;color:#73777f;">';
        $html .= date_i18n( 'H:i' ) . ' &bull; ' . date_i18n( 'd/m/Y' );
        $html .= '</td></tr></table>';
        $html .= '</td></tr>';

        $html .= '</table>'; // Fecha container principal

        $html .= '</td></tr></table>'; // Fecha wrapper de centralização
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Gera badge de status inline para emails.
     *
     * @since 1.4.0
     * @param string $status Status do agendamento.
     * @return string HTML do badge.
     */
    private function get_status_badge( $status ) {
        $colors = [
            'pendente'   => [ 'bg' => '#ffdea3', 'text' => '#2c1f00' ],
            'confirmado' => [ 'bg' => '#d4e4ff', 'text' => '#001c3a' ],
            'finalizado' => [ 'bg' => '#a8f5b5', 'text' => '#00210a' ],
            'pago'       => [ 'bg' => '#a8f5b5', 'text' => '#00210a' ],
            'cancelado'  => [ 'bg' => '#ffdad6', 'text' => '#410002' ],
        ];

        $key = strtolower( $status );
        $c   = $colors[ $key ] ?? [ 'bg' => '#ecedf4', 'text' => '#43474e' ];

        return '<span style="display:inline-block;padding:4px 12px;border-radius:9999px;font-size:12px;font-weight:600;background:' . $c['bg'] . ';color:' . $c['text'] . ';">'
            . esc_html( ucfirst( $status ) )
            . '</span>';
    }

    /**
     * Envia emails para destinatários.
     *
     * @param array  $recipients Destinatários.
     * @param string $subject    Assunto.
     * @param string $html       Conteúdo HTML.
     */
    private function send_emails( $recipients, $subject, $html ) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        foreach ( $recipients as $email ) {
            if ( is_email( $email ) ) {
                wp_mail( $email, $subject, $html, $headers );
            }
        }
    }

    /**
     * Envia mensagem para o Telegram.
     *
     * @param string $message Mensagem.
     * @param string $context Contexto (agenda, report, weekly).
     */
    public function send_to_telegram( $message, $context = '' ) {
        $token   = get_option( 'dps_push_telegram_token' );
        $chat_id = get_option( 'dps_push_telegram_chat' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return;
        }

        // Validar formato do token (formato: 123456789:ABCdefGHI...).
        if ( ! preg_match( '/^\d{8,12}:[A-Za-z0-9_-]{30,50}$/', $token ) ) {
            $this->log( 'error', 'Token do Telegram com formato inválido', [ 'context' => $context ] );
            return;
        }

        // Validar chat_id (número ou número negativo para grupos).
        if ( ! preg_match( '/^-?\d+$/', $chat_id ) ) {
            $this->log( 'error', 'Chat ID do Telegram com formato inválido', [ 'context' => $context ] );
            return;
        }

        // Construir URL segura.
        $url = 'https://api.telegram.org/bot' . urlencode( $token ) . '/sendMessage';

        $response = wp_remote_post( $url, [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            $this->log( 'error', 'Erro ao enviar para Telegram: ' . $response->get_error_message(), [ 'context' => $context ] );
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( isset( $data['ok'] ) && $data['ok'] ) {
                $this->log( 'info', 'Mensagem enviada para Telegram', [ 'context' => $context ] );
            } else {
                $error_desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : 'Erro desconhecido';
                $this->log( 'error', 'Telegram retornou erro: ' . $error_desc, [ 'context' => $context ] );
            }
        }
    }

    /**
     * Registra log.
     *
     * @param string $level   Nível (info, error, warning).
     * @param string $message Mensagem.
     * @param array  $context Contexto adicional.
     */
    private function log( $level, $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            // Validar nível de log para evitar execução de métodos arbitrários.
            $allowed_levels = [ 'info', 'error', 'warning', 'debug' ];
            if ( ! in_array( $level, $allowed_levels, true ) ) {
                $level = 'info';
            }
            call_user_func( [ 'DPS_Logger', $level ], $message, $context, 'email-reports' );
        }
    }

    /**
     * Reagenda cron da agenda.
     */
    public function reschedule_agenda_cron() {
        wp_clear_scheduled_hook( 'dps_send_agenda_notification' );
        if ( get_option( 'dps_push_agenda_enabled', true ) ) {
            $time = $this->get_next_daily_timestamp( get_option( 'dps_push_agenda_time', '08:00' ) );
            wp_schedule_event( $time, 'daily', 'dps_send_agenda_notification' );
        }
    }

    /**
     * Reagenda cron do relatório.
     */
    public function reschedule_report_cron() {
        wp_clear_scheduled_hook( 'dps_send_daily_report' );
        if ( get_option( 'dps_push_report_enabled', true ) ) {
            $time = $this->get_next_daily_timestamp( get_option( 'dps_push_report_time', '19:00' ) );
            wp_schedule_event( $time, 'daily', 'dps_send_daily_report' );
        }
    }

    /**
     * Reagenda cron semanal.
     */
    public function reschedule_weekly_cron() {
        wp_clear_scheduled_hook( 'dps_send_weekly_inactive_report' );
        if ( get_option( 'dps_push_weekly_enabled', true ) ) {
            $time = $this->get_next_weekly_timestamp(
                get_option( 'dps_push_weekly_day', 'monday' ),
                get_option( 'dps_push_weekly_time', '08:00' )
            );
            wp_schedule_event( $time, 'weekly', 'dps_send_weekly_inactive_report' );
        }
    }

    /**
     * Reagenda todos os crons de uma vez.
     *
     * Este método deve ser chamado após salvar configurações para garantir
     * que todos os crons sejam reagendados com os novos horários.
     *
     * @since 1.3.1
     */
    public function reschedule_all_crons() {
        // Limpar cache de opções para garantir valores atualizados.
        wp_cache_delete( 'dps_push_agenda_time', 'options' );
        wp_cache_delete( 'dps_push_agenda_enabled', 'options' );
        wp_cache_delete( 'dps_push_report_time', 'options' );
        wp_cache_delete( 'dps_push_report_enabled', 'options' );
        wp_cache_delete( 'dps_push_weekly_time', 'options' );
        wp_cache_delete( 'dps_push_weekly_day', 'options' );
        wp_cache_delete( 'dps_push_weekly_enabled', 'options' );

        $this->reschedule_agenda_cron();
        $this->reschedule_report_cron();
        $this->reschedule_weekly_cron();

        // Formatar timestamps para legibilidade no log.
        $next_agenda = wp_next_scheduled( 'dps_send_agenda_notification' );
        $next_report = wp_next_scheduled( 'dps_send_daily_report' );
        $next_weekly = wp_next_scheduled( 'dps_send_weekly_inactive_report' );

        $this->log( 'info', 'Todos os crons de relatórios reagendados', [
            'agenda_time'   => get_option( 'dps_push_agenda_time', '08:00' ),
            'report_time'   => get_option( 'dps_push_report_time', '19:00' ),
            'weekly_day'    => get_option( 'dps_push_weekly_day', 'monday' ),
            'weekly_time'   => get_option( 'dps_push_weekly_time', '08:00' ),
            'next_agenda'   => $next_agenda ? date_i18n( 'Y-m-d H:i:s', $next_agenda ) : null,
            'next_report'   => $next_report ? date_i18n( 'Y-m-d H:i:s', $next_report ) : null,
            'next_weekly'   => $next_weekly ? date_i18n( 'Y-m-d H:i:s', $next_weekly ) : null,
        ] );
    }

    /**
     * Envia teste de relatório.
     *
     * @param string $type Tipo (agenda, report, weekly).
     * @return bool Sucesso.
     */
    public function send_test( $type ) {
        switch ( $type ) {
            case 'agenda':
                $this->send_agenda_notification();
                return true;
            case 'report':
                $this->send_daily_report();
                return true;
            case 'weekly':
                $this->send_weekly_inactive_report();
                return true;
        }
        return false;
    }
}
