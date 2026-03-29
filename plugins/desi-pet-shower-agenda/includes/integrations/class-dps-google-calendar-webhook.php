<?php
/**
 * Google Calendar Webhook Handler
 *
 * Processa notificações push do Google Calendar para sincronização bidirecional.
 * 
 * @package    DPS_Agenda_Addon
 * @subpackage Integrations
 * @since      2.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe webhook para Google Calendar.
 *
 * Sincronização bidirecional: Calendar → DPS
 * - Registra webhook no Google (watch channel)
 * - Processa notificações push quando eventos mudam
 * - Atualiza agendamentos no DPS
 * - Renova webhook automaticamente
 *
 * @since 2.0.0
 */
class DPS_Google_Calendar_Webhook {
    
    /**
     * Nome da option para armazenar dados do webhook.
     *
     * @since 2.0.0
     * @var string
     */
    const WEBHOOK_OPTION = 'dps_google_calendar_webhook';
    
    /**
     * TTL do webhook (em segundos) - Google permite até 30 dias.
     * Usando 7 dias (604800s) para renovação semanal.
     *
     * @since 2.0.0
     * @var int
     */
    const WEBHOOK_TTL = 604800; // 7 dias
    
    /**
     * Inicializa a classe.
     *
     * @since 2.0.0
     */
    public function __construct() {
        // Registra endpoint REST API
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoint' ] );
        
        // Agenda renovação automática do webhook
        add_action( 'dps_google_webhook_renew', [ $this, 'renew_webhook' ] );
        
        // Registra webhook quando conectar
        add_action( 'dps_google_auth_connected', [ $this, 'register_webhook' ] );
        
        // Remove webhook quando desconectar
        add_action( 'dps_google_auth_disconnected', [ $this, 'stop_webhook' ] );
    }
    
    /**
     * Registra endpoint REST API para receber notificações.
     *
     * @since 2.0.0
     */
    public function register_rest_endpoint() {
        register_rest_route( 'dps/v1', '/google-calendar-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook_notification' ],
            'permission_callback' => '__return_true', // Webhook público, validação via header
        ] );
    }
    
    /**
     * Registra webhook no Google Calendar.
     *
     * @since 2.0.0
     *
     * @return array|WP_Error Dados do webhook ou WP_Error.
     */
    public function register_webhook() {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        // Gera ID único para este canal
        $channel_id = 'dps-calendar-' . wp_generate_uuid4();
        
        // URL do webhook
        $webhook_url = rest_url( 'dps/v1/google-calendar-webhook' );
        
        // Token secreto para validação
        $token = wp_generate_password( 32, false );
        
        // Calcula expiration (timestamp em milissegundos)
        $expiration = ( time() + self::WEBHOOK_TTL ) * 1000;
        
        // Dados da requisição
        $body = [
            'id'         => $channel_id,
            'type'       => 'web_hook',
            'address'    => $webhook_url,
            'token'      => $token,
            'expiration' => $expiration,
        ];
        
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/watch';
        
        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code !== 200 ) {
            return new WP_Error(
                'webhook_registration_failed',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Falha ao registrar webhook. HTTP Code: %d', 'dps-agenda-addon' ),
                    $code
                ),
                [ 'response' => $data ]
            );
        }
        
        // Armazena dados do webhook
        $webhook_data = [
            'id'              => $channel_id,
            'resource_id'     => $data['resourceId'],
            'token'           => $token,
            'expiration'      => $data['expiration'],
            'registered_at'   => time(),
        ];
        
        update_option( self::WEBHOOK_OPTION, $webhook_data );
        
        // Agenda renovação (5 dias antes de expirar)
        $renew_time = time() + self::WEBHOOK_TTL - ( 5 * DAY_IN_SECONDS );
        wp_schedule_single_event( $renew_time, 'dps_google_webhook_renew' );
        
        return $webhook_data;
    }
    
    /**
     * Para o webhook no Google Calendar.
     *
     * @since 2.0.0
     *
     * @return bool|WP_Error True em sucesso ou WP_Error.
     */
    public function stop_webhook() {
        $webhook_data = get_option( self::WEBHOOK_OPTION );
        
        if ( empty( $webhook_data['id'] ) || empty( $webhook_data['resource_id'] ) ) {
            return true; // Nada para parar
        }
        
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = 'https://www.googleapis.com/calendar/v3/channels/stop';
        
        $body = [
            'id'         => $webhook_data['id'],
            'resourceId' => $webhook_data['resource_id'],
        ];
        
        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );
        
        // Remove dados locais
        delete_option( self::WEBHOOK_OPTION );
        wp_clear_scheduled_hook( 'dps_google_webhook_renew' );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return true;
    }
    
    /**
     * Renova webhook antes de expirar.
     *
     * @since 2.0.0
     */
    public function renew_webhook() {
        // Para webhook antigo e registra novo
        $this->stop_webhook();
        $this->register_webhook();
    }
    
    /**
     * Processa notificação do webhook.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function handle_webhook_notification( $request ) {
        // Valida token
        $webhook_data = get_option( self::WEBHOOK_OPTION );
        
        if ( empty( $webhook_data['token'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Webhook not configured' ], 401 );
        }
        
        $received_token = $request->get_header( 'x-goog-channel-token' );
        
        if ( $received_token !== $webhook_data['token'] ) {
            return new WP_REST_Response( [ 'error' => 'Invalid token' ], 401 );
        }
        
        // Obtém state do header
        $state = $request->get_header( 'x-goog-resource-state' );
        
        // Ignora notificações de sincronização (apenas mudanças reais)
        if ( 'sync' === $state ) {
            return new WP_REST_Response( [ 'status' => 'ok', 'message' => 'Sync ignored' ], 200 );
        }
        
        // Processa mudanças em background (para não bloquear webhook)
        wp_schedule_single_event( time(), 'dps_google_calendar_process_changes' );
        
        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }
    
    /**
     * Processa mudanças do Google Calendar.
     *
     * @since 2.0.0
     */
    public function process_calendar_changes() {
        // Obtém eventos atualizados desde última sincronização
        $last_sync = get_option( 'dps_google_calendar_last_sync', time() - HOUR_IN_SECONDS );
        
        $events = $this->fetch_updated_events( $last_sync );
        
        if ( is_wp_error( $events ) ) {
            $this->log_error( 'fetch_events', $events->get_error_message() );
            return;
        }
        
        foreach ( $events as $event ) {
            $this->sync_event_to_dps( $event );
        }
        
        update_option( 'dps_google_calendar_last_sync', time() );
    }
    
    /**
     * Busca eventos atualizados desde timestamp.
     *
     * @since 2.0.0
     *
     * @param int $since Timestamp (Unix).
     * @return array|WP_Error Array de eventos ou WP_Error.
     */
    private function fetch_updated_events( $since ) {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        // Converte para RFC3339
        $updated_min = gmdate( 'c', $since );
        
        $url = add_query_arg( [
            'updatedMin'   => $updated_min,
            'singleEvents' => 'true',
            'orderBy'      => 'updated',
        ], 'https://www.googleapis.com/calendar/v3/calendars/primary/events' );
        
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code !== 200 ) {
            return new WP_Error( 'fetch_events_failed', 'Failed to fetch events', [ 'code' => $code ] );
        }
        
        return $data['items'] ?? [];
    }
    
    /**
     * Sincroniza evento do Calendar para agendamento no DPS.
     *
     * @since 2.0.0
     *
     * @param array $event Dados do evento.
     */
    private function sync_event_to_dps( $event ) {
        // Verifica se é evento criado pelo DPS
        $dps_appt_id = $event['extendedProperties']['private']['dps_appointment_id'] ?? null;
        
        if ( empty( $dps_appt_id ) ) {
            return; // Não é evento do DPS, ignora
        }
        
        // Verifica se agendamento existe
        $post = get_post( $dps_appt_id );
        
        if ( ! $post || 'dps_agendamento' !== $post->post_type ) {
            return;
        }
        
        // Verifica se evento foi deletado
        if ( ! empty( $event['status'] ) && 'cancelled' === $event['status'] ) {
            // Evento deletado no Calendar, mas NÃO deletar no DPS (apenas marcar)
            update_post_meta( $dps_appt_id, '_google_calendar_deleted', true );
            return;
        }
        
        // Extrai data/hora do evento
        $start = $event['start']['dateTime'] ?? null;
        
        if ( empty( $start ) ) {
            return; // Evento sem horário (all-day), não suportado
        }
        
        // Converte RFC3339 para formato DPS
        $datetime = new DateTime( $start, new DateTimeZone( 'UTC' ) );
        $datetime->setTimezone( new DateTimeZone( wp_timezone_string() ) );
        
        $new_date = $datetime->format( 'Y-m-d' );
        $new_time = $datetime->format( 'H:i' );
        
        // Obtém data/hora atuais do agendamento
        $current_date = get_post_meta( $dps_appt_id, 'appointment_date', true );
        $current_time = get_post_meta( $dps_appt_id, 'appointment_time', true );
        
        // Verifica se houve mudança
        if ( $new_date === $current_date && $new_time === $current_time ) {
            return; // Sem mudanças
        }
        
        // Marca como sincronizando (previne loop)
        update_post_meta( $dps_appt_id, '_dps_syncing_from_google', true );
        
        // Atualiza data/hora no DPS
        update_post_meta( $dps_appt_id, 'appointment_date', $new_date );
        update_post_meta( $dps_appt_id, 'appointment_time', $new_time );
        
        // Log de sincronização
        update_post_meta( $dps_appt_id, '_google_calendar_synced_from_calendar_at', time() );
        
        /**
         * Disparado após sincronizar mudança do Calendar para DPS.
         *
         * @since 2.0.0
         *
         * @param int   $dps_appt_id ID do agendamento.
         * @param array $event       Dados do evento do Calendar.
         * @param array $changes     Mudanças aplicadas.
         */
        do_action( 'dps_google_calendar_synced_from_calendar', $dps_appt_id, $event, [
            'old_date' => $current_date,
            'old_time' => $current_time,
            'new_date' => $new_date,
            'new_time' => $new_time,
        ] );
    }
    
    /**
     * Registra erro de webhook.
     *
     * @since 2.0.0
     *
     * @param string $context Contexto do erro.
     * @param string $message Mensagem de erro.
     */
    private function log_error( $context, $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[DPS Google Calendar Webhook] %s: %s',
                $context,
                $message
            ) );
        }
        
        /**
         * Disparado após erro no webhook.
         *
         * @since 2.0.0
         *
         * @param string $context Contexto do erro.
         * @param string $message Mensagem de erro.
         */
        do_action( 'dps_google_calendar_webhook_error', $context, $message );
    }
}
