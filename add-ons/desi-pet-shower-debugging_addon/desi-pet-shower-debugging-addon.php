<?php
/**
 * Plugin Name:       Desi Pet Shower – Debugging
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para gerenciamento de debug no WordPress. Permite ativar/desativar constantes de debug, visualizar e limpar o arquivo debug.log.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
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
define( 'DPS_DEBUGGING_VERSION', '1.0.0' );
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
    new DPS_Debugging_Addon();
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
                    '<strong>Desi Pet Shower – Debugging</strong>',
                    '<strong>Desi Pet Shower – Base</strong>'
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
     * Construtor.
     */
    public function __construct() {
        $this->options     = get_option( 'dps_debugging_options', $this->get_default_options() );
        $this->config_path = $this->get_config_path();

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
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
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
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Carrega apenas nas páginas do add-on
        if ( 'desi-pet-shower_page_dps-debugging' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'dps-debugging-admin',
            DPS_DEBUGGING_URL . 'assets/css/debugging-admin.css',
            [],
            DPS_DEBUGGING_VERSION
        );
    }

    /**
     * Processa salvamento das configurações.
     */
    public function handle_settings_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
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
     * Processa ações do log (visualização e limpeza).
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
    }

    /**
     * Renderiza a página de configurações.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-debugging-addon' ) );
        }

        // Obtém a aba atual
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

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

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=settings' ) ); ?>" 
                   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Configurações', 'dps-debugging-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=log-viewer' ) ); ?>" 
                   class="nav-tab <?php echo 'log-viewer' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Visualizador de Log', 'dps-debugging-addon' ); ?>
                </a>
            </nav>

            <div class="dps-debugging-content">
                <?php
                if ( 'log-viewer' === $current_tab ) {
                    $this->render_log_viewer_tab();
                } else {
                    $this->render_settings_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a aba de configurações.
     */
    private function render_settings_tab() {
        $transformer  = new DPS_Debugging_Config_Transformer( $this->config_path );
        $is_writable  = $transformer->is_writable();
        $config_exists = file_exists( $this->config_path );

        ?>
        <div class="dps-debugging-settings-tab">
            <?php if ( ! $config_exists ) : ?>
                <div class="notice notice-error inline">
                    <p><?php esc_html_e( 'O arquivo wp-config.php não foi encontrado.', 'dps-debugging-addon' ); ?></p>
                </div>
            <?php elseif ( ! $is_writable ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'O arquivo wp-config.php não é gravável. As configurações abaixo são somente leitura.', 'dps-debugging-addon' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php esc_html_e( 'Constantes de Debug', 'dps-debugging-addon' ); ?></h2>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: Link para documentação do WordPress */
                        esc_html__( 'Configure as constantes de debug do WordPress. Consulte a %s para mais informações.', 'dps-debugging-addon' ),
                        '<a href="https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/" target="_blank" rel="noopener">' . esc_html__( 'documentação oficial', 'dps-debugging-addon' ) . '</a>'
                    );
                    ?>
                </p>

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

            <div class="card">
                <h2><?php esc_html_e( 'Constantes Atuais no wp-config.php', 'dps-debugging-addon' ); ?></h2>
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
     * Renderiza a aba do visualizador de log.
     */
    private function render_log_viewer_tab() {
        $log_viewer = new DPS_Debugging_Log_Viewer();
        $log_file   = $log_viewer->get_debug_log_path();
        $log_exists = $log_viewer->log_exists();

        // Modo de visualização (formatado ou raw)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $view_mode = isset( $_GET['mode'] ) && 'raw' === $_GET['mode'] ? 'raw' : 'formatted';

        ?>
        <div class="dps-debugging-log-viewer-tab">
            <div class="dps-debugging-log-actions">
                <div class="dps-debugging-log-info">
                    <strong><?php esc_html_e( 'Arquivo:', 'dps-debugging-addon' ); ?></strong>
                    <code><?php echo esc_html( $log_file ); ?></code>
                    
                    <?php if ( $log_exists ) : ?>
                        <span class="dps-debugging-log-size">
                            (<?php echo esc_html( $log_viewer->get_log_size_formatted() ); ?>)
                        </span>
                    <?php endif; ?>
                </div>

                <div class="dps-debugging-log-buttons">
                    <?php if ( $log_exists ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&mode=' . ( 'raw' === $view_mode ? 'formatted' : 'raw' ) ) ); ?>" 
                           class="button">
                            <?php echo 'raw' === $view_mode ? esc_html__( 'Visualizar Formatado', 'dps-debugging-addon' ) : esc_html__( 'Visualizar Raw', 'dps-debugging-addon' ); ?>
                        </a>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dps-debugging&tab=log-viewer&dps_debug_action=purge' ), 'dps_debugging_purge' ) ); ?>" 
                           class="button button-secondary"
                           onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja limpar o arquivo de debug? Esta ação não pode ser desfeita.', 'dps-debugging-addon' ) ); ?>');">
                            <?php esc_html_e( 'Limpar Log', 'dps-debugging-addon' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'A constante WP_DEBUG_LOG não está definida ou está desativada. Ative-a na aba Configurações para gerar logs.', 'dps-debugging-addon' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="dps-debugging-log-content">
                <?php if ( ! $log_exists ) : ?>
                    <div class="dps-debugging-log-empty">
                        <p><?php esc_html_e( 'O arquivo de debug não existe ou está vazio.', 'dps-debugging-addon' ); ?></p>
                    </div>
                <?php elseif ( 'raw' === $view_mode ) : ?>
                    <pre class="dps-debugging-log-raw"><?php echo esc_html( $log_viewer->get_raw_content() ); ?></pre>
                <?php else : ?>
                    <?php echo $log_viewer->get_formatted_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteúdo já sanitizado internamente ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
