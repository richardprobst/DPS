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
                                    <input type="checkbox" name="sync_tasks" value="1" disabled>
                                    <?php esc_html_e( 'Sincronizar follow-ups e cobranças com Google Tasks', 'dps-agenda-addon' ); ?>
                                    <span class="description" style="color: #f59e0b;">
                                        <?php esc_html_e( '(Disponível na Fase 4)', 'dps-agenda-addon' ); ?>
                                    </span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Salvar Configurações', 'dps-agenda-addon' ) ); ?>
                
                <p class="description" style="margin-top: 20px; padding: 15px; background: #d1fae5; border-left: 4px solid #10b981;">
                    ✅ <?php esc_html_e( 'Fase 2 concluída: Sincronização Google Calendar implementada (DPS → Calendar).', 'dps-agenda-addon' ); ?>
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
        // Callback OAuth
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'dps-agenda-hub' &&
             isset( $_GET['tab'] ) && $_GET['tab'] === 'google-integrations' &&
             isset( $_GET['action'] ) && $_GET['action'] === 'oauth_callback' ) {
            
            $this->handle_oauth_callback();
        }
        
        // Desconectar
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'dps_google_disconnect' ) {
            $this->handle_disconnect();
        }
        
        // Salvar configurações
        if ( isset( $_POST['dps_action'] ) && $_POST['dps_action'] === 'save_google_settings' ) {
            $this->handle_save_settings();
        }
    }
    
    /**
     * Processa salvamento de configurações.
     *
     * @since 2.0.0
     */
    private function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ) );
        }
        
        // Verifica nonce
        if ( empty( $_POST['dps_google_nonce'] ) || ! wp_verify_nonce( $_POST['dps_google_nonce'], 'dps_google_save_settings' ) ) {
            wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
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
        if ( empty( $_GET['state'] ) || ! wp_verify_nonce( $_GET['state'], 'dps_google_oauth' ) ) {
            wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
        }
        
        // Verifica erro
        if ( ! empty( $_GET['error'] ) ) {
            $error_msg = sprintf(
                /* translators: %s: Mensagem de erro */
                __( 'Erro ao autorizar: %s', 'dps-agenda-addon' ),
                sanitize_text_field( $_GET['error'] )
            );
            wp_die( esc_html( $error_msg ) );
        }
        
        // Troca code por tokens
        if ( empty( $_GET['code'] ) ) {
            wp_die( esc_html__( 'Authorization code não recebido.', 'dps-agenda-addon' ) );
        }
        
        $code   = sanitize_text_field( $_GET['code'] );
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
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permissão negada.', 'dps-agenda-addon' ) );
        }
        
        // Verifica nonce
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'dps_google_disconnect' ) ) {
            wp_die( esc_html__( 'Token de segurança inválido.', 'dps-agenda-addon' ) );
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
