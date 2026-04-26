# Implementacao visual do Agendamento - 2026-04-26

## Escopo

- Superficie: pagina publicada `https://desi.pet/agendamento/`.
- Add-on: `plugins/desi-pet-shower-booking`.
- Fonte de verdade: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Breakpoints validados: `375`, `600`, `840`, `1200`, `1920`.
- Sessao: usuario temporario autenticado via WP-CLI para validar o runtime publicado.

## Ajustes implementados

- Removida a origem dos notices `WP_Scripts::add` ao parar de desregistrar handles externos que ainda eram dependencias de terceiros.
- Ampliada a filtragem apenas de markup externo conhecido de Elementor App Loader quando impresso fora do enqueue normal.
- Recolorido o painel `Atribuicao` para `bone/line/sky`, eliminando o roxo fora da paleta DPS Signature.
- Reorganizado o formulario em tela grande a partir de `1440px`, com coluna operacional principal e coluna lateral para atribuicao, observacoes, CTA e resumo.
- Reduzido o container final do CTA para geometria reta (`0px`) e linguagem visual sobria.
- Neutralizados os chips de preco para uma familia unica `white/line/petrol`, removendo ruido cromatico repetitivo.

## Evidencias visuais

- `docs/screenshots/2026-04-26/booking-visual-fixes-375.png`
- `docs/screenshots/2026-04-26/booking-visual-fixes-600.png`
- `docs/screenshots/2026-04-26/booking-visual-fixes-840.png`
- `docs/screenshots/2026-04-26/booking-visual-fixes-1200.png`
- `docs/screenshots/2026-04-26/booking-visual-fixes-1920.png`
- `docs/screenshots/2026-04-26/booking-visual-fixes-check.json`

## Resultado da verificacao publicada

- CSS publicado: `booking-addon.css?ver=1.4.6`.
- Notices visiveis: `0` em todos os breakpoints.
- Console relevante: `0` em todos os breakpoints.
- Requests falhos relevantes: `0` em todos os breakpoints.
- Overflow horizontal real: `false` em todos os breakpoints.
- Painel `Atribuicao`: `rgb(236, 226, 211)` com acento `rgb(189, 216, 231)`.
- CTA final: `border-radius: 0px`.
- Chips de preco: 1 variante visual em todos os breakpoints.
- Tela grande `1920px`: wrapper com `1520px` (`79.2%` da viewport), grid `918px 420px`, sem lacunas verticais anormais entre data, TaxiDog e servicos.

## Observacoes

- Amostras de elementos off-canvas em `1200px` e `1920px` vieram do dropdown do tema posicionado com `left` negativo extremo; `scrollWidth` permaneceu igual a `clientWidth`, portanto nao houve overflow horizontal real do Booking.
- A validacao inicial usou o parametro `w=` e isso acionou a query var nativa de semana do WordPress. A rodada final usou `bp=` para nao contaminar a pagina com 404 ou notices de teste.
