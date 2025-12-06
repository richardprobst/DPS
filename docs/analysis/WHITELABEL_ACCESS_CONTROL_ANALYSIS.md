# Análise de Controle de Acesso do Site - White Label Add-on

**Data:** 2025-12-06  
**Versão do White Label:** 1.0.0  
**Autor:** DPS by PRObst

## Sumário Executivo

Esta análise avalia a viabilidade e propõe a implementação de uma funcionalidade de **Controle de Acesso ao Site** no White Label Add-on, que permitirá restringir o acesso de visitantes não autorizados a páginas específicas ou ao site inteiro, redirecionando-os para uma página de login personalizada.

**Conclusão:** ✅ **IMPLEMENTAÇÃO VIÁVEL E RECOMENDADA**

A funcionalidade proposta é não apenas viável, mas complementa perfeitamente os recursos existentes do White Label add-on (modo de manutenção e página de login customizada), criando uma solução completa de controle de acesso e branding.

---

## 1. Estado Atual do White Label Add-on

### 1.1 Funcionalidades Existentes

O White Label add-on (v1.0.0) já possui uma base sólida para controle de acesso:

#### **Modo de Manutenção** (`class-dps-whitelabel-maintenance.php`)
- ✅ Bloqueia acesso ao site para usuários não autorizados
- ✅ Permite bypass por roles configuráveis (padrão: `administrator`)
- ✅ Exibe página customizada de manutenção
- ✅ Retorna HTTP 503 (Service Unavailable)
- ✅ Suporte a countdown timer para retorno
- ✅ Indicador visual na admin bar quando ativo

**Limitações atuais:**
- ❌ Modo "tudo ou nada" - bloqueia todo o site ou nenhuma página
- ❌ Não permite exceções por página/URL
- ❌ Não redireciona para login (apenas mostra página de manutenção)
- ❌ Focado em manutenção temporária, não em controle de acesso permanente

#### **Página de Login Personalizada** (`class-dps-whitelabel-login-page.php`)
- ✅ Logo, cores e layout customizáveis
- ✅ Background (cor sólida, imagem ou gradiente)
- ✅ Mensagem customizada
- ✅ Footer text
- ✅ Opção de ocultar links de registro/recuperação de senha

**Oportunidade:**
- ✅ Página de login já está totalmente personalizada e pronta para receber visitantes redirecionados

### 1.2 Arquitetura e Padrões

O White Label add-on segue a estrutura modular do DPS:

```
desi-pet-shower-whitelabel_addon/
├── desi-pet-shower-whitelabel-addon.php (orquestração)
├── includes/
│   ├── class-dps-whitelabel-settings.php (branding, cores, URLs)
│   ├── class-dps-whitelabel-maintenance.php (modo manutenção)
│   ├── class-dps-whitelabel-login-page.php (login customizado)
│   ├── class-dps-whitelabel-admin-bar.php (personalização admin)
│   ├── class-dps-whitelabel-smtp.php (SMTP customizado)
│   ├── class-dps-whitelabel-branding.php (branding geral)
│   └── class-dps-whitelabel-assets.php (assets CSS/JS)
├── assets/
│   ├── css/whitelabel-admin.css
│   └── js/whitelabel-admin.js
└── templates/
    ├── admin-settings.php (interface de configuração)
    └── maintenance.php (página de manutenção)
```

**Padrões identificados:**
- ✅ Separação de responsabilidades em classes
- ✅ Uso de hooks WordPress (`template_redirect`, `admin_init`)
- ✅ Validação de nonces e capabilities
- ✅ Sanitização consistente de inputs
- ✅ Sistema de abas na interface admin
- ✅ Mensagens de feedback via `add_settings_error()`

---

## 2. Funcionalidade Proposta: Controle de Acesso ao Site

### 2.1 Descrição Geral

Implementar um módulo de **Controle de Acesso** que permite:

1. **Bloquear todo o site** para visitantes não autenticados
2. **Definir exceções** - páginas/URLs que permanecem públicas
3. **Redirecionar para login** - enviar visitantes bloqueados para a página de login customizada
4. **Controlar por role** - definir quais roles WordPress podem acessar
5. **Preservar URL original** - após login, redirecionar para a página inicialmente solicitada
6. **Permitir REST API e AJAX** - não quebrar funcionalidades técnicas

### 2.2 Casos de Uso

#### Caso de Uso 1: Site Totalmente Privado
**Cenário:** Pet shop quer que todo o site seja acessível apenas para clientes cadastrados.

**Configuração:**
- Acesso ao site: "Bloquear visitantes não autenticados"
- Roles permitidas: `administrator`, `editor`, `subscriber`
- Exceções: Nenhuma
- Redirecionamento: Página de login customizada

**Resultado:** Visitante sem login é redirecionado para página de login. Após autenticar, é levado para a página que tentou acessar.

#### Caso de Uso 2: Portal de Clientes com Landing Page Pública
**Cenário:** Pet shop quer site público para marketing, mas Portal do Cliente privado.

**Configuração:**
- Acesso ao site: "Bloquear visitantes não autenticados"
- Roles permitidas: `administrator`, `subscriber`
- Exceções: 
  - `/` (home)
  - `/sobre-nos/`
  - `/servicos/`
  - `/contato/`
  - `/blog/` (e todos os posts)
- Redirecionamento: `/portal/login/`

**Resultado:** Landing pages ficam públicas, mas tentativa de acessar `/minha-conta/` redireciona para login.

#### Caso de Uso 3: Site em Desenvolvimento com Preview
**Cenário:** Agência desenvolvendo site para cliente, quer mostrar preview sem deixar público.

**Configuração:**
- Acesso ao site: "Bloquear visitantes não autenticados"
- Roles permitidas: `administrator`, `editor`
- Exceções: `/preview/?key=abc123` (URL com token)
- Redirecionamento: Página de login customizada

**Resultado:** Apenas usuários autorizados ou quem tem link especial pode acessar.

### 2.3 Interface de Configuração Proposta

Nova aba "**Acesso ao Site**" na página White Label (`?page=dps-whitelabel&tab=access-control`):

```
┌─────────────────────────────────────────────────────────────────┐
│ Controle de Acesso ao Site                                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│ [✓] Restringir acesso ao site                                   │
│     Bloqueie o acesso de visitantes não autenticados            │
│                                                                  │
│ ┌────────────────────────────────────────────────────────────┐  │
│ │ Quem pode acessar o site?                                  │  │
│ │ [✓] Administrator                                          │  │
│ │ [✓] Editor                                                 │  │
│ │ [✓] Author                                                 │  │
│ │ [✓] Contributor                                            │  │
│ │ [✓] Subscriber                                             │  │
│ │                                                            │  │
│ │ ℹ️  Usuários com as roles selecionadas terão acesso total │  │
│ └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│ ┌────────────────────────────────────────────────────────────┐  │
│ │ Páginas de Exceção (sempre públicas)                       │  │
│ │                                                            │  │
│ │ Digite uma URL por linha. Exemplos:                        │  │
│ │ /  (página inicial)                                        │  │
│ │ /contato/  (página específica)                             │  │
│ │ /blog/  (inclui todos os posts)                            │  │
│ │ /preview/?token=*  (com wildcard)                          │  │
│ │                                                            │  │
│ │ ┌────────────────────────────────────────────────────────┐ │  │
│ │ │ /                                                      │ │  │
│ │ │ /contato/                                              │ │  │
│ │ │ /servicos/                                             │ │  │
│ │ └────────────────────────────────────────────────────────┘ │  │
│ │                                                            │  │
│ │ [+ Adicionar Página do Site]  [Ajuda]                     │  │
│ └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│ ┌────────────────────────────────────────────────────────────┐  │
│ │ Redirecionamento                                           │  │
│ │                                                            │  │
│ │ ○ Página de login padrão (/wp-login.php)                  │  │
│ │ ● Página de login customizada (configurada na aba Login)  │  │
│ │ ○ URL customizada: [____________________________]          │  │
│ │                                                            │  │
│ │ [✓] Redirecionar de volta após login                       │  │
│ │     Após autenticar, leva o usuário para a página          │  │
│ │     que ele estava tentando acessar                        │  │
│ └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│ ┌────────────────────────────────────────────────────────────┐  │
│ │ Opções Avançadas                                           │  │
│ │                                                            │  │
│ │ [✓] Permitir REST API para usuários autenticados           │  │
│ │ [✓] Permitir requisições AJAX                              │  │
│ │ [✓] Permitir acesso a arquivos de mídia (imagens, PDFs)    │  │
│ │                                                            │  │
│ │ Mensagem de bloqueio (se não redirecionar):                │  │
│ │ ┌────────────────────────────────────────────────────────┐ │  │
│ │ │ Este conteúdo é exclusivo para membros.                │ │  │
│ │ │ Por favor, faça login para acessar.                    │ │  │
│ │ └────────────────────────────────────────────────────────┘ │  │
│ └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│ [Salvar Configurações]  [Restaurar Padrões]                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Arquitetura Técnica Proposta

### 3.1 Nova Classe: `DPS_WhiteLabel_Access_Control`

**Localização:** `/includes/class-dps-whitelabel-access-control.php`

**Responsabilidades:**
1. Verificar se visitante tem permissão para acessar a URL atual
2. Comparar URL atual com lista de exceções
3. Redirecionar para login se bloqueado
4. Preservar URL de destino no redirect
5. Validar bypass por role
6. Aplicar filtros para extensibilidade

**Hooks utilizados:**
- `template_redirect` (prioridade 1) - interceptar antes de renderizar
- `admin_init` - processar salvamento de configurações
- `rest_authentication_errors` - controlar acesso REST API
- `admin_bar_menu` - adicionar indicador visual quando ativo

### 3.2 Estrutura de Dados

**Option:** `dps_whitelabel_access_control`

```php
[
    'access_enabled'         => false,              // Ativar controle de acesso
    'allowed_roles'          => [                   // Roles permitidas
        'administrator',
        'editor',
        'subscriber'
    ],
    'exception_urls'         => [                   // URLs sempre públicas
        '/',
        '/contato/',
        '/blog/*'                                   // Wildcard para incluir subpáginas
    ],
    'redirect_type'          => 'custom_login',     // 'wp_login' | 'custom_login' | 'custom_url'
    'redirect_url'           => '',                 // URL customizada (se redirect_type = custom_url)
    'redirect_back'          => true,               // Redirecionar após login
    'allow_rest_api'         => true,               // Permitir REST API
    'allow_ajax'             => true,               // Permitir AJAX
    'allow_media'            => true,               // Permitir /wp-content/uploads/
    'blocked_message'        => 'Este conteúdo...', // Mensagem se não redirecionar
]
```

### 3.3 Fluxo de Execução

```
┌──────────────────────────────────────────────────────────────┐
│ 1. Visitante acessa URL: /minha-conta/                       │
└─────────────────────┬────────────────────────────────────────┘
                      │
                      ▼
┌──────────────────────────────────────────────────────────────┐
│ 2. Hook: template_redirect (prioridade 1)                    │
│    DPS_WhiteLabel_Access_Control::maybe_block_access()       │
└─────────────────────┬────────────────────────────────────────┘
                      │
                      ▼
┌──────────────────────────────────────────────────────────────┐
│ 3. Verificações de bypass:                                   │
│    ✓ Controle está ativo?                                    │
│    ✓ Usuário está logado?                                    │
│    ✓ Usuário tem role permitida?                             │
│    ✓ É admin, login ou AJAX?                                 │
│    ✓ URL está na lista de exceções?                          │
│    ✓ Filtro dps_whitelabel_access_can_access retorna true?   │
└─────────────────────┬────────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
        ▼ SIM (permitir)            ▼ NÃO (bloquear)
┌──────────────────┐      ┌──────────────────────────────────┐
│ 4. Permitir      │      │ 5. Construir URL de redirect:    │
│    acesso        │      │    - Pegar redirect_type         │
│                  │      │    - Adicionar redirect_to=URL   │
│                  │      │    - Aplicar filtro              │
└──────────────────┘      └─────────┬────────────────────────┘
                                    │
                                    ▼
                          ┌────────────────────────────────┐
                          │ 6. wp_redirect() + exit        │
                          │    Exemplo:                    │
                          │    /login/?redirect_to=%2F...  │
                          └────────────────────────────────┘
```

### 3.4 Compatibilidade com Modo de Manutenção

**Problema:** Como evitar conflito entre "Modo de Manutenção" e "Controle de Acesso"?

**Solução:** Prioridade de execução clara:

```php
// class-dps-whitelabel-maintenance.php - template_redirect prioridade 1
public function maybe_show_maintenance() {
    // Se manutenção ativa, bloqueia TUDO (exceto admins)
    // EXIT - não continua para access_control
}

// class-dps-whitelabel-access-control.php - template_redirect prioridade 2
public function maybe_block_access() {
    // Só executa se modo manutenção não bloqueou
    // Controle de acesso mais granular
}
```

**Lógica:**
- **Modo Manutenção Ativo:** Bloqueia tudo, mostra página de manutenção (temporário)
- **Modo Manutenção Inativo + Controle de Acesso Ativo:** Redireciona para login (permanente)
- **Ambos Inativos:** Site totalmente público

**Indicador na Admin Bar:**
```
[⚠ MANUTENÇÃO]  [🔒 ACESSO RESTRITO]
```

---

## 4. Implementação Detalhada

### 4.1 Código Base da Nova Classe

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

/**
 * Gerencia o controle de acesso ao site.
 *
 * @since 1.1.0
 */
class DPS_WhiteLabel_Access_Control {

    /**
     * Nome da option onde as configurações são armazenadas.
     */
    const OPTION_NAME = 'dps_whitelabel_access_control';

    /**
     * Construtor da classe.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
        add_action( 'template_redirect', [ $this, 'maybe_block_access' ], 2 );
        add_filter( 'rest_authentication_errors', [ $this, 'maybe_block_rest_api' ], 99 );
        add_action( 'admin_bar_menu', [ $this, 'add_access_control_indicator' ], 100 );
    }

    /**
     * Retorna as configurações padrão.
     *
     * @return array Configurações padrão.
     */
    public static function get_defaults() {
        return [
            'access_enabled'     => false,
            'allowed_roles'      => [ 'administrator' ],
            'exception_urls'     => [],
            'redirect_type'      => 'custom_login',
            'redirect_url'       => '',
            'redirect_back'      => true,
            'allow_rest_api'     => true,
            'allow_ajax'         => true,
            'allow_media'        => true,
            'blocked_message'    => __( 'Este conteúdo é exclusivo para membros. Por favor, faça login para acessar.', 'dps-whitelabel-addon' ),
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
     * Verifica se deve bloquear acesso à página atual.
     */
    public function maybe_block_access() {
        $settings = self::get_settings();

        if ( empty( $settings['access_enabled'] ) ) {
            return;
        }

        // Bypass se usuário pode acessar
        if ( $this->can_user_access() ) {
            return;
        }

        // Bypass para áreas do WordPress
        if ( is_admin() || ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) ) {
            return;
        }

        // Bypass para AJAX
        if ( ! empty( $settings['allow_ajax'] ) && wp_doing_ajax() ) {
            return;
        }

        // Bypass para arquivos de mídia
        if ( ! empty( $settings['allow_media'] ) && $this->is_media_file() ) {
            return;
        }

        // Bypass se URL está nas exceções
        if ( $this->is_exception_url() ) {
            return;
        }

        // Permitir bypass via filtro
        if ( apply_filters( 'dps_whitelabel_access_can_access', false, wp_get_current_user() ) ) {
            return;
        }

        // Bloquear e redirecionar
        $this->redirect_to_login();
    }

    /**
     * Verifica se o usuário atual pode acessar.
     *
     * @return bool True se pode acessar.
     */
    private function can_user_access() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $settings      = self::get_settings();
        $allowed_roles = $settings['allowed_roles'] ?? [ 'administrator' ];
        $user          = wp_get_current_user();

        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se a URL atual está nas exceções.
     *
     * @return bool True se é exceção.
     */
    private function is_exception_url() {
        $settings       = self::get_settings();
        $exception_urls = $settings['exception_urls'] ?? [];
        $current_url    = $_SERVER['REQUEST_URI'] ?? '';

        foreach ( $exception_urls as $exception ) {
            $exception = trim( $exception );
            if ( empty( $exception ) ) {
                continue;
            }

            // Suporte a wildcard
            if ( strpos( $exception, '*' ) !== false ) {
                $pattern = str_replace( '*', '.*', preg_quote( $exception, '/' ) );
                if ( preg_match( '/^' . $pattern . '$/i', $current_url ) ) {
                    return true;
                }
            } else {
                // Comparação exata ou início de caminho
                if ( $current_url === $exception || strpos( $current_url, rtrim( $exception, '/' ) . '/' ) === 0 ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se a requisição é para arquivo de mídia.
     *
     * @return bool True se é mídia.
     */
    private function is_media_file() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos( $request_uri, '/wp-content/uploads/' ) !== false;
    }

    /**
     * Redireciona para página de login.
     */
    private function redirect_to_login() {
        $settings = self::get_settings();

        $redirect_url = $this->get_login_url();

        // Adicionar redirect_to se configurado
        if ( ! empty( $settings['redirect_back'] ) ) {
            $current_url  = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $redirect_url = add_query_arg( 'redirect_to', urlencode( $current_url ), $redirect_url );
        }

        // Permitir filtro
        $redirect_url = apply_filters( 'dps_whitelabel_access_redirect_url', $redirect_url, wp_get_current_user() );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Obtém URL de login baseada nas configurações.
     *
     * @return string URL de login.
     */
    private function get_login_url() {
        $settings = self::get_settings();

        switch ( $settings['redirect_type'] ?? 'custom_login' ) {
            case 'wp_login':
                return wp_login_url();
            case 'custom_url':
                return ! empty( $settings['redirect_url'] ) ? $settings['redirect_url'] : wp_login_url();
            case 'custom_login':
            default:
                // Usar página de login customizada se houver
                $login_page_id = get_option( 'dps_custom_login_page_id' );
                return $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
        }
    }

    /**
     * Bloqueia REST API se necessário.
     *
     * @param WP_Error|null $result Erro atual.
     * @return WP_Error|null
     */
    public function maybe_block_rest_api( $result ) {
        $settings = self::get_settings();

        if ( empty( $settings['access_enabled'] ) ) {
            return $result;
        }

        if ( ! empty( $settings['allow_rest_api'] ) && is_user_logged_in() ) {
            return $result;
        }

        if ( $this->can_user_access() ) {
            return $result;
        }

        return new WP_Error(
            'rest_access_denied',
            __( 'Acesso à API REST requer autenticação.', 'dps-whitelabel-addon' ),
            [ 'status' => 401 ]
        );
    }

    /**
     * Adiciona indicador de acesso restrito na admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Instância da admin bar.
     */
    public function add_access_control_indicator( $wp_admin_bar ) {
        $settings = self::get_settings();

        if ( empty( $settings['access_enabled'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $wp_admin_bar->add_node( [
            'id'    => 'dps-access-control-active',
            'title' => '<span style="background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px;">' .
                       esc_html__( '🔒 ACESSO RESTRITO', 'dps-whitelabel-addon' ) .
                       '</span>',
            'href'  => admin_url( 'admin.php?page=dps-whitelabel&tab=access-control' ),
            'meta'  => [
                'title' => __( 'O controle de acesso está ativo. Clique para configurar.', 'dps-whitelabel-addon' ),
            ],
        ] );
    }

    /**
     * Processa salvamento de configurações.
     */
    public function handle_settings_save() {
        if ( ! isset( $_POST['dps_whitelabel_save_access_control'] ) ) {
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

        // Sanitizar roles permitidas
        $allowed_roles = [];
        if ( isset( $_POST['allowed_roles'] ) && is_array( $_POST['allowed_roles'] ) ) {
            foreach ( $_POST['allowed_roles'] as $role ) {
                $allowed_roles[] = sanitize_key( $role );
            }
        }

        // Garantir que administrator sempre está incluído
        if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
            $allowed_roles[] = 'administrator';
        }

        // Sanitizar exception URLs
        $exception_urls = [];
        if ( isset( $_POST['exception_urls'] ) ) {
            $raw_urls = sanitize_textarea_field( wp_unslash( $_POST['exception_urls'] ) );
            $lines    = explode( "\n", $raw_urls );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( ! empty( $line ) ) {
                    $exception_urls[] = $line;
                }
            }
        }

        $new_settings = [
            'access_enabled'  => isset( $_POST['access_enabled'] ),
            'allowed_roles'   => $allowed_roles,
            'exception_urls'  => $exception_urls,
            'redirect_type'   => sanitize_key( $_POST['redirect_type'] ?? 'custom_login' ),
            'redirect_url'    => esc_url_raw( wp_unslash( $_POST['redirect_url'] ?? '' ) ),
            'redirect_back'   => isset( $_POST['redirect_back'] ),
            'allow_rest_api'  => isset( $_POST['allow_rest_api'] ),
            'allow_ajax'      => isset( $_POST['allow_ajax'] ),
            'allow_media'     => isset( $_POST['allow_media'] ),
            'blocked_message' => wp_kses_post( wp_unslash( $_POST['blocked_message'] ?? '' ) ),
        ];

        update_option( self::OPTION_NAME, $new_settings );

        add_settings_error(
            'dps_whitelabel',
            'settings_saved',
            __( 'Configurações de controle de acesso salvas com sucesso!', 'dps-whitelabel-addon' ),
            'success'
        );
    }

    /**
     * Verifica se o controle de acesso está ativo.
     *
     * @return bool True se ativo.
     */
    public static function is_active() {
        $settings = self::get_settings();
        return ! empty( $settings['access_enabled'] );
    }
}
```

### 4.2 Integração com Arquivo Principal

Adicionar no `desi-pet-shower-whitelabel-addon.php`:

```php
// Linha 54 - adicionar após outros requires
require_once DPS_WHITELABEL_DIR . 'includes/class-dps-whitelabel-access-control.php';

// Linha 143 - adicionar propriedade
private $access_control;

// Linha 157 - inicializar no construtor
$this->access_control = new DPS_WhiteLabel_Access_Control();

// Linha 192 - adicionar na lista de abas permitidas
$allowed_tabs = [ 'branding', 'smtp', 'login', 'admin-bar', 'maintenance', 'access-control' ];

// Linha 338 - adicionar no hook de ativação
if ( false === get_option( 'dps_whitelabel_access_control' ) ) {
    add_option( 'dps_whitelabel_access_control', DPS_WhiteLabel_Access_Control::get_defaults() );
}
```

---

## 5. Funcionalidades Adicionais Sugeridas

Além do controle de acesso básico, identificamos outras funcionalidades que podem ser implementadas no White Label add-on:

### 5.1 Modo Privado por CPT (Custom Post Type)

**Descrição:** Permitir tornar específicos CPTs privados (ex: apenas posts do tipo `dps_documento` requerem login).

**Casos de uso:**
- Documentos financeiros apenas para clientes autenticados
- Posts de blog públicos, mas área de documentos privada

**Implementação:**
```php
'cpt_access_control' => [
    'dps_documento' => [
        'enabled' => true,
        'allowed_roles' => [ 'administrator', 'subscriber' ],
        'redirect_url' => '/login/'
    ]
]
```

### 5.2 Redirecionamento Baseado em Role

**Descrição:** Após login, redirecionar usuários para páginas diferentes baseado em sua role.

**Casos de uso:**
- Clientes (subscribers) → `/portal-cliente/`
- Funcionários (editors) → `/painel-gestao/`
- Administradores → `/wp-admin/`

**Implementação:**
```php
'role_redirect_rules' => [
    'subscriber'    => '/portal-cliente/',
    'editor'        => '/painel-gestao/',
    'administrator' => '/wp-admin/'
]
```

### 5.3 Controle de Acesso por Horário

**Descrição:** Restringir acesso ao site em horários específicos (ex: apenas horário comercial).

**Casos de uso:**
- Portal de agendamento disponível apenas durante expediente
- Site de suporte disponível 24/7, mas agendamentos apenas em dias úteis

**Implementação:**
```php
'time_restrictions' => [
    'enabled' => true,
    'timezone' => 'America/Sao_Paulo',
    'allowed_hours' => [
        'monday'    => [ 'start' => '08:00', 'end' => '18:00' ],
        'tuesday'   => [ 'start' => '08:00', 'end' => '18:00' ],
        // ...
        'sunday'    => [ 'enabled' => false ]
    ],
    'blocked_message' => 'Atendimento disponível de segunda a sexta, das 8h às 18h.'
]
```

### 5.4 Controle de Acesso por IP/Geolocalização

**Descrição:** Permitir ou bloquear acesso baseado em endereço IP ou país.

**Casos de uso:**
- Bloquear acessos de países específicos (segurança)
- Whitelist de IPs corporativos
- Blacklist de IPs maliciosos

**Implementação:**
```php
'ip_restrictions' => [
    'enabled' => true,
    'mode' => 'whitelist', // ou 'blacklist'
    'allowed_ips' => [
        '192.168.1.0/24',
        '10.0.0.1'
    ],
    'allowed_countries' => [ 'BR', 'US' ],
    'blocked_message' => 'Acesso não permitido da sua localização.'
]
```

### 5.5 Rate Limiting e Proteção Anti-Bot

**Descrição:** Limitar número de tentativas de acesso para prevenir ataques de força bruta.

**Casos de uso:**
- Prevenir bots de rastrear todo o site
- Limitar tentativas de login
- Proteção contra scraping

**Implementação:**
```php
'rate_limiting' => [
    'enabled' => true,
    'max_requests_per_minute' => 60,
    'max_requests_per_hour' => 500,
    'blocked_duration' => 3600, // segundos
    'whitelist_ips' => [ '127.0.0.1' ]
]
```

### 5.6 Logs de Acesso e Auditoria

**Descrição:** Registrar tentativas de acesso bloqueadas para análise de segurança.

**Casos de uso:**
- Identificar padrões de ataque
- Auditar acessos não autorizados
- Compliance com LGPD

**Implementação:**
```php
'access_logging' => [
    'enabled' => true,
    'log_blocked_attempts' => true,
    'log_successful_access' => false,
    'retention_days' => 30,
    'notify_admin_threshold' => 10 // notificar após X tentativas bloqueadas
]
```

**Tabela de logs:**
```sql
CREATE TABLE dps_access_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20),
    ip_address varchar(45) NOT NULL,
    requested_url text NOT NULL,
    user_agent text,
    blocked tinyint(1) DEFAULT 0,
    reason varchar(255),
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.7 Integração com Two-Factor Authentication (2FA)

**Descrição:** Exigir autenticação de dois fatores para acessar áreas sensíveis.

**Casos de uso:**
- Acesso ao `/wp-admin/` requer 2FA
- Portal financeiro requer 2FA
- Documentos sensíveis requerem 2FA

**Integração com plugins:**
- WP 2FA
- Two Factor Authentication
- Google Authenticator

### 5.8 Página de "Acesso Negado" Customizada

**Descrição:** Substituir redirecionamento por página customizada de acesso negado (HTTP 403).

**Casos de uso:**
- Informar ao usuário por que foi bloqueado
- Oferecer opções de contato com suporte
- Exibir formulário de solicitação de acesso

**Template customizado:**
```html
<!DOCTYPE html>
<html>
<head>
    <title>Acesso Negado</title>
</head>
<body>
    <h1>🔒 Acesso Restrito</h1>
    <p>Você não tem permissão para acessar este conteúdo.</p>
    <a href="/login/">Fazer Login</a>
    <a href="/contato/">Solicitar Acesso</a>
</body>
</html>
```

---

## 6. Priorização de Implementação

Sugestão de roadmap baseado em valor vs. complexidade:

### Fase 1 - MVP (Minimum Viable Product) ✅ ALTA PRIORIDADE
**Escopo:** Controle de acesso básico (conforme descrito na seção 2 e 4)

**Entregáveis:**
- ✅ Classe `DPS_WhiteLabel_Access_Control`
- ✅ Interface de configuração (aba "Acesso ao Site")
- ✅ Redirecionamento para login
- ✅ Lista de exceções de URLs
- ✅ Controle por role
- ✅ Preservação de URL original
- ✅ Indicador na admin bar

**Tempo estimado:** 8-12 horas de desenvolvimento

### Fase 2 - Melhorias e Segurança ⚠️ MÉDIA PRIORIDADE
**Escopo:** Logs, auditoria e página de acesso negado

**Entregáveis:**
- Logs de acesso bloqueado
- Página customizada de acesso negado
- Dashboard de estatísticas de acesso
- Integração com Debugging Add-on para logs

**Tempo estimado:** 4-6 horas de desenvolvimento

### Fase 3 - Recursos Avançados 🔵 BAIXA PRIORIDADE
**Escopo:** Funcionalidades avançadas conforme demanda

**Entregáveis (a escolher):**
- Controle de acesso por CPT
- Redirecionamento baseado em role
- Controle por horário
- IP/Geolocalização
- Rate limiting
- 2FA

**Tempo estimado:** 2-4 horas por funcionalidade

---

## 7. Considerações de Segurança

### 7.1 Validações Obrigatórias

✅ **Implementadas:**
- Nonce verification em todas as ações
- Capability check (`manage_options`)
- Sanitização de inputs (URLs, roles, textarea)
- Escape de outputs
- Administrator sempre incluído nas roles permitidas

⚠️ **A implementar:**
- Rate limiting de requisições
- Validação de URLs de exceção (prevenir bypass via regex malicioso)
- Proteção contra SSRF em redirect_url customizada

### 7.2 Testes de Segurança Recomendados

Antes de liberar em produção:

1. **Teste de bypass de autenticação:**
   - Tentar acessar URLs bloqueadas sem login
   - Testar wildcards maliciosos em exception_urls
   - Verificar se REST API é bloqueada quando deveria

2. **Teste de redirecionamento:**
   - Verificar se redirect_to não permite open redirect
   - Validar que URLs externas não são aceitas

3. **Teste de roles:**
   - Confirmar que administrator nunca é removido
   - Testar acesso com diferentes roles

4. **Teste de compatibilidade:**
   - Verificar conflito com modo de manutenção
   - Testar com AJAX/REST API ativo
   - Verificar acesso a arquivos de mídia

---

## 8. Compatibilidade e Dependências

### 8.1 Requisitos

**WordPress:**
- Versão mínima: 6.0
- PHP: 7.4+

**DPS:**
- Plugin base: Qualquer versão
- Outros add-ons: Nenhuma dependência

### 8.2 Conflitos Conhecidos

**Plugins de cache:**
- ⚠️ WP Super Cache, W3 Total Cache podem cachear páginas bloqueadas
- **Solução:** Adicionar filtros para excluir páginas com access control do cache

**Plugins de membership:**
- ⚠️ MemberPress, Paid Memberships Pro podem ter lógica conflitante
- **Solução:** Usar hook `dps_whitelabel_access_can_access` para integração

**Plugins de segurança:**
- ⚠️ Wordfence, iThemes Security podem ter firewall próprio
- **Solução:** Documentar ordem de execução e compatibilidade

### 8.3 Hooks e Filtros para Extensibilidade

**Filtros disponíveis:**

```php
// Permitir acesso customizado
apply_filters( 'dps_whitelabel_access_can_access', false, WP_User $user );

// Customizar URL de redirecionamento
apply_filters( 'dps_whitelabel_access_redirect_url', string $url, WP_User $user );

// Adicionar exceções de URL dinamicamente
apply_filters( 'dps_whitelabel_access_exception_urls', array $urls );

// Customizar mensagem de bloqueio
apply_filters( 'dps_whitelabel_access_blocked_message', string $message );
```

**Ações disponíveis:**

```php
// Disparado quando acesso é bloqueado
do_action( 'dps_whitelabel_access_blocked', string $url, WP_User $user );

// Disparado quando configurações são salvas
do_action( 'dps_whitelabel_access_settings_saved', array $settings );

// Disparado quando usuário permitido acessa área restrita
do_action( 'dps_whitelabel_access_granted', string $url, WP_User $user );
```

**Exemplo de uso:**

```php
// Permitir acesso para usuários com meta específica
add_filter( 'dps_whitelabel_access_can_access', function( $can_access, $user ) {
    if ( get_user_meta( $user->ID, 'vip_member', true ) ) {
        return true;
    }
    return $can_access;
}, 10, 2 );

// Adicionar logs quando acesso é bloqueado
add_action( 'dps_whitelabel_access_blocked', function( $url, $user ) {
    error_log( sprintf(
        'Acesso bloqueado: Usuário %s tentou acessar %s',
        $user->user_login ?? 'visitante',
        $url
    ) );
}, 10, 2 );
```

---

## 9. Documentação para Usuários

### 9.1 Guia Rápido de Configuração

**Cenário 1: Tornar todo o site privado**

1. Acesse **White Label → Acesso ao Site**
2. Marque **"Restringir acesso ao site"**
3. Selecione as roles permitidas (ex: Administrator, Subscriber)
4. Deixe "Páginas de Exceção" vazio
5. Configure redirecionamento para "Página de login customizada"
6. Marque "Redirecionar de volta após login"
7. Clique em **Salvar Configurações**

**Cenário 2: Site público com portal privado**

1. Acesse **White Label → Acesso ao Site**
2. Marque **"Restringir acesso ao site"**
3. Selecione as roles permitidas
4. Em "Páginas de Exceção", adicione:
   ```
   /
   /sobre-nos/
   /servicos/
   /contato/
   /blog/*
   ```
5. Configure redirecionamento
6. Clique em **Salvar Configurações**

### 9.2 FAQ (Perguntas Frequentes)

**P: O que acontece quando ativo o controle de acesso?**  
R: Visitantes não autenticados que tentarem acessar páginas restritas serão redirecionados para a página de login.

**P: Posso bloquear apenas algumas páginas?**  
R: Sim! Use a lista de "Páginas de Exceção" para definir quais URLs ficam públicas. As demais serão bloqueadas.

**P: O que é o wildcard (*)?**  
R: Use `/blog/*` para permitir acesso a `/blog/` e todos os posts dentro dele.

**P: Vou ser bloqueado do wp-admin?**  
R: Não! Áreas administrativas (`/wp-admin/`, `/wp-login.php`) são sempre acessíveis.

**P: Como funciona junto com Modo de Manutenção?**  
R: Se Modo de Manutenção estiver ativo, ele tem prioridade e bloqueia todo o site. Controle de Acesso só funciona quando Manutenção está desativada.

**P: Posso usar com plugins de cache?**  
R: Sim, mas você pode precisar configurar o cache para não cachear páginas restritas.

**P: Funciona com REST API?**  
R: Sim, há opção para permitir REST API para usuários autenticados.

---

## 10. Testes e Validação

### 10.1 Checklist de Testes

**Funcionalidades Básicas:**
- [ ] Visitante sem login é redirecionado para login
- [ ] Usuário com role permitida acessa normalmente
- [ ] Usuário com role não permitida é redirecionado
- [ ] URLs de exceção são acessíveis sem login
- [ ] Wildcard funciona corretamente
- [ ] Redirect_to preserva URL original
- [ ] Após login, usuário vai para página que queria acessar

**Segurança:**
- [ ] Nonces são validados em salvamento
- [ ] Capabilities são verificadas
- [ ] Administrator não pode ser removido das roles
- [ ] Inputs são sanitizados
- [ ] Outputs são escapados
- [ ] Não é possível bypassar via URL manipulation

**Compatibilidade:**
- [ ] Não conflita com Modo de Manutenção
- [ ] wp-admin permanece acessível
- [ ] wp-login.php permanece acessível
- [ ] AJAX funciona quando permitido
- [ ] REST API funciona quando permitido
- [ ] Arquivos de mídia são acessíveis quando permitido

**Interface:**
- [ ] Aba "Acesso ao Site" aparece no menu
- [ ] Configurações são salvas corretamente
- [ ] Mensagens de sucesso/erro são exibidas
- [ ] Indicador aparece na admin bar quando ativo
- [ ] Seletor de páginas funciona

### 10.2 Casos de Teste Automatizados

```php
/**
 * Testes PHPUnit para Access Control
 */
class DPS_WhiteLabel_Access_Control_Test extends WP_UnitTestCase {

    public function test_visitor_is_redirected() {
        // Ativar controle de acesso
        update_option( 'dps_whitelabel_access_control', [
            'access_enabled' => true,
            'allowed_roles' => [ 'administrator' ],
            'exception_urls' => [],
        ] );

        // Simular visitante tentando acessar /pagina-privada/
        // Verificar redirecionamento para login
    }

    public function test_exception_urls_work() {
        // Configurar exceção para /contato/
        update_option( 'dps_whitelabel_access_control', [
            'access_enabled' => true,
            'exception_urls' => [ '/contato/' ],
        ] );

        // Verificar que /contato/ é acessível sem login
        // Verificar que /outra-pagina/ redireciona
    }

    public function test_wildcard_exceptions() {
        // Configurar exceção para /blog/*
        update_option( 'dps_whitelabel_access_control', [
            'access_enabled' => true,
            'exception_urls' => [ '/blog/*' ],
        ] );

        // Verificar que /blog/ e /blog/post-1/ são acessíveis
        // Verificar que /servicos/ redireciona
    }

    public function test_administrator_cannot_be_removed() {
        // Tentar salvar configuração sem administrator
        // Verificar que administrator foi adicionado automaticamente
    }
}
```

---

## 11. Conclusão e Recomendações

### 11.1 Viabilidade: ✅ CONFIRMADA

A implementação de controle de acesso ao site no White Label add-on é **100% viável** e **altamente recomendada** pelos seguintes motivos:

1. **Base sólida existente:** Modo de Manutenção já implementa lógica similar
2. **Arquitetura preparada:** Sistema modular permite adicionar nova classe facilmente
3. **Complementa recursos atuais:** Integra perfeitamente com login customizado
4. **Demanda real:** Casos de uso claros (portais de cliente, sites em desenvolvimento)
5. **Valor agregado:** Diferencial competitivo para o White Label add-on

### 11.2 Próximos Passos Recomendados

**Curto Prazo (Fase 1 - MVP):**
1. ✅ Criar classe `DPS_WhiteLabel_Access_Control`
2. ✅ Adicionar interface de configuração (aba "Acesso ao Site")
3. ✅ Implementar lógica de redirecionamento
4. ✅ Adicionar suporte a exceções de URL
5. ✅ Testar compatibilidade com Modo de Manutenção
6. ✅ Documentar no README do add-on
7. ✅ Atualizar ANALYSIS.md com informações do White Label

**Médio Prazo (Fase 2):**
- Adicionar logs de acesso
- Implementar página customizada de acesso negado
- Criar dashboard de estatísticas

**Longo Prazo (Fase 3):**
- Avaliar demanda para features avançadas (CPT, horário, IP)
- Implementar conforme prioridade de usuários

### 11.3 Impacto Estimado

**Desenvolvimento:**
- Tempo: 8-12 horas (Fase 1 - MVP)
- Complexidade: Baixa-Média
- Risco: Baixo (não altera código existente)

**Usuários:**
- Valor: Alto (resolve problema real)
- Usabilidade: Excelente (interface simples)
- Compatibilidade: Alta (não quebra nada existente)

**Negócio:**
- Diferencial competitivo: Sim
- Justifica upgrade: Sim
- Demanda de suporte: Baixa (feature autodocumentada)

### 11.4 Recomendação Final

**IMPLEMENTAR NA PRÓXIMA VERSÃO (v1.1.0)**

O controle de acesso ao site deve ser implementado como feature principal da versão 1.1.0 do White Label add-on, seguindo o escopo da Fase 1 (MVP) descrito neste documento.

---

## 12. Anexos

### 12.1 Exemplo de Configuração Completa

```php
// Option: dps_whitelabel_access_control
[
    'access_enabled'  => true,
    'allowed_roles'   => [
        'administrator',
        'editor',
        'subscriber'
    ],
    'exception_urls'  => [
        '/',
        '/sobre-nos/',
        '/servicos/',
        '/contato/',
        '/blog/*',
        '/wp-content/uploads/*'
    ],
    'redirect_type'   => 'custom_login',
    'redirect_url'    => '',
    'redirect_back'   => true,
    'allow_rest_api'  => true,
    'allow_ajax'      => true,
    'allow_media'     => true,
    'blocked_message' => 'Este conteúdo é exclusivo para membros cadastrados.'
]
```

### 12.2 Diagrama de Fluxo de Decisão

```
Visitante acessa URL
        │
        ▼
Controle de Acesso está ativo?
    │           │
   NÃO         SIM
    │           │
    │           ▼
    │     É admin/login/AJAX?
    │       │           │
    │      SIM         NÃO
    │       │           │
    │       │           ▼
    │       │     Usuário logado com role permitida?
    │       │       │               │
    │       │      SIM             NÃO
    │       │       │               │
    │       │       │               ▼
    │       │       │         URL está nas exceções?
    │       │       │           │           │
    │       │       │          SIM         NÃO
    │       │       │           │           │
    ▼       ▼       ▼           ▼           ▼
PERMITIR ACESSO              BLOQUEAR → REDIRECIONAR
```

### 12.3 Mockup da Interface

```
┌───────────────────────────────────────────────────────────────┐
│ DPS by PRObst – White Label                                   │
├───────────────────────────────────────────────────────────────┤
│ [Branding] [SMTP] [Login] [Admin Bar] [Manutenção] [Acesso]  │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│  CONTROLE DE ACESSO AO SITE                                   │
│  ────────────────────────────────────────────────────────────  │
│                                                               │
│  Configure quem pode acessar seu site e quais páginas         │
│  ficam públicas.                                              │
│                                                               │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ Status                                                   │ │
│  │                                                          │ │
│  │ ○ Site totalmente público (padrão)                      │ │
│  │ ● Restringir acesso a usuários autenticados             │ │
│  │                                                          │ │
│  │ ℹ️  Visitantes sem login serão redirecionados          │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ Quem pode acessar?                                       │ │
│  │                                                          │ │
│  │ [✓] Administrator (sempre ativo)                        │ │
│  │ [✓] Editor                                              │ │
│  │ [ ] Author                                              │ │
│  │ [ ] Contributor                                         │ │
│  │ [✓] Subscriber                                          │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ Páginas Públicas (Exceções)                              │ │
│  │                                                          │ │
│  │ Digite uma URL por linha. Use * para incluir subpáginas. │ │
│  │                                                          │ │
│  │ ┌────────────────────────────────────────────────────┐   │ │
│  │ │ /                                                  │   │ │
│  │ │ /contato/                                          │   │ │
│  │ │ /servicos/                                         │   │ │
│  │ │ /blog/*                                            │   │ │
│  │ │                                                    │   │ │
│  │ └────────────────────────────────────────────────────┘   │ │
│  │                                                          │ │
│  │ [+ Adicionar Página]  [📖 Documentação]                 │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                               │
│  [💾 Salvar Configurações]  [↺ Restaurar Padrões]            │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

---

## Referências

- WordPress Codex: [Roles and Capabilities](https://codex.wordpress.org/Roles_and_Capabilities)
- WordPress Developer: [template_redirect Hook](https://developer.wordpress.org/reference/hooks/template_redirect/)
- DPS Documentation: `AGENTS.md`, `ANALYSIS.md`, `CHANGELOG.md`
- White Label Add-on: `desi-pet-shower-whitelabel-addon.php`

---

**Documento elaborado em:** 2025-12-06  
**Versão:** 1.0  
**Status:** ✅ Aprovado para implementação
