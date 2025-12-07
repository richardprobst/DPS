<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Processador de requisições AJAX do portal do cliente.
 * 
 * Esta classe é responsável por processar todos os endpoints AJAX
 * relacionados ao portal (chat, notificações, etc.).
 * 
 * @since 3.0.0
 */
class DPS_Portal_AJAX_Handler {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_AJAX_Handler|null
     */
    private static $instance = null;

    /**
     * Repositório de mensagens.
     *
     * @var DPS_Message_Repository
     */
    private $message_repository;

    /**
     * Repositório de clientes.
     *
     * @var DPS_Client_Repository
     */
    private $client_repository;

    /**
     * Provedor de dados.
     *
     * @var DPS_Portal_Data_Provider
     */
    private $data_provider;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_AJAX_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        $this->message_repository = DPS_Message_Repository::get_instance();
        $this->client_repository  = DPS_Client_Repository::get_instance();
        $this->data_provider      = DPS_Portal_Data_Provider::get_instance();
        
        // Registra handlers AJAX
        add_action( 'wp_ajax_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_get_messages', [ $this, 'ajax_get_chat_messages' ] );
        add_action( 'wp_ajax_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_send_message', [ $this, 'ajax_send_chat_message' ] );
        add_action( 'wp_ajax_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        add_action( 'wp_ajax_nopriv_dps_chat_mark_read', [ $this, 'ajax_mark_messages_read' ] );
        add_action( 'wp_ajax_nopriv_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
    }

    /**
     * Valida requisição de chat e retorna client_id.
     *
     * @return int Client ID ou encerra com erro.
     */
    private function validate_chat_request() {
        // Verifica nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_portal_chat' ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce inválido', 'dps-client-portal' ) ] );
        }

        // Obtém client_id da sessão
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Cliente não autenticado', 'dps-client-portal' ) ] );
        }

        return $client_id;
    }

    /**
     * AJAX handler para obter mensagens do chat.
     *
     * @since 2.3.0
     */
    public function ajax_get_chat_messages() {
        $client_id = $this->validate_chat_request();

        // Busca mensagens usando repositório
        $messages = $this->message_repository->get_messages_by_client( $client_id );

        $formatted_messages = [];
        foreach ( $messages as $msg ) {
            $formatted_messages[] = [
                'id'      => $msg->ID,
                'content' => wp_strip_all_tags( $msg->post_content ),
                'sender'  => get_post_meta( $msg->ID, 'message_sender', true ),
                'time'    => get_post_time( get_option( 'date_format' ) . ' H:i', false, $msg, true ),
                'status'  => get_post_meta( $msg->ID, 'message_status', true ),
            ];
        }

        // Conta não lidas usando data provider
        $unread_count = $this->data_provider->get_unread_messages_count( $client_id );

        wp_send_json_success( [
            'messages'     => $formatted_messages,
            'unread_count' => $unread_count,
        ] );
    }

    /**
     * AJAX handler para enviar mensagem do chat.
     *
     * @since 2.3.0
     */
    public function ajax_send_chat_message() {
        $client_id = $this->validate_chat_request();
        
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => __( 'Mensagem vazia', 'dps-client-portal' ) ] );
        }

        if ( strlen( $message ) > 1000 ) {
            wp_send_json_error( [ 'message' => __( 'Mensagem muito longa', 'dps-client-portal' ) ] );
        }

        // Rate limiting simples (máximo 10 mensagens por minuto)
        if ( ! $this->check_rate_limit( $client_id, 10 ) ) {
            wp_send_json_error( [ 'message' => __( 'Muitas mensagens. Aguarde um momento.', 'dps-client-portal' ) ] );
        }

        // Cria a mensagem
        $client_name = get_the_title( $client_id );
        $title       = sprintf( __( 'Mensagem via Chat - %s', 'dps-client-portal' ), $client_name );

        $message_id = wp_insert_post( [
            'post_type'    => 'dps_portal_message',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => $message,
        ] );

        if ( is_wp_error( $message_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao salvar mensagem', 'dps-client-portal' ) ] );
        }

        update_post_meta( $message_id, 'message_client_id', $client_id );
        update_post_meta( $message_id, 'message_sender', 'client' );
        update_post_meta( $message_id, 'message_status', 'open' );

        // Notifica admin via Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            $api->send_message_from_client( $client_id, $message, [
                'message_id' => $message_id,
                'source'     => 'chat',
            ] );
        }

        wp_send_json_success( [
            'message_id' => $message_id,
            'time'       => current_time( 'd/m H:i' ),
        ] );
    }

    /**
     * AJAX handler para marcar mensagens como lidas.
     *
     * @since 2.3.0
     */
    public function ajax_mark_messages_read() {
        $client_id = $this->validate_chat_request();

        // Busca mensagens não lidas usando repositório
        $messages = $this->message_repository->get_unread_message_ids( $client_id );

        // Marca como lidas
        $now = current_time( 'mysql' );
        foreach ( $messages as $msg_id ) {
            update_post_meta( $msg_id, 'client_read_at', $now );
        }

        wp_send_json_success( [ 'marked' => count( $messages ) ] );
    }

    /**
     * AJAX handler para notificação de solicitação de acesso ao portal (Fase 1.4).
     * 
     * Quando cliente clica em "Quero acesso ao meu portal", registra solicitação
     * e notifica admin via Communications API se disponível.
     * 
     * Rate limiting: 5 solicitações por hora por IP
     * 
     * @since 2.4.0
     */
    public function ajax_request_portal_access() {
        // Valida IP para rate limiting
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $rate_key = 'dps_access_request_' . md5( $ip );
        
        // Verifica se já solicitou recentemente (rate limiting: 5 solicitações por hora)
        $request_count = get_transient( $rate_key );
        if ( false === $request_count ) {
            $request_count = 0;
        }
        
        if ( $request_count >= 5 ) {
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou acesso várias vezes. Aguarde um momento antes de solicitar novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Incrementa contador
        set_transient( $rate_key, $request_count + 1, HOUR_IN_SECONDS );
        
        // Captura dados do cliente (opcional, pode vir do formulário)
        $client_name = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $client_phone = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        
        // Tenta encontrar cliente por telefone se fornecido
        $client_id = $this->find_client_by_phone( $client_phone );
        
        if ( $client_id && empty( $client_name ) ) {
            $client_name = get_the_title( $client_id );
        }
        
        // Registra solicitação em log
        $this->log_access_request( $client_id, $client_name, $client_phone, $ip );
        
        // Notifica admin
        $this->notify_access_request( $client_id, $client_name, $client_phone, $ip );
        
        wp_send_json_success( [ 
            'message' => __( 'Sua solicitação foi registrada! Nossa equipe entrará em contato em breve.', 'dps-client-portal' ) 
        ] );
    }

    /**
     * Verifica rate limiting para cliente.
     *
     * @param int $client_id ID do cliente.
     * @param int $max_requests Máximo de requisições permitidas.
     * @return bool True se dentro do limite.
     */
    private function check_rate_limit( $client_id, $max_requests ) {
        $rate_key  = 'dps_chat_rate_' . $client_id;
        $rate_data = get_transient( $rate_key );
        
        if ( $rate_data && $rate_data >= $max_requests ) {
            return false;
        }
        
        set_transient( $rate_key, ( $rate_data ? $rate_data + 1 : 1 ), 60 );
        return true;
    }

    /**
     * Tenta encontrar cliente por telefone.
     *
     * @param string $phone Telefone do cliente.
     * @return int ID do cliente ou 0.
     */
    private function find_client_by_phone( $phone ) {
        if ( ! $phone ) {
            return 0;
        }
        
        $client = $this->client_repository->get_client_by_phone( $phone );
        return $client ? $client->ID : 0;
    }

    /**
     * Registra solicitação de acesso em log.
     *
     * @param int    $client_id    ID do cliente.
     * @param string $client_name  Nome do cliente.
     * @param string $client_phone Telefone do cliente.
     * @param string $ip           IP do solicitante.
     */
    private function log_access_request( $client_id, $client_name, $client_phone, $ip ) {
        if ( function_exists( 'dps_log' ) ) {
            dps_log( 'Portal access requested', [
                'client_id'    => $client_id,
                'client_name'  => $client_name,
                'client_phone' => $client_phone,
                'ip'           => $ip,
            ], 'info', 'client-portal' );
        }
    }

    /**
     * Notifica admin sobre solicitação de acesso.
     *
     * @param int    $client_id    ID do cliente.
     * @param string $client_name  Nome do cliente.
     * @param string $client_phone Telefone do cliente.
     * @param string $ip           IP do solicitante.
     */
    private function notify_access_request( $client_id, $client_name, $client_phone, $ip ) {
        if ( class_exists( 'DPS_Communications_API' ) && method_exists( 'DPS_Communications_API', 'notify_admin_portal_access_requested' ) ) {
            DPS_Communications_API::notify_admin_portal_access_requested( $client_id, $client_name, $client_phone );
        } else {
            // Fallback: cria uma mensagem no portal para o admin ver
            $this->create_access_request_message( $client_id, $client_name, $client_phone, $ip );
        }
    }

    /**
     * Cria mensagem de solicitação de acesso como fallback.
     *
     * @param int    $client_id    ID do cliente.
     * @param string $client_name  Nome do cliente.
     * @param string $client_phone Telefone do cliente.
     * @param string $ip           IP do solicitante.
     */
    private function create_access_request_message( $client_id, $client_name, $client_phone, $ip ) {
        $message_title = sprintf(
            __( 'Solicitação de Acesso ao Portal - %s', 'dps-client-portal' ),
            $client_name ? $client_name : __( 'Cliente não identificado', 'dps-client-portal' )
        );
        
        $message_content = sprintf(
            __( "Nova solicitação de acesso ao portal:\n\nNome: %s\nTelefone: %s\nIP: %s\nData: %s", 'dps-client-portal' ),
            $client_name ? $client_name : __( 'Não informado', 'dps-client-portal' ),
            $client_phone ? $client_phone : __( 'Não informado', 'dps-client-portal' ),
            $ip,
            current_time( 'mysql' )
        );
        
        // Cria post de notificação para admin
        wp_insert_post( [
            'post_type'    => 'dps_portal_message',
            'post_title'   => $message_title,
            'post_content' => $message_content,
            'post_status'  => 'publish',
            'meta_input'   => [
                'message_client_id' => $client_id ? $client_id : 0,
                'message_sender'    => 'system',
                'message_type'      => 'access_request',
            ],
        ] );
    }
}
