# Relatório de Auditoria — Client Portal Add-on

**Data:** 2026-02-07  
**Versão auditada:** 2.4.3  
**Diretório:** `plugins/desi-pet-shower-client-portal/`  
**Total de arquivos:** 37

---

## 1. Inventário

### 1.1 Entrypoint
- `desi-pet-shower-client-portal.php` — arquivo principal, constantes, requires, hooks de inicialização

### 1.2 Classes Principais
| Classe | Arquivo | Responsabilidade |
|--------|---------|-----------------|
| `DPS_Client_Portal` | `includes/class-dps-client-portal.php` | Coordenador principal (shortcode, auth, formulários, assets) |
| `DPS_Portal_Token_Manager` | `includes/class-dps-portal-token-manager.php` | Geração/validação/revogação de tokens de acesso |
| `DPS_Portal_Session_Manager` | `includes/class-dps-portal-session-manager.php` | Gerenciamento de sessões via transients + cookies |
| `DPS_Portal_Admin_Actions` | `includes/class-dps-portal-admin-actions.php` | Ações admin (gerar token, WhatsApp, email) |
| `DPS_Portal_Cache_Helper` | `includes/class-dps-portal-cache-helper.php` | Cache de seções do portal via transients |
| `DPS_Calendar_Helper` | `includes/class-dps-calendar-helper.php` | Geração de arquivos .ics |
| `DPS_Portal_Profile_Update` | `includes/class-dps-portal-profile-update.php` | Atualização de perfil via link |
| `DPS_Portal_Hub` | `includes/class-dps-portal-hub.php` | Hub administrativo centralizado |

### 1.3 Classes Refatoradas (Fase 3.0.0)
| Classe | Arquivo | Responsabilidade |
|--------|---------|-----------------|
| `DPS_Portal_AJAX_Handler` | `includes/client-portal/class-dps-portal-ajax-handler.php` | Endpoints AJAX (chat, loyalty, acesso, agendamento) |
| `DPS_Portal_Renderer` | `includes/client-portal/class-dps-portal-renderer.php` | Renderização de componentes UI |
| `DPS_Portal_Data_Provider` | `includes/client-portal/class-dps-portal-data-provider.php` | Agregação de dados para o portal |
| `DPS_Portal_Actions_Handler` | `includes/client-portal/class-dps-portal-actions-handler.php` | Processamento de formulários POST |
| `DPS_Portal_Admin` | `includes/client-portal/class-dps-portal-admin.php` | Interface admin, CPT mensagens, metaboxes |
| `DPS_Portal_Pet_History` | `includes/client-portal/class-dps-portal-pet-history.php` | Histórico de serviços por pet |

### 1.4 Repositórios
| Classe | Arquivo |
|--------|---------|
| `DPS_Client_Repository` | `repositories/class-dps-client-repository.php` |
| `DPS_Pet_Repository` | `repositories/class-dps-pet-repository.php` |
| `DPS_Appointment_Repository` | `repositories/class-dps-appointment-repository.php` |
| `DPS_Finance_Repository` | `repositories/class-dps-finance-repository.php` |
| `DPS_Message_Repository` | `repositories/class-dps-message-repository.php` |
| `DPS_Appointment_Request_Repository` | `repositories/class-dps-appointment-request-repository.php` |

### 1.5 Interfaces
- `DPS_Portal_Token_Manager_Interface`
- `DPS_Portal_Session_Manager_Interface`

### 1.6 AJAX Endpoints (14 total)
| Action | Classe | Auth |
|--------|--------|------|
| `dps_chat_get_messages` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_chat_send_message` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_chat_mark_read` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_request_portal_access` | `DPS_Portal_AJAX_Handler` | rate limiting |
| `dps_create_appointment_request` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_loyalty_get_history` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_loyalty_portal_redeem` | `DPS_Portal_AJAX_Handler` | session + nonce |
| `dps_generate_client_token` | `DPS_Portal_Admin_Actions` | manage_options + nonce |
| `dps_revoke_client_tokens` | `DPS_Portal_Admin_Actions` | manage_options + nonce |
| `dps_get_whatsapp_message` | `DPS_Portal_Admin_Actions` | manage_options + nonce |
| `dps_preview_email` | `DPS_Portal_Admin_Actions` | manage_options + nonce |
| `dps_send_email_with_token` | `DPS_Portal_Admin_Actions` | manage_options + nonce |
| `dps_request_access_link_by_email` | `DPS_Client_Portal` | rate limiting |
| `dps_export_pet_history_pdf` | `DPS_Client_Portal` | session + nonce |

### 1.7 Shortcodes
| Shortcode | Classe |
|-----------|--------|
| `[dps_client_portal]` | `DPS_Client_Portal` |
| `[dps_client_login]` | `DPS_Client_Portal` |
| `[dps_profile_update]` | `DPS_Portal_Profile_Update` |

### 1.8 CPTs
| CPT | Registrado em |
|-----|---------------|
| `dps_portal_message` | `DPS_Portal_Admin` |
| `dps_appt_request` | Função global `dps_client_portal_register_appointment_request_cpt()` |

### 1.9 Tabelas Customizadas
| Tabela | Gerenciada por |
|--------|---------------|
| `{prefix}dps_portal_tokens` | `DPS_Portal_Token_Manager` (versão 1.0.0, dbDelta protegido) |

---

## 2. Achados e Correções

### 2.1 Segurança — Corrigidos ✅

#### 2.1.1 `wp_redirect()` sem validação (Severidade: Alta)
- **Arquivo:** `class-dps-client-portal.php:763`
- **Problema:** Uso de `wp_redirect()` em vez de `wp_safe_redirect()` no handler `handle_portal_actions()`. Embora a URL de redirecionamento venha do referer, `wp_safe_redirect()` valida contra hosts permitidos, prevenindo open redirect.
- **Correção:** Substituído por `wp_safe_redirect()`.

#### 2.1.2 `$_POST` não sanitizado passado a hooks (Severidade: Média)
- **Arquivo:** `class-dps-client-portal.php:686,709`
- **Problema:** `do_action('dps_portal_after_update_preferences', $client_id, $_POST)` e `do_action('dps_portal_after_update_pet_preferences', $pet_id, $client_id, $_POST)` passavam `$_POST` cru para callbacks de outros plugins/add-ons, expondo dados potencialmente não sanitizados.
- **Correção:** Substituído por arrays com valores já sanitizados (apenas as chaves relevantes).

#### 2.1.3 Admin notice sem escape (Severidade: Baixa)
- **Arquivo:** `desi-pet-shower-client-portal.php:282`
- **Problema:** `printf()` exibia `$msg['message']` (que contém tags HTML) sem escaping. Embora o conteúdo fosse gerado internamente com `sprintf()`, a falta de `wp_kses_post()` é uma má prática.
- **Correção:** Adicionado `wp_kses_post()` para sanitizar HTML na saída.

### 2.2 Bugs — Corrigidos ✅

#### 2.2.1 Hooks AJAX duplicados (Severidade: Média)
- **Arquivo:** `class-dps-client-portal.php:119-129`
- **Problema:** 8 hooks AJAX (`dps_chat_get_messages`, `dps_chat_send_message`, `dps_chat_mark_read`, `dps_request_portal_access`) estavam registrados tanto em `DPS_Client_Portal` (legacy) quanto em `DPS_Portal_AJAX_Handler` (Fase 3.0.0). Isso causava execução duplicada dos callbacks a cada requisição AJAX desses endpoints.
- **Correção:** Removidos os registros duplicados em `DPS_Client_Portal`, mantendo apenas os de `DPS_Portal_AJAX_Handler` que são a versão refatorada com repositórios e validações mais robustas.

---

## 3. Verificações Positivas ✅

### 3.1 Segurança
- ✅ **Tokens:** Geração segura com `random_bytes(32)`, hash com `password_hash()`, validação com `password_verify()`, rate limiting (5 tentativas/hora/IP), cache negativo
- ✅ **Sessões:** Migrado de `$_SESSION` para transients + cookies com `SameSite=Strict`, `HttpOnly`, `Secure`
- ✅ **Nonces:** Todos os formulários e AJAX usam nonce; validação via `DPS_Request_Validator` ou `check_ajax_referer()`
- ✅ **AJAX Admin:** Todos os handlers admin verificam `manage_options` + nonce
- ✅ **Ownership:** Helper centralizado `dps_portal_assert_client_owns_resource()` com logging de tentativas negadas
- ✅ **Upload:** Validação de extensão, MIME type real via `getimagesize()`, limite de tamanho, `is_uploaded_file()`
- ✅ **Rate Limiting:** Implementado em chat (10 msgs/min), solicitação de acesso (5/hora), validação de token (5/hora)
- ✅ **Sanitização:** `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_email()`, `sanitize_key()` aplicados consistentemente
- ✅ **Escape de saída:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_strip_all_tags()` aplicados na renderização
- ✅ **Segredos:** Tokens nunca armazenados em texto plano; apenas hashes no banco
- ✅ **Uninstall:** `uninstall.php` limpa tabelas, options, transients e cron jobs

### 3.2 Compatibilidade
- ✅ WordPress 6.9+ e PHP 8.4+ declarados
- ✅ `get_page_by_title()` deprecada substituída por `dps_get_page_by_title_compat()` com `$wpdb`
- ✅ Sessões compatíveis com multi-servidor (transients + cookies)
- ✅ Fallbacks para helpers centralizados (`DPS_WhatsApp_Helper`, `DPS_Phone_Helper`, `DPS_Communications_API`)

### 3.3 Arquitetura
- ✅ Padrão Repository para acesso a dados
- ✅ Interfaces para Token Manager e Session Manager (DI)
- ✅ Separação de responsabilidades (Data Provider, Renderer, Actions Handler, AJAX Handler)
- ✅ Cache com invalidação inteligente via hooks de `save_post`
- ✅ Menus e CPTs como submenus de `desi-pet-shower`
- ✅ `dbDelta()` protegido por versão de schema

### 3.4 I18n
- ✅ Text domain `dps-client-portal` carregado em `init` prioridade 1
- ✅ Strings traduzíveis com `__()`, `esc_html__()`, `_n()`

---

## 4. Itens Observados — Resolvidos na Fase 2

### 4.1 CSS migrado para M3 Design Tokens ✅
- **Arquivo:** `assets/css/client-portal.css`
- **Descrição:** CSS migrado de variáveis hardcoded em `:root` para tokens semânticos M3 mapeados no wrapper (`.dps-client-portal`, `.dps-client-portal-access-page`, `.dps-chat-widget`). 251 cores hex substituídas por referências a variáveis. `dps-design-tokens.css` adicionado como dependência no `wp_register_style()`.
- **Status:** ✅ Corrigido na Fase 2

### 4.2 Código legado no coordenador principal (Severidade: Baixa)
- **Arquivo:** `class-dps-client-portal.php` (~5200 linhas)
- **Descrição:** A classe `DPS_Client_Portal` ainda contém métodos legados que já foram refatorados para classes dedicadas (ex: `ajax_get_chat_messages`, `handle_portal_actions` com ~400 linhas de lógica inline). Os métodos legados permanecem como fallback, mas o código novo na `DPS_Portal_Actions_Handler` e `DPS_Portal_AJAX_Handler` é mais limpo.
- **Recomendação:** Progressivamente delegar lógica para as classes refatoradas em fases futuras.

### 4.3 Inline CSS no admin com cores hardcoded ✅
- **Arquivo:** `class-dps-client-portal.php:856-863`
- **Descrição:** `enqueue_message_list_styles()` agora usa tokens M3 com fallbacks hex para compatibilidade (ex: `var(--dps-color-primary, #0b6bcb)`).
- **Status:** ✅ Corrigido na Fase 2

---

## 5. Resumo

| Categoria | Encontrados | Corrigidos | Pendentes |
|-----------|-------------|-----------|-----------|
| Segurança (Alta) | 1 | 1 | 0 |
| Segurança (Média) | 1 | 1 | 0 |
| Segurança (Baixa) | 1 | 1 | 0 |
| Bugs (Média) | 1 | 1 | 0 |
| Compatibilidade (M3) | 2 | 2 | 0 |
| Performance | 0 | 0 | 0 |
| Limpeza | 1 | 0 | 1 |

**Conclusão:** O add-on Portal do Cliente está em bom estado geral. A arquitetura é sólida com padrão Repository, interfaces para DI, e separação de responsabilidades. Segurança é robusta com tokens seguros, rate limiting, validação de ownership, e sanitização consistente. Foram corrigidos 4 achados de segurança/bugs (Fase 1) e 2 itens de compatibilidade M3 (Fase 2: CSS migrado para tokens M3, inline admin CSS com tokens). Resta 1 item de limpeza (código legado no coordenador principal) para fases futuras.
