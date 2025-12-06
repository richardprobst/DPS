# Guia de Implementação: Controle de Acesso do White Label Add-on

**Data:** 2025-12-06  
**Versão Alvo:** White Label v1.1.0  
**Autor:** DPS by PRObst

## Visão Geral

Este documento fornece um guia prático, passo a passo, para implementar a funcionalidade de **Controle de Acesso ao Site** no White Label Add-on.

**Pré-requisito:** Leia primeiro `docs/analysis/WHITELABEL_ACCESS_CONTROL_ANALYSIS.md` para entender a arquitetura completa e os casos de uso.

---

## Fase 1: Implementação da Classe Base

### Passo 1.1: Criar a Classe DPS_WhiteLabel_Access_Control

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/includes/class-dps-whitelabel-access-control.php`

**Tarefas:**
1. Copiar o código base da classe do documento de análise
2. Implementar todos os métodos públicos e privados
3. Validar sanitização de inputs
4. Testar lógica de verificação de acesso

**Checklist de Implementação:**
- [ ] Método `__construct()` - registra hooks
- [ ] Método `get_defaults()` - retorna configurações padrão
- [ ] Método `get_settings()` - obtém configurações mescladas
- [ ] Método `handle_settings_save()` - processa formulário
- [ ] Método `maybe_block_access()` - intercepta requisições
- [ ] Método `can_user_access()` - valida permissões do usuário
- [ ] Método `is_exception_url()` - verifica exceções com suporte a wildcard
- [ ] Método `is_media_file()` - detecta arquivos de mídia
- [ ] Método `redirect_to_login()` - redireciona com preservação de URL
- [ ] Método `get_login_url()` - obtém URL de login baseada em configurações
- [ ] Método `maybe_block_rest_api()` - controla acesso à REST API
- [ ] Método `add_access_control_indicator()` - adiciona badge na admin bar
- [ ] Método `is_active()` - verifica se controle está ativo

**Código de Referência:**

```php
<?php
/**
 * Classe de controle de acesso ao site do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_WhiteLabel_Access_Control {
    
    const OPTION_NAME = 'dps_whitelabel_access_control';
    
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'template_redirect', [ $this, 'maybe_block_access' ], 2 );
        add_filter( 'rest_authentication_errors', [ $this, 'maybe_block_rest_api' ], 99 );
        add_action( 'admin_bar_menu', [ $this, 'add_access_control_indicator' ], 100 );
    }
    
    // ... implementar todos os métodos conforme análise
}
```

**Validação de Segurança:**

```php
// Em handle_settings_save()
if ( ! isset( $_POST['dps_whitelabel_save_access_control'] ) ) {
    return;
}

// Verificar nonce
if ( ! isset( $_POST['dps_whitelabel_nonce'] ) || 
     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
    add_settings_error( 'dps_whitelabel', 'invalid_nonce', __( 'Erro de segurança.', 'dps-whitelabel-addon' ), 'error' );
    return;
}

// Verificar permissões
if ( ! current_user_can( 'manage_options' ) ) {
    add_settings_error( 'dps_whitelabel', 'no_permission', __( 'Sem permissão.', 'dps-whitelabel-addon' ), 'error' );
    return;
}

// Garantir que administrator sempre está incluído
if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
    $allowed_roles[] = 'administrator';
}
```

### Passo 1.2: Integrar com o Arquivo Principal

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/desi-pet-shower-whitelabel-addon.php`

**Tarefas:**

1. **Adicionar require_once (linha ~54):**
```php
require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-access-control.php';
```

2. **Adicionar propriedade na classe (linha ~143):**
```php
/**
 * Instância de Access Control.
 *
 * @var DPS_WhiteLabel_Access_Control
 */
private $access_control;
```

3. **Inicializar no construtor (linha ~157):**
```php
$this->access_control = new DPS_WhiteLabel_Access_Control();
```

4. **Adicionar aba na lista de abas permitidas (linha ~192):**
```php
$allowed_tabs = [ 'branding', 'smtp', 'login', 'admin-bar', 'maintenance', 'access-control' ];
```

5. **Adicionar criação de option no hook de ativação (linha ~338):**
```php
if ( false === get_option( 'dps_whitelabel_access_control' ) ) {
    add_option( 'dps_whitelabel_access_control', DPS_WhiteLabel_Access_Control::get_defaults() );
}
```

**Checklist:**
- [ ] require_once adicionado
- [ ] Propriedade $access_control declarada
- [ ] Instância inicializada no construtor
- [ ] Aba 'access-control' adicionada
- [ ] Option criada no activation hook

---

## Fase 2: Criação da Interface de Configuração

### Passo 2.1: Adicionar Template da Aba "Acesso ao Site"

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/templates/admin-settings.php`

**Localização:** Adicionar após a aba "Manutenção" (procurar por `<!-- Fim Aba Manutenção -->`)

**Código do Template:**

```php
<!-- Aba Acesso ao Site -->
<?php if ( 'access-control' === $active_tab ) : ?>
    <?php
    $access_settings = DPS_WhiteLabel_Access_Control::get_settings();
    $wp_roles        = wp_roles();
    ?>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'dps_whitelabel_settings', 'dps_whitelabel_nonce' ); ?>
        
        <div class="dps-whitelabel-section">
            <h2><?php esc_html_e( 'Controle de Acesso ao Site', 'dps-whitelabel-addon' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Configure quem pode acessar seu site e quais páginas ficam públicas.', 'dps-whitelabel-addon' ); ?>
            </p>
        </div>
        
        <!-- Status do Controle de Acesso -->
        <div class="dps-whitelabel-section">
            <h3><?php esc_html_e( 'Status', 'dps-whitelabel-addon' ); ?></h3>
            
            <label class="dps-whitelabel-toggle">
                <input type="checkbox" name="access_enabled" value="1" <?php checked( $access_settings['access_enabled'] ); ?>>
                <span><?php esc_html_e( 'Restringir acesso ao site', 'dps-whitelabel-addon' ); ?></span>
            </label>
            <p class="description">
                <?php esc_html_e( 'Quando ativo, visitantes não autenticados serão redirecionados para a página de login.', 'dps-whitelabel-addon' ); ?>
            </p>
        </div>
        
        <!-- Roles Permitidas -->
        <div class="dps-whitelabel-section">
            <h3><?php esc_html_e( 'Quem pode acessar o site?', 'dps-whitelabel-addon' ); ?></h3>
            
            <div class="dps-whitelabel-checkboxes">
                <?php foreach ( $wp_roles->get_names() as $role_slug => $role_name ) : ?>
                    <label>
                        <input 
                            type="checkbox" 
                            name="allowed_roles[]" 
                            value="<?php echo esc_attr( $role_slug ); ?>"
                            <?php checked( in_array( $role_slug, $access_settings['allowed_roles'], true ) ); ?>
                            <?php disabled( 'administrator' === $role_slug ); ?>
                        >
                        <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                        <?php if ( 'administrator' === $role_slug ) : ?>
                            <em>(<?php esc_html_e( 'sempre ativo', 'dps-whitelabel-addon' ); ?>)</em>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <p class="description">
                <?php esc_html_e( 'Usuários com as roles selecionadas terão acesso total ao site.', 'dps-whitelabel-addon' ); ?>
            </p>
        </div>
        
        <!-- Páginas de Exceção -->
        <div class="dps-whitelabel-section">
            <h3><?php esc_html_e( 'Páginas Públicas (Exceções)', 'dps-whitelabel-addon' ); ?></h3>
            
            <p class="description">
                <?php esc_html_e( 'Digite uma URL por linha. Use * para incluir subpáginas.', 'dps-whitelabel-addon' ); ?>
                <br>
                <?php esc_html_e( 'Exemplos: / (home), /contato/ (página específica), /blog/* (blog e posts)', 'dps-whitelabel-addon' ); ?>
            </p>
            
            <textarea 
                name="exception_urls" 
                rows="10" 
                class="large-text code"
                placeholder="<?php esc_attr_e( "/\n/contato/\n/servicos/\n/blog/*", 'dps-whitelabel-addon' ); ?>"
            ><?php echo esc_textarea( implode( "\n", $access_settings['exception_urls'] ) ); ?></textarea>
            
            <p class="description">
                <?php esc_html_e( 'Áreas do WordPress (/wp-admin/, /wp-login.php) são sempre acessíveis.', 'dps-whitelabel-addon' ); ?>
            </p>
        </div>
        
        <!-- Redirecionamento -->
        <div class="dps-whitelabel-section">
            <h3><?php esc_html_e( 'Redirecionamento', 'dps-whitelabel-addon' ); ?></h3>
            
            <label>
                <input 
                    type="radio" 
                    name="redirect_type" 
                    value="wp_login"
                    <?php checked( $access_settings['redirect_type'], 'wp_login' ); ?>
                >
                <?php esc_html_e( 'Página de login padrão do WordPress (/wp-login.php)', 'dps-whitelabel-addon' ); ?>
            </label>
            <br>
            
            <label>
                <input 
                    type="radio" 
                    name="redirect_type" 
                    value="custom_login"
                    <?php checked( $access_settings['redirect_type'], 'custom_login' ); ?>
                >
                <?php esc_html_e( 'Página de login customizada (configurada na aba Login)', 'dps-whitelabel-addon' ); ?>
            </label>
            <br>
            
            <label>
                <input 
                    type="radio" 
                    name="redirect_type" 
                    value="custom_url"
                    <?php checked( $access_settings['redirect_type'], 'custom_url' ); ?>
                >
                <?php esc_html_e( 'URL customizada:', 'dps-whitelabel-addon' ); ?>
                <input 
                    type="url" 
                    name="redirect_url" 
                    value="<?php echo esc_attr( $access_settings['redirect_url'] ); ?>"
                    class="regular-text"
                    placeholder="https://"
                >
            </label>
            
            <br><br>
            
            <label class="dps-whitelabel-toggle">
                <input type="checkbox" name="redirect_back" value="1" <?php checked( $access_settings['redirect_back'] ); ?>>
                <span><?php esc_html_e( 'Redirecionar de volta após login', 'dps-whitelabel-addon' ); ?></span>
            </label>
            <p class="description">
                <?php esc_html_e( 'Após autenticar, leva o usuário para a página que ele estava tentando acessar.', 'dps-whitelabel-addon' ); ?>
            </p>
        </div>
        
        <!-- Opções Avançadas -->
        <div class="dps-whitelabel-section">
            <h3><?php esc_html_e( 'Opções Avançadas', 'dps-whitelabel-addon' ); ?></h3>
            
            <label class="dps-whitelabel-toggle">
                <input type="checkbox" name="allow_rest_api" value="1" <?php checked( $access_settings['allow_rest_api'] ); ?>>
                <span><?php esc_html_e( 'Permitir REST API para usuários autenticados', 'dps-whitelabel-addon' ); ?></span>
            </label>
            
            <label class="dps-whitelabel-toggle">
                <input type="checkbox" name="allow_ajax" value="1" <?php checked( $access_settings['allow_ajax'] ); ?>>
                <span><?php esc_html_e( 'Permitir requisições AJAX', 'dps-whitelabel-addon' ); ?></span>
            </label>
            
            <label class="dps-whitelabel-toggle">
                <input type="checkbox" name="allow_media" value="1" <?php checked( $access_settings['allow_media'] ); ?>>
                <span><?php esc_html_e( 'Permitir acesso a arquivos de mídia (imagens, PDFs)', 'dps-whitelabel-addon' ); ?></span>
            </label>
            
            <br><br>
            
            <label>
                <strong><?php esc_html_e( 'Mensagem de bloqueio (se não redirecionar):', 'dps-whitelabel-addon' ); ?></strong>
                <textarea 
                    name="blocked_message" 
                    rows="3" 
                    class="large-text"
                ><?php echo esc_textarea( $access_settings['blocked_message'] ); ?></textarea>
            </label>
        </div>
        
        <div class="dps-whitelabel-actions">
            <button type="submit" name="dps_whitelabel_save_access_control" class="button button-primary">
                <?php esc_html_e( 'Salvar Configurações', 'dps-whitelabel-addon' ); ?>
            </button>
        </div>
    </form>
<?php endif; ?>
<!-- Fim Aba Acesso ao Site -->
```

**Checklist:**
- [ ] Template adicionado no arquivo
- [ ] Todos os campos estão escapados corretamente
- [ ] Nonce field incluído
- [ ] Valores padrão carregados
- [ ] Checkboxes marcadas/desmarcadas baseado nas configurações
- [ ] Administrator sempre disabled e checked
- [ ] Descrições traduzíveis

### Passo 2.2: Adicionar Aba no Menu de Navegação

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/templates/admin-settings.php`

**Localização:** Procurar por `<nav class="nav-tab-wrapper">` (linha ~15)

**Adicionar após a aba "Manutenção":**

```php
<a 
    href="<?php echo esc_url( admin_url( 'admin.php?page=dps-whitelabel&tab=access-control' ) ); ?>" 
    class="nav-tab <?php echo 'access-control' === $active_tab ? 'nav-tab-active' : ''; ?>"
>
    <?php esc_html_e( 'Acesso ao Site', 'dps-whitelabel-addon' ); ?>
</a>
```

**Checklist:**
- [ ] Link adicionado no menu de abas
- [ ] Classe 'nav-tab-active' aplicada quando aba está ativa
- [ ] Texto traduzível

---

## Fase 3: Estilos e Assets

### Passo 3.1: Adicionar Estilos CSS

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/assets/css/whitelabel-admin.css`

**Adicionar ao final do arquivo:**

```css
/* Aba Acesso ao Site */
.dps-whitelabel-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.dps-whitelabel-checkboxes label {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.dps-whitelabel-checkboxes label:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.dps-whitelabel-checkboxes input[type="checkbox"] {
    margin-right: 8px;
}

.dps-whitelabel-checkboxes label em {
    margin-left: 5px;
    font-size: 12px;
    color: #6b7280;
}

.dps-whitelabel-toggle {
    display: flex;
    align-items: center;
    margin: 10px 0;
}

.dps-whitelabel-toggle input[type="checkbox"] {
    margin-right: 10px;
}

.dps-whitelabel-toggle span {
    font-weight: 500;
}

textarea.code {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
}
```

**Checklist:**
- [ ] Estilos adicionados
- [ ] Grid responsivo para checkboxes
- [ ] Hover states implementados
- [ ] Estilos consistentes com outras abas

### Passo 3.2: Adicionar JavaScript (Opcional)

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/assets/js/whitelabel-admin.js`

**Adicionar validação e helper para seletor de páginas:**

```javascript
jQuery(document).ready(function($) {
    // Validar formulário de controle de acesso
    $('form').on('submit', function(e) {
        var redirectType = $('input[name="redirect_type"]:checked').val();
        var redirectUrl = $('input[name="redirect_url"]').val();
        
        if (redirectType === 'custom_url' && !redirectUrl) {
            e.preventDefault();
            alert(dpsWhiteLabelL10n.redirectUrlRequired || 'Por favor, insira uma URL de redirecionamento.');
            $('input[name="redirect_url"]').focus();
            return false;
        }
    });
    
    // Mostrar/ocultar campo de URL customizada
    $('input[name="redirect_type"]').on('change', function() {
        var customUrlField = $('input[name="redirect_url"]');
        if ($(this).val() === 'custom_url') {
            customUrlField.prop('disabled', false).closest('label').show();
        } else {
            customUrlField.prop('disabled', true);
        }
    }).trigger('change');
    
    // Helper para adicionar páginas populares
    if ($('#dps-add-page-helper').length === 0) {
        var helper = $('<div id="dps-add-page-helper" style="margin-top: 10px;">' +
            '<button type="button" class="button button-secondary add-page-btn" data-url="/">Home (/)</button> ' +
            '<button type="button" class="button button-secondary add-page-btn" data-url="/contato/">Contato</button> ' +
            '<button type="button" class="button button-secondary add-page-btn" data-url="/blog/*">Blog</button> ' +
            '</div>');
        
        $('textarea[name="exception_urls"]').after(helper);
        
        $('.add-page-btn').on('click', function() {
            var textarea = $('textarea[name="exception_urls"]');
            var currentValue = textarea.val();
            var newUrl = $(this).data('url');
            
            // Adicionar apenas se não existir
            if (currentValue.indexOf(newUrl) === -1) {
                textarea.val(currentValue ? currentValue + '\n' + newUrl : newUrl);
            }
        });
    }
});
```

**Checklist:**
- [ ] Validação de URL customizada implementada
- [ ] Toggle de campos implementado
- [ ] Helpers de adição rápida de páginas (opcional)

---

## Fase 4: Testes

### Passo 4.1: Testes Funcionais Manuais

**Preparação:**
1. Ativar White Label add-on
2. Navegar para DPS by PRObst → White Label → Acesso ao Site
3. Criar um usuário de teste com role "Subscriber"
4. Usar navegador em modo anônimo/privado para simular visitante

**Cenários de Teste:**

#### Teste 1: Bloquear Todo o Site
- [ ] Ativar "Restringir acesso ao site"
- [ ] Selecionar apenas "Administrator" como role permitida
- [ ] Deixar "Páginas de Exceção" vazio
- [ ] Salvar configurações
- [ ] Fazer logout
- [ ] Tentar acessar qualquer página → Deve redirecionar para login
- [ ] Fazer login como admin → Deve acessar normalmente
- [ ] Verificar badge "🔒 ACESSO RESTRITO" na admin bar

#### Teste 2: Exceções de URL
- [ ] Adicionar `/` e `/contato/` nas exceções
- [ ] Salvar configurações
- [ ] Fazer logout
- [ ] Acessar `/` → Deve carregar normalmente
- [ ] Acessar `/contato/` → Deve carregar normalmente
- [ ] Acessar `/sobre/` → Deve redirecionar para login

#### Teste 3: Wildcard
- [ ] Adicionar `/blog/*` nas exceções
- [ ] Fazer logout
- [ ] Acessar `/blog/` → Deve carregar
- [ ] Acessar `/blog/meu-post/` → Deve carregar
- [ ] Acessar `/servicos/` → Deve redirecionar

#### Teste 4: Redirect Back
- [ ] Ativar "Redirecionar de volta após login"
- [ ] Fazer logout
- [ ] Tentar acessar `/minha-conta/`
- [ ] Fazer login
- [ ] Verificar se foi redirecionado para `/minha-conta/`

#### Teste 5: Roles Permitidas
- [ ] Adicionar "Subscriber" nas roles permitidas
- [ ] Fazer login como subscriber
- [ ] Verificar acesso total ao site

#### Teste 6: REST API
- [ ] Desmarcar "Permitir REST API"
- [ ] Fazer logout
- [ ] Acessar `/wp-json/wp/v2/posts` → Deve retornar erro 401
- [ ] Fazer login
- [ ] Acessar `/wp-json/wp/v2/posts` → Deve funcionar

#### Teste 7: Arquivos de Mídia
- [ ] Desmarcar "Permitir acesso a arquivos de mídia"
- [ ] Fazer logout
- [ ] Tentar acessar imagem em `/wp-content/uploads/` → Deve bloquear
- [ ] Marcar a opção
- [ ] Tentar novamente → Deve carregar

#### Teste 8: Compatibilidade com Modo Manutenção
- [ ] Ativar Modo de Manutenção
- [ ] Fazer logout
- [ ] Acessar site → Deve mostrar página de manutenção (não redirecionar)
- [ ] Desativar Modo de Manutenção
- [ ] Acessar site → Deve redirecionar para login

**Checklist de Validação:**
- [ ] Todos os 8 testes passaram
- [ ] Nenhum erro de PHP no log
- [ ] Nenhum erro de JavaScript no console
- [ ] Comportamento consistente em diferentes navegadores

### Passo 4.2: Testes de Segurança

**Checklist:**
- [ ] Tentar salvar configurações sem nonce → Deve rejeitar
- [ ] Tentar salvar como editor (sem manage_options) → Deve rejeitar
- [ ] Tentar remover "administrator" das roles → Deve adicionar automaticamente
- [ ] Tentar injetar JavaScript em exception_urls → Deve sanitizar
- [ ] Tentar injetar SQL em exception_urls → Deve sanitizar
- [ ] Tentar bypass via URL manipulation → Não deve funcionar
- [ ] Tentar bypass via REST API → Deve respeitar configuração

### Passo 4.3: Testes de Performance

**Checklist:**
- [ ] Hook `template_redirect` executa rápido (< 50ms)
- [ ] Verificação de exceções otimizada (não faz queries)
- [ ] Configurações cacheadas (não lê option em cada requisição)
- [ ] Não quebra cache de páginas públicas

---

## Fase 5: Documentação

### Passo 5.1: Atualizar README do Add-on

**Arquivo:** `/add-ons/desi-pet-shower-whitelabel_addon/README.md` (criar se não existir)

**Adicionar seção:**

```markdown
## Controle de Acesso ao Site (v1.1.0+)

### Descrição

Restrinja o acesso ao seu site para visitantes não autenticados, redirecionando-os para uma página de login customizada.

### Configuração

1. Acesse **DPS by PRObst → White Label → Acesso ao Site**
2. Marque **"Restringir acesso ao site"**
3. Selecione as **roles permitidas** (usuários com essas roles terão acesso total)
4. Adicione **páginas de exceção** que devem permanecer públicas (uma URL por linha)
5. Configure o **tipo de redirecionamento**:
   - Página de login padrão do WordPress
   - Página de login customizada (configurada na aba Login)
   - URL customizada
6. Marque **"Redirecionar de volta após login"** para preservar URL original
7. Configure **opções avançadas** (REST API, AJAX, arquivos de mídia)
8. Clique em **Salvar Configurações**

### Exemplos de URLs de Exceção

```
/                    # Página inicial
/contato/            # Página de contato
/sobre-nos/          # Página sobre nós
/blog/*              # Blog e todos os posts (wildcard)
/wp-content/uploads/* # Todos os arquivos de mídia
```

### FAQ

**P: O que acontece quando ativo o controle de acesso?**  
R: Visitantes não autenticados que tentarem acessar páginas restritas serão redirecionados para a página de login.

**P: Posso bloquear apenas algumas páginas?**  
R: Sim! Use a lista de "Páginas de Exceção" para definir quais URLs ficam públicas. As demais serão bloqueadas.

**P: Vou ser bloqueado do wp-admin?**  
R: Não! Áreas administrativas (`/wp-admin/`, `/wp-login.php`) são sempre acessíveis.

**P: Como funciona com Modo de Manutenção?**  
R: Se Modo de Manutenção estiver ativo, ele tem prioridade. Controle de Acesso só funciona quando Manutenção está desativada.
```

### Passo 5.2: Atualizar CHANGELOG.md

**Arquivo:** `/CHANGELOG.md`

**Adicionar na seção `[Unreleased]`:**

```markdown
### White Label Add-on

#### Added (Novos recursos)
- **Controle de Acesso ao Site**: Restrinja acesso a visitantes não autenticados
  - Seleção de roles permitidas (administrator, editor, subscriber, etc.)
  - Lista de exceções de URLs (suporte a wildcards)
  - Redirecionamento para login customizado
  - Preservação de URL original após login
  - Controle de REST API, AJAX e arquivos de mídia
  - Indicador visual na admin bar quando ativo
  - Nova aba "Acesso ao Site" na interface de configuração
```

### Passo 5.3: Criar Guia de Usuário Final

**Arquivo:** `/docs/implementation/WHITELABEL_ACCESS_CONTROL_USER_GUIDE.md`

**Conteúdo:** (criar documento separado com capturas de tela)

---

## Fase 6: Finalização

### Checklist Final

**Código:**
- [ ] Classe `DPS_WhiteLabel_Access_Control` implementada
- [ ] Integração com arquivo principal concluída
- [ ] Template da aba adicionado
- [ ] CSS e JavaScript implementados
- [ ] Todos os métodos documentados com DocBlocks
- [ ] Código segue WordPress Coding Standards
- [ ] Não há erros de PHP (executar `php -l`)

**Testes:**
- [ ] Todos os testes funcionais passaram
- [ ] Testes de segurança validados
- [ ] Performance aceitável
- [ ] Compatibilidade verificada

**Documentação:**
- [ ] README.md atualizado
- [ ] CHANGELOG.md atualizado
- [ ] ANALYSIS.md já contém informações (feito anteriormente)
- [ ] Guia de usuário criado

**Deployment:**
- [ ] Incrementar versão para 1.1.0 no arquivo principal
- [ ] Criar tag de release `v1.1.0`
- [ ] Push para repositório
- [ ] Comunicar usuários sobre nova feature

---

## Troubleshooting

### Problema: Configurações não salvam

**Solução:**
- Verificar se nonce está correto
- Verificar se usuário tem capability `manage_options`
- Verificar logs de PHP para erros

### Problema: Redirecionamento em loop

**Solução:**
- Verificar se página de login não está na lista de páginas bloqueadas
- Verificar se `/wp-login.php` não está sendo bloqueado
- Desativar temporariamente para depurar

### Problema: Exceções de URL não funcionam

**Solução:**
- Verificar formato da URL (deve começar com `/`)
- Verificar se wildcard está correto (`/blog/*` não `/blog*`)
- Verificar se URL atual corresponde exatamente à exceção

### Problema: Badge não aparece na admin bar

**Solução:**
- Verificar se controle de acesso está ativo
- Verificar se usuário tem `manage_options`
- Limpar cache do navegador

---

## Conclusão

Este guia fornece todos os passos necessários para implementar o Controle de Acesso ao Site no White Label Add-on. Siga as fases sequencialmente e valide cada etapa antes de prosseguir.

**Próximas Features Sugeridas:**
- Logs de acesso bloqueado
- Página customizada de acesso negado
- Dashboard de estatísticas
- Controle por CPT, horário, IP (conforme demanda)

**Tempo Estimado Total:** 8-12 horas de desenvolvimento + 2-4 horas de testes

**Suporte:** Para dúvidas, consulte `docs/analysis/WHITELABEL_ACCESS_CONTROL_ANALYSIS.md`
