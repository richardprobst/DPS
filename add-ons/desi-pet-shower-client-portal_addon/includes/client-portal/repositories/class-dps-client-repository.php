<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de dados relacionadas a clientes.
 * 
 * Centraliza todas as consultas de dados de clientes (CPT dps_cliente),
 * seguindo o padrão Repository para isolar lógica de acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Client_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Client_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Client_Repository
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        // Nada a inicializar por enquanto
    }

    /**
     * Busca um cliente por ID.
     *
     * @param int $client_id ID do cliente.
     * @return WP_Post|null Objeto do cliente ou null se não encontrado.
     */
    public function get_client_by_id( $client_id ) {
        $client = get_post( $client_id );
        
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return null;
        }
        
        return $client;
    }

    /**
     * Busca um cliente por email.
     *
     * @param string $email Email do cliente.
     * @return WP_Post|null Objeto do cliente ou null se não encontrado.
     */
    public function get_client_by_email( $email ) {
        if ( ! is_email( $email ) ) {
            return null;
        }

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'client_email',
                    'value'   => $email,
                    'compare' => '=',
                ],
            ],
        ] );

        return ! empty( $clients ) ? $clients[0] : null;
    }

    /**
     * Busca um cliente por telefone.
     *
     * @param string $phone Telefone do cliente.
     * @return WP_Post|null Objeto do cliente ou null se não encontrado.
     */
    public function get_client_by_phone( $phone ) {
        if ( empty( $phone ) ) {
            return null;
        }

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'client_phone',
                    'value'   => $phone,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        return ! empty( $clients ) ? $clients[0] : null;
    }

    /**
     * Busca todos os clientes com paginação.
     *
     * @param array $args Argumentos de consulta (orderby, order, per_page, page, search).
     * @return array Array de posts de clientes.
     */
    public function get_clients( $args = [] ) {
        $defaults = [
            'orderby'  => 'title',
            'order'    => 'ASC',
            'per_page' => -1,
            'page'     => 1,
            'search'   => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $query_args = [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
        ];

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = $args['search'];
        }

        $query = new WP_Query( $query_args );
        
        return $query->posts;
    }
}
