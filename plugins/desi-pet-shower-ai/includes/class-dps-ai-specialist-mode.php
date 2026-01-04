<?php
/**
 * Modo Especialista - Chat interno para admin.
 *
 * Responsável por:
 * - Interface de chat restrita a admins
 * - Acesso a dados completos (histórico, métricas, clientes)
 * - System prompt técnico para equipe interna
 * - Comandos especiais tipo "/" para buscar dados
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Modo Especialista da IA.
 */
class DPS_AI_Specialist_Mode {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Specialist_Mode|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Specialist_Mode
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
        add_action( 'admin_menu', [ $this, 'register_menu' ], 21 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_dps_ai_specialist_query', [ $this, 'handle_specialist_query' ] );
    }

    /**
     * Registra a página de modo especialista no menu admin.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-ai-hub (aba "Modo Especialista").
     */
    public function register_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'IA – Modo Especialista', 'dps-ai' ),
            __( 'IA – Modo Especialista', 'dps-ai' ),
            'manage_options',
            'dps-ai-specialist',
            [ $this, 'render_interface' ]
        );
    }

    /**
     * Carrega assets do modo especialista.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'desi-pet-shower_page_dps-ai-specialist' !== $hook ) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'dps-ai-specialist-mode',
            DPS_AI_ADDON_URL . 'assets/css/dps-ai-specialist-mode.css',
            [],
            DPS_AI_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'dps-ai-specialist-mode',
            DPS_AI_ADDON_URL . 'assets/js/dps-ai-specialist-mode.js',
            [ 'jquery' ],
            DPS_AI_VERSION,
            true
        );

        wp_localize_script(
            'dps-ai-specialist-mode',
            'dpsAiSpecialist',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'dps_ai_specialist_nonce' ),
                'i18n' => [
                    'error_generic' => __( 'Erro ao processar requisição. Tente novamente.', 'dps-ai' ),
                    'processing' => __( 'Processando...', 'dps-ai' ),
                    'type_message' => __( 'Digite sua consulta ou comando (ex: /buscar_cliente João)...', 'dps-ai' ),
                ],
            ]
        );
    }

    /**
     * Renderiza interface do modo especialista.
     */
    public function render_interface() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
        }

        ?>
        <div class="wrap dps-ai-specialist-mode">
            <h1><?php esc_html_e( 'IA – Modo Especialista', 'dps-ai' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Chat interno com acesso a dados completos do sistema. Use comandos especiais para buscar informações:', 'dps-ai' ); ?>
                <strong>/buscar_cliente [nome]</strong>, 
                <strong>/historico [cliente_id]</strong>, 
                <strong>/metricas [período]</strong>
            </p>

            <div class="dps-specialist-container">
                <!-- Histórico de Conversas -->
                <div class="dps-specialist-messages" id="dps-specialist-messages">
                    <div class="dps-specialist-welcome">
                        <p>👨‍💼 <?php esc_html_e( 'Bem-vindo ao Modo Especialista!', 'dps-ai' ); ?></p>
                        <p><?php esc_html_e( 'Este é um chat interno com acesso avançado aos dados do sistema. Você pode fazer perguntas técnicas ou usar comandos especiais:', 'dps-ai' ); ?></p>
                        <ul>
                            <li><code>/buscar_cliente João</code> - <?php esc_html_e( 'Busca dados de um cliente pelo nome', 'dps-ai' ); ?></li>
                            <li><code>/historico 123</code> - <?php esc_html_e( 'Exibe histórico de conversas de um cliente', 'dps-ai' ); ?></li>
                            <li><code>/metricas 7</code> - <?php esc_html_e( 'Exibe métricas dos últimos N dias', 'dps-ai' ); ?></li>
                            <li><code>/conversas whatsapp</code> - <?php esc_html_e( 'Lista conversas de um canal específico', 'dps-ai' ); ?></li>
                        </ul>
                        <p><?php esc_html_e( 'Ou faça perguntas diretas sobre dados, padrões e insights do sistema.', 'dps-ai' ); ?></p>
                    </div>
                </div>

                <!-- Formulário de Input -->
                <div class="dps-specialist-input-container">
                    <form id="dps-specialist-form" autocomplete="off">
                        <textarea 
                            id="dps-specialist-query" 
                            name="query" 
                            rows="6" 
                            placeholder="<?php esc_attr_e( 'Digite sua consulta ou comando (ex: /buscar_cliente João)...', 'dps-ai' ); ?>"
                            required
                        ></textarea>
                        <div class="dps-specialist-form-actions">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php esc_html_e( 'Processar Consulta', 'dps-ai' ); ?>
                            </button>
                            <span class="dps-specialist-shortcut-hint">
                                <?php esc_html_e( 'Pressione Ctrl+Enter para enviar', 'dps-ai' ); ?>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handler AJAX para processar consultas do especialista.
     */
    public function handle_specialist_query() {
        check_ajax_referer( 'dps_ai_specialist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-ai' ) ] );
        }

        $query = isset( $_POST['query'] ) ? sanitize_textarea_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( empty( $query ) ) {
            wp_send_json_error( [ 'message' => __( 'Consulta vazia.', 'dps-ai' ) ] );
        }

        // Detectar se é um comando especial
        if ( strpos( $query, '/' ) === 0 ) {
            $response = $this->process_command( $query );
        } else {
            $response = $this->process_natural_query( $query );
        }

        wp_send_json_success( [
            'response' => $response,
            'query' => $query,
        ] );
    }

    /**
     * Processa comandos especiais tipo "/".
     *
     * @param string $command Comando completo.
     * @return string Resposta formatada.
     */
    private function process_command( $command ) {
        $parts = explode( ' ', trim( $command ), 2 );
        $cmd   = strtolower( $parts[0] );
        $arg   = isset( $parts[1] ) ? trim( $parts[1] ) : '';

        switch ( $cmd ) {
            case '/buscar_cliente':
                return $this->cmd_search_client( $arg );

            case '/historico':
                return $this->cmd_client_history( $arg );

            case '/metricas':
                return $this->cmd_metrics( $arg );

            case '/conversas':
                return $this->cmd_conversations( $arg );

            default:
                return sprintf(
                    __( '❌ Comando desconhecido: %s. Use /buscar_cliente, /historico, /metricas ou /conversas', 'dps-ai' ),
                    esc_html( $cmd )
                );
        }
    }

    /**
     * Comando: Buscar cliente por nome.
     *
     * @param string $name Nome do cliente.
     * @return string Resposta formatada.
     */
    private function cmd_search_client( $name ) {
        if ( empty( $name ) ) {
            return __( '❌ Especifique o nome do cliente. Exemplo: /buscar_cliente João', 'dps-ai' );
        }

        $users = get_users( [
            'search' => '*' . $name . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number' => 10,
        ] );

        if ( empty( $users ) ) {
            return sprintf( __( '❌ Nenhum cliente encontrado com o nome "%s".', 'dps-ai' ), esc_html( $name ) );
        }

        $response = sprintf( __( '✅ Encontrados %d cliente(s):', 'dps-ai' ), count( $users ) ) . "\n\n";

        foreach ( $users as $user ) {
            $response .= sprintf(
                "👤 **%s** (ID: %d)\n- Email: %s\n- Login: %s\n\n",
                $user->display_name,
                $user->ID,
                $user->user_email,
                $user->user_login
            );
        }

        return $response;
    }

    /**
     * Comando: Histórico de conversas de um cliente.
     *
     * @param string $client_id ID do cliente.
     * @return string Resposta formatada.
     */
    private function cmd_client_history( $client_id ) {
        if ( empty( $client_id ) || ! is_numeric( $client_id ) ) {
            return __( '❌ Especifique o ID do cliente. Exemplo: /historico 123', 'dps-ai' );
        }

        $client_id = intval( $client_id );
        $repo = DPS_AI_Conversations_Repository::get_instance();

        $conversations = $repo->get_conversations_by_customer( $client_id, 10 );

        if ( empty( $conversations ) ) {
            return sprintf( __( '❌ Nenhuma conversa encontrada para o cliente ID %d.', 'dps-ai' ), $client_id );
        }

        $user_data = get_userdata( $client_id );
        $name = $user_data ? $user_data->display_name : sprintf( __( 'Cliente #%d', 'dps-ai' ), $client_id );

        $response = sprintf( __( '✅ Histórico de %s (últimas 10 conversas):', 'dps-ai' ), $name ) . "\n\n";

        foreach ( $conversations as $conv ) {
            $messages = $repo->get_messages( $conv->id, 100 );
            $msg_count = count( $messages );

            $response .= sprintf(
                "💬 **Conversa #%d** (%s)\n- Canal: %s\n- Iniciada: %s\n- Mensagens: %d\n\n",
                $conv->id,
                $conv->status,
                $conv->channel,
                $conv->started_at,
                $msg_count
            );
        }

        return $response;
    }

    /**
     * Comando: Métricas de uso dos últimos N dias.
     *
     * @param string $days Número de dias (padrão: 7).
     * @return string Resposta formatada.
     */
    private function cmd_metrics( $days ) {
        $days = ! empty( $days ) && is_numeric( $days ) ? intval( $days ) : 7;
        $days = max( 1, min( $days, 365 ) ); // Entre 1 e 365 dias

        global $wpdb;
        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;
        $msg_table  = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;
        $metrics_table = $wpdb->prefix . DPS_AI_Analytics::TABLE_NAME;

        $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $date_to   = gmdate( 'Y-m-d' );

        // Conversas e mensagens
        $conv_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$conv_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        $msg_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$msg_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        // Tokens
        $tokens_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(tokens_input + tokens_output) as total FROM {$metrics_table} WHERE date BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        $total_tokens = $tokens_data ? intval( $tokens_data->total ) : 0;

        $response = sprintf( __( '📊 **Métricas dos últimos %d dias** (%s a %s):', 'dps-ai' ), $days, $date_from, $date_to ) . "\n\n";
        $response .= sprintf( __( '💬 Total de Conversas: %s', 'dps-ai' ), number_format_i18n( $conv_count ) ) . "\n";
        $response .= sprintf( __( '✉️ Total de Mensagens: %s', 'dps-ai' ), number_format_i18n( $msg_count ) ) . "\n";
        $response .= sprintf( __( '🔢 Total de Tokens: %s', 'dps-ai' ), number_format_i18n( $total_tokens ) ) . "\n";

        if ( $conv_count > 0 ) {
            $avg_msg = $msg_count / $conv_count;
            $response .= sprintf( __( '📈 Média msgs/conversa: %.1f', 'dps-ai' ), $avg_msg ) . "\n";
        }

        return $response;
    }

    /**
     * Comando: Listar conversas de um canal.
     *
     * @param string $channel Nome do canal (web_chat, portal, whatsapp, admin_specialist).
     * @return string Resposta formatada.
     */
    private function cmd_conversations( $channel ) {
        $channel = strtolower( trim( $channel ) );

        if ( empty( $channel ) ) {
            return __( '❌ Especifique o canal. Exemplo: /conversas whatsapp', 'dps-ai' );
        }

        if ( ! in_array( $channel, DPS_AI_Conversations_Repository::VALID_CHANNELS, true ) ) {
            return sprintf(
                __( '❌ Canal inválido "%s". Use: web_chat, portal, whatsapp ou admin_specialist', 'dps-ai' ),
                esc_html( $channel )
            );
        }

        $repo = DPS_AI_Conversations_Repository::get_instance();
        $conversations = $repo->list_conversations( [
            'channel' => $channel,
            'per_page' => 10,
        ] );

        if ( empty( $conversations ) ) {
            return sprintf( __( '❌ Nenhuma conversa encontrada no canal "%s".', 'dps-ai' ), $channel );
        }

        $response = sprintf( __( '✅ Últimas 10 conversas no canal "%s":', 'dps-ai' ), $channel ) . "\n\n";

        foreach ( $conversations as $conv ) {
            $messages = $repo->get_messages( $conv->id, 100 );
            $msg_count = count( $messages );

            $client_label = $conv->customer_id > 0 ? sprintf( 'Cliente #%d', $conv->customer_id ) : __( 'Visitante', 'dps-ai' );

            $response .= sprintf(
                "💬 **Conversa #%d** - %s\n- Status: %s\n- Iniciada: %s\n- Última atividade: %s\n- Mensagens: %d\n\n",
                $conv->id,
                $client_label,
                $conv->status,
                $conv->started_at,
                $conv->last_activity_at,
                $msg_count
            );
        }

        return $response;
    }

    /**
     * Processa consulta natural (não-comando) usando IA.
     *
     * @param string $query Consulta em linguagem natural.
     * @return string Resposta da IA.
     */
    private function process_natural_query( $query ) {
        // System prompt especializado para modo especialista
        $system_prompt = $this->get_specialist_system_prompt();

        // Buscar contexto relevante (últimas métricas)
        $context = $this->get_context_for_query();

        $ai_client = DPS_AI_Client::get_instance();

        $messages = [
            [ 'role' => 'system', 'content' => $system_prompt ],
            [ 'role' => 'system', 'content' => "Contexto atual do sistema:\n" . $context ],
            [ 'role' => 'user', 'content' => $query ],
        ];

        try {
            $response = $ai_client->chat( $messages, [ 'max_tokens' => 1000 ] );

            // Criar conversa no histórico
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $admin_id = get_current_user_id();

            $conversation_id = $repo->create_conversation( [
                'customer_id' => $admin_id,
                'channel' => 'admin_specialist',
                'session_identifier' => 'admin_' . $admin_id,
            ] );

            // Registrar mensagem do admin
            $repo->add_message( $conversation_id, [
                'sender_type' => 'user',
                'sender_identifier' => 'admin_' . $admin_id,
                'message_text' => $query,
            ] );

            // Registrar resposta da IA
            $repo->add_message( $conversation_id, [
                'sender_type' => 'assistant',
                'sender_identifier' => 'ai_specialist',
                'message_text' => $response['answer'],
                'message_metadata' => wp_json_encode( [
                    'model' => $response['model'] ?? '',
                    'tokens' => $response['tokens'] ?? 0,
                ] ),
            ] );

            return $response['answer'];

        } catch ( Exception $e ) {
            return sprintf(
                __( '❌ Erro ao processar consulta: %s', 'dps-ai' ),
                esc_html( $e->getMessage() )
            );
        }
    }

    /**
     * Retorna system prompt especializado para modo especialista.
     *
     * @return string
     */
    private function get_specialist_system_prompt() {
        return "Você é um assistente técnico especializado para a equipe interna do desi.pet by PRObst.

Seu propósito é ajudar administradores com:
- Análise de dados e métricas do sistema
- Identificação de padrões e insights
- Suporte técnico em questões complexas
- Interpretação de histórico de conversas
- Sugestões de melhorias operacionais

Características importantes:
- Use tom técnico e profissional (não coloquial)
- Seja direto e objetivo nas respostas
- Forneça dados concretos quando disponível
- Sugira ações práticas quando relevante
- Não hesite em pedir esclarecimentos se a pergunta for ambígua

Você tem acesso a dados internos do sistema através do contexto fornecido.
Se precisar de dados específicos que não estão no contexto, sugira ao usuário usar os comandos especiais:
- /buscar_cliente [nome]
- /historico [cliente_id]
- /metricas [dias]
- /conversas [canal]";
    }

    /**
     * Busca contexto relevante para consultas da IA.
     *
     * @return string
     */
    private function get_context_for_query() {
        global $wpdb;

        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;
        $msg_table  = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;

        // Últimos 7 dias
        $date_from = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
        $date_to   = gmdate( 'Y-m-d' );

        $conv_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$conv_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        $msg_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$msg_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        // Stats por canal
        $channel_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(*) as count FROM {$conv_table} 
             WHERE DATE(created_at) BETWEEN %s AND %s 
             GROUP BY channel",
            $date_from,
            $date_to
        ), ARRAY_A );

        $context = "Métricas dos últimos 7 dias:\n";
        $context .= "- Total de conversas: " . $conv_count . "\n";
        $context .= "- Total de mensagens: " . $msg_count . "\n";
        $context .= "- Conversas por canal:\n";

        foreach ( $channel_stats as $stat ) {
            $context .= "  • " . $stat['channel'] . ": " . $stat['count'] . "\n";
        }

        return $context;
    }
}
