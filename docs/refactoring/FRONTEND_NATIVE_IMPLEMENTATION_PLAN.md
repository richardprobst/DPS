# Plano de Implementação Nativa — Frontend Add-on (Fase 7)

> **Versão**: 1.1.0  
> **Data**: 2026-02-12  
> **Autor**: PRObst  
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## 📋 Índice

1. [Contexto e Motivação](#contexto-e-motivação)
2. [Situação Atual (Fases 1-6)](#situação-atual-fases-1-6)
3. [Inventário de Funcionalidades Legadas a Preservar](#inventário-de-funcionalidades-legadas-a-preservar)
4. [Objetivo da Fase 7](#objetivo-da-fase-7)
5. [Arquitetura Proposta](#arquitetura-proposta)
6. [Implementação da Hook Bridge](#implementação-da-hook-bridge)
7. [Estratégia de Migração](#estratégia-de-migração)
8. [Novos Shortcodes Nativos](#novos-shortcodes-nativos)
9. [Coexistência de Shortcodes v1 e v2](#coexistência-de-shortcodes-v1-e-v2)
10. [Estrutura de Templates](#estrutura-de-templates)
11. [Reutilização de Helpers Globais do Base](#reutilização-de-helpers-globais-do-base)
12. [Estratégia de Testes](#estratégia-de-testes)
13. [Cronograma de Implementação](#cronograma-de-implementação)
14. [Critérios de Aceite](#critérios-de-aceite)
15. [Riscos e Mitigação](#riscos-e-mitigação)

---

## Contexto e Motivação

### Problema Identificado

O Frontend Add-on criado nas Fases 1-6 (PR #581) implementa uma **estratégia dual-run** onde:

❌ **Limitações atuais:**
- Reutiliza código legado dos add-ons `desi-pet-shower-registration` e `desi-pet-shower-booking`
- Apenas envolve o output legado em wrapper `.dps-frontend`
- Adiciona CSS M3 por cima do HTML legado (estrutura antiga permanece)
- Mantém dependências fortes dos add-ons legados
- Não permite refatoração completa da UX/UI
- Compromete o potencial completo do Material 3 Expressive
- HTML gerado continua com padrões antigos (estrutura, acessibilidade limitada)

✅ **O que funciona bem:**
- Rollback instantâneo via feature flags
- Zero quebra de compatibilidade
- Transição gradual e segura
- Telemetria de uso implementada
- Documentação completa

### Motivação para Fase 7

**Queremos criar páginas 100% novas:**
- ✨ HTML semântico moderno (PHP 8.4)
- ✨ Estrutura nativa Material 3 Expressive
- ✨ UX redesenhada do zero
- ✨ Acessibilidade WCAG 2.1 AA nativa
- ✨ Performance otimizada (lazy load, code splitting)
- ✨ Independência dos add-ons legados
- ✨ Templates reutilizáveis e testáveis
- ✨ Código limpo seguindo padrões modernos

**Resultado esperado:**
> Páginas de cadastro e agendamento completamente novas, construídas from-scratch com Material 3 Expressive, sem nenhuma dependência ou reutilização de código legado.

---

## Situação Atual (Fases 1-6)

### Fase 1 — Fundação ✅
- Estrutura do add-on criada
- Feature flags implementadas
- Assets M3 carregados condicionalmente
- Logger e telemetria funcionais

### Fase 2 — Registration Dual-Run ✅
- Módulo `DPS_Frontend_Registration_Module`
- **Estratégia:** `remove_shortcode()` + wrapper legado
- **Implementação:**
  ```php
  public function renderShortcode(): string {
      $legacy = DPS_Registration_Addon::get_instance();
      $html = $legacy->render_registration_form();
      return '<div class="dps-frontend">' . $html . '</div>';
  }
  ```
- ⚠️ **Problema:** HTML é gerado pelo legado, apenas envolto em div

### Fase 3 — Booking Dual-Run ✅
- Módulo `DPS_Frontend_Booking_Module`
- **Estratégia:** idêntica ao Registration
- **Implementação:**
  ```php
  public function renderShortcode(): string {
      $legacy = DPS_Booking_Addon::get_instance();
      $html = $legacy->render_booking_form();
      return '<div class="dps-frontend">' . $html . '</div>';
  }
  ```
- ⚠️ **Problema:** mesma limitação — wrapper apenas

### Fase 4 — Settings ✅
- Aba admin para gerenciar feature flags
- Funciona bem (não precisa refatoração)

### Fase 5 — Consolidação e Docs ✅
- Guias operacionais completos
- Matriz de compatibilidade
- Runbooks de incidentes

### Fase 6 — Governança de Depreciação ✅
- Política de 180 dias definida
- Telemetria de uso implementada
- Lista de alvos de remoção

### Arquivos Atuais

```
plugins/desi-pet-shower-frontend/
├── desi-pet-shower-frontend-addon.php
├── includes/
│   ├── class-dps-frontend-addon.php
│   ├── class-dps-frontend-module-registry.php
│   ├── class-dps-frontend-compatibility.php
│   ├── class-dps-frontend-feature-flags.php
│   ├── modules/
│   │   ├── class-dps-frontend-registration-module.php  ← DUAL-RUN
│   │   ├── class-dps-frontend-booking-module.php       ← DUAL-RUN
│   │   └── class-dps-frontend-settings-module.php
│   └── support/
│       ├── class-dps-frontend-assets.php
│       ├── class-dps-frontend-logger.php
│       └── class-dps-frontend-request-guard.php
├── templates/                                            ← VAZIO!
│   └── .gitkeep
└── assets/
    ├── css/
    │   └── frontend-addon.css                           ← CSS adicional apenas
    └── js/
```

**Nota crítica:** O diretório `templates/` existe mas está **vazio** — nenhum template nativo foi criado!

---

## Inventário de Funcionalidades Legadas a Preservar

> **Princípio fundamental:** A Fase 7 cria páginas NOVAS com shortcodes NOVOS (`[dps_registration_v2]`, `[dps_booking_v2]`). As páginas antigas com shortcodes legados (`[dps_registration_form]`, `[dps_booking_form]`) continuam funcionando INTACTAS via dual-run (Fases 2-3). Ambos os shortcodes podem coexistir no mesmo site simultaneamente.

### Registration — Funcionalidades que o V2 DEVE Reimplementar

O add-on `desi-pet-shower-registration` (v1.3.0) possui funcionalidades que vão além de um formulário simples. O V2 deve atingir **paridade funcional** com todas elas:

| # | Funcionalidade | Descrição | Prioridade |
|---|---------------|-----------|------------|
| R1 | **Formulário de cadastro** | Campos: nome, email, telefone, CPF, endereço, pets (nome, espécie, raça, porte, observações) | P0 — Obrigatório |
| R2 | **Validação CPF** | Algoritmo Mod-11 via `validate_cpf()` + normalização `normalize_cpf()`. Opcional mas se preenchido deve ser válido | P0 — Obrigatório |
| R3 | **Validação/Normalização de telefone** | Via `DPS_Phone_Helper` do base. Formato brasileiro padrão | P0 — Obrigatório |
| R4 | **Detecção de duplicatas (phone-based)** | `find_duplicate_client()` — busca APENAS por telefone (email/CPF ignorados desde v1.3.0). Bloqueia registro duplicado para não-admin | P0 — Obrigatório |
| R5 | **reCAPTCHA v3** | Integração Google reCAPTCHA v3 com score threshold configurável. Options: `dps_registration_recaptcha_enabled/site_key/secret_key/threshold` | P1 — Importante |
| R6 | **Confirmação de email (48h)** | Token UUID com TTL de 48h. Metadata: `dps_email_confirmed`, `dps_email_confirm_token`, `dps_email_confirm_token_created`. Parâmetro URL: `dps_confirm_email` | P1 — Importante |
| R7 | **Lembretes de confirmação (cron)** | `CONFIRMATION_REMINDER_CRON` — envia lembretes para registros não confirmados após 24h | P1 — Importante |
| R8 | **Dataset de raças** | `get_breed_dataset()` — raças organizadas por espécie (cão/gato), com "populares" priorizadas. Usado em datalist | P1 — Importante |
| R9 | **Google Maps Autocomplete** | Places API para endereço com campos ocultos de coordenadas. Requer `dps_google_api_key` | P2 — Desejável |
| R10 | **Admin quick-registration (F3.2)** | Cadastro rápido pelo painel admin com checkbox `dps_admin_skip_confirmation` | P2 — Desejável |
| R11 | **REST API** | Endpoint via `register_rest_route()` com API key + rate limiting. Path protegido | P2 — Desejável |
| R12 | **Anti-spam** | Hook `dps_registration_spam_check` (filter) para validações adicionais | P1 — Importante |
| R13 | **Marketing opt-in** | Checkbox de consentimento para comunicações | P1 — Importante |

### Registration — Hooks que o V2 DEVE Disparar (via Bridge)

| Hook | Tipo | Args | Consumidor | Crítico |
|------|------|------|-----------|---------|
| `dps_registration_after_fields` | action | 0 | Loyalty (render_registration_field) | ⚠️ Sim |
| `dps_registration_after_client_created` | action | 4: `$referral_code, $client_id, $email, $phone` | Loyalty (maybe_register_referral) | ⚠️ Sim |
| `dps_registration_spam_check` | filter | 2: `$valid, $context` | Anti-spam externo | ⚠️ Sim |
| `dps_registration_agenda_url` | filter | 1: `$fallback_url` | URL override | Não |
| `dps_registration_v2_before_render` | action | 1: `$atts` | **NOVO** — extensibilidade | — |
| `dps_registration_v2_after_render` | action | 1: `$html` | **NOVO** — extensibilidade | — |
| `dps_registration_v2_before_process` | action | 1: `$data` | **NOVO** — extensibilidade | — |
| `dps_registration_v2_after_process` | action | 2: `$result, $data` | **NOVO** — extensibilidade | — |
| `dps_registration_v2_client_created` | action | 2: `$client_id, $data` | **NOVO** — extensibilidade | — |
| `dps_registration_v2_pet_created` | action | 3: `$pet_id, $client_id, $data` | **NOVO** — extensibilidade | — |

### Booking — Funcionalidades que o V2 DEVE Reimplementar

O add-on `desi-pet-shower-booking` (v1.3.0) possui funcionalidades especializadas:

| # | Funcionalidade | Descrição | Prioridade |
|---|---------------|-----------|------------|
| B1 | **Wizard multi-step** | 5 steps: cliente → pet → serviço → data/hora → confirmação | P0 — Obrigatório |
| B2 | **3 tipos de agendamento** | `simple` (avulso), `subscription` (recorrente semanal/quinzenal), `past` (registro retroativo) | P0 — Obrigatório |
| B3 | **Busca cliente por telefone** | AJAX search com seleção de cliente existente | P0 — Obrigatório |
| B4 | **Multi-pet com paginação** | Seleção múltipla de pets com "Carregar mais" e query paginada (`$pets_query->max_num_pages`) | P0 — Obrigatório |
| B5 | **Seleção de serviços** | Lista de serviços disponíveis com preços | P0 — Obrigatório |
| B6 | **Calendário de disponibilidade** | Seleção de data/hora com validação de conflitos | P0 — Obrigatório |
| B7 | **TaxiDog** | Checkbox "Solicitar TaxiDog?" + campo de preço. Metas: `appointment_taxidog`, `appointment_taxidog_price` | P1 — Importante |
| B8 | **Tosa (extras)** | Para assinaturas: checkbox tosa + preço (default R$30) + dropdown ocorrência. Metas: `appointment_tosa`, `appointment_tosa_price`, `appointment_tosa_occurrence` | P1 — Importante |
| B9 | **Confirmação via transient** | `dps_booking_confirmation_{user_id}` com TTL 5min. Dados: appointment_id, type, timestamp | P0 — Obrigatório |
| B10 | **Controle de permissões** | `manage_options`, `dps_manage_clients`, `dps_manage_pets`, `dps_manage_appointments`. Login obrigatório | P0 — Obrigatório |
| B11 | **Login check** | Redireciona para login se `!is_user_logged_in()` | P0 — Obrigatório |
| B12 | **Cache control** | `DPS_Cache_Control::force_no_cache()` para desabilitar cache em páginas de booking | P0 — Obrigatório |
| B13 | **Editar/duplicar agendamentos** | Suporte a `$edit_id` para edição de appointments existentes | P1 — Importante |
| B14 | **Skip REST/AJAX** | Retorna vazio se `REST_REQUEST` ou `wp_doing_ajax()` para evitar renderização acidental | P0 — Obrigatório |

### Booking — Hooks que o V2 DEVE Disparar (via Bridge)

| Hook | Tipo | Args | Consumidores (8 add-ons) | Crítico |
|------|------|------|-------------------------|---------|
| `dps_base_after_save_appointment` | action | 2: `$appointment_id, $meta` | Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking | ⚠️ **CRÍTICO** |
| `dps_base_appointment_fields` | action | 2: `$edit_id, $meta` | Services (injeção de campos) | ⚠️ Sim |
| `dps_base_appointment_assignment_fields` | action | 2: `$edit_id, $meta` | Groomers (campos de atribuição) | ⚠️ Sim |
| `dps_booking_v2_before_render` | action | 1: `$atts` | **NOVO** — extensibilidade | — |
| `dps_booking_v2_step_render` | action | 2: `$step, $data` | **NOVO** — extensibilidade | — |
| `dps_booking_v2_step_validate` | filter | 3: `$valid, $step, $data` | **NOVO** — extensibilidade | — |
| `dps_booking_v2_before_process` | action | 1: `$data` | **NOVO** — extensibilidade | — |
| `dps_booking_v2_after_process` | action | 2: `$result, $data` | **NOVO** — extensibilidade | — |
| `dps_booking_v2_appointment_created` | action | 2: `$appointment_id, $data` | **NOVO** — extensibilidade | — |

### Options/Settings que o V2 Deve Respeitar

| Option | Uso | Origem |
|--------|-----|--------|
| `dps_registration_page_id` | ID da página de cadastro | Base settings |
| `dps_booking_page_id` | ID da página de agendamento | Base settings |
| `dps_registration_recaptcha_enabled` | Toggle reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_site_key` | Chave pública reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_secret_key` | Chave secreta reCAPTCHA | Registration settings |
| `dps_registration_recaptcha_threshold` | Score mínimo (0-1) | Registration settings |
| `dps_google_api_key` | API key Google Maps | Base settings |
| `dps_registration_confirm_email_enabled` | Toggle confirmação email | Registration settings |
| `dps_frontend_feature_flags` | Feature flags do frontend | Frontend settings |

---

## Objetivo da Fase 7

### Visão

**Criar implementações 100% nativas** dos formulários de cadastro e agendamento, **do zero**, sem reutilizar código legado.

### Metas Específicas

#### 1. Novos Shortcodes Nativos

Criar shortcodes completamente novos que não dependam dos legados:

- `[dps_registration_v2]` — cadastro nativo M3
- `[dps_booking_v2]` — agendamento nativo M3
- `[dps_client_portal]` — portal do cliente (futuro)

#### 2. Templates Modernos

Criar sistema de templates reutilizáveis:

```
templates/
├── registration/
│   ├── form-main.php              ← Formulário principal
│   ├── form-client-data.php       ← Seção dados do cliente
│   ├── form-pet-data.php          ← Seção dados do pet
│   ├── form-success.php           ← Tela de sucesso
│   └── form-error.php             ← Tela de erro
├── booking/
│   ├── form-main.php
│   ├── step-client-selection.php
│   ├── step-pet-selection.php
│   ├── step-service-selection.php
│   ├── step-datetime-selection.php
│   ├── step-confirmation.php
│   └── form-success.php
└── components/
    ├── field-text.php
    ├── field-select.php
    ├── field-phone.php
    ├── field-email.php
    ├── button-primary.php
    ├── button-secondary.php
    ├── card.php
    ├── alert.php
    └── loader.php
```

#### 3. Handlers Nativos

Criar processadores de formulário independentes:

```
includes/
├── handlers/
│   ├── class-dps-registration-handler.php     ← Processa cadastro
│   ├── class-dps-booking-handler.php          ← Processa agendamento
│   └── class-dps-form-validator.php           ← Validação centralizada
├── services/
│   ├── class-dps-client-service.php           ← CRUD de clientes
│   ├── class-dps-pet-service.php              ← CRUD de pets
│   ├── class-dps-appointment-service.php      ← CRUD de agendamentos
│   ├── class-dps-breed-provider.php           ← Dataset de raças por espécie
│   ├── class-dps-recaptcha-service.php        ← Verificação reCAPTCHA v3
│   ├── class-dps-email-confirmation-service.php ← Tokens 48h + cron lembretes
│   ├── class-dps-duplicate-detector.php       ← Detecção duplicatas (phone-based)
│   └── class-dps-booking-confirmation-service.php ← Transient de confirmação
├── bridges/
│   ├── class-dps-registration-hook-bridge.php ← Bridge hooks registration (Loyalty)
│   └── class-dps-booking-hook-bridge.php      ← Bridge hooks booking (8 add-ons)
├── validators/
│   ├── class-dps-cpf-validator.php            ← Validação CPF mod-11
│   └── class-dps-booking-validator.php        ← Validações complexas booking
└── ajax/
    ├── class-dps-ajax-client-search.php       ← Busca cliente por telefone
    ├── class-dps-ajax-pet-list.php            ← Lista pets do cliente (paginado)
    ├── class-dps-ajax-available-slots.php     ← Horários disponíveis
    ├── class-dps-ajax-services-list.php       ← Serviços disponíveis com preços
    └── class-dps-ajax-validate-step.php       ← Validação de step server-side
```

#### 4. Assets Nativos M3 Completos

```
assets/
├── css/
│   ├── registration-v2.css        ← CSS nativo cadastro M3
│   ├── booking-v2.css             ← CSS nativo agendamento M3
│   └── components.css             ← Componentes reutilizáveis
└── js/
    ├── registration-v2.js         ← JS nativo cadastro
    ├── booking-v2.js              ← JS nativo agendamento
    └── form-utils.js              ← Utilitários compartilhados
```

#### 5. Independência Total

**Remover todas as dependências dos add-ons legados:**
- ❌ Não chamar `DPS_Registration_Addon::get_instance()`
- ❌ Não chamar `DPS_Booking_Addon::get_instance()`
- ❌ Não delegar para métodos legados
- ✅ Implementar toda lógica nativamente
- ✅ Reutilizar apenas helpers globais do base (`DPS_Money_Helper`, etc.)

---

## Arquitetura Proposta

### Princípios Arquiteturais

1. **Separation of Concerns**
   - Templates = apresentação pura
   - Handlers = lógica de negócio
   - Services = acesso a dados
   - Validators = regras de validação

2. **Dependency Injection**
   - Sem singletons
   - Composição via construtor
   - Testabilidade

3. **Modern PHP 8.4**
   - Constructor promotion
   - Readonly properties
   - Typed properties
   - Return types
   - Enums para estados

4. **Material 3 Expressive Native**
   - HTML semântico desde o início
   - Design tokens CSS em todos os componentes
   - Acessibilidade ARIA nativa
   - Motion expressivo opcional

### Diagrama de Fluxo — Registration V2

```
┌─────────────────────────────────────────────────────────────┐
│ [dps_registration_v2] shortcode                             │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ DPS_Frontend_Registration_V2_Module                         │
│  └─ renderShortcode()                                       │
│      ├─ Valida nonce se POST                                │
│      ├─ Se GET: renderiza form (templates/registration/)    │
│      └─ Se POST: processa via Handler                       │
└────────────────┬────────────────────────────────────────────┘
                 │
    ┌────────────┴────────────┐
    │ POST?                   │ GET?
    ▼                         ▼
┌──────────────────────┐  ┌─────────────────────────┐
│ Registration Handler │  │ Template Engine         │
│  └─ process()        │  │  └─ render_form_main()  │
│     ├─ Valida dados  │  │     ├─ form-client-data │
│     ├─ Sanitiza      │  │     ├─ form-pet-data    │
│     ├─ Cria cliente  │  │     └─ Components       │
│     ├─ Cria pet(s)   │  └─────────────────────────┘
│     ├─ Dispara hooks │
│     └─ Retorna result│
└──────────┬───────────┘
           │
           ▼
┌──────────────────────────────────┐
│ Client Service                   │
│  └─ createClient()               │
│     └─ wp_insert_post()          │
└──────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────┐
│ Pet Service                      │
│  └─ createPet()                  │
│     └─ wp_insert_post()          │
└──────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────┐
│ Hooks de Integração              │
│  ├─ dps_registration_v2_created  │ ← NOVO
│  └─ dps_base_after_client_create │ ← Reutiliza base
└──────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────┐
│ Success Template                 │
│  └─ templates/registration/      │
│      form-success.php            │
└──────────────────────────────────┘
```

### Diagrama de Fluxo — Booking V2

```
┌─────────────────────────────────────────────────────────────┐
│ [dps_booking_v2] shortcode                                  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ DPS_Frontend_Booking_V2_Module                              │
│  └─ renderShortcode()                                       │
│      ├─ Detecta step atual (query param ?step=X)            │
│      ├─ Renderiza step apropriado                           │
│      └─ Processa transição entre steps                      │
└────────────────┬────────────────────────────────────────────┘
                 │
        ┌────────┴────────┬────────┬─────────┬──────────┐
        ▼                 ▼        ▼         ▼          ▼
    ┌─────────┐      ┌────────┐ ┌──────┐ ┌──────┐ ┌─────────┐
    │ Step 1  │      │ Step 2 │ │Step 3│ │Step 4│ │ Step 5  │
    │ Cliente │  →   │  Pet   │ →│Serviço│→│Data │ →│Confirma│
    └─────────┘      └────────┘ └──────┘ └──────┘ └─────────┘
         │                                              │
         └──────────────────────────────────────────────┘
                              ▼
                    ┌─────────────────────┐
                    │ Booking Handler     │
                    │  └─ process()       │
                    │     ├─ Valida tudo  │
                    │     ├─ Cria appoint.│
                    │     ├─ Dispara hooks│
                    │     └─ Email confirm│
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌───────────────────────────────┐
                    │ Appointment Service           │
                    │  └─ createAppointment()       │
                    │     └─ wp_insert_post()       │
                    └───────────┬───────────────────┘
                                │
                                ▼
                    ┌───────────────────────────────┐
                    │ Hooks de Integração           │
                    │  ├─ dps_booking_v2_created    │ ← NOVO
                    │  └─ dps_base_after_save_appt  │ ← Mantém
                    └───────────┬───────────────────┘
                                │
                                ▼
                    ┌───────────────────────────────┐
                    │ Success Template + Email      │
                    └───────────────────────────────┘
```

---

## Implementação da Hook Bridge

### Conceito

A hook bridge é o mecanismo que garante **compatibilidade retroativa** durante a coexistência v1/v2. Quando o v2 processa uma ação (criar cliente, criar pet, criar agendamento), ele dispara **AMBOS** os hooks: o novo (v2) e o legado, garantindo que add-ons existentes (Loyalty, Stock, Payment, etc.) continuem funcionando sem alterações.

### Implementação — Registration Hook Bridge

```php
class DPS_Registration_Hook_Bridge {
    
    /**
     * Dispara hooks após criação de cliente no v2.
     * Mantém compatibilidade com Loyalty e outros add-ons.
     */
    public function afterClientCreated(
        int $client_id,
        string $email,
        string $phone,
        string $referral_code = ''
    ): void {
        // 1. Hook NOVO v2 (para novos consumidores)
        do_action( 'dps_registration_v2_client_created', $client_id, [
            'email'         => $email,
            'phone'         => $phone,
            'referral_code' => $referral_code,
        ] );
        
        // 2. Hook LEGADO (para Loyalty e outros add-ons existentes)
        // Assinatura IDÊNTICA ao legado: ($referral_code, $client_id, $email, $phone)
        do_action(
            'dps_registration_after_client_created',
            $referral_code,
            $client_id,
            $email,
            $phone
        );
    }
    
    /**
     * Dispara hook de campos adicionais no formulário.
     * Permite que Loyalty injete campo de referral code.
     */
    public function afterFormFields(): void {
        // Hook legado (Loyalty: render_registration_field)
        do_action( 'dps_registration_after_fields' );
    }
    
    /**
     * Aplica filtro anti-spam.
     * Permite validações externas adicionais.
     */
    public function applySpamCheck( bool $valid, array $context ): bool {
        return apply_filters( 'dps_registration_spam_check', $valid, $context );
    }
}
```

### Implementação — Booking Hook Bridge

```php
class DPS_Booking_Hook_Bridge {
    
    /**
     * Dispara hooks após criação de agendamento no v2.
     * CRÍTICO: 8 add-ons consomem dps_base_after_save_appointment.
     */
    public function afterAppointmentCreated(
        int $appointment_id,
        array $meta
    ): void {
        // 1. Hook NOVO v2
        do_action( 'dps_booking_v2_appointment_created', $appointment_id, $meta );
        
        // 2. Hook LEGADO CRÍTICO (8 consumidores)
        // Assinatura IDÊNTICA: ($appointment_id, $meta)
        // Consumidores: Stock, Payment, Groomers, Calendar,
        //               Communications, Push, Services, Booking
        do_action( 'dps_base_after_save_appointment', $appointment_id, $meta );
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
     * Dispara hooks de validação de step (filtro novo).
     * Permite validações externas por step.
     */
    public function validateStep( bool $valid, int $step, array $data ): bool {
        return apply_filters( 'dps_booking_v2_step_validate', $valid, $step, $data );
    }
}
```

### Regras da Hook Bridge

1. **Ordem de disparo:** Hook v2 PRIMEIRO, hook legado DEPOIS (permite que v2 consumers atuem antes)
2. **Assinatura idêntica:** Os hooks legados DEVEM receber exatamente os mesmos argumentos/tipos do legado
3. **Sem condicionais:** A bridge SEMPRE dispara ambos os hooks — não importa se há consumidores ou não
4. **Testes obrigatórios:** Cada hook bridge deve ter teste que valida disparo de ambos os hooks
5. **Monitoramento:** Logger deve registrar cada disparo de hook bridge para telemetria

---

## Estratégia de Migração

### Fase 7.1 — Preparação (Sprint 1-2)

**Objetivo:** Estruturar arquitetura sem quebrar nada

✅ **Tarefas:**
1. Criar estrutura de diretórios (`templates/`, `handlers/`, `services/`, `ajax/`, `bridges/`)
2. Implementar classes base abstratas:
   - `Abstract_Module_V2` — base para módulos nativos
   - `Abstract_Handler` — base para handlers
   - `Abstract_Service` — base para services
   - `Abstract_Validator` — base para validadores
3. Criar sistema de template engine simples
4. Implementar componentes reutilizáveis básicos (button, field, card, alert)
5. Implementar Hook Bridge base (classes `DPS_Registration_Hook_Bridge` e `DPS_Booking_Hook_Bridge`)
6. Documentar padrões de código e convenções

✅ **Feature Flags:**
- Criar nova flag `registration_v2` (desabilitada por padrão)
- Criar nova flag `booking_v2` (desabilitada por padrão)
- Manter flags antigas (`registration`, `booking`) funcionando
- **Importante:** flags v1 e v2 são independentes — ambas podem estar ativas ao mesmo tempo (coexistência)

✅ **Critérios de Aceite:**
- [ ] Estrutura de diretórios criada (incluindo `bridges/`)
- [ ] Classes base implementadas
- [ ] Template engine funcional
- [ ] 5+ componentes reutilizáveis prontos
- [ ] Feature flags novas criadas
- [ ] Hook Bridge base implementada e testada
- [ ] Zero quebra de funcionalidade existente

### Fase 7.2 — Registration V2 (Sprint 3-5)

**Objetivo:** Implementação nativa completa do cadastro com paridade funcional ao legado

> **Referência:** Ver [Inventário de Funcionalidades Legadas — Registration](#registration--funcionalidades-que-o-v2-deve-reimplementar) para a lista completa de features R1-R13.

✅ **Tarefas:**
1. **Templates Registration:**
   - `form-main.php` — estrutura principal
   - `form-client-data.php` — campos do cliente (nome, email, telefone, CPF, endereço)
   - `form-pet-data.php` — campos do pet (repeater: nome, espécie, raça com datalist, porte, observações)
   - `form-success.php` — sucesso (com CTA para agendamento)
   - `form-error.php` — erro
   - `form-duplicate-warning.php` — aviso de telefone duplicado (com opção admin override)

2. **Handler e Services:**
   - `DPS_Registration_Handler` — processa formulário
   - `DPS_Client_Service` — CRUD de clientes (wp_insert_post)
   - `DPS_Pet_Service` — CRUD de pets (wp_insert_post + metas: espécie, raça, porte)
   - `DPS_Form_Validator` — validações (CPF mod-11, telefone, email, required)
   - `DPS_Duplicate_Detector` — busca por telefone (phone-only, conforme legado v1.3.0)
   - `DPS_Breed_Provider` — dataset de raças por espécie (reutilizar `get_breed_dataset()` do legado)

3. **Integrações de Segurança:**
   - reCAPTCHA v3 — ler options `dps_registration_recaptcha_*`, validar server-side
   - Anti-spam — aplicar filtro `dps_registration_spam_check` via Hook Bridge
   - Duplicate detection — bloquear se telefone duplicado (non-admin)
   - Nonce + capability check + sanitização completa

4. **Email e Confirmação:**
   - Confirmação de email 48h (reutilizar lógica de token UUID)
   - HTML template de email M3 para confirmação
   - Cron de lembretes (registrar `CONFIRMATION_REMINDER_CRON` se não existir)
   - Respeitar option `dps_registration_confirm_email_enabled`

5. **Hook Bridge Registration (CRÍTICO):**
   - Integrar `DPS_Registration_Hook_Bridge` em todos os pontos
   - Disparar `dps_registration_after_fields` no template do formulário
   - Disparar `dps_registration_after_client_created` após criação (4 args)
   - Aplicar `dps_registration_spam_check` antes de processar
   - Testes de integração com Loyalty add-on

6. **Assets Nativos:**
   - `registration-v2.css` — estilos M3 puros
   - `registration-v2.js` — comportamento nativo (validação client-side, repeater de pets, datalist de raças)
   - Integração com design tokens
   - Condicional: Google Maps Places API se `dps_google_api_key` configurada

7. **Módulo V2:**
   - `DPS_Frontend_Registration_V2_Module`
   - Shortcode `[dps_registration_v2]`
   - Zero dependência do legado (usa serviços e helpers nativos)

8. **Hooks Novos + Bridge:**
   - `dps_registration_v2_before_render` — antes de renderizar form
   - `dps_registration_v2_after_render` — depois de renderizar form
   - `dps_registration_v2_before_process` — antes de processar
   - `dps_registration_v2_after_process` — depois de processar
   - `dps_registration_v2_client_created` — cliente criado
   - `dps_registration_v2_pet_created` — pet criado
   - **Bridge:** `dps_registration_after_client_created` (4 args — Loyalty)
   - **Bridge:** `dps_registration_after_fields` (0 args — Loyalty)
   - **Bridge:** `dps_registration_spam_check` (filter — anti-spam)

9. **Validação e Testes:**
   - Testes funcionais completos (ver [Estratégia de Testes](#estratégia-de-testes))
   - Validação WCAG 2.1 AA
   - Performance benchmark
   - Teste em mobile/tablet/desktop
   - Teste de integração com Loyalty add-on (referral code)
   - Teste de reCAPTCHA v3 (se habilitado)
   - Teste de email confirmation flow

✅ **Critérios de Aceite:**
- [ ] Formulário renderiza 100% nativo (HTML M3)
- [ ] Processa cadastro sem chamar add-on legado
- [ ] Cria cliente e pet corretamente (wp_insert_post + metas)
- [ ] Valida todos os campos (client-side + server-side): nome, email, telefone, CPF (mod-11)
- [ ] Detecção de duplicatas por telefone funciona (bloqueio + admin override)
- [ ] reCAPTCHA v3 integrado (quando habilitado nas options)
- [ ] Confirmação de email 48h funciona (token + cron de lembretes)
- [ ] Dataset de raças por espécie funciona (datalist)
- [ ] Google Maps autocomplete funciona (quando API key presente)
- [ ] Dispara hooks de integração via bridge (Loyalty referral funcional)
- [ ] Anti-spam filter `dps_registration_spam_check` aplicado
- [ ] CSS 100% design tokens M3
- [ ] JavaScript vanilla (zero jQuery)
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Performance < 2s render, < 500ms submit
- [ ] Rollback instantâneo (flag `registration_v2`)
- [ ] Shortcode legado `[dps_registration_form]` continua funcionando intacto

### Fase 7.3 — Booking V2 (Sprint 6-10)

**Objetivo:** Implementação nativa completa do agendamento com paridade funcional ao legado

> **Referência:** Ver [Inventário de Funcionalidades Legadas — Booking](#booking--funcionalidades-que-o-v2-deve-reimplementar) para a lista completa de features B1-B14.

✅ **Tarefas:**
1. **Templates Booking (Multi-step):**
   - `form-main.php` — wizard container
   - `step-client-selection.php` — Step 1: busca/seleção cliente (AJAX)
   - `step-pet-selection.php` — Step 2: seleção de pets (com paginação "Carregar mais")
   - `step-service-selection.php` — Step 3: escolha de serviços com preços
   - `step-datetime-selection.php` — Step 4: data/hora com validação de conflitos
   - `step-confirmation.php` — Step 5: revisão final com resumo de preços
   - `step-extras.php` — **NOVO**: TaxiDog + Tosa (extras condicionais por tipo)
   - `form-success.php` — confirmação pós-criação
   - `form-login-required.php` — **NOVO**: tela de redirecionamento para login

2. **Tipos de Agendamento (3 modos):**
   - `simple` — agendamento avulso (padrão)
   - `subscription` — agendamento recorrente (semanal/quinzenal), com extras de tosa
   - `past` — registro retroativo de serviço já realizado
   - Seletor de tipo no Step 1 ou como atributo do shortcode

3. **Handler e Services:**
   - `DPS_Booking_Handler` — processa wizard (state machine)
   - `DPS_Appointment_Service` — CRUD de agendamentos (wp_insert_post + metas)
   - `DPS_Service_Availability_Service` — horários disponíveis com validação de conflitos
   - `DPS_Booking_Validator` — validações complexas (conflitos, permissões, limites)
   - `DPS_Booking_Confirmation_Service` — gerencia transient de confirmação (`dps_booking_confirmation_{user_id}`, TTL 5min)

4. **Controle de Acesso:**
   - Login obrigatório (`is_user_logged_in()`) — redireciona para `wp_login_url()` com return
   - Capabilities: `manage_options` OU `dps_manage_clients` OU `dps_manage_pets` OU `dps_manage_appointments`
   - Skip em REST_REQUEST e wp_doing_ajax() (evitar renderização acidental)
   - Cache control: `DPS_Cache_Control::force_no_cache()` na página de booking

5. **Extras — TaxiDog e Tosa:**
   - TaxiDog: checkbox + campo de preço (metas: `appointment_taxidog`, `appointment_taxidog_price`)
   - Tosa: apenas para `subscription` — checkbox + preço (default R$30) + dropdown de ocorrência
   - Metas: `appointment_tosa`, `appointment_tosa_price`, `appointment_tosa_occurrence`
   - UI: card estilizado M3 com ícones e descrição

6. **AJAX Endpoints:**
   - `wp_ajax_dps_search_client` — busca cliente por telefone
   - `wp_ajax_dps_get_pets` — lista pets do cliente (com paginação)
   - `wp_ajax_dps_get_services` — serviços disponíveis com preços
   - `wp_ajax_dps_get_slots` — horários livres para data selecionada
   - `wp_ajax_dps_validate_step` — valida step atual server-side
   - Todos com nonce + capability check + sanitização

7. **Assets Nativos:**
   - `booking-v2.css` — estilos M3 wizard
   - `booking-v2.js` — wizard state machine (vanilla JS)
   - Animações de transição entre steps (`prefers-reduced-motion` respeitado)

8. **Módulo V2:**
   - `DPS_Frontend_Booking_V2_Module`
   - Shortcode `[dps_booking_v2]`
   - State management para wizard (sessão + URL query param `?step=X`)
   - Suporte a edição/duplicação (`$edit_id` via atributo ou query param)

9. **Hooks Novos + Bridge (CRÍTICO):**
   - `dps_booking_v2_before_render` — antes de renderizar
   - `dps_booking_v2_step_render` — ao renderizar step
   - `dps_booking_v2_step_validate` — validação de step (filter)
   - `dps_booking_v2_before_process` — antes de criar appointment
   - `dps_booking_v2_after_process` — depois de criar
   - `dps_booking_v2_appointment_created` — appointment criado
   - **Bridge CRÍTICA:** `dps_base_after_save_appointment` (8 consumidores: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking)
   - **Bridge:** `dps_base_appointment_fields` (Services — injeção de campos)
   - **Bridge:** `dps_base_appointment_assignment_fields` (Groomers — campos de atribuição)

10. **Integrações Críticas (via Hook Bridge):**
    - Stock (consumo de produtos) — via `dps_base_after_save_appointment`
    - Payment (link de pagamento) — via `dps_base_after_save_appointment`
    - Groomers (atribuição de tosador) — via `dps_base_after_save_appointment` + `dps_base_appointment_assignment_fields`
    - Calendar (sincronização Google Calendar) — via `dps_base_after_save_appointment`
    - Communications (notificações email/WhatsApp) — via `dps_base_after_save_appointment`
    - Push (notificações push) — via `dps_base_after_save_appointment`
    - Services (snapshot de valores) — via `dps_base_after_save_appointment`
    - **Testar CADA integração** individualmente e em conjunto

✅ **Critérios de Aceite:**
- [ ] Wizard funciona com 5 steps + extras condicionais
- [ ] 3 tipos de agendamento suportados (simple, subscription, past)
- [ ] State management robusto (sessão + URL)
- [ ] AJAX endpoints funcionais e seguros (nonce + capability)
- [ ] Busca de cliente por telefone OK
- [ ] Seleção múltipla de pets com paginação OK
- [ ] TaxiDog checkbox + preço funcional
- [ ] Tosa extras para subscription funcional (preço + ocorrência)
- [ ] Calendário de disponibilidade com validação de conflitos OK
- [ ] Confirmação via transient (5min TTL) OK
- [ ] Login check + redirecionamento funcional
- [ ] Cache control desabilitado na página de booking
- [ ] Edição/duplicação de agendamentos existentes OK
- [ ] Cria appointment corretamente com TODAS as metas
- [ ] Dispara **TODOS** os hooks críticos via bridge (8 add-ons)
- [ ] Email de confirmação enviado
- [ ] CSS 100% M3 (wizard expressivo)
- [ ] Animações de transição suaves (respeita `prefers-reduced-motion`)
- [ ] Validação robusta (client + server)
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Performance < 3s render, < 1s transição, < 200ms step change
- [ ] Funciona em mobile (touch-friendly)
- [ ] Rollback instantâneo (flag `booking_v2`)
- [ ] Shortcode legado `[dps_booking_form]` continua funcionando intacto

### Fase 7.4 — Coexistência e Migração (Sprint 11-12)

**Objetivo:** Permitir escolha entre v1 (dual-run) e v2 (nativo)

✅ **Tarefas:**
1. **Documentação de Migração:**
   - Guia passo a passo para migrar de v1 para v2
   - Comparação de features v1 vs v2
   - Checklist de compatibilidade
   - Plano de rollback

2. **Testes de Migração:**
   - Script de validação de compatibilidade
   - Testes side-by-side (v1 e v2 ao mesmo tempo)
   - Validação de hooks em ambas versões

3. **Telemetria V2:**
   - Adicionar tracking de uso v2
   - Comparar métricas v1 vs v2
   - Dashboard de adoção

4. **Ferramentas Admin:**
   - Toggle fácil entre v1/v2 na aba Settings
   - Indicador visual de qual versão está ativa
   - Link para guia de migração

✅ **Critérios de Aceite:**
- [ ] v1 e v2 podem coexistir
- [ ] Documentação de migração completa
- [ ] Script de validação funcional
- [ ] Telemetria v2 implementada
- [ ] Admin UI para toggle v1/v2
- [ ] Guia de troubleshooting

### Fase 7.5 — Depreciação do Dual-Run (Sprint 13-18+)

**Objetivo:** Descontinuar v1 após adoção massiva de v2

⚠️ **ATENÇÃO:** Esta fase só deve iniciar após:
- ✅ 90+ dias de v2 em produção estável
- ✅ 80%+ dos sites migraram para v2
- ✅ Zero bugs críticos em v2
- ✅ Telemetria confirma uso < 5% de v1

✅ **Tarefas:**
1. **Comunicação Formal:**
   - Anúncio de depreciação (180 dias antecedência)
   - Email para todos os clientes
   - Banner no admin WordPress
   - Documentação atualizada

2. **Período de Observação:**
   - 90 dias dual-run obrigatório
   - 60 dias aviso de remoção
   - 30 dias observação final

3. **Remoção do Legado (apenas após aprovação):**
   - Remover `DPS_Registration_Addon`
   - Remover `DPS_Booking_Addon`
   - Remover código dual-run v1
   - Manter apenas v2

---

## Novos Shortcodes Nativos

### Registration V2

```php
/**
 * Shortcode: [dps_registration_v2]
 * 
 * Exibe formulário nativo de cadastro Material 3 Expressive.
 * Completamente independente do add-on legado.
 * 
 * @param array $atts Atributos do shortcode
 * @return string HTML renderizado
 */
[dps_registration_v2]
```

**Atributos aceitos:**
- `redirect_url` — URL de redirecionamento pós-sucesso (padrão: página de agendamento)
- `show_pets` — exibir seção de pets (padrão: `true`)
- `show_marketing` — exibir opt-in de marketing (padrão: `true`)
- `theme` — tema visual: `light|dark` (padrão: `light`)
- `compact` — modo compacto (padrão: `false`)

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
 * Exibe wizard nativo de agendamento Material 3 Expressive.
 * Multi-step com state management robusto.
 * Completamente independente do add-on legado.
 * 
 * @param array $atts Atributos do shortcode
 * @return string HTML renderizado
 */
[dps_booking_v2]
```

**Atributos aceitos:**
- `client_id` — pré-selecionar cliente (opcional)
- `service_id` — pré-selecionar serviço (opcional)
- `start_step` — step inicial: `1-5` (padrão: `1`)
- `show_progress` — exibir barra de progresso (padrão: `true`)
- `theme` — tema visual: `light|dark` (padrão: `light`)
- `compact` — modo compacto (padrão: `false`)
- `appointment_type` — tipo de agendamento: `simple|subscription|past` (padrão: `simple`)
- `edit_id` — ID do agendamento para edição (opcional)

**Exemplos:**
```
[dps_booking_v2]
[dps_booking_v2 client_id="123"]
[dps_booking_v2 service_id="456" start_step="3"]
[dps_booking_v2 show_progress="true" theme="light"]
[dps_booking_v2 appointment_type="subscription"]
[dps_booking_v2 edit_id="789"]
```

### Comparação v1 vs v2

| Feature | v1 (Dual-Run) | v2 (Nativo) |
|---------|---------------|-------------|
| **Shortcode** | `[dps_registration_form]` | `[dps_registration_v2]` |
| **Dependência Legado** | ✅ Sim (obrigatório) | ❌ Não (independente) |
| **HTML** | Legado (estrutura antiga) | Nativo M3 (semântico) |
| **CSS** | Legado + wrapper | 100% M3 Expressive |
| **JavaScript** | Legado (jQuery) | Vanilla JS (moderno) |
| **Acessibilidade** | Limitada | WCAG 2.1 AA |
| **Performance** | ~3-4s render | ~1-2s render |
| **Customização** | Limitada | Totalmente flexível |
| **Hooks** | Legados | Novos + bridge legados |
| **Templates** | Hardcoded | Reutilizáveis |
| **Rollback** | Flag `registration` | Flag `registration_v2` |

---

## Coexistência de Shortcodes v1 e v2

### Princípio Fundamental

Os shortcodes v1 (`[dps_registration_form]`, `[dps_booking_form]`) e v2 (`[dps_registration_v2]`, `[dps_booking_v2]`) **coexistem independentemente**. Ambos podem estar ativos no mesmo site WordPress ao mesmo tempo.

### Cenários de Coexistência

```
┌──────────────────────────────────────────────────────────┐
│ CENÁRIO 1: Transição Gradual (RECOMENDADO)               │
│                                                          │
│  Página A: [dps_registration_form]  ← legado (v1)       │
│  Página B: [dps_registration_v2]    ← nova (v2)         │
│                                                          │
│  Ambas ativas. Admin testa v2 enquanto v1 serve público. │
│  Quando satisfeito, troca link público para Página B.    │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ CENÁRIO 2: Substituição Direta                           │
│                                                          │
│  Página existente: trocar shortcode de                   │
│  [dps_registration_form] para [dps_registration_v2]      │
│                                                          │
│  Rollback: trocar de volta e desabilitar flag v2.        │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ CENÁRIO 3: Side-by-Side para Testes                      │
│                                                          │
│  Mesma página pode ter AMBOS os shortcodes (debug).      │
│  [dps_registration_form] mostra v1, [dps_registration_v2]│
│  mostra v2 lado a lado para comparação visual.           │
└──────────────────────────────────────────────────────────┘
```

### Isolamento Garantido

- **v1** (`[dps_registration_form]`, `[dps_booking_form]`) continua usando dual-run (Fases 2-3):
  - Registrado por `DPS_Frontend_Registration_Module` / `DPS_Frontend_Booking_Module`
  - Delega para add-ons legados (`DPS_Registration_Addon`, `DPS_Booking_Addon`)
  - Feature flags: `registration`, `booking`

- **v2** (`[dps_registration_v2]`, `[dps_booking_v2]`) é completamente independente:
  - Registrado por `DPS_Frontend_Registration_V2_Module` / `DPS_Frontend_Booking_V2_Module`
  - Zero referência aos add-ons legados
  - Feature flags: `registration_v2`, `booking_v2`

- **Sem conflito:** Os shortcodes são diferentes, os módulos são diferentes, os assets são diferentes (namespaced CSS classes)

### Matrix de Feature Flags

| Flag | Shortcode Controlado | Dependência Legada | Pode Coexistir |
|------|---------------------|-------------------|---------------|
| `registration` | `[dps_registration_form]` | ✅ Sim (dual-run) | ✅ Com `registration_v2` |
| `booking` | `[dps_booking_form]` | ✅ Sim (dual-run) | ✅ Com `booking_v2` |
| `registration_v2` | `[dps_registration_v2]` | ❌ Não (nativo) | ✅ Com `registration` |
| `booking_v2` | `[dps_booking_v2]` | ❌ Não (nativo) | ✅ Com `booking` |
| `settings` | Aba admin "Frontend" | ❌ Não | ✅ Sempre |

### Guia de Migração para Administradores

1. **Ativar v2:** `wp option patch update dps_frontend_feature_flags registration_v2 1`
2. **Criar nova página** com `[dps_registration_v2]` (ou editar página existente)
3. **Testar** completamente (cadastro, validação, email, integração Loyalty)
4. **Quando satisfeito:** apontar links públicos para a nova página
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
├── registration/
│   ├── form-main.php                 ← Wrapper principal
│   ├── form-client-data.php          ← Seção cliente (nome, email, telefone, CPF, endereço)
│   ├── form-pet-data.php             ← Seção pet (repeater: nome, espécie, raça datalist, porte, obs)
│   ├── form-duplicate-warning.php    ← NOVO: aviso telefone duplicado (admin override)
│   ├── form-success.php              ← Sucesso (com CTA agendamento)
│   └── form-error.php                ← Erro
├── booking/
│   ├── form-main.php                 ← Wizard container
│   ├── step-client-selection.php     ← Step 1: Cliente (busca AJAX por telefone)
│   ├── step-pet-selection.php        ← Step 2: Pet (multi-select com paginação)
│   ├── step-service-selection.php    ← Step 3: Serviço (com preços)
│   ├── step-datetime-selection.php   ← Step 4: Data/Hora (calendário + conflitos)
│   ├── step-confirmation.php         ← Step 5: Confirmação (resumo completo)
│   ├── step-extras.php               ← NOVO: TaxiDog + Tosa (condicional por tipo)
│   ├── form-success.php              ← Sucesso (confirmação pós-criação)
│   ├── form-login-required.php       ← NOVO: redirecionamento para login
│   └── form-type-selector.php        ← NOVO: seletor tipo (simple/subscription/past)
├── emails/
│   ├── registration-confirmation.php ← NOVO: email confirmação M3
│   └── booking-confirmation.php      ← NOVO: email confirmação agendamento M3
└── components/
    ├── field-text.php                ← Input text M3
    ├── field-email.php               ← Input email M3
    ├── field-phone.php               ← Input phone M3
    ├── field-cpf.php                 ← NOVO: Input CPF M3 (máscara + validação)
    ├── field-address.php             ← NOVO: Input endereço M3 (Google Maps autocomplete)
    ├── field-select.php              ← Select M3
    ├── field-datalist.php            ← NOVO: Input com datalist M3 (raças)
    ├── field-textarea.php            ← Textarea M3
    ├── field-checkbox.php            ← Checkbox M3
    ├── field-currency.php            ← NOVO: Input moeda M3 (preço TaxiDog/Tosa)
    ├── button-primary.php            ← Botão primário M3
    ├── button-secondary.php          ← Botão secundário M3
    ├── button-text.php               ← Botão texto M3
    ├── card.php                      ← Card M3
    ├── alert.php                     ← Alert M3
    ├── loader.php                    ← Loader M3
    ├── progress-bar.php              ← Barra de progresso
    ├── wizard-steps.php              ← Indicador de steps
    └── recaptcha-badge.php           ← NOVO: reCAPTCHA v3 badge M3
```

### Exemplo de Template — Registration Form Main

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

// Dados disponíveis:
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
        
        <!-- Seção Cliente -->
        <?php echo $this->render( 'registration/form-client-data.php', $data ); ?>
        
        <!-- Seção Pet (condicional) -->
        <?php if ( $show_pets ) : ?>
            <?php echo $this->render( 'registration/form-pet-data.php', $data ); ?>
        <?php endif; ?>
        
        <!-- Marketing Opt-in -->
        <?php if ( $show_marketing ) : ?>
            <div class="dps-field-group">
                <?php echo $this->render( 'components/field-checkbox.php', [
                    'name'    => 'marketing_optin',
                    'label'   => __( 'Desejo receber novidades e promoções', 'dps-frontend-addon' ),
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

### Exemplo de Template — Component Field Text

```php
<?php
/**
 * Component: Text Field (M3 Expressive)
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
            <span class="dps-field-required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
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

## Reutilização de Helpers Globais do Base

### Princípio

O V2 NÃO deve reimplementar lógica que já existe nos helpers globais do `desi-pet-shower-base`. A regra é: **reutilizar SEMPRE que disponível**, reimplementar APENAS o que é específico do frontend.

### Helpers do Base a Reutilizar

| Helper | Métodos Relevantes | Uso no V2 |
|--------|-------------------|-----------|
| `DPS_Phone_Helper` | `normalize()`, `format()`, `validate()` | Validação e formatação de telefone no cadastro e busca |
| `DPS_Money_Helper` | `format()`, `parse()`, `to_cents()` | Exibição de preços (serviços, TaxiDog, Tosa) |
| `DPS_URL_Builder` | `build()`, `admin_url()` | Construção de URLs de redirecionamento |
| `DPS_Message_Helper` | `success()`, `error()`, `warning()` | Feedback consistente para o usuário |
| `DPS_Cache_Control` | `force_no_cache()` | Desabilitar cache em páginas de booking |

### Lógica a Reimplementar Nativamente

| Componente | Motivo | Referência Legada |
|-----------|--------|-------------------|
| Validação CPF (mod-11) | Lógica simples, sem helper global. Extrair para `DPS_Cpf_Validator` | `DPS_Registration_Addon::validate_cpf()` |
| Breed dataset | Dados estáticos, extrair para provider reutilizável | `DPS_Registration_Addon::get_breed_dataset()` |
| reCAPTCHA v3 verification | Integração com API Google, extrair para service | `DPS_Registration_Addon::verify_recaptcha_token()` |
| Email confirmation tokens | Lógica de token UUID + TTL, extrair para service | `DPS_Registration_Addon::send_confirmation_email()` |
| Booking state machine | Lógica nova específica do wizard v2 | N/A (conceito novo) |

### Nota sobre DI

Todos os helpers devem ser injetados via construtor (DI), nunca acessados como singleton ou estático direto:

```php
// ✅ Correto
public function __construct(
    private readonly DPS_Phone_Helper $phoneHelper,
    private readonly DPS_Money_Helper $moneyHelper,
) {}

// ❌ Incorreto
DPS_Phone_Helper::normalize( $phone ); // Acesso estático
```

---

## Estratégia de Testes

### Abordagem

A Fase 7 introduz código novo significativo. Para garantir qualidade e evitar regressões, a estratégia de testes é:

### 1. Validação PHP (Obrigatória)

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

Cada feature do inventário legado (R1-R13, B1-B14) deve ter um teste funcional documentado:

| Teste | Passos | Resultado Esperado |
|-------|--------|-------------------|
| Registration V2 — Cadastro básico | Preencher todos os campos, submeter | Cliente + pet criados, success page exibida |
| Registration V2 — CPF inválido | Preencher CPF inválido, submeter | Erro de validação exibido, form preserva dados |
| Registration V2 — Telefone duplicado | Usar telefone existente | Aviso de duplicata exibido, bloqueio para não-admin |
| Registration V2 — reCAPTCHA | Submeter com reCAPTCHA habilitado | Score validado server-side, registro prossegue |
| Registration V2 — Email confirmation | Cadastrar novo cliente | Email de confirmação enviado com token 48h |
| Registration V2 — Loyalty bridge | Cadastrar com referral code | Hook `dps_registration_after_client_created` disparado, Loyalty processa |
| Booking V2 — Wizard completo | Navegar 5 steps, confirmar | Appointment criado com todas as metas |
| Booking V2 — TaxiDog | Marcar TaxiDog no step extras | Meta `appointment_taxidog` = 1 no appointment |
| Booking V2 — Tosa subscription | Selecionar subscription + tosa | Metas de tosa salvas corretamente |
| Booking V2 — Multi-pet | Selecionar 3+ pets com paginação | Todos os pets incluídos no appointment |
| Booking V2 — Hook bridge | Criar appointment via v2 | TODOS os 8 add-ons recebem `dps_base_after_save_appointment` |
| Booking V2 — Login required | Acessar booking sem login | Redireciona para login com return URL |

### 3. Testes de Integração (cross-addon)

```
┌─────────────────────────────────────────────────────┐
│ Cenário: Registration V2 + Loyalty                  │
│  1. Habilitar flag registration_v2                  │
│  2. Acessar [dps_registration_v2]                   │
│  3. Preencher form com referral code                │
│  4. Submeter                                        │
│  5. Verificar: Loyalty registrou referral ✓         │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Cenário: Booking V2 + Payment + Stock + Groomers    │
│  1. Habilitar flag booking_v2                       │
│  2. Completar wizard [dps_booking_v2]               │
│  3. Verificar:                                      │
│     - Payment: link gerado ✓                        │
│     - Stock: produtos reservados ✓                  │
│     - Groomers: tosador atribuído ✓                 │
│     - Calendar: evento sincronizado ✓               │
│     - Communications: notificação enviada ✓         │
│     - Push: push notification enviada ✓             │
│     - Services: snapshot salvo ✓                    │
└─────────────────────────────────────────────────────┘
```

### 4. Testes de Coexistência

- [ ] v1 e v2 na mesma página não conflitam (CSS, JS, IDs)
- [ ] Alternar flag v2 não afeta v1
- [ ] Desabilitar flag v2 remove shortcode v2 sem afetar v1
- [ ] Hooks bridge não duplicam ações quando v1 e v2 estão ambos ativos

### 5. Testes de Acessibilidade

- Validação via aXe DevTools ou WAVE
- Navegação por teclado completa (Tab, Enter, Escape)
- Leitura por screen reader (NVDA/VoiceOver)
- Contraste mínimo WCAG 2.1 AA (4.5:1 texto, 3:1 UI)
- `prefers-reduced-motion` respeitado

### 6. Benchmarks de Performance

| Métrica | Alvo Registration V2 | Alvo Booking V2 |
|---------|---------------------|-----------------|
| First render | < 2s | < 3s |
| Form submit | < 500ms | < 1s |
| Step transition | N/A | < 200ms |
| AJAX response | < 300ms | < 500ms |
| Total page weight (CSS+JS) | < 50KB | < 80KB |

Medição via `performance.mark()` / `performance.measure()` no JS e `microtime()` no PHP.

---

## Cronograma de Implementação

### Timeline Estimado

| Fase | Duração | Sprint | Descrição |
|------|---------|--------|-----------|
| **7.1 Preparação** | 2-3 semanas | 1-2 | Estrutura base, componentes, classes abstratas |
| **7.2 Registration V2** | 3-4 semanas | 3-5 | Implementação nativa completa cadastro |
| **7.3 Booking V2** | 5-6 semanas | 6-10 | Implementação nativa completa agendamento |
| **7.4 Coexistência** | 2 semanas | 11-12 | Docs migração, testes, ferramentas admin |
| **7.5 Depreciação** | 6+ meses | 13-18+ | Observação, comunicação, remoção legado |
| **TOTAL** | **4-5 meses** (código) + **6+ meses** (observação) | | |

### Marcos Principais

1. **M1 — Fundação Completa** (fim Sprint 2)
   - Estrutura criada
   - Componentes básicos prontos
   - Feature flags v2 implementadas

2. **M2 — Registration V2 Funcional** (fim Sprint 5)
   - Formulário nativo completo
   - Processamento independente
   - Hooks de integração OK
   - Rollback testado

3. **M3 — Booking V2 Funcional** (fim Sprint 10)
   - Wizard completo 5 steps
   - AJAX endpoints OK
   - Integrações críticas preservadas
   - Rollback testado

4. **M4 — Coexistência Estável** (fim Sprint 12)
   - v1 e v2 coexistem
   - Migração documentada
   - Telemetria v2 ativa

5. **M5 — Adoção Massiva** (6 meses após M4)
   - 80%+ migraram para v2
   - v1 usado < 5%
   - Aprovação para remoção legado

---

## Critérios de Aceite

### Critérios Globais (todas as fases)

✅ **Funcionalidade:**
- [ ] Zero quebra de funcionalidade existente (shortcodes v1 intactos)
- [ ] Rollback instantâneo via feature flags
- [ ] Compatibilidade retroativa de hooks (via Hook Bridge)
- [ ] Telemetria de uso implementada (v2 tracking separado)
- [ ] Coexistência v1/v2 funcional no mesmo site

✅ **Código:**
- [ ] PHP 8.4 moderno (typed properties, readonly, constructor promotion)
- [ ] Zero uso de singletons
- [ ] Dependency injection (todos os helpers via construtor)
- [ ] Sem jQuery (vanilla JS apenas)
- [ ] Reutilização de helpers globais do base (DPS_Phone_Helper, DPS_Money_Helper, etc.)
- [ ] Comentários PHPDoc completos
- [ ] Conformidade com AGENTS.md e PLAYBOOK.md
- [ ] Text domain consistente: `dps-frontend-addon`

✅ **Visual (M3 Expressive):**
- [ ] 100% design tokens CSS
- [ ] Zero hex/rgb hardcoded
- [ ] HTML semântico
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Motion expressivo opcional (`prefers-reduced-motion`)
- [ ] Tema escuro suportado
- [ ] Conformidade com `docs/visual/VISUAL_STYLE_GUIDE.md`

✅ **Performance:**
- [ ] Render < 2s (Registration)
- [ ] Render < 3s (Booking wizard)
- [ ] Submit < 500ms
- [ ] Transição steps < 200ms
- [ ] AJAX responses < 500ms
- [ ] Lazy load de assets
- [ ] Minificação CSS/JS
- [ ] Total page weight < 80KB (CSS+JS)

✅ **Segurança:**
- [ ] Nonces em todos os forms e AJAX endpoints
- [ ] Capability check (custom DPS capabilities + manage_options)
- [ ] Sanitização server-side (todos os inputs)
- [ ] Escape de output (esc_html, esc_attr, esc_url)
- [ ] Validação client-side + server-side
- [ ] CSRF protection
- [ ] XSS protection
- [ ] reCAPTCHA v3 (quando habilitado)
- [ ] Duplicate detection (phone-based)

✅ **Documentação:**
- [ ] Guia de uso atualizado (`docs/FRONTEND_ADDON_GUIA_USUARIO.md`)
- [ ] Exemplos de código para cada shortcode v2
- [ ] Migration guide v1 → v2 (passo a passo)
- [ ] Troubleshooting atualizado
- [ ] CHANGELOG.md atualizado
- [ ] ANALYSIS.md atualizado com hooks v2

### Critérios Específicos — Registration V2

✅ **Funcional (paridade com R1-R13):**
- [ ] Renderiza form nativo (zero legado)
- [ ] Valida campos obrigatórios (nome, email, telefone)
- [ ] Valida CPF com algoritmo Mod-11 (se preenchido)
- [ ] Normaliza telefone via DPS_Phone_Helper
- [ ] Detecta duplicata por telefone (bloqueia não-admin)
- [ ] Cria cliente corretamente (wp_insert_post + metas)
- [ ] Cria 1+ pets corretamente (repeater funcional)
- [ ] Dataset de raças por espécie (datalist)
- [ ] reCAPTCHA v3 funcional (quando habilitado)
- [ ] Confirmação de email 48h (token + cron lembretes)
- [ ] Google Maps autocomplete (quando API key presente)
- [ ] Anti-spam filter aplicado
- [ ] Marketing opt-in checkbox
- [ ] Envia email de boas-vindas
- [ ] Redireciona pós-sucesso (configurável via atributo)
- [ ] Exibe erros de validação (preserva dados do form)
- [ ] Mantém dados em caso de erro (sticky form)

✅ **Integração (via Hook Bridge):**
- [ ] Dispara hooks nativos (`dps_registration_v2_*`)
- [ ] Bridge: `dps_registration_after_client_created` (4 args — Loyalty)
- [ ] Bridge: `dps_registration_after_fields` (Loyalty UI)
- [ ] Bridge: `dps_registration_spam_check` (filter)
- [ ] Loyalty add-on funciona (referral code processado)
- [ ] Communications add-on funciona (email enviado)

### Critérios Específicos — Booking V2

✅ **Funcional (paridade com B1-B14):**
- [ ] Wizard 5 steps + extras condicionais funcional
- [ ] 3 tipos de agendamento: simple, subscription, past
- [ ] State management robusto (sessão + URL query param)
- [ ] Login obrigatório (redireciona se não logado)
- [ ] Cache control desabilitado na página
- [ ] Busca cliente por telefone (AJAX)
- [ ] Seleção múltipla de pets com paginação
- [ ] Lista serviços com preços
- [ ] Calendário de disponibilidade com validação de conflitos
- [ ] TaxiDog: checkbox + preço
- [ ] Tosa (subscription only): checkbox + preço + ocorrência
- [ ] Confirmação via transient (5min TTL)
- [ ] Edição/duplicação de agendamentos
- [ ] Cria appointment com TODAS as metas
- [ ] Envia email confirmação
- [ ] Skip REST/AJAX requests (retorna vazio)
- [ ] Capabilities check: manage_options OU dps_manage_*

✅ **Integração (CRÍTICO — 8 add-ons via Hook Bridge):**
- [ ] Bridge: `dps_base_after_save_appointment` (8 consumidores)
- [ ] Bridge: `dps_base_appointment_fields` (Services)
- [ ] Bridge: `dps_base_appointment_assignment_fields` (Groomers)
- [ ] Stock (consumo de produtos confirmado)
- [ ] Payment (link de pagamento gerado)
- [ ] Groomers (atribuição de tosador)
- [ ] Calendar (sincronização Google Calendar)
- [ ] Communications (notificações email/WhatsApp)
- [ ] Push (notificações push)
- [ ] Services (snapshot de valores)

---

## Riscos e Mitigação

### Riscos Identificados

#### 1. **Complexidade Alta**
**Risco:** Implementação nativa é significativamente mais complexa que wrapper.

**Mitigação:**
- Dividir em fases pequenas e incrementais
- Criar protótipos antes de implementação completa
- Code review rigoroso em cada PR
- Testes automatizados desde o início

#### 2. **Quebra de Integrações**
**Risco:** Add-ons que dependem de hooks legados podem quebrar.

**Mitigação:**
- Manter hooks legados via bridge durante Fase 7.4
- Testar todos os 18 add-ons em cada fase
- Matriz de compatibilidade atualizada continuamente
- Rollback instantâneo sempre disponível

#### 3. **Adoção Lenta**
**Risco:** Usuários podem resistir a migrar para v2.

**Mitigação:**
- Documentação de migração clara e passo a passo
- Benefícios de v2 claramente comunicados
- Ferramentas admin para facilitar toggle
- Suporte dedicado durante migração
- Incentivos para early adopters

#### 4. **Performance Pior que Esperado**
**Risco:** Implementação nativa pode ser mais lenta que legado otimizado.

**Mitigação:**
- Benchmarks desde Fase 7.1
- Otimização contínua em cada fase
- Lazy loading agressivo
- Code splitting
- Caching inteligente
- Profiling de performance

#### 5. **Scope Creep**
**Risco:** Tentação de adicionar features não planejadas.

**Mitigação:**
- Roadmap rígido e acordado
- Definition of Done clara
- PR reviews focados em scope
- Features extras = backlog separado
- Foco em paridade funcional primeiro

#### 6. **Tempo de Desenvolvimento**
**Risco:** 4-5 meses é estimativa otimista.

**Mitigação:**
- Buffer de 20% no cronograma
- Revisões semanais de progresso
- Ajustes de scope se necessário
- Comunicação transparente de atrasos
- Priorização clara (Registration > Booking)

#### 7. **Paridade Funcional Incompleta**
**Risco:** V2 pode ir para produção sem implementar features legadas que alguns clientes usam (ex.: reCAPTCHA, TaxiDog, tosa, admin quick-registration, email confirmation).

**Mitigação:**
- Inventário completo de features legadas documentado neste plano (R1-R13, B1-B14)
- Checklist de paridade funcional em cada Fase (7.2 e 7.3)
- Testes funcionais feature-a-feature antes de liberar flag v2
- Features P2 (desejáveis) podem ser adiadas, mas features P0 e P1 são obrigatórias antes de liberar v2 para produção
- Documentar explicitamente qualquer feature legada NÃO implementada no v2 e o motivo

#### 8. **Conflito de CSS/JS entre v1 e v2**
**Risco:** Quando v1 e v2 coexistem na mesma página (cenário side-by-side), CSS e JS podem conflitar.

**Mitigação:**
- Namespacing CSS rigoroso: v2 usa classes `.dps-v2-*`, v1 mantém `.dps-frontend`
- IDs únicos: v2 usa prefixo `dps-v2-` em todos os IDs de elementos
- JS scoped: v2 JS opera apenas dentro de containers `.dps-v2-*`
- Assets carregados condicionalmente (apenas quando shortcode v2 presente na página)
- Teste de coexistência obrigatório na Fase 7.4

---

## Próximos Passos Imediatos

### Ações Recomendadas (Next Sprint)

1. **Aprovação Formal**
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
   - [ ] Criar estrutura de diretórios
   - [ ] Implementar classes base abstratas
   - [ ] Criar primeiros componentes M3
   - [ ] Documentar padrões de código

4. **Comunicação**
   - [ ] Anunciar Fase 7 para equipe
   - [ ] Atualizar CHANGELOG.md
   - [ ] Criar issue tracker no GitHub
   - [ ] Setup de daily standups

---

## Conclusão

A **Fase 7** representa a **evolução definitiva** do Frontend Add-on:

**De:** Wrappers que reutilizam código legado  
**Para:** Implementações nativas 100% modernas e alinhadas ao Material 3 Expressive

**Benefícios esperados:**
- ✨ UX/UI completamente redesenhada do zero
- ✨ Performance superior
- ✨ Código limpo e testável
- ✨ Independência total dos add-ons legados
- ✨ Flexibilidade para evoluções futuras
- ✨ Acessibilidade nativa WCAG 2.1 AA
- ✨ Pride na qualidade do código

**Compromissos:**
- ✅ Migração gradual e segura (4-5 meses código + 6 meses observação)
- ✅ Rollback sempre disponível
- ✅ Zero quebra de compatibilidade durante coexistência
- ✅ Documentação completa em todas as fases

Este plano estabelece as bases para que o Frontend Add-on atinja seu **potencial completo**, tornando o DPS um sistema verdadeiramente moderno em todos os aspectos: arquitetura, código, design e experiência do usuário.

---

**Versão:** 1.1.0  
**Status:** 📋 Aguardando Aprovação  
**Próximo Milestone:** Fase 7.1 — Preparação (Sprint 1-2)  
**Data prevista início:** A definir após aprovação  
**Revisão:** v1.1.0 — Refinamento com inventário completo de funcionalidades legadas, hook bridge detalhada, estratégia de coexistência, testes e helpers (2026-02-12)

---

**Documentos Relacionados:**
- `FRONTEND_ADDON_PHASED_ROADMAP.md` — Fases 1-6 (concluídas)
- `FRONTEND_DEPRECATION_POLICY.md` — Política de 180 dias
- `FRONTEND_REMOVAL_TARGETS.md` — Alvos de remoção
- `AGENT_ENGINEERING_PLAYBOOK.md` — Padrões de código
- `VISUAL_STYLE_GUIDE.md` — Design tokens M3
- `FRONTEND_DESIGN_INSTRUCTIONS.md` — Metodologia M3

**Aprovação necessária de:**
- [ ] Product Owner
- [ ] Tech Lead
- [ ] Design Lead
- [ ] DevOps Lead
