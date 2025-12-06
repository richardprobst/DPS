<?php
/**
 * Classe de gerenciamento de configurações do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerencia as configurações gerais do White Label.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Settings {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_settings';

    /**
     * Cache estático de settings.
     *
     * @var array|null
     */
    private static $settings_cache = null;

    /**
     * Construtor da classe.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            // Identidade Visual
            'brand_name'           => '',
            'brand_tagline'        => '',
            'brand_logo_url'       => '',
            'brand_logo_dark_url'  => '',
            'brand_favicon_url'    => '',
            
            // Cores do tema
            'color_primary'        => '#0ea5e9',
            'color_secondary'      => '#10b981',
            'color_accent'         => '#f59e0b',
            'color_background'     => '#f9fafb',
            'color_text'           => '#374151',
            
            // Informações de contato
            'contact_email'        => '',
            'contact_phone'        => '',
            'contact_whatsapp'     => '',
            'support_url'          => '',
            
            // URLs personalizadas
            'website_url'          => '',
            'docs_url'             => '',
            'terms_url'            => '',
            'privacy_url'          => '',
            
            // Opções de exibição
            'hide_powered_by'      => false,
            'hide_author_links'    => false,
            'custom_footer_text'   => '',
            'custom_css'           => '',
        ];
    }

    /**
     * Obtém configurações atuais (com cache).
     *
     * @param bool $force_refresh Forçar recarregamento do cache.
     * @return array Configurações mescladas com padrões.
     */
    public static function get_settings( $force_refresh = false ) {
        if ( null === self::$settings_cache || $force_refresh ) {
            $saved = get_option( self::OPTION_NAME, [] );
            self::$settings_cache = wp_parse_args( $saved, self::get_defaults() );
        }
        
        return self::$settings_cache;
    }

    /**
     * Limpa cache de settings.
     */
    public static function clear_cache() {
        self::$settings_cache = null;
    }

    /**
     * Obtém valor de uma configuração específica.
     *
     * @param string $key     Nome da configuração.
     * @param mixed  $default Valor padrão se não existir.
     * @return mixed Valor da configuração.
     */
    public static function get( $key, $default = '' ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Processa salvamento de configurações.
     */
    public function handle_settings_save() {
        // Verifica se é uma requisição de salvamento
        if ( ! isset( $_POST['dps_whitelabel_save_branding'] ) ) {
            return;
        }

        // Verifica nonce
        if ( ! isset( $_POST['dps_whitelabel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'invalid_nonce',
                __( 'Erro de segurança. Por favor, tente novamente.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'no_permission',
                __( 'Você não tem permissão para alterar estas configurações.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        // Sanitiza e salva configurações
        $new_settings = [
            'brand_name'           => sanitize_text_field( wp_unslash( $_POST['brand_name'] ?? '' ) ),
            'brand_tagline'        => sanitize_text_field( wp_unslash( $_POST['brand_tagline'] ?? '' ) ),
            'brand_logo_url'       => esc_url_raw( wp_unslash( $_POST['brand_logo_url'] ?? '' ) ),
            'brand_logo_dark_url'  => esc_url_raw( wp_unslash( $_POST['brand_logo_dark_url'] ?? '' ) ),
            'brand_favicon_url'    => esc_url_raw( wp_unslash( $_POST['brand_favicon_url'] ?? '' ) ),
            
            'color_primary'        => sanitize_hex_color( wp_unslash( $_POST['color_primary'] ?? '' ) ) ?: '#0ea5e9',
            'color_secondary'      => sanitize_hex_color( wp_unslash( $_POST['color_secondary'] ?? '' ) ) ?: '#10b981',
            'color_accent'         => sanitize_hex_color( wp_unslash( $_POST['color_accent'] ?? '' ) ) ?: '#f59e0b',
            'color_background'     => sanitize_hex_color( wp_unslash( $_POST['color_background'] ?? '' ) ) ?: '#f9fafb',
            'color_text'           => sanitize_hex_color( wp_unslash( $_POST['color_text'] ?? '' ) ) ?: '#374151',
            
            'contact_email'        => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
            'contact_phone'        => sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) ),
            'contact_whatsapp'     => sanitize_text_field( wp_unslash( $_POST['contact_whatsapp'] ?? '' ) ),
            'support_url'          => esc_url_raw( wp_unslash( $_POST['support_url'] ?? '' ) ),
            
            'website_url'          => esc_url_raw( wp_unslash( $_POST['website_url'] ?? '' ) ),
            'docs_url'             => esc_url_raw( wp_unslash( $_POST['docs_url'] ?? '' ) ),
            'terms_url'            => esc_url_raw( wp_unslash( $_POST['terms_url'] ?? '' ) ),
            'privacy_url'          => esc_url_raw( wp_unslash( $_POST['privacy_url'] ?? '' ) ),
            
            'hide_powered_by'      => isset( $_POST['hide_powered_by'] ),
            'hide_author_links'    => isset( $_POST['hide_author_links'] ),
            'custom_footer_text'   => sanitize_textarea_field( wp_unslash( $_POST['custom_footer_text'] ?? '' ) ),
            'custom_css'           => self::sanitize_custom_css( wp_unslash( $_POST['custom_css'] ?? '' ) ),
        ];
        
        // Validar URLs de logo
        $logo_fields = [ 'brand_logo_url', 'brand_logo_dark_url', 'brand_favicon_url' ];
        
        foreach ( $logo_fields as $field ) {
            if ( ! empty( $new_settings[ $field ] ) ) {
                if ( ! self::validate_logo_url( $new_settings[ $field ] ) ) {
                    add_settings_error(
                        'dps_whitelabel',
                        'invalid_' . $field,
                        sprintf(
                            /* translators: %s: nome do campo */
                            __( 'URL de %s inválida. Formatos permitidos: JPG, PNG, GIF, SVG, WebP, ICO.', 'dps-whitelabel-addon' ),
                            str_replace( '_', ' ', $field )
                        ),
                        'warning'
                    );
                    // Define como vazio ao invés de salvar URL inválida
                    $new_settings[ $field ] = '';
                }
            }
        }

        // Salva configurações
        update_option( self::OPTION_NAME, $new_settings );
        
        // Limpa cache de settings
        self::clear_cache();

        // Dispara ação para outros módulos
        do_action( 'dps_whitelabel_settings_saved', $new_settings );
        
        // Invalida cache de CSS customizado
        DPS_WhiteLabel_Assets::invalidate_css_cache();

        // Adiciona mensagem de sucesso
        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações de branding salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Sanitiza CSS customizado removendo construções potencialmente perigosas.
     *
     * @param string $css CSS a ser sanitizado.
     * @return string CSS sanitizado.
     */
    public static function sanitize_custom_css( $css ) {
        if ( empty( $css ) ) {
            return '';
        }
        
        // Remove tags HTML primeiro
        $css = wp_strip_all_tags( $css );
        
        // Remove comentários
        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
        
        // Lista de propriedades/valores perigosos
        $dangerous_patterns = [
            '/javascript\s*:/i',
            '/expression\s*\(/i',
            '/behavior\s*:/i',
            '/-moz-binding\s*:/i',
            '/vbscript\s*:/i',
            '/@import/i',
            '/url\s*\(\s*["\']?\s*data:/i', // Bloqueia data URIs
        ];
        
        foreach ( $dangerous_patterns as $pattern ) {
            $css = preg_replace( $pattern, '/* BLOCKED */', $css );
        }
        
        // Validação adicional: remove qualquer octal/hex encoding suspeito em URLs
        $css = preg_replace_callback(
            '/url\s*\([^)]*\)/i',
            function( $matches ) {
                $url = $matches[0];
                // Remove encoding hexadecimal/octal que pode contornar filtros
                if ( preg_match( '/\\\\[0-9a-f]{2,4}/i', $url ) ) {
                    return '/* BLOCKED - encoded chars */';
                }
                return $url;
            },
            $css
        );
        
        // Aplicar filtro para permitir customização
        $css = apply_filters( 'dps_whitelabel_sanitize_custom_css', $css );
        
        return $css;
    }

    /**
     * Valida URL de logo para garantir que é uma imagem válida.
     *
     * @param string $url URL da imagem.
     * @return bool True se válida.
     */
    public static function validate_logo_url( $url ) {
        if ( empty( $url ) ) {
            return true; // Vazio é válido (opcional)
        }

        // Se é um attachment do WordPress, validar via Media Library
        $attachment_id = attachment_url_to_postid( $url );
        if ( $attachment_id ) {
            $mime_type = get_post_mime_type( $attachment_id );
            $allowed   = [ 'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp' ];
            return in_array( $mime_type, $allowed, true );
        }
        
        // Se é URL externa, validar extensão
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['path'] ) ) {
            return false;
        }
        
        $ext = strtolower( pathinfo( $parsed['path'], PATHINFO_EXTENSION ) );
        $allowed_ext = [ 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico' ];
        
        return in_array( $ext, $allowed_ext, true );
    }

    /**
     * Restaura configurações padrão.
     *
     * @return bool True em sucesso.
     */
    public static function reset_to_defaults() {
        return update_option( self::OPTION_NAME, self::get_defaults() );
    }
}
