<?php
/**
 * Template da página de configurações do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.0.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variáveis disponíveis: $active_tab
$settings    = DPS_WhiteLabel_Settings::get_settings();
$smtp        = DPS_WhiteLabel_SMTP::get_settings();
$login       = DPS_WhiteLabel_Login_Page::get_settings();
$admin_bar   = DPS_WhiteLabel_Admin_Bar::get_settings();
$maintenance = DPS_WhiteLabel_Maintenance::get_settings();
?>
<div class="wrap dps-whitelabel-wrap">
    <h1><?php esc_html_e( 'White Label', 'dps-whitelabel-addon' ); ?></h1>
    
    <?php settings_errors( 'dps_whitelabel' ); ?>
    
    <nav class="nav-tab-wrapper dps-whitelabel-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=branding' ) ); ?>" 
           class="nav-tab <?php echo 'branding' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Branding', 'dps-whitelabel-addon' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=smtp' ) ); ?>" 
           class="nav-tab <?php echo 'smtp' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'SMTP', 'dps-whitelabel-addon' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=login' ) ); ?>" 
           class="nav-tab <?php echo 'login' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Página de Login', 'dps-whitelabel-addon' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=admin-bar' ) ); ?>" 
           class="nav-tab <?php echo 'admin-bar' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Admin Bar', 'dps-whitelabel-addon' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=maintenance' ) ); ?>" 
           class="nav-tab <?php echo 'maintenance' === $active_tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Manutenção', 'dps-whitelabel-addon' ); ?>
            <?php if ( DPS_WhiteLabel_Maintenance::is_active() ) : ?>
                <span class="dps-badge-warning">!</span>
            <?php endif; ?>
        </a>
    </nav>
    
    <div class="dps-whitelabel-content">
        <?php if ( 'branding' === $active_tab ) : ?>
            <!-- Tab: Branding -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Identidade Visual', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="brand_name"><?php esc_html_e( 'Nome da Marca', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="brand_name" name="brand_name" 
                                       value="<?php echo esc_attr( $settings['brand_name'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e( 'Ex: Minha Pet Shop Sistemas', 'dps-whitelabel-addon' ); ?>">
                                <p class="description"><?php esc_html_e( 'Nome que substituirá "DPS by PRObst" em todo o sistema.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="brand_tagline"><?php esc_html_e( 'Slogan', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="brand_tagline" name="brand_tagline" 
                                       value="<?php echo esc_attr( $settings['brand_tagline'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e( 'Ex: Gestão completa para seu pet shop', 'dps-whitelabel-addon' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="brand_logo_url"><?php esc_html_e( 'Logo (Claro)', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="brand_logo_url" name="brand_logo_url" 
                                           value="<?php echo esc_url( $settings['brand_logo_url'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                    <button type="button" class="button dps-media-remove-btn" <?php echo empty( $settings['brand_logo_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remover', 'dps-whitelabel-addon' ); ?></button>
                                    <?php if ( ! empty( $settings['brand_logo_url'] ) ) : ?>
                                        <div class="dps-media-preview"><img src="<?php echo esc_url( $settings['brand_logo_url'] ); ?>" alt="Logo"></div>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Logo para uso em fundos claros. Recomendado: PNG com fundo transparente.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="brand_favicon_url"><?php esc_html_e( 'Favicon', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="brand_favicon_url" name="brand_favicon_url" 
                                           value="<?php echo esc_url( $settings['brand_favicon_url'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                    <button type="button" class="button dps-media-remove-btn" <?php echo empty( $settings['brand_favicon_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remover', 'dps-whitelabel-addon' ); ?></button>
                                </div>
                                <p class="description"><?php esc_html_e( 'Ícone do site. Recomendado: 32x32 pixels, formato ICO ou PNG.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Cores do Tema', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor Primária', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="color_primary" 
                                       value="<?php echo esc_attr( $settings['color_primary'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#0ea5e9">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor Secundária', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="color_secondary" 
                                       value="<?php echo esc_attr( $settings['color_secondary'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#10b981">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor de Destaque', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="color_accent" 
                                       value="<?php echo esc_attr( $settings['color_accent'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#f59e0b">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Informações de Contato', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="contact_email"><?php esc_html_e( 'E-mail de Contato', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="email" id="contact_email" name="contact_email" 
                                       value="<?php echo esc_attr( $settings['contact_email'] ); ?>" 
                                       class="regular-text">
                                <p class="description"><?php esc_html_e( 'Será usado como remetente de e-mails e e-mail de suporte.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="contact_whatsapp"><?php esc_html_e( 'WhatsApp', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="contact_whatsapp" name="contact_whatsapp" 
                                       value="<?php echo esc_attr( $settings['contact_whatsapp'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php esc_attr_e( 'Ex: 5511999999999', 'dps-whitelabel-addon' ); ?>">
                                <p class="description"><?php esc_html_e( 'Número com código do país, sem espaços ou caracteres especiais.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="support_url"><?php esc_html_e( 'URL de Suporte', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="url" id="support_url" name="support_url" 
                                       value="<?php echo esc_url( $settings['support_url'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="https://suporte.seusite.com">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="website_url"><?php esc_html_e( 'Site da Empresa', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="url" id="website_url" name="website_url" 
                                       value="<?php echo esc_url( $settings['website_url'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="https://www.seusite.com">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Opções de Exibição', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ocultar "Powered by"', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hide_powered_by" value="1" 
                                           <?php checked( $settings['hide_powered_by'] ); ?>>
                                    <?php esc_html_e( 'Ocultar "Powered by DPS" e referências ao desenvolvedor original', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="custom_footer_text"><?php esc_html_e( 'Texto do Footer', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="custom_footer_text" name="custom_footer_text" 
                                          rows="2" class="large-text"><?php echo esc_textarea( $settings['custom_footer_text'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Texto personalizado para exibir no rodapé do sistema.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="custom_css"><?php esc_html_e( 'CSS Customizado', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="custom_css" name="custom_css" 
                                          rows="6" class="large-text code"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'CSS adicional para personalizar a aparência do sistema.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="dps_whitelabel_save_branding" class="button button-primary">
                        <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
                    </button>
                </p>
            </form>
            
        <?php elseif ( 'smtp' === $active_tab ) : ?>
            <!-- Tab: SMTP -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Configurações SMTP', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <?php if ( class_exists( 'WPMailSMTP\Core' ) ) : ?>
                        <div class="notice notice-info inline">
                            <p><?php esc_html_e( 'O plugin WP Mail SMTP está ativo. As configurações SMTP deste módulo serão ignoradas.', 'dps-whitelabel-addon' ); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ativar SMTP', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="smtp_enabled" value="1" 
                                           <?php checked( $smtp['smtp_enabled'] ); ?>>
                                    <?php esc_html_e( 'Usar servidor SMTP para envio de e-mails', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_host"><?php esc_html_e( 'Servidor SMTP', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="smtp_host" name="smtp_host" 
                                       value="<?php echo esc_attr( $smtp['smtp_host'] ); ?>" 
                                       class="regular-text" 
                                       placeholder="smtp.gmail.com">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_port"><?php esc_html_e( 'Porta', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="smtp_port" name="smtp_port" 
                                       value="<?php echo absint( $smtp['smtp_port'] ); ?>" 
                                       class="small-text" min="1" max="65535">
                                <p class="description"><?php esc_html_e( 'Portas comuns: 25, 465 (SSL), 587 (TLS)', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Criptografia', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <select name="smtp_encryption">
                                    <option value="none" <?php selected( $smtp['smtp_encryption'], 'none' ); ?>><?php esc_html_e( 'Nenhuma', 'dps-whitelabel-addon' ); ?></option>
                                    <option value="ssl" <?php selected( $smtp['smtp_encryption'], 'ssl' ); ?>>SSL</option>
                                    <option value="tls" <?php selected( $smtp['smtp_encryption'], 'tls' ); ?>>TLS</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Autenticação', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="smtp_auth" value="1" 
                                           <?php checked( $smtp['smtp_auth'] ); ?>>
                                    <?php esc_html_e( 'Requer autenticação', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_username"><?php esc_html_e( 'Usuário', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="smtp_username" name="smtp_username" 
                                       value="<?php echo esc_attr( $smtp['smtp_username'] ); ?>" 
                                       class="regular-text" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_password"><?php esc_html_e( 'Senha', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="password" id="smtp_password" name="smtp_password" 
                                       value="" class="regular-text" autocomplete="new-password"
                                       placeholder="<?php echo ! empty( $smtp['smtp_password'] ) ? '••••••••' : ''; ?>">
                                <p class="description"><?php esc_html_e( 'Deixe em branco para manter a senha atual.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Logs de E-mail', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="log_emails" value="1" 
                                           <?php checked( $smtp['log_emails'] ); ?>>
                                    <?php esc_html_e( 'Registrar erros de envio de e-mail', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Teste de E-mail', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_email"><?php esc_html_e( 'E-mail de Teste', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="email" id="test_email" 
                                       value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" 
                                       class="regular-text">
                                <button type="button" id="dps-send-test-email" class="button">
                                    <?php esc_html_e( 'Enviar E-mail de Teste', 'dps-whitelabel-addon' ); ?>
                                </button>
                                <span id="test-email-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="dps_whitelabel_save_smtp" class="button button-primary">
                        <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
                    </button>
                </p>
            </form>
            
        <?php elseif ( 'login' === $active_tab ) : ?>
            <!-- Tab: Login Page -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Personalização da Página de Login', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ativar Personalização', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="login_enabled" value="1" 
                                           <?php checked( $login['login_enabled'] ); ?>>
                                    <?php esc_html_e( 'Personalizar página de login do WordPress', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="login_logo_url"><?php esc_html_e( 'Logo do Login', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="login_logo_url" name="login_logo_url" 
                                           value="<?php echo esc_url( $login['login_logo_url'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                    <button type="button" class="button dps-media-remove-btn" <?php echo empty( $login['login_logo_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remover', 'dps-whitelabel-addon' ); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Dimensões do Logo', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="number" name="login_logo_width" 
                                       value="<?php echo absint( $login['login_logo_width'] ); ?>" 
                                       class="small-text" min="50" max="500"> x 
                                <input type="number" name="login_logo_height" 
                                       value="<?php echo absint( $login['login_logo_height'] ); ?>" 
                                       class="small-text" min="20" max="200"> px
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Tipo de Fundo', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <select name="login_background_type" id="login_background_type">
                                    <option value="color" <?php selected( $login['login_background_type'], 'color' ); ?>><?php esc_html_e( 'Cor sólida', 'dps-whitelabel-addon' ); ?></option>
                                    <option value="image" <?php selected( $login['login_background_type'], 'image' ); ?>><?php esc_html_e( 'Imagem', 'dps-whitelabel-addon' ); ?></option>
                                    <option value="gradient" <?php selected( $login['login_background_type'], 'gradient' ); ?>><?php esc_html_e( 'Gradiente', 'dps-whitelabel-addon' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="login-bg-color">
                            <th scope="row"><?php esc_html_e( 'Cor de Fundo', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="login_background_color" 
                                       value="<?php echo esc_attr( $login['login_background_color'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#f9fafb">
                            </td>
                        </tr>
                        <tr class="login-bg-image" style="display:none;">
                            <th scope="row">
                                <label for="login_background_image"><?php esc_html_e( 'Imagem de Fundo', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="login_background_image" name="login_background_image" 
                                           value="<?php echo esc_url( $login['login_background_image'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr class="login-bg-gradient" style="display:none;">
                            <th scope="row">
                                <label for="login_background_gradient"><?php esc_html_e( 'Gradiente', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="login_background_gradient" name="login_background_gradient" 
                                       value="<?php echo esc_attr( $login['login_background_gradient'] ); ?>" 
                                       class="large-text" 
                                       placeholder="linear-gradient(135deg, #0ea5e9 0%, #10b981 100%)">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor do Botão', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="login_button_color" 
                                       value="<?php echo esc_attr( $login['login_button_color'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#0ea5e9">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="dps_whitelabel_save_login" class="button button-primary">
                        <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
                    </button>
                    <a href="<?php echo esc_url( wp_login_url() ); ?>" target="_blank" class="button">
                        <?php esc_html_e( 'Visualizar Página de Login', 'dps-whitelabel-addon' ); ?>
                    </a>
                </p>
            </form>
            
        <?php elseif ( 'admin-bar' === $active_tab ) : ?>
            <!-- Tab: Admin Bar -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Personalização da Admin Bar', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ativar Personalização', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="admin_bar_enabled" value="1" 
                                           <?php checked( $admin_bar['admin_bar_enabled'] ); ?>>
                                    <?php esc_html_e( 'Personalizar a Admin Bar do WordPress', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ocultar Itens', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hide_wp_logo" value="1" 
                                           <?php checked( $admin_bar['hide_wp_logo'] ); ?>>
                                    <?php esc_html_e( 'Ocultar logo do WordPress', 'dps-whitelabel-addon' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="hide_updates_notice" value="1" 
                                           <?php checked( $admin_bar['hide_updates_notice'] ); ?>>
                                    <?php esc_html_e( 'Ocultar avisos de atualizações', 'dps-whitelabel-addon' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="hide_comments_menu" value="1" 
                                           <?php checked( $admin_bar['hide_comments_menu'] ); ?>>
                                    <?php esc_html_e( 'Ocultar menu de comentários', 'dps-whitelabel-addon' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="hide_new_content_menu" value="1" 
                                           <?php checked( $admin_bar['hide_new_content_menu'] ); ?>>
                                    <?php esc_html_e( 'Ocultar menu "Novo"', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="custom_logo_url"><?php esc_html_e( 'Logo na Admin Bar', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="custom_logo_url" name="custom_logo_url" 
                                           value="<?php echo esc_url( $admin_bar['custom_logo_url'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                </div>
                                <p class="description"><?php esc_html_e( 'Imagem pequena (20x20px) para exibir na Admin Bar.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor da Admin Bar', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="admin_bar_color" 
                                       value="<?php echo esc_attr( $admin_bar['admin_bar_color'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#1d2327">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor do Texto', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="admin_bar_text_color" 
                                       value="<?php echo esc_attr( $admin_bar['admin_bar_text_color'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#ffffff">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="dps_whitelabel_save_admin_bar" class="button button-primary">
                        <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
                    </button>
                </p>
            </form>
            
        <?php elseif ( 'maintenance' === $active_tab ) : ?>
            <!-- Tab: Maintenance -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
                
                <div class="dps-card">
                    <h2><?php esc_html_e( 'Modo de Manutenção', 'dps-whitelabel-addon' ); ?></h2>
                    
                    <?php if ( DPS_WhiteLabel_Maintenance::is_active() ) : ?>
                        <div class="notice notice-warning inline">
                            <p><strong><?php esc_html_e( '⚠ O modo de manutenção está ATIVO!', 'dps-whitelabel-addon' ); ?></strong></p>
                            <p><?php esc_html_e( 'Visitantes não autenticados verão a página de manutenção.', 'dps-whitelabel-addon' ); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ativar Manutenção', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="maintenance_enabled" value="1" 
                                           <?php checked( $maintenance['maintenance_enabled'] ); ?>>
                                    <?php esc_html_e( 'Exibir página de manutenção para visitantes', 'dps-whitelabel-addon' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="maintenance_title"><?php esc_html_e( 'Título', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="maintenance_title" name="maintenance_title" 
                                       value="<?php echo esc_attr( $maintenance['maintenance_title'] ); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="maintenance_message"><?php esc_html_e( 'Mensagem', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <textarea id="maintenance_message" name="maintenance_message" 
                                          rows="4" class="large-text"><?php echo esc_textarea( $maintenance['maintenance_message'] ); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="maintenance_logo_url"><?php esc_html_e( 'Logo', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <div class="dps-media-uploader">
                                    <input type="text" id="maintenance_logo_url" name="maintenance_logo_url" 
                                           value="<?php echo esc_url( $maintenance['maintenance_logo_url'] ); ?>" 
                                           class="regular-text dps-media-url">
                                    <button type="button" class="button dps-media-upload-btn"><?php esc_html_e( 'Selecionar', 'dps-whitelabel-addon' ); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cor de Fundo', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <input type="text" name="maintenance_background" 
                                       value="<?php echo esc_attr( $maintenance['maintenance_background'] ); ?>" 
                                       class="dps-color-picker" data-default-color="#f9fafb">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="maintenance_countdown"><?php esc_html_e( 'Countdown (Opcional)', 'dps-whitelabel-addon' ); ?></label>
                            </th>
                            <td>
                                <input type="datetime-local" id="maintenance_countdown" name="maintenance_countdown" 
                                       value="<?php echo esc_attr( $maintenance['maintenance_countdown'] ); ?>">
                                <p class="description"><?php esc_html_e( 'Data/hora prevista para retorno do sistema.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Roles que podem acessar', 'dps-whitelabel-addon' ); ?></th>
                            <td>
                                <?php 
                                $bypass_roles = $maintenance['maintenance_bypass_roles'] ?? [ 'administrator' ];
                                $all_roles    = wp_roles()->get_names();
                                foreach ( $all_roles as $role_key => $role_name ) :
                                ?>
                                    <label>
                                        <input type="checkbox" name="maintenance_bypass_roles[]" 
                                               value="<?php echo esc_attr( $role_key ); ?>" 
                                               <?php checked( in_array( $role_key, $bypass_roles, true ) ); ?>
                                               <?php echo 'administrator' === $role_key ? 'disabled checked' : ''; ?>>
                                        <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <input type="hidden" name="maintenance_bypass_roles[]" value="administrator">
                                <p class="description"><?php esc_html_e( 'Administradores sempre têm acesso.', 'dps-whitelabel-addon' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="dps_whitelabel_save_maintenance" class="button button-primary">
                        <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
                    </button>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>
