# Guia do UsuÃ¡rio: Frontend Add-on (desi.pet by PRObst)

> **VersÃ£o**: 1.0.0
> **Ãšltima atualizaÃ§Ã£o**: 2026-02-12
> **Autor**: PRObst
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## ðŸ“– Ãndice

1. [VisÃ£o Geral](#visÃ£o-geral)
2. [InstalaÃ§Ã£o e AtivaÃ§Ã£o](#instalaÃ§Ã£o-e-ativaÃ§Ã£o)
3. [MÃ³dulos DisponÃ­veis](#mÃ³dulos-disponÃ­veis)
4. [ConfiguraÃ§Ã£o](#configuraÃ§Ã£o)
5. [Shortcodes](#shortcodes)
6. [Criando PÃ¡ginas Frontend](#criando-pÃ¡ginas-frontend)
7. [PersonalizaÃ§Ã£o Visual](#personalizaÃ§Ã£o-visual)
8. [ResoluÃ§Ã£o de Problemas](#resoluÃ§Ã£o-de-problemas)
9. [Perguntas Frequentes](#perguntas-frequentes)

---

## VisÃ£o Geral

O **Frontend Add-on** (`desi-pet-shower-frontend`) Ã© um complemento modular do sistema desi.pet by PRObst que consolida e moderniza as experiÃªncias frontend voltadas aos clientes, incluindo:

- **FormulÃ¡rio de Cadastro** de clientes e pets
- **FormulÃ¡rio de Agendamento** de serviÃ§os
- **Painel de ConfiguraÃ§Ãµes** administrativas

### âœ¨ Principais caracterÃ­sticas

- **Arquitetura moderna PHP 8.4**: CÃ³digo otimizado e seguro
- **Design DPS Signature**: Interface visual moderna e consistente
- **Feature Flags**: Controle granular de ativaÃ§Ã£o por mÃ³dulo
- **Rollback instantÃ¢neo**: Desative mÃ³dulos sem impactar o sistema
- **Dual-run**: Funciona em paralelo com add-ons legados durante a migraÃ§Ã£o
- **Totalmente seguro**: ValidaÃ§Ã£o de nonce, capabilities e sanitizaÃ§Ã£o

### ðŸŽ¯ Para quem Ã© este add-on?

- **ProprietÃ¡rios de pet shops** que desejam modernizar a experiÃªncia dos clientes
- **Administradores do sistema** que precisam de controle granular sobre funcionalidades
- **Desenvolvedores** que buscam uma arquitetura moderna e bem documentada

---

## InstalaÃ§Ã£o e AtivaÃ§Ã£o

### PrÃ©-requisitos

Antes de instalar o Frontend Add-on, certifique-se de que:

- âœ… WordPress **6.9 ou superior** estÃ¡ instalado
- âœ… PHP **8.4 ou superior** estÃ¡ ativo no servidor
- âœ… Plugin base **desi.pet by PRObst** estÃ¡ instalado e ativado
- âœ… Para usar o mÃ³dulo Registration: add-on `desi-pet-shower-registration` deve estar ativo
- âœ… Para usar o mÃ³dulo Booking: add-on `desi-pet-shower-booking` deve estar ativo

### Passo 1: Instalar o plugin

1. FaÃ§a upload do plugin para `/wp-content/plugins/desi-pet-shower-frontend/`
2. Ou instale via GitHub Updater (consulte `GUIA_SISTEMA_DPS.md` para instruÃ§Ãµes)

### Passo 2: Ativar o plugin

**Via Painel WordPress:**
1. Acesse **Plugins** â†’ **Plugins Instalados**
2. Localize **desi.pet by PRObst â€“ Frontend Add-on**
3. Clique em **Ativar**

**Via WP-CLI:**
```bash
wp plugin activate desi-pet-shower-frontend
```

### Passo 3: Verificar a instalaÃ§Ã£o

ApÃ³s ativar o plugin, vocÃª verÃ¡:
- Uma nova aba **"Frontend"** na pÃ¡gina de ConfiguraÃ§Ãµes do sistema
- Mensagem de sucesso no topo da tela

> **Nota importante**: Ao instalar pela primeira vez, **todos os mÃ³dulos estarÃ£o desabilitados por padrÃ£o**. VocÃª precisarÃ¡ habilitar os mÃ³dulos desejados manualmente na aba de ConfiguraÃ§Ãµes.

---

## MÃ³dulos DisponÃ­veis

O Frontend Add-on Ã© composto por **3 mÃ³dulos independentes**, cada um controlado por uma feature flag. VocÃª pode ativar ou desativar cada mÃ³dulo conforme sua necessidade.

AlÃ©m disso, os **mÃ³dulos nativos V2** (Fase 7) oferecem formulÃ¡rios 100% independentes dos add-ons legados, com implementaÃ§Ã£o DPS Signature nativa.

### ðŸ“‹ MÃ³dulo Registration (Cadastro)

**Status:** Operacional (Fase 2)
**Feature Flag:** `registration`
**Shortcode assumido:** `[dps_registration_form]`

**O que faz:**
- Exibe formulÃ¡rio pÃºblico de cadastro para clientes e pets
- Aplica estilos modernos DPS Signature
- MantÃ©m compatibilidade total com o add-on legado de cadastro
- Preserva todos os hooks de integraÃ§Ã£o (ex: integraÃ§Ã£o com Loyalty)

**Quando usar:**
- Para criar pÃ¡gina pÃºblica de cadastro de novos clientes
- Para modernizar visualmente o formulÃ¡rio de cadastro existente
- Para preparar migraÃ§Ã£o gradual do sistema legado

### ðŸ“… MÃ³dulo Booking (Agendamento)

**Status:** Operacional (Fase 3)
**Feature Flag:** `booking`
**Shortcode assumido:** `[dps_booking_form]`

**O que faz:**
- Exibe formulÃ¡rio pÃºblico de agendamento de serviÃ§os
- Aplica estilos modernos DPS Signature
- MantÃ©m compatibilidade total com o add-on legado de agendamento
- Preserva todos os hooks crÃ­ticos consumidos por 7+ add-ons

**Quando usar:**
- Para criar pÃ¡gina pÃºblica de agendamento de serviÃ§os
- Para modernizar visualmente o formulÃ¡rio de agendamento existente
- Para preparar migraÃ§Ã£o gradual do sistema legado

### âš™ï¸ MÃ³dulo Settings (ConfiguraÃ§Ãµes)

**Status:** Operacional (Fase 4)
**Feature Flag:** `settings`
**Hooks consumidos:** `dps_settings_register_tabs`, `dps_settings_save_save_frontend`

**O que faz:**
- Adiciona aba "Frontend" no painel de configuraÃ§Ãµes administrativas
- Permite controlar feature flags de forma visual
- Exibe informaÃ§Ãµes do add-on (versÃ£o, mÃ³dulos ativos)
- Interface intuitiva para habilitar/desabilitar mÃ³dulos

**Quando usar:**
- Para gerenciar visualmente as feature flags dos mÃ³dulos
- Para verificar status de ativaÃ§Ã£o e versÃµes
- Para administradores que preferem interface grÃ¡fica ao WP-CLI

### ðŸ“‹ MÃ³dulo Registration V2 (Cadastro Nativo)

**Status:** Operacional (Fase 7.2)
**Feature Flag:** `registration_v2`
**Shortcode:** `[dps_registration_v2]`

**O que faz:**
- FormulÃ¡rio de cadastro 100% nativo DPS Signature â€” nÃ£o depende do add-on legado
- ValidaÃ§Ã£o completa: nome, email, telefone, CPF (mod-11), pets
- DetecÃ§Ã£o de duplicatas por telefone com override para admin
- reCAPTCHA v3 integrado (quando habilitado)
- Email de confirmaÃ§Ã£o com token 48h
- IntegraÃ§Ã£o Loyalty via Hook Bridge (cÃ³digo de indicaÃ§Ã£o)
- Anti-spam filter configurÃ¡vel

**Quando usar:**
- Para substituir o formulÃ¡rio legado de cadastro por implementaÃ§Ã£o nativa moderna
- Quando deseja independÃªncia total do `DPS_Registration_Addon`
- Para sites novos que nÃ£o precisam de compatibilidade retroativa

> **Nota:** Pode coexistir com o mÃ³dulo Registration v1 â€” ambos podem estar ativos em pÃ¡ginas diferentes.

### ðŸ“… MÃ³dulo Booking V2 (Agendamento Nativo)

**Status:** Operacional (Fase 7.3)
**Feature Flag:** `booking_v2`
**Shortcode:** `[dps_booking_v2]`

**O que faz:**
- Wizard de agendamento nativo DPS Signature com 5 steps:
  1. **Busca e seleÃ§Ã£o de cliente** (AJAX por telefone)
  2. **SeleÃ§Ã£o de pets** (mÃºltiplos, com paginaÃ§Ã£o)
  3. **SeleÃ§Ã£o de serviÃ§os** (com preÃ§os por porte e total acumulado)
  4. **Data e horÃ¡rio** (slots de 30min com verificaÃ§Ã£o de conflitos)
  5. **ConfirmaÃ§Ã£o** (resumo completo + submit)
- 3 tipos de agendamento: avulso (simple), recorrente (subscription), retroativo (past)
- Extras condicionais: TaxiDog (checkbox + preÃ§o) e Tosa (subscription only + preÃ§o + frequÃªncia)
- Login obrigatÃ³rio com redirecionamento automÃ¡tico
- 5 endpoints AJAX com nonce + capability check
- Hook bridge CRÃTICO: dispara `dps_base_after_save_appointment` para 8 add-ons consumidores
- ConfirmaÃ§Ã£o via transient (5min TTL)
- 100% independente do `DPS_Booking_Addon`
- JavaScript vanilla (zero jQuery)

**Quando usar:**
- Para substituir o formulÃ¡rio legado de agendamento por implementaÃ§Ã£o nativa moderna
- Quando deseja independÃªncia total do `DPS_Booking_Addon`
- Para sites novos que nÃ£o precisam de compatibilidade retroativa

> **Nota:** Pode coexistir com o mÃ³dulo Booking v1 â€” ambos podem estar ativos em pÃ¡ginas diferentes.

---

## ConfiguraÃ§Ã£o

### OpÃ§Ã£o 1: Via Painel Administrativo (recomendado)

Esta Ã© a forma mais simples e visual de configurar o Frontend Add-on.

#### Passo 1: Habilitar o mÃ³dulo Settings

**Primeira vez? Habilite via WP-CLI ou diretamente no banco:**

```bash
# Via WP-CLI
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json
```

**Ou via phpMyAdmin/cÃ³digo:**
```php
update_option( 'dps_frontend_feature_flags', [
    'registration' => false,
    'booking'      => false,
    'settings'     => true,
] );
```

#### Passo 2: Acessar a aba Frontend

1. Acesse a pÃ¡gina de **ConfiguraÃ§Ãµes** do sistema
   - Use o shortcode `[dps_configuracoes]` ou
   - Acesse via menu admin do desi.pet
2. Clique na aba **"Frontend"**
3. VocÃª verÃ¡:
   - InformaÃ§Ãµes sobre o add-on (versÃ£o, mÃ³dulos disponÃ­veis)
   - Checkboxes para habilitar cada mÃ³dulo
   - Contador de mÃ³dulos ativos

#### Passo 3: Habilitar mÃ³dulos desejados

1. Marque as caixas dos mÃ³dulos que deseja ativar:
   - â˜ **Registration** (Cadastro)
   - â˜ **Booking** (Agendamento)
   - â˜ **Settings** (ConfiguraÃ§Ãµes)
2. Clique no botÃ£o **"Salvar ConfiguraÃ§Ãµes"**
3. Aguarde a mensagem de confirmaÃ§Ã£o
4. As pÃ¡ginas pÃºblicas com os shortcodes agora exibirÃ£o a versÃ£o modernizada

### OpÃ§Ã£o 2: Via WP-CLI

Para administradores avanÃ§ados ou scripts de deploy automatizado.

#### Habilitar todos os mÃ³dulos de uma vez:

```bash
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json
```

#### Habilitar mÃ³dulos gradualmente (recomendado em produÃ§Ã£o):

```bash
# Primeiro: Settings (menor risco)
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json

# Depois: Registration
wp option update dps_frontend_feature_flags '{"registration":true,"booking":false,"settings":true}' --format=json

# Por Ãºltimo: Booking
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json
```

#### Verificar status atual:

```bash
wp option get dps_frontend_feature_flags --format=json
```

#### Desabilitar um mÃ³dulo especÃ­fico (rollback):

```bash
# Exemplo: desabilitar apenas Registration
wp option update dps_frontend_feature_flags '{"registration":false,"booking":true,"settings":true}' --format=json
```

### OpÃ§Ã£o 3: Via CÃ³digo PHP

Em `wp-config.php` ou em um plugin personalizado:

```php
// Habilitar todos os mÃ³dulos
update_option( 'dps_frontend_feature_flags', [
    'registration' => true,
    'booking'      => true,
    'settings'     => true,
] );

// Verificar se um mÃ³dulo estÃ¡ ativo
$flags = get_option( 'dps_frontend_feature_flags', [] );
if ( ! empty( $flags['registration'] ) ) {
    // MÃ³dulo Registration estÃ¡ ativo
}
```

### EstratÃ©gia de AtivaÃ§Ã£o Recomendada

Para minimizar riscos, siga esta ordem de ativaÃ§Ã£o:

1. **Primeiro: Settings** (risco mÃ­nimo â€” apenas aba admin)
2. **Segundo: Registration** (risco mÃ©dio â€” formulÃ¡rio pÃºblico)
3. **Terceiro: Booking** (risco mÃ©dio â€” agendamento)

**Janela de observaÃ§Ã£o:** aguarde mÃ­nimo **48 horas** entre a ativaÃ§Ã£o de cada mÃ³dulo em ambiente de produÃ§Ã£o.

---

## Shortcodes

O Frontend Add-on trabalha com shortcodes existentes (v1, em dual-run com legado) e novos shortcodes nativos (v2, independentes). Abaixo, a lista completa de shortcodes utilizados e como aplicÃ¡-los.

> **v1 (dual-run):** `[dps_registration_form]` e `[dps_booking_form]` â€” envolvem o legado com surface DPS Signature
> **v2 (nativo):** `[dps_registration_v2]` e `[dps_booking_v2]` â€” implementaÃ§Ã£o 100% independente

### ðŸ”– `[dps_registration_form]`

**DescriÃ§Ã£o:** Exibe o formulÃ¡rio de cadastro de clientes e pets com design DPS Signature.

**MÃ³dulo requerido:** Registration (`registration` flag habilitada)

**ParÃ¢metros:** Nenhum (usa todos os padrÃµes do sistema)

**Exemplo de uso:**
```
[dps_registration_form]
```

**Output:**
- FormulÃ¡rio completo de cadastro
- Campos para dados do cliente (nome, telefone, email, etc.)
- Campos para dados do(s) pet(s)
- BotÃ£o de envio com validaÃ§Ã£o
- Mensagens de sucesso/erro
- Redirecionamento automÃ¡tico apÃ³s cadastro

**Onde usar:**
- PÃ¡gina pÃºblica "Cadastre-se"
- PÃ¡gina "Novo Cliente"
- Landing pages de captaÃ§Ã£o

**Hooks disponÃ­veis para extensÃ£o:**
```php
// Adicionar campos customizados ao formulÃ¡rio
add_action( 'dps_registration_after_fields', 'minha_funcao', 10, 1 );

// Processar dados apÃ³s criaÃ§Ã£o do cliente
add_action( 'dps_registration_after_client_created', 'minha_funcao', 10, 4 );

// ValidaÃ§Ã£o anti-spam customizada
add_filter( 'dps_registration_spam_check', 'minha_funcao', 10, 2 );

// Customizar URL de redirecionamento pÃ³s-cadastro
add_filter( 'dps_registration_agenda_url', 'minha_funcao', 10, 2 );
```

---

### ðŸ”– `[dps_booking_form]`

**DescriÃ§Ã£o:** Exibe o formulÃ¡rio de agendamento de serviÃ§os com design DPS Signature.

**MÃ³dulo requerido:** Booking (`booking` flag habilitada)

**ParÃ¢metros:** Nenhum (usa todos os padrÃµes do sistema)

**Exemplo de uso:**
```
[dps_booking_form]
```

**Output:**
- FormulÃ¡rio completo de agendamento
- SeleÃ§Ã£o de cliente (se logado) ou busca por telefone
- SeleÃ§Ã£o de pet(s)
- SeleÃ§Ã£o de serviÃ§o(s)
- Escolha de data e horÃ¡rio
- Campo de observaÃ§Ãµes
- BotÃ£o de confirmaÃ§Ã£o
- Mensagens de validaÃ§Ã£o
- ConfirmaÃ§Ã£o visual pÃ³s-agendamento

**Onde usar:**
- PÃ¡gina pÃºblica "Agendar ServiÃ§o"
- PÃ¡gina "Novo Agendamento"
- Portal do cliente (Ã¡rea autenticada)

**Hooks disponÃ­veis para extensÃ£o:**
```php
// CRÃTICO: Hook consumido por 7+ add-ons
add_action( 'dps_base_after_save_appointment', 'minha_funcao', 10, 2 );

// Adicionar campos customizados ao formulÃ¡rio
add_action( 'dps_base_appointment_fields', 'minha_funcao', 10, 1 );

// Modificar campos de atribuiÃ§Ã£o (tosadores, etc.)
add_action( 'dps_base_appointment_assignment_fields', 'minha_funcao', 10, 1 );
```

---

### ðŸ”– `[dps_registration_v2]`

**DescriÃ§Ã£o:** FormulÃ¡rio nativo de cadastro DPS Signature. **100% independente do add-on legado** â€” nÃ£o requer `DPS_Registration_Addon`.

**MÃ³dulo requerido:** Registration V2 (`registration_v2` flag habilitada)

**ParÃ¢metros:**
| Atributo | DescriÃ§Ã£o | PadrÃ£o |
|----------|-----------|--------|
| `redirect_url` | URL pÃ³s-cadastro | PÃ¡gina de agendamento |
| `show_pets` | Exibir seÃ§Ã£o de pets | `true` |
| `show_marketing` | Exibir opt-in marketing | `true` |
| `theme` | Tema visual: `light` ou `dark` | `light` |
| `compact` | Modo compacto | `false` |

**Exemplos de uso:**
```
[dps_registration_v2]
[dps_registration_v2 redirect_url="/agendar" theme="dark"]
[dps_registration_v2 show_pets="false" compact="true"]
```

**Output:**
- FormulÃ¡rio nativo com validaÃ§Ã£o client + server (nome, email, telefone, CPF)
- DetecÃ§Ã£o de duplicatas por telefone
- Repeater de pets com dataset de raÃ§as por espÃ©cie
- reCAPTCHA v3 (quando habilitado)
- Email de confirmaÃ§Ã£o 48h
- IntegraÃ§Ã£o Loyalty preservada via Hook Bridge

**Onde usar:**
- PÃ¡gina pÃºblica de cadastro (substitui `[dps_registration_form]`)
- Sites novos sem add-on legado instalado

**Hooks disponÃ­veis para extensÃ£o:**
```php
// Antes de renderizar o formulÃ¡rio V2
add_action( 'dps_registration_v2_before_render', 'minha_funcao', 10, 1 );

// ApÃ³s criar cliente via V2 (hook bridge dispara legado do Loyalty primeiro)
add_action( 'dps_registration_v2_client_created', 'minha_funcao', 10, 3 );

// ApÃ³s criar pet via V2
add_action( 'dps_registration_v2_pet_created', 'minha_funcao', 10, 3 );
```

---

### ðŸ”– `[dps_booking_v2]`

**DescriÃ§Ã£o:** Wizard nativo de agendamento DPS Signature com 5 steps. **100% independente do add-on legado** â€” nÃ£o requer `DPS_Booking_Addon`.

**MÃ³dulo requerido:** Booking V2 (`booking_v2` flag habilitada)
**Requisito:** UsuÃ¡rio logado com capability `manage_options`, `dps_manage_clients`, `dps_manage_pets` ou `dps_manage_appointments`

**ParÃ¢metros:**
| Atributo | DescriÃ§Ã£o | PadrÃ£o |
|----------|-----------|--------|
| `appointment_type` | Tipo: `simple`, `subscription` ou `past` | `simple` |
| `client_id` | ID do cliente prÃ©-selecionado | (vazio) |
| `service_id` | ID do serviÃ§o prÃ©-selecionado | (vazio) |
| `start_step` | Step inicial do wizard (1-5) | `1` |
| `show_progress` | Exibir barra de progresso | `true` |
| `theme` | Tema visual: `light` ou `dark` | `light` |
| `compact` | Modo compacto | `false` |
| `edit_id` | ID de agendamento para ediÃ§Ã£o | (vazio) |

**Exemplos de uso:**
```
[dps_booking_v2]
[dps_booking_v2 appointment_type="subscription"]
[dps_booking_v2 client_id="123" start_step="2"]
[dps_booking_v2 theme="dark" compact="true"]
```

**Output:**
- Wizard 5 steps com barra de progresso e navegaÃ§Ã£o
- Step 1: Busca de cliente por telefone (AJAX)
- Step 2: SeleÃ§Ã£o de pets (mÃºltiplos, com paginaÃ§Ã£o)
- Step 3: SeleÃ§Ã£o de serviÃ§os com preÃ§os por porte
- Step 4: Data/hora com slots e verificaÃ§Ã£o de conflitos
- Step 5: Extras (TaxiDog, Tosa para subscription) + ConfirmaÃ§Ã£o final
- Tela de sucesso pÃ³s-criaÃ§Ã£o

**Onde usar:**
- PÃ¡gina administrativa de agendamento (substitui `[dps_booking_form]`)
- Portal do cliente (Ã¡rea autenticada)

**Hooks disponÃ­veis para extensÃ£o:**
```php
// Antes de renderizar o wizard V2
add_action( 'dps_booking_v2_before_render', 'minha_funcao', 10, 1 );

// Ao renderizar step do wizard
add_action( 'dps_booking_v2_step_render', 'minha_funcao', 10, 2 );

// Filtro de validaÃ§Ã£o por step
add_filter( 'dps_booking_v2_step_validate', 'minha_funcao', 10, 3 );

// Antes de criar agendamento
add_action( 'dps_booking_v2_before_process', 'minha_funcao', 10, 1 );

// ApÃ³s criar agendamento V2
add_action( 'dps_booking_v2_appointment_created', 'minha_funcao', 10, 2 );

// CRÃTICO: Hook bridge para 8 add-ons (disparado automaticamente)
// Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
add_action( 'dps_base_after_save_appointment', 'minha_funcao', 10, 2 );
```

---

### ðŸ”– `[dps_configuracoes]`

**DescriÃ§Ã£o:** Exibe a pÃ¡gina completa de configuraÃ§Ãµes administrativas do sistema. **Este shortcode nÃ£o Ã© modificado pelo Frontend Add-on**, mas a aba "Frontend" sÃ³ aparece se o mÃ³dulo Settings estiver ativo.

**MÃ³dulo requerido:** Settings (`settings` flag habilitada) â€” apenas para exibir a aba Frontend

**ParÃ¢metros:** Nenhum

**Exemplo de uso:**
```
[dps_configuracoes]
```

**Output:**
- PÃ¡gina de configuraÃ§Ãµes com mÃºltiplas abas
- Aba "Frontend" (se mÃ³dulo Settings ativo) com:
  - InformaÃ§Ãµes do add-on
  - Controles de feature flags
  - Contador de mÃ³dulos ativos
  - BotÃ£o de salvar

**Onde usar:**
- PÃ¡gina administrativa "ConfiguraÃ§Ãµes do Sistema"
- Painel de administraÃ§Ã£o (uso interno)

**Nota:** Este shortcode Ã© do plugin base e nÃ£o Ã© afetado pelo Frontend Add-on. O mÃ³dulo Settings apenas adiciona uma nova aba dentro desta pÃ¡gina.

---

## Criando PÃ¡ginas Frontend

Aqui estÃ£o instruÃ§Ãµes completas para criar as pÃ¡ginas necessÃ¡rias que utilizam os shortcodes do Frontend Add-on.

### ðŸ“„ PÃ¡gina de Cadastro

**Objetivo:** Permitir que novos clientes se cadastrem no sistema.

**Passo a passo:**

1. **Criar nova pÃ¡gina no WordPress:**
   - VÃ¡ em **PÃ¡ginas** â†’ **Adicionar Nova**
   - TÃ­tulo sugerido: "Cadastre-se" ou "Novo Cliente"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_registration_form]`
   - Publique a pÃ¡gina

3. **Configurar permalink amigÃ¡vel (opcional):**
   - URL sugerida: `https://seusite.com/cadastro/`
   - Configure em **ConfiguraÃ§Ãµes** â†’ **Links Permanentes**

4. **Adicionar ao menu (opcional):**
   - VÃ¡ em **AparÃªncia** â†’ **Menus**
   - Adicione a pÃ¡gina ao menu principal
   - Texto sugerido: "Cadastre-se" ou "Novo Cliente"

5. **Definir como pÃ¡gina de cadastro do sistema:**
   - Acesse as configuraÃ§Ãµes do add-on Registration
   - Defina esta pÃ¡gina como "PÃ¡gina de Cadastro"
   - Isso garantirÃ¡ redirecionamentos corretos

**Dica de seguranÃ§a:** Esta pÃ¡gina deve ser pÃºblica e acessÃ­vel sem login.

---

### ðŸ“„ PÃ¡gina de Agendamento

**Objetivo:** Permitir que clientes agendem serviÃ§os.

**Passo a passo:**

1. **Criar nova pÃ¡gina no WordPress:**
   - VÃ¡ em **PÃ¡ginas** â†’ **Adicionar Nova**
   - TÃ­tulo sugerido: "Agendar ServiÃ§o" ou "Marcar HorÃ¡rio"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_booking_form]`
   - Publique a pÃ¡gina

3. **Configurar permalink amigÃ¡vel (opcional):**
   - URL sugerida: `https://seusite.com/agendar/`

4. **Adicionar ao menu (opcional):**
   - VÃ¡ em **AparÃªncia** â†’ **Menus**
   - Adicione a pÃ¡gina ao menu principal
   - Texto sugerido: "Agendar" ou "Marcar HorÃ¡rio"

5. **Definir como pÃ¡gina de agendamento do sistema:**
   - Acesse as configuraÃ§Ãµes do add-on Booking
   - Defina esta pÃ¡gina como "PÃ¡gina de Agendamento"

**Notas importantes:**
- Esta pÃ¡gina pode ser pÃºblica ou protegida (requer login)
- Se protegida, garanta que clientes tenham acesso
- Considere criar versÃµes diferentes para:
  - Clientes pÃºblicos (primeiro agendamento)
  - Clientes cadastrados (reagendamento)

---

### ðŸ“„ PÃ¡gina de ConfiguraÃ§Ãµes (Admin)

**Objetivo:** Centralizar configuraÃ§Ãµes administrativas do sistema.

**Passo a passo:**

1. **Criar nova pÃ¡gina no WordPress:**
   - VÃ¡ em **PÃ¡ginas** â†’ **Adicionar Nova**
   - TÃ­tulo sugerido: "ConfiguraÃ§Ãµes do Sistema"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_configuracoes]`
   - Publique a pÃ¡gina

3. **Proteger a pÃ¡gina (OBRIGATÃ“RIO):**
   - Esta pÃ¡gina deve ser acessÃ­vel **apenas para administradores**
   - Use plugin de controle de acesso ou configure via cÃ³digo
   - Exemplo com cÃ³digo:
   ```php
   // No functions.php do tema ou plugin
   add_action( 'template_redirect', function() {
       if ( is_page( 'configuracoes' ) && ! current_user_can( 'manage_options' ) ) {
           wp_redirect( home_url() );
           exit;
       }
   } );
   ```

4. **Adicionar ao menu admin (opcional):**
   - Adicione link direto no menu do admin
   - Ou crie shortcut no dashboard

**SeguranÃ§a:** Esta pÃ¡gina contÃ©m configuraÃ§Ãµes sensÃ­veis do sistema. NUNCA a deixe pÃºblica.

---

### ðŸ“„ Exemplo de Layout Completo

Para uma experiÃªncia ideal, crie a seguinte estrutura de pÃ¡ginas:

```
ðŸ“ PÃ¡ginas PÃºblicas
â”œâ”€â”€ ðŸ  Home
â”œâ”€â”€ ðŸ“‹ Cadastre-se              â†’ [dps_registration_form]
â”œâ”€â”€ ðŸ“… Agendar ServiÃ§o          â†’ [dps_booking_form]
â”œâ”€â”€ ðŸ“ž Contato
â””â”€â”€ â„¹ï¸ Sobre NÃ³s

ðŸ“ PÃ¡ginas Protegidas (clientes)
â”œâ”€â”€ ðŸ‘¤ Minha Conta
â”œâ”€â”€ ðŸ¾ Meus Pets
â””â”€â”€ ðŸ“… Meus Agendamentos        â†’ [dps_booking_form]

ðŸ“ PÃ¡ginas Admin (apenas staff)
â””â”€â”€ âš™ï¸ ConfiguraÃ§Ãµes            â†’ [dps_configuracoes]
```

---

## PersonalizaÃ§Ã£o Visual

O Frontend Add-on utiliza o **Design System DPS Signature** para garantir uma experiÃªncia visual moderna e consistente.

### ðŸŽ¨ Sistema de Design Tokens

Todos os estilos sÃ£o baseados em **CSS Custom Properties** (variÃ¡veis CSS), facilitando a personalizaÃ§Ã£o sem editar arquivos do plugin.

**Arquivo de tokens:** `dps-design-tokens.css` (carregado automaticamente pelo plugin base)

### Principais Categorias de Tokens

#### 1. Cores

```css
/* Cores principais (primary) */
--dps-color-primary: #6750A4;
--dps-color-on-primary: #FFFFFF;
--dps-color-primary-container: #EADDFF;
--dps-color-on-primary-container: #21005D;

/* Cores secundÃ¡rias (secondary) */
--dps-color-secondary: #625B71;
--dps-color-on-secondary: #FFFFFF;
--dps-color-secondary-container: #E8DEF8;
--dps-color-on-secondary-container: #1D192B;

/* SuperfÃ­cies (backgrounds) */
--dps-color-surface: #FEF7FF;
--dps-color-surface-variant: #E7E0EC;
--dps-color-on-surface: #1C1B1F;
--dps-color-on-surface-variant: #49454F;

/* Estados (success, warning, error) */
--dps-color-success: #4CAF50;
--dps-color-warning: #FF9800;
--dps-color-error: #B3261E;
```

#### 2. Tipografia

```css
/* Escala tipogrÃ¡fica DPS Signature */
--dps-typescale-display-large: 57px;
--dps-typescale-headline-large: 32px;
--dps-typescale-title-large: 22px;
--dps-typescale-body-large: 16px;
--dps-typescale-label-large: 14px;
```

#### 3. Formas (arredondamentos)

```css
/* Escala de arredondamento */
--dps-shape-none: 0px;
--dps-shape-extra-small: 4px;
--dps-shape-small: 8px;
--dps-shape-medium: 12px;
--dps-shape-large: 16px;
--dps-shape-extra-large: 28px;
--dps-shape-pill: 9999px;
```

#### 4. ElevaÃ§Ã£o (sombras)

```css
/* NÃ­veis de elevaÃ§Ã£o tonal */
--dps-elevation-1: 0px 1px 2px rgba(0, 0, 0, 0.3);
--dps-elevation-2: 0px 1px 2px rgba(0, 0, 0, 0.3), 0px 2px 6px rgba(0, 0, 0, 0.15);
--dps-elevation-3: 0px 4px 8px rgba(0, 0, 0, 0.15), 0px 1px 3px rgba(0, 0, 0, 0.3);
```

#### 5. Motion (animaÃ§Ãµes)

```css
/* DuraÃ§Ãµes */
--dps-motion-duration-short: 200ms;
--dps-motion-duration-medium: 300ms;
--dps-motion-duration-long: 500ms;

/* Easing expressivo */
--dps-motion-easing-standard: cubic-bezier(0.4, 0.0, 0.2, 1);
--dps-motion-easing-emphasized: cubic-bezier(0.2, 0.0, 0, 1);
```

### Personalizando o Frontend Add-on

#### MÃ©todo 1: Sobrescrever tokens (recomendado)

Adicione CSS customizado no seu tema que sobrescreve os tokens:

```css
/* No arquivo CSS do seu tema */
:root {
    /* Mudar cor primÃ¡ria para azul */
    --dps-color-primary: #1976D2;
    --dps-color-on-primary: #FFFFFF;
    --dps-color-primary-container: #BBDEFB;

    /* Mudar arredondamento padrÃ£o */
    --dps-shape-medium: 8px;

    /* Acelerar animaÃ§Ãµes */
    --dps-motion-duration-medium: 200ms;
}
```

#### MÃ©todo 2: Classes CSS especÃ­ficas

Cada mÃ³dulo envolve seu output em classes especÃ­ficas:

```css
/* Estilizar o formulÃ¡rio de cadastro */
.dps-frontend .dps-registration-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem;
}

/* Estilizar o formulÃ¡rio de agendamento */
.dps-frontend .dps-booking-form {
    background: var(--dps-color-surface);
    border-radius: var(--dps-shape-large);
    padding: 2rem;
}

/* Customizar botÃµes */
.dps-frontend .dps-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

#### MÃ©todo 3: Tema Escuro

O sistema suporta tema escuro via atributo `data-dps-theme`:

```html
<body data-dps-theme="dark">
    <!-- ConteÃºdo com tema escuro aplicado -->
</body>
```

Ou via JavaScript:

```javascript
// Ativar tema escuro
document.body.setAttribute('data-dps-theme', 'dark');

// Desativar tema escuro
document.body.setAttribute('data-dps-theme', 'light');

// Toggle
const currentTheme = document.body.getAttribute('data-dps-theme') || 'light';
document.body.setAttribute('data-dps-theme', currentTheme === 'light' ? 'dark' : 'light');
```

### Classes CSS DisponÃ­veis

**Wrapper principal:**
- `.dps-frontend` â€” envolve todo o output dos mÃ³dulos

**FormulÃ¡rios:**
- `.dps-registration-form` â€” formulÃ¡rio de cadastro
- `.dps-booking-form` â€” formulÃ¡rio de agendamento

**Componentes:**
- `.dps-btn-primary` â€” botÃ£o primÃ¡rio (aÃ§Ã£o principal)
- `.dps-btn-secondary` â€” botÃ£o secundÃ¡rio
- `.dps-btn-text` â€” botÃ£o texto (sem fundo)
- `.dps-field-group` â€” grupo de campos
- `.dps-label` â€” rÃ³tulos de campos
- `.dps-input` â€” campos de entrada
- `.dps-select` â€” campos select
- `.dps-checkbox` â€” checkboxes
- `.dps-radio` â€” radio buttons
- `.dps-message` â€” mensagens de feedback
- `.dps-message--success` â€” mensagem de sucesso
- `.dps-message--error` â€” mensagem de erro
- `.dps-message--warning` â€” mensagem de aviso

### ReferÃªncias de Design

Para design detalhado, consulte:
- `docs/visual/VISUAL_STYLE_GUIDE.md` â€” Guia completo de estilos
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` â€” InstruÃ§Ãµes de design frontend

---

## ResoluÃ§Ã£o de Problemas

### âŒ Problema: Aba "Frontend" nÃ£o aparece nas ConfiguraÃ§Ãµes

**Causa provÃ¡vel:** MÃ³dulo Settings nÃ£o estÃ¡ ativo.

**SoluÃ§Ã£o:**
1. Ative o mÃ³dulo Settings via WP-CLI:
   ```bash
   wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json
   ```
2. Recarregue a pÃ¡gina de configuraÃ§Ãµes

---

### âŒ Problema: Shortcode exibe cÃ³digo ao invÃ©s do formulÃ¡rio

**Exemplo:** A pÃ¡gina mostra `[dps_registration_form]` como texto.

**Causas provÃ¡veis:**
1. MÃ³dulo correspondente nÃ£o estÃ¡ ativo
2. Add-on legado nÃ£o estÃ¡ instalado
3. Plugin Frontend nÃ£o estÃ¡ ativado

**SoluÃ§Ã£o:**
1. Verifique se o plugin estÃ¡ ativo:
   ```bash
   wp plugin list | grep frontend
   ```
2. Verifique se o mÃ³dulo estÃ¡ ativo:
   ```bash
   wp option get dps_frontend_feature_flags --format=json
   ```
3. Verifique se o add-on legado correspondente estÃ¡ ativo:
   - Para Registration: `desi-pet-shower-registration`
   - Para Booking: `desi-pet-shower-booking`
4. Ative o mÃ³dulo necessÃ¡rio via WP-CLI ou painel

---

### âŒ Problema: FormulÃ¡rio aparece mas sem estilos

**Sintoma:** O formulÃ¡rio funciona mas estÃ¡ com aparÃªncia "quebrada" ou sem estilo.

**Causas provÃ¡veis:**
1. CSS nÃ£o estÃ¡ sendo carregado
2. Conflito com tema ou outro plugin
3. Cache de CSS desatualizado

**SoluÃ§Ã£o:**
1. Limpe o cache do navegador (Ctrl+Shift+R)
2. Limpe cache do WordPress (se usar plugin de cache)
3. Verifique se `dps-design-tokens.css` estÃ¡ sendo carregado:
   - Abra DevTools (F12)
   - VÃ¡ na aba Network
   - Recarregue a pÃ¡gina
   - Procure por `dps-design-tokens.css` e `frontend-addon.css`
4. Se nÃ£o estiver carregando, verifique se o plugin base estÃ¡ ativo
5. Desative temporariamente outros plugins para identificar conflito

---

### âŒ Problema: Erro ao salvar configuraÃ§Ãµes

**Sintoma:** Mensagem de erro ao clicar em "Salvar ConfiguraÃ§Ãµes" na aba Frontend.

**Causas provÃ¡veis:**
1. Nonce expirado (sessÃ£o antiga)
2. Falta de permissÃ£o (usuÃ¡rio nÃ£o Ã© admin)
3. Conflito de plugin

**SoluÃ§Ã£o:**
1. Recarregue a pÃ¡gina e tente novamente
2. FaÃ§a logout e login novamente
3. Verifique se seu usuÃ¡rio tem capability `manage_options`:
   ```php
   current_user_can( 'manage_options' ); // deve retornar true
   ```
4. Verifique logs de erro do WordPress:
   ```bash
   tail -f /caminho/para/wp-content/debug.log
   ```

---

### âŒ Problema: MÃ³dulo ativo mas formulÃ¡rio nÃ£o aparece

**Sintoma:** Feature flag estÃ¡ `true` mas o formulÃ¡rio nÃ£o renderiza.

**Causas provÃ¡veis:**
1. Add-on legado nÃ£o estÃ¡ instalado/ativo
2. Shortcode foi digitado incorretamente
3. Cache de pÃ¡gina

**SoluÃ§Ã£o:**
1. Verifique a digitaÃ§Ã£o exata do shortcode:
   - Registration: `[dps_registration_form]` (sem espaÃ§os extras)
   - Booking: `[dps_booking_form]` (sem espaÃ§os extras)
2. Verifique se add-on legado estÃ¡ ativo:
   ```bash
   wp plugin list | grep -E '(registration|booking)'
   ```
3. Ative o add-on legado correspondente:
   ```bash
   wp plugin activate desi-pet-shower-registration
   wp plugin activate desi-pet-shower-booking
   ```
4. Limpe cache de pÃ¡ginas
5. Verifique logs do sistema (se WP_DEBUG ativo):
   - Procure por avisos do `DPS_Frontend_Logger`

---

### âŒ Problema: Rollback nÃ£o funciona

**Sintoma:** Desabilitei o mÃ³dulo mas o formulÃ¡rio continua aparecendo.

**Causa provÃ¡vel:** Cache de pÃ¡gina ou de objeto.

**SoluÃ§Ã£o:**
1. Limpe TODOS os caches:
   - Cache de navegador
   - Cache de pÃ¡gina (WP Super Cache, W3 Total Cache, etc.)
   - Cache de objeto (Redis, Memcached)
   - Cache de CDN (Cloudflare, etc.)
2. Verifique se a flag foi realmente desabilitada:
   ```bash
   wp option get dps_frontend_feature_flags --format=json
   ```
3. Force recarga sem cache: Ctrl+Shift+R (ou Cmd+Shift+R no Mac)
4. Se persistir, desative o plugin inteiro e reative

---

### âš ï¸ Problema: Hooks personalizados nÃ£o funcionam

**Sintoma:** Hooks customizados (ex: `dps_registration_after_fields`) nÃ£o sÃ£o executados.

**Causa provÃ¡vel:** Prioridade de hook ou mÃ³dulo nÃ£o inicializado.

**SoluÃ§Ã£o:**
1. Verifique se o mÃ³dulo estÃ¡ ativo
2. Registre seu hook com prioridade adequada:
   ```php
   // Prioridade 10 Ã© padrÃ£o, mas pode precisar ajustar
   add_action( 'dps_registration_after_fields', 'minha_funcao', 10, 1 );
   ```
3. Verifique se sua funÃ§Ã£o estÃ¡ sendo carregada:
   ```php
   function minha_funcao( $data ) {
       error_log( 'Hook executado: ' . print_r( $data, true ) );
       // seu cÃ³digo aqui
   }
   ```
4. Consulte documentaÃ§Ã£o dos hooks em `ANALYSIS.md`

---

### ðŸ” Debug Mode

Para ativar logs detalhados do Frontend Add-on:

1. Ative WP_DEBUG no `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

2. Logs serÃ£o salvos em `/wp-content/debug.log`

3. Procure por mensagens do `DPS_Frontend_Logger`:
   ```bash
   tail -f wp-content/debug.log | grep "Frontend"
   ```

4. NÃ­veis de log:
   - `INFO`: InformaÃ§Ãµes gerais (mÃ³dulo ativado, etc.)
   - `WARNING`: Avisos (legado nÃ£o encontrado, etc.)
   - `ERROR`: Erros crÃ­ticos

---

## Perguntas Frequentes

### 1. Preciso desinstalar os add-ons legados?

**Resposta:** NÃ£o! O Frontend Add-on trabalha **em paralelo** (dual-run) com os add-ons legados. Ambos precisam estar ativos para o sistema funcionar.

- âœ… Mantenha `desi-pet-shower-registration` ativo
- âœ… Mantenha `desi-pet-shower-booking` ativo
- âœ… Ative `desi-pet-shower-frontend` adicional

O Frontend Add-on apenas "envolve" a funcionalidade legada com estilos modernos.

---

### 2. Posso usar o Frontend Add-on em produÃ§Ã£o?

**Resposta:** Sim! Todos os 3 mÃ³dulos estÃ£o **operacionais e testados** (Fases 2-4 concluÃ­das). PorÃ©m, recomendamos:

1. Testar em ambiente de desenvolvimento primeiro
2. Ativar mÃ³dulos gradualmente (Settings â†’ Registration â†’ Booking)
3. Manter janela de observaÃ§Ã£o de 48h entre ativaÃ§Ãµes
4. Ter plano de rollback documentado

O sistema foi projetado para rollback instantÃ¢neo desabilitando feature flags.

---

### 3. O que acontece se eu desabilitar um mÃ³dulo?

**Resposta:** Rollback instantÃ¢neo! O comportamento volta **100%** para o add-on legado:

- âœ… Sem quebra de funcionalidade
- âœ… Sem perda de dados
- âœ… Sem necessidade de reconfigurar

Apenas os estilos DPS Signature deixam de ser aplicados, voltando ao visual legado.

---

### 4. Posso customizar os formulÃ¡rios?

**Resposta:** Sim! HÃ¡ 3 nÃ­veis de customizaÃ§Ã£o:

**NÃ­vel 1 â€” Visual (CSS):**
- Sobrescreva design tokens CSS
- Adicione classes customizadas
- Ative tema escuro

**NÃ­vel 2 â€” Estrutura (Hooks):**
- Use hooks para adicionar campos
- Modifique comportamentos via filtros
- Estenda funcionalidade sem editar core

**NÃ­vel 3 â€” CÃ³digo (Desenvolvimento):**
- Crie mÃ³dulos customizados
- Estenda classes base
- Consulte `AGENT_ENGINEERING_PLAYBOOK.md`

---

### 5. Como atualizar o Frontend Add-on?

**Resposta:** Usando GitHub Updater (recomendado):

1. Configure GitHub Updater (consulte `GUIA_SISTEMA_DPS.md`)
2. VÃ¡ em **Painel** â†’ **AtualizaÃ§Ãµes**
3. Localize "desi.pet by PRObst â€“ Frontend Add-on"
4. Clique em "Atualizar Agora"

Ou manualmente:
1. Desative o plugin
2. Substitua arquivos em `/wp-content/plugins/desi-pet-shower-frontend/`
3. Reative o plugin
4. Verifique se feature flags permanecem ativas

**Dica:** As configuraÃ§Ãµes (feature flags) sÃ£o mantidas apÃ³s atualizaÃ§Ã£o.

---

### 6. Frontend Add-on consome recursos extras?

**Resposta:** Impacto mÃ­nimo!

- **CSS adicional:** ~15KB (gzipped)
- **JavaScript:** MÃ­nimo (apenas quando necessÃ¡rio)
- **Processamento:** Zero overhead (apenas envolve output legado)
- **Banco de dados:** Apenas 1 option (`dps_frontend_feature_flags`)
- **Telemetria:** Contadores batch no shutdown (zero overhead por request)

O add-on foi otimizado para performance mÃ¡xima.

---

### 7. Posso usar apenas alguns mÃ³dulos?

**Resposta:** Sim! Cada mÃ³dulo Ã© **100% independente**:

- âœ… Habilite apenas Settings (se quiser apenas a aba admin)
- âœ… Habilite apenas Registration (se quiser apenas modernizar cadastro)
- âœ… Habilite apenas Booking (se quiser apenas modernizar agendamento)
- âœ… Habilite qualquer combinaÃ§Ã£o que desejar

NÃ£o hÃ¡ dependÃªncia entre mÃ³dulos.

---

### 8. Como reportar problemas?

**Resposta:** Entre em contato:

1. **Via GitHub:** Abra issue em `https://github.com/richardprobst/DPS`
2. **Via Email:** Contate PRObst em [www.probst.pro](https://www.probst.pro)
3. **Incluir sempre:**
   - VersÃ£o do WordPress e PHP
   - VersÃ£o do Frontend Add-on
   - VersÃ£o do plugin base
   - Logs de erro (se disponÃ­veis)
   - Passos para reproduzir o problema

---

### 9. Roadmap futuro do Frontend Add-on

**Resposta:** O add-on seguiu um plano em **6 fases** + a **Fase 7** de implementaÃ§Ã£o nativa:

- âœ… **Fase 1:** FundaÃ§Ã£o (arquitetura, feature flags, assets) â€” ConcluÃ­da
- âœ… **Fase 2:** MÃ³dulo Registration (dual-run) â€” ConcluÃ­da
- âœ… **Fase 3:** MÃ³dulo Booking (dual-run) â€” ConcluÃ­da
- âœ… **Fase 4:** MÃ³dulo Settings (aba admin) â€” ConcluÃ­da
- âœ… **Fase 5:** ConsolidaÃ§Ã£o e documentaÃ§Ã£o â€” ConcluÃ­da
- âœ… **Fase 6:** GovernanÃ§a de depreciaÃ§Ã£o â€” ConcluÃ­da
- âœ… **Fase 7.1:** PreparaÃ§Ã£o V2 (abstracts, template engine, hook bridges, componentes DPS Signature) â€” ConcluÃ­da
- âœ… **Fase 7.2:** Registration V2 nativo (formulÃ¡rio independente) â€” ConcluÃ­da
- âœ… **Fase 7.3:** Booking V2 nativo (wizard 5-step independente) â€” ConcluÃ­da
- âœ… **Fase 7.4:** CoexistÃªncia e migraÃ§Ã£o (toggle admin, documentaÃ§Ã£o, telemetria) â€” ConcluÃ­da
- âœ… **Fase 7.5:** DepreciaÃ§Ã£o do dual-run (aviso admin implementado) â€” CÃ³digo concluÃ­do

**Status atual:** Todo o cÃ³digo das Fases 1â€“7 estÃ¡ implementado. A remoÃ§Ã£o efetiva dos mÃ³dulos v1 (parte final da Fase 7.5) aguarda prÃ©-requisitos de produÃ§Ã£o:
- 90+ dias de V2 em produÃ§Ã£o estÃ¡vel
- 80%+ dos sites migraram para V2
- Zero bugs crÃ­ticos em V2
- Telemetria confirma uso < 5% de V1

**PrÃ³ximos passos:**
- Ativar mÃ³dulos V2 em produÃ§Ã£o e monitorar telemetria
- ObservaÃ§Ã£o de telemetria de uso (180 dias mÃ­nimo)
- DecisÃ£o sobre remoÃ§Ã£o dos add-ons legados (conforme FRONTEND_DEPRECATION_POLICY.md)
- Novos mÃ³dulos (portal do cliente, relatÃ³rios, etc.)

Consulte `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` para detalhes da Fase 7.

---

### 10. O que Ã© o aviso de depreciaÃ§Ã£o no admin?

**Resposta:** Quando mÃ³dulos v1 (Cadastro v1 ou Agendamento v1) estÃ£o ativos, um banner amarelo aparece no painel administrativo do WordPress com o tÃ­tulo **"desi.pet Frontend â€” Aviso de MigraÃ§Ã£o"**.

Este aviso:
- Informa que o modo dual-run (v1) serÃ¡ descontinuado em versÃ£o futura
- Recomenda migrar para os mÃ³dulos nativos V2
- Inclui link para o guia de migraÃ§Ã£o
- Pode ser dispensado (clique no "X") â€” volta apÃ³s 30 dias
- SÃ³ aparece para administradores (`manage_options`)

**Para remover o aviso permanentemente:** desabilite os mÃ³dulos v1 e use apenas os mÃ³dulos v2 (`registration_v2` e `booking_v2`).

---

### 11. Onde encontrar mais documentaÃ§Ã£o?

**Resposta:** DocumentaÃ§Ã£o completa disponÃ­vel:

| Documento | PropÃ³sito |
|-----------|-----------|
| `docs/GUIA_SISTEMA_DPS.md` | Guia geral do sistema completo |
| `docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md` | Guia de migraÃ§Ã£o v1 â†’ v2 |
| `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` | Guia de rollout por ambiente |
| `docs/implementation/FRONTEND_RUNBOOK.md` | Runbook de incidentes e rollback |
| `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` | Compatibilidade com outros add-ons |
| `docs/qa/FRONTEND_REMOVAL_READINESS.md` | Checklist de remoÃ§Ã£o futura |
| `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md` | Roadmap completo das 6 fases |
| `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` | Plano da Fase 7 (implementaÃ§Ã£o nativa V2) |
| `docs/refactoring/FRONTEND_DEPRECATION_POLICY.md` | PolÃ­tica de depreciaÃ§Ã£o |
| `docs/visual/VISUAL_STYLE_GUIDE.md` | Guia de estilos visuais DPS Signature |
| `ANALYSIS.md` | Arquitetura e contratos internos |
| `CHANGELOG.md` | HistÃ³rico de versÃµes e mudanÃ§as |

---

## ðŸ“ž Suporte

Para suporte tÃ©cnico ou dÃºvidas:

- **Site:** [www.probst.pro](https://www.probst.pro)
- **GitHub:** [richardprobst/DPS](https://github.com/richardprobst/DPS)
- **Email:** Consulte o site para contato

---

## ðŸ“œ LicenÃ§a

Frontend Add-on Ã© parte do **desi.pet by PRObst** e Ã© licenciado sob GPL-2.0+.

---

**Ãšltima atualizaÃ§Ã£o:** 2026-02-12
**VersÃ£o do documento:** 1.0.0
**VersÃ£o do add-on:** 1.5.0 (todas as 6 fases concluÃ­das)
