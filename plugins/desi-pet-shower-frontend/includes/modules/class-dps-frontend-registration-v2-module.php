<?php
/**
 * Módulo de Cadastro Signature do Frontend Add-on.
 *
 * Implementação canônica do cadastro público alinhada ao padrão DPS Signature.
 *
 * Shortcode principal: [dps_registration_v2]
 * Alias compatível: [dps_registration_form] via módulo de compatibilidade.
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
    private ?DPS_Email_Confirmation_Service $emailConfirmationService = null;

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

    /**
     * Injects the email confirmation service.
     */
    public function setEmailConfirmationService( DPS_Email_Confirmation_Service $service ): void {
        $this->emailConfirmationService = $service;
    }

    public function boot(): void {
        parent::boot();
        add_action( 'init', [ $this, 'maybeHandleEmailConfirmation' ], 7 );
    }

    protected function shortcodeTag(): string {
        return 'dps_registration_v2';
    }

    /**
     * Enqueue helper for compatibility aliases.
     */
    public function enqueueCompatibilityAssets(): void {
        $this->enqueueAssets();
    }

    /**
     * Renderiza o shortcode [dps_registration_v2].
     *
     * @param array<string, string>|string $atts    Atributos do shortcode.
     * @param string|null                  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    public function renderShortcode( array|string $atts = [], ?string $content = null ): string {
        $atts = shortcode_atts(
            [
                'redirect_url'   => '',
                'show_pets'      => 'true',
                'show_marketing' => 'true',
                'theme'          => 'light',
                'compact'        => 'false',
            ],
            $atts,
            'dps_registration_v2'
        );

        $this->hookBridge->beforeRender( $atts );

        $result            = null;
        $errors            = [];
        $fieldErrors       = [];
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
                $errors            = $result['errors'];
                $formData          = $result['data'];
                $fieldErrors       = isset( $formData['field_errors'] ) && is_array( $formData['field_errors'] ) ? $formData['field_errors'] : [];
                $duplicateWarning  = ! empty( $formData['duplicate_warning'] );
                $duplicateClientId = isset( $formData['duplicate_client_id'] ) ? (int) $formData['duplicate_client_id'] : 0;
                unset( $formData['field_errors'] );
            }
        }

        $breedData = $this->breedProvider ? $this->breedProvider->toJson() : [];

        $recaptchaEnabled = $this->recaptchaService && $this->recaptchaService->isEnabled();
        $recaptchaSiteKey = $recaptchaEnabled ? $this->recaptchaService->getSiteKey() : '';

        $emailConfirmationEnabled = $this->emailConfirmationService && $this->emailConfirmationService->isEnabled();
        $registrationNotice       = $this->getRegistrationNotice();
        $googleApiKey             = sanitize_text_field( (string) get_option( 'dps_google_api_key', '' ) );

        $bookingPageId = (int) get_option( 'dps_booking_page_id', 0 );
        $bookingUrl    = $bookingPageId > 0 ? get_permalink( $bookingPageId ) : '';

        $html = $this->templateEngine->render(
            'registration/form-main.php',
            [
                'engine'                     => $this->templateEngine,
                'atts'                       => $atts,
                'theme'                      => $atts['theme'],
                'show_pets'                  => 'true' === $atts['show_pets'],
                'show_marketing'             => 'true' === $atts['show_marketing'],
                'compact'                    => 'true' === $atts['compact'],
                'errors'                     => $errors,
                'field_errors'               => $fieldErrors,
                'data'                       => $formData,
                'form_action'                => '',
                'nonce_field'                => wp_nonce_field( 'dps_registration_v2', '_dps_registration_v2_nonce', true, false ),
                'success'                    => $success,
                'duplicate_warning'          => $duplicateWarning,
                'duplicate_client_id'        => $duplicateClientId,
                'breed_data'                 => $breedData,
                'recaptcha_enabled'          => $recaptchaEnabled,
                'recaptcha_site_key'         => $recaptchaSiteKey,
                'booking_url'                => $bookingUrl ?: '',
                'email_confirmation_enabled' => $emailConfirmationEnabled,
                'registration_notice'        => $registrationNotice,
                'google_api_key'             => $googleApiKey,
                'form_started_at'            => time(),
            ]
        );

        $this->hookBridge->afterRender( $html );

        $this->logger->info( 'Shortcode dps_registration_v2 renderizado (DPS Signature).' );
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
            'dps-signature-forms',
            DPS_BASE_URL . 'assets/css/dps-signature-forms.css',
            [ 'dps-design-tokens' ],
            file_exists( trailingslashit( DPS_BASE_DIR ) . 'assets/css/dps-signature-forms.css' )
                ? filemtime( trailingslashit( DPS_BASE_DIR ) . 'assets/css/dps-signature-forms.css' )
                : DPS_BASE_VERSION
        );

        wp_enqueue_style(
            'dps-registration-v2',
            DPS_FRONTEND_URL . 'assets/css/registration-v2.css',
            [ 'dps-design-tokens', 'dps-signature-forms' ],
            DPS_FRONTEND_VERSION
        );

        wp_enqueue_script(
            'dps-signature-forms',
            DPS_BASE_URL . 'assets/js/dps-signature-forms.js',
            [],
            file_exists( trailingslashit( DPS_BASE_DIR ) . 'assets/js/dps-signature-forms.js' )
                ? filemtime( trailingslashit( DPS_BASE_DIR ) . 'assets/js/dps-signature-forms.js' )
                : DPS_BASE_VERSION,
            true
        );

        wp_enqueue_script(
            'dps-registration-v2',
            DPS_FRONTEND_URL . 'assets/js/registration-v2.js',
            [ 'dps-signature-forms' ],
            DPS_FRONTEND_VERSION,
            true
        );

        wp_localize_script(
            'dps-registration-v2',
            'dpsRegistrationV2',
            [
                'googleMapsApiKey' => sanitize_text_field( (string) get_option( 'dps_google_api_key', '' ) ),
                'i18n'             => [
                    'nameRequired'        => __( 'Informe o nome completo do tutor.', 'dps-frontend-addon' ),
                    'emailRequired'       => __( 'Informe um e-mail válido para o cadastro.', 'dps-frontend-addon' ),
                    'emailInvalid'        => __( 'O e-mail informado não é válido.', 'dps-frontend-addon' ),
                    'phoneRequired'       => __( 'Informe o telefone ou WhatsApp do tutor.', 'dps-frontend-addon' ),
                    'petNameRequired'     => __( 'Informe o nome do pet.', 'dps-frontend-addon' ),
                    'petSpeciesRequired'  => __( 'Selecione a espécie do pet.', 'dps-frontend-addon' ),
                    'addPetLabel'         => __( 'Adicionar Outro Pet', 'dps-frontend-addon' ),
                    'removePetLabel'      => __( 'Remover pet', 'dps-frontend-addon' ),
                    'stepCopied'          => __( 'Etapa selecionada.', 'dps-frontend-addon' ),
                    'saving'              => __( 'Enviando cadastro…', 'dps-frontend-addon' ),
                    'recaptchaUnavailable'=> __( 'Não foi possível validar o anti-spam. Tente novamente.', 'dps-frontend-addon' ),
                ],
            ]
        );

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
        if ( ! DPS_Frontend_Request_Guard::verifyNonce( 'dps_registration_v2', '_dps_registration_v2_nonce' ) ) {
            $this->logger->warning( 'Nonce inválido no formulário Registration V2.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Sessão expirada. Recarregue a página e tente novamente.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        $honeypot = isset( $_POST['dps_website_url'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_website_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( '' !== $honeypot ) {
            $this->logger->warning( 'Honeypot detectou bot.' );
            return [
                'success' => false,
                'errors'  => [ __( 'Cadastro bloqueado por filtro de segurança.', 'dps-frontend-addon' ) ],
                'data'    => [],
            ];
        }

        $data = $this->sanitizeFormData();

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
            'client_name'           => DPS_Frontend_Request_Guard::text( 'client_name' ),
            'client_email'          => sanitize_email( wp_unslash( $_POST['client_email'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'client_phone'          => DPS_Frontend_Request_Guard::text( 'client_phone' ),
            'client_cpf'            => DPS_Frontend_Request_Guard::text( 'client_cpf' ),
            'client_birth'          => DPS_Frontend_Request_Guard::text( 'client_birth' ),
            'client_instagram'      => DPS_Frontend_Request_Guard::text( 'client_instagram' ),
            'client_facebook'       => DPS_Frontend_Request_Guard::text( 'client_facebook' ),
            'client_address'        => sanitize_textarea_field( wp_unslash( $_POST['client_address'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'client_lat'            => DPS_Frontend_Request_Guard::text( 'client_lat' ),
            'client_lng'            => DPS_Frontend_Request_Guard::text( 'client_lng' ),
            'client_referral'       => DPS_Frontend_Request_Guard::text( 'client_referral' ),
            'client_photo_auth'     => DPS_Frontend_Request_Guard::text( 'client_photo_auth' ),
            'dps_referral_code'     => DPS_Frontend_Request_Guard::text( 'dps_referral_code' ),
            'marketing_optin'       => DPS_Frontend_Request_Guard::text( 'marketing_optin' ),
            'recaptcha_token'       => DPS_Frontend_Request_Guard::text( 'recaptcha_token' ),
            'dps_confirm_duplicate' => DPS_Frontend_Request_Guard::text( 'dps_confirm_duplicate' ),
            'form_started_at'       => absint( wp_unslash( $_POST['dps_form_started_at'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ];

        $rawPets = isset( $_POST['pets'] ) && is_array( $_POST['pets'] ) ? $_POST['pets'] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $data['pets'] = [];

        foreach ( $rawPets as $pet ) {
            if ( ! is_array( $pet ) ) {
                continue;
            }

            $data['pets'][] = [
                'pet_name'       => sanitize_text_field( wp_unslash( $pet['pet_name'] ?? '' ) ),
                'pet_species'    => sanitize_text_field( wp_unslash( $pet['pet_species'] ?? '' ) ),
                'pet_breed'      => sanitize_text_field( wp_unslash( $pet['pet_breed'] ?? '' ) ),
                'pet_sex'        => sanitize_text_field( wp_unslash( $pet['pet_sex'] ?? '' ) ),
                'pet_size'       => sanitize_text_field( wp_unslash( $pet['pet_size'] ?? '' ) ),
                'pet_weight'     => sanitize_text_field( wp_unslash( $pet['pet_weight'] ?? '' ) ),
                'pet_birth'      => sanitize_text_field( wp_unslash( $pet['pet_birth'] ?? '' ) ),
                'pet_coat'       => sanitize_text_field( wp_unslash( $pet['pet_coat'] ?? '' ) ),
                'pet_color'      => sanitize_text_field( wp_unslash( $pet['pet_color'] ?? '' ) ),
                'pet_care'       => sanitize_textarea_field( wp_unslash( $pet['pet_care'] ?? '' ) ),
                'pet_aggressive' => sanitize_text_field( wp_unslash( $pet['pet_aggressive'] ?? '' ) ),
                'pet_obs'        => sanitize_textarea_field( wp_unslash( $pet['pet_obs'] ?? '' ) ),
            ];
        }

        return $data;
    }

    /**
     * Processa links de confirmação de e-mail enviados pelo cadastro Signature.
     */
    public function maybeHandleEmailConfirmation(): void {
        if (
            ! isset( $_GET['dps_confirm_email'] )
            || ! isset( $_GET['client_id'] )
            || null === $this->emailConfirmationService
        ) {
            return;
        }

        $clientId = absint( wp_unslash( $_GET['client_id'] ) );
        $token    = sanitize_text_field( wp_unslash( $_GET['dps_confirm_email'] ) );
        $status   = $this->emailConfirmationService->getTokenStatus( $clientId, $token );

        if ( 'valid' === $status ) {
            $this->emailConfirmationService->confirm( $clientId );
        }

        $redirectUrl = $this->getRegistrationLandingUrl();
        wp_safe_redirect(
            add_query_arg(
                'dps_registration_notice',
                'valid' === $status ? 'confirmed' : $status,
                $redirectUrl
            )
        );
        exit;
    }

    /**
     * Retorna notice de confirmação de e-mail a partir da query string.
     *
     * @return array<string, string>|null
     */
    private function getRegistrationNotice(): ?array {
        $notice = isset( $_GET['dps_registration_notice'] ) ? sanitize_key( wp_unslash( $_GET['dps_registration_notice'] ) ) : '';

        return match ( $notice ) {
            'confirmed' => [
                'type'        => 'success',
                'title'       => __( 'E-mail confirmado', 'dps-frontend-addon' ),
                'description' => __( 'Seu cadastro foi ativado com sucesso. Você já pode seguir para o agendamento.', 'dps-frontend-addon' ),
            ],
            'expired' => [
                'type'        => 'warning',
                'title'       => __( 'Link expirado', 'dps-frontend-addon' ),
                'description' => __( 'Esse link de confirmação expirou. Faça um novo cadastro ou fale com a equipe para reativar o acesso.', 'dps-frontend-addon' ),
            ],
            'invalid' => [
                'type'        => 'error',
                'title'       => __( 'Link inválido', 'dps-frontend-addon' ),
                'description' => __( 'Não foi possível validar este link de confirmação.', 'dps-frontend-addon' ),
            ],
            default => null,
        };
    }

    /**
     * Define URL de retorno para links de confirmação.
     */
    private function getRegistrationLandingUrl(): string {
        $pageId = (int) get_option( 'dps_registration_page_id', 0 );
        if ( $pageId > 0 ) {
            $permalink = get_permalink( $pageId );
            if ( is_string( $permalink ) && '' !== $permalink ) {
                return $permalink;
            }
        }

        return home_url( '/' );
    }
}
