<?php
/**
 * Gerencia API REST para consultas financeiras.
 *
 * FASE 4 - F4.5: API REST de Consulta Financeira (Somente Leitura)
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por expor endpoints REST para consulta de dados financeiros.
 */
class DPS_Finance_REST {

    /**
     * Namespace da API.
     *
     * @var string
     */
    const NAMESPACE = 'dps-finance/v1';

    /**
     * Inicializa a API REST.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Registra rotas REST.
     *
     * @since 1.6.0
     */
    public static function register_routes() {
        // GET /dps-finance/v1/transactions
        register_rest_route( self::NAMESPACE, '/transactions', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_transactions' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args'                => self::get_transactions_args(),
        ] );

        // GET /dps-finance/v1/transactions/{id}
        register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_transaction' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args'                => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
            ],
        ] );

        // GET /dps-finance/v1/summary
        register_rest_route( self::NAMESPACE, '/summary', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_summary' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args'                => self::get_summary_args(),
        ] );
    }

    /**
     * Verifica permissão de acesso à API.
     *
     * @since 1.6.0
     * @return bool True se autorizado.
     */
    public static function check_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Argumentos para endpoint /transactions.
     *
     * @since 1.6.0
     * @return array Argumentos de validação.
     */
    private static function get_transactions_args() {
        return [
            'status' => [
                'description'       => __( 'Filtrar por status', 'dps-finance-addon' ),
                'type'              => 'string',
                'enum'              => [ 'em_aberto', 'pago', 'cancelado' ],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_from' => [
                'description'       => __( 'Data inicial (Y-m-d)', 'dps-finance-addon' ),
                'type'              => 'string',
                'format'            => 'date',
                'validate_callback' => [ __CLASS__, 'validate_date' ],
            ],
            'date_to' => [
                'description'       => __( 'Data final (Y-m-d)', 'dps-finance-addon' ),
                'type'              => 'string',
                'format'            => 'date',
                'validate_callback' => [ __CLASS__, 'validate_date' ],
            ],
            'customer' => [
                'description'       => __( 'ID do cliente', 'dps-finance-addon' ),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'description'       => __( 'Página atual', 'dps-finance-addon' ),
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description'       => __( 'Itens por página', 'dps-finance-addon' ),
                'type'              => 'integer',
                'default'           => 20,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Argumentos para endpoint /summary.
     *
     * @since 1.6.0
     * @return array Argumentos de validação.
     */
    private static function get_summary_args() {
        return [
            'period' => [
                'description'       => __( 'Período predefinido', 'dps-finance-addon' ),
                'type'              => 'string',
                'enum'              => [ 'current_month', 'last_month', 'custom' ],
                'default'           => 'current_month',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_from' => [
                'description'       => __( 'Data inicial para período customizado', 'dps-finance-addon' ),
                'type'              => 'string',
                'format'            => 'date',
                'validate_callback' => [ __CLASS__, 'validate_date' ],
            ],
            'date_to' => [
                'description'       => __( 'Data final para período customizado', 'dps-finance-addon' ),
                'type'              => 'string',
                'format'            => 'date',
                'validate_callback' => [ __CLASS__, 'validate_date' ],
            ],
        ];
    }

    /**
     * Valida formato de data.
     *
     * @since 1.6.0
     * @param string $date Data a validar.
     * @return bool True se válida.
     */
    public static function validate_date( $date ) {
        if ( empty( $date ) ) {
            return true; // Opcional
        }

        $parsed = date_parse( $date );
        return $parsed && $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }

    /**
     * Retorna lista de transações.
     *
     * @since 1.6.0
     * @param WP_REST_Request $request Requisição REST.
     * @return WP_REST_Response Resposta formatada.
     */
    public static function get_transactions( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Parâmetros
        $status    = $request->get_param( 'status' );
        $date_from = $request->get_param( 'date_from' );
        $date_to   = $request->get_param( 'date_to' );
        $customer  = $request->get_param( 'customer' );
        $page      = max( 1, (int) $request->get_param( 'page' ) );
        $per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

        // Monta query
        $where = '1=1';
        $params = [];

        if ( $status ) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }

        if ( $date_from ) {
            $where .= ' AND data >= %s';
            $params[] = $date_from;
        }

        if ( $date_to ) {
            $where .= ' AND data <= %s';
            $params[] = $date_to;
        }

        if ( $customer ) {
            $where .= ' AND cliente_id = %d';
            $params[] = $customer;
        }

        // Conta total
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where";
        if ( ! empty( $params ) ) {
            $count_query = $wpdb->prepare( $count_query, $params );
        }
        $total = (int) $wpdb->get_var( $count_query );

        // Paginação
        $offset = ( $page - 1 ) * $per_page;
        $query = "SELECT * FROM $table WHERE $where ORDER BY data DESC, id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params );
        }

        $transactions = $wpdb->get_results( $query );

        // Formata resposta
        $data = [];
        foreach ( $transactions as $trans ) {
            $data[] = self::format_transaction( $trans );
        }

        $response = new WP_REST_Response( $data );

        // Headers de paginação
        $total_pages = ceil( $total / $per_page );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', $total_pages );

        return $response;
    }

    /**
     * Retorna detalhes de uma transação específica.
     *
     * @since 1.6.0
     * @param WP_REST_Request $request Requisição REST.
     * @return WP_REST_Response|WP_Error Resposta ou erro.
     */
    public static function get_transaction( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $id = (int) $request->get_param( 'id' );

        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( ! $trans ) {
            return new WP_Error( 'not_found', __( 'Transação não encontrada.', 'dps-finance-addon' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( self::format_transaction( $trans, true ) );
    }

    /**
     * Retorna resumo financeiro por período.
     *
     * @since 1.6.0
     * @param WP_REST_Request $request Requisição REST.
     * @return WP_REST_Response Resposta formatada.
     */
    public static function get_summary( $request ) {
        $period    = $request->get_param( 'period' );
        $date_from = $request->get_param( 'date_from' );
        $date_to   = $request->get_param( 'date_to' );

        // Determina período
        switch ( $period ) {
            case 'current_month':
                $date_from = date( 'Y-m-01' );
                $date_to   = date( 'Y-m-t' );
                break;

            case 'last_month':
                $date_from = date( 'Y-m-01', strtotime( '-1 month' ) );
                $date_to   = date( 'Y-m-t', strtotime( '-1 month' ) );
                break;

            case 'custom':
                // Usa parâmetros fornecidos
                if ( ! $date_from || ! $date_to ) {
                    return new WP_Error( 
                        'missing_dates', 
                        __( 'Para período customizado, forneça date_from e date_to.', 'dps-finance-addon' ), 
                        [ 'status' => 400 ] 
                    );
                }
                break;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Calcula totais
        $receitas = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'pago' 
             AND data >= %s 
             AND data <= %s",
            $date_from,
            $date_to
        ) );

        $despesas = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM $table 
             WHERE tipo = 'despesa' 
             AND status = 'pago' 
             AND data >= %s 
             AND data <= %s",
            $date_from,
            $date_to
        ) );

        $pendente = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'em_aberto' 
             AND data >= %s 
             AND data <= %s",
            $date_from,
            $date_to
        ) );

        $receitas = (float) ( $receitas ?: 0 );
        $despesas = (float) ( $despesas ?: 0 );
        $pendente = (float) ( $pendente ?: 0 );
        $resultado = $receitas - $despesas;

        return new WP_REST_Response( [
            'period' => [
                'type'      => $period,
                'date_from' => $date_from,
                'date_to'   => $date_to,
            ],
            'summary' => [
                'total_receitas' => $receitas,
                'total_despesas' => $despesas,
                'total_pendente' => $pendente,
                'resultado'      => $resultado,
            ],
            'formatted' => [
                'total_receitas' => self::format_money( $receitas ),
                'total_despesas' => self::format_money( $despesas ),
                'total_pendente' => self::format_money( $pendente ),
                'resultado'      => self::format_money( $resultado ),
            ],
        ] );
    }

    /**
     * Formata objeto de transação para API.
     *
     * @since 1.6.0
     * @param object $trans   Objeto da transação.
     * @param bool   $detailed Incluir detalhes extras.
     * @return array Transação formatada.
     */
    private static function format_transaction( $trans, $detailed = false ) {
        // Nome do cliente
        $cliente_nome = '';
        if ( $trans->cliente_id ) {
            $client = get_post( $trans->cliente_id );
            $cliente_nome = $client ? $client->post_title : '';
        }

        $data = [
            'id'               => (int) $trans->id,
            'cliente_id'       => (int) $trans->cliente_id,
            'cliente_nome'     => $cliente_nome,
            'agendamento_id'   => (int) $trans->agendamento_id,
            'data'             => $trans->data,
            'valor'            => (float) $trans->valor,
            'valor_formatado'  => self::format_money( (float) $trans->valor ),
            'categoria'        => $trans->categoria,
            'tipo'             => $trans->tipo,
            'status'           => $trans->status,
            'descricao'        => $trans->descricao,
        ];

        // Campos detalhados
        if ( $detailed ) {
            $data['created_at'] = $trans->created_at ?? null;
            $data['updated_at'] = $trans->updated_at ?? null;

            // Link de pagamento (se existir)
            if ( $trans->agendamento_id ) {
                $data['payment_link'] = get_post_meta( $trans->agendamento_id, 'dps_payment_link', true );
            }
        }

        return $data;
    }

    /**
     * Formata valor monetário.
     *
     * @since 1.6.0
     * @param float $value Valor numérico.
     * @return string Valor formatado.
     */
    private static function format_money( $value ) {
        return DPS_Money_Helper::format_currency_from_decimal( $value );
    }
}

// Inicializa
DPS_Finance_REST::init();
