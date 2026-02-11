<?php
/**
 * Módulo de Cadastro do Frontend Add-on (Fase 2).
 *
 * Consolida o formulário público de cadastro de clientes e pets.
 * Quando habilitado via feature flag, assume o shortcode [dps_registration_form]
 * delegando renderização para o add-on legado DPS_Registration_Addon (dual-run).
 *
 * Estratégia: intervenção mínima. O legado continua processando formulário,
 * emails, REST, AJAX, settings e cron. O módulo apenas:
 *   1. Assume o shortcode para envolver output na surface do frontend add-on.
 *   2. Acrescenta CSS do frontend add-on sobre os assets do legado.
 *
 * Todos os hooks existentes são preservados:
 *   - dps_registration_after_fields (consumido pelo Loyalty)
 *   - dps_registration_after_client_created (consumido pelo Loyalty)
 *   - dps_registration_spam_check
 *   - dps_registration_agenda_url
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 * @since   1.1.0 Fase 2 — módulo operacional com dual-run.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Registration_Module {

    /**
     * Indica se o legado está disponível.
     */
    private bool $legacyAvailable = false;

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Inicializa o módulo quando habilitado pela feature flag.
     *
     * Estratégia dual-run com intervenção mínima:
     * - Assume o shortcode (envolve output do legado na surface do add-on)
     * - Adiciona CSS do frontend add-on como camada sobre o legado
     * - Mantém todo o processamento (form, email, REST, AJAX) no legado
     */
    public function boot(): void {
        $this->legacyAvailable = class_exists( 'DPS_Registration_Addon' );

        if ( ! $this->legacyAvailable ) {
            $this->logger->warning( 'Módulo Registration ativado mas add-on legado não encontrado.' );
            return;
        }

        // Assume o shortcode: remove do legado e registra o nosso wrapper
        remove_shortcode( 'dps_registration_form' );
        add_shortcode( 'dps_registration_form', [ $this, 'renderShortcode' ] );

        // Enfileira CSS extra do frontend add-on (sobre os assets do legado)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueueFrontendCss' ] );

        $this->logger->info( 'Módulo Registration ativado (dual-run com legado).' );
    }

    /**
     * Renderiza o shortcode [dps_registration_form].
     *
     * Delega para o legado e envolve o output na surface do frontend add-on
     * para aplicar estilos M3 Expressive adicionais.
     *
     * @param array|string $atts  Atributos do shortcode.
     * @param string|null  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    public function renderShortcode( array|string $atts = [], ?string $content = null ): string {
        if ( ! $this->legacyAvailable ) {
            return '';
        }

        $legacy = DPS_Registration_Addon::get_instance();
        $html   = $legacy->render_registration_form();

        $this->logger->info( 'Shortcode dps_registration_form renderizado via módulo Frontend.' );

        return '<div class="dps-frontend">' . $html . '</div>';
    }

    /**
     * Enfileira CSS do frontend add-on sobre os assets do legado.
     *
     * O legado enfileira dps-design-tokens + registration-addon.css + dps-registration.js
     * no seu próprio enqueue_assets (que continua ativo). Este método apenas adiciona
     * o CSS do frontend add-on como camada extra.
     */
    public function enqueueFrontendCss(): void {
        if ( ! $this->shouldEnqueue() ) {
            return;
        }

        // O legado já enfileira dps-design-tokens e registration-addon.css
        // Adiciona frontend-addon.css por cima
        wp_enqueue_style(
            'dps-frontend-addon',
            DPS_FRONTEND_URL . 'assets/css/frontend-addon.css',
            [ 'dps-design-tokens' ],
            DPS_FRONTEND_VERSION
        );
    }

    /**
     * Determina se o CSS deve ser carregado na página atual.
     */
    private function shouldEnqueue(): bool {
        $page_id = get_option( 'dps_registration_page_id' );
        $post    = get_post();
        $content = $post ? (string) $post->post_content : '';

        return is_page( $page_id ) || has_shortcode( $content, 'dps_registration_form' );
    }
}
