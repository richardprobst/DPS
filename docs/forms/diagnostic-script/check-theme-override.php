<?php
/**
 * Script de diagnóstico: Verifica se há override do tema para o template de consentimento
 * 
 * Como usar:
 * 1. Copie este arquivo para a raiz do WordPress
 * 2. Acesse via navegador: https://seusite.com/check-theme-override.php
 * 3. Siga as instruções exibidas
 * 
 * @package DesiPetShower
 */

// Define ABSPATH se não estiver definido (script standalone)
if ( ! defined( 'ABSPATH' ) ) {
    // Tenta carregar o WordPress
    $wp_load_path = __DIR__ . '/wp-load.php';
    if ( file_exists( $wp_load_path ) ) {
        require_once $wp_load_path;
    } else {
        die( 'WordPress não encontrado. Copie este arquivo para a raiz do WordPress.' );
    }
}

// Verifica se funções DPS existem
if ( ! function_exists( 'dps_is_template_overridden' ) ) {
    require_once WP_CONTENT_DIR . '/plugins/desi-pet-shower-base/includes/template-functions.php';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico: Template de Consentimento</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #1e40af; margin-top: 0; }
        h2 { color: #374151; margin-top: 0; }
        .status { padding: 8px 16px; border-radius: 6px; display: inline-block; margin: 8px 0; }
        .status-ok { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .code {background: #f3f4f6; padding: 12px; border-radius: 4px; overflow-x: auto; font-family: monospace; }
        .action-btn { 
            background: #0ea5e9; 
            color: white; 
            padding: 10px 20px; 
            border-radius: 6px; 
            text-decoration: none; 
            display: inline-block; 
            margin-top: 12px;
        }
        .action-btn:hover { background: #0284c7; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Diagnóstico do Template de Consentimento de Tosa</h1>
        <p>Este script verifica se há um template antigo sobrescrevendo a versão atualizada do plugin.</p>
    </div>

    <?php
    $template_name = 'tosa-consent-form.php';
    $is_overridden = dps_is_template_overridden( $template_name );
    $template_path = dps_get_template_path( $template_name );
    $theme_path = get_template_directory() . '/dps-templates/' . $template_name;
    $child_theme_path = get_stylesheet_directory() . '/dps-templates/' . $template_name;
    $plugin_path = WP_CONTENT_DIR . '/plugins/desi-pet-shower-base/templates/' . $template_name;
    
    // Verifica qual arquivo está sendo usado
    ?>

    <div class="card">
        <h2>📍 Status do Template</h2>
        
        <?php if ( $is_overridden ) : ?>
            <div class="status status-warning">
                ⚠️ <strong>TEMPLATE SOBRESCRITO PELO TEMA</strong>
            </div>
            <p><strong>O tema está sobrescrevendo o template do plugin!</strong></p>
            <p>Caminho do template sendo usado:</p>
            <div class="code"><?php echo esc_html( $template_path ); ?></div>
            
            <h3>🛠️ Solução Recomendada</h3>
            <p>Para garantir que a versão mais recente do formulário seja exibida, você tem duas opções:</p>
            
            <h4>Opção 1: Remover o arquivo do tema (Recomendado)</h4>
            <ol>
                <li>Conecte-se ao servidor via FTP ou SSH</li>
                <li>Localize e <strong>delete</strong> o arquivo:</li>
                <div class="code"><?php echo esc_html( $template_path ); ?></div>
                <li>Limpe todos os caches (navegador, WordPress, CDN)</li>
                <li>Recarregue a página do formulário</li>
            </ol>

            <h4>Opção 2: Atualizar o arquivo do tema</h4>
            <ol>
                <li>Copie o conteúdo do template do plugin:</li>
                <div class="code"><?php echo esc_html( $plugin_path ); ?></div>
                <li>Cole no arquivo do tema, substituindo o conteúdo antigo:</li>
                <div class="code"><?php echo esc_html( $template_path ); ?></div>
                <li>Limpe todos os caches</li>
            </ol>

        <?php else : ?>
            <div class="status status-ok">
                ✅ <strong>TEMPLATE DO PLUGIN ESTÁ ATIVO</strong>
            </div>
            <p>O plugin está usando sua própria versão do template, que é a mais recente.</p>
            <p>Caminho do template:</p>
            <div class="code"><?php echo esc_html( $template_path ); ?></div>
            
            <?php if ( file_exists( $theme_path ) || file_exists( $child_theme_path ) ) : ?>
                <div class="status status-warning" style="margin-top: 16px;">
                    ⚠️ <strong>AVISO:</strong> Detectado arquivo de override no tema, mas está sendo ignorado devido ao filtro de proteção implementado na PR #524.
                </div>
                <p>Para garantir estabilidade futura, considere remover o arquivo antigo do tema.</p>
            <?php endif; ?>

            <h3>🔍 Se o problema persistir</h3>
            <p>Se você ainda vê o formulário antigo, o problema pode ser cache:</p>
            <ul>
                <li><strong>Cache do navegador:</strong> Pressione Ctrl+Shift+R (ou Cmd+Shift+R no Mac) para forçar recarga</li>
                <li><strong>Cache do WordPress:</strong> Limpe o cache de plugins como WP Super Cache, W3 Total Cache, etc.</li>
                <li><strong>Cache de CDN:</strong> Se usar Cloudflare ou similar, limpe o cache no painel de controle</li>
                <li><strong>Verifique o CSS:</strong> Inspecione a página e confirme se o arquivo CSS está sendo carregado:<br>
                    <code>...assets/css/tosa-consent-form.css?ver=<?php echo filemtime( $plugin_path ); ?></code>
                </li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>📊 Informações do Sistema</h2>
        <ul>
            <li><strong>Tema ativo:</strong> <?php echo wp_get_theme()->get( 'Name' ); ?> (<?php echo wp_get_theme()->get( 'Version' ); ?>)</li>
            <li><strong>Tema pai:</strong> <?php echo wp_get_theme()->parent() ? wp_get_theme()->parent()->get( 'Name' ) : 'N/A'; ?></li>
            <li><strong>Plugin base DPS:</strong> <?php echo defined( 'DPS_BASE_VERSION' ) ? DPS_BASE_VERSION : 'não detectado'; ?></li>
        </ul>
    </div>

    <div class="card">
        <h2>🔒 Segurança</h2>
        <p><strong>IMPORTANTE:</strong> Após o diagnóstico, remova este arquivo do servidor por questões de segurança:</p>
        <div class="code">rm <?php echo __FILE__; ?></div>
    </div>
</body>
</html>
