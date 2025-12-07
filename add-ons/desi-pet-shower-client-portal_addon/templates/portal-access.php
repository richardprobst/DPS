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
                    default:
                        $error_message = __( 'Não foi possível validar o link. Peça um novo link de acesso à nossa equipe.', 'dps-client-portal' );
                }
                ?>
                <div class="dps-portal-access__error">
                    <p><?php echo esc_html( $error_message ); ?></p>
                </div>
            <?php endif; ?>

            <?php
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
            
            if ( $whatsapp_url ) :
            ?>
            
            <a href="<?php echo esc_url( $whatsapp_url ); ?>" 
               class="dps-portal-access__button" 
               id="dps-request-access-btn"
               target="_blank" 
               rel="noopener noreferrer">
                <?php echo esc_html__( 'Quero acesso ao meu portal', 'dps-client-portal' ); ?>
            </a>
            
            <div id="dps-access-request-feedback" class="dps-portal-access__feedback" style="display:none;"></div>
            
            <script>
            // Fase 1.4: Notifica admin quando cliente solicita acesso
            (function() {
                var btn = document.getElementById('dps-request-access-btn');
                var feedback = document.getElementById('dps-access-request-feedback');
                
                if (btn && feedback) {
                    btn.addEventListener('click', function(e) {
                        // Envia notificação em background (não bloqueia WhatsApp)
                        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=dps_request_portal_access'
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success && data.data && data.data.message) {
                                feedback.textContent = data.data.message;
                                feedback.style.display = 'block';
                                feedback.style.color = '#10b981';
                            }
                        })
                        .catch(function(error) {
                            // Silenciosamente ignora erros para não atrapalhar a experiência
                            console.log('Access request notification failed:', error);
                        });
                    });
                }
            })();
            </script>
            
            <?php else : ?>
            
            <button class="dps-portal-access__button dps-portal-access__button--disabled" disabled>
                <?php echo esc_html__( 'Quero acesso ao meu portal', 'dps-client-portal' ); ?>
            </button>
            
            <p class="dps-portal-access__note dps-portal-access__note--error">
                <?php echo esc_html__( 'Configuração de WhatsApp não encontrada. Entre em contato com a equipe.', 'dps-client-portal' ); ?>
            </p>
            
            <?php endif; ?>

            <p class="dps-portal-access__note">
                <?php echo esc_html__( 'Já tem um link de acesso? Basta clicar nele novamente para entrar.', 'dps-client-portal' ); ?>
            </p>
        </div>
    </div>
</div>
