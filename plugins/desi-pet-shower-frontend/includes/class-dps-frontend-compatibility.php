<?php
/**
 * Camada de compatibilidade do Frontend Add-on.
 *
 * Gerencia aliases de shortcode e bridges de hooks para manter
 * paridade com os add-ons legados durante a transição.
 *
 * Fase 1: esqueleto vazio — bridges serão adicionados nas fases 2-4
 * conforme cada módulo for migrado.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
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
        // Fase 2: shortcode aliases para cadastro
        // Fase 3: shortcode aliases para agendamento
        // Fase 4: bridges de hooks de configurações
    }
}
