<?php
/**
 * Funções auxiliares do Portal do Cliente
 * 
 * Fornece funções utilitárias para gerenciar URLs e páginas do portal
 *
 * @package DPS_Client_Portal
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtém a URL da página do Portal do Cliente
 * 
 * Prioriza a configuração armazenada em options, com fallbacks:
 * 1. Página configurada via dps_portal_page_id
 * 2. Página com título "Portal do Cliente"
 * 3. URL padrão /portal-cliente/
 *
 * @return string URL da página do portal
 * @since 2.1.0
 */
function dps_get_portal_page_url() {
    // Tenta obter da configuração
    $page_id = (int) get_option( 'dps_portal_page_id', 0 );
    
    if ( $page_id > 0 ) {
        $permalink = get_permalink( $page_id );
        if ( $permalink ) {
            return $permalink;
        }
    }
    
    // Fallback: busca por título (comportamento legado)
    $portal_page = get_page_by_title( 'Portal do Cliente' );
    if ( $portal_page ) {
        return get_permalink( $portal_page->ID );
    }
    
    // Fallback final: URL genérica
    return home_url( '/portal-cliente/' );
}

/**
 * Obtém o ID da página do Portal do Cliente
 * 
 * Prioriza a configuração armazenada em options, com fallback para busca por título
 *
 * @return int|null ID da página ou null se não encontrada
 * @since 2.1.0
 */
function dps_get_portal_page_id() {
    // Tenta obter da configuração
    $page_id = (int) get_option( 'dps_portal_page_id', 0 );
    
    if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
        return $page_id;
    }
    
    // Fallback: busca por título (comportamento legado)
    $portal_page = get_page_by_title( 'Portal do Cliente' );
    if ( $portal_page ) {
        return $portal_page->ID;
    }
    
    return null;
}
