# Mapeamento Completo – Back-End e Front-End
## Sistema desi.pet by PRObst (DPS)

**Data da Análise**: 2025-11-22  
**Baseado em**: Código-fonte real (não documentação)

---

## RESUMO EXECUTIVO

O sistema DPS consiste em:
- **1 plugin base** (`desi-pet-shower-base_plugin`)
- **14 add-ons** que estendem funcionalidades
- **Problemas identificados**: duplicações de arquivos, funções e lógica espalhada

---

## 1. FUNCIONALIDADES DO BACK-END (ADMIN)

### 1.1 Menus e Submenus Administrativos

#### Plugin Base
- **NÃO possui menus administrativos próprios**
- Apenas expõe hooks para add-ons adicionarem menus

#### DPS Logs (Base Plugin)
| Menu | Slug | Capability | Arquivo | Callback |
|------|------|-----------|---------|----------|
| DPS Logs | `dps-logs` | `manage_options` | `class-dps-logs-admin-page.php` | `render_logs_page()` |

- **Localização**: `plugins/desi-pet-shower-base/includes/class-dps-logs-admin-page.php`
- **Hook**: `admin_menu`
- **Funcionalidade**: Visualização de logs do sistema com filtros por nível e período

#### Loyalty Add-on
| Menu | Slug | Capability | Arquivo | Callback |
|------|------|-----------|---------|----------|
| DPS Fidelidade | `dps-loyalty` | `manage_options` | `desi-pet-shower-loyalty.php:175` | Menu principal |
| └─ Recompensas | `dps-loyalty` | `manage_options` | `desi-pet-shower-loyalty.php:185` | Submenu |
| └─ Logs | `dps-loyalty-logs` | `manage_options` | `desi-pet-shower-loyalty.php:194` | Submenu |

#### Client Portal Add-on
| Menu | Slug | Capability | Arquivo | Callback |
|------|------|-----------|---------|----------|
| Logins de Clientes | `dps-client-logins` | `manage_options` | `class-dps-client-portal.php:1206` | Submenu em "Configurações" |

**NOTA**: O Client Portal comentou a criação do menu admin e usa hooks do base para adicionar abas.

#### Registration Add-on
| Menu | Slug | Capability | Arquivo | Callback |
|------|------|-----------|---------|----------|
| DPS Cadastro | `dps-registration-settings` | `manage_options` | `desi-pet-shower-registration-addon.php:63` | Submenu em "Configurações" |

- **Funcionalidade**: Configuração de API Key do Google Maps

### 1.2 Formulários do Admin

**NENHUM formulário administrativo tradicional identificado.**

O sistema usa inteiramente shortcodes no front-end para administração, sem painéis WP_Admin tradicionais de CRUD.

### 1.3 Custom Post Types (CPTs)

| CPT | Registrado em | Labels | show_ui | Uso |
|-----|---------------|--------|---------|-----|
| `dps_cliente` | Base Plugin | "Clientes" | `false` | Cadastro de clientes/tutores |
| `dps_pet` | Base Plugin | "Pets" | `false` | Cadastro de animais |
| `dps_agendamento` | Base Plugin | "Agendamentos" | `false` | Agendamentos de banho/tosa |
| `dps_subscription` | Subscription Add-on | "Assinaturas" | N/A | Pacotes mensais |
| `dps_portal_message` | Client Portal | "Mensagens Portal" | N/A | Mensagens para clientes |

**PROBLEMA IDENTIFICADO**: 
- CPTs com `show_ui => false` significa que **não aparecem no admin WordPress**
- Todo gerenciamento é via shortcodes front-end
- Não há interface WP_Admin nativa para edição

### 1.4 Hooks Utilizados no Admin

#### Hooks do Core WordPress usados:
- `admin_menu` - Registro de menus (apenas Logs e add-ons específicos)
- `admin_enqueue_scripts` - Carregamento de assets (apenas base plugin)
- `save_post_dps_cliente` - Criação de login para cliente
- `save_post_dps_agendamento` - Versionamento de agendamentos
- `save_post_dps_pet` - Limpeza de cache
- `before_delete_post` - Prevenção de exclusão órfã
- `pre_get_posts` - Filtro de exclusão lógica

#### Hooks Customizados DPS:
**Nenhum hook custom foi encontrado sendo disparado (fired) no código.**

Os add-ons se conectam ao base usando hooks de navegação e seções:
- `dps_settings_nav_tabs` - Para adicionar abas
- `dps_settings_sections` - Para adicionar seções

### 1.5 Scripts e Estilos Carregados no Admin

| Asset | Handle | Onde Carrega | Arquivo |
|-------|--------|--------------|---------|
| CSS Admin | `dps-admin-style` | Páginas com slug `dps` | `assets/css/dps-admin.css` |

**Localização do hook**: `plugins/desi-pet-shower-base/desi-pet-shower-base.php:267`

```php
public function enqueue_admin_assets( $hook ) {
    $is_dps_page = in_array( $hook, $dps_admin_pages, true ) || strpos( $hook, 'dps' ) !== false;
    if ( ! $is_dps_page ) {
        return;
    }
    wp_enqueue_style( 'dps-admin-style', DPS_BASE_URL . 'assets/css/dps-admin.css', [], DPS_BASE_VERSION );
}
```

---

## 2. FUNCIONALIDADES DO FRONT-END

### 2.1 Shortcodes Registrados

| Shortcode | Add-on | Callback | Arquivo | Funcionalidade |
|-----------|--------|----------|---------|----------------|
| `[dps_base]` | Base | `DPS_Base_Frontend::render_app()` | `desi-pet-shower-base.php:70` | **APLICAÇÃO PRINCIPAL** - CRUD clientes, pets, agendamentos |
| `[dps_configuracoes]` | Base | `DPS_Base_Frontend::render_settings()` | `desi-pet-shower-base.php:71` | Configurações e abas extensíveis |
| `[dps_fin_docs]` | Finance | `render_fin_docs_shortcode()` | `desi-pet-shower-finance-addon.php:96` | Documentos financeiros |
| `[dps_agenda_page]` | Agenda | `render_agenda_shortcode()` | `desi-pet-shower-agenda-addon.php:27` | Visualização de agenda |
| `[dps_charges_notes]` | Agenda | `render_charges_notes_shortcode()` | `desi-pet-shower-agenda-addon.php:28` | Cobranças e notas |
| `[dps_client_portal]` | Client Portal | `render_portal_shortcode()` | `class-dps-client-portal.php:58` | **PORTAL DO CLIENTE** |
| `[dps_client_login]` | Client Portal | `render_login_shortcode()` | `class-dps-client-portal.php:60` | Login de cliente |
| `[dps_registration_form]` | Registration | `render_registration_form()` | `desi-pet-shower-registration-addon.php:28` | Formulário público de cadastro |

### 2.2 Templates Front-End

**Template identificado**:
- `plugins/desi-pet-shower-base/templates/appointments-list.php`
  - Renderiza lista de agendamentos

**Método de carregamento**:
```php
// Em class-dps-base-frontend.php
include DPS_BASE_DIR . 'templates/appointments-list.php';
```

**Padrão**: HTML é gerado inline dentro dos métodos PHP, SEM uso de sistema de templates.

### 2.3 Formulários Front-End

#### Formulário de Cadastro de Cliente
- **Shortcode**: `[dps_base]` → Aba "Clientes" → Botão "Adicionar Cliente"
- **Arquivo**: `class-dps-base-frontend.php` (método `render_client_form()`)
- **Campos**: Nome, Email, Telefone, WhatsApp, Endereço, CEP, etc.
- **Nonce**: `wp_nonce_field( 'dps_action' )`
- **Sanitização**: Feita em `handle_request()` usando `sanitize_text_field()`, `sanitize_email()`
- **Validação**: Email único, telefone obrigatório
- **Envio**: POST para mesma página com `dps_action=save_client`
- **Redirecionamento**: Após salvar, redireciona para `?dps_view=clients` com mensagem de sucesso

#### Formulário de Cadastro de Pet
- **Shortcode**: `[dps_base]` → Aba "Pets" → Botão "Adicionar Pet"
- **Arquivo**: `class-dps-base-frontend.php` (método `render_pet_form()`)
- **Campos**: Nome, Tutor (select), Espécie, Raça, Porte, Idade, Foto, Observações
- **Fieldsets**: "Dados Básicos" e "Saúde e Comportamento"
- **Upload**: Usa `.dps-file-upload` com preview via JavaScript
- **Nonce**: `wp_nonce_field( 'dps_action' )`
- **Sanitização**: `sanitize_text_field()`, `wp_handle_upload()`

#### Formulário de Agendamento
- **Shortcode**: `[dps_base]` → Aba "Agendamentos" → Botão "Novo Agendamento"
- **Arquivo**: `class-dps-base-frontend.php` (método `render_appointment_form()`)
- **Campos**: 
  - Cliente (select)
  - Pets (checkboxes múltiplos com carregamento via REST API)
  - Data (date picker)
  - Horário (select dinâmico via AJAX)
  - Observações
- **JavaScript**: `dps-appointment-form.js`
- **AJAX**: `dps_get_available_times` para carregar horários disponíveis
- **Validação Front-end**: 
  - Cliente obrigatório
  - Pelo menos 1 pet
  - Data não pode ser passada
  - Horário obrigatório
- **Resumo Dinâmico**: Atualiza em tempo real com cliente, pets, data, horário

#### Formulário de Configurações
- **Shortcode**: `[dps_configuracoes]`
- **Arquivo**: `class-dps-base-frontend.php` (método `render_settings()`)
- **Sistema de Abas**: Usa hooks `dps_settings_nav_tabs` e `dps_settings_sections`
- **Add-ons podem adicionar suas próprias abas**

#### Formulário de Cadastro Público
- **Shortcode**: `[dps_registration_form]`
- **Add-on**: Registration
- **Arquivo**: `desi-pet-shower-registration-addon.php`
- **Funcionalidade**: Permite cliente se cadastrar sem login
- **Confirmação**: Envia email de confirmação

#### Formulários do Portal do Cliente
- **Shortcode**: `[dps_client_portal]`
- **Add-on**: Client Portal
- **Arquivo**: `class-dps-client-portal.php`
- **Formulários**:
  1. **Atualizar Dados do Cliente**: Nome, telefone, endereço
  2. **Adicionar Pet**: Cadastro de novo pet
  3. **Atualizar Pet**: Edição de pet existente
- **Autenticação**: Via sessão PHP (não usa usuários WordPress)
- **Validação**: Nonce + sanitização padrão
- **Feedback**: Classes `.dps-portal-notice--success/error/info`

### 2.4 Endpoints Públicos (REST/AJAX)

| Endpoint | Tipo | Público | Arquivo | Funcionalidade |
|----------|------|---------|---------|----------------|
| `dps_get_available_times` | AJAX | Sim | Base Plugin | Retorna horários disponíveis para data |
| `dps_update_status` | AJAX | Sim | Agenda Add-on | Atualiza status de agendamento |
| `dps_get_services_details` | AJAX | Sim | Agenda Add-on | Detalhes de serviços do agendamento |
| `/dps/v1/pets` | REST | Não | Base Plugin | Lista paginada de pets (autenticado) |

**REST API**:
```php
// Base Plugin - desi-pet-shower-base.php:287
register_rest_route( 'dps/v1', '/pets', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [ $this, 'rest_list_pets' ],
    'permission_callback' => [ $this, 'rest_permissions' ],
] );
```

- **Permissão**: Requer capability `dps_manage_pets`
- **Parâmetros**: `page`, `search`, `owner`
- **Cache**: 15 minutos via transients
- **Uso**: Carregamento incremental de pets no formulário de agendamento

### 2.5 Scripts e Estilos do Front-End

#### Base Plugin
| Asset | Handle | Dependências | Quando Carrega | Arquivo |
|-------|--------|--------------|----------------|---------|
| CSS Base | `dps-base-style` | - | Páginas com shortcode `[dps_base]` ou `[dps_configuracoes]` | `assets/css/dps-base.css` |
| JS Base | `dps-base-script` | jQuery | Idem | `assets/js/dps-base.js` |
| JS Agendamento | `dps-appointment-form` | jQuery | Idem | `assets/js/dps-appointment-form.js` |

**Condição de carregamento**:
```php
// desi-pet-shower-base.php:206
$should_enqueue = ( $post instanceof WP_Post ) && 
    ( has_shortcode( $post->post_content, 'dps_base' ) || 
      has_shortcode( $post->post_content, 'dps_configuracoes' ) );
```

**Localizações JavaScript**:
- `dpsAppointmentData`: AJAX URL, nonce, textos de UI
- `dpsBaseData`: REST URL, nonce, paginação
- `dpsBaseL10n`: Textos traduzíveis

#### Agenda Add-on
| Asset | Handle | Quando Carrega | Arquivo |
|-------|--------|----------------|---------|
| CSS Agenda | `dps-agenda-addon-style` | Páginas com `[dps_agenda_page]` | `assets/css/agenda-addon.css` |
| JS Services Modal | `dps-services-modal` | Idem | `assets/js/services-modal.js` |

**PROBLEMA**: Também existem arquivos `agenda-addon.js` e `agenda.js` na raiz do add-on (fora da pasta assets) - possível duplicação ou arquivos antigos.

#### Client Portal Add-on
| Asset | Handle | Quando Carrega | Arquivo |
|-------|--------|----------------|---------|
| CSS Portal | `dps-client-portal-style` | Páginas com `[dps_client_portal]` | `assets/css/client-portal.css` |
| JS Portal | `dps-client-portal-script` | Idem | `assets/js/client-portal.js` |

**Funcionalidade JS**:
- Desabilita botões durante submit
- Preview de upload de imagem
- Validação de formulários

#### Services Add-on
| Asset | Handle | Quando Carrega | Arquivo |
|-------|--------|----------------|---------|
| JS Services | `dps-services-addon-script` | Páginas com shortcode de serviços | `dps_service/assets/js/dps-services-addon.js` |

---

## 3. DUPLICAÇÕES E ERROS ARQUITETURAIS

### 3.1 Arquivos Duplicados

#### ❌ Finance Add-on - DUPLICAÇÃO COMPLETA
```
plugins/desi-pet-shower-finance/
├── desi-pet-shower-finance-addon.php  ← PLUGIN PRINCIPAL (tem header de plugin)
└── desi-pet-shower-finance.php        ← ARQUIVO DE COMPATIBILIDADE (sem header)
```

**Análise**:
- `desi-pet-shower-finance-addon.php`: Plugin completo com header WordPress
- `desi-pet-shower-finance.php`: Carrega o arquivo principal para compatibilidade

**Status**: ✅ CORRETO - Não é duplicação real. O arquivo `-addon.php` é o plugin, o outro é wrapper de compatibilidade. Documentado no README.

#### ❌ Services Add-on - ESTRUTURA DUPLICADA
```
plugins/desi-pet-shower-services/
├── desi-pet-shower-services.php         ← PLUGIN v1.1.0 (tem header)
└── dps_service/
    └── desi-pet-shower-services-addon.php ← OUTRO PLUGIN v1.0.0 (tem header)
```

**Análise**:
```bash
# desi-pet-shower-services.php
Plugin Name: desi.pet by PRObst – Serviços Add-on
Version: 1.1.0

# dps_service/desi-pet-shower-services-addon.php
Plugin Name: desi.pet by PRObst – Serviços Add-on
Version: 1.0.0
```

**PROBLEMA CRÍTICO**: 
- **DOIS arquivos com header de plugin completo**
- Ambos aparecem na lista de plugins do WordPress
- Versões diferentes (1.1.0 vs 1.0.0)
- Provavelmente causam conflito se ambos ativados

**SOLUÇÃO RECOMENDADA**:
- Decidir qual é a versão correta
- Remover header do plugin descontinuado
- Ou deletar o arquivo obsoleto

#### ❌ Subscription Add-on - ESTRUTURA DUPLICADA
```
plugins/desi-pet-shower-subscription/
├── desi-pet-shower-subscription.php         ← PLUGIN (tem header)
└── dps_subscription/
    └── desi-pet-shower-subscription-addon.php ← OUTRO PLUGIN (tem header)
```

**Análise**:
```bash
# desi-pet-shower-subscription.php
Plugin Name: desi.pet by PRObst – Assinaturas Add-on
Version: 1.0.0
# Inclui o arquivo da subpasta

# dps_subscription/desi-pet-shower-subscription-addon.php
Plugin Name: desi.pet by PRObst – Assinaturas Add-on
Version: 1.0.0
```

**PROBLEMA**: Mesma situação do Services - dois headers de plugin.

**ESTRUTURA ATUAL**:
- O arquivo raiz inclui (`require_once`) o arquivo da subpasta
- Ambos têm header de plugin

**SOLUÇÃO**:
- Remover header de um dos arquivos
- Seguir padrão do Finance (apenas addon principal tem header)

#### ❌ Agenda Add-on - ARQUIVOS JS DUPLICADOS
```
plugins/desi-pet-shower-agenda/
├── agenda-addon.js     ← FORA da pasta assets
├── agenda.js           ← FORA da pasta assets
└── assets/
    └── js/
        └── services-modal.js
```

**PROBLEMA**: 
- Existem 2 arquivos JS na raiz do add-on
- Não são referenciados em `enqueue_assets()`
- Provavelmente arquivos antigos/não utilizados

**SOLUÇÃO**: Deletar `agenda-addon.js` e `agenda.js` se não são usados.

### 3.2 Funções Duplicadas

#### ❌ Função `dps_format_money_br()` - DUPLICADA 2x

**Ocorrências**:
1. `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php:69`
2. `plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php:966`

**Análise**:
```php
// Finance Add-on
if ( ! function_exists( 'dps_format_money_br' ) ) {
    function dps_format_money_br( $int ) {
        if ( ! is_numeric( $int ) || $int < 0 ) {
            return 'R$ 0,00';
        }
        return 'R$ ' . number_format( $int / 100, 2, ',', '.' );
    }
}

// Loyalty Add-on
if ( ! function_exists( 'dps_format_money_br' ) ) {
    function dps_format_money_br( $int ) {
        if ( ! is_numeric( $int ) || $int < 0 ) {
            return 'R$ 0,00';
        }
        return 'R$ ' . number_format( $int / 100, 2, ',', '.' );
    }
}
```

**PROBLEMA**: 
- Código idêntico em dois add-ons
- Usa `if (!function_exists())` para evitar erro fatal
- Mas não há garantia de qual versão carrega primeiro

**SOLUÇÃO**: 
- **EXISTE helper oficial**: `DPS_Money_Helper::format_to_brazilian()`
- Remover funções duplicadas
- Usar helper do core

#### ❌ Função `dps_parse_money_br()` - DUPLICADA

**Ocorrências**:
1. `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php:47`

**PROBLEMA**:
- **EXISTE helper oficial**: `DPS_Money_Helper::parse_brazilian_format()`
- Add-on cria função global desnecessária

#### ❌ Função `format_whatsapp_number()` - DUPLICADA

**Ocorrências**:
1. `class-dps-base-frontend.php:28` (método privado)
2. `desi-pet-shower-agenda-addon.php:1127` (método privado)

**Código**:
```php
// Base
private static function format_whatsapp_number( $raw_phone ) {
    $digits = preg_replace( '/\D/', '', $raw_phone );
    if ( strlen( $digits ) === 11 ) {
        return '55' . $digits;
    }
    return $digits;
}

// Agenda
private static function format_whatsapp_number( $phone ) {
    $clean = preg_replace( '/\D/', '', $phone );
    if ( strlen( $clean ) === 11 ) {
        return '55' . $clean;
    }
    return $clean;
}
```

**PROBLEMA**: Lógica idêntica em dois lugares.

**SOLUÇÃO**: Criar helper público no base ou mover para classe utilitária.

### 3.3 Classes Duplicadas

**NENHUMA classe duplicada identificada.**

Todas as classes têm nomes únicos:
- Base: `DPS_Base_Plugin`, `DPS_Base_Frontend`, helpers
- Add-ons: `DPS_Finance_Addon`, `DPS_Agenda_Addon`, `DPS_Client_Portal`, etc.

### 3.4 Lógica Espalhada Entre Core e Add-ons

#### ❌ Lógica Financeira em Múltiplos Locais

**Finance Add-on**:
- Cria tabela `dps_transacoes`
- Registra receitas e despesas
- Shortcode `[dps_fin_docs]`

**Agenda Add-on**:
- **TAMBÉM tem lógica financeira**:
  - Gera cobranças
  - Cria notas/boletos
  - Shortcode `[dps_charges_notes]`
  
**PROBLEMA**: 
- Funcionalidade financeira está em 2 add-ons diferentes
- Finance deveria centralizar TUDO relacionado a dinheiro
- Agenda deveria apenas agendar, não cobrar

#### ❌ Lógica de Comunicação Espalhada

**Communications Add-on**:
- Envia mensagens WhatsApp
- Templates de mensagens

**Client Portal Add-on**:
- **TAMBÉM envia mensagens**:
  - Mensagens para clientes via portal
  - Sistema de notificações

**Agenda Add-on**:
- **TAMBÉM envia lembretes**:
  - Cron job para lembretes diários
  - Integração WhatsApp

**PROBLEMA**: Comunicação está em 3 lugares diferentes.

#### ❌ Lógica de Serviços Misturada

**Services Add-on**:
- Cadastro de serviços (padrão, extras, pacotes)
- Cálculo de valores por porte

**Agenda Add-on**:
- **TAMBÉM manipula serviços**:
  - AJAX `dps_get_services_details`
  - Calcula valor total do agendamento
  - Aplica variações por porte

**PROBLEMA**: 
- Cálculo de serviços deveria ser 100% no Services Add-on
- Agenda deveria apenas consumir via API/hook

#### ❌ HTML Inline vs. Lógica de Negócio

**TODOS os arquivos misturam**:
- Queries complexas
- Lógica de negócio
- Cálculos
- Validação
- HTML inline (echo de HTML dentro de PHP)

**Exemplo típico** (`class-dps-base-frontend.php`):
```php
public static function render_client_form() {
    // 200 linhas de HTML inline com echo
    // Misturado com PHP de validação
    // Misturado com queries de dados
}
```

**PROBLEMA**: Dificulta:
- Manutenção
- Testes
- Reutilização
- Separação de responsabilidades

### 3.5 Conflitos Entre Core e Add-ons

#### ⚠️ CPT Registrado pelo Core, Modificado por Add-on

**Core registra**:
- `dps_cliente`
- `dps_pet`
- `dps_agendamento`

**Add-ons registram seus próprios CPTs**:
- `dps_subscription` (Subscription)
- `dps_portal_message` (Client Portal)

**SEM conflitos diretos**, mas:

#### ⚠️ Metadados de CPTs Modificados por Vários Add-ons

**Exemplo**: `dps_agendamento`

**Metadados adicionados por**:
- **Base**: `appointment_client_id`, `appointment_pet_ids`, `appointment_date`, `appointment_time`
- **Services**: `appointment_services` (array de serviços)
- **Finance**: `appointment_payment_status`
- **Agenda**: `appointment_version`, `appointment_status`

**PROBLEMA POTENCIAL**:
- Não há contrato formal de metadados
- Add-ons podem sobrescrever uns aos outros
- Sem validação de schema

---

## 4. SUGESTÃO DE REORGANIZAÇÃO

### 4.1 Resolver Duplicações de Arquivos

#### Services Add-on
```
AÇÃO: Consolidar em estrutura única

ATUAL:
├── desi-pet-shower-services.php (v1.1.0)
└── dps_service/
    └── desi-pet-shower-services-addon.php (v1.0.0)

PROPOSTA:
└── desi-pet-shower-services-addon.php (versão única v1.1.0)
    └── includes/ (opcional para classes)
```

#### Subscription Add-on
```
AÇÃO: Mesma consolidação

ATUAL:
├── desi-pet-shower-subscription.php
└── dps_subscription/
    └── desi-pet-shower-subscription-addon.php

PROPOSTA:
└── desi-pet-shower-subscription-addon.php
    └── includes/ (classes)
```

#### Agenda Add-on
```
AÇÃO: Limpar arquivos JS antigos

REMOVER:
├── agenda-addon.js
└── agenda.js

MANTER:
└── assets/
    └── js/
        └── services-modal.js
```

### 4.2 Centralizar Helpers Duplicados

#### Remover Funções Globais Duplicadas

**Finance e Loyalty Add-ons**:
```php
// REMOVER estas funções:
// - dps_format_money_br()
// - dps_parse_money_br()

// SUBSTITUIR por:
DPS_Money_Helper::format_to_brazilian( $cents );
DPS_Money_Helper::parse_brazilian_format( $money_string );
```

**WhatsApp Formatting**:
```php
// CRIAR novo helper global:
// plugins/desi-pet-shower-base/includes/class-dps-phone-helper.php

class DPS_Phone_Helper {
    public static function format_for_whatsapp( $phone ) {
        $digits = preg_replace( '/\D/', '', $phone );
        if ( strlen( $digits ) === 11 ) {
            return '55' . $digits;
        }
        return $digits;
    }
}

// REMOVER de:
// - class-dps-base-frontend.php
// - desi-pet-shower-agenda-addon.php
```

### 4.3 Separar Responsabilidades

#### Finance Add-on - Dono de TUDO Financeiro
```
MOVER para Finance Add-on:
- Geração de cobranças (atualmente no Agenda)
- Criação de notas/boletos (atualmente no Agenda)
- Shortcode [dps_charges_notes] (atualmente no Agenda)

MANTER no Finance:
- Tabela dps_transacoes
- Receitas e despesas
- Relatórios financeiros
```

#### Communications Add-on - Dono de TODA Comunicação
```
CENTRALIZAR no Communications:
- Templates de mensagens
- Envio WhatsApp
- Envio Email
- Lembretes (mover do Agenda)
- Notificações (mover do Client Portal)

EXPOR hooks:
- dps_send_whatsapp_message( $to, $message )
- dps_send_appointment_reminder( $appointment_id )
- dps_send_payment_notification( $client_id, $amount )
```

#### Services Add-on - Dono de Cálculos de Serviço
```
MOVER para Services:
- Toda lógica de cálculo de valor (do Agenda)
- AJAX dps_get_services_details (do Agenda)

EXPOR funções:
- dps_calculate_service_price( $service_id, $pet_size )
- dps_get_appointment_total( $services, $pets )
```

### 4.4 Separar HTML de Lógica

#### Criar Sistema de Templates

**Proposta**:
```
plugins/desi-pet-shower-base/
└── templates/
    ├── forms/
    │   ├── client-form.php
    │   ├── pet-form.php
    │   └── appointment-form.php
    ├── lists/
    │   ├── clients-list.php
    │   ├── pets-list.php
    │   └── appointments-list.php
    └── partials/
        ├── client-card.php
        ├── pet-card.php
        └── form-field.php
```

**Padrão de uso**:
```php
// Em vez de:
public function render_client_form() {
    echo '<form>';
    echo '<input name="client_name">';
    // ... 200 linhas
}

// Fazer:
public function render_client_form() {
    $data = $this->prepare_client_form_data();
    include DPS_BASE_DIR . 'templates/forms/client-form.php';
}
```

### 4.5 Documentar Contratos de Metadados

#### Criar `METADATA_CONTRACTS.md`

```markdown
# Contratos de Metadados DPS

## dps_agendamento

| Meta Key | Tipo | Owner | Descrição |
|----------|------|-------|-----------|
| appointment_client_id | int | Base | ID do cliente |
| appointment_pet_ids | array | Base | IDs dos pets |
| appointment_date | string | Base | Data (Y-m-d) |
| appointment_time | string | Base | Horário (H:i) |
| appointment_services | array | Services | Serviços selecionados |
| appointment_total | int | Services | Valor total em centavos |
| appointment_payment_status | string | Finance | paid/pending/cancelled |
| appointment_version | int | Agenda | Versionamento |
| appointment_status | string | Agenda | scheduled/completed/cancelled |

## dps_cliente

| Meta Key | Tipo | Owner | Descrição |
|----------|------|-------|-----------|
| client_email | string | Base | Email único |
| client_phone | string | Base | Telefone |
| client_whatsapp | string | Base | WhatsApp |
| client_user_id | int | Portal | ID usuário WordPress |
| loyalty_points | int | Loyalty | Pontos de fidelidade |
```

### 4.6 Criar Interfaces Admin Nativas

**PROBLEMA ATUAL**: CPTs com `show_ui => false`

**PROPOSTA**:
```php
// Tornar CPTs editáveis no admin
register_post_type( 'dps_cliente', [
    'public'     => false,
    'show_ui'    => true,  // ← MUDAR para true
    'show_in_menu' => 'dps-main', // ← Agrupar em menu DPS
    // ...
] );
```

**Benefícios**:
- Editores podem usar interface nativa do WordPress
- Bulk actions
- Quick Edit
- Filtros avançados
- Integração com plugins de terceiros

**Manter shortcodes para**:
- Interface simplificada para recepcionistas
- Portal do cliente
- Formulários públicos

### 4.7 Estrutura de Add-ons Padronizada

**Modelo de estrutura**:
```
desi-pet-shower-[nome]_addon/
├── desi-pet-shower-[nome]-addon.php  ← ÚNICO arquivo de plugin
├── README.md
├── uninstall.php
├── includes/
│   ├── class-dps-[nome]-main.php
│   ├── class-dps-[nome]-helper.php
│   └── class-dps-[nome]-admin.php (se tiver UI admin)
├── assets/
│   ├── css/
│   │   └── [nome]-addon.css
│   └── js/
│       └── [nome]-addon.js
└── templates/ (se tiver)
    └── [nome]-form.php
```

**Aplicar para**:
- ✅ Finance (já segue)
- ❌ Services (consolidar)
- ❌ Subscription (consolidar)
- ✅ Agenda (limpar JS antigos)

---

## 5. MÉTRICAS DO SISTEMA

### Contagem de Código

```
Plugin Base:
- class-dps-base-frontend.php: 3.049 linhas (!)
- Total Base: ~3.500 linhas PHP

Add-ons:
- Loyalty: 1.006 linhas
- Agenda: ~800 linhas
- Client Portal: ~1.200 linhas
- Finance: ~300 linhas
- Services: ~500 linhas (dividido em 2 arquivos)

Total: ~7.000+ linhas PHP
```

### Complexidade

**Arquivo mais complexo**: `class-dps-base-frontend.php`
- 3.049 linhas
- Responsável por TUDO do front-end
- Mistura HTML, queries, validação, lógica de negócio

**Funções gigantes identificadas**:
- `render_app()`: ~200 linhas
- `render_client_form()`: ~200 linhas
- `render_pet_form()`: ~250 linhas
- `render_appointment_form()`: ~300 linhas

### Assets

```
CSS: 4 arquivos
- dps-base.css (base)
- dps-admin.css (base)
- agenda-addon.css
- client-portal.css

JS: 6 arquivos
- dps-base.js
- dps-appointment-form.js
- services-modal.js
- client-portal.js
- dps-services-addon.js
- agenda-addon.js (duplicado?)
```

---

## 6. CONCLUSÃO

### ✅ Pontos Positivos

1. **Arquitetura de extensão bem pensada**: Sistema de hooks para add-ons
2. **Helpers globais úteis**: `DPS_Money_Helper`, `DPS_Request_Validator`, etc.
3. **Segurança**: Uso consistente de nonces e sanitização
4. **Cache**: Implementado para queries de pets
5. **AJAX bem estruturado**: Endpoints claros e validados

### ❌ Problemas Críticos

1. **Duplicação de plugins**: Services e Subscription têm 2 headers de plugin cada
2. **Funções duplicadas**: `dps_format_money_br()`, `format_whatsapp_number()`
3. **Responsabilidades espalhadas**: 
   - Financeiro em Finance + Agenda
   - Comunicação em Communications + Portal + Agenda
   - Serviços em Services + Agenda
4. **HTML inline**: 3.000+ linhas de echo misturado com lógica
5. **Sem UI admin nativa**: CPTs com `show_ui => false`
6. **Arquivos antigos**: JS duplicados no Agenda

### 🔧 Prioridade de Refatoração

**Alta Prioridade**:
1. Remover header duplicado de Services e Subscription
2. Centralizar funções monetárias (usar helpers)
3. Limpar arquivos JS antigos do Agenda

**Média Prioridade**:
4. Separar responsabilidades (Finance/Communications/Services)
5. Criar sistema de templates
6. Documentar contratos de metadados

**Baixa Prioridade**:
7. Habilitar `show_ui` nos CPTs
8. Quebrar `class-dps-base-frontend.php` em múltiplas classes
9. Padronizar estrutura de todos os add-ons

---

## APÊNDICE: Mapa de Dependências

```
Base Plugin (core)
  ├── CPTs: cliente, pet, agendamento
  ├── Helpers globais
  ├── Shortcode [dps_base]
  └── Expõe hooks para extensão

Finance Add-on
  ├── Depende de: Base
  ├── Tabela: dps_transacoes
  └── Shortcode [dps_fin_docs]

Services Add-on
  ├── Depende de: Base
  ├── CPT: dps_service
  └── Adiciona metadados em appointment

Subscription Add-on
  ├── Depende de: Base, Services (?)
  ├── CPT: dps_subscription
  └── Gera agendamentos recorrentes

Agenda Add-on
  ├── Depende de: Base, Services, Finance (!)
  ├── Shortcodes: [dps_agenda_page], [dps_charges_notes]
  ├── AJAX: update_status, get_services_details
  └── Cron: lembretes diários

Client Portal Add-on
  ├── Depende de: Base, Finance (?)
  ├── CPT: dps_portal_message
  ├── Shortcodes: [dps_client_portal], [dps_client_login]
  └── Sistema de login próprio (sessão PHP)

Communications Add-on
  ├── Depende de: Base
  └── Integração WhatsApp

Loyalty Add-on
  ├── Depende de: Base, Finance (?)
  ├── Sistema de pontos
  └── Menu admin próprio

Registration Add-on
  ├── Depende de: Base
  ├── Shortcode: [dps_registration_form]
  └── Formulário público

[Outros add-ons menores não analisados em detalhe]
```

---

**FIM DO MAPEAMENTO**
