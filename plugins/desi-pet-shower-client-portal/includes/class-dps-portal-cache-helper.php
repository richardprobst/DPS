<?php
/**
 * Helper de compatibilidade do Portal do Cliente.
 *
 * Mantem o contrato publico da antiga API de cache, mas sempre executa
 * as secoes em tempo real para cumprir a politica global sem cache.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Cache_Helper' ) ) :

/**
 * Classe de compatibilidade para contratos historicos do portal.
 */
final class DPS_Portal_Cache_Helper {

    /**
     * Prefixo legado mantido para compatibilidade com integracoes externas.
     *
     * @var string
     */
    const CACHE_PREFIX = 'dps_portal_';

    /**
     * Valor legado mantido para filtros existentes. Nao controla armazenamento.
     *
     * @var int
     */
    const CACHE_LIFETIME = HOUR_IN_SECONDS;

    /**
     * Renderiza uma secao sempre em tempo real.
     *
     * @param string   $section    Nome da secao.
     * @param int      $client_id  ID do cliente.
     * @param callable $callback   Funcao que gera o conteudo.
     * @param int|null $expiration Valor legado recebido por compatibilidade.
     * @return string Conteudo da secao.
     */
    public static function get_cached_section( $section, $client_id, $callback, $expiration = null ) {
        $expiration = $expiration ?? self::CACHE_LIFETIME;

        apply_filters( 'dps_portal_disable_cache', false, $section, $client_id );
        apply_filters( 'dps_portal_cache_expiration', $expiration, $section, $client_id );

        ob_start();
        call_user_func( $callback, $client_id );
        return ob_get_clean();
    }

    /**
     * Notifica integracoes sobre atualizacao de dados de um cliente.
     *
     * @param int          $client_id ID do cliente.
     * @param string|array $sections  Secoes afetadas (null = todas).
     * @return void
     */
    public static function invalidate_client_cache( $client_id, $sections = null ) {
        if ( null === $sections ) {
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

        do_action( 'dps_portal_cache_invalidated', $client_id, $sections );
    }

    /**
     * Notifica integracoes sobre atualizacao global do portal.
     *
     * @return void
     */
    public static function invalidate_all_cache() {
        do_action( 'dps_portal_all_cache_invalidated' );
    }

    /**
     * Retorna estatisticas de compatibilidade.
     *
     * @return array<string, mixed>
     */
    public static function get_cache_stats() {
        return [
            'total_cached_sections' => 0,
            'cache_prefix'          => self::CACHE_PREFIX,
            'default_lifetime'      => self::CACHE_LIFETIME,
            'cache_enabled'         => false,
            'storage_mode'          => 'realtime',
        ];
    }
}

endif;
