# Relatório de Auditoria — Agenda Add-on

**Data:** 2026-02-07  
**Versão auditada:** 1.1.0  
**Escopo:** Todos os arquivos PHP, CSS e JS do add-on `plugins/desi-pet-shower-agenda/`

---

## 1. Inventário do Add-on

### Estrutura de Arquivos

| Arquivo | Tipo | Linhas |
|---------|------|--------|
| `desi-pet-shower-agenda-addon.php` | Entrypoint + Classe principal | ~4.200 |
| `includes/class-dps-agenda-hub.php` | Hub centralizado (abas admin) | 151 |
| `includes/class-dps-agenda-dashboard-service.php` | Serviço de KPIs do dashboard | 318 |
| `includes/class-dps-agenda-capacity-helper.php` | Helper de capacidade/lotação | 341 |
| `includes/class-dps-agenda-payment-helper.php` | Helper de status de pagamento | 237 |
| `includes/class-dps-agenda-taxidog-helper.php` | Helper de TaxiDog | 272 |
| `includes/class-dps-agenda-gps-helper.php` | Helper de rotas GPS | 183 |
| `includes/trait-dps-agenda-renderer.php` | Trait de renderização | ~900 |
| `includes/trait-dps-agenda-query.php` | Trait de queries | 221 |
| `includes/integrations/class-dps-google-auth.php` | OAuth 2.0 Google | ~400 |
| `includes/integrations/class-dps-google-calendar-client.php` | API Client Google Calendar | ~250 |
| `includes/integrations/class-dps-google-calendar-sync.php` | Sincronização DPS→Calendar | ~300 |
| `includes/integrations/class-dps-google-calendar-webhook.php` | Webhook bidirecional | ~300 |
| `includes/integrations/class-dps-google-integrations-settings.php` | UI de configurações Google | ~400 |
| `includes/integrations/class-dps-google-tasks-client.php` | API Client Google Tasks | ~250 |
| `includes/integrations/class-dps-google-tasks-sync.php` | Sincronização DPS→Tasks | ~300 |
| `assets/css/agenda-addon.css` | Estilos da agenda | ~2.000 |
| `assets/css/dashboard.css` | Estilos do dashboard | 491 |
| `assets/js/agenda-addon.js` | JavaScript principal | — |
| `assets/js/services-modal.js` | Modal de serviços | — |
| `uninstall.php` | Rotina de desinstalação | 47 |

### Endpoints AJAX Registrados (14)

| Action | Handler | Nonce | Capability | Sanitização |
|--------|---------|-------|-----------|-------------|
| `dps_update_status` | `update_status_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_quick_action` | `quick_action_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_update_confirmation` | `update_confirmation_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_update_taxidog` | `update_taxidog_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_request_taxidog` | `request_taxidog_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_save_capacity` | `save_capacity_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_resend_payment` | `resend_payment_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_get_services_details` | `get_services_details_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_export_csv` | `export_csv_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_agenda_export_pdf` | `export_pdf_ajax()` | ✅ | ✅ (via DPS_Request_Validator) | ✅ |
| `dps_agenda_calendar_events` | `calendar_events_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_quick_reschedule` | `quick_reschedule_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_get_appointment_history` | `get_appointment_history_ajax()` | ✅ | ✅ manage_options | ✅ |
| `dps_get_admin_kpis` | `get_admin_kpis_ajax()` | ✅ | ✅ manage_options | ✅ |

### Shortcodes Registrados (3)

| Shortcode | Handler | Status |
|-----------|---------|--------|
| `[dps_agenda_page]` | `render_agenda_shortcode()` | Ativo |
| `[dps_agenda_dashboard]` | `render_dashboard_shortcode()` | Ativo |
| `[dps_charges_notes]` | `render_charges_notes_shortcode_deprecated()` | Deprecated (v1.1.0) |

---

## 2. Achados de Segurança

### 2.1 Corrigido: XSS no Hub (class-dps-agenda-hub.php)

**Severidade:** Crítica  
**Status:** ✅ Corrigido  
**Arquivo:** `includes/class-dps-agenda-hub.php`, linhas 103 e 122

**Problema:** Os métodos `render_dashboard_tab()` e `render_settings_tab()` capturavam o output de outras funções via `ob_get_clean()`, manipulavam com `preg_replace()` e faziam `echo $content` sem nenhum escape.

**Correção:** Substituído `echo $content` por `echo wp_kses_post( $content )` em ambos os métodos, garantindo que apenas tags HTML seguras sejam emitidas.

### 2.2 Corrigido: Chave de Criptografia Hardcoded (class-dps-google-auth.php)

**Severidade:** Crítica  
**Status:** ✅ Corrigido  
**Arquivo:** `includes/integrations/class-dps-google-auth.php`, linhas 391-403

**Problema:** O fallback da chave de criptografia usava uma string literal determinística (`'dps_google_integrations_fallback'`), idêntica para todas as instalações. Um atacante que comprometesse um site poderia decriptografar tokens de qualquer outro site que usasse o mesmo fallback.

**Correção:** O fallback agora gera uma senha aleatória única com `wp_generate_password(64, true, true)` e a persiste via `update_option('dps_encryption_key_fallback', ..., false)` com autoload desativado. Além disso, o fallback de `AUTH_KEY` agora valida que a chave tenha entropia suficiente (≥32 caracteres) e não contenha o placeholder padrão do WordPress.

### 2.3 Info: Validação de Webhook (class-dps-google-calendar-webhook.php)

**Severidade:** Média  
**Status:** ℹ️ Informativo (melhoria sugerida)  
**Arquivo:** `includes/integrations/class-dps-google-calendar-webhook.php`, linha 238

**Observação:** A validação do webhook Google Calendar utiliza apenas comparação de token (`x-goog-channel-token`). Embora funcional, uma validação adicional (verificação de `x-goog-resource-id`, log de tentativas, expiração do webhook) reforçaria a segurança. **Não é uma vulnerabilidade ativa** pois o token é gerado aleatoriamente e armazenado de forma segura.

### 2.4 Info: Token Refresh Margin (class-dps-google-auth.php)

**Severidade:** Baixa  
**Status:** ℹ️ Informativo

**Observação:** A margem de refresh do token OAuth é de 5 minutos (`time() + 300`). Em cenários com alta latência, isso pode ser insuficiente. Sugestão: aumentar para 15-30 minutos em iteração futura.

---

## 3. Achados de Qualidade de Código

### 3.1 Corrigido: Cores Hardcoded no Heatmap de Capacidade

**Severidade:** Média  
**Status:** ✅ Corrigido  
**Arquivo:** `includes/class-dps-agenda-capacity-helper.php`

**Problema:** Os métodos `get_occupancy_color()` e `get_occupancy_text_color()` retornavam cores hex hardcoded (#d1fae5, #fef3c7, etc.) que não seguiam o sistema de design M3 Expressive e não suportavam dark theme.

**Correção:**  
- Métodos antigos substituídos por `get_occupancy_level()` que retorna 'available', 'busy' ou 'full'
- Template agora usa classes CSS (`dps-heatmap-cell-available`, `dps-heatmap-cell-busy`, `dps-heatmap-cell-full`) em vez de inline styles
- Legenda também usa classes CSS (`dps-heatmap-legend-color--available`, etc.)
- Novas classes CSS em `dashboard.css` usando tokens M3 (`--dps-color-success-container`, `--dps-color-warning-container`, `--dps-color-error-container`)
- Suporte automático a dark theme via design tokens

### 3.2 Info: Cores Hardcoded em Helpers de Badge

**Severidade:** Baixa  
**Status:** ℹ️ Informativo (melhoria futura)

**Arquivos afetados:**
- `class-dps-agenda-payment-helper.php`: Métodos `get_payment_badge_config()`, `render_payment_badge()`
- `class-dps-agenda-taxidog-helper.php`: Métodos `get_taxidog_badge_config()`, `render_taxidog_badge()`
- `class-dps-agenda-gps-helper.php`: Método `render_route_button()`
- `class-dps-agenda-dashboard-service.php`: Método `render_kpi_card()`

**Observação:** Estes helpers usam cores hex inline (e.g., `#10b981`, `#f59e0b`, `#ef4444`) em seus badges. Uma migração para classes CSS com tokens M3 é recomendada em iteração futura. **Impacto menor** pois os badges já usam classes CSS e as cores inline são apenas redundância de estilo.

### 3.3 Info: Arquivo Principal Muito Grande

**Severidade:** Baixa (manutenibilidade)  
**Status:** ℹ️ Informativo

**Arquivo:** `desi-pet-shower-agenda-addon.php` (~4.200 linhas, 193KB)

**Observação:** O arquivo principal é extenso. As fases de refatoração já extraíram traits (`DPS_Agenda_Renderer`, `DPS_Agenda_Query`) e helpers. Sugestão para refatoração futura: extrair os métodos de renderização de abas (Calendar, Admin Dashboard) para classes dedicadas.

### 3.4 Info: Shortcode Deprecated sem Remoção Planejada

**Severidade:** Baixa  
**Status:** ℹ️ Informativo

O shortcode `[dps_charges_notes]` está deprecated desde v1.1.0 mas sem versão-alvo de remoção. Recomendação: definir versão de remoção (e.g., v2.0.0) e documentar no CHANGELOG.

---

## 4. Achados Positivos

### Segurança ✅
- **Todos os 14 endpoints AJAX** verificam nonce e capability (`manage_options`)
- **Sanitização de input** consistente com `sanitize_text_field()`, `intval()`, `sanitize_textarea_field()`
- **Escape de output** com `esc_html()`, `esc_attr()`, `esc_url()` em toda saída HTML
- **Nenhuma query SQL direta** no arquivo principal — usa WordPress API (`get_posts`, `get_post_meta`, etc.)
- **OAuth state parameter** validado para proteção CSRF no fluxo Google
- **Tokens armazenados criptografados** via AES-256-CBC
- **Carregamento condicional** do módulo Google: somente se extensão OpenSSL disponível

### Arquitetura ✅
- **Singleton pattern** na classe principal
- **Traits** para separação de responsabilidades (renderer, query)
- **Helpers especializados** para pagamento, TaxiDog, GPS, capacidade, dashboard
- **Hub centralizado** com abas para consolidar menus
- **Constantes de status** centralizadas para evitar strings hardcoded
- **Hooks/filtros** para extensibilidade (`dps_agenda_daily_limit`, `dps_agenda_shop_address`)
- **Versionamento de agendamentos** para evitar conflitos de escrita
- **Log de auditoria** via `DPS_Logger` para mudanças de status

### I18n ✅
- Text domain `dps-agenda-addon` carregado em `init` com prioridade 1
- Todas as strings de interface com `__()` / `esc_html_e()`
- Arquivo POT presente em `languages/`

### Conformidade com AGENTS.md ✅
- Versões mínimas declaradas: WordPress 6.9, PHP 8.4
- Menu registrado como submenu de `desi-pet-shower`
- Assets carregados condicionalmente (somente em páginas relevantes)
- Nenhum segredo hardcoded no código

---

## 5. Resumo de Alterações Realizadas

| # | Tipo | Arquivo | Alteração |
|---|------|---------|-----------|
| 1 | Segurança | `includes/class-dps-agenda-hub.php` | `echo $content` → `echo wp_kses_post($content)` em 2 métodos |
| 2 | Segurança | `includes/integrations/class-dps-google-auth.php` | Fallback de criptografia: chave hardcoded → chave aleatória persistida + validação de entropia AUTH_KEY |
| 3 | Design | `includes/class-dps-agenda-capacity-helper.php` | Cores hardcoded → classes CSS com tokens M3 |
| 4 | Design | `assets/css/dashboard.css` | Adicionadas classes CSS para níveis de ocupação do heatmap |

---

## 6. Recomendações Futuras

1. **Migrar badges de helpers para CSS classes** com tokens M3 (payment, taxidog, GPS)
2. **Aumentar margem de refresh do token OAuth** de 5 para 30 minutos
3. **Extrair renderizadores** do arquivo principal para classes dedicadas (~4200 linhas é grande)
4. **Definir versão de remoção** do shortcode deprecated `[dps_charges_notes]`
5. **Considerar rate limiting** em endpoints AJAX de alta frequência
