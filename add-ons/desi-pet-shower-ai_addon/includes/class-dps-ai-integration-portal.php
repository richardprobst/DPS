<?php
/**
 * Integração do Assistente de IA com o Portal do Cliente.
 *
 * Este arquivo contém a classe responsável por integrar o assistente de IA
 * ao Portal do Cliente, incluindo widget de chat e handlers AJAX.
 *
 * @package DPS_AI_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de integração com o Portal do Cliente.
 *
 * Responsável por:
 * - Renderizar widget de chat no Portal do Cliente
 * - Processar perguntas via AJAX
 * - Validar permissões e sessão do cliente
 * - Carregar assets (JS e CSS) apenas quando necessário
 */
class DPS_AI_Integration_Portal {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Integration_Portal|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Integration_Portal
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado. Registra hooks necessários.
     */
    private function __construct() {
        // Adiciona widget ao Portal do Cliente (antes do conteúdo, no topo)
        add_action( 'dps_client_portal_before_content', [ $this, 'render_ai_widget' ] );

        // Registra handler AJAX para perguntas (usuários logados e não logados)
        add_action( 'wp_ajax_dps_ai_portal_ask', [ $this, 'handle_ajax_ask' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_portal_ask', [ $this, 'handle_ajax_ask' ] );

        // Registra assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_portal_assets' ] );
    }

    /**
     * Renderiza o widget de IA no Portal do Cliente.
     * 
     * Design v2.0.0: Layout moderno full-width integrado ao estilo das tabs.
     *
     * @param int $client_id ID do cliente logado.
     */
    public function render_ai_widget( $client_id = 0 ) {
        // Verifica se a IA está habilitada
        $settings = get_option( 'dps_ai_settings', [] );
        if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
            // IA desabilitada ou sem API key - não exibe widget
            return;
        }

        // Fallback: obtém client_id se não foi passado pelo hook
        if ( ! $client_id ) {
            $client_id = $this->get_current_client_id();
        }

        if ( ! $client_id ) {
            return;
        }

        // Configurações de widget
        $widget_mode       = $settings['widget_mode'] ?? 'inline';
        $floating_position = $settings['floating_position'] ?? 'bottom-right';
        $enable_feedback   = ! empty( $settings['enable_feedback'] );

        // FAQs sugeridas
        $faq_suggestions = DPS_AI_Knowledge_Base::get_faq_suggestions( 4 );

        // Cliente nome para personalização
        $client_name = get_the_title( $client_id );
        $first_name  = explode( ' ', $client_name )[0] ?? $client_name;

        // Classes do widget
        $widget_classes = 'dps-ai-assistant';
        if ( 'floating' === $widget_mode ) {
            $widget_classes .= ' dps-ai-assistant--floating dps-ai-assistant--' . $floating_position;
        }

        ?>
        <section id="dps-ai-assistant" class="<?php echo esc_attr( $widget_classes ); ?>" data-client-id="<?php echo esc_attr( $client_id ); ?>" data-feedback="<?php echo $enable_feedback ? 'true' : 'false'; ?>">
            <?php if ( 'floating' === $widget_mode ) : ?>
                <!-- Botão flutuante -->
                <button id="dps-ai-fab" class="dps-ai-assistant__fab" aria-label="<?php esc_attr_e( 'Abrir assistente', 'dps-ai' ); ?>">
                    <span class="dps-ai-assistant__fab-icon">🤖</span>
                    <span class="dps-ai-assistant__fab-close">✕</span>
                </button>
            <?php endif; ?>

            <div class="dps-ai-assistant__container <?php echo 'floating' === $widget_mode ? 'dps-ai-assistant__container--floating' : ''; ?>">
                <!-- Header com gradiente moderno -->
                <header class="dps-ai-assistant__header" id="dps-ai-header">
                    <div class="dps-ai-assistant__header-content">
                        <div class="dps-ai-assistant__avatar">
                            <span class="dps-ai-assistant__avatar-icon">🤖</span>
                            <span class="dps-ai-assistant__status-dot"></span>
                        </div>
                        <div class="dps-ai-assistant__header-info">
                            <h3 class="dps-ai-assistant__title"><?php esc_html_e( 'Assistente Virtual DPS', 'dps-ai' ); ?></h3>
                            <span class="dps-ai-assistant__subtitle"><?php esc_html_e( 'Online • Resposta instantânea', 'dps-ai' ); ?></span>
                        </div>
                    </div>
                    <button id="dps-ai-toggle" class="dps-ai-assistant__toggle" aria-label="<?php esc_attr_e( 'Expandir/Recolher assistente', 'dps-ai' ); ?>" aria-expanded="true">
                        <svg class="dps-ai-assistant__toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </header>

                <!-- Conteúdo principal do assistente -->
                <div id="dps-ai-content" class="dps-ai-assistant__content">
                    <!-- Mensagem de boas-vindas personalizada -->
                    <div class="dps-ai-assistant__welcome">
                        <div class="dps-ai-assistant__welcome-text">
                            <p class="dps-ai-assistant__welcome-greeting">
                                <?php
                                printf(
                                    /* translators: %s: Nome do cliente */
                                    esc_html__( 'Olá, %s! 👋', 'dps-ai' ),
                                    '<strong>' . esc_html( $first_name ) . '</strong>'
                                );
                                ?>
                            </p>
                            <p class="dps-ai-assistant__welcome-message">
                                <?php esc_html_e( 'Sou o assistente virtual do DPS. Posso ajudar com agendamentos, serviços, histórico e dúvidas sobre o portal. Como posso ajudá-lo hoje?', 'dps-ai' ); ?>
                            </p>
                        </div>
                    </div>

                    <?php if ( ! empty( $faq_suggestions ) ) : ?>
                        <!-- Sugestões de perguntas frequentes -->
                        <div class="dps-ai-assistant__suggestions">
                            <p class="dps-ai-assistant__suggestions-label">
                                <span class="dps-ai-assistant__suggestions-icon">💡</span>
                                <?php esc_html_e( 'Perguntas populares', 'dps-ai' ); ?>
                            </p>
                            <div class="dps-ai-assistant__suggestions-grid">
                                <?php foreach ( $faq_suggestions as $faq ) : ?>
                                    <button type="button" class="dps-ai-assistant__suggestion-btn" data-question="<?php echo esc_attr( $faq ); ?>">
                                        <span class="dps-ai-assistant__suggestion-text"><?php echo esc_html( $faq ); ?></span>
                                        <svg class="dps-ai-assistant__suggestion-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"></polyline>
                                        </svg>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Área de conversa -->
                    <div id="dps-ai-messages" class="dps-ai-assistant__messages">
                        <!-- Mensagens da conversa aparecerão aqui -->
                    </div>

                    <!-- Indicador de digitação/pensando -->
                    <div id="dps-ai-loading" class="dps-ai-assistant__loading" style="display: none;">
                        <div class="dps-ai-assistant__loading-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="dps-ai-assistant__loading-text"><?php esc_html_e( 'Pensando...', 'dps-ai' ); ?></span>
                    </div>

                    <!-- Input de mensagem moderno -->
                    <div class="dps-ai-assistant__input-container">
                        <div class="dps-ai-assistant__input-wrapper">
                            <textarea
                                id="dps-ai-question"
                                class="dps-ai-assistant__input"
                                placeholder="<?php esc_attr_e( 'Digite sua pergunta aqui...', 'dps-ai' ); ?>"
                                rows="1"
                                maxlength="500"
                            ></textarea>
                            <button id="dps-ai-submit" class="dps-ai-assistant__submit" aria-label="<?php esc_attr_e( 'Enviar pergunta', 'dps-ai' ); ?>">
                                <svg class="dps-ai-assistant__submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                        <p class="dps-ai-assistant__hint">
                            <kbd>Ctrl</kbd> + <kbd>Enter</kbd> <?php esc_html_e( 'para enviar', 'dps-ai' ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }

    /**
     * Processa perguntas via AJAX.
     */
    public function handle_ajax_ask() {
        // Verifica nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_ask' ) ) {
            wp_send_json_error( [
                'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
            ] );
        }

        // Obtém ID do cliente
        $client_id = $this->get_current_client_id();
        if ( ! $client_id ) {
            wp_send_json_error( [
                'message' => __( 'Você precisa estar logado para usar o assistente.', 'dps-ai' ),
            ] );
        }

        // Obtém a pergunta
        $question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
        if ( empty( $question ) ) {
            wp_send_json_error( [
                'message' => __( 'Por favor, digite uma pergunta.', 'dps-ai' ),
            ] );
        }

        // Limita tamanho da pergunta
        if ( mb_strlen( $question ) > 500 ) {
            wp_send_json_error( [
                'message' => __( 'Pergunta muito longa. Por favor, resuma em até 500 caracteres.', 'dps-ai' ),
            ] );
        }

        // Obtém ou cria conversa ativa para este cliente
        $conversation_id = $this->get_or_create_conversation( $client_id );

        // Salva mensagem do usuário
        if ( $conversation_id && class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'user',
                'sender_identifier' => (string) $client_id,
                'message_text'      => $question,
            ] );
        }

        // Busca pets do cliente
        $pet_ids = $this->get_client_pet_ids( $client_id );

        // Chama o assistente de IA
        $answer = DPS_AI_Assistant::answer_portal_question( $client_id, $pet_ids, $question );

        // Se a IA retornou null, significa que houve falha na API
        if ( null === $answer ) {
            wp_send_json_error( [
                'message' => __( 'No momento não foi possível gerar uma resposta automática. Por favor, fale diretamente com a equipe.', 'dps-ai' ),
            ] );
        }

        // Extrai texto da resposta para processamento
        $answer_text = $this->extract_answer_text( $answer );

        // Adiciona sugestão proativa de agendamento se aplicável
        if ( class_exists( 'DPS_AI_Proactive_Scheduler' ) ) {
            $scheduler = DPS_AI_Proactive_Scheduler::get_instance();
            $answer_text = $scheduler->append_suggestion_to_response( $answer_text, $client_id, 'portal' );
        }

        // Salva resposta da IA (com sugestão, se aplicável)
        if ( $conversation_id && class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            
            // Extrai metadados da resposta original
            $answer_data = $this->extract_answer_data( $answer );
            
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'assistant',
                'sender_identifier' => 'ai',
                'message_text'      => $answer_text, // Usa texto com sugestão
                'metadata'          => $answer_data['metadata'],
            ] );
        }

        // Retorna a resposta com sucesso
        wp_send_json_success( [
            'answer' => $answer_text, // Retorna texto com sugestão
        ] );
    }

    /**
     * Extrai texto e metadados de uma resposta de IA.
     *
     * @param mixed $answer Resposta da IA (string ou array).
     *
     * @return array Array com 'text' e 'metadata'.
     */
    private function extract_answer_data( $answer ) {
        $text     = '';
        $metadata = null;

        if ( is_array( $answer ) && isset( $answer['text'] ) ) {
            $text     = $answer['text'];
            $metadata = $answer;
        } elseif ( is_string( $answer ) ) {
            $text = $answer;
        }

        return [
            'text'     => $text,
            'metadata' => $metadata,
        ];
    }

    /**
     * Extrai apenas o texto de uma resposta de IA.
     *
     * @param mixed $answer Resposta da IA (string ou array).
     *
     * @return string Texto da resposta.
     */
    private function extract_answer_text( $answer ) {
        if ( is_string( $answer ) ) {
            return $answer;
        }

        if ( is_array( $answer ) && isset( $answer['text'] ) ) {
            return $answer['text'];
        }

        return '';
    }

    /**
     * Obtém ou cria conversa ativa para o cliente no portal.
     *
     * @param int $client_id ID do cliente.
     *
     * @return int|false ID da conversa ou false em caso de erro.
     */
    private function get_or_create_conversation( $client_id ) {
        if ( ! class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            return false;
        }

        $repo = DPS_AI_Conversations_Repository::get_instance();

        // Busca conversa aberta recente do cliente no portal (últimas 24 horas)
        $conversations = $repo->get_conversations_by_customer( $client_id, 'portal', 1 );

        if ( ! empty( $conversations ) ) {
            $conversation = $conversations[0];
            
            // Se a última atividade foi há menos de 24 horas, reutiliza
            $last_activity = strtotime( $conversation->last_activity_at );
            if ( ( current_time( 'timestamp' ) - $last_activity ) < DAY_IN_SECONDS ) {
                return (int) $conversation->id;
            }
        }

        // Cria nova conversa
        $conversation_id = $repo->create_conversation( [
            'customer_id' => $client_id,
            'channel'     => 'portal',
            'status'      => 'open',
        ] );

        return $conversation_id;
    }

    /**
     * Carrega assets (JS e CSS) apenas no Portal do Cliente.
     */
    public function enqueue_portal_assets() {
        // Verifica se estamos em uma página com o shortcode do Portal
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'dps_client_portal' ) ) {
            return;
        }

        // Verifica se a IA está habilitada
        $settings = get_option( 'dps_ai_settings', [] );
        if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
            return;
        }

        // CSS do widget
        wp_enqueue_style(
            'dps-ai-portal',
            DPS_AI_ADDON_URL . 'assets/css/dps-ai-portal.css',
            [],
            DPS_AI_VERSION
        );

        // JavaScript do widget
        wp_enqueue_script(
            'dps-ai-portal',
            DPS_AI_ADDON_URL . 'assets/js/dps-ai-portal.js',
            [ 'jquery' ],
            DPS_AI_VERSION,
            true
        );

        // Localiza script com dados necessários
        wp_localize_script( 'dps-ai-portal', 'dpsAI', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'dps_ai_ask' ),
            'feedbackNonce'  => wp_create_nonce( 'dps_ai_feedback' ),
            'schedulerNonce' => wp_create_nonce( 'dps_ai_scheduler' ),
            'enableFeedback' => ! empty( $settings['enable_feedback'] ),
            'widgetMode'     => $settings['widget_mode'] ?? 'inline',
            'i18n'           => [
                'errorGeneric'        => __( 'Ocorreu um erro ao processar sua pergunta. Tente novamente.', 'dps-ai' ),
                'you'                 => __( 'Você', 'dps-ai' ),
                'assistant'           => __( 'Assistente', 'dps-ai' ),
                'pleaseEnterQuestion' => __( 'Por favor, digite uma pergunta.', 'dps-ai' ),
                'feedbackThanks'      => __( 'Obrigado pelo feedback!', 'dps-ai' ),
                'wasHelpful'          => __( 'Esta resposta foi útil?', 'dps-ai' ),
            ],
        ] );
    }

    /**
     * Obtém o ID do cliente logado.
     *
     * Compatível com o sistema de autenticação do Portal do Cliente
     * (que pode usar sessão PHP ou usuário WordPress).
     *
     * @return int ID do cliente ou 0 se não estiver logado.
     */
    private function get_current_client_id() {
        // Tenta obter via método do Portal do Cliente se disponível
        if ( class_exists( 'DPS_Client_Portal' ) && method_exists( 'DPS_Client_Portal', 'get_current_client_id' ) ) {
            $instance  = DPS_Client_Portal::get_instance();
            $client_id = $instance->get_current_client_id();
            if ( $client_id ) {
                return $client_id;
            }
        }

        // Fallback: tenta via usuário WordPress logado
        if ( is_user_logged_in() ) {
            $user_id   = get_current_user_id();
            $client_id = absint( get_user_meta( $user_id, 'dps_client_id', true ) );

            if ( $client_id && 'dps_cliente' === get_post_type( $client_id ) ) {
                return $client_id;
            }

            // Tenta buscar cliente por email
            $user = get_userdata( $user_id );
            if ( $user && $user->user_email ) {
                $client_query = new WP_Query( [
                    'post_type'      => 'dps_cliente',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => [
                        [
                            'key'     => 'client_email',
                            'value'   => $user->user_email,
                            'compare' => '=',
                        ],
                    ],
                ] );

                if ( $client_query->have_posts() ) {
                    return absint( $client_query->posts[0]->ID );
                }
            }
        }

        return 0;
    }

    /**
     * Busca os IDs dos pets de um cliente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return array IDs dos pets.
     */
    private function get_client_pet_ids( $client_id ) {
        $query = new WP_Query( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'pet_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
        ] );

        return $query->posts;
    }
}
