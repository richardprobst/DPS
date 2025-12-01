# Diagnóstico: Reorganização Arquitetural Finance ⇄ Agenda

**Data**: 2025-11-22  
**Versão**: 1.0  
**Objetivo**: Centralizar lógica financeira no Finance Add-on e eliminar duplicações com Agenda Add-on

---

## 1. DIAGNÓSTICO REAL DOS PROBLEMAS ENCONTRADOS

### 1.1 Arquivos Duplicados

#### ✅ **Finance Add-on: NÃO há duplicação real de plugin**

Análise dos arquivos:

**`desi-pet-shower-finance-addon.php` (72.669 bytes)**:
- ✅ Header de plugin WordPress completo (linhas 2-13)
- ✅ Versão: 1.0.0
- ✅ Define classe `DPS_Finance_Addon`
- ✅ Instancia a classe em `$GLOBALS['dps_finance_addon']`
- ✅ Este é o arquivo principal CORRETO

**`desi-pet-shower-finance.php` (952 bytes)**:
- ✅ SEM header de plugin (apenas comentários explicativos)
- ✅ Arquivo de compatibilidade retroativa explícito
- ✅ Inclui `desi-pet-shower-finance-addon.php` se necessário
- ✅ Instancia a classe se ainda não existir
- ✅ **CONCLUSÃO: Este arquivo está CORRETO como está**

**Veredicto**: ❌ **NÃO existe problema de duplicação** no Finance Add-on. O arquivo `desi-pet-shower-finance.php` é intencional e bem documentado como arquivo de compatibilidade, SEM cabeçalho de plugin. O README.md confirma esta arquitetura (linhas 19-26).

**Ação**: ✅ **NENHUMA**. Manter ambos os arquivos como estão.

---

### 1.2 Funções Duplicadas

#### ❌ **Problema CONFIRMADO: Funções monetárias duplicadas**

**`dps_parse_money_br()` e `dps_format_money_br()` duplicadas em 2 add-ons**:

| Add-on | Arquivo | Linhas | Funções |
|--------|---------|--------|---------|
| Finance | `desi-pet-shower-finance-addon.php` | 36-73 | `dps_parse_money_br()`, `dps_format_money_br()` |
| Loyalty | `desi-pet-shower-loyalty.php` | 966+ | `dps_format_money_br()` |

**Impacto**:
- ❌ Código duplicado (2 implementações da mesma lógica)
- ❌ Risco de inconsistência se uma for atualizada e outra não
- ❌ Ignora helper oficial `DPS_Money_Helper` do núcleo

**Helper oficial disponível (NÃO está sendo usado)**:
```php
// plugin/desi-pet-shower-base_plugin/includes/class-dps-money-helper.php
DPS_Money_Helper::parse_brazilian_format( $str )  // equivale a dps_parse_money_br()
DPS_Money_Helper::format_to_brazilian( $cents )   // equivale a dps_format_money_br()
```

**Uso atual no Finance**:
- 11 ocorrências de `dps_format_money_br()`
- 3 ocorrências de `dps_parse_money_br()`

**Uso atual no Loyalty**:
- 2 ocorrências de `dps_format_money_br()`

**Ação necessária**: 
1. Substituir todas as chamadas por `DPS_Money_Helper`
2. Remover funções duplicadas após substituição
3. Depreciar funções globais (manter com `_deprecated_function()` por 1 versão)

---

### 1.3 Lógica Financeira Duplicada

#### ❌ **Problema CONFIRMADO: Agenda manipula tabela financeira diretamente**

**Agenda Add-on manipula `dps_transacoes` em 2 locais**:

**Loc 1: `render_charges_notes_shortcode()` (linhas 821-845)**:
```php
$table = $wpdb->prefix . 'dps_transacoes';
$rows = $wpdb->get_results( "SELECT * FROM $table WHERE tipo = 'receita' ORDER BY data DESC" );
// Renderiza tabela de cobranças
```
- ❌ Consulta direta na tabela financeira
- ❌ Shortcode `[dps_charges_notes]` deveria estar no Finance
- ❌ Duplica responsabilidade de listar transações

**Loc 2: `update_status_ajax()` (linhas 894-943)**:
```php
// Ao finalizar agendamento, cria/atualiza transação
$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE agendamento_id = %d", $id ) );
$trans_data = [
    'cliente_id'     => $client_id,
    'agendamento_id' => $id,
    'valor'          => $valor,
    'categoria'      => 'Serviço',
    'tipo'           => 'receita',
    'status'         => ( $status === 'finalizado' ? 'em_aberto' : 'pago' ),
    'descricao'      => $desc,
];
if ( $existing ) {
    $wpdb->update( $table, [...], [ 'id' => $existing ] );
} else {
    $wpdb->insert( $table, $trans_data, [...] );
}
```
- ❌ Lógica completa de criação/atualização de transação
- ❌ Cálculo de valores, montagem de descrição, decisão de status
- ❌ DUPLICA exatamente a mesma lógica presente no Finance (linhas 1100-1210)

**Finance Add-on JÁ possui lógica equivalente**:

**`sync_status_to_finance()` (linhas 1126-1210)**:
- ✅ Já sincroniza status de agendamento → transação
- ✅ Já cria/atualiza transação baseado em `appointment_status`
- ✅ Usa hooks `updated_post_meta` e `added_post_meta`

**PROBLEMA: Ambos fazem a mesma coisa de formas diferentes!**

| Aspecto | Agenda | Finance |
|---------|--------|---------|
| Gatilho | AJAX `update_status_ajax()` | Hook `updated_post_meta` em `appointment_status` |
| Criação de transação | ✅ Sim | ✅ Sim |
| Atualização de transação | ✅ Sim | ✅ Sim |
| Cálculo de valor | ✅ Sim (linha 900) | ✅ Sim (linha 1163-1167) |
| Montagem de descrição | ✅ Sim (linhas 903-918) | ✅ Sim (linhas 1170-1185) |
| Decisão de status | ✅ Sim (linha 932) | ✅ Sim (linhas 1138-1197) |

**Consequência**: 
- ⚠️ Risco de condições de corrida (race condition)
- ⚠️ Lógica duplicada dificulta manutenção
- ⚠️ Se Finance estiver desabilitado, Agenda cria transações mesmo assim

---

### 1.4 Meta Keys Duplicadas/Compartilhadas

**Meta keys usadas por AMBOS Finance e Agenda**:

| Meta Key | Agenda | Finance | Propósito |
|----------|--------|---------|-----------|
| `appointment_status` | ✅ Lê/Escreve (21x) | ✅ Lê/Escreve (10x) | Status do agendamento |
| `appointment_total_value` | ✅ Lê (2x) | ✅ Lê (1x) | Valor total |
| `appointment_client_id` | ✅ Lê (múltiplas) | ✅ Lê (3x) | Cliente vinculado |
| `appointment_pet_id` | ✅ Lê (múltiplas) | ✅ Lê (2x) | Pet vinculado |
| `appointment_services` | ✅ Lê (múltiplas) | ✅ Lê (2x) | Serviços selecionados |
| `appointment_date` | ✅ Lê (múltiplas) | ✅ Lê (1x) | Data do agendamento |

**Análise**: ✅ **Não é duplicação problemática**

Estes meta keys são do CPT `dps_agendamento` (núcleo). É correto que ambos add-ons leiam essas metas. O problema é quando ambos ESCREVEM na tabela `dps_transacoes`.

---

### 1.5 Conflitos de Responsabilidade

#### ❌ **Tabela `dps_transacoes` manipulada por 11 add-ons**

```
Finance ............... ✅ DONO (deveria ser o único a manipular)
Agenda ................ ❌ INSERT/UPDATE diretos (linhas 936, 942)
Client Portal ......... ✅ SELECT apenas (leitura de pendências)
Backup ................ ✅ SELECT apenas (exportação)
Groomers .............. ✅ SELECT apenas (comissões)
Loyalty ............... ✅ SELECT apenas (bonificações)
Payment ............... ⚠️ UPDATE de status (webhooks MP)
Push .................. ✅ SELECT apenas (notificações)
Stats ................. ✅ SELECT apenas (métricas)
Subscription .......... ⚠️ INSERT direto (cobranças recorrentes)
```

**Gravidade**:
- ❌ **Agenda**: Grave (duplica lógica completa de CRUD)
- ⚠️ **Payment/Subscription**: Médio (precisam escrever mas de forma controlada)
- ✅ **Demais**: OK (apenas leitura)

---

## 2. API FINANCEIRA PROPOSTA (DETALHADA)

### 2.1 Localização e Estrutura

**Arquivo**: `add-ons/desi-pet-shower-finance_addon/includes/class-dps-finance-api.php`

**Namespace**: Global (classe estática sem namespace)

**Classe**: `DPS_Finance_API`

### 2.2 Métodos da API

#### `DPS_Finance_API::create_or_update_charge( array $data ): int|WP_Error`

**Propósito**: Criar ou atualizar cobrança vinculada a agendamento (método principal usado pela Agenda)

**Parâmetros**:
```php
$data = [
    'appointment_id' => int,     // OBRIGATÓRIO: ID do agendamento
    'client_id'      => int,     // OBRIGATÓRIO: ID do cliente
    'services'       => array,   // OPCIONAL: IDs de serviços (para descrição)
    'pet_id'         => int,     // OPCIONAL: ID do pet (para descrição)
    'value_cents'    => int,     // OBRIGATÓRIO: Valor em centavos
    'status'         => string,  // OPCIONAL: 'pending'|'paid'|'cancelled' (padrão: 'pending')
    'date'           => string,  // OPCIONAL: Data no formato Y-m-d (padrão: data do agendamento ou hoje)
];
```

**Retorno**:
- `int`: ID da transação criada/atualizada
- `WP_Error`: Em caso de erro de validação

**Comportamento**:
1. Valida dados com `validate_charge_data()`
2. Verifica se já existe transação para `appointment_id`
3. Se existe: atualiza valor, status e descrição
4. Se não existe: insere nova transação
5. Monta descrição automaticamente a partir de `services` e `pet_id`
6. Mapeia status: `pending` → `em_aberto`, `paid` → `pago`, `cancelled` → `cancelado`
7. Dispara hook `dps_finance_charge_updated` ou `dps_finance_charge_created`

**Exemplo de uso (Agenda)**:
```php
$result = DPS_Finance_API::create_or_update_charge([
    'appointment_id' => $appointment_id,
    'client_id'      => $client_id,
    'services'       => $service_ids,
    'pet_id'         => $pet_id,
    'value_cents'    => $total_cents,
    'status'         => 'pending',
]);

if ( is_wp_error( $result ) ) {
    // Tratar erro
} else {
    // $result contém o ID da transação
}
```

---

#### `DPS_Finance_API::mark_as_paid( int $charge_id, array $options = [] ): bool|WP_Error`

**Propósito**: Marcar cobrança como paga

**Parâmetros**:
```php
$charge_id = int;  // ID da transação
$options = [
    'paid_date'      => string,  // OPCIONAL: Data de pagamento Y-m-d (padrão: hoje)
    'payment_method' => string,  // OPCIONAL: Método de pagamento
    'notes'          => string,  // OPCIONAL: Observações
];
```

**Retorno**:
- `true`: Sucesso
- `WP_Error`: Erro (transação não encontrada, já paga, etc.)

**Comportamento**:
1. Valida que transação existe
2. Atualiza status para `pago`
3. Registra data de pagamento
4. Dispara hook `dps_finance_booking_paid` (MANTÉM COMPATIBILIDADE com Loyalty)
5. Atualiza `appointment_status` para `finalizado_pago` se vinculado

---

#### `DPS_Finance_API::mark_as_pending( int $charge_id ): bool|WP_Error`

**Propósito**: Marcar cobrança como pendente (reabrir cobrança paga por engano)

**Retorno**: `true` ou `WP_Error`

---

#### `DPS_Finance_API::mark_as_cancelled( int $charge_id, string $reason = '' ): bool|WP_Error`

**Propósito**: Cancelar cobrança

**Parâmetros**:
- `$charge_id`: ID da transação
- `$reason`: Motivo do cancelamento (opcional)

**Comportamento**:
1. Atualiza status para `cancelado`
2. Registra motivo em campo `notes` ou `descricao`
3. Atualiza `appointment_status` para `cancelado` se vinculado

---

#### `DPS_Finance_API::get_charge( int $charge_id ): object|null`

**Propósito**: Buscar dados de uma cobrança

**Retorno**: Objeto com dados da transação ou `null` se não encontrada

**Estrutura do retorno**:
```php
stdClass {
    id: int,
    appointment_id: int,
    client_id: int,
    value_cents: int,         // Convertido de float para int
    status: string,           // 'pending'|'paid'|'cancelled'
    date: string,             // Y-m-d
    paid_date: string|null,   // Y-m-d
    description: string,
    created_at: string,       // Y-m-d H:i:s
}
```

---

#### `DPS_Finance_API::get_charges_by_appointment( int $appointment_id ): array`

**Propósito**: Buscar todas as cobranças de um agendamento

**Retorno**: Array de objetos (mesma estrutura de `get_charge()`)

---

#### `DPS_Finance_API::calculate_appointment_total( int $appointment_id ): int`

**Propósito**: Calcular valor total de um agendamento baseado em serviços e pets

**Retorno**: Valor em centavos

**Comportamento**:
1. Busca `appointment_services` e `appointment_pet_id`
2. Para cada serviço, busca preço via Services Add-on
3. Aplica variações de preço por porte do pet (se Services Add-on ativo)
4. Retorna soma total em centavos

**Nota**: Este método PODE depender do Services Add-on. Se Services não estiver ativo, retorna valor de `appointment_total_value` ou 0.

---

#### `DPS_Finance_API::validate_charge_data( array $data ): true|WP_Error`

**Propósito**: Validar dados antes de criar/atualizar cobrança

**Validações**:
- `appointment_id` existe e é válido
- `client_id` existe e é válido
- `value_cents` é inteiro positivo
- `status` é um dos valores permitidos
- `date` está no formato correto

**Retorno**: `true` se válido, `WP_Error` com mensagens descritivas se inválido

---

#### `DPS_Finance_API::delete_charges_by_appointment( int $appointment_id ): int`

**Propósito**: Remover todas as cobranças de um agendamento (usado ao excluir agendamento)

**Retorno**: Número de transações removidas

**Comportamento**:
1. Busca todas as transações com `agendamento_id = $appointment_id`
2. Remove também parcelas vinculadas (tabela `dps_parcelas`)
3. Dispara hook `dps_finance_charges_deleted`

---

### 2.3 Hooks Disparados pela API

| Hook | Quando | Parâmetros | Uso |
|------|--------|------------|-----|
| `dps_finance_charge_created` | Após criar nova cobrança | `$charge_id`, `$appointment_id` | Notificações, logs |
| `dps_finance_charge_updated` | Após atualizar cobrança | `$charge_id`, `$appointment_id` | Sincronizar outros sistemas |
| `dps_finance_booking_paid` | Ao marcar como pago | `$charge_id`, `$client_id`, `$value_cents` | Loyalty (bonificações) |
| `dps_finance_charges_deleted` | Após deletar cobranças | `$appointment_id`, `$deleted_count` | Limpeza de dados relacionados |

---

## 3. ALTERAÇÕES ESPECÍFICAS NA AGENDA

### 3.1 Arquivo a modificar

**`add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`**

### 3.2 Mudanças necessárias

#### Mudança 1: Remover shortcode `[dps_charges_notes]`

**ANTES (linhas 28, 821-845)**:
```php
add_shortcode( 'dps_charges_notes', [ $this, 'render_charges_notes_shortcode' ] );

public function render_charges_notes_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'dps_transacoes';
    $rows = $wpdb->get_results( "SELECT * FROM $table WHERE tipo = 'receita' ORDER BY data DESC" );
    // ... renderização de tabela ...
}
```

**DEPOIS**:
```php
// Remover linha 28 completamente
// Remover método render_charges_notes_shortcode() completamente (linhas 820-845)
```

**Justificativa**: Este shortcode pertence ao Finance. Se usuários já o utilizam, migrar para `[dps_fin_docs]` (já existe no Finance).

**Migração para usuários**:
1. Documentar no CHANGELOG.md que `[dps_charges_notes]` foi movido para Finance como `[dps_fin_docs]`
2. Manter shortcode deprecated por 1 versão com aviso:
```php
add_shortcode( 'dps_charges_notes', function() {
    _deprecated_function( 'dps_charges_notes', '1.1.0', 'dps_fin_docs' );
    return do_shortcode( '[dps_fin_docs]' );
});
```

---

#### Mudança 2: Substituir lógica financeira em `update_status_ajax()`

**ANTES (linhas 894-943)**:
```php
if ( $status === 'finalizado' || $status === 'finalizado_pago' ) {
    $client_id  = get_post_meta( $id, 'appointment_client_id', true );
    $date       = get_post_meta( $id, 'appointment_date', true );
    $valor      = get_post_meta( $id, 'appointment_total_value', true );
    $valor      = $valor ? (float) $valor : 0;
    
    $service_ids = get_post_meta( $id, 'appointment_services', true );
    $desc_parts = [];
    if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
        foreach ( $service_ids as $sid ) {
            $srv = get_post( $sid );
            if ( $srv ) {
                $desc_parts[] = $srv->post_title;
            }
        }
    }
    $pet_post = $pet_id ? get_post( $pet_id ) : null;
    if ( $pet_post ) {
        $desc_parts[] = $pet_post->post_title;
    }
    $desc = implode( ' - ', $desc_parts );
    
    global $wpdb;
    $table = $wpdb->prefix . 'dps_transacoes';
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE agendamento_id = %d", $id ) );
    $trans_data = [
        'cliente_id'     => $client_id,
        'agendamento_id' => $id,
        'data'           => $date ? $date : current_time( 'Y-m-d' ),
        'valor'          => $valor,
        'categoria'      => 'Serviço',
        'tipo'           => 'receita',
        'status'         => ( $status === 'finalizado' ? 'em_aberto' : 'pago' ),
        'descricao'      => $desc,
    ];
    if ( $existing ) {
        $wpdb->update( $table, [...], [ 'id' => $existing ] );
    } else {
        $wpdb->insert( $table, $trans_data, [...] );
    }
}
```

**DEPOIS (substituir bloco inteiro por)**:
```php
// Nota: A sincronização financeira é feita automaticamente pelo Finance Add-on
// via hook updated_post_meta ao alterar appointment_status.
// Não é necessário criar transações manualmente aqui.

// OPCIONAL: Se quiser forçar criação/atualização imediata (síncrona) em vez de via hook:
if ( ( $status === 'finalizado' || $status === 'finalizado_pago' ) && class_exists( 'DPS_Finance_API' ) ) {
    $client_id   = get_post_meta( $id, 'appointment_client_id', true );
    $pet_id      = get_post_meta( $id, 'appointment_pet_id', true );
    $service_ids = get_post_meta( $id, 'appointment_services', true );
    $valor_meta  = get_post_meta( $id, 'appointment_total_value', true );
    $valor_cents = DPS_Money_Helper::parse_brazilian_format( $valor_meta );
    
    DPS_Finance_API::create_or_update_charge([
        'appointment_id' => $id,
        'client_id'      => $client_id,
        'services'       => is_array( $service_ids ) ? $service_ids : [],
        'pet_id'         => $pet_id,
        'value_cents'    => $valor_cents,
        'status'         => ( $status === 'finalizado_pago' ? 'paid' : 'pending' ),
    ]);
}
```

**Alternativamente (RECOMENDADO)**: Remover bloco completamente e confiar 100% no Finance

```php
// Sincronização financeira automática via Finance Add-on (hook updated_post_meta)
// Nenhuma ação necessária aqui.
```

**Justificativa**: O Finance Add-on já possui `sync_status_to_finance()` que monitora mudanças em `appointment_status` via hook. Duplicar essa lógica aqui causa race conditions.

---

#### Mudança 3: Remover referências diretas a `dps_transacoes`

**Ocorrências atuais**:
- Linha 823: `$table = $wpdb->prefix . 'dps_transacoes';`
- Linha 921: `$table = $wpdb->prefix . 'dps_transacoes';`

**Ação**: Remover após implementar mudanças 1 e 2.

---

### 3.3 Dependências para Agenda

Após refatoração, Agenda ADD-ON precisa:

**OBRIGATÓRIO**:
- Plugin Base (já é dependência)
- Finance Add-on (nova dependência)

**Verificação de ativação**:
```php
// Adicionar no constructor da classe DPS_Agenda_Addon
if ( ! class_exists( 'DPS_Finance_API' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __( 'Agenda Add-on requer Finance Add-on ativo.', 'dps-agenda-addon' );
        echo '</p></div>';
    });
    return; // Não inicializa
}
```

---

## 4. ARQUIVO DUPLICADO DO FINANCE

### Veredicto: ✅ NÃO REMOVER

**Análise**:

| Arquivo | Status | Ação |
|---------|--------|------|
| `desi-pet-shower-finance-addon.php` | ✅ Principal | **MANTER** |
| `desi-pet-shower-finance.php` | ✅ Compatibilidade | **MANTER** |

**Razões para manter `desi-pet-shower-finance.php`**:

1. **NÃO causa duplicação**: Não possui header de plugin WordPress
2. **Bem documentado**: Comentários claros explicam propósito (linhas 2-11)
3. **Retrocompatibilidade**: Permite código antigo funcionar
4. **Confirmado no README**: Documentação oficial descreve essa arquitetura (README.md linhas 19-26)
5. **Padrão comum**: Outros projetos WordPress usam esta técnica

**Comparação com problema REAL (Services Add-on)**:

Services Add-on TEM problema (memória #4):
- `desi-pet-shower-services.php` → Header completo (v1.1.0)
- `dps_service/desi-pet-shower-services-addon.php` → Header duplicado (v1.0.0)
- Resultado: **2 plugins na lista do WordPress** ❌

Finance NÃO tem problema:
- `desi-pet-shower-finance-addon.php` → Header completo (v1.0.0)
- `desi-pet-shower-finance.php` → SEM header, apenas include
- Resultado: **1 plugin na lista** ✅

---

## 5. NOVA ARQUITETURA ORGANIZADA

### 5.1 Diagrama de Responsabilidades

```
┌─────────────────────────────────────────────────────────────┐
│                    PLUGIN BASE (Núcleo)                     │
│  CPTs: dps_cliente, dps_pet, dps_agendamento               │
│  Meta keys: appointment_*, client_*, pet_*                   │
│  Helpers: DPS_Money_Helper, DPS_Query_Helper, etc.          │
│  Hooks: dps_base_*, dps_finance_cleanup_for_appointment     │
└─────────────────────────────────────────────────────────────┘
                              │
                 ┌────────────┴────────────┐
                 │                         │
        ┌────────▼────────┐       ┌───────▼────────┐
        │  FINANCE ADD-ON │       │  AGENDA ADD-ON │
        │   (Autoridade)  │       │   (Cliente)    │
        └────────┬────────┘       └───────┬────────┘
                 │                        │
                 │  ◄──── chama API ──────┘
                 │
    ┌────────────┴────────────┐
    │  DPS_Finance_API        │
    │  (Interface Pública)    │
    ├─────────────────────────┤
    │  • create_or_update()   │
    │  • mark_as_paid()       │
    │  • get_charge()         │
    │  • calculate_total()    │
    └─────────────────────────┘
                 │
    ┌────────────▼────────────┐
    │  DPS_Finance_Addon      │
    │  (Implementação)        │
    ├─────────────────────────┤
    │  • Tabela transacoes    │
    │  • Tabela parcelas      │
    │  • Lógica de cálculo    │
    │  • Validação            │
    │  • Sincronização hooks  │
    └─────────────────────────┘
                 │
    ┌────────────▼────────────┐
    │  Banco de Dados         │
    │  • dps_transacoes       │
    │  • dps_parcelas         │
    └─────────────────────────┘
```

### 5.2 Matriz de Responsabilidades

| Responsabilidade | Finance | Agenda | Base | Outros |
|------------------|---------|--------|------|--------|
| **CRUD de Transações** | ✅ ÚNICO | ❌ | ❌ | ❌ |
| **Tabela dps_transacoes** | ✅ WRITE | ❌ | ❌ | ✅ READ |
| **Cálculo de valores** | ✅ | ❌ | ❌ | Services (preços) |
| **Status financeiro** | ✅ ÚNICO | ❌ | ❌ | ❌ |
| **Parcelas** | ✅ ÚNICO | ❌ | ❌ | ❌ |
| **Documentos (recibos)** | ✅ | ❌ | ❌ | ❌ |
| **Hook dps_finance_booking_paid** | ✅ DISPARA | ❌ | ❌ | ✅ CONSOME (Loyalty) |
| **Criar agendamento** | ❌ | ✅ | ✅ Frontend | ❌ |
| **Alterar status agendamento** | ❌ | ✅ | ✅ Frontend | ❌ |
| **Meta keys appointment_*** | ❌ | ✅ WRITE | ✅ DEFINE | ✅ READ |
| **Sincronizar status → finance** | ✅ AUTOMÁTICO | ❌ | ❌ | ❌ |
| **Exibir pendências** | ✅ Shortcode | ❌ | ❌ | ✅ Portal |

### 5.3 Fluxo de Dados (Criar Agendamento)

**ANTES (duplicado)**:
```
1. Usuário cria agendamento
2. Agenda salva CPT dps_agendamento
3. Agenda cria transação em dps_transacoes ❌ DUPLICADO
4. Finance detecta mudança via hook
5. Finance cria transação em dps_transacoes ❌ DUPLICADO
   → RESULTADO: 2 transações para 1 agendamento!
```

**DEPOIS (centralizado)**:
```
1. Usuário cria agendamento
2. Agenda salva CPT dps_agendamento
3. Agenda chama DPS_Finance_API::create_or_update_charge()
4. Finance valida dados
5. Finance cria transação em dps_transacoes ✅ ÚNICO
6. Finance dispara hook dps_finance_charge_created
7. Loyalty/Payment/etc reagem ao hook (opcional)
   → RESULTADO: 1 transação, 1 fonte de verdade
```

### 5.4 Fluxo de Dados (Alterar Status)

**ANTES (duplicado + race condition)**:
```
1. Usuário marca agendamento como "finalizado_pago"
2. AJAX update_status_ajax() executa
3. Agenda atualiza appointment_status meta ✅
4. Agenda cria/atualiza transação ❌ DUPLICADO
5. Hook updated_post_meta dispara
6. Finance detecta mudança em appointment_status
7. Finance cria/atualiza transação ❌ DUPLICADO
   → RESULTADO: Race condition, 2 updates concorrentes!
```

**DEPOIS (único ponto de escrita)**:
```
1. Usuário marca agendamento como "finalizado_pago"
2. AJAX update_status_ajax() executa
3. Agenda atualiza appointment_status meta ✅
4. Hook updated_post_meta dispara
5. Finance detecta mudança em appointment_status
6. Finance atualiza transação via sync_status_to_finance() ✅ ÚNICO
7. Finance dispara hook dps_finance_booking_paid
   → RESULTADO: 1 update, fonte de verdade clara
```

---

## 6. LISTA DE TODOs DE IMPLEMENTAÇÃO

### Fase 1: Criar API Financeira (PRIORIDADE ALTA)

- [ ] **1.1** Criar arquivo `add-ons/desi-pet-shower-finance_addon/includes/class-dps-finance-api.php`
- [ ] **1.2** Implementar métodos CRUD:
  - [ ] `create_or_update_charge()`
  - [ ] `mark_as_paid()`
  - [ ] `mark_as_pending()`
  - [ ] `mark_as_cancelled()`
  - [ ] `get_charge()`
  - [ ] `get_charges_by_appointment()`
- [ ] **1.3** Implementar métodos auxiliares:
  - [ ] `validate_charge_data()`
  - [ ] `calculate_appointment_total()` (integração com Services)
  - [ ] `delete_charges_by_appointment()`
- [ ] **1.4** Adicionar hooks:
  - [ ] `dps_finance_charge_created`
  - [ ] `dps_finance_charge_updated`
  - [ ] `dps_finance_charges_deleted`
  - [ ] Manter `dps_finance_booking_paid` (compatibilidade)
- [ ] **1.5** Incluir API no arquivo principal: `require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-api.php';`
- [ ] **1.6** Escrever DocBlocks completos para todos os métodos

### Fase 2: Migrar Finance para usar helpers oficiais (PRIORIDADE ALTA)

- [ ] **2.1** Substituir `dps_parse_money_br()` por `DPS_Money_Helper::parse_brazilian_format()`
  - [ ] Linha 170 (partial value)
  - [ ] Linha 215 (finance value)
  - [ ] Linha 1166 (appointment total)
  - Total: 3 ocorrências
- [ ] **2.2** Substituir `dps_format_money_br()` por `DPS_Money_Helper::format_to_brazilian()`
  - [ ] Linha 429, 499, 795, 796, 940, 958 (2x), 996, 1088, 1095
  - Total: 11 ocorrências
- [ ] **2.3** Depreciar funções globais:
```php
if ( ! function_exists( 'dps_parse_money_br' ) ) {
    function dps_parse_money_br( $str ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::parse_brazilian_format()' );
        return DPS_Money_Helper::parse_brazilian_format( $str );
    }
}
```
- [ ] **2.4** Atualizar CHANGELOG.md com depreciação

### Fase 3: Refatorar Agenda (PRIORIDADE ALTA)

- [ ] **3.1** Remover método `render_charges_notes_shortcode()` (linhas 821-845)
- [ ] **3.2** Depreciar shortcode `[dps_charges_notes]`:
```php
add_shortcode( 'dps_charges_notes', function() {
    _deprecated_function( 'Shortcode dps_charges_notes', '1.1.0', 'dps_fin_docs (Finance Add-on)' );
    if ( shortcode_exists( 'dps_fin_docs' ) ) {
        return do_shortcode( '[dps_fin_docs]' );
    }
    return '<p>Este shortcode foi movido para o Finance Add-on. Use [dps_fin_docs].</p>';
});
```
- [ ] **3.3** Refatorar `update_status_ajax()`:
  - [ ] Remover linhas 894-943 (criação de transação)
  - [ ] Confiar na sincronização automática do Finance
  - [ ] OU chamar `DPS_Finance_API::create_or_update_charge()` se quiser controle explícito
- [ ] **3.4** Adicionar verificação de dependência no `__construct()`:
```php
if ( ! class_exists( 'DPS_Finance_API' ) ) {
    add_action( 'admin_notices', [ $this, 'finance_dependency_notice' ] );
    return;
}
```
- [ ] **3.5** Atualizar README.md da Agenda documentando dependência do Finance

### Fase 4: Migrar Loyalty (PRIORIDADE MÉDIA)

- [ ] **4.1** Substituir `dps_format_money_br()` por `DPS_Money_Helper::format_to_brazilian()`
  - [ ] Linha 463, 517
  - Total: 2 ocorrências
- [ ] **4.2** Remover função duplicada (linha 966):
```php
// REMOVER:
function dps_format_money_br( $int ) {
    $float = (int) $int / 100;
    return number_format( $float, 2, ',', '.' );
}
```

### Fase 5: Documentação (PRIORIDADE ALTA)

- [ ] **5.1** Atualizar `ANALYSIS.md`:
  - [ ] Adicionar seção "API Financeira" em Finance Add-on
  - [ ] Documentar métodos públicos com assinaturas e exemplos
  - [ ] Atualizar diagrama de dependências
  - [ ] Marcar Agenda como dependente de Finance
- [ ] **5.2** Atualizar `CHANGELOG.md`:
  - [ ] Categoria "Added": Nova API financeira pública
  - [ ] Categoria "Deprecated": Funções `dps_*_money_br()` e shortcode `[dps_charges_notes]`
  - [ ] Categoria "Changed": Agenda agora depende de Finance
  - [ ] Categoria "Refactoring": Lógica financeira centralizada
- [ ] **5.3** Atualizar `add-ons/desi-pet-shower-finance_addon/README.md`:
  - [ ] Adicionar seção "API Pública" com exemplos
  - [ ] Documentar métodos da classe `DPS_Finance_API`
  - [ ] Listar add-ons que devem usar a API
- [ ] **5.4** Atualizar `add-ons/desi-pet-shower-agenda_addon/README.md`:
  - [ ] Adicionar Finance como dependência obrigatória
  - [ ] Documentar mudança de shortcode
  - [ ] Explicar que lógica financeira foi movida

### Fase 6: Testes de Integração (PRIORIDADE ALTA)

- [ ] **6.1** Testar fluxo: Criar agendamento → Verificar transação criada
- [ ] **6.2** Testar fluxo: Alterar status para "finalizado" → Verificar transação em aberto
- [ ] **6.3** Testar fluxo: Alterar status para "finalizado_pago" → Verificar transação paga
- [ ] **6.4** Testar fluxo: Cancelar agendamento → Verificar transação cancelada
- [ ] **6.5** Testar fluxo: Excluir agendamento → Verificar transação removida
- [ ] **6.6** Testar fluxo: Marcar como pago via Finance → Verificar hook `dps_finance_booking_paid` dispara
- [ ] **6.7** Testar fluxo: Loyalty bonifica após pagamento → Verificar integração funciona
- [ ] **6.8** Testar shortcode deprecado `[dps_charges_notes]` redireciona corretamente

### Fase 7: Migrações e Compatibilidade (PRIORIDADE BAIXA)

- [ ] **7.1** Criar script de migração para usuários (se necessário):
  - Verificar transações duplicadas
  - Consolidar se encontrar duplicatas
  - Registrar em log
- [ ] **7.2** Manter depreciações por 1 versão MINOR antes de remover completamente
- [ ] **7.3** Adicionar avisos no admin se Finance desabilitado mas Agenda ativo

### TODOs Opcionais Futuros (NÃO PRIORITÁRIOS)

- [ ] **Opcional 1**: Criar `DPS_Finance_API::bulk_create_charges()` para importação em lote
- [ ] **Opcional 2**: Adicionar cache em memória para transações frequentemente consultadas
- [ ] **Opcional 3**: Implementar `DPS_Finance_API::get_client_balance()` para saldo total por cliente
- [ ] **Opcional 4**: Adicionar filtro `dps_finance_calculate_total` para personalizar cálculos
- [ ] **Opcional 5**: Migrar Payment e Subscription para usar API (atualmente fazem INSERT direto)

---

## 7. RESUMO EXECUTIVO

### ✅ O que está correto

1. Finance Add-on NÃO tem duplicação de plugin (arquivo .php é compatibilidade intencional)
2. Estrutura de tabelas `dps_transacoes` e `dps_parcelas` bem desenhada
3. Finance já possui sincronização via hook `updated_post_meta`
4. Helpers globais do núcleo (DPS_Money_Helper) já existem

### ❌ O que precisa corrigir

1. Agenda manipula tabela financeira diretamente (INSERT/UPDATE)
2. Lógica de criação de transação duplicada em 2 lugares
3. Funções monetárias duplicadas em Finance e Loyalty
4. Agenda possui shortcode financeiro que deveria estar no Finance
5. Risco de race conditions ao alterar status

### 🎯 Solução proposta

1. Criar API pública `DPS_Finance_API` no Finance Add-on
2. Refatorar Agenda para chamar API em vez de SQL direto
3. Migrar todos os add-ons para usar `DPS_Money_Helper` oficial
4. Depreciar funções e shortcodes duplicados
5. Documentar nova arquitetura e dependências

### 📊 Impacto estimado

- **Linhas removidas**: ~150 linhas (lógica duplicada na Agenda)
- **Linhas adicionadas**: ~400 linhas (nova API + depreciações + docs)
- **Breaking changes**: Agenda passa a DEPENDER de Finance
- **Compatibilidade**: Mantida via depreciações por 1 versão

### ⏱️ Esforço estimado

- Fase 1-3 (API + refatoração): **4-6 horas**
- Fase 4 (Loyalty): **1 hora**
- Fase 5 (Documentação): **2 horas**
- Fase 6 (Testes): **3-4 horas**
- **TOTAL**: **10-13 horas**

---

**Fim do diagnóstico.**
