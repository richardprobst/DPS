# Screenshots 2026-02-17 â€” Agenda add-on (revisÃ£o UX/UI)

## Contexto
- Objetivo da mudanÃ§a: melhorar usabilidade e elegÃ¢ncia visual do add-on de agenda com ajustes de foco visÃ­vel, contraste e legibilidade responsiva.
- Ambiente: preview estÃ¡tico local (`python3 -m http.server`) com estilos reais do plugin.
- ReferÃªncia de design DPS Signature utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - elementos interativos sem foco visÃ­vel consistente;
  - badge pendente com hierarquia visual discreta;
  - tipografia do heatmap compacta em viewport menor.
- Resumo do depois:
  - foco visÃ­vel padronizado para botÃµes-chave e campos numÃ©ricos;
  - estado pendente com melhor contraste/Ãªnfase visual;
  - leitura do heatmap melhorada em telas menores.
- Arquivos de cÃ³digo alterados:
  - `plugins/desi-pet-shower-agenda/assets/css/checklist-checkin.css`
  - `plugins/desi-pet-shower-agenda/assets/css/dashboard.css`

## Capturas
- `./agenda-ux-ui-desktop-fullpage.png`
- `./agenda-ux-ui-tablet-fullpage.png`
- `./agenda-ux-ui-mobile-fullpage.png`
- `./ux-ui-agenda-review-preview.html` (fonte visual de validaÃ§Ã£o)

## ObservaÃ§Ãµes
- Como o ambiente nÃ£o tinha WordPress rodando para captura da tela real do plugin, foi utilizado preview estÃ¡tico com os CSS reais do add-on para validar os ajustes visuais.

---

# Screenshots 2026-02-17 â€” Client Portal add-on (main page + tabs)

## Contexto
- Objetivo da mudanÃ§a: revisar UX/UI do shell da pÃ¡gina principal e do sistema de abas do portal do cliente, sem alterar conteÃºdo interno das abas.
- Ambiente: preview estÃ¡tico local (`python3 -m http.server`) com estilos e JS reais do add-on.
- ReferÃªncia de design DPS Signature utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - Ã¡rea de topo sem agrupamento claro de aÃ§Ãµes principais;
  - tabs sem foco visÃ­vel dedicado e sem navegaÃ§Ã£o por teclado com setas/home/end;
  - estado de carregamento ao trocar aba inexistente;
  - em mobile os rÃ³tulos das abas ficavam ocultos, reduzindo descobribilidade;
  - breadcrumb estÃ¡tico (sempre exibia "InÃ­cio" independente da aba ativa);
  - sem indicadores visuais de overflow na barra de abas em mobile;
  - active tab sem diferenciaÃ§Ã£o visual forte (mesmo font-weight das demais).
- Resumo do depois:
  - header com hierarquia mais clara (`main` + `actions`) e aÃ§Ã£o de logout estilizada;
  - tabs com foco visÃ­vel, suporte a `aria-disabled`, `tabindex` roving e ARIA melhorada;
  - navegaÃ§Ã£o por teclado (`ArrowLeft/Right`, `Home/End`, `Enter`/`Space`) e deep link por hash;
  - feedback de carregamento curto na troca de aba;
  - mobile com rÃ³tulos visÃ­veis e overflow horizontal controlado;
  - breadcrumb dinÃ¢mico que atualiza ao trocar de aba;
  - gradientes de overflow (fade hints) indicando direÃ§Ã£o de rolagem em mobile;
  - scroll-snap e auto-scroll da aba ativa para Ã¡rea visÃ­vel;
  - active tab com font-weight 600 (mais forte e claro);
  - transiÃ§Ãµes CSS especÃ­ficas (sem `transition: all`);
  - `prefers-reduced-motion` respeitado na animaÃ§Ã£o dos painÃ©is;
  - separador do breadcrumb com `aria-hidden="true"`.
- Arquivos de cÃ³digo alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## Capturas
- `./client-portal-main-tabs-desktop-fullpage.png`
- `./client-portal-main-tabs-tablet-fullpage.png`
- `./client-portal-main-tabs-mobile-fullpage.png`
- `./client-portal-main-tabs-preview.html` (fonte visual de validaÃ§Ã£o)
- EvidÃªncias externas fornecidas no contexto da revisÃ£o:
  - Desktop (apÃ³s): `https://github.com/user-attachments/assets/0a78fe84-ec60-423f-9eec-a36bcd197c1b`
  - Mobile (apÃ³s): `https://github.com/user-attachments/assets/1be0e197-d102-40e5-9099-d79cb4b58776`
  - Desktop (anterior): `https://github.com/user-attachments/assets/988b5065-fb7a-421f-a7a8-0358b4c65ec3`
  - Tablet (anterior): `https://github.com/user-attachments/assets/2a9d2816-6af9-4ddb-9a0b-daf96ef4b0d8`
  - Mobile (anterior): `https://github.com/user-attachments/assets/d2f14754-f53d-4dbb-b170-52a255bfc1ea`

## ObservaÃ§Ãµes
- Como o ambiente nÃ£o tinha WordPress rodando para captura da tela real do plugin, foi utilizado preview estÃ¡tico para validar shell e tabs.
- O escopo foi restrito ao shell principal e navegaÃ§Ã£o por abas, sem mudanÃ§as no conteÃºdo interno de cada painel.

---

# Screenshots 2026-02-17 â€” AI Add-on (assistente virtual no portal)

## Contexto
- Objetivo da mudanÃ§a: revisÃ£o UX/UI + funcional do widget de assistente virtual na Ã¡rea de topo do portal do cliente.
- Ambiente: validaÃ§Ã£o via inspeÃ§Ã£o de cÃ³digo (sem WordPress rodando).
- ReferÃªncia de design DPS Signature utilizada: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois
- Resumo do antes:
  - sem `role="region"` ou `aria-label` no container principal;
  - header nÃ£o focÃ¡vel via teclado (sem `tabindex`);
  - mensagens sem `aria-live` â€” screen readers nÃ£o anunciavam novas mensagens;
  - sem Escape key handler para fechar/recolher o widget;
  - sem prevenÃ§Ã£o de envio duplo (clique rÃ¡pido no botÃ£o Send);
  - AJAX sem timeout, possÃ­vel espera indefinida em caso de falha de rede;
  - chevron apontava para cima quando colapsado (semanticamente invertido);
  - vÃ¡rios elementos interativos sem `focus-visible` (sugestÃµes, FAB, header, submit, feedback).
- Resumo do depois:
  - container com `role="region"` e `aria-label="Assistente virtual"`;
  - header com `tabindex="0"` e estilo `focus-visible`;
  - mensagens com `aria-live="polite"` e `aria-relevant="additions"`;
  - Escape recolhe inline / fecha flutuante, devolvendo foco;
  - flag `isSubmitting` bloqueia envios duplicados;
  - AJAX com timeout de 15s e mensagem de erro especÃ­fica para timeout;
  - chevron corrigido: aponta para baixo quando colapsado, para cima quando expandido;
  - `focus-visible` em header, FAB, sugestÃµes, submit, feedback buttons;
  - `aria-label` nos botÃµes de sugestÃ£o.
- Arquivos de cÃ³digo alterados:
  - `plugins/desi-pet-shower-ai/includes/class-dps-ai-integration-portal.php`
  - `plugins/desi-pet-shower-ai/assets/css/dps-ai-portal.css`
  - `plugins/desi-pet-shower-ai/assets/js/dps-ai-portal.js`

## ObservaÃ§Ãµes
- Como o ambiente nÃ£o tinha WordPress rodando, nÃ£o hÃ¡ capturas de tela do widget real. As alteraÃ§Ãµes foram validadas via inspeÃ§Ã£o de cÃ³digo, linting PHP/JS e verificaÃ§Ã£o de acessibilidade.
- O escopo foi restrito ao widget de assistente no topo do portal, sem mudanÃ§as no conteÃºdo interno das abas.

---

# Screenshots 2026-02-17 â€” Aba InÃ­cio do Portal do Cliente (revisÃ£o completa)

## Contexto
- Objetivo: revisÃ£o UX/UI + funcional da primeira aba (InÃ­cio) do portal do cliente.
- Ambiente: validaÃ§Ã£o via inspeÃ§Ã£o de cÃ³digo (sem WordPress rodando).

## Antes/Depois
- Resumo do antes:
  - Card de fidelidade (overview) tinha `role="button"` e `cursor:pointer` mas clique nÃ£o funcionava (dead click).
  - VerificaÃ§Ã£o de propriedade do pet em impressÃ£o de histÃ³rico usava meta key incorreta `pet_client_id` em vez de `owner_id`.
  - Nenhum elemento interativo na aba InÃ­cio tinha `focus-visible` (overview cards, quick actions, pet cards, collapsible, botÃµes de pagamento, sugestÃµes).
  - VÃ¡rios componentes usavam `transition: all` em vez de propriedades especÃ­ficas.
  - Elementos com `role="button"` nÃ£o respondiam a Enter/Space no teclado.
- Resumo do depois:
  - Card de fidelidade navega para aba correta ao clicar ou usar Enter/Space.
  - Propriedade do pet usa meta key canÃ´nica `owner_id`.
  - `focus-visible` em todos os elementos interativos da aba (8 componentes).
  - TransiÃ§Ãµes CSS especÃ­ficas em pet-card, quick-action e pet-action-btn.
  - Suporte a Enter/Space em elementos `role="button"` com `data-tab`.
- Arquivos de cÃ³digo alterados:
  - `plugins/desi-pet-shower-client-portal/includes/client-portal/class-dps-portal-ajax-handler.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## ObservaÃ§Ãµes
- ValidaÃ§Ã£o via php -l, node -c e inspeÃ§Ã£o de cÃ³digo.
- Escopo: aba InÃ­cio apenas â€” nenhuma outra aba foi alterada.

---

# Screenshots 2026-02-17 â€” Aba Fidelidade do Portal do Cliente (revisÃ£o completa)

## Contexto
- Objetivo: revisÃ£o UX/UI + funcional da segunda aba (Fidelidade) do portal do cliente.
- Ambiente: validaÃ§Ã£o via inspeÃ§Ã£o de cÃ³digo (sem WordPress rodando).

## Antes/Depois
- Resumo do antes:
  - barra de progresso de nÃ­vel sem atributos ARIA (role/aria-valuenow);
  - nenhum elemento interativo na aba Fidelidade tinha `focus-visible`;
  - campo numÃ©rico de resgate removia outline no foco (`outline: none`);
  - botÃ£o de resgate usava `transition: all`;
  - texto do botÃ£o de resgate era hardcoded a `'Resgatar pontos'` no finally (original: `'ðŸŽ Resgatar Agora'`);
  - input.value apÃ³s resgate podia exceder max attribute;
  - erro no carregamento de histÃ³rico era silencioso (sem feedback);
  - copiar link de referral nÃ£o funcionava em HTTP (sem fallback).
- Resumo do depois:
  - barra de progresso com `role="progressbar"`, `aria-valuenow`, `aria-valuemin="0"`, `aria-valuemax="100"`, `aria-label`;
  - `focus-visible` em 4 tipos de elementos interativos;
  - campo numÃ©rico mantÃ©m outline visÃ­vel no foco;
  - botÃ£o usa `transition: transform, box-shadow` + `focus-visible`;
  - texto original do botÃ£o preservado via variÃ¡vel;
  - input.value clamped ao novo max apÃ³s resgate;
  - toast exibido em erro de carregamento de histÃ³rico;
  - fallback de clipboard via `document.execCommand('copy')`.
- Arquivos de cÃ³digo alterados:
  - `plugins/desi-pet-shower-client-portal/includes/class-dps-client-portal.php`
  - `plugins/desi-pet-shower-client-portal/assets/css/client-portal.css`
  - `plugins/desi-pet-shower-client-portal/assets/js/client-portal.js`

## ObservaÃ§Ãµes
- ValidaÃ§Ã£o via php -l, node -c e inspeÃ§Ã£o de cÃ³digo.
- Escopo: aba Fidelidade apenas.
