# Stats Add-on — Sumário Executivo

**Versão Analisada:** v1.1.0  
**Data da Análise:** 2025-12-13  
**Autor:** Copilot Coding Agent  
**Tipo:** Análise estratégica de métricas e dashboard para Pet Shop (Banho e Tosa)

---

## 1. Visão Geral em Alto Nível

O **Stats Add-on** é o módulo de **inteligência de negócio** do desi.pet by PRObst. Fornece ao dono do pet shop uma **visão consolidada** do desempenho operacional e financeiro através de:

- Dashboard visual com métricas-chave
- Comparativos de período (variação % vs período anterior)
- Gráficos interativos (Chart.js) para serviços e espécies
- Análise de clientes inativos (reengajamento via WhatsApp)
- Exportação de relatórios em CSV
- API pública (`DPS_Stats_API`) para integração com outros add-ons

### O que o add-on Stats faz hoje

| Categoria | Funcionalidades |
|-----------|----------------|
| **Métricas Operacionais** | • Total de atendimentos no período<br>• Taxa de cancelamento<br>• Novos clientes cadastrados<br>• Pets inativos (sem atendimento há 30+ dias) |
| **Métricas Financeiras** | • Receita total (status "pago")<br>• Despesas totais<br>• Lucro líquido (receita - despesas)<br>• Ticket médio (receita ÷ atendimentos) |
| **Análise de Serviços** | • Top 5 serviços mais solicitados<br>• Gráfico de barras com percentual<br>• Distribuição de demanda |
| **Análise de Pets** | • Distribuição por espécie (cão/gato/outro)<br>• Top 5 raças mais atendidas<br>• Pets que precisam de reengajamento |
| **Assinaturas** | • Assinaturas ativas vs pendentes<br>• Receita de assinaturas no período<br>• Valor em aberto (não pago) |
| **Comparativo** | • Variação % vs período anterior (automático)<br>• Métricas atuais vs período equivalente passado |
| **Exports** | • CSV de métricas consolidadas<br>• CSV de pets inativos com telefone |

---

## 2. Onde o Stats é Usado

### 2.1 Interface Admin

**Localização:** Aba "Estatísticas" no painel base DPS (`[dps_base]` shortcode)

**Hook de integração:**
```php
add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_stats_tab' ], 20, 1 );
add_action( 'dps_base_sections_after_history', [ $this, 'add_stats_section' ], 20, 1 );
```

**Capability requerida:** O shortcode base valida `visitor_only`, impedindo que clientes vejam stats. Na prática, apenas admins/managers acessam o painel completo.

**Assets carregados:**
- `stats-addon.css` (449 linhas) — Estilos para cards, gráficos, tabelas
- `stats-addon.js` (311 linhas) — Funções de gráficos Chart.js
- `chart.js@4.4.0` (CDN) — Biblioteca de gráficos

### 2.2 Endpoints AJAX/REST

| Endpoint | Método | Capability | Nonce | Uso |
|----------|--------|------------|-------|-----|
| `admin-post.php?action=dps_clear_stats_cache` | POST | `manage_options` | ✅ `dps_clear_stats_cache_nonce` | Limpa transients de cache |
| `admin-post.php?action=dps_export_stats_csv` | GET | `manage_options` | ✅ `dps_export_nonce` | Export métricas CSV |
| `admin-post.php?action=dps_export_inactive_csv` | GET | `manage_options` | ✅ `dps_export_nonce` | Export pets inativos CSV |

**Nota:** Não há endpoints REST. Todas as métricas são calculadas server-side e renderizadas em HTML no carregamento da aba.

### 2.3 Widgets e Shortcodes

**Widgets WordPress:** Nenhum  
**Shortcodes públicos:** Nenhum  
**Dashboards externos:** Não exposto

O add-on opera **exclusivamente dentro do painel admin**, sem exposição para clientes ou front-end.

---

## 3. KPIs Existentes vs Faltantes

### 3.1 KPIs Implementados (v1.1.0)

| KPI | Definição | Fonte de Dados | Janela Tempo | Filtros |
|-----|-----------|----------------|--------------|---------|
| **Atendimentos** | Contagem de `dps_agendamento` no período | CPT + meta `appointment_date` | Personalizável (start/end) | Nenhum (futuros: serviço, funcionário) |
| **Receita** | SUM(valor) WHERE status='pago' AND tipo='receita' | `dps_transacoes` | Personalizável | Nenhum |
| **Despesas** | SUM(valor) WHERE status='pago' AND tipo='despesa' | `dps_transacoes` | Personalizável | Nenhum |
| **Lucro** | Receita - Despesas | Calculado | Personalizável | Nenhum |
| **Ticket Médio** | Receita ÷ Atendimentos | Calculado | Personalizável | Nenhum |
| **Novos Clientes** | COUNT(`dps_cliente`) WHERE post_date IN period | CPT + date_query | Personalizável | Nenhum |
| **Taxa Cancelamento** | (cancelados ÷ total) × 100 | CPT + meta `appointment_status='cancelado'` | Personalizável | Nenhum |
| **Pets Inativos** | Pets sem atendimento há X dias | CPT `dps_pet` + última data de agendamento | Fixo (30 dias) | Nenhum |
| **Top Serviços** | COUNT(appointment_services) GROUP BY service_id | CPT + meta `appointment_services` (array) | Personalizável | Limit (padrão 5) |
| **Espécies** | COUNT(appointment_pet_id → pet_species) | CPT `dps_pet` + meta `pet_species` | Personalizável | Nenhum |
| **Raças** | COUNT(appointment_pet_id → pet_breed) | CPT `dps_pet` + meta `pet_breed` | Personalizável | Limit (padrão 5) |
| **Assinaturas** | COUNT(`dps_subscription`) por payment_status | CPT + meta `subscription_payment_status` | Global (ignora período) | Nenhum |
| **Variação %** | ((atual - anterior) ÷ anterior) × 100 | Calculado com período equivalente anterior | Automático | Nenhum |

### 3.2 KPIs Faltantes (Contexto Banho e Tosa)

| KPI Sugerido | Valor para o Negócio | Esforço | Prioridade |
|--------------|---------------------|---------|------------|
| **Taxa de Retorno (30/60/90d)** | Medir fidelização de clientes | Médio | **Alta** |
| **No-show** | Agendamentos não comparecidos (status?) | Baixo | **Alta** |
| **Ocupação Agenda** | % de slots preenchidos vs disponíveis | Alto | Média |
| **Ticket Médio por Serviço** | Identificar serviços mais lucrativos | Baixo | **Alta** |
| **Ticket Médio por Espécie/Porte** | Otimizar precificação | Médio | Média |
| **Tempo Médio Atendimento** | Planejamento de capacidade | Alto | Baixa |
| **Receita por Funcionário** | Avaliação de produtividade | Médio | Média |
| **Clientes Recorrentes** | % de clientes com 2+ atendimentos | Médio | **Alta** |
| **LTV (Lifetime Value)** | Valor total gerado por cliente | Alto | Baixa |
| **Inadimplência** | % de receita não paga (vencida) | Baixo | **Alta** |
| **Conversão Cadastro → Primeiro Agendamento** | Taxa de ativação de novos clientes | Médio | Média |
| **Sazonalidade** | Padrão de demanda por mês/estação | Médio | Baixa |

**Legenda Prioridade:**
- **Alta:** Impacto direto em receita ou decisões operacionais críticas
- Média: Melhora planejamento e eficiência
- Baixa: Insights avançados, não urgentes

---

## 4. Pontos Fortes

### 4.1 Arquitetura e Código

✅ **API pública bem estruturada:** `DPS_Stats_API` com 14 métodos estáticos reutilizáveis  
✅ **Cache inteligente:** Transients com TTL de 1h (métricas) e 24h (inatividade)  
✅ **Integração com Finance API:** Usa `DPS_Finance_API::get_period_totals()` quando disponível, com fallback para SQL direto  
✅ **Query otimizada para pets inativos:** Substituiu N+1 por query SQL com GROUP BY (redução de ~1500 queries para 1)  
✅ **Modularização:** Assets em arquivos separados (CSS/JS), não mais inline  
✅ **Gráficos profissionais:** Chart.js 4.4.0 com configuração customizada  

### 4.2 Segurança

✅ **Nonces em todas as ações:** `dps_clear_stats_cache_nonce`, `dps_export_nonce`  
✅ **Capability checks:** `manage_options` em endpoints sensíveis  
✅ **Sanitização de entrada:** `sanitize_text_field()` em datas, `absint()` em limites  
✅ **Escape de saída:** `esc_html()`, `esc_url()`, `esc_attr()` consistentes  
✅ **Prepared statements:** `$wpdb->prepare()` em queries SQL diretas  

### 4.3 UX/UI

✅ **Dashboard visual limpo:** Cards coloridos com ícones e variação %  
✅ **Filtro de período flexível:** Seletor de data inicial/final com apply button  
✅ **Comparativo automático:** Variação % vs período anterior sem configuração  
✅ **Seções colapsáveis:** `<details>` HTML5 para organizar conteúdo  
✅ **Link WhatsApp direto:** Reengajamento de pets inativos com mensagem pré-populada  
✅ **Exports em CSV:** Dados estruturados com BOM UTF-8 para Excel  

---

## 5. Pontos Fracos

### 5.1 Performance e Escalabilidade

⚠️ **Limite fixo de 1000 agendamentos por query:** Hardcoded em `posts_per_page => 1000`  
- Risco: Pet shops com >1000 atendimentos/mês terão dados incompletos
- Solução: Usar paginação ou remover limite com validação de timeout

⚠️ **Query de agendamentos sem índices otimizados:**  
```php
'meta_query' => [
    [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
    [ 'key' => 'appointment_date', 'value' => $end_date, 'compare' => '<=', 'type' => 'DATE' ]
]
```
- Risco: Performance degrada com muitos agendamentos (meta_query é lento)
- Solução: Considerar tabela agregada diária ou índices compostos

⚠️ **Sem paginação na tabela de pets inativos:**  
- Exibe apenas 20 na tela, mas calcula TODOS em memória
- Risco: Timeout com >500 pets
- Solução: Aplicar filtro `dps_stats_inactive_pets_limit` antes do cálculo

⚠️ **Cache invalidado APENAS manualmente:**  
- Não há hooks de invalidação automática quando:
  - Agendamento muda de status
  - Pagamento é registrado/estornado
  - Cliente/Pet é editado
- Risco: Dados "congelados" até admin clicar "Atualizar dados"
- Solução: Adicionar hooks `save_post_dps_agendamento`, `dps_finance_transaction_updated`, etc.

### 5.2 Consistência e "Fonte da Verdade"

⚠️ **Definição de "receita" ambígua:**  
- Código: status='pago' (receita REALIZADA)
- Label na tela: "Receita entre X e Y" (pode ser interpretado como receita LANÇADA)
- Risco: Confusão entre receita realizada vs projetada
- Solução: Adicionar tooltip ou legenda explicativa

⚠️ **Taxa de cancelamento sem distinção de motivo:**  
- Calcula apenas `appointment_status='cancelado'`
- Não diferencia: cancelamento pelo cliente, no-show, reagendamento
- Risco: Métrica pouco actionable
- Solução: Adicionar meta `cancellation_reason` e drill-down

⚠️ **Assinaturas ignoram período selecionado:**  
- Métricas de assinaturas são GLOBAIS (todas as subscriptions, não filtradas por data)
- Label diz "Receita de assinaturas no período", mas contagem de ativas/pendentes é total
- Risco: Inconsistência entre dados exibidos
- Solução: Filtrar subscriptions por `post_date` ou adicionar meta de período ativo

⚠️ **Timezone não explícito:**  
- Usa `current_time( 'timestamp' )` do WordPress
- Mas comparações de data em queries usam string `Y-m-d` sem considerar hora
- Risco: Agendamentos das 23h podem "vazar" para o dia seguinte em timezones diferentes
- Solução: Normalizar para UTC ou timezone configurado do site

### 5.3 Lacunas de Funcionalidades

⚠️ **Sem drill-down:**  
- Clique em "42 atendimentos" não exibe lista detalhada
- Não há link para agendamentos do período
- Risco: Baixa actionability dos insights
- Solução: Adicionar modais ou páginas de detalhe

⚠️ **Sem alertas ou notificações:**  
- Admin precisa acessar manualmente para ver quedas de receita/atendimentos
- Risco: Reação tardia a problemas
- Solução: WP-Cron com email quando variação < -X%

⚠️ **Sem agrupamento por:**  
- Funcionário/groomer (quem mais atende)
- Unidade/local (se houver múltiplas)
- Período do dia (manhã/tarde/noite)
- Risco: Insights limitados para otimização operacional
- Solução: Adicionar filtros na UI

⚠️ **Sem previsões ou tendências:**  
- Gráficos mostram apenas período atual
- Não há linha de tendência ou projeção
- Risco: Planejamento reativo, não proativo
- Solução: Gráfico de linha com histórico 3-6 meses

---

## 6. Riscos Técnicos

### 6.1 Dependências Externas

| Dependência | Tipo | Risco | Mitigação |
|-------------|------|-------|-----------|
| **Finance Add-on** | Opcional | Se desativado, métricas financeiras quebram | ✅ Fallback para SQL direto implementado |
| **Chart.js CDN** | Obrigatória | CDN offline = gráficos não renderizam | ⚠️ Sem fallback local |
| **Tabela `dps_transacoes`** | Obrigatória | Se Finance nunca foi ativado, tabela não existe | ❌ SEM validação de existência |
| **CPT `dps_subscription`** | Opcional | Se Subscription não ativo, seção vazia | ✅ Funciona (apenas 0 resultados) |

**Recomendação Crítica:**  
```php
// ANTES de consultar dps_transacoes
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dps_transacoes'");
if ( ! $table_exists ) {
    // Exibir apenas métricas operacionais
}
```

### 6.2 Performance em Escala

| Cenário | Impacto | Mitigação |
|---------|---------|-----------|
| Pet shop com 2000+ atendimentos/mês | Queries lentas, timeout | Pré-agregação diária via cron |
| 1000+ pets cadastrados | Cálculo de inativos demora 10-30s | Processar em background, cachear 24h |
| Múltiplos admins acessando simultaneamente | Competição de cache, queries duplicadas | Object cache (Redis/Memcached) |

### 6.3 Segurança e Privacidade

⚠️ **Exposição de PII em exports:**  
- CSV de pets inativos inclui: nome do pet, nome do cliente, telefone
- Não há avisos de LGPD ou consentimento
- Risco: Violação de privacidade se arquivo vazar
- Solução: Adicionar aviso LGPD, criptografar exports, log de quem baixou

⚠️ **Capability muito permissiva:**  
- `manage_options` é equivalente a admin total
- Funcionários/managers deveriam ver stats SEM poder limpar cache ou exportar
- Solução: Criar capability `dps_view_stats` separada de `dps_manage_stats`

---

## 7. Riscos de Dados

### 7.1 Duplicidade e Fonte da Verdade

✅ **Não há duplicação de dados:** Stats consome CPTs/tabelas existentes, não cria cópias  
✅ **Fonte única:** `dps_transacoes` para financeiro, CPTs do núcleo para operacional  
❌ **Sem auditoria:** Não registra quando/quem consultou métricas ou alterou período  

### 7.2 Consistência Temporal

⚠️ **Comparativo de períodos assume duração igual:**  
```php
$duration = $end_ts - $start_ts;
$prev_start = date( 'Y-m-d', $start_ts - $duration - DAY_IN_SECONDS );
```
- Se período atual = 30 dias, período anterior = 30 dias anteriores
- Mas se mês atual tem 31 dias e anterior 28, comparação não é equivalente
- Solução: Usar `strtotime( '-1 month', $start_ts )` para meses completos

⚠️ **Sem normalização de feriados/fins de semana:**  
- Comparar novembro (30 dias) com dezembro (31 dias + feriados) gera variações falsas
- Solução: Oferecer comparação "mesmo mês do ano anterior"

### 7.3 Timezone e Horários

⚠️ **Data de agendamento é string Y-m-d, sem hora:**  
- Queries comparam `appointment_date >= '2024-11-01'`
- Mas se timezone do WP for diferente de UTC, pode haver descasamento
- Solução: Normalizar para início/fim do dia no timezone correto

---

## 8. Oportunidades Claras de Melhoria

### 8.1 Performance (Curto Prazo)

| Melhoria | Impacto | Esforço |
|----------|---------|---------|
| **Validar existência de `dps_transacoes`** | Evita fatal error se Finance nunca foi ativado | 1h |
| **Paginação de pets inativos** | Evita timeout com muitos pets | 2h |
| **Cache invalidação automática** | Dados sempre atualizados sem intervenção manual | 4h |
| **Fallback local para Chart.js** | Gráficos funcionam mesmo com CDN offline | 2h |

### 8.2 Funcionalidades (Médio Prazo)

| Melhoria | Impacto | Esforço |
|----------|---------|---------|
| **KPIs faltantes** (no-show, taxa retorno, inadimplência) | Decisões mais informadas | 8-12h |
| **Drill-down em métricas** | Links para lista de agendamentos/clientes | 6h |
| **Filtros avançados** (serviço, funcionário, unidade) | Análise segmentada | 12h |
| **Gráfico de tendência** (linha temporal) | Visualizar evolução ao longo do tempo | 8h |
| **Alertas automáticos** (email quando KPI < threshold) | Ação proativa | 10h |

### 8.3 UX e Decisão (Longo Prazo)

| Melhoria | Impacto | Esforço |
|----------|---------|---------|
| **Dashboard customizável** | Admin escolhe quais KPIs exibir | 16h |
| **Comparativo flexível** | Escolher período de comparação (mês anterior, ano anterior, etc.) | 6h |
| **Metas e objetivos** | Definir metas de receita/atendimentos e acompanhar progresso | 12h |
| **Relatórios agendados** | Email semanal/mensal automático com resumo | 8h |
| **Widget WP Dashboard** | Resumo rápido no painel principal do WP | 6h |

---

## 9. Conclusão

### 9.1 Nota Geral: **7.5/10**

| Critério | Nota | Observação |
|----------|------|------------|
| **Funcionalidade** | 8/10 | Métricas essenciais presentes, faltam KPIs avançados |
| **Confiabilidade** | 7/10 | Fonte de dados correta, mas cache pode ficar desatualizado |
| **Performance** | 6/10 | Funciona bem até ~1000 agendamentos, depois degrada |
| **Segurança** | 8/10 | Nonces e capabilities OK, falta LGPD em exports |
| **UX** | 8/10 | Dashboard visual limpo, falta drill-down e filtros |
| **Manutenibilidade** | 8/10 | Código modular, bem documentado |

### 9.2 Recomendações Prioritárias

**🔴 Críticas (Fazer Agora):**
1. Validar existência de `dps_transacoes` antes de consultar
2. Adicionar capability `dps_view_stats` separada de `manage_options`
3. Corrigir métricas de assinaturas para respeitar período selecionado
4. Adicionar aviso LGPD em exports de dados pessoais

**🟡 Importantes (Próximas 2-4 Semanas):**
5. Implementar invalidação automática de cache (hooks de save_post)
6. Adicionar KPIs faltantes: no-show, taxa de retorno, inadimplência
7. Remover limite de 1000 agendamentos ou implementar paginação
8. Criar fallback local para Chart.js

**🟢 Melhorias (2-3 Meses):**
9. Drill-down em métricas (links para listas detalhadas)
10. Filtros avançados (serviço, funcionário, período do dia)
11. Gráfico de tendência temporal (linha de evolução)
12. Alertas automáticos por email

---

## 10. Próximos Passos

Consulte **`STATS_ADDON_DEEP_ANALYSIS.md`** para:
- Análise técnica detalhada de cada método
- Roadmap de melhorias em FASES com esforço estimado
- Achados técnicos catalogados por severidade
- Exemplos de código para implementações sugeridas

**Fim do Sumário Executivo**
