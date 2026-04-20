# Screenshots 2026-04-17 - Matéria piloto

## Contexto
- Objetivo da mudança: publicar a primeira matéria piloto baseada no novo modelo editorial da Desi Pet Shower.
- Tema da matéria: `5 sinais de que a rotina de banho do seu pet pode pedir um ajuste`.
- Ambiente: preview local do shell WordPress + Flatsome em `docs/screenshots/2026-04-16/site-pages-preview.html`.
- Referência de design DPS Signature utilizada:
  - `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
  - `docs/visual/VISUAL_STYLE_GUIDE.md`

## Antes/Depois
- Resumo do antes: havia apenas o template editorial base, ainda sem uma matéria real pronta para publicação.
- Resumo do depois: a primeira matéria foi recriada com copy final em português brasileiro, quadro visual próprio, fechamento editorial e links de continuação coerentes com a jornada do site.
- Ajustes desta revisão:
  - acentuação corrigida e validada em renderização real;
  - título e corpo ajustados para SEO natural;
  - regra de marca aplicada no contexto de banho e tosa, usando `Desi Pet Shower` ou `DPS`.
- Arquivos de código alterados:
  - `docs/layout/site/pages/materias/cinco-sinais-ajuste-rotina-banho/page-content.html`
  - `docs/layout/site/pages/materias/cinco-sinais-ajuste-rotina-banho/seo-notes.md`
  - `docs/layout/site/templates/materia-breaking-news/README.md`
  - `docs/layout/site/templates/materia-breaking-news/EDITORIAL_VOICE_GUIDE.md`
  - `docs/layout/site/templates/materia-breaking-news/EDITORIAL_SEO_GUIDE.md`
  - `docs/layout/site/templates/materia-breaking-news/VISUAL_ASSET_GUIDE.md`
  - `docs/layout/site/templates/materia-breaking-news/page-content.html`

## Escolha do apoio visual
- Para esta matéria, o melhor apoio visual foi um **quadro de leitura rápida**.
- Motivo: o assunto é diagnóstico/orientativo, então um quadro ajuda mais do que uma foto decorativa.
- Regra documentada em:
  - `docs/layout/site/templates/materia-breaking-news/VISUAL_ASSET_GUIDE.md`

## Capturas
- `./materia-piloto-desktop-1280-fullpage.png`
- `./materia-piloto-mobile-375-fullpage.png`

## Observações
- O preview foi validado localmente com `npx playwright screenshot`.
- O tom da matéria foi ajustado para ficar mais natural, menos formal e ainda sério, conforme documentado em `docs/layout/site/templates/materia-breaking-news/EDITORIAL_VOICE_GUIDE.md`.
- A renderização final confirmou UTF-8 válido e acentuação correta em desktop e mobile.
