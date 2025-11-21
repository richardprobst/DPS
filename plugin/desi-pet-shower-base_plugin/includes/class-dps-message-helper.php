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
     * Adiciona mensagem ao transient do usuário
     *
     * @param string $type Tipo da mensagem (success/error/warning)
     * @param string $message Texto da mensagem
     * @param int|null $user_id ID do usuário
     * @return void
     */
    private static function add_message( $type, $message, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return;
        }
        
        $key = self::TRANSIENT_PREFIX . $user_id;
        $messages = get_transient( $key );
        
        if ( ! is_array( $messages ) ) {
            $messages = [];
        }
        
        $messages[] = [
            'type' => $type,
            'text' => $message,
        ];
        
        set_transient( $key, $messages, self::TRANSIENT_EXPIRATION );
    }
    
    /**
     * Exibe todas as mensagens armazenadas e as remove
     *
     * @param int|null $user_id ID do usuário (padrão: usuário atual)
     * @return string HTML das mensagens
     */
    public static function display_messages( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return '';
        }
        
        $key = self::TRANSIENT_PREFIX . $user_id;
        $messages = get_transient( $key );
        
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
            
            $html .= '<div class="' . esc_attr( $class ) . '">';
            $html .= esc_html( $msg['text'] );
            $html .= '</div>';
        }
        
        // Remove as mensagens após exibição
        delete_transient( $key );
        
        return $html;
    }
}
