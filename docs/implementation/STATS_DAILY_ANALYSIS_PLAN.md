# Plano de Implementação: Análise Diária e por Período na Aba Estatísticas

**Data:** 08/01/2026  
**Versão:** 1.0  
**Status:** Planejado

## Contexto

Com a remoção das seções "Resumo do Dia" e "Relatório de Ocupação" da agenda (que eram elementos duplicados/fragmentados), identificamos que algumas métricas específicas dessas seções ainda não estão disponíveis na aba Estatísticas. Este documento descreve o plano para integrar essas funcionalidades.

## Funcionalidades Removidas da Agenda

### Resumo do Dia (`render_admin_dashboard`)
- ✅ Pendentes (contagem) → **Já existe** na Estatísticas (filtrar período = dia)
- ✅ Finalizados (contagem) → **Já existe** na Estatísticas (Atendimentos)
- ✅ Faturamento Estimado → **Já existe** na Estatísticas (Receita)
- ✅ Taxa de Cancelamento (semana) → **Já existe** na Estatísticas
- ✅ Média diária (7d) → **Pode ser calculado** com período de 7 dias

### Relatório de Ocupação (`render_occupancy_report`)
- ✅ Taxa de Conclusão → **Já existe** implicitamente (Atendimentos - Cancelamentos)
- ✅ Taxa de Cancelamento → **Já existe** na Estatísticas
- ❌ **Horário de Pico** → **NÃO EXISTE** - precisa implementar
- ❌ **Média por Hora Ativa** → **NÃO EXISTE** - precisa implementar
- ✅ Distribuição por Status → Parcialmente coberto pelo gráfico de serviços

## Plano de Implementação

### Fase 1: Shortcuts de Período (Prioridade Alta)

Adicionar botões de atalho na aba Estatísticas para facilitar análise por período:

```
[ Hoje ] [ Ontem ] [ Últimos 7 dias ] [ Últimos 30 dias ] [ Este mês ] [ Mês anterior ]
```

**Arquivos a modificar:**
- `plugins/desi-pet-shower-stats/desi-pet-shower-stats-addon.php`
  - Método `render_date_filter()` - adicionar botões de atalho

**Estimativa:** 2-3 horas

### Fase 2: Métricas de Ocupação (Prioridade Média)

Adicionar nova seção "Análise de Ocupação" na aba Estatísticas com:

1. **Horário de Pico**
   - Calcula qual horário do dia teve mais agendamentos
   - Exibe em formato "14:00 - 15:00"

2. **Média por Hora Ativa**
   - Divide total de atendimentos pelas horas que tiveram agendamentos
   - Ajuda a entender capacidade de atendimento

3. **Distribuição por Horário**
   - Gráfico de barras mostrando quantidade de atendimentos por faixa horária
   - Faixas: manhã (08-12h), tarde (12-18h), noite (18-22h)

**Arquivos a modificar:**
- `plugins/desi-pet-shower-stats/includes/class-dps-stats-api.php`
  - Novo método: `get_peak_hours($start_date, $end_date)`
  - Novo método: `get_hourly_distribution($start_date, $end_date)`

- `plugins/desi-pet-shower-stats/desi-pet-shower-stats-addon.php`
  - Nova seção em `section_stats()`
  - Novo método: `render_occupancy_metrics()`

**Estimativa:** 4-6 horas

### Fase 3: Taxa de Conclusão Explícita (Prioridade Baixa)

Adicionar card específico para Taxa de Conclusão nos KPIs existentes:

```
Taxa de Conclusão = (Finalizados + Finalizados e Pagos) / (Total - Cancelados) × 100
```

**Arquivos a modificar:**
- `plugins/desi-pet-shower-stats/includes/class-dps-stats-api.php`
  - Novo método: `get_completion_rate($start_date, $end_date)`

- `plugins/desi-pet-shower-stats/desi-pet-shower-stats-addon.php`
  - Adicionar card em `render_metric_cards()`

**Estimativa:** 1-2 horas

## Especificações Técnicas

### Novo Método: `get_peak_hours()`

```php
/**
 * Obtém horário de pico de agendamentos no período.
 *
 * @param string $start_date Data inicial (Y-m-d).
 * @param string $end_date   Data final (Y-m-d).
 *
 * @return array ['peak_hour' => string, 'count' => int, 'avg_per_active_hour' => float]
 */
public static function get_peak_hours( $start_date, $end_date ) {
    global $wpdb;
    
    $sql = $wpdb->prepare(
        "SELECT HOUR(pm_time.meta_value) as hour, COUNT(*) as count
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id 
           AND pm_date.meta_key = 'appointment_date'
         INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id 
           AND pm_time.meta_key = 'appointment_time'
         WHERE p.post_type = 'dps_agendamento'
           AND p.post_status = 'publish'
           AND pm_date.meta_value >= %s
           AND pm_date.meta_value <= %s
         GROUP BY HOUR(pm_time.meta_value)
         ORDER BY count DESC",
        $start_date,
        $end_date
    );
    
    $results = $wpdb->get_results( $sql );
    
    // Processar e retornar
}
```

### Novo Método: `get_completion_rate()`

```php
/**
 * Obtém taxa de conclusão de agendamentos.
 *
 * @param string $start_date Data inicial (Y-m-d).
 * @param string $end_date   Data final (Y-m-d).
 *
 * @return array ['value' => float, 'unit' => '%', 'completed' => int, 'total' => int]
 */
public static function get_completion_rate( $start_date, $end_date ) {
    $total = self::get_appointments_count( $start_date, $end_date );
    $cancelled = self::get_appointments_count( $start_date, $end_date, 'cancelado' );
    $completed_base = $total - $cancelled;
    
    // Buscar finalizados e finalizados_pago
    $finished = self::get_appointments_count( $start_date, $end_date, 'finalizado' );
    $paid = self::get_appointments_count( $start_date, $end_date, 'finalizado_pago' );
    
    $completed = $finished + $paid;
    $rate = $completed_base > 0 ? round( ( $completed / $completed_base ) * 100, 1 ) : 0;
    
    return [
        'value'     => $rate,
        'unit'      => '%',
        'completed' => $completed,
        'total'     => $completed_base,
    ];
}
```

## Cronograma Sugerido

| Fase | Descrição | Prioridade | Estimativa | Dependência |
|------|-----------|------------|------------|-------------|
| 1 | Shortcuts de período | Alta | 2-3h | Nenhuma |
| 2 | Métricas de ocupação | Média | 4-6h | Fase 1 |
| 3 | Taxa de conclusão | Baixa | 1-2h | Nenhuma |

**Total estimado:** 7-11 horas de desenvolvimento

## Considerações

1. **Cache:** Todos os novos métodos devem utilizar o sistema de cache existente (`cache_get`/`cache_set`)

2. **Performance:** Queries SQL otimizadas com JOINs e GROUP BY conforme padrão F2.1 existente

3. **Internacionalização:** Usar `__()` e `esc_html_e()` para todas as strings

4. **Depreciação implementada:** Os métodos `render_occupancy_report` e `render_admin_dashboard` foram marcados como `@deprecated 1.6.0` com chamadas `_deprecated_function()`. A remoção completa está prevista para v1.7.0 ou posterior.

## Conclusão

A remoção das seções duplicadas da agenda simplifica a interface e centraliza as análises na aba Estatísticas. As fases descritas acima complementam a aba com as métricas que ainda não estavam disponíveis, garantindo que nenhuma funcionalidade útil seja perdida.

A maioria das funcionalidades já está coberta pela aba Estatísticas existente - basta o usuário ajustar o filtro de período para "hoje" ou "1 dia" para obter o equivalente ao "Resumo do Dia".
