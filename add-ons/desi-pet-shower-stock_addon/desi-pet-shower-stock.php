<?php
/**
 * Plugin Name:       DPS by PRObst – Estoque Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Controle de estoque de insumos. Gerencie produtos, movimentações e baixas automáticas por atendimento.
 * Version:           0.1.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-stock-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_stock_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Estoque requer o plugin base DPS by PRObst para funcionar.', 'dps-stock-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_stock_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Stock Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_stock_load_textdomain() {
    load_plugin_textdomain( 'dps-stock-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_stock_load_textdomain', 1 );

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
                'publish_posts'          => self::CAPABILITY,
                'edit_posts'             => self::CAPABILITY,
                'edit_others_posts'      => self::CAPABILITY,
                'delete_posts'           => self::CAPABILITY,
                'delete_others_posts'    => self::CAPABILITY,
                'delete_private_posts'   => self::CAPABILITY,
                'delete_published_posts' => self::CAPABILITY,
                'read_private_posts'     => self::CAPABILITY,
                'edit_post'              => self::CAPABILITY,
                'delete_post'            => self::CAPABILITY,
                'read_post'              => self::CAPABILITY,
                'create_posts'           => self::CAPABILITY,
            ];

            $this->cpt_helper = new DPS_CPT_Helper(
                self::CPT,
                [
                    'name'          => __( 'Estoque', 'dps-stock-addon' ),
                    'singular_name' => __( 'Item de estoque', 'dps-stock-addon' ),
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
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_stock_tab' ], 30, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_stock_section' ], 30, 1 );

        // Enfileira assets CSS para responsividade
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

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
     * Enfileira CSS responsivo do add-on de estoque.
     *
     * @since 1.1.0
     */
    public function enqueue_assets() {
        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.1.0';

        wp_enqueue_style(
            'dps-stock-addon',
            $addon_url . 'assets/css/stock-addon.css',
            [],
            $version
        );
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
            __( 'Detalhes do estoque', 'dps-stock-addon' ),
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
        if ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_nonce_field( 'dps_stock_meta', 'dps_stock_meta_nonce' );

        $unit     = get_post_meta( $post->ID, 'dps_stock_unit', true );
        $quantity = get_post_meta( $post->ID, 'dps_stock_quantity', true );
        $minimum  = get_post_meta( $post->ID, 'dps_stock_minimum', true );

        echo '<p><label for="dps_stock_unit">' . esc_html__( 'Unidade (ml, un, pct...)', 'dps-stock-addon' ) . '</label><br>';
        echo '<input type="text" id="dps_stock_unit" name="dps_stock_unit" value="' . esc_attr( $unit ) . '" class="widefat" /></p>';

        echo '<p><label for="dps_stock_quantity">' . esc_html__( 'Quantidade atual', 'dps-stock-addon' ) . '</label><br>';
        echo '<input type="number" step="0.01" min="0" id="dps_stock_quantity" name="dps_stock_quantity" value="' . esc_attr( $quantity ) . '" class="widefat" /></p>';

        echo '<p><label for="dps_stock_minimum">' . esc_html__( 'Quantidade mínima para alerta', 'dps-stock-addon' ) . '</label><br>';
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

        if ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
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
     * Verifica se o usuário atual pode acessar o módulo de estoque.
     *
     * Aceita tanto a capability customizada quanto manage_options
     * para garantir que administradores sempre tenham acesso.
     *
     * @since 1.1.0
     *
     * @return bool True se o usuário pode acessar o estoque.
     */
    public function can_access_stock() {
        return is_user_logged_in() && ( current_user_can( self::CAPABILITY ) || current_user_can( 'manage_options' ) );
    }

    /**
     * Adiciona aba "Estoque" à navegação do painel principal.
     *
     * Conectado ao hook dps_base_nav_tabs_after_history para injetar
     * a aba de gestão de estoque na interface do plugin base.
     *
     * @since 1.0.0
     *
     * @param bool $visitor_only Se true, está no modo visitante (sem permissões admin).
     */
    public function add_stock_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        if ( $this->can_access_stock() ) {
            echo '<li><a href="#" class="dps-tab-link" data-tab="estoque">' . esc_html__( 'Estoque', 'dps-stock-addon' ) . '</a></li>';
        }
    }

    /**
     * Renderiza seção de estoque no painel principal.
     *
     * Conectado ao hook dps_base_sections_after_history para exibir
     * o conteúdo da aba de gestão de estoque.
     *
     * @since 1.0.0
     *
     * @param bool $visitor_only Se true, está no modo visitante (sem permissões admin).
     */
    public function add_stock_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        echo wp_kses_post( $this->render_stock_page() );
    }

    /**
     * Renderiza a página de listagem e alertas do estoque.
     *
     * Exibe lista completa de itens de estoque com suas quantidades,
     * unidades e alertas de estoque baixo quando quantidade atual
     * está abaixo do mínimo configurado.
     *
     * @since 1.0.0
     *
     * @return string HTML da página de estoque.
     */
    public function render_stock_page() {
        if ( ! $this->can_access_stock() ) {
            return '<div class="dps-section" id="dps-section-estoque"><p>' . esc_html__( 'Você não possui permissão para acessar o estoque.', 'dps-stock-addon' ) . '</p></div>';
        }

        $only_critical = isset( $_GET['dps_show'] ) && 'critical' === sanitize_text_field( wp_unslash( $_GET['dps_show'] ) );

        // Implementa paginação para lidar com estoque grande.
        $per_page = 50;
        $paged    = isset( $_GET['stock_page'] ) ? max( 1, absint( $_GET['stock_page'] ) ) : 1;

        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $query = new WP_Query( $args );
        $items = $query->posts;
        $total_items = $query->found_posts;
        $total_pages = $query->max_num_pages;

        $alerts    = get_option( self::ALERT_OPTION, [] );
        $alerts    = is_array( $alerts ) ? $alerts : [];
        $base_link = add_query_arg( 'tab', 'estoque', get_permalink() );

        ob_start();
        echo '<div class="dps-section" id="dps-section-estoque">';
        echo '<h2 style="margin-bottom: 20px; color: #374151;">' . esc_html__( 'Estoque de Insumos', 'dps-stock-addon' ) . '</h2>';

        // Barra de ações com botões
        echo '<div class="dps-field-group" style="margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">';
        
        // Botão para adicionar novo item (link para admin)
        $add_new_url = admin_url( 'post-new.php?post_type=' . self::CPT );
        echo '<a class="button button-primary" href="' . esc_url( $add_new_url ) . '" target="_blank" style="background: #0ea5e9; border-color: #0ea5e9;">';
        echo '<span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 4px;"></span>';
        echo esc_html__( 'Adicionar Item', 'dps-stock-addon' ) . '</a> ';
        
        // Botão para ver todos os itens no admin
        $all_items_url = admin_url( 'edit.php?post_type=' . self::CPT );
        echo '<a class="button" href="' . esc_url( $all_items_url ) . '" target="_blank">';
        echo '<span class="dashicons dashicons-list-view" style="vertical-align: middle; margin-right: 4px;"></span>';
        echo esc_html__( 'Gerenciar no Admin', 'dps-stock-addon' ) . '</a> ';
        
        // Filtro críticos
        $filter_link = add_query_arg( 'dps_show', $only_critical ? '' : 'critical', $base_link );
        if ( $only_critical ) {
            $filter_link = remove_query_arg( 'dps_show', $base_link );
        }
        $filter_text = $only_critical ? __( 'Ver todos', 'dps-stock-addon' ) : __( 'Mostrar apenas críticos', 'dps-stock-addon' );
        $filter_icon = $only_critical ? 'visibility' : 'warning';
        echo '<a class="button" href="' . esc_url( $filter_link ) . '">';
        echo '<span class="dashicons dashicons-' . esc_attr( $filter_icon ) . '" style="vertical-align: middle; margin-right: 4px;"></span>';
        echo esc_html( $filter_text ) . '</a>';
        
        echo '</div>';

        // Resumo rápido
        $critical_count = 0;
        foreach ( $items as $item ) {
            $qty = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $min = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );
            if ( $min > 0 && $qty < $min ) {
                $critical_count++;
            }
        }
        
        if ( $critical_count > 0 ) {
            echo '<div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">';
            echo '<span style="color: #92400e; font-weight: 600;">';
            echo '<span class="dashicons dashicons-warning" style="vertical-align: middle;"></span> ';
            echo esc_html( sprintf( _n( '%d item abaixo do mínimo', '%d itens abaixo do mínimo', $critical_count, 'dps-stock-addon' ), $critical_count ) );
            echo '</span></div>';
        }
        
        echo '<table class="widefat fixed striped" style="border-radius: 6px; overflow: hidden;">';
        echo '<thead style="background: #f9fafb;"><tr>';
        echo '<th style="font-weight: 600; color: #374151;">' . esc_html__( 'Item', 'dps-stock-addon' ) . '</th>';
        echo '<th style="font-weight: 600; color: #374151; width: 80px;">' . esc_html__( 'Unidade', 'dps-stock-addon' ) . '</th>';
        echo '<th style="font-weight: 600; color: #374151; width: 100px;">' . esc_html__( 'Qtd. atual', 'dps-stock-addon' ) . '</th>';
        echo '<th style="font-weight: 600; color: #374151; width: 100px;">' . esc_html__( 'Qtd. mínima', 'dps-stock-addon' ) . '</th>';
        echo '<th style="font-weight: 600; color: #374151; width: 140px;">' . esc_html__( 'Status', 'dps-stock-addon' ) . '</th>';
        echo '<th style="font-weight: 600; color: #374151; width: 80px;">' . esc_html__( 'Ações', 'dps-stock-addon' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $items ) ) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 40px 20px;">';
            echo '<span class="dashicons dashicons-archive" style="font-size: 48px; color: #d1d5db; display: block; margin-bottom: 12px;"></span>';
            echo '<p style="color: #6b7280; margin: 0 0 12px;">' . esc_html__( 'Nenhum item de estoque cadastrado.', 'dps-stock-addon' ) . '</p>';
            echo '<a class="button button-primary" href="' . esc_url( $add_new_url ) . '" target="_blank">' . esc_html__( 'Cadastrar primeiro item', 'dps-stock-addon' ) . '</a>';
            echo '</td></tr>';
        }

        foreach ( $items as $item ) {
            $quantity = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $minimum  = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );
            $unit     = get_post_meta( $item->ID, 'dps_stock_unit', true );
            $is_low   = $minimum > 0 && $quantity < $minimum;

            // Filtro "críticos" aplicado em PHP pois requer comparação de dois metadados
            // (quantity < minimum). Meta_query do WordPress não suporta comparação entre
            // dois meta_values. Como a paginação já limita a 50 itens, o overhead é mínimo.
            if ( $only_critical && ! $is_low ) {
                continue;
            }

            $status_text = $is_low ? __( 'Baixo', 'dps-stock-addon' ) : __( 'OK', 'dps-stock-addon' );
            $status_style = $is_low 
                ? 'background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;' 
                : 'background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;';
            $status_icon = $is_low ? '⚠️' : '✓';

            $row_style = $is_low ? 'background: #fffbeb;' : '';

            echo '<tr style="' . esc_attr( $row_style ) . '">';
            echo '<td><strong>' . esc_html( $item->post_title ) . '</strong></td>';
            echo '<td>' . esc_html( $unit ?: '—' ) . '</td>';
            echo '<td style="' . ( $is_low ? 'color: #dc2626; font-weight: 600;' : '' ) . '">' . esc_html( number_format_i18n( $quantity, 2 ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( $minimum, 2 ) ) . '</td>';
            echo '<td><span style="' . esc_attr( $status_style ) . '">' . esc_html( $status_icon . ' ' . $status_text ) . '</span>';

            if ( $is_low && isset( $alerts[ $item->ID ] ) ) {
                $alert_info = $alerts[ $item->ID ];
                $label      = isset( $alert_info['created_at'] ) ? $alert_info['created_at'] : '';
                echo '<br><small style="color: #6b7280;">' . esc_html__( 'Desde:', 'dps-stock-addon' ) . ' ' . esc_html( $label ) . '</small>';
            }

            echo '</td>';
            
            // Coluna de ações
            $edit_url = admin_url( 'post.php?post=' . $item->ID . '&action=edit' );
            echo '<td>';
            echo '<a href="' . esc_url( $edit_url ) . '" target="_blank" class="button button-small" title="' . esc_attr__( 'Editar item', 'dps-stock-addon' ) . '">';
            echo '<span class="dashicons dashicons-edit" style="vertical-align: middle; font-size: 14px;"></span>';
            echo '</a>';
            echo '</td>';
            
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Renderiza paginação se houver múltiplas páginas.
        if ( $total_pages > 1 ) {
            echo '<div class="dps-pagination" style="margin-top: 20px; text-align: center;">';
            echo '<p>' . esc_html( sprintf( __( 'Página %d de %d (%d itens no total)', 'dps-stock-addon' ), $paged, $total_pages, $total_items ) ) . '</p>';

            $prev_link = $paged > 1 ? add_query_arg( [ 'stock_page' => $paged - 1, 'dps_show' => $only_critical ? 'critical' : 'all' ], $base_link ) : '';
            $next_link = $paged < $total_pages ? add_query_arg( [ 'stock_page' => $paged + 1, 'dps_show' => $only_critical ? 'critical' : 'all' ], $base_link ) : '';

            if ( $prev_link ) {
                echo '<a class="button" href="' . esc_url( $prev_link ) . '">&laquo; ' . esc_html__( 'Anterior', 'dps-stock-addon' ) . '</a> ';
            }

            echo '<span style="margin: 0 10px;">' . esc_html( sprintf( __( 'Página %d de %d', 'dps-stock-addon' ), $paged, $total_pages ) ) . '</span>';

            if ( $next_link ) {
                echo ' <a class="button" href="' . esc_url( $next_link ) . '">' . esc_html__( 'Próxima', 'dps-stock-addon' ) . ' &raquo;</a>';
            }
            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
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

/**
 * Inicializa o Stock Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_stock_init_addon() {
    if ( class_exists( 'DPS_Stock_Addon' ) ) {
        new DPS_Stock_Addon();
    }
}
add_action( 'init', 'dps_stock_init_addon', 5 );

register_activation_hook( __FILE__, [ 'DPS_Stock_Addon', 'activate' ] );
