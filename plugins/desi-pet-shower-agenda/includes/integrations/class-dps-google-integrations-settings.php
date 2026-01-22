<?php
/**
 * Google Integrations Settings Page
 *
 * Interface administrativa para configurar integrações com Google Calendar e Tasks.
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
 * Classe de configurações das integrações Google.
 *
 * Adiciona uma aba "Integrações Google" no hub da Agenda
 * para gerenciar conexão OAuth e configurações de sincronização.
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
        
        // Renderiza conteúdo da aba
        add_action( 'dps_agenda_hub_tab_content_google-integrations', [ $this, 'render_tab_content' ] );
        
        // Processa ações (conectar, desconectar, salvar configurações)
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }
    
    /**
     * Adiciona aba "Integrações Google" ao hub da Agenda.
     *
     * @since 2.0.0
     *
     * @param array $tabs Abas existentes.
     * @return array Abas com nova aba adicionada.
     */
    public function add_tab( $tabs ) {
        $tabs['google-integrations'] = [
            'label' => __( 'Integrações Google', 'dps-agenda-addon' ),
            'icon'  => '🔗',
        ];
        
        return $tabs;
    }
    
    /**
     * Renderiza conteúdo da aba de integrações.
     *
     * @since 2.0.0
     */
    public function render_tab_content() {
        // Verifica capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-agenda-addon' ) );
        }
        
        $is_connected = DPS_Google_Auth::is_connected();
        
        // Exibe mensagens de feedback
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura de parâmetro
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        if ( $message ) {
            $this->render_feedback_message( $message );
        }
        
        ?>
        <div class="dps-google-integrations-settings">
            <h2><?php esc_html_e( 'Integrações com Google Workspace', 'dps-agenda-addon' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Conecte o DPS com Google Calendar e Google Tasks para sincronizar agendamentos e tarefas administrativas.', 'dps-agenda-addon' ); ?>
            </p>
            
            <?php $this->render_connection_status( $is_connected ); ?>
            
            <?php if ( ! $is_connected ) : ?>
                <?php $this->render_connection_section(); ?>
            <?php else : ?>
                <?php $this->render_sync_settings_section(); ?>
            <?php endif; ?>
        </div>
        
        <style>
        .dps-google-integrations-settings {
            max-width: 800px;
        }
        .dps-connection-status {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .dps-connection-status.connected {
            border-left: 4px solid #10b981;
            background: #d1fae5;
        }
        .dps-connection-status.disconnected {
            border-left: 4px solid #f59e0b;
            background: #fef3c7;
        }
        .dps-connection-status h3 {
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dps-status-icon {
            font-size: 24px;
        }
        .dps-google-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0ea5e9;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
        }
        .dps-google-btn:hover {
            background: #0284c7;
            color: white;
        }
        .dps-google-btn.disconnect {
            background: #ef4444;
        }
        .dps-google-btn.disconnect:hover {
            background: #dc2626;
        }
        .dps-setup-instructions {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .dps-setup-instructions ol {
            margin-left: 20px;
        }
        .dps-setup-instructions li {
            margin-bottom: 10px;
        }
        .dps-setup-instructions code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    /**
     * Renderiza mensagem de feedback após operações.
     *
     * @since 2.0.0
     *
     * @param string $message Tipo da mensagem.
     */
    private function render_feedback_message( $message ) {
        $messages = [
            'connected' => [
                'type'    => 'success',
                'message' => __( 'Conectado ao Google com sucesso! Agora configure as sincronizações desejadas.', 'dps-agenda-addon' ),
            ],
            'disconnected' => [
                'type'    => 'info',
                'message' => __( 'Desconectado do Google. As sincronizações foram interrompidas.', 'dps-agenda-addon' ),
            ],
            'settings_saved' => [
                'type'    => 'success',
                'message' => __( 'Configurações salvas com sucesso!', 'dps-agenda-addon' ),
            ],
        ];
        
        if ( ! isset( $messages[ $message ] ) ) {
            return;
        }
        
        $msg_data = $messages[ $message ];
        $class    = 'dps-feedback-message dps-feedback-' . esc_attr( $msg_data['type'] );
        
        ?>
        <div class="<?php echo esc_attr( $class ); ?>">
            <?php echo esc_html( $msg_data['message'] ); ?>
        </div>
        <style>
        .dps-feedback-message {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .dps-feedback-success {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #047857;
        }
        .dps-feedback-info {
            background: #e0f2fe;
            border: 1px solid #0ea5e9;
            color: #0369a1;
        }
        </style>
        <?php
    }
    
    /**
     * Renderiza seção de status de conexão.
     *
     * @since 2.0.0
     *
     * @param bool $is_connected Se está conectado.
     */
    private function render_connection_status( $is_connected ) {
        $status_class = $is_connected ? 'connected' : 'disconnected';
        $status_icon  = $is_connected ? '✅' : '⚠️';
        $status_text  = $is_connected 
            ? __( 'Conectado ao Google', 'dps-agenda-addon' )
            : __( 'Não Conectado', 'dps-agenda-addon' );
        
        ?>
        <div class="dps-connection-status <?php echo esc_attr( $status_class ); ?>">
            <h3>
                <span class="dps-status-icon"><?php echo esc_html( $status_icon ); ?></span>
                <?php echo esc_html( $status_text ); ?>
            </h3>
            
            <?php if ( $is_connected ) : ?>
                <p>
                    <?php esc_html_e( 'Sua conta Google está conectada e as integrações estão ativas.', 'dps-agenda-addon' ); ?>
                </p>
                <?php
                $settings = get_option( DPS_Google_Auth::OPTION_NAME, [] );
                if ( ! empty( $settings['connected_at'] ) ) {
                    echo '<p class="description">';
                    printf(
                        /* translators: %s: Data de conexão */
                        esc_html__( 'Conectado em: %s', 'dps-agenda-addon' ),
                        esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $settings['connected_at'] ) )
                    );
                    echo '</p>';
                    
                    // Status do webhook
                    $webhook_data = get_option( 'dps_google_calendar_webhook' );
                    if ( ! empty( $webhook_data['id'] ) ) {
                        echo '<p class="description" style="color: #10b981;">';
                        echo '✅ ' . esc_html__( 'Sincronização bidirecional ativa (Calendar ⇄ DPS)', 'dps-agenda-addon' );
                        echo '</p>';
                        
                        if ( ! empty( $webhook_data['expiration'] ) ) {
                            $expires_at = intval( $webhook_data['expiration'] / 1000 );
                            echo '<p class="description" style="font-size: 0.9em;">';
                            printf(
                                /* translators: %s: Data de expiração */
                                esc_html__( 'Renovação automática em: %s', 'dps-agenda-addon' ),
                                esc_html( date_i18n( get_option( 'date_format' ), $expires_at ) )
                            );
                            echo '</p>';
                        }
                    }
                }
                ?>
                
                <a href="<?php echo esc_url( $this->get_disconnect_url() ); ?>" 
                   class="dps-google-btn disconnect"
                   onclick="return confirm('<?php esc_attr_e( 'Tem certeza que deseja desconectar? As sincronizações serão interrompidas.', 'dps-agenda-addon' ); ?>');">
                    🔌 <?php esc_html_e( 'Desconectar', 'dps-agenda-addon' ); ?>
                </a>
            <?php else : ?>
                <p>
                    <?php esc_html_e( 'Conecte sua conta Google para começar a sincronizar agendamentos e tarefas.', 'dps-agenda-addon' ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderiza seção de conexão (quando não conectado).
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
        <div class="dps-setup-instructions">
            <h3><?php esc_html_e( 'Conectar com Google', 'dps-agenda-addon' ); ?></h3>
            <p>
                <?php esc_html_e( 'Clique no botão abaixo para autorizar o DPS a acessar sua conta Google.', 'dps-agenda-addon' ); ?>
            </p>
            <p class="description">
                <?php esc_html_e( 'Você será redirecionado para a página de consentimento do Google. Após autorizar, será redirecionado de volta para esta página.', 'dps-agenda-addon' ); ?>
            </p>
            
            <a href="<?php echo esc_url( $auth_url ); ?>" class="dps-google-btn">
                🔐 <?php esc_html_e( 'Conectar com Google', 'dps-agenda-addon' ); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Renderiza instruções de configuração inicial.
     *
     * @since 2.0.0
     */
    private function render_setup_instructions() {
        ?>
        <div class="dps-setup-instructions">
            <h3><?php esc_html_e( 'Configuração Inicial Necessária', 'dps-agenda-addon' ); ?></h3>
            <p>
                <?php esc_html_e( 'Antes de conectar, você precisa configurar as credenciais do Google Cloud Console:', 'dps-agenda-addon' ); ?>
            </p>
            
            <ol>
                <li>
                    <?php
                    printf(
                        /* translators: %s: URL do Google Cloud Console */
                        wp_kses_post( __( 'Acesse o <a href="%s" target="_blank">Google Cloud Console</a>', 'dps-agenda-addon' ) ),
                        'https://console.cloud.google.com/'
                    );
                    ?>
                </li>
                <li><?php esc_html_e( 'Crie um novo projeto ou selecione um existente', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( 'Ative as APIs: Google Calendar API e Google Tasks API', 'dps-agenda-addon' ); ?></li>
                <li><?php esc_html_e( 'Crie credenciais OAuth 2.0 (tipo: Web application)', 'dps-agenda-addon' ); ?></li>
                <li>
                    <?php esc_html_e( 'Adicione esta URI de redirecionamento autorizada:', 'dps-agenda-addon' ); ?>
                    <br>
                    <code><?php echo esc_html( admin_url( 'admin.php?page=dps-agenda-hub&tab=google-integrations&action=oauth_callback' ) ); ?></code>
                </li>
                <li>
                    <?php esc_html_e( 'Copie Client ID e Client Secret e adicione no wp-config.php:', 'dps-agenda-addon' ); ?>
                    <br><br>
                    <code>define( 'DPS_GOOGLE_CLIENT_ID', 'seu_client_id_aqui' );</code><br>
                    <code>define( 'DPS_GOOGLE_CLIENT_SECRET', 'seu_client_secret_aqui' );</code>
                </li>
            </ol>
            
            <p>
                <?php esc_html_e( 'Após configurar, recarregue esta página para ver o botão de conexão.', 'dps-agenda-addon' ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Renderiza seção de configurações de sincronização (quando conectado).
     *
     * @since 2.0.0
     */
    private function render_sync_settings_section() {
        $settings = get_option( DPS_Google_Auth::OPTION_NAME, [] );
        $sync_calendar = ! empty( $settings['sync_calendar'] );
        $sync_tasks = ! empty( $settings['sync_tasks'] );
        
        ?>
        <div class="dps-setup-instructions">
            <h3><?php esc_html_e( 'Configurações de Sincronização', 'dps-agenda-addon' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Configure quais funcionalidades sincronizar com o Google.', 'dps-agenda-addon' ); ?>
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_google_save_settings', 'dps_google_nonce' ); ?>
                <input type="hidden" name="dps_action" value="save_google_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>
                                <?php esc_html_e( 'Google Calendar', 'dps-agenda-addon' ); ?>
                            </label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="sync_calendar" value="1" <?php checked( $sync_calendar ); ?>>
                                    <?php esc_html_e( 'Sincronizar agendamentos com Google Calendar', 'dps-agenda-addon' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Cria eventos no Google Calendar quando agendamentos são salvos no DPS.', 'dps-agenda-addon' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label>
                                <?php esc_html_e( 'Google Tasks', 'dps-agenda-addon' ); ?>
                            </label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="sync_tasks" value="1" <?php checked( $sync_tasks ); ?>>
                                    <?php esc_html_e( 'Sincronizar tarefas administrativas com Google Tasks', 'dps-agenda-addon' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Cria tarefas no Google Tasks para follow-ups pós-atendimento, cobranças pendentes e mensagens do portal.', 'dps-agenda-addon' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Salvar Configurações', 'dps-agenda-addon' ) ); ?>
                
                <p class="description" style="margin-top: 20px; padding: 15px; background: #d1fae5; border-left: 4px solid #10b981;">
                    ✅ <?php esc_html_e( 'Fase 4 concluída: Integração completa com Google Calendar + Google Tasks implementada!', 'dps-agenda-addon' ); ?>
                    <br>
                    <small>
                        <?php esc_html_e( '• Sincronização bidirecional de agendamentos (Calendar ⇄ DPS)', 'dps-agenda-addon' ); ?><br>
                        <?php esc_html_e( '• Tarefas administrativas automáticas (follow-ups, cobranças, mensagens)', 'dps-agenda-addon' ); ?>
                    </small>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Processa ações (conectar, desconectar, salvar configurações).
     *
     * @since 2.0.0
     */
    public function handle_actions() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verifica parâmetros GET para roteamento
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
        
        // Salvar configurações
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado em handle_save_settings
        $post_action = isset( $_POST['dps_action'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_action'] ) ) : '';
        if ( 'save_google_settings' === $post_action ) {
            $this->handle_save_settings();
        }
    }
    
    /**
     * Processa salvamento de configurações.
     *
     * @since 2.0.0
     */
    private function handle_save_settings() {
        // Usa helper para verificar nonce com capability
        if ( class_exists( 'DPS_Request_Validator' ) ) {
            if ( ! DPS_Request_Validator::verify_admin_form( 'dps_google_save_settings', 'dps_google_nonce' ) ) {
                wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
            }
        } else {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ) );
            }
            $nonce = isset( $_POST['dps_google_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_google_nonce'] ) ) : '';
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dps_google_save_settings' ) ) {
                wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
            }
        }
        
        // Obtém settings atuais
        $settings = get_option( DPS_Google_Auth::OPTION_NAME, [] );
        
        // Atualiza configurações
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
     * Processa callback OAuth após autorização.
     *
     * @since 2.0.0
     */
    private function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ) );
        }
        
        // Verifica state (nonce)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- State é o nonce que será verificado
        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        if ( empty( $state ) || ! wp_verify_nonce( $state, 'dps_google_oauth' ) ) {
            wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
        }
        
        // Verifica erro
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Já verificamos o nonce acima
        if ( ! empty( $_GET['error'] ) ) {
            $error_msg = sprintf(
                /* translators: %s: Mensagem de erro */
                __( 'Erro ao autorizar: %s', 'dps-agenda-addon' ),
                sanitize_text_field( wp_unslash( $_GET['error'] ) )
            );
            wp_die( esc_html( $error_msg ) );
        }
        
        // Troca code por tokens
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Já verificamos o nonce acima
        if ( empty( $_GET['code'] ) ) {
            wp_die( esc_html__( 'Authorization code não recebido.', 'dps-agenda-addon' ) );
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Já verificamos o nonce acima
        $code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $result = DPS_Google_Auth::exchange_code_for_tokens( $code );
        
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ) );
        }
        
        // Redireciona de volta para a página de configurações com mensagem de sucesso
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
     * Processa desconexão.
     *
     * @since 2.0.0
     */
    private function handle_disconnect() {
        // Usa helper para verificar nonce com capability
        if ( class_exists( 'DPS_Request_Validator' ) ) {
            if ( ! DPS_Request_Validator::verify_admin_action( 'dps_google_disconnect' ) ) {
                wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
            }
        } else {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ) );
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce passado via URL
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'dps_google_disconnect' ) ) {
                wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
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
     * Obtém URL de desconexão com nonce.
     *
     * @since 2.0.0
     *
     * @return string URL de desconexão.
     */
    private function get_disconnect_url() {
        return wp_nonce_url(
            admin_url( 'admin.php?page=dps-agenda-hub&tab=google-integrations&action=dps_google_disconnect' ),
            'dps_google_disconnect'
        );
    }
}
