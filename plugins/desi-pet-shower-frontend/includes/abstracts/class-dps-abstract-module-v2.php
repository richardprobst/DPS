<?php
/**
 * Classe base abstrata para módulos nativos V2 (Fase 7).
 *
 * Define o contrato mínimo que módulos nativos (Registration V2, Booking V2)
 * devem implementar. Fornece boot padronizado com feature flag check,
 * registro de shortcode e enqueue de assets.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class DPS_Abstract_Module_V2 {

    public function __construct(
        protected readonly DPS_Frontend_Logger  $logger,
        protected readonly DPS_Template_Engine  $templateEngine,
    ) {}

    /**
     * Inicializa o módulo: registra shortcode e assets.
     */
    public function boot(): void {
        add_shortcode( $this->shortcodeTag(), [ $this, 'renderShortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueueAssets' ] );
        $this->logger->debug( sprintf( 'Módulo V2 "%s" ativado.', static::class ) );
    }

    /**
     * Enfileira assets apenas quando o shortcode está presente na página.
     */
    public function maybeEnqueueAssets(): void {
        $post    = get_post();
        $content = $post instanceof \WP_Post ? $post->post_content : '';

        if ( ! has_shortcode( $content, $this->shortcodeTag() ) ) {
            return;
        }

        $this->enqueueAssets();
    }

    /**
     * Retorna a tag do shortcode (ex.: 'dps_registration_v2').
     */
    abstract protected function shortcodeTag(): string;

    /**
     * Renderiza o shortcode.
     *
     * @param array<string, string>|string $atts    Atributos do shortcode.
     * @param string|null                  $content Conteúdo encapsulado.
     * @return string HTML renderizado.
     */
    abstract public function renderShortcode( array|string $atts = [], ?string $content = null ): string;

    /**
     * Enfileira CSS e JS específicos do módulo.
     */
    abstract protected function enqueueAssets(): void;
}
