<?php
/**
 * Helper class para consultas WP_Query comuns.
 *
 * @package DesiPetShower
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe utilitária para consultas padronizadas no WordPress.
 */
class DPS_Query_Helper {

    /**
     * Constrói argumentos base para consulta de posts.
     *
     * Define padrões comuns e permite sobrescrever via array de overrides.
     *
     * @param string $post_type Tipo de post a consultar.
     * @param array  $overrides Argumentos para sobrescrever os padrões.
     * @return array Argumentos completos para WP_Query.
     */
    public static function build_base_query_args( $post_type, $overrides = [] ) {
        $defaults = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        return array_merge( $defaults, $overrides );
    }

    /**
     * Obtém todos os posts de um tipo específico.
     *
     * @param string $post_type Tipo de post.
     * @param array  $extra_args Argumentos adicionais para a consulta.
     * @return array Lista de posts encontrados.
     */
    public static function get_all_posts_by_type( $post_type, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge( [ 'posts_per_page' => -1, 'no_found_rows' => true ], $extra_args )
        );

        $query = new WP_Query( $query_args );
        return $query->posts;
    }

    /**
     * Obtém posts paginados de um tipo específico.
     *
     * @param string $post_type Tipo de post.
     * @param int    $page Número da página (começa em 1).
     * @param int    $per_page Quantidade de posts por página.
     * @param array  $extra_args Argumentos adicionais para a consulta.
     * @return WP_Query Objeto de consulta completo.
     */
    public static function get_paginated_posts( $post_type, $page = 1, $per_page = 20, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge(
                [
                    'posts_per_page' => absint( $per_page ),
                    'paged' => max( 1, absint( $page ) ),
                ],
                $extra_args
            )
        );

        return new WP_Query( $query_args );
    }

    /**
     * Obtém posts filtrados por meta key e value.
     *
     * @param string $post_type Tipo de post.
     * @param string $meta_key Chave de meta.
     * @param mixed  $meta_value Valor de meta.
     * @param array  $extra_args Argumentos adicionais.
     * @return array Lista de posts encontrados.
     */
    public static function get_posts_by_meta( $post_type, $meta_key, $meta_value, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge(
                [
                    'posts_per_page' => -1,
                    'no_found_rows'  => true,
                    'meta_key'       => $meta_key,
                    'meta_value'     => $meta_value,
                ],
                $extra_args
            )
        );

        $query = new WP_Query( $query_args );
        return $query->posts;
    }

    /**
     * Obtém posts com meta_query customizada.
     *
     * @param string $post_type Tipo de post.
     * @param array  $meta_query Array de meta_query.
     * @param array  $extra_args Argumentos adicionais.
     * @return array Lista de posts encontrados.
     */
    public static function get_posts_by_meta_query( $post_type, $meta_query, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge(
                [
                    'posts_per_page' => -1,
                    'no_found_rows'  => true,
                    'meta_query'     => $meta_query,
                ],
                $extra_args
            )
        );

        $query = new WP_Query( $query_args );
        return $query->posts;
    }

    /**
     * Obtém contagem de posts de um tipo específico.
     *
     * @param string $post_type Tipo de post.
     * @param array  $extra_args Argumentos adicionais.
     * @return int Contagem de posts.
     */
    public static function count_posts_by_type( $post_type, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge(
                [
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ],
                $extra_args
            )
        );

        $query = new WP_Query( $query_args );
        return $query->found_posts;
    }

    /**
     * Verifica se existe pelo menos um post com os critérios fornecidos.
     *
     * @param string $post_type Tipo de post.
     * @param array  $extra_args Argumentos adicionais.
     * @return bool True se existe pelo menos um post.
     */
    public static function post_exists( $post_type, $extra_args = [] ) {
        $query_args = self::build_base_query_args(
            $post_type,
            array_merge(
                [
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                ],
                $extra_args
            )
        );

        $query = new WP_Query( $query_args );
        return ! empty( $query->posts );
    }
}
