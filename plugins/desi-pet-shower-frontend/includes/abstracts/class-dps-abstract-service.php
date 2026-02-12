<?php
/**
 * Classe base abstrata para services (Fase 7).
 *
 * Services encapsulam acesso a dados (CRUD de posts, metas, options).
 * Cada service recebe dependências via construtor (DI) e não mantém
 * estado entre chamadas.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class DPS_Abstract_Service {

    /**
     * Retorna o post type gerenciado pelo service.
     */
    abstract protected function postType(): string;

    /**
     * Cria um novo post com metas.
     *
     * @param array<string, mixed> $postData Dados do post (title, content, status, etc.).
     * @param array<string, mixed> $meta     Metas a associar ao post.
     * @return int|false ID do post criado ou false em caso de erro.
     */
    protected function createPost( array $postData, array $meta = [] ): int|false {
        $defaults = [
            'post_type'   => $this->postType(),
            'post_status' => 'publish',
        ];

        $args = array_merge( $defaults, $postData );
        $id   = wp_insert_post( $args, true );

        if ( is_wp_error( $id ) ) {
            return false;
        }

        foreach ( $meta as $key => $value ) {
            update_post_meta( $id, $key, $value );
        }

        return $id;
    }

    /**
     * Atualiza metas de um post existente.
     *
     * @param int                  $postId ID do post.
     * @param array<string, mixed> $meta   Metas a atualizar.
     */
    protected function updateMeta( int $postId, array $meta ): void {
        foreach ( $meta as $key => $value ) {
            update_post_meta( $postId, $key, $value );
        }
    }
}
