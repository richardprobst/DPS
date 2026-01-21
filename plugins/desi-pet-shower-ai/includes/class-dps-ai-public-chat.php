<?php
/**
 * Chat público de IA para visitantes do site.
 *
 * Este chat é diferente do chat do Portal do Cliente:
 * - Não requer autenticação (visitantes podem usar)
 * - Foco em informações sobre serviços, preços e funcionamento
 * - Não acessa dados pessoais de clientes
 * - Inclui rate limiting para proteção contra abuso
 *
 * MODO ADMINISTRADOR (v1.8.0):
 * - Administradores logados recebem acesso expandido a informações do sistema
 * - Contexto inclui estatísticas, dados de clientes e informações sensíveis
 * - Indicador visual de "Modo Administrador" no chat
 * - Rate limiting relaxado para administradores
 *
 * SEGURANÇA:
 * - Visitantes NUNCA recebem dados de clientes, financeiros ou sensíveis
 * - Validação de capability no backend para determinar modo
 * - Logs de auditoria para requisições administrativas
 *
 * @package DPS_AI_Addon
 * @since 1.6.0
 * @updated 1.8.0 - Adicionado modo administrador com acesso expandido
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Chat Público de IA.
 *
 * Implementa um chat por IA aberto para visitantes do site,
 * permitindo que tirem dúvidas sobre serviços de Banho e Tosa.
 */
class DPS_AI_Public_Chat {

    /**
     * Slug do shortcode.
     *
     * @var string
     */
    const SHORTCODE = 'dps_ai_public_chat';

    /**
     * Limite de requisições por minuto por IP.
     *
     * @var int
     */
    const RATE_LIMIT_PER_MINUTE = 10;

    /**
     * Limite de requisições por hora por IP.
     *
     * @var int
     */
    const RATE_LIMIT_PER_HOUR = 60;

    /**
     * Limite de requisições por minuto para administradores (mais alto).
     *
     * @var int
     */
    const ADMIN_RATE_LIMIT_PER_MINUTE = 30;

    /**
     * Limite de requisições por hora para administradores (mais alto).
     *
     * @var int
     */
    const ADMIN_RATE_LIMIT_PER_HOUR = 200;

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Public_Chat|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Public_Chat
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
        // Registra shortcode diretamente (a classe é instanciada no hook 'init' prioridade 21)
        // Como 'init' já está executando, não faz sentido registrar outro action para 'init'
        add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );

        // Handler AJAX para visitantes (nopriv) e usuários logados
        add_action( 'wp_ajax_dps_ai_public_ask', [ $this, 'handle_ajax_ask' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_public_ask', [ $this, 'handle_ajax_ask' ] );

        // Handler para feedback
        add_action( 'wp_ajax_dps_ai_public_feedback', [ $this, 'handle_ajax_feedback' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_public_feedback', [ $this, 'handle_ajax_feedback' ] );

        // Registra assets
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    }

    /**
     * Renderiza o shortcode do chat público.
     *
     * @param array $atts Atributos do shortcode.
     *
     * @return string HTML do chat.
     */
    public function render_shortcode( $atts ) {
        // Desabilita cache da página para garantir dados sempre atualizados
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        // Verifica se a IA está habilitada
        $settings = get_option( 'dps_ai_settings', [] );
        if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
            return '<!-- DPS AI: Assistente não configurado -->';
        }

        // Verifica se o chat público está habilitado
        if ( empty( $settings['public_chat_enabled'] ) ) {
            return '<!-- DPS AI: Chat público não habilitado -->';
        }

        // Parse de atributos
        $atts = shortcode_atts(
            [
                'title'          => __( 'Tire suas dúvidas', 'dps-ai' ),
                'subtitle'       => __( 'Nosso assistente virtual pode ajudar com informações sobre serviços de Banho e Tosa.', 'dps-ai' ),
                'placeholder'    => __( 'Digite sua pergunta sobre nossos serviços...', 'dps-ai' ),
                'mode'           => 'inline', // inline ou floating
                'position'       => 'bottom-right', // bottom-right ou bottom-left
                'theme'          => 'light', // light ou dark
                'primary_color'  => '', // cor customizada (hex)
                'show_faqs'      => 'true',
            ],
            $atts,
            self::SHORTCODE
        );

        // Configurações do widget
        $widget_mode = sanitize_text_field( $atts['mode'] );
        $position    = sanitize_text_field( $atts['position'] );
        $theme       = sanitize_text_field( $atts['theme'] );

        // Verifica se o usuário atual é administrador (modo expandido)
        $is_admin_mode = $this->is_admin_user();

        // Obtém FAQs para o chat público (ou FAQs admin)
        $faqs = $is_admin_mode ? $this->get_admin_faqs() : $this->get_public_faqs();

        // Gera nonce para AJAX
        $nonce = wp_create_nonce( 'dps_ai_public_ask' );

        // Inicia output buffer
        ob_start();
        ?>
        <?php
        // Valida cor primária customizada
        $primary_color = '';
        if ( ! empty( $atts['primary_color'] ) ) {
            $validated_color = sanitize_hex_color( $atts['primary_color'] );
            if ( $validated_color ) {
                $primary_color = $validated_color;
            }
        }
        ?>
        <div 
            id="dps-ai-public-chat" 
            class="dps-ai-public-chat dps-ai-public-chat--<?php echo esc_attr( $widget_mode ); ?> dps-ai-public-chat--<?php echo esc_attr( $theme ); ?> <?php echo 'floating' === $widget_mode ? 'dps-ai-public-chat--' . esc_attr( $position ) : ''; ?><?php echo $is_admin_mode ? ' dps-ai-public-chat--admin-mode' : ''; ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-admin-mode="<?php echo esc_attr( $is_admin_mode ? 'true' : 'false' ); ?>"
            <?php if ( $primary_color ) : ?>
                style="--dps-ai-primary: <?php echo esc_attr( $primary_color ); ?>;"
            <?php endif; ?>
        >
            <?php if ( 'floating' === $widget_mode ) : ?>
                <!-- Botão flutuante -->
                <button id="dps-ai-public-fab" class="dps-ai-public-fab" aria-label="<?php esc_attr_e( 'Abrir chat', 'dps-ai' ); ?>">
                    <span class="dps-ai-public-fab-icon">💬</span>
                    <span class="dps-ai-public-fab-close">✕</span>
                </button>
            <?php endif; ?>

            <div class="dps-ai-public-panel <?php echo 'floating' === $widget_mode ? 'dps-ai-public-panel--floating' : ''; ?>">
                <!-- Cabeçalho -->
                <div class="dps-ai-public-header">
                    <div class="dps-ai-public-header-content">
                        <h3 class="dps-ai-public-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                        <p class="dps-ai-public-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
                    </div>
                    <div class="dps-ai-public-header-right">
                        <?php if ( $is_admin_mode ) : ?>
                            <div class="dps-ai-public-admin-badge" title="<?php esc_attr_e( 'Você está no modo administrador com acesso expandido ao sistema', 'dps-ai' ); ?>">
                                <span class="dps-ai-public-admin-icon">🔐</span>
                                <span class="dps-ai-public-admin-text"><?php esc_html_e( 'Admin', 'dps-ai' ); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="dps-ai-public-status">
                            <span class="dps-ai-public-status-dot"></span>
                            <span class="dps-ai-public-status-text"><?php esc_html_e( 'Online', 'dps-ai' ); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Toolbar do chat -->
                <div class="dps-ai-public-toolbar">
                    <div class="dps-ai-public-toolbar-left">
                        <span class="dps-ai-public-msg-count">1 msg</span>
                        <?php if ( $is_admin_mode ) : ?>
                            <span class="dps-ai-public-mode-indicator" title="<?php esc_attr_e( 'Acesso a dados do sistema', 'dps-ai' ); ?>">
                                <?php esc_html_e( '• Modo Sistema', 'dps-ai' ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="dps-ai-public-toolbar-right">
                        <button type="button" class="dps-ai-public-clear-btn" title="<?php esc_attr_e( 'Limpar conversa', 'dps-ai' ); ?>">
                            <span>🗑️</span> <?php esc_html_e( 'Limpar', 'dps-ai' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Área de chat -->
                <div class="dps-ai-public-body">
                    <?php if ( 'true' === $atts['show_faqs'] && ! empty( $faqs ) ) : ?>
                        <!-- FAQs sugeridas -->
                        <div class="dps-ai-public-faqs">
                            <p class="dps-ai-public-faqs-label"><?php echo $is_admin_mode ? esc_html__( 'Consultas do sistema:', 'dps-ai' ) : esc_html__( 'Perguntas frequentes:', 'dps-ai' ); ?></p>
                            <div class="dps-ai-public-faqs-list">
                                <?php foreach ( $faqs as $faq ) : ?>
                                    <button type="button" class="dps-ai-public-faq-btn" data-question="<?php echo esc_attr( $faq ); ?>">
                                        <?php echo esc_html( $faq ); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Container de mensagens -->
                    <div id="dps-ai-public-messages" class="dps-ai-public-messages">
                        <!-- Mensagem de boas-vindas -->
                        <div class="dps-ai-public-message dps-ai-public-message--assistant">
                            <div class="dps-ai-public-message-avatar"><?php echo $is_admin_mode ? '🔐' : '🐾'; ?></div>
                            <div class="dps-ai-public-message-content">
                                <div class="dps-ai-public-message-text">
                                    <?php if ( $is_admin_mode ) : ?>
                                        <p><?php esc_html_e( 'Olá, Administrador! 👋 Você está no Modo Sistema com acesso expandido. Posso ajudar com:', 'dps-ai' ); ?></p>
                                        <ul>
                                            <li><?php esc_html_e( '📊 Estatísticas e métricas do sistema', 'dps-ai' ); ?></li>
                                            <li><?php esc_html_e( '👥 Consultas sobre clientes e pets', 'dps-ai' ); ?></li>
                                            <li><?php esc_html_e( '📅 Agendamentos e histórico', 'dps-ai' ); ?></li>
                                            <li><?php esc_html_e( '💰 Informações financeiras e faturamento', 'dps-ai' ); ?></li>
                                            <li><?php esc_html_e( '⚙️ Configurações e status do sistema', 'dps-ai' ); ?></li>
                                        </ul>
                                        <p><?php esc_html_e( 'Como posso ajudar você hoje?', 'dps-ai' ); ?></p>
                                    <?php else : ?>
                                        <p><?php esc_html_e( 'Olá! 👋 Sou o assistente virtual do pet shop. Posso ajudar com informações sobre nossos serviços de Banho e Tosa, preços, horários e muito mais. Como posso ajudar você hoje?', 'dps-ai' ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Indicador de digitação -->
                    <div id="dps-ai-public-typing" class="dps-ai-public-typing" style="display: none;">
                        <div class="dps-ai-public-typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="dps-ai-public-typing-text"><?php esc_html_e( 'Digitando...', 'dps-ai' ); ?></span>
                    </div>
                </div>

                <!-- Área de input -->
                <div class="dps-ai-public-footer">
                    <div class="dps-ai-public-input-wrapper">
                        <textarea
                            id="dps-ai-public-input"
                            class="dps-ai-public-input"
                            placeholder="<?php echo $is_admin_mode ? esc_attr__( 'Pergunte sobre clientes, agendamentos, finanças...', 'dps-ai' ) : esc_attr( $atts['placeholder'] ); ?>"
                            rows="1"
                            maxlength="<?php echo $is_admin_mode ? '1000' : '500'; ?>"
                        ></textarea>
                        <button id="dps-ai-public-voice" class="dps-ai-public-voice" aria-label="<?php esc_attr_e( 'Usar entrada por voz', 'dps-ai' ); ?>" title="<?php esc_attr_e( 'Falar ao invés de digitar', 'dps-ai' ); ?>" style="display: none;">
                            <span class="dps-ai-public-voice-icon">🎤</span>
                        </button>
                        <button id="dps-ai-public-submit" class="dps-ai-public-submit" aria-label="<?php esc_attr_e( 'Enviar pergunta', 'dps-ai' ); ?>">
                            <span class="dps-ai-public-submit-icon">➤</span>
                        </button>
                    </div>
                    <p class="dps-ai-public-disclaimer">
                        <?php if ( $is_admin_mode ) : ?>
                            <?php esc_html_e( '🔐 Modo Administrador: dados sensíveis do sistema estão disponíveis nesta sessão.', 'dps-ai' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Este é um assistente virtual. Para informações mais detalhadas, entre em contato conosco.', 'dps-ai' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handler AJAX para perguntas do chat público.
     */
    public function handle_ajax_ask() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_public_ask' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança. Recarregue a página e tente novamente.', 'dps-ai' ),
            ] );
        }

        // Verifica se o chat público está habilitado
        $settings = get_option( 'dps_ai_settings', [] );
        if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) || empty( $settings['public_chat_enabled'] ) ) {
            wp_send_json_error( [
                'message' => __( 'O chat não está disponível no momento.', 'dps-ai' ),
            ] );
        }

        // Verifica se é modo administrador (validação no backend - CRÍTICO para segurança)
        $is_admin_mode = $this->is_admin_user();

        // Rate limiting por IP (limites mais altos para admins)
        $ip_address = $this->get_client_ip();
        if ( ! $this->check_rate_limit( $ip_address, $is_admin_mode ) ) {
            wp_send_json_error( [
                'message'    => __( 'Você atingiu o limite de perguntas. Por favor, aguarde alguns minutos antes de tentar novamente.', 'dps-ai' ),
                'error_type' => 'rate_limit', // Permite ao frontend diferenciar tipo de erro
            ] );
        }

        // Obtém e valida a pergunta
        $question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
        if ( empty( $question ) ) {
            wp_send_json_error( [
                'message' => __( 'Por favor, digite uma pergunta.', 'dps-ai' ),
            ] );
        }

        // Limita tamanho da pergunta (maior para admins)
        $max_length = $is_admin_mode ? 1000 : 500;
        if ( mb_strlen( $question ) > $max_length ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %d: número máximo de caracteres */
                    __( 'Pergunta muito longa. Por favor, resuma em até %d caracteres.', 'dps-ai' ),
                    $max_length
                ),
            ] );
        }

        // Registra a requisição no rate limiting
        $this->record_request( $ip_address );

        // Log de auditoria para requisições admin
        if ( $is_admin_mode ) {
            $current_user = wp_get_current_user();
            dps_ai_log( sprintf(
                'Admin mode request from user %s (ID: %d): %s',
                $current_user->user_login,
                $current_user->ID,
                mb_substr( $question, 0, 100 )
            ), 'info' );
        }

        // Obtém ou cria conversa para este visitante
        $conversation_id = $this->get_or_create_public_conversation( $ip_address );

        // Salva mensagem do usuário
        if ( $conversation_id && class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'user',
                'sender_identifier' => $is_admin_mode ? 'admin_' . get_current_user_id() : $ip_address,
                'message_text'      => $question,
                'metadata'          => $is_admin_mode ? [ 'admin_mode' => true ] : [],
            ] );
        }

        // Obtém resposta da IA (passa flag de admin mode)
        $start_time = microtime( true );
        $answer     = $this->get_ai_response( $question, $is_admin_mode );
        $end_time   = microtime( true );

        // Se falhou, verifica o tipo de erro
        if ( null === $answer ) {
            $last_error = DPS_AI_Client::get_last_error();
            
            // Diferencia rate limit de outros erros
            if ( $last_error && 'rate_limit' === $last_error['type'] ) {
                wp_send_json_error( [
                    'message'    => __( 'Muitas solicitações em sequência. Aguarde alguns segundos antes de tentar novamente.', 'dps-ai' ),
                    'error_type' => 'rate_limit',
                ] );
            }
            
            // Erro genérico para outros casos
            wp_send_json_error( [
                'message'    => __( 'Desculpe, não consegui processar sua pergunta no momento. Por favor, tente novamente ou entre em contato conosco diretamente.', 'dps-ai' ),
                'error_type' => 'generic',
            ] );
        }

        // Salva resposta da IA
        if ( $conversation_id && class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'assistant',
                'sender_identifier' => 'ai',
                'message_text'      => $answer,
                'metadata'          => [
                    'response_time_ms' => round( ( $end_time - $start_time ) * 1000 ),
                    'ip_address'       => $ip_address,
                ],
            ] );
        }

        // Registra métricas se analytics estiver habilitado
        if ( class_exists( 'DPS_AI_Analytics' ) && ! empty( $settings['enable_analytics'] ) ) {
            DPS_AI_Analytics::record_public_chat_interaction(
                $question,
                $answer,
                round( ( $end_time - $start_time ) * 1000 ),
                $ip_address
            );
        }

        // Gera ID único para a mensagem (para feedback)
        $message_id = wp_generate_uuid4();

        wp_send_json_success( [
            'answer'     => $answer,
            'message_id' => $message_id,
        ] );
    }

    /**
     * Obtém ou cria conversa ativa para visitante não logado.
     *
     * @param string $ip_address IP do visitante.
     *
     * @return int|false ID da conversa ou false em caso de erro.
     */
    private function get_or_create_public_conversation( $ip_address ) {
        if ( ! class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            return false;
        }

        $repo = DPS_AI_Conversations_Repository::get_instance();

        // Usa hash seguro do IP como identificador de sessão
        $session_id = 'public_' . wp_hash( $ip_address, 'nonce' );

        // Busca conversa aberta recente (últimas 2 horas)
        $conversation = $repo->get_active_conversation_by_session( $session_id, 'web_chat' );

        if ( $conversation ) {
            // Se a última atividade foi há menos de 2 horas, reutiliza
            $last_activity = strtotime( $conversation->last_activity_at );
            if ( ( current_time( 'timestamp' ) - $last_activity ) < ( 2 * HOUR_IN_SECONDS ) ) {
                return (int) $conversation->id;
            }
        }

        // Cria nova conversa
        $conversation_id = $repo->create_conversation( [
            'customer_id'        => null, // Visitante não identificado
            'channel'            => 'web_chat',
            'session_identifier' => $session_id,
            'status'             => 'open',
        ] );

        return $conversation_id;
    }

    /**
     * Handler AJAX para feedback.
     */
    public function handle_ajax_feedback() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_public_ask' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
            ] );
        }

        $message_id = isset( $_POST['message_id'] ) ? sanitize_text_field( wp_unslash( $_POST['message_id'] ) ) : '';
        $feedback   = isset( $_POST['feedback'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback'] ) ) : '';
        $question   = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';

        if ( empty( $message_id ) || ! in_array( $feedback, [ 'positive', 'negative' ], true ) ) {
            wp_send_json_error( [
                'message' => __( 'Dados inválidos.', 'dps-ai' ),
            ] );
        }

        // Registra feedback
        if ( class_exists( 'DPS_AI_Analytics' ) ) {
            DPS_AI_Analytics::record_feedback(
                0, // client_id = 0 para visitantes
                $question,
                $feedback
            );
        }

        wp_send_json_success( [
            'message' => __( 'Obrigado pelo feedback!', 'dps-ai' ),
        ] );
    }

    /**
     * Obtém resposta da IA para o chat público.
     *
     * @param string $question      Pergunta do visitante.
     * @param bool   $is_admin_mode Se está no modo administrador (acesso expandido).
     *
     * @return string|null Resposta da IA ou null em caso de erro.
     */
    private function get_ai_response( $question, $is_admin_mode = false ) {
        // SEGURANÇA: Para visitantes, verifica se a pergunta está no contexto permitido
        // Administradores podem perguntar qualquer coisa sobre o sistema
        if ( ! $is_admin_mode && ! $this->is_question_in_context( $question ) ) {
            return __( 'Sou um assistente focado em ajudar com informações sobre serviços de Banho e Tosa para pets. Posso ajudar com dúvidas sobre preços, horários, serviços oferecidos, cuidados com seu pet e muito mais. Como posso ajudar você?', 'dps-ai' );
        }

        // Monta o contexto do negócio (para visitantes) ou contexto do sistema (para admins)
        if ( $is_admin_mode ) {
            $business_context = $this->get_admin_system_context();
        } else {
            $business_context = $this->get_business_context();
        }

        // Busca artigos relevantes da base de conhecimento
        $kb_context = '';
        if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
            $relevant_articles = DPS_AI_Knowledge_Base::get_relevant_articles( $question, 5 );
            $kb_context = DPS_AI_Knowledge_Base::format_articles_for_context( $relevant_articles );
        }

        // Array de mensagens
        $messages = [];

        // Obtém configurações incluindo idioma
        $settings = get_option( 'dps_ai_settings', [] );
        $language = ! empty( $settings['language'] ) ? $settings['language'] : 'pt_BR';

        // 1. System prompt específico (diferente para admin e visitante)
        if ( $is_admin_mode ) {
            $messages[] = [
                'role'    => 'system',
                'content' => $this->get_admin_system_prompt_with_language( $language ),
            ];
        } else {
            $messages[] = [
                'role'    => 'system',
                'content' => $this->get_public_system_prompt_with_language( $language ),
            ];
        }

        // 2. Contexto do negócio/sistema (se disponível)
        if ( ! empty( $business_context ) ) {
            $messages[] = [
                'role'    => 'system',
                'content' => $business_context,
            ];
        }

        // 3. Instruções adicionais do administrador (apenas para visitantes)
        if ( ! $is_admin_mode ) {
            $extra_instructions = ! empty( $settings['public_chat_instructions'] ) ? trim( $settings['public_chat_instructions'] ) : '';
            if ( ! empty( $extra_instructions ) ) {
                $messages[] = [
                    'role'    => 'system',
                    'content' => 'Instruções adicionais do administrador: ' . $extra_instructions,
                ];
            }
        }

        // 4. Pergunta do visitante/admin com contexto da base de conhecimento
        $user_content = $question;
        if ( ! empty( $kb_context ) ) {
            $label = $is_admin_mode ? 'Pergunta do administrador: ' : 'Pergunta do visitante: ';
            $user_content = $kb_context . "\n\n" . $label . $question;
        }
        
        $messages[] = [
            'role'    => 'user',
            'content' => $user_content,
        ];

        // Chama a API
        return DPS_AI_Client::chat( $messages );
    }

    /**
     * Retorna o system prompt específico para o chat público.
     *
     * IMPORTANTE: Agora utiliza DPS_AI_Prompts::get() para carregar o prompt
     * de arquivo e aplicar filtros, permitindo customização.
     *
     * @return string
     */
    private function get_public_system_prompt() {
        // Usa a nova classe centralizada de prompts
        return DPS_AI_Prompts::get( 'public' );
    }

    /**
     * Retorna o system prompt específico para o chat público com instrução de idioma.
     * 
     * Adiciona instrução explícita para que a IA responda no idioma configurado.
     *
     * @param string $language Código do idioma (pt_BR, en_US, es_ES, auto).
     * 
     * @return string Conteúdo do prompt com instrução de idioma.
     */
    private function get_public_system_prompt_with_language( $language = 'pt_BR' ) {
        $base_prompt = $this->get_public_system_prompt();
        
        // Mapeia códigos de idioma para instruções claras
        $language_instructions = [
            'pt_BR' => 'IMPORTANTE: Você DEVE responder SEMPRE em Português do Brasil, mesmo que os artigos da base de conhecimento estejam em outro idioma. Adapte e traduza o conteúdo conforme necessário.',
            'en_US' => 'IMPORTANT: You MUST ALWAYS respond in English (US), even if the knowledge base articles are in another language. Adapt and translate the content as needed.',
            'es_ES' => 'IMPORTANTE: Usted DEBE responder SIEMPRE en Español, incluso si los artículos de la base de conocimiento están en otro idioma. Adapte y traduzca el contenido según sea necesario.',
            'auto'  => 'IMPORTANTE: Detecte automaticamente o idioma da pergunta do usuário e responda no mesmo idioma. Se artigos da base de conhecimento estiverem em outro idioma, traduza e adapte o conteúdo.',
        ];
        
        $instruction = isset( $language_instructions[ $language ] ) 
            ? $language_instructions[ $language ] 
            : $language_instructions['pt_BR'];
        
        return $base_prompt . "\n\n" . $instruction;
    }

    /**
     * Obtém contexto do negócio para a IA.
     *
     * Busca informações da base de conhecimento e configurações.
     *
     * @return string
     */
    private function get_business_context() {
        $context = '';

        // Obtém artigos da base de conhecimento relevantes para informações gerais
        if ( class_exists( 'DPS_AI_Knowledge_Base' ) ) {
            // Busca artigos marcados como públicos
            $articles = get_posts( [
                'post_type'      => DPS_AI_Knowledge_Base::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'meta_query'     => [
                    [
                        'key'     => '_dps_ai_active',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_dps_ai_public',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_dps_ai_priority',
                'order'          => 'DESC',
            ] );

            if ( ! empty( $articles ) ) {
                $context .= "INFORMAÇÕES DO NEGÓCIO:\n";
                foreach ( $articles as $article ) {
                    $context .= "\n" . $article->post_title . ":\n";
                    $context .= wp_strip_all_tags( $article->post_content ) . "\n";
                }
            }
        }

        // Obtém informações de serviços (se Services Add-on estiver ativo)
        if ( class_exists( 'DPS_Services_API' ) && method_exists( 'DPS_Services_API', 'get_public_services' ) ) {
            $services = DPS_Services_API::get_public_services();
            if ( ! empty( $services ) ) {
                $context .= "\nSERVIÇOS DISPONÍVEIS:\n";
                foreach ( $services as $service ) {
                    $name  = $service['title'] ?? '';
                    $price = isset( $service['price'] ) ? 'R$ ' . number_format( $service['price'], 2, ',', '.' ) : '';
                    if ( $name ) {
                        $context .= "- {$name}" . ( $price ? " (a partir de {$price})" : '' ) . "\n";
                    }
                }
            }
        }

        // Configurações adicionais do negócio
        $settings = get_option( 'dps_ai_settings', [] );
        if ( ! empty( $settings['public_chat_business_info'] ) ) {
            $context .= "\nINFORMAÇÕES ADICIONAIS:\n" . $settings['public_chat_business_info'] . "\n";
        }

        return $context;
    }

    /**
     * Verifica se a pergunta está no contexto permitido.
     *
     * @param string $question Pergunta do visitante.
     *
     * @return bool
     */
    private function is_question_in_context( $question ) {
        $keywords = [
            'pet', 'pets', 'cachorro', 'cao', 'cão', 'cães', 'gato', 'gatos', 'animal', 'animais',
            'banho', 'tosa', 'grooming', 'tosador', 'tosadora',
            'preço', 'preco', 'precos', 'preços', 'valor', 'valores', 'quanto', 'custa', 'custo',
            'horário', 'horario', 'horarios', 'horários', 'hora', 'funciona', 'funcionamento', 'abre', 'fecha',
            'servico', 'serviço', 'servicos', 'serviços',
            'agendar', 'agendamento', 'agenda', 'marcar', 'reservar',
            'local', 'localização', 'localizacao', 'endereço', 'endereco', 'onde', 'fica',
            'contato', 'telefone', 'whatsapp', 'email', 'e-mail',
            'fidelidade', 'pontos', 'desconto', 'promoção', 'promocao', 'pacote', 'assinatura',
            'pagamento', 'pagar', 'cartão', 'cartao', 'pix', 'dinheiro',
            'vacina', 'vacinas', 'vacinação', 'vacinacao',
            'raca', 'raça', 'porte', 'tamanho', 'pelagem', 'pelo',
            'higiene', 'limpeza', 'cuidado', 'cuidados', 'saude', 'saúde',
            'oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'tudo bem', 'obrigado', 'obrigada',
        ];

        // Cast para string para compatibilidade com PHP 8.1+
        $question_lower = mb_strtolower( (string) $question, 'UTF-8' );

        foreach ( $keywords as $keyword ) {
            if ( false !== mb_strpos( $question_lower, $keyword ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém FAQs para o chat público.
     *
     * @return array
     */
    private function get_public_faqs() {
        $settings    = get_option( 'dps_ai_settings', [] );
        $custom_faqs = ! empty( $settings['public_chat_faqs'] ) ? $settings['public_chat_faqs'] : '';

        // FAQs padrão para visitantes
        $default_faqs = [
            __( 'Quanto custa um banho?', 'dps-ai' ),
            __( 'Qual o horário de funcionamento?', 'dps-ai' ),
            __( 'Como agendar um serviço?', 'dps-ai' ),
            __( 'Vocês fazem tosa para gatos?', 'dps-ai' ),
        ];

        // Se há FAQs customizados, usa eles
        if ( ! empty( $custom_faqs ) ) {
            $custom_array = array_filter( array_map( 'trim', explode( "\n", $custom_faqs ) ) );
            if ( ! empty( $custom_array ) ) {
                return array_slice( $custom_array, 0, 5 );
            }
        }

        return $default_faqs;
    }

    /**
     * Obtém o IP do cliente de forma segura.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        // Ordem de prioridade para obter IP
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy/Load balancer
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR',           // Conexão direta
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // Se houver múltiplos IPs (X-Forwarded-For), pega o primeiro
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }
                break;
            }
        }

        // Valida IP - usa hash único baseado na sessão se IP inválido
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            // Gera um identificador único baseado em características do navegador
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
            $accept_lang = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
            $ip = 'unknown_' . substr( md5( $user_agent . $accept_lang . gmdate( 'Y-m-d' ) ), 0, 16 );
        }

        return $ip;
    }

    /**
     * Verifica rate limiting para um IP.
     *
     * @param string $ip_address    Endereço IP.
     * @param bool   $is_admin_mode Se está no modo administrador (limites mais altos).
     *
     * @return bool True se dentro do limite, false se excedido.
     */
    private function check_rate_limit( $ip_address, $is_admin_mode = false ) {
        $ip_hash = md5( $ip_address );

        // Define limites baseado no modo
        $limit_per_minute = $is_admin_mode ? self::ADMIN_RATE_LIMIT_PER_MINUTE : self::RATE_LIMIT_PER_MINUTE;
        $limit_per_hour   = $is_admin_mode ? self::ADMIN_RATE_LIMIT_PER_HOUR : self::RATE_LIMIT_PER_HOUR;

        // Verifica limite por minuto
        $minute_key   = 'dps_ai_rl_m_' . $ip_hash;
        $minute_count = (int) get_transient( $minute_key );
        if ( $minute_count >= $limit_per_minute ) {
            return false;
        }

        // Verifica limite por hora
        $hour_key   = 'dps_ai_rl_h_' . $ip_hash;
        $hour_count = (int) get_transient( $hour_key );
        if ( $hour_count >= $limit_per_hour ) {
            return false;
        }

        return true;
    }

    /**
     * Registra uma requisição para rate limiting.
     *
     * @param string $ip_address Endereço IP.
     */
    private function record_request( $ip_address ) {
        $ip_hash = md5( $ip_address );

        // Incrementa contador por minuto
        $minute_key   = 'dps_ai_rl_m_' . $ip_hash;
        $minute_count = (int) get_transient( $minute_key );
        set_transient( $minute_key, $minute_count + 1, MINUTE_IN_SECONDS );

        // Incrementa contador por hora
        $hour_key   = 'dps_ai_rl_h_' . $ip_hash;
        $hour_count = (int) get_transient( $hour_key );
        set_transient( $hour_key, $hour_count + 1, HOUR_IN_SECONDS );
    }

    /**
     * Carrega assets se o shortcode estiver na página.
     */
    public function maybe_enqueue_assets() {
        global $post;

        // Verifica se estamos em uma página com o shortcode
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        // Verifica se o shortcode está no conteúdo
        if ( ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
            return;
        }

        // Verifica se o chat público está habilitado
        $settings = get_option( 'dps_ai_settings', [] );
        if ( empty( $settings['enabled'] ) || empty( $settings['public_chat_enabled'] ) ) {
            return;
        }

        // Enfileira CSS
        wp_enqueue_style(
            'dps-ai-public-chat',
            DPS_AI_ADDON_URL . 'assets/css/dps-ai-public-chat.css',
            [],
            DPS_AI_VERSION
        );

        // Enfileira JavaScript
        wp_enqueue_script(
            'dps-ai-public-chat',
            DPS_AI_ADDON_URL . 'assets/js/dps-ai-public-chat.js',
            [ 'jquery' ],
            DPS_AI_VERSION,
            true
        );

        // Localiza script
        wp_localize_script( 'dps-ai-public-chat', 'dpsAIPublicChat', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'errorGeneric'        => __( 'Ocorreu um erro ao processar sua pergunta. Tente novamente.', 'dps-ai' ),
                'pleaseEnterQuestion' => __( 'Por favor, digite uma pergunta.', 'dps-ai' ),
                'sending'             => __( 'Enviando...', 'dps-ai' ),
                'feedbackThanks'      => __( 'Obrigado pelo feedback!', 'dps-ai' ),
                'wasHelpful'          => __( 'Esta resposta foi útil?', 'dps-ai' ),
                'copied'              => __( 'Copiado!', 'dps-ai' ),
                'copy'                => __( 'Copiar', 'dps-ai' ),
                'clearConfirm'        => __( 'Tem certeza que deseja limpar a conversa?', 'dps-ai' ),
                'messages'            => __( 'mensagens', 'dps-ai' ),
            ],
        ] );
    }

    /**
     * Verifica se o usuário atual é administrador (pode acessar modo expandido).
     *
     * SEGURANÇA: Esta verificação é feita no backend para garantir que não pode
     * ser burlada manipulando o frontend. Apenas usuários com capability
     * 'manage_options' são considerados administradores.
     *
     * @return bool True se o usuário é admin, false caso contrário.
     */
    private function is_admin_user() {
        // Apenas usuários logados com manage_options podem acessar modo admin
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    /**
     * Obtém FAQs específicas para administradores.
     *
     * @return array Lista de perguntas sugeridas para admins.
     */
    private function get_admin_faqs() {
        return [
            __( 'Quantos clientes temos cadastrados?', 'dps-ai' ),
            __( 'Quais foram os agendamentos de hoje?', 'dps-ai' ),
            __( 'Qual é o faturamento deste mês?', 'dps-ai' ),
            __( 'Quais clientes estão com pagamentos pendentes?', 'dps-ai' ),
            __( 'Mostre as estatísticas do sistema', 'dps-ai' ),
        ];
    }

    /**
     * Retorna o system prompt específico para modo administrador.
     *
     * Este prompt dá acesso expandido a informações do sistema,
     * incluindo dados de clientes, financeiros e operacionais.
     *
     * SEGURANÇA: Este prompt é usado APENAS quando is_admin_user() retorna true.
     *
     * @return string Conteúdo do prompt para modo admin.
     */
    private function get_admin_system_prompt() {
        return "Você é um assistente administrativo do sistema desi.pet by PRObst, um software de gestão para pet shops especializado em serviços de Banho e Tosa.

Você está em MODO ADMINISTRADOR com acesso expandido ao sistema. O usuário que está falando com você é um administrador verificado do sistema.

CAPACIDADES NO MODO ADMIN:
- Você pode acessar e fornecer informações sobre clientes cadastrados
- Você pode informar dados de agendamentos (passados, presentes e futuros)
- Você pode fornecer informações financeiras (faturamento, pagamentos pendentes, etc.)
- Você pode mostrar estatísticas e métricas do sistema
- Você pode detalhar configurações e status do sistema
- Você pode listar pets cadastrados e seus históricos

REGRAS DE COMPORTAMENTO:
- Seja objetivo e forneça dados concretos quando disponíveis
- Use formatação com listas e tabelas para organizar informações
- Se não tiver dados específicos no contexto, informe claramente
- Sempre identifique-se como assistente do sistema DPS
- Para ações que modifiquem dados, oriente o admin a usar o painel administrativo

CONTEXTO DO SISTEMA:
- Sistema: desi.pet by PRObst (DPS)
- Especialização: Gestão de Pet Shops com foco em Banho e Tosa
- Módulos: Agenda, Clientes, Pets, Financeiro, Assinaturas, Fidelidade, Comunicações";
    }

    /**
     * Retorna o system prompt de admin com instrução de idioma.
     *
     * @param string $language Código do idioma.
     * @return string Prompt com instrução de idioma.
     */
    private function get_admin_system_prompt_with_language( $language = 'pt_BR' ) {
        $base_prompt = $this->get_admin_system_prompt();
        
        $language_instructions = [
            'pt_BR' => 'IMPORTANTE: Responda SEMPRE em Português do Brasil.',
            'en_US' => 'IMPORTANT: ALWAYS respond in English (US).',
            'es_ES' => 'IMPORTANTE: Responda SIEMPRE en Español.',
            'auto'  => 'Responda no mesmo idioma da pergunta.',
        ];
        
        $instruction = isset( $language_instructions[ $language ] ) 
            ? $language_instructions[ $language ] 
            : $language_instructions['pt_BR'];
        
        return $base_prompt . "\n\n" . $instruction;
    }

    /**
     * Obtém contexto do sistema para administradores.
     *
     * Esta função coleta dados reais do sistema para fornecer ao assistente,
     * permitindo respostas precisas sobre o estado atual do negócio.
     *
     * SEGURANÇA: Só é chamada quando is_admin_user() retorna true.
     *
     * @return string Contexto do sistema formatado.
     */
    private function get_admin_system_context() {
        global $wpdb;
        
        $context = "DADOS DO SISTEMA (atualizados em " . current_time( 'd/m/Y H:i' ) . "):\n\n";

        // 1. Estatísticas de clientes (CPT dps_cliente)
        $client_counts = wp_count_posts( 'dps_cliente' );
        $total_clients = isset( $client_counts->publish ) ? (int) $client_counts->publish : 0;
        
        $context .= "📊 CLIENTES:\n";
        $context .= "- Total de clientes cadastrados: {$total_clients}\n";
        
        // Clientes ativos (com agendamento nos últimos 90 dias)
        $active_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $active_clients = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT meta_client.meta_value) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} meta_date ON p.ID = meta_date.post_id AND meta_date.meta_key = 'appointment_date'
            INNER JOIN {$wpdb->postmeta} meta_client ON p.ID = meta_client.post_id AND meta_client.meta_key = 'appointment_client_id'
            WHERE p.post_type = 'dps_agendamento'
            AND p.post_status = 'publish'
            AND meta_date.meta_value >= %s",
            $active_date
        ) );
        $context .= "- Clientes ativos (últimos 90 dias): {$active_clients}\n\n";

        // 2. Estatísticas de pets (CPT dps_pet)
        $pet_counts = wp_count_posts( 'dps_pet' );
        $total_pets = isset( $pet_counts->publish ) ? (int) $pet_counts->publish : 0;
        $context .= "🐾 PETS:\n";
        $context .= "- Total de pets cadastrados: {$total_pets}\n\n";

        // 3. Agendamentos (CPT dps_agendamento com meta appointment_date)
        $today = current_time( 'Y-m-d' );
        $week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
        $week_end = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
        $month_start = gmdate( 'Y-m-01' );
        $month_end = gmdate( 'Y-m-t' );
        
        // Contagem eficiente usando SQL direto com prepared statements
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $today_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'appointment_date'
            WHERE p.post_type = 'dps_agendamento' AND p.post_status = 'publish'
            AND pm.meta_value = %s",
            $today
        ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $week_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'appointment_date'
            WHERE p.post_type = 'dps_agendamento' AND p.post_status = 'publish'
            AND pm.meta_value BETWEEN %s AND %s",
            $week_start,
            $week_end
        ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $month_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'appointment_date'
            WHERE p.post_type = 'dps_agendamento' AND p.post_status = 'publish'
            AND pm.meta_value BETWEEN %s AND %s",
            $month_start,
            $month_end
        ) );
        
        $context .= "📅 AGENDAMENTOS:\n";
        $context .= "- Agendamentos hoje ({$today}): {$today_count}\n";
        $context .= "- Agendamentos esta semana: {$week_count}\n";
        $context .= "- Agendamentos este mês: {$month_count}\n\n";

        // 4. Dados financeiros (tabela dps_transacoes - esta é uma tabela real)
        $transacoes_table = $wpdb->prefix . 'dps_transacoes';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $transacoes_table ) ) === $transacoes_table;
        
        if ( $table_exists ) {
            $month_start_dt = gmdate( 'Y-m-01' );
            $status_pago = 'pago';
            $status_pendente = 'pendente';
            
            // Faturamento do mês (transações pagas)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $month_revenue = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM `{$transacoes_table}` 
                WHERE status = %s AND created_at >= %s",
                $status_pago,
                $month_start_dt
            ) );
            $month_revenue = (float) $month_revenue;
            
            // Pendentes
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pending_amount = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM `{$transacoes_table}` WHERE status = %s",
                $status_pendente
            ) );
            $pending_amount = (float) $pending_amount;
            
            $context .= "💰 FINANCEIRO:\n";
            $context .= "- Faturamento deste mês: R$ " . number_format( $month_revenue, 2, ',', '.' ) . "\n";
            $context .= "- Valor pendente total: R$ " . number_format( $pending_amount, 2, ',', '.' ) . "\n\n";
        }

        // 5. Informações do sistema
        $context .= "⚙️ SISTEMA:\n";
        $context .= "- Versão do Plugin Base: " . ( defined( 'DPS_VERSION' ) ? DPS_VERSION : 'N/A' ) . "\n";
        $context .= "- Versão do AI Add-on: " . DPS_AI_VERSION . "\n";
        $context .= "- WordPress: " . get_bloginfo( 'version' ) . "\n";
        $context .= "- PHP: " . PHP_VERSION . "\n";

        return $context;
    }
}
