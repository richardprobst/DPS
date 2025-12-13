# Stats Add-on — Fase 3B Implementation Guide

**Status:** 🟡 Pending Implementation  
**Complexity:** Alta (600+ linhas de código)  
**Estimated Time:** 8-12 horas  
**Dependencies:** Fase 1, 2 e 3.1 concluídas ✅

---

## Visão Geral

Fase 3B adiciona interatividade avançada ao Stats Add-on através de:
- **F3.2:** Drill-down em métricas (modais/listagens)
- **F3.3:** Filtros avançados (serviço, status, funcionário, unidade)
- **F3.4:** Gráfico de tendência temporal com média móvel

---

## F3.2 — Drill-down em Métricas

### Objetivo
Permitir que usuários cliquem em métricas agregadas para ver detalhes completos dos dados subjacentes.

### Implementação Necessária

#### 1. Tornar Cards Clicáveis
**Arquivo:** `desi-pet-shower-stats_addon.php`

Modificar `render_card()` para aceitar parâmetro `$drill_down_url`:

```php
private function render_card( $icon, $value, $label, $variation = null, $type = 'primary', $drill_down_url = '' ) {
    $clickable_class = $drill_down_url ? 'dps-stats-card--clickable' : '';
    $onclick = $drill_down_url ? sprintf( 'onclick="window.location.href=\'%s\'"', esc_url( $drill_down_url ) ) : '';
    ?>
    <div class="dps-stats-card dps-stats-card--<?php echo esc_attr( $type ); ?> <?php echo esc_attr( $clickable_class ); ?>" <?php echo $onclick; ?>>
        <!-- conteúdo do card -->
    </div>
    <?php
}
```

#### 2. Criar Método de Drill-down
**Arquivo:** `desi-pet-shower-stats_addon.php`

```php
/**
 * F3.2: Renderiza modal de drill-down com listagem paginada.
 *
 * @param string $type Tipo de drill-down (appointments, services, etc).
 * @param array  $filters Filtros ativos.
 * @param int    $page Página atual.
 *
 * @since 1.5.0
 */
private function render_drill_down_modal( $type, $filters = [], $page = 1 ) {
    // Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Acesso negado.', 'dps-stats-addon' ) );
    }
    
    $per_page = 50;
    $offset = ( $page - 1 ) * $per_page;
    
    // Query appointments with filters
    $args = [
        'post_type'      => 'dps_agendamento',
        'posts_per_page' => $per_page,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'meta_query'     => [],
    ];
    
    // Apply date filters
    if ( ! empty( $filters['start_date'] ) && ! empty( $filters['end_date'] ) ) {
        $args['meta_query'][] = [
            'key'     => 'appointment_date',
            'value'   => [ $filters['start_date'], $filters['end_date'] ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ];
    }
    
    // Apply service filter
    if ( ! empty( $filters['service_id'] ) ) {
        $args['meta_query'][] = [
            'key'     => 'appointment_services',
            'value'   => serialize( (string) $filters['service_id'] ),
            'compare' => 'LIKE',
        ];
    }
    
    // Apply status filter
    if ( ! empty( $filters['status'] ) && $filters['status'] !== 'all' ) {
        $args['meta_query'][] = [
            'key'     => 'appointment_status',
            'value'   => sanitize_text_field( $filters['status'] ),
            'compare' => '=',
        ];
    }
    
    $appointments = get_posts( $args );
    $total = wp_count_posts( 'dps_agendamento' )->publish; // Simplified, should filter
    $total_pages = ceil( $total / $per_page );
    
    ?>
    <div class="dps-stats-modal">
        <div class="dps-stats-modal__header">
            <h2><?php esc_html_e( 'Atendimentos no Período', 'dps-stats-addon' ); ?></h2>
            <button class="dps-stats-modal__close" onclick="history.back()">✕</button>
        </div>
        <div class="dps-stats-modal__content">
            <?php if ( empty( $appointments ) ) : ?>
                <p><?php esc_html_e( 'Nenhum atendimento encontrado com os filtros selecionados.', 'dps-stats-addon' ); ?></p>
            <?php else : ?>
                <table class="dps-stats-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data', 'dps-stats-addon' ); ?></th>
                            <th><?php esc_html_e( 'Cliente', 'dps-stats-addon' ); ?></th>
                            <th><?php esc_html_e( 'Pet', 'dps-stats-addon' ); ?></th>
                            <th><?php esc_html_e( 'Serviço', 'dps-stats-addon' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dps-stats-addon' ); ?></th>
                            <th><?php esc_html_e( 'Ações', 'dps-stats-addon' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $appointments as $appointment ) : 
                            $date = get_post_meta( $appointment->ID, 'appointment_date', true );
                            $client_id = get_post_meta( $appointment->ID, 'appointment_client_id', true );
                            $pet_id = get_post_meta( $appointment->ID, 'appointment_pet_id', true );
                            $services = get_post_meta( $appointment->ID, 'appointment_services', true );
                            $status = get_post_meta( $appointment->ID, 'appointment_status', true );
                            
                            $client_name = $client_id ? get_the_title( $client_id ) : '—';
                            $pet_name = $pet_id ? get_the_title( $pet_id ) : '—';
                            
                            $service_names = [];
                            if ( is_array( $services ) ) {
                                foreach ( $services as $service_id ) {
                                    $service_names[] = get_the_title( $service_id );
                                }
                            }
                            $service_display = ! empty( $service_names ) ? implode( ', ', $service_names ) : '—';
                            
                            $status_badge_class = 'dps-badge dps-badge--';
                            switch ( $status ) {
                                case 'confirmado':
                                case 'confirmed':
                                    $status_badge_class .= 'primary';
                                    break;
                                case 'concluido':
                                case 'completed':
                                    $status_badge_class .= 'success';
                                    break;
                                case 'cancelado':
                                case 'cancelled':
                                    $status_badge_class .= 'secondary';
                                    break;
                                case 'no_show':
                                    $status_badge_class .= 'danger';
                                    break;
                                default:
                                    $status_badge_class .= 'default';
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $date ) ) ); ?></td>
                            <td><?php echo esc_html( $client_name ); ?></td>
                            <td><?php echo esc_html( $pet_name ); ?></td>
                            <td><?php echo esc_html( $service_display ); ?></td>
                            <td><span class="<?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $appointment->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'dps-stats-addon' ); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ( $total_pages > 1 ) : ?>
                <div class="dps-stats-pagination">
                    <?php if ( $page > 1 ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'stats_drill_page', $page - 1 ) ); ?>" class="button">← <?php esc_html_e( 'Anterior', 'dps-stats-addon' ); ?></a>
                    <?php endif; ?>
                    <span><?php printf( esc_html__( 'Página %d de %d', 'dps-stats-addon' ), $page, $total_pages ); ?></span>
                    <?php if ( $page < $total_pages ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'stats_drill_page', $page + 1 ) ); ?>" class="button"><?php esc_html_e( 'Próxima', 'dps-stats-addon' ); ?> →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
```

#### 3. CSS para Modal e Badges
**Arquivo:** `assets/css/stats-addon.css`

```css
/* F3.2: Drill-down modal */
.dps-stats-card--clickable {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.dps-stats-card--clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dps-stats-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 1200px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    z-index: 10000;
}

.dps-stats-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.dps-stats-modal__header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #374151;
}

.dps-stats-modal__close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background 0.2s;
}

.dps-stats-modal__close:hover {
    background: #f3f4f6;
}

.dps-stats-modal__content {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(80vh - 80px);
}

.dps-stats-table {
    width: 100%;
    border-collapse: collapse;
}

.dps-stats-table th {
    background: #f9fafb;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.dps-stats-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    color: #6b7280;
}

.dps-stats-table tr:hover {
    background: #f9fafb;
}

.dps-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.dps-badge--success {
    background: #d1fae5;
    color: #065f46;
}

.dps-badge--primary {
    background: #dbeafe;
    color: #1e40af;
}

.dps-badge--danger {
    background: #fee2e2;
    color: #991b1b;
}

.dps-badge--secondary {
    background: #f3f4f6;
    color: #6b7280;
}

.dps-stats-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}
```

---

## F3.3 — Filtros Avançados

### Objetivo
Permitir segmentação de métricas por serviço, status, funcionário e unidade.

### Implementação Necessária

#### 1. Renderizar Filtros
**Arquivo:** `desi-pet-shower-stats_addon.php`

```php
/**
 * F3.3: Renderiza filtros avançados.
 *
 * @param array $current_filters Filtros atualmente ativos.
 *
 * @since 1.5.0
 */
private function render_advanced_filters( $current_filters = [] ) {
    // Buscar serviços disponíveis
    $services = get_posts( [
        'post_type'      => 'dps_servico',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    
    // Buscar funcionários (usuários com role relevante)
    $users = get_users( [
        'role__in' => [ 'administrator', 'editor' ], // Ajustar roles conforme necessário
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ] );
    
    ?>
    <div class="dps-stats-advanced-filters">
        <h4><?php esc_html_e( 'Filtros Avançados', 'dps-stats-addon' ); ?></h4>
        <form method="get" class="dps-stats-filters-form">
            <?php foreach ( $_GET as $k => $v ) : if ( strpos( $k, 'stats_' ) === 0 ) continue; ?>
                <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
            <?php endforeach; ?>
            
            <!-- Preservar filtros de data -->
            <?php if ( ! empty( $current_filters['start_date'] ) ) : ?>
                <input type="hidden" name="stats_start" value="<?php echo esc_attr( $current_filters['start_date'] ); ?>">
            <?php endif; ?>
            <?php if ( ! empty( $current_filters['end_date'] ) ) : ?>
                <input type="hidden" name="stats_end" value="<?php echo esc_attr( $current_filters['end_date'] ); ?>">
            <?php endif; ?>
            
            <div class="dps-stats-filters-grid">
                <!-- Filtro de Serviço -->
                <div class="dps-stats-filter">
                    <label for="stats_service"><?php esc_html_e( 'Serviço', 'dps-stats-addon' ); ?></label>
                    <select name="stats_service" id="stats_service">
                        <option value=""><?php esc_html_e( 'Todos os serviços', 'dps-stats-addon' ); ?></option>
                        <?php foreach ( $services as $service ) : ?>
                            <option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( $current_filters['service_id'] ?? '', $service->ID ); ?>>
                                <?php echo esc_html( get_the_title( $service->ID ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro de Status -->
                <div class="dps-stats-filter">
                    <label for="stats_status"><?php esc_html_e( 'Status', 'dps-stats-addon' ); ?></label>
                    <select name="stats_status" id="stats_status">
                        <option value="all"><?php esc_html_e( 'Todos os status', 'dps-stats-addon' ); ?></option>
                        <option value="confirmado" <?php selected( $current_filters['status'] ?? '', 'confirmado' ); ?>><?php esc_html_e( 'Confirmado', 'dps-stats-addon' ); ?></option>
                        <option value="concluido" <?php selected( $current_filters['status'] ?? '', 'concluido' ); ?>><?php esc_html_e( 'Concluído', 'dps-stats-addon' ); ?></option>
                        <option value="cancelado" <?php selected( $current_filters['status'] ?? '', 'cancelado' ); ?>><?php esc_html_e( 'Cancelado', 'dps-stats-addon' ); ?></option>
                        <option value="no_show" <?php selected( $current_filters['status'] ?? '', 'no_show' ); ?>><?php esc_html_e( 'No-Show', 'dps-stats-addon' ); ?></option>
                    </select>
                </div>
                
                <!-- Filtro de Funcionário -->
                <div class="dps-stats-filter">
                    <label for="stats_employee"><?php esc_html_e( 'Funcionário', 'dps-stats-addon' ); ?></label>
                    <select name="stats_employee" id="stats_employee">
                        <option value=""><?php esc_html_e( 'Todos os funcionários', 'dps-stats-addon' ); ?></option>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $current_filters['employee_id'] ?? '', $user->ID ); ?>>
                                <?php echo esc_html( $user->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro de Unidade (placeholder) -->
                <div class="dps-stats-filter">
                    <label for="stats_location"><?php esc_html_e( 'Unidade', 'dps-stats-addon' ); ?></label>
                    <select name="stats_location" id="stats_location" disabled>
                        <option value=""><?php esc_html_e( 'Em breve', 'dps-stats-addon' ); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="dps-stats-filters-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar Filtros', 'dps-stats-addon' ); ?></button>
                <a href="<?php echo esc_url( remove_query_arg( [ 'stats_service', 'stats_status', 'stats_employee', 'stats_location' ] ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Limpar Filtros', 'dps-stats-addon' ); ?></a>
            </div>
        </form>
    </div>
    <?php
}
```

#### 2. Extrair Filtros do Request
**Arquivo:** `desi-pet-shower-stats_addon.php`

```php
/**
 * F3.3: Extrai e valida filtros da URL.
 *
 * @return array Filtros sanitizados.
 *
 * @since 1.5.0
 */
private function get_active_filters() {
    $filters = [];
    
    // Serviço
    if ( ! empty( $_GET['stats_service'] ) ) {
        $service_id = absint( $_GET['stats_service'] );
        if ( get_post_type( $service_id ) === 'dps_servico' ) {
            $filters['service_id'] = $service_id;
        }
    }
    
    // Status
    if ( ! empty( $_GET['stats_status'] ) ) {
        $valid_statuses = [ 'all', 'confirmado', 'concluido', 'cancelado', 'no_show' ];
        $status = sanitize_text_field( $_GET['stats_status'] );
        if ( in_array( $status, $valid_statuses, true ) ) {
            $filters['status'] = $status;
        }
    }
    
    // Funcionário
    if ( ! empty( $_GET['stats_employee'] ) ) {
        $employee_id = absint( $_GET['stats_employee'] );
        if ( get_userdata( $employee_id ) ) {
            $filters['employee_id'] = $employee_id;
        }
    }
    
    // Unidade (futuro)
    if ( ! empty( $_GET['stats_location'] ) ) {
        $filters['location_id'] = absint( $_GET['stats_location'] );
    }
    
    return $filters;
}
```

#### 3. CSS para Filtros
**Arquivo:** `assets/css/stats-addon.css`

```css
/* F3.3: Advanced filters */
.dps-stats-advanced-filters {
    margin-bottom: 24px;
    padding: 20px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.dps-stats-advanced-filters h4 {
    margin: 0 0 16px;
    color: #374151;
    font-size: 16px;
    font-weight: 600;
}

.dps-stats-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.dps-stats-filter label {
    display: block;
    margin-bottom: 6px;
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
}

.dps-stats-filter select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #374151;
    font-size: 14px;
}

.dps-stats-filter select:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

.dps-stats-filters-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
}

@media (max-width: 768px) {
    .dps-stats-filters-grid {
        grid-template-columns: 1fr;
    }
}
```

---

## F3.4 — Gráfico de Tendência Temporal

### Objetivo
Visualizar evolução temporal de atendimentos com média móvel de 7 dias.

### Implementação Necessária

#### 1. Método de Timeseries na API
**Arquivo:** `includes/class-dps-stats-api.php`

```php
/**
 * F3.4: Obtém série temporal de atendimentos.
 *
 * @param string $start_date Data inicial (Y-m-d).
 * @param string $end_date   Data final (Y-m-d).
 * @param array  $filters    Filtros adicionais.
 *
 * @return array ['labels' => [...], 'data' => [...], 'moving_avg' => [...]]
 *
 * @since 1.5.0
 */
public static function get_appointments_timeseries( $start_date, $end_date, $filters = [] ) {
    global $wpdb;
    
    // Cache key com filtros
    $filters_hash = md5( serialize( $filters ) );
    $cache_key = dps_stats_build_cache_key( 'dps_stats_timeseries', $start_date, $end_date ) . '_' . $filters_hash;
    
    $cached = self::cache_get( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }
    
    // Determinar granularidade (diária ou semanal)
    $days_diff = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;
    $granularity = $days_diff <= 31 ? 'daily' : 'weekly';
    
    // Query base
    $sql = "
        SELECT DATE(pm_date.meta_value) as date, COUNT(DISTINCT p.ID) as count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'appointment_date'
        WHERE p.post_type = 'dps_agendamento'
          AND p.post_status = 'publish'
          AND pm_date.meta_value BETWEEN %s AND %s
    ";
    
    // Aplicar filtros
    $where_clauses = [];
    $prepare_values = [ $start_date, $end_date . ' 23:59:59' ];
    
    if ( ! empty( $filters['service_id'] ) ) {
        $sql .= " INNER JOIN {$wpdb->postmeta} pm_service ON p.ID = pm_service.post_id AND pm_service.meta_key = 'appointment_services'";
        $where_clauses[] = "pm_service.meta_value LIKE %s";
        $prepare_values[] = '%' . $wpdb->esc_like( serialize( (string) $filters['service_id'] ) ) . '%';
    }
    
    if ( ! empty( $filters['status'] ) && $filters['status'] !== 'all' ) {
        $sql .= " INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'appointment_status'";
        $where_clauses[] = "pm_status.meta_value = %s";
        $prepare_values[] = $filters['status'];
    }
    
    if ( ! empty( $where_clauses ) ) {
        $sql .= " AND " . implode( " AND ", $where_clauses );
    }
    
    $sql .= " GROUP BY DATE(pm_date.meta_value) ORDER BY DATE(pm_date.meta_value) ASC";
    
    $results = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_values ) );
    
    // Preencher gaps (dias sem atendimentos)
    $all_dates = [];
    $current = strtotime( $start_date );
    $end = strtotime( $end_date );
    while ( $current <= $end ) {
        $all_dates[ date( 'Y-m-d', $current ) ] = 0;
        $current += DAY_IN_SECONDS;
    }
    
    foreach ( $results as $row ) {
        $all_dates[ $row->date ] = (int) $row->count;
    }
    
    // Calcular média móvel de 7 dias
    $moving_avg = [];
    $data_values = array_values( $all_dates );
    foreach ( $data_values as $i => $value ) {
        $start_index = max( 0, $i - 3 );
        $end_index = min( count( $data_values ) - 1, $i + 3 );
        $window = array_slice( $data_values, $start_index, $end_index - $start_index + 1 );
        $moving_avg[] = round( array_sum( $window ) / count( $window ), 1 );
    }
    
    // Formatar labels
    $labels = [];
    foreach ( array_keys( $all_dates ) as $date ) {
        if ( $granularity === 'daily' ) {
            $labels[] = date_i18n( 'd/m', strtotime( $date ) );
        } else {
            $labels[] = 'Sem ' . date_i18n( 'W', strtotime( $date ) );
        }
    }
    
    $result = [
        'labels'     => $labels,
        'data'       => array_values( $all_dates ),
        'moving_avg' => $moving_avg,
        'granularity' => $granularity,
    ];
    
    self::cache_set( $cache_key, $result, HOUR_IN_SECONDS );
    return $result;
}
```

#### 2. Renderizar Seção de Tendência
**Arquivo:** `desi-pet-shower-stats_addon.php`

Adicionar antes dos `details` de serviços:

```php
<?php
// F3.4: Gráfico de tendência
$timeseries = DPS_Stats_API::get_appointments_timeseries( $start_date, $end_date, $active_filters );
if ( count( $timeseries['data'] ) >= 3 ) : // Mínimo 3 pontos
?>
<details class="dps-stats-section" open>
    <summary><span class="dps-stats-section__icon">📈</span> <?php esc_html_e( 'Tendência de Atendimentos', 'dps-stats-addon' ); ?></summary>
    <div class="dps-stats-section__content">
        <canvas id="dps-stats-trend-chart" width="400" height="200"></canvas>
        <script>
            var dpsStatsData = dpsStatsData || {};
            dpsStatsData.trend = {
                labels: <?php echo wp_json_encode( $timeseries['labels'] ); ?>,
                data: <?php echo wp_json_encode( $timeseries['data'] ); ?>,
                label: '<?php esc_html_e( 'Atendimentos', 'dps-stats-addon' ); ?>'
            };
        </script>
    </div>
</details>
<?php endif; ?>
```

---

## Integração Final

### Ordem de Modificações

1. **DPS_Stats_API** (`includes/class-dps-stats-api.php`):
   - Adicionar `get_appointments_timeseries()` ✅

2. **Main Plugin File** (`desi-pet-shower-stats-addon.php`):
   - Bump version para 1.5.0 ✅
   - Adicionar `render_advanced_filters()` ✅
   - Adicionar `render_drill_down_modal()` ✅
   - Adicionar `get_active_filters()` ✅
   - Modificar `section_stats()` para chamar filtros e tendência ✅

3. **CSS** (`assets/css/stats-addon.css`):
   - Adicionar estilos de modal ✅
   - Adicionar estilos de filtros ✅
   - Adicionar estilos de badges ✅

4. **JS** (`assets/js/stats-addon.js`):
   - Já suporta `initTrendChart()` ✅
   - Apenas passar dados via `dpsStatsData.trend`

---

## Checklist de Testes

### F3.2 — Drill-down
- [ ] Clicar em "Atendimentos" abre modal com lista
- [ ] Modal mostra 50 itens por página
- [ ] Paginação funciona (anterior/próxima)
- [ ] Links "Editar" levam ao post correto
- [ ] Filtros de data/serviço são aplicados no drill-down
- [ ] Usuário sem `manage_options` não acessa

### F3.3 — Filtros
- [ ] Dropdowns populados corretamente
- [ ] Selecionar serviço atualiza todas as métricas
- [ ] Selecionar status filtra corretamente
- [ ] "Limpar Filtros" remove todos os filtros
- [ ] URL persiste filtros (sharable)
- [ ] Serviço inválido (ID não existe) é ignorado

### F3.4 — Tendência
- [ ] Gráfico renderiza com dados reais
- [ ] Granularidade diária para ≤31 dias
- [ ] Granularidade semanal para >31 dias
- [ ] Média móvel suaviza picos isolados
- [ ] Tooltip mostra data + contagem
- [ ] Cache funciona (segunda carga mais rápida)

### Regressão
- [ ] Sem filtros: comportamento idêntico a v1.4.0
- [ ] KPIs existentes ainda funcionam
- [ ] Exports CSV não quebram
- [ ] Cache invalidation ainda funciona

---

## Próximos Passos (Pós-Implementação)

1. Testar em ambiente local com dados reais
2. Validar performance com >5000 appointments
3. Revisar acessibilidade (modals, focus trap)
4. Adicionar testes automatizados se possível
5. Documentar no CHANGELOG.md
6. Atualizar README.md com screenshots

---

## Notas Técnicas

### Por que Modal Server-Side?
- Simplicidade: evita AJAX complexo
- SEO-friendly: URLs navegáveis
- Compatibilidade: funciona sem JS
- Cache: reutiliza infraestrutura existente

### Por que Média Móvel de 7 Dias?
- Suaviza variação de fins de semana
- Padrão em análise de métricas operacionais
- Fácil de explicar para usuários finais

### Limitações Conhecidas
- Drill-down por "Top Serviços" requer click handling em Chart.js (complexo)
- Unidade/Local é placeholder (não implementado ainda)
- Funcionário filtra apenas usuários WordPress (não groomer custom)

---

**Status Final:** 🟡 Aguardando implementação manual (~8-12h)
