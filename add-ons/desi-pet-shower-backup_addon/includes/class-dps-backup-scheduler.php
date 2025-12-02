<?php
/**
 * Classe de agendamento de backups automáticos.
 *
 * Gerencia cron jobs para backups automáticos.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Backup_Scheduler
 *
 * @since 1.1.0
 */
class DPS_Backup_Scheduler {

    /**
     * Nome do hook do cron.
     *
     * @var string
     */
    const CRON_HOOK = 'dps_scheduled_backup';

    /**
     * Inicializa o scheduler.
     *
     * @since 1.1.0
     */
    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_scheduled_backup' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );
    }

    /**
     * Adiciona schedules customizados ao cron do WordPress.
     *
     * @since 1.1.0
     * @param array $schedules Schedules existentes.
     * @return array
     */
    public static function add_cron_schedules( $schedules ) {
        $schedules['dps_monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __( 'Mensalmente', 'dps-backup-addon' ),
        ];
        return $schedules;
    }

    /**
     * Agenda o backup automático.
     *
     * @since 1.1.0
     * @return bool
     */
    public static function schedule() {
        $settings = DPS_Backup_Settings::get_all();

        if ( empty( $settings['scheduled_enabled'] ) ) {
            self::unschedule();
            return false;
        }

        // Limpar agendamento anterior
        self::unschedule();

        $frequency = $settings['scheduled_frequency'] ?? 'weekly';
        $time = $settings['scheduled_time'] ?? '02:00';
        $day = $settings['scheduled_day'] ?? 'sunday';

        // Calcular próximo horário de execução
        $next_run = self::calculate_next_run( $frequency, $day, $time );

        $recurrence = self::get_recurrence( $frequency );

        return wp_schedule_event( $next_run, $recurrence, self::CRON_HOOK );
    }

    /**
     * Remove o agendamento de backup.
     *
     * @since 1.1.0
     * @return void
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Verifica se há backup agendado.
     *
     * @since 1.1.0
     * @return bool
     */
    public static function is_scheduled() {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Obtém o próximo horário de execução.
     *
     * @since 1.1.0
     * @return int|false Timestamp ou false se não agendado.
     */
    public static function get_next_run() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Calcula o próximo horário de execução.
     *
     * @since 1.1.0
     * @param string $frequency Frequência (daily, weekly, monthly).
     * @param string $day       Dia da semana.
     * @param string $time      Horário (HH:MM).
     * @return int Timestamp.
     */
    private static function calculate_next_run( $frequency, $day, $time ) {
        $timezone = wp_timezone();
        $now = new DateTime( 'now', $timezone );

        // Validar formato de tempo HH:MM
        $time_parts = explode( ':', $time );
        if ( count( $time_parts ) !== 2 ) {
            $time_parts = [ '02', '00' ]; // Valor padrão se formato inválido
        }
        $hour = min( 23, max( 0, absint( $time_parts[0] ) ) );
        $minute = min( 59, max( 0, absint( $time_parts[1] ) ) );

        $next = clone $now;
        $next->setTime( $hour, $minute, 0 );

        if ( 'daily' === $frequency ) {
            if ( $next <= $now ) {
                $next->modify( '+1 day' );
            }
        } elseif ( 'weekly' === $frequency ) {
            // Encontrar próximo dia da semana
            $target_day = ucfirst( $day );
            $next->modify( "next {$target_day}" );
            $next->setTime( $hour, $minute, 0 );
        } elseif ( 'monthly' === $frequency ) {
            // Primeiro dia do próximo mês
            $next->modify( 'first day of next month' );
            $next->setTime( $hour, $minute, 0 );
        }

        return $next->getTimestamp();
    }

    /**
     * Obtém a recorrência do WordPress para a frequência.
     *
     * @since 1.1.0
     * @param string $frequency Frequência.
     * @return string
     */
    private static function get_recurrence( $frequency ) {
        switch ( $frequency ) {
            case 'daily':
                return 'daily';
            case 'monthly':
                return 'dps_monthly';
            case 'weekly':
            default:
                return 'weekly';
        }
    }

    /**
     * Executa o backup agendado.
     *
     * @since 1.1.0
     * @return void
     */
    public static function run_scheduled_backup() {
        if ( ! class_exists( 'DPS_Backup_Exporter' ) ) {
            require_once dirname( __FILE__ ) . '/class-dps-backup-exporter.php';
        }

        $settings = DPS_Backup_Settings::get_all();
        $components = $settings['default_components'] ?? [];

        // Gerar backup
        $exporter = new DPS_Backup_Exporter();
        $payload = $exporter->build_selective_backup( $components );

        if ( is_wp_error( $payload ) ) {
            self::log_error( $payload->get_error_message() );
            return;
        }

        $filename = 'dps-backup-auto-' . gmdate( 'Ymd-His' ) . '.json';
        $content = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

        // Salvar no servidor
        $filepath = DPS_Backup_History::save_backup_file( $filename, $content );

        if ( is_wp_error( $filepath ) ) {
            self::log_error( $filepath->get_error_message() );
            return;
        }

        // Registrar no histórico
        $stats = $exporter->get_backup_stats( $payload );
        DPS_Backup_History::add_entry( [
            'filename'   => $filename,
            'size'       => strlen( $content ),
            'type'       => 'scheduled',
            'components' => $components,
            'stats'      => $stats,
            'stored'     => true,
            'file_path'  => $filepath,
        ] );

        // Enviar notificação por e-mail
        if ( ! empty( $settings['email_notification'] ) ) {
            self::send_notification( $filename, $stats );
        }

        // Registrar log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log(
                'backup_scheduled',
                sprintf(
                    'Backup automático realizado: %s (%d clientes, %d pets, %d agendamentos)',
                    $filename,
                    $stats['clients'] ?? 0,
                    $stats['pets'] ?? 0,
                    $stats['appointments'] ?? 0
                ),
                'info'
            );
        }
    }

    /**
     * Envia notificação por e-mail.
     *
     * @since 1.1.0
     * @param string $filename Nome do arquivo.
     * @param array  $stats    Estatísticas do backup.
     * @return void
     */
    private static function send_notification( $filename, $stats ) {
        $email = DPS_Backup_Settings::get( 'notification_email' );
        if ( empty( $email ) ) {
            $email = get_option( 'admin_email' );
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] Backup automático realizado', 'dps-backup-addon' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: 1: filename, 2: date/time */
            __( "Um backup automático foi realizado com sucesso.\n\nArquivo: %1\$s\nData: %2\$s\n\nEstatísticas:", 'dps-backup-addon' ),
            $filename,
            current_time( 'd/m/Y H:i' )
        );

        foreach ( $stats as $key => $value ) {
            $label = ucfirst( $key );
            $message .= "\n- {$label}: {$value}";
        }

        $message .= "\n\n" . __( 'O backup está armazenado no servidor e pode ser baixado na área de Backup & Restauração do painel administrativo.', 'dps-backup-addon' );

        wp_mail( $email, $subject, $message );
    }

    /**
     * Registra erro no log.
     *
     * @since 1.1.0
     * @param string $message Mensagem de erro.
     * @return void
     */
    private static function log_error( $message ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( 'backup_error', $message, 'error' );
        }
        error_log( 'DPS Backup Error: ' . $message );
    }
}
