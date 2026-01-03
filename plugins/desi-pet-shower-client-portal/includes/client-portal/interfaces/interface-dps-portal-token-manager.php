<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface para gerenciador de tokens do portal.
 * 
 * Define o contrato para implementações de gerenciamento de tokens de acesso,
 * permitindo injeção de dependência e facilidade de testes.
 * 
 * @since 3.0.0
 */
interface DPS_Portal_Token_Manager_Interface {

    /**
     * Valida um token de acesso.
     *
     * @param string $token Token em texto plano.
     * @return array|false Array com dados do token se válido, false caso contrário.
     *                     Array: ['id' => int, 'client_id' => int, 'type' => string]
     */
    public function validate_token( $token );

    /**
     * Gera um novo token para um cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param string $type      Tipo do token ('temporary' ou 'permanent').
     * @param int    $duration  Duração em segundos (para tokens temporários).
     * @return string|false Token gerado ou false em caso de erro.
     */
    public function generate_token( $client_id, $type = 'temporary', $duration = 86400 );

    /**
     * Marca um token como usado.
     *
     * @param int $token_id ID do registro do token.
     * @return bool True se marcado com sucesso.
     */
    public function mark_as_used( $token_id );

    /**
     * Obtém estatísticas de tokens de um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array com estatísticas (active, used, expired, etc.).
     */
    public function get_client_stats( $client_id );
}
