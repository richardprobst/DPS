<?php
/**
 * Plugin Name:       Desi Pet Shower – Estoque Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para controlar estoque de insumos usados nos atendimentos do Desi Pet Shower.
 * Version:           0.1.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       desi-pet-shower
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe principal do add-on de estoque.
 */
class DPS_Stock_Addon {

    const CPT = 'dps_stock_item';
    const ALERT_OPTION = 'dps_stock_alerts';
    const CAPABILITY = 'dps_manage_stock';

    /**
     * Helper para registrar o CPT de estoque.
     *
     * @var DPS_CPT_Helper|null
     */
    private $cpt_helper;

    /**
     * Inicializa hooks do add-on.
     */
    public function __construct() {
        if ( ! class_exists( 'DPS_CPT_Helper' ) && defined( 'DPS_BASE_DIR' ) ) {
            require_once DPS_BASE_DIR . 'includes/class-dps-cpt-helper.php';
        }

        if ( class_exists( 'DPS_CPT_Helper' ) ) {
            $stock_capabilities = [
                'publish_posts'       => self::CAPABILITY,
                'edit_posts'          => self::CAPABILITY,
                'edit_others_posts'   => self::CAPABILITY,
                'delete_posts'        => self::CAPABILITY,
                'delete_others_posts' => self::CAPABILITY,
                'read_private_posts'  => self::CAPABILITY,
                'edit_post'           => self::CAPABILITY,
                'delete_post'         => self::CAPABILITY,
                'read_post'           => self::CAPABILITY,
                'create_posts'        => self::CAPABILITY,
            ];

            $this->cpt_helper = new DPS_CPT_Helper(
                self::CPT,
                [
                    'name'          => __( 'Estoque', 'desi-pet-shower' ),
                    'singular_name' => __( 'Item de estoque', 'desi-pet-shower' ),
                ],
                [
                    'public'          => false,
                    'show_ui'         => true,
                    'show_in_menu'    => false,
                    'supports'        => [ 'title' ],
                    'capability_type' => 'post',
                    'map_meta_cap'    => true,
                    'capabilities'    => $stock_capabilities,
                ]
            );
        }

        add_action( 'init', [ self::class, 'ensure_roles_have_capability' ] );
        add_action( 'init', [ $this, 'register_stock_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_' . self::CPT, [ $this, 'save_stock_meta' ], 10, 3 );
        // Registra menus após o núcleo para aproveitar o menu principal existente.
        add_action( 'admin_menu', [ $this, 'register_menu' ], 99 );

        // Integração com agendamentos: baixa estoque quando o atendimento é concluído.
        add_action( 'dps_base_after_save_appointment', [ $this, 'maybe_handle_appointment_completion' ], 10, 2 );
    }

    /**
     * Rotinas de ativação: registra o CPT e flush das regras.
     */
    public static function activate() {
        self::ensure_roles_have_capability();
        $self = new self();
        $self->register_stock_cpt();
        flush_rewrite_rules();
    }

    /**
     * Garante que administradores e recepção possam gerenciar estoque.
     */
    public static function ensure_roles_have_capability() {
        $roles = [ 'administrator', 'dps_reception' ];

        foreach ( $roles as $role_slug ) {
            $role = get_role( $role_slug );

            if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
                $role->add_cap( self::CAPABILITY );
            }
        }
    }

    /**
     * Registra o tipo de post para itens de estoque.
     */
    public function register_stock_cpt() {
        if ( ! $this->cpt_helper ) {
            return;
        }

        $this->cpt_helper->register();
    }

    /**
     * Registra metaboxes para controlar unidade e quantidades de estoque.
     */
    public function register_meta_boxes() {
        add_meta_box(
            'dps_stock_details',
            __( 'Detalhes do estoque', 'desi-pet-shower' ),
            [ $this, 'render_stock_metabox' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza a metabox de estoque.
     *
     * @param WP_Post $post Objeto do post atual.
     */
    public function render_stock_metabox( $post ) {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        wp_nonce_field( 'dps_stock_meta', 'dps_stock_meta_nonce' );

        $unit     = get_post_meta( $post->ID, 'dps_stock_unit', true );
        $quantity = get_post_meta( $post->ID, 'dps_stock_quantity', true );
        $minimum  = get_post_meta( $post->ID, 'dps_stock_minimum', true );

        echo '<p><label for="dps_stock_unit">' . esc_html__( 'Unidade (ml, un, pct...)', 'desi-pet-shower' ) . '</label><br>';
        echo '<input type="text" id="dps_stock_unit" name="dps_stock_unit" value="' . esc_attr( $unit ) . '" class="widefat" /></p>';

        echo '<p><label for="dps_stock_quantity">' . esc_html__( 'Quantidade atual', 'desi-pet-shower' ) . '</label><br>';
        echo '<input type="number" step="0.01" min="0" id="dps_stock_quantity" name="dps_stock_quantity" value="' . esc_attr( $quantity ) . '" class="widefat" /></p>';

        echo '<p><label for="dps_stock_minimum">' . esc_html__( 'Quantidade mínima para alerta', 'desi-pet-shower' ) . '</label><br>';
        echo '<input type="number" step="0.01" min="0" id="dps_stock_minimum" name="dps_stock_minimum" value="' . esc_attr( $minimum ) . '" class="widefat" /></p>';
    }

    /**
     * Salva metadados do item de estoque.
     */
    public function save_stock_meta( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['dps_stock_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_stock_meta_nonce'] ) ), 'dps_stock_meta' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $unit     = isset( $_POST['dps_stock_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_stock_unit'] ) ) : '';
        $quantity = isset( $_POST['dps_stock_quantity'] ) ? floatval( wp_unslash( $_POST['dps_stock_quantity'] ) ) : 0;
        $minimum  = isset( $_POST['dps_stock_minimum'] ) ? floatval( wp_unslash( $_POST['dps_stock_minimum'] ) ) : 0;

        update_post_meta( $post_id, 'dps_stock_unit', $unit );
        update_post_meta( $post_id, 'dps_stock_quantity', max( 0, $quantity ) );
        update_post_meta( $post_id, 'dps_stock_minimum', max( 0, $minimum ) );
    }

    /**
     * Adiciona submenu na área administrativa para visualizar o estoque.
     */
    public function register_menu() {
        // O núcleo cria o menu principal "desi-pet-shower"; aqui apenas anexamos o submenu de estoque.
        if ( isset( $GLOBALS['admin_page_hooks']['desi-pet-shower'] ) ) {
            add_submenu_page(
                'desi-pet-shower',
                __( 'Estoque DPS', 'desi-pet-shower' ),
                __( 'Estoque DPS', 'desi-pet-shower' ),
                self::CAPABILITY,
                'dps-stock',
                [ $this, 'render_stock_page' ]
            );

            return;
        }

        // Fallback: quando o núcleo não está ativo, criamos menu próprio com slug distinto.
        add_menu_page(
            __( 'Estoque DPS', 'desi-pet-shower' ),
            __( 'Estoque DPS', 'desi-pet-shower' ),
            self::CAPABILITY,
            'dps-stock-root',
            [ $this, 'render_stock_page' ],
            'dashicons-products'
        );
    }

    /**
     * Renderiza a página de listagem e alertas do estoque.
     */
    public function render_stock_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        $only_critical = isset( $_GET['dps_show'] ) && 'critical' === sanitize_text_field( wp_unslash( $_GET['dps_show'] ) );
        $args          = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $items  = get_posts( $args );
        $alerts = get_option( self::ALERT_OPTION, [] );
        $alerts = is_array( $alerts ) ? $alerts : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Estoque DPS', 'desi-pet-shower' ) . '</h1>';

        $filter_link = add_query_arg( 'dps_show', $only_critical ? 'all' : 'critical' );
        $filter_text = $only_critical ? __( 'Ver todos', 'desi-pet-shower' ) : __( 'Mostrar apenas críticos', 'desi-pet-shower' );
        echo '<a class="button" href="' . esc_url( $filter_link ) . '">' . esc_html( $filter_text ) . '</a> ';

        $export_link = add_query_arg( 'dps_stock_export', '1' );
        echo '<a class="button" href="' . esc_url( $export_link ) . '">' . esc_html__( 'Exportar estoque (em breve)', 'desi-pet-shower' ) . '</a>';

        echo '<p class="description">' . esc_html__( 'Cadastre os itens em "Todos os itens" para controlar insumos internos.', 'desi-pet-shower' ) . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Item', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Unidade', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Qtd. atual', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Qtd. mínima', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'desi-pet-shower' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $items ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Nenhum item cadastrado.', 'desi-pet-shower' ) . '</td></tr>';
        }

        foreach ( $items as $item ) {
            $quantity = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $minimum  = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );
            $unit     = get_post_meta( $item->ID, 'dps_stock_unit', true );
            $is_low   = $minimum > 0 && $quantity < $minimum;

            if ( $only_critical && ! $is_low ) {
                continue;
            }

            $status_text = $is_low ? __( 'Abaixo do mínimo', 'desi-pet-shower' ) : __( 'OK', 'desi-pet-shower' );
            $status_cls  = $is_low ? 'tag-description' : 'description';

            echo '<tr>';
            echo '<td><strong>' . esc_html( $item->post_title ) . '</strong></td>';
            echo '<td>' . esc_html( $unit ) . '</td>';
            echo '<td>' . esc_html( $quantity ) . '</td>';
            echo '<td>' . esc_html( $minimum ) . '</td>';
            echo '<td><span class="' . esc_attr( $status_cls ) . '">' . esc_html( $status_text ) . '</span>';

            if ( $is_low && isset( $alerts[ $item->ID ] ) ) {
                $alert_info = $alerts[ $item->ID ];
                $label      = isset( $alert_info['created_at'] ) ? $alert_info['created_at'] : '';
                echo '<br><small>' . esc_html__( 'Alerta registrado em:', 'desi-pet-shower' ) . ' ' . esc_html( $label ) . '</small>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Processa a conclusão de um agendamento e aplica a baixa de estoque.
     *
     * Fluxo: ao concluir um atendimento (status finalizado ou finalizado_pago),
     * coletamos os serviços do agendamento, somamos os insumos configurados em cada
     * serviço e subtraímos a quantidade consumida do estoque. Se a quantidade
     * resultante ficar abaixo do mínimo, registramos um alerta em option para exibir
     * na tela administrativa.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $appointment_type Tipo de agendamento (simple|subscription).
     */
    public function maybe_handle_appointment_completion( $appointment_id, $appointment_type ) {
        $status = get_post_meta( $appointment_id, 'appointment_status', true );

        if ( ! in_array( $status, [ 'finalizado', 'finalizado_pago' ], true ) ) {
            return;
        }

        $processed = get_post_meta( $appointment_id, '_dps_stock_processed_status', true );
        if ( $processed === $status ) {
            return;
        }

        $service_ids = get_post_meta( $appointment_id, 'appointment_services', true );
        if ( empty( $service_ids ) || ! is_array( $service_ids ) ) {
            return;
        }

        $totals = $this->collect_consumption_totals( array_map( 'intval', $service_ids ) );
        if ( empty( $totals ) ) {
            update_post_meta( $appointment_id, '_dps_stock_processed_status', $status );
            return;
        }

        foreach ( $totals as $item_id => $qty ) {
            $this->deduct_stock_for_item( $item_id, $qty, $appointment_id );
        }

        update_post_meta( $appointment_id, '_dps_stock_processed_status', $status );
    }

    /**
     * Consolida consumo de estoque para uma lista de serviços.
     *
     * @param array $service_ids IDs dos serviços selecionados.
     * @return array<int,float>
     */
    private function collect_consumption_totals( $service_ids ) {
        $totals  = [];
        $visited = [];

        foreach ( $service_ids as $service_id ) {
            $this->merge_service_consumption( $service_id, $totals, $visited );
        }

        return $totals;
    }

    /**
     * Soma os insumos de um serviço específico, incluindo pacotes recursivos.
     *
     * @param int   $service_id ID do serviço.
     * @param array $totals     Referência para o mapa acumulado.
     * @param array $visited    Controle de serviços já processados.
     */
    private function merge_service_consumption( $service_id, array &$totals, array &$visited ) {
        if ( in_array( $service_id, $visited, true ) ) {
            return;
        }

        $visited[] = $service_id;
        $consumption = get_post_meta( $service_id, 'dps_service_stock_consumption', true );
        if ( is_array( $consumption ) ) {
            foreach ( $consumption as $row ) {
                $item_id  = isset( $row['item_id'] ) ? intval( $row['item_id'] ) : 0;
                $quantity = isset( $row['quantity'] ) ? floatval( $row['quantity'] ) : 0;

                if ( $item_id && $quantity > 0 ) {
                    if ( ! isset( $totals[ $item_id ] ) ) {
                        $totals[ $item_id ] = 0;
                    }
                    $totals[ $item_id ] += $quantity;
                }
            }
        }

        $type = get_post_meta( $service_id, 'service_type', true );
        if ( 'package' === $type ) {
            $package_items = get_post_meta( $service_id, 'service_package_items', true );
            if ( is_array( $package_items ) ) {
                foreach ( $package_items as $pkg_id ) {
                    $this->merge_service_consumption( intval( $pkg_id ), $totals, $visited );
                }
            }
        }
    }

    /**
     * Subtrai quantidade consumida de um item e registra alerta se necessário.
     *
     * @param int $item_id       ID do item de estoque.
     * @param float $quantity    Quantidade consumida.
     * @param int $appointment_id ID do agendamento utilizado na baixa.
     */
    private function deduct_stock_for_item( $item_id, $quantity, $appointment_id ) {
        $item = get_post( $item_id );
        if ( ! $item || self::CPT !== $item->post_type ) {
            return;
        }

        $current  = (float) get_post_meta( $item_id, 'dps_stock_quantity', true );
        $new_qty  = max( 0, $current - $quantity );
        $minimum  = (float) get_post_meta( $item_id, 'dps_stock_minimum', true );

        update_post_meta( $item_id, 'dps_stock_quantity', $new_qty );

        if ( $minimum > 0 && $new_qty < $minimum ) {
            $this->register_alert( $item_id, $new_qty, $minimum, $appointment_id );
        }
    }

    /**
     * Registra um alerta quando o estoque atinge nível crítico.
     */
    private function register_alert( $item_id, $quantity, $minimum, $appointment_id ) {
        $alerts = get_option( self::ALERT_OPTION, [] );
        $alerts = is_array( $alerts ) ? $alerts : [];

        $alerts[ $item_id ] = [
            'item_id'     => $item_id,
            'quantity'    => $quantity,
            'minimum'     => $minimum,
            'appointment' => $appointment_id,
            'created_at'  => current_time( 'mysql' ),
        ];

        update_option( self::ALERT_OPTION, $alerts );
    }
}

add_action( 'plugins_loaded', function() {
    new DPS_Stock_Addon();
} );

register_activation_hook( __FILE__, [ 'DPS_Stock_Addon', 'activate' ] );
