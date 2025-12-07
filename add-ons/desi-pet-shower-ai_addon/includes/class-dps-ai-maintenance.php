<?php
/**
 * Gerenciador de Manutenção Automática do AI Add-on.
 *
 * Responsável por:
 * - Limpeza automática de métricas e feedback antigos
 * - Limpeza de transients expirados relacionados à IA
 * - Agendamento de tarefas via WP-Cron
 *
 * @package DPS_AI_Addon
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Manutenção do AI Add-on.
 */
class DPS_AI_Maintenance {

    /**
     * Nome do evento do WP-Cron para limpeza diária.
     *
     * @var string
     */
    const CLEANUP_HOOK = 'dps_ai_daily_cleanup';

    /**
     * Período de retenção padrão em dias.
     *
     * @var int
     */
    const DEFAULT_RETENTION_DAYS = 365;

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Maintenance|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Maintenance
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
        // Registra o evento de limpeza no WP-Cron
        add_action( 'init', [ $this, 'schedule_cleanup' ] );
        
        // Registra o handler do evento de limpeza
        add_action( self::CLEANUP_HOOK, [ $this, 'run_cleanup' ] );
        
        // Registra handler AJAX para limpeza manual
        add_action( 'wp_ajax_dps_ai_manual_cleanup', [ $this, 'ajax_manual_cleanup' ] );
    }

    /**
     * Agenda o evento de limpeza diária se ainda não estiver agendado.
     *
     * @return void
     */
    public function schedule_cleanup() {
        if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
            // Calcula o próximo 03:00 (sempre no futuro)
            $current_time = current_time( 'timestamp' );
            $today_3am = strtotime( 'today 03:00', $current_time );
            
            // Se já passou das 03:00 hoje, agenda para amanhã
            if ( $current_time >= $today_3am ) {
                $next_run = strtotime( 'tomorrow 03:00', $current_time );
            } else {
                $next_run = $today_3am;
            }
            
            wp_schedule_event( $next_run, 'daily', self::CLEANUP_HOOK );
            dps_ai_log_info( 'Evento de limpeza automática agendado para ' . gmdate( 'd/m/Y H:i:s', $next_run ) );
        }
    }

    /**
     * Desagenda o evento de limpeza.
     * Chamado na desativação do plugin.
     *
     * @return void
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
            dps_ai_log_info( 'Evento de limpeza automática desagendado' );
        }
    }

    /**
     * Executa todas as rotinas de limpeza.
     *
     * @return array Resultado da limpeza com contadores.
     */
    public function run_cleanup() {
        dps_ai_log_info( 'Iniciando limpeza automática de dados do AI Add-on' );
        
        $results = [
            'metrics_deleted'    => 0,
            'feedback_deleted'   => 0,
            'transients_deleted' => 0,
        ];
        
        // Obtém período de retenção das configurações
        $settings = get_option( 'dps_ai_settings', [] );
        $retention_days = isset( $settings['data_retention_days'] ) ? absint( $settings['data_retention_days'] ) : self::DEFAULT_RETENTION_DAYS;
        
        // Executa limpezas
        $results['metrics_deleted']    = $this->cleanup_old_metrics( $retention_days );
        $results['feedback_deleted']   = $this->cleanup_old_feedback( $retention_days );
        $results['transients_deleted'] = $this->cleanup_expired_transients();
        
        dps_ai_log_info( 'Limpeza automática concluída', $results );
        
        return $results;
    }

    /**
     * Remove métricas mais antigas que o período de retenção.
     *
     * @param int $retention_days Número de dias para manter os dados.
     *
     * @return int Número de registros deletados.
     */
    public function cleanup_old_metrics( $retention_days ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . DPS_AI_Analytics::TABLE_NAME;
        $cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE date < %s",
                $cutoff_date
            )
        );
        
        if ( false === $deleted ) {
            dps_ai_log_error( 'Erro ao deletar métricas antigas' );
            return 0;
        }
        
        if ( $deleted > 0 ) {
            dps_ai_log_info( "Deletadas {$deleted} métricas anteriores a {$cutoff_date}" );
        }
        
        return absint( $deleted );
    }

    /**
     * Remove feedback mais antigo que o período de retenção.
     *
     * @param int $retention_days Número de dias para manter os dados.
     *
     * @return int Número de registros deletados.
     */
    public function cleanup_old_feedback( $retention_days ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . DPS_AI_Analytics::FEEDBACK_TABLE_NAME;
        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        if ( false === $deleted ) {
            dps_ai_log_error( 'Erro ao deletar feedback antigo' );
            return 0;
        }
        
        if ( $deleted > 0 ) {
            dps_ai_log_info( "Deletados {$deleted} registros de feedback anteriores a {$cutoff_date}" );
        }
        
        return absint( $deleted );
    }

    /**
     * Remove transients expirados relacionados à IA.
     *
     * Limpa transients de cache de contexto e rate limiting que já expiraram.
     *
     * @return int Número de transients deletados.
     */
    public function cleanup_expired_transients() {
        global $wpdb;
        
        // Busca transients expirados do AI Add-on
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $expired_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                AND option_name LIKE %s 
                AND option_value < %d",
                '_transient_timeout_%',
                '%dps_ai%',
                time()
            )
        );
        
        if ( empty( $expired_transients ) ) {
            return 0;
        }
        
        $deleted = 0;
        
        foreach ( $expired_transients as $timeout_key ) {
            // Remove _transient_timeout_ para obter o nome real do transient
            $transient_key = str_replace( '_transient_timeout_', '', $timeout_key );
            
            // Deleta o transient (isso também remove o timeout)
            if ( delete_transient( $transient_key ) ) {
                $deleted++;
            }
        }
        
        if ( $deleted > 0 ) {
            dps_ai_log_info( "Deletados {$deleted} transients expirados relacionados à IA" );
        }
        
        return $deleted;
    }

    /**
     * Handler AJAX para limpeza manual via interface admin.
     *
     * @return void
     */
    public function ajax_manual_cleanup() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_manual_cleanup' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ) ] );
        }
        
        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para executar esta ação.', 'dps-ai' ) ] );
        }
        
        // Executa limpeza
        $results = $this->run_cleanup();
        
        // Prepara mensagem de sucesso
        $message = sprintf(
            /* translators: 1: número de métricas, 2: número de feedbacks, 3: número de transients */
            __( 'Limpeza concluída com sucesso! Removidos: %1$d métricas, %2$d feedbacks, %3$d transients expirados.', 'dps-ai' ),
            $results['metrics_deleted'],
            $results['feedback_deleted'],
            $results['transients_deleted']
        );
        
        wp_send_json_success( [
            'message' => $message,
            'results' => $results,
        ] );
    }

    /**
     * Obtém estatísticas sobre o volume de dados armazenado.
     *
     * Útil para exibir na interface admin.
     *
     * @return array Estatísticas de uso.
     */
    public static function get_storage_stats() {
        global $wpdb;
        
        $metrics_table  = $wpdb->prefix . DPS_AI_Analytics::TABLE_NAME;
        $feedback_table = $wpdb->prefix . DPS_AI_Analytics::FEEDBACK_TABLE_NAME;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $metrics_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$metrics_table}" );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $feedback_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$feedback_table}" );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $oldest_metric = $wpdb->get_var( "SELECT MIN(date) FROM {$metrics_table}" );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $oldest_feedback = $wpdb->get_var( "SELECT MIN(created_at) FROM {$feedback_table}" );
        
        return [
            'metrics_count'    => absint( $metrics_count ),
            'feedback_count'   => absint( $feedback_count ),
            'oldest_metric'    => $oldest_metric ? gmdate( 'd/m/Y', strtotime( $oldest_metric ) ) : '-',
            'oldest_feedback'  => $oldest_feedback ? gmdate( 'd/m/Y H:i', strtotime( $oldest_feedback ) ) : '-',
        ];
    }
}
