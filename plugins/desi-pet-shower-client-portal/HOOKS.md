# Hooks do Client Portal Add-on

Este documento lista todos os hooks (actions e filters) disponíveis no Client Portal Add-on para permitir que outros add-ons estendam sua funcionalidade.

## Actions (do_action)

### Renderização do Portal

#### `dps_portal_before_render`
Executado antes de renderizar qualquer conteúdo do portal.

**Parâmetros:** Nenhum

**Uso:**
```php
add_action( 'dps_portal_before_render', function() {
    // Adicionar scripts ou estilos customizados
    wp_enqueue_style( 'my-custom-portal-style' );
} );
```

---

#### `dps_portal_after_auth_check`
Executado após verificar autenticação do cliente.

**Parâmetros:**
- `int $client_id` - ID do cliente (0 se não autenticado)

**Uso:**
```php
add_action( 'dps_portal_after_auth_check', function( $client_id ) {
    if ( $client_id ) {
        // Registrar acesso do cliente
        dps_log_client_access( $client_id );
    }
}, 10, 1 );
```

---

#### `dps_portal_before_login_screen`
Executado antes de renderizar tela de login.

**Parâmetros:** Nenhum

---

#### `dps_portal_client_authenticated`
Executado quando cliente está autenticado e portal será renderizado.

**Parâmetros:**
- `int $client_id` - ID do cliente autenticado

**Uso:**
```php
add_action( 'dps_portal_client_authenticated', function( $client_id ) {
    // Atualizar última visita
    update_post_meta( $client_id, 'last_portal_access', current_time( 'mysql' ) );
}, 10, 1 );
```

---

#### `dps_client_portal_before_content`
Executado antes do conteúdo principal do portal (antes das tabs).

**Parâmetros:**
- `int $client_id` - ID do cliente

**Uso:**
```php
add_action( 'dps_client_portal_before_content', function( $client_id ) {
    // Adicionar banner ou notificação no topo
    echo '<div class="my-banner">Bem-vindo!</div>';
}, 10, 1 );
```

---

#### `dps_portal_before_tab_content`
Executado antes de renderizar o conteúdo das tabs.

**Parâmetros:**
- `int $client_id` - ID do cliente

---

### Hooks de Conteúdo por Tab

#### `dps_portal_before_inicio_content`
Executado antes do conteúdo da tab "Início".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_after_inicio_content`
Executado após o conteúdo da tab "Início".

**Parâmetros:**
- `int $client_id` - ID do cliente

**Uso:**
```php
add_action( 'dps_portal_after_inicio_content', function( $client_id ) {
    // Adicionar widget customizado
    echo '<div class="my-widget">Conteúdo extra</div>';
}, 10, 1 );
```

---

#### `dps_portal_before_agendamentos_content`
Executado antes do conteúdo da tab "Agendamentos".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_after_agendamentos_content`
Executado após o conteúdo da tab "Agendamentos".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_before_galeria_content`
Executado antes do conteúdo da tab "Galeria".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_after_galeria_content`
Executado após o conteúdo da tab "Galeria".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_before_dados_content`
Executado antes do conteúdo da tab "Meus Dados".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_after_dados_content`
Executado após o conteúdo da tab "Meus Dados".

**Parâmetros:**
- `int $client_id` - ID do cliente

---

#### `dps_portal_custom_tab_panels`
Executado após renderizar todos os panels padrão. Permite adicionar panels customizados.

**Parâmetros:**
- `int $client_id` - ID do cliente
- `array $tabs` - Array de tabs registradas

**Uso:**
```php
add_action( 'dps_portal_custom_tab_panels', function( $client_id, $tabs ) {
    if ( isset( $tabs['minha-tab'] ) ) {
        echo '<div id="panel-minha-tab" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        echo '<h2>Meu Conteúdo Customizado</h2>';
        echo '</div>';
    }
}, 10, 2 );
```

---

### Hooks de Atualização de Dados

#### `dps_portal_after_update_client`
Executado após atualizar dados do cliente.

**Parâmetros:**
- `int $client_id` - ID do cliente
- `array $_POST` - Dados submetidos no formulário

**Uso:**
```php
add_action( 'dps_portal_after_update_client', function( $client_id, $post_data ) {
    // Sincronizar com sistema externo
    my_sync_client_data( $client_id, $post_data );
}, 10, 2 );
```

---

### Hooks de Cache

#### `dps_portal_cache_invalidated`
Executado após invalidar cache de um cliente.

**Parâmetros:**
- `int $client_id` - ID do cliente
- `array $sections` - Seções invalidadas

**Uso:**
```php
add_action( 'dps_portal_cache_invalidated', function( $client_id, $sections ) {
    // Limpar cache customizado
    delete_transient( 'my_custom_cache_' . $client_id );
}, 10, 2 );
```

---

#### `dps_portal_all_cache_invalidated`
Executado após invalidar todo o cache do portal.

**Parâmetros:** Nenhum

---

## Filters (apply_filters)

### `dps_portal_tabs`
Permite modificar as tabs do portal.

**Parâmetros:**
- `array $tabs` - Array de tabs padrão
- `int $client_id` - ID do cliente

**Retorno:** `array` - Array de tabs modificado

**Estrutura de Tab:**
```php
[
    'tab_id' => [
        'icon'   => '🏠',      // Emoji ou HTML
        'label'  => 'Label',  // Texto da tab
        'active' => false,    // Se é a tab ativa
    ],
]
```

**Uso:**
```php
add_filter( 'dps_portal_tabs', function( $tabs, $client_id ) {
    // Adicionar nova tab
    $tabs['minha-tab'] = [
        'icon'   => '⭐',
        'label'  => 'Minha Tab',
        'active' => false,
    ];
    
    // Remover tab existente
    unset( $tabs['galeria'] );
    
    return $tabs;
}, 10, 2 );
```

---

### `dps_portal_login_screen`
Permite modificar o HTML da tela de login.

**Parâmetros:**
- `string $output` - HTML da tela de login

**Retorno:** `string` - HTML modificado

**Uso:**
```php
add_filter( 'dps_portal_login_screen', function( $output ) {
    // Adicionar mensagem customizada
    return $output . '<p>Mensagem customizada</p>';
} );
```

---

### `dps_portal_disable_cache`
Permite desabilitar cache para seções específicas.

**Parâmetros:**
- `bool $disable` - Se deve desabilitar (padrão: false)
- `string $section` - Nome da seção
- `int $client_id` - ID do cliente

**Retorno:** `bool`

**Uso:**
```php
add_filter( 'dps_portal_disable_cache', function( $disable, $section, $client_id ) {
    // Desabilitar cache para seção 'history'
    if ( $section === 'history' ) {
        return true;
    }
    return $disable;
}, 10, 3 );
```

---

### `dps_portal_cache_expiration`
Permite customizar tempo de expiração do cache.

**Parâmetros:**
- `int $expiration` - Tempo em segundos (padrão: 1 hora)
- `string $section` - Nome da seção
- `int $client_id` - ID do cliente

**Retorno:** `int` - Tempo de expiração em segundos

**Uso:**
```php
add_filter( 'dps_portal_cache_expiration', function( $expiration, $section, $client_id ) {
    // Cache de 2 horas para histórico
    if ( $section === 'history' ) {
        return 2 * HOUR_IN_SECONDS;
    }
    return $expiration;
}, 10, 3 );
```

---

## Exemplo Completo: Adicionar Tab Customizada

```php
// 1. Adicionar tab ao filtro
add_filter( 'dps_portal_tabs', function( $tabs, $client_id ) {
    $tabs['cursos'] = [
        'icon'   => '📚',
        'label'  => 'Meus Cursos',
        'active' => false,
    ];
    return $tabs;
}, 10, 2 );

// 2. Renderizar conteúdo da tab
add_action( 'dps_portal_custom_tab_panels', function( $client_id, $tabs ) {
    if ( isset( $tabs['cursos'] ) ) {
        echo '<div id="panel-cursos" class="dps-portal-tab-panel" role="tabpanel" aria-hidden="true">';
        echo '<h2>Meus Cursos</h2>';
        
        // Seu conteúdo aqui
        $cursos = get_user_courses( $client_id );
        foreach ( $cursos as $curso ) {
            echo '<div class="curso-item">';
            echo '<h3>' . esc_html( $curso->title ) . '</h3>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}, 10, 2 );
```

---

## Suporte

Para mais informações sobre hooks e extensibilidade, consulte:
- `ANALYSIS.md` - Arquitetura geral do sistema
- `CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` - Análise detalhada do add-on
