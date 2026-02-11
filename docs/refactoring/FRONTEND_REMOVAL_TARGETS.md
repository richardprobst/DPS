# Alvos de Remoção — Frontend Add-on

> **Versão**: 1.5.0 (Fase 6)
> **Última atualização**: 2026-02-11
> **Status**: 📋 Inventário (nenhuma remoção nesta etapa)

## 1. Objetivo

Lista os add-ons legados candidatos a remoção futura, com análise de risco, dependências, esforço estimado e plano de reversão por alvo.

---

## 2. Alvos

### 2.1 `desi-pet-shower-registration`

**Descrição**: Add-on de cadastro público de clientes e pets.

**Módulo frontend substituto**: Registration (Fase 2, dual-run operacional).

**Risco geral**: 🟡 Médio

#### Dependências diretas (referências ao `DPS_Registration_Addon`)

| Arquivo | Linha | Tipo | Contexto |
|---------|-------|------|----------|
| `base/includes/class-dps-settings-frontend.php` | 178 | `class_exists` | Condicional para exibir aba de configurações de cadastro |
| `base/includes/class-dps-settings-frontend.php` | 1784 | `class_exists` | Guard da aba de cadastro |
| `base/includes/class-dps-tools-hub.php` | 70, 95-96 | `class_exists` + `get_instance` | Tools Hub usa para operações de limpeza/migração |
| `base/includes/class-dps-addon-manager.php` | 200 | Registro | Registro no addon-manager (slot de UI) |
| `base/includes/class-dps-shortcodes-admin-page.php` | 367 | `class_exists` | Verifica se shortcode está disponível |

#### Dependências de hooks (consumidores fora do registration)

| Hook | Consumidor | Arquivo | Risco |
|------|-----------|---------|-------|
| `dps_registration_after_fields` | Loyalty (render_registration_field) | `loyalty/desi-pet-shower-loyalty.php:2597` | 🔴 Alto |
| `dps_registration_after_client_created` | Loyalty (maybe_register_referral, 4 args) | `loyalty/desi-pet-shower-loyalty.php:2598` | 🔴 Alto |

#### Dependências de options (lidas fora do registration)

| Option | Leitura em | Contexto |
|--------|-----------|----------|
| `dps_registration_page_id` | `base/class-dps-settings-frontend.php` | Aba de configurações |
| `dps_registration_recaptcha_*` | `base/class-dps-settings-frontend.php` | Settings de reCAPTCHA |
| `dps_registration_api_*` | `base/class-dps-settings-frontend.php` | Settings de API rate |
| `dps_registration_confirm_email_*` | `base/class-dps-settings-frontend.php` | Settings de email |

#### Plano de reversão

1. Restaurar plugin do tag `pre-removal-registration-v{versão}`.
2. Reativar no WordPress: `wp plugin activate desi-pet-shower-registration`.
3. Desabilitar flag `registration` no frontend add-on.
4. Verificar shortcode funcional: `wp eval "var_dump(shortcode_exists('dps_registration_form'));"`.

#### Esforço estimado para remoção

| Tarefa | Complexidade |
|--------|-------------|
| Remover diretório do add-on | Trivial |
| Atualizar addon-manager (remover registro) | Trivial |
| Migrar aba de settings de cadastro para módulo frontend | Média |
| Migrar processamento de formulário para módulo frontend | Alta |
| Migrar hooks (dps_registration_after_fields, etc.) | Alta |
| Atualizar Tools Hub (remover referências) | Baixa |
| Atualizar shortcodes admin page | Baixa |
| Migrar emails e cron | Média |
| Testes de regressão completos | Alta |

**Esforço total estimado**: Alto (múltiplas sessões de trabalho).

---

### 2.2 `desi-pet-shower-booking`

**Descrição**: Add-on de agendamento público.

**Módulo frontend substituto**: Booking (Fase 3, dual-run operacional).

**Risco geral**: 🟢 Baixo

#### Dependências diretas (referências ao `DPS_Booking_Addon`)

| Arquivo | Linha | Tipo | Contexto |
|---------|-------|------|----------|
| — | — | — | **Nenhuma referência direta fora do próprio add-on e do frontend** |

#### Dependências de hooks (consumidores fora do booking)

| Hook | Consumidor | Arquivo | Risco |
|------|-----------|---------|-------|
| `dps_base_after_save_appointment` | Stock | `stock/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Payment | `payment/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Groomers | `groomers/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Calendar | `calendar/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Communications | `communications/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Push | `push/*.php` | 🟡 Médio |
| `dps_base_after_save_appointment` | Services | `services/*.php` | 🟡 Médio |

> **Nota**: `dps_base_after_save_appointment` é disparado pelo **plugin base** (`DPS_Base_Frontend`), não pelo booking add-on. A remoção do booking **não afeta** este hook.

#### Dependências de options (lidas fora do booking)

| Option | Leitura em | Contexto |
|--------|-----------|----------|
| `dps_booking_page_id` | Frontend add-on (enqueue condicional) | Detecção de página |

#### Plano de reversão

1. Restaurar plugin do tag `pre-removal-booking-v{versão}`.
2. Reativar no WordPress: `wp plugin activate desi-pet-shower-booking`.
3. Desabilitar flag `booking` no frontend add-on.
4. Verificar shortcode funcional: `wp eval "var_dump(shortcode_exists('dps_booking_form'));"`.

#### Esforço estimado para remoção

| Tarefa | Complexidade |
|--------|-------------|
| Remover diretório do add-on | Trivial |
| Atualizar addon-manager (remover registro) | Trivial |
| Migrar renderização do formulário para módulo frontend | Média |
| Migrar processamento do agendamento para módulo frontend | Média |
| Migrar tela de confirmação | Baixa |
| Migrar capture_saved_appointment | Baixa |
| Testes de regressão completos | Média |

**Esforço total estimado**: Médio (1-2 sessões de trabalho).

---

## 3. Prioridade de remoção recomendada

| Ordem | Add-on | Risco | Esforço | Justificativa |
|-------|--------|-------|---------|---------------|
| 1° | `desi-pet-shower-booking` | 🟢 Baixo | Médio | Zero referências diretas fora do add-on; hook principal vem do base |
| 2° | `desi-pet-shower-registration` | 🟡 Médio | Alto | Múltiplas referências no base; hooks consumidos pelo Loyalty; aba de settings complexa |

---

## 4. Pré-requisitos transversais

Antes de remover **qualquer** alvo:

1. ✅ Política de depreciação publicada (`docs/refactoring/FRONTEND_DEPRECATION_POLICY.md`)
2. ✅ Matriz de compatibilidade validada (`docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md`)
3. ✅ Runbook de rollback documentado (`docs/implementation/FRONTEND_RUNBOOK.md`)
4. ✅ Checklist de prontidão por módulo (`docs/qa/FRONTEND_REMOVAL_READINESS.md`)
5. ⬜ Telemetria de uso implementada e operacional
6. ⬜ Módulo frontend operando em produção por ≥ 90 dias
7. ⬜ Aviso de depreciação publicado há ≥ 60 dias

---

## 5. Decisão atual

**Nenhuma remoção será feita nesta etapa.** Este inventário existe para:
- Documentar os alvos com análise de risco completa.
- Guiar planejamento futuro de remoção.
- Identificar dependências que precisam ser resolvidas antes da remoção.
