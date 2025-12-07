# Client Portal - Fase 1: Segurança e Autenticação Exclusiva por Token

**Data de Implementação:** 2024-12-07  
**Versão do Add-on:** 2.4.0  
**Autor:** Implementação Automatizada  
**Repositório:** richardprobst/DPS

---

## SUMÁRIO EXECUTIVO

A Fase 1 do Cliente Portal implementou melhorias críticas de segurança, removendo o login legado por usuário/senha e fortalecendo a validação de ownership em ações sensíveis. O portal agora opera EXCLUSIVAMENTE com autenticação por token (magic links), aumentando a segurança e simplificando o fluxo de acesso para clientes.

### Objetivos Alcançados

✅ **Login exclusivo por token implementado**  
✅ **Sistema de notificação de acessos criado**  
✅ **Validação centralizada de ownership aplicada**  
✅ **Depreciação do shortcode [dps_client_login] documentada**  
✅ **Nenhuma vulnerabilidade de segurança detectada**

---

## 1. REMOÇÃO DO LOGIN LEGADO POR USUÁRIO/SENHA

### 1.1 Shortcode [dps_client_login] Depreciado

**Arquivo Modificado:** `includes/class-dps-client-portal.php`

**Comportamento Anterior:**
- Exibia formulário de login com usuário/senha
- Usava `wp_signon()` para autenticar clientes
- Criava sessões WordPress tradicionais

**Comportamento Novo:**
- Exibe mensagem de depreciação com instruções claras
- Orienta cliente a usar apenas `[dps_client_portal]`
- Fornece link direto para a página do portal
- Registra uso do shortcode depreciado nos logs

**Exemplo de Mensagem Exibida:**

```
Método de Login Descontinuado

O login por usuário e senha não está mais disponível no Portal do Cliente.

Como acessar o portal agora:
1. Acesse a página do Portal do Cliente
2. Clique no botão "Quero acesso ao meu portal"
3. Aguarde nossa equipe enviar seu link exclusivo de acesso
4. Clique no link recebido para acessar automaticamente

[Ir para o Portal do Cliente]
```

### 1.2 Documentação no Código

Todas as referências ao login por usuário/senha foram documentadas como depreciadas:

```php
/**
 * Renderiza shortcode de login (DEPRECIADO)
 * 
 * ESTE SHORTCODE FOI DESCONTINUADO EM FAVOR DO LOGIN EXCLUSIVO POR TOKEN (MAGIC LINK)
 * 
 * O login por usuário/senha do Cliente Portal foi removido por questões de segurança
 * e usabilidade. O sistema agora utiliza EXCLUSIVAMENTE autenticação por token via
 * link único (magic link) enviado por WhatsApp ou e-mail.
 * 
 * @deprecated 2.4.0 Use apenas autenticação por token via [dps_client_portal]
 * @return string Mensagem de depreciação
 */
```

### 1.3 Plano de Remoção Completa

**Versão Alvo:** 3.0.0 (Breaking Change)

**Ações Previstas:**
- Remover completamente o método `render_login_shortcode()`
- Remover registro do shortcode `[dps_client_login]`
- Remover lógica de compatibilidade com usuários WP antigos
- Atualizar documentação refletindo apenas autenticação por token

---

## 2. SISTEMA DE NOTIFICAÇÃO DE ACESSOS

### 2.1 Objetivo

Aumentar a segurança e transparência notificando o cliente sempre que o portal é acessado. Permite que o cliente identifique acessos não autorizados rapidamente.

### 2.2 Implementação

**Arquivo:** `includes/class-dps-client-portal.php`  
**Método:** `send_access_notification()`

**Fluxo de Notificação:**

1. Cliente autentica via token (magic link)
2. Sistema valida token e cria sessão
3. **Notificação enviada automaticamente** se habilitada
4. Cliente recebe e-mail informativo

**Conteúdo do E-mail:**

```
Assunto: Acesso ao Portal - [Nome do Site]

Olá [Nome do Cliente],

Detectamos um acesso ao seu Portal do Cliente.

Data/Hora: 07/12/2024 17:45
IP: 192.168.***

Se você reconhece este acesso, pode ignorar esta mensagem. Ela é apenas uma 
notificação de segurança para mantê-lo informado.

⚠️ IMPORTANTE: Se você NÃO realizou este acesso, entre em contato com nossa 
equipe IMEDIATAMENTE. Pode ser que alguém tenha obtido seu link de acesso 
indevidamente.

Atenciosamente,
Equipe [Nome do Site]
```

### 2.3 Configuração

**Localização:** Portal do Cliente → Configurações → Notificações de Segurança

**Opção Adicionada:**
- ☑ Enviar e-mail ao cliente quando o portal for acessado

**Comportamento Padrão:** Desativado (opt-in para evitar spam)

### 2.4 Privacidade

- **IP Parcialmente Ofuscado:** Apenas primeiros 2 octetos visíveis (ex: 192.168.***)
- **Suporta apenas IPv4 atualmente** (IPv6 documentado como limitação)

### 2.5 Integração

**Com DPS_Communications_API (preferencial):**
```php
if ( class_exists( 'DPS_Communications_API' ) ) {
    $comm_api = DPS_Communications_API::get_instance();
    $sent = $comm_api->send_email( $client_email, $subject, $body, [
        'client_id' => $client_id,
        'type'      => 'portal_access_notification',
    ] );
}
```

**Fallback para wp_mail():**
```php
$sent = wp_mail( $client_email, $subject, $body );
```

### 2.6 Extensibilidade

**Hooks Disponíveis:**

```php
// Controlar se notificação deve ser enviada
apply_filters( 'dps_portal_access_notification_enabled', $enabled, $client_id );

// Customizar assunto do e-mail
apply_filters( 'dps_portal_access_notification_subject', $subject, $client_id );

// Customizar corpo do e-mail
apply_filters( 'dps_portal_access_notification_body', $body, $client_id, $access_date, $ip_obfuscated );

// Ação após envio (para notificações complementares como WhatsApp)
do_action( 'dps_portal_access_notification_sent', $client_id, $sent, $access_date, $ip_address );
```

**Exemplo de Uso:**

```php
// Enviar também via WhatsApp quando disponível
add_action( 'dps_portal_access_notification_sent', function( $client_id, $sent, $access_date, $ip ) {
    if ( ! $sent || ! class_exists( 'DPS_Communications_API' ) ) {
        return;
    }
    
    $phone = get_post_meta( $client_id, 'client_phone', true );
    if ( ! $phone ) {
        return;
    }
    
    $message = sprintf(
        'Detectamos um acesso ao seu Portal do Cliente em %s. Se não foi você, entre em contato imediatamente.',
        $access_date
    );
    
    $comm = DPS_Communications_API::get_instance();
    $comm->send_whatsapp( $phone, $message, [
        'type'      => 'portal_access_alert',
        'client_id' => $client_id,
    ] );
}, 10, 4 );
```

---

## 3. VALIDAÇÃO CENTRALIZADA DE OWNERSHIP

### 3.1 Motivação

Antes da Fase 1, validações de ownership estavam espalhadas pelo código com lógica duplicada e inconsistente. Alguns endpoints não validavam ownership, criando risco de acesso cross-client.

### 3.2 Helper Centralizado

**Arquivo:** `includes/functions-portal-helpers.php`  
**Função:** `dps_portal_assert_client_owns_resource()`

**Tipos de Recursos Suportados:**

| Tipo | Meta Key Validado | Exemplo de Uso |
|------|------------------|----------------|
| `appointment` | `appointment_client_id` | Validar antes de gerar .ics |
| `pet` | `owner_id` | Validar antes de atualizar dados |
| `message` | `message_client_id` | Validar antes de exibir mensagem |
| `transaction` | `transaction_client_id` | Validar antes de gerar link de pagamento |
| `client` | Compara IDs diretamente | Validar antes de atualizar dados próprios |

### 3.3 Assinatura da Função

```php
/**
 * Valida se um recurso pertence ao cliente autenticado
 * 
 * @param int    $client_id   ID do cliente autenticado no portal
 * @param int    $resource_id ID do recurso a ser validado
 * @param string $type        Tipo do recurso (appointment, pet, message, transaction, client)
 * @return bool True se o recurso pertence ao cliente, false caso contrário
 */
function dps_portal_assert_client_owns_resource( $client_id, $resource_id, $type ) {
    // ... implementação
}
```

### 3.4 Exemplos de Aplicação

**Antes (código duplicado):**

```php
// Em download de .ics
$appt_client_id = get_post_meta( $appointment_id, 'appointment_client_id', true );
if ( absint( $appt_client_id ) !== $client_id ) {
    wp_die( 'Acesso negado' );
}

// Em atualização de pet (padrão diferente!)
$owner_id = absint( get_post_meta( $pet_id, 'owner_id', true ) );
if ( $owner_id === $client_id ) {
    // ... atualiza
}
```

**Depois (centralizado):**

```php
// Em download de .ics
if ( ! dps_portal_assert_client_owns_resource( $client_id, $appointment_id, 'appointment' ) ) {
    wp_die( __( 'Você não tem permissão para baixar este arquivo.', 'dps-client-portal' ) );
}

// Em atualização de pet
if ( ! dps_portal_assert_client_owns_resource( $client_id, $pet_id, 'pet' ) ) {
    $redirect_url = add_query_arg( 'portal_msg', 'error', $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
```

### 3.5 Segurança Adicional

**Logs Automáticos de Acesso Negado:**

```php
if ( ! $is_owner ) {
    DPS_Logger::log( 'warning', 'Portal ownership validation: acesso negado', [
        'client_id'   => $client_id,
        'resource_id' => $resource_id,
        'type'        => $type,
        'ip'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
    ] );
}
```

Isso permite auditoria de tentativas de acesso cross-client para identificar padrões suspeitos.

### 3.6 Extensibilidade

**Filtros Disponíveis:**

```php
// Pre-check: permite validação customizada antes da lógica padrão
apply_filters( 'dps_portal_pre_ownership_check', null, $client_id, $resource_id, $type );

// Post-check: permite modificar resultado final
apply_filters( 'dps_portal_ownership_validated', $is_owner, $client_id, $resource_id, $type );
```

**Exemplo de Extensão para Novo Tipo:**

```php
// Adicionar suporte para tipo 'invoice' (Finance Add-on)
add_filter( 'dps_portal_pre_ownership_check', function( $pre_check, $client_id, $resource_id, $type ) {
    if ( $type !== 'invoice' ) {
        return $pre_check;
    }
    
    // Busca na tabela custom do Finance
    global $wpdb;
    $found = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->prefix}dps_invoices WHERE id = %d AND client_id = %d",
        $resource_id,
        $client_id
    ) );
    
    return (bool) $found;
}, 10, 4 );
```

### 3.7 Aplicações Implementadas

**Fase 1 aplicou ownership em:**

1. **Download de .ics (agendamentos)**
   - Arquivo: `desi-pet-shower-client-portal.php`
   - Handler: `dps_client_portal_handle_ics_download()`

2. **Atualização de dados de pets**
   - Arquivo: `includes/class-dps-client-portal.php`
   - Método: `handle_portal_actions()` (ação `update_pet`)

**Já tinham ownership implícito:**

- **Mensagens do chat:** Query filtrada por `meta_value => $client_id`
- **Atualização de dados do cliente:** Opera sobre o próprio `$client_id`

**Mantido com lógica própria:**

- **Transações financeiras:** Usa tabela custom `dps_transacoes`, método `transaction_belongs_to_client()` mantido

---

## 4. IMPACTO E BENEFÍCIOS

### 4.1 Segurança

**Antes da Fase 1:**
- Login por usuário/senha exposto a ataques de força bruta
- Sem notificação de acessos (cliente não sabia quando portal foi acessado)
- Validação de ownership inconsistente (risco de acesso cross-client)

**Após a Fase 1:**
- ✅ Apenas magic links (tokens temporários de 30 min)
- ✅ Notificação automática de acessos
- ✅ Validação centralizada e auditada
- ✅ Logs de tentativas de acesso indevido

### 4.2 Experiência do Usuário

**Para o Cliente:**
- ✅ Não precisa lembrar senha
- ✅ Acesso mais rápido (clica no link e já está logado)
- ✅ Maior sensação de segurança (recebe notificação de acessos)

**Para a Equipe:**
- ✅ Menos chamados de "esqueci minha senha"
- ✅ Processo de envio de link já familiar (via WhatsApp/e-mail)
- ✅ Controle via dashboard administrativo

### 4.3 Manutenibilidade

**Código:**
- ✅ Validação de ownership em um único lugar
- ✅ Menos duplicação de lógica
- ✅ Mais fácil adicionar novos tipos de recursos

**Documentação:**
- ✅ DocBlocks detalhados com exemplos
- ✅ Hooks documentados para extensibilidade
- ✅ Guia de migração para v3.0.0

---

## 5. ARQUIVOS MODIFICADOS

### 5.1 Add-on Cliente Portal

```
add-ons/desi-pet-shower-client-portal_addon/
├── desi-pet-shower-client-portal.php
│   └── Versão: 2.3.0 → 2.4.0
│   └── Descrição: Atualizada para refletir autenticação exclusiva por token
│   └── Handler de .ics: Usa helper de ownership
│
├── includes/
│   ├── class-dps-client-portal.php
│   │   └── Método: render_login_shortcode() - Depreciado
│   │   └── Método: handle_token_authentication() - Chama send_access_notification()
│   │   └── Método: send_access_notification() - NOVO (linhas 2319-2440)
│   │   └── Método: handle_portal_actions() - Usa helper de ownership para pets
│   │   └── Método: handle_portal_settings_save() - Salva configuração de notificação
│   │
│   └── functions-portal-helpers.php
│       └── Função: dps_portal_assert_client_owns_resource() - NOVA (linhas 119-229)
│
└── templates/
    └── portal-settings.php
        └── Seção: Notificações de Segurança - NOVA (linhas 67-84)
```

### 5.2 Documentação

```
CHANGELOG.md
└── [Unreleased]
    ├── Added: Notificação de acesso, Helper de ownership
    └── Deprecated: Shortcode [dps_client_login]

docs/implementation/
└── CLIENT_PORTAL_PHASE1_SUMMARY.md - NOVO (este documento)
```

---

## 6. PRÓXIMAS FASES

### Fase 2 (Planejada)

**Melhorias no Fluxo de Token:**
- Intermediário GET → POST para reduzir exposição de token na URL
- Auto-submit form com token em campo hidden
- Melhoria de logging de tokens

**Outras Melhorias:**
- Suporte a IPv6 na ofuscação de IP
- Migração de validação de transações para helper centralizado
- Estatísticas de notificações enviadas no admin

### Fase 3 (v3.0.0 - Breaking Changes)

**Remoção Completa de Código Legado:**
- Remover shortcode `[dps_client_login]`
- Remover fallback de compatibilidade com usuários WP
- Limpar código de métodos depreciados

---

## 7. REFERÊNCIAS

### Documentos Relacionados

- **ANALYSIS.md** - Arquitetura geral do DPS
- **AGENTS.md** - Políticas de versionamento e convenções
- **CHANGELOG.md** - Histórico completo de mudanças
- **TOKEN_AUTH_SYSTEM.md** - Documentação do sistema de autenticação por token

### Commits da Fase 1

1. `e4dd160` - Implement Phase 1: Deprecate legacy login, add ownership validation, and access notifications
2. `a8a3b69` - Add ownership validation to pet updates and update CHANGELOG with Phase 1 changes
3. `fa37ab6` - Address code review feedback: Fix IP obfuscation comment and translator note

### Code Review

**Status:** ✅ Aprovado com observações

**Observações Não-Críticas:**
- IPv6 não suportado na ofuscação de IP (documentado)
- Validação de transações usa método próprio (por design, tabela custom)

### CodeQL Scan

**Status:** ✅ Nenhuma vulnerabilidade detectada

**Linguagens Analisadas:** PHP, JavaScript

---

## 8. COMO USAR

### 8.1 Para Desenvolvedores

**Aplicar Validação de Ownership em Novo Endpoint:**

```php
// 1. Obter cliente autenticado
$portal = DPS_Client_Portal::get_instance();
$client_id = $portal->get_current_client_id();

if ( ! $client_id ) {
    wp_die( __( 'Você precisa estar autenticado.', 'dps-client-portal' ) );
}

// 2. Validar ownership do recurso
$resource_id = absint( $_GET['resource_id'] );
if ( ! dps_portal_assert_client_owns_resource( $client_id, $resource_id, 'appointment' ) ) {
    wp_die( __( 'Você não tem permissão para acessar este recurso.', 'dps-client-portal' ) );
}

// 3. Processar ação
// ...
```

**Adicionar Notificação Customizada:**

```php
add_action( 'dps_portal_access_notification_sent', function( $client_id, $sent, $access_date ) {
    if ( ! $sent ) {
        return; // E-mail falhou, não enviar WhatsApp
    }
    
    // Envia confirmação via WhatsApp também
    // ...
}, 10, 3 );
```

### 8.2 Para Administradores

**Ativar Notificações de Acesso:**

1. Acesse: `Portal do Cliente → Configurações`
2. Localize seção: `Notificações de Segurança`
3. Marque: ☑ Enviar e-mail ao cliente quando o portal for acessado
4. Clique: `Salvar Configurações`

**Migrar de [dps_client_login]:**

1. Localize páginas usando `[dps_client_login]`
2. Substitua por: `[dps_client_portal]`
3. Oriente clientes a solicitarem links de acesso
4. Remova usuários WordPress de clientes (se desejar)

---

**Fim do Documento**

**Versão:** 1.0  
**Data:** 2024-12-07  
**Status:** ✅ Implementado e Testado
