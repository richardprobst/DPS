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
        add_action( 'wp_ajax_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
        add_action( 'wp_ajax_nopriv_dps_request_portal_access', [ $this, 'ajax_request_portal_access' ] );
        add_action( 'wp_ajax_dps_create_appointment_request', [ $this, 'ajax_create_appointment_request' ] ); // Fase 4
        add_action( 'wp_ajax_nopriv_dps_create_appointment_request', [ $this, 'ajax_create_appointment_request' ] ); // Fase 4
        add_action( 'wp_ajax_dps_loyalty_get_history', [ $this, 'ajax_get_loyalty_history' ] );
        add_action( 'wp_ajax_nopriv_dps_loyalty_get_history', [ $this, 'ajax_get_loyalty_history' ] );
        add_action( 'wp_ajax_dps_loyalty_portal_redeem', [ $this, 'ajax_redeem_loyalty_points' ] );
        add_action( 'wp_ajax_nopriv_dps_loyalty_portal_redeem', [ $this, 'ajax_redeem_loyalty_points' ] );

        // AJAX handler para auto-envio de link de acesso por email
        add_action( 'wp_ajax_dps_request_access_link_by_email', [ $this, 'ajax_request_access_link_by_email' ] );
        add_action( 'wp_ajax_nopriv_dps_request_access_link_by_email', [ $this, 'ajax_request_access_link_by_email' ] );

        // AJAX handler para export PDF do histórico do pet
        add_action( 'wp_ajax_dps_export_pet_history_pdf', [ $this, 'ajax_export_pet_history_pdf' ] );
        add_action( 'wp_ajax_nopriv_dps_export_pet_history_pdf', [ $this, 'ajax_export_pet_history_pdf' ] );

        add_action( 'wp_ajax_dps_load_more_pet_history', [ $this, 'ajax_load_more_pet_history' ] );
        add_action( 'wp_ajax_nopriv_dps_load_more_pet_history', [ $this, 'ajax_load_more_pet_history' ] );
    }

    /**
     * Valida requisição de chat e retorna client_id.
     *
     * @return int Client ID ou encerra com erro.
     */
    private function validate_chat_request() {
        // Verifica nonce usando helper
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_portal_chat' ) ) {
            return 0;
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
     * Valida requisições relacionadas à fidelidade no portal.
     *
     * @return int Client ID ou encerra com erro.
     */
    private function validate_loyalty_request() {
        // Verifica nonce usando helper
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_portal_loyalty' ) ) {
            return 0;
        }

        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Cliente não autenticado', 'dps-client-portal' ) ], 403 );
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
     * Retorna histórico paginado de pontos para o portal.
     */
    public function ajax_get_loyalty_history() {
        if ( ! class_exists( 'DPS_Loyalty_API' ) ) {
            wp_send_json_error( [ 'message' => __( 'Fidelidade indisponível.', 'dps-client-portal' ) ], 500 );
        }

        $client_id = $this->validate_loyalty_request();

        $limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;
        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

        $history    = DPS_Loyalty_API::get_points_history( $client_id, [ 'limit' => $limit, 'offset' => $offset ] );
        $total_logs = count( get_post_meta( $client_id, 'dps_loyalty_points_log' ) );
        $items      = [];

        foreach ( $history as $entry ) {
            $items[] = [
                'context' => DPS_Loyalty_API::get_context_label( $entry['context'] ),
                'date'    => date_i18n( 'd/m/Y H:i', strtotime( $entry['date'] ) ),
                'action'  => $entry['action'],
                'points'  => (int) $entry['points'],
            ];
        }

        $next_offset = $offset + $limit;

        wp_send_json_success( [
            'items'       => $items,
            'has_more'    => $next_offset < $total_logs,
            'next_offset' => $next_offset,
        ] );
    }

    /**
     * Permite resgate de pontos via portal.
     */
    public function ajax_redeem_loyalty_points() {
        if ( ! class_exists( 'DPS_Loyalty_API' ) ) {
            wp_send_json_error( [ 'message' => __( 'Fidelidade indisponível.', 'dps-client-portal' ) ], 500 );
        }

        $client_id = $this->validate_loyalty_request();

        $points_to_redeem = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;
        $settings         = get_option( 'dps_loyalty_settings', [] );

        if ( empty( $settings['enable_portal_redemption'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Resgate pelo portal está desativado no momento.', 'dps-client-portal' ) ] );
        }

        if ( $points_to_redeem <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Informe uma quantidade válida de pontos.', 'dps-client-portal' ) ] );
        }

        $current_points      = DPS_Loyalty_API::get_points( $client_id );
        $portal_min_points   = isset( $settings['portal_min_points_to_redeem'] ) ? absint( $settings['portal_min_points_to_redeem'] ) : 0;
        $points_per_real     = isset( $settings['portal_points_per_real'] ) ? max( 1, absint( $settings['portal_points_per_real'] ) ) : 100;
        $max_discount_cents  = isset( $settings['portal_max_discount_amount'] ) ? (int) $settings['portal_max_discount_amount'] : 0;
        $max_points_by_cap   = $max_discount_cents > 0 ? (int) floor( ( $max_discount_cents / 100 ) * $points_per_real ) : $current_points;

        if ( $portal_min_points > 0 && $points_to_redeem < $portal_min_points ) {
            wp_send_json_error( [ 'message' => sprintf( __( 'Resgate mínimo de %d pontos.', 'dps-client-portal' ), $portal_min_points ) ] );
        }

        if ( $points_to_redeem > $current_points ) {
            wp_send_json_error( [ 'message' => __( 'Você não possui pontos suficientes.', 'dps-client-portal' ) ] );
        }

        if ( $points_to_redeem > $max_points_by_cap ) {
            wp_send_json_error( [ 'message' => sprintf( __( 'O máximo permitido por resgate é %d pontos.', 'dps-client-portal' ), $max_points_by_cap ) ] );
        }

        $discount_cents = (int) floor( ( $points_to_redeem / $points_per_real ) * 100 );

        if ( $discount_cents <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Não foi possível converter os pontos em crédito.', 'dps-client-portal' ) ] );
        }

        $redeemed = DPS_Loyalty_API::redeem_points( $client_id, $points_to_redeem, 'portal_redemption' );

        if ( false === $redeemed ) {
            wp_send_json_error( [ 'message' => __( 'Não foi possível resgatar seus pontos.', 'dps-client-portal' ) ] );
        }

        $new_credit = DPS_Loyalty_API::add_credit( $client_id, $discount_cents, 'portal_redemption' );
        $new_points = DPS_Loyalty_API::get_points( $client_id );

        $credit_display = DPS_Money_Helper::format_currency( $new_credit );

        $message = sprintf(
            __( 'Você converteu %1$d pontos em %2$s de crédito. 🎉', 'dps-client-portal' ),
            $points_to_redeem,
            $credit_display
        );

        wp_send_json_success( [
            'points'  => $new_points,
            'credit'  => $credit_display,
            'message' => $message,
        ] );
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
     * Usa post meta no registro do cliente para persistir contagem sem cache.
     *
     * @param int $client_id ID do cliente.
     * @param int $max_requests Máximo de requisições permitidas por minuto.
     * @return bool True se dentro do limite.
     */
    private function check_rate_limit( $client_id, $max_requests ) {
        $meta_key  = '_dps_chat_rate';
        $rate_data = get_post_meta( $client_id, $meta_key, true );
        $now       = time();

        // Reseta se dados inválidos ou janela de 60s expirou
        if ( ! is_array( $rate_data ) || empty( $rate_data['start'] ) || ( $now - $rate_data['start'] ) > 60 ) {
            update_post_meta( $client_id, $meta_key, [ 'start' => $now, 'count' => 1 ] );
            return true;
        }

        if ( $rate_data['count'] >= $max_requests ) {
            return false;
        }

        $rate_data['count']++;
        update_post_meta( $client_id, $meta_key, $rate_data );
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

    /**
     * AJAX handler para criar pedido de agendamento (Fase 4).
     *
     * @since 2.4.0
     */
    public function ajax_create_appointment_request() {
        // Verifica nonce usando helper
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_portal_appointment_request' ) ) {
            return;
        }

        // Obtém client_id da sessão
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Cliente não autenticado', 'dps-client-portal' ) ] );
        }

        // Extrai dados do formulário (wp_unslash para remover magic quotes)
        $request_type            = isset( $_POST['request_type'] ) ? sanitize_key( wp_unslash( $_POST['request_type'] ) ) : 'new';
        $pet_id                  = isset( $_POST['pet_id'] ) ? absint( $_POST['pet_id'] ) : 0;
        $desired_date            = isset( $_POST['desired_date'] ) ? sanitize_text_field( wp_unslash( $_POST['desired_date'] ) ) : '';
        $desired_period          = isset( $_POST['desired_period'] ) ? sanitize_key( wp_unslash( $_POST['desired_period'] ) ) : '';
        $services                = isset( $_POST['services'] ) && is_array( $_POST['services'] ) ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['services'] ) ) : [];
        $original_appointment_id = isset( $_POST['original_appointment_id'] ) ? absint( $_POST['original_appointment_id'] ) : 0;
        $notes                   = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

        // Validações básicas
        if ( empty( $desired_date ) || empty( $desired_period ) ) {
            wp_send_json_error( [ 'message' => __( 'Data e período são obrigatórios', 'dps-client-portal' ) ] );
        }

        // Valida propriedade do pet
        if ( $pet_id ) {
            $pet_repo  = DPS_Pet_Repository::get_instance();
            $is_owner  = $pet_repo->pet_belongs_to_client( $pet_id, $client_id );
            if ( ! $is_owner ) {
                wp_send_json_error( [ 'message' => __( 'Pet inválido', 'dps-client-portal' ) ] );
            }
        }

        // Cria pedido
        $request_repo = DPS_Appointment_Request_Repository::get_instance();
        $request_id   = $request_repo->create_request( [
            'client_id'               => $client_id,
            'pet_id'                  => $pet_id,
            'request_type'            => $request_type,
            'desired_date'            => $desired_date,
            'desired_period'          => $desired_period,
            'services'                => $services,
            'original_appointment_id' => $original_appointment_id,
            'notes'                   => $notes,
        ] );

        if ( ! $request_id ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao criar pedido', 'dps-client-portal' ) ] );
        }

        // Mensagem de sucesso diferente por tipo
        $success_messages = [
            'new'        => __( 'Sua solicitação de agendamento foi enviada! A equipe do Banho e Tosa irá confirmar o horário com você em breve.', 'dps-client-portal' ),
            'reschedule' => __( 'Sua solicitação de reagendamento foi enviada! A equipe do Banho e Tosa irá confirmar o novo horário com você.', 'dps-client-portal' ),
            'cancel'     => __( 'Sua solicitação de cancelamento foi enviada! A equipe do Banho e Tosa pode entrar em contato caso necessário.', 'dps-client-portal' ),
        ];

        $message = isset( $success_messages[ $request_type ] ) ? $success_messages[ $request_type ] : $success_messages['new'];

        wp_send_json_success( [ 
            'message'    => $message,
            'request_id' => $request_id,
        ] );
    }

    /**
     * AJAX handler para solicitação de link de acesso por email (auto-envio).
     * 
     * Permite que clientes com email cadastrado solicitem o link de acesso
     * automaticamente. Para clientes sem email, orienta a usar WhatsApp.
     * 
     * Rate limiting: 3 solicitações por hora por IP e por email (ambos limites aplicados independentemente)
     * 
     * @since 2.4.3
     */
    public function ajax_request_access_link_by_email() {
        // Verifica nonce para proteção CSRF
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_request_access_link' ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Sessão expirada. Por favor, recarregue a página e tente novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Captura e valida email
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Por favor, informe um e-mail válido.', 'dps-client-portal' ) 
            ] );
        }
        
        // Rate limiting por IP (usa helper ou fallback)
        $ip = class_exists( 'DPS_IP_Helper' )
            ? DPS_IP_Helper::get_ip_with_proxy_support()
            : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0' );

        $rate_key_ip = 'dps_access_link_ip_' . md5( $ip );
        $rate_key_email = 'dps_access_link_email_' . md5( $email );
        
        // Verifica rate limit por IP (3 solicitações por hora)
        $ip_count = get_transient( $rate_key_ip );
        if ( false === $ip_count ) {
            $ip_count = 0;
        }
        
        if ( $ip_count >= 3 ) {
            // F6.3: FASE 6 - Audit log de rate limit atingido
            if ( class_exists( 'DPS_Audit_Logger' ) ) {
                DPS_Audit_Logger::log_portal_event( 'rate_limit_ip', 0, [ 'ip' => $ip, 'count' => $ip_count ] );
            }
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou o link várias vezes. Aguarde alguns minutos antes de tentar novamente.', 'dps-client-portal' ) 
            ] );
        }
        
        // Verifica rate limit por email (3 solicitações por hora)
        $email_count = get_transient( $rate_key_email );
        if ( false === $email_count ) {
            $email_count = 0;
        }
        
        if ( $email_count >= 3 ) {
            wp_send_json_error( [ 
                'message' => __( 'Você já solicitou o link várias vezes para este e-mail. Verifique sua caixa de entrada (e spam).', 'dps-client-portal' ) 
            ] );
        }
        
        // Busca cliente pelo email
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'client_email',
                    'value'   => $email,
                    'compare' => '=',
                ],
            ],
        ] );
        
        // Incrementa contadores de rate limit antes de verificar resultado
        // (evita brute force para descobrir emails cadastrados)
        set_transient( $rate_key_ip, $ip_count + 1, HOUR_IN_SECONDS );
        set_transient( $rate_key_email, $email_count + 1, HOUR_IN_SECONDS );
        
        if ( empty( $clients ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Não encontramos um cadastro com este e-mail. Por favor, entre em contato via WhatsApp para solicitar acesso.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        $client_id = $clients[0];
        $client_name = get_the_title( $client_id );
        
        // F4.6: FASE 4 - Verifica se cliente quer manter acesso permanente
        $remember_me = isset( $_POST['remember_me'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remember_me'] ) );
        
        // Gera token de acesso
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_plain   = $token_manager->generate_token( $client_id, 'login' );
        
        if ( false === $token_plain ) {
            wp_send_json_error( [ 
                'message' => __( 'Não foi possível gerar o link de acesso. Por favor, tente novamente ou entre em contato via WhatsApp.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        // Gera URL de acesso (com flag remember se solicitado)
        $access_url = $token_manager->generate_access_url( $token_plain );
        if ( $remember_me ) {
            $access_url = add_query_arg( 'dps_remember', '1', $access_url );
        }
        
        // Monta email HTML moderno
        $safe_client_name = wp_strip_all_tags( $client_name );
        $subject = __( 'Seu link de acesso ao Portal do Cliente - desi.pet by PRObst', 'dps-client-portal' );
        
        $site_name = get_bloginfo( 'name' );
        
        // Template HTML do email
        $body = $this->get_access_link_email_html( $safe_client_name, $access_url, $site_name );
        
        // Envia email
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $comm_api = DPS_Communications_API::get_instance();
            $sent     = $comm_api->send_email( 
                $email, 
                $subject, 
                $body, 
                [
                    'type'      => 'portal_access_link',
                    'client_id' => $client_id,
                ]
            );
        } else {
            $sent = wp_mail( $email, $subject, $body, $headers );
        }
        
        // Registra em log
        if ( function_exists( 'dps_log' ) ) {
            dps_log( 'Portal access link sent via email', [
                'client_id' => $client_id,
                'email'     => $email,
                'ip'        => $ip,
                'sent'      => $sent,
            ], 'info', 'client-portal' );
        }
        
        if ( ! $sent ) {
            wp_send_json_error( [ 
                'message' => __( 'Não foi possível enviar o e-mail. Por favor, tente novamente ou entre em contato via WhatsApp.', 'dps-client-portal' ),
                'show_whatsapp' => true
            ] );
        }
        
        wp_send_json_success( [ 
            'message' => __( 'Link enviado com sucesso! Verifique sua caixa de entrada (e a pasta de spam).', 'dps-client-portal' ) 
        ] );
    }

    /**
     * Gera o HTML do email de link de acesso ao portal.
     *
     * @since 2.4.4
     * @param string $client_name Nome do cliente (já sanitizado).
     * @param string $access_url  URL de acesso com token.
     * @param string $site_name   Nome do site.
     * @return string HTML do email.
     */
    private function get_access_link_email_html( $client_name, $access_url, $site_name ) {
        $escaped_url = esc_url( $access_url );
        $escaped_name = esc_html( $client_name );
        $escaped_site = esc_html( $site_name );
        
        $current_year = wp_date( 'Y' );
        $validity_text = esc_html__( 'Este link é válido por 30 minutos e pode ser usado apenas uma vez.', 'dps-client-portal' );
        $access_button_text = esc_html__( 'Acessar Meu Portal', 'dps-client-portal' );
        $security_note = esc_html__( 'Se você não solicitou este acesso, ignore este e-mail.', 'dps-client-portal' );
        $greeting = sprintf( esc_html__( 'Olá, %s!', 'dps-client-portal' ), $escaped_name );
        $intro_text = esc_html__( 'Você solicitou acesso ao Portal do Cliente. Clique no botão abaixo para acessar:', 'dps-client-portal' );
        $alt_link_text = esc_html__( 'Se o botão não funcionar, copie e cole este link no navegador:', 'dps-client-portal' );
        $footer_text = sprintf( esc_html__( 'Equipe %s', 'dps-client-portal' ), $escaped_site );
        
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$escaped_site}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                    <tr>
                        <td style="padding: 40px 40px 24px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🐾</div>
                            <h1 style="margin: 0; font-size: 24px; font-weight: 600; color: #1f2937;">{$escaped_site}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #374151;">{$greeting}</p>
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #6b7280;">{$intro_text}</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%">
                                <tr>
                                    <td style="text-align: center; padding: 8px 0 24px;">
                                        <a href="{$escaped_url}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 16px 48px; border-radius: 12px; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);">{$access_button_text}</a>
                                    </td>
                                </tr>
                            </table>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <p style="margin: 0; font-size: 14px; color: #92400e; line-height: 1.5;">
                                            <strong>⏱️ Atenção:</strong> {$validity_text}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0 0 8px; font-size: 13px; color: #9ca3af;">{$alt_link_text}</p>
                            <p style="margin: 0; font-size: 12px; color: #0ea5e9; word-break: break-all;">
                                <a href="{$escaped_url}" style="color: #0ea5e9; text-decoration: underline;">{$escaped_url}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                                🔒 {$security_note}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px 40px; background: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280;">{$footer_text}</p>
                            <p style="margin: 8px 0 0; font-size: 12px; color: #9ca3af;">© {$current_year} {$escaped_site}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * AJAX handler para carregar mais itens do histórico de serviços do pet.
     *
     * @since 3.1.0
     */
    public function ajax_load_more_pet_history() {
        if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_portal_pet_history' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sessão expirada. Recarregue a página.', 'dps-client-portal' ) ] );
        }

        // Obtém client_id da sessão (não do POST, por segurança)
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $client_id       = $session_manager->get_authenticated_client_id();

        if ( ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Cliente não autenticado.', 'dps-client-portal' ) ], 403 );
        }

        $pet_id = isset( $_POST['pet_id'] ) ? absint( $_POST['pet_id'] ) : 0;
        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $limit  = 10;

        if ( 0 === $pet_id ) {
            wp_send_json_error( [ 'message' => __( 'Parâmetros inválidos.', 'dps-client-portal' ) ] );
        }

        // Verifica ownership do pet
        $pet_owner_id = get_post_meta( $pet_id, 'owner_id', true );
        if ( absint( $pet_owner_id ) !== $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Acesso não autorizado.', 'dps-client-portal' ) ] );
        }

        $pet_history = DPS_Portal_Pet_History::get_instance();
        // Busca todos os serviços e faz slice para paginação
        $all_services = $pet_history->get_pet_service_history( $pet_id, -1 );
        $services     = array_slice( $all_services, $offset, $limit );
        $has_more     = ( $offset + $limit ) < count( $all_services );

        // Captura HTML renderizado
        ob_start();
        $renderer = DPS_Portal_Renderer::get_instance();
        foreach ( $services as $service ) {
            $renderer->render_single_timeline_item( $service, $client_id, $pet_id );
        }
        $html = ob_get_clean();

        wp_send_json_success( [
            'html'      => $html,
            'hasMore'   => $has_more,
            'newOffset' => $offset + count( $services ),
        ] );
    }

    /**
     * AJAX handler para exportar histórico do pet em formato para impressão/PDF.
     *
     * @since 2.5.0
     */
    public function ajax_export_pet_history_pdf() {
        // Verifica nonce usando helper (GET)
        if ( ! DPS_Request_Validator::verify_admin_action( 'dps_portal_export_pdf', null, 'nonce', false ) ) {
            wp_die( esc_html__( 'Erro de segurança. Por favor, recarregue a página e tente novamente.', 'dps-client-portal' ), 403 );
        }

        // Obtém IDs
        $pet_id    = isset( $_GET['pet_id'] ) ? absint( $_GET['pet_id'] ) : 0;
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

        if ( 0 === $pet_id || 0 === $client_id ) {
            wp_die( esc_html__( 'Parâmetros inválidos.', 'dps-client-portal' ), 400 );
        }

        // Verifica se o pet pertence ao cliente (segurança)
        $pet_owner_id = get_post_meta( $pet_id, 'owner_id', true );
        if ( absint( $pet_owner_id ) !== $client_id ) {
            wp_die( esc_html__( 'Acesso não autorizado.', 'dps-client-portal' ), 403 );
        }

        // Renderiza página de impressão
        $renderer = DPS_Portal_Renderer::get_instance();
        $renderer->render_pet_history_print_page( $pet_id, $client_id );
        exit;
    }
}
