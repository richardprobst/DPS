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

## Evidencia automatizada

- `./booking-visual-fixes-check.json`

Resumo:
- `cssVersion` confirmou `booking-addon.css?ver=1.4.6`;
- `notices`, `consoleCount` e `failedCount` ficaram `0` nos cinco breakpoints;
- nao houve overflow horizontal real em `375`, `600`, `840`, `1200` e `1920`;
- `1920px` passou a usar wrapper de `1520px` e grid `918px 420px`;
- painel `Atribuicao`, CTA e chips de preco ficaram dentro da paleta/geometria DPS Signature.
