<?php
/**
 * Gerenciador de Analytics e Métricas do AI Add-on.
 *
 * Responsável por:
 * - Registrar uso da IA (perguntas, tokens, feedback)
 * - Gerenciar tabela de métricas
 * - Fornecer dados para dashboard de analytics
 *
 * @package DPS_AI_Addon
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Analytics da IA.
 */
class DPS_AI_Analytics {

    /**
     * Nome da tabela de métricas.
     *
     * @var string
     */
    const TABLE_NAME = 'dps_ai_metrics';

    /**
     * Nome da tabela de feedback.
     *
     * @var string
     */
    const FEEDBACK_TABLE_NAME = 'dps_ai_feedback';

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Analytics|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Analytics
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        // Registra handlers AJAX
        add_action( 'wp_ajax_dps_ai_submit_feedback', [ $this, 'ajax_submit_feedback' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_submit_feedback', [ $this, 'ajax_submit_feedback' ] );
    }

    /**
     * Cria as tabelas de métricas se não existirem.
     */
    public static function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $metrics_table   = $wpdb->prefix . self::TABLE_NAME;
        $feedback_table  = $wpdb->prefix . self::FEEDBACK_TABLE_NAME;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // IMPORTANTE: dbDelta() do WordPress tem requisitos estritos de formatação SQL:
        // - Exatamente 2 espaços entre 'PRIMARY KEY' e '(' (não 1)
        // - Usar 'KEY' em vez de 'INDEX' para índices secundários
        // - Um espaço após cada vírgula na definição de colunas
        // Ref: https://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

        // Tabela de métricas diárias
        $sql_metrics = "CREATE TABLE IF NOT EXISTS {$metrics_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            client_id BIGINT(20) UNSIGNED DEFAULT 0,
            questions_count INT(11) UNSIGNED DEFAULT 0,
            tokens_input INT(11) UNSIGNED DEFAULT 0,
            tokens_output INT(11) UNSIGNED DEFAULT 0,
            errors_count INT(11) UNSIGNED DEFAULT 0,
            avg_response_time FLOAT DEFAULT 0,
            model VARCHAR(50) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY date_client (date, client_id),
            KEY date_idx (date),
            KEY client_idx (client_id)
        ) {$charset_collate};";

        // Tabela de feedback individual
        $sql_feedback = "CREATE TABLE IF NOT EXISTS {$feedback_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT(20) UNSIGNED DEFAULT 0,
            question TEXT,
            answer TEXT,
            feedback ENUM('positive', 'negative') NOT NULL,
            comment TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY client_idx (client_id),
            KEY feedback_idx (feedback),
            KEY created_at_idx (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_metrics );
        dbDelta( $sql_feedback );
    }

    /**
     * Registra uma interação com a IA.
     *
     * @param int    $client_id     ID do cliente.
     * @param int    $tokens_input  Tokens de entrada.
     * @param int    $tokens_output Tokens de saída.
     * @param float  $response_time Tempo de resposta em segundos.
     * @param string $model         Modelo usado.
     * @param bool   $is_error      Se houve erro.
     */
    public static function log_interaction( $client_id, $tokens_input = 0, $tokens_output = 0, $response_time = 0, $model = '', $is_error = false ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $today      = current_time( 'Y-m-d' );

        // Tenta atualizar registro existente do dia
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, questions_count, tokens_input, tokens_output, errors_count, avg_response_time FROM {$table_name} WHERE date = %s AND client_id = %d",
                $today,
                $client_id
            )
        );

        if ( $existing ) {
            // Atualiza registro existente
            $new_questions   = $existing->questions_count + 1;
            $new_errors      = $existing->errors_count + ( $is_error ? 1 : 0 );
            $new_avg_time    = ( ( $existing->avg_response_time * $existing->questions_count ) + $response_time ) / $new_questions;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [
                    'questions_count' => $new_questions,
                    'tokens_input'    => $existing->tokens_input + $tokens_input,
                    'tokens_output'   => $existing->tokens_output + $tokens_output,
                    'errors_count'    => $new_errors,
                    'avg_response_time' => $new_avg_time,
                    'model'           => $model,
                ],
                [ 'id' => $existing->id ],
                [ '%d', '%d', '%d', '%d', '%f', '%s' ],
                [ '%d' ]
            );
        } else {
            // Insere novo registro
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $table_name,
                [
                    'date'            => $today,
                    'client_id'       => $client_id,
                    'questions_count' => 1,
                    'tokens_input'    => $tokens_input,
                    'tokens_output'   => $tokens_output,
                    'errors_count'    => $is_error ? 1 : 0,
                    'avg_response_time' => $response_time,
                    'model'           => $model,
                ],
                [ '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%s' ]
            );
        }
    }

    /**
     * Handler AJAX para submeter feedback.
     */
    public function ajax_submit_feedback() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_feedback' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ) ] );
        }

        $feedback  = isset( $_POST['feedback'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback'] ) ) : '';
        $question  = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
        $answer    = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';
        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

        if ( ! in_array( $feedback, [ 'positive', 'negative' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Feedback inválido.', 'dps-ai' ) ] );
        }

        $result = self::save_feedback( $client_id, $question, $answer, $feedback );

        if ( $result ) {
            wp_send_json_success( [ 'message' => __( 'Obrigado pelo seu feedback!', 'dps-ai' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Erro ao salvar feedback.', 'dps-ai' ) ] );
        }
    }

    /**
     * Salva feedback no banco de dados.
     *
     * @param int    $client_id ID do cliente.
     * @param string $question  Pergunta feita.
     * @param string $answer    Resposta da IA.
     * @param string $feedback  Tipo de feedback (positive/negative).
     *
     * @return bool True se salvo com sucesso.
     */
    public static function save_feedback( $client_id, $question, $answer, $feedback ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::FEEDBACK_TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $table_name,
            [
                'client_id' => $client_id,
                'question'  => $question,
                'answer'    => $answer,
                'feedback'  => $feedback,
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        return false !== $result;
    }

    /**
     * Registra feedback para o chat público ou portal.
     *
     * @param int    $client_id ID do cliente (0 para visitantes).
     * @param string $question  Pergunta feita.
     * @param string $feedback  Tipo de feedback (positive/negative).
     */
    public static function record_feedback( $client_id, $question, $feedback ) {
        self::save_feedback( $client_id, $question, '', $feedback );
    }

    /**
     * Registra uma interação do chat público.
     *
     * @param string $question      Pergunta feita.
     * @param string $answer        Resposta da IA.
     * @param int    $response_time Tempo de resposta em ms.
     * @param string $ip_address    Endereço IP do visitante.
     */
    public static function record_public_chat_interaction( $question, $answer, $response_time = 0, $ip_address = '' ) {
        // Obtém configurações
        $settings = get_option( 'dps_ai_settings', [] );
        $model    = $settings['model'] ?? 'gpt-4o-mini';

        // Estima tokens (aproximação simples)
        $tokens_input  = mb_strlen( $question ) / 4;
        $tokens_output = mb_strlen( $answer ) / 4;

        // Registra a interação com client_id = 0 (visitante)
        self::log_interaction(
            0, // client_id = 0 para visitantes do chat público
            (int) $tokens_input,
            (int) $tokens_output,
            $response_time / 1000, // Converte de ms para segundos
            $model,
            false
        );
    }

    /**
     * Obtém estatísticas gerais para o dashboard.
     *
     * @param string $start_date Data de início (Y-m-d).
     * @param string $end_date   Data de fim (Y-m-d).
     *
     * @return array Estatísticas.
     */
    public static function get_stats( $start_date = null, $end_date = null ) {
        global $wpdb;

        $metrics_table  = $wpdb->prefix . self::TABLE_NAME;
        $feedback_table = $wpdb->prefix . self::FEEDBACK_TABLE_NAME;

        // Datas padrão: últimos 30 dias
        if ( ! $start_date ) {
            $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        }
        if ( ! $end_date ) {
            $end_date = current_time( 'Y-m-d' );
        }

        // Estatísticas de métricas
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $metrics = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    SUM(questions_count) as total_questions,
                    SUM(tokens_input) as total_tokens_input,
                    SUM(tokens_output) as total_tokens_output,
                    SUM(errors_count) as total_errors,
                    AVG(avg_response_time) as avg_response_time,
                    COUNT(DISTINCT client_id) as unique_clients
                FROM {$metrics_table}
                WHERE date BETWEEN %s AND %s",
                $start_date,
                $end_date
            )
        );

        // Estatísticas de feedback
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $feedback = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN feedback = 'positive' THEN 1 ELSE 0 END) as positive_count,
                    SUM(CASE WHEN feedback = 'negative' THEN 1 ELSE 0 END) as negative_count
                FROM {$feedback_table}
                WHERE created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        // Dados por dia para gráfico
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $daily_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    date,
                    SUM(questions_count) as questions,
                    SUM(tokens_input + tokens_output) as tokens,
                    SUM(errors_count) as errors
                FROM {$metrics_table}
                WHERE date BETWEEN %s AND %s
                GROUP BY date
                ORDER BY date ASC",
                $start_date,
                $end_date
            )
        );

        return [
            'summary' => [
                'total_questions'    => absint( $metrics->total_questions ?? 0 ),
                'total_tokens_input' => absint( $metrics->total_tokens_input ?? 0 ),
                'total_tokens_output'=> absint( $metrics->total_tokens_output ?? 0 ),
                'total_errors'       => absint( $metrics->total_errors ?? 0 ),
                'avg_response_time'  => round( floatval( $metrics->avg_response_time ?? 0 ), 2 ),
                'unique_clients'     => absint( $metrics->unique_clients ?? 0 ),
                'positive_feedback'  => absint( $feedback->positive_count ?? 0 ),
                'negative_feedback'  => absint( $feedback->negative_count ?? 0 ),
            ],
            'daily'   => $daily_data,
        ];
    }

    /**
     * Obtém feedback recente para análise.
     *
     * @param int    $limit  Número de registros.
     * @param string $type   Tipo de feedback (positive/negative/all).
     *
     * @return array Lista de feedbacks.
     */
    public static function get_recent_feedback( $limit = 20, $type = 'all' ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::FEEDBACK_TABLE_NAME;

        // Usa prepared statement para o where clause
        if ( 'positive' === $type ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE feedback = %s ORDER BY created_at DESC LIMIT %d",
                    'positive',
                    $limit
                )
            );
        } elseif ( 'negative' === $type ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE feedback = %s ORDER BY created_at DESC LIMIT %d",
                    'negative',
                    $limit
                )
            );
        }

        // Todos os feedbacks
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Calcula custo estimado baseado em tokens.
     *
     * @param int    $tokens_input  Tokens de entrada.
     * @param int    $tokens_output Tokens de saída.
     * @param string $model         Modelo usado.
     *
     * @return float Custo estimado em USD.
     */
    public static function estimate_cost( $tokens_input, $tokens_output, $model = 'gpt-4o-mini' ) {
        // Preços por 1M tokens (dezembro 2024) - verificar periodicamente em https://openai.com/pricing
        $prices = [
            'gpt-4o-mini'   => [ 'input' => 0.15, 'output' => 0.60 ],
            'gpt-4o'        => [ 'input' => 2.50, 'output' => 10.00 ],
            'gpt-4-turbo'   => [ 'input' => 10.00, 'output' => 30.00 ],
            'gpt-3.5-turbo' => [ 'input' => 0.50, 'output' => 1.50 ],
        ];

        $model_prices = $prices[ $model ] ?? $prices['gpt-4o-mini'];

        $cost_input  = ( $tokens_input / 1000000 ) * $model_prices['input'];
        $cost_output = ( $tokens_output / 1000000 ) * $model_prices['output'];

        return $cost_input + $cost_output;
    }
}
