<?php
/**
 * Camada de compatibilidade do Frontend Add-on.
 *
 * Gerencia aliases de shortcode e bridges de hooks para manter
 * paridade com os add-ons legados durante a transição.
 *
 * Fase 2: bridge de shortcode para módulo de cadastro.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 * @since   1.1.0 Fase 2 — bridge de shortcode registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Compatibility {

    public function __construct(
        private readonly DPS_Frontend_Feature_Flags $flags,
        private readonly DPS_Frontend_Logger        $logger,
    ) {}

    /**
     * Registra bridges de shortcodes e hooks legados.
     *
     * Invocado durante o boot do add-on. Cada bridge só é registrado
     * quando o módulo correspondente está habilitado via feature flag.
     */
    public function registerBridges(): void {
        $this->registerRegistrationBridges();
        // Fase 3: shortcode aliases para agendamento
        // Fase 4: bridges de hooks de configurações
    }

    /**
     * Bridges do módulo de cadastro (Fase 2).
     *
     * Quando habilitado, loga uso do shortcode legado para telemetria.
     * O shortcode em si é assumido pelo módulo Registration diretamente
     * (remove_shortcode + add_shortcode no boot do módulo).
     */
    private function registerRegistrationBridges(): void {
        if ( ! $this->flags->isEnabled( 'registration' ) ) {
            return;
        }

        $this->logger->info( 'Bridge de compatibilidade do módulo Registration ativo.' );
    }
}
