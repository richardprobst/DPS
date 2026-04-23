<?php
/**
 * PÃ¡gina administrativa do Push Add-on.
 *
 * Renderiza a interface de configuraÃ§Ãµes de notificaÃ§Ãµes push,
 * relatÃ³rios por email e integraÃ§Ã£o Telegram.
 *
 * @package DPS_Push_Addon
 * @since   2.0.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de administraÃ§Ã£o do Push Add-on.
 *
 * @since 2.0.0
 */
class DPS_Push_Admin {

    /**
     * Registra submenu admin para Push Notifications.
     *
     * @since 1.0.0
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'NotificaÃ§Ãµes', 'dps-push-addon' ),
            __( 'NotificaÃ§Ãµes', 'dps-push-addon' ),
            'manage_options',
            'dps-push-notifications',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enfileira assets do admin.
     *
     * @since 1.0.0
     * @param string $hook Hook da pÃ¡gina atual.
     */
    public function enqueue_admin_assets( $hook ) {
        $hook = (string) $hook;

        $is_push_page = ( strpos( $hook, 'dps-push-notifications' ) !== false );
        $is_dps_page  = ( strpos( $hook, 'desi-pet-shower' ) !== false || strpos( $hook, 'dps-' ) !== false );

        if ( ! $is_push_page && ! $is_dps_page ) {
            return;
        }

        $addon_url = plugin_dir_url( dirname( __DIR__ ) . '/desi-pet-shower-push-addon.php' );
        $version   = '2.0.0';

        // Design tokens DPS Signature.
        $css_deps = [];
        if ( defined( 'DPS_BASE_URL' ) ) {
            wp_register_style(
                'dps-design-tokens',
                DPS_BASE_URL . 'assets/css/dps-design-tokens.css',
                [],
                defined( 'DPS_BASE_VERSION' ) ? DPS_BASE_VERSION : '2.0.0'
            );
            $css_deps[] = 'dps-design-tokens';
        }

        wp_enqueue_style(
            'dps-push-addon',
            $addon_url . 'assets/css/push-addon.css',
            $css_deps,
            $version
        );

        wp_enqueue_script(
            'dps-push-addon',
            $addon_url . 'assets/js/push-addon.js',
            [ 'jquery' ],
            $version,
            true
        );

        $vapid_keys = get_option( DPS_Push_Addon::VAPID_KEY, [] );

        wp_localize_script( 'dps-push-addon', 'DPS_Push', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce_subscribe' => wp_create_nonce( 'dps_push_subscribe' ),
            'nonce_test'      => wp_create_nonce( 'dps_push_test' ),
            'vapid_public'    => $vapid_keys['public'] ?? '',
            'sw_url'          => $addon_url . 'assets/js/push-sw.js',
            'messages'        => [
                'subscribing'       => __( 'Ativando notificaÃ§Ãµes...', 'dps-push-addon' ),
                'subscribed'        => __( 'NotificaÃ§Ãµes ativadas!', 'dps-push-addon' ),
                'unsubscribed'      => __( 'NotificaÃ§Ãµes desativadas.', 'dps-push-addon' ),
                'error'             => __( 'Erro ao ativar notificaÃ§Ãµes.', 'dps-push-addon' ),
                'not_supported'     => __( 'Seu navegador nÃ£o suporta notificaÃ§Ãµes push.', 'dps-push-addon' ),
                'permission_denied' => __( 'PermissÃ£o negada. Habilite nas configuraÃ§Ãµes do navegador.', 'dps-push-addon' ),
                'test_sent'         => __( 'NotificaÃ§Ã£o de teste enviada!', 'dps-push-addon' ),
                'saving'            => __( 'Salvando...', 'dps-push-addon' ),
                'save_settings'     => __( 'Salvar ConfiguraÃ§Ãµes', 'dps-push-addon' ),
                'sending'           => __( 'Enviando...', 'dps-push-addon' ),
                'testing'           => __( 'Testando...', 'dps-push-addon' ),
                'invalid_email'     => __( 'Email invÃ¡lido: ', 'dps-push-addon' ),
                'invalid_token'     => __( 'Formato de token invÃ¡lido. Exemplo: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ', 'dps-push-addon' ),
                'vapid_not_configured' => __( 'Chaves VAPID nÃ£o configuradas. Desative e reative o plugin para gerÃ¡-las.', 'dps-push-addon' ),
            ],
        ] );
    }

    /**
     * Enfileira assets no frontend (para pÃ¡gina de agenda).
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        global $post;
        if ( ! $post || ! has_shortcode( (string) $post->post_content, 'dps_agenda_page' ) ) {
            return;
        }

        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->enqueue_admin_assets( 'dps-agenda' );
    }

    /**
     * Renderiza pÃ¡gina de configuraÃ§Ãµes.
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        $settings       = get_option( DPS_Push_Addon::OPTION_KEY, [] );
        $user_id        = get_current_user_id();
        $subscriptions  = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        $sub_count      = is_array( $subscriptions ) ? count( $subscriptions ) : 0;

        // ConfiguraÃ§Ãµes de relatÃ³rios por email.
        $emails_agenda  = get_option( 'dps_push_emails_agenda', get_option( 'admin_email' ) );
        $emails_report  = get_option( 'dps_push_emails_report', get_option( 'admin_email' ) );
        $agenda_time    = get_option( 'dps_push_agenda_time', '08:00' );
        $report_time    = get_option( 'dps_push_report_time', '19:00' );
        $weekly_day     = get_option( 'dps_push_weekly_day', 'monday' );
        $weekly_time    = get_option( 'dps_push_weekly_time', '08:00' );
        $inactive_days  = get_option( 'dps_push_inactive_days', 30 );
        $telegram_token = get_option( 'dps_push_telegram_token', '' );
        $telegram_chat  = get_option( 'dps_push_telegram_chat', '' );
        $agenda_enabled = get_option( 'dps_push_agenda_enabled', true );
        $report_enabled = get_option( 'dps_push_report_enabled', true );
        $weekly_enabled = get_option( 'dps_push_weekly_enabled', true );

        // PrÃ³ximos envios agendados.
        $next_agenda = wp_next_scheduled( 'dps_send_agenda_notification' );
        $next_report = wp_next_scheduled( 'dps_send_daily_report' );
        $next_weekly = wp_next_scheduled( 'dps_send_weekly_inactive_report' );

        // Status do Telegram.
        $telegram_configured = ! empty( $telegram_token ) && ! empty( $telegram_chat );

        // Formatar emails para exibiÃ§Ã£o.
        $emails_agenda_display = is_array( $emails_agenda ) ? implode( ', ', $emails_agenda ) : $emails_agenda;
        $emails_report_display = is_array( $emails_report ) ? implode( ', ', $emails_report ) : $emails_report;

        // Verificar status de salvamento.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura para exibir mensagem.
        $is_updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura para exibir mensagem.
        $has_error = isset( $_GET['error'] ) && '1' === $_GET['error'];

        $error_message = get_transient( 'dps_push_settings_error' );
        if ( $error_message ) {
            delete_transient( 'dps_push_settings_error' );
        }

        ?>
        <div class="wrap dps-push-settings">
            <h1 class="dps-section-title">
                <span class="dps-section-title__icon">ðŸ””</span>
                <?php echo esc_html__( 'NotificaÃ§Ãµes e RelatÃ³rios', 'dps-push-addon' ); ?>
            </h1>
            <p class="dps-section-header__subtitle"><?php echo esc_html__( 'Configure notificaÃ§Ãµes push do navegador, relatÃ³rios automÃ¡ticos por email e integraÃ§Ã£o com Telegram.', 'dps-push-addon' ); ?></p>

            <?php if ( $is_updated ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__( 'ConfiguraÃ§Ãµes salvas com sucesso.', 'dps-push-addon' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $has_error && $error_message ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <?php settings_errors( 'dps_push' ); ?>

            <form method="post" id="dps-push-settings-form">
                <input type="hidden" name="dps_push_action" value="save_settings" />
                <?php wp_nonce_field( 'dps_push_settings', 'dps_push_nonce' ); ?>

                <div class="dps-push-stacked">

                    <!-- Card: NotificaÃ§Ãµes Push do Navegador -->
                    <div class="dps-surface dps-surface--info">
                        <div class="dps-surface__title">
                            <span>ðŸ–¥ï¸</span>
                            <?php echo esc_html__( 'NotificaÃ§Ãµes Push do Navegador', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba alertas em tempo real diretamente no seu navegador, mesmo quando estiver em outra aba ou com o navegador minimizado.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-browser-section">
                            <div class="dps-push-status-row">
                                <div id="dps-push-status-indicator" class="dps-push-indicator dps-push-checking">
                                    <span class="dps-push-dot"></span>
                                    <span class="dps-push-status-text"><?php echo esc_html__( 'Verificando...', 'dps-push-addon' ); ?></span>
                                </div>
                                <span class="dps-push-devices">
                                    <?php
                                    printf(
                                        esc_html__( '(%d dispositivo(s) inscrito(s))', 'dps-push-addon' ),
                                        $sub_count
                                    );
                                    ?>
                                </span>
                            </div>

                            <div class="dps-push-actions">
                                <button type="button" id="dps-push-subscribe" class="button button-primary">
                                    <?php echo esc_html__( 'Ativar NotificaÃ§Ãµes neste Dispositivo', 'dps-push-addon' ); ?>
                                </button>
                                <button type="button" id="dps-push-test" class="button" style="display: none;">
                                    <?php echo esc_html__( 'Enviar NotificaÃ§Ã£o de Teste', 'dps-push-addon' ); ?>
                                </button>
                            </div>

                            <fieldset class="dps-push-events-fieldset">
                                <legend><?php echo esc_html__( 'Eventos que disparam notificaÃ§Ãµes push:', 'dps-push-addon' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="notify_new_appointment" value="1"
                                           <?php checked( ! empty( $settings['notify_new_appointment'] ) ); ?>>
                                    <?php echo esc_html__( 'Novo agendamento criado', 'dps-push-addon' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="notify_status_change" value="1"
                                           <?php checked( ! empty( $settings['notify_status_change'] ) ); ?>>
                                    <?php echo esc_html__( 'AlteraÃ§Ã£o de status do agendamento', 'dps-push-addon' ); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="notify_rescheduled" value="1"
                                           <?php checked( ! empty( $settings['notify_rescheduled'] ) ); ?>>
                                    <?php echo esc_html__( 'Agendamento reagendado', 'dps-push-addon' ); ?>
                                </label>
                            </fieldset>

                            <p class="description dps-push-note">
                                <?php echo esc_html__( 'Nota: Requer HTTPS e navegador compatÃ­vel (Chrome, Firefox, Edge, Safari 16+). Ative em cada dispositivo que deseja receber notificaÃ§Ãµes.', 'dps-push-addon' ); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card: RelatÃ³rio da ManhÃ£ (Agenda do Dia) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>â˜€ï¸</span>
                            <?php echo esc_html__( 'RelatÃ³rio da ManhÃ£ â€“ Agenda do Dia', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba no inÃ­cio do dia um resumo com todos os agendamentos programados, incluindo horÃ¡rios, pets e serviÃ§os.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_agenda_enabled" value="1" <?php checked( $agenda_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatÃ³rio da manhÃ£', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_agenda_time"><?php echo esc_html__( 'HorÃ¡rio de envio:', 'dps-push-addon' ); ?></label>
                                    <input type="time" id="dps_push_agenda_time" name="dps_push_agenda_time" value="<?php echo esc_attr( $agenda_time ); ?>">
                                    <?php if ( $agenda_enabled && $next_agenda ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">âœ“ <?php echo esc_html__( 'PrÃ³ximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_agenda ) ); ?></span>
                                    <?php elseif ( ! $agenda_enabled ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-inactive">â¸ <?php echo esc_html__( 'Desativado', 'dps-push-addon' ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_emails_agenda"><?php echo esc_html__( 'DestinatÃ¡rios:', 'dps-push-addon' ); ?></label>
                                    <input type="text" id="dps_push_emails_agenda" name="dps_push_emails_agenda" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" value="<?php echo esc_attr( $emails_agenda_display ); ?>">
                                    <p class="description"><?php echo esc_html__( 'Separe mÃºltiplos emails por vÃ­rgula. Deixe em branco para usar o email do administrador.', 'dps-push-addon' ); ?></p>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="agenda">
                                    ðŸ“¤ <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="agenda"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: RelatÃ³rio do Final do Dia (Financeiro) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>ðŸŒ™</span>
                            <?php echo esc_html__( 'RelatÃ³rio do Final do Dia â€“ Resumo Financeiro', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba no final do expediente um balanÃ§o com receitas, despesas e atendimentos realizados no dia.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_report_enabled" value="1" <?php checked( $report_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatÃ³rio do final do dia', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_report_time"><?php echo esc_html__( 'HorÃ¡rio de envio:', 'dps-push-addon' ); ?></label>
                                    <input type="time" id="dps_push_report_time" name="dps_push_report_time" value="<?php echo esc_attr( $report_time ); ?>">
                                    <?php if ( $report_enabled && $next_report ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">âœ“ <?php echo esc_html__( 'PrÃ³ximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_report ) ); ?></span>
                                    <?php elseif ( ! $report_enabled ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-inactive">â¸ <?php echo esc_html__( 'Desativado', 'dps-push-addon' ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_emails_report"><?php echo esc_html__( 'DestinatÃ¡rios:', 'dps-push-addon' ); ?></label>
                                    <input type="text" id="dps_push_emails_report" name="dps_push_emails_report" class="regular-text" placeholder="email1@exemplo.com, email2@exemplo.com" value="<?php echo esc_attr( $emails_report_display ); ?>">
                                    <p class="description"><?php echo esc_html__( 'Separe mÃºltiplos emails por vÃ­rgula. Deixe em branco para usar o email do administrador.', 'dps-push-addon' ); ?></p>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="report">
                                    ðŸ“¤ <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="report"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: RelatÃ³rio Semanal (Pets Inativos) -->
                    <div class="dps-surface dps-surface--neutral">
                        <div class="dps-surface__title">
                            <span>ðŸ¾</span>
                            <?php echo esc_html__( 'RelatÃ³rio Semanal â€“ Pets Inativos', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba semanalmente uma lista de pets que nÃ£o foram atendidos hÃ¡ muito tempo, ideal para aÃ§Ãµes de reengajamento.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-report-config">
                            <label class="dps-push-toggle-label">
                                <input type="checkbox" name="dps_push_weekly_enabled" value="1" <?php checked( $weekly_enabled ); ?>>
                                <strong><?php echo esc_html__( 'Ativar relatÃ³rio semanal', 'dps-push-addon' ); ?></strong>
                            </label>

                            <div class="dps-push-report-fields">
                                <div class="dps-push-field-row">
                                    <label for="dps_push_weekly_day"><?php echo esc_html__( 'Dia da semana:', 'dps-push-addon' ); ?></label>
                                    <select id="dps_push_weekly_day" name="dps_push_weekly_day">
                                        <option value="monday" <?php selected( $weekly_day, 'monday' ); ?>><?php echo esc_html__( 'Segunda-feira', 'dps-push-addon' ); ?></option>
                                        <option value="tuesday" <?php selected( $weekly_day, 'tuesday' ); ?>><?php echo esc_html__( 'TerÃ§a-feira', 'dps-push-addon' ); ?></option>
                                        <option value="wednesday" <?php selected( $weekly_day, 'wednesday' ); ?>><?php echo esc_html__( 'Quarta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="thursday" <?php selected( $weekly_day, 'thursday' ); ?>><?php echo esc_html__( 'Quinta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="friday" <?php selected( $weekly_day, 'friday' ); ?>><?php echo esc_html__( 'Sexta-feira', 'dps-push-addon' ); ?></option>
                                        <option value="saturday" <?php selected( $weekly_day, 'saturday' ); ?>><?php echo esc_html__( 'SÃ¡bado', 'dps-push-addon' ); ?></option>
                                        <option value="sunday" <?php selected( $weekly_day, 'sunday' ); ?>><?php echo esc_html__( 'Domingo', 'dps-push-addon' ); ?></option>
                                    </select>
                                    <input type="time" id="dps_push_weekly_time" name="dps_push_weekly_time" value="<?php echo esc_attr( $weekly_time ); ?>">
                                    <?php if ( $weekly_enabled && $next_weekly ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-active">âœ“ <?php echo esc_html__( 'PrÃ³ximo:', 'dps-push-addon' ); ?> <?php echo esc_html( date_i18n( 'd/m H:i', $next_weekly ) ); ?></span>
                                    <?php elseif ( ! $weekly_enabled ) : ?>
                                        <span class="dps-schedule-badge dps-schedule-inactive">â¸ <?php echo esc_html__( 'Desativado', 'dps-push-addon' ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-push-field-row">
                                    <label for="dps_push_inactive_days"><?php echo esc_html__( 'Considerar inativo apÃ³s:', 'dps-push-addon' ); ?></label>
                                    <input type="number" id="dps_push_inactive_days" name="dps_push_inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="7" max="365" class="dps-push-days-input">
                                    <span><?php echo esc_html__( 'dias sem atendimento', 'dps-push-addon' ); ?></span>
                                </div>

                                <button type="button" class="button dps-test-report-btn" data-type="weekly">
                                    ðŸ“¤ <?php echo esc_html__( 'Enviar Teste Agora', 'dps-push-addon' ); ?>
                                </button>
                                <span class="dps-test-result" data-type="weekly"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card: IntegraÃ§Ã£o Telegram -->
                    <div class="dps-surface dps-surface--info">
                        <div class="dps-surface__title">
                            <span>ðŸ“±</span>
                            <?php echo esc_html__( 'IntegraÃ§Ã£o com Telegram', 'dps-push-addon' ); ?>
                        </div>
                        <p class="dps-surface__description"><?php echo esc_html__( 'Receba os relatÃ³rios tambÃ©m via Telegram. Configure um bot e informe o Chat ID para envio automÃ¡tico.', 'dps-push-addon' ); ?></p>

                        <div class="dps-push-telegram-config">
                            <div class="dps-push-field-row">
                                <label for="dps_push_telegram_token"><?php echo esc_html__( 'Token do Bot:', 'dps-push-addon' ); ?></label>
                                <div class="dps-telegram-token-wrapper">
                                    <input type="password" id="dps_push_telegram_token" name="dps_push_telegram_token" value="<?php echo esc_attr( $telegram_token ); ?>" class="regular-text" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ" autocomplete="off">
                                    <button type="button" id="dps-toggle-token" class="button" aria-label="<?php echo esc_attr__( 'Mostrar/ocultar token', 'dps-push-addon' ); ?>">ðŸ‘ï¸</button>
                                </div>
                                <p class="description"><?php echo esc_html__( 'Crie um bot via @BotFather no Telegram para obter o token.', 'dps-push-addon' ); ?></p>
                            </div>

                            <div class="dps-push-field-row">
                                <label for="dps_push_telegram_chat"><?php echo esc_html__( 'Chat ID:', 'dps-push-addon' ); ?></label>
                                <input type="text" id="dps_push_telegram_chat" name="dps_push_telegram_chat" value="<?php echo esc_attr( $telegram_chat ); ?>" class="regular-text" placeholder="-1001234567890">
                                <p class="description"><?php echo esc_html__( 'ID do chat ou grupo. Use @userinfobot para descobrir o seu.', 'dps-push-addon' ); ?></p>
                            </div>

                            <div class="dps-push-telegram-status">
                                <?php if ( $telegram_configured ) : ?>
                                    <span class="dps-status-badge dps-status-badge--success">âœ“ <?php echo esc_html__( 'Configurado', 'dps-push-addon' ); ?></span>
                                <?php else : ?>
                                    <span class="dps-status-badge dps-status-badge--pending"><?php echo esc_html__( 'NÃ£o configurado', 'dps-push-addon' ); ?></span>
                                <?php endif; ?>
                                <button type="button" id="dps-test-telegram" class="button">
                                    ðŸ”— <?php echo esc_html__( 'Testar ConexÃ£o', 'dps-push-addon' ); ?>
                                </button>
                                <span id="dps-telegram-result" class="dps-test-result"></span>
                            </div>
                        </div>
                    </div>

                </div><!-- .dps-push-stacked -->

                <p class="submit dps-push-submit-area">
                    <button type="submit" id="dps-push-save-btn" class="button button-primary button-hero">
                        ðŸ’¾ <?php echo esc_html__( 'Salvar Todas as ConfiguraÃ§Ãµes', 'dps-push-addon' ); ?>
                    </button>
                    <span id="dps-push-save-spinner" class="spinner dps-push-spinner"></span>
                </p>
            </form>

        </div>
        <?php
    }
}
