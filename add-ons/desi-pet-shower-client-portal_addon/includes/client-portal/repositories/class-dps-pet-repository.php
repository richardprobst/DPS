<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de dados relacionadas a pets.
 * 
 * Centraliza todas as consultas de dados de pets (CPT dps_pet),
 * seguindo o padrão Repository para isolar lógica de acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Pet_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Pet_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Pet_Repository
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
     * Busca um pet por ID.
     *
     * @param int $pet_id ID do pet.
     * @return WP_Post|null Objeto do pet ou null se não encontrado.
     */
    public function get_pet( $pet_id ) {
        $pet = get_post( $pet_id );
        
        if ( ! $pet || 'dps_pet' !== $pet->post_type ) {
            return null;
        }
        
        return $pet;
    }

    /**
     * Busca todos os pets de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de posts de pets.
     */
    public function get_pets_by_client( $client_id ) {
        return get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    /**
     * Verifica se um pet pertence a um cliente.
     *
     * @param int $pet_id    ID do pet.
     * @param int $client_id ID do cliente.
     * @return bool True se o pet pertence ao cliente.
     */
    public function pet_belongs_to_client( $pet_id, $client_id ) {
        $owner_id = absint( get_post_meta( $pet_id, 'owner_id', true ) );
        return $owner_id === absint( $client_id );
    }
}
