<?php
/**
 * Plugin Name:       Desi Pet Shower – Base
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Plugin básico para cadastro de clientes, pets e agendamentos de banho e tosa. Este é o núcleo que pode ser expandido por add-ons.
 * Version:           1.0.1
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-base
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

// Carrega classe de frontend
require_once DPS_BASE_DIR . 'includes/class-dps-base-frontend.php';

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
        // Shortcode para exibir a aplicação no frontend
        add_shortcode( 'dps_base', [ 'DPS_Base_Frontend', 'render_app' ] );
    }

    /**
     * Registra tipos de post personalizados: clientes, pets e agendamentos
     */
    public function register_post_types() {
        // Clientes (tutores)
        $labels = [
            'name'               => __( 'Clientes', 'dps-base' ),
            'singular_name'      => __( 'Cliente', 'dps-base' ),
            'add_new'            => __( 'Adicionar Novo', 'dps-base' ),
            'add_new_item'       => __( 'Adicionar Novo Cliente', 'dps-base' ),
            'edit_item'          => __( 'Editar Cliente', 'dps-base' ),
            'new_item'           => __( 'Novo Cliente', 'dps-base' ),
            'all_items'          => __( 'Todos os Clientes', 'dps-base' ),
            'view_item'          => __( 'Ver Cliente', 'dps-base' ),
            'search_items'       => __( 'Buscar Clientes', 'dps-base' ),
            'not_found'          => __( 'Nenhum cliente encontrado', 'dps-base' ),
            'menu_name'          => __( 'Clientes', 'dps-base' ),
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
            'name'               => __( 'Pets', 'dps-base' ),
            'singular_name'      => __( 'Pet', 'dps-base' ),
            'add_new'            => __( 'Adicionar Novo', 'dps-base' ),
            'add_new_item'       => __( 'Adicionar Novo Pet', 'dps-base' ),
            'edit_item'          => __( 'Editar Pet', 'dps-base' ),
            'new_item'           => __( 'Novo Pet', 'dps-base' ),
            'all_items'          => __( 'Todos os Pets', 'dps-base' ),
            'view_item'          => __( 'Ver Pet', 'dps-base' ),
            'search_items'       => __( 'Buscar Pets', 'dps-base' ),
            'not_found'          => __( 'Nenhum pet encontrado', 'dps-base' ),
            'menu_name'          => __( 'Pets', 'dps-base' ),
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
            'name'               => __( 'Agendamentos', 'dps-base' ),
            'singular_name'      => __( 'Agendamento', 'dps-base' ),
            'add_new_item'       => __( 'Adicionar Novo Agendamento', 'dps-base' ),
            'edit_item'          => __( 'Editar Agendamento', 'dps-base' ),
            'new_item'           => __( 'Novo Agendamento', 'dps-base' ),
            'all_items'          => __( 'Todos os Agendamentos', 'dps-base' ),
            'view_item'          => __( 'Ver Agendamento', 'dps-base' ),
            'search_items'       => __( 'Buscar Agendamentos', 'dps-base' ),
            'not_found'          => __( 'Nenhum agendamento encontrado', 'dps-base' ),
            'menu_name'          => __( 'Agendamentos', 'dps-base' ),
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
            'summarySingle'     => __( 'Pet selecionado: %s', 'dps-base' ),
            'summaryMultiple'   => __( '%d pets selecionados: %s', 'dps-base' ),
            'selectPetWarning'  => __( 'Selecione pelo menos um pet para o agendamento.', 'dps-base' ),
            'historySummary'    => __( '%1$s atendimentos filtrados. Total estimado: R$ %2$s.', 'dps-base' ),
            'historyEmpty'      => __( 'Nenhum atendimento corresponde aos filtros aplicados.', 'dps-base' ),
            'historyExportEmpty'=> __( 'Nenhum atendimento visível para exportar.', 'dps-base' ),
            'historyExportFileName' => __( 'historico-atendimentos-%s.csv', 'dps-base' ),
            'pendingTitle'          => __( 'Pagamentos em aberto para %s.', 'dps-base' ),
            'pendingGenericTitle'   => __( 'Este cliente possui pagamentos pendentes.', 'dps-base' ),
            'pendingItem'           => __( '%1$s: R$ %2$s – %3$s', 'dps-base' ),
            'pendingItemNoDate'     => __( 'R$ %1$s – %2$s', 'dps-base' ),
        ] );
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

// Instancia o plugin
new DPS_Base_Plugin();