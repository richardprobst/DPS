<?php
/**
 * Plugin Name:       DPS by PRObst – Campanhas & Fidelidade
 * Plugin URI:        https://www.probst.pro
 * Description:       Programa de fidelidade e campanhas promocionais. Fidelize seus clientes com pontos e benefícios exclusivos.
 * Version:           1.5.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-loyalty-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'DPS_LOYALTY_VERSION', '1.5.0' );
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

// Carrega helpers e APIs públicas
require_once DPS_LOYALTY_DIR . 'includes/class-dps-loyalty-api.php';
require_once DPS_LOYALTY_DIR . 'includes/class-dps-loyalty-achievements.php';
require_once DPS_LOYALTY_DIR . 'includes/class-dps-loyalty-rest.php';

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

        add_action( 'dps_loyalty_points_added', [ $this, 'maybe_notify_points_added' ], 20, 3 );
        add_action( 'dps_loyalty_tier_bonus_applied', [ $this, 'maybe_notify_referral_bonus' ], 20, 3 );

        add_action( 'init', [ $this, 'maybe_schedule_crons' ] );
        add_action( 'dps_loyalty_expire_points_daily', [ $this, 'handle_points_expiration' ] );
        add_action( 'dps_loyalty_expiration_notices_daily', [ $this, 'handle_expiration_notices' ] );
        
        // AJAX para busca de clientes (autocomplete).
        add_action( 'wp_ajax_dps_loyalty_search_clients', [ $this, 'ajax_search_clients' ] );
        
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
        // Cast para string para compatibilidade com PHP 8.4+
        $hook = (string) $hook;

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

        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '4.4.1',
            true
        );

        wp_enqueue_style(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/css/loyalty-addon.css',
            [],
            DPS_LOYALTY_VERSION
        );

        wp_enqueue_script(
            'dps-loyalty-addon',
            DPS_LOYALTY_URL . 'assets/js/loyalty-addon.js',
            [ 'jquery', 'chartjs' ],
            DPS_LOYALTY_VERSION,
            true
        );

        // Passa dados para o JS (nonce, URL do AJAX).
        wp_localize_script( 'dps-loyalty-addon', 'dpsLoyaltyData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dps_loyalty_clients_nonce' ),
            'i18n'    => [
                'searchPlaceholder' => __( 'Digite para buscar cliente...', 'dps-loyalty-addon' ),
                'noResults'         => __( 'Nenhum cliente encontrado.', 'dps-loyalty-addon' ),
                'searching'         => __( 'Buscando...', 'dps-loyalty-addon' ),
            ],
        ] );
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

    /**
     * Handler AJAX para busca de clientes (autocomplete).
     *
     * @since 1.3.0
     */
    public function ajax_search_clients() {
        // Verifica permissão.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-loyalty-addon' ) ], 403 );
        }

        // Verifica nonce.
        if ( ! check_ajax_referer( 'dps_loyalty_clients_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce inválido.', 'dps-loyalty-addon' ) ], 403 );
        }

        $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( [] );
        }

        // Argumentos base para busca de clientes.
        $base_args = [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 20,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ];

        // Busca por título (nome).
        $title_args = $base_args;
        $title_args['s'] = $search;
        $clients = get_posts( $title_args );

        // Se não encontrou por título, tenta por telefone/email.
        if ( empty( $clients ) ) {
            $meta_args = $base_args;
            $meta_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'client_phone',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'client_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ];
            $clients = get_posts( $meta_args );
        }

        $results = [];
        foreach ( $clients as $client ) {
            $phone = get_post_meta( $client->ID, 'client_phone', true );
            $points = dps_loyalty_get_points( $client->ID );
            
            $results[] = [
                'id'     => $client->ID,
                'text'   => $client->post_title,
                'phone'  => $phone ? $phone : '',
                'points' => $points,
            ];
        }

        wp_send_json_success( $results );
    }

    /**
     * Formata o saldo de créditos de um cliente para exibição.
     *
     * Padroniza a leitura e formatação de créditos em todos os pontos do add-on:
     * - Garante que valores negativos sejam tratados como zero.
     * - Formata usando o DPS_Money_Helper quando disponível.
     * - Retorna string pronta para exibição (ex: "R$ 10,00").
     *
     * @since 1.3.0
     *
     * @param int $client_id ID do cliente.
     * @return string Saldo formatado (ex: "R$ 0,00" ou "Sem créditos").
     */
    private function get_credit_for_display( $client_id ) {
        $credit_cents = (int) get_post_meta( $client_id, '_dps_credit_balance', true );
        
        // Garante que não haja valores negativos.
        if ( $credit_cents < 0 ) {
            $credit_cents = 0;
        }

        // Formata usando helper ou fallback.
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return 'R$ ' . DPS_Money_Helper::format_to_brazilian( $credit_cents );
        }

        // Fallback: formata manualmente.
        return 'R$ ' . number_format( $credit_cents / 100, 2, ',', '.' );
    }

    /**
     * Formata o saldo de créditos para exibição nas métricas globais.
     *
     * @since 1.3.0
     *
     * @param int $total_credits Total de créditos em centavos.
     * @return string Saldo formatado.
     */
    private function format_credits_display( $total_credits ) {
        $credits = (int) $total_credits;
        
        // Garante que não haja valores negativos.
        if ( $credits < 0 ) {
            $credits = 0;
        }

        // Formata usando helper ou fallback.
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return 'R$ ' . DPS_Money_Helper::format_to_brazilian( $credits );
        }

        // Fallback: formata manualmente.
        return 'R$ ' . number_format( $credits / 100, 2, ',', '.' );
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

        // REMOVIDO: Submenu redundante "Campanhas" - já acessível via aba interna
        // add_submenu_page(
        //     'desi-pet-shower',
        //     __( 'Campanhas', 'dps-loyalty-addon' ),
        //     __( 'Campanhas', 'dps-loyalty-addon' ),
        //     'manage_options',
        //     'edit.php?post_type=dps_campaign'
        // );
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
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'reports', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>"
                   class="nav-tab <?php echo $active_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Relatórios', 'dps-loyalty-addon' ); ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'ranking', admin_url( 'admin.php?page=dps-loyalty' ) ) ); ?>"
                   class="nav-tab <?php echo $active_tab === 'ranking' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Ranking', 'dps-loyalty-addon' ); ?>
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
                    case 'reports':
                        $this->render_reports_tab();
                        break;
                    case 'ranking':
                        $this->render_ranking_tab();
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
     * @since 1.2.0
     * @since 1.3.0 Usa método padronizado para exibição de créditos.
     *
     * @param array $metrics Métricas globais.
     */
    private function render_dashboard_tab( $metrics ) {
        $timeseries         = DPS_Loyalty_API::get_points_timeseries( 6 );
        $tier_distribution  = DPS_Loyalty_API::get_tier_distribution();
        $recent_summary     = $this->get_recent_points_summary();
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
                <span class="dps-loyalty-card-value"><?php echo esc_html( $this->format_credits_display( $metrics['total_credits'] ) ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Créditos em Circulação', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card dps-loyalty-card--info">
                <span class="dps-loyalty-card-icon">📈</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( number_format( $recent_summary['granted_30d'], 0, ',', '.' ) ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Pontos concedidos (30d)', 'dps-loyalty-addon' ); ?></span>
            </div>
            <div class="dps-loyalty-card dps-loyalty-card--danger">
                <span class="dps-loyalty-card-icon">↘️</span>
                <span class="dps-loyalty-card-value"><?php echo esc_html( number_format( $recent_summary['redeemed_30d'], 0, ',', '.' ) ); ?></span>
                <span class="dps-loyalty-card-label"><?php esc_html_e( 'Pontos resgatados (30d)', 'dps-loyalty-addon' ); ?></span>
            </div>
        </div>

        <hr />

        <div class="dps-loyalty-grid">
            <div class="dps-loyalty-panel">
                <h2><?php esc_html_e( 'Pontos concedidos x resgatados', 'dps-loyalty-addon' ); ?></h2>
                <canvas id="dps-loyalty-timeseries" data-timeseries="<?php echo esc_attr( wp_json_encode( $timeseries ) ); ?>"></canvas>
            </div>
            <div class="dps-loyalty-panel">
                <h2><?php esc_html_e( 'Distribuição por nível', 'dps-loyalty-addon' ); ?></h2>
                <canvas id="dps-loyalty-tiers" data-tiers="<?php echo esc_attr( wp_json_encode( $tier_distribution ) ); ?>"></canvas>
                <ul class="dps-tier-legend">
                    <li><span class="dps-tier-dot dps-tier-bronze"></span><?php esc_html_e( 'Bronze', 'dps-loyalty-addon' ); ?> – <?php echo esc_html( isset( $tier_distribution['bronze'] ) ? $tier_distribution['bronze'] : 0 ); ?></li>
                    <li><span class="dps-tier-dot dps-tier-prata"></span><?php esc_html_e( 'Prata', 'dps-loyalty-addon' ); ?> – <?php echo esc_html( isset( $tier_distribution['prata'] ) ? $tier_distribution['prata'] : 0 ); ?></li>
                    <li><span class="dps-tier-dot dps-tier-ouro"></span><?php esc_html_e( 'Ouro', 'dps-loyalty-addon' ); ?> – <?php echo esc_html( isset( $tier_distribution['ouro'] ) ? $tier_distribution['ouro'] : 0 ); ?></li>
                </ul>
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
     * Renderiza relatório de campanhas com métricas agregadas.
     *
     * @since 1.4.0
     */
    private function render_reports_tab() {
        $campaigns = DPS_Loyalty_API::get_campaign_effectiveness();
        ?>
        <h2><?php esc_html_e( 'Relatório de Campanhas', 'dps-loyalty-addon' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Acompanhe elegibilidade, uso e pontos gerados por campanha.', 'dps-loyalty-addon' ); ?></p>

        <div class="dps-referrals-table-wrapper">
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Campanha', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Período', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Elegíveis', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Usaram', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Taxa de uso', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Pontos gerados', 'dps-loyalty-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $campaigns ) ) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e( 'Nenhuma campanha encontrada.', 'dps-loyalty-addon' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $campaigns as $campaign ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $campaign['name'] ); ?></strong><br />
                                    <code><?php echo esc_html( 'ID: ' . $campaign['id'] ); ?></code>
                                </td>
                                <td>
                                    <?php
                                    $start = $campaign['start'] ? esc_html( $campaign['start'] ) : '—';
                                    $end   = $campaign['end'] ? esc_html( $campaign['end'] ) : '—';
                                    echo $start . ' → ' . $end;
                                    ?>
                                </td>
                                <td><?php echo esc_html( number_format_i18n( $campaign['eligible'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $campaign['used'] ) ); ?></td>
                                <td><?php echo esc_html( $campaign['usage_rate'] ); ?>%</td>
                                <td><?php echo esc_html( number_format_i18n( $campaign['points'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderiza ranking de clientes mais engajados.
     *
     * @since 1.4.0
     */
    private function render_ranking_tab() {
        $period = isset( $_GET['ranking_period'] ) ? sanitize_text_field( wp_unslash( $_GET['ranking_period'] ) ) : '90d';
        $limit  = isset( $_GET['ranking_limit'] ) ? max( 5, absint( $_GET['ranking_limit'] ) ) : 20;

        $start_date = '';
        $end_date   = gmdate( 'Y-m-d' );

        switch ( $period ) {
            case '30d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
                break;
            case '365d':
                $start_date = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
                break;
            default:
                $start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
        }

        $ranking = DPS_Loyalty_API::get_engagement_ranking(
            [
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'limit'      => $limit,
            ]
        );
        ?>
        <h2><?php esc_html_e( 'Ranking de clientes', 'dps-loyalty-addon' ); ?></h2>
        <form method="get" class="dps-ranking-filters">
            <input type="hidden" name="page" value="dps-loyalty" />
            <input type="hidden" name="tab" value="ranking" />
            <label for="ranking_period">
                <?php esc_html_e( 'Período', 'dps-loyalty-addon' ); ?>
            </label>
            <select name="ranking_period" id="ranking_period">
                <option value="30d" <?php selected( $period, '30d' ); ?>><?php esc_html_e( 'Últimos 30 dias', 'dps-loyalty-addon' ); ?></option>
                <option value="90d" <?php selected( $period, '90d' ); ?>><?php esc_html_e( 'Últimos 90 dias', 'dps-loyalty-addon' ); ?></option>
                <option value="365d" <?php selected( $period, '365d' ); ?>><?php esc_html_e( 'Últimos 12 meses', 'dps-loyalty-addon' ); ?></option>
            </select>

            <label for="ranking_limit" style="margin-left:12px;">
                <?php esc_html_e( 'Quantidade', 'dps-loyalty-addon' ); ?>
            </label>
            <input type="number" min="5" max="50" name="ranking_limit" id="ranking_limit" value="<?php echo esc_attr( $limit ); ?>" />
            <?php submit_button( __( 'Aplicar', 'dps-loyalty-addon' ), 'secondary', '', false ); ?>
        </form>

        <div class="dps-referrals-table-wrapper">
            <table class="widefat">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Cliente', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Pontos ganhos', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Pontos resgatados', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Indicações', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Atendimentos', 'dps-loyalty-addon' ); ?></th>
                        <th><?php esc_html_e( 'Score', 'dps-loyalty-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $ranking ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'Nenhum dado para o período selecionado.', 'dps-loyalty-addon' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $ranking as $index => $client ) : ?>
                            <tr>
                                <td><?php echo esc_html( $index + 1 ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $client['name'] ); ?></strong>
                                    <div class="description"><?php printf( esc_html__( 'ID: %d', 'dps-loyalty-addon' ), (int) $client['id'] ); ?></div>
                                </td>
                                <td><?php echo esc_html( number_format_i18n( $client['earned'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $client['redeemed'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $client['referrals'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( $client['appointments'] ) ); ?></td>
                                <td><strong><?php echo esc_html( number_format_i18n( $client['score'] ) ); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Resumo rápido de pontos concedidos e resgatados nos últimos 30 dias.
     *
     * @since 1.4.0
     *
     * @return array
     */
    private function get_recent_points_summary() {
        $granted = 0;
        $redeem  = 0;
        $cutoff  = ( new DateTime( 'now', wp_timezone() ) )->modify( '-30 days' );

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 300,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ] );

        foreach ( $clients as $client_id ) {
            $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
            foreach ( $logs as $log ) {
                $date = isset( $log['date'] ) ? date_create( $log['date'] ) : false;
                if ( ! $date || $date < $cutoff ) {
                    continue;
                }

                $points = isset( $log['points'] ) ? (int) $log['points'] : 0;
                if ( 'add' === $log['action'] ) {
                    $granted += $points;
                }
                if ( in_array( $log['action'], [ 'redeem', 'expire' ], true ) ) {
                    $redeem += $points;
                }
            }
        }

        return [
            'granted_30d'  => $granted,
            'redeemed_30d' => $redeem,
        ];
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
        $settings = get_option( self::OPTION_KEY, [] );
        $referral_page_id = isset( $settings['referral_page_id'] ) ? (int) $settings['referral_page_id'] : 0;
        $portal_enabled   = ! empty( $settings['enable_portal_redemption'] );
        $portal_min_points = isset( $settings['portal_min_points_to_redeem'] ) ? absint( $settings['portal_min_points_to_redeem'] ) : 0;
        $portal_points_per_real = isset( $settings['portal_points_per_real'] ) ? absint( $settings['portal_points_per_real'] ) : 100;
        $portal_max_discount = isset( $settings['portal_max_discount_amount'] ) ? (int) $settings['portal_max_discount_amount'] : 0;
        $send_points_notification   = ! empty( $settings['send_points_notification'] );
        $send_referral_notification = ! empty( $settings['send_referral_notification'] );
        $points_template = isset( $settings['points_notification_template'] ) ? $settings['points_notification_template'] : __( 'Olá {client_name}! 🎉 Você acabou de ganhar {points} pontos no programa de fidelidade. Seu saldo agora é de {new_balance} pontos.', 'dps-loyalty-addon' );
        $referral_template = isset( $settings['referral_notification_template'] ) ? $settings['referral_notification_template'] : __( 'Obrigad@ por indicar amigos! 🐾 Você recebeu uma recompensa no programa de fidelidade.', 'dps-loyalty-addon' );
        $enable_expiration = ! empty( $settings['enable_points_expiration'] );
        $expiration_months = isset( $settings['points_expire_after_months'] ) ? absint( $settings['points_expire_after_months'] ) : 12;
        $enable_expiration_notices = ! empty( $settings['enable_expiration_notifications'] );
        $days_before_notice = isset( $settings['days_before_expiration_notice'] ) ? absint( $settings['days_before_expiration_notice'] ) : 15;
        $expiration_template = isset( $settings['expiration_notification_template'] ) ? $settings['expiration_notification_template'] : __( 'Olá {client_name}! Você tem {expiring_points} pontos que expiram em {days} dias. Aproveite para usar seus benefícios com a gente! 🐾', 'dps-loyalty-addon' );
        
        // Busca todas as páginas publicadas para o dropdown
        $pages = get_pages( [
            'post_status' => 'publish',
            'sort_column' => 'post_title',
        ] );
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

            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Níveis de Fidelidade', 'dps-loyalty-addon' ); ?></legend>
                <p class="description"><?php esc_html_e( 'Configure os níveis, limiares de pontos e multiplicadores. O último nível da lista é considerado o máximo.', 'dps-loyalty-addon' ); ?></p>
                <?php $tiers = DPS_Loyalty_API::get_tiers_config(); ?>
                <table class="widefat fixed dps-tier-table" id="dps-tier-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Slug', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Nome', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Pontos mínimos', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Multiplicador', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Ícone', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Cor', 'dps-loyalty-addon' ); ?></th>
                            <th><?php esc_html_e( 'Ações', 'dps-loyalty-addon' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dps-tier-rows">
                        <?php foreach ( $tiers as $index => $tier ) : ?>
                            <tr class="dps-tier-row">
                                <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $tier['slug'] ); ?>" required /></td>
                                <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $tier['label'] ); ?>" required /></td>
                                <td><input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][min_points]" value="<?php echo esc_attr( $tier['min_points'] ); ?>" /></td>
                                <td><input type="number" step="0.1" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][multiplier]" value="<?php echo esc_attr( $tier['multiplier'] ); ?>" /></td>
                                <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][icon]" value="<?php echo esc_attr( $tier['icon'] ); ?>" /></td>
                                <td><input type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][<?php echo esc_attr( $index ); ?>][color]" value="<?php echo esc_attr( $tier['color'] ); ?>" /></td>
                                <td><button type="button" class="button dps-remove-tier">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="dps-add-tier"><?php esc_html_e( 'Adicionar nível', 'dps-loyalty-addon' ); ?></button></p>
                <template id="dps-tier-template">
                    <tr class="dps-tier-row">
                        <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][slug]" required /></td>
                        <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][label]" required /></td>
                        <td><input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][min_points]" /></td>
                        <td><input type="number" step="0.1" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][multiplier]" value="1" /></td>
                        <td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][icon]" /></td>
                        <td><input type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[loyalty_tiers][__index__][color]" value="#e5e7eb" /></td>
                        <td><button type="button" class="button dps-remove-tier">&times;</button></td>
                    </tr>
                </template>
            </fieldset>
            
            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Link de Indicação', 'dps-loyalty-addon' ); ?></legend>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="dps_referral_page_id"><?php esc_html_e( 'Página de cadastro para indicações', 'dps-loyalty-addon' ); ?></label>
                        </th>
                        <td>
                            <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referral_page_id]" id="dps_referral_page_id">
                                <option value="0"><?php esc_html_e( '— Usar página de cadastro padrão —', 'dps-loyalty-addon' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $referral_page_id, $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Escolha a página para onde o link de indicação irá direcionar novos clientes. Se não configurar, será usada a página do add-on "Cadastro".', 'dps-loyalty-addon' ); ?>
                            </p>
                            <?php 
                            // Mostra preview da URL atual
                            $preview_url = DPS_Loyalty_API::get_referral_url( 0 );
                            // Remove o ?ref= já que não temos um cliente real
                            $preview_base = remove_query_arg( 'ref', $preview_url );
                            if ( $preview_base ) :
                            ?>
                            <p class="description" style="margin-top: 8px;">
                                <strong><?php esc_html_e( 'URL base atual:', 'dps-loyalty-addon' ); ?></strong> 
                                <code><?php echo esc_html( $preview_base ); ?></code>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </fieldset>

            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Comunicações', 'dps-loyalty-addon' ); ?></legend>
                <p class="description"><?php esc_html_e( 'Configure avisos automáticos via Communications quando o cliente ganhar pontos ou receber recompensas de indicação.', 'dps-loyalty-addon' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bonificação de pontos', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[send_points_notification]" value="1" <?php checked( $send_points_notification ); ?> />
                                    <?php esc_html_e( 'Enviar mensagem quando pontos forem creditados', 'dps-loyalty-addon' ); ?>
                                </label>
                            </p>
                            <p>
                                <label for="dps_points_notification_template" style="display:block;">
                                    <?php esc_html_e( 'Template da mensagem', 'dps-loyalty-addon' ); ?>
                                </label>
                                <textarea id="dps_points_notification_template" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[points_notification_template]" rows="3" style="width: 100%; max-width: 480px;"><?php echo esc_textarea( $points_template ); ?></textarea>
                                <span class="description"><?php esc_html_e( 'Placeholders: {client_name}, {points}, {new_balance}, {context}, {tier_name}', 'dps-loyalty-addon' ); ?></span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Recompensa de indicação', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[send_referral_notification]" value="1" <?php checked( $send_referral_notification ); ?> />
                                    <?php esc_html_e( 'Enviar mensagem quando bônus de indicação for aplicado', 'dps-loyalty-addon' ); ?>
                                </label>
                            </p>
                            <p>
                                <label for="dps_referral_notification_template" style="display:block;">
                                    <?php esc_html_e( 'Template da mensagem', 'dps-loyalty-addon' ); ?>
                                </label>
                                <textarea id="dps_referral_notification_template" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referral_notification_template]" rows="3" style="width: 100%; max-width: 480px;"><?php echo esc_textarea( $referral_template ); ?></textarea>
                                <span class="description"><?php esc_html_e( 'Placeholders: {client_name}, {points}, {new_balance}, {context}, {tier_name}', 'dps-loyalty-addon' ); ?></span>
                            </p>
                        </td>
                    </tr>
                </table>
            </fieldset>

            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Expiração de Pontos', 'dps-loyalty-addon' ); ?></legend>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Ativar expiração automática', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_points_expiration]" value="1" <?php checked( $enable_expiration ); ?> />
                                <?php esc_html_e( 'Expirar pontos após X meses', 'dps-loyalty-addon' ); ?>
                            </label>
                            <p style="margin-top:8px;">
                                <label>
                                    <?php esc_html_e( 'Meses até expirar', 'dps-loyalty-addon' ); ?>
                                    <input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[points_expire_after_months]" value="<?php echo esc_attr( $expiration_months ); ?>" />
                                </label>
                            </p>
                            <p class="description"><?php esc_html_e( 'Os lançamentos mais antigos são expirados primeiro (FIFO) com um lançamento negativo no histórico.', 'dps-loyalty-addon' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Avisos de pontos a expirar', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <p>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_expiration_notifications]" value="1" <?php checked( $enable_expiration_notices ); ?> />
                                    <?php esc_html_e( 'Enviar alerta antes da expiração', 'dps-loyalty-addon' ); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <?php esc_html_e( 'Dias antes do vencimento', 'dps-loyalty-addon' ); ?>
                                    <input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[days_before_expiration_notice]" value="<?php echo esc_attr( $days_before_notice ); ?>" />
                                </label>
                            </p>
                            <p>
                                <label for="dps_loyalty_expiration_template" style="display:block;">
                                    <?php esc_html_e( 'Template do aviso', 'dps-loyalty-addon' ); ?>
                                </label>
                                <textarea id="dps_loyalty_expiration_template" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[expiration_notification_template]" rows="3" style="width: 100%; max-width: 480px;"><?php echo esc_textarea( $expiration_template ); ?></textarea>
                                <span class="description"><?php esc_html_e( 'Placeholders: {client_name}, {expiring_points}, {days}', 'dps-loyalty-addon' ); ?></span>
                            </p>
                        </td>
                    </tr>
                </table>
            </fieldset>

            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Resgate no Portal', 'dps-loyalty-addon' ); ?></legend>
                <p class="description"><?php esc_html_e( 'Permite que o cliente converta pontos em crédito diretamente pelo Portal do Cliente, respeitando limite por resgate.', 'dps-loyalty-addon' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Habilitar resgate', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_portal_redemption]" value="1" <?php checked( $portal_enabled ); ?> />
                                <?php esc_html_e( 'Permitir resgate de pontos pelo Portal', 'dps-loyalty-addon' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Mínimo de pontos', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[portal_min_points_to_redeem]" value="<?php echo esc_attr( $portal_min_points ); ?>" />
                            <p class="description"><?php esc_html_e( 'Quantidade mínima que o cliente precisa ter para iniciar um resgate.', 'dps-loyalty-addon' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Pontos por R$ 1,00', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <input type="number" min="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[portal_points_per_real]" value="<?php echo esc_attr( $portal_points_per_real ); ?>" />
                            <p class="description"><?php esc_html_e( 'Exemplo: 100 pontos = R$ 1,00 de crédito.', 'dps-loyalty-addon' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Limite por resgate (R$)', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[portal_max_discount_amount]" value="<?php echo esc_attr( DPS_Money_Helper::format_to_brazilian( $portal_max_discount ) ); ?>" />
                            <p class="description"><?php esc_html_e( 'Teto de crédito convertido a cada solicitação no Portal.', 'dps-loyalty-addon' ); ?></p>
                        </td>
                    </tr>
                </table>
            </fieldset>

            <fieldset style="margin-top: 20px;">
                <legend><?php esc_html_e( 'Uso de créditos no Financeiro', 'dps-loyalty-addon' ); ?></legend>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Permitir abatimento', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_finance_credit_usage]" value="1" <?php checked( ! empty( $settings['enable_finance_credit_usage'] ) ); ?> />
                                <?php esc_html_e( 'Permitir usar créditos de fidelidade ao registrar pagamentos no Financeiro.', 'dps-loyalty-addon' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Limite por atendimento (R$)', 'dps-loyalty-addon' ); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[finance_max_credit_per_appointment]" value="<?php echo esc_attr( DPS_Money_Helper::format_to_brazilian( isset( $settings['finance_max_credit_per_appointment'] ) ? (int) $settings['finance_max_credit_per_appointment'] : 0 ) ); ?>" />
                            <p class="description"><?php esc_html_e( 'Valor máximo de créditos que podem ser abatidos em um atendimento.', 'dps-loyalty-addon' ); ?></p>
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
     * @since 1.2.0
     * @since 1.3.0 Substituído dropdown por autocomplete.
     *
     * @param int $selected_id ID do cliente selecionado.
     */
    private function render_clients_tab( $selected_id ) {
        $logs_limit       = 10;
        $logs_page        = isset( $_GET['logs_page'] ) ? max( 1, absint( $_GET['logs_page'] ) ) : 1;
        $logs_offset      = ( $logs_page - 1 ) * $logs_limit;
        $logs             = [];
        $total_logs       = 0;
        $total_logs_pages = 1;

        if ( $selected_id ) {
            $logs = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_points_history(
                $selected_id,
                [
                    'limit'  => $logs_limit,
                    'offset' => $logs_offset,
                ]
            ) : dps_loyalty_get_logs( $selected_id );

            $total_logs = count( get_post_meta( $selected_id, 'dps_loyalty_points_log' ) );
            $total_logs_pages = $logs_limit > 0 ? max( 1, (int) ceil( $total_logs / $logs_limit ) ) : 1;
        }
        $selected_name = '';
        
        // Busca nome do cliente selecionado para exibir no campo.
        if ( $selected_id ) {
            $selected_client = get_post( $selected_id );
            if ( $selected_client ) {
                $selected_name = $selected_client->post_title;
            }
        }
        ?>
        <h2><?php esc_html_e( 'Resumo de Fidelidade', 'dps-loyalty-addon' ); ?></h2>
        
        <!-- Formulário com autocomplete -->
        <div class="dps-client-search-container">
            <form method="get" id="dps-loyalty-client-form">
                <input type="hidden" name="page" value="dps-loyalty" />
                <input type="hidden" name="tab" value="clients" />
                <input type="hidden" id="dps-loyalty-client-id" name="dps_client_id" value="<?php echo esc_attr( $selected_id ); ?>" />
                
                <label for="dps-loyalty-client-search"><?php esc_html_e( 'Buscar cliente', 'dps-loyalty-addon' ); ?></label>
                <div class="dps-autocomplete-wrapper">
                    <input 
                        type="text" 
                        id="dps-loyalty-client-search" 
                        class="regular-text"
                        placeholder="<?php esc_attr_e( 'Digite o nome ou telefone do cliente...', 'dps-loyalty-addon' ); ?>"
                        value="<?php echo esc_attr( $selected_name ); ?>"
                        autocomplete="off"
                    />
                    <div id="dps-loyalty-client-results" class="dps-autocomplete-results"></div>
                </div>
                <?php submit_button( __( 'Carregar', 'dps-loyalty-addon' ), 'secondary', '', false ); ?>
            </form>
            
            <?php if ( $selected_id && $selected_name ) : ?>
                <p class="dps-selected-client-info">
                    <?php 
                    printf(
                        /* translators: %s: client name */
                        esc_html__( 'Cliente selecionado: %s', 'dps-loyalty-addon' ),
                        '<strong>' . esc_html( $selected_name ) . '</strong>'
                    );
                    ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-loyalty&tab=clients' ) ); ?>" class="button-link">
                        <?php esc_html_e( 'Limpar', 'dps-loyalty-addon' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <?php if ( $selected_id ) :
            $client_points = dps_loyalty_get_points( $selected_id );
            $client_credit = $this->get_credit_for_display( $selected_id );
            $referral_code = dps_loyalty_get_referral_code( $selected_id );
            $referral_stats = DPS_Loyalty_API::get_referral_stats( $selected_id );
            $tier_info = DPS_Loyalty_API::get_loyalty_tier( $selected_id );
            $achievement_definitions = DPS_Loyalty_Achievements::get_achievements_definitions();
            $unlocked_achievements  = DPS_Loyalty_Achievements::get_client_achievements( $selected_id );
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
                    <span class="dps-loyalty-card-value"><?php echo esc_html( $client_credit ); ?></span>
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
                            $tiers = DPS_Loyalty_API::get_tiers_config();
                            $next_tier_icon = '🏆';
                            foreach ( $tiers as $tier_item ) {
                                if ( $tier_item['slug'] === $tier_info['next_tier'] ) {
                                    $next_tier_icon = $tier_item['icon'];
                                    break;
                                }
                            }
                            echo esc_html( $next_tier_icon );
                        ?></span>
                        <span class="dps-tier-name"><?php echo esc_html( $tier_info['next_label'] ); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="dps-achievements-wrapper">
                <h3><?php esc_html_e( 'Conquistas', 'dps-loyalty-addon' ); ?></h3>
                <div class="dps-achievements-grid">
                    <?php foreach ( $achievement_definitions as $key => $achievement ) :
                        $unlocked = in_array( $key, $unlocked_achievements, true );
                        ?>
                        <div class="dps-achievement-card <?php echo $unlocked ? '' : 'is-locked'; ?>">
                            <h4><?php echo esc_html( $achievement['label'] ); ?></h4>
                            <p><?php echo esc_html( $achievement['description'] ); ?></p>
                            <span class="dps-achievement-status"><?php echo esc_html( $unlocked ? __( 'Conquistado', 'dps-loyalty-addon' ) : __( 'Ainda não', 'dps-loyalty-addon' ) ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

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
                <h3><?php esc_html_e( 'Histórico de pontos', 'dps-loyalty-addon' ); ?></h3>
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

                <?php if ( $total_logs_pages > 1 ) :
                    $base_url = add_query_arg(
                        [
                            'page'          => 'dps-loyalty',
                            'tab'           => 'clients',
                            'dps_client_id' => $selected_id,
                        ],
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <div class="dps-pagination">
                        <?php if ( $logs_page > 1 ) :
                            $prev_url = add_query_arg( 'logs_page', $logs_page - 1, $base_url );
                            ?>
                            <a class="button" href="<?php echo esc_url( $prev_url ); ?>">&laquo; <?php esc_html_e( 'Anterior', 'dps-loyalty-addon' ); ?></a>
                        <?php endif; ?>

                        <span class="dps-pagination-info">
                            <?php echo esc_html( sprintf( __( 'Página %1$d de %2$d', 'dps-loyalty-addon' ), $logs_page, $total_logs_pages ) ); ?>
                        </span>

                        <?php if ( $logs_page < $total_logs_pages ) :
                            $next_url = add_query_arg( 'logs_page', $logs_page + 1, $base_url );
                            ?>
                            <a class="button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Próxima', 'dps-loyalty-addon' ); ?> &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
        if ( class_exists( 'DPS_Loyalty_API' ) ) {
            return DPS_Loyalty_API::get_context_label( $context );
        }

        return $context;
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
        
        // Sanitize filename to prevent header injection
        $filename = sanitize_file_name( 'indicacoes-' . gmdate( 'Y-m-d' ) . '.csv' );
        
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        echo $csv;
        exit;
    }

    /**
     * Encontra clientes elegíveis para uma campanha.
     *
     * @since 1.2.0
     * @since 1.3.0 Otimizado para usar batch query em vez de N+1.
     *
     * @param int $campaign_id ID da campanha.
     * @return int[] Array de IDs de clientes elegíveis.
     */
    private function find_eligible_clients_for_campaign( $campaign_id ) {
        $eligibility      = get_post_meta( $campaign_id, 'dps_campaign_eligibility', true );
        $inactive_days    = absint( get_post_meta( $campaign_id, 'dps_campaign_inactive_days', true ) );
        $points_threshold = absint( get_post_meta( $campaign_id, 'dps_campaign_points_threshold', true ) );
        $eligible_clients = [];

        $check_inactive = ! empty( $eligibility ) && in_array( 'inactive', (array) $eligibility, true );
        $check_points   = ! empty( $eligibility ) && in_array( 'points', (array) $eligibility, true );

        // Limite de clientes processados por campanha (500 clientes).
        // Para bases maiores, considerar processamento em background via cron job.
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        ] );

        if ( empty( $clients ) ) {
            return $eligible_clients;
        }

        // Carrega datas de último atendimento em batch (elimina N+1).
        $last_appointments = [];
        if ( $check_inactive && $inactive_days > 0 ) {
            $last_appointments = $this->get_last_appointments_batch( $clients );
        }

        foreach ( $clients as $client_id ) {
            $passes_inactive = false;
            $passes_points   = false;

            // Verifica inatividade usando dados do batch.
            if ( $check_inactive && $inactive_days > 0 ) {
                $last_date = isset( $last_appointments[ $client_id ] ) ? $last_appointments[ $client_id ] : '';
                $passes_inactive = $this->is_client_inactive_from_date( $last_date, $inactive_days );
            }

            // Verifica pontos.
            if ( $check_points ) {
                $passes_points = dps_loyalty_get_points( $client_id ) >= $points_threshold;
            }

            if ( ( $passes_inactive || $passes_points ) && ! in_array( $client_id, $eligible_clients, true ) ) {
                $eligible_clients[] = $client_id;
            }
        }

        return $eligible_clients;
    }

    /**
     * Retorna, em batch, a última data de atendimento por cliente.
     *
     * Otimização para evitar queries N+1 ao verificar inatividade.
     *
     * @since 1.3.0
     *
     * @param int[] $client_ids Array de IDs de clientes.
     * @return array Associativo: client_id => 'Y-m-d' (data do último atendimento).
     */
    private function get_last_appointments_batch( $client_ids ) {
        if ( empty( $client_ids ) ) {
            return [];
        }

        global $wpdb;

        // Sanitiza IDs.
        $client_ids = array_map( 'absint', $client_ids );
        $client_ids = array_filter( $client_ids );

        if ( empty( $client_ids ) ) {
            return [];
        }

        // Cria placeholders para IN clause.
        $placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );

        // Query otimizada: busca a última data de atendimento para cada cliente.
        // Usa subquery com MAX para obter apenas a data mais recente.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    m1.meta_value AS client_id,
                    MAX(m2.meta_value) AS last_date
                FROM {$wpdb->postmeta} m1
                INNER JOIN {$wpdb->postmeta} m2 
                    ON m1.post_id = m2.post_id 
                    AND m2.meta_key = 'appointment_date'
                INNER JOIN {$wpdb->posts} p 
                    ON m1.post_id = p.ID 
                    AND p.post_type = 'dps_agendamento'
                    AND p.post_status = 'publish'
                WHERE m1.meta_key = 'appointment_client_id'
                    AND m1.meta_value IN ({$placeholders})
                GROUP BY m1.meta_value",
                ...$client_ids
            ),
            OBJECT
        );

        // Converte para array associativo.
        $appointments = [];
        if ( $results ) {
            foreach ( $results as $row ) {
                $appointments[ (int) $row->client_id ] = $row->last_date;
            }
        }

        return $appointments;
    }

    /**
     * Verifica se um cliente está inativo com base na data do último atendimento.
     *
     * @since 1.3.0
     *
     * @param string $last_date Data do último atendimento (Y-m-d ou vazio).
     * @param int    $days      Número de dias para considerar inativo.
     * @return bool True se inativo, false caso contrário.
     */
    private function is_client_inactive_from_date( $last_date, $days ) {
        if ( $days <= 0 ) {
            return false;
        }

        // Cliente sem atendimentos é considerado inativo.
        if ( empty( $last_date ) ) {
            return true;
        }

        $interval = ( time() - strtotime( $last_date ) ) / DAY_IN_SECONDS;
        return $interval >= $days;
    }

    /**
     * Verifica se um cliente está inativo há X dias.
     *
     * @deprecated 1.3.0 Use is_client_inactive_from_date() com dados do batch.
     *
     * @param int $client_id ID do cliente.
     * @param int $days      Número de dias.
     * @return bool True se inativo.
     */
    private function is_client_inactive_for_days( $client_id, $days ) {
        if ( $days <= 0 ) {
            return false;
        }

        $last_date = $this->get_last_appointment_date_for_client( $client_id );
        return $this->is_client_inactive_from_date( $last_date, $days );
    }

    /**
     * Obtém a data do último atendimento de um cliente individual.
     *
     * @deprecated 1.3.0 Use get_last_appointments_batch() para múltiplos clientes.
     *
     * @param int $client_id ID do cliente.
     * @return string Data no formato Y-m-d ou vazio.
     */
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
        $output['referral_page_id']           = isset( $input['referral_page_id'] ) ? absint( $input['referral_page_id'] ) : 0;
        $output['referrals_enabled']          = ! empty( $input['referrals_enabled'] ) ? 1 : 0;
        $output['referrer_reward_type']       = isset( $input['referrer_reward_type'] ) ? sanitize_text_field( $input['referrer_reward_type'] ) : 'none';
        $output['referrer_reward_value']      = isset( $input['referrer_reward_value'] ) ? $this->sanitize_reward_value( $input['referrer_reward_value'], $output['referrer_reward_type'] ) : 0;
        $output['referee_reward_type']        = isset( $input['referee_reward_type'] ) ? sanitize_text_field( $input['referee_reward_type'] ) : 'none';
        $output['referee_reward_value']       = isset( $input['referee_reward_value'] ) ? $this->sanitize_reward_value( $input['referee_reward_value'], $output['referee_reward_type'] ) : 0;
        $output['referrals_minimum_amount']   = isset( $input['referrals_minimum_amount'] ) ? dps_loyalty_parse_money_br( $input['referrals_minimum_amount'] ) : 0;
        $output['referrals_max_per_referrer'] = isset( $input['referrals_max_per_referrer'] ) ? absint( $input['referrals_max_per_referrer'] ) : 0;
        $output['referrals_first_purchase']   = ! empty( $input['referrals_first_purchase'] ) ? 1 : 0;

        $output['enable_portal_redemption']   = ! empty( $input['enable_portal_redemption'] ) ? 1 : 0;
        $output['portal_min_points_to_redeem']= isset( $input['portal_min_points_to_redeem'] ) ? absint( $input['portal_min_points_to_redeem'] ) : 0;
        $output['portal_points_per_real']     = isset( $input['portal_points_per_real'] ) ? max( 1, absint( $input['portal_points_per_real'] ) ) : 100;
        $output['portal_max_discount_amount'] = isset( $input['portal_max_discount_amount'] ) ? dps_loyalty_parse_money_br( $input['portal_max_discount_amount'] ) : 0;
        $output['enable_finance_credit_usage'] = ! empty( $input['enable_finance_credit_usage'] ) ? 1 : 0;
        $output['finance_max_credit_per_appointment'] = isset( $input['finance_max_credit_per_appointment'] ) ? dps_loyalty_parse_money_br( $input['finance_max_credit_per_appointment'] ) : 0;

        $output['loyalty_tiers'] = [];
        if ( isset( $input['loyalty_tiers'] ) && is_array( $input['loyalty_tiers'] ) ) {
            foreach ( $input['loyalty_tiers'] as $tier ) {
                if ( empty( $tier['slug'] ) ) {
                    continue;
                }

                $output['loyalty_tiers'][] = [
                    'slug'       => sanitize_key( $tier['slug'] ),
                    'label'      => isset( $tier['label'] ) ? sanitize_text_field( $tier['label'] ) : strtoupper( sanitize_key( $tier['slug'] ) ),
                    'min_points' => isset( $tier['min_points'] ) ? absint( $tier['min_points'] ) : 0,
                    'multiplier' => isset( $tier['multiplier'] ) ? (float) $tier['multiplier'] : 1.0,
                    'icon'       => isset( $tier['icon'] ) ? sanitize_text_field( $tier['icon'] ) : '⭐',
                    'color'      => isset( $tier['color'] ) ? sanitize_hex_color( $tier['color'] ) : '',
                ];
            }
        }

        if ( empty( $output['loyalty_tiers'] ) ) {
            $output['loyalty_tiers'] = DPS_Loyalty_API::get_default_tiers();
        }

        $default_points_template  = __( 'Olá {client_name}! 🎉 Você acabou de ganhar {points} pontos no programa de fidelidade. Seu saldo agora é de {new_balance} pontos.', 'dps-loyalty-addon' );
        $default_referral_template = __( 'Obrigad@ por indicar amigos! 🐾 Você recebeu uma recompensa no programa de fidelidade.', 'dps-loyalty-addon' );
        $default_expiration_template = __( 'Olá {client_name}! Você tem {expiring_points} pontos que expiram em {days} dias. Aproveite para usar seus benefícios com a gente! 🐾', 'dps-loyalty-addon' );

        $output['send_points_notification']   = ! empty( $input['send_points_notification'] ) ? 1 : 0;
        $output['send_referral_notification'] = ! empty( $input['send_referral_notification'] ) ? 1 : 0;
        $output['enable_points_expiration']   = ! empty( $input['enable_points_expiration'] ) ? 1 : 0;
        $output['points_expire_after_months'] = isset( $input['points_expire_after_months'] ) ? max( 1, absint( $input['points_expire_after_months'] ) ) : 12;
        $output['enable_expiration_notifications'] = ! empty( $input['enable_expiration_notifications'] ) ? 1 : 0;
        $output['days_before_expiration_notice']   = isset( $input['days_before_expiration_notice'] ) ? max( 1, absint( $input['days_before_expiration_notice'] ) ) : 15;

        $output['points_notification_template']  = isset( $input['points_notification_template'] ) ? sanitize_textarea_field( $input['points_notification_template'] ) : $default_points_template;
        $output['referral_notification_template'] = isset( $input['referral_notification_template'] ) ? sanitize_textarea_field( $input['referral_notification_template'] ) : $default_referral_template;
        $output['expiration_notification_template'] = isset( $input['expiration_notification_template'] ) ? sanitize_textarea_field( $input['expiration_notification_template'] ) : $default_expiration_template;

        if ( $output['brl_per_point'] <= 0 ) {
            $output['brl_per_point'] = 10.0;
        }
        return $output;
    }

    /**
     * Garante o agendamento dos crons diários.
     */
    public function maybe_schedule_crons() {
        if ( ! wp_next_scheduled( 'dps_loyalty_expire_points_daily' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dps_loyalty_expire_points_daily' );
        }

        if ( ! wp_next_scheduled( 'dps_loyalty_expiration_notices_daily' ) ) {
            wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'dps_loyalty_expiration_notices_daily' );
        }
    }

    /**
     * Expira pontos vencidos com base no número de meses configurado.
     */
    public function handle_points_expiration() {
        $settings = get_option( self::OPTION_KEY, [] );
        if ( empty( $settings['enable_points_expiration'] ) ) {
            return;
        }

        $months  = isset( $settings['points_expire_after_months'] ) ? max( 1, absint( $settings['points_expire_after_months'] ) ) : 12;
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 300,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'dps_loyalty_points',
                    'value'   => 0,
                    'compare' => '>',
                ],
            ],
        ] );

        foreach ( $clients as $client_id ) {
            $expirable = DPS_Loyalty_API::get_expirable_points( $client_id, $months );
            if ( $expirable > 0 ) {
                DPS_Loyalty_API::expire_points( $client_id, $expirable );
            }
        }
    }

    /**
     * Envia avisos de pontos próximos de expirar.
     */
    public function handle_expiration_notices() {
        $settings = get_option( self::OPTION_KEY, [] );
        if ( empty( $settings['enable_points_expiration'] ) || empty( $settings['enable_expiration_notifications'] ) ) {
            return;
        }

        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            return;
        }

        $months       = isset( $settings['points_expire_after_months'] ) ? max( 1, absint( $settings['points_expire_after_months'] ) ) : 12;
        $days_before  = isset( $settings['days_before_expiration_notice'] ) ? max( 1, absint( $settings['days_before_expiration_notice'] ) ) : 15;
        $template     = isset( $settings['expiration_notification_template'] ) ? $settings['expiration_notification_template'] : __( 'Olá {client_name}! Você tem {expiring_points} pontos que expiram em {days} dias. Aproveite para usar seus benefícios com a gente! 🐾', 'dps-loyalty-addon' );
        $today        = new DateTime( 'now', wp_timezone() );
        $notice_limit = ( clone $today )->modify( '+' . $days_before . ' days' );

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'dps_loyalty_points',
                    'value'   => 0,
                    'compare' => '>',
                ],
            ],
        ] );

        foreach ( $clients as $client_id ) {
            $last_notice = get_post_meta( $client_id, 'dps_loyalty_last_expiration_notice', true );
            if ( $last_notice && strtotime( $last_notice ) > strtotime( '-1 day' ) ) {
                continue;
            }

            $expiring_points = $this->calculate_points_expiring_soon( $client_id, $months, $notice_limit );
            if ( $expiring_points <= 0 ) {
                continue;
            }

            $message = str_replace(
                [ '{client_name}', '{expiring_points}', '{days}' ],
                [ get_the_title( $client_id ), (int) $expiring_points, $days_before ],
                $template
            );

            $this->dispatch_loyalty_message( $client_id, $message, 'expiration_warning' );
            update_post_meta( $client_id, 'dps_loyalty_last_expiration_notice', current_time( 'mysql' ) );
        }
    }

    /**
     * Calcula pontos que irão expirar até uma data limite.
     *
     * @param int       $client_id   Cliente.
     * @param int       $months      Meses para expiração total.
     * @param DateTime  $notice_date Data limite para aviso.
     * @return int
     */
    private function calculate_points_expiring_soon( $client_id, $months, DateTime $notice_date ) {
        $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
        if ( empty( $logs ) ) {
            return 0;
        }

        usort(
            $logs,
            function ( $a, $b ) {
                return strcmp( isset( $a['date'] ) ? $a['date'] : '', isset( $b['date'] ) ? $b['date'] : '' );
            }
        );

        $accruals = [];
        foreach ( $logs as $log ) {
            $date = isset( $log['date'] ) ? $log['date'] : '';
            if ( empty( $date ) ) {
                continue;
            }

            $points = isset( $log['points'] ) ? (int) $log['points'] : 0;
            $action = isset( $log['action'] ) ? $log['action'] : '';

            if ( 'add' === $action ) {
                $accruals[] = [
                    'remaining' => $points,
                    'date'      => $date,
                ];
                continue;
            }

            if ( in_array( $action, [ 'redeem', 'expire' ], true ) ) {
                $to_reduce = $points;
                foreach ( $accruals as &$accrual ) {
                    if ( $to_reduce <= 0 ) {
                        break;
                    }

                    if ( $accrual['remaining'] <= 0 ) {
                        continue;
                    }

                    $deduct              = min( $accrual['remaining'], $to_reduce );
                    $accrual['remaining'] -= $deduct;
                    $to_reduce           -= $deduct;
                }
                unset( $accrual );
            }
        }

        $soon_expiring = 0;
        foreach ( $accruals as $accrual ) {
            if ( $accrual['remaining'] <= 0 ) {
                continue;
            }
            $grant_date = date_create( $accrual['date'] );
            if ( ! $grant_date ) {
                continue;
            }
            $expiration_date = ( clone $grant_date )->modify( '+' . $months . ' months' );
            if ( $expiration_date <= $notice_date && $expiration_date >= new DateTime( 'now', wp_timezone() ) ) {
                $soon_expiring += (int) $accrual['remaining'];
            }
        }

        return $soon_expiring;
    }

    /**
     * Envia notificação quando pontos são adicionados, se habilitado.
     *
     * @param int    $client_id ID do cliente.
     * @param int    $points    Pontos creditados.
     * @param string $context   Contexto do crédito.
     */
    public function maybe_notify_points_added( $client_id, $points, $context ) {
        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            return;
        }

        $settings = get_option( self::OPTION_KEY, [] );
        if ( empty( $settings['send_points_notification'] ) ) {
            return;
        }

        $template = isset( $settings['points_notification_template'] ) ? $settings['points_notification_template'] : __( 'Olá {client_name}! 🎉 Você acabou de ganhar {points} pontos no programa de fidelidade. Seu saldo agora é de {new_balance} pontos.', 'dps-loyalty-addon' );
        $message  = $this->prepare_loyalty_message( $template, $client_id, $points, $context );

        $this->dispatch_loyalty_message( $client_id, $message, 'points_awarded' );
    }

    /**
     * Envia notificação para recompensa de indicação, se habilitado.
     *
     * @param int   $client_id  ID do cliente.
     * @param int   $bonus      Bônus aplicado.
     * @param float $multiplier Multiplicador (não utilizado na mensagem, mas mantido para compatibilidade).
     */
    public function maybe_notify_referral_bonus( $client_id, $bonus, $multiplier ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            return;
        }

        $settings = get_option( self::OPTION_KEY, [] );
        if ( empty( $settings['send_referral_notification'] ) ) {
            return;
        }

        $template = isset( $settings['referral_notification_template'] ) ? $settings['referral_notification_template'] : __( 'Obrigad@ por indicar amigos! 🐾 Você recebeu uma recompensa no programa de fidelidade.', 'dps-loyalty-addon' );
        $message  = $this->prepare_loyalty_message( $template, $client_id, $bonus, 'referral_reward' );

        $this->dispatch_loyalty_message( $client_id, $message, 'referral_reward' );
    }

    /**
     * Substitui placeholders do template de mensagem.
     *
     * @param string $template  Template configurado.
     * @param int    $client_id ID do cliente.
     * @param int    $points    Valor de pontos ou bônus.
     * @param string $context   Contexto da ação.
     * @return string Mensagem formatada.
     */
    private function prepare_loyalty_message( $template, $client_id, $points, $context ) {
        $client_name  = get_the_title( $client_id );
        $new_balance  = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_points( $client_id ) : dps_loyalty_get_points( $client_id );
        $tier         = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_loyalty_tier( $client_id ) : [];
        $tier_name    = isset( $tier['label'] ) ? $tier['label'] : '';
        $context_label = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_context_label( $context ) : $context;

        $placeholders = [
            '{client_name}' => $client_name,
            '{points}'      => (int) $points,
            '{new_balance}' => (int) $new_balance,
            '{context}'     => $context_label,
            '{tier_name}'   => $tier_name,
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), (string) $template );
    }

    /**
     * Encaminha mensagem via Communications (WhatsApp e e-mail quando disponível).
     *
     * @param int    $client_id ID do cliente.
     * @param string $message   Mensagem já formatada.
     * @param string $type      Tipo de evento.
     */
    private function dispatch_loyalty_message( $client_id, $message, $type ) {
        if ( ! class_exists( 'DPS_Communications_API' ) ) {
            return;
        }

        $phone = get_post_meta( $client_id, 'client_phone', true );
        $email = get_post_meta( $client_id, 'client_email', true );
        $api   = DPS_Communications_API::get_instance();
        $context = [
            'client_id' => $client_id,
            'type'      => $type,
        ];

        if ( $phone ) {
            $api->send_whatsapp( $phone, $message, $context );
        }

        if ( $email ) {
            $subject = __( 'Atualização do programa de fidelidade', 'dps-loyalty-addon' );
            $api->send_email( $email, $subject, $message, $context );
        }
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

        if ( class_exists( 'DPS_Loyalty_API' ) ) {
            DPS_Loyalty_API::recalculate_client_tier( $client_id );
        }

        if ( class_exists( 'DPS_Loyalty_Achievements' ) ) {
            DPS_Loyalty_Achievements::evaluate_achievements_for_client( $client_id );
        }

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

        if ( class_exists( 'DPS_Loyalty_API' ) ) {
            DPS_Loyalty_API::recalculate_client_tier( $client_id );
        }

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
    function dps_loyalty_get_logs( $client_id, $args = [] ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return [];
        }

        // Compatibilidade: se $args for inteiro, considera como limit.
        if ( ! is_array( $args ) ) {
            $args = [ 'limit' => (int) $args ];
        }

        $limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 10;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
        if ( empty( $logs ) ) {
            return [];
        }

        $logs = array_reverse( $logs );

        if ( 0 === $limit ) {
            return $logs;
        }

        return array_slice( $logs, $offset, $limit );
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
        $normalized = (string) str_replace( ' ', '', (string) $normalized );
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

        if ( class_exists( 'DPS_Loyalty_Achievements' ) ) {
            DPS_Loyalty_Achievements::evaluate_achievements_for_client( $client_id );
        }
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
