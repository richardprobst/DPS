<?php
/**
 * Rate limiting persistente para fluxos públicos do Portal do Cliente.
 *
 * @package DPS_Client_Portal
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Rate_Limiter' ) ) :

final class DPS_Portal_Rate_Limiter {

    /**
     * Option que armazena o estado do rate limiting.
     *
     * @var string
     */
    private const OPTION_KEY = 'dps_portal_rate_limits';

    /**
     * Instância única.
     *
     * @var DPS_Portal_Rate_Limiter|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Portal_Rate_Limiter
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Verifica se um identificador excedeu o limite.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @param int    $limit Máximo permitido na janela.
     * @return bool
     */
    public function is_limited( $bucket, $identifier, $limit ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );

        if ( ! isset( $state[ $key ] ) || ! is_array( $state[ $key ] ) ) {
            return false;
        }

        if ( $this->is_expired( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
            return false;
        }

        return (int) $state[ $key ]['count'] >= (int) $limit;
    }

    /**
     * Registra uma nova tentativa.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @param int    $window_seconds Janela em segundos.
     * @return int Número de tentativas acumuladas.
     */
    public function hit( $bucket, $identifier, $window_seconds ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );
        $now   = time();

        $this->purge_expired_entries( $state, $now );

        if ( ! isset( $state[ $key ] ) || ! is_array( $state[ $key ] ) || $this->is_expired( $state[ $key ], $now ) ) {
            $state[ $key ] = [
                'count'      => 0,
                'expires_at' => $now + max( 1, (int) $window_seconds ),
            ];
        }

        $state[ $key ]['count'] = (int) $state[ $key ]['count'] + 1;
        $this->save_state( $state );

        return (int) $state[ $key ]['count'];
    }

    /**
     * Limpa o contador de um identificador.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @return void
     */
    public function clear( $bucket, $identifier ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );

        if ( isset( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
        }
    }

    /**
     * Recupera o estado persistido.
     *
     * @return array<string, array<string, int>>
     */
    private function get_state() {
        $state = get_option( self::OPTION_KEY, [] );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Persiste o estado.
     *
     * @param array<string, array<string, int>> $state Estado atualizado.
     * @return void
     */
    private function save_state( array $state ) {
        update_option( self::OPTION_KEY, $state, false );
    }

    /**
     * Remove entradas expiradas.
     *
     * @param array<string, array<string, int>> $state Estado atual.
     * @param int                               $now Timestamp de referência.
     * @return void
     */
    private function purge_expired_entries( array &$state, $now ) {
        foreach ( $state as $key => $entry ) {
            if ( ! is_array( $entry ) || $this->is_expired( $entry, $now ) ) {
                unset( $state[ $key ] );
            }
        }
    }

    /**
     * Determina se a entrada expirou.
     *
     * @param array<string, int> $entry Entrada monitorada.
     * @param int|null           $now Timestamp de referência.
     * @return bool
     */
    private function is_expired( array $entry, $now = null ) {
        $now = null === $now ? time() : (int) $now;

        return empty( $entry['expires_at'] ) || (int) $entry['expires_at'] <= $now;
    }

    /**
     * Monta a chave interna.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @return string
     */
    private function build_key( $bucket, $identifier ) {
        return sanitize_key( $bucket ) . ':' . md5( strtolower( trim( (string) $identifier ) ) );
    }
}

endif;
