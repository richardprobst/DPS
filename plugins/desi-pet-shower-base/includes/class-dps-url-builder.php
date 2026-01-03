<?php
/**
 * Helper class para construção de URLs do painel.
 *
 * @package DesiPetShower
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe utilitária para construção consistente de URLs no plugin.
 */
class DPS_URL_Builder {

    /**
     * Constrói URL para editar um registro.
     *
     * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
     * @param int    $record_id ID do registro.
     * @param string $tab Aba de destino (opcional).
     * @param string $base_url URL base (opcional, usa permalink atual se não fornecida).
     * @return string URL completa para edição.
     */
    public static function build_edit_url( $record_type, $record_id, $tab = '', $base_url = null ) {
        if ( null === $base_url ) {
            $base_url = self::get_clean_current_url();
        }

        $query_args = [
            'dps_edit' => sanitize_key( $record_type ),
            'id' => absint( $record_id ),
        ];

        if ( ! empty( $tab ) ) {
            $query_args['tab'] = sanitize_key( $tab );
        }

        return add_query_arg( $query_args, $base_url );
    }

    /**
     * Constrói URL para excluir um registro com nonce de segurança.
     *
     * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
     * @param int    $record_id ID do registro.
     * @param string $tab Aba de destino (opcional).
     * @param string $base_url URL base (opcional, usa permalink atual se não fornecida).
     * @return string URL completa para exclusão com nonce.
     */
    public static function build_delete_url( $record_type, $record_id, $tab = '', $base_url = null ) {
        if ( null === $base_url ) {
            $base_url = self::get_clean_current_url();
        }

        $query_args = [
            'dps_delete' => sanitize_key( $record_type ),
            'id' => absint( $record_id ),
            'dps_nonce' => wp_create_nonce( 'dps_delete' ),
        ];

        if ( ! empty( $tab ) ) {
            $query_args['tab'] = sanitize_key( $tab );
        }

        return add_query_arg( $query_args, $base_url );
    }

    /**
     * Constrói URL para visualizar detalhes de um registro.
     *
     * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
     * @param int    $record_id ID do registro.
     * @param string $base_url URL base (opcional, usa permalink atual se não fornecida).
     * @return string URL completa para visualização.
     */
    public static function build_view_url( $record_type, $record_id, $base_url = null ) {
        if ( null === $base_url ) {
            $base_url = self::get_clean_current_url();
        }

        return add_query_arg( [
            'dps_view' => sanitize_key( $record_type ),
            'id' => absint( $record_id ),
        ], $base_url );
    }

    /**
     * Constrói URL para uma aba específica.
     *
     * @param string $tab Nome da aba.
     * @param string $base_url URL base (opcional, usa permalink atual se não fornecida).
     * @return string URL completa para a aba.
     */
    public static function build_tab_url( $tab, $base_url = null ) {
        if ( null === $base_url ) {
            $base_url = self::get_clean_current_url();
        }

        return add_query_arg( 'tab', sanitize_key( $tab ), $base_url );
    }

    /**
     * Constrói URL para agendar atendimento para um cliente específico.
     *
     * @param int    $client_id ID do cliente.
     * @param string $base_url URL base (opcional, usa permalink atual se não fornecida).
     * @return string URL completa para agendamento.
     */
    public static function build_schedule_url( $client_id, $base_url = null ) {
        if ( null === $base_url ) {
            $base_url = self::get_clean_current_url();
        }

        return add_query_arg( [
            'tab' => 'agendas',
            'pref_client' => absint( $client_id ),
        ], $base_url );
    }

    /**
     * Remove parâmetros de ação da URL.
     *
     * @param string $url URL para limpar.
     * @return string URL sem parâmetros de ação.
     */
    public static function remove_action_params( $url ) {
        return remove_query_arg(
            [ 'dps_delete', 'id', 'dps_edit', 'dps_view', 'tab', 'dps_action', 'dps_nonce' ],
            (string) $url
        );
    }

    /**
     * Safe wrapper for get_permalink() that always returns a string.
     *
     * Prevents PHP 8.1+ deprecation warnings caused by passing null/false
     * to functions like strpos(), str_replace(), add_query_arg(), etc.
     *
     * @param int|WP_Post|null $post Optional. Post ID or post object. Default is the global `$post`.
     * @return string The permalink URL or home_url() as fallback.
     */
    public static function safe_get_permalink( $post = null ) {
        $permalink = get_permalink( $post );

        if ( $permalink && is_string( $permalink ) ) {
            return $permalink;
        }

        // Fallback: try queried object
        if ( null === $post ) {
            $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
            if ( $queried_id ) {
                $permalink = get_permalink( $queried_id );
                if ( $permalink && is_string( $permalink ) ) {
                    return $permalink;
                }
            }

            // Fallback: try global $post
            global $post;
            if ( isset( $post->ID ) ) {
                $permalink = get_permalink( $post->ID );
                if ( $permalink && is_string( $permalink ) ) {
                    return $permalink;
                }
            }
        }

        // Final fallback: home URL (always a valid string)
        return home_url();
    }

    /**
     * Obtém URL base da página atual sem parâmetros de ação.
     *
     * @return string URL base limpa.
     */
    public static function get_clean_current_url() {
        $current_url = '';

        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
            if ( is_string( $request_uri ) && '' !== $request_uri ) {
                $current_url = esc_url_raw( home_url( $request_uri ) );
            }
        }

        if ( empty( $current_url ) ) {
            $current_url = self::safe_get_permalink();
        }

        // Ensure we always return a string, never null/false
        if ( empty( $current_url ) || ! is_string( $current_url ) ) {
            $current_url = home_url();
        }

        return self::remove_action_params( $current_url );
    }
}
