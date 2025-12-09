# Análise Profunda do Add-on Campanhas & Fidelidade

**Plugin:** DPS by PRObst – Campanhas & Fidelidade  
**Versão Analisada:** 1.2.0  
**Data da Análise:** 09/12/2024  
**Autor da Análise:** Agente de Análise de Código  
**Total de Linhas:** ~2.800 linhas (PHP: ~2.460 + CSS: ~490 + JS: ~220)

---

## ÍNDICE

1. [MAPEAMENTO COMPLETO DO ADD-ON](#1-mapeamento-completo-do-add-on)
2. [ARQUITETURA E ORGANIZAÇÃO DE CÓDIGO](#2-arquitetura-e-organização-de-código)
3. [MODELAGEM DE FIDELIDADE (Pontos, Saldo, Níveis)](#3-modelagem-de-fidelidade-pontos-saldo-níveis)
4. [MODELAGEM DE CAMPANHAS](#4-modelagem-de-campanhas)
5. [FLUXOS DE NEGÓCIO](#5-fluxos-de-negócio)
6. [SEGURANÇA E INTEGRIDADE](#6-segurança-e-integridade)
7. [PERFORMANCE E ESCALABILIDADE](#7-performance-e-escalabilidade)
8. [UX E LAYOUT](#8-ux-e-layout)
9. [PROBLEMAS IDENTIFICADOS](#9-problemas-identificados)
10. [ROADMAP DE MELHORIAS EM FASES](#10-roadmap-de-melhorias-em-fases)
11. [CONCLUSÃO](#11-conclusão)

---

## 1. MAPEAMENTO COMPLETO DO ADD-ON

### 1.1 Estrutura de Arquivos

```
add-ons/desi-pet-shower-loyalty_addon/
├── desi-pet-shower-loyalty.php      # Plugin principal (~1.860 linhas)
│   ├── class DPS_Loyalty_Addon      # Orquestração, CPT, menus, renderização
│   ├── class DPS_Loyalty_Referrals  # Sistema Indique e Ganhe
│   └── 18 funções globais           # API legada (wrappers para DPS_Loyalty_API)
├── includes/
│   └── class-dps-loyalty-api.php    # API pública centralizada (~600 linhas)
├── assets/
│   ├── css/
│   │   └── loyalty-addon.css        # Estilos do dashboard (~490 linhas)
│   └── js/
│       └── loyalty-addon.js         # Interatividade (~220 linhas)
├── README.md                         # Documentação funcional
└── uninstall.php                     # Limpeza na desinstalação (~57 linhas)
```

### 1.2 Dependências Externas

| Dependência | Versão | Uso | Criticidade |
|-------------|--------|-----|-------------|
| **Plugin Base DPS** | Requerido | Estrutura de navegação, CPT Helper, hooks | **CRÍTICA** |
| **Finance Add-on** | Opcional | Bonificações automáticas via `dps_finance_booking_paid` | **ALTA** |
| **Registration Add-on** | Opcional | Captura código de indicação no cadastro | **ALTA** |
| **Client Portal Add-on** | Opcional | Exibição de código de indicação (API disponível) | **MÉDIA** |
| **Communications Add-on** | Opcional | Disparo de campanhas (não integrado ainda) | **BAIXA** |

### 1.3 Hooks Consumidos

| Hook | Origem | Uso no Loyalty | Prioridade |
|------|--------|----------------|------------|
| `plugins_loaded` | WordPress | Verificação do plugin base | 1 |
| `init` | WordPress | Carregamento de text domain | 1 |
| `init` | WordPress | Inicialização da classe | 5 |
| `init` | WordPress | Registro do CPT `dps_campaign` | 10 |
| `updated_post_meta` | WordPress | Detecta status "finalizado_pago" para pontuar | 10 |
| `added_post_meta` | WordPress | Detecta status "finalizado_pago" para pontuar | 10 |
| `dps_finance_booking_paid` | Finance Add-on | Bonifica indicações na primeira compra | 10 |
| `dps_registration_after_client_created` | Registration | Registra indicação no cadastro | 10 |
| `save_post_dps_cliente` | WordPress | Gera código de indicação para novo cliente | 10 |

### 1.4 Hooks Disparados

| Hook | Quando Disparado | Parâmetros | Consumidores |
|------|------------------|------------|--------------|
| `dps_loyalty_points_added` | Após adicionar pontos | `$client_id`, `$points`, `$context` | Stats, Communications (potencial) |
| `dps_loyalty_points_redeemed` | Após resgatar pontos | `$client_id`, `$points`, `$context` | Stats |
| `dps_loyalty_points_awarded_appointment` | Após pontuar por atendimento | `$client_id`, `$points`, `$appointment_id`, `$value` | Stats |
| `dps_loyalty_tier_bonus_applied` | Quando multiplicador é aplicado | `$client_id`, `$bonus`, `$multiplier` | - |

### 1.5 Tabelas de Banco de Dados

**{prefix}dps_referrals** (v1.0.0):

```sql
CREATE TABLE {prefix}dps_referrals (
    id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
    referrer_client_id BIGINT(20) unsigned NOT NULL,
    referee_client_id BIGINT(20) unsigned NULL,
    referral_code VARCHAR(50) NOT NULL,
    first_booking_id BIGINT(20) unsigned NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    reward_type_referrer VARCHAR(20) NULL,
    reward_value_referrer DECIMAL(12,2) NULL,
    reward_type_referee VARCHAR(20) NULL,
    reward_value_referee DECIMAL(12,2) NULL,
    meta LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY referrer_idx (referrer_client_id),
    KEY referee_idx (referee_client_id),
    KEY code_idx (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Índices existentes:** ✅ Adequados para consultas atuais

### 1.6 Options Armazenadas

| Option Key | Tipo | Uso |
|------------|------|-----|
| `dps_loyalty_settings` | Serialized Array | Configurações (taxa de pontos, recompensas, regras) |
| `dps_referrals_db_version` | String | Controle de versão da tabela |

### 1.7 Metas Utilizadas

**Em `dps_cliente`:**
- `dps_loyalty_points` (int) - Saldo atual de pontos
- `dps_loyalty_points_log` (array, múltiplos) - Histórico de movimentações
- `_dps_referral_code` (string) - Código único de indicação
- `_dps_credit_balance` (int) - Saldo de crédito em centavos

**Em `dps_agendamento`:**
- `dps_loyalty_points_awarded` (bool) - Flag para evitar pontuação dupla

**Em `dps_campaign`:**
- `dps_campaign_type` - Tipo (percentage, fixed, double_points)
- `dps_campaign_eligibility` - Critérios de elegibilidade
- `dps_campaign_inactive_days` - Dias de inatividade
- `dps_campaign_points_threshold` - Pontos mínimos
- `dps_campaign_start_date` / `dps_campaign_end_date` - Período
- `dps_campaign_pending_offers` - Lista de clientes elegíveis
- `dps_campaign_last_audit` - Última execução da auditoria

---

## 2. ARQUITETURA E ORGANIZAÇÃO DE CÓDIGO

### 2.1 Avaliação Geral

**Nota: ⭐⭐⭐⭐ (4/5 - BOM)**

**Pontos Fortes:**
- ✅ API pública centralizada (`DPS_Loyalty_API`) com métodos estáticos
- ✅ Singleton pattern correto em `DPS_Loyalty_Referrals`
- ✅ Separação clara entre pontos e indicações
- ✅ Funções globais como wrappers simples (facilita migração)
- ✅ DocBlocks completos na API

**Pontos de Melhoria:**
- ⚠️ Arquivo principal com ~1.860 linhas (poderia ser dividido)
- ⚠️ Métodos de renderização extensos (60+ linhas)
- ⚠️ Duas classes grandes no mesmo arquivo

### 2.2 Análise de Classes

#### 2.2.1 DPS_Loyalty_Addon (Classe Principal)

**Arquivo:** `desi-pet-shower-loyalty.php` (linhas 57-1241)  
**Responsabilidades:** CPT, menus, renderização, configurações, pontos automáticos

| Método | Linhas | Responsabilidade | Avaliação |
|--------|--------|------------------|-----------|
| `register_post_type()` | ~40 | Registro do CPT via helper | ✅ OK |
| `render_loyalty_page()` | ~50 | Orquestração de renderização | ✅ OK |
| `render_dashboard_tab()` | ~55 | Dashboard com métricas | ✅ OK |
| `render_referrals_tab()` | ~100 | Tabela de indicações | ⚠️ Poderia usar template |
| `render_settings_tab()` | ~70 | Formulário de configurações | ⚠️ Poderia usar template |
| `render_clients_tab()` | ~130 | Consulta de cliente | ⚠️ Grande, muita lógica de UI |
| `find_eligible_clients_for_campaign()` | ~30 | Busca elegíveis | ⚠️ Queries N+1 |
| `maybe_award_points_on_status_change()` | ~40 | Pontua automaticamente | ✅ OK |
| `calculate_points_from_value()` | ~30 | Calcula pontos com multiplicador | ✅ OK (v1.2.0) |

#### 2.2.2 DPS_Loyalty_Referrals (Sistema de Indicações)

**Arquivo:** `desi-pet-shower-loyalty.php` (linhas 1243-1512)  
**Responsabilidades:** Tabela, registro, bonificação de indicações

| Método | Linhas | Avaliação |
|--------|--------|-----------|
| `create_table()` | ~25 | ✅ OK |
| `maybe_register_referral()` | ~25 | ✅ OK, validações corretas |
| `handle_booking_paid()` | ~35 | ✅ OK, proteções anti-fraude |
| `apply_rewards()` | ~30 | ✅ OK |
| `apply_single_reward()` | ~20 | ✅ OK |

#### 2.2.3 DPS_Loyalty_API (API Pública)

**Arquivo:** `includes/class-dps-loyalty-api.php` (~600 linhas)  
**Responsabilidades:** Interface pública para pontos, créditos, indicações, métricas

**Métodos Principais:**

| Categoria | Métodos |
|-----------|---------|
| **Pontos** | `add_points()`, `get_points()`, `redeem_points()`, `get_points_history()` |
| **Créditos** | `add_credit()`, `get_credit()`, `use_credit()` |
| **Indicações** | `get_referral_code()`, `get_referral_url()`, `get_referral_stats()`, `get_referrals()`, `export_referrals_csv()` |
| **Níveis** | `get_loyalty_tier()`, `get_default_tiers()`, `get_clients_by_tier()` |
| **Métricas** | `get_global_metrics()`, `get_top_clients()`, `calculate_points_for_amount()` |

**Avaliação:** ⭐⭐⭐⭐⭐ (5/5 - EXCELENTE)

### 2.3 Funções Globais

O add-on expõe 18 funções globais (linhas 1537-1860), todas com padrão `if ( ! function_exists() )`:

| Função | Propósito | Depreciação |
|--------|-----------|-------------|
| `dps_loyalty_add_points()` | Adicionar pontos | - |
| `dps_loyalty_get_points()` | Obter saldo | - |
| `dps_loyalty_redeem_points()` | Resgatar pontos | - |
| `dps_loyalty_log_event()` | Registrar evento | - |
| `dps_loyalty_get_logs()` | Obter histórico | - |
| `dps_loyalty_parse_money_br()` | Converter moeda | ✅ Use `DPS_Money_Helper` |
| `dps_format_money_br()` | Formatar moeda | ✅ Use `DPS_Money_Helper` |
| `dps_loyalty_generate_referral_code()` | Gerar código | - |
| `dps_loyalty_get_referral_code()` | Obter código | - |
| `dps_referral_code_exists()` | Verificar unicidade | - |
| `dps_referrals_create()` | Criar indicação | - |
| `dps_referrals_find_pending_by_referee()` | Buscar pendente | - |
| `dps_referrals_mark_rewarded()` | Marcar recompensada | - |
| `dps_referrals_get_settings()` | Obter configurações | - |
| `dps_referrals_register_signup()` | Registrar signup | - |
| `dps_loyalty_add_credit()` | Adicionar crédito | - |
| `dps_loyalty_get_credit()` | Obter crédito | - |
| `dps_loyalty_use_credit()` | Usar crédito | - |

---

## 3. MODELAGEM DE FIDELIDADE (Pontos, Saldo, Níveis)

### 3.1 Armazenamento de Pontos

**Estratégia:** Campo de saldo + log de movimentações em `post_meta`

```php
// Saldo atual
get_post_meta( $client_id, 'dps_loyalty_points', true ); // int

// Histórico de movimentações (múltiplos registros)
get_post_meta( $client_id, 'dps_loyalty_points_log' ); // array of arrays
// Cada entrada:
// [
//     'action'  => 'add' | 'redeem',
//     'points'  => 50,
//     'context' => 'appointment_payment',
//     'date'    => '2024-12-09 14:30:00',
// ]
```

**Prós:**
- ✅ Saldo calculado em tempo constante O(1)
- ✅ Histórico preservado para auditoria
- ✅ Simples de implementar

**Contras:**
- ⚠️ Histórico pode crescer muito (um registro por movimentação)
- ⚠️ Difícil consultar "pontos ganhos nos últimos 30 dias"
- ⚠️ Sem data de expiração por lote de pontos

### 3.2 Acúmulo de Pontos

**Quando:** Status do agendamento muda para `finalizado_pago`

**Hook:** `updated_post_meta` / `added_post_meta`

**Fluxo:**
```
1. Meta 'appointment_status' é atualizada para 'finalizado_pago'
2. maybe_award_points_on_status_change() é chamado
3. Verifica se pontos já foram concedidos (flag dps_loyalty_points_awarded)
4. Obtém valor do atendimento (meta ou tabela dps_transacoes)
5. Calcula pontos = valor / brl_per_point
6. Aplica multiplicador do nível (Bronze=1x, Prata=1.5x, Ouro=2x)
7. Credita pontos e marca flag
8. Dispara hook dps_loyalty_points_awarded_appointment
```

**Código relevante (linhas 1150-1191):**
```php
public function maybe_award_points_on_status_change( $meta_id, $object_id, $meta_key, $meta_value ) {
    if ( 'appointment_status' !== $meta_key || 'finalizado_pago' !== $meta_value ) {
        return;
    }
    // ... validações ...
    $points = $this->calculate_points_from_value( $total_value, $client_id );
    if ( $points > 0 ) {
        dps_loyalty_add_points( $client_id, $points, 'appointment_payment' );
        update_post_meta( $object_id, 'dps_loyalty_points_awarded', 1 );
    }
}
```

### 3.3 Níveis de Fidelidade

**Configuração padrão:**

| Nível | Pontos Mínimos | Multiplicador | Ícone |
|-------|----------------|---------------|-------|
| Bronze | 0 | 1.0x | 🥉 |
| Prata | 500 | 1.5x | 🥈 |
| Ouro | 1000 | 2.0x | 🥇 |

**Determinação do nível (`DPS_Loyalty_API::get_loyalty_tier()`):**
```php
foreach ( $tiers as $key => $tier ) {
    if ( $points >= $tier['min_points'] ) {
        $current_tier = $key;
    }
}
// Maior tier cujo min_points foi atingido
```

**Aplicação do multiplicador (v1.2.0):**
```php
private function calculate_points_from_value( $value, $client_id = 0 ) {
    $base_points = floor( $value / $brl_per_pt );
    
    if ( $client_id > 0 ) {
        $tier_info = DPS_Loyalty_API::get_loyalty_tier( $client_id );
        $multiplier = $tier_info['multiplier'];
        $total_points = floor( $base_points * $multiplier );
        
        // Hook para rastrear bônus
        if ( $bonus > 0 ) {
            do_action( 'dps_loyalty_tier_bonus_applied', $client_id, $bonus, $multiplier );
        }
    }
    
    return (int) $total_points;
}
```

### 3.4 Sistema de Créditos

**Diferença de pontos x créditos:**
- **Pontos:** Unidade de fidelidade, precisa ser "convertida" em benefício
- **Créditos:** Valor monetário (em centavos), pode ser usado diretamente como pagamento

**Armazenamento:**
```php
get_post_meta( $client_id, '_dps_credit_balance', true ); // int (centavos)
```

**Uso atual:**
- Recompensas de indicação podem ser créditos (tipo `fixed`)
- API disponível (`add_credit`, `get_credit`, `use_credit`)
- **NÃO** integrado automaticamente com Finance para pagamento

---

## 4. MODELAGEM DE CAMPANHAS

### 4.1 CPT `dps_campaign`

**Registro via DPS_CPT_Helper:**
```php
$this->cpt_helper = new DPS_CPT_Helper(
    'dps_campaign',
    [
        'name'          => 'Campanhas',
        'singular_name' => 'Campanha',
        // ...
    ],
    [
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => false, // Acessado via página do add-on
        'supports'     => [ 'title', 'editor' ],
    ]
);
```

### 4.2 Tipos de Campanha Suportados

| Tipo | Código | Descrição |
|------|--------|-----------|
| Desconto percentual | `percentage` | X% de desconto |
| Desconto fixo | `fixed` | R$ X,XX de desconto |
| Pontos em dobro | `double_points` | 2x pontos durante a campanha |

### 4.3 Critérios de Elegibilidade

**Critérios disponíveis:**
1. **Clientes inativos:** Sem atendimento há X dias
2. **Pontos mínimos:** Clientes com mais de N pontos

**Armazenamento:**
```php
// Array de critérios selecionados
$eligibility = get_post_meta( $campaign_id, 'dps_campaign_eligibility', true );
// ['inactive', 'points'] ou apenas um

// Parâmetros
$inactive_days = get_post_meta( $campaign_id, 'dps_campaign_inactive_days', true );
$points_threshold = get_post_meta( $campaign_id, 'dps_campaign_points_threshold', true );
```

### 4.4 Período de Vigência

```php
$start_date = get_post_meta( $campaign_id, 'dps_campaign_start_date', true ); // 'Y-m-d'
$end_date = get_post_meta( $campaign_id, 'dps_campaign_end_date', true );     // 'Y-m-d'
```

### 4.5 Rotina de Auditoria

**Funcionalidade:** Identifica clientes elegíveis e salva lista

**Fluxo:**
```
1. Admin clica "Rodar rotina de elegibilidade" no dashboard
2. handle_campaign_audit() é chamado
3. Para cada campanha publicada (limite 50):
   a. find_eligible_clients_for_campaign() busca elegíveis
   b. Salva em meta 'dps_campaign_pending_offers'
   c. Atualiza 'dps_campaign_last_audit'
4. Redireciona com mensagem de sucesso
```

**Problema:** Não dispara ações (WhatsApp, e-mail, etc.)

---

## 5. FLUXOS DE NEGÓCIO

### 5.1 Fluxo de Acúmulo de Pontos

```
┌─────────────────────────────────────────────────────────────────┐
│ FASE 1: ATENDIMENTO REALIZADO                                   │
└─────────────────────────────────────────────────────────────────┘
1. Pet é atendido (banho, tosa, etc.)
2. Atendente finaliza e marca status do agendamento

┌─────────────────────────────────────────────────────────────────┐
│ FASE 2: PAGAMENTO CONFIRMADO                                    │
└─────────────────────────────────────────────────────────────────┘
3. Status muda para "finalizado_pago"
   - Via interface manual OU
   - Via webhook do Mercado Pago (Payment Add-on)

4. Hook updated_post_meta disparado
   └── Loyalty detecta meta_key = 'appointment_status'
       └── meta_value = 'finalizado_pago'

┌─────────────────────────────────────────────────────────────────┐
│ FASE 3: PONTUAÇÃO                                               │
└─────────────────────────────────────────────────────────────────┘
5. Loyalty verifica flag dps_loyalty_points_awarded
   └── Se já pontuou, ignora (evita duplicação)

6. Obtém valor do atendimento:
   - Prioridade 1: meta 'appointment_total_value'
   - Prioridade 2: soma de dps_transacoes

7. Calcula pontos base:
   └── base_points = floor( valor / brl_per_point )
       Ex: R$ 120,00 / R$ 10,00 = 12 pontos base

8. Aplica multiplicador do nível:
   └── Bronze: 12 × 1.0 = 12 pontos
   └── Prata:  12 × 1.5 = 18 pontos
   └── Ouro:   12 × 2.0 = 24 pontos

9. Credita pontos:
   └── update_post_meta( cliente, 'dps_loyalty_points', saldo + pontos )

10. Registra no histórico:
    └── add_post_meta( cliente, 'dps_loyalty_points_log', entrada )

11. Marca flag para evitar re-pontuação:
    └── update_post_meta( agendamento, 'dps_loyalty_points_awarded', 1 )

12. Dispara hook para outros add-ons:
    └── do_action( 'dps_loyalty_points_awarded_appointment', ... )
```

### 5.2 Fluxo de Indicação (Indique e Ganhe)

```
┌─────────────────────────────────────────────────────────────────┐
│ FASE 1: CLIENTE A COMPARTILHA CÓDIGO                           │
└─────────────────────────────────────────────────────────────────┘
1. Cliente A acessa Portal ou atendente informa código
2. Código único: "ABCD1234" (gerado automaticamente)
3. Link compartilhável: https://site.com/cadastro?ref=ABCD1234
4. Cliente A envia para amigos via WhatsApp/e-mail

┌─────────────────────────────────────────────────────────────────┐
│ FASE 2: CLIENTE B SE CADASTRA                                  │
└─────────────────────────────────────────────────────────────────┘
5. Cliente B acessa link e preenche formulário
6. Registration Add-on detecta parâmetro ?ref=ABCD1234
7. Após criar cliente, dispara:
   └── do_action( 'dps_registration_after_client_created', ... )

8. Loyalty captura e valida:
   - Programa está ativo?
   - Código existe?
   - Não é auto-indicação?
   - E-mail/telefone não já cadastrado?

9. Se válido, cria registro em dps_referrals:
   └── status = 'pending', sem recompensas ainda

┌─────────────────────────────────────────────────────────────────┐
│ FASE 3: CLIENTE B FAZ PRIMEIRA COMPRA                          │
└─────────────────────────────────────────────────────────────────┘
10. Cliente B agenda e paga primeiro atendimento
11. Finance dispara hook:
    └── do_action( 'dps_finance_booking_paid', $appt_id, $client_id, $amount )

12. Loyalty verifica:
    - Existe indicação pendente para este cliente?
    - Valor atinge mínimo configurado?
    - É realmente a primeira compra?
    - Indicador não atingiu limite de indicações?

13. Se todas validações passam, aplica recompensas:
    - Indicador (A): pontos, crédito fixo ou percentual
    - Indicado (B): pontos, crédito fixo ou percentual

14. Atualiza registro em dps_referrals:
    └── status = 'rewarded'
    └── reward_type_referrer, reward_value_referrer
    └── reward_type_referee, reward_value_referee

┌─────────────────────────────────────────────────────────────────┐
│ FASE 4: PROTEÇÕES ANTI-FRAUDE                                  │
└─────────────────────────────────────────────────────────────────┘
- Auto-indicação: referrer_id ≠ referee_id
- Limite por indicador: COUNT(rewarded) < max_per_referrer
- Valor mínimo: amount >= referrals_minimum_amount
- Primeira compra: verifica transações anteriores
- Contato existente: verifica e-mail/telefone duplicado
```

### 5.3 Fluxo de Campanhas

```
┌─────────────────────────────────────────────────────────────────┐
│ FASE 1: CRIAÇÃO DA CAMPANHA                                    │
└─────────────────────────────────────────────────────────────────┘
1. Admin cria nova campanha via CPT
2. Define: nome, descrição, tipo, critérios, período
3. Publica a campanha

┌─────────────────────────────────────────────────────────────────┐
│ FASE 2: IDENTIFICAÇÃO DE ELEGÍVEIS                             │
└─────────────────────────────────────────────────────────────────┘
4. Admin clica "Rodar rotina de elegibilidade"
5. Sistema processa campanhas publicadas (limite 50)
6. Para cada campanha:
   - Busca clientes (limite 500)
   - Verifica critérios de elegibilidade
   - Salva lista de elegíveis em meta

┌─────────────────────────────────────────────────────────────────┐
│ FASE 3: O QUE DEVERIA ACONTECER (NÃO IMPLEMENTADO)             │
└─────────────────────────────────────────────────────────────────┘
7. ❌ Disparo automático de mensagens
8. ❌ Geração de cupom para cliente usar
9. ❌ Rastreamento de conversão
10. ❌ Relatórios de eficácia

┌─────────────────────────────────────────────────────────────────┐
│ FASE 3: O QUE ACONTECE HOJE                                    │
└─────────────────────────────────────────────────────────────────┘
7. Lista de elegíveis fica salva em meta
8. Admin precisa manualmente:
   - Ver quem são os elegíveis
   - Entrar em contato
   - Aplicar desconto na hora do atendimento
9. ⚠️ Sem rastreamento de uso
```

---

## 6. SEGURANÇA E INTEGRIDADE

### 6.1 Checklist de Segurança

| Item | Status | Localização |
|------|--------|-------------|
| Nonces em forms | ✅ | `dps_campaign_details_nonce`, `dps_loyalty_run_audit_nonce` |
| Nonces em ações GET | ✅ | Exportação CSV usa `wp_nonce_url` |
| Capability check | ✅ | `manage_options` em todas as ações admin |
| Sanitização de entrada | ✅ | `sanitize_text_field()`, `absint()`, `sanitize_key()` |
| Escape de saída | ✅ | `esc_html()`, `esc_attr()`, `esc_url()` consistentes |
| Prepared statements | ✅ | `$wpdb->prepare()` em todas as queries |
| Proteção contra acesso direto | ✅ | `defined('ABSPATH')` em todos os arquivos |

### 6.2 Proteções Anti-Fraude no Indique e Ganhe

| Proteção | Implementação | Código |
|----------|---------------|--------|
| Auto-indicação | ✅ Verifica se referrer ≠ referee | `handle_booking_paid()` linha 1368 |
| Limite por indicador | ✅ Verifica COUNT de recompensadas | `has_referrer_reached_limit()` |
| Valor mínimo | ✅ Compara amount com configuração | `handle_booking_paid()` linha 1373 |
| Primeira compra | ✅ Verifica transações anteriores | `client_has_previous_paid_booking()` |
| Contato duplicado | ✅ Verifica e-mail/telefone | `is_existing_client_contact()` |

### 6.3 Integridade de Pontos

| Aspecto | Status | Descrição |
|---------|--------|-----------|
| Duplicação de pontos | ✅ Protegido | Flag `dps_loyalty_points_awarded` por agendamento |
| Saldo negativo | ✅ Protegido | `redeem_points()` verifica saldo suficiente |
| Valores inválidos | ✅ Protegido | `absint()` e `(int)` em todas as operações |
| Auditoria | ⚠️ Parcial | Histórico existe mas sem quem/quando alterou |

### 6.4 Endpoints e Actions Sensíveis

| Ação | Método | Nonce | Capability | Avaliação |
|------|--------|-------|------------|-----------|
| Salvar campanha | POST | ✅ CPT padrão | `manage_options` | ✅ Seguro |
| Rodar auditoria | POST | ✅ `dps_loyalty_run_audit` | `manage_options` | ✅ Seguro |
| Exportar indicações | GET | ✅ `dps_export_referrals` | `manage_options` | ✅ Seguro |
| Consultar cliente | GET | ⚠️ Sem nonce | `manage_options` | ✅ OK (apenas leitura) |

**Avaliação Geral de Segurança: ⭐⭐⭐⭐ (8/10)**

---

## 7. PERFORMANCE E ESCALABILIDADE

### 7.1 Análise de Queries Críticas

#### Query 1: Métricas Globais (`get_global_metrics`)

```sql
SELECT COUNT(DISTINCT post_id) FROM wp_postmeta 
WHERE meta_key = 'dps_loyalty_points' AND meta_value > 0;

SELECT COALESCE(SUM(meta_value), 0) FROM wp_postmeta 
WHERE meta_key = 'dps_loyalty_points';

SELECT COUNT(*) FROM wp_dps_referrals WHERE created_at >= '2024-12-01 00:00:00';
```

**Otimização existente:** ✅ Cache via transient (5 minutos)

**Estimativa de performance:**

| Registros | Tempo Estimado | Gargalo |
|-----------|----------------|---------|
| 1.000 clientes | < 50ms | ✅ OK |
| 10.000 clientes | ~200ms | ⚠️ Aceitável |
| 100.000 clientes | ~2s | 🔴 Lento |

#### Query 2: Busca de Elegíveis para Campanha

**Problema identificado:** Queries N+1

```php
// Linha 937-956: Para cada cliente, faz query individual
foreach ( $clients as $client_id ) {
    $passes_inactive = $this->is_client_inactive_for_days( $client_id, $inactive_days );
    // ^^ Faz query para buscar último atendimento
}
```

**Impacto:** Com 500 clientes, são 500+ queries extras

**Solução proposta:**
```php
// Carregar datas em batch ANTES do loop
private function get_last_appointments_batch( $client_ids ) {
    global $wpdb;
    $ids_placeholder = implode( ',', array_map( 'intval', $client_ids ) );
    
    return $wpdb->get_results( "
        SELECT m1.meta_value AS client_id, MAX(m2.meta_value) AS last_date
        FROM {$wpdb->postmeta} m1
        INNER JOIN {$wpdb->postmeta} m2 ON m1.post_id = m2.post_id 
            AND m2.meta_key = 'appointment_date'
        WHERE m1.meta_key = 'appointment_client_id'
        AND m1.meta_value IN ({$ids_placeholder})
        GROUP BY m1.meta_value
    ", OBJECT_K );
}
```

#### Query 3: Dropdown de Clientes (render_clients_tab)

```php
$clients_query = new WP_Query( [
    'post_type'      => 'dps_cliente',
    'posts_per_page' => 100, // Paginado, bom!
    'paged'          => $paged,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );
```

**Otimização existente:** ✅ Paginação implementada (100 por página)

**Problema residual:** Dropdown HTML com 100 opções ainda é ruim para UX

### 7.2 Crescimento do Histórico de Pontos

**Cenário:** Pet shop com 1.000 clientes ativos, média 2 atendimentos/mês

- 1 mês: 2.000 registros em `post_meta`
- 1 ano: 24.000 registros
- 5 anos: 120.000 registros

**Impacto:** Consultas em `post_meta` ficam lentas

**Soluções propostas:**
1. Tabela dedicada para histórico (melhor para queries)
2. Limpeza periódica (manter últimos 12 meses)
3. Agregação mensal (resumo em vez de detalhes antigos)

### 7.3 Resumo de Performance

| Aspecto | Status | Nota |
|---------|--------|------|
| Cache de métricas | ✅ | 5 min via transient |
| Paginação de clientes | ✅ | 100 por página |
| Paginação de indicações | ✅ | 20 por página |
| Auditoria de campanhas | ⚠️ | Queries N+1 |
| Histórico de pontos | ⚠️ | Pode crescer muito |
| Índices em dps_referrals | ✅ | Adequados |

**Avaliação de Performance: ⭐⭐⭐ (7/10)**

---

## 8. UX E LAYOUT

### 8.1 Organização de Telas

**Menu no Admin:**
```
DPS by PRObst
└── Campanhas & Fidelidade
    ├── Aba: Dashboard
    ├── Aba: Indicações
    ├── Aba: Configurações
    └── Aba: Consulta de Cliente
```

### 8.2 Estrutura da Interface

**Dashboard:**
```
┌─────────────────────────────────────────────────────────────────┐
│ CAMPANHAS & FIDELIDADE                                          │
├─────────────────────────────────────────────────────────────────┤
│ [Dashboard] [Indicações] [Configurações] [Consulta de Cliente]  │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐    │
│ │👥 150   │ │⭐ 12.500│ │🤝 45    │ │✅ 32    │ │💰 R$850 │    │
│ │Clientes │ │ Pontos  │ │Indicaçõe│ │Recomp.  │ │Créditos │    │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘    │
├─────────────────────────────────────────────────────────────────┤
│ ROTINAS DE CAMPANHAS                                            │
│ Execute uma varredura para identificar clientes elegíveis...    │
│ [Rodar rotina de elegibilidade]                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Consulta de Cliente:**
```
┌─────────────────────────────────────────────────────────────────┐
│ RESUMO DE FIDELIDADE                                            │
├─────────────────────────────────────────────────────────────────┤
│ Selecionar cliente: [Dropdown ▼] [Filtrar]                      │
│ Página 1 de 10  [Anterior] [Próxima]                            │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐                 │
│ │🥈 Prata │ │⭐ 750   │ │💰 R$50  │ │🤝 3/5   │                 │
│ │ Nível   │ │ Pontos  │ │Crédito  │ │Indicaçõe│                 │
│ └─────────┘ └─────────┘ └─────────┘ └─────────┘                 │
├─────────────────────────────────────────────────────────────────┤
│ PROGRESSO PARA PRÓXIMO NÍVEL                                    │
│ 🥈 Prata ═══════════════════════════░░░░░░░░░░ 🥇 Ouro          │
│              750 / 1000 pontos (75%)                            │
├─────────────────────────────────────────────────────────────────┤
│ CÓDIGO DE INDICAÇÃO                                             │
│ [ABCD1234] [📋 Copiar]                                          │
│ https://site.com/cadastro?ref=ABCD1234 [🔗 Copiar] [📲 WhatsApp]│
├─────────────────────────────────────────────────────────────────┤
│ HISTÓRICO RECENTE                                               │
│ • Pagamento de atendimento (+50 pts) - 09/12/2024 14:30         │
│ • Recompensa de indicação (+100 pts) - 05/12/2024 10:15         │
│ • ...                                                           │
└─────────────────────────────────────────────────────────────────┘
```

### 8.3 Avaliação de UX

| Aspecto | Status | Observação |
|---------|--------|------------|
| Cards de métricas | ✅ Bom | Visual limpo com ícones |
| Navegação por abas | ✅ Bom | Padrão WordPress |
| Paginação | ✅ Bom | Implementada em indicações e clientes |
| Barra de progresso | ✅ Bom | Visual para próximo nível |
| Compartilhamento WhatsApp | ✅ Bom (v1.2.0) | Botão prático |
| Dropdown de clientes | ⚠️ Regular | Deveria ser autocomplete |
| Histórico limitado | ⚠️ Regular | Apenas 10 itens, sem paginação |
| Feedback de ações | ⚠️ Regular | Algumas ações sem mensagem |

**Avaliação de UX: ⭐⭐⭐ (7/10)**

---

## 9. PROBLEMAS IDENTIFICADOS

### 9.1 Críticos (Devem ser corrigidos imediatamente)

Nenhum problema crítico de segurança identificado.

### 9.2 Altos (Devem ser priorizados)

| ID | Problema | Impacto | Localização |
|----|----------|---------|-------------|
| A1 | Pontos nunca expiram | Acúmulo irreal de "dívida" | Modelagem de dados |
| A2 | Resgate apenas administrativo | Cliente não tem autonomia | Interface |
| A3 | Campanhas não disparam ações | Apenas identificam elegíveis | `handle_campaign_audit()` |
| A4 | Portal sem seção de fidelidade | APIs existem, UI não | Integração |

### 9.3 Médios (Melhorias importantes)

| ID | Problema | Impacto | Localização |
|----|----------|---------|-------------|
| M1 | Queries N+1 na auditoria | Performance ruim com muitos clientes | `find_eligible_clients_for_campaign()` |
| M2 | Dropdown de clientes | UX ruim com 1000+ clientes | `render_clients_tab()` |
| M3 | Histórico sem paginação | Limitado a 10 itens | `dps_loyalty_get_logs()` |
| M4 | Créditos não integrados com Finance | Uso manual | API existe, integração não |
| M5 | Arquivo principal muito grande | Manutenção difícil | ~1.860 linhas |

### 9.4 Baixos (Nice to have)

| ID | Problema | Impacto | Localização |
|----|----------|---------|-------------|
| B1 | Sem notificação de bonificação | Cliente não sabe que ganhou | Communications |
| B2 | Sem relatórios de campanhas | Não mede eficácia | Analytics |
| B3 | Configurações não colapsáveis | Interface poluída | `render_settings_tab()` |
| B4 | Sem gamificação (badges) | Menos engajamento | Feature nova |

---

## 10. ROADMAP DE MELHORIAS EM FASES

### FASE 1 – CRÍTICO / CORREÇÕES / SEGURANÇA

**Objetivo:** Corrigir problemas que podem causar inconsistências ou má experiência.

**Prioridade:** 🔴 ALTA  
**Esforço estimado:** 6-8 horas

| # | Item | Prioridade | Esforço | Benefício |
|---|------|------------|---------|-----------|
| F1.1 | **Otimizar queries de auditoria** | 🔴 ALTA | 3h | Performance com muitos clientes |
| F1.2 | **Autocomplete para seleção de cliente** | 🔴 ALTA | 4h | UX melhor com muitos clientes |
| F1.3 | **Validar exibição de créditos** | 🟡 MÉDIA | 1h | Consistência de valores |

**Detalhamento F1.1 - Otimizar queries de auditoria:**
```php
// ANTES (N+1 queries)
foreach ( $clients as $client_id ) {
    $last_date = $this->get_last_appointment_date_for_client( $client_id );
}

// DEPOIS (batch query)
$last_appointments = $this->get_last_appointments_batch( $clients );
foreach ( $clients as $client_id ) {
    $last_date = $last_appointments[ $client_id ] ?? '';
}
```

**Detalhamento F1.2 - Autocomplete para seleção de cliente:**
```php
// Handler AJAX
add_action( 'wp_ajax_dps_loyalty_search_clients', [ $this, 'ajax_search_clients' ] );

public function ajax_search_clients() {
    check_ajax_referer( 'dps_loyalty_nonce', 'nonce' );
    
    $search = sanitize_text_field( $_GET['q'] );
    $clients = new WP_Query( [
        'post_type'      => 'dps_cliente',
        'posts_per_page' => 20,
        's'              => $search,
    ] );
    
    $results = [];
    foreach ( $clients->posts as $client ) {
        $results[] = [
            'id'     => $client->ID,
            'text'   => $client->post_title,
            'points' => dps_loyalty_get_points( $client->ID ),
        ];
    }
    
    wp_send_json( $results );
}
```

---

### FASE 2 – UX DO DIA A DIA

**Objetivo:** Facilitar o trabalho diário da equipe e melhorar experiência do cliente.

**Prioridade:** 🟡 MÉDIA-ALTA  
**Esforço estimado:** 20-25 horas

| # | Item | Prioridade | Esforço | Benefício |
|---|------|------------|---------|-----------|
| F2.1 | **Seção de fidelidade no Portal do Cliente** | 🔴 ALTA | 10h | Cliente vê pontos/nível/código |
| F2.2 | **Notificação de bonificação** | 🔴 ALTA | 4h | Cliente sabe que ganhou pontos |
| F2.3 | **Histórico com paginação** | 🟡 MÉDIA | 3h | Ver mais de 10 itens |
| F2.4 | **Resgate de pontos pelo cliente** | 🟡 MÉDIA | 8h | Autonomia para o cliente |

**Detalhamento F2.1 - Seção no Portal do Cliente:**
```php
// No Client Portal Add-on, adicionar shortcode ou widget
[dps_loyalty_portal_section]

// Conteúdo:
// - Nível atual com ícone
// - Pontos e progresso
// - Código de indicação com botão compartilhar
// - Últimas movimentações
// - Botão de resgate (se implementado)
```

**Detalhamento F2.2 - Notificação de bonificação:**
```php
// Hook após adicionar pontos
add_action( 'dps_loyalty_points_added', function( $client_id, $points, $context ) {
    if ( class_exists( 'DPS_Communications' ) ) {
        $template = dps_get_notification_template( 'loyalty_points_added' );
        $message = str_replace( '{points}', $points, $template );
        
        DPS_Communications::send_whatsapp( $client_id, $message );
    }
}, 10, 3 );
```

---

### FASE 3 – RELATÓRIOS E ENGAJAMENTO

**Objetivo:** Fornecer visibilidade para o dono do negócio e aumentar engajamento.

**Prioridade:** 🟡 MÉDIA  
**Esforço estimado:** 25-30 horas

| # | Item | Prioridade | Esforço | Benefício |
|---|------|------------|---------|-----------|
| F3.1 | **Dashboard de métricas avançado** | 🔴 ALTA | 8h | Gráficos de evolução |
| F3.2 | **Relatório de eficácia de campanhas** | 🟡 MÉDIA | 6h | ROI de campanhas |
| F3.3 | **Ranking de clientes engajados** | 🟡 MÉDIA | 4h | Identificar VIPs |
| F3.4 | **Expiração de pontos** | 🟡 MÉDIA | 8h | Incentiva uso |
| F3.5 | **Alertas de pontos a expirar** | 🟢 BAIXA | 4h | Comunicação proativa |

**Detalhamento F3.1 - Dashboard com gráficos:**
```php
// Usar Chart.js (já usado pelo Stats Add-on)
$monthly_data = DPS_Loyalty_API::get_monthly_points_stats();

// Gráfico de linha: pontos concedidos x resgatados nos últimos 12 meses
// Gráfico de pizza: distribuição por nível (Bronze/Prata/Ouro)
// Cards: pontos a expirar este mês, clientes inativos, etc.
```

**Detalhamento F3.4 - Expiração de pontos:**
```php
// Adicionar campo de validade nos lotes de pontos
$entry = [
    'action'     => 'add',
    'points'     => 50,
    'context'    => 'appointment_payment',
    'date'       => current_time( 'mysql' ),
    'expires_at' => date( 'Y-m-d', strtotime( '+12 months' ) ), // NOVO
];

// Cron job semanal para verificar e expirar
add_action( 'dps_loyalty_weekly_expiry_check', function() {
    $clients = get_posts( [
        'post_type' => 'dps_cliente',
        'meta_query' => [ /* pontos > 0 */ ],
    ] );
    
    foreach ( $clients as $client ) {
        $logs = get_post_meta( $client->ID, 'dps_loyalty_points_log' );
        // Verificar lotes expirados e debitar
    }
} );
```

---

### FASE 4 – EXTRAS AVANÇADOS (OPCIONAL)

**Objetivo:** Funcionalidades avançadas para diferenciação.

**Prioridade:** 🟢 BAIXA  
**Esforço estimado:** 40-50 horas

| # | Item | Prioridade | Esforço | Benefício |
|---|------|------------|---------|-----------|
| F4.1 | **Disparo automático de campanhas** | 🟡 MÉDIA | 12h | Campanhas ativas de verdade |
| F4.2 | **Gamificação (badges/conquistas)** | 🟢 BAIXA | 15h | Maior engajamento |
| F4.3 | **Níveis configuráveis pelo admin** | 🟢 BAIXA | 6h | Flexibilidade |
| F4.4 | **Integração de créditos com Finance** | 🟡 MÉDIA | 10h | Uso automático de créditos |
| F4.5 | **API REST para integrações** | 🟢 BAIXA | 8h | Apps terceiros |

**Detalhamento F4.1 - Disparo automático de campanhas:**
```php
// Após rodar auditoria, envia mensagens
foreach ( $eligible_clients as $client_id ) {
    if ( class_exists( 'DPS_Communications' ) ) {
        $message = build_campaign_message( $campaign_id, $client_id );
        DPS_Communications::send_whatsapp( $client_id, $message );
        
        // Marca como notificado
        $notified = get_post_meta( $campaign_id, 'dps_campaign_notified_clients', true ) ?: [];
        $notified[] = $client_id;
        update_post_meta( $campaign_id, 'dps_campaign_notified_clients', $notified );
    }
}
```

**Detalhamento F4.2 - Sistema de Badges:**
```php
// Badges predefinidos
$badges = [
    'first_visit'     => [ 'label' => '🎉 Primeiro Atendimento', 'condition' => 'appointments >= 1' ],
    'loyal_customer'  => [ 'label' => '🌟 Fiel da Casa', 'condition' => 'appointments >= 10' ],
    'super_referrer'  => [ 'label' => '🏆 Indicador Master', 'condition' => 'referrals >= 5' ],
    'vip'             => [ 'label' => '💎 VIP', 'condition' => 'tier == ouro' ],
];

// Verificar e conceder badges após cada ação relevante
```

---

## 11. CONCLUSÃO

### 11.1 Resumo da Análise

O **Add-on Campanhas & Fidelidade v1.2.0** é um módulo **sólido e bem estruturado** que cobre as necessidades básicas de um programa de fidelidade para Banho e Tosa.

### 11.2 Principais Conquistas

1. ✅ **API pública centralizada** (`DPS_Loyalty_API`) com 18+ métodos
2. ✅ **Sistema Indique e Ganhe robusto** com proteções anti-fraude
3. ✅ **Multiplicador de nível aplicado** (v1.2.0)
4. ✅ **Segurança adequada** (nonces, sanitização, capabilities)
5. ✅ **Cache de métricas** via transient
6. ✅ **Exportação CSV** de indicações
7. ✅ **Compartilhamento WhatsApp** do código de indicação

### 11.3 Principais Limitações

1. ❌ **Falta integração com Portal do Cliente** (APIs existem, UI não)
2. ❌ **Campanhas não disparam ações** (apenas identificam elegíveis)
3. ❌ **Pontos não expiram** (pode acumular "dívida")
4. ❌ **Resgate apenas administrativo** (cliente sem autonomia)
5. ⚠️ **Queries N+1** na auditoria de campanhas
6. ⚠️ **Créditos não integrados** com Finance

### 11.4 Notas Finais

| Aspecto | Nota | Justificativa |
|---------|------|---------------|
| **Funcionalidade** | 8/10 | Cobre necessidades básicas, falta Portal e disparo de campanhas |
| **Código** | 8/10 | API bem estruturada, arquivo principal grande mas organizado |
| **Segurança** | 8/10 | Boas práticas, proteções anti-fraude |
| **Performance** | 7/10 | Cache OK, mas queries N+1 em auditoria |
| **UX** | 7/10 | Interface funcional, falta autocomplete e integração Portal |
| **Integração** | 6/10 | Boa com Finance/Agenda, fraca com Portal/Communications |

**Nota Geral: ⭐⭐⭐⭐ (7.5/10) - BOM**

### 11.5 Próximos Passos Recomendados

**Imediato (Fase 1):**
1. Otimizar queries de auditoria (eliminar N+1)
2. Implementar autocomplete para seleção de clientes

**Curto prazo (Fase 2):**
3. Criar seção de fidelidade no Portal do Cliente
4. Integrar notificações de bonificação com Communications

**Médio prazo (Fase 3):**
5. Implementar expiração de pontos
6. Dashboard com gráficos de evolução
7. Relatórios de eficácia de campanhas

**Longo prazo (Fase 4):**
8. Disparo automático de campanhas
9. Sistema de gamificação (badges)
10. Integração de créditos com Finance para pagamento

---

**Documento atualizado em:** 09/12/2024  
**Autor:** Agente de Análise de Código - Repositório DPS
