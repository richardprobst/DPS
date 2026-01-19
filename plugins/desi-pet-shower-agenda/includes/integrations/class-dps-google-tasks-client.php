<?php
/**
 * Cliente HTTP para Google Tasks API v1
 *
 * Implementa operações CRUD de tarefas no Google Tasks.
 *
 * @package    DPS_Agenda_Addon
 * @subpackage Integrations
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe cliente para Google Tasks API.
 *
 * Gerencia comunicação HTTP com Google Tasks API v1:
 * - Criar tarefas
 * - Atualizar tarefas existentes
 * - Deletar tarefas
 * - Obter tarefas
 *
 * @since 2.0.0
 */
class DPS_Google_Tasks_Client {

    /**
     * Base URL da API Google Tasks.
     *
     * @since 2.0.0
     * @var string
     */
    const API_BASE_URL = 'https://www.googleapis.com/tasks/v1';

    /**
     * Cria uma nova tarefa.
     *
     * @since 2.0.0
     *
     * @param string $task_list_id ID da lista de tarefas (default: '@default').
     * @param array  $task_data    Dados da tarefa.
     * @return array|WP_Error Resposta da API ou erro.
     */
    public static function create_task( $task_list_id = '@default', $task_data = [] ) {
        $access_token = DPS_Google_Auth::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::API_BASE_URL . '/lists/' . rawurlencode( $task_list_id ) . '/tasks';

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $task_data ),
                'timeout' => 30,
            ]
        );

        return self::handle_response( $response );
    }

    /**
     * Atualiza uma tarefa existente.
     *
     * @since 2.0.0
     *
     * @param string $task_list_id ID da lista de tarefas.
     * @param string $task_id      ID da tarefa.
     * @param array  $task_data    Dados atualizados.
     * @return array|WP_Error Resposta da API ou erro.
     */
    public static function update_task( $task_list_id, $task_id, $task_data ) {
        $access_token = DPS_Google_Auth::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::API_BASE_URL . '/lists/' . rawurlencode( $task_list_id ) . '/tasks/' . rawurlencode( $task_id );

        $response = wp_remote_request(
            $url,
            [
                'method'  => 'PATCH',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $task_data ),
                'timeout' => 30,
            ]
        );

        return self::handle_response( $response );
    }

    /**
     * Deleta uma tarefa.
     *
     * @since 2.0.0
     *
     * @param string $task_list_id ID da lista de tarefas.
     * @param string $task_id      ID da tarefa.
     * @return true|WP_Error True se sucesso ou erro.
     */
    public static function delete_task( $task_list_id, $task_id ) {
        $access_token = DPS_Google_Auth::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::API_BASE_URL . '/lists/' . rawurlencode( $task_list_id ) . '/tasks/' . rawurlencode( $task_id );

        $response = wp_remote_request(
            $url,
            [
                'method'  => 'DELETE',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 204 ) {
            return true;
        }

        return new WP_Error(
            'google_tasks_delete_failed',
            sprintf( 'Falha ao deletar tarefa (código %d)', $code )
        );
    }

    /**
     * Obtém detalhes de uma tarefa.
     *
     * @since 2.0.0
     *
     * @param string $task_list_id ID da lista de tarefas.
     * @param string $task_id      ID da tarefa.
     * @return array|WP_Error Dados da tarefa ou erro.
     */
    public static function get_task( $task_list_id, $task_id ) {
        $access_token = DPS_Google_Auth::get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $url = self::API_BASE_URL . '/lists/' . rawurlencode( $task_list_id ) . '/tasks/' . rawurlencode( $task_id );

        $response = wp_remote_get(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'timeout' => 30,
            ]
        );

        return self::handle_response( $response );
    }

    /**
     * Formata data para formato RFC 3339 (Google Tasks).
     *
     * Nota: Google Tasks aceita RFC 3339 completo para 'due', mas ignora o componente de tempo,
     * usando apenas a data. O formato completo é mantido para compatibilidade com a API.
     *
     * @since 2.0.0
     *
     * @param string|int $date Data no formato Y-m-d (string) ou Unix timestamp (int).
     * @return string Data formatada em RFC 3339.
     */
    public static function format_due_date( $date ) {
        if ( is_numeric( $date ) ) {
            $timestamp = (int) $date;
        } else {
            $timestamp = strtotime( $date );
        }

        // Google Tasks aceita RFC 3339, mas só considera a data (ignora horário)
        return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
    }

    /**
     * Processa resposta HTTP da API.
     *
     * @since 2.0.0
     *
     * @param array|WP_Error $response Resposta do wp_remote_*.
     * @return array|WP_Error Dados decodificados ou erro.
     */
    private static function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $error_data = json_decode( $body, true );
            $message    = isset( $error_data['error']['message'] )
                ? $error_data['error']['message']
                : 'Erro desconhecido na Google Tasks API';

            return new WP_Error(
                'google_tasks_api_error',
                sprintf( 'Google Tasks API Error (HTTP %d): %s', $code, $message ),
                [ 'response' => $error_data ]
            );
        }

        return json_decode( $body, true );
    }
}
