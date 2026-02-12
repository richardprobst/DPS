<?php
/**
 * Módulo de Cadastro V2 nativo do Frontend Add-on (Fase 7).
 *
 * Implementação 100% nativa do formulário de cadastro com Material 3 Expressive.
 * Completamente independente do add-on legado DPS_Registration_Addon.
 *
 * Shortcode: [dps_registration_v2]
 * Feature flag: registration_v2
 *
 * Coexiste com o shortcode legado [dps_registration_form] (v1 dual-run).
 * Ambos podem estar ativos simultaneamente no mesmo site.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Registration_V2_Module extends DPS_Abstract_Module_V2 {

    public function __construct(
        DPS_Frontend_Logger             $logger,
        DPS_Template_Engine             $templateEngine,
        private readonly DPS_Registration_Hook_Bridge $hookBridge,
    ) {
        parent::__construct( $logger, $templateEngine );
    }

    protected function shortcodeTag(): string {
        return 'dps_registration_v2';
    }

    /**
     * Renderiza o shortcode [dps_registration_v2].
     *
     * @param array<string, string>|string $atts    Atributos do shortcode.
     * @param string|null                  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    public function renderShortcode( array|string $atts = [], ?string $content = null ): string {
        $atts = shortcode_atts( [
            'redirect_url'  => '',
            'show_pets'     => 'true',
            'show_marketing' => 'true',
            'theme'         => 'light',
            'compact'       => 'false',
        ], $atts, 'dps_registration_v2' );

        $this->hookBridge->beforeRender( $atts );

        $html = $this->templateEngine->render( 'registration/form-main.php', [
            'atts'           => $atts,
            'theme'          => $atts['theme'],
            'show_pets'      => 'true' === $atts['show_pets'],
            'show_marketing' => 'true' === $atts['show_marketing'],
            'compact'        => 'true' === $atts['compact'],
            'errors'         => [],
            'data'           => [],
            'form_action'    => '',
            'nonce_field'    => wp_nonce_field( 'dps_registration_v2', '_dps_registration_v2_nonce', true, false ),
        ] );

        $this->hookBridge->afterRender( $html );

        $this->logger->info( 'Shortcode dps_registration_v2 renderizado (nativo M3).' );
        $this->logger->track( 'registration_v2' );

        return $html;
    }

    protected function enqueueAssets(): void {
        if ( ! defined( 'DPS_BASE_URL' ) || ! defined( 'DPS_BASE_VERSION' ) ) {
            return;
        }

        wp_enqueue_style(
            'dps-design-tokens',
            DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
            [],
            DPS_BASE_VERSION
        );

        wp_enqueue_style(
            'dps-registration-v2',
            DPS_FRONTEND_URL . 'assets/css/registration-v2.css',
            [ 'dps-design-tokens' ],
            DPS_FRONTEND_VERSION
        );

        wp_enqueue_script(
            'dps-registration-v2',
            DPS_FRONTEND_URL . 'assets/js/registration-v2.js',
            [],
            DPS_FRONTEND_VERSION,
            true
        );
    }
}
