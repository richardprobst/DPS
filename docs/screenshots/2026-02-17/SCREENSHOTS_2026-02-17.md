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

---

# Screenshots 2026-02-17 — Client Portal add-on (main page + tabs)

## Contexto
- Objetivo da mudança: revisar UX/UI do shell da página principal e do sistema de abas do portal do cliente, sem alterar conteúdo interno das abas.
- Ambiente: preview estático local (`python3 -m http.server`) com estilos e JS reais do add-on.
- Referência de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - área de topo sem agrupamento claro de ações principais;
  - tabs sem foco visível dedicado e sem navegação por teclado com setas/home/end;
  - estado de carregamento ao trocar aba inexistente;
  - em mobile os rótulos das abas ficavam ocultos, reduzindo descobribilidade.
- Resumo do depois:
  - header com hierarquia mais clara (`main` + `actions`) e ação de logout estilizada;
  - tabs com foco visível, suporte a `aria-disabled`, `tabindex` roving e ARIA melhorada;
  - navegação por teclado (`ArrowLeft/Right`, `Home/End`, `Enter`/`Space`) e deep link por hash;
  - feedback de carregamento curto na troca de aba;
  - mobile com rótulos visíveis e overflow horizontal controlado.
- Arquivos de código alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## Capturas
- `./client-portal-main-tabs-desktop-fullpage.png`
- `./client-portal-main-tabs-tablet-fullpage.png`
- `./client-portal-main-tabs-mobile-fullpage.png`
- `./client-portal-main-tabs-preview.html` (fonte visual de validação)
- Evidências externas fornecidas no contexto da revisão:
  - `https://github.com/user-attachments/assets/988b5065-fb7a-421f-a7a8-0358b4c65ec3`
  - `https://github.com/user-attachments/assets/2a9d2816-6af9-4ddb-9a0b-daf96ef4b0d8`
  - `https://github.com/user-attachments/assets/d2f14754-f53d-4dbb-b170-52a255bfc1ea`

## Observações
- Como o ambiente não tinha WordPress rodando para captura da tela real do plugin, foi utilizado preview estático para validar shell e tabs.
- O escopo foi restrito ao shell principal e navegação por abas, sem mudanças no conteúdo interno de cada painel.
