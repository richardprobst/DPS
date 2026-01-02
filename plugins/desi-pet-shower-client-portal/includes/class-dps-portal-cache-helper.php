<?php
/**
 * Helper de Cache do Portal do Cliente
 *
 * Gerencia cache de seções do portal para melhorar performance.
 * Usa transients do WordPress com invalidação inteligente.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Cache_Helper' ) ) :

/**
 * Classe helper para gerenciamento de cache do portal
 */
final class DPS_Portal_Cache_Helper {

    /**
     * Prefixo para chaves de cache
     *
     * @var string
     */
    const CACHE_PREFIX = 'dps_portal_';

    /**
     * Tempo de vida padrão do cache (1 hora)
     *
     * @var int
     */
    const CACHE_LIFETIME = HOUR_IN_SECONDS;

    /**
     * Obtém conteúdo cacheado de uma seção
     *
     * @param string   $section    Nome da seção (ex: 'next_appt', 'history', 'gallery')
     * @param int      $client_id  ID do cliente
     * @param callable $callback   Função que gera o conteúdo se não estiver em cache
     * @param int      $expiration Tempo de expiração em segundos (padrão: 1 hora)
     * @return string Conteúdo da seção
     */
    public static function get_cached_section( $section, $client_id, $callback, $expiration = null ) {
        // Permite desabilitar cache via filtro
        if ( apply_filters( 'dps_portal_disable_cache', false, $section, $client_id ) ) {
            ob_start();
            call_user_func( $callback, $client_id );
            return ob_get_clean();
        }

        $cache_key = self::get_cache_key( $section, $client_id );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Gera conteúdo
        ob_start();
        call_user_func( $callback, $client_id );
        $output = ob_get_clean();

        // Armazena em cache
        $expiration = $expiration ?? self::CACHE_LIFETIME;
        $expiration = apply_filters( 'dps_portal_cache_expiration', $expiration, $section, $client_id );
        
        set_transient( $cache_key, $output, $expiration );

        return $output;
    }

    /**
     * Invalida cache de um cliente específico
     *
     * @param int          $client_id ID do cliente
     * @param string|array $sections  Seções a invalidar (null = todas)
     */
    public static function invalidate_client_cache( $client_id, $sections = null ) {
        if ( null === $sections ) {
            // Invalida todas as seções padrão
            $sections = [
                'next_appt',
                'history',
                'gallery',
                'pending',
                'referrals',
                'pets',
                'messages',
            ];
        }

        if ( ! is_array( $sections ) ) {
            $sections = [ $sections ];
        }

        foreach ( $sections as $section ) {
            $cache_key = self::get_cache_key( $section, $client_id );
            delete_transient( $cache_key );
        }

        do_action( 'dps_portal_cache_invalidated', $client_id, $sections );
    }

    /**
     * Invalida cache de todos os clientes
     *
     * Útil quando há mudanças globais (ex: atualização de serviços)
     */
    public static function invalidate_all_cache() {
        global $wpdb;

        // Remove todos os transients do portal
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%',
                $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%'
            )
        );

        do_action( 'dps_portal_all_cache_invalidated' );
    }

    /**
     * Gera chave de cache única
     *
     * @param string $section   Nome da seção
     * @param int    $client_id ID do cliente
     * @return string Chave de cache
     */
    private static function get_cache_key( $section, $client_id ) {
        return self::CACHE_PREFIX . $section . '_' . $client_id;
    }

    /**
     * Retorna estatísticas de cache (para debug)
     *
     * @return array Estatísticas de cache
     */
    public static function get_cache_stats() {
        global $wpdb;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%'
            )
        );

        return [
            'total_cached_sections' => intval( $total ),
            'cache_prefix'          => self::CACHE_PREFIX,
            'default_lifetime'      => self::CACHE_LIFETIME,
        ];
    }
}

endif;
