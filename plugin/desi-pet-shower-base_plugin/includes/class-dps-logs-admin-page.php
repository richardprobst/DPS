<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página administrativa para visualização de logs do DPS.
 */
class DPS_Logs_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_post_dps_purge_logs', array( $this, 'handle_purge' ) );
    }

    /**
     * Registra a página de Logs DPS no menu principal.
     */
    public function register_page() {
        add_menu_page(
            __( 'Logs DPS', 'desi-pet-shower' ),
            __( 'Logs DPS', 'desi-pet-shower' ),
            'manage_options',
            'dps-logs',
            array( $this, 'render_page' ),
            'dashicons-clipboard',
            56
        );
    }

    /**
     * Renderiza a listagem com filtros e paginação.
     */
    public function render_page() {
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
            $query_data = $this->query_logs( $level, $source, $paged, $per_page );
        }

        $current_url = admin_url( 'admin.php?page=dps-logs' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Logs DPS', 'desi-pet-shower' ) . '</h1>';

        if ( ! DPS_Logger::table_exists() ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'A tabela de logs ainda não existe. Reative o plugin para criar a estrutura.', 'desi-pet-shower' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( isset( $_GET['purged'] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs antigos removidos com sucesso.', 'desi-pet-shower' ) . '</p></div>';
        }

        echo '<form method="get" action="" style="margin-bottom: 16px;">';
        echo '<input type="hidden" name="page" value="dps-logs" />';

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

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Data/Hora (UTC)', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Nível', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Origem', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Mensagem', 'desi-pet-shower' ) . '</th>';
        echo '<th>' . esc_html__( 'Contexto', 'desi-pet-shower' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ( $query_data['items'] as $item ) {
            echo '<tr>';
            echo '<td>' . esc_html( $item['date_time'] ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $item['level'] ) ) . '</td>';
            echo '<td>' . esc_html( $item['source'] ) . '</td>';
            echo '<td>' . esc_html( $item['message'] ) . '</td>';
            echo '<td>' . esc_html( $item['context'] ? $item['context'] : '-' ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        $total_pages = (int) ceil( $query_data['total'] / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $url = add_query_arg(
                    array(
                        'page'   => 'dps-logs',
                        'level'  => $level,
                        'source' => $source,
                        'paged'  => $i,
                    ),
                    $current_url
                );
                $class = $i === $paged ? ' class="page-numbers current"' : ' class="page-numbers"';
                printf( '<a%1$s href="%2$s">%3$d</a> ', $class, esc_url( $url ), $i );
            }
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
    private function query_logs( $level, $source, $paged, $per_page ) {
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
