# Plano de Implementação - White Label Add-on

**Baseado em:** `docs/analysis/WHITELABEL_ADDON_COMPLETE_ANALYSIS.md`  
**Data:** 2025-12-06  
**Status:** Planejamento

---

## Índice de Fases

- **[Fase 1](#fase-1-correções-críticas-de-segurança)** - Correções Críticas de Segurança (v1.1.1)
- **[Fase 2](#fase-2-otimizações-de-performance)** - Otimizações de Performance (v1.1.2)
- **[Fase 3](#fase-3-melhorias-de-ux-básicas)** - Melhorias de UX Básicas (v1.2.0)
- **[Fase 4](#fase-4-funcionalidades-essenciais)** - Funcionalidades Essenciais (v1.2.1)
- **[Fase 5](#fase-5-recursos-avançados)** - Recursos Avançados (v1.3.0)
- **[Fase 6](#fase-6-integrações-e-escalabilidade)** - Integrações e Escalabilidade (v1.4.0)

---

## FASE 1: Correções Críticas de Segurança
**Versão:** 1.1.1  
**Prioridade:** 🔴 URGENTE  
**Tempo Estimado:** 2-3 dias  
**Dependências:** Nenhuma

### Objetivos
Corrigir vulnerabilidades de segurança e problemas críticos que podem comprometer a integridade do sistema.

### Tarefas

#### 1.1. Corrigir Validação de Open Redirect
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-access-control.php`  
**Linhas:** 226-254

**Problema:**
```php
// ANTES - Vulnerável se configuração for manipulada no DB
private function get_login_url() {
    $settings = self::get_settings();
    switch ( $settings['redirect_type'] ?? 'custom_login' ) {
        case 'custom_url':
            $custom_url = ! empty( $settings['redirect_url'] ) ? $settings['redirect_url'] : '';
            if ( ! empty( $custom_url ) ) {
                $parsed = parse_url( $custom_url );
                if ( ! isset( $parsed['host'] ) || $parsed['host'] === $_SERVER['HTTP_HOST'] ) {
                    return $custom_url;
                }
            }
            return wp_login_url();
        // ...
    }
}
```

**Solução:**
```php
// DEPOIS - Validação redundante ao redirecionar
private function get_login_url() {
    $settings = self::get_settings();
    
    switch ( $settings['redirect_type'] ?? 'custom_login' ) {
        case 'custom_url':
            $custom_url = ! empty( $settings['redirect_url'] ) ? $settings['redirect_url'] : '';
            
            if ( ! empty( $custom_url ) ) {
                // Validação robusta contra open redirect
                $parsed = parse_url( $custom_url );
                $current_host = $_SERVER['HTTP_HOST'];
                
                // Permitir apenas:
                // 1. URLs relativas (sem host)
                // 2. URLs do mesmo domínio
                if ( ! isset( $parsed['host'] ) || $parsed['host'] === $current_host ) {
                    // Sanitizar URL antes de retornar
                    return esc_url_raw( $custom_url );
                }
                
                // Log de tentativa suspeita
                if ( class_exists( 'DPS_Logger' ) ) {
                    DPS_Logger::warning(
                        sprintf(
                            'Tentativa de open redirect bloqueada. URL: %s, Host esperado: %s',
                            $custom_url,
                            $current_host
                        ),
                        'whitelabel-security'
                    );
                }
            }
            
            // Fallback seguro
            return wp_login_url();
            
        case 'wp_login':
            return wp_login_url();
            
        case 'custom_login':
        default:
            $login_page_id = get_option( 'dps_custom_login_page_id' );
            return $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
    }
}
```

**Testes:**
- [ ] Configurar `redirect_url` com domínio externo
- [ ] Verificar que redireciona para `wp_login_url()` ao invés de domínio externo
- [ ] Verificar que URLs relativas funcionam normalmente
- [ ] Verificar que URLs do mesmo domínio funcionam
- [ ] Verificar log de segurança é gerado

---

#### 1.2. Melhorar Sanitização de CSS Customizado
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-settings.php`  
**Linhas:** 172-195

**Problema:**
Regex pode ser contornada com encoding (ex: `\74` = 't', bypass de `data:`)

**Solução:**
```php
/**
 * Sanitiza CSS customizado usando safecss_filter_attr() do WordPress.
 *
 * @param string $css CSS a ser sanitizado.
 * @return string CSS sanitizado.
 */
public static function sanitize_custom_css( $css ) {
    if ( empty( $css ) ) {
        return '';
    }
    
    // Remove tags HTML primeiro
    $css = wp_strip_all_tags( $css );
    
    // Usa função nativa do WordPress que é mais robusta
    // Nota: safecss_filter_attr() é para propriedades individuais
    // Para CSS completo, usamos validação manual mais rigorosa
    
    // Remove comentários
    $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
    
    // Lista de propriedades/valores perigosos
    $dangerous_patterns = [
        '/javascript\s*:/i',
        '/expression\s*\(/i',
        '/behavior\s*:/i',
        '/-moz-binding\s*:/i',
        '/vbscript\s*:/i',
        '/@import/i',
        '/url\s*\(\s*["\']?\s*data:/i', // Bloqueia data URIs
    ];
    
    foreach ( $dangerous_patterns as $pattern ) {
        $css = preg_replace( $pattern, '/* BLOCKED */', $css );
    }
    
    // Validação adicional: remove qualquer octal/hex encoding suspeito em URLs
    $css = preg_replace_callback(
        '/url\s*\([^)]*\)/i',
        function( $matches ) {
            $url = $matches[0];
            // Remove encoding hexadecimal/octal
            if ( preg_match('/\\\\[0-9a-f]{2,4}/i', $url ) ) {
                return '/* BLOCKED - encoded chars */';
            }
            return $url;
        },
        $css
    );
    
    // Aplicar filtro para permitir customização
    $css = apply_filters( 'dps_whitelabel_sanitize_custom_css', $css );
    
    return $css;
}
```

**Testes:**
- [ ] Tentar injetar `url(da\74a:text/html,<script>)` - deve bloquear
- [ ] Tentar `url(\6A avascript:alert(1))` - deve bloquear
- [ ] CSS legítimo com `url(../images/bg.jpg)` - deve permitir
- [ ] CSS legítimo com seletores complexos - deve permitir

---

#### 1.3. Implementar Validação de Logo
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-settings.php`  
**Linhas:** 99-170

**Problema:**
Método `validate_logo_url()` existe (linhas 198-226) mas nunca é chamado em `handle_settings_save()`.

**Solução:**
```php
// Em handle_settings_save(), após sanitizar URLs de logo:

// Validar URLs de logo
$logo_fields = [ 'brand_logo_url', 'brand_logo_dark_url', 'brand_favicon_url' ];

foreach ( $logo_fields as $field ) {
    if ( ! empty( $new_settings[ $field ] ) ) {
        if ( ! self::validate_logo_url( $new_settings[ $field ] ) ) {
            add_settings_error(
                'dps_whitelabel',
                'invalid_' . $field,
                sprintf(
                    /* translators: %s: nome do campo */
                    __( 'URL de %s inválida. Formatos permitidos: JPG, PNG, GIF, SVG, WebP, ICO.', 'dps-whitelabel-addon' ),
                    str_replace( '_', ' ', $field )
                ),
                'warning'
            );
            // Define como vazio ao invés de salvar URL inválida
            $new_settings[ $field ] = '';
        }
    }
}

// Salva configurações...
update_option( self::OPTION_NAME, $new_settings );
```

**Testes:**
- [ ] Upload de JPG válido - deve aceitar
- [ ] Upload de PNG válido - deve aceitar
- [ ] Upload de SVG válido - deve aceitar
- [ ] Tentar URL de PDF - deve rejeitar com mensagem clara
- [ ] Tentar URL de executável - deve rejeitar

---

### Checklist de Validação da Fase 1

- [ ] Todas as correções implementadas
- [ ] Testes de segurança executados
- [ ] Code review com foco em segurança
- [ ] Documentação atualizada (CHANGELOG.md)
- [ ] Criar tag `v1.1.1`

---

## FASE 2: Otimizações de Performance
**Versão:** 1.1.2  
**Prioridade:** 🟠 ALTA  
**Tempo Estimado:** 2-3 dias  
**Dependências:** Fase 1 concluída

### Objetivos
Melhorar a performance do add-on através de cache e otimização de queries.

### Tarefas

#### 2.1. Implementar Cache de CSS Customizado
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-assets.php`  
**Linhas:** 36-63

**Problema:**
CSS é regenerado em cada pageload via `generate_custom_css()`.

**Solução:**
```php
/**
 * Gera CSS customizado baseado nas configurações (com cache).
 *
 * @return string CSS gerado.
 */
private function generate_custom_css() {
    // Tenta obter do cache
    $cache_key = 'dps_whitelabel_custom_css';
    $cached_css = get_transient( $cache_key );
    
    if ( false !== $cached_css ) {
        return $cached_css;
    }
    
    // Se não há cache, gera CSS
    $settings = DPS_WhiteLabel_Settings::get_settings();
    $colors   = DPS_WhiteLabel_Branding::get_colors();
    $css      = '';
    
    // Aplica cores primárias
    if ( ! empty( $colors['primary'] ) ) {
        $css .= ".dps-btn-primary, .dps-button-primary { background-color: {$colors['primary']}; border-color: {$colors['primary']}; }\n";
        $css .= ".dps-link-primary, a.dps-link { color: {$colors['primary']}; }\n";
        $css .= ".dps-nav .dps-nav-item.active { border-color: {$colors['primary']}; }\n";
    }
    
    // Aplica cores secundárias
    if ( ! empty( $colors['secondary'] ) ) {
        $css .= ".dps-btn-secondary { background-color: {$colors['secondary']}; border-color: {$colors['secondary']}; }\n";
        $css .= ".dps-alert-success { border-left-color: {$colors['secondary']}; }\n";
    }
    
    // Aplica cor de destaque
    if ( ! empty( $colors['accent'] ) ) {
        $css .= ".dps-alert-warning { border-left-color: {$colors['accent']}; }\n";
        $css .= ".dps-badge-accent { background-color: {$colors['accent']}; }\n";
    }
    
    // CSS customizado do usuário
    $custom_css = $settings['custom_css'] ?? '';
    if ( ! empty( $custom_css ) ) {
        $css .= "\n/* Custom CSS */\n" . $custom_css . "\n";
    }
    
    // Armazena no cache por 24 horas
    set_transient( $cache_key, $css, DAY_IN_SECONDS );
    
    return $css;
}

/**
 * Invalida cache de CSS ao salvar configurações.
 */
public static function invalidate_css_cache() {
    delete_transient( 'dps_whitelabel_custom_css' );
}
```

**Hook para invalidar cache:**
```php
// Em class-dps-whitelabel-settings.php, após salvar:
do_action( 'dps_whitelabel_settings_saved', $new_settings );
DPS_WhiteLabel_Assets::invalidate_css_cache(); // Adicionar esta linha
```

**Testes:**
- [ ] Primeira visita gera CSS e armazena em transient
- [ ] Segunda visita usa CSS do cache (verificar com query monitor)
- [ ] Salvar configurações invalida cache
- [ ] CSS atualizado aparece após salvar

---

#### 2.2. Otimizar Verificação de Hook para Assets Admin
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-assets.php`  
**Linhas:** 48-56

**Problema:**
`strpos( $hook, 'dps' )` pode carregar CSS em páginas não-DPS.

**Solução:**
```php
/**
 * Enfileira estilos customizados no admin.
 *
 * @param string $hook Hook da página atual.
 */
public function enqueue_admin_custom_styles( $hook ) {
    // Lista whitelist de hooks DPS
    $allowed_hooks = [
        'toplevel_page_desi-pet-shower',
        'desi-pet-shower_page_dps-agenda',
        'desi-pet-shower_page_dps-finance',
        'desi-pet-shower_page_dps-loyalty',
        'desi-pet-shower_page_dps-whitelabel',
        'desi-pet-shower_page_dps-ai',
        'desi-pet-shower_page_dps-debugging',
    ];
    
    // Permitir filtro para adicionar hooks customizados
    $allowed_hooks = apply_filters( 'dps_whitelabel_admin_hooks', $allowed_hooks );
    
    // Verifica se hook atual está na lista permitida
    $is_dps_page = false;
    foreach ( $allowed_hooks as $allowed_hook ) {
        if ( $hook === $allowed_hook || strpos( $hook, $allowed_hook ) === 0 ) {
            $is_dps_page = true;
            break;
        }
    }
    
    if ( ! $is_dps_page ) {
        return;
    }
    
    $custom_css = $this->generate_custom_css();
    
    if ( ! empty( $custom_css ) ) {
        wp_add_inline_style( 'dps-admin-style', $custom_css );
    }
}
```

**Testes:**
- [ ] CSS carregado em páginas DPS (agenda, finance, etc.)
- [ ] CSS NÃO carregado em páginas WordPress padrão (posts, pages)
- [ ] CSS NÃO carregado em páginas de outros plugins com "dps" no nome

---

#### 2.3. Adicionar Cache Estático de Objeto para Settings
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-settings.php`  
**Linhas:** 74-82

**Problema:**
`wp_parse_args()` executado em cada chamada a `get_settings()`.

**Solução:**
```php
/**
 * Cache estático de settings.
 *
 * @var array|null
 */
private static $settings_cache = null;

/**
 * Obtém configurações atuais (com cache).
 *
 * @param bool $force_refresh Forçar recarregamento do cache.
 * @return array Configurações mescladas com padrões.
 */
public static function get_settings( $force_refresh = false ) {
    if ( null === self::$settings_cache || $force_refresh ) {
        $saved = get_option( self::OPTION_NAME, [] );
        self::$settings_cache = wp_parse_args( $saved, self::get_defaults() );
    }
    
    return self::$settings_cache;
}

/**
 * Limpa cache de settings.
 */
public static function clear_cache() {
    self::$settings_cache = null;
}
```

**Aplicar em todas as classes de settings:**
- `class-dps-whitelabel-smtp.php`
- `class-dps-whitelabel-login-page.php`
- `class-dps-whitelabel-admin-bar.php`
- `class-dps-whitelabel-maintenance.php`
- `class-dps-whitelabel-access-control.php`

**Hook para limpar cache ao salvar:**
```php
// Em handle_settings_save(), após update_option():
self::clear_cache();
```

**Testes:**
- [ ] Primeira chamada a `get_settings()` faz query ao DB
- [ ] Chamadas subsequentes usam cache (verificar com query monitor)
- [ ] Salvar settings limpa cache
- [ ] Force refresh funciona

---

### Checklist de Validação da Fase 2

- [ ] Cache de CSS implementado e testado
- [ ] Verificação de hooks otimizada
- [ ] Cache estático de settings implementado em todas as classes
- [ ] Performance medida antes e depois (usar Query Monitor)
- [ ] Documentação atualizada (CHANGELOG.md)
- [ ] Criar tag `v1.1.2`

---

## FASE 3: Melhorias de UX Básicas
**Versão:** 1.2.0  
**Prioridade:** 🟡 MÉDIA  
**Tempo Estimado:** 4-5 dias  
**Dependências:** Fase 2 concluída

### Objetivos
Melhorar a experiência do usuário com validações em tempo real e feedback visual.

### Tarefas

#### 3.1. Adicionar Validação de URLs em Tempo Real (JavaScript)
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/assets/js/whitelabel-admin.js`  
**Adicionar nova função**

**Implementação:**
```javascript
/**
 * Valida URLs em tempo real.
 */
function initUrlValidation() {
    var $urlInputs = $(
        'input[name="brand_logo_url"], ' +
        'input[name="brand_logo_dark_url"], ' +
        'input[name="brand_favicon_url"], ' +
        'input[name="website_url"], ' +
        'input[name="support_url"], ' +
        'input[name="redirect_url"]'
    );
    
    $urlInputs.on('blur', function() {
        var $input = $(this);
        var url = $input.val().trim();
        var $feedback = $input.next('.url-validation-feedback');
        
        // Remove feedback anterior
        $feedback.remove();
        $input.removeClass('url-valid url-invalid');
        
        if ( ! url ) {
            return; // Campo vazio é válido (opcional)
        }
        
        // Valida formato básico de URL
        var urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
        
        if ( urlPattern.test( url ) ) {
            $input.addClass('url-valid');
            $input.after('<span class="url-validation-feedback valid">✓ URL válida</span>');
        } else {
            $input.addClass('url-invalid');
            $input.after('<span class="url-validation-feedback invalid">✗ URL inválida</span>');
        }
    });
}

// Adicionar ao $(document).ready():
$(document).ready(function() {
    initColorPickers();
    initMediaUploaders();
    initLoginBackgroundToggle();
    initTestEmail();
    initUrlValidation(); // ADICIONAR
});
```

**CSS correspondente** (`whitelabel-admin.css`):
```css
/* URL Validation Feedback */
input.url-valid {
    border-color: #10b981 !important;
}

input.url-invalid {
    border-color: #ef4444 !important;
}

.url-validation-feedback {
    display: inline-block;
    margin-left: 10px;
    font-size: 12px;
    font-weight: 500;
}

.url-validation-feedback.valid {
    color: #10b981;
}

.url-validation-feedback.invalid {
    color: #ef4444;
}
```

---

#### 3.2. Adicionar Indicadores de Campos Recomendados
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/templates/admin-settings.php`  
**Várias linhas**

**Solução:**
```php
<!-- Exemplo: campo Nome da Marca -->
<th scope="row">
    <label for="brand_name">
        <?php esc_html_e( 'Nome da Marca', 'dps-whitelabel-addon' ); ?>
        <span class="dps-field-recommended" title="<?php esc_attr_e( 'Campo recomendado', 'dps-whitelabel-addon' ); ?>">*</span>
    </label>
</th>

<!-- Exemplo: campo Logo -->
<th scope="row">
    <label for="brand_logo_url">
        <?php esc_html_e( 'Logo (Claro)', 'dps-whitelabel-addon' ); ?>
        <span class="dps-field-recommended" title="<?php esc_attr_e( 'Campo recomendado', 'dps-whitelabel-addon' ); ?>">*</span>
    </label>
</th>
```

**CSS:**
```css
.dps-field-recommended {
    color: #f59e0b;
    font-weight: 700;
    margin-left: 3px;
    cursor: help;
}
```

---

#### 3.3. Melhorar Feedback Visual ao Salvar
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/assets/js/whitelabel-admin.js`

**Implementação:**
```javascript
/**
 * Scroll automático para mensagens de sucesso/erro.
 */
function initSaveScrollBehavior() {
    var $form = $('.dps-whitelabel-wrap form');
    
    $form.on('submit', function() {
        // Após submit, aguarda reload e scroll para o topo
        setTimeout(function() {
            if ( $('.notice, .dps-alert').length ) {
                $('html, body').animate({
                    scrollTop: $('.dps-whitelabel-wrap').offset().top - 50
                }, 300);
            }
        }, 100);
    });
}

// Adicionar ao ready:
$(document).ready(function() {
    // ...
    initSaveScrollBehavior();
});
```

---

#### 3.4. Adicionar Paletas de Cores Pré-definidas
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/templates/admin-settings.php`  
**Aba Branding, seção de cores**

**Implementação:**
```php
<!-- Após os color pickers, adicionar presets -->
<tr>
    <th scope="row">
        <?php esc_html_e( 'Paletas Pré-definidas', 'dps-whitelabel-addon' ); ?>
    </th>
    <td>
        <div class="dps-color-presets">
            <button type="button" class="button dps-preset-btn" data-preset="default">
                <?php esc_html_e( 'Padrão DPS', 'dps-whitelabel-addon' ); ?>
            </button>
            <button type="button" class="button dps-preset-btn" data-preset="ocean">
                <?php esc_html_e( 'Oceano', 'dps-whitelabel-addon' ); ?>
            </button>
            <button type="button" class="button dps-preset-btn" data-preset="forest">
                <?php esc_html_e( 'Floresta', 'dps-whitelabel-addon' ); ?>
            </button>
            <button type="button" class="button dps-preset-btn" data-preset="sunset">
                <?php esc_html_e( 'Pôr do Sol', 'dps-whitelabel-addon' ); ?>
            </button>
            <button type="button" class="button dps-preset-btn" data-preset="modern">
                <?php esc_html_e( 'Moderno', 'dps-whitelabel-addon' ); ?>
            </button>
        </div>
        <p class="description">
            <?php esc_html_e( 'Clique em uma paleta para aplicar cores harmonizadas automaticamente.', 'dps-whitelabel-addon' ); ?>
        </p>
    </td>
</tr>
```

**JavaScript** (`whitelabel-admin.js`):
```javascript
/**
 * Paletas de cores pré-definidas.
 */
function initColorPresets() {
    var presets = {
        'default': {
            primary: '#0ea5e9',
            secondary: '#10b981',
            accent: '#f59e0b',
            background: '#f9fafb',
            text: '#374151'
        },
        'ocean': {
            primary: '#0891b2',
            secondary: '#06b6d4',
            accent: '#6366f1',
            background: '#f0f9ff',
            text: '#0c4a6e'
        },
        'forest': {
            primary: '#059669',
            secondary: '#10b981',
            accent: '#84cc16',
            background: '#f0fdf4',
            text: '#14532d'
        },
        'sunset': {
            primary: '#f97316',
            secondary: '#fb923c',
            accent: '#fbbf24',
            background: '#fff7ed',
            text: '#7c2d12'
        },
        'modern': {
            primary: '#8b5cf6',
            secondary: '#a78bfa',
            accent: '#ec4899',
            background: '#faf5ff',
            text: '#581c87'
        }
    };
    
    $('.dps-preset-btn').on('click', function(e) {
        e.preventDefault();
        
        var presetName = $(this).data('preset');
        var colors = presets[presetName];
        
        if ( ! colors ) {
            return;
        }
        
        // Aplica cores nos inputs
        $('#color_primary').val(colors.primary).wpColorPicker('color', colors.primary);
        $('#color_secondary').val(colors.secondary).wpColorPicker('color', colors.secondary);
        $('#color_accent').val(colors.accent).wpColorPicker('color', colors.accent);
        $('#color_background').val(colors.background).wpColorPicker('color', colors.background);
        $('#color_text').val(colors.text).wpColorPicker('color', colors.text);
        
        // Feedback visual
        $(this).addClass('preset-applied');
        setTimeout(function() {
            $('.dps-preset-btn').removeClass('preset-applied');
        }, 1000);
    });
}

// Adicionar ao ready
$(document).ready(function() {
    // ...
    initColorPresets();
});
```

**CSS:**
```css
.dps-color-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.dps-preset-btn {
    min-width: 100px;
}

.dps-preset-btn.preset-applied {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
}
```

---

#### 3.5. Adicionar Breakpoint Responsivo 480px
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/assets/css/whitelabel-admin.css`

**Adicionar após breakpoint 782px:**
```css
/* Mobile Portrait */
@media screen and (max-width: 480px) {
    .dps-card {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .dps-card h2 {
        font-size: 1.125rem;
    }
    
    .form-table th,
    .form-table td {
        display: block;
        width: 100%;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    .form-table th {
        padding-bottom: 5px;
    }
    
    .form-table td {
        padding-top: 5px;
        padding-bottom: 20px;
    }
    
    .dps-media-uploader .regular-text,
    .regular-text {
        width: 100% !important;
    }
    
    .dps-color-presets {
        flex-direction: column;
    }
    
    .dps-preset-btn {
        width: 100%;
    }
}
```

---

### Checklist de Validação da Fase 3

- [ ] Validação de URLs em tempo real funcionando
- [ ] Campos recomendados marcados com asterisco
- [ ] Scroll automático ao salvar funcionando
- [ ] Paletas de cores aplicadas corretamente
- [ ] Layout responsivo em 480px testado
- [ ] Screenshots de UX antes/depois documentados
- [ ] Documentação atualizada (CHANGELOG.md)
- [ ] Criar tag `v1.2.0`

---

## FASE 4: Funcionalidades Essenciais
**Versão:** 1.2.1  
**Prioridade:** 🟡 MÉDIA  
**Tempo Estimado:** 5-6 dias  
**Dependências:** Fase 3 concluída

### Objetivos
Adicionar funcionalidades que melhoram significativamente o valor do add-on.

### Tarefas

#### 4.1. Teste de Conectividade SMTP
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-smtp.php`

**Adicionar método:**
```php
/**
 * Testa conectividade com servidor SMTP.
 *
 * @param array $settings Configurações SMTP a testar.
 * @return bool|WP_Error True em sucesso ou WP_Error.
 */
public static function test_smtp_connection( $settings = null ) {
    if ( null === $settings ) {
        $settings = self::get_settings();
    }
    
    if ( empty( $settings['smtp_host'] ) ) {
        return new WP_Error( 'missing_host', __( 'Host SMTP não configurado.', 'dps-whitelabel-addon' ) );
    }
    
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    
    $smtp = new PHPMailer\PHPMailer\PHPMailer( true );
    
    try {
        $smtp->isSMTP();
        $smtp->Host = sanitize_text_field( $settings['smtp_host'] );
        $smtp->Port = absint( $settings['smtp_port'] );
        $smtp->SMTPAuth = ! empty( $settings['smtp_auth'] );
        
        if ( $smtp->SMTPAuth ) {
            $helper = new self();
            $smtp->Username = sanitize_text_field( $settings['smtp_username'] );
            $smtp->Password = $helper->decrypt_password( $settings['smtp_password'] );
        }
        
        $encryption = $settings['smtp_encryption'] ?? 'tls';
        if ( 'tls' === $encryption ) {
            $smtp->SMTPSecure = 'tls';
        } elseif ( 'ssl' === $encryption ) {
            $smtp->SMTPSecure = 'ssl';
        }
        
        $smtp->Timeout = 10;
        $smtp->SMTPDebug = 0;
        
        // Tenta conectar
        if ( ! $smtp->smtpConnect() ) {
            return new WP_Error(
                'connection_failed',
                __( 'Não foi possível conectar ao servidor SMTP. Verifique host, porta e credenciais.', 'dps-whitelabel-addon' )
            );
        }
        
        $smtp->smtpClose();
        return true;
        
    } catch ( Exception $e ) {
        return new WP_Error( 'smtp_exception', $e->getMessage() );
    }
}

/**
 * AJAX: Testa conectividade SMTP.
 */
public function ajax_test_smtp_connection() {
    check_ajax_referer( 'dps_whitelabel_ajax', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-whitelabel-addon' ) ] );
    }
    
    $result = self::test_smtp_connection();
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    
    wp_send_json_success( [ 'message' => __( 'Conexão SMTP bem-sucedida!', 'dps-whitelabel-addon' ) ] );
}
```

**Registrar AJAX:**
```php
// No construtor de DPS_WhiteLabel_SMTP:
add_action( 'wp_ajax_dps_whitelabel_test_smtp_connection', [ $this, 'ajax_test_smtp_connection' ] );
```

**Template** (adicionar botão na aba SMTP):
```php
<button type="button" id="dps-test-smtp-connection" class="button">
    <?php esc_html_e( 'Testar Conexão', 'dps-whitelabel-addon' ); ?>
</button>
<span id="test-smtp-connection-result"></span>
```

**JavaScript:**
```javascript
// Em whitelabel-admin.js, adicionar:
function initTestSmtpConnection() {
    var $button = $('#dps-test-smtp-connection');
    var $result = $('#test-smtp-connection-result');
    
    if ( ! $button.length ) {
        return;
    }
    
    $button.on('click', function(e) {
        e.preventDefault();
        
        $button.prop('disabled', true);
        $result
            .removeClass('success error')
            .addClass('loading')
            .text('Testando...');
        
        $.ajax({
            url: dpsWhiteLabelL10n.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dps_whitelabel_test_smtp_connection',
                nonce: dpsWhiteLabelL10n.nonce
            },
            success: function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    $result
                        .removeClass('loading error')
                        .addClass('success')
                        .text(response.data.message);
                } else {
                    $result
                        .removeClass('loading success')
                        .addClass('error')
                        .text(response.data.message);
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $result
                    .removeClass('loading success')
                    .addClass('error')
                    .text('Erro na requisição.');
            }
        });
    });
}

// Adicionar ao ready
$(document).ready(function() {
    // ...
    initTestSmtpConnection();
});
```

---

#### 4.2. White Label do Dashboard WordPress
**Arquivo:** Criar `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-dashboard.php`

**Implementação completa em arquivo separado** (ver abaixo)

---

#### 4.3. Implementar `hide_author_links`
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-branding.php`

**Adicionar ao construtor:**
```php
// Ocultar links de autor se configurado
add_filter( 'the_author_posts_link', [ $this, 'maybe_hide_author_link' ] );
add_filter( 'author_link', [ $this, 'maybe_hide_author_link' ] );
```

**Adicionar método:**
```php
/**
 * Oculta links de autor se configurado.
 *
 * @param string $link Link original.
 * @return string Link ou vazio.
 */
public function maybe_hide_author_link( $link ) {
    $hide = DPS_WhiteLabel_Settings::get( 'hide_author_links' );
    
    if ( $hide ) {
        return '';
    }
    
    return $link;
}
```

---

#### 4.4. Botão "Restaurar Padrões" Funcional
**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/templates/admin-settings.php`

**Adicionar em cada aba:**
```php
<p class="submit">
    <input type="submit" name="dps_whitelabel_save_branding" class="button-primary" 
           value="<?php esc_attr_e( 'Salvar Alterações', 'dps-whitelabel-addon' ); ?>">
    
    <button type="button" class="button dps-reset-defaults" data-section="branding">
        <?php esc_html_e( 'Restaurar Padrões', 'dps-whitelabel-addon' ); ?>
    </button>
</p>
```

**JavaScript:**
```javascript
function initResetDefaults() {
    $('.dps-reset-defaults').on('click', function(e) {
        e.preventDefault();
        
        if ( ! confirm( dpsWhiteLabelL10n.confirmReset || 'Tem certeza?' ) ) {
            return;
        }
        
        var section = $(this).data('section');
        var $form = $(this).closest('form');
        
        // Adiciona campo hidden para indicar reset
        $form.append('<input type="hidden" name="dps_whitelabel_reset_' + section + '" value="1">');
        $form.submit();
    });
}
```

**PHP** (em cada `handle_settings_save()`):
```php
// Verificar se é reset
if ( isset( $_POST['dps_whitelabel_reset_branding'] ) ) {
    update_option( self::OPTION_NAME, self::get_defaults() );
    self::clear_cache();
    
    add_settings_error(
        'dps_whitelabel',
        'settings_reset',
        __( 'Configurações restauradas para padrões com sucesso!', 'dps-whitelabel-addon' ),
        'success'
    );
    return;
}
```

---

### Checklist de Validação da Fase 4

- [ ] Teste de conectividade SMTP funcionando
- [ ] Dashboard WordPress customizado
- [ ] `hide_author_links` implementado e testado
- [ ] Botão "Restaurar Padrões" funcionando em todas as abas
- [ ] Testes end-to-end de todas as funcionalidades
- [ ] Documentação atualizada (CHANGELOG.md)
- [ ] Criar tag `v1.2.1`

---

## FASE 5: Recursos Avançados
**Versão:** 1.3.0  
**Prioridade:** 🟢 BAIXA  
**Tempo Estimado:** 7-10 dias  
**Dependências:** Fase 4 concluída

### Objetivos
Adicionar recursos avançados que diferenciam o add-on de soluções concorrentes.

### Tarefas

#### 5.1. Templates de E-mail Personalizáveis
*Especificação detalhada a ser desenvolvida*

#### 5.2. Múltiplos Perfis de Branding (Export/Import)
*Especificação detalhada a ser desenvolvida*

#### 5.3. Custom Login Redirect por Role
*Especificação detalhada a ser desenvolvida*

#### 5.4. Logs de Acesso Bloqueado
*Especificação detalhada a ser desenvolvida*

---

## FASE 6: Integrações e Escalabilidade
**Versão:** 1.4.0  
**Prioridade:** 🟢 BAIXA  
**Tempo Estimado:** 5-7 dias  
**Dependências:** Fase 5 concluída

### Objetivos
Preparar add-on para ambientes enterprise e multisite.

### Tarefas

#### 6.1. Suporte Oficial a Multisite
*Especificação detalhada a ser desenvolvida*

#### 6.2. API REST para Configurações
*Especificação detalhada a ser desenvolvida*

#### 6.3. Webhooks de Eventos
*Especificação detalhada a ser desenvolvida*

#### 6.4. IP Whitelist para Modo Manutenção
*Especificação detalhada a ser desenvolvida*

---

## APÊNDICE A: Classe Dashboard (Fase 4.2)

**Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-dashboard.php`

```php
<?php
/**
 * Classe de personalização do Dashboard WordPress.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Personaliza o dashboard do WordPress com branding customizado.
 *
 * @since 1.2.1
 */
class DPS_WhiteLabel_Dashboard {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_dashboard';

    /**
     * Construtor da classe.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'customize_dashboard_widgets' ] );
        add_action( 'admin_head', [ $this, 'hide_dashboard_elements' ] );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'dashboard_enabled'        => false,
            'hide_wp_news'             => true,
            'hide_quick_draft'         => true,
            'hide_at_a_glance'         => false,
            'hide_activity'            => false,
            'show_custom_widget'       => true,
            'custom_widget_title'      => '',
            'custom_widget_content'    => '',
        ];
    }

    /**
     * Obtém configurações atuais.
     *
     * @return array Configurações mescladas com padrões.
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( $saved, self::get_defaults() );
    }

    /**
     * Customiza widgets do dashboard.
     */
    public function customize_dashboard_widgets() {
        $settings = self::get_settings();
        
        if ( empty( $settings['dashboard_enabled'] ) ) {
            return;
        }
        
        global $wp_meta_boxes;
        
        // Remove widgets padrão conforme configuração
        if ( ! empty( $settings['hide_wp_news'] ) ) {
            remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
        }
        
        if ( ! empty( $settings['hide_quick_draft'] ) ) {
            remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
        }
        
        if ( ! empty( $settings['hide_at_a_glance'] ) ) {
            remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
        }
        
        if ( ! empty( $settings['hide_activity'] ) ) {
            remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
        }
        
        // Adiciona widget customizado
        if ( ! empty( $settings['show_custom_widget'] ) ) {
            $brand_name = DPS_WhiteLabel_Branding::get_brand_name();
            $widget_title = ! empty( $settings['custom_widget_title'] ) 
                ? $settings['custom_widget_title'] 
                : sprintf( __( 'Bem-vindo ao %s', 'dps-whitelabel-addon' ), $brand_name );
            
            wp_add_dashboard_widget(
                'dps_whitelabel_welcome',
                $widget_title,
                [ $this, 'render_custom_widget' ]
            );
        }
    }

    /**
     * Renderiza widget customizado.
     */
    public function render_custom_widget() {
        $settings = self::get_settings();
        $content = $settings['custom_widget_content'] ?? '';
        
        if ( empty( $content ) ) {
            $brand_name = DPS_WhiteLabel_Branding::get_brand_name();
            $content = sprintf(
                '<p>%s</p>',
                sprintf(
                    /* translators: %s: nome da marca */
                    __( 'Seja bem-vindo ao painel administrativo do %s. Use o menu lateral para navegar entre as funcionalidades.', 'dps-whitelabel-addon' ),
                    '<strong>' . esc_html( $brand_name ) . '</strong>'
                )
            );
        }
        
        echo wp_kses_post( $content );
    }

    /**
     * Oculta elementos do dashboard via CSS.
     */
    public function hide_dashboard_elements() {
        $settings = self::get_settings();
        
        if ( empty( $settings['dashboard_enabled'] ) ) {
            return;
        }
        
        $screen = get_current_screen();
        if ( 'dashboard' !== $screen->id ) {
            return;
        }
        
        echo '<style>';
        echo '/* White Label Dashboard Customizations */';
        
        // Adicionar regras CSS conforme necessário
        
        echo '</style>';
    }

    /**
     * Processa salvamento de configurações.
     */
    public function handle_settings_save() {
        if ( ! isset( $_POST['dps_whitelabel_save_dashboard'] ) ) {
            return;
        }

        if ( ! isset( $_POST['dps_whitelabel_nonce'] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'invalid_nonce',
                __( 'Erro de segurança. Por favor, tente novamente.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error(
                'dps_whitelabel',
                'no_permission',
                __( 'Você não tem permissão para alterar estas configurações.', 'dps-whitelabel-addon' ),
                'error'
            );
            return;
        }

        $new_settings = [
            'dashboard_enabled'        => isset( $_POST['dashboard_enabled'] ),
            'hide_wp_news'             => isset( $_POST['hide_wp_news'] ),
            'hide_quick_draft'         => isset( $_POST['hide_quick_draft'] ),
            'hide_at_a_glance'         => isset( $_POST['hide_at_a_glance'] ),
            'hide_activity'            => isset( $_POST['hide_activity'] ),
            'show_custom_widget'       => isset( $_POST['show_custom_widget'] ),
            'custom_widget_title'      => sanitize_text_field( wp_unslash( $_POST['custom_widget_title'] ?? '' ) ),
            'custom_widget_content'    => wp_kses_post( wp_unslash( $_POST['custom_widget_content'] ?? '' ) ),
        ];

        update_option( self::OPTION_NAME, $new_settings );

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações do dashboard salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }
}
```

---

## RESUMO EXECUTIVO

### Timeline Total Estimado
- **Fase 1:** 2-3 dias (URGENTE)
- **Fase 2:** 2-3 dias (ALTA)
- **Fase 3:** 4-5 dias (MÉDIA)
- **Fase 4:** 5-6 dias (MÉDIA)
- **Fase 5:** 7-10 dias (BAIXA)
- **Fase 6:** 5-7 dias (BAIXA)

**Total:** ~25-34 dias de desenvolvimento

### Priorização Recomendada
1. ✅ **Fase 1** deve ser implementada IMEDIATAMENTE (segurança)
2. ✅ **Fase 2** deve seguir logo após (performance)
3. ⏱️ **Fase 3** pode ser feita em paralelo com Fase 4 por desenvolvedores diferentes
4. ⏱️ **Fases 5 e 6** são opcionais e podem ser roadmap de longo prazo

### Métricas de Sucesso
- **Fase 1:** 0 vulnerabilidades de segurança
- **Fase 2:** Redução de 50%+ em queries ao DB
- **Fase 3:** Aumento de 30%+ em satisfação do usuário (NPS)
- **Fase 4:** 100% das funcionalidades básicas implementadas
- **Fases 5-6:** Diferenciação competitiva alcançada

---

**Fim do roadmap de implementação.**
