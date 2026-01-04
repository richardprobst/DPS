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
        if ( $permalink && is_string( $permalink ) ) {
            return $permalink;
        }
    }
    
    // Fallback: busca por título usando WP_Query (compatível com WP 6.2+)
    $portal_page = dps_get_page_by_title_compat( 'Portal do Cliente' );
    if ( $portal_page ) {
        $permalink = get_permalink( $portal_page->ID );
        if ( $permalink && is_string( $permalink ) ) {
            return $permalink;
        }
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

/**
 * Valida se um recurso pertence ao cliente autenticado
 * 
 * Função central de validação de ownership para ações sensíveis do portal.
 * Garante que clientes só possam acessar/modificar recursos que pertencem a eles.
 * 
 * QUANDO USAR:
 * - Antes de exibir/modificar dados de agendamentos
 * - Antes de exibir/modificar dados de pets
 * - Antes de exibir mensagens/chat
 * - Antes de gerar/download de arquivos (.ics, faturas, etc.)
 * - Antes de qualquer ação que envolva dados específicos de um cliente
 * 
 * TIPOS DE RECURSOS SUPORTADOS:
 * - 'appointment': Valida se agendamento pertence ao cliente (meta: appointment_client_id)
 * - 'pet': Valida se pet pertence ao cliente (meta: owner_id)
 * - 'message': Valida se mensagem pertence ao cliente (meta: message_client_id)
 * - 'transaction': Valida se transação pertence ao cliente (meta: transaction_client_id)
 * - 'client': Valida se é o próprio cliente
 * 
 * @param int    $client_id   ID do cliente autenticado no portal
 * @param int    $resource_id ID do recurso a ser validado
 * @param string $type        Tipo do recurso (appointment, pet, message, transaction, client)
 * @return bool True se o recurso pertence ao cliente, false caso contrário
 * 
 * @example
 * // Validar ownership de agendamento antes de gerar .ics
 * $client_id = DPS_Client_Portal::get_instance()->get_current_client_id();
 * if ( ! dps_portal_assert_client_owns_resource( $client_id, $appointment_id, 'appointment' ) ) {
 *     wp_die( __( 'Você não tem permissão para acessar este recurso.', 'dps-client-portal' ) );
 * }
 * 
 * @since 2.4.0
 */
function dps_portal_assert_client_owns_resource( $client_id, $resource_id, $type ) {
    $client_id   = absint( $client_id );
    $resource_id = absint( $resource_id );
    
    // Validação básica
    if ( $client_id <= 0 || $resource_id <= 0 ) {
        DPS_Logger::log( 'warning', 'Portal ownership validation: IDs inválidos', [
            'client_id'   => $client_id,
            'resource_id' => $resource_id,
            'type'        => $type,
        ] );
        return false;
    }
    
    // Permite extensão por outros add-ons
    $pre_check = apply_filters( 'dps_portal_pre_ownership_check', null, $client_id, $resource_id, $type );
    if ( null !== $pre_check ) {
        return (bool) $pre_check;
    }
    
    $is_owner = false;
    
    switch ( $type ) {
        case 'appointment':
            // Agendamento: verifica meta appointment_client_id
            $appt_client_id = get_post_meta( $resource_id, 'appointment_client_id', true );
            $is_owner       = ( absint( $appt_client_id ) === $client_id );
            break;
            
        case 'pet':
            // Pet: verifica meta owner_id
            $owner_id = get_post_meta( $resource_id, 'owner_id', true );
            $is_owner = ( absint( $owner_id ) === $client_id );
            break;
            
        case 'message':
            // Mensagem do portal: verifica meta message_client_id
            $msg_client_id = get_post_meta( $resource_id, 'message_client_id', true );
            $is_owner      = ( absint( $msg_client_id ) === $client_id );
            break;
            
        case 'transaction':
            // Transação financeira: verifica meta transaction_client_id
            $txn_client_id = get_post_meta( $resource_id, 'transaction_client_id', true );
            $is_owner      = ( absint( $txn_client_id ) === $client_id );
            break;
            
        case 'client':
            // Cliente: verifica se é o próprio
            $is_owner = ( $resource_id === $client_id );
            break;
            
        default:
            // Tipo desconhecido: permite extensão via filtro
            DPS_Logger::log( 'warning', 'Portal ownership validation: tipo desconhecido', [
                'type' => $type,
            ] );
            break;
    }
    
    // Permite filtrar resultado final
    $is_owner = apply_filters( 'dps_portal_ownership_validated', $is_owner, $client_id, $resource_id, $type );
    
    // Log de tentativa de acesso negado (segurança)
    if ( ! $is_owner ) {
        DPS_Logger::log( 'warning', 'Portal ownership validation: acesso negado', [
            'client_id'   => $client_id,
            'resource_id' => $resource_id,
            'type'        => $type,
            'ip'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
        ] );
    }
    
    return $is_owner;
}
