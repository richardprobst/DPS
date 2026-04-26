# Ajuste da lateral direita do Agendamento - 2026-04-26

## Escopo

- Superficie: pagina publicada `https://desi.pet/agendamento/`.
- Objetivo: revisar margens internas, alinhamentos horizontais/verticais e usabilidade da barra lateral direita.
- Fonte de verdade: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Breakpoints validados: `375`, `600`, `840`, `1200`, `1920`.

## Ajuste aplicado

- O renderer canonico de agendamentos recebeu a opcao visual `side_rail`, acionada apenas pelo contexto Booking.
- Atribuicao, observacoes, resumo, erros e acao final passaram a ficar agrupados em `.dps-form-side-rail`.
- Em `1440px+`, o trilho lateral fica alinhado ao topo do primeiro bloco, com `gap` constante e comportamento `sticky`.
- A rolagem interna da lateral foi removida; o trilho usa altura natural compacta para permanecer inteiro no viewport de tela grande.
- O resumo dinamico voltou a respeitar `hidden`, evitando que a lista vazia e o item de observacoes inflassem a lateral antes de haver dados reais.
- Os badges numericos da lateral foram fixados como `05` para Atribuicao e `06` para Observacoes no fluxo normal, preservando `06/07` quando o pagamento passado estiver visivel.
- Em telas menores, a lateral volta para fluxo vertical natural depois de servicos, preservando ordem de uso e sem alterar campos, hooks, nonces ou nomes de POST.

## Evidencias

- `docs/screenshots/2026-04-26/booking-side-rail-375.png`
- `docs/screenshots/2026-04-26/booking-side-rail-600.png`
- `docs/screenshots/2026-04-26/booking-side-rail-840.png`
- `docs/screenshots/2026-04-26/booking-side-rail-1200.png`
- `docs/screenshots/2026-04-26/booking-side-rail-1920.png`
- `docs/screenshots/2026-04-26/booking-side-rail-1920-summary-filled.png`
- `docs/screenshots/2026-04-26/booking-side-rail-check.json`

## Resultado da verificacao publicada

- CSS publicado: `booking-addon.css?ver=1.4.12`.
- Notices visiveis: `0` em todos os breakpoints.
- Console relevante: `0` em todos os breakpoints.
- Requests falhos relevantes: `0` em todos os breakpoints.
- Overflow horizontal real: `false` em todos os breakpoints.
- Padding minimo em paineis: `14px` ate `1200px`; `10px` na acao final da lateral compacta em `1920px`.
- Alinhamento horizontal dos blocos principais: spread `0px` nos cinco breakpoints.
- Alinhamento horizontal dos blocos da lateral: spread `0px` nos cinco breakpoints.
- Em `1920px`: grid `918px 420px`, lateral com `sideRailTopDelta=0`, gap horizontal `24px`, `height=579`, `bottom=1048`, `overflowY=visible`, `maxHeight=none` e `hasInternalScrollbar=false`.
- Simulacao do resumo preenchido em `1920px`: `height=700`, `bottom=1168`, `sideRailFitsViewport=true` e `hasInternalScrollbar=false`.
- Resumo vazio: `.dps-appointment-summary__list[hidden]` ficou com `display=none` e `.dps-appointment-summary__notes` com `display=none` nos cinco breakpoints.
- Badges da lateral: `05,06` nos cinco breakpoints.

## Sugestoes remanescentes

- Nao ha bloqueio visual restante no Booking apos esta rodada.
- Melhoria futura opcional: criar um teste automatizado dedicado para preencher cliente, pet e servico e validar o resumo com dados reais, complementando a prova visual estrutural.
