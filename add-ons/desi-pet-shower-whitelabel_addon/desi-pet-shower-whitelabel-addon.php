<?php
/**
 * Plugin Name:       DPS by PRObst – White Label
 * Plugin URI:        https://www.probst.pro
 * Description:       Personalize o sistema DPS com sua própria marca, cores, logo, SMTP e muito mais. Ideal para agências e revendedores.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-whitelabel-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package DPS_WhiteLabel_Addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do add-on
define( 'DPS_WHITELABEL_VERSION', '1.1.0' );
define( 'DPS_WHITELABEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_WHITELABEL_URL', plugin_dir_url( __FILE__ ) );
define( 'DPS_WHITELABEL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Carrega o text domain do White Label Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_whitelabel_load_textdomain() {
    load_plugin_textdomain( 'dps-whitelabel-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_whitelabel_load_textdomain', 1 );

/**
 * Inicializa o add-on após verificação do plugin base.
 */
function dps_whitelabel_init() {
    // Verifica se o plugin base está ativo
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', 'dps_whitelabel_missing_base_notice' );
        return;
    }

    // Carrega classes do add-on
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-settings.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-branding.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-assets.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-smtp.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-login-page.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-admin-bar.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-maintenance.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-access-control.php';

    // Inicializa a classe principal
    DPS_WhiteLabel_Addon::get_instance();
}
add_action( 'init', 'dps_whitelabel_init', 5 );

/**
 * Exibe aviso se o plugin base não estiver ativo.
 */
function dps_whitelabel_missing_base_notice() {
    $allowed_tags = [
        'strong' => [],
    ];
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            echo wp_kses(
                sprintf(
                    /* translators: %1$s: White Label add-on name, %2$s: Base plugin name */
                    __( 'O add-on %1$s requer o plugin %2$s para funcionar.', 'dps-whitelabel-addon' ),
                    '<strong>DPS by PRObst – White Label</strong>',
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
 * Classe principal do White Label Add-on.
 *
 * Coordena todos os módulos do add-on e gerencia a interface administrativa.
 *
 * @since 1.0.0
 */
class DPS_WhiteLabel_Addon {

    /**
     * Instância única (singleton).
     *
     * @since 1.1.0
     * @var DPS_WhiteLabel_Addon|null
     */
    private static $instance = null;

    /**
     * Instância de configurações.
     *
     * @var DPS_WhiteLabel_Settings
     */
    private $settings;

    /**
     * Instância de branding.
     *
     * @var DPS_WhiteLabel_Branding
     */
    private $branding;

    /**
     * Instância de assets.
     *
     * @var DPS_WhiteLabel_Assets
     */
    private $assets;

    /**
     * Instância de SMTP.
     *
     * @var DPS_WhiteLabel_SMTP
     */
    private $smtp;

    /**
     * Instância de Login Page.
     *
     * @var DPS_WhiteLabel_Login_Page
     */
    private $login_page;

    /**
     * Instância de Admin Bar.
     *
     * @var DPS_WhiteLabel_Admin_Bar
     */
    private $admin_bar;

    /**
     * Instância de Maintenance.
     *
     * @var DPS_WhiteLabel_Maintenance
     */
    private $maintenance;

    /**
     * Instância de Access Control.
     *
     * @var DPS_WhiteLabel_Access_Control
     */
    private $access_control;

    /**
     * Recupera a instância única.
     *
     * @since 1.1.0
     * @return DPS_WhiteLabel_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor da classe.
     */
    private function __construct() {
        // Inicializa módulos
        $this->settings       = new DPS_WhiteLabel_Settings();
        $this->branding       = new DPS_WhiteLabel_Branding();
        $this->assets         = new DPS_WhiteLabel_Assets();
        $this->smtp           = new DPS_WhiteLabel_SMTP();
        $this->login_page     = new DPS_WhiteLabel_Login_Page();
        $this->admin_bar      = new DPS_WhiteLabel_Admin_Bar();
        $this->maintenance    = new DPS_WhiteLabel_Maintenance();
        $this->access_control = new DPS_WhiteLabel_Access_Control();

        // Registra menu admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

        // Enfileira assets admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Adiciona link de configurações na página de plugins
        add_filter( 'plugin_action_links_' . DPS_WHITELABEL_BASENAME, [ $this, 'add_settings_link' ] );
    }

    /**
     * Registra o menu admin do White Label.
     * 
     * NOTA: A partir da v1.1.0, este menu está oculto (parent=null) para backward compatibility.
     * Use o novo hub unificado em dps-system-hub para acessar via aba "White Label".
     */
    public function register_admin_menu() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'White Label', 'dps-whitelabel-addon' ),
            __( 'White Label', 'dps-whitelabel-addon' ),
            'manage_options',
            'dps-whitelabel',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Renderiza a página de configurações.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-whitelabel-addon' ) );
        }

        // Determina a aba ativa
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'branding';
        $allowed_tabs = [ 'branding', 'smtp', 'login', 'admin-bar', 'maintenance', 'access-control' ];
        
        if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
            $active_tab = 'branding';
        }

        // Inclui template de configurações
        include DPS_WHITELABEL_DIR . 'templates/admin-settings.php';
    }

    /**
     * Enfileira assets do admin.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        // Carrega apenas na página de configurações do White Label
        if ( 'desi-pet-shower_page_dps-whitelabel' !== $hook ) {
            return;
        }

        // WordPress Color Picker
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // WordPress Media Uploader
        wp_enqueue_media();

        // CSS do add-on
        wp_enqueue_style(
            'dps-whitelabel-admin',
            DPS_WHITELABEL_URL . 'assets/css/whitelabel-admin.css',
            [],
            DPS_WHITELABEL_VERSION
        );

        // JS do add-on
        wp_enqueue_script(
            'dps-whitelabel-admin',
            DPS_WHITELABEL_URL . 'assets/js/whitelabel-admin.js',
            [ 'jquery', 'wp-color-picker' ],
            DPS_WHITELABEL_VERSION,
            true
        );

        // Localização
        wp_localize_script( 'dps-whitelabel-admin', 'dpsWhiteLabelL10n', [
            'selectImage'       => __( 'Selecionar Imagem', 'dps-whitelabel-addon' ),
            'useImage'          => __( 'Usar esta imagem', 'dps-whitelabel-addon' ),
            'removeImage'       => __( 'Remover', 'dps-whitelabel-addon' ),
            'testEmailSending'  => __( 'Enviando...', 'dps-whitelabel-addon' ),
            'testEmailSuccess'  => __( 'E-mail de teste enviado com sucesso!', 'dps-whitelabel-addon' ),
            'testEmailError'    => __( 'Erro ao enviar e-mail de teste.', 'dps-whitelabel-addon' ),
            'confirmReset'      => __( 'Tem certeza que deseja restaurar as configurações padrão?', 'dps-whitelabel-addon' ),
            'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'dps_whitelabel_ajax' ),
        ] );
    }

    /**
     * Adiciona link de configurações na página de plugins.
     *
     * @param array $links Links existentes.
     * @return array Links com o novo link de configurações.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=dps-whitelabel' ) ),
            esc_html__( 'Configurações', 'dps-whitelabel-addon' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Retorna a instância de configurações.
     *
     * @return DPS_WhiteLabel_Settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Retorna a instância de branding.
     *
     * @return DPS_WhiteLabel_Branding
     */
    public function get_branding() {
        return $this->branding;
    }

    /**
     * Retorna a instância de SMTP.
     *
     * @return DPS_WhiteLabel_SMTP
     */
    public function get_smtp() {
        return $this->smtp;
    }
}

/**
 * Retorna a instância global do add-on.
 *
 * @return DPS_WhiteLabel_Addon|null
 */
function dps_whitelabel() {
    static $instance = null;

    if ( null === $instance && class_exists( 'DPS_WhiteLabel_Addon' ) ) {
        $instance = new DPS_WhiteLabel_Addon();
    }

    return $instance;
}

/**
 * Hook de ativação do plugin.
 */
function dps_whitelabel_activate() {
    // Carrega classes necessárias para o hook de ativação
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-settings.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-smtp.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-login-page.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-admin-bar.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-maintenance.php';
    require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-access-control.php';

    // Cria opções padrão se não existirem
    if ( false === get_option( 'dps_whitelabel_settings' ) ) {
        add_option( 'dps_whitelabel_settings', DPS_WhiteLabel_Settings::get_defaults() );
    }
    if ( false === get_option( 'dps_whitelabel_smtp' ) ) {
        add_option( 'dps_whitelabel_smtp', DPS_WhiteLabel_SMTP::get_defaults() );
    }
    if ( false === get_option( 'dps_whitelabel_login' ) ) {
        add_option( 'dps_whitelabel_login', DPS_WhiteLabel_Login_Page::get_defaults() );
    }
    if ( false === get_option( 'dps_whitelabel_admin_bar' ) ) {
        add_option( 'dps_whitelabel_admin_bar', DPS_WhiteLabel_Admin_Bar::get_defaults() );
    }
    if ( false === get_option( 'dps_whitelabel_maintenance' ) ) {
        add_option( 'dps_whitelabel_maintenance', DPS_WhiteLabel_Maintenance::get_defaults() );
    }
    if ( false === get_option( 'dps_whitelabel_access_control' ) ) {
        add_option( 'dps_whitelabel_access_control', DPS_WhiteLabel_Access_Control::get_defaults() );
    }

    // Limpa cache de rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dps_whitelabel_activate' );

/**
 * Hook de desativação do plugin.
 */
function dps_whitelabel_deactivate() {
    // Limpa cache de rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dps_whitelabel_deactivate' );
