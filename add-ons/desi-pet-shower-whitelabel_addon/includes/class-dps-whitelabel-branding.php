<?php
/**
 * Classe de aplicação de branding do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Aplica o branding personalizado em todo o sistema.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Branding {

    /**
     * Construtor da classe.
     */
    public function __construct() {
        // Filtros de nome da marca
        add_filter( 'dps_brand_name', [ $this, 'filter_brand_name' ] );
        add_filter( 'dps_brand_tagline', [ $this, 'filter_brand_tagline' ] );
        
        // Filtros de logo
        add_filter( 'dps_brand_logo', [ $this, 'filter_brand_logo' ], 10, 2 );
        
        // Filtros de contato
        add_filter( 'dps_team_whatsapp_number', [ $this, 'filter_whatsapp_number' ] );
        add_filter( 'dps_support_email', [ $this, 'filter_support_email' ] );
        add_filter( 'dps_support_url', [ $this, 'filter_support_url' ] );
        
        // Filtros de e-mail
        add_filter( 'wp_mail_from', [ $this, 'filter_email_from' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'filter_email_from_name' ] );
        
        // Footer e powered by
        add_filter( 'dps_footer_text', [ $this, 'filter_footer_text' ] );
        add_filter( 'dps_show_powered_by', [ $this, 'filter_show_powered_by' ] );
        
        // Ocultar links de autor se configurado
        add_filter( 'the_author_posts_link', [ $this, 'maybe_hide_author_link' ] );
        add_filter( 'author_link', [ $this, 'maybe_hide_author_link' ] );
        
        // Adiciona favicon customizado
        add_action( 'wp_head', [ $this, 'add_custom_favicon' ], 1 );
        add_action( 'admin_head', [ $this, 'add_custom_favicon' ], 1 );
        add_action( 'login_head', [ $this, 'add_custom_favicon' ], 1 );
    }

    /**
     * Filtra o nome da marca.
     *
     * @param string $name Nome original.
     * @return string Nome filtrado.
     */
    public function filter_brand_name( $name ) {
        $custom_name = DPS_WhiteLabel_Settings::get( 'brand_name' );
        
        if ( ! empty( $custom_name ) ) {
            return $custom_name;
        }
        
        return $name;
    }

    /**
     * Filtra o slogan/tagline da marca.
     *
     * @param string $tagline Tagline original.
     * @return string Tagline filtrado.
     */
    public function filter_brand_tagline( $tagline ) {
        $custom_tagline = DPS_WhiteLabel_Settings::get( 'brand_tagline' );
        
        if ( ! empty( $custom_tagline ) ) {
            return $custom_tagline;
        }
        
        return $tagline;
    }

    /**
     * Filtra a URL do logo.
     *
     * @param string $logo_url URL original do logo.
     * @param string $context  Contexto (light, dark, admin, frontend).
     * @return string URL filtrada.
     */
    public function filter_brand_logo( $logo_url, $context = 'light' ) {
        if ( 'dark' === $context ) {
            $custom_logo = DPS_WhiteLabel_Settings::get( 'brand_logo_dark_url' );
        } else {
            $custom_logo = DPS_WhiteLabel_Settings::get( 'brand_logo_url' );
        }
        
        if ( ! empty( $custom_logo ) ) {
            return esc_url( $custom_logo );
        }
        
        return $logo_url;
    }

    /**
     * Filtra o número de WhatsApp da equipe.
     *
     * @param string $number Número original.
     * @return string Número filtrado.
     */
    public function filter_whatsapp_number( $number ) {
        $custom_number = DPS_WhiteLabel_Settings::get( 'contact_whatsapp' );
        
        if ( ! empty( $custom_number ) ) {
            return preg_replace( '/[^0-9]/', '', $custom_number );
        }
        
        return $number;
    }

    /**
     * Filtra o e-mail de suporte.
     *
     * @param string $email E-mail original.
     * @return string E-mail filtrado.
     */
    public function filter_support_email( $email ) {
        $custom_email = DPS_WhiteLabel_Settings::get( 'contact_email' );
        
        if ( ! empty( $custom_email ) ) {
            return sanitize_email( $custom_email );
        }
        
        return $email;
    }

    /**
     * Filtra a URL de suporte.
     *
     * @param string $url URL original.
     * @return string URL filtrada.
     */
    public function filter_support_url( $url ) {
        $custom_url = DPS_WhiteLabel_Settings::get( 'support_url' );
        
        if ( ! empty( $custom_url ) ) {
            return esc_url( $custom_url );
        }
        
        return $url;
    }

    /**
     * Filtra o remetente de e-mails.
     *
     * @param string $from_email E-mail original do remetente.
     * @return string E-mail filtrado.
     */
    public function filter_email_from( $from_email ) {
        $custom_email = DPS_WhiteLabel_Settings::get( 'contact_email' );
        
        if ( ! empty( $custom_email ) ) {
            return sanitize_email( $custom_email );
        }
        
        return $from_email;
    }

    /**
     * Filtra o nome do remetente de e-mails.
     *
     * @param string $from_name Nome original do remetente.
     * @return string Nome filtrado.
     */
    public function filter_email_from_name( $from_name ) {
        $custom_name = DPS_WhiteLabel_Settings::get( 'brand_name' );
        
        if ( ! empty( $custom_name ) ) {
            return sanitize_text_field( $custom_name );
        }
        
        return $from_name;
    }

    /**
     * Filtra o texto do footer.
     *
     * @param string $text Texto original.
     * @return string Texto filtrado.
     */
    public function filter_footer_text( $text ) {
        $custom_text = DPS_WhiteLabel_Settings::get( 'custom_footer_text' );
        
        if ( ! empty( $custom_text ) ) {
            return wp_kses_post( $custom_text );
        }
        
        return $text;
    }

    /**
     * Filtra exibição do "Powered by".
     *
     * @param bool $show Se deve exibir.
     * @return bool Se deve exibir.
     */
    public function filter_show_powered_by( $show ) {
        $hide = DPS_WhiteLabel_Settings::get( 'hide_powered_by' );
        
        if ( $hide ) {
            return false;
        }
        
        return $show;
    }

    /**
     * Adiciona favicon customizado.
     */
    public function add_custom_favicon() {
        $favicon_url = DPS_WhiteLabel_Settings::get( 'brand_favicon_url' );
        
        if ( empty( $favicon_url ) ) {
            return;
        }

        // Remove favicon padrão do WordPress
        remove_action( 'wp_head', 'wp_site_icon', 99 );

        printf(
            '<link rel="icon" href="%s" sizes="32x32" />' . "\n",
            esc_url( $favicon_url )
        );
        printf(
            '<link rel="apple-touch-icon" href="%s" />' . "\n",
            esc_url( $favicon_url )
        );
    }

    /**
     * Retorna o nome da marca atual.
     *
     * @return string Nome da marca.
     */
    public static function get_brand_name() {
        $name = DPS_WhiteLabel_Settings::get( 'brand_name' );
        
        if ( ! empty( $name ) ) {
            return $name;
        }
        
        return apply_filters( 'dps_brand_name_default', __( 'DPS by PRObst', 'dps-whitelabel-addon' ) );
    }

    /**
     * Retorna a URL do logo atual.
     *
     * @param string $context Contexto (light, dark).
     * @return string URL do logo ou vazio.
     */
    public static function get_brand_logo( $context = 'light' ) {
        if ( 'dark' === $context ) {
            return DPS_WhiteLabel_Settings::get( 'brand_logo_dark_url' );
        }
        
        return DPS_WhiteLabel_Settings::get( 'brand_logo_url' );
    }

    /**
     * Retorna as cores do tema.
     *
     * @return array Cores configuradas.
     */
    public static function get_colors() {
        return [
            'primary'    => DPS_WhiteLabel_Settings::get( 'color_primary', '#0ea5e9' ),
            'secondary'  => DPS_WhiteLabel_Settings::get( 'color_secondary', '#10b981' ),
            'accent'     => DPS_WhiteLabel_Settings::get( 'color_accent', '#f59e0b' ),
            'background' => DPS_WhiteLabel_Settings::get( 'color_background', '#f9fafb' ),
            'text'       => DPS_WhiteLabel_Settings::get( 'color_text', '#374151' ),
        ];
    }

    /**
     * Oculta links de autor se configurado.
     *
     * @param string $link Link original.
     * @return string Link ou vazio.
     */
    public function maybe_hide_author_link( $link ) {
        $hide = DPS_WhiteLabel_Settings::get( 'hide_author_links' );
        
        if ( $hide ) {
            return '';
        }
        
        return $link;
    }
}
