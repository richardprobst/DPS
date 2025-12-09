<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página administrativa para visualização de logs do DPS by PRObst.
 */
class DPS_Logs_Admin_Page {

    public function __construct() {
        // Prioridade 20 garante que o menu pai 'desi-pet-shower' já foi registrado pelo plugin base.
        add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
        add_action( 'admin_post_dps_purge_logs', array( $this, 'handle_purge' ) );
    }

    /**
     * Registra a página de Logs do sistema como submenu de "DPS by PRObst".
     * 
     * NOTA: A partir da v1.1.0, este menu está oculto (parent=null) para backward compatibility.
     * Use o novo hub unificado em dps-system-hub para acessar via aba "Logs".
     */
    public function register_page() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'Logs do Sistema', 'desi-pet-shower' ),
            __( 'Logs do Sistema', 'desi-pet-shower' ),
            'manage_options',
            'dps-logs',
            array( $this, 'render_page' )
        );
    }

    /**
     * Renderiza a listagem com filtros e paginação.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        $level  = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
        $source = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
        $paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $levels     = DPS_Logger::get_levels();
        $per_page   = 20;
        $query_data = array();

        if ( DPS_Logger::table_exists() ) {
            $query_data = self::query_logs( $level, $source, $paged, $per_page );
        }

        $current_url = admin_url( 'admin.php?page=dps-logs' );

        echo '<div class="wrap dps-admin-page">';
        echo '<h1>' . esc_html__( 'Logs do Sistema', 'desi-pet-shower' ) . '</h1>';

        if ( ! DPS_Logger::table_exists() ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'A tabela de logs ainda não existe. Reative o plugin para criar a estrutura.', 'desi-pet-shower' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( isset( $_GET['purged'] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs antigos removidos com sucesso.', 'desi-pet-shower' ) . '</p></div>';
        }

        echo '<div class="dps-filter-container">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="dps-logs" />';

        // Indicação de filtros ativos
        if ( ! empty( $level ) || ! empty( $source ) ) {
            echo '<div class="dps-active-filters">';
            echo '<span class="dps-active-filters-icon">🔍</span>';
            echo esc_html__( 'Filtros ativos:', 'desi-pet-shower' ) . ' ';
            $filters = [];
            if ( $level ) {
                $filters[] = esc_html__( 'Nível:', 'desi-pet-shower' ) . ' ' . esc_html( ucfirst( $level ) );
            }
            if ( $source ) {
                $filters[] = esc_html__( 'Origem:', 'desi-pet-shower' ) . ' ' . esc_html( $source );
            }
            echo esc_html( implode( ' | ', $filters ) );
            echo '</div>';
        }

        echo '<label for="dps-log-level">' . esc_html__( 'Nível', 'desi-pet-shower' ) . '</label> ';
        echo '<select id="dps-log-level" name="level">';
        echo '<option value="">' . esc_html__( 'Todos', 'desi-pet-shower' ) . '</option>';
        foreach ( $levels as $item_level ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $item_level ),
                selected( $level, $item_level, false ),
                esc_html( ucfirst( $item_level ) )
            );
        }
        echo '</select> ';

        echo '<label for="dps-log-source">' . esc_html__( 'Origem', 'desi-pet-shower' ) . '</label> ';
        printf(
            '<input type="text" id="dps-log-source" name="source" value="%s" placeholder="base, finance, payment" /> ',
            esc_attr( $source )
        );

        submit_button( __( 'Filtrar', 'desi-pet-shower' ), 'secondary', '', false );
        echo '</form>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 20px;">';
        wp_nonce_field( 'dps_purge_logs_action', 'dps_purge_logs_nonce' );
        echo '<input type="hidden" name="action" value="dps_purge_logs" />';
        echo '<label for="dps-clear-logs-days">' . esc_html__( 'Remover logs com mais de (dias):', 'desi-pet-shower' ) . '</label> ';
        echo '<input type="number" id="dps-clear-logs-days" name="dps_clear_logs_days" value="30" min="1" max="365" /> ';
        submit_button( __( 'Limpar logs antigos', 'desi-pet-shower' ), 'delete', '', false );
        echo '</form>';

        if ( empty( $query_data['items'] ) ) {
            echo '<p>' . esc_html__( 'Nenhum log encontrado para os filtros informados.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="dps-table-wrapper">';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 180px;">' . esc_html__( 'Data/Hora (UTC)', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 100px;">' . esc_html__( 'Nível', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 120px;">' . esc_html__( 'Origem', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Mensagem', 'desi-pet-shower' ) . '</th>';
        echo '<th style="width: 200px;">' . esc_html__( 'Contexto', 'desi-pet-shower' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $query_data['items'] as $item ) {
            echo '<tr>';
            echo '<td>' . esc_html( $item['date_time'] ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $item['level'] ) ) . '</td>';
            echo '<td>' . esc_html( $item['source'] ) . '</td>';
            
            // Truncar mensagens longas
            $message = $item['message'];
            $message_display = esc_html( $message );
            if ( mb_strlen( $message ) > 100 ) {
                $message_display = '<span class="dps-truncated-text" title="' . esc_attr( $message ) . '">';
                $message_display .= esc_html( mb_substr( $message, 0, 100 ) ) . '...';
                $message_display .= '</span>';
            } else {
                // $message_display is already escaped, no need to escape again.
            }
            echo '<td>' . $message_display . '</td>';
            
            // Formatar contexto (normalmente JSON)
            $context = $item['context'];
            if ( ! empty( $context ) ) {
                $context_display = '<span class="dps-context-display" title="' . esc_attr( $context ) . '">';
                $context_display .= esc_html( mb_strlen( $context ) > 80 ? mb_substr( $context, 0, 80 ) . '...' : $context );
                $context_display .= '</span>';
                echo '<td>' . $context_display . '</td>';
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
            
            $base_url = remove_query_arg( 'paged' );
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
     * Recupera os logs com filtros e paginação.
     *
     * @param string $level    Nível filtrado.
     * @param string $source   Origem filtrada.
     * @param int    $paged    Página atual.
     * @param int    $per_page Registros por página.
     *
     * @return array
     */
    private static function query_logs( $level, $source, $paged, $per_page ) {
        global $wpdb;

        $table       = DPS_Logger::get_table_name();
        $where       = array();
        $params      = array();
        $offset      = ( $paged - 1 ) * $per_page;
        $valid_level = in_array( $level, DPS_Logger::get_levels(), true );

        if ( $valid_level ) {
            $where[]  = 'level = %s';
            $params[] = $level;
        }

        if ( ! empty( $source ) ) {
            $where[]  = 'source = %s';
            $params[] = $source;
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $query_params = array_merge( $params, array( $per_page, $offset ) );

        $sql   = $wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY date_time DESC LIMIT %d OFFSET %d", $query_params );
        $items = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $where_sql ) ) {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        } else {
            $count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params );
            $total     = (int) $wpdb->get_var( $count_sql );
        }

        return array(
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * Limpa logs antigos respeitando o número de dias informado.
     */
    public function handle_purge() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não possui permissão para executar esta ação.', 'desi-pet-shower' ) );
        }

        check_admin_referer( 'dps_purge_logs_action', 'dps_purge_logs_nonce' );

        $days = isset( $_POST['dps_clear_logs_days'] ) ? absint( $_POST['dps_clear_logs_days'] ) : 30;
        $days = $days > 0 ? $days : 30;

        if ( DPS_Logger::table_exists() ) {
            global $wpdb;

            $table_name = DPS_Logger::get_table_name();
            $threshold  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE date_time < %s", $threshold ) );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'   => 'dps-logs',
                    'purged' => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}

if ( is_admin() ) {
    new DPS_Logs_Admin_Page();
}
