<?php
/**
 * Central hub for Agenda admin surfaces.
 *
 * @package DPS_Agenda_Addon
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Hub {

    /**
     * Singleton instance.
     *
     * @var DPS_Agenda_Hub|null
     */
    private static $instance = null;

    /**
     * Returns the singleton instance.
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
     * Private constructor.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 );
    }

    /**
     * Registers the hub submenu.
     *
     * @return void
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
     * Renders the hub shell with custom tabs aligned to DPS Signature.
     *
     * @return void
     */
    public function render_hub_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-agenda-addon' ) );
        }

        $tab_data    = $this->get_tabs_and_callbacks();
        $tabs        = $tab_data['tabs'];
        $callbacks   = $tab_data['callbacks'];
        $default_tab = array_key_exists( 'dashboard', $tabs ) ? 'dashboard' : array_key_first( $tabs );
        $active_tab  = DPS_Admin_Tabs_Helper::get_active_tab( $default_tab );

        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = $default_tab;
        }

        echo '<div class="wrap dps-agenda-admin-shell dps-agenda-hub">';
        echo '<div class="dps-agenda-admin-page dps-agenda-hub-page">';
        echo '<section class="dps-agenda-admin-card dps-agenda-hub-header">';
        echo '<div class="dps-agenda-admin-card__header">';
        echo '<div>';
        echo '<p class="dps-agenda-admin-eyebrow">' . esc_html__( 'Agenda', 'dps-agenda-addon' ) . '</p>';
        echo '<h1 class="dps-agenda-admin-title">' . esc_html__( 'Operação e configuração', 'dps-agenda-addon' ) . '</h1>';
        echo '<p class="dps-agenda-admin-description">' . esc_html__( 'Dashboard, parâmetros operacionais e integrações em uma navegação direta e consistente com o DPS Signature.', 'dps-agenda-addon' ) . '</p>';
        echo '</div>';
        echo '<div class="dps-agenda-admin-chips">';
        echo '<span class="dps-agenda-admin-chip dps-agenda-admin-chip--primary">' . esc_html( $tabs[ $active_tab ] ) . '</span>';
        echo '<span class="dps-agenda-admin-chip">' . esc_html__( 'Painel administrativo', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<nav class="dps-agenda-hub-tabs" aria-label="' . esc_attr__( 'Navegação da Agenda', 'dps-agenda-addon' ) . '">';

        foreach ( $tabs as $slug => $label ) {
            $tab_url       = add_query_arg( 'tab', $slug, admin_url( 'admin.php?page=dps-agenda-hub' ) );
            $is_active     = $active_tab === $slug;
            $active_class  = $is_active ? ' dps-agenda-hub-tab--active' : '';
            $current_attr  = $is_active ? ' aria-current="page"' : '';

            echo '<a href="' . esc_url( $tab_url ) . '" class="dps-agenda-hub-tab' . esc_attr( $active_class ) . '"' . $current_attr . '>';
            echo '<span class="dps-agenda-hub-tab__label">' . esc_html( $label ) . '</span>';
            echo '</a>';
        }

        echo '</nav>';
        echo '</section>';
        echo '<section class="dps-agenda-hub-content">';
        $this->render_active_tab_content( $active_tab, $callbacks );
        echo '</section>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Builds the hub tabs and tab callbacks.
     *
     * @return array{tabs: array<string,string>, callbacks: array<string,callable>}
     */
    private function get_tabs_and_callbacks() {
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

        return [
            'tabs'      => $tabs,
            'callbacks' => $callbacks,
        ];
    }

    /**
     * Renders the active tab content.
     *
     * @param string                $active_tab Active tab slug.
     * @param array<string,callable> $callbacks Tab callbacks.
     * @return void
     */
    private function render_active_tab_content( $active_tab, array $callbacks ) {
        if ( isset( $callbacks[ $active_tab ] ) && is_callable( $callbacks[ $active_tab ] ) ) {
            call_user_func( $callbacks[ $active_tab ] );
            return;
        }

        echo '<div class="dps-agenda-admin-notice dps-agenda-admin-notice--warning">';
        echo esc_html__( 'A aba solicitada não está disponível no momento.', 'dps-agenda-addon' );
        echo '</div>';
    }

    /**
     * Renders the dashboard tab.
     *
     * @return void
     */
    public function render_dashboard_tab() {
        if ( ! class_exists( 'DPS_Agenda_Addon' ) ) {
            return;
        }

        $addon = DPS_Agenda_Addon::get_instance();
        ob_start();
        $addon->render_dashboard_admin_page();
        $content = ob_get_clean();

        $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
        $content = preg_replace( '/<\/div>\s*$/i', '', $content );
        $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin HTML is escaped at source.
    }

    /**
     * Renders the settings tab.
     *
     * @return void
     */
    public function render_settings_tab() {
        if ( ! class_exists( 'DPS_Agenda_Addon' ) ) {
            return;
        }

        $addon = DPS_Agenda_Addon::get_instance();
        ob_start();
        $addon->render_settings_admin_page();
        $content = ob_get_clean();

        $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
        $content = preg_replace( '/<\/div>\s*$/i', '', $content );
        $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin HTML is escaped at source.
    }
}
