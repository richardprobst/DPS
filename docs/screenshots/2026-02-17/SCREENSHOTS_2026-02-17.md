# Screenshots 2026-02-17 — Agenda add-on (revisão UX/UI)

## Contexto
- Objetivo da mudança: melhorar usabilidade e elegância visual do add-on de agenda com ajustes de foco visível, contraste e legibilidade responsiva.
- Ambiente: preview estático local (`python3 -m http.server`) com estilos reais do plugin.
- Referência de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - elementos interativos sem foco visível consistente;
  - badge pendente com hierarquia visual discreta;
  - tipografia do heatmap compacta em viewport menor.
- Resumo do depois:
  - foco visível padronizado para botões-chave e campos numéricos;
  - estado pendente com melhor contraste/ênfase visual;
  - leitura do heatmap melhorada em telas menores.
- Arquivos de código alterados:
  - `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
  - `plugins/desi-pet-shower-agenda/assets/css/dashboard.css`

## Capturas
- `./agenda-ux-ui-desktop-fullpage.png`
- `./agenda-ux-ui-tablet-fullpage.png`
- `./agenda-ux-ui-mobile-fullpage.png`
- `./ux-ui-agenda-review-preview.html` (fonte visual de validação)

## Observações
- Como o ambiente não tinha WordPress rodando para captura da tela real do plugin, foi utilizado preview estático com os CSS reais do add-on para validar os ajustes visuais.
