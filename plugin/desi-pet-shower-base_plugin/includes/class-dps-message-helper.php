<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper para gerenciar mensagens de feedback (sucesso/erro/aviso).
 * 
 * Utiliza transients do WordPress para armazenar mensagens temporárias
 * por usuário, que são exibidas uma única vez após redirecionamento.
 *
 * @package DPS_Base
 * @since 1.0.0
 */
class DPS_Message_Helper {
    
    /**
     * Prefixo para transients de mensagens
     */
    const TRANSIENT_PREFIX = 'dps_message_';
    
    /**
     * Tempo de expiração dos transients (60 segundos)
     */
    const TRANSIENT_EXPIRATION = 60;
    
    /**
     * Adiciona mensagem de sucesso
     *
     * @param string $message Texto da mensagem
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return void
     */
    public static function add_success( $message, $user_id = null ) {
        self::add_message( 'success', $message, $user_id );
    }
    
    /**
     * Adiciona mensagem de erro
     *
     * @param string $message Texto da mensagem
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return void
     */
    public static function add_error( $message, $user_id = null ) {
        self::add_message( 'error', $message, $user_id );
    }
    
    /**
     * Adiciona mensagem de aviso
     *
     * @param string $message Texto da mensagem
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return void
     */
    public static function add_warning( $message, $user_id = null ) {
        self::add_message( 'warning', $message, $user_id );
    }
    
    /**
     * Resolve o alvo de armazenamento das mensagens (transient de usuário ou cookie para visitantes).
     *
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return array{
     *     type: string,
     *     key: string
     * }
     */
    private static function resolve_storage_target( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( $user_id ) {
            return [
                'type' => 'user',
                'key'  => self::TRANSIENT_PREFIX . $user_id,
            ];
        }

        return [
            'type' => 'guest',
            'key'  => 'dps_message_guest',
        ];
    }

    /**
     * Adiciona mensagem ao armazenamento apropriado.
     *
     * @param string   $type    Tipo da mensagem (success/error/warning).
     * @param string   $message Texto da mensagem.
     * @param int|null $user_id ID do usuário (padrão: usuário atual).
     * @return void
     */
    private static function add_message( $type, $message, $user_id = null ) {
        $target = self::resolve_storage_target( $user_id );
        $messages = [];

        if ( 'user' === $target['type'] ) {
            $stored = get_transient( $target['key'] );
            if ( is_array( $stored ) ) {
                $messages = $stored;
            }
        } else {
            $cookie_val = isset( $_COOKIE[ $target['key'] ] ) ? wp_unslash( $_COOKIE[ $target['key'] ] ) : '';
            if ( $cookie_val ) {
                $decoded = json_decode( $cookie_val, true );
                if ( is_array( $decoded ) ) {
                    $messages = $decoded;
                }
            }
        }

        $messages[] = [
            'type' => $type,
            'text' => $message,
        ];

        if ( 'user' === $target['type'] ) {
            set_transient( $target['key'], $messages, self::TRANSIENT_EXPIRATION );
            return;
        }

        $cookie_value = wp_json_encode( $messages );
        setcookie( $target['key'], $cookie_value, time() + self::TRANSIENT_EXPIRATION, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    /**
     * Exibe todas as mensagens armazenadas e as remove.
     *
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return string HTML das mensagens
     */
    public static function display_messages( $user_id = null ) {
        $target = self::resolve_storage_target( $user_id );

        $messages = [];
        if ( 'user' === $target['type'] ) {
            $messages = get_transient( $target['key'] );
        } else {
            $cookie_val = isset( $_COOKIE[ $target['key'] ] ) ? wp_unslash( $_COOKIE[ $target['key'] ] ) : '';
            if ( $cookie_val ) {
                $decoded = json_decode( $cookie_val, true );
                if ( is_array( $decoded ) ) {
                    $messages = $decoded;
                }
            }
        }

        if ( ! is_array( $messages ) || empty( $messages ) ) {
            return '';
        }

        $html = '';
        foreach ( $messages as $msg ) {
            $class = 'dps-alert';

            if ( $msg['type'] === 'error' ) {
                $class .= ' dps-alert--danger';
            } elseif ( $msg['type'] === 'success' ) {
                $class .= ' dps-alert--success';
            } elseif ( $msg['type'] === 'warning' ) {
                $class .= ' dps-alert--pending';
            }

            // Define atributos de acessibilidade conforme o tipo de mensagem
            $role      = ( $msg['type'] === 'error' ) ? 'alert' : 'status';
            $aria_live = ( $msg['type'] === 'error' ) ? 'assertive' : 'polite';

            $html .= '<div class="' . esc_attr( $class ) . '" role="' . esc_attr( $role ) . '" aria-live="' . esc_attr( $aria_live ) . '">';
            $html .= esc_html( $msg['text'] );
            $html .= '</div>';
        }

        if ( 'user' === $target['type'] ) {
            delete_transient( $target['key'] );
        } else {
            // Limpa cookie após exibir para evitar mensagens duplicadas.
            setcookie( $target['key'], '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }

        return $html;
    }
}
