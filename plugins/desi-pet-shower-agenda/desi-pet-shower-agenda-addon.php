<?php
/**
 * Plugin Name:       desi.pet by PRObst – Agenda Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Cria página automática com agenda de atendimentos. Visualize e gerencie compromissos de forma prática.
 * Version:           1.1.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-agenda-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_agenda_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-agenda-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_agenda_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Agenda Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_agenda_load_textdomain() {
    load_plugin_textdomain( 'dps-agenda-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_agenda_load_textdomain', 1 );

// FASE 3: Carrega traits de refatoração e helpers
require_once __DIR__ . '/includes/trait-dps-agenda-renderer.php';
require_once __DIR__ . '/includes/trait-dps-agenda-query.php';

// FASE 3: Carrega helpers para Pagamento, TaxiDog e GPS
require_once __DIR__ . '/includes/class-dps-agenda-payment-helper.php';
require_once __DIR__ . '/includes/class-dps-agenda-taxidog-helper.php';
require_once __DIR__ . '/includes/class-dps-agenda-gps-helper.php';

// FASE 4: Carrega helper para Dashboard Operacional
require_once __DIR__ . '/includes/class-dps-agenda-dashboard-service.php';

// FASE 4: Carrega helper para Capacidade/Lotação
require_once __DIR__ . '/includes/class-dps-agenda-capacity-helper.php';

// Hub centralizado de Agenda (Fase 2 - Reorganização de Menus)
require_once __DIR__ . '/includes/class-dps-agenda-hub.php';

class DPS_Agenda_Addon {
    
    // FASE 3: Usa traits para métodos auxiliares
    use DPS_Agenda_Renderer;
    use DPS_Agenda_Query;
    
    /**
     * Instância única (singleton).
     *
     * @since 1.4.1
     * @var DPS_Agenda_Addon|null
     */
    private static $instance = null;
    
    /**
     * Recupera a instância única.
     *
     * @since 1.4.1
     * @return DPS_Agenda_Addon
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Número de agendamentos por página no modo "Todos".
     * 
     * @since 1.1.0
     */
    const APPOINTMENTS_PER_PAGE = 50;
    
    /**
     * Limite de agendamentos por dia nas queries de visualização.
     * Pode ser filtrado via 'dps_agenda_daily_limit'.
     * 
     * @since 1.2.0
     */
    const DAILY_APPOINTMENTS_LIMIT = 200;
    
    /**
     * Limite de clientes na lista de filtros.
     * Pode ser filtrado via 'dps_agenda_clients_limit'.
     * 
     * @since 1.2.0
     */
    const CLIENTS_LIST_LIMIT = 300;
    
    /**
     * Limite de serviços na lista de filtros.
     * Pode ser filtrado via 'dps_agenda_services_limit'.
     * 
     * @since 1.2.0
     */
    const SERVICES_LIST_LIMIT = 200;
    
    /**
     * Constantes de status de agendamento.
     * Centralizadas para evitar strings hardcoded.
     * 
     * @since 1.3.1
     */
    const STATUS_PENDING = 'pendente';
    const STATUS_FINISHED = 'finalizado';
    const STATUS_PAID = 'finalizado_pago';
    const STATUS_CANCELED = 'cancelado';
    
    /**
     * Retorna configuração de status com labels, cores e ícones.
     *
     * Use este método para obter a configuração completa de todos os status
     * disponíveis. Cada status contém label traduzida, cor de destaque,
     * cor de fundo e ícone.
     *
     * Exemplo de uso:
     * ```php
     * $config = DPS_Agenda_Addon::get_status_config();
     * $pending_color = $config[ DPS_Agenda_Addon::STATUS_PENDING ]['color'];
     * $pending_label = $config[ DPS_Agenda_Addon::STATUS_PENDING ]['label'];
     * ```
     *
     * @since 1.3.1
     * @return array Configuração completa de status. Cada item contém:
     *               - 'label' (string) Label traduzida
     *               - 'color' (string) Cor hex para borda/destaque
     *               - 'bg'    (string) Cor hex para fundo
     *               - 'icon'  (string) Emoji/ícone
     */
    public static function get_status_config() {
        return [
            self::STATUS_PENDING => [
                'label' => __( 'Pendente', 'dps-agenda-addon' ),
                'color' => '#f59e0b',
                'bg'    => '#fffbeb',
                'icon'  => '⏳',
            ],
            self::STATUS_FINISHED => [
                'label' => __( 'Finalizado', 'dps-agenda-addon' ),
                'color' => '#0ea5e9',
                'bg'    => '#f0f9ff',
                'icon'  => '✓',
            ],
            self::STATUS_PAID => [
                'label' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                'color' => '#22c55e',
                'bg'    => '#f0fdf4',
                'icon'  => '💰',
            ],
            self::STATUS_CANCELED => [
                'label' => __( 'Cancelado', 'dps-agenda-addon' ),
                'color' => '#ef4444',
                'bg'    => '#fef2f2',
                'icon'  => '❌',
            ],
        ];
    }
    
    /**
     * Retorna label traduzida para um status.
     *
     * @since 1.3.1
     * @param string $status Código do status.
     * @return string Label traduzida ou o próprio código se não encontrado.
     */
    public static function get_status_label( $status ) {
        $config = self::get_status_config();
        return isset( $config[ $status ]['label'] ) ? $config[ $status ]['label'] : $status;
    }
    
    /**
     * Construtor privado (singleton).
     *
     * @since 1.4.1
     */
    private function __construct() {
        // Verifica dependência do Finance Add-on após todos os plugins terem sido carregados
        add_action( 'plugins_loaded', [ $this, 'check_finance_dependency' ] );

        // Cria páginas necessárias ao ativar o plugin (apenas agenda, sem a página de cobranças)
        register_activation_hook( __FILE__, [ $this, 'create_agenda_page' ] );
        // Limpa cron jobs ao desativar o plugin
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        // Registra shortcodes
        add_shortcode( 'dps_agenda_page', [ $this, 'render_agenda_shortcode' ] );
        // FASE 4: Shortcode para Dashboard Operacional
        add_shortcode( 'dps_agenda_dashboard', [ $this, 'render_dashboard_shortcode' ] );
        // Shortcode dps_charges_notes deprecated - redireciona para Finance
        add_shortcode( 'dps_charges_notes', [ $this, 'render_charges_notes_shortcode_deprecated' ] );
        // Enfileira scripts e estilos somente quando páginas específicas forem exibidas
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // AJAX para atualizar status de agendamento (apenas usuários autenticados)
        add_action( 'wp_ajax_dps_update_status', [ $this, 'update_status_ajax' ] );
        
        // UX-1: AJAX para ações rápidas de status
        add_action( 'wp_ajax_dps_agenda_quick_action', [ $this, 'quick_action_ajax' ] );
        
        // CONF-2: AJAX para atualização de status de confirmação
        add_action( 'wp_ajax_dps_agenda_update_confirmation', [ $this, 'update_confirmation_ajax' ] );
        
        // FASE 3: AJAX para atualização de status de TaxiDog
        add_action( 'wp_ajax_dps_agenda_update_taxidog', [ $this, 'update_taxidog_ajax' ] );
        
        // FASE 7: AJAX para solicitar TaxiDog
        add_action( 'wp_ajax_dps_agenda_request_taxidog', [ $this, 'request_taxidog_ajax' ] );
        
        // FASE 4: AJAX para salvar configuração de capacidade
        add_action( 'wp_ajax_dps_agenda_save_capacity', [ $this, 'save_capacity_ajax' ] );
        
        // FASE 5: AJAX para reenviar link de pagamento
        add_action( 'wp_ajax_dps_agenda_resend_payment', [ $this, 'resend_payment_ajax' ] );

        // Versionamento de agendamentos para evitar conflitos de escrita
        add_action( 'save_post_dps_agendamento', [ $this, 'ensure_appointment_version_meta' ], 10, 3 );

        // AJAX para obter detalhes de serviços de um agendamento (apenas usuários autenticados)
        add_action( 'wp_ajax_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );

        // FASE 2: AJAX para exportação CSV da agenda (mantido por compatibilidade)
        add_action( 'wp_ajax_dps_agenda_export_csv', [ $this, 'export_csv_ajax' ] );
        
        // FASE 2: AJAX para exportação PDF da agenda
        add_action( 'wp_ajax_dps_agenda_export_pdf', [ $this, 'export_pdf_ajax' ] );

        // FASE 4: AJAX para calendário mensal
        add_action( 'wp_ajax_dps_agenda_calendar_events', [ $this, 'calendar_events_ajax' ] );

        // FASE 5: AJAX para ações administrativas avançadas
        add_action( 'wp_ajax_dps_bulk_update_status', [ $this, 'bulk_update_status_ajax' ] );
        add_action( 'wp_ajax_dps_quick_reschedule', [ $this, 'quick_reschedule_ajax' ] );
        add_action( 'wp_ajax_dps_get_appointment_history', [ $this, 'get_appointment_history_ajax' ] );
        add_action( 'wp_ajax_dps_get_admin_kpis', [ $this, 'get_admin_kpis_ajax' ] );

        // FASE 5: Registra alterações de status no histórico
        add_action( 'dps_appointment_status_changed', [ $this, 'log_status_change' ], 10, 4 );

        // Agenda: agendamento de envio de lembretes diários
        add_action( 'init', [ $this, 'maybe_schedule_reminders' ] );
        add_action( 'dps_agenda_send_reminders', [ $this, 'send_reminders' ] );
        
        // FASE 4: Adiciona página de Dashboard no admin
        add_action( 'admin_menu', [ $this, 'register_dashboard_admin_page' ], 20 );
        
        // FASE 5: Adiciona página de Configurações no admin
        add_action( 'admin_menu', [ $this, 'register_settings_admin_page' ], 21 );
        
        // FASE 4: Enfileira assets do Dashboard no admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    /**
     * Verifica se o Finance Add-on está ativo após todos os plugins terem sido carregados.
     *
     * Este método é executado no hook 'plugins_loaded' para garantir que todos os plugins
     * já tenham sido carregados antes de verificar a existência da classe DPS_Finance_API.
     *
     * @since 1.0.1
     */
    public function check_finance_dependency() {
        if ( ! class_exists( 'DPS_Finance_API' ) ) {
            add_action( 'admin_notices', [ $this, 'finance_dependency_notice' ] );
            // Continua a carregar para não quebrar completamente, mas funcionalidade financeira não estará disponível
        }
    }

    /**
     * Cria a página de agenda de atendimentos.
     * 
     * @since 1.0.0
     * @return void
     */
    public function create_agenda_page() {
        $title = __( 'Agenda de Atendimentos', 'dps-agenda-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_agenda_page]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_agenda_page_id', $page_id );
            }
        } else {
            update_option( 'dps_agenda_page_id', $page->ID );
        }
    }

    /**
     * FASE 4: Registra página de Dashboard no admin.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-agenda-hub (aba "Dashboard").
     *
     * @since 1.3.0
     */
    public function register_dashboard_admin_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Dashboard da Agenda', 'dps-agenda-addon' ),
            __( 'Dashboard', 'dps-agenda-addon' ),
            'manage_options',
            'dps-agenda-dashboard',
            [ $this, 'render_dashboard_admin_page' ]
        );
    }

    /**
     * FASE 4: Enfileira assets do Dashboard no admin.
     *
     * @since 1.3.0
     */
    public function enqueue_dashboard_assets( $hook ) {
        // Cast para string para compatibilidade com PHP 8.1+
        $hook = (string) $hook;

        // Carrega apenas na página do dashboard ou na aba Dashboard do hub
        $is_dashboard_page = 'desi-pet-shower_page_dps-agenda-dashboard' === $hook;

        $is_hub_dashboard_tab = (
            'desi-pet-shower_page_dps-agenda-hub' === $hook
            && 'dashboard' === DPS_Admin_Tabs_Helper::get_active_tab( 'dashboard' )
        );

        if ( ! $is_dashboard_page && ! $is_hub_dashboard_tab ) {
            return;
        }

        wp_enqueue_style(
            'dps-dashboard-css',
            plugin_dir_url( __FILE__ ) . 'assets/css/dashboard.css',
            [],
            '1.3.0'
        );

        // jQuery já está enfileirado no admin
        wp_enqueue_script( 'jquery' );
    }

    /**
     * FASE 4: Renderiza a página de Dashboard no admin.
     *
     * @since 1.3.0
     */
    public function render_dashboard_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-agenda-addon' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Dashboard Operacional da Agenda', 'dps-agenda-addon' ) . '</h1>';
        echo $this->render_dashboard_shortcode();
        echo '</div>';
    }

    /**
     * FASE 5: Registra página de Configurações no admin.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-agenda-hub (aba "Configurações").
     *
     * @since 1.5.0
     */
    public function register_settings_admin_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Configurações da Agenda', 'dps-agenda-addon' ),
            __( 'Configurações', 'dps-agenda-addon' ),
            'manage_options',
            'dps-agenda-settings',
            [ $this, 'render_settings_admin_page' ]
        );
    }

    /**
     * FASE 5: Renderiza a página de Configurações no admin.
     *
     * @since 1.5.0
     */
    public function render_settings_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-agenda-addon' ) );
        }

        // Processa salvamento
        if ( isset( $_POST['dps_save_settings'] ) && check_admin_referer( 'dps_agenda_settings' ) ) {
            $shop_address = isset( $_POST['dps_shop_address'] ) ? sanitize_textarea_field( $_POST['dps_shop_address'] ) : '';
            update_option( 'dps_shop_address', $shop_address );
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configurações salvas com sucesso!', 'dps-agenda-addon' ) . '</p></div>';
        }

        $shop_address = get_option( 'dps_shop_address', '' );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Configurações da Agenda', 'dps-agenda-addon' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_agenda_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dps_shop_address"><?php esc_html_e( 'Endereço do Banho e Tosa', 'dps-agenda-addon' ); ?></label>
                        </th>
                        <td>
                            <textarea 
                                name="dps_shop_address" 
                                id="dps_shop_address" 
                                rows="3" 
                                class="large-text"
                                placeholder="<?php esc_attr_e( 'Ex: Rua Exemplo, 123, Centro, São Paulo - SP, CEP 01234-567', 'dps-agenda-addon' ); ?>"
                            ><?php echo esc_textarea( $shop_address ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Endereço completo usado como ponto de origem nas rotas GPS. Será usado para traçar rotas do Banho e Tosa até o cliente.', 'dps-agenda-addon' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="dps_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Salvar Configurações', 'dps-agenda-addon' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * FASE 4: Renderiza o Dashboard Operacional via shortcode.
     *
     * @since 1.3.0
     * @return string HTML do dashboard.
     */
    public function render_dashboard_shortcode() {
        // Verifica permissão
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return '<p>' . esc_html__( 'Acesso negado.', 'dps-agenda-addon' ) . '</p>';
        }

        // Obtém data selecionada (default: hoje)
        $selected_date = isset( $_GET['dashboard_date'] ) ? sanitize_text_field( $_GET['dashboard_date'] ) : current_time( 'Y-m-d' );

        // Valida formato de data
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
            $selected_date = current_time( 'Y-m-d' );
        }

        // Obtém KPIs do dia
        $kpis = DPS_Agenda_Dashboard_Service::get_daily_kpis( $selected_date );

        // Obtém próximos atendimentos
        $next_appointments = DPS_Agenda_Dashboard_Service::get_next_appointments( $selected_date, 10 );

        ob_start();
        ?>
        <div class="dps-dashboard-wrapper">
            
            <!-- Seletor de Data -->
            <div class="dps-dashboard-date-selector">
                <form method="get" class="dps-dashboard-form">
                    <?php
                    // Preserva parâmetros da URL
                    if ( isset( $_GET['page'] ) ) {
                        echo '<input type="hidden" name="page" value="' . esc_attr( $_GET['page'] ) . '">';
                    }
                    ?>
                    
                    <div class="dps-dashboard-date-controls">
                        <button type="button" class="dps-dashboard-quick-date" data-days="-1">
                            <?php esc_html_e( '← Ontem', 'dps-agenda-addon' ); ?>
                        </button>
                        
                        <button type="button" class="dps-dashboard-quick-date" data-days="0">
                            <?php esc_html_e( 'Hoje', 'dps-agenda-addon' ); ?>
                        </button>
                        
                        <button type="button" class="dps-dashboard-quick-date" data-days="1">
                            <?php esc_html_e( 'Amanhã →', 'dps-agenda-addon' ); ?>
                        </button>
                        
                        <input type="date" 
                               name="dashboard_date" 
                               value="<?php echo esc_attr( $selected_date ); ?>" 
                               class="dps-dashboard-date-input">
                        
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Atualizar', 'dps-agenda-addon' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- KPI Cards -->
            <div class="dps-dashboard-kpis">
                
                <!-- Card 1: Atendimentos de Hoje -->
                <div class="dps-dashboard-kpi-section">
                    <h3><?php esc_html_e( 'Atendimentos', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Total', 'dps-agenda-addon' ),
                            $kpis['total_counts']['total'],
                            sprintf(
                                __( 'Manhã: %d | Tarde: %d', 'dps-agenda-addon' ),
                                $kpis['total_counts']['morning'],
                                $kpis['total_counts']['afternoon']
                            ),
                            '#3b82f6'
                        );
                        ?>
                    </div>
                </div>

                <!-- Card 2: Confirmação -->
                <div class="dps-dashboard-kpi-section">
                    <h3><?php esc_html_e( 'Confirmação', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Confirmados', 'dps-agenda-addon' ),
                            $kpis['confirmation_stats']['confirmed'],
                            '',
                            '#10b981'
                        );
                        
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Não Confirmados', 'dps-agenda-addon' ),
                            $kpis['confirmation_stats']['not_confirmed'],
                            '',
                            '#f59e0b'
                        );
                        ?>
                    </div>
                </div>

                <!-- Card 3: Status de Execução -->
                <div class="dps-dashboard-kpi-section">
                    <h3><?php esc_html_e( 'Execução', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Concluídos', 'dps-agenda-addon' ),
                            $kpis['execution_stats']['completed'],
                            '',
                            '#10b981'
                        );
                        
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Cancelados', 'dps-agenda-addon' ),
                            $kpis['execution_stats']['canceled'],
                            '',
                            '#ef4444'
                        );
                        
                        if ( $kpis['execution_stats']['late'] > 0 ) {
                            echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                                __( 'Atrasados', 'dps-agenda-addon' ),
                                $kpis['execution_stats']['late'],
                                __( 'Pendentes após horário', 'dps-agenda-addon' ),
                                '#f59e0b'
                            );
                        }
                        ?>
                    </div>
                </div>

                <!-- Card 4: Especiais -->
                <div class="dps-dashboard-kpi-section">
                    <h3><?php esc_html_e( 'Especiais', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'TaxiDog', 'dps-agenda-addon' ),
                            $kpis['special_stats']['with_taxidog'],
                            '',
                            '#3b82f6'
                        );
                        
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Cobrança Pendente', 'dps-agenda-addon' ),
                            $kpis['special_stats']['pending_payment'],
                            '',
                            '#f59e0b'
                        );
                        ?>
                    </div>
                </div>
            </div>

            <!-- Próximos Atendimentos -->
            <?php if ( ! empty( $next_appointments ) ) : ?>
                <div class="dps-dashboard-next-appointments">
                    <h3><?php esc_html_e( 'Próximos Atendimentos', 'dps-agenda-addon' ); ?></h3>
                    
                    <table class="dps-dashboard-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Hora', 'dps-agenda-addon' ); ?></th>
                                <th><?php esc_html_e( 'Pet', 'dps-agenda-addon' ); ?></th>
                                <th><?php esc_html_e( 'Tutor', 'dps-agenda-addon' ); ?></th>
                                <th><?php esc_html_e( 'Serviços', 'dps-agenda-addon' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'dps-agenda-addon' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $next_appointments as $appt ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $appt['time'] ); ?></strong></td>
                                    <td><?php echo esc_html( $appt['pet_name'] ); ?></td>
                                    <td><?php echo esc_html( $appt['client_name'] ); ?></td>
                                    <td><?php echo esc_html( $appt['services'] ?: '-' ); ?></td>
                                    <td>
                                        <?php
                                        $status_config = DPS_Agenda_Addon::get_status_config();
                                        $status_label = isset( $status_config[ $appt['status'] ]['label'] ) 
                                            ? $status_config[ $appt['status'] ]['label'] 
                                            : $appt['status'];
                                        echo esc_html( $status_label );
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="dps-dashboard-empty">
                    <p><?php esc_html_e( 'Nenhum atendimento próximo encontrado para esta data.', 'dps-agenda-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- FASE 4: Capacidade/Lotação Heatmap -->
            <div class="dps-dashboard-capacity-section">
                <div class="dps-dashboard-capacity-header">
                    <h3><?php esc_html_e( 'Capacidade / Lotação da Semana', 'dps-agenda-addon' ); ?></h3>
                    
                    <!-- Navegação de Semana -->
                    <?php
                    $week_dates = DPS_Agenda_Capacity_Helper::get_week_dates( $selected_date );
                    $prev_week_date = date( 'Y-m-d', strtotime( $week_dates['start'] . ' -7 days' ) );
                    $next_week_date = date( 'Y-m-d', strtotime( $week_dates['start'] . ' +7 days' ) );
                    ?>
                    <div class="dps-capacity-week-nav">
                        <a href="?page=<?php echo esc_attr( $_GET['page'] ?? 'dps-agenda-dashboard' ); ?>&dashboard_date=<?php echo esc_attr( $prev_week_date ); ?>" class="button">
                            ← <?php esc_html_e( 'Semana Anterior', 'dps-agenda-addon' ); ?>
                        </a>
                        <span class="dps-capacity-week-label">
                            <?php
                            echo esc_html(
                                sprintf(
                                    __( 'Semana de %s a %s', 'dps-agenda-addon' ),
                                    date_i18n( 'd/m', strtotime( $week_dates['start'] ) ),
                                    date_i18n( 'd/m/Y', strtotime( $week_dates['end'] ) )
                                )
                            );
                            ?>
                        </span>
                        <a href="?page=<?php echo esc_attr( $_GET['page'] ?? 'dps-agenda-dashboard' ); ?>&dashboard_date=<?php echo esc_attr( $next_week_date ); ?>" class="button">
                            <?php esc_html_e( 'Próxima Semana', 'dps-agenda-addon' ); ?> →
                        </a>
                    </div>
                </div>

                <!-- Configuração de Capacidade -->
                <div class="dps-capacity-config">
                    <h4><?php esc_html_e( 'Configuração de Capacidade Máxima', 'dps-agenda-addon' ); ?></h4>
                    <?php $capacity_config = DPS_Agenda_Capacity_Helper::get_capacity_config(); ?>
                    <form id="dps-capacity-config-form" class="dps-capacity-form">
                        <div class="dps-capacity-inputs">
                            <div class="dps-capacity-input-group">
                                <label for="capacity_morning"><?php esc_html_e( 'Manhã (08:00-11:59):', 'dps-agenda-addon' ); ?></label>
                                <input type="number" id="capacity_morning" name="morning" value="<?php echo esc_attr( $capacity_config['morning'] ); ?>" min="1" max="100">
                                <span class="description"><?php esc_html_e( 'atendimentos', 'dps-agenda-addon' ); ?></span>
                            </div>
                            <div class="dps-capacity-input-group">
                                <label for="capacity_afternoon"><?php esc_html_e( 'Tarde (12:00-17:59):', 'dps-agenda-addon' ); ?></label>
                                <input type="number" id="capacity_afternoon" name="afternoon" value="<?php echo esc_attr( $capacity_config['afternoon'] ); ?>" min="1" max="100">
                                <span class="description"><?php esc_html_e( 'atendimentos', 'dps-agenda-addon' ); ?></span>
                            </div>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Salvar Capacidade', 'dps-agenda-addon' ); ?>
                            </button>
                        </div>
                    </form>
                    <p class="description">
                        <?php esc_html_e( 'A capacidade é uma referência para ajudar a equipe a evitar overbooking. Não impede agendamentos automaticamente.', 'dps-agenda-addon' ); ?>
                    </p>
                </div>

                <!-- Heatmap -->
                <?php echo DPS_Agenda_Capacity_Helper::render_capacity_heatmap( $week_dates['start'], $week_dates['end'] ); ?>
            </div>

            <!-- Link para Agenda Completa -->
            <div class="dps-dashboard-actions">
                <?php
                $agenda_page_id = get_option( 'dps_agenda_page_id' );
                if ( $agenda_page_id ) {
                    $agenda_permalink = get_permalink( $agenda_page_id );
                    if ( $agenda_permalink && is_string( $agenda_permalink ) ) {
                        $agenda_url = add_query_arg( 'dps_date', $selected_date, $agenda_permalink );
                    ?>
                    <a href="<?php echo esc_url( $agenda_url ); ?>" class="button button-primary button-large">
                        <?php esc_html_e( 'Ver Agenda Completa', 'dps-agenda-addon' ); ?>
                    </a>
                    <?php
                    }
                }
                ?>
            </div>

        </div>

        <script>
        jQuery(document).ready(function($){
            // Botões de data rápida
            $('.dps-dashboard-quick-date').on('click', function(){
                var days = parseInt($(this).data('days'), 10);
                var today = new Date();
                today.setDate(today.getDate() + days);
                
                var year = today.getFullYear();
                var month = String(today.getMonth() + 1).padStart(2, '0');
                var day = String(today.getDate()).padStart(2, '0');
                var dateStr = year + '-' + month + '-' + day;
                
                $('.dps-dashboard-date-input').val(dateStr);
                $('.dps-dashboard-form').submit();
            });

            // FASE 4: Form de configuração de capacidade
            $('#dps-capacity-config-form').on('submit', function(e){
                e.preventDefault();
                
                var morning = $('#capacity_morning').val();
                var afternoon = $('#capacity_afternoon').val();
                var submitBtn = $(this).find('button[type="submit"]');
                var originalText = submitBtn.text();
                
                submitBtn.prop('disabled', true).text('Salvando...');
                
                $.post(ajaxurl, {
                    action: 'dps_agenda_save_capacity',
                    nonce: DPS_AG_Addon.nonce_capacity,
                    morning: morning,
                    afternoon: afternoon
                }, function(resp){
                    if (resp && resp.success) {
                        submitBtn.text('Salvo!');
                        // Recarrega a página após 1 segundo para atualizar o heatmap
                        setTimeout(function(){
                            location.reload();
                        }, 1000);
                    } else {
                        alert(resp.data ? resp.data.message : 'Erro ao salvar configuração.');
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                }).fail(function(){
                    alert('Erro de comunicação ao salvar configuração.');
                    submitBtn.prop('disabled', false).text(originalText);
                });
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * Exibe aviso no admin se Finance Add-on não estiver ativo.
     *
     * @since 1.1.0
     */
    public function finance_dependency_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Agenda Add-on:', 'dps-agenda-addon' ); ?></strong>
                <?php esc_html_e( 'O Finance Add-on é recomendado para funcionalidade completa de cobranças. Algumas funcionalidades financeiras podem não estar disponíveis.', 'dps-agenda-addon' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Cria a página para listar cobranças e notas geradas pelo addon.
     */
    public function create_charges_page() {
        $title = __( 'Cobranças e Notas', 'dps-agenda-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_charges_notes]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_charges_page_id', $page_id );
            }
        } else {
            update_option( 'dps_charges_page_id', $page->ID );
        }
    }

    /**
     * Garante que o meta de versão do agendamento seja inicializado.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post sendo salvo.
     * @param bool    $update  Indica se é uma atualização.
     */
    public function ensure_appointment_version_meta( $post_id, $post, $update ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $post || 'dps_agendamento' !== $post->post_type ) {
            return;
        }

        $current_version = intval( get_post_meta( $post_id, '_dps_appointment_version', true ) );

        if ( $current_version < 1 ) {
            update_post_meta( $post_id, '_dps_appointment_version', 1 );
        }
    }

    /**
     * Enfileira os scripts e estilos necessários apenas quando a página de agenda for carregada.
     * 
     * CSS e JS agora são carregados de arquivos externos (assets/css e assets/js)
     * para melhor cache do navegador, minificação e separação de responsabilidades.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        $agenda_page_id  = get_option( 'dps_agenda_page_id' );
        $charges_page_id = get_option( 'dps_charges_page_id' );

        $current_post            = is_singular() ? get_post() : null;
        $current_content         = $current_post ? (string) $current_post->post_content : '';
        $has_agenda_shortcode    = $current_post ? has_shortcode( $current_content, 'dps_agenda_page' ) : false;
        $has_charges_shortcode   = $current_post ? has_shortcode( $current_content, 'dps_charges_notes' ) : false;
        $is_agenda_target_page   = $agenda_page_id && is_page( $agenda_page_id );
        $is_charges_target_page  = $charges_page_id && is_page( $charges_page_id );
        
        // Agenda page: carrega CSS e scripts da agenda
        if ( $is_agenda_target_page || $has_agenda_shortcode ) {
            wp_enqueue_style(
                'dps-base-style',
                DPS_BASE_URL . 'assets/css/dps-base.css',
                [],
                DPS_BASE_VERSION
            );
            wp_enqueue_style(
                'dps-form-validation',
                DPS_BASE_URL . 'assets/css/dps-form-validation.css',
                [],
                DPS_BASE_VERSION
            );
            wp_enqueue_script(
                'dps-base-script',
                DPS_BASE_URL . 'assets/js/dps-base.js',
                [ 'jquery' ],
                DPS_BASE_VERSION,
                true
            );
            wp_enqueue_script(
                'dps-appointment-form',
                DPS_BASE_URL . 'assets/js/dps-appointment-form.js',
                [ 'jquery' ],
                DPS_BASE_VERSION,
                true
            );
            wp_enqueue_script(
                'dps-form-validation',
                DPS_BASE_URL . 'assets/js/dps-form-validation.js',
                [],
                DPS_BASE_VERSION,
                true
            );
            // CSS da agenda (extraído do inline para arquivo dedicado)
            wp_enqueue_style(
                'dps-agenda-addon-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/agenda-addon.css',
                [],
                '1.1.0' 
            );
            
            // Modal de serviços (precisa ser carregado antes do agenda-addon.js)
            wp_enqueue_script( 
                'dps-services-modal', 
                plugin_dir_url( __FILE__ ) . 'assets/js/services-modal.js', 
                [ 'jquery' ], 
                '1.0.0', 
                true 
            );
            
            // Script principal da agenda (atualização de status e interações)
            wp_enqueue_script( 
                'dps-agenda-addon', 
                plugin_dir_url( __FILE__ ) . 'assets/js/agenda-addon.js', 
                [ 'jquery', 'dps-services-modal', 'dps-base-script', 'dps-appointment-form', 'dps-form-validation' ], 
                '1.4.0', 
                true 
            );
            
            wp_localize_script( 'dps-appointment-form', 'dpsAppointmentData', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'dps_action' ),
                'appointmentId' => 0,
                'l10n' => [
                    'loadingTimes'    => __( 'Carregando horários...', 'dps-agenda-addon' ),
                    'selectTime'      => __( 'Selecione um horário', 'dps-agenda-addon' ),
                    'noTimes'         => __( 'Nenhum horário disponível para esta data', 'dps-agenda-addon' ),
                    'selectClient'    => __( 'Selecione um cliente', 'dps-agenda-addon' ),
                    'selectPet'       => __( 'Selecione pelo menos um pet', 'dps-agenda-addon' ),
                    'selectDate'      => __( 'Selecione uma data', 'dps-agenda-addon' ),
                    'selectTimeSlot'  => __( 'Selecione um horário', 'dps-agenda-addon' ),
                    'pastDate'        => __( 'A data não pode ser anterior a hoje', 'dps-agenda-addon' ),
                    'saving'          => __( 'Salvando...', 'dps-agenda-addon' ),
                    'loadError'       => __( 'Erro ao carregar horários', 'dps-agenda-addon' ),
                    'formErrorsTitle' => __( 'Por favor, corrija os seguintes erros:', 'dps-agenda-addon' ),
                ],
            ] );

            wp_localize_script( 'dps-base-script', 'dpsBaseData', [
                'restUrl'     => esc_url_raw( rest_url( 'dps/v1/pets' ) ),
                'restNonce'   => wp_create_nonce( 'wp_rest' ),
                'petsPerPage' => DPS_BASE_PETS_PER_PAGE,
            ] );
            wp_localize_script( 'dps-base-script', 'dpsBaseL10n', [
                'summarySingle'     => __( 'Pet selecionado: %s', 'dps-agenda-addon' ),
                'summaryMultiple'   => __( '%d pets selecionados: %s', 'dps-agenda-addon' ),
                'selectPetWarning'  => __( 'Selecione pelo menos um pet para o agendamento.', 'dps-agenda-addon' ),
                'historySummary'    => __( '%1$s atendimentos filtrados. Total estimado: R$ %2$s.', 'dps-agenda-addon' ),
                'historyEmpty'      => __( 'Nenhum atendimento corresponde aos filtros aplicados.', 'dps-agenda-addon' ),
                'historyExportEmpty'=> __( 'Nenhum atendimento visível para exportar.', 'dps-agenda-addon' ),
                'historyExportFileName' => __( 'historico-atendimentos-%s.csv', 'dps-agenda-addon' ),
                'pendingTitle'          => __( 'Pagamentos em aberto para %s.', 'dps-agenda-addon' ),
                'pendingGenericTitle'   => __( 'Este cliente possui pagamentos pendentes.', 'dps-agenda-addon' ),
                'pendingItem'           => __( '%1$s: R$ %2$s – %3$s', 'dps-agenda-addon' ),
                'pendingItemNoDate'     => __( 'R$ %1$s – %2$s', 'dps-agenda-addon' ),
                'petSearchPlaceholder'  => __( 'Buscar pets por nome, tutor ou raça', 'dps-agenda-addon' ),
                'petLoadMore'           => __( 'Carregar mais pets', 'dps-agenda-addon' ),
                'petLoading'            => __( 'Carregando pets...', 'dps-agenda-addon' ),
                'petNoResults'          => __( 'Nenhum pet encontrado para o filtro atual.', 'dps-agenda-addon' ),
                'savingText'            => __( 'Salvando...', 'dps-agenda-addon' ),
                'selectedSingle'        => __( 'selecionado', 'dps-agenda-addon' ),
                'selectedMultiple'      => __( 'selecionados', 'dps-agenda-addon' ),
            ] );
            wp_localize_script( 'dps-form-validation', 'dpsFormL10n', [
                'generic'     => [
                    'formErrorsTitle' => __( 'Por favor, corrija os campos destacados:', 'dps-agenda-addon' ),
                ],
                'client'      => [
                    'nameRequired'  => __( 'O campo Nome é obrigatório.', 'dps-agenda-addon' ),
                    'phoneRequired' => __( 'O campo Telefone / WhatsApp é obrigatório.', 'dps-agenda-addon' ),
                ],
                'pet'         => [
                    'nameRequired'    => __( 'Informe o nome do pet.', 'dps-agenda-addon' ),
                    'ownerRequired'   => __( 'Selecione o cliente (tutor) do pet.', 'dps-agenda-addon' ),
                    'speciesRequired' => __( 'Selecione a espécie do pet.', 'dps-agenda-addon' ),
                    'sexRequired'     => __( 'Selecione o sexo do pet.', 'dps-agenda-addon' ),
                    'sizeRequired'    => __( 'Selecione o porte do pet.', 'dps-agenda-addon' ),
                ],
                'appointment' => [
                    'clientRequired'    => __( 'Selecione um cliente para o agendamento.', 'dps-agenda-addon' ),
                    'petRequired'       => __( 'Selecione pelo menos um pet.', 'dps-agenda-addon' ),
                    'dateRequired'      => __( 'Selecione uma data para o agendamento.', 'dps-agenda-addon' ),
                    'timeRequired'      => __( 'Selecione um horário para o agendamento.', 'dps-agenda-addon' ),
                    'frequencyRequired' => __( 'Selecione a frequência da assinatura.', 'dps-agenda-addon' ),
                    'errorTitle'        => __( 'Corrija os erros para continuar.', 'dps-agenda-addon' ),
                ],
            ] );

            wp_localize_script( 'dps-agenda-addon', 'DPS_AG_Addon', [
                'ajax'          => admin_url( 'admin-ajax.php' ),
                'nonce_status'  => wp_create_nonce( 'dps_update_status' ),
                'nonce_services'=> wp_create_nonce( 'dps_get_services_details' ),
                'nonce_export'  => wp_create_nonce( 'dps_agenda_export_csv' ),
                'nonce_export_pdf' => wp_create_nonce( 'dps_agenda_export_pdf' ),
                // UX-1: Nonce para ações rápidas
                'nonce_quick_action' => wp_create_nonce( 'dps_agenda_quick_action' ),
                // CONF-2: Nonce para confirmação
                'nonce_confirmation' => wp_create_nonce( 'dps_agenda_confirmation' ),
                // FASE 3: Nonce para TaxiDog
                'nonce_taxidog'      => wp_create_nonce( 'dps_agenda_taxidog' ),
                // FASE 4: Nonce para capacidade
                'nonce_capacity'     => wp_create_nonce( 'dps_agenda_capacity' ),
                // FASE 5: Nonce para reenvio de pagamento
                'nonce_resend_payment' => wp_create_nonce( 'dps_agenda_resend_payment' ),
                // FASE 5: Nonces para funcionalidades administrativas avançadas
                'nonce_bulk'      => wp_create_nonce( 'dps_bulk_actions' ),
                'nonce_reschedule'=> wp_create_nonce( 'dps_quick_reschedule' ),
                'nonce_history'   => wp_create_nonce( 'dps_appointment_history' ),
                'nonce_modal_form'=> wp_create_nonce( 'dps_modal_appointment' ),
                'nonce_kpis'      => wp_create_nonce( 'dps_admin_kpis' ),
                'statuses'      => [
                    'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
                    'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
                    'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                    'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
                ],
                'messages'      => [
                    'updating' => __( 'Atualizando status...', 'dps-agenda-addon' ),
                    'updated'  => __( 'Status atualizado!', 'dps-agenda-addon' ),
                    'error'    => __( 'Não foi possível atualizar o status.', 'dps-agenda-addon' ),
                    'versionConflict' => __( 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.', 'dps-agenda-addon' ),
                    'export'   => __( 'Exportar', 'dps-agenda-addon' ),
                    // FASE 5: Mensagens para funcionalidades administrativas
                    'selected_singular' => __( 'selecionado', 'dps-agenda-addon' ),
                    'selected_plural'   => __( 'selecionados', 'dps-agenda-addon' ),
                    'bulk_confirm'      => __( 'Deseja alterar o status dos agendamentos selecionados?', 'dps-agenda-addon' ),
                    'reschedule_title'  => __( 'Reagendar Agendamento', 'dps-agenda-addon' ),
                    'new_date'          => __( 'Nova data', 'dps-agenda-addon' ),
                    'new_time'          => __( 'Novo horário', 'dps-agenda-addon' ),
                    'cancel'            => __( 'Cancelar', 'dps-agenda-addon' ),
                    'save'              => __( 'Salvar', 'dps-agenda-addon' ),
                    'saving'            => __( 'Salvando...', 'dps-agenda-addon' ),
                    'fill_all_fields'   => __( 'Preencha todos os campos.', 'dps-agenda-addon' ),
                    'no_history'        => __( 'Sem histórico de alterações.', 'dps-agenda-addon' ),
                    'history_title'     => __( 'Histórico de Alterações', 'dps-agenda-addon' ),
                    'action_created'    => __( 'Criado', 'dps-agenda-addon' ),
                    'action_status_change' => __( 'Status alterado', 'dps-agenda-addon' ),
                    'action_rescheduled'   => __( 'Reagendado', 'dps-agenda-addon' ),
                    'modalTitle'      => __( 'Novo agendamento', 'dps-agenda-addon' ),
                    'formLoading'     => __( 'Carregando formulário de agendamento...', 'dps-agenda-addon' ),
                    'saveError'       => __( 'Não foi possível salvar o agendamento.', 'dps-agenda-addon' ),
                    'close'           => __( 'Fechar', 'dps-agenda-addon' ),
                ],
                'reloadDelay'  => 700,
            ] );
        }

        // Charges/notes page: pode precisar de estilos extras
        if ( $is_charges_target_page || $has_charges_shortcode ) {
            // carregue CSS para tabelas se necessário; podemos reutilizar estilos de dps-table se o tema os define.
        }
    }

    /**
     * Renderiza o conteúdo do shortcode [dps_agenda_page].
     */
    public function render_agenda_shortcode() {
        ob_start();
        /*
         * Verifica permissão: somente administradores (capacidade manage_options).
         * Anteriormente, funcionários também tinham acesso à agenda, mas por questões
         * de segurança e a pedido do cliente, o acesso agora é restrito aos
         * administradores. Caso o usuário não esteja logado ou não possua a
         * capacidade de administrador, exibimos um link de login.
         */
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            $login_url = wp_login_url( DPS_URL_Builder::safe_get_permalink() );
            return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
        }
        // Nenhum controle adicional de cookies é necessário; o acesso é controlado por permissões do usuário.
        // Wrapper da agenda (CSS agora carregado de arquivo externo via enqueue_assets)
        echo '<div class="dps-agenda-wrapper">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo DPS_Message_Helper::display_messages();
        // Acesso permitido: mostrar agenda
        // Filtro de data e visualização
        $selected_date = isset( $_GET['dps_date'] ) ? sanitize_text_field( $_GET['dps_date'] ) : '';
        if ( ! $selected_date ) {
            $selected_date = current_time( 'Y-m-d' );
        }
        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        // Determine if we are in a weekly list or calendar view. Both share similar navigation logic.
        $is_week_view = ( $view === 'week' || $view === 'calendar' );
        // Exibe todos os atendimentos? Se show_all=1, ignoramos view e data para a listagem principal
        $show_all = isset( $_GET['show_all'] ) ? sanitize_text_field( $_GET['show_all'] ) : '';
        // Filtros globais
        $filter_client            = isset( $_GET['filter_client'] ) ? intval( $_GET['filter_client'] ) : 0;
        $filter_status            = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $filter_service           = isset( $_GET['filter_service'] ) ? intval( $_GET['filter_service'] ) : 0;
        $filter_pending_payment   = isset( $_GET['filter_pending_payment'] ) ? sanitize_text_field( $_GET['filter_pending_payment'] ) : '';
        $filter_staff             = isset( $_GET['filter_staff'] ) ? intval( $_GET['filter_staff'] ) : 0;

        $view_labels = [
            'day'      => __( 'Visão do dia', 'dps-agenda-addon' ),
            'week'     => __( 'Visão semanal', 'dps-agenda-addon' ),
            'calendar' => __( 'Calendário mensal', 'dps-agenda-addon' ),
        ];
        $current_view_label = isset( $view_labels[ $view ] ) ? $view_labels[ $view ] : __( 'Agenda', 'dps-agenda-addon' );

        $range_label = '';
        $selected_date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( $selected_date_obj ) {
            if ( $is_week_view ) {
                $week_start = ( clone $selected_date_obj )->modify( 'monday this week' );
                $week_end   = ( clone $selected_date_obj )->modify( 'sunday this week' );
                $range_label = sprintf(
                    '%s – %s',
                    date_i18n( 'd/m', $week_start->getTimestamp() ),
                    date_i18n( 'd/m', $week_end->getTimestamp() )
                );
            } elseif ( $view === 'calendar' ) {
                $range_label = ucfirst( date_i18n( 'F Y', $selected_date_obj->getTimestamp() ) );
            } else {
                $range_label = date_i18n( 'd/m/Y', $selected_date_obj->getTimestamp() );
            }
        }

        $context_hint = $show_all
            ? __( 'Listando todos os agendamentos futuros.', 'dps-agenda-addon' )
            : __( 'Use a navegação para avançar rapidamente pela agenda.', 'dps-agenda-addon' );
        // Título simples da agenda
        echo '<header class="dps-agenda-header">';
        echo '<div class="dps-agenda-title">';
        echo '<h3>' . __( 'Agenda de Atendimentos', 'dps-agenda-addon' ) . '</h3>';
        echo '</div>';
        echo '</header>';
        
        // Links para dia/semana anterior/próximo, preservando filtros
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( $is_week_view ) {
            // Para semana, calcula datas sete dias antes e depois
            $prev_date = $date_obj ? $date_obj->modify( '-7 days' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+7 days' )->format( 'Y-m-d' ) : '';
        } else {
            // Para dia, calcula dias anterior e seguinte
            $prev_date = $date_obj ? $date_obj->modify( '-1 day' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+1 day' )->format( 'Y-m-d' ) : '';
        }
        // Base URL sem parâmetros de data, visualização ou modo 'show_all'
        // Remove também show_all para que a navegação saia do modo "Todos os Atendimentos"
        $base_url = remove_query_arg( [ 'dps_date', 'view', 'show_all' ] );
        // Mantém filtros e outros parâmetros, exceto show_all (que será tratado separadamente)
        $current_args = $_GET;
        unset( $current_args['dps_date'] );
        unset( $current_args['view'] );
        // Não propagamos show_all em links de navegação padrão
        $nav_args = $current_args;
        unset( $nav_args['show_all'] );
        // Botões anterior e seguinte com filtros
        // Ao gerar links, não propague show_all (nav_args não contém show_all)
        $prev_args = array_merge( $nav_args, [ 'dps_date' => $prev_date, 'view' => $view ] );
        $next_args = array_merge( $nav_args, [ 'dps_date' => $next_date, 'view' => $view ] );
        
        // UX-4: Navegação e filtros consolidados em até 2 linhas
        echo '<div class="dps-agenda-controls-wrapper">';
        
        // Linha 1: Navegação principal e data
        echo '<div class="dps-agenda-nav dps-agenda-nav--primary">';
        
        // Grupo 1: Data e navegação temporal
        echo '<div class="dps-agenda-nav-group dps-agenda-nav-group--date">';
        
        // Data atual/selecionada como referência visual
        if ( ! $show_all ) {
            $date_display = date_i18n( 'd/m/Y', strtotime( $selected_date ) );
            echo '<span class="dps-current-date" title="' . esc_attr__( 'Data atual', 'dps-agenda-addon' ) . '">';
            echo '📅 <strong>' . esc_html( $date_display ) . '</strong>';
            echo '</span>';
        } else {
            echo '<span class="dps-current-date">';
            echo '📋 <strong>' . esc_html__( 'Todos os agendamentos', 'dps-agenda-addon' ) . '</strong>';
            echo '</span>';
        }
        
        // Navegação anterior/hoje/próximo
        echo '<div class="dps-date-nav">';
        echo '<a href="' . esc_url( add_query_arg( $prev_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--prev" title="' . esc_attr( $is_week_view ? __( 'Ver semana anterior', 'dps-agenda-addon' ) : __( 'Ver dia anterior', 'dps-agenda-addon' ) ) . '">';
        echo '←';
        echo '</a>';
        
        $today = current_time( 'Y-m-d' );
        $today_args = array_merge( $current_args, [ 'dps_date' => $today, 'view' => $view ] );
        unset( $today_args['show_all'] );
        echo '<a href="' . esc_url( add_query_arg( $today_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--today" title="' . esc_attr__( 'Ver agendamentos de hoje', 'dps-agenda-addon' ) . '">';
        echo esc_html__( 'Hoje', 'dps-agenda-addon' );
        echo '</a>';
        
        echo '<a href="' . esc_url( add_query_arg( $next_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--next" title="' . esc_attr( $is_week_view ? __( 'Ver próxima semana', 'dps-agenda-addon' ) : __( 'Ver próximo dia', 'dps-agenda-addon' ) ) . '">';
        echo '→';
        echo '</a>';
        echo '</div>';
        echo '</div>';

        // Grupo 2: Visualizações compactas (Dia | Semana | Mês)
        echo '<div class="dps-agenda-nav-group dps-agenda-nav-group--views">';
        echo '<span class="dps-nav-label">' . esc_html__( 'Ver:', 'dps-agenda-addon' ) . '</span>';
        
        $view_buttons = [];
        
        // Botão Dia
        $day_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'day' ] );
        $day_active = ( $view === 'day' ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $day_args, $base_url ) ) . '" class="dps-view-btn' . $day_active . '" title="' . esc_attr__( 'Ver lista diária', 'dps-agenda-addon' ) . '">' . esc_html__( 'Dia', 'dps-agenda-addon' ) . '</a>';
        
        // Botão Semana
        $week_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'week' ] );
        $week_active = ( $view === 'week' ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $week_args, $base_url ) ) . '" class="dps-view-btn' . $week_active . '" title="' . esc_attr__( 'Ver lista semanal', 'dps-agenda-addon' ) . '">' . esc_html__( 'Semana', 'dps-agenda-addon' ) . '</a>';
        
        // Botão Mês/Calendário
        $cal_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'calendar' ] );
        $cal_active = ( $view === 'calendar' ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $cal_args, $base_url ) ) . '" class="dps-view-btn' . $cal_active . '" title="' . esc_attr__( 'Ver calendário mensal', 'dps-agenda-addon' ) . '">' . esc_html__( 'Mês', 'dps-agenda-addon' ) . '</a>';
        
        echo '<div class="dps-view-buttons">' . implode( '', $view_buttons ) . '</div>';
        echo '</div>';
        
        // Grupo 3: Ações principais
        echo '<div class="dps-agenda-nav-group dps-agenda-nav-group--actions">';
        
        // Botão Novo Agendamento
        $base_page_id = get_option( 'dps_base_page_id' );
        $new_appt_page = '';
        if ( $base_page_id ) {
            $permalink = get_permalink( $base_page_id );
            if ( $permalink && is_string( $permalink ) ) {
                $new_appt_page = $permalink;
            }
        }
        if ( empty( $new_appt_page ) ) {
            $new_appt_page = self::get_current_page_url();
        }
        if ( $new_appt_page ) {
            $new_appt_url = add_query_arg(
                [
                    'tab'    => 'agendas',
                    'action' => 'new',
                ],
                $new_appt_page
            );
            $new_button_attrs = ' data-dps-open-appointment-modal="true"';
            if ( $filter_client ) {
                $new_button_attrs .= ' data-pref-client="' . esc_attr( $filter_client ) . '"';
            }
            echo '<a href="' . esc_url( $new_appt_url ) . '" class="button dps-btn dps-btn--primary" title="' . esc_attr__( 'Criar novo agendamento', 'dps-agenda-addon' ) . '"' . $new_button_attrs . '>';
            echo '➕ ' . esc_html__( 'Novo', 'dps-agenda-addon' );
            echo '</a>';
        } else {
            echo '<button type="button" class="button dps-btn dps-btn--primary" data-dps-open-appointment-modal="true" title="' . esc_attr__( 'Criar novo agendamento', 'dps-agenda-addon' ) . '">';
            echo '➕ ' . esc_html__( 'Novo', 'dps-agenda-addon' );
            echo '</button>';
        }
        
        // Botão Exportar PDF
        $export_date = $show_all ? '' : $selected_date;
        echo '<button type="button" class="button dps-btn dps-btn--ghost dps-export-pdf-btn" data-date="' . esc_attr( $export_date ) . '" data-view="' . esc_attr( $view ) . '" title="' . esc_attr__( 'Exportar agenda para PDF', 'dps-agenda-addon' ) . '">';
        echo '📄';
        echo '</button>';
        
        echo '</div>';
        echo '</div>';

        $all_view_args = array_merge( $nav_args, [ 'show_all' => '1' ] );
        $focused_view_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => $view ] );

        echo '<div class="dps-agenda-context-bar">';
        echo '<div class="dps-context-meta">';
        echo '<span class="dps-context-pill dps-context-pill--accent">' . esc_html( $current_view_label ) . '</span>';
        if ( $range_label ) {
            echo '<span class="dps-context-pill">' . esc_html( $range_label ) . '</span>';
        }
        echo '<span class="dps-context-hint">' . esc_html( $context_hint ) . '</span>';
        echo '</div>';

        echo '<div class="dps-context-actions">';
        if ( $show_all ) {
            echo '<a href="' . esc_url( add_query_arg( $focused_view_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost dps-context-btn">';
            echo '↩️ ' . esc_html__( 'Voltar para data', 'dps-agenda-addon' );
            echo '</a>';
        } else {
            echo '<a href="' . esc_url( add_query_arg( $all_view_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft dps-context-btn">';
            echo '📋 ' . esc_html__( 'Ver agenda completa', 'dps-agenda-addon' );
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>'; // Fim da .dps-agenda-controls-wrapper
        
        // Inicializa variáveis de filtro (já foram obtidas acima no formulário unificado)
        if ( $view === 'calendar' ) {
            $filter_client  = 0;
            $filter_status  = '';
            $filter_service = 0;
            $filter_staff   = 0;
        }
        
        // Carrega agendamentos conforme visualização ou modo "todos"
        $appointments = [];
        if ( $show_all ) {
            // Carrega todos os agendamentos a partir de hoje (inclusive)
            // PERFORMANCE: Implementada paginação com limite de 50 registros por página
            $today = current_time( 'Y-m-d' );
            $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
            
            $appointments['todos'] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => self::APPOINTMENTS_PER_PAGE,
                'paged'          => $paged,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_date',
                        'value'   => $today,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                ],
                'orderby'        => [ 'appointment_date' => 'ASC', 'appointment_time' => 'ASC' ],
                'meta_key'       => 'appointment_date',
                'order'          => 'ASC',
            ] );
        } elseif ( $view === 'week' ) {
            // Limite diário configurável via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // Calcula início (segunda-feira) da semana contendo $selected_date
            $dt      = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $weekday = (int) $dt->format( 'N' ); // 1 = seg, 7 = dom
            $start_of_week = clone $dt;
            $start_of_week->modify( '-' . ( $weekday - 1 ) . ' days' );
            for ( $i = 0; $i < 7; $i++ ) {
                $day_date = clone $start_of_week;
                $day_date->modify( '+' . $i . ' days' );
                $appointments[ $day_date->format( 'Y-m-d' ) ] = get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => $daily_limit,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [
                            'key'     => 'appointment_date',
                            'value'   => $day_date->format( 'Y-m-d' ),
                            'compare' => '=',
                        ],
                    ],
                    'orderby'        => 'meta_value',
                    'meta_key'       => 'appointment_time',
                    'order'          => 'ASC',
                    'no_found_rows'  => true, // PERFORMANCE: não conta total
                ] );
            }
        } else {
            // Limite diário configurável via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // Visualização diária
            $appointments[ $selected_date ] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => $daily_limit,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_date',
                        'value'   => $selected_date,
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_time',
                'order'          => 'ASC',
                'no_found_rows'  => true, // PERFORMANCE: não conta total
            ] );
        }
        
        // FASE 5: Filtrar pagamentos pendentes (pós-query para usar helper)
        if ( ! empty( $filter_pending_payment ) && $filter_pending_payment === '1' ) {
            foreach ( $appointments as $date => $appts ) {
                if ( is_array( $appts ) ) {
                    $appointments[ $date ] = array_filter( $appts, function( $appt ) {
                        return DPS_Agenda_Payment_Helper::has_pending_payment( $appt->ID );
                    } );
                }
            }
        }
        
        // FASE 2 Groomers: Filtrar por profissional
        if ( $filter_staff > 0 ) {
            foreach ( $appointments as $date => $appts ) {
                if ( is_array( $appts ) ) {
                    $appointments[ $date ] = array_filter( $appts, function( $appt ) use ( $filter_staff ) {
                        $staff_ids = get_post_meta( $appt->ID, '_dps_groomers', true );
                        if ( ! is_array( $staff_ids ) ) {
                            return false;
                        }
                        return in_array( $filter_staff, array_map( 'intval', $staff_ids ) );
                    } );
                }
            }
        }
        
        // FASE 4: Renderiza calendário mensal se view=calendar
        if ( $view === 'calendar' ) {
            $this->render_calendar_view( $selected_date );
            echo '</div>';
            return ob_get_clean();
        }
        
        // FASE 6: Sistema de abas para reorganizar visualização
        $current_tab = isset( $_GET['agenda_tab'] ) ? sanitize_text_field( $_GET['agenda_tab'] ) : 'visao-rapida';
        
        echo '<div class="dps-agenda-tabs-wrapper">';
        
        // Cabeçalho das abas com título e descrição
        echo '<div class="dps-agenda-tabs-header">';
        echo '<h3 class="dps-agenda-tabs-title">📋 ' . esc_html__( 'Lista de Atendimentos', 'dps-agenda-addon' ) . '</h3>';
        echo '</div>';
        
        echo '<nav class="dps-agenda-tabs-nav" role="tablist">';
        
        // Definir abas com ícones e descrições
        $tabs = [
            'visao-rapida' => [
                'label' => __( 'Visão Rápida', 'dps-agenda-addon' ),
                'icon'  => '👁️',
                'desc'  => __( 'Check rápido de status', 'dps-agenda-addon' ),
            ],
            'operacao'     => [
                'label' => __( 'Operação', 'dps-agenda-addon' ),
                'icon'  => '⚙️',
                'desc'  => __( 'Ações e pagamentos', 'dps-agenda-addon' ),
            ],
            'detalhes'     => [
                'label' => __( 'Detalhes', 'dps-agenda-addon' ),
                'icon'  => '📍',
                'desc'  => __( 'Logística e TaxiDog', 'dps-agenda-addon' ),
            ],
        ];
        
        foreach ( $tabs as $tab_id => $tab_data ) {
            $is_active = ( $current_tab === $tab_id );
            $tab_class = 'dps-agenda-tab-button' . ( $is_active ? ' dps-agenda-tab-button--active' : '' );
            
            echo '<button type="button" class="' . esc_attr( $tab_class ) . '" data-tab="' . esc_attr( $tab_id ) . '" role="tab" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" aria-controls="dps-tab-content-' . esc_attr( $tab_id ) . '" title="' . esc_attr( $tab_data['desc'] ) . '">';
            echo '<span class="dps-tab-icon">' . $tab_data['icon'] . '</span>';
            echo '<span class="dps-tab-label">' . esc_html( $tab_data['label'] ) . '</span>';
            echo '<span class="dps-tab-desc">' . esc_html( $tab_data['desc'] ) . '</span>';
            echo '</button>';
        }
        
        echo '</nav>';
        
        // Container para conteúdo das abas
        echo '<div class="dps-agenda-tabs-content">';
        
        // Inicializa variáveis e coleta dados de todos os dias primeiro
        $has_any = false;
        $all_filtered_appointments = []; // FASE 7: Armazena todos os appointments filtrados para relatório no final
        $days_data = []; // Armazena dados processados de cada dia para renderização posterior
        $column_labels = [
            'date'          => __( 'Data', 'dps-agenda-addon' ),
            'time'          => __( 'Hora', 'dps-agenda-addon' ),
            'pet'           => __( 'Pet (Cliente)', 'dps-agenda-addon' ),
            'service'       => __( 'Serviço', 'dps-agenda-addon' ),
            'status'        => __( 'Status', 'dps-agenda-addon' ),
            'payment'       => __( 'Pagamento', 'dps-agenda-addon' ),
            'map'           => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation'  => __( 'Confirmação', 'dps-agenda-addon' ),
            'charge'        => __( 'Cobrança', 'dps-agenda-addon' ),
        ];
        
        // FASE 1: Pré-processa todos os dias para coletar dados filtrados
        foreach ( $appointments as $day => $appts ) {
            $has_any = $has_any || ! empty( $appts );
            
            // Define título do bloco
            if ( $show_all ) {
                $day_title = __( 'Todos os Atendimentos', 'dps-agenda-addon' );
            } elseif ( $view === 'week' ) {
                $day_dt = DateTime::createFromFormat( 'Y-m-d', $day );
                $day_title = ucfirst( date_i18n( 'l', $day_dt->getTimestamp() ) ) . ' ' . date_i18n( 'd-m', $day_dt->getTimestamp() );
            } else {
                $day_title = __( 'Agendamentos do dia', 'dps-agenda-addon' );
            }
            
            // Se não houver appointments para o dia, pula se semanal
            if ( empty( $appts ) && $view === 'week' ) {
                continue;
            }
            
            // PERFORMANCE: Pre-cache metadata para todos os agendamentos do dia
            if ( ! empty( $appts ) ) {
                $appointment_ids = wp_list_pluck( $appts, 'ID' );
                update_meta_cache( 'post', $appointment_ids );
            }
            
            // Aplica filtros de cliente, status e serviço
            $filtered = [];
            foreach ( $appts as $appt ) {
                $match = true;
                if ( $filter_client ) {
                    $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                    if ( intval( $cid ) !== $filter_client ) {
                        $match = false;
                    }
                }
                if ( $filter_status ) {
                    $st_val = get_post_meta( $appt->ID, 'appointment_status', true );
                    if ( ! $st_val ) { $st_val = 'pendente'; }
                    if ( $st_val !== $filter_status ) {
                        $match = false;
                    }
                }
                if ( $filter_service ) {
                    $service_ids_meta = get_post_meta( $appt->ID, 'appointment_services', true );
                    if ( ! is_array( $service_ids_meta ) || ! in_array( $filter_service, $service_ids_meta ) ) {
                        $match = false;
                    }
                }
                if ( $match ) {
                    $filtered[] = $appt;
                }
            }
            
            // Acumula todos os appointments filtrados para relatório no final
            $all_filtered_appointments = array_merge( $all_filtered_appointments, $filtered );
            
            // Classificar por status: pendente vs finalizado
            $upcoming  = [];
            $completed = [];
            foreach ( $filtered as $appt ) {
                $st = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $st ) {
                    $st = 'pendente';
                }
                if ( $st === 'pendente' ) {
                    $upcoming[] = $appt;
                } else {
                    $completed[] = $appt;
                }
            }
            
            // Armazena dados processados do dia
            $days_data[] = [
                'day'       => $day,
                'title'     => $day_title,
                'filtered'  => $filtered,
                'upcoming'  => $upcoming,
                'completed' => $completed,
            ];
        }
        
        // FASE 2: Verifica se deve agrupar por cliente (mantém comportamento antigo)
        $group_by_client = isset( $_GET['group_by_client'] ) && $_GET['group_by_client'] === '1';
        
        // FASE 3: Define funções de renderização (uma vez, fora do loop de dias)
        
        // Aba 1: Visão Rápida
        $render_table_tab1 = function( $apts, $heading ) use ( $column_labels ) {
            if ( empty( $apts ) ) {
                return;
            }
            
            // Pre-carregar posts relacionados
            $client_ids = [];
            $pet_ids    = [];
            foreach ( $apts as $appt ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $cid ) {
                    $client_ids[] = (int) $cid;
                }
                if ( $pid ) {
                    $pet_ids[] = (int) $pid;
                }
            }
            $related_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
            if ( ! empty( $related_ids ) ) {
                _prime_post_caches( $related_ids, false, false );
                update_meta_cache( 'post', $related_ids );
            }
            
            // Ordenação cronológica (próximo atendimento primeiro)
            usort(
                $apts,
                function( $a, $b ) {
                    $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                    $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                    $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                    $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                    $dt_a   = strtotime( trim( $date_a . ' ' . $time_a ) );
                    $dt_b   = strtotime( trim( $date_b . ' ' . $time_b ) );
                    if ( $dt_a === $dt_b ) {
                        return $a->ID <=> $b->ID;
                    }
                    return $dt_a <=> $dt_b; // ASC: próximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab1"><thead><tr>';
            echo '<th class="dps-select-all-wrapper"><input type="checkbox" class="dps-select-all dps-select-checkbox" title="' . esc_attr__( 'Selecionar todos', 'dps-agenda-addon' ) . '"></th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html( $column_labels['service'] ?? __( 'Serviços', 'dps-agenda-addon' ) ) . '</th>';
            echo '<th>' . esc_html( $column_labels['confirmation'] ?? __( 'Confirmação', 'dps-agenda-addon' ) ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                echo $this->render_appointment_row_tab1( $appt, $column_labels );
            }
            echo '</tbody></table>';
            echo '</div>';
        };
        
        // Aba 2: Operação
        $render_table_tab2 = function( $apts, $heading ) use ( $column_labels ) {
            if ( empty( $apts ) ) {
                return;
            }
            
            // Pre-carregar posts relacionados
            $client_ids = [];
            $pet_ids    = [];
            foreach ( $apts as $appt ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $cid ) {
                    $client_ids[] = (int) $cid;
                }
                if ( $pid ) {
                    $pet_ids[] = (int) $pid;
                }
            }
            $related_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
            if ( ! empty( $related_ids ) ) {
                _prime_post_caches( $related_ids, false, false );
                update_meta_cache( 'post', $related_ids );
            }
            
            // Ordenação cronológica (próximo atendimento primeiro)
            usort(
                $apts,
                function( $a, $b ) {
                    $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                    $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                    $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                    $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                    $dt_a   = strtotime( trim( $date_a . ' ' . $time_a ) );
                    $dt_b   = strtotime( trim( $date_b . ' ' . $time_b ) );
                    if ( $dt_a === $dt_b ) {
                        return $a->ID <=> $b->ID;
                    }
                    return $dt_a <=> $dt_b; // ASC: próximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab2"><thead><tr>';
            echo '<th class="dps-select-all-wrapper"><input type="checkbox" class="dps-select-all dps-select-checkbox" title="' . esc_attr__( 'Selecionar todos', 'dps-agenda-addon' ) . '"></th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Status do Serviço', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html( $column_labels['payment'] ?? __( 'Pagamento', 'dps-agenda-addon' ) ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                echo $this->render_appointment_row_tab2( $appt, $column_labels );
            }
            echo '</tbody></table>';
            echo '</div>';
        };
        
        // Aba 3: Detalhes
        $render_table_tab3 = function( $apts, $heading ) use ( $column_labels ) {
            if ( empty( $apts ) ) {
                return;
            }
            
            // Pre-carregar posts relacionados
            $client_ids = [];
            $pet_ids    = [];
            foreach ( $apts as $appt ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $cid ) {
                    $client_ids[] = (int) $cid;
                }
                if ( $pid ) {
                    $pet_ids[] = (int) $pid;
                }
            }
            $related_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
            if ( ! empty( $related_ids ) ) {
                _prime_post_caches( $related_ids, false, false );
                update_meta_cache( 'post', $related_ids );
            }
            
            // Ordenação cronológica (próximo atendimento primeiro)
            usort(
                $apts,
                function( $a, $b ) {
                    $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                    $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                    $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                    $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                    $dt_a   = strtotime( trim( $date_a . ' ' . $time_a ) );
                    $dt_b   = strtotime( trim( $date_b . ' ' . $time_b ) );
                    if ( $dt_a === $dt_b ) {
                        return $a->ID <=> $b->ID;
                    }
                    return $dt_a <=> $dt_b; // ASC: próximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab3"><thead><tr>';
            echo '<th class="dps-select-all-wrapper"><input type="checkbox" class="dps-select-all dps-select-checkbox" title="' . esc_attr__( 'Selecionar todos', 'dps-agenda-addon' ) . '"></th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>TaxiDog</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                echo $this->render_appointment_row_tab3( $appt, $column_labels );
            }
            echo '</tbody></table>';
            echo '</div>';
        };
        
        // FASE 4: Renderiza conteúdo das abas (uma única vez, fora do loop de dias)
        if ( $group_by_client && ! empty( $all_filtered_appointments ) ) {
            // Renderiza tabelas agrupadas por cliente (mantém comportamento antigo)
            $this->render_grouped_by_client( $all_filtered_appointments, $column_labels );
        } else {
            // Aba 1: Visão Rápida
            echo '<div id="dps-tab-content-visao-rapida" class="dps-tab-content' . ( $current_tab === 'visao-rapida' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel">';
            foreach ( $days_data as $day_info ) {
                // Exibe título do dia apenas em visualização semanal com múltiplos dias
                if ( $view === 'week' && count( $days_data ) > 1 ) {
                    echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                }
                $render_table_tab1( $day_info['upcoming'], __( 'Próximos Atendimentos', 'dps-agenda-addon' ) );
                $render_table_tab1( $day_info['completed'], __( 'Atendimentos Finalizados', 'dps-agenda-addon' ) );
            }
            echo '</div>';
            
            // Aba 2: Operação
            echo '<div id="dps-tab-content-operacao" class="dps-tab-content' . ( $current_tab === 'operacao' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel">';
            foreach ( $days_data as $day_info ) {
                if ( $view === 'week' && count( $days_data ) > 1 ) {
                    echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                }
                $render_table_tab2( $day_info['upcoming'], __( 'Próximos Atendimentos', 'dps-agenda-addon' ) );
                $render_table_tab2( $day_info['completed'], __( 'Atendimentos Finalizados', 'dps-agenda-addon' ) );
            }
            echo '</div>';
            
            // Aba 3: Detalhes
            echo '<div id="dps-tab-content-detalhes" class="dps-tab-content' . ( $current_tab === 'detalhes' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel">';
            foreach ( $days_data as $day_info ) {
                if ( $view === 'week' && count( $days_data ) > 1 ) {
                    echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                }
                $render_table_tab3( $day_info['upcoming'], __( 'Próximos Atendimentos', 'dps-agenda-addon' ) );
                $render_table_tab3( $day_info['completed'], __( 'Atendimentos Finalizados', 'dps-agenda-addon' ) );
            }
            echo '</div>';
        }
        
        if ( ! $has_any ) {
            echo '<p class="dps-agenda-empty" role="status">' . __( 'Nenhum agendamento.', 'dps-agenda-addon' ) . '</p>';
        }
        
        // Fecha container de tabs
        echo '</div>'; // .dps-agenda-tabs-content
        echo '</div>'; // .dps-agenda-tabs-wrapper
        
        // PERFORMANCE: Controles de paginação para modo "Todos os Atendimentos"
        if ( $show_all ) {
            $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
            $prev_page = max( 1, $paged - 1 );
            $next_page = $paged + 1;
            
            // Preserva parâmetros de filtro válidos na paginação
            // Sanitiza cada parâmetro para prevenir injeção de código
            $pagination_args = [
                'show_all' => '1',
                'dps_date' => $selected_date,
                'view'     => $view,
            ];
            
            // Adiciona filtros se definidos
            if ( $filter_client ) {
                $pagination_args['filter_client'] = $filter_client;
            }
            if ( $filter_status ) {
                $pagination_args['filter_status'] = $filter_status;
            }
            if ( $filter_service ) {
                $pagination_args['filter_service'] = $filter_service;
            }
            
            echo '<div class="dps-agenda-pagination" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">';
            
            if ( $paged > 1 ) {
                $pagination_args['paged'] = $prev_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo '← ' . esc_html__( 'Página anterior', 'dps-agenda-addon' );
                echo '</a>';
            }
            
            echo '<span class="dps-pagination-info" style="padding: 8px 15px; background: #f9fafb; border-radius: 4px;">';
            echo sprintf( esc_html__( 'Página %d', 'dps-agenda-addon' ), $paged );
            echo '</span>';
            
            // Só mostra "Próxima" se retornou o máximo de registros (indicando que pode haver mais)
            if ( ! empty( $appointments['todos'] ) && count( $appointments['todos'] ) >= self::APPOINTMENTS_PER_PAGE ) {
                $pagination_args['paged'] = $next_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo esc_html__( 'Próxima página', 'dps-agenda-addon' ) . ' →';
                echo '</a>';
            }
            
            echo '</div>';
        }
        
        // FASE 7: "Resumo do Dia" e "Relatório de Ocupação" movidos para o final da página
        // Renderiza Relatório de Ocupação como seção colapsável
        if ( ! $show_all && $view !== 'calendar' && ! empty( $all_filtered_appointments ) ) {
            $this->render_occupancy_report( $all_filtered_appointments, $selected_date, $is_week_view );
        }
        
        // Renderiza Dashboard de KPIs como seção colapsável no final
        if ( ! $show_all && $view !== 'calendar' ) {
            $this->render_admin_dashboard( $selected_date );
        }
        
        // FASE 5: Barra de ações em lote flutuante no rodapé (oculta inicialmente, exibida via JS ao selecionar itens)
        echo '<div class="dps-bulk-actions">';
        echo '<span class="dps-bulk-count">0 ' . esc_html__( 'selecionados', 'dps-agenda-addon' ) . '</span>';
        echo '<button type="button" class="dps-bulk-btn" data-action="update_status" data-status="' . esc_attr( self::STATUS_FINISHED ) . '">✅ ' . esc_html__( 'Finalizar', 'dps-agenda-addon' ) . '</button>';
        echo '<button type="button" class="dps-bulk-btn" data-action="update_status" data-status="' . esc_attr( self::STATUS_PAID ) . '">💰 ' . esc_html__( 'Marcar Pago', 'dps-agenda-addon' ) . '</button>';
        echo '<button type="button" class="dps-bulk-btn dps-bulk-btn--danger" data-action="update_status" data-status="' . esc_attr( self::STATUS_CANCELED ) . '">❌ ' . esc_html__( 'Cancelar', 'dps-agenda-addon' ) . '</button>';
        echo '<button type="button" class="dps-bulk-close">✕ ' . esc_html__( 'Limpar seleção', 'dps-agenda-addon' ) . '</button>';
        echo '</div>';
        
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Shortcode deprecated que redireciona para o Finance Add-on.
     *
     * @deprecated 1.1.0 Use [dps_fin_docs] do Finance Add-on
     * @return string HTML do shortcode ou mensagem de depreciação
     */
    public function render_charges_notes_shortcode_deprecated() {
        _deprecated_function( 'Shortcode [dps_charges_notes]', '1.1.0', '[dps_fin_docs] (Finance Add-on)' );
        
        // Tenta redirecionar para shortcode do Finance
        if ( shortcode_exists( 'dps_fin_docs' ) ) {
            return do_shortcode( '[dps_fin_docs]' );
        }
        
        // Se Finance não estiver ativo, exibe mensagem
        return '<div class="notice notice-warning" style="padding: 20px; margin: 20px 0; background: #fff3cd; border-left: 4px solid #ffc107;">' .
               '<p><strong>' . esc_html__( 'Atenção:', 'dps-agenda-addon' ) . '</strong> ' .
               esc_html__( 'Este shortcode foi movido para o Finance Add-on. Por favor, use [dps_fin_docs] ou ative o Finance Add-on.', 'dps-agenda-addon' ) .
               '</p></div>';
    }

    /**
     * AJAX handler para atualizar o status de um agendamento.
     *
     * Espera campos 'id' e 'status' via POST. Somente usuários logados podem executar.
     */
    public function update_status_ajax() {
        // Verifica permissão do usuário. Apenas administradores podem alterar o status.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        // Verifica nonce para evitar CSRF. O nonce deve ser enviado no campo 'nonce'.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_update_status' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        $id     = intval( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $version = isset( $_POST['version'] ) ? intval( $_POST['version'] ) : 0;
        // Aceita variações do texto "finalizado e pago" além do slug sem espaços
        if ( $status === 'finalizado e pago' ) {
            $status = 'finalizado_pago';
        }
        $valid_statuses = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];
        if ( ! $id || ! in_array( $status, $valid_statuses, true ) || $version < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        $current_version = intval( get_post_meta( $id, '_dps_appointment_version', true ) );

        if ( $current_version < 1 ) {
            $current_version = 1;
            update_post_meta( $id, '_dps_appointment_version', $current_version );
        }

        if ( $version !== $current_version ) {
            wp_send_json_error(
                [
                    'message'    => __( 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.', 'dps-agenda-addon' ),
                    'error_code' => 'version_conflict',
                ]
            );
        }
        // Atualiza meta de status. Remove entradas anteriores para garantir que não haja valores duplicados.
        delete_post_meta( $id, 'appointment_status' );
        add_post_meta( $id, 'appointment_status', $status, true );
        $new_version = $current_version + 1;
        update_post_meta( $id, '_dps_appointment_version', $new_version );

        // AUDITORIA: Registra mudança de status no log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status alterado para "%s" por usuário #%d',
                    $id,
                    $status,
                    get_current_user_id()
                ),
                [
                    'appointment_id' => $id,
                    'new_status'     => $status,
                    'user_id'        => get_current_user_id(),
                    'version'        => $new_version,
                ],
                'agenda'
            );
        }

        // A sincronização financeira é feita automaticamente pelo Finance Add-on via hook updated_post_meta
        // O Finance monitora mudanças em appointment_status e cria/atualiza transações conforme necessário
        // Não é necessário manipular dps_transacoes diretamente aqui

        // Após atualizar a transação, aciona o hook dps_base_after_save_appointment para que
        // outros add-ons (como o de pagamentos) possam processar o agendamento finalizado.
        // Isso garante que o link de pagamento seja criado automaticamente mesmo quando
        // o status é alterado manualmente pela agenda.
        do_action( 'dps_base_after_save_appointment', $id, 'simple' );

        // TODO: Implementar notificação via WhatsApp quando necessário
        // Atualmente o código abaixo usa variáveis não definidas ($client_id, $pet_post, $date, $valor)
        // e precisa ser refatorado para obter esses dados do agendamento

        wp_send_json_success(
            [
                'message' => __( 'Status atualizado.', 'dps-agenda-addon' ),
                'status'  => $status,
                'version' => $new_version,
            ]
        );
    }

    /**
     * AJAX handler para retornar detalhes de serviços de um agendamento.
     * Retorna lista de serviços (nome e preço) para o agendamento.
     *
     * @deprecated 1.1.0 Lógica movida para Services Add-on (DPS_Services_API).
     *                   Mantido por compatibilidade, mas delega para API quando disponível.
     */
    public function get_services_details_ajax() {
        // Apenas administradores podem consultar detalhes de serviços. Garante que usuários não
        // autenticados ou sem permissão não exponham dados. Caso contrário, retorna erro.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        // SEGURANÇA: Verificação de nonce obrigatória para prevenir CSRF
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_get_services_details' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        $id_param  = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        if ( ! $id_param ) {
            // Compatibilidade: aceita "id" como fallback
            $id_param = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        }
        if ( ! $id_param ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'dps-agenda-addon' ) ] );
        }
        
        // Busca observações do agendamento
        $appt_notes = get_post_meta( $id_param, 'appointment_notes', true );
        
        // Verifica se TaxiDog foi solicitado e seu valor
        $taxidog_requested = get_post_meta( $id_param, 'appointment_taxidog', true );
        $taxidog_price = 0;
        if ( $taxidog_requested ) {
            $taxidog_price = (float) get_post_meta( $id_param, 'appointment_taxidog_price', true );
        }

        // Delega para Services API se disponível (recomendado)
        if ( class_exists( 'DPS_Services_API' ) ) {
            $details = DPS_Services_API::get_services_details( $id_param );
            $services = $details['services'];
            
            // Adiciona TaxiDog como serviço se foi solicitado
            if ( $taxidog_requested ) {
                $services[] = [
                    'name'       => __( 'TaxiDog', 'dps-agenda-addon' ),
                    'price'      => $taxidog_price,
                    'is_taxidog' => true,
                ];
            }
            
            wp_send_json_success( [
                'services' => $services,
                'notes'    => $appt_notes,
            ] );
        }

        // Fallback: implementação legada (mantida se Services Add-on não estiver ativo)
        $service_ids    = get_post_meta( $id_param, 'appointment_services', true );
        $service_prices = get_post_meta( $id_param, 'appointment_service_prices', true );
        if ( ! is_array( $service_prices ) ) {
            $service_prices = [];
        }
        $services = [];
        if ( is_array( $service_ids ) ) {
            foreach ( $service_ids as $sid ) {
                $srv = get_post( $sid );
                if ( $srv ) {
                    $name  = $srv->post_title;
                    // Preço personalizado ou base
                    $price = 0;
                    if ( isset( $service_prices[ $sid ] ) ) {
                        $price = (float) $service_prices[ $sid ];
                    } else {
                        $price = (float) get_post_meta( $sid, 'service_price', true );
                    }
                    $services[] = [ 'name' => $name, 'price' => $price ];
                }
            }
        }
        
        // Adiciona TaxiDog como serviço se foi solicitado
        if ( $taxidog_requested ) {
            $services[] = [
                'name'       => __( 'TaxiDog', 'dps-agenda-addon' ),
                'price'      => $taxidog_price,
                'is_taxidog' => true,
            ];
        }
        
        wp_send_json_success( [ 'services' => $services, 'notes' => $appt_notes ] );
    }

    /**
     * Limpa cron jobs agendados quando o plugin é desativado.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'dps_agenda_send_reminders' );
    }

    /**
     * Agenda envio diário de lembretes para clientes com agendamentos do dia.
     * O evento é agendado apenas uma vez, no próximo horário configurado (padrão: 08:00).
     */
    public function maybe_schedule_reminders() {
        if ( ! function_exists( 'wp_next_scheduled' ) ) {
            return;
        }
        // Verifica se já existe um evento programado
        $timestamp = wp_next_scheduled( 'dps_agenda_send_reminders' );
        if ( ! $timestamp ) {
            // Calcula timestamp para 08:00 do horário do site
            $hour   = 8;
            $minute = 0;
            // Usa timezone do site
            $tz = wp_timezone();
            $now = new DateTime( 'now', $tz );
            // Cria data para hoje às 08:00
            $schedule_time = new DateTime( $now->format( 'Y-m-d' ) . ' ' . sprintf( '%02d:%02d', $hour, $minute ), $tz );
            // Se já passou hoje, agenda para o dia seguinte
            if ( $schedule_time <= $now ) {
                $schedule_time->modify( '+1 day' );
            }
            wp_schedule_event( $schedule_time->getTimestamp(), 'daily', 'dps_agenda_send_reminders' );
        }
    }

    /**
     * Envia lembretes de agendamentos para clientes.
     * Este método é executado pelo cron diário configurado em maybe_schedule_reminders().
     *
     * NOTA: A lógica de ENVIO está delegada à Communications API.
     * A Agenda apenas identifica quais agendamentos precisam de lembrete.
     * 
     * @since 1.0.0
     * @return void
     */
    public function send_reminders() {
        // Determina a data atual no fuso horário do site
        $date = current_time( 'Y-m-d' );
        
        // Limite diário configurável (mesmo usado nas queries de visualização)
        $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
        
        // PERFORMANCE: Busca agendamentos do dia com limite e otimização
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => $daily_limit,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'appointment_date', 'value' => $date, 'compare' => '=' ],
            ],
            'no_found_rows'  => true, // Otimização: não conta total
        ] );
        
        // AUDITORIA: Registra início do envio de lembretes
        $total_appointments = count( $appointments );
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Cron de lembretes iniciado: %d agendamentos encontrados para %s',
                    $total_appointments,
                    $date
                ),
                [
                    'date'              => $date,
                    'total_found'       => $total_appointments,
                    'cron_job'          => 'dps_agenda_send_reminders',
                ],
                'agenda'
            );
        }
        
        if ( empty( $appointments ) ) {
            return;
        }

        $reminders_sent = 0;
        $reminders_skipped = 0;

        // Se Communications API estiver disponível, usa ela (método preferido)
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            
            foreach ( $appointments as $appt ) {
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $status ) {
                    $status = 'pendente';
                }
                
                // Apenas lembretes para agendamentos pendentes
                if ( $status !== 'pendente' ) {
                    $reminders_skipped++;
                    continue;
                }
                
                // Delega envio para a Communications API
                $api->send_appointment_reminder( $appt->ID );
                $reminders_sent++;
            }
        } else {
            // Fallback: envio manual via wp_mail (compatibilidade retroativa)
            foreach ( $appointments as $appt ) {
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $status ) {
                    $status = 'pendente';
                }
                if ( $status !== 'pendente' ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                if ( ! $client_id ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_post = get_post( $client_id );
                if ( ! $client_post ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_email = get_post_meta( $client_id, 'client_email', true );
                if ( ! $client_email ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_name = $client_post->post_title;
                $pet_id      = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet_name    = '';
                
                if ( $pet_id ) {
                    $pet_post = get_post( $pet_id );
                    if ( $pet_post ) {
                        $pet_name = $pet_post->post_title;
                    }
                }
                
                $time    = get_post_meta( $appt->ID, 'appointment_time', true );
                $time    = $time ? $time : '';
                $subject = sprintf( __( 'Lembrete de agendamento para %s', 'dps-agenda-addon' ), $client_name );
                $message = sprintf(
                    __( 'Olá %s,\n\nEste é um lembrete do agendamento para %s no dia %s às %s.\n\nEstamos aguardando você!\n\nAtenciosamente,\ndesi.pet by PRObst', 'dps-agenda-addon' ),
                    $client_name,
                    $pet_name ? $pet_name : __( 'seu pet', 'dps-agenda-addon' ),
                    date_i18n( 'd-m-Y', strtotime( $date ) ),
                    $time
                );
                
                $recipients = apply_filters( 'dps_agenda_reminder_recipients', [ $client_email ], $appt->ID );
                $subject    = apply_filters( 'dps_agenda_reminder_subject', $subject, $appt->ID );
                $message    = apply_filters( 'dps_agenda_reminder_content', $message, $appt->ID );
                
                foreach ( $recipients as $recipient ) {
                    wp_mail( $recipient, $subject, $message );
                }
                $reminders_sent++;
            }
        }
        
        // AUDITORIA: Registra resultado do envio de lembretes
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Cron de lembretes finalizado: %d enviados, %d ignorados (não pendentes ou sem dados)',
                    $reminders_sent,
                    $reminders_skipped
                ),
                [
                    'date'              => $date,
                    'total_found'       => $total_appointments,
                    'reminders_sent'    => $reminders_sent,
                    'reminders_skipped' => $reminders_skipped,
                    'cron_job'          => 'dps_agenda_send_reminders',
                ],
                'agenda'
            );
        }
    }

    /**
     * UX-1: AJAX handler para ações rápidas de status.
     * Permite mudança rápida de status em 1 clique sem modais.
     * 
     * Ações suportadas:
     * - finish: muda para 'finalizado'
     * - finish_and_paid: muda para 'finalizado_pago'
     * - cancel: muda para 'cancelado'
     * - mark_paid: muda de 'finalizado' para 'finalizado_pago'
     *
     * @since 1.1.0
     */
    public function quick_action_ajax() {
        // Verifica permissão do usuário
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_quick_action' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $action  = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
        
        if ( ! $appt_id || ! $action ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Mapeia ação para status
        $status_map = [
            'finish'          => 'finalizado',
            'finish_and_paid' => 'finalizado_pago',
            'cancel'          => 'cancelado',
            'mark_paid'       => 'finalizado_pago',
        ];
        
        if ( ! isset( $status_map[ $action ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Ação inválida.', 'dps-agenda-addon' ) ] );
        }
        
        $new_status = $status_map[ $action ];
        $old_status = get_post_meta( $appt_id, 'appointment_status', true );
        if ( ! $old_status ) {
            $old_status = 'pendente';
        }
        
        // Validações de negócio
        // Não permite marcar como pago se não estiver finalizado
        if ( $action === 'mark_paid' && $old_status !== 'finalizado' ) {
            wp_send_json_error( [ 'message' => __( 'Apenas atendimentos finalizados podem ser marcados como pagos.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica se é assinatura (não deve ter status finalizado_pago)
        $is_subscription = ! empty( get_post_meta( $appt_id, 'subscription_id', true ) );
        if ( $is_subscription && $new_status === 'finalizado_pago' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamentos de assinatura não podem ser marcados como pagos.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status usando mesma lógica do update_status_ajax
        delete_post_meta( $appt_id, 'appointment_status' );
        add_post_meta( $appt_id, 'appointment_status', $new_status, true );
        
        // Incrementa versão
        $current_version = intval( get_post_meta( $appt_id, '_dps_appointment_version', true ) );
        if ( $current_version < 1 ) {
            $current_version = 1;
        }
        $new_version = $current_version + 1;
        update_post_meta( $appt_id, '_dps_appointment_version', $new_version );
        
        // Log de auditoria
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Ação rápida "%s" (status %s → %s) por usuário #%d',
                    $appt_id,
                    $action,
                    $old_status,
                    $new_status,
                    get_current_user_id()
                ),
                [
                    'appointment_id' => $appt_id,
                    'action'         => $action,
                    'old_status'     => $old_status,
                    'new_status'     => $new_status,
                    'user_id'        => get_current_user_id(),
                    'version'        => $new_version,
                ],
                'agenda'
            );
        }
        
        // Aciona hook para sincronização com outros add-ons
        do_action( 'dps_base_after_save_appointment', $appt_id, 'simple' );
        
        // UX-2: Renderiza HTML da linha atualizada
        $updated_post = get_post( $appt_id );
        $column_labels = $this->get_column_labels();
        $row_html = $this->render_appointment_row( $updated_post, $column_labels );
        
        wp_send_json_success( [
            'message'        => __( 'Status atualizado com sucesso!', 'dps-agenda-addon' ),
            'row_html'       => $row_html,
            'appointment_id' => $appt_id,
            'new_status'     => $new_status,
            'version'        => $new_version,
        ] );
    }

    /**
     * CONF-2: AJAX handler para atualizar status de confirmação.
     * Permite marcar confirmação de atendimento sem alterar o status principal.
     * 
     * @since 1.2.0
     */
    public function update_confirmation_ajax() {
        // Verifica permissão do usuário
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_confirmation' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $confirmation_status = isset( $_POST['confirmation_status'] ) ? sanitize_text_field( $_POST['confirmation_status'] ) : '';
        
        if ( ! $appt_id || ! $confirmation_status ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida status de confirmação
        $valid_statuses = [ 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer' ];
        if ( ! in_array( $confirmation_status, $valid_statuses, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Status de confirmação inválido.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status de confirmação usando helper
        $success = $this->set_confirmation_status( $appt_id, $confirmation_status, get_current_user_id() );
        
        if ( ! $success ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao atualizar status de confirmação.', 'dps-agenda-addon' ) ] );
        }
        
        // Log de auditoria
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status de confirmação alterado para "%s" por usuário #%d',
                    $appt_id,
                    $confirmation_status,
                    get_current_user_id()
                ),
                [
                    'appointment_id'      => $appt_id,
                    'confirmation_status' => $confirmation_status,
                    'user_id'             => get_current_user_id(),
                ],
                'agenda'
            );
        }
        
        // Renderiza HTML da linha atualizada
        $updated_post = get_post( $appt_id );
        $column_labels = $this->get_column_labels();
        $row_html = $this->render_appointment_row( $updated_post, $column_labels );
        
        wp_send_json_success( [
            'message'             => __( 'Confirmação atualizada com sucesso!', 'dps-agenda-addon' ),
            'row_html'            => $row_html,
            'appointment_id'      => $appt_id,
            'confirmation_status' => $confirmation_status,
        ] );
    }

    /**
     * FASE 3: AJAX handler para atualização de status de TaxiDog.
     * Permite mudança de status do TaxiDog via ações rápidas.
     *
     * @since 1.2.0
     */
    public function update_taxidog_ajax() {
        // Verifica permissão do usuário
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_taxidog' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $new_status = isset( $_POST['taxidog_status'] ) ? sanitize_text_field( $_POST['taxidog_status'] ) : '';
        
        if ( ! $appt_id || ! $new_status ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status usando o helper
        $success = DPS_Agenda_TaxiDog_Helper::update_taxidog_status( $appt_id, $new_status );
        
        if ( ! $success ) {
            wp_send_json_error( [ 'message' => __( 'Status de TaxiDog inválido.', 'dps-agenda-addon' ) ] );
        }
        
        // Renderiza HTML da linha atualizada
        $updated_post = get_post( $appt_id );
        $column_labels = $this->get_column_labels();
        $row_html = $this->render_appointment_row( $updated_post, $column_labels );
        
        wp_send_json_success( [
            'message'        => __( 'Status de TaxiDog atualizado com sucesso!', 'dps-agenda-addon' ),
            'row_html'       => $row_html,
            'appointment_id' => $appt_id,
            'taxidog_status' => $new_status,
        ] );
    }

    /**
     * FASE 7: AJAX handler para solicitar TaxiDog.
     *
     * Habilita TaxiDog para um agendamento que não tinha solicitado.
     *
     * @since 1.4.2
     */
    public function request_taxidog_ajax() {
        // Verifica permissão do usuário
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_taxidog' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        
        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Habilita TaxiDog no agendamento
        update_post_meta( $appt_id, 'appointment_taxidog', 1 );
        update_post_meta( $appt_id, '_dps_taxidog_status', 'requested' );
        
        wp_send_json_success( [
            'message'        => __( 'TaxiDog solicitado com sucesso!', 'dps-agenda-addon' ),
            'appointment_id' => $appt_id,
        ] );
    }

    /**
     * FASE 4: AJAX handler para salvar configuração de capacidade.
     *
     * @since 1.4.0
     */
    public function save_capacity_ajax() {
        // Verifica permissão
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }

        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_capacity' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }

        $morning = isset( $_POST['morning'] ) ? intval( $_POST['morning'] ) : 10;
        $afternoon = isset( $_POST['afternoon'] ) ? intval( $_POST['afternoon'] ) : 10;

        $config = [
            'morning'   => max( 1, $morning ),
            'afternoon' => max( 1, $afternoon ),
        ];

        $success = DPS_Agenda_Capacity_Helper::save_capacity_config( $config );

        if ( $success ) {
            wp_send_json_success( [
                'message' => __( 'Configuração de capacidade salva com sucesso!', 'dps-agenda-addon' ),
                'config'  => $config,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Erro ao salvar configuração.', 'dps-agenda-addon' ) ] );
        }
    }

    /**
     * FASE 5: AJAX handler para reenviar link de pagamento.
     *
     * @since 1.5.0
     */
    public function resend_payment_ajax() {
        // Verifica permissão
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }

        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_resend_payment' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }

        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;

        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }

        // Valida que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }

        // Tenta reenviar via Payment Add-on se disponível
        $success = false;
        $message = '';

        if ( class_exists( 'DPS_Payment_API' ) && method_exists( 'DPS_Payment_API', 'resend_payment_link' ) ) {
            $result = DPS_Payment_API::resend_payment_link( $appt_id );
            $success = $result['success'] ?? false;
            $message = $result['message'] ?? '';
        } else {
            // Fallback: marca como pendente e registra tentativa
            update_post_meta( $appt_id, '_dps_payment_link_status', 'pending' );
            update_post_meta( $appt_id, '_dps_payment_resent_at', current_time( 'mysql' ) );
            $success = true;
            $message = __( 'Link marcado para reenvio. Configure o Payment Add-on para envio automático.', 'dps-agenda-addon' );
        }

        if ( $success ) {
            // Renderiza HTML da linha atualizada
            $updated_post = get_post( $appt_id );
            $column_labels = $this->get_column_labels();
            $row_html = $this->render_appointment_row( $updated_post, $column_labels );

            wp_send_json_success( [
                'message'        => $message ?: __( 'Link de pagamento reenviado com sucesso!', 'dps-agenda-addon' ),
                'row_html'       => $row_html,
                'appointment_id' => $appt_id,
            ] );
        } else {
            wp_send_json_error( [ 'message' => $message ?: __( 'Erro ao reenviar link de pagamento.', 'dps-agenda-addon' ) ] );
        }
    }

    /**
     * FASE 2: Renderiza relatório de ocupação.
     * Exibe métricas como taxa de ocupação, horários mais ocupados e cancelamentos.
     *
     * @since 1.2.0
     * @param array  $appointments Lista de agendamentos.
     * @param string $selected_date Data selecionada.
     * @param bool   $is_week_view Se é visualização semanal.
     */
    private function render_occupancy_report( $appointments, $selected_date, $is_week_view ) {
        if ( empty( $appointments ) ) {
            return;
        }
        
        // Calcular métricas
        $total = count( $appointments );
        $pendente = 0;
        $finalizado = 0;
        $finalizado_pago = 0;
        $cancelado = 0;
        $horarios = [];
        
        foreach ( $appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $status ) {
                $status = 'pendente';
            }
            
            switch ( $status ) {
                case 'pendente':
                    $pendente++;
                    break;
                case 'finalizado':
                    $finalizado++;
                    break;
                case 'finalizado_pago':
                    $finalizado_pago++;
                    break;
                case 'cancelado':
                    $cancelado++;
                    break;
            }
            
            // Agrupar por hora
            $time = get_post_meta( $appt->ID, 'appointment_time', true );
            if ( $time ) {
                $hour = substr( $time, 0, 2 );
                if ( ! isset( $horarios[ $hour ] ) ) {
                    $horarios[ $hour ] = 0;
                }
                $horarios[ $hour ]++;
            }
        }
        
        // Ordenar horários
        ksort( $horarios );
        
        // Encontrar horário mais ocupado
        $horario_pico = '';
        $max_count = 0;
        foreach ( $horarios as $hora => $count ) {
            if ( $count > $max_count ) {
                $max_count = $count;
                $horario_pico = $hora . ':00';
            }
        }
        
        // Calcular taxa de conclusão (excluindo cancelados)
        $total_nao_cancelado = $total - $cancelado;
        $taxa_conclusao = $total_nao_cancelado > 0 ? round( ( ( $finalizado + $finalizado_pago ) / $total_nao_cancelado ) * 100 ) : 0;
        $taxa_cancelamento = $total > 0 ? round( ( $cancelado / $total ) * 100 ) : 0;
        
        // Período do relatório
        if ( $is_week_view ) {
            $period_label = sprintf(
                __( 'Semana de %s', 'dps-agenda-addon' ),
                date_i18n( 'd/m/Y', strtotime( $selected_date ) )
            );
        } else {
            $period_label = date_i18n( 'd/m/Y', strtotime( $selected_date ) );
        }
        
        echo '<details class="dps-occupancy-report">';
        echo '<summary>' . esc_html__( '📊 Relatório de Ocupação', 'dps-agenda-addon' ) . ' - ' . esc_html( $period_label ) . '</summary>';
        echo '<div class="dps-occupancy-content">';
        
        // Cards de métricas
        echo '<div class="dps-occupancy-cards">';
        
        // Taxa de conclusão
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value dps-occupancy-success">' . esc_html( $taxa_conclusao ) . '%</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Taxa de Conclusão', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Taxa de cancelamento
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value dps-occupancy-warning">' . esc_html( $taxa_cancelamento ) . '%</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Taxa de Cancelamento', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Horário de pico
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value">' . esc_html( $horario_pico ?: '-' ) . '</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Horário de Pico', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Média por hora ativa (atendimentos ÷ horas com agendamentos)
        $horas_com_atendimento = count( $horarios );
        $media_por_hora = $horas_com_atendimento > 0 ? round( $total / $horas_com_atendimento, 1 ) : 0;
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value">' . esc_html( $media_por_hora ) . '</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Média/Hora Ativa', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        echo '</div>';
        
        // Distribuição por status
        echo '<div class="dps-occupancy-status">';
        echo '<h6>' . esc_html__( 'Distribuição por Status', 'dps-agenda-addon' ) . '</h6>';
        echo '<div class="dps-occupancy-bars">';
        
        if ( $pendente > 0 ) {
            $pct = round( ( $pendente / $total ) * 100 );
            echo '<div class="dps-occupancy-bar dps-status-pendente" style="width:' . esc_attr( $pct ) . '%;" title="' . esc_attr__( 'Pendente', 'dps-agenda-addon' ) . ': ' . $pendente . '">';
            echo esc_html( $pendente );
            echo '</div>';
        }
        if ( $finalizado > 0 ) {
            $pct = round( ( $finalizado / $total ) * 100 );
            echo '<div class="dps-occupancy-bar dps-status-finalizado" style="width:' . esc_attr( $pct ) . '%;" title="' . esc_attr__( 'Finalizado', 'dps-agenda-addon' ) . ': ' . $finalizado . '">';
            echo esc_html( $finalizado );
            echo '</div>';
        }
        if ( $finalizado_pago > 0 ) {
            $pct = round( ( $finalizado_pago / $total ) * 100 );
            echo '<div class="dps-occupancy-bar dps-status-pago" style="width:' . esc_attr( $pct ) . '%;" title="' . esc_attr__( 'Finalizado e Pago', 'dps-agenda-addon' ) . ': ' . $finalizado_pago . '">';
            echo esc_html( $finalizado_pago );
            echo '</div>';
        }
        if ( $cancelado > 0 ) {
            $pct = round( ( $cancelado / $total ) * 100 );
            echo '<div class="dps-occupancy-bar dps-status-cancelado" style="width:' . esc_attr( $pct ) . '%;" title="' . esc_attr__( 'Cancelado', 'dps-agenda-addon' ) . ': ' . $cancelado . '">';
            echo esc_html( $cancelado );
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</details>';
    }

    /**
     * FASE 2: Renderiza agendamentos agrupados por cliente.
     *
     * @since 1.2.0
     * @param array $appointments Lista de agendamentos filtrados.
     * @param array $column_labels Labels das colunas.
     */
    private function render_grouped_by_client( $appointments, $column_labels ) {
        if ( empty( $appointments ) ) {
            return;
        }
        
        // Agrupar por client_id
        $grouped = [];
        foreach ( $appointments as $appt ) {
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            if ( ! $client_id ) {
                $client_id = 0; // Sem cliente
            }
            if ( ! isset( $grouped[ $client_id ] ) ) {
                $grouped[ $client_id ] = [];
            }
            $grouped[ $client_id ][] = $appt;
        }
        
        // Pre-carregar posts de clientes (ignora client_id = 0)
        $valid_client_ids = array_filter( array_keys( $grouped ) );
        if ( ! empty( $valid_client_ids ) ) {
            _prime_post_caches( $valid_client_ids, false, false );
        }
        
        echo '<div class="dps-agenda-grouped">';
        
        foreach ( $grouped as $client_id => $client_appts ) {
            $client_post = $client_id ? get_post( $client_id ) : null;
            $client_name = $client_post ? $client_post->post_title : __( 'Sem cliente', 'dps-agenda-addon' );
            $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
            
            // Calcular totais do cliente
            $total_appts = count( $client_appts );
            $total_pendente = 0;
            foreach ( $client_appts as $appt ) {
                $st = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $st || $st === 'pendente' ) {
                    $total_pendente++;
                }
            }
            
            echo '<details class="dps-client-group" open>';
            echo '<summary class="dps-client-group-header">';
            echo '<span class="dps-client-name">👤 ' . esc_html( $client_name ) . '</span>';
            echo '<span class="dps-client-stats">';
            echo '<span class="dps-client-count">' . esc_html( sprintf( _n( '%d agendamento', '%d agendamentos', $total_appts, 'dps-agenda-addon' ), $total_appts ) ) . '</span>';
            if ( $total_pendente > 0 ) {
                echo ' <span class="dps-client-pending">(' . esc_html( sprintf( _n( '%d pendente', '%d pendentes', $total_pendente, 'dps-agenda-addon' ), $total_pendente ) ) . ')</span>';
            }
            echo '</span>';
            if ( $client_phone ) {
                echo '<a href="https://wa.me/' . esc_attr( preg_replace( '/\D/', '', $client_phone ) ) . '" target="_blank" class="dps-client-whatsapp" title="' . esc_attr__( 'Contato via WhatsApp', 'dps-agenda-addon' ) . '">💬</a>';
            }
            echo '</summary>';
            
            // Ordenar por data/hora
            usort( $client_appts, function( $a, $b ) {
                $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                return strcmp( $date_a . $time_a, $date_b . $time_b );
            } );
            
            // Renderizar lista compacta
            echo '<div class="dps-client-appointments">';
            foreach ( $client_appts as $appt ) {
                $date = get_post_meta( $appt->ID, 'appointment_date', true );
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet_post = $pet_id ? get_post( $pet_id ) : null;
                $pet_name = $pet_post ? $pet_post->post_title : '';
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $status ) {
                    $status = 'pendente';
                }
                
                $status_labels = [
                    'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
                    'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
                    'finalizado_pago' => __( 'Pago', 'dps-agenda-addon' ),
                    'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
                ];
                
                echo '<div class="dps-client-appt status-' . esc_attr( $status ) . '">';
                echo '<span class="dps-appt-datetime">' . esc_html( date_i18n( 'd/m', strtotime( $date ) ) ) . ' ' . esc_html( $time ) . '</span>';
                echo '<span class="dps-appt-pet">' . esc_html( $pet_name ) . '</span>';
                echo '<span class="dps-appt-status">' . esc_html( $status_labels[ $status ] ?? $status ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
            echo '</details>';
        }
        
        echo '</div>';
    }

    /**
     * FASE 2: Handler AJAX para exportação CSV.
     *
     * @since 1.2.0
     */
    public function export_csv_ajax() {
        // Verificar nonce (deve corresponder ao nonce_export gerado em enqueue_assets)
        if ( ! check_ajax_referer( 'dps_agenda_export_csv', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $view = isset( $_POST['view'] ) ? sanitize_text_field( $_POST['view'] ) : 'day';
        
        // Buscar agendamentos
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        
        if ( ! empty( $date ) ) {
            if ( $view === 'week' ) {
                $start_date = $date;
                $end_date = date( 'Y-m-d', strtotime( $date . ' +6 days' ) );
                $args['meta_query'] = [
                    [
                        'key'     => 'appointment_date',
                        'value'   => [ $start_date, $end_date ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                ];
            } else {
                $args['meta_query'] = [
                    [
                        'key'   => 'appointment_date',
                        'value' => $date,
                    ],
                ];
            }
        }
        
        $appointments = get_posts( $args );
        
        if ( empty( $appointments ) ) {
            wp_send_json_error( [ 'message' => __( 'Nenhum agendamento encontrado para exportar.', 'dps-agenda-addon' ) ] );
        }
        
        // Pre-carregar metadados
        $ids = wp_list_pluck( $appointments, 'ID' );
        update_meta_cache( 'post', $ids );
        
        // Coletar IDs relacionados
        $related_ids = [];
        foreach ( $appointments as $appt ) {
            $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            if ( $cid ) {
                $related_ids[] = (int) $cid;
            }
            if ( $pid ) {
                $related_ids[] = (int) $pid;
            }
        }
        if ( ! empty( $related_ids ) ) {
            _prime_post_caches( array_unique( $related_ids ), false, false );
        }
        
        // Gerar CSV
        $rows = [];
        $rows[] = [
            __( 'Data', 'dps-agenda-addon' ),
            __( 'Hora', 'dps-agenda-addon' ),
            __( 'Cliente', 'dps-agenda-addon' ),
            __( 'Pet', 'dps-agenda-addon' ),
            __( 'Status', 'dps-agenda-addon' ),
            __( 'Telefone', 'dps-agenda-addon' ),
        ];
        
        $status_labels = [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e Pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
        
        foreach ( $appointments as $appt ) {
            $date_val = get_post_meta( $appt->ID, 'appointment_date', true );
            $time_val = get_post_meta( $appt->ID, 'appointment_time', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $status ) {
                $status = 'pendente';
            }
            
            $client_post = $client_id ? get_post( $client_id ) : null;
            $pet_post = $pet_id ? get_post( $pet_id ) : null;
            
            $client_name = $client_post ? $client_post->post_title : '';
            $pet_name = $pet_post ? $pet_post->post_title : '';
            $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
            
            $rows[] = [
                date_i18n( 'd/m/Y', strtotime( $date_val ) ),
                $time_val,
                $client_name,
                $pet_name,
                $status_labels[ $status ] ?? $status,
                $client_phone,
            ];
        }
        
        // Converter para CSV
        $csv_content = "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
        foreach ( $rows as $row ) {
            $csv_content .= '"' . implode( '","', array_map( function( $cell ) {
                return str_replace( '"', '""', (string) $cell );
            }, $row ) ) . '"' . "\n";
        }
        
        $filename = 'agenda-' . ( $date ?: date( 'Y-m-d' ) ) . '.csv';
        
        wp_send_json_success( [
            'filename' => $filename,
            'content'  => base64_encode( $csv_content ),
        ] );
    }

    /**
     * FASE 2: Exporta a agenda para PDF (página de impressão).
     *
     * Gera uma página HTML otimizada para impressão e salvamento como PDF.
     * Layout moderno e elegante, sem poluição visual.
     *
     * @since 1.4.0
     */
    public function export_pdf_ajax() {
        // Verificar nonce (deve corresponder ao nonce_export_pdf gerado em enqueue_assets)
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dps_agenda_export_pdf' ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança. Por favor, recarregue a página e tente novamente.', 'dps-agenda-addon' ), 403 );
        }
        
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ), 403 );
        }
        
        $date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : '';
        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        
        // Validar formato de data se fornecida
        if ( ! empty( $date ) ) {
            $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
                $date = ''; // Data inválida, ignora o filtro
            }
        }
        
        // Validar view
        if ( ! in_array( $view, [ 'day', 'week' ], true ) ) {
            $view = 'day';
        }
        
        // Buscar agendamentos
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        
        if ( ! empty( $date ) ) {
            if ( $view === 'week' ) {
                $start_date = $date;
                $end_date = date( 'Y-m-d', strtotime( $date . ' +6 days' ) );
                $args['meta_query'] = [
                    [
                        'key'     => 'appointment_date',
                        'value'   => [ $start_date, $end_date ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                ];
            } else {
                $args['meta_query'] = [
                    [
                        'key'   => 'appointment_date',
                        'value' => $date,
                    ],
                ];
            }
        }
        
        $appointments = get_posts( $args );
        
        // Pre-carregar metadados
        if ( ! empty( $appointments ) ) {
            $ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $ids );
            
            // Coletar IDs relacionados
            $related_ids = [];
            foreach ( $appointments as $appt ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $cid ) {
                    $related_ids[] = (int) $cid;
                }
                if ( $pid ) {
                    $related_ids[] = (int) $pid;
                }
            }
            if ( ! empty( $related_ids ) ) {
                _prime_post_caches( array_unique( $related_ids ), false, false );
            }
        }
        
        // Status labels
        $status_labels = [
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e Pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
        
        // Nome do petshop
        $shop_name = get_option( 'dps_shop_name', get_bloginfo( 'name' ) );
        
        // Formatar título do período
        if ( ! empty( $date ) ) {
            if ( $view === 'week' ) {
                $start_date = $date;
                $end_date = date( 'Y-m-d', strtotime( $date . ' +6 days' ) );
                $period_title = sprintf(
                    /* translators: %1$s: start date, %2$s: end date */
                    __( 'Agenda: %1$s a %2$s', 'dps-agenda-addon' ),
                    date_i18n( 'd/m/Y', strtotime( $start_date ) ),
                    date_i18n( 'd/m/Y', strtotime( $end_date ) )
                );
            } else {
                $period_title = sprintf(
                    /* translators: %s: date */
                    __( 'Agenda: %s', 'dps-agenda-addon' ),
                    date_i18n( 'd/m/Y', strtotime( $date ) )
                );
            }
        } else {
            $period_title = __( 'Agenda Completa', 'dps-agenda-addon' );
        }
        
        // Renderizar página de impressão
        $this->render_pdf_print_page( $appointments, $period_title, $shop_name, $status_labels );
        exit;
    }

    /**
     * Renderiza a página de impressão PDF da agenda.
     *
     * @since 1.4.0
     * @param array  $appointments   Lista de agendamentos.
     * @param string $period_title   Título do período.
     * @param string $shop_name      Nome do petshop.
     * @param array  $status_labels  Labels de status traduzidos.
     */
    private function render_pdf_print_page( $appointments, $period_title, $shop_name, $status_labels ) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( $period_title . ' - ' . $shop_name ); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                    color: #374151;
                    line-height: 1.5;
                    padding: 40px;
                    max-width: 900px;
                    margin: 0 auto;
                    background: #fff;
                }
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 3px solid #0ea5e9;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .print-header__info { flex: 1; }
                .print-header__logo { 
                    font-size: 24px; 
                    font-weight: 700; 
                    color: #0ea5e9; 
                    margin-bottom: 4px;
                }
                .print-header__period { 
                    font-size: 18px; 
                    font-weight: 600; 
                    color: #374151; 
                }
                .print-header__date { 
                    color: #6b7280; 
                    font-size: 13px;
                    margin-top: 8px;
                }
                .print-summary {
                    display: flex;
                    gap: 24px;
                    background: #f9fafb;
                    padding: 16px 20px;
                    border-radius: 8px;
                    margin-bottom: 30px;
                    border: 1px solid #e5e7eb;
                }
                .print-summary__item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .print-summary__label {
                    font-size: 13px;
                    color: #6b7280;
                }
                .print-summary__value {
                    font-size: 18px;
                    font-weight: 700;
                    color: #374151;
                }
                .appointments-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 30px; 
                }
                .appointments-table th {
                    background: #f3f4f6;
                    padding: 12px 14px;
                    text-align: left;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #6b7280;
                    border-bottom: 2px solid #e5e7eb;
                }
                .appointments-table td {
                    padding: 14px;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 14px;
                    vertical-align: top;
                }
                .appointments-table tr:nth-child(even) { 
                    background: #f9fafb; 
                }
                .appointments-table tr:hover {
                    background: #f3f4f6;
                }
                .cell-time {
                    font-weight: 600;
                    color: #0ea5e9;
                    white-space: nowrap;
                }
                .cell-date {
                    color: #6b7280;
                    font-size: 12px;
                }
                .cell-client {
                    font-weight: 600;
                    color: #374151;
                }
                .cell-pet {
                    font-size: 13px;
                    color: #6b7280;
                }
                .cell-phone {
                    font-size: 13px;
                    color: #6b7280;
                    font-family: "SF Mono", Consolas, monospace;
                }
                .status-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 16px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.02em;
                }
                .status-pendente { 
                    background: #fef3c7; 
                    color: #92400e; 
                }
                .status-finalizado { 
                    background: #f3f4f6; 
                    color: #4b5563; 
                }
                .status-finalizado_pago { 
                    background: #d1fae5; 
                    color: #047857; 
                }
                .status-cancelado { 
                    background: #fee2e2; 
                    color: #b91c1c; 
                }
                .print-footer {
                    text-align: center;
                    padding-top: 24px;
                    border-top: 1px solid #e5e7eb;
                    color: #9ca3af;
                    font-size: 12px;
                }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #6b7280;
                }
                .empty-state__icon {
                    font-size: 48px;
                    margin-bottom: 16px;
                }
                .empty-state__message {
                    font-size: 16px;
                }
                @media print {
                    body { 
                        padding: 20px; 
                        max-width: 100%;
                    }
                    .no-print { display: none !important; }
                    .appointments-table th {
                        background: #f3f4f6 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .appointments-table tr:nth-child(even) { 
                        background: #f9fafb !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .status-badge {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
                .print-actions {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    display: flex;
                    gap: 10px;
                    z-index: 1000;
                }
                .print-actions button {
                    padding: 12px 24px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.2s ease;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .btn-print { 
                    background: #0ea5e9; 
                    color: white; 
                }
                .btn-print:hover { 
                    background: #0284c7; 
                }
                .btn-close { 
                    background: #f3f4f6; 
                    color: #374151; 
                }
                .btn-close:hover { 
                    background: #e5e7eb; 
                }
            </style>
        </head>
        <body>
            <div class="print-actions no-print">
                <button type="button" class="btn-print" id="dps-print-btn">🖨️ <?php esc_html_e( 'Imprimir / Salvar PDF', 'dps-agenda-addon' ); ?></button>
                <button type="button" class="btn-close" id="dps-close-btn"><?php esc_html_e( 'Fechar', 'dps-agenda-addon' ); ?></button>
            </div>
            <script>
                document.getElementById('dps-print-btn').addEventListener('click', function() { window.print(); });
                document.getElementById('dps-close-btn').addEventListener('click', function() { window.close(); });
            </script>

            <header class="print-header">
                <div class="print-header__info">
                    <div class="print-header__logo">🐾 <?php echo esc_html( $shop_name ); ?></div>
                    <div class="print-header__period"><?php echo esc_html( $period_title ); ?></div>
                    <div class="print-header__date"><?php echo esc_html( sprintf( __( 'Gerado em %s', 'dps-agenda-addon' ), date_i18n( 'd/m/Y \à\s H:i' ) ) ); ?></div>
                </div>
            </header>

            <?php if ( ! empty( $appointments ) ) : 
                // Contadores de status
                $status_counts = [
                    'pendente' => 0,
                    'finalizado' => 0,
                    'finalizado_pago' => 0,
                    'cancelado' => 0,
                ];
                foreach ( $appointments as $appt ) {
                    $status = get_post_meta( $appt->ID, 'appointment_status', true );
                    if ( ! $status ) {
                        $status = 'pendente';
                    }
                    if ( isset( $status_counts[ $status ] ) ) {
                        $status_counts[ $status ]++;
                    }
                }
            ?>
                <div class="print-summary">
                    <div class="print-summary__item">
                        <span class="print-summary__label"><?php esc_html_e( 'Total:', 'dps-agenda-addon' ); ?></span>
                        <span class="print-summary__value"><?php echo esc_html( count( $appointments ) ); ?></span>
                    </div>
                    <?php if ( $status_counts['pendente'] > 0 ) : ?>
                        <div class="print-summary__item">
                            <span class="print-summary__label">🟡 <?php esc_html_e( 'Pendentes:', 'dps-agenda-addon' ); ?></span>
                            <span class="print-summary__value"><?php echo esc_html( $status_counts['pendente'] ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $status_counts['finalizado_pago'] > 0 ) : ?>
                        <div class="print-summary__item">
                            <span class="print-summary__label">✅ <?php esc_html_e( 'Pagos:', 'dps-agenda-addon' ); ?></span>
                            <span class="print-summary__value"><?php echo esc_html( $status_counts['finalizado_pago'] ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Horário', 'dps-agenda-addon' ); ?></th>
                            <th><?php esc_html_e( 'Cliente', 'dps-agenda-addon' ); ?></th>
                            <th><?php esc_html_e( 'Contato', 'dps-agenda-addon' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dps-agenda-addon' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $appointments as $appt ) : 
                            $date_val   = get_post_meta( $appt->ID, 'appointment_date', true );
                            $time_val   = get_post_meta( $appt->ID, 'appointment_time', true );
                            $client_id  = get_post_meta( $appt->ID, 'appointment_client_id', true );
                            $pet_id     = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                            $status     = get_post_meta( $appt->ID, 'appointment_status', true );
                            if ( ! $status ) {
                                $status = 'pendente';
                            }
                            
                            $client_post  = $client_id ? get_post( $client_id ) : null;
                            $pet_post     = $pet_id ? get_post( $pet_id ) : null;
                            
                            $client_name  = $client_post ? $client_post->post_title : '';
                            $pet_name     = $pet_post ? $pet_post->post_title : '';
                            $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
                            
                            $status_class = 'status-' . sanitize_html_class( $status );
                        ?>
                            <tr>
                                <td>
                                    <div class="cell-time"><?php echo esc_html( $time_val ); ?></div>
                                    <div class="cell-date"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $date_val ) ) ); ?></div>
                                </td>
                                <td>
                                    <div class="cell-client"><?php echo esc_html( $client_name ?: '—' ); ?></div>
                                    <?php if ( $pet_name ) : ?>
                                        <div class="cell-pet">🐾 <?php echo esc_html( $pet_name ); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cell-phone"><?php echo esc_html( $client_phone ?: '—' ); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="empty-state">
                    <div class="empty-state__icon">📋</div>
                    <div class="empty-state__message"><?php esc_html_e( 'Nenhum agendamento encontrado para este período.', 'dps-agenda-addon' ); ?></div>
                </div>
            <?php endif; ?>

            <footer class="print-footer">
                <?php echo esc_html( sprintf( __( 'Documento gerado em %s por %s', 'dps-agenda-addon' ), date_i18n( 'd/m/Y H:i' ), $shop_name ) ); ?>
            </footer>
        </body>
        </html>
        <?php
    }

    /**
     * FASE 4: Renderiza a visualização de calendário mensal.
     *
     * @since 1.3.0
     * @param string $selected_date Data selecionada no formato Y-m-d.
     */
    private function render_calendar_view( $selected_date ) {
        $base_url = remove_query_arg( [ 'dps_date', 'view', 'show_all' ] );
        
        // Valida e cria objeto de data
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( ! $date_obj ) {
            $date_obj = new DateTime();
            $selected_date = $date_obj->format( 'Y-m-d' );
        }
        
        // Calcula mês anterior e próximo
        $prev_month = ( clone $date_obj )->modify( 'first day of previous month' )->format( 'Y-m-d' );
        $next_month = ( clone $date_obj )->modify( 'first day of next month' )->format( 'Y-m-d' );
        
        // Header do calendário
        echo '<div class="dps-calendar-header">';
        echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $prev_month, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-nav-btn">← ' . esc_html__( 'Anterior', 'dps-agenda-addon' ) . '</a>';
        
        // Título e botão Hoje
        $today = current_time( 'Y-m-d' );
        echo '<div class="dps-calendar-title-group">';
        echo '<h4 class="dps-calendar-title">' . esc_html( ucfirst( date_i18n( 'F Y', $date_obj->getTimestamp() ) ) ) . '</h4>';
        if ( $selected_date !== $today ) {
            echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $today, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-today-btn">' . esc_html__( 'Hoje', 'dps-agenda-addon' ) . '</a>';
        }
        echo '</div>';
        
        echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $next_month, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-nav-btn">' . esc_html__( 'Próximo', 'dps-agenda-addon' ) . ' →</a>';
        echo '</div>';
        
        // Container do calendário
        $calendar_attrs = sprintf(
            'id="dps-calendar-container" class="dps-calendar" data-date="%s" data-ajax="%s" data-nonce="%s"',
            esc_attr( $selected_date ),
            esc_attr( admin_url( 'admin-ajax.php' ) ),
            esc_attr( wp_create_nonce( 'dps_agenda_calendar' ) )
        );
        echo '<div ' . $calendar_attrs . '>';
        
        // Renderiza calendário HTML (fallback se JS não carregar)
        $this->render_calendar_grid( $selected_date );
        
        echo '</div>';
        
        // Legenda
        echo '<div class="dps-calendar-legend">';
        echo '<span class="dps-legend-item"><span class="dps-legend-color dps-status-pendente"></span> ' . esc_html__( 'Pendente', 'dps-agenda-addon' ) . '</span>';
        echo '<span class="dps-legend-item"><span class="dps-legend-color dps-status-finalizado"></span> ' . esc_html__( 'Finalizado', 'dps-agenda-addon' ) . '</span>';
        echo '<span class="dps-legend-item"><span class="dps-legend-color dps-status-pago"></span> ' . esc_html__( 'Pago', 'dps-agenda-addon' ) . '</span>';
        echo '<span class="dps-legend-item"><span class="dps-legend-color dps-status-cancelado"></span> ' . esc_html__( 'Cancelado', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
    }

    /**
     * FASE 4: Renderiza o grid HTML do calendário.
     *
     * @since 1.3.0
     * @param string $selected_date Data selecionada no formato Y-m-d.
     */
    private function render_calendar_grid( $selected_date ) {
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( ! $date_obj ) {
            $date_obj = new DateTime();
        }
        $year = (int) $date_obj->format( 'Y' );
        $month = (int) $date_obj->format( 'm' );
        
        // Primeiro dia do mês
        $first_day = new DateTime( "$year-$month-01" );
        $days_in_month = (int) $first_day->format( 't' );
        $start_weekday = (int) $first_day->format( 'N' ); // 1=seg, 7=dom
        
        // Busca agendamentos do mês
        $appointments = $this->get_month_appointments( $year, $month );
        
        // Header dos dias da semana
        $weekdays = [
            __( 'Seg', 'dps-agenda-addon' ),
            __( 'Ter', 'dps-agenda-addon' ),
            __( 'Qua', 'dps-agenda-addon' ),
            __( 'Qui', 'dps-agenda-addon' ),
            __( 'Sex', 'dps-agenda-addon' ),
            __( 'Sáb', 'dps-agenda-addon' ),
            __( 'Dom', 'dps-agenda-addon' ),
        ];
        
        echo '<div class="dps-calendar-grid">';
        
        // Header
        echo '<div class="dps-calendar-weekdays">';
        foreach ( $weekdays as $wd ) {
            echo '<div class="dps-calendar-weekday">' . esc_html( $wd ) . '</div>';
        }
        echo '</div>';
        
        // Dias
        echo '<div class="dps-calendar-days">';
        
        // Células vazias antes do primeiro dia
        for ( $i = 1; $i < $start_weekday; $i++ ) {
            echo '<div class="dps-calendar-day dps-calendar-day--empty"></div>';
        }
        
        $base_url = remove_query_arg( [ 'dps_date', 'view', 'show_all' ] );
        $today = current_time( 'Y-m-d' );
        
        // Dias do mês
        for ( $day = 1; $day <= $days_in_month; $day++ ) {
            $date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
            $is_today = ( $date === $today );
            $day_appointments = isset( $appointments[ $date ] ) ? $appointments[ $date ] : [];
            $count = count( $day_appointments );
            
            // Conta por status
            $status_counts = [ 'pendente' => 0, 'finalizado' => 0, 'finalizado_pago' => 0, 'cancelado' => 0 ];
            foreach ( $day_appointments as $appt ) {
                $st = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $st ) {
                    $st = 'pendente';
                }
                if ( isset( $status_counts[ $st ] ) ) {
                    $status_counts[ $st ]++;
                }
            }
            
            $classes = [ 'dps-calendar-day' ];
            if ( $is_today ) {
                $classes[] = 'dps-calendar-day--today';
            }
            if ( $count > 0 ) {
                $classes[] = 'dps-calendar-day--has-events';
            }
            
            $day_url = add_query_arg( [ 'dps_date' => $date, 'view' => 'day' ], $base_url );
            
            echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
            echo '<a href="' . esc_url( $day_url ) . '" class="dps-calendar-day-link">';
            echo '<span class="dps-calendar-day-number">' . esc_html( $day ) . '</span>';
            
            if ( $count > 0 ) {
                echo '<div class="dps-calendar-day-events">';
                
                // Indicadores de status
                if ( $status_counts['pendente'] > 0 ) {
                    echo '<span class="dps-calendar-badge dps-status-pendente" title="' . esc_attr( sprintf( __( '%d pendente(s)', 'dps-agenda-addon' ), $status_counts['pendente'] ) ) . '">' . esc_html( $status_counts['pendente'] ) . '</span>';
                }
                if ( $status_counts['finalizado'] > 0 ) {
                    echo '<span class="dps-calendar-badge dps-status-finalizado" title="' . esc_attr( sprintf( __( '%d finalizado(s)', 'dps-agenda-addon' ), $status_counts['finalizado'] ) ) . '">' . esc_html( $status_counts['finalizado'] ) . '</span>';
                }
                if ( $status_counts['finalizado_pago'] > 0 ) {
                    echo '<span class="dps-calendar-badge dps-status-pago" title="' . esc_attr( sprintf( __( '%d pago(s)', 'dps-agenda-addon' ), $status_counts['finalizado_pago'] ) ) . '">' . esc_html( $status_counts['finalizado_pago'] ) . '</span>';
                }
                if ( $status_counts['cancelado'] > 0 ) {
                    echo '<span class="dps-calendar-badge dps-status-cancelado" title="' . esc_attr( sprintf( __( '%d cancelado(s)', 'dps-agenda-addon' ), $status_counts['cancelado'] ) ) . '">' . esc_html( $status_counts['cancelado'] ) . '</span>';
                }
                
                echo '</div>';
            }
            
            echo '</a>';
            echo '</div>';
        }
        
        // Células vazias após o último dia
        $end_weekday = (int) ( new DateTime( "$year-$month-$days_in_month" ) )->format( 'N' );
        for ( $i = $end_weekday; $i < 7; $i++ ) {
            echo '<div class="dps-calendar-day dps-calendar-day--empty"></div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * FASE 4: Busca agendamentos do mês.
     *
     * @since 1.3.0
     * @param int $year Ano.
     * @param int $month Mês.
     * @return array Agendamentos indexados por data.
     */
    private function get_month_appointments( $year, $month ) {
        $first_day = sprintf( '%04d-%02d-01', $year, $month );
        $last_day = sprintf( '%04d-%02d-%02d', $year, $month, cal_days_in_month( CAL_GREGORIAN, $month, $year ) );
        
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => [ $first_day, $last_day ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
            ],
            'no_found_rows'  => true,
        ] );
        
        // Pre-carrega metadados
        if ( ! empty( $appointments ) ) {
            $ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $ids );
        }
        
        // Agrupa por data
        $grouped = [];
        foreach ( $appointments as $appt ) {
            $date = get_post_meta( $appt->ID, 'appointment_date', true );
            if ( ! isset( $grouped[ $date ] ) ) {
                $grouped[ $date ] = [];
            }
            $grouped[ $date ][] = $appt;
        }
        
        return $grouped;
    }

    /**
     * FASE 4: Handler AJAX para buscar eventos do calendário.
     *
     * @since 1.3.0
     */
    public function calendar_events_ajax() {
        // Verificar nonce
        if ( ! check_ajax_referer( 'dps_agenda_calendar', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $start = isset( $_POST['start'] ) ? sanitize_text_field( $_POST['start'] ) : '';
        $end = isset( $_POST['end'] ) ? sanitize_text_field( $_POST['end'] ) : '';
        
        // Validar formato de data Y-m-d
        if ( empty( $start ) || empty( $end ) || 
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || 
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de data inválido.', 'dps-agenda-addon' ) ] );
        }
        
        // Busca agendamentos no período
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => [ $start, $end ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
            ],
        ] );
        
        // Pre-carrega caches
        if ( ! empty( $appointments ) ) {
            $ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $ids );
            
            $related_ids = [];
            foreach ( $appointments as $appt ) {
                $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                if ( $cid ) {
                    $related_ids[] = (int) $cid;
                }
                if ( $pid ) {
                    $related_ids[] = (int) $pid;
                }
            }
            if ( ! empty( $related_ids ) ) {
                _prime_post_caches( array_unique( $related_ids ), false, false );
            }
        }
        
        // Formata eventos para o calendário
        $events = [];
        $status_colors = [
            'pendente'        => '#f59e0b',
            'finalizado'      => '#0ea5e9',
            'finalizado_pago' => '#22c55e',
            'cancelado'       => '#ef4444',
        ];
        
        foreach ( $appointments as $appt ) {
            $date = get_post_meta( $appt->ID, 'appointment_date', true );
            $time = get_post_meta( $appt->ID, 'appointment_time', true );
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $status ) {
                $status = 'pendente';
            }
            
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_post = $client_id ? get_post( $client_id ) : null;
            $pet_post = $pet_id ? get_post( $pet_id ) : null;
            
            $title = $time;
            if ( $pet_post ) {
                $title .= ' - ' . $pet_post->post_title;
            }
            if ( $client_post ) {
                $title .= ' (' . $client_post->post_title . ')';
            }
            
            $events[] = [
                'id'        => $appt->ID,
                'title'     => $title,
                'start'     => $date . 'T' . $time,
                'color'     => $status_colors[ $status ] ?? '#6b7280',
                'status'    => $status,
                'client'    => $client_post ? $client_post->post_title : '',
                'pet'       => $pet_post ? $pet_post->post_title : '',
            ];
        }
        
        wp_send_json_success( $events );
    }

    // =========================================================================
    // FASE 5: Funcionalidades Administrativas Avançadas
    // =========================================================================

    /**
     * AJAX handler para atualização de status em lote.
     *
     * Permite alterar o status de múltiplos agendamentos de uma só vez.
     *
     * @since 1.3.2
     * @return void
     */
    public function bulk_update_status_ajax() {
        // Verificar nonce e permissões
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_bulk_actions' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( empty( $ids ) || empty( $status ) ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Validar status
        $valid_statuses = [ self::STATUS_PENDING, self::STATUS_FINISHED, self::STATUS_PAID, self::STATUS_CANCELED ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Status inválido.', 'dps-agenda-addon' ) ] );
        }
        
        $updated = 0;
        $user_id = get_current_user_id();
        
        foreach ( $ids as $appt_id ) {
            $post = get_post( $appt_id );
            if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
                continue;
            }
            
            $old_status = get_post_meta( $appt_id, 'appointment_status', true );
            if ( ! $old_status ) {
                $old_status = self::STATUS_PENDING;
            }
            
            if ( $old_status !== $status ) {
                update_post_meta( $appt_id, 'appointment_status', $status );
                
                // Incrementar versão
                $version = intval( get_post_meta( $appt_id, '_dps_appointment_version', true ) );
                update_post_meta( $appt_id, '_dps_appointment_version', $version + 1 );
                
                // Disparar hook para log
                do_action( 'dps_appointment_status_changed', $appt_id, $old_status, $status, $user_id );
                
                $updated++;
            }
        }
        
        wp_send_json_success( [
            'message' => sprintf(
                _n( '%d agendamento atualizado.', '%d agendamentos atualizados.', $updated, 'dps-agenda-addon' ),
                $updated
            ),
            'updated' => $updated,
        ] );
    }

    /**
     * AJAX handler para reagendamento rápido.
     *
     * Permite alterar apenas data e hora de um agendamento sem editar outros campos.
     *
     * @since 1.3.2
     * @return void
     */
    public function quick_reschedule_ajax() {
        // Verificar nonce e permissões
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_quick_reschedule' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $new_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $new_time = isset( $_POST['time'] ) ? sanitize_text_field( $_POST['time'] ) : '';
        
        if ( ! $appt_id || ! $new_date || ! $new_time ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-agenda-addon' ) ] );
        }
        
        // Validar que o post existe e é um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento não encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Validar formato de data e hora
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de data inválido.', 'dps-agenda-addon' ) ] );
        }
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $new_time ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de hora inválido.', 'dps-agenda-addon' ) ] );
        }
        
        // Salvar data/hora anteriores para log
        $old_date = get_post_meta( $appt_id, 'appointment_date', true );
        $old_time = get_post_meta( $appt_id, 'appointment_time', true );
        
        // Atualizar data e hora
        update_post_meta( $appt_id, 'appointment_date', $new_date );
        update_post_meta( $appt_id, 'appointment_time', $new_time );
        
        // Incrementar versão
        $version = intval( get_post_meta( $appt_id, '_dps_appointment_version', true ) );
        update_post_meta( $appt_id, '_dps_appointment_version', $version + 1 );
        
        // Registrar no histórico
        $this->add_to_appointment_history( $appt_id, 'rescheduled', [
            'old_date' => $old_date,
            'old_time' => $old_time,
            'new_date' => $new_date,
            'new_time' => $new_time,
        ] );
        
        // Disparar hook para notificações (pode ser usado por outros add-ons)
        do_action( 'dps_appointment_rescheduled', $appt_id, $new_date, $new_time, $old_date, $old_time );
        
        wp_send_json_success( [
            'message' => __( 'Agendamento reagendado com sucesso.', 'dps-agenda-addon' ),
            'new_date' => date_i18n( 'd/m/Y', strtotime( $new_date ) ),
            'new_time' => $new_time,
        ] );
    }

    /**
     * AJAX handler para obter histórico de alterações de um agendamento.
     *
     * @since 1.3.2
     * @return void
     */
    public function get_appointment_history_ajax() {
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_appointment_history' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        
        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'dps-agenda-addon' ) ] );
        }
        
        $history = get_post_meta( $appt_id, '_dps_appointment_history', true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }
        
        // Formatar para exibição
        $formatted = [];
        foreach ( $history as $entry ) {
            $user = get_userdata( $entry['user_id'] ?? 0 );
            $formatted[] = [
                'action'    => $entry['action'] ?? '',
                'date'      => isset( $entry['date'] ) ? date_i18n( 'd/m/Y H:i', strtotime( $entry['date'] ) ) : '',
                'user'      => $user ? $user->display_name : __( 'Sistema', 'dps-agenda-addon' ),
                'details'   => $entry['details'] ?? [],
            ];
        }
        
        wp_send_json_success( [ 'history' => array_reverse( $formatted ) ] );
    }

    /**
     * AJAX handler para obter KPIs administrativos.
     *
     * Retorna métricas consolidadas para o dashboard administrativo.
     *
     * @since 1.3.2
     * @return void
     */
    public function get_admin_kpis_ajax() {
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_admin_kpis' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        
        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : current_time( 'Y-m-d' );
        
        $kpis = $this->calculate_admin_kpis( $date );
        
        wp_send_json_success( $kpis );
    }

    /**
     * Calcula KPIs administrativos para uma data.
     *
     * @since 1.3.2
     * @param string $date Data no formato Y-m-d.
     * @return array KPIs calculados.
     */
    private function calculate_admin_kpis( $date ) {
        // Agendamentos do dia
        $day_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $date,
                    'compare' => '=',
                    'type'    => 'DATE',
                ],
            ],
            'no_found_rows'  => true,
        ] );
        
        $pending = 0;
        $finished = 0;
        $paid = 0;
        $canceled = 0;
        $revenue_estimate = 0;
        
        foreach ( $day_appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $status ) {
                $status = self::STATUS_PENDING;
            }
            
            switch ( $status ) {
                case self::STATUS_PENDING:
                    $pending++;
                    break;
                case self::STATUS_FINISHED:
                    $finished++;
                    break;
                case self::STATUS_PAID:
                    $paid++;
                    break;
                case self::STATUS_CANCELED:
                    $canceled++;
                    break;
            }
            
            // Calcular receita estimada (exceto cancelados)
            if ( $status !== self::STATUS_CANCELED ) {
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                if ( is_array( $services ) && class_exists( 'DPS_Services_API' ) ) {
                    $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    $pet_size = $pet_id ? get_post_meta( $pet_id, 'pet_size', true ) : 'medium';
                    foreach ( $services as $service_id ) {
                        $price = DPS_Services_API::calculate_price( $service_id, $pet_size );
                        if ( $price !== null ) {
                            $revenue_estimate += $price;
                        }
                    }
                }
            }
        }
        
        // Taxa de cancelamento semanal
        $week_start = date( 'Y-m-d', strtotime( 'monday this week', strtotime( $date ) ) );
        $week_end = date( 'Y-m-d', strtotime( 'sunday this week', strtotime( $date ) ) );
        
        $week_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => [ $week_start, $week_end ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
            ],
            'no_found_rows'  => true,
        ] );
        
        $week_total = count( $week_appointments );
        $week_canceled = 0;
        foreach ( $week_appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( $status === self::STATUS_CANCELED ) {
                $week_canceled++;
            }
        }
        $cancel_rate = $week_total > 0 ? round( ( $week_canceled / $week_total ) * 100, 1 ) : 0;
        
        // Média de atendimentos por dia (últimos 7 dias)
        $seven_days_ago = date( 'Y-m-d', strtotime( '-7 days', strtotime( $date ) ) );
        $recent_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => [ $seven_days_ago, $date ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => 'appointment_status',
                    'value'   => self::STATUS_CANCELED,
                    'compare' => '!=',
                ],
            ],
            'no_found_rows'  => true,
        ] );
        $avg_daily = round( count( $recent_appointments ) / 7, 1 );
        
        return [
            'date'            => $date,
            'date_formatted'  => date_i18n( 'd/m/Y', strtotime( $date ) ),
            'pending'         => $pending,
            'finished'        => $finished,
            'paid'            => $paid,
            'canceled'        => $canceled,
            'total'           => count( $day_appointments ),
            'revenue_estimate' => $revenue_estimate,
            'revenue_formatted' => 'R$ ' . number_format( $revenue_estimate / 100, 2, ',', '.' ),
            'cancel_rate'     => $cancel_rate,
            'avg_daily'       => $avg_daily,
        ];
    }

    /**
     * Registra alteração de status no histórico do agendamento.
     *
     * @since 1.3.2
     * @param int    $appt_id    ID do agendamento.
     * @param string $old_status Status anterior.
     * @param string $new_status Novo status.
     * @param int    $user_id    ID do usuário que fez a alteração.
     * @return void
     */
    public function log_status_change( $appt_id, $old_status, $new_status, $user_id ) {
        $this->add_to_appointment_history( $appt_id, 'status_change', [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );
    }

    /**
     * Adiciona entrada ao histórico de um agendamento.
     *
     * @since 1.3.2
     * @param int    $appt_id ID do agendamento.
     * @param string $action  Tipo de ação (created, status_change, rescheduled).
     * @param array  $details Detalhes adicionais.
     * @return void
     */
    private function add_to_appointment_history( $appt_id, $action, $details = [] ) {
        $history = get_post_meta( $appt_id, '_dps_appointment_history', true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }
        
        $history[] = [
            'action'  => $action,
            'date'    => current_time( 'Y-m-d H:i:s' ),
            'user_id' => get_current_user_id(),
            'details' => $details,
        ];
        
        // Limitar a 50 entradas mais recentes
        if ( count( $history ) > 50 ) {
            $history = array_slice( $history, -50 );
        }
        
        update_post_meta( $appt_id, '_dps_appointment_history', $history );
    }

    /**
     * Renderiza o dashboard de KPIs como seção colapsável no final da agenda.
     *
     * @since 1.3.2
     * @since 1.4.1 Modificado para usar <details> colapsável, fechado por padrão
     * @param string $date Data selecionada.
     * @return void
     */
    private function render_admin_dashboard( $date ) {
        $kpis = $this->calculate_admin_kpis( $date );
        $status_config = self::get_status_config();
        $date_formatted = date_i18n( 'd/m/Y', strtotime( $date ) );
        
        echo '<details class="dps-summary-report">';
        echo '<summary>📊 ' . esc_html__( 'Resumo do Dia', 'dps-agenda-addon' ) . ' - ' . esc_html( $date_formatted ) . '</summary>';
        echo '<div class="dps-summary-content">';
        echo '<div class="dps-kpi-grid">';
        
        // Card: Pendentes
        echo '<div class="dps-kpi-card dps-kpi-pending">';
        echo '<span class="dps-kpi-icon">' . esc_html( $status_config[ self::STATUS_PENDING ]['icon'] ) . '</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['pending'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Pendentes', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: Finalizados
        echo '<div class="dps-kpi-card dps-kpi-finished">';
        echo '<span class="dps-kpi-icon">' . esc_html( $status_config[ self::STATUS_FINISHED ]['icon'] ) . '</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['finished'] + $kpis['paid'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Finalizados', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: Faturamento Estimado
        echo '<div class="dps-kpi-card dps-kpi-revenue">';
        echo '<span class="dps-kpi-icon">💰</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['revenue_formatted'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Faturamento Est.', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: Taxa de Cancelamento
        echo '<div class="dps-kpi-card dps-kpi-cancel">';
        echo '<span class="dps-kpi-icon">📉</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['cancel_rate'] ) . '%</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Cancelamentos (semana)', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: Média Diária
        echo '<div class="dps-kpi-card dps-kpi-avg">';
        echo '<span class="dps-kpi-icon">📈</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['avg_daily'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Média/dia (7d)', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        echo '</div>'; // .dps-kpi-grid
        echo '</div>'; // .dps-summary-content
        echo '</details>'; // .dps-summary-report
    }

    /**
     * Retorna a URL da página atual.
     *
     * @since 1.5.0
     * @return string
     */
    private static function get_current_page_url() {
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
            if ( is_string( $request_uri ) && '' !== $request_uri ) {
                return esc_url_raw( home_url( $request_uri ) );
            }
        }

        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        if ( $queried_id ) {
            $permalink = get_permalink( $queried_id );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        global $post;
        if ( isset( $post->ID ) ) {
            $permalink = get_permalink( $post->ID );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        return home_url();
    }


}

/**
 * Inicializa o Agenda Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_agenda_init_addon() {
    if ( class_exists( 'DPS_Agenda_Addon' ) ) {
        DPS_Agenda_Addon::get_instance();
        
        // Inicializa o Hub centralizado de Agenda (Fase 2 - Reorganização de Menus)
        if ( class_exists( 'DPS_Agenda_Hub' ) ) {
            DPS_Agenda_Hub::get_instance();
        }
    }
}
add_action( 'init', 'dps_agenda_init_addon', 5 );
