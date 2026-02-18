<?php
/**
 * Plugin Name:       desi.pet by PRObst – Estoque Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Controle de estoque de insumos. Gerencie produtos, movimentações e baixas automáticas por atendimento.
 * Version:           1.2.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-stock-addon
 * Domain Path:       /languages
 * Requires at least: 6.9
 * Requires PHP:      8.4
 * Update URI:        https://github.com/richardprobst/DPS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base desi.pet by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_stock_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on requer o plugin base desi.pet by PRObst para funcionar.', 'dps-stock-addon' );
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
                    'map_meta_cap'    => false,
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

        // Enfileira assets CSS condicionalmente (apenas em páginas DPS)
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

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
     * Carregamento condicional: apenas nas páginas admin do plugin DPS.
     *
     * @since 1.1.0
     * @since 1.2.0 Atualizado para versão 1.2.0 com novo layout moderno.
     * @since 2.0.0 F3.4 — Carregamento condicional (não mais global).
     * @param string $hook Hook suffix da página admin atual.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'desi-pet-shower' ) ) {
            return;
        }

        $addon_url = plugin_dir_url( __FILE__ );
        $version   = '1.2.0';

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

        // Verifica nonce usando helper
        if ( ! DPS_Request_Validator::verify_request_nonce( 'dps_stock_meta_nonce', 'dps_stock_meta', 'POST', false ) ) {
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
     * @since 1.2.0 Refatorado para seguir padrão visual moderno do sistema DPS.
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

        $query       = new WP_Query( $args );
        $items       = $query->posts;
        $total_items = $query->found_posts;
        $total_pages = $query->max_num_pages;

        $alerts    = get_option( self::ALERT_OPTION, [] );
        $alerts    = is_array( $alerts ) ? $alerts : [];
        $base_link = add_query_arg( 'tab', 'estoque', DPS_URL_Builder::safe_get_permalink() );

        // Calcula estatísticas para o resumo.
        $stats = $this->calculate_stock_stats( $items );

        // URLs de ações.
        $add_new_url   = admin_url( 'post-new.php?post_type=' . self::CPT );
        $all_items_url = admin_url( 'edit.php?post_type=' . self::CPT );

        ob_start();
        ?>
        <div class="dps-section" id="dps-section-estoque">
            <!-- Header padronizado seguindo padrão das outras abas -->
            <h2 class="dps-section-title">
                <span class="dps-section-title__icon">📦</span>
                <?php esc_html_e( 'Gestão de Estoque', 'dps-stock-addon' ); ?>
            </h2>
            <p class="dps-section-header__subtitle">
                <?php esc_html_e( 'Controle de insumos e materiais. Monitore quantidades, receba alertas de estoque baixo e acompanhe consumo por atendimento.', 'dps-stock-addon' ); ?>
            </p>

            <!-- Cards empilhados verticalmente -->
            <div class="dps-stock-stacked">

                <!-- Card de Resumo/Estatísticas -->
                <div class="dps-surface dps-surface--info dps-stock-status-card">
                    <div class="dps-surface__title">
                        <span>🗂️</span>
                        <?php esc_html_e( 'Resumo do Estoque', 'dps-stock-addon' ); ?>
                    </div>
                    <p class="dps-surface__description">
                        <?php esc_html_e( 'Visão geral dos itens cadastrados e status atual do inventário.', 'dps-stock-addon' ); ?>
                    </p>
                    <ul class="dps-inline-stats dps-inline-stats--panel">
                        <li>
                            <div class="dps-inline-stats__label">
                                <span class="dps-status-badge dps-status-badge--scheduled">
                                    <?php esc_html_e( 'Total de itens', 'dps-stock-addon' ); ?>
                                </span>
                                <small><?php esc_html_e( 'Produtos cadastrados no sistema', 'dps-stock-addon' ); ?></small>
                            </div>
                            <strong class="dps-inline-stats__value"><?php echo esc_html( (string) $stats['total'] ); ?></strong>
                        </li>
                        <li>
                            <div class="dps-inline-stats__label">
                                <span class="dps-status-badge dps-status-badge--paid">
                                    <?php esc_html_e( 'Estoque OK', 'dps-stock-addon' ); ?>
                                </span>
                                <small><?php esc_html_e( 'Itens com quantidade adequada', 'dps-stock-addon' ); ?></small>
                            </div>
                            <strong class="dps-inline-stats__value"><?php echo esc_html( (string) $stats['ok'] ); ?></strong>
                        </li>
                        <li>
                            <div class="dps-inline-stats__label">
                                <span class="dps-status-badge dps-status-badge--pending">
                                    <?php esc_html_e( 'Estoque baixo', 'dps-stock-addon' ); ?>
                                </span>
                                <small><?php esc_html_e( 'Abaixo do mínimo configurado', 'dps-stock-addon' ); ?></small>
                            </div>
                            <strong class="dps-inline-stats__value"><?php echo esc_html( (string) $stats['critical'] ); ?></strong>
                        </li>
                    </ul>

                    <div class="dps-actions dps-actions--stacked">
                        <a class="button button-primary" href="<?php echo esc_url( $add_new_url ); ?>" target="_blank">
                            <?php esc_html_e( 'Adicionar novo item', 'dps-stock-addon' ); ?>
                        </a>
                        <a class="button button-secondary" href="<?php echo esc_url( $all_items_url ); ?>" target="_blank">
                            <?php esc_html_e( 'Gerenciar no Admin', 'dps-stock-addon' ); ?>
                        </a>
                    </div>
                </div>

                <?php if ( $stats['critical'] > 0 ) : ?>
                <!-- Card de Alertas Críticos -->
                <div class="dps-surface dps-surface--warning dps-stock-alert-card">
                    <div class="dps-surface__title">
                        <span>⚠️</span>
                        <?php
                        printf(
                            /* translators: %d: número de itens críticos */
                            esc_html( _n( '%d item abaixo do mínimo', '%d itens abaixo do mínimo', $stats['critical'], 'dps-stock-addon' ) ),
                            (int) $stats['critical']
                        );
                        ?>
                    </div>
                    <p class="dps-surface__description">
                        <?php esc_html_e( 'Os itens abaixo precisam de reposição urgente. Confira as quantidades e providencie a compra.', 'dps-stock-addon' ); ?>
                    </p>
                    <?php $this->render_critical_items_list( $items, $alerts ); ?>
                </div>
                <?php endif; ?>

                <!-- Card de Lista de Itens -->
                <div class="dps-surface dps-surface--neutral dps-stock-list-card">
                    <div class="dps-surface__title">
                        <span>📋</span>
                        <?php esc_html_e( 'Inventário Completo', 'dps-stock-addon' ); ?>
                    </div>
                    <p class="dps-surface__description">
                        <?php
                        if ( $only_critical ) {
                            esc_html_e( 'Exibindo apenas itens com estoque baixo. Clique no botão abaixo para ver todos.', 'dps-stock-addon' );
                        } else {
                            esc_html_e( 'Lista completa de todos os insumos cadastrados. Use o filtro para ver apenas itens críticos.', 'dps-stock-addon' );
                        }
                        ?>
                    </p>

                    <!-- Toolbar de filtros -->
                    <div class="dps-stock-toolbar">
                        <?php
                        $filter_link = $only_critical
                            ? remove_query_arg( 'dps_show', $base_link )
                            : add_query_arg( 'dps_show', 'critical', $base_link );
                        $filter_text = $only_critical
                            ? __( 'Ver todos os itens', 'dps-stock-addon' )
                            : __( 'Mostrar apenas críticos', 'dps-stock-addon' );
                        ?>
                        <a class="button button-secondary" href="<?php echo esc_url( $filter_link ); ?>">
                            <?php echo esc_html( $filter_text ); ?>
                        </a>
                    </div>

                    <?php $this->render_stock_table( $items, $only_critical, $alerts, $add_new_url ); ?>

                    <?php if ( $total_pages > 1 ) : ?>
                    <!-- Paginação -->
                    <div class="dps-stock-pagination">
                        <span class="dps-stock-pagination-info">
                            <?php
                            printf(
                                /* translators: %1$d: página atual, %2$d: total de páginas, %3$d: total de itens */
                                esc_html__( 'Página %1$d de %2$d (%3$d itens)', 'dps-stock-addon' ),
                                (int) $paged,
                                (int) $total_pages,
                                (int) $total_items
                            );
                            ?>
                        </span>
                        <div class="dps-stock-pagination-buttons">
                            <?php if ( $paged > 1 ) : ?>
                                <?php $prev_link = add_query_arg( [ 'stock_page' => $paged - 1, 'dps_show' => $only_critical ? 'critical' : '' ], $base_link ); ?>
                                <a class="button button-secondary" href="<?php echo esc_url( $prev_link ); ?>">
                                    &laquo; <?php esc_html_e( 'Anterior', 'dps-stock-addon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $paged < $total_pages ) : ?>
                                <?php $next_link = add_query_arg( [ 'stock_page' => $paged + 1, 'dps_show' => $only_critical ? 'critical' : '' ], $base_link ); ?>
                                <a class="button button-secondary" href="<?php echo esc_url( $next_link ); ?>">
                                    <?php esc_html_e( 'Próxima', 'dps-stock-addon' ); ?> &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- .dps-stock-stacked -->
        </div><!-- .dps-section -->
        <?php
        return ob_get_clean();
    }

    /**
     * Calcula estatísticas do estoque para exibição no resumo.
     *
     * @since 1.2.0
     *
     * @param array $items Lista de posts de itens de estoque.
     * @return array Estatísticas calculadas (total, ok, critical).
     */
    private function calculate_stock_stats( array $items ) {
        $stats = [
            'total'    => count( $items ),
            'ok'       => 0,
            'critical' => 0,
        ];

        foreach ( $items as $item ) {
            $qty = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $min = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );

            if ( $min > 0 && $qty < $min ) {
                $stats['critical']++;
            } else {
                $stats['ok']++;
            }
        }

        return $stats;
    }

    /**
     * Renderiza lista de itens críticos (estoque baixo) no card de alertas.
     *
     * @since 1.2.0
     *
     * @param array $items  Lista de posts de itens de estoque.
     * @param array $alerts Alertas registrados.
     */
    private function render_critical_items_list( array $items, array $alerts ) {
        $critical_items = [];

        foreach ( $items as $item ) {
            $qty = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $min = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );

            if ( $min > 0 && $qty < $min ) {
                $critical_items[] = [
                    'id'       => $item->ID,
                    'name'     => $item->post_title,
                    'quantity' => $qty,
                    'minimum'  => $min,
                    'unit'     => get_post_meta( $item->ID, 'dps_stock_unit', true ),
                    'alert'    => isset( $alerts[ $item->ID ] ) ? $alerts[ $item->ID ] : null,
                ];
            }
        }

        if ( empty( $critical_items ) ) {
            return;
        }
        ?>
        <ul class="dps-stock-critical-list">
            <?php foreach ( $critical_items as $citem ) : ?>
            <li class="dps-stock-critical-item">
                <div class="dps-stock-critical-item__info">
                    <strong><?php echo esc_html( $citem['name'] ); ?></strong>
                    <span class="dps-stock-critical-item__qty">
                        <?php
                        printf(
                            /* translators: %1$s: quantidade atual, %2$s: quantidade mínima, %3$s: unidade */
                            esc_html__( '%1$s de %2$s %3$s', 'dps-stock-addon' ),
                            esc_html( number_format_i18n( $citem['quantity'], 2 ) ),
                            esc_html( number_format_i18n( $citem['minimum'], 2 ) ),
                            esc_html( $citem['unit'] ?: '' )
                        );
                        ?>
                    </span>
                </div>
                <a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?post=' . $citem['id'] . '&action=edit' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Editar item', 'dps-stock-addon' ); ?>">
                    <?php esc_html_e( 'Editar', 'dps-stock-addon' ); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Renderiza a tabela de itens de estoque.
     *
     * @since 1.2.0
     *
     * @param array  $items         Lista de posts de itens de estoque.
     * @param bool   $only_critical Se true, exibe apenas itens com estoque baixo.
     * @param array  $alerts        Alertas registrados.
     * @param string $add_new_url   URL para adicionar novo item.
     */
    private function render_stock_table( array $items, bool $only_critical, array $alerts, string $add_new_url ) {
        // Filtra itens se necessário.
        $filtered_items = [];
        foreach ( $items as $item ) {
            $qty    = (float) get_post_meta( $item->ID, 'dps_stock_quantity', true );
            $min    = (float) get_post_meta( $item->ID, 'dps_stock_minimum', true );
            $is_low = $min > 0 && $qty < $min;

            if ( $only_critical && ! $is_low ) {
                continue;
            }

            $filtered_items[] = [
                'post'     => $item,
                'quantity' => $qty,
                'minimum'  => $min,
                'unit'     => get_post_meta( $item->ID, 'dps_stock_unit', true ),
                'is_low'   => $is_low,
                'alert'    => isset( $alerts[ $item->ID ] ) ? $alerts[ $item->ID ] : null,
            ];
        }
        ?>
        <div class="dps-table-wrapper dps-stock-table-wrapper">
            <table class="dps-table dps-stock-table">
                <thead>
                    <tr>
                        <th class="dps-col-name"><?php esc_html_e( 'Item', 'dps-stock-addon' ); ?></th>
                        <th class="dps-col-unit"><?php esc_html_e( 'Unidade', 'dps-stock-addon' ); ?></th>
                        <th class="dps-col-qty"><?php esc_html_e( 'Qtd. Atual', 'dps-stock-addon' ); ?></th>
                        <th class="dps-col-min"><?php esc_html_e( 'Qtd. Mínima', 'dps-stock-addon' ); ?></th>
                        <th class="dps-col-status"><?php esc_html_e( 'Status', 'dps-stock-addon' ); ?></th>
                        <th class="dps-col-actions"><?php esc_html_e( 'Ações', 'dps-stock-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $filtered_items ) ) : ?>
                    <tr class="dps-stock-empty-row">
                        <td colspan="6">
                            <div class="dps-stock-empty">
                                <span class="dashicons dashicons-archive"></span>
                                <p>
                                    <?php
                                    if ( $only_critical ) {
                                        esc_html_e( 'Nenhum item com estoque baixo. Parabéns! 🎉', 'dps-stock-addon' );
                                    } else {
                                        esc_html_e( 'Nenhum item de estoque cadastrado.', 'dps-stock-addon' );
                                    }
                                    ?>
                                </p>
                                <?php if ( ! $only_critical ) : ?>
                                <a class="button button-primary" href="<?php echo esc_url( $add_new_url ); ?>" target="_blank">
                                    <?php esc_html_e( 'Cadastrar primeiro item', 'dps-stock-addon' ); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else : ?>
                        <?php foreach ( $filtered_items as $fitem ) : ?>
                        <tr class="<?php echo $fitem['is_low'] ? 'dps-stock-row--low' : ''; ?>" data-label="<?php echo esc_attr( $fitem['post']->post_title ); ?>">
                            <td class="dps-col-name" data-label="<?php esc_attr_e( 'Item', 'dps-stock-addon' ); ?>">
                                <strong><?php echo esc_html( $fitem['post']->post_title ); ?></strong>
                            </td>
                            <td class="dps-col-unit" data-label="<?php esc_attr_e( 'Unidade', 'dps-stock-addon' ); ?>">
                                <?php echo esc_html( $fitem['unit'] ?: '—' ); ?>
                            </td>
                            <td class="dps-col-qty<?php echo $fitem['is_low'] ? ' dps-stock-qty--low' : ''; ?>" data-label="<?php esc_attr_e( 'Qtd. Atual', 'dps-stock-addon' ); ?>">
                                <?php echo esc_html( number_format_i18n( $fitem['quantity'], 2 ) ); ?>
                            </td>
                            <td class="dps-col-min" data-label="<?php esc_attr_e( 'Qtd. Mínima', 'dps-stock-addon' ); ?>">
                                <?php echo esc_html( number_format_i18n( $fitem['minimum'], 2 ) ); ?>
                            </td>
                            <td class="dps-col-status" data-label="<?php esc_attr_e( 'Status', 'dps-stock-addon' ); ?>">
                                <?php if ( $fitem['is_low'] ) : ?>
                                    <span class="dps-stock-status dps-stock-status--low">⚠️ <?php esc_html_e( 'Baixo', 'dps-stock-addon' ); ?></span>
                                    <?php if ( $fitem['alert'] && isset( $fitem['alert']['created_at'] ) ) : ?>
                                        <br><small class="dps-stock-alert-date"><?php esc_html_e( 'Desde:', 'dps-stock-addon' ); ?> <?php echo esc_html( $fitem['alert']['created_at'] ); ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="dps-stock-status dps-stock-status--ok">✓ <?php esc_html_e( 'OK', 'dps-stock-addon' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="dps-col-actions" data-label="<?php esc_attr_e( 'Ações', 'dps-stock-addon' ); ?>">
                                <a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?post=' . $fitem['post']->ID . '&action=edit' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Editar item', 'dps-stock-addon' ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
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
