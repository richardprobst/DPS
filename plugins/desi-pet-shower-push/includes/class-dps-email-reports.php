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

        // Ativação/desativação
        register_activation_hook( dirname( __DIR__ ) . '/desi-pet-shower-push-addon.php', [ $this, 'activate' ] );
        register_deactivation_hook( dirname( __DIR__ ) . '/desi-pet-shower-push-addon.php', [ $this, 'deactivate' ] );

        // Reagendar crons após salvar configurações de horário.
        add_action( 'update_option_dps_push_agenda_time', [ $this, 'reschedule_agenda_cron' ] );
        add_action( 'update_option_dps_push_report_time', [ $this, 'reschedule_report_cron' ] );
        add_action( 'update_option_dps_push_weekly_time', [ $this, 'reschedule_weekly_cron' ] );
        add_action( 'update_option_dps_push_weekly_day', [ $this, 'reschedule_weekly_cron' ] );

        // Reagendar crons quando habilitação mudar.
        add_action( 'update_option_dps_push_agenda_enabled', [ $this, 'reschedule_agenda_cron' ] );
        add_action( 'update_option_dps_push_report_enabled', [ $this, 'reschedule_report_cron' ] );
        add_action( 'update_option_dps_push_weekly_enabled', [ $this, 'reschedule_weekly_cron' ] );
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
            'post_type'      => 'dps_appointment',
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

        // Verificar se tabela existe
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE DATE(data) = %s ORDER BY data ASC", $date )
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

        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );

        $inactive = [];
        foreach ( $pets as $pet_id ) {
            $last_appt = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(pm.meta_value) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'appointment_date'
                 AND p.post_type = 'dps_appointment'
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
        $html = '<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h1 style="color: #374151;">📅 Agenda do Dia</h1>';
        $html .= '<p style="color: #6b7280;">' . date_i18n( 'l, d \d\e F \d\e Y', strtotime( $date ) ) . '</p>';

        if ( empty( $appointments ) ) {
            $html .= '<p style="padding: 20px; background: #f3f4f6; border-radius: 8px;">Nenhum agendamento para hoje.</p>';
        } else {
            $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
            $html .= '<tr style="background: #374151; color: white;">';
            $html .= '<th style="padding: 10px; text-align: left;">Horário</th>';
            $html .= '<th style="padding: 10px; text-align: left;">Pet</th>';
            $html .= '<th style="padding: 10px; text-align: left;">Cliente</th>';
            $html .= '<th style="padding: 10px; text-align: left;">Status</th>';
            $html .= '</tr>';

            foreach ( $appointments as $appt ) {
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );

                $pet = get_post( $pet_id );
                $client = get_post( $client_id );

                $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
                $html .= '<td style="padding: 10px;">' . esc_html( $time ) . '</td>';
                $html .= '<td style="padding: 10px;">' . esc_html( $pet ? $pet->post_title : '-' ) . '</td>';
                $html .= '<td style="padding: 10px;">' . esc_html( $client ? $client->post_title : '-' ) . '</td>';
                $html .= '<td style="padding: 10px;">' . esc_html( ucfirst( $status ?: 'pendente' ) ) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= '<p style="margin-top: 30px; color: #6b7280; font-size: 12px;">Este email foi enviado automaticamente pelo desi.pet by PRObst.</p>';
        $html .= '</body></html>';

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

        $html = '<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h1 style="color: #374151;">💰 Relatório Financeiro</h1>';
        $html .= '<p style="color: #6b7280;">' . date_i18n( 'l, d \d\e F \d\e Y', strtotime( $date ) ) . '</p>';

        // Cards de resumo
        $html .= '<div style="display: flex; gap: 15px; margin: 20px 0;">';
        $html .= '<div style="flex: 1; padding: 15px; background: #d1fae5; border-radius: 8px; text-align: center;">';
        $html .= '<div style="font-size: 24px; color: #10b981; font-weight: bold;">R$ ' . number_format( $total_receitas, 2, ',', '.' ) . '</div>';
        $html .= '<div style="color: #6b7280; font-size: 12px;">Receitas</div>';
        $html .= '</div>';
        $html .= '<div style="flex: 1; padding: 15px; background: #fee2e2; border-radius: 8px; text-align: center;">';
        $html .= '<div style="font-size: 24px; color: #ef4444; font-weight: bold;">R$ ' . number_format( $total_despesas, 2, ',', '.' ) . '</div>';
        $html .= '<div style="color: #6b7280; font-size: 12px;">Despesas</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Resumo de atendimentos
        $html .= '<h2 style="color: #374151; margin-top: 30px;">📋 Atendimentos</h2>';
        $html .= '<p>' . count( $appointments ) . ' atendimento(s) realizado(s)</p>';

        // Lista de transações
        if ( ! empty( $transactions ) ) {
            $html .= '<h2 style="color: #374151; margin-top: 30px;">📊 Transações</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="background: #374151; color: white;">';
            $html .= '<th style="padding: 8px; text-align: left;">Descrição</th>';
            $html .= '<th style="padding: 8px; text-align: right;">Valor</th>';
            $html .= '</tr>';

            foreach ( $transactions as $trans ) {
                $color = $trans->tipo === 'receita' ? '#10b981' : '#ef4444';
                $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
                $html .= '<td style="padding: 8px;">' . esc_html( $trans->descricao ) . '</td>';
                $html .= '<td style="padding: 8px; text-align: right; color: ' . $color . ';">R$ ' . number_format( (float) $trans->valor, 2, ',', '.' ) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= '<p style="margin-top: 30px; color: #6b7280; font-size: 12px;">Este email foi enviado automaticamente pelo desi.pet by PRObst.</p>';
        $html .= '</body></html>';

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
        $text .= "💵 Receitas: R$ " . number_format( $total_receitas, 2, ',', '.' ) . "\n";
        $text .= "�� Despesas: R$ " . number_format( $total_despesas, 2, ',', '.' ) . "\n";
        $text .= "📊 Saldo: R$ " . number_format( $total_receitas - $total_despesas, 2, ',', '.' );

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
        $html = '<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h1 style="color: #374151;">🐾 Pets Inativos</h1>';
        $html .= '<p style="color: #6b7280;">Pets sem atendimento há mais de ' . $days . ' dias</p>';

        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $html .= '<tr style="background: #374151; color: white;">';
        $html .= '<th style="padding: 10px; text-align: left;">Pet</th>';
        $html .= '<th style="padding: 10px; text-align: left;">Último Atendimento</th>';
        $html .= '</tr>';

        foreach ( $inactive_pets as $item ) {
            $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
            $html .= '<td style="padding: 10px;">' . esc_html( $item['pet']->post_title ) . '</td>';
            $html .= '<td style="padding: 10px;">' . ( $item['last_date'] ? date_i18n( 'd/m/Y', strtotime( $item['last_date'] ) ) : 'Nunca' ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '<p style="margin-top: 30px; color: #6b7280; font-size: 12px;">Este email foi enviado automaticamente pelo desi.pet by PRObst.</p>';
        $html .= '</body></html>';

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
        $token = get_option( 'dps_push_telegram_token' );
        $chat_id = get_option( 'dps_push_telegram_chat' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

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
            $this->log( 'info', 'Mensagem enviada para Telegram', [ 'context' => $context ] );
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
            DPS_Logger::$level( $message, $context, 'email-reports' );
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
