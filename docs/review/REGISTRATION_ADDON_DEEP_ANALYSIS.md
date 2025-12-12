# Análise Profunda — Add-on Registration (Cadastro Público)

**Plugin:** DPS by PRObst – Cadastro Add-on  
**Versão Analisada:** 1.0.1  
**Data da Análise:** 2024-12-12  
**Analista:** Copilot Coding Agent  
**Arquivos:** `desi-pet-shower-registration-addon.php` (737 linhas), `assets/css/registration-addon.css` (407 linhas), `uninstall.php` (43 linhas), `README.md` (227 linhas)

> **Regra de ouro**: Este documento cita apenas funcionalidades existentes no código. Nenhuma funcionalidade foi inventada.

---

## ÍNDICE

1. [MAPEAMENTO DO ADD-ON](#1-mapeamento-do-add-on)
2. [MAPA DE CONTRATOS](#2-mapa-de-contratos)
3. [FLUXOS DE CADASTRO](#3-fluxos-de-cadastro)
4. [VALIDAÇÃO E QUALIDADE DE DADOS](#4-validação-e-qualidade-de-dados)
5. [MODELAGEM E FONTE DA VERDADE](#5-modelagem-e-fonte-da-verdade)
6. [SEGURANÇA E CONTROLE DE ACESSO](#6-segurança-e-controle-de-acesso)
7. [UX, UI E ONBOARDING](#7-ux-ui-e-onboarding)
8. [INTEGRAÇÕES COM OUTROS ADD-ONS](#8-integrações-com-outros-add-ons)
9. [PERFORMANCE E ESCALABILIDADE](#9-performance-e-escalabilidade)
10. [AUDITORIA, LOGS E MANUTENÇÃO](#10-auditoria-logs-e-manutenção)
11. [ACHADOS](#11-achados)
12. [ROADMAP DE MELHORIAS EM FASES](#12-roadmap-de-melhorias-em-fases)

---

## 1. MAPEAMENTO DO ADD-ON

### 1.1 Localização e Estrutura de Arquivos

```
add-ons/desi-pet-shower-registration_addon/
├── desi-pet-shower-registration-addon.php   # Arquivo principal (737 linhas)
├── assets/
│   └── css/
│       └── registration-addon.css           # CSS responsivo (407 linhas)
├── README.md                                 # Documentação (227 linhas)
└── uninstall.php                            # Limpeza na desinstalação (43 linhas)
```

**Arquivos relacionados em outros diretórios:**
- `plugin/desi-pet-shower-base_plugin/includes/class-dps-tools-hub.php` (renderiza aba de configurações)
- `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php` (consome hooks)
- `add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php` (usa `dps_registration_page_id`)

### 1.2 Classe Principal

**Classe:** `DPS_Registration_Addon` (linhas 51-725)  
**Padrão:** Singleton (linhas 53-72)

| Método | Linhas | Responsabilidade | Complexidade Ciclomática |
|--------|--------|------------------|-------------------------|
| `get_instance()` | 67-72 | Singleton pattern | 2 |
| `__construct()` | 79-95 | Registra hooks | 1 |
| `enqueue_assets()` | 102-121 | Carrega CSS condicionalmente | 3 |
| `activate()` | 126-144 | Cria página na ativação | 3 |
| `add_settings_page()` | 152-161 | Registra submenu oculto | 1 |
| `register_settings()` | 166-172 | Registra option | 1 |
| `render_settings_page()` | 177-194 | Renderiza configurações | 2 |
| **`maybe_handle_registration()`** | 200-320 | **Processa POST** | **12** |
| `maybe_handle_email_confirmation()` | 325-355 | Confirma email via token | 4 |
| **`render_registration_form()`** | 362-579 | **Renderiza formulário** | **8** |
| `send_confirmation_email()` | 587-602 | Envia email | 1 |
| `get_registration_page_url()` | 609-619 | Retorna URL | 2 |
| `get_pet_fieldset_html()` | 627-673 | HTML de pet | 1 |
| `get_pet_fieldset_html_placeholder()` | 681-724 | Template JS | 1 |

### 1.3 Scripts e CSS

| Arquivo | Tipo | Tamanho | Carregamento |
|---------|------|---------|--------------|
| `registration-addon.css` | CSS | 407 linhas | `wp_enqueue_scripts` condicional |
| JavaScript inline | JS | ~40 linhas | Embutido no HTML (linhas 538-550) |
| Google Maps API | JS externo | - | Condicional (se API key configurada) |

**Problema identificado:** JavaScript inline não é cacheado, não é minificado, dificulta manutenção.

---

## 2. MAPA DE CONTRATOS

### 2.1 Hooks/Actions EXPOSTOS pelo Add-on

| Hook | Tipo | Parâmetros | Onde é disparado | Propósito |
|------|------|------------|------------------|-----------|
| `dps_registration_after_fields` | action | Nenhum | `render_registration_form()` linha 417 | Permitir add-ons adicionarem campos ao formulário |
| `dps_registration_after_client_created` | action | `$referral_code`, `$client_id`, `$client_email`, `$client_phone` | `maybe_handle_registration()` linha 264 | Notificar add-ons após criar cliente |

### 2.2 Filters EXPOSTOS pelo Add-on

| Filter | Parâmetros | Onde é disparado | Propósito |
|--------|------------|------------------|-----------|
| `dps_registration_spam_check` | `true`, `$_POST` | `maybe_handle_registration()` linha 213 | Validação anti-spam customizada (reCAPTCHA, Akismet) |

### 2.3 Hooks CONSUMIDOS pelo Add-on

| Hook | Prioridade | Callback | Propósito |
|------|------------|----------|-----------|
| `plugins_loaded` | 1 | Closure anônima | Verifica se plugin base está ativo |
| `init` | 1 | `dps_registration_load_textdomain()` | Carrega traduções |
| `init` | 5 | `dps_registration_init_addon()` | Inicializa singleton |
| `init` | 10 | `maybe_handle_registration()` | Processa POST do formulário |
| `init` | 10 | `maybe_handle_email_confirmation()` | Processa confirmação de email |
| `wp_enqueue_scripts` | 10 | `enqueue_assets()` | Carrega CSS |
| `admin_menu` | 20 | `add_settings_page()` | Registra menu oculto |
| `admin_init` | 10 | `register_settings()` | Registra options |

### 2.4 Shortcodes

| Shortcode | Callback | Parâmetros | Onde aparece |
|-----------|----------|------------|--------------|
| `[dps_registration_form]` | `render_registration_form()` | Nenhum | Página pública criada automaticamente |

### 2.5 Endpoints AJAX/REST

**Nenhum endpoint AJAX ou REST é registrado por este add-on.**

O formulário usa POST tradicional para `init` hook, não AJAX.

### 2.6 Options Utilizadas

| Option | Tipo | Descrição | Criado por | Usado por |
|--------|------|-----------|------------|-----------|
| `dps_registration_page_id` | int | ID da página de cadastro | `activate()` | Este add-on, Client Portal, Loyalty |
| `dps_google_api_key` | string | API key do Google Maps | Admin | `render_registration_form()` |

### 2.7 Modelo de Dados Tocado

| Entidade | Tipo | Operações | Método |
|----------|------|-----------|--------|
| `dps_cliente` | CPT | CREATE | `wp_insert_post()` linha 237 |
| `dps_pet` | CPT | CREATE | `wp_insert_post()` linha 296 |
| `wp_postmeta` | Meta | CREATE | `update_post_meta()` linhas 243-258, 302-312 |
| `wp_options` | Option | READ/WRITE | `get_option()`, `update_option()` |

**Nenhum `wp_user` é criado.** O cadastro cria apenas posts (CPTs), não usuários WordPress.

---

## 3. FLUXOS DE CADASTRO

### 3.1 Fluxo 1: Cadastro via Formulário Público (Principal)

**Iniciador:** Visitante anônimo  
**Entrada:** Página com shortcode `[dps_registration_form]`  
**Saída:** Posts `dps_cliente` + `dps_pet` criados, email de confirmação enviado

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FLUXO: CADASTRO VIA FORMULÁRIO PÚBLICO                   │
└─────────────────────────────────────────────────────────────────────────────┘

[1] Visitante acessa /cadastro/ (ou página com shortcode)
         │
         ▼
[2] WordPress renderiza shortcode [dps_registration_form]
    └── render_registration_form() linha 362
         │
         ▼
[3] Formulário HTML exibido com:
    ├── Campos do cliente (nome, CPF, telefone, email, etc.)
    ├── Campos do pet (nome, espécie, raça, porte, etc.)
    ├── Botão "Adicionar outro pet" (JavaScript)
    ├── Campo honeypot oculto
    └── Nonce CSRF
         │
         ▼
[4] Usuário preenche e submete (POST)
         │
         ▼
[5] WordPress hook 'init' dispara maybe_handle_registration() linha 200
         │
         ├── [5a] Verifica nonce → FALHA: return silencioso
         ├── [5b] Verifica honeypot → PREENCHIDO: return silencioso
         ├── [5c] Aplica filter dps_registration_spam_check → FALSE: return silencioso
         └── [5d] Valida client_name → VAZIO: return silencioso
         │
         ▼
[6] Sanitiza todos os campos (linhas 218-232)
    └── sanitize_text_field(), sanitize_email(), sanitize_textarea_field()
         │
         ▼
[7] Cria post dps_cliente (linha 237)
    └── wp_insert_post(['post_type' => 'dps_cliente', 'post_title' => $client_name])
         │
         ▼
[8] Popula metadados do cliente (linhas 243-258)
    ├── client_cpf, client_phone, client_email, client_birth
    ├── client_instagram, client_facebook, client_photo_auth
    ├── client_address, client_referral
    ├── client_lat, client_lng (coordenadas Google Maps)
    ├── dps_email_confirmed = 0
    └── dps_is_active = 0
         │
         ▼
[9] Se email informado → send_confirmation_email() linha 261
    ├── Gera UUID v4 como token
    ├── Salva em meta 'dps_email_confirm_token'
    └── Envia email com link de confirmação
         │
         ▼
[10] Dispara hook dps_registration_after_client_created (linha 264)
     └── Loyalty Add-on consome para registrar indicação
         │
         ▼
[11] Loop: Para cada pet_name[] não vazio (linhas 278-314)
     ├── Cria post dps_pet
     ├── Popula meta 'owner_id' = $client_id
     └── Popula demais metas (species, breed, size, weight, etc.)
         │
         ▼
[12] Redireciona para ?registered=1 (linha 317)
         │
         ▼
[13] Mensagem de sucesso exibida: "Cadastro realizado com sucesso!"
```

**Pontos de falha identificados:**
- [5a-5d] Retorno silencioso sem feedback ao usuário
- [6] Sanitização não valida formato (CPF, telefone, email)
- [7] Não verifica duplicatas antes de criar

---

### 3.2 Fluxo 2: Confirmação de Email

**Iniciador:** Cliente clicando link no email  
**Entrada:** URL com `?dps_confirm_email=TOKEN`  
**Saída:** Meta `dps_email_confirmed = 1`, `dps_is_active = 1`

```
[1] Cliente recebe email com link: /cadastro/?dps_confirm_email=UUID
         │
         ▼
[2] WordPress hook 'init' dispara maybe_handle_email_confirmation() linha 325
         │
         ▼
[3] Busca cliente por meta 'dps_email_confirm_token' = $token (linhas 331-341)
    └── get_posts() com meta_query
         │
         ├── NÃO ENCONTROU: return silencioso
         │
         ▼
[4] Atualiza metas (linhas 348-350):
    ├── dps_email_confirmed = 1
    ├── dps_is_active = 1
    └── delete_post_meta('dps_email_confirm_token')
         │
         ▼
[5] Redireciona para ?dps_email_confirmed=1
         │
         ▼
[6] Mensagem: "Email confirmado com sucesso! Seu cadastro está ativo."
```

---

### 3.3 Fluxo 3: Cadastro via Link de Indicação (Indique e Ganhe)

**Iniciador:** Visitante com link `?ref=CODIGO`  
**Diferença:** Campo de código de indicação pré-preenchido

```
[1] Indicador compartilha link: /cadastro/?ref=ABC123
         │
         ▼
[2] Indicado acessa link
         │
         ▼
[3] render_registration_form() é executado
         │
         ▼
[4] Loyalty Add-on injeta campo via hook 'dps_registration_after_fields' (linha 417)
    └── DPS_Loyalty_Referrals::render_registration_field() pré-preenche $_GET['ref']
         │
         ▼
[5] Indicado preenche formulário e submete
         │
         ▼
[6] maybe_handle_registration() processa
    └── $referral_code = sanitize_text_field($_POST['dps_referral_code'])
         │
         ▼
[7] Hook dps_registration_after_client_created disparado com $referral_code
         │
         ▼
[8] DPS_Loyalty_Referrals::maybe_register_referral() consome
    ├── Valida código
    ├── Busca indicador por código
    └── Cria registro em tabela dps_referrals (se válido)
```

---

### 3.4 Fluxo 4: Cadastro Manual pelo Admin

**STATUS: NÃO EXISTE FLUXO ESPECÍFICO**

O admin pode criar clientes/pets via:
1. Shortcode `[dps_base]` (plugin base) → seção "Clientes"
2. Interface admin nativa do CPT (se habilitada)

O Registration Add-on **não fornece** interface admin para cadastro manual.

---

### 3.5 Resumo de Dados Coletados

#### Cliente (dps_cliente)

| Campo | Input name | Tipo HTML | required HTML | Validação Backend | Meta key |
|-------|------------|-----------|---------------|-------------------|----------|
| Nome | `client_name` | text | ✅ | `if (!$client_name) return` | post_title |
| CPF | `client_cpf` | text | ❌ | `sanitize_text_field` apenas | `client_cpf` |
| Telefone | `client_phone` | text | ✅ | `sanitize_text_field` apenas | `client_phone` |
| Email | `client_email` | email | ❌ | `sanitize_email` apenas | `client_email` |
| Data nascimento | `client_birth` | date | ❌ | `sanitize_text_field` apenas | `client_birth` |
| Instagram | `client_instagram` | text | ❌ | `sanitize_text_field` apenas | `client_instagram` |
| Facebook | `client_facebook` | text | ❌ | `sanitize_text_field` apenas | `client_facebook` |
| Autoriza foto | `client_photo_auth` | checkbox | ❌ | `isset() ? 1 : 0` | `client_photo_auth` |
| Endereço | `client_address` | textarea | ❌ | `sanitize_textarea_field` apenas | `client_address` |
| Como conheceu | `client_referral` | text | ❌ | `sanitize_text_field` apenas | `client_referral` |
| Latitude | `client_lat` | hidden | ❌ | `sanitize_text_field` apenas | `client_lat` |
| Longitude | `client_lng` | hidden | ❌ | `sanitize_text_field` apenas | `client_lng` |

#### Pet (dps_pet)

| Campo | Input name | Tipo HTML | required HTML | Validação Backend | Meta key |
|-------|------------|-----------|---------------|-------------------|----------|
| Nome | `pet_name[]` | text | ❌ | `if (!$pname) continue` | post_title |
| Espécie | `pet_species[]` | select | ✅ | `sanitize_text_field` apenas | `pet_species` |
| Raça | `pet_breed[]` | text+datalist | ❌ | `sanitize_text_field` apenas | `pet_breed` |
| Porte | `pet_size[]` | select | ✅ | `sanitize_text_field` apenas | `pet_size` |
| Peso | `pet_weight[]` | number | ❌ | `sanitize_text_field` apenas | `pet_weight` |
| Pelagem | `pet_coat[]` | text | ❌ | `sanitize_text_field` apenas | `pet_coat` |
| Cor | `pet_color[]` | text | ❌ | `sanitize_text_field` apenas | `pet_color` |
| Data nascimento | `pet_birth[]` | date | ❌ | `sanitize_text_field` apenas | `pet_birth` |
| Sexo | `pet_sex[]` | select | ✅ | `sanitize_text_field` apenas | `pet_sex` |
| Cuidados | `pet_care[]` | textarea | ❌ | `sanitize_textarea_field` apenas | `pet_care` |
| Agressivo | `pet_aggressive[N]` | checkbox | ❌ | `isset() ? 1 : 0` | `pet_aggressive` |

---

## 4. VALIDAÇÃO E QUALIDADE DE DADOS

### 4.1 Campos Obrigatórios

#### Backend (real)

| Campo | Código | Resultado se falha |
|-------|--------|-------------------|
| `client_name` | `if ( ! $client_name ) return;` (linha 233) | Formulário não processado |
| (todos os outros) | Nenhuma verificação | Salvo vazio |

**PROBLEMA CRÍTICO**: Telefone tem `required` no HTML mas NÃO é verificado no backend. Usuário pode remover atributo via DevTools.

#### Frontend (bypass possível)

Todos os atributos `required` podem ser removidos via DevTools, tornando a validação frontend inútil para segurança.

### 4.2 Sanitização vs Validação

O código aplica **sanitização** mas não **validação**:

```php
// Sanitização aplicada (linhas 218-232):
$client_cpf = sanitize_text_field( $_POST['client_cpf'] ?? '' );
$client_phone = sanitize_text_field( $_POST['client_phone'] ?? '' );
$client_email = sanitize_email( $_POST['client_email'] ?? '' );
```

| Função | O que faz | O que NÃO faz |
|--------|-----------|---------------|
| `sanitize_text_field()` | Remove tags HTML, trim | Não valida formato (CPF, telefone) |
| `sanitize_email()` | Remove caracteres inválidos | Não verifica se é email válido |

### 4.3 Validações Ausentes (CRÍTICO)

| Campo | Validação Necessária | Código sugerido | Impacto se ausente |
|-------|---------------------|-----------------|-------------------|
| **CPF** | Algoritmo mod 11 | `preg_match('/^\d{11}$/', $cpf)` + verificador | CPFs falsos na base |
| **CNPJ** | Algoritmo mod 11 | Similar ao CPF | CNPJs falsos |
| **Telefone** | Regex BR | `preg_match('/^[1-9]\d{10,11}$/', $phone)` | WhatsApp não funciona |
| **Email** | `is_email()` | `if (!is_email($email))` | Confirmação não chega |
| **Data nascimento** | Não futuro | `strtotime($date) <= time()` | Datas impossíveis |
| **Peso** | Positivo | `(float)$weight > 0` | Pesos negativos |

### 4.4 Verificação de Duplicatas

**STATUS: NÃO IMPLEMENTADO**

```php
// Código atual (linha 237):
$client_id = wp_insert_post( [
    'post_type'   => 'dps_cliente',
    'post_title'  => $client_name,
    'post_status' => 'publish',
] );
// CRIA DIRETAMENTE SEM VERIFICAR DUPLICATAS
```

**Consequências**:
- Mesmo email cadastrado múltiplas vezes
- Histórico fragmentado entre registros duplicados
- Dificuldade em identificar cliente correto

### 4.5 Normalização de Dados

| Campo | Normalização | Status |
|-------|--------------|--------|
| Telefone | Remover caracteres não numéricos | ❌ Não implementado |
| CPF | Remover pontuação | ❌ Não implementado |
| Email | Lowercase | ✅ `sanitize_email()` faz implicitamente |
| Nome | Capitalizar | ❌ Não implementado |

**Recomendação**: Usar `DPS_Phone_Helper::format_for_whatsapp()` disponível no core.

---

## 5. MODELAGEM E FONTE DA VERDADE

### 5.1 Relação entre Entidades

```
┌────────────────┐
│   wp_users     │  ← NÃO UTILIZADO pelo Registration
│ (não criado)   │
└────────────────┘


┌────────────────┐         ┌────────────────┐
│  dps_cliente   │ 1 ───── N │   dps_pet      │
│    (CPT)       │         │    (CPT)       │
└────────────────┘         └────────────────┘
        │                          │
        │                          │
        ▼                          ▼
┌────────────────┐         ┌────────────────┐
│  wp_postmeta   │         │  wp_postmeta   │
│  client_*      │         │  pet_*         │
│  dps_email_*   │         │  owner_id      │
└────────────────┘         └────────────────┘
```

### 5.2 Fonte da Verdade por Dado

| Dado | Fonte da Verdade | Onde armazenado | Potencial divergência |
|------|------------------|-----------------|----------------------|
| Nome do cliente | `dps_cliente.post_title` | wp_posts | Nenhum |
| CPF | `client_cpf` meta | wp_postmeta | Nenhum |
| Email | `client_email` meta | wp_postmeta | Portal cria `wp_user` separado |
| Telefone | `client_phone` meta | wp_postmeta | Nenhum |
| Status ativo | `dps_is_active` meta | wp_postmeta | Nenhum |
| Vínculo pet→cliente | `owner_id` meta no pet | wp_postmeta | Nenhum |

### 5.3 Ponto de Divergência: User vs Cliente

O Registration **NÃO cria wp_user**. O Client Portal **cria wp_user** com mesmos dados.

```
Registration: dps_cliente → meta: client_email = "joao@email.com"
Portal Login: wp_user → user_email = "joao@email.com" (DUPLICADO)
```

**Risco**: Se cliente alterar email em um lugar, fica inconsistente.

**Recomendação**: Centralizar email em um único local (preferência: `dps_cliente`) e sincronizar.

---

## 6. SEGURANÇA E CONTROLE DE ACESSO

### 6.1 Proteção CSRF

**STATUS: ✅ IMPLEMENTADO CORRETAMENTE**

```php
// Renderização (linha 386):
wp_nonce_field( 'dps_reg_action', 'dps_reg_nonce' );

// Verificação (linhas 203-205):
if ( ! isset( $_POST['dps_reg_nonce'] ) || 
     ! check_admin_referer( 'dps_reg_action', 'dps_reg_nonce' ) ) {
    return;
}
```

### 6.2 Honeypot Anti-Bot

**STATUS: ✅ IMPLEMENTADO**

```php
// Campo oculto (linhas 387-390):
echo '<div class="dps-hp-field" aria-hidden="true" style="position:absolute; left:-9999px;">';
echo '<input type="text" name="dps_hp_field" tabindex="-1">';
echo '</div>';

// Verificação (linhas 207-210):
if ( ! empty( $_POST['dps_hp_field'] ) ) {
    return;
}
```

**Limitações**: Bots sofisticados ignoram honeypots simples.

### 6.3 Rate Limiting

**STATUS: ❌ NÃO IMPLEMENTADO**

Não existe proteção contra:
- Múltiplas submissões do mesmo IP
- Flood de cadastros
- Ataques automatizados

**Impacto**: Atacante pode criar milhares de registros falsos.

### 6.4 Token de Confirmação de Email (Threat Model)

| Aspecto | Implementação | Risco |
|---------|---------------|-------|
| **Geração** | `wp_generate_uuid4()` | ✅ Criptograficamente seguro |
| **Armazenamento** | Plaintext em `wp_postmeta` | ⚠️ Médio (DB access = token access) |
| **Expiração** | ❌ Não existe | 🔴 Alto (token válido para sempre) |
| **Single-use** | ✅ `delete_post_meta()` após uso | ✅ Adequado |
| **Revogação** | ❌ Não existe | ⚠️ Médio (admin não pode invalidar) |
| **Vazamento** | Token visível na URL | ⚠️ Médio (logs de servidor, referrer) |
| **Replay** | Mitigado por single-use | ✅ Adequado |

### 6.5 Permissões e Roles

| Operação | Quem pode | Verificação |
|----------|-----------|-------------|
| Submeter formulário | Qualquer visitante | Nenhuma (público) |
| Ver configurações | `manage_options` | `current_user_can()` linha 178 |
| Editar option | `manage_options` | WordPress Settings API |

**Não há risco de elevação de privilégio** pois não cria `wp_user`.

### 6.6 Enumeração de Contas

**Risco parcial**: Se implementar detecção de duplicatas, mensagem "email já cadastrado" permite atacante descobrir emails válidos.

**Mitigação sugerida**: Mensagem genérica "Verifique seu email" sempre.

### 6.7 LGPD / Dados Sensíveis

| Dado | Sensibilidade | Armazenamento | Log |
|------|---------------|---------------|-----|
| CPF | Alta | Plaintext em meta | ❌ Não logado |
| Email | Média | Plaintext em meta | ❌ Não logado |
| Telefone | Média | Plaintext em meta | ❌ Não logado |
| Endereço | Média | Plaintext em meta | ❌ Não logado |
| Token email | Média | Plaintext em meta | ❌ Não logado |

**Recomendações LGPD**:
1. Implementar política de retenção (excluir dados após X anos)
2. Permitir exportação de dados do cliente
3. Permitir exclusão a pedido (GDPR "right to be forgotten")

---

## 7. UX, UI E ONBOARDING

### 7.1 Experiência do Usuário Final (Tutor)

#### Formulário

| Aspecto | Status | Observação |
|---------|--------|------------|
| Layout responsivo | ✅ | Breakpoints 768/640/480px |
| Grid adaptativo | ✅ | 2 colunas desktop, 1 mobile |
| Adição de pets | ✅ | JavaScript funcional |
| Autocomplete endereço | ✅ | Google Places (se configurado) |
| Datalist de raças | ✅ | ~94 raças pré-populadas |
| Validação client-side | ❌ | Apenas `required` HTML |
| Máscaras de entrada | ❌ | CPF, telefone sem formatação |
| Indicador de loading | ❌ | Botão não indica processamento |
| Confirmação pré-envio | ❌ | Sem resumo antes de enviar |

#### Mensagens de Feedback

| Situação | Mensagem | Problema |
|----------|----------|----------|
| Sucesso | "Cadastro realizado com sucesso!" | Não menciona verificar email |
| Email confirmado | "Email confirmado com sucesso!" | ✅ Adequada |
| Nonce inválido | (silêncio) | Usuário não sabe o que aconteceu |
| Honeypot preenchido | (silêncio) | Usuário não sabe o que aconteceu |
| Spam check falhou | (silêncio) | Usuário não sabe o que aconteceu |
| Nome vazio | (silêncio) | Usuário não sabe o que aconteceu |

### 7.2 Experiência do Admin

#### Configurações

| Item | Status | Observação |
|------|--------|------------|
| Acesso via Hub | ✅ | DPS → Ferramentas → Formulário de Cadastro |
| Campos configuráveis | 1 (API Key) | Muito limitado |
| Preview do formulário | ❌ | Admin não vê como ficará |
| Estatísticas | ❌ | Sem métricas de cadastros |
| Gestão de pendentes | ❌ | Não mostra quem não confirmou |

#### Visualização de Clientes

Clientes criados pelo Registration aparecem na listagem geral de `dps_cliente` sem distinção de origem.

**Problemas**:
- Não há filtro "cadastros pendentes"
- Não há indicador visual de `dps_email_confirmed`
- Admin não sabe origem do cliente (manual vs público)

### 7.3 Onboarding Pós-Cadastro

**STATUS: INEXISTENTE**

Após cadastro, usuário:
1. Vê mensagem genérica
2. Recebe email (se informou)
3. **FIM** - Nenhuma orientação

**O que deveria existir**:
- Mensagem explicando verificar email
- Link para agendar primeiro atendimento
- Informações sobre Portal do Cliente
- Contato da equipe
- Prazo de validade do link

---

## 8. INTEGRAÇÕES COM OUTROS ADD-ONS

### 8.1 Loyalty Add-on (Fidelidade)

**Status**: ✅ Funcional

| Hook | Consumidor | Implementação |
|------|------------|---------------|
| `dps_registration_after_fields` | `DPS_Loyalty_Referrals::render_registration_field()` | Adiciona campo código indicação |
| `dps_registration_after_client_created` | `DPS_Loyalty_Referrals::maybe_register_referral()` | Registra em `dps_referrals` |

**Evidência**: `desi-pet-shower-loyalty.php` linhas 2349-2350

**Qualidade**: ⭐⭐⭐⭐⭐ (5/5) - Integração via hooks, desacoplada.

### 8.2 Client Portal Add-on

**Status**: ⚠️ Parcial

**Uso atual**:
```php
// class-dps-client-portal.php linha 2269:
$page_id = (int) get_option( 'dps_registration_page_id', 0 );
```

**O que está faltando**:
- ❌ Login automático após confirmação de email
- ❌ Link para Portal na mensagem de sucesso
- ❌ Token de acesso enviado junto com confirmação
- ❌ Reset de senha/primeiro acesso

### 8.3 Communications Add-on

**Status**: ❌ Sem integração

**O que deveria existir**:
- Hook após cadastro para enviar boas-vindas via WhatsApp
- Notificação para equipe sobre novo cadastro
- Template de email de confirmação customizável
- Lembrete para quem não confirmou

### 8.4 Agenda Add-on

**Status**: ❌ Sem integração

**O que poderia existir**:
- CTA para agendar primeiro atendimento após cadastro
- Sugestão de horários disponíveis

### 8.5 Finance Add-on

**Status**: N/A

Não há integração direta. A relação acontece via Loyalty (indicações).

### 8.6 Resumo de Integrações

| Add-on | Status | Tipo | Prioridade de Melhoria |
|--------|--------|------|------------------------|
| Loyalty | ✅ Funcional | Via hooks | Baixa |
| Client Portal | ⚠️ Parcial | Leitura de option | Alta |
| Communications | ❌ Ausente | - | Alta |
| Agenda | ❌ Ausente | - | Média |
| Finance | N/A | Indireto via Loyalty | N/A |

---

## 9. PERFORMANCE E ESCALABILIDADE

### 9.1 Operações por Cadastro

Para 1 cliente + 1 pet:

| Operação | Quantidade | Tipo | Custo |
|----------|------------|------|-------|
| `wp_insert_post()` | 2 | Write | Médio |
| `update_post_meta()` | ~23 | Write | Baixo cada, alto no total |
| `get_option()` | 2-3 | Read (cached) | Baixo |
| `wp_mail()` | 1 | I/O | Alto |
| `wp_generate_uuid4()` | 1 | CPU | Baixo |
| `wp_redirect()` | 1 | HTTP | - |

**Total: ~27 operações de escrita no banco**

### 9.2 Queries Potencialmente Lentas

```php
// Confirmação de email (linhas 331-341):
$client = get_posts( [
    'post_type'      => 'dps_cliente',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'   => 'dps_email_confirm_token',
            'value' => $token,
        ],
    ],
] );
```

**Avaliação**:
- ✅ `fields => 'ids'` reduz carga
- ✅ `posts_per_page => 1` limita
- ⚠️ `meta_query` sem índice pode ser lenta com muitos clientes

**Recomendação**: Criar índice em `meta_key = 'dps_email_confirm_token'` se volume > 10k clientes.

### 9.3 Escalabilidade

| Volume | Impacto | Recomendação |
|--------|---------|--------------|
| < 100/dia | ✅ Sem problemas | Manter atual |
| 100-500/dia | ⚠️ Meta queries lentas | Índices |
| > 500/dia | ❌ I/O pesado | Queue de emails, rate limiting |

---

## 10. AUDITORIA, LOGS E MANUTENÇÃO

### 10.1 Logs Existentes

**STATUS: NENHUM LOG IMPLEMENTADO**

Não existe registro de:
- Data/hora do cadastro
- IP de origem
- User-agent
- Tentativas de spam rejeitadas
- Erros de envio de email
- Confirmações de email

### 10.2 Hooks para Debug

| Hook | Utilidade |
|------|-----------|
| `dps_registration_spam_check` | Inspecionar dados submetidos |
| `dps_registration_after_client_created` | Verificar cliente criado |
| `dps_registration_after_fields` | Verificar campos adicionados |

### 10.3 Manutenibilidade

| Métrica | Valor | Avaliação |
|---------|-------|-----------|
| Linhas no arquivo principal | 737 | ⚠️ Alto para arquivo único |
| Métodos na classe | 14 | ✅ Adequado |
| Maior método | `render_registration_form()` ~217 linhas | ❌ Muito grande |
| Segundo maior | `maybe_handle_registration()` ~120 linhas | ⚠️ Grande |
| Duplicação | `get_pet_fieldset_html` vs `_placeholder` ~90% idênticos | ❌ Refatorar |
| DocBlocks | Presentes | ✅ Adequados |

### 10.4 Testabilidade

**Problemas**:
- Não há interface para testar sem UI (mock de formulário)
- Hooks permitem injeção de dados para teste
- `session_start()` pode conflitar em ambiente de teste

---

## 11. ACHADOS

### A1 - Ausência de Validação de CPF

| Campo | Valor |
|-------|-------|
| **Título** | CPF aceita qualquer texto sem validação de dígitos verificadores |
| **Severidade** | 🔴 Crítico |
| **Impacto** | Dados inválidos na base, impossível verificar cliente |
| **Evidência** | `desi-pet-shower-registration-addon.php:219` - `sanitize_text_field()` apenas |
| **Como reproduzir** | Submeter formulário com CPF "abc123" |
| **Sugestão** | Implementar algoritmo mod 11 para validação |
| **Risco de regressão** | Baixo se implementar como função isolada |
| **Teste recomendado** | Unitário: CPFs válidos passam, inválidos rejeitados |

### A2 - Sem Verificação de Duplicatas

| Campo | Valor |
|-------|-------|
| **Título** | Email/telefone/CPF podem ser cadastrados múltiplas vezes |
| **Severidade** | 🔴 Crítico |
| **Impacto** | Base fragmentada, histórico inconsistente, cliente não identificável |
| **Evidência** | `desi-pet-shower-registration-addon.php:237` - `wp_insert_post()` direto |
| **Como reproduzir** | Submeter mesmo email duas vezes |
| **Sugestão** | Query `meta_query` antes de criar para verificar unicidade |
| **Risco de regressão** | Médio (decidir: bloquear vs merge de registros) |
| **Teste recomendado** | E2E: Segunda submissão com mesmo email mostra erro |

### A3 - Sem Rate Limiting

| Campo | Valor |
|-------|-------|
| **Título** | Formulário pode ser submetido infinitamente sem bloqueio |
| **Severidade** | 🔴 Crítico |
| **Impacto** | Base poluída por bots, performance degradada |
| **Evidência** | Ausência de verificação em `maybe_handle_registration()` |
| **Como reproduzir** | Script curl em loop |
| **Sugestão** | Transient por IP, limite 3/hora |
| **Risco de regressão** | Baixo |
| **Teste recomendado** | Manual: 4ª submissão em 1 hora bloqueada |

### A4 - Token sem Expiração

| Campo | Valor |
|-------|-------|
| **Título** | Link de confirmação de email válido para sempre |
| **Severidade** | 🟡 Alto |
| **Impacto** | Links antigos funcionam indefinidamente |
| **Evidência** | `desi-pet-shower-registration-addon.php:588-589` - UUID sem timestamp |
| **Como reproduzir** | Usar link de confirmação de 1 ano atrás |
| **Sugestão** | Salvar timestamp junto, validar expiração 48h |
| **Risco de regressão** | Baixo |
| **Teste recomendado** | Unitário: Token de 49h atrás rejeitado |

### A5 - Retorno Silencioso em Erros

| Campo | Valor |
|-------|-------|
| **Título** | Falhas de validação não informam usuário |
| **Severidade** | 🟡 Alto |
| **Impacto** | UX ruim, usuário não sabe o que corrigir |
| **Evidência** | `desi-pet-shower-registration-addon.php:203-216` - `return;` sem mensagem |
| **Como reproduzir** | Submeter com honeypot preenchido |
| **Sugestão** | Usar `DPS_Message_Helper::add_error()` + redirect com query arg |
| **Risco de regressão** | Baixo |
| **Teste recomendado** | E2E: Mensagem de erro visível após falha |

### A6 - JavaScript Inline

| Campo | Valor |
|-------|-------|
| **Título** | ~40 linhas de JS embutidas no HTML |
| **Severidade** | 🟢 Baixo |
| **Impacto** | Não cacheado, não minificado, difícil manutenção |
| **Evidência** | `desi-pet-shower-registration-addon.php:538-550` |
| **Como reproduzir** | Inspecionar fonte da página |
| **Sugestão** | Mover para `assets/js/registration-addon.js` + `wp_enqueue_script()` |
| **Risco de regressão** | Baixo |
| **Teste recomendado** | Manual: Funcionalidade de adicionar pet funciona após refatoração |

### A7 - Duplicação de Código

| Campo | Valor |
|-------|-------|
| **Título** | `get_pet_fieldset_html()` e `get_pet_fieldset_html_placeholder()` ~90% idênticos |
| **Severidade** | 🟢 Baixo |
| **Impacto** | Manutenção dobrada, risco de divergência |
| **Evidência** | `desi-pet-shower-registration-addon.php:627-673` vs `681-724` |
| **Como reproduzir** | Comparar métodos |
| **Sugestão** | Unificar em método único com parâmetro `$index` |
| **Risco de regressão** | Baixo |
| **Teste recomendado** | Manual: Formulário renderiza corretamente |

### A8 - Uso de session_start()

| Campo | Valor |
|-------|-------|
| **Título** | PHP sessions podem conflitar com cache |
| **Severidade** | 🟡 Médio |
| **Impacto** | Comportamento imprevisível com plugins de cache |
| **Evidência** | `desi-pet-shower-registration-addon.php:364-366` |
| **Como reproduzir** | Usar com plugin de cache agressivo |
| **Sugestão** | Usar transients ou cookies diretamente |
| **Risco de regressão** | Médio (verificar todos os usos) |
| **Teste recomendado** | E2E: Formulário funciona com WP Super Cache ativo |

---

## 12. ROADMAP DE MELHORIAS EM FASES

### Fase 1 – Crítico / Segurança / Correções

**Prioridade**: 🔴 Alta  
**Estimativa Total**: 3-5 dias  
**Pré-requisito para**: Fases 2, 3, 4

| ID | Item | Prioridade | Esforço | Dependências | Critério de Aceite |
|----|------|------------|---------|--------------|-------------------|
| F1.1 | Validação de campos obrigatórios no backend | 🔴 Alta | P (1d) | Nenhuma | • Telefone vazio → erro exibido |
| F1.2 | Validação de CPF com algoritmo mod 11 | 🔴 Alta | P (1d) | Nenhuma | • CPF inválido → erro exibido<br>• CPF válido → aceito |
| F1.3 | Validação de telefone brasileiro | 🔴 Alta | P (1d) | `DPS_Phone_Helper` | • Telefone inválido → erro<br>• Normalizado para WhatsApp |
| F1.4 | Validação de email com `is_email()` | 🔴 Alta | P (0.5d) | Nenhuma | • Email inválido → erro |
| F1.5 | Detecção de duplicatas (email/telefone/CPF) | 🔴 Alta | M (2d) | F1.2, F1.3, F1.4 | • Segunda submissão com mesmo dado → mensagem específica |
| F1.6 | Rate limiting básico (3/hora por IP) | 🔴 Alta | P (1d) | Nenhuma | • 4ª submissão bloqueada com mensagem |
| F1.7 | Expiração de token de confirmação (48h) | 🟡 Média | P (0.5d) | Nenhuma | • Token de 49h → "link expirado" |
| F1.8 | Feedback de erro para usuário | 🟡 Média | P (1d) | `DPS_Message_Helper` | • Qualquer erro → mensagem visível |
| F1.9 | Normalização de telefone | 🟡 Média | P (0.5d) | `DPS_Phone_Helper` | • Telefone salvo sem pontuação |

**Benefícios**:
- **Tutor**: Sabe exatamente o que corrigir
- **Equipe**: Dados confiáveis para contato
- **Negócio**: Base limpa, WhatsApp funciona

---

### Fase 2 – UX & Onboarding

**Prioridade**: 🟡 Média  
**Estimativa Total**: 4-6 dias  
**Pré-requisito**: F1.1-F1.8

| ID | Item | Prioridade | Esforço | Dependências | Critério de Aceite |
|----|------|------------|---------|--------------|-------------------|
| F2.1 | Máscaras de entrada (CPF, telefone) | 🟡 Média | M (1.5d) | F1.2, F1.3 | • Campos formatados automaticamente |
| F2.2 | Validação client-side (JS) | 🟡 Média | M (2d) | F1.1-F1.4 | • Erros mostrados antes de submit |
| F2.3 | Mensagem de sucesso melhorada | 🟡 Média | P (0.5d) | Nenhuma | • Menciona "verifique seu email" |
| F2.4 | Indicador de loading no botão | 🟡 Média | P (0.5d) | Nenhuma | • Botão desabilitado + spinner |
| F2.5 | JavaScript em arquivo separado | 🟢 Baixa | P (1d) | Nenhuma | • JS cacheado pelo browser |
| F2.6 | Formulário multi-etapas (wizard) | 🟢 Baixa | G (3d) | F2.1, F2.2 | • Passo 1: Cliente<br>• Passo 2: Pet(s)<br>• Indicador de progresso |
| F2.7 | Resumo pré-envio | 🟢 Baixa | M (1d) | F2.6 | • Usuário confirma dados antes de enviar |
| F2.8 | Refatorar duplicação de código | 🟢 Baixa | P (0.5d) | Nenhuma | • Único método para fieldset de pet |
| F2.9 | Remover `session_start()` | 🟢 Baixa | P (0.5d) | Nenhuma | • Funciona com cache ativo |

**Benefícios**:
- **Tutor**: Experiência fluida, menos erros
- **Equipe**: Menos correções manuais
- **Negócio**: Maior taxa de conversão

---

### Fase 3 – Automação & Integrações

**Prioridade**: 🟡 Média  
**Estimativa Total**: 5-7 dias  
**Pré-requisito**: F1 completa, F2.3

| ID | Item | Prioridade | Esforço | Dependências | Critério de Aceite |
|----|------|------------|---------|--------------|-------------------|
| F3.1 | Notificação para admin (email) | 🟡 Média | P (1d) | Nenhuma | • Admin recebe email a cada cadastro |
| F3.2 | Integração com Communications (boas-vindas) | 🟡 Média | M (2d) | Communications Add-on | • WhatsApp automático após cadastro |
| F3.3 | Link automático para Portal | 🟡 Média | M (2d) | Client Portal | • Email de confirmação inclui link do Portal |
| F3.4 | Lembrete para não-confirmados | 🟡 Média | M (2d) | Communications | • Cron envia lembrete após 24h |
| F3.5 | Link para agendar primeiro atendimento | 🟢 Baixa | P (1d) | Agenda Add-on | • Mensagem de sucesso inclui CTA |
| F3.6 | Log de cadastros com `DPS_Logger` | 🟢 Baixa | P (1d) | Plugin base | • Cada cadastro registrado com timestamp |
| F3.7 | Filtro de pendentes no admin | 🟢 Baixa | M (1d) | Nenhuma | • Admin filtra por `dps_email_confirmed=0` |
| F3.8 | Indicador de origem do cliente | 🟢 Baixa | P (0.5d) | Nenhuma | • Meta `dps_registration_source=public` |

**Benefícios**:
- **Tutor**: Boas-vindas imediatas, sabe como acessar Portal
- **Equipe**: Notificados em tempo real
- **Negócio**: Maior engajamento, menos abandono

---

### Fase 4 – Recursos Avançados (Opcional)

**Prioridade**: 🟢 Baixa  
**Estimativa Total**: 7-10 dias  
**Pré-requisito**: F1 e F2 completas

| ID | Item | Prioridade | Esforço | Dependências | Critério de Aceite |
|----|------|------------|---------|--------------|-------------------|
| F4.1 | Cadastro via QR Code | 🟢 Baixa | M (2d) | Nenhuma | • QR gera link para cadastro |
| F4.2 | API REST para cadastro | 🟢 Baixa | G (3d) | F1 completa | • POST /wp-json/dps/v1/register |
| F4.3 | Pré-cadastro (salvar rascunho) | 🟢 Baixa | G (3d) | Nenhuma | • Usuário continua depois |
| F4.4 | Upload de foto do pet | 🟢 Baixa | M (2d) | Nenhuma | • Foto salva como attachment |
| F4.5 | Campos customizáveis pelo admin | 🟢 Baixa | G (4d) | Nenhuma | • Admin adiciona/remove campos |
| F4.6 | Integração reCAPTCHA v3 | 🟢 Baixa | M (2d) | Filter existente | • Score < 0.5 → rejeita |
| F4.7 | Template de email customizável | 🟢 Baixa | M (2d) | Communications | • Admin edita email de confirmação |
| F4.8 | Dashboard de cadastros | 🟢 Baixa | M (2d) | Stats Add-on | • Gráfico de cadastros por período |
| F4.9 | Convites personalizados | 🟢 Baixa | G (3d) | Communications | • Admin envia link único para cliente |

**Benefícios**:
- **Tutor**: Múltiplas formas de se cadastrar
- **Equipe**: Dados mais ricos, métricas
- **Negócio**: Vantagem competitiva, marketing

---

### Resumo do Roadmap

| Fase | Foco | Itens | Esforço Estimado | Impacto |
|------|------|-------|------------------|---------|
| **Fase 1** | Segurança & Validação | 9 | 3-5 dias | 🔴 Crítico |
| **Fase 2** | UX & Onboarding | 9 | 4-6 dias | 🟡 Alto |
| **Fase 3** | Automação & Integrações | 8 | 5-7 dias | 🟡 Alto |
| **Fase 4** | Recursos Avançados | 9 | 7-10 dias | 🟢 Médio |
| **Total** | - | 35 | 19-28 dias | - |

> **Nota**: Estimativas não incluem testes, QA e imprevistos. Adicione 30-50% de buffer.

---

### Diagrama de Dependências

```
┌───────────────────────────────────────────────────────────────────────┐
│                          FASE 1 (Obrigatória)                         │
│  F1.1 ─┬─ F1.5 (duplicatas)                                          │
│  F1.2 ─┤                                                              │
│  F1.3 ─┤                                                              │
│  F1.4 ─┘                                                              │
│  F1.6, F1.7, F1.8, F1.9 (independentes)                              │
└───────────────────────────┬───────────────────────────────────────────┘
                            │
                            ▼
┌───────────────────────────────────────────────────────────────────────┐
│                          FASE 2 (UX)                                  │
│  F2.1, F2.2 dependem de F1.2-F1.4                                    │
│  F2.6, F2.7 dependem de F2.1, F2.2                                   │
│  F2.3-F2.5, F2.8-F2.9 (independentes após F1)                        │
└───────────────────────────┬───────────────────────────────────────────┘
                            │
                            ▼
┌───────────────────────────────────────────────────────────────────────┐
│                     FASE 3 (Automação)                                │
│  Requer F1 completa + F2.3                                           │
│  F3.2, F3.4 dependem de Communications Add-on                        │
│  F3.3 depende de Client Portal Add-on                                │
└───────────────────────────┬───────────────────────────────────────────┘
                            │
                            ▼
┌───────────────────────────────────────────────────────────────────────┐
│                    FASE 4 (Opcional)                                  │
│  Pode começar após F1 + F2 (independente de F3)                      │
└───────────────────────────────────────────────────────────────────────┘
```

---

## CONCLUSÃO

O Registration Add-on é **funcional para uso básico**, mas apresenta **lacunas críticas** em validação de dados e proteção contra abuso que precisam ser endereçadas antes de escalar:

1. **Prioridade Imediata**: Fase 1 (validação + rate limiting + duplicatas)
2. **Curto Prazo**: Fase 2 (UX + máscaras + feedback)
3. **Médio Prazo**: Fase 3 (integrações com Portal e Communications)
4. **Longo Prazo**: Fase 4 (recursos diferenciados)

O código está razoavelmente organizado para arquivo único, mas seria beneficiado por:
- Separação em classes (Validator, FormRenderer, EmailHandler)
- JavaScript em arquivo separado
- Uso dos helpers do core (`DPS_Phone_Helper`, `DPS_Message_Helper`, `DPS_Request_Validator`)

**Este roadmap pode ser utilizado como base para planejamento de desenvolvimento futuro.**
