<?php
/**
 * Conector WhatsApp para AI Add-on.
 *
 * Responsável por:
 * - Normalizar dados recebidos de diferentes providers (Meta, Twilio, etc.)
 * - Enviar mensagens de resposta para WhatsApp
 * - Isolar lógica específica de provider
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de conexão com WhatsApp Business API.
 */
class DPS_AI_WhatsApp_Connector {

    /**
     * Providers suportados.
     *
     * @var array
     */
    const PROVIDERS = [
        'meta',   // Meta (Facebook) WhatsApp Business API
        'twilio', // Twilio WhatsApp API
        'custom', // Custom provider/gateway
    ];

    /**
     * Normaliza dados de mensagem recebida de diferentes providers.
     *
     * @param array  $raw_data Dados brutos do webhook.
     * @param string $provider Provider usado (meta, twilio, custom).
     *
     * @return array|false Array normalizado com 'phone', 'message', 'metadata' ou false se inválido.
     */
    public static function normalize_incoming_message( $raw_data, $provider = 'meta' ) {
        if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
            dps_ai_log( "Provider WhatsApp inválido: {$provider}", 'error' );
            return false;
        }

        switch ( $provider ) {
            case 'meta':
                return self::normalize_meta_message( $raw_data );
            
            case 'twilio':
                return self::normalize_twilio_message( $raw_data );
            
            case 'custom':
                return self::normalize_custom_message( $raw_data );
            
            default:
                return false;
        }
    }

    /**
     * Normaliza mensagem do Meta WhatsApp Business API.
     *
     * @param array $raw_data Dados brutos do webhook.
     *
     * @return array|false Dados normalizados ou false.
     */
    private static function normalize_meta_message( $raw_data ) {
        // Estrutura esperada do Meta WhatsApp:
        // {
        //   "object": "whatsapp_business_account",
        //   "entry": [{
        //     "changes": [{
        //       "value": {
        //         "messages": [{
        //           "from": "5511999999999",
        //           "text": { "body": "texto da mensagem" }
        //         }]
        //       }
        //     }]
        //   }]
        // }

        if ( empty( $raw_data['entry'][0]['changes'][0]['value']['messages'][0] ) ) {
            return false;
        }

        $message = $raw_data['entry'][0]['changes'][0]['value']['messages'][0];

        $phone = isset( $message['from'] ) ? sanitize_text_field( $message['from'] ) : '';
        $text  = isset( $message['text']['body'] ) ? sanitize_text_field( $message['text']['body'] ) : '';

        if ( empty( $phone ) || empty( $text ) ) {
            return false;
        }

        return [
            'phone'    => $phone,
            'message'  => $text,
            'metadata' => [
                'message_id' => $message['id'] ?? '',
                'timestamp'  => $message['timestamp'] ?? '',
                'type'       => $message['type'] ?? 'text',
            ],
        ];
    }

    /**
     * Normaliza mensagem do Twilio WhatsApp API.
     *
     * @param array $raw_data Dados brutos do webhook.
     *
     * @return array|false Dados normalizados ou false.
     */
    private static function normalize_twilio_message( $raw_data ) {
        // Estrutura esperada do Twilio:
        // {
        //   "From": "whatsapp:+5511999999999",
        //   "Body": "texto da mensagem"
        // }

        $phone = isset( $raw_data['From'] ) ? sanitize_text_field( $raw_data['From'] ) : '';
        $text  = isset( $raw_data['Body'] ) ? sanitize_text_field( $raw_data['Body'] ) : '';

        // Remove prefixo "whatsapp:" se presente
        $phone = str_replace( 'whatsapp:', '', $phone );

        if ( empty( $phone ) || empty( $text ) ) {
            return false;
        }

        return [
            'phone'    => $phone,
            'message'  => $text,
            'metadata' => [
                'message_sid' => $raw_data['MessageSid'] ?? '',
                'account_sid' => $raw_data['AccountSid'] ?? '',
            ],
        ];
    }

    /**
     * Normaliza mensagem de provider customizado.
     *
     * @param array $raw_data Dados brutos do webhook.
     *
     * @return array|false Dados normalizados ou false.
     */
    private static function normalize_custom_message( $raw_data ) {
        // Estrutura simplificada esperada:
        // {
        //   "phone": "5511999999999",
        //   "message": "texto da mensagem"
        // }

        $phone = isset( $raw_data['phone'] ) ? sanitize_text_field( $raw_data['phone'] ) : '';
        $text  = isset( $raw_data['message'] ) ? sanitize_text_field( $raw_data['message'] ) : '';

        if ( empty( $phone ) || empty( $text ) ) {
            return false;
        }

        return [
            'phone'    => $phone,
            'message'  => $text,
            'metadata' => $raw_data['metadata'] ?? [],
        ];
    }

    /**
     * Envia mensagem de resposta para WhatsApp.
     *
     * @param string $phone    Número de telefone do destinatário.
     * @param string $message  Texto da mensagem a enviar.
     * @param string $provider Provider a usar.
     *
     * @return array Array com 'success' (bool) e 'message' (string) ou 'error'.
     */
    public static function send_message( $phone, $message, $provider = 'meta' ) {
        if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
            return [
                'success' => false,
                'error'   => 'Provider inválido',
            ];
        }

        // Obtém configurações
        $settings = get_option( 'dps_ai_settings', [] );

        if ( empty( $settings['whatsapp_enabled'] ) ) {
            return [
                'success' => false,
                'error'   => 'WhatsApp não está habilitado',
            ];
        }

        switch ( $provider ) {
            case 'meta':
                return self::send_meta_message( $phone, $message, $settings );
            
            case 'twilio':
                return self::send_twilio_message( $phone, $message, $settings );
            
            case 'custom':
                return self::send_custom_message( $phone, $message, $settings );
            
            default:
                return [
                    'success' => false,
                    'error'   => 'Provider não implementado',
                ];
        }
    }

    /**
     * Envia mensagem via Meta WhatsApp Business API.
     *
     * @param string $phone    Número de telefone.
     * @param string $message  Texto da mensagem.
     * @param array  $settings Configurações do plugin.
     *
     * @return array Resultado do envio.
     */
    private static function send_meta_message( $phone, $message, $settings ) {
        $phone_number_id = $settings['whatsapp_meta_phone_id'] ?? '';
        $access_token    = $settings['whatsapp_meta_token'] ?? '';

        if ( empty( $phone_number_id ) || empty( $access_token ) ) {
            return [
                'success' => false,
                'error'   => 'Credenciais Meta WhatsApp não configuradas',
            ];
        }

        $url = "https://graph.facebook.com/v18.0/{$phone_number_id}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => [
                'body' => $message,
            ],
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            dps_ai_log( 'Erro ao enviar mensagem WhatsApp Meta: ' . $response->get_error_message(), 'error' );
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            return [
                'success'    => true,
                'message_id' => $data['messages'][0]['id'] ?? '',
            ];
        }

        dps_ai_log( "Erro ao enviar mensagem WhatsApp Meta (status {$status_code}): {$body}", 'error' );
        return [
            'success' => false,
            'error'   => $data['error']['message'] ?? 'Erro desconhecido',
        ];
    }

    /**
     * Envia mensagem via Twilio WhatsApp API.
     *
     * @param string $phone    Número de telefone.
     * @param string $message  Texto da mensagem.
     * @param array  $settings Configurações do plugin.
     *
     * @return array Resultado do envio.
     */
    private static function send_twilio_message( $phone, $message, $settings ) {
        $account_sid = $settings['whatsapp_twilio_account_sid'] ?? '';
        $auth_token  = $settings['whatsapp_twilio_auth_token'] ?? '';
        $from_number = $settings['whatsapp_twilio_from'] ?? '';

        if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from_number ) ) {
            return [
                'success' => false,
                'error'   => 'Credenciais Twilio WhatsApp não configuradas',
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
            ],
            'body'    => [
                'From' => 'whatsapp:' . $from_number,
                'To'   => 'whatsapp:' . $phone,
                'Body' => $message,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            dps_ai_log( 'Erro ao enviar mensagem WhatsApp Twilio: ' . $response->get_error_message(), 'error' );
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            return [
                'success'     => true,
                'message_sid' => $data['sid'] ?? '',
            ];
        }

        dps_ai_log( "Erro ao enviar mensagem WhatsApp Twilio (status {$status_code}): {$body}", 'error' );
        return [
            'success' => false,
            'error'   => $data['message'] ?? 'Erro desconhecido',
        ];
    }

    /**
     * Envia mensagem via provider customizado.
     *
     * @param string $phone    Número de telefone.
     * @param string $message  Texto da mensagem.
     * @param array  $settings Configurações do plugin.
     *
     * @return array Resultado do envio.
     */
    private static function send_custom_message( $phone, $message, $settings ) {
        $webhook_url = $settings['whatsapp_custom_webhook_url'] ?? '';
        $api_key     = $settings['whatsapp_custom_api_key'] ?? '';

        if ( empty( $webhook_url ) ) {
            return [
                'success' => false,
                'error'   => 'Webhook customizado não configurado',
            ];
        }

        $body = [
            'phone'   => $phone,
            'message' => $message,
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ( ! empty( $api_key ) ) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_post( $webhook_url, [
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            dps_ai_log( 'Erro ao enviar mensagem WhatsApp custom: ' . $response->get_error_message(), 'error' );
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code >= 200 && $status_code < 300 ) {
            return [
                'success' => true,
            ];
        }

        $body = wp_remote_retrieve_body( $response );
        dps_ai_log( "Erro ao enviar mensagem WhatsApp custom (status {$status_code}): {$body}", 'error' );
        return [
            'success' => false,
            'error'   => 'Erro ao enviar mensagem',
        ];
    }
}
