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
            'capacity'  => __( 'Capacidade', 'dps-agenda-addon' ),
        ];

        $callbacks = [
            'dashboard' => [ $this, 'render_dashboard_tab' ],
            'settings'  => [ $this, 'render_settings_tab' ],
            'capacity'  => [ $this, 'render_capacity_tab' ],
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

    /**
     * Renderiza a aba de Capacidade (placeholder para futuro).
     */
    public function render_capacity_tab() {
        ?>
        <div class="dps-agenda-admin-page dps-agenda-hub-placeholder">
            <section class="dps-agenda-admin-card">
                <div class="dps-agenda-admin-card__header">
                    <div>
                        <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Agenda', 'dps-agenda-addon' ); ?></p>
                        <h2 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'Gerenciamento de Capacidade', 'dps-agenda-addon' ); ?></h2>
                        <p class="dps-agenda-admin-description">
                            <?php esc_html_e( 'Esta aba prepara a evolução da Agenda para uma leitura mais previsível de ocupação, limites e bloqueios operacionais.', 'dps-agenda-addon' ); ?>
                        </p>
                    </div>
                    <div class="dps-agenda-admin-chips">
                        <span class="dps-agenda-admin-chip dps-agenda-admin-chip--primary"><?php esc_html_e( 'Em planejamento', 'dps-agenda-addon' ); ?></span>
                        <span class="dps-agenda-admin-chip"><?php esc_html_e( 'Capacidade semanal', 'dps-agenda-addon' ); ?></span>
                    </div>
                </div>

                <div class="dps-agenda-admin-notice dps-agenda-admin-notice--info">
                    <?php esc_html_e( 'A leitura de capacidade completa já está disponível no Dashboard operacional. Esta aba ficará dedicada à configuração avançada do recurso.', 'dps-agenda-addon' ); ?>
                </div>

                <div class="dps-agenda-admin-feature-grid">
                    <article class="dps-agenda-admin-feature-card">
                        <h3><?php esc_html_e( 'Limites por horário', 'dps-agenda-addon' ); ?></h3>
                        <p><?php esc_html_e( 'Definir teto operacional por faixa horária para evitar sobrecarga em momentos críticos.', 'dps-agenda-addon' ); ?></p>
                    </article>
                    <article class="dps-agenda-admin-feature-card">
                        <h3><?php esc_html_e( 'Capacidade por dia', 'dps-agenda-addon' ); ?></h3>
                        <p><?php esc_html_e( 'Ajustar a leitura da semana conforme escala, equipe e perfil de demanda de cada dia.', 'dps-agenda-addon' ); ?></p>
                    </article>
                    <article class="dps-agenda-admin-feature-card">
                        <h3><?php esc_html_e( 'Bloqueios pontuais', 'dps-agenda-addon' ); ?></h3>
                        <p><?php esc_html_e( 'Reservar períodos para manutenção, encaixes prioritários ou indisponibilidades temporárias.', 'dps-agenda-addon' ); ?></p>
                    </article>
                    <article class="dps-agenda-admin-feature-card">
                        <h3><?php esc_html_e( 'Alertas de sobrecarga', 'dps-agenda-addon' ); ?></h3>
                        <p><?php esc_html_e( 'Identificar rapidamente quando a semana estiver acima do volume seguro para a operação.', 'dps-agenda-addon' ); ?></p>
                    </article>
                </div>
            </section>
        </div>
        <?php
    }
}