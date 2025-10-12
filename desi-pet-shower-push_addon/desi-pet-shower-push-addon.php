<?php
/**
 * Plugin Name:       Desi Pet Shower – Push Notifications Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Envia notificações diárias às 08:00 com o resumo dos agendamentos do dia. Pode ser adaptado para serviços de push externos.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-push-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Push_Notifications_Addon {

    /**
     * Inicializa hooks
     */
    public function __construct() {
        // Agenda a tarefa diária ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        // Remove o evento agendado ao desativar
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        // Hook para enviar notificação quando o cron rodar
        add_action( 'dps_send_agenda_notification', [ $this, 'send_agenda_notification' ] );
        // Hook para enviar relatório diário de atendimentos e financeiro às 19h
        add_action( 'dps_send_daily_report', [ $this, 'send_daily_report' ] );

        // Se o base plugin estiver ativo, adiciona uma aba de configurações
        add_action( 'dps_base_nav_tabs', [ $this, 'add_nav_tab' ] );
        add_action( 'dps_base_sections', [ $this, 'add_settings_section' ] );

        // Lida com salvamento do formulário de configurações
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Aplica filtros para usar emails salvos nas notificações
        add_filter( 'dps_push_notification_recipients', [ $this, 'filter_agenda_recipients' ] );
        add_filter( 'dps_daily_report_recipients', [ $this, 'filter_report_recipients' ] );

        // Hook para relatório semanal de pets inativos
        add_action( 'dps_send_weekly_inactive_report', [ $this, 'send_weekly_inactive_report' ] );
        // Hook para enviar mensagem via Telegram, se configurado
        add_action( 'dps_send_push_notification', [ $this, 'send_to_telegram' ], 10, 2 );
    }

    /**
     * Agenda o evento diário às 08:00 na ativação
     */
    public function activate() {
        // Agenda a notificação diária de agenda no horário configurado
        $agenda_hour = intval( get_option( 'dps_push_agenda_hour', 8 ) );
        if ( ! wp_next_scheduled( 'dps_send_agenda_notification' ) ) {
            $timestamp = $this->get_next_run_timestamp( $agenda_hour );
            wp_schedule_event( $timestamp, 'daily', 'dps_send_agenda_notification' );
        }
        // Agenda o envio do relatório diário no horário configurado
        $report_hour = intval( get_option( 'dps_push_report_hour', 19 ) );
        if ( ! wp_next_scheduled( 'dps_send_daily_report' ) ) {
            $report_time = $this->get_next_run_timestamp( $report_hour );
            wp_schedule_event( $report_time, 'daily', 'dps_send_daily_report' );
        }
        // Agenda o relatório semanal de pets inativos para segunda-feira às 08h (ou horário da agenda)
        $week_hour = intval( get_option( 'dps_push_agenda_hour', 8 ) );
        if ( ! wp_next_scheduled( 'dps_send_weekly_inactive_report' ) ) {
            $weekly_time = $this->get_next_weekly_run_timestamp( 'Monday', $week_hour );
            wp_schedule_event( $weekly_time, 'weekly', 'dps_send_weekly_inactive_report' );
        }
    }

    /**
     * Cancela o evento agendado ao desativar
     */
    public function deactivate() {
        $timestamp = wp_next_scheduled( 'dps_send_agenda_notification' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'dps_send_agenda_notification' );
        }
        $report_timestamp = wp_next_scheduled( 'dps_send_daily_report' );
        if ( $report_timestamp ) {
            wp_unschedule_event( $report_timestamp, 'dps_send_daily_report' );
        }
        $inactive_timestamp = wp_next_scheduled( 'dps_send_weekly_inactive_report' );
        if ( $inactive_timestamp ) {
            wp_unschedule_event( $inactive_timestamp, 'dps_send_weekly_inactive_report' );
        }
    }

    /**
     * Calcula o próximo horário às 08:00 baseado no fuso horário do site.
     *
     * @return int Timestamp
     */
    private function get_next_run_timestamp( $hour = 8 ) {
        // Calcula o próximo horário desejado (default 8h) no timezone do WordPress
        $now = current_time( 'timestamp' );
        $next_run = mktime( $hour, 0, 0, date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) );
        if ( $next_run <= $now ) {
            $next_run = strtotime( '+1 day', $next_run );
        }
        return $next_run;
    }

    /**
     * Calcula o próximo horário para um dia específico da semana.
     *
     * @param string $day Dia da semana em inglês (Monday, Tuesday, etc.)
     * @param int    $hour Horário (0-23)
     * @return int Timestamp da próxima ocorrência
     */
    private function get_next_weekly_run_timestamp( $day = 'Monday', $hour = 8 ) {
        $now = current_time( 'timestamp' );
        // Encontra a próxima ocorrência do dia específico
        // Usa strtotime com 'next Monday' etc.
        $next = strtotime( 'next ' . $day, $now );
        // Define hora
        $next_run = mktime( $hour, 0, 0, date( 'n', $next ), date( 'j', $next ), date( 'Y', $next ) );
        // Se ainda estiver no passado (mesmo dia e hora), acrescenta uma semana
        if ( $next_run <= $now ) {
            $next_run = strtotime( '+1 week', $next_run );
        }
        return $next_run;
    }

    /**
     * Envia a notificação diária com o resumo da agenda
     */
    public function send_agenda_notification() {
        // Obtém a data atual no formato YYYY-mm-dd
        $today = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
        // Busca agendamentos do dia
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
        ] );
        // Constrói o resumo
        $lines = [];
        foreach ( $appointments as $appt ) {
            $time   = get_post_meta( $appt->ID, 'appointment_time', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet   = $pet_id ? get_post( $pet_id ) : null;
            $client= $client_id ? get_post( $client_id ) : null;
            $pet_name    = $pet ? $pet->post_title : '-';
            $client_name = $client ? $client->post_title : '-';
            $lines[] = $time . ' – ' . $pet_name . ' (' . $client_name . ')';
        }
        // Mensagem vazia se não houver atendimentos
        $content = '';
        if ( $lines ) {
            $content .= "Agendamentos para hoje (" . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . "):\n";
            foreach ( $lines as $line ) {
                $content .= '- ' . $line . "\n";
            }
        } else {
            $content = 'Não há agendamentos para hoje.';
        }
        // Permite modificar o conteúdo via filtro
        $content = apply_filters( 'dps_push_notification_content', $content, $appointments );
        // Determina destinatários
        $to      = apply_filters( 'dps_push_notification_recipients', [ get_option( 'admin_email' ) ] );
        $subject = 'Resumo de agendamentos do dia';
        // Constrói versão HTML
        $html    = '<html><body>';
        $html   .= '<p>Agendamentos para hoje (' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . '):</p>';
        if ( $lines ) {
            $html .= '<ul>';
            foreach ( $lines as $line ) {
                $html .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Não há agendamentos para hoje.</p>';
        }
        $html .= '</body></html>';
        // Define headers para HTML
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        foreach ( $to as $recipient ) {
            if ( is_email( $recipient ) ) {
                wp_mail( $recipient, $subject, $html, $headers );
            }
        }
        // Integra com serviços de push externos, como Telegram
        do_action( 'dps_send_push_notification', $content, $appointments );
    }

    /**
     * Envia um relatório diário às 19:00 contendo resumo de atendimentos e dados financeiros.
     */
    public function send_daily_report() {
        // Data atual
        $today = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
        // ----- Resumo de atendimentos -----
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
        ] );
        $ap_lines = [];
        foreach ( $appointments as $appt ) {
            $time   = get_post_meta( $appt->ID, 'appointment_time', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet   = $pet_id ? get_post( $pet_id ) : null;
            $client= $client_id ? get_post( $client_id ) : null;
            $pet_name    = $pet ? $pet->post_title : '-';
            $client_name = $client ? $client->post_title : '-';
            $ap_lines[]  = $time . ' – ' . $pet_name . ' (' . $client_name . ')';
        }
        // ----- Resumo financeiro -----
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Seleciona transações cuja data seja hoje (ignorando hora)
        $trans = [];
        $total_pago = 0.0;
        $total_aberto = 0.0;
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
            $trans = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE DATE(data) = %s", $today ) );
            foreach ( $trans as $t ) {
                $valor = (float) $t->valor;
                if ( $t->status === 'pago' ) {
                    $total_pago   += $valor;
                } else {
                    $total_aberto += $valor;
                }
            }
        }
        // Monta conteúdo de email em HTML e texto
        $content = 'Relatório diário de ' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . "\n\n";
        $content .= "Resumo de atendimentos:\n";
        if ( $ap_lines ) {
            foreach ( $ap_lines as $line ) {
                $content .= '- ' . $line . "\n";
            }
        } else {
            $content .= "Nenhum atendimento registrado hoje.\n";
        }
        $content .= "\nResumo financeiro:\n";
        if ( $trans ) {
            $content .= sprintf( "Total recebido (pago): R$ %s\n", number_format( $total_pago, 2, ',', '.' ) );
            $content .= sprintf( "Total em aberto: R$ %s\n", number_format( $total_aberto, 2, ',', '.' ) );
            $content .= "Transações:\n";
            foreach ( $trans as $t ) {
                $date_fmt = $t->data ? date_i18n( 'H:i', strtotime( $t->data ) ) : '';
                $valor_fmt = number_format( (float) $t->valor, 2, ',', '.' );
                $status_label = ( $t->status === 'pago' ) ? 'Pago' : 'Em aberto';
                $desc = $t->descricao ?: '';
                $content .= '- ' . $date_fmt . ': R$ ' . $valor_fmt . ' (' . $status_label . ') ' . $desc . "\n";
            }
        } else {
            $content .= "Nenhuma transação financeira registrada hoje.\n";
        }
        // Constrói HTML
        $html = '<html><body>';
        $html .= '<h3>Relatório diário de ' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . '</h3>';
        $html .= '<h4>Resumo de atendimentos:</h4>';
        if ( $ap_lines ) {
            $html .= '<ul>';
            foreach ( $ap_lines as $line ) {
                $html .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Nenhum atendimento registrado hoje.</p>';
        }
        $html .= '<h4>Resumo financeiro:</h4>';
        if ( $trans ) {
            $html .= '<p>Total recebido (pago): <strong>R$ ' . esc_html( number_format( $total_pago, 2, ',', '.' ) ) . '</strong><br>';
            $html .= 'Total em aberto: <strong>R$ ' . esc_html( number_format( $total_aberto, 2, ',', '.' ) ) . '</strong></p>';
            $html .= '<ul>';
            foreach ( $trans as $t ) {
                $date_fmt = $t->data ? date_i18n( 'H:i', strtotime( $t->data ) ) : '';
                $valor_fmt = number_format( (float) $t->valor, 2, ',', '.' );
                $status_label = ( $t->status === 'pago' ) ? 'Pago' : 'Em aberto';
                $desc = $t->descricao ?: '';
                $html .= '<li>' . esc_html( $date_fmt ) . ': R$ ' . esc_html( $valor_fmt ) . ' (' . esc_html( $status_label ) . ') ' . esc_html( $desc ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Nenhuma transação financeira registrada hoje.</p>';
        }
        $html .= '</body></html>';
        // Permite filtros no conteúdo e destinatários
        $content = apply_filters( 'dps_daily_report_content', $content, $appointments, $trans );
        $html    = apply_filters( 'dps_daily_report_html', $html, $appointments, $trans );
        $recipients = apply_filters( 'dps_daily_report_recipients', [ get_option( 'admin_email' ) ] );
        $subject = 'Relatório diário de atendimentos e financeiro';
        // HTML header
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        foreach ( $recipients as $recipient ) {
            if ( is_email( $recipient ) ) {
                wp_mail( $recipient, $subject, $html, $headers );
            }
        }
    }

    /**
     * Adiciona uma nova aba de navegação para Notificações no painel do plugin base.
     *
     * @param bool $agenda_view Parâmetro herdado do hook (não utilizado aqui)
     */
    public function add_nav_tab( $agenda_view ) {
        // Apenas usuários administradores devem ver a aba
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<li><a href="#" class="dps-tab-link" data-tab="notificacoes">' . esc_html__( 'Notificações', 'dps-push-addon' ) . '</a></li>';
        }
    }

    /**
     * Renderiza a seção de configurações de notificações dentro do painel do plugin base.
     *
     * @param bool $agenda_view Parâmetro herdado do hook (não utilizado)
     */
    public function add_settings_section( $agenda_view ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Obtém emails salvos
        $agenda_emails = get_option( 'dps_push_emails_agenda', [] );
        $report_emails = get_option( 'dps_push_emails_report', [] );
        $agenda_str = is_array( $agenda_emails ) ? implode( ', ', $agenda_emails ) : '';
        $report_str = is_array( $report_emails ) ? implode( ', ', $report_emails ) : '';
        $agenda_hour = intval( get_option( 'dps_push_agenda_hour', 8 ) );
        $report_hour = intval( get_option( 'dps_push_report_hour', 19 ) );
        $telegram_token = get_option( 'dps_push_telegram_token', '' );
        $telegram_chat  = get_option( 'dps_push_telegram_chat', '' );
        echo '<div class="dps-section" id="dps-section-notificacoes">';
        echo '<h3>' . esc_html__( 'Configurações de Notificações', 'dps-push-addon' ) . '</h3>';
        // Mensagem de sucesso
        if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
            echo '<p style="color:green;font-weight:bold;">' . esc_html__( 'Configurações salvas com sucesso.', 'dps-push-addon' ) . '</p>';
        }
        echo '<form method="post">';
        echo '<input type="hidden" name="dps_push_action" value="save_notifications">';
        wp_nonce_field( 'dps_push_save', 'dps_push_nonce' );
        echo '<p><label>' . esc_html__( 'Emails para resumo de agendamentos', 'dps-push-addon' ) . '<br>'; 
        echo '<input type="text" name="agenda_emails" value="' . esc_attr( $agenda_str ) . '" style="width:100%;"></label></p>';
        echo '<p><label>' . esc_html__( 'Horário do resumo de agendamentos (0-23)', 'dps-push-addon' ) . '<br>';
        echo '<input type="number" name="agenda_hour" min="0" max="23" value="' . esc_attr( $agenda_hour ) . '" style="width:60px;"></label></p>';
        echo '<p><label>' . esc_html__( 'Emails para relatório financeiro e atendimentos', 'dps-push-addon' ) . '<br>'; 
        echo '<input type="text" name="report_emails" value="' . esc_attr( $report_str ) . '" style="width:100%;"></label></p>';
        echo '<p><label>' . esc_html__( 'Horário do relatório diário (0-23)', 'dps-push-addon' ) . '<br>';
        echo '<input type="number" name="report_hour" min="0" max="23" value="' . esc_attr( $report_hour ) . '" style="width:60px;"></label></p>';
        // Campos para integração com Telegram
        echo '<h4>' . esc_html__( 'Integração com Telegram (opcional)', 'dps-push-addon' ) . '</h4>';
        echo '<p><label>' . esc_html__( 'Token do bot', 'dps-push-addon' ) . '<br>';
        echo '<input type="text" name="telegram_token" value="' . esc_attr( $telegram_token ) . '" style="width:100%;"></label></p>';
        echo '<p><label>' . esc_html__( 'ID do chat (chat_id)', 'dps-push-addon' ) . '<br>';
        echo '<input type="text" name="telegram_chat" value="' . esc_attr( $telegram_chat ) . '" style="width:100%;"></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar', 'dps-push-addon' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Processa o envio do formulário de configurações das notificações.
     */
    public function maybe_handle_save() {
        if ( isset( $_POST['dps_push_action'] ) && 'save_notifications' === $_POST['dps_push_action'] ) {
            // Verifica o nonce
            if ( ! isset( $_POST['dps_push_nonce'] ) || ! wp_verify_nonce( $_POST['dps_push_nonce'], 'dps_push_save' ) ) {
                return;
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            // Sanitiza e salva emails
            $agenda_raw = isset( $_POST['agenda_emails'] ) ? sanitize_text_field( $_POST['agenda_emails'] ) : '';
            $report_raw = isset( $_POST['report_emails'] ) ? sanitize_text_field( $_POST['report_emails'] ) : '';
            $agenda_list = array_filter( array_map( 'trim', explode( ',', $agenda_raw ) ) );
            $report_list = array_filter( array_map( 'trim', explode( ',', $report_raw ) ) );
            update_option( 'dps_push_emails_agenda', $agenda_list );
            update_option( 'dps_push_emails_report', $report_list );
            // Salva horários
            $agenda_hour = isset( $_POST['agenda_hour'] ) ? intval( $_POST['agenda_hour'] ) : 8;
            $report_hour = isset( $_POST['report_hour'] ) ? intval( $_POST['report_hour'] ) : 19;
            update_option( 'dps_push_agenda_hour', $agenda_hour );
            update_option( 'dps_push_report_hour', $report_hour );
            // Salva integração Telegram
            $telegram_token = isset( $_POST['telegram_token'] ) ? sanitize_text_field( $_POST['telegram_token'] ) : '';
            $telegram_chat  = isset( $_POST['telegram_chat'] ) ? sanitize_text_field( $_POST['telegram_chat'] ) : '';
            update_option( 'dps_push_telegram_token', $telegram_token );
            update_option( 'dps_push_telegram_chat', $telegram_chat );
            // Reagendar eventos com novos horários
            $timestamp = wp_next_scheduled( 'dps_send_agenda_notification' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'dps_send_agenda_notification' );
            }
            $report_timestamp = wp_next_scheduled( 'dps_send_daily_report' );
            if ( $report_timestamp ) {
                wp_unschedule_event( $report_timestamp, 'dps_send_daily_report' );
            }
            $inactive_timestamp = wp_next_scheduled( 'dps_send_weekly_inactive_report' );
            if ( $inactive_timestamp ) {
                wp_unschedule_event( $inactive_timestamp, 'dps_send_weekly_inactive_report' );
            }
            // Agenda novamente com novos horários
            $timestamp = $this->get_next_run_timestamp( $agenda_hour );
            wp_schedule_event( $timestamp, 'daily', 'dps_send_agenda_notification' );
            $report_time = $this->get_next_run_timestamp( $report_hour );
            wp_schedule_event( $report_time, 'daily', 'dps_send_daily_report' );
            // agenda semanal (segunda)
            $weekly_time = $this->get_next_weekly_run_timestamp( 'Monday', $agenda_hour );
            wp_schedule_event( $weekly_time, 'weekly', 'dps_send_weekly_inactive_report' );
            // Redireciona com flag de sucesso
            wp_redirect( add_query_arg( [ 'tab' => 'notificacoes', 'updated' => '1' ], get_permalink() ) );
            exit;
        }
    }

    /**
     * Substitui os destinatários padrão do resumo de agendamentos pelos emails configurados.
     *
     * @param array $recipients Lista original de emails
     * @return array Nova lista de emails
     */
    public function filter_agenda_recipients( $recipients ) {
        $saved = get_option( 'dps_push_emails_agenda', [] );
        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return $saved;
        }
        return $recipients;
    }

    /**
     * Substitui os destinatários padrão do relatório das 19h pelos emails configurados.
     *
     * @param array $recipients Lista original
     * @return array Nova lista
     */
    public function filter_report_recipients( $recipients ) {
        $saved = get_option( 'dps_push_emails_report', [] );
        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return $saved;
        }
        return $recipients;
    }

    /**
     * Envia relatório semanal de pets inativos (sem agendamentos nos últimos 30 dias)
     */
    public function send_weekly_inactive_report() {
        // Data limite: 30 dias atrás
        $cutoff_date = date_i18n( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );
        // Busca todos os pets
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );
        $inactive = [];
        foreach ( $pets as $pet ) {
            $pet_id = $pet->ID;
            // Busca últimos agendamentos deste pet com status publish
            $appointments = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_pet_id',
                        'value'   => $pet_id,
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'meta_value_num',
                'meta_key'       => 'appointment_date',
                'order'          => 'DESC',
            ] );
            $last_date = null;
            if ( $appointments ) {
                $last_date = get_post_meta( $appointments[0]->ID, 'appointment_date', true );
            }
            // Se não há data ou é anterior ao cutoff, adiciona à lista
            if ( ! $last_date || $last_date < $cutoff_date ) {
                // Obter nome do dono e data formatada
                $owner_id = get_post_meta( $pet_id, 'owner_id', true );
                $owner    = $owner_id ? get_post( $owner_id ) : null;
                $last_fmt = $last_date ? date_i18n( 'd/m/Y', strtotime( $last_date ) ) : 'Nunca';
                $inactive[] = [
                    'pet_name'   => $pet->post_title,
                    'owner_name' => $owner ? $owner->post_title : '-',
                    'last_date'  => $last_fmt,
                ];
            }
        }
        // Monta mensagem de relatório
        $today_label = date_i18n( 'd/m/Y', current_time( 'timestamp' ) );
        $content = "Relatório semanal de pets inativos ({$today_label})\n\n";
        $html    = '<html><body>';
        $html   .= '<h3>Relatório semanal de pets inativos (' . esc_html( $today_label ) . ')</h3>';
        if ( $inactive ) {
            $content .= "Pets sem atendimento nos últimos 30 dias:\n";
            $html    .= '<p>Pets sem atendimento nos últimos 30 dias:</p><ul>';
            foreach ( $inactive as $item ) {
                $line = $item['pet_name'] . ' – ' . $item['owner_name'] . ' (último: ' . $item['last_date'] . ')';
                $content .= '- ' . $line . "\n";
                $html    .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $content .= "Todos os pets tiveram atendimento recente.\n";
            $html    .= '<p>Todos os pets tiveram atendimento recente.</p>';
        }
        $html .= '</body></html>';
        // Determina destinatários (usar emails de relatório por padrão)
        $recipients = apply_filters( 'dps_weekly_inactive_report_recipients', get_option( 'dps_push_emails_report', [ get_option( 'admin_email' ) ] ) );
        $subject = 'Relatório semanal de pets inativos';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        foreach ( $recipients as $recipient ) {
            if ( is_email( $recipient ) ) {
                wp_mail( $recipient, $subject, $html, $headers );
            }
        }
        // Aciona serviço de push se configurado
        do_action( 'dps_send_push_notification', $content, $inactive );
    }

    /**
     * Envia notificação via Telegram se as credenciais estiverem configuradas.
     *
     * @param string $message Mensagem a ser enviada (texto)
     * @param mixed  $context Contexto adicional (não utilizado)
     */
    public function send_to_telegram( $message, $context ) {
        $token = get_option( 'dps_push_telegram_token', '' );
        $chat_id = get_option( 'dps_push_telegram_chat', '' );
        if ( empty( $token ) || empty( $chat_id ) ) {
            return;
        }
        // Monta endpoint
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $args = [
            'body' => [
                'chat_id' => $chat_id,
                'text'    => $message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 15,
        ];
        // Envia requisição; ignora resposta
        wp_remote_post( $url, $args );
    }
}

new DPS_Push_Notifications_Addon();