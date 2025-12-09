<?php
/**
 * Plugin Name:       DPS by PRObst – Debugging
 * Plugin URI:        https://www.probst.pro
 * Description:       Gerenciamento de debug no WordPress. Ative/desative constantes de debug e visualize o arquivo debug.log com busca, filtros, estatísticas e exportação.
 * Version:           1.3.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-debugging-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package DPS_Debugging_Addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do add-on
define( 'DPS_DEBUGGING_VERSION', '1.3.0' );
define( 'DPS_DEBUGGING_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_DEBUGGING_URL', plugin_dir_url( __FILE__ ) );

// Carrega o text domain
add_action( 'init', 'dps_debugging_load_textdomain', 1 );

/**
 * Carrega o text domain do add-on.
 */
function dps_debugging_load_textdomain() {
    load_plugin_textdomain( 'dps-debugging-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Inclui arquivos do add-on
require_once DPS_DEBUGGING_DIR . 'includes/class-dps-debugging-config-transformer.php';
require_once DPS_DEBUGGING_DIR . 'includes/class-dps-debugging-log-viewer.php';
require_once DPS_DEBUGGING_DIR . 'includes/class-dps-debugging-admin-bar.php';

// Inicializa o add-on após verificação do plugin base
add_action( 'init', 'dps_debugging_init', 5 );

/**
 * Inicializa o add-on.
 */
function dps_debugging_init() {
    // Verifica se o plugin base está ativo
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', 'dps_debugging_missing_base_notice' );
        return;
    }

    // Inicializa a classe principal
    DPS_Debugging_Addon::get_instance();
}

/**
 * Exibe aviso se o plugin base não estiver ativo.
 */
function dps_debugging_missing_base_notice() {
    $allowed_tags = [
        'strong' => [],
    ];
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: %1$s: Debugging add-on name, %2$s: Base plugin name */
                    __( 'O add-on %1$s requer o plugin %2$s para funcionar.', 'dps-debugging-addon' ),
                    '<strong>DPS by PRObst – Debugging</strong>',
                    '<strong>DPS by PRObst – Base</strong>'
                ),
                $allowed_tags
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Classe principal do add-on de debugging.
 */
class DPS_Debugging_Addon {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Debugging_Addon|null
     */
    private static $instance = null;

    /**
     * Opções salvas do add-on.
     *
     * @var array
     */
    private $options;

    /**
     * Caminho do arquivo wp-config.php.
     *
     * @var string
     */
    private $config_path;

    /**
     * Constantes de debug suportadas.
     *
     * @var array
     */
    private $debug_constants = [
        'wp_debug',
        'wp_debug_log',
        'wp_debug_display',
        'script_debug',
        'savequeries',
        'wp_disable_fatal_error_handler',
    ];

    /**
     * Labels dos tipos de erro para exibição (fonte única de verdade).
     *
     * Array associativo com chave = tipo interno e valor = label traduzido.
     * Centraliza todos os labels usados em estatísticas, filtros e visualização.
     *
     * @since 1.2.0
     * @var array
     */
    private static $error_type_labels = null;

    /**
     * Recupera os labels dos tipos de erro.
     *
     * Fonte única de verdade para labels usados em estatísticas, filtros e visualização.
     * Tipos disponíveis: fatal, warning, notice, deprecated, parse, wordpress-db, exception, other.
     *
     * @since 1.2.0
     *
     * @return array Array associativo com tipo => label traduzido.
     */
    public static function get_error_type_labels() {
        if ( null === self::$error_type_labels ) {
            self::$error_type_labels = [
                'fatal'        => __( 'Fatal', 'dps-debugging-addon' ),
                'warning'      => __( 'Warning', 'dps-debugging-addon' ),
                'notice'       => __( 'Notice', 'dps-debugging-addon' ),
                'deprecated'   => __( 'Deprecated', 'dps-debugging-addon' ),
                'parse'        => __( 'Parse Error', 'dps-debugging-addon' ),
                'wordpress-db' => __( 'DB Error', 'dps-debugging-addon' ),
                'exception'    => __( 'Exception', 'dps-debugging-addon' ),
                'other'        => __( 'Outros', 'dps-debugging-addon' ),
            ];
        }
        return self::$error_type_labels;
    }

    /**
     * Recupera o label de um tipo de erro específico.
     *
     * @since 1.2.0
     *
     * @param string $type Tipo de erro (ex: 'fatal', 'warning').
     * @return string Label traduzido ou o próprio tipo se não encontrado.
     */
    public static function get_error_type_label( $type ) {
        $labels = self::get_error_type_labels();
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * Recupera a instância única.
     *
     * @since 1.0.0
     *
     * @return DPS_Debugging_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->config_path = $this->get_config_path();
        $this->options     = $this->sync_options_with_config();

        // Hooks de admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'admin_init', [ $this, 'handle_log_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Admin bar
        $admin_bar = new DPS_Debugging_Admin_Bar( $this->config_path );
        $admin_bar->init();

        // Hooks de ativação e desativação
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    /**
     * Sincroniza opções salvas com estado real das constantes.
     * 
     * Isso garante que a interface reflita o estado atual do wp-config.php,
     * mesmo que o arquivo tenha sido modificado externamente.
     *
     * @return array Opções sincronizadas.
     */
    private function sync_options_with_config() {
        $saved_options = get_option( 'dps_debugging_options', $this->get_default_options() );
        $synced_options = [];

        foreach ( $this->debug_constants as $constant ) {
            $const_name = strtoupper( $constant );
            
            if ( defined( $const_name ) ) {
                $value = constant( $const_name );
                
                // Para WP_DEBUG_DISPLAY, a checkbox indica "ocultar erros" (valor false)
                if ( 'wp_debug_display' === $constant ) {
                    $synced_options[ $constant ] = ! $value; // Invertido: checkbox marcada = false
                } else {
                    $synced_options[ $constant ] = (bool) $value;
                }
            } else {
                $synced_options[ $constant ] = isset( $saved_options[ $constant ] ) ? $saved_options[ $constant ] : false;
            }
        }

        return $synced_options;
    }

    /**
     * Retorna opções padrão.
     *
     * @return array
     */
    private function get_default_options() {
        return [
            'wp_debug'                      => false,
            'wp_debug_log'                  => false,
            'wp_debug_display'              => true, // Default true, checkbox desmarca para false
            'script_debug'                  => false,
            'savequeries'                   => false,
            'wp_disable_fatal_error_handler' => false,
        ];
    }

    /**
     * Obtém o caminho do arquivo wp-config.php.
     *
     * @return string
     */
    private function get_config_path() {
        $config_path = ABSPATH . 'wp-config.php';

        if ( ! file_exists( $config_path ) ) {
            // Verifica um nível acima, mas apenas se não for outra instalação
            $parent_config = dirname( ABSPATH ) . '/wp-config.php';
            $parent_settings = dirname( ABSPATH ) . '/wp-settings.php';
            
            if ( file_exists( $parent_config ) && ! file_exists( $parent_settings ) ) {
                $config_path = $parent_config;
            }
        }

        /**
         * Filtra o caminho do arquivo de configuração.
         *
         * @param string $config_path Caminho do wp-config.php
         */
        return apply_filters( 'dps_debugging_config_path', $config_path );
    }

    /**
     * Executa na ativação do add-on.
     */
    public function activate() {
        // Salva opções padrão se não existirem
        if ( ! get_option( 'dps_debugging_options' ) ) {
            add_option( 'dps_debugging_options', $this->get_default_options() );
        }

        // Salva estado atual das constantes para restaurar na desativação
        $this->save_pre_activation_state();
    }

    /**
     * Executa na desativação do add-on.
     */
    public function deactivate() {
        // Remove constantes de debug do wp-config.php
        $this->restore_pre_activation_state();
    }

    /**
     * Salva estado das constantes antes da ativação.
     */
    private function save_pre_activation_state() {
        if ( ! class_exists( 'DPS_Debugging_Config_Transformer' ) ) {
            return;
        }

        $transformer = new DPS_Debugging_Config_Transformer( $this->config_path );
        if ( ! $transformer->is_writable() ) {
            return;
        }

        $restore_state = [];
        foreach ( $this->debug_constants as $constant ) {
            $value = $transformer->get_constant( strtoupper( $constant ) );
            if ( null !== $value ) {
                $restore_state[ $constant ] = $value;
            }
        }

        update_option( 'dps_debugging_restore_state', $restore_state );
    }

    /**
     * Restaura estado das constantes na desativação.
     */
    private function restore_pre_activation_state() {
        if ( ! class_exists( 'DPS_Debugging_Config_Transformer' ) ) {
            return;
        }

        $transformer = new DPS_Debugging_Config_Transformer( $this->config_path );
        if ( ! $transformer->is_writable() ) {
            return;
        }

        $restore_state = get_option( 'dps_debugging_restore_state', [] );

        // Remove constantes que foram adicionadas pelo add-on
        $current_options = get_option( 'dps_debugging_options', [] );
        foreach ( $this->debug_constants as $constant ) {
            // Só remove se não estava no estado original
            if ( ! isset( $restore_state[ $constant ] ) && isset( $current_options[ $constant ] ) && $current_options[ $constant ] ) {
                $transformer->remove_constant( strtoupper( $constant ) );
            }
        }

        // Restaura valores originais
        foreach ( $restore_state as $constant => $value ) {
            $transformer->update_constant( strtoupper( $constant ), $value );
        }

        delete_option( 'dps_debugging_restore_state' );
    }

    /**
     * Registra menu administrativo.
     * 
     * NOTA: A partir da v1.1.0, este menu está oculto (parent=null) para backward compatibility.
     * Use o novo hub unificado em dps-system-hub para acessar via aba "Debugging".
     */
    public function register_admin_menu() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'Debugging', 'dps-debugging-addon' ),
            __( 'Debugging', 'dps-debugging-addon' ),
            'manage_options',
            'dps-debugging',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Enfileira assets administrativos.
     *
     * Carrega CSS e JS nas páginas do add-on:
     * - Página standalone do Debugging (dps-debugging)
     * - System Hub quando a aba ativa é "debugging"
     *
     * @since 1.0.0
     * @since 1.2.0 Adicionado suporte ao System Hub.
     *
     * @param string $hook Hook da página atual.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Lista de hooks onde os assets podem ser carregados
        $allowed_hooks = [
            'desi-pet-shower_page_dps-debugging',      // Página standalone do Debugging
            'desi-pet-shower_page_dps-system-hub',     // System Hub
        ];

        // Se não é nenhum dos hooks permitidos, retorna
        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        // Se é o System Hub, verifica se a aba ativa é "debugging"
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura para carregamento de assets
        if ( 'desi-pet-shower_page_dps-system-hub' === $hook ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'logs';
            if ( 'debugging' !== $current_tab ) {
                return;
            }
        }

        wp_enqueue_style(
            'dps-debugging-admin',
            DPS_DEBUGGING_URL . 'assets/css/debugging-admin.css',
            [],
            DPS_DEBUGGING_VERSION
        );

        wp_enqueue_script(
            'dps-debugging-admin',
            DPS_DEBUGGING_URL . 'assets/js/debugging-admin.js',
            [ 'jquery' ],
            DPS_DEBUGGING_VERSION,
            true
        );

        wp_localize_script(
            'dps-debugging-admin',
            'dpsDebugging',
            [
                'copySuccess' => __( 'Copiado para a área de transferência!', 'dps-debugging-addon' ),
                'copyError'   => __( 'Erro ao copiar. Selecione manualmente.', 'dps-debugging-addon' ),
                'noResults'   => __( 'Nenhum resultado encontrado para:', 'dps-debugging-addon' ),
                'showAll'     => __( 'Mostrar todos', 'dps-debugging-addon' ),
                'filtered'    => __( 'entradas encontradas', 'dps-debugging-addon' ),
            ]
        );
    }

    /**
     * Processa salvamento das configurações.
     */
    public function handle_settings_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Processa Modo Rápido
        if ( isset( $_POST['dps_quick_mode'] ) ) {
            $this->handle_quick_mode();
            return;
        }

        if ( ! isset( $_POST['dps_debugging_save_settings'] ) ) {
            return;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'dps_debugging_settings' ) ) {
            return;
        }

        // Verifica se wp-config.php é gravável
        $transformer = new DPS_Debugging_Config_Transformer( $this->config_path );
        if ( ! $transformer->is_writable() ) {
            add_settings_error(
                'dps_debugging',
                'config_not_writable',
                __( 'O arquivo wp-config.php não é gravável. Verifique as permissões do arquivo.', 'dps-debugging-addon' ),
                'error'
            );
            return;
        }

        $new_options = [];

        // Sanitiza os valores do POST antes de processar
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitização feita no loop
        $posted_debugging = isset( $_POST['dps_debugging'] ) && is_array( $_POST['dps_debugging'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['dps_debugging'] ) )
            : [];

        foreach ( $this->debug_constants as $constant ) {
            // Verifica se a chave (que é uma constante conhecida) existe e tem valor '1'
            $new_options[ $constant ] = isset( $posted_debugging[ $constant ] ) && '1' === $posted_debugging[ $constant ];
        }

        // Atualiza constantes no wp-config.php
        foreach ( $this->debug_constants as $constant ) {
            $const_name = strtoupper( $constant );
            $enabled    = $new_options[ $constant ];

            if ( $enabled ) {
                // wp_debug_display é especial: quando habilitado, definimos como false
                $value = 'wp_debug_display' === $constant ? 'false' : 'true';
                $transformer->update_constant( $const_name, $value );
            } else {
                // Remove a constante se não está ativa
                // Exceto WP_DEBUG que precisamos manter como false quando não ativo
                if ( 'wp_debug' === $constant ) {
                    $transformer->update_constant( $const_name, 'false' );
                } else {
                    $transformer->remove_constant( $const_name );
                }
            }
        }

        // Salva opções
        update_option( 'dps_debugging_options', $new_options );
        $this->options = $new_options;

        add_settings_error(
            'dps_debugging',
            'settings_saved',
            __( 'Configurações salvas com sucesso.', 'dps-debugging-addon' ),
            'success'
        );
    }

    /**
     * Processa o Modo Rápido de debug.
     *
     * @since 1.3.0
     */
    private function handle_quick_mode() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'dps_debugging_quick_mode' ) ) {
            return;
        }

        $mode = isset( $_POST['dps_quick_mode'] ) ? sanitize_key( $_POST['dps_quick_mode'] ) : '';

        $transformer = new DPS_Debugging_Config_Transformer( $this->config_path );
        if ( ! $transformer->is_writable() ) {
            add_settings_error(
                'dps_debugging',
                'config_not_writable',
                __( 'O arquivo wp-config.php não é gravável. Verifique as permissões do arquivo.', 'dps-debugging-addon' ),
                'error'
            );
            return;
        }

        if ( 'enable' === $mode ) {
            // Ativa debug completo: WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false
            $transformer->update_constant( 'WP_DEBUG', 'true' );
            $transformer->update_constant( 'WP_DEBUG_LOG', 'true' );
            $transformer->update_constant( 'WP_DEBUG_DISPLAY', 'false' );

            // Atualiza opções salvas
            $this->options['wp_debug']         = true;
            $this->options['wp_debug_log']     = true;
            $this->options['wp_debug_display'] = true; // Invertido: checkbox marcada = display false

            update_option( 'dps_debugging_options', $this->options );

            add_settings_error(
                'dps_debugging',
                'quick_mode_enabled',
                __( 'Modo de debug completo ativado com sucesso.', 'dps-debugging-addon' ),
                'success'
            );
        } elseif ( 'disable' === $mode ) {
            // Desativa debug: WP_DEBUG=false, remove outras constantes
            $transformer->update_constant( 'WP_DEBUG', 'false' );
            $transformer->remove_constant( 'WP_DEBUG_LOG' );
            $transformer->remove_constant( 'WP_DEBUG_DISPLAY' );

            // Atualiza opções salvas
            $this->options['wp_debug']         = false;
            $this->options['wp_debug_log']     = false;
            $this->options['wp_debug_display'] = false;

            update_option( 'dps_debugging_options', $this->options );

            add_settings_error(
                'dps_debugging',
                'quick_mode_disabled',
                __( 'Debug desativado com sucesso.', 'dps-debugging-addon' ),
                'success'
            );
        }
    }

    /**
     * Processa ações do log (visualização, limpeza e exportação).
     */
    public function handle_log_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Limpeza do log
        if ( isset( $_GET['dps_debug_action'] ) && 'purge' === $_GET['dps_debug_action'] ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'dps_debugging_purge' ) ) {
                return;
            }

            $log_viewer = new DPS_Debugging_Log_Viewer();
            $result     = $log_viewer->purge_log();

            // Invalida cache da admin bar
            delete_transient( 'dps_debugging_adminbar_stats' );

            if ( $result ) {
                add_settings_error(
                    'dps_debugging',
                    'log_purged',
                    __( 'Arquivo de debug limpo com sucesso.', 'dps-debugging-addon' ),
                    'success'
                );
            } else {
                add_settings_error(
                    'dps_debugging',
                    'log_purge_error',
                    __( 'Erro ao limpar o arquivo de debug.', 'dps-debugging-addon' ),
                    'error'
                );
            }

            // Redireciona para remover query string
            wp_safe_redirect( admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&purged=1' ) );
            exit;
        }

        // Exportação do log
        if ( isset( $_GET['dps_debug_action'] ) && 'export' === $_GET['dps_debug_action'] ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'dps_debugging_export' ) ) {
                return;
            }

            $log_viewer = new DPS_Debugging_Log_Viewer();
            if ( ! $log_viewer->log_exists() ) {
                return;
            }

            $content  = $log_viewer->get_raw_content();
            $filename = 'debug-log-' . gmdate( 'Y-m-d-H-i-s' ) . '.log';

            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . strlen( $content ) );
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );

            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo raw do log
            exit;
        }
    }

    /**
     * Renderiza a página de configurações.
     *
     * @since 1.0.0
     * @since 1.3.0 Reorganizada em 3 abas: Logs, Configurações, Ferramentas.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-debugging-addon' ) );
        }

        // Obtém a aba atual (padrão: logs)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'logs';

        // Normaliza tabs antigas para novas (backward compatibility)
        if ( 'log-viewer' === $current_tab ) {
            $current_tab = 'logs';
        }
        if ( 'settings' === $current_tab ) {
            $current_tab = 'config';
        }

        // Exibe mensagens
        settings_errors( 'dps_debugging' );

        // Exibe mensagem de sucesso após purge
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['purged'] ) && '1' === $_GET['purged'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Arquivo de debug limpo com sucesso!', 'dps-debugging-addon' ) . '</p></div>';
        }

        ?>
        <div class="wrap dps-debugging-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <nav class="nav-tab-wrapper dps-debugging-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=logs' ) ); ?>" 
                   class="nav-tab <?php echo 'logs' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-editor-alignleft"></span>
                    <?php esc_html_e( 'Logs', 'dps-debugging-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=config' ) ); ?>" 
                   class="nav-tab <?php echo 'config' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e( 'Configurações', 'dps-debugging-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=tools' ) ); ?>" 
                   class="nav-tab <?php echo 'tools' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e( 'Ferramentas', 'dps-debugging-addon' ); ?>
                </a>
            </nav>

            <div class="dps-debugging-content">
                <?php
                switch ( $current_tab ) {
                    case 'config':
                        $this->render_config_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    case 'logs':
                    default:
                        $this->render_logs_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Alias para render_settings_page() para compatibilidade com System Hub.
     *
     * @since 1.1.0
     */
    public function render_admin_page() {
        $this->render_settings_page();
    }

    /**
     * Renderiza a aba de Logs (nova aba principal).
     *
     * @since 1.3.0
     */
    private function render_logs_tab() {
        $log_viewer = new DPS_Debugging_Log_Viewer();
        $log_file   = $log_viewer->get_debug_log_path();
        $log_exists = $log_viewer->log_exists();

        // Parâmetros de filtragem e paginação
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'all';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 100;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $compact = isset( $_GET['compact'] ) && '1' === $_GET['compact'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $view_mode = isset( $_GET['mode'] ) && 'raw' === $_GET['mode'] ? 'raw' : 'formatted';

        // Valida per_page
        if ( ! in_array( $per_page, DPS_Debugging_Log_Viewer::$per_page_options, true ) ) {
            $per_page = 100;
        }

        // Obtém entradas paginadas
        $result = $log_viewer->get_paginated_entries( [
            'filter_type' => $filter_type,
            'period'      => $period,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'page'        => $page,
            'per_page'    => $per_page,
            'compact'     => $compact,
        ] );

        // Estatísticas gerais (sem filtros, para exibir cards)
        $stats = $log_exists ? $log_viewer->get_entry_stats() : [];

        ?>
        <div class="dps-debugging-logs-tab">
            <?php if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'A constante WP_DEBUG_LOG não está ativada. Ative-a na aba Configurações para gerar logs.', 'dps-debugging-addon' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Informações do arquivo e ações rápidas -->
            <div class="dps-debugging-log-header">
                <div class="dps-debugging-log-info">
                    <strong><?php esc_html_e( 'Arquivo:', 'dps-debugging-addon' ); ?></strong>
                    <code><?php echo esc_html( $log_file ); ?></code>
                    <?php if ( $log_exists ) : ?>
                        <span class="dps-debugging-log-size">(<?php echo esc_html( $log_viewer->get_log_size_formatted() ); ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="dps-debugging-log-actions-quick">
                    <?php if ( $log_exists ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=logs&mode=' . ( 'raw' === $view_mode ? 'formatted' : 'raw' ) ) ); ?>" class="button">
                            <?php echo 'raw' === $view_mode ? esc_html__( 'Modo Formatado', 'dps-debugging-addon' ) : esc_html__( 'Modo Raw', 'dps-debugging-addon' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $log_exists && 'formatted' === $view_mode ) : ?>
                <!-- Estatísticas -->
                <?php $this->render_log_stats( $stats ); ?>

                <!-- Filtros -->
                <div class="dps-debugging-log-filters-container">
                    <form method="get" action="" class="dps-debugging-filters-form">
                        <input type="hidden" name="page" value="dps-debugging">
                        <input type="hidden" name="tab" value="logs">

                        <div class="dps-debugging-filters-row">
                            <!-- Filtro por tipo -->
                            <div class="dps-debugging-filter-group">
                                <label for="dps-filter-type"><?php esc_html_e( 'Tipo:', 'dps-debugging-addon' ); ?></label>
                                <select name="type" id="dps-filter-type">
                                    <option value=""><?php esc_html_e( 'Todos os tipos', 'dps-debugging-addon' ); ?></option>
                                    <?php
                                    $all_labels = self::get_error_type_labels();
                                    $filterable_types = array_diff_key( $all_labels, [ 'other' => '', 'exception' => '' ] );
                                    foreach ( $filterable_types as $type => $label ) :
                                        if ( isset( $stats['by_type'][ $type ] ) && $stats['by_type'][ $type ] > 0 ) :
                                            ?>
                                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_type, $type ); ?>>
                                                <?php echo esc_html( $label . ' (' . $stats['by_type'][ $type ] . ')' ); ?>
                                            </option>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </select>
                            </div>

                            <!-- Filtro por período -->
                            <div class="dps-debugging-filter-group">
                                <label for="dps-filter-period"><?php esc_html_e( 'Período:', 'dps-debugging-addon' ); ?></label>
                                <select name="period" id="dps-filter-period" class="dps-period-select">
                                    <?php
                                    $period_labels = DPS_Debugging_Log_Viewer::get_period_labels();
                                    foreach ( $period_labels as $key => $label ) :
                                        ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Campos de data customizada -->
                            <div class="dps-debugging-filter-group dps-custom-date-fields" style="<?php echo 'custom' === $period ? '' : 'display:none;'; ?>">
                                <label for="dps-date-from"><?php esc_html_e( 'De:', 'dps-debugging-addon' ); ?></label>
                                <input type="date" name="date_from" id="dps-date-from" value="<?php echo esc_attr( $date_from ); ?>">
                                <label for="dps-date-to"><?php esc_html_e( 'Até:', 'dps-debugging-addon' ); ?></label>
                                <input type="date" name="date_to" id="dps-date-to" value="<?php echo esc_attr( $date_to ); ?>">
                            </div>

                            <!-- Entradas por página -->
                            <div class="dps-debugging-filter-group">
                                <label for="dps-per-page"><?php esc_html_e( 'Por página:', 'dps-debugging-addon' ); ?></label>
                                <select name="per_page" id="dps-per-page">
                                    <?php foreach ( DPS_Debugging_Log_Viewer::$per_page_options as $option ) : ?>
                                        <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
                                            <?php echo esc_html( $option ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Modo compacto -->
                            <div class="dps-debugging-filter-group dps-compact-toggle">
                                <label>
                                    <input type="checkbox" name="compact" value="1" <?php checked( $compact ); ?>>
                                    <?php esc_html_e( 'Modo compacto', 'dps-debugging-addon' ); ?>
                                </label>
                            </div>

                            <div class="dps-debugging-filter-group">
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'dps-debugging-addon' ); ?></button>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=logs' ) ); ?>" class="button"><?php esc_html_e( 'Limpar', 'dps-debugging-addon' ); ?></a>
                            </div>
                        </div>

                        <!-- Busca -->
                        <div class="dps-debugging-search-row">
                            <input type="text" id="dps-debugging-search" class="dps-debugging-search-input" placeholder="<?php esc_attr_e( 'Buscar no log...', 'dps-debugging-addon' ); ?>">
                            <button type="button" class="button dps-debugging-search-clear" style="display:none;"><?php esc_html_e( 'Limpar', 'dps-debugging-addon' ); ?></button>
                        </div>
                    </form>
                </div>
                <div id="dps-debugging-search-results" class="dps-debugging-search-results" style="display:none;"></div>

                <!-- Paginação superior -->
                <?php $this->render_pagination( $result, $filter_type, $period, $date_from, $date_to, $per_page, $compact ); ?>
            <?php endif; ?>

            <!-- Conteúdo do log -->
            <div class="dps-debugging-log-content">
                <?php if ( ! $log_exists ) : ?>
                    <div class="dps-debugging-log-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e( 'O arquivo de debug não existe ou está vazio.', 'dps-debugging-addon' ); ?></p>
                    </div>
                <?php elseif ( 'raw' === $view_mode ) : ?>
                    <pre class="dps-debugging-log-raw"><?php echo esc_html( $log_viewer->get_raw_content() ); ?></pre>
                <?php else : ?>
                    <?php if ( empty( $result['entries'] ) ) : ?>
                        <div class="dps-debugging-log-empty">
                            <p><?php esc_html_e( 'Nenhuma entrada encontrada para os filtros selecionados.', 'dps-debugging-addon' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="dps-debugging-log-entries <?php echo $compact ? 'compact-mode' : ''; ?>">
                            <?php
                            foreach ( $result['entries'] as $entry ) {
                                if ( $compact ) {
                                    echo $log_viewer->format_entry_compact( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                } else {
                                    echo $this->format_log_entry( $entry, $log_viewer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ( $log_exists && 'formatted' === $view_mode && $result['total_pages'] > 1 ) : ?>
                <!-- Paginação inferior -->
                <?php $this->render_pagination( $result, $filter_type, $period, $date_from, $date_to, $per_page, $compact ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Formata uma entrada de log para exibição.
     *
     * @since 1.3.0
     *
     * @param string                   $entry      Entrada de log.
     * @param DPS_Debugging_Log_Viewer $log_viewer Instância do visualizador.
     * @return string HTML formatado.
     */
    private function format_log_entry( $entry, $log_viewer ) {
        $class = 'dps-debugging-log-entry';

        // Detecta tipo
        $error_types = [
            'fatal'        => 'PHP Fatal error:',
            'warning'      => 'PHP Warning:',
            'notice'       => 'PHP Notice:',
            'deprecated'   => 'PHP Deprecated:',
            'parse'        => 'PHP Parse error:',
            'wordpress-db' => 'WordPress database error',
            'exception'    => 'Uncaught Exception',
        ];

        $type = null;
        foreach ( $error_types as $error_type => $marker ) {
            if ( false !== strpos( $entry, $marker ) ) {
                $type = $error_type;
                break;
            }
        }

        if ( $type ) {
            $class .= ' dps-debugging-log-entry-' . $type;
        }

        // Constrói HTML de forma segura
        $output = '<div class="' . esc_attr( $class ) . '"><div class="dps-debugging-log-entry-content">';

        // Extrai e formata datetime separadamente
        $datetime_html = '';
        $rest_of_entry = $entry;
        if ( preg_match( '/^\[([^\]]+)\](.*)$/s', $entry, $matches ) ) {
            $datetime = $matches[1];
            $rest_of_entry = $matches[2];
            $datetime_html = '<span class="dps-debugging-log-datetime">[' . esc_html( $datetime ) . ']</span> ';
        }

        // Formata tipo de erro separadamente
        $label_html = '';
        foreach ( $error_types as $etype => $marker ) {
            if ( false !== strpos( $rest_of_entry, $marker ) ) {
                $label_html = '<span class="dps-debugging-log-label dps-debugging-log-label-' . esc_attr( $etype ) . '">' . esc_html( rtrim( $marker, ':' ) ) . '</span> ';
                // Remove o marker do resto da entrada
                $rest_of_entry = str_replace( $marker, '', $rest_of_entry );
                break;
            }
        }

        // Escapa o restante do conteúdo e converte quebras de linha
        $content_html = nl2br( esc_html( trim( $rest_of_entry ) ) );

        // Monta o HTML final na ordem correta
        $output .= $datetime_html . $label_html . $content_html;
        $output .= '</div></div>';

        return $output;
    }

    /**
     * Renderiza controles de paginação.
     *
     * @since 1.3.0
     *
     * @param array  $result      Resultado da paginação.
     * @param string $filter_type Filtro de tipo atual.
     * @param string $period      Período atual.
     * @param string $date_from   Data inicial.
     * @param string $date_to     Data final.
     * @param int    $per_page    Entradas por página.
     * @param bool   $compact     Modo compacto.
     */
    private function render_pagination( $result, $filter_type, $period, $date_from, $date_to, $per_page, $compact ) {
        if ( $result['total'] === 0 ) {
            return;
        }

        $base_args = [
            'page'     => 'dps-debugging',
            'tab'      => 'logs',
            'per_page' => $per_page,
        ];

        if ( ! empty( $filter_type ) ) {
            $base_args['type'] = $filter_type;
        }
        if ( 'all' !== $period ) {
            $base_args['period'] = $period;
        }
        if ( ! empty( $date_from ) ) {
            $base_args['date_from'] = $date_from;
        }
        if ( ! empty( $date_to ) ) {
            $base_args['date_to'] = $date_to;
        }
        if ( $compact ) {
            $base_args['compact'] = '1';
        }

        ?>
        <div class="dps-debugging-pagination">
            <span class="dps-debugging-pagination-info">
                <?php
                printf(
                    /* translators: %1$d: from, %2$d: to, %3$d: total */
                    esc_html__( 'Mostrando %1$d–%2$d de %3$d entradas', 'dps-debugging-addon' ),
                    $result['from'],
                    $result['to'],
                    $result['total']
                );
                ?>
            </span>

            <div class="dps-debugging-pagination-nav">
                <?php if ( $result['page'] > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $result['page'] - 1 ] ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        &laquo; <?php esc_html_e( 'Anterior', 'dps-debugging-addon' ); ?>
                    </a>
                <?php else : ?>
                    <span class="button disabled">&laquo; <?php esc_html_e( 'Anterior', 'dps-debugging-addon' ); ?></span>
                <?php endif; ?>

                <span class="dps-debugging-pagination-pages">
                    <?php
                    printf(
                        /* translators: %1$d: current page, %2$d: total pages */
                        esc_html__( 'Página %1$d de %2$d', 'dps-debugging-addon' ),
                        $result['page'],
                        $result['total_pages']
                    );
                    ?>
                </span>

                <?php if ( $result['page'] < $result['total_pages'] ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, [ 'paged' => $result['page'] + 1 ] ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <?php esc_html_e( 'Próximo', 'dps-debugging-addon' ); ?> &raquo;
                    </a>
                <?php else : ?>
                    <span class="button disabled"><?php esc_html_e( 'Próximo', 'dps-debugging-addon' ); ?> &raquo;</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a aba de Configurações (com Modo Rápido e Avançado).
     *
     * @since 1.0.0
     * @since 1.3.0 Reorganizada com Modo Rápido e seção Avançado colapsável.
     */
    private function render_config_tab() {
        $transformer   = new DPS_Debugging_Config_Transformer( $this->config_path );
        $is_writable   = $transformer->is_writable();
        $config_exists = file_exists( $this->config_path );

        // Verifica estado atual das constantes principais
        $debug_on  = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $log_on    = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
        $display_off = defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY;

        ?>
        <div class="dps-debugging-config-tab">
            <?php if ( ! $config_exists ) : ?>
                <div class="notice notice-error inline">
                    <p><?php esc_html_e( 'O arquivo wp-config.php não foi encontrado.', 'dps-debugging-addon' ); ?></p>
                </div>
            <?php elseif ( ! $is_writable ) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'O arquivo wp-config.php não é gravável. As configurações abaixo são somente leitura.', 'dps-debugging-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Modo Rápido -->
            <div class="card dps-debugging-quick-mode">
                <h2>
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e( 'Modo Rápido', 'dps-debugging-addon' ); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Ative ou desative o debug completo com um clique. O modo completo ativa WP_DEBUG, WP_DEBUG_LOG e desativa WP_DEBUG_DISPLAY.', 'dps-debugging-addon' ); ?>
                </p>

                <div class="dps-debugging-quick-status">
                    <?php if ( $debug_on && $log_on && $display_off ) : ?>
                        <span class="dps-status-badge dps-status-active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'Debug Completo Ativo', 'dps-debugging-addon' ); ?>
                        </span>
                    <?php elseif ( $debug_on ) : ?>
                        <span class="dps-status-badge dps-status-partial">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e( 'Debug Parcialmente Ativo', 'dps-debugging-addon' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="dps-status-badge dps-status-inactive">
                            <span class="dashicons dashicons-minus"></span>
                            <?php esc_html_e( 'Debug Desativado', 'dps-debugging-addon' ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ( $is_writable ) : ?>
                    <div class="dps-debugging-quick-buttons">
                        <form method="post" action="" style="display:inline-block;">
                            <?php wp_nonce_field( 'dps_debugging_quick_mode' ); ?>
                            <input type="hidden" name="dps_quick_mode" value="enable">
                            <button type="submit" class="button button-primary button-hero" <?php disabled( $debug_on && $log_on && $display_off ); ?>>
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php esc_html_e( 'Ativar Debug Completo', 'dps-debugging-addon' ); ?>
                            </button>
                        </form>

                        <form method="post" action="" style="display:inline-block;">
                            <?php wp_nonce_field( 'dps_debugging_quick_mode' ); ?>
                            <input type="hidden" name="dps_quick_mode" value="disable">
                            <button type="submit" class="button button-secondary button-hero" <?php disabled( ! $debug_on ); ?>>
                                <span class="dashicons dashicons-no"></span>
                                <?php esc_html_e( 'Desativar Debug', 'dps-debugging-addon' ); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Status Atual -->
            <div class="card">
                <h2><?php esc_html_e( 'Status Atual', 'dps-debugging-addon' ); ?></h2>
                <pre class="dps-debugging-current-constants"><?php
                    foreach ( $this->debug_constants as $constant ) {
                        $const_name = strtoupper( $constant );
                        if ( defined( $const_name ) ) {
                            $value = constant( $const_name );
                            $display_value = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : var_export( $value, true );
                            echo esc_html( "define( '{$const_name}', {$display_value} );" ) . "\n";
                        }
                    }
                ?></pre>
            </div>

            <!-- Configurações Avançadas (colapsável) -->
            <div class="card dps-debugging-advanced-section">
                <h2>
                    <button type="button" class="dps-debugging-toggle-advanced" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <?php esc_html_e( 'Configurações Avançadas', 'dps-debugging-addon' ); ?>
                    </button>
                </h2>
                <p class="description">
                    <?php esc_html_e( 'Configure cada constante de debug individualmente. Clique para expandir.', 'dps-debugging-addon' ); ?>
                </p>

                <div class="dps-debugging-advanced-content" style="display:none;">
                    <form method="post" action="">
                        <?php wp_nonce_field( 'dps_debugging_settings' ); ?>

                        <table class="form-table dps-debugging-constants-table">
                            <tbody>
                                <?php $this->render_constant_row( 'wp_debug', __( 'WP_DEBUG', 'dps-debugging-addon' ), __( 'Ativa o modo de debug do WordPress. Mostra erros e avisos PHP.', 'dps-debugging-addon' ), $is_writable ); ?>
                                <?php $this->render_constant_row( 'wp_debug_log', __( 'WP_DEBUG_LOG', 'dps-debugging-addon' ), __( 'Grava os erros no arquivo wp-content/debug.log em vez de exibi-los.', 'dps-debugging-addon' ), $is_writable ); ?>
                                <?php $this->render_constant_row( 'wp_debug_display', __( 'WP_DEBUG_DISPLAY = false', 'dps-debugging-addon' ), __( 'Desativa a exibição de erros na tela (recomendado em produção quando WP_DEBUG está ativo).', 'dps-debugging-addon' ), $is_writable ); ?>
                                <?php $this->render_constant_row( 'script_debug', __( 'SCRIPT_DEBUG', 'dps-debugging-addon' ), __( 'Força o WordPress a usar versões não-minificadas de scripts e estilos.', 'dps-debugging-addon' ), $is_writable ); ?>
                                <?php $this->render_constant_row( 'savequeries', __( 'SAVEQUERIES', 'dps-debugging-addon' ), __( 'Salva todas as queries do banco de dados para análise. Aumenta uso de memória.', 'dps-debugging-addon' ), $is_writable ); ?>
                                <?php $this->render_constant_row( 'wp_disable_fatal_error_handler', __( 'WP_DISABLE_FATAL_ERROR_HANDLER', 'dps-debugging-addon' ), __( 'Desativa o recovery mode do WordPress (disponível desde WP 5.2).', 'dps-debugging-addon' ), $is_writable ); ?>
                            </tbody>
                        </table>

                        <?php if ( $is_writable ) : ?>
                            <p class="submit">
                                <button type="submit" name="dps_debugging_save_settings" class="button button-primary">
                                    <?php esc_html_e( 'Salvar Configurações', 'dps-debugging-addon' ); ?>
                                </button>
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a aba de Ferramentas.
     *
     * @since 1.3.0
     */
    private function render_tools_tab() {
        $log_viewer = new DPS_Debugging_Log_Viewer();
        $log_exists = $log_viewer->log_exists();

        ?>
        <div class="dps-debugging-tools-tab">
            <!-- Gerenciamento do Log -->
            <div class="card">
                <h2>
                    <span class="dashicons dashicons-editor-alignleft"></span>
                    <?php esc_html_e( 'Gerenciamento do Log', 'dps-debugging-addon' ); ?>
                </h2>

                <div class="dps-debugging-tool-row">
                    <div class="dps-debugging-tool-info">
                        <strong><?php esc_html_e( 'Exportar Log', 'dps-debugging-addon' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Baixe o arquivo de debug para análise offline ou compartilhamento.', 'dps-debugging-addon' ); ?></p>
                    </div>
                    <div class="dps-debugging-tool-action">
                        <?php if ( $log_exists ) : ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dps-debugging&tab=tools&dps_debug_action=export' ), 'dps_debugging_export' ) ); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Exportar Log', 'dps-debugging-addon' ); ?>
                            </a>
                        <?php else : ?>
                            <button class="button" disabled><?php esc_html_e( 'Sem log disponível', 'dps-debugging-addon' ); ?></button>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>

                <div class="dps-debugging-tool-row">
                    <div class="dps-debugging-tool-info">
                        <strong><?php esc_html_e( 'Limpar Log', 'dps-debugging-addon' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Remove todas as entradas do arquivo de debug. Esta ação não pode ser desfeita.', 'dps-debugging-addon' ); ?></p>
                    </div>
                    <div class="dps-debugging-tool-action">
                        <?php if ( $log_exists ) : ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dps-debugging&tab=tools&dps_debug_action=purge' ), 'dps_debugging_purge' ) ); ?>" 
                               class="button button-secondary dps-debugging-purge-btn"
                               onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja limpar o arquivo de debug? Esta ação não pode ser desfeita.', 'dps-debugging-addon' ) ); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e( 'Limpar Log', 'dps-debugging-addon' ); ?>
                            </a>
                        <?php else : ?>
                            <button class="button" disabled><?php esc_html_e( 'Sem log disponível', 'dps-debugging-addon' ); ?></button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( $log_exists ) : ?>
                    <hr>
                    <div class="dps-debugging-tool-row">
                        <div class="dps-debugging-tool-info">
                            <strong><?php esc_html_e( 'Copiar Log', 'dps-debugging-addon' ); ?></strong>
                            <p class="description"><?php esc_html_e( 'Copie o conteúdo do log para a área de transferência.', 'dps-debugging-addon' ); ?></p>
                        </div>
                        <div class="dps-debugging-tool-action">
                            <button type="button" class="button button-secondary dps-debugging-copy-log-tool">
                                <span class="dashicons dashicons-clipboard"></span>
                                <?php esc_html_e( 'Copiar Log', 'dps-debugging-addon' ); ?>
                            </button>
                        </div>
                    </div>
                    <textarea id="dps-log-content-hidden" style="position:absolute;left:-9999px;"><?php echo esc_textarea( $log_viewer->get_raw_content() ); ?></textarea>
                <?php endif; ?>
            </div>

            <!-- Informações do Sistema -->
            <div class="card">
                <h2>
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e( 'Informações do Sistema', 'dps-debugging-addon' ); ?>
                </h2>

                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Versão do WordPress', 'dps-debugging-addon' ); ?></strong></td>
                            <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Versão do PHP', 'dps-debugging-addon' ); ?></strong></td>
                            <td><?php echo esc_html( phpversion() ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Caminho do Log', 'dps-debugging-addon' ); ?></strong></td>
                            <td><code><?php echo esc_html( $log_viewer->get_debug_log_path() ); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Tamanho do Log', 'dps-debugging-addon' ); ?></strong></td>
                            <td><?php echo esc_html( $log_viewer->get_log_size_formatted() ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Limite de Memória PHP', 'dps-debugging-addon' ); ?></strong></td>
                            <td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Limite de Memória WP', 'dps-debugging-addon' ); ?></strong></td>
                            <td><?php echo esc_html( WP_MEMORY_LIMIT ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Aviso de Segurança -->
            <div class="notice notice-info inline dps-debugging-security-notice">
                <p>
                    <strong>⚠️ <?php esc_html_e( 'Aviso de Segurança:', 'dps-debugging-addon' ); ?></strong>
                    <?php esc_html_e( 'O arquivo de debug pode conter informações sensíveis (caminhos, queries SQL, dados de sessão). Revise o conteúdo antes de compartilhar.', 'dps-debugging-addon' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza uma linha de configuração de constante.
     *
     * @param string $constant    Nome da constante (minúsculo).
     * @param string $label       Label para exibição.
     * @param string $description Descrição da constante.
     * @param bool   $is_writable Se o wp-config.php é gravável.
     */
    private function render_constant_row( $constant, $label, $description, $is_writable ) {
        $checked = ! empty( $this->options[ $constant ] );
        ?>
        <tr>
            <th scope="row">
                <label for="dps_debugging_<?php echo esc_attr( $constant ); ?>">
                    <code><?php echo esc_html( $label ); ?></code>
                </label>
            </th>
            <td>
                <input 
                    type="checkbox" 
                    name="dps_debugging[<?php echo esc_attr( $constant ); ?>]" 
                    id="dps_debugging_<?php echo esc_attr( $constant ); ?>" 
                    value="1" 
                    <?php checked( $checked ); ?>
                    <?php disabled( ! $is_writable ); ?>
                >
                <p class="description"><?php echo esc_html( $description ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Renderiza estatísticas de erros no log.
     *
     * @since 1.0.0
     * @since 1.2.0 Usa labels centralizados via get_error_type_labels().
     *
     * @param array $stats Estatísticas de entradas.
     * @return void
     */
    private function render_log_stats( $stats ) {
        if ( empty( $stats ) ) {
            return;
        }

        // Usa labels centralizados (fonte única de verdade)
        $type_labels = self::get_error_type_labels();

        ?>
        <div class="dps-debugging-log-stats">
            <div class="dps-debugging-stat-cards">
                <div class="dps-debugging-stat-card dps-debugging-stat-total">
                    <span class="dps-debugging-stat-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
                    <span class="dps-debugging-stat-label"><?php esc_html_e( 'Total', 'dps-debugging-addon' ); ?></span>
                </div>
                <?php foreach ( $stats['by_type'] as $type => $count ) : ?>
                    <?php if ( $count > 0 ) : ?>
                        <div class="dps-debugging-stat-card dps-debugging-stat-<?php echo esc_attr( $type ); ?>">
                            <span class="dps-debugging-stat-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
                            <span class="dps-debugging-stat-label"><?php echo esc_html( isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type ); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
