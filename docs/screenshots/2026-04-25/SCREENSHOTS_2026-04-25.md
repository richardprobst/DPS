# Screenshots 2026-04-25 - Cadastro Add-on DPS Signature

## Contexto

- Objetivo: registrar baseline de auditoria e evidencia final da reescrita do formulario publico de Cadastro para o padrao DPS Signature.
- Ambiente: `https://desi.pet/cadastro/`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a variante autenticada/admin; a remocao da conta temporaria segue pendente de confirmacao humana por ser exclusao de usuario em ambiente publicado.
- Referencia visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: CSS legado, radius/elevacao fora do gate DPS Signature, callback proprio de Google Maps, campo de endereco como `textarea`, transients no rate limit/mensagens e indice incorreto em `pet_aggressive` clonado.
- Depois: shell/formulario refeito em DPS Signature, endereco como `input`, loader compartilhado `DPSSignatureForms`, persistencia propria sem transients, rascunho opt-in, `PlaceAutocompleteElement` com fallback legado, controlador JS reescrito, foto opcional do pet com preview reto, autorizacao obrigatoria de publicacao de foto em destaque antes do botao Proximo e formulario publicado validado nos breakpoints oficiais.

Arquivos de codigo alterados:
- `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`
- `plugins/desi-pet-shower-registration/includes/class-dps-registration-storage.php`
- `plugins/desi-pet-shower-registration/includes/class-dps-registration-draft-service.php`
- `plugins/desi-pet-shower-registration/includes/class-dps-registration-maintenance.php`
- `plugins/desi-pet-shower-registration/includes/class-dps-registration-ux.php`
- `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
- `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
- `plugins/desi-pet-shower-registration/uninstall.php`
- `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js`
- `plugins/desi-pet-shower-loyalty/desi-pet-shower-loyalty.php`
- `docs/qa/cadastro-10-implementacoes-smoke-2026-04-25.mjs`

## Capturas de auditoria

- `./cadastro-audit-admin-375.png` - Baseline autenticado/admin em 375px.
- `./cadastro-audit-admin-600.png` - Baseline autenticado/admin em 600px.
- `./cadastro-audit-admin-840.png` - Baseline autenticado/admin em 840px.
- `./cadastro-audit-admin-1200.png` - Baseline autenticado/admin em 1200px.
- `./cadastro-audit-admin-1920.png` - Baseline autenticado/admin em 1920px.
- `./cadastro-audit-runtime-check.json` - Evidencia funcional da auditoria inicial.

## Capturas de implementacao

- `./cadastro-implementation-admin-375.png` - Cadastro autenticado/admin em 375px.
- `./cadastro-implementation-admin-600.png` - Cadastro autenticado/admin em 600px.
- `./cadastro-implementation-admin-840.png` - Cadastro autenticado/admin em 840px.
- `./cadastro-implementation-admin-1200.png` - Cadastro autenticado/admin em 1200px.
- `./cadastro-implementation-admin-1920.png` - Cadastro autenticado/admin em 1920px.
- `./cadastro-implementation-admin-flow-1200.png` - Fluxo preenchido ate etapa 3 em 1200px.

## Capturas das 10 melhorias finais

- `./cadastro-10melhorias-admin-375.png` - Cadastro autenticado/admin em 375px com rascunho, grupos DPS Signature, indicador de expansao e autorizacao obrigatoria em coluna unica.
- `./cadastro-10melhorias-admin-600.png` - Cadastro autenticado/admin em 600px.
- `./cadastro-10melhorias-admin-840.png` - Cadastro autenticado/admin em 840px.
- `./cadastro-10melhorias-admin-1200.png` - Cadastro autenticado/admin em 1200px.
- `./cadastro-10melhorias-admin-1920.png` - Cadastro autenticado/admin em 1920px.
- `./cadastro-10melhorias-flow-1200.png` - Fluxo preenchido ate etapa 3 com dois pets, resumo, autorizacao de foto refletida e confirmacao.
- `./cadastro-address-contrast-field-1200.png` - Campo Endereco completo com texto digitado e contraste corrigido em 1200px.
- `./cadastro-address-contrast-typed-1200.png` - Captura full-page do campo Endereco completo em foco com texto digitado e sugestoes do Google Places.

## Capturas da foto do pet

- `./cadastro-pet-photo-admin-375.png` - Etapa 2 com upload e preview da foto do pet em 375px.
- `./cadastro-pet-photo-admin-600.png` - Etapa 2 com upload e preview da foto do pet em 600px.
- `./cadastro-pet-photo-admin-840.png` - Etapa 2 com upload e preview da foto do pet em 840px.
- `./cadastro-pet-photo-admin-1200.png` - Etapa 2 com upload e preview da foto do pet em 1200px.
- `./cadastro-pet-photo-admin-1920.png` - Etapa 2 com upload e preview da foto do pet em 1920px.
- `./cadastro-pet-photo-step-1200.png` - Captura do smoke funcional real com a foto selecionada antes do envio.
- `./cadastro-pet-photo-responsive-check.json` - Auditoria visual responsiva da foto do pet sem envio do formulario.
- `./cadastro-pet-photo-wp-meta-check.json` - Verificacao WP-CLI dos metadados da foto salva no pet.

## Evidencia funcional final

- `./cadastro-implementation-runtime-check.json`
- `./cadastro-10melhorias-runtime-check.json`
- `./cadastro-ui-audit-runtime-check.json`
- `./cadastro-pet-photo-responsive-check.json`
- `./cadastro-pet-photo-wp-meta-check.json`

Resumo:
- formulario renderizou nos cinco breakpoints;
- sem overflow horizontal em `375`, `600`, `840`, `1200` e `1920`;
- `#dps-client-address` renderizou como `INPUT`;
- Google Places marcou `data-dps-places-ready="1"` e `data-dps-places-mode="place-autocomplete-element"` nos cinco breakpoints finais;
- `DPSSignatureForms` e `DPSRegistration` estavam disponiveis no runtime;
- `duplicateCheck` ficou ativo na sessao admin temporaria;
- validacao em branco permaneceu na etapa 1 e exibiu `Informe o nome do tutor.` e `Informe o telefone ou WhatsApp.`;
- foco acessivel apos erro foi para `client_name`;
- microcopy final validada em portugues: `Tutor e pets em um único cadastro`, `Campos obrigatórios`, `Endereço e origem`;
- auditoria computada confirmou `Sora` nos titulos, `Manrope` no corpo/UI, wizard/botoes/disclosures com `0px` de raio e inputs com `2px`;
- topo do wizard validado sem contador visual duplicado: `stepCounterExists: false` e `progressTopText: Passo 1 de 3` nos cinco breakpoints;
- area `Dados complementares do tutor` validada com indicador explicito `Expandir`/`Recolher` e sem contador redundante;
- autorizacao de publicacao de foto movida para um fieldset obrigatorio acima do botao `Proximo`, com opcoes destacadas `Autorizo` e `Nao autorizo`;
- opcoes administrativas validadas como painel premium DPS Signature com 2 cards suportados pelo backend: `Ativar imediatamente` e `Enviar boas-vindas`;
- autorizacao de foto refinada para cabecalho `Uso de imagem` e cards de decisao obrigatorios, mantendo `client_photo_auth` com 2 radios required;
- validacao em branco passou a bloquear avanco tambem com `Informe se autoriza ou nao autoriza a publicacao da foto do pet.`;
- runtime publicado confirmou `photoAuthFieldExists: true`, `photoAuthOptions: 2`, `photoAuthRequired: true` e `summaryHasPhotoAuth: true`;
- campo Telefone/WhatsApp validado sem o texto fixo `Formato: (DDD) numero com 8 ou 9 digitos`; `phoneHintExists: false` nos cinco breakpoints e grid essencial com menor espaco vertical entre linhas;
- campo Endereco completo validado com `PlaceAutocompleteElement` em superficie clara: `placeElementColor: rgb(17, 22, 28)`, `placeElementBackground: rgb(255, 255, 255)` e `placeElementColorScheme: light` nos cinco breakpoints;
- captura dedicada confirmou endereco digitado legivel no componente Google Places, sem fundo escuro e sem texto apagado;
- rascunho opt-in salvou, exibiu `Rascunho salvo.`, restaurou o nome do tutor e foi limpo apos envio real;
- clone de pet gerou dois fieldsets, legends `Pet 1` e `Pet 2`, e nomes `pet_aggressive[0]` / `pet_aggressive[1]`;
- etapa 3 gerou preferencias e resumo para dois pets;
- submit ficou desabilitado antes da confirmacao e habilitado apos marcar a confirmacao;
- envio real controlado retornou sucesso em `https://desi.pet/cadastro-de-clientes-e-pets/?registered=1`;
- os posts reais de QA criados pelo smoke final `20260425162825` foram removidos por titulo exato via WP-CLI e a verificacao retornou `remaining: 0`;
- campo de indicacao do Loyalty apareceu como `Código de indicação`/`Seu código, se tiver` no runtime, sem mojibake visual;
- foto opcional do pet apareceu na etapa 2, dentro de `Detalhes e cuidados opcionais`, com preview quadrado, borda fina e cantos `0px`;
- a auditoria visual da foto passou em `375`, `600`, `840`, `1200` e `1920`, sem overflow horizontal e com label associado ao input;
- o smoke real `20260425PETPHOTO171126` enviou uma foto PNG para o primeiro pet, manteve o segundo pet sem foto e confirmou `pet_photo_id`, `pet_photos` e `_thumbnail_id` apontando para o attachment `1836`;
- os posts reais do smoke de foto `[1834, 1835, 1837]` e o attachment `[1836]` foram removidos por WP-CLI; a verificacao final retornou `remaining: 0`.

## Observacoes

- A URL do Google Maps registrada na evidencia usa `loading=async` e a callback compartilhada `dpsSignatureGooglePlacesReady`.
- A entrega final usa `PlaceAutocompleteElement` quando disponivel e preserva fallback legado para compatibilidade operacional. Nao houve `InvalidValueError` nem aviso de `Autocomplete` legado nos smokes finais.
- Requests com `key`, `token`, `login` ou `dps_token` foram redigidos no JSON de evidencia.
