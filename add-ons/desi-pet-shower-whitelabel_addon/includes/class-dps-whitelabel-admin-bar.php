<?php
/**
 * Classe de personalização da Admin Bar do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Personaliza a Admin Bar do WordPress.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Admin_Bar {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_admin_bar';

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
        add_action( 'admin_bar_menu', [ $this, 'customize_admin_bar' ], 999 );
        add_action( 'wp_head', [ $this, 'add_admin_bar_styles' ] );
        add_action( 'admin_head', [ $this, 'add_admin_bar_styles' ] );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'admin_bar_enabled'        => false,
            'hide_wp_logo'             => true,
            'hide_updates_notice'      => false,
            'hide_comments_menu'       => false,
            'hide_new_content_menu'    => false,
            'custom_logo_url'          => '',
            'custom_logo_link'         => '',
            'admin_bar_color'          => '#1d2327',
            'admin_bar_text_color'     => '#ffffff',
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
        if ( ! isset( $_POST['dps_whitelabel_save_admin_bar'] ) ) {
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
            'admin_bar_enabled'        => isset( $_POST['admin_bar_enabled'] ),
            'hide_wp_logo'             => isset( $_POST['hide_wp_logo'] ),
            'hide_updates_notice'      => isset( $_POST['hide_updates_notice'] ),
            'hide_comments_menu'       => isset( $_POST['hide_comments_menu'] ),
            'hide_new_content_menu'    => isset( $_POST['hide_new_content_menu'] ),
            'custom_logo_url'          => esc_url_raw( wp_unslash( $_POST['custom_logo_url'] ?? '' ) ),
            'custom_logo_link'         => esc_url_raw( wp_unslash( $_POST['custom_logo_link'] ?? '' ) ),
            'admin_bar_color'          => sanitize_hex_color( wp_unslash( $_POST['admin_bar_color'] ?? '' ) ) ?: '#1d2327',
            'admin_bar_text_color'     => sanitize_hex_color( wp_unslash( $_POST['admin_bar_text_color'] ?? '' ) ) ?: '#ffffff',
        ];

        update_option( self::OPTION_NAME, $new_settings );
        
        // Limpa cache de settings
        self::clear_cache();

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações da Admin Bar salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Customiza a admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Instância da admin bar.
     */
    public function customize_admin_bar( $wp_admin_bar ) {
        $admin_bar = self::get_settings();
        
        if ( empty( $admin_bar['admin_bar_enabled'] ) ) {
            return;
        }
        
        do_action( 'dps_whitelabel_admin_bar_before', $wp_admin_bar );
        
        // Remover logo do WordPress
        if ( ! empty( $admin_bar['hide_wp_logo'] ) ) {
            $wp_admin_bar->remove_node( 'wp-logo' );
        }
        
        // Remover avisos de atualização
        if ( ! empty( $admin_bar['hide_updates_notice'] ) ) {
            $wp_admin_bar->remove_node( 'updates' );
        }
        
        // Remover menu de comentários
        if ( ! empty( $admin_bar['hide_comments_menu'] ) ) {
            $wp_admin_bar->remove_node( 'comments' );
        }
        
        // Remover menu "Novo"
        if ( ! empty( $admin_bar['hide_new_content_menu'] ) ) {
            $wp_admin_bar->remove_node( 'new-content' );
        }
        
        // Adicionar logo customizado
        if ( ! empty( $admin_bar['custom_logo_url'] ) ) {
            $brand_name = DPS_WhiteLabel_Branding::get_brand_name();
            $link       = ! empty( $admin_bar['custom_logo_link'] ) ? $admin_bar['custom_logo_link'] : admin_url();
            
            $wp_admin_bar->add_node( [
                'id'    => 'dps-whitelabel-logo',
                'title' => sprintf(
                    '<img src="%s" alt="%s" style="height: 20px; vertical-align: middle; margin-right: 5px; margin-top: -2px;">',
                    esc_url( $admin_bar['custom_logo_url'] ),
                    esc_attr( $brand_name )
                ),
                'href'  => esc_url( $link ),
                'meta'  => [
                    'class' => 'dps-whitelabel-logo-node',
                ],
            ] );
        }
        
        do_action( 'dps_whitelabel_admin_bar_after', $wp_admin_bar );
    }

    /**
     * Adiciona estilos customizados para a admin bar.
     */
    public function add_admin_bar_styles() {
        if ( ! is_admin_bar_showing() ) {
            return;
        }
        
        $admin_bar = self::get_settings();
        
        if ( empty( $admin_bar['admin_bar_enabled'] ) ) {
            return;
        }
        
        $bg_color   = $admin_bar['admin_bar_color'] ?? '#1d2327';
        $text_color = $admin_bar['admin_bar_text_color'] ?? '#ffffff';
        
        ?>
        <style id="dps-whitelabel-admin-bar">
            #wpadminbar {
                background: <?php echo esc_attr( $bg_color ); ?> !important;
            }
            #wpadminbar .ab-item,
            #wpadminbar a.ab-item,
            #wpadminbar > #wp-toolbar span.ab-label,
            #wpadminbar > #wp-toolbar span.noticon {
                color: <?php echo esc_attr( $text_color ); ?> !important;
            }
            #wpadminbar .ab-top-menu > li:hover > .ab-item,
            #wpadminbar .ab-top-menu > li.hover > .ab-item {
                background: rgba(255,255,255,0.1) !important;
            }
            #wpadminbar .dps-whitelabel-logo-node > a {
                padding: 0 10px !important;
            }
        </style>
        <?php
    }
}
