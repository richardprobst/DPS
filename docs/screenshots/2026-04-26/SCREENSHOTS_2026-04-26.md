# Screenshots 2026-04-26

# Agenda - alinhamento do card operacional

## Contexto

- Objetivo: alinhar os botoes do card operacional, remover o nome do proprietario da lista de atendimentos e posicionar o horario antes do nome do pet no mesmo grupo visual.
- Ambiente: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a Agenda publicada; usuario removido ao final da rodada.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: o horario aparecia isolado acima do pet, o nome do tutor era repetido na lista e o botao `Mais` podia quebrar para uma linha separada em tela grande.
- Depois: o horario foi colocado antes do pet no cabecalho compacto do card, o tutor ficou restrito ao inspetor lateral e os botoes operacionais ficam alinhados na mesma linha em `1200px` e `1920px`.
- Arquivos de codigo alterados: `plugins/desi-pet-shower-agenda/includes/trait-dps-agenda-renderer.php`, `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`.

## Capturas

- `./agenda-card-alignment-375.png` - Agenda publicada em 375px.
- `./agenda-card-alignment-600.png` - Agenda publicada em 600px.
- `./agenda-card-alignment-840.png` - Agenda publicada em 840px.
- `./agenda-card-alignment-1200.png` - Agenda publicada em 1200px.
- `./agenda-card-alignment-1920.png` - Agenda publicada em 1920px.

## Evidencia funcional

- `./agenda-card-alignment-check.json`

Resumo:
- card operacional encontrado nos cinco breakpoints;
- `23:30` e `QA Smoke Pet` ficaram no mesmo grupo visual;
- `ownerTextInCard` ficou `false` em todos os breakpoints;
- em `1200px` e `1920px`, os quatro botoes ficaram em uma unica linha;
- nao houve overflow horizontal em `375`, `600`, `840`, `1200` e `1920`;
- o proprietario permaneceu visivel no inspetor lateral em telas grandes.

## Observacoes

- O console do runtime publicado exibiu erros externos de anuncios/Mixpanel, tratados como ruido fora do escopo do add-on Agenda.

---

# Agenda - destaque do horario no card operacional

## Contexto

- Objetivo: aumentar e destacar levemente o horario do atendimento para alinhar com a caixa do nome do pet.
- Ambiente: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a Agenda publicada; usuario removido ao final da rodada.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: o horario estava em linha com o pet, mas tinha menos presenca visual e nao ocupava a mesma altura da caixa do pet.
- Depois: o horario recebeu fonte `20px`, peso `900`, altura de `44px`, borda seca e acento lateral petrol, com alinhamento exato ao topo, centro e altura da caixa do pet.
- Arquivo de codigo alterado: `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`.

## Capturas

- `./agenda-time-emphasis-375.png` - Agenda publicada em 375px.
- `./agenda-time-emphasis-600.png` - Agenda publicada em 600px.
- `./agenda-time-emphasis-840.png` - Agenda publicada em 840px.
- `./agenda-time-emphasis-1200.png` - Agenda publicada em 1200px.
- `./agenda-time-emphasis-1920.png` - Agenda publicada em 1920px.

## Evidencia funcional

- `./agenda-time-emphasis-check.json`

Resumo:
- `timeAndPetHeightDelta`, `timeAndPetTopDelta` e `timeAndPetCenterDelta` ficaram `0` nos cinco breakpoints;
- `ownerTextInCard` permaneceu `false`;
- os botoes continuam em uma unica linha em `1200px` e `1920px`;
- nao houve overflow horizontal em `375`, `600`, `840`, `1200` e `1920`.

## Observacoes

- O console do runtime publicado exibiu apenas erros externos de anuncios/Mixpanel, tratados como ruido fora do escopo do add-on Agenda.

---

# Agenda - distribuicao mobile dos botoes operacionais

## Contexto

- Objetivo: distribuir melhor os botoes do card operacional em tela pequena, reduzindo a altura exagerada e evitando quebra artificial dos rotulos.
- Ambiente: `https://desi.pet/agenda-de-atendimentos/?dps_date=2026-04-24&view=week`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a Agenda publicada; usuario removido ao final da rodada.
- Referencia de design: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: os botoes em tela pequena ficavam altos demais, com `Editar check-in/out` quebrando em varias linhas e gerando uma grade visualmente pesada.
- Depois: a area usa grade 2x2 compacta no mobile, botoes com altura consistente, texto em uma linha, menor espacamento de letras e sem overflow.
- Arquivo de codigo alterado: `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`.

## Capturas

- `./agenda-mobile-actions-375.png` - Agenda publicada em 375px.
- `./agenda-mobile-actions-600.png` - Agenda publicada em 600px.
- `./agenda-mobile-actions-840.png` - Agenda publicada em 840px.
- `./agenda-mobile-actions-1200.png` - Agenda publicada em 1200px.
- `./agenda-mobile-actions-1920.png` - Agenda publicada em 1920px.

## Evidencia funcional

- `./agenda-mobile-actions-check.json`

Resumo:
- em `375px` e `600px`, os botoes ficaram em 2 linhas com altura uniforme de `62px`;
- nenhum botao apresentou overflow horizontal ou vertical;
- em `1200px` e `1920px`, os botoes permaneceram em uma unica linha;
- `ownerTextInCard` permaneceu `false`;
- nao houve overflow horizontal em `375`, `600`, `840`, `1200` e `1920`.

## Observacoes

- O console do runtime publicado exibiu apenas erros externos de anuncios/Mixpanel, tratados como ruido fora do escopo do add-on Agenda.
