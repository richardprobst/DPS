<?php
/**
 * Template: Portal Access Screen (Shortcode Fragment)
 * 
 * Este template é usado pelo shortcode [dps_client_portal] quando o cliente
 * não está autenticado. Ele exibe informações sobre como obter acesso ao portal.
 * 
 * IMPORTANTE: Este é um FRAGMENTO HTML para ser inserido em uma página,
 * NÃO um documento HTML completo. O tema já fornece <html>, <head>, <body>, etc.
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dps-client-portal-access-page">
    <div class="dps-portal-access">
        <div class="dps-portal-access__card">
            <div class="dps-portal-access__logo">
                🐾
            </div>
            
            <h1 class="dps-portal-access__title">
                <?php echo esc_html__( 'Portal do Cliente – DPS by PRObst', 'dps-client-portal' ); ?>
            </h1>
            
            <p class="dps-portal-access__description">
                <?php 
                echo esc_html__( 
                    'Acompanhe seus agendamentos, histórico, assinaturas e informações do seu pet em um só lugar. Para acessar o portal, peça à nossa equipe o seu link exclusivo.', 
                    'dps-client-portal' 
                ); 
                ?>
            </p>

            <?php
            // Exibe mensagem de erro se token inválido
            $show_contact_section = true; // Default: mostrar seção de contato
            
            if ( isset( $_GET['token_error'] ) ) :
                $error_type = sanitize_text_field( wp_unslash( $_GET['token_error'] ) );
                $error_message = '';
                
                switch ( $error_type ) {
                    case 'invalid':
                        $error_message = __( 'Esse link não é mais válido. Peça um novo link de acesso à nossa equipe.', 'dps-client-portal' );
                        break;
                    case 'expired':
                        $error_message = __( 'Esse link expirou. Peça um novo link de acesso à nossa equipe.', 'dps-client-portal' );
                        break;
                    case 'used':
                        $error_message = __( 'Esse link já foi utilizado. Peça um novo link de acesso à nossa equipe.', 'dps-client-portal' );
                        break;
                    case 'page_not_found':
                        $error_message = __( 'A página do Portal do Cliente ainda não foi configurada. Entre em contato com nossa equipe.', 'dps-client-portal' );
                        $show_contact_section = false; // Não mostrar botão de WhatsApp neste caso
                        break;
                    default:
                        $error_message = __( 'Não foi possível validar o link. Peça um novo link de acesso à nossa equipe.', 'dps-client-portal' );
                }
                ?>
                <div class="dps-portal-access__error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <?php
            // Seção de contato - exibe apenas se não houver erro de configuração
            if ( $show_contact_section ) :
            
            // Gera link para o cliente solicitar acesso via WhatsApp
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $whatsapp_message = DPS_WhatsApp_Helper::get_portal_access_request_message();
                $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
            } else {
                // Fallback: busca número das configurações
                $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
                
                if ( $whatsapp_number ) {
                    if ( class_exists( 'DPS_Phone_Helper' ) ) {
                        $whatsapp_clean = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
                    } else {
                        $whatsapp_clean = preg_replace( '/\D/', '', $whatsapp_number );
                    }
                    
                    $whatsapp_message = sprintf(
                        __( 'Olá, gostaria de acesso ao Portal do Cliente. Meu nome é ______ e o nome do meu pet é ______.', 'dps-client-portal' )
                    );
                    
                    $whatsapp_url = 'https://wa.me/' . $whatsapp_clean . '?text=' . rawurlencode( $whatsapp_message );
                } else {
                    $whatsapp_url = '';
                }
            }
            ?>
            
            <!-- Formulário para solicitar link por email (auto-envio) -->
            <div class="dps-portal-access__email-section">
                <h2 class="dps-portal-access__subtitle">
                    <?php echo esc_html__( '📧 Receba seu link por e-mail', 'dps-client-portal' ); ?>
                </h2>
                <p class="dps-portal-access__email-description">
                    <?php echo esc_html__( 'Se você tem e-mail cadastrado, digite abaixo para receber o link automaticamente:', 'dps-client-portal' ); ?>
                </p>
                
                <form id="dps-email-access-form" class="dps-portal-access__email-form">
                    <?php wp_nonce_field( 'dps_request_access_link', '_dps_access_nonce', false ); ?>
                    <div class="dps-portal-access__email-input-group">
                        <input 
                            type="email" 
                            id="dps-access-email" 
                            name="email" 
                            placeholder="<?php echo esc_attr__( 'seu@email.com', 'dps-client-portal' ); ?>"
                            class="dps-portal-access__email-input"
                            required
                            autocomplete="email"
                        >
                        <button type="submit" class="dps-portal-access__email-button" id="dps-email-submit-btn">
                            <?php echo esc_html__( 'Enviar Link', 'dps-client-portal' ); ?>
                        </button>
                    </div>
                </form>
                
                <div id="dps-email-feedback" class="dps-portal-access__feedback" style="display:none;"></div>
            </div>
            
            <?php if ( $whatsapp_url ) : ?>
            <!-- Seção de WhatsApp (para quem não tem email cadastrado) -->
            <div class="dps-portal-access__whatsapp-section" id="dps-whatsapp-section">
                <p class="dps-portal-access__divider">
                    <?php echo esc_html__( 'ou', 'dps-client-portal' ); ?>
                </p>
                <p class="dps-portal-access__whatsapp-description">
                    <?php echo esc_html__( 'Não tem e-mail cadastrado? Solicite via WhatsApp:', 'dps-client-portal' ); ?>
                </p>
                
                <a href="<?php echo esc_url( $whatsapp_url ); ?>" 
                   class="dps-portal-access__button dps-portal-access__button--secondary" 
                   id="dps-request-access-btn"
                   target="_blank" 
                   rel="noopener noreferrer">
                    <?php echo esc_html__( '📱 Solicitar via WhatsApp', 'dps-client-portal' ); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <script>
            (function() {
                var form = document.getElementById('dps-email-access-form');
                var emailInput = document.getElementById('dps-access-email');
                var submitBtn = document.getElementById('dps-email-submit-btn');
                var feedback = document.getElementById('dps-email-feedback');
                var whatsappSection = document.getElementById('dps-whatsapp-section');
                var whatsappBtn = document.getElementById('dps-request-access-btn');
                var nonceField = document.getElementById('_dps_access_nonce');
                
                if (form && emailInput && submitBtn && feedback && nonceField) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        var email = emailInput.value.trim();
                        var nonce = nonceField.value;
                        
                        if (!email) {
                            feedback.textContent = '<?php echo esc_js( __( 'Por favor, informe seu e-mail.', 'dps-client-portal' ) ); ?>';
                            feedback.style.display = 'block';
                            feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                            return;
                        }
                        
                        // Desabilita botão durante envio
                        submitBtn.disabled = true;
                        submitBtn.textContent = '<?php echo esc_js( __( 'Enviando...', 'dps-client-portal' ) ); ?>';
                        feedback.style.display = 'none';
                        
                        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=dps_request_access_link_by_email&email=' + encodeURIComponent(email) + '&_wpnonce=' + encodeURIComponent(nonce)
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = '<?php echo esc_js( __( 'Enviar Link', 'dps-client-portal' ) ); ?>';
                            
                            if (data.success) {
                                feedback.textContent = data.data.message;
                                feedback.style.display = 'block';
                                feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--success';
                                emailInput.value = '';
                            } else {
                                feedback.textContent = data.data.message;
                                feedback.style.display = 'block';
                                feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                                
                                // Destaca a seção de WhatsApp se o email não foi encontrado
                                if (data.data.show_whatsapp && whatsappSection) {
                                    whatsappSection.classList.add('dps-portal-access__whatsapp-section--highlight');
                                }
                            }
                        })
                        .catch(function(error) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = '<?php echo esc_js( __( 'Enviar Link', 'dps-client-portal' ) ); ?>';
                            feedback.textContent = '<?php echo esc_js( __( 'Erro ao processar solicitação. Tente novamente.', 'dps-client-portal' ) ); ?>';
                            feedback.style.display = 'block';
                            feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                        });
                    });
                }
                
                // Notifica admin quando cliente clica no WhatsApp
                if (whatsappBtn) {
                    whatsappBtn.addEventListener('click', function(e) {
                        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=dps_request_portal_access'
                        }).catch(function(error) {
                            console.log('Access request notification failed:', error);
                        });
                    });
                }
            })();
            </script>
            
            <?php endif; // Fim da condicional $show_contact_section ?>

            <p class="dps-portal-access__note">
                <?php echo esc_html__( 'Já tem um link de acesso? Basta clicar nele novamente para entrar.', 'dps-client-portal' ); ?>
            </p>
        </div>
    </div>
</div>
