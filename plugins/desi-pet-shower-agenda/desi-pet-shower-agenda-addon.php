<?php
/**
 * Plugin Name:       desi.pet by PRObst Ã¢â‚¬â€œ Agenda Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Cria pÃƒÂ¡gina automÃƒÂ¡tica com agenda de atendimentos. Visualize e gerencie compromissos de forma prÃƒÂ¡tica.
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
 * Verifica se o plugin base desi.pet by PRObst estÃƒÂ¡ ativo.
 * Se nÃƒÂ£o estiver, exibe aviso e interrompe carregamento do add-on.
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
 * Usa prioridade 1 para garantir que rode antes da inicializaÃƒÂ§ÃƒÂ£o da classe (prioridade 5).
 */
function dps_agenda_load_textdomain() {
    load_plugin_textdomain( 'dps-agenda-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_agenda_load_textdomain', 1 );

// FASE 3: Carrega traits de refatoraÃƒÂ§ÃƒÂ£o e helpers
require_once __DIR__ . '/includes/trait-dps-agenda-renderer.php';
require_once __DIR__ . '/includes/trait-dps-agenda-query.php';

// FASE 3: Carrega helpers para Pagamento, TaxiDog e GPS
require_once __DIR__ . '/includes/class-dps-agenda-payment-helper.php';
require_once __DIR__ . '/includes/class-dps-agenda-taxidog-helper.php';
require_once __DIR__ . '/includes/class-dps-agenda-gps-helper.php';

// FASE 4: Carrega helper para Dashboard Operacional
require_once __DIR__ . '/includes/class-dps-agenda-dashboard-service.php';

// FASE 4: Carrega helper para Capacidade/LotaÃƒÂ§ÃƒÂ£o
require_once __DIR__ . '/includes/class-dps-agenda-capacity-helper.php';

// Checklist Operacional e Check-in/Check-out
require_once __DIR__ . '/includes/class-dps-agenda-checklist-service.php';
require_once __DIR__ . '/includes/class-dps-agenda-checkin-service.php';

// Hub centralizado de Agenda (Fase 2 - ReorganizaÃƒÂ§ÃƒÂ£o de Menus)
require_once __DIR__ . '/includes/class-dps-agenda-hub.php';

// FASE 1 - IntegraÃƒÂ§ÃƒÂµes Google (Calendar + Tasks): Infraestrutura
// Carrega apenas se extensÃƒÂµes OpenSSL estÃƒÂ£o disponÃƒÂ­veis (necessÃƒÂ¡rio para criptografia)
if ( extension_loaded( 'openssl' ) ) {
    require_once __DIR__ . '/includes/integrations/class-dps-google-auth.php';
    require_once __DIR__ . '/includes/integrations/class-dps-google-integrations-settings.php';
    
    // FASE 2 - Google Calendar: SincronizaÃƒÂ§ÃƒÂ£o (DPS Ã¢â€ â€™ Calendar)
    require_once __DIR__ . '/includes/integrations/class-dps-google-calendar-client.php';
    require_once __DIR__ . '/includes/integrations/class-dps-google-calendar-sync.php';
    
    // FASE 3 - Google Calendar: SincronizaÃƒÂ§ÃƒÂ£o Bidirecional (Calendar Ã¢â€¡â€ž DPS)
    require_once __DIR__ . '/includes/integrations/class-dps-google-calendar-webhook.php';
    
    // FASE 4 - Google Tasks: SincronizaÃƒÂ§ÃƒÂ£o (DPS Ã¢â€ â€™ Tasks)
    require_once __DIR__ . '/includes/integrations/class-dps-google-tasks-client.php';
    require_once __DIR__ . '/includes/integrations/class-dps-google-tasks-sync.php';
    
    // Inicializa interface de configuraÃƒÂ§ÃƒÂµes
    add_action( 'plugins_loaded', function() {
        if ( is_admin() ) {
            new DPS_Google_Integrations_Settings();
        }
        
        // Inicializa sincronizaÃƒÂ§ÃƒÂ£o Calendar e Tasks (se conectado)
        if ( DPS_Google_Auth::is_connected() ) {
            new DPS_Google_Calendar_Sync();
            
            // Inicializa webhook para sincronizaÃƒÂ§ÃƒÂ£o bidirecional
            $webhook = new DPS_Google_Calendar_Webhook();
            
            // Registra aÃƒÂ§ÃƒÂ£o para processar mudanÃƒÂ§as
            add_action( 'dps_google_calendar_process_changes', [ $webhook, 'process_calendar_changes' ] );
            
            // FASE 4: Inicializa sincronizaÃƒÂ§ÃƒÂ£o Google Tasks
            new DPS_Google_Tasks_Sync();
        }
    }, 20 );
}

class DPS_Agenda_Addon {
    
    // FASE 3: Usa traits para mÃƒÂ©todos auxiliares
    use DPS_Agenda_Renderer;
    use DPS_Agenda_Query;
    
    /**
     * InstÃƒÂ¢ncia ÃƒÂºnica (singleton).
     *
     * @since 1.4.1
     * @var DPS_Agenda_Addon|null
     */
    private static $instance = null;
    
    /**
     * Recupera a instÃƒÂ¢ncia ÃƒÂºnica.
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
     * NÃƒÂºmero de agendamentos por pÃƒÂ¡gina no modo "Todos".
     * 
     * @since 1.1.0
     */
    const APPOINTMENTS_PER_PAGE = 50;
    
    /**
     * Limite de agendamentos por dia nas queries de visualizaÃƒÂ§ÃƒÂ£o.
     * Pode ser filtrado via 'dps_agenda_daily_limit'.
     * 
     * @since 1.2.0
     */
    const DAILY_APPOINTMENTS_LIMIT = 200;
    
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
     * Retorna a URL do endpoint AJAX em formato relativo.
     *
     * A agenda roda em pagina frontend e pode ficar atras de proxy/CDN.
     * O caminho relativo evita mixed content e falhas de mesma origem
     * quando o esquema detectado pelo WordPress nao coincide com o da pagina.
     *
     * @since 2.0.2
     * @return string
     */
    private static function get_frontend_ajax_url() {
        $ajax_url = admin_url( 'admin-ajax.php', 'relative' );

        if ( ! is_string( $ajax_url ) || '' === $ajax_url ) {
            $ajax_url = admin_url( 'admin-ajax.php' );
        }

        return $ajax_url;
    }

    /**
     * Retorna configuraÃƒÂ§ÃƒÂ£o de status com labels, cores e ÃƒÂ­cones.
     *
     * Use este mÃƒÂ©todo para obter a configuraÃƒÂ§ÃƒÂ£o completa de todos os status
     * disponÃƒÂ­veis. Cada status contÃƒÂ©m label traduzida, cor de destaque,
     * cor de fundo e ÃƒÂ­cone.
     *
     * Exemplo de uso:
     * ```php
     * $config = DPS_Agenda_Addon::get_status_config();
     * $pending_color = $config[ DPS_Agenda_Addon::STATUS_PENDING ]['color'];
     * $pending_label = $config[ DPS_Agenda_Addon::STATUS_PENDING ]['label'];
     * ```
     *
     * @since 1.3.1
     * @return array ConfiguraÃƒÂ§ÃƒÂ£o completa de status. Cada item contÃƒÂ©m:
     *               - 'label' (string) Label traduzida
     *               - 'color' (string) Cor hex para borda/destaque
     *               - 'bg'    (string) Cor hex para fundo
     *               - 'icon'  (string) Emoji/ÃƒÂ­cone
     */
    public static function get_status_config() {
        return [
            self::STATUS_PENDING => [
                'label' => __( 'Pendente', 'dps-agenda-addon' ),
                'color' => '#f59e0b',
                'bg'    => '#fffbeb',
                'icon'  => 'Ã¢ÂÂ³',
            ],
            self::STATUS_FINISHED => [
                'label' => __( 'Finalizado', 'dps-agenda-addon' ),
                'color' => '#0ea5e9',
                'bg'    => '#f0f9ff',
                'icon'  => 'Ã¢Å“â€œ',
            ],
            self::STATUS_PAID => [
                'label' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                'color' => '#22c55e',
                'bg'    => '#f0fdf4',
                'icon'  => 'Ã°Å¸â€™Â°',
            ],
            self::STATUS_CANCELED => [
                'label' => __( 'Cancelado', 'dps-agenda-addon' ),
                'color' => '#ef4444',
                'bg'    => '#fef2f2',
                'icon'  => 'Ã¢ÂÅ’',
            ],
        ];
    }
    
    /**
     * Retorna label traduzida para um status.
     *
     * @since 1.3.1
     * @param string $status CÃƒÂ³digo do status.
     * @return string Label traduzida ou o prÃƒÂ³prio cÃƒÂ³digo se nÃƒÂ£o encontrado.
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
        // Verifica dependÃƒÂªncia do Finance Add-on apÃƒÂ³s todos os plugins terem sido carregados
        add_action( 'plugins_loaded', [ $this, 'check_finance_dependency' ] );

        // Cria pÃƒÂ¡ginas necessÃƒÂ¡rias ao ativar o plugin (apenas agenda, sem a pÃƒÂ¡gina de cobranÃƒÂ§as)
        register_activation_hook( __FILE__, [ $this, 'create_agenda_page' ] );
        // Limpa cron jobs ao desativar o plugin
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        // Registra shortcodes
        add_shortcode( 'dps_agenda_page', [ $this, 'render_agenda_shortcode' ] );
        // FASE 4: Shortcode para Dashboard Operacional
        add_shortcode( 'dps_agenda_dashboard', [ $this, 'render_dashboard_shortcode' ] );
        // Shortcode dps_charges_notes deprecated - redireciona para Finance
        add_shortcode( 'dps_charges_notes', [ $this, 'render_charges_notes_shortcode_deprecated' ] );
        // Enfileira scripts e estilos somente quando pÃƒÂ¡ginas especÃƒÂ­ficas forem exibidas
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // AJAX para atualizar status de agendamento (apenas usuÃƒÂ¡rios autenticados)
        add_action( 'wp_ajax_dps_update_status', [ $this, 'update_status_ajax' ] );
        
        // UX-1: AJAX para aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas de status
        add_action( 'wp_ajax_dps_agenda_quick_action', [ $this, 'quick_action_ajax' ] );
        
        // CONF-2: AJAX para atualizaÃƒÂ§ÃƒÂ£o de status de confirmaÃƒÂ§ÃƒÂ£o
        add_action( 'wp_ajax_dps_agenda_update_confirmation', [ $this, 'update_confirmation_ajax' ] );
        
        // FASE 3: AJAX para atualizaÃƒÂ§ÃƒÂ£o de status de TaxiDog
        add_action( 'wp_ajax_dps_agenda_update_taxidog', [ $this, 'update_taxidog_ajax' ] );
        
        // FASE 7: AJAX para solicitar TaxiDog
        add_action( 'wp_ajax_dps_agenda_request_taxidog', [ $this, 'request_taxidog_ajax' ] );
        
        // FASE 4: AJAX para salvar configuraÃƒÂ§ÃƒÂ£o de capacidade
        add_action( 'wp_ajax_dps_agenda_save_capacity', [ $this, 'save_capacity_ajax' ] );
        
        // FASE 5: AJAX para reenviar link de pagamento
        add_action( 'wp_ajax_dps_agenda_resend_payment', [ $this, 'resend_payment_ajax' ] );

        // Versionamento de agendamentos para evitar conflitos de escrita
        add_action( 'save_post_dps_agendamento', [ $this, 'ensure_appointment_version_meta' ], 10, 3 );

        // AJAX para obter detalhes de serviÃƒÂ§os de um agendamento (apenas usuÃƒÂ¡rios autenticados)
        add_action( 'wp_ajax_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );
        add_action( 'wp_ajax_nopriv_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );

        // FASE 2: AJAX para exportaÃƒÂ§ÃƒÂ£o PDF da agenda
        add_action( 'wp_ajax_dps_agenda_export_pdf', [ $this, 'export_pdf_ajax' ] );

        // FASE 4: AJAX para calendÃƒÂ¡rio mensal
        add_action( 'wp_ajax_dps_agenda_calendar_events', [ $this, 'calendar_events_ajax' ] );

        // FASE 5: AJAX para aÃƒÂ§ÃƒÂµes administrativas avanÃƒÂ§adas
        add_action( 'wp_ajax_dps_quick_reschedule', [ $this, 'quick_reschedule_ajax' ] );
        add_action( 'wp_ajax_dps_get_appointment_history', [ $this, 'get_appointment_history_ajax' ] );
        add_action( 'wp_ajax_dps_get_admin_kpis', [ $this, 'get_admin_kpis_ajax' ] );

        // Checklist Operacional: AJAX para atualizar etapas, registrar retrabalho e obter painel
        add_action( 'wp_ajax_dps_checklist_update', [ $this, 'checklist_update_ajax' ] );
        add_action( 'wp_ajax_dps_checklist_rework', [ $this, 'checklist_rework_ajax' ] );
        add_action( 'wp_ajax_dps_get_checklist_panel', [ $this, 'get_checklist_panel_ajax' ] );

        // Check-in / Check-out: AJAX para registrar entrada e saÃƒÂ­da
        add_action( 'wp_ajax_dps_appointment_checkin', [ $this, 'appointment_checkin_ajax' ] );
        add_action( 'wp_ajax_dps_appointment_checkout', [ $this, 'appointment_checkout_ajax' ] );

        // FASE 5: Registra alteraÃƒÂ§ÃƒÂµes de status no histÃƒÂ³rico
        add_action( 'dps_appointment_status_changed', [ $this, 'log_status_change' ], 10, 4 );

        // Agenda: agendamento de envio de lembretes diÃƒÂ¡rios
        add_action( 'init', [ $this, 'maybe_schedule_reminders' ] );
        add_action( 'dps_agenda_send_reminders', [ $this, 'send_reminders' ] );
        
        // FASE 4: Adiciona pÃƒÂ¡gina de Dashboard no admin
        add_action( 'admin_menu', [ $this, 'register_dashboard_admin_page' ], 20 );
        
        // FASE 5: Adiciona pÃƒÂ¡gina de ConfiguraÃƒÂ§ÃƒÂµes no admin
        add_action( 'admin_menu', [ $this, 'register_settings_admin_page' ], 21 );
        
        // FASE 4: Enfileira assets do Dashboard no admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
    }

    /**
     * Verifica se o Finance Add-on estÃƒÂ¡ ativo apÃƒÂ³s todos os plugins terem sido carregados.
     *
     * Este mÃƒÂ©todo ÃƒÂ© executado no hook 'plugins_loaded' para garantir que todos os plugins
     * jÃƒÂ¡ tenham sido carregados antes de verificar a existÃƒÂªncia da classe DPS_Finance_API.
     *
     * @since 1.0.1
     */
    public function check_finance_dependency() {
        if ( ! class_exists( 'DPS_Finance_API' ) ) {
            add_action( 'admin_notices', [ $this, 'finance_dependency_notice' ] );
            // Continua a carregar para nÃƒÂ£o quebrar completamente, mas funcionalidade financeira nÃƒÂ£o estarÃƒÂ¡ disponÃƒÂ­vel
        }
    }

    /**
     * Cria a pÃƒÂ¡gina de agenda de atendimentos.
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
     * FASE 4: Registra pÃƒÂ¡gina de Dashboard no admin.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegaÃƒÂ§ÃƒÂ£o unificada.
     * TambÃƒÂ©m acessÃƒÂ­vel pelo hub em dps-agenda-hub (aba "Dashboard").
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
        $hook = (string) $hook;

        $is_dashboard_page = 'desi-pet-shower_page_dps-agenda-dashboard' === $hook;
        $is_settings_page  = 'desi-pet-shower_page_dps-agenda-settings' === $hook;
        $is_hub_page       = 'desi-pet-shower_page_dps-agenda-hub' === $hook;

        $is_hub_dashboard_tab = (
            $is_hub_page
            && 'dashboard' === DPS_Admin_Tabs_Helper::get_active_tab( 'dashboard' )
        );

        if ( ! $is_dashboard_page && ! $is_settings_page && ! $is_hub_page ) {
            return;
        }

        wp_enqueue_style(
            'dps-design-tokens',
            DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
            [],
            DPS_BASE_VERSION
        );

        wp_enqueue_style(
            'dps-agenda-admin-css',
            plugin_dir_url( __FILE__ ) . 'assets/css/agenda-admin.css',
            [ 'dps-design-tokens' ],
            '2.1.0'
        );

        if ( $is_dashboard_page || $is_hub_dashboard_tab ) {
            wp_enqueue_style(
                'dps-dashboard-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/dashboard.css',
                [ 'dps-design-tokens', 'dps-agenda-admin-css' ],
                '2.1.0'
            );

            wp_enqueue_script( 'jquery' );
        }
    }

    /**
     * FASE 4: Renderiza a pÃƒÂ¡gina de Dashboard no admin.
     *
     * @since 1.3.0
     */
    public function render_dashboard_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'VocÃƒÂª nÃƒÂ£o tem permissÃƒÂ£o para acessar esta pÃƒÂ¡gina.', 'dps-agenda-addon' ) );
        }

        echo '<div class="wrap dps-agenda-admin-shell">';
        echo $this->render_dashboard_shortcode();
        echo '</div>';
    }

    /**
     * FASE 5: Registra pÃƒÂ¡gina de ConfiguraÃƒÂ§ÃƒÂµes no admin.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegaÃƒÂ§ÃƒÂ£o unificada.
     * TambÃƒÂ©m acessÃƒÂ­vel pelo hub em dps-agenda-hub (aba "ConfiguraÃƒÂ§ÃƒÂµes").
     *
     * @since 1.5.0
     */
    public function register_settings_admin_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'ConfiguraÃƒÂ§ÃƒÂµes da Agenda', 'dps-agenda-addon' ),
            __( 'ConfiguraÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ),
            'manage_options',
            'dps-agenda-settings',
            [ $this, 'render_settings_admin_page' ]
        );
    }

    /**
     * FASE 5: Renderiza a pÃƒÂ¡gina de ConfiguraÃƒÂ§ÃƒÂµes no admin.
     *
     * @since 1.5.0
     */
    public function render_settings_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'VocÃƒÂª nÃƒÂ£o tem permissÃƒÂ£o para acessar esta pÃƒÂ¡gina.', 'dps-agenda-addon' ) );
        }

        $settings_saved = false;

        if ( isset( $_POST['dps_save_settings'] ) && check_admin_referer( 'dps_agenda_settings' ) ) {
            $shop_address = isset( $_POST['dps_shop_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_shop_address'] ) ) : '';
            update_option( 'dps_shop_address', $shop_address );
            $settings_saved = true;
        }

        $shop_address = get_option( 'dps_shop_address', '' );
        ?>
        <div class="wrap dps-agenda-admin-shell">
            <div class="dps-agenda-admin-page">
                <section class="dps-agenda-admin-card">
                    <div class="dps-agenda-admin-card__header">
                        <div>
                            <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Agenda', 'dps-agenda-addon' ); ?></p>
                            <h1 class="dps-agenda-admin-title"><?php esc_html_e( 'ConfiguraÃƒÂ§ÃƒÂµes da Agenda', 'dps-agenda-addon' ); ?></h1>
                            <p class="dps-agenda-admin-description">
                                <?php esc_html_e( 'Padronize os dados operacionais usados na logÃƒÂ­stica da agenda, nas rotas do GPS e nas integraÃƒÂ§ÃƒÂµes administrativas.', 'dps-agenda-addon' ); ?>
                            </p>
                        </div>
                        <div class="dps-agenda-admin-chips" aria-label="<?php esc_attr_e( 'Resumo do contexto', 'dps-agenda-addon' ); ?>">
                            <span class="dps-agenda-admin-chip"><?php esc_html_e( 'LogÃƒÂ­stica', 'dps-agenda-addon' ); ?></span>
                            <span class="dps-agenda-admin-chip"><?php esc_html_e( 'GPS', 'dps-agenda-addon' ); ?></span>
                        </div>
                    </div>

                    <?php if ( $settings_saved ) : ?>
                        <div class="dps-agenda-admin-notice dps-agenda-admin-notice--success" role="status">
                            <?php esc_html_e( 'ConfiguraÃƒÂ§ÃƒÂµes salvas com sucesso!', 'dps-agenda-addon' ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="dps-agenda-admin-grid">
                        <form method="post" action="" class="dps-agenda-admin-card dps-agenda-admin-card--subtle dps-agenda-admin-form">
                            <?php wp_nonce_field( 'dps_agenda_settings' ); ?>

                            <div class="dps-agenda-admin-field dps-agenda-admin-field--full">
                                <label class="dps-agenda-admin-field__label" for="dps_shop_address">
                                    <?php esc_html_e( 'EndereÃƒÂ§o do Banho e Tosa', 'dps-agenda-addon' ); ?>
                                </label>
                                <p class="dps-agenda-admin-field__hint">
                                    <?php esc_html_e( 'Use o endereÃƒÂ§o completo da operaÃƒÂ§ÃƒÂ£o para rotas, mapas e referÃƒÂªncias logÃƒÂ­sticas exibidas na Agenda.', 'dps-agenda-addon' ); ?>
                                </p>
                                <textarea
                                    name="dps_shop_address"
                                    id="dps_shop_address"
                                    rows="5"
                                    class="dps-agenda-admin-textarea"
                                    placeholder="<?php esc_attr_e( 'Ex: Rua Exemplo, 123, Centro, SÃƒÂ£o Paulo - SP, CEP 01234-567', 'dps-agenda-addon' ); ?>"
                                ><?php echo esc_textarea( $shop_address ); ?></textarea>
                                <p class="dps-agenda-admin-field__description">
                                    <?php esc_html_e( 'O valor serÃƒÂ¡ usado como origem nas rotas do GPS e como contexto operacional para os atendimentos com deslocamento.', 'dps-agenda-addon' ); ?>
                                </p>
                            </div>

                            <div class="dps-agenda-admin-form-actions">
                                <button type="submit" name="dps_save_settings" class="dps-btn dps-btn--primary">
                                    <?php esc_html_e( 'Salvar configuraÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ); ?>
                                </button>
                            </div>
                        </form>

                        <aside class="dps-agenda-admin-card dps-agenda-admin-card--subtle dps-agenda-admin-sidecard">
                            <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Impacto operacional', 'dps-agenda-addon' ); ?></p>
                            <h2 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'Onde esta configuraÃƒÂ§ÃƒÂ£o aparece', 'dps-agenda-addon' ); ?></h2>
                            <ul class="dps-agenda-admin-list">
                                <li><?php esc_html_e( 'BotÃƒÂ£o Ã¢â‚¬Å“Abrir rotaÃ¢â‚¬Â na aba de detalhes da Agenda.', 'dps-agenda-addon' ); ?></li>
                                <li><?php esc_html_e( 'ReferÃƒÂªncia de deslocamento para atendimentos com TaxiDog.', 'dps-agenda-addon' ); ?></li>
                                <li><?php esc_html_e( 'Fluxos operacionais que dependem do ponto de origem da loja.', 'dps-agenda-addon' ); ?></li>
                            </ul>
                            <p class="dps-agenda-admin-card__note">
                                <?php esc_html_e( 'Mantenha esse endereÃƒÂ§o atualizado para evitar rotas incorretas na operaÃƒÂ§ÃƒÂ£o diÃƒÂ¡ria.', 'dps-agenda-addon' ); ?>
                            </p>
                        </aside>
                    </div>
                </section>
            </div>
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
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return '<p>' . esc_html__( 'Acesso negado.', 'dps-agenda-addon' ) . '</p>';
        }

        $selected_date = isset( $_GET['dashboard_date'] )
            ? sanitize_text_field( wp_unslash( $_GET['dashboard_date'] ) )
            : current_time( 'Y-m-d' );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
            $selected_date = current_time( 'Y-m-d' );
        }

        $selected_date_label = date_i18n( get_option( 'date_format' ), strtotime( $selected_date ) );
        $page_slug           = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'dps-agenda-dashboard';
        $kpis                = DPS_Agenda_Dashboard_Service::get_daily_kpis( $selected_date );
        $next_appointments   = DPS_Agenda_Dashboard_Service::get_next_appointments( $selected_date, 10 );

        ob_start();
        ?>
        <div class="dps-dashboard-wrapper dps-agenda-admin-page">
            <section class="dps-dashboard-date-selector dps-agenda-admin-card">
                <div class="dps-dashboard-date-selector__header">
                    <div class="dps-dashboard-section-header">
                        <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Agenda', 'dps-agenda-addon' ); ?></p>
                        <h2 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'Dashboard operacional', 'dps-agenda-addon' ); ?></h2>
                        <p class="dps-dashboard-date-selector__description">
                            <?php esc_html_e( 'Acompanhe volume, prÃƒÂ³ximos atendimentos e capacidade semanal em uma leitura ÃƒÂºnica e consistente com o padrÃƒÂ£o M3.', 'dps-agenda-addon' ); ?>
                        </p>
                    </div>
                    <div class="dps-agenda-admin-chips">
                        <span class="dps-agenda-admin-chip dps-agenda-admin-chip--primary"><?php echo esc_html( $selected_date_label ); ?></span>
                        <span class="dps-agenda-admin-chip"><?php esc_html_e( 'VisÃƒÂ£o diÃƒÂ¡ria', 'dps-agenda-addon' ); ?></span>
                    </div>
                </div>

                <form method="get" class="dps-dashboard-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>">

                    <div class="dps-dashboard-date-controls">
                        <button type="button" class="dps-btn dps-btn--ghost dps-dashboard-quick-date" data-days="-1"><?php esc_html_e( 'Ontem', 'dps-agenda-addon' ); ?></button>
                        <button type="button" class="dps-btn dps-btn--tonal dps-dashboard-quick-date" data-days="0"><?php esc_html_e( 'Hoje', 'dps-agenda-addon' ); ?></button>
                        <button type="button" class="dps-btn dps-btn--ghost dps-dashboard-quick-date" data-days="1"><?php esc_html_e( 'AmanhÃƒÂ£', 'dps-agenda-addon' ); ?></button>
                        <input type="date" name="dashboard_date" value="<?php echo esc_attr( $selected_date ); ?>" class="dps-dashboard-date-input">
                        <button type="submit" class="dps-btn dps-btn--primary dps-dashboard-submit"><?php esc_html_e( 'Atualizar painel', 'dps-agenda-addon' ); ?></button>
                    </div>
                </form>
            </section>

            <div class="dps-dashboard-kpis">
                <section class="dps-dashboard-kpi-section dps-agenda-admin-card dps-agenda-admin-card--subtle">
                    <h3><?php esc_html_e( 'Atendimentos', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card(
                            __( 'Total', 'dps-agenda-addon' ),
                            $kpis['total_counts']['total'],
                            sprintf( __( 'ManhÃƒÂ£: %d | Tarde: %d', 'dps-agenda-addon' ), $kpis['total_counts']['morning'], $kpis['total_counts']['afternoon'] ),
                            'primary'
                        );
                        ?>
                    </div>
                </section>

                <section class="dps-dashboard-kpi-section dps-agenda-admin-card dps-agenda-admin-card--subtle">
                    <h3><?php esc_html_e( 'ConfirmaÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'Confirmados', 'dps-agenda-addon' ), $kpis['confirmation_stats']['confirmed'], '', 'success' );
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'NÃƒÂ£o confirmados', 'dps-agenda-addon' ), $kpis['confirmation_stats']['not_confirmed'], '', 'warning' );
                        ?>
                    </div>
                </section>

                <section class="dps-dashboard-kpi-section dps-agenda-admin-card dps-agenda-admin-card--subtle">
                    <h3><?php esc_html_e( 'ExecuÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'ConcluÃƒÂ­dos', 'dps-agenda-addon' ), $kpis['execution_stats']['completed'], '', 'success' );
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'Cancelados', 'dps-agenda-addon' ), $kpis['execution_stats']['canceled'], '', 'error' );

                        if ( $kpis['execution_stats']['late'] > 0 ) {
                            echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'Atrasados', 'dps-agenda-addon' ), $kpis['execution_stats']['late'], __( 'Pendentes apÃƒÂ³s o horÃƒÂ¡rio previsto', 'dps-agenda-addon' ), 'warning' );
                        }
                        ?>
                    </div>
                </section>

                <section class="dps-dashboard-kpi-section dps-agenda-admin-card dps-agenda-admin-card--subtle">
                    <h3><?php esc_html_e( 'Especiais', 'dps-agenda-addon' ); ?></h3>
                    <div class="dps-dashboard-cards">
                        <?php
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'TaxiDog', 'dps-agenda-addon' ), $kpis['special_stats']['with_taxidog'], '', 'tertiary' );
                        echo DPS_Agenda_Dashboard_Service::render_kpi_card( __( 'CobranÃƒÂ§a pendente', 'dps-agenda-addon' ), $kpis['special_stats']['pending_payment'], '', 'warning' );
                        ?>
                    </div>
                </section>
            </div>

            <?php if ( ! empty( $next_appointments ) ) : ?>
                <section class="dps-dashboard-next-appointments dps-agenda-admin-card">
                    <div class="dps-dashboard-section-header">
                        <div>
                            <h3><?php esc_html_e( 'PrÃƒÂ³ximos atendimentos', 'dps-agenda-addon' ); ?></h3>
                            <p><?php esc_html_e( 'Leitura rÃƒÂ¡pida dos prÃƒÂ³ximos horÃƒÂ¡rios para a operaÃƒÂ§ÃƒÂ£o do dia.', 'dps-agenda-addon' ); ?></p>
                        </div>
                    </div>

                    <div class="dps-dashboard-table-wrapper">
                        <table class="dps-dashboard-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Hora', 'dps-agenda-addon' ); ?></th>
                                    <th><?php esc_html_e( 'Pet', 'dps-agenda-addon' ); ?></th>
                                    <th><?php esc_html_e( 'Tutor', 'dps-agenda-addon' ); ?></th>
                                    <th><?php esc_html_e( 'ServiÃƒÂ§os', 'dps-agenda-addon' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'dps-agenda-addon' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $next_appointments as $appt ) : ?>
                                    <?php
                                    $status_config     = DPS_Agenda_Addon::get_status_config();
                                    $status_slug       = isset( $appt['status'] ) ? (string) $appt['status'] : 'pendente';
                                    $status_label      = isset( $status_config[ $status_slug ]['label'] ) ? $status_config[ $status_slug ]['label'] : $status_slug;
                                    $status_chip_class = 'dps-agenda-admin-chip--warning';

                                    if ( in_array( $status_slug, [ self::STATUS_FINISHED, self::STATUS_PAID ], true ) ) {
                                        $status_chip_class = 'dps-agenda-admin-chip--success';
                                    } elseif ( self::STATUS_CANCELED === $status_slug ) {
                                        $status_chip_class = 'dps-agenda-admin-chip--error';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $appt['time'] ); ?></strong></td>
                                        <td><?php echo esc_html( $appt['pet_name'] ); ?></td>
                                        <td><?php echo esc_html( $appt['client_name'] ); ?></td>
                                        <td><?php echo esc_html( $appt['services'] ?: '-' ); ?></td>
                                        <td><span class="dps-agenda-admin-chip dps-dashboard-table-status <?php echo esc_attr( $status_chip_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php else : ?>
                <div class="dps-dashboard-empty dps-agenda-admin-card" role="status">
                    <p><?php esc_html_e( 'Nenhum atendimento prÃƒÂ³ximo encontrado para esta data.', 'dps-agenda-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <section class="dps-dashboard-capacity-section dps-agenda-admin-card">
                <div class="dps-dashboard-capacity-header">
                    <div class="dps-dashboard-section-header">
                        <div>
                            <h3><?php esc_html_e( 'Capacidade da semana', 'dps-agenda-addon' ); ?></h3>
                            <p><?php esc_html_e( 'Use a capacidade como referÃƒÂªncia operacional para evitar sobrecarga e antecipar gargalos.', 'dps-agenda-addon' ); ?></p>
                        </div>
                    </div>

                    <?php
                    $week_dates     = DPS_Agenda_Capacity_Helper::get_week_dates( $selected_date );
                    $prev_week_date = date( 'Y-m-d', strtotime( $week_dates['start'] . ' -7 days' ) );
                    $next_week_date = date( 'Y-m-d', strtotime( $week_dates['start'] . ' +7 days' ) );
                    $prev_week_url  = add_query_arg( [ 'page' => $page_slug, 'dashboard_date' => $prev_week_date ], admin_url( 'admin.php' ) );
                    $next_week_url  = add_query_arg( [ 'page' => $page_slug, 'dashboard_date' => $next_week_date ], admin_url( 'admin.php' ) );
                    ?>
                    <div class="dps-capacity-week-nav">
                        <a href="<?php echo esc_url( $prev_week_url ); ?>" class="dps-btn dps-btn--ghost dps-dashboard-week-nav-link"><?php esc_html_e( 'Semana anterior', 'dps-agenda-addon' ); ?></a>
                        <span class="dps-capacity-week-label">
                            <?php echo esc_html( sprintf( __( 'Semana de %s a %s', 'dps-agenda-addon' ), date_i18n( 'd/m', strtotime( $week_dates['start'] ) ), date_i18n( 'd/m/Y', strtotime( $week_dates['end'] ) ) ) ); ?>
                        </span>
                        <a href="<?php echo esc_url( $next_week_url ); ?>" class="dps-btn dps-btn--ghost dps-dashboard-week-nav-link"><?php esc_html_e( 'PrÃƒÂ³xima semana', 'dps-agenda-addon' ); ?></a>
                    </div>
                </div>

                <div class="dps-capacity-config">
                    <h4><?php esc_html_e( 'ConfiguraÃƒÂ§ÃƒÂ£o de capacidade mÃƒÂ¡xima', 'dps-agenda-addon' ); ?></h4>
                    <?php $capacity_config = DPS_Agenda_Capacity_Helper::get_capacity_config(); ?>
                    <form id="dps-capacity-config-form" class="dps-capacity-form">
                        <div class="dps-capacity-inputs">
                            <div class="dps-capacity-input-group">
                                <label for="capacity_morning"><?php esc_html_e( 'ManhÃƒÂ£ (08:00-11:59)', 'dps-agenda-addon' ); ?></label>
                                <input type="number" id="capacity_morning" name="morning" value="<?php echo esc_attr( $capacity_config['morning'] ); ?>" min="1" max="100">
                                <span class="description"><?php esc_html_e( 'atendimentos', 'dps-agenda-addon' ); ?></span>
                            </div>
                            <div class="dps-capacity-input-group">
                                <label for="capacity_afternoon"><?php esc_html_e( 'Tarde (12:00-17:59)', 'dps-agenda-addon' ); ?></label>
                                <input type="number" id="capacity_afternoon" name="afternoon" value="<?php echo esc_attr( $capacity_config['afternoon'] ); ?>" min="1" max="100">
                                <span class="description"><?php esc_html_e( 'atendimentos', 'dps-agenda-addon' ); ?></span>
                            </div>
                            <button type="submit" class="dps-btn dps-btn--primary dps-capacity-submit"><?php esc_html_e( 'Salvar capacidade', 'dps-agenda-addon' ); ?></button>
                        </div>
                    </form>
                    <p class="description"><?php esc_html_e( 'A capacidade ÃƒÂ© uma referÃƒÂªncia operacional e nÃƒÂ£o bloqueia agendamentos automaticamente.', 'dps-agenda-addon' ); ?></p>
                </div>

                <?php echo DPS_Agenda_Capacity_Helper::render_capacity_heatmap( $week_dates['start'], $week_dates['end'] ); ?>
            </section>

            <div class="dps-dashboard-actions">
                <?php
                $agenda_page_id = get_option( 'dps_agenda_page_id' );
                if ( $agenda_page_id ) {
                    $agenda_permalink = get_permalink( $agenda_page_id );
                    if ( $agenda_permalink && is_string( $agenda_permalink ) ) {
                        $agenda_url = add_query_arg( 'dps_date', $selected_date, $agenda_permalink );
                        ?>
                        <a href="<?php echo esc_url( $agenda_url ); ?>" class="dps-btn dps-btn--primary dps-btn--large"><?php esc_html_e( 'Ver agenda completa', 'dps-agenda-addon' ); ?></a>
                        <?php
                    }
                }
                ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
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
                        setTimeout(function(){
                            location.reload();
                        }, 1000);
                    } else {
                        alert(resp.data ? resp.data.message : 'Erro ao salvar configuraÃƒÂ§ÃƒÂ£o.');
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                }).fail(function(){
                    alert('Erro de comunicaÃƒÂ§ÃƒÂ£o ao salvar configuraÃƒÂ§ÃƒÂ£o.');
                    submitBtn.prop('disabled', false).text(originalText);
                });
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * Exibe aviso no admin se Finance Add-on nÃƒÂ£o estiver ativo.
     *
     * @since 1.1.0
     */
    public function finance_dependency_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Agenda Add-on:', 'dps-agenda-addon' ); ?></strong>
                <?php esc_html_e( 'O Finance Add-on ÃƒÂ© recomendado para funcionalidade completa de cobranÃƒÂ§as. Algumas funcionalidades financeiras podem nÃƒÂ£o estar disponÃƒÂ­veis.', 'dps-agenda-addon' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Cria a pÃƒÂ¡gina para listar cobranÃƒÂ§as e notas geradas pelo addon.
     */
    public function create_charges_page() {
        $title = __( 'CobranÃƒÂ§as e Notas', 'dps-agenda-addon' );
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
     * Garante que o meta de versÃƒÂ£o do agendamento seja inicializado.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post sendo salvo.
     * @param bool    $update  Indica se ÃƒÂ© uma atualizaÃƒÂ§ÃƒÂ£o.
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
     * Enfileira os scripts e estilos necessÃƒÂ¡rios apenas quando a pÃƒÂ¡gina de agenda for carregada.
     * 
     * CSS e JS agora sÃƒÂ£o carregados de arquivos externos (assets/css e assets/js)
     * para melhor cache do navegador, minificaÃƒÂ§ÃƒÂ£o e separaÃƒÂ§ÃƒÂ£o de responsabilidades.
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
            // Design tokens M3 Expressive (devem ser carregados antes de qualquer CSS)
            wp_enqueue_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                DPS_BASE_VERSION
            );
            wp_enqueue_style(
                'dps-base-style',
                DPS_BASE_URL . 'assets/css/dps-base.css',
                [ 'dps-design-tokens' ],
                DPS_BASE_VERSION
            );
            // CSS da agenda com design tokens M3 Expressive
            wp_enqueue_style(
                'dps-agenda-addon-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/agenda-addon.css',
                [ 'dps-design-tokens' ],
                '2.2.0'
            );

            // CSS do Checklist Operacional e Check-in/Check-out
            wp_enqueue_style(
                'dps-checklist-checkin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/checklist-checkin.css',
                [ 'dps-design-tokens' ],
                '1.1.0'
            );
            
            // Modal de serviÃƒÂ§os (precisa ser carregado antes do agenda-addon.js)
            wp_enqueue_script( 
                'dps-services-modal', 
                plugin_dir_url( __FILE__ ) . 'assets/js/services-modal.js', 
                [ 'jquery' ], 
                '1.0.0', 
                true 
            );
            
            // Script principal da agenda (atualizaÃƒÂ§ÃƒÂ£o de status e interaÃƒÂ§ÃƒÂµes)
            wp_enqueue_script( 
                'dps-agenda-addon', 
                plugin_dir_url( __FILE__ ) . 'assets/js/agenda-addon.js', 
                [ 'jquery', 'dps-services-modal' ],
                '1.5.0',
                true 
            );

            wp_enqueue_script(
                'dps-pet-profile-modal',
                plugin_dir_url( __FILE__ ) . 'assets/js/pet-profile-modal.js',
                [ 'jquery', 'dps-agenda-addon' ],
                '1.0.2',
                true
            );

            // Script do Checklist Operacional e Check-in/Check-out
            wp_enqueue_script(
                'dps-checklist-checkin',
                plugin_dir_url( __FILE__ ) . 'assets/js/checklist-checkin.js',
                [ 'jquery', 'dps-agenda-addon' ],
                '1.1.0',
                true
            );

            wp_localize_script( 'dps-checklist-checkin', 'DPS_Checklist_Checkin', [
                'ajax'            => self::get_frontend_ajax_url(),
                'nonce_checklist' => wp_create_nonce( 'dps_checklist' ),
                'nonce_checkin'   => wp_create_nonce( 'dps_checkin' ),
                'messages'        => [
                    'markDone'         => __( 'Concluir', 'dps-agenda-addon' ),
                    'undo'             => __( 'Desfazer', 'dps-agenda-addon' ),
                    'rework'           => __( 'Refazer', 'dps-agenda-addon' ),
                    'skip'             => __( 'Pular', 'dps-agenda-addon' ),
                    'reworkTitle'      => __( 'Registrar retrabalho', 'dps-agenda-addon' ),
                    'reworkPlaceholder'=> __( 'Motivo do retrabalho (ex.: secagem insuficiente)...', 'dps-agenda-addon' ),
                    'confirmRework'    => __( 'Confirmar retrabalho', 'dps-agenda-addon' ),
                    'cancel'           => __( 'Cancelar', 'dps-agenda-addon' ),
                    'saving'           => __( 'Salvando...', 'dps-agenda-addon' ),
                    'error'            => __( 'Erro ao salvar. Tente novamente.', 'dps-agenda-addon' ),
                    'checkin'          => __( 'Check-in', 'dps-agenda-addon' ),
                    'checkout'         => __( 'Check-out', 'dps-agenda-addon' ),
                    'sendWhatsApp'     => __( 'Enviar relatÃƒÂ³rio via WhatsApp', 'dps-agenda-addon' ),
                ],
            ] );
            
            wp_localize_script( 'dps-agenda-addon', 'DPS_AG_Addon', [
                'ajax'          => self::get_frontend_ajax_url(),
                'nonce_status'  => wp_create_nonce( 'dps_update_status' ),
                'nonce_services'=> wp_create_nonce( 'dps_get_services_details' ),
                'nonce_export_pdf' => wp_create_nonce( 'dps_agenda_export_pdf' ),
                // UX-1: Nonce para aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas
                'nonce_quick_action' => wp_create_nonce( 'dps_agenda_quick_action' ),
                // CONF-2: Nonce para confirmaÃƒÂ§ÃƒÂ£o
                'nonce_confirmation' => wp_create_nonce( 'dps_agenda_confirmation' ),
                // FASE 3: Nonce para TaxiDog
                'nonce_taxidog'      => wp_create_nonce( 'dps_agenda_taxidog' ),
                // FASE 4: Nonce para capacidade
                'nonce_capacity'     => wp_create_nonce( 'dps_agenda_capacity' ),
                // FASE 5: Nonce para reenvio de pagamento
                'nonce_resend_payment' => wp_create_nonce( 'dps_agenda_resend_payment' ),
                // FASE 5: Nonces para funcionalidades administrativas avanÃƒÂ§adas
                'nonce_reschedule'=> wp_create_nonce( 'dps_quick_reschedule' ),
                'nonce_history'   => wp_create_nonce( 'dps_appointment_history' ),
                'nonce_kpis'      => wp_create_nonce( 'dps_admin_kpis' ),
                // Checklist popup
                'nonce_checklist' => wp_create_nonce( 'dps_checklist' ),
                'statuses'      => [
                    'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
                    'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
                    'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                    'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
                ],
                'messages'      => [
                    'updating' => __( 'Atualizando status...', 'dps-agenda-addon' ),
                    'updated'  => __( 'Status atualizado!', 'dps-agenda-addon' ),
                    'error'    => __( 'NÃƒÂ£o foi possÃƒÂ­vel atualizar o status.', 'dps-agenda-addon' ),
                    'versionConflict' => __( 'Esse agendamento foi atualizado por outro usuÃƒÂ¡rio. Atualize a pÃƒÂ¡gina para ver as alteraÃƒÂ§ÃƒÂµes.', 'dps-agenda-addon' ),
                    // FASE 5: Mensagens para funcionalidades administrativas
                    'reschedule_title'  => __( 'Reagendar Agendamento', 'dps-agenda-addon' ),
                    'new_date'          => __( 'Nova data', 'dps-agenda-addon' ),
                    'new_time'          => __( 'Novo horÃƒÂ¡rio', 'dps-agenda-addon' ),
                    'cancel'            => __( 'Cancelar', 'dps-agenda-addon' ),
                    'save'              => __( 'Salvar', 'dps-agenda-addon' ),
                    'saving'            => __( 'Salvando...', 'dps-agenda-addon' ),
                    'fill_all_fields'   => __( 'Preencha todos os campos.', 'dps-agenda-addon' ),
                    'no_history'        => __( 'Sem histÃƒÂ³rico de alteraÃƒÂ§ÃƒÂµes.', 'dps-agenda-addon' ),
                    'history_title'     => __( 'HistÃƒÂ³rico de AlteraÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ),
                    'action_created'    => __( 'Criado', 'dps-agenda-addon' ),
                    'action_status_change' => __( 'Status alterado', 'dps-agenda-addon' ),
                    'action_rescheduled'   => __( 'Reagendado', 'dps-agenda-addon' ),
                    'close'           => __( 'Fechar', 'dps-agenda-addon' ),
                    'checklistTitle'  => __( 'Checklist Operacional', 'dps-agenda-addon' ),
                    'checklistLoading'=> __( 'Carregando checklist...', 'dps-agenda-addon' ),
                    'checklistError'  => __( 'NÃƒÂ£o foi possÃƒÂ­vel carregar o checklist.', 'dps-agenda-addon' ),
                    'historyDialogTitle' => __( 'Linha do tempo do atendimento', 'dps-agenda-addon' ),
                    'historyEmptyTitle'  => __( 'HistÃƒÂ³rico indisponÃƒÂ­vel', 'dps-agenda-addon' ),
                    'historyClose'       => __( 'Fechar histÃƒÂ³rico', 'dps-agenda-addon' ),
                    'confirmAction'      => __( 'Confirmar aÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ),
                    'confirmResendPayment' => __( 'Deseja reenviar o link de pagamento para este atendimento?', 'dps-agenda-addon' ),
                    'confirmTaxidogRequest' => __( 'Deseja solicitar TaxiDog para este atendimento?', 'dps-agenda-addon' ),
                    'confirmProceed'     => __( 'Confirmar', 'dps-agenda-addon' ),
                    'paymentDialogTitle' => __( 'CobranÃƒÂ§a do atendimento', 'dps-agenda-addon' ),
                    'copyPaymentLink'    => __( 'Copiar link de pagamento', 'dps-agenda-addon' ),
                    'rescheduleDialogTitle' => __( 'Reagendar atendimento', 'dps-agenda-addon' ),
                    'operationPanelOpened' => __( 'Painel operacional aberto.', 'dps-agenda-addon' ),
                    'operationPanelContinue' => __( 'Continue o checklist e o check-in no painel expandido.', 'dps-agenda-addon' ),
                ],
                'reloadDelay'  => 700,
            ] );
        }

        // Charges/notes page: pode precisar de estilos extras
        if ( $is_charges_target_page || $has_charges_shortcode ) {
            // carregue CSS para tabelas se necessÃƒÂ¡rio; podemos reutilizar estilos de dps-table se o tema os define.
        }

        // Base page (histÃƒÂ³rico, pÃƒÂ¡gina do cliente): carrega CSS dos resumos operacionais
        $has_base_shortcode   = $current_post ? has_shortcode( $current_content, 'dps_base' ) : false;
        $has_portal_shortcode = $current_post ? has_shortcode( $current_content, 'dps_client_portal' ) : false;
        if ( $has_base_shortcode || $has_portal_shortcode ) {
            wp_enqueue_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                DPS_BASE_VERSION
            );
            wp_enqueue_style(
                'dps-checklist-checkin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/checklist-checkin.css',
                [ 'dps-design-tokens' ],
                '1.1.0'
            );
        }
    }

    /**
     * Renderiza a linha atualizada de um atendimento conforme a aba ativa.
     *
     * @since 2.0.1
     * @param WP_Post $appointment Agendamento.
     * @return string
     */
    private function render_row_html_for_active_tab( $appointment ) {
        if ( ! $appointment instanceof WP_Post ) {
            return '';
        }

        $agenda_tab = isset( $_POST['agenda_tab'] ) ? sanitize_key( wp_unslash( $_POST['agenda_tab'] ) ) : 'visao-rapida';
        if ( ! in_array( $agenda_tab, [ 'visao-rapida', 'operacao', 'detalhes' ], true ) ) {
            $agenda_tab = 'visao-rapida';
        }

        $column_labels = $this->get_column_labels();

        if ( 'operacao' === $agenda_tab ) {
            return $this->render_appointment_row_tab2_m3( $appointment, $column_labels );
        }

        if ( 'detalhes' === $agenda_tab ) {
            return $this->render_appointment_row_tab3_m3( $appointment, $column_labels );
        }

        return $this->render_appointment_row_tab1_m3( $appointment, $column_labels );
    }

    /**
     * Renderiza o conteÃƒÂºdo do shortcode [dps_agenda_page].
     */
    public function render_agenda_shortcode() {
        // Desabilita cache da pÃƒÂ¡gina para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        ob_start();
        /*
         * Verifica permissÃƒÂ£o: somente administradores (capacidade manage_options).
         * Anteriormente, funcionÃƒÂ¡rios tambÃƒÂ©m tinham acesso ÃƒÂ  agenda, mas por questÃƒÂµes
         * de seguranÃƒÂ§a e a pedido do cliente, o acesso agora ÃƒÂ© restrito aos
         * administradores. Caso o usuÃƒÂ¡rio nÃƒÂ£o esteja logado ou nÃƒÂ£o possua a
         * capacidade de administrador, exibimos um link de login.
         */
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            $login_url = wp_login_url( DPS_URL_Builder::safe_get_permalink() );
            return '<p>' . esc_html__( 'VocÃƒÂª precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
        }
        // Nenhum controle adicional de cookies ÃƒÂ© necessÃƒÂ¡rio; o acesso ÃƒÂ© controlado por permissÃƒÂµes do usuÃƒÂ¡rio.
        // Wrapper da agenda (CSS agora carregado de arquivo externo via enqueue_assets)
        echo '<div class="dps-agenda-wrapper">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo helper
        echo DPS_Message_Helper::display_messages();
        // Acesso permitido: mostrar agenda
        // Filtro de data e visualizaÃƒÂ§ÃƒÂ£o
        $selected_date = isset( $_GET['dps_date'] ) ? sanitize_text_field( $_GET['dps_date'] ) : '';
        if ( ! $selected_date ) {
            $selected_date = current_time( 'Y-m-d' );
        }
        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        // Determine if we are in a weekly list or calendar view. Both share similar navigation logic.
        $is_week_view = ( $view === 'week' || $view === 'calendar' );
        // Exibe todos os atendimentos? Se show_all=1, ignoramos view e data para a listagem principal
        $show_all = isset( $_GET['show_all'] ) ? sanitize_text_field( $_GET['show_all'] ) : '';
        // Links para dia/semana anterior/prÃƒÂ³ximo, preservando apenas o contexto da aba
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
        // Base URL sem parametros volateis; a navegacao preserva apenas a aba ativa.
        $base_url = DPS_URL_Builder::safe_get_permalink();
        $current_tab = isset( $_GET['agenda_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['agenda_tab'] ) ) : 'visao-rapida';
        $current_args = [ 'agenda_tab' => $current_tab ];
        $nav_args = $current_args;
        $prev_args = array_merge( $nav_args, [ 'dps_date' => $prev_date, 'view' => $view ] );
        $next_args = array_merge( $nav_args, [ 'dps_date' => $next_date, 'view' => $view ] );
        $today = current_time( 'Y-m-d' );
        $today_args = array_merge( $current_args, [ 'dps_date' => $today, 'view' => $view ] );
        $all_view_args = array_merge( $nav_args, [ 'show_all' => '1' ] );
        $focused_view_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => $view ] );
        $scope_label        = $this->get_agenda_scope_label( $selected_date, $view, ! empty( $show_all ) );

        echo '<header class="dps-agenda-header dps-agenda-header--refresh">';
        echo '<div class="dps-agenda-title">';
        echo '<h3>' . esc_html__( 'Agenda de Atendimentos', 'dps-agenda-addon' ) . '</h3>';
        echo '<p class="dps-current-date dps-current-date--header" title="' . esc_attr__( 'Periodo em foco', 'dps-agenda-addon' ) . '">';
        echo '<span class="dps-current-date__label">' . esc_html__( 'Periodo ativo', 'dps-agenda-addon' ) . '</span>';
        echo '<strong>' . esc_html( $scope_label ) . '</strong>';
        echo '</p>';
        echo '</div>';

        echo '</header>';

        echo '<div class="dps-agenda-controls-wrapper">';
        echo '<div class="dps-agenda-nav dps-agenda-nav--primary">';
        echo '<div class="dps-agenda-nav-group dps-agenda-nav-group--views">';
        echo '<span class="dps-nav-label">' . esc_html__( 'Ver:', 'dps-agenda-addon' ) . '</span>';

        $view_buttons = [];
        $day_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'day' ] );
        $day_active = ( $view === 'day' && ! $show_all ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $day_args, $base_url ) ) . '" class="dps-view-btn' . $day_active . '" title="' . esc_attr__( 'Ver lista diaria', 'dps-agenda-addon' ) . '"' . ( $day_active ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Dia', 'dps-agenda-addon' ) . '</a>';

        $week_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'week' ] );
        $week_active = ( $view === 'week' && ! $show_all ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $week_args, $base_url ) ) . '" class="dps-view-btn' . $week_active . '" title="' . esc_attr__( 'Ver lista semanal', 'dps-agenda-addon' ) . '"' . ( $week_active ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Semana', 'dps-agenda-addon' ) . '</a>';

        $cal_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'calendar' ] );
        $cal_active = ( $view === 'calendar' && ! $show_all ) ? ' dps-view-btn--active' : '';
        $view_buttons[] = '<a href="' . esc_url( add_query_arg( $cal_args, $base_url ) ) . '" class="dps-view-btn' . $cal_active . '" title="' . esc_attr__( 'Ver calendario mensal', 'dps-agenda-addon' ) . '"' . ( $cal_active ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'Mes', 'dps-agenda-addon' ) . '</a>';

        if ( $show_all ) {
            $view_buttons[] = '<a href="' . esc_url( add_query_arg( $focused_view_args, $base_url ) ) . '" class="dps-view-btn dps-view-btn--active" title="' . esc_attr__( 'Voltar para o periodo atual', 'dps-agenda-addon' ) . '" aria-current="page">' . esc_html__( 'Agenda completa', 'dps-agenda-addon' ) . '</a>';
        } else {
            $view_buttons[] = '<a href="' . esc_url( add_query_arg( $all_view_args, $base_url ) ) . '" class="dps-view-btn" title="' . esc_attr__( 'Ver todos os agendamentos futuros', 'dps-agenda-addon' ) . '">' . esc_html__( 'Agenda completa', 'dps-agenda-addon' ) . '</a>';
        }

        echo '<div class="dps-view-buttons">' . implode( '', $view_buttons ) . '</div>';
        echo '<div class="dps-view-buttons dps-view-buttons--date-nav">';
        echo '<a href="' . esc_url( add_query_arg( $prev_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--prev" title="' . esc_attr( $is_week_view ? __( 'Ver periodo anterior', 'dps-agenda-addon' ) : __( 'Ver dia anterior', 'dps-agenda-addon' ) ) . '" aria-label="' . esc_attr( $is_week_view ? __( 'Ver periodo anterior', 'dps-agenda-addon' ) : __( 'Ver dia anterior', 'dps-agenda-addon' ) ) . '">';
        echo '&larr;';
        echo '</a>';
        echo '<a href="' . esc_url( add_query_arg( $today_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--today" title="' . esc_attr__( 'Ver agendamentos de hoje', 'dps-agenda-addon' ) . '" aria-label="' . esc_attr__( 'Ver agendamentos de hoje', 'dps-agenda-addon' ) . '">';
        echo esc_html__( 'Hoje', 'dps-agenda-addon' );
        echo '</a>';
        echo '<a href="' . esc_url( add_query_arg( $next_args, $base_url ) ) . '" class="dps-nav-btn dps-nav-btn--next" title="' . esc_attr( $is_week_view ? __( 'Ver proximo periodo', 'dps-agenda-addon' ) : __( 'Ver proximo dia', 'dps-agenda-addon' ) ) . '" aria-label="' . esc_attr( $is_week_view ? __( 'Ver proximo periodo', 'dps-agenda-addon' ) : __( 'Ver proximo dia', 'dps-agenda-addon' ) ) . '">';
        echo '&rarr;';
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Carrega agendamentos conforme visualizaÃƒÂ§ÃƒÂ£o ou modo "todos"
        $appointments = [];
        if ( $show_all ) {
            // Carrega todos os agendamentos a partir de hoje (inclusive)
            // PERFORMANCE: Implementada paginaÃƒÂ§ÃƒÂ£o com limite de 50 registros por pÃƒÂ¡gina
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
            // Limite diÃƒÂ¡rio configurÃƒÂ¡vel via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // Calcula inÃƒÂ­cio (segunda-feira) da semana contendo $selected_date
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
                    'no_found_rows'  => true, // PERFORMANCE: nÃƒÂ£o conta total
                ] );
            }
        } else {
            // Limite diÃƒÂ¡rio configurÃƒÂ¡vel via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // VisualizaÃƒÂ§ÃƒÂ£o diÃƒÂ¡ria
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
                'no_found_rows'  => true, // PERFORMANCE: nÃƒÂ£o conta total
            ] );
        }
        
        // FASE 5: Filtrar pagamentos pendentes (pÃƒÂ³s-query para usar helper)
        
        // FASE 4: Renderiza calendÃƒÂ¡rio mensal se view=calendar
        if ( $view === 'calendar' ) {
            $this->render_calendar_view( $selected_date );
            echo '</div>';
            return ob_get_clean();
        }
        
        // FASE 6: Sistema de abas para reorganizar visualizaÃƒÂ§ÃƒÂ£o
        
        // Definir abas com descricoes operacionais enxutas.
        $tabs = [
            'visao-rapida' => __( 'ConfirmaÃ§Ãµes e prÃ³ximos passos', 'dps-agenda-addon' ),
            'operacao'     => __( 'Checklist, check-in e cobranÃ§a', 'dps-agenda-addon' ),
            'detalhes'     => __( 'LogÃ­stica, notas e TaxiDog', 'dps-agenda-addon' ),
        ];
        
        // Inicializa variÃƒÂ¡veis e coleta dados de todos os dias primeiro
        $has_any = false;
        $all_visible_appointments = [];
        $days_data = []; // Armazena dados processados de cada dia para renderizaÃƒÂ§ÃƒÂ£o posterior
        $column_labels = [
            'date'          => __( 'Data', 'dps-agenda-addon' ),
            'time'          => __( 'Hora', 'dps-agenda-addon' ),
            'pet'           => __( 'Pet e tutor', 'dps-agenda-addon' ),
            'service'       => __( 'ServiÃƒÂ§o', 'dps-agenda-addon' ),
            'status'        => __( 'Status', 'dps-agenda-addon' ),
            'payment'       => __( 'Pagamento', 'dps-agenda-addon' ),
            'map'           => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation'  => __( 'ConfirmaÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ),
            'charge'        => __( 'CobranÃƒÂ§a', 'dps-agenda-addon' ),
        ];
        
        // FASE 1: PrÃƒÂ©-processa todos os dias para coletar dados visÃƒÂ­veis
        foreach ( $appointments as $day => $appts ) {
            $has_any = $has_any || ! empty( $appts );
            
            // Define tÃƒÂ­tulo do bloco
            if ( $show_all ) {
                $day_title = __( 'Todos os atendimentos futuros', 'dps-agenda-addon' );
            } elseif ( $view === 'week' ) {
                $day_dt = DateTime::createFromFormat( 'Y-m-d', $day );
                $day_title = ucfirst( date_i18n( 'l, d/m', $day_dt->getTimestamp() ) );
            } else {
                $day_title = ucfirst( date_i18n( 'l, d/m', strtotime( $day ) ) );
            }
            
            // Se nÃƒÂ£o houver appointments para o dia, pula se semanal
            if ( empty( $appts ) && $view === 'week' ) {
                continue;
            }
            
            // PERFORMANCE: Pre-cache metadata para todos os agendamentos do dia
            if ( ! empty( $appts ) ) {
                $appointment_ids = wp_list_pluck( $appts, 'ID' );
                update_meta_cache( 'post', $appointment_ids );
            }
            
            $visible_appointments = array_values( $appts );
            $all_visible_appointments = array_merge( $all_visible_appointments, $visible_appointments );
            
            // Classificar por status: pendente vs finalizado
            $upcoming  = [];
            $completed = [];
            foreach ( $visible_appointments as $appt ) {
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
                'day'          => $day,
                'title'        => $day_title,
                'appointments' => $visible_appointments,
                'upcoming'     => $upcoming,
                'completed'    => $completed,
            ];
        }
        

        // FASE 3: Define funÃƒÂ§ÃƒÂµes de renderizaÃƒÂ§ÃƒÂ£o (uma vez, fora do loop de dias)
        
        // Aba 1: VisÃƒÂ£o RÃƒÂ¡pida
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
            
            // OrdenaÃƒÂ§ÃƒÂ£o cronolÃƒÂ³gica (prÃƒÂ³ximo atendimento primeiro)
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
                    return $dt_a <=> $dt_b; // ASC: prÃƒÂ³ximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab1"><thead><tr>';
            echo '<th>' . esc_html__( 'HorÃƒÂ¡rio', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet e tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html( $column_labels['service'] ?? __( 'ServiÃƒÂ§os', 'dps-agenda-addon' ) ) . '</th>';
            echo '<th>' . esc_html( $column_labels['confirmation'] ?? __( 'ConfirmaÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ) ) . '</th>';
            echo '<th>' . esc_html__( 'AÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                echo $this->render_appointment_row_tab1_m3( $appt, $column_labels );
            }
            echo '</tbody></table>';
            echo '</div>';
        };
        
        // Aba 2: OperaÃƒÂ§ÃƒÂ£o
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
            
            // OrdenaÃƒÂ§ÃƒÂ£o cronolÃƒÂ³gica (prÃƒÂ³ximo atendimento primeiro)
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
                    return $dt_a <=> $dt_b; // ASC: prÃƒÂ³ximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab2"><thead><tr>';
            echo '<th>' . esc_html__( 'HorÃƒÂ¡rio', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet e tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Status do ServiÃƒÂ§o', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html( $column_labels['payment'] ?? __( 'Pagamento', 'dps-agenda-addon' ) ) . '</th>';
            echo '<th>' . esc_html__( 'Painel operacional', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'AÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado por render_appointment_row_tab2_m3
                echo $this->render_appointment_row_tab2_m3( $appt, $column_labels );
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
            
            // OrdenaÃƒÂ§ÃƒÂ£o cronolÃƒÂ³gica (prÃƒÂ³ximo atendimento primeiro)
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
                    return $dt_a <=> $dt_b; // ASC: prÃƒÂ³ximo primeiro
                }
            );
            
            echo '<h5>' . esc_html( $heading ) . '</h5>';
            echo '<div class="dps-agenda-table-container">';
            echo '<table class="dps-table dps-table--tab3"><thead><tr>';
            echo '<th>' . esc_html__( 'HorÃƒÂ¡rio', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet e tutor', 'dps-agenda-addon' ) . '</th>';
            echo '<th>TaxiDog</th>';
            echo '<th>' . esc_html__( 'ObservaÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Operacional', 'dps-agenda-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'AÃƒÂ§ÃƒÂµes', 'dps-agenda-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $apts as $appt ) {
                echo $this->render_appointment_row_tab3_m3( $appt, $column_labels );
            }
            echo '</tbody></table>';
            echo '</div>';
        };
        
        $has_visible_results = ! empty( $all_visible_appointments );
        $overview_stats = $this->get_agenda_overview_stats( $all_visible_appointments );


        $overview_cards = [
            [
                'label'    => __( 'Total', 'dps-agenda-addon' ),
                'value'    => $overview_stats['total'],
                'tone'     => 'primary',
                'featured' => true,
            ],
            [
                'label' => __( 'Pendentes', 'dps-agenda-addon' ),
                'value' => $overview_stats['pending'],
                'tone'  => 'warning',
            ],
            [
                'label' => __( 'Finalizados', 'dps-agenda-addon' ),
                'value' => $overview_stats['completed'],
                'tone'  => 'success',
            ],
            [
                'label' => __( 'Cancelados', 'dps-agenda-addon' ),
                'value' => $overview_stats['canceled'],
                'tone'  => 'error',
            ],
            [
                'label' => __( 'Atrasados', 'dps-agenda-addon' ),
                'value' => $overview_stats['late'],
                'tone'  => 'warning',
            ],
            [
                'label' => __( 'Pagamento pendente', 'dps-agenda-addon' ),
                'value' => $overview_stats['pending_payment'],
                'tone'  => 'secondary',
            ],
            [
                'label' => __( 'TaxiDog', 'dps-agenda-addon' ),
                'value' => $overview_stats['taxidog'],
                'tone'  => 'tertiary',
            ],
        ];

        echo '<section class="dps-agenda-overview" aria-label="' . esc_attr__( 'Resumo da agenda', 'dps-agenda-addon' ) . '">';
        foreach ( $overview_cards as $overview_card ) {
            $card_classes = 'dps-agenda-overview-card dps-agenda-overview-card--' . esc_attr( $overview_card['tone'] );
            if ( ! empty( $overview_card['featured'] ) ) {
                $card_classes .= ' dps-agenda-overview-card--featured';
            }

            echo '<article class="' . esc_attr( $card_classes ) . '">';
            echo '<div class="dps-agenda-overview-card__body">';
            echo '<strong class="dps-agenda-overview-card__value">' . esc_html( $overview_card['value'] ) . '</strong>';
            echo '<span class="dps-agenda-overview-card__label">' . esc_html( $overview_card['label'] ) . '</span>';
            echo '</div>';
            echo '</article>';
        }
        echo '</section>';
        echo '<div class="dps-agenda-tabs-wrapper">';

        // Header da lista de atendimentos.
        echo '<div class="dps-agenda-tabs-header">';
        echo '<div>';
        echo '<h3 class="dps-agenda-tabs-title">' . esc_html__( 'Lista de Atendimentos', 'dps-agenda-addon' ) . '</h3>';
        echo '<p class="dps-agenda-tabs-subtitle">' . esc_html__( 'TrÃƒÂªs leituras do mesmo fluxo operacional: confirmar, executar e revisar contexto.', 'dps-agenda-addon' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<nav class="dps-agenda-tabs-nav" role="tablist" aria-label="' . esc_attr__( 'Modos de visualizaÃƒÂ§ÃƒÂ£o da agenda', 'dps-agenda-addon' ) . '">';

        foreach ( $tabs as $tab_id => $tab_data ) {
            $is_active = ( $current_tab === $tab_id );
            $tab_class = 'dps-agenda-tab-button' . ( $is_active ? ' dps-agenda-tab-button--active' : '' );

            echo '<button type="button" id="dps-agenda-tab-' . esc_attr( $tab_id ) . '" class="' . esc_attr( $tab_class ) . '" data-tab="' . esc_attr( $tab_id ) . '" role="tab" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" aria-controls="dps-tab-content-' . esc_attr( $tab_id ) . '" title="' . esc_attr( $tab_data ) . '" tabindex="' . ( $is_active ? '0' : '-1' ) . '">';
            echo '<span class="dps-tab-desc">' . esc_html( $tab_data ) . '</span>';
            echo '</button>';
        }

        echo '</nav>';

        // Container para conteudo das abas.
        echo '<div class="dps-agenda-tabs-content">';
        echo '<div id="dps-tab-content-visao-rapida" class="dps-tab-content' . ( $current_tab === 'visao-rapida' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel" aria-labelledby="dps-agenda-tab-visao-rapida"' . ( $current_tab === 'visao-rapida' ? '' : ' hidden' ) . '>';
            foreach ( $days_data as $day_info ) {
                $day_total = count( $day_info['appointments'] );
                if ( 0 === $day_total ) {
                    continue;
                }
                echo '<section class="dps-agenda-day-panel">';
                echo '<div class="dps-agenda-day-panel__header">';
                echo '<div>';
                echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                echo '<p>' . sprintf( _n( '%d atendimento no periodo', '%d atendimentos no periodo', $day_total, 'dps-agenda-addon' ), $day_total ) . '</p>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__stats">';
                echo '<span class="dps-context-pill dps-context-pill--warning">' . sprintf( esc_html__( '%d pendentes', 'dps-agenda-addon' ), count( $day_info['upcoming'] ) ) . '</span>';
                echo '<span class="dps-context-pill dps-context-pill--success">' . sprintf( esc_html__( '%d finalizados', 'dps-agenda-addon' ), count( $day_info['completed'] ) ) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__body">';
                $render_table_tab1( $day_info['upcoming'], __( 'Fila ativa', 'dps-agenda-addon' ) );
                $render_table_tab1( $day_info['completed'], __( 'ConcluÃƒÂ­dos no perÃƒÂ­odo', 'dps-agenda-addon' ) );
                echo '</div>';
                echo '</section>';
            }
        echo '</div>';

        echo '<div id="dps-tab-content-operacao" class="dps-tab-content' . ( $current_tab === 'operacao' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel" aria-labelledby="dps-agenda-tab-operacao"' . ( $current_tab === 'operacao' ? '' : ' hidden' ) . '>';
            foreach ( $days_data as $day_info ) {
                $day_total = count( $day_info['appointments'] );
                if ( 0 === $day_total ) {
                    continue;
                }
                echo '<section class="dps-agenda-day-panel">';
                echo '<div class="dps-agenda-day-panel__header">';
                echo '<div>';
                echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                echo '<p>' . sprintf( _n( '%d atendimento no periodo', '%d atendimentos no periodo', $day_total, 'dps-agenda-addon' ), $day_total ) . '</p>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__stats">';
                echo '<span class="dps-context-pill dps-context-pill--warning">' . sprintf( esc_html__( '%d pendentes', 'dps-agenda-addon' ), count( $day_info['upcoming'] ) ) . '</span>';
                echo '<span class="dps-context-pill dps-context-pill--success">' . sprintf( esc_html__( '%d finalizados', 'dps-agenda-addon' ), count( $day_info['completed'] ) ) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__body">';
                $render_table_tab2( $day_info['upcoming'], __( 'Fila ativa', 'dps-agenda-addon' ) );
                $render_table_tab2( $day_info['completed'], __( 'ConcluÃƒÂ­dos no perÃƒÂ­odo', 'dps-agenda-addon' ) );
                echo '</div>';
                echo '</section>';
            }
        echo '</div>';

        echo '<div id="dps-tab-content-detalhes" class="dps-tab-content' . ( $current_tab === 'detalhes' ? ' dps-tab-content--active' : '' ) . '" role="tabpanel" aria-labelledby="dps-agenda-tab-detalhes"' . ( $current_tab === 'detalhes' ? '' : ' hidden' ) . '>';
            foreach ( $days_data as $day_info ) {
                $day_total = count( $day_info['appointments'] );
                if ( 0 === $day_total ) {
                    continue;
                }
                echo '<section class="dps-agenda-day-panel">';
                echo '<div class="dps-agenda-day-panel__header">';
                echo '<div>';
                echo '<h4>' . esc_html( $day_info['title'] ) . '</h4>';
                echo '<p>' . sprintf( _n( '%d atendimento no periodo', '%d atendimentos no periodo', $day_total, 'dps-agenda-addon' ), $day_total ) . '</p>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__stats">';
                echo '<span class="dps-context-pill dps-context-pill--warning">' . sprintf( esc_html__( '%d pendentes', 'dps-agenda-addon' ), count( $day_info['upcoming'] ) ) . '</span>';
                echo '<span class="dps-context-pill dps-context-pill--success">' . sprintf( esc_html__( '%d finalizados', 'dps-agenda-addon' ), count( $day_info['completed'] ) ) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '<div class="dps-agenda-day-panel__body">';
                $render_table_tab3( $day_info['upcoming'], __( 'Fila ativa', 'dps-agenda-addon' ) );
                $render_table_tab3( $day_info['completed'], __( 'ConcluÃƒÂ­dos no perÃƒÂ­odo', 'dps-agenda-addon' ) );
                echo '</div>';
                echo '</section>';
            }
        echo '</div>';
        if ( ! $has_visible_results ) {
            echo '<div class="dps-agenda-empty" role="status">';
            echo '<strong>' . esc_html__( 'Nenhum atendimento neste recorte.', 'dps-agenda-addon' ) . '</strong>';
            echo '<p>' . esc_html__( 'Ajuste o perÃƒÂ­odo ou abra a agenda completa para continuar a operaÃƒÂ§ÃƒÂ£o.', 'dps-agenda-addon' ) . '</p>';
            echo '</div>';
        }
        // Fecha container de tabs
        echo '</div>'; // .dps-agenda-tabs-content
        echo '</div>'; // .dps-agenda-tabs-wrapper
        
        // PERFORMANCE: Controles de paginaÃƒÂ§ÃƒÂ£o para modo "Todos os Atendimentos"
        if ( $show_all ) {
            $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
            $prev_page = max( 1, $paged - 1 );
            $next_page = $paged + 1;
            
            // Preserva parÃƒÂ¢metros de filtro vÃƒÂ¡lidos na paginaÃƒÂ§ÃƒÂ£o
            // Sanitiza cada parÃƒÂ¢metro para prevenir injeÃƒÂ§ÃƒÂ£o de cÃƒÂ³digo
            $pagination_args = [
                'show_all'   => '1',
                'dps_date'   => $selected_date,
                'view'       => $view,
                'agenda_tab' => $current_tab,
            ];

            echo '<div class="dps-agenda-pagination">';
            
            if ( $paged > 1 ) {
                $pagination_args['paged'] = $prev_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo 'Ã¢â€ Â ' . esc_html__( 'PÃƒÂ¡gina anterior', 'dps-agenda-addon' );
                echo '</a>';
            }
            
            echo '<span class="dps-pagination-info">';
            echo sprintf( esc_html__( 'PÃƒÂ¡gina %d', 'dps-agenda-addon' ), $paged );
            echo '</span>';
            
            // SÃƒÂ³ mostra "PrÃƒÂ³xima" se retornou o mÃƒÂ¡ximo de registros (indicando que pode haver mais)
            if ( ! empty( $appointments['todos'] ) && count( $appointments['todos'] ) >= self::APPOINTMENTS_PER_PAGE ) {
                $pagination_args['paged'] = $next_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo esc_html__( 'PrÃƒÂ³xima pÃƒÂ¡gina', 'dps-agenda-addon' ) . ' Ã¢â€ â€™';
                echo '</a>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Shortcode deprecated que redireciona para o Finance Add-on.
     *
     * @deprecated 1.1.0 Use [dps_fin_docs] do Finance Add-on
     * @return string HTML do shortcode ou mensagem de depreciaÃƒÂ§ÃƒÂ£o
     */
    public function render_charges_notes_shortcode_deprecated() {
        _deprecated_function( 'Shortcode [dps_charges_notes]', '1.1.0', '[dps_fin_docs] (Finance Add-on)' );
        
        // Tenta redirecionar para shortcode do Finance
        if ( shortcode_exists( 'dps_fin_docs' ) ) {
            return do_shortcode( '[dps_fin_docs]' );
        }
        
        // Se Finance nÃƒÂ£o estiver ativo, exibe mensagem
        return '<div class="notice notice-warning" style="padding: 20px; margin: 20px 0; background: #fff3cd; border-left: 4px solid #ffc107;">' .
               '<p><strong>' . esc_html__( 'AtenÃƒÂ§ÃƒÂ£o:', 'dps-agenda-addon' ) . '</strong> ' .
               esc_html__( 'Este shortcode foi movido para o Finance Add-on. Por favor, use [dps_fin_docs] ou ative o Finance Add-on.', 'dps-agenda-addon' ) .
               '</p></div>';
    }

    /**
     * AJAX handler para atualizar o status de um agendamento.
     *
     * Espera campos 'id' e 'status' via POST. Somente usuÃƒÂ¡rios logados podem executar.
     */
    public function update_status_ajax() {
        // Verifica permissÃƒÂ£o do usuÃƒÂ¡rio. Apenas administradores podem alterar o status.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        // Verifica nonce para evitar CSRF. O nonce deve ser enviado no campo 'nonce'.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_update_status' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        $id     = intval( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $version = isset( $_POST['version'] ) ? intval( $_POST['version'] ) : 0;
        // Aceita variaÃƒÂ§ÃƒÂµes do texto "finalizado e pago" alÃƒÂ©m do slug sem espaÃƒÂ§os
        if ( $status === 'finalizado e pago' ) {
            $status = 'finalizado_pago';
        }
        $valid_statuses = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];
        if ( ! $id || ! in_array( $status, $valid_statuses, true ) || $version < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }
        $current_version = intval( get_post_meta( $id, '_dps_appointment_version', true ) );

        if ( $current_version < 1 ) {
            $current_version = 1;
            update_post_meta( $id, '_dps_appointment_version', $current_version );
        }

        if ( $version !== $current_version ) {
            wp_send_json_error(
                [
                    'message'    => __( 'Esse agendamento foi atualizado por outro usuÃƒÂ¡rio. Atualize a pÃƒÂ¡gina para ver as alteraÃƒÂ§ÃƒÂµes.', 'dps-agenda-addon' ),
                    'error_code' => 'version_conflict',
                ]
            );
        }
        // Atualiza meta de status. Remove entradas anteriores para garantir que nÃƒÂ£o haja valores duplicados.
        delete_post_meta( $id, 'appointment_status' );
        add_post_meta( $id, 'appointment_status', $status, true );
        $new_version = $current_version + 1;
        update_post_meta( $id, '_dps_appointment_version', $new_version );

        // AUDITORIA: Registra mudanÃƒÂ§a de status no log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status alterado para "%s" por usuÃƒÂ¡rio #%d',
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

        // A sincronizaÃƒÂ§ÃƒÂ£o financeira ÃƒÂ© feita automaticamente pelo Finance Add-on via hook updated_post_meta
        // O Finance monitora mudanÃƒÂ§as em appointment_status e cria/atualiza transaÃƒÂ§ÃƒÂµes conforme necessÃƒÂ¡rio
        // NÃƒÂ£o ÃƒÂ© necessÃƒÂ¡rio manipular dps_transacoes diretamente aqui

        // ApÃƒÂ³s atualizar a transaÃƒÂ§ÃƒÂ£o, aciona o hook dps_base_after_save_appointment para que
        // outros add-ons (como o de pagamentos) possam processar o agendamento finalizado.
        // Isso garante que o link de pagamento seja criado automaticamente mesmo quando
        // o status ÃƒÂ© alterado manualmente pela agenda.
        do_action( 'dps_base_after_save_appointment', $id, 'simple' );

        // TODO: Implementar notificaÃƒÂ§ÃƒÂ£o via WhatsApp quando necessÃƒÂ¡rio
        // Atualmente o cÃƒÂ³digo abaixo usa variÃƒÂ¡veis nÃƒÂ£o definidas ($client_id, $pet_post, $date, $valor)
        // e precisa ser refatorado para obter esses dados do agendamento

        $updated_post = get_post( $id );
        $row_html     = $this->render_row_html_for_active_tab( $updated_post );

        wp_send_json_success(
            [
                'message'  => __( 'Status atualizado.', 'dps-agenda-addon' ),
                'status'   => $status,
                'version'  => $new_version,
                'row_html' => $row_html,
            ]
        );
    }

    /**
     * AJAX handler para retornar detalhes de serviÃƒÂ§os de um agendamento.
     * Retorna lista de serviÃƒÂ§os (nome e preÃƒÂ§o) para o agendamento.
     *
     * @deprecated 1.1.0 LÃƒÂ³gica movida para Services Add-on (DPS_Services_API).
     *                   Mantido por compatibilidade, mas delega para API quando disponÃƒÂ­vel.
     */
    public function get_services_details_ajax() {
        // Apenas administradores podem consultar detalhes de servi?os. Garante que usu?rios n?o
        // autenticados ou sem permiss?o n?o exponham dados. Caso contr?rio, retorna erro.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        // SEGURAN?A: verifica??o de nonce obrigat?ria para prevenir CSRF.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_get_services_details' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $id_param = isset( $_POST['appt_id'] ) ? intval( wp_unslash( $_POST['appt_id'] ) ) : 0;
        if ( ! $id_param ) {
            // Compatibilidade: aceita "id" como fallback.
            $id_param = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
        }
        if ( ! $id_param ) {
            wp_send_json_error( [ 'message' => __( 'ID invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }

        $appointment = get_post( $id_param );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }

        try {
            $payload = $this->build_services_details_payload( $id_param );
        } catch ( Throwable $exception ) {
            wp_send_json_error(
                [ 'message' => __( 'NÃƒÂ£o foi possÃƒÂ­vel carregar os serviÃƒÂ§os deste atendimento.', 'dps-agenda-addon' ) ],
                500
            );
        }

        wp_send_json_success( $payload );
    }

    /**
     * Monta o payload do modal de servi?os do atendimento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array
     */
    private function build_services_details_payload( $appointment_id ) {
        $appt_notes        = $this->normalize_services_modal_text( get_post_meta( $appointment_id, 'appointment_notes', true ), true );
        $taxidog_requested = ! empty( get_post_meta( $appointment_id, 'appointment_taxidog', true ) );
        $taxidog_price     = $taxidog_requested ? (float) get_post_meta( $appointment_id, 'appointment_taxidog_price', true ) : 0.0;
        $pet_info          = $this->get_services_modal_pet_info( $appointment_id );
        $pet_size          = $pet_info['size'] ?? '';
        $service_ids       = get_post_meta( $appointment_id, 'appointment_services', true );
        $service_prices    = get_post_meta( $appointment_id, 'appointment_service_prices', true );
        $service_ids       = is_array( $service_ids ) ? array_values( array_filter( array_map( 'absint', $service_ids ) ) ) : [];
        $service_prices    = is_array( $service_prices ) ? $service_prices : [];
        $services          = [];
        $total_duration    = 0;

        foreach ( $service_ids as $service_id ) {
            $service_data = $this->get_service_details_entry( $service_id, $pet_size, $service_prices );
            if ( null === $service_data ) {
                continue;
            }

            $total_duration += $service_data['duration'];
            $services[]      = $service_data;
        }

        if ( $taxidog_requested ) {
            $services[] = [
                'name'        => __( 'TaxiDog', 'dps-agenda-addon' ),
                'price'       => $taxidog_price,
                'is_taxidog'  => true,
                'type'        => 'extra',
                'category'    => 'transporte',
                'description' => '',
                'duration'    => 0,
            ];
        }

        return [
            'services'       => $services,
            'notes'          => $appt_notes,
            'pet'            => $pet_info,
            'total_duration' => $total_duration,
        ];
    }

    /**
     * Retorna uma entrada de servi?o pronta para o modal.
     *
     * @param int    $service_id     ID do servi?o.
     * @param string $pet_size       Porte do pet.
     * @param array  $service_prices Pre?os customizados do agendamento.
     * @return array|null
     */
    private function get_service_details_entry( $service_id, $pet_size, array $service_prices ) {
        $service_id = absint( $service_id );
        if ( ! $service_id ) {
            return null;
        }

        $duration = $this->get_service_duration_for_pet_size( $service_id, $pet_size );

        if ( class_exists( 'DPS_Services_API' ) && method_exists( 'DPS_Services_API', 'get_service' ) ) {
            $service_data = DPS_Services_API::get_service( $service_id );
            if ( is_array( $service_data ) ) {
                $price = null;
                if ( isset( $service_prices[ $service_id ] ) ) {
                    $price = (float) $service_prices[ $service_id ];
                } elseif ( method_exists( 'DPS_Services_API', 'calculate_price' ) ) {
                    $price = DPS_Services_API::calculate_price( $service_id, $pet_size );
                }

                if ( null === $price ) {
                    $price = isset( $service_data['price'] ) ? (float) $service_data['price'] : 0.0;
                }

                return [
                    'name'        => $this->normalize_services_modal_text( $service_data['title'] ?? '' ),
                    'price'       => (float) $price,
                    'type'        => $this->normalize_services_modal_text( $service_data['type'] ?? '' ),
                    'category'    => $this->normalize_services_modal_text( $service_data['category'] ?? '' ),
                    'description' => $this->normalize_services_modal_text( $service_data['description'] ?? '', true ),
                    'duration'    => $duration,
                ];
            }
        }

        $service_post = get_post( $service_id );
        if ( ! $service_post ) {
            return null;
        }

        $price = isset( $service_prices[ $service_id ] ) ? (float) $service_prices[ $service_id ] : (float) get_post_meta( $service_id, 'service_price', true );

        return [
            'name'        => $this->normalize_services_modal_text( $service_post->post_title ),
            'price'       => $price,
            'type'        => $this->normalize_services_modal_text( get_post_meta( $service_id, 'service_type', true ) ),
            'category'    => $this->normalize_services_modal_text( get_post_meta( $service_id, 'service_category', true ) ),
            'description' => $this->normalize_services_modal_text( $service_post->post_content, true ),
            'duration'    => $duration,
        ];
    }

    /**
     * Obt?m informa??es do pet para exibi??o no modal.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array
     */
    private function get_services_modal_pet_info( $appointment_id ) {
        $pet_id = absint( get_post_meta( $appointment_id, 'appointment_pet_id', true ) );
        if ( ! $pet_id ) {
            $pet_id = absint( get_post_meta( $appointment_id, 'appointment_pet', true ) );
        }

        if ( ! $pet_id ) {
            return [];
        }

        $pet_post = get_post( $pet_id );
        if ( ! $pet_post ) {
            return [];
        }

        return [
            'name'   => $this->normalize_services_modal_text( $pet_post->post_title ),
            'size'   => $this->normalize_services_modal_text( get_post_meta( $pet_id, 'pet_size', true ) ),
            'breed'  => $this->normalize_services_modal_text( get_post_meta( $pet_id, 'pet_breed', true ) ),
            'weight' => $this->normalize_services_modal_text( get_post_meta( $pet_id, 'pet_weight', true ) ),
        ];
    }

    /**
     * Normaliza textos exibidos no modal para evitar quebra de JSON.
     *
     * @param mixed $value     Valor bruto.
     * @param bool  $multiline Se true, preserva quebras de linha.
     * @return string
     */
    private function normalize_services_modal_text( $value, $multiline = false ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $text = wp_check_invalid_utf8( (string) $value );
        if ( '' === $text ) {
            return '';
        }

        return $multiline ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
    }

    /**
     * Obt?m a dura??o de um servi?o baseada no porte do pet.
     *
     * @param int    $service_id ID do servi?o.
     * @param string $pet_size   Porte do pet (pequeno, medio, grande, small, medium, large).
     * @return int Dura??o em minutos.
     */
    private function get_service_duration_for_pet_size( $service_id, $pet_size ) {
        $pet_size = remove_accents( strtolower( $this->normalize_services_modal_text( $pet_size ) ) );
        $duration = 0;

        if ( 'pequeno' === $pet_size || 'small' === $pet_size ) {
            $duration = (int) get_post_meta( $service_id, 'service_duration_small', true );
        } elseif ( 'medio' === $pet_size || 'medium' === $pet_size ) {
            $duration = (int) get_post_meta( $service_id, 'service_duration_medium', true );
        } elseif ( 'grande' === $pet_size || 'large' === $pet_size ) {
            $duration = (int) get_post_meta( $service_id, 'service_duration_large', true );
        }

        // Fallback para dura??o base se n?o houver dura??o espec?fica por porte.
        if ( ! $duration ) {
            $duration = (int) get_post_meta( $service_id, 'service_duration', true );
        }

        return $duration;
    }
    /**
     * Limpa cron jobs agendados quando o plugin ÃƒÂ© desativado.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'dps_agenda_send_reminders' );
    }

    /**
     * Agenda envio diÃƒÂ¡rio de lembretes para clientes com agendamentos do dia.
     * O evento ÃƒÂ© agendado apenas uma vez, no prÃƒÂ³ximo horÃƒÂ¡rio configurado (padrÃƒÂ£o: 08:00).
     */
    public function maybe_schedule_reminders() {
        if ( ! function_exists( 'wp_next_scheduled' ) ) {
            return;
        }
        // Verifica se jÃƒÂ¡ existe um evento programado
        $timestamp = wp_next_scheduled( 'dps_agenda_send_reminders' );
        if ( ! $timestamp ) {
            // Calcula timestamp para 08:00 do horÃƒÂ¡rio do site
            $hour   = 8;
            $minute = 0;
            // Usa timezone do site
            $tz = wp_timezone();
            $now = new DateTime( 'now', $tz );
            // Cria data para hoje ÃƒÂ s 08:00
            $schedule_time = new DateTime( $now->format( 'Y-m-d' ) . ' ' . sprintf( '%02d:%02d', $hour, $minute ), $tz );
            // Se jÃƒÂ¡ passou hoje, agenda para o dia seguinte
            if ( $schedule_time <= $now ) {
                $schedule_time->modify( '+1 day' );
            }
            wp_schedule_event( $schedule_time->getTimestamp(), 'daily', 'dps_agenda_send_reminders' );
        }
    }

    /**
     * Envia lembretes de agendamentos para clientes.
     * Este mÃƒÂ©todo ÃƒÂ© executado pelo cron diÃƒÂ¡rio configurado em maybe_schedule_reminders().
     *
     * NOTA: A lÃƒÂ³gica de ENVIO estÃƒÂ¡ delegada ÃƒÂ  Communications API.
     * A Agenda apenas identifica quais agendamentos precisam de lembrete.
     * 
     * @since 1.0.0
     * @return void
     */
    public function send_reminders() {
        // Determina a data atual no fuso horÃƒÂ¡rio do site
        $date = current_time( 'Y-m-d' );
        
        // Limite diÃƒÂ¡rio configurÃƒÂ¡vel (mesmo usado nas queries de visualizaÃƒÂ§ÃƒÂ£o)
        $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
        
        // PERFORMANCE: Busca agendamentos do dia com limite e otimizaÃƒÂ§ÃƒÂ£o
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => $daily_limit,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'appointment_date', 'value' => $date, 'compare' => '=' ],
            ],
            'no_found_rows'  => true, // OtimizaÃƒÂ§ÃƒÂ£o: nÃƒÂ£o conta total
        ] );
        
        // AUDITORIA: Registra inÃƒÂ­cio do envio de lembretes
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

        // Se Communications API estiver disponÃƒÂ­vel, usa ela (mÃƒÂ©todo preferido)
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
                    __( 'OlÃƒÂ¡ %s,\n\nEste ÃƒÂ© um lembrete do agendamento para %s no dia %s ÃƒÂ s %s.\n\nEstamos aguardando vocÃƒÂª!\n\nAtenciosamente,\ndesi.pet by PRObst', 'dps-agenda-addon' ),
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
                    'Cron de lembretes finalizado: %d enviados, %d ignorados (nÃƒÂ£o pendentes ou sem dados)',
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
     * UX-1: AJAX handler para aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas de status.
     * Permite mudanÃƒÂ§a rÃƒÂ¡pida de status em 1 clique sem modais.
     * 
     * AÃƒÂ§ÃƒÂµes suportadas:
     * - finish: muda para 'finalizado'
     * - finish_and_paid: muda para 'finalizado_pago'
     * - cancel: muda para 'cancelado'
     * - mark_paid: muda de 'finalizado' para 'finalizado_pago'
     *
     * @since 1.1.0
     */
    public function quick_action_ajax() {
        // Verifica permissÃƒÂ£o do usuÃƒÂ¡rio
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_quick_action' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $action  = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
        
        if ( ! $appt_id || ! $action ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Mapeia aÃƒÂ§ÃƒÂ£o para status
        $status_map = [
            'finish'          => 'finalizado',
            'finish_and_paid' => 'finalizado_pago',
            'cancel'          => 'cancelado',
            'mark_paid'       => 'finalizado_pago',
        ];
        
        if ( ! isset( $status_map[ $action ] ) ) {
            wp_send_json_error( [ 'message' => __( 'AÃƒÂ§ÃƒÂ£o invÃƒÂ¡lida.', 'dps-agenda-addon' ) ] );
        }
        
        $new_status = $status_map[ $action ];
        $old_status = get_post_meta( $appt_id, 'appointment_status', true );
        if ( ! $old_status ) {
            $old_status = 'pendente';
        }
        
        // ValidaÃƒÂ§ÃƒÂµes de negÃƒÂ³cio
        // NÃƒÂ£o permite marcar como pago se nÃƒÂ£o estiver finalizado
        if ( $action === 'mark_paid' && $old_status !== 'finalizado' ) {
            wp_send_json_error( [ 'message' => __( 'Apenas atendimentos finalizados podem ser marcados como pagos.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica se ÃƒÂ© assinatura (nÃƒÂ£o deve ter status finalizado_pago)
        $is_subscription = ! empty( get_post_meta( $appt_id, 'subscription_id', true ) );
        if ( $is_subscription && $new_status === 'finalizado_pago' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamentos de assinatura nÃƒÂ£o podem ser marcados como pagos.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status usando mesma lÃƒÂ³gica do update_status_ajax
        delete_post_meta( $appt_id, 'appointment_status' );
        add_post_meta( $appt_id, 'appointment_status', $new_status, true );
        
        // Incrementa versÃƒÂ£o
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
                    'Agendamento #%d: AÃƒÂ§ÃƒÂ£o rÃƒÂ¡pida "%s" (status %s Ã¢â€ â€™ %s) por usuÃƒÂ¡rio #%d',
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
        
        // Aciona hook para sincronizaÃƒÂ§ÃƒÂ£o com outros add-ons
        do_action( 'dps_base_after_save_appointment', $appt_id, 'simple' );
        
        // UX-2: Renderiza HTML da linha atualizada
        $updated_post = get_post( $appt_id );
                $row_html = $this->render_row_html_for_active_tab( $updated_post );
        
        wp_send_json_success( [
            'message'        => __( 'Status atualizado com sucesso!', 'dps-agenda-addon' ),
            'row_html'       => $row_html,
            'appointment_id' => $appt_id,
            'new_status'     => $new_status,
            'version'        => $new_version,
        ] );
    }

    /**
     * CONF-2: AJAX handler para atualizar status de confirmaÃƒÂ§ÃƒÂ£o.
     * Permite marcar confirmaÃƒÂ§ÃƒÂ£o de atendimento sem alterar o status principal.
     * 
     * @since 1.2.0
     */
    public function update_confirmation_ajax() {
        // Verifica permissÃƒÂ£o do usuÃƒÂ¡rio
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_confirmation' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $confirmation_status = isset( $_POST['confirmation_status'] ) ? sanitize_text_field( $_POST['confirmation_status'] ) : '';
        
        if ( ! $appt_id || ! $confirmation_status ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida status de confirmaÃƒÂ§ÃƒÂ£o
        $valid_statuses = [ 'not_sent', 'sent', 'confirmed', 'denied', 'no_answer' ];
        if ( ! in_array( $confirmation_status, $valid_statuses, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Status de confirmaÃƒÂ§ÃƒÂ£o invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status de confirmaÃƒÂ§ÃƒÂ£o usando helper
        $success = $this->set_confirmation_status( $appt_id, $confirmation_status, get_current_user_id() );
        
        if ( ! $success ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao atualizar status de confirmaÃƒÂ§ÃƒÂ£o.', 'dps-agenda-addon' ) ] );
        }
        
        // Log de auditoria
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status de confirmaÃƒÂ§ÃƒÂ£o alterado para "%s" por usuÃƒÂ¡rio #%d',
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
                $row_html = $this->render_row_html_for_active_tab( $updated_post );
        
        wp_send_json_success( [
            'message'             => __( 'ConfirmaÃƒÂ§ÃƒÂ£o atualizada com sucesso!', 'dps-agenda-addon' ),
            'row_html'            => $row_html,
            'appointment_id'      => $appt_id,
            'confirmation_status' => $confirmation_status,
        ] );
    }

    /**
     * FASE 3: AJAX handler para atualizaÃƒÂ§ÃƒÂ£o de status de TaxiDog.
     * Permite mudanÃƒÂ§a de status do TaxiDog via aÃƒÂ§ÃƒÂµes rÃƒÂ¡pidas.
     *
     * @since 1.2.0
     */
    public function update_taxidog_ajax() {
        // Verifica permissÃƒÂ£o do usuÃƒÂ¡rio
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_taxidog' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        $new_status = isset( $_POST['taxidog_status'] ) ? sanitize_text_field( $_POST['taxidog_status'] ) : '';
        
        if ( ! $appt_id || ! $new_status ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Atualiza status usando o helper
        $success = DPS_Agenda_TaxiDog_Helper::update_taxidog_status( $appt_id, $new_status );
        
        if ( ! $success ) {
            wp_send_json_error( [ 'message' => __( 'Status de TaxiDog invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        
        // Renderiza HTML da linha atualizada
        $updated_post = get_post( $appt_id );
                $row_html = $this->render_row_html_for_active_tab( $updated_post );
        
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
     * Habilita TaxiDog para um agendamento que nÃƒÂ£o tinha solicitado.
     *
     * @since 1.4.2
     */
    public function request_taxidog_ajax() {
        // Verifica permissÃƒÂ£o do usuÃƒÂ¡rio
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_taxidog' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        
        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }
        
        // Valida que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Habilita TaxiDog no agendamento
        update_post_meta( $appt_id, 'appointment_taxidog', 1 );
        update_post_meta( $appt_id, '_dps_taxidog_status', 'requested' );
        
        $updated_post = get_post( $appt_id );
        $row_html     = $this->render_row_html_for_active_tab( $updated_post );

        wp_send_json_success( [
            'message'        => __( 'TaxiDog solicitado com sucesso!', 'dps-agenda-addon' ),
            'appointment_id' => $appt_id,
            'row_html'       => $row_html,
        ] );
    }

    /**
     * FASE 4: AJAX handler para salvar configuraÃƒÂ§ÃƒÂ£o de capacidade.
     *
     * @since 1.4.0
     */
    public function save_capacity_ajax() {
        // Verifica permissÃƒÂ£o
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_capacity' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
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
                'message' => __( 'ConfiguraÃƒÂ§ÃƒÂ£o de capacidade salva com sucesso!', 'dps-agenda-addon' ),
                'config'  => $config,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Erro ao salvar configuraÃƒÂ§ÃƒÂ£o.', 'dps-agenda-addon' ) ] );
        }
    }

    /**
     * FASE 5: AJAX handler para reenviar link de pagamento.
     *
     * @since 1.5.0
     */
    public function resend_payment_ajax() {
        // Verifica permissÃƒÂ£o
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_agenda_resend_payment' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appt_id = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;

        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }

        // Valida que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }

        // Tenta reenviar via Payment Add-on se disponÃƒÂ­vel
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
            $message = __( 'Link marcado para reenvio. Configure o Payment Add-on para envio automÃƒÂ¡tico.', 'dps-agenda-addon' );
        }

        if ( $success ) {
            // Renderiza HTML da linha atualizada
            $updated_post = get_post( $appt_id );
                        $row_html = $this->render_row_html_for_active_tab( $updated_post );

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
     * FASE 2: Renderiza relatÃƒÂ³rio de ocupaÃƒÂ§ÃƒÂ£o.
     * Exibe mÃƒÂ©tricas como taxa de ocupaÃƒÂ§ÃƒÂ£o, horÃƒÂ¡rios mais ocupados e cancelamentos.
     *
     * @deprecated 1.6.0 Funcionalidade movida para a aba EstatÃƒÂ­sticas. Use filtro de perÃƒÂ­odo na aba EstatÃƒÂ­sticas.
     * @since 1.2.0
     * @param array  $appointments Lista de agendamentos.
     * @param string $selected_date Data selecionada.
     * @param bool   $is_week_view Se ÃƒÂ© visualizaÃƒÂ§ÃƒÂ£o semanal.
     */
    private function render_occupancy_report( $appointments, $selected_date, $is_week_view ) {
        _deprecated_function( __METHOD__, '1.6.0', __( 'Aba EstatÃƒÂ­sticas', 'dps-agenda-addon' ) );
        
        if ( empty( $appointments ) ) {
            return;
        }
        
        // Calcular mÃƒÂ©tricas
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
        
        // Ordenar horÃƒÂ¡rios
        ksort( $horarios );
        
        // Encontrar horÃƒÂ¡rio mais ocupado
        $horario_pico = '';
        $max_count = 0;
        foreach ( $horarios as $hora => $count ) {
            if ( $count > $max_count ) {
                $max_count = $count;
                $horario_pico = $hora . ':00';
            }
        }
        
        // Calcular taxa de conclusÃƒÂ£o (excluindo cancelados)
        $total_nao_cancelado = $total - $cancelado;
        $taxa_conclusao = $total_nao_cancelado > 0 ? round( ( ( $finalizado + $finalizado_pago ) / $total_nao_cancelado ) * 100 ) : 0;
        $taxa_cancelamento = $total > 0 ? round( ( $cancelado / $total ) * 100 ) : 0;
        
        // PerÃƒÂ­odo do relatÃƒÂ³rio
        if ( $is_week_view ) {
            $period_label = sprintf(
                __( 'Semana de %s', 'dps-agenda-addon' ),
                date_i18n( 'd/m/Y', strtotime( $selected_date ) )
            );
        } else {
            $period_label = date_i18n( 'd/m/Y', strtotime( $selected_date ) );
        }
        
        echo '<details class="dps-occupancy-report">';
        echo '<summary>' . esc_html__( 'Ã°Å¸â€œÅ  RelatÃƒÂ³rio de OcupaÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ) . ' - ' . esc_html( $period_label ) . '</summary>';
        echo '<div class="dps-occupancy-content">';
        
        // Cards de mÃƒÂ©tricas
        echo '<div class="dps-occupancy-cards">';
        
        // Taxa de conclusÃƒÂ£o
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value dps-occupancy-success">' . esc_html( $taxa_conclusao ) . '%</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Taxa de ConclusÃƒÂ£o', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Taxa de cancelamento
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value dps-occupancy-warning">' . esc_html( $taxa_cancelamento ) . '%</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'Taxa de Cancelamento', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // HorÃƒÂ¡rio de pico
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value">' . esc_html( $horario_pico ?: '-' ) . '</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'HorÃƒÂ¡rio de Pico', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // MÃƒÂ©dia por hora ativa (atendimentos ÃƒÂ· horas com agendamentos)
        $horas_com_atendimento = count( $horarios );
        $media_por_hora = $horas_com_atendimento > 0 ? round( $total / $horas_com_atendimento, 1 ) : 0;
        echo '<div class="dps-occupancy-card">';
        echo '<span class="dps-occupancy-value">' . esc_html( $media_por_hora ) . '</span>';
        echo '<span class="dps-occupancy-label">' . esc_html__( 'MÃƒÂ©dia/Hora Ativa', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        echo '</div>';
        
        // DistribuiÃƒÂ§ÃƒÂ£o por status
        echo '<div class="dps-occupancy-status">';
        echo '<h6>' . esc_html__( 'DistribuiÃƒÂ§ÃƒÂ£o por Status', 'dps-agenda-addon' ) . '</h6>';
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
     * FASE 2: Exporta a agenda para PDF (pÃƒÂ¡gina de impressÃƒÂ£o).
     *
     * Gera uma pÃƒÂ¡gina HTML otimizada para impressÃƒÂ£o e salvamento como PDF.
     * Layout moderno e elegante, sem poluiÃƒÂ§ÃƒÂ£o visual.
     *
     * @since 1.4.0
     */
    public function export_pdf_ajax() {
        // Verificar nonce e permissÃƒÂ£o usando helper
        if ( ! DPS_Request_Validator::verify_admin_action( 'dps_agenda_export_pdf', 'manage_options', 'nonce', false ) ) {
            wp_die( esc_html__( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a. Por favor, recarregue a pÃƒÂ¡gina e tente novamente.', 'dps-agenda-addon' ), 403 );
        }
        
        $date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : '';
        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        
        // Validar formato de data se fornecida
        if ( ! empty( $date ) ) {
            $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
                $date = ''; // Data invÃƒÂ¡lida, ignora o filtro
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
        
        // Formatar tÃƒÂ­tulo do perÃƒÂ­odo
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
        
        // Renderizar pÃƒÂ¡gina de impressÃƒÂ£o
        $this->render_pdf_print_page( $appointments, $period_title, $shop_name, $status_labels );
        exit;
    }

    /**
     * Renderiza a pÃƒÂ¡gina de impressÃƒÂ£o PDF da agenda.
     *
     * @since 1.4.0
     * @param array  $appointments   Lista de agendamentos.
     * @param string $period_title   TÃƒÂ­tulo do perÃƒÂ­odo.
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
                <button type="button" class="btn-print" id="dps-print-btn">Ã°Å¸â€“Â¨Ã¯Â¸Â <?php esc_html_e( 'Imprimir / Salvar PDF', 'dps-agenda-addon' ); ?></button>
                <button type="button" class="btn-close" id="dps-close-btn"><?php esc_html_e( 'Fechar', 'dps-agenda-addon' ); ?></button>
            </div>
            <script>
                document.getElementById('dps-print-btn').addEventListener('click', function() { window.print(); });
                document.getElementById('dps-close-btn').addEventListener('click', function() { window.close(); });
            </script>

            <header class="print-header">
                <div class="print-header__info">
                    <div class="print-header__logo">Ã°Å¸ÂÂ¾ <?php echo esc_html( $shop_name ); ?></div>
                    <div class="print-header__period"><?php echo esc_html( $period_title ); ?></div>
                    <div class="print-header__date"><?php echo esc_html( sprintf( __( 'Gerado em %s', 'dps-agenda-addon' ), date_i18n( 'd/m/Y \ÃƒÂ \s H:i' ) ) ); ?></div>
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
                            <span class="print-summary__label">Ã°Å¸Å¸Â¡ <?php esc_html_e( 'Pendentes:', 'dps-agenda-addon' ); ?></span>
                            <span class="print-summary__value"><?php echo esc_html( $status_counts['pendente'] ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $status_counts['finalizado_pago'] > 0 ) : ?>
                        <div class="print-summary__item">
                            <span class="print-summary__label">Ã¢Å“â€¦ <?php esc_html_e( 'Pagos:', 'dps-agenda-addon' ); ?></span>
                            <span class="print-summary__value"><?php echo esc_html( $status_counts['finalizado_pago'] ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'HorÃƒÂ¡rio', 'dps-agenda-addon' ); ?></th>
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
                                    <div class="cell-client"><?php echo esc_html( $client_name ?: 'Ã¢â‚¬â€' ); ?></div>
                                    <?php if ( $pet_name ) : ?>
                                        <div class="cell-pet">Ã°Å¸ÂÂ¾ <?php echo esc_html( $pet_name ); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cell-phone"><?php echo esc_html( $client_phone ?: 'Ã¢â‚¬â€' ); ?></div>
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
                    <div class="empty-state__icon">Ã°Å¸â€œâ€¹</div>
                    <div class="empty-state__message"><?php esc_html_e( 'Nenhum agendamento encontrado para este perÃƒÂ­odo.', 'dps-agenda-addon' ); ?></div>
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
     * FASE 4: Renderiza a visualizaÃƒÂ§ÃƒÂ£o de calendÃƒÂ¡rio mensal.
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
        
        // Calcula mÃƒÂªs anterior e prÃƒÂ³ximo
        $prev_month = ( clone $date_obj )->modify( 'first day of previous month' )->format( 'Y-m-d' );
        $next_month = ( clone $date_obj )->modify( 'first day of next month' )->format( 'Y-m-d' );
        
        // Header do calendÃƒÂ¡rio
        echo '<div class="dps-calendar-header">';
        echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $prev_month, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-nav-btn">Ã¢â€ Â ' . esc_html__( 'Anterior', 'dps-agenda-addon' ) . '</a>';
        
        // TÃƒÂ­tulo e botÃƒÂ£o Hoje
        $today = current_time( 'Y-m-d' );
        echo '<div class="dps-calendar-title-group">';
        echo '<h4 class="dps-calendar-title">' . esc_html( ucfirst( date_i18n( 'F Y', $date_obj->getTimestamp() ) ) ) . '</h4>';
        if ( $selected_date !== $today ) {
            echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $today, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-today-btn">' . esc_html__( 'Hoje', 'dps-agenda-addon' ) . '</a>';
        }
        echo '</div>';
        
        echo '<a href="' . esc_url( add_query_arg( [ 'dps_date' => $next_month, 'view' => 'calendar' ], $base_url ) ) . '" class="dps-calendar-nav-btn">' . esc_html__( 'PrÃƒÂ³ximo', 'dps-agenda-addon' ) . ' Ã¢â€ â€™</a>';
        echo '</div>';
        
        // Container do calendÃƒÂ¡rio
        $calendar_attrs = sprintf(
            'id="dps-calendar-container" class="dps-calendar" data-date="%s" data-ajax="%s" data-nonce="%s"',
            esc_attr( $selected_date ),
            esc_attr( self::get_frontend_ajax_url() ),
            esc_attr( wp_create_nonce( 'dps_agenda_calendar' ) )
        );
        echo '<div ' . $calendar_attrs . '>';
        
        // Renderiza calendÃƒÂ¡rio HTML (fallback se JS nÃƒÂ£o carregar)
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
     * FASE 4: Renderiza o grid HTML do calendÃƒÂ¡rio.
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
        
        // Primeiro dia do mÃƒÂªs
        $first_day = new DateTime( "$year-$month-01" );
        $days_in_month = (int) $first_day->format( 't' );
        $start_weekday = (int) $first_day->format( 'N' ); // 1=seg, 7=dom
        
        // Busca agendamentos do mÃƒÂªs
        $appointments = $this->get_month_appointments( $year, $month );
        
        // Header dos dias da semana
        $weekdays = [
            __( 'Seg', 'dps-agenda-addon' ),
            __( 'Ter', 'dps-agenda-addon' ),
            __( 'Qua', 'dps-agenda-addon' ),
            __( 'Qui', 'dps-agenda-addon' ),
            __( 'Sex', 'dps-agenda-addon' ),
            __( 'SÃƒÂ¡b', 'dps-agenda-addon' ),
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
        
        // CÃƒÂ©lulas vazias antes do primeiro dia
        for ( $i = 1; $i < $start_weekday; $i++ ) {
            echo '<div class="dps-calendar-day dps-calendar-day--empty"></div>';
        }
        
        $base_url = remove_query_arg( [ 'dps_date', 'view', 'show_all' ] );
        $today = current_time( 'Y-m-d' );
        
        // Dias do mÃƒÂªs
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
        
        // CÃƒÂ©lulas vazias apÃƒÂ³s o ÃƒÂºltimo dia
        $end_weekday = (int) ( new DateTime( "$year-$month-$days_in_month" ) )->format( 'N' );
        for ( $i = $end_weekday; $i < 7; $i++ ) {
            echo '<div class="dps-calendar-day dps-calendar-day--empty"></div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * FASE 4: Busca agendamentos do mÃƒÂªs.
     *
     * @since 1.3.0
     * @param int $year Ano.
     * @param int $month MÃƒÂªs.
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
     * FASE 4: Handler AJAX para buscar eventos do calendÃƒÂ¡rio.
     *
     * @since 1.3.0
     */
    public function calendar_events_ajax() {
        // Verificar nonce
        if ( ! check_ajax_referer( 'dps_agenda_calendar', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        // Verificar permissÃƒÂµes
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        $start = isset( $_POST['start'] ) ? sanitize_text_field( $_POST['start'] ) : '';
        $end = isset( $_POST['end'] ) ? sanitize_text_field( $_POST['end'] ) : '';
        
        // Validar formato de data Y-m-d
        if ( empty( $start ) || empty( $end ) || 
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || 
             ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de data invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        
        // Busca agendamentos no perÃƒÂ­odo
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
        
        // Formata eventos para o calendÃƒÂ¡rio
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
    // FASE 5: Funcionalidades Administrativas AvanÃƒÂ§adas
    // =========================================================================

    /**
     * AJAX handler para reagendamento rÃƒÂ¡pido.
     *
     * Permite alterar apenas data e hora de um agendamento sem editar outros campos.
     *
     * @since 1.3.2
     * @return void
     */
    public function quick_reschedule_ajax() {
        // Verificar nonce e permissÃƒÂµes
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_quick_reschedule' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $new_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $new_time = isset( $_POST['time'] ) ? sanitize_text_field( $_POST['time'] ) : '';
        
        if ( ! $appt_id || ! $new_date || ! $new_time ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-agenda-addon' ) ] );
        }
        
        // Validar que o post existe e ÃƒÂ© um agendamento
        $post = get_post( $appt_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }
        
        // Validar formato de data e hora
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de data invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $new_time ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de hora invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        
        // Salvar data/hora anteriores para log
        $old_date = get_post_meta( $appt_id, 'appointment_date', true );
        $old_time = get_post_meta( $appt_id, 'appointment_time', true );
        
        // Atualizar data e hora
        update_post_meta( $appt_id, 'appointment_date', $new_date );
        update_post_meta( $appt_id, 'appointment_time', $new_time );
        
        // Incrementar versÃƒÂ£o
        $version = intval( get_post_meta( $appt_id, '_dps_appointment_version', true ) );
        update_post_meta( $appt_id, '_dps_appointment_version', $version + 1 );
        
        // Registrar no histÃƒÂ³rico
        $this->add_to_appointment_history( $appt_id, 'rescheduled', [
            'old_date' => $old_date,
            'old_time' => $old_time,
            'new_date' => $new_date,
            'new_time' => $new_time,
        ] );
        
        // Disparar hook para notificaÃƒÂ§ÃƒÂµes (pode ser usado por outros add-ons)
        do_action( 'dps_appointment_rescheduled', $appt_id, $new_date, $new_time, $old_date, $old_time );
        
        wp_send_json_success( [
            'message' => __( 'Agendamento reagendado com sucesso.', 'dps-agenda-addon' ),
            'new_date' => date_i18n( 'd/m/Y', strtotime( $new_date ) ),
            'new_time' => $new_time,
        ] );
    }

    /**
     * AJAX handler para obter histÃƒÂ³rico de alteraÃƒÂ§ÃƒÂµes de um agendamento.
     *
     * @since 1.3.2
     * @return void
     */
    public function get_appointment_history_ajax() {
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_appointment_history' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }
        
        $appt_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        
        if ( ! $appt_id ) {
            wp_send_json_error( [ 'message' => __( 'ID invÃƒÂ¡lido.', 'dps-agenda-addon' ) ] );
        }
        
        $history = get_post_meta( $appt_id, '_dps_appointment_history', true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }
        
        // Formatar para exibiÃƒÂ§ÃƒÂ£o
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
     * Retorna mÃƒÂ©tricas consolidadas para o dashboard administrativo.
     *
     * @since 1.3.2
     * @return void
     */
    public function get_admin_kpis_ajax() {
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dps_admin_kpis' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }
        
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
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
        
        // MÃƒÂ©dia de atendimentos por dia (ÃƒÂºltimos 7 dias)
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
            'revenue_formatted' => DPS_Money_Helper::format_currency( $revenue_estimate ),
            'cancel_rate'     => $cancel_rate,
            'avg_daily'       => $avg_daily,
        ];
    }

    /**
     * Registra alteraÃƒÂ§ÃƒÂ£o de status no histÃƒÂ³rico do agendamento.
     *
     * @since 1.3.2
     * @param int    $appt_id    ID do agendamento.
     * @param string $old_status Status anterior.
     * @param string $new_status Novo status.
     * @param int    $user_id    ID do usuÃƒÂ¡rio que fez a alteraÃƒÂ§ÃƒÂ£o.
     * @return void
     */
    public function log_status_change( $appt_id, $old_status, $new_status, $user_id ) {
        $this->add_to_appointment_history( $appt_id, 'status_change', [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );
    }

    /**
     * Adiciona entrada ao histÃƒÂ³rico de um agendamento.
     *
     * @since 1.3.2
     * @param int    $appt_id ID do agendamento.
     * @param string $action  Tipo de aÃƒÂ§ÃƒÂ£o (created, status_change, rescheduled).
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
     * Renderiza o dashboard de KPIs como seÃƒÂ§ÃƒÂ£o colapsÃƒÂ¡vel no final da agenda.
     *
     * @deprecated 1.6.0 Funcionalidade movida para a aba EstatÃƒÂ­sticas. Use filtro de perÃƒÂ­odo na aba EstatÃƒÂ­sticas.
     * @since 1.3.2
     * @since 1.4.1 Modificado para usar <details> colapsÃƒÂ¡vel, fechado por padrÃƒÂ£o
     * @param string $date Data selecionada.
     * @return void
     */
    private function render_admin_dashboard( $date ) {
        _deprecated_function( __METHOD__, '1.6.0', __( 'Aba EstatÃƒÂ­sticas', 'dps-agenda-addon' ) );
        
        $kpis = $this->calculate_admin_kpis( $date );
        $status_config = self::get_status_config();
        $date_formatted = date_i18n( 'd/m/Y', strtotime( $date ) );
        
        echo '<details class="dps-summary-report">';
        echo '<summary>Ã°Å¸â€œÅ  ' . esc_html__( 'Resumo do Dia', 'dps-agenda-addon' ) . ' - ' . esc_html( $date_formatted ) . '</summary>';
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
        echo '<span class="dps-kpi-icon">Ã°Å¸â€™Â°</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['revenue_formatted'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Faturamento Est.', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: Taxa de Cancelamento
        echo '<div class="dps-kpi-card dps-kpi-cancel">';
        echo '<span class="dps-kpi-icon">Ã°Å¸â€œâ€°</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['cancel_rate'] ) . '%</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'Cancelamentos (semana)', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        // Card: MÃƒÂ©dia DiÃƒÂ¡ria
        echo '<div class="dps-kpi-card dps-kpi-avg">';
        echo '<span class="dps-kpi-icon">Ã°Å¸â€œË†</span>';
        echo '<span class="dps-kpi-value">' . esc_html( $kpis['avg_daily'] ) . '</span>';
        echo '<span class="dps-kpi-label">' . esc_html__( 'MÃƒÂ©dia/dia (7d)', 'dps-agenda-addon' ) . '</span>';
        echo '</div>';
        
        echo '</div>'; // .dps-kpi-grid
        echo '</div>'; // .dps-summary-content
        echo '</details>'; // .dps-summary-report
    }

    /**
     * Retorna a URL da pÃƒÂ¡gina atual.
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

    /* ===========================
       CHECKLIST OPERACIONAL Ã¢â‚¬â€ AJAX
       =========================== */

    /**
     * AJAX: Atualiza o status de uma etapa do checklist.
     *
     * Espera POST: appointment_id, step_key, status (done|pending|skipped).
     *
     * @since 1.2.0
     */
    public function checklist_update_ajax() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_checklist' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $step_key       = isset( $_POST['step_key'] ) ? sanitize_key( $_POST['step_key'] ) : '';
        $status         = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

        if ( ! $appointment_id || ! $step_key || ! $status ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }

        $updated = DPS_Agenda_Checklist_Service::update_step( $appointment_id, $step_key, $status );

        if ( ! $updated ) {
            wp_send_json_error( [ 'message' => __( 'NÃƒÂ£o foi possÃƒÂ­vel atualizar o checklist.', 'dps-agenda-addon' ) ] );
        }

        wp_send_json_success( [
            'message'      => __( 'Checklist atualizado.', 'dps-agenda-addon' ),
            'progress'     => DPS_Agenda_Checklist_Service::get_progress( $appointment_id ),
            'rework_count' => DPS_Agenda_Checklist_Service::count_reworks( $appointment_id ),
        ] );
    }

    /**
     * AJAX: Registra retrabalho em uma etapa do checklist.
     *
     * Espera POST: appointment_id, step_key, reason (opcional).
     *
     * @since 1.2.0
     */
    public function checklist_rework_ajax() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_checklist' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $step_key       = isset( $_POST['step_key'] ) ? sanitize_key( $_POST['step_key'] ) : '';
        $reason         = isset( $_POST['reason'] ) ? sanitize_textarea_field( $_POST['reason'] ) : '';

        if ( ! $appointment_id || ! $step_key ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }

        $registered = DPS_Agenda_Checklist_Service::register_rework( $appointment_id, $step_key, $reason );

        if ( ! $registered ) {
            wp_send_json_error( [ 'message' => __( 'NÃƒÂ£o foi possÃƒÂ­vel registrar o retrabalho.', 'dps-agenda-addon' ) ] );
        }

        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Retrabalho registrado na etapa "%s" Ã¢â‚¬â€ %s',
                    $appointment_id,
                    $step_key,
                    $reason ?: '(sem motivo)'
                ),
                [
                    'appointment_id' => $appointment_id,
                    'step_key'       => $step_key,
                    'reason'         => $reason,
                    'user_id'        => get_current_user_id(),
                ],
                'agenda'
            );
        }

        wp_send_json_success( [
            'message'      => __( 'Retrabalho registrado.', 'dps-agenda-addon' ),
            'progress'     => DPS_Agenda_Checklist_Service::get_progress( $appointment_id ),
            'rework_count' => DPS_Agenda_Checklist_Service::count_reworks( $appointment_id ),
        ] );
    }

    /**
     * AJAX: Retorna o HTML do painel de checklist para exibiÃƒÂ§ÃƒÂ£o em popup.
     *
     * Espera POST: appointment_id, nonce (dps_checklist).
     *
     * @since 1.5.0
     */
    public function get_checklist_panel_ajax() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_checklist' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        if ( ! $appointment_id ) {
            wp_send_json_error( [ 'message' => __( 'Dados invÃƒÂ¡lidos.', 'dps-agenda-addon' ) ] );
        }

        $html = self::render_checklist_panel( $appointment_id );

        wp_send_json_success( [ 'html' => $html ] );
    }

    /* ===========================
       CHECK-IN / CHECK-OUT Ã¢â‚¬â€ AJAX
       =========================== */

    /**
     * AJAX: Registra check-in de um agendamento.
     *
     * Espera POST: appointment_id, observations, safety_items[slug][checked/notes].
     *
     * @since 1.2.0
     */
    public function appointment_checkin_ajax() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_checkin' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $observations   = isset( $_POST['observations'] ) ? sanitize_textarea_field( $_POST['observations'] ) : '';
        $safety_items   = isset( $_POST['safety_items'] ) && is_array( $_POST['safety_items'] ) ? $_POST['safety_items'] : [];

        if ( ! $appointment_id ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }

        $saved = DPS_Agenda_Checkin_Service::checkin( $appointment_id, $observations, $safety_items );

        if ( ! $saved ) {
            wp_send_json_error( [ 'message' => __( 'NÃƒÂ£o foi possÃƒÂ­vel registrar o check-in.', 'dps-agenda-addon' ) ] );
        }

        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf( 'Agendamento #%d: Check-in registrado', $appointment_id ),
                [
                    'appointment_id' => $appointment_id,
                    'user_id'        => get_current_user_id(),
                ],
                'agenda'
            );
        }

        wp_send_json_success( $this->build_checkin_response( $appointment_id ) );
    }

    /**
     * AJAX: Registra check-out de um agendamento.
     *
     * Espera POST: appointment_id, observations, safety_items[slug][checked/notes].
     *
     * @since 1.2.0
     */
    public function appointment_checkout_ajax() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'PermissÃƒÂ£o negada.', 'dps-agenda-addon' ) ] );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_checkin' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificaÃƒÂ§ÃƒÂ£o de seguranÃƒÂ§a.', 'dps-agenda-addon' ) ] );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $observations   = isset( $_POST['observations'] ) ? sanitize_textarea_field( $_POST['observations'] ) : '';
        $safety_items   = isset( $_POST['safety_items'] ) && is_array( $_POST['safety_items'] ) ? $_POST['safety_items'] : [];

        if ( ! $appointment_id ) {
            wp_send_json_error( [ 'message' => __( 'Agendamento nÃƒÂ£o encontrado.', 'dps-agenda-addon' ) ] );
        }

        if ( ! DPS_Agenda_Checkin_Service::has_checkin( $appointment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Ãƒâ€° necessÃƒÂ¡rio fazer o check-in antes do check-out.', 'dps-agenda-addon' ) ] );
        }

        $saved = DPS_Agenda_Checkin_Service::checkout( $appointment_id, $observations, $safety_items );

        if ( ! $saved ) {
            wp_send_json_error( [ 'message' => __( 'NÃƒÂ£o foi possÃƒÂ­vel registrar o check-out.', 'dps-agenda-addon' ) ] );
        }

        if ( class_exists( 'DPS_Logger' ) ) {
            $duration = DPS_Agenda_Checkin_Service::get_duration_minutes( $appointment_id );
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Check-out registrado (duraÃƒÂ§ÃƒÂ£o: %s min)',
                    $appointment_id,
                    $duration !== false ? $duration : '?'
                ),
                [
                    'appointment_id' => $appointment_id,
                    'duration'       => $duration,
                    'user_id'        => get_current_user_id(),
                ],
                'agenda'
            );
        }

        wp_send_json_success( $this->build_checkin_response( $appointment_id ) );
    }

    /**
     * Monta a resposta padrÃƒÂ£o do painel de check-in/check-out.
     *
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return array Dados formatados para o JS.
     */
    private function build_checkin_response( $appointment_id ) {
        $checkin  = DPS_Agenda_Checkin_Service::get_checkin( $appointment_id );
        $checkout = DPS_Agenda_Checkin_Service::get_checkout( $appointment_id );
        $duration = DPS_Agenda_Checkin_Service::get_duration_minutes( $appointment_id );

        $response = [
            'has_checkin'    => (bool) $checkin,
            'has_checkout'   => (bool) $checkout,
            'checkin_time'   => $checkin ? mysql2date( 'H:i', $checkin['time'] ) : '',
            'checkout_time'  => $checkout ? mysql2date( 'H:i', $checkout['time'] ) : '',
            'duration'       => false !== $duration
                ? sprintf( __( '%d min', 'dps-agenda-addon' ), $duration )
                : '',
            'safety_summary' => [],
            'whatsapp_url'   => '',
        ];

        $summary = DPS_Agenda_Checkin_Service::get_safety_summary( $appointment_id );
        foreach ( $summary as $item ) {
            $response['safety_summary'][] = [
                'label'    => $item['label'],
                'icon'     => $item['icon'],
                'severity' => $item['severity'],
            ];
        }

        // Gera link WhatsApp se houver check-in e helpers disponÃƒÂ­veis.
        if ( $checkin && class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $response['whatsapp_url'] = $this->build_checkin_whatsapp_url( $appointment_id );
        }

        return $response;
    }

    /**
     * Monta a URL do WhatsApp com mensagem de relatÃƒÂ³rio do check-in/check-out.
     *
     * @since 1.3.0
     * @param int $appointment_id ID do agendamento.
     * @return string URL do WhatsApp ou string vazia se telefone indisponÃƒÂ­vel.
     */
    private function build_checkin_whatsapp_url( $appointment_id ) {
        $client_id = get_post_meta( $appointment_id, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appointment_id, 'appointment_pet_id', true );

        $client_post = $client_id ? get_post( $client_id ) : null;
        $pet_post    = $pet_id ? get_post( $pet_id ) : null;

        $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
        if ( empty( $client_phone ) ) {
            return '';
        }

        $client_name = $client_post ? $client_post->post_title : '';
        $pet_name    = $pet_post ? $pet_post->post_title : '';

        $checkin  = DPS_Agenda_Checkin_Service::get_checkin( $appointment_id );
        $checkout = DPS_Agenda_Checkin_Service::get_checkout( $appointment_id );
        $duration = DPS_Agenda_Checkin_Service::get_duration_minutes( $appointment_id );

        $report_data = [
            'client_name'    => $client_name,
            'pet_name'       => $pet_name,
            'checkin_time'   => $checkin ? mysql2date( 'H:i', $checkin['time'] ) : '',
            'checkout_time'  => $checkout ? mysql2date( 'H:i', $checkout['time'] ) : '',
            'duration'       => false !== $duration
                ? sprintf( __( '%d min', 'dps-agenda-addon' ), $duration )
                : '',
            'safety_summary' => [],
            'observations_in'  => $checkin && ! empty( $checkin['observations'] ) ? $checkin['observations'] : '',
            'observations_out' => $checkout && ! empty( $checkout['observations'] ) ? $checkout['observations'] : '',
        ];

        // Monta safety_summary com notas para a mensagem.
        $summary = DPS_Agenda_Checkin_Service::get_safety_summary( $appointment_id );
        foreach ( $summary as $item ) {
            $report_data['safety_summary'][] = [
                'icon'  => $item['icon'],
                'label' => $item['label'],
                'notes' => isset( $item['notes'] ) ? $item['notes'] : '',
            ];
        }

        $message = DPS_WhatsApp_Helper::get_checkin_report_message( $report_data );

        return DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $message );
    }

    /* ===========================
       RENDER HELPERS Ã¢â‚¬â€ Checklist & Check-in/Check-out
       =========================== */

    /**
     * Renderiza o painel do Checklist Operacional para um agendamento.
     *
     * Pode ser chamado em templates de cartÃƒÂ£o de agendamento na agenda.
     *
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do painel.
     */
    public static function render_checklist_panel( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id || ! current_user_can( 'manage_options' ) ) {
            return '';
        }

        $checklist = DPS_Agenda_Checklist_Service::get( $appointment_id );
        $steps     = DPS_Agenda_Checklist_Service::get_default_steps();
        $progress  = DPS_Agenda_Checklist_Service::get_progress( $appointment_id );

        ob_start();
        ?>
        <div class="dps-checklist-panel" data-appointment="<?php echo esc_attr( $appointment_id ); ?>">
            <h4>Ã°Å¸â€œâ€¹ <?php esc_html_e( 'Checklist Operacional', 'dps-agenda-addon' ); ?></h4>

            <div class="dps-checklist-progress">
                <div class="dps-checklist-progress-bar">
                    <div class="dps-checklist-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%"></div>
                </div>
                <span class="dps-checklist-progress-text"><?php echo esc_html( $progress ); ?>%</span>
            </div>

            <ul class="dps-checklist-steps">
            <?php foreach ( $steps as $key => $step ) :
                $item   = isset( $checklist[ $key ] ) ? $checklist[ $key ] : [ 'status' => 'pending', 'rework' => [] ];
                $status = isset( $item['status'] ) ? $item['status'] : 'pending';
                $rework_count = ! empty( $item['rework'] ) ? count( $item['rework'] ) : 0;
            ?>
                <li class="dps-checklist-step" data-step="<?php echo esc_attr( $key ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
                    <span class="dps-checklist-step-icon"><?php echo esc_html( $step['icon'] ); ?></span>
                    <span class="dps-checklist-step-label"><?php echo esc_html( $step['label'] ); ?></span>

                    <?php if ( $rework_count > 0 ) : ?>
                        <span class="dps-checklist-rework-badge">Ã°Å¸â€â€ž <?php echo esc_html( $rework_count ); ?></span>
                    <?php endif; ?>

                    <span class="dps-checklist-step-actions">
                        <?php if ( 'pending' === $status ) : ?>
                            <button class="dps-checklist-btn dps-checklist-btn--done" type="button">Ã¢Å“â€œ <?php esc_html_e( 'Concluir', 'dps-agenda-addon' ); ?></button>
                            <button class="dps-checklist-btn dps-checklist-btn--skip" type="button"><?php esc_html_e( 'Pular', 'dps-agenda-addon' ); ?></button>
                        <?php elseif ( 'done' === $status ) : ?>
                            <button class="dps-checklist-btn dps-checklist-btn--undo" type="button">Ã¢â€ Â© <?php esc_html_e( 'Desfazer', 'dps-agenda-addon' ); ?></button>
                            <button class="dps-checklist-btn dps-checklist-btn--rework" type="button">Ã°Å¸â€â€ž <?php esc_html_e( 'Refazer', 'dps-agenda-addon' ); ?></button>
                        <?php elseif ( 'skipped' === $status ) : ?>
                            <button class="dps-checklist-btn dps-checklist-btn--undo" type="button">Ã¢â€ Â© <?php esc_html_e( 'Desfazer', 'dps-agenda-addon' ); ?></button>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o painel de Check-in / Check-out para um agendamento.
     *
     * Pode ser chamado em templates de cartÃƒÂ£o de agendamento na agenda.
     *
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do painel.
     */
    public static function render_checkin_panel( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id || ! current_user_can( 'manage_options' ) ) {
            return '';
        }

        $checkin      = DPS_Agenda_Checkin_Service::get_checkin( $appointment_id );
        $checkout     = DPS_Agenda_Checkin_Service::get_checkout( $appointment_id );
        $duration     = DPS_Agenda_Checkin_Service::get_duration_minutes( $appointment_id );
        $safety_items = DPS_Agenda_Checkin_Service::get_safety_items();
        $summary      = DPS_Agenda_Checkin_Service::get_safety_summary( $appointment_id );

        ob_start();
        ?>
        <div class="dps-checkin-panel" data-appointment="<?php echo esc_attr( $appointment_id ); ?>">
            <h4>Ã°Å¸ÂÂ¥ <?php esc_html_e( 'Check-in / Check-out', 'dps-agenda-addon' ); ?></h4>

            <div class="dps-checkin-status">
                <?php if ( $checkin ) : ?>
                    <span class="dps-checkin-status-badge dps-checkin-status-badge--in">
                        Ã°Å¸â€œÂ¥ Check-in: <?php echo esc_html( mysql2date( 'H:i', $checkin['time'] ) ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( $checkout ) : ?>
                    <span class="dps-checkin-status-badge dps-checkin-status-badge--out">
                        Ã°Å¸â€œÂ¤ Check-out: <?php echo esc_html( mysql2date( 'H:i', $checkout['time'] ) ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( false !== $duration ) : ?>
                    <span class="dps-checkin-status-badge dps-checkin-status-badge--duration">
                        Ã¢ÂÂ±Ã¯Â¸Â <?php printf( esc_html__( '%d min', 'dps-agenda-addon' ), $duration ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( ! $checkin && ! $checkout ) : ?>
                    <span class="dps-checkin-status-badge dps-checkin-status-badge--pending">
                        <?php esc_html_e( 'Aguardando check-in', 'dps-agenda-addon' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $summary ) ) : ?>
                <div class="dps-safety-summary">
                    <?php foreach ( $summary as $item ) : ?>
                        <span class="dps-safety-tag dps-safety-tag--<?php echo esc_attr( $item['severity'] ); ?>">
                            <?php echo esc_html( $item['icon'] . ' ' . $item['label'] ); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! $checkin || ! $checkout ) : ?>
                <div class="dps-safety-items">
                    <?php foreach ( $safety_items as $slug => $item ) : ?>
                        <div class="dps-safety-item" data-slug="<?php echo esc_attr( $slug ); ?>" data-severity="<?php echo esc_attr( $item['severity'] ); ?>">
                            <span class="dps-safety-item-icon"><?php echo esc_html( $item['icon'] ); ?></span>
                            <div class="dps-safety-item-content">
                                <label class="dps-safety-item-label">
                                    <input type="checkbox" name="safety_<?php echo esc_attr( $slug ); ?>" value="1">
                                    <?php echo esc_html( $item['label'] ); ?>
                                </label>
                                <textarea class="dps-safety-item-notes" rows="1" placeholder="<?php esc_attr_e( 'Detalhes...', 'dps-agenda-addon' ); ?>"></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="dps-checkin-observations">
                    <label><?php esc_html_e( 'ObservaÃƒÂ§ÃƒÂµes gerais', 'dps-agenda-addon' ); ?></label>
                    <textarea placeholder="<?php esc_attr_e( 'Ex.: pet chegou agitado, tutor pediu corte mais curto...', 'dps-agenda-addon' ); ?>"></textarea>
                </div>
            <?php endif; ?>

            <div class="dps-checkin-actions">
                <?php if ( ! $checkin ) : ?>
                    <button type="button" class="dps-checkin-btn dps-checkin-btn--checkin">Ã°Å¸â€œÂ¥ <?php esc_html_e( 'Check-in', 'dps-agenda-addon' ); ?></button>
                <?php elseif ( ! $checkout ) : ?>
                    <button type="button" class="dps-checkin-btn dps-checkin-btn--checkout">Ã°Å¸â€œÂ¤ <?php esc_html_e( 'Check-out', 'dps-agenda-addon' ); ?></button>
                <?php endif; ?>
            </div>

            <?php
            // BotÃƒÂ£o WhatsApp Ã¢â‚¬â€ exibido quando hÃƒÂ¡ check-in registrado.
            if ( $checkin ) :
                $instance   = self::get_instance();
                $wa_url     = $instance->build_checkin_whatsapp_url( $appointment_id );
                $has_wa_url = ! empty( $wa_url );
            ?>
                <div class="dps-checkin-whatsapp<?php echo $has_wa_url ? '' : ' dps-checkin-whatsapp--hidden'; ?>">
                    <a href="<?php echo $has_wa_url ? esc_url( $wa_url ) : '#'; ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="dps-checkin-btn dps-checkin-btn--whatsapp">
                        Ã°Å¸â€œÂ± <?php esc_html_e( 'Enviar relatÃƒÂ³rio via WhatsApp', 'dps-agenda-addon' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza resumo compacto de Checklist e Check-in/Check-out para uso em histÃƒÂ³ricos.
     *
     * Exibe informaÃƒÂ§ÃƒÂµes somente-leitura sobre o progresso do checklist operacional,
     * horÃƒÂ¡rios de check-in/check-out, duraÃƒÂ§ÃƒÂ£o e itens de seguranÃƒÂ§a identificados.
     * Projetado para ser chamado de qualquer view de histÃƒÂ³rico (aba histÃƒÂ³rico,
     * pÃƒÂ¡gina do cliente, portal do cliente, timeline).
     *
     * @since 1.3.0
     * @param int  $appointment_id ID do agendamento.
     * @param bool $is_public      Se true, omite informaÃƒÂ§ÃƒÂµes sensÃƒÂ­veis (ex.: portal do cliente).
     * @return string HTML do resumo ou string vazia se nÃƒÂ£o houver dados.
     */
    public static function render_checkin_checklist_summary( $appointment_id, $is_public = false ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return '';
        }

        // Verifica se as service classes estÃƒÂ£o disponÃƒÂ­veis.
        if ( ! class_exists( 'DPS_Agenda_Checklist_Service' ) || ! class_exists( 'DPS_Agenda_Checkin_Service' ) ) {
            return '';
        }

        $progress     = DPS_Agenda_Checklist_Service::get_progress( $appointment_id );
        $rework_count = DPS_Agenda_Checklist_Service::count_reworks( $appointment_id );
        $checkin      = DPS_Agenda_Checkin_Service::get_checkin( $appointment_id );
        $checkout     = DPS_Agenda_Checkin_Service::get_checkout( $appointment_id );
        $duration     = DPS_Agenda_Checkin_Service::get_duration_minutes( $appointment_id );
        $summary      = DPS_Agenda_Checkin_Service::get_safety_summary( $appointment_id );

        // Se nÃƒÂ£o hÃƒÂ¡ dados relevantes, retorna vazio.
        $has_checklist = $progress > 0;
        $has_checkin   = (bool) $checkin;
        if ( ! $has_checklist && ! $has_checkin ) {
            return '';
        }

        ob_start();
        ?>
        <div class="dps-history-ops-summary">
            <?php if ( ! $is_public && $has_checklist ) : ?>
                <div class="dps-history-ops-row">
                    <span class="dps-history-ops-label">Ã°Å¸â€œâ€¹ <?php esc_html_e( 'Checklist', 'dps-agenda-addon' ); ?></span>
                    <span class="dps-history-ops-value <?php echo 100 === $progress ? 'dps-history-ops-value--complete' : ''; ?>">
                        <?php echo esc_html( $progress ); ?>%
                    </span>
                    <?php if ( $rework_count > 0 ) : ?>
                        <span class="dps-history-ops-badge dps-history-ops-badge--rework">Ã°Å¸â€â€ž <?php echo esc_html( $rework_count ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ( $has_checkin ) : ?>
                <div class="dps-history-ops-row">
                    <span class="dps-history-ops-label">Ã°Å¸â€œÂ¥ <?php esc_html_e( 'Check-in', 'dps-agenda-addon' ); ?></span>
                    <span class="dps-history-ops-value"><?php echo esc_html( mysql2date( 'H:i', $checkin['time'] ) ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $checkout ) : ?>
                <div class="dps-history-ops-row">
                    <span class="dps-history-ops-label">Ã°Å¸â€œÂ¤ <?php esc_html_e( 'Check-out', 'dps-agenda-addon' ); ?></span>
                    <span class="dps-history-ops-value"><?php echo esc_html( mysql2date( 'H:i', $checkout['time'] ) ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( false !== $duration ) : ?>
                <div class="dps-history-ops-row">
                    <span class="dps-history-ops-label">Ã¢ÂÂ±Ã¯Â¸Â <?php esc_html_e( 'DuraÃƒÂ§ÃƒÂ£o', 'dps-agenda-addon' ); ?></span>
                    <span class="dps-history-ops-value"><?php printf( esc_html__( '%d min', 'dps-agenda-addon' ), $duration ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $summary ) ) : ?>
                <div class="dps-history-ops-safety">
                    <?php foreach ( $summary as $item ) : ?>
                        <span class="dps-safety-tag dps-safety-tag--<?php echo esc_attr( $item['severity'] ); ?>" title="<?php echo esc_attr( $item['notes'] ); ?>">
                            <?php echo esc_html( $item['icon'] . ' ' . $item['label'] ); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $has_checkin && ! empty( $checkin['observations'] ) ) : ?>
                <div class="dps-history-ops-obs">
                    <span class="dps-history-ops-label">Ã°Å¸â€œÂ</span>
                    <span class="dps-history-ops-obs-text"><?php echo esc_html( $checkin['observations'] ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $checkout && ! empty( $checkout['observations'] ) ) : ?>
                <div class="dps-history-ops-obs">
                    <span class="dps-history-ops-label">Ã°Å¸â€œÂ</span>
                    <span class="dps-history-ops-obs-text"><?php echo esc_html( $checkout['observations'] ); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza indicadores compactos de checklist e check-in para uso em cards.
     *
     * @since 1.2.0
     * @param int $appointment_id ID do agendamento.
     * @return string HTML dos indicadores compactos.
     */
    public static function render_compact_indicators( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return '';
        }

        $progress     = DPS_Agenda_Checklist_Service::get_progress( $appointment_id );
        $rework_count = DPS_Agenda_Checklist_Service::count_reworks( $appointment_id );
        $has_checkin  = DPS_Agenda_Checkin_Service::has_checkin( $appointment_id );
        $has_checkout = DPS_Agenda_Checkin_Service::has_checkout( $appointment_id );
        $summary      = DPS_Agenda_Checkin_Service::get_safety_summary( $appointment_id );

        ob_start();
        ?>
        <span class="dps-checklist-compact" title="<?php esc_attr_e( 'Checklist Operacional', 'dps-agenda-addon' ); ?>">
            Ã°Å¸â€œâ€¹ <?php echo esc_html( $progress ); ?>%
            <?php if ( $rework_count > 0 ) : ?>
                <span class="dps-checklist-rework-badge">Ã°Å¸â€â€ž <?php echo esc_html( $rework_count ); ?></span>
            <?php endif; ?>
        </span>

        <span class="dps-checkin-compact" title="<?php esc_attr_e( 'Check-in / Check-out', 'dps-agenda-addon' ); ?>">
            <?php if ( $has_checkout ) : ?>
                Ã¢Å“â€¦
            <?php elseif ( $has_checkin ) : ?>
                Ã°Å¸â€œÂ¥
            <?php else : ?>
                Ã¢Â¬Å“
            <?php endif; ?>
        </span>

        <?php foreach ( $summary as $item ) : ?>
            <span class="dps-safety-tag dps-safety-tag--<?php echo esc_attr( $item['severity'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>">
                <?php echo esc_html( $item['icon'] ); ?>
            </span>
        <?php endforeach; ?>
        <?php
        return ob_get_clean();
    }


}

/**
 * Inicializa o Agenda Add-on apÃƒÂ³s o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar apÃƒÂ³s o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_agenda_init_addon() {
    if ( class_exists( 'DPS_Agenda_Addon' ) ) {
        DPS_Agenda_Addon::get_instance();
        
        // Inicializa o Hub centralizado de Agenda (Fase 2 - ReorganizaÃƒÂ§ÃƒÂ£o de Menus)
        if ( class_exists( 'DPS_Agenda_Hub' ) ) {
            DPS_Agenda_Hub::get_instance();
        }
    }
}
add_action( 'init', 'dps_agenda_init_addon', 5 );
