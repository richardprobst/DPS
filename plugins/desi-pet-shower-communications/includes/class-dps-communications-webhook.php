<?php
/**
 * Gerenciador de webhooks de status de entrega
 *
 * Esta classe gerencia webhooks recebidos de gateways de comunicação
 * para atualizar o status de entrega das mensagens.
 *
 * @package DesiPetShower
 * @subpackage Communications
 * @since 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de webhooks de comunicações
 */
class DPS_Communications_Webhook {

    /**
     * Namespace da REST API
     */
    const REST_NAMESPACE = 'dps-communications/v1';

    /**
     * Secret para validação de webhooks
     */
    const WEBHOOK_SECRET_OPTION = 'dps_comm_webhook_secret';

    /**
     * Instância singleton
     *
     * @var DPS_Communications_Webhook|null
     */
    private static $instance = null;

    /**
     * Obtém instância singleton
     *
     * @return DPS_Communications_Webhook
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        // Registra endpoints REST
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );

        // Gera secret se não existir
        add_action( 'init', [ $this, 'maybe_generate_secret' ] );
    }

    /**
     * Gera secret de webhook se não existir
     */
    public function maybe_generate_secret() {
        $secret = get_option( self::WEBHOOK_SECRET_OPTION );
        if ( empty( $secret ) ) {
            $secret = wp_generate_password( 32, false );
            update_option( self::WEBHOOK_SECRET_OPTION, $secret );
        }
    }

    /**
     * Obtém o secret do webhook
     *
     * @return string
     */
    public static function get_secret() {
        return get_option( self::WEBHOOK_SECRET_OPTION, '' );
    }

    /**
     * Obtém a URL do webhook
     *
     * @param string $provider Provider do webhook (evolution, twilio, etc.)
     * @return string
     */
    public static function get_webhook_url( $provider = 'generic' ) {
        return rest_url( self::REST_NAMESPACE . '/webhook/' . sanitize_key( $provider ) );
    }

    /**
     * Registra rotas REST
     */
    public function register_routes() {
        // Endpoint genérico de webhook
        register_rest_route(
            self::REST_NAMESPACE,
            '/webhook/(?P<provider>[a-zA-Z0-9_-]+)',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => [ $this, 'verify_webhook' ],
                'args'                => [
                    'provider' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );

        // Endpoint para obter URL do webhook (somente admin)
        register_rest_route(
            self::REST_NAMESPACE,
            '/webhook-url',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_webhook_info' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        // Endpoint para estatísticas (somente admin)
        register_rest_route(
            self::REST_NAMESPACE,
            '/stats',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_stats' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        // Endpoint para histórico (somente admin)
        register_rest_route(
            self::REST_NAMESPACE,
            '/history',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_history' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'limit'   => [
                        'default'           => 50,
                        'sanitize_callback' => 'absint',
                    ],
                    'channel' => [
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'status'  => [
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );
    }

    /**
     * Verifica autenticidade do webhook
     *
     * @param WP_REST_Request $request Requisição
     * @return bool|WP_Error
     */
    public function verify_webhook( $request ) {
        $provider = $request->get_param( 'provider' );

        // Obtém secret configurado
        $secret = self::get_secret();

        // Verifica header de autenticação baseado no provider
        switch ( $provider ) {
            case 'evolution':
                // Evolution API usa header X-Webhook-Secret
                $header_secret = $request->get_header( 'X-Webhook-Secret' );
                break;

            case 'twilio':
                // Twilio usa assinatura HMAC
                // @see https://www.twilio.com/docs/usage/webhooks/webhooks-security
                $signature = $request->get_header( 'X-Twilio-Signature' );
                if ( empty( $signature ) ) {
                    return new WP_Error(
                        'missing_signature',
                        __( 'Assinatura do webhook ausente', 'dps-communications-addon' ),
                        [ 'status' => 401 ]
                    );
                }
                // NOTA: Para validação completa do Twilio, precisaríamos do AuthToken específico
                // e implementar HMAC-SHA1. Por enquanto, Twilio webhooks usam o secret geral.
                // @see https://www.twilio.com/docs/usage/webhooks/webhooks-security
                // TODO: Implementar validação HMAC quando AuthToken estiver disponível nas options
                $header_secret = $signature;
                break;

            case 'generic':
            default:
                // Provider genérico usa header Authorization: Bearer <secret>
                $auth_header = $request->get_header( 'Authorization' );
                // Regex mais restritiva: apenas caracteres não-whitespace no token
                if ( $auth_header && preg_match( '/^Bearer\s+(\S+)$/i', $auth_header, $matches ) ) {
                    $header_secret = $matches[1];
                } else {
                    // Também aceita X-Webhook-Secret
                    $header_secret = $request->get_header( 'X-Webhook-Secret' );
                }
                break;
        }

        // Valida secret
        if ( empty( $header_secret ) || ! hash_equals( $secret, $header_secret ) ) {
            $this->safe_log( 'warning', sprintf(
                'Communications Webhook: Tentativa não autorizada de %s',
                $provider
            ) );

            return new WP_Error(
                'invalid_secret',
                __( 'Secret de webhook inválido', 'dps-communications-addon' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    /**
     * Processa webhook recebido
     *
     * @param WP_REST_Request $request Requisição
     * @return WP_REST_Response|WP_Error
     */
    public function handle_webhook( $request ) {
        $provider = $request->get_param( 'provider' );
        $body     = $request->get_json_params();

        if ( empty( $body ) ) {
            return new WP_Error(
                'empty_body',
                __( 'Corpo do webhook vazio', 'dps-communications-addon' ),
                [ 'status' => 400 ]
            );
        }

        $this->safe_log( 'info', sprintf(
            'Communications Webhook: Recebido de %s',
            $provider
        ) );

        // Processa baseado no provider
        switch ( $provider ) {
            case 'evolution':
                $result = $this->process_evolution_webhook( $body );
                break;

            case 'twilio':
                $result = $this->process_twilio_webhook( $body );
                break;

            case 'generic':
            default:
                $result = $this->process_generic_webhook( $body );
                break;
        }

        // Dispara action para extensibilidade
        do_action( 'dps_comm_webhook_received', $provider, $body, $result );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Webhook processado com sucesso', 'dps-communications-addon' ),
        ], 200 );
    }

    /**
     * Processa webhook da Evolution API
     *
     * @param array $body Corpo do webhook
     * @return bool|WP_Error
     */
    private function process_evolution_webhook( $body ) {
        // Estrutura esperada da Evolution API:
        // {
        //   "event": "messages.upsert",
        //   "instance": "instance_name",
        //   "data": {
        //     "key": { "remoteJid": "5511999999999@s.whatsapp.net", "id": "MSG_ID" },
        //     "message": {...},
        //     "messageType": "conversation",
        //     "status": "DELIVERY_ACK" | "READ" | "SENT" | "PENDING"
        //   }
        // }

        if ( ! isset( $body['event'] ) ) {
            return new WP_Error( 'invalid_format', __( 'Formato inválido para Evolution API', 'dps-communications-addon' ) );
        }

        $event = $body['event'];
        $data  = isset( $body['data'] ) ? $body['data'] : [];

        // Ignora eventos que não são de status
        if ( ! in_array( $event, [ 'messages.upsert', 'messages.update', 'message.ack' ], true ) ) {
            return true;
        }

        $external_id = isset( $data['key']['id'] ) ? $data['key']['id'] : null;
        $status      = isset( $data['status'] ) ? $data['status'] : null;

        if ( ! $external_id || ! $status ) {
            return true; // Ignora se não tem dados suficientes
        }

        return $this->update_message_status( $external_id, $this->map_evolution_status( $status ) );
    }

    /**
     * Processa webhook do Twilio
     *
     * @param array $body Corpo do webhook
     * @return bool|WP_Error
     */
    private function process_twilio_webhook( $body ) {
        // Estrutura esperada do Twilio:
        // {
        //   "MessageSid": "SM...",
        //   "MessageStatus": "sent" | "delivered" | "read" | "failed" | "undelivered",
        //   "To": "+5511999999999",
        //   "From": "+1234567890"
        // }

        $external_id = isset( $body['MessageSid'] ) ? $body['MessageSid'] : null;
        $status      = isset( $body['MessageStatus'] ) ? $body['MessageStatus'] : null;

        if ( ! $external_id || ! $status ) {
            return new WP_Error( 'missing_data', __( 'Dados obrigatórios ausentes', 'dps-communications-addon' ) );
        }

        return $this->update_message_status( $external_id, $this->map_twilio_status( $status ) );
    }

    /**
     * Processa webhook genérico
     *
     * @param array $body Corpo do webhook
     * @return bool|WP_Error
     */
    private function process_generic_webhook( $body ) {
        // Estrutura esperada genérica:
        // {
        //   "message_id": "...",
        //   "status": "sent" | "delivered" | "read" | "failed",
        //   "timestamp": "2024-01-01T00:00:00Z"
        // }

        $external_id = isset( $body['message_id'] ) ? $body['message_id'] : null;
        $status      = isset( $body['status'] ) ? $body['status'] : null;

        if ( ! $external_id || ! $status ) {
            return new WP_Error( 'missing_data', __( 'Dados obrigatórios ausentes', 'dps-communications-addon' ) );
        }

        return $this->update_message_status( $external_id, $status );
    }

    /**
     * Atualiza status de uma mensagem no histórico
     *
     * @param string $external_id ID externo da mensagem
     * @param string $status      Novo status
     * @return bool
     */
    private function update_message_status( $external_id, $status ) {
        if ( ! class_exists( 'DPS_Communications_History' ) ) {
            return false;
        }

        $history = DPS_Communications_History::get_instance();
        $record  = $history->get_by_external_id( $external_id );

        if ( ! $record ) {
            $this->safe_log( 'warning', sprintf(
                'Communications Webhook: Mensagem não encontrada para external_id: %s',
                $external_id
            ) );
            return false;
        }

        $updated = $history->update_status( $record->id, $status );

        if ( $updated ) {
            $this->safe_log( 'info', sprintf(
                'Communications Webhook: Status atualizado para %s (ID: %d)',
                $status,
                $record->id
            ) );

            // Dispara action específica de status
            do_action( 'dps_comm_status_updated', $record->id, $status, $record );
        }

        return $updated;
    }

    /**
     * Mapeia status da Evolution API para status interno
     *
     * @param string $status Status da Evolution API
     * @return string
     */
    private function map_evolution_status( $status ) {
        $map = [
            'PENDING'      => DPS_Communications_History::STATUS_PENDING,
            'SENT'         => DPS_Communications_History::STATUS_SENT,
            'DELIVERY_ACK' => DPS_Communications_History::STATUS_DELIVERED,
            'READ'         => DPS_Communications_History::STATUS_READ,
            'ERROR'        => DPS_Communications_History::STATUS_FAILED,
        ];

        return isset( $map[ $status ] ) ? $map[ $status ] : DPS_Communications_History::STATUS_PENDING;
    }

    /**
     * Mapeia status do Twilio para status interno
     *
     * @param string $status Status do Twilio
     * @return string
     */
    private function map_twilio_status( $status ) {
        $map = [
            'queued'      => DPS_Communications_History::STATUS_PENDING,
            'sent'        => DPS_Communications_History::STATUS_SENT,
            'delivered'   => DPS_Communications_History::STATUS_DELIVERED,
            'read'        => DPS_Communications_History::STATUS_READ,
            'failed'      => DPS_Communications_History::STATUS_FAILED,
            'undelivered' => DPS_Communications_History::STATUS_FAILED,
        ];

        return isset( $map[ $status ] ) ? $map[ $status ] : DPS_Communications_History::STATUS_PENDING;
    }

    /**
     * Mascara o secret para exibição segura
     *
     * @since 0.3.0
     * @param string|null $secret Secret completo
     * @return string Secret mascarado (ex: "abc***xyz") ou string vazia se inválido
     */
    private function mask_secret( $secret ) {
        // Valida que é uma string não vazia
        if ( ! is_string( $secret ) || '' === $secret ) {
            return '';
        }

        $length = strlen( $secret );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }
        return substr( $secret, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $secret, -4 );
    }

    /**
     * Retorna informações do webhook para configuração
     *
     * @param WP_REST_Request $request Requisição
     * @return WP_REST_Response
     */
    public function get_webhook_info( $request ) {
        $secret = self::get_secret();
        
        return new WP_REST_Response( [
            'urls' => [
                'generic'   => self::get_webhook_url( 'generic' ),
                'evolution' => self::get_webhook_url( 'evolution' ),
                'twilio'    => self::get_webhook_url( 'twilio' ),
            ],
            'secret_preview' => $this->mask_secret( $secret ),
            'secret_length'  => strlen( $secret ),
            'instructions'   => __( 'O secret completo está disponível na página de configurações do admin. Use no header Authorization: Bearer <secret> ou X-Webhook-Secret: <secret>', 'dps-communications-addon' ),
        ], 200 );
    }

    /**
     * Retorna estatísticas de comunicações
     *
     * @param WP_REST_Request $request Requisição
     * @return WP_REST_Response
     */
    public function get_stats( $request ) {
        $stats = [];

        if ( class_exists( 'DPS_Communications_History' ) ) {
            $history           = DPS_Communications_History::get_instance();
            $stats['history']  = $history->get_stats();
        }

        if ( class_exists( 'DPS_Communications_Retry' ) ) {
            $retry           = DPS_Communications_Retry::get_instance();
            $stats['retry']  = $retry->get_stats();
        }

        return new WP_REST_Response( $stats, 200 );
    }

    /**
     * Retorna histórico de comunicações
     *
     * @param WP_REST_Request $request Requisição
     * @return WP_REST_Response
     */
    public function get_history( $request ) {
        if ( ! class_exists( 'DPS_Communications_History' ) ) {
            return new WP_REST_Response( [], 200 );
        }

        $history = DPS_Communications_History::get_instance();
        $records = $history->get_recent(
            $request->get_param( 'limit' ),
            $request->get_param( 'channel' ),
            $request->get_param( 'status' )
        );

        return new WP_REST_Response( $records, 200 );
    }

    /**
     * Log seguro verificando disponibilidade do DPS_Logger
     *
     * @param string $level   Nível do log
     * @param string $message Mensagem
     */
    private function safe_log( $level, $message ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, $message );
        }
    }
}
