<?php
/**
 * Google Integrations Settings Page
 *
 * Interface administrativa para configurar integraÃ§Ãµes com Google Calendar e Tasks.
 *
 * @package    DPS_Agenda_Addon
 * @subpackage Integrations
 * @since      2.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de configuraÃ§Ãµes das integraÃ§Ãµes Google.
 *
 * Adiciona uma aba "IntegraÃ§Ãµes Google" no hub da Agenda
 * para gerenciar conexÃ£o OAuth e configuraÃ§Ãµes de sincronizaÃ§Ã£o.
 *
 * @since 2.0.0
 */
class DPS_Google_Integrations_Settings {

    /**
     * Inicializa a classe.
     *
     * @since 2.0.0
     */
    public function __construct() {
        // Adiciona aba ao hub da Agenda
        add_filter( 'dps_agenda_hub_tabs', [ $this, 'add_tab' ], 20 );

        // Renderiza conteÃºdo da aba
        add_action( 'dps_agenda_hub_tab_content_google-integrations', [ $this, 'render_tab_content' ] );

        // Processa aÃ§Ãµes (conectar, desconectar, salvar configuraÃ§Ãµes)
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    /**
     * Adiciona aba "IntegraÃ§Ãµes Google" ao hub da Agenda.
     *
     * @since 2.0.0
     *
     * @param array $tabs Abas existentes.
     * @return array Abas com nova aba adicionada.
     */
    public function add_tab( $tabs ) {
        $tabs['google-integrations'] = __( 'IntegraÃ§Ãµes Google', 'dps-agenda-addon' );

        return $tabs;
    }

    /**
     * Renderiza conteÃºdo da aba de integraÃ§Ãµes.
     *
     * @since 2.0.0
     */
    public function render_tab_content() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'VocÃª nÃ£o tem permissÃ£o para acessar esta pÃ¡gina.', 'dps-agenda-addon' ) );
        }

        $is_connected = DPS_Google_Auth::is_connected();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura de parÃ¢metro.
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        ?>
        <div class="dps-agenda-admin-page dps-google-integrations-settings">
            <?php if ( $message ) : ?>
                <?php $this->render_feedback_message( $message ); ?>
            <?php endif; ?>

            <section class="dps-agenda-admin-card">
                <div class="dps-agenda-admin-card__header">
                    <div>
                        <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Agenda + Google Workspace', 'dps-agenda-addon' ); ?></p>
                        <h2 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'IntegraÃ§Ãµes com Google Workspace', 'dps-agenda-addon' ); ?></h2>
                        <p class="dps-agenda-admin-description">
                            <?php esc_html_e( 'Conecte o DPS com Google Calendar e Google Tasks para sincronizar agendamentos e tarefas administrativas seguindo o padrÃ£o visual DPS Signature da Agenda.', 'dps-agenda-addon' ); ?>
                        </p>
                    </div>
                    <div class="dps-agenda-admin-chips">
                        <span class="dps-agenda-admin-chip"><?php esc_html_e( 'Google Calendar', 'dps-agenda-addon' ); ?></span>
                        <span class="dps-agenda-admin-chip"><?php esc_html_e( 'Google Tasks', 'dps-agenda-addon' ); ?></span>
                    </div>
                </div>

                <?php $this->render_connection_status( $is_connected ); ?>

                <?php if ( ! $is_connected ) : ?>
                    <?php $this->render_connection_section(); ?>
                <?php else : ?>
                    <?php $this->render_sync_settings_section(); ?>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    /**
     * Renderiza mensagem de feedback apÃ³s operaÃ§Ãµes.
     *
     * @since 2.0.0
     *
     * @param string $message Tipo da mensagem.
     */
    private function render_feedback_message( $message ) {
        $messages = [
            'connected' => [
                'type'    => 'success',
                'message' => __( 'Conectado ao Google com sucesso! Agora configure as sincronizaÃ§Ãµes desejadas.', 'dps-agenda-addon' ),
            ],
            'disconnected' => [
                'type'    => 'info',
                'message' => __( 'Desconectado do Google. As sincronizaÃ§Ãµes foram interrompidas.', 'dps-agenda-addon' ),
            ],
            'settings_saved' => [
                'type'    => 'success',
                'message' => __( 'ConfiguraÃ§Ãµes salvas com sucesso!', 'dps-agenda-addon' ),
            ],
        ];

        if ( ! isset( $messages[ $message ] ) ) {
            return;
        }

        $class_map = [
            'success' => 'dps-agenda-admin-notice--success',
            'info'    => 'dps-agenda-admin-notice--info',
        ];

        $message_data = $messages[ $message ];
        $class_name   = isset( $class_map[ $message_data['type'] ] ) ? $class_map[ $message_data['type'] ] : 'dps-agenda-admin-notice--info';
        ?>
        <div class="dps-agenda-admin-notice <?php echo esc_attr( $class_name ); ?>" role="status">
            <?php echo esc_html( $message_data['message'] ); ?>
        </div>
        <?php
    }

    /**
     * Renderiza seÃ§Ã£o de status de conexÃ£o.
     *
     * @since 2.0.0
     *
     * @param bool $is_connected Se estÃ¡ conectado.
     */
    private function render_connection_status( $is_connected ) {
        $status_text = $is_connected
            ? __( 'Conectado ao Google', 'dps-agenda-addon' )
            : __( 'ConexÃ£o pendente', 'dps-agenda-addon' );

        $status_icon = $is_connected ? 'âœ“' : '!';
        $status_chip = $is_connected ? 'dps-agenda-admin-chip--success' : 'dps-agenda-admin-chip--warning';
        ?>
        <div class="dps-agenda-admin-card dps-agenda-admin-card--subtle dps-google-status-card">
            <div class="dps-google-status-card__header">
                <div>
                    <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'Status da conexÃ£o', 'dps-agenda-addon' ); ?></p>
                    <h3 class="dps-agenda-admin-subtitle">
                        <span class="dps-google-status-card__icon" aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
                        <?php echo esc_html( $status_text ); ?>
                    </h3>
                    <p class="dps-agenda-admin-description">
                        <?php if ( $is_connected ) : ?>
                            <?php esc_html_e( 'Sua conta Google estÃ¡ conectada e pronta para sincronizar agenda e tarefas.', 'dps-agenda-addon' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Conecte sua conta para ativar os fluxos de Calendar e Tasks no DPS.', 'dps-agenda-addon' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="dps-agenda-admin-chip <?php echo esc_attr( $status_chip ); ?>">
                    <?php echo esc_html( $is_connected ? __( 'Ativo', 'dps-agenda-addon' ) : __( 'Pendente', 'dps-agenda-addon' ) ); ?>
                </span>
            </div>

            <?php if ( $is_connected ) : ?>
                <?php
                $settings   = get_option( DPS_Google_Auth::OPTION_NAME, [] );
                $meta_items = [];

                if ( ! empty( $settings['connected_at'] ) ) {
                    $meta_items[] = sprintf(
                        /* translators: %s: Data de conexÃ£o. */
                        __( 'Conectado em %s', 'dps-agenda-addon' ),
                        date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $settings['connected_at'] )
                    );
                }

                $webhook_data = get_option( 'dps_google_calendar_webhook' );
                if ( ! empty( $webhook_data['id'] ) ) {
                    $meta_items[] = __( 'SincronizaÃ§Ã£o bidirecional ativa', 'dps-agenda-addon' );

                    if ( ! empty( $webhook_data['expiration'] ) ) {
                        $expires_at   = intval( $webhook_data['expiration'] / 1000 );
                        $meta_items[] = sprintf(
                            /* translators: %s: Data de renovaÃ§Ã£o. */
                            __( 'RenovaÃ§Ã£o automÃ¡tica em %s', 'dps-agenda-addon' ),
                            date_i18n( get_option( 'date_format' ), $expires_at )
                        );
                    }
                }
                ?>

                <?php if ( ! empty( $meta_items ) ) : ?>
                    <div class="dps-agenda-admin-chips">
                        <?php foreach ( $meta_items as $meta_item ) : ?>
                            <span class="dps-agenda-admin-chip"><?php echo esc_html( $meta_item ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="dps-agenda-admin-form-actions">
                    <a
                        href="<?php echo esc_url( $this->get_disconnect_url() ); ?>"
                        class="dps-btn dps-btn--danger"
                        onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja desconectar? As sincronizaÃ§Ãµes serÃ£o interrompidas.', 'dps-agenda-addon' ) ); ?>');"
                    >
                        <?php esc_html_e( 'Desconectar', 'dps-agenda-addon' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza seÃ§Ã£o de conexÃ£o (quando nÃ£o conectado).
     *
     * @since 2.0.0
     */
    private function render_connection_section() {
        $client_id     = defined( 'DPS_GOOGLE_CLIENT_ID' ) ? DPS_GOOGLE_CLIENT_ID : '';
        $client_secret = defined( 'DPS_GOOGLE_CLIENT_SECRET' ) ? DPS_GOOGLE_CLIENT_SECRET : '';

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            $this->render_setup_instructions();
            return;
        }

        $auth_url = DPS_Google_Auth::get_auth_url();
        ?>
        <div class="dps-agenda-admin-card dps-agenda-admin-card--subtle">
            <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'PrÃ³ximo passo', 'dps-agenda-addon' ); ?></p>
            <h3 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'Conectar com Google', 'dps-agenda-addon' ); ?></h3>
            <p class="dps-agenda-admin-description">
                <?php esc_html_e( 'Clique no botÃ£o abaixo para autorizar o DPS a acessar sua conta Google.', 'dps-agenda-addon' ); ?>
            </p>
            <p class="dps-agenda-admin-field__description">
                <?php esc_html_e( 'VocÃª serÃ¡ redirecionado para a pÃ¡gina de consentimento do Google e retornarÃ¡ automaticamente para esta aba apÃ³s a autorizaÃ§Ã£o.', 'dps-agenda-addon' ); ?>
            </p>

            <div class="dps-agenda-admin-form-actions">
                <a href="<?php echo esc_url( $auth_url ); ?>" class="dps-btn dps-btn--primary">
                    <?php esc_html_e( 'Conectar com Google', 'dps-agenda-addon' ); ?>
                </a>
            </div>

            <div class="dps-agenda-admin-feature-grid">
                <article class="dps-agenda-admin-feature-card">
                    <h4><?php esc_html_e( 'Calendar', 'dps-agenda-addon' ); ?></h4>
                    <p><?php esc_html_e( 'Espelha agendamentos do DPS no Google Calendar e preserva a leitura operacional da Agenda.', 'dps-agenda-addon' ); ?></p>
                </article>
                <article class="dps-agenda-admin-feature-card">
                    <h4><?php esc_html_e( 'Tasks', 'dps-agenda-addon' ); ?></h4>
                    <p><?php esc_html_e( 'Cria tarefas automÃ¡ticas para follow-ups, cobranÃ§as pendentes e rotinas administrativas.', 'dps-agenda-addon' ); ?></p>
                </article>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza instruÃ§Ãµes de configuraÃ§Ã£o inicial.
     *
     * @since 2.0.0
     */
    private function render_setup_instructions() {
        ?>
        <div class="dps-agenda-admin-card dps-agenda-admin-card--subtle">
            <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'PreparaÃ§Ã£o', 'dps-agenda-addon' ); ?></p>
            <h3 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'ConfiguraÃ§Ã£o inicial necessÃ¡ria', 'dps-agenda-addon' ); ?></h3>
            <p class="dps-agenda-admin-description">
                <?php esc_html_e( 'Antes de conectar, configure as credenciais do Google Cloud Console:', 'dps-agenda-addon' ); ?>
            </p>

            <ol class="dps-agenda-admin-steps">
                <li>
                    <?php
                    printf(
                        /* translators: %s: URL do Google Cloud Console. */
                        wp_kses_post( __( 'Acesse o <a href="%s" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>.', 'dps-agenda-addon' ) ),
                        'https://console.cloud.google.com/'
                    );
                    ?>
                </li>
                <li><?php esc_html_e( 'Crie um novo projeto ou selecione um existente.', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( 'Ative as APIs Google Calendar API e Google Tasks API.', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( 'Crie credenciais OAuth 2.0 do tipo Web application.', 'dps-agenda-addon' ); ?></li>
                <li>
                    <?php esc_html_e( 'Adicione esta URI de redirecionamento autorizada:', 'dps-agenda-addon' ); ?>
                    <code><?php echo esc_html( admin_url( 'admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback' ) ); ?></code>
                </li>
                <li>
                    <?php esc_html_e( 'Adicione as constantes abaixo no wp-config.php:', 'dps-agenda-addon' ); ?>
                    <code>define( 'DPS_GOOGLE_CLIENT_ID', 'seu_client_id_aqui' );</code>
                    <code>define( 'DPS_GOOGLE_CLIENT_SECRET', 'seu_client_secret_aqui' );</code>
                </li>
            </ol>

            <p class="dps-agenda-admin-field__description">
                <?php esc_html_e( 'Depois de configurar as credenciais, recarregue esta pÃ¡gina para liberar a conexÃ£o.', 'dps-agenda-addon' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Renderiza seÃ§Ã£o de configuraÃ§Ãµes de sincronizaÃ§Ã£o (quando conectado).
     *
     * @since 2.0.0
     */
    private function render_sync_settings_section() {
        $settings      = get_option( DPS_Google_Auth::OPTION_NAME, [] );
        $sync_calendar = ! empty( $settings['sync_calendar'] );
        $sync_tasks    = ! empty( $settings['sync_tasks'] );
        ?>
        <div class="dps-agenda-admin-card dps-agenda-admin-card--subtle">
            <p class="dps-agenda-admin-eyebrow"><?php esc_html_e( 'SincronizaÃ§Ã£o', 'dps-agenda-addon' ); ?></p>
            <h3 class="dps-agenda-admin-subtitle"><?php esc_html_e( 'ConfiguraÃ§Ãµes de sincronizaÃ§Ã£o', 'dps-agenda-addon' ); ?></h3>
            <p class="dps-agenda-admin-description">
                <?php esc_html_e( 'Escolha quais fluxos da Agenda serÃ£o espelhados no Google Workspace.', 'dps-agenda-addon' ); ?>
            </p>

            <form method="post" action="" class="dps-agenda-admin-form">
                <?php wp_nonce_field( 'dps_google_save_settings', 'dps_google_nonce' ); ?>
                <input type="hidden" name="dps_action" value="save_google_settings">

                <div class="dps-google-toggle-list">
                    <label class="dps-google-toggle">
                        <input type="checkbox" name="sync_calendar" value="1" <?php checked( $sync_calendar ); ?>>
                        <span class="dps-google-toggle__body">
                            <span class="dps-google-toggle__title"><?php esc_html_e( 'Google Calendar', 'dps-agenda-addon' ); ?></span>
                            <span class="dps-google-toggle__description"><?php esc_html_e( 'Cria e atualiza eventos no Google Calendar sempre que um agendamento Ã© salvo ou alterado no DPS.', 'dps-agenda-addon' ); ?></span>
                        </span>
                    </label>

                    <label class="dps-google-toggle">
                        <input type="checkbox" name="sync_tasks" value="1" <?php checked( $sync_tasks ); ?>>
                        <span class="dps-google-toggle__body">
                            <span class="dps-google-toggle__title"><?php esc_html_e( 'Google Tasks', 'dps-agenda-addon' ); ?></span>
                            <span class="dps-google-toggle__description"><?php esc_html_e( 'Cria tarefas automÃ¡ticas para follow-ups, cobranÃ§as pendentes e mensagens operacionais.', 'dps-agenda-addon' ); ?></span>
                        </span>
                    </label>
                </div>

                <div class="dps-agenda-admin-form-actions">
                    <button type="submit" class="dps-btn dps-btn--primary">
                        <?php esc_html_e( 'Salvar configuraÃ§Ãµes', 'dps-agenda-addon' ); ?>
                    </button>
                </div>
            </form>

            <div class="dps-agenda-admin-notice dps-agenda-admin-notice--success dps-google-release-note">
                <strong><?php esc_html_e( 'Fase 4 concluÃ­da:', 'dps-agenda-addon' ); ?></strong>
                <?php esc_html_e( ' integraÃ§Ã£o completa com Google Calendar e Google Tasks disponÃ­vel no hub da Agenda.', 'dps-agenda-addon' ); ?>
                <ul class="dps-agenda-admin-list">
                    <li><?php esc_html_e( 'SincronizaÃ§Ã£o bidirecional de agendamentos entre Calendar e DPS.', 'dps-agenda-addon' ); ?></li>
                    <li><?php esc_html_e( 'Tarefas administrativas automÃ¡ticas para follow-ups, cobranÃ§as e mensagens.', 'dps-agenda-addon' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Processa aÃ§Ãµes (conectar, desconectar, salvar configuraÃ§Ãµes).
     *
     * @since 2.0.0
     */
    public function handle_actions() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verifica parÃ¢metros GET para roteamento
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $tab    = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        // phpcs:enable

        // Callback OAuth
        if ( 'dps-agenda-hub' === $page && 'google-integrations' === $tab && 'oauth_callback' === $action ) {
            $this->handle_oauth_callback();
        }

        // Desconectar
        if ( 'dps_google_disconnect' === $action ) {
            $this->handle_disconnect();
        }

        // Salvar configuraÃ§Ãµes
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado em handle_save_settings
        $post_action = isset( $_POST['dps_action'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_action'] ) ) : '';
        if ( 'save_google_settings' === $post_action ) {
            $this->handle_save_settings();
        }
    }

    /**
     * Processa salvamento de configuraÃ§Ãµes.
     *
     * @since 2.0.0
     */
    private function handle_save_settings() {
        // Usa helper para verificar nonce com capability
        if ( class_exists( 'DPS_Request_Validator' ) ) {
            if ( ! DPS_Request_Validator::verify_admin_form( 'dps_google_save_settings', 'dps_google_nonce' ) ) {
                wp_die( esc_html__( 'Token de seguranÃ§a invÃ¡lido.', 'dps-agenda-addon' ) );
            }
        } else {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'PermissÃ£o negada.', 'dps-agenda-addon' ) );
            }
            $nonce = isset( $_POST['dps_google_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_google_nonce'] ) ) : '';
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dps_google_save_settings' ) ) {
                wp_die( esc_html__( 'Token de seguranÃ§a invÃ¡lido.', 'dps-agenda-addon' ) );
            }
        }

        // ObtÃ©m settings atuais
        $settings = get_option( DPS_Google_Auth::OPTION_NAME, [] );

        // Atualiza configuraÃ§Ãµes
        $settings['sync_calendar'] = ! empty( $_POST['sync_calendar'] ) ? 1 : 0;
        $settings['sync_tasks'] = ! empty( $_POST['sync_tasks'] ) ? 1 : 0;

        update_option( DPS_Google_Auth::OPTION_NAME, $settings );

        // Redireciona com mensagem de sucesso
        $redirect_url = add_query_arg(
            [
                'page'    => 'dps-agenda-hub',
                'tab'     => 'google-integrations',
                'message' => 'settings_saved',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Processa callback OAuth apÃ³s autorizaÃ§Ã£o.
     *
     * @since 2.0.0
     */
    private function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'PermissÃ£o negada.', 'dps-agenda-addon' ) );
        }

        // Verifica state (nonce)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- State Ã© o nonce que serÃ¡ verificado
        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        if ( empty( $state ) || ! wp_verify_nonce( $state, 'dps_google_oauth' ) ) {
            wp_die( esc_html__( 'Token de seguranÃ§a invÃ¡lido.', 'dps-agenda-addon' ) );
        }

        // Verifica erro
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- JÃ¡ verificamos o nonce acima
        if ( ! empty( $_GET['error'] ) ) {
            $error_msg = sprintf(
                /* translators: %s: Mensagem de erro */
                __( 'Erro ao autorizar: %s', 'dps-agenda-addon' ),
                sanitize_text_field( wp_unslash( $_GET['error'] ) )
            );
            wp_die( esc_html( $error_msg ) );
        }

        // Troca code por tokens
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- JÃ¡ verificamos o nonce acima
        if ( empty( $_GET['code'] ) ) {
            wp_die( esc_html__( 'Authorization code nÃ£o recebido.', 'dps-agenda-addon' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- JÃ¡ verificamos o nonce acima
        $code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $result = DPS_Google_Auth::exchange_code_for_tokens( $code );

        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ) );
        }

        // Redireciona de volta para a pÃ¡gina de configuraÃ§Ãµes com mensagem de sucesso
        $redirect_url = add_query_arg(
            [
                'page'    => 'dps-agenda-hub',
                'tab'     => 'google-integrations',
                'message' => 'connected',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Processa desconexÃ£o.
     *
     * @since 2.0.0
     */
    private function handle_disconnect() {
        // Usa helper para verificar nonce com capability
        if ( class_exists( 'DPS_Request_Validator' ) ) {
            if ( ! DPS_Request_Validator::verify_admin_action( 'dps_google_disconnect' ) ) {
                wp_die( esc_html__( 'Token de seguranÃ§a invÃ¡lido.', 'dps-agenda-addon' ) );
            }
        } else {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'PermissÃ£o negada.', 'dps-agenda-addon' ) );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce passado via URL
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dps_google_disconnect' ) ) {
                wp_die( esc_html__( 'Token de seguranÃ§a invÃ¡lido.', 'dps-agenda-addon' ) );
            }
        }

        DPS_Google_Auth::disconnect();

        // Redireciona de volta
        $redirect_url = add_query_arg(
            [
                'page'    => 'dps-agenda-hub',
                'tab'     => 'google-integrations',
                'message' => 'disconnected',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * ObtÃ©m URL de desconexÃ£o com nonce.
     *
     * @since 2.0.0
     *
     * @return string URL de desconexÃ£o.
     */
    private function get_disconnect_url() {
        return wp_nonce_url(
            admin_url( 'admin.php?page=dps-agenda-hub&tab=google-integrations&action=dps_google_disconnect' ),
            'dps_google_disconnect'
        );
    }
}
