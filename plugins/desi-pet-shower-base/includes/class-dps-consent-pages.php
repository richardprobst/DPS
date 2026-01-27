<?php
/**
 * Shortcodes de páginas de consentimento.
 *
 * @package Desi_Pet_Shower
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Consent_Pages {

    const MACHINE_CONSENT_SHORTCODE = 'dps_consent_tosa_maquina';

    /**
     * Renderiza o shortcode de consentimento para tosa na máquina.
     *
     * @param array $atts Atributos do shortcode.
     * @return string
     */
    public static function render_machine_consent( $atts = [] ) {
        if ( self::should_skip_render() ) {
            return '';
        }

        self::enqueue_assets();

        $defaults = [
            'company_name' => get_bloginfo( 'name' ),
            'support_email' => '',
            'support_phone' => '',
        ];

        $attributes = shortcode_atts( $defaults, $atts, self::MACHINE_CONSENT_SHORTCODE );

        $args = [
            'company_name' => sanitize_text_field( $attributes['company_name'] ),
            'support_email' => sanitize_email( $attributes['support_email'] ),
            'support_phone' => sanitize_text_field( $attributes['support_phone'] ),
            'generated_at' => current_time( 'd/m/Y' ),
        ];

        ob_start();
        dps_get_template( 'consent/consentimento-tosa-maquina.php', $args );
        return ob_get_clean();
    }

    /**
     * Verifica se o shortcode deve ser ignorado (ex.: REST API, AJAX).
     *
     * @return bool
     */
    private static function should_skip_render() {
        if ( wp_doing_ajax() ) {
            return true;
        }

        return defined( 'REST_REQUEST' ) && REST_REQUEST;
    }

    /**
     * Enfileira assets específicos das páginas de consentimento.
     */
    private static function enqueue_assets() {
        static $enqueued = false;

        if ( $enqueued ) {
            return;
        }

        $enqueued = true;

        $style_path = 'assets/css/dps-consent.css';
        $version = file_exists( DPS_BASE_DIR . $style_path )
            ? (string) filemtime( DPS_BASE_DIR . $style_path )
            : DPS_BASE_VERSION;

        wp_enqueue_style( 'dps-consent', DPS_BASE_URL . $style_path, [], $version );
    }
}
