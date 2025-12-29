<?php
/**
 * Plugin Name:       DPS by PRObst – Base
 * Plugin URI:        https://www.probst.pro
 * Description:       Sistema completo de gestão para pet shops. Gerencie clientes, pets e agendamentos de forma simples e eficiente. Expanda com add-ons para controle financeiro, comunicações, portal do cliente e mais.
 * Version:           1.1.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       desi-pet-shower
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'DPS_BASE_VERSION', '1.1.0' );
define( 'DPS_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'DPS_BASE_PETS_PER_PAGE', 20 );

/**
 * Verifica se o cache do DPS está desabilitado.
 * 
 * Para desabilitar o cache, defina a constante DPS_DISABLE_CACHE como true
 * no wp-config.php:
 * 
 *     define( 'DPS_DISABLE_CACHE', true );
 * 
 * @since 1.0.2
 * @return bool True se o cache está desabilitado, false caso contrário.
 */
function dps_is_cache_disabled() {
    return defined( 'DPS_DISABLE_CACHE' ) && DPS_DISABLE_CACHE;
}

// Funções auxiliares de template
require_once DPS_BASE_DIR . 'includes/template-functions.php';
// Helper para registro de CPTs
require_once DPS_BASE_DIR . 'includes/class-dps-cpt-helper.php';
// Logger e UI de logs
require_once DPS_BASE_DIR . 'includes/class-dps-logger.php';
require_once DPS_BASE_DIR . 'includes/class-dps-logs-admin-page.php';
// Helper classes para refatoração
require_once DPS_BASE_DIR . 'includes/class-dps-money-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-url-builder.php';
require_once DPS_BASE_DIR . 'includes/class-dps-query-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-request-validator.php';
require_once DPS_BASE_DIR . 'includes/class-dps-message-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-phone-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-whatsapp-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-admin-tabs-helper.php';
require_once DPS_BASE_DIR . 'includes/class-dps-shortcodes-admin-page.php';
require_once DPS_BASE_DIR . 'includes/class-dps-dashboard.php';

// Hubs centralizados (Fase 2 - Reorganização de Menus)
require_once DPS_BASE_DIR . 'includes/class-dps-integrations-hub.php';
require_once DPS_BASE_DIR . 'includes/class-dps-system-hub.php';
require_once DPS_BASE_DIR . 'includes/class-dps-tools-hub.php';

// Gerenciador de Add-ons
require_once DPS_BASE_DIR . 'includes/class-dps-addon-manager.php';

// Carrega classe de frontend
require_once DPS_BASE_DIR . 'includes/class-dps-base-frontend.php';

if ( ! function_exists( 'dps_load_textdomain' ) ) {
    /**
     * Carrega o text domain do plugin base.
     * 
     * Usa hook 'init' conforme WordPress 6.7+ para garantir que strings
     * traduzíveis sejam carregadas corretamente antes de qualquer uso.
     */
    function dps_load_textdomain() {
        load_plugin_textdomain( 'desi-pet-shower', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}
add_action( 'init', 'dps_load_textdomain', 1 );

/**
 * Classe principal do plugin
 */
class DPS_Base_Plugin {

    public function __construct() {
        // Registra tipos de posts personalizados
        add_action( 'init', [ $this, 'register_post_types' ] );
        // Registra menu admin unificado
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        // Enfileira scripts e estilos
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        // Manipula ações de formulário (salvar e excluir)
        add_action( 'init', [ $this, 'maybe_handle_request' ] );
        // Impede exclusão de clientes/pets com agendamentos vinculados
        add_action( 'before_delete_post', [ $this, 'prevent_orphaned_appointments' ] );
        // Oculta registros marcados como exclusão lógica
        add_action( 'pre_get_posts', [ $this, 'filter_soft_deleted_posts' ] );
        // Shortcodes para exibir a aplicação no frontend
        add_shortcode( 'dps_base', [ 'DPS_Base_Frontend', 'render_app' ] );
        add_shortcode( 'dps_configuracoes', [ 'DPS_Base_Frontend', 'render_settings' ] );

        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'save_post_dps_pet', [ $this, 'clear_pets_cache' ], 10, 2 );
        add_action( 'delete_post', [ $this, 'maybe_clear_pets_cache_on_delete' ] );
        
        // AJAX handlers para funcionalidades do formulário de agendamento
        add_action( 'wp_ajax_dps_render_appointment_form', [ 'DPS_Base_Frontend', 'ajax_render_appointment_form' ] );
        add_action( 'wp_ajax_dps_modal_save_appointment', [ 'DPS_Base_Frontend', 'ajax_save_appointment_modal' ] );
        add_action( 'wp_ajax_dps_get_available_times', [ 'DPS_Base_Frontend', 'ajax_get_available_times' ] );
        add_action( 'wp_ajax_nopriv_dps_get_available_times', [ 'DPS_Base_Frontend', 'ajax_get_available_times' ] );
        
        // Handler para exportação de clientes
        add_action( 'admin_post_dps_export_clients', [ $this, 'export_clients_csv' ] );
    }

    /**
     * Retorna a versão do asset baseada no timestamp de modificação do arquivo.
     *
     * Usa `filemtime` para forçar atualização de CSS/JS quando o conteúdo muda,
     * garantindo que o layout moderno do painel não fique preso em cache antigo.
     *
     * @since 1.0.4
     * @param string $relative_path Caminho relativo dentro do plugin (sem barra inicial).
     * @return string Versão calculada ou fallback para constante do plugin.
     */
    private static function get_asset_version( $relative_path ) {
        $file_path = DPS_BASE_DIR . ltrim( $relative_path, '/' );
        $mtime     = file_exists( $file_path ) ? filemtime( $file_path ) : false;

        return $mtime ? (string) $mtime : DPS_BASE_VERSION;
    }

    /**
     * Executa rotinas de ativação: criação de capabilities e do papel de recepção.
     */
    public static function activate() {
        $capabilities = [
            'dps_manage_appointments',
            'dps_manage_clients',
            'dps_manage_pets',
        ];

        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            foreach ( $capabilities as $capability ) {
                $admin_role->add_cap( $capability );
            }
        }

        $reception_caps = array_fill_keys( $capabilities, true );
        $existing_reception = get_role( 'dps_reception' );
        if ( $existing_reception ) {
            foreach ( $capabilities as $capability ) {
                $existing_reception->add_cap( $capability );
            }
        } else {
            add_role(
                'dps_reception',
                __( 'Recepção DPS', 'desi-pet-shower' ),
                $reception_caps
            );
        }

        add_option( 'dps_logger_min_level', DPS_Logger::LEVEL_INFO );
        DPS_Logger::maybe_install();
    }

    /**
     * Rotina de desativação do plugin.
     *
     * Limpa transients de cache e cron jobs agendados para evitar
     * dados órfãos no banco de dados.
     */
    public static function deactivate() {
        // Limpa transients de cache de pets
        $keys = get_option( 'dps_pets_cache_keys', [] );
        if ( is_array( $keys ) ) {
            foreach ( $keys as $key ) {
                delete_transient( $key );
            }
        }
        delete_option( 'dps_pets_cache_keys' );

        // Limpa scheduled events se houver
        wp_clear_scheduled_hook( 'dps_daily_cleanup' );
    }

    /**
     * Registra o menu admin principal unificado do DPS.
     * Este menu centraliza todos os submenus de configuração dos add-ons.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'DPS by PRObst', 'desi-pet-shower' ),
            __( 'DPS by PRObst', 'desi-pet-shower' ),
            'manage_options',
            'desi-pet-shower',
            [ $this, 'render_main_settings_page' ],
            'dashicons-pets',
            56
        );
    }

    /**
     * Renderiza a página principal de configurações do DPS.
     * Por enquanto, exibe apenas uma mensagem de boas-vindas.
     */
    public function render_main_settings_page() {
        // Usa o novo painel central (dashboard)
        DPS_Dashboard::render();
    }

    /**
     * Registra tipos de post personalizados: clientes, pets e agendamentos
     */
    public function register_post_types() {
        // Clientes (tutores)
        $labels = [
            'name'               => __( 'Clientes', 'desi-pet-shower' ),
            'singular_name'      => __( 'Cliente', 'desi-pet-shower' ),
            'add_new'            => __( 'Adicionar Novo', 'desi-pet-shower' ),
            'add_new_item'       => __( 'Adicionar Novo Cliente', 'desi-pet-shower' ),
            'edit_item'          => __( 'Editar Cliente', 'desi-pet-shower' ),
            'new_item'           => __( 'Novo Cliente', 'desi-pet-shower' ),
            'all_items'          => __( 'Todos os Clientes', 'desi-pet-shower' ),
            'view_item'          => __( 'Ver Cliente', 'desi-pet-shower' ),
            'search_items'       => __( 'Buscar Clientes', 'desi-pet-shower' ),
            'not_found'          => __( 'Nenhum cliente encontrado', 'desi-pet-shower' ),
            'menu_name'          => __( 'Clientes', 'desi-pet-shower' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'dps_client',
            'map_meta_cap'       => false,
            'capabilities'       => [
                'edit_post'              => 'dps_manage_clients',
                'read_post'              => 'dps_manage_clients',
                'delete_post'            => 'dps_manage_clients',
                'edit_posts'             => 'dps_manage_clients',
                'edit_others_posts'      => 'dps_manage_clients',
                'publish_posts'          => 'dps_manage_clients',
                'read_private_posts'     => 'dps_manage_clients',
                'delete_posts'           => 'dps_manage_clients',
                'delete_private_posts'   => 'dps_manage_clients',
                'delete_published_posts' => 'dps_manage_clients',
                'delete_others_posts'    => 'dps_manage_clients',
            ],
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'menu_icon'          => 'dashicons-groups',
        ];
        register_post_type( 'dps_cliente', $args );

        // Pets
        $labels = [
            'name'               => __( 'Pets', 'desi-pet-shower' ),
            'singular_name'      => __( 'Pet', 'desi-pet-shower' ),
            'add_new'            => __( 'Adicionar Novo', 'desi-pet-shower' ),
            'add_new_item'       => __( 'Adicionar Novo Pet', 'desi-pet-shower' ),
            'edit_item'          => __( 'Editar Pet', 'desi-pet-shower' ),
            'new_item'           => __( 'Novo Pet', 'desi-pet-shower' ),
            'all_items'          => __( 'Todos os Pets', 'desi-pet-shower' ),
            'view_item'          => __( 'Ver Pet', 'desi-pet-shower' ),
            'search_items'       => __( 'Buscar Pets', 'desi-pet-shower' ),
            'not_found'          => __( 'Nenhum pet encontrado', 'desi-pet-shower' ),
            'menu_name'          => __( 'Pets', 'desi-pet-shower' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'dps_pet',
            'map_meta_cap'       => false,
            'capabilities'       => [
                'edit_post'              => 'dps_manage_pets',
                'read_post'              => 'dps_manage_pets',
                'delete_post'            => 'dps_manage_pets',
                'edit_posts'             => 'dps_manage_pets',
                'edit_others_posts'      => 'dps_manage_pets',
                'publish_posts'          => 'dps_manage_pets',
                'read_private_posts'     => 'dps_manage_pets',
                'delete_posts'           => 'dps_manage_pets',
                'delete_private_posts'   => 'dps_manage_pets',
                'delete_published_posts' => 'dps_manage_pets',
                'delete_others_posts'    => 'dps_manage_pets',
            ],
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'menu_icon'          => 'dashicons-pets',
        ];
        register_post_type( 'dps_pet', $args );

        // Agendamentos
        $labels = [
            'name'               => __( 'Agendamentos', 'desi-pet-shower' ),
            'singular_name'      => __( 'Agendamento', 'desi-pet-shower' ),
            'add_new_item'       => __( 'Adicionar Novo Agendamento', 'desi-pet-shower' ),
            'edit_item'          => __( 'Editar Agendamento', 'desi-pet-shower' ),
            'new_item'           => __( 'Novo Agendamento', 'desi-pet-shower' ),
            'all_items'          => __( 'Todos os Agendamentos', 'desi-pet-shower' ),
            'view_item'          => __( 'Ver Agendamento', 'desi-pet-shower' ),
            'search_items'       => __( 'Buscar Agendamentos', 'desi-pet-shower' ),
            'not_found'          => __( 'Nenhum agendamento encontrado', 'desi-pet-shower' ),
            'menu_name'          => __( 'Agendamentos', 'desi-pet-shower' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'dps_appointment',
            'map_meta_cap'       => false,
            'capabilities'       => [
                'edit_post'              => 'dps_manage_appointments',
                'read_post'              => 'dps_manage_appointments',
                'delete_post'            => 'dps_manage_appointments',
                'edit_posts'             => 'dps_manage_appointments',
                'edit_others_posts'      => 'dps_manage_appointments',
                'publish_posts'          => 'dps_manage_appointments',
                'read_private_posts'     => 'dps_manage_appointments',
                'delete_posts'           => 'dps_manage_appointments',
                'delete_private_posts'   => 'dps_manage_appointments',
                'delete_published_posts' => 'dps_manage_appointments',
                'delete_others_posts'    => 'dps_manage_appointments',
            ],
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'menu_icon'          => 'dashicons-calendar-alt',
        ];
        register_post_type( 'dps_agendamento', $args );
    }

    /**
     * Enfileira scripts e estilos para o frontend
     */
    public function enqueue_assets() {
        global $post;

        if ( is_admin() ) {
            return;
        }

        $content = ( $post instanceof WP_Post ) ? (string) $post->post_content : '';
        $should_enqueue = ( $post instanceof WP_Post ) && ( has_shortcode( $content, 'dps_base' ) || has_shortcode( $content, 'dps_configuracoes' ) );
        $should_enqueue = apply_filters( 'dps_base_should_enqueue_assets', $should_enqueue, $post );

        if ( ! $should_enqueue ) {
            return;
        }

        self::enqueue_frontend_assets();
    }

    /**
     * Enfileira os assets necessários para o painel no frontend.
     *
     * Protege contra múltiplas chamadas no mesmo request para evitar
     * duplicação de localizações de scripts e enfileiramento redundante.
     */
    public static function enqueue_frontend_assets() {
        static $enqueued = false;

        if ( $enqueued ) {
            return;
        }

        $enqueued = true;

        // CSS
        wp_enqueue_style( 'dps-base-style', DPS_BASE_URL . 'assets/css/dps-base.css', [], self::get_asset_version( 'assets/css/dps-base.css' ) );
        wp_enqueue_style( 'dps-form-validation', DPS_BASE_URL . 'assets/css/dps-form-validation.css', [], self::get_asset_version( 'assets/css/dps-form-validation.css' ) );
        // JS
        wp_enqueue_script( 'dps-base-script', DPS_BASE_URL . 'assets/js/dps-base.js', [ 'jquery' ], self::get_asset_version( 'assets/js/dps-base.js' ), true );
        wp_enqueue_script( 'dps-appointment-form', DPS_BASE_URL . 'assets/js/dps-appointment-form.js', [ 'jquery' ], self::get_asset_version( 'assets/js/dps-appointment-form.js' ), true );
        wp_enqueue_script( 'dps-form-validation', DPS_BASE_URL . 'assets/js/dps-form-validation.js', [], self::get_asset_version( 'assets/js/dps-form-validation.js' ), true );
        
        // Localização para o script de agendamento
        wp_localize_script( 'dps-appointment-form', 'dpsAppointmentData', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dps_action' ),
            'appointmentId' => isset( $_GET['dps_edit'] ) && isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0,
            'l10n' => [
                'loadingTimes'    => __( 'Carregando horários...', 'desi-pet-shower' ),
                'selectTime'      => __( 'Selecione um horário', 'desi-pet-shower' ),
                'noTimes'         => __( 'Nenhum horário disponível para esta data', 'desi-pet-shower' ),
                'selectClient'    => __( 'Selecione um cliente', 'desi-pet-shower' ),
                'selectPet'       => __( 'Selecione pelo menos um pet', 'desi-pet-shower' ),
                'selectDate'      => __( 'Selecione uma data', 'desi-pet-shower' ),
                'selectTimeSlot'  => __( 'Selecione um horário', 'desi-pet-shower' ),
                'pastDate'        => __( 'A data não pode ser anterior a hoje', 'desi-pet-shower' ),
                'saving'          => __( 'Salvando...', 'desi-pet-shower' ),
                'loadError'       => __( 'Erro ao carregar horários', 'desi-pet-shower' ),
                'formErrorsTitle' => __( 'Por favor, corrija os seguintes erros:', 'desi-pet-shower' ),
            ],
        ] );
        
        wp_localize_script( 'dps-base-script', 'dpsBaseData', [
            'restUrl'     => esc_url_raw( rest_url( 'dps/v1/pets' ) ),
            'restNonce'   => wp_create_nonce( 'wp_rest' ),
            'petsPerPage' => DPS_BASE_PETS_PER_PAGE,
        ] );
        wp_localize_script( 'dps-base-script', 'dpsBaseL10n', [
            'summarySingle'     => __( 'Pet selecionado: %s', 'desi-pet-shower' ),
            'summaryMultiple'   => __( '%d pets selecionados: %s', 'desi-pet-shower' ),
            'selectPetWarning'  => __( 'Selecione pelo menos um pet para o agendamento.', 'desi-pet-shower' ),
            'historySummary'    => __( '%1$s atendimentos filtrados. Total estimado: R$ %2$s.', 'desi-pet-shower' ),
            'historyEmpty'      => __( 'Nenhum atendimento corresponde aos filtros aplicados.', 'desi-pet-shower' ),
            'historyExportEmpty'=> __( 'Nenhum atendimento visível para exportar.', 'desi-pet-shower' ),
            'historyExportFileName' => __( 'historico-atendimentos-%s.csv', 'desi-pet-shower' ),
            'pendingTitle'          => __( 'Pagamentos em aberto para %s.', 'desi-pet-shower' ),
            'pendingGenericTitle'   => __( 'Este cliente possui pagamentos pendentes.', 'desi-pet-shower' ),
            'pendingItem'           => __( '%1$s: R$ %2$s – %3$s', 'desi-pet-shower' ),
            'pendingItemNoDate'     => __( 'R$ %1$s – %2$s', 'desi-pet-shower' ),
            'petSearchPlaceholder'  => __( 'Buscar pets por nome, tutor ou raça', 'desi-pet-shower' ),
            'petLoadMore'           => __( 'Carregar mais pets', 'desi-pet-shower' ),
            'petLoading'            => __( 'Carregando pets...', 'desi-pet-shower' ),
            'petNoResults'          => __( 'Nenhum pet encontrado para o filtro atual.', 'desi-pet-shower' ),
            'savingText'            => __( 'Salvando...', 'desi-pet-shower' ),
            'selectedSingle'        => __( 'selecionado', 'desi-pet-shower' ),
            'selectedMultiple'      => __( 'selecionados', 'desi-pet-shower' ),
        ] );
        wp_localize_script( 'dps-form-validation', 'dpsFormL10n', [
            'generic'     => [
                'formErrorsTitle' => __( 'Por favor, corrija os campos destacados:', 'desi-pet-shower' ),
            ],
            'client'      => [
                'nameRequired'  => __( 'O campo Nome é obrigatório.', 'desi-pet-shower' ),
                'phoneRequired' => __( 'O campo Telefone / WhatsApp é obrigatório.', 'desi-pet-shower' ),
            ],
            'pet'         => [
                'nameRequired'    => __( 'Informe o nome do pet.', 'desi-pet-shower' ),
                'ownerRequired'   => __( 'Selecione o cliente (tutor) do pet.', 'desi-pet-shower' ),
                'speciesRequired' => __( 'Selecione a espécie do pet.', 'desi-pet-shower' ),
                'sexRequired'     => __( 'Selecione o sexo do pet.', 'desi-pet-shower' ),
                'sizeRequired'    => __( 'Selecione o porte do pet.', 'desi-pet-shower' ),
            ],
            'appointment' => [
                'clientRequired'    => __( 'Selecione um cliente para o agendamento.', 'desi-pet-shower' ),
                'petRequired'       => __( 'Selecione pelo menos um pet.', 'desi-pet-shower' ),
                'dateRequired'      => __( 'Selecione uma data para o agendamento.', 'desi-pet-shower' ),
                'timeRequired'      => __( 'Selecione um horário para o agendamento.', 'desi-pet-shower' ),
                'frequencyRequired' => __( 'Selecione a frequência da assinatura.', 'desi-pet-shower' ),
                'errorTitle'        => __( 'Corrija os erros para continuar.', 'desi-pet-shower' ),
            ],
        ] );
    }

    /**
     * Enfileira estilos para páginas administrativas do DPS
     */
    public function enqueue_admin_assets( $hook ) {
        // Lista de páginas DPS no admin onde o CSS deve ser carregado
        $dps_admin_pages = [
            'toplevel_page_dps-logs',
            'dps-logs',
        ];

        // Cast para string para compatibilidade com PHP 8.4+
        $hook = (string) $hook;

        // Verifica se estamos em uma página DPS ou se o hook contém 'dps'
        $is_dps_page = in_array( $hook, $dps_admin_pages, true ) || strpos( $hook, 'dps' ) !== false;

        if ( ! $is_dps_page ) {
            return;
        }

        wp_enqueue_style( 'dps-admin-style', DPS_BASE_URL . 'assets/css/dps-admin.css', [], self::get_asset_version( 'assets/css/dps-admin.css' ) );

        if ( strpos( $hook, 'dps-shortcodes' ) !== false ) {
            wp_enqueue_script(
                'dps-shortcodes-admin',
                DPS_BASE_URL . 'assets/js/dps-shortcodes.js',
                [],
                self::get_asset_version( 'assets/js/dps-shortcodes.js' ),
                true
            );
        }
    }

    /**
     * Registra rotas REST para carregamento incremental de pets.
     */
    public function register_rest_routes() {
        register_rest_route(
            'dps/v1',
            '/pets',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_list_pets' ],
                'permission_callback' => [ $this, 'rest_permissions' ],
                'args'                => [
                    'page'   => [
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'search' => [
                        'type'              => 'string',
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'owner'  => [
                        'type'              => 'integer',
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    /**
     * Verifica permissões para acesso às rotas REST do plugin.
     */
    public function rest_permissions() {
        return current_user_can( 'dps_manage_pets' );
    }

    /**
     * Retorna lista paginada de pets para uso no frontend.
     *
     * @param WP_REST_Request $request Requisição atual.
     *
     * @return WP_REST_Response
     */
    public function rest_list_pets( WP_REST_Request $request ) {
        $page       = max( 1, (int) $request->get_param( 'page' ) );
        $search     = $request->get_param( 'search' );
        $owner_id   = (int) $request->get_param( 'owner' );
        $cache_args = [
            'page'   => $page,
            'search' => $search,
            'owner'  => $owner_id,
        ];
        $cache_key  = $this->build_pets_cache_key( $cache_args );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return rest_ensure_response( $cached );
            }
        }

        $query_args = [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => DPS_BASE_PETS_PER_PAGE,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'paged'          => $page,
        ];

        if ( $search ) {
            $query_args['s'] = $search;
        }

        if ( $owner_id ) {
            $query_args['meta_query'] = [
                [
                    'key'   => 'owner_id',
                    'value' => $owner_id,
                ],
            ];
        }

        $pets_query = new WP_Query( $query_args );
        $pets_data  = [];

        if ( $pets_query->have_posts() ) {
            foreach ( $pets_query->posts as $pet ) {
                $owner_meta = get_post_meta( $pet->ID, 'owner_id', true );
                $owner_name = $owner_meta ? get_the_title( (int) $owner_meta ) : '';
                $pets_data[] = [
                    'id'         => $pet->ID,
                    'name'       => $pet->post_title,
                    'owner_id'   => $owner_meta ? (string) $owner_meta : '',
                    'owner_name' => $owner_name,
                    'size'       => get_post_meta( $pet->ID, 'pet_size', true ),
                    'breed'      => get_post_meta( $pet->ID, 'pet_breed', true ),
                ];
            }
        }

        $response = [
            'items'       => $pets_data,
            'page'        => $page,
            'total_pages' => (int) max( 1, $pets_query->max_num_pages ),
            'total'       => (int) $pets_query->found_posts,
        ];

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $response, 15 * MINUTE_IN_SECONDS );
            $this->remember_pets_cache_key( $cache_key );
        }

        return rest_ensure_response( $response );
    }

    /**
     * Monta uma chave única para o cache de pets.
     *
     * @param array $args Argumentos da consulta.
     *
     * @return string
     */
    private function build_pets_cache_key( array $args ) {
        ksort( $args );
        return 'dps_pets_' . md5( wp_json_encode( $args ) );
    }

    /**
     * Armazena a referência das chaves de cache utilizadas.
     *
     * @param string $key Chave gerada para o cache.
     */
    private function remember_pets_cache_key( $key ) {
        $keys = get_option( 'dps_pets_cache_keys', [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }
        if ( ! in_array( $key, $keys, true ) ) {
            $keys[] = $key;
            update_option( 'dps_pets_cache_keys', $keys, false );
        }
    }

    /**
     * Limpa todos os caches relacionados à listagem de pets.
     */
    public function clear_pets_cache() {
        $keys = get_option( 'dps_pets_cache_keys', [] );
        if ( is_array( $keys ) ) {
            foreach ( $keys as $key ) {
                delete_transient( $key );
            }
        }
        delete_option( 'dps_pets_cache_keys' );
    }

    /**
     * Limpa o cache quando um pet é removido.
     *
     * @param int $post_id ID do post removido.
     */
    public function maybe_clear_pets_cache_on_delete( $post_id ) {
        if ( 'dps_pet' !== get_post_type( $post_id ) ) {
            return;
        }
        $this->clear_pets_cache();
    }

    /**
     * Impede exclusão física de clientes ou pets que possuem agendamentos.
     * Permite exclusão lógica caso o filtro dps_enable_soft_delete seja verdadeiro.
     *
     * @param int $post_id ID do post prestes a ser removido.
     */
    public function prevent_orphaned_appointments( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( 'dps_cliente' !== $post_type && 'dps_pet' !== $post_type ) {
            return;
        }

        if ( ! $this->has_related_appointments( $post_id, $post_type ) ) {
            return;
        }

        if ( apply_filters( 'dps_enable_soft_delete', false, $post_id, $post_type ) ) {
            update_post_meta( $post_id, 'dps_mark_as_deleted', current_time( 'mysql' ) );

            wp_die(
                esc_html__( 'Este registro foi marcado como removido e não aparecerá mais na listagem padrão.', 'desi-pet-shower' ),
                '',
                [ 'back_link' => true ]
            );
        }

        $message = ( 'dps_cliente' === $post_type )
            ? __( 'Este cliente possui agendamentos vinculados e não pode ser excluído.', 'desi-pet-shower' )
            : __( 'Este pet possui agendamentos vinculados e não pode ser excluído.', 'desi-pet-shower' );

        wp_die( esc_html( $message ), '', [ 'back_link' => true ] );
    }

    /**
     * Verifica se há agendamentos associados ao cliente ou pet informado.
     *
     * @param int    $post_id   ID do post a verificar.
     * @param string $post_type Tipo de post (dps_cliente ou dps_pet).
     *
     * @return bool
     */
    private function has_related_appointments( $post_id, $post_type ) {
        if ( 'dps_cliente' === $post_type ) {
            $meta_query = [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => (int) $post_id,
                    'compare' => '=',
                ],
            ];
        } else {
            $meta_query = [
                'relation' => 'OR',
                [
                    'key'     => 'appointment_pet_id',
                    'value'   => (int) $post_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'appointment_pet_ids',
                    'value'   => '"' . (int) $post_id . '"',
                    'compare' => 'LIKE',
                ],
            ];
        }

        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ] );

        return ! empty( $appointments );
    }

    /**
     * Remove da listagem clientes/pets marcados como exclusão lógica.
     * Para incluir registros marcados, defina a query var dps_include_deleted como verdadeira.
     *
     * @param WP_Query $query Consulta sendo construída.
     */
    public function filter_soft_deleted_posts( $query ) {
        if ( ! $query instanceof WP_Query || $query->get( 'dps_include_deleted' ) ) {
            return;
        }

        $post_type     = $query->get( 'post_type' );
        $target_types  = [ 'dps_cliente', 'dps_pet' ];
        $should_filter = false;

        if ( is_array( $post_type ) ) {
            $should_filter = ! empty( array_intersect( $target_types, $post_type ) );
        } elseif ( in_array( $post_type, $target_types, true ) ) {
            $should_filter = true;
        }

        if ( ! $should_filter ) {
            return;
        }

        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }

        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => 'dps_mark_as_deleted',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'dps_mark_as_deleted',
                'value'   => '',
                'compare' => '=',
            ],
        ];

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Verifica se há solicitações de salvamento ou exclusão e delega para a classe de frontend
     */
    public function maybe_handle_request() {
        // Processa logout via GET
        if ( isset( $_GET['dps_logout'] ) ) {
            DPS_Base_Frontend::handle_logout();
        }
        // Processa ações de formulário via POST
        if ( isset( $_POST['dps_action'] ) ) {
            DPS_Base_Frontend::handle_request();
        }
        // Processa exclusões via GET
        if ( isset( $_GET['dps_delete'] ) && isset( $_GET['id'] ) ) {
            DPS_Base_Frontend::handle_delete();
        }
    }

    /**
     * Exporta lista de clientes para arquivo CSV.
     *
     * Gera um arquivo CSV com todos os clientes cadastrados, incluindo
     * nome, telefone, email, CPF e cidade. O arquivo é baixado diretamente
     * pelo navegador.
     *
     * @since 1.0.3
     * @return void
     */
    public function export_clients_csv() {
        // Verifica permissões.
        if ( ! current_user_can( 'dps_manage_clients' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para exportar clientes.', 'desi-pet-shower' ) );
        }

        // Verifica nonce.
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_export_clients' ) ) {
            wp_die( esc_html__( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }

        // Obtém todos os clientes.
        $clients = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );

        // Define headers para download do CSV.
        $filename = 'clientes-dps-' . wp_date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Abre stream de saída.
        $output = fopen( 'php://output', 'w' );

        // Adiciona BOM para Excel reconhecer UTF-8.
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Cabeçalhos do CSV.
        // Nota: Usa ponto-e-vírgula como delimitador para compatibilidade com Excel
        // em países que usam vírgula como separador decimal (Brasil, Europa).
        fputcsv(
            $output,
            [
                __( 'Nome', 'desi-pet-shower' ),
                __( 'Telefone', 'desi-pet-shower' ),
                __( 'Email', 'desi-pet-shower' ),
                __( 'CPF', 'desi-pet-shower' ),
                __( 'Cidade', 'desi-pet-shower' ),
                __( 'Endereço', 'desi-pet-shower' ),
                __( 'Data de Nascimento', 'desi-pet-shower' ),
                __( 'Instagram', 'desi-pet-shower' ),
            ],
            ';'
        );

        // Dados dos clientes.
        foreach ( $clients as $client ) {
            fputcsv(
                $output,
                [
                    $client->post_title,
                    get_post_meta( $client->ID, 'client_phone', true ),
                    get_post_meta( $client->ID, 'client_email', true ),
                    get_post_meta( $client->ID, 'client_cpf', true ),
                    get_post_meta( $client->ID, 'client_city', true ),
                    get_post_meta( $client->ID, 'client_address', true ),
                    get_post_meta( $client->ID, 'client_birth', true ),
                    get_post_meta( $client->ID, 'client_instagram', true ),
                ],
                ';'
            );
        }

        fclose( $output );
        exit;
    }
}

if ( ! function_exists( 'dps_base_maybe_install_logger_table' ) ) {
    /**
     * Garante atualização de tabela de logs quando a flag de versão não coincide.
     */
    function dps_base_maybe_install_logger_table() {
        DPS_Logger::maybe_install();
    }
}
add_action( 'plugins_loaded', 'dps_base_maybe_install_logger_table' );

// Registra hook de ativação para criação de capabilities e papéis padrão
register_activation_hook( __FILE__, [ 'DPS_Base_Plugin', 'activate' ] );

// Registra hook de desativação para limpeza de transients e cron jobs
register_deactivation_hook( __FILE__, [ 'DPS_Base_Plugin', 'deactivate' ] );

/**
 * Inicializa a classe principal do plugin após o carregamento do text domain.
 */
function dps_base_init_plugin() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new DPS_Base_Plugin();
    }
}
add_action( 'init', 'dps_base_init_plugin', 5 );

// Inicializa os hubs centralizados (Fase 2 - Reorganização de Menus)
add_action( 'init', function() {
    if ( class_exists( 'DPS_Integrations_Hub' ) ) {
        DPS_Integrations_Hub::get_instance();
    }
    if ( class_exists( 'DPS_System_Hub' ) ) {
        DPS_System_Hub::get_instance();
    }
    if ( class_exists( 'DPS_Tools_Hub' ) ) {
        DPS_Tools_Hub::get_instance();
    }
}, 5 );
