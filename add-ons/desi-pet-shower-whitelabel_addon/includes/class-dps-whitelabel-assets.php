<?php
/**
 * Classe de gestão de assets personalizados do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerencia assets personalizados (CSS/JS) do White Label.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Assets {

    /**
     * Construtor da classe.
     */
    public function __construct() {
        // Adiciona estilos customizados no frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_custom_styles' ], 100 );
        
        // Adiciona estilos customizados no admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_custom_styles' ], 100 );
        
        // Adiciona CSS variables no head
        add_action( 'wp_head', [ $this, 'print_css_variables' ], 5 );
        add_action( 'admin_head', [ $this, 'print_css_variables' ], 5 );
    }

    /**
     * Enfileira estilos customizados no frontend.
     */
    public function enqueue_custom_styles() {
        $custom_css = $this->generate_custom_css();
        
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'dps-base-style', $custom_css );
        }
    }

    /**
     * Enfileira estilos customizados no admin.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_custom_styles( $hook ) {
        // Cast para string para compatibilidade com PHP 8.4+
        $hook = (string) $hook;

        // Lista whitelist de hooks DPS
        $allowed_hooks = [
            'toplevel_page_desi-pet-shower',
            'desi-pet-shower_page_dps-agenda',
            'desi-pet-shower_page_dps-finance',
            'desi-pet-shower_page_dps-loyalty',
            'desi-pet-shower_page_dps-whitelabel',
            'desi-pet-shower_page_dps-ai',
            'desi-pet-shower_page_dps-debugging',
        ];
        
        // Permitir filtro para adicionar hooks customizados
        $allowed_hooks = apply_filters( 'dps_whitelabel_admin_hooks', $allowed_hooks );
        
        // Verifica se hook atual está na lista permitida
        $is_dps_page = false;
        foreach ( $allowed_hooks as $allowed_hook ) {
            if ( $hook === $allowed_hook || strpos( $hook, $allowed_hook ) === 0 ) {
                $is_dps_page = true;
                break;
            }
        }
        
        if ( ! $is_dps_page ) {
            return;
        }

        $custom_css = $this->generate_custom_css();
        
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'dps-admin-style', $custom_css );
        }
    }

    /**
     * Imprime CSS variables no head.
     */
    public function print_css_variables() {
        $colors = DPS_WhiteLabel_Branding::get_colors();
        
        echo '<style id="dps-whitelabel-variables">' . "\n";
        echo ':root {' . "\n";
        echo '  --dps-color-primary: ' . esc_attr( $colors['primary'] ) . ';' . "\n";
        echo '  --dps-color-secondary: ' . esc_attr( $colors['secondary'] ) . ';' . "\n";
        echo '  --dps-color-accent: ' . esc_attr( $colors['accent'] ) . ';' . "\n";
        echo '  --dps-color-background: ' . esc_attr( $colors['background'] ) . ';' . "\n";
        echo '  --dps-color-text: ' . esc_attr( $colors['text'] ) . ';' . "\n";
        echo '}' . "\n";
        echo '</style>' . "\n";
    }

    /**
     * Gera CSS customizado baseado nas configurações (com cache).
     *
     * @return string CSS gerado.
     */
    private function generate_custom_css() {
        // Tenta obter do cache
        $cache_key = 'dps_whitelabel_custom_css';
        $cached_css = get_transient( $cache_key );
        
        if ( false !== $cached_css ) {
            return $cached_css;
        }
        
        // Se não há cache, gera CSS
        $settings = DPS_WhiteLabel_Settings::get_settings();
        $css      = '';
        
        // Cores do tema usando as variáveis
        $colors = DPS_WhiteLabel_Branding::get_colors();
        
        // Aplica cores primárias
        if ( ! empty( $colors['primary'] ) ) {
            $css .= ".dps-btn-primary, .dps-button-primary { background-color: {$colors['primary']}; border-color: {$colors['primary']}; }\n";
            $css .= ".dps-link-primary, a.dps-link { color: {$colors['primary']}; }\n";
            $css .= ".dps-nav .dps-nav-item.active { border-color: {$colors['primary']}; }\n";
        }
        
        // Aplica cores secundárias
        if ( ! empty( $colors['secondary'] ) ) {
            $css .= ".dps-btn-secondary { background-color: {$colors['secondary']}; border-color: {$colors['secondary']}; }\n";
            $css .= ".dps-alert-success { border-left-color: {$colors['secondary']}; }\n";
        }
        
        // Aplica cor de destaque
        if ( ! empty( $colors['accent'] ) ) {
            $css .= ".dps-alert-warning { border-left-color: {$colors['accent']}; }\n";
            $css .= ".dps-badge-accent { background-color: {$colors['accent']}; }\n";
        }
        
        // CSS customizado do usuário
        $custom_css = $settings['custom_css'] ?? '';
        if ( ! empty( $custom_css ) ) {
            $css .= "\n/* Custom CSS */\n" . $custom_css . "\n";
        }
        
        // Armazena no cache por 24 horas
        set_transient( $cache_key, $css, DAY_IN_SECONDS );
        
        return $css;
    }

    /**
     * Invalida cache de CSS ao salvar configurações.
     */
    public static function invalidate_css_cache() {
        delete_transient( 'dps_whitelabel_custom_css' );
    }

    /**
     * Gera arquivo CSS customizado.
     *
     * @return string|WP_Error Caminho do arquivo ou erro.
     */
    public static function generate_css_file() {
        $instance = new self();
        $css      = $instance->generate_custom_css();
        
        if ( empty( $css ) ) {
            return '';
        }
        
        $upload_dir = wp_upload_dir();
        $css_dir    = $upload_dir['basedir'] . '/dps-whitelabel/';
        $css_file   = $css_dir . 'custom-styles.css';
        
        // Cria diretório se não existir
        if ( ! file_exists( $css_dir ) ) {
            wp_mkdir_p( $css_dir );
        }
        
        // Escreve arquivo
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $result = $wp_filesystem->put_contents( $css_file, $css, FS_CHMOD_FILE );
        
        if ( false === $result ) {
            return new WP_Error( 'write_failed', __( 'Não foi possível escrever o arquivo CSS.', 'dps-whitelabel-addon' ) );
        }
        
        return $css_file;
    }

    /**
     * Retorna a URL do arquivo CSS customizado.
     *
     * @return string URL do arquivo ou vazio.
     */
    public static function get_css_file_url() {
        $upload_dir = wp_upload_dir();
        $css_file   = $upload_dir['basedir'] . '/dps-whitelabel/custom-styles.css';
        
        if ( file_exists( $css_file ) ) {
            return $upload_dir['baseurl'] . '/dps-whitelabel/custom-styles.css';
        }
        
        return '';
    }
}
