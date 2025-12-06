<?php
/**
 * Classe de personalização da página de login do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Personaliza a página de login do WordPress.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Login_Page {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_login';

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
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login_styles' ] );
        add_filter( 'login_headerurl', [ $this, 'filter_login_logo_url' ] );
        add_filter( 'login_headertext', [ $this, 'filter_login_logo_text' ] );
        add_action( 'login_footer', [ $this, 'add_login_footer' ] );
        add_filter( 'login_message', [ $this, 'filter_login_message' ] );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'login_enabled'            => false,
            'login_logo_url'           => '',
            'login_logo_width'         => 320,
            'login_logo_height'        => 80,
            'login_background_type'    => 'color',
            'login_background_color'   => '#f9fafb',
            'login_background_image'   => '',
            'login_background_gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #10b981 100%)',
            'login_form_width'         => 320,
            'login_form_background'    => '#ffffff',
            'login_form_border_radius' => 8,
            'login_form_shadow'        => true,
            'login_button_color'       => '#0ea5e9',
            'login_button_text_color'  => '#ffffff',
            'login_custom_css'         => '',
            'login_custom_message'     => '',
            'login_footer_text'        => '',
            'hide_register_link'       => false,
            'hide_lost_password_link'  => false,
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
        if ( ! isset( $_POST['dps_whitelabel_save_login'] ) ) {
            return;
        }

        if ( ! isset( $_POST['dps_whitelabel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'invalid_nonce',
                __( 'Erro de segurança. Por favor, tente novamente.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'no_permission',
                __( 'Você não tem permissão para alterar estas configurações.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        $new_settings = [
            'login_enabled'            => isset( $_POST['login_enabled'] ),
            'login_logo_url'           => esc_url_raw( wp_unslash( $_POST['login_logo_url'] ?? '' ) ),
            'login_logo_width'         => absint( $_POST['login_logo_width'] ?? 320 ),
            'login_logo_height'        => absint( $_POST['login_logo_height'] ?? 80 ),
            'login_background_type'    => sanitize_key( $_POST['login_background_type'] ?? 'color' ),
            'login_background_color'   => sanitize_hex_color( wp_unslash( $_POST['login_background_color'] ?? '' ) ) ?: '#f9fafb',
            'login_background_image'   => esc_url_raw( wp_unslash( $_POST['login_background_image'] ?? '' ) ),
            'login_background_gradient' => sanitize_text_field( wp_unslash( $_POST['login_background_gradient'] ?? '' ) ),
            'login_form_width'         => absint( $_POST['login_form_width'] ?? 320 ),
            'login_form_background'    => sanitize_hex_color( wp_unslash( $_POST['login_form_background'] ?? '' ) ) ?: '#ffffff',
            'login_form_border_radius' => absint( $_POST['login_form_border_radius'] ?? 8 ),
            'login_form_shadow'        => isset( $_POST['login_form_shadow'] ),
            'login_button_color'       => sanitize_hex_color( wp_unslash( $_POST['login_button_color'] ?? '' ) ) ?: '#0ea5e9',
            'login_button_text_color'  => sanitize_hex_color( wp_unslash( $_POST['login_button_text_color'] ?? '' ) ) ?: '#ffffff',
            'login_custom_css'         => DPS_WhiteLabel_Settings::sanitize_custom_css( wp_unslash( $_POST['login_custom_css'] ?? '' ) ),
            'login_custom_message'     => wp_kses_post( wp_unslash( $_POST['login_custom_message'] ?? '' ) ),
            'login_footer_text'        => wp_kses_post( wp_unslash( $_POST['login_footer_text'] ?? '' ) ),
            'hide_register_link'       => isset( $_POST['hide_register_link'] ),
            'hide_lost_password_link'  => isset( $_POST['hide_lost_password_link'] ),
        ];

        update_option( self::OPTION_NAME, $new_settings );
        
        // Limpa cache de settings
        self::clear_cache();

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações da página de login salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Enfileira estilos customizados para o login.
     */
    public function enqueue_login_styles() {
        $login = self::get_settings();
        
        if ( empty( $login['login_enabled'] ) ) {
            return;
        }
        
        $css = $this->generate_login_css( $login );
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is sanitized in generate_login_css()
        echo '<style id="dps-whitelabel-login">' . "\n" . wp_strip_all_tags( $css ) . '</style>' . "\n";
    }

    /**
     * Gera CSS para a página de login.
     *
     * @param array $login Configurações de login.
     * @return string CSS gerado.
     */
    private function generate_login_css( $login ) {
        $css = '';
        
        // Background
        $bg_type = $login['login_background_type'] ?? 'color';
        switch ( $bg_type ) {
            case 'color':
                $css .= 'body.login { background-color: ' . esc_attr( $login['login_background_color'] ?? '#f9fafb' ) . '; }' . "\n";
                break;
            case 'image':
                if ( ! empty( $login['login_background_image'] ) ) {
                    $css .= 'body.login { background-image: url(' . esc_url( $login['login_background_image'] ) . '); background-size: cover; background-position: center; background-attachment: fixed; }' . "\n";
                }
                break;
            case 'gradient':
                if ( ! empty( $login['login_background_gradient'] ) ) {
                    $css .= 'body.login { background: ' . esc_attr( $login['login_background_gradient'] ) . '; }' . "\n";
                }
                break;
        }
        
        // Logo
        if ( ! empty( $login['login_logo_url'] ) ) {
            $width  = absint( $login['login_logo_width'] ?? 320 );
            $height = absint( $login['login_logo_height'] ?? 80 );
            
            $css .= sprintf(
                '#login h1 a, .login h1 a {
                    background-image: url(%s);
                    width: %dpx;
                    height: %dpx;
                    background-size: contain;
                    background-repeat: no-repeat;
                    background-position: center;
                }' . "\n",
                esc_url( $login['login_logo_url'] ),
                $width,
                $height
            );
        }
        
        // Formulário
        $form_width  = absint( $login['login_form_width'] ?? 320 );
        $form_bg     = $login['login_form_background'] ?? '#ffffff';
        $form_radius = absint( $login['login_form_border_radius'] ?? 8 );
        $form_shadow = ! empty( $login['login_form_shadow'] );
        
        $css .= sprintf(
            '#loginform, .login form {
                background: %s;
                border-radius: %dpx;
                max-width: %dpx;
                %s
            }' . "\n",
            esc_attr( $form_bg ),
            $form_radius,
            $form_width,
            $form_shadow ? 'box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);' : ''
        );
        
        // Botão
        $btn_color      = $login['login_button_color'] ?? '#0ea5e9';
        $btn_text_color = $login['login_button_text_color'] ?? '#ffffff';
        
        $css .= sprintf(
            '.login .button-primary {
                background: %s !important;
                border-color: %s !important;
                color: %s !important;
                text-shadow: none !important;
            }
            .login .button-primary:hover,
            .login .button-primary:focus {
                background: %s !important;
                filter: brightness(0.9);
            }' . "\n",
            esc_attr( $btn_color ),
            esc_attr( $btn_color ),
            esc_attr( $btn_text_color ),
            esc_attr( $btn_color )
        );
        
        // Ocultar links
        if ( ! empty( $login['hide_register_link'] ) ) {
            $css .= '#nav a:first-child { display: none; }' . "\n";
        }
        
        if ( ! empty( $login['hide_lost_password_link'] ) ) {
            $css .= '#nav a:last-child { display: none; }' . "\n";
        }
        
        // CSS customizado
        if ( ! empty( $login['login_custom_css'] ) ) {
            $css .= $login['login_custom_css'] . "\n";
        }
        
        return $css;
    }

    /**
     * Altera URL do logo no login.
     *
     * @return string URL do site.
     */
    public function filter_login_logo_url() {
        $login = self::get_settings();
        
        if ( empty( $login['login_enabled'] ) ) {
            return home_url();
        }
        
        $settings = DPS_WhiteLabel_Settings::get_settings();
        return ! empty( $settings['website_url'] ) ? esc_url( $settings['website_url'] ) : home_url();
    }

    /**
     * Altera texto do logo no login.
     *
     * @return string Nome da marca.
     */
    public function filter_login_logo_text() {
        return DPS_WhiteLabel_Branding::get_brand_name();
    }

    /**
     * Adiciona texto customizado no footer do login.
     */
    public function add_login_footer() {
        $login = self::get_settings();
        
        if ( empty( $login['login_enabled'] ) || empty( $login['login_footer_text'] ) ) {
            return;
        }
        
        echo '<div class="dps-login-footer" style="text-align: center; margin-top: 20px; color: #666;">';
        echo wp_kses_post( $login['login_footer_text'] );
        echo '</div>';
    }

    /**
     * Adiciona mensagem customizada acima do formulário.
     *
     * @param string $message Mensagem original.
     * @return string Mensagem filtrada.
     */
    public function filter_login_message( $message ) {
        $login = self::get_settings();
        
        if ( empty( $login['login_enabled'] ) || empty( $login['login_custom_message'] ) ) {
            return $message;
        }
        
        return '<p class="message dps-login-message">' . wp_kses_post( $login['login_custom_message'] ) . '</p>' . $message;
    }
}
