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
     * Com tratamento robusto de erros incluindo:
     * - Validação de configurações
     * - Tratamento de timeouts e falhas de rede
     * - Tratamento de códigos HTTP de erro
     * - Validação de estrutura da resposta
     * - Try/catch para exceções inesperadas
     *
     * @param array $messages Array de mensagens no formato [['role' => 'system|user|assistant', 'content' => 'texto'], ...]
     * @param array $options  Opções adicionais (temperature, max_tokens, etc.). Se não fornecido, usa configurações salvas.
     *
     * @return string|null Conteúdo da resposta ou null em caso de erro.
     */
    public static function chat( array $messages, array $options = [] ) {
        try {
            // Carrega configurações
            $settings = get_option( 'dps_ai_settings', [] );

            // Verifica se a IA está habilitada
            if ( empty( $settings['enabled'] ) ) {
                dps_ai_log_debug( 'Assistente de IA está desabilitado nas configurações' );
                return null;
            }

            // Verifica se há API key configurada
            $api_key = $settings['api_key'] ?? '';
            if ( empty( $api_key ) ) {
                dps_ai_log_error( 'API key da OpenAI não configurada' );
                return null;
            }

            // Valida array de mensagens
            if ( empty( $messages ) || ! is_array( $messages ) ) {
                dps_ai_log_error( 'Array de mensagens inválido', [ 'messages' => $messages ] );
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

            dps_ai_log_debug( 'Enviando requisição para API da OpenAI', [
                'model'      => $model,
                'msg_count'  => count( $messages ),
                'max_tokens' => $max_tokens,
            ] );

            // Marca início para calcular tempo de resposta
            $start_time = microtime( true );

            // Executa a requisição
            $response = wp_remote_post( self::API_BASE_URL, $args );

            // Calcula tempo de resposta
            $response_time = microtime( true ) - $start_time;

            // Verifica se houve erro de rede ou timeout
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                $error_code    = $response->get_error_code();
                
                // Logs detalhados baseados no tipo de erro
                if ( 'http_request_failed' === $error_code ) {
                    dps_ai_log_error( 'Falha na requisição HTTP para API da OpenAI', [
                        'error'         => $error_message,
                        'timeout'       => $timeout,
                        'response_time' => round( $response_time, 2 ),
                    ] );
                } else {
                    dps_ai_log_error( 'Erro WP ao chamar API da OpenAI', [
                        'code'  => $error_code,
                        'error' => $error_message,
                    ] );
                }
                
                return null;
            }

            // Obtém código de status HTTP
            $status_code = wp_remote_retrieve_response_code( $response );
            $body        = wp_remote_retrieve_body( $response );

            // Trata códigos HTTP de erro
            if ( 200 !== $status_code ) {
                // Tenta decodificar mensagem de erro da API
                $error_data = json_decode( $body, true );
                $api_error  = $error_data['error']['message'] ?? 'Erro desconhecido';
                
                // Logs específicos por tipo de erro HTTP
                $error_context = [
                    'status'        => $status_code,
                    'api_error'     => $api_error,
                    'response_time' => round( $response_time, 2 ),
                ];
                
                switch ( $status_code ) {
                    case 400:
                        dps_ai_log_error( 'Requisição inválida para API da OpenAI (Bad Request)', $error_context );
                        break;
                    case 401:
                        dps_ai_log_error( 'API key inválida ou expirada (Unauthorized)', $error_context );
                        break;
                    case 429:
                        dps_ai_log_warning( 'Rate limit excedido na API da OpenAI (Too Many Requests)', $error_context );
                        break;
                    case 500:
                    case 502:
                    case 503:
                        dps_ai_log_warning( 'Erro no servidor da OpenAI (Server Error)', $error_context );
                        break;
                    default:
                        dps_ai_log_error( 'API da OpenAI retornou código de erro', $error_context );
                }
                
                return null;
            }

            // Valida se o body não está vazio
            if ( empty( $body ) ) {
                dps_ai_log_error( 'Resposta da API da OpenAI está vazia' );
                return null;
            }

            // Decodifica o JSON da resposta
            $data = json_decode( $body, true );

            // Valida se JSON é válido
            if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
                dps_ai_log_error( 'Resposta da API da OpenAI não é JSON válido', [
                    'json_error' => json_last_error_msg(),
                    'body'       => substr( $body, 0, 200 ),
                ] );
                return null;
            }

            // Valida estrutura da resposta
            if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
                dps_ai_log_error( 'Resposta da API da OpenAI em formato inválido (faltando choices/message/content)', [
                    'data_keys' => array_keys( $data ),
                ] );
                return null;
            }

            // Log de sucesso em modo debug
            dps_ai_log_debug( 'Resposta recebida com sucesso da API da OpenAI', [
                'response_time' => round( $response_time, 2 ),
                'tokens_used'   => $data['usage']['total_tokens'] ?? 'unknown',
            ] );

            // Retorna apenas o conteúdo da mensagem
            return trim( $data['choices'][0]['message']['content'] );
            
        } catch ( Exception $e ) {
            // Captura qualquer exceção inesperada
            // Sanitiza caminho do arquivo para não expor estrutura em produção
            // Inclui nome da classe se disponível para facilitar debug
            $file_path = basename( $e->getFile() );
            $class_name = get_class( $e );
            
            dps_ai_log_error( 'Exceção inesperada ao chamar API da OpenAI', [
                'exception_class' => $class_name,
                'exception' => $e->getMessage(),
                'file'      => $file_path,
                'line'      => $e->getLine(),
            ] );
            return null;
        }
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
