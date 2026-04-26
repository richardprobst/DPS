# Screenshots 2026-04-24 — Agenda

## Agenda: card operacional simplificado

Fonte de verdade visual seguida nesta correção: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrão DPS Signature).

### Objetivo

Remover do card operacional os blocos redundantes `Serviços`, `Financeiro`, `Logística` e `Checklist`, pois os dados completos ficam no inspetor lateral da Agenda. Também foi removido o botão `Operação` do rodapé do card e do menu secundário.

### Antes/Depois

- Antes: o card repetia dados já presentes na barra lateral e exibia três ações visíveis (`Cobrar cliente`, `Operação`, `Mais`).
- Depois: o card fica focado em horário, status, pet, tutor e ações principais (`Cobrar cliente`, `Mais`), mantendo os detalhes completos no inspetor lateral.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`

### Capturas

- [agenda-operational-card-simplified-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-375.png)
- [agenda-operational-card-simplified-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-600.png)
- [agenda-operational-card-simplified-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-840.png)
- [agenda-operational-card-simplified-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-1200.png)
- [agenda-operational-card-simplified-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-1920.png)
- [agenda-operational-card-simplified-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-check.json)
- [agenda-operational-card-simplified-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-operational-card-simplified-preview.html)

### Breakpoints validados

- `375`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `600`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `840`: sem overflow horizontal; card sem blocos redundantes; ações visíveis `COBRAR CLIENTE` e `MAIS`.
- `1200`: sem overflow horizontal; card simplificado e inspetor lateral visível com `Serviços`, `Financeiro`, `Logística` e `Checklist`.
- `1920`: sem overflow horizontal; card simplificado e inspetor lateral visível com `Serviços`, `Financeiro`, `Logística` e `Checklist`.

### Validação automática

- `cardMetaCount = 0`
- `cardProgressCount = 0`
- `cardContainsForbiddenLabels = []`
- `actionText = ["COBRAR CLIENTE", "MAIS"]`
- `consoleMessages = []`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php.__backup_20260424-002414`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260424-002414`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260424-002414`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `php -l` remoto: sem erros de sintaxe.
- Hashes SHA-256 remotos conferidos contra os arquivos locais.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`, PHP `8.4.19`, headers sem cache.
- WP-CLI remoto confirmou o add-on `desi-pet-shower-agenda` ativo.

## Agenda: Check-in / Check-out separado do Checklist Operacional

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Separar a acao de Check-in / Check-out do Checklist Operacional e criar um atalho direto antes do botao de status do servico. O atalho mostra `Check-in` quando ainda nao existe entrada, `Check-out` quando a entrada ja existe, e `Editar check-in/out` quando as duas etapas ja foram registradas.

### Comportamento coberto

- O botao principal aparece antes do botao de status do atendimento.
- Quando os dois registros existem, o fluxo abre uma escolha entre `Editar check-in` e `Editar check-out`, com indicacao de que cada etapa ja foi feita.
- A escolha `Editar check-in` abre somente a etapa de Check-in.
- A escolha `Editar check-out` abre somente a etapa de Check-out.
- `MAIS > Checklist Operacional` abre somente o Checklist Operacional, sem exibir o painel de Check-in / Check-out.
- Nenhuma gravacao operacional foi feita durante a validacao; os botoes de salvar nao foram acionados.
- Mudancas persistentes continuam usando os endpoints existentes de checklist, check-in, check-out e status, preservando os registros em historico/logs.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`

### Capturas

- [agenda-checkio-choice-dialog-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-checkio-choice-dialog-1200.png)
- [agenda-checkin-selected-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-checkin-selected-1200.png)
- [agenda-checkout-selected-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-checkout-selected-1200.png)
- [agenda-checklist-only-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-checklist-only-1200.png)
- [agenda-checkio-flow-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-checkio-flow-check.json)

### Validacao publicada

- URL validada: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week&codex_bust=checkio-label-20260424-233056`
- Atendimento validado: `1667`
- Estado principal: `Editar check-in/out`, `Pago`, `Mais`.
- Dialogo de escolha: `Editar check-in` e `Editar check-out`, ambos habilitados e marcados como ja feitos.
- Dialogo Check-in: `checklistVisible=false`, `checkinVisible=true`, `checkoutVisible=false`.
- Dialogo Check-out: `checklistVisible=false`, `checkinVisible=false`, `checkoutVisible=true`.
- Dialogo Checklist Operacional: `checklistVisible=true`, `checkinPanelVisible=false`.

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-checkio-flow-20260424-232537`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-checkio-label-20260424-233056`
- Hashes SHA-256 remotos conferidos contra os arquivos locais.
- `php -l` remoto: sem erros em `includes/trait-dps-agenda-renderer.php` e `desi-pet-shower-agenda-addon.php`.
- Validacoes locais: `node --check` nos JS alterados, `php -l` nos PHP relevantes e `git diff --check`.

## Agenda: rodada integral de testes por fases

Fonte de verdade visual seguida nesta rodada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrão DPS Signature).

### Objetivo

Executar uma nova rodada criteriosa na Agenda publicada, interagindo com cada área operacional do atendimento e corrigindo pontos encontrados no caminho sem reintroduzir qualquer resíduo do padrão visual antigo.

### Fases executadas

- Fase 1: contratos, arquivos e riscos do fluxo da Agenda.
- Fase 2: navegação, views, filtros e responsividade.
- Fase 3: card/linha do atendimento, botão principal, botão `MAIS` e resumo lateral.
- Fase 4: modais operacionais, histórico, logs, reagendamento, logística e fluxos críticos sem confirmar ações finais destrutivas.
- Fase 5: publicação no servidor, validação no runtime e registro visual.

### Ajustes aplicados na rodada

- Padronização de cópia visível: `Período`, `Mês`, `Horário`, `ação primária`, `serviços`, `observações`, `Duração` e demais rótulos com acentuação correta.
- Troca dos usos de tokens legados de movimento na Agenda por tokens `signature`, mantendo DPS Signature como padrão ativo.
- Formatação do card `Reagendar` dentro de `MAIS` para `20/04/2026 23:30`.
- Resumo lateral: `Últimos logs` agora mostra rótulos legíveis (`Status alterado 24/04 18:34`) em vez de chaves internas como `status_change`.
- Conferência de que `MAIS` concentra serviços, Checklist Operacional, Check-in / Check-out, logística, reagendamento e histórico.
- Conferência de que o fluxo `Cancelado` oferece `Cancelar`/`Reagendar` e que atendimento já cancelado oferece `Reabrir atendimento`/`Reagendar`.
- Conferência de que selecionar uma opção de serviço finalizado abre o Checklist Operacional em seguida, para reduzir esquecimento operacional.
- Conferência da linha do tempo auditável com `Campo alterado`, `Valor anterior` e `Novo valor`.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Capturas

- [agenda-integral-audit-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-375.png)
- [agenda-integral-audit-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-600.png)
- [agenda-integral-audit-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-840.png)
- [agenda-integral-audit-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-1200.png)
- [agenda-integral-audit-1200-viewport.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-1200-viewport.png)
- [agenda-integral-audit-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-1920.png)
- [agenda-integral-audit-more-actions-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-more-actions-1200.png)
- [agenda-integral-audit-cancel-path-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-cancel-path-1200.png)
- [agenda-integral-audit-history-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-history-1200.png)
- [agenda-integral-audit-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-audit-check.json)
- [agenda-integral-sidebar-logs-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-integral-sidebar-logs-check.json)

### Breakpoints validados

- `375`: sem overflow horizontal; controles principais empilham sem corte.
- `600`: sem overflow horizontal; filtros e ações mantêm área de toque adequada.
- `840`: sem overflow horizontal; a lista e os botões preservam leitura.
- `1200`: sem overflow horizontal; lista e resumo lateral funcionam em duas colunas.
- `1920`: sem overflow horizontal; composição permanece centralizada e contida.

### Validação funcional

- Views testadas: `Dia`, `Semana`, `Mês` e `Completa`.
- Filtros testados: `Todos`, `Atrasados` e `TaxiDog`.
- `MAIS` expôs: `services`, `checklist`, `checkinout`, `logistics`, `reschedule`, `history`.
- Modal de cancelamento exibiu `Cancelar`, `Reagendar` e `Fechar`.
- Modal de atendimento cancelado exibiu `Reabrir atendimento`, `Reagendar` e `Fechar`.
- Modal de logística abriu confirmação de TaxiDog com `Cancelar` e `Confirmar`, sem confirmar a alteração.
- Modal de reagendamento exibiu campos de data/hora e botão `Salvar`, sem salvar alteração.
- Histórico retornou 8 itens e manteve campos auditáveis.
- Validação simulada de `Finalizado` confirmou abertura do Checklist Operacional sem gravar status real.

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backup remoto final: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-integral-logs-20260424-223728`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `php -l` remoto: sem erros de sintaxe nos arquivos PHP publicados.
- Hashes SHA-256 remotos conferidos contra os arquivos locais.

## Agenda: hub completo do botao MAIS e sidebar operacional

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Validar a Agenda no runtime publicado autenticado e transformar o botao `MAIS` no ponto unico das interacoes extras do atendimento, sem poluir o card operacional.

### Analise visual e funcional no navegador

- A sessao autenticada temporaria foi criada por WP-CLI/SSH, usada somente para a auditoria visual e removida imediatamente depois.
- Antes da alteracao, o `MAIS` exibia apenas `Servicos`, `Reagendar` e `Historico`.
- A sidebar repetia dados basicos, mas nao indicava claramente que as interacoes extras ficavam agrupadas em `MAIS`.
- O card operacional publicado ja estava limpo, com acao primaria + `MAIS`, alinhado a direcao de densidade operacional do DPS Signature.

### Comportamento implementado

- `MAIS` agora abre um hub com cinco interacoes extras:
  - `Servicos e observacoes`
  - `Operacao`
  - `Logistica e TaxiDog`
  - `Reagendar`
  - `Historico e logs`
- `Operacao` abre o painel existente de checklist, check-in e check-out.
- `Logistica e TaxiDog` abre modal proprio com status, endereco, mapa/rota quando disponiveis e proximas acoes de TaxiDog.
- A sidebar foi reorganizada em `Resumo`, `Operacao`, `Acoes extras`, `Observacoes` e `Ultimos logs`.
- O modal de logistica diferencia corretamente `Sem endereco cadastrado` de `TaxiDog solicitado`.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Capturas publicadas

- [agenda-more-actions-live-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-375.png)
- [agenda-more-actions-live-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-600.png)
- [agenda-more-actions-live-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-840.png)
- [agenda-more-actions-live-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-1200.png)
- [agenda-more-actions-live-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-1920.png)
- [agenda-more-actions-live-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-live-check.json)

### Breakpoints validados no runtime publicado

- `375`: sem overflow horizontal; sidebar oculta por regra responsiva; `MAIS` em coluna.
- `600`: sem overflow horizontal; modal usa largura disponivel sem cortar texto.
- `840`: sem overflow horizontal; modal com largura controlada.
- `1200`: sem overflow horizontal; sidebar visivel com resumo, operacao e acoes extras.
- `1920`: sem overflow horizontal; modal centralizado e sidebar preservada.

### Validacao automatica

- `actions = ["services", "operation", "logistics", "reschedule", "history"]`
- `moreMenuIncludesAllExtraActions = true`
- `operationActionOpensChecklistCheckinCheckout = true`
- `sidebarIncludesResumoOperacaoAndAcoesExtras = true`
- `logisticsDialogShowsMissingAddressExplicitly = true`
- `temporaryLoginEndpointRemoved = true`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-191111`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-191434`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- `php -l` remoto: sem erros de sintaxe nos arquivos PHP publicados.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`.

## Agenda: separacao de Checklist Operacional e Check-in / Check-out

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Separar `Checklist Operacional` de `Check-in / Check-out` nas interacoes do atendimento e impedir que o checklist seja esquecido ao finalizar o servico.

### Comportamento implementado

- O botao `MAIS` agora separa:
  - `Checklist Operacional`
  - `Check-in / Check-out`
- A sidebar tambem separa os dois blocos:
  - `Checklist Operacional`: progresso e retrabalhos.
  - `Check-in / Check-out`: horarios/status de entrada e saida.
- Ao selecionar `Finalizado` ou `Finalizado e pago`, o sistema atualiza o status e abre automaticamente o modal operacional com foco no checklist.
- O endpoint/modal operacional existente foi preservado, mantendo checklist, check-in e check-out editaveis e auditaveis.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`

### Capturas publicadas

- [agenda-more-actions-separated-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-375.png)
- [agenda-more-actions-separated-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-600.png)
- [agenda-more-actions-separated-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-840.png)
- [agenda-more-actions-separated-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-1200.png)
- [agenda-more-actions-separated-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-1920.png)
- [agenda-finalize-checklist-auto-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-finalize-checklist-auto-1200.png)
- [agenda-more-actions-separated-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-more-actions-separated-check.json)

### Breakpoints validados no runtime publicado

- `375`: sem overflow horizontal; acoes do `MAIS` empilham corretamente.
- `600`: sem overflow horizontal; texto de `Check-in / Check-out` permanece dentro do card.
- `840`: sem overflow horizontal; modal com largura controlada.
- `1200`: sem overflow horizontal; sidebar visivel com os blocos separados.
- `1920`: sem overflow horizontal; modal centralizado e leitura preservada.

### Validacao automatica

- `actions = ["services", "checklist", "checkinout", "logistics", "reschedule", "history"]`
- `moreMenuSeparatesChecklistFromCheckinCheckout = true`
- `sidebarSeparatesChecklistFromCheckinCheckout = true`
- `checklistActionOpensOperationalChecklist = true`
- `checkinoutActionOpensCheckinCheckoutArea = true`
- `finalizadoPagoOpensChecklistAutomatically = true`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backup remoto:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/backups/codex-20260424-192447`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `php -l` remoto: sem erros de sintaxe no renderer publicado.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`.

### Observações

- A validação visual foi feita em fixture local usando os estilos reais do add-on. A rodada de publicação confirmou arquivos ativos, sintaxe PHP remota, página publicada respondendo e plugin ativo via WP-CLI.
- A busca local no add-on da Agenda não encontrou referências ativas ao padrão visual antigo.

## Agenda: botao multifuncao do fluxo operacional

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Adicionar um botao de fluxo antes da acao financeira, mudando a acao visivel conforme a etapa do atendimento:

- `Confirmar` quando o atendimento ainda nao esta confirmado.
- `Finalizar` quando o atendimento esta confirmado e ainda nao saiu da fila operacional.
- `Finalizado` ao lado de `Cobrar cliente` quando o servico foi finalizado e ainda existe cobranca.
- `Pago` quando o atendimento foi finalizado e pago, evitando nova chamada de cobranca como acao principal.
- `Cancelado` quando o atendimento foi cancelado, abrindo as opcoes `Reabrir atendimento` e `Reagendar`.

### Comportamento implementado

- `Confirmar` abre modal com `Confirmar` e `Nao confirmado`.
- `Nao confirmado` atualiza a confirmacao e pergunta se o atendimento deve ser reagendado.
- `Finalizar` abre modal com `Finalizado`, `Finalizado e pago` e `Cancelado`.
- `Cancelado` abre nova decisao entre `Cancelar` e `Reagendar`.
- `Reagendar` aciona o fluxo existente de reagendamento rapido.
- Quando o atendimento ja esta `Cancelado`, o botao abre decisao entre `Reabrir atendimento` e `Reagendar`.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

### Capturas: estados do botao

- [agenda-workflow-action-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-375.png)
- [agenda-workflow-action-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-600.png)
- [agenda-workflow-action-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-840.png)
- [agenda-workflow-action-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-1200.png)
- [agenda-workflow-action-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-1920.png)
- [agenda-workflow-action-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-check.json)
- [agenda-workflow-action-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-action-preview.html)

### Capturas: modal de finalizacao

- [agenda-workflow-modal-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-375.png)
- [agenda-workflow-modal-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-600.png)
- [agenda-workflow-modal-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-840.png)
- [agenda-workflow-modal-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-1200.png)
- [agenda-workflow-modal-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-1920.png)
- [agenda-workflow-modal-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-check.json)
- [agenda-workflow-modal-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-modal-preview.html)

### Capturas: modal de atendimento cancelado

- [agenda-workflow-cancelled-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-375.png)
- [agenda-workflow-cancelled-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-600.png)
- [agenda-workflow-cancelled-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-840.png)
- [agenda-workflow-cancelled-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-1200.png)
- [agenda-workflow-cancelled-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-1920.png)
- [agenda-workflow-cancelled-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-check.json)
- [agenda-workflow-cancelled-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-workflow-cancelled-preview.html)

### Breakpoints validados

- `375`: sem overflow horizontal; estados do botao empilham sem corte; modal com acoes em coluna.
- `600`: sem overflow horizontal; botoes mantem area de toque; modal com acoes em coluna.
- `840`: sem overflow horizontal; modal passa para tres colunas sem sobrepor texto.
- `1200`: sem overflow horizontal; card e inspetor lateral mantem hierarquia operacional.
- `1920`: sem overflow horizontal; acoes continuam compactas e alinhadas ao padrao da Agenda.

### Validacao automatica

- `workflowButtonCount = 5`
- `paymentButtonCount = 1`
- `operationButtonCount = 0`
- `cancelledButtonCount = 1`
- `actionText = [["Confirmar","Mais"],["Finalizar","Mais"],["Finalizado","Cobrar cliente","Mais"],["Pago","Mais"],["Cancelado","Mais"]]`
- `modalActions = ["Finalizado","Finalizado e pago","Cancelado"]`
- `cancelledModalActions = ["Reabrir atendimento","Reagendar"]`
- `consoleMessages = []`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260424-182122`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php.__backup_20260424-182122`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260424-182122`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260424-182122`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `php -l` remoto: sem erros de sintaxe.
- Hashes SHA-256 remotos conferidos contra os arquivos locais.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`.
- WP-CLI remoto confirmou o add-on `desi-pet-shower-agenda` ativo.

## Agenda: evidências visuais do pet

Fonte de verdade visual seguida nesta implementação: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrão DPS Signature).

### Objetivo

Criar um fluxo operacional para anexar fotos ou vídeos de situações identificadas no pet, como feridas, alergias, pulgas, carrapatos e outros sinais observados no atendimento, preservando contexto por etapa (`Check-in` ou `Check-out`) e por item de segurança.

### Comportamento implementado

- Nova área `Evidências do pet` dentro de cada item de segurança do Check-in / Check-out.
- Upload de imagem/vídeo com descrição operacional curta antes do envio.
- Tipos aceitos: JPG, PNG, WEBP, HEIC, MP4, MOV e WEBM.
- Upload via Biblioteca de Mídia do WordPress, anexado ao post do agendamento.
- Remoção não destrutiva na UI: a evidência sai da lista ativa do atendimento, mas o anexo permanece preservado.
- Cada upload e remoção entra em `_dps_appointment_history` e no log técnico via `DPS_Logger`, com campo alterado, valor anterior e novo valor.
- O resumo do modal operacional mostra a contagem de evidências ativas quando houver registros.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
- `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`

### Capturas

- [agenda-pet-evidence-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-375.png)
- [agenda-pet-evidence-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-600.png)
- [agenda-pet-evidence-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-840.png)
- [agenda-pet-evidence-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-1200.png)
- [agenda-pet-evidence-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-1920.png)
- [agenda-pet-evidence-upload-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-upload-1200.png)
- [agenda-pet-evidence-history-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-history-1200.png)
- [agenda-pet-evidence-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-pet-evidence-check.json)

### Breakpoints validados

- `375`, `600`, `840`, `1200`, `1920`: sem overflow horizontal.
- `375`, `600`, `840`, `1200`, `1920`: `14` painéis de evidência renderizados no modal de Check-in, `14` botões de upload disponíveis, `0` botões com texto estourando.

### Validação funcional

- Upload de evidência em `Check-in > Pulgas`: sucesso, 1 card ativo exibido.
- Remoção de evidência pelo modal de confirmação: sucesso, 0 cards ativos após a remoção.
- Histórico do atendimento: eventos `Evidência do pet adicionada` e `Evidência do pet removida` exibidos com ação manual, campo alterado, valor anterior e novo valor.
- WP-CLI remoto confirmou `_dps_pet_evidence` com 1 registro preservado e 0 registros ativos após a remoção.
- Console do navegador: apenas erros CORS externos de Google Ads/Mixpanel já existentes no runtime; nenhuma falha funcional da Agenda associada ao fluxo de evidências.

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backup remoto: `/home/u944637195/backups/codex-20260424-pet-evidence-20260424-235934`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/checklist-checkin.js`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
- `php -l` remoto: sem erros de sintaxe.

## Agenda: historico auditavel de status e interacoes

Fonte de verdade visual seguida nesta correcao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

### Objetivo

Garantir que mudancas de status e interacoes persistentes do atendimento entrem no log tecnico e na linha do tempo do atendimento, mantendo visiveis `data`, `usuario`, `campo alterado`, `valor anterior`, `novo valor` e a separacao entre acao manual e registro automatico.

### Comportamento coberto

- Alteracao de status via `dps_update_status`.
- Acoes rapidas de status via `dps_agenda_quick_action`.
- Alteracao de confirmacao via `dps_agenda_update_confirmation`.
- Atualizacao e solicitacao de TaxiDog.
- Reenvio de cobranca.
- Interacoes operacionais ja existentes: reagendamento, checklist, retrabalho, check-in e check-out.
- Todo evento salvo em `_dps_appointment_history` tambem gera log tecnico em `DPS_Logger` no canal `agenda_history`, mantendo um espelho central para auditoria.

### Arquivos alterados

- `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
- `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`

### Capturas

- [agenda-history-audit-375.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-375.png)
- [agenda-history-audit-600.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-600.png)
- [agenda-history-audit-840.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-840.png)
- [agenda-history-audit-1200.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-1200.png)
- [agenda-history-audit-1920.png](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-1920.png)
- [agenda-history-audit-check.json](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-check.json)
- [agenda-history-audit-preview.html](/C:/Users/casaprobst/DPS/docs/screenshots/2026-04-24/agenda-history-audit-preview.html)

### Breakpoints validados

- `375`, `600`, `840`, `1200`, `1920`: sem overflow horizontal; os campos auditaveis permanecem legiveis.

### Validacao automatica

- `itemCount = 3`
- `manualSourceCount = 2`
- `systemSourceCount = 1`
- `hasFieldLabel = true`
- `hasOldValueLabel = true`
- `hasNewValueLabel = true`
- `consoleMessages = []`

### Publicado no servidor

- Servidor: `195.35.41.35:65002`
- Caminho publicado: `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/`
- Backups remotos:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php.__backup_20260424-114522`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php.__backup_20260424-114522`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js.__backup_20260424-114522`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css.__backup_20260424-114522`
- Arquivos publicados:
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`
  - `/home/u944637195/domains/desi.pet/public_html/wp-content/plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`
- Hashes SHA-256 remotos conferidos contra os arquivos locais.
- `php -l` remoto: sem erros de sintaxe nos arquivos PHP publicados.
- `https://desi.pet/agenda-de-atendimentos/`: HTTP `200 OK`.
- WP-CLI remoto confirmou o add-on `desi-pet-shower-agenda` ativo.
