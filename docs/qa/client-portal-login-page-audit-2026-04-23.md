# Auditoria integral do login inicial do Portal do Cliente

Data: `2026-04-23`

Fonte de verdade visual seguida nesta auditoria e na implementacao: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` (padrao DPS Signature).

## Escopo

Auditoria integral da pagina inicial publica do Portal do Cliente em `https://desi.pet/portal-do-cliente/`, cobrindo:

- UX/UI do shell publico;
- coerencia visual com o DPS Signature;
- responsividade real em `375`, `600`, `840`, `1200` e `1920`;
- fluxos funcionais de senha, magic link e redefinicao de senha;
- estabilidade do runtime publicado e do add-on `plugins/desi-pet-shower-client-portal/`.

## Diagnostico consolidado

### Achados principais antes da reescrita

- a tela publica estava acoplada ao bundle grande do portal autenticado, o que deixava o bootstrap sensivel a handlers tardios que nao pertenciam ao acesso inicial;
- o CTA `Criar ou redefinir senha` dependia desse bootstrap quebrado e no publicado ficava sem feedback util;
- o fluxo de reset tinha um defeito backend: `login` e `key` eram enviados com `rawurlencode()` antes de `add_query_arg()`, gerando URL duplamente codificada e invalidando o `check_password_reset_key()`;
- a hierarquia de UX da landing ainda nao dava o devido peso ao retorno recorrente por senha nem separava com clareza os dois modos de acesso;
- o layout antigo desperdicava area nobre com cards concorrentes e nao apresentava comparacao suficiente entre senha, link rapido e suporte;
- faltava ergonomia basica no shell publico, como alternancia explicita de modo, sincronizacao de e-mail entre trilhas e toggle de visibilidade de senha.

### Riscos de UX/UI identificados

- dificuldade de entendimento do caminho principal para clientes recorrentes;
- duplicacao desnecessaria de contexto e de entrada de e-mail;
- fragilidade de confianca quando o usuario clica e nada acontece;
- risco de regressao visual em breakpoints intermediarios caso o shell publico continuasse reaproveitando estruturas do portal autenticado.

## Correcoes entregues nesta rodada

### Reescrita integral do shell publico

- a tela inicial foi reestruturada em shell DPS Signature proprio, com hero editorial, comparacao entre modos de acesso, trilha primaria por senha e rail de suporte;
- o reset de senha ganhou shell equivalente, com campos, toggle de visibilidade, copy operacional e estados valido/invalido consistentes;
- a geometria publica passou a usar `0px` e `2px`, com paleta `ink`/`petrol`/`paper`/`bone`, alinhada ao DPS Signature publicado.

### Runtime publico desacoplado

- o acesso publico deixou de depender do bootstrap completo de `client-portal.js`;
- foi criado o runtime dedicado `assets/js/client-portal-access.js` para tabs, sincronizacao de e-mail, toggles de senha e AJAX da landing publica;
- os contratos externos do add-on foram preservados: mesmos shortcodes, hooks, endpoints AJAX, nomes de campos e nonces.

### Correcao funcional de senha/reset

- o CTA `Criar ou redefinir senha` foi mantido assincrono, anti-enumeration e com feedback inline na propria tela;
- o fluxo de reset deixou de gerar links duplamente codificados;
- os redirects internos do reset tambem passaram a preservar `login` e `key` sem dupla codificacao;
- a tela publicada voltou a abrir com os campos de nova senha validos quando o link e emitido pelo proprio add-on.

## Validacao publicada

### Runtime

- pagina validada diretamente em `https://desi.pet/portal-do-cliente/`;
- reset validado com URL emitida no servidor via WP-CLI;
- login por senha validado com fixture temporario autenticado;
- login por magic link validado com token temporario emitido no servidor via WP-CLI.

### Resultado funcional

- `pageErrors = []` em todas as amostras do shell publico e dos fluxos validados;
- `password login`: autenticou e abriu o portal com `9` tabs;
- `magic login`: autenticou e abriu o portal com `9` tabs;
- `Criar ou redefinir senha`: respondeu `200` com feedback inline anti-enumeration esperado;
- `Link rapido`: respondeu `200` com feedback inline de envio bem-sucedido;
- `reset`: abriu com `2` toggles de senha ativos e sem overflow.

### Responsividade

- `375`: sem overflow horizontal, shell `355px`, CTA `295x56`;
- `600`: sem overflow horizontal, shell `580px`, CTA `514x56`;
- `840`: sem overflow horizontal, shell `820px`, CTA `751x56`;
- `1200`: sem overflow horizontal, shell `1140px`, CTA `617x56`;
- `1920`: sem overflow horizontal, shell maximo mantido em `1140px`.

### Ruido externo observado

- erro CORS de `adsbygoogle.js` continua aparecendo no dominio publicado e nao pertence ao add-on;
- o notice de carregamento antecipado do `all-in-one-wp-migration` apareceu nas chamadas WP-CLI do ambiente, mas nao interfere no fluxo funcional do portal.

## Plano recomendado apos esta rodada

### Prioridade alta

- versionar um smoke test publicado do login publico, cobrindo senha, magic link e reset, para reexecutar sempre que o add-on mudar;
- expor no admin do Portal um resumo simples de throttling publico por e-mail/IP para facilitar suporte quando clientes relatarem nao recebimento;
- manter a separacao entre runtime publico e runtime autenticado como regra de arquitetura do add-on.

### Prioridade media

- adicionar indicacao de forca de senha e dicas de composicao na tela de reset;
- avaliar um CTA contextual de reenvio quando o reset expirar, sem depender do usuario voltar manualmente ao topo do fluxo;
- revisar a copy de suporte para clientes sem e-mail cadastrado, conectando melhor a trilha de WhatsApp com a trilha de acesso.

### Prioridade baixa

- criar um estado de sucesso mais orientado apos salvar a nova senha, indicando retorno automatico ao portal e o e-mail vinculado;
- registrar no admin a origem do ultimo acesso publico solicitado (`magic` ou `password_access_email`) para facilitar auditoria operacional.
