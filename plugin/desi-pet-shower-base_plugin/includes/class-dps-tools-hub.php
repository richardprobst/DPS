<?php
/**
 * Página Hub centralizada de Ferramentas.
 *
 * Consolida ferramentas administrativas em uma única página com abas:
 * - Formulário de Cadastro
 * - (Futuras ferramentas podem ser adicionadas aqui)
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub de Ferramentas.
 */
class DPS_Tools_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Tools_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Tools_Hub
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 18 );
    }

    /**
     * Registra o menu hub centralizado.
     */
    public function register_hub_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Ferramentas', 'dps-base' ),
            __( 'Ferramentas', 'dps-base' ),
            'manage_options',
            'dps-tools-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [];
        $callbacks = [];

        // Aba Formulário de Cadastro (se add-on ativo)
        if ( class_exists( 'DPS_Registration_Addon' ) ) {
            $tabs['registration'] = __( 'Formulário de Cadastro', 'dps-base' );
            $callbacks['registration'] = [ $this, 'render_registration_tab' ];
        }

        // Se nenhuma ferramenta está disponível
        if ( empty( $tabs ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Ferramentas', 'dps-base' ) . '</h1>';
            echo '<p>' . esc_html__( 'Nenhuma ferramenta administrativa está ativa no momento.', 'dps-base' ) . '</p></div>';
            return;
        }

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Ferramentas', 'dps-base' ),
            $tabs,
            $callbacks,
            'dps-tools-hub',
            array_key_first( $tabs )
        );
    }

    /**
     * Renderiza a aba de Formulário de Cadastro.
     */
    public function render_registration_tab() {
        if ( class_exists( 'DPS_Registration_Addon' ) ) {
            $addon = DPS_Registration_Addon::get_instance();
            ob_start();
            $addon->render_settings_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }
}
