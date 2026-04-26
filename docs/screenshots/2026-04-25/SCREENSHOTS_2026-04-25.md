# Screenshots 2026-04-25

## Contexto

- Objetivo: registrar baseline visual e funcional do formulario de Cadastro antes da reescrita integral proposta para DPS Signature.
- Ambiente: `https://desi.pet/cadastro/`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a variante autenticada/admin; usuario removido ao final.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: formulario atual ainda usa CSS legado, radius/elevacao fora do gate DPS Signature, callback proprio de Google Maps e `textarea` para endereco.
- Depois: nao houve alteracao visual implementada nesta rodada. Este registro documenta o estado auditado e os artefatos para orientar a reescrita.
- Arquivos de codigo alterados: nenhum arquivo de codigo foi alterado nesta rodada.

## Capturas

- `./cadastro-audit-admin-375.png` - Cadastro autenticado/admin em 375px.
- `./cadastro-audit-admin-600.png` - Cadastro autenticado/admin em 600px.
- `./cadastro-audit-admin-840.png` - Cadastro autenticado/admin em 840px.
- `./cadastro-audit-admin-1200.png` - Cadastro autenticado/admin em 1200px.
- `./cadastro-audit-admin-1920.png` - Cadastro autenticado/admin em 1920px.

## Evidencia funcional

- `./cadastro-audit-runtime-check.json`

Resumo:
- formulario renderizou nos cinco breakpoints;
- sem overflow horizontal detectado na coleta;
- opcoes administrativas apareceram na sessao autenticada;
- `duplicateCheck` estava ativo para admin;
- validacao em branco bloqueou o avanco da etapa 1;
- fluxo preenchido avancou ate a etapa 3, gerou resumo e habilitou submit apenas apos confirmacao;
- Google Places apresentou erro publicado porque o campo `#dps-client-address` e `TEXTAREA`, enquanto o autocomplete legado espera `HTMLInputElement`.

## Observacoes

- As capturas sao baseline de auditoria, nao evidencia de correcao visual.
- O console tambem apresentou erro de recurso externo de anuncios, tratado como ruido fora do escopo do Cadastro Add-on.
- O erro de Google Places e do escopo do Cadastro e deve ser corrigido na reescrita.

---

# Agenda - cor semantica da etapa no inspetor

## Contexto

- Objetivo: ajustar a area de ETAPA da Agenda em tela grande, aplicando cor semantica conforme o estado do atendimento e removendo o selo textual duplicado de etapa da lista.
- Ambiente: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a Agenda publicada; usuario removido ao final da rodada.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: a lista exibia um selo textual de etapa alem dos botoes de acao, e a secao ETAPA do inspetor lateral nao mudava o tom visual do valor.
- Depois: a lista preserva os botoes operacionais sem o selo duplicado, e a secao ETAPA do inspetor recebeu borda, fundo e texto semanticamente coloridos conforme `confirm`, `ready`, `service`, `done` ou `danger`.
- Arquivos de codigo alterados: `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`, `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`, `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`, `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`.

## Capturas

- `./agenda-stage-color-375.png` - Agenda publicada em 375px.
- `./agenda-stage-color-600.png` - Agenda publicada em 600px.
- `./agenda-stage-color-840.png` - Agenda publicada em 840px.
- `./agenda-stage-color-1200.png` - Agenda publicada em 1200px.
- `./agenda-stage-color-1920.png` - Agenda publicada em 1920px.

## Evidencia funcional

- `./agenda-stage-color-check.json`

Resumo:
- `visibleStageBadgeCount` ficou `0` nos cinco breakpoints;
- a secao ETAPA recebeu classe de tom do estado selecionado;
- texto e borda da etapa ficaram com a cor semantica esperada;
- nao houve overflow horizontal em `375`, `600`, `840`, `1200` e `1920`.

## Observacoes

- O console do runtime publicado exibiu erros externos de anuncios/Mixpanel, tratados como ruido fora do escopo do add-on Agenda.

---

# Agenda - links acionaveis no resumo lateral

## Contexto

- Objetivo: tornar os valores de `Servicos`, `Financeiro` e `Logistica` do resumo lateral clicaveis, abrindo seus respectivos modais sem aumentar a altura visual do bloco.
- Ambiente: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a Agenda publicada; usuario removido ao final da rodada.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: os valores do resumo lateral eram texto estatico.
- Depois: os valores viraram botoes com aparencia compacta de texto, abrindo os modais de Servicos, Cobranca/Financeiro e Logistica/TaxiDog.
- Ajuste posterior: o reset visual removeu altura minima, padding e transformacao herdada de `button`, mantendo o bloco com densidade similar ao layout anterior.
- Arquivos de codigo alterados: `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`, `plugins/desi-pet-shower-agenda/assets/js/agenda-addon.js`, `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`.

## Capturas

- `./agenda-summary-clicks-375.png` - Agenda publicada em 375px.
- `./agenda-summary-clicks-600.png` - Agenda publicada em 600px.
- `./agenda-summary-clicks-840.png` - Agenda publicada em 840px.
- `./agenda-summary-clicks-1200.png` - Agenda publicada em 1200px.
- `./agenda-summary-clicks-1920.png` - Agenda publicada em 1920px.

## Evidencia funcional

- `./agenda-summary-clicks-check.json`

Resumo:
- os tres valores existem como `button` com `data-inspector-action`;
- em 1200px e 1920px os valores visiveis mantiveram `height` de texto e `min-height: 0`;
- os cliques abriram os modais `Servicos do atendimento`, `Cobranca do atendimento` e `Logistica e TaxiDog`;
- nao houve overflow horizontal nos breakpoints `375`, `600`, `840`, `1200` e `1920`.
