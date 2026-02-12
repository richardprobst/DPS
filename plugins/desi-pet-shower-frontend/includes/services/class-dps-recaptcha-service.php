<?php
/**
 * Service de verificação reCAPTCHA v3 (Fase 7).
 *
 * Valida token reCAPTCHA v3 com Google API.
 * Lê configuração das options do registro legado.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Recaptcha_Service {

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const ACTION     = 'dps_registration';

    /**
     * Verifica se reCAPTCHA está habilitado.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return (bool) get_option( 'dps_registration_recaptcha_enabled', false );
    }

    /**
     * Retorna site key para uso no frontend.
     *
     * @return string
     */
    public function getSiteKey(): string {
        return (string) get_option( 'dps_registration_recaptcha_site_key', '' );
    }

    /**
     * Verifica token reCAPTCHA v3 com Google.
     *
     * @param string $token Token recebido do frontend.
     * @return true|string True se válido, mensagem de erro caso contrário.
     */
    public function verify( string $token ): true|string {
        if ( ! $this->isEnabled() ) {
            return true;
        }

        if ( '' === $token ) {
            return __( 'Token reCAPTCHA não fornecido.', 'dps-frontend-addon' );
        }

        $secretKey = (string) get_option( 'dps_registration_recaptcha_secret_key', '' );
        if ( '' === $secretKey ) {
            return true; // Sem chave secreta, pula verificação
        }

        $response = wp_remote_post( self::VERIFY_URL, [
            'body'    => [
                'secret'   => $secretKey,
                'response' => $token,
            ],
            'timeout' => 5,
        ] );

        if ( is_wp_error( $response ) ) {
            return __( 'Erro ao verificar reCAPTCHA. Tente novamente.', 'dps-frontend-addon' );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) {
            return __( 'Verificação reCAPTCHA falhou. Tente novamente.', 'dps-frontend-addon' );
        }

        // Verificar ação
        if ( isset( $body['action'] ) && self::ACTION !== $body['action'] ) {
            return __( 'Ação reCAPTCHA inválida.', 'dps-frontend-addon' );
        }

        // Verificar score
        $threshold = (float) get_option( 'dps_registration_recaptcha_threshold', 0.5 );
        if ( isset( $body['score'] ) && (float) $body['score'] < $threshold ) {
            return __( 'Atividade suspeita detectada. Tente novamente.', 'dps-frontend-addon' );
        }

        return true;
    }
}
