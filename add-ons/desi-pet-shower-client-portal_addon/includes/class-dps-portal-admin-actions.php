<?php
/**
 * Gerenciador de ações administrativas para tokens do portal
 *
 * Esta classe processa todas as ações administrativas relacionadas aos tokens
 * de acesso do portal: geração, revogação, envio por WhatsApp e e-mail.
 *
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Admin_Actions' ) ) :

/**
 * Classe responsável pelas ações administrativas de tokens
 */
final class DPS_Portal_Admin_Actions {

    /**
     * Única instância da classe
     *
     * @var DPS_Portal_Admin_Actions|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Portal_Admin_Actions
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para singleton
     */
    private function __construct() {
        // Processa ações de token via query params
        add_action( 'init', [ $this, 'handle_token_actions' ], 10 );
        
        // Registra endpoints AJAX
        add_action( 'wp_ajax_dps_generate_client_token', [ $this, 'ajax_generate_token' ] );
        add_action( 'wp_ajax_dps_revoke_client_tokens', [ $this, 'ajax_revoke_tokens' ] );
        add_action( 'wp_ajax_dps_get_whatsapp_message', [ $this, 'ajax_get_whatsapp_message' ] );
        add_action( 'wp_ajax_dps_preview_email', [ $this, 'ajax_preview_email' ] );
        add_action( 'wp_ajax_dps_send_email_with_token', [ $this, 'ajax_send_email' ] );
    }

    /**
     * Processa ações de token via GET
     */
    public function handle_token_actions() {
        // Somente usuários com permissão podem executar ações
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Gerar token
        if ( isset( $_GET['dps_action'], $_GET['client_id'], $_GET['_wpnonce'] ) && 'generate_token' === $_GET['dps_action'] ) {
            $this->handle_generate_token();
            return;
        }

        // Revogar tokens
        if ( isset( $_GET['dps_action'], $_GET['client_id'], $_GET['_wpnonce'] ) && 'revoke_tokens' === $_GET['dps_action'] ) {
            $this->handle_revoke_tokens();
            return;
        }

        // Copiar link para WhatsApp
        if ( isset( $_GET['dps_action'], $_GET['client_id'], $_GET['_wpnonce'] ) && 'whatsapp_link' === $_GET['dps_action'] ) {
            $this->handle_whatsapp_link();
            return;
        }
    }

    /**
     * Gera um novo token para o cliente
     */
    private function handle_generate_token() {
        $client_id = absint( $_GET['client_id'] );
        $nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
        $type      = isset( $_GET['token_type'] ) ? sanitize_text_field( wp_unslash( $_GET['token_type'] ) ) : 'login';

        // Valida nonce
        if ( ! wp_verify_nonce( $nonce, 'dps_generate_token_' . $client_id ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança.', 'dps-client-portal' ) );
        }

        // Valida client_id
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_die( esc_html__( 'Cliente inválido.', 'dps-client-portal' ) );
        }

        $token_manager = DPS_Portal_Token_Manager::get_instance();
        
        // Se for regeneração, revoga tokens antigos
        if ( 'regenerate' === $type ) {
            $token_manager->revoke_tokens( $client_id );
            $token_type = 'login';
        } else {
            $token_type = $type;
        }

        // Gera novo token
        $token_plain = $token_manager->generate_token( $client_id, $token_type );

        if ( false === $token_plain ) {
            wp_die( esc_html__( 'Não foi possível gerar o token.', 'dps-client-portal' ) );
        }

        // Gera URL de acesso
        $access_url = $token_manager->generate_access_url( $token_plain );

        // Armazena temporariamente para exibição
        set_transient( 'dps_portal_generated_token_' . $client_id, [
            'token' => $token_plain,
            'url'   => $access_url,
            'type'  => $token_type,
        ], 5 * MINUTE_IN_SECONDS );

        // Redireciona de volta
        $redirect_url = $this->get_redirect_url();
        $redirect_url = add_query_arg( [
            'dps_token_generated' => '1',
            'client_id'           => $client_id,
        ], $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Revoga todos os tokens ativos do cliente
     */
    private function handle_revoke_tokens() {
        $client_id = absint( $_GET['client_id'] );
        $nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        // Valida nonce
        if ( ! wp_verify_nonce( $nonce, 'dps_revoke_tokens_' . $client_id ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança.', 'dps-client-portal' ) );
        }

        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $revoked_count = $token_manager->revoke_tokens( $client_id );

        // Redireciona de volta
        $redirect_url = $this->get_redirect_url();
        $redirect_url = add_query_arg( [
            'dps_tokens_revoked' => $revoked_count !== false ? $revoked_count : 0,
            'client_id'          => $client_id,
        ], $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Prepara link para WhatsApp
     */
    private function handle_whatsapp_link() {
        $client_id = absint( $_GET['client_id'] );
        $nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        // Valida nonce
        if ( ! wp_verify_nonce( $nonce, 'dps_whatsapp_link_' . $client_id ) ) {
            wp_die( esc_html__( 'Falha na verificação de segurança.', 'dps-client-portal' ) );
        }

        // Recupera token gerado
        $token_data = get_transient( 'dps_portal_generated_token_' . $client_id );
        if ( ! $token_data || ! isset( $token_data['url'] ) ) {
            wp_die( esc_html__( 'Token não encontrado. Gere um novo token primeiro.', 'dps-client-portal' ) );
        }

        // Monta mensagem
        $client_name = get_the_title( $client_id );
        $phone       = get_post_meta( $client_id, 'client_phone', true );
        $access_url  = $token_data['url'];

        // Busca primeiro pet do cliente para personalizar
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'owner_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        $pet_name = $pets ? get_the_title( $pets[0]->ID ) : '';

        $message = $this->get_whatsapp_message_template( $client_name, $pet_name, $access_url );

        // Gera link do WhatsApp usando helper centralizado
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_client( $phone, $message );
        } else {
            // Fallback para compatibilidade
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $phone_clean = DPS_Phone_Helper::format_for_whatsapp( $phone );
            } else {
                $phone_clean = preg_replace( '/\D/', '', $phone );
            }
            $whatsapp_url = 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode( $message );
        }

        // Redireciona para WhatsApp
        wp_safe_redirect( $whatsapp_url );
        exit;
    }

    /**
     * AJAX: Gera token
     */
    public function ajax_generate_token() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $type      = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'login';

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido.', 'dps-client-portal' ) ] );
        }

        $token_manager = DPS_Portal_Token_Manager::get_instance();
        
        // Se for regeneração, revoga tokens antigos
        if ( 'regenerate' === $type ) {
            $token_manager->revoke_tokens( $client_id );
            $token_type = 'login';
        } else {
            $token_type = $type;
        }

        $token_plain = $token_manager->generate_token( $client_id, $token_type );

        if ( false === $token_plain ) {
            wp_send_json_error( [ 'message' => __( 'Não foi possível gerar o token.', 'dps-client-portal' ) ] );
        }

        $access_url = $token_manager->generate_access_url( $token_plain );

        wp_send_json_success( [
            'token' => $token_plain,
            'url'   => $access_url,
            'type'  => $token_type,
        ] );
    }

    /**
     * AJAX: Revoga tokens
     */
    public function ajax_revoke_tokens() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

        if ( ! $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido.', 'dps-client-portal' ) ] );
        }

        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $revoked_count = $token_manager->revoke_tokens( $client_id );

        wp_send_json_success( [
            'revoked_count' => $revoked_count !== false ? $revoked_count : 0,
        ] );
    }

    /**
     * AJAX: Retorna mensagem formatada para WhatsApp
     */
    public function ajax_get_whatsapp_message() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-client-portal' ) ] );
        }

        $client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $access_url = isset( $_POST['access_url'] ) ? esc_url_raw( wp_unslash( $_POST['access_url'] ) ) : '';

        if ( ! $client_id || ! $access_url ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        $client_name = get_the_title( $client_id );
        
        // Busca primeiro pet
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'owner_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        $pet_name = $pets ? get_the_title( $pets[0]->ID ) : '';

        $message = $this->get_whatsapp_message_template( $client_name, $pet_name, $access_url );

        wp_send_json_success( [ 'message' => $message ] );
    }

    /**
     * AJAX: Pré-visualiza e-mail
     */
    public function ajax_preview_email() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-client-portal' ) ] );
        }

        $client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $access_url = isset( $_POST['access_url'] ) ? esc_url_raw( wp_unslash( $_POST['access_url'] ) ) : '';

        if ( ! $client_id || ! $access_url ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        $client_name = get_the_title( $client_id );
        $email_data  = $this->get_email_template( $client_name, $access_url );

        wp_send_json_success( $email_data );
    }

    /**
     * AJAX: Envia e-mail com token
     */
    public function ajax_send_email() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
        $subject   = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body      = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

        if ( ! $client_id || ! $subject || ! $body ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        $client_email = get_post_meta( $client_id, 'client_email', true );

        if ( ! $client_email || ! is_email( $client_email ) ) {
            wp_send_json_error( [ 'message' => __( 'E-mail do cliente não encontrado ou inválido.', 'dps-client-portal' ) ] );
        }

        // Envia e-mail (formato plain text para segurança)
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent = wp_mail( $client_email, $subject, $body, $headers );

        if ( $sent ) {
            wp_send_json_success( [ 'message' => __( 'E-mail enviado com sucesso!', 'dps-client-portal' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Falha ao enviar e-mail.', 'dps-client-portal' ) ] );
        }
    }

    /**
     * Retorna template de mensagem para WhatsApp
     *
     * @param string $client_name Nome do cliente
     * @param string $pet_name    Nome do pet
     * @param string $access_url  URL de acesso
     * @return string Mensagem formatada
     */
    private function get_whatsapp_message_template( $client_name, $pet_name, $access_url ) {
        if ( $pet_name ) {
            $message = sprintf(
                __( "Oi, %s! Aqui está seu link de acesso ao Portal do Cliente para acompanhar os agendamentos e histórico do %s:\n\n%s\n\nO link é válido por 30 minutos.", 'dps-client-portal' ),
                $client_name,
                $pet_name,
                $access_url
            );
        } else {
            $message = sprintf(
                __( "Oi, %s! Aqui está seu link de acesso ao Portal do Cliente:\n\n%s\n\nO link é válido por 30 minutos.", 'dps-client-portal' ),
                $client_name,
                $access_url
            );
        }

        return $message;
    }

    /**
     * Retorna template de e-mail
     *
     * @param string $client_name Nome do cliente
     * @param string $access_url  URL de acesso
     * @return array Array com 'subject' e 'body'
     */
    private function get_email_template( $client_name, $access_url ) {
        $subject = sprintf(
            __( 'Seu acesso ao Portal do Cliente - Desi Pet Shower', 'dps-client-portal' )
        );

        $body = sprintf(
            __( "Olá, %s!\n\nAqui está seu link de acesso ao Portal do Cliente da Desi Pet Shower.\n\nNo portal você pode:\n- Consultar seus agendamentos\n- Ver o histórico de atendimentos\n- Visualizar fotos do seu pet\n- Atualizar seus dados\n\nClique no link abaixo para acessar:\n%s\n\nO link é válido por 30 minutos.\n\nQualquer dúvida, estamos à disposição!\n\nEquipe Desi Pet Shower", 'dps-client-portal' ),
            $client_name,
            $access_url
        );

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    /**
     * Retorna URL de redirecionamento
     *
     * @return string URL base
     */
    private function get_redirect_url() {
        $referer = wp_get_referer();
        
        if ( $referer ) {
            return remove_query_arg( 
                [ 'dps_action', 'client_id', 'token_type', '_wpnonce', 'dps_token_generated', 'dps_tokens_revoked' ],
                $referer
            );
        }

        // Fallback
        $page_id = get_queried_object_id();
        if ( $page_id ) {
            return add_query_arg( 'tab', 'logins', get_permalink( $page_id ) );
        }

        return home_url();
    }
}

endif;
