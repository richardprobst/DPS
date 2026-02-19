<?php
/**
 * Página administrativa para visualização de auditoria do desi.pet by PRObst.
 *
 * @package    Desi_Pet_Shower
 * @subpackage Base_Plugin
 * @since      2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página administrativa de auditoria do sistema.
 *
 * @since 2.6.0
 */
class DPS_Audit_Admin_Page {

    public function __construct() {
        // Prioridade 20 garante que o menu pai 'desi-pet-shower' já foi registrado pelo plugin base.
        add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
        add_action( 'admin_post_dps_purge_audit', array( $this, 'handle_purge' ) );
    }

    /**
     * Registra a página de Auditoria como submenu oculto.
     *
     * @since 2.6.0
     */
    public function register_page() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'Auditoria do Sistema', 'desi-pet-shower' ),
            __( 'Auditoria do Sistema', 'desi-pet-shower' ),
            'manage_options',
            'dps-audit',
            array( $this, 'render_page' )
        );
    }

    /**
     * Renderiza a listagem com filtros e paginação.
     *
     * @since 2.6.0
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        $entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entity_type'] ) ) : '';
        $action      = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
        $date_from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
        $paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $entity_types = DPS_Audit_Logger::get_entity_types();
        $action_types = DPS_Audit_Logger::get_action_types();
        $per_page     = 30;
        $query_data   = array();

        if ( DPS_Audit_Logger::table_exists() ) {
            $query_data = self::query_logs( $entity_type, $action, $date_from, $date_to, $paged, $per_page );
        }

        $current_url = admin_url( 'admin.php?page=dps-audit' );

        echo '<div class="wrap dps-admin-page">';
        echo '<h1>' . esc_html__( 'Auditoria do Sistema', 'desi-pet-shower' ) . '</h1>';

        if ( ! DPS_Audit_Logger::table_exists() ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'A tabela de auditoria ainda não existe. Reative o plugin para criar a estrutura.', 'desi-pet-shower' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( isset( $_GET['purged'] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Registros antigos removidos com sucesso.', 'desi-pet-shower' ) . '</p></div>';
        }

        echo '<div class="dps-filter-container">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="dps-audit" />';

        // Indicação de filtros ativos
        if ( ! empty( $entity_type ) || ! empty( $action ) || ! empty( $date_from ) || ! empty( $date_to ) ) {
            echo '<div class="dps-active-filters">';
            echo '<span class="dps-active-filters-icon">🔍</span>';
            echo esc_html__( 'Filtros ativos:', 'desi-pet-shower' ) . ' ';
            $filters = [];
            if ( $entity_type ) {
                $filters[] = esc_html__( 'Tipo:', 'desi-pet-shower' ) . ' ' . esc_html( DPS_Audit_Logger::get_entity_label( $entity_type ) );
            }
            if ( $action ) {
                $filters[] = esc_html__( 'Ação:', 'desi-pet-shower' ) . ' ' . esc_html( DPS_Audit_Logger::get_action_label( $action ) );
            }
            if ( $date_from ) {
                $filters[] = esc_html__( 'De:', 'desi-pet-shower' ) . ' ' . esc_html( $date_from );
            }
            if ( $date_to ) {
                $filters[] = esc_html__( 'Até:', 'desi-pet-shower' ) . ' ' . esc_html( $date_to );
            }
            echo esc_html( implode( ' | ', $filters ) );
            echo '</div>';
        }

        echo '<label for="dps-audit-entity-type">' . esc_html__( 'Tipo', 'desi-pet-shower' ) . '</label> ';
        echo '<select id="dps-audit-entity-type" name="entity_type">';
        echo '<option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $entity_types as $item_type ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $item_type ),
                selected( $entity_type, $item_type, false ),
                esc_html( DPS_Audit_Logger::get_entity_label( $item_type ) )
            );
        }
        echo '</select> ';

        echo '<label for="dps-audit-action">' . esc_html__( 'Ação', 'desi-pet-shower' ) . '</label> ';
        echo '<select id="dps-audit-action" name="action_filter">';
        echo '<option value="">' . esc_html__( 'Todas', 'desi-pet-shower' ) . '</option>';
        foreach ( $action_types as $item_action ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $item_action ),
                selected( $action, $item_action, false ),
                esc_html( DPS_Audit_Logger::get_action_label( $item_action ) )
            );
        }
        echo '</select> ';

        echo '<label for="dps-audit-date-from">' . esc_html__( 'De', 'desi-pet-shower' ) . '</label> ';
        printf(
            '<input type="date" id="dps-audit-date-from" name="date_from" value="%s" /> ',
            esc_attr( $date_from )
        );

        echo '<label for="dps-audit-date-to">' . esc_html__( 'Até', 'desi-pet-shower' ) . '</label> ';
        printf(
            '<input type="date" id="dps-audit-date-to" name="date_to" value="%s" /> ',
            esc_attr( $date_to )
        );

        submit_button( __( 'Filtrar', 'desi-pet-shower' ), 'secondary', '', false );
        echo '</form>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 20px;">';
        wp_nonce_field( 'dps_purge_audit_action', 'dps_purge_audit_nonce' );
        echo '<input type="hidden" name="action" value="dps_purge_audit" />';
        echo '<label for="dps-clear-audit-days">' . esc_html__( 'Remover registros com mais de (dias):', 'desi-pet-shower' ) . '</label> ';
        echo '<input type="number" id="dps-clear-audit-days" name="dps_clear_audit_days" value="90" min="1" max="365" /> ';
        submit_button( __( 'Limpar registros antigos', 'desi-pet-shower' ), 'delete', '', false );
        echo '</form>';

        if ( empty( $query_data['items'] ) ) {
            echo '<p>' . esc_html__( 'Nenhum registro de auditoria encontrado para os filtros informados.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="dps-table-wrapper">';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 160px;">' . esc_html__( 'Data/Hora', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 110px;">' . esc_html__( 'Tipo', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 60px;">' . esc_html__( 'ID', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 140px;">' . esc_html__( 'Ação', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 120px;">' . esc_html__( 'Usuário', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 120px;">' . esc_html__( 'IP', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Detalhes', 'desi-pet-shower' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $query_data['items'] as $item ) {
            echo '<tr>';

            // Data/Hora
            echo '<td>' . esc_html( $item->created_at ) . '</td>';

            // Tipo (entity_type badge)
            $entity_label = DPS_Audit_Logger::get_entity_label( $item->entity_type );
            echo '<td><span class="dps-badge" style="background-color: #e2e3e5; color: #383d41; padding: 2px 8px; border-radius: 3px; font-size: 12px;">'
                . esc_html( $entity_label )
                . '</span></td>';

            // ID (link to edit post if > 0)
            if ( $item->entity_id > 0 ) {
                echo '<td><a href="' . esc_url( get_edit_post_link( $item->entity_id ) ) . '">'
                    . esc_html( $item->entity_id )
                    . '</a></td>';
            } else {
                echo '<td>-</td>';
            }

            // Ação (color-coded badge)
            $action_label = DPS_Audit_Logger::get_action_label( $item->action );
            $badge_style  = self::get_action_badge_style( $item->action );
            echo '<td><span class="dps-badge" style="' . esc_attr( $badge_style ) . ' padding: 2px 8px; border-radius: 3px; font-size: 12px;">'
                . esc_html( $action_label )
                . '</span></td>';

            // Usuário
            if ( $item->user_id > 0 ) {
                $user = get_userdata( $item->user_id );
                $username = $user ? $user->user_login : '#' . $item->user_id;
                echo '<td>' . esc_html( $username ) . '</td>';
            } else {
                echo '<td>' . esc_html__( 'Sistema', 'desi-pet-shower' ) . '</td>';
            }

            // IP
            echo '<td>' . esc_html( $item->ip_address ?? '-' ) . '</td>';

            // Detalhes (JSON truncated)
            $details = (string) ( $item->details ?? '' );
            if ( ! empty( $details ) ) {
                $details_display = '<span class="dps-context-display" title="' . esc_attr( $details ) . '">';
                $details_display .= esc_html( mb_strlen( $details ) > 80 ? mb_substr( $details, 0, 80 ) . '...' : $details );
                $details_display .= '</span>';
                echo '<td>' . $details_display . '</td>';
            } else {
                echo '<td>-</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        $total_pages = (int) ceil( $query_data['total'] / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';

            $base_url        = remove_query_arg( 'paged' );
            $pagination_args = [
                'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '‹ ' . __( 'Anterior', 'desi-pet-shower' ),
                'next_text' => __( 'Próxima', 'desi-pet-shower' ) . ' ›',
                'type'      => 'plain',
                'end_size'  => 1,
                'mid_size'  => 2,
            ];

            echo paginate_links( $pagination_args );
            echo '</div></div>';
        }

        echo '</div>';
    }

    /**
     * Retorna estilos inline para o badge de ação.
     *
     * @since 2.6.0
     *
     * @param string $action Tipo da ação.
     *
     * @return string Estilos CSS inline.
     */
    private static function get_action_badge_style( $action ) {
        switch ( $action ) {
            case 'create':
                return 'background-color: #d4edda; color: #155724;';
            case 'update':
            case 'status_change':
                return 'background-color: #cce5ff; color: #004085;';
            case 'delete':
            case 'login_failed':
                return 'background-color: #f8d7da; color: #721c24;';
            case 'login':
            case 'token_generated':
            case 'token_revoked':
            case 'logout':
                return 'background-color: #d1ecf1; color: #0c5460;';
            default:
                return 'background-color: #fff3cd; color: #856404;';
        }
    }

    /**
     * Recupera os registros de auditoria com filtros e paginação.
     *
     * @since 2.6.0
     *
     * @param string $entity_type Tipo de entidade filtrado.
     * @param string $action      Ação filtrada.
     * @param string $date_from   Data inicial.
     * @param string $date_to     Data final.
     * @param int    $paged       Página atual.
     * @param int    $per_page    Registros por página.
     *
     * @return array
     */
    private static function query_logs( $entity_type, $action, $date_from, $date_to, $paged, $per_page ) {
        $offset = ( $paged - 1 ) * $per_page;

        $args = array(
            'limit'  => $per_page,
            'offset' => $offset,
        );

        if ( ! empty( $entity_type ) && in_array( $entity_type, DPS_Audit_Logger::get_entity_types(), true ) ) {
            $args['entity_type'] = $entity_type;
        }

        if ( ! empty( $action ) && in_array( $action, DPS_Audit_Logger::get_action_types(), true ) ) {
            $args['action'] = $action;
        }

        if ( ! empty( $date_from ) ) {
            $args['date_from'] = $date_from;
        }

        if ( ! empty( $date_to ) ) {
            $args['date_to'] = $date_to;
        }

        $items = DPS_Audit_Logger::get_logs( $args );
        $total = DPS_Audit_Logger::count_logs( $args );

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Remove registros antigos de auditoria.
     *
     * @since 2.6.0
     */
    public function handle_purge() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não possui permissão para executar esta ação.', 'desi-pet-shower' ) );
        }

        check_admin_referer( 'dps_purge_audit_action', 'dps_purge_audit_nonce' );

        $days = isset( $_POST['dps_clear_audit_days'] ) ? absint( $_POST['dps_clear_audit_days'] ) : 90;
        $days = $days > 0 ? $days : 90;

        if ( DPS_Audit_Logger::table_exists() ) {
            DPS_Audit_Logger::cleanup( $days );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'   => 'dps-audit',
                    'purged' => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}

if ( is_admin() ) {
    new DPS_Audit_Admin_Page();
}
