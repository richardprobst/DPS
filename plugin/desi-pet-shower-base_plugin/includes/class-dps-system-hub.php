<?php
/**
 * Página Hub centralizada de Sistema.
 *
 * Consolida todos os menus de sistema em uma única página com abas:
 * - Logs
 * - Backup
 * - Debugging
 * - White Label
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub de Sistema.
 */
class DPS_System_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_System_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_System_Hub
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
            __( 'Sistema', 'dps-base' ),
            __( 'Sistema', 'dps-base' ),
            'manage_options',
            'dps-system-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [];
        $callbacks = [];

        // Aba Logs (sempre disponível - do base plugin)
        $tabs['logs'] = __( 'Logs', 'dps-base' );
        $callbacks['logs'] = [ $this, 'render_logs_tab' ];

        // Aba Backup (se add-on ativo)
        if ( class_exists( 'DPS_Backup_Addon' ) ) {
            $tabs['backup'] = __( 'Backup', 'dps-base' );
            $callbacks['backup'] = [ $this, 'render_backup_tab' ];
        }

        // Aba Debugging (se add-on ativo)
        if ( class_exists( 'DPS_Debugging_Addon' ) ) {
            $tabs['debugging'] = __( 'Debugging', 'dps-base' );
            $callbacks['debugging'] = [ $this, 'render_debugging_tab' ];
        }

        // Aba White Label (se add-on ativo)
        if ( class_exists( 'DPS_WhiteLabel_Addon' ) ) {
            $tabs['whitelabel'] = __( 'White Label', 'dps-base' );
            $callbacks['whitelabel'] = [ $this, 'render_whitelabel_tab' ];
        }

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Sistema', 'dps-base' ),
            $tabs,
            $callbacks,
            'dps-system-hub',
            'logs'
        );
    }

    /**
     * Renderiza a aba de Logs.
     */
    public function render_logs_tab() {
        if ( class_exists( 'DPS_Logs_Admin_Page' ) ) {
            ob_start();
            DPS_Logs_Admin_Page::render_page();
            $content = ob_get_clean();
            
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Backup.
     */
    public function render_backup_tab() {
        if ( class_exists( 'DPS_Backup_Addon' ) ) {
            $addon = DPS_Backup_Addon::get_instance();
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
     * Renderiza a aba de Debugging.
     */
    public function render_debugging_tab() {
        if ( class_exists( 'DPS_Debugging_Addon' ) ) {
            $addon = DPS_Debugging_Addon::get_instance();
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
     * Renderiza a aba de White Label.
     */
    public function render_whitelabel_tab() {
        if ( class_exists( 'DPS_WhiteLabel_Addon' ) ) {
            $addon = DPS_WhiteLabel_Addon::get_instance();
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
