<?php
/**
 * Plugin Name:       Desi Pet Shower – Base
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Plugin básico para cadastro de clientes, pets e agendamentos de banho e tosa. Este é o núcleo que pode ser expandido por add-ons.
 * Version:           1.0.1
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       desi-pet-shower
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'DPS_BASE_VERSION', '1.0.1' );
define( 'DPS_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'DPS_BASE_PETS_PER_PAGE', 20 );

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

// Carrega classe de frontend
require_once DPS_BASE_DIR . 'includes/class-dps-base-frontend.php';

if ( ! function_exists( 'dps_load_textdomain' ) ) {
    /**
     * Carrega o text domain do plugin base.
     */
    function dps_load_textdomain() {
        load_plugin_textdomain( 'desi-pet-shower', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}
add_action( 'plugins_loaded', 'dps_load_textdomain' );

/**
 * Classe principal do plugin
 */
class DPS_Base_Plugin {

    public function __construct() {
        // Registra tipos de posts personalizados
        add_action( 'init', [ $this, 'register_post_types' ] );
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
        add_action( 'wp_ajax_dps_get_available_times', [ 'DPS_Base_Frontend', 'ajax_get_available_times' ] );
        add_action( 'wp_ajax_nopriv_dps_get_available_times', [ 'DPS_Base_Frontend', 'ajax_get_available_times' ] );
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
            'show_ui'            => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
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
            'show_ui'            => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
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
            'show_ui'            => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => [ 'title' ],
            'has_archive'        => false,
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

        $should_enqueue = ( $post instanceof WP_Post ) && ( has_shortcode( $post->post_content, 'dps_base' ) || has_shortcode( $post->post_content, 'dps_configuracoes' ) );
        $should_enqueue = apply_filters( 'dps_base_should_enqueue_assets', $should_enqueue, $post );

        if ( ! $should_enqueue ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'dps-base-style', DPS_BASE_URL . 'assets/css/dps-base.css', [], DPS_BASE_VERSION );
        // JS
        wp_enqueue_script( 'dps-base-script', DPS_BASE_URL . 'assets/js/dps-base.js', [ 'jquery' ], DPS_BASE_VERSION, true );
        wp_enqueue_script( 'dps-appointment-form', DPS_BASE_URL . 'assets/js/dps-appointment-form.js', [ 'jquery' ], DPS_BASE_VERSION, true );
        
        // Localização para o script de agendamento
        wp_localize_script( 'dps-appointment-form', 'dpsAppointmentData', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dps_action' ),
            'appointmentId' => isset( $_GET['dps_edit'] ) && isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0,
            'l10n' => [
                'loadingTimes'   => __( 'Carregando horários...', 'desi-pet-shower' ),
                'selectTime'     => __( 'Selecione um horário', 'desi-pet-shower' ),
                'noTimes'        => __( 'Nenhum horário disponível para esta data', 'desi-pet-shower' ),
                'selectClient'   => __( 'Selecione um cliente', 'desi-pet-shower' ),
                'selectPet'      => __( 'Selecione pelo menos um pet', 'desi-pet-shower' ),
                'selectDate'     => __( 'Selecione uma data', 'desi-pet-shower' ),
                'selectTimeSlot' => __( 'Selecione um horário', 'desi-pet-shower' ),
                'pastDate'       => __( 'A data não pode ser anterior a hoje', 'desi-pet-shower' ),
                'saving'         => __( 'Salvando...', 'desi-pet-shower' ),
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

        // Verifica se estamos em uma página DPS ou se o hook contém 'dps'
        $is_dps_page = in_array( $hook, $dps_admin_pages, true ) || strpos( $hook, 'dps' ) !== false;

        if ( ! $is_dps_page ) {
            return;
        }

        wp_enqueue_style( 'dps-admin-style', DPS_BASE_URL . 'assets/css/dps-admin.css', [], DPS_BASE_VERSION );
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
        $cached     = get_transient( $cache_key );

        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
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

        set_transient( $cache_key, $response, 15 * MINUTE_IN_SECONDS );
        $this->remember_pets_cache_key( $cache_key );

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
        if ( isset( $_POST['dps_action'] ) ) {
            // Processa ações de formulário
            DPS_Base_Frontend::handle_request();
        }
        // Processa exclusões via GET
        if ( isset( $_GET['dps_delete'] ) && isset( $_GET['id'] ) ) {
            DPS_Base_Frontend::handle_delete();
        }
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

// Instancia o plugin
new DPS_Base_Plugin();
