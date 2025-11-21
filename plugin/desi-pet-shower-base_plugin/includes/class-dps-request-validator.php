<?php
/**
 * Helper class para validação de requisições e nonces.
 *
 * @package DesiPetShower
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe utilitária para validação de segurança de requisições.
 */
class DPS_Request_Validator {

    /**
     * Verifica nonce de uma requisição.
     *
     * @param string $nonce_field Nome do campo de nonce.
     * @param string $nonce_action Ação do nonce.
     * @param string $method Método HTTP ('POST' ou 'GET').
     * @param bool   $die_on_failure Se deve terminar execução em caso de falha.
     * @return bool True se o nonce é válido.
     */
    public static function verify_request_nonce( $nonce_field, $nonce_action, $method = 'POST', $die_on_failure = true ) {
        $superglobal = ( 'POST' === $method ) ? $_POST : $_GET;

        if ( ! isset( $superglobal[ $nonce_field ] ) ) {
            return self::handle_invalid_nonce( $die_on_failure );
        }

        $nonce_value = self::extract_nonce_value( $superglobal[ $nonce_field ], $method );

        if ( ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
            return self::handle_invalid_nonce( $die_on_failure );
        }

        return true;
    }

    /**
     * Extrai e sanitiza valor do nonce.
     *
     * @param mixed  $raw_nonce Valor bruto do nonce.
     * @param string $method Método HTTP.
     * @return string Valor sanitizado do nonce.
     */
    private static function extract_nonce_value( $raw_nonce, $method ) {
        if ( 'POST' === $method ) {
            return sanitize_text_field( wp_unslash( $raw_nonce ) );
        }

        return wp_unslash( $raw_nonce );
    }

    /**
     * Trata nonce inválido.
     *
     * @param bool $die_on_failure Se deve terminar execução.
     * @return bool Sempre retorna false.
     */
    private static function handle_invalid_nonce( $die_on_failure ) {
        if ( $die_on_failure ) {
            wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }

        return false;
    }

    /**
     * Verifica se usuário tem permissão específica.
     *
     * @param string $capability Capability necessária.
     * @param bool   $die_on_failure Se deve terminar execução em caso de falha.
     * @return bool True se o usuário tem a permissão.
     */
    public static function verify_capability( $capability, $die_on_failure = true ) {
        if ( ! current_user_can( $capability ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
            }
            return false;
        }

        return true;
    }

    /**
     * Verifica nonce e capability em uma única chamada.
     *
     * @param string $nonce_field Nome do campo de nonce.
     * @param string $nonce_action Ação do nonce.
     * @param string $capability Capability necessária.
     * @param string $method Método HTTP ('POST' ou 'GET').
     * @param bool   $die_on_failure Se deve terminar execução em caso de falha.
     * @return bool True se validação passou.
     */
    public static function verify_nonce_and_capability( $nonce_field, $nonce_action, $capability, $method = 'POST', $die_on_failure = true ) {
        $nonce_valid = self::verify_request_nonce( $nonce_field, $nonce_action, $method, $die_on_failure );

        if ( ! $nonce_valid ) {
            return false;
        }

        return self::verify_capability( $capability, $die_on_failure );
    }

    /**
     * Verifica se campo POST existe e não está vazio.
     *
     * @param string $field_name Nome do campo.
     * @return bool True se o campo existe e tem valor.
     */
    public static function post_field_exists( $field_name ) {
        return isset( $_POST[ $field_name ] ) && '' !== $_POST[ $field_name ];
    }

    /**
     * Verifica se campo GET existe e não está vazio.
     *
     * @param string $field_name Nome do campo.
     * @return bool True se o campo existe e tem valor.
     */
    public static function get_field_exists( $field_name ) {
        return isset( $_GET[ $field_name ] ) && '' !== $_GET[ $field_name ];
    }

    /**
     * Obtém e sanitiza valor inteiro do POST.
     *
     * @param string $field_name Nome do campo.
     * @param int    $default Valor padrão se campo não existir.
     * @return int Valor sanitizado.
     */
    public static function get_post_int( $field_name, $default = 0 ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return $default;
        }

        return intval( wp_unslash( $_POST[ $field_name ] ) );
    }

    /**
     * Obtém e sanitiza string do POST.
     *
     * @param string $field_name Nome do campo.
     * @param string $default Valor padrão se campo não existir.
     * @return string Valor sanitizado.
     */
    public static function get_post_string( $field_name, $default = '' ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return $default;
        }

        return sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) );
    }

    /**
     * Obtém e sanitiza textarea do POST.
     *
     * @param string $field_name Nome do campo.
     * @param string $default Valor padrão se campo não existir.
     * @return string Valor sanitizado.
     */
    public static function get_post_textarea( $field_name, $default = '' ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return $default;
        }

        return sanitize_textarea_field( wp_unslash( $_POST[ $field_name ] ) );
    }

    /**
     * Obtém valor de checkbox do POST.
     *
     * @param string $field_name Nome do campo.
     * @return string '1' se marcado, '0' caso contrário.
     */
    public static function get_post_checkbox( $field_name ) {
        return isset( $_POST[ $field_name ] ) ? '1' : '0';
    }
}
