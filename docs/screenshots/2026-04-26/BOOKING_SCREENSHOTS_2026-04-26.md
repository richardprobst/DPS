# Booking screenshots - 2026-04-26

## Contexto

- Objetivo: registrar a implementacao das correcoes sugeridas pela auditoria visual do Agendamento.
- Ambiente: `https://desi.pet/agendamento/`, WordPress publicado.
- Sessao: usuario temporario autenticado via WP-CLI para validar a pagina publicada.
- Fonte de verdade: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: notices tecnicos apareciam no corpo da pagina, o painel `Atribuicao` usava roxo fora da paleta, a tela de `1920px` subutilizava largura, o wrapper do CTA tinha raio alto e os chips de preco geravam ruido cromatico.
- Depois: runtime sem notices, `Atribuicao` em paleta DPS Signature, CTA reto, chips neutros e layout de tela grande com coluna lateral operacional.
- Arquivos alterados: `plugins/desi-pet-shower-booking/desi-pet-shower-booking-addon.php` e `plugins/desi-pet-shower-booking/assets/css/booking-addon.css`.

## Capturas

- `./booking-visual-fixes-375.png` - Agendamento publicado em 375px.
- `./booking-visual-fixes-600.png` - Agendamento publicado em 600px.
- `./booking-visual-fixes-840.png` - Agendamento publicado em 840px.
- `./booking-visual-fixes-1200.png` - Agendamento publicado em 1200px.
- `./booking-visual-fixes-1920.png` - Agendamento publicado em 1920px.
- `./booking-side-rail-375.png` - Lateral direita e margens em 375px.
- `./booking-side-rail-600.png` - Lateral direita e margens em 600px.
- `./booking-side-rail-840.png` - Lateral direita e margens em 840px.
- `./booking-side-rail-1200.png` - Lateral direita e margens em 1200px.
- `./booking-side-rail-1920.png` - Lateral direita completa, sem rolagem interna, em 1920px.
- `./booking-side-rail-1920-summary-filled.png` - Lateral direita em 1920px com resumo preenchido simulado, ainda sem rolagem interna.

## Evidencia automatizada

- `./booking-visual-fixes-check.json`
- `./booking-side-rail-check.json`

Resumo:
- `cssVersion` confirmou `booking-addon.css?ver=1.4.12`;
- `notices`, `consoleCount` e `failedCount` ficaram `0` nos cinco breakpoints;
- nao houve overflow horizontal real em `375`, `600`, `840`, `1200` e `1920`;
- `1920px` passou a usar wrapper de `1520px` e grid `918px 420px`;
- painel `Atribuicao`, CTA e chips de preco ficaram dentro da paleta/geometria DPS Signature.
- a lateral direita final usa `.dps-form-side-rail`, sem rolagem interna, com `sideRailTopDelta=0`, gap horizontal de `24px`, `height=579` e `bottom=1048` em `1920px`;
- a simulacao de resumo preenchido em `1920px` ficou com `height=700`, `bottom=1168`, `sideRailFitsViewport=true` e `hasInternalScrollbar=false`;
- o resumo vazio respeitou `hidden`, com a lista e observacoes fora do layout ate haver dados reais;
- os badges da lateral ficaram em `05,06` nos cinco breakpoints.
