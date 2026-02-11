<?php
/**
 * Módulo de Agendamento (stub — Fase 3).
 *
 * Será responsável por consolidar o fluxo de agendamento público,
 * hoje no add-on desi-pet-shower-booking.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Booking_Module {

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Inicializa o módulo quando habilitado pela feature flag.
     */
    public function boot(): void {
        $this->logger->info( 'Módulo Booking pronto (stub Fase 3).' );
    }
}
