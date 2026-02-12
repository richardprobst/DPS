<?php
/**
 * Service de pets (Fase 7).
 *
 * CRUD para o post type dps_pet. Cria pets com metas
 * padronizadas conforme o add-on legado de registro.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Pet_Service extends DPS_Abstract_Service {

    protected function postType(): string {
        return 'dps_pet';
    }

    /**
     * Cria um novo pet vinculado a um cliente.
     *
     * @param int                  $clientId ID do cliente dono.
     * @param array<string, mixed> $data     Dados do pet.
     * @return int|false ID do pet criado ou false em caso de erro.
     */
    public function create( int $clientId, array $data ): int|false {
        $name = $data['pet_name'] ?? '';

        $postData = [
            'post_title'  => sanitize_text_field( $name ),
            'post_status' => 'publish',
        ];

        $meta = [
            'owner_id'    => $clientId,
            'pet_species' => sanitize_text_field( $data['pet_species'] ?? '' ),
            'pet_breed'   => sanitize_text_field( $data['pet_breed'] ?? '' ),
            'pet_size'    => sanitize_text_field( $data['pet_size'] ?? '' ),
            'pet_obs'     => sanitize_textarea_field( $data['pet_obs'] ?? '' ),
        ];

        return $this->createPost( $postData, $meta );
    }
}
