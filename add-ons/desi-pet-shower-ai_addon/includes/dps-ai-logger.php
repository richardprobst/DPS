<?php
/**
 * Logger condicional para o AI Add-on.
 *
 * Registra logs apenas quando WP_DEBUG está habilitado ou quando a opção
 * de debug do plugin está ativada. Em produção, registra apenas erros críticos.
 *
 * @package DPS_AI_Addon
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra uma mensagem de log condicionalmente.
 *
 * Logs são registrados apenas quando:
 * - WP_DEBUG está definido como true, OU
 * - A configuração 'debug_logging' do plugin está habilitada
 *
 * Em produção (debug desabilitado), apenas logs de nível 'error' são registrados
 * para evitar poluição dos arquivos de log.
 *
 * @param string $message Mensagem a ser registrada.
 * @param string $level   Nível de log: 'debug', 'info', 'warning', 'error'. Padrão: 'info'.
 * @param array  $context Contexto adicional (opcional, para dados estruturados).
 *
 * @return void
 */
function dps_ai_log( $message, $level = 'info', $context = [] ) {
    // Verifica se debug está habilitado
    $settings = get_option( 'dps_ai_settings', [] );
    $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! empty( $settings['debug_logging'] );
    
    // Em produção, registra apenas erros críticos
    if ( ! $debug_enabled && 'error' !== $level ) {
        return;
    }
    
    // Prepara prefixo baseado no nível
    $prefix_map = [
        'debug'   => '[DPS AI DEBUG]',
        'info'    => '[DPS AI INFO]',
        'warning' => '[DPS AI WARNING]',
        'error'   => '[DPS AI ERROR]',
    ];
    
    $prefix = $prefix_map[ $level ] ?? '[DPS AI]';
    
    // Formata a mensagem
    $formatted_message = sprintf( '%s %s', $prefix, $message );
    
    // Adiciona contexto se fornecido
    if ( ! empty( $context ) ) {
        $formatted_message .= ' | Context: ' . wp_json_encode( $context );
    }
    
    // Registra usando error_log do PHP
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intencional para logging
    error_log( $formatted_message );
}

/**
 * Registra uma mensagem de debug.
 *
 * Útil para rastreamento detalhado durante desenvolvimento.
 * Não é registrado em produção (a menos que debug_logging esteja habilitado).
 *
 * @param string $message Mensagem de debug.
 * @param array  $context Contexto adicional.
 *
 * @return void
 */
function dps_ai_log_debug( $message, $context = [] ) {
    dps_ai_log( $message, 'debug', $context );
}

/**
 * Registra uma mensagem informativa.
 *
 * Útil para eventos normais do sistema que valem documentação.
 * Não é registrado em produção (a menos que debug_logging esteja habilitado).
 *
 * @param string $message Mensagem informativa.
 * @param array  $context Contexto adicional.
 *
 * @return void
 */
function dps_ai_log_info( $message, $context = [] ) {
    dps_ai_log( $message, 'info', $context );
}

/**
 * Registra uma mensagem de aviso.
 *
 * Indica situações anormais que não são necessariamente erros.
 * Não é registrado em produção (a menos que debug_logging esteja habilitado).
 *
 * @param string $message Mensagem de aviso.
 * @param array  $context Contexto adicional.
 *
 * @return void
 */
function dps_ai_log_warning( $message, $context = [] ) {
    dps_ai_log( $message, 'warning', $context );
}

/**
 * Registra uma mensagem de erro.
 *
 * Indica falhas críticas que requerem atenção.
 * Sempre é registrado, mesmo em produção.
 *
 * @param string $message Mensagem de erro.
 * @param array  $context Contexto adicional.
 *
 * @return void
 */
function dps_ai_log_error( $message, $context = [] ) {
    dps_ai_log( $message, 'error', $context );
}
