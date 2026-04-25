# Cadastro - teste funcional real e auditoria UI DPS Signature

## Escopo

- Ambiente: `https://desi.pet/cadastro/`
- Data: 2026-04-25
- Sessao: usuario temporario WP criado/renovado via WP-CLI para validar a variante autenticada/admin.
- Fonte visual obrigatoria: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`, `docs/visual/VISUAL_STYLE_GUIDE.md` e `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`.

## Teste pratico funcional em ambiente real

Resultado: aprovado.

Fluxo executado no WordPress publicado:

- login com usuario temporario admin;
- renderizacao do Cadastro em `375`, `600`, `840`, `1200` e `1920`;
- validacao de campos vazios na etapa 1;
- foco acessivel no primeiro campo invalido (`client_name`);
- preenchimento do tutor;
- rascunho opt-in salvo e restaurado;
- criacao de dois pets por clone dinamico;
- geracao de preferencias e resumo;
- confirmacao final;
- envio real do formulario;
- sucesso em `https://desi.pet/cadastro-de-clientes-e-pets/?registered=1`;
- remocao dos posts reais de QA criados no teste.

Evidencia:

- `docs/screenshots/2026-04-25/cadastro-10melhorias-runtime-check.json`
- `docs/screenshots/2026-04-25/cadastro-ui-audit-runtime-check.json`

Limpeza confirmada:

- `Cliente Codex Real Cadastro 20260425162825`
- `Luna Codex 20260425162825`
- `Thor Codex 20260425162825`
- Resultado WP-CLI: `remaining: 0`

## Auditoria UI DPS Signature

Resultado: aprovado com ajuste aplicado.

O ajuste aplicado nesta rodada foi de microcopy: textos novos que estavam sem acento foram normalizados para portugues premium e correto, sem alterar contratos do formulario.

Evidencias de runtime:

- Titulo: `Tutor e pets em um único cadastro`
- Legenda: `* Campos obrigatórios`
- Grupos: `Essenciais`, `Endereço e origem`
- Disclosures: `Dados complementares do tutor`, `Detalhes e cuidados opcionais`
- Fontes computadas: `Sora` para titulo e `Manrope` para corpo/UI.
- Geometria computada:
  - wizard: `0px`;
  - botoes: `0px`;
  - disclosures: `0px`;
  - inputs: `2px`.
- Cores computadas:
  - CTA principal: `rgb(23, 48, 66)` (`petrol`);
  - superficie do wizard: `rgb(255, 253, 248)`.
- Google Places:
  - `data-dps-places-mode="place-autocomplete-element"`;
  - apenas 1 widget visual por campo;
  - input canonico `client_address` escondido e sincronizado.
- Responsividade:
  - sem overflow horizontal nos cinco breakpoints.
  - os unicos elementos com `scrollWidth > clientWidth` foram campos tecnicos intencionalmente ocultos (`honeypot` e live region).

Conclusao visual: o Cadastro esta coerente com DPS Signature: geometria reta, paleta contida, tipografia normativa, densidade controlada, hierarquia clara, rascunho discreto e etapas compreensiveis.

## Viabilidade da foto do pet

Viavel e recomendada, com risco medio por envolver upload publico de arquivo.

Evidencia de compatibilidade:

- O CPT `dps_pet` hoje suporta apenas `title` em `plugins/desi-pet-shower-base/desi-pet-shower-base.php`.
- O Cadastro ja cria `dps_pet` por indice e salva metadados por pet em `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`.
- O backup ja reconhece o metadado `pet_photo_id` em `plugins/desi-pet-shower-backup/desi-pet-shower-backup-addon.php`, o que indica um contrato de anexo compatível com o ecossistema.

Arquitetura recomendada:

- Campo opcional por pet: `pet_photo[]`, dentro de `Detalhes e cuidados opcionais`.
- Formulario com `enctype="multipart/form-data"`.
- Upload somente apos validacao completa do cadastro, usando APIs nativas do WordPress.
- Validar MIME real e extensao com `wp_check_filetype_and_ext`.
- Aceitar apenas `jpg`, `jpeg`, `png` e `webp`.
- Limitar tamanho inicial a `5 MB`.
- Salvar o attachment na Media Library com `post_parent` do pet.
- Gravar `pet_photo_id` e opcionalmente `_thumbnail_id` no `dps_pet`.
- Nao salvar foto em rascunho, localStorage, base64 ou tabela customizada.
- Se qualquer pet falhar apos upload, remover attachments criados naquela tentativa.

UX recomendada:

- Foto opcional, nunca obrigatoria.
- Texto: `Foto de perfil do pet`.
- Ajuda curta: `Ajuda a equipe a identificar o pet no atendimento.`
- Preview local antes do envio.
- Visual quadrado, borda reta, superficie `paper`, acento `petrol`, sem avatar circular para manter DPS Signature.

Gates antes de implementar:

- validar upload real com imagem pequena;
- validar rejeicao de PDF/executavel/imagem acima do limite;
- validar dois pets com fotos diferentes;
- validar limpeza ao remover dados de QA;
- validar exibicao futura no perfil/admin/portal onde o pet aparece.
