# Análise de Duplicidade de Código (DRY) - desi.pet by PRObst

## Data da Análise: Janeiro 2026
## Última Atualização: Janeiro 2026

---

## 📊 Sumário Executivo

Este documento apresenta uma análise completa de redundâncias e duplicidade de código no sistema desi.pet by PRObst, seguindo o princípio DRY (Don't Repeat Yourself). A análise abrange o plugin base e todos os 17 add-ons.

### Estatísticas do Sistema
| Métrica | Valor |
|---------|-------|
| Arquivos PHP analisados | 169 |
| Total de linhas de código | ~98.500 |
| Classes DPS_* | 128 → 129 (+DPS_IP_Helper) |
| Add-ons | 17 |
| Helpers existentes | 7 → 8 |

### Resumo das Duplicações Encontradas
| Categoria | Status | Prioridade |
|-----------|--------|------------|
| Obtenção de IP do cliente | ✅ **CORRIGIDO** - DPS_IP_Helper criado | 🔴 Alta |
| Formatação monetária manual | ✅ **CORRIGIDO** - 44 locais migrados | 🔴 Alta |
| Verificação de nonce inline | ⏳ **EM ANDAMENTO** - 88 handlers migrados em 35 arquivos | 🟡 Média |
| Acesso a metadados de cliente | ⏳ Pendente | 🟡 Média |
| Carregamento de text domain | ⚪ Mantido (necessário) | 🟢 Baixa |
| Registro de menu admin | ⚪ Mantido (necessário) | 🟢 Baixa |

---

## ✅ Duplicações Corrigidas

### 1. Funções `get_client_ip()` e `get_client_ip_with_proxy_support()` - **CORRIGIDO**

**Status:** ✅ Implementado em Janeiro 2026

**Solução Implementada:**
Criado `DPS_IP_Helper` em `plugins/desi-pet-shower-base/includes/class-dps-ip-helper.php`

**Métodos disponíveis:**
- `DPS_IP_Helper::get_ip()` - IP simples via REMOTE_ADDR
- `DPS_IP_Helper::get_ip_with_proxy_support()` - IP real através de proxies/CDNs
- `DPS_IP_Helper::get_ip_hash( $salt )` - Hash SHA-256 do IP para rate limiting
- `DPS_IP_Helper::is_valid_ip( $ip )` - Validação IPv4/IPv6
- `DPS_IP_Helper::is_localhost( $ip )` - Detecção de ambiente local
- `DPS_IP_Helper::anonymize( $ip )` - Anonimização para LGPD/GDPR

**Arquivos migrados (8):**
- ✅ `class-dps-client-portal.php` (2 métodos)
- ✅ `class-dps-portal-session-manager.php`
- ✅ `class-dps-portal-token-manager.php`
- ✅ `desi-pet-shower-payment-addon.php`
- ✅ `class-dps-ai-public-chat.php`
- ✅ `class-dps-finance-audit.php`
- ✅ `desi-pet-shower-registration-addon.php`

**Retrocompatibilidade:** Métodos antigos mantidos como wrappers com fallback e marcados como `@deprecated 2.5.0`

---

## ✅ Duplicações de Alta Prioridade (Corrigidas)

### 2. Formatação Monetária Manual (sem DPS_Money_Helper) - **CONCLUÍDO**

**Status:** ✅ Migração concluída - 44 locais migrados, 19 restantes são fallbacks ou casos especiais

**Problema Original:** 63 ocorrências de `number_format(..., 2, ',', '.')` em vez de usar `DPS_Money_Helper`.

**Solução Implementada:**
Adicionados novos métodos ao `DPS_Money_Helper`:
- `format_currency( int $cents, string $symbol = 'R$ ' )` - Para valores em centavos
- `format_currency_from_decimal( float $decimal, string $symbol = 'R$ ' )` - Para valores decimais
- `is_valid_money_string( string $value )` - Validação de strings monetárias

**Migração Realizada:**
- [x] Migrar `desi-pet-shower-subscription` (4 locais)
- [x] Migrar `desi-pet-shower-stats` (12 locais)
- [x] Migrar `desi-pet-shower-ai` (4 locais)
- [x] Migrar `desi-pet-shower-client-portal` (6 locais)
- [x] Migrar `desi-pet-shower-payment` (3 locais)
- [x] Migrar `desi-pet-shower-booking` (1 local)
- [x] Migrar `desi-pet-shower-base` (2 locais)
- [x] Migrar `desi-pet-shower-agenda` (1 local)
- [x] Migrar `desi-pet-shower-push` (6 locais)
- [x] Migrar `desi-pet-shower-services` (4 locais)

**Ocorrências restantes (19):** São fallbacks dentro de `class_exists()` ou casos especiais:
- 2 dentro do próprio DPS_Money_Helper (necessário)
- 1 em refactoring-examples.php (documentação)
- 2 para taxas de câmbio USD/BRL (não é formatação de moeda BRL)
- 14 fallbacks em class_exists() (boas práticas de retrocompatibilidade)

---

## 🟡 Duplicações de Média Prioridade

### 3. Verificação de Nonce Inline - **EM ANDAMENTO**

**Status:** ⏳ 88 handlers migrados em 35 arquivos

**Problema:** 161 ocorrências de verificação de nonce com padrões similares, quando poderia usar `DPS_Request_Validator`.

**Solução Implementada:**
Expandido `DPS_Request_Validator` com novos métodos:
- `verify_ajax_nonce( $action, $nonce_field = 'nonce' )` - AJAX público/portal
- `verify_ajax_admin( $action, $capability = 'manage_options' )` - AJAX admin
- `verify_admin_action( $action, $capability, $nonce_field = '_wpnonce' )` - GET actions
- `verify_admin_form( $action, $nonce_field, $capability )` - POST forms
- `verify_dynamic_nonce( $prefix, $id, $method = 'GET' )` - Nonces com ID dinâmico

**Métodos auxiliares:**
- `send_json_error()`, `send_json_success()` - Respostas JSON padronizadas
- `get_get_int()`, `get_get_string()` - Getters GET sanitizados

**Exemplo de migração:**
```php
// ❌ Código antigo (repetido ~50 vezes):
if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_action' ) ) {
    wp_send_json_error( [ 'message' => 'Acesso negado.' ] );
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( [ 'message' => 'Sem permissão.' ] );
}

// ✅ Novo padrão (1 linha):
if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_action' ) ) {
    return;
}
```

**Add-ons migrados:**
| Add-on | Handlers |
|--------|----------|
| desi-pet-shower-ai | 18 |
| desi-pet-shower-client-portal | 12 |
| desi-pet-shower-groomers | 7 |
| desi-pet-shower-subscription | 8 |
| desi-pet-shower-loyalty | 4 |
| desi-pet-shower-finance | 10 |
| desi-pet-shower-base | 7 |
| desi-pet-shower-stats | 6 |
| desi-pet-shower-agenda | 3 |
| desi-pet-shower-backup | 4 |
| desi-pet-shower-payment | 3 |
| desi-pet-shower-push | 3 |
| desi-pet-shower-booking | 3 |
| desi-pet-shower-stock | 1 |
| desi-pet-shower-registration | 1 |
| desi-pet-shower-services | 1 |
| desi-pet-shower-communications | 1 |
| **Total** | **88** |

---

### 4. Acesso Direto a Metadados de Cliente

**Problema:** 30+ locais acessando `client_phone`, `client_email` diretamente via `get_post_meta()`.

**Código repetido:**
```php
// ❌ Padrão repetido em 30+ lugares:
$phone = get_post_meta( $client_id, 'client_phone', true );
$email = get_post_meta( $client_id, 'client_email', true );
```

**Solução Proposta:**
Usar `DPS_Client_Repository` (já existe em `class-dps-client-repository.php`) em mais lugares, ou criar um helper de dados de cliente:

```php
class DPS_Client_Helper {
    public static function get_contact_data( $client_id ): array {
        return [
            'name'  => get_the_title( $client_id ),
            'phone' => get_post_meta( $client_id, 'client_phone', true ),
            'email' => get_post_meta( $client_id, 'client_email', true ),
        ];
    }
    
    public static function get_full_data( $client_id ): array {
        // Todos os metadados do cliente
    }
}
```

---

### 5. Verificações `class_exists()` Repetidas

**Problema:** 30+ verificações de `class_exists( 'DPS_*_Helper' )` antes de usar helpers.

**Padrão repetido:**
```php
// ❌ Padrão repetido:
if ( class_exists( 'DPS_Money_Helper' ) ) {
    $formatted = DPS_Money_Helper::format_to_brazilian( $value );
} else {
    $formatted = number_format( $value / 100, 2, ',', '.' );
}
```

**Solução Proposta:**
1. Os helpers do plugin base devem ser carregados antes dos add-ons (já garantido pela prioridade de `init`)
2. Documentar que add-ons podem assumir que helpers existem se `DPS_Base_Plugin` existe
3. Criar um único wrapper de verificação se necessário

---

## 🟢 Duplicações de Baixa Prioridade

### 6. Carregamento de Text Domain

**Problema:** 16 add-ons com código idêntico para carregar text domain.

**Padrão repetido:**
```php
function dps_{addon}_load_textdomain() {
    load_plugin_textdomain( 'dps-{addon}-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_{addon}_load_textdomain', 1 );
```

**Análise:** Embora seja código repetido, cada add-on precisa carregar seu próprio text domain. Isso é uma duplicação necessária e não deve ser consolidada.

**Recomendação:** Manter como está. Documentar o padrão no AGENTS.md para novos add-ons.

---

### 7. Registro de Menu Admin

**Problema:** 31 registros de `add_submenu_page()` com padrões similares.

**Padrão comum:**
```php
add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
// ...
add_submenu_page( 'desi-pet-shower', ... );
```

**Análise:** Cada add-on/componente precisa registrar seus próprios menus. A estrutura é similar mas não idêntica.

**Recomendação:** Considerar criar um helper de registro de menu para padronizar:
```php
DPS_Admin_Menu_Helper::register_submenu( [
    'title' => 'Minha Página',
    'capability' => 'manage_options',
    'callback' => [ $this, 'render_page' ],
] );
```

---

## 📈 Métricas de Utilização dos Helpers Existentes

| Helper | Usos Atuais | Potencial de Uso | Status |
|--------|-------------|------------------|--------|
| DPS_Money_Helper | 94 | ~157 | ⏳ `format_currency()` adicionado |
| DPS_IP_Helper | 8 | 8 | ✅ **NOVO** - Consolidado |
| DPS_Phone_Helper | 24 | ~30 | ✅ Bom uso |
| DPS_WhatsApp_Helper | 26 | ~30 | ✅ Bom uso |
| DPS_URL_Builder | 30 | ~50 | ⏳ 20 locais não usando |
| DPS_Request_Validator | 47 | ~161 | ⏳ **EXPANDIDO** - 40 handlers migrados |
| DPS_Query_Helper | 7 | ~50 | ⏳ 43 locais não usando |
| DPS_Message_Helper | 252 | ~260 | ✅ Excelente uso |

---

## 🎯 Plano de Correções por Fases

### Fase 1: Criar Novo Helper e Consolidar IP (Prioridade Alta) - ✅ CONCLUÍDA
**Esforço:** 2-3 horas | **Risco:** Baixo | **Impacto:** Alto

**Resultado:**
- ✅ Criado `class-dps-ip-helper.php` com 8 métodos
- ✅ Migradas 8 implementações para usar o helper
- ✅ Retrocompatibilidade mantida com fallback
- ✅ Documentação atualizada no ANALYSIS.md

---

### Fase 2: Consolidar Formatação Monetária (Prioridade Alta) - ✅ CONCLUÍDA
**Esforço:** 3-4 horas | **Risco:** Médio | **Impacto:** Alto

**Resultado:**
- ✅ Adicionado método `format_currency()` ao DPS_Money_Helper
- ✅ Adicionado método `format_currency_from_decimal()` ao DPS_Money_Helper
- ✅ Adicionado método `is_valid_money_string()` ao DPS_Money_Helper
- ✅ Migrados 44 locais com `number_format` manual
- ✅ 19 ocorrências restantes são fallbacks ou casos especiais

**Add-ons migrados:**
- desi-pet-shower-subscription (4 locais)
- desi-pet-shower-stats (12 locais)
- desi-pet-shower-ai (4 locais)
- desi-pet-shower-client-portal (6 locais)
- desi-pet-shower-payment (3 locais)
- desi-pet-shower-booking (1 local)
- desi-pet-shower-base (2 locais)
- desi-pet-shower-agenda (1 local)
- desi-pet-shower-push (6 locais)
- desi-pet-shower-services (4 locais)

---

### Fase 3: Expandir DPS_Request_Validator (Prioridade Média) - ⏳ EM ANDAMENTO
**Esforço:** 4-5 horas | **Risco:** Médio | **Impacto:** Alto

**Progresso:**
- ✅ Adicionados 5 métodos novos ao `DPS_Request_Validator`:
  - `verify_ajax_nonce()` - AJAX público
  - `verify_ajax_admin()` - AJAX admin
  - `verify_admin_action()` - GET actions
  - `verify_admin_form()` - POST forms
  - `verify_dynamic_nonce()` - Nonces com ID
- ✅ Adicionados métodos auxiliares `send_json_error()`, `send_json_success()`
- ✅ Adicionados getters `get_get_int()`, `get_get_string()`
- ✅ Migrados **40 handlers** em 22 arquivos
- [ ] Migrar restante das ~50 ocorrências (próximas iterações)

**Arquivos migrados:**
- `desi-pet-shower-ai-addon.php` (6 handlers)
- `class-dps-ai-analytics.php` (1 handler)
- `class-dps-ai-scheduler.php` (2 handlers)
- `class-dps-ai-maintenance.php` (1 handler)
- `class-dps-ai-integration-portal.php` (1 handler)
- `class-dps-ai-knowledge-base.php` (1 handler)
- `class-dps-ai-public-chat.php` (2 handlers)
- `class-dps-ai-knowledge-base-admin.php` (1 handler)
- `class-dps-ai-knowledge-base-tester.php` (1 handler)
- `class-dps-portal-ajax-handler.php` (3 handlers)
- `desi-pet-shower-finance-addon.php` (3 handlers)
- `desi-pet-shower-base.php` (2 handlers)
- `class-dps-base-frontend.php` (4 handlers)
- `class-dps-github-updater.php` (1 handler)
- `class-dps-client-portal.php` (2 handlers)
- `desi-pet-shower-backup-addon.php` (4 handlers)
- `class-dps-groomer-session-manager.php` (1 handler)
- `desi-pet-shower-stock.php` (1 handler)
- `desi-pet-shower-agenda-addon.php` (1 handler)
- `desi-pet-shower-registration-addon.php` (1 handler)
- `desi-pet-shower-services-addon.php` (1 handler)
- `desi-pet-shower-communications-addon.php` (1 handler)

---

### Fase 4: Centralizar Acesso a Dados de Cliente (Prioridade Média)
**Esforço:** 3-4 horas | **Risco:** Baixo | **Impacto:** Médio

**Tarefas:**
1. Criar `DPS_Client_Helper` ou expandir `DPS_Client_Repository`
2. Adicionar métodos:
   - `get_contact_data( $client_id )`
   - `get_full_data( $client_id )`
   - `get_client_name( $client_id )`
3. Migrar os 30+ locais de acesso direto
4. Usar cache de metadados quando apropriado

---

### Fase 5: Expandir DPS_Query_Helper (Prioridade Baixa)
**Esforço:** 2-3 horas | **Risco:** Baixo | **Impacto:** Médio

**Tarefas:**
1. Adicionar métodos especializados:
   - `get_clients_by_status( $status )`
   - `get_pets_by_owner( $client_id )`
   - `get_appointments_by_date_range( $start, $end )`
2. Implementar cache automático para queries frequentes
3. Migrar queries repetidas nos add-ons

---

## 📋 Checklist de Implementação

### Fase 1 - DPS_IP_Helper ✅ CONCLUÍDA
- [x] Criar arquivo `class-dps-ip-helper.php`
- [x] Implementar `get_ip()` (simples)
- [x] Implementar `get_ip_with_proxy_support()` (com headers)
- [x] Implementar `get_ip_hash()` (para rate limiting)
- [x] Implementar `is_valid_ip()`, `is_localhost()`, `anonymize()`
- [x] Adicionar require no `desi-pet-shower-base.php`
- [x] Atualizar `class-dps-client-portal.php` (2 métodos)
- [x] Atualizar `class-dps-portal-session-manager.php`
- [x] Atualizar `class-dps-portal-token-manager.php`
- [x] Atualizar `desi-pet-shower-payment-addon.php`
- [x] Atualizar `class-dps-ai-public-chat.php`
- [x] Atualizar `class-dps-finance-audit.php`
- [x] Atualizar `desi-pet-shower-registration-addon.php`
- [x] Atualizar ANALYSIS.md com novo helper

### Fase 2 - Formatação Monetária ✅ CONCLUÍDA
- [x] Adicionar `format_currency()` ao DPS_Money_Helper
- [x] Adicionar `format_currency_from_decimal()` ao DPS_Money_Helper
- [x] Adicionar `is_valid_money_string()` ao DPS_Money_Helper
- [x] Migrar desi-pet-shower-subscription (4 locais)
- [x] Migrar desi-pet-shower-stats (12 locais)
- [x] Migrar desi-pet-shower-ai (4 locais)
- [x] Migrar desi-pet-shower-client-portal (6 locais)
- [x] Migrar desi-pet-shower-payment (3 locais)
- [x] Migrar desi-pet-shower-booking (1 local)
- [x] Migrar desi-pet-shower-base (2 locais)
- [x] Migrar desi-pet-shower-agenda (1 local)
- [x] Migrar desi-pet-shower-push (6 locais)
- [x] Migrar desi-pet-shower-services (4 locais)
- [x] Atualizar relatório de análise

### Fase 3 - Request Validator ⏳ EM ANDAMENTO
- [x] Adicionar método `verify_ajax_nonce()`
- [x] Adicionar método `verify_ajax_admin()`
- [x] Adicionar método `verify_admin_action()`
- [x] Adicionar método `verify_admin_form()`
- [x] Adicionar método `verify_dynamic_nonce()`
- [x] Adicionar métodos auxiliares `send_json_error()`, `send_json_success()`
- [x] Adicionar getters `get_get_int()`, `get_get_string()`
- [x] Migrar desi-pet-shower-ai-addon.php (6 handlers)
- [x] Migrar class-dps-ai-analytics.php (1 handler)
- [x] Migrar class-dps-ai-scheduler.php (2 handlers)
- [x] Migrar class-dps-ai-maintenance.php (1 handler)
- [x] Migrar class-dps-ai-integration-portal.php (1 handler)
- [x] Migrar class-dps-ai-knowledge-base.php (1 handler)
- [x] Migrar class-dps-ai-public-chat.php (2 handlers)
- [x] Migrar class-dps-ai-knowledge-base-admin.php (1 handler)
- [x] Migrar class-dps-ai-knowledge-base-tester.php (1 handler)
- [x] Migrar class-dps-portal-ajax-handler.php (3 handlers)
- [x] Migrar desi-pet-shower-finance-addon.php (3 handlers)
- [x] Migrar desi-pet-shower-base.php (2 handlers: export_clients, export_pets)
- [x] Migrar class-dps-base-frontend.php (4 handlers: handle_form, logout, history, delete_doc)
- [x] Migrar class-dps-github-updater.php (1 handler: force_update_check)
- [x] Migrar class-dps-client-portal.php (2 handlers: chat, export_pdf)
- [x] Migrar desi-pet-shower-backup-addon.php (4 handlers: settings, export, download, import)
- [x] Migrar class-dps-groomer-session-manager.php (1 handler: logout)
- [x] Migrar desi-pet-shower-stock.php (1 handler: save_meta)
- [x] Migrar desi-pet-shower-agenda-addon.php (1 handler: export_pdf)
- [x] Migrar desi-pet-shower-registration-addon.php (1 handler: save_registration)
- [x] Migrar desi-pet-shower-services-addon.php (1 handler: save_service)
- [x] Migrar desi-pet-shower-communications-addon.php (1 handler: save_settings)
- [ ] Migrar restante (~50+ ocorrências)
- [x] Atualizar documentação ANALYSIS.md

### Fase 4 - Client Helper
- [ ] Criar/expandir helper
- [ ] Adicionar métodos de acesso
- [ ] Migrar locais de acesso direto

### Fase 5 - Query Helper
- [ ] Adicionar métodos especializados
- [ ] Implementar cache
- [ ] Migrar queries

---

## 📝 Notas Adicionais

### Boas Práticas Identificadas
1. **DPS_Message_Helper**: Excelente adoção (252 usos), modelo a seguir
2. **DPS_Money_Helper**: Boa adoção mas precisa de consolidação
3. **Traits**: Uso bem-sucedido no add-on Agenda (`DPS_Agenda_Renderer`, `DPS_Agenda_Query`)

### Padrões a Evitar
1. Duplicar funções de IP em cada add-on
2. Usar `number_format` diretamente quando existe helper
3. Verificar nonce inline sem usar validator
4. Acessar metadados diretamente sem cache

### Retrocompatibilidade
Ao migrar para helpers centralizados:
1. Manter métodos antigos como wrappers por 1-2 versões
2. Marcar como `@deprecated` com versão de remoção
3. Logar uso de métodos deprecated em modo debug
4. Documentar migração no CHANGELOG.md

---

## 🔗 Referências

- `docs/refactoring/REFACTORING_ANALYSIS.md` - Análise de funções grandes
- `plugins/desi-pet-shower-base/includes/refactoring-examples.php` - Exemplos de refatoração
- `ANALYSIS.md` - Documentação dos helpers existentes
- `AGENTS.md` - Diretrizes de desenvolvimento
