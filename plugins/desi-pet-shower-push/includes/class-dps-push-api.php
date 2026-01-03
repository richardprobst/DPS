<?php
/**
 * API de Push Notifications para o DPS.
 *
 * Implementa Web Push API usando biblioteca PHP nativa.
 *
 * @package DPS_Push_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe API para Push Notifications.
 */
class DPS_Push_API {

    /**
     * Gera par de chaves VAPID.
     *
     * @since 1.0.0
     * @return array Chaves public e private em base64url.
     */
    public static function generate_vapid_keys() {
        // Gerar chaves ECDSA P-256
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];

        $key = openssl_pkey_new( $config );
        
        if ( ! $key ) {
            // Fallback: gerar chaves aleatórias (funcionará apenas para armazenamento)
            return [
                'public'  => self::base64url_encode( random_bytes( 65 ) ),
                'private' => self::base64url_encode( random_bytes( 32 ) ),
            ];
        }

        $details = openssl_pkey_get_details( $key );
        openssl_pkey_export( $key, $private_pem );

        // Extrair coordenadas x e y da chave pública
        $public_key_data = $details['ec']['x'] . $details['ec']['y'];
        // Prefixar com 0x04 para indicar chave não comprimida
        $public_key = chr( 4 ) . $public_key_data;

        return [
            'public'      => self::base64url_encode( $public_key ),
            'private'     => self::base64url_encode( $details['ec']['d'] ),
            'private_pem' => $private_pem,
        ];
    }

    /**
     * Envia notificação para um usuário específico.
     *
     * @since 1.0.0
     * @param int   $user_id ID do usuário.
     * @param array $payload Dados da notificação (title, body, icon, etc.).
     * @return array Resultado com success e failed counts.
     */
    public static function send_to_user( $user_id, $payload ) {
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );
        
        if ( ! is_array( $subscriptions ) || empty( $subscriptions ) ) {
            return [ 'success' => 0, 'failed' => 0 ];
        }

        $result = [ 'success' => 0, 'failed' => 0 ];
        $failed_endpoints = [];

        foreach ( $subscriptions as $hash => $subscription ) {
            $sent = self::send_notification( $subscription, $payload );
            
            if ( $sent ) {
                $result['success']++;
            } else {
                $result['failed']++;
                $failed_endpoints[] = $hash;
            }
        }

        // Remover endpoints que falharam (provavelmente expirados)
        if ( ! empty( $failed_endpoints ) ) {
            foreach ( $failed_endpoints as $hash ) {
                unset( $subscriptions[ $hash ] );
            }
            update_user_meta( $user_id, '_dps_push_subscriptions', $subscriptions );
        }

        return $result;
    }

    /**
     * Envia notificação para todos os administradores.
     *
     * @since 1.0.0
     * @param array $payload     Dados da notificação.
     * @param array $exclude_ids IDs de usuários a excluir.
     * @return array Resultado consolidado.
     */
    public static function send_to_all_admins( $payload, $exclude_ids = [] ) {
        $admins = get_users( [
            'role__in' => [ 'administrator' ],
            'fields'   => 'ID',
        ] );

        $total_result = [ 'success' => 0, 'failed' => 0 ];

        foreach ( $admins as $admin_id ) {
            if ( in_array( $admin_id, $exclude_ids, true ) ) {
                continue;
            }

            $result = self::send_to_user( $admin_id, $payload );
            $total_result['success'] += $result['success'];
            $total_result['failed']  += $result['failed'];
        }

        return $total_result;
    }

    /**
     * Envia uma notificação push individual.
     *
     * @since 1.0.0
     * @param array $subscription Dados da inscrição.
     * @param array $payload      Dados da notificação.
     * @return bool True se enviado com sucesso.
     */
    private static function send_notification( $subscription, $payload ) {
        if ( empty( $subscription['endpoint'] ) ) {
            return false;
        }

        $vapid_keys = get_option( DPS_Push_Addon::VAPID_KEY, [] );
        
        if ( empty( $vapid_keys['public'] ) || empty( $vapid_keys['private'] ) ) {
            return false;
        }

        // Preparar payload JSON
        $json_payload = wp_json_encode( $payload );

        // Para implementação completa de Web Push com criptografia,
        // seria necessário usar biblioteca como minishlink/web-push.
        // Esta é uma implementação simplificada que funciona com o
        // endpoint e headers básicos.
        
        $endpoint = $subscription['endpoint'];
        $parsed   = wp_parse_url( $endpoint );
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        // Gerar JWT para VAPID
        $jwt = self::generate_vapid_jwt( $audience, $vapid_keys );
        
        if ( ! $jwt ) {
            // Fallback: tenta enviar sem VAPID (pode funcionar em alguns casos)
            $jwt = '';
        }

        // Headers para Web Push
        $headers = [
            'Content-Type'     => 'application/json',
            'Content-Length'   => strlen( $json_payload ),
            'TTL'              => '86400', // 24 horas
        ];

        if ( $jwt ) {
            $headers['Authorization'] = 'vapid t=' . $jwt . ', k=' . $vapid_keys['public'];
        }

        // Enviar requisição
        $response = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => $json_payload,
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'DPS_Logger' ) ) {
                DPS_Logger::error(
                    'Erro ao enviar push notification: ' . $response->get_error_message(),
                    [ 'endpoint' => $endpoint ],
                    'push'
                );
            }
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        
        // 201 = Created (sucesso)
        // 410 = Gone (inscrição expirada)
        // 404 = Not Found (inscrição inválida)
        if ( $status_code === 201 ) {
            return true;
        }

        if ( in_array( $status_code, [ 404, 410 ], true ) ) {
            // Inscrição expirada ou inválida - será removida
            return false;
        }

        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::warning(
                sprintf( 'Push notification retornou status %d', $status_code ),
                [ 
                    'endpoint' => $endpoint,
                    'body'     => wp_remote_retrieve_body( $response ),
                ],
                'push'
            );
        }

        return false;
    }

    /**
     * Gera JWT para autenticação VAPID.
     *
     * @since 1.0.0
     * @param string $audience   URL do endpoint (scheme + host).
     * @param array  $vapid_keys Chaves VAPID.
     * @return string|false JWT ou false em caso de erro.
     */
    private static function generate_vapid_jwt( $audience, $vapid_keys ) {
        if ( empty( $vapid_keys['private_pem'] ) ) {
            return false;
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ];

        $payload = [
            'aud' => $audience,
            'exp' => time() + 86400, // 24 horas
            'sub' => 'mailto:admin@' . wp_parse_url( home_url(), PHP_URL_HOST ),
        ];

        $header_encoded  = self::base64url_encode( wp_json_encode( $header ) );
        $payload_encoded = self::base64url_encode( wp_json_encode( $payload ) );

        $data = $header_encoded . '.' . $payload_encoded;

        // Assinar com chave privada ECDSA
        $private_key = openssl_pkey_get_private( $vapid_keys['private_pem'] );
        
        if ( ! $private_key ) {
            return false;
        }

        $signature = '';
        $result = openssl_sign( $data, $signature, $private_key, OPENSSL_ALGO_SHA256 );

        if ( ! $result ) {
            return false;
        }

        // Converter assinatura DER para formato JWT (R || S)
        $signature = self::der_to_raw( $signature, 64 );

        return $data . '.' . self::base64url_encode( $signature );
    }

    /**
     * Converte assinatura DER para formato raw (R || S).
     *
     * @since 1.0.0
     * @param string $der    Assinatura em formato DER.
     * @param int    $length Tamanho esperado (64 para P-256).
     * @return string Assinatura raw.
     */
    private static function der_to_raw( $der, $length ) {
        $pos = 0;
        
        // Skip SEQUENCE tag
        if ( ord( $der[ $pos++ ] ) !== 0x30 ) {
            return $der;
        }
        
        // Skip length byte(s)
        $len = ord( $der[ $pos++ ] );
        if ( $len & 0x80 ) {
            $pos += $len & 0x7F;
        }

        // Read R
        if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
            return $der;
        }
        $r_len = ord( $der[ $pos++ ] );
        $r = substr( $der, $pos, $r_len );
        $pos += $r_len;

        // Read S
        if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
            return $der;
        }
        $s_len = ord( $der[ $pos++ ] );
        $s = substr( $der, $pos, $s_len );

        // Pad or trim to correct length
        $half = $length / 2;
        $r = str_pad( ltrim( $r, "\x00" ), $half, "\x00", STR_PAD_LEFT );
        $s = str_pad( ltrim( $s, "\x00" ), $half, "\x00", STR_PAD_LEFT );

        return substr( $r, -$half ) . substr( $s, -$half );
    }

    /**
     * Codifica em base64url.
     *
     * @since 1.0.0
     * @param string $data Dados a codificar.
     * @return string Dados codificados.
     */
    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Decodifica de base64url.
     *
     * @since 1.0.0
     * @param string $data Dados codificados.
     * @return string Dados decodificados.
     */
    private static function base64url_decode( $data ) {
        return base64_decode( strtr( $data, '-_', '+/' ) . str_repeat( '=', 3 - ( 3 + strlen( $data ) ) % 4 ) );
    }
}
