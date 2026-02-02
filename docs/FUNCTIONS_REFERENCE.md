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

### Helper Classes (Base Plugin)
- [DPS_Client_Helper](#dps_client_helper)
- [DPS_Money_Helper](#dps_money_helper)
- [DPS_Query_Helper](#dps_query_helper)
- [DPS_Phone_Helper](#dps_phone_helper)
- [DPS_WhatsApp_Helper](#dps_whatsapp_helper)
- [DPS_Message_Helper](#dps_message_helper)
- [DPS_IP_Helper](#dps_ip_helper)
- [DPS_Admin_Tabs_Helper](#dps_admin_tabs_helper)
- [DPS_CPT_Helper](#dps_cpt_helper)
- [DPS_Addon_Manager](#dps_addon_manager)
- [DPS_URL_Builder](#dps_url_builder)
- [DPS_Cache_Control](#dps_cache_control)
- [DPS_GitHub_Updater](#dps_github_updater)

### Core Utilities
- [DPS_Logger](#dps_logger)
- [DPS_Request_Validator](#dps_request_validator)

### Loyalty System
- [DPS_Loyalty_API](#dps_loyalty_api)
- [DPS_Loyalty_Achievements](#dps_loyalty_achievements)

### Communications Add-on
- [DPS_Communications_API](#dps_communications_api)
- [DPS_Communications_History](#dps_communications_history)
- [DPS_Communications_Retry](#dps_communications_retry)
- [DPS_Communications_Webhook](#dps_communications_webhook)

### Finance Add-on
- [DPS_Finance_API](#dps_finance_api)
- [DPS_Finance_Audit](#dps_finance_audit)
- [DPS_Finance_Reminders](#dps_finance_reminders)
- [DPS_Finance_Revenue_Query](#dps_finance_revenue_query)

### Client Portal Add-on
- [DPS_Portal_Session_Manager](#dps_portal_session_manager)
- [DPS_Portal_Token_Manager](#dps_portal_token_manager)
- [Portal Repository Classes](#portal-repositories)

### Push Notifications Add-on
- [DPS_Push_API](#dps_push_api)
- [DPS_Email_Reports](#dps_email_reports)

### AI Add-on
- [AI Logging Functions](#ai-logging-functions)
- [DPS_AI_Assistant](#dps_ai_assistant)
- [DPS_AI_Knowledge_Base](#dps_ai_knowledge_base)
- [DPS_AI_Client](#dps_ai_client)

### Agenda Add-on
- [DPS_Agenda_Capacity_Helper](#dps_agenda_capacity_helper)
- [DPS_Agenda_GPS_Helper](#dps_agenda_gps_helper)
- [DPS_Agenda_Payment_Helper](#dps_agenda_payment_helper)

### Stats Add-on
- [DPS_Stats_API](#dps_stats_api)

### Services Add-on
- [DPS_Services_API](#dps_services_api)

### Other Add-ons
- [Backup Add-on](#backup-add-on)
- [Booking Add-on](#booking-add-on)
- [Groomers Add-on](#groomers-add-on)
- [Payment Add-on](#payment-add-on)
- [Registration Add-on](#registration-add-on)
- [Stock Add-on](#stock-add-on)
- [Subscription Add-on](#subscription-add-on)

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


---

# ADD-ONS DOCUMENTATION

- 🎯 **Método de Instância**: Métodos que requerem instância da classe
- ⚠️ **Nota de Segurança**: Requer validações de nonce, capability ou sanitização
- 🎨 **Frontend**: Função usada no frontend
- 🛠️ **Admin**: Função restrita ao painel administrativo

---

## 📚 Table of Contents

### Base Plugin (Expanded)
- [DPS_Addon_Manager](#dps_addon_manager) - Gerenciamento de add-ons instalados
- [DPS_URL_Builder](#dps_url_builder) - Construção consistente de URLs
- [DPS_Cache_Control](#dps_cache_control) - Controle de cache para páginas DPS
- [DPS_CPT_Helper](#dps_cpt_helper) - Helper para registrar Custom Post Types
- [DPS_GitHub_Updater](#dps_github_updater) - Atualizações automáticas via GitHub

### Communications Add-on
- [DPS_Communications_API](#dps_communications_api) - API centralizada de comunicações
- [DPS_Communications_History](#dps_communications_history) - Histórico de mensagens
- [DPS_Communications_Retry](#dps_communications_retry) - Retry automático de falhas
- [DPS_Communications_Webhook](#dps_communications_webhook) - Webhooks de comunicação

### Finance Add-on  
- [DPS_Finance_API](#dps_finance_api) - API financeira centralizada
- [DPS_Finance_Audit](#dps_finance_audit) - Auditoria de transações
- [DPS_Finance_Reminders](#dps_finance_reminders) - Lembretes de pagamento
- [DPS_Finance_Revenue_Query](#dps_finance_revenue_query) - Consultas de receita

### Client Portal Add-on
- [DPS_Portal_Session_Manager](#dps_portal_session_manager) - Gerenciamento de sessões
- [DPS_Portal_Token_Manager](#dps_portal_token_manager) - Gerenciamento de tokens
- [DPS_Client_Repository](#dps_client_repository) - Repositório de clientes
- [DPS_Pet_Repository](#dps_pet_repository) - Repositório de pets
- [DPS_Appointment_Repository](#dps_appointment_repository) - Repositório de agendamentos
- [DPS_Finance_Repository](#dps_finance_repository) - Repositório financeiro

### Push Add-on
- [DPS_Push_API](#dps_push_api) - Push notifications (VAPID, Web Push)
- [DPS_Email_Reports](#dps_email_reports) - Relatórios por email

### AI Add-on
- [AI Logging Functions](#ai-logging-functions) - Funções globais de log
  - `dps_ai_log()`, `dps_ai_log_debug()`, `dps_ai_log_info()`, `dps_ai_log_warning()`, `dps_ai_log_error()`, `dps_ai_log_conversation()`
- [DPS_AI_Assistant](#dps_ai_assistant) - Assistente de IA
- [DPS_AI_Knowledge_Base](#dps_ai_knowledge_base) - Base de conhecimento
- [DPS_AI_Client](#dps_ai_client) - Cliente da API de IA

### Agenda Add-on
- [DPS_Agenda_Capacity_Helper](#dps_agenda_capacity_helper) - Gerenciamento de capacidade
- [DPS_Agenda_GPS_Helper](#dps_agenda_gps_helper) - Funcionalidades GPS/rotas
- [DPS_Agenda_Payment_Helper](#dps_agenda_payment_helper) - Pagamentos de agendamentos

### Stats Add-on
- [DPS_Stats_API](#dps_stats_api) - Estatísticas e métricas

### Services Add-on
- [DPS_Services_API](#dps_services_api) - API de serviços

### Other Add-ons
- [Backup Add-on](#backup-addon) - Backup e exportação
- [Booking Add-on](#booking-addon) - Sistema de reservas
- [Groomers Add-on](#groomers-addon) - Portal de tosadores
- [Payment Add-on](#payment-addon) - Integração MercadoPago
- [Registration Add-on](#registration-addon) - Registro de clientes
- [Stock Add-on](#stock-addon) - Controle de estoque
- [Subscription Add-on](#subscription-addon) - Sistema de assinaturas

---


## 📦 BASE PLUGIN (Expanded)

### Additional Helper Classes

O plugin base fornece várias classes helper reutilizáveis que centralizam lógica comum. Sempre que possível, reutilize esses helpers em vez de duplicar código.


---

### DPS_Addon_Manager

📦 **Helper Class** | **Base Plugin**

Gerenciador central de add-ons. Fornece listagem, categorização e verificação de instalação.

**Arquivo:** `plugins/desi-pet-shower-base/includes/class-dps-addon-manager.php`

**Total de métodos públicos:** 17


#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.1.0

Gerenciador de Add-ons do DPS. Fornece funcionalidades para: - Listar add-ons disponíveis e instalados - Verificar status de ativação - Determinar ordem correta de ativação baseada em dependências - Ativar/desativar add-ons em lote na ordem correta / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe gerenciadora de add-ons. / class DPS_Addon_Manager { /** Diretório onde os add-ons estão instalados (relativo a WP_PLUGIN_DIR). / const ADDONS_DIR = 'add-ons'; /** Instância singleton. / private static $instance = null; /** Lista de add-ons registrados com metadados. / private $addons = []; /** Mapeamento de slug do add-on para arquivo principal. / private $addon_files = []; /** Obtém a instância singleton.

**Assinatura:**

```php
DPS_Addon_Manager::get_instance()
```

**Retorno:** `DPS_Addon_Manager`

---


#### 🎯 get_all_addons()

**Método de Instância**

Construtor privado para singleton. / private function __construct() { $this->register_core_addons(); add_action( 'admin_menu', [ $this, 'register_admin_page' ], 20 ); add_action( 'admin_init', [ $this, 'handle_addon_actions' ] ); add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] ); } /** Registra os add-ons conhecidos do ecossistema DPS. Cada add-on tem: - slug: identificador único - name: nome de exibição - description: descrição curta - file: caminho relativo para o arquivo principal (dentro de add-ons/) - class: classe principal do add-on - dependencies: array de slugs de add-ons que devem estar ativos - priority: ordem de ativação (menor = primeiro) - category: categoria para organização na interface / private function register_core_addons() { $this->addons = [ // Categoria: Essenciais (ativados primeiro) 'services' => [ 'slug'         => 'services', 'name'         => __( 'Serviços', 'desi-pet-shower' ), 'description'  => __( 'Catálogo de serviços com preços por porte. Base para cálculos de valores.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-services/desi-pet-shower-services.php', 'class'        => 'DPS_Services_Addon', 'dependencies' => [], 'priority'     => 10, 'category'     => 'essential', 'icon'         => '💇', ], 'finance' => [ 'slug'         => 'finance', 'name'         => __( 'Financeiro', 'desi-pet-shower' ), 'description'  => __( 'Controle financeiro completo. Receitas, despesas e relatórios.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-finance/desi-pet-shower-finance-addon.php', 'class'        => 'DPS_Finance_Addon', 'dependencies' => [], 'priority'     => 15, 'category'     => 'essential', 'icon'         => '💰', ], 'communications' => [ 'slug'         => 'communications', 'name'         => __( 'Comunicações', 'desi-pet-shower' ), 'description'  => __( 'WhatsApp, SMS e e-mail integrados. Notificações automáticas.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-communications/desi-pet-shower-communications-addon.php', 'class'        => 'DPS_Communications_Addon', 'dependencies' => [], 'priority'     => 20, 'category'     => 'essential', 'icon'         => '📱', ], // Categoria: Operação 'agenda' => [ 'slug'         => 'agenda', 'name'         => __( 'Agenda', 'desi-pet-shower' ), 'description'  => __( 'Visualização e gestão de agendamentos diários.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php', 'class'        => 'DPS_Agenda_Addon', 'dependencies' => [ 'services' ], 'priority'     => 30, 'category'     => 'operation', 'icon'         => '📅', ], 'groomers' => [ 'slug'         => 'groomers', 'name'         => __( 'Groomers', 'desi-pet-shower' ), 'description'  => __( 'Gestão de profissionais e relatórios de produtividade.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-groomers/desi-pet-shower-groomers-addon.php', 'class'        => 'DPS_Groomers_Addon', 'dependencies' => [], 'priority'     => 35, 'category'     => 'operation', 'icon'         => '👤', ], 'subscription' => [ 'slug'         => 'subscription', 'name'         => __( 'Assinaturas', 'desi-pet-shower' ), 'description'  => __( 'Pacotes mensais de banho com frequência configurável.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-subscription/desi-pet-shower-subscription.php', 'class'        => 'DPS_Subscription_Addon', 'dependencies' => [ 'services', 'finance' ], 'priority'     => 40, 'category'     => 'operation', 'icon'         => '🔄', ], 'stock' => [ 'slug'         => 'stock', 'name'         => __( 'Estoque', 'desi-pet-shower' ), 'description'  => __( 'Controle de insumos com baixas automáticas.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-stock/desi-pet-shower-stock.php', 'class'        => 'DPS_Stock_Addon', 'dependencies' => [], 'priority'     => 45, 'category'     => 'operation', 'icon'         => '📦', ], // Categoria: Integrações 'payment' => [ 'slug'         => 'payment', 'name'         => __( 'Pagamentos', 'desi-pet-shower' ), 'description'  => __( 'Integração com Mercado Pago para links de pagamento.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-payment/desi-pet-shower-payment-addon.php', 'class'        => 'DPS_Payment_Addon', 'dependencies' => [ 'finance' ], 'priority'     => 50, 'category'     => 'integrations', 'icon'         => '💳', ], 'push' => [ 'slug'         => 'push', 'name'         => __( 'Notificações Push', 'desi-pet-shower' ), 'description'  => __( 'Relatórios diários/semanais por e-mail e Telegram.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-push/desi-pet-shower-push-addon.php', 'class'        => 'DPS_Push_Addon', 'dependencies' => [], 'priority'     => 55, 'category'     => 'integrations', 'icon'         => '🔔', ], // Categoria: Cliente 'registration' => [ 'slug'         => 'registration', 'name'         => __( 'Cadastro Público', 'desi-pet-shower' ), 'description'  => __( 'Formulário público para cadastro de clientes e pets.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-registration/desi-pet-shower-registration-addon.php', 'class'        => 'DPS_Registration_Addon', 'dependencies' => [], 'priority'     => 60, 'category'     => 'client', 'icon'         => '📝', ], 'client-portal' => [ 'slug'         => 'client-portal', 'name'         => __( 'Portal do Cliente', 'desi-pet-shower' ), 'description'  => __( 'Área autenticada para clientes visualizarem seus dados.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-client-portal/desi-pet-shower-client-portal.php', 'class'        => 'DPS_Client_Portal', 'dependencies' => [], 'priority'     => 65, 'category'     => 'client', 'icon'         => '🏠', ], 'loyalty' => [ 'slug'         => 'loyalty', 'name'         => __( 'Fidelidade & Campanhas', 'desi-pet-shower' ), 'description'  => __( 'Programa de pontos, indicações e campanhas.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-loyalty/desi-pet-shower-loyalty.php', 'class'        => 'DPS_Loyalty_Addon', 'dependencies' => [], 'priority'     => 70, 'category'     => 'client', 'icon'         => '🎁', ], // Categoria: Avançado 'ai' => [ 'slug'         => 'ai', 'name'         => __( 'Assistente de IA', 'desi-pet-shower' ), 'description'  => __( 'Chat inteligente no Portal do Cliente e sugestões de mensagens.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-ai/desi-pet-shower-ai-addon.php', 'class'        => 'DPS_AI_Addon', 'dependencies' => [ 'client-portal' ], 'priority'     => 75, 'category'     => 'advanced', 'icon'         => '🤖', ], 'stats' => [ 'slug'         => 'stats', 'name'         => __( 'Estatísticas', 'desi-pet-shower' ), 'description'  => __( 'Dashboard com métricas, gráficos e relatórios.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-stats/desi-pet-shower-stats-addon.php', 'class'        => 'DPS_Stats_Addon', 'dependencies' => [], 'priority'     => 80, 'category'     => 'advanced', 'icon'         => '📊', ], // Categoria: Sistema 'backup' => [ 'slug'         => 'backup', 'name'         => __( 'Backup & Restauração', 'desi-pet-shower' ), 'description'  => __( 'Exportação e importação de todos os dados do sistema.', 'desi-pet-shower' ), 'file'         => 'desi-pet-shower-backup/desi-pet-shower-backup-addon.php', 'class'        => 'DPS_Backup_Addon', 'dependencies' => [], 'priority'     => 85, 'category'     => 'system', 'icon'         => '💾', ], ]; // Mapeia arquivos para busca rápida foreach ( $this->addons as $slug => $addon ) { $this->addon_files[ $slug ] = $addon['file']; } } /** Retorna todos os add-ons registrados.

**Assinatura:**

```php
$addonmanager->get_all_addons()
```

**Retorno:** `array`

---


#### 🎯 get_categories()

**Método de Instância**

Retorna categorias de add-ons com labels traduzidos.

**Assinatura:**

```php
$addonmanager->get_categories()
```

**Retorno:** `array`

---


#### 🎯 get_addons_by_category()

**Método de Instância**

Retorna add-ons agrupados por categoria.

**Assinatura:**

```php
$addonmanager->get_addons_by_category()
```

**Retorno:** `array`

---


#### 🎯 is_installed()

**Método de Instância**

Verifica se um add-on está instalado (arquivo existe).

**Assinatura:**

```php
$addonmanager->is_installed($slug)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$slug` | `string` | Slug do add-on. |

**Retorno:** `bool`

---


#### 🎯 is_active()

**Método de Instância**

Verifica se um add-on está ativo.

**Assinatura:**

```php
$addonmanager->is_active($slug)
```

**Parâmetros:** 1 parâmetro(s)

---


#### 🎯 get_addon_file()

**Método de Instância**

Retorna o caminho completo do arquivo principal do add-on.

**Assinatura:**

```php
$addonmanager->get_addon_file($slug)
```

**Parâmetros:** 1 parâmetro(s)

---


#### 🎯 get_dependents()

**Método de Instância**

Retorna add-ons que dependem de um determinado add-on.

**Assinatura:**

```php
$addonmanager->get_dependents($slug)
```

**Parâmetros:** 1 parâmetro(s)

---


*... e mais 9 métodos. Consulte o arquivo fonte para documentação completa.*


---

### DPS_URL_Builder

📦 **Helper Class** | **Base Plugin**

Helper para construção consistente de URLs de edição, exclusão e visualização.

**Arquivo:** `plugins/desi-pet-shower-base/includes/class-dps-url-builder.php`

**Total de métodos públicos:** 8


#### 🔧 build_edit_url()

**Método Estático** | **Desde:** 1.0.2

Helper class para construção de URLs do painel. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe utilitária para construção consistente de URLs no plugin. / class DPS_URL_Builder { /** Constrói URL para editar um registro.

**Assinatura:**

```php
DPS_URL_Builder::build_edit_url($record_type, $record_id, $tab = '', $base_url = null)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$record_type` | `string` | Tipo de registro ('client', 'pet', 'appointment'). |
| `$record_id` | `int` | ID do registro. |
| `$tab` | `string` | Aba de destino (opcional). |
| `$base_url` | `string` | URL base (opcional, usa permalink atual se não fornecida). |

**Retorno:** `string URL completa para edição.`

---


#### 🔧 build_delete_url()

**Método Estático**

Constrói URL para excluir um registro com nonce de segurança.

**Assinatura:**

```php
DPS_URL_Builder::build_delete_url($record_type, $record_id, $tab = '', $base_url = null)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$record_type` | `string` | Tipo de registro ('client', 'pet', 'appointment'). |
| `$record_id` | `int` | ID do registro. |
| `$tab` | `string` | Aba de destino (opcional). |
| `$base_url` | `string` | URL base (opcional, usa permalink atual se não fornecida). |

**Retorno:** `string URL completa para exclusão com nonce.`

---


#### 🔧 build_view_url()

**Método Estático**

Constrói URL para visualizar detalhes de um registro.

**Assinatura:**

```php
DPS_URL_Builder::build_view_url($record_type, $record_id, $base_url = null)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$record_type` | `string` | Tipo de registro ('client', 'pet', 'appointment'). |
| `$record_id` | `int` | ID do registro. |
| `$base_url` | `string` | URL base (opcional, usa permalink atual se não fornecida). |

**Retorno:** `string URL completa para visualização.`

---


#### 🔧 build_tab_url()

**Método Estático**

Constrói URL para uma aba específica.

**Assinatura:**

```php
DPS_URL_Builder::build_tab_url($tab, $base_url = null)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$tab` | `string` | Nome da aba. |
| `$base_url` | `string` | URL base (opcional, usa permalink atual se não fornecida). |

**Retorno:** `string URL completa para a aba.`

---


#### 🔧 build_schedule_url()

**Método Estático**

Constrói URL para agendar atendimento para um cliente específico.

**Assinatura:**

```php
DPS_URL_Builder::build_schedule_url($client_id, $base_url = null)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$client_id` | `int` | ID do cliente. |
| `$base_url` | `string` | URL base (opcional, usa permalink atual se não fornecida). |

**Retorno:** `string URL completa para agendamento.`

---


#### 🔧 remove_action_params()

**Método Estático**

Remove parâmetros de ação da URL.

**Assinatura:**

```php
DPS_URL_Builder::remove_action_params($url)
```

**Parâmetros:** 1 parâmetro(s)

---


#### 🔧 safe_get_permalink()

**Método Estático**

Safe wrapper for get_permalink() that always returns a string. Prevents PHP 8.1+ deprecation warnings caused by passing null/false to functions like strpos(), str_replace(), add_query_arg(), etc.

**Assinatura:**

```php
DPS_URL_Builder::safe_get_permalink($post_param = null)
```

**Parâmetros:** 1 parâmetro(s)

---


#### 🔧 get_clean_current_url()

**Método Estático**

Obtém URL base da página atual sem parâmetros de ação.

**Assinatura:**

```php
DPS_URL_Builder::get_clean_current_url()
```


---

### DPS_Cache_Control

📦 **Helper Class** | **Base Plugin**

Controle de cache: desabilita cache para páginas com shortcodes DPS, evitando conteúdo desatualizado.

**Arquivo:** `plugins/desi-pet-shower-base/includes/class-dps-cache-control.php`

**Total de métodos públicos:** 9


#### 🔧 init()

**Método Estático** | **Desde:** 1.1.1

Classe responsável pelo controle de cache das páginas do DPS. Garante que páginas do sistema não sejam armazenadas em cache, forçando o navegador e plugins de cache a sempre buscar conteúdo atualizado do servidor. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** DPS_Cache_Control - Prevenção de cache para páginas do sistema DPS. Esta classe intercepta requisições para páginas que contêm shortcodes do DPS e envia headers HTTP de no-cache, além de definir a constante DONOTCACHEPAGE para plugins de cache do WordPress. / class DPS_Cache_Control { /** Lista de shortcodes DPS que devem ter cache desabilitado. / private static $dps_shortcodes = [ // Base 'dps_base', 'dps_configuracoes', 'dps_tosa_consent', // Client Portal 'dps_client_portal', 'dps_client_login', // Agenda 'dps_agenda_page', 'dps_agenda_dashboard', 'dps_charges_notes', // Groomers 'dps_groomer_dashboard', 'dps_groomer_agenda', 'dps_groomer_review', 'dps_groomer_reviews', 'dps_groomer_portal', 'dps_groomer_login', // Services 'dps_services_catalog', // Finance 'dps_fin_docs', // Registration 'dps_registration_form', // AI 'dps_ai_chat', ]; /** Indica se os headers de no-cache já foram enviados nesta requisição. / private static $headers_sent = false; /** Inicializa o controle de cache. Registra hooks para detecção de páginas DPS e envio de headers.

**Assinatura:**

```php
DPS_Cache_Control::init()
```

---


#### 🔧 maybe_disable_cache_by_url_params()

**Método Estático** | **Desde:** 1.2.1

Desabilita cache baseado em parâmetros de URL específicos do DPS. Esta função é executada muito cedo (hook 'wp') para capturar requisições com parâmetros dinâmicos como client_id e token antes que caches agressivos (ex.: page builders, LiteSpeed Cache, WP Rocket) sirvam conteúdo cacheado.

**Assinatura:**

```php
DPS_Cache_Control::maybe_disable_cache_by_url_params()
```

**Retorno:** `void`

---


#### 🔧 maybe_disable_page_cache()

**Método Estático**

Verifica se a página atual contém shortcodes DPS e desabilita cache. Este método é executado no hook 'template_redirect', antes que qualquer output seja enviado ao navegador.

**Assinatura:**

```php
DPS_Cache_Control::maybe_disable_page_cache()
```

---


#### 🔧 disable_cache()

**Método Estático**

Verifica se o conteúdo da página atual contém shortcodes do DPS. Além do conteúdo principal do post, também verifica metadados comuns de page builders como Elementor, YooTheme e Beaver Builder. / private static function page_has_dps_shortcode() { global $post; // Sem post atual, não há shortcode if ( ! $post instanceof WP_Post ) { return false; } $content = $post->post_content; // Verifica cada shortcode DPS no conteúdo principal foreach ( self::$dps_shortcodes as $shortcode ) { if ( has_shortcode( $content, $shortcode ) ) { return true; } } // Pré-constrói padrões de busca para shortcodes (otimização para loops) // Inclui espaço ou ] após o nome para evitar falsos positivos (ex: [dps_tosa vs [dps_tosa_extra]) // Nota: shortcodes DPS são nomes seguros sem caracteres especiais, então string literal é segura para strpos $shortcode_patterns = []; foreach ( self::$dps_shortcodes as $shortcode ) { $shortcode_patterns[] = '[' . $shortcode . ' '; $shortcode_patterns[] = '[' . $shortcode . ']'; } // Verifica em metadados de page builders populares // Elementor armazena dados em _elementor_data (formato JSON) $elementor_data = get_post_meta( $post->ID, '_elementor_data', true ); if ( self::metadata_contains_shortcode( $elementor_data, $shortcode_patterns ) ) { return true; } // YooTheme armazena dados em _yootheme_source (formato JSON) $yootheme_source = get_post_meta( $post->ID, '_yootheme_source', true ); if ( self::metadata_contains_shortcode( $yootheme_source, $shortcode_patterns ) ) { return true; } return false; } /** Verifica se uma string de metadados contém padrões de shortcode. / private static function metadata_contains_shortcode( $metadata, array $patterns ) { if ( ! $metadata || ! is_string( $metadata ) ) { return false; } foreach ( $patterns as $pattern ) { if ( strpos( $metadata, $pattern ) !== false ) { return true; } } return false; } /** Desabilita o cache para a página atual. Define a constante DONOTCACHEPAGE e prepara para envio de headers.

**Assinatura:**

```php
DPS_Cache_Control::disable_cache()
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$metadata` | `mixed` | String de metadados ou valor vazio. |
| `$patterns` | `array` | Padrões de shortcode para buscar. |

**Retorno:** `bool True se a página contém shortcodes DPS.`

---


#### 🔧 send_nocache_headers()

**Método Estático**

Envia os headers HTTP de no-cache. Este método é chamado tanto pelo hook 'send_headers' quanto diretamente quando necessário.

**Assinatura:**

```php
DPS_Cache_Control::send_nocache_headers()
```

---


#### 🔧 disable_admin_cache()

**Método Estático**

Desabilita cache para páginas administrativas do DPS. Garante que todas as páginas admin do DPS não sejam cacheadas, independente de shortcodes.

**Assinatura:**

```php
DPS_Cache_Control::disable_admin_cache()
```

---


#### 🔧 force_no_cache()

**Método Estático**

Método público para forçar desabilitação de cache. Pode ser chamado por add-ons ou outros componentes que precisam garantir que uma página específica não seja cacheada. ```php // Em qualquer shortcode ou handler: DPS_Cache_Control::force_no_cache(); ```

**Assinatura:**

```php
DPS_Cache_Control::force_no_cache()
```

---


#### 🔧 register_shortcode()

**Método Estático**

Adiciona um shortcode à lista de shortcodes DPS. Permite que add-ons registrem seus próprios shortcodes para desabilitação automática de cache.

**Assinatura:**

```php
DPS_Cache_Control::register_shortcode($shortcode)
```

**Parâmetros:** 1 parâmetro(s)

---


*... e mais 1 métodos. Consulte o arquivo fonte para documentação completa.*


---

### DPS_CPT_Helper

📦 **Helper Class** | **Base Plugin**

Helper para registrar Custom Post Types com opções padronizadas.

**Arquivo:** `plugins/desi-pet-shower-base/includes/class-dps-cpt-helper.php`

**Total de métodos públicos:** 1


#### 🎯 register()

**Método de Instância**

Executa o registro do CPT com argumentos opcionais adicionais.

**Assinatura:**

```php
$cpthelper->register(array $args = [])
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$args` | `array` | Argumentos adicionais ou sobrescritos. |


---

### DPS_GitHub_Updater

📦 **Helper Class** | **Base Plugin**

Sistema de atualização automática via GitHub Releases.

**Arquivo:** `plugins/desi-pet-shower-base/includes/class-dps-github-updater.php`

**Total de métodos públicos:** 9


#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.2.0

DPS GitHub Updater Classe responsável por verificar e gerenciar atualizações dos plugins DPS diretamente do repositório GitHub. / // Impede acesso direto. if ( ! defined( 'ABSPATH' ) ) { exit; } /** Class DPS_GitHub_Updater Implementa verificação de atualizações via API do GitHub. Suporta o plugin base e todos os add-ons do sistema DPS. / class DPS_GitHub_Updater { /** Repositório GitHub (owner/repo). / private $github_repo = 'richardprobst/DPS'; /** URL da API do GitHub. / private $github_api_url = 'https://api.github.com'; /** Transient para cache da verificação de updates. / private $cache_key = 'dps_github_update_data'; /** Tempo de cache em segundos (12 horas). / private $cache_expiration = 43200; /** Lista de plugins gerenciados pelo updater. Mapeamento: slug do plugin => caminho relativo no repositório GitHub. / private $plugins = array(); /** Instância singleton. / private static $instance = null; /** Retorna a instância singleton.

**Assinatura:**

```php
DPS_GitHub_Updater::get_instance()
```

**Retorno:** `DPS_GitHub_Updater`

---


#### 🎯 check_for_updates()

**Método de Instância**

Construtor privado para singleton. / private function __construct() { $this->register_plugins(); $this->init_hooks(); } /** Registra os plugins que serão atualizados. / private function register_plugins() { $this->plugins = array( // Plugin Base 'desi-pet-shower-base/desi-pet-shower-base.php' => array( 'name'        => 'desi.pet by PRObst – Base', 'repo_path'   => 'plugins/desi-pet-shower-base', 'slug'        => 'desi-pet-shower-base', ), // Add-ons 'desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php' => array( 'name'        => 'desi.pet by PRObst – Agenda Add-on', 'repo_path'   => 'plugins/desi-pet-shower-agenda', 'slug'        => 'desi-pet-shower-agenda', ), 'desi-pet-shower-ai/desi-pet-shower-ai-addon.php' => array( 'name'        => 'desi.pet by PRObst – AI Add-on', 'repo_path'   => 'plugins/desi-pet-shower-ai', 'slug'        => 'desi-pet-shower-ai', ), 'desi-pet-shower-backup/desi-pet-shower-backup-addon.php' => array( 'name'        => 'desi.pet by PRObst – Backup Add-on', 'repo_path'   => 'plugins/desi-pet-shower-backup', 'slug'        => 'desi-pet-shower-backup', ), 'desi-pet-shower-client-portal/desi-pet-shower-client-portal.php' => array( 'name'        => 'desi.pet by PRObst – Client Portal Add-on', 'repo_path'   => 'plugins/desi-pet-shower-client-portal', 'slug'        => 'desi-pet-shower-client-portal', ), 'desi-pet-shower-communications/desi-pet-shower-communications-addon.php' => array( 'name'        => 'desi.pet by PRObst – Communications Add-on', 'repo_path'   => 'plugins/desi-pet-shower-communications', 'slug'        => 'desi-pet-shower-communications', ), 'desi-pet-shower-finance/desi-pet-shower-finance-addon.php' => array( 'name'        => 'desi.pet by PRObst – Financeiro Add-on', 'repo_path'   => 'plugins/desi-pet-shower-finance', 'slug'        => 'desi-pet-shower-finance', ), 'desi-pet-shower-groomers/desi-pet-shower-groomers-addon.php' => array( 'name'        => 'desi.pet by PRObst – Groomers Add-on', 'repo_path'   => 'plugins/desi-pet-shower-groomers', 'slug'        => 'desi-pet-shower-groomers', ), 'desi-pet-shower-loyalty/desi-pet-shower-loyalty.php' => array( 'name'        => 'desi.pet by PRObst – Loyalty Add-on', 'repo_path'   => 'plugins/desi-pet-shower-loyalty', 'slug'        => 'desi-pet-shower-loyalty', ), 'desi-pet-shower-payment/desi-pet-shower-payment-addon.php' => array( 'name'        => 'desi.pet by PRObst – Payment Add-on', 'repo_path'   => 'plugins/desi-pet-shower-payment', 'slug'        => 'desi-pet-shower-payment', ), 'desi-pet-shower-push/desi-pet-shower-push-addon.php' => array( 'name'        => 'desi.pet by PRObst – Push Add-on', 'repo_path'   => 'plugins/desi-pet-shower-push', 'slug'        => 'desi-pet-shower-push', ), 'desi-pet-shower-registration/desi-pet-shower-registration-addon.php' => array( 'name'        => 'desi.pet by PRObst – Registration Add-on', 'repo_path'   => 'plugins/desi-pet-shower-registration', 'slug'        => 'desi-pet-shower-registration', ), 'desi-pet-shower-services/desi-pet-shower-services.php' => array( 'name'        => 'desi.pet by PRObst – Services Add-on', 'repo_path'   => 'plugins/desi-pet-shower-services', 'slug'        => 'desi-pet-shower-services', ), 'desi-pet-shower-stats/desi-pet-shower-stats-addon.php' => array( 'name'        => 'desi.pet by PRObst – Stats Add-on', 'repo_path'   => 'plugins/desi-pet-shower-stats', 'slug'        => 'desi-pet-shower-stats', ), 'desi-pet-shower-stock/desi-pet-shower-stock.php' => array( 'name'        => 'desi.pet by PRObst – Stock Add-on', 'repo_path'   => 'plugins/desi-pet-shower-stock', 'slug'        => 'desi-pet-shower-stock', ), 'desi-pet-shower-subscription/desi-pet-shower-subscription.php' => array( 'name'        => 'desi.pet by PRObst – Subscription Add-on', 'repo_path'   => 'plugins/desi-pet-shower-subscription', 'slug'        => 'desi-pet-shower-subscription', ), ); } /** Inicializa os hooks do WordPress. / private function init_hooks() { // Hook para verificar atualizações add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) ); // Hook para informações do plugin (popup de detalhes) add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 ); // Hook após instalar plugin (limpar cache) add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 ); // Hook para limpar cache quando verificar updates manualmente add_action( 'admin_init', array( $this, 'maybe_force_check' ) ); // Hook para mensagem no admin add_action( 'admin_notices', array( $this, 'update_notice' ) ); } /** Verifica se há atualizações disponíveis.

**Assinatura:**

```php
$githubupdater->check_for_updates($transient)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$transient` | `object` | Transient de atualizações. |

**Retorno:** `object`

---


#### 🎯 plugin_info()

**Método de Instância**

Fornece informações detalhadas do plugin para o popup.

**Assinatura:**

```php
$githubupdater->plugin_info($result, $action, $args)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$result` | `false|object|array` | Resultado padrão. |
| `$action` | `string` | Ação sendo executada. |
| `$args` | `object` | Argumentos da requisição. |

**Retorno:** `false|object|array`

---


#### 🎯 after_install()

**Método de Instância**

Obtém dados da release mais recente do GitHub. / private function get_release_data( $force_refresh = false ) { // Verifica cache if ( ! $force_refresh ) { $cached_data = get_transient( $this->cache_key ); if ( false !== $cached_data ) { return $cached_data; } } // Faz requisição à API do GitHub $url = sprintf( '%s/repos/%s/releases/latest', $this->github_api_url, $this->github_repo ); $response = wp_remote_get( $url, array( 'timeout'    => 15, 'headers'    => array( 'Accept'     => 'application/vnd.github.v3+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; DPS-Updater', ), ) ); if ( is_wp_error( $response ) ) { return null; } $response_code = wp_remote_retrieve_response_code( $response ); if ( 200 !== $response_code ) { return null; } $body = wp_remote_retrieve_body( $response ); $data = json_decode( $body, true ); if ( empty( $data ) || ! is_array( $data ) ) { return null; } // Prepara dados relevantes $release_data = array( 'tag_name'     => $data['tag_name'] ?? '', 'name'         => $data['name'] ?? '', 'body'         => $data['body'] ?? '', 'published_at' => $data['published_at'] ?? '', 'html_url'     => $data['html_url'] ?? '', 'zipball_url'  => $data['zipball_url'] ?? '', 'tarball_url'  => $data['tarball_url'] ?? '', 'assets'       => array(), ); // Processa assets (arquivos zip anexados à release) if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) { foreach ( $data['assets'] as $asset ) { $release_data['assets'][ $asset['name'] ] = $asset['browser_download_url']; } } // Salva no cache set_transient( $this->cache_key, $release_data, $this->cache_expiration ); return $release_data; } /** Extrai a versão da tag. / private function get_latest_version( $release_data ) { $tag = $release_data['tag_name'] ?? ''; // Remove prefixo 'v' se existir return ltrim( $tag, 'vV' ); } /** Obtém a URL de download do plugin. / private function get_download_url( $release_data, $repo_path ) { // Primeiro, verifica se há um asset .zip específico para o plugin $plugin_slug = basename( $repo_path ); $zip_name    = $plugin_slug . '.zip'; if ( ! empty( $release_data['assets'][ $zip_name ] ) ) { return $release_data['assets'][ $zip_name ]; } // Fallback: usa o zipball_url do repositório completo // Nota: O usuário precisará extrair manualmente o plugin desejado return $release_data['zipball_url'] ?? ''; } /** Obtém o changelog formatado. / private function get_changelog( $release_data ) { $body = $release_data['body'] ?? ''; if ( empty( $body ) ) { return '<p>' . esc_html__( 'Sem notas de lançamento disponíveis.', 'desi-pet-shower' ) . '</p>'; } // Converte Markdown básico para HTML $html = nl2br( esc_html( $body ) ); $html = preg_replace( '/^## (.+)$/m', '<h4>$1</h4>', $html ); $html = preg_replace( '/^### (.+)$/m', '<h5>$1</h5>', $html ); $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html ); $html = preg_replace( '/(<li>.+<\/li>\n?)+/', '<ul>$0</ul>', $html ); return $html; } /** Obtém a descrição do plugin. / private function get_plugin_description( $plugin_file ) { $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false ); return $plugin_data['Description'] ?? ''; } /** Retorna instruções de instalação. / private function get_installation_instructions() { return sprintf( '<ol> <li>%s</li> <li>%s</li> <li>%s</li> </ol>', esc_html__( 'Faça o download do arquivo .zip do plugin.', 'desi-pet-shower' ), esc_html__( 'No painel WordPress, vá em Plugins → Adicionar Novo → Enviar Plugin.', 'desi-pet-shower' ), esc_html__( 'Ative o plugin após a instalação.', 'desi-pet-shower' ) ); } /** Busca o arquivo do plugin pelo slug. / private function get_plugin_file_by_slug( $slug ) { foreach ( $this->plugins as $plugin_file => $plugin_info ) { if ( $plugin_info['slug'] === $slug ) { return $plugin_file; } } return null; } /** Ação após instalação do plugin.

**Assinatura:**

```php
$githubupdater->after_install($response, $hook_extra, $result)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$force_refresh` | `bool` | Forçar atualização do cache. |
| `$release_data` | `array` | Dados da release. |
| `$release_data` | `array` | Dados da release. |
| `$repo_path` | `string` | Caminho do plugin no repositório. |
| `$release_data` | `array` | Dados da release. |
| `$plugin_file` | `string` | Arquivo do plugin. |
| `$slug` | `string` | Slug do plugin. |
| `$response` | `bool` | Resposta da instalação. |
| `$hook_extra` | `array` | Dados extras. |
| `$result` | `array` | Resultado da instalação. |

**Retorno:** `array|null`

---


#### 🎯 maybe_force_check()

**Método de Instância**

Verifica se deve forçar checagem de atualizações. Requer nonce válido para proteção CSRF.

**Assinatura:**

```php
$githubupdater->maybe_force_check()
```

---


#### 🎯 update_notice()

**Método de Instância**

Exibe aviso sobre atualizações disponíveis.

**Assinatura:**

```php
$githubupdater->update_notice()
```

---


#### 🎯 force_check()

**Método de Instância**

Método público para forçar verificação de atualizações.

**Assinatura:**

```php
$githubupdater->force_check()
```

---


#### 🎯 get_managed_plugins()

**Método de Instância**

Retorna a lista de plugins gerenciados.

**Assinatura:**

```php
$githubupdater->get_managed_plugins()
```

---


*... e mais 1 métodos. Consulte o arquivo fonte para documentação completa.*


#### 💡 Exemplo de Uso: DPS_URL_Builder

```php
// Construir URL de edição de cliente
$edit_url = DPS_URL_Builder::build_edit_url('client', 123, 'info');
// Resultado: https://example.com/page?dps_edit=client&id=123&tab=info

// Construir URL de exclusão com nonce
$delete_url = DPS_URL_Builder::build_delete_url('pet', 456);
// Resultado: https://example.com/page?dps_delete=pet&id=456&dps_nonce=abc123

// Obter URL limpa (sem parâmetros de ação)
$clean_url = DPS_URL_Builder::get_clean_current_url();
```


## 📞 COMMUNICATIONS ADD-ON

### Overview

O add-on de comunicações centraliza todo o envio de mensagens (WhatsApp, Email, SMS). **Outros add-ons DEVEM usar esta API** em vez de implementar envio próprio.


### DPS_Communications_API

API principal para envio de comunicações. Interface única para WhatsApp, Email e SMS.

**Arquivo:** `plugins/desi-pet-shower-communications/includes/class-dps-communications-api.php`

**Métodos públicos:** 7


#### 🔧 get_instance()

**Método Estático** | **Desde:** 0.2.0

API centralizada de comunicações Esta classe centraliza toda a lógica de envio de comunicações (WhatsApp, e-mail, SMS) no sistema DPS. Outros add-ons (Agenda, Portal, Finance, etc.) devem usar esta API ao invés de implementar envio de mensagens diretamente. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe API de Comunicações Interface pública para envio de todas as comunicações do sistema. Responsável por: - Enviar mensagens via WhatsApp, e-mail e SMS - Aplicar templates de mensagens - Registrar logs de envio - Disparar hooks para extensibilidade / class DPS_Communications_API { /** Chave de opção para configurações / const OPTION_KEY = 'dps_comm_settings'; /** Timeout padrão para requests externos em segundos / const REQUEST_TIMEOUT = 30; /** Instância singleton / private static $instance = null; /** Último erro ocorrido durante envio / private $last_error = ''; /** Obtém instância singleton

**Assinatura:**

```php
DPS_Communications_API::get_instance()
```

**Retorno:** `DPS_Communications_API`

---


#### 🎯 get_last_error()

**Método de Instância** | **Desde:** 0.3.0

Construtor privado (singleton) / private function __construct() { // Construtor privado para padrão singleton } /** Obtém o último erro ocorrido

**Assinatura:**

```php
$communicationsapi->get_last_error()
```

**Retorno:** `string`

---


#### 🎯 send_whatsapp()

**Método de Instância** | **Desde:** 0.2.1

Registra log de forma segura, verificando disponibilidade do DPS_Logger. / private function safe_log( $level, $message, $context = [] ) { // Remove possíveis dados sensíveis do contexto $safe_context = $this->sanitize_log_context( $context ); if ( class_exists( 'DPS_Logger' ) ) { DPS_Logger::log( $level, $message, $safe_context ); } } /** Remove dados sensíveis do contexto de log. / private function sanitize_log_context( $context ) { $sensitive_keys = [ 'phone', 'to', 'email', 'message', 'body', 'subject', 'api_key' ]; $safe           = []; foreach ( $context as $key => $value ) { if ( in_array( $key, $sensitive_keys, true ) ) { // Mascarar dados sensíveis if ( is_string( $value ) && ! empty( $value ) ) { $safe[ $key ] = '[REDACTED:' . strlen( $value ) . ' chars]'; } else { $safe[ $key ] = '[REDACTED]'; } } else { $safe[ $key ] = $value; } } return $safe; } /** Envia mensagem via WhatsApp Este é o método central para envio de WhatsApp no sistema. Toda comunicação via WhatsApp deve passar por aqui. DPS_Communications_API::get_instance()->send_whatsapp( '11987654321', 'Seu agendamento está confirmado!', ['appointment_id' => 123, 'type' => 'confirmation'] );

**Assinatura:**

```php
$communicationsapi->send_whatsapp($to, $message, $context = [])
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$level` | `string` | Nível do log (info, warning, error). |
| `$message` | `string` | Mensagem do log. |
| `$context` | `array` | Contexto adicional (sem PII). |
| `$context` | `array` | Contexto original. |
| `$to` | `string` | Número de telefone do destinatário (será formatado automaticamente) |
| `$message` | `string` | Mensagem a ser enviada |
| `$context` | `array` | Contexto adicional (appointment_id, client_id, etc.) para logs e hooks |

**Retorno:** `array Contexto sanitizado.`

---


#### 🎯 send_email()

**Método de Instância**

Envia e-mail Método central para envio de e-mails no sistema. DPS_Communications_API::get_instance()->send_email( 'cliente@email.com', 'Confirmação de agendamento', 'Seu agendamento foi confirmado para...', ['appointment_id' => 123] );

**Assinatura:**

```php
$communicationsapi->send_email($to, $subject, $body, $context = [])
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$to` | `string` | Endereço de e-mail do destinatário |
| `$subject` | `string` | Assunto do e-mail |
| `$body` | `string` | Corpo da mensagem |
| `$context` | `array` | Contexto adicional para logs e hooks |

**Retorno:** `bool True se enviado com sucesso, false caso contrário`

---


#### 🎯 send_appointment_reminder()

**Método de Instância**

Envia lembrete de agendamento Método específico para envio de lembretes de agendamentos. Busca dados do agendamento e usa template configurado.

**Assinatura:**

```php
$communicationsapi->send_appointment_reminder($appointment_id)
```

**Parâmetros:** 1 parâmetro(s)

---


#### 🎯 send_payment_notification()

**Método de Instância**

Envia notificação de pagamento

**Assinatura:**

```php
$communicationsapi->send_payment_notification($client_id, $amount_cents, $context = [])
```

**Parâmetros:** 3 parâmetro(s)


*... mais 1 métodos disponíveis*


### DPS_Communications_History

Gerenciamento de histórico: rastreamento e consulta de mensagens enviadas.

**Arquivo:** `plugins/desi-pet-shower-communications/includes/class-dps-communications-history.php`

**Métodos públicos:** 11


#### 🔧 get_instance()

**Método Estático** | **Desde:** 0.3.0

Gerenciador de histórico de comunicações Esta classe gerencia a tabela de histórico de comunicações, registrando todas as mensagens enviadas (WhatsApp, e-mail, SMS). / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe de histórico de comunicações / class DPS_Communications_History { /** Versão do banco de dados / const DB_VERSION = '1.0.0'; /** Option key para versão do banco / const DB_VERSION_OPTION = 'dps_comm_history_db_version'; /** Status possíveis de uma comunicação / const STATUS_PENDING   = 'pending'; const STATUS_SENT      = 'sent'; const STATUS_DELIVERED = 'delivered'; const STATUS_READ      = 'read'; const STATUS_FAILED    = 'failed'; const STATUS_RETRYING  = 'retrying'; /** Canais de comunicação / const CHANNEL_WHATSAPP = 'whatsapp'; const CHANNEL_EMAIL    = 'email'; const CHANNEL_SMS      = 'sms'; /** Instância singleton / private static $instance = null; /** Obtém instância singleton

**Assinatura:**

```php
DPS_Communications_History::get_instance()
```

**Retorno:** `DPS_Communications_History`

---


#### 🔧 get_table_name()

**Método Estático**

Construtor / private function __construct() { // Verifica e cria tabela se necessário add_action( 'plugins_loaded', [ $this, 'maybe_create_table' ], 5 ); } /** Retorna o nome da tabela de histórico

**Assinatura:**

```php
DPS_Communications_History::get_table_name()
```

**Retorno:** `string`

---


#### 🔧 table_exists()

**Método Estático**

Verifica se a tabela existe

**Assinatura:**

```php
DPS_Communications_History::table_exists()
```

**Retorno:** `bool`

---


#### 🎯 maybe_create_table()

**Método de Instância**

Cria ou atualiza a tabela de histórico

**Assinatura:**

```php
$communicationshistory->maybe_create_table()
```

---


#### 🎯 log_communication()

**Método de Instância**

Registra uma nova comunicação no histórico

**Assinatura:**

```php
$communicationshistory->log_communication($channel, $recipient, $message, $context = [])
```

**Parâmetros:** 4 parâmetro(s)

---


#### 🎯 update_status()

**Método de Instância**

Atualiza o status de uma comunicação

**Assinatura:**

```php
$communicationshistory->update_status($history_id, $status, $extra_data = [])
```

**Parâmetros:** 3 parâmetro(s)


*... mais 5 métodos disponíveis*


### DPS_Communications_Retry

Sistema de retry automático para mensagens que falharam.

**Arquivo:** `plugins/desi-pet-shower-communications/includes/class-dps-communications-retry.php`

**Métodos públicos:** 5


#### 🔧 get_instance()

**Método Estático** | **Desde:** 0.3.0

Gerenciador de retry com exponential backoff Esta classe implementa lógica de retry com exponential backoff para falhas de envio de comunicações. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe de retry com exponential backoff / class DPS_Communications_Retry { /** Máximo de tentativas de retry / const MAX_RETRIES = 5; /** Base do backoff em segundos / const BACKOFF_BASE = 60; // 1 minuto /** Multiplicador do exponential backoff / const BACKOFF_MULTIPLIER = 2; /** Jitter máximo em segundos (para evitar thundering herd) / const JITTER_MAX = 30; /** Instância singleton / private static $instance = null; /** Obtém instância singleton

**Assinatura:**

```php
DPS_Communications_Retry::get_instance()
```

**Retorno:** `DPS_Communications_Retry`

---


#### 🎯 schedule_retry()

**Método de Instância**

Construtor / private function __construct() { // Registra o handler do cron de retry add_action( 'dps_comm_retry_send', [ $this, 'process_retry' ], 10, 1 ); // Cron de limpeza de retries expirados (diário) add_action( 'dps_comm_cleanup_expired_retries', [ $this, 'cleanup_expired_retries' ] ); // Agenda cron de limpeza se não existir if ( ! wp_next_scheduled( 'dps_comm_cleanup_expired_retries' ) ) { wp_schedule_event( time(), 'daily', 'dps_comm_cleanup_expired_retries' ); } } /** Agenda um retry para uma comunicação que falhou

**Assinatura:**

```php
$communicationsretry->schedule_retry($history_id, $channel, $recipient, $message, $context, $retry_count, $last_error = '')
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$history_id` | `int` | ID do registro no histórico |
| `$channel` | `string` | Canal (whatsapp, email, sms) |
| `$recipient` | `string` | Destinatário |
| `$message` | `string` | Mensagem |
| `$context` | `array` | Contexto adicional |
| `$retry_count` | `int` | Número atual de tentativas |
| `$last_error` | `string` | Último erro ocorrido |

**Retorno:** `bool                 True se agendado, false se excedeu limite`

---


#### 🎯 process_retry()

**Método de Instância**

Processa o retry de uma comunicação

**Assinatura:**

```php
$communicationsretry->process_retry($history_id)
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$history_id` | `int` | ID do registro no histórico |

---


#### 🎯 cleanup_expired_retries()

**Método de Instância**

Calcula o delay do backoff exponencial com jitter / private function calculate_backoff_delay( $retry_count ) { // Exponential backoff: base * multiplier^retry_count $delay = self::BACKOFF_BASE * pow( self::BACKOFF_MULTIPLIER, $retry_count ); // Adiciona jitter aleatório para evitar thundering herd $jitter = wp_rand( 0, self::JITTER_MAX ); $delay += $jitter; // Cap máximo de 1 hora return min( $delay, HOUR_IN_SECONDS ); } /** Marca uma comunicação como permanentemente falha / private function mark_as_permanently_failed( $history_id, $last_error ) { if ( class_exists( 'DPS_Communications_History' ) ) { $history = DPS_Communications_History::get_instance(); $history->update_status( $history_id, DPS_Communications_History::STATUS_FAILED, [ 'last_error' => sprintf( __( 'Falha permanente após %d tentativas. Último erro: %s', 'dps-communications-addon' ), self::MAX_RETRIES, $last_error ), ] ); } $this->safe_log( 'error', sprintf( 'Communications Retry: Falha permanente para ID %d após %d tentativas', $history_id, self::MAX_RETRIES ) ); // Dispara hook para notificar falha permanente do_action( 'dps_comm_permanent_failure', $history_id, $last_error ); } /** Limpa retries expirados (transients órfãos)

**Assinatura:**

```php
$communicationsretry->cleanup_expired_retries()
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$retry_count` | `int` | Número atual de tentativas |
| `$history_id` | `int` | ID do registro |
| `$last_error` | `string` | Último erro |

**Retorno:** `int Delay em segundos`

---


#### 🎯 get_stats()

**Método de Instância**

Obtém estatísticas de retries

**Assinatura:**

```php
$communicationsretry->get_stats()
```


### DPS_Communications_Webhook

Webhooks para receber confirmações de status de mensagens.

**Arquivo:** `plugins/desi-pet-shower-communications/includes/class-dps-communications-webhook.php`

**Métodos públicos:** 10


#### 🔧 get_instance()

**Método Estático** | **Desde:** 0.3.0

Gerenciador de webhooks de status de entrega Esta classe gerencia webhooks recebidos de gateways de comunicação para atualizar o status de entrega das mensagens. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe de webhooks de comunicações / class DPS_Communications_Webhook { /** Namespace da REST API / const REST_NAMESPACE = 'dps-communications/v1'; /** Secret para validação de webhooks / const WEBHOOK_SECRET_OPTION = 'dps_comm_webhook_secret'; /** Instância singleton / private static $instance = null; /** Obtém instância singleton

**Assinatura:**

```php
DPS_Communications_Webhook::get_instance()
```

**Retorno:** `DPS_Communications_Webhook`

---


#### 🎯 maybe_generate_secret()

**Método de Instância**

Construtor / private function __construct() { // Registra endpoints REST add_action( 'rest_api_init', [ $this, 'register_routes' ] ); // Gera secret se não existir add_action( 'init', [ $this, 'maybe_generate_secret' ] ); } /** Gera secret de webhook se não existir

**Assinatura:**

```php
$communicationswebhook->maybe_generate_secret()
```

---


#### 🔧 get_secret()

**Método Estático**

Obtém o secret do webhook

**Assinatura:**

```php
DPS_Communications_Webhook::get_secret()
```

**Retorno:** `string`

---


#### 🔧 get_webhook_url()

**Método Estático**

Obtém a URL do webhook

**Assinatura:**

```php
DPS_Communications_Webhook::get_webhook_url($provider = 'generic')
```

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|----------|
| `$provider` | `string` | Provider do webhook (evolution, twilio, etc.) |

**Retorno:** `string`

---


#### 🎯 register_routes()

**Método de Instância**

Registra rotas REST

**Assinatura:**

```php
$communicationswebhook->register_routes()
```

---


#### 🎯 verify_webhook()

**Método de Instância**

Verifica autenticidade do webhook

**Assinatura:**

```php
$communicationswebhook->verify_webhook($request)
```

**Parâmetros:** 1 parâmetro(s)


*... mais 4 métodos disponíveis*


#### 💡 Exemplo de Uso: Envio de WhatsApp

```php
$api = DPS_Communications_API::get_instance();

// Enviar mensagem simples
$success = $api->send_whatsapp(
    '11987654321',
    'Olá! Seu agendamento foi confirmado para amanhã às 10h.',
    ['appointment_id' => 123, 'type' => 'confirmation']
);

if (!$success) {
    $error = $api->get_last_error();
    error_log("Falha ao enviar WhatsApp: $error");
}

// Enviar email
$api->send_email(
    'cliente@example.com',
    'Confirmação de Agendamento',
    'Seu banho está agendado!',
    ['client_id' => 456]
);
```


## 💰 FINANCE ADD-ON

### Overview

Sistema financeiro centralizado. **Todos os add-ons DEVEM usar esta API** para criar, atualizar ou consultar transações financeiras, em vez de manipular a tabela `dps_transacoes` diretamente.


### DPS_Finance_API

API principal: criação/atualização de cobranças, marcação de pagamentos, consultas.

**Arquivo:** `plugins/desi-pet-shower-finance/includes/class-dps-finance-api.php`

**Métodos públicos:** 8


#### 🔧 create_or_update_charge()

**Método Estático** | **Desde:** 1.1.0

API Financeira Centralizada do DPS Fornece interface pública para operações financeiras, centralizando toda a lógica de criação, atualização e consulta de cobranças/transações. Outros add-ons (como Agenda) devem usar esta API em vez de manipular a tabela dps_transacoes diretamente. / // Impede acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe estática que fornece API pública para operações financeiras. TODOS os add-ons que precisam criar, atualizar ou consultar transações financeiras devem usar os métodos desta classe em vez de fazer queries diretas na tabela dps_transacoes. / class DPS_Finance_API { /** Verifica se uma tabela existe no banco de dados atual. / private static function table_exists( $table_name ) { global $wpdb; $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ); return $table_exists === $table_name; } /** Criar ou atualizar cobrança vinculada a um agendamento. Este é o método principal usado pela Agenda e outros add-ons para registrar cobranças. Se já existir transação para o agendamento, atualiza; caso contrário, cria nova.

**Assinatura:**

```php
DPS_Finance_API::create_or_update_charge($data)
```

**Parâmetros:**

- `$table_name` (`string`): Nome completo da tabela (com prefixo).
- `$data` (`array`): Dados da cobrança.

**Retorno:** `bool True se a tabela existe, false caso contrário.`

---


#### 🔧 mark_as_paid()

**Método Estático** | **Desde:** 1.1.0

Disparado após atualizar uma cobrança existente. / do_action( 'dps_finance_charge_updated', $existing_id, $appointment_id ); return $existing_id; } else { // Cria nova transação $wpdb->insert( $table, $trans_data, [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s' ] ); $new_id = $wpdb->insert_id; /** Disparado após criar uma nova cobrança. / do_action( 'dps_finance_charge_created', $new_id, $appointment_id ); return $new_id; } } /** Marcar cobrança como paga. Atualiza status da transação para 'pago' e dispara hook dps_finance_booking_paid para que outros add-ons (como Loyalty) possam reagir ao pagamento.

**Assinatura:**

```php
DPS_Finance_API::mark_as_paid($charge_id, $options = [])
```

**Parâmetros:**

- `$existing_id` (`int`): ID da transação atualizada.
- `$appointment_id` (`int`): ID do agendamento vinculado.
- `$new_id` (`int`): ID da transação criada.
- `$appointment_id` (`int`): ID do agendamento vinculado.
- `$charge_id` (`int`): ID da transação.

**Retorno:** `true|WP_Error True em caso de sucesso, WP_Error em caso de erro.`

---


#### 🔧 mark_as_pending()

**Método Estático** | **Desde:** 1.0.0

Disparado quando uma cobrança é marcada como paga. Hook mantido para compatibilidade com Loyalty e outros add-ons. / do_action( 'dps_finance_booking_paid', $charge_id, (int) $transaction->cliente_id, (int) round( (float) $transaction->valor * 100 ) ); return true; } /** Marcar cobrança como pendente. Útil para reabrir cobranças marcadas como pagas por engano.

**Assinatura:**

```php
DPS_Finance_API::mark_as_pending($charge_id)
```

**Parâmetros:**

- `$charge_id` (`int`): ID da transação.
- `$client_id` (`int`): ID do cliente.
- `$value_cents` (`int`): Valor em centavos.
- `$charge_id` (`int`): ID da transação.

**Retorno:** `true|WP_Error True em caso de sucesso, WP_Error em caso de erro.`

---


#### 🔧 mark_as_cancelled()

**Método Estático** | **Desde:** 1.1.0

Marcar cobrança como cancelada.

**Assinatura:**

```php
DPS_Finance_API::mark_as_cancelled($charge_id, $reason = '')
```

**Parâmetros:**

- `$charge_id` (`int`): ID da transação.
- `$reason` (`string`): Motivo do cancelamento (opcional).

**Retorno:** `true|WP_Error True em caso de sucesso, WP_Error em caso de erro.`

---


#### 🔧 get_charge()

**Método Estático** | **Desde:** 1.1.0

Buscar dados de uma cobrança.

**Assinatura:**

```php
DPS_Finance_API::get_charge($charge_id)
```

**Retorno:** `object|null Objeto com dados da transação ou null se não encontrada.`

---


#### 🔧 get_charges_by_appointment()

**Método Estático** | **Desde:** 1.1.0

Buscar todas as cobranças de um agendamento.

**Assinatura:**

```php
DPS_Finance_API::get_charges_by_appointment($appointment_id)
```

**Retorno:** `array Array de objetos (mesma estrutura de get_charge()).`

---


#### 🔧 delete_charges_by_appointment()

**Método Estático** | **Desde:** 1.1.0

Remover todas as cobranças de um agendamento. Usado quando agendamento é excluído. Remove também parcelas vinculadas.

**Assinatura:**

```php
DPS_Finance_API::delete_charges_by_appointment($appointment_id)
```

**Retorno:** `int Número de transações removidas.`


*... mais 1 métodos*


### DPS_Finance_Audit

Auditoria: rastreamento de alterações em transações financeiras.

**Arquivo:** `plugins/desi-pet-shower-finance/includes/class-dps-finance-audit.php`

**Métodos públicos:** 6


#### 🔧 init()

**Método Estático** | **Desde:** 1.6.0

Gerencia auditoria de alterações financeiras. FASE 4 - F4.4: Auditoria de Alterações Financeiras / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe responsável por registrar e exibir logs de auditoria financeira. / class DPS_Finance_Audit { /** Nome da tabela de auditoria. / const TABLE_NAME = 'dps_finance_audit_log'; /** Inicializa a classe de auditoria.

**Assinatura:**

```php
DPS_Finance_Audit::init()
```

---


#### 🔧 log_event()

**Método Estático** | **Desde:** 1.6.0

Registra evento de auditoria.

**Assinatura:**

```php
DPS_Finance_Audit::log_event($trans_id, $action, $data = [])
```

**Parâmetros:**

- `$trans_id` (`int`): ID da transação.
- `$action` (`string`): Tipo de ação (status_change, value_change, partial_add, manual_create).
- `$data` (`array`): Dados da alteração (from_status, to_status, from_value, to_value, meta_info).

**Retorno:** `int|false ID do registro de auditoria ou false em caso de erro.`

---


#### 🔧 get_logs()

**Método Estático** | **Desde:** 1.6.0

Obtém IP do cliente de forma segura. / private static function get_client_ip() { if ( class_exists( 'DPS_IP_Helper' ) ) { return DPS_IP_Helper::get_ip(); } // Fallback para retrocompatibilidade $ip = ''; // REMOTE_ADDR é a fonte mais confiável (não pode ser falsificado pelo cliente) if ( isset( $_SERVER['REMOTE_ADDR'] ) ) { $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); } // Valida REMOTE_ADDR - se inválido, tenta fallback if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) { // HTTP_X_FORWARDED_FOR pode ser falsificado, então usamos apenas como fallback // Nota: X_FORWARDED_FOR pode conter múltiplos IPs - usamos apenas o primeiro if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ); // Pega apenas o primeiro IP se houver múltiplos (separados por vírgula) $forwarded_parts = explode( ',', $forwarded ); $ip = trim( $forwarded_parts[0] ); } } // Validação final do IP if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; } return 'unknown'; } /** Busca logs de auditoria.

**Assinatura:**

```php
DPS_Finance_Audit::get_logs($args = [])
```

**Parâmetros:**

- `$args` (`array`): Argumentos de busca (trans_id, date_from, date_to, limit, offset).

**Retorno:** `string IP address ou 'unknown'.`

---


#### 🔧 count_logs()

**Método Estático** | **Desde:** 1.6.0

Conta total de logs de auditoria.

**Assinatura:**

```php
DPS_Finance_Audit::count_logs($args = [])
```

**Parâmetros:**

- `$args` (`array`): Argumentos de filtro (trans_id, date_from, date_to).

**Retorno:** `int Total de registros.`

---


#### 🔧 register_audit_page()

**Método Estático** | **Desde:** 1.6.0

Registra página de auditoria no menu admin.

**Assinatura:**

```php
DPS_Finance_Audit::register_audit_page()
```

---


#### 🔧 render_audit_page()

**Método Estático** | **Desde:** 1.6.0

Renderiza página de auditoria.

**Assinatura:**

```php
DPS_Finance_Audit::render_audit_page()
```


### DPS_Finance_Reminders

Sistema de lembretes automáticos para pagamentos pendentes.

**Arquivo:** `plugins/desi-pet-shower-finance/includes/class-dps-finance-reminders.php`

**Métodos públicos:** 6


#### 🔧 init()

**Método Estático** | **Desde:** 1.6.0

Gerencia lembretes automáticos de pagamento. FASE 4 - F4.2: Lembretes Automáticos de Pagamento / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe responsável por gerenciar lembretes automáticos de cobrança. / class DPS_Finance_Reminders { /** Nome do evento cron. / const CRON_HOOK = 'dps_finance_process_payment_reminders'; /** Inicializa a classe de lembretes.

**Assinatura:**

```php
DPS_Finance_Reminders::init()
```

---


#### 🔧 clear_scheduled_hook()

**Método Estático** | **Desde:** 1.6.0

Limpa evento cron agendado.

**Assinatura:**

```php
DPS_Finance_Reminders::clear_scheduled_hook()
```

---


#### 🔧 process_reminders()

**Método Estático** | **Desde:** 1.6.0

Processa lembretes de pagamento (executado diariamente via cron).

**Assinatura:**

```php
DPS_Finance_Reminders::process_reminders()
```

---


#### 🔧 is_enabled()

**Método Estático** | **Desde:** 1.6.0

Envia lembretes ANTES do vencimento. / private static function send_before_reminders( $target_date ) { global $wpdb; $table = $wpdb->prefix . 'dps_transacoes'; // Busca transações em aberto que vencem na data alvo $transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE tipo = 'receita' AND status = 'em_aberto' AND data = %s", $target_date ) ); foreach ( $transactions as $trans ) { // Verifica se já enviou lembrete antes para esta transação $sent_at = get_transient( 'dps_reminder_before_' . $trans->id ); if ( $sent_at ) { continue; // Já foi enviado } // Envia lembrete $result = self::send_reminder( $trans, 'before' ); if ( $result ) { // Marca como enviado (expira em 7 dias) set_transient( 'dps_reminder_before_' . $trans->id, current_time( 'mysql' ), 7 * DAY_IN_SECONDS ); } } } /** Envia lembretes APÓS vencimento. / private static function send_after_reminders( $target_date ) { global $wpdb; $table = $wpdb->prefix . 'dps_transacoes'; // Busca transações em aberto que venceram na data alvo $transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE tipo = 'receita' AND status = 'em_aberto' AND data = %s", $target_date ) ); foreach ( $transactions as $trans ) { // Verifica se já enviou lembrete depois para esta transação $sent_at = get_transient( 'dps_reminder_after_' . $trans->id ); if ( $sent_at ) { continue; // Já foi enviado } // Envia lembrete $result = self::send_reminder( $trans, 'after' ); if ( $result ) { // Marca como enviado (expira em 7 dias) set_transient( 'dps_reminder_after_' . $trans->id, current_time( 'mysql' ), 7 * DAY_IN_SECONDS ); } } } /** Envia lembrete para uma transação. / private static function send_reminder( $trans, $type ) { // Busca dados do cliente if ( ! $trans->cliente_id ) { return false; } $client = get_post( $trans->cliente_id ); if ( ! $client ) { return false; } $client_name = $client->post_title; // Busca telefone do cliente (meta) $phone = get_post_meta( $trans->cliente_id, 'client_phone', true ); if ( ! $phone ) { return false; } // Busca dados do agendamento para obter pet $pet_name = ''; if ( $trans->agendamento_id ) { $pet_id = get_post_meta( $trans->agendamento_id, 'appointment_pet_id', true ); if ( $pet_id ) { $pet_post = get_post( $pet_id ); $pet_name = $pet_post ? $pet_post->post_title : ''; } } // Formata valor if ( class_exists( 'DPS_Money_Helper' ) ) { $valor_formatted = 'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans->valor * 100 ) ); } else { $valor_formatted = 'R$ ' . number_format( (float) $trans->valor, 2, ',', '.' ); } // Busca link de pagamento (se existir) $payment_link = ''; if ( $trans->agendamento_id ) { $payment_link = get_post_meta( $trans->agendamento_id, 'dps_payment_link', true ); } // Monta mensagem $message = self::get_reminder_message( $type, [ 'cliente' => $client_name, 'pet'     => $pet_name, 'data'    => date_i18n( 'd/m/Y', strtotime( $trans->data ) ), 'valor'   => $valor_formatted, 'link'    => $payment_link, ] ); // Envia via WhatsApp (reutiliza sistema existente se disponível) $sent = self::send_whatsapp_message( $phone, $message ); // Log if ( $sent ) { error_log( sprintf( 'DPS Finance Reminders: Lembrete %s enviado para trans #%d (cliente: %s)', $type, $trans->id, $client_name ) ); } else { error_log( sprintf( 'DPS Finance Reminders: Falha ao enviar lembrete %s para trans #%d', $type, $trans->id ) ); } return $sent; } /** Retorna mensagem de lembrete. / private static function get_reminder_message( $type, $data ) { $templates = [ 'before' => get_option( 'dps_finance_reminder_message_before', 'Olá {cliente}, este é um lembrete amigável: o pagamento de R$ {valor} vence amanhã. Para sua comodidade, você pode pagar via PIX ou utilizar o link: {link}. Obrigado!' ), 'after' => get_option( 'dps_finance_reminder_message_after', 'Olá {cliente}, o pagamento de R$ {valor} está vencido. Para regularizar, você pode pagar via PIX ou utilizar o link: {link}. Agradecemos a atenção!' ), ]; $template = isset( $templates[ $type ] ) ? $templates[ $type ] : $templates['after']; // Substitui placeholders if ( class_exists( 'DPS_Finance_Settings' ) ) { return DPS_Finance_Settings::format_message( $template, $data ); } // Fallback manual $placeholders = [ '{cliente}' => isset( $data['cliente'] ) ? $data['cliente'] : '', '{pet}'     => isset( $data['pet'] ) ? $data['pet'] : '', '{data}'    => isset( $data['data'] ) ? $data['data'] : '', '{valor}'   => isset( $data['valor'] ) ? $data['valor'] : '', '{link}'    => isset( $data['link'] ) ? $data['link'] : '', ]; return str_replace( array_keys( $placeholders ), array_values( $placeholders ), (string) $template ); } /** Envia mensagem via WhatsApp. / private static function send_whatsapp_message( $phone, $message ) { // Remove formatação do telefone $phone_clean = preg_replace( '/[^0-9]/', '', $phone ); // Se houver integração com Communications Add-on, usar aqui // Por enquanto, simula envio (log apenas) // Em produção, poderia: // - Chamar API do Communications Add-on // - Enviar via API do WhatsApp Business // - Adicionar à fila de mensagens // Simula sucesso return true; } /** Verifica se lembretes estão habilitados.

**Assinatura:**

```php
DPS_Finance_Reminders::is_enabled()
```

**Parâmetros:**

- `$target_date` (`string`): Data alvo (Y-m-d).
- `$target_date` (`string`): Data alvo (Y-m-d).
- `$trans` (`object`): Objeto da transação.
- `$type` (`string`): Tipo de lembrete ('before' ou 'after').
- `$type` (`string`): Tipo de lembrete ('before' ou 'after').

**Retorno:** `bool True se enviado com sucesso.`

---


#### 🔧 render_settings_section()

**Método Estático** | **Desde:** 1.6.0

Renderiza seção de configurações de lembretes.

**Assinatura:**

```php
DPS_Finance_Reminders::render_settings_section()
```

---


#### 🔧 save_settings()

**Método Estático** | **Desde:** 1.6.0

Salva configurações de lembretes.

**Assinatura:**

```php
DPS_Finance_Reminders::save_settings($data)
```


### DPS_Finance_Revenue_Query

Consultas otimizadas de receita e métricas financeiras.

**Arquivo:** `plugins/desi-pet-shower-finance/includes/class-dps-finance-revenue-query.php`

**Métodos públicos:** 1


#### 🔧 sum_by_period()

**Método Estático**

Helper para consultar faturamento a partir de metas históricas. / class DPS_Finance_Revenue_Query { /** Soma o meta `_dps_total_at_booking` para agendamentos publicados dentro do intervalo informado.

**Assinatura:**

```php
DPS_Finance_Revenue_Query::sum_by_period($start_date, $end_date, $db = null)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).
- `$db` (`object|null`): Objeto wpdb customizado para testes.

**Retorno:** `int Total em centavos.`


#### 💡 Exemplo de Uso: Finance API

```php
// Criar/atualizar cobrança de agendamento
$charge_data = [
    'appointment_id' => 123,
    'client_id' => 456,
    'services' => [10, 11],  // IDs dos serviços
    'pet_id' => 789,
    'value_cents' => 8500,  // R$ 85,00
    'status' => 'pending',
    'date' => '2024-12-15',
];

$transaction_id = DPS_Finance_API::create_or_update_charge($charge_data);

if (is_wp_error($transaction_id)) {
    error_log('Erro ao criar cobrança: ' . $transaction_id->get_error_message());
} else {
    // Marcar como pago
    DPS_Finance_API::mark_as_paid($transaction_id);
}

// Consultar receita
$query = new DPS_Finance_Revenue_Query();
$revenue = $query->get_total_revenue('2024-12-01', '2024-12-31');
```


## 🌐 CLIENT PORTAL ADD-ON

### Overview

Portal do cliente com autenticação via token, gerenciamento de sessão e repositórios de dados.


### DPS_Portal_Session_Manager

Gerenciamento de sessões autenticadas de clientes.

**Arquivo:** `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-session-manager.php`

**Métodos públicos:** 9


#### 🔧 get_instance()

**Método Estático** | **Desde:** 2.0.0

Gerenciador de sessões do Portal do Cliente Esta classe gerencia a autenticação e sessão dos clientes no portal, independente do sistema de usuários do WordPress. / if ( ! defined( 'ABSPATH' ) ) { exit; } if ( ! class_exists( 'DPS_Portal_Session_Manager' ) ) : /** Classe responsável pelo gerenciamento de sessões do portal Versão 2.4.0: Migrado de $_SESSION para transients + cookies para compatibilidade com ambientes multi-servidor e cloud. / final class DPS_Portal_Session_Manager implements DPS_Portal_Session_Manager_Interface { /** Nome do cookie de sessão / const COOKIE_NAME = 'dps_portal_session'; /** Prefixo para transients de sessão / const TRANSIENT_PREFIX = 'dps_session_'; /** Tempo de vida da sessão em segundos (24 horas) / const SESSION_LIFETIME = 86400; /** Única instância da classe / private static $instance = null; /** Recupera a instância única (singleton)

**Assinatura:**

```php
DPS_Portal_Session_Manager::get_instance()
```

**Retorno:** `DPS_Portal_Session_Manager`

---


#### 🎯 authenticate_client()

**Método de Instância**

Construtor privado para singleton / private function __construct() { // Valida sessão em cada requisição // IMPORTANTE: Prioridade 10 para executar APÓS handle_token_authentication (prioridade 5) // Isso garante que o cookie esteja definido antes da validação // // NOTA: Se o hook 'init' já executou, chamamos validate_session() diretamente // para garantir validação de sessão mesmo em inicialização tardia. if ( did_action( 'init' ) ) { $this->validate_session(); } else { add_action( 'init', [ $this, 'validate_session' ], 10 ); } } /** Autentica um cliente no portal usando transients + cookies

**Assinatura:**

```php
$portalsess->authenticate_client($client_id)
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente

**Retorno:** `bool True se autenticado com sucesso, false se erro`

---


#### 🎯 get_authenticated_client_id()

**Método de Instância**

Retorna o ID do cliente autenticado

**Assinatura:**

```php
$portalsess->get_authenticated_client_id()
```

**Retorno:** `int ID do cliente ou 0 se não autenticado`

---


#### 🎯 is_authenticated()

**Método de Instância**

Verifica se há um cliente autenticado

**Assinatura:**

```php
$portalsess->is_authenticated()
```

**Retorno:** `bool True se autenticado, false caso contrário`

---


#### 🎯 validate_session()

**Método de Instância**

Valida a sessão atual Remove sessões expiradas ou inválidas

**Assinatura:**

```php
$portalsess->validate_session()
```

---


#### 🎯 logout()

**Método de Instância**

Faz logout do cliente

**Assinatura:**

```php
$portalsess->logout()
```


### DPS_Portal_Token_Manager

Geração e validação de tokens de acesso único.

**Arquivo:** `plugins/desi-pet-shower-client-portal/includes/class-dps-portal-token-manager.php`

**Métodos públicos:** 13


#### 🔧 get_instance()

**Método Estático** | **Desde:** 2.0.0

Gerenciador de tokens de acesso ao Portal do Cliente Esta classe gerencia a criação, validação, revogação e limpeza de tokens de autenticação para o Portal do Cliente. Tokens são magic links que permitem acesso sem senha. / if ( ! defined( 'ABSPATH' ) ) { exit; } if ( ! class_exists( 'DPS_Portal_Token_Manager' ) ) : /** Classe responsável pelo gerenciamento de tokens do portal / final class DPS_Portal_Token_Manager implements DPS_Portal_Token_Manager_Interface { /** Nome da tabela de tokens (sem prefixo) / const TABLE_NAME = 'dps_portal_tokens'; /** Versão do schema da tabela / const DB_VERSION = '1.0.0'; /** Tempo de expiração padrão em minutos / const DEFAULT_EXPIRATION_MINUTES = 30; /** Tempo de expiração para tokens permanentes em minutos (10 anos) / const PERMANENT_EXPIRATION_MINUTES = 60 * 24 * 365 * 10; /** Tempo de expiração para tokens de atualização de perfil em minutos (7 dias) / const PROFILE_UPDATE_EXPIRATION_MINUTES = 60 * 24 * 7; /** Tamanho máximo do user agent armazenado no log de acesso / const MAX_USER_AGENT_LENGTH = 255; /** Única instância da classe / private static $instance = null; /** Recupera a instância única (singleton)

**Assinatura:**

```php
DPS_Portal_Token_Manager::get_instance()
```

**Retorno:** `DPS_Portal_Token_Manager`

---


#### 🎯 maybe_create_table()

**Método de Instância**

Construtor privado para singleton / private function __construct() { // Registra hook para criar/atualizar tabela add_action( 'plugins_loaded', [ $this, 'maybe_create_table' ] ); // Registra cron job para limpeza de tokens expirados add_action( 'dps_portal_cleanup_tokens', [ $this, 'cleanup_expired_tokens' ] ); // Agenda cron job se não estiver agendado if ( ! wp_next_scheduled( 'dps_portal_cleanup_tokens' ) ) { wp_schedule_event( time(), 'hourly', 'dps_portal_cleanup_tokens' ); } } /** Retorna o nome completo da tabela com prefixo do WordPress / private function get_table_name() { global $wpdb; return $wpdb->prefix . self::TABLE_NAME; } /** Cria ou atualiza a tabela de tokens se necessário

**Assinatura:**

```php
$portaltoke->maybe_create_table()
```

**Retorno:** `string`

---


#### 🎯 generate_token()

**Método de Instância**

Cria a tabela de tokens / private function create_table() { global $wpdb; $table_name      = $this->get_table_name(); $charset_collate = $wpdb->get_charset_collate(); $sql = "CREATE TABLE {$table_name} ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, client_id bigint(20) unsigned NOT NULL, token_hash varchar(255) NOT NULL, type varchar(50) NOT NULL DEFAULT 'login', created_at datetime NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL, revoked_at datetime DEFAULT NULL, ip_created varchar(45) DEFAULT NULL, user_agent text DEFAULT NULL, PRIMARY KEY  (id), KEY client_id (client_id), KEY token_hash (token_hash), KEY expires_at (expires_at), KEY type (type) ) {$charset_collate};"; require_once ABSPATH . 'wp-admin/includes/upgrade.php'; dbDelta( $sql ); } /** Gera um novo token de acesso para um cliente

**Assinatura:**

```php
$portaltoke->generate_token($client_id, $type = 'login', $expiration_minutes = null)
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente
- `$type` (`string`): Tipo do token ('login' ou 'first_access')
- `$expiration_minutes` (`int`): Minutos até expiração (padrão: 30)

**Retorno:** `string|false Token em texto plano ou false em caso de erro`

---


#### 🎯 validate_token()

**Método de Instância**

Valida um token e retorna os dados se válido Implementa rate limiting para prevenir brute force: - 5 tentativas por hora por IP - Cache negativo de tokens inválidos (5 min) - Logging de tentativas inválidas

**Assinatura:**

```php
$portaltoke->validate_token($token_plain)
```

**Retorno:** `array|false Dados do token se válido, false se inválido`

---


#### 🎯 mark_as_used()

**Método de Instância**

Incrementa o contador de rate limiting / private function increment_rate_limit( $key, $current_attempts ) { set_transient( $key, $current_attempts + 1, HOUR_IN_SECONDS ); } /** Registra tentativa inválida de acesso com token / private function log_invalid_attempt( $token_plain, $ip, $reason ) { $log_data = [ 'ip'           => $ip, 'token_prefix' => substr( $token_plain, 0, 8 ) . '...', 'reason'   ...

**Assinatura:**

```php
$portaltoke->mark_as_used($token_id)
```

**Retorno:** `string IP do cliente ou string vazia`

---


#### 🎯 revoke_tokens()

**Método de Instância**

Revoga todos os tokens ativos de um cliente

**Assinatura:**

```php
$portaltoke->revoke_tokens($client_id)
```

**Retorno:** `int|false Número de tokens revogados ou false em caso de erro`


### DPS_Client_Repository

Repositório: consulta otimizada de dados de clientes.

**Arquivo:** `plugins/desi-pet-shower-client-portal/includes/client-portal/repositories/class-dps-client-repository.php`

**Métodos públicos:** 5


#### 🔧 get_instance()

**Método Estático** | **Desde:** 3.0.0

Repositório para operações de dados relacionadas a clientes. Centraliza todas as consultas de dados de clientes (CPT dps_cliente), seguindo o padrão Repository para isolar lógica de acesso a dados. / class DPS_Client_Repository { /** Instância única da classe (singleton). / private static $instance = null; /** Recupera a instância única (singleton).

**Assinatura:**

```php
DPS_Client_Repository::get_instance()
```

**Retorno:** `DPS_Client_Repository`

---


#### 🎯 get_client_by_id()

**Método de Instância**

Construtor privado (singleton). / private function __construct() { // Nada a inicializar por enquanto } /** Busca um cliente por ID.

**Assinatura:**

```php
$clientrepo->get_client_by_id($client_id)
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente.

**Retorno:** `WP_Post|null Objeto do cliente ou null se não encontrado.`

---


#### 🎯 get_client_by_email()

**Método de Instância**

Busca um cliente por email.

**Assinatura:**

```php
$clientrepo->get_client_by_email($email)
```

**Parâmetros:**

- `$email` (`string`): Email do cliente.

**Retorno:** `WP_Post|null Objeto do cliente ou null se não encontrado.`

---


#### 🎯 get_client_by_phone()

**Método de Instância**

Busca um cliente por telefone.

**Assinatura:**

```php
$clientrepo->get_client_by_phone($phone)
```

**Retorno:** `WP_Post|null Objeto do cliente ou null se não encontrado.`

---


#### 🎯 get_clients()

**Método de Instância**

Busca todos os clientes com paginação.

**Assinatura:**

```php
$clientrepo->get_clients($args = [])
```

**Retorno:** `array Array de posts de clientes.`


### DPS_Pet_Repository

Repositório: consulta de pets vinculados a clientes.

**Arquivo:** `plugins/desi-pet-shower-client-portal/includes/client-portal/repositories/class-dps-pet-repository.php`

**Métodos públicos:** 4


#### 🔧 get_instance()

**Método Estático** | **Desde:** 3.0.0

Repositório para operações de dados relacionadas a pets. Centraliza todas as consultas de dados de pets (CPT dps_pet), seguindo o padrão Repository para isolar lógica de acesso a dados. / class DPS_Pet_Repository { /** Instância única da classe (singleton). / private static $instance = null; /** Recupera a instância única (singleton).

**Assinatura:**

```php
DPS_Pet_Repository::get_instance()
```

**Retorno:** `DPS_Pet_Repository`

---


#### 🎯 get_pet()

**Método de Instância**

Construtor privado (singleton). / private function __construct() { // Nada a inicializar por enquanto } /** Busca um pet por ID.

**Assinatura:**

```php
$petreposit->get_pet($pet_id)
```

**Parâmetros:**

- `$pet_id` (`int`): ID do pet.

**Retorno:** `WP_Post|null Objeto do pet ou null se não encontrado.`

---


#### 🎯 get_pets_by_client()

**Método de Instância**

Busca todos os pets de um cliente.

**Assinatura:**

```php
$petreposit->get_pets_by_client($client_id)
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente.

**Retorno:** `array Array de posts de pets.`

---


#### 🎯 pet_belongs_to_client()

**Método de Instância**

Verifica se um pet pertence a um cliente.

**Assinatura:**

```php
$petreposit->pet_belongs_to_client($pet_id, $client_id)
```

**Retorno:** `bool True se o pet pertence ao cliente.`


## 🔔 PUSH ADD-ON


### DPS_Push_API

API de push notifications usando Web Push Protocol (VAPID).

**Arquivo:** `plugins/desi-pet-shower-push/includes/class-dps-push-api.php`

**Métodos públicos:** 3


#### 🔧 generate_vapid_keys()

**Método Estático** | **Desde:** 1.0.0

API de Push Notifications para o DPS. Implementa Web Push API usando biblioteca PHP nativa. / // Impede acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe API para Push Notifications. / class DPS_Push_API { /** Gera par de chaves VAPID.

**Assinatura:**

```php
DPS_Push_API::generate_vapid_keys()
```

**Retorno:** `array Chaves public e private em base64url.`


#### 🔧 send_to_user()

**Método Estático** | **Desde:** 1.0.0

Envia notificação para um usuário específico.

**Assinatura:**

```php
DPS_Push_API::send_to_user($user_id, $payload)
```

**Parâmetros:**

- `$user_id` (`int`): ID do usuário.
- `$payload` (`array`): Dados da notificação (title, body, icon, etc.).

**Retorno:** `array Resultado com success e failed counts.`


#### 🔧 send_to_all_admins()

**Método Estático** | **Desde:** 1.0.0

Envia notificação para todos os administradores.

**Assinatura:**

```php
DPS_Push_API::send_to_all_admins($payload, $exclude_ids = [])
```

**Parâmetros:**

- `$payload` (`array`): Dados da notificação.
- `$exclude_ids` (`array`): IDs de usuários a excluir.

**Retorno:** `array Resultado consolidado.`


## 🤖 AI ADD-ON

### AI Logging Functions

Funções globais para logging condicional (apenas quando WP_DEBUG está habilitado).

**Funções disponíveis:**

#### 📦 dps_ai_log_debug()

Logger condicional para o AI Add-on. Registra logs apenas quando WP_DEBUG está habilitado ou quando a opção de debug do plugin está ativada. Em produção, registra apenas erros críticos. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Registra uma mensagem de log condicionalmente. Logs são registrados 

**Assinatura:** `dps_ai_log_debug($message, $context = [])`

**Parâmetros:**
- @param string $message Mensagem a ser registrada.
- @param string $level   Nível de log: 'debug', 'info', 'warning', 'error'. Padrão: 'info'.
- @param array  $context Contexto adicional (opcional, para dados estruturados).

---

#### 📦 dps_ai_log_info()

Registra uma mensagem informativa. Útil para eventos normais do sistema que valem documentação. Não é registrado em produção (a menos que debug_logging esteja habilitado).

**Assinatura:** `dps_ai_log_info($message, $context = [])`

**Parâmetros:**
- @param string $message Mensagem informativa.
- @param array  $context Contexto adicional.

---

#### 📦 dps_ai_log_warning()

Registra uma mensagem de aviso. Indica situações anormais que não são necessariamente erros. Não é registrado em produção (a menos que debug_logging esteja habilitado).

**Assinatura:** `dps_ai_log_warning($message, $context = [])`

**Parâmetros:**
- @param string $message Mensagem de aviso.
- @param array  $context Contexto adicional.

---

#### 📦 dps_ai_log_error()

Registra uma mensagem de erro. Indica falhas críticas que requerem atenção. Sempre é registrado, mesmo em produção.

**Assinatura:** `dps_ai_log_error($message, $context = [])`

**Parâmetros:**
- @param string $message Mensagem de erro.
- @param array  $context Contexto adicional.

---


#### 💡 Exemplo de Uso: AI Logging

```php
// Log simples
dps_ai_log_info('Processamento de mensagem iniciado');

// Log com contexto
dps_ai_log_warning('Token expirado', ['client_id' => 123, 'token_age' => 3600]);

// Log de erro
dps_ai_log_error('Falha na API da OpenAI', ['error' => $exception->getMessage()]);

// Log de conversação
dps_ai_log_conversation(456, 'user', 'Qual o horário disponível?');
dps_ai_log_conversation(456, 'assistant', 'Temos vagas às 10h e 14h');
```


### DPS_AI_Assistant

Assistente principal: processamento de mensagens e geração de respostas.

**Arquivo:** `plugins/desi-pet-shower-ai/includes/class-dps-ai-assistant.php`

**Métodos públicos:** 4


#### 🔧 answer_portal_question()

**Método Estático**

Assistente de IA do DPS. Este arquivo contém a classe responsável por todas as regras de negócio da IA, incluindo o system prompt restritivo e a montagem de contexto. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe Assistente de IA. Concentra TODAS as regras de comportamento da IA, incluindo: - System prompt restritivo focado em Banho e Tosa - Montagem de contexto do cliente/pet - Filtro preventivo de perguntas fora do contexto - Integração com DPS_AI_Client / class DPS_AI_Assistant { /** Palavras-chave relacionadas ao contexto permitido. / const CONTEXT_KEYWORDS = [ 'pet', 'pets', 'cachorro', 'cao', 'cão', 'cães', 'gato', 'gatos', 'banho', 'tosa', 'grooming', 'tosador', 'tosadora', 'agendamento', 'agendamentos', 'agenda', 'agendar', 'marcar', 'horario', 'horário', 'servico', 'serviço', 'servicos', 'serviços', 'pagamento', 'pagamentos', 'pagar', 'pendencia', 'pendência', 'pendências', 'cobranca', 'cobrança', 'portal', 'sistema', 'dps', 'desi', 'assinatura', 'assinaturas', 'plano', 'planos', 'mensalidade', 'fidelidade', 'pontos', 'recompensa', 'recompensas', 'vacina', 'vacinas', 'vacinacao', 'vacinação', 'historico', 'histórico', 'atendimento', 'atendimentos', 'cliente', 'cadastro', 'dados', 'telefone', 'email', 'endereco', 'endereço', 'raca', 'raça', 'porte', 'idade', 'peso', 'pelagem', 'higiene', 'limpeza', 'cuidado', 'cuidados', 'saude', 'saúde', ]; /** Tempo de expiração do cache de contexto em segundos (5 minutos). / const CONTEXT_CACHE_EXPIRATION = 300; /** Responde a uma pergunta feita pelo cliente no Portal. SEGURANÇA (Isolamento de Dados): - O $client_id é obtido via autenticação do portal (DPS_Client_Portal::get_current_client_id) - Os $pet_ids são buscados filtrando por pet_client_id = $client_id - O contexto é construído usando apenas dados do cliente autenticado - Agendamentos são filtrados por appointment_client_id no banco de dados - Transações são filtradas por cliente_id na tabela dps_transacoes - Pontos de fidelidade são filtrados por loyalty_client_id Isso garante que o assistente de IA não tem acesso a dados de outros clientes.

**Assinatura:**

```php
DPS_AI_Assistant::answer_portal_question($client_id, array $pet_ids, $user_question)
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente autenticado no portal.
- `$pet_ids` (`array`): IDs dos pets do cliente (validados como pertencentes ao cliente).
- `$user_question` (`string`): Pergunta do usuário.

**Retorno:** `string|null Resposta da IA ou null em caso de erro/indisponibilidade.`


#### 🔧 get_base_system_prompt()

**Método Estático**

Verifica se a pergunta contém palavras-chave do contexto permitido. / private static function is_question_in_context( $question ) { // Cast para string para compatibilidade com PHP 8.1+ $question_lower = mb_strtolower( (string) $question, 'UTF-8' ); foreach ( self::CONTEXT_KEYWORDS as $keyword ) { if ( false !== mb_strpos( $question_lower, $keyword ) ) { return true; } } return false; } /** Retorna o prompt base do sistema. IMPORTANTE: Este método agora utiliza DPS_AI_Prompts::get() para carregar o prompt de arquivo e aplicar filtros, permitindo customização. Mantido por retrocompatibilidade com código existente.

**Assinatura:**

```php
DPS_AI_Assistant::get_base_system_prompt()
```

**Parâmetros:**

- `$question` (`string`): Pergunta do usuário.

**Retorno:** `bool True se a pergunta está no contexto, false caso contrário.`


#### 🔧 get_base_system_prompt_with_language()

**Método Estático**

Retorna o prompt base do sistema com instrução de idioma. Adiciona instrução explícita para que a IA responda no idioma configurado.

**Assinatura:**

```php
DPS_AI_Assistant::get_base_system_prompt_with_language($language = 'pt_BR')
```

**Parâmetros:**

- `$language` (`string`): Código do idioma (pt_BR, en_US, es_ES, auto).

**Retorno:** `string Conteúdo do prompt base do sistema com instrução de idioma.`


#### 🔧 invalidate_context_cache()

**Método Estático**

Obtém contexto do cliente com cache via Transients. Cacheia o contexto por 5 minutos para evitar reconstrução repetitiva a cada pergunta do mesmo cliente. / private static function get_cached_client_context( $client_id, array $pet_ids ) { // Gera chave única baseada no cliente e pets usando wp_hash para melhor unicidade $pets_string = implode( ',', array_map( 'absint', $pet_ids ) ); $cache_key   = 'dps_ai_ctx_' . absint( $client_id ) . '_' . substr( wp_hash( $pets_string ), 0, 12 ); // Tenta obter do cache (se não estiver desabilitado) if ( ! dps_is_cache_disabled() ) { $cached_context = get_transient( $cache_key ); if ( false !== $cached_context ) { return $cached_context; } } // Cache miss: reconstrói contexto $context = self::build_client_context( $client_id, $pet_ids ); // Salva no cache (se não estiver desabilitado) if ( ! dps_is_cache_disabled() ) { set_transient( $cache_key, $context, self::CONTEXT_CACHE_EXPIRATION ); } return $context; } /** Invalida o cache de contexto de um cliente. Deve ser chamado quando dados do cliente, pets ou agendamentos são alterados.

**Assinatura:**

```php
DPS_AI_Assistant::invalidate_context_cache($client_id, array $pet_ids = [])
```

**Parâmetros:**

- `$client_id` (`int`): ID do cliente.
- `$pet_ids` (`array`): IDs dos pets.
- `$client_id` (`int`): ID do cliente.
- `$pet_ids` (`array`): IDs dos pets (opcional, se vazio limpa todos os caches do cliente).

**Retorno:** `string Contexto formatado (do cache ou recém-construído).`


### DPS_AI_Knowledge_Base

Base de conhecimento: busca semântica e contextual.

**Arquivo:** `plugins/desi-pet-shower-ai/includes/class-dps-ai-knowledge-base.php`

**Métodos públicos:** 10


#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.5.0

Base de Conhecimento do AI Add-on. Gerencia artigos e FAQs que são incluídos no contexto da IA para respostas mais precisas e personalizadas. / if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe de Base de Conhecimento. / class DPS_AI_Knowledge_Base { /** Slug do Custom Post Type. / const POST_TYPE = 'dps_ai_knowledge'; /** Taxonomia para categorias de conhecimento. / const TAXONOMY = 'dps_ai_knowledge_cat'; /** Instância única (singleton). / private static $instance = null; /** Recupera a instância única.

**Assinatura:**

```php
DPS_AI_Knowledge_Base::get_instance()
```

**Retorno:** `DPS_AI_Knowledge_Base`


#### 🎯 register_post_type()

**Método de Instância**

Construtor privado. / private function __construct() { add_action( 'init', [ $this, 'register_post_type' ] ); add_action( 'init', [ $this, 'register_taxonomy' ] ); add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] ); add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta_boxes' ] ); } /** Registra o Custom Post Type para Base de Conhecimento.

**Assinatura:**

```php
$aiknowledg->register_post_type()
```


#### 🎯 register_taxonomy()

**Método de Instância**

Registra a taxonomia de categorias.

**Assinatura:**

```php
$aiknowledg->register_taxonomy()
```


#### 🎯 add_meta_boxes()

**Método de Instância**

Cria termos padrão da taxonomia. Chamado apenas uma vez durante a primeira inicialização. / private static function create_default_terms() { $default_terms = [ 'servicos'     => __( 'Serviços', 'dps-ai' ), 'agendamento'  => __( 'Agendamento', 'dps-ai' ), 'pagamentos'   => __( 'Pagamentos', 'dps-ai' ), 'fidelidade'   => __( 'Fidelidade', 'dps-ai' ), 'cuidados-pet' => __( 'Cuidados com Pet', 'dps-ai' ), 'politicas'    => __( 'Políticas', 'dps-ai' ), ]; foreach ( $default_terms as $slug => $name ) { if ( ! term_exists( $slug, self::TAXONOMY ) ) { wp_insert_term( $name, self::TAXONOMY, [ 'slug' => $slug ] ); } } } /** Adiciona meta boxes.

**Assinatura:**

```php
$aiknowledg->add_meta_boxes()
```


## 📅 AGENDA ADD-ON


### DPS_Agenda_Capacity_Helper

Gerenciamento de capacidade: slots disponíveis por período.

**Arquivo:** `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-capacity-helper.php`

**Métodos públicos:** 10


#### 🔧 get_default_capacity_config()

**Método Estático** | **Desde:** 1.4.0

Helper para gerenciamento de capacidade e lotação da AGENDA. Fornece funcionalidades para: - Configurar capacidade máxima por faixa horária - Calcular ocupação/lotação - Gerar dados para heatmap de capacidade / // Impede acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } class DPS_Agenda_Capacity_Helper { /** Retorna a configuração de capacidade padrão.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::get_default_capacity_config()
```

**Retorno:** `array Configuração de capacidade.`


#### 🔧 get_capacity_config()

**Método Estático**

Obtém a configuração de capacidade atual.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::get_capacity_config()
```

**Retorno:** `array Configuração de capacidade.`


#### 🔧 save_capacity_config()

**Método Estático**

Salva a configuração de capacidade.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::save_capacity_config($config)
```

**Parâmetros:**

- `$config` (`array`): Configuração de capacidade.

**Retorno:** `bool True se salvo com sucesso.`


#### 🔧 get_capacity_for_period()

**Método Estático**

Retorna a capacidade para um slot específico.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::get_capacity_for_period($period)
```

**Parâmetros:**

- `$period` (`string`): 'morning' ou 'afternoon'.

**Retorno:** `int Capacidade máxima.`


#### 🔧 get_period_from_time()

**Método Estático**

Determina o período (morning/afternoon) baseado em um horário.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::get_period_from_time($time)
```

**Parâmetros:**

- `$time` (`string`): Horário no formato H:i.

**Retorno:** `string 'morning' ou 'afternoon'.`


#### 🔧 get_capacity_heatmap_data()

**Método Estático**

Retorna dados de heatmap de capacidade para um intervalo de datas.

**Assinatura:**

```php
DPS_Agenda_Capacity_Helper::get_capacity_heatmap_data($start_date, $end_date)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial no formato Y-m-d.
- `$end_date` (`string`): Data final no formato Y-m-d.

**Retorno:** `array Dados do heatmap.`


### DPS_Agenda_GPS_Helper

Funcionalidades GPS: cálculo de rotas e distâncias.

**Arquivo:** `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-gps-helper.php`

**Métodos públicos:** 7


#### 🔧 get_shop_address()

**Método Estático** | **Desde:** 1.2.0

Helper para geração de rotas GPS na AGENDA. Centraliza a lógica de construção de URLs do Google Maps para rotas, SEMPRE do endereço do Banho e Tosa até o endereço do cliente. / // Impede acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } class DPS_Agenda_GPS_Helper { /** Retorna o endereço do Banho e Tosa (loja). Tenta obter o endereço configurado nas opções. Se não existir, retorna um endereço padrão vazio.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::get_shop_address()
```

**Retorno:** `string Endereço da loja.`


#### 🔧 get_client_address()

**Método Estático**

Retorna o endereço do cliente de um agendamento.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::get_client_address($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string Endereço do cliente ou string vazia.`


#### 🔧 get_route_url()

**Método Estático**

Monta a URL de rota do Google Maps. IMPORTANTE: SEMPRE monta a rota do Banho e Tosa até o cliente. Não implementa o trajeto inverso nesta fase.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::get_route_url($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string URL do Google Maps ou string vazia se não houver dados suficientes.`


#### 🔧 render_route_button()

**Método Estático**

Renderiza botão "Abrir rota" se houver dados suficientes.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::render_route_button($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string HTML do botão ou string vazia.`


#### 🔧 render_map_link()

**Método Estático**

Renderiza link de mapa simples (apenas destino, sem rota). Mantido para compatibilidade com o código existente.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::render_map_link($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string HTML do link ou string vazia.`


#### 🔧 is_shop_address_configured()

**Método Estático**

Verifica se a configuração de endereço da loja está definida.

**Assinatura:**

```php
DPS_Agenda_GPS_Helper::is_shop_address_configured()
```

**Retorno:** `bool True se configurado.`


### DPS_Agenda_Payment_Helper

Helper para processar pagamentos de agendamentos.

**Arquivo:** `plugins/desi-pet-shower-agenda/includes/class-dps-agenda-payment-helper.php`

**Métodos públicos:** 7


#### 🔧 get_payment_status()

**Método Estático** | **Desde:** 1.2.0

Helper para consolidar status de pagamento na AGENDA. Centraliza a lógica de obtenção de status de pagamento, evitando duplicação de código entre diferentes componentes da agenda. / // Impede acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } class DPS_Agenda_Payment_Helper { /** Retorna o status consolidado de pagamento de um agendamento. Mapeia os diferentes estados possíveis para valores padronizados: - 'paid': Pagamento confirmado - 'pending': Link enviado, aguardando pagamento - 'error': Erro na geração do link - 'not_requested': Nenhuma tentativa de cobrança ainda

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::get_payment_status($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string Status consolidado.`


#### 🔧 get_payment_badge_config()

**Método Estático**

Retorna a configuração de badge para um status de pagamento.

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::get_payment_badge_config($status)
```

**Parâmetros:**

- `$status` (`string`): Status retornado por get_payment_status().

**Retorno:** `array Configuração com 'label', 'class', 'icon'.`


#### 🔧 get_payment_details()

**Método Estático**

Retorna detalhes de pagamento para tooltip/popover.

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::get_payment_details($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `array Detalhes com 'has_details', 'link_url', 'last_attempt', 'error_message'.`


#### 🔧 render_payment_badge()

**Método Estático**

Renderiza badge de status de pagamento.

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::render_payment_badge($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string HTML do badge.`


#### 🔧 render_payment_tooltip()

**Método Estático**

Renderiza tooltip com detalhes de pagamento.

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::render_payment_tooltip($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string HTML do tooltip.`


#### 🔧 render_resend_button()

**Método Estático** | **Desde:** 1.5.0

Renderiza botão "Reenviar link de pagamento" se aplicável.

**Assinatura:**

```php
DPS_Agenda_Payment_Helper::render_resend_button($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `string HTML do botão ou string vazia.`


## 📊 STATS ADD-ON


### DPS_Stats_API

API de estatísticas: métricas de agendamentos, receita e performance.

**Arquivo:** `plugins/desi-pet-shower-stats/includes/class-dps-stats-api.php`

**Métodos públicos:** 20


#### 🔧 bump_cache_version()

**Método Estático** | **Desde:** 1.1.0

API pública do Stats Add-on Centraliza toda a lógica de estatísticas e métricas para reutilização por outros add-ons e facilitar manutenção. / // Bloqueia acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe DPS_Stats_API Fornece métodos públicos para: - Obter contagem de atendimentos - Calcular receita e despesas - Listar pets inativos - Obter serviços mais solicitados - Calcular métricas de comparativo de períodos - Calcular ticket médio e taxa de retenção / class DPS_Stats_API { /** Verifica se a tabela dps_transacoes existe. / private static function table_dps_transacoes_exists() { global $wpdb; $table_name = $wpdb->prefix . 'dps_transacoes'; $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) ); return $table_exists === $table_name; } /** F2.3: Obtém valor do cache (object cache ou transient). / private static function cache_get( $key ) { if ( wp_using_ext_object_cache() ) { return wp_cache_get( $key, 'dps_stats' ); } return get_transient( $key ); } /** F2.3: Armazena valor no cache (object cache ou transient). / private static function cache_set( $key, $value, $ttl ) { if ( wp_using_ext_object_cache() ) { return wp_cache_set( $key, $value, 'dps_stats', $ttl ); } return set_transient( $key, $value, $ttl ); } /** F2.3: Obtém versão do cache para invalidação. / private static function get_cache_version() { $version = get_option( 'dps_stats_cache_version', 1 ); return (int) $version; } /** F2.3: Incrementa versão do cache (invalida todo cache).

**Assinatura:**

```php
DPS_Stats_API::bump_cache_version()
```

**Parâmetros:**

- `$key` (`string`): Chave do cache.
- `$key` (`string`): Chave do cache.
- `$value` (`mixed`): Valor a armazenar.
- `$ttl` (`int`): Time to live em segundos.

**Retorno:** `bool True se a tabela existe, false caso contrário.`


#### 🔧 get_appointments_count()

**Método Estático** | **Desde:** 1.1.0

Obtém contagem de atendimentos no período.

**Assinatura:**

```php
DPS_Stats_API::get_appointments_count($start_date, $end_date, $status = '')
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).
- `$status` (`string`): Status do agendamento (opcional).

**Retorno:** `int Número de atendimentos.`


#### 🔧 get_revenue_total()

**Método Estático** | **Desde:** 1.1.0

Obtém total de receitas pagas no período.

**Assinatura:**

```php
DPS_Stats_API::get_revenue_total($start_date, $end_date)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).

**Retorno:** `float Total de receitas.`


#### 🔧 get_expenses_total()

**Método Estático** | **Desde:** 1.1.0

Obtém total de despesas pagas no período.

**Assinatura:**

```php
DPS_Stats_API::get_expenses_total($start_date, $end_date)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).

**Retorno:** `float Total de despesas.`


#### 🔧 get_financial_totals()

**Método Estático** | **Desde:** 1.1.0

Obtém totais financeiros do período (receita e despesas).

**Assinatura:**

```php
DPS_Stats_API::get_financial_totals($start_date, $end_date)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).

**Retorno:** `array [ 'revenue' => float, 'expenses' => float ]`


#### 🔧 get_inactive_pets()

**Método Estático** | **Desde:** 1.1.0

Obtém pets inativos (sem atendimento há X dias).

**Assinatura:**

```php
DPS_Stats_API::get_inactive_pets($days = 30)
```

**Parâmetros:**

- `$days` (`int`): Número de dias de inatividade (padrão: 30).

**Retorno:** `array Lista de pets inativos com dados do cliente.`


#### 🔧 get_top_services()

**Método Estático** | **Desde:** 1.1.0

Obtém serviços mais solicitados no período.

**Assinatura:**

```php
DPS_Stats_API::get_top_services($start_date, $end_date, $limit = 5)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial (Y-m-d).
- `$end_date` (`string`): Data final (Y-m-d).
- `$limit` (`int`): Limite de serviços (padrão: 5).

**Retorno:** `array Lista de serviços com contagem.`


#### 🔧 get_period_comparison()

**Método Estático** | **Desde:** 1.1.0

Calcula comparativo entre período atual e período anterior.

**Assinatura:**

```php
DPS_Stats_API::get_period_comparison($start_date, $end_date)
```

**Parâmetros:**

- `$start_date` (`string`): Data inicial do período atual (Y-m-d).
- `$end_date` (`string`): Data final do período atual (Y-m-d).

**Retorno:** `array Comparativo de métricas.`


## 🛠️ SERVICES ADD-ON


### DPS_Services_API

API de serviços: CRUD e consulta de serviços disponíveis.

**Arquivo:** `plugins/desi-pet-shower-services/dps_service/includes/class-dps-services-api.php`

**Métodos públicos:** 12


#### 🔧 get_service()

**Método Estático** | **Desde:** 1.2.0

API pública do Services Add-on Centraliza toda a lógica de serviços, cálculo de preços e informações detalhadas para reutilização por outros add-ons (Agenda, Finance, Portal, etc.) / // Bloqueia acesso direto if ( ! defined( 'ABSPATH' ) ) { exit; } /** Classe DPS_Services_API Fornece métodos públicos para: - Obter dados completos de um serviço - Calcular preço por porte de pet - Calcular total de um agendamento - Obter detalhes de serviços de um agendamento / class DPS_Services_API { /** Obtém dados completos de um serviço. Estrutura retornada: [ 'id'           => int, 'title'        => string, 'type'         => string, 'category'     => string, 'active'       => bool, 'description'  => string, 'price'        => float (preço base), 'price_small'  => float|null, 'price_medium' => float|null, 'price_large'  => float|null, ]

**Assinatura:**

```php
DPS_Services_API::get_service($service_id)
```

**Parâmetros:**

- `$service_id` (`int`): ID do serviço.

**Retorno:** `array|null Array com dados do serviço ou null se não encontrado.`


#### 🔧 calculate_price()

**Método Estático** | **Desde:** 1.2.0

Calcula o preço de um serviço com base no porte do pet.

**Assinatura:**

```php
DPS_Services_API::calculate_price($service_id, $pet_size = '', $context = [])
```

**Parâmetros:**

- `$service_id` (`int`): ID do serviço.
- `$pet_size` (`string`): Porte do pet: 'pequeno', 'medio', 'grande' ou 'small', 'medium', 'large'.
- `$context` (`array`): Contexto adicional (reservado para uso futuro).

**Retorno:** `float|null Preço calculado ou null se serviço não encontrado.`


#### 🔧 calculate_appointment_total()

**Método Estático** | **Desde:** 1.2.0

Calcula o total de um agendamento com base nos serviços e pets selecionados. Estrutura retornada: [ 'total'            => float, 'services_total'   => float, 'services_details' => array, 'extras_total'     => float, 'taxidog_total'    => float, ] Context pode incluir: - 'custom_prices': array [ service_id => price ] com preços personalizados - 'extras': float valor de extras - 'taxidog': float valor de taxidog

**Assinatura:**

```php
DPS_Services_API::calculate_appointment_total($service_ids, $pet_ids, $context = [])
```

**Parâmetros:**

- `$service_ids` (`array`): Array de IDs de serviços.
- `$pet_ids` (`array`): Array de IDs de pets.
- `$context` (`array`): Contexto adicional (pode conter 'custom_prices', 'extras', 'taxidog').

**Retorno:** `array Array com informações do cálculo.`


#### 🔧 get_services_details()

**Método Estático** | **Desde:** 1.2.0

Obtém detalhes de serviços de um agendamento. Estrutura retornada: [ 'services' => [ ['name' => string, 'price' => float], ... ], 'total' => float, ]

**Assinatura:**

```php
DPS_Services_API::get_services_details($appointment_id)
```

**Parâmetros:**

- `$appointment_id` (`int`): ID do agendamento.

**Retorno:** `array Array com detalhes dos serviços.`


#### 🔧 calculate_package_price()

**Método Estático** | **Desde:** 1.2.0

Normaliza o porte do pet para formato padrão. / private static function normalize_pet_size( $size ) { $size = strtolower( trim( $size ) ); // Remove acentos $size = remove_accents( $size ); if ( 'pequeno' === $size || 'small' === $size ) { return 'small'; } if ( 'medio' === $size || 'médio' === $size || 'medium' === $size ) { return 'medium'; } if ( 'grande' === $size || 'large' === $size ) { return 'large'; } return ''; } /** Obtém valor float de um meta, retornando null se vazio. / private static function get_meta_float( $post_id, $meta_key ) { $value = get_post_meta( $post_id, $meta_key, true ); if ( '' === $value || null === $value ) { return null; } return (float) $value; } // ===================================================================== // FUNCIONALIDADES NOVAS v1.3.0 // ===================================================================== /** Calcula o preço de um pacote promocional. Um pacote pode ter: - Preço fixo (service_package_fixed_price): ignora serviços incluídos - Desconto percentual (service_package_discount): aplica sobre soma dos serviços

**Assinatura:**

```php
DPS_Services_API::calculate_package_price($package_id, $pet_size = '')
```

**Parâmetros:**

- `$size` (`string`): Porte do pet.
- `$post_id` (`int`): Post ID.
- `$meta_key` (`string`): Meta key.
- `$package_id` (`int`): ID do pacote.
- `$pet_size` (`string`): Porte do pet para cálculo.

**Retorno:** `string Porte normalizado: 'small', 'medium', 'large' ou ''.`


#### 🔧 get_price_history()

**Método Estático** | **Desde:** 1.3.0

Obtém o histórico de alterações de preço de um serviço. Estrutura de cada item: [ 'date'       => string (Y-m-d H:i:s), 'user_id'    => int, 'user_name'  => string, 'old_price'  => float, 'new_price'  => float, 'price_type' => string ('base', 'small', 'medium', 'large'), ]

**Assinatura:**

```php
DPS_Services_API::get_price_history($service_id)
```

**Parâmetros:**

- `$service_id` (`int`): ID do serviço.

**Retorno:** `array Array de alterações ordenadas da mais recente para a mais antiga.`


## 🔌 OTHER ADD-ONS

### Overview

Estes add-ons fornecem funcionalidades especializadas. A maioria segue o padrão singleton com método `get_instance()` e funções `activate()`/`deactivate()`.


## 💾 BACKUP ADD-ON

### Overview

Sistema completo de backup, exportação e restauração de dados do DPS. Suporta backups completos, seletivos e diferenciais (desde data específica), com agendamento automático via cron, comparação de diferenças, histórico com retenção configurável e interface administrativa integrada.

**Diretório:** `plugins/desi-pet-shower-backup/`

**Arquivo principal:** `desi-pet-shower-backup-addon.php`

**Versão:** 1.3.1


### DPS_Backup_Addon

Classe principal de gerenciamento; registra menus, renderiza interface administrativa, processa formulários e requisições AJAX.

**Arquivo:** `plugins/desi-pet-shower-backup/desi-pet-shower-backup-addon.php`

**Métodos públicos:** 12

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Retorna a instância singleton do add-on.

**Assinatura:**

```php
DPS_Backup_Addon::get_instance()
```

**Retorno:** `DPS_Backup_Addon` Instância singleton.

---

#### 🎯 register_admin_menu()

**Método de Instância** | **Desde:** 1.0.0

Registra o submenu "Backup" no admin do WordPress sob o menu principal "desi.pet by PRObst".

**Assinatura:**

```php
$instance->register_admin_menu()
```

**Retorno:** `void`

---

#### 🎯 enqueue_admin_assets()

**Método de Instância** | **Desde:** 1.0.0

Enfileira CSS e JavaScript para a página de backup no admin.

**Assinatura:**

```php
$instance->enqueue_admin_assets($hook)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$hook` | `string` | Hook da página atual do admin |

**Retorno:** `void`

---

#### 🎯 render_admin_page()

**Método de Instância** | **Desde:** 1.0.0

Renderiza a página principal de backup e restauração no admin, incluindo configurações, botões de ação e histórico.

**Assinatura:**

```php
$instance->render_admin_page()
```

**Retorno:** `void`

---

#### 🎯 handle_save_settings()

**Método de Instância** | **Desde:** 1.2.0

Processa o formulário de configurações de agendamento de backup.

**Assinatura:**

```php
$instance->handle_save_settings()
```

**Retorno:** `void`

---

#### 🎯 handle_export()

**Método de Instância** | **Desde:** 1.0.0

Processa a requisição de exportação manual de backup (download JSON).

**Assinatura:**

```php
$instance->handle_export()
```

**Retorno:** `void` (força download ou exibe erro)

---

#### 🎯 handle_import()

**Método de Instância** | **Desde:** 1.0.0

Processa o upload e restauração de arquivo de backup.

**Assinatura:**

```php
$instance->handle_import()
```

**Retorno:** `void`

---

#### 🎯 ajax_compare_backup()

**Método de Instância** | **Desde:** 1.0.0

Endpoint AJAX para comparar backup com dados atuais.

**Assinatura:**

```php
$instance->ajax_compare_backup()
```

**Retorno:** `void` (responde JSON)

---

#### 🎯 ajax_delete_backup()

**Método de Instância** | **Desde:** 1.0.0

Endpoint AJAX para deletar backup do histórico.

**Assinatura:**

```php
$instance->ajax_delete_backup()
```

**Retorno:** `void` (responde JSON)

---

#### 🎯 ajax_download_backup()

**Método de Instância** | **Desde:** 1.0.0

Endpoint AJAX para baixar backup do histórico.

**Assinatura:**

```php
$instance->ajax_download_backup()
```

**Retorno:** `void` (força download)

---

#### 🎯 ajax_restore_from_history()

**Método de Instância** | **Desde:** 1.0.0

Endpoint AJAX para restaurar backup do histórico.

**Assinatura:**

```php
$instance->ajax_restore_from_history()
```

**Retorno:** `void` (responde JSON)


### DPS_Backup_Exporter

Exportador de dados em formatos completo, seletivo ou diferencial.

**Arquivo:** `plugins/desi-pet-shower-backup/includes/class-dps-backup-exporter.php`

**Métodos públicos:** 13

#### 🎯 build_complete_backup()

**Método de Instância** | **Desde:** 1.0.0

Cria backup completo de todos os componentes disponíveis.

**Assinatura:**

```php
$exporter->build_complete_backup()
```

**Retorno:** `array|WP_Error` Dados do backup ou erro.

---

#### 🎯 build_selective_backup()

**Método de Instância** | **Desde:** 1.1.0

Cria backup seletivo com componentes especificados.

**Assinatura:**

```php
$exporter->build_selective_backup($components)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$components` | `array` | Array de chaves de componentes (ex: ['clients', 'pets']) |

**Retorno:** `array|WP_Error` Dados do backup ou erro.

---

#### 🎯 build_differential_backup()

**Método de Instância** | **Desde:** 1.2.0

Cria backup diferencial desde uma data específica (apenas registros modificados).

**Assinatura:**

```php
$exporter->build_differential_backup($since)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$since` | `string` | Data em formato ISO 8601 ou timestamp |

**Retorno:** `array|WP_Error` Dados do backup ou erro.

---

#### 🎯 export_transactions()

**Método de Instância** | **Desde:** 1.0.0

Exporta transações financeiras com validação de relacionamentos.

**Assinatura:**

```php
$exporter->export_transactions()
```

**Retorno:** `array` Array de transações.

---

#### 🎯 get_component_counts()

**Método de Instância** | **Desde:** 1.0.0

Retorna contagem de registros para todos os componentes disponíveis.

**Assinatura:**

```php
$exporter->get_component_counts()
```

**Retorno:** `array` Array associativo com contagens por componente.

**Exemplo:**

```php
$exporter = new DPS_Backup_Exporter();
$counts = $exporter->get_component_counts();
// ['clients' => 150, 'pets' => 300, 'appointments' => 1200, ...]
```


### DPS_Backup_History

Gerencia registros de histórico de backups e armazenamento de arquivos.

**Arquivo:** `plugins/desi-pet-shower-backup/includes/class-dps-backup-history.php`

**Métodos públicos:** 10 (todos estáticos)

#### 🔧 get_history()

**Método Estático** | **Desde:** 1.0.0

Recupera histórico de backups, ordenado do mais recente para o mais antigo.

**Assinatura:**

```php
DPS_Backup_History::get_history($limit = 0)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$limit` | `int` | Número de registros a retornar (0 = todos) |

**Retorno:** `array` Array de entradas de backup.

---

#### 🔧 add_entry()

**Método Estático** | **Desde:** 1.0.0

Adiciona nova entrada ao histórico, aplicando retenção automática.

**Assinatura:**

```php
DPS_Backup_History::add_entry($entry)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$entry` | `array` | Dados da entrada (id, timestamp, type, stats, filepath, size) |

**Retorno:** `bool` True em caso de sucesso.

---

#### 🔧 remove_entry()

**Método Estático** | **Desde:** 1.0.0

Remove backup do histórico e deleta o arquivo.

**Assinatura:**

```php
DPS_Backup_History::remove_entry($id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$id` | `string` | UUID do backup |

**Retorno:** `bool` True em caso de sucesso.

---

#### 🔧 save_backup_file()

**Método Estático** | **Desde:** 1.0.0

Salva conteúdo JSON do backup no disco com segurança.

**Assinatura:**

```php
DPS_Backup_History::save_backup_file($filename, $content)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$filename` | `string` | Nome do arquivo |
| `$content` | `string` | Conteúdo JSON do backup |

**Retorno:** `string|WP_Error` Caminho completo do arquivo ou erro.

---

#### 🔧 format_size()

**Método Estático** | **Desde:** 1.0.0

Formata bytes para formato legível (KB, MB, GB).

**Assinatura:**

```php
DPS_Backup_History::format_size($bytes)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$bytes` | `int` | Tamanho em bytes |

**Retorno:** `string` Tamanho formatado (ex: "2.5 MB").


### DPS_Backup_Scheduler

Gerencia agendamento automático de backups via WordPress cron.

**Arquivo:** `plugins/desi-pet-shower-backup/includes/class-dps-backup-scheduler.php`

**Métodos públicos:** 6 (todos estáticos)

#### 🔧 init()

**Método Estático** | **Desde:** 1.2.0

Inicializa hooks e filtros do agendador.

**Assinatura:**

```php
DPS_Backup_Scheduler::init()
```

**Retorno:** `void`

---

#### 🔧 schedule()

**Método Estático** | **Desde:** 1.2.0

Agenda backup automático baseado nas configurações.

**Assinatura:**

```php
DPS_Backup_Scheduler::schedule()
```

**Retorno:** `bool` True se agendado com sucesso.

---

#### 🔧 is_scheduled()

**Método Estático** | **Desde:** 1.2.0

Verifica se backup está agendado.

**Assinatura:**

```php
DPS_Backup_Scheduler::is_scheduled()
```

**Retorno:** `bool` True se agendado.

---

#### 🔧 get_next_run()

**Método Estático** | **Desde:** 1.2.0

Retorna timestamp da próxima execução agendada.

**Assinatura:**

```php
DPS_Backup_Scheduler::get_next_run()
```

**Retorno:** `int|false` Timestamp Unix ou false se não agendado.


### DPS_Backup_Comparator

Compara dados de backup com estado atual do sistema.

**Arquivo:** `plugins/desi-pet-shower-backup/includes/class-dps-backup-comparator.php`

**Métodos públicos:** 2 (ambos estáticos)

#### 🔧 compare()

**Método Estático** | **Desde:** 1.0.0

Compara backup com dados atuais, retorna comparação detalhada.

**Assinatura:**

```php
DPS_Backup_Comparator::compare($payload)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$payload` | `array` | Dados do backup a comparar |

**Retorno:** `array` Comparação detalhada por componente.

---

#### 🔧 format_summary()

**Método Estático** | **Desde:** 1.0.0

Formata comparação como tabela HTML com avisos.

**Assinatura:**

```php
DPS_Backup_Comparator::format_summary($comparison)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$comparison` | `array` | Resultado de compare() |

**Retorno:** `string` HTML formatado.


### DPS_Backup_Settings

Gerencia configurações de operações de backup.

**Arquivo:** `plugins/desi-pet-shower-backup/includes/class-dps-backup-settings.php`

**Métodos públicos:** 7 (todos estáticos)

#### 🔧 get_all()

**Método Estático** | **Desde:** 1.2.0

Recupera todas as configurações com defaults mesclados.

**Assinatura:**

```php
DPS_Backup_Settings::get_all()
```

**Retorno:** `array` Array de configurações.

---

#### 🔧 get()

**Método Estático** | **Desde:** 1.2.0

Obtém valor de uma configuração específica.

**Assinatura:**

```php
DPS_Backup_Settings::get($key, $default = null)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$key` | `string` | Chave da configuração |
| `$default` | `mixed` | Valor padrão se não existir |

**Retorno:** `mixed` Valor da configuração.

---

#### 🔧 set()

**Método Estático** | **Desde:** 1.2.0

Define valor de uma configuração.

**Assinatura:**

```php
DPS_Backup_Settings::set($key, $value)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$key` | `string` | Chave da configuração |
| `$value` | `mixed` | Valor a salvar |

**Retorno:** `bool` True em caso de sucesso.

---

#### 🔧 get_available_components()

**Método Estático** | **Desde:** 1.0.0

Retorna componentes disponíveis para backup.

**Assinatura:**

```php
DPS_Backup_Settings::get_available_components()
```

**Retorno:** `array` Array de componentes (clients, pets, appointments, transactions, etc.).


## 📅 BOOKING ADD-ON

### Overview

Sistema de reservas online integrado ao painel principal do DPS. Fornece página dedicada de agendamento com mesmas funcionalidades do painel de gestão, mas focada exclusivamente em criação de novos agendamentos. Ideal para recepção ou ambientes onde se deseja restringir acesso apenas à função de agendamento.

**Diretório:** `plugins/desi-pet-shower-booking/`

**Arquivo principal:** `desi-pet-shower-booking-addon.php`

**Versão:** 1.0.0


### Funções Globais

#### 📦 dps_booking_check_base_plugin()

**Função Global** | **Desde:** 1.0.0

Verifica se o plugin base está ativo; exibe aviso de erro se ausente.

**Assinatura:**

```php
dps_booking_check_base_plugin()
```

**Retorno:** `bool` True se plugin base existe, false caso contrário.

---

#### 📦 dps_booking_load_textdomain()

**Função Global** | **Desde:** 1.0.0

Carrega arquivos de tradução para o add-on de booking.

**Assinatura:**

```php
dps_booking_load_textdomain()
```

**Retorno:** `void`

---

#### 📦 dps_booking_init_addon()

**Função Global** | **Desde:** 1.0.0

Inicializa a instância singleton do Booking Add-on.

**Assinatura:**

```php
dps_booking_init_addon()
```

**Retorno:** `void`


### DPS_Booking_Addon

Classe principal fornecendo página dedicada de agendamento com mesma funcionalidade do Painel de Gestão DPS.

**Arquivo:** `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php`

**Métodos públicos:** 5

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Retorna instância singleton do add-on.

**Assinatura:**

```php
DPS_Booking_Addon::get_instance()
```

**Retorno:** `DPS_Booking_Addon` Instância singleton.

---

#### 🎯 activate()

**Método de Instância** | **Desde:** 1.0.0

Cria página de agendamento na ativação do plugin.

**Assinatura:**

```php
$instance->activate()
```

**Retorno:** `void`

**Descrição:** Cria página com título "Agendamento" e shortcode `[dps_booking_form]` se não existir.

---

#### 🎯 enqueue_assets()

**Método de Instância** | **Desde:** 1.0.0

Enfileira CSS/JS para página de agendamento; carrega apenas na página de booking ou onde o shortcode existe.

**Assinatura:**

```php
$instance->enqueue_assets()
```

**Retorno:** `void`

---

#### 🎯 render_booking_form()

**Método de Instância** | **Desde:** 1.0.0

Renderiza formulário completo de agendamento com verificações de permissão.

**Assinatura:**

```php
$instance->render_booking_form()
```

**Retorno:** `string` HTML do formulário.

**Descrição:** Exibe requisito de login ou página de confirmação se necessário.

---

#### 🎯 capture_saved_appointment()

**Método de Instância** | **Desde:** 1.0.0

Captura dados de agendamento salvo e armazena em transient para exibição de confirmação.

**Assinatura:**

```php
$instance->capture_saved_appointment($appointment_id, $appointment_type)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$appointment_id` | `int` | ID do agendamento |
| `$appointment_type` | `string` | Tipo do agendamento |

**Retorno:** `void`


### Shortcode

#### [dps_booking_form]

Renderiza o formulário de agendamento em qualquer página.

**Uso:**

```
[dps_booking_form]
```

**Atributos:** Nenhum

**Exemplo:**

```php
// Em um template ou página
echo do_shortcode('[dps_booking_form]');
```


### Hooks WordPress Utilizados

**Action Hooks:**
- `wp_enqueue_scripts` - Enfileira assets
- `dps_base_after_save_appointment` - Captura agendamento para confirmação
- `init` - Carrega text domain e inicializa add-on


## 👤 GROOMERS ADD-ON

### Overview

Portal completo de tosadores com autenticação via magic links (sem login tradicional). Gerencia perfis de staff (tosadores, banhistas, auxiliares, recepção), tokens de acesso permanentes e temporários, comissões automáticas, avaliações de clientes e dashboard com estatísticas de desempenho.

**Diretório:** `plugins/desi-pet-shower-groomers/`

**Arquivo principal:** `desi-pet-shower-groomers-addon.php`

**Versão:** 1.8.6


### DPS_Groomer_Session_Manager

Gerencia autenticação e sessões do portal de tosadores via magic links sem login tradicional.

**Arquivo:** `plugins/desi-pet-shower-groomers/includes/class-dps-groomer-session-manager.php`

**Padrão:** Singleton - use `DPS_Groomer_Session_Manager::get_instance()`

**Métodos públicos:** 10

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Recupera a instância singleton do gerenciador de sessões.

**Assinatura:**

```php
DPS_Groomer_Session_Manager::get_instance()
```

**Retorno:** `DPS_Groomer_Session_Manager` Instância singleton.

---

#### 🎯 authenticate_groomer()

**Método de Instância** | **Desde:** 1.0.0

Autentica um tosador, retorna true em caso de sucesso; valida role do usuário e regenera session ID.

**Assinatura:**

```php
$manager->authenticate_groomer($groomer_id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do usuário tosador |

**Retorno:** `bool` True se autenticação bem-sucedida.

---

#### 🎯 get_authenticated_groomer_id()

**Método de Instância** | **Desde:** 1.0.0

Retorna ID do tosador autenticado ou 0 se não autenticado.

**Assinatura:**

```php
$manager->get_authenticated_groomer_id()
```

**Retorno:** `int` ID do tosador ou 0.

---

#### 🎯 is_groomer_authenticated()

**Método de Instância** | **Desde:** 1.0.0

Verifica se algum tosador está atualmente autenticado.

**Assinatura:**

```php
$manager->is_groomer_authenticated()
```

**Retorno:** `bool` True se autenticado.

---

#### 🎯 validate_session()

**Método de Instância** | **Desde:** 1.0.0

Valida expiração da sessão atual (tempo de vida de 24h).

**Assinatura:**

```php
$manager->validate_session()
```

**Retorno:** `void`

---

#### 🎯 logout()

**Método de Instância** | **Desde:** 1.0.0

Limpa dados de sessão do tosador.

**Assinatura:**

```php
$manager->logout()
```

**Retorno:** `void`

---

#### 🎯 get_logout_url()

**Método de Instância** | **Desde:** 1.0.0

Gera URL de logout com nonce e parâmetro de redirecionamento opcional.

**Assinatura:**

```php
$manager->get_logout_url($redirect_to = '')
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$redirect_to` | `string` | URL para redirecionar após logout (opcional) |

**Retorno:** `string` URL de logout.

---

#### 🎯 get_authenticated_groomer()

**Método de Instância** | **Desde:** 1.0.0

Retorna objeto WP_User do tosador autenticado ou false.

**Assinatura:**

```php
$manager->get_authenticated_groomer()
```

**Retorno:** `WP_User|false` Objeto do usuário ou false.


### DPS_Groomer_Token_Manager

Gerencia geração, validação, revogação e limpeza de tokens de magic link para acesso ao portal.

**Arquivo:** `plugins/desi-pet-shower-groomers/includes/class-dps-groomer-token-manager.php`

**Padrão:** Singleton - use `DPS_Groomer_Token_Manager::get_instance()`

**Métodos públicos:** 10

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Recupera a instância singleton do gerenciador de tokens.

**Assinatura:**

```php
DPS_Groomer_Token_Manager::get_instance()
```

**Retorno:** `DPS_Groomer_Token_Manager` Instância singleton.

---

#### 🎯 generate_token()

**Método de Instância** | **Desde:** 1.0.0

Gera novo token de acesso; retorna token em texto plano ou false em caso de erro.

**Assinatura:**

```php
$manager->generate_token($groomer_id, $type = 'login', $expiration_minutes = null)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do tosador |
| `$type` | `string` | Tipo: 'login' (30min) ou 'permanent' (10 anos) |
| `$expiration_minutes` | `int` | Minutos de validade (opcional) |

**Retorno:** `string|false` Token em texto plano ou false em erro.

---

#### 🎯 validate_token()

**Método de Instância** | **Desde:** 1.0.0

Valida token e retorna dados se válido; verifica expiração, uso e status de revogação.

**Assinatura:**

```php
$manager->validate_token($token_plain)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$token_plain` | `string` | Token em texto plano |

**Retorno:** `array|false` Dados do token se válido, false caso contrário.

---

#### 🎯 revoke_tokens()

**Método de Instância** | **Desde:** 1.0.0

Revoga todos os tokens ativos de um tosador; retorna contagem de revogados ou false em erro.

**Assinatura:**

```php
$manager->revoke_tokens($groomer_id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do tosador |

**Retorno:** `int|false` Número de tokens revogados ou false.

---

#### 🎯 get_groomer_stats()

**Método de Instância** | **Desde:** 1.0.0

Retorna estatísticas de tokens: total_generated, total_used, active_tokens, last_used_at.

**Assinatura:**

```php
$manager->get_groomer_stats($groomer_id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do tosador |

**Retorno:** `array` Estatísticas de tokens.

---

#### 🎯 get_active_tokens()

**Método de Instância** | **Desde:** 1.0.0

Lista todos os tokens ativos de um tosador com ID, tipo, datas e IP.

**Assinatura:**

```php
$manager->get_active_tokens($groomer_id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do tosador |

**Retorno:** `array` Array de tokens ativos.


### DPS_Groomers_Addon

Classe principal do add-on gerenciando perfis de staff, portal via shortcodes, avaliações e comissões.

**Arquivo:** `plugins/desi-pet-shower-groomers/desi-pet-shower-groomers-addon.php`

**Padrão:** Singleton - use `DPS_Groomers_Addon::get_instance()`

**Métodos públicos:** 25+

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Recupera instância singleton do add-on.

**Assinatura:**

```php
DPS_Groomers_Addon::get_instance()
```

**Retorno:** `DPS_Groomers_Addon` Instância singleton.

---

#### 🎯 get_portal_page_url()

**Método de Instância** | **Desde:** 1.0.0

Retorna URL da página do portal de tosadores.

**Assinatura:**

```php
$addon->get_portal_page_url()
```

**Retorno:** `string` URL do portal ou fallback home_url/portal-groomer/.

---

#### 🎯 render_groomer_portal_shortcode()

**Método de Instância** | **Desde:** 1.0.0

Renderiza shortcode `[dps_groomer_portal]` com dashboard, agenda e abas de avaliações.

**Assinatura:**

```php
$addon->render_groomer_portal_shortcode($atts)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$atts` | `array` | Atributos do shortcode |

**Retorno:** `string` HTML do portal.

---

#### 🎯 render_groomer_dashboard_shortcode()

**Método de Instância** | **Desde:** 1.0.0

Renderiza shortcode `[dps_groomer_dashboard]` com stats e gráficos de desempenho.

**Assinatura:**

```php
$addon->render_groomer_dashboard_shortcode($atts)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$atts` | `array` | Atributos do shortcode |

**Retorno:** `string` HTML do dashboard.

---

#### 🎯 render_groomer_agenda_shortcode()

**Método de Instância** | **Desde:** 1.0.0

Renderiza shortcode `[dps_groomer_agenda]` com calendário de agendamentos do tosador.

**Assinatura:**

```php
$addon->render_groomer_agenda_shortcode($atts)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$atts` | `array` | Atributos do shortcode |

**Retorno:** `string` HTML da agenda.

---

#### 🎯 generate_staff_commission()

**Método de Instância** | **Desde:** 1.5.0

Gera automaticamente comissões de staff quando pagamento é confirmado; divide proporcionalmente entre staff vinculado.

**Assinatura:**

```php
$addon->generate_staff_commission($charge_id, $client_id, $value_cents)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$charge_id` | `int` | ID da cobrança |
| `$client_id` | `int` | ID do cliente |
| `$value_cents` | `int` | Valor em centavos |

**Retorno:** `void`

---

#### 🎯 get_groomer_rating()

**Método de Instância** | **Desde:** 1.6.0

Retorna avaliação média do tosador e contagem total de avaliações.

**Assinatura:**

```php
$addon->get_groomer_rating($groomer_id)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$groomer_id` | `int` | ID do tosador |

**Retorno:** `array` Array com 'average' e 'count'.

---

#### 🔧 get_staff_types()

**Método Estático** | **Desde:** 1.7.0

Retorna tipos de staff disponíveis com traduções.

**Assinatura:**

```php
DPS_Groomers_Addon::get_staff_types()
```

**Retorno:** `array` Array associativo ['groomer' => 'Tosador', 'banhista' => 'Banhista', ...].

---

#### 🔧 activate()

**Método Estático** | **Desde:** 1.0.0

Adiciona role dps_groomer na ativação do plugin.

**Assinatura:**

```php
DPS_Groomers_Addon::activate()
```

**Retorno:** `void`


### Shortcodes

#### [dps_groomer_portal]

Portal completo do tosador com abas de dashboard, agenda e avaliações.

**Uso:**

```
[dps_groomer_portal]
```

---

#### [dps_groomer_login]

Formulário de login/mensagem de autenticação com redirecionamento.

**Uso:**

```
[dps_groomer_login]
```

---

#### [dps_groomer_dashboard]

Dashboard com estatísticas e gráficos de desempenho.

**Uso:**

```
[dps_groomer_dashboard]
```

---

#### [dps_groomer_agenda]

Calendário de agendamentos do tosador.

**Uso:**

```
[dps_groomer_agenda]
```

---

#### [dps_groomer_review]

Formulário para clientes enviarem avaliações.

**Uso:**

```
[dps_groomer_review]
```

---

#### [dps_groomer_reviews]

Lista de avaliações e notas do tosador.

**Uso:**

```
[dps_groomer_reviews groomer_id="123"]
```


### Constantes

**DPS_Groomer_Session_Manager:**
- `SESSION_KEY = 'dps_groomer_id'`
- `SESSION_LIFETIME = 86400` (24 horas)

**DPS_Groomer_Token_Manager:**
- `DEFAULT_EXPIRATION_MINUTES = 30`
- `PERMANENT_EXPIRATION_MINUTES = 525600` (10 anos)

**DPS_Groomers_Addon:**
- `VERSION = '1.8.6'`
- `STAFF_TYPES = ['groomer', 'banhista', 'auxiliar', 'recepcao']`


## 💳 PAYMENT ADD-ON

### Overview

Integração completa com MercadoPago para geração de links de pagamento PIX, processamento de webhooks/IPN e marcação automática de pagamentos. Suporta configuração via constantes (wp-config.php) ou interface administrativa, com validação de webhooks via rate limiting e idempotência.

**Diretório:** `plugins/desi-pet-shower-payment/`

**Arquivo principal:** `desi-pet-shower-payment-addon.php`

**Versão:** 1.0.0


### DPS_Payment_Addon

Gerenciador principal de integração MercadoPago: geração de links, webhooks e injeção de informações de pagamento em mensagens.

**Arquivo:** `plugins/desi-pet-shower-payment/desi-pet-shower-payment-addon.php`

**Padrão:** Singleton - use `DPS_Payment_Addon::get_instance()`

**Métodos públicos:** 12

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Recupera instância singleton do add-on de pagamentos.

**Assinatura:**

```php
DPS_Payment_Addon::get_instance()
```

**Retorno:** `DPS_Payment_Addon` Instância singleton.

---

#### 🎯 enqueue_admin_assets()

**Método de Instância** | **Desde:** 1.0.0

Enfileira CSS e JavaScript na página de configurações de pagamento.

**Assinatura:**

```php
$addon->enqueue_admin_assets($hook)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$hook` | `string` | Hook da página atual do admin |

**Retorno:** `void`

---

#### 🎯 register_settings()

**Método de Instância** | **Desde:** 1.0.0

Registra configurações WordPress para access token, chave PIX e webhook secret com callbacks de sanitização.

**Assinatura:**

```php
$addon->register_settings()
```

**Retorno:** `void`

---

#### 🎯 sanitize_access_token()

**Método de Instância** | **Desde:** 1.0.0

Sanitiza access token do MercadoPago - remove espaços e caracteres inválidos.

**Assinatura:**

```php
$addon->sanitize_access_token($token)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$token` | `string` | Token bruto |

**Retorno:** `string` Token sanitizado (permite alfanuméricos, traços e underscores).

---

#### 🎯 sanitize_webhook_secret()

**Método de Instância** | **Desde:** 1.0.0

Sanitiza webhook secret - remove caracteres de controle mas permite especiais para senhas fortes.

**Assinatura:**

```php
$addon->sanitize_webhook_secret($secret)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$secret` | `string` | Secret bruto |

**Retorno:** `string` Secret sanitizado.

---

#### 🎯 add_settings_page()

**Método de Instância** | **Desde:** 1.0.0

Adiciona página de configurações no submenu "desi.pet by PRObst".

**Assinatura:**

```php
$addon->add_settings_page()
```

**Retorno:** `void`

---

#### 🎯 render_settings_page()

**Método de Instância** | **Desde:** 1.0.0

Renderiza página completa de configurações de pagamento com indicador de status.

**Assinatura:**

```php
$addon->render_settings_page()
```

**Retorno:** `void`

---

#### 🎯 maybe_generate_payment_link()

**Método de Instância** | **Desde:** 1.0.0

Gera link de pagamento para agendamentos finalizados e armazena como post meta.

**Assinatura:**

```php
$addon->maybe_generate_payment_link($appt_id, $appt_type)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$appt_id` | `int` | ID do agendamento |
| `$appt_type` | `string` | Tipo: "simple" ou "subscription" |

**Retorno:** `void`

**Disparado por:** Hook `dps_base_after_save_appointment`

---

#### 🎯 inject_payment_link_in_message()

**Método de Instância** | **Desde:** 1.0.0

Filtro que injeta link de pagamento e informações PIX em mensagens WhatsApp.

**Assinatura:**

```php
$addon->inject_payment_link_in_message($message, $appt, $context)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$message` | `string` | Mensagem original |
| `$appt` | `WP_Post` | Objeto do agendamento |
| `$context` | `string` | Contexto de uso |

**Retorno:** `string` Mensagem modificada (apenas para contexto "agenda").

**Filtro:** `dps_agenda_whatsapp_message`

---

#### 🎯 maybe_handle_mp_notification()

**Método de Instância** | **Desde:** 1.0.0

⚠️ **Segurança Crítica** - Processa webhooks/notificações IPN do MercadoPago com validação e rate limiting.

**Assinatura:**

```php
$addon->maybe_handle_mp_notification()
```

**Retorno:** `void`

**Descrição:** Valida webhook secret, aplica rate limiting (10 tentativas/5 min), verifica idempotência e atualiza status de pagamento.

**Disparado por:** Hook `init` (early)


### DPS_MercadoPago_Config

Gerencia credenciais seguras do MercadoPago com sistema de fallback prioritário (constantes → opções do banco).

**Arquivo:** `plugins/desi-pet-shower-payment/includes/class-dps-mercadopago-config.php`

**Métodos públicos:** 7 (todos estáticos)

#### 🔧 get_access_token()

**Método Estático** | **Desde:** 1.0.0

Recupera access token do MercadoPago.

**Assinatura:**

```php
DPS_MercadoPago_Config::get_access_token()
```

**Retorno:** `string` Access token.

**Prioridade:** Constante `DPS_MERCADOPAGO_ACCESS_TOKEN` → opção `dps_mercadopago_access_token` → string vazia

---

#### 🔧 get_public_key()

**Método Estático** | **Desde:** 1.0.0

Recupera public key do MercadoPago.

**Assinatura:**

```php
DPS_MercadoPago_Config::get_public_key()
```

**Retorno:** `string` Public key.

**Prioridade:** Constante `DPS_MERCADOPAGO_PUBLIC_KEY` → opção `dps_mercadopago_public_key` → string vazia

---

#### 🔧 get_webhook_secret()

**Método Estático** | **Desde:** 1.0.0

Recupera webhook secret para validação.

**Assinatura:**

```php
DPS_MercadoPago_Config::get_webhook_secret()
```

**Retorno:** `string` Webhook secret.

**Prioridade:** Constante `DPS_MERCADOPAGO_WEBHOOK_SECRET` → opção `dps_mercadopago_webhook_secret` → access token (fallback legado)

---

#### 🔧 is_access_token_from_constant()

**Método Estático** | **Desde:** 1.0.0

Verifica se access token é definido via constante `DPS_MERCADOPAGO_ACCESS_TOKEN`.

**Assinatura:**

```php
DPS_MercadoPago_Config::is_access_token_from_constant()
```

**Retorno:** `bool` True se definido via constante (útil para UI read-only).

---

#### 🔧 get_masked_credential()

**Método Estático** | **Desde:** 1.0.0

Retorna credencial mascarada para exibição segura na UI.

**Assinatura:**

```php
DPS_MercadoPago_Config::get_masked_credential($credential)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$credential` | `string` | Valor completo da credencial |

**Retorno:** `string` Últimos 4 caracteres prefixados com "••••" ou "••••" se vazio/curto.

**Exemplo:**

```php
$masked = DPS_MercadoPago_Config::get_masked_credential('APP-1234567890ABCDEF');
// Retorna: "••••CDEF"
```


### Hooks Utilizados

- `dps_base_after_save_appointment` - Gera link de pagamento
- `dps_agenda_whatsapp_message` - Injeta link em mensagens
- `admin_init` - Registra configurações
- `admin_menu` - Adiciona página de settings
- `admin_enqueue_scripts` - Enfileira assets
- `init` - Processa webhooks


### Integração com Finance Add-on

Trabalha com tabela `wp_dps_transacoes` do Finance Add-on. Degrada graciosamente se tabela não disponível.

**Fluxo:**
1. Agendamento finalizado → gera link de pagamento
2. Cliente paga via MercadoPago
3. Webhook recebido → valida credenciais
4. Marca transação como paga em `wp_dps_transacoes`
5. Dispara hook `dps_finance_booking_paid` (Loyalty integra aqui)


## 📝 REGISTRATION ADD-ON

### Overview

Formulário multi-etapa de registro de clientes e pets com validação avançada (CPF, duplicatas, reCAPTCHA v3, honeypot), confirmação por email com tokens de 48h, lembretes automáticos, integração com Google Maps API para endereços, e REST API pública com autenticação por chave para integrações externas.

**Diretório:** `plugins/desi-pet-shower-registration/`

**Arquivo principal:** `desi-pet-shower-registration-addon.php`

**Versão:** 1.0.0


### Funções Globais

#### 📦 dps_registration_check_base_plugin()

**Função Global** | **Desde:** 1.0.0

Verifica se o plugin base DPS está ativo; exibe aviso administrativo se ausente.

**Assinatura:**

```php
dps_registration_check_base_plugin()
```

**Retorno:** `bool` True se plugin base existe.

---

#### 📦 dps_registration_load_textdomain()

**Função Global** | **Desde:** 1.0.0

Carrega domínio de tradução do plugin para localização.

**Assinatura:**

```php
dps_registration_load_textdomain()
```

**Retorno:** `void`


### DPS_Registration_Addon

Classe principal gerenciando formulário de registro de clientes/pets, confirmação por email, API endpoints e configurações.

**Arquivo:** `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`

**Padrão:** Singleton - use `DPS_Registration_Addon::get_instance()`

**Métodos públicos:** 20+

#### 🔧 get_instance()

**Método Estático** | **Desde:** 1.0.0

Retorna instância singleton do add-on.

**Assinatura:**

```php
DPS_Registration_Addon::get_instance()
```

**Retorno:** `DPS_Registration_Addon` Instância singleton.

---

#### 🔧 deactivate()

**Método Estático** | **Desde:** 1.0.0

Limpeza na desativação do plugin.

**Assinatura:**

```php
DPS_Registration_Addon::deactivate()
```

**Retorno:** `void`

**Descrição:** Limpa eventos cron agendados de lembretes de confirmação.

---

#### 🎯 activate()

**Método de Instância** | **Desde:** 1.0.0

Cria página de registro na ativação do plugin.

**Assinatura:**

```php
$addon->activate()
```

**Retorno:** `void`

**Descrição:** Cria página "Cadastro de Clientes e Pets" com shortcode `[dps_registration_form]` se não existir.

---

#### 🎯 register_settings()

**Método de Instância** | **Desde:** 1.0.0

Registra configurações WordPress para configuração do plugin.

**Assinatura:**

```php
$addon->register_settings()
```

**Retorno:** `void`

**Descrição:** Registra settings para Google Maps API, reCAPTCHA, templates de email, e configuração de API com callbacks de sanitização.

---

#### 🎯 render_settings_page()

**Método de Instância** | **Desde:** 1.0.0

Renderiza página de configurações no admin.

**Assinatura:**

```php
$addon->render_settings_page()
```

**Retorno:** `void`

**Descrição:** Exibe formulários de configuração para Google Maps API, reCAPTCHA, email, API, e seção de teste de email.

---

#### 🎯 render_pending_clients_page()

**Método de Instância** | **Desde:** 1.0.0

Renderiza lista de confirmações de clientes pendentes.

**Assinatura:**

```php
$addon->render_pending_clients_page()
```

**Retorno:** `void`

**Descrição:** Exibe tabela paginada de clientes com emails não confirmados, pesquisável por nome/telefone.

---

#### 🎯 register_rest_routes()

**Método de Instância** | **Desde:** 1.0.0

Registra endpoint REST API para registro.

**Assinatura:**

```php
$addon->register_rest_routes()
```

**Retorno:** `void`

**Descrição:** Registra endpoint `POST /dps/v1/register` com handlers de permissão e callback.

---

#### 🎯 rest_register_permission_check()

**Método de Instância** | **Desde:** 1.0.0

⚠️ **Segurança** - Valida chave API para endpoint REST de registro.

**Assinatura:**

```php
$addon->rest_register_permission_check($request)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$request` | `WP_REST_Request` | Objeto da requisição |

**Retorno:** `bool|WP_Error` True ou WP_Error.

**Descrição:** Verifica status de API habilitada e valida hash da chave API fornecida.

---

#### 🎯 handle_rest_register()

**Método de Instância** | **Desde:** 1.0.0

Processa registro de cliente via REST API.

**Assinatura:**

```php
$addon->handle_rest_register($request)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$request` | `WP_REST_Request` | Objeto da requisição |

**Retorno:** `WP_REST_Response|WP_Error` Resposta de sucesso com IDs ou erro.

**Descrição:** Valida rate limits, processa dados JSON de registro, cria cliente/pets, envia emails.

---

#### 🎯 maybe_handle_registration()

**Método de Instância** | **Desde:** 1.0.0

Processa submissão de formulário do frontend.

**Assinatura:**

```php
$addon->maybe_handle_registration()
```

**Retorno:** `void`

**Descrição:** Valida nonce, honeypot, rate limit, reCAPTCHA, CPF/telefone/email. Cria registros de cliente e pet. Trata duplicatas e opções de admin.

---

#### 🎯 maybe_handle_email_confirmation()

**Método de Instância** | **Desde:** 1.0.0

Processa confirmação de email via token na URL.

**Assinatura:**

```php
$addon->maybe_handle_email_confirmation()
```

**Retorno:** `void`

**Descrição:** Valida token (expiração de 48h), confirma email, ativa registro de cliente, redireciona em sucesso.

---

#### 🎯 render_registration_form()

**Método de Instância** | **Desde:** 1.0.0

Renderiza shortcode de formulário de registro multi-etapa.

**Assinatura:**

```php
$addon->render_registration_form()
```

**Retorno:** `string` HTML do formulário.

**Descrição:** Exibe formulário de 3 etapas (dados do cliente → pets → preferências de produtos) com template JavaScript para campos dinâmicos de pets. Mostra mensagens de sucesso quando aplicável.

---

#### 🎯 send_confirmation_reminders()

**Método de Instância** | **Desde:** 1.0.0

Envia emails de lembrete para clientes não confirmados após 24h.

**Assinatura:**

```php
$addon->send_confirmation_reminders()
```

**Retorno:** `void`

**Descrição:** Processa clientes pendentes em lote, envia lembretes via WhatsApp/email usando DPS_Communications_API.

---

#### 🎯 get_pet_fieldset_html()

**Método de Instância** | **Desde:** 1.0.0

Gera HTML para um único fieldset de pet.

**Assinatura:**

```php
$addon->get_pet_fieldset_html($index)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$index` | `int` | Número do pet (1, 2, 3, etc.) |

**Retorno:** `string` HTML do fieldset.

**Descrição:** Retorna fieldset com inputs para nome do pet, espécie, raça, tamanho, peso, pelagem, cor, data de nascimento, sexo, notas de cuidado, flag de agressividade.


### REST API

#### POST /dps/v1/register

**Autenticação:** Header `X-DPS-Registration-Key` (hash SHA-256)

**Body (JSON):**

```json
{
  "client_name": "João Silva",
  "client_phone": "11987654321",
  "client_email": "joao@example.com",
  "client_cpf": "12345678900",
  "pets": [
    {
      "name": "Rex",
      "species": "Cachorro",
      "breed": "Labrador"
    }
  ]
}
```

**Resposta de Sucesso (200):**

```json
{
  "success": true,
  "client_id": 123,
  "pets_created": 1
}
```

**Erros:**
- 401: API desabilitada ou chave inválida
- 429: Rate limit excedido
- 400: Validação falhou


### Shortcode

#### [dps_registration_form]

Exibe formulário multi-etapa de registro com todas as validações e estilização.

**Uso:**

```
[dps_registration_form]
```


### Constantes

- `RECAPTCHA_ACTION = 'dps_registration'` - Nome da ação para reCAPTCHA v3
- `TOKEN_EXPIRATION_SECONDS = 172800` - Validade do token de confirmação de email (48 horas)
- `CONFIRMATION_REMINDER_CRON = 'dps_registration_confirmation_reminder'` - Nome do hook cron


### Hooks Disparados

- `dps_registration_after_client_created` - Disparado após criação de cliente/pet


## 📦 STOCK ADD-ON

### Overview

Sistema de controle de inventário de insumos com dedução automática em agendamentos finalizados. Fornece CPT para itens de estoque, rastreamento de quantidades mínimas, alertas de estoque crítico e interface integrada ao painel principal do DPS.

**Diretório:** `plugins/desi-pet-shower-stock/`

**Arquivo principal:** `desi-pet-shower-stock.php`

**Versão:** 1.2.0


### Funções Globais

#### 📦 dps_stock_check_base_plugin()

**Função Global** | **Desde:** 1.0.0

Verifica se o plugin base DPS está ativo antes de carregar o add-on.

**Assinatura:**

```php
dps_stock_check_base_plugin()
```

**Retorno:** `bool` True se plugin base existe.

**Descrição:** Exibe aviso administrativo e retorna false se classe DPS_Base_Plugin não existe.

---

#### 📦 dps_stock_load_textdomain()

**Função Global** | **Desde:** 1.0.0

Carrega domínio de texto para traduções do Stock add-on.

**Assinatura:**

```php
dps_stock_load_textdomain()
```

**Retorno:** `void`

**Descrição:** Carrega traduções do diretório languages para domínio 'dps-stock-addon'.

---

#### 📦 dps_stock_init_addon()

**Função Global** | **Desde:** 1.0.0

Inicializa o Stock add-on após disparo do hook init.

**Assinatura:**

```php
dps_stock_init_addon()
```

**Retorno:** `void`

**Descrição:** Instancia classe DPS_Stock_Addon se existir; roda no hook init com prioridade 5.


### DPS_Stock_Addon

Classe principal gerenciando sistema de inventário, registro de CPT, integração de UI e dedução de estoque.

**Arquivo:** `plugins/desi-pet-shower-stock/desi-pet-shower-stock.php`

**Métodos públicos:** 11

#### 🎯 register_stock_cpt()

**Método de Instância** | **Desde:** 1.0.0

Registra custom post type para itens de estoque.

**Assinatura:**

```php
$addon->register_stock_cpt()
```

**Retorno:** `void`

**Descrição:** Registra CPT usando `DPS_CPT_Helper` para itens de estoque.

---

#### 🎯 register_meta_boxes()

**Método de Instância** | **Desde:** 1.0.0

Adiciona meta box 'dps_stock_details' ao CPT de estoque para edição.

**Assinatura:**

```php
$addon->register_meta_boxes()
```

**Retorno:** `void`

---

#### 🎯 render_stock_metabox()

**Método de Instância** | **Desde:** 1.0.0

Renderiza UI da metabox com campos de unidade, quantidade e quantidade mínima.

**Assinatura:**

```php
$addon->render_stock_metabox($post)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$post` | `WP_Post` | Post do item de estoque |

**Retorno:** `void`

---

#### 🎯 save_stock_meta()

**Método de Instância** | **Desde:** 1.0.0

Salva unidade, quantidade e valores mínimos em post meta com validação.

**Assinatura:**

```php
$addon->save_stock_meta($post_id, $post, $update)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$post_id` | `int` | ID do post |
| `$post` | `WP_Post` | Objeto do post |
| `$update` | `bool` | Se é atualização |

**Retorno:** `void`

---

#### 🎯 can_access_stock()

**Método de Instância** | **Desde:** 1.0.0

Verifica se usuário atual tem capability de gestão de estoque ou é admin.

**Assinatura:**

```php
$addon->can_access_stock()
```

**Retorno:** `bool` True se tem permissão.

---

#### 🎯 add_stock_tab()

**Método de Instância** | **Desde:** 1.0.0

Adiciona aba "Estoque" à navegação do dashboard principal.

**Assinatura:**

```php
$addon->add_stock_tab($visitor_only)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$visitor_only` | `bool` | Se em modo visitante |

**Retorno:** `void`

**Descrição:** Pula se em modo visitante.

---

#### 🎯 add_stock_section()

**Método de Instância** | **Desde:** 1.0.0

Renderiza seção de gestão de estoque no dashboard principal.

**Assinatura:**

```php
$addon->add_stock_section($visitor_only)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$visitor_only` | `bool` | Se em modo visitante |

**Retorno:** `void`

**Descrição:** Pula se em modo visitante.

---

#### 🎯 render_stock_page()

**Método de Instância** | **Desde:** 1.0.0

Retorna HTML completo para página de inventário de estoque.

**Assinatura:**

```php
$addon->render_stock_page()
```

**Retorno:** `string` HTML da página.

**Descrição:** Retorna página com estatísticas, alertas e tabela paginada de itens.

---

#### 🎯 maybe_handle_appointment_completion()

**Método de Instância** | **Desde:** 1.0.0

Deduz automaticamente estoque quando agendamento é finalizado.

**Assinatura:**

```php
$addon->maybe_handle_appointment_completion($appointment_id, $appointment_type)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$appointment_id` | `int` | ID do agendamento |
| `$appointment_type` | `string` | Tipo do agendamento |

**Retorno:** `void`

**Descrição:** Deduz estoque quando status se torna 'finalizado' ou 'finalizado_pago'.

---

#### 🔧 activate()

**Método Estático** | **Desde:** 1.0.0

Executa na ativação do plugin.

**Assinatura:**

```php
DPS_Stock_Addon::activate()
```

**Retorno:** `void`

**Descrição:** Garante que roles tenham capabilities, registra CPT, faz flush de rewrite rules.

---

#### 🔧 ensure_roles_have_capability()

**Método Estático** | **Desde:** 1.0.0

Concede capability 'dps_manage_stock' para roles administrator e dps_reception.

**Assinatura:**

```php
DPS_Stock_Addon::ensure_roles_have_capability()
```

**Retorno:** `void`


### Constantes

- `CPT = 'dps_stock_item'` - Custom post type para itens de estoque
- `ALERT_OPTION = 'dps_stock_alerts'` - Chave de option WordPress para alertas críticos
- `CAPABILITY = 'dps_manage_stock'` - Capability customizada para gestão de estoque


### Pontos de Integração

**Hooks WordPress Utilizados:**
- `dps_base_nav_tabs_after_history` - Adiciona aba de estoque à UI
- `dps_base_sections_after_history` - Adiciona seção de estoque à UI
- `dps_base_after_save_appointment` - Dispara dedução de estoque na finalização de agendamento


## 🔄 SUBSCRIPTION ADD-ON

### Overview

Sistema completo de assinaturas e planos recorrentes com geração automática de agendamentos, sincronização financeira, gerenciamento de status de pagamento e renovação manual. Suporta múltiplos ciclos de cobrança e integração com gateway de pagamento via hooks.

**Diretório:** `plugins/desi-pet-shower-subscription/`

**Arquivo principal:** `desi-pet-shower-subscription.php`

**Versão:** 1.0.0


### Funções Globais

#### 📦 dps_subscription_check_base_plugin()

**Função Global** | **Desde:** 1.0.0

Verifica se o plugin base DPS está ativo.

**Assinatura:**

```php
dps_subscription_check_base_plugin()
```

**Retorno:** `bool` True se plugin base existe.

**Descrição:** Verifica se classe DPS_Base_Plugin existe; exibe aviso administrativo e retorna false se ausente.

---

#### 📦 dps_subscription_load_textdomain()

**Função Global** | **Desde:** 1.0.0

Carrega arquivos de tradução para o subscription add-on.

**Assinatura:**

```php
dps_subscription_load_textdomain()
```

**Retorno:** `bool` Sucesso do carregamento.

**Descrição:** Registra domínio de texto 'dps-subscription-addon' com prioridade 1 (inicialização precoce).


### DPS_Subscription_Addon

Classe principal de implementação do add-on de assinaturas.

**Arquivo:** `plugins/desi-pet-shower-subscription/dps_subscription/desi-pet-shower-subscription-addon.php`

**Métodos públicos:** 8

#### 🎯 enqueue_assets()

**Método de Instância** | **Desde:** 1.0.0

Enfileira assets CSS/JS e localiza strings i18n para UI de gestão de assinaturas.

**Assinatura:**

```php
$addon->enqueue_assets()
```

**Retorno:** `void`

---

#### 🎯 register_subscription_cpt()

**Método de Instância** | **Desde:** 1.0.0

Registra custom post type 'dps_subscription' para armazenar dados de assinaturas.

**Assinatura:**

```php
$addon->register_subscription_cpt()
```

**Retorno:** `void`

---

#### 🎯 add_subscriptions_tab()

**Método de Instância** | **Desde:** 1.0.0

Adiciona aba de navegação "Assinaturas" à UI do plugin base.

**Assinatura:**

```php
$addon->add_subscriptions_tab($visitor_only)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$visitor_only` | `bool` | Se true, oculta aba de visitantes |

**Retorno:** `void`

---

#### 🎯 add_subscriptions_section()

**Método de Instância** | **Desde:** 1.0.0

Renderiza conteúdo da seção de assinaturas na UI do plugin base.

**Assinatura:**

```php
$addon->add_subscriptions_section($visitor_only)
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$visitor_only` | `bool` | Se true, oculta seção de visitantes |

**Retorno:** `void`

---

#### 🎯 maybe_handle_subscription_request()

**Método de Instância** | **Desde:** 1.0.0

Processa todas as ações de assinatura: save, cancel, restore, delete, renew e atualizações de status de pagamento (com validação de nonce).

**Assinatura:**

```php
$addon->maybe_handle_subscription_request()
```

**Retorno:** `void`

**Descrição:** Roteador central para operações de assinatura.

---

#### 🎯 handle_subscription_payment_status()

**Método de Instância** | **Desde:** 1.0.0

Atualiza status de pagamento de assinatura desde gateway de pagamento externo; sincroniza com módulo financeiro.

**Assinatura:**

```php
$addon->handle_subscription_payment_status($sub_id, $cycle_key = '', $payment_status = '')
```

**Parâmetros:**

| Nome | Tipo | Descrição |
|------|------|-----------|
| `$sub_id` | `int` | ID da assinatura |
| `$cycle_key` | `string` | Chave do ciclo (formato Y-m) |
| `$payment_status` | `string` | Status: paid\|failed\|pending |

**Retorno:** `void`

**Descrição:** Hook de integração: `dps_subscription_payment_status`

---

#### 🎯 maybe_sync_finance_on_save()

**Método de Instância** | **Desde:** 1.0.0

Sincroniza registros financeiros de assinatura após operações de salvamento.

**Assinatura:**

```php
$addon->maybe_sync_finance_on_save()
```

**Retorno:** `void`

**Descrição:** Método de compatibilidade para sincronização financeira.


### Hooks Registrados

**Action Hooks:**
- `dps_base_nav_tabs_after_pets` → `add_subscriptions_tab`
- `dps_base_sections_after_pets` → `add_subscriptions_section`
- `wp_enqueue_scripts` / `admin_enqueue_scripts` → `enqueue_assets`
- `dps_subscription_payment_status` → `handle_subscription_payment_status` (integração com gateway de pagamento)


### Integração com Payment Gateway

Para integrar gateway de pagamento, dispare o hook:

```php
do_action('dps_subscription_payment_status', $subscription_id, $cycle_key, $payment_status);
```

**Exemplo:**

```php
// Quando webhook do gateway recebe confirmação de pagamento
do_action('dps_subscription_payment_status', 123, '2024-01', 'paid');
```


---

## 📖 Best Practices

### Padrão Singleton
Todas as APIs principais seguem o padrão singleton:

```php
$api = DPS_Communications_API::get_instance();
$finance = DPS_Finance_API::get_instance();
```

### Validação de Segurança
SEMPRE valide nonce, capability e sanitize inputs:

```php
// Exemplo de validação completa
if (!wp_verify_nonce($_POST['nonce'], 'dps_action') || !current_user_can('manage_options')) {
    wp_die('Acesso negado');
}

$client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
```

### Uso de Helpers
Reutilize helpers globais em vez de duplicar código:

```php
// Formatar telefone
$formatted = DPS_Phone_Helper::format_for_whatsapp('11987654321');

// Converter moeda
$cents = DPS_Money_Helper::to_cents('R$ 85,50');
$display = DPS_Money_Helper::format_cents($cents);

// Construir URL
$edit_url = DPS_URL_Builder::build_edit_url('client', 123);
```

### Logging Condicional
Use funções de log apropriadas:

```php
// Base plugin
DPS_Logger::log('info', 'Operação concluída', ['user_id' => 123]);

// AI add-on
dps_ai_log_info('Processamento concluído');
dps_ai_log_error('Falha na API', ['error' => $e->getMessage()]);
```

### Hooks e Extensibilidade
Sempre dispare hooks para permitir extensões:

```php
// Antes de salvar
do_action('dps_before_save_appointment', $appointment_id, $data);

// Após salvar
do_action('dps_after_save_appointment', $appointment_id, $data);

// Filtros
$value = apply_filters('dps_appointment_value', $value, $appointment_id);
```

---

## 🔗 Additional Resources

- **ANALYSIS.md**: Arquitetura e fluxos de integração
- **CHANGELOG.md**: Histórico de versões e mudanças
- **AGENTS.md**: Diretrizes para desenvolvimento
- **docs/refactoring/**: Análises e padrões de refatoração
- **Código fonte**: Sempre consulte os arquivos originais para detalhes completos

---

**Fim da Documentação**

*Este documento é gerado automaticamente a partir dos arquivos fonte. Para correções ou adições, "
edite os docblocks nos arquivos PHP correspondentes.*


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

---

## 📝 Documentation Notes

### Coverage
This reference documents all major public APIs across the DPS ecosystem, including:
- ✅ All 16 add-ons (Communications, Finance, Client Portal, Push, AI, Agenda, Stats, Services, Backup, Booking, Groomers, Payment, Registration, Stock, Subscription)
- ✅ Base plugin helper classes (32+ classes, 148+ methods)
- ✅ Template functions, portal functions, and core utilities

### Quality Levels
- **Primary APIs**: Fully documented with complete parameter tables, return values, and examples (Communications, Finance, Portal, Push, AI, Agenda, Stats, Services)
- **Utility Methods**: Some base helper methods have abbreviated documentation; consult source files for complete details when needed

### Continuous Improvement
This is a living document. If you find missing or incomplete documentation for a specific method you need, please:
1. Check the source file indicated in the comment
2. Submit an issue or PR to improve the documentation
3. Contact: contato@probst.pro

