<?php
/**
 * Página Hub centralizada da Agenda.
 *
 * Consolida os menus principais de Agenda em uma única página com abas.
 *
 * @package DPS_Agenda_Addon
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub da Agenda.
 */
class DPS_Agenda_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Agenda_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Agenda_Hub
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
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 );
    }

    /**
     * Registra o menu hub centralizado.
     */
    public function register_hub_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Agenda', 'dps-agenda-addon' ),
            __( 'Agenda', 'dps-agenda-addon' ),
            'manage_options',
            'dps-agenda-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [
            'dashboard' => __( 'Dashboard', 'dps-agenda-addon' ),
            'settings'  => __( 'Configurações', 'dps-agenda-addon' ),
        ];

        $callbacks = [
            'dashboard' => [ $this, 'render_dashboard_tab' ],
            'settings'  => [ $this, 'render_settings_tab' ],
        ];

        $tabs = apply_filters( 'dps_agenda_hub_tabs', $tabs );

        foreach ( array_keys( $tabs ) as $slug ) {
            if ( isset( $callbacks[ $slug ] ) ) {
                continue;
            }

            $callbacks[ $slug ] = function() use ( $slug ) {
                do_action( 'dps_agenda_hub_tab_content_' . $slug );
            };
        }

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Agenda', 'dps-agenda-addon' ),
            $tabs,
            $callbacks,
            'dps-agenda-hub',
            'dashboard'
        );
    }

    /**
     * Renderiza a aba de Dashboard.
     */
    public function render_dashboard_tab() {
        if ( class_exists( 'DPS_Agenda_Addon' ) ) {
            $addon = DPS_Agenda_Addon::get_instance();
            ob_start();
            $addon->render_dashboard_admin_page();
            $content = ob_get_clean();

            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );

            echo wp_kses_post( $content );
        }
    }

    /**
     * Renderiza a aba de Configurações.
     */
    public function render_settings_tab() {
        if ( class_exists( 'DPS_Agenda_Addon' ) ) {
            $addon = DPS_Agenda_Addon::get_instance();
            ob_start();
            $addon->render_settings_admin_page();
            $content = ob_get_clean();

            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );

            echo wp_kses_post( $content );
        }
    }
}
