<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface para gerenciador de sessões do portal.
 * 
 * Define o contrato para implementações de gerenciamento de sessão,
 * permitindo injeção de dependência e facilidade de testes.
 * 
 * @since 3.0.0
 */
interface DPS_Portal_Session_Manager_Interface {

    /**
     * Obtém o ID do cliente autenticado.
     *
     * @return int ID do cliente ou 0 se não autenticado.
     */
    public function get_authenticated_client_id();

    /**
     * Autentica um cliente criando uma sessão.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se autenticação foi bem-sucedida.
     */
    public function authenticate_client( $client_id );

    /**
     * Faz logout do cliente atual.
     *
     * @return void
     */
    public function logout();

    /**
     * Obtém a URL de logout.
     *
     * @return string URL de logout.
     */
    public function get_logout_url();

    /**
     * Processa requisição de logout.
     *
     * @return void
     */
    public function handle_logout_request();
}
