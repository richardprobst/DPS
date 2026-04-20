# Screenshots 2026-04-17 - Template editorial para materias

## Contexto
- Objetivo da mudanca: criar um modelo reutilizavel de post estilo breaking news / blog para materias informativas da Desi, mantendo o `DPS Signature` e diferenciando o clima editorial das paginas comerciais.
- Ambiente: preview local do shell WordPress + Flatsome em `docs/screenshots/2026-04-16/site-pages-preview.html`.
- Referencia de design DPS Signature utilizada:
  - `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
  - `docs/visual/VISUAL_STYLE_GUIDE.md`

## Antes/Depois
- Resumo do antes: o sistema tinha paginas institucionais e de servico, mas nao tinha um modelo editorial proprio para materias.
- Resumo do depois: passou a existir um template base com hero jornalistico, metadados de leitura, sidebar editorial, corpo de artigo, callouts, quote e fechamento com CTA discreto.
- Arquivos de codigo alterados:
  - `docs/layout/site/flatsome/flatsome-additional-css.css`
  - `docs/layout/site/templates/materia-breaking-news/page-content.html`
  - `docs/layout/site/templates/materia-breaking-news/README.md`
  - `docs/layout/site/README.md`
  - `docs/layout/site/site-pages.manifest.json`
  - `docs/screenshots/2026-04-16/site-pages-preview.html`

## Capturas
- `./modelo-materia-desktop-1280-fullpage.png`
- `./modelo-materia-mobile-375-fullpage.png`

## Observacoes
- O preview foi validado localmente por linha de comando com `npx playwright screenshot`, porque a sessao do Playwright MCP estava fechada neste ambiente.
- O template foi registrado em `docs/layout/site/templates/materia-breaking-news/` para servir como base fixa das proximas materias.
