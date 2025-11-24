<?php
/**
 * Template para configurações do Portal do Cliente
 * 
 * Variáveis disponíveis:
 * @var int    $portal_page_id      ID da página configurada do portal
 * @var string $portal_url          URL atual do portal
 * @var array  $pages               Lista de páginas disponíveis
 * @var string $base_url            URL base da página de configurações
 * @var array  $feedback_messages   Mensagens de feedback
 * 
 * @package DPS_Client_Portal
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="dps-portal-settings">';
echo '<h2 class="dps-portal-settings__title">' . esc_html__( 'Portal do Cliente → Configurações', 'dps-client-portal' ) . '</h2>';

// Mensagens de feedback
if ( ! empty( $feedback_messages ) ) {
    echo '<div class="dps-portal-settings__feedback">';
    foreach ( $feedback_messages as $feedback ) {
        $notice_class = 'success' === $feedback['type'] ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . esc_html( $feedback['text'] ) . '</p></div>';
    }
    echo '</div>';
}

// Descrição
echo '<p class="dps-portal-settings__description">';
echo esc_html__( 'Configure a página do Portal do Cliente e gerencie os links de acesso que serão enviados aos clientes.', 'dps-client-portal' );
echo '</p>';

// Formulário de configurações
echo '<form method="post" action="" class="dps-portal-settings__form">';
wp_nonce_field( 'dps_save_portal_settings', '_dps_portal_settings_nonce' );

echo '<fieldset class="dps-fieldset">';
echo '<legend>' . esc_html__( 'Configuração da Página do Portal', 'dps-client-portal' ) . '</legend>';

// Campo: Página do Portal
echo '<div class="dps-form-field">';
echo '<label for="dps_portal_page_id">' . esc_html__( 'Página do Portal do Cliente:', 'dps-client-portal' ) . '</label>';
echo '<select name="dps_portal_page_id" id="dps_portal_page_id" class="dps-select">';
echo '<option value="0">' . esc_html__( '— Selecione uma página —', 'dps-client-portal' ) . '</option>';

foreach ( $pages as $page ) {
    $selected = selected( $portal_page_id, $page->ID, false );
    echo '<option value="' . esc_attr( $page->ID ) . '" ' . $selected . '>';
    echo esc_html( $page->post_title );
    echo '</option>';
}

echo '</select>';
echo '<p class="dps-field-description">';
echo esc_html__( 'Selecione a página onde o shortcode [dps_client_portal] está inserido.', 'dps-client-portal' );
echo '</p>';
echo '</div>';

echo '</fieldset>';

// Seção: Link do Portal
echo '<fieldset class="dps-fieldset">';
echo '<legend>' . esc_html__( 'Link de Acesso ao Portal', 'dps-client-portal' ) . '</legend>';

echo '<div class="dps-form-field">';
echo '<label>' . esc_html__( 'URL do Portal:', 'dps-client-portal' ) . '</label>';
echo '<div class="dps-portal-url-display">';
echo '<input type="text" readonly value="' . esc_attr( $portal_url ) . '" onclick="this.select();" class="dps-input-url" />';
echo '<button type="button" class="button button-secondary dps-copy-url" data-url="' . esc_attr( $portal_url ) . '">';
echo esc_html__( 'Copiar Link', 'dps-client-portal' );
echo '</button>';
echo '</div>';
echo '<p class="dps-field-description">';
echo esc_html__( 'Este é o link base do portal. Os clientes acessarão através de links personalizados com token gerados na aba "Logins de Clientes".', 'dps-client-portal' );
echo '</p>';
echo '</div>';

echo '</fieldset>';

// Seção: Instruções
echo '<fieldset class="dps-fieldset">';
echo '<legend>' . esc_html__( 'Como Usar', 'dps-client-portal' ) . '</legend>';

echo '<div class="dps-instructions">';
echo '<ol class="dps-instructions-list">';
echo '<li>' . esc_html__( 'Crie uma página no WordPress (ex: "Portal do Cliente")', 'dps-client-portal' ) . '</li>';
echo '<li>' . esc_html__( 'Adicione o shortcode [dps_client_portal] no conteúdo da página', 'dps-client-portal' ) . '</li>';
echo '<li>' . esc_html__( 'Selecione essa página no campo acima e salve', 'dps-client-portal' ) . '</li>';
echo '<li>' . esc_html__( 'Acesse a aba "Logins de Clientes" para gerar links de acesso personalizados', 'dps-client-portal' ) . '</li>';
echo '<li>' . esc_html__( 'Envie os links por WhatsApp ou e-mail para seus clientes', 'dps-client-portal' ) . '</li>';
echo '</ol>';

echo '<div class="dps-instructions-note">';
echo '<strong>' . esc_html__( 'Importante:', 'dps-client-portal' ) . '</strong> ';
echo esc_html__( 'Os clientes acessam o portal através de links com token único e temporário (válido por 30 minutos). Não é necessário criar senhas manualmente.', 'dps-client-portal' );
echo '</div>';
echo '</div>';

echo '</fieldset>';

// Botão salvar
echo '<div class="dps-form-actions">';
echo '<button type="submit" name="dps_save_portal_settings" class="button button-primary">';
echo esc_html__( 'Salvar Configurações', 'dps-client-portal' );
echo '</button>';
echo '</div>';

echo '</form>';

echo '</div>'; // .dps-portal-settings
?>

<style>
/* Estilos para a página de configurações do portal */
.dps-portal-settings {
    max-width: 800px;
}

.dps-portal-settings__title {
    margin-bottom: 20px;
}

.dps-portal-settings__description {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}

.dps-portal-settings__form {
    background: #fff;
}

.dps-fieldset {
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.dps-fieldset legend {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    padding: 0 8px;
}

.dps-form-field {
    margin-bottom: 16px;
}

.dps-form-field:last-child {
    margin-bottom: 0;
}

.dps-form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.dps-field-description {
    margin-top: 8px;
    font-size: 13px;
    color: #6b7280;
    font-style: italic;
}

.dps-select {
    width: 100%;
    max-width: 500px;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-size: 14px;
}

.dps-portal-url-display {
    display: flex;
    gap: 8px;
    align-items: center;
}

.dps-input-url {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
    background: #f9fafb;
}

.dps-copy-url {
    white-space: nowrap;
}

.dps-instructions {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 16px;
}

.dps-instructions-list {
    margin: 0 0 16px 20px;
    padding: 0;
}

.dps-instructions-list li {
    margin-bottom: 8px;
    color: #374151;
}

.dps-instructions-note {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 12px;
    border-radius: 4px;
    font-size: 13px;
    color: #92400e;
}

.dps-form-actions {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
}

/* Responsividade */
@media (max-width: 768px) {
    .dps-portal-url-display {
        flex-direction: column;
        align-items: stretch;
    }
    
    .dps-copy-url {
        width: 100%;
    }
}
</style>

<script>
// Script para copiar URL
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const copyButtons = document.querySelectorAll('.dps-copy-url');
        
        copyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const url = this.dataset.url || this.previousElementSibling.value;
                
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function() {
                        const originalText = button.textContent;
                        button.textContent = '✓ Copiado!';
                        button.style.background = '#10b981';
                        button.style.color = '#fff';
                        
                        setTimeout(function() {
                            button.textContent = originalText;
                            button.style.background = '';
                            button.style.color = '';
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Erro ao copiar:', err);
                        alert('Erro ao copiar. Selecione e copie manualmente.');
                    });
                } else {
                    // Fallback para navegadores antigos
                    const input = button.previousElementSibling;
                    input.select();
                    document.execCommand('copy');
                    
                    const originalText = button.textContent;
                    button.textContent = '✓ Copiado!';
                    setTimeout(function() {
                        button.textContent = originalText;
                    }, 2000);
                }
            });
        });
    });
})();
</script>
