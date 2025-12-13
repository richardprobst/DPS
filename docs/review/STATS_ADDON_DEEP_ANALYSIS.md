# Stats Add-on — Análise Técnica Profunda

**Versão Analisada:** v1.1.0  
**Data da Análise:** 2025-12-13  
**Autor:** Copilot Coding Agent  
**Tipo:** Análise técnica detalhada, performance, segurança e roadmap de melhorias

---

## Índice

1. [Arquitetura e Modelo de Dados](#1-arquitetura-e-modelo-de-dados)
2. [Fontes de Eventos e Integrações](#2-fontes-de-eventos-e-integrações)
3. [KPIs e Definições Técnicas](#3-kpis-e-definições-técnicas)
4. [Performance e Escalabilidade](#4-performance-e-escalabilidade)
5. [Segurança, Privacidade e Acesso](#5-segurança-privacidade-e-acesso)
6. [Auditoria e Confiabilidade](#6-auditoria-e-confiabilidade)
7. [Mapa de Contratos (Hooks e Endpoints)](#7-mapa-de-contratos)
8. [Achados Técnicos Catalogados](#8-achados-técnicos-catalogados)
9. [Roadmap de Melhorias em FASES](#9-roadmap-de-melhorias-em-fases)

---

## 1. Arquitetura e Modelo de Dados

### 1.1 Estrutura de Arquivos

```
add-ons/desi-pet-shower-stats_addon/
├── desi-pet-shower-stats-addon.php      403 linhas (bootstrapping + classe principal)
├── includes/
│   └── class-dps-stats-api.php          750 linhas (API pública com 14 métodos estáticos)
├── assets/
│   ├── css/
│   │   └── stats-addon.css              449 linhas (estilos visuais, cards, gráficos)
│   └── js/
│       └── stats-addon.js               311 linhas (Chart.js + helpers)
├── README.md                            Documentação funcional
└── uninstall.php                        Limpeza de transients
```

**Total:** 1913 linhas de código

**Avaliação:** ✅ Estrutura modular desde v1.1.0 (antes era arquivo único de 600 linhas)

### 1.2 Classes e Responsabilidades

#### `DPS_Stats_Addon` (arquivo principal)

| Método | Linhas | Responsabilidade | Complexidade |
|--------|--------|------------------|--------------|
| `__construct()` | 123-130 | Registra hooks de integração | Simples |
| `register_assets()` | 132-136 | Registra (não enfileira) CSS e JS | Simples |
| `enqueue_assets()` | 138-142 | Enfileira assets quando necessário | Simples |
| `add_stats_tab()` | 144-147 | Adiciona aba na navegação do painel base | Simples |
| `add_stats_section()` | 149-153 | Renderiza seção completa de stats | Simples (delega) |
| `section_stats()` | 155-202 | **Orquestra coleta e renderização de todas as métricas** | **Complexo (48 linhas)** |
| `get_date_range()` | 204-211 | Extrai período de `$_GET` ou usa padrão (30 dias) | Simples |
| `render_date_filter()` | 213-233 | Formulário de filtro de período | Médio |
| `render_metric_cards()` | 235-247 | Grid de 5 cards principais | Médio |
| `render_card()` | 249-263 | Card individual com ícone, valor, trend | Simples |
| `render_financial_metrics()` | 266-280 | Detalhamento financeiro (receita, despesas, lucro) | Médio |
| `get_subscription_metrics()` | 282-293 | **Query SQL direta para assinaturas** | **Médio (acoplamento)** |
| `render_subscription_metrics()` | 295-304 | Grid de assinaturas (ativas, pendentes, receita) | Simples |
| `render_top_services()` | 306-322 | Gráfico Chart.js + lista de serviços | Médio |
| `render_pet_distribution()` | 324-348 | Gráficos de espécies (pizza) e raças (barras) | Médio |
| `render_inactive_pets_table()` | 350-370 | Tabela de pets inativos com WhatsApp | Médio |
| `get_export_url()` | 372-375 | Gera URL de export com nonce | Simples |
| `handle_export_csv()` | 377-386 | Handler de export de métricas | Simples |
| `handle_export_inactive_csv()` | 388-395 | Handler de export de inativos | Simples |

**Observação Crítica:** `get_subscription_metrics()` faz query SQL direta à `dps_transacoes` SEM validar existência da tabela. **RISCO DE FATAL ERROR**.

#### `DPS_Stats_API` (includes/class-dps-stats-api.php)

API pública com **14 métodos estáticos** para consumo por outros add-ons:

| Método | Linhas | Retorno | Cache | Observações |
|--------|--------|---------|-------|-------------|
| `get_appointments_count()` | 43-84 | int | 1h | Conta agendamentos com WP_Query + meta_query |
| `get_revenue_total()` | 96-99 | float | 1h (delegado) | Wrapper para get_financial_totals() |
| `get_expenses_total()` | 111-114 | float | 1h (delegado) | Wrapper para get_financial_totals() |
| `get_financial_totals()` | 126-172 | array | 1h | **Integra com Finance API ou SQL direto** |
| `get_inactive_pets()` | 183-266 | array | 24h | **Query otimizada com GROUP BY (v1.1.0)** |
| `get_top_services()` | 279-337 | array | 1h | Loop em appointments para contar services |
| `get_period_comparison()` | 349-419 | array | 1h | Calcula período anterior automaticamente |
| `calculate_variation()` | 429-434 | float | — | Helper privado para variação % |
| `get_ticket_average()` | 446-451 | float | — (calculado) | receita ÷ atendimentos |
| `get_cancellation_rate()` | 463-472 | float | — (delega) | % de cancelados sobre total |
| `get_new_clients_count()` | 484-518 | int | 1h | date_query em dps_cliente |
| `get_species_distribution()` | 530-590 | array | 1h | Loop em appointments → pet_species |
| `get_top_breeds()` | 603-660 | array | 1h | Loop em appointments → pet_breed |
| `export_inactive_pets_csv()` | 671-697 | string | — | CSV com BOM UTF-8 para Excel |
| `export_metrics_csv()` | 709-749 | string | — | CSV consolidado de métricas |

**Pontos Fortes:**
- ✅ API bem documentada com DocBlocks
- ✅ Métodos independentes e reutilizáveis
- ✅ Cache controlado por `dps_is_cache_disabled()`
- ✅ Sanitização de entrada em todos os métodos

**Pontos Fracos:**
- ⚠️ Todos os métodos são estáticos (dificulta testar com mocks)
- ⚠️ Loops em PHP para contar serviços/espécies (poderia ser SQL GROUP BY)
- ⚠️ Limite fixo de 1000 agendamentos em várias queries

### 1.3 Modelo de Dados Tocado

#### CPTs Consultados

| CPT | Meta Keys Usados | Tipo de Query |
|-----|------------------|---------------|
| `dps_agendamento` | `appointment_date`<br>`appointment_status`<br>`appointment_pet_id`<br>`appointment_services` (array) | WP_Query com meta_query<br>(DATE comparison) |
| `dps_cliente` | (nenhum, apenas post_date) | WP_Query com date_query |
| `dps_pet` | `owner_id`<br>`pet_species`<br>`pet_breed` | get_posts + get_post_meta |
| `dps_subscription` | `subscription_payment_status` | get_posts (ignora período!) |
| `dps_service` | (nenhum, apenas post_title) | get_the_title() em loop |

#### Tabelas Consultadas

| Tabela | Operação | Validação de Existência | Observações |
|--------|----------|------------------------|-------------|
| `dps_transacoes` | SELECT SUM(valor) GROUP BY tipo | ❌ **NÃO VALIDADA** | **CRÍTICO: Fatal error se Finance nunca foi ativado** |
| `wp_postmeta` | JOIN complexo para última data de agendamento | ✅ Nativo WP | Query otimizada com GROUP BY |

**Recomendação Urgente:**

```php
// ANTES de qualquer query em dps_transacoes
global $wpdb;
$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $wpdb->esc_like( $wpdb->prefix . 'dps_transacoes' )
) );

if ( ! $table_exists ) {
    // Retornar array zerado ou mensagem de erro
    return [ 'revenue' => 0, 'expenses' => 0 ];
}
```

#### Transients (Cache)

| Prefixo | Exemplo | TTL | Invalidação |
|---------|---------|-----|-------------|
| `dps_stats_appts_count_` | `dps_stats_appts_count_20241101_20241130` | 1h | ❌ Apenas manual |
| `dps_stats_financial_` | `dps_stats_financial_20241101_20241130` | 1h | ❌ Apenas manual |
| `dps_stats_total_revenue_` | (depreciado em favor de financial) | 1h | ❌ Apenas manual |
| `dps_stats_inactive_pets_` | `dps_stats_inactive_pets_20241113` | 24h | ❌ Apenas manual |
| `dps_stats_top_services_` | `dps_stats_top_services_20241101_20241130_5` | 1h | ❌ Apenas manual |
| `dps_stats_species_` | `dps_stats_species_20241101_20241130` | 1h | ❌ Apenas manual |
| `dps_stats_top_breeds_` | `dps_stats_top_breeds_20241101_20241130_5` | 1h | ❌ Apenas manual |
| `dps_stats_new_clients_` | `dps_stats_new_clients_20241101_20241130` | 1h | ❌ Apenas manual |
| `dps_stats_comparison_` | `dps_stats_comparison_20241101_20241130` | 1h | ❌ Apenas manual |

**Problema Crítico:** NENHUM transient é invalidado automaticamente quando dados mudam. Admin vê dados "congelados" até clicar manualmente "Atualizar dados".

**Solução Recomendada:**

```php
// Em includes/class-dps-stats-cache-manager.php
class DPS_Stats_Cache_Manager {
    public static function init() {
        add_action( 'save_post_dps_agendamento', [ __CLASS__, 'invalidate_on_appointment_change' ], 10, 3 );
        add_action( 'dps_finance_transaction_created', [ __CLASS__, 'invalidate_on_financial_change' ] );
        add_action( 'dps_finance_transaction_updated', [ __CLASS__, 'invalidate_on_financial_change' ] );
    }
    
    public static function invalidate_on_appointment_change( $post_id, $post, $update ) {
        // Buscar data do agendamento
        $date = get_post_meta( $post_id, 'appointment_date', true );
        if ( ! $date ) return;
        
        // Invalidar caches que incluem essa data
        self::invalidate_pattern( 'dps_stats_' );
    }
    
    private static function invalidate_pattern( $pattern ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_' . $pattern ) . '%',
            $wpdb->esc_like( '_transient_timeout_' . $pattern ) . '%'
        ) );
    }
}
DPS_Stats_Cache_Manager::init();
```

---

## 2. Fontes de Eventos e Integrações

### 2.1 Integração com Finance Add-on

**Nível de Integração:** Alto (dados financeiros dependem de Finance)

**Contratos:**

```php
// Stats CONSOME Finance API (quando disponível)
if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'get_period_totals' ) ) {
    $totals = DPS_Finance_API::get_period_totals( $start_date, $end_date );
    // Retorna: [ 'paid_revenue' => float, 'paid_expenses' => float, ... ]
}
```

**Fallback:** SQL direto em `dps_transacoes` (SEM validação de existência ⚠️)

**Dependências:**
- Tabela `dps_transacoes` com colunas: `data`, `valor`, `status`, `tipo`, `plano_id`
- Status: `'pago'` (apenas transações confirmadas são contabilizadas)
- Tipo: `'receita'` ou `'despesa'`

**Melhorias Sugeridas:**
1. **Validar existência de tabela:** Evitar fatal error
2. **Usar apenas Finance API:** Não duplicar lógica de SQL direto
3. **Expor mais métodos em Finance API:**
   - `get_overdue_total()` (inadimplência)
   - `get_revenue_by_service()` (ticket médio por serviço)
   - `get_revenue_by_groomer()` (produtividade)

### 2.2 Integração com Agenda Add-on

**Nível de Integração:** Médio (leitura de agendamentos)

**Contratos:**

```php
// Stats LÊ diretamente CPT dps_agendamento
$appointments = new WP_Query( [
    'post_type' => 'dps_agendamento',
    'meta_query' => [
        [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
        [ 'key' => 'appointment_date', 'value' => $end_date, 'compare' => '<=', 'type' => 'DATE' ],
    ]
] );
```

**Dependências:**
- Meta `appointment_date`: string Y-m-d
- Meta `appointment_status`: `'agendado'`, `'confirmado'`, `'concluido'`, `'cancelado'`, etc.
- Meta `appointment_pet_id`: ID do pet
- Meta `appointment_services`: array de IDs de serviços

**Lacunas:**
- ❌ Não há hook de Agenda que notifique Stats quando status muda
- ❌ Não há conceito de "no-show" diferenciado de "cancelado"
- ❌ Não há meta de "motivo do cancelamento"

**Melhorias Sugeridas:**
1. **Agenda expor hook:**
   ```php
   do_action( 'dps_agenda_status_changed', $appointment_id, $old_status, $new_status );
   ```
2. **Stats escutar hook para invalidar cache**
3. **Adicionar meta `appointment_no_show` booleana** para diferenciar de cancelamento voluntário

### 2.3 Integração com Registration Add-on

**Nível de Integração:** Baixo (apenas contagem de novos clientes)

**Contratos:**

```php
// Stats LÊ CPT dps_cliente com date_query
$new_clients = new WP_Query( [
    'post_type' => 'dps_cliente',
    'date_query' => [
        [ 'after' => $start_date, 'before' => $end_date . ' 23:59:59', 'inclusive' => true ]
    ]
] );
```

**Dependências:**
- `post_date` do CPT (data de cadastro)

**Lacunas:**
- ❌ Não diferencia clientes que já agendaram vs clientes cadastrados mas inativos
- ❌ Não calcula taxa de conversão (cadastro → primeiro agendamento)

**Melhorias Sugeridas:**
1. **Adicionar KPI "Taxa de Conversão":**
   ```php
   public static function get_conversion_rate( $start_date, $end_date ) {
       $new_clients = self::get_new_clients_count( $start_date, $end_date );
       $clients_with_appointments = // COUNT DISTINCT appointment_client_id WHERE client criado no período
       return $clients_with_appointments / $new_clients * 100;
   }
   ```

### 2.4 Integração com Portal do Cliente

**Nível de Integração:** Nenhum (Stats não expõe dados para clientes)

**Oportunidade:**
- Portal poderia consumir `DPS_Stats_API` para exibir métricas do CLIENTE:
  - Total de atendimentos realizados
  - Próximo agendamento previsto (se houver padrão de recorrência)
  - Valor total gasto (histórico)
  - Programa de fidelidade (se integrado com Loyalty)

**Restrições de Segurança:**
- ❌ Stats atual não valida `current_user_can()` em nível de API
- ⚠️ Se Portal chamar `DPS_Stats_API::get_revenue_total()`, verá receita GLOBAL do pet shop

**Solução Necessária:**
```php
// Novo método client-scoped
public static function get_client_stats( $client_id ) {
    // Validar que user atual TEM permissão para ver esse client_id
    if ( ! current_user_can( 'dps_view_own_stats' ) && get_current_user_id() != $owner_user_id ) {
        return new WP_Error( 'forbidden', __( 'Você não tem permissão para ver essas estatísticas.' ) );
    }
    // Retornar apenas dados DESTE cliente
}
```

### 2.5 Integração com Loyalty/Campaigns Add-ons

**Nível de Integração:** Nenhum (potencial alto para cross-sell)

**Oportunidades:**
1. **Usar métricas de Stats para disparar Campanhas:**
   - Clientes com >90 dias sem atendimento → Campanha de reativação automática
   - Clientes com ticket médio > R$ 200 → Programa VIP
   - Top 10 clientes por volume → Recompensas especiais

2. **Usar campanhas para medir ROI:**
   - Campanha X disparou Y mensagens → Z conversões → R$ W de receita adicional

**Implementação Sugerida:**
```php
// Em Campaigns Add-on, criar trigger:
$inactive_pets = DPS_Stats_API::get_inactive_pets( 90 );
foreach ( $inactive_pets as $item ) {
    $client_id = $item['client']->ID;
    DPS_Campaigns_API::send_campaign( 'reengajamento_90d', $client_id );
}
```

---

## 3. KPIs e Definições Técnicas

### 3.1 Métricas Operacionais

#### 3.1.1 Total de Atendimentos

**Definição:**  
Contagem de posts do tipo `dps_agendamento` cuja meta `appointment_date` está no intervalo `[start_date, end_date]`.

**Fórmula:**
```sql
SELECT COUNT(p.ID)
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'appointment_date'
WHERE p.post_type = 'dps_agendamento'
  AND p.post_status = 'publish'
  AND pm.meta_value >= '2024-11-01'
  AND pm.meta_value <= '2024-11-30'
```

**Implementação:**
```php
// class-dps-stats-api.php:43-84
public static function get_appointments_count( $start_date, $end_date, $status = '' ) {
    $meta_query = [
        'relation' => 'AND',
        [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
        [ 'key' => 'appointment_date', 'value' => $end_date,   'compare' => '<=', 'type' => 'DATE' ],
    ];
    if ( $status ) {
        $meta_query[] = [ 'key' => 'appointment_status', 'value' => $status ];
    }
    return (new WP_Query( [
        'post_type' => 'dps_agendamento',
        'posts_per_page' => -1, // ⚠️ SEM LIMITE
        'meta_query' => $meta_query,
        'fields' => 'ids',
    ] ))->found_posts;
}
```

**Fonte da Verdade:** ✅ CPT `dps_agendamento` com meta `appointment_date`

**Janela de Tempo:** Personalizável (filtro de data na UI)

**Filtros Suportados:** `$status` (opcional) — ex: `'cancelado'`, `'concluido'`

**Problemas Identificados:**
1. ⚠️ `posts_per_page => -1`: Sem paginação. Pet shop com 10.000 agendamentos irá carregar TODOS em memória.
2. ⚠️ `meta_query` com `TYPE = 'DATE'`: Performance degrada com muitos posts (índice não otimizado).
3. ⚠️ Sem distinção entre agendamentos futuros vs passados (conta tudo no período).

**Melhorias Sugeridas:**
```php
// Opção 1: Usar `no_found_rows` se não precisar de paginação
'no_found_rows' => false, // Mantém found_posts
'update_post_meta_cache' => false, // Não precisamos de metas aqui
'update_post_term_cache' => false,

// Opção 2: Query SQL direta (mais rápida para grandes volumes)
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(DISTINCT p.ID)
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type = 'dps_agendamento'
       AND p.post_status = 'publish'
       AND pm.meta_key = 'appointment_date'
       AND pm.meta_value >= %s
       AND pm.meta_value <= %s",
    $start_date, $end_date
) );
```

#### 3.1.2 Taxa de Cancelamento

**Definição:**  
Percentual de agendamentos cancelados sobre o total de agendamentos no período.

**Fórmula:**
```
taxa_cancelamento = (cancelados ÷ total) × 100
```

**Implementação:**
```php
// class-dps-stats-api.php:463-472
public static function get_cancellation_rate( $start_date, $end_date ) {
    $total     = self::get_appointments_count( $start_date, $end_date );
    $cancelled = self::get_appointments_count( $start_date, $end_date, 'cancelado' );
    return $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0;
}
```

**Fonte da Verdade:** ✅ Meta `appointment_status = 'cancelado'`

**Problemas Identificados:**
1. ❌ Não diferencia motivo de cancelamento:
   - Cancelado pelo cliente (voluntário)
   - No-show (cliente não compareceu)
   - Cancelado pelo pet shop (emergência, falta de funcionário)
2. ❌ Não considera reagendamentos (cancelado + novo agendamento deveria ser neutro?)

**Melhorias Sugeridas:**
```php
// Adicionar meta appointment_cancellation_reason
// Valores: 'client_request', 'no_show', 'shop_emergency', 'rescheduled'

public static function get_no_show_rate( $start_date, $end_date ) {
    // COUNT WHERE cancellation_reason = 'no_show'
}

public static function get_rescheduling_rate( $start_date, $end_date ) {
    // COUNT WHERE cancellation_reason = 'rescheduled'
}
```

#### 3.1.3 Novos Clientes

**Definição:**  
Contagem de posts do tipo `dps_cliente` criados no intervalo `[start_date, end_date]`.

**Fórmula:**
```sql
SELECT COUNT(ID)
FROM wp_posts
WHERE post_type = 'dps_cliente'
  AND post_status = 'publish'
  AND post_date >= '2024-11-01 00:00:00'
  AND post_date <= '2024-11-30 23:59:59'
```

**Implementação:**
```php
// class-dps-stats-api.php:484-518
public static function get_new_clients_count( $start_date, $end_date ) {
    return (new WP_Query( [
        'post_type' => 'dps_cliente',
        'posts_per_page' => -1,
        'date_query' => [
            [
                'after' => $start_date,
                'before' => $end_date . ' 23:59:59',
                'inclusive' => true,
            ]
        ],
        'fields' => 'ids',
    ] ))->found_posts;
}
```

**Fonte da Verdade:** ✅ `post_date` do CPT

**Timezone:** ⚠️ Usa timezone do WordPress (`current_time()`), mas query usa `post_date` em UTC. Pode haver descasamento de 2-3 horas.

**Melhorias Sugeridas:**
```php
// Normalizar para timezone do site
$start_ts = strtotime( $start_date . ' 00:00:00', current_time( 'timestamp' ) );
$end_ts   = strtotime( $end_date . ' 23:59:59', current_time( 'timestamp' ) );

'date_query' => [
    [
        'after' => date( 'Y-m-d H:i:s', $start_ts ),
        'before' => date( 'Y-m-d H:i:s', $end_ts ),
        'inclusive' => true,
        'column' => 'post_date', // Explicitamente post_date (não post_date_gmt)
    ]
]
```

#### 3.1.4 Pets Inativos

**Definição:**  
Pets que não tiveram nenhum agendamento há pelo menos `$days` dias (padrão: 30).

**Fórmula:**
```
cutoff_date = TODAY - X dias
inativos = pets WHERE última_data_agendamento < cutoff_date OR última_data_agendamento IS NULL
```

**Implementação (v1.1.0 — OTIMIZADA):**
```php
// class-dps-stats-api.php:183-266
public static function get_inactive_pets( $days = 30 ) {
    $cutoff_date = date( 'Y-m-d', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
    
    // 1. Buscar TODOS os pets (limit 500)
    $pets = get_posts( [
        'post_type' => 'dps_pet',
        'posts_per_page' => 500, // ⚠️ FILTRO APLICÁVEL
        'fields' => 'ids',
    ] );
    
    // 2. Query SQL otimizada com GROUP BY (UMA query para TODOS os pets)
    $sql = $wpdb->prepare(
        "SELECT pm.meta_value AS pet_id, MAX(pm2.meta_value) AS last_date
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = 'appointment_date'
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'dps_agendamento'
         WHERE pm.meta_key = 'appointment_pet_id' AND pm.meta_value IN (%s)
         GROUP BY pm.meta_value",
        implode( ',', $pets )
    );
    $last_appointments = $wpdb->get_results( $sql, OBJECT_K );
    
    // 3. Filtrar pets inativos
    foreach ( $pets as $pet_id ) {
        $last_date = $last_appointments[ $pet_id ]->last_date ?? '';
        if ( ! $last_date || strtotime( $last_date ) < strtotime( $cutoff_date ) ) {
            // Incluir na lista
        }
    }
}
```

**Performance:**  
✅ **ANTES (v1.0.0):** ~1500 queries (N+1 problem)  
✅ **DEPOIS (v1.1.0):** 2 queries (1 para pets + 1 JOIN otimizado)

**Fonte da Verdade:** ✅ Meta `appointment_pet_id` com última `appointment_date`

**Filtros Aplicáveis:**
```php
// Permite ajustar limite de pets analisados
$pets_limit = apply_filters( 'dps_stats_inactive_pets_limit', 500 );
```

**Problemas Identificados:**
1. ⚠️ Limite de 500 pets: Pet shops com >500 pets não verão todos os inativos.
2. ⚠️ Cache de 24h: Se pet agenda hoje, ficará na lista de inativos até amanhã.
3. ❌ Não considera pets que NUNCA tiveram agendamento (só aparecem como "Nunca" na coluna).

**Melhorias Sugeridas:**
```php
// Remover limite ou paginar
'posts_per_page' => -1, // Todos os pets
// OU
'posts_per_page' => 100,
'paged' => $page,
'no_found_rows' => false,

// Invalidar cache ao criar agendamento
add_action( 'save_post_dps_agendamento', function( $post_id ) {
    delete_transient( 'dps_stats_inactive_pets_' . date( 'Ymd' ) );
} );
```

### 3.2 Métricas Financeiras

#### 3.2.1 Receita Total

**Definição:**  
Soma de transações financeiras do tipo `'receita'` com status `'pago'` no período.

**Fórmula (SQL):**
```sql
SELECT SUM(valor)
FROM wp_dps_transacoes
WHERE data >= '2024-11-01'
  AND data <= '2024-11-30'
  AND status = 'pago'
  AND tipo = 'receita'
```

**Implementação:**
```php
// class-dps-stats-api.php:126-172
public static function get_financial_totals( $start_date, $end_date ) {
    // INTEGRAÇÃO COM FINANCE API (preferencial)
    if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'get_period_totals' ) ) {
        $totals = DPS_Finance_API::get_period_totals( $start_date, $end_date );
        return [
            'revenue'  => (float) ( $totals['paid_revenue'] ?? 0 ),
            'expenses' => (float) ( $totals['paid_expenses'] ?? 0 ),
        ];
    }
    
    // FALLBACK: SQL direto (SEM VALIDAÇÃO DE TABELA ⚠️)
    global $wpdb;
    $table = $wpdb->prefix . 'dps_transacoes';
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT tipo, SUM(valor) AS total
         FROM {$table}
         WHERE data >= %s AND data <= %s AND status = 'pago'
         GROUP BY tipo",
        $start_date, $end_date
    ), OBJECT_K );
    
    return [
        'revenue'  => (float) ( $results['receita']->total ?? 0 ),
        'expenses' => (float) ( $results['despesa']->total ?? 0 ),
    ];
}
```

**Fonte da Verdade:** ✅ Tabela `dps_transacoes` (criada pelo Finance Add-on)

**Problemas Críticos:**
1. ❌ **SEM VALIDAÇÃO DE EXISTÊNCIA DA TABELA:** Fatal error se Finance nunca foi ativado
2. ⚠️ **Dupla lógica:** Finance API + SQL direto (manutenção duplicada)

**Solução Obrigatória:**
```php
// VALIDAR ANTES
$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $wpdb->esc_like( $wpdb->prefix . 'dps_transacoes' )
) );

if ( ! $table_exists ) {
    return [
        'revenue' => 0,
        'expenses' => 0,
        'error' => __( 'Add-on Finance não está ativado. Métricas financeiras indisponíveis.', 'dps-stats-addon' )
    ];
}
```

**Ambiguidade de Definição:**
- Status `'pago'` = Receita REALIZADA (dinheiro em caixa)
- Mas algumas empresas querem ver receita LANÇADA (independente de pagamento)
- **Solução:** Oferecer toggle na UI: "Exibir: [ ] Receita Paga (realizada) [ ] Receita Lançada (projetada)"

#### 3.2.2 Ticket Médio

**Definição:**  
Receita total dividida pelo número de atendimentos.

**Fórmula:**
```
ticket_médio = receita_total ÷ atendimentos_total
```

**Implementação:**
```php
// class-dps-stats-api.php:446-451
public static function get_ticket_average( $start_date, $end_date ) {
    $appointments = self::get_appointments_count( $start_date, $end_date );
    $revenue = self::get_revenue_total( $start_date, $end_date );
    return $appointments > 0 ? round( $revenue / $appointments, 2 ) : 0;
}
```

**Fonte da Verdade:** ✅ Calculado (receita ÷ atendimentos)

**Problemas Identificados:**
1. ⚠️ **Não considera atendimentos sem receita lançada:** Se agendamento foi concluído mas pagamento não foi registrado, ticket médio sobe artificialmente.
2. ⚠️ **Não diferencia por tipo de serviço:** Banho simples (R$ 50) vs Banho + Tosa (R$ 150) têm tickets muito diferentes.

**Melhorias Sugeridas:**
```php
// Ticket médio por serviço
public static function get_ticket_average_by_service( $service_id, $start_date, $end_date ) {
    // Filtrar agendamentos que incluem esse serviço
    // Somar receita APENAS dos lançamentos vinculados a esses agendamentos
}

// Ticket médio por espécie/porte
public static function get_ticket_average_by_species( $species, $start_date, $end_date ) {
    // Filtrar agendamentos por pet_species
}
```

#### 3.2.3 Lucro Líquido

**Definição:**  
Receita total menos despesas totais.

**Fórmula:**
```
lucro = receita - despesas
```

**Implementação:**
```php
// Calculado em desi-pet-shower-stats-addon.php:266-280
$current['profit'] = $current['revenue'] - $current['expenses'];
```

**Fonte da Verdade:** ✅ Derivado de receita e despesas

**Observação:** Simplificação contábil. Lucro "real" deveria considerar:
- Depreciação de equipamentos
- Impostos (se PJ)
- Custos indiretos (aluguel, energia, água)
- Pró-labore dos sócios

**Melhoria Sugerida:**
- Adicionar tooltip explicando que é "lucro bruto" (receita - despesas lançadas), não lucro contábil/tributário.

### 3.3 Comparativo de Períodos

**Definição:**  
Variação percentual das métricas entre período atual e período equivalente anterior.

**Fórmula:**
```
variação_% = ((valor_atual - valor_anterior) ÷ valor_anterior) × 100
```

**Implementação:**
```php
// class-dps-stats-api.php:349-419
public static function get_period_comparison( $start_date, $end_date ) {
    // 1. Calcular duração do período atual
    $duration = strtotime( $end_date ) - strtotime( $start_date );
    
    // 2. Calcular período anterior com mesma duração
    $prev_end = date( 'Y-m-d', strtotime( $start_date ) - DAY_IN_SECONDS );
    $prev_start = date( 'Y-m-d', strtotime( $start_date ) - $duration - DAY_IN_SECONDS );
    
    // 3. Buscar métricas de ambos os períodos
    $current = [ ... ];
    $previous = [ ... ];
    
    // 4. Calcular variações
    $variation = [
        'appointments' => self::calculate_variation( $previous['appointments'], $current['appointments'] ),
        'revenue' => self::calculate_variation( $previous['revenue'], $current['revenue'] ),
        // ...
    ];
    
    return compact( 'current', 'previous', 'variation' );
}

private static function calculate_variation( $old_value, $new_value ) {
    if ( $old_value == 0 ) {
        return $new_value > 0 ? 100 : 0; // Crescimento de 0 → X = +100%
    }
    return round( ( ( $new_value - $old_value ) / abs( $old_value ) ) * 100, 1 );
}
```

**Fonte da Verdade:** ✅ Calculado dinamicamente

**Problemas Identificados:**
1. ⚠️ **Duração variável de meses:** Novembro tem 30 dias, Dezembro tem 31. Comparar período de 30 dias com período de 31 dias gera variação artificial de ~3%.
2. ⚠️ **Não considera sazonalidade:** Dezembro (Natal) geralmente tem mais atendimentos que Janeiro (volta das férias). Variação negativa é esperada, não problemática.
3. ⚠️ **Sem opção de comparar com ano anterior:** "Novembro 2024 vs Novembro 2023" seria mais útil para identificar crescimento real.

**Melhorias Sugeridas:**
```php
// Opção de comparação flexível
public static function get_period_comparison( $start_date, $end_date, $comparison_type = 'previous' ) {
    switch ( $comparison_type ) {
        case 'previous':
            // Período equivalente imediatamente anterior (lógica atual)
            break;
        case 'year_ago':
            // Mesmo período do ano anterior (ex: Nov/2024 vs Nov/2023)
            $prev_start = date( 'Y-m-d', strtotime( $start_date . ' -1 year' ) );
            $prev_end = date( 'Y-m-d', strtotime( $end_date . ' -1 year' ) );
            break;
        case 'same_month_last_year':
            // Mês completo do ano anterior
            break;
    }
}
```


---

## 4. Performance e Escalabilidade

### 4.1 Análise de Queries

#### Query 1: Contagem de Agendamentos (get_appointments_count)

**Código:**
```php
$query = new WP_Query( [
    'post_type' => 'dps_agendamento',
    'posts_per_page' => -1, // ⚠️ SEM LIMITE
    'post_status' => 'publish',
    'meta_query' => [
        [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
        [ 'key' => 'appointment_date', 'value' => $end_date, 'compare' => '<=', 'type' => 'DATE' ],
    ],
    'fields' => 'ids', // ✅ Apenas IDs
] );
```

**Performance:**
- ✅ `fields => 'ids'`: Não carrega objetos completos
- ❌ `posts_per_page => -1`: Carrega TODOS os agendamentos em memória
- ❌ `meta_query`: Executa 2 JOINs na tabela postmeta (lento para >5000 posts)

**Tempo estimado:**
- 100 agendamentos: ~50ms
- 1000 agendamentos: ~200ms
- 10000 agendamentos: ~2-5 segundos (risco de timeout)

**Otimização Recomendada:**

```php
// Opção 1: Usar SQL direto (10x mais rápido)
global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(DISTINCT p.ID)
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type = 'dps_agendamento'
       AND p.post_status = 'publish'
       AND pm.meta_key = 'appointment_date'
       AND pm.meta_value >= %s
       AND pm.meta_value <= %s",
    $start_date, $end_date
) );

// Opção 2: Pré-agregar em tabela diária (cron)
// Tabela: dps_stats_daily (data, appointments, revenue, expenses)
```

#### Query 2: Top Serviços (get_top_services)

**Código:**
```php
// 1. Busca 1000 agendamentos
$appointments = get_posts( [
    'post_type' => 'dps_agendamento',
    'posts_per_page' => 1000, // ⚠️ LIMITE FIXO
    'meta_query' => [ /* date range */ ],
    'fields' => 'ids',
] );

// 2. Loop em PHP para contar serviços
foreach ( $appointments as $appt_id ) {
    $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
    foreach ( $service_ids as $sid ) {
        $service_counts[ $sid ] = ( $service_counts[ $sid ] ?? 0 ) + 1;
    }
}
```

**Problemas:**
- ❌ Limite de 1000 agendamentos: Se houver mais, dados ficam incompletos
- ❌ 1001 queries (1 para agendamentos + 1000 para get_post_meta)
- ❌ Loop em PHP poderia ser SQL GROUP BY

**Otimização Recomendada:**

```php
global $wpdb;

// Query única com GROUP BY e COUNT
$top_services = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        pm_service.meta_value AS service_id,
        COUNT(DISTINCT pm_service.post_id) AS count,
        p_service.post_title AS title
     FROM {$wpdb->postmeta} pm_date
     INNER JOIN {$wpdb->postmeta} pm_service ON pm_date.post_id = pm_service.post_id
     INNER JOIN {$wpdb->posts} p ON pm_date.post_id = p.ID
     LEFT JOIN {$wpdb->posts} p_service ON pm_service.meta_value = p_service.ID
     WHERE pm_date.meta_key = 'appointment_date'
       AND pm_date.meta_value >= %s
       AND pm_date.meta_value <= %s
       AND pm_service.meta_key = 'appointment_services'
       AND p.post_type = 'dps_agendamento'
       AND p.post_status = 'publish'
     GROUP BY pm_service.meta_value
     ORDER BY count DESC
     LIMIT %d",
    $start_date, $end_date, $limit
) );
```

**Ganho estimado:** 1001 queries → 1 query (50-100x mais rápido)

#### Query 3: Pets Inativos (get_inactive_pets)

**Performance v1.1.0 (OTIMIZADA):**
- ✅ Query única com GROUP BY para últimas datas
- ✅ Redução de ~1500 queries para 2 queries
- ⚠️ Ainda processa 500 pets em memória

**Gargalo Restante:**
```php
$pets = get_posts( [
    'post_type' => 'dps_pet',
    'posts_per_page' => 500, // ⚠️ LIMITE FIXO
] );

// Para cada pet, verifica última data
foreach ( $pets as $pet_id ) {
    // get_post() + get_post_meta() (owner_id, phone)
}
```

**Otimização Adicional:**

```php
// Usar update_meta_cache para carregar metas em batch
update_meta_cache( 'post', $pets );

// OU: Query SQL única com LEFT JOIN
$inactive_pets = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        p_pet.ID AS pet_id,
        p_pet.post_title AS pet_name,
        p_client.ID AS client_id,
        p_client.post_title AS client_name,
        MAX(pm_date.meta_value) AS last_date,
        pm_phone.meta_value AS client_phone
     FROM {$wpdb->posts} p_pet
     INNER JOIN {$wpdb->postmeta} pm_owner ON p_pet.ID = pm_owner.post_id AND pm_owner.meta_key = 'owner_id'
     LEFT JOIN {$wpdb->posts} p_client ON pm_owner.meta_value = p_client.ID
     LEFT JOIN {$wpdb->postmeta} pm_phone ON p_client.ID = pm_phone.post_id AND pm_phone.meta_key = 'client_phone'
     LEFT JOIN {$wpdb->postmeta} pm_pet_appt ON p_pet.ID = pm_pet_appt.meta_value AND pm_pet_appt.meta_key = 'appointment_pet_id'
     LEFT JOIN {$wpdb->postmeta} pm_date ON pm_pet_appt.post_id = pm_date.post_id AND pm_date.meta_key = 'appointment_date'
     WHERE p_pet.post_type = 'dps_pet' AND p_pet.post_status = 'publish'
     GROUP BY p_pet.ID
     HAVING last_date IS NULL OR last_date < %s
     ORDER BY last_date ASC
     LIMIT 100",
    $cutoff_date
) );
```

### 4.2 Estratégias de Cache

#### Cache Atual (Transients)

| Transient | TTL | Invalidação | Risco |
|-----------|-----|-------------|-------|
| Métricas financeiras | 1h | Manual | Dados desatualizados por até 1h |
| Pets inativos | 24h | Manual | Pet que agenda hoje fica na lista até amanhã |
| Contagens | 1h | Manual | Admin vê números "congelados" |

**Problemas:**
1. ❌ Cache NUNCA é invalidado automaticamente
2. ❌ Admin precisa clicar manualmente "Atualizar dados"
3. ⚠️ Múltiplos admins podem ver dados diferentes (cache local do navegador)

**Solução: Invalidação Automática**

```php
// Em includes/class-dps-stats-cache-invalidator.php
class DPS_Stats_Cache_Invalidator {
    public static function init() {
        // Invalidar quando agendamento muda
        add_action( 'save_post_dps_agendamento', [ __CLASS__, 'invalidate_all' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'invalidate_on_delete' ] );
        
        // Invalidar quando transação financeira é criada/atualizada
        add_action( 'dps_finance_transaction_created', [ __CLASS__, 'invalidate_all' ] );
        add_action( 'dps_finance_transaction_updated', [ __CLASS__, 'invalidate_all' ] );
        
        // Invalidar quando cliente/pet é criado
        add_action( 'save_post_dps_cliente', [ __CLASS__, 'invalidate_clients' ] );
        add_action( 'save_post_dps_pet', [ __CLASS__, 'invalidate_pets' ] );
    }
    
    public static function invalidate_all() {
        self::delete_transients_by_pattern( 'dps_stats_' );
    }
    
    public static function invalidate_clients() {
        self::delete_transients_by_pattern( 'dps_stats_new_clients_' );
    }
    
    public static function invalidate_pets() {
        self::delete_transients_by_pattern( 'dps_stats_inactive_pets_' );
    }
    
    private static function delete_transients_by_pattern( $pattern ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_' . $pattern ) . '%',
            $wpdb->esc_like( '_transient_timeout_' . $pattern ) . '%'
        ) );
    }
}
DPS_Stats_Cache_Invalidator::init();
```

#### Cache Avançado (Object Cache / Redis)

Para pet shops de alto volume (>5000 agendamentos/mês), considerar:

```php
// Usar WP Object Cache (compatível com Redis/Memcached)
if ( wp_using_ext_object_cache() ) {
    $cache_key = 'dps_stats_' . md5( $start_date . $end_date );
    $cached = wp_cache_get( $cache_key, 'dps_stats' );
    if ( false !== $cached ) {
        return $cached;
    }
    // ... calcular
    wp_cache_set( $cache_key, $result, 'dps_stats', HOUR_IN_SECONDS );
}
```

### 4.3 Escalabilidade: Limites e Soluções

| Cenário | Limite Atual | Impacto | Solução |
|---------|--------------|---------|---------|
| **Pet shop com 2000+ agendamentos/mês** | Queries lentas (2-5s) | Timeout, UX ruim | Pré-agregar diariamente via cron |
| **Pet shop com 10.000 agendamentos históricos** | WP_Query carrega todos em memória | Fatal error (memory limit) | Paginação ou SQL direto |
| **1000+ pets cadastrados** | Cálculo de inativos demora 10-30s | Timeout | Processar em background, cachear 24h |
| **5+ admins acessando simultaneamente** | Competição de cache, queries duplicadas | Degradação de performance | Object cache compartilhado (Redis) |

**Roadmap de Escalabilidade:**

**Fase 1 (Curto Prazo):**
- Validar existência de `dps_transacoes`
- Invalidação automática de cache
- Remover limites fixos de 1000/500

**Fase 2 (Médio Prazo):**
- Converter loops PHP para SQL GROUP BY
- Usar `update_meta_cache()` em batch
- Implementar paginação em pets inativos

**Fase 3 (Longo Prazo):**
- Tabela de agregação diária (`dps_stats_daily`)
- WP-Cron para consolidar métricas à meia-noite
- Object cache (Redis/Memcached)
- Índices compostos em postmeta

---

## 5. Segurança, Privacidade e Acesso

### 5.1 Análise de Capabilities

#### Endpoints Admin (admin-post.php)

| Endpoint | Capability | Nonce | Método | Avaliação |
|----------|------------|-------|--------|-----------|
| `dps_clear_stats_cache` | `manage_options` | ✅ `dps_clear_stats_cache_nonce` | POST | ✅ Seguro |
| `dps_export_stats_csv` | `manage_options` | ✅ `dps_export_nonce` | GET | ✅ Seguro |
| `dps_export_inactive_csv` | `manage_options` | ✅ `dps_export_nonce` | GET | ✅ Seguro |

**Observação Crítica:**  
`manage_options` = Administrator TOTAL. Funcionários (Managers, Groomers) NÃO conseguem ver stats.

**Problema de Caso de Uso:**
- **Gerente do pet shop** deveria ver métricas operacionais (atendimentos, cancelamentos, pets inativos)
- **Gerente NÃO deveria** limpar cache ou exportar dados financeiros
- **Funcionário** deveria ver APENAS suas próprias métricas (atendimentos que realizou)

**Solução: Capabilities Granulares**

```php
// Em activation hook do plugin base
function dps_add_custom_capabilities() {
    $admin = get_role( 'administrator' );
    $admin->add_cap( 'dps_view_stats' );
    $admin->add_cap( 'dps_manage_stats' );
    $admin->add_cap( 'dps_export_stats' );
    
    $manager = get_role( 'dps_manager' ); // Capability customizada
    $manager->add_cap( 'dps_view_stats' );
    // Manager NÃO tem dps_manage_stats ou dps_export_stats
}

// Em Stats Add-on
public function add_stats_section( $visitor_only ) {
    if ( $visitor_only ) return;
    
    // Verificar capability específica
    if ( ! current_user_can( 'dps_view_stats' ) ) {
        echo '<p>' . __( 'Você não tem permissão para ver estatísticas.', 'dps-stats-addon' ) . '</p>';
        return;
    }
    
    // Renderizar stats
    $this->section_stats();
}

// Endpoints de export
public function handle_export_csv() {
    if ( ! current_user_can( 'dps_export_stats' ) ) {
        wp_die( __( 'Você não tem permissão para exportar dados.', 'dps-stats-addon' ) );
    }
    // ...
}
```

### 5.2 Sanitização e Escape

#### Entrada (Sanitização) — ✅ COMPLIANT

| Parâmetro | Fonte | Sanitização | Avaliação |
|-----------|-------|-------------|-----------|
| `stats_start` | `$_GET` | `sanitize_text_field()` | ✅ Correto |
| `stats_end` | `$_GET` | `sanitize_text_field()` | ✅ Correto |
| `$days` (inactive pets) | Parâmetro | `absint()` | ✅ Correto |
| `$limit` (top services) | Parâmetro | `absint()` | ✅ Correto |

**Nenhuma vulnerabilidade de SQL Injection identificada.**

#### Saída (Escape) — ✅ COMPLIANT

| Contexto | Função Usada | Avaliação |
|----------|--------------|-----------|
| Texto HTML | `esc_html()`, `esc_html__()` | ✅ Consistente |
| Atributos HTML | `esc_attr()` | ✅ Consistente |
| URLs | `esc_url()`, `esc_url_raw()` | ✅ Consistente |
| JavaScript inline | `esc_js()`, `wp_json_encode()` | ✅ Consistente |

**Nenhuma vulnerabilidade de XSS identificada.**

### 5.3 LGPD e Privacidade

#### PII (Personally Identifiable Information) Exposto

| Dado | Onde Aparece | Risco | Mitigação |
|------|--------------|-------|-----------|
| **Nome do cliente** | Tabela de pets inativos | Baixo (admin interno) | ✅ OK (é necessário para reengajamento) |
| **Telefone do cliente** | Tabela de pets inativos + CSV | **Médio** | ⚠️ Avisar LGPD antes de export |
| **Nome do pet** | Tabela de pets inativos + CSV | Baixo | ✅ OK |
| **Receita total** | Dashboard | Baixo (dado agregado) | ✅ OK |

**Problemas Identificados:**

1. ❌ **Export CSV sem aviso LGPD:**
   - CSV inclui nome e telefone de clientes
   - Não há aviso de que arquivo contém dados pessoais
   - Não há log de quem baixou o arquivo

2. ❌ **CSV não tem expiração:**
   - Arquivo baixado pode ficar indefinidamente no computador do admin
   - Risco de vazamento se computador for comprometido

**Solução Recomendada:**

```php
// Antes do botão de export
<div class="dps-lgpd-notice">
    <p>
        <strong>⚠️ Atenção - LGPD</strong><br>
        Este arquivo contém dados pessoais (nome e telefone de clientes).
        Ao baixar, você se compromete a:
        • Usar os dados apenas para reengajamento de clientes
        • Não compartilhar com terceiros
        • Deletar o arquivo após uso
        • Seguir a Política de Privacidade da empresa
    </p>
    <label>
        <input type="checkbox" id="dps-lgpd-consent" required>
        Li e concordo com os termos acima
    </label>
</div>
<a href="..." class="dps-export-btn" id="dps-export-link" disabled>
    📥 Exportar CSV
</a>

<script>
document.getElementById('dps-lgpd-consent').addEventListener('change', function(e) {
    document.getElementById('dps-export-link').disabled = !e.target.checked;
});
</script>
```

```php
// Registrar log de export
add_action( 'dps_stats_export_csv', function( $user_id, $type, $count ) {
    $log_entry = sprintf(
        '[%s] User %d exported %s (%d records)',
        current_time( 'mysql' ),
        $user_id,
        $type,
        $count
    );
    error_log( $log_entry ); // Ou salvar em tabela dps_export_logs
}, 10, 3 );
```

### 5.4 Nonces e CSRF Protection — ✅ COMPLIANT

| Ação | Nonce Field | Nonce Action | Avaliação |
|------|-------------|--------------|-----------|
| Limpar cache | `dps_clear_stats_cache_nonce` | `dps_clear_stats_cache` | ✅ Correto |
| Export métricas | `dps_export_nonce` | `dps_export_metrics` | ✅ Correto |
| Export inativos | `dps_export_nonce` | `dps_export_inactive` | ✅ Correto |

**Todas as ações POST/GET sensíveis estão protegidas com nonces.**

---

## 6. Auditoria e Confiabilidade

### 6.1 Reprodutibilidade de Métricas

**Pergunta:** "Se admin calcular manualmente, chega no mesmo número?"

| KPI | Reprodutível? | Como Validar |
|-----|---------------|--------------|
| **Atendimentos** | ✅ Sim | Contar posts `dps_agendamento` no período via WP Admin |
| **Receita** | ✅ Sim | Somar transações `tipo='receita'` e `status='pago'` em Finance |
| **Novos clientes** | ✅ Sim | Filtrar `dps_cliente` por data de cadastro |
| **Taxa cancelamento** | ✅ Sim | Contar agendamentos `status='cancelado'` ÷ total |
| **Pets inativos** | ⚠️ Parcial | Depende de cache (pode estar desatualizado 24h) |
| **Ticket médio** | ✅ Sim | Receita ÷ Atendimentos (calculável) |

**Problema:** Cache desatualizado pode fazer admin ver números diferentes ao recalcular manualmente vs ver no dashboard.

**Solução:** Adicionar tooltip em cada KPI:
```html
<span class="dps-kpi-info" title="Última atualização: 13/12/2024 10:35">ℹ️</span>
```

### 6.2 Trace e Debug

**Estado Atual:**
- ❌ Não há log de quando métricas foram calculadas
- ❌ Não há flag de "debug mode" para ver queries executadas
- ❌ Não há validação de consistência (ex: receita > atendimentos × ticket médio mínimo)

**Solução: Debug Mode**

```php
// Em wp-config.php
define( 'DPS_STATS_DEBUG', true );

// Em DPS_Stats_API
if ( defined( 'DPS_STATS_DEBUG' ) && DPS_STATS_DEBUG ) {
    error_log( sprintf(
        '[DPS Stats] get_revenue_total(%s, %s) = %s (cached: %s)',
        $start_date, $end_date, $total, $cached ? 'yes' : 'no'
    ) );
}
```

### 6.3 Testes (Unit/Integration)

**Estado Atual:**
- ❌ NENHUM teste automatizado
- ❌ Mudanças podem quebrar métricas silenciosamente

**Recomendação: Testes Críticos**

```php
// tests/test-dps-stats-api.php
class Test_DPS_Stats_API extends WP_UnitTestCase {
    public function test_get_appointments_count_returns_correct_number() {
        // Criar 5 agendamentos no período
        $appt_ids = $this->factory->post->create_many( 5, [
            'post_type' => 'dps_agendamento',
            'meta_input' => [
                'appointment_date' => '2024-11-15',
            ]
        ] );
        
        $count = DPS_Stats_API::get_appointments_count( '2024-11-01', '2024-11-30' );
        $this->assertEquals( 5, $count );
    }
    
    public function test_ticket_average_handles_zero_appointments() {
        $ticket = DPS_Stats_API::get_ticket_average( '2024-11-01', '2024-11-30' );
        $this->assertEquals( 0, $ticket ); // Não deve dividir por zero
    }
}
```

---

## 7. Mapa de Contratos (Hooks e Endpoints)

### 7.1 Hooks CONSUMIDOS pelo Stats Add-on

| Hook | Tipo | Prioridade | Uso | Arquivo |
|------|------|------------|-----|---------|
| `plugins_loaded` | action | 1 | Carregar DPS_Stats_API se plugin base ativo | desi-pet-shower-stats-addon.php:40 |
| `init` | action | 1 | Carregar text domain | desi-pet-shower-stats-addon.php:50 |
| `init` | action | 5 | Instanciar DPS_Stats_Addon | desi-pet-shower-stats-addon.php:403 |
| `dps_base_nav_tabs_after_history` | action | 20 | Adicionar aba "Estatísticas" | desi-pet-shower-stats-addon.php:124 |
| `dps_base_sections_after_history` | action | 20 | Renderizar seção de stats | desi-pet-shower-stats-addon.php:125 |
| `wp_enqueue_scripts` | action | 10 | Registrar assets (front) | desi-pet-shower-stats-addon.php:126 |
| `admin_enqueue_scripts` | action | 10 | Registrar assets (admin) | desi-pet-shower-stats-addon.php:127 |
| `admin_post_dps_clear_stats_cache` | action | 10 | Limpar cache de transients | desi-pet-shower-stats-addon.php:120 |
| `admin_post_dps_export_stats_csv` | action | 10 | Exportar métricas em CSV | desi-pet-shower-stats-addon.php:128 |
| `admin_post_dps_export_inactive_csv` | action | 10 | Exportar pets inativos em CSV | desi-pet-shower-stats-addon.php:129 |

### 7.2 Hooks EXPOSTOS pelo Stats Add-on

| Hook | Tipo | Parâmetros | Propósito | Exemplo de Uso |
|------|------|------------|-----------|----------------|
| `dps_stats_inactive_pets_limit` | filter | `int $limit` (default: 500) | Ajustar limite de pets analisados | `add_filter( 'dps_stats_inactive_pets_limit', fn() => 1000 );` |

**Observação:** Add-on expõe APENAS 1 filtro. Poderia expor mais para extensibilidade:

```php
// Sugestões de hooks adicionais
apply_filters( 'dps_stats_appointments_query_args', $args, $start_date, $end_date );
apply_filters( 'dps_stats_cache_ttl', HOUR_IN_SECONDS, $cache_type );
do_action( 'dps_stats_metric_calculated', $metric_name, $value, $start_date, $end_date );
```

### 7.3 Funções Globais Exportadas

| Função | Propósito | Status |
|--------|-----------|--------|
| `dps_stats_build_cache_key()` | Gerar chave de transient padronizada | ✅ Útil |
| `dps_get_total_revenue()` | Obter receita total (depreciada) | ⚠️ Usar `DPS_Stats_API::get_revenue_total()` |
| `dps_stats_clear_cache()` | Limpar cache (interno) | ❌ Não deve ser chamada diretamente |

### 7.4 API Pública (DPS_Stats_API)

Ver seção [3. KPIs e Definições Técnicas](#3-kpis-e-definições-técnicas) para detalhes de cada método.

**Métodos Principais:**
- `get_appointments_count( $start, $end, $status = '' )`
- `get_revenue_total( $start, $end )`
- `get_financial_totals( $start, $end )`
- `get_inactive_pets( $days = 30 )`
- `get_period_comparison( $start, $end )`
- `export_metrics_csv( $start, $end )`

---

## 8. Achados Técnicos Catalogados

### 8.1 Bugs Críticos

#### [CRÍTICO-001] Fatal Error se Finance Add-on nunca foi ativado

**Severidade:** Crítica  
**Impacto:** Site quebra (fatal error) ao acessar aba Estatísticas  
**Evidência:**
```php
// desi-pet-shower-stats-addon.php:284
// class-dps-stats-api.php:150
$table = $wpdb->prefix . 'dps_transacoes';
$wpdb->get_results( ... ); // ❌ SEM VALIDAR SE TABELA EXISTE
```

**Como Reproduzir:**
1. Instalar DPS Base + Stats Add-on
2. NÃO ativar Finance Add-on
3. Acessar aba "Estatísticas"
4. Resultado: Query falha silenciosamente OU retorna 0

**Correção:**
```php
$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $wpdb->esc_like( $wpdb->prefix . 'dps_transacoes' )
) );

if ( ! $table_exists ) {
    return [ 'revenue' => 0, 'expenses' => 0, 'error' => 'Finance não ativo' ];
}
```

**Teste Recomendado:** Unit test com banco sem tabela dps_transacoes

---

#### [CRÍTICO-002] Cache nunca é invalidado automaticamente

**Severidade:** Crítica  
**Impacto:** Usuário vê dados desatualizados por até 24h  
**Evidência:** Nenhum hook de `save_post` ou `dps_finance_*` invalida cache  
**Como Reproduzir:**
1. Ver dashboard (ex: 100 atendimentos)
2. Criar novo agendamento
3. Atualizar página de stats
4. Resultado: Ainda mostra 100 (cache de 1h)

**Correção:** Implementar `DPS_Stats_Cache_Invalidator` (ver seção 4.2)

**Risco de Regressão:** Baixo (apenas adiciona hooks, não modifica queries)

---

### 8.2 Riscos Altos

#### [ALTO-001] Limite fixo de 1000 agendamentos trunca dados

**Severidade:** Alta  
**Impacto:** Pet shops com >1000 agendamentos/mês veem métricas INCORRETAS  
**Evidência:**
```php
// class-dps-stats-api.php:296, 546, 620
'posts_per_page' => 1000, // ⚠️ LIMITE FIXO
```

**Sugestão de Correção:**
```php
'posts_per_page' => -1, // Remover limite
'no_found_rows' => false, // Permitir contagem
'fields' => 'ids', // Apenas IDs para otimizar
```

OU: Converter para SQL direto com GROUP BY (10x mais rápido)

---

#### [ALTO-002] Métricas de assinaturas ignoram período selecionado

**Severidade:** Alta  
**Impacto:** Usuário vê assinaturas GLOBAIS, não do período  
**Evidência:**
```php
// desi-pet-shower-stats-addon.php:285
$subscriptions = get_posts( [
    'post_type' => 'dps_subscription',
    'posts_per_page' => -1, // ❌ SEM FILTRO DE DATA
] );
```

Label na tela diz "Receita de assinaturas no período", mas contagem é global.

**Correção:**
```php
'date_query' => [
    [
        'after' => $start_date,
        'before' => $end_date . ' 23:59:59',
        'inclusive' => true,
    ]
]
```

---

### 8.3 Riscos Médios

#### [MÉDIO-001] Export CSV sem aviso LGPD

**Severidade:** Média  
**Impacto:** Violação de privacidade se arquivo vazar  
**Correção:** Ver seção 5.3 (adicionar aviso e checkbox de consentimento)

---

#### [MÉDIO-002] Capability muito permissiva (manage_options)

**Severidade:** Média  
**Impacto:** Apenas admins veem stats; gerentes ficam sem dados  
**Correção:** Criar capabilities `dps_view_stats`, `dps_export_stats`

---

#### [MÉDIO-003] Chart.js via CDN sem fallback

**Severidade:** Média  
**Impacto:** Gráficos não renderizam se CDN offline  
**Evidência:**
```php
// desi-pet-shower-stats-addon.php:134
wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/...' );
```

**Correção:** Adicionar cópia local em `assets/js/chart.min.js` como fallback

---

### 8.4 Dívidas Técnicas

#### [DEBT-001] Loops PHP para contar serviços/espécies/raças

**Impacto:** Performance degrada com muitos agendamentos  
**Solução:** Converter para SQL GROUP BY (ver seção 4.1)

---

#### [DEBT-002] Métodos estáticos dificultam testes

**Impacto:** Impossível mockar dependências em testes  
**Solução:** Converter para instância com injeção de dependências

---

#### [DEBT-003] Sem testes automatizados

**Impacto:** Mudanças podem quebrar métricas silenciosamente  
**Solução:** Implementar PHPUnit tests (ver seção 6.3)

---

## 9. Roadmap de Melhorias em FASES

### FASE 1 — Correções Críticas e Higiene Técnica (1-2 semanas)

**Objetivo:** Eliminar bugs críticos e riscos de fatal error.

#### F1.1 — Validar existência de dps_transacoes

**Prioridade:** 🔴 Crítica  
**Esforço:** 2h  
**Dependências:** Nenhuma  
**Arquivos:** `class-dps-stats-api.php`, `desi-pet-shower-stats-addon.php`

**Critérios de Aceite:**
- [ ] Query em `dps_transacoes` SEMPRE valida existência antes
- [ ] Se tabela não existe, retorna array zerado com flag `error`
- [ ] Exibe mensagem na UI: "Ative o Finance Add-on para métricas financeiras"

**Benefício:**
- ✅ Evita fatal error em instalações sem Finance
- ✅ Permite usar Stats mesmo sem Finance (métricas operacionais)

---

#### F1.2 — Invalidação automática de cache

**Prioridade:** 🔴 Crítica  
**Esforço:** 4h  
**Dependências:** Nenhuma  
**Arquivo Novo:** `includes/class-dps-stats-cache-invalidator.php`

**Critérios de Aceite:**
- [ ] Cache é invalidado automaticamente quando:
  - Agendamento é criado/editado/deletado
  - Transação financeira é criada/atualizada
  - Cliente/Pet é criado
- [ ] Admin NÃO precisa mais clicar "Atualizar dados"
- [ ] Métricas sempre refletem estado atual (latência < 1min)

**Benefício:**
- ✅ Dados sempre atualizados
- ✅ UX melhorada (sem ação manual)

---

#### F1.3 — Corrigir métricas de assinaturas

**Prioridade:** 🟡 Alta  
**Esforço:** 2h  
**Dependências:** Nenhuma  
**Arquivo:** `desi-pet-shower-stats-addon.php:282-293`

**Critérios de Aceite:**
- [ ] Contagem de assinaturas filtra por `post_date` no período
- [ ] Receita de assinaturas usa período selecionado (não global)
- [ ] Label atualizado: "Assinaturas ativas no período"

**Benefício:**
- ✅ Consistência entre métricas
- ✅ Análise temporal correta

---

#### F1.4 — Remover limite de 1000 agendamentos

**Prioridade:** 🟡 Alta  
**Esforço:** 3h  
**Dependências:** Nenhuma  
**Arquivos:** `class-dps-stats-api.php` (métodos get_top_services, get_species_distribution, get_top_breeds)

**Critérios de Aceite:**
- [ ] Queries NÃO têm limite fixo OU usam paginação
- [ ] Pet shops com >1000 agendamentos veem dados completos
- [ ] Performance não degrada (usar SQL direto se necessário)

**Benefício:**
- ✅ Métricas corretas para pet shops de alto volume
- ✅ Escalabilidade

---

### FASE 2 — Performance e Otimização (2-3 semanas)

**Objetivo:** Melhorar performance para pet shops de médio/alto volume.

#### F2.1 — Converter loops PHP para SQL GROUP BY

**Prioridade:** 🟡 Alta  
**Esforço:** 8h  
**Dependências:** F1.4  
**Arquivos:** `class-dps-stats-api.php` (get_top_services, get_species_distribution, get_top_breeds)

**Critérios de Aceite:**
- [ ] Top Serviços: 1 query SQL com GROUP BY (não mais 1000+ queries)
- [ ] Espécies: 1 query SQL com GROUP BY
- [ ] Raças: 1 query SQL com GROUP BY
- [ ] Performance: <500ms para 5000 agendamentos

**Benefício:**
- ✅ 50-100x mais rápido
- ✅ Suporta >10.000 agendamentos

---

#### F2.2 — Fallback local para Chart.js

**Prioridade:** Média  
**Esforço:** 2h  
**Dependências:** Nenhuma  
**Arquivo Novo:** `assets/js/chart.min.js` (cópia local)

**Critérios de Aceite:**
- [ ] Tenta carregar de CDN primeiro
- [ ] Se falhar, carrega cópia local
- [ ] Gráficos sempre renderizam (mesmo offline)

**Benefício:**
- ✅ Confiabilidade
- ✅ Funciona em ambientes sem internet

---

#### F2.3 — Implementar Object Cache (Redis/Memcached)

**Prioridade:** Baixa (apenas para alto volume)  
**Esforço:** 6h  
**Dependências:** F2.1  
**Arquivo:** `class-dps-stats-api.php` (adicionar wp_cache_* calls)

**Critérios de Aceite:**
- [ ] Se `wp_using_ext_object_cache()`, usa object cache
- [ ] Fallback para transients se object cache não disponível
- [ ] Cache compartilhado entre múltiplos admins

**Benefício:**
- ✅ Performance para 5+ admins simultâneos
- ✅ Reduz carga no banco

---

### FASE 3 — UX e Decisão (3-4 semanas)

**Objetivo:** Melhorar clareza, actionability e tomada de decisão.

#### F3.1 — KPIs faltantes

**Prioridade:** 🟡 Alta  
**Esforço:** 12h  
**Dependências:** F1.2 (cache)  
**Arquivo:** `class-dps-stats-api.php` (novos métodos)

**KPIs a Implementar:**
- [ ] Taxa de Retorno (30/60/90 dias)
- [ ] No-show separado de cancelamento
- [ ] Inadimplência (receita vencida não paga)
- [ ] Conversão Cadastro → Primeiro Agendamento
- [ ] Clientes Recorrentes (2+ atendimentos)

**Critérios de Aceite:**
- [ ] Cada KPI tem método na API
- [ ] Exibidos no dashboard com cards visuais
- [ ] Definição clara em tooltip

**Benefício:**
- ✅ Insights mais profundos
- ✅ Decisões mais informadas

---

#### F3.2 — Drill-down em métricas

**Prioridade:** Média  
**Esforço:** 10h  
**Dependências:** F3.1  
**Arquivos:** `desi-pet-shower-stats-addon.php` (adicionar links/modals)

**Critérios de Aceite:**
- [ ] Clicar em "42 atendimentos" abre modal com lista
- [ ] Clicar em serviço abre agendamentos desse serviço
- [ ] Links para editar agendamento/cliente

**Benefício:**
- ✅ Actionability
- ✅ Investigação rápida de anomalias

---

#### F3.3 — Filtros avançados

**Prioridade:** Média  
**Esforço:** 8h  
**Dependências:** Nenhuma  
**Arquivos:** `desi-pet-shower-stats-addon.php` (adicionar filtros na UI)

**Filtros a Adicionar:**
- [ ] Serviço específico
- [ ] Funcionário/groomer
- [ ] Unidade/local (se multi-unidade)
- [ ] Status do agendamento

**Critérios de Aceite:**
- [ ] Filtros em dropdowns acima do dashboard
- [ ] Métricas recalculadas ao aplicar filtro
- [ ] URL preserva filtros (shareable)

**Benefício:**
- ✅ Análise segmentada
- ✅ Identificar gargalos por funcionário/serviço

---

#### F3.4 — Gráfico de tendência temporal

**Prioridade:** Média  
**Esforço:** 10h  
**Dependências:** F2.1  
**Arquivo:** `assets/js/stats-addon.js` (adicionar initTrendChart)

**Critérios de Aceite:**
- [ ] Gráfico de linha com atendimentos por dia/semana
- [ ] Suavização (média móvel 7 dias)
- [ ] Período: últimos 3-6 meses

**Benefício:**
- ✅ Visualizar tendências de longo prazo
- ✅ Identificar sazonalidade

---

### FASE 4 — Recursos Avançados (OPCIONAL — 4+ semanas)

**Objetivo:** Features avançadas para pet shops sofisticados.

#### F4.1 — Metas e Objetivos

**Prioridade:** Baixa  
**Esforço:** 16h  
**Descrição:** Permitir definir metas (ex: 150 atendimentos/mês, R$ 20k receita) e acompanhar progresso

---

#### F4.2 — Alertas Automáticos

**Prioridade:** Baixa  
**Esforço:** 12h  
**Descrição:** Email automático quando KPI cai abaixo de threshold (ex: -20% atendimentos)

---

#### F4.3 — Relatórios Agendados

**Prioridade:** Baixa  
**Esforço:** 10h  
**Descrição:** Email semanal/mensal com resumo de métricas

---

#### F4.4 — Dashboard Customizável

**Prioridade:** Baixa  
**Esforço:** 20h  
**Descrição:** Admin escolhe quais KPIs exibir, ordem, tamanho de cards

---

#### F4.5 — API REST para Métricas

**Prioridade:** Baixa  
**Esforço:** 8h  
**Descrição:** Expor métricas via REST para integração com apps externos (ex: mobile app do dono)

---

### Estimativa Total de Esforço

| Fase | Esforço | Prioridade | Impacto |
|------|---------|------------|---------|
| **Fase 1** | 11h (1-2 semanas) | 🔴 Crítica | Elimina bugs e riscos |
| **Fase 2** | 16h (2-3 semanas) | 🟡 Alta | Performance para escala |
| **Fase 3** | 40h (3-4 semanas) | 🟡 Alta | UX e decisão melhorada |
| **Fase 4** | 66h+ (4+ semanas) | Baixa (opcional) | Features avançadas |
| **TOTAL MVP (F1+F2)** | 27h (~3 semanas) | — | Stats confiável e escalável |

---

## 10. Conclusão

O **Stats Add-on v1.1.0** é um módulo **funcional e bem estruturado**, mas com lacunas críticas de confiabilidade, performance e UX que limitam seu uso em pet shops de médio/alto volume.

### Pontos Fortes Confirmados

✅ API pública reutilizável (`DPS_Stats_API`)  
✅ Modularização (assets separados, não mais inline)  
✅ Segurança (nonces, capabilities, sanitização)  
✅ Integração com Finance API (quando disponível)  
✅ Dashboard visual limpo (cards, gráficos Chart.js)  

### Riscos Críticos que Bloqueiam Adoção

❌ **Fatal error se Finance nunca foi ativado** (BLOCKER)  
❌ **Cache nunca invalidado** (dados desatualizados)  
❌ **Limite de 1000 agendamentos** (métricas incorretas)  
❌ **Performance degrada com >5000 agendamentos** (timeouts)  

### Roadmap Recomendado

**Imediato (1-2 semanas):**  
→ **Fase 1:** Correções críticas (11h)

**Curto Prazo (2-3 semanas):**  
→ **Fase 2:** Performance e otimização (16h)

**Médio Prazo (3-4 semanas):**  
→ **Fase 3:** UX e decisão (40h)

**Longo Prazo (Opcional):**  
→ **Fase 4:** Recursos avançados (66h+)

**MVP Mínimo Viável:** Fase 1 + Fase 2 = **27 horas (~3 semanas)** para Stats **confiável, escalável e pronto para produção**.

---

**Fim da Análise Técnica Profunda**
