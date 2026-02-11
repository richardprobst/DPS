<?php
/**
 * Guard de requisições do Frontend Add-on.
 *
 * Centraliza verificações de segurança (nonce, capability, sanitização)
 * utilizadas pelos módulos. Métodos estáticos — sem estado, utilitário puro.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Request_Guard {

    /**
     * Verifica nonce de formulário POST.
     */
    public static function verifyNonce( string $action, string $field = '_wpnonce' ): bool {
        $value = isset( $_POST[ $field ] )
            ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) )
            : '';

        return '' !== $value && false !== wp_verify_nonce( $value, $action );
    }

    /**
     * Verifica capability do usuário atual.
     */
    public static function userCan( string $capability ): bool {
        return current_user_can( $capability );
    }

    /**
     * Sanitiza campo de texto do POST.
     */
    public static function text( string $key, string $default = '' ): string {
        return isset( $_POST[ $key ] )
            ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) )
            : $default;
    }

    /**
     * Sanitiza campo inteiro do POST.
     */
    public static function integer( string $key, int $default = 0 ): int {
        return isset( $_POST[ $key ] )
            ? absint( wp_unslash( $_POST[ $key ] ) )
            : $default;
    }

    /**
     * Aborta com erro 403 e loga o motivo.
     */
    public static function abort( string $reason = '' ): never {
        if ( '' !== $reason ) {
            ( new DPS_Frontend_Logger() )->warning( "Requisição bloqueada: {$reason}" );
        }

        wp_die(
            esc_html__( 'Requisição não autorizada.', 'dps-frontend-addon' ),
            esc_html__( 'Erro de segurança', 'dps-frontend-addon' ),
            [ 'response' => 403 ]
        );
    }
}
