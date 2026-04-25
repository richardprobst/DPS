# QA - foto do pet no Cadastro

**Data:** 2026-04-25  
**Ambiente:** WordPress publicado em `https://desi.pet/cadastro-de-clientes-e-pets/`  
**Sessao:** usuario temporario autenticado via WP-CLI (`dps_codex_cadastro_1777125838`)  
**Padrao visual:** DPS Signature (`docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`, `docs/visual/VISUAL_STYLE_GUIDE.md`)

## Escopo validado

- Campo opcional `pet_photo[]` na etapa 2 do Cadastro.
- Preview local da foto antes do envio.
- Validacao visual responsiva em `375`, `600`, `840`, `1200` e `1920`.
- Envio real de uma foto PNG para o primeiro pet.
- Persistencia da foto como attachment da Media Library, vinculada ao pet.
- Rollback/limpeza dos registros criados pelo smoke.

## Resultado funcional

Smoke real executado com sufixo `20260425PETPHOTO171126`:

- cliente criado: `1834`;
- primeiro pet criado: `1835`;
- attachment da foto: `1836`;
- segundo pet criado sem foto: `1837`;
- redirecionamento de sucesso: `https://desi.pet/cadastro-de-clientes-e-pets/?registered=1`;
- resumo final exibiu a foto selecionada antes do envio.

Metadados confirmados via WP-CLI:

- `pet_photo_id = 1836`;
- `_thumbnail_id = 1836`;
- `pet_photos` com 1 item;
- MIME salvo como `image/png`;
- `post_parent` do attachment apontando para o pet `1835`;
- URL do attachment existente.

Limpeza concluida:

- posts removidos: `[1834, 1835, 1837]`;
- attachment removido: `[1836]`;
- verificacao final: `remaining: 0`.

## Resultado visual

A auditoria visual da area de foto foi executada sem envio do formulario. Evidencia salva em:

- `docs/screenshots/2026-04-25/cadastro-pet-photo-admin-375.png`;
- `docs/screenshots/2026-04-25/cadastro-pet-photo-admin-600.png`;
- `docs/screenshots/2026-04-25/cadastro-pet-photo-admin-840.png`;
- `docs/screenshots/2026-04-25/cadastro-pet-photo-admin-1200.png`;
- `docs/screenshots/2026-04-25/cadastro-pet-photo-admin-1920.png`;
- `docs/screenshots/2026-04-25/cadastro-pet-photo-responsive-check.json`.

Resultado nos cinco breakpoints:

- formulario renderizado;
- etapa ativa: `Passo 2 de 3`;
- disclosure opcional aberto;
- input e label associados;
- preview preenchido;
- `border-radius` do preview e do controle: `0px`;
- fonte do formulario: `Manrope`;
- sem overflow horizontal.

Observacao: o JSON registra elementos do honeypot anti-spam fora do viewport em `overflowingNodes`, mas `hasHorizontalOverflow` permaneceu `false` em todos os breakpoints; nao houve barra horizontal nem corte visual do formulario.

## Comandos de validacao

- `php -l plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`;
- `node --check plugins/desi-pet-shower-registration/assets/js/dps-registration.js`;
- `node --check docs/qa/cadastro-10-implementacoes-smoke-2026-04-25.mjs`;
- smoke Playwright autenticado no runtime publicado;
- WP-CLI remoto para checar os metadados e remover os dados de QA.

## Conclusao

A implementacao esta viavel e validada: a foto fica opcional, nao atrapalha o fluxo essencial, segue DPS Signature visualmente e e persistida no perfil do pet por APIs nativas do WordPress.
