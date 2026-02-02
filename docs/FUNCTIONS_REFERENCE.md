# DPS Functions Reference

**Version:** 2.6.0  
**Last Updated:** December 2024  
**Author:** PRObst - desi.pet by PRObst

---

## Introduction

Este é o **guia de referência definitivo** para todas as funções, métodos e APIs públicas do sistema desi.pet by PRObst (DPS). Use este documento como fonte única de verdade ao desenvolver plugins, add-ons ou integrações com o DPS.

### Convenções de Documentação

- 📦 **Função Global**: Funções no namespace global (`dps_*`)
- 🔧 **Método Estático**: Métodos de classe acessíveis via `ClassName::method()`
- 🎯 **Método de Instância**: Métodos que requerem instância da classe
- 🔒 **Requer Capability**: Função requer permissão específica
- ⚠️ **Segurança**: Função com validações de segurança obrigatórias
- 🎨 **Frontend**: Função usada no frontend
- 🛠️ **Admin**: Função restrita ao painel administrativo

---

## Table of Contents

### Template Functions
- [dps_get_template()](#dps_get_template)
- [dps_get_template_path()](#dps_get_template_path)
- [dps_is_template_overridden()](#dps_is_template_overridden)

### Portal Functions
- [dps_get_portal_page_url()](#dps_get_portal_page_url)
- [dps_get_portal_page_id()](#dps_get_portal_page_id)
- [dps_get_page_by_title_compat()](#dps_get_page_by_title_compat)
- [dps_get_tosa_consent_page_url()](#dps_get_tosa_consent_page_url)
- [dps_portal_assert_client_owns_resource()](#dps_portal_assert_client_owns_resource)

### Helper Classes
- [DPS_Client_Helper](#dps_client_helper)
- [DPS_Money_Helper](#dps_money_helper)
- [DPS_Query_Helper](#dps_query_helper)
- [DPS_Phone_Helper](#dps_phone_helper)
- [DPS_WhatsApp_Helper](#dps_whatsapp_helper)
- [DPS_Message_Helper](#dps_message_helper)
- [DPS_IP_Helper](#dps_ip_helper)
- [DPS_Admin_Tabs_Helper](#dps_admin_tabs_helper)
- [DPS_CPT_Helper](#dps_cpt_helper)

### Core Utilities
- [DPS_Logger](#dps_logger)
- [DPS_URL_Builder](#dps_url_builder)
- [DPS_Request_Validator](#dps_request_validator)
- [DPS_Cache_Control](#dps_cache_control)

### Loyalty System
- [DPS_Loyalty_API](#dps_loyalty_api)
- [DPS_Loyalty_Achievements](#dps_loyalty_achievements)

### Quick Reference Tables
- [Security Functions](#security-functions-quick-reference)
- [Validation Functions](#validation-functions-quick-reference)
- [Money Conversion](#money-conversion-quick-reference)
- [Client Data Access](#client-data-access-quick-reference)

---

## Template Functions

### dps_get_template()

📦 **Função Global** | 🎨 **Frontend**

Localiza e carrega um template, permitindo override pelo tema.

#### Assinatura

```php
function dps_get_template( string $template_name, array $args = [] ): void
```

#### Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$template_name` | `string` | Nome do arquivo de template (ex: `'tosa-consent-form.php'`) |
| `$args` | `array` | Variáveis a serem extraídas para o template (opcional) |

#### Ordem de Busca

1. **Tema filho**: `wp-content/themes/CHILD_THEME/dps-templates/{template_name}`
2. **Tema pai**: `wp-content/themes/PARENT_THEME/dps-templates/{template_name}`
3. **Plugin base**: `wp-content/plugins/desi-pet-shower-base/templates/{template_name}`

#### Exemplos

```php
// Exemplo 1: Carregar template simples
dps_get_template( 'portal/header.php' );

// Exemplo 2: Passar variáveis para o template
dps_get_template( 'client-card.php', [
    'client_id' => 123,
    'show_pets' => true,
] );

// Exemplo 3: Forçar uso do template do plugin
add_filter( 'dps_use_plugin_template', function( $use_plugin, $template_name ) {
    if ( $template_name === 'tosa-consent-form.php' ) {
        return true; // Ignora override do tema
    }
    return $use_plugin;
}, 10, 2 );
```

#### Hooks

**Filtro: `dps_use_plugin_template`**

```php
apply_filters( 'dps_use_plugin_template', bool $use_plugin, string $template_name )
```

Permite forçar o uso do template do plugin, ignorando overrides do tema.

**Ação: `dps_template_loaded`**

```php
do_action( 'dps_template_loaded', string $path_to_load, string $template_name, bool $is_theme_override )
```

Disparada quando um template é carregado. Útil para debug e logging.

#### Retorno

Nenhum. O template é incluído e renderizado diretamente.

#### Arquivo

`plugins/desi-pet-shower-base/includes/template-functions.php`

#### Relacionado

- [`dps_get_template_path()`](#dps_get_template_path)
- [`dps_is_template_overridden()`](#dps_is_template_overridden)

---

### dps_get_template_path()

📦 **Função Global**

Retorna o caminho do template que seria carregado, sem incluí-lo.

#### Assinatura

```php
function dps_get_template_path( string $template_name ): string|false
```

#### Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$template_name` | `string` | Nome do arquivo de template |

#### Exemplos

```php
// Obter caminho do template
$path = dps_get_template_path( 'portal/header.php' );
if ( $path ) {
    echo 'Template encontrado em: ' . $path;
}

// Verificar qual versão está ativa
$path = dps_get_template_path( 'consent-form.php' );
if ( strpos( $path, '/themes/' ) !== false ) {
    echo 'Usando template do tema';
} else {
    echo 'Usando template do plugin';
}
```

#### Retorno

- **`string`**: Caminho completo do template
- **`false`**: Template não encontrado

#### Arquivo

`plugins/desi-pet-shower-base/includes/template-functions.php`

---

### dps_is_template_overridden()

📦 **Função Global**

Verifica se um template está sendo sobrescrito pelo tema.

#### Assinatura

```php
function dps_is_template_overridden( string $template_name ): bool
```

#### Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$template_name` | `string` | Nome do arquivo de template |

#### Exemplos

```php
// Verificar override
if ( dps_is_template_overridden( 'portal-header.php' ) ) {
    echo 'Tema está customizando este template';
}

// Gerar lista de templates overridden
$templates = [ 'header.php', 'footer.php', 'client-card.php' ];
foreach ( $templates as $template ) {
    if ( dps_is_template_overridden( $template ) ) {
        echo "✓ {$template} (customizado)\n";
    } else {
        echo "  {$template} (padrão)\n";
    }
}
```

#### Retorno

- **`true`**: Template sobrescrito pelo tema
- **`false`**: Usando template do plugin

#### Arquivo

`plugins/desi-pet-shower-base/includes/template-functions.php`

---

## Portal Functions

### dps_get_portal_page_url()

📦 **Função Global** | 🎨 **Frontend**

Obtém a URL da página do Portal do Cliente.

#### Assinatura

```php
function dps_get_portal_page_url(): string
```

#### Ordem de Prioridade

1. Página configurada via option `dps_portal_page_id`
2. Página com título "Portal do Cliente"
3. URL padrão `/portal-cliente/`

#### Exemplos

```php
// Gerar link para o portal
$portal_url = dps_get_portal_page_url();
echo '<a href="' . esc_url( $portal_url ) . '">Acessar Portal</a>';

// Redirecionar para o portal
wp_redirect( dps_get_portal_page_url() );
exit;

// Link com token de autenticação
$token = dps_generate_auth_token( $client_id );
$url = add_query_arg( 'token', $token, dps_get_portal_page_url() );
```

#### Retorno

**`string`**: URL da página do portal

#### Arquivo

`plugins/desi-pet-shower-client-portal/includes/functions-portal-helpers.php`

#### Relacionado

- [`dps_get_portal_page_id()`](#dps_get_portal_page_id)

---

### dps_get_portal_page_id()

📦 **Função Global**

Obtém o ID da página do Portal do Cliente.

#### Assinatura

```php
function dps_get_portal_page_id(): int|null
```

#### Exemplos

```php
// Verificar se portal está configurado
$portal_id = dps_get_portal_page_id();
if ( ! $portal_id ) {
    // Criar página automaticamente
    $portal_id = wp_insert_post( [
        'post_title'   => 'Portal do Cliente',
        'post_content' => '[dps_client_portal]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ] );
    update_option( 'dps_portal_page_id', $portal_id );
}

// Redirecionar para edição da página
$edit_url = admin_url( 'post.php?post=' . dps_get_portal_page_id() . '&action=edit' );
```

#### Retorno

- **`int`**: ID da página do portal
- **`null`**: Página não encontrada

#### Arquivo

`plugins/desi-pet-shower-client-portal/includes/functions-portal-helpers.php`

---

### dps_get_page_by_title_compat()

📦 **Função Global**

Busca uma página pelo título de forma compatível com WordPress 6.2+.

Substitui a função deprecada `get_page_by_title()` usando `WP_Query` com filtro de correspondência exata.

#### Assinatura

```php
function dps_get_page_by_title_compat( 
    string $title, 
    string $output = OBJECT, 
    string $post_type = 'page' 
): WP_Post|array|null
```

#### Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$title` | `string` | Título exato da página a ser buscada |
| `$output` | `string` | Tipo de retorno: `OBJECT`, `ARRAY_A` ou `ARRAY_N` (padrão: `OBJECT`) |
| `$post_type` | `string` | Tipo de post a buscar (padrão: `'page'`) |

#### Exemplos

```php
// Buscar página por título
$portal_page = dps_get_page_by_title_compat( 'Portal do Cliente' );
if ( $portal_page ) {
    echo 'Portal ID: ' . $portal_page->ID;
}

// Buscar CPT por título
$client = dps_get_page_by_title_compat( 'João Silva', OBJECT, 'dps_cliente' );

// Retornar como array
$page_data = dps_get_page_by_title_compat( 'Sobre Nós', ARRAY_A );
```

#### Retorno

- **`WP_Post|array`**: Post encontrado no formato especificado
- **`null`**: Post não encontrado

#### Arquivo

`plugins/desi-pet-shower-client-portal/includes/functions-portal-helpers.php`

#### Notas de Compatibilidade

- Usa `wpdb` direta para busca eficiente por título exato
- Compatível com WordPress 6.2+ onde `get_page_by_title()` foi deprecada
- Busca apenas posts com status `'publish'`

---

### dps_get_tosa_consent_page_url()

📦 **Função Global** | 🎨 **Frontend**

Obtém a URL da página de Consentimento de Tosa com Máquina.

A página é criada automaticamente pela classe `DPS_Tosa_Consent` se não existir.

#### Assinatura

```php
function dps_get_tosa_consent_page_url(): string
```

#### Exemplos

```php
// Gerar link de consentimento
$consent_url = dps_get_tosa_consent_page_url();
echo '<a href="' . esc_url( $consent_url ) . '">Assinar Termo de Consentimento</a>';

// Link com dados do cliente
$url = add_query_arg( [
    'client_id' => $client_id,
    'token'     => $token,
], dps_get_tosa_consent_page_url() );

// Enviar via WhatsApp
$whatsapp_url = DPS_WhatsApp_Helper::get_link_to_client(
    $client_phone,
    "Olá! Por favor, assine o termo de consentimento: {$consent_url}"
);
```

#### Retorno

**`string`**: URL da página de consentimento

#### Hooks

**Filtro: `dps_tosa_consent_page_url`**

```php
apply_filters( 'dps_tosa_consent_page_url', string $url, int $page_id )
```

#### Arquivo

`plugins/desi-pet-shower-client-portal/includes/functions-portal-helpers.php`

---

### dps_portal_assert_client_owns_resource()

📦 **Função Global** | ⚠️ **Segurança**

Valida se um recurso pertence ao cliente autenticado.

**CRÍTICO**: Use esta função antes de qualquer operação sensível no portal.

#### Assinatura

```php
function dps_portal_assert_client_owns_resource( 
    int $client_id, 
    int $resource_id, 
    string $type 
): bool
```

#### Parâmetros

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$client_id` | `int` | ID do cliente autenticado no portal |
| `$resource_id` | `int` | ID do recurso a ser validado |
| `$type` | `string` | Tipo do recurso: `'appointment'`, `'pet'`, `'message'`, `'transaction'`, `'client'` |

#### Tipos de Recursos Suportados

| Tipo | Validação | Meta Key |
|------|-----------|----------|
| `appointment` | Agendamento pertence ao cliente | `appointment_client_id` |
| `pet` | Pet pertence ao cliente | `owner_id` |
| `message` | Mensagem pertence ao cliente | `message_client_id` |
| `transaction` | Transação pertence ao cliente | `transaction_client_id` |
| `client` | É o próprio cliente | N/A |

#### Exemplos

```php
// Exemplo 1: Validar acesso a agendamento antes de gerar .ics
$client_id = DPS_Client_Portal::get_instance()->get_current_client_id();
$appointment_id = absint( $_GET['appointment_id'] );

if ( ! dps_portal_assert_client_owns_resource( $client_id, $appointment_id, 'appointment' ) ) {
    wp_die( __( 'Você não tem permissão para acessar este recurso.', 'dps-client-portal' ) );
}

// Gerar arquivo .ics
generate_ics_file( $appointment_id );

// Exemplo 2: Validar acesso a pet antes de exibir histórico
if ( ! dps_portal_assert_client_owns_resource( $client_id, $pet_id, 'pet' ) ) {
    return '<p class="dps-alert dps-alert--danger">Acesso negado.</p>';
}

// Exemplo 3: Validar transação antes de gerar fatura
if ( ! dps_portal_assert_client_owns_resource( $client_id, $transaction_id, 'transaction' ) ) {
    wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
}
```

#### Hooks

**Filtro: `dps_portal_pre_ownership_check`**

```php
apply_filters( 'dps_portal_pre_ownership_check', null|bool $result, int $client_id, int $resource_id, string $type )
```

Permite add-ons implementarem validação customizada antes da verificação padrão.

**Filtro: `dps_portal_ownership_validated`**

```php
apply_filters( 'dps_portal_ownership_validated', bool $is_owner, int $client_id, int $resource_id, string $type )
```

Permite modificar resultado final da validação.

#### Retorno

- **`true`**: Recurso pertence ao cliente
- **`false`**: Cliente não é dono do recurso

#### Segurança

- **Logging automático**: Tentativas negadas são registradas via `DPS_Logger` com IP do cliente
- **IDs inválidos**: Retorna `false` e registra warning se `client_id` ou `resource_id` <= 0
- **Tipo desconhecido**: Registra warning se tipo não for suportado

#### Quando Usar

✅ **USE SEMPRE**:
- Antes de exibir/modificar dados de agendamentos
- Antes de exibir/modificar dados de pets
- Antes de gerar downloads (.ics, faturas, relatórios)
- Antes de operações via AJAX no portal
- Antes de exibir mensagens/chat

❌ **NÃO USE**:
- Em páginas públicas (login, cadastro)
- Em áreas administrativas (use `current_user_can()`)

#### Arquivo

`plugins/desi-pet-shower-client-portal/includes/functions-portal-helpers.php`

#### Relacionado

- [`DPS_Logger::log()`](#dps_logger-log)
- `DPS_Client_Portal::get_current_client_id()`

---

## DPS_Client_Helper

🔧 **Classe Helper Estática**

Centraliza acesso a dados de clientes, seguindo o princípio DRY. Suporta tanto CPT `dps_client` quanto usuários WordPress.

#### Constantes

```php
const META_PHONE     = 'client_phone';
const META_EMAIL     = 'client_email';
const META_WHATSAPP  = 'client_whatsapp';
const META_ADDRESS   = 'client_address';
const META_CITY      = 'client_city';
const META_STATE     = 'client_state';
const META_ZIP       = 'client_zip';
const META_COUNTRY   = 'client_country';
const META_NOTES     = 'client_notes';
```

### Métodos

#### get_phone()

Obtém o número de telefone do cliente.

```php
public static function get_phone( int $client_id, ?string $source = null ): string
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$client_id` | `int` | ID do cliente (post ID ou user ID) |
| `$source` | `?string` | Fonte: `'post'`, `'user'` ou `null` para auto-detect |

**Exemplo:**

```php
$phone = DPS_Client_Helper::get_phone( $client_id );
echo 'Telefone: ' . esc_html( $phone );
```

---

#### get_email()

Obtém o endereço de email do cliente.

```php
public static function get_email( int $client_id, ?string $source = null ): string
```

**Exemplo:**

```php
$email = DPS_Client_Helper::get_email( $client_id );
if ( is_email( $email ) ) {
    wp_mail( $email, 'Assunto', 'Mensagem' );
}
```

**Nota:** Para usuários WordPress, faz fallback automático para `user_email` se meta estiver vazia.

---

#### get_whatsapp()

Obtém o número WhatsApp do cliente.

```php
public static function get_whatsapp( int $client_id, ?string $source = null ): string
```

**Exemplo:**

```php
$whatsapp = DPS_Client_Helper::get_whatsapp( $client_id );
$whatsapp_url = DPS_WhatsApp_Helper::get_link_to_client( $whatsapp, 'Olá!' );
```

**Nota:** Faz fallback automático para `client_phone` se campo WhatsApp estiver vazio.

---

#### get_name()

Obtém o nome do cliente.

```php
public static function get_name( int $client_id, ?string $source = null ): string
```

**Exemplo:**

```php
$name = DPS_Client_Helper::get_name( $client_id );
echo '<h2>' . esc_html( $name ) . '</h2>';
```

**Comportamento:**
- **Posts**: Retorna `post_title`
- **Usuários**: Retorna `first_name + last_name`, fallback para `display_name`

---

#### get_display_name()

Obtém o nome do cliente formatado para UI.

```php
public static function get_display_name( int $client_id, ?string $source = null ): string
```

**Exemplo:**

```php
$display_name = DPS_Client_Helper::get_display_name( $client_id );
// Retorna: "João Silva" ou "Cliente sem nome" se vazio
```

---

#### get_address()

Obtém o endereço completo formatado.

```php
public static function get_address( 
    int $client_id, 
    ?string $source = null, 
    string $separator = ', ' 
): string
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$separator` | `string` | Separador entre partes do endereço (padrão: `', '`) |

**Exemplo:**

```php
// Formato padrão
$address = DPS_Client_Helper::get_address( $client_id );
// Retorna: "Rua ABC 123, São Paulo, SP, 01234-567"

// Formato customizado
$address = DPS_Client_Helper::get_address( $client_id, null, ' - ' );
// Retorna: "Rua ABC 123 - São Paulo - SP - 01234-567"
```

---

#### get_all_data()

Obtém todos os metadados do cliente de uma só vez.

```php
public static function get_all_data( int $client_id, ?string $source = null ): array
```

**Retorno:**

```php
[
    'id'       => int,
    'name'     => string,
    'phone'    => string,
    'email'    => string,
    'whatsapp' => string,
    'address'  => string, // Endereço completo formatado
    'city'     => string,
    'state'    => string,
    'zip'      => string,
    'notes'    => string,
]
```

**Exemplo:**

```php
$client_data = DPS_Client_Helper::get_all_data( $client_id );
print_r( $client_data );

// Uso eficiente (uma query em vez de múltiplas)
$data = DPS_Client_Helper::get_all_data( $client_id );
echo "Nome: {$data['name']}\n";
echo "Email: {$data['email']}\n";
echo "Telefone: {$data['phone']}\n";
```

---

#### has_valid_phone()

Verifica se o cliente tem um número de telefone válido.

```php
public static function has_valid_phone( int $client_id, ?string $source = null ): bool
```

**Exemplo:**

```php
if ( DPS_Client_Helper::has_valid_phone( $client_id ) ) {
    $sms_service->send( $client_id, 'Mensagem de confirmação' );
}
```

**Validação:**
- Usa `DPS_Phone_Helper::is_valid()` se disponível
- Fallback: verifica se tem pelo menos 8 dígitos

---

#### has_valid_email()

Verifica se o cliente tem um email válido.

```php
public static function has_valid_email( int $client_id, ?string $source = null ): bool
```

**Exemplo:**

```php
if ( DPS_Client_Helper::has_valid_email( $client_id ) ) {
    wp_mail( $client_id, 'Newsletter', 'Conteúdo...' );
}
```

---

#### get_pets()

Obtém os pets associados ao cliente.

```php
public static function get_pets( int $client_id, array $args = [] ): array
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$args` | `array` | Argumentos adicionais para `WP_Query` |

**Exemplo:**

```php
// Obter todos os pets
$pets = DPS_Client_Helper::get_pets( $client_id );
foreach ( $pets as $pet ) {
    echo $pet->post_title . '<br>';
}

// Obter apenas IDs (mais eficiente)
$pet_ids = DPS_Client_Helper::get_pets( $client_id, [ 'fields' => 'ids' ] );

// Buscar pets de raça específica
$poodles = DPS_Client_Helper::get_pets( $client_id, [
    'meta_query' => [
        [
            'key'   => 'pet_breed',
            'value' => 'Poodle',
        ],
    ],
] );
```

---

#### get_pets_count()

Obtém a contagem de pets do cliente.

```php
public static function get_pets_count( int $client_id ): int
```

**Exemplo:**

```php
$count = DPS_Client_Helper::get_pets_count( $client_id );
echo "Este cliente tem {$count} pet(s) cadastrado(s).";
```

**Nota:** Mais eficiente que `count( get_pets() )` pois usa `fields => 'ids'`.

---

#### get_primary_pet()

Obtém o primeiro pet do cliente.

```php
public static function get_primary_pet( int $client_id ): ?WP_Post
```

**Exemplo:**

```php
$pet = DPS_Client_Helper::get_primary_pet( $client_id );
if ( $pet ) {
    echo 'Pet principal: ' . $pet->post_title;
}
```

---

#### format_contact_info()

Formata informações de contato como HTML.

```php
public static function format_contact_info( int $client_id, ?string $source = null ): string
```

**Retorno:**

```html
<span class="dps-contact-phone">Tel: (11) 98765-4321</span> | 
<span class="dps-contact-email">Email: <a href="mailto:cliente@exemplo.com">cliente@exemplo.com</a></span>
```

**Exemplo:**

```php
echo DPS_Client_Helper::format_contact_info( $client_id );
```

---

#### get_for_display()

Obtém dados do cliente formatados e prontos para UI.

```php
public static function get_for_display( int $client_id, ?string $source = null ): array
```

**Retorno:**

```php
[
    // Dados básicos (mesmo que get_all_data())
    'id'       => int,
    'name'     => string,
    'phone'    => string,
    'email'    => string,
    // ... demais campos ...
    
    // Campos adicionais para display
    'phone_formatted' => string,  // (11) 98765-4321
    'display_name'    => string,  // Nome ou "Cliente sem nome"
    'contact_html'    => string,  // HTML formatado do contato
    'pets_count'      => int,     // Quantidade de pets (apenas para posts)
]
```

**Exemplo:**

```php
$client = DPS_Client_Helper::get_for_display( $client_id );
?>
<div class="client-card">
    <h3><?php echo esc_html( $client['display_name'] ); ?></h3>
    <div class="contact"><?php echo $client['contact_html']; ?></div>
    <p>Pets cadastrados: <?php echo esc_html( $client['pets_count'] ); ?></p>
</div>
<?php
```

---

#### search_by_phone()

Busca cliente por número de telefone.

```php
public static function search_by_phone( string $phone, bool $exact = false ): ?int
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$phone` | `string` | Número a buscar (aceita máscaras) |
| `$exact` | `bool` | `true` para busca exata, `false` para LIKE |

**Exemplo:**

```php
// Busca parcial (permite variações de formatação)
$client_id = DPS_Client_Helper::search_by_phone( '11987654321' );

// Busca exata
$client_id = DPS_Client_Helper::search_by_phone( '5511987654321', true );

if ( $client_id ) {
    echo 'Cliente encontrado: ' . DPS_Client_Helper::get_name( $client_id );
} else {
    echo 'Cliente não cadastrado.';
}
```

**Nota:** Busca em `client_phone` e `client_whatsapp`.

---

#### search_by_email()

Busca cliente por email.

```php
public static function search_by_email( string $email ): ?int
```

**Exemplo:**

```php
$client_id = DPS_Client_Helper::search_by_email( 'cliente@exemplo.com' );
if ( ! $client_id ) {
    // Criar novo cliente
    $client_id = wp_insert_post( [
        'post_type'  => 'dps_cliente',
        'post_title' => 'Novo Cliente',
    ] );
    update_post_meta( $client_id, 'client_email', 'cliente@exemplo.com' );
}
```

---

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-client-helper.php`

---

## DPS_Money_Helper

🔧 **Classe Helper Estática**

Utilitários para conversão e formatação de valores monetários.

**Sistema de Representação:**
- Internamente: valores em **centavos** (int)
- Interface: valores em **reais** formatados (string)

### Métodos

#### parse_brazilian_format()

Converte string em formato brasileiro para centavos.

```php
public static function parse_brazilian_format( string $money_string ): int
```

**Exemplos:**

```php
// Formatos aceitos
DPS_Money_Helper::parse_brazilian_format( '1.234,56' );    // 123456
DPS_Money_Helper::parse_brazilian_format( '1234,56' );     // 123456
DPS_Money_Helper::parse_brazilian_format( '1234.56' );     // 123456
DPS_Money_Helper::parse_brazilian_format( 'R$ 1.234,56' ); // 123456
DPS_Money_Helper::parse_brazilian_format( '80' );          // 8000
DPS_Money_Helper::parse_brazilian_format( '' );            // 0

// Uso prático
$input_value = $_POST['price']; // "R$ 150,00"
$price_cents = DPS_Money_Helper::parse_brazilian_format( $input_value );
update_post_meta( $service_id, 'service_price', $price_cents );
```

---

#### format_to_brazilian()

Formata valor em centavos para string no formato brasileiro.

```php
public static function format_to_brazilian( int $cents ): string
```

**Exemplos:**

```php
DPS_Money_Helper::format_to_brazilian( 123456 ); // "1.234,56"
DPS_Money_Helper::format_to_brazilian( 100 );    // "1,00"
DPS_Money_Helper::format_to_brazilian( 0 );      // "0,00"

// Exibir preço
$price = get_post_meta( $service_id, 'service_price', true );
echo 'Preço: R$ ' . DPS_Money_Helper::format_to_brazilian( $price );
```

---

#### format_currency()

Formata valor em centavos com símbolo de moeda.

```php
public static function format_currency( int $cents, string $symbol = 'R$ ' ): string
```

**Exemplos:**

```php
DPS_Money_Helper::format_currency( 123456 );         // "R$ 1.234,56"
DPS_Money_Helper::format_currency( 100 );            // "R$ 1,00"
DPS_Money_Helper::format_currency( 5000, 'US$ ' );  // "US$ 50,00"

// HTML output
echo '<span class="price">' . esc_html( DPS_Money_Helper::format_currency( $total ) ) . '</span>';
```

---

#### format_currency_from_decimal()

Formata valor decimal (reais) com símbolo.

```php
public static function format_currency_from_decimal( float $decimal_value, string $symbol = 'R$ ' ): string
```

**Exemplos:**

```php
DPS_Money_Helper::format_currency_from_decimal( 1234.56 ); // "R$ 1.234,56"
DPS_Money_Helper::format_currency_from_decimal( 80.00 );   // "R$ 80,00"

// Converter de centavos para reais e formatar
$cents = 15000;
$reais = $cents / 100;
echo DPS_Money_Helper::format_currency_from_decimal( $reais );
// Output: "R$ 150,00"
```

---

#### decimal_to_cents()

Converte valor decimal para centavos.

```php
public static function decimal_to_cents( float $decimal_value ): int
```

**Exemplos:**

```php
DPS_Money_Helper::decimal_to_cents( 80.50 );  // 8050
DPS_Money_Helper::decimal_to_cents( 10.00 );  // 1000
DPS_Money_Helper::decimal_to_cents( 1.99 );   // 199
```

---

#### cents_to_decimal()

Converte centavos para valor decimal.

```php
public static function cents_to_decimal( int $cents ): float
```

**Exemplos:**

```php
DPS_Money_Helper::cents_to_decimal( 8050 );  // 80.50
DPS_Money_Helper::cents_to_decimal( 1000 );  // 10.00
DPS_Money_Helper::cents_to_decimal( 199 );   // 1.99
```

---

#### format_decimal_to_brazilian()

Formata valor decimal para formato brasileiro.

```php
public static function format_decimal_to_brazilian( float $decimal_value ): string
```

**Exemplos:**

```php
DPS_Money_Helper::format_decimal_to_brazilian( 1234.56 ); // "1.234,56"
DPS_Money_Helper::format_decimal_to_brazilian( 80.00 );   // "80,00"
```

---

#### is_valid_money_string()

Valida se string representa valor monetário válido.

```php
public static function is_valid_money_string( string $money_string ): bool
```

**Exemplos:**

```php
DPS_Money_Helper::is_valid_money_string( '1.234,56' );      // true
DPS_Money_Helper::is_valid_money_string( 'R$ 80,00' );      // true
DPS_Money_Helper::is_valid_money_string( '1234.56' );       // true
DPS_Money_Helper::is_valid_money_string( 'abc' );           // false
DPS_Money_Helper::is_valid_money_string( '' );              // false

// Validação antes de processar
if ( ! DPS_Money_Helper::is_valid_money_string( $_POST['price'] ) ) {
    wp_die( 'Valor inválido' );
}
```

---

#### sanitize_post_price_field()

Sanitiza e converte campo de preço do POST para float.

```php
public static function sanitize_post_price_field( string $field_name ): float
```

**Exemplos:**

```php
// Obter preço sanitizado do POST
$price = DPS_Money_Helper::sanitize_post_price_field( 'service_price' );

// Garante não-negativo
$price = DPS_Money_Helper::sanitize_post_price_field( 'discount' );
// Se campo não existe ou é negativo, retorna 0.0
```

**Nota:** Retorna sempre valor >= 0.0

---

### Fluxo de Trabalho Recomendado

```php
// 1. RECEBER: converter entrada do usuário para centavos
$input = sanitize_text_field( $_POST['price'] ); // "R$ 150,00"
$price_cents = DPS_Money_Helper::parse_brazilian_format( $input );

// 2. ARMAZENAR: salvar em centavos no banco
update_post_meta( $item_id, 'price', $price_cents );

// 3. CALCULAR: sempre em centavos
$total = $price_cents * $quantity;
$discount = (int) ( $total * 0.10 ); // 10% desconto

// 4. EXIBIR: converter de volta para formato brasileiro
echo 'Total: ' . DPS_Money_Helper::format_currency( $total );
echo 'Desconto: ' . DPS_Money_Helper::format_currency( $discount );
```

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-money-helper.php`

---

## DPS_Query_Helper

🔧 **Classe Helper Estática**

Utilitários para construção de consultas WP_Query padronizadas e eficientes.

### Métodos

#### build_base_query_args()

Constrói argumentos base para consulta de posts.

```php
public static function build_base_query_args( string $post_type, array $overrides = [] ): array
```

**Exemplo:**

```php
$args = DPS_Query_Helper::build_base_query_args( 'dps_cliente', [
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );
$query = new WP_Query( $args );
```

---

#### get_all_posts_by_type()

Obtém todos os posts de um tipo específico.

```php
public static function get_all_posts_by_type( string $post_type, array $extra_args = [] ): array
```

**Exemplo:**

```php
// Todos os clientes
$clients = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );

// Todos os pets de porte grande
$large_pets = DPS_Query_Helper::get_all_posts_by_type( 'dps_pet', [
    'meta_query' => [
        [
            'key'   => 'pet_size',
            'value' => 'grande',
        ],
    ],
] );
```

---

#### get_paginated_posts()

Obtém posts paginados.

```php
public static function get_paginated_posts( 
    string $post_type, 
    int $page = 1, 
    int $per_page = 20, 
    array $extra_args = [] 
): WP_Query
```

**Exemplo:**

```php
$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$query = DPS_Query_Helper::get_paginated_posts( 'dps_agendamento', $page, 25 );

if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
        $query->the_post();
        // Exibir post
    }
    
    // Paginação
    $total_pages = $query->max_num_pages;
}
```

---

#### count_posts_by_type()

Obtém contagem de posts.

```php
public static function count_posts_by_type( string $post_type, array $extra_args = [] ): int
```

**Exemplo:**

```php
$total_clients = DPS_Query_Helper::count_posts_by_type( 'dps_cliente' );
$active_clients = DPS_Query_Helper::count_posts_by_type( 'dps_cliente', [
    'meta_query' => [
        [
            'key'   => 'client_status',
            'value' => 'active',
        ],
    ],
] );

echo "Clientes ativos: {$active_clients} de {$total_clients}";
```

**Nota:** Usa `fields => 'ids'` para performance otimizada.

---

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-query-helper.php`

---

## DPS_Phone_Helper

🔧 **Classe Helper Estática**

Formatação e validação de números de telefone brasileiros.

### Métodos

#### format_for_whatsapp()

Formata número para WhatsApp (formato internacional).

```php
public static function format_for_whatsapp( string $phone ): string
```

**Exemplos:**

```php
DPS_Phone_Helper::format_for_whatsapp( '(11) 98765-4321' ); // '5511987654321'
DPS_Phone_Helper::format_for_whatsapp( '11987654321' );     // '5511987654321'
DPS_Phone_Helper::format_for_whatsapp( '5511987654321' );   // '5511987654321'

// Uso prático
$phone = get_post_meta( $client_id, 'client_phone', true );
$whatsapp_phone = DPS_Phone_Helper::format_for_whatsapp( $phone );
$wa_link = "https://wa.me/{$whatsapp_phone}";
```

---

#### format_for_display()

Formata número para exibição no formato brasileiro.

```php
public static function format_for_display( string $phone ): string
```

**Exemplos:**

```php
DPS_Phone_Helper::format_for_display( '11987654321' );     // '(11) 98765-4321'
DPS_Phone_Helper::format_for_display( '1134567890' );      // '(11) 3456-7890'
DPS_Phone_Helper::format_for_display( '5511987654321' );   // '(11) 98765-4321'

// Exibir em tabela
$phone_raw = get_post_meta( $client_id, 'client_phone', true );
echo '<td>' . esc_html( DPS_Phone_Helper::format_for_display( $phone_raw ) ) . '</td>';
```

---

#### is_valid_brazilian_phone()

Valida se número é um telefone brasileiro válido.

```php
public static function is_valid_brazilian_phone( string $phone ): bool
```

**Exemplos:**

```php
DPS_Phone_Helper::is_valid_brazilian_phone( '11987654321' );   // true
DPS_Phone_Helper::is_valid_brazilian_phone( '1134567890' );    // true
DPS_Phone_Helper::is_valid_brazilian_phone( '123' );           // false
DPS_Phone_Helper::is_valid_brazilian_phone( '11876543' );      // false (7 dígitos)

// Validação em formulário
if ( ! DPS_Phone_Helper::is_valid_brazilian_phone( $_POST['phone'] ) ) {
    DPS_Message_Helper::add_error( 'Telefone inválido' );
    return;
}
```

**Regras de Validação:**
- 10 dígitos (telefone fixo) ou 11 dígitos (celular)
- DDD entre 11 e 99
- Remove código do país (55) automaticamente se presente

---

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-phone-helper.php`

---

## DPS_WhatsApp_Helper

🔧 **Classe Helper Estática**

Geração de links e mensagens padronizadas para WhatsApp.

### Constantes

```php
const TEAM_PHONE = '5515991606299'; // Número padrão da equipe
```

### Métodos

#### get_link_to_team()

Gera link WhatsApp para cliente enviar mensagem à equipe.

```php
public static function get_link_to_team( string $message = '' ): string
```

**Exemplos:**

```php
// Link simples
$link = DPS_WhatsApp_Helper::get_link_to_team();
echo '<a href="' . esc_url( $link ) . '">Fale Conosco</a>';

// Link com mensagem pré-preenchida
$message = 'Olá, gostaria de agendar um banho para meu pet.';
$link = DPS_WhatsApp_Helper::get_link_to_team( $message );

// Botão de solicitação de acesso ao portal
$client_name = DPS_Client_Helper::get_name( $client_id );
$pet_name = get_the_title( $pet_id );
$message = DPS_WhatsApp_Helper::get_portal_access_request_message( $client_name, $pet_name );
$link = DPS_WhatsApp_Helper::get_link_to_team( $message );
?>
<a href="<?php echo esc_url( $link ); ?>" class="button button-primary">
    📱 Solicitar Acesso ao Portal
</a>
```

---

#### get_link_to_client()

Gera link WhatsApp para equipe enviar mensagem ao cliente.

```php
public static function get_link_to_client( string $client_phone, string $message = '' ): string
```

**Exemplos:**

```php
// Link para contatar cliente
$phone = DPS_Client_Helper::get_whatsapp( $client_id );
$link = DPS_WhatsApp_Helper::get_link_to_client( $phone, 'Seu agendamento foi confirmado!' );

// Enviar link do portal
$portal_url = dps_get_portal_page_url();
$token = generate_access_token( $client_id );
$portal_url_with_token = add_query_arg( 'token', $token, $portal_url );

$client_name = DPS_Client_Helper::get_name( $client_id );
$message = DPS_WhatsApp_Helper::get_portal_link_message( $client_name, $portal_url_with_token );
$link = DPS_WhatsApp_Helper::get_link_to_client( $phone, $message );

// Exibir no admin
echo '<a href="' . esc_url( $link ) . '" target="_blank">Enviar Link do Portal via WhatsApp</a>';
```

---

#### get_portal_access_request_message()

Gera mensagem padrão para cliente solicitar acesso ao portal.

```php
public static function get_portal_access_request_message( string $client_name = '', string $pet_name = '' ): string
```

**Exemplos:**

```php
// Mensagem personalizada
$message = DPS_WhatsApp_Helper::get_portal_access_request_message( 'João Silva', 'Rex' );
// Retorna: "Olá! 🐾 Sou João Silva e gostaria de receber o link de acesso..."

// Mensagem genérica (sem nome/pet)
$message = DPS_WhatsApp_Helper::get_portal_access_request_message();
// Retorna: "Olá! 🐾 Gostaria de receber o link de acesso..."
```

---

#### get_portal_link_message()

Gera mensagem padrão para envio de link do portal ao cliente.

```php
public static function get_portal_link_message( string $client_name, string $portal_url ): string
```

**Exemplos:**

```php
$client_name = DPS_Client_Helper::get_name( $client_id );
$portal_url = add_query_arg( 'token', $token, dps_get_portal_page_url() );
$message = DPS_WhatsApp_Helper::get_portal_link_message( $client_name, $portal_url );

// Retorna:
// "Olá João Silva! Aqui está seu link de acesso ao Portal do Cliente: 
// https://exemplo.com/portal?token=abc123 - Este link é válido por 30 minutos..."
```

---

#### get_appointment_confirmation_message()

Gera mensagem de confirmação de agendamento.

```php
public static function get_appointment_confirmation_message( array $appointment_data ): string
```

**Parâmetros:**

Array com keys: `client_name`, `pet_name`, `date`, `time`

**Exemplo:**

```php
$appointment_data = [
    'client_name' => DPS_Client_Helper::get_name( $client_id ),
    'pet_name'    => get_the_title( $pet_id ),
    'date'        => date_i18n( 'd/m/Y', strtotime( $appointment_date ) ),
    'time'        => $appointment_time,
];

$message = DPS_WhatsApp_Helper::get_appointment_confirmation_message( $appointment_data );
$phone = DPS_Client_Helper::get_whatsapp( $client_id );
$link = DPS_WhatsApp_Helper::get_link_to_client( $phone, $message );

echo '<a href="' . esc_url( $link ) . '">Enviar Confirmação</a>';
```

---

#### get_payment_request_message()

Gera mensagem de cobrança.

```php
public static function get_payment_request_message( 
    string $client_name, 
    string $amount, 
    string $payment_url = '' 
): string
```

**Exemplo:**

```php
$amount = DPS_Money_Helper::format_currency( $total_cents );
$message = DPS_WhatsApp_Helper::get_payment_request_message(
    DPS_Client_Helper::get_name( $client_id ),
    $amount,
    'https://payment-link.com/123'
);

// Retorna: "Olá João Silva! O valor do serviço é R$ 80,00. 
// Você pode pagar através deste link: https://payment-link.com/123"
```

---

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-whatsapp-helper.php`

---

## DPS_Logger

🔧 **Classe Helper Estática**

Sistema centralizado de logs do DPS.

### Constantes

```php
const DB_VERSION    = '1.0.0';
const LEVEL_DEBUG   = 'debug';
const LEVEL_INFO    = 'info';
const LEVEL_WARNING = 'warning';
const LEVEL_ERROR   = 'error';
```

### Métodos

#### log()

Registra log genérico.

```php
public static function log( string $level, string $message, array $context = [], string $source = 'base' ): void
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `$level` | `string` | Nível: `'debug'`, `'info'`, `'warning'`, `'error'` |
| `$message` | `string` | Mensagem descritiva |
| `$context` | `array` | Dados complementares (será convertido para JSON) |
| `$source` | `string` | Origem do evento (ex: `'base'`, `'finance'`, `'loyalty'`) |

**Exemplos:**

```php
// Log simples
DPS_Logger::log( 'info', 'Cliente acessou o portal', [], 'portal' );

// Log com contexto
DPS_Logger::log( 'warning', 'Tentativa de acesso negada', [
    'client_id' => $client_id,
    'resource'  => 'appointment',
    'ip'        => $_SERVER['REMOTE_ADDR'],
], 'portal' );

// Log de erro com stack trace
DPS_Logger::log( 'error', 'Falha ao processar pagamento', [
    'client_id'      => $client_id,
    'amount'         => $amount,
    'gateway'        => 'pix',
    'error_message'  => $e->getMessage(),
    'stack_trace'    => $e->getTraceAsString(),
], 'payment' );
```

---

#### debug()

Registra log de debug.

```php
public static function debug( string $message, array $context = [], string $source = 'base' ): void
```

---

#### info()

Registra log de informação.

```php
public static function info( string $message, array $context = [], string $source = 'base' ): void
```

---

#### warning()

Registra log de aviso.

```php
public static function warning( string $message, array $context = [], string $source = 'base' ): void
```

---

#### error()

Registra log de erro.

```php
public static function error( string $message, array $context = [], string $source = 'base' ): void
```

---

### Configuração

**Nível Mínimo de Log:**

```php
// Configurar para registrar apenas warnings e errors
update_option( 'dps_logger_min_level', DPS_Logger::LEVEL_WARNING );

// Configurar para debug (todos os logs)
update_option( 'dps_logger_min_level', DPS_Logger::LEVEL_DEBUG );
```

### Tabela de Banco

Logs são armazenados em `{$wpdb->prefix}dps_logs`:

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | `bigint` | ID auto-increment |
| `date_time` | `datetime` | Data/hora do evento |
| `level` | `varchar(20)` | Nível do log |
| `source` | `varchar(50)` | Origem do evento |
| `message` | `text` | Mensagem descritiva |
| `context` | `longtext` | JSON com dados complementares |

### Fallback

Se a tabela não existir ou houver erro de inserção, os logs são salvos em arquivo:
- Caminho: `wp-content/uploads/dps-logs/dps.log`
- Formato: `[datetime] LEVEL.source: message | context`

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-logger.php`

---

## DPS_Request_Validator

🔧 **Classe Helper Estática** | ⚠️ **Segurança**

Validação de requisições, nonces e capabilities.

### Métodos Principais

#### verify_ajax_nonce()

Verifica nonce para requisições AJAX.

```php
public static function verify_ajax_nonce( 
    string $nonce_action, 
    string $nonce_field = 'nonce', 
    bool $send_json_error = true 
): bool
```

**Exemplo:**

```php
// Handler AJAX
public function ajax_save_settings() {
    // Verifica nonce (envia wp_send_json_error() automaticamente se inválido)
    if ( ! DPS_Request_Validator::verify_ajax_nonce( 'dps_save_settings' ) ) {
        return;
    }
    
    // Processa requisição
    $value = sanitize_text_field( $_POST['setting_value'] );
    update_option( 'dps_setting', $value );
    
    DPS_Request_Validator::send_json_success( 'Configurações salvas!' );
}
```

---

#### verify_ajax_admin()

Verifica nonce e capability para AJAX admin.

```php
public static function verify_ajax_admin( 
    string $nonce_action, 
    string $capability = 'manage_options', 
    string $nonce_field = 'nonce', 
    bool $send_json_error = true 
): bool
```

**Exemplo:**

```php
// Handler AJAX administrativo
public function ajax_delete_item() {
    // Verifica nonce + capability em uma chamada
    if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_item' ) ) {
        return; // Erro já enviado
    }
    
    $item_id = absint( $_POST['item_id'] );
    wp_delete_post( $item_id, true );
    
    DPS_Request_Validator::send_json_success( 'Item excluído com sucesso!' );
}
```

---

#### verify_admin_form()

Verifica nonce de formulário admin (POST).

```php
public static function verify_admin_form( 
    string $nonce_action, 
    string $nonce_field, 
    string $capability = 'manage_options', 
    bool $die_on_failure = true 
): bool
```

**Exemplo:**

```php
// Handler de formulário POST
public function handle_settings_save() {
    // Verifica nonce e permissão
    if ( ! DPS_Request_Validator::verify_admin_form( 'dps_save_settings', 'dps_settings_nonce' ) ) {
        return;
    }
    
    // Processa formulário
    $settings = [
        'option1' => DPS_Request_Validator::get_post_string( 'option1' ),
        'option2' => DPS_Request_Validator::get_post_int( 'option2' ),
    ];
    update_option( 'dps_settings', $settings );
    
    // Redireciona com mensagem
    DPS_Message_Helper::add_success( 'Configurações salvas!' );
    wp_redirect( admin_url( 'admin.php?page=dps-settings' ) );
    exit;
}
```

---

### Métodos de Extração Segura

#### get_post_int()

Obtém e sanitiza valor inteiro do POST.

```php
public static function get_post_int( string $field_name, int $default = 0 ): int
```

---

#### get_post_string()

Obtém e sanitiza string do POST.

```php
public static function get_post_string( string $field_name, string $default = '' ): string
```

---

#### get_post_textarea()

Obtém e sanitiza textarea do POST.

```php
public static function get_post_textarea( string $field_name, string $default = '' ): string
```

---

#### get_post_checkbox()

Obtém valor de checkbox do POST.

```php
public static function get_post_checkbox( string $field_name ): string
```

**Retorna:** `'1'` se marcado, `'0'` caso contrário

---

### Métodos de Resposta

#### send_json_success()

Envia resposta JSON de sucesso padronizada.

```php
public static function send_json_success( string $message, array $data = [] ): void
```

---

#### send_json_error()

Envia resposta JSON de erro padronizada.

```php
public static function send_json_error( string $message, string $code = 'error', int $status = 400 ): void
```

---

### Arquivo

`plugins/desi-pet-shower-base/includes/class-dps-request-validator.php`

---

## DPS_Loyalty_API

🔧 **Classe Helper Estática**

API pública do sistema de fidelidade.

### Métodos de Pontos

#### add_points()

Adiciona pontos ao cliente.

```php
public static function add_points( int $client_id, int $points, string $context = '' ): int|false
```

**Exemplo:**

```php
// Adicionar pontos por pagamento
$amount_cents = 15000; // R$ 150,00
$points = DPS_Loyalty_API::calculate_points_for_amount( $amount_cents, $client_id );
$new_balance = DPS_Loyalty_API::add_points( $client_id, $points, 'appointment_payment' );

if ( $new_balance !== false ) {
    DPS_Message_Helper::add_success( "Você ganhou {$points} pontos!" );
}
```

---

#### get_points()

Obtém saldo de pontos do cliente.

```php
public static function get_points( int $client_id ): int
```

**Exemplo:**

```php
$points = DPS_Loyalty_API::get_points( $client_id );
echo "Você tem {$points} pontos acumulados.";
```

---

#### redeem_points()

Resgata pontos do cliente.

```php
public static function redeem_points( int $client_id, int $points, string $context = '' ): int|false
```

**Exemplo:**

```php
$points_to_redeem = 100;
$new_balance = DPS_Loyalty_API::redeem_points( $client_id, $points_to_redeem, 'portal_redemption' );

if ( $new_balance === false ) {
    DPS_Message_Helper::add_error( 'Saldo insuficiente.' );
} else {
    DPS_Message_Helper::add_success( "Resgate realizado! Novo saldo: {$new_balance} pontos." );
}
```

---

### Métodos de Crédito

#### add_credit()

Adiciona crédito ao cliente (em centavos).

```php
public static function add_credit( int $client_id, int $amount_in_cents, string $context = '' ): int
```

---

#### get_credit()

Obtém saldo de crédito do cliente (em centavos).

```php
public static function get_credit( int $client_id ): int
```

---

#### use_credit()

Usa crédito do cliente.

```php
public static function use_credit( int $client_id, int $amount_in_cents, string $context = '' ): int
```

---

### Métodos de Indicação

#### get_referral_code()

Obtém código de indicação do cliente.

```php
public static function get_referral_code( int $client_id ): string
```

**Exemplo:**

```php
$code = DPS_Loyalty_API::get_referral_code( $client_id );
echo "Seu código de indicação: {$code}";
```

---

#### get_referral_url()

Obtém URL de indicação do cliente.

```php
public static function get_referral_url( int $client_id ): string
```

**Exemplo:**

```php
$url = DPS_Loyalty_API::get_referral_url( $client_id );
?>
<div class="referral-box">
    <p>Compartilhe seu link de indicação:</p>
    <input type="text" value="<?php echo esc_attr( $url ); ?>" readonly />
    <button onclick="copyToClipboard()">Copiar Link</button>
</div>
```

---

### Métodos de Tier (Níveis)

#### get_loyalty_tier()

Obtém nível de fidelidade do cliente.

```php
public static function get_loyalty_tier( int $client_id ): array
```

**Retorno:**

```php
[
    'current'      => string,  // Slug do tier atual (ex: 'ouro')
    'label'        => string,  // Label (ex: 'Ouro')
    'icon'         => string,  // Ícone (ex: '🥇')
    'color'        => string,  // Cor hex (ex: '#ffd700')
    'multiplier'   => float,   // Multiplicador de pontos (ex: 1.5)
    'min_points'   => int,     // Pontos mínimos do tier
    'next_tier'    => ?array,  // Dados do próximo tier ou null
    'progress'     => float,   // Progresso para próximo tier (0-100%)
]
```

**Exemplo:**

```php
$tier = DPS_Loyalty_API::get_loyalty_tier( $client_id );
?>
<div class="loyalty-tier">
    <span class="tier-icon"><?php echo esc_html( $tier['icon'] ); ?></span>
    <span class="tier-label"><?php echo esc_html( $tier['label'] ); ?></span>
    
    <?php if ( $tier['next_tier'] ) : ?>
        <div class="progress-bar">
            <div class="progress" style="width: <?php echo esc_attr( $tier['progress'] ); ?>%;"></div>
        </div>
        <p>Faltam <?php echo esc_html( $tier['next_tier']['min_points'] - DPS_Loyalty_API::get_points( $client_id ) ); ?> 
           pontos para <?php echo esc_html( $tier['next_tier']['label'] ); ?></p>
    <?php endif; ?>
</div>
```

---

### Métodos de Análise

#### get_top_clients()

Obtém ranking dos melhores clientes.

```php
public static function get_top_clients( int $limit = 10 ): array
```

**Exemplo:**

```php
$top_10 = DPS_Loyalty_API::get_top_clients( 10 );
?>
<h3>Top 10 Clientes</h3>
<ol>
    <?php foreach ( $top_10 as $client ) : ?>
        <li>
            <?php echo esc_html( DPS_Client_Helper::get_name( $client['client_id'] ) ); ?>
            - <?php echo esc_html( $client['points'] ); ?> pontos
        </li>
    <?php endforeach; ?>
</ol>
```

---

### Arquivo

`plugins/desi-pet-shower-loyalty/includes/class-dps-loyalty-api.php`

---

## Best Practices

### Segurança

```php
// ✅ BOM: Sempre validar nonce + capability
if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_client' ) ) {
    return;
}

// ✅ BOM: Validar ownership no portal
if ( ! dps_portal_assert_client_owns_resource( $client_id, $appointment_id, 'appointment' ) ) {
    wp_die( 'Acesso negado.' );
}

// ❌ RUIM: Confiar em dados do cliente sem validação
$client_id = $_GET['client_id']; // NUNCA faça isso sem validação
```

### Performance

```php
// ✅ BOM: Usar métodos batch
$client_data = DPS_Client_Helper::get_all_data( $client_id ); // 1 query

// ❌ RUIM: Múltiplas queries
$name = DPS_Client_Helper::get_name( $client_id );           // Query 1
$email = DPS_Client_Helper::get_email( $client_id );         // Query 2
$phone = DPS_Client_Helper::get_phone( $client_id );         // Query 3
```

### Money Handling

```php
// ✅ BOM: Sempre em centavos internamente
$price_cents = DPS_Money_Helper::parse_brazilian_format( $_POST['price'] );
update_post_meta( $item_id, 'price', $price_cents );

// ✅ BOM: Formatar apenas na saída
echo DPS_Money_Helper::format_currency( $price_cents );

// ❌ RUIM: Armazenar valores formatados
update_post_meta( $item_id, 'price', 'R$ 150,00' ); // NÃO!
```

---

## Security Functions Quick Reference

| Função | Uso | Exemplo |
|--------|-----|---------|
| `verify_ajax_nonce()` | AJAX público | `verify_ajax_nonce( 'dps_action' )` |
| `verify_ajax_admin()` | AJAX admin | `verify_ajax_admin( 'dps_action', 'manage_options' )` |
| `verify_admin_form()` | Form POST admin | `verify_admin_form( 'dps_save', 'nonce_field' )` |
| `dps_portal_assert_client_owns_resource()` | Portal ownership | `dps_portal_assert_client_owns_resource( $client_id, $resource_id, 'appointment' )` |

---

## Validation Functions Quick Reference

| Função | Retorno | Exemplo |
|--------|---------|---------|
| `DPS_Phone_Helper::is_valid_brazilian_phone()` | `bool` | Valida telefone BR |
| `DPS_Money_Helper::is_valid_money_string()` | `bool` | Valida string monetária |
| `DPS_Client_Helper::has_valid_phone()` | `bool` | Cliente tem telefone válido |
| `DPS_Client_Helper::has_valid_email()` | `bool` | Cliente tem email válido |

---

## Money Conversion Quick Reference

| De → Para | Função |
|-----------|--------|
| String BR → Centavos | `DPS_Money_Helper::parse_brazilian_format()` |
| Centavos → String BR | `DPS_Money_Helper::format_to_brazilian()` |
| Centavos → String com R$ | `DPS_Money_Helper::format_currency()` |
| Decimal → Centavos | `DPS_Money_Helper::decimal_to_cents()` |
| Centavos → Decimal | `DPS_Money_Helper::cents_to_decimal()` |

---

## Client Data Access Quick Reference

| Dado | Método |
|------|--------|
| Nome | `DPS_Client_Helper::get_name()` |
| Email | `DPS_Client_Helper::get_email()` |
| Telefone | `DPS_Client_Helper::get_phone()` |
| WhatsApp | `DPS_Client_Helper::get_whatsapp()` |
| Endereço completo | `DPS_Client_Helper::get_address()` |
| Todos os dados | `DPS_Client_Helper::get_all_data()` |
| Pets | `DPS_Client_Helper::get_pets()` |
| Busca por telefone | `DPS_Client_Helper::search_by_phone()` |
| Busca por email | `DPS_Client_Helper::search_by_email()` |

---

## Support & Contribution

Para reportar bugs ou sugerir melhorias nesta documentação:

- **GitHub Issues**: https://github.com/richardprobst/DPS/issues
- **Email**: contato@probst.pro
- **Website**: https://www.probst.pro

---

**© 2024 PRObst - desi.pet by PRObst**  
**Versão do Documento:** 1.0.0
