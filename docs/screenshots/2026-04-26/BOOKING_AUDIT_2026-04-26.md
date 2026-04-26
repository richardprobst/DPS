# Evidencias visuais - Booking / Agendamento - 2026-04-26

## Contexto

Auditoria integral do Booking Add-on e da pagina publicada `https://desi.pet/agendamento/`.

Fonte visual obrigatoria: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Ambiente

- Site: `https://desi.pet`
- Pagina: `https://desi.pet/agendamento/`
- Sessao: usuario temporario criado via WP-CLI e removido apos os testes
- Browser: Chromium headless via Playwright
- Data: 2026-04-26

## Capturas autenticadas

- `booking-audit-auth-375.png` - breakpoint 375px
- `booking-audit-auth-600.png` - breakpoint 600px
- `booking-audit-auth-840.png` - breakpoint 840px
- `booking-audit-auth-1200.png` - breakpoint 1200px
- `booking-audit-auth-1920.png` - breakpoint 1920px

## Smoke funcional sem salvar dados

- `booking-audit-interaction-1200.png` - estado apos selecionar cliente e data
- `booking-audit-auth-check.json` - verificacao de render, campos, overflow e assets por breakpoint
- `booking-audit-interaction-check.json` - verificacao de cliente, pets, horarios e endpoints acionados

## Resultado observado

- Formulario autenticado renderizou em todos os breakpoints.
- Nao houve overflow horizontal no estado inicial autenticado.
- `booking-addon.css` foi carregado.
- Nonce `dps_nonce_agendamentos` presente.
- Selecao de cliente carregou pet via REST.
- Selecao de data carregou horarios via `admin-ajax.php`.
- Usuario temporario foi removido ao final.

## Problemas visuais registrados

- Mojibake visivel em textos e icones.
- Cores fora da paleta canonica DPS Signature.
- Cantos arredondados, pills e sombras herdadas.
- Formulario muito longo no mobile.
- Lista de servicos renderizada integralmente, sem busca ou progressao.
- Painel de atribuicao com fundo roxo destoante.
