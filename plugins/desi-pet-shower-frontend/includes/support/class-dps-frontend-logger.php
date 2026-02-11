<?php
/**
 * Logger do Frontend Add-on.
 *
 * Fornece logging estruturado para diagnóstico e observabilidade.
 * Só escreve quando WP_DEBUG está ativo — zero overhead em produção.
 *
 * Inclui telemetria leve de uso (contadores por módulo) para apoiar
 * decisões de depreciação futura (Fase 6).
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 * @since   1.5.0 Fase 6 — telemetria de uso por módulo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Logger {

    private const PREFIX     = '[DPS Frontend]';
    private const USAGE_KEY  = 'dps_frontend_usage_counters';

    public function info( string $message ): void {
        $this->write( 'INFO', $message );
    }

    public function warning( string $message ): void {
        $this->write( 'WARNING', $message );
    }

    public function error( string $message ): void {
        $this->write( 'ERROR', $message );
    }

    /**
     * Registra uso de um módulo para telemetria.
     *
     * Incrementa um contador persistido por módulo. Usado pelos módulos
     * para rastrear quantas vezes cada shortcode é renderizado via
     * frontend add-on (vs. legado). Os contadores alimentam decisões
     * de depreciação documentadas em FRONTEND_DEPRECATION_POLICY.md.
     *
     * @since 1.5.0
     *
     * @param string $module Nome do módulo (ex.: 'registration', 'booking').
     */
    public function track( string $module ): void {
        $counters = get_option( self::USAGE_KEY, [] );

        if ( ! is_array( $counters ) ) {
            $counters = [];
        }

        $counters[ $module ] = ( $counters[ $module ] ?? 0 ) + 1;
        update_option( self::USAGE_KEY, $counters, false );
    }

    /**
     * Retorna os contadores de uso de todos os módulos.
     *
     * @since 1.5.0
     *
     * @return array<string, int>
     */
    public function getUsageCounters(): array {
        $counters = get_option( self::USAGE_KEY, [] );
        return is_array( $counters ) ? $counters : [];
    }

    private function write( string $level, string $message ): void {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return;
        }

        error_log( sprintf( '%s [%s] %s', self::PREFIX, $level, $message ) );
    }
}
