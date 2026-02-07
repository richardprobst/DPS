<?php
/**
 * Endpoints REST do programa de fidelidade.
 *
 * @package Desi_Pet_Shower_Loyalty
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Loyalty_REST {

    const API_NAMESPACE = 'dps-loyalty/v1';

    /**
     * Registra rotas.
     */
    public static function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/client/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_client' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
                'args'                => [
                    'id' => [
                        'validate_callback' => 'is_numeric',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/client-by-ref/(?P<code>[A-Za-z0-9_-]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_client_by_ref' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/summary',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_summary' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
                'args'                => [
                    'months' => [
                        'default'           => 3,
                        'validate_callback' => 'is_numeric',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/campaign-settings',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_campaign_settings' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/campaign-settings',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'update_campaign_settings' ],
                'permission_callback' => [ __CLASS__, 'can_access' ],
            ]
        );
    }

    /**
     * Checa permissão.
     */
    public static function can_access() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Retorna dados de cliente.
     */
    public static function get_client( WP_REST_Request $request ) {
        $client_id = (int) $request['id'];

        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return new WP_Error( 'not_found', __( 'Cliente não encontrado.', 'dps-loyalty-addon' ), [ 'status' => 404 ] );
        }

        $tier         = DPS_Loyalty_API::get_loyalty_tier( $client_id );
        $achievements = class_exists( 'DPS_Loyalty_Achievements' ) ? DPS_Loyalty_Achievements::get_client_achievements( $client_id ) : [];

        return new WP_REST_Response(
            [
                'client_id'     => $client_id,
                'name'          => $client->post_title,
                'points'        => DPS_Loyalty_API::get_points( $client_id ),
                'credit_cents'  => DPS_Loyalty_API::get_credit( $client_id ),
                'tier'          => $tier,
                'achievements'  => self::format_achievements_response( $achievements ),
            ]
        );
    }

    /**
     * Retorna cliente pelo código de indicação.
     */
    public static function get_client_by_ref( WP_REST_Request $request ) {
        $code = sanitize_text_field( $request['code'] );
        if ( ! $code ) {
            return new WP_Error( 'invalid_code', __( 'Código inválido.', 'dps-loyalty-addon' ), [ 'status' => 400 ] );
        }

        $client = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_referral_code',
                    'value' => $code,
                ],
            ],
        ] );

        if ( empty( $client ) ) {
            return new WP_Error( 'not_found', __( 'Cliente não encontrado.', 'dps-loyalty-addon' ), [ 'status' => 404 ] );
        }

        $request->set_param( 'id', (int) $client[0] );
        return self::get_client( $request );
    }

    /**
     * Resumo agregado simples.
     */
    public static function get_summary( WP_REST_Request $request ) {
        $months = max( 1, absint( $request->get_param( 'months' ) ) );
        $timeseries = DPS_Loyalty_API::get_points_timeseries( $months );

        return new WP_REST_Response(
            [
                'months'       => $months,
                'labels'       => $timeseries['labels'],
                'granted'      => $timeseries['granted'],
                'redeemed'     => $timeseries['redeemed'],
                'clients_by_tier' => DPS_Loyalty_API::get_tier_distribution(),
            ]
        );
    }

    /**
     * Retorna o estado dos toggles de campanha.
     *
     * @since 2.0.0
     */
    public static function get_campaign_settings() {
        return new WP_REST_Response(
            [
                'enable_campaign_referrals'  => DPS_Loyalty_Addon::is_campaign_enabled( 'referrals' ),
                'enable_campaign_points'     => DPS_Loyalty_Addon::is_campaign_enabled( 'points' ),
                'enable_campaign_promotions' => DPS_Loyalty_Addon::is_campaign_enabled( 'promotions' ),
            ]
        );
    }

    /**
     * Atualiza os toggles de campanha via REST.
     *
     * @since 2.0.0
     */
    public static function update_campaign_settings( WP_REST_Request $request ) {
        $settings = get_option( DPS_Loyalty_Addon::OPTION_KEY, [] );
        $body     = $request->get_json_params();

        $allowed_keys = [ 'enable_campaign_referrals', 'enable_campaign_points', 'enable_campaign_promotions' ];

        foreach ( $allowed_keys as $key ) {
            if ( isset( $body[ $key ] ) ) {
                $settings[ $key ] = ! empty( $body[ $key ] ) ? 1 : 0;
            }
        }

        update_option( DPS_Loyalty_Addon::OPTION_KEY, $settings );

        return new WP_REST_Response(
            [
                'enable_campaign_referrals'  => DPS_Loyalty_Addon::is_campaign_enabled( 'referrals' ),
                'enable_campaign_points'     => DPS_Loyalty_Addon::is_campaign_enabled( 'points' ),
                'enable_campaign_promotions' => DPS_Loyalty_Addon::is_campaign_enabled( 'promotions' ),
                'updated'                    => true,
            ]
        );
    }

    /**
     * Formata conquistas para resposta REST.
     *
     * @param array $achievements Lista de conquistas desbloqueadas.
     * @return array
     */
    private static function format_achievements_response( $achievements ) {
        $definitions = class_exists( 'DPS_Loyalty_Achievements' ) ? DPS_Loyalty_Achievements::get_achievements_definitions() : [];
        $items       = [];

        foreach ( $definitions as $key => $definition ) {
            $items[] = [
                'id'          => $key,
                'label'       => $definition['label'],
                'description' => $definition['description'],
                'unlocked'    => in_array( $key, $achievements, true ),
            ];
        }

        return $items;
    }
}

add_action( 'rest_api_init', [ 'DPS_Loyalty_REST', 'register_routes' ] );
