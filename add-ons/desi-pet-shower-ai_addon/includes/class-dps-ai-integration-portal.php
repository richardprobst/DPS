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
        // Adiciona widget ao Portal do Cliente
        add_action( 'dps_client_portal_after_content', [ $this, 'render_ai_widget' ] );

        // Registra handler AJAX para perguntas (usuários logados e não logados)
        add_action( 'wp_ajax_dps_ai_portal_ask', [ $this, 'handle_ajax_ask' ] );
        add_action( 'wp_ajax_nopriv_dps_ai_portal_ask', [ $this, 'handle_ajax_ask' ] );

        // Registra assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_portal_assets' ] );
    }

    /**
     * Renderiza o widget de IA no Portal do Cliente.
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

        ?>
        <div id="dps-ai-widget" class="dps-ai-widget">
            <div class="dps-ai-header">
                <h3><?php esc_html_e( 'Assistente Virtual', 'dps-ai' ); ?></h3>
                <button id="dps-ai-toggle" class="dps-ai-toggle" aria-label="<?php esc_attr_e( 'Expandir/Recolher assistente', 'dps-ai' ); ?>">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
            </div>

            <div id="dps-ai-content" class="dps-ai-content" style="display: none;">
                <div class="dps-ai-description">
                    <p><?php esc_html_e( 'Olá! Sou o assistente virtual do Desi Pet Shower. Posso ajudar com informações sobre seus agendamentos, serviços, histórico e funcionalidades do portal.', 'dps-ai' ); ?></p>
                </div>

                <div id="dps-ai-messages" class="dps-ai-messages">
                    <!-- Mensagens aparecerão aqui -->
                </div>

                <div class="dps-ai-input-wrapper">
                    <textarea
                        id="dps-ai-question"
                        class="dps-ai-question"
                        placeholder="<?php esc_attr_e( 'Faça uma pergunta sobre seus agendamentos, serviços ou histórico...', 'dps-ai' ); ?>"
                        rows="3"
                    ></textarea>
                    <button id="dps-ai-submit" class="dps-ai-submit">
                        <?php esc_html_e( 'Perguntar', 'dps-ai' ); ?>
                    </button>
                </div>

                <div id="dps-ai-loading" class="dps-ai-loading" style="display: none;">
                    <span class="spinner"></span>
                    <span><?php esc_html_e( 'Pensando...', 'dps-ai' ); ?></span>
                </div>
            </div>
        </div>
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

        // Retorna a resposta com sucesso
        wp_send_json_success( [
            'answer' => $answer,
        ] );
    }

    /**
     * Carrega assets (JS e CSS) apenas no Portal do Cliente.
     */
    public function enqueue_portal_assets() {
        // Verifica se estamos em uma página com o shortcode do Portal
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'dps_client_portal' ) ) {
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
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dps_ai_ask' ),
            'i18n'    => [
                'errorGeneric' => __( 'Ocorreu um erro ao processar sua pergunta. Tente novamente.', 'dps-ai' ),
                'you'          => __( 'Você', 'dps-ai' ),
                'assistant'    => __( 'Assistente', 'dps-ai' ),
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
