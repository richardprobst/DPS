<?php
/**
 * Classe de modo manutenção do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerencia o modo de manutenção do site.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Maintenance {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_maintenance';

    /**
     * Construtor da classe.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'template_redirect', [ $this, 'maybe_show_maintenance' ], 1 );
        add_action( 'admin_bar_menu', [ $this, 'add_maintenance_indicator' ], 100 );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'maintenance_enabled'      => false,
            'maintenance_title'        => __( 'Em Manutenção', 'dps-whitelabel-addon' ),
            'maintenance_message'      => __( 'Estamos realizando atualizações. Voltamos em breve!', 'dps-whitelabel-addon' ),
            'maintenance_logo_url'     => '',
            'maintenance_background'   => '#f9fafb',
            'maintenance_text_color'   => '#374151',
            'maintenance_bypass_roles' => [ 'administrator' ],
            'maintenance_countdown'    => '',
        ];
    }

    /**
     * Obtém configurações atuais.
     *
     * @return array Configurações mescladas com padrões.
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( $saved, self::get_defaults() );
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
        if ( ! isset( $_POST['dps_whitelabel_save_maintenance'] ) ) {
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

        // Sanitizar roles permitidas
        $bypass_roles = [];
        if ( isset( $_POST['maintenance_bypass_roles'] ) && is_array( $_POST['maintenance_bypass_roles'] ) ) {
            foreach ( $_POST['maintenance_bypass_roles'] as $role ) {
                $bypass_roles[] = sanitize_key( $role );
            }
        }
        
        // Garantir que administrator sempre está incluído
        if ( ! in_array( 'administrator', $bypass_roles, true ) ) {
            $bypass_roles[] = 'administrator';
        }

        $new_settings = [
            'maintenance_enabled'      => isset( $_POST['maintenance_enabled'] ),
            'maintenance_title'        => sanitize_text_field( wp_unslash( $_POST['maintenance_title'] ?? '' ) ),
            'maintenance_message'      => wp_kses_post( wp_unslash( $_POST['maintenance_message'] ?? '' ) ),
            'maintenance_logo_url'     => esc_url_raw( wp_unslash( $_POST['maintenance_logo_url'] ?? '' ) ),
            'maintenance_background'   => sanitize_hex_color( wp_unslash( $_POST['maintenance_background'] ?? '' ) ) ?: '#f9fafb',
            'maintenance_text_color'   => sanitize_hex_color( wp_unslash( $_POST['maintenance_text_color'] ?? '' ) ) ?: '#374151',
            'maintenance_bypass_roles' => $bypass_roles,
            'maintenance_countdown'    => sanitize_text_field( wp_unslash( $_POST['maintenance_countdown'] ?? '' ) ),
        ];

        update_option( self::OPTION_NAME, $new_settings );

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações de manutenção salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Exibe página de manutenção se ativo.
     */
    public function maybe_show_maintenance() {
        $settings = self::get_settings();
        
        if ( empty( $settings['maintenance_enabled'] ) ) {
            return;
        }
        
        // Verifica se usuário pode bypassar
        if ( $this->can_user_bypass() ) {
            return;
        }
        
        // Não bloquear wp-admin e wp-login
        if ( is_admin() || ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) ) {
            return;
        }
        
        // Não bloquear AJAX
        if ( wp_doing_ajax() ) {
            return;
        }
        
        // Não bloquear REST API para usuários que podem bypassar
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST && $this->can_user_bypass() ) {
            return;
        }
        
        // Permitir bypass via filtro
        if ( apply_filters( 'dps_whitelabel_maintenance_can_access', false, wp_get_current_user() ) ) {
            return;
        }
        
        // Exibe página de manutenção
        $this->render_maintenance_page();
        exit;
    }

    /**
     * Verifica se o usuário atual pode bypassar a manutenção.
     *
     * @return bool True se pode bypassar.
     */
    private function can_user_bypass() {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        
        $settings     = self::get_settings();
        $bypass_roles = $settings['maintenance_bypass_roles'] ?? [ 'administrator' ];
        $user         = wp_get_current_user();
        
        foreach ( $bypass_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Renderiza a página de manutenção.
     */
    private function render_maintenance_page() {
        $settings   = self::get_settings();
        $brand_name = DPS_WhiteLabel_Branding::get_brand_name();
        
        // HTTP 503 Service Unavailable
        status_header( 503 );
        header( 'Retry-After: 3600' );
        
        // Variáveis para o template
        $title      = $settings['maintenance_title'] ?: __( 'Em Manutenção', 'dps-whitelabel-addon' );
        $message    = $settings['maintenance_message'] ?: __( 'Estamos realizando atualizações. Voltamos em breve!', 'dps-whitelabel-addon' );
        $logo_url   = $settings['maintenance_logo_url'] ?: DPS_WhiteLabel_Branding::get_brand_logo();
        $bg_color   = $settings['maintenance_background'] ?: '#f9fafb';
        $text_color = $settings['maintenance_text_color'] ?: '#374151';
        $countdown  = $settings['maintenance_countdown'];
        
        // Verifica se existe template customizado
        $template_path = apply_filters( 'dps_whitelabel_maintenance_template', DPS_WHITELABEL_DIR . 'templates/maintenance.php' );
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
            return;
        }
        
        // Template padrão inline
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html( $title ); ?> - <?php echo esc_html( $brand_name ); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background-color: <?php echo esc_attr( $bg_color ); ?>;
                    color: <?php echo esc_attr( $text_color ); ?>;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .maintenance-container {
                    text-align: center;
                    max-width: 600px;
                }
                .maintenance-logo {
                    max-width: 200px;
                    height: auto;
                    margin-bottom: 30px;
                }
                .maintenance-title {
                    font-size: 2.5rem;
                    font-weight: 700;
                    margin-bottom: 20px;
                }
                .maintenance-message {
                    font-size: 1.125rem;
                    line-height: 1.6;
                    opacity: 0.8;
                    margin-bottom: 30px;
                }
                .maintenance-countdown {
                    font-size: 1rem;
                    opacity: 0.6;
                }
                @media (max-width: 480px) {
                    .maintenance-title {
                        font-size: 1.75rem;
                    }
                    .maintenance-message {
                        font-size: 1rem;
                    }
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <?php if ( ! empty( $logo_url ) ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" class="maintenance-logo">
                <?php endif; ?>
                
                <h1 class="maintenance-title"><?php echo esc_html( $title ); ?></h1>
                
                <div class="maintenance-message">
                    <?php echo wp_kses_post( $message ); ?>
                </div>
                
                <?php if ( ! empty( $countdown ) ) : ?>
                    <div class="maintenance-countdown" id="countdown">
                        <?php esc_html_e( 'Previsão de retorno:', 'dps-whitelabel-addon' ); ?>
                        <span id="countdown-timer"></span>
                    </div>
                    <script>
                        (function() {
                            var countdownDate = new Date('<?php echo esc_js( $countdown ); ?>').getTime();
                            var timerEl = document.getElementById('countdown-timer');
                            
                            function updateTimer() {
                                var now = new Date().getTime();
                                var distance = countdownDate - now;
                                
                                if (distance < 0) {
                                    timerEl.innerHTML = '<?php echo esc_js( __( 'Em breve!', 'dps-whitelabel-addon' ) ); ?>';
                                    return;
                                }
                                
                                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                
                                var text = '';
                                if (days > 0) text += days + 'd ';
                                if (hours > 0) text += hours + 'h ';
                                text += minutes + 'm';
                                
                                timerEl.innerHTML = text;
                            }
                            
                            updateTimer();
                            setInterval(updateTimer, 60000);
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Adiciona indicador de manutenção na admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Instância da admin bar.
     */
    public function add_maintenance_indicator( $wp_admin_bar ) {
        $settings = self::get_settings();
        
        if ( empty( $settings['maintenance_enabled'] ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $wp_admin_bar->add_node( [
            'id'    => 'dps-maintenance-mode',
            'title' => '<span style="background: #f59e0b; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px;">' . 
                       esc_html__( '⚠ MANUTENÇÃO', 'dps-whitelabel-addon' ) . 
                       '</span>',
            'href'  => admin_url( 'admin.php?page=dps-whitelabel&tab=maintenance' ),
            'meta'  => [
                'title' => __( 'O modo de manutenção está ativo. Clique para configurar.', 'dps-whitelabel-addon' ),
            ],
        ] );
    }

    /**
     * Verifica se o modo manutenção está ativo.
     *
     * @return bool True se ativo.
     */
    public static function is_active() {
        return (bool) self::get( 'maintenance_enabled' );
    }
}
