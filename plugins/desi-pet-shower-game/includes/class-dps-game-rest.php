<?php
/**
 * Endpoints REST do Space Groomers.
 *
 * @package DPS_Game
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Game_REST {

    const API_NAMESPACE = 'dps-game/v1';

    /**
     * Registra rotas.
     *
     * @return void
     */
    public static function register_routes(): void {
        register_rest_route(
            self::API_NAMESPACE,
            '/progress',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_progress' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
                'args'                => [
                    'client_id' => [
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/progress/sync',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'sync_progress' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
                'args'                => [
                    'client_id' => [
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    /**
     * Verifica acesso a rotas do jogo.
     *
     * @param WP_REST_Request $request Request atual.
     * @return true|WP_Error
     */
    public static function can_access( WP_REST_Request $request ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( ! self::verify_nonce( $request ) ) {
            return new WP_Error(
                'dps_game_invalid_nonce',
                __( 'Nonce invalido para sincronizacao do jogo.', 'dps-game' ),
                [ 'status' => 403 ]
            );
        }

        $client_id = DPS_Game_Progress_Service::resolve_request_client_id( $request );
        if ( $client_id <= 0 ) {
            return new WP_Error(
                'dps_game_forbidden',
                __( 'Cliente nao autenticado no portal.', 'dps-game' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Retorna progresso consolidado do cliente.
     *
     * @param WP_REST_Request $request Request atual.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_progress( WP_REST_Request $request ) {
        $client_id = DPS_Game_Progress_Service::resolve_request_client_id( $request );
        if ( $client_id <= 0 ) {
            return new WP_Error(
                'dps_game_client_not_found',
                __( 'Cliente nao encontrado.', 'dps-game' ),
                [ 'status' => 404 ]
            );
        }

        $progress = DPS_Game_Progress_Service::get_progress( $client_id );

        return new WP_REST_Response(
            [
                'clientId' => $client_id,
                'progress' => $progress,
                'summary'  => DPS_Game_Progress_Service::build_summary( $client_id, $progress ),
            ]
        );
    }

    /**
     * Sincroniza progresso enviado pelo frontend.
     *
     * @param WP_REST_Request $request Request atual.
     * @return WP_REST_Response|WP_Error
     */
    public static function sync_progress( WP_REST_Request $request ) {
        $client_id = DPS_Game_Progress_Service::resolve_request_client_id( $request );
        if ( $client_id <= 0 ) {
            return new WP_Error(
                'dps_game_client_not_found',
                __( 'Cliente nao encontrado.', 'dps-game' ),
                [ 'status' => 404 ]
            );
        }

        $payload = $request->get_json_params();
        if ( isset( $payload['progress'] ) && is_array( $payload['progress'] ) ) {
            $payload = $payload['progress'];
        }

        if ( ! is_array( $payload ) ) {
            $payload = [];
        }

        $result = DPS_Game_Progress_Service::sync_progress( $client_id, $payload );

        return new WP_REST_Response(
            [
                'clientId'       => $client_id,
                'progress'       => $result['progress'],
                'summary'        => $result['summary'],
                'awardedRewards' => $result['awardedRewards'],
                'loyalty'        => $result['loyalty'],
            ]
        );
    }

    /**
     * Verifica nonce custom do jogo.
     *
     * @param WP_REST_Request $request Request atual.
     * @return bool
     */
    private static function verify_nonce( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-DPS-Game-Nonce' );
        if ( empty( $nonce ) ) {
            $nonce = $request->get_param( 'nonce' );
        }

        if ( ! is_string( $nonce ) || '' === $nonce ) {
            return false;
        }

        return (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'dps_game_progress' );
    }
}

add_action( 'rest_api_init', [ 'DPS_Game_REST', 'register_routes' ] );
