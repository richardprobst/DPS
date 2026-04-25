# Screenshots 2026-04-25 - Cadastro Add-on DPS Signature

## Contexto

- Objetivo: registrar baseline de auditoria e evidencia final da reescrita do formulario publico de Cadastro para o padrao DPS Signature.
- Ambiente: `https://desi.pet/cadastro/`, WordPress publicado.
- Sessao: usuario temporario criado via WP-CLI com role administrator para validar a variante autenticada/admin; a remocao da conta temporaria segue pendente de confirmacao humana por ser exclusao de usuario em ambiente publicado.
- Referencia visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.

## Antes/Depois

- Antes: CSS legado, radius/elevacao fora do gate DPS Signature, callback proprio de Google Maps, campo de endereco como `textarea`, transients no rate limit/mensagens e indice incorreto em `pet_aggressive` clonado.
- Depois: shell/formulario refeito em DPS Signature, endereco como `input`, loader compartilhado `DPSSignatureForms`, persistencia propria sem transients, rascunho opt-in, `PlaceAutocompleteElement` com fallback legado, controlador JS reescrito e formulario publicado validado nos breakpoints oficiais.

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

- `./cadastro-10melhorias-admin-375.png` - Cadastro autenticado/admin em 375px com rascunho e grupos DPS Signature.
- `./cadastro-10melhorias-admin-600.png` - Cadastro autenticado/admin em 600px.
- `./cadastro-10melhorias-admin-840.png` - Cadastro autenticado/admin em 840px.
- `./cadastro-10melhorias-admin-1200.png` - Cadastro autenticado/admin em 1200px.
- `./cadastro-10melhorias-admin-1920.png` - Cadastro autenticado/admin em 1920px.
- `./cadastro-10melhorias-flow-1200.png` - Fluxo preenchido ate etapa 3 com dois pets, resumo e confirmacao.

## Evidencia funcional final

- `./cadastro-implementation-runtime-check.json`
- `./cadastro-10melhorias-runtime-check.json`

Resumo:
- formulario renderizou nos cinco breakpoints;
- sem overflow horizontal em `375`, `600`, `840`, `1200` e `1920`;
- `#dps-client-address` renderizou como `INPUT`;
- Google Places marcou `data-dps-places-ready="1"` e `data-dps-places-mode="place-autocomplete-element"` nos cinco breakpoints finais;
- `DPSSignatureForms` e `DPSRegistration` estavam disponiveis no runtime;
- `duplicateCheck` ficou ativo na sessao admin temporaria;
- validacao em branco permaneceu na etapa 1 e exibiu `Informe o nome do tutor.` e `Informe o telefone ou WhatsApp.`;
- foco acessivel apos erro foi para `client_name`;
- rascunho opt-in salvou, exibiu `Rascunho salvo.`, restaurou o nome do tutor e foi limpo apos envio real;
- clone de pet gerou dois fieldsets, legends `Pet 1` e `Pet 2`, e nomes `pet_aggressive[0]` / `pet_aggressive[1]`;
- etapa 3 gerou preferencias e resumo para dois pets;
- submit ficou desabilitado antes da confirmacao e habilitado apos marcar a confirmacao;
- envio real controlado retornou sucesso em `https://desi.pet/cadastro-de-clientes-e-pets/?registered=1`;
- os posts reais de QA criados pelos smokes finais foram removidos por titulo exato via WP-CLI e a verificacao retornou `remaining: 0`;
- campo de indicacao do Loyalty apareceu como `Código de indicação`/`Seu código, se tiver` no runtime, sem mojibake visual.

## Observacoes

- A URL do Google Maps registrada na evidencia usa `loading=async` e a callback compartilhada `dpsSignatureGooglePlacesReady`.
- A entrega final usa `PlaceAutocompleteElement` quando disponivel e preserva fallback legado para compatibilidade operacional. Nao houve `InvalidValueError` nem aviso de `Autocomplete` legado nos smokes finais.
- Requests com `key`, `token`, `login` ou `dps_token` foram redigidos no JSON de evidencia.
