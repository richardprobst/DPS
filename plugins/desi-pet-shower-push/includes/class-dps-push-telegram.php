<?php
/**
 * Serviço de integração com Telegram para o Push Add-on.
 *
 * Gerencia envio de mensagens e teste de conexão com a API do Telegram.
 *
 * @package DPS_Push_Addon
 * @since   2.0.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de integração com Telegram.
 *
 * @since 2.0.0
 */
class DPS_Push_Telegram {

    /**
     * URL base da API do Telegram.
     *
     * @since 2.0.0
     * @var string
     */
    const API_BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Regex para validação de token do bot.
     *
     * Formato: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ (8-12 dígitos : 30-50 alnum/hifens).
     *
     * @since 2.0.0
     * @var string
     */
    const TOKEN_REGEX = '/^\d{8,12}:[A-Za-z0-9_-]{30,50}$/';

    /**
     * Regex para validação de Chat ID.
     *
     * Formato: número inteiro (positivo para chats, negativo para grupos).
     *
     * @since 2.0.0
     * @var string
     */
    const CHAT_ID_REGEX = '/^-?\d+$/';

    /**
     * Envia mensagem para o Telegram.
     *
     * Utilizado como callback do hook `dps_send_push_notification` disparado
     * pelos relatórios por email.
     *
     * @since 1.1.0
     * @param string $message Mensagem a enviar.
     * @param string $context Contexto (agenda, report, weekly).
     */
    public function send( $message, $context = '' ) {
        $token   = get_option( 'dps_push_telegram_token' );
        $chat_id = get_option( 'dps_push_telegram_chat' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return;
        }

        if ( ! preg_match( self::TOKEN_REGEX, $token ) ) {
            self::log( 'error', __( 'Token do Telegram com formato inválido.', 'dps-push-addon' ), [ 'context' => $context ] );
            return;
        }

        if ( ! preg_match( self::CHAT_ID_REGEX, $chat_id ) ) {
            self::log( 'error', __( 'Chat ID do Telegram com formato inválido.', 'dps-push-addon' ), [ 'context' => $context ] );
            return;
        }

        $url = self::API_BASE_URL . urlencode( $token ) . '/sendMessage';

        $response = wp_remote_post( $url, [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $message,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            self::log( 'error',
                sprintf( __( 'Erro ao enviar para Telegram: %s', 'dps-push-addon' ), $response->get_error_message() ),
                [ 'context' => $context ]
            );
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['ok'] ) && $data['ok'] ) {
            self::log( 'info', __( 'Mensagem enviada para Telegram.', 'dps-push-addon' ), [ 'context' => $context ] );
        } else {
            $error_desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : __( 'Erro desconhecido', 'dps-push-addon' );
            self::log( 'error',
                sprintf( __( 'Telegram retornou erro: %s', 'dps-push-addon' ), $error_desc ),
                [ 'context' => $context ]
            );
        }
    }

    /**
     * Envia mensagem de teste para verificar conexão.
     *
     * @since 1.2.0
     * @return array{success: bool, message: string} Resultado do teste.
     */
    public function test_connection() {
        $token   = get_option( 'dps_push_telegram_token', '' );
        $chat_id = get_option( 'dps_push_telegram_chat', '' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            return [
                'success' => false,
                'message' => __( 'Configure o Token do Bot e o Chat ID antes de testar.', 'dps-push-addon' ),
            ];
        }

        if ( ! preg_match( self::TOKEN_REGEX, $token ) ) {
            return [
                'success' => false,
                'message' => __( 'Formato de token inválido. Verifique o token do bot.', 'dps-push-addon' ),
            ];
        }

        if ( ! preg_match( self::CHAT_ID_REGEX, $chat_id ) ) {
            return [
                'success' => false,
                'message' => __( 'Chat ID deve ser um número válido.', 'dps-push-addon' ),
            ];
        }

        $url = self::API_BASE_URL . urlencode( $token ) . '/sendMessage';

        $test_message = sprintf(
            /* translators: %s: nome do blog */
            __( '🔔 Teste de conexão do desi.pet by PRObst (%s). Conexão funcionando!', 'dps-push-addon' ),
            get_bloginfo( 'name' )
        );

        $response = wp_remote_post( $url, [
            'body'    => [
                'chat_id'    => $chat_id,
                'text'       => $test_message,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: mensagem de erro */
                    __( 'Erro de conexão: %s', 'dps-push-addon' ),
                    $response->get_error_message()
                ),
            ];
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['ok'] ) && $data['ok'] ) {
            return [
                'success' => true,
                'message' => __( 'Conexão com Telegram funcionando! Mensagem de teste enviada.', 'dps-push-addon' ),
            ];
        }

        $error_desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : __( 'Erro desconhecido.', 'dps-push-addon' );
        return [
            'success' => false,
            'message' => sprintf(
                /* translators: %s: descrição do erro do Telegram */
                __( 'Erro do Telegram: %s', 'dps-push-addon' ),
                $error_desc
            ),
        ];
    }

    /**
     * Registra log.
     *
     * @since 2.0.0
     * @param string $level   Nível (info, error, warning).
     * @param string $message Mensagem.
     * @param array  $context Contexto adicional.
     */
    private static function log( $level, $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            $allowed_levels = [ 'info', 'error', 'warning', 'debug' ];
            if ( ! in_array( $level, $allowed_levels, true ) ) {
                $level = 'info';
            }
            call_user_func( [ 'DPS_Logger', $level ], $message, $context, 'telegram' );
        }
    }
}
