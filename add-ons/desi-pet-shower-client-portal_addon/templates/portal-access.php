<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__( 'Portal do Cliente – Desi Pet Shower', 'dps-client-portal' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="dps-portal-access-page">

<div class="dps-portal-access">
    <div class="dps-portal-access__card">
        <div class="dps-portal-access__logo">
            🐾
        </div>
        
        <h1 class="dps-portal-access__title">
            <?php echo esc_html__( 'Portal do Cliente – Desi Pet Shower', 'dps-client-portal' ); ?>
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
        // Busca número de WhatsApp das configurações
        $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
        
        if ( $whatsapp_number ) :
            // Limpa o número de WhatsApp
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_clean = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            } else {
                $whatsapp_clean = preg_replace( '/\D/', '', $whatsapp_number );
            }
            
            // Monta mensagem padrão
            $whatsapp_message = sprintf(
                __( 'Olá, gostaria de acesso ao Portal do Cliente. Meu nome é ______ e o nome do meu pet é ______.', 'dps-client-portal' )
            );
            
            $whatsapp_url = 'https://wa.me/' . $whatsapp_clean . '?text=' . rawurlencode( $whatsapp_message );
        ?>
        
        <a href="<?php echo esc_url( $whatsapp_url ); ?>" 
           class="dps-portal-access__button" 
           target="_blank" 
           rel="noopener noreferrer">
            <?php echo esc_html__( 'Quero acesso ao meu portal', 'dps-client-portal' ); ?>
        </a>
        
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

<style>
/* Reset básico */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body.dps-portal-access-page {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background-color: #f3f4f6;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.dps-portal-access {
    width: 100%;
    max-width: 420px;
}

.dps-portal-access__card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 32px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.dps-portal-access__logo {
    font-size: 48px;
    text-align: center;
    margin-bottom: 24px;
}

.dps-portal-access__title {
    font-size: 24px;
    font-weight: 600;
    color: #374151;
    text-align: center;
    margin-bottom: 16px;
    line-height: 1.3;
}

.dps-portal-access__description {
    font-size: 16px;
    color: #6b7280;
    line-height: 1.6;
    text-align: center;
    margin-bottom: 24px;
}

.dps-portal-access__error {
    background-color: #fef3c7;
    border: 1px solid #f59e0b;
    border-left: 4px solid #f59e0b;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 24px;
}

.dps-portal-access__error p {
    font-size: 14px;
    color: #92400e;
    line-height: 1.5;
    margin: 0;
}

.dps-portal-access__button {
    display: block;
    width: 100%;
    background-color: #0ea5e9;
    color: #ffffff;
    font-size: 16px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    padding: 14px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.dps-portal-access__button:hover {
    background-color: #0284c7;
}

.dps-portal-access__button:active {
    background-color: #0369a1;
}

.dps-portal-access__button--disabled {
    background-color: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.dps-portal-access__button--disabled:hover {
    background-color: #e5e7eb;
}

.dps-portal-access__note {
    font-size: 14px;
    color: #6b7280;
    text-align: center;
    margin-top: 16px;
    line-height: 1.5;
}

.dps-portal-access__note--error {
    color: #dc2626;
}

/* Responsividade */
@media (max-width: 480px) {
    .dps-portal-access__card {
        padding: 24px;
    }
    
    .dps-portal-access__title {
        font-size: 20px;
    }
    
    .dps-portal-access__description {
        font-size: 14px;
    }
    
    .dps-portal-access__button {
        font-size: 15px;
        padding: 12px 16px;
    }
}
</style>

<?php wp_footer(); ?>
</body>
</html>
