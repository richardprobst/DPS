# Plano de ImplementaÃ§Ã£o Nativa â€” Frontend Add-on (Fase 7)

> **VersÃ£o**: 1.4.0
> **Data**: 2026-02-12
> **Autor**: PRObst
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## ðŸ“‹ Ãndice

1. [Contexto e MotivaÃ§Ã£o](#contexto-e-motivaÃ§Ã£o)
2. [SituaÃ§Ã£o Atual (Fases 1-6)](#situaÃ§Ã£o-atual-fases-1-6)
3. [InventÃ¡rio de Funcionalidades Legadas a Preservar](#inventÃ¡rio-de-funcionalidades-legadas-a-preservar)
4. [Objetivo da Fase 7](#objetivo-da-fase-7)
5. [Arquitetura Proposta](#arquitetura-proposta)
6. [ImplementaÃ§Ã£o da Hook Bridge](#implementaÃ§Ã£o-da-hook-bridge)
7. [EstratÃ©gia de MigraÃ§Ã£o](#estratÃ©gia-de-migraÃ§Ã£o)
8. [Novos Shortcodes Nativos](#novos-shortcodes-nativos)
9. [CoexistÃªncia de Shortcodes v1 e v2](#coexistÃªncia-de-shortcodes-v1-e-v2)
10. [Estrutura de Templates](#estrutura-de-templates)
11. [ReutilizaÃ§Ã£o de Helpers Globais do Base](#reutilizaÃ§Ã£o-de-helpers-globais-do-base)
12. [EstratÃ©gia de Testes](#estratÃ©gia-de-testes)
13. [Cronograma de ImplementaÃ§Ã£o](#cronograma-de-implementaÃ§Ã£o)
14. [CritÃ©rios de Aceite](#critÃ©rios-de-aceite)
15. [Riscos e MitigaÃ§Ã£o](#riscos-e-mitigaÃ§Ã£o)

---

## Contexto e MotivaÃ§Ã£o

### Problema Identificado

O Frontend Add-on criado nas Fases 1-6 (PR #581) implementa uma **estratÃ©gia dual-run** onde:

âŒ **LimitaÃ§Ãµes atuais:**
- Reutiliza cÃ³digo legado dos add-ons `desi-pet-shower-registration` e `desi-pet-shower-booking`
- Apenas envolve o output legado em wrapper `.dps-frontend`
- Adiciona CSS DPS Signature por cima do HTML legado (estrutura antiga permanece)
- MantÃ©m dependÃªncias fortes dos add-ons legados
- NÃ£o permite refatoraÃ§Ã£o completa da UX/UI
- Compromete o potencial completo do DPS Signature
- HTML gerado continua com padrÃµes antigos (estrutura, acessibilidade limitada)

âœ… **O que funciona bem:**
- Rollback instantÃ¢neo via feature flags
- Zero quebra de compatibilidade
- TransiÃ§Ã£o gradual e segura
- Telemetria de uso implementada
- DocumentaÃ§Ã£o completa

### MotivaÃ§Ã£o para Fase 7

**Queremos criar pÃ¡ginas 100% novas:**
- âœ¨ HTML semÃ¢ntico moderno (PHP 8.4)
- âœ¨ Estrutura nativa DPS Signature
- âœ¨ UX redesenhada do zero
- âœ¨ Acessibilidade WCAG 2.1 AA nativa
- âœ¨ Performance otimizada (lazy load, code splitting)
- âœ¨ IndependÃªncia dos add-ons legados
- âœ¨ Templates reutilizÃ¡veis e testÃ¡veis
- âœ¨ CÃ³digo limpo seguindo padrÃµes modernos

**Resultado esperado:**
> PÃ¡ginas de cadastro e agendamento completamente novas, construÃ­das from-scratch com DPS Signature, sem nenhuma dependÃªncia ou reutilizaÃ§Ã£o de cÃ³digo legado.

---

## SituaÃ§Ã£o Atual (Fases 1-6)

### Fase 1 â€” FundaÃ§Ã£o âœ…
- Estrutura do add-on criada
- Feature flags implementadas
- Assets DPS Signature carregados condicionalmente
- Logger e telemetria funcionais

### Fase 2 â€” Registration Dual-Run âœ…
- MÃ³dulo `DPS_Frontend_Registration_Module`
- **EstratÃ©gia:** `remove_shortcode()` + wrapper legado
- **ImplementaÃ§Ã£o:**
  ```php
  public function renderShortcode(): string {
      $legacy = DPS_Registration_Addon::get_instance();
      $html = $legacy->render_registration_form();
      return '<div class="dps-frontend">' . $html . '</div>';
  }
  ```
- âš ï¸ **Problema:** HTML Ã© gerado pelo legado, apenas envolto em div

### Fase 3 â€” Booking Dual-Run âœ…
- MÃ³dulo `DPS_Frontend_Booking_Module`
- **EstratÃ©gia:** idÃªntica ao Registration
- **ImplementaÃ§Ã£o:**
  ```php
  public function renderShortcode(): string {
      $legacy = DPS_Booking_Addon::get_instance();
      $html = $legacy->render_booking_form();
      return '<div class="dps-frontend">' . $html . '</div>';
  }
  ```
- âš ï¸ **Problema:** mesma limitaÃ§Ã£o â€” wrapper apenas

### Fase 4 â€” Settings âœ…
- Aba admin para gerenciar feature flags
- Funciona bem (nÃ£o precisa refatoraÃ§Ã£o)

### Fase 5 â€” ConsolidaÃ§Ã£o e Docs âœ…
- Guias operacionais completos
- Matriz de compatibilidade
- Runbooks de incidentes

### Fase 6 â€” GovernanÃ§a de DepreciaÃ§Ã£o âœ…
- PolÃ­tica de 180 dias definida
- Telemetria de uso implementada
- Lista de alvos de remoÃ§Ã£o

### Arquivos Atuais

```
plugins/desi-pet-shower-frontend/
â”œâ”€â”€ desi-pet-shower-frontend-addon.php
â”œâ”€â”€ includes/
â”‚   â”œâ”€â”€ class-dps-frontend-addon.php
â”‚   â”œâ”€â”€ class-dps-frontend-module-registry.php
â”‚   â”œâ”€â”€ class-dps-frontend-compatibility.php
â”‚   â”œâ”€â”€ class-dps-frontend-feature-flags.php
â”‚   â”œâ”€â”€ modules/
â”‚   â”‚   â”œâ”€â”€ class-dps-frontend-registration-module.php  â† DUAL-RUN
â”‚   â”‚   â”œâ”€â”€ class-dps-frontend-booking-module.php       â† DUAL-RUN
â”‚   â”‚   â””â”€â”€ class-dps-frontend-settings-module.php
â”‚   â””â”€â”€ support/
â”‚       â”œâ”€â”€ class-dps-frontend-assets.php
â”‚       â”œâ”€â”€ class-dps-frontend-logger.php
â”‚       â””â”€â”€ class-dps-frontend-request-guard.php
â”œâ”€â”€ templates/                                            â† VAZIO!
â”‚   â””â”€â”€ .gitkeep
â””â”€â”€ assets/
    â”œâ”€â”€ css/
    â”‚   â””â”€â”€ frontend-addon.css                           â† CSS adicional apenas
    â””â”€â”€ js/
```

**Nota crÃ­tica:** O diretÃ³rio `templates/` existe mas estÃ¡ **vazio** â€” nenhum template nativo foi criado!

---

## InventÃ¡rio de Funcionalidades Legadas a Preservar

> **PrincÃ­pio fundamental:** A Fase 7 cria pÃ¡ginas NOVAS com shortcodes NOVOS (`[dps_registration_v2]`, `[dps_booking_v2]`). As pÃ¡ginas antigas com shortcodes legados (`[dps_registration_form]`, `[dps_booking_form]`) continuam funcionando INTACTAS via dual-run (Fases 2-3). Ambos os shortcodes podem coexistir no mesmo site simultaneamente.

### Registration â€” Funcionalidades que o V2 DEVE Reimplementar

O add-on `desi-pet-shower-registration` (v1.3.0) possui funcionalidades que vÃ£o alÃ©m de um formulÃ¡rio simples. O V2 deve atingir **paridade funcional** com todas elas:

| # | Funcionalidade | DescriÃ§Ã£o | Prioridade |
|---|---------------|-----------|------------|
| R1 | **FormulÃ¡rio de cadastro** | Campos: nome, email, telefone, CPF, endereÃ§o, pets (nome, espÃ©cie, raÃ§a, porte, observaÃ§Ãµes) | P0 â€” ObrigatÃ³rio |
| R2 | **ValidaÃ§Ã£o CPF** | Algoritmo Mod-11 via `validate_cpf()` + normalizaÃ§Ã£o `normalize_cpf()`. Opcional mas se preenchido deve ser vÃ¡lido | P0 â€” ObrigatÃ³rio |
| R3 | **ValidaÃ§Ã£o/NormalizaÃ§Ã£o de telefone** | Via `DPS_Phone_Helper` do base. Formato brasileiro padrÃ£o | P0 â€” ObrigatÃ³rio |
| R4 | **DetecÃ§Ã£o de duplicatas (phone-based)** | `find_duplicate_client()` â€” busca APENAS por telefone (email/CPF ignorados desde v1.3.0). Bloqueia registro duplicado para nÃ£o-admin | P0 â€” ObrigatÃ³rio |
| R5 | **reCAPTCHA v3** | IntegraÃ§Ã£o Google reCAPTCHA v3 com score threshold configurÃ¡vel. Options: `dps_registration_recaptcha_enabled/site_key/secret_key/threshold` | P1 â€” Importante |
| R6 | **ConfirmaÃ§Ã£o de email (48h)** | Token UUID com TTL de 48h. Metadata: `dps_email_confirmed`, `dps_email_confirm_token`, `dps_email_confirm_token_created`. ParÃ¢metro URL: `dps_confirm_email` | P1 â€” Importante |
| R7 | **Lembretes de confirmaÃ§Ã£o (cron)** | `CONFIRMATION_REMINDER_CRON` â€” envia lembretes para registros nÃ£o confirmados apÃ³s 24h | P1 â€” Importante |
| R8 | **Dataset de raÃ§as** | `get_breed_dataset()` â€” raÃ§as organizadas por espÃ©cie (cÃ£o/gato), com "populares" priorizadas. Usado em datalist | P1 â€” Importante |
| R9 | **Google Maps Autocomplete** | Places API para endereÃ§o com campos ocultos de coordenadas. Requer `dps_google_api_key` | P2 â€” DesejÃ¡vel |
| R10 | **Admin quick-registration (F3.2)** | Cadastro rÃ¡pido pelo painel admin com checkbox `dps_admin_skip_confirmation` | P2 â€” DesejÃ¡vel |
| R11 | **REST API** | Endpoint via `register_rest_route()` com autenticaÃ§Ã£o por API key (`dps_registration_api_key`), rate limiting por IP (max requests/min configurÃ¡vel), e validaÃ§Ã£o server-side completa. Path: `dps/v1/register`. Segue padrÃ£o WP REST (nonce OU API key) | P2 â€” DesejÃ¡vel |
| R12 | **Anti-spam** | Hook `dps_registration_spam_check` (filter) para validaÃ§Ãµes adicionais | P1 â€” Importante |
| R13 | **Marketing opt-in** | Checkbox de consentimento para comunicaÃ§Ãµes | P1 â€” Importante |

### Registration â€” Hooks que o V2 DEVE Disparar (via Bridge)

| Hook | Tipo | Args | Consumidor | CrÃ­tico |
|------|------|------|-----------|---------|
| `dps_registration_after_fields` | action | 0 | Loyalty (render_registration_field) | âš ï¸ Sim |
| `dps_registration_after_client_created` | action | 4: `$referral_code, $client_id, $email, $phone` | Loyalty (maybe_register_referral) | âš ï¸ Sim |
| `dps_registration_spam_check` | filter | 2: `$valid, $context` | Anti-spam externo | âš ï¸ Sim |
| `dps_registration_agenda_url` | filter | 1: `$fallback_url` | URL override | NÃ£o |
| `dps_registration_v2_before_render` | action | 1: `$atts` | **NOVO** â€” extensibilidade | â€” |
| `dps_registration_v2_after_render` | action | 1: `$html` | **NOVO** â€” extensibilidade | â€” |
| `dps_registration_v2_before_process` | action | 1: `$data` | **NOVO** â€” extensibilidade | â€” |
| `dps_registration_v2_after_process` | action | 2: `$result, $data` | **NOVO** â€” extensibilidade | â€” |
| `dps_registration_v2_client_created` | action | 2: `$client_id, $data` | **NOVO** â€” extensibilidade | â€” |
| `dps_registration_v2_pet_created` | action | 3: `$pet_id, $client_id, $data` | **NOVO** â€” extensibilidade | â€” |

### Booking â€” Funcionalidades que o V2 DEVE Reimplementar

O add-on `desi-pet-shower-booking` (v1.3.0) possui funcionalidades especializadas:

| # | Funcionalidade | DescriÃ§Ã£o | Prioridade |
|---|---------------|-----------|------------|
| B1 | **Wizard multi-step** | 5 steps: cliente â†’ pet â†’ serviÃ§o â†’ data/hora â†’ confirmaÃ§Ã£o | P0 â€” ObrigatÃ³rio |
| B2 | **3 tipos de agendamento** | `simple` (avulso), `subscription` (recorrente semanal/quinzenal), `past` (registro retroativo) | P0 â€” ObrigatÃ³rio |
| B3 | **Busca cliente por telefone** | AJAX search com seleÃ§Ã£o de cliente existente | P0 â€” ObrigatÃ³rio |
| B4 | **Multi-pet com paginaÃ§Ã£o** | SeleÃ§Ã£o mÃºltipla de pets com "Carregar mais" e query paginada (`$pets_query->max_num_pages`) | P0 â€” ObrigatÃ³rio |
| B5 | **SeleÃ§Ã£o de serviÃ§os** | Lista de serviÃ§os disponÃ­veis com preÃ§os | P0 â€” ObrigatÃ³rio |
| B6 | **CalendÃ¡rio de disponibilidade** | SeleÃ§Ã£o de data/hora com validaÃ§Ã£o de conflitos | P0 â€” ObrigatÃ³rio |
| B7 | **TaxiDog** | Checkbox "Solicitar TaxiDog?" + campo de preÃ§o. Metas: `appointment_taxidog`, `appointment_taxidog_price` | P1 â€” Importante |
| B8 | **Tosa (extras)** | Para assinaturas: checkbox tosa + preÃ§o (default R$30) + dropdown ocorrÃªncia. Metas: `appointment_tosa`, `appointment_tosa_price`, `appointment_tosa_occurrence` | P1 â€” Importante |
| B9 | **ConfirmaÃ§Ã£o via transient** | `dps_booking_confirmation_{user_id}` com TTL 5min. Dados: appointment_id, type, timestamp. Nota: transients sÃ£o server-side (DB/object cache), nÃ£o expostos ao cliente. PadrÃ£o mantido do legado por compatibilidade â€” user_id vem de `get_current_user_id()` (autenticado) | P0 â€” ObrigatÃ³rio |
| B10 | **Controle de permissÃµes** | `manage_options`, `dps_manage_clients`, `dps_manage_pets`, `dps_manage_appointments`. Login obrigatÃ³rio | P0 â€” ObrigatÃ³rio |
| B11 | **Login check** | Redireciona para login se `!is_user_logged_in()` | P0 â€” ObrigatÃ³rio |
| B12 | **Cache control** | `DPS_Cache_Control::force_no_cache()` para desabilitar cache em pÃ¡ginas de booking | P0 â€” ObrigatÃ³rio |
| B13 | **Editar/duplicar agendamentos** | Suporte a `$edit_id` para ediÃ§Ã£o de appointments existentes | P1 â€” Importante |
| B14 | **Skip REST/AJAX** | Retorna vazio se `REST_REQUEST` ou `wp_doing_ajax()` para evitar renderizaÃ§Ã£o acidental | P0 â€” ObrigatÃ³rio |

### Booking â€” Hooks que o V2 DEVE Disparar (via Bridge)

| Hook | Tipo | Args | Consumidores (8 add-ons) | CrÃ­tico |
|------|------|------|-------------------------|---------|
| `dps_base_after_save_appointment` | action | 2: `$appointment_id, $meta` | Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking | âš ï¸ **CRÃTICO** |
| `dps_base_appointment_fields` | action | 2: `$edit_id, $meta` | Services (injeÃ§Ã£o de campos) | âš ï¸ Sim |
| `dps_base_appointment_assignment_fields` | action | 2: `$edit_id, $meta` | Groomers (campos de atribuiÃ§Ã£o) | âš ï¸ Sim |
| `dps_booking_v2_before_render` | action | 1: `$atts` | **NOVO** â€” extensibilidade | â€” |
| `dps_booking_v2_step_render` | action | 2: `$step, $data` | **NOVO** â€” extensibilidade | â€” |
| `dps_booking_v2_step_validate` | filter | 3: `$valid, $step, $data` | **NOVO** â€” extensibilidade | â€” |
| `dps_booking_v2_before_process` | action | 1: `$data` | **NOVO** â€” extensibilidade | â€” |
| `dps_booking_v2_after_process` | action | 2: `$result, $data` | **NOVO** â€” extensibilidade | â€” |
| `dps_booking_v2_appointment_created` | action | 2: `$appointment_id, $data` | **NOVO** â€” extensibilidade | â€” |

### Options/Settings que o V2 Deve Respeitar

| Option | Uso | Origem |
|--------|-----|--------|
| `dps_registration_page_id` | ID da pÃ¡gina de cadastro | Base settings |
| `dps_booking_page_id` | ID da pÃ¡gina de agendamento | Base settings |
| `dps_registration_recaptcha_enabled` | Toggle reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_site_key` | Chave pÃºblica reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_secret_key` | Chave secreta reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_threshold` | Score mÃ­nimo (0-1) | Registration settings |
| `dps_google_api_key` | API key Google Maps | Base settings |
| `dps_registration_confirm_email_enabled` | Toggle confirmaÃ§Ã£o email | Registration settings |
| `dps_frontend_feature_flags` | Feature flags do frontend | Frontend settings |

---

## Objetivo da Fase 7

### VisÃ£o

**Criar implementaÃ§Ãµes 100% nativas** dos formulÃ¡rios de cadastro e agendamento, **do zero**, sem reutilizar cÃ³digo legado.

### Metas EspecÃ­ficas

#### 1. Novos Shortcodes Nativos

Criar shortcodes completamente novos que nÃ£o dependam dos legados:

- `[dps_registration_v2]` â€” cadastro nativo DPS Signature
- `[dps_booking_v2]` â€” agendamento nativo DPS Signature
- `[dps_client_portal]` â€” portal do cliente (futuro)

#### 2. Templates Modernos

Criar sistema de templates reutilizÃ¡veis:

```
templates/
â”œâ”€â”€ registration/
â”‚   â”œâ”€â”€ form-main.php              â† FormulÃ¡rio principal
â”‚   â”œâ”€â”€ form-client-data.php       â† SeÃ§Ã£o dados do cliente
â”‚   â”œâ”€â”€ form-pet-data.php          â† SeÃ§Ã£o dados do pet
â”‚   â”œâ”€â”€ form-success.php           â† Tela de sucesso
â”‚   â””â”€â”€ form-error.php             â† Tela de erro
â”œâ”€â”€ booking/
â”‚   â”œâ”€â”€ form-main.php
â”‚   â”œâ”€â”€ step-client-selection.php
â”‚   â”œâ”€â”€ step-pet-selection.php
â”‚   â”œâ”€â”€ step-service-selection.php
â”‚   â”œâ”€â”€ step-datetime-selection.php
â”‚   â”œâ”€â”€ step-confirmation.php
â”‚   â””â”€â”€ form-success.php
â””â”€â”€ components/
    â”œâ”€â”€ field-text.php
    â”œâ”€â”€ field-select.php
    â”œâ”€â”€ field-phone.php
    â”œâ”€â”€ field-email.php
    â”œâ”€â”€ button-primary.php
    â”œâ”€â”€ button-secondary.php
    â”œâ”€â”€ card.php
    â”œâ”€â”€ alert.php
    â””â”€â”€ loader.php
```

#### 3. Handlers Nativos

Criar processadores de formulÃ¡rio independentes:

```
includes/
â”œâ”€â”€ handlers/
â”‚   â”œâ”€â”€ class-dps-registration-handler.php     â† Processa cadastro
â”‚   â”œâ”€â”€ class-dps-booking-handler.php          â† Processa agendamento
â”‚   â””â”€â”€ class-dps-form-validator.php           â† ValidaÃ§Ã£o centralizada
â”œâ”€â”€ services/
â”‚   â”œâ”€â”€ class-dps-client-service.php           â† CRUD de clientes
â”‚   â”œâ”€â”€ class-dps-pet-service.php              â† CRUD de pets
â”‚   â”œâ”€â”€ class-dps-appointment-service.php      â† CRUD de agendamentos
â”‚   â”œâ”€â”€ class-dps-breed-provider.php           â† Dataset de raÃ§as por espÃ©cie
â”‚   â”œâ”€â”€ class-dps-recaptcha-service.php        â† VerificaÃ§Ã£o reCAPTCHA v3
â”‚   â”œâ”€â”€ class-dps-email-confirmation-service.php â† Tokens 48h + cron lembretes
â”‚   â”œâ”€â”€ class-dps-duplicate-detector.php       â† DetecÃ§Ã£o duplicatas (phone-based)
â”‚   â””â”€â”€ class-dps-booking-confirmation-service.php â† Transient de confirmaÃ§Ã£o
â”œâ”€â”€ bridges/
â”‚   â”œâ”€â”€ class-dps-registration-hook-bridge.php â† Bridge hooks registration (Loyalty)
â”‚   â””â”€â”€ class-dps-booking-hook-bridge.php      â† Bridge hooks booking (8 add-ons)
â”œâ”€â”€ validators/
â”‚   â”œâ”€â”€ class-dps-cpf-validator.php            â† ValidaÃ§Ã£o CPF mod-11
â”‚   â””â”€â”€ class-dps-booking-validator.php        â† ValidaÃ§Ãµes complexas booking
â””â”€â”€ ajax/
    â”œâ”€â”€ class-dps-ajax-client-search.php       â† Busca cliente por telefone
    â”œâ”€â”€ class-dps-ajax-pet-list.php            â† Lista pets do cliente (paginado)
    â”œâ”€â”€ class-dps-ajax-available-slots.php     â† HorÃ¡rios disponÃ­veis
    â”œâ”€â”€ class-dps-ajax-services-list.php       â† ServiÃ§os disponÃ­veis com preÃ§os
    â””â”€â”€ class-dps-ajax-validate-step.php       â† ValidaÃ§Ã£o de step server-side
```

#### 4. Assets Nativos DPS Signature Completos

```
assets/
â”œâ”€â”€ css/
â”‚   â”œâ”€â”€ registration-v2.css        â† CSS nativo cadastro DPS Signature
â”‚   â”œâ”€â”€ booking-v2.css             â† CSS nativo agendamento DPS Signature
â”‚   â””â”€â”€ components.css             â† Componentes reutilizÃ¡veis
â””â”€â”€ js/
    â”œâ”€â”€ registration-v2.js         â† JS nativo cadastro
    â”œâ”€â”€ booking-v2.js              â† JS nativo agendamento
    â””â”€â”€ form-utils.js              â† UtilitÃ¡rios compartilhados
```

#### 5. IndependÃªncia Total

**Remover todas as dependÃªncias dos add-ons legados:**
- âŒ NÃ£o chamar `DPS_Registration_Addon::get_instance()`
- âŒ NÃ£o chamar `DPS_Booking_Addon::get_instance()`
- âŒ NÃ£o delegar para mÃ©todos legados
- âœ… Implementar toda lÃ³gica nativamente
- âœ… Reutilizar apenas helpers globais do base (`DPS_Money_Helper`, etc.)

---

## Arquitetura Proposta

### PrincÃ­pios Arquiteturais

1. **Separation of Concerns**
   - Templates = apresentaÃ§Ã£o pura
   - Handlers = lÃ³gica de negÃ³cio
   - Services = acesso a dados
   - Validators = regras de validaÃ§Ã£o

2. **Dependency Injection**
   - Sem singletons
   - ComposiÃ§Ã£o via construtor
   - Testabilidade

3. **Modern PHP 8.4**
   - Constructor promotion
   - Readonly properties
   - Typed properties
   - Return types
   - Enums para estados

4. **DPS Signature Native**
   - HTML semÃ¢ntico desde o inÃ­cio
   - Design tokens CSS em todos os componentes
   - Acessibilidade ARIA nativa
   - Motion expressivo opcional

### Diagrama de Fluxo â€” Registration V2

```
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ [dps_registration_v2] shortcode                             â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                 â”‚
                 â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ DPS_Frontend_Registration_V2_Module                         â”‚
â”‚  â””â”€ renderShortcode()                                       â”‚
â”‚      â”œâ”€ Valida nonce se POST                                â”‚
â”‚      â”œâ”€ Se GET: renderiza form (templates/registration/)    â”‚
â”‚      â””â”€ Se POST: processa via Handler                       â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                 â”‚
    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
    â”‚ POST?                   â”‚ GET?
    â–¼                         â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”  â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ Registration Handler â”‚  â”‚ Template Engine         â”‚
â”‚  â””â”€ process()        â”‚  â”‚  â””â”€ render_form_main()  â”‚
â”‚     â”œâ”€ Valida dados  â”‚  â”‚     â”œâ”€ form-client-data â”‚
â”‚     â”œâ”€ Sanitiza      â”‚  â”‚     â”œâ”€ form-pet-data    â”‚
â”‚     â”œâ”€ Cria cliente  â”‚  â”‚     â””â”€ Components       â”‚
â”‚     â”œâ”€ Cria pet(s)   â”‚  â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
â”‚     â”œâ”€ Dispara hooks â”‚
â”‚     â””â”€ Retorna resultâ”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
           â”‚
           â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ Client Service                   â”‚
â”‚  â””â”€ createClient()               â”‚
â”‚     â””â”€ wp_insert_post()          â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
           â”‚
           â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ Pet Service                      â”‚
â”‚  â””â”€ createPet()                  â”‚
â”‚     â””â”€ wp_insert_post()          â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
           â”‚
           â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ Hooks de IntegraÃ§Ã£o              â”‚
â”‚  â”œâ”€ dps_registration_v2_created  â”‚ â† NOVO
â”‚  â””â”€ dps_base_after_client_create â”‚ â† Reutiliza base
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
           â”‚
           â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ Success Template                 â”‚
â”‚  â””â”€ templates/registration/      â”‚
â”‚      form-success.php            â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
```

### Diagrama de Fluxo â€” Booking V2

```
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ [dps_booking_v2] shortcode                                  â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                 â”‚
                 â–¼
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ DPS_Frontend_Booking_V2_Module                              â”‚
â”‚  â””â”€ renderShortcode()                                       â”‚
â”‚      â”œâ”€ Detecta step atual (query param ?step=X)            â”‚
â”‚      â”œâ”€ Renderiza step apropriado                           â”‚
â”‚      â””â”€ Processa transiÃ§Ã£o entre steps                      â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                 â”‚
        â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”´â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
        â–¼                 â–¼        â–¼         â–¼          â–¼
    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”      â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â” â”Œâ”€â”€â”€â”€â”€â”€â” â”Œâ”€â”€â”€â”€â”€â”€â” â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”
    â”‚ Step 1  â”‚      â”‚ Step 2 â”‚ â”‚Step 3â”‚ â”‚Step 4â”‚ â”‚ Step 5  â”‚
    â”‚ Cliente â”‚  â†’   â”‚  Pet   â”‚ â†’â”‚ServiÃ§oâ”‚â†’â”‚Data â”‚ â†’â”‚Confirmaâ”‚
    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜      â””â”€â”€â”€â”€â”€â”€â”€â”€â”˜ â””â”€â”€â”€â”€â”€â”€â”˜ â””â”€â”€â”€â”€â”€â”€â”˜ â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
         â”‚                                              â”‚
         â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                              â–¼
                    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                    â”‚ Booking Handler     â”‚
                    â”‚  â””â”€ process()       â”‚
                    â”‚     â”œâ”€ Valida tudo  â”‚
                    â”‚     â”œâ”€ Cria appoint.â”‚
                    â”‚     â”œâ”€ Dispara hooksâ”‚
                    â”‚     â””â”€ Email confirmâ”‚
                    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                               â”‚
                               â–¼
                    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                    â”‚ Appointment Service           â”‚
                    â”‚  â””â”€ createAppointment()       â”‚
                    â”‚     â””â”€ wp_insert_post()       â”‚
                    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                                â”‚
                                â–¼
                    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                    â”‚ Hooks de IntegraÃ§Ã£o           â”‚
                    â”‚  â”œâ”€ dps_booking_v2_created    â”‚ â† NOVO
                    â”‚  â””â”€ dps_base_after_save_appt  â”‚ â† MantÃ©m
                    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
                                â”‚
                                â–¼
                    â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                    â”‚ Success Template + Email      â”‚
                    â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
```

---

## ImplementaÃ§Ã£o da Hook Bridge

### Conceito

A hook bridge Ã© o mecanismo que garante **compatibilidade retroativa** durante a coexistÃªncia v1/v2. Quando o v2 processa uma aÃ§Ã£o (criar cliente, criar pet, criar agendamento), ele dispara **AMBOS** os hooks: o novo (v2) e o legado, garantindo que add-ons existentes (Loyalty, Stock, Payment, etc.) continuem funcionando sem alteraÃ§Ãµes.

### ImplementaÃ§Ã£o â€” Registration Hook Bridge

```php
class DPS_Registration_Hook_Bridge {

    /**
     * Dispara hooks apÃ³s criaÃ§Ã£o de cliente no v2.
     * MantÃ©m compatibilidade com Loyalty e outros add-ons.
     */
    public function afterClientCreated(
        int $client_id,
        string $email,
        string $phone,
        string $referral_code = ''
    ): void {
        // 1. Hook LEGADO primeiro (para Loyalty e outros add-ons existentes)
        // Assinatura IDÃŠNTICA ao legado: ($referral_code, $client_id, $email, $phone)
        do_action(
            'dps_registration_after_client_created',
            $referral_code,
            $client_id,
            $email,
            $phone
        );

        // 2. Hook NOVO v2 (para novos consumidores futuros)
        do_action( 'dps_registration_v2_client_created', $client_id, [
            'email'         => $email,
            'phone'         => $phone,
            'referral_code' => $referral_code,
        ] );
    }

    /**
     * Dispara hook de campos adicionais no formulÃ¡rio.
     * Permite que Loyalty injete campo de referral code.
     */
    public function afterFormFields(): void {
        // Hook legado (Loyalty: render_registration_field)
        do_action( 'dps_registration_after_fields' );
    }

    /**
     * Aplica filtro anti-spam.
     * Permite validaÃ§Ãµes externas adicionais.
     */
    public function applySpamCheck( bool $valid, array $context ): bool {
        return apply_filters( 'dps_registration_spam_check', $valid, $context );
    }
}
```

### ImplementaÃ§Ã£o â€” Booking Hook Bridge

```php
class DPS_Booking_Hook_Bridge {

    /**
     * Dispara hooks apÃ³s criaÃ§Ã£o de agendamento no v2.
     * CRÃTICO: 8 add-ons consomem dps_base_after_save_appointment.
     */
    public function afterAppointmentCreated(
        int $appointment_id,
        array $meta
    ): void {
        // 1. Hook LEGADO CRÃTICO primeiro (8 consumidores existentes)
        // Assinatura IDÃŠNTICA: ($appointment_id, $meta)
        // Consumidores: Stock, Payment, Groomers, Calendar,
        //               Communications, Push, Services, Booking
        do_action( 'dps_base_after_save_appointment', $appointment_id, $meta );

        // 2. Hook NOVO v2 (para extensÃµes futuras)
        do_action( 'dps_booking_v2_appointment_created', $appointment_id, $meta );
    }

    /**
     * Dispara hooks de campos do agendamento.
     * Permite que Services e Groomers injetem campos.
     */
    public function appointmentFields( int $edit_id, array $meta ): void {
        do_action( 'dps_base_appointment_fields', $edit_id, $meta );
        do_action( 'dps_base_appointment_assignment_fields', $edit_id, $meta );
    }

    /**
     * Dispara hooks de validaÃ§Ã£o de step (filtro novo).
     * Permite validaÃ§Ãµes externas por step.
     */
    public function validateStep( bool $valid, int $step, array $data ): bool {
        return apply_filters( 'dps_booking_v2_step_validate', $valid, $step, $data );
    }
}
```

### Regras da Hook Bridge

1. **Ordem de disparo:** Hook legado PRIMEIRO, hook v2 DEPOIS. Justificativa: os add-ons existentes (Loyalty, Stock, Payment, etc.) jÃ¡ consomem os hooks legados â€” se disparamos o legado antes, garantimos que o comportamento atual Ã© preservado sem regressÃµes. Hooks v2 disparam depois para extensÃµes futuras que possam querer atuar sobre o resultado jÃ¡ processado. Esta ordem Ã© intencional e NÃƒO deve ser invertida sem anÃ¡lise de impacto
2. **Assinatura idÃªntica:** Os hooks legados DEVEM receber exatamente os mesmos argumentos/tipos do legado
3. **Sem condicionais:** A bridge SEMPRE dispara ambos os hooks â€” nÃ£o importa se hÃ¡ consumidores ou nÃ£o
4. **Testes obrigatÃ³rios:** Cada hook bridge deve ter teste que valida disparo de ambos os hooks na ordem correta
5. **Monitoramento:** Logger deve registrar cada disparo de hook bridge para telemetria

---

## EstratÃ©gia de MigraÃ§Ã£o

### Fase 7.1 â€” PreparaÃ§Ã£o (Sprint 1-2)

**Objetivo:** Estruturar arquitetura sem quebrar nada

âœ… **Tarefas:**
1. Criar estrutura de diretÃ³rios (`templates/`, `handlers/`, `services/`, `ajax/`, `bridges/`)
2. Implementar classes base abstratas:
   - `Abstract_Module_V2` â€” base para mÃ³dulos nativos
   - `Abstract_Handler` â€” base para handlers
   - `Abstract_Service` â€” base para services
   - `Abstract_Validator` â€” base para validadores
3. Criar sistema de template engine simples
4. Implementar componentes reutilizÃ¡veis bÃ¡sicos (button, field, card, alert)
5. Implementar Hook Bridge base (classes `DPS_Registration_Hook_Bridge` e `DPS_Booking_Hook_Bridge`)
6. Documentar padrÃµes de cÃ³digo e convenÃ§Ãµes

âœ… **Feature Flags:**
- Criar nova flag `registration_v2` (desabilitada por padrÃ£o)
- Criar nova flag `booking_v2` (desabilitada por padrÃ£o)
- Manter flags antigas (`registration`, `booking`) funcionando
- **Importante:** flags v1 e v2 sÃ£o independentes â€” ambas podem estar ativas ao mesmo tempo (coexistÃªncia)

âœ… **CritÃ©rios de Aceite:**
- [x] Estrutura de diretÃ³rios criada (incluindo `bridges/`)
- [x] Classes base implementadas
- [x] Template engine funcional
- [x] 5+ componentes reutilizÃ¡veis prontos
- [x] Feature flags novas criadas
- [x] Hook Bridge base implementada e testada
- [x] Zero quebra de funcionalidade existente

### Fase 7.2 â€” Registration V2 (Sprint 3-5)

**Objetivo:** ImplementaÃ§Ã£o nativa completa do cadastro com paridade funcional ao legado

> **ReferÃªncia:** Ver [InventÃ¡rio de Funcionalidades Legadas â€” Registration](#registration--funcionalidades-que-o-v2-deve-reimplementar) para a lista completa de features R1-R13.

âœ… **Tarefas:**
1. **Templates Registration:**
   - `form-main.php` â€” estrutura principal
   - `form-client-data.php` â€” campos do cliente (nome, email, telefone, CPF, endereÃ§o)
   - `form-pet-data.php` â€” campos do pet (repeater: nome, espÃ©cie, raÃ§a com datalist, porte, observaÃ§Ãµes)
   - `form-success.php` â€” sucesso (com CTA para agendamento)
   - `form-error.php` â€” erro
   - `form-duplicate-warning.php` â€” aviso de telefone duplicado (com opÃ§Ã£o admin override)

2. **Handler e Services:**
   - `DPS_Registration_Handler` â€” processa formulÃ¡rio
   - `DPS_Client_Service` â€” CRUD de clientes (wp_insert_post)
   - `DPS_Pet_Service` â€” CRUD de pets (wp_insert_post + metas: espÃ©cie, raÃ§a, porte)
   - `DPS_Form_Validator` â€” validaÃ§Ãµes (CPF mod-11, telefone, email, required)
   - `DPS_Duplicate_Detector` â€” busca por telefone (phone-only, conforme legado v1.3.0)
   - `DPS_Breed_Provider` â€” dataset de raÃ§as por espÃ©cie (reutilizar `get_breed_dataset()` do legado)

3. **IntegraÃ§Ãµes de SeguranÃ§a:**
   - reCAPTCHA v3 â€” ler options `dps_registration_recaptcha_*`, validar server-side
   - Anti-spam â€” aplicar filtro `dps_registration_spam_check` via Hook Bridge
   - Duplicate detection â€” bloquear se telefone duplicado (non-admin)
   - Nonce + capability check + sanitizaÃ§Ã£o completa

4. **Email e ConfirmaÃ§Ã£o:**
   - ConfirmaÃ§Ã£o de email 48h (reutilizar lÃ³gica de token UUID)
   - HTML template de email DPS Signature para confirmaÃ§Ã£o
   - Cron de lembretes (registrar `CONFIRMATION_REMINDER_CRON` se nÃ£o existir)
   - Respeitar option `dps_registration_confirm_email_enabled`

5. **Hook Bridge Registration (CRÃTICO):**
   - Integrar `DPS_Registration_Hook_Bridge` em todos os pontos
   - Disparar `dps_registration_after_fields` no template do formulÃ¡rio
   - Disparar `dps_registration_after_client_created` apÃ³s criaÃ§Ã£o (4 args)
   - Aplicar `dps_registration_spam_check` antes de processar
   - Testes de integraÃ§Ã£o com Loyalty add-on

6. **Assets Nativos:**
   - `registration-v2.css` â€” estilos DPS Signature puros
   - `registration-v2.js` â€” comportamento nativo (validaÃ§Ã£o client-side, repeater de pets, datalist de raÃ§as)
   - IntegraÃ§Ã£o com design tokens
   - Condicional: Google Maps Places API se `dps_google_api_key` configurada

7. **MÃ³dulo V2:**
   - `DPS_Frontend_Registration_V2_Module`
   - Shortcode `[dps_registration_v2]`
   - Zero dependÃªncia do legado (usa serviÃ§os e helpers nativos)

8. **Hooks Novos + Bridge:**
   - `dps_registration_v2_before_render` â€” antes de renderizar form
   - `dps_registration_v2_after_render` â€” depois de renderizar form
   - `dps_registration_v2_before_process` â€” antes de processar
   - `dps_registration_v2_after_process` â€” depois de processar
   - `dps_registration_v2_client_created` â€” cliente criado
   - `dps_registration_v2_pet_created` â€” pet criado
   - **Bridge:** `dps_registration_after_client_created` (4 args â€” Loyalty)
   - **Bridge:** `dps_registration_after_fields` (0 args â€” Loyalty)
   - **Bridge:** `dps_registration_spam_check` (filter â€” anti-spam)

9. **ValidaÃ§Ã£o e Testes:**
   - Testes funcionais completos (ver [EstratÃ©gia de Testes](#estratÃ©gia-de-testes))
   - ValidaÃ§Ã£o WCAG 2.1 AA
   - Performance benchmark
   - Teste em mobile/tablet/desktop
   - Teste de integraÃ§Ã£o com Loyalty add-on (referral code)
   - Teste de reCAPTCHA v3 (se habilitado)
   - Teste de email confirmation flow

âœ… **CritÃ©rios de Aceite:**
- [x] FormulÃ¡rio renderiza 100% nativo (HTML DPS Signature)
- [x] Processa cadastro sem chamar add-on legado
- [x] Cria cliente e pet corretamente (wp_insert_post + metas)
- [x] Valida todos os campos (client-side + server-side): nome, email, telefone, CPF (mod-11)
- [x] DetecÃ§Ã£o de duplicatas por telefone funciona (bloqueio + admin override)
- [x] reCAPTCHA v3 integrado (quando habilitado nas options)
- [x] ConfirmaÃ§Ã£o de email 48h funciona (token + cron de lembretes)
- [x] Dataset de raÃ§as por espÃ©cie funciona (datalist)
- [ ] Google Maps autocomplete funciona (quando API key presente) â€” *P2 DesejÃ¡vel, adiado para futuro*
- [x] Dispara hooks de integraÃ§Ã£o via bridge (Loyalty referral funcional)
- [x] Anti-spam filter `dps_registration_spam_check` aplicado
- [x] CSS 100% design tokens DPS Signature
- [x] JavaScript vanilla (zero jQuery)
- [x] Acessibilidade WCAG 2.1 AA
- [x] Rollback instantÃ¢neo (flag `registration_v2`)
- [x] Shortcode legado `[dps_registration_form]` continua funcionando intacto

### Fase 7.3 â€” Booking V2 (Sprint 6-10)

**Objetivo:** ImplementaÃ§Ã£o nativa completa do agendamento com paridade funcional ao legado

> **ReferÃªncia:** Ver [InventÃ¡rio de Funcionalidades Legadas â€” Booking](#booking--funcionalidades-que-o-v2-deve-reimplementar) para a lista completa de features B1-B14.

âœ… **Tarefas:**
1. **Templates Booking (Multi-step):**
   - `form-main.php` â€” wizard container
   - `step-client-selection.php` â€” Step 1: busca/seleÃ§Ã£o cliente (AJAX)
   - `step-pet-selection.php` â€” Step 2: seleÃ§Ã£o de pets (com paginaÃ§Ã£o "Carregar mais")
   - `step-service-selection.php` â€” Step 3: escolha de serviÃ§os com preÃ§os
   - `step-datetime-selection.php` â€” Step 4: data/hora com validaÃ§Ã£o de conflitos
   - `step-confirmation.php` â€” Step 5: revisÃ£o final com resumo de preÃ§os
   - `step-extras.php` â€” **NOVO**: TaxiDog + Tosa (extras condicionais por tipo)
   - `form-success.php` â€” confirmaÃ§Ã£o pÃ³s-criaÃ§Ã£o
   - `form-login-required.php` â€” **NOVO**: tela de redirecionamento para login

2. **Tipos de Agendamento (3 modos):**
   - `simple` â€” agendamento avulso (padrÃ£o)
   - `subscription` â€” agendamento recorrente (semanal/quinzenal), com extras de tosa
   - `past` â€” registro retroativo de serviÃ§o jÃ¡ realizado
   - Seletor de tipo no Step 1 ou como atributo do shortcode

3. **Handler e Services:**
   - `DPS_Booking_Handler` â€” processa wizard (state machine)
   - `DPS_Appointment_Service` â€” CRUD de agendamentos (wp_insert_post + metas)
   - `DPS_Service_Availability_Service` â€” horÃ¡rios disponÃ­veis com validaÃ§Ã£o de conflitos
   - `DPS_Booking_Validator` â€” validaÃ§Ãµes complexas (conflitos, permissÃµes, limites)
   - `DPS_Booking_Confirmation_Service` â€” gerencia transient de confirmaÃ§Ã£o (`dps_booking_confirmation_{user_id}`, TTL 5min)

4. **Controle de Acesso:**
   - Login obrigatÃ³rio (`is_user_logged_in()`) â€” redireciona para `wp_login_url()` com return
   - Capabilities: `manage_options` OU `dps_manage_clients` OU `dps_manage_pets` OU `dps_manage_appointments`
   - Skip em REST_REQUEST e wp_doing_ajax() (evitar renderizaÃ§Ã£o acidental)
   - Cache control: `DPS_Cache_Control::force_no_cache()` na pÃ¡gina de booking

5. **Extras â€” TaxiDog e Tosa:**
   - TaxiDog: checkbox + campo de preÃ§o (metas: `appointment_taxidog`, `appointment_taxidog_price`)
   - Tosa: apenas para `subscription` â€” checkbox + preÃ§o (default R$30) + dropdown de ocorrÃªncia
   - Metas: `appointment_tosa`, `appointment_tosa_price`, `appointment_tosa_occurrence`
   - UI: card estilizado DPS Signature com Ã­cones e descriÃ§Ã£o

6. **AJAX Endpoints:**
   - `wp_ajax_dps_search_client` â€” busca cliente por telefone
   - `wp_ajax_dps_get_pets` â€” lista pets do cliente (com paginaÃ§Ã£o)
   - `wp_ajax_dps_get_services` â€” serviÃ§os disponÃ­veis com preÃ§os
   - `wp_ajax_dps_get_slots` â€” horÃ¡rios livres para data selecionada
   - `wp_ajax_dps_validate_step` â€” valida step atual server-side
   - Todos com nonce + capability check + sanitizaÃ§Ã£o

7. **Assets Nativos:**
   - `booking-v2.css` â€” estilos DPS Signature wizard
   - `booking-v2.js` â€” wizard state machine (vanilla JS)
   - AnimaÃ§Ãµes de transiÃ§Ã£o entre steps (`prefers-reduced-motion` respeitado)

8. **MÃ³dulo V2:**
   - `DPS_Frontend_Booking_V2_Module`
   - Shortcode `[dps_booking_v2]`
   - State management para wizard (sessÃ£o + URL query param `?step=X`)
   - Suporte a ediÃ§Ã£o/duplicaÃ§Ã£o (`$edit_id` via atributo ou query param)

9. **Hooks Novos + Bridge (CRÃTICO):**
   - `dps_booking_v2_before_render` â€” antes de renderizar
   - `dps_booking_v2_step_render` â€” ao renderizar step
   - `dps_booking_v2_step_validate` â€” validaÃ§Ã£o de step (filter)
   - `dps_booking_v2_before_process` â€” antes de criar appointment
   - `dps_booking_v2_after_process` â€” depois de criar
   - `dps_booking_v2_appointment_created` â€” appointment criado
   - **Bridge CRÃTICA:** `dps_base_after_save_appointment` (8 consumidores: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking)
   - **Bridge:** `dps_base_appointment_fields` (Services â€” injeÃ§Ã£o de campos)
   - **Bridge:** `dps_base_appointment_assignment_fields` (Groomers â€” campos de atribuiÃ§Ã£o)

10. **IntegraÃ§Ãµes CrÃ­ticas (via Hook Bridge):**
    - Stock (consumo de produtos) â€” via `dps_base_after_save_appointment`
    - Payment (link de pagamento) â€” via `dps_base_after_save_appointment`
    - Groomers (atribuiÃ§Ã£o de tosador) â€” via `dps_base_after_save_appointment` + `dps_base_appointment_assignment_fields`
    - Calendar (sincronizaÃ§Ã£o Google Calendar) â€” via `dps_base_after_save_appointment`
    - Communications (notificaÃ§Ãµes email/WhatsApp) â€” via `dps_base_after_save_appointment`
    - Push (notificaÃ§Ãµes push) â€” via `dps_base_after_save_appointment`
    - Services (snapshot de valores) â€” via `dps_base_after_save_appointment`
    - **Testar CADA integraÃ§Ã£o** individualmente e em conjunto

âœ… **CritÃ©rios de Aceite:**
- [x] Wizard funciona com 5 steps + extras condicionais
- [x] 3 tipos de agendamento suportados (simple, subscription, past)
- [x] State management robusto (sessÃ£o + URL)
- [x] AJAX endpoints funcionais e seguros (nonce + capability)
- [x] Busca de cliente por telefone OK
- [x] SeleÃ§Ã£o mÃºltipla de pets com paginaÃ§Ã£o OK
- [x] TaxiDog checkbox + preÃ§o funcional
- [x] Tosa extras para subscription funcional (preÃ§o + ocorrÃªncia)
- [x] CalendÃ¡rio de disponibilidade com validaÃ§Ã£o de conflitos OK
- [x] ConfirmaÃ§Ã£o via transient (5min TTL) OK
- [x] Login check + redirecionamento funcional
- [ ] Cache control desabilitado na pÃ¡gina de booking
- [ ] EdiÃ§Ã£o/duplicaÃ§Ã£o de agendamentos existentes OK
- [x] Cria appointment corretamente com TODAS as metas
- [x] Dispara **TODOS** os hooks crÃ­ticos via bridge (8 add-ons)
- [ ] Email de confirmaÃ§Ã£o enviado
- [x] CSS 100% DPS Signature (wizard expressivo)
- [x] AnimaÃ§Ãµes de transiÃ§Ã£o suaves (respeita `prefers-reduced-motion`)
- [x] ValidaÃ§Ã£o robusta (client + server)
- [x] Acessibilidade WCAG 2.1 AA
- [ ] Performance < 3s render, < 1s transiÃ§Ã£o, < 200ms step change
- [x] Funciona em mobile (touch-friendly)
- [x] Rollback instantÃ¢neo (flag `booking_v2`)
- [x] Shortcode legado `[dps_booking_form]` continua funcionando intacto

### Fase 7.4 â€” CoexistÃªncia e MigraÃ§Ã£o (Sprint 11-12)

**Objetivo:** Permitir escolha entre v1 (dual-run) e v2 (nativo)

âœ… **Tarefas:**
1. **DocumentaÃ§Ã£o de MigraÃ§Ã£o:**
   - Guia passo a passo para migrar de v1 para v2
   - ComparaÃ§Ã£o de features v1 vs v2
   - Checklist de compatibilidade
   - Plano de rollback

2. **Testes de MigraÃ§Ã£o:**
   - Script de validaÃ§Ã£o de compatibilidade
   - Testes side-by-side (v1 e v2 ao mesmo tempo)
   - ValidaÃ§Ã£o de hooks em ambas versÃµes

3. **Telemetria V2:**
   - Adicionar tracking de uso v2
   - Comparar mÃ©tricas v1 vs v2
   - Dashboard de adoÃ§Ã£o

4. **Ferramentas Admin:**
   - Toggle fÃ¡cil entre v1/v2 na aba Settings
   - Indicador visual de qual versÃ£o estÃ¡ ativa
   - Link para guia de migraÃ§Ã£o

âœ… **CritÃ©rios de Aceite:**
- [x] v1 e v2 podem coexistir
- [x] DocumentaÃ§Ã£o de migraÃ§Ã£o completa
- [ ] Script de validaÃ§Ã£o funcional
- [x] Telemetria v2 implementada
- [x] Admin UI para toggle v1/v2
- [x] Guia de troubleshooting

### Fase 7.5 â€” DepreciaÃ§Ã£o do Dual-Run (Sprint 13-18+)

**Objetivo:** Descontinuar v1 apÃ³s adoÃ§Ã£o massiva de v2

âš ï¸ **ATENÃ‡ÃƒO:** Esta fase sÃ³ deve iniciar apÃ³s:
- âœ… 90+ dias de v2 em produÃ§Ã£o estÃ¡vel
- âœ… 80%+ dos sites migraram para v2
- âœ… Zero bugs crÃ­ticos em v2
- âœ… Telemetria confirma uso < 5% de v1

âœ… **Tarefas:**
1. **ComunicaÃ§Ã£o Formal:**
   - AnÃºncio de depreciaÃ§Ã£o (180 dias antecedÃªncia)
   - Email para todos os clientes
   - Banner no admin WordPress
   - DocumentaÃ§Ã£o atualizada

2. **PerÃ­odo de ObservaÃ§Ã£o:**
   - 90 dias dual-run obrigatÃ³rio
   - 60 dias aviso de remoÃ§Ã£o
   - 30 dias observaÃ§Ã£o final

3. **RemoÃ§Ã£o do Legado (apenas apÃ³s aprovaÃ§Ã£o):**
   - Remover `DPS_Registration_Addon`
   - Remover `DPS_Booking_Addon`
   - Remover cÃ³digo dual-run v1
   - Manter apenas v2

---

## Novos Shortcodes Nativos

### Registration V2

```php
/**
 * Shortcode: [dps_registration_v2]
 *
 * Exibe formulÃ¡rio nativo de cadastro DPS Signature.
 * Completamente independente do add-on legado.
 *
 * @param array $atts Atributos do shortcode
 * @return string HTML renderizado
 */
[dps_registration_v2]
```

**Atributos aceitos:**
- `redirect_url` â€” URL de redirecionamento pÃ³s-sucesso (padrÃ£o: pÃ¡gina de agendamento)
- `show_pets` â€” exibir seÃ§Ã£o de pets (padrÃ£o: `true`)
- `show_marketing` â€” exibir opt-in de marketing (padrÃ£o: `true`)
- `theme` â€” tema visual: `light|dark` (padrÃ£o: `light`)
- `compact` â€” modo compacto (padrÃ£o: `false`)

**Exemplos:**
```
[dps_registration_v2]
[dps_registration_v2 redirect_url="/agendar"]
[dps_registration_v2 show_pets="true" show_marketing="false"]
[dps_registration_v2 theme="dark" compact="true"]
```

### Booking V2

```php
/**
 * Shortcode: [dps_booking_v2]
 *
 * Exibe wizard nativo de agendamento DPS Signature.
 * Multi-step com state management robusto.
 * Completamente independente do add-on legado.
 *
 * @param array $atts Atributos do shortcode
 * @return string HTML renderizado
 */
[dps_booking_v2]
```

**Atributos aceitos:**
- `client_id` â€” prÃ©-selecionar cliente (opcional)
- `service_id` â€” prÃ©-selecionar serviÃ§o (opcional)
- `start_step` â€” step inicial: `1-5` (padrÃ£o: `1`)
- `show_progress` â€” exibir barra de progresso (padrÃ£o: `true`)
- `theme` â€” tema visual: `light|dark` (padrÃ£o: `light`)
- `compact` â€” modo compacto (padrÃ£o: `false`)
- `appointment_type` â€” tipo de agendamento: `simple|subscription|past` (padrÃ£o: `simple`)
- `edit_id` â€” ID do agendamento para ediÃ§Ã£o (opcional)

**Exemplos:**
```
[dps_booking_v2]
[dps_booking_v2 client_id="123"]
[dps_booking_v2 service_id="456" start_step="3"]
[dps_booking_v2 show_progress="true" theme="light"]
[dps_booking_v2 appointment_type="subscription"]
[dps_booking_v2 edit_id="789"]
```

### ComparaÃ§Ã£o v1 vs v2

| Feature | v1 (Dual-Run) | v2 (Nativo) |
|---------|---------------|-------------|
| **Shortcode** | `[dps_registration_form]` | `[dps_registration_v2]` |
| **DependÃªncia Legado** | âœ… Sim (obrigatÃ³rio) | âŒ NÃ£o (independente) |
| **HTML** | Legado (estrutura antiga) | Nativo DPS Signature (semÃ¢ntico) |
| **CSS** | Legado + wrapper | 100% DPS Signature |
| **JavaScript** | Legado (jQuery) | Vanilla JS (moderno) |
| **Acessibilidade** | Limitada | WCAG 2.1 AA |
| **Performance** | ~3-4s render | ~1-2s render |
| **CustomizaÃ§Ã£o** | Limitada | Totalmente flexÃ­vel |
| **Hooks** | Legados | Novos + bridge legados |
| **Templates** | Hardcoded | ReutilizÃ¡veis |
| **Rollback** | Flag `registration` | Flag `registration_v2` |

---

## CoexistÃªncia de Shortcodes v1 e v2

### PrincÃ­pio Fundamental

Os shortcodes v1 (`[dps_registration_form]`, `[dps_booking_form]`) e v2 (`[dps_registration_v2]`, `[dps_booking_v2]`) **coexistem independentemente**. Ambos podem estar ativos no mesmo site WordPress ao mesmo tempo.

### CenÃ¡rios de CoexistÃªncia

```
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ CENÃRIO 1: TransiÃ§Ã£o Gradual (RECOMENDADO)               â”‚
â”‚                                                          â”‚
â”‚  PÃ¡gina A: [dps_registration_form]  â† legado (v1)       â”‚
â”‚  PÃ¡gina B: [dps_registration_v2]    â† nova (v2)         â”‚
â”‚                                                          â”‚
â”‚  Ambas ativas. Admin testa v2 enquanto v1 serve pÃºblico. â”‚
â”‚  Quando satisfeito, troca link pÃºblico para PÃ¡gina B.    â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜

â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ CENÃRIO 2: SubstituiÃ§Ã£o Direta                           â”‚
â”‚                                                          â”‚
â”‚  PÃ¡gina existente: trocar shortcode de                   â”‚
â”‚  [dps_registration_form] para [dps_registration_v2]      â”‚
â”‚                                                          â”‚
â”‚  Rollback: trocar de volta e desabilitar flag v2.        â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜

â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ CENÃRIO 3: Side-by-Side para Testes                      â”‚
â”‚                                                          â”‚
â”‚  Mesma pÃ¡gina pode ter AMBOS os shortcodes (debug).      â”‚
â”‚  [dps_registration_form] mostra v1, [dps_registration_v2]â”‚
â”‚  mostra v2 lado a lado para comparaÃ§Ã£o visual.           â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
```

### Isolamento Garantido

- **v1** (`[dps_registration_form]`, `[dps_booking_form]`) continua usando dual-run (Fases 2-3):
  - Registrado por `DPS_Frontend_Registration_Module` / `DPS_Frontend_Booking_Module`
  - Delega para add-ons legados (`DPS_Registration_Addon`, `DPS_Booking_Addon`)
  - Feature flags: `registration`, `booking`

- **v2** (`[dps_registration_v2]`, `[dps_booking_v2]`) Ã© completamente independente:
  - Registrado por `DPS_Frontend_Registration_V2_Module` / `DPS_Frontend_Booking_V2_Module`
  - Zero referÃªncia aos add-ons legados
  - Feature flags: `registration_v2`, `booking_v2`

- **Sem conflito:** Os shortcodes sÃ£o diferentes, os mÃ³dulos sÃ£o diferentes, os assets sÃ£o diferentes (namespaced CSS classes)

### Matrix de Feature Flags

| Flag | Shortcode Controlado | DependÃªncia Legada | Pode Coexistir |
|------|---------------------|-------------------|---------------|
| `registration` | `[dps_registration_form]` | âœ… Sim (dual-run) | âœ… Com `registration_v2` |
| `booking` | `[dps_booking_form]` | âœ… Sim (dual-run) | âœ… Com `booking_v2` |
| `registration_v2` | `[dps_registration_v2]` | âŒ NÃ£o (nativo) | âœ… Com `registration` |
| `booking_v2` | `[dps_booking_v2]` | âŒ NÃ£o (nativo) | âœ… Com `booking` |
| `settings` | Aba admin "Frontend" | âŒ NÃ£o | âœ… Sempre |

### Guia de MigraÃ§Ã£o para Administradores

1. **Ativar v2:** `wp option patch update dps_frontend_feature_flags registration_v2 1`
2. **Criar nova pÃ¡gina** com `[dps_registration_v2]` (ou editar pÃ¡gina existente)
3. **Testar** completamente (cadastro, validaÃ§Ã£o, email, integraÃ§Ã£o Loyalty)
4. **Quando satisfeito:** apontar links pÃºblicos para a nova pÃ¡gina
5. **Opcional:** desativar v1 com `wp option patch update dps_frontend_feature_flags registration 0`
6. **Rollback:** reverter flags e restaurar shortcode original

---

## Estrutura de Templates

### Sistema de Template Engine

Criar engine simples inspirado em WordPress template hierarchy:

```php
class DPS_Template_Engine {

    private string $template_path;

    public function __construct( string $base_path ) {
        $this->template_path = trailingslashit( $base_path ) . 'templates/';
    }

    /**
     * Renderiza template com dados
     */
    public function render( string $template, array $data = [] ): string {
        $file = $this->locate_template( $template );

        if ( ! $file ) {
            return '';
        }

        // Extrai dados para scope local
        extract( $data, EXTR_SKIP );

        // Captura output
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Localiza template (permite override via tema)
     */
    private function locate_template( string $template ): string|false {
        // 1. Busca no tema (override)
        $theme_template = get_stylesheet_directory() . '/dps-templates/' . $template;
        if ( file_exists( $theme_template ) ) {
            return $theme_template;
        }

        // 2. Busca no plugin
        $plugin_template = $this->template_path . $template;
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return false;
    }
}
```

### Estrutura de Templates

```
templates/
â”œâ”€â”€ registration/
â”‚   â”œâ”€â”€ form-main.php                 â† Wrapper principal
â”‚   â”œâ”€â”€ form-client-data.php          â† SeÃ§Ã£o cliente (nome, email, telefone, CPF, endereÃ§o)
â”‚   â”œâ”€â”€ form-pet-data.php             â† SeÃ§Ã£o pet (repeater: nome, espÃ©cie, raÃ§a datalist, porte, obs)
â”‚   â”œâ”€â”€ form-duplicate-warning.php    â† NOVO: aviso telefone duplicado (admin override)
â”‚   â”œâ”€â”€ form-success.php              â† Sucesso (com CTA agendamento)
â”‚   â””â”€â”€ form-error.php                â† Erro
â”œâ”€â”€ booking/
â”‚   â”œâ”€â”€ form-main.php                 â† Wizard container
â”‚   â”œâ”€â”€ step-client-selection.php     â† Step 1: Cliente (busca AJAX por telefone)
â”‚   â”œâ”€â”€ step-pet-selection.php        â† Step 2: Pet (multi-select com paginaÃ§Ã£o)
â”‚   â”œâ”€â”€ step-service-selection.php    â† Step 3: ServiÃ§o (com preÃ§os)
â”‚   â”œâ”€â”€ step-datetime-selection.php   â† Step 4: Data/Hora (calendÃ¡rio + conflitos)
â”‚   â”œâ”€â”€ step-confirmation.php         â† Step 5: ConfirmaÃ§Ã£o (resumo completo)
â”‚   â”œâ”€â”€ step-extras.php               â† NOVO: TaxiDog + Tosa (condicional por tipo)
â”‚   â”œâ”€â”€ form-success.php              â† Sucesso (confirmaÃ§Ã£o pÃ³s-criaÃ§Ã£o)
â”‚   â”œâ”€â”€ form-login-required.php       â† NOVO: redirecionamento para login
â”‚   â””â”€â”€ form-type-selector.php        â† NOVO: seletor tipo (simple/subscription/past)
â”œâ”€â”€ emails/
â”‚   â”œâ”€â”€ registration-confirmation.php â† NOVO: email confirmaÃ§Ã£o DPS Signature
â”‚   â””â”€â”€ booking-confirmation.php      â† NOVO: email confirmaÃ§Ã£o agendamento DPS Signature
â””â”€â”€ components/
    â”œâ”€â”€ field-text.php                â† Input text DPS Signature
    â”œâ”€â”€ field-email.php               â† Input email DPS Signature
    â”œâ”€â”€ field-phone.php               â† Input phone DPS Signature
    â”œâ”€â”€ field-cpf.php                 â† NOVO: Input CPF DPS Signature (mÃ¡scara + validaÃ§Ã£o)
    â”œâ”€â”€ field-address.php             â† NOVO: Input endereÃ§o DPS Signature (Google Maps autocomplete)
    â”œâ”€â”€ field-select.php              â† Select DPS Signature
    â”œâ”€â”€ field-datalist.php            â† NOVO: Input com datalist DPS Signature (raÃ§as)
    â”œâ”€â”€ field-textarea.php            â† Textarea DPS Signature
    â”œâ”€â”€ field-checkbox.php            â† Checkbox DPS Signature
    â”œâ”€â”€ field-currency.php            â† NOVO: Input moeda DPS Signature (preÃ§o TaxiDog/Tosa)
    â”œâ”€â”€ button-primary.php            â† BotÃ£o primÃ¡rio DPS Signature
    â”œâ”€â”€ button-secondary.php          â† BotÃ£o secundÃ¡rio DPS Signature
    â”œâ”€â”€ button-text.php               â† BotÃ£o texto DPS Signature
    â”œâ”€â”€ card.php                      â† Card DPS Signature
    â”œâ”€â”€ alert.php                     â† Alert DPS Signature
    â”œâ”€â”€ loader.php                    â† Loader DPS Signature
    â”œâ”€â”€ progress-bar.php              â† Barra de progresso
    â”œâ”€â”€ wizard-steps.php              â† Indicador de steps
    â””â”€â”€ recaptcha-badge.php           â† NOVO: reCAPTCHA v3 badge DPS Signature
```

### Exemplo de Template â€” Registration Form Main

```php
<?php
/**
 * Template: Registration Form Main
 *
 * @package DPS_Frontend_Addon
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Dados disponÃ­veis:
// $form_action, $nonce_field, $errors, $data
?>

<div class="dps-registration-v2" data-theme="<?php echo esc_attr( $theme ?? 'light' ); ?>">

    <!-- Header -->
    <div class="dps-registration-header">
        <h1 class="dps-typescale-headline-large">
            <?php esc_html_e( 'Cadastre-se', 'dps-frontend-addon' ); ?>
        </h1>
        <p class="dps-typescale-body-large dps-color-on-surface-variant">
            <?php esc_html_e( 'Preencha os dados abaixo para criar sua conta', 'dps-frontend-addon' ); ?>
        </p>
    </div>

    <!-- Alerts -->
    <?php if ( ! empty( $errors ) ) : ?>
        <?php echo $this->render( 'components/alert.php', [
            'type'    => 'error',
            'message' => implode( '<br>', $errors ),
        ] ); ?>
    <?php endif; ?>

    <!-- Form -->
    <form
        method="post"
        action="<?php echo esc_url( $form_action ); ?>"
        class="dps-registration-form"
        novalidate
    >

        <?php echo $nonce_field; ?>

        <!-- SeÃ§Ã£o Cliente -->
        <?php echo $this->render( 'registration/form-client-data.php', $data ); ?>

        <!-- SeÃ§Ã£o Pet (condicional) -->
        <?php if ( $show_pets ) : ?>
            <?php echo $this->render( 'registration/form-pet-data.php', $data ); ?>
        <?php endif; ?>

        <!-- Marketing Opt-in -->
        <?php if ( $show_marketing ) : ?>
            <div class="dps-field-group">
                <?php echo $this->render( 'components/field-checkbox.php', [
                    'name'    => 'marketing_optin',
                    'label'   => __( 'Desejo receber novidades e promoÃ§Ãµes', 'dps-frontend-addon' ),
                    'checked' => $data['marketing_optin'] ?? false,
                ] ); ?>
            </div>
        <?php endif; ?>

        <!-- Submit -->
        <div class="dps-form-actions">
            <?php echo $this->render( 'components/button-primary.php', [
                'type'    => 'submit',
                'text'    => __( 'Cadastrar', 'dps-frontend-addon' ),
                'loading' => true, // Mostra loader ao submeter
            ] ); ?>
        </div>

    </form>

</div>
```

### Exemplo de Template â€” Component Field Text

```php
<?php
/**
 * Component: Text Field (DPS Signature)
 *
 * @package DPS_Frontend_Addon
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Dados:
// $name, $label, $value, $placeholder, $required, $autocomplete, $type, $error
?>

<div class="dps-field dps-field--text <?php echo $error ? 'dps-field--error' : ''; ?>">

    <label for="dps-<?php echo esc_attr( $name ); ?>" class="dps-field-label">
        <?php echo esc_html( $label ); ?>
        <?php if ( $required ) : ?>
            <span class="dps-field-required" aria-label="<?php esc_attr_e( 'ObrigatÃ³rio', 'dps-frontend-addon' ); ?>">*</span>
        <?php endif; ?>
    </label>

    <input
        type="<?php echo esc_attr( $type ?? 'text' ); ?>"
        id="dps-<?php echo esc_attr( $name ); ?>"
        name="<?php echo esc_attr( $name ); ?>"
        value="<?php echo esc_attr( $value ?? '' ); ?>"
        placeholder="<?php echo esc_attr( $placeholder ?? '' ); ?>"
        class="dps-field-input"
        <?php echo $required ? 'required' : ''; ?>
        <?php echo $autocomplete ? 'autocomplete="' . esc_attr( $autocomplete ) . '"' : ''; ?>
        aria-describedby="<?php echo $error ? 'dps-' . esc_attr( $name ) . '-error' : ''; ?>"
    />

    <?php if ( $error ) : ?>
        <span
            id="dps-<?php echo esc_attr( $name ); ?>-error"
            class="dps-field-error"
            role="alert"
        >
            <?php echo esc_html( $error ); ?>
        </span>
    <?php endif; ?>

</div>
```

---

## ReutilizaÃ§Ã£o de Helpers Globais do Base

### PrincÃ­pio

O V2 NÃƒO deve reimplementar lÃ³gica que jÃ¡ existe nos helpers globais do `desi-pet-shower-base`. A regra Ã©: **reutilizar SEMPRE que disponÃ­vel**, reimplementar APENAS o que Ã© especÃ­fico do frontend.

### Helpers do Base a Reutilizar

| Helper | MÃ©todos Relevantes | Uso no V2 |
|--------|-------------------|-----------|
| `DPS_Phone_Helper` | `normalize()`, `format()`, `validate()` | ValidaÃ§Ã£o e formataÃ§Ã£o de telefone no cadastro e busca |
| `DPS_Money_Helper` | `format()`, `parse()`, `to_cents()` | ExibiÃ§Ã£o de preÃ§os (serviÃ§os, TaxiDog, Tosa) |
| `DPS_URL_Builder` | `build()`, `admin_url()` | ConstruÃ§Ã£o de URLs de redirecionamento |
| `DPS_Message_Helper` | `success()`, `error()`, `warning()` | Feedback consistente para o usuÃ¡rio |
| `DPS_Cache_Control` | `force_no_cache()` | Desabilitar cache em pÃ¡ginas de booking |

### LÃ³gica a Reimplementar Nativamente

| Componente | Motivo | ReferÃªncia Legada |
|-----------|--------|-------------------|
| ValidaÃ§Ã£o CPF (mod-11) | LÃ³gica simples, sem helper global. Extrair para `DPS_Cpf_Validator` | `DPS_Registration_Addon::validate_cpf()` |
| Breed dataset | Dados estÃ¡ticos, extrair para provider reutilizÃ¡vel | `DPS_Registration_Addon::get_breed_dataset()` |
| reCAPTCHA v3 verification | IntegraÃ§Ã£o com API Google, extrair para service | `DPS_Registration_Addon::verify_recaptcha_token()` |
| Email confirmation tokens | LÃ³gica de token UUID + TTL, extrair para service | `DPS_Registration_Addon::send_confirmation_email()` |
| Booking state machine | LÃ³gica nova especÃ­fica do wizard v2 | N/A (conceito novo) |

### Nota sobre DI

Todos os helpers devem ser injetados via construtor (DI), nunca acessados como singleton ou estÃ¡tico direto:

```php
// âœ… Correto
public function __construct(
    private readonly DPS_Phone_Helper $phoneHelper,
    private readonly DPS_Money_Helper $moneyHelper,
) {}

// âŒ Incorreto
DPS_Phone_Helper::normalize( $phone ); // Acesso estÃ¡tico
```

---

## EstratÃ©gia de Testes

### Abordagem

A Fase 7 introduz cÃ³digo novo significativo. Para garantir qualidade e evitar regressÃµes, a estratÃ©gia de testes Ã©:

### 1. ValidaÃ§Ã£o PHP (ObrigatÃ³ria)

Todos os arquivos PHP alterados/criados devem passar em `php -l`:

```bash
# Validar todos os novos arquivos
find plugins/desi-pet-shower-frontend/includes/handlers/ \
     plugins/desi-pet-shower-frontend/includes/services/ \
     plugins/desi-pet-shower-frontend/includes/bridges/ \
     plugins/desi-pet-shower-frontend/includes/ajax/ \
     plugins/desi-pet-shower-frontend/templates/ \
     -name '*.php' -exec php -l {} \;
```

### 2. Testes Funcionais (por feature)

Cada feature do inventÃ¡rio legado (R1-R13, B1-B14) deve ter um teste funcional documentado:

| Teste | Passos | Resultado Esperado |
|-------|--------|-------------------|
| Registration V2 â€” Cadastro bÃ¡sico | Preencher todos os campos, submeter | Cliente + pet criados, success page exibida |
| Registration V2 â€” CPF invÃ¡lido | Preencher CPF invÃ¡lido, submeter | Erro de validaÃ§Ã£o exibido, form preserva dados |
| Registration V2 â€” Telefone duplicado | Usar telefone existente | Aviso de duplicata exibido, bloqueio para nÃ£o-admin |
| Registration V2 â€” reCAPTCHA | Submeter com reCAPTCHA habilitado | Score validado server-side, registro prossegue |
| Registration V2 â€” Email confirmation | Cadastrar novo cliente | Email de confirmaÃ§Ã£o enviado com token 48h |
| Registration V2 â€” Loyalty bridge | Cadastrar com referral code | Hook `dps_registration_after_client_created` disparado, Loyalty processa |
| Booking V2 â€” Wizard completo | Navegar 5 steps, confirmar | Appointment criado com todas as metas |
| Booking V2 â€” TaxiDog | Marcar TaxiDog no step extras | Meta `appointment_taxidog` = 1 no appointment |
| Booking V2 â€” Tosa subscription | Selecionar subscription + tosa | Metas de tosa salvas corretamente |
| Booking V2 â€” Multi-pet | Selecionar 3+ pets com paginaÃ§Ã£o | Todos os pets incluÃ­dos no appointment |
| Booking V2 â€” Hook bridge | Criar appointment via v2 | TODOS os 8 add-ons recebem `dps_base_after_save_appointment` |
| Booking V2 â€” Login required | Acessar booking sem login | Redireciona para login com return URL |

### 3. Testes de IntegraÃ§Ã£o (cross-addon)

```
â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ CenÃ¡rio: Registration V2 + Loyalty                  â”‚
â”‚  1. Habilitar flag registration_v2                  â”‚
â”‚  2. Acessar [dps_registration_v2]                   â”‚
â”‚  3. Preencher form com referral code                â”‚
â”‚  4. Submeter                                        â”‚
â”‚  5. Verificar: Loyalty registrou referral âœ“         â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜

â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
â”‚ CenÃ¡rio: Booking V2 + Payment + Stock + Groomers    â”‚
â”‚  1. Habilitar flag booking_v2                       â”‚
â”‚  2. Completar wizard [dps_booking_v2]               â”‚
â”‚  3. Verificar:                                      â”‚
â”‚     - Payment: link gerado âœ“                        â”‚
â”‚     - Stock: produtos reservados âœ“                  â”‚
â”‚     - Groomers: tosador atribuÃ­do âœ“                 â”‚
â”‚     - Calendar: evento sincronizado âœ“               â”‚
â”‚     - Communications: notificaÃ§Ã£o enviada âœ“         â”‚
â”‚     - Push: push notification enviada âœ“             â”‚
â”‚     - Services: snapshot salvo âœ“                    â”‚
â””â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”˜
```

### 4. Testes de CoexistÃªncia

- [ ] v1 e v2 na mesma pÃ¡gina nÃ£o conflitam (CSS, JS, IDs)
- [ ] Alternar flag v2 nÃ£o afeta v1
- [ ] Desabilitar flag v2 remove shortcode v2 sem afetar v1
- [ ] Hooks bridge nÃ£o duplicam aÃ§Ãµes quando v1 e v2 estÃ£o ambos ativos

### 5. Testes de Acessibilidade

- ValidaÃ§Ã£o via aXe DevTools ou WAVE
- NavegaÃ§Ã£o por teclado completa (Tab, Enter, Escape)
- Leitura por screen reader (NVDA/VoiceOver)
- Contraste mÃ­nimo WCAG 2.1 AA (4.5:1 texto, 3:1 UI)
- `prefers-reduced-motion` respeitado

### 6. Benchmarks de Performance

| MÃ©trica | Alvo Registration V2 | Alvo Booking V2 |
|---------|---------------------|-----------------|
| First render | < 2s | < 3s |
| Form submit | < 500ms | < 1s |
| Step transition | N/A | < 200ms |
| AJAX response | < 300ms | < 500ms |
| Total page weight (CSS+JS) | < 50KB | < 80KB |

MediÃ§Ã£o via `performance.mark()` / `performance.measure()` no JS e `microtime()` no PHP.

---

## Cronograma de ImplementaÃ§Ã£o

### Timeline Estimado

| Fase | DuraÃ§Ã£o | Sprint | DescriÃ§Ã£o |
|------|---------|--------|-----------|
| **7.1 PreparaÃ§Ã£o** | 2-3 semanas | 1-2 | Estrutura base, componentes, classes abstratas |
| **7.2 Registration V2** | 3-4 semanas | 3-5 | ImplementaÃ§Ã£o nativa completa cadastro |
| **7.3 Booking V2** | 5-6 semanas | 6-10 | ImplementaÃ§Ã£o nativa completa agendamento |
| **7.4 CoexistÃªncia** | 2 semanas | 11-12 | Docs migraÃ§Ã£o, testes, ferramentas admin |
| **7.5 DepreciaÃ§Ã£o** | 6+ meses | 13-18+ | ObservaÃ§Ã£o, comunicaÃ§Ã£o, remoÃ§Ã£o legado |
| **TOTAL** | **4-5 meses** (cÃ³digo) + **6+ meses** (observaÃ§Ã£o) | | |

### Marcos Principais

1. **M1 â€” FundaÃ§Ã£o Completa** (fim Sprint 2)
   - Estrutura criada
   - Componentes bÃ¡sicos prontos
   - Feature flags v2 implementadas

2. **M2 â€” Registration V2 Funcional** (fim Sprint 5)
   - FormulÃ¡rio nativo completo
   - Processamento independente
   - Hooks de integraÃ§Ã£o OK
   - Rollback testado

3. **DPS Signature â€” Booking V2 Funcional** (fim Sprint 10)
   - Wizard completo 5 steps
   - AJAX endpoints OK
   - IntegraÃ§Ãµes crÃ­ticas preservadas
   - Rollback testado

4. **M4 â€” CoexistÃªncia EstÃ¡vel** (fim Sprint 12)
   - v1 e v2 coexistem
   - MigraÃ§Ã£o documentada
   - Telemetria v2 ativa

5. **M5 â€” AdoÃ§Ã£o Massiva** (6 meses apÃ³s M4)
   - 80%+ migraram para v2
   - v1 usado < 5%
   - AprovaÃ§Ã£o para remoÃ§Ã£o legado

---

## CritÃ©rios de Aceite

### CritÃ©rios Globais (todas as fases)

âœ… **Funcionalidade:**
- [ ] Zero quebra de funcionalidade existente (shortcodes v1 intactos)
- [ ] Rollback instantÃ¢neo via feature flags
- [ ] Compatibilidade retroativa de hooks (via Hook Bridge)
- [ ] Telemetria de uso implementada (v2 tracking separado)
- [ ] CoexistÃªncia v1/v2 funcional no mesmo site

âœ… **CÃ³digo:**
- [ ] PHP 8.4 moderno (typed properties, readonly, constructor promotion)
- [ ] Zero uso de singletons
- [ ] Dependency injection (todos os helpers via construtor)
- [ ] Sem jQuery (vanilla JS apenas)
- [ ] ReutilizaÃ§Ã£o de helpers globais do base (DPS_Phone_Helper, DPS_Money_Helper, etc.)
- [ ] ComentÃ¡rios PHPDoc completos
- [ ] Conformidade com AGENTS.md e PLAYBOOK.md
- [ ] Text domain consistente: `dps-frontend-addon`

âœ… **Visual (DPS Signature):**
- [ ] 100% design tokens CSS
- [ ] Zero hex/rgb hardcoded
- [ ] HTML semÃ¢ntico
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Motion expressivo opcional (`prefers-reduced-motion`)
- [ ] Tema escuro suportado
- [ ] Conformidade com `docs/visual/VISUAL_STYLE_GUIDE.md`

âœ… **Performance:**
- [ ] Render < 2s (Registration)
- [ ] Render < 3s (Booking wizard)
- [ ] Submit < 500ms
- [ ] TransiÃ§Ã£o steps < 200ms
- [ ] AJAX responses < 500ms
- [ ] Lazy load de assets
- [ ] MinificaÃ§Ã£o CSS/JS
- [ ] Total page weight < 80KB (CSS+JS)

âœ… **SeguranÃ§a:**
- [ ] Nonces em todos os forms e AJAX endpoints
- [ ] Capability check (custom DPS capabilities + manage_options)
- [ ] SanitizaÃ§Ã£o server-side (todos os inputs)
- [ ] Escape de output (esc_html, esc_attr, esc_url)
- [ ] ValidaÃ§Ã£o client-side + server-side
- [ ] CSRF protection
- [ ] XSS protection
- [ ] reCAPTCHA v3 (quando habilitado)
- [ ] Duplicate detection (phone-based)

âœ… **DocumentaÃ§Ã£o:**
- [ ] Guia de uso atualizado (`docs/FRONTEND_ADDON_GUIA_USUARIO.md`)
- [ ] Exemplos de cÃ³digo para cada shortcode v2
- [ ] Migration guide v1 â†’ v2 (passo a passo)
- [ ] Troubleshooting atualizado
- [ ] CHANGELOG.md atualizado
- [ ] ANALYSIS.md atualizado com hooks v2

### CritÃ©rios EspecÃ­ficos â€” Registration V2

âœ… **Funcional (paridade com R1-R13):**
- [ ] Renderiza form nativo (zero legado)
- [ ] Valida campos obrigatÃ³rios (nome, email, telefone)
- [ ] Valida CPF com algoritmo Mod-11 (se preenchido)
- [ ] Normaliza telefone via DPS_Phone_Helper
- [ ] Detecta duplicata por telefone (bloqueia nÃ£o-admin)
- [ ] Cria cliente corretamente (wp_insert_post + metas)
- [ ] Cria 1+ pets corretamente (repeater funcional)
- [ ] Dataset de raÃ§as por espÃ©cie (datalist)
- [ ] reCAPTCHA v3 funcional (quando habilitado)
- [ ] ConfirmaÃ§Ã£o de email 48h (token + cron lembretes)
- [ ] Google Maps autocomplete (quando API key presente)
- [ ] Anti-spam filter aplicado
- [ ] Marketing opt-in checkbox
- [ ] Envia email de boas-vindas
- [ ] Redireciona pÃ³s-sucesso (configurÃ¡vel via atributo)
- [ ] Exibe erros de validaÃ§Ã£o (preserva dados do form)
- [ ] MantÃ©m dados em caso de erro (sticky form)

âœ… **IntegraÃ§Ã£o (via Hook Bridge):**
- [ ] Dispara hooks nativos (`dps_registration_v2_*`)
- [ ] Bridge: `dps_registration_after_client_created` (4 args â€” Loyalty)
- [ ] Bridge: `dps_registration_after_fields` (Loyalty UI)
- [ ] Bridge: `dps_registration_spam_check` (filter)
- [ ] Loyalty add-on funciona (referral code processado)
- [ ] Communications add-on funciona (email enviado)

### CritÃ©rios EspecÃ­ficos â€” Booking V2

âœ… **Funcional (paridade com B1-B14):**
- [ ] Wizard 5 steps + extras condicionais funcional
- [ ] 3 tipos de agendamento: simple, subscription, past
- [ ] State management robusto (sessÃ£o + URL query param)
- [ ] Login obrigatÃ³rio (redireciona se nÃ£o logado)
- [ ] Cache control desabilitado na pÃ¡gina
- [ ] Busca cliente por telefone (AJAX)
- [ ] SeleÃ§Ã£o mÃºltipla de pets com paginaÃ§Ã£o
- [ ] Lista serviÃ§os com preÃ§os
- [ ] CalendÃ¡rio de disponibilidade com validaÃ§Ã£o de conflitos
- [ ] TaxiDog: checkbox + preÃ§o
- [ ] Tosa (subscription only): checkbox + preÃ§o + ocorrÃªncia
- [ ] ConfirmaÃ§Ã£o via transient (5min TTL)
- [ ] EdiÃ§Ã£o/duplicaÃ§Ã£o de agendamentos
- [ ] Cria appointment com TODAS as metas
- [ ] Envia email confirmaÃ§Ã£o
- [ ] Skip REST/AJAX requests (retorna vazio)
- [ ] Capabilities check: manage_options OU dps_manage_*

âœ… **IntegraÃ§Ã£o (CRÃTICO â€” 8 add-ons via Hook Bridge):**
- [ ] Bridge: `dps_base_after_save_appointment` (8 consumidores)
- [ ] Bridge: `dps_base_appointment_fields` (Services)
- [ ] Bridge: `dps_base_appointment_assignment_fields` (Groomers)
- [ ] Stock (consumo de produtos confirmado)
- [ ] Payment (link de pagamento gerado)
- [ ] Groomers (atribuiÃ§Ã£o de tosador)
- [ ] Calendar (sincronizaÃ§Ã£o Google Calendar)
- [ ] Communications (notificaÃ§Ãµes email/WhatsApp)
- [ ] Push (notificaÃ§Ãµes push)
- [ ] Services (snapshot de valores)

---

## Riscos e MitigaÃ§Ã£o

### Riscos Identificados

#### 1. **Complexidade Alta**
**Risco:** ImplementaÃ§Ã£o nativa Ã© significativamente mais complexa que wrapper.

**MitigaÃ§Ã£o:**
- Dividir em fases pequenas e incrementais
- Criar protÃ³tipos antes de implementaÃ§Ã£o completa
- Code review rigoroso em cada PR
- Testes automatizados desde o inÃ­cio

#### 2. **Quebra de IntegraÃ§Ãµes**
**Risco:** Add-ons que dependem de hooks legados podem quebrar.

**MitigaÃ§Ã£o:**
- Manter hooks legados via bridge durante Fase 7.4
- Testar todos os 18 add-ons em cada fase
- Matriz de compatibilidade atualizada continuamente
- Rollback instantÃ¢neo sempre disponÃ­vel

#### 3. **AdoÃ§Ã£o Lenta**
**Risco:** UsuÃ¡rios podem resistir a migrar para v2.

**MitigaÃ§Ã£o:**
- DocumentaÃ§Ã£o de migraÃ§Ã£o clara e passo a passo
- BenefÃ­cios de v2 claramente comunicados
- Ferramentas admin para facilitar toggle
- Suporte dedicado durante migraÃ§Ã£o
- Incentivos para early adopters

#### 4. **Performance Pior que Esperado**
**Risco:** ImplementaÃ§Ã£o nativa pode ser mais lenta que legado otimizado.

**MitigaÃ§Ã£o:**
- Benchmarks desde Fase 7.1
- OtimizaÃ§Ã£o contÃ­nua em cada fase
- Lazy loading agressivo
- Code splitting
- Caching inteligente
- Profiling de performance

#### 5. **Scope Creep**
**Risco:** TentaÃ§Ã£o de adicionar features nÃ£o planejadas.

**MitigaÃ§Ã£o:**
- Roadmap rÃ­gido e acordado
- Definition of Done clara
- PR reviews focados em scope
- Features extras = backlog separado
- Foco em paridade funcional primeiro

#### 6. **Tempo de Desenvolvimento**
**Risco:** 4-5 meses Ã© estimativa otimista.

**MitigaÃ§Ã£o:**
- Buffer de 20% no cronograma
- RevisÃµes semanais de progresso
- Ajustes de scope se necessÃ¡rio
- ComunicaÃ§Ã£o transparente de atrasos
- PriorizaÃ§Ã£o clara (Registration > Booking)

#### 7. **Paridade Funcional Incompleta**
**Risco:** V2 pode ir para produÃ§Ã£o sem implementar features legadas que alguns clientes usam (ex.: reCAPTCHA, TaxiDog, tosa, admin quick-registration, email confirmation).

**MitigaÃ§Ã£o:**
- InventÃ¡rio completo de features legadas documentado neste plano (R1-R13, B1-B14)
- Checklist de paridade funcional em cada Fase (7.2 e 7.3)
- Testes funcionais feature-a-feature antes de liberar flag v2
- Features P2 (desejÃ¡veis) podem ser adiadas, mas features P0 e P1 sÃ£o obrigatÃ³rias antes de liberar v2 para produÃ§Ã£o
- Documentar explicitamente qualquer feature legada NÃƒO implementada no v2 e o motivo

#### 8. **Conflito de CSS/JS entre v1 e v2**
**Risco:** Quando v1 e v2 coexistem na mesma pÃ¡gina (cenÃ¡rio side-by-side), CSS e JS podem conflitar.

**MitigaÃ§Ã£o:**
- Namespacing CSS rigoroso: v2 usa classes `.dps-v2-*`, v1 mantÃ©m `.dps-frontend`
- IDs Ãºnicos: v2 usa prefixo `dps-v2-` em todos os IDs de elementos
- JS scoped: v2 JS opera apenas dentro de containers `.dps-v2-*`
- Assets carregados condicionalmente (apenas quando shortcode v2 presente na pÃ¡gina)
- Teste de coexistÃªncia obrigatÃ³rio na Fase 7.4

---

## PrÃ³ximos Passos Imediatos

### AÃ§Ãµes Recomendadas (Next Sprint)

1. **AprovaÃ§Ã£o Formal**
   - [ ] Revisar este plano com stakeholders
   - [ ] Aprovar roadmap Fase 7
   - [ ] Definir equipe alocada
   - [ ] Confirmar timeline

2. **Setup Inicial**
   - [ ] Criar branch `feature/frontend-v2-native`
   - [ ] Setup ambiente de desenvolvimento
   - [ ] Configurar CI/CD para v2
   - [ ] Preparar ambiente de testes

3. **Kickoff Fase 7.1**
   - [ ] Criar estrutura de diretÃ³rios
   - [ ] Implementar classes base abstratas
   - [ ] Criar primeiros componentes DPS Signature
   - [ ] Documentar padrÃµes de cÃ³digo

4. **ComunicaÃ§Ã£o**
   - [ ] Anunciar Fase 7 para equipe
   - [ ] Atualizar CHANGELOG.md
   - [ ] Criar issue tracker no GitHub
   - [ ] Setup de daily standups

---

## ConclusÃ£o

A **Fase 7** representa a **evoluÃ§Ã£o definitiva** do Frontend Add-on:

**De:** Wrappers que reutilizam cÃ³digo legado
**Para:** ImplementaÃ§Ãµes nativas 100% modernas e alinhadas ao DPS Signature

**BenefÃ­cios esperados:**
- âœ¨ UX/UI completamente redesenhada do zero
- âœ¨ Performance superior
- âœ¨ CÃ³digo limpo e testÃ¡vel
- âœ¨ IndependÃªncia total dos add-ons legados
- âœ¨ Flexibilidade para evoluÃ§Ãµes futuras
- âœ¨ Acessibilidade nativa WCAG 2.1 AA
- âœ¨ Pride na qualidade do cÃ³digo

**Compromissos:**
- âœ… MigraÃ§Ã£o gradual e segura (4-5 meses cÃ³digo + 6 meses observaÃ§Ã£o)
- âœ… Rollback sempre disponÃ­vel
- âœ… Zero quebra de compatibilidade durante coexistÃªncia
- âœ… DocumentaÃ§Ã£o completa em todas as fases

Este plano estabelece as bases para que o Frontend Add-on atinja seu **potencial completo**, tornando o DPS um sistema verdadeiramente moderno em todos os aspectos: arquitetura, cÃ³digo, design e experiÃªncia do usuÃ¡rio.

---

**VersÃ£o:** 1.4.0
**Status:** âœ… Fase 7 Completa (todas as subfases de cÃ³digo implementadas)
**Fase 7.5 â€” DepreciaÃ§Ã£o:** Aviso admin implementado. RemoÃ§Ã£o do legado aguarda prÃ©-requisitos (90+ dias V2 produÃ§Ã£o, 80%+ migraÃ§Ã£o, zero bugs crÃ­ticos, telemetria <5% v1)
**RevisÃ£o:** v1.4.0 â€” Fase 7.5 parcial: aviso de depreciaÃ§Ã£o admin com dismissal 30 dias, documentaÃ§Ã£o visual completa (2026-02-12)

---

**Documentos Relacionados:**
- `FRONTEND_ADDON_PHASED_ROADMAP.md` â€” Fases 1-6 (concluÃ­das)
- `FRONTEND_DEPRECATION_POLICY.md` â€” PolÃ­tica de 180 dias
- `FRONTEND_REMOVAL_TARGETS.md` â€” Alvos de remoÃ§Ã£o
- `AGENT_ENGINEERING_PLAYBOOK.md` â€” PadrÃµes de cÃ³digo
- `VISUAL_STYLE_GUIDE.md` â€” Design tokens DPS Signature
- `FRONTEND_DESIGN_INSTRUCTIONS.md` â€” Metodologia DPS Signature

**AprovaÃ§Ã£o necessÃ¡ria de:**
- [ ] Product Owner
- [ ] Tech Lead
- [ ] Design Lead
- [ ] DevOps Lead
