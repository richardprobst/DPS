# Relatório de Auditoria — Registration Add-on

**Data:** 2026-02-07  
**Versão auditada:** 1.3.0  
**Escopo:** Todos os arquivos PHP, CSS e JS do add-on `plugins/desi-pet-shower-registration/`

---

## 1. Inventário do Add-on

### Estrutura de Arquivos

| Arquivo | Tipo | Linhas |
|---------|------|--------|
| `desi-pet-shower-registration-addon.php` | Entrypoint + Classe principal | ~3.365 |
| `assets/css/registration-addon.css` | Estilos do formulário público | ~940 |
| `assets/js/dps-registration.js` | JavaScript (validações, máscaras, steps) | ~1.650 |
| `uninstall.php` | Rotina de desinstalação | 68 |
| `README.md` | Documentação do add-on | 227 |

### Endpoints AJAX Registrados (2)

| Action | Handler | Nonce | Capability | Sanitização |
|--------|---------|-------|-----------|-------------|
| `dps_registration_send_test_email` | `ajax_send_test_email()` | ✅ `dps_registration_test_email` | ✅ manage_options | ✅ |
| `dps_registration_check_duplicate` | `ajax_check_duplicate()` | ✅ `dps_duplicate_check` | ✅ manage_options | ✅ |

### Shortcodes Registrados (1)

| Shortcode | Handler | Status |
|-----------|---------|--------|
| `[dps_registration_form]` | `render_registration_form()` | Ativo |

### Endpoints REST Registrados (1)

| Rota | Método | Permission Callback | Rate Limiting |
|------|--------|---------------------|---------------|
| `dps/v1/register` | POST | API key hash (SHA-256) + `hash_equals` | ✅ Por chave + por IP |

### Hooks Personalizados Expostos

| Hook | Tipo | Parâmetros | Propósito |
|------|------|-----------|-----------|
| `dps_registration_after_client_created` | Action | `$referral_code, $client_id, $client_email, $client_phone` | Pós-criação de cliente |
| `dps_registration_spam_check` | Filter | `$result, $sanitized_context` | Validação anti-spam extensível |
| `dps_registration_after_fields` | Action | — | Ponto de extensão para campos adicionais |
| `dps_registration_agenda_url` | Filter | `$fallback_url` | Sobrescrever URL de agendamento |

### Cron Jobs

| Hook | Intervalo | Callback |
|------|-----------|----------|
| `dps_registration_confirmation_reminder` | Horário | `send_confirmation_reminders()` |

---

## 2. Achados de Segurança

### 2.1 Corrigido: XSS no JavaScript (dps-registration.js, linha 803)

**Severidade:** Média  
**Status:** ✅ Corrigido  
**Arquivo:** `assets/js/dps-registration.js`, linha 803

**Problema:** O nome do pet (input do usuário) era inserido via `innerHTML` no título de preferências de produtos, permitindo injeção de HTML:

```javascript
// ANTES (vulnerável)
petTitle.innerHTML = speciesIcon + ' ' + (petName || 'Pet ' + (i + 1));
```

**Correção:** Substituído `innerHTML` por `textContent`, que trata o conteúdo como texto puro:

```javascript
// DEPOIS (seguro)
petTitle.textContent = speciesIcon + ' ' + (petName || 'Pet ' + (i + 1));
```

**Impacto:** Baixo — o valor vem de um input do próprio formulário (self-XSS), mas a correção é preventiva e alinhada com boas práticas.

### 2.2 OK: AJAX Handlers

Ambos os AJAX handlers (`ajax_send_test_email` e `ajax_check_duplicate`) implementam corretamente:
- ✅ Verificação de nonce via `check_ajax_referer()`
- ✅ Verificação de capability `manage_options`
- ✅ Sanitização de inputs com `sanitize_text_field()` / `sanitize_email()`
- ✅ Respostas via `wp_send_json_error()` / `wp_send_json_success()`

### 2.3 OK: REST API

O endpoint `/dps/v1/register` implementa:
- ✅ Autenticação por API key via header `X-DPS-Registration-Key`
- ✅ Chave armazenada como hash SHA-256 (nunca em texto claro)
- ✅ Comparação segura com `hash_equals()`
- ✅ Rate limiting duplo (por chave + por IP) com transients
- ✅ Header `Retry-After` em respostas 429
- ✅ Sanitização e validação completa de dados via `validate_api_client_data()`
- ✅ Logging sem PII (hashes em vez de valores reais)

### 2.4 OK: Formulário Público

- ✅ Nonce (`dps_reg_nonce` / `dps_reg_action`) verificado via `DPS_Request_Validator`
- ✅ Honeypot (`dps_hp_field`) para bots
- ✅ reCAPTCHA v3 integrado com threshold configurável
- ✅ Hook `dps_registration_spam_check` para validações extensíveis
- ✅ Rate limiting por IP (3 cadastros/hora para não-admins)
- ✅ Bypass de restrições para admins (`manage_options`)
- ✅ Whitelist validation para campos com valores predefinidos (espécie, porte, sexo)
- ✅ Validação de coordenadas (lat: -90/+90, lng: -180/+180)
- ✅ Validação de peso (positivo, ≤500kg)
- ✅ Validação de data de nascimento (não futura)
- ✅ Escape de wildcards LIKE via `escape_like_wildcards()`

### 2.5 OK: Confirmação de Email

- ✅ Token gerado via `wp_generate_uuid4()` (128 bits de entropia)
- ✅ Expiração de 48 horas validada no backend
- ✅ Token removido após confirmação (single-use)
- ✅ Timestamp validado como inteiro positivo ≤ time()

### 2.6 OK: Sanitização/Escape no Output

- ✅ Admin pages verificam `manage_options` antes de renderizar
- ✅ Todos os valores em atributos HTML usam `esc_attr()`
- ✅ Todas as URLs usam `esc_url()` / `esc_url_raw()`
- ✅ Texto traduzível usa `esc_html__()` / `esc_html()`
- ✅ Email template escapa placeholders com `esc_html()` e `esc_url()`
- ✅ Inline JS usa `esc_js()` para strings literais
- ✅ Redirecionamentos usam `wp_safe_redirect()`

### 2.7 OK: Logging Seguro

- ✅ PII nunca logada em claro — usa hashes SHA-256 via `get_safe_hash()`
- ✅ IPs logados como hashes via `get_client_ip_hash()`
- ✅ Fallback para `error_log()` quando `DPS_Logger` indisponível

### 2.8 OK: Uninstall

- ✅ Verifica `WP_UNINSTALL_PLUGIN` antes de executar
- ✅ Remove todas as options do add-on
- ✅ Limpa transients com prefixo correto via query SQL
- ✅ Remove eventos de cron agendados

---

## 3. Performance

### 3.1 Corrigido: Query sem `no_found_rows` (find_duplicate_client)

**Severidade:** Baixa  
**Status:** ✅ Corrigido  
**Arquivo:** `desi-pet-shower-registration-addon.php`, método `find_duplicate_client()`

**Problema:** A query de detecção de duplicatas usava `get_posts()` com `posts_per_page => 1` mas sem `no_found_rows => true`, executando um `COUNT(*)` desnecessário.

**Correção:** Adicionado `'no_found_rows' => true` à query.

### 3.2 Corrigido: Query sem `no_found_rows` (maybe_handle_email_confirmation)

**Severidade:** Baixa  
**Status:** ✅ Corrigido  
**Arquivo:** `desi-pet-shower-registration-addon.php`, método `maybe_handle_email_confirmation()`

**Problema:** A query de busca de token de confirmação executava `COUNT(*)` desnecessário.

**Correção:** Adicionado `'no_found_rows' => true` à query.

### 3.3 OK: Cron de Lembretes

- ✅ Processamento em batches de 50 com offset
- ✅ Usa `fields => 'ids'` e `no_found_rows => true`
- ✅ Desabilita caches desnecessários (`update_post_meta_cache`, `update_post_term_cache`)

### 3.4 OK: Assets

- ✅ CSS e JS carregados apenas na página de cadastro (verificação por page_id e shortcode)
- ✅ reCAPTCHA carregado apenas quando habilitado e com site key
- ✅ Dataset de raças usa cache estático (`static $dataset`)
- ✅ Google Maps script carregado apenas quando API key configurada

### 3.5 OK: Admin Queries

- ✅ Pendentes: paginação com 20 por página, `update_post_term_cache => false`
- ✅ Busca por telefone escapa wildcards LIKE

---

## 4. Manutenibilidade

### 4.1 Informacional: Arquivo Monolítico

**Severidade:** Informacional  
**Status:** Aceito (não requer ação imediata)

O arquivo principal tem ~3.365 linhas com toda a lógica em uma única classe. Embora funcione, a separação em classes menores (e.g., `DPS_Registration_Form`, `DPS_Registration_API`, `DPS_Registration_Email`) facilitaria manutenção futura.

**Nota:** A refatoração não é urgente pois o código está bem organizado com seções comentadas e DocBlocks completos.

### 4.2 Informacional: Duplicação de Lógica de Fieldset

**Severidade:** Informacional  
**Status:** Aceito

Os métodos `get_pet_fieldset_html()` e `get_pet_fieldset_html_placeholder()` compartilham ~95% do HTML com pequenas variações (índice fixo vs `__INDEX__`). Uma refatoração futura poderia unificá-los com um parâmetro de modo.

### 4.3 Informacional: CSS com Cores Hardcoded

**Severidade:** Informacional  
**Status:** Aceito

O CSS define variáveis locais em `.dps-registration-form` com cores hardcoded (e.g., `--dps-accent: #0ea5e9`). Idealmente, deveria usar os design tokens globais de `dps-design-tokens.css`. Porém, como o formulário é público (frontend), manter variáveis locais evita dependência do design system admin e garante consistência visual independente do tema.

### 4.4 Informacional: Método Deprecated `get_client_ip_hash()`

**Severidade:** Informacional  
**Status:** Aceito

O método `get_client_ip_hash()` está marcado como `@deprecated 2.5.0` com fallback para `DPS_IP_Helper`. A lógica de fallback é correta e a migração será automática quando o helper estiver disponível globalmente.

---

## 5. Compatibilidade

### 5.1 OK: WordPress e PHP

- ✅ Declara `Requires at least: 6.9` e `Requires PHP: 8.4`
- ✅ Usa `array_key_first()` (PHP 7.3+) — compatível
- ✅ Usa `wp_generate_uuid4()` (WP 4.7+) — compatível
- ✅ Não usa funções deprecated do WordPress

### 5.2 OK: Integração com Base Plugin

- ✅ Verifica `class_exists( 'DPS_Base_Plugin' )` em `plugins_loaded`
- ✅ Exibe aviso admin se plugin base não estiver ativo
- ✅ Usa helpers do core quando disponíveis com fallback (Phone, IP, Message, Cache, Communications, Request Validator, Logger)

### 5.3 OK: Integração com Outros Add-ons

- ✅ Hook `dps_registration_after_client_created` permite integração com Loyalty
- ✅ Usa `DPS_Communications_API` quando disponível (Communications add-on)
- ✅ Referência ao Portal do Cliente via option/helper

---

## 6. JavaScript (dps-registration.js)

### 6.1 OK: Estrutura e Padrões

- ✅ IIFE com `'use strict'`
- ✅ Função `escapeHtml()` implementada e usada no modal de duplicatas
- ✅ Dados de duplicata do servidor escapados com `escapeHtml()` antes de innerHTML
- ✅ Summary usa `textContent` e `createTextNode()` (seguro contra XSS)
- ✅ Validações client-side (CPF mod 11, telefone, nome mínimo 2 chars)
- ✅ Máscaras de input (CPF, telefone)
- ✅ Navegação por steps com progress bar acessível (`aria-live`, `role="progressbar"`)
- ✅ Integração reCAPTCHA v3 com tratamento de erro
- ✅ Clone de pet via template JSON (sem eval)
- ✅ Prevenção de duplo submit

### 6.2 OK: innerHTML Restante

Os usos restantes de `innerHTML` são seguros:
- `datalist.innerHTML = ''` — limpa conteúdo (seguro)
- `wrapper.innerHTML = ''` — limpa erros (seguro)
- `div.innerHTML = html` (linha 651) — template vindo do servidor via JSON (controlado)
- `shampooField.innerHTML`, `perfumeField.innerHTML`, etc. — HTML estático com options hardcoded (seguro)
- `summaryContent.innerHTML = ''` — limpa resumo (seguro)
- `container.innerHTML = modalHtml` — modal com dados escapados via `escapeHtml()` (seguro)

---

## 7. CSS (registration-addon.css)

### 7.1 OK: Responsividade

- ✅ Breakpoints em 480px, 640px, 768px, 1024px, 1200px, 1440px
- ✅ Layout flexbox com flex-wrap para campos
- ✅ `@media print` para impressão
- ✅ `@media (prefers-reduced-motion: reduce)` para acessibilidade

### 7.2 OK: Acessibilidade

- ✅ `:focus-visible` com outline visível (2px solid, offset 2px)
- ✅ `role="alert"` em mensagens de erro
- ✅ `aria-live="polite"` na barra de progresso
- ✅ Labels associados corretamente

---

## 8. Resumo de Ações

| # | Achado | Severidade | Status |
|---|--------|-----------|--------|
| 2.1 | XSS via innerHTML em petTitle (JS) | Média | ✅ Corrigido |
| 3.1 | Query sem no_found_rows (find_duplicate) | Baixa | ✅ Corrigido |
| 3.2 | Query sem no_found_rows (email_confirmation) | Baixa | ✅ Corrigido |
| 4.1 | Arquivo monolítico (~3.365 linhas) | Informacional | Aceito |
| 4.2 | Duplicação de fieldset HTML | Informacional | Aceito |
| 4.3 | CSS com cores hardcoded | Informacional | Aceito |
| 4.4 | Método deprecated com fallback | Informacional | Aceito |

---

## 9. Conclusão

O Registration Add-on apresenta um nível de maturidade **alto** em segurança e qualidade de código:

- **Segurança:** Todas as entradas são sanitizadas e escapadas. Nonces, capabilities e rate limiting estão implementados corretamente. A única vulnerabilidade encontrada (XSS no JS) era de baixo impacto (self-XSS) e foi corrigida.

- **Performance:** Queries otimizadas com `fields => 'ids'`, processamento em batch no cron, e assets condicionais. Corrigidas 2 queries sem `no_found_rows`.

- **Manutenibilidade:** Código bem documentado com DocBlocks completos e seções organizadas. A refatoração em classes menores é recomendada para o futuro mas não urgente.

- **Compatibilidade:** Integração correta com o plugin base e outros add-ons, com fallbacks adequados para quando dependências não estão disponíveis.

**Classificação geral: ✅ Aprovado com correções menores aplicadas**
