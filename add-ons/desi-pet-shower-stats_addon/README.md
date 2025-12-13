# DPS by PRObst – Estatísticas Add-on

Dashboard visual de métricas operacionais e financeiras do sistema.

## Visão geral

O **Estatísticas Add-on** fornece um dashboard completo e visual com métricas de uso do sistema, incluindo atendimentos realizados, receita gerada, clientes inativos, serviços mais recorrentes e análise de distribuição de espécies/raças. Ideal para acompanhamento gerencial e tomada de decisões.

### Funcionalidades principais (v1.1.0):
- ✅ **Dashboard visual** com cards de métricas coloridos
- ✅ **Comparativo de períodos** (variação % vs período anterior)
- ✅ **Ticket médio** calculado automaticamente
- ✅ **Taxa de cancelamento** monitorada
- ✅ **Novos clientes** no período
- ✅ **Gráficos Chart.js** para serviços e espécies
- ✅ **Exportação CSV** de métricas e pets inativos
- ✅ **Distribuição de espécies** com gráfico de pizza
- ✅ **Top 5 raças** com barras horizontais
- ✅ **Seções colapsáveis** para melhor organização
- ✅ **API pública** (`DPS_Stats_API`) para integração com outros add-ons
- ✅ **Cache otimizado** via transients

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-stats_addon/`
- **Slug**: `dps-stats-addon`
- **Classe principal**: `DPS_Stats_Addon`
- **Arquivo principal**: `desi-pet-shower-stats-addon.php`
- **API pública**: `includes/class-dps-stats-api.php`
- **Tipo**: Add-on (depende do plugin base)

## Estrutura de arquivos

```
add-ons/desi-pet-shower-stats_addon/
├── desi-pet-shower-stats-addon.php    # Plugin principal
├── includes/
│   └── class-dps-stats-api.php        # API pública para métricas
├── assets/
│   ├── css/
│   │   └── stats-addon.css            # Estilos do dashboard
│   └── js/
│       └── stats-addon.js             # Gráficos Chart.js
├── README.md
└── uninstall.php
```

## Dependências e compatibilidade

### Dependências obrigatórias
- **DPS by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Finance Add-on**: para métricas financeiras completas (receita, ticket médio, inadimplência)
- **Services Add-on**: para ranking de serviços mais realizados

### Versão
- **Versão atual**: v1.1.0
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## API Pública

A classe `DPS_Stats_API` fornece métodos estáticos para consumo por outros add-ons:

```php
// Contagem de atendimentos
DPS_Stats_API::get_appointments_count( $start_date, $end_date, $status = '' );

// Totais financeiros
DPS_Stats_API::get_revenue_total( $start_date, $end_date );
DPS_Stats_API::get_expenses_total( $start_date, $end_date );
DPS_Stats_API::get_financial_totals( $start_date, $end_date );

// Ticket médio
DPS_Stats_API::get_ticket_average( $start_date, $end_date );

// Taxa de cancelamento
DPS_Stats_API::get_cancellation_rate( $start_date, $end_date );

// Novos clientes
DPS_Stats_API::get_new_clients_count( $start_date, $end_date );

// Pets inativos
DPS_Stats_API::get_inactive_pets( $days = 30 );

// Serviços mais solicitados
DPS_Stats_API::get_top_services( $start_date, $end_date, $limit = 5 );

// Distribuição de espécies
DPS_Stats_API::get_species_distribution( $start_date, $end_date );

// Top raças
DPS_Stats_API::get_top_breeds( $start_date, $end_date, $limit = 5 );

// Comparativo de períodos
DPS_Stats_API::get_period_comparison( $start_date, $end_date );

// Exportação CSV
DPS_Stats_API::export_metrics_csv( $start_date, $end_date );
DPS_Stats_API::export_inactive_pets_csv( $days = 30 );
```

## Funcionalidades principais

### Métricas operacionais
- **Total de atendimentos**: contador de agendamentos concluídos
- **Média diária/semanal/mensal**: tendências de volume
- **Taxa de conclusão**: percentual de agendamentos concluídos vs agendados
- **Taxa de cancelamento**: percentual de agendamentos cancelados

### Métricas financeiras (requer Finance Add-on)
- **Receita total**: soma de transações pagas no período
- **Ticket médio**: receita total ÷ número de atendimentos
- **Inadimplência**: percentual de cobranças vencidas
- **Receita prevista**: soma de cobranças pendentes

### Análise de clientes
- **Clientes ativos**: clientes com pelo menos 1 atendimento no período
- **Clientes inativos**: clientes sem atendimento há X dias (configurável)
- **Novos clientes**: cadastros realizados no período
- **Taxa de retenção**: percentual de clientes que retornaram

### Análise de pets
- **Distribuição de espécies**: gráfico de pizza (cachorro 70%, gato 25%, outros 5%)
- **Raças mais atendidas**: ranking de raças por volume
- **Distribuição de porte**: pequeno, médio, grande

### Serviços (requer Services Add-on)
- **Serviços mais realizados**: ranking de serviços por frequência
- **Receita por serviço**: qual serviço gera mais receita

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes públicos.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_base_nav_tabs_after_history` (action)
- **Propósito**: adicionar aba "Estatísticas" à navegação do painel base
- **Implementação**: renderiza tab após aba "Histórico"

#### `dps_base_sections_after_history` (action)
- **Propósito**: renderizar dashboard de estatísticas
- **Implementação**: exibe gráficos, tabelas e métricas

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios. Consulta CPTs do sistema:
- **`dps_appointment`**: para métricas de atendimentos
- **`dps_client`**: para análise de clientes
- **`dps_pet`**: para análise de espécies e raças
- **`dps_service`**: para ranking de serviços (se Services Add-on ativo)

### Tabelas consultadas
- **`dps_transacoes`** (do Finance Add-on): para métricas financeiras

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
Este add-on não armazena options globais.

## Como usar (visão funcional)

### Para administradores

1. **Acessar estatísticas**:
   - No painel base, clique na aba "Estatísticas"
   - Visualize dashboard com métricas principais

2. **Filtrar por período**:
   - Use seletores para definir período (hoje, esta semana, este mês, customizado)
   - Dashboard atualiza automaticamente

3. **Analisar métricas**:
   - **Seção Operacional**: volume de atendimentos, taxas de conclusão/cancelamento
   - **Seção Financeira**: receita, ticket médio, inadimplência
   - **Seção Clientes**: ativos, inativos, novos, retenção
   - **Seção Pets**: distribuição de espécies e raças
   - **Seção Serviços**: ranking de serviços mais realizados

4. **Exportar relatórios**:
   - Clique em "Exportar CSV" ou "Exportar PDF"
   - Arquivo será baixado com dados do período selecionado

### Exemplo de dashboard

```
📊 ESTATÍSTICAS - Novembro/2024

=== Operacional ===
Atendimentos: 127
Média diária: 4,2
Taxa de conclusão: 92%
Taxa de cancelamento: 8%

=== Financeiro ===
Receita: R$ 15.240,00
Ticket médio: R$ 120,00
Inadimplência: 5,2%

=== Clientes ===
Ativos: 98
Inativos: 23
Novos: 15
Retenção: 85%

=== Serviços Mais Realizados ===
1. Banho e Tosa: 65 atendimentos
2. Banho Simples: 42 atendimentos
3. Tosa Higiênica: 20 atendimentos
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com Finance e Services

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender consultas a `dps_transacoes` e outros CPTs
2. **Implementar** seguindo políticas de performance (queries otimizadas)
3. **Atualizar ANALYSIS.md** se criar novas métricas
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** performance com grandes volumes de dados

### Políticas de segurança

- ✅ **Capabilities**: verificar `manage_options` ou `dps_view_stats` antes de exibir
- ✅ **Queries otimizadas**: usar `DPS_Query_Helper` quando possível
- ✅ **Cache**: considerar cachear métricas pesadas por período
- ✅ **Escape**: escapar saída em gráficos e tabelas

### Oportunidades de refatoração

**ANALYSIS.md** indica que este add-on é candidato a refatoração:
- **Arquivo único**: atualmente 538 linhas em um único arquivo
- **Estrutura recomendada**: migrar para padrão modular com `includes/` e `assets/`
- **Classes separadas**: extrair lógica de queries, cálculos e renderização

Consulte **[../docs/refactoring/REFACTORING_ANALYSIS.md](../docs/refactoring/REFACTORING_ANALYSIS.md)** para detalhes.

### Integração com outros add-ons

#### Finance Add-on (opcional)
- Verificar existência de tabela `dps_transacoes` antes de consultar
- Se não disponível, exibir apenas métricas operacionais

#### Services Add-on (opcional)
- Verificar existência de CPT `dps_service` antes de gerar ranking
- Se não disponível, omitir seção de serviços

### Pontos de atenção

- **Performance**: queries pesadas podem demorar em sites com muitos agendamentos
- **Cache**: considerar implementar cache de 1 hora para métricas
- **Índices de BD**: garantir que colunas consultadas estejam indexadas
- **Timeouts**: queries complexas podem estourar `max_execution_time`
- **Arquivo grande**: refatorar seguindo padrão modular

### Melhorias futuras sugeridas

- Gráficos interativos (ChartJS, Google Charts)
- Comparação entre períodos (mês atual vs mês anterior)
- Metas e KPIs configuráveis
- Alertas automáticos (queda de receita, aumento de inadimplência)
- Export de relatórios em PDF com gráficos
- Dashboard público para clientes (anonimizado)

## Checklist de Testes Manuais — Fase 1 (v1.2.0)

### F1.1: Validação de tabela dps_transacoes

#### Teste com Finance Add-on DESATIVADO
- [ ] Desativar o Finance Add-on
- [ ] Acessar a aba "Estatísticas" no painel DPS
- [ ] **Resultado Esperado**: Dashboard abre sem fatal error
- [ ] **Resultado Esperado**: Seção "Métricas Financeiras" mostra aviso amarelo: "⚠️ Finance Add-on não está ativo"
- [ ] **Resultado Esperado**: Métricas operacionais (atendimentos, pets inativos) continuam funcionando
- [ ] Clicar em "Exportar Métricas CSV"
- [ ] **Resultado Esperado**: CSV é gerado com valores financeiros zerados (R$ 0,00)

#### Teste com Finance Add-on ATIVADO
- [ ] Ativar o Finance Add-on
- [ ] Recarregar a aba "Estatísticas"
- [ ] **Resultado Esperado**: Métricas financeiras exibem valores corretos (receita, despesas, lucro)
- [ ] **Resultado Esperado**: Aviso amarelo NÃO aparece

### F1.2: Invalidação automática de cache

#### Teste de invalidação em agendamentos
- [ ] Visualizar dashboard e anotar número de atendimentos (ex: 42 atendimentos)
- [ ] Criar um NOVO agendamento via painel DPS
- [ ] Recarregar a aba "Estatísticas" (F5)
- [ ] **Resultado Esperado**: Número de atendimentos aumenta automaticamente (43 atendimentos)
- [ ] **Resultado Esperado**: Não precisa clicar em "Atualizar dados" manualmente

#### Teste de invalidação em clientes
- [ ] Anotar número de "Novos Clientes" no período
- [ ] Criar um NOVO cliente com data dentro do período selecionado
- [ ] Recarregar a aba "Estatísticas"
- [ ] **Resultado Esperado**: Contador de novos clientes aumenta

#### Teste de throttle (evitar sobrecarga)
- [ ] Criar 5 agendamentos rapidamente em sequência (< 30 segundos)
- [ ] **Resultado Esperado**: Sistema não trava (throttle evita invalidações excessivas)

### F1.3: Assinaturas respeitam período selecionado

#### Teste de filtro temporal
- [ ] Selecionar período: 01/11/2024 a 30/11/2024
- [ ] Clicar em "Aplicar intervalo"
- [ ] Verificar seção "Assinaturas"
- [ ] **Resultado Esperado**: Contadores mostram apenas assinaturas criadas entre 01/11 e 30/11
- [ ] Alterar período: 01/12/2024 a 31/12/2024
- [ ] **Resultado Esperado**: Contadores mudam (não mostram mais assinaturas de novembro)

#### Teste de receita de assinaturas
- [ ] Verificar "Receita de assinaturas no período"
- [ ] **Resultado Esperado**: Valor reflete apenas transações do período selecionado (não soma global)

### F1.4: Remoção de limite de 1000 agendamentos

#### Teste com grande volume (>1000 agendamentos)
- [ ] Selecionar período amplo (ex: últimos 6 meses) em site com >1000 agendamentos
- [ ] Verificar seção "Serviços Mais Solicitados"
- [ ] **Resultado Esperado**: Contagem completa (não truncada em 1000)
- [ ] Verificar seção "Distribuição de Espécies"
- [ ] **Resultado Esperado**: Percentuais corretos (baseados em todos os agendamentos)
- [ ] Verificar "Top 5 Raças"
- [ ] **Resultado Esperado**: Ranking correto sem truncamento

#### Teste de performance
- [ ] Com >2000 agendamentos, carregar dashboard
- [ ] **Resultado Esperado**: Página carrega em tempo razoável (< 10 segundos)
- [ ] **Resultado Esperado**: Sem timeout ou "white screen"

### Teste de Regressão Geral
- [ ] Todos os cards de métricas exibem valores corretos
- [ ] Gráficos Chart.js renderizam sem erros JavaScript
- [ ] Comparativo "vs. Período Anterior" mostra variação % correta
- [ ] Links de export (CSV) funcionam
- [ ] Tabela de pets inativos exibe corretamente
- [ ] Links WhatsApp na tabela abrem corretamente

---

## Checklist de Testes Manuais — Fase 2 (v1.3.0)

### F2.1: SQL GROUP BY (Performance)

#### Teste de performance com alto volume
- [ ] Selecionar período de 90 dias com >1000 agendamentos
- [ ] Abrir DevTools Network e recarregar aba Stats
- [ ] Verificar tempo de carregamento da página
- [ ] **Resultado Esperado**: Dashboard carrega em <3 segundos (vs 5-10s antes)
- [ ] **Resultado Esperado**: Console não mostra erros SQL

#### Teste de precisão dos dados
- [ ] Anotar valores ANTES do update (Top Serviços, Espécies, Raças)
- [ ] Atualizar para v1.3.0
- [ ] Recarregar Stats
- [ ] **Resultado Esperado**: Valores batem com os anotados (mesma lógica, query otimizada)

### F2.2: Fallback Chart.js

#### Teste com CDN disponível (internet OK)
- [ ] Abrir DevTools Network
- [ ] Carregar aba Stats
- [ ] Verificar que Chart.js carrega de `cdn.jsdelivr.net`
- [ ] **Resultado Esperado**: Gráficos renderizam normalmente
- [ ] **Resultado Esperado**: Console não mostra warnings de fallback

#### Teste com CDN bloqueada (simular offline)
- [ ] DevTools Network → Bloquear domínio `cdn.jsdelivr.net` ou ativar "Offline"
- [ ] Recarregar aba Stats (Ctrl+Shift+R)
- [ ] **Resultado Esperado**: Console mostra "Chart.js CDN failed, loading local fallback..."
- [ ] **Resultado Esperado**: Gráficos renderizam usando arquivo local
- [ ] **Resultado Esperado**: Network mostra carregamento de `/assets/js/chart.min.js`

⚠️ **NOTA IMPORTANTE**: O arquivo `assets/js/chart.min.js` é um placeholder. Para funcionamento completo:
```bash
cd add-ons/desi-pet-shower-stats_addon/assets/js/
curl -o chart.min.js https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js
```

### F2.3: Object Cache (Redis/Memcached)

#### Teste com object cache DESATIVADO (padrão)
- [ ] Verificar que `WP_CACHE` não está definido ou `wp_using_ext_object_cache()` retorna false
- [ ] Carregar Stats duas vezes
- [ ] **Resultado Esperado**: Segunda carga mais rápida (hit em transient)
- [ ] Verificar `wp_options` no banco: existem transients `_transient_dps_stats_v*`

#### Teste com object cache ATIVADO (Redis/Memcached)
- [ ] Instalar e ativar plugin de object cache (ex: Redis Object Cache)
- [ ] Confirmar que `wp_using_ext_object_cache()` retorna true
- [ ] Limpar cache Stats (botão "Atualizar dados" ou criar novo agendamento)
- [ ] Carregar Stats primeira vez (cache miss)
- [ ] Carregar Stats segunda vez
- [ ] **Resultado Esperado**: Hit em object cache (não consulta banco)
- [ ] **Resultado Esperado**: Performance melhor em sites com múltiplos admins

#### Teste de invalidação com versioning
- [ ] Carregar Stats e anotar valor de atendimentos
- [ ] Criar novo agendamento
- [ ] Recarregar Stats
- [ ] **Resultado Esperado**: Contador aumenta (cache invalidado via version bump)
- [ ] Verificar `wp_options`: `dps_stats_cache_version` incrementou

### Testes de Regressão

#### Todas as métricas continuam funcionando
- [ ] Atendimentos, receita, despesas, lucro exibem valores corretos
- [ ] Comparativo vs período anterior funciona
- [ ] Pets inativos listam corretamente
- [ ] Novos clientes contam corretamente
- [ ] Taxa de cancelamento calcula corretamente
- [ ] Assinaturas respeitam período selecionado

#### UI e UX não quebraram
- [ ] Cards visuais renderizam corretamente
- [ ] Gráficos Chart.js (barras, pizza) funcionam
- [ ] Seções colapsáveis (`<details>`) abrem/fecham
- [ ] Links WhatsApp funcionam
- [ ] Exports CSV funcionam

---

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.3.0**: FASE 2 — Performance e Otimização
  - SQL GROUP BY para Top Serviços, Espécies e Raças (10-100x mais rápido)
  - Fallback local para Chart.js (funciona offline)
  - Object Cache (Redis/Memcached) com fallback para transients
  - Cache versioning para invalidação eficiente
- **v1.2.0**: FASE 1 — Correções Críticas e Higiene Técnica
  - Validação de tabela dps_transacoes (evita fatal error sem Finance)
  - Invalidação automática de cache (dados sempre atualizados)
  - Assinaturas respeitam período selecionado (consistência)
  - Limite de 1000 agendamentos removido (paginação)
- **v1.1.0**: Modularização, API pública, gráficos Chart.js, comparativo de períodos
- **v0.1.0**: Lançamento inicial com dashboard de métricas operacionais, financeiras, análise de clientes/pets e ranking de serviços

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
