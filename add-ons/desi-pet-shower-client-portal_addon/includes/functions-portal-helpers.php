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
    
    // Fallback: busca por título usando WP_Query (compatível com WP 6.2+)
    $portal_page = dps_get_page_by_title_compat( 'Portal do Cliente' );
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
    
    // Fallback: busca por título usando WP_Query (compatível com WP 6.2+)
    $portal_page = dps_get_page_by_title_compat( 'Portal do Cliente' );
    if ( $portal_page ) {
        return $portal_page->ID;
    }
    
    return null;
}

/**
 * Busca uma página pelo título de forma compatível com WordPress 6.2+
 * 
 * Substitui a função deprecada get_page_by_title() usando WP_Query com filtro
 * de correspondência exata de título. Esta é a forma recomendada pelo WordPress
 * para encontrar páginas por título após a depreciação de get_page_by_title().
 *
 * @param string $title     Título exato da página a ser buscada.
 * @param string $output    Tipo de retorno: OBJECT, ARRAY_A ou ARRAY_N. Padrão OBJECT.
 * @param string $post_type Tipo de post a buscar. Padrão 'page'.
 * @return WP_Post|array|null Post encontrado ou null se não encontrado.
 * @since 2.2.0
 */
function dps_get_page_by_title_compat( $title, $output = OBJECT, $post_type = 'page' ) {
    global $wpdb;

    // Busca direta por título exato usando wpdb (mais preciso e eficiente)
    $post_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status = 'publish' LIMIT 1",
            $title,
            $post_type
        )
    );

    if ( ! $post_id ) {
        return null;
    }

    $page = get_post( $post_id );

    if ( ! $page ) {
        return null;
    }

    if ( OBJECT === $output ) {
        return $page;
    } elseif ( ARRAY_A === $output ) {
        return get_object_vars( $page );
    } elseif ( ARRAY_N === $output ) {
        return array_values( get_object_vars( $page ) );
    }

    return $page;
}
