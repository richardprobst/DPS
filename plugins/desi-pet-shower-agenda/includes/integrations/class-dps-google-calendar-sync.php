<?php
/**
 * Google Calendar Synchronization
 *
 * Sincroniza agendamentos do DPS com Google Calendar.
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
 * Classe de sincronização com Google Calendar.
 *
 * Sincronização unidirecional: DPS → Google Calendar
 * - Cria evento quando agendamento é salvo
 * - Atualiza evento quando agendamento é editado
 * - Deleta evento quando agendamento é deletado
 *
 * @since 2.0.0
 */
class DPS_Google_Calendar_Sync {
    
    /**
     * Inicializa a classe.
     *
     * @since 2.0.0
     */
    public function __construct() {
        // Sincroniza após salvar agendamento
        add_action( 'dps_base_after_save_appointment', [ $this, 'sync_appointment' ], 10, 2 );
        
        // Sincroniza ao deletar agendamento
        add_action( 'before_delete_post', [ $this, 'handle_delete_appointment' ], 10, 2 );
        
        // Sincroniza ao restaurar agendamento da lixeira
        add_action( 'untrashed_post', [ $this, 'handle_untrash_appointment' ], 10, 2 );
    }
    
    /**
     * Sincroniza agendamento com Google Calendar.
     *
     * @since 2.0.0
     *
     * @param int    $appt_id   ID do agendamento.
     * @param string $appt_type Tipo do agendamento (simple, subscription, past).
     */
    public function sync_appointment( $appt_id, $appt_type = 'simple' ) {
        // Verifica se está conectado
        if ( ! DPS_Google_Auth::is_connected() ) {
            return;
        }
        
        // Verifica se sincronização está habilitada
        $settings = get_option( DPS_Google_Auth::OPTION_NAME, [] );
        if ( empty( $settings['sync_calendar'] ) ) {
            return;
        }
        
        // Previne loop infinito (se evento foi criado via webhook)
        if ( get_post_meta( $appt_id, '_dps_syncing_from_google', true ) ) {
            delete_post_meta( $appt_id, '_dps_syncing_from_google' );
            return;
        }
        
        // Verifica se é post type correto
        if ( 'dps_agendamento' !== get_post_type( $appt_id ) ) {
            return;
        }
        
        // Verifica se agendamento está publicado
        if ( 'publish' !== get_post_status( $appt_id ) ) {
            return;
        }
        
        // Obtém event_id existente (se houver)
        $event_id = get_post_meta( $appt_id, '_google_calendar_event_id', true );
        
        // Formata agendamento como evento
        $event_data = $this->format_appointment_as_event( $appt_id );
        
        if ( is_wp_error( $event_data ) ) {
            $this->log_sync_error( $appt_id, 'format_error', $event_data->get_error_message() );
            return;
        }
        
        // Cria ou atualiza evento
        if ( empty( $event_id ) ) {
            $result = $this->create_calendar_event( $appt_id, $event_data );
        } else {
            $result = $this->update_calendar_event( $appt_id, $event_id, $event_data );
        }
        
        // Log de resultado
        if ( is_wp_error( $result ) ) {
            $this->log_sync_error( $appt_id, 'sync_error', $result->get_error_message() );
        } else {
            update_post_meta( $appt_id, '_google_calendar_synced_at', time() );
        }
    }
    
    /**
     * Cria evento no Google Calendar.
     *
     * @since 2.0.0
     *
     * @param int   $appt_id    ID do agendamento.
     * @param array $event_data Dados do evento.
     * @return array|WP_Error Resultado da criação.
     */
    private function create_calendar_event( $appt_id, $event_data ) {
        $result = DPS_Google_Calendar_Client::create_event( $event_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Armazena event_id
        if ( ! empty( $result['id'] ) ) {
            update_post_meta( $appt_id, '_google_calendar_event_id', $result['id'] );
        }
        
        /**
         * Disparado após criar evento no Calendar.
         *
         * @since 2.0.0
         *
         * @param int    $appt_id  ID do agendamento.
         * @param string $event_id ID do evento no Calendar.
         * @param array  $result   Resposta completa da API.
         */
        do_action( 'dps_google_calendar_event_created', $appt_id, $result['id'], $result );
        
        return $result;
    }
    
    /**
     * Atualiza evento no Google Calendar.
     *
     * @since 2.0.0
     *
     * @param int    $appt_id    ID do agendamento.
     * @param string $event_id   ID do evento no Calendar.
     * @param array  $event_data Dados atualizados do evento.
     * @return array|WP_Error Resultado da atualização.
     */
    private function update_calendar_event( $appt_id, $event_id, $event_data ) {
        $result = DPS_Google_Calendar_Client::update_event( $event_id, $event_data );
        
        if ( is_wp_error( $result ) ) {
            // Se evento não existe mais, cria um novo
            if ( $result->get_error_code() === 'calendar_api_error_update_event' ) {
                $error_data = $result->get_error_data();
                if ( isset( $error_data['status'] ) && 404 === $error_data['status'] ) {
                    delete_post_meta( $appt_id, '_google_calendar_event_id' );
                    return $this->create_calendar_event( $appt_id, $event_data );
                }
            }
            return $result;
        }
        
        /**
         * Disparado após atualizar evento no Calendar.
         *
         * @since 2.0.0
         *
         * @param int    $appt_id  ID do agendamento.
         * @param string $event_id ID do evento no Calendar.
         * @param array  $result   Resposta completa da API.
         */
        do_action( 'dps_google_calendar_event_updated', $appt_id, $event_id, $result );
        
        return $result;
    }
    
    /**
     * Formata agendamento do DPS como evento do Google Calendar.
     *
     * @since 2.0.0
     *
     * @param int $appt_id ID do agendamento.
     * @return array|WP_Error Dados do evento ou WP_Error.
     */
    private function format_appointment_as_event( $appt_id ) {
        // Obtém dados do agendamento
        $date         = get_post_meta( $appt_id, 'appointment_date', true );
        $time         = get_post_meta( $appt_id, 'appointment_time', true );
        $client_id    = get_post_meta( $appt_id, 'appointment_client_id', true );
        $pet_id       = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $service_ids  = get_post_meta( $appt_id, 'appointment_service_ids', true );
        $groomer_id   = get_post_meta( $appt_id, 'appointment_groomer_id', true );
        $status       = get_post_meta( $appt_id, 'appointment_status', true );
        
        // Valida dados obrigatórios
        if ( empty( $date ) || empty( $time ) ) {
            return new WP_Error(
                'missing_datetime',
                __( 'Agendamento sem data ou hora válidas.', 'dps-agenda-addon' )
            );
        }
        
        // Obtém nomes
        $client_name = get_the_title( $client_id );
        $pet_name    = get_the_title( $pet_id );
        
        // Obtém serviços
        $services = [];
        if ( is_array( $service_ids ) ) {
            foreach ( $service_ids as $service_id ) {
                $services[] = get_the_title( $service_id );
            }
        }
        $services_text = ! empty( $services ) ? implode( ', ', $services ) : __( 'Serviço não especificado', 'dps-agenda-addon' );
        
        // Calcula duração estimada (padrão: 1 hora, ajustar conforme serviços)
        $duration_minutes = 60;
        $duration_minutes = apply_filters( 'dps_google_calendar_event_duration', $duration_minutes, $appt_id, $service_ids );
        
        // Formata data/hora início e fim
        $start = DPS_Google_Calendar_Client::format_datetime( $date, $time );
        
        $datetime = new DateTime( $date . ' ' . $time, new DateTimeZone( wp_timezone_string() ) );
        $datetime->modify( "+{$duration_minutes} minutes" );
        $end = $datetime->format( 'c' );
        
        // Monta título do evento
        $title = sprintf(
            '🐾 %s - %s (%s)',
            $services_text,
            $pet_name,
            $client_name
        );
        
        // Monta descrição
        $description_parts = [];
        $description_parts[] = sprintf( __( 'Cliente: %s', 'dps-agenda-addon' ), $client_name );
        $description_parts[] = sprintf( __( 'Pet: %s', 'dps-agenda-addon' ), $pet_name );
        $description_parts[] = sprintf( __( 'Serviços: %s', 'dps-agenda-addon' ), $services_text );
        
        if ( ! empty( $groomer_id ) ) {
            $groomer_name = get_the_title( $groomer_id );
            $description_parts[] = sprintf( __( 'Profissional: %s', 'dps-agenda-addon' ), $groomer_name );
        }
        
        $description_parts[] = '';
        $description_parts[] = sprintf(
            __( '🔗 Ver no DPS: %s', 'dps-agenda-addon' ),
            admin_url( 'admin.php?page=dps-agenda-hub&appointment_id=' . $appt_id )
        );
        
        $description = implode( "\n", $description_parts );
        
        // Determina cor baseada no status
        $color_id = $this->get_color_by_status( $status );
        
        // Monta dados do evento
        $event = [
            'summary'     => $title,
            'description' => $description,
            'start'       => [
                'dateTime' => $start,
                'timeZone' => wp_timezone_string(),
            ],
            'end'         => [
                'dateTime' => $end,
                'timeZone' => wp_timezone_string(),
            ],
            'colorId'     => $color_id,
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    [ 'method' => 'popup', 'minutes' => 60 ],  // 1 hora antes
                    [ 'method' => 'popup', 'minutes' => 15 ],  // 15 min antes
                ],
            ],
        ];
        
        // Adiciona Extended Properties para rastreamento
        $event['extendedProperties'] = [
            'private' => [
                'dps_appointment_id' => (string) $appt_id,
                'dps_source'         => 'dps_agenda_addon',
            ],
        ];
        
        /**
         * Filtra dados do evento antes de enviar para Calendar API.
         *
         * @since 2.0.0
         *
         * @param array $event   Dados do evento.
         * @param int   $appt_id ID do agendamento.
         */
        return apply_filters( 'dps_google_calendar_event_data', $event, $appt_id );
    }
    
    /**
     * Determina cor do evento baseada no status do agendamento.
     *
     * @since 2.0.0
     *
     * @param string $status Status do agendamento.
     * @return string Color ID do Google Calendar.
     */
    private function get_color_by_status( $status ) {
        $color_map = [
            'pendente'         => DPS_Google_Calendar_Client::COLORS['blue'],
            'finalizado'       => DPS_Google_Calendar_Client::COLORS['green'],
            'finalizado_pago'  => DPS_Google_Calendar_Client::COLORS['green'],
            'cancelado'        => DPS_Google_Calendar_Client::COLORS['red'],
        ];
        
        /**
         * Filtra mapeamento de status para cores.
         *
         * @since 2.0.0
         *
         * @param array $color_map Mapeamento status => color_id.
         */
        $color_map = apply_filters( 'dps_google_calendar_status_colors', $color_map );
        
        return $color_map[ $status ] ?? DPS_Google_Calendar_Client::COLORS['blue'];
    }
    
    /**
     * Lida com deleção de agendamento.
     *
     * @since 2.0.0
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post.
     */
    public function handle_delete_appointment( $post_id, $post ) {
        // Verificar se é um objeto WP_Post válido
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        
        if ( 'dps_agendamento' !== $post->post_type ) {
            return;
        }
        
        // Verifica se está conectado
        if ( ! DPS_Google_Auth::is_connected() ) {
            return;
        }
        
        $event_id = get_post_meta( $post_id, '_google_calendar_event_id', true );
        
        if ( empty( $event_id ) ) {
            return;
        }
        
        // Deleta evento do Calendar
        $result = DPS_Google_Calendar_Client::delete_event( $event_id );
        
        if ( ! is_wp_error( $result ) ) {
            delete_post_meta( $post_id, '_google_calendar_event_id' );
            delete_post_meta( $post_id, '_google_calendar_synced_at' );
        }
    }
    
    /**
     * Lida com restauração de agendamento da lixeira.
     *
     * @since 2.0.0
     *
     * @param int    $post_id ID do post.
     * @param string $previous_status Status anterior.
     */
    public function handle_untrash_appointment( $post_id, $previous_status ) {
        if ( 'dps_agendamento' !== get_post_type( $post_id ) ) {
            return;
        }
        
        // Remove event_id antigo e força recriação
        delete_post_meta( $post_id, '_google_calendar_event_id' );
        
        // Sincroniza novamente
        $this->sync_appointment( $post_id, 'simple' );
    }
    
    /**
     * Registra erro de sincronização.
     *
     * @since 2.0.0
     *
     * @param int    $appt_id ID do agendamento.
     * @param string $type    Tipo do erro.
     * @param string $message Mensagem de erro.
     */
    private function log_sync_error( $appt_id, $type, $message ) {
        // Armazena último erro
        update_post_meta( $appt_id, '_google_calendar_last_error', [
            'type'    => $type,
            'message' => $message,
            'time'    => time(),
        ] );
        
        // Log no WordPress (apenas se WP_DEBUG ativo)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[DPS Google Calendar Sync] Appointment #%d - %s: %s',
                $appt_id,
                $type,
                $message
            ) );
        }
        
        /**
         * Disparado após erro de sincronização.
         *
         * @since 2.0.0
         *
         * @param int    $appt_id ID do agendamento.
         * @param string $type    Tipo do erro.
         * @param string $message Mensagem de erro.
         */
        do_action( 'dps_google_calendar_sync_error', $appt_id, $type, $message );
    }
}
