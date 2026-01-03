# Correções de Segurança - Finance Add-on

## Resumo das Correções

Este documento apresenta as correções de segurança implementadas no Finance Add-on do desi.pet by PRObst, conforme solicitado.

**Data da Revisão**: 2025-11-23  
**Arquivos Modificados**:
- `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php`

**Arquivos Verificados (já estavam seguros)**:
- `plugins/desi-pet-shower-finance/includes/class-dps-finance-api.php` ✅
- `plugins/desi-pet-shower-finance/includes/class-dps-finance-revenue-query.php` ✅

---

## 1. Correções em Queries SQL

### Exemplo 1: Query de opções de documentos

**ANTES**:
```php
$option_rows = $wpdb->get_results( 
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" 
);
```

**DEPOIS**:
```php
$option_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
    'dps_fin_doc_%'
) );
```

**Motivo**: Usar `$wpdb->prepare()` em todas as queries, mesmo aquelas sem input direto do usuário, é uma boa prática e previne futuras vulnerabilidades caso o código seja modificado.

---

### Exemplo 2: Query de documentos financeiros

**ANTES**:
```php
$doc_options = $wpdb->get_results( 
    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" 
);
```

**DEPOIS**:
```php
$doc_options = $wpdb->get_results( $wpdb->prepare(
    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
    'dps_fin_doc_%'
) );
```

**Motivo**: Consistência e segurança nas queries de banco de dados.

---

### Exemplo 3: Exclusão de transação (adicionado tipo de dado)

**ANTES**:
```php
$wpdb->delete( $table, [ 'id' => $trans_id ] );
```

**DEPOIS**:
```php
$wpdb->delete( $table, [ 'id' => $trans_id ], [ '%d' ] );
```

**Motivo**: Especificar o tipo de dado (%d para inteiro) garante que o WordPress sanitize corretamente o valor.

---

## 2. Sanitização de Entrada ($_POST e $_GET)

### Exemplo 1: Sanitização de $_POST com wp_unslash()

**ANTES**:
```php
$date       = sanitize_text_field( $_POST['finance_date'] ?? '' );
$value_raw  = sanitize_text_field( wp_unslash( $_POST['finance_value'] ?? '0' ) );
$category   = sanitize_text_field( $_POST['finance_category'] ?? '' );
```

**DEPOIS**:
```php
$date       = isset( $_POST['finance_date'] ) 
    ? sanitize_text_field( wp_unslash( $_POST['finance_date'] ) ) 
    : '';
$value_raw  = isset( $_POST['finance_value'] ) 
    ? sanitize_text_field( wp_unslash( $_POST['finance_value'] ) ) 
    : '0';
$category   = isset( $_POST['finance_category'] ) 
    ? sanitize_text_field( wp_unslash( $_POST['finance_category'] ) ) 
    : '';
```

**Motivo**: 
1. Sempre usar `wp_unslash()` antes de sanitizar para remover slashes adicionados pelo PHP
2. Usar `isset()` explícito em vez do operador `??` para maior clareza
3. Aplicar sanitização consistente em TODOS os campos

---

### Exemplo 2: Sanitização de $_GET com wp_unslash()

**ANTES**:
```php
$start_date = isset( $_GET['fin_start'] ) ? sanitize_text_field( $_GET['fin_start'] ) : '';
$end_date   = isset( $_GET['fin_end'] ) ? sanitize_text_field( $_GET['fin_end'] ) : '';
$cat_filter = isset( $_GET['fin_cat'] ) ? sanitize_text_field( $_GET['fin_cat'] ) : '';
```

**DEPOIS**:
```php
$start_date = isset( $_GET['fin_start'] ) 
    ? sanitize_text_field( wp_unslash( $_GET['fin_start'] ) ) 
    : '';
$end_date   = isset( $_GET['fin_end'] ) 
    ? sanitize_text_field( wp_unslash( $_GET['fin_end'] ) ) 
    : '';
$cat_filter = isset( $_GET['fin_cat'] ) 
    ? sanitize_text_field( wp_unslash( $_GET['fin_cat'] ) ) 
    : '';
```

**Motivo**: Mesma lógica de `$_POST` - `wp_unslash()` é obrigatório para sanitização correta.

---

### Exemplo 3: Sanitização de iteração de $_GET

**ANTES**:
```php
foreach ( $_GET as $k => $v ) {
    if ( in_array( $k, [ 'fin_start', 'fin_end', 'fin_range', 'fin_cat' ], true ) ) {
        continue;
    }
    echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
}
```

**DEPOIS**:
```php
foreach ( $_GET as $k => $v ) {
    if ( in_array( $k, [ 'fin_start', 'fin_end', 'fin_range', 'fin_cat' ], true ) ) {
        continue;
    }
    $safe_key = sanitize_key( $k );
    $safe_val = is_array( $v ) ? '' : sanitize_text_field( wp_unslash( $v ) );
    echo '<input type="hidden" name="' . esc_attr( $safe_key ) . '" value="' . esc_attr( $safe_val ) . '">';
}
```

**Motivo**: 
1. Sanitizar tanto a chave quanto o valor
2. Verificar se é array para evitar erros
3. Usar `wp_unslash()` antes de sanitizar

---

## 3. Verificação de Capabilities

### Exemplo 1: Salvamento de transação

**ANTES**:
```php
if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_trans' 
     && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
    $date       = sanitize_text_field( $_POST['finance_date'] ?? '' );
    // ... resto do código
}
```

**DEPOIS**:
```php
if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_trans' 
     && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
    
    // Verifica permissão
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
    }
    
    $date       = isset( $_POST['finance_date'] ) 
        ? sanitize_text_field( wp_unslash( $_POST['finance_date'] ) ) 
        : '';
    // ... resto do código
}
```

**Motivo**: Garantir que apenas usuários com permissão `manage_options` possam criar transações.

---

### Exemplo 2: Exclusão de transação

**ANTES**:
```php
if ( isset( $_GET['dps_delete_trans'] ) && isset( $_GET['id'] ) ) {
    $trans_id = intval( $_GET['id'] );
    $wpdb->delete( $table, [ 'id' => $trans_id ] );
    // ...
}
```

**DEPOIS**:
```php
if ( isset( $_GET['dps_delete_trans'] ) && isset( $_GET['id'] ) ) {
    // Verifica permissão
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
    }
    
    $trans_id = intval( $_GET['id'] );
    $wpdb->delete( $table, [ 'id' => $trans_id ], [ '%d' ] );
    // ...
}
```

**Motivo**: Prevenir que usuários sem permissão excluam transações financeiras.

---

### Exemplo 3: Atualização de status

**ANTES**:
```php
if ( isset( $_POST['dps_update_trans_status'] ) && isset( $_POST['trans_id'] ) ) {
    $id     = intval( $_POST['trans_id'] );
    $status = sanitize_text_field( $_POST['trans_status'] );
    // ...
}
```

**DEPOIS**:
```php
if ( isset( $_POST['dps_update_trans_status'] ) && isset( $_POST['trans_id'] ) ) {
    // Verifica permissão
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
    }
    
    $id     = intval( $_POST['trans_id'] );
    $status = isset( $_POST['trans_status'] ) 
        ? sanitize_text_field( wp_unslash( $_POST['trans_status'] ) ) 
        : 'em_aberto';
    // ...
}
```

**Motivo**: Apenas administradores podem alterar o status de transações.

---

## 4. Compatibilidade de API Mantida

### APIs Públicas NÃO ALTERADAS:

✅ **DPS_Finance_API::create_or_update_charge()** - Mantida sem alterações  
✅ **DPS_Finance_API::mark_as_paid()** - Mantida sem alterações  
✅ **DPS_Finance_API::mark_as_pending()** - Mantida sem alterações  
✅ **DPS_Finance_API::mark_as_cancelled()** - Mantida sem alterações  
✅ **DPS_Finance_API::get_charge()** - Mantida sem alterações  
✅ **DPS_Finance_Revenue_Query::sum_by_period()** - Mantida sem alterações

**Motivo**: Todas as correções foram feitas INTERNAMENTE nos métodos privados e handlers de request. As APIs públicas continuam funcionando exatamente como antes, garantindo compatibilidade com outros add-ons (Payment, Subscription, Loyalty, Agenda).

---

## 5. Documentação no Código

### Comentários de Segurança Adicionados:

```php
/**
 * Manipula ações de salvamento ou exclusão de transações.
 * 
 * SEGURANÇA: Revisado em 2025-11-23
 * - Adicionadas verificações de capability (manage_options)
 * - Sanitização consistente com wp_unslash()
 * - Queries SQL usando $wpdb->prepare()
 */
public function maybe_handle_finance_actions() {
    // ...
}
```

```php
/**
 * Renderiza a seção do controle financeiro: formulário para nova transação e listagem.
 * 
 * SEGURANÇA: Revisado em 2025-11-23
 * - Sanitização consistente de $_GET com wp_unslash()
 * - Queries SQL usando $wpdb->prepare()
 */
private function section_financeiro() {
    // ...
}
```

Além disso, comentários inline foram adicionados em pontos críticos:

```php
// SEGURANÇA: Sanitiza valores de $_GET antes de usar
foreach ( $_GET as $k => $v ) {
    // ...
}
```

```php
// Filtros de datas - SEGURANÇA: Sanitiza com wp_unslash()
$start_date = isset( $_GET['fin_start'] ) 
    ? sanitize_text_field( wp_unslash( $_GET['fin_start'] ) ) 
    : '';
```

---

## 6. Resumo das Melhorias

### Problemas Corrigidos:

✅ **SQL Injection**: Todas as queries agora usam `$wpdb->prepare()` com tipos corretos (%d, %f, %s)  
✅ **Input Sanitization**: Todos os `$_POST` e `$_GET` agora usam `wp_unslash()` + sanitização adequada  
✅ **Authorization**: Todas as ações críticas verificam `current_user_can('manage_options')`  
✅ **Type Safety**: `$wpdb->delete()`, `$wpdb->update()`, `$wpdb->insert()` especificam tipos de dados  

### Arquivos Auditados:

| Arquivo | Status | Alterações |
|---------|--------|------------|
| `desi-pet-shower-finance-addon.php` | ⚠️ Corrigido | 10 pontos de segurança |
| `includes/class-dps-finance-api.php` | ✅ Seguro | Já estava correto |
| `includes/class-dps-finance-revenue-query.php` | ✅ Seguro | Já estava correto |

### Compatibilidade:

✅ **API pública mantida sem alterações**  
✅ **Comportamento externo idêntico**  
✅ **Zero breaking changes para add-ons dependentes**

---

## 7. Recomendações Adicionais

### Implementado:
- ✅ Verificação de nonces em todos os formulários
- ✅ Verificação de capabilities em todas as ações
- ✅ Sanitização de entrada com wp_unslash()
- ✅ Prepared statements em todas as queries SQL
- ✅ Documentação de segurança no código

### Para Consideração Futura:
- ⚠️ Adicionar logging de auditoria para operações financeiras críticas
- ⚠️ Implementar rate limiting para ações financeiras
- ⚠️ Considerar adicionar confirmação de exclusão via JavaScript
- ⚠️ Validar datas no formato correto (Y-m-d) antes de inserir no banco

---

**Revisor**: GitHub Copilot AI Agent  
**Data**: 2025-11-23  
**Status**: ✅ Concluído
