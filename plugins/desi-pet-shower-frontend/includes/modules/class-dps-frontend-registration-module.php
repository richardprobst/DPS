<?php
/**
 * Compatibility module for the legacy registration shortcode.
 *
 * Keeps `[dps_registration_form]` available while delegating all rendering and
 * processing to the canonical DPS Signature implementation.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Registration_Module {

    /**
     * Option key for the public registration page.
     */
    private const PAGE_OPTION = 'dps_registration_page_id';

    public function __construct(
        private readonly DPS_Frontend_Logger                 $logger,
        private readonly DPS_Frontend_Registration_V2_Module $registrationV2,
    ) {}

    /**
     * Registers the compatibility shortcode and its asset bridge.
     */
    public function boot(): void {
        remove_shortcode( 'dps_registration_form' );
        add_shortcode( 'dps_registration_form', [ $this, 'renderShortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueueCompatibilityAssets' ] );

        $this->logger->debug( 'Módulo Registration ativado como alias compatível do DPS Signature.' );
    }

    /**
     * Renders the legacy shortcode using the canonical Signature renderer.
     *
     * @param array<string, string>|string $atts    Shortcode attributes.
     * @param string|null                  $content Nested shortcode content.
     * @return string
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
            'dps_registration_form'
        );

        $this->logger->info( 'Shortcode dps_registration_form renderizado via motor canônico DPS Signature.' );
        $this->logger->track( 'registration_signature_alias' );

        return $this->registrationV2->renderShortcode( $atts, $content );
    }

    /**
     * Enqueues the Signature assets when the legacy shortcode is present.
     */
    public function maybeEnqueueCompatibilityAssets(): void {
        if ( ! $this->shouldEnqueue() ) {
            return;
        }

        $this->registrationV2->enqueueCompatibilityAssets();
    }

    /**
     * Checks whether the current request contains the legacy shortcode.
     */
    private function shouldEnqueue(): bool {
        $pageId  = (int) get_option( self::PAGE_OPTION );
        $post    = get_post();
        $content = $post instanceof \WP_Post ? $post->post_content : '';

        return is_page( $pageId ) || has_shortcode( $content, 'dps_registration_form' );
    }
}
