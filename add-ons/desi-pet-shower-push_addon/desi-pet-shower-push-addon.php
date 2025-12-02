<?php
/**
 * Plugin Name:       Desi Pet Shower – Push Notifications Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Envia notificações diárias às 08:00 com o resumo dos agendamentos do dia. Pode ser adaptado para serviços de push externos.
 * Version:           1.1.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-push-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'DPS_PUSH_VERSION', '1.1.0' );
define( 'DPS_PUSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_PUSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Verifica se o plugin base Desi Pet Shower está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_push_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Push Notifications requer o plugin base Desi Pet Shower para funcionar.', 'dps-push-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_push_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Push Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_push_load_textdomain() {
    load_plugin_textdomain( 'dps-push-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_push_load_textdomain', 1 );

class DPS_Push_Notifications_Addon {

    /**
     * Inicializa hooks
     */
    public function __construct() {
        // Agenda a tarefa diária ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        // Remove o evento agendado ao desativar
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        // Hook para enviar notificação quando o cron rodar
        add_action( 'dps_send_agenda_notification', [ $this, 'send_agenda_notification' ] );
        // Hook para enviar relatório diário de atendimentos e financeiro às 19h
        add_action( 'dps_send_daily_report', [ $this, 'send_daily_report' ] );

        // Registra menu admin
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );

        // Enfileira assets admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Lida com salvamento do formulário de configurações
        add_action( 'init', [ $this, 'maybe_handle_save' ] );

        // Aplica filtros para usar emails salvos nas notificações
        add_filter( 'dps_push_notification_recipients', [ $this, 'filter_agenda_recipients' ] );
        add_filter( 'dps_daily_report_recipients', [ $this, 'filter_report_recipients' ] );

        // Hook para relatório semanal de pets inativos
        add_action( 'dps_send_weekly_inactive_report', [ $this, 'send_weekly_inactive_report' ] );
        // Hook para enviar mensagem via Telegram, se configurado
        add_action( 'dps_send_push_notification', [ $this, 'send_to_telegram' ], 10, 2 );

        // AJAX handlers
        add_action( 'wp_ajax_dps_push_send_test', [ $this, 'ajax_send_test' ] );
        add_action( 'wp_ajax_dps_push_test_telegram', [ $this, 'ajax_test_telegram' ] );
    }

    /**
     * Enfileira assets para página admin de notificações.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'desi-pet-shower_page_dps-notifications' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'dps-push-admin',
            DPS_PUSH_PLUGIN_URL . 'assets/css/push-admin.css',
            [],
            DPS_PUSH_VERSION
        );

        wp_enqueue_script(
            'dps-push-admin',
            DPS_PUSH_PLUGIN_URL . 'assets/js/push-admin.js',
            [ 'jquery' ],
            DPS_PUSH_VERSION,
            true
        );

        wp_localize_script( 'dps-push-admin', 'dps_push_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dps_push_ajax' ),
            'i18n'     => [
                'error'            => __( 'Ocorreu um erro. Tente novamente.', 'dps-push-addon' ),
                'testing'          => __( 'Testando...', 'dps-push-addon' ),
                'telegram_missing' => __( 'Preencha o token e o ID do chat antes de testar.', 'dps-push-addon' ),
                'telegram_error'   => __( 'Erro ao conectar com o Telegram.', 'dps-push-addon' ),
            ],
        ] );
    }

    /**
     * Registra submenu admin para notificações.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Notificações', 'dps-push-addon' ),
            __( 'Notificações', 'dps-push-addon' ),
            'manage_options',
            'dps-notifications',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Renderiza a página admin de notificações.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-push-addon' ) );
        }

        // Obtém configurações salvas
        $agenda_emails   = get_option( 'dps_push_emails_agenda', [] );
        $report_emails   = get_option( 'dps_push_emails_report', [] );
        $agenda_str      = is_array( $agenda_emails ) ? implode( ', ', $agenda_emails ) : '';
        $report_str      = is_array( $report_emails ) ? implode( ', ', $report_emails ) : '';
        $agenda_time     = $this->normalize_time_option( get_option( 'dps_push_agenda_time', '' ), get_option( 'dps_push_agenda_hour', 8 ) );
        $report_time     = $this->normalize_time_option( get_option( 'dps_push_report_time', '' ), get_option( 'dps_push_report_hour', 19 ) );
        $weekly_day      = $this->sanitize_weekday( get_option( 'dps_push_weekly_day', 'monday' ) );
        $weekly_time     = $this->normalize_time_option( get_option( 'dps_push_weekly_time', '' ), get_option( 'dps_push_agenda_hour', 8 ) );
        $telegram_token  = get_option( 'dps_push_telegram_token', '' );
        $telegram_chat   = get_option( 'dps_push_telegram_chat', '' );
        $inactive_days   = absint( get_option( 'dps_push_inactive_days', 30 ) );
        
        // Opções de habilitar/desabilitar
        $agenda_enabled  = get_option( 'dps_push_agenda_enabled', true );
        $report_enabled  = get_option( 'dps_push_report_enabled', true );
        $weekly_enabled  = get_option( 'dps_push_weekly_enabled', true );

        // Próximos agendamentos
        $next_agenda = wp_next_scheduled( 'dps_send_agenda_notification' );
        $next_report = wp_next_scheduled( 'dps_send_daily_report' );
        $next_weekly = wp_next_scheduled( 'dps_send_weekly_inactive_report' );

        $weekdays = [
            'monday'    => __( 'Segunda-feira', 'dps-push-addon' ),
            'tuesday'   => __( 'Terça-feira', 'dps-push-addon' ),
            'wednesday' => __( 'Quarta-feira', 'dps-push-addon' ),
            'thursday'  => __( 'Quinta-feira', 'dps-push-addon' ),
            'friday'    => __( 'Sexta-feira', 'dps-push-addon' ),
            'saturday'  => __( 'Sábado', 'dps-push-addon' ),
            'sunday'    => __( 'Domingo', 'dps-push-addon' ),
        ];

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Configure destinatários e horários para notificações automáticas e relatórios do sistema.', 'dps-push-addon' ); ?></p>

            <?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Configurações salvas com sucesso.', 'dps-push-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Status Card -->
            <div class="dps-push-status-card">
                <h3><?php esc_html_e( '📊 Status do Sistema', 'dps-push-addon' ); ?></h3>
                <div class="dps-push-status-grid">
                    <!-- Agenda status -->
                    <div class="dps-push-status-item <?php echo $agenda_enabled ? 'status-enabled' : 'status-disabled'; ?>" data-type="agenda">
                        <span class="dashicons <?php echo $agenda_enabled ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                        <div class="dps-push-status-content">
                            <div class="dps-push-status-title"><?php esc_html_e( 'Agenda Diária', 'dps-push-addon' ); ?></div>
                            <div class="dps-push-status-detail">
                                <?php if ( $agenda_enabled && $next_agenda ) : ?>
                                    <?php
                                    /* translators: %s: formatted date and time */
                                    printf( esc_html__( 'Próximo envio: %s', 'dps-push-addon' ), esc_html( date_i18n( 'd/m/Y \à\s H:i', $next_agenda ) ) );
                                    ?>
                                <?php elseif ( ! $agenda_enabled ) : ?>
                                    <?php esc_html_e( 'Desabilitado', 'dps-push-addon' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Não agendado', 'dps-push-addon' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Report status -->
                    <div class="dps-push-status-item <?php echo $report_enabled ? 'status-enabled' : 'status-disabled'; ?>" data-type="report">
                        <span class="dashicons <?php echo $report_enabled ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                        <div class="dps-push-status-content">
                            <div class="dps-push-status-title"><?php esc_html_e( 'Relatório Financeiro', 'dps-push-addon' ); ?></div>
                            <div class="dps-push-status-detail">
                                <?php if ( $report_enabled && $next_report ) : ?>
                                    <?php
                                    /* translators: %s: formatted date and time */
                                    printf( esc_html__( 'Próximo envio: %s', 'dps-push-addon' ), esc_html( date_i18n( 'd/m/Y \à\s H:i', $next_report ) ) );
                                    ?>
                                <?php elseif ( ! $report_enabled ) : ?>
                                    <?php esc_html_e( 'Desabilitado', 'dps-push-addon' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Não agendado', 'dps-push-addon' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly status -->
                    <div class="dps-push-status-item <?php echo $weekly_enabled ? 'status-enabled' : 'status-disabled'; ?>" data-type="weekly">
                        <span class="dashicons <?php echo $weekly_enabled ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                        <div class="dps-push-status-content">
                            <div class="dps-push-status-title"><?php esc_html_e( 'Pets Inativos', 'dps-push-addon' ); ?></div>
                            <div class="dps-push-status-detail">
                                <?php if ( $weekly_enabled && $next_weekly ) : ?>
                                    <?php
                                    /* translators: %s: formatted date and time */
                                    printf( esc_html__( 'Próximo envio: %s', 'dps-push-addon' ), esc_html( date_i18n( 'd/m/Y \à\s H:i', $next_weekly ) ) );
                                    ?>
                                <?php elseif ( ! $weekly_enabled ) : ?>
                                    <?php esc_html_e( 'Desabilitado', 'dps-push-addon' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Não agendado', 'dps-push-addon' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Telegram status -->
                    <div class="dps-push-status-item <?php echo ( $telegram_token && $telegram_chat ) ? 'status-enabled' : 'status-warning'; ?>">
                        <span class="dashicons <?php echo ( $telegram_token && $telegram_chat ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                        <div class="dps-push-status-content">
                            <div class="dps-push-status-title"><?php esc_html_e( 'Telegram', 'dps-push-addon' ); ?></div>
                            <div class="dps-push-status-detail">
                                <?php if ( $telegram_token && $telegram_chat ) : ?>
                                    <?php esc_html_e( 'Configurado', 'dps-push-addon' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Não configurado', 'dps-push-addon' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="">
                <input type="hidden" name="dps_push_action" value="save_notifications">
                <?php wp_nonce_field( 'dps_push_save', 'dps_push_nonce' ); ?>

                <!-- Seção: Agenda Diária -->
                <div class="dps-push-section" id="section-agenda">
                    <div class="dps-push-section-header">
                        <h2><?php esc_html_e( '📅 Resumo Diário de Agendamentos', 'dps-push-addon' ); ?></h2>
                        <div class="dps-push-section-toggle">
                            <div class="dps-push-switch">
                                <input type="checkbox" name="agenda_enabled" id="agenda_enabled" class="dps-push-enable-toggle" data-type="agenda" <?php checked( $agenda_enabled ); ?> />
                                <label for="agenda_enabled" class="dps-push-switch-label"><?php esc_html_e( 'Habilitado', 'dps-push-addon' ); ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="dps-push-section-body">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="agenda_emails"><?php esc_html_e( 'Destinatários (emails)', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="agenda_emails" name="agenda_emails" value="<?php echo esc_attr( $agenda_str ); ?>" class="large-text" />
                                        <p class="description"><?php esc_html_e( 'Lista de emails separados por vírgula. Ex: admin@exemplo.com, gerente@exemplo.com', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="agenda_time"><?php esc_html_e( 'Horário de envio', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="time" id="agenda_time" name="agenda_time" value="<?php echo esc_attr( $agenda_time ); ?>" pattern="[0-2][0-9]:[0-5][0-9]" step="60" />
                                        <p class="description"><?php esc_html_e( 'Horário para enviar o resumo de agendamentos do dia (formato 24h).', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="dps-push-actions">
                            <button type="button" class="dps-push-test-btn" data-type="agenda">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php esc_html_e( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seção: Relatório Financeiro -->
                <div class="dps-push-section" id="section-report">
                    <div class="dps-push-section-header">
                        <h2><?php esc_html_e( '💰 Relatório Diário de Atendimentos e Financeiro', 'dps-push-addon' ); ?></h2>
                        <div class="dps-push-section-toggle">
                            <div class="dps-push-switch">
                                <input type="checkbox" name="report_enabled" id="report_enabled" class="dps-push-enable-toggle" data-type="report" <?php checked( $report_enabled ); ?> />
                                <label for="report_enabled" class="dps-push-switch-label"><?php esc_html_e( 'Habilitado', 'dps-push-addon' ); ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="dps-push-section-body">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="report_emails"><?php esc_html_e( 'Destinatários (emails)', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="report_emails" name="report_emails" value="<?php echo esc_attr( $report_str ); ?>" class="large-text" />
                                        <p class="description"><?php esc_html_e( 'Lista de emails para receber relatório de atendimentos realizados e situação financeira.', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="report_time"><?php esc_html_e( 'Horário de envio', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="time" id="report_time" name="report_time" value="<?php echo esc_attr( $report_time ); ?>" pattern="[0-2][0-9]:[0-5][0-9]" step="60" />
                                        <p class="description"><?php esc_html_e( 'Horário para enviar o relatório diário (formato 24h).', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="dps-push-actions">
                            <button type="button" class="dps-push-test-btn" data-type="report">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php esc_html_e( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seção: Pets Inativos -->
                <div class="dps-push-section" id="section-weekly">
                    <div class="dps-push-section-header">
                        <h2><?php esc_html_e( '🐾 Relatório Semanal de Pets Inativos', 'dps-push-addon' ); ?></h2>
                        <div class="dps-push-section-toggle">
                            <div class="dps-push-switch">
                                <input type="checkbox" name="weekly_enabled" id="weekly_enabled" class="dps-push-enable-toggle" data-type="weekly" <?php checked( $weekly_enabled ); ?> />
                                <label for="weekly_enabled" class="dps-push-switch-label"><?php esc_html_e( 'Habilitado', 'dps-push-addon' ); ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="dps-push-section-body">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="inactive_days"><?php esc_html_e( 'Dias sem atendimento', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <div class="dps-push-inline-group">
                                            <input type="number" id="inactive_days" name="inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="7" max="365" />
                                            <span class="description"><?php esc_html_e( 'dias para considerar um pet como inativo', 'dps-push-addon' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="weekly_day"><?php esc_html_e( 'Dia da semana', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <select id="weekly_day" name="weekly_day">
                                            <?php foreach ( $weekdays as $day_key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $day_key ); ?>" <?php selected( $weekly_day, $day_key ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="weekly_time"><?php esc_html_e( 'Horário de envio', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="time" id="weekly_time" name="weekly_time" value="<?php echo esc_attr( $weekly_time ); ?>" pattern="[0-2][0-9]:[0-5][0-9]" step="60" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="dps-push-actions">
                            <button type="button" class="dps-push-test-btn" data-type="weekly">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php esc_html_e( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seção: Telegram -->
                <div class="dps-push-section" id="section-telegram">
                    <div class="dps-push-section-header">
                        <h2><?php esc_html_e( '📱 Integração com Telegram', 'dps-push-addon' ); ?></h2>
                    </div>
                    <div class="dps-push-section-body">
                        <p><?php esc_html_e( 'Configure um bot do Telegram para receber notificações em tempo real além dos emails.', 'dps-push-addon' ); ?></p>
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="telegram_token"><?php esc_html_e( 'Token do bot', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="telegram_token" name="telegram_token" value="<?php echo esc_attr( $telegram_token ); ?>" class="large-text" />
                                        <p class="description"><?php esc_html_e( 'Token de autenticação do bot do Telegram (obtido via @BotFather).', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="telegram_chat"><?php esc_html_e( 'ID do chat', 'dps-push-addon' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="telegram_chat" name="telegram_chat" value="<?php echo esc_attr( $telegram_chat ); ?>" class="regular-text" />
                                        <p class="description"><?php esc_html_e( 'ID do chat ou grupo que receberá as notificações.', 'dps-push-addon' ); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="dps-push-actions">
                            <button type="button" class="dps-push-test-btn" id="dps-test-telegram">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e( 'Testar Conexão', 'dps-push-addon' ); ?>
                            </button>
                            <span class="dps-telegram-status"></span>
                        </div>
                    </div>
                </div>

                <?php submit_button( __( 'Salvar configurações', 'dps-push-addon' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Agenda o evento diário às 08:00 na ativação
     */
    public function activate() {
        // Agenda a notificação diária de agenda no horário configurado
        $agenda_time  = $this->normalize_time_option( get_option( 'dps_push_agenda_time', '' ), get_option( 'dps_push_agenda_hour', 8 ) );
        if ( ! wp_next_scheduled( 'dps_send_agenda_notification' ) ) {
            $timestamp = $this->get_next_daily_timestamp( $agenda_time );
            wp_schedule_event( $timestamp, 'daily', 'dps_send_agenda_notification' );
        }
        // Agenda o envio do relatório diário no horário configurado
        $report_time_option = $this->normalize_time_option( get_option( 'dps_push_report_time', '' ), get_option( 'dps_push_report_hour', 19 ) );
        if ( ! wp_next_scheduled( 'dps_send_daily_report' ) ) {
            $report_time = $this->get_next_daily_timestamp( $report_time_option );
            wp_schedule_event( $report_time, 'daily', 'dps_send_daily_report' );
        }
        // Agenda o relatório semanal de pets inativos com dia e horário configuráveis
        $week_day  = $this->sanitize_weekday( get_option( 'dps_push_weekly_day', 'monday' ) );
        $week_time = $this->normalize_time_option( get_option( 'dps_push_weekly_time', '' ), get_option( 'dps_push_agenda_hour', 8 ) );
        if ( ! wp_next_scheduled( 'dps_send_weekly_inactive_report' ) ) {
            $weekly_time = $this->get_next_weekly_timestamp( $week_day, $week_time );
            wp_schedule_event( $weekly_time, 'weekly', 'dps_send_weekly_inactive_report' );
        }
    }

    /**
     * Cancela o evento agendado ao desativar
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'dps_send_agenda_notification' );
        wp_clear_scheduled_hook( 'dps_send_daily_report' );
        wp_clear_scheduled_hook( 'dps_send_weekly_inactive_report' );
    }

    /**
     * Calcula o próximo horário às 08:00 baseado no fuso horário do site.
     *
     * @return int Timestamp
     */
    private function get_next_daily_timestamp( $time_string = '08:00' ) {
        $time_string = $this->normalize_time_option( $time_string, 8 );
        $timezone    = $this->get_wp_timezone();
        $now         = new DateTimeImmutable( 'now', $timezone );

        $target = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $now->format( 'Y-m-d' ) . ' ' . $time_string, $timezone );
        if ( ! $target ) {
            $target = $now->setTime( 8, 0 );
        }

        if ( $target <= $now ) {
            $target = $target->modify( '+1 day' );
        }

        return $target->getTimestamp();
    }

    /**
     * Calcula o próximo horário para um dia específico da semana.
     *
     * @param string $day Dia da semana em inglês (Monday, Tuesday, etc.)
     * @param string $time_string Horário no formato H:i
     * @return int Timestamp da próxima ocorrência
     */
    private function get_next_weekly_timestamp( $day = 'monday', $time_string = '08:00' ) {
        $day         = $this->sanitize_weekday( $day );
        $time_string = $this->normalize_time_option( $time_string, 8 );
        $timezone    = $this->get_wp_timezone();
        $now         = new DateTimeImmutable( 'now', $timezone );

        $candidate = new DateTimeImmutable( 'this ' . $day, $timezone );
        $target    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $candidate->format( 'Y-m-d' ) . ' ' . $time_string, $timezone );

        if ( ! $target || $target <= $now ) {
            $candidate = $candidate->modify( '+1 week' );
            $target    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $candidate->format( 'Y-m-d' ) . ' ' . $time_string, $timezone );
        }

        if ( ! $target ) {
            $target = $now->modify( '+1 week' )->setTime( 8, 0 );
        }

        return $target->getTimestamp();
    }

    /**
     * Retorna a timezone configurada no WordPress.
     *
     * @return DateTimeZone
     */
    private function get_wp_timezone() {
        $timezone_string = get_option( 'timezone_string' );
        if ( $timezone_string ) {
            return new DateTimeZone( $timezone_string );
        }

        $offset   = (float) get_option( 'gmt_offset', 0 );
        $hours    = (int) $offset;
        $minutes  = (int) round( abs( $offset - $hours ) * 60 );
        $sign     = ( $offset >= 0 ) ? '+' : '-';
        $timezone = sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes );

        return new DateTimeZone( $timezone );
    }

    /**
     * Normaliza horário no formato HH:MM, com fallback para hora inteira.
     *
     * Esta função garante que sempre haverá um horário válido, mesmo se o
     * usuário não preencher o campo ou enviar valor inválido. Isso permite
     * que os inputs não tenham 'required' quando o relatório está desabilitado.
     *
     * @param string $time_string Horário recebido do formulário/option.
     * @param int    $fallback_hour Hora padrão caso formato seja inválido.
     * @return string Horário normalizado no formato HH:MM.
     */
    private function normalize_time_option( $time_string, $fallback_hour = 8 ) {
        $time_string = is_string( $time_string ) ? trim( $time_string ) : '';
        if ( preg_match( '/^(2[0-3]|[01]?\d):([0-5]\d)$/', $time_string, $matches ) ) {
            return sprintf( '%02d:%02d', intval( $matches[1] ), intval( $matches[2] ) );
        }

        $hour = is_numeric( $fallback_hour ) ? (int) $fallback_hour : 8;
        if ( $hour < 0 || $hour > 23 ) {
            $hour = 8;
        }

        return sprintf( '%02d:00', $hour );
    }

    /**
     * Sanitiza o dia da semana garantindo valores válidos em inglês.
     *
     * @param string $day Dia recebido das opções ou formulário.
     * @return string Dia validado (em minúsculas).
     */
    private function sanitize_weekday( $day ) {
        $valid_days = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
        $day        = is_string( $day ) ? strtolower( $day ) : 'monday';

        if ( in_array( $day, $valid_days, true ) ) {
            return $day;
        }

        return 'monday';
    }

    /**
     * Envia a notificação diária com o resumo da agenda
     */
    public function send_agenda_notification() {
        // Verifica se está habilitado
        if ( ! get_option( 'dps_push_agenda_enabled', true ) ) {
            return;
        }
        
        // Obtém a data atual no formato YYYY-mm-dd
        $today = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
        // Busca agendamentos do dia
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
        ] );
        // Constrói o resumo
        $lines = [];
        foreach ( $appointments as $appt ) {
            $time   = get_post_meta( $appt->ID, 'appointment_time', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet   = $pet_id ? get_post( $pet_id ) : null;
            $client= $client_id ? get_post( $client_id ) : null;
            $pet_name    = $pet ? $pet->post_title : '-';
            $client_name = $client ? $client->post_title : '-';
            $lines[] = $time . ' – ' . $pet_name . ' (' . $client_name . ')';
        }
        // Mensagem vazia se não houver atendimentos
        $content = '';
        if ( $lines ) {
            $content .= "Agendamentos para hoje (" . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . "):\n";
            foreach ( $lines as $line ) {
                $content .= '- ' . $line . "\n";
            }
        } else {
            $content = 'Não há agendamentos para hoje.';
        }
        // Permite modificar o conteúdo via filtro
        $content = apply_filters( 'dps_push_notification_content', $content, $appointments );
        // Determina destinatários
        $to      = apply_filters( 'dps_push_notification_recipients', [ get_option( 'admin_email' ) ] );
        $subject = 'Resumo de agendamentos do dia';
        // Constrói versão HTML
        $html    = '<html><body>';
        $html   .= '<p>Agendamentos para hoje (' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . '):</p>';
        if ( $lines ) {
            $html .= '<ul>';
            foreach ( $lines as $line ) {
                $html .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Não há agendamentos para hoje.</p>';
        }
        $html .= '</body></html>';
        // Define headers para HTML
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        $sent_count = 0;
        foreach ( $to as $recipient ) {
            if ( is_email( $recipient ) ) {
                $sent = wp_mail( $recipient, $subject, $html, $headers );
                if ( $sent ) {
                    $sent_count++;
                }
            }
        }
        
        // Log de envio
        $this->log( 'info', 'Agenda diária enviada', [
            'recipients'   => $sent_count,
            'appointments' => count( $appointments ),
        ] );
        
        // Integra com serviços de push externos, como Telegram
        do_action( 'dps_send_push_notification', $content, $appointments );
    }

    /**
     * Envia um relatório diário às 19:00 contendo resumo de atendimentos e dados financeiros.
     */
    public function send_daily_report() {
        // Verifica se está habilitado
        if ( ! get_option( 'dps_push_report_enabled', true ) ) {
            return;
        }
        
        // Data atual
        $today = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
        // ----- Resumo de atendimentos -----
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_time',
            'order'          => 'ASC',
        ] );
        $ap_lines = [];
        foreach ( $appointments as $appt ) {
            $time   = get_post_meta( $appt->ID, 'appointment_time', true );
            $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet   = $pet_id ? get_post( $pet_id ) : null;
            $client= $client_id ? get_post( $client_id ) : null;
            $pet_name    = $pet ? $pet->post_title : '-';
            $client_name = $client ? $client->post_title : '-';
            $ap_lines[]  = $time . ' – ' . $pet_name . ' (' . $client_name . ')';
        }
        // ----- Resumo financeiro -----
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Seleciona transações cuja data seja hoje (ignorando hora)
        $trans = [];
        $total_pago = 0.0;
        $total_aberto = 0.0;
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
            $trans = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE DATE(data) = %s", $today ) );
            foreach ( $trans as $t ) {
                $valor = (float) $t->valor;
                if ( $t->status === 'pago' ) {
                    $total_pago   += $valor;
                } else {
                    $total_aberto += $valor;
                }
            }
        }
        // Monta conteúdo de email em HTML e texto
        $content = 'Relatório diário de ' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . "\n\n";
        $content .= "Resumo de atendimentos:\n";
        if ( $ap_lines ) {
            foreach ( $ap_lines as $line ) {
                $content .= '- ' . $line . "\n";
            }
        } else {
            $content .= "Nenhum atendimento registrado hoje.\n";
        }
        $content .= "\nResumo financeiro:\n";
        if ( $trans ) {
            $content .= sprintf( "Total recebido (pago): R$ %s\n", number_format( $total_pago, 2, ',', '.' ) );
            $content .= sprintf( "Total em aberto: R$ %s\n", number_format( $total_aberto, 2, ',', '.' ) );
            $content .= "Transações:\n";
            foreach ( $trans as $t ) {
                $date_fmt = $t->data ? date_i18n( 'H:i', strtotime( $t->data ) ) : '';
                $valor_fmt = number_format( (float) $t->valor, 2, ',', '.' );
                $status_label = ( $t->status === 'pago' ) ? 'Pago' : 'Em aberto';
                $desc = $t->descricao ?: '';
                $content .= '- ' . $date_fmt . ': R$ ' . $valor_fmt . ' (' . $status_label . ') ' . $desc . "\n";
            }
        } else {
            $content .= "Nenhuma transação financeira registrada hoje.\n";
        }
        // Constrói HTML
        $html = '<html><body>';
        $html .= '<h3>Relatório diário de ' . date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) . '</h3>';
        $html .= '<h4>Resumo de atendimentos:</h4>';
        if ( $ap_lines ) {
            $html .= '<ul>';
            foreach ( $ap_lines as $line ) {
                $html .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Nenhum atendimento registrado hoje.</p>';
        }
        $html .= '<h4>Resumo financeiro:</h4>';
        if ( $trans ) {
            $html .= '<p>Total recebido (pago): <strong>R$ ' . esc_html( number_format( $total_pago, 2, ',', '.' ) ) . '</strong><br>';
            $html .= 'Total em aberto: <strong>R$ ' . esc_html( number_format( $total_aberto, 2, ',', '.' ) ) . '</strong></p>';
            $html .= '<ul>';
            foreach ( $trans as $t ) {
                $date_fmt = $t->data ? date_i18n( 'H:i', strtotime( $t->data ) ) : '';
                $valor_fmt = number_format( (float) $t->valor, 2, ',', '.' );
                $status_label = ( $t->status === 'pago' ) ? 'Pago' : 'Em aberto';
                $desc = $t->descricao ?: '';
                $html .= '<li>' . esc_html( $date_fmt ) . ': R$ ' . esc_html( $valor_fmt ) . ' (' . esc_html( $status_label ) . ') ' . esc_html( $desc ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>Nenhuma transação financeira registrada hoje.</p>';
        }
        $html .= '</body></html>';
        // Permite filtros no conteúdo e destinatários
        $content = apply_filters( 'dps_daily_report_content', $content, $appointments, $trans );
        $html    = apply_filters( 'dps_daily_report_html', $html, $appointments, $trans );
        $recipients = apply_filters( 'dps_daily_report_recipients', [ get_option( 'admin_email' ) ] );
        $subject = 'Relatório diário de atendimentos e financeiro';
        // HTML header
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        $sent_count = 0;
        foreach ( $recipients as $recipient ) {
            if ( is_email( $recipient ) ) {
                $sent = wp_mail( $recipient, $subject, $html, $headers );
                if ( $sent ) {
                    $sent_count++;
                }
            }
        }
        
        // Log de envio
        $this->log( 'info', 'Relatório financeiro enviado', [
            'recipients'   => $sent_count,
            'appointments' => count( $appointments ),
            'transactions' => count( $trans ),
            'total_pago'   => $total_pago,
            'total_aberto' => $total_aberto,
        ] );
    }

    /**
     * Adiciona uma nova aba de navegação para Notificações no painel do plugin base.
     *
     * @param bool $agenda_view Parâmetro herdado do hook (não utilizado aqui)
     */

    /**
     * Processa o envio do formulário de configurações das notificações.
     */
    public function maybe_handle_save() {
        if ( isset( $_POST['dps_push_action'] ) && 'save_notifications' === $_POST['dps_push_action'] ) {
            // Verifica o nonce
            if ( ! isset( $_POST['dps_push_nonce'] ) || ! wp_verify_nonce( $_POST['dps_push_nonce'], 'dps_push_save' ) ) {
                return;
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            // Sanitiza e salva emails
            $agenda_raw = isset( $_POST['agenda_emails'] ) ? sanitize_text_field( $_POST['agenda_emails'] ) : '';
            $report_raw = isset( $_POST['report_emails'] ) ? sanitize_text_field( $_POST['report_emails'] ) : '';
            $agenda_list = array_filter( array_map( 'trim', explode( ',', $agenda_raw ) ) );
            $report_list = array_filter( array_map( 'trim', explode( ',', $report_raw ) ) );
            update_option( 'dps_push_emails_agenda', $agenda_list );
            update_option( 'dps_push_emails_report', $report_list );
            
            // Salva opções de habilitar/desabilitar
            $agenda_enabled = isset( $_POST['agenda_enabled'] );
            $report_enabled = isset( $_POST['report_enabled'] );
            $weekly_enabled = isset( $_POST['weekly_enabled'] );
            update_option( 'dps_push_agenda_enabled', $agenda_enabled );
            update_option( 'dps_push_report_enabled', $report_enabled );
            update_option( 'dps_push_weekly_enabled', $weekly_enabled );
            
            // Salva threshold de inatividade
            $inactive_days = isset( $_POST['inactive_days'] ) ? absint( $_POST['inactive_days'] ) : 30;
            $inactive_days = max( 7, min( 365, $inactive_days ) ); // Entre 7 e 365 dias
            update_option( 'dps_push_inactive_days', $inactive_days );
            
            // Salva horários
            $agenda_time = isset( $_POST['agenda_time'] ) ? sanitize_text_field( wp_unslash( $_POST['agenda_time'] ) ) : '08:00';
            $report_time = isset( $_POST['report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['report_time'] ) ) : '19:00';
            $weekly_day  = isset( $_POST['weekly_day'] ) ? sanitize_text_field( wp_unslash( $_POST['weekly_day'] ) ) : 'monday';
            $weekly_time = isset( $_POST['weekly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['weekly_time'] ) ) : '08:00';
            $agenda_time = $this->normalize_time_option( $agenda_time, 8 );
            $report_time = $this->normalize_time_option( $report_time, 19 );
            $weekly_day  = $this->sanitize_weekday( $weekly_day );
            $weekly_time = $this->normalize_time_option( $weekly_time, 8 );
            update_option( 'dps_push_agenda_time', $agenda_time );
            update_option( 'dps_push_report_time', $report_time );
            update_option( 'dps_push_weekly_day', $weekly_day );
            update_option( 'dps_push_weekly_time', $weekly_time );
            // Salva integração Telegram
            $telegram_token = isset( $_POST['telegram_token'] ) ? sanitize_text_field( $_POST['telegram_token'] ) : '';
            $telegram_chat  = isset( $_POST['telegram_chat'] ) ? sanitize_text_field( $_POST['telegram_chat'] ) : '';
            update_option( 'dps_push_telegram_token', $telegram_token );
            update_option( 'dps_push_telegram_chat', $telegram_chat );
            // Reagendar eventos com novos horários
            wp_clear_scheduled_hook( 'dps_send_agenda_notification' );
            wp_clear_scheduled_hook( 'dps_send_daily_report' );
            wp_clear_scheduled_hook( 'dps_send_weekly_inactive_report' );
            // Agenda novamente apenas se habilitado
            if ( $agenda_enabled ) {
                $timestamp = $this->get_next_daily_timestamp( $agenda_time );
                wp_schedule_event( $timestamp, 'daily', 'dps_send_agenda_notification' );
            }
            if ( $report_enabled ) {
                $report_timestamp = $this->get_next_daily_timestamp( $report_time );
                wp_schedule_event( $report_timestamp, 'daily', 'dps_send_daily_report' );
            }
            if ( $weekly_enabled ) {
                $weekly_timestamp = $this->get_next_weekly_timestamp( $weekly_day, $weekly_time );
                wp_schedule_event( $weekly_timestamp, 'weekly', 'dps_send_weekly_inactive_report' );
            }
            
            // Log de salvamento
            $this->log( 'info', 'Configurações salvas', [
                'agenda_enabled' => $agenda_enabled,
                'report_enabled' => $report_enabled,
                'weekly_enabled' => $weekly_enabled,
                'inactive_days'  => $inactive_days,
            ] );
            
            // Redireciona com flag de sucesso
            wp_redirect( add_query_arg( [ 'page' => 'dps-notifications', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Substitui os destinatários padrão do resumo de agendamentos pelos emails configurados.
     *
     * @param array $recipients Lista original de emails
     * @return array Nova lista de emails
     */
    public function filter_agenda_recipients( $recipients ) {
        $saved = get_option( 'dps_push_emails_agenda', [] );
        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return $saved;
        }
        return $recipients;
    }

    /**
     * Substitui os destinatários padrão do relatório das 19h pelos emails configurados.
     *
     * @param array $recipients Lista original
     * @return array Nova lista
     */
    public function filter_report_recipients( $recipients ) {
        $saved = get_option( 'dps_push_emails_report', [] );
        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return $saved;
        }
        return $recipients;
    }

    /**
     * Envia relatório semanal de pets inativos (sem agendamentos nos últimos X dias)
     */
    public function send_weekly_inactive_report() {
        // Verifica se está habilitado
        if ( ! get_option( 'dps_push_weekly_enabled', true ) ) {
            return;
        }
        
        // Obtém threshold configurável (padrão: 30 dias)
        $inactive_days = absint( get_option( 'dps_push_inactive_days', 30 ) );
        if ( $inactive_days < 7 ) {
            $inactive_days = 30;
        }
        
        // Data limite: X dias atrás
        $cutoff_date = date_i18n( 'Y-m-d', strtotime( "-{$inactive_days} days", current_time( 'timestamp' ) ) );
        // Busca todos os pets
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );
        $inactive = [];
        foreach ( $pets as $pet ) {
            $pet_id = $pet->ID;
            // Busca últimos agendamentos deste pet com status publish
            $appointments = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_pet_id',
                        'value'   => $pet_id,
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'meta_value_num',
                'meta_key'       => 'appointment_date',
                'order'          => 'DESC',
            ] );
            $last_date = null;
            if ( $appointments ) {
                $last_date = get_post_meta( $appointments[0]->ID, 'appointment_date', true );
            }
            // Se não há data ou é anterior ao cutoff, adiciona à lista
            if ( ! $last_date || $last_date < $cutoff_date ) {
                // Obter nome do dono e data formatada
                $owner_id = get_post_meta( $pet_id, 'owner_id', true );
                $owner    = $owner_id ? get_post( $owner_id ) : null;
                $last_fmt = $last_date ? date_i18n( 'd/m/Y', strtotime( $last_date ) ) : 'Nunca';
                $inactive[] = [
                    'pet_name'   => $pet->post_title,
                    'owner_name' => $owner ? $owner->post_title : '-',
                    'last_date'  => $last_fmt,
                ];
            }
        }
        // Monta mensagem de relatório
        $today_label = date_i18n( 'd/m/Y', current_time( 'timestamp' ) );
        $content = "Relatório semanal de pets inativos ({$today_label})\n\n";
        $html    = '<html><body>';
        $html   .= '<h3>Relatório semanal de pets inativos (' . esc_html( $today_label ) . ')</h3>';
        if ( $inactive ) {
            /* translators: %d: number of days without appointments */
            $content .= sprintf( __( "Pets sem atendimento nos últimos %d dias:\n", 'dps-push-addon' ), $inactive_days );
            /* translators: %d: number of days without appointments */
            $html    .= '<p>' . sprintf( esc_html__( 'Pets sem atendimento nos últimos %d dias:', 'dps-push-addon' ), $inactive_days ) . '</p><ul>';
            foreach ( $inactive as $item ) {
                $line = $item['pet_name'] . ' – ' . $item['owner_name'] . ' (último: ' . $item['last_date'] . ')';
                $content .= '- ' . $line . "\n";
                $html    .= '<li>' . esc_html( $line ) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $content .= "Todos os pets tiveram atendimento recente.\n";
            $html    .= '<p>Todos os pets tiveram atendimento recente.</p>';
        }
        $html .= '</body></html>';
        // Determina destinatários (usar emails de relatório por padrão)
        $recipients = apply_filters( 'dps_weekly_inactive_report_recipients', get_option( 'dps_push_emails_report', [ get_option( 'admin_email' ) ] ) );
        $subject = 'Relatório semanal de pets inativos';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        $sent_count = 0;
        foreach ( $recipients as $recipient ) {
            if ( is_email( $recipient ) ) {
                $sent = wp_mail( $recipient, $subject, $html, $headers );
                if ( $sent ) {
                    $sent_count++;
                }
            }
        }
        
        // Log de envio
        $this->log( 'info', 'Relatório semanal de pets inativos enviado', [
            'recipients'    => $sent_count,
            'inactive_pets' => count( $inactive ),
            'threshold'     => $inactive_days,
        ] );
        
        // Aciona serviço de push se configurado
        do_action( 'dps_send_push_notification', $content, $inactive );
    }

    /**
     * Envia notificação via Telegram se as credenciais estiverem configuradas.
     *
     * @param string $message Mensagem a ser enviada (texto)
     * @param mixed  $context Contexto adicional (não utilizado)
     */
    public function send_to_telegram( $message, $context ) {
        $token = get_option( 'dps_push_telegram_token', '' );
        $chat_id = get_option( 'dps_push_telegram_chat', '' );
        if ( empty( $token ) || empty( $chat_id ) ) {
            return;
        }
        // Monta endpoint
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $args = [
            'body' => [
                'chat_id' => $chat_id,
                'text'    => $message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 15,
        ];
        // Envia requisição
        $response = wp_remote_post( $url, $args );
        
        // Log do resultado
        if ( is_wp_error( $response ) ) {
            $this->log( 'error', 'Erro ao enviar para Telegram: ' . $response->get_error_message(), [
                'chat_id' => $chat_id,
            ] );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['ok'] ) && $body['ok'] ) {
                $this->log( 'info', 'Mensagem enviada ao Telegram com sucesso', [
                    'chat_id' => $chat_id,
                ] );
            } else {
                $this->log( 'error', 'Telegram retornou erro: ' . ( $body['description'] ?? 'Desconhecido' ), [
                    'chat_id' => $chat_id,
                ] );
            }
        }
    }

    /**
     * AJAX handler para enviar teste de notificação.
     */
    public function ajax_send_test() {
        // Verifica nonce
        if ( ! check_ajax_referer( 'dps_push_ajax', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Erro de segurança. Recarregue a página.', 'dps-push-addon' ) ] );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para esta ação.', 'dps-push-addon' ) ] );
        }

        $type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

        switch ( $type ) {
            case 'agenda':
                $this->send_agenda_notification();
                $message = __( 'Teste de agenda diária enviado com sucesso!', 'dps-push-addon' );
                break;

            case 'report':
                $this->send_daily_report();
                $message = __( 'Teste de relatório financeiro enviado com sucesso!', 'dps-push-addon' );
                break;

            case 'weekly':
                // Temporariamente habilita para enviar teste
                add_filter( 'dps_push_weekly_test_mode', '__return_true' );
                $this->send_weekly_inactive_report();
                remove_filter( 'dps_push_weekly_test_mode', '__return_true' );
                $message = __( 'Teste de pets inativos enviado com sucesso!', 'dps-push-addon' );
                break;

            default:
                wp_send_json_error( [ 'message' => __( 'Tipo de notificação inválido.', 'dps-push-addon' ) ] );
        }

        $this->log( 'info', 'Teste de notificação enviado', [ 'type' => $type ] );

        wp_send_json_success( [ 'message' => $message ] );
    }

    /**
     * AJAX handler para testar conexão com Telegram.
     */
    public function ajax_test_telegram() {
        // Verifica nonce
        if ( ! check_ajax_referer( 'dps_push_ajax', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Erro de segurança. Recarregue a página.', 'dps-push-addon' ) ] );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para esta ação.', 'dps-push-addon' ) ] );
        }

        $token   = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $chat_id = isset( $_POST['chat_id'] ) ? sanitize_text_field( $_POST['chat_id'] ) : '';

        if ( empty( $token ) || empty( $chat_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Token e ID do chat são obrigatórios.', 'dps-push-addon' ) ] );
        }

        // Primeiro, testa se o bot é válido
        $bot_url  = 'https://api.telegram.org/bot' . $token . '/getMe';
        $response = wp_remote_get( $bot_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => __( 'Erro de conexão: ', 'dps-push-addon' ) . $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['ok'] ) || ! $body['ok'] ) {
            wp_send_json_error( [ 'message' => __( 'Token inválido. Verifique o token do bot.', 'dps-push-addon' ) ] );
        }

        $bot_name = $body['result']['first_name'] ?? 'Bot';

        // Tenta enviar mensagem de teste
        $test_url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $test_msg = wp_remote_post( $test_url, [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => '✅ Conexão com Desi Pet Shower testada com sucesso!',
                'parse_mode' => 'HTML',
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $test_msg ) ) {
            wp_send_json_error( [ 'message' => __( 'Bot válido, mas erro ao enviar: ', 'dps-push-addon' ) . $test_msg->get_error_message() ] );
        }

        $msg_body = json_decode( wp_remote_retrieve_body( $test_msg ), true );

        if ( ! isset( $msg_body['ok'] ) || ! $msg_body['ok'] ) {
            $error_desc = $msg_body['description'] ?? __( 'Erro desconhecido', 'dps-push-addon' );
            wp_send_json_error( [ 'message' => __( 'Erro ao enviar mensagem: ', 'dps-push-addon' ) . $error_desc ] );
        }

        $this->log( 'info', 'Conexão Telegram testada com sucesso', [
            'bot_name' => $bot_name,
            'chat_id'  => $chat_id,
        ] );

        /* translators: %s: bot name */
        wp_send_json_success( [ 'message' => sprintf( __( 'Conectado! Bot: %s', 'dps-push-addon' ), $bot_name ) ] );
    }

    /**
     * Registra log usando DPS_Logger se disponível.
     *
     * @param string $level   Nível: 'info', 'error', 'warning'
     * @param string $message Mensagem de log
     * @param array  $context Contexto adicional
     */
    private function log( $level, $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, 'Push: ' . $message, $context );
        } else {
            // Fallback para error_log
            error_log( sprintf( '[DPS Push %s] %s %s', strtoupper( $level ), $message, wp_json_encode( $context ) ) );
        }
    }
}

/**
 * Inicializa o Push Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_push_init_addon() {
    if ( class_exists( 'DPS_Push_Notifications_Addon' ) ) {
        new DPS_Push_Notifications_Addon();
    }
}
add_action( 'init', 'dps_push_init_addon', 5 );