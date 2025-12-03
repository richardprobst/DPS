# Análise Profunda do Add-on Financeiro (DPS Finance)

**Data da Análise**: 02/12/2025  
**Versão Analisada**: 1.0.0 → **1.2.0** (atualizado)  
**Arquivo Principal**: `desi-pet-shower-finance-addon.php` (~2000 linhas)  
**Arquivos Auxiliares**: `includes/class-dps-finance-api.php` (562 linhas), `includes/class-dps-finance-revenue-query.php` (55 linhas), `includes/class-dps-finance-settings.php` (novo em v1.2.0)  
**Assets**: `assets/css/finance-addon.css`, `assets/js/finance-addon.js` (novos em 1.1.0)

---

## ✅ Melhorias Implementadas

### v1.1.0 - Fase 1 e 2

#### Fase 1 - Quick Wins
- ✅ **Feedback visual após ações** - Mensagens de sucesso/erro usando DPS_Message_Helper
- ✅ **Nonces em links GET sensíveis** - Exclusão e geração de documentos agora verificam nonce
- ✅ **Estilos CSS separados** - Novo arquivo `assets/css/finance-addon.css` com badges de status, cards, responsividade
- ✅ **Scripts JS separados** - Novo arquivo `assets/js/finance-addon.js` (modal de serviços estilizado, confirmação de exclusão)

#### Fase 2 - Usabilidade
- ✅ **Dashboard de resumo financeiro** - Cards mostrando Receitas, Despesas, Pendente e Saldo
- ✅ **Exportação CSV real** - Método `export_transactions_csv()` implementado com filtros
- ✅ **Tabela responsiva** - CSS para layout card em mobile
- ✅ **Paginação de transações** - 20 por página com navegação completa

### v1.2.0 - Fase 3 e 4

#### Fase 3 - Funcionalidades
- ✅ **Histórico de parcelas** - Modal com lista de pagamentos parciais, opção de exclusão
- ✅ **Dados da loja configuráveis** - Nova classe `DPS_Finance_Settings` com options no banco
- ✅ **Mensagens de WhatsApp configuráveis** - Templates com placeholders (`{cliente}`, `{valor}`, etc.)
- ✅ **AJAX handlers para parcelas** - `dps_get_partial_history` e `dps_delete_partial`

#### Fase 4 - Refatoração Técnica
- ✅ **Migração de schema** - Adicionadas colunas `valor_cents` (bigint) em transações e parcelas
- ✅ **Campos de auditoria** - Colunas `created_at` e `updated_at` em ambas as tabelas
- ✅ **Migração automática** - Conversão de valores float para centavos na ativação
- ✅ **Versão do schema** - Controle via `dps_transacoes_db_version` e `dps_parcelas_db_version`

### Segurança Reforçada
- ✅ Nonces em links de exclusão (`dps_finance_delete_{id}`)
- ✅ Nonces em links de geração de documento (`dps_finance_doc_{id}`)
- ✅ Nonces em AJAX de parcelas (`dps_partial_history`, `dps_delete_partial`)
- ✅ Verificação de nonce antes de processar ações GET

---

## Sumário Executivo

O **Finance Add-on** é o núcleo financeiro do sistema DPS, responsável por gerenciar todas as transações, sincronizar cobranças com agendamentos, suportar quitação parcial e fornecer infraestrutura compartilhada para outros add-ons (Pagamentos, Assinaturas, Fidelidade).

### Pontos Fortes
- ✅ Arquitetura modular com API centralizada (`DPS_Finance_API`)
- ✅ Sincronização automática com status de agendamentos
- ✅ Suporte a parcelas e quitação parcial
- ✅ Hook `dps_finance_booking_paid` para integração com outros add-ons
- ✅ Uso consistente de `DPS_Money_Helper` para valores monetários
- ✅ Queries preparadas com `$wpdb->prepare()`
- ✅ Verificação de capabilities em todas as ações
- ✅ **Dashboard de resumo financeiro** (novo em v1.1.0)
- ✅ **Exportação CSV funcional** (novo em v1.1.0)
- ✅ **Interface responsiva** (novo em v1.1.0)
- ✅ **Histórico de parcelas com modal** (novo em v1.2.0)
- ✅ **Configurações de loja via options** (novo em v1.2.0)
- ✅ **Schema com campos de auditoria** (novo em v1.2.0)

### Pontos de Atenção (Menores)
- ⚠️ Alguns métodos ainda longos (ex: `section_financeiro`) - candidatos a refatoração futura
- ⚠️ Coluna `valor` (float) mantida para retrocompatibilidade - usar `valor_cents` para novos desenvolvimentos

---

## 1. Arquitetura e Estrutura de Arquivos

### Estrutura Atual (v1.2.0)

```
desi-pet-shower-finance_addon/
├── desi-pet-shower-finance-addon.php    # Arquivo principal (~2000 linhas)
├── desi-pet-shower-finance.php          # Retrocompatibilidade (27 linhas)
├── includes/
│   ├── class-dps-finance-api.php        # API centralizada (562 linhas)
│   ├── class-dps-finance-revenue-query.php  # Consulta de faturamento (55 linhas)
│   └── class-dps-finance-settings.php   # Configurações (novo em v1.2.0)
├── assets/
│   ├── css/finance-addon.css            # Estilos (novo em v1.1.0)
│   └── js/finance-addon.js              # Scripts (novo em v1.1.0)
├── tests/
│   └── sum-revenue-by-period.test.php   # Teste unitário
├── README.md                            # Documentação
├── finance-notes.md                     # Referência rápida
└── uninstall.php                        # Limpeza na desinstalação
```

### Avaliação da Estrutura

| Aspecto | Status | Observação |
|---------|--------|------------|
| Separação em `includes/` | ✅ Bom | API, queries e settings separadas |
| Arquivo principal | ⚠️ Médio | ~2000 linhas, poderia ser dividido |
| Testes | ✅ Bom | Teste unitário presente |
| Documentação | ✅ Bom | README e notes bem escritos |
| Uninstall | ✅ Bom | Limpa tabelas e options |

### Sugestão de Refatoração Estrutural

```
desi-pet-shower-finance_addon/
├── desi-pet-shower-finance-addon.php    # Bootstrapping apenas (~100 linhas)
├── includes/
│   ├── class-dps-finance-addon.php      # Classe principal refatorada
│   ├── class-dps-finance-api.php        # API (mantém)
│   ├── class-dps-finance-admin.php      # NOVO: Interface administrativa
│   ├── class-dps-finance-actions.php    # NOVO: Handlers de ações
│   ├── class-dps-finance-documents.php  # NOVO: Geração de documentos
│   └── class-dps-finance-revenue-query.php  # Mantém
├── templates/
│   ├── section-financeiro.php           # NOVO: Template da seção
│   └── partials/
│       ├── table-transactions.php       # NOVO: Tabela de transações
│       ├── form-new-transaction.php     # NOVO: Formulário
│       └── summary-pending.php          # NOVO: Resumo de pendências
├── assets/
│   ├── css/
│   │   └── finance-addon.css            # NOVO: Estilos específicos
│   └── js/
│       └── finance-addon.js             # NOVO: Scripts específicos
├── tests/
│   └── ...
└── ...
```

---

## 2. Banco de Dados

### Tabelas Customizadas

#### `{prefix}dps_transacoes`

```sql
CREATE TABLE wp_dps_transacoes (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    cliente_id bigint(20) DEFAULT NULL,
    agendamento_id bigint(20) DEFAULT NULL,
    plano_id bigint(20) DEFAULT NULL,
    data date DEFAULT NULL,
    valor float DEFAULT 0,              -- ⚠️ Deveria ser bigint (centavos)
    categoria varchar(255) NOT NULL DEFAULT '',
    tipo varchar(50) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT '',
    descricao text NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY cliente_id (cliente_id),
    KEY agendamento_id (agendamento_id),
    KEY plano_id (plano_id)
) DEFAULT CHARSET=utf8mb4;
```

#### `{prefix}dps_parcelas`

```sql
CREATE TABLE wp_dps_parcelas (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    trans_id bigint(20) NOT NULL,
    data date NOT NULL,
    valor float NOT NULL,               -- ⚠️ Deveria ser bigint (centavos)
    metodo varchar(50) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY trans_id (trans_id)
) DEFAULT CHARSET=utf8mb4;
```

### Problemas Identificados no Schema

| Problema | Impacto | Sugestão |
|----------|---------|----------|
| `valor float` | Imprecisão em valores monetários | Migrar para `bigint` (centavos) |
| Falta de `created_at` | Sem histórico de criação | Adicionar timestamp |
| Falta de `updated_at` | Sem rastreio de alterações | Adicionar timestamp |
| Falta de `payment_date` | Data de pagamento não registrada | Adicionar coluna |
| Falta de `payment_method` | Método de pagamento não centralizado | Adicionar coluna |

### Options Utilizadas

| Option | Propósito | Valor |
|--------|-----------|-------|
| `dps_transacoes_db_version` | Versão do schema de transações | "1.0.0" |
| `dps_parcelas_db_version` | Versão do schema de parcelas | "1.0.0" |
| `dps_fin_docs_page_id` | ID da página de documentos | int |
| `dps_fin_doc_{trans_id}` | Cache de URL de documento | URL |
| `dps_fin_doc_email_{trans_id}` | Email padrão para envio | email |
| `dps_fin_recurring_{trans_id}` | Flag de recorrência (deprecated) | bool |

---

## 3. Funcionalidades Principais

### 3.1 Registro de Transações

**Fluxo Manual:**
1. Usuário acessa aba "Financeiro" no painel base
2. Preenche formulário: data, valor, categoria, tipo (receita/despesa), status, cliente (opcional), descrição
3. Submit dispara `maybe_handle_finance_actions()`
4. Transação inserida em `dps_transacoes`
5. Redirect para aba financeiro com transação listada

**Fluxo Automático (via agendamento):**
1. Status de agendamento é atualizado (meta `appointment_status`)
2. Hook `updated_post_meta` ou `added_post_meta` dispara
3. Método `sync_status_to_finance()` é executado
4. Transação criada/atualizada com base no status:
   - `finalizado_pago` → status `pago`
   - `finalizado` → status `em_aberto`
   - `cancelado` → status `cancelado`

### 3.2 Pagamentos Parciais

**Fluxo:**
1. Na tabela de transações, link "Registrar" em transações não pagas
2. Formulário de parcela: data, valor, método (PIX/Cartão/Dinheiro/Outro)
3. Parcela inserida em `dps_parcelas`
4. Soma de parcelas comparada ao total da transação
5. Se parcelas >= total, status alterado para "pago"

**Problema identificado:** Interface não exibe histórico de parcelas já registradas.

### 3.3 Geração de Documentos

**Tipos de documentos:**
- **Nota** (status pago): Recibo de pagamento
- **Cobrança** (status em aberto): Boleto/lembrete

**Fluxo:**
1. Clique em link de geração ou envio
2. Método `generate_document()` verifica se documento já existe
3. Se não, gera HTML com dados da transação, cliente, pet, serviços
4. Salva em `wp-content/uploads/dps_docs/`
5. URL armazenada em option `dps_fin_doc_{trans_id}`
6. Pode ser enviado por email

### 3.4 Cobrança via WhatsApp

**Fluxo:**
1. Transações em aberto exibem botão "Cobrar via WhatsApp"
2. Clique gera link wa.me com mensagem pré-formatada
3. Mensagem inclui: nome do cliente, pet, data, valor, chave PIX

**Implementação correta:** Usa `DPS_WhatsApp_Helper` quando disponível, fallback para formatação manual.

### 3.5 Filtros e Relatórios

**Filtros disponíveis:**
- Por período (data inicial e final)
- Por categoria
- Atalhos: últimos 7 dias, últimos 30 dias

**Exportação:** Link "Exportar CSV" presente, mas implementação não visível no código atual.

---

## 4. API Financeira (`DPS_Finance_API`)

### Métodos Públicos

| Método | Parâmetros | Retorno | Uso |
|--------|------------|---------|-----|
| `create_or_update_charge()` | `array $data` | `int\|WP_Error` | Criar/atualizar cobrança |
| `mark_as_paid()` | `int $charge_id, array $options` | `true\|WP_Error` | Marcar como pago |
| `mark_as_pending()` | `int $charge_id` | `true\|WP_Error` | Reabrir cobrança |
| `mark_as_cancelled()` | `int $charge_id, string $reason` | `true\|WP_Error` | Cancelar |
| `get_charge()` | `int $charge_id` | `object\|null` | Buscar cobrança |
| `get_charges_by_appointment()` | `int $appointment_id` | `array` | Listar por agendamento |
| `delete_charges_by_appointment()` | `int $appointment_id` | `int` | Excluir por agendamento |
| `validate_charge_data()` | `array $data` | `true\|WP_Error` | Validar dados |

### Exemplo de Uso

```php
// Criar cobrança via API
$result = DPS_Finance_API::create_or_update_charge([
    'appointment_id' => 123,
    'client_id'      => 456,
    'value_cents'    => 15000, // R$ 150,00
    'status'         => 'pending',
    'services'       => [10, 11],
    'pet_id'         => 789,
]);

if ( is_wp_error( $result ) ) {
    // Tratar erro
} else {
    // $result contém ID da transação
}

// Marcar como pago
DPS_Finance_API::mark_as_paid( $result );
```

### Avaliação da API

| Aspecto | Status | Observação |
|---------|--------|------------|
| Validação de entrada | ✅ Excelente | Validação completa com mensagens descritivas |
| Tratamento de erro | ✅ Bom | Retorna WP_Error com códigos específicos |
| Documentação | ✅ Excelente | DocBlocks completos |
| Hooks de extensão | ✅ Bom | Actions após criar/atualizar/deletar |
| Normalização de status | ✅ Bom | Tradução entre externo (pending) e interno (em_aberto) |

---

## 5. Hooks e Integrações

### Hooks Consumidos

| Hook | Prioridade | Uso |
|------|------------|-----|
| `dps_base_nav_tabs_after_history` | 10 | Adiciona aba "Financeiro" |
| `dps_base_sections_after_history` | 10 | Renderiza seção financeira |
| `dps_finance_cleanup_for_appointment` | 10 | Remove transações ao excluir agendamento |
| `updated_post_meta` | 10 | Sincroniza status ao atualizar agendamento |
| `added_post_meta` | 10 | Sincroniza status ao criar meta de status |

### Hooks Disparados

| Hook | Parâmetros | Propósito |
|------|------------|-----------|
| `dps_finance_booking_paid` | `$appt_id, $client_id, $amount_cents` | Notifica pagamento para Loyalty |
| `dps_finance_charge_created` | `$charge_id, $appointment_id` | Após criar cobrança via API |
| `dps_finance_charge_updated` | `$charge_id, $appointment_id` | Após atualizar cobrança via API |
| `dps_finance_charges_deleted` | `$appointment_id, $count` | Após deletar cobranças |

### Integrações com Outros Add-ons

| Add-on | Tipo | Detalhes |
|--------|------|----------|
| **Loyalty** | Consome hook | `dps_finance_booking_paid` para bonificar pontos |
| **Subscription** | Usa tabela | Cria transações em `dps_transacoes` para assinaturas |
| **Payment** | Atualiza status | Webhooks do Mercado Pago atualizam via API |
| **Services** | Endpoint AJAX | `dps_get_services_details` para exibir serviços |

---

## 6. Análise de Segurança

### Verificações Implementadas

| Verificação | Status | Linhas |
|-------------|--------|--------|
| Nonce em formulários | ✅ | 282, 342, 937, 953 |
| Capability check | ✅ | 284, 329, 344, 378, 391, 457, 499 |
| Sanitização de $_POST | ✅ | 289, 294, 348-356 |
| Sanitização de $_GET | ✅ | 460, 503, 879-884 |
| $wpdb->prepare() | ✅ | 308, 311, 404, 425, 471-474, 541, 772-773 |
| Escape de saída | ✅ | Consistente em toda a interface |

### Possíveis Melhorias de Segurança

| Item | Risco | Sugestão |
|------|-------|----------|
| Exclusão de transação via GET | Médio | Adicionar nonce na URL ou usar POST |
| Geração de documento via GET | Baixo | Adicionar nonce na URL |
| Envio de documento via GET | Baixo | Adicionar nonce na URL |
| Scripts inline | Baixo | Mover para arquivo externo com nonce |

---

## 7. Análise de Performance

### Pontos Positivos

- ✅ Queries com `$wpdb->prepare()` (preparadas)
- ✅ Índices nas colunas de FK (`cliente_id`, `agendamento_id`, `plano_id`)
- ✅ `DPS_Finance_Revenue_Query` usa query agregada em vez de loop PHP
- ✅ Clientes carregados uma única vez no início da seção

### Pontos de Atenção

| Item | Impacto | Sugestão |
|------|---------|----------|
| Loop de `get_post()` em transações | Alto | Pré-carregar clientes e pets em batch |
| Carregamento de todas transações | Alto | Implementar paginação |
| Múltiplos `get_post_meta()` | Médio | Usar `update_meta_cache()` |
| Verificação de documento existente | Baixo | Cache em memória |

### Sugestão de Otimização

```php
// Antes: múltiplos get_post() no loop
foreach ( $trans as $tr ) {
    $cpost = get_post( $tr->cliente_id ); // N queries
}

// Depois: pré-carregar todos os posts
$client_ids = array_unique( array_filter( wp_list_pluck( $trans, 'cliente_id' ) ) );
_prime_post_caches( $client_ids, false, false );

// Agora get_post() usa cache
foreach ( $trans as $tr ) {
    $cpost = get_post( $tr->cliente_id ); // 0 queries
}
```

---

## 8. Análise de Interface (UX/UI)

### Estrutura Atual

A seção financeira é renderizada pelo método `section_financeiro()` (linhas 875-1267) e inclui:

1. **Formulário de pagamento parcial** (condicional)
2. **Formulário de nova transação**
3. **Filtros** (data, categoria, atalhos)
4. **Tabela de transações**
5. **Seção de pendências por cliente**

### Problemas de UX Identificados

| Problema | Impacto | Sugestão |
|----------|---------|----------|
| Formulários inline sem agrupamento | Alto | Usar fieldsets com legends |
| Tabela não paginada | Alto | Implementar paginação (20-50 por página) |
| Sem feedback visual após ações | Médio | Usar `DPS_Message_Helper` |
| Cores de status apenas na tabela | Médio | Adicionar badges/pills |
| Dropdown de status pequeno | Baixo | Aumentar área clicável |
| Muitas colunas na tabela | Médio | Versão compacta para mobile |
| Link "Ver" para serviços usa alert() | Médio | Usar modal estilizado |

### Sugestões de Melhoria Visual

#### 1. Fieldsets Semânticos

```html
<fieldset class="dps-fieldset">
    <legend>Nova Transação</legend>
    <div class="dps-form-grid">
        <div class="dps-field">
            <label>Data</label>
            <input type="date" ...>
        </div>
        <div class="dps-field">
            <label>Valor</label>
            <input type="text" class="dps-input-money" ...>
        </div>
        <!-- ... -->
    </div>
</fieldset>
```

#### 2. Badges de Status

```html
<span class="dps-badge dps-badge-pending">Pendente</span>
<span class="dps-badge dps-badge-paid">Pago</span>
<span class="dps-badge dps-badge-cancelled">Cancelado</span>
```

```css
.dps-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.dps-badge-pending { background: #fef3c7; color: #92400e; }
.dps-badge-paid { background: #d1fae5; color: #065f46; }
.dps-badge-cancelled { background: #fee2e2; color: #991b1b; }
```

#### 3. Cards para Resumo

```html
<div class="dps-finance-cards">
    <div class="dps-card dps-card-revenue">
        <h4>Receitas (30 dias)</h4>
        <span class="dps-card-value">R$ 12.500,00</span>
    </div>
    <div class="dps-card dps-card-expense">
        <h4>Despesas (30 dias)</h4>
        <span class="dps-card-value">R$ 3.200,00</span>
    </div>
    <div class="dps-card dps-card-pending">
        <h4>Pendências</h4>
        <span class="dps-card-value">R$ 2.150,00</span>
    </div>
</div>
```

#### 4. Tabela Responsiva

```css
@media (max-width: 768px) {
    .dps-table-finance {
        display: block;
    }
    .dps-table-finance thead {
        display: none;
    }
    .dps-table-finance tr {
        display: block;
        margin-bottom: 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
    }
    .dps-table-finance td {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
    }
    .dps-table-finance td::before {
        content: attr(data-label);
        font-weight: 600;
    }
}
```

---

## 9. Problemas de Código Identificados

### 9.1 Método `section_financeiro()` Muito Longo

**Problema:** 392 linhas (875-1267) em um único método.

**Sugestão:** Dividir em métodos menores:

```php
private function section_financeiro() {
    ob_start();
    echo '<div class="dps-section" id="dps-section-financeiro">';
    echo '<h3>' . esc_html__( 'Controle Financeiro', 'dps-finance-addon' ) . '</h3>';
    
    $this->render_partial_payment_form();
    $this->render_new_transaction_form();
    $this->render_transactions_filter();
    $this->render_transactions_table();
    $this->render_pending_charges_summary();
    
    echo '</div>';
    return ob_get_clean();
}

private function render_partial_payment_form() { /* ... */ }
private function render_new_transaction_form() { /* ... */ }
private function render_transactions_filter() { /* ... */ }
private function render_transactions_table() { /* ... */ }
private function render_pending_charges_summary() { /* ... */ }
```

### 9.2 Dados da Loja Hardcoded

**Problema:** Linhas 645-648 contêm dados fixos:

```php
$store_name    = 'Banho e Tosa DPS by PRObst';
$store_address = 'Rua Água Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP';
$store_phone   = '15 9 9160-6299';
$store_email   = 'contato@desi.pet';
```

**Sugestão:** Usar options configuráveis:

```php
$store_name    = get_option( 'dps_store_name', 'Nome da Loja' );
$store_address = get_option( 'dps_store_address', '' );
$store_phone   = get_option( 'dps_whatsapp_number', '' );
$store_email   = get_option( 'dps_store_email', get_option( 'admin_email' ) );
```

### 9.3 Valor Float no Banco

**Problema:** Valores armazenados como `float` causam imprecisão.

**Exemplo:**
```php
// Atual (float) - problema de precisão
$valor = 129.90;
$total = $valor * 100;
echo $total; // Resultado: 12989.999999999998 (impreciso!)

// Recomendado (centavos) - sempre exato
$valor_cents = 12990;
$total = $valor_cents;
echo $total; // Resultado: 12990 (exato)
```

**Migração sugerida:**
1. **Backup obrigatório** - Fazer backup completo das tabelas `dps_transacoes` e `dps_parcelas`
2. Criar coluna `valor_cents bigint`
3. Copiar dados: `UPDATE ... SET valor_cents = ROUND(valor * 100)`
4. Validar: conferir que soma de `valor_cents` = soma de `ROUND(valor * 100)` para todas as linhas
5. Alterar código para usar nova coluna
6. Após validação em ambiente de staging, remover coluna `valor`

**Rollback (se necessário):**
- Se algo der errado, restaurar backup das tabelas
- Reverter código para usar coluna `valor` original

### 9.4 Funções Deprecated Ainda Presentes

**Problema:** Funções `dps_parse_money_br()` e `dps_format_money_br()` marcadas como deprecated mas ainda existem.

**Sugestão:** Manter por mais 1-2 versões MINOR, depois remover completamente.

### 9.5 Script Inline para Modal de Serviços

**Problema:** JavaScript inline (linha 1199) dificulta manutenção e CSP.

**Sugestão:** Mover para arquivo `assets/js/finance-addon.js`:

```javascript
(function($) {
    $(document).on('click', '.dps-trans-services', function(e) {
        e.preventDefault();
        var apptId = $(this).data('appt-id');
        
        $.post(dpsFinance.ajaxUrl, {
            action: 'dps_get_services_details',
            appt_id: apptId,
            nonce: dpsFinance.nonce
        }, function(resp) {
            if (resp.success && resp.data.services.length > 0) {
                // Exibir em modal estilizado em vez de alert()
                dpsFinance.showServicesModal(resp.data.services);
            } else {
                alert(resp.data.message || 'Nenhum serviço encontrado.');
            }
        });
    });
})(jQuery);
```

---

## 10. Funcionalidades Sugeridas

### 10.1 Dashboard Financeiro

**Descrição:** Visão geral com métricas e gráficos.

**Componentes:**
- Cards de resumo (receitas, despesas, saldo, pendências)
- Gráfico de receitas x despesas por mês
- Gráfico de distribuição por categoria
- Lista de pendências próximas do vencimento

**Prioridade:** Alta

### 10.2 Paginação de Transações

**Descrição:** Limitar transações por página para melhor performance.

**Implementação:**
```php
$per_page = 20;
$paged = isset( $_GET['fin_page'] ) ? max( 1, intval( $_GET['fin_page'] ) ) : 1;
$offset = ( $paged - 1 ) * $per_page;

$query .= $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );
```

**Prioridade:** Alta

### 10.3 Exportação CSV Real

**Descrição:** Implementar download de CSV com transações filtradas.

**Endpoint sugerido:**
```php
if ( isset( $_GET['dps_fin_export'] ) && '1' === $_GET['dps_fin_export'] ) {
    $this->export_transactions_csv();
    exit;
}

private function export_transactions_csv() {
    // Verificar permissões
    // Buscar transações com filtros atuais
    // Gerar headers de download
    // Escrever linhas CSV
}
```

**Prioridade:** Média

### 10.4 Histórico de Parcelas

**Descrição:** Exibir histórico de pagamentos parciais por transação.

**Interface:**
- Botão "Ver parcelas" em transações parceladas
- Modal com tabela de parcelas (data, valor, método, status)
- Opção de excluir parcela (se erro)

**Prioridade:** Média

### 10.5 Categorias Configuráveis

**Descrição:** Permitir criar/editar categorias de transações.

**Implementação:**
- CPT `dps_finance_category` ou taxonomy
- Metabox no formulário de nova transação
- Gerenciamento nas configurações

**Prioridade:** Baixa

### 10.6 Conciliação Bancária

**Descrição:** Importar extratos e conciliar com transações.

**Funcionalidades:**
- Upload de arquivo OFX/CSV
- Matching automático por valor e data
- Interface de revisão e vinculação manual

**Prioridade:** Baixa (futuro)

### 10.7 Relatórios Gerenciais

**Descrição:** Relatórios detalhados para análise financeira.

**Tipos:**
- DRE simplificado (receitas - despesas = resultado)
- Fluxo de caixa projetado
- Inadimplência por cliente
- Receita por serviço/categoria

**Prioridade:** Baixa (futuro)

---

## 11. Plano de Melhorias Priorizadas

### Fase 1: Quick Wins (1-2 dias)

| Melhoria | Esforço | Impacto |
|----------|---------|---------|
| Adicionar `DPS_Message_Helper` após ações | Baixo | Alto |
| Mover scripts inline para arquivo externo | Baixo | Médio |
| Adicionar nonce em links GET sensíveis | Baixo | Alto |
| Adicionar badges de status na tabela | Baixo | Médio |

### Fase 2: Usabilidade (3-5 dias)

| Melhoria | Esforço | Impacto |
|----------|---------|---------|
| Implementar paginação de transações | Médio | Alto |
| Dividir `section_financeiro()` em métodos menores | Médio | Médio |
| Criar modal estilizado para serviços | Médio | Médio |
| Adicionar fieldsets semânticos aos formulários | Médio | Médio |
| Tornar tabela responsiva para mobile | Médio | Alto |

### Fase 3: Funcionalidades (5-10 dias)

| Melhoria | Esforço | Impacto |
|----------|---------|---------|
| Implementar exportação CSV | Médio | Alto |
| Adicionar dashboard com cards de resumo | Alto | Alto |
| Implementar histórico de parcelas | Médio | Médio |
| Configurar dados da loja via options | Baixo | Médio |

### Fase 4: Refatoração Técnica (5-10 dias)

| Melhoria | Esforço | Impacto |
|----------|---------|---------|
| Migrar valores para centavos (int) | Alto | Alto |
| Reorganizar estrutura de arquivos | Alto | Médio |
| Adicionar campos created_at/updated_at | Médio | Baixo |
| Remover funções deprecated | Baixo | Baixo |

---

## 12. Conclusão

O **Finance Add-on** é um componente bem estruturado e funcional, com boa arquitetura de API e integrações bem definidas. As principais áreas de melhoria são:

1. **Performance**: Implementar paginação e otimizar carregamento de dados
2. **UX**: Modernizar interface com feedback visual e responsividade
3. **Código**: Refatorar métodos longos e migrar valores para centavos
4. **Funcionalidades**: Adicionar dashboard, exportação CSV e histórico de parcelas

As melhorias da Fase 1 e 2 podem ser implementadas rapidamente e trarão impacto significativo na experiência do usuário. As Fases 3 e 4 requerem mais planejamento mas são importantes para a evolução do sistema.

---

## Anexos

### A. Métricas de Código

| Arquivo | Linhas | Métodos | Complexidade |
|---------|--------|---------|--------------|
| desi-pet-shower-finance-addon.php | 1404 | 15 | Alta |
| class-dps-finance-api.php | 562 | 13 | Média |
| class-dps-finance-revenue-query.php | 55 | 1 | Baixa |

### B. Cobertura de Testes

| Área | Cobertura | Observação |
|------|-----------|------------|
| Revenue Query | ✅ Coberto | Teste unitário presente |
| Finance API | ❌ Não coberto | Faltam testes |
| Transações | ❌ Não coberto | Faltam testes |
| Parcelas | ❌ Não coberto | Faltam testes |

### C. Dependências Externas

| Dependência | Obrigatória | Versão |
|-------------|-------------|--------|
| WordPress | Sim | 6.0+ |
| PHP | Sim | 7.4+ |
| DPS Base Plugin | Sim | 1.0+ |
| DPS_Money_Helper | Sim | - |
| DPS_WhatsApp_Helper | Não | Fallback disponível |
