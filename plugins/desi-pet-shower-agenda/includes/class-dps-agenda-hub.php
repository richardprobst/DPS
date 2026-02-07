<?php
/**
 * Página Hub centralizada da Agenda.
 *
 * Consolida todos os menus de Agenda em uma única página com abas:
 * - Dashboard
 * - Configurações
 * - Capacidade (preparada para futuro)
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
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 ); // Antes dos submenus antigos
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
            'capacity'  => __( 'Capacidade', 'dps-agenda-addon' ),
        ];

        $callbacks = [
            'dashboard' => [ $this, 'render_dashboard_tab' ],
            'settings'  => [ $this, 'render_settings_tab' ],
            'capacity'  => [ $this, 'render_capacity_tab' ],
        ];

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
            
            // Remove o wrapper e H1 duplicado
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
            
            // Remove o wrapper e H1 duplicado
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo wp_kses_post( $content );
        }
    }

    /**
     * Renderiza a aba de Capacidade (placeholder para futuro).
     */
    public function render_capacity_tab() {
        ?>
        <div class="dps-capacity-placeholder">
            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e( 'Gerenciamento de Capacidade', 'dps-agenda-addon' ); ?></strong><br>
                    <?php esc_html_e( 'Esta funcionalidade está em desenvolvimento. Em breve você poderá configurar limites de agendamentos por horário, dia da semana e período.', 'dps-agenda-addon' ); ?>
                </p>
            </div>

            <h2><?php esc_html_e( 'Funcionalidades Planejadas', 'dps-agenda-addon' ); ?></h2>
            <ul style="line-height: 2; margin-left: 20px;">
                <li><?php esc_html_e( '✓ Limite de agendamentos simultâneos por horário', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( '✓ Configuração de capacidade por dia da semana', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( '✓ Bloqueio de horários específicos', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( '✓ Alertas de sobrecarga', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( '✓ Relatório de ocupação', 'dps-agenda-addon' ); ?></li>
            </ul>
        </div>
        <?php
    }
}
