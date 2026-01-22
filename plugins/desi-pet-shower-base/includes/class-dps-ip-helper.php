<?php
/**
 * Helper para obtenção e validação de endereços IP
 *
 * Centraliza a lógica de detecção de IP do cliente, suportando proxies,
 * CDNs (Cloudflare) e ambientes de desenvolvimento.
 *
 * @package DesiPetShower
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe helper para operações com endereços IP
 */
class DPS_IP_Helper {

    /**
     * Obtém o endereço IP do cliente de forma simples.
     *
     * Usa apenas REMOTE_ADDR, que é a fonte mais confiável e não pode ser
     * falsificada pelo cliente. Recomendado para a maioria dos casos.
     *
     * @since 2.5.0
     *
     * @return string Endereço IP sanitizado ou 'unknown' se não disponível.
     *
     * @example
     * $ip = DPS_IP_Helper::get_ip();
     * // Retorna: '192.168.1.100' ou 'unknown'
     */
    public static function get_ip() {
        if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return 'unknown';
    }

    /**
     * Obtém o endereço IP do cliente com suporte a proxies e CDNs.
     *
     * Verifica múltiplos headers em ordem de prioridade para obter o IP
     * real do cliente mesmo atrás de proxies reversos ou CDNs como Cloudflare.
     *
     * Headers verificados (em ordem):
     * 1. HTTP_CF_CONNECTING_IP - Cloudflare
     * 2. HTTP_X_REAL_IP - Nginx proxy
     * 3. HTTP_X_FORWARDED_FOR - Proxy padrão (primeiro IP da lista)
     * 4. REMOTE_ADDR - Conexão direta
     *
     * @since 2.5.0
     *
     * @return string Endereço IP validado ou string vazia se não encontrado.
     *
     * @example
     * $ip = DPS_IP_Helper::get_ip_with_proxy_support();
     * // Retorna: '203.0.113.50' (IP real do cliente através do proxy)
     */
    public static function get_ip_with_proxy_support() {
        // Headers a verificar, em ordem de prioridade
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Proxy padrão
            'REMOTE_ADDR',           // Direto
        ];
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip_list = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                
                // X-Forwarded-For pode ter múltiplos IPs (client, proxy1, proxy2)
                // Pega o primeiro (cliente real)
                if ( strpos( $ip_list, ',' ) !== false ) {
                    $ips = explode( ',', $ip_list );
                    $ip_list = trim( $ips[0] );
                }
                
                // Valida IPv4 ou IPv6
                if ( filter_var( $ip_list, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    return $ip_list;
                }
                
                if ( filter_var( $ip_list, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                    return $ip_list;
                }
            }
        }
        
        return '';
    }

    /**
     * Obtém um hash do endereço IP para uso em rate limiting.
     *
     * Usa SHA-256 para criar um hash irreversível do IP, permitindo
     * armazenar referências de IP sem expor o endereço real.
     * Inclui fallback para X-Forwarded-For quando REMOTE_ADDR é localhost.
     *
     * @since 2.5.0
     *
     * @param string $salt Prefixo opcional para o hash (padrão: 'dps_ip_').
     * @return string Hash SHA-256 do IP.
     *
     * @example
     * $hash = DPS_IP_Helper::get_ip_hash();
     * // Retorna: 'a1b2c3d4e5f6...' (64 caracteres)
     *
     * $hash = DPS_IP_Helper::get_ip_hash( 'rate_limit_' );
     * // Retorna hash com prefixo customizado
     */
    public static function get_ip_hash( $salt = 'dps_ip_' ) {
        $ip = '';
        
        // Prioriza REMOTE_ADDR por segurança
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        // Fallback para X-Forwarded-For (primeiro IP apenas) se REMOTE_ADDR for localhost/proxy
        if ( ( empty( $ip ) || in_array( $ip, [ '127.0.0.1', '::1' ], true ) ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ips = explode( ',', $forwarded );
            $ip = trim( $ips[0] );
        }
        
        // Hash para não armazenar IP diretamente (sha256 mais seguro que md5)
        return hash( 'sha256', $salt . $ip );
    }

    /**
     * Valida se uma string é um endereço IP válido.
     *
     * Suporta IPv4 e IPv6.
     *
     * @since 2.5.0
     *
     * @param string $ip Endereço IP a validar.
     * @return bool True se válido, false caso contrário.
     *
     * @example
     * DPS_IP_Helper::is_valid_ip( '192.168.1.1' );     // true
     * DPS_IP_Helper::is_valid_ip( '2001:db8::1' );      // true
     * DPS_IP_Helper::is_valid_ip( 'not-an-ip' );        // false
     */
    public static function is_valid_ip( $ip ) {
        return (bool) filter_var( $ip, FILTER_VALIDATE_IP );
    }

    /**
     * Valida se uma string é um endereço IPv4 válido.
     *
     * @since 2.5.0
     *
     * @param string $ip Endereço IP a validar.
     * @return bool True se IPv4 válido, false caso contrário.
     */
    public static function is_valid_ipv4( $ip ) {
        return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
    }

    /**
     * Valida se uma string é um endereço IPv6 válido.
     *
     * @since 2.5.0
     *
     * @param string $ip Endereço IP a validar.
     * @return bool True se IPv6 válido, false caso contrário.
     */
    public static function is_valid_ipv6( $ip ) {
        return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
    }

    /**
     * Verifica se o IP é localhost/loopback.
     *
     * Útil para detectar ambiente de desenvolvimento.
     *
     * @since 2.5.0
     *
     * @param string|null $ip Endereço IP a verificar. Se null, usa IP atual.
     * @return bool True se localhost, false caso contrário.
     */
    public static function is_localhost( $ip = null ) {
        if ( null === $ip ) {
            $ip = self::get_ip();
        }
        
        $localhost_ips = [
            '127.0.0.1',
            '::1',
            'localhost',
        ];
        
        return in_array( $ip, $localhost_ips, true );
    }

    /**
     * Anonimiza um endereço IP para compliance com LGPD/GDPR.
     *
     * Para IPv4: zera o último octeto (192.168.1.100 → 192.168.1.0)
     * Para IPv6: zera os últimos 80 bits
     *
     * @since 2.5.0
     *
     * @param string $ip Endereço IP a anonimizar.
     * @return string IP anonimizado ou string vazia se inválido.
     *
     * @example
     * DPS_IP_Helper::anonymize( '192.168.1.100' ); // '192.168.1.0'
     * DPS_IP_Helper::anonymize( '2001:db8::1' );    // '2001:db8::'
     */
    public static function anonymize( $ip ) {
        if ( self::is_valid_ipv4( $ip ) ) {
            // IPv4: zera último octeto
            $parts = explode( '.', $ip );
            $parts[3] = '0';
            return implode( '.', $parts );
        }
        
        if ( self::is_valid_ipv6( $ip ) ) {
            // IPv6: mantém apenas os primeiros 48 bits (/48)
            $expanded = inet_pton( $ip );
            if ( false === $expanded ) {
                return '';
            }
            // Zera os últimos 10 bytes (80 bits)
            $anonymized = substr( $expanded, 0, 6 ) . str_repeat( "\x00", 10 );
            return inet_ntop( $anonymized );
        }
        
        return '';
    }
}
