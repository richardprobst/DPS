<?php
/**
 * Página Hub centralizada de Integrações.
 *
 * Consolida todos os menus de integrações em uma única página com abas:
 * - Comunicações (WhatsApp, Email)
 * - Pagamentos (Mercado Pago)
 * - Notificações Push
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub de Integrações.
 */
class DPS_Integrations_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Integrations_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Integrations_Hub
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
            __( 'Integrações', 'dps-base' ),
            __( 'Integrações', 'dps-base' ),
            'manage_options',
            'dps-integrations-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [];
        $callbacks = [];

        // Aba Comunicações (se add-on ativo)
        if ( class_exists( 'DPS_Communications_Addon' ) ) {
            $tabs['communications'] = __( 'Comunicações', 'dps-base' );
            $callbacks['communications'] = [ $this, 'render_communications_tab' ];
        }

        // Aba Pagamentos (se add-on ativo)
        if ( class_exists( 'DPS_Payment_Addon' ) ) {
            $tabs['payments'] = __( 'Pagamentos', 'dps-base' );
            $callbacks['payments'] = [ $this, 'render_payments_tab' ];
        }

        // Aba Notificações Push (se add-on ativo)
        if ( class_exists( 'DPS_Push_Addon' ) ) {
            $tabs['push'] = __( 'Notificações Push', 'dps-base' );
            $callbacks['push'] = [ $this, 'render_push_tab' ];
        }

        if ( empty( $tabs ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Integrações', 'dps-base' ) . '</h1>';
            echo '<p>' . esc_html__( 'Nenhum add-on de integração ativo.', 'dps-base' ) . '</p></div>';
            return;
        }

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Integrações', 'dps-base' ),
            $tabs,
            $callbacks,
            'dps-integrations-hub',
            array_key_first( $tabs )
        );
    }

    /**
     * Renderiza a aba de Comunicações.
     */
    public function render_communications_tab() {
        if ( class_exists( 'DPS_Communications_Addon' ) ) {
            $addon = DPS_Communications_Addon::get_instance();
            ob_start();
            $addon->render_admin_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Pagamentos.
     */
    public function render_payments_tab() {
        if ( class_exists( 'DPS_Payment_Addon' ) ) {
            $addon = DPS_Payment_Addon::get_instance();
            ob_start();
            $addon->render_settings_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Notificações Push.
     */
    public function render_push_tab() {
        if ( class_exists( 'DPS_Push_Addon' ) ) {
            $addon = DPS_Push_Addon::get_instance();
            ob_start();
            $addon->render_admin_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }
}
