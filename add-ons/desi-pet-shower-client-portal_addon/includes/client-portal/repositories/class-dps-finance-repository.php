<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositório para operações de dados relacionadas a finanças/transações.
 * 
 * Centraliza todas as consultas de dados financeiros (tabela dps_transacoes),
 * seguindo o padrão Repository para isolar lógica de acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Finance_Repository {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Finance_Repository|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Finance_Repository
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        // Nada a inicializar por enquanto
    }

    /**
     * Busca transações pendentes de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de objetos de transação.
     */
    public function get_pending_transactions_for_client( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente') ORDER BY data DESC",
            $client_id
        ) );
    }

    /**
     * Busca transações pagas de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @param int $limit     Limite de resultados (padrão: -1 = todos).
     * @return array Array de objetos de transação.
     */
    public function get_paid_transactions_for_client( $client_id, $limit = -1 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE cliente_id = %d AND status = 'pago' ORDER BY data DESC",
            $client_id
        );
        
        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( " LIMIT %d", $limit );
        }
        
        return $wpdb->get_results( $sql );
    }

    /**
     * Busca uma transação por ID.
     *
     * @param int $transaction_id ID da transação.
     * @return object|null Objeto da transação ou null se não encontrada.
     */
    public function get_transaction( $transaction_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $transaction_id
        ) );
    }

    /**
     * Verifica se uma transação pertence a um cliente.
     *
     * @param int $transaction_id ID da transação.
     * @param int $client_id      ID do cliente.
     * @return bool True se a transação pertence ao cliente.
     */
    public function transaction_belongs_to_client( $transaction_id, $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        $owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT cliente_id FROM {$table} WHERE id = %d",
            $transaction_id
        ) );
        
        return absint( $owner ) === absint( $client_id );
    }

    /**
     * Conta transações pendentes de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int Número de transações pendentes.
     */
    public function count_pending_transactions( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente')",
            $client_id
        ) );
        
        return absint( $count );
    }
}
