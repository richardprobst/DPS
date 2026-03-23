# Revisão UX/UI da Agenda — Março 2026

## Contexto
- Escopo: `plugins/desi-pet-shower-agenda/`
- Fonte de verdade visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`
- Objetivo: revisar profundamente a UX/UI da Agenda, recriar a camada operacional principal e corrigir inconsistências de navegação e estado.

## Diagnóstico antes da recriação
- O topo da Agenda tinha pouca hierarquia visual e exigia leitura dispersa para entender período, ações principais e filtros ativos.
- Os filtros operacionais estavam fragmentados e não deixavam claro quais recortes estavam aplicados na lista.
- A leitura do volume operacional dependia da inspeção direta das tabelas; faltava resumo rápido de pendências, finalizações, atrasos e pagamentos.
- A navegação entre abas preservava sessão, mas ainda carecia de vínculo ARIA completo entre tab e painel.
- O estado vazio usava a presença bruta de agendamentos carregados, o que podia conflitar com listas filtradas sem resultado.
- A paginação da agenda completa não preservava todos os filtros novos, gerando perda de contexto na navegação.

## Mudanças implementadas
- Recriado o shell da Agenda com header em três zonas: contexto do período, navegação temporal e ações primárias.
- Adicionado painel único de filtros com data foco, cliente, status, serviço, profissional, pagamento pendente e agrupamento por cliente.
- Incluídos chips de contexto e cards de overview para leitura imediata do estado operacional.
- Reorganizadas as listas em painéis por dia, com contagem de pendentes/finalizados por bloco.
- Mantida a estrutura de abas, agora com persistência em URL/sessionStorage e sem perder o foco operacional.
- Consolidada a camada de refresh em `assets/css/agenda-addon.css`, eliminando duplicidade de arquivos e mantendo evolução incremental da UI.

## Correções funcionais aplicadas junto com o redesign
- Empty state agora respeita o resultado filtrado real e oferece ações objetivas de recuperação.
- Paginação do modo “agenda completa” passou a preservar `agenda_tab`, `filter_staff`, `filter_pending_payment` e `group_by_client`.
- Tabs agora expõem `id`, `aria-labelledby`, `hidden`, `tabindex` e navegação por teclado coerente com o padrão de tabs.
- Exportação CSV passou a restaurar o rótulo original do botão após sucesso ou erro.

## Breakpoints e responsividade considerados
- Revisão feita para 375px, 600px, 840px, 1200px e 1920px.
- O layout novo prioriza empilhamento progressivo de ações e filtros, evitando overflow horizontal e CTA inacessível em telas menores.

## Próximos refinamentos recomendados
- Separar visualmente “cancelados” de “finalizados” nas métricas resumidas, evitando leitura ambígua em agendas com muitos encerramentos não concluídos.
- Reduzir densidade das tabelas das abas Operação e Detalhes em mobile com estratégias de colapso por prioridade.
- Considerar quick filters de status no topo para acelerar o uso diário em equipes com alto volume.

## Complemento (2026-03-21) - Verificacao por fases

### Fase 1 - Diagnostico
- Confirmado gap de UX: filtros ativos na logica (`$_GET`) sem painel visivel no shell principal da Agenda.
- Confirmada ambiguidade semantica no resumo: status `cancelado` era contado junto de finalizados.

### Fase 2 - Implementacao de filtros operacionais
- Reintroduzido painel completo de filtros no `render_agenda_shortcode()` com:
  - data foco;
  - cliente;
  - status;
  - servico;
  - profissional;
  - toggles para pagamento pendente e agrupamento por cliente.
- Incluido bloco de chips com contagem de filtros ativos para preservar contexto operacional durante a navegacao.

### Fase 3 - Ajuste de metricas
- `get_agenda_overview_stats()` atualizado para separar `canceled` de `completed`.
- Overview passa a exibir cards distintos de `Finalizados` e `Cancelados`.

### Fase 4 - Acessibilidade e consistencia
- Links de navegacao temporal (`anterior`, `hoje`, `proximo`) receberam `aria-label`.
- Botoes de modo (`Dia`, `Semana`, `Mes`, `Agenda completa`) passam a usar `aria-current="page"` quando ativos.

### Fase 5 - Responsividade e limpeza
- Adicionadas regras M3 para o novo painel de filtros no `assets/css/agenda-addon.css` com validacao nos breakpoints `375`, `600`, `840`, `1200` e `1920`.
- Paginacao da Agenda migrada de `style` inline para classes dedicadas (`.dps-agenda-pagination` e `.dps-pagination-info`).
