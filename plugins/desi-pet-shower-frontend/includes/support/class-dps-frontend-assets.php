<?php
/**
 * Gerenciador de assets do Frontend Add-on.
 *
 * Enfileira CSS e JS condicionalmente — somente quando ao menos um
 * módulo está habilitado. Garante dependência de dps-design-tokens.css
 * para conformidade M3 Expressive.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Assets {

    public function __construct(
        private readonly DPS_Frontend_Feature_Flags $flags,
    ) {}

    /**
     * Callback de wp_enqueue_scripts.
     *
     * Só carrega quando há módulo ativo — enqueue verdadeiramente condicional.
     */
    public function enqueue(): void {
        if ( ! $this->flags->hasAnyEnabled() ) {
            return;
        }

        $this->ensureDesignTokens();

        wp_enqueue_style(
            'dps-frontend-addon',
            DPS_FRONTEND_URL . 'assets/css/frontend-addon.css',
            [ 'dps-design-tokens' ],
            DPS_FRONTEND_VERSION
        );

        wp_enqueue_script(
            'dps-frontend-addon',
            DPS_FRONTEND_URL . 'assets/js/frontend-addon.js',
            [],
            DPS_FRONTEND_VERSION,
            true
        );
    }

    /**
     * Garante que dps-design-tokens esteja registrado e enfileirado.
     */
    private function ensureDesignTokens(): void {
        if ( wp_style_is( 'dps-design-tokens', 'enqueued' ) ) {
            return;
        }

        if ( ! defined( 'DPS_BASE_URL' ) || ! defined( 'DPS_BASE_VERSION' ) ) {
            return;
        }

        wp_enqueue_style(
            'dps-design-tokens',
            DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
            [],
            DPS_BASE_VERSION
        );
    }
}
