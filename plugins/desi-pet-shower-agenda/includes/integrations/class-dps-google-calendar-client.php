<?php
/**
 * Google Calendar API Client
 *
 * Cliente HTTP para Google Calendar API v3.
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
 * Classe cliente para Google Calendar API.
 *
 * Gerencia comunicação HTTP com Google Calendar API v3:
 * - Criar eventos
 * - Atualizar eventos
 * - Deletar eventos
 * - Listar eventos
 *
 * @since 2.0.0
 */
class DPS_Google_Calendar_Client {
    
    /**
     * URL base da API do Google Calendar v3.
     *
     * @since 2.0.0
     * @var string
     */
    const API_BASE_URL = 'https://www.googleapis.com/calendar/v3';
    
    /**
     * ID do calendário principal (primary).
     *
     * @since 2.0.0
     * @var string
     */
    const CALENDAR_ID = 'primary';
    
    /**
     * Cores disponíveis para eventos no Google Calendar.
     *
     * @since 2.0.0
     * @var array
     */
    const COLORS = [
        'blue'       => '9',  // Azul
        'lightblue'  => '1',  // Azul claro
        'purple'     => '3',  // Roxo
        'green'      => '10', // Verde
        'yellow'     => '5',  // Amarelo
        'red'        => '11', // Vermelho
        'gray'       => '8',  // Cinza
    ];
    
    /**
     * Cria um evento no Google Calendar.
     *
     * @since 2.0.0
     *
     * @param array $event_data Dados do evento.
     * @return array|WP_Error Array com dados do evento criado ou WP_Error.
     */
    public static function create_event( $event_data ) {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = self::API_BASE_URL . '/calendars/' . self::CALENDAR_ID . '/events';
        
        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $event_data ),
            'timeout' => 15,
        ] );
        
        return self::process_response( $response, 'create_event' );
    }
    
    /**
     * Atualiza um evento no Google Calendar.
     *
     * @since 2.0.0
     *
     * @param string $event_id   ID do evento no Google Calendar.
     * @param array  $event_data Dados atualizados do evento.
     * @return array|WP_Error Array com dados do evento atualizado ou WP_Error.
     */
    public static function update_event( $event_id, $event_data ) {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = self::API_BASE_URL . '/calendars/' . self::CALENDAR_ID . '/events/' . $event_id;
        
        $response = wp_remote_request( $url, [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $event_data ),
            'timeout' => 15,
        ] );
        
        return self::process_response( $response, 'update_event' );
    }
    
    /**
     * Deleta um evento do Google Calendar.
     *
     * @since 2.0.0
     *
     * @param string $event_id ID do evento no Google Calendar.
     * @return bool|WP_Error True em caso de sucesso ou WP_Error.
     */
    public static function delete_event( $event_id ) {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = self::API_BASE_URL . '/calendars/' . self::CALENDAR_ID . '/events/' . $event_id;
        
        $response = wp_remote_request( $url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        
        // 204 No Content = sucesso
        if ( 204 === $code ) {
            return true;
        }
        
        // 410 Gone = evento já foi deletado (considerar sucesso)
        if ( 410 === $code ) {
            return true;
        }
        
        return new WP_Error(
            'delete_event_failed',
            sprintf(
                /* translators: %d: HTTP status code */
                __( 'Falha ao deletar evento. HTTP Code: %d', 'dps-agenda-addon' ),
                $code
            )
        );
    }
    
    /**
     * Obtém um evento do Google Calendar.
     *
     * @since 2.0.0
     *
     * @param string $event_id ID do evento.
     * @return array|WP_Error Array com dados do evento ou WP_Error.
     */
    public static function get_event( $event_id ) {
        $access_token = DPS_Google_Auth::get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = self::API_BASE_URL . '/calendars/' . self::CALENDAR_ID . '/events/' . $event_id;
        
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 15,
        ] );
        
        return self::process_response( $response, 'get_event' );
    }
    
    /**
     * Processa resposta da API.
     *
     * @since 2.0.0
     *
     * @param array|WP_Error $response Resposta do wp_remote_*.
     * @param string         $context  Contexto da operação.
     * @return array|WP_Error Dados processados ou WP_Error.
     */
    private static function process_response( $response, $context ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Sucesso (200, 201)
        if ( $code >= 200 && $code < 300 ) {
            return $data;
        }
        
        // Erro
        $error_message = '';
        if ( isset( $data['error']['message'] ) ) {
            $error_message = $data['error']['message'];
        } else {
            $error_message = sprintf(
                /* translators: %d: HTTP status code */
                __( 'Erro HTTP %d', 'dps-agenda-addon' ),
                $code
            );
        }
        
        return new WP_Error(
            'calendar_api_error_' . $context,
            $error_message,
            [
                'status' => $code,
                'data'   => $data,
            ]
        );
    }
    
    /**
     * Formata data/hora para RFC3339 (formato do Google Calendar).
     *
     * @since 2.0.0
     *
     * @param string $date Data (Y-m-d).
     * @param string $time Hora (H:i).
     * @param string $timezone Timezone (padrão: timezone do WordPress).
     * @return string Data/hora formatada em RFC3339.
     */
    public static function format_datetime( $date, $time, $timezone = null ) {
        if ( null === $timezone ) {
            $timezone = wp_timezone_string();
        }
        
        $datetime = new DateTime( $date . ' ' . $time, new DateTimeZone( $timezone ) );
        return $datetime->format( 'c' ); // ISO 8601 / RFC3339
    }
}
