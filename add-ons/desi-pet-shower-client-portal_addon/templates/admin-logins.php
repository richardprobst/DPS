<?php
/**
 * Template para a tela administrativa de gerenciamento de logins/tokens
 * 
 * Variáveis disponíveis:
 * @var array  $clients            Lista de clientes
 * @var string $context            Contexto ('admin' ou 'frontend')
 * @var string $base_url           URL base da página
 * @var array  $feedback_messages  Mensagens de feedback
 * 
 * @package DPS_Client_Portal
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title_tag = ( 'admin' === $context ) ? 'h1' : 'h2';

if ( 'admin' === $context ) {
    echo '<div class="wrap">';
}

echo '<div class="dps-portal-logins">';
echo '<' . esc_attr( $title_tag ) . ' class="dps-portal-logins__title">' . esc_html__( 'Portal do Cliente → Logins', 'dps-client-portal' ) . '</' . esc_attr( $title_tag ) . '>';

// Mensagens de feedback
if ( ! empty( $feedback_messages ) ) {
    echo '<div class="dps-portal-logins__feedback">';
    foreach ( $feedback_messages as $feedback ) {
        $notice_class = 'success' === $feedback['type'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html( $feedback['text'] ) . '</p></div>';
    }
    echo '</div>';
}

// Descrição
echo '<p class="dps-portal-logins__description">';
echo esc_html__( 'Gerenciar os links de acesso ao Portal do Cliente. Gere links exclusivos para cada cliente, envie por WhatsApp ou e-mail, e controle o acesso de forma segura.', 'dps-client-portal' );
echo '</p>';

// Resumo
echo '<div class="dps-portal-logins__summary">';
echo '<div class="dps-portal-logins__summary-item">';
echo '<span class="dps-portal-logins__summary-label">' . esc_html__( 'Total de Clientes:', 'dps-client-portal' ) . '</span> ';
echo '<strong>' . esc_html( number_format_i18n( count( $clients ) ) ) . '</strong>';
echo '</div>';
echo '</div>';

// Filtros de busca
echo '<form method="get" action="' . esc_url( $base_url ) . '" class="dps-portal-logins__filters">';
if ( 'frontend' === $context ) {
    echo '<input type="hidden" name="tab" value="logins" />';
}
echo '<input type="search" name="dps_search" placeholder="' . esc_attr__( 'Buscar por nome ou telefone...', 'dps-client-portal' ) . '" value="' . esc_attr( isset( $_GET['dps_search'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_search'] ) ) : '' ) . '" />';
echo '<button type="submit" class="button">' . esc_html__( 'Buscar', 'dps-client-portal' ) . '</button>';
echo '</form>';

// Tabela de clientes
if ( ! empty( $clients ) ) {
    echo '<div class="dps-portal-logins__table-wrapper">';
    echo '<table class="dps-portal-logins__table widefat striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . esc_html__( 'Cliente', 'dps-client-portal' ) . '</th>';
    echo '<th>' . esc_html__( 'Contato', 'dps-client-portal' ) . '</th>';
    echo '<th>' . esc_html__( 'Situação do Acesso', 'dps-client-portal' ) . '</th>';
    echo '<th>' . esc_html__( 'Último Login', 'dps-client-portal' ) . '</th>';
    echo '<th>' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ( $clients as $client_data ) {
        $client_id = $client_data['id'];
        $stats     = $client_data['token_stats'];
        
        // Determina situação do acesso
        if ( $stats['active_tokens'] > 0 ) {
            $access_status = __( 'Link ativo', 'dps-client-portal' );
            $status_class  = 'active';
        } elseif ( $stats['total_used'] > 0 ) {
            $access_status = __( 'Já acessou', 'dps-client-portal' );
            $status_class  = 'used';
        } else {
            $access_status = __( 'Sem acesso ainda', 'dps-client-portal' );
            $status_class  = 'none';
        }
        
        // Formata último login
        $last_login = $stats['last_used_at'] 
            ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $stats['last_used_at'] ) )
            : '—';

        echo '<tr>';
        
        // Cliente
        echo '<td data-label="' . esc_attr__( 'Cliente', 'dps-client-portal' ) . '">';
        echo '<strong>' . esc_html( $client_data['name'] ) . '</strong>';
        echo '<br><small>#' . esc_html( $client_id ) . '</small>';
        echo '</td>';
        
        // Contato
        echo '<td data-label="' . esc_attr__( 'Contato', 'dps-client-portal' ) . '">';
        if ( $client_data['phone'] ) {
            echo '<div>📱 ' . esc_html( $client_data['phone'] ) . '</div>';
        }
        if ( $client_data['email'] ) {
            echo '<div>✉️ ' . esc_html( $client_data['email'] ) . '</div>';
        }
        if ( ! $client_data['phone'] && ! $client_data['email'] ) {
            echo '—';
        }
        echo '</td>';
        
        // Situação
        echo '<td data-label="' . esc_attr__( 'Situação', 'dps-client-portal' ) . '">';
        echo '<span class="dps-portal-logins__status dps-portal-logins__status--' . esc_attr( $status_class ) . '">';
        echo esc_html( $access_status );
        echo '</span>';
        if ( $stats['active_tokens'] > 0 ) {
            echo '<br><small>' . esc_html( sprintf( _n( '%d token ativo', '%d tokens ativos', $stats['active_tokens'], 'dps-client-portal' ), $stats['active_tokens'] ) ) . '</small>';
        }
        echo '</td>';
        
        // Último login
        echo '<td data-label="' . esc_attr__( 'Último Login', 'dps-client-portal' ) . '">';
        echo esc_html( $last_login );
        echo '</td>';
        
        // Ações
        echo '<td data-label="' . esc_attr__( 'Ações', 'dps-client-portal' ) . '">';
        echo '<div class="dps-portal-logins__actions">';
        
        // Gera URLs com nonce para ambos os tipos
        $url_temporary = wp_nonce_url(
            add_query_arg( [
                'dps_action'  => 'generate_token',
                'client_id'   => $client_id,
                'token_type'  => 'login',
            ], $base_url ),
            'dps_generate_token_' . $client_id
        );
        
        $url_permanent = wp_nonce_url(
            add_query_arg( [
                'dps_action'  => 'generate_token',
                'client_id'   => $client_id,
                'token_type'  => 'permanent',
            ], $base_url ),
            'dps_generate_token_' . $client_id
        );
        
        // Determina qual botão mostrar
        if ( $stats['total_generated'] === 0 ) {
            // Primeiro acesso - mostra botão que abre modal
            echo '<button type="button" class="button button-primary dps-generate-token-btn" ';
            echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
            echo 'data-client-name="' . esc_attr( $client_data['name'] ) . '" ';
            echo 'data-url-temporary="' . esc_attr( $url_temporary ) . '" ';
            echo 'data-url-permanent="' . esc_attr( $url_permanent ) . '">';
            echo esc_html__( 'Gerar Link de Acesso', 'dps-client-portal' );
            echo '</button>';
        } else {
            // Gerar novo link - mostra botão que abre modal
            echo '<button type="button" class="button dps-generate-token-btn" ';
            echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
            echo 'data-client-name="' . esc_attr( $client_data['name'] ) . '" ';
            echo 'data-url-temporary="' . esc_attr( $url_temporary ) . '" ';
            echo 'data-url-permanent="' . esc_attr( $url_permanent ) . '">';
            echo esc_html__( 'Gerar Novo Link', 'dps-client-portal' );
            echo '</button>';
        }
        
        // Revogar (se houver tokens ativos)
        if ( $stats['active_tokens'] > 0 ) {
            $revoke_url = wp_nonce_url(
                add_query_arg( [
                    'dps_action' => 'revoke_tokens',
                    'client_id'  => $client_id,
                ], $base_url ),
                'dps_revoke_tokens_' . $client_id
            );
            echo '<a href="' . esc_url( $revoke_url ) . '" class="button button-secondary" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja revogar todos os links ativos deste cliente?', 'dps-client-portal' ) ) . '\');">';
            echo esc_html__( 'Revogar', 'dps-client-portal' );
            echo '</a>';
        }
        
        echo '</div>';
        
        // Se houver token gerado recentemente, mostra opções de envio
        $generated_token = get_transient( 'dps_portal_generated_token_' . $client_id );
        if ( $generated_token && isset( $generated_token['url'] ) ) {
            echo '<div class="dps-portal-logins__token-display">';
            echo '<div class="dps-portal-logins__token-url">';
            echo '<input type="text" readonly value="' . esc_attr( $generated_token['url'] ) . '" onclick="this.select();" />';
            echo '<button type="button" class="button button-small dps-copy-token" data-url="' . esc_attr( $generated_token['url'] ) . '">' . esc_html__( 'Copiar', 'dps-client-portal' ) . '</button>';
            echo '</div>';
            
            echo '<div class="dps-portal-logins__send-options">';
            
            // Botão WhatsApp
            if ( $client_data['phone'] ) {
                $whatsapp_url = wp_nonce_url(
                    add_query_arg( [
                        'dps_action' => 'whatsapp_link',
                        'client_id'  => $client_id,
                    ], $base_url ),
                    'dps_whatsapp_link_' . $client_id
                );
                echo '<a href="' . esc_url( $whatsapp_url ) . '" class="button button-small" target="_blank">';
                echo '📱 ' . esc_html__( 'Enviar por WhatsApp', 'dps-client-portal' );
                echo '</a>';
            }
            
            // Botão E-mail
            if ( $client_data['email'] ) {
                echo '<button type="button" class="button button-small dps-preview-email" data-client-id="' . esc_attr( $client_id ) . '" data-url="' . esc_attr( $generated_token['url'] ) . '">';
                echo '✉️ ' . esc_html__( 'Preparar E-mail', 'dps-client-portal' );
                echo '</button>';
            }
            
            echo '</div>';
            
            // Determina mensagem de validade baseado no tipo
            $token_type = isset( $generated_token['type'] ) ? $generated_token['type'] : 'login';
            if ( 'permanent' === $token_type ) {
                $validity_note = __( 'Link permanente - válido até revogar manualmente', 'dps-client-portal' );
            } else {
                $validity_note = __( 'Link válido por 30 minutos', 'dps-client-portal' );
            }
            
            echo '<small class="dps-portal-logins__token-note">' . esc_html( $validity_note ) . '</small>';
            echo '</div>';
        }
        
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<p class="dps-portal-logins__empty">' . esc_html__( 'Nenhum cliente encontrado.', 'dps-client-portal' ) . '</p>';
}

echo '</div>'; // .dps-portal-logins

if ( 'admin' === $context ) {
    echo '</div>'; // .wrap
}

// Modal de pré-visualização de e-mail
?>
<div id="dps-email-preview-modal" class="dps-modal" style="display:none;">
    <div class="dps-modal__overlay"></div>
    <div class="dps-modal__content">
        <div class="dps-modal__header">
            <h2><?php esc_html_e( 'Pré-visualização do E-mail', 'dps-client-portal' ); ?></h2>
            <button type="button" class="dps-modal__close">&times;</button>
        </div>
        <div class="dps-modal__body">
            <div class="dps-form-field">
                <label for="dps-email-subject"><?php esc_html_e( 'Assunto:', 'dps-client-portal' ); ?></label>
                <input type="text" id="dps-email-subject" class="widefat" />
            </div>
            <div class="dps-form-field">
                <label for="dps-email-body"><?php esc_html_e( 'Mensagem:', 'dps-client-portal' ); ?></label>
                <textarea id="dps-email-body" rows="10" class="widefat"></textarea>
            </div>
        </div>
        <div class="dps-modal__footer">
            <button type="button" class="button button-secondary dps-modal__close"><?php esc_html_e( 'Cancelar', 'dps-client-portal' ); ?></button>
            <button type="button" class="button button-primary" id="dps-confirm-send-email"><?php esc_html_e( 'Confirmar Envio', 'dps-client-portal' ); ?></button>
        </div>
    </div>
</div>

<!-- Modal de seleção de tipo de token -->
<div id="dps-token-type-modal" class="dps-modal" style="display:none;">
    <div class="dps-modal__overlay"></div>
    <div class="dps-modal__content">
        <div class="dps-modal__header">
            <h2><?php esc_html_e( 'Gerar Link de Acesso', 'dps-client-portal' ); ?></h2>
            <button type="button" class="dps-modal__close">&times;</button>
        </div>
        <div class="dps-modal__body">
            <p id="dps-token-client-name" style="margin-bottom: 20px; font-weight: 600;"></p>
            
            <div class="dps-form-field">
                <label><?php esc_html_e( 'Tipo de Link:', 'dps-client-portal' ); ?></label>
                <div style="margin-top: 12px;">
                    <label style="display: block; margin-bottom: 12px; padding: 12px; border: 2px solid #e5e7eb; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="dps_token_type" value="login" checked style="margin-right: 8px;">
                        <strong><?php esc_html_e( 'Temporário (30 minutos)', 'dps-client-portal' ); ?></strong>
                        <br>
                        <small style="color: #6b7280; margin-left: 24px;">
                            <?php esc_html_e( 'O link expira após 30 minutos. Ideal para acesso único e imediato.', 'dps-client-portal' ); ?>
                        </small>
                    </label>
                    
                    <label style="display: block; padding: 12px; border: 2px solid #e5e7eb; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="dps_token_type" value="permanent" style="margin-right: 8px;">
                        <strong><?php esc_html_e( 'Permanente (até revogar)', 'dps-client-portal' ); ?></strong>
                        <br>
                        <small style="color: #6b7280; margin-left: 24px;">
                            <?php esc_html_e( 'O link permanece válido até que seja revogado manualmente. Ideal para acesso recorrente.', 'dps-client-portal' ); ?>
                        </small>
                    </label>
                </div>
            </div>
        </div>
        <div class="dps-modal__footer">
            <button type="button" class="button button-secondary dps-modal__close"><?php esc_html_e( 'Cancelar', 'dps-client-portal' ); ?></button>
            <button type="button" class="button button-primary" id="dps-confirm-generate-token"><?php esc_html_e( 'Gerar Link', 'dps-client-portal' ); ?></button>
        </div>
    </div>
</div>

<style>
/* Estilos para radio buttons personalizados */
#dps-token-type-modal input[type="radio"]:checked + strong {
    color: #0ea5e9;
}

#dps-token-type-modal label:has(input[type="radio"]:checked) {
    border-color: #0ea5e9;
    background-color: #f0f9ff;
}

#dps-token-type-modal label:hover {
    border-color: #0ea5e9;
}

/* Estilos básicos para a tela de logins */
.dps-portal-logins {
    max-width: 1200px;
}

.dps-portal-logins__title {
    margin-bottom: 20px;
}

.dps-portal-logins__description {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}

.dps-portal-logins__summary {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 20px;
}

.dps-portal-logins__filters {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.dps-portal-logins__filters input[type="search"] {
    flex: 1;
    max-width: 400px;
}

.dps-portal-logins__table-wrapper {
    overflow-x: auto;
}

.dps-portal-logins__status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.dps-portal-logins__status--active {
    background: #d1fae5;
    color: #065f46;
}

.dps-portal-logins__status--used {
    background: #f3f4f6;
    color: #374151;
}

.dps-portal-logins__status--none {
    background: #fef3c7;
    color: #92400e;
}

.dps-portal-logins__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.dps-portal-logins__token-display {
    margin-top: 12px;
    padding: 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
}

.dps-portal-logins__token-url {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.dps-portal-logins__token-url input {
    flex: 1;
    font-family: monospace;
    font-size: 12px;
}

.dps-portal-logins__send-options {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.dps-portal-logins__token-note {
    display: block;
    color: #6b7280;
    font-size: 12px;
}

/* Modal */
.dps-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.dps-modal__overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}

.dps-modal__content {
    position: relative;
    max-width: 600px;
    margin: 50px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.dps-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.dps-modal__header h2 {
    margin: 0;
}

.dps-modal__close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
}

.dps-modal__body {
    padding: 20px;
}

.dps-modal__footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
}

.dps-modal__footer .button {
    margin-left: 8px;
}

.dps-form-field {
    margin-bottom: 16px;
}

.dps-form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

/* Responsividade */
@media (max-width: 782px) {
    .dps-portal-logins__table thead {
        display: none;
    }
    
    .dps-portal-logins__table tr {
        display: block;
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
    }
    
    .dps-portal-logins__table td {
        display: block;
        text-align: right;
        padding: 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .dps-portal-logins__table td:before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
    }
    
    .dps-portal-logins__actions {
        justify-content: flex-end;
    }
}
</style>
