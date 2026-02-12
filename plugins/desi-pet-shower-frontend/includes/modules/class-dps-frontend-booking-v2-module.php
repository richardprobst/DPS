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

    private ?DPS_Booking_Handler $handler = null;
    private ?DPS_Booking_Confirmation_Service $confirmationService = null;

    public function __construct(
        DPS_Frontend_Logger          $logger,
        DPS_Template_Engine          $templateEngine,
        private readonly DPS_Booking_Hook_Bridge $hookBridge,
    ) {
        parent::__construct( $logger, $templateEngine );
    }

    /**
     * Injects the handler (called from bootstrap after all dependencies are available).
     */
    public function setHandler( DPS_Booking_Handler $handler ): void {
        $this->handler = $handler;
    }

    /**
     * Injects the confirmation service.
     */
    public function setConfirmationService( DPS_Booking_Confirmation_Service $service ): void {
        $this->confirmationService = $service;
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

        // Process POST if this is a form submission
        $result          = null;
        $errors          = [];
        $formData        = [];
        $success         = false;
        $appointmentId   = 0;
        $appointmentData = [];

        if ( $this->isFormSubmission() ) {
            $result = $this->handleSubmission();

            if ( $result['success'] ) {
                $success         = true;
                $appointmentId   = $result['data']['appointment_id'] ?? 0;
                $appointmentData = $result['data'];
            } else {
                $errors   = $result['errors'];
                $formData = $result['data'];
            }
        }

        $currentStep = max( 1, min( 5, absint( $atts['start_step'] ) ) );

        $html = $this->templateEngine->render( 'booking/form-main.php', [
            'engine'           => $this->templateEngine,
            'atts'             => $atts,
            'theme'            => $atts['theme'],
            'show_progress'    => 'true' === $atts['show_progress'],
            'compact'          => 'true' === $atts['compact'],
            'appointment_type' => $atts['appointment_type'],
            'current_step'     => $currentStep,
            'total_steps'      => 5,
            'errors'           => $errors,
            'data'             => $formData,
            'nonce_field'      => wp_nonce_field( 'dps_booking_v2', '_dps_booking_v2_nonce', true, false ),
            'success'          => $success,
            'appointment_id'   => $appointmentId,
            'appointment_data' => $appointmentData,
            'summary'          => [],
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

        wp_localize_script( 'dps-booking-v2', 'dpsBookingV2', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dps_booking_v2' ),
        ] );
    }

    /**
     * Verifica se a requisição atual é uma submissão do formulário V2.
     */
    private function isFormSubmission(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleSubmission()
        return 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' )
            && isset( $_POST['dps_booking_action'] )
            && 'confirm_booking' === $_POST['dps_booking_action'];
    }

    /**
     * Processa a submissão do formulário de agendamento.
     *
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    private function handleSubmission(): array {
        // 1. Verify nonce
        if ( ! DPS_Frontend_Request_Guard::verifyNonce( 'dps_booking_v2', '_dps_booking_v2_nonce' ) ) {
            $this->logger->warning( 'Nonce inválido no formulário Booking V2.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Sessão expirada. Recarregue a página e tente novamente.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        // 2. Check capability
        if ( ! current_user_can( 'manage_options' )
            && ! current_user_can( 'dps_manage_clients' )
            && ! current_user_can( 'dps_manage_pets' )
            && ! current_user_can( 'dps_manage_appointments' )
        ) {
            $this->logger->warning( 'Permissão insuficiente para booking V2.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Você não tem permissão para agendar.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        // 3. Sanitize form data
        $data = $this->sanitizeFormData();

        // 4. Delegate to handler
        if ( null === $this->handler ) {
            $this->logger->error( 'Handler de booking não configurado.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Erro interno. Tente novamente.', 'dps-frontend-addon' ) ],
                'data'    => $data,
            ];
        }

        return $this->handler->process( $data );
    }

    /**
     * Sanitiza dados do formulário a partir do POST.
     *
     * @return array<string, mixed>
     */
    private function sanitizeFormData(): array {
        return [
            'client_id'                  => DPS_Frontend_Request_Guard::integer( 'client_id' ),
            'pet_ids'                    => array_map( 'absint', (array) ( $_POST['pet_ids'] ?? [] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'service_ids'                => array_map( 'absint', (array) ( $_POST['service_ids'] ?? [] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'service_prices'             => array_map( 'floatval', (array) ( $_POST['service_prices'] ?? [] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'appointment_date'           => DPS_Frontend_Request_Guard::text( 'appointment_date' ),
            'appointment_time'           => DPS_Frontend_Request_Guard::text( 'appointment_time' ),
            'appointment_type'           => DPS_Frontend_Request_Guard::text( 'dps_appointment_type' ),
            'appointment_notes'          => sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'total_value'                => (float) ( $_POST['total_value'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'appointment_taxidog'        => DPS_Frontend_Request_Guard::text( 'appointment_taxidog' ),
            'appointment_taxidog_price'  => (float) ( $_POST['appointment_taxidog_price'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'appointment_tosa'           => DPS_Frontend_Request_Guard::text( 'appointment_tosa' ),
            'appointment_tosa_price'     => (float) ( $_POST['appointment_tosa_price'] ?? 30.00 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'appointment_tosa_occurrence' => DPS_Frontend_Request_Guard::integer( 'appointment_tosa_occurrence' ),
            'subscription_id'            => DPS_Frontend_Request_Guard::integer( 'subscription_id' ),
        ];
    }
}
