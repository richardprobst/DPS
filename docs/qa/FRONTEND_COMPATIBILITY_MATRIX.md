# Matriz de Compatibilidade — Frontend Add-on

> **Versão**: 1.3.0 (Fase 5)
> **Última atualização**: 2026-02-11

## 1. Visão geral

Esta matriz documenta a compatibilidade do add-on `desi-pet-shower-frontend` com o plugin base e todos os add-ons do sistema DPS.

### Legenda de status

| Status | Significado |
|--------|-------------|
| ✅ Compatível | Testado e funcional sem impacto |
| ⚠️ Integração | Possui hooks/contratos que o módulo preserva |
| 🔄 Dual-run | Módulo opera sobre o add-on legado (wrapper) |
| ➖ Sem interação | Nenhuma integração direta |

---

## 2. Compatibilidade por add-on

| Add-on | Status | Módulo afetado | Detalhes |
|--------|--------|----------------|----------|
| **base** | ✅ Compatível | Todos | Dependência direta (DPS_Base_Plugin, design tokens, DPS_Settings_Frontend) |
| **registration** | 🔄 Dual-run | Registration | Shortcode assumido; legado processa forms, emails, REST, AJAX, cron |
| **booking** | 🔄 Dual-run | Booking | Shortcode assumido; legado processa forms, confirmação, captura |
| **loyalty** | ⚠️ Integração | Registration | Hooks preservados: `dps_registration_after_fields`, `dps_registration_after_client_created` |
| **agenda** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados |
| **ai** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados |
| **backup** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados |
| **client-portal** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados |
| **communications** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **finance** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados diretos |
| **groomers** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **payment** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **push** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **services** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **stats** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados |
| **stock** | ⚠️ Integração | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **subscription** | ➖ Sem interação | — | Sem shortcodes ou hooks compartilhados diretos |

---

## 3. Contratos de shortcodes

| Shortcode | Origem legada | Módulo Frontend | Estratégia | Status |
|-----------|---------------|-----------------|------------|--------|
| `dps_registration_form` | `desi-pet-shower-registration` | Registration | Dual-run: `remove_shortcode` + wrapper DPS Signature | ✅ |
| `dps_booking_form` | `desi-pet-shower-booking` | Booking | Dual-run: `remove_shortcode` + wrapper DPS Signature | ✅ |

---

## 4. Contratos de hooks preservados

### 4.1 Hooks do módulo Registration

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_registration_after_fields` | action | Loyalty (render_registration_field) | Alto | ✅ Sim — disparado pelo legado |
| `dps_registration_after_client_created` | action | Loyalty (maybe_register_referral, 4 args) | Alto | ✅ Sim — disparado pelo legado |
| `dps_registration_spam_check` | filter | Registration (reCAPTCHA) | Médio | ✅ Sim — disparado pelo legado |
| `dps_registration_agenda_url` | filter | Registration | Baixo | ✅ Sim — disparado pelo legado |

### 4.2 Hooks do módulo Booking

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_base_after_save_appointment` | action | stock, payment, groomers, calendar, communications, push, services, booking | Crítico | ✅ Sim — disparado pelo legado |
| `dps_base_appointment_fields` | action | Booking (campos customizados) | Médio | ✅ Sim — disparado pelo legado |
| `dps_base_appointment_assignment_fields` | action | Booking (campos de atribuição) | Médio | ✅ Sim — disparado pelo legado |

### 4.3 Hooks do módulo Settings

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_settings_register_tabs` | action | Frontend Settings Module | Baixo | ✅ Usado para registrar aba |
| `dps_settings_save_save_frontend` | action | Frontend Settings Module | Baixo | ✅ Usado para salvar flags |

---

## 5. Contratos de options/dados

| Option | Tipo | Módulo | Uso |
|--------|------|--------|-----|
| `dps_frontend_feature_flags` | array (JSON) | Todos | Feature flags por módulo |
| `dps_registration_page_id` | int | Registration | Leitura-only (detectar página do shortcode) |
| `dps_booking_page_id` | int | Booking | Leitura-only (detectar página do shortcode) |

> **Nota**: O frontend add-on **não cria nem altera** options dos legados. Apenas lê `dps_registration_page_id` e `dps_booking_page_id` para enqueue condicional de CSS.

---

## 6. Impacto da desativação por módulo

| Módulo desativado | Efeito | Quem assume | Impacto em outros add-ons |
|-------------------|--------|-------------|---------------------------|
| Registration | Legado reassume shortcode | `DPS_Registration_Addon` | Nenhum |
| Booking | Legado reassume shortcode | `DPS_Booking_Addon` | Nenhum |
| Settings | Aba desaparece | — | Nenhum (flags persistidos) |
| Plugin inteiro | Todos os legados reassumem | Respectivos add-ons | Nenhum |

---

## 7. Requisitos mínimos verificados

| Requisito | Versão mínima | Verificado |
|-----------|---------------|------------|
| WordPress | 6.9+ | ✅ |
| PHP | 8.4+ | ✅ |
| Plugin base | Ativo com DPS_Base_Plugin | ✅ |
| Design tokens | dps-design-tokens.css disponível | ✅ |
