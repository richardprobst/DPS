<?php
/**
 * Handler de Webhook WhatsApp para AI Add-on.
 *
 * Responsável por:
 * - Receber mensagens do WhatsApp via webhook
 * - Validar requisições
 * - Processar mensagens com IA
 * - Registrar histórico de conversas
 * - Enviar respostas de volta
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de handler para webhook WhatsApp.
 */
class DPS_AI_WhatsApp_Webhook {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_WhatsApp_Webhook|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_WhatsApp_Webhook
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
        // Registra endpoint REST API
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Registra rotas REST API para webhook WhatsApp.
     */
    public function register_rest_routes() {
        // Endpoint para receber mensagens
        register_rest_route( 'dps-ai/v1', '/whatsapp-webhook', [
            'methods'             => [ 'GET', 'POST' ],
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true', // Validação interna
        ] );
    }

    /**
     * Handler principal do webhook.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response.
     */
    public function handle_webhook( $request ) {
        $method = $request->get_method();

        // GET: Verificação do webhook (Meta WhatsApp)
        if ( 'GET' === $method ) {
            return $this->handle_verification( $request );
        }

        // POST: Mensagem recebida
        if ( 'POST' === $method ) {
            return $this->handle_incoming_message( $request );
        }

        return new WP_Error( 'invalid_method', 'Método não suportado', [ 'status' => 405 ] );
    }

    /**
     * Handler para verificação do webhook (Meta WhatsApp).
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response.
     */
    private function handle_verification( $request ) {
        $settings = get_option( 'dps_ai_settings', [] );

        // Parâmetros de verificação do Meta WhatsApp
        $mode          = $request->get_param( 'hub.mode' );
        $token         = $request->get_param( 'hub.verify_token' );
        $challenge     = $request->get_param( 'hub.challenge' );
        $verify_token  = $settings['whatsapp_verify_token'] ?? '';

        if ( 'subscribe' === $mode && $token === $verify_token ) {
            dps_ai_log( 'Webhook WhatsApp verificado com sucesso' );
            return new WP_REST_Response( $challenge, 200 );
        }

        dps_ai_log( 'Falha na verificação do webhook WhatsApp', 'warning' );
        return new WP_Error( 'verification_failed', 'Verificação falhou', [ 'status' => 403 ] );
    }

    /**
     * Handler para mensagem recebida.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response Response.
     */
    private function handle_incoming_message( $request ) {
        // Obtém configurações
        $settings = get_option( 'dps_ai_settings', [] );

        if ( empty( $settings['whatsapp_enabled'] ) ) {
            dps_ai_log( 'Webhook WhatsApp recebido mas canal está desabilitado', 'warning' );
            return new WP_REST_Response( [ 'status' => 'disabled' ], 200 );
        }

        // Valida requisição
        if ( ! $this->validate_webhook_request( $request, $settings ) ) {
            dps_ai_log( 'Webhook WhatsApp rejeitado: validação falhou', 'error' );
            return new WP_Error( 'invalid_request', 'Requisição inválida', [ 'status' => 403 ] );
        }

        // Obtém provider configurado
        $provider = $settings['whatsapp_provider'] ?? 'meta';

        // Normaliza mensagem recebida
        $raw_data = $request->get_json_params();
        if ( empty( $raw_data ) ) {
            $raw_data = $request->get_body_params();
        }

        $normalized = DPS_AI_WhatsApp_Connector::normalize_incoming_message( $raw_data, $provider );

        if ( false === $normalized ) {
            dps_ai_log( 'Falha ao normalizar mensagem WhatsApp: ' . wp_json_encode( $raw_data ), 'error' );
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Formato inválido' ], 200 );
        }

        $phone   = $normalized['phone'];
        $message = $normalized['message'];

        // Processa mensagem
        $this->process_message( $phone, $message, $normalized['metadata'], $provider );

        // Retorna 200 OK para o provider
        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    /**
     * Valida requisição do webhook.
     *
     * @param WP_REST_Request $request  Request object.
     * @param array           $settings Configurações.
     *
     * @return bool True se válida, false caso contrário.
     */
    private function validate_webhook_request( $request, $settings ) {
        $provider = $settings['whatsapp_provider'] ?? 'meta';

        // Meta WhatsApp: valida assinatura
        if ( 'meta' === $provider ) {
            $signature = $request->get_header( 'x-hub-signature-256' );
            $app_secret = $settings['whatsapp_meta_app_secret'] ?? '';

            if ( empty( $signature ) || empty( $app_secret ) ) {
                return false;
            }

            $body = $request->get_body();
            $expected_signature = 'sha256=' . hash_hmac( 'sha256', $body, $app_secret );

            return hash_equals( $expected_signature, $signature );
        }

        // Twilio: valida assinatura usando X-Twilio-Signature
        if ( 'twilio' === $provider ) {
            $signature   = $request->get_header( 'x-twilio-signature' );
            $auth_token  = $settings['whatsapp_twilio_auth_token'] ?? '';

            if ( empty( $signature ) || empty( $auth_token ) ) {
                dps_ai_log( 'Twilio webhook: assinatura ou auth_token ausente', 'warning' );
                return false;
            }

            // Reconstrói a URL completa do webhook
            $webhook_url = rest_url( 'dps-ai/v1/whatsapp-webhook' );

            // Obtém parâmetros do POST para validação Twilio
            // NOTA: Esta implementação básica funciona para mensagens de texto simples.
            // Para produção com estruturas complexas (arrays, MediaUrl, etc.),
            // considere usar o SDK oficial Twilio: https://www.twilio.com/docs/php/install
            $params = $request->get_body_params();
            ksort( $params );
            $data = $webhook_url;
            foreach ( $params as $key => $value ) {
                // Trata arrays em parâmetros (ex: MediaUrl0, MediaUrl1)
                if ( is_array( $value ) ) {
                    foreach ( $value as $sub_value ) {
                        $data .= $key . $sub_value;
                    }
                } else {
                    $data .= $key . $value;
                }
            }

            // Calcula assinatura esperada usando HMAC-SHA1 com Base64
            $expected_signature = base64_encode( hash_hmac( 'sha1', $data, $auth_token, true ) );

            // Usa hash_equals para comparação segura timing-safe
            if ( ! hash_equals( $expected_signature, $signature ) ) {
                dps_ai_log( 'Twilio webhook: assinatura inválida', 'warning' );
                return false;
            }

            return true;
        }

        // Custom: valida token obrigatoriamente
        if ( 'custom' === $provider ) {
            $api_key = $settings['whatsapp_custom_api_key'] ?? '';

            // SEGURANÇA: Exige API key configurada para webhooks customizados
            if ( empty( $api_key ) ) {
                dps_ai_log( 'Custom webhook: API key não configurada - requisição rejeitada', 'warning' );
                return false;
            }

            $auth_header = $request->get_header( 'authorization' );
            $expected    = 'Bearer ' . $api_key;

            // Usa hash_equals para comparação segura timing-safe
            if ( empty( $auth_header ) || ! hash_equals( $expected, $auth_header ) ) {
                dps_ai_log( 'Custom webhook: autorização inválida', 'warning' );
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Processa mensagem recebida.
     *
     * @param string $phone    Número de telefone.
     * @param string $message  Texto da mensagem.
     * @param array  $metadata Metadados da mensagem.
     * @param string $provider Provider usado.
     */
    private function process_message( $phone, $message, $metadata, $provider ) {
        // Log da mensagem recebida (mascara telefone e mensagem para LGPD/privacidade)
        $masked_phone = $this->mask_phone( $phone );
        dps_ai_log( "WhatsApp recebido de {$masked_phone}", 'info' );

        // Obtém ou cria conversa
        $conversation_id = $this->get_or_create_conversation( $phone );

        if ( ! $conversation_id ) {
            dps_ai_log( "Erro ao criar conversa WhatsApp para {$masked_phone}", 'error' );
            return;
        }

        // Registra mensagem do usuário
        if ( class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'user',
                'sender_identifier' => $phone,
                'message_text'      => $message,
                'metadata'          => $metadata,
            ] );
        }

        // Gera resposta da IA
        $answer = $this->get_ai_response( $phone, $message );

        if ( null === $answer ) {
            dps_ai_log( "Erro ao gerar resposta IA para WhatsApp {$phone}", 'error' );
            $answer = __( 'Desculpe, não consegui processar sua mensagem no momento. Por favor, tente novamente mais tarde.', 'dps-ai' );
        }

        // Registra resposta da IA
        if ( class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            $repo = DPS_AI_Conversations_Repository::get_instance();
            $repo->add_message( $conversation_id, [
                'sender_type'       => 'assistant',
                'sender_identifier' => 'ai',
                'message_text'      => $answer,
                'metadata'          => [
                    'provider' => $provider,
                    'channel'  => 'whatsapp',
                ],
            ] );
        }

        // Envia resposta para WhatsApp
        $result = DPS_AI_WhatsApp_Connector::send_message( $phone, $answer, $provider );

        if ( ! $result['success'] ) {
            dps_ai_log( "Erro ao enviar resposta WhatsApp para {$masked_phone}: " . ( $result['error'] ?? 'desconhecido' ), 'error' );
        } else {
            dps_ai_log( "Resposta WhatsApp enviada para {$masked_phone}", 'info' );
        }
    }

    /**
     * Mascara número de telefone para logs (LGPD/privacidade).
     *
     * Exemplo: +5511999887766 -> +55***7766
     *
     * @param string $phone Número de telefone.
     *
     * @return string Número mascarado.
     */
    private function mask_phone( $phone ) {
        $length = strlen( $phone );
        
        // Telefones muito curtos: mascara completamente
        if ( $length <= 6 ) {
            return str_repeat( '*', $length );
        }
        
        // Telefones de 7 caracteres: mostra apenas 3 primeiros e 1 último
        if ( 7 === $length ) {
            return substr( $phone, 0, 3 ) . '***' . substr( $phone, -1 );
        }
        
        // Mantém os 3 primeiros e 4 últimos dígitos
        $middle_count = max( 1, $length - 7 ); // Garante pelo menos 1 asterisco
        return substr( $phone, 0, 3 ) . str_repeat( '*', $middle_count ) . substr( $phone, -4 );
    }

    /**
     * Obtém ou cria conversa para número de telefone.
     *
     * @param string $phone Número de telefone.
     *
     * @return int|false ID da conversa ou false em caso de erro.
     */
    private function get_or_create_conversation( $phone ) {
        if ( ! class_exists( 'DPS_AI_Conversations_Repository' ) ) {
            return false;
        }

        $repo = DPS_AI_Conversations_Repository::get_instance();

        // Usa hash do telefone como session_identifier
        $session_id = 'whatsapp_' . wp_hash( $phone, 'nonce' );

        // Busca conversa aberta recente (últimas 24 horas)
        $conversation = $repo->get_active_conversation_by_session( $session_id, 'whatsapp' );

        if ( $conversation ) {
            // Se a última atividade foi há menos de 24 horas, reutiliza
            $last_activity = strtotime( $conversation->last_activity_at );
            if ( ( current_time( 'timestamp' ) - $last_activity ) < DAY_IN_SECONDS ) {
                return (int) $conversation->id;
            }
        }

        // Cria nova conversa
        $conversation_id = $repo->create_conversation( [
            'customer_id'        => null, // Pode ser vinculado depois se identificar cliente
            'channel'            => 'whatsapp',
            'session_identifier' => $session_id,
            'status'             => 'open',
        ] );

        return $conversation_id;
    }

    /**
     * Gera resposta da IA para mensagem WhatsApp.
     *
     * @param string $phone   Número de telefone.
     * @param string $message Mensagem do usuário.
     *
     * @return string|null Resposta da IA ou null em caso de erro.
     */
    private function get_ai_response( $phone, $message ) {
        if ( ! class_exists( 'DPS_AI_Client' ) ) {
            return null;
        }

        // Monta contexto simples para WhatsApp (sem HTML)
        $context = $this->build_whatsapp_context( $phone );

        // System prompt adaptado para WhatsApp
        $system_prompt = $this->get_whatsapp_system_prompt();

        // Chama IA
        $response = DPS_AI_Client::chat_completion( [
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $system_prompt,
                ],
                [
                    'role'    => 'system',
                    'content' => $context,
                ],
                [
                    'role'    => 'user',
                    'content' => $message,
                ],
            ],
        ] );

        if ( ! $response || empty( $response['choices'][0]['message']['content'] ) ) {
            return null;
        }

        $answer = $response['choices'][0]['message']['content'];

        // Remove formatação HTML que possa ter sido gerada
        $answer = wp_strip_all_tags( $answer );

        return $answer;
    }

    /**
     * Monta contexto para IA em conversas WhatsApp.
     *
     * @param string $phone Número de telefone.
     *
     * @return string Contexto formatado.
     */
    private function build_whatsapp_context( $phone ) {
        $settings = get_option( 'dps_ai_settings', [] );

        // Informações do negócio (se configuradas)
        $business_info = $settings['public_chat_business_info'] ?? '';

        $context = "CANAL: WhatsApp\n";
        $context .= "CLIENTE: {$phone}\n\n";

        if ( ! empty( $business_info ) ) {
            $context .= "INFORMAÇÕES DO NEGÓCIO:\n{$business_info}\n\n";
        }

        $context .= "IMPORTANTE: Respostas devem ser curtas e diretas, adequadas para WhatsApp. Sem formatação HTML.\n";

        return $context;
    }

    /**
     * Retorna system prompt para WhatsApp.
     *
     * @return string System prompt.
     */
    private function get_whatsapp_system_prompt() {
        $settings = get_option( 'dps_ai_settings', [] );

        // Usa instruções customizadas se configuradas
        $custom_instructions = $settings['whatsapp_instructions'] ?? '';

        if ( ! empty( $custom_instructions ) ) {
            return $custom_instructions;
        }

        // Prompt padrão para WhatsApp
        return 'Você é um assistente virtual atencioso e prestativo via WhatsApp. ' .
               'Responda de forma concisa, clara e amigável. ' .
               'Mantenha as respostas curtas (máximo 3 parágrafos) e diretas. ' .
               'Não use formatação HTML ou Markdown complexo. ' .
               'Use emojis quando apropriado para deixar a conversa mais leve.';
    }
}
