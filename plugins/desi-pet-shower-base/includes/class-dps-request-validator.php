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

        return sanitize_text_field( wp_unslash( $raw_nonce ) );
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

    // =========================================================================
    // Métodos AJAX - Fase 3 DRY Refactoring
    // =========================================================================

    /**
     * Verifica nonce para requisições AJAX.
     *
     * Padrão simplificado para handlers AJAX. Extrai nonce do campo especificado,
     * sanitiza e verifica contra a ação.
     *
     * @since 2.5.0
     *
     * @param string $nonce_action  Ação esperada do nonce.
     * @param string $nonce_field   Nome do campo que contém o nonce (padrão: 'nonce').
     * @param bool   $send_json_error Se deve enviar wp_send_json_error() em caso de falha.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * // Em handler AJAX:
     * if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_save_settings' ) ) {
     *     return; // wp_send_json_error já foi chamado
     * }
     */
    public static function verify_ajax_nonce( $nonce_action, $nonce_field = 'nonce', $send_json_error = true ) {
        $nonce = '';
        
        // Tenta extrair do POST primeiro, depois do GET
        if ( isset( $_POST[ $nonce_field ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
        } elseif ( isset( $_GET[ $nonce_field ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_GET[ $nonce_field ] ) );
        }
        
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            if ( $send_json_error ) {
                wp_send_json_error( [
                    'message' => __( 'Sessão expirada. Recarregue a página.', 'desi-pet-shower' ),
                    'code'    => 'invalid_nonce',
                ] );
            }
            return false;
        }
        
        return true;
    }

    /**
     * Verifica nonce e capability para requisições AJAX administrativas.
     *
     * Combina verificação de nonce e permissão em uma única chamada.
     *
     * @since 2.5.0
     *
     * @param string $nonce_action  Ação esperada do nonce.
     * @param string $capability    Capability necessária (padrão: 'manage_options').
     * @param string $nonce_field   Nome do campo do nonce (padrão: 'nonce').
     * @param bool   $send_json_error Se deve enviar wp_send_json_error() em caso de falha.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * // Em handler AJAX admin:
     * if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_item', 'manage_options' ) ) {
     *     return;
     * }
     */
    public static function verify_ajax_admin( $nonce_action, $capability = 'manage_options', $nonce_field = 'nonce', $send_json_error = true ) {
        // Verifica capability primeiro
        if ( ! current_user_can( $capability ) ) {
            if ( $send_json_error ) {
                wp_send_json_error( [
                    'message' => __( 'Você não tem permissão para esta ação.', 'desi-pet-shower' ),
                    'code'    => 'unauthorized',
                ] );
            }
            return false;
        }
        
        // Depois verifica nonce
        return self::verify_ajax_nonce( $nonce_action, $nonce_field, $send_json_error );
    }

    /**
     * Verifica nonce de ação admin via GET (links de ação).
     *
     * Útil para links com ?action=delete&_wpnonce=xxx
     *
     * @since 2.5.0
     *
     * @param string $nonce_action  Ação esperada do nonce.
     * @param string $capability    Capability necessária (padrão: 'manage_options').
     * @param string $nonce_field   Nome do campo do nonce (padrão: '_wpnonce').
     * @param bool   $die_on_failure Se deve chamar wp_die() em caso de falha.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * // Em handler de ação admin via GET:
     * if ( ! DPS_Request_Validator::verify_admin_action( 'dps_export_data' ) ) {
     *     return; // wp_die já foi chamado
     * }
     */
    public static function verify_admin_action( $nonce_action, $capability = 'manage_options', $nonce_field = '_wpnonce', $die_on_failure = true ) {
        // Verifica capability primeiro
        if ( ! current_user_can( $capability ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Você não tem permissão para esta ação.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        // Verifica nonce
        $nonce = isset( $_GET[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_GET[ $nonce_field ] ) ) : '';
        
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Link expirado. Tente novamente.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        return true;
    }

    /**
     * Verifica nonce de formulário admin (POST).
     *
     * Combina verificação de nonce POST e capability.
     *
     * @since 2.5.0
     *
     * @param string $nonce_action  Ação esperada do nonce.
     * @param string $nonce_field   Nome do campo do nonce.
     * @param string $capability    Capability necessária (padrão: 'manage_options').
     * @param bool   $die_on_failure Se deve chamar wp_die() em caso de falha.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * // Em handler de formulário admin:
     * if ( ! DPS_Request_Validator::verify_admin_form( 'dps_save_settings', 'dps_settings_nonce' ) ) {
     *     return;
     * }
     */
    public static function verify_admin_form( $nonce_action, $nonce_field, $capability = 'manage_options', $die_on_failure = true ) {
        // Verifica capability primeiro
        if ( ! current_user_can( $capability ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Você não tem permissão para esta ação.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        // Verifica nonce
        if ( ! isset( $_POST[ $nonce_field ] ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
        
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Sessão expirada. Recarregue a página.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        return true;
    }

    /**
     * Verifica nonce dinâmico com ID (ex: dps_delete_item_123).
     *
     * Útil para ações que incluem ID do item no nonce.
     *
     * @since 2.5.0
     *
     * @param string $nonce_prefix  Prefixo da ação (ex: 'dps_delete_item_').
     * @param int    $item_id       ID do item.
     * @param string $nonce_field   Nome do campo do nonce (padrão: 'nonce').
     * @param string $method        Método HTTP ('POST' ou 'GET').
     * @param bool   $die_on_failure Se deve chamar wp_die() em caso de falha.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * // Verificar nonce com ID dinâmico:
     * $client_id = absint( $_GET['client_id'] );
     * if ( ! DPS_Request_Validator::verify_dynamic_nonce( 'dps_delete_client_', $client_id, 'nonce', 'GET' ) ) {
     *     return;
     * }
     */
    public static function verify_dynamic_nonce( $nonce_prefix, $item_id, $nonce_field = 'nonce', $method = 'POST', $die_on_failure = true ) {
        $superglobal = ( 'POST' === $method ) ? $_POST : $_GET;
        
        if ( ! isset( $superglobal[ $nonce_field ] ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        $nonce = sanitize_text_field( wp_unslash( $superglobal[ $nonce_field ] ) );
        $nonce_action = $nonce_prefix . absint( $item_id );
        
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            if ( $die_on_failure ) {
                wp_die( __( 'Sessão expirada. Tente novamente.', 'desi-pet-shower' ), 403 );
            }
            return false;
        }
        
        return true;
    }

    // =========================================================================
    // Métodos de resposta padronizada
    // =========================================================================

    /**
     * Envia resposta JSON de erro padronizada.
     *
     * @since 2.5.0
     *
     * @param string $message Mensagem de erro.
     * @param string $code    Código do erro (padrão: 'error').
     * @param int    $status  Código HTTP (padrão: 400).
     */
    public static function send_json_error( $message, $code = 'error', $status = 400 ) {
        wp_send_json_error( [
            'message' => $message,
            'code'    => $code,
        ], $status );
    }

    /**
     * Envia resposta JSON de sucesso padronizada.
     *
     * @since 2.5.0
     *
     * @param string $message Mensagem de sucesso.
     * @param array  $data    Dados adicionais (opcional).
     */
    public static function send_json_success( $message, $data = [] ) {
        wp_send_json_success( array_merge( [
            'message' => $message,
        ], $data ) );
    }

    // =========================================================================
    // Métodos GET auxiliares
    // =========================================================================

    /**
     * Obtém e sanitiza valor inteiro do GET.
     *
     * @since 2.5.0
     *
     * @param string $field_name Nome do campo.
     * @param int    $default    Valor padrão se campo não existir.
     * @return int Valor sanitizado.
     */
    public static function get_get_int( $field_name, $default = 0 ) {
        if ( ! isset( $_GET[ $field_name ] ) ) {
            return $default;
        }

        return intval( wp_unslash( $_GET[ $field_name ] ) );
    }

    /**
     * Obtém e sanitiza string do GET.
     *
     * @since 2.5.0
     *
     * @param string $field_name Nome do campo.
     * @param string $default    Valor padrão se campo não existir.
     * @return string Valor sanitizado.
     */
    public static function get_get_string( $field_name, $default = '' ) {
        if ( ! isset( $_GET[ $field_name ] ) ) {
            return $default;
        }

        return sanitize_text_field( wp_unslash( $_GET[ $field_name ] ) );
    }
}
