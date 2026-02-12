<?php
/**
 * Módulo de Agendamento V2 nativo do Frontend Add-on (Fase 7).
 *
 * Implementação 100% nativa do wizard de agendamento com Material 3 Expressive.
 * Completamente independente do add-on legado DPS_Booking_Addon.
 *
 * Shortcode: [dps_booking_v2]
 * Feature flag: booking_v2
 *
 * Coexiste com o shortcode legado [dps_booking_form] (v1 dual-run).
 * Ambos podem estar ativos simultaneamente no mesmo site.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Booking_V2_Module extends DPS_Abstract_Module_V2 {

    public function __construct(
        DPS_Frontend_Logger          $logger,
        DPS_Template_Engine          $templateEngine,
        private readonly DPS_Booking_Hook_Bridge $hookBridge,
    ) {
        parent::__construct( $logger, $templateEngine );
    }

    protected function shortcodeTag(): string {
        return 'dps_booking_v2';
    }

    /**
     * Renderiza o shortcode [dps_booking_v2].
     *
     * @param array<string, string>|string $atts    Atributos do shortcode.
     * @param string|null                  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    public function renderShortcode( array|string $atts = [], ?string $content = null ): string {
        // Skip REST e AJAX (evitar renderização acidental)
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return '';
        }
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            return '';
        }

        // Login obrigatório
        if ( ! is_user_logged_in() ) {
            return $this->templateEngine->render( 'booking/form-login-required.php', [
                'login_url' => wp_login_url( get_permalink() ),
            ] );
        }

        $atts = shortcode_atts( [
            'client_id'        => '',
            'service_id'       => '',
            'start_step'       => '1',
            'show_progress'    => 'true',
            'theme'            => 'light',
            'compact'          => 'false',
            'appointment_type' => 'simple',
            'edit_id'          => '',
        ], $atts, 'dps_booking_v2' );

        $this->hookBridge->beforeRender( $atts );

        $currentStep = max( 1, min( 5, absint( $atts['start_step'] ) ) );

        $html = $this->templateEngine->render( 'booking/form-main.php', [
            'atts'             => $atts,
            'theme'            => $atts['theme'],
            'show_progress'    => 'true' === $atts['show_progress'],
            'compact'          => 'true' === $atts['compact'],
            'appointment_type' => $atts['appointment_type'],
            'current_step'     => $currentStep,
            'total_steps'      => 5,
            'errors'           => [],
            'data'             => [],
            'nonce_field'      => wp_nonce_field( 'dps_booking_v2', '_dps_booking_v2_nonce', true, false ),
        ] );

        $this->hookBridge->stepRender( $currentStep, [ 'atts' => $atts ] );

        $this->logger->info( 'Shortcode dps_booking_v2 renderizado (nativo M3).' );
        $this->logger->track( 'booking_v2' );

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
            'dps-booking-v2',
            DPS_FRONTEND_URL . 'assets/css/booking-v2.css',
            [ 'dps-design-tokens' ],
            DPS_FRONTEND_VERSION
        );

        wp_enqueue_script(
            'dps-booking-v2',
            DPS_FRONTEND_URL . 'assets/js/booking-v2.js',
            [],
            DPS_FRONTEND_VERSION,
            true
        );
    }
}
