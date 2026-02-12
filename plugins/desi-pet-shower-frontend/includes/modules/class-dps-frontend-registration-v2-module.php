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

    private ?DPS_Registration_Handler $handler = null;
    private ?DPS_Breed_Provider $breedProvider = null;
    private ?DPS_Recaptcha_Service $recaptchaService = null;

    public function __construct(
        DPS_Frontend_Logger             $logger,
        DPS_Template_Engine             $templateEngine,
        private readonly DPS_Registration_Hook_Bridge $hookBridge,
    ) {
        parent::__construct( $logger, $templateEngine );
    }

    /**
     * Injects the handler (called from bootstrap after all dependencies are available).
     */
    public function setHandler( DPS_Registration_Handler $handler ): void {
        $this->handler = $handler;
    }

    /**
     * Injects the breed provider.
     */
    public function setBreedProvider( DPS_Breed_Provider $provider ): void {
        $this->breedProvider = $provider;
    }

    /**
     * Injects the reCAPTCHA service.
     */
    public function setRecaptchaService( DPS_Recaptcha_Service $service ): void {
        $this->recaptchaService = $service;
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
            'redirect_url'   => '',
            'show_pets'      => 'true',
            'show_marketing' => 'true',
            'theme'          => 'light',
            'compact'        => 'false',
        ], $atts, 'dps_registration_v2' );

        $this->hookBridge->beforeRender( $atts );

        // Process POST if this is a form submission
        $result            = null;
        $errors            = [];
        $formData          = [];
        $success           = false;
        $duplicateWarning  = false;
        $duplicateClientId = 0;

        if ( $this->isFormSubmission() ) {
            $result = $this->handleSubmission();

            if ( $result['success'] ) {
                $success  = true;
                $formData = $result['data'];
            } else {
                $errors   = $result['errors'];
                $formData = $result['data'];

                // Detect duplicate warning vs hard block
                if ( ! empty( $formData['duplicate_warning'] ) ) {
                    $duplicateWarning  = true;
                    $duplicateClientId = $formData['duplicate_client_id'] ?? 0;
                }
            }
        }

        // Breed data for datalist
        $breedData = $this->breedProvider ? $this->breedProvider->toJson() : [];

        // reCAPTCHA
        $recaptchaEnabled = $this->recaptchaService && $this->recaptchaService->isEnabled();
        $recaptchaSiteKey = $recaptchaEnabled ? $this->recaptchaService->getSiteKey() : '';

        // Booking URL for CTA
        $bookingPageId = (int) get_option( 'dps_booking_page_id', 0 );
        $bookingUrl    = $bookingPageId > 0 ? get_permalink( $bookingPageId ) : '';

        $html = $this->templateEngine->render( 'registration/form-main.php', [
            'engine'               => $this->templateEngine,
            'atts'                 => $atts,
            'theme'                => $atts['theme'],
            'show_pets'            => 'true' === $atts['show_pets'],
            'show_marketing'       => 'true' === $atts['show_marketing'],
            'compact'              => 'true' === $atts['compact'],
            'errors'               => $errors,
            'data'                 => $formData,
            'form_action'          => '',
            'nonce_field'          => wp_nonce_field( 'dps_registration_v2', '_dps_registration_v2_nonce', true, false ),
            'success'              => $success,
            'duplicate_warning'    => $duplicateWarning,
            'duplicate_client_id'  => $duplicateClientId,
            'field_errors'         => [],
            'breed_data'           => $breedData,
            'recaptcha_enabled'    => $recaptchaEnabled,
            'recaptcha_site_key'   => $recaptchaSiteKey,
            'booking_url'          => $bookingUrl ?: '',
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

        // reCAPTCHA v3
        if ( $this->recaptchaService && $this->recaptchaService->isEnabled() ) {
            $siteKey = $this->recaptchaService->getSiteKey();
            if ( '' !== $siteKey ) {
                wp_enqueue_script(
                    'google-recaptcha-v3',
                    'https://www.google.com/recaptcha/api.js?render=' . urlencode( $siteKey ),
                    [],
                    null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external
                    true
                );
            }
        }
    }

    /**
     * Verifica se a requisição atual é uma submissão do formulário V2.
     */
    private function isFormSubmission(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleSubmission()
        return 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' )
            && isset( $_POST['dps_reg_action'] )
            && 'register_v2' === $_POST['dps_reg_action'];
    }

    /**
     * Processa a submissão do formulário.
     *
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    private function handleSubmission(): array {
        // 1. Verify nonce
        if ( ! DPS_Frontend_Request_Guard::verifyNonce( 'dps_registration_v2', '_dps_registration_v2_nonce' ) ) {
            $this->logger->warning( 'Nonce inválido no formulário Registration V2.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Sessão expirada. Recarregue a página e tente novamente.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        // 2. Honeypot check
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $honeypot = isset( $_POST['dps_website_url'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_website_url'] ) ) : '';
        if ( '' !== $honeypot ) {
            $this->logger->warning( 'Honeypot detectou bot.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Cadastro bloqueado por filtro de segurança.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        // 3. Sanitize form data
        $data = $this->sanitizeFormData();

        // 4. Delegate to handler
        if ( null === $this->handler ) {
            $this->logger->error( 'Handler de registro não configurado.' );
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
        $data = [
            'client_name'          => DPS_Frontend_Request_Guard::text( 'client_name' ),
            'client_email'         => sanitize_email( wp_unslash( $_POST['client_email'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'client_phone'         => DPS_Frontend_Request_Guard::text( 'client_phone' ),
            'client_cpf'           => DPS_Frontend_Request_Guard::text( 'client_cpf' ),
            'client_address'       => DPS_Frontend_Request_Guard::text( 'client_address' ),
            'client_lat'           => DPS_Frontend_Request_Guard::text( 'client_lat' ),
            'client_lng'           => DPS_Frontend_Request_Guard::text( 'client_lng' ),
            'dps_referral_code'    => DPS_Frontend_Request_Guard::text( 'dps_referral_code' ),
            'marketing_optin'      => DPS_Frontend_Request_Guard::text( 'marketing_optin' ),
            'recaptcha_token'      => DPS_Frontend_Request_Guard::text( 'recaptcha_token' ),
            'dps_confirm_duplicate' => DPS_Frontend_Request_Guard::text( 'dps_confirm_duplicate' ),
        ];

        // Sanitize pets array
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleSubmission()
        $rawPets = isset( $_POST['pets'] ) && is_array( $_POST['pets'] ) ? $_POST['pets'] : [];
        $data['pets'] = [];

        foreach ( $rawPets as $pet ) {
            if ( ! is_array( $pet ) ) {
                continue;
            }
            $data['pets'][] = [
                'pet_name'    => sanitize_text_field( wp_unslash( $pet['pet_name'] ?? '' ) ),
                'pet_species' => sanitize_text_field( wp_unslash( $pet['pet_species'] ?? '' ) ),
                'pet_breed'   => sanitize_text_field( wp_unslash( $pet['pet_breed'] ?? '' ) ),
                'pet_size'    => sanitize_text_field( wp_unslash( $pet['pet_size'] ?? '' ) ),
                'pet_obs'     => sanitize_textarea_field( wp_unslash( $pet['pet_obs'] ?? '' ) ),
            ];
        }

        return $data;
    }
}
