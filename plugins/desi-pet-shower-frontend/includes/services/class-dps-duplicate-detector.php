<?php
/**
 * Detector de duplicatas por telefone (Fase 7).
 *
 * Verifica se já existe um cliente com o mesmo telefone.
 * Busca APENAS por telefone (email/CPF ignorados), conforme legado v1.3.0.
 *
 * Extraído de DPS_Registration_Addon::find_duplicate_client().
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Duplicate_Detector {

    /**
     * Busca cliente existente por telefone.
     *
     * @param string $phone Telefone normalizado.
     * @return int ID do cliente existente ou 0 se não encontrado.
     */
    public function findByPhone( string $phone ): int {
        if ( '' === $phone ) {
            return 0;
        }

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => 'client_phone',
                    'value' => $phone,
                ],
            ],
        ] );

        return ! empty( $clients ) ? (int) $clients[0] : 0;
    }

    /**
     * Verifica se o telefone é duplicado e o usuário pode fazer override.
     *
     * Non-admins: duplicata sempre bloqueia.
     * Admins: podem forçar o cadastro.
     *
     * @param string $phone    Telefone normalizado.
     * @param bool   $isAdmin  Se o usuário é admin.
     * @return array{duplicate: bool, client_id: int, can_override: bool}
     */
    public function check( string $phone, bool $isAdmin = false ): array {
        $existingId = $this->findByPhone( $phone );

        if ( 0 === $existingId ) {
            return [
                'duplicate'    => false,
                'client_id'    => 0,
                'can_override' => false,
            ];
        }

        return [
            'duplicate'    => true,
            'client_id'    => $existingId,
            'can_override' => $isAdmin,
        ];
    }
}
