<?php
/**
 * Gerencia contas WordPress vinculadas aos clientes do portal.
 *
 * @package DPS_Client_Portal
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_User_Manager' ) ) :

final class DPS_Portal_User_Manager {

    /**
     * Chave do meta do cliente com o último login por senha.
     *
     * @var string
     */
    private const META_LAST_PASSWORD_LOGIN = 'dps_portal_last_password_login_at';

    /**
     * Chave do meta do cliente com o último envio de acesso por senha.
     *
     * @var string
     */
    private const META_LAST_PASSWORD_EMAIL = 'dps_portal_password_access_sent_at';

    /**
     * Instância única.
     *
     * @var DPS_Portal_User_Manager|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Portal_User_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Garante que o cliente tenha uma conta WordPress vinculada ao e-mail cadastrado.
     *
     * @param int $client_id ID do cliente.
     * @return WP_User|WP_Error
     */
    public function ensure_user_for_client( $client_id ) {
        $client_id = absint( $client_id );

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return new WP_Error( 'invalid_client', __( 'Cliente inválido para ativação do login por senha.', 'dps-client-portal' ) );
        }

        $email = $this->get_client_email( $client_id );
        if ( ! $email ) {
            return new WP_Error( 'missing_email', __( 'Cadastre um e-mail válido no cliente para habilitar o login por senha.', 'dps-client-portal' ) );
        }

        $summary = $this->get_access_summary( $client_id );
        if ( 'conflict' === $summary['status'] ) {
            return new WP_Error( 'email_conflict', __( 'Este e-mail já está em uso por outro acesso do portal. Revise o cadastro antes de continuar.', 'dps-client-portal' ) );
        }

        $display_name = get_the_title( $client_id );
        $user         = $this->get_user_for_client( $client_id );

        if ( $user instanceof WP_User ) {
            $update_data = [
                'ID'           => $user->ID,
                'display_name' => $display_name,
            ];

            if ( strtolower( (string) $user->user_email ) !== strtolower( $email ) ) {
                $update_data['user_email'] = $email;
            }

            $updated_user_id = wp_update_user( $update_data );
            if ( is_wp_error( $updated_user_id ) ) {
                return $updated_user_id;
            }

            $user = get_userdata( $user->ID );
        } else {
            $user_id = wp_insert_user(
                [
                    'user_login'   => $email,
                    'user_email'   => $email,
                    'user_pass'    => wp_generate_password( 24, true, true ),
                    'display_name' => $display_name,
                    'role'         => 'subscriber',
                ]
            );

            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }

            $user = get_userdata( $user_id );
        }

        if ( ! $user instanceof WP_User ) {
            return new WP_Error( 'user_not_available', __( 'Não foi possível preparar o usuário do portal para este cliente.', 'dps-client-portal' ) );
        }

        update_user_meta( $user->ID, 'dps_client_id', $client_id );
        update_post_meta( $client_id, 'client_user_id', $user->ID );

        if ( ! get_post_meta( $client_id, 'client_login_created_at', true ) ) {
            update_post_meta( $client_id, 'client_login_created_at', current_time( 'mysql' ) );
        }

        return $user;
    }

    /**
     * Recupera o usuário vinculado ao cliente.
     *
     * @param int $client_id ID do cliente.
     * @return WP_User|null
     */
    public function get_user_for_client( $client_id ) {
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return null;
        }

        $linked_user_id = absint( get_post_meta( $client_id, 'client_user_id', true ) );
        if ( $linked_user_id > 0 ) {
            $linked_user = get_userdata( $linked_user_id );
            if ( $linked_user instanceof WP_User ) {
                return $linked_user;
            }
        }

        $email = $this->get_client_email( $client_id );
        if ( ! $email ) {
            return null;
        }

        $email_user = get_user_by( 'email', $email );
        if ( $email_user instanceof WP_User ) {
            return $email_user;
        }

        return null;
    }

    /**
     * Obtém o e-mail válido do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string
     */
    public function get_client_email( $client_id ) {
        $email = sanitize_email( get_post_meta( absint( $client_id ), 'client_email', true ) );

        return is_email( $email ) ? $email : '';
    }

    /**
     * Busca cliente pelo e-mail cadastrado.
     *
     * @param string $email E-mail do cliente.
     * @return int
     */
    public function find_client_id_by_email( $email ) {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return 0;
        }

        $clients = get_posts(
            [
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
            ]
        );

        return ! empty( $clients ) ? absint( $clients[0] ) : 0;
    }

    /**
     * Retorna resumo do estado do login por senha.
     *
     * @param int $client_id ID do cliente.
     * @return array<string, mixed>
     */
    public function get_access_summary( $client_id ) {
        $client_id = absint( $client_id );
        $email     = $this->get_client_email( $client_id );
        $summary   = [
            'email'                    => $email,
            'status'                   => 'missing_email',
            'status_label'             => __( 'Sem e-mail', 'dps-client-portal' ),
            'status_description'       => __( 'Cadastre um e-mail válido para liberar o login por senha.', 'dps-client-portal' ),
            'can_use_password'         => false,
            'can_send_password_email'  => false,
            'needs_sync'               => false,
            'legacy_username'          => false,
            'user_id'                  => 0,
            'user_login'               => '',
            'user_email'               => '',
            'last_password_login_at'   => (string) get_post_meta( $client_id, self::META_LAST_PASSWORD_LOGIN, true ),
            'last_password_email_sent' => (string) get_post_meta( $client_id, self::META_LAST_PASSWORD_EMAIL, true ),
        ];

        if ( ! $email ) {
            return $summary;
        }

        $linked_user_id = absint( get_post_meta( $client_id, 'client_user_id', true ) );
        $linked_user    = $linked_user_id > 0 ? get_userdata( $linked_user_id ) : false;
        $email_user     = get_user_by( 'email', $email );

        if ( $email_user instanceof WP_User && $this->user_belongs_to_other_client( $email_user->ID, $client_id ) ) {
            $summary['status']             = 'conflict';
            $summary['status_label']       = __( 'Conflito de e-mail', 'dps-client-portal' );
            $summary['status_description'] = __( 'Este e-mail já está sendo usado em outro acesso do portal.', 'dps-client-portal' );
            return $summary;
        }

        if ( $linked_user instanceof WP_User && $email_user instanceof WP_User && $linked_user->ID !== $email_user->ID ) {
            $summary['status']             = 'conflict';
            $summary['status_label']       = __( 'Conflito de conta', 'dps-client-portal' );
            $summary['status_description'] = __( 'O cliente está vinculado a um usuário diferente do e-mail cadastrado.', 'dps-client-portal' );
            $summary['user_id']            = $linked_user->ID;
            $summary['user_login']         = (string) $linked_user->user_login;
            $summary['user_email']         = (string) $linked_user->user_email;
            return $summary;
        }

        $user = $linked_user instanceof WP_User ? $linked_user : ( $email_user instanceof WP_User ? $email_user : null );

        if ( ! $user instanceof WP_User ) {
            $summary['status']                   = 'provisionable';
            $summary['status_label']             = __( 'Pronto para ativar', 'dps-client-portal' );
            $summary['status_description']       = __( 'O e-mail já permite criar o acesso por senha para este cliente.', 'dps-client-portal' );
            $summary['can_use_password']         = true;
            $summary['can_send_password_email']  = true;
            $summary['needs_sync']               = true;
            return $summary;
        }

        $summary['user_id']                 = $user->ID;
        $summary['user_login']              = (string) $user->user_login;
        $summary['user_email']              = (string) $user->user_email;
        $summary['status']                  = 'ready';
        $summary['status_label']            = __( 'Ativo por e-mail', 'dps-client-portal' );
        $summary['status_description']      = __( 'O cliente pode entrar com o e-mail cadastrado e a própria senha.', 'dps-client-portal' );
        $summary['can_use_password']        = true;
        $summary['can_send_password_email'] = true;
        $summary['needs_sync']              = absint( get_user_meta( $user->ID, 'dps_client_id', true ) ) !== $client_id || $linked_user_id !== $user->ID || strtolower( (string) $user->user_email ) !== strtolower( $email );
        $summary['legacy_username']         = strtolower( (string) $user->user_login ) !== strtolower( $email );

        return $summary;
    }

    /**
     * Envia o e-mail de ativação/redefinição de senha.
     *
     * @param int    $client_id ID do cliente.
     * @param string $context Contexto do envio.
     * @return true|WP_Error
     */
    public function send_password_access_email( $client_id, $context = 'admin' ) {
        $user = $this->ensure_user_for_client( $client_id );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        $reset_url = $this->get_password_reset_url( $user );
        if ( is_wp_error( $reset_url ) ) {
            return $reset_url;
        }

        $email = $this->get_client_email( $client_id );
        if ( ! $email ) {
            return new WP_Error( 'missing_email', __( 'Cadastre um e-mail válido no cliente para enviar as instruções.', 'dps-client-portal' ) );
        }

        $client_name = get_the_title( $client_id );
        $site_name   = get_bloginfo( 'name' );
        $subject     = sprintf(
            /* translators: %s: nome do site */
            __( 'Ative seu acesso por e-mail e senha no portal - %s', 'dps-client-portal' ),
            $site_name
        );
        $body        = $this->build_password_access_email_html( $client_name, $email, $reset_url, $site_name, $context );
        $headers     = [ 'Content-Type: text/html; charset=UTF-8' ];

        if ( class_exists( 'DPS_Communications_API' ) ) {
            $communications = DPS_Communications_API::get_instance();
            $sent           = $communications->send_email(
                $email,
                $subject,
                $body,
                [
                    'type'      => 'portal_password_access',
                    'client_id' => $client_id,
                    'context'   => $context,
                ]
            );
        } else {
            $sent = wp_mail( $email, $subject, $body, $headers );
        }

        if ( ! $sent ) {
            return new WP_Error( 'email_failed', __( 'Não foi possível enviar o e-mail com as instruções de senha.', 'dps-client-portal' ) );
        }

        update_post_meta( $client_id, self::META_LAST_PASSWORD_EMAIL, current_time( 'mysql' ) );

        return true;
    }

    /**
     * Monta a URL customizada de redefinição dentro do portal.
     *
     * @param WP_User $user Usuário do portal.
     * @return string|WP_Error
     */
    public function get_password_reset_url( WP_User $user ) {
        $reset_key = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            return $reset_key;
        }

        return add_query_arg(
            [
                'dps_action' => 'portal_password_reset',
                'key'        => $reset_key,
                'login'      => $user->user_login,
            ],
            dps_get_portal_page_url()
        );
    }

    /**
     * Registra um login por senha para auditoria e admin.
     *
     * @param WP_User $user Usuário autenticado.
     * @return void
     */
    public function record_password_login( WP_User $user ) {
        $client_id = absint( get_user_meta( $user->ID, 'dps_client_id', true ) );
        if ( ! $client_id ) {
            return;
        }

        update_post_meta( $client_id, self::META_LAST_PASSWORD_LOGIN, current_time( 'mysql' ) );
        update_post_meta( $client_id, 'dps_portal_last_login_method', 'password' );

        if ( class_exists( 'DPS_Audit_Logger' ) ) {
            $ip = class_exists( 'DPS_IP_Helper' ) ? DPS_IP_Helper::get_ip() : 'unknown';
            DPS_Audit_Logger::log_portal_event(
                'password_login_success',
                $client_id,
                [
                    'ip'      => $ip,
                    'user_id' => $user->ID,
                ]
            );
        }
    }

    /**
     * Determina se o usuário é um cliente do portal.
     *
     * @param int|WP_User $user Usuário a validar.
     * @return bool
     */
    public function is_client_portal_user( $user ) {
        $wp_user = $user instanceof WP_User ? $user : get_userdata( absint( $user ) );
        if ( ! $wp_user instanceof WP_User ) {
            return false;
        }

        return $this->get_client_id_for_user( $wp_user ) > 0;
    }

    /**
     * Resolve o client_id a partir de um usuário WP.
     *
     * @param int|WP_User $user Usuário a validar.
     * @return int
     */
    public function get_client_id_for_user( $user ) {
        $wp_user = $user instanceof WP_User ? $user : get_userdata( absint( $user ) );
        if ( ! $wp_user instanceof WP_User ) {
            return 0;
        }

        $client_id = absint( get_user_meta( $wp_user->ID, 'dps_client_id', true ) );

        if ( $client_id && 'dps_cliente' === get_post_type( $client_id ) ) {
            return $client_id;
        }

        if ( $wp_user->user_email ) {
            return $this->find_client_id_by_email( $wp_user->user_email );
        }

        return 0;
    }

    /**
     * Determina se um usuário está associado a outro cliente.
     *
     * @param int $user_id ID do usuário.
     * @param int $client_id ID do cliente atual.
     * @return bool
     */
    private function user_belongs_to_other_client( $user_id, $client_id ) {
        $linked_client_id = absint( get_user_meta( absint( $user_id ), 'dps_client_id', true ) );

        return $linked_client_id > 0 && $linked_client_id !== absint( $client_id );
    }

    /**
     * Constrói o HTML do e-mail de ativação de senha.
     *
     * @param string $client_name Nome do cliente.
     * @param string $email E-mail de acesso do cliente.
     * @param string $reset_url URL para criação/redefinição da senha.
     * @param string $site_name Nome do site.
     * @param string $context Contexto do envio.
     * @return string
     */
    private function build_password_access_email_html( $client_name, $email, $reset_url, $site_name, $context ) {
        $escaped_name      = esc_html( $client_name ? $client_name : __( 'cliente', 'dps-client-portal' ) );
        $escaped_email     = esc_html( $email );
        $escaped_reset_url = esc_url( $reset_url );
        $escaped_site_name = esc_html( $site_name );
        $title             = 'self_service' === $context
            ? esc_html__( 'Seu acesso por e-mail e senha está pronto', 'dps-client-portal' )
            : esc_html__( 'Ative o login por e-mail e senha do cliente', 'dps-client-portal' );
        $intro             = 'self_service' === $context
            ? esc_html__( 'Recebemos sua solicitação para entrar no portal com e-mail e senha.', 'dps-client-portal' )
            : esc_html__( 'A equipe preparou o acesso por e-mail e senha ao Portal do Cliente.', 'dps-client-portal' );
        $cta               = esc_html__( 'Criar ou redefinir senha', 'dps-client-portal' );
        $note              = esc_html__( 'O login sempre usa o e-mail cadastrado no seu perfil. Se você não reconhece esta solicitação, ignore este e-mail.', 'dps-client-portal' );

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escaped_site_name}</title>
</head>
<body style="margin:0;padding:24px;background:#eef3f2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#132321;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:620px;margin:0 auto;">
        <tr>
            <td style="background:#ffffff;border-radius:28px;padding:40px 36px;box-shadow:0 12px 32px rgba(19,35,33,0.08);">
                <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#3f6a63;">Portal do Cliente</p>
                <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;font-weight:500;color:#132321;">{$title}</h1>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.6;color:#32524d;">Olá, {$escaped_name}.</p>
                <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#32524d;">{$intro}</p>
                <div style="background:#eef7f4;border:1px solid #c6e7dc;border-radius:16px;padding:18px 20px;margin:0 0 24px;">
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#4f6d67;">Login do portal</p>
                    <p style="margin:0;font-size:18px;line-height:1.4;font-weight:500;color:#132321;">{$escaped_email}</p>
                </div>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;">
                    <tr>
                        <td style="background:#1a7a5c;border-radius:999px;">
                            <a href="{$escaped_reset_url}" target="_blank" style="display:inline-block;padding:15px 28px;color:#ffffff;font-size:15px;font-weight:500;line-height:1;text-decoration:none;">{$cta}</a>
                        </td>
                    </tr>
                </table>
                <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#5a706c;">{$note}</p>
                <p style="margin:0;font-size:12px;line-height:1.6;color:#5a706c;">{$escaped_site_name}<br><a href="{$escaped_reset_url}" style="color:#1a7a5c;word-break:break-all;">{$escaped_reset_url}</a></p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

endif;
