<?php
/**
 * Módulo de Cadastro (stub — Fase 2).
 *
 * Será responsável por consolidar o formulário público de cadastro
 * de clientes e pets, hoje no add-on desi-pet-shower-registration.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Registration_Module {

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Inicializa o módulo quando habilitado pela feature flag.
     */
    public function boot(): void {
        $this->logger->info( 'Módulo Registration pronto (stub Fase 2).' );
    }
}
