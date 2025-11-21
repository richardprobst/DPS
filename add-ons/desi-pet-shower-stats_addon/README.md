# Desi Pet Shower – Estatísticas Add-on

Dashboard de métricas operacionais e financeiras do sistema.

## Visão geral

O **Estatísticas Add-on** fornece um dashboard completo com métricas de uso do sistema, incluindo atendimentos realizados, receita gerada, clientes inativos, serviços mais recorrentes e análise de distribuição de espécies/raças. Ideal para acompanhamento gerencial e tomada de decisões.

Funcionalidades principais:
- Métricas de atendimentos (total, média por período, taxa de conclusão)
- Métricas financeiras (receita, ticket médio, inadimplência)
- Análise de clientes inativos (sem atendimento há X dias)
- Ranking de serviços mais realizados
- Distribuição de espécies e raças atendidas
- Filtros por período (dia, semana, mês, ano, customizado)

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-stats_addon/`
- **Slug**: `dps-stats-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-stats-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Finance Add-on**: para métricas financeiras completas (receita, ticket médio, inadimplência)
- **Services Add-on**: para ranking de serviços mais realizados

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

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

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com dashboard de métricas operacionais, financeiras, análise de clientes/pets e ranking de serviços

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
