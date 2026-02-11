<?php
/**
 * Logger do Frontend Add-on.
 *
 * Fornece logging estruturado para diagnóstico e observabilidade.
 * Só escreve quando WP_DEBUG está ativo — zero overhead em produção.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Logger {

    private const PREFIX = '[DPS Frontend]';

    public function info( string $message ): void {
        $this->write( 'INFO', $message );
    }

    public function warning( string $message ): void {
        $this->write( 'WARNING', $message );
    }

    public function error( string $message ): void {
        $this->write( 'ERROR', $message );
    }

    private function write( string $level, string $message ): void {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return;
        }

        error_log( sprintf( '%s [%s] %s', self::PREFIX, $level, $message ) );
    }
}
