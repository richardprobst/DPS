<?php
/**
 * Módulo de Configurações (stub — Fase 4).
 *
 * Será responsável por consolidar as configurações de experiência
 * frontend e integração com abas do painel administrativo.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Settings_Module {

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Inicializa o módulo quando habilitado pela feature flag.
     */
    public function boot(): void {
        $this->logger->info( 'Módulo Settings pronto (stub Fase 4).' );
    }
}
