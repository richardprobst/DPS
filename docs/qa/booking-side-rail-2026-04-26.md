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
- Em telas menores, a lateral volta para fluxo vertical natural depois de servicos, preservando ordem de uso e sem alterar campos, hooks, nonces ou nomes de POST.

## Evidencias

- `docs/screenshots/2026-04-26/booking-side-rail-375.png`
- `docs/screenshots/2026-04-26/booking-side-rail-600.png`
- `docs/screenshots/2026-04-26/booking-side-rail-840.png`
- `docs/screenshots/2026-04-26/booking-side-rail-1200.png`
- `docs/screenshots/2026-04-26/booking-side-rail-1920.png`
- `docs/screenshots/2026-04-26/booking-side-rail-check.json`

## Resultado da verificacao publicada

- CSS publicado: `booking-addon.css?ver=1.4.7`.
- Notices visiveis: `0` em todos os breakpoints.
- Console relevante: `0` em todos os breakpoints.
- Requests falhos relevantes: `0` em todos os breakpoints.
- Overflow horizontal real: `false` em todos os breakpoints.
- Padding minimo em paineis: `14px` em todos os breakpoints.
- Alinhamento horizontal dos blocos principais: spread `0px` nos cinco breakpoints.
- Alinhamento horizontal dos blocos da lateral: spread `0px` nos cinco breakpoints.
- Em `1920px`: grid `918px 420px`, lateral com `sideRailTopDelta=0`, gap horizontal `24px` e gaps internos `16px`.

## Sugestoes remanescentes

- Nao ha bloqueio visual restante no Booking apos esta rodada.
- Melhoria futura opcional: criar um teste automatizado dedicado para preencher cliente, pet e servico e validar o resumo com dados reais, complementando a prova visual estrutural.
