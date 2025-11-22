<?php
/**
 * Cliente da API da OpenAI.
 *
 * Este arquivo contém a classe responsável por fazer chamadas à API da OpenAI
 * de forma segura e robusta, tratando erros e timeouts adequadamente.
 *
 * @package DPS_AI_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe cliente da API da OpenAI.
 *
 * Responsável por fazer chamadas HTTP à API da OpenAI usando wp_remote_post(),
 * tratar erros, timeouts e retornar apenas o conteúdo da resposta ou null em caso de falha.
 */
class DPS_AI_Client {

    /**
     * URL base da API da OpenAI.
     *
     * @var string
     */
    const API_BASE_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Realiza uma chamada à API Chat Completions da OpenAI.
     *
     * @param array $messages Array de mensagens no formato [['role' => 'system|user|assistant', 'content' => 'texto'], ...]
     * @param array $options  Opções adicionais (temperature, max_tokens, etc.). Se não fornecido, usa configurações salvas.
     *
     * @return string|null Conteúdo da resposta ou null em caso de erro.
     */
    public static function chat( array $messages, array $options = [] ) {
        // Carrega configurações
        $settings = get_option( 'dps_ai_settings', [] );

        // Verifica se a IA está habilitada
        if ( empty( $settings['enabled'] ) ) {
            return null;
        }

        // Verifica se há API key configurada
        $api_key = $settings['api_key'] ?? '';
        if ( empty( $api_key ) ) {
            error_log( 'DPS AI: API key da OpenAI não configurada.' );
            return null;
        }

        // Mescla opções com configurações padrão
        $model       = $options['model'] ?? $settings['model'] ?? 'gpt-3.5-turbo';
        $temperature = $options['temperature'] ?? $settings['temperature'] ?? 0.4;
        $max_tokens  = $options['max_tokens'] ?? $settings['max_tokens'] ?? 500;
        $timeout     = $options['timeout'] ?? $settings['timeout'] ?? 10;

        // Monta payload da requisição
        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => floatval( $temperature ),
            'max_tokens'  => absint( $max_tokens ),
        ];

        // Configuração da requisição HTTP
        $args = [
            'method'  => 'POST',
            'timeout' => absint( $timeout ),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode( $payload ),
        ];

        // Executa a requisição
        $response = wp_remote_post( self::API_BASE_URL, $args );

        // Verifica se houve erro de rede ou timeout
        if ( is_wp_error( $response ) ) {
            error_log( 'DPS AI: Erro ao chamar API da OpenAI - ' . $response->get_error_message() );
            return null;
        }

        // Obtém código de status HTTP
        $status_code = wp_remote_retrieve_response_code( $response );

        // Verifica se a resposta é 200 OK
        if ( 200 !== $status_code ) {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'DPS AI: API da OpenAI retornou status ' . $status_code . ' - ' . $body );
            return null;
        }

        // Decodifica o JSON da resposta
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Valida estrutura da resposta
        if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
            error_log( 'DPS AI: Resposta da API da OpenAI em formato inválido - ' . $body );
            return null;
        }

        // Retorna apenas o conteúdo da mensagem
        return trim( $data['choices'][0]['message']['content'] );
    }

    /**
     * Testa a conexão com a API da OpenAI.
     *
     * Útil para validar a API key nas configurações.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function test_connection() {
        $test_messages = [
            [
                'role'    => 'system',
                'content' => 'Você é um assistente de teste.',
            ],
            [
                'role'    => 'user',
                'content' => 'Diga apenas "OK" se você está funcionando.',
            ],
        ];

        $response = self::chat( $test_messages, [ 'max_tokens' => 10 ] );

        if ( null === $response ) {
            return [
                'success' => false,
                'message' => __( 'Falha ao conectar com a API da OpenAI. Verifique a chave de API e tente novamente.', 'dps-ai' ),
            ];
        }

        return [
            'success' => true,
            'message' => __( 'Conexão com a API da OpenAI estabelecida com sucesso!', 'dps-ai' ),
        ];
    }
}
