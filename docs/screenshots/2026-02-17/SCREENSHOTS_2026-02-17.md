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
  - em mobile os rótulos das abas ficavam ocultos, reduzindo descobribilidade;
  - breadcrumb estático (sempre exibia "Início" independente da aba ativa);
  - sem indicadores visuais de overflow na barra de abas em mobile;
  - active tab sem diferenciação visual forte (mesmo font-weight das demais).
- Resumo do depois:
  - header com hierarquia mais clara (`main` + `actions`) e ação de logout estilizada;
  - tabs com foco visível, suporte a `aria-disabled`, `tabindex` roving e ARIA melhorada;
  - navegação por teclado (`ArrowLeft/Right`, `Home/End`, `Enter`/`Space`) e deep link por hash;
  - feedback de carregamento curto na troca de aba;
  - mobile com rótulos visíveis e overflow horizontal controlado;
  - breadcrumb dinâmico que atualiza ao trocar de aba;
  - gradientes de overflow (fade hints) indicando direção de rolagem em mobile;
  - scroll-snap e auto-scroll da aba ativa para área visível;
  - active tab com font-weight 600 (mais forte e claro);
  - transições CSS específicas (sem `transition: all`);
  - `prefers-reduced-motion` respeitado na animação dos painéis;
  - separador do breadcrumb com `aria-hidden="true"`.
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
  - Desktop (após): `https://github.com/user-attachments/assets/0a78fe84-ec60-423f-9eec-a36bcd197c1b`
  - Mobile (após): `https://github.com/user-attachments/assets/1be0e197-d102-40e5-9099-d79cb4b58776`
  - Desktop (anterior): `https://github.com/user-attachments/assets/988b5065-fb7a-421f-a7a8-0358b4c65ec3`
  - Tablet (anterior): `https://github.com/user-attachments/assets/2a9d2816-6af9-4ddb-9a0b-daf96ef4b0d8`
  - Mobile (anterior): `https://github.com/user-attachments/assets/d2f14754-f53d-4dbb-b170-52a255bfc1ea`

## Observações
- Como o ambiente não tinha WordPress rodando para captura da tela real do plugin, foi utilizado preview estático para validar shell e tabs.
- O escopo foi restrito ao shell principal e navegação por abas, sem mudanças no conteúdo interno de cada painel.

---

# Screenshots 2026-02-17 — AI Add-on (assistente virtual no portal)

## Contexto
- Objetivo da mudança: revisão UX/UI + funcional do widget de assistente virtual na área de topo do portal do cliente.
- Ambiente: validação via inspeção de código (sem WordPress rodando).
- Referência de design M3 utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - sem `role="region"` ou `aria-label` no container principal;
  - header não focável via teclado (sem `tabindex`);
  - mensagens sem `aria-live` — screen readers não anunciavam novas mensagens;
  - sem Escape key handler para fechar/recolher o widget;
  - sem prevenção de envio duplo (clique rápido no botão Send);
  - AJAX sem timeout, possível espera indefinida em caso de falha de rede;
  - chevron apontava para cima quando colapsado (semanticamente invertido);
  - vários elementos interativos sem `focus-visible` (sugestões, FAB, header, submit, feedback).
- Resumo do depois:
  - container com `role="region"` e `aria-label="Assistente virtual"`;
  - header com `tabindex="0"` e estilo `focus-visible`;
  - mensagens com `aria-live="polite"` e `aria-relevant="additions"`;
  - Escape recolhe inline / fecha flutuante, devolvendo foco;
  - flag `isSubmitting` bloqueia envios duplicados;
  - AJAX com timeout de 15s e mensagem de erro específica para timeout;
  - chevron corrigido: aponta para baixo quando colapsado, para cima quando expandido;
  - `focus-visible` em header, FAB, sugestões, submit, feedback buttons;
  - `aria-label` nos botões de sugestão.
- Arquivos de código alterados:
  - `plugins/desi-pet-shower-ai/includes/class-dps-ai-integration-portal.php`
  - `plugins/desi-pet-shower-ai/assets/css/dps-ai-portal.css`
  - `plugins/desi-pet-shower-ai/assets/js/dps-ai-portal.js`

## Observações
- Como o ambiente não tinha WordPress rodando, não há capturas de tela do widget real. As alterações foram validadas via inspeção de código, linting PHP/JS e verificação de acessibilidade.
- O escopo foi restrito ao widget de assistente no topo do portal, sem mudanças no conteúdo interno das abas.

---

# Screenshots 2026-02-17 — Aba Início do Portal do Cliente (revisão completa)

## Contexto
- Objetivo: revisão UX/UI + funcional da primeira aba (Início) do portal do cliente.
- Ambiente: validação via inspeção de código (sem WordPress rodando).

## Antes/Depois
- Resumo do antes:
  - Card de fidelidade (overview) tinha `role="button"` e `cursor:pointer` mas clique não funcionava (dead click).
  - Verificação de propriedade do pet em impressão de histórico usava meta key incorreta `pet_client_id` em vez de `owner_id`.
  - Nenhum elemento interativo na aba Início tinha `focus-visible` (overview cards, quick actions, pet cards, collapsible, botões de pagamento, sugestões).
  - Vários componentes usavam `transition: all` em vez de propriedades específicas.
  - Elementos com `role="button"` não respondiam a Enter/Space no teclado.
- Resumo do depois:
  - Card de fidelidade navega para aba correta ao clicar ou usar Enter/Space.
  - Propriedade do pet usa meta key canônica `owner_id`.
  - `focus-visible` em todos os elementos interativos da aba (8 componentes).
  - Transições CSS específicas em pet-card, quick-action e pet-action-btn.
  - Suporte a Enter/Space em elementos `role="button"` com `data-tab`.
- Arquivos de código alterados:
  - `plugins/desi-pet-shower-client-portal/includes/client-portal/class-dps-portal-ajax-handler.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## Observações
- Validação via php -l, node -c e inspeção de código.
- Escopo: aba Início apenas — nenhuma outra aba foi alterada.

---

# Screenshots 2026-02-17 — Aba Fidelidade do Portal do Cliente (revisão completa)

## Contexto
- Objetivo: revisão UX/UI + funcional da segunda aba (Fidelidade) do portal do cliente.
- Ambiente: validação via inspeção de código (sem WordPress rodando).

## Antes/Depois
- Resumo do antes:
  - barra de progresso de nível sem atributos ARIA (role/aria-valuenow);
  - nenhum elemento interativo na aba Fidelidade tinha `focus-visible`;
  - campo numérico de resgate removia outline no foco (`outline: none`);
  - botão de resgate usava `transition: all`;
  - texto do botão de resgate era hardcoded a `'Resgatar pontos'` no finally (original: `'🎁 Resgatar Agora'`);
  - input.value após resgate podia exceder max attribute;
  - erro no carregamento de histórico era silencioso (sem feedback);
  - copiar link de referral não funcionava em HTTP (sem fallback).
- Resumo do depois:
  - barra de progresso com `role="progressbar"`, `aria-valuenow`, `aria-valuemin="0"`, `aria-valuemax="100"`, `aria-label`;
  - `focus-visible` em 4 tipos de elementos interativos;
  - campo numérico mantém outline visível no foco;
  - botão usa `transition: transform, box-shadow` + `focus-visible`;
  - texto original do botão preservado via variável;
  - input.value clamped ao novo max após resgate;
  - toast exibido em erro de carregamento de histórico;
  - fallback de clipboard via `document.execCommand('copy')`.
- Arquivos de código alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## Observações
- Validação via php -l, node -c e inspeção de código.
- Escopo: aba Fidelidade apenas.
