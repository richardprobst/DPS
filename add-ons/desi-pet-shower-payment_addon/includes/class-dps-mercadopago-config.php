<?php
/**
 * Classe para gerenciar configurações do Mercado Pago de forma segura.
 *
 * Implementa ordem de prioridade para tokens:
 * 1. Constantes em wp-config.php (recomendado para produção)
 * 2. Options em banco de dados (fallback para desenvolvimento)
 *
 * @package DPS_Payment_Addon
 * @since 1.1.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gerencia credenciais e configurações do Mercado Pago.
 *
 * Esta classe centraliza o acesso às credenciais do Mercado Pago,
 * permitindo que elas sejam definidas via constantes em wp-config.php
 * (recomendado para produção) ou via Settings API (útil para desenvolvimento).
 *
 * @since 1.1.0
 */
class DPS_MercadoPago_Config {

    /**
     * Retorna o access token do Mercado Pago.
     *
     * Ordem de prioridade:
     * 1. Constante DPS_MERCADOPAGO_ACCESS_TOKEN (wp-config.php)
     * 2. Option dps_mercadopago_access_token (banco de dados)
     *
     * @since 1.1.0
     * @return string Access token ou string vazia se não configurado.
     */
    public static function get_access_token() {
        // 1. Prioridade: constante definida em wp-config.php
        if ( defined( 'DPS_MERCADOPAGO_ACCESS_TOKEN' ) && DPS_MERCADOPAGO_ACCESS_TOKEN ) {
            return trim( DPS_MERCADOPAGO_ACCESS_TOKEN );
        }

        // 2. Fallback: opção salva no banco de dados
        $token = get_option( 'dps_mercadopago_access_token', '' );
        return trim( $token );
    }

    /**
     * Retorna a chave pública do Mercado Pago (se necessário).
     *
     * Ordem de prioridade:
     * 1. Constante DPS_MERCADOPAGO_PUBLIC_KEY (wp-config.php)
     * 2. Option dps_mercadopago_public_key (banco de dados)
     *
     * @since 1.1.0
     * @return string Public key ou string vazia se não configurado.
     */
    public static function get_public_key() {
        // 1. Prioridade: constante definida em wp-config.php
        if ( defined( 'DPS_MERCADOPAGO_PUBLIC_KEY' ) && DPS_MERCADOPAGO_PUBLIC_KEY ) {
            return trim( DPS_MERCADOPAGO_PUBLIC_KEY );
        }

        // 2. Fallback: opção salva no banco de dados
        $key = get_option( 'dps_mercadopago_public_key', '' );
        return trim( $key );
    }

    /**
     * Retorna o webhook secret do Mercado Pago.
     *
     * Ordem de prioridade:
     * 1. Constante DPS_MERCADOPAGO_WEBHOOK_SECRET (wp-config.php)
     * 2. Option dps_mercadopago_webhook_secret (banco de dados)
     *
     * Se nenhum secret estiver definido, retorna o access token como fallback
     * (comportamento legado mantido para compatibilidade).
     *
     * @since 1.1.0
     * @return string Webhook secret ou string vazia.
     */
    public static function get_webhook_secret() {
        // 1. Prioridade: constante definida em wp-config.php
        if ( defined( 'DPS_MERCADOPAGO_WEBHOOK_SECRET' ) && DPS_MERCADOPAGO_WEBHOOK_SECRET ) {
            return trim( DPS_MERCADOPAGO_WEBHOOK_SECRET );
        }

        // 2. Fallback: opção salva no banco de dados
        $secret = get_option( 'dps_mercadopago_webhook_secret', '' );
        if ( $secret ) {
            return trim( $secret );
        }

        // 3. Fallback legado: usar access token se nenhum secret estiver definido
        return self::get_access_token();
    }

    /**
     * Verifica se o access token está definido via constante.
     *
     * Útil para determinar se os campos de configuração devem ser
     * exibidos como somente-leitura na interface administrativa.
     *
     * @since 1.1.0
     * @return bool True se definido via constante, false caso contrário.
     */
    public static function is_access_token_from_constant() {
        return defined( 'DPS_MERCADOPAGO_ACCESS_TOKEN' ) && DPS_MERCADOPAGO_ACCESS_TOKEN;
    }

    /**
     * Verifica se a chave pública está definida via constante.
     *
     * @since 1.1.0
     * @return bool True se definido via constante, false caso contrário.
     */
    public static function is_public_key_from_constant() {
        return defined( 'DPS_MERCADOPAGO_PUBLIC_KEY' ) && DPS_MERCADOPAGO_PUBLIC_KEY;
    }

    /**
     * Verifica se o webhook secret está definido via constante.
     *
     * @since 1.1.0
     * @return bool True se definido via constante, false caso contrário.
     */
    public static function is_webhook_secret_from_constant() {
        return defined( 'DPS_MERCADOPAGO_WEBHOOK_SECRET' ) && DPS_MERCADOPAGO_WEBHOOK_SECRET;
    }

    /**
     * Retorna os últimos 4 caracteres de uma credencial (para exibição segura).
     *
     * Útil para mostrar uma prévia da credencial na interface administrativa
     * sem expor o valor completo.
     *
     * @since 1.1.0
     * @param string $credential Credencial completa.
     * @return string Últimos 4 caracteres ou máscara padrão se vazia.
     */
    public static function get_masked_credential( $credential ) {
        if ( ! is_string( $credential ) || ! $credential || strlen( $credential ) < 4 ) {
            return '••••';
        }
        return '••••' . substr( $credential, -4 );
    }
}
