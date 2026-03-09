<?php
/**
 * Gerenciador de acoes administrativas para logins do portal.
 *
 * @package DPS_Client_Portal
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Admin_Actions' ) ) :

final class DPS_Portal_Admin_Actions {

    /**
     * Instancia unica.
     *
     * @var DPS_Portal_Admin_Actions|null
     */
    private static $instance = null;

    /**
     * Recupera a instancia unica.
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
     * Construtor privado para singleton.
     */
    private function __construct() {
        add_action( 'wp_ajax_dps_generate_client_token', [ $this, 'ajax_generate_token' ] );
        add_action( 'wp_ajax_dps_revoke_client_tokens', [ $this, 'ajax_revoke_tokens' ] );
        add_action( 'wp_ajax_dps_get_whatsapp_message', [ $this, 'ajax_get_whatsapp_message' ] );
        add_action( 'wp_ajax_dps_preview_email', [ $this, 'ajax_preview_email' ] );
        add_action( 'wp_ajax_dps_send_email_with_token', [ $this, 'ajax_send_email' ] );
        add_action( 'wp_ajax_dps_send_password_access_email', [ $this, 'ajax_send_password_access_email' ] );
        add_action( 'wp_ajax_dps_sync_portal_user', [ $this, 'ajax_sync_portal_user' ] );
    }

    /**
     * AJAX: gera um token de acesso para o cliente.
     *
     * @return void
     */
    public function ajax_generate_token() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $type      = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'login';

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente invalido.', 'dps-client-portal' ) ] );
        }

        if ( ! in_array( $type, [ 'login', 'permanent', 'regenerate' ], true ) ) {
            $type = 'login';
        }

        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_type    = 'permanent' === $type ? 'permanent' : 'login';

        if ( 'regenerate' === $type ) {
            $token_manager->revoke_tokens( $client_id );
        }

        $token_plain = $token_manager->generate_token( $client_id, $token_type );
        if ( false === $token_plain ) {
            wp_send_json_error( [ 'message' => __( 'Nao foi possivel gerar o link.', 'dps-client-portal' ) ] );
        }

        wp_send_json_success(
            [
                'token'         => $token_plain,
                'url'           => $token_manager->generate_access_url( $token_plain ),
                'type'          => $token_type,
                'validityLabel' => 'permanent' === $token_type
                    ? __( 'Link permanente - valido ate revogar manualmente.', 'dps-client-portal' )
                    : __( 'Link valido por 30 minutos.', 'dps-client-portal' ),
            ]
        );
    }

    /**
     * AJAX: revoga tokens ativos do cliente.
     *
     * @return void
     */
    public function ajax_revoke_tokens() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente invalido.', 'dps-client-portal' ) ] );
        }

        $revoked_count = DPS_Portal_Token_Manager::get_instance()->revoke_tokens( $client_id );

        wp_send_json_success(
            [
                'message'       => __( 'Links revogados com sucesso.', 'dps-client-portal' ),
                'revoked_count' => false !== $revoked_count ? absint( $revoked_count ) : 0,
            ]
        );
    }

    /**
     * AJAX: monta mensagem para WhatsApp.
     *
     * @return void
     */
    public function ajax_get_whatsapp_message() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id  = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $access_url = isset( $_POST['access_url'] ) ? esc_url_raw( wp_unslash( $_POST['access_url'] ) ) : '';

        if ( ! $client_id || ! $access_url ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        $client_name = get_the_title( $client_id );
        $pet_name    = $this->get_first_pet_name( $client_id );

        wp_send_json_success(
            [
                'message' => $this->get_whatsapp_message_template( $client_name, $pet_name, $access_url ),
            ]
        );
    }

    /**
     * AJAX: prepara o conteudo do e-mail com magic link.
     *
     * @return void
     */
    public function ajax_preview_email() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id  = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $access_url = isset( $_POST['access_url'] ) ? esc_url_raw( wp_unslash( $_POST['access_url'] ) ) : '';

        if ( ! $client_id || ! $access_url ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        wp_send_json_success( $this->get_email_template( get_the_title( $client_id ), $access_url ) );
    }

    /**
     * AJAX: envia e-mail com magic link.
     *
     * @return void
     */
    public function ajax_send_email() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $subject   = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body      = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

        if ( ! $client_id || '' === $subject || '' === $body ) {
            wp_send_json_error( [ 'message' => __( 'Dados incompletos.', 'dps-client-portal' ) ] );
        }

        $client_email = sanitize_email( get_post_meta( $client_id, 'client_email', true ) );
        if ( ! is_email( $client_email ) ) {
            wp_send_json_error( [ 'message' => __( 'E-mail do cliente nao encontrado ou invalido.', 'dps-client-portal' ) ] );
        }

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent    = wp_mail( $client_email, $subject, $body, $headers );

        if ( ! $sent ) {
            wp_send_json_error( [ 'message' => __( 'Falha ao enviar o e-mail.', 'dps-client-portal' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'E-mail enviado com sucesso!', 'dps-client-portal' ) ] );
    }

    /**
     * AJAX: envia e-mail para criar ou redefinir senha.
     *
     * @return void
     */
    public function ajax_send_password_access_email() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente invalido.', 'dps-client-portal' ) ] );
        }

        $result = DPS_Portal_User_Manager::get_instance()->send_password_access_email( $client_id, 'admin' );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success(
            [
                'message' => __( 'As instrucoes de senha foram enviadas para o e-mail cadastrado.', 'dps-client-portal' ),
                'summary' => DPS_Portal_User_Manager::get_instance()->get_access_summary( $client_id ),
            ]
        );
    }

    /**
     * AJAX: sincroniza ou cria o usuario WordPress vinculado ao cliente.
     *
     * @return void
     */
    public function ajax_sync_portal_user() {
        check_ajax_referer( 'dps_portal_admin_actions', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissao negada.', 'dps-client-portal' ) ] );
        }

        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente invalido.', 'dps-client-portal' ) ] );
        }

        $user = DPS_Portal_User_Manager::get_instance()->ensure_user_for_client( $client_id );
        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => $user->get_error_message() ] );
        }

        wp_send_json_success(
            [
                'message' => __( 'Usuario do portal sincronizado com sucesso.', 'dps-client-portal' ),
                'summary' => DPS_Portal_User_Manager::get_instance()->get_access_summary( $client_id ),
            ]
        );
    }

    /**
     * Busca o primeiro pet do cliente para contextualizar as mensagens.
     *
     * @param int $client_id ID do cliente.
     * @return string
     */
    private function get_first_pet_name( $client_id ) {
        $pets = get_posts(
            [
                'post_type'      => 'dps_pet',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => 'owner_id',
                        'value' => absint( $client_id ),
                    ],
                ],
            ]
        );

        return ! empty( $pets ) ? get_the_title( (int) $pets[0] ) : '';
    }

    /**
     * Retorna template de mensagem para WhatsApp.
     *
     * @param string $client_name Nome do cliente.
     * @param string $pet_name Nome do pet.
     * @param string $access_url URL de acesso.
     * @return string
     */
    private function get_whatsapp_message_template( $client_name, $pet_name, $access_url ) {
        if ( $pet_name ) {
            return sprintf(
                __( "Oi, %1$s! Aqui esta seu link de acesso ao Portal do Cliente para acompanhar os agendamentos e historico do %2$s:\n\n%3$s\n\nO link e valido por 30 minutos.", 'dps-client-portal' ),
                $client_name,
                $pet_name,
                $access_url
            );
        }

        return sprintf(
            __( "Oi, %1$s! Aqui esta seu link de acesso ao Portal do Cliente:\n\n%2$s\n\nO link e valido por 30 minutos.", 'dps-client-portal' ),
            $client_name,
            $access_url
        );
    }

    /**
     * Retorna template de e-mail para magic link.
     *
     * @param string $client_name Nome do cliente.
     * @param string $access_url URL de acesso.
     * @return array<string, string>
     */
    private function get_email_template( $client_name, $access_url ) {
        return [
            'subject' => __( 'Seu acesso ao Portal do Cliente - desi.pet by PRObst', 'dps-client-portal' ),
            'body'    => sprintf(
                __( "Ola, %1$s!\n\nAqui esta seu link de acesso ao Portal do Cliente da desi.pet by PRObst.\n\nNo portal voce pode:\n- Consultar seus agendamentos\n- Ver o historico de atendimentos\n- Visualizar fotos do seu pet\n- Atualizar seus dados\n\nClique no link abaixo para acessar:\n%2$s\n\nO link e valido por 30 minutos.\n\nQualquer duvida, estamos a disposicao!\n\nEquipe desi.pet by PRObst", 'dps-client-portal' ),
                $client_name,
                $access_url
            ),
        ];
    }
}

endif;
