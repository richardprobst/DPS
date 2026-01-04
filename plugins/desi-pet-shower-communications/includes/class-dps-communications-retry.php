<?php
/**
 * Gerenciador de retry com exponential backoff
 *
 * Esta classe implementa lógica de retry com exponential backoff
 * para falhas de envio de comunicações.
 *
 * @package DesiPetShower
 * @subpackage Communications
 * @since 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de retry com exponential backoff
 */
class DPS_Communications_Retry {

    /**
     * Máximo de tentativas de retry
     */
    const MAX_RETRIES = 5;

    /**
     * Base do backoff em segundos
     */
    const BACKOFF_BASE = 60; // 1 minuto

    /**
     * Multiplicador do exponential backoff
     */
    const BACKOFF_MULTIPLIER = 2;

    /**
     * Jitter máximo em segundos (para evitar thundering herd)
     */
    const JITTER_MAX = 30;

    /**
     * Instância singleton
     *
     * @var DPS_Communications_Retry|null
     */
    private static $instance = null;

    /**
     * Obtém instância singleton
     *
     * @return DPS_Communications_Retry
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        // Registra o handler do cron de retry
        add_action( 'dps_comm_retry_send', [ $this, 'process_retry' ], 10, 1 );

        // Cron de limpeza de retries expirados (diário)
        add_action( 'dps_comm_cleanup_expired_retries', [ $this, 'cleanup_expired_retries' ] );

        // Agenda cron de limpeza se não existir
        if ( ! wp_next_scheduled( 'dps_comm_cleanup_expired_retries' ) ) {
            wp_schedule_event( time(), 'daily', 'dps_comm_cleanup_expired_retries' );
        }
    }

    /**
     * Agenda um retry para uma comunicação que falhou
     *
     * @param int    $history_id    ID do registro no histórico
     * @param string $channel       Canal (whatsapp, email, sms)
     * @param string $recipient     Destinatário
     * @param string $message       Mensagem
     * @param array  $context       Contexto adicional
     * @param int    $retry_count   Número atual de tentativas
     * @param string $last_error    Último erro ocorrido
     * @return bool                 True se agendado, false se excedeu limite
     */
    public function schedule_retry( $history_id, $channel, $recipient, $message, $context, $retry_count, $last_error = '' ) {
        $history_id  = absint( $history_id );
        $retry_count = absint( $retry_count );

        // Verifica se excedeu limite de retries
        if ( $retry_count >= self::MAX_RETRIES ) {
            $this->mark_as_permanently_failed( $history_id, $last_error );
            return false;
        }

        // Calcula delay com exponential backoff + jitter
        $delay = $this->calculate_backoff_delay( $retry_count );

        // Prepara dados para o retry
        $retry_data = [
            'history_id'  => $history_id,
            'channel'     => $channel,
            'recipient'   => $recipient,
            'message'     => $message,
            'context'     => $context,
            'retry_count' => $retry_count + 1,
            'last_error'  => $last_error,
        ];

        // Salva dados do retry em transient (expira em 1 dia)
        $transient_key = 'dps_comm_retry_' . $history_id;
        set_transient( $transient_key, $retry_data, DAY_IN_SECONDS );

        // Agenda o cron de retry
        $scheduled_time = time() + $delay;
        wp_schedule_single_event( $scheduled_time, 'dps_comm_retry_send', [ $history_id ] );

        // Atualiza status no histórico
        if ( class_exists( 'DPS_Communications_History' ) ) {
            $history = DPS_Communications_History::get_instance();
            $history->update_status( $history_id, DPS_Communications_History::STATUS_RETRYING, [
                'retry_count' => $retry_count + 1,
                'last_error'  => $last_error,
            ] );
        }

        // Log do agendamento
        $this->safe_log( 'info', sprintf(
            'Communications Retry: Agendado retry #%d para ID %d em %d segundos',
            $retry_count + 1,
            $history_id,
            $delay
        ) );

        return true;
    }

    /**
     * Processa o retry de uma comunicação
     *
     * @param int $history_id ID do registro no histórico
     */
    public function process_retry( $history_id ) {
        $history_id    = absint( $history_id );
        $transient_key = 'dps_comm_retry_' . $history_id;
        $retry_data    = get_transient( $transient_key );

        if ( ! $retry_data ) {
            $this->safe_log( 'warning', sprintf(
                'Communications Retry: Dados de retry não encontrados para ID %d',
                $history_id
            ) );
            return;
        }

        // Remove transient
        delete_transient( $transient_key );

        // Tenta enviar novamente
        $api    = DPS_Communications_API::get_instance();
        $result = false;

        switch ( $retry_data['channel'] ) {
            case 'whatsapp':
                $result = $api->send_whatsapp(
                    $retry_data['recipient'],
                    $retry_data['message'],
                    array_merge( $retry_data['context'], [ 'is_retry' => true, 'retry_count' => $retry_data['retry_count'] ] )
                );
                break;

            case 'email':
                $subject = isset( $retry_data['context']['subject'] ) ? $retry_data['context']['subject'] : '';
                $result  = $api->send_email(
                    $retry_data['recipient'],
                    $subject,
                    $retry_data['message'],
                    array_merge( $retry_data['context'], [ 'is_retry' => true, 'retry_count' => $retry_data['retry_count'] ] )
                );
                break;

            default:
                $this->safe_log( 'error', sprintf(
                    'Communications Retry: Canal desconhecido: %s',
                    $retry_data['channel']
                ) );
                return;
        }

        // Se ainda falhou, agenda próximo retry
        if ( ! $result ) {
            $this->schedule_retry(
                $history_id,
                $retry_data['channel'],
                $retry_data['recipient'],
                $retry_data['message'],
                $retry_data['context'],
                $retry_data['retry_count'],
                $retry_data['last_error']
            );
        } else {
            // Sucesso - atualiza histórico
            if ( class_exists( 'DPS_Communications_History' ) ) {
                $history = DPS_Communications_History::get_instance();
                $history->update_status( $history_id, DPS_Communications_History::STATUS_SENT );
            }

            $this->safe_log( 'info', sprintf(
                'Communications Retry: Sucesso no retry #%d para ID %d',
                $retry_data['retry_count'],
                $history_id
            ) );
        }
    }

    /**
     * Calcula o delay do backoff exponencial com jitter
     *
     * @param int $retry_count Número atual de tentativas
     * @return int Delay em segundos
     */
    private function calculate_backoff_delay( $retry_count ) {
        // Exponential backoff: base * multiplier^retry_count
        $delay = self::BACKOFF_BASE * pow( self::BACKOFF_MULTIPLIER, $retry_count );

        // Adiciona jitter aleatório para evitar thundering herd
        $jitter = wp_rand( 0, self::JITTER_MAX );
        $delay += $jitter;

        // Cap máximo de 1 hora
        return min( $delay, HOUR_IN_SECONDS );
    }

    /**
     * Marca uma comunicação como permanentemente falha
     *
     * @param int    $history_id ID do registro
     * @param string $last_error Último erro
     */
    private function mark_as_permanently_failed( $history_id, $last_error ) {
        if ( class_exists( 'DPS_Communications_History' ) ) {
            $history = DPS_Communications_History::get_instance();
            $history->update_status( $history_id, DPS_Communications_History::STATUS_FAILED, [
                'last_error' => sprintf(
                    __( 'Falha permanente após %d tentativas. Último erro: %s', 'dps-communications-addon' ),
                    self::MAX_RETRIES,
                    $last_error
                ),
            ] );
        }

        $this->safe_log( 'error', sprintf(
            'Communications Retry: Falha permanente para ID %d após %d tentativas',
            $history_id,
            self::MAX_RETRIES
        ) );

        // Dispara hook para notificar falha permanente
        do_action( 'dps_comm_permanent_failure', $history_id, $last_error );
    }

    /**
     * Limpa retries expirados (transients órfãos)
     */
    public function cleanup_expired_retries() {
        global $wpdb;

        // Remove transients de retry expirados (timeout já passou)
        $timeout_pattern = $wpdb->esc_like( '_transient_timeout_dps_comm_retry_' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %s",
                $timeout_pattern,
                time()
            )
        );

        $this->safe_log( 'info', 'Communications Retry: Limpeza de retries expirados concluída' );
    }

    /**
     * Obtém estatísticas de retries
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        // Conta transients de retry ativos
        $like_pattern = $wpdb->esc_like( '_transient_dps_comm_retry_' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_retries = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        return [
            'pending_retries' => (int) $pending_retries,
            'max_retries'     => self::MAX_RETRIES,
            'backoff_base'    => self::BACKOFF_BASE,
        ];
    }

    /**
     * Log seguro verificando disponibilidade do DPS_Logger
     *
     * @param string $level   Nível do log
     * @param string $message Mensagem
     */
    private function safe_log( $level, $message ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, $message );
        }
    }
}
