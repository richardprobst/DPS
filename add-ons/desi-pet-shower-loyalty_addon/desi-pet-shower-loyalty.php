<?php
/**
 * Plugin Name:       DPS by PRObst – Campanhas & Fidelidade
 * Plugin URI:        https://www.probst.pro
 * Description:       Programa de fidelidade e campanhas promocionais. Fidelize seus clientes com pontos e benefícios exclusivos.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-loyalty-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'DPS_LOYALTY_VERSION', '1.2.0' );
define( 'DPS_LOYALTY_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_LOYALTY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_loyalty_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Campanhas & Fidelidade requer o plugin base DPS by PRObst para funcionar.', 'dps-loyalty-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_loyalty_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Loyalty Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_loyalty_load_textdomain() {
    load_plugin_textdomain( 'dps-loyalty-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_loyalty_load_textdomain', 1 );

// Carrega a API pública
require_once DPS_LOYALTY_DIR . 'includes/class-dps-loyalty-api.php';

class DPS_Loyalty_Addon {

    const OPTION_KEY = 'dps_loyalty_settings';

    /**
     * Helper para registrar o CPT de campanhas.
     *
     * @var DPS_CPT_Helper|null
     */
    private $cpt_helper;

    public function __construct() {
        // Registra CPT (o helper será inicializado dentro do método register_post_type)
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_campaign_metaboxes' ] );
        add_action( 'save_post_dps_campaign', [ $this, 'save_campaign_meta' ] );
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_post_dps_loyalty_run_audit', [ $this, 'handle_campaign_audit' ] );
        add_action( 'admin_post_dps_loyalty_export_referrals', [ $this, 'handle_export_referrals' ] );
        add_action( 'updated_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
        
        // Enfileira assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
    }

    /**
     * Enfileira assets no admin.
     *
     * @param string $hook Hook atual do admin.
     */
    public function enqueue_admin_assets( $hook ) {
        // Carrega apenas nas páginas relevantes
        $is_loyalty_page = strpos( $hook, 'dps-loyalty' ) !== false;
        $is_campaign_edit = false;
        
        // Verifica se estamos editando uma campanha
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            $is_campaign_edit = $screen && $screen->post_type === 'dps_campaign';
        }
        
        if ( ! $is_loyalty_page && ! $is_campaign_edit ) {
            return;
        }

        wp_enqueue_style(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/css/loyalty-addon.css',
            [],
            DPS_LOYALTY_VERSION
        );

        wp_enqueue_script(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/js/loyalty-addon.js',
            [ 'jquery' ],
            DPS_LOYALTY_VERSION,
            true
        );
    }

    /**
     * Enfileira assets no frontend.
     */
    public function enqueue_frontend_assets() {
        // Carrega apenas quando necessário (ex: portal do cliente)
        if ( ! is_singular() ) {
            return;
        }

        wp_enqueue_style(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/css/loyalty-addon.css',
            [],
            DPS_LOYALTY_VERSION
        );

        wp_enqueue_script(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/js/loyalty-addon.js',
            [ 'jquery' ],
            DPS_LOYALTY_VERSION,
            true
        );
    }

    public function register_post_type() {
        // Inicializa o CPT helper se necessário
        if ( ! $this->cpt_helper ) {
            if ( ! class_exists( 'DPS_CPT_Helper' ) && defined( 'DPS_BASE_DIR' ) ) {
                require_once DPS_BASE_DIR . 'includes/class-dps-cpt-helper.php';
            }

            if ( class_exists( 'DPS_CPT_Helper' ) ) {
                $this->cpt_helper = new DPS_CPT_Helper(
                    'dps_campaign',
                    [
                        'name'               => _x( 'Campanhas', 'post type general name', 'dps-loyalty-addon' ),
                        'singular_name'      => _x( 'Campanha', 'post type singular name', 'dps-loyalty-addon' ),
                        'menu_name'          => _x( 'Campanhas', 'admin menu', 'dps-loyalty-addon' ),
                        'name_admin_bar'     => _x( 'Campanha', 'add new on admin bar', 'dps-loyalty-addon' ),
                        'add_new'            => _x( 'Adicionar nova', 'campaign', 'dps-loyalty-addon' ),
                        'add_new_item'       => __( 'Adicionar nova campanha', 'dps-loyalty-addon' ),
                        'new_item'           => __( 'Nova campanha', 'dps-loyalty-addon' ),
                        'edit_item'          => __( 'Editar campanha', 'dps-loyalty-addon' ),
                        'view_item'          => __( 'Ver campanha', 'dps-loyalty-addon' ),
                        'all_items'          => __( 'Todas as campanhas', 'dps-loyalty-addon' ),
                        'search_items'       => __( 'Buscar campanhas', 'dps-loyalty-addon' ),
                        'not_found'          => __( 'Nenhuma campanha encontrada.', 'dps-loyalty-addon' ),
                        'not_found_in_trash' => __( 'Nenhuma campanha na lixeira.', 'dps-loyalty-addon' ),
                    ],
                    [
                        'public'          => false,
                        'show_ui'         => true,
                        'show_in_menu'    => false,
                        'supports'        => [ 'title', 'editor' ],
                        'capability_type' => 'post',
                        'map_meta_cap'    => true,
                        'has_archive'     => false,
                    ]
                );
            }
        }

        if ( $this->cpt_helper ) {
            $this->cpt_helper->register();
        }
    }

    public function register_campaign_metaboxes() {
        add_meta_box(
            'dps_campaign_details',
            __( 'Configurações da campanha', 'dps-loyalty-addon' ),
            [ $this, 'render_campaign_details_meta_box' ],
            'dps_campaign',
            'normal',
            'high'
        );
    }

    public function render_campaign_details_meta_box( $post ) {
        wp_nonce_field( 'dps_campaign_details', 'dps_campaign_details_nonce' );

        $campaign_type         = get_post_meta( $post->ID, 'dps_campaign_type', true );
        $eligibility           = get_post_meta( $post->ID, 'dps_campaign_eligibility', true );
        $inactive_days         = get_post_meta( $post->ID, 'dps_campaign_inactive_days', true );
        $points_threshold      = get_post_meta( $post->ID, 'dps_campaign_points_threshold', true );
        $start_date            = get_post_meta( $post->ID, 'dps_campaign_start_date', true );
        $end_date              = get_post_meta( $post->ID, 'dps_campaign_end_date', true );
        $eligibility_selection = is_array( $eligibility ) ? $eligibility : [];
        ?>
        <p>
            <label for="dps_campaign_type"><strong><?php esc_html_e( 'Tipo de campanha', 'dps-loyalty-addon' ); ?></strong></label>
            <select id="dps_campaign_type" name="dps_campaign_type" class="widefat">
                <option value="percentage" <?php selected( $campaign_type, 'percentage' ); ?>><?php esc_html_e( 'Desconto percentual', 'dps-loyalty-addon' ); ?></option>
                <option value="fixed" <?php selected( $campaign_type, 'fixed' ); ?>><?php esc_html_e( 'Desconto fixo', 'dps-loyalty-addon' ); ?></option>
                <option value="double_points" <?php selected( $campaign_type, 'double_points' ); ?>><?php esc_html_e( 'Pontos em dobro', 'dps-loyalty-addon' ); ?></option>
            </select>
        </p>
        
        <fieldset style="border: 1px solid #e5e7eb; padding: 16px; margin: 16px 0; border-radius: 4px;">
            <legend style="font-weight: 600; color: #374151; padding: 0 8px;"><strong><?php esc_html_e( 'Critérios de elegibilidade', 'dps-loyalty-addon' ); ?></strong></legend>
            <p>
                <label>
                    <input type="checkbox" name="dps_campaign_eligibility[]" value="inactive" <?php checked( in_array( 'inactive', $eligibility_selection, true ) ); ?> />
                    <?php esc_html_e( 'Clientes sem atendimento há X dias', 'dps-loyalty-addon' ); ?>
                </label>
                <input type="number" name="dps_campaign_inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="0" class="small-text" />
            </p>
            <p>
                <label>
                    <input type="checkbox" name="dps_campaign_eligibility[]" value="points" <?php checked( in_array( 'points', $eligibility_selection, true ) ); ?> />
                    <?php esc_html_e( 'Clientes com mais de N pontos', 'dps-loyalty-addon' ); ?>
                </label>
                <input type="number" name="dps_campaign_points_threshold" value="<?php echo esc_attr( $points_threshold ); ?>" min="0" class="small-text" />
            </p>
        </fieldset>
        
        <fieldset style="border: 1px solid #e5e7eb; padding: 16px; margin: 16px 0; border-radius: 4px;">
            <legend style="font-weight: 600; color: #374151; padding: 0 8px;"><strong><?php esc_html_e( 'Período da campanha', 'dps-loyalty-addon' ); ?></strong></legend>
            <p>
                <label for="dps_campaign_start_date"><strong><?php esc_html_e( 'Início', 'dps-loyalty-addon' ); ?></strong></label>
                <input type="date" id="dps_campaign_start_date" name="dps_campaign_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
            </p>
            <p>
                <label for="dps_campaign_end_date"><strong><?php esc_html_e( 'Fim', 'dps-loyalty-addon' ); ?></strong></label>
                <input type="date" id="dps_campaign_end_date" name="dps_campaign_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
            </p>
        </fieldset>
        <?php
    }

    public function save_campaign_meta( $post_id ) {
        if ( ! isset( $_POST['dps_campaign_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dps_campaign_details_nonce'] ), 'dps_campaign_details' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $campaign_type    = isset( $_POST['dps_campaign_type'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_type'] ) ) : '';
        $eligibility_raw  = isset( $_POST['dps_campaign_eligibility'] ) ? (array) wp_unslash( $_POST['dps_campaign_eligibility'] ) : [];
        $eligibility_safe = array_map( 'sanitize_text_field', $eligibility_raw );
        $inactive_days    = isset( $_POST['dps_campaign_inactive_days'] ) ? absint( $_POST['dps_campaign_inactive_days'] ) : 0;
        $points_threshold = isset( $_POST['dps_campaign_points_threshold'] ) ? absint( $_POST['dps_campaign_points_threshold'] ) : 0;
        $start_date       = isset( $_POST['dps_campaign_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_start_date'] ) ) : '';
        $end_date         = isset( $_POST['dps_campaign_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_end_date'] ) ) : '';

        update_post_meta( $post_id, 'dps_campaign_type', $campaign_type );
        update_post_meta( $post_id, 'dps_campaign_eligibility', $eligibility_safe );
        update_post_meta( $post_id, 'dps_campaign_inactive_days', $inactive_days );
        update_post_meta( $post_id, 'dps_campaign_points_threshold', $points_threshold );
        update_post_meta( $post_id, 'dps_campaign_start_date', $start_date );
        update_post_meta( $post_id, 'dps_campaign_end_date', $end_date );
    }

    public function register_menu() {
        // Submenu dentro do menu principal "DPS by PRObst" (criado pelo plugin base)
        add_submenu_page(
            'desi-pet-shower',
            __( 'Campanhas & Fidelidade', 'dps-loyalty-addon' ),
            __( 'Campanhas & Fidelidade', 'dps-loyalty-addon' ),
            'manage_options',
            'dps-loyalty',
            [ $this, 'render_loyalty_page' ]
        );

        add_submenu_page(
            'desi-pet-shower',
            __( 'Campanhas', 'dps-loyalty-addon' ),
            __( 'Campanhas', 'dps-loyalty-addon' ),
            'manage_options',
            'edit.php?post_type=dps_campaign'
        );
    }

    public function render_loyalty_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings    = get_option( self::OPTION_KEY, [] );
        $brl_per_pt  = isset( $settings['brl_per_point'] ) && $settings['brl_per_point'] > 0 ? (float) $settings['brl_per_point'] : 10.0;
        $selected_id = isset( $_GET['dps_client_id'] ) ? intval( $_GET['dps_client_id'] ) : 0;
        $active_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';

        // Obtém métricas globais
        $metrics = DPS_Loyalty_API::get_global_metrics();
        ?>
        <div class="wrap dps-loyalty-wrap">
            <h1><?php echo esc_html__( 'Campanhas & Fidelidade', 'dps-loyalty-addon' ); ?></h1>

            <!-- Navegação por abas -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'dashboard', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>" 
                   class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Dashboard', 'dps-loyalty-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'referrals', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>" 
                   class="nav-tab <?php echo $active_tab === 'referrals' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Indicações', 'dps-loyalty-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Configurações', 'dps-loyalty-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'clients', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>" 
                   class="nav-tab <?php echo $active_tab === 'clients' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Consulta de Cliente', 'dps-loyalty-addon' ); ?>
                </a>
            </nav>

            <div class="dps-loyalty-content" style="margin-top: 20px;">
                <?php
                switch ( $active_tab ) {
                    case 'referrals':
                        $this->render_referrals_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab( $brl_per_pt );
                        break;
                    case 'clients':
                        $this->render_clients_tab( $selected_id );
                        break;
                    default:
                        $this->render_dashboard_tab( $metrics );
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a aba de dashboard com métricas.
     *
     * @param array $metrics Métricas globais.
     */
    private function render_dashboard_tab( $metrics ) {
        ?>
        <!-- Cards de Métricas -->
        <div class="dps-loyalty-dashboard">
            <div class="dps-loyalty-card">
                <span class="dps-loyalty-card-icon">👥</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( number_format( $metrics['clients_with_points'], 0, ',', '.' ) ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Clientes com Pontos', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card dps-loyalty-card--info">
                <span class="dps-loyalty-card-icon">⭐</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( number_format( $metrics['total_points'], 0, ',', '.' ) ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Pontos em Circulação', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card">
                <span class="dps-loyalty-card-icon">🤝</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( $metrics['referrals_this_month'] ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Indicações Este Mês', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card dps-loyalty-card--success">
                <span class="dps-loyalty-card-icon">✅</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( $metrics['rewarded_this_month'] ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Recompensadas', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card dps-loyalty-card--warning">
                <span class="dps-loyalty-card-icon">💰</span>
                <span class="dps-loyalty-card-value">
                    <?php 
                    if ( class_exists( 'DPS_Money_Helper' ) ) {
                        echo 'R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $metrics['total_credits'] ) );
                    } else {
                        echo 'R$ ' . esc_html( number_format( $metrics['total_credits'] / 100, 2, ',', '.' ) );
                    }
                    ?>
                </span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Créditos em Circulação', 'dps-loyalty-addon' ); ?></span>
            </div>
        </div>

        <hr />

        <h2><?php esc_html_e( 'Rotinas de Campanhas', 'dps-loyalty-addon' ); ?></h2>
        <p><?php esc_html_e( 'Execute uma varredura para identificar clientes elegíveis e registrar ofertas pendentes.', 'dps-loyalty-addon' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'dps_loyalty_run_audit', 'dps_loyalty_run_audit_nonce' ); ?>
            <input type="hidden" name="action" value="dps_loyalty_run_audit" />
            <?php submit_button( __( 'Rodar rotina de elegibilidade', 'dps-loyalty-addon' ), 'primary', 'dps_loyalty_run_audit_btn', false ); ?>
        </form>

        <?php if ( isset( $_GET['audit'] ) && $_GET['audit'] === 'done' ) : ?>
            <div class="notice notice-success" style="margin-top: 16px;">
                <p><?php esc_html_e( 'Rotina de elegibilidade executada com sucesso!', 'dps-loyalty-addon' ); ?></p>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Renderiza a aba de indicações.
     */
    private function render_referrals_tab() {
        $status_filter = isset( $_GET['ref_status'] ) ? sanitize_text_field( $_GET['ref_status'] ) : '';
        $current_page  = isset( $_GET['ref_page'] ) ? max( 1, absint( $_GET['ref_page'] ) ) : 1;

        $referrals_data = DPS_Loyalty_API::get_referrals( [
            'status'   => $status_filter,
            'page'     => $current_page,
            'per_page' => 20,
        ] );

        $referrals = $referrals_data['items'];
        $total_pages = $referrals_data['pages'];
        $total_referrals = $referrals_data['total'];
        
        // URL para exportação CSV
        $export_url = wp_nonce_url(
            add_query_arg( [
                'action' => 'dps_loyalty_export_referrals',
                'status' => $status_filter,
            ], admin_url( 'admin-post.php' ) ),
            'dps_export_referrals'
        );
        ?>
        <div class="dps-referrals-header">
            <h2><?php esc_html_e( 'Indicações', 'dps-loyalty-addon' ); ?></h2>
            <?php if ( $total_referrals > 0 ) : ?>
                <a href="<?php echo esc_url( $export_url ); ?>" class="button">
                    📥 <?php esc_html_e( 'Exportar CSV', 'dps-loyalty-addon' ); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Filtros -->
        <div class="dps-referrals-filters">
            <select id="dps-referrals-status-filter">
                <option value=""><?php esc_html_e( 'Todos os status', 'dps-loyalty-addon' ); ?></option>
                <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pendentes', 'dps-loyalty-addon' ); ?></option>
                <option value="rewarded" <?php selected( $status_filter, 'rewarded' ); ?>><?php esc_html_e( 'Recompensadas', 'dps-loyalty-addon' ); ?></option>
            </select>
            <span class="dps-referrals-count">
                <?php 
                /* translators: %d: number of referrals */
                echo esc_html( sprintf( _n( '%d indicação', '%d indicações', $total_referrals, 'dps-loyalty-addon' ), $total_referrals ) ); 
                ?>
            </span>
        </div>

        <!-- Tabela de Indicações -->
        <div class="dps-referrals-table-wrapper">
            <table class="dps-referrals-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Indicador', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Indicado', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Código', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Data', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Recompensas', 'dps-loyalty-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $referrals ) ) : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                <?php esc_html_e( 'Nenhuma indicação encontrada.', 'dps-loyalty-addon' ); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $referrals as $ref ) : 
                            $referrer = get_post( $ref->referrer_client_id );
                            $referee = get_post( $ref->referee_client_id );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $referrer ? $referrer->post_title : '—' ); ?></td>
                            <td><?php echo esc_html( $referee ? $referee->post_title : '—' ); ?></td>
                            <td><code><?php echo esc_html( $ref->referral_code ); ?></code></td>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $ref->created_at ) ) ); ?></td>
                            <td>
                                <span class="dps-status-badge dps-status-<?php echo esc_attr( $ref->status ); ?>">
                                    <?php echo esc_html( $ref->status === 'rewarded' ? __( 'Recompensada', 'dps-loyalty-addon' ) : __( 'Pendente', 'dps-loyalty-addon' ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $ref->status === 'rewarded' ) : ?>
                                    <?php echo esc_html( $this->format_reward_display( $ref->reward_type_referrer, $ref->reward_value_referrer ) ); ?> / 
                                    <?php echo esc_html( $this->format_reward_display( $ref->reward_type_referee, $ref->reward_value_referee ) ); ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="dps-pagination">
                <?php
                $base_url = add_query_arg( [ 'page' => 'dps-loyalty', 'tab' => 'referrals' ], admin_url( 'admin.php' ) );
                if ( $status_filter ) {
                    $base_url = add_query_arg( 'ref_status', $status_filter, $base_url );
                }

                if ( $current_page > 1 ) :
                    $prev_url = add_query_arg( 'ref_page', $current_page - 1, $base_url );
                ?>
                    <a class="button" href="<?php echo esc_url( $prev_url ); ?>">&laquo; <?php esc_html_e( 'Anterior', 'dps-loyalty-addon' ); ?></a>
                <?php endif; ?>

                <span class="dps-pagination-info">
                    <?php echo esc_html( sprintf( __( 'Página %d de %d', 'dps-loyalty-addon' ), $current_page, $total_pages ) ); ?>
                </span>

                <?php if ( $current_page < $total_pages ) :
                    $next_url = add_query_arg( 'ref_page', $current_page + 1, $base_url );
                ?>
                    <a class="button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Próxima', 'dps-loyalty-addon' ); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Formata exibição de recompensa.
     *
     * @param string $type  Tipo de recompensa.
     * @param mixed  $value Valor da recompensa.
     * @return string Texto formatado.
     */
    private function format_reward_display( $type, $value ) {
        switch ( $type ) {
            case 'points':
                return sprintf( '%d pts', (int) $value );
            case 'fixed':
                if ( class_exists( 'DPS_Money_Helper' ) ) {
                    return 'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) $value );
                }
                return 'R$ ' . number_format( (int) $value / 100, 2, ',', '.' );
            case 'percent':
                return $value . '%';
            default:
                return '—';
        }
    }

    /**
     * Renderiza a aba de configurações.
     *
     * @param float $brl_per_pt Valor atual de BRL por ponto.
     */
    private function render_settings_tab( $brl_per_pt ) {
        ?>
        <form method="post" action="options.php" class="dps-loyalty-settings-form">
            <?php
            settings_fields( 'dps_loyalty_settings_group' );
            do_settings_sections( 'dps_loyalty_settings_page' );
            ?>
            <fieldset>
                <legend><?php esc_html_e( 'Programa de Pontos', 'dps-loyalty-addon' ); ?></legend>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Regra de pontos', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e( '1 ponto a cada', 'dps-loyalty-addon' ); ?>
                                <input type="number" step="0.01" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[brl_per_point]" value="<?php echo esc_attr( $brl_per_pt ); ?>" />
                                <?php esc_html_e( 'reais faturados', 'dps-loyalty-addon' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </fieldset>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Renderiza a aba de consulta de clientes.
     *
     * @param int $selected_id ID do cliente selecionado.
     */
    private function render_clients_tab( $selected_id ) {
        // Implementa paginação para melhor performance com muitos clientes.
        $per_page = 100;
        $paged    = isset( $_GET['loyalty_page'] ) ? max( 1, absint( $_GET['loyalty_page'] ) ) : 1;

        $clients_query = new WP_Query( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $clients = $clients_query->posts;
        $total_pages = $clients_query->max_num_pages;

        $logs = $selected_id ? dps_loyalty_get_logs( $selected_id ) : [];
        ?>
        <h2><?php esc_html_e( 'Resumo de Fidelidade', 'dps-loyalty-addon' ); ?></h2>
        <form method="get">
            <input type="hidden" name="page" value="dps-loyalty" />
            <input type="hidden" name="tab" value="clients" />
            <label for="dps_client_id"><?php esc_html_e( 'Selecionar cliente', 'dps-loyalty-addon' ); ?></label>
            <select id="dps_client_id" name="dps_client_id">
                <option value="0"><?php esc_html_e( 'Selecione um cliente', 'dps-loyalty-addon' ); ?></option>
                <?php foreach ( $clients as $client ) : ?>
                    <option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $selected_id, $client->ID ); ?>><?php echo esc_html( $client->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( __( 'Filtrar', 'dps-loyalty-addon' ), 'secondary', '', false ); ?>
        </form>

        <?php
        // Renderiza paginação de clientes se houver múltiplas páginas.
        if ( $total_pages > 1 ) {
            echo '<div class="dps-pagination" style="margin: 10px 0;">';
            $prev_page = $paged > 1 ? $paged - 1 : 0;
            $next_page = $paged < $total_pages ? $paged + 1 : 0;

            // Preserva o filtro de cliente selecionado nos links de paginação.
            $base_url = admin_url( 'admin.php?page=dps-loyalty&tab=clients' );
            if ( $selected_id ) {
                $base_url = add_query_arg( 'dps_client_id', $selected_id, $base_url );
            }

            if ( $prev_page ) {
                printf(
                    '<a class="button" href="%s">&laquo; %s</a> ',
                    esc_url( add_query_arg( 'loyalty_page', $prev_page, $base_url ) ),
                    esc_html__( 'Anterior', 'dps-loyalty-addon' )
                );
            }

            printf(
                '<span class="dps-pagination-info">%s</span>',
                esc_html( sprintf( __( 'Página %d de %d', 'dps-loyalty-addon' ), $paged, $total_pages ) )
            );

            if ( $next_page ) {
                printf(
                    ' <a class="button" href="%s">%s &raquo;</a>',
                    esc_url( add_query_arg( 'loyalty_page', $next_page, $base_url ) ),
                    esc_html__( 'Próxima', 'dps-loyalty-addon' )
                );
            }
            echo '</div>';
        }
        ?>

        <?php if ( $selected_id ) : 
            $client_points = dps_loyalty_get_points( $selected_id );
            $client_credit = dps_loyalty_get_credit( $selected_id );
            $referral_code = dps_loyalty_get_referral_code( $selected_id );
            $referral_stats = DPS_Loyalty_API::get_referral_stats( $selected_id );
            $tier_info = DPS_Loyalty_API::get_loyalty_tier( $selected_id );
        ?>
            <hr />
            
            <!-- Cards de resumo do cliente -->
            <div class="dps-loyalty-dashboard" style="margin-top: 20px;">
                <div class="dps-loyalty-card">
                    <span class="dps-loyalty-card-icon"><?php echo esc_html( $tier_info['icon'] ); ?></span>
                    <span class="dps-loyalty-card-value"><?php echo esc_html( $tier_info['label'] ); ?></span>
                    <span class="dps-loyalty-card-label"><?php esc_html_e( 'Nível', 'dps-loyalty-addon' ); ?></span>
                </div>
                <div class="dps-loyalty-card dps-loyalty-card--info">
                    <span class="dps-loyalty-card-icon">⭐</span>
                    <span class="dps-loyalty-card-value"><?php echo esc_html( number_format( $client_points, 0, ',', '.' ) ); ?></span>
                    <span class="dps-loyalty-card-label"><?php esc_html_e( 'Pontos', 'dps-loyalty-addon' ); ?></span>
                </div>
                <div class="dps-loyalty-card dps-loyalty-card--warning">
                    <span class="dps-loyalty-card-icon">💰</span>
                    <span class="dps-loyalty-card-value">
                        <?php 
                        if ( class_exists( 'DPS_Money_Helper' ) ) {
                            echo 'R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $client_credit ) );
                        } else {
                            echo 'R$ ' . esc_html( number_format( $client_credit / 100, 2, ',', '.' ) );
                        }
                        ?>
                    </span>
                    <span class="dps-loyalty-card-label"><?php esc_html_e( 'Crédito', 'dps-loyalty-addon' ); ?></span>
                </div>
                <div class="dps-loyalty-card dps-loyalty-card--success">
                    <span class="dps-loyalty-card-icon">🤝</span>
                    <span class="dps-loyalty-card-value"><?php echo esc_html( $referral_stats['rewarded'] ); ?>/<?php echo esc_html( $referral_stats['total'] ); ?></span>
                    <span class="dps-loyalty-card-label"><?php esc_html_e( 'Indicações', 'dps-loyalty-addon' ); ?></span>
                </div>
            </div>

            <!-- Barra de progresso para próximo nível -->
            <?php if ( $tier_info['next_tier'] ) : ?>
            <div class="dps-points-progress-wrapper">
                <p><strong><?php esc_html_e( 'Progresso para o próximo nível:', 'dps-loyalty-addon' ); ?></strong></p>
                <div class="dps-tier-progress">
                    <div class="dps-tier-current">
                        <span class="dps-tier-icon"><?php echo esc_html( $tier_info['icon'] ); ?></span>
                        <span class="dps-tier-name"><?php echo esc_html( $tier_info['label'] ); ?></span>
                    </div>
                    <div class="dps-progress-container" style="flex: 1;">
                        <div class="dps-points-progress">
                            <div class="dps-points-progress-fill" style="width: <?php echo esc_attr( $tier_info['progress'] ); ?>%;"></div>
                            <span class="dps-points-progress-text">
                                <?php echo esc_html( number_format( $tier_info['points'], 0, ',', '.' ) ); ?> / 
                                <?php echo esc_html( number_format( $tier_info['next_points'], 0, ',', '.' ) ); ?>
                            </span>
                        </div>
                    </div>
                    <div class="dps-tier-next">
                        <span class="dps-tier-icon"><?php 
                            $tiers = DPS_Loyalty_API::get_default_tiers();
                            echo esc_html( $tiers[ $tier_info['next_tier'] ]['icon'] ?? '🏆' );
                        ?></span>
                        <span class="dps-tier-name"><?php echo esc_html( $tier_info['next_label'] ); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Código de indicação -->
            <div class="dps-referral-section">
                <p><strong><?php esc_html_e( 'Código de indicação:', 'dps-loyalty-addon' ); ?></strong></p>
                <div class="dps-referral-code-box">
                    <div class="dps-referral-code">
                        <code><?php echo esc_html( $referral_code ); ?></code>
                        <button type="button" class="button dps-copy-referral-code" data-code="<?php echo esc_attr( $referral_code ); ?>">
                            📋 <?php esc_html_e( 'Copiar', 'dps-loyalty-addon' ); ?>
                        </button>
                    </div>
                    <?php 
                    // Gerar link de indicação e botão WhatsApp
                    $referral_url = DPS_Loyalty_API::get_referral_url( $selected_id );
                    $share_message = sprintf(
                        /* translators: 1: referral code, 2: referral URL */
                        __( 'Use meu código %1$s e ganhe benefícios no seu primeiro atendimento! Cadastre-se aqui: %2$s', 'dps-loyalty-addon' ),
                        $referral_code,
                        $referral_url
                    );
                    $whatsapp_url = 'https://wa.me/?text=' . rawurlencode( $share_message );
                    ?>
                    <div class="dps-referral-actions">
                        <input type="text" value="<?php echo esc_attr( $referral_url ); ?>" readonly class="dps-referral-link-input" />
                        <button type="button" class="button dps-copy-referral-link" data-link="<?php echo esc_attr( $referral_url ); ?>">
                            🔗 <?php esc_html_e( 'Copiar Link', 'dps-loyalty-addon' ); ?>
                        </button>
                        <a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" class="button dps-btn-whatsapp">
                            📲 <?php esc_html_e( 'Compartilhar via WhatsApp', 'dps-loyalty-addon' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $logs ) ) : ?>
                <h3><?php esc_html_e( 'Histórico recente', 'dps-loyalty-addon' ); ?></h3>
                <ul class="dps-points-history">
                    <?php foreach ( $logs as $entry ) : 
                        $context_label = $this->get_context_label( $entry['context'] );
                        $formatted_date = date_i18n( 'd/m/Y H:i', strtotime( $entry['date'] ) );
                    ?>
                        <li class="dps-points-history-item">
                            <div class="dps-points-history-info">
                                <span class="dps-points-history-context"><?php echo esc_html( $context_label ); ?></span>
                                <span class="dps-points-history-date"><?php echo esc_html( $formatted_date ); ?></span>
                            </div>
                            <span class="dps-points-history-value <?php echo esc_attr( $entry['action'] ); ?>">
                                <?php echo esc_html( $entry['points'] ); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e( 'Nenhum histórico disponível.', 'dps-loyalty-addon' ); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Translates context codes to human-readable labels.
     *
     * @since 1.2.0
     *
     * @param string $context The context code.
     * @return string The translated label.
     */
    private function get_context_label( $context ) {
        $labels = [
            'appointment_payment' => __( 'Pagamento de atendimento', 'dps-loyalty-addon' ),
            'referral_reward'     => __( 'Recompensa de indicação', 'dps-loyalty-addon' ),
            'credit_add'          => __( 'Crédito adicionado', 'dps-loyalty-addon' ),
            'credit_use'          => __( 'Crédito utilizado', 'dps-loyalty-addon' ),
            'manual_adjustment'   => __( 'Ajuste manual', 'dps-loyalty-addon' ),
            'points_expired'      => __( 'Pontos expirados', 'dps-loyalty-addon' ),
            'redeem'              => __( 'Resgate de pontos', 'dps-loyalty-addon' ),
        ];

        return isset( $labels[ $context ] ) ? $labels[ $context ] : $context;
    }

    public function handle_campaign_audit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Acesso negado.', 'dps-loyalty-addon' ) );
        }

        if ( ! isset( $_POST['dps_loyalty_run_audit_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dps_loyalty_run_audit_nonce'] ), 'dps_loyalty_run_audit' ) ) {
            wp_die( __( 'Nonce inválido.', 'dps-loyalty-addon' ) );
        }

        // Limite de campanhas processadas em uma única execução.
        $campaigns = get_posts( [
            'post_type'      => 'dps_campaign',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ] );

        foreach ( $campaigns as $campaign ) {
            $eligible_clients = $this->find_eligible_clients_for_campaign( $campaign->ID );
            update_post_meta( $campaign->ID, 'dps_campaign_pending_offers', $eligible_clients );
            update_post_meta( $campaign->ID, 'dps_campaign_last_audit', current_time( 'mysql' ) );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'dps-loyalty', 'audit' => 'done' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handles the CSV export of referrals.
     *
     * @since 1.2.0
     */
    public function handle_export_referrals() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Acesso negado.', 'dps-loyalty-addon' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'dps_export_referrals' ) ) {
            wp_die( __( 'Nonce inválido.', 'dps-loyalty-addon' ) );
        }

        $status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        
        $csv = DPS_Loyalty_API::export_referrals_csv( [ 'status' => $status ] );
        
        $filename = 'indicacoes-' . gmdate( 'Y-m-d' ) . '.csv';
        
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        echo $csv;
        exit;
    }

    private function find_eligible_clients_for_campaign( $campaign_id ) {
        $eligibility      = get_post_meta( $campaign_id, 'dps_campaign_eligibility', true );
        $inactive_days    = absint( get_post_meta( $campaign_id, 'dps_campaign_inactive_days', true ) );
        $points_threshold = absint( get_post_meta( $campaign_id, 'dps_campaign_points_threshold', true ) );
        $eligible_clients = [];

        // Limite de clientes processados por campanha (500 clientes).
        // Para bases maiores, considerar processamento em background via cron job.
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        ] );

        foreach ( $clients as $client_id ) {
            $passes_inactive = ! empty( $eligibility ) && in_array( 'inactive', (array) $eligibility, true )
                ? $this->is_client_inactive_for_days( $client_id, $inactive_days )
                : false;
            $passes_points   = ! empty( $eligibility ) && in_array( 'points', (array) $eligibility, true )
                ? dps_loyalty_get_points( $client_id ) >= $points_threshold
                : false;

            if ( ( $passes_inactive || $passes_points ) && ! in_array( $client_id, $eligible_clients, true ) ) {
                $eligible_clients[] = $client_id;
            }
        }

        return $eligible_clients;
    }

    private function is_client_inactive_for_days( $client_id, $days ) {
        if ( $days <= 0 ) {
            return false;
        }

        $last_date = $this->get_last_appointment_date_for_client( $client_id );
        if ( ! $last_date ) {
            return true;
        }

        $interval = ( time() - strtotime( $last_date ) ) / DAY_IN_SECONDS;
        return $interval >= $days;
    }

    private function get_last_appointment_date_for_client( $client_id ) {
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => 'appointment_date',
            'meta_query'     => [
                [
                    'key'   => 'appointment_client_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        if ( empty( $appointments ) ) {
            return '';
        }

        return get_post_meta( $appointments[0]->ID, 'appointment_date', true );
    }

    public function register_settings() {
        register_setting( 'dps_loyalty_settings_group', self::OPTION_KEY, [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'dps_loyalty_referrals_section',
            __( 'Indique e Ganhe', 'dps-loyalty-addon' ),
            [ $this, 'render_referrals_section_intro' ],
            'dps_loyalty_settings_page'
        );

        add_settings_field(
            'dps_loyalty_referrals_enabled',
            __( 'Ativar programa', 'dps-loyalty-addon' ),
            [ $this, 'render_referrals_enabled_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referrer_reward',
            __( 'Recompensa do indicador', 'dps-loyalty-addon' ),
            [ $this, 'render_referrer_reward_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referee_reward',
            __( 'Recompensa do indicado', 'dps-loyalty-addon' ),
            [ $this, 'render_referee_reward_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referrals_rules',
            __( 'Regras gerais', 'dps-loyalty-addon' ),
            [ $this, 'render_referrals_rules_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );
    }

    public function sanitize_settings( $input ) {
        $output                               = [];
        $output['brl_per_point']              = isset( $input['brl_per_point'] ) ? (float) $input['brl_per_point'] : 10.0;
        $output['referrals_enabled']          = ! empty( $input['referrals_enabled'] ) ? 1 : 0;
        $output['referrer_reward_type']       = isset( $input['referrer_reward_type'] ) ? sanitize_text_field( $input['referrer_reward_type'] ) : 'none';
        $output['referrer_reward_value']      = isset( $input['referrer_reward_value'] ) ? $this->sanitize_reward_value( $input['referrer_reward_value'], $output['referrer_reward_type'] ) : 0;
        $output['referee_reward_type']        = isset( $input['referee_reward_type'] ) ? sanitize_text_field( $input['referee_reward_type'] ) : 'none';
        $output['referee_reward_value']       = isset( $input['referee_reward_value'] ) ? $this->sanitize_reward_value( $input['referee_reward_value'], $output['referee_reward_type'] ) : 0;
        $output['referrals_minimum_amount']   = isset( $input['referrals_minimum_amount'] ) ? dps_loyalty_parse_money_br( $input['referrals_minimum_amount'] ) : 0;
        $output['referrals_max_per_referrer'] = isset( $input['referrals_max_per_referrer'] ) ? absint( $input['referrals_max_per_referrer'] ) : 0;
        $output['referrals_first_purchase']   = ! empty( $input['referrals_first_purchase'] ) ? 1 : 0;

        if ( $output['brl_per_point'] <= 0 ) {
            $output['brl_per_point'] = 10.0;
        }
        return $output;
    }

    public function render_referrals_section_intro() {
        echo '<p>' . esc_html__( 'Configure as regras do programa de indicações, incluindo recompensas e limites.', 'dps-loyalty-addon' ) . '</p>';
    }

    public function render_referrals_enabled_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $enabled  = ! empty( $settings['referrals_enabled'] );
        echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[referrals_enabled]" value="1" ' . checked( $enabled, true, false ) . ' /> ' . esc_html__( 'Ativar programa Indique e Ganhe', 'dps-loyalty-addon' ) . '</label>';
    }

    public function render_referrer_reward_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $type     = isset( $settings['referrer_reward_type'] ) ? $settings['referrer_reward_type'] : 'none';
        $value    = isset( $settings['referrer_reward_value'] ) ? $settings['referrer_reward_value'] : 0;
        $this->render_reward_selector( 'referrer', $type, $value );
    }

    public function render_referee_reward_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $type     = isset( $settings['referee_reward_type'] ) ? $settings['referee_reward_type'] : 'none';
        $value    = isset( $settings['referee_reward_value'] ) ? $settings['referee_reward_value'] : 0;
        $this->render_reward_selector( 'referee', $type, $value );
    }

    public function render_referrals_rules_field() {
        $settings       = get_option( self::OPTION_KEY, [] );
        $minimum_amount = isset( $settings['referrals_minimum_amount'] ) ? (int) $settings['referrals_minimum_amount'] : 0;
        $max_referrals  = isset( $settings['referrals_max_per_referrer'] ) ? absint( $settings['referrals_max_per_referrer'] ) : 0;
        $first_purchase = ! empty( $settings['referrals_first_purchase'] );
        ?>
        <p>
            <label>
                <?php esc_html_e( 'Valor mínimo do primeiro atendimento para liberar recompensa (R$)', 'dps-loyalty-addon' ); ?><br />
                <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_minimum_amount]" value="<?php echo esc_attr( DPS_Money_Helper::format_to_brazilian( $minimum_amount ) ); ?>" />
            </label>
        </p>
        <p>
            <label>
                <?php esc_html_e( 'Máximo de indicações recompensadas por cliente (0 para ilimitado)', 'dps-loyalty-addon' ); ?><br />
                <input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_max_per_referrer]" value="<?php echo esc_attr( $max_referrals ); ?>" />
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_first_purchase]" value="1" <?php checked( $first_purchase ); ?> />
                <?php esc_html_e( 'Somente a primeira compra conta', 'dps-loyalty-addon' ); ?>
            </label>
        </p>
        <?php
    }

    private function render_reward_selector( $role, $type, $value ) {
        $type_key  = self::OPTION_KEY . '[' . $role . '_reward_type]';
        $value_key = self::OPTION_KEY . '[' . $role . '_reward_value]';
        ?>
        <fieldset>
            <label for="<?php echo esc_attr( $type_key ); ?>">
                <select id="<?php echo esc_attr( $type_key ); ?>" name="<?php echo esc_attr( $type_key ); ?>">
                    <option value="none" <?php selected( $type, 'none' ); ?>><?php esc_html_e( 'Sem recompensa', 'dps-loyalty-addon' ); ?></option>
                    <option value="points" <?php selected( $type, 'points' ); ?>><?php esc_html_e( 'Pontos de fidelidade', 'dps-loyalty-addon' ); ?></option>
                    <option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Crédito fixo (R$)', 'dps-loyalty-addon' ); ?></option>
                    <option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Crédito percentual', 'dps-loyalty-addon' ); ?></option>
                </select>
            </label>
            <input type="text" name="<?php echo esc_attr( $value_key ); ?>" value="<?php echo esc_attr( $this->format_reward_value( $value, $type ) ); ?>" placeholder="<?php esc_attr_e( 'Valor', 'dps-loyalty-addon' ); ?>" />
        </fieldset>
        <?php
    }

    private function sanitize_reward_value( $value, $type ) {
        if ( 'points' === $type ) {
            return absint( $value );
        }

        if ( 'fixed' === $type ) {
            return dps_loyalty_parse_money_br( $value );
        }

        if ( 'percent' === $type ) {
            return (float) $value;
        }

        return 0;
    }

    private function format_reward_value( $value, $type ) {
        if ( 'fixed' === $type ) {
            return DPS_Money_Helper::format_to_brazilian( (int) $value );
        }

        return $value;
    }

    public function maybe_award_points_on_status_change( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( 'appointment_status' !== $meta_key || 'finalizado_pago' !== $meta_value ) {
            return;
        }

        // Verifica se o post existe antes de chamar get_post_type para evitar erro de map_meta_cap
        $post = get_post( $object_id );
        if ( ! $post || $post->post_type !== 'dps_agendamento' ) {
            return;
        }

        if ( get_post_meta( $object_id, 'dps_loyalty_points_awarded', true ) ) {
            return;
        }

        $client_id = (int) get_post_meta( $object_id, 'appointment_client_id', true );
        if ( ! $client_id ) {
            return;
        }

        $total_value = (float) get_post_meta( $object_id, 'appointment_total_value', true );
        if ( $total_value <= 0 ) {
            $total_value = $this->get_transaction_total_for_appointment( $object_id );
        }

        $points = $this->calculate_points_from_value( $total_value, $client_id );
        if ( $points > 0 ) {
            dps_loyalty_add_points( $client_id, $points, 'appointment_payment' );
            update_post_meta( $object_id, 'dps_loyalty_points_awarded', 1 );

            /**
             * Fires after loyalty points are awarded for an appointment.
             *
             * @since 1.2.0
             * @param int   $client_id    The client ID.
             * @param int   $points       Points awarded.
             * @param int   $object_id    The appointment ID.
             * @param float $total_value  The appointment total value.
             */
            do_action( 'dps_loyalty_points_awarded_appointment', $client_id, $points, $object_id, $total_value );
        }
    }

    /**
     * Calculates loyalty points from a monetary value, applying tier multiplier.
     *
     * @since 1.2.0 Added tier multiplier support.
     *
     * @param float $value     The monetary value to convert.
     * @param int   $client_id Optional. Client ID to apply tier multiplier. Default 0.
     * @return int Number of points.
     */
    private function calculate_points_from_value( $value, $client_id = 0 ) {
        $settings     = get_option( self::OPTION_KEY, [] );
        $brl_per_pt   = isset( $settings['brl_per_point'] ) && $settings['brl_per_point'] > 0 ? (float) $settings['brl_per_point'] : 10.0;
        $points_float = $value > 0 ? floor( $value / $brl_per_pt ) : 0;

        // Apply tier multiplier if client_id is provided
        if ( $client_id > 0 && class_exists( 'DPS_Loyalty_API' ) ) {
            $tier_info = DPS_Loyalty_API::get_loyalty_tier( $client_id );
            $multiplier = isset( $tier_info['multiplier'] ) ? (float) $tier_info['multiplier'] : 1.0;
            
            if ( $multiplier > 1.0 ) {
                $base_points = (int) $points_float;
                $points_float = floor( $points_float * $multiplier );
                
                // Log the bonus points separately for transparency
                $bonus = (int) $points_float - $base_points;
                if ( $bonus > 0 ) {
                    /**
                     * Fires when bonus points are applied due to tier multiplier.
                     *
                     * @since 1.2.0
                     * @param int   $client_id   The client ID.
                     * @param int   $bonus       Bonus points from multiplier.
                     * @param float $multiplier  The tier multiplier applied.
                     */
                    do_action( 'dps_loyalty_tier_bonus_applied', $client_id, $bonus, $multiplier );
                }
            }
        }

        return (int) $points_float;
    }

    private function get_transaction_total_for_appointment( $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $total = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$table} WHERE agendamento_id = %d AND status = %s", $appointment_id, 'pago' ) );
        return $total ? (float) $total : 0.0;
    }
}

class DPS_Loyalty_Referrals {

    const DB_VERSION = '1.0.0';

    private static $instance = null;

    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dps_referrals';

        add_action( 'init', [ $this, 'maybe_create_table' ] );
        add_action( 'save_post_dps_cliente', [ $this, 'ensure_referral_code' ], 10, 3 );
        add_action( 'dps_registration_after_fields', [ $this, 'render_registration_field' ] );
        add_action( 'dps_registration_after_client_created', [ $this, 'maybe_register_referral' ], 10, 4 );
        add_action( 'dps_finance_booking_paid', [ $this, 'handle_booking_paid' ], 10, 3 );
    }

    public static function install() {
        self::get_instance()->create_table();
    }

    public function maybe_create_table() {
        $installed = get_option( 'dps_referrals_db_version', '' );
        if ( self::DB_VERSION !== $installed ) {
            $this->create_table();
        }
    }

    private function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_client_id BIGINT(20) unsigned NOT NULL,
            referee_client_id BIGINT(20) unsigned NULL,
            referral_code VARCHAR(50) NOT NULL,
            first_booking_id BIGINT(20) unsigned NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            reward_type_referrer VARCHAR(20) NULL,
            reward_value_referrer DECIMAL(12,2) NULL,
            reward_type_referee VARCHAR(20) NULL,
            reward_value_referee DECIMAL(12,2) NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY referrer_idx (referrer_client_id),
            KEY referee_idx (referee_client_id),
            KEY code_idx (referral_code)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'dps_referrals_db_version', self::DB_VERSION );
    }

    public function ensure_referral_code( $post_id, $post, $update ) {
        if ( $update ) {
            return;
        }

        dps_loyalty_get_referral_code( $post_id );
    }

    public function render_registration_field() {
        $referral_param = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
        ?>
        <p class="dps-referral-field">
            <label><?php esc_html_e( 'Código de indicação (opcional)', 'dps-loyalty-addon' ); ?><br />
                <input type="text" name="dps_referral_code" value="<?php echo esc_attr( $referral_param ); ?>" maxlength="20" />
            </label>
        </p>
        <?php
    }

    public function maybe_register_referral( $referral_code, $new_client_id, $client_email, $client_phone ) {
        if ( ! $referral_code || ! $new_client_id ) {
            return;
        }

        $settings = dps_referrals_get_settings();
        if ( empty( $settings['referrals_enabled'] ) ) {
            return;
        }

        $referrer_id = $this->get_client_id_by_referral_code( $referral_code );

        if ( ! $referrer_id || $referrer_id === $new_client_id ) {
            return;
        }

        if ( $this->is_existing_client_contact( $client_email, $client_phone ) ) {
            return;
        }

        dps_referrals_create( [
            'referrer_client_id' => $referrer_id,
            'referee_client_id'  => $new_client_id,
            'referral_code'      => $referral_code,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql' ),
        ] );
    }

    public function handle_booking_paid( $appointment_id, $client_id, $amount_in_cents ) {
        $settings = dps_referrals_get_settings();
        if ( empty( $settings['referrals_enabled'] ) ) {
            return;
        }

        $pending = dps_referrals_find_pending_by_referee( $client_id );
        if ( ! $pending ) {
            return;
        }

        if ( (int) $pending->referrer_client_id === (int) $client_id ) {
            error_log( 'DPS Referrals: tentativa de autopromoção ignorada para cliente ' . $client_id );
            return;
        }

        if ( $settings['referrals_minimum_amount'] > 0 && $amount_in_cents < (int) $settings['referrals_minimum_amount'] ) {
            return;
        }

        if ( ! empty( $settings['referrals_first_purchase'] ) && $this->client_has_previous_paid_booking( $client_id, $appointment_id ) ) {
            return;
        }

        if ( $this->has_referrer_reached_limit( $pending->referrer_client_id, $settings['referrals_max_per_referrer'] ) ) {
            return;
        }

        $rewards_applied = $this->apply_rewards( $pending, $amount_in_cents );

        dps_referrals_mark_rewarded(
            $pending->id,
            $appointment_id,
            [
                'reward_type_referrer' => $rewards_applied['referrer_type'],
                'reward_value_referrer' => $rewards_applied['referrer_value'],
                'reward_type_referee' => $rewards_applied['referee_type'],
                'reward_value_referee' => $rewards_applied['referee_value'],
            ]
        );
    }

    private function apply_rewards( $referral, $amount_in_cents ) {
        $settings       = dps_referrals_get_settings();
        $rewards_applied = [
            'referrer_type' => 'none',
            'referrer_value' => 0,
            'referee_type' => 'none',
            'referee_value' => 0,
        ];

        if ( ! empty( $settings['referrer_reward_type'] ) && 'none' !== $settings['referrer_reward_type'] ) {
            $rewards_applied['referrer_type']  = $settings['referrer_reward_type'];
            $rewards_applied['referrer_value'] = $this->apply_single_reward( $referral->referrer_client_id, $settings['referrer_reward_type'], $settings['referrer_reward_value'], $amount_in_cents );
        }

        if ( ! empty( $settings['referee_reward_type'] ) && 'none' !== $settings['referee_reward_type'] ) {
            $rewards_applied['referee_type']  = $settings['referee_reward_type'];
            $rewards_applied['referee_value'] = $this->apply_single_reward( $referral->referee_client_id, $settings['referee_reward_type'], $settings['referee_reward_value'], $amount_in_cents );
        }

        return $rewards_applied;
    }

    private function apply_single_reward( $client_id, $type, $value, $amount_in_cents ) {
        if ( 'points' === $type ) {
            dps_loyalty_add_points( $client_id, (int) $value, 'referral_reward' );
            return (int) $value;
        }

        if ( 'fixed' === $type ) {
            dps_loyalty_add_credit( $client_id, (int) $value, 'referral_reward' );
            return (int) $value;
        }

        if ( 'percent' === $type ) {
            $calculated = (int) floor( (float) $value * $amount_in_cents / 100 );
            if ( $calculated > 0 ) {
                dps_loyalty_add_credit( $client_id, $calculated, 'referral_reward' );
            }
            return $calculated;
        }

        return 0;
    }

    private function client_has_previous_paid_booking( $client_id, $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE cliente_id = %d AND status = %s AND agendamento_id <> %d", $client_id, 'pago', $appointment_id ) );
        return $count > 0;
    }

    private function has_referrer_reached_limit( $referrer_id, $limit ) {
        if ( ! $limit ) {
            return false;
        }

        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE referrer_client_id = %d AND status = %s", $referrer_id, 'rewarded' ) );
        return $count >= $limit;
    }

    private function get_client_id_by_referral_code( $code ) {
        $client = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_referral_code',
                    'value' => $code,
                ],
            ],
        ] );

        if ( empty( $client ) ) {
            return 0;
        }

        return (int) $client[0];
    }

    private function is_existing_client_contact( $email, $phone ) {
        $meta_query = [ 'relation' => 'OR' ];

        if ( $email ) {
            $meta_query[] = [
                'key'     => 'client_email',
                'value'   => $email,
                'compare' => '=',
            ];
        }

        if ( $phone ) {
            $meta_query[] = [
                'key'     => 'client_phone',
                'value'   => $phone,
                'compare' => '=',
            ];
        }

        if ( count( $meta_query ) === 1 ) {
            return false;
        }

        $existing = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ] );

        return ! empty( $existing );
    }
}

if ( ! function_exists( 'dps_loyalty_init' ) ) {
    /**
     * Inicializa o Loyalty Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
     * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
     * dos métodos de registro que usam prioridade padrão (10).
     */
    function dps_loyalty_init() {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new DPS_Loyalty_Addon();
            add_action( 'admin_init', [ $instance, 'register_settings' ] );
            DPS_Loyalty_Referrals::get_instance();
        }

        return $instance;
    }
}

add_action( 'init', 'dps_loyalty_init', 5 );

register_activation_hook( __FILE__, [ 'DPS_Loyalty_Referrals', 'install' ] );

if ( ! function_exists( 'dps_loyalty_add_points' ) ) {
    function dps_loyalty_add_points( $client_id, $points, $context = '' ) {
        $client_id = (int) $client_id;
        $points    = (int) $points;

        if ( $client_id <= 0 || $points <= 0 ) {
            return false;
        }

        $current = dps_loyalty_get_points( $client_id );
        $new     = $current + $points;

        update_post_meta( $client_id, 'dps_loyalty_points', $new );
        dps_loyalty_log_event( $client_id, 'add', $points, $context );

        do_action( 'dps_loyalty_points_added', $client_id, $points, $context );

        return $new;
    }
}

if ( ! function_exists( 'dps_loyalty_get_points' ) ) {
    function dps_loyalty_get_points( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return 0;
        }

        $points = get_post_meta( $client_id, 'dps_loyalty_points', true );
        $points = $points ? (int) $points : 0;

        return max( 0, $points );
    }
}

if ( ! function_exists( 'dps_loyalty_redeem_points' ) ) {
    function dps_loyalty_redeem_points( $client_id, $points, $context = '' ) {
        $client_id = (int) $client_id;
        $points    = (int) $points;

        if ( $client_id <= 0 || $points <= 0 ) {
            return false;
        }

        $current = dps_loyalty_get_points( $client_id );
        if ( $points > $current ) {
            return false;
        }

        $new = $current - $points;
        update_post_meta( $client_id, 'dps_loyalty_points', $new );
        dps_loyalty_log_event( $client_id, 'redeem', $points, $context );

        do_action( 'dps_loyalty_points_redeemed', $client_id, $points, $context );

        return $new;
    }
}

if ( ! function_exists( 'dps_loyalty_log_event' ) ) {
    function dps_loyalty_log_event( $client_id, $action, $points, $context = '' ) {
        $entry = [
            'action'  => $action,
            'points'  => (int) $points,
            'context' => sanitize_text_field( $context ),
            'date'    => current_time( 'mysql' ),
        ];

        add_post_meta( $client_id, 'dps_loyalty_points_log', $entry );
    }
}

if ( ! function_exists( 'dps_loyalty_get_logs' ) ) {
    function dps_loyalty_get_logs( $client_id, $limit = 10 ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return [];
        }

        $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
        if ( empty( $logs ) ) {
            return [];
        }

        $logs = array_reverse( $logs );
        return array_slice( $logs, 0, $limit );
    }
}

if ( ! function_exists( 'dps_loyalty_parse_money_br' ) ) {
    /**
     * Converte valor monetário no formato brasileiro para centavos.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::parse_brazilian_format() instead.
     * @param string $value Valor no formato brasileiro (ex: "1.234,56").
     * @return int Valor em centavos.
     */
    function dps_loyalty_parse_money_br( $value ) {
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::parse_brazilian_format( $value );
        }

        // Fallback se helper não disponível
        $raw = trim( (string) $value );
        if ( '' === $raw ) {
            return 0;
        }

        $normalized = preg_replace( '/[^0-9,.-]/', '', $raw );
        $normalized = str_replace( ' ', '', $normalized );
        if ( strpos( $normalized, ',' ) !== false ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }

        $float = (float) $normalized;
        return (int) round( $float * 100 );
    }
}

if ( ! function_exists( 'dps_format_money_br' ) ) {
    /**
     * Formata um valor em centavos para string no padrão brasileiro.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::format_to_brazilian() instead.
     * @param int $int Valor em centavos.
     * @return string Valor formatado.
     */
    function dps_format_money_br( $int ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::format_to_brazilian()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::format_to_brazilian( $int );
        }
        // Fallback se helper não disponível
        $float = (int) $int / 100;
        return number_format( $float, 2, ',', '.' );
    }
}

if ( ! function_exists( 'dps_loyalty_generate_referral_code' ) ) {
    function dps_loyalty_generate_referral_code( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return '';
        }

        $existing = get_post_meta( $client_id, '_dps_referral_code', true );
        if ( $existing ) {
            return $existing;
        }

        $attempts = 0;
        $code     = '';
        do {
            $attempts++;
            $code = strtoupper( wp_generate_password( 8, false, false ) );
        } while ( $attempts < 5 && dps_referral_code_exists( $code ) );

        update_post_meta( $client_id, '_dps_referral_code', $code );
        return $code;
    }
}

if ( ! function_exists( 'dps_loyalty_get_referral_code' ) ) {
    function dps_loyalty_get_referral_code( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return '';
        }

        $code = get_post_meta( $client_id, '_dps_referral_code', true );
        if ( ! $code ) {
            $code = dps_loyalty_generate_referral_code( $client_id );
        }

        return $code;
    }
}

if ( ! function_exists( 'dps_referral_code_exists' ) ) {
    function dps_referral_code_exists( $code ) {
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_referral_code',
                    'value' => $code,
                ],
            ],
        ] );

        return ! empty( $clients );
    }
}

if ( ! function_exists( 'dps_referrals_create' ) ) {
    function dps_referrals_create( $data ) {
        global $wpdb;

        $defaults = [
            'referrer_client_id' => 0,
            'referee_client_id'  => null,
            'referral_code'      => '',
            'first_booking_id'   => null,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql' ),
            'reward_type_referrer' => null,
            'reward_value_referrer' => null,
            'reward_type_referee' => null,
            'reward_value_referee' => null,
            'meta'               => null,
        ];

        $data = wp_parse_args( $data, $defaults );

        $wpdb->insert(
            $wpdb->prefix . 'dps_referrals',
            $data,
            [ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s' ]
        );

        return $wpdb->insert_id;
    }
}

if ( ! function_exists( 'dps_referrals_find_pending_by_referee' ) ) {
    function dps_referrals_find_pending_by_referee( $referee_client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE referee_client_id = %d AND status = %s ORDER BY created_at ASC LIMIT 1", $referee_client_id, 'pending' ) );
    }
}

if ( ! function_exists( 'dps_referrals_mark_rewarded' ) ) {
    function dps_referrals_mark_rewarded( $referral_id, $first_booking_id, $reward_data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';
        $data  = [
            'status'             => 'rewarded',
            'first_booking_id'   => $first_booking_id,
            'reward_type_referrer' => isset( $reward_data['reward_type_referrer'] ) ? sanitize_text_field( $reward_data['reward_type_referrer'] ) : null,
            'reward_value_referrer' => isset( $reward_data['reward_value_referrer'] ) ? $reward_data['reward_value_referrer'] : null,
            'reward_type_referee' => isset( $reward_data['reward_type_referee'] ) ? sanitize_text_field( $reward_data['reward_type_referee'] ) : null,
            'reward_value_referee' => isset( $reward_data['reward_value_referee'] ) ? $reward_data['reward_value_referee'] : null,
        ];

        $wpdb->update( $table, $data, [ 'id' => $referral_id ], [ '%s', '%d', '%s', '%f', '%s', '%f' ], [ '%d' ] );
    }
}

if ( ! function_exists( 'dps_referrals_get_settings' ) ) {
    function dps_referrals_get_settings() {
        $settings = get_option( DPS_Loyalty_Addon::OPTION_KEY, [] );
        $defaults = [
            'referrals_enabled'          => 0,
            'referrer_reward_type'       => 'none',
            'referrer_reward_value'      => 0,
            'referee_reward_type'        => 'none',
            'referee_reward_value'       => 0,
            'referrals_minimum_amount'   => 0,
            'referrals_max_per_referrer' => 0,
            'referrals_first_purchase'   => 0,
        ];

        return wp_parse_args( $settings, $defaults );
    }
}

if ( ! function_exists( 'dps_referrals_register_signup' ) ) {
    function dps_referrals_register_signup( $referral_code, $new_client_id, $client_email = '', $client_phone = '' ) {
        $instance = DPS_Loyalty_Referrals::get_instance();
        $instance->maybe_register_referral( $referral_code, $new_client_id, $client_email, $client_phone );
    }
}

if ( ! function_exists( 'dps_loyalty_add_credit' ) ) {
    function dps_loyalty_add_credit( $client_id, $amount_in_cents, $context = '' ) {
        $client_id        = (int) $client_id;
        $amount_in_cents  = (int) $amount_in_cents;

        if ( $client_id <= 0 || $amount_in_cents <= 0 ) {
            return 0;
        }

        $current = dps_loyalty_get_credit( $client_id );
        $new     = $current + $amount_in_cents;
        update_post_meta( $client_id, '_dps_credit_balance', $new );
        dps_loyalty_log_event( $client_id, 'credit_add', $amount_in_cents, $context );
        return $new;
    }
}

if ( ! function_exists( 'dps_loyalty_get_credit' ) ) {
    function dps_loyalty_get_credit( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return 0;
        }

        $balance = get_post_meta( $client_id, '_dps_credit_balance', true );
        return $balance ? (int) $balance : 0;
    }
}

if ( ! function_exists( 'dps_loyalty_use_credit' ) ) {
    function dps_loyalty_use_credit( $client_id, $amount_in_cents, $context = '' ) {
        $client_id       = (int) $client_id;
        $amount_in_cents = (int) $amount_in_cents;

        if ( $client_id <= 0 || $amount_in_cents <= 0 ) {
            return 0;
        }

        $current = dps_loyalty_get_credit( $client_id );
        $amount  = min( $current, $amount_in_cents );
        $new     = $current - $amount;
        update_post_meta( $client_id, '_dps_credit_balance', $new );
        dps_loyalty_log_event( $client_id, 'credit_use', $amount, $context );
        return $amount;
    }
}
