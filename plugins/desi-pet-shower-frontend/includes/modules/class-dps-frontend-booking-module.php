<?php
/**
 * Módulo de Agendamento do Frontend Add-on (Fase 3).
 *
 * Consolida o fluxo de agendamento público.
 * Quando habilitado via feature flag, assume o shortcode [dps_booking_form]
 * delegando renderização para o add-on legado DPS_Booking_Addon (dual-run).
 *
 * Estratégia: intervenção mínima. O legado continua processando agendamento,
 * confirmação e captura de appointment. O módulo apenas:
 *   1. Assume o shortcode para envolver output na surface do frontend add-on.
 *   2. Acrescenta CSS do frontend add-on sobre os assets do legado.
 *
 * Todos os hooks existentes são preservados:
 *   - dps_base_after_save_appointment (consumido por stock, payment, groomers,
 *     calendar, communications, push, services — e pelo próprio booking)
 *   - dps_base_appointment_fields
 *   - dps_base_appointment_assignment_fields
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 * @since   1.2.0 Fase 3 — módulo operacional com dual-run.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Booking_Module {

    /**
     * Option key da página de agendamento (legado).
     */
    private const PAGE_OPTION = 'dps_booking_page_id';

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
     * - Mantém todo o processamento (formulário, confirmação, captura) no legado
     */
    public function boot(): void {
        $this->legacyAvailable = class_exists( 'DPS_Booking_Addon' );

        if ( ! $this->legacyAvailable ) {
            $this->logger->warning( 'Módulo Booking ativado mas add-on legado não encontrado.' );
            return;
        }

        // Assume o shortcode: remove do legado e registra o nosso wrapper
        remove_shortcode( 'dps_booking_form' );
        add_shortcode( 'dps_booking_form', [ $this, 'renderShortcode' ] );

        // Enfileira CSS extra do frontend add-on (sobre os assets do legado)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueueFrontendCss' ] );

        $this->logger->debug( 'Módulo Booking ativado (dual-run com legado).' );
    }

    /**
     * Renderiza o shortcode [dps_booking_form].
     *
     * Delega para o legado e envolve o output na surface do frontend add-on
     * para aplicar estilos M3 Expressive adicionais.
     *
     * @param array|string $atts    Atributos do shortcode.
     * @param string|null  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    public function renderShortcode( array|string $atts = [], ?string $content = null ): string {
        if ( ! $this->legacyAvailable ) {
            return '';
        }

        if ( ! method_exists( DPS_Booking_Addon::class, 'get_instance' ) ) {
            $this->logger->error( 'Método get_instance não encontrado no legado Booking.' );
            return '';
        }

        $legacy = DPS_Booking_Addon::get_instance();

        if ( ! method_exists( $legacy, 'render_booking_form' ) ) {
            $this->logger->error( 'Método render_booking_form não encontrado no legado.' );
            return '';
        }

        $html = $legacy->render_booking_form();

        $this->logger->info( 'Shortcode dps_booking_form renderizado via módulo Frontend.' );
        $this->logger->track( 'booking' );

        return '<div class="dps-frontend">' . $html . '</div>';
    }

    /**
     * Enfileira CSS do frontend add-on sobre os assets do legado.
     *
     * O legado enfileira dps-design-tokens + booking-addon.css + assets do base
     * no seu próprio enqueue_assets (que continua ativo). Este método apenas
     * adiciona o CSS do frontend add-on como camada extra.
     */
    public function enqueueFrontendCss(): void {
        if ( ! $this->shouldEnqueue() ) {
            return;
        }

        // O legado já enfileira dps-design-tokens e booking-addon.css
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
        $page_id = get_option( self::PAGE_OPTION );
        $post    = get_post();
        $content = $post instanceof \WP_Post ? $post->post_content : '';

        return is_page( $page_id ) || has_shortcode( $content, 'dps_booking_form' );
    }
}
