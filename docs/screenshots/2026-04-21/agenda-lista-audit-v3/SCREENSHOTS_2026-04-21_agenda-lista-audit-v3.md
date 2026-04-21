# Agenda Lista Audit V3 — 2026-04-21

## Contexto

Rodada de revisão visual e funcional da área `Lista de Atendimentos` no site publicado `https://desi.pet`, seguindo `docs/visual/` como fonte de verdade do padrão visual do sistema e usando critérios de clareza operacional, responsividade e consistência visual.

Escopo validado:

- aba `Visão rápida`
- aba `Operação`
- aba `Detalhes`
- comportamento expandido da operação
- regra de visibilidade do checklist por status
- breakpoints `1280px` e `375px`

## Problemas atacados

- células de `Operação` e `Detalhes` com vazio vertical excessivo por alinhamento inadequado
- labels e subtítulos sem quebra adequada, causando clipping e leitura ruim
- botões e ações com largura apertada em tabelas fixas
- cabeçalhos internos e cartões contextuais com densidade inconsistente
- seletor de status cortando o texto `Finalizado` no desktop
- necessidade de validar que o checklist só aparece em `finalizado` e `finalizado_pago`

## Mudanças aplicadas

Arquivo principal alterado:

- `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

Ajustes desta rodada:

- alinhamento vertical das células das abas `Operação` e `Detalhes` fixado no topo
- reforço de `white-space: normal`, `overflow-wrap: break-word` e `min-width: 0` em blocos críticos
- redistribuição de colunas para reduzir clipping em `Operação` e `Detalhes`
- redução controlada de densidade e tipografia em áreas apertadas
- ajuste fino final no seletor de status da aba `Operação` para eliminar o corte de `Finalizado`

## Validação real em produção

Checklist validado com usuário temporário admin criado via WP-CLI e removido ao final.

Resultado da regra do checklist em produção:

- `pendente`: `checklistCount = 0`, `checklistVisible = false`
- `finalizado`: `checklistCount = 1`, `checklistVisible = true`

Resultado visual/estrutural após o deploy:

- `overflowCount = 0` em todas as capturas auditadas
- `topGap` desktop das abas `Operação` e `Detalhes` caiu de valores altos e inconsistentes para faixa alinhada (`17–24px`)
- mobile sem truncamento visível nos subtítulos e sem overflow horizontal na superfície auditada

## Artefatos

Capturas principais:

- `tab1-visao-rapida-1280.png`
- `tab2-operacao-1280.png`
- `tab2-operacao-expandido-1280.png`
- `tab3-detalhes-1280.png`
- `tab2-operacao-finalizado-1280-v2.png`
- `tab1-visao-rapida-375.png`
- `tab2-operacao-375.png`
- `tab2-operacao-expandido-375.png`
- `tab3-detalhes-375.png`
- `checklist-pendente-1280.png`
- `checklist-finalizado-1280.png`

Relatórios:

- `layout-report-v2.json`
- `checklist-rule.json`

## Leitura objetiva do estado final

- `Visão rápida`: limpa, sem estouro e com leitura rápida correta
- `Operação`: hierarquia operacional consistente; resumo, status, pagamento e ação sem vazios centrais artificiais
- `Operação expandida`: checklist ausente em `pendente` e presente em `finalizado`; painel de check-in permanece disponível
- `Detalhes`: colunas contextuais equilibradas, sem clipping nos cartões internos

## Observações

- Persistem elementos do tema fora do escopo do add-on, como cabeçalho/logo mobile acima da superfície da Agenda. Eles não pertencem ao CSS do plugin auditado.
- O QA desta rodada foi executado integralmente no ambiente publicado.
