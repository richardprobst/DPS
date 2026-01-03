<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de dados relacionadas a mensagens do portal.
 * 
 * Centraliza todas as consultas de dados de mensagens (CPT dps_portal_message),
 * seguindo o padrão Repository para isolar lógica de acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Message_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Message_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Message_Repository
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
     * Busca mensagens de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @param int $limit     Limite de resultados (padrão: 50).
     * @return array Array de posts de mensagens.
     */
    public function get_messages_by_client( $client_id, $limit = 50 ) {
        return get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_key'       => 'message_client_id',
            'meta_value'     => $client_id,
        ] );
    }

    /**
     * Conta mensagens não lidas do admin para o cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int Quantidade de mensagens não lidas.
     */
    public function count_unread_messages( $client_id ) {
        $messages = get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'message_sender',
                    'value' => 'admin',
                ],
                [
                    'key'     => 'client_read_at',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        return count( $messages );
    }

    /**
     * Busca IDs de mensagens não lidas do admin para o cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de IDs de mensagens.
     */
    public function get_unread_message_ids( $client_id ) {
        return get_posts( [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'message_sender',
                    'value' => 'admin',
                ],
                [
                    'key'     => 'client_read_at',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );
    }
}
