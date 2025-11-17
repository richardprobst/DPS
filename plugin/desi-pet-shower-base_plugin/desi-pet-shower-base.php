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

// Funções auxiliares de template
require_once DPS_BASE_DIR . 'includes/template-functions.php';
// Logger e UI de logs
require_once DPS_BASE_DIR . 'includes/class-dps-logger.php';
require_once DPS_BASE_DIR . 'includes/class-dps-logs-admin-page.php';

// Carrega classe de frontend
require_once DPS_BASE_DIR . 'includes/class-dps-base-frontend.php';

/**
 * Carrega o text domain do plugin base.
 */
function dps_load_textdomain() {
    load_plugin_textdomain( 'desi-pet-shower', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
        // Manipula ações de formulário (salvar e excluir)
        add_action( 'init', [ $this, 'maybe_handle_request' ] );
        // Impede exclusão de clientes/pets com agendamentos vinculados
        add_action( 'before_delete_post', [ $this, 'prevent_orphaned_appointments' ] );
        // Oculta registros marcados como exclusão lógica
        add_action( 'pre_get_posts', [ $this, 'filter_soft_deleted_posts' ] );
        // Shortcodes para exibir a aplicação no frontend
        add_shortcode( 'dps_base', [ 'DPS_Base_Frontend', 'render_app' ] );
        add_shortcode( 'dps_configuracoes', [ 'DPS_Base_Frontend', 'render_settings' ] );
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
        DPS_Logger::create_table();
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
        // CSS
        wp_enqueue_style( 'dps-base-style', DPS_BASE_URL . 'assets/css/dps-base.css', [], DPS_BASE_VERSION );
        // JS
        wp_enqueue_script( 'dps-base-script', DPS_BASE_URL . 'assets/js/dps-base.js', [ 'jquery' ], DPS_BASE_VERSION, true );
        // Passa dados para o script (lista de pets para filtragem dinâmica)
        $pets_data = [];
        $pets_query = new WP_Query( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );
        if ( $pets_query->have_posts() ) {
            foreach ( $pets_query->posts as $pet_id ) {
                $owner_id = get_post_meta( $pet_id, 'owner_id', true );
                $pets_data[] = [
                    'id'       => $pet_id,
                    'name'     => get_the_title( $pet_id ),
                    'owner_id' => $owner_id ? strval( $owner_id ) : '',
                ];
            }
        }
        wp_localize_script( 'dps-base-script', 'DPSBase', [
            'pets' => $pets_data,
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
        ] );
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

// Registra hook de ativação para criação de capabilities e papéis padrão
register_activation_hook( __FILE__, [ 'DPS_Base_Plugin', 'activate' ] );

// Instancia o plugin
new DPS_Base_Plugin();
