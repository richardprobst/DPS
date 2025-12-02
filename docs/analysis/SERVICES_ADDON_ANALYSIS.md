# Análise Profunda do Services Add-on

**Versão Analisada:** 1.2.0  
**Data da Análise:** 02/12/2024  
**Autor:** Análise automatizada por Copilot

---

## Sumário Executivo

O Services Add-on é um componente essencial do sistema DPS que gerencia o catálogo de serviços oferecidos pelo pet shop, com suporte a preços diferenciados por porte de animal e integração com agendamentos. Esta análise identifica pontos fortes, vulnerabilidades e oportunidades de melhoria.

### Avaliação Geral

| Critério | Nota | Observação |
|----------|------|------------|
| **Funcionalidade** | 8/10 | Robusto, mas falta recursos avançados |
| **Código** | 7/10 | Bem estruturado, precisa refatoração pontual |
| **Segurança** | 6/10 | Vulnerabilidades de CSRF em exclusão/toggle |
| **Performance** | 7/10 | Queries não otimizadas |
| **Layout/UX** | 6/10 | Interface funcional, pode ser modernizada |
| **Documentação** | 7/10 | README básico, falta DocBlocks detalhados |
| **Integração** | 9/10 | API pública bem projetada |

---

## 1. Estrutura de Arquivos

### Estrutura Atual
```
add-ons/desi-pet-shower-services_addon/
├── desi-pet-shower-services.php      # Wrapper com header do plugin
├── README.md                          # Documentação básica
├── uninstall.php                      # Limpeza na desinstalação
└── dps_service/
    ├── desi-pet-shower-services-addon.php  # Classe principal (1175 linhas)
    ├── includes/
    │   └── class-dps-services-api.php      # API pública (302 linhas)
    └── assets/
        ├── css/
        │   └── services-addon.css          # Estilos (148 linhas)
        └── js/
            └── dps-services-addon.js       # JavaScript (156 linhas)
```

### Avaliação da Estrutura: ✅ Boa

O add-on segue o padrão modular recomendado no ANALYSIS.md com separação de:
- Arquivo wrapper (header do plugin)
- Classe principal
- API pública em `includes/`
- Assets em pastas dedicadas

**Pontos de melhoria:**
- Classe principal com 1175 linhas é monolítica
- Falta separação em classes menores (CPT, Admin, Frontend)

---

## 2. Análise de Código

### 2.1 Classe Principal `DPS_Services_Addon`

#### Métodos e Responsabilidades

| Método | Linhas | Responsabilidade | Avaliação |
|--------|--------|------------------|-----------|
| `__construct()` | 34-61 | Registro de hooks | ✅ Correto |
| `register_service_cpt()` | 100-127 | Registro de CPT | ✅ Usa DPS_CPT_Helper |
| `activate()` | 132-198 | Povoamento inicial | ⚠️ Query ineficiente |
| `section_services()` | 227-506 | Renderização completa | ❌ 279 linhas, muito longo |
| `maybe_handle_service_request()` | 511-653 | Handler de formulários | ⚠️ Vulnerabilidades CSRF |
| `appointment_service_fields()` | 661-855 | Campos no formulário | ⚠️ 194 linhas, muito HTML |
| `appointment_finalization_fields()` | 866-940 | Campos de finalização | ✅ Adequado |
| `save_appointment_services_meta()` | 1004-1077 | Salvamento de metas | ✅ Com sanitização |
| `store_booking_totals_snapshot()` | 1103-1142 | Snapshot de preços | ✅ Usa DPS_Money_Helper |

#### Problemas Identificados

**1. Método `section_services()` muito extenso (279 linhas)**

Este método combina:
- Processamento de dados de edição
- Query de serviços
- Renderização de formulário
- Renderização de tabela
- JavaScript inline

**Recomendação:** Dividir em 4 métodos menores:
```php
private function get_edit_service_data( $edit_id ) { ... }
private function render_service_form( $editing, $meta ) { ... }
private function render_services_table( $services ) { ... }
private function get_service_form_scripts() { ... }
```

**2. Vulnerabilidade CSRF em exclusão e toggle (Crítico)** ✅ CORRIGIDO

O código original no método `maybe_handle_service_request()` processava exclusões e alterações de status sem verificação de nonce:

```php
// Código vulnerável (ANTES da correção):
if ( isset( $_GET['dps_service_delete'] ) ) {
    $id = intval( wp_unslash( $_GET['dps_service_delete'] ) );
    if ( $id ) {
        wp_delete_post( $id, true );
    }
    // ...
}
```

**Correção aplicada:**
```php
// Código seguro (APÓS a correção):
if ( isset( $_GET['dps_service_delete'] ) && isset( $_GET['_wpnonce'] ) ) {
    $id = intval( wp_unslash( $_GET['dps_service_delete'] ) );
    if ( $id && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_delete_service_' . $id ) ) {
        $service = get_post( $id );
        if ( $service && 'dps_service' === $service->post_type ) {
            wp_delete_post( $id, true );
            DPS_Message_Helper::add_success( __( 'Serviço excluído com sucesso.', 'dps-services-addon' ) );
        }
    }
}
```

**3. Queries não otimizadas**

No método `section_services()`, a query de serviços pode ser otimizada:

```php
// Query atual:
$services = get_posts( [
    'post_type'      => 'dps_service',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
] );
```

**Recomendação:** Adicionar `'no_found_rows' => true` e usar cache:
```php
$services = get_posts( [
    'post_type'      => 'dps_service',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'no_found_rows'  => true,
    'update_post_term_cache' => false,
] );
// Pré-carregar metas
update_meta_cache( 'post', wp_list_pluck( $services, 'ID' ) );
```

### 2.2 API Pública `DPS_Services_API`

#### Avaliação: ✅ Excelente

A API é bem projetada com métodos estáticos claros:

| Método | Propósito | Avaliação |
|--------|-----------|-----------|
| `get_service()` | Dados completos de serviço | ✅ |
| `calculate_price()` | Preço por porte | ✅ |
| `calculate_appointment_total()` | Total de agendamento | ✅ |
| `get_services_details()` | Detalhes de agendamento | ✅ |
| `normalize_pet_size()` | Normalização de porte | ✅ |

**Pontos fortes:**
- Métodos estáticos para fácil acesso
- DocBlocks completos com `@since`
- Normalização de porte bilíngue (pt_BR e en)
- Fallback para preço base quando variação não existe

**Sugestões de melhoria:**

1. **Adicionar cache para consultas frequentes:**
```php
public static function get_service( $service_id ) {
    static $cache = [];
    
    $service_id = absint( $service_id );
    if ( isset( $cache[ $service_id ] ) ) {
        return $cache[ $service_id ];
    }
    
    // ... lógica existente ...
    
    $cache[ $service_id ] = $data;
    return $data;
}
```

2. **Adicionar método para listar todos os serviços ativos:**
```php
/**
 * Lista todos os serviços ativos.
 *
 * @param array $args Argumentos adicionais para WP_Query.
 * @return array Array de serviços.
 * @since 1.3.0
 */
public static function get_active_services( $args = [] ) {
    $defaults = [
        'post_type'      => 'dps_service',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => 'service_active',
                'value'   => '0',
                'compare' => '!=',
            ],
        ],
    ];
    
    $args = wp_parse_args( $args, $defaults );
    $services = get_posts( $args );
    
    return array_map( function( $service ) {
        return self::get_service( $service->ID );
    }, $services );
}
```

3. **Adicionar método para calcular duração total:**
```php
/**
 * Calcula a duração total estimada de um agendamento.
 *
 * @param array  $service_ids IDs dos serviços.
 * @param string $pet_size    Porte do pet.
 * @return int Duração total em minutos.
 * @since 1.3.0
 */
public static function calculate_duration( $service_ids, $pet_size = '' ) {
    $total_minutes = 0;
    $size = self::normalize_pet_size( $pet_size );
    
    foreach ( $service_ids as $service_id ) {
        $duration_key = 'service_duration';
        if ( $size ) {
            $size_duration = get_post_meta( $service_id, "service_duration_{$size}", true );
            if ( '' !== $size_duration ) {
                $total_minutes += (int) $size_duration;
                continue;
            }
        }
        $total_minutes += (int) get_post_meta( $service_id, $duration_key, true );
    }
    
    return $total_minutes;
}
```

---

## 3. Análise de Segurança

### 3.1 Vulnerabilidades Identificadas

| Severidade | Tipo | Local | Status |
|------------|------|-------|--------|
| **Alta** | CSRF | Exclusão de serviço via GET | ✅ Corrigido |
| **Alta** | CSRF | Toggle de status via GET | ✅ Corrigido |
| **Média** | Falta de capability | Toggle não verifica permissão | ✅ Corrigido (via can_manage()) |
| Baixa | Escape inconsistente | JavaScript inline | ⚠️ Parcialmente |

### 3.2 Boas Práticas Já Implementadas

- ✅ Nonces no formulário de salvamento
- ✅ Nonces em exclusão e toggle (ADICIONADO)
- ✅ Verificação de post_type antes de modificar (ADICIONADO)
- ✅ Sanitização de inputs com `sanitize_text_field()`, `floatval()`, `intval()`
- ✅ Escape de saída com `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ Verificação de capability em `can_manage()`
- ✅ Uso de `wp_unslash()` antes de sanitizar

### 3.3 Correções Aplicadas

**1. Nonces nas URLs de exclusão e toggle (✅ Implementado):**

```php
// Em section_services(), ao gerar URLs:
$del_url = wp_nonce_url(
    add_query_arg( [ 'tab' => 'servicos', 'dps_service_delete' => $service->ID ], $base_url ),
    'dps_delete_service_' . $service->ID
);
$toggle_url = wp_nonce_url(
    add_query_arg( [ 'tab' => 'servicos', 'dps_toggle_service' => $service->ID ], $base_url ),
    'dps_toggle_service_' . $service->ID
);
```

**2. Verificação de nonces e post_type em handlers (✅ Implementado):**

```php
// Exclusão
if ( isset( $_GET['dps_service_delete'] ) ) {
    $id = intval( wp_unslash( $_GET['dps_service_delete'] ) );
    if ( $id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_delete_service_' . $id ) ) {
        $service = get_post( $id );
        if ( $service && 'dps_service' === $service->post_type ) {
            wp_delete_post( $id, true );
            if ( class_exists( 'DPS_Message_Helper' ) ) {
                DPS_Message_Helper::add_success( __( 'Serviço excluído com sucesso.', 'dps-services-addon' ) );
            }
        }
    }
    $redirect = $this->get_redirect_url();
    wp_safe_redirect( $redirect );
    exit;
}
```

---

## 4. Análise de Performance

### 4.1 Problemas Identificados

**1. Múltiplas queries na listagem de serviços**

O loop de renderização faz chamadas individuais de `get_post_meta()` para cada serviço:

```php
foreach ( $services as $service ) {
    $type  = get_post_meta( $service->ID, 'service_type', true );
    $cat   = get_post_meta( $service->ID, 'service_category', true );
    // ... mais get_post_meta ...
}
```

**Solução:** Pré-carregar todas as metas:
```php
$service_ids = wp_list_pluck( $services, 'ID' );
update_meta_cache( 'post', $service_ids );
```

**2. Query repetida em `appointment_service_fields()`**

A mesma query de serviços é feita em `section_services()` e em `appointment_service_fields()`.

**Solução:** Implementar cache estático ou usar API:
```php
$services = DPS_Services_API::get_active_services();
```

**3. JavaScript de cálculo executa em toda página**

O script `dps-services-addon.js` é carregado em todas as páginas onde o shortcode base está presente.

**Solução:** Verificar se formulário de agendamento existe antes de executar:
```javascript
jQuery(document).ready(function ($) {
    // Só executa se existir o campo de serviços
    if ( ! $('.dps-service-checkbox').length ) {
        return;
    }
    // ... resto do código ...
});
```

### 4.2 Métricas Estimadas

| Operação | Impacto | Solução |
|----------|---------|---------|
| Listagem de 50 serviços | ~100 queries | `update_meta_cache()` |
| Formulário de agendamento | ~20 queries | Cache estático em API |
| Cálculo JavaScript | Negligível | Manter atual |

---

## 5. Análise de Layout e UX

### 5.1 Interface de Cadastro de Serviços

#### Estado Atual

O formulário de cadastro segue estrutura básica mas carece de:
- Agrupamento visual em fieldsets
- Indicadores de campos obrigatórios
- Feedback visual após ações
- Validação client-side

#### Melhorias Recomendadas

**1. Organização em Fieldsets**

```php
// Proposta de reorganização:
<fieldset class="dps-fieldset">
    <legend><?php esc_html_e( 'Informações Básicas', 'dps-services-addon' ); ?></legend>
    <!-- Nome, Tipo, Categoria, Status Ativo -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend><?php esc_html_e( 'Precificação por Porte', 'dps-services-addon' ); ?></legend>
    <!-- Preço Pequeno, Médio, Grande -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend><?php esc_html_e( 'Duração Estimada', 'dps-services-addon' ); ?></legend>
    <!-- Duração Pequeno, Médio, Grande -->
</fieldset>

<fieldset class="dps-fieldset">
    <legend><?php esc_html_e( 'Consumo de Estoque', 'dps-services-addon' ); ?></legend>
    <!-- Tabela de insumos -->
</fieldset>
```

**2. Grid Responsivo para Campos de Porte**

```css
.dps-size-fields {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

@media (max-width: 768px) {
    .dps-size-fields {
        grid-template-columns: 1fr;
    }
}
```

**3. Indicadores de Campo Obrigatório**

```php
echo '<label>' . esc_html__( 'Nome do serviço', 'dps-services-addon' );
echo ' <span class="dps-required" title="' . esc_attr__( 'Campo obrigatório', 'dps-services-addon' ) . '">*</span>';
echo '<input type="text" name="service_name" required>';
echo '</label>';
```

**4. Mensagens de Feedback**

```php
// No início de section_services():
if ( class_exists( 'DPS_Message_Helper' ) ) {
    echo DPS_Message_Helper::display_messages();
}
```

### 5.2 Tabela de Serviços

#### Estado Atual

- Tabela funcional com ações básicas
- Falta responsividade em mobile
- Ações de excluir sem confirmação adequada (apenas JavaScript `confirm()`)

#### Melhorias Recomendadas

**1. Wrapper Responsivo**

```php
echo '<div class="dps-table-wrapper">';
echo '<table class="dps-table dps-services-table">';
// ... conteúdo da tabela ...
echo '</table>';
echo '</div>';
```

**2. Colunas Colapsáveis em Mobile**

```css
@media (max-width: 768px) {
    .dps-services-table th:nth-child(3),
    .dps-services-table td:nth-child(3),
    .dps-services-table th:nth-child(4),
    .dps-services-table td:nth-child(4) {
        display: none;
    }
}
```

**3. Badges de Status**

```php
$status_class = ( '0' === $active ) ? 'dps-badge-inactive' : 'dps-badge-active';
$status_label = ( '0' === $active ) 
    ? __( 'Inativo', 'dps-services-addon' ) 
    : __( 'Ativo', 'dps-services-addon' );
echo '<td><span class="dps-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
```

```css
.dps-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.dps-badge-active {
    background: #d1fae5;
    color: #059669;
}

.dps-badge-inactive {
    background: #f3f4f6;
    color: #6b7280;
}
```

### 5.3 Campos no Formulário de Agendamento

#### Estado Atual

- Checkboxes funcionais
- Campos de preço editáveis inline
- Cálculo automático de total

#### Problemas Identificados

1. **Lista muito longa sem agrupamento visual**
2. **Preços difíceis de visualizar em mobile**
3. **Falta indicação visual do total sendo atualizado**

#### Melhorias Recomendadas

**1. Acordeão para Categorias de Extras**

```php
echo '<details class="dps-service-category">';
echo '<summary>' . esc_html( $label ) . ' <span class="dps-count">(' . count( $items ) . ')</span></summary>';
foreach ( $items as $srv ) {
    // ... checkboxes ...
}
echo '</details>';
```

**2. Destaque Visual no Total**

```css
#dps-appointment-total {
    font-size: 18px;
    font-weight: 700;
    color: #0ea5e9;
    background: #eff6ff;
    border: 2px solid #0ea5e9;
    transition: all 0.3s ease;
}

#dps-appointment-total.dps-updating {
    animation: pulse 0.5s ease;
}

@keyframes pulse {
    50% { opacity: 0.6; }
}
```

---

## 6. Funcionalidades Ausentes

### 6.1 Funcionalidades Recomendadas de Alta Prioridade

| Funcionalidade | Justificativa | Esforço |
|----------------|---------------|---------|
| **Pacotes promocionais** | Combos com desconto | Médio |
| **Histórico de preços** | Rastreabilidade | Baixo |
| **Duplicar serviço** | Agilidade no cadastro | Baixo |
| **Ordenação customizada** | UX melhorada | Baixo |
| **Filtros na listagem** | Encontrar serviços | Médio |

### 6.2 Funcionalidades Recomendadas de Média Prioridade

| Funcionalidade | Justificativa | Esforço |
|----------------|---------------|---------|
| **Imagem do serviço** | Identificação visual | Médio |
| **Descrição longa** | Detalhamento para cliente | Baixo |
| **Tempo de preparo** | Planejamento de agenda | Baixo |
| **Serviços sazonais** | Promoções temporárias | Alto |
| **Integração com catálogo público** | Visibilidade para clientes | Alto |

### 6.3 Propostas Detalhadas

#### 6.3.1 Pacotes Promocionais

**Descrição:** Permitir criar pacotes que combinam múltiplos serviços com desconto.

**Estrutura de dados proposta:**
```php
// Metas adicionais para tipo 'package':
'service_package_items'     => array    // IDs dos serviços incluídos (já existe)
'service_package_discount'  => float    // Percentual de desconto
'service_package_fixed_price' => float  // OU preço fixo do pacote
```

**Método de cálculo na API:**
```php
public static function calculate_package_price( $package_id, $pet_size = '' ) {
    $package = self::get_service( $package_id );
    if ( ! $package || 'package' !== $package['type'] ) {
        return null;
    }
    
    $items = get_post_meta( $package_id, 'service_package_items', true );
    $discount = (float) get_post_meta( $package_id, 'service_package_discount', true );
    $fixed = get_post_meta( $package_id, 'service_package_fixed_price', true );
    
    // Se tem preço fixo, usa ele
    if ( '' !== $fixed && $fixed > 0 ) {
        return (float) $fixed;
    }
    
    // Senão, calcula soma dos itens com desconto
    $sum = 0;
    foreach ( $items as $item_id ) {
        $sum += self::calculate_price( $item_id, $pet_size );
    }
    
    if ( $discount > 0 ) {
        $sum = $sum * ( 1 - ( $discount / 100 ) );
    }
    
    return $sum;
}
```

#### 6.3.2 Histórico de Preços

**Descrição:** Manter registro de alterações de preço para auditoria e relatórios.

**Estrutura de dados proposta:**
```php
// Nova meta ao salvar serviço:
'service_price_history' => [
    [
        'date'         => '2024-12-02 14:30:00',
        'user_id'      => 1,
        'old_price'    => 50.00,
        'new_price'    => 55.00,
        'price_type'   => 'small', // ou 'medium', 'large', 'base'
    ],
    // ...
]
```

**Implementação no salvamento:**
```php
private function log_price_change( $service_id, $price_type, $old_price, $new_price ) {
    if ( abs( $old_price - $new_price ) < 0.01 ) {
        return; // Sem mudança
    }
    
    $history = get_post_meta( $service_id, 'service_price_history', true ) ?: [];
    $history[] = [
        'date'       => current_time( 'mysql' ),
        'user_id'    => get_current_user_id(),
        'old_price'  => $old_price,
        'new_price'  => $new_price,
        'price_type' => $price_type,
    ];
    
    // Mantém apenas últimos 50 registros
    if ( count( $history ) > 50 ) {
        $history = array_slice( $history, -50 );
    }
    
    update_post_meta( $service_id, 'service_price_history', $history );
}
```

#### 6.3.3 Duplicar Serviço

**Descrição:** Botão para criar cópia de serviço existente com novo nome.

**Implementação:**
```php
// Handler:
if ( isset( $_GET['dps_duplicate_service'] ) && isset( $_GET['_wpnonce'] ) ) {
    $id = intval( wp_unslash( $_GET['dps_duplicate_service'] ) );
    if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_duplicate_service_' . $id ) 
         && $this->can_manage() ) {
        $new_id = $this->duplicate_service( $id );
        if ( $new_id ) {
            DPS_Message_Helper::add_success( 
                sprintf( __( 'Serviço duplicado com sucesso. <a href="%s">Editar cópia</a>', 'dps-services-addon' ),
                esc_url( add_query_arg( [ 'tab' => 'servicos', 'dps_edit' => 'service', 'id' => $new_id ], get_permalink() ) ) )
            );
        }
    }
}

private function duplicate_service( $service_id ) {
    $original = get_post( $service_id );
    if ( ! $original || 'dps_service' !== $original->post_type ) {
        return false;
    }
    
    $new_id = wp_insert_post( [
        'post_type'   => 'dps_service',
        'post_title'  => sprintf( __( '%s (Cópia)', 'dps-services-addon' ), $original->post_title ),
        'post_status' => 'publish',
    ] );
    
    if ( ! $new_id ) {
        return false;
    }
    
    // Copia todas as metas
    $metas = get_post_meta( $service_id );
    foreach ( $metas as $key => $values ) {
        if ( strpos( $key, '_' ) === 0 ) {
            continue; // Pula metas internas do WP
        }
        foreach ( $values as $value ) {
            add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
        }
    }
    
    // Marca como inativo por padrão
    update_post_meta( $new_id, 'service_active', '0' );
    
    return $new_id;
}
```

---

## 7. Integração com Outros Add-ons

### 7.1 Integrações Existentes

| Add-on | Tipo | Método | Avaliação |
|--------|------|--------|-----------|
| **Agenda** | Consumidor | `DPS_Services_API::get_services_details()` | ✅ |
| **Estoque** | Fornecedor | Campo de consumo no formulário | ✅ |
| **Finance** | Consumidor | Via metadados de agendamento | ⚠️ |
| **Portal** | Consumidor | Exibe serviços históricos | ⚠️ |

### 7.2 Oportunidades de Melhoria

**1. Integração mais direta com Finance**

```php
// Adicionar hook após calcular total:
do_action( 'dps_services_total_calculated', $appointment_id, $total, $services_details );
```

**2. API para Portal do Cliente**

```php
/**
 * Obtém serviços disponíveis para exibição pública.
 *
 * @param bool $include_prices Incluir preços na resposta.
 * @return array
 * @since 1.3.0
 */
public static function get_public_services( $include_prices = true ) {
    $services = self::get_active_services();
    
    // Filtra dados sensíveis se necessário
    return array_map( function( $service ) use ( $include_prices ) {
        $public = [
            'id'          => $service['id'],
            'title'       => $service['title'],
            'description' => $service['description'],
            'type'        => $service['type'],
            'category'    => $service['category'],
        ];
        
        if ( $include_prices ) {
            $public['price'] = $service['price'];
            $public['price_small'] = $service['price_small'];
            $public['price_medium'] = $service['price_medium'];
            $public['price_large'] = $service['price_large'];
        }
        
        return $public;
    }, $services );
}
```

**3. Shortcode para Exibição de Catálogo**

```php
/**
 * Shortcode [dps_services_catalog] para exibir catálogo público de serviços.
 *
 * Atributos:
 * - show_prices: 'yes'|'no' (padrão: 'yes')
 * - category: slug da categoria para filtrar
 * - type: 'padrao'|'extra'|'package' para filtrar
 *
 * @param array $atts Atributos do shortcode.
 * @return string HTML do catálogo.
 */
public function render_catalog_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'show_prices' => 'yes',
        'category'    => '',
        'type'        => '',
    ], $atts, 'dps_services_catalog' );
    
    $services = DPS_Services_API::get_public_services( 'yes' === $atts['show_prices'] );
    
    // Filtra por categoria/tipo se especificado
    if ( $atts['category'] ) {
        $services = array_filter( $services, function( $s ) use ( $atts ) {
            return $s['category'] === $atts['category'];
        } );
    }
    
    if ( $atts['type'] ) {
        $services = array_filter( $services, function( $s ) use ( $atts ) {
            return $s['type'] === $atts['type'];
        } );
    }
    
    ob_start();
    include plugin_dir_path( __FILE__ ) . 'templates/catalog.php';
    return ob_get_clean();
}
```

---

## 8. Recomendações Prioritárias

### 8.1 Correções Críticas ✅ CONCLUÍDAS

1. ✅ **[SEGURANÇA]** Adicionar verificação de nonce em exclusão de serviço
2. ✅ **[SEGURANÇA]** Adicionar verificação de nonce em toggle de status
3. ✅ **[SEGURANÇA]** Adicionar verificação de post_type antes de modificar
4. ✅ **[UX]** Adicionar mensagens de feedback via `DPS_Message_Helper`
5. ✅ **[UX]** Adicionar badges de status na tabela

### 8.2 Melhorias de Alta Prioridade (Próxima Release)

1. **[PERFORMANCE]** Implementar cache de metas com `update_meta_cache()`
2. **[UX]** Organizar formulário em fieldsets temáticos
3. **[CÓDIGO]** Refatorar `section_services()` em métodos menores

### 8.3 Melhorias de Média Prioridade (Futuras Releases)

1. **[FUNCIONALIDADE]** Implementar duplicação de serviço
2. **[FUNCIONALIDADE]** Adicionar histórico de preços
3. **[FUNCIONALIDADE]** Melhorar cálculo de pacotes promocionais
4. **[UX]** Implementar acordeão para categorias de extras

### 8.4 Melhorias de Baixa Prioridade (Roadmap)

1. **[FUNCIONALIDADE]** Shortcode de catálogo público
2. **[FUNCIONALIDADE]** API para Portal do Cliente
3. **[FUNCIONALIDADE]** Ordenação customizada (drag-and-drop)
4. **[FUNCIONALIDADE]** Imagem do serviço
5. **[FUNCIONALIDADE]** Serviços sazonais

---

## 9. Estimativas de Esforço

| Tarefa | Esforço | Impacto |
|--------|---------|---------|
| ~~Correções de segurança (CSRF)~~ | ~~2h~~ | ~~Alto~~ ✅ Concluído |
| ~~Mensagens de feedback~~ | ~~1h~~ | ~~Alto~~ ✅ Concluído |
| Otimização de queries | 1h | Médio |
| Refatoração de `section_services()` | 4h | Médio |
| Fieldsets no formulário | 2h | Alto |
| Duplicação de serviço | 2h | Médio |
| Histórico de preços | 3h | Baixo |
| Shortcode de catálogo | 4h | Médio |
| **Total restante estimado** | **~16h** | - |

---

## 10. Conclusão

O Services Add-on é um componente funcional e bem estruturado do sistema DPS, com uma API pública sólida que facilita integrações. 

### Correções Aplicadas Nesta Análise

1. ✅ **Segurança**: Vulnerabilidades CSRF corrigidas com nonces em exclusão e toggle
2. ✅ **UX**: Mensagens de feedback adicionadas via `DPS_Message_Helper`
3. ✅ **UX**: Badges de status visuais adicionados na tabela de serviços
4. ✅ **CSS**: Estilos responsivos e semânticos melhorados

### Áreas de Melhoria Restantes

1. **Performance**: Queries podem ser otimizadas com caching simples
2. **UX**: Formulário pode ser organizado em fieldsets temáticos
3. **Código**: Classe principal pode ser refatorada para melhor manutenibilidade

Com as correções já aplicadas e as melhorias propostas, o add-on está alinhado com as melhores práticas do repositório DPS e pronto para expansões futuras como catálogo público e pacotes promocionais avançados.

---

**Próximos passos recomendados:**
1. ~~Criar issue para correções de segurança (P0)~~ ✅ Corrigido
2. Planejar refatoração de UX para próximo sprint (P1)
3. Adicionar funcionalidades de duplicação e histórico em backlog (P2)
