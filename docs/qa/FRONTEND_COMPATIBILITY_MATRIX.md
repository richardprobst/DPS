# Matriz de Compatibilidade â€” Frontend Add-on

> **VersÃ£o**: 1.3.0 (Fase 5)
> **Ãšltima atualizaÃ§Ã£o**: 2026-02-11

## 1. VisÃ£o geral

Esta matriz documenta a compatibilidade do add-on `desi-pet-shower-frontend` com o plugin base e todos os add-ons do sistema DPS.

### Legenda de status

| Status | Significado |
|--------|-------------|
| âœ… CompatÃ­vel | Testado e funcional sem impacto |
| âš ï¸ IntegraÃ§Ã£o | Possui hooks/contratos que o mÃ³dulo preserva |
| ðŸ”„ Dual-run | MÃ³dulo opera sobre o add-on legado (wrapper) |
| âž– Sem interaÃ§Ã£o | Nenhuma integraÃ§Ã£o direta |

---

## 2. Compatibilidade por add-on

| Add-on | Status | MÃ³dulo afetado | Detalhes |
|--------|--------|----------------|----------|
| **base** | âœ… CompatÃ­vel | Todos | DependÃªncia direta (DPS_Base_Plugin, design tokens, DPS_Settings_Frontend) |
| **registration** | ðŸ”„ Dual-run | Registration | Shortcode assumido; legado processa forms, emails, REST, AJAX, cron |
| **booking** | ðŸ”„ Dual-run | Booking | Shortcode assumido; legado processa forms, confirmaÃ§Ã£o, captura |
| **loyalty** | âš ï¸ IntegraÃ§Ã£o | Registration | Hooks preservados: `dps_registration_after_fields`, `dps_registration_after_client_created` |
| **agenda** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados |
| **ai** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados |
| **backup** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados |
| **client-portal** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados |
| **communications** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **finance** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados diretos |
| **groomers** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **payment** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **push** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **services** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **stats** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados |
| **stock** | âš ï¸ IntegraÃ§Ã£o | Booking | Hook preservado: `dps_base_after_save_appointment` (disparado pelo legado) |
| **subscription** | âž– Sem interaÃ§Ã£o | â€” | Sem shortcodes ou hooks compartilhados diretos |

---

## 3. Contratos de shortcodes

| Shortcode | Origem legada | MÃ³dulo Frontend | EstratÃ©gia | Status |
|-----------|---------------|-----------------|------------|--------|
| `dps_registration_form` | `desi-pet-shower-registration` | Registration | Dual-run: `remove_shortcode` + wrapper DPS Signature | âœ… |
| `dps_booking_form` | `desi-pet-shower-booking` | Booking | Dual-run: `remove_shortcode` + wrapper DPS Signature | âœ… |

---

## 4. Contratos de hooks preservados

### 4.1 Hooks do mÃ³dulo Registration

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_registration_after_fields` | action | Loyalty (render_registration_field) | Alto | âœ… Sim â€” disparado pelo legado |
| `dps_registration_after_client_created` | action | Loyalty (maybe_register_referral, 4 args) | Alto | âœ… Sim â€” disparado pelo legado |
| `dps_registration_spam_check` | filter | Registration (reCAPTCHA) | MÃ©dio | âœ… Sim â€” disparado pelo legado |
| `dps_registration_agenda_url` | filter | Registration | Baixo | âœ… Sim â€” disparado pelo legado |

### 4.2 Hooks do mÃ³dulo Booking

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_base_after_save_appointment` | action | stock, payment, groomers, calendar, communications, push, services, booking | CrÃ­tico | âœ… Sim â€” disparado pelo legado |
| `dps_base_appointment_fields` | action | Booking (campos customizados) | MÃ©dio | âœ… Sim â€” disparado pelo legado |
| `dps_base_appointment_assignment_fields` | action | Booking (campos de atribuiÃ§Ã£o) | MÃ©dio | âœ… Sim â€” disparado pelo legado |

### 4.3 Hooks do mÃ³dulo Settings

| Hook | Tipo | Consumidores | Risco | Preservado |
|------|------|--------------|-------|------------|
| `dps_settings_register_tabs` | action | Frontend Settings Module | Baixo | âœ… Usado para registrar aba |
| `dps_settings_save_save_frontend` | action | Frontend Settings Module | Baixo | âœ… Usado para salvar flags |

---

## 5. Contratos de options/dados

| Option | Tipo | MÃ³dulo | Uso |
|--------|------|--------|-----|
| `dps_frontend_feature_flags` | array (JSON) | Todos | Feature flags por mÃ³dulo |
| `dps_registration_page_id` | int | Registration | Leitura-only (detectar pÃ¡gina do shortcode) |
| `dps_booking_page_id` | int | Booking | Leitura-only (detectar pÃ¡gina do shortcode) |

> **Nota**: O frontend add-on **nÃ£o cria nem altera** options dos legados. Apenas lÃª `dps_registration_page_id` e `dps_booking_page_id` para enqueue condicional de CSS.

---

## 6. Impacto da desativaÃ§Ã£o por mÃ³dulo

| MÃ³dulo desativado | Efeito | Quem assume | Impacto em outros add-ons |
|-------------------|--------|-------------|---------------------------|
| Registration | Legado reassume shortcode | `DPS_Registration_Addon` | Nenhum |
| Booking | Legado reassume shortcode | `DPS_Booking_Addon` | Nenhum |
| Settings | Aba desaparece | â€” | Nenhum (flags persistidos) |
| Plugin inteiro | Todos os legados reassumem | Respectivos add-ons | Nenhum |

---

## 7. Requisitos mÃ­nimos verificados

| Requisito | VersÃ£o mÃ­nima | Verificado |
|-----------|---------------|------------|
| WordPress | 6.9+ | âœ… |
| PHP | 8.4+ | âœ… |
| Plugin base | Ativo com DPS_Base_Plugin | âœ… |
| Design tokens | dps-design-tokens.css disponÃ­vel | âœ… |
