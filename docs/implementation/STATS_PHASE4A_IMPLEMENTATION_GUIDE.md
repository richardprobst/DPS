# Stats Add-on — Phase 4A Implementation Guide

**Objetivo:** Implementar F4.1 (Metas e Objetivos) + F4.4 (Dashboard Customizável)  
**Versão alvo:** 1.6.0  
**Esforço estimado:** 12-16 horas  
**Complexidade:** High (customization UI + drag-drop logic)

---

## 📋 Visão Geral

Esta fase adiciona:
1. **F4.1 — Metas e Objetivos:** Definir targets mensais para KPIs críticos (atendimentos, receita, ticket médio)
2. **F4.4 — Dashboard Customizável:** Permitir que cada admin personalize quais KPIs vê, ordem e tamanho dos cards

**Regras:**
- ✅ Uma PR única para toda a Fase 4A
- ❌ NÃO implementar alertas (F4.2), relatórios agendados (F4.3) ou REST API (F4.5)
- ✅ Configuração reversível (botão "Restaurar padrão")
- ✅ Zero breaking changes

---

## 🎯 F4.1 — Metas e Objetivos

### 1.1 — Settings (Options)

Criar 4 novas options para armazenar metas:

```php
// Em desi-pet-shower-stats-addon.php ou em método init()

/**
 * Registra settings de metas (Goals)
 */
function dps_stats_register_goal_settings() {
    register_setting( 'dps_stats_goals', 'dps_stats_goals_enabled', [
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ] );

    register_setting( 'dps_stats_goals', 'dps_stats_goal_appointments_month', [
        'type'              => 'integer',
        'default'           => 100,
        'sanitize_callback' => 'absint',
    ] );

    register_setting( 'dps_stats_goals', 'dps_stats_goal_revenue_month', [
        'type'              => 'number',
        'default'           => 10000.00,
        'sanitize_callback' => function( $value ) {
            return floatval( str_replace( ',', '.', sanitize_text_field( $value ) ) );
        },
    ] );

    register_setting( 'dps_stats_goals', 'dps_stats_goal_ticket_month', [
        'type'              => 'number',
        'default'           => 0, // 0 = não configurado
        'sanitize_callback' => function( $value ) {
            return floatval( str_replace( ',', '.', sanitize_text_field( $value ) ) );
        },
    ] );
}
add_action( 'admin_init', 'dps_stats_register_goal_settings' );
```

---

### 1.2 — UI de Configuração de Metas

Adicionar seção "Configurar Metas" no dashboard ou criar página separada em "DPS > Stats > Metas":

```php
/**
 * Renderiza formulário de configuração de metas
 */
function dps_stats_render_goals_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Você não tem permissão para acessar esta página.', 'desi-pet-shower' ) );
    }

    // Salvar se submetido
    if ( isset( $_POST['dps_stats_save_goals'] ) ) {
        check_admin_referer( 'dps_stats_save_goals_nonce' );

        update_option( 'dps_stats_goals_enabled', isset( $_POST['goals_enabled'] ) );
        update_option( 'dps_stats_goal_appointments_month', absint( $_POST['goal_appointments'] ?? 100 ) );
        update_option( 'dps_stats_goal_revenue_month', floatval( str_replace( ',', '.', sanitize_text_field( $_POST['goal_revenue'] ?? '0' ) ) ) );
        update_option( 'dps_stats_goal_ticket_month', floatval( str_replace( ',', '.', sanitize_text_field( $_POST['goal_ticket'] ?? '0' ) ) ) );

        echo '<div class="notice notice-success"><p>Metas atualizadas com sucesso!</p></div>';
    }

    $enabled      = get_option( 'dps_stats_goals_enabled', false );
    $goal_appts   = get_option( 'dps_stats_goal_appointments_month', 100 );
    $goal_revenue = get_option( 'dps_stats_goal_revenue_month', 10000.00 );
    $goal_ticket  = get_option( 'dps_stats_goal_ticket_month', 0 );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Configurar Metas Mensais', 'desi-pet-shower' ); ?></h1>
        <p>Defina metas para KPIs principais. O dashboard mostrará o progresso em relação às metas do mês atual.</p>

        <form method="post">
            <?php wp_nonce_field( 'dps_stats_save_goals_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Ativar Metas</th>
                    <td>
                        <label>
                            <input type="checkbox" name="goals_enabled" value="1" <?php checked( $enabled ); ?>>
                            Exibir cards de metas no dashboard
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Meta de Atendimentos</th>
                    <td>
                        <input type="number" name="goal_appointments" value="<?php echo esc_attr( $goal_appts ); ?>" min="0" step="1" class="regular-text">
                        <p class="description">Quantidade de atendimentos esperados por mês.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Meta de Receita (R$)</th>
                    <td>
                        <input type="text" name="goal_revenue" value="<?php echo esc_attr( number_format( $goal_revenue, 2, ',', '' ) ); ?>" class="regular-text">
                        <p class="description">Receita esperada por mês (ex: 10000,00).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Meta de Ticket Médio (R$)</th>
                    <td>
                        <input type="text" name="goal_ticket" value="<?php echo esc_attr( $goal_ticket > 0 ? number_format( $goal_ticket, 2, ',', '' ) : '' ); ?>" class="regular-text">
                        <p class="description">Opcional. Ticket médio esperado por mês (ex: 80,00). Deixe vazio para não exibir.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="dps_stats_save_goals" class="button button-primary">Salvar Metas</button>
            </p>
        </form>
    </div>
    <?php
}
```

---

### 1.3 — Cards de Meta no Dashboard

Adicionar método para renderizar cards de progresso de meta:

```php
/**
 * Renderiza card de meta com progresso
 *
 * @param string $icon  Emoji ou ícone
 * @param int    $current Valor atual
 * @param int    $goal    Meta
 * @param string $label   Label do KPI
 * @param string $unit    Unidade (ex: '', 'R$')
 */
function dps_stats_render_goal_card( $icon, $current, $goal, $label, $unit = '' ) {
    if ( $goal <= 0 ) {
        return; // Não renderiza se meta não configurada
    }

    $percentage = $goal > 0 ? round( ( $current / $goal ) * 100, 1 ) : 0;
    $percentage = min( $percentage, 100 ); // Cap em 100%

    // Cor baseada em progresso
    if ( $percentage >= 100 ) {
        $color = 'success'; // Verde
    } elseif ( $percentage >= 70 ) {
        $color = 'primary'; // Azul
    } elseif ( $percentage >= 40 ) {
        $color = 'warning'; // Amarelo
    } else {
        $color = 'danger'; // Vermelho
    }

    $current_formatted = $unit === 'R$' ? 'R$ ' . number_format( $current, 2, ',', '.' ) : number_format( $current, 0, ',', '.' );
    $goal_formatted    = $unit === 'R$' ? 'R$ ' . number_format( $goal, 2, ',', '.' ) : number_format( $goal, 0, ',', '.' );

    ?>
    <div class="dps-stats-card dps-stats-card--<?php echo esc_attr( $color ); ?> dps-stats-card--goal">
        <span class="dps-stats-card__icon"><?php echo esc_html( $icon ); ?></span>
        <span class="dps-stats-card__label"><?php echo esc_html( $label ); ?></span>
        <span class="dps-stats-card__value"><?php echo esc_html( $current_formatted ); ?> / <?php echo esc_html( $goal_formatted ); ?></span>
        <div class="dps-stats-card__progress">
            <div class="dps-stats-card__progress-bar dps-stats-card__progress-bar--<?php echo esc_attr( $color ); ?>" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
        </div>
        <span class="dps-stats-card__progress-text"><?php echo esc_html( $percentage ); ?>% atingido</span>
    </div>
    <?php
}
```

**Adicionar no dashboard (dentro de `section_stats()`):**

```php
// Após os cards principais, antes de "Indicadores Avançados"
if ( get_option( 'dps_stats_goals_enabled', false ) ) {
    echo '<h4>Metas do Mês Atual</h4>';
    echo '<div class="dps-stats-cards">';

    // Meta de Atendimentos
    $goal_appts = get_option( 'dps_stats_goal_appointments_month', 0 );
    if ( $goal_appts > 0 ) {
        $current_month_start = date( 'Y-m-01' );
        $current_month_end   = date( 'Y-m-t' );
        $current_appts       = DPS_Stats_API::get_appointments_count( $current_month_start, $current_month_end );
        dps_stats_render_goal_card( '🎯', $current_appts, $goal_appts, 'Meta de Atendimentos', '' );
    }

    // Meta de Receita
    $goal_revenue = get_option( 'dps_stats_goal_revenue_month', 0 );
    if ( $goal_revenue > 0 ) {
        $finance_exists = dps_stats_table_exists( 'dps_transacoes' );
        if ( $finance_exists ) {
            $current_month_start = date( 'Y-m-01' );
            $current_month_end   = date( 'Y-m-t' );
            $financial_data      = DPS_Stats_API::get_financial_totals( $current_month_start, $current_month_end );
            $current_revenue     = $financial_data['revenue'] ?? 0;
            dps_stats_render_goal_card( '💰', $current_revenue, $goal_revenue, 'Meta de Receita', 'R$' );
        } else {
            // Finance não ativo: exibir aviso
            echo '<div class="dps-stats-card dps-stats-card--warning">';
            echo '<span class="dps-stats-card__icon">⚠️</span>';
            echo '<span class="dps-stats-card__label">Meta de Receita</span>';
            echo '<span class="dps-stats-card__value">Requer Finance Add-on</span>';
            echo '</div>';
        }
    }

    // Meta de Ticket Médio (opcional)
    $goal_ticket = get_option( 'dps_stats_goal_ticket_month', 0 );
    if ( $goal_ticket > 0 ) {
        $finance_exists = dps_stats_table_exists( 'dps_transacoes' );
        if ( $finance_exists ) {
            $current_month_start = date( 'Y-m-01' );
            $current_month_end   = date( 'Y-m-t' );
            $financial_data      = DPS_Stats_API::get_financial_totals( $current_month_start, $current_month_end );
            $current_ticket      = $financial_data['ticket_medio'] ?? 0;
            dps_stats_render_goal_card( '💳', $current_ticket, $goal_ticket, 'Meta de Ticket Médio', 'R$' );
        }
    }

    echo '</div>';
}
```

---

### 1.4 — CSS para Progress Bar

Adicionar em `assets/css/stats-addon.css`:

```css
/* Goal Cards */
.dps-stats-card--goal {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.dps-stats-card__progress {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
}

.dps-stats-card__progress-bar {
    height: 100%;
    transition: width 0.3s ease;
}

.dps-stats-card__progress-bar--success {
    background-color: #10b981;
}

.dps-stats-card__progress-bar--primary {
    background-color: #0ea5e9;
}

.dps-stats-card__progress-bar--warning {
    background-color: #f59e0b;
}

.dps-stats-card__progress-bar--danger {
    background-color: #ef4444;
}

.dps-stats-card__progress-text {
    font-size: 12px;
    color: #6b7280;
    text-align: center;
}
```

---

## 🎨 F4.4 — Dashboard Customizável

### 2.1 — Layout Config (User Meta + Option)

Estrutura de dados para layout customizável:

```json
{
  "enabled_kpis": ["appointments", "revenue", "ticket_medio", "return_rate", "no_show"],
  "order": ["appointments", "revenue", "return_rate", "no_show", "ticket_medio"],
  "sizes": {
    "appointments": "large",
    "revenue": "large",
    "ticket_medio": "medium",
    "return_rate": "small",
    "no_show": "small"
  }
}
```

**Métodos helper:**

```php
/**
 * Obtém layout do dashboard (user meta com fallback para option)
 *
 * @return array Layout config
 */
function dps_stats_get_dashboard_layout() {
    $user_id = get_current_user_id();

    // Tenta user_meta primeiro
    $user_layout = get_user_meta( $user_id, 'dps_stats_dashboard_layout', true );
    if ( ! empty( $user_layout ) && is_array( $user_layout ) ) {
        return $user_layout;
    }

    // Fallback para option global
    $global_layout = get_option( 'dps_stats_dashboard_layout_default', [] );
    if ( ! empty( $global_layout ) && is_array( $global_layout ) ) {
        return $global_layout;
    }

    // Fallback para layout padrão
    return dps_stats_get_default_layout();
}

/**
 * Layout padrão do dashboard
 *
 * @return array
 */
function dps_stats_get_default_layout() {
    return [
        'enabled_kpis' => [
            'appointments',
            'revenue',
            'ticket_medio',
            'new_clients',
            'cancellations',
            'return_rate',
            'no_show',
            'overdue_revenue',
            'conversion_rate',
            'recurring_clients',
        ],
        'order'        => [
            'appointments',
            'revenue',
            'ticket_medio',
            'new_clients',
            'cancellations',
            'return_rate',
            'no_show',
            'overdue_revenue',
            'conversion_rate',
            'recurring_clients',
        ],
        'sizes'        => [
            'appointments'       => 'large',
            'revenue'            => 'large',
            'ticket_medio'       => 'medium',
            'new_clients'        => 'medium',
            'cancellations'      => 'medium',
            'return_rate'        => 'small',
            'no_show'            => 'small',
            'overdue_revenue'    => 'small',
            'conversion_rate'    => 'small',
            'recurring_clients'  => 'small',
        ],
    ];
}

/**
 * Salva layout customizado (user meta)
 *
 * @param array $layout Layout config
 */
function dps_stats_save_dashboard_layout( $layout ) {
    $user_id = get_current_user_id();

    // Validar estrutura
    if ( ! isset( $layout['enabled_kpis'] ) || ! is_array( $layout['enabled_kpis'] ) ) {
        return false;
    }

    update_user_meta( $user_id, 'dps_stats_dashboard_layout', $layout );
    return true;
}

/**
 * Reseta layout para padrão
 */
function dps_stats_reset_dashboard_layout() {
    $user_id = get_current_user_id();
    delete_user_meta( $user_id, 'dps_stats_dashboard_layout' );
}
```

---

### 2.2 — UI de Personalização (Modal)

Adicionar botão "Personalizar Dashboard" no topo da página de Stats:

```php
// No início de section_stats(), antes dos cards
?>
<div class="dps-stats-header">
    <h2>Dashboard de Estatísticas</h2>
    <button type="button" class="button button-secondary" id="dps-stats-customize-btn">
        <span class="dashicons dashicons-admin-generic"></span> Personalizar Dashboard
    </button>
</div>

<!-- Modal de Personalização -->
<div id="dps-stats-customize-modal" class="dps-stats-modal" style="display: none;">
    <div class="dps-stats-modal__overlay"></div>
    <div class="dps-stats-modal__content">
        <div class="dps-stats-modal__header">
            <h3>Personalizar Dashboard</h3>
            <button type="button" class="dps-stats-modal__close">&times;</button>
        </div>
        <div class="dps-stats-modal__body">
            <p>Selecione quais KPIs deseja exibir e arraste para reordenar:</p>

            <form id="dps-stats-customize-form">
                <?php wp_nonce_field( 'dps_stats_save_layout', 'dps_stats_layout_nonce' ); ?>

                <div class="dps-stats-kpi-list" id="dps-stats-kpi-list">
                    <?php
                    $all_kpis = [
                        'appointments'       => 'Atendimentos',
                        'revenue'            => 'Receita',
                        'ticket_medio'       => 'Ticket Médio',
                        'new_clients'        => 'Novos Clientes',
                        'cancellations'      => 'Cancelamentos',
                        'return_rate'        => 'Taxa de Retorno',
                        'no_show'            => 'No-Show',
                        'overdue_revenue'    => 'Inadimplência',
                        'conversion_rate'    => 'Taxa de Conversão',
                        'recurring_clients'  => 'Clientes Recorrentes',
                    ];

                    $layout      = dps_stats_get_dashboard_layout();
                    $enabled_ids = $layout['enabled_kpis'] ?? array_keys( $all_kpis );

                    foreach ( $all_kpis as $kpi_id => $kpi_label ) {
                        $checked = in_array( $kpi_id, $enabled_ids, true ) ? 'checked' : '';
                        $size    = $layout['sizes'][ $kpi_id ] ?? 'medium';
                        ?>
                        <div class="dps-stats-kpi-item" data-kpi-id="<?php echo esc_attr( $kpi_id ); ?>">
                            <span class="dashicons dashicons-menu dps-stats-kpi-item__handle"></span>
                            <label>
                                <input type="checkbox" name="enabled_kpis[]" value="<?php echo esc_attr( $kpi_id ); ?>" <?php echo $checked; ?>>
                                <?php echo esc_html( $kpi_label ); ?>
                            </label>
                            <select name="size_<?php echo esc_attr( $kpi_id ); ?>" class="dps-stats-kpi-size">
                                <option value="small" <?php selected( $size, 'small' ); ?>>Pequeno</option>
                                <option value="medium" <?php selected( $size, 'medium' ); ?>>Médio</option>
                                <option value="large" <?php selected( $size, 'large' ); ?>>Grande</option>
                            </select>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="dps-stats-modal__actions">
                    <button type="button" class="button" id="dps-stats-reset-layout">Restaurar Padrão</button>
                    <button type="submit" class="button button-primary">Salvar Personalização</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
```

---

### 2.3 — JavaScript para Drag & Drop e AJAX

Adicionar em `assets/js/stats-addon.js`:

```javascript
(function($) {
    'use strict';

    // Modal de personalização
    $('#dps-stats-customize-btn').on('click', function() {
        $('#dps-stats-customize-modal').fadeIn(200);
    });

    $('.dps-stats-modal__close, .dps-stats-modal__overlay').on('click', function() {
        $('#dps-stats-customize-modal').fadeOut(200);
    });

    // Drag & Drop com SortableJS (ou implementar com jQuery UI Sortable)
    // Exemplo com jQuery UI Sortable:
    if (typeof $.fn.sortable !== 'undefined') {
        $('#dps-stats-kpi-list').sortable({
            handle: '.dps-stats-kpi-item__handle',
            axis: 'y',
            cursor: 'move',
            opacity: 0.8
        });
    }

    // Salvar personalização via AJAX
    $('#dps-stats-customize-form').on('submit', function(e) {
        e.preventDefault();

        const enabledKpis = [];
        const order = [];
        const sizes = {};

        $('#dps-stats-kpi-list .dps-stats-kpi-item').each(function() {
            const kpiId = $(this).data('kpi-id');
            const checkbox = $(this).find('input[type="checkbox"]');
            const size = $(this).find('.dps-stats-kpi-size').val();

            order.push(kpiId);
            sizes[kpiId] = size;

            if (checkbox.is(':checked')) {
                enabledKpis.push(kpiId);
            }
        });

        const layoutData = {
            enabled_kpis: enabledKpis,
            order: order,
            sizes: sizes
        };

        $.post(ajaxurl, {
            action: 'dps_stats_save_layout',
            nonce: $('#dps_stats_layout_nonce').val(),
            layout: JSON.stringify(layoutData)
        }, function(response) {
            if (response.success) {
                alert('Personalização salva com sucesso!');
                location.reload(); // Recarregar para aplicar mudanças
            } else {
                alert('Erro ao salvar: ' + (response.data || 'Desconhecido'));
            }
        });
    });

    // Restaurar padrão
    $('#dps-stats-reset-layout').on('click', function() {
        if (!confirm('Tem certeza que deseja restaurar o layout padrão? Suas personalizações serão perdidas.')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'dps_stats_reset_layout',
            nonce: $('#dps_stats_layout_nonce').val()
        }, function(response) {
            if (response.success) {
                alert('Layout restaurado para o padrão!');
                location.reload();
            } else {
                alert('Erro ao restaurar: ' + (response.data || 'Desconhecido'));
            }
        });
    });

})(jQuery);
```

---

### 2.4 — AJAX Handlers (Backend)

Adicionar no arquivo principal:

```php
/**
 * AJAX: Salvar layout customizado
 */
function dps_stats_ajax_save_layout() {
    check_ajax_referer( 'dps_stats_save_layout', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permissão negada' );
    }

    $layout_json = isset( $_POST['layout'] ) ? sanitize_text_field( wp_unslash( $_POST['layout'] ) ) : '';
    $layout      = json_decode( $layout_json, true );

    if ( ! is_array( $layout ) ) {
        wp_send_json_error( 'Layout inválido' );
    }

    // Validar estrutura
    $valid_kpis = [
        'appointments', 'revenue', 'ticket_medio', 'new_clients', 'cancellations',
        'return_rate', 'no_show', 'overdue_revenue', 'conversion_rate', 'recurring_clients',
    ];

    $layout['enabled_kpis'] = array_intersect( $layout['enabled_kpis'] ?? [], $valid_kpis );
    $layout['order']        = array_intersect( $layout['order'] ?? [], $valid_kpis );

    if ( dps_stats_save_dashboard_layout( $layout ) ) {
        wp_send_json_success( 'Layout salvo' );
    } else {
        wp_send_json_error( 'Erro ao salvar' );
    }
}
add_action( 'wp_ajax_dps_stats_save_layout', 'dps_stats_ajax_save_layout' );

/**
 * AJAX: Resetar layout para padrão
 */
function dps_stats_ajax_reset_layout() {
    check_ajax_referer( 'dps_stats_save_layout', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permissão negada' );
    }

    dps_stats_reset_dashboard_layout();
    wp_send_json_success( 'Layout resetado' );
}
add_action( 'wp_ajax_dps_stats_reset_layout', 'dps_stats_ajax_reset_layout' );
```

---

### 2.5 — Renderizar Cards com Base no Layout

Modificar `section_stats()` para respeitar o layout customizado:

```php
function section_stats() {
    // ... código existente ...

    // Obter layout customizado
    $layout      = dps_stats_get_dashboard_layout();
    $enabled_ids = $layout['enabled_kpis'] ?? [];
    $order       = $layout['order'] ?? [];
    $sizes       = $layout['sizes'] ?? [];

    // Mapear KPIs para métodos de render
    $kpi_map = [
        'appointments'      => function() use ( $start_date, $end_date ) {
            $count = DPS_Stats_API::get_appointments_count( $start_date, $end_date );
            dps_stats_render_card( '📅', $count, 'Atendimentos', '', '' );
        },
        'revenue'           => function() use ( $start_date, $end_date ) {
            $financial = DPS_Stats_API::get_financial_totals( $start_date, $end_date );
            dps_stats_render_card( '💰', 'R$ ' . number_format( $financial['revenue'], 2, ',', '.' ), 'Receita', 'success', '' );
        },
        // ... adicionar todos os outros KPIs ...
    ];

    echo '<div class="dps-stats-cards">';

    // Renderizar cards na ordem configurada
    foreach ( $order as $kpi_id ) {
        if ( ! in_array( $kpi_id, $enabled_ids, true ) ) {
            continue; // KPI desabilitado
        }

        if ( ! isset( $kpi_map[ $kpi_id ] ) ) {
            continue; // KPI não existe
        }

        $size = $sizes[ $kpi_id ] ?? 'medium';

        echo '<div class="dps-stats-card-wrapper dps-stats-card-wrapper--' . esc_attr( $size ) . '">';
        call_user_func( $kpi_map[ $kpi_id ] );
        echo '</div>';
    }

    echo '</div>';
}
```

---

### 2.6 — CSS para Tamanhos de Card

Adicionar em `assets/css/stats-addon.css`:

```css
/* Dashboard Customizável */
.dps-stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.dps-stats-card-wrapper {
    flex: 0 0 auto;
}

.dps-stats-card-wrapper--small {
    flex-basis: calc(25% - 15px); /* 4 colunas */
}

.dps-stats-card-wrapper--medium {
    flex-basis: calc(33.333% - 15px); /* 3 colunas */
}

.dps-stats-card-wrapper--large {
    flex-basis: calc(50% - 10px); /* 2 colunas */
}

@media (max-width: 1200px) {
    .dps-stats-card-wrapper--small,
    .dps-stats-card-wrapper--medium {
        flex-basis: calc(50% - 10px);
    }
}

@media (max-width: 768px) {
    .dps-stats-card-wrapper {
        flex-basis: 100% !important;
    }
}

/* Modal de Personalização */
.dps-stats-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
}

.dps-stats-modal__overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.dps-stats-modal__content {
    position: relative;
    max-width: 600px;
    margin: 50px auto;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    z-index: 1;
}

.dps-stats-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.dps-stats-modal__close {
    font-size: 24px;
    background: none;
    border: none;
    cursor: pointer;
    color: #6b7280;
}

.dps-stats-modal__body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.dps-stats-kpi-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dps-stats-kpi-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    margin-bottom: 8px;
    cursor: move;
}

.dps-stats-kpi-item__handle {
    cursor: grab;
    color: #9ca3af;
}

.dps-stats-kpi-item label {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.dps-stats-kpi-size {
    padding: 4px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.dps-stats-modal__actions {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
```

---

## ✅ Checklist de Testes

### F4.1 — Metas e Objetivos
- [ ] Configurar metas no formulário (atendimentos, receita, ticket)
- [ ] Ativar "Exibir metas" e verificar cards de progresso no dashboard
- [ ] Verificar cores corretas (verde >100%, azul >70%, amarelo >40%, vermelho <40%)
- [ ] Testar com Finance inativo (meta de receita mostra aviso)
- [ ] Verificar que metas usam mês atual (não período selecionado)
- [ ] Desativar metas e verificar que cards desaparecem

### F4.4 — Dashboard Customizável
- [ ] Clicar "Personalizar Dashboard" abre modal
- [ ] Desmarcar KPIs e verificar que somem do dashboard
- [ ] Arrastar itens para reordenar e verificar nova ordem
- [ ] Mudar tamanho de card (pequeno/médio/grande) e verificar visual
- [ ] Clicar "Salvar Personalização" e recarregar página (persistência)
- [ ] Clicar "Restaurar Padrão" e verificar reset completo
- [ ] Testar com usuário diferente (cada admin tem seu layout)

### Regressão
- [ ] Todas as métricas existentes continuam funcionando
- [ ] Cache invalidation funciona normalmente
- [ ] Filtros avançados (Fase 3B) não quebram
- [ ] Exports CSV funcionam
- [ ] Drill-downs continuam operando

---

## 📦 Dependências

### jQuery UI Sortable
Para drag & drop funcionar, incluir jQuery UI Sortable:

```php
// Em register_assets()
wp_enqueue_script( 'jquery-ui-sortable' );
```

**Alternativa:** Usar biblioteca mais leve como [SortableJS](https://sortablejs.github.io/Sortable/) (sem dependências jQuery).

---

## 🚀 Esforço Estimado

| Task | Horas |
|------|-------|
| F4.1 Settings + UI formulário | 2-3h |
| F4.1 Cards de meta + progresso | 2-3h |
| F4.2 Layout config + helpers | 2h |
| F4.4 Modal + UI personalização | 3-4h |
| F4.4 Drag & drop + AJAX | 2-3h |
| F4.4 Renderização baseada em layout | 2h |
| CSS + testes | 1-2h |
| **TOTAL** | **12-16h** |

---

## 🎯 Critérios de Aceite

- ✅ Admin consegue definir metas mensais (atendimentos, receita, ticket)
- ✅ Cards de meta exibem progresso visual (barra) e percentual
- ✅ Cores de progresso mudam conforme % atingido
- ✅ Admin consegue ocultar/mostrar KPIs específicos
- ✅ Admin consegue reordenar cards (drag & drop ou botões)
- ✅ Admin consegue mudar tamanho de cards (pequeno/médio/grande)
- ✅ Configuração salva por usuário (user_meta) com fallback global
- ✅ Botão "Restaurar Padrão" reseta para layout original
- ✅ Zero breaking changes (instalações existentes continuam funcionando)

---

## 📝 Notas Finais

### Decisões Técnicas
- **User Meta vs Option:** Layout salvo por usuário permite personalização individual. Administradores podem ter dashboards diferentes.
- **Drag & Drop:** jQuery UI Sortable é nativo do WordPress Admin. Alternativa: SortableJS (mais leve, sem jQuery).
- **Metas do Mês Atual:** Não dependem do filtro de período para evitar confusão. Sempre calculam com base no mês corrente.

### Limitações Conhecidas
- Drag & drop requer JavaScript habilitado (fallback: botões "Mover acima/abaixo").
- Configuração visual complexa pode confundir usuários menos experientes (considerar tour/tooltip na v2).

### Melhorias Futuras (Fase 5)
- Salvar múltiplos "presets" de layout (ex: "Visão Financeira", "Visão Operacional")
- Exportar/importar configuração de layout entre usuários
- Dashboard compartilhado (público) para clientes via shortcode

---

**Status:** 📋 Guia completo — pronto para implementação manual (~12-16h)  
**Versão alvo:** 1.6.0  
**Próximos passos:** Implementar F4.2 (Alertas), F4.3 (Relatórios Agendados), F4.5 (REST API) em PRs futuras.
