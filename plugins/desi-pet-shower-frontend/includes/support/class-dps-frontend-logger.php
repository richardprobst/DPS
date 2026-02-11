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

    /**
     * Contadores pendentes no request atual (flush no shutdown).
     *
     * @var array<string, int>
     */
    private array $pendingCounts = [];

    /**
     * Se o hook de shutdown já foi registrado.
     */
    private bool $shutdownRegistered = false;

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
     * Acumula incrementos no request atual e persiste uma única vez
     * no shutdown do WordPress, evitando overhead de DB por render.
     *
     * @since 1.5.0
     *
     * @param string $module Nome do módulo (ex.: 'registration', 'booking').
     */
    public function track( string $module ): void {
        $this->pendingCounts[ $module ] = ( $this->pendingCounts[ $module ] ?? 0 ) + 1;

        if ( ! $this->shutdownRegistered ) {
            add_action( 'shutdown', [ $this, 'flushCounters' ] );
            $this->shutdownRegistered = true;
        }
    }

    /**
     * Persiste contadores acumulados no DB (chamado no shutdown).
     *
     * @since 1.5.0
     * @internal
     */
    public function flushCounters(): void {
        if ( [] === $this->pendingCounts ) {
            return;
        }

        $counters = get_option( self::USAGE_KEY, [] );

        if ( ! is_array( $counters ) ) {
            $counters = [];
        }

        foreach ( $this->pendingCounts as $module => $increment ) {
            $counters[ $module ] = ( $counters[ $module ] ?? 0 ) + $increment;
        }

        update_option( self::USAGE_KEY, $counters, false );
        $this->pendingCounts = [];
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
