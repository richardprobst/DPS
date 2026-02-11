# Plano de Implementação Nativa — Frontend Add-on (Fase 7)

> **Versão**: 1.0.0  
> **Data**: 2026-02-11  
> **Autor**: PRObst  
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## 📋 Índice

1. [Contexto e Motivação](#contexto-e-motivação)
2. [Situação Atual (Fases 1-6)](#situação-atual-fases-1-6)
3. [Objetivo da Fase 7](#objetivo-da-fase-7)
4. [Arquitetura Proposta](#arquitetura-proposta)
5. [Estratégia de Migração](#estratégia-de-migração)
6. [Novos Shortcodes Nativos](#novos-shortcodes-nativos)
7. [Estrutura de Templates](#estrutura-de-templates)
8. [Cronograma de Implementação](#cronograma-de-implementação)
9. [Critérios de Aceite](#critérios-de-aceite)
10. [Riscos e Mitigação](#riscos-e-mitigação)

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
│   └── class-dps-appointment-service.php      ← CRUD de agendamentos
└── ajax/
    ├── class-dps-ajax-client-search.php       ← Busca cliente por telefone
    ├── class-dps-ajax-pet-list.php            ← Lista pets do cliente
    └── class-dps-ajax-available-slots.php     ← Horários disponíveis
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

## Estratégia de Migração

### Fase 7.1 — Preparação (Sprint 1-2)

**Objetivo:** Estruturar arquitetura sem quebrar nada

✅ **Tarefas:**
1. Criar estrutura de diretórios (`templates/`, `handlers/`, `services/`, `ajax/`)
2. Implementar classes base abstratas:
   - `Abstract_Module_V2` — base para módulos nativos
   - `Abstract_Handler` — base para handlers
   - `Abstract_Service` — base para services
   - `Abstract_Validator` — base para validadores
3. Criar sistema de template engine simples
4. Implementar componentes reutilizáveis básicos (button, field, card, alert)
5. Documentar padrões de código e convenções

✅ **Feature Flags:**
- Criar nova flag `registration_v2` (desabilitada por padrão)
- Criar nova flag `booking_v2` (desabilitada por padrão)
- Manter flags antigas (`registration`, `booking`) funcionando

✅ **Critérios de Aceite:**
- [ ] Estrutura de diretórios criada
- [ ] Classes base implementadas
- [ ] Template engine funcional
- [ ] 5+ componentes reutilizáveis prontos
- [ ] Feature flags novas criadas
- [ ] Zero quebra de funcionalidade existente

### Fase 7.2 — Registration V2 (Sprint 3-5)

**Objetivo:** Implementação nativa completa do cadastro

✅ **Tarefas:**
1. **Templates Registration:**
   - `form-main.php` — estrutura principal
   - `form-client-data.php` — campos do cliente
   - `form-pet-data.php` — campos do pet (repeater)
   - `form-success.php` — sucesso
   - `form-error.php` — erro

2. **Handler e Services:**
   - `DPS_Registration_Handler` — processa formulário
   - `DPS_Client_Service` — CRUD de clientes
   - `DPS_Pet_Service` — CRUD de pets
   - `DPS_Form_Validator` — validações

3. **Assets Nativos:**
   - `registration-v2.css` — estilos M3 puros
   - `registration-v2.js` — comportamento nativo
   - Integração com design tokens

4. **Módulo V2:**
   - `DPS_Frontend_Registration_V2_Module`
   - Shortcode `[dps_registration_v2]`
   - Zero dependência do legado

5. **Hooks Novos:**
   - `dps_registration_v2_before_render` — antes de renderizar form
   - `dps_registration_v2_after_render` — depois de renderizar form
   - `dps_registration_v2_before_process` — antes de processar
   - `dps_registration_v2_after_process` — depois de processar
   - `dps_registration_v2_client_created` — cliente criado
   - `dps_registration_v2_pet_created` — pet criado
   - **Bridge:** manter hooks antigos para compatibilidade

6. **Validação e Testes:**
   - Testes funcionais completos
   - Validação WCAG 2.1 AA
   - Performance benchmark
   - Teste em mobile/tablet/desktop

✅ **Critérios de Aceite:**
- [ ] Formulário renderiza 100% nativo (HTML M3)
- [ ] Processa cadastro sem chamar legado
- [ ] Cria cliente e pet corretamente
- [ ] Valida todos os campos (client-side + server-side)
- [ ] Dispara hooks de integração (Loyalty, etc.)
- [ ] CSS 100% design tokens M3
- [ ] JavaScript vanilla (zero jQuery)
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Performance < 2s render, < 500ms submit
- [ ] Rollback instantâneo (flag `registration_v2`)

### Fase 7.3 — Booking V2 (Sprint 6-10)

**Objetivo:** Implementação nativa completa do agendamento

✅ **Tarefas:**
1. **Templates Booking (Multi-step):**
   - `form-main.php` — wizard container
   - `step-client-selection.php` — busca/seleção cliente
   - `step-pet-selection.php` — seleção de pets
   - `step-service-selection.php` — escolha de serviços
   - `step-datetime-selection.php` — data/hora
   - `step-confirmation.php` — revisão final
   - `form-success.php` — confirmação

2. **Handler e Services:**
   - `DPS_Booking_Handler` — processa wizard
   - `DPS_Appointment_Service` — CRUD de agendamentos
   - `DPS_Service_Availability_Service` — horários disponíveis
   - `DPS_Booking_Validator` — validações complexas

3. **AJAX Endpoints:**
   - `wp_ajax_dps_search_client` — busca cliente
   - `wp_ajax_dps_get_pets` — lista pets
   - `wp_ajax_dps_get_services` — serviços disponíveis
   - `wp_ajax_dps_get_slots` — horários livres
   - `wp_ajax_dps_validate_step` — valida step atual

4. **Assets Nativos:**
   - `booking-v2.css` — estilos M3 wizard
   - `booking-v2.js` — wizard state machine
   - Animações de transição entre steps

5. **Módulo V2:**
   - `DPS_Frontend_Booking_V2_Module`
   - Shortcode `[dps_booking_v2]`
   - State management para wizard

6. **Hooks Novos:**
   - `dps_booking_v2_before_render` — antes de renderizar
   - `dps_booking_v2_step_render` — ao renderizar step
   - `dps_booking_v2_step_validate` — validação de step
   - `dps_booking_v2_before_process` — antes de criar appointment
   - `dps_booking_v2_after_process` — depois de criar
   - `dps_booking_v2_appointment_created` — appointment criado
   - **Bridge:** manter `dps_base_after_save_appointment` (crítico — 7+ add-ons)

7. **Integrações Críticas:**
   - Stock (consumo de produtos)
   - Payment (link de pagamento)
   - Groomers (atribuição de tosador)
   - Calendar (sincronização)
   - Communications (notificações)
   - Push (notificações push)
   - Services (snapshot de valores)

✅ **Critérios de Aceite:**
- [ ] Wizard funciona com 5 steps
- [ ] State management robusto (sessão + URL)
- [ ] AJAX endpoints funcionais e seguros
- [ ] Busca de cliente por telefone OK
- [ ] Seleção múltipla de pets OK
- [ ] Calendário de disponibilidade OK
- [ ] Confirmação de agendamento OK
- [ ] Cria appointment corretamente
- [ ] Dispara **TODOS** os hooks críticos (7+ add-ons)
- [ ] Email de confirmação enviado
- [ ] CSS 100% M3 (wizard expressivo)
- [ ] Animações de transição suaves
- [ ] Validação robusta (client + server)
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Performance < 3s render, < 1s transição
- [ ] Funciona em mobile (touch-friendly)
- [ ] Rollback instantâneo (flag `booking_v2`)

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

**Exemplos:**
```
[dps_booking_v2]
[dps_booking_v2 client_id="123"]
[dps_booking_v2 service_id="456" start_step="3"]
[dps_booking_v2 show_progress="true" theme="light"]
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
│   ├── form-client-data.php          ← Seção cliente
│   ├── form-pet-data.php             ← Seção pet (repeater)
│   ├── form-success.php              ← Sucesso
│   └── form-error.php                ← Erro
├── booking/
│   ├── form-main.php                 ← Wizard container
│   ├── step-client-selection.php     ← Step 1: Cliente
│   ├── step-pet-selection.php        ← Step 2: Pet
│   ├── step-service-selection.php    ← Step 3: Serviço
│   ├── step-datetime-selection.php   ← Step 4: Data/Hora
│   ├── step-confirmation.php         ← Step 5: Confirmação
│   └── form-success.php              ← Sucesso
└── components/
    ├── field-text.php                ← Input text M3
    ├── field-email.php               ← Input email M3
    ├── field-phone.php               ← Input phone M3
    ├── field-select.php              ← Select M3
    ├── field-textarea.php            ← Textarea M3
    ├── field-checkbox.php            ← Checkbox M3
    ├── button-primary.php            ← Botão primário M3
    ├── button-secondary.php          ← Botão secundário M3
    ├── button-text.php               ← Botão texto M3
    ├── card.php                      ← Card M3
    ├── alert.php                     ← Alert M3
    ├── loader.php                    ← Loader M3
    ├── progress-bar.php              ← Barra de progresso
    └── wizard-steps.php              ← Indicador de steps
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
- [ ] Zero quebra de funcionalidade existente
- [ ] Rollback instantâneo via feature flags
- [ ] Compatibilidade retroativa de hooks
- [ ] Telemetria de uso implementada

✅ **Código:**
- [ ] PHP 8.4 moderno (typed properties, readonly, etc.)
- [ ] Zero uso de singletons
- [ ] Dependency injection
- [ ] Sem jQuery (vanilla JS apenas)
- [ ] Comentários PHPDoc completos
- [ ] Conformidade com AGENTS.md e PLAYBOOK.md

✅ **Visual (M3 Expressive):**
- [ ] 100% design tokens CSS
- [ ] Zero hex/rgb hardcoded
- [ ] HTML semântico
- [ ] Acessibilidade WCAG 2.1 AA
- [ ] Motion expressivo opcional (`prefers-reduced-motion`)
- [ ] Tema escuro suportado

✅ **Performance:**
- [ ] Render < 2s (Registration)
- [ ] Render < 3s (Booking wizard)
- [ ] Submit < 500ms
- [ ] Transição steps < 200ms
- [ ] Lazy load de assets
- [ ] Minificação CSS/JS

✅ **Segurança:**
- [ ] Nonces em todos os forms
- [ ] Capability check (`manage_options` admin, user logged para portal)
- [ ] Sanitização server-side
- [ ] Escape de output
- [ ] Validação client-side + server-side
- [ ] CSRF protection
- [ ] XSS protection

✅ **Documentação:**
- [ ] Guia de uso atualizado
- [ ] Exemplos de código
- [ ] Migration guide v1 → v2
- [ ] Troubleshooting atualizado
- [ ] CHANGELOG.md atualizado

### Critérios Específicos — Registration V2

✅ **Funcional:**
- [ ] Renderiza form nativo (zero legado)
- [ ] Valida campos obrigatórios
- [ ] Cria cliente corretamente
- [ ] Cria 1+ pets corretamente
- [ ] Envia email de boas-vindas
- [ ] Redireciona pós-sucesso
- [ ] Exibe erros de validação
- [ ] Mantém dados em caso de erro

✅ **Integração:**
- [ ] Dispara hooks nativos (`dps_registration_v2_*`)
- [ ] Mantém hooks legados via bridge
- [ ] Loyalty add-on funciona (referral)
- [ ] Communications add-on funciona (email)

### Critérios Específicos — Booking V2

✅ **Funcional:**
- [ ] Wizard 5 steps funcional
- [ ] State management robusto
- [ ] Busca cliente por telefone
- [ ] Lista pets do cliente
- [ ] Exibe serviços disponíveis
- [ ] Calendário de disponibilidade
- [ ] Validação de conflitos
- [ ] Cria appointment corretamente
- [ ] Envia email confirmação
- [ ] Redireciona pós-sucesso

✅ **Integração (CRÍTICO — 7+ add-ons):**
- [ ] Stock (consumo de produtos)
- [ ] Payment (link de pagamento)
- [ ] Groomers (atribuição)
- [ ] Calendar (sincronização)
- [ ] Communications (notificações)
- [ ] Push (notificações push)
- [ ] Services (snapshot valores)

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

**Versão:** 1.0.0  
**Status:** 📋 Aguardando Aprovação  
**Próximo Milestone:** Fase 7.1 — Preparação (Sprint 1-2)  
**Data prevista início:** A definir após aprovação

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
