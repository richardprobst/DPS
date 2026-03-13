# Screenshots 2026-03-12 — Agenda (cabecalho minimalista)

## Contexto
- Objetivo da mudanca: reposicionar `Periodo ativo` para abaixo de `Agenda de Atendimentos` sem container visual e mover o seletor de data para o bloco `Ver:` com o mesmo padrao visual.
- Ambiente: preview local estatico em `http://127.0.0.1:8766/docs/screenshots/2026-03-12/agenda-header-minimal-preview.html`.
- Referencia de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Antes: `Periodo ativo` ficava em badge separado no header e o seletor de data ficava no bloco superior.
- Depois: `Periodo ativo` aparece como texto minimalista abaixo do titulo e o seletor de data foi integrado ao bloco inferior `Ver:`.
- Arquivos de codigo alterados:
  - `plugins/desi-pet-shower-agenda/desi-pet-shower-agenda-addon.php`
  - `plugins/desi-pet-shower-agenda/assets/css/agenda-addon.css`

## Breakpoints validados
- 375px
- 600px
- 840px
- 1200px
- 1920px

## Capturas
- `./agenda-header-minimal-mobile-375-fullpage.png`
- `./agenda-header-minimal-mobile-600-fullpage.png`
- `./agenda-header-minimal-tablet-840-fullpage.png`
- `./agenda-header-minimal-desktop-1200-fullpage.png`
- `./agenda-header-minimal-wide-1920-fullpage.png`

## Artefatos auxiliares
- `./agenda-header-minimal-preview.html`

## Observacoes
- Validacao realizada via preview estatico por indisponibilidade de instancia WordPress ativa neste workspace.
