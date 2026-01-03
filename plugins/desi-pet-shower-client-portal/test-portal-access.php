<?php
/**
 * Script de Teste: Verificação de Acesso ao Portal do Cliente
 * 
 * Este script pode ser executado via WP-CLI para testar se o portal está configurado corretamente.
 * 
 * USO:
 * wp eval-file add-ons/desi-pet-shower-client-portal/test-portal-access.php
 * 
 * OU adicione ?dps_test_portal=1 na URL do site (apenas para administradores)
 * 
 * @package DPS_Client_Portal
 * @since 2.4.1
 */

// Verifica se está sendo executado dentro do WordPress
if ( ! defined( 'ABSPATH' ) ) {
    // Se não, tenta carregar WordPress
    require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';
}

// Verifica se o add-on está ativo
if ( ! function_exists( 'dps_get_portal_page_url' ) ) {
    echo "ERRO: Add-on Client Portal não está ativo.\n";
    exit( 1 );
}

echo "=== TESTE DE CONFIGURAÇÃO DO PORTAL DO CLIENTE ===\n\n";

// 1. Verificar página configurada
echo "1. Verificando página configurada...\n";
$page_id = get_option( 'dps_portal_page_id', 0 );
if ( $page_id ) {
    echo "   ✓ Página configurada (ID: {$page_id})\n";
    
    $page = get_post( $page_id );
    if ( $page ) {
        echo "   ✓ Página existe: \"{$page->post_title}\"\n";
        echo "   ✓ Status: {$page->post_status}\n";
        echo "   ✓ URL: " . get_permalink( $page_id ) . "\n";
        
        if ( has_shortcode( (string) $page->post_content, 'dps_client_portal' ) ) {
            echo "   ✓ Shortcode [dps_client_portal] presente\n";
        } else {
            echo "   ✗ ERRO: Shortcode [dps_client_portal] NÃO encontrado\n";
        }
    } else {
        echo "   ✗ ERRO: Página não existe\n";
    }
} else {
    echo "   ✗ AVISO: Nenhuma página configurada\n";
}

echo "\n2. Verificando helper de URL...\n";
$portal_url = dps_get_portal_page_url();
echo "   ✓ URL retornada: {$portal_url}\n";

// 3. Verificar tabela de tokens
echo "\n3. Verificando tabela de tokens...\n";
global $wpdb;
$table_name = $wpdb->prefix . 'dps_portal_tokens';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Script de diagnóstico
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

if ( $table_exists ) {
    echo "   ✓ Tabela {$table_name} existe\n";
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Script de diagnóstico, nome de tabela interno
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    echo "   ✓ Total de tokens: {$count}\n";
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Script de diagnóstico
    $active = $wpdb->get_var( 
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
            WHERE expires_at > %s 
            AND used_at IS NULL 
            AND revoked_at IS NULL",
            current_time( 'mysql' )
        )
    );
    echo "   ✓ Tokens ativos: {$active}\n";
} else {
    echo "   ✗ ERRO: Tabela {$table_name} não existe\n";
}

// 4. Verificar classes necessárias
echo "\n4. Verificando classes necessárias...\n";
$required_classes = [
    'DPS_Client_Portal',
    'DPS_Portal_Token_Manager',
    'DPS_Portal_Session_Manager',
];

foreach ( $required_classes as $class ) {
    if ( class_exists( $class ) ) {
        echo "   ✓ {$class}\n";
    } else {
        echo "   ✗ ERRO: {$class} não encontrada\n";
    }
}

// 5. Gerar token de teste (se houver clientes)
echo "\n5. Testando geração de token...\n";
$clients = get_posts( [
    'post_type'      => 'dps_cliente',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
    'fields'         => 'ids',
] );

if ( ! empty( $clients ) ) {
    $test_client_id = $clients[0];
    $client_name = get_the_title( $test_client_id );
    echo "   ✓ Cliente de teste encontrado: {$client_name} (ID: {$test_client_id})\n";
    
    if ( class_exists( 'DPS_Portal_Token_Manager' ) ) {
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $test_token = $token_manager->generate_token( $test_client_id, 'login', 30 );
        
        if ( $test_token ) {
            echo "   ✓ Token gerado com sucesso\n";
            $access_url = add_query_arg( 'dps_token', $test_token, $portal_url );
            echo "   ✓ URL de acesso:\n";
            echo "     {$access_url}\n";
            echo "   ⚠  ATENÇÃO: Este token é real e válido por 30 minutos.\n";
        } else {
            echo "   ✗ ERRO: Falha ao gerar token\n";
        }
    }
} else {
    echo "   ⚠  AVISO: Nenhum cliente cadastrado para testar\n";
}

echo "\n=== FIM DO TESTE ===\n";

// Se executado via HTTP (não WP-CLI), retorna como JSON
if ( isset( $_GET['dps_test_portal'] ) && current_user_can( 'manage_options' ) ) {
    $result = [
        'page_configured' => (bool) $page_id,
        'page_exists'     => isset( $page ) && $page !== null,
        'has_shortcode'   => isset( $page ) && has_shortcode( (string) $page->post_content, 'dps_client_portal' ),
        'table_exists'    => $table_exists,
        'portal_url'      => $portal_url,
    ];
    
    wp_send_json_success( $result );
}
