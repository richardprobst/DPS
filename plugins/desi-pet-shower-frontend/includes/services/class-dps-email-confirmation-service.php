<?php
/**
 * Service de confirmação de email (Fase 7).
 *
 * Gerencia tokens UUID de confirmação com TTL de 48h.
 * Metadados: dps_email_confirm_token, dps_email_confirm_token_created.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Email_Confirmation_Service {

    private const TOKEN_META         = 'dps_email_confirm_token';
    private const TOKEN_CREATED_META = 'dps_email_confirm_token_created';
    private const CONFIRMED_META     = 'dps_email_confirmed';
    private const TOKEN_TTL_HOURS    = 48;

    /**
     * Verifica se confirmação de email está habilitada.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return (bool) get_option( 'dps_registration_confirm_email_enabled', false );
    }

    /**
     * Gera token UUID e armazena no cliente.
     *
     * @param int $clientId ID do cliente.
     * @return string Token gerado.
     */
    public function generateToken( int $clientId ): string {
        $token = wp_generate_uuid4();

        update_post_meta( $clientId, self::TOKEN_META, $token );
        update_post_meta( $clientId, self::TOKEN_CREATED_META, time() );
        update_post_meta( $clientId, self::CONFIRMED_META, '0' );

        return $token;
    }

    /**
     * Verifica se um token é válido (existe e não expirou).
     *
     * @param int    $clientId ID do cliente.
     * @param string $token    Token a verificar.
     * @return bool
     */
    public function verifyToken( int $clientId, string $token ): bool {
        $storedToken = get_post_meta( $clientId, self::TOKEN_META, true );

        if ( '' === $storedToken || $storedToken !== $token ) {
            return false;
        }

        $createdAt = (int) get_post_meta( $clientId, self::TOKEN_CREATED_META, true );
        $expiresAt = $createdAt + ( self::TOKEN_TTL_HOURS * HOUR_IN_SECONDS );

        return time() <= $expiresAt;
    }

    /**
     * Confirma email do cliente e limpa tokens.
     *
     * @param int $clientId ID do cliente.
     */
    public function confirm( int $clientId ): void {
        update_post_meta( $clientId, self::CONFIRMED_META, '1' );
        delete_post_meta( $clientId, self::TOKEN_META );
        delete_post_meta( $clientId, self::TOKEN_CREATED_META );
    }

    /**
     * Envia email de confirmação.
     *
     * @param int    $clientId ID do cliente.
     * @param string $email    Email do cliente.
     * @param string $name     Nome do cliente.
     * @param string $token    Token de confirmação.
     * @return bool Se o email foi enviado.
     */
    public function sendConfirmationEmail( int $clientId, string $email, string $name, string $token ): bool {
        if ( ! $this->isEnabled() ) {
            return false;
        }

        $confirmUrl = add_query_arg( [
            'dps_confirm_email' => $token,
            'client_id'         => $clientId,
        ], home_url( '/' ) );

        $blogName = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: blog name */
            __( 'Confirme seu email — %s', 'dps-frontend-addon' ),
            $blogName
        );

        $message = sprintf(
            /* translators: 1: client name, 2: blog name, 3: confirmation URL */
            __( 'Olá %1$s, obrigado por se cadastrar no %2$s! Para confirmar seu email, clique no link abaixo: %3$s (Este link expira em 48 horas.)', 'dps-frontend-addon' ),
            esc_html( $name ),
            esc_html( $blogName ),
            esc_url( $confirmUrl )
        );

        // Tenta usar Communications API se disponível
        if ( class_exists( 'DPS_Communications_API' ) && method_exists( DPS_Communications_API::class, 'send_email' ) ) {
            return DPS_Communications_API::send_email( $email, $subject, $message );
        }

        return wp_mail( $email, $subject, $message );
    }
}
