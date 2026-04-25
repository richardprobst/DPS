# Auditoria e plano integral do Cadastro Add-on - DPS Signature

**Data:** 2026-04-25  
**Escopo:** `plugins/desi-pet-shower-registration/`  
**Superficie:** formulario publico de cadastro de tutor e pets, shortcode publicado e variante autenticada/admin  
**Classificacao:** Trilha B, por envolver UX ampla, contratos publicos, REST/AJAX, fluxo de autenticacao administrativa, Google Places e possivel reestruturacao interna.

## Fontes de verdade consultadas

- `AGENTS.md`
- `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`
- `docs/visual/VISUAL_STYLE_GUIDE.md`
- `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`
- `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js`
- `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`
- `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
- `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
- `plugins/desi-pet-shower-registration/README.md`

## Veredito executivo

O Cadastro Add-on deve ser reescrito como modulo integral, nao remendado. A superficie publicada funciona parcialmente, mas a implementacao atual mistura renderizacao, processamento POST, REST, admin, e-mails, rate limit, mensagens, Google Places e UI em uma unica classe de mais de 3.300 linhas. O CSS tambem carrega decisoes antigas incompatíveis com DPS Signature, e a pagina publicada tem erro funcional de Google Places no runtime.

A reescrita deve preservar os contratos externos e trocar a organizacao interna: separar renderers, servicos, validadores, controladores REST/AJAX, assets e persistencia. O objetivo nao e "embelezar" o formulario; e entregar um fluxo de cadastro proprietario, direto, responsivo, sem cache/transients, com UX clara para cliente final e modo admin seguro.

## Contratos que devem ser preservados

| Contrato | Evidencia | Regra para a reescrita |
|---|---|---|
| Shortcode canonico `[dps_registration_form]` | `desi-pet-shower-registration-addon.php:161` | Manter comportamento publico. |
| Alias de compatibilidade `[dps_registration_v2]` | `desi-pet-shower-registration-addon.php:162` | Manter ate confirmacao explicita de extincao. |
| Enqueue condicional para ambos os shortcodes | `desi-pet-shower-registration-addon.php:705-709` | Preservar deteccao dos dois shortcodes. |
| REST `POST /wp-json/dps/v1/register` | `desi-pet-shower-registration-addon.php:866-874` | Preservar rota, metodo e autenticacao por API key. |
| AJAX `dps_registration_send_test_email` | `desi-pet-shower-registration-addon.php:185` | Preservar action e nonce. |
| AJAX `dps_registration_check_duplicate` | `desi-pet-shower-registration-addon.php:188` | Preservar action e nonce para admin. |
| Nonce do formulario `dps_reg_nonce` / action `dps_reg_action` | `desi-pet-shower-registration-addon.php:2482-2484` | Nao alterar nomes sem ponte de compatibilidade. |
| Campos POST de tutor | `desi-pet-shower-registration-addon.php:2519-2529` | Preservar `client_name`, `client_cpf`, `client_phone`, `client_email`, `client_birth`, `client_instagram`, `client_facebook`, `client_photo_auth`, `client_address`, `client_referral`. |
| Campos POST de pets | `desi-pet-shower-registration-addon.php:3260-3298` | Preservar arrays `pet_*[]` e indice de `pet_aggressive`. |
| Hook `dps_registration_spam_check` | `desi-pet-shower-registration-addon.php:1878` | Preservar assinatura. |
| Hook `dps_registration_after_client_created` | `desi-pet-shower-registration-addon.php:2059` e `:1086` | Preservar assinatura atual. Adicionar novo hook posterior aos pets se necessario. |
| Hook `dps_registration_after_fields` | `desi-pet-shower-registration-addon.php:2579` | Preservar local ou documentar nova posicao compatível. |
| Filtro `dps_registration_agenda_url` | `desi-pet-shower-registration-addon.php:2656` | Preservar para CTA pos-cadastro. |

## Achados criticos

### 1. Google Places quebra no runtime publicado

Evidencia local/codigo:
- O campo de endereco e renderizado como `textarea`: `desi-pet-shower-registration-addon.php:2528`.
- O JS inicializa `google.maps.places.Autocomplete(input)` esperando elemento compativel: `assets/js/dps-registration.js:671-680`.
- O plugin injeta callback proprio `dpsGoogleMapsReady`: `desi-pet-shower-registration-addon.php:2591-2602`.
- O base ja possui loader compartilhado com callback `dpsSignatureGooglePlacesReady`: `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js:173-205`.

Evidencia publicada em `https://desi.pet/cadastro/`:
- `#dps-client-address` apareceu como `TEXTAREA`.
- Console retornou `InvalidValueError: not an instance of HTMLInputElement`.
- A pagina ainda carregou o formulario, mas o autocomplete de endereco esta tecnicamente quebrado.

Correcao no plano:
- Trocar endereco para `input type="text"` com campo auxiliar opcional para complemento/observacoes.
- Usar uma unica trilha de loader, preferencialmente o loader compartilhado do DPS Signature.
- Evitar callback paralelo e remover a injecao inline de script do renderer.
- Avaliar migracao para `PlaceAutocompleteElement` somente como decisao controlada, pois altera comportamento da API do Google Places.

### 2. Uso de transients viola a regra global de cache proibido

Evidencia:
- Rate limit publico usa `get_transient`/`set_transient`: `desi-pet-shower-registration-addon.php:344-377`.
- Fallback de mensagens usa transients por IP: `desi-pet-shower-registration-addon.php:620-640` e `:649-658`.
- Rate limit REST usa transients: `desi-pet-shower-registration-addon.php:916-940`.

Impacto:
- Conflita diretamente com o MUST do `AGENTS.md`: nao implementar cache/transients/object cache/armazenamento temporario para reutilizacao.
- A seguranca anti-abuso continua necessaria, mas deve virar estado persistente/auditavel, nao cache.

Correcao no plano:
- Substituir por armazenamento persistente de eventos de seguranca, com TTL logico calculado em consulta e limpeza operacional documentada.
- Alternativa recomendada: tabela propria do add-on versionada com `dbDelta()` para tentativas e mensagens de curta retencao. Como nao e tabela compartilhada, nao cai no ASK BEFORE de schema compartilhado, mas deve ser documentada em `ANALYSIS.md`.
- Alternativa sem tabela: reutilizar um servico persistente de logs do DPS se houver contrato formal suficiente; se nao houver, nao improvisar em options autoloaded.

### 3. CSS atual ainda carrega estetica antiga, nao DPS Signature puro

Evidencia:
- O cabecalho do CSS se declara "Legacy Fallback" e cita "Elevated cards" e "Botoes pill-shaped": `assets/css/registration-addon.css:1-18`.
- O formulario usa aliases legados `--dps-color-primary`, `--dps-color-surface`, `--dps-color-outline` em vez de semantica canonica `--dps-signature-*`: `assets/css/registration-addon.css:41-61`.
- Container, progresso, fieldsets e legends usam radius/elevacao fora do gate reto do DPS Signature: `assets/css/registration-addon.css:68-75`, `:116-170`, `:420-464`, `:923-1001`.

Correcao no plano:
- Recriar o CSS do Cadastro como `registration-signature.css` ou reescrever `registration-addon.css` por completo.
- Base visual: `paper`, `bone`, `line`, `ink`, `petrol`, `sky`, `action`, `warning`, `danger`.
- Raio padrao `0px`; controles pequenos `2px-4px`; sem pill UI.
- Sem sombras decorativas, sem cards flutuantes, sem emojis como estrutura visual.

### 4. JS mistura regra de negocio de UI, markup e estilo inline

Evidencia:
- Erros JS sao criados com hex/radius inline: `assets/js/dps-registration.js:357-364`.
- Preferencias de produto e resumo sao montados por `innerHTML` dentro do JS: `assets/js/dps-registration.js:774-859`.
- Modal de duplicata injeta HTML e estilos dinamicos: `assets/js/dps-registration.js:1219-1328`.
- Reenvio de formulario usa fluxo misto de `requestSubmit`, evento manual e `form.submit()`: `assets/js/dps-registration.js:1579-1586` e `:1621-1622`.

Correcao no plano:
- JS deve ser um controlador de estado, nao um template engine visual.
- Templates HTML devem vir do PHP com escape e atributos estaveis, ou de `<template>` declarativo.
- CSS do modal, erros, preferencias e resumo deve sair do JS e ir para CSS versionado.
- Reenvio com reCAPTCHA deve usar flag de estado e caminho unico; evitar bypass acidental de validacao.

### 5. Classe principal e grande demais para manutencao segura

Evidencia:
- O arquivo principal concentra bootstrap, assets, settings, REST, AJAX, renderizacao, POST, e-mail e utilitarios: `desi-pet-shower-registration-addon.php:156-188`, `:866-1140`, `:1808-2187`, `:2429-2605`, `:2827-3003`, `:3098-3355`.
- O proprio `README.md` esta desatualizado ao dizer que o arquivo tinha 636 linhas e que WP/PHP minimos eram 6.0/7.4.

Correcao no plano:
- Arquivo principal deve virar bootstrap fino.
- Criar `includes/` com classes de responsabilidade unica.
- Preservar nomes publicos somente onde forem contratos.

### 6. Hook de cliente dispara antes dos pets

Evidencia:
- Fluxo publico dispara `dps_registration_after_client_created` em `:2059`, antes da criacao dos pets iniciada em `:2061`.
- Fluxo REST dispara o mesmo hook em `:1086`, antes do loop de pets em `:1088-1123`.

Impacto:
- Compatibilidade atual deve ser preservada, mas integracoes que precisem de tutor + pets completos nao tem um evento canonico pos-registro.

Correcao no plano:
- Manter `dps_registration_after_client_created` exatamente como esta.
- Adicionar novo hook posterior, por exemplo `dps_registration_after_registration_created( $client_id, $pet_ids, $context )`, documentado em `ANALYSIS.md` e `CHANGELOG.md`.

### 7. Ambiente publicado nao bate com o requisito declarado

Evidencia:
- Header do add-on declara `Requires PHP: 8.4`: `desi-pet-shower-registration-addon.php:12`.
- WP-CLI do servidor `desi.pet` reportou PHP `8.2.30`.

Impacto:
- A reescrita deve ser validada no PHP real do servidor antes de publicar, ou o ambiente deve ser elevado para PHP 8.4. O requisito global do repo segue sendo PHP 8.4, mas o runtime atual e um risco operacional confirmado.

## Achados de UX/UI

### Fluxo atual

O fluxo publicado e um wizard de 3 etapas:
1. Tutor.
2. Pets.
3. Preferencias/restricoes e resumo.

Pontos positivos confirmados:
- O formulario renderiza em `/cadastro/`.
- A validacao em branco bloqueia avancar e mostra nome/telefone obrigatorios.
- A navegacao com dados validos avanca ate a etapa 3.
- O resumo e gerado antes do envio.
- O submit fica bloqueado ate confirmar os dados.
- Na sessao autenticada/admin, as opcoes administrativas aparecem e `duplicateCheck` e ativado.
- Nao houve overflow horizontal nos breakpoints `375`, `600`, `840`, `1200` e `1920` durante a inspeção.

Problemas de experiencia:
- A primeira dobra comeca seca demais para publico final; falta uma introducao curta que explique o objetivo sem virar hero decorativo.
- A etapa 1 mistura dados essenciais, redes sociais, autorizacao de foto, endereco e origem em uma mesma massa visual.
- O campo "Cliente" readonly dentro do pet cria ruido para o usuario publico.
- A etapa de preferencias de produto antes do primeiro contato pode aumentar friccao; deve ser opcional, progressiva e claramente ligada a seguranca.
- Emojis no resumo/preferencias/admin criam ruído e nao pertencem ao DPS Signature.
- Modal de duplicata precisa foco preso, retorno de foco e estado visual alinhado ao padrao.
- Estados de erro/sucesso precisam usar o mesmo componente visual do DPS, sem hex inline e sem mensagens dispersas.

## Plano de reescrita integral

### Fase 0 - Congelamento, backup e inventario

1. Confirmar pagina publicada principal:
   - `dps_registration_page_id = 971`
   - `/cadastro-de-clientes-e-pets/` usa `[dps_registration_v2]`
   - `/cadastro/` usa `[dps_registration_v2]`
   - pagina `1555` usa `[dps_registration_form]`
2. Registrar backup dos arquivos do add-on antes de publicar.
3. Criar inventario definitivo de:
   - shortcodes;
   - hooks/filters;
   - options;
   - campos POST;
   - metas de cliente/pet;
   - REST/AJAX;
   - cron;
   - paginas criadas por ativacao.
4. Definir rollback: restaurar pasta `plugins/desi-pet-shower-registration/` anterior e limpar tabela nova de rate events se criada.

### Fase 1 - Nova arquitetura interna

Criar estrutura proposta:

```text
plugins/desi-pet-shower-registration/
├── desi-pet-shower-registration-addon.php
├── includes/
│   ├── class-dps-registration-plugin.php
│   ├── class-dps-registration-assets.php
│   ├── class-dps-registration-form-renderer.php
│   ├── class-dps-registration-form-handler.php
│   ├── class-dps-registration-validator.php
│   ├── class-dps-registration-client-service.php
│   ├── class-dps-registration-pet-service.php
│   ├── class-dps-registration-rate-limiter.php
│   ├── class-dps-registration-message-service.php
│   ├── class-dps-registration-rest-controller.php
│   ├── class-dps-registration-admin-settings.php
│   ├── class-dps-registration-email-service.php
│   └── class-dps-registration-maps-service.php
├── templates/
│   ├── registration-form.php
│   ├── registration-success.php
│   ├── fields-client.php
│   ├── fields-pet.php
│   ├── fields-product-preferences.php
│   └── modal-duplicate.php
└── assets/
    ├── css/registration-addon.css
    └── js/dps-registration.js
```

Regras:
- Bootstrap fino: carregar text domain em `init` prioridade 1 e plugin em `init` prioridade 5.
- Nada de singleton global espalhado alem do root do add-on.
- Renderizacao em templates escapados.
- Processamento POST em handler separado.
- Serviços de cliente/pet sem HTML.

### Fase 2 - Persistencia, seguranca e anti-abuso sem cache

1. Remover todos os `get_transient` e `set_transient`.
2. Criar servico persistente de rate limit:
   - chave por IP hash e contexto;
   - janela calculada por `created_at`;
   - limpeza por rotina administrativa/cron de manutencao, sem cache.
3. Remover fallback de mensagem por transient:
   - usar `DPS_Message_Helper` quando disponivel;
   - fallback por query arg assinado ou armazenamento persistente curto auditavel.
4. Manter nonce, honeypot, reCAPTCHA e `dps_registration_spam_check`.
5. Validar REST com schema de args e respostas consistentes.
6. Redigir entradas em `ANALYSIS.md` para nova tabela/servico, se criado.

### Fase 3 - UX e markup DPS Signature

Novo fluxo recomendado:

1. **Tutor**
   - Nome e WhatsApp como essenciais.
   - CPF/e-mail/data de nascimento como complementares.
   - Redes sociais em bloco opcional ou secundario.
2. **Endereco e origem**
   - Endereco como `input` compativel com Places.
   - Complemento/observacoes separado em `textarea`.
   - "Como nos conheceu?" com opcoes orientadas ou texto curto.
3. **Pets**
   - Um pet por bloco estrutural, sem card macio.
   - Nome, especie, porte e sexo como essenciais.
   - Raca/peso/pelagem/cor/nascimento/cuidados como complementares.
4. **Seguranca, preferencias e revisao**
   - Preferencias de shampoo/perfume/aderecos como opcionais.
   - Restricoes e alergias com destaque por seguranca.
   - Confirmacao final com resumo compacto.

Regras visuais:
- Seguir `docs/visual/` como fonte de verdade DPS Signature.
- Sem M3/Material, sem template SaaS, sem hero decorativo dentro do formulario.
- Base em `paper`/`bone`; identidade em `petrol`; estados em `action`, `warning`, `danger`.
- Raio `0px` por padrao; `2px-4px` apenas em controles pequenos.
- Sem pill, glow, sombra pesada ou emojis estruturais.
- Labels sempre visiveis; placeholder apenas auxiliar.
- Foco visivel e area de toque segura.

### Fase 4 - JavaScript reescrito como controlador de estado

1. Criar estado unico do wizard:
   - etapa atual;
   - pets;
   - preferencias;
   - confirmacao;
   - flags de reCAPTCHA/duplicata.
2. Validacoes client-side devem espelhar o PHP, sem substituir backend.
3. Remover CSS inline e hex hardcoded.
4. Remover markup grande de `innerHTML` sempre que houver alternativa por template.
5. Usar loader unico de Google Places:
   - `data-dps-address-autocomplete`;
   - `data-dps-google-api-key`;
   - `data-dps-lat-target`;
   - `data-dps-lng-target`.
6. Modal de duplicata:
   - foco preso;
   - Escape fecha;
   - retorno de foco ao botao original;
   - `aria-describedby`;
   - botoes DPS Signature.

### Fase 5 - REST, admin e e-mails

1. REST:
   - manter `/dps/v1/register`;
   - validar payload por schema;
   - retornar codigos previsiveis;
   - manter rate limit persistente.
2. Admin:
   - preservar submenu em `desi-pet-shower`;
   - manter capability `manage_options`;
   - separar settings de API, reCAPTCHA, e-mail e limite.
3. E-mail:
   - manter confirmacao e reminder;
   - revisar copy para cliente final;
   - testar `ajax_send_test_email`.
4. Hooks:
   - manter existentes;
   - adicionar hook pos-registro completo se necessario.

### Fase 6 - Documentacao e migracao

Atualizar:
- `ANALYSIS.md` se houver nova tabela, novo hook, nova classe publica ou nova assinatura.
- `CHANGELOG.md` por mudanca user-facing.
- `plugins/desi-pet-shower-registration/README.md` porque esta desatualizado em requisitos, tamanho e option key.
- `docs/screenshots/YYYY-MM-DD/SCREENSHOTS_YYYY-MM-DD.md` com antes/depois e breakpoints.

### Fase 7 - Validacao obrigatoria

Local:
- `php -l plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`
- `php -l` em todos os novos PHP de `includes/` e `templates/`
- `node --check plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
- `git diff --check`

Servidor/publicado:
- Criar usuario temporario via WP-CLI para sessao autenticada.
- Validar publico e admin em:
  - `375`
  - `600`
  - `840`
  - `1200`
  - `1920`
- Validar:
  - render do shortcode canonico e alias V2;
  - erro em branco;
  - avanco completo do wizard sem envio;
  - admin options;
  - modal de duplicata;
  - Google Places sem erro de console DPS;
  - reCAPTCHA quando habilitado;
  - POST real somente com dados de teste e limpeza documentada;
  - REST com API key valida/invalida;
  - confirmacao de e-mail.

Gates de conclusao:
- Zero `get_transient`/`set_transient` no add-on.
- Zero callbacks paralelos de Maps no formulario.
- Zero erro de console DPS na pagina de cadastro.
- Sem overflow horizontal nos cinco breakpoints.
- Screenshots completos salvos no registro do dia.
- Conta temporaria criada para teste removida no fechamento.

### Fase 8 - Deploy, commit e rollback

1. Commit isolado dos arquivos do Cadastro.
2. Push para `origin/main` ou branch acordada.
3. Upload via SSH apenas dos arquivos alterados.
4. `php -l` remoto nos PHP publicados.
5. Smoke publicado pos-deploy.
6. Rollback pronto:
   - restaurar backup da pasta do add-on;
   - reverter commit;
   - limpar artefatos de teste;
   - desativar nova tabela/rotina se necessario.

## Evidencia coletada nesta auditoria

Arquivos:
- `docs/screenshots/2026-04-25/SCREENSHOTS_2026-04-25.md`
- `docs/screenshots/2026-04-25/cadastro-audit-admin-375.png`
- `docs/screenshots/2026-04-25/cadastro-audit-admin-600.png`
- `docs/screenshots/2026-04-25/cadastro-audit-admin-840.png`
- `docs/screenshots/2026-04-25/cadastro-audit-admin-1200.png`
- `docs/screenshots/2026-04-25/cadastro-audit-admin-1920.png`
- `docs/screenshots/2026-04-25/cadastro-audit-runtime-check.json`

Resultados:
- Sessao temporaria autenticada criada via WP-CLI e removida ao final.
- Formulario renderizou em `/cadastro/`.
- Variante autenticada exibiu opcoes administrativas.
- `duplicateCheck` estava ativo para admin.
- Validacao em branco bloqueou etapa 1 e exibiu erros esperados.
- Fluxo preenchido avancou para etapa 3, gerou preferencias/resumo e liberou submit apenas apos confirmacao.
- Breakpoints `375`, `600`, `840`, `1200` e `1920` nao apresentaram overflow horizontal na coleta.
- Google Places apresentou erro funcional por `textarea` no campo de endereco.

## Execucao da implementacao - 2026-04-25

A primeira entrega de reescrita integral foi executada com preservacao dos contratos externos. O modulo ainda pode evoluir para separacao completa em renderers/handlers/services, mas a superficie critica do formulario publicado deixou de depender de remendos visuais e de transients.

Arquivos centrais alterados:
- `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`
- `plugins/desi-pet-shower-registration/includes/class-dps-registration-storage.php`
- `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
- `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
- `plugins/desi-pet-shower-registration/uninstall.php`
- `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js`
- `plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php`

Decisoes tomadas:
1. A persistencia propria foi implementada em `dps_registration_events`, porque o Cadastro precisava manter rate limit e mensagens de feedback sem `get_transient`/`set_transient`.
2. A etapa de preferencias de produtos foi mantida como coleta progressiva e opcional; o bloqueio do submit permanece vinculado apenas a revisao/confirmacao final.
3. O Google Places ficou no `Autocomplete` legado corrigido por compatibilidade operacional, mas agora usa o loader compartilhado `DPSSignatureForms` com `loading=async`. A migracao para `PlaceAutocompleteElement` segue como melhoria futura controlada.
4. A publicacao foi validada no PHP real do servidor (`8.2.30`) por compatibilidade operacional temporaria, sem alterar o requisito declarado do projeto.
5. O campo de indicacao renderizado pelo Loyalty no hook `dps_registration_after_fields` foi corrigido porque o erro de encoding era visivel dentro do Cadastro publicado.

Evidencia final:
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-375.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-600.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-840.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-1200.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-1920.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-admin-flow-1200.png`
- `docs/screenshots/2026-04-25/cadastro-implementation-runtime-check.json`

Resultados:
- zero `get_transient`/`set_transient` no Cadastro;
- endereco publicado renderizado como `INPUT`;
- `DPSSignatureForms` e `DPSRegistration` presentes no runtime;
- `duplicateCheck` ativo na sessao admin temporaria;
- validacao em branco permaneceu na etapa 1 e exibiu erros esperados;
- clone de pet preservou indices `pet_aggressive[0]` e `pet_aggressive[1]`;
- etapa 3 gerou preferencias/resumo para dois pets e liberou submit somente apos confirmacao;
- breakpoints `375`, `600`, `840`, `1200` e `1920` sem overflow horizontal;
- aviso residual do Google sobre `Autocomplete` legado foi registrado como deprecacao de fornecedor, sem `InvalidValueError` DPS.
