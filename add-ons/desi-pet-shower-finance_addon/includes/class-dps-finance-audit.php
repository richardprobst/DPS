<?php
/**
 * Gerencia auditoria de alterações financeiras.
 *
 * FASE 4 - F4.4: Auditoria de Alterações Financeiras
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por registrar e exibir logs de auditoria financeira.
 */
class DPS_Finance_Audit {

    /**
     * Nome da tabela de auditoria.
     *
     * @var string
     */
    const TABLE_NAME = 'dps_finance_audit_log';

    /**
     * Inicializa a classe de auditoria.
     */
    public static function init() {
        // Hook para exibir histórico de auditoria na UI
        add_action( 'admin_menu', [ __CLASS__, 'register_audit_page' ], 20 );
    }

    /**
     * Registra evento de auditoria.
     *
     * @since 1.6.0
     * @param int    $trans_id     ID da transação.
     * @param string $action       Tipo de ação (status_change, value_change, partial_add, manual_create).
     * @param array  $data         Dados da alteração (from_status, to_status, from_value, to_value, meta_info).
     * @return int|false ID do registro de auditoria ou false em caso de erro.
     */
    public static function log_event( $trans_id, $action, $data = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // Valida trans_id
        if ( ! $trans_id || ! is_numeric( $trans_id ) ) {
            return false;
        }

        // Prepara dados para inserção
        $insert_data = [
            'trans_id'    => (int) $trans_id,
            'user_id'     => get_current_user_id(), // 0 se for cron/webhook
            'action'      => sanitize_text_field( $action ),
            'from_status' => isset( $data['from_status'] ) ? sanitize_text_field( $data['from_status'] ) : null,
            'to_status'   => isset( $data['to_status'] ) ? sanitize_text_field( $data['to_status'] ) : null,
            'from_value'  => isset( $data['from_value'] ) ? sanitize_text_field( $data['from_value'] ) : null,
            'to_value'    => isset( $data['to_value'] ) ? sanitize_text_field( $data['to_value'] ) : null,
            'meta_info'   => isset( $data['meta_info'] ) ? wp_json_encode( $data['meta_info'] ) : null,
            'ip_address'  => self::get_client_ip(),
            'created_at'  => current_time( 'mysql' ),
        ];

        // Define tipos de dados
        $format = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        // Insere registro
        $result = $wpdb->insert( $table, $insert_data, $format );

        if ( $result === false ) {
            // Log error silently - não quebra fluxo principal
            error_log( 'DPS Finance Audit: Failed to log event for trans_id ' . $trans_id );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Obtém IP do cliente de forma segura.
     *
     * @since 1.6.0
     * @return string IP address ou 'unknown'.
     */
    private static function get_client_ip() {
        $ip = 'unknown';

        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        }

        // Valida formato de IP
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return 'unknown';
    }

    /**
     * Busca logs de auditoria.
     *
     * @since 1.6.0
     * @param array $args Argumentos de busca (trans_id, date_from, date_to, limit, offset).
     * @return array Array de objetos de log.
     */
    public static function get_logs( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // Verifica se tabela existe
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $table_exists ) {
            return [];
        }

        $defaults = [
            'trans_id'  => null,
            'date_from' => null,
            'date_to'   => null,
            'limit'     => 50,
            'offset'    => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        // Monta query
        $where = '1=1';
        $params = [];

        if ( $args['trans_id'] ) {
            $where .= ' AND trans_id = %d';
            $params[] = (int) $args['trans_id'];
        }

        if ( $args['date_from'] ) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }

        if ( $args['date_to'] ) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        // Query com limit e offset
        $query = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Conta total de logs de auditoria.
     *
     * @since 1.6.0
     * @param array $args Argumentos de filtro (trans_id, date_from, date_to).
     * @return int Total de registros.
     */
    public static function count_logs( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // Verifica se tabela existe
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $table_exists ) {
            return 0;
        }

        $where = '1=1';
        $params = [];

        if ( isset( $args['trans_id'] ) && $args['trans_id'] ) {
            $where .= ' AND trans_id = %d';
            $params[] = (int) $args['trans_id'];
        }

        if ( isset( $args['date_from'] ) && $args['date_from'] ) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }

        if ( isset( $args['date_to'] ) && $args['date_to'] ) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $query = "SELECT COUNT(*) FROM $table WHERE $where";

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params );
        }

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Registra página de auditoria no menu admin.
     *
     * @since 1.6.0
     */
    public static function register_audit_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Histórico de Auditoria Financeira', 'dps-finance-addon' ),
            __( 'Auditoria', 'dps-finance-addon' ),
            'manage_options',
            'dps-finance-audit',
            [ __CLASS__, 'render_audit_page' ]
        );
    }

    /**
     * Renderiza página de auditoria.
     *
     * @since 1.6.0
     */
    public static function render_audit_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-finance-addon' ) );
        }

        // Filtros
        $trans_id  = isset( $_GET['trans_id'] ) ? intval( $_GET['trans_id'] ) : null;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
        $page_num  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

        $per_page = 20;
        $offset = ( $page_num - 1 ) * $per_page;

        $args = [
            'trans_id'  => $trans_id,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'limit'     => $per_page,
            'offset'    => $offset,
        ];

        $logs = self::get_logs( $args );
        $total = self::count_logs( $args );
        $total_pages = ceil( $total / $per_page );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Histórico de Auditoria Financeira', 'dps-finance-addon' ); ?></h1>
            <p><?php esc_html_e( 'Registro de todas as alterações realizadas nas transações financeiras.', 'dps-finance-addon' ); ?></p>

            <!-- Filtros -->
            <form method="get" class="dps-finance-audit-filters">
                <input type="hidden" name="page" value="dps-finance-audit">
                
                <label>
                    <?php esc_html_e( 'ID da Transação:', 'dps-finance-addon' ); ?>
                    <input type="number" name="trans_id" value="<?php echo esc_attr( $trans_id ?: '' ); ?>" min="1" style="width: 100px;">
                </label>

                <label>
                    <?php esc_html_e( 'Data de:', 'dps-finance-addon' ); ?>
                    <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
                </label>

                <label>
                    <?php esc_html_e( 'Data até:', 'dps-finance-addon' ); ?>
                    <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
                </label>

                <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'dps-finance-addon' ); ?></button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-finance-audit' ) ); ?>" class="button"><?php esc_html_e( 'Limpar', 'dps-finance-addon' ); ?></a>
            </form>

            <!-- Tabela de logs -->
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Data/Hora', 'dps-finance-addon' ); ?></th>
                        <th><?php esc_html_e( 'Transação', 'dps-finance-addon' ); ?></th>
                        <th><?php esc_html_e( 'Usuário', 'dps-finance-addon' ); ?></th>
                        <th><?php esc_html_e( 'Ação', 'dps-finance-addon' ); ?></th>
                        <th><?php esc_html_e( 'De → Para', 'dps-finance-addon' ); ?></th>
                        <th><?php esc_html_e( 'IP', 'dps-finance-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $logs ) ) : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $log->created_at ) ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=desi-pet-shower#financeiro' ) ); ?>">
                                        #<?php echo esc_html( $log->trans_id ); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    if ( $log->user_id > 0 ) {
                                        $user = get_userdata( $log->user_id );
                                        echo $user ? esc_html( $user->display_name ) : esc_html__( 'Usuário #', 'dps-finance-addon' ) . esc_html( $log->user_id );
                                    } else {
                                        esc_html_e( 'Sistema', 'dps-finance-addon' );
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( self::get_action_label( $log->action ) ); ?></td>
                                <td>
                                    <?php
                                    $from = $log->from_status ?: $log->from_value;
                                    $to = $log->to_status ?: $log->to_value;
                                    if ( $from || $to ) {
                                        echo esc_html( $from ?: '—' ) . ' → ' . esc_html( $to ?: '—' );
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( $log->ip_address ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <?php esc_html_e( 'Nenhum registro de auditoria encontrado.', 'dps-finance-addon' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginação -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = add_query_arg( [
                            'page'      => 'dps-finance-audit',
                            'trans_id'  => $trans_id,
                            'date_from' => $date_from,
                            'date_to'   => $date_to,
                        ], admin_url( 'admin.php' ) );

                        echo paginate_links( [
                            'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                            'format'    => '',
                            'current'   => $page_num,
                            'total'     => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ] );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Retorna label amigável para tipo de ação.
     *
     * @since 1.6.0
     * @param string $action Tipo de ação.
     * @return string Label traduzida.
     */
    private static function get_action_label( $action ) {
        $labels = [
            'status_change'        => __( 'Mudança de Status', 'dps-finance-addon' ),
            'value_change'         => __( 'Alteração de Valor', 'dps-finance-addon' ),
            'partial_add'          => __( 'Pagamento Parcial Adicionado', 'dps-finance-addon' ),
            'manual_create'        => __( 'Criação Manual', 'dps-finance-addon' ),
            'status_change_webhook'=> __( 'Status Atualizado via Webhook', 'dps-finance-addon' ),
        ];

        return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
    }
}

// Inicializa
DPS_Finance_Audit::init();
