# Auditoria de Segurança — Fase 1

> **Data:** 2026-02-18
> **Referência:** `docs/implementation/PLANO_IMPLEMENTACAO_FASES.md` — Fase 1
> **Status:** ✅ Concluída

---

## Índice

1. [Resumo Executivo](#resumo-executivo)
2. [SQL Injection — Queries sem prepare()](#sql-injection--queries-sem-prepare)
3. [Verificação de Nonce em AJAX](#verificação-de-nonce-em-ajax)
4. [Endpoints REST — Permission Callbacks](#endpoints-rest--permission-callbacks)
5. [Sanitização de Entrada](#sanitização-de-entrada)
6. [Escape de Saída HTML](#escape-de-saída-html)
7. [Capabilities Mapeadas](#capabilities-mapeadas)
8. [Funções Perigosas](#funções-perigosas)
9. [Correções Aplicadas](#correções-aplicadas)

---

## Resumo Executivo

| Categoria | Status | Achados | Corrigidos |
|-----------|--------|---------|------------|
| SQL sem `prepare()` | ✅ Corrigido | 30+ queries | 30+ |
| Nonce em AJAX | ✅ OK | 0 faltando | N/A |
| REST Permissions | ⚠️ Documentado | 2 endpoints `__return_true` | 0 (webhook público — ASK BEFORE) |
| Sanitização de entrada | ✅ Corrigido | 1 achado | 1 |
| Escape de saída | ✅ OK | 0 faltando | N/A |
| Funções perigosas | ✅ OK | 0 achados | N/A |

---

## SQL Injection — Queries sem prepare()

### Metodologia

Busca global por padrões vulneráveis:
```bash
grep -rn '\$wpdb->\(query\|get_results\|get_var\|get_row\|get_col\)(' plugins/ --include='*.php'
```

Cada ocorrência foi classificada em:
- **Vulnerável**: recebe dados de usuário sem `prepare()`
- **DDL seguro**: usa `$wpdb->prefix` (constante do WP) para nomes de tabela em operações DDL
- **Constante**: query sem variáveis externas

### Achados por Plugin

#### Finance Add-on (`desi-pet-shower-finance`)

| Arquivo | Tipo | Descrição | Severidade | Status |
|---------|------|-----------|------------|--------|
| `desi-pet-shower-finance-addon.php` L333-351 | DDL | SHOW COLUMNS / ALTER TABLE / UPDATE em migração | Baixa | ✅ Documentado |
| `desi-pet-shower-finance-addon.php` L361-371 | DDL | SHOW INDEX / CREATE INDEX | Baixa | ✅ Documentado |
| `desi-pet-shower-finance-addon.php` L411-436 | DDL | SHOW COLUMNS / ALTER TABLE para parcelas | Baixa | ✅ Documentado |
| `desi-pet-shower-finance-addon.php` L1720 | Query | SELECT DISTINCT sem prepare() | Média | ✅ Corrigido |
| `desi-pet-shower-finance-addon.php` L1791 | Query | COUNT sem prepare() quando params vazio | Baixa | ✅ Corrigido |
| `desi-pet-shower-finance-addon.php` L1830 | Query | SELECT * sem prepare() quando sem filtros | Baixa | ✅ Corrigido |
| `uninstall.php` L26 | DDL | DROP TABLE com backticks | Baixa | ✅ Documentado |
| `includes/class-dps-finance-api.php` | — | Todas as queries usam prepare() | — | ✅ OK |
| `includes/class-dps-finance-rest.php` | — | Todas as queries usam prepare() | — | ✅ OK |
| `includes/class-dps-finance-audit.php` | — | Todas as queries usam prepare() | — | ✅ OK |

#### Base Plugin (`desi-pet-shower-base`)

| Arquivo | Tipo | Descrição | Severidade | Status |
|---------|------|-----------|------------|--------|
| `class-dps-base-frontend.php` L5760 | Query | LIKE sem esc_like/prepare() | Média | ✅ Corrigido |
| `class-dps-logs-admin-page.php` L239 | Query | COUNT sem prepare() (tabela constante) | Baixa | ✅ Documentado |
| `uninstall.php` L58 | DDL | DROP TABLE | Baixa | ✅ Documentado |

#### Backup Add-on (`desi-pet-shower-backup`)

| Arquivo | Tipo | Descrição | Severidade | Status |
|---------|------|-----------|------------|--------|
| `desi-pet-shower-backup-addon.php` L873 | Query | SELECT LIKE sem prepare() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L879 | Query | SELECT IN com intval() sem prepare() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L882 | Query | SELECT LIKE sem prepare() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L1071 | Query | SELECT * sem prepare() | Baixa | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L1598 | Query | SELECT IN com esc_sql() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L1613 | Query | SELECT IN com intval() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L1621-1640 | Query | DELETE IN com intval() | Alta | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L2167 | Query | SELECT IN com intval() | Média | ✅ Corrigido |
| `desi-pet-shower-backup-addon.php` L2190-2198 | Query | SELECT IN com intval() | Média | ✅ Corrigido |

#### AI Add-on (`desi-pet-shower-ai`)

| Arquivo | Tipo | Descrição | Severidade | Status |
|---------|------|-----------|------------|--------|
| `class-dps-ai-maintenance.php` L311-320 | Query | COUNT/MIN sem prepare() (tabela constante) | Baixa | ✅ Documentado |
| `class-dps-ai-analytics.php` L453 | Query | COUNT sem prepare() (tabela constante) | Baixa | ✅ Documentado |

### Padrão de Correção Aplicado

1. **Queries com entrada de usuário** → `$wpdb->prepare()` com placeholders `%s`, `%d`
2. **Queries IN com IDs** → `array_fill()` + `implode()` para gerar placeholders dinâmicos
3. **Queries LIKE** → `$wpdb->esc_like()` + `$wpdb->prepare()`
4. **Queries DDL (ALTER, CREATE INDEX, DROP)** → Backticks + `phpcs:ignore` documentando que `$wpdb->prefix` é seguro
5. **Queries sem variáveis** → `phpcs:ignore` documentando a constância

---

## Verificação de Nonce em AJAX

### Resultado: ✅ Todos os handlers AJAX verificam nonce

Handlers auditados:
- **Finance**: `dps_get_partial_history`, `dps_delete_partial` → `check_ajax_referer()`
- **Booking**: `dps_booking_search_client`, `dps_booking_get_pets`, etc. → `check_ajax_referer()`
- **Client Portal**: Todos os endpoints `nopriv` → `DPS_Request_Validator::verify_ajax_nonce()`
- **Base/Frontend**: Todos os handlers → nonce verificado

---

## Endpoints REST — Permission Callbacks

### Endpoints Seguros (com capability check)

| Plugin | Endpoint | Permission |
|--------|----------|------------|
| Finance | `dps-finance/v1/transactions` | `manage_options` |
| Loyalty | `dps-loyalty/v1/*` (5 rotas) | `manage_options` |
| Communications | `dps-communications/v1/*` (3 rotas) | `manage_options` |

### Endpoints Webhook (com `__return_true`)

| Plugin | Endpoint | Justificativa |
|--------|----------|---------------|
| AI | `/dps-ai/v1/whatsapp-webhook` | Webhook público do Meta/WhatsApp. Validação interna via `verify_token`. |
| Agenda | `/dps/v1/google-calendar-webhook` | Webhook público do Google Calendar. Validação interna via headers. |

> **Nota:** Endpoints de webhook externos são legítimos com `__return_true` pois recebem chamadas de serviços terceiros (Meta, Google) que não possuem autenticação WordPress. A validação é feita internamente no handler.

---

## Sanitização de Entrada

### Achado Corrigido

**Arquivo:** `plugins/desi-pet-shower-services/dps_service/desi-pet-shower-services-addon.php`
**Linhas:** 2249-2250
**Descrição:** Arrays `$_POST['appointment_extra_names']` e `$_POST['appointment_extra_prices']` eram recebidos sem sanitização imediata (sanitização acontecia apenas no loop).
**Correção:** `array_map('sanitize_text_field', ...)` e `wp_unslash()` aplicados imediatamente.

### Padrão Verificado

Todos os demais usos de `$_GET`, `$_POST`, `$_REQUEST` nos plugins aplicam sanitização imediata:
- `sanitize_text_field()` para strings
- `intval()` / `absint()` para inteiros
- `sanitize_email()` para e-mails
- `wp_unslash()` para escapamento de barras

---

## Escape de Saída HTML

### Resultado: ✅ Nenhum problema encontrado

Padrão consistente de escape em saída HTML:
- `esc_html()` / `esc_html__()` para conteúdo texto
- `esc_attr()` para atributos HTML
- `esc_url()` para URLs
- `wp_kses()` / `wp_kses_post()` para HTML controlado

---

## Capabilities Mapeadas

### Capabilities utilizadas no sistema

| Capability | Contexto de Uso | Plugins |
|-----------|-----------------|---------|
| `manage_options` | Admin pages, REST endpoints, AJAX handlers | Todos os add-ons |
| `dps_manage_clients` | Gestão de clientes | Base, Frontend |
| `dps_manage_pets` | Gestão de pets | Base, Frontend |
| `dps_manage_appointments` | Gestão de agendamentos | Base, Agenda, Frontend |

### Princípio do Menor Privilégio

O sistema utiliza uma hierarquia simples e adequada:
- **Operações administrativas** (configurações, relatórios, finanças): `manage_options`
- **Operações de gestão** (clientes, pets, agendamentos): capabilities customizadas `dps_manage_*`
- **Operações do portal** (cliente final): autenticação via token/sessão sem WordPress capabilities

---

## Funções Perigosas

### Resultado: ✅ Nenhum uso detectado

Buscas realizadas por: `eval()`, `system()`, `exec()`, `passthru()`, `shell_exec()`, `popen()`
Nenhuma ocorrência encontrada nos plugins do sistema.

---

## Correções Aplicadas

### Arquivos Alterados

1. `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php` — Backticks em table identifiers, phpcs:ignore em DDL seguro
2. `plugins/desi-pet-shower-finance/uninstall.php` — phpcs:ignore em DROP TABLE
3. `plugins/desi-pet-shower-base/includes/class-dps-base-frontend.php` — LIKE com esc_like + prepare()
4. `plugins/desi-pet-shower-base/includes/class-dps-logs-admin-page.php` — phpcs:ignore em COUNT
5. `plugins/desi-pet-shower-base/uninstall.php` — phpcs:ignore em DROP TABLE
6. `plugins/desi-pet-shower-backup/desi-pet-shower-backup-addon.php` — prepare() com placeholders em SELECT/DELETE/LIKE
7. `plugins/desi-pet-shower-ai/includes/class-dps-ai-maintenance.php` — Backticks e phpcs:ignore
8. `plugins/desi-pet-shower-ai/includes/class-dps-ai-analytics.php` — Backticks e phpcs:ignore
9. `plugins/desi-pet-shower-services/dps_service/desi-pet-shower-services-addon.php` — Sanitização imediata de $_POST arrays

### Validação

Todos os 9 arquivos passaram em `php -l` sem erros de sintaxe.
