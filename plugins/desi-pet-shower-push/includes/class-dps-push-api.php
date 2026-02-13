<?php
/**
 * API de Push Notifications para o DPS.
 *
 * Implementa Web Push API com criptografia de payload conforme RFC 8291
 * (aes128gcm content encoding) e autenticação VAPID (RFC 8292).
 *
 * @package DPS_Push_Addon
 * @since   1.0.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe API para Push Notifications.
 *
 * Responsável por gerar chaves VAPID, criptografar payloads conforme RFC 8291
 * e enviar notificações push via endpoints de serviços de push conhecidos.
 *
 * @since 1.0.0
 */
class DPS_Push_API {

    /**
     * Tamanho da chave pública EC P-256 não comprimida (0x04 || x || y).
     *
     * @since 2.0.0
     * @var int
     */
    const EC_P256_PUBLIC_KEY_LENGTH = 65;

    /**
     * Tamanho do auth secret do cliente (RFC 8291).
     *
     * @since 2.0.0
     * @var int
     */
    const AUTH_SECRET_LENGTH = 16;

    /**
     * Record size para aes128gcm (RFC 8188 §2).
     *
     * @since 2.0.0
     * @var int
     */
    const AES128GCM_RECORD_SIZE = 4096;

    /**
     * Hosts permitidos para endpoints push (proteção SSRF).
     *
     * @since 2.0.0
     * @var string[]
     */
    private static $allowed_hosts = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'notify.windows.com',
        'web.push.apple.com',
    ];

    /**
     * Prefixo DER para chave pública EC P-256 no formato SPKI (uncompressed).
     *
     * @since 2.0.0
     * @var string
     */
    private static $ec_p256_der_prefix = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Gera par de chaves VAPID.
     *
     * Cria um par de chaves ECDSA P-256 para uso com VAPID (RFC 8292).
     * A chave pública é retornada como ponto EC não comprimido (65 bytes)
     * codificado em base64url. A chave privada é retornada tanto em
     * base64url (escalar d, 32 bytes) quanto em formato PEM.
     *
     * @since 1.0.0
     * @return array{public: string, private: string, private_pem: string}|false
     *     Array com chaves ou false em caso de erro.
     */
    public static function generate_vapid_keys() {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];

        $key = openssl_pkey_new( $config );

        if ( ! $key ) {
            self::log_error(
                __( 'Falha ao gerar chaves VAPID via OpenSSL.', 'dps-push-addon' ),
                [ 'openssl_error' => openssl_error_string() ]
            );
            return false;
        }

        $details = openssl_pkey_get_details( $key );
        openssl_pkey_export( $key, $private_pem );

        // Ponto EC não comprimido: 0x04 || x || y.
        $public_key = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        return [
            'public'      => self::base64url_encode( $public_key ),
            'private'     => self::base64url_encode( $details['ec']['d'] ),
            'private_pem' => $private_pem,
        ];
    }

    /**
     * Envia notificação para um usuário específico.
     *
     * Itera sobre todas as subscriptions do usuário, envia a notificação
     * para cada uma e remove automaticamente subscriptions expiradas
     * (status 404 ou 410).
     *
     * @since 1.0.0
     * @param int   $user_id ID do usuário.
     * @param array $payload Dados da notificação (title, body, icon, etc.).
     * @return array{success: int, failed: int} Contadores de sucesso/falha.
     */
    public static function send_to_user( $user_id, $payload ) {
        $subscriptions = get_user_meta( $user_id, '_dps_push_subscriptions', true );

        if ( ! is_array( $subscriptions ) || empty( $subscriptions ) ) {
            return [ 'success' => 0, 'failed' => 0 ];
        }

        $result            = [ 'success' => 0, 'failed' => 0 ];
        $expired_endpoints = [];

        foreach ( $subscriptions as $hash => $subscription ) {
            $status = self::send_notification( $subscription, $payload );

            if ( true === $status ) {
                $result['success']++;
            } else {
                $result['failed']++;
                if ( 'expired' === $status ) {
                    $expired_endpoints[] = $hash;
                }
            }
        }

        // Remover subscriptions expiradas/inválidas.
        if ( ! empty( $expired_endpoints ) ) {
            foreach ( $expired_endpoints as $hash ) {
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
     * @return array{success: int, failed: int} Resultado consolidado.
     */
    public static function send_to_all_admins( $payload, $exclude_ids = [] ) {
        $admins = get_users( [
            'role__in' => [ 'administrator' ],
            'fields'   => 'ID',
        ] );

        $total_result = [ 'success' => 0, 'failed' => 0 ];

        foreach ( $admins as $admin_id ) {
            if ( in_array( (int) $admin_id, array_map( 'intval', $exclude_ids ), true ) ) {
                continue;
            }

            $result = self::send_to_user( (int) $admin_id, $payload );
            $total_result['success'] += $result['success'];
            $total_result['failed']  += $result['failed'];
        }

        return $total_result;
    }

    // ------------------------------------------------------------------
    // Métodos privados: envio e criptografia
    // ------------------------------------------------------------------

    /**
     * Envia uma notificação push individual com payload criptografado (RFC 8291).
     *
     * @since 2.0.0
     * @param array $subscription Dados da subscription (endpoint, keys.p256dh, keys.auth).
     * @param array $payload      Dados da notificação.
     * @return true|string|false True em caso de sucesso, 'expired' se a subscription
     *     expirou (404/410), false para outros erros.
     */
    private static function send_notification( $subscription, $payload ) {
        // --- Validações básicas ------------------------------------------------
        if ( empty( $subscription['endpoint'] ) ) {
            return false;
        }

        $vapid_keys = get_option( DPS_Push_Addon::VAPID_KEY, [] );

        if ( empty( $vapid_keys['public'] ) || empty( $vapid_keys['private_pem'] ) ) {
            self::log_error( __( 'Chaves VAPID não configuradas.', 'dps-push-addon' ) );
            return false;
        }

        $endpoint = esc_url_raw( $subscription['endpoint'] );
        if ( empty( $endpoint ) ) {
            return false;
        }

        // --- SSRF protection ---------------------------------------------------
        $parsed = wp_parse_url( $endpoint );
        if ( ! isset( $parsed['host'] ) || ! isset( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
            return false;
        }

        if ( ! self::is_allowed_host( $parsed['host'] ) ) {
            self::log_warning(
                __( 'Endpoint push não reconhecido rejeitado.', 'dps-push-addon' ),
                [ 'host' => $parsed['host'] ]
            );
            return false;
        }

        // --- Validar chaves do cliente -----------------------------------------
        if ( empty( $subscription['keys']['p256dh'] ) || empty( $subscription['keys']['auth'] ) ) {
            self::log_warning(
                __( 'Subscription sem chaves p256dh/auth.', 'dps-push-addon' ),
                [ 'endpoint_host' => $parsed['host'] ]
            );
            return false;
        }

        // --- Criptografar payload (RFC 8291) -----------------------------------
        $json_payload = wp_json_encode( $payload );

        $encrypted_body = self::encrypt_payload(
            $json_payload,
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );

        if ( false === $encrypted_body ) {
            self::log_error(
                __( 'Falha ao criptografar payload push.', 'dps-push-addon' ),
                [ 'endpoint_host' => $parsed['host'] ]
            );
            return false;
        }

        // --- VAPID JWT ---------------------------------------------------------
        $audience = $parsed['scheme'] . '://' . $parsed['host'];
        $jwt      = self::generate_vapid_jwt( $audience, $vapid_keys );

        if ( ! $jwt ) {
            self::log_error(
                __( 'Falha ao gerar JWT VAPID.', 'dps-push-addon' ),
                [ 'endpoint_host' => $parsed['host'] ]
            );
            return false;
        }

        // --- Enviar requisição -------------------------------------------------
        $headers = [
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'Content-Length'   => (string) strlen( $encrypted_body ),
            'TTL'              => '86400',
            'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid_keys['public'],
        ];

        $response = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => $encrypted_body,
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            self::log_error(
                /* translators: %s: mensagem de erro do WP_Error */
                sprintf( __( 'Erro ao enviar push notification: %s', 'dps-push-addon' ), $response->get_error_message() ),
                [ 'endpoint_host' => $parsed['host'] ]
            );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // 201 = Created (sucesso).
        if ( 201 === $status_code ) {
            return true;
        }

        // 404/410 = Subscription expirada/inválida.
        if ( in_array( $status_code, [ 404, 410 ], true ) ) {
            return 'expired';
        }

        self::log_warning(
            /* translators: %d: código de status HTTP */
            sprintf( __( 'Push notification retornou status %d.', 'dps-push-addon' ), $status_code ),
            [
                'endpoint_host' => $parsed['host'],
                'status'        => $status_code,
            ]
        );

        return false;
    }

    /**
     * Criptografa o payload seguindo RFC 8291 (aes128gcm content encoding).
     *
     * Fluxo:
     * 1. Decodifica p256dh (chave pública do cliente) e auth (segredo compartilhado).
     * 2. Gera par de chaves EC P-256 local (efêmero).
     * 3. Computa shared secret via ECDH.
     * 4. Deriva IKM via HKDF usando auth como salt.
     * 5. Gera salt aleatório de 16 bytes.
     * 6. Deriva CEK (16 bytes) e nonce (12 bytes) via HKDF.
     * 7. Criptografa payload com AES-128-GCM.
     * 8. Monta corpo aes128gcm: salt || rs || idlen || keyid || ciphertext.
     *
     * @since 2.0.0
     * @param string $plaintext   Payload em texto plano (JSON).
     * @param string $client_pub  Chave pública do cliente (base64url, 65 bytes raw).
     * @param string $client_auth Segredo auth do cliente (base64url, 16 bytes raw).
     * @return string|false Corpo criptografado ou false em caso de erro.
     */
    private static function encrypt_payload( $plaintext, $client_pub, $client_auth ) {
        // --- Decodificar chaves do cliente --------------------------------------
        $ua_public   = self::base64url_decode( $client_pub );
        $auth_secret = self::base64url_decode( $client_auth );

        if ( self::EC_P256_PUBLIC_KEY_LENGTH !== strlen( $ua_public ) || "\x04" !== $ua_public[0] ) {
            self::log_error( __( 'Chave pública do cliente inválida (esperado 65 bytes, uncompressed).', 'dps-push-addon' ),
                [ 'length' => strlen( $ua_public ) ]
            );
            return false;
        }

        if ( self::AUTH_SECRET_LENGTH !== strlen( $auth_secret ) ) {
            self::log_error( __( 'Auth secret do cliente inválido (esperado 16 bytes).', 'dps-push-addon' ) );
            return false;
        }

        // --- Gerar par de chaves EC P-256 local (efêmero) ----------------------
        $local_key = openssl_pkey_new( [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ] );

        if ( ! $local_key ) {
            self::log_error(
                __( 'Falha ao gerar chave EC efêmera.', 'dps-push-addon' ),
                [ 'openssl_error' => openssl_error_string() ]
            );
            return false;
        }

        $local_details = openssl_pkey_get_details( $local_key );
        $local_public  = "\x04" . $local_details['ec']['x'] . $local_details['ec']['y'];

        // --- ECDH shared secret ------------------------------------------------
        $client_pem  = self::raw_public_key_to_pem( $ua_public );
        $client_ossl = openssl_pkey_get_public( $client_pem );

        if ( ! $client_ossl ) {
            self::log_error(
                __( 'Falha ao importar chave pública do cliente.', 'dps-push-addon' ),
                [ 'openssl_error' => openssl_error_string() ]
            );
            return false;
        }

        $ecdh_secret = openssl_pkey_derive( $client_ossl, $local_key, 32 );

        if ( false === $ecdh_secret ) {
            self::log_error(
                __( 'Falha ao derivar ECDH shared secret.', 'dps-push-addon' ),
                [ 'openssl_error' => openssl_error_string() ]
            );
            return false;
        }

        // --- Derivar IKM (RFC 8291 §3.4) --------------------------------------
        // info = "WebPush: info\x00" || ua_public || local_public
        $ikm_info = "WebPush: info\x00" . $ua_public . $local_public;
        $ikm      = self::hkdf( $auth_secret, $ecdh_secret, $ikm_info, 32 );

        if ( false === $ikm ) {
            return false;
        }

        // --- Salt aleatório (16 bytes) -----------------------------------------
        $salt = random_bytes( 16 );

        // --- Derivar CEK e nonce -----------------------------------------------
        $cek_info = "Content-Encoding: aes128gcm\x00";
        $cek      = self::hkdf( $salt, $ikm, $cek_info, 16 );

        $nonce_info = "Content-Encoding: nonce\x00";
        $nonce      = self::hkdf( $salt, $ikm, $nonce_info, 12 );

        if ( false === $cek || false === $nonce ) {
            return false;
        }

        // --- Criptografar com AES-128-GCM --------------------------------------
        // Padding delimiter \x02 indica último registro (RFC 8188 §2).
        $padded_plaintext = $plaintext . "\x02";

        $tag        = '';
        $ciphertext = openssl_encrypt(
            $padded_plaintext,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ( false === $ciphertext ) {
            self::log_error(
                __( 'Falha na criptografia AES-128-GCM.', 'dps-push-addon' ),
                [ 'openssl_error' => openssl_error_string() ]
            );
            return false;
        }

        // --- Montar corpo aes128gcm (RFC 8188 §2.1) ---------------------------
        // header: salt(16) || rs(4, big-endian) || idlen(1) || keyid(65)
        $record_size = pack( 'N', self::AES128GCM_RECORD_SIZE );
        $keyid_len   = chr( self::EC_P256_PUBLIC_KEY_LENGTH );

        $body = $salt . $record_size . $keyid_len . $local_public . $ciphertext . $tag;

        return $body;
    }

    /**
     * Implementação de HKDF (RFC 5869) usando hash_hkdf nativo do PHP 7.1+.
     *
     * @since 2.0.0
     * @param string $salt   Salt para HKDF.
     * @param string $ikm    Input Keying Material.
     * @param string $info   Context/application-specific info.
     * @param int    $length Tamanho da chave derivada em bytes.
     * @return string|false Chave derivada ou false em caso de erro.
     */
    private static function hkdf( $salt, $ikm, $info, $length ) {
        $derived = hash_hkdf( 'sha256', $ikm, $length, $info, $salt );

        if ( false === $derived ) {
            self::log_error(
                __( 'Falha ao derivar chave via HKDF.', 'dps-push-addon' ),
                [ 'length' => $length ]
            );
            return false;
        }

        return $derived;
    }

    /**
     * Converte chave pública EC P-256 raw (65 bytes, uncompressed) para PEM (SPKI).
     *
     * Prepende o header DER fixo para P-256 SPKI ao ponto EC e codifica em PEM.
     *
     * @since 2.0.0
     * @param string $raw_public_key Chave pública raw (65 bytes: 0x04 || x || y).
     * @return string Chave pública no formato PEM.
     */
    private static function raw_public_key_to_pem( $raw_public_key ) {
        $der_prefix = hex2bin( self::$ec_p256_der_prefix );
        $der        = $der_prefix . $raw_public_key;
        $pem        = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split( base64_encode( $der ), 64 )
            . "-----END PUBLIC KEY-----";

        return $pem;
    }

    // ------------------------------------------------------------------
    // VAPID JWT
    // ------------------------------------------------------------------

    /**
     * Gera JWT para autenticação VAPID (RFC 8292, algoritmo ES256).
     *
     * @since 1.0.0
     * @param string $audience   URL do endpoint (scheme + host).
     * @param array  $vapid_keys Chaves VAPID (deve conter 'private_pem').
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
            'exp' => time() + 86400,
            'sub' => 'mailto:admin@' . wp_parse_url( home_url(), PHP_URL_HOST ),
        ];

        $header_encoded  = self::base64url_encode( wp_json_encode( $header ) );
        $payload_encoded = self::base64url_encode( wp_json_encode( $payload ) );

        $data = $header_encoded . '.' . $payload_encoded;

        // Assinar com chave privada ECDSA.
        $private_key = openssl_pkey_get_private( $vapid_keys['private_pem'] );

        if ( ! $private_key ) {
            return false;
        }

        $signature = '';
        $result    = openssl_sign( $data, $signature, $private_key, OPENSSL_ALGO_SHA256 );

        if ( ! $result ) {
            return false;
        }

        // Converter assinatura DER para formato JWT (R || S).
        $signature = self::der_to_raw( $signature, 64 );

        return $data . '.' . self::base64url_encode( $signature );
    }

    /**
     * Converte assinatura DER para formato raw (R || S).
     *
     * As assinaturas ECDSA do OpenSSL são codificadas em DER (SEQUENCE de dois
     * INTEGERs). O formato JWT/JWS requer concatenação simples R || S,
     * cada um com 32 bytes para P-256.
     *
     * @since 1.0.0
     * @param string $der    Assinatura em formato DER.
     * @param int    $length Tamanho total esperado (64 para P-256).
     * @return string Assinatura raw (R || S).
     */
    private static function der_to_raw( $der, $length ) {
        $pos = 0;

        // Skip SEQUENCE tag.
        if ( ord( $der[ $pos++ ] ) !== 0x30 ) {
            return $der;
        }

        // Skip length byte(s).
        $len = ord( $der[ $pos++ ] );
        if ( $len & 0x80 ) {
            $pos += $len & 0x7F;
        }

        // Read R.
        if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
            return $der;
        }
        $r_len = ord( $der[ $pos++ ] );
        $r     = substr( $der, $pos, $r_len );
        $pos  += $r_len;

        // Read S.
        if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
            return $der;
        }
        $s_len = ord( $der[ $pos++ ] );
        $s     = substr( $der, $pos, $s_len );

        // Pad or trim to correct length.
        $half = $length / 2;
        $r    = str_pad( ltrim( $r, "\x00" ), $half, "\x00", STR_PAD_LEFT );
        $s    = str_pad( ltrim( $s, "\x00" ), $half, "\x00", STR_PAD_LEFT );

        return substr( $r, -$half ) . substr( $s, -$half );
    }

    // ------------------------------------------------------------------
    // Helpers de validação
    // ------------------------------------------------------------------

    /**
     * Verifica se o host do endpoint está na lista de hosts permitidos.
     *
     * Aceita correspondência exata ou subdomínio (ex.: *.fcm.googleapis.com).
     *
     * @since 2.0.0
     * @param string $host Hostname a validar.
     * @return bool True se o host for permitido.
     */
    private static function is_allowed_host( $host ) {
        foreach ( self::$allowed_hosts as $allowed ) {
            if ( $host === $allowed || str_ends_with( $host, '.' . $allowed ) ) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Helpers de encoding
    // ------------------------------------------------------------------

    /**
     * Codifica dados em base64url (RFC 4648 §5, sem padding).
     *
     * @since 1.0.0
     * @param string $data Dados a codificar.
     * @return string Dados codificados em base64url.
     */
    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Decodifica dados de base64url (RFC 4648 §5).
     *
     * @since 1.0.0
     * @param string $data Dados codificados em base64url.
     * @return string Dados decodificados.
     */
    private static function base64url_decode( $data ) {
        // Outer modulo handles the case where strlen % 4 === 0 (would yield 4 '=' without it).
        $padding = str_repeat( '=', ( 4 - strlen( $data ) % 4 ) % 4 );
        return base64_decode( strtr( $data, '-_', '+/' ) . $padding );
    }

    // ------------------------------------------------------------------
    // Helpers de logging
    // ------------------------------------------------------------------

    /**
     * Registra mensagem de erro via DPS_Logger (se disponível).
     *
     * @since 2.0.0
     * @param string $message Mensagem de erro.
     * @param array  $context Contexto adicional.
     * @return void
     */
    private static function log_error( $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::error( $message, $context, 'push' );
        }
    }

    /**
     * Registra mensagem de aviso via DPS_Logger (se disponível).
     *
     * @since 2.0.0
     * @param string $message Mensagem de aviso.
     * @param array  $context Contexto adicional.
     * @return void
     */
    private static function log_warning( $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::warning( $message, $context, 'push' );
        }
    }
}
