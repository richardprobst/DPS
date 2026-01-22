# Análise de Duplicidade de Código (DRY) - desi.pet by PRObst

## Data da Análise: Janeiro 2026

---

## 📊 Sumário Executivo

Este documento apresenta uma análise completa de redundâncias e duplicidade de código no sistema desi.pet by PRObst, seguindo o princípio DRY (Don't Repeat Yourself). A análise abrange o plugin base e todos os 17 add-ons.

### Estatísticas do Sistema
| Métrica | Valor |
|---------|-------|
| Arquivos PHP analisados | 169 |
| Total de linhas de código | ~98.500 |
| Classes DPS_* | 128 |
| Add-ons | 17 |
| Helpers existentes | 7 |

### Resumo das Duplicações Encontradas
| Categoria | Ocorrências | Prioridade |
|-----------|-------------|------------|
| Obtenção de IP do cliente | 6 implementações | 🔴 Alta |
| Formatação monetária manual | 63 locais | 🔴 Alta |
| Verificação de nonce inline | 161 ocorrências | 🟡 Média |
| Acesso a metadados de cliente | 30+ locais | 🟡 Média |
| Carregamento de text domain | 16 padrões idênticos | 🟢 Baixa |
| Registro de menu admin | 31 registros | 🟢 Baixa |

---

## 🔴 Duplicações de Alta Prioridade

### 1. Funções `get_client_ip()` e `get_client_ip_with_proxy_support()`

**Problema:** 6 implementações diferentes da mesma funcionalidade espalhadas pelo código.

**Localizações:**
| Arquivo | Método | Linhas |
|---------|--------|--------|
| `class-dps-client-portal.php` | `get_client_ip()` | 4482-4487 |
| `class-dps-client-portal.php` | `get_client_ip_with_proxy_support()` | 5215-5240 |
| `class-dps-portal-session-manager.php` | `get_client_ip()` | 320-330 |
| `class-dps-portal-token-manager.php` | `get_client_ip_with_proxy_support()` | 360-395 |
| `desi-pet-shower-payment-addon.php` | `get_client_ip()` | 1183-1195 |
| `class-dps-ai-public-chat.php` | `get_client_ip()` | 789-800 |
| `class-dps-finance-audit.php` | `get_client_ip()` | 89-100 |
| `desi-pet-shower-registration-addon.php` | `get_client_ip_hash()` | 260-280 |

**Solução Proposta:**
Criar `DPS_IP_Helper` no plugin base com métodos:
```php
class DPS_IP_Helper {
    public static function get_ip(): string;
    public static function get_ip_with_proxy_support(): string;
    public static function get_ip_hash(): string;
    public static function is_valid_ip( string $ip ): bool;
}
```

**Impacto:** 8 arquivos, ~150 linhas de código redundante.

---

### 2. Formatação Monetária Manual (sem DPS_Money_Helper)

**Problema:** 63 ocorrências de `number_format(..., 2, ',', '.')` em vez de usar `DPS_Money_Helper`.

**Exemplos de código duplicado:**
```php
// ❌ Código atual (repetido 63 vezes):
echo 'R$ ' . number_format( $valor, 2, ',', '.' );
echo 'R$ ' . number_format( (float) $price, 2, ',', '.' );

// ✅ Deveria usar:
echo 'R$ ' . DPS_Money_Helper::format_to_brazilian( $valor_centavos );
```

**Add-ons afetados:**
- `desi-pet-shower-subscription` (6 ocorrências)
- `desi-pet-shower-client-portal` (25 ocorrências)
- `desi-pet-shower-stock` (2 ocorrências)
- `desi-pet-shower-finance` (estimado 10+ ocorrências)
- `desi-pet-shower-loyalty` (estimado 5+ ocorrências)

**Solução Proposta:**
1. Verificar se todos os valores são armazenados em centavos
2. Substituir todas as ocorrências por `DPS_Money_Helper::format_to_brazilian()`
3. Adicionar método utilitário `format_currency()` que já inclui "R$ "

**Impacto:** 63 locais em 8+ arquivos.

---

## 🟡 Duplicações de Média Prioridade

### 3. Verificação de Nonce Inline

**Problema:** 161 ocorrências de verificação de nonce com padrões similares, quando poderia usar `DPS_Request_Validator`.

**Padrões repetidos:**
```php
// ❌ Padrão 1 (mais comum):
if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_action' ) ) {
    wp_die( 'Acesso negado.' );
}

// ❌ Padrão 2:
if ( ! wp_verify_nonce( $nonce, 'dps_some_action_' . $id ) ) {
    return false;
}

// ✅ Deveria usar:
if ( ! DPS_Request_Validator::verify_nonce_and_capability( 'dps_nonce', 'manage_options' ) ) {
    wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
}
```

**Localizações principais:**
- `class-dps-base-frontend.php` (12 ocorrências)
- `class-dps-client-portal.php` (15 ocorrências)
- `class-dps-portal-admin-actions.php` (8 ocorrências)
- `desi-pet-shower-subscription-addon.php` (6 ocorrências)
- Outros add-ons (~120 ocorrências totais)

**Solução Proposta:**
1. Expandir `DPS_Request_Validator` com métodos especializados:
   - `verify_ajax_nonce( $action )`
   - `verify_admin_action( $action, $capability )`
   - `verify_frontend_action( $action )`
2. Criar wrapper que retorna resposta JSON padronizada para AJAX

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

| Helper | Usos Atuais | Potencial de Uso | Gap |
|--------|-------------|------------------|-----|
| DPS_Money_Helper | 94 | ~157 | 63 locais não usando |
| DPS_Phone_Helper | 24 | ~30 | Bom uso |
| DPS_WhatsApp_Helper | 26 | ~30 | Bom uso |
| DPS_URL_Builder | 30 | ~50 | 20 locais não usando |
| DPS_Request_Validator | 11 | ~161 | 150 locais não usando |
| DPS_Query_Helper | 7 | ~50 | 43 locais não usando |
| DPS_Message_Helper | 252 | ~260 | Excelente uso |

---

## 🎯 Plano de Correções por Fases

### Fase 1: Criar Novo Helper e Consolidar IP (Prioridade Alta)
**Esforço:** 2-3 horas | **Risco:** Baixo | **Impacto:** Alto

**Tarefas:**
1. Criar `class-dps-ip-helper.php` no plugin base
2. Migrar todas as 6 implementações de `get_client_ip()` para usar o helper
3. Manter métodos antigos como wrappers (retrocompatibilidade)
4. Testar em cada add-on afetado

**Arquivos a modificar:**
- `plugins/desi-pet-shower-base/includes/class-dps-ip-helper.php` (novo)
- `plugins/desi-pet-shower-base/desi-pet-shower-base.php` (require)
- 6-8 arquivos para atualizar chamadas

---

### Fase 2: Consolidar Formatação Monetária (Prioridade Alta)
**Esforço:** 3-4 horas | **Risco:** Médio | **Impacto:** Alto

**Tarefas:**
1. Auditar todos os 63 locais com `number_format` manual
2. Verificar se valores estão em centavos (padrão do sistema) ou reais
3. Substituir por `DPS_Money_Helper::format_to_brazilian()`
4. Adicionar método `format_currency()` que já inclui "R$ "
5. Testar renderização de valores em todas as telas

**Riscos:**
- Valores podem estar em formatos diferentes (reais vs centavos)
- Necessário testar cada tela visualmente

---

### Fase 3: Expandir DPS_Request_Validator (Prioridade Média)
**Esforço:** 4-5 horas | **Risco:** Médio | **Impacto:** Alto

**Tarefas:**
1. Adicionar métodos especializados ao `DPS_Request_Validator`:
   - `verify_ajax_request( $action, $capability = null )`
   - `verify_admin_page_access( $capability )`
   - `wp_die_unauthorized()`
2. Criar wrappers para respostas AJAX padronizadas
3. Migrar progressivamente as 150+ ocorrências
4. Documentar padrão no AGENTS.md

**Abordagem recomendada:** Migrar por add-on, começando pelos mais usados.

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

### Fase 1 - DPS_IP_Helper
- [ ] Criar arquivo `class-dps-ip-helper.php`
- [ ] Implementar `get_ip()` (simples)
- [ ] Implementar `get_ip_with_proxy_support()` (com headers)
- [ ] Implementar `get_ip_hash()` (para rate limiting)
- [ ] Adicionar require no `desi-pet-shower-base.php`
- [ ] Atualizar `class-dps-client-portal.php`
- [ ] Atualizar `class-dps-portal-session-manager.php`
- [ ] Atualizar `class-dps-portal-token-manager.php`
- [ ] Atualizar `desi-pet-shower-payment-addon.php`
- [ ] Atualizar `class-dps-ai-public-chat.php`
- [ ] Atualizar `class-dps-finance-audit.php`
- [ ] Atualizar `desi-pet-shower-registration-addon.php`
- [ ] Testar todas as funcionalidades afetadas
- [ ] Atualizar ANALYSIS.md com novo helper

### Fase 2 - Formatação Monetária
- [ ] Listar todos os 63 locais
- [ ] Categorizar por formato (centavos vs reais)
- [ ] Adicionar `format_currency()` ao DPS_Money_Helper
- [ ] Migrar add-on por add-on
- [ ] Testar renderização visual

### Fase 3 - Request Validator
- [ ] Adicionar métodos especializados
- [ ] Criar helper de resposta AJAX
- [ ] Migrar plugin base
- [ ] Migrar add-ons gradualmente

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
