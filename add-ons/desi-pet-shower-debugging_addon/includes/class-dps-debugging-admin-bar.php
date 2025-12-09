<?php
/**
 * Classe para integração com a admin bar do WordPress.
 *
 * @package DPS_Debugging_Addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Debugging_Admin_Bar
 * 
 * Adiciona menu de debug na admin bar do WordPress.
 *
 * @since 1.0.0
 */
class DPS_Debugging_Admin_Bar {

    /**
     * Caminho do arquivo wp-config.php.
     *
     * @since 1.0.0
     * @var string
     */
    private $config_path;

    /**
     * Construtor.
     *
     * @since 1.0.0
     *
     * @param string $config_path Caminho do wp-config.php.
     */
    public function __construct( $config_path ) {
        $this->config_path = $config_path;
    }

    /**
     * Inicializa a integração com admin bar.
     *
     * Registra os hooks necessários para adicionar o menu e estilos.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 999 );
        add_action( 'wp_head', [ $this, 'add_admin_bar_styles' ] );
        add_action( 'admin_head', [ $this, 'add_admin_bar_styles' ] );
    }

    /**
     * Verifica se o usuário pode ver o menu de debug.
     *
     * @since 1.0.0
     *
     * @return bool True se o usuário tem permissão.
     */
    private function can_view() {
        /**
         * Filtra a capability necessária para ver o menu de debug na admin bar.
         *
         * @since 1.0.0
         *
         * @param string $capability Capability padrão.
         */
        $capability = apply_filters( 'dps_debugging_admin_bar_cap', 'manage_options' );

        return current_user_can( $capability );
    }

    /**
     * Adiciona menu à admin bar.
     *
     * Exibe contador de entradas, alerta de erros fatais,
     * status das constantes e links rápidos.
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionado alerta de erros fatais com animação.
     *
     * @param WP_Admin_Bar $wp_admin_bar Objeto da admin bar.
     * @return void
     */
    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! $this->can_view() || ! is_admin_bar_showing() ) {
            return;
        }

        $debug_active = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $log_viewer   = new DPS_Debugging_Log_Viewer();
        $log_exists   = $log_viewer->log_exists();
        
        // Usa cache transient para estatísticas (5 minutos) para evitar overhead
        $stats_cache_key = 'dps_debugging_adminbar_stats';
        $cached_data     = get_transient( $stats_cache_key );
        
        if ( false === $cached_data && $log_exists ) {
            $cached_data = [
                'entry_count' => $log_viewer->get_entry_count(),
                'stats'       => $log_viewer->get_entry_stats(),
            ];
            set_transient( $stats_cache_key, $cached_data, 5 * MINUTE_IN_SECONDS );
        }
        
        $entry_count = $log_exists && isset( $cached_data['entry_count'] ) ? $cached_data['entry_count'] : 0;
        $stats       = $log_exists && isset( $cached_data['stats'] ) ? $cached_data['stats'] : [];
        $fatal_count = isset( $stats['by_type']['fatal'] ) ? $stats['by_type']['fatal'] : 0;
        $fatal_count += isset( $stats['by_type']['parse'] ) ? $stats['by_type']['parse'] : 0;

        // Menu principal
        $title = __( 'Debug', 'dps-debugging-addon' );
        if ( $fatal_count > 0 ) {
            $title .= ' <span class="dps-debug-count dps-debug-fatal-count">' . number_format_i18n( $fatal_count ) . '</span>';
        } elseif ( $entry_count > 0 ) {
            $title .= ' <span class="dps-debug-count">' . number_format_i18n( $entry_count ) . '</span>';
        }

        $menu_class = 'dps-debugging-admin-bar';
        if ( $debug_active ) {
            $menu_class .= ' dps-debug-active';
        }
        if ( $fatal_count > 0 ) {
            $menu_class .= ' dps-debug-has-fatals';
        } elseif ( $entry_count > 0 ) {
            $menu_class .= ' dps-debug-has-entries';
        }

        $wp_admin_bar->add_node( [
            'id'    => 'dps-debugging',
            'title' => $title,
            'href'  => admin_url( 'admin.php?page=dps-debugging' ),
            'meta'  => [
                'class' => $menu_class,
                'title' => __( 'DPS Debugging', 'dps-debugging-addon' ),
            ],
        ] );

        // Alerta de erros fatais
        if ( $fatal_count > 0 ) {
            $wp_admin_bar->add_node( [
                'id'     => 'dps-debugging-fatal-alert',
                'parent' => 'dps-debugging',
                'title'  => '<span class="dps-debug-fatal-alert">⚠️ ' . 
                    sprintf(
                        /* translators: %d: Number of fatal errors */
                        _n( '%d erro fatal detectado!', '%d erros fatais detectados!', $fatal_count, 'dps-debugging-addon' ),
                        $fatal_count
                    ) . '</span>',
                'href'   => admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&type=fatal' ),
                'meta'   => [
                    'class' => 'dps-debugging-fatal-item',
                ],
            ] );
        }

        // Verificação de constante WP_DEBUG_LOG
        if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
            $wp_admin_bar->add_node( [
                'id'     => 'dps-debugging-warning',
                'parent' => 'dps-debugging',
                'title'  => '<span class="dps-debug-warning">' . esc_html__( 'WP_DEBUG_LOG não está ativo!', 'dps-debugging-addon' ) . '</span>',
                'href'   => admin_url( 'admin.php?page=dps-debugging' ),
                'meta'   => [
                    'class' => 'dps-debugging-warning-item',
                ],
            ] );
        }

        // Submenu: Visualizar Log
        $wp_admin_bar->add_node( [
            'id'     => 'dps-debugging-view-log',
            'parent' => 'dps-debugging',
            'title'  => __( 'Visualizar Log', 'dps-debugging-addon' ),
            'href'   => admin_url( 'admin.php?page=dps-debugging&tab=log-viewer' ),
            'meta'   => [
                'title' => __( 'Visualizar arquivo de debug formatado', 'dps-debugging-addon' ),
            ],
        ] );

        // Submenu: Visualizar Log Raw
        $wp_admin_bar->add_node( [
            'id'     => 'dps-debugging-view-raw',
            'parent' => 'dps-debugging',
            'title'  => __( 'Visualizar Log (Raw)', 'dps-debugging-addon' ),
            'href'   => admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&mode=raw' ),
            'meta'   => [
                'title' => __( 'Visualizar arquivo de debug sem formatação', 'dps-debugging-addon' ),
            ],
        ] );

        // Submenu: Limpar Log
        if ( $log_exists ) {
            $wp_admin_bar->add_node( [
                'id'     => 'dps-debugging-purge',
                'parent' => 'dps-debugging',
                'title'  => __( 'Limpar Log', 'dps-debugging-addon' ),
                'href'   => wp_nonce_url( admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&dps_debug_action=purge' ), 'dps_debugging_purge' ),
                'meta'   => [
                    'class'   => 'dps-debugging-purge-link',
                    'title'   => __( 'Limpar arquivo de debug', 'dps-debugging-addon' ),
                    'onclick' => 'return confirm("' . esc_js( __( 'Tem certeza que deseja limpar o arquivo de debug?', 'dps-debugging-addon' ) ) . '");',
                ],
            ] );
        }

        // Submenu: Configurações
        $wp_admin_bar->add_node( [
            'id'     => 'dps-debugging-settings',
            'parent' => 'dps-debugging',
            'title'  => __( 'Configurações', 'dps-debugging-addon' ),
            'href'   => admin_url( 'admin.php?page=dps-debugging&tab=settings' ),
            'meta'   => [
                'title' => __( 'Configurar constantes de debug', 'dps-debugging-addon' ),
            ],
        ] );

        // Submenu: Status das Constantes
        $this->add_constants_status_submenu( $wp_admin_bar );
    }

    /**
     * Adiciona submenu com status das constantes.
     *
     * @param WP_Admin_Bar $wp_admin_bar Objeto da admin bar.
     */
    private function add_constants_status_submenu( $wp_admin_bar ) {
        $constants = [
            'WP_DEBUG'                      => defined( 'WP_DEBUG' ) ? WP_DEBUG : false,
            'WP_DEBUG_LOG'                  => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
            'WP_DEBUG_DISPLAY'              => defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : true,
            'SCRIPT_DEBUG'                  => defined( 'SCRIPT_DEBUG' ) ? SCRIPT_DEBUG : false,
            'SAVEQUERIES'                   => defined( 'SAVEQUERIES' ) ? SAVEQUERIES : false,
            'WP_DISABLE_FATAL_ERROR_HANDLER' => defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ? WP_DISABLE_FATAL_ERROR_HANDLER : false,
        ];

        foreach ( $constants as $const => $value ) {
            $is_active = is_bool( $value ) ? $value : (bool) $value;
            
            // Para WP_DEBUG_DISPLAY, ativo significa false (ocultar erros)
            if ( 'WP_DEBUG_DISPLAY' === $const ) {
                $is_active = ! $value;
            }

            $status_icon  = $is_active ? '✓' : '✗';
            $status_class = $is_active ? 'dps-const-active' : 'dps-const-inactive';

            $title = '<span class="' . $status_class . '">' . $status_icon . '</span> ' . $const;

            $wp_admin_bar->add_node( [
                'id'     => 'dps-debugging-const-' . sanitize_key( $const ),
                'parent' => 'dps-debugging',
                'title'  => $title,
                'href'   => admin_url( 'admin.php?page=dps-debugging&tab=settings' ),
                'meta'   => [
                    'class' => 'dps-debugging-const-item ' . $status_class,
                ],
            ] );
        }
    }

    /**
     * Adiciona estilos para admin bar.
     *
     * Injeta CSS inline para estilização do menu de debug:
     * contadores, alertas, status de constantes.
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionado estilo de animação para alerta de erros fatais.
     *
     * @return void
     */
    public function add_admin_bar_styles() {
        if ( ! $this->can_view() || ! is_admin_bar_showing() ) {
            return;
        }
        ?>
        <style>
            /* Menu principal */
            #wpadminbar .dps-debugging-admin-bar .ab-item {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            /* Contador de entradas */
            #wpadminbar .dps-debug-count {
                display: inline-block;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                font-size: 11px;
                font-weight: 600;
                line-height: 18px;
                text-align: center;
                color: #fff;
                background: #72aee6;
                border-radius: 9px;
            }

            /* Contador de erros fatais */
            #wpadminbar .dps-debug-fatal-count {
                background: #d63638;
                animation: dps-debug-pulse 2s infinite;
            }

            @keyframes dps-debug-pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }

            /* Menu com erros fatais */
            #wpadminbar .dps-debug-has-fatals > .ab-item {
                background: rgba(214, 54, 56, 0.1);
            }

            /* Debug ativo */
            #wpadminbar .dps-debug-active > .ab-item {
                background: rgba(0, 150, 0, 0.1);
            }

            /* Status das constantes */
            #wpadminbar .dps-const-active {
                color: #00a32a;
            }
            #wpadminbar .dps-const-inactive {
                color: #d63638;
            }

            /* Aviso de WP_DEBUG_LOG */
            #wpadminbar .dps-debug-warning {
                color: #dba617;
                font-weight: 600;
            }

            /* Alerta de erros fatais */
            #wpadminbar .dps-debug-fatal-alert {
                color: #d63638;
                font-weight: 600;
            }

            #wpadminbar .dps-debugging-fatal-item .ab-item {
                background: rgba(214, 54, 56, 0.1) !important;
            }

            /* Submenu de purge */
            #wpadminbar .dps-debugging-purge-link .ab-item {
                color: #d63638 !important;
            }
            #wpadminbar .dps-debugging-purge-link:hover .ab-item {
                color: #fff !important;
                background: #d63638 !important;
            }

            /* Mobile */
            @media screen and (max-width: 782px) {
                #wpadminbar li#wp-admin-bar-dps-debugging {
                    display: block;
                }
            }
        </style>
        <?php
    }
}
