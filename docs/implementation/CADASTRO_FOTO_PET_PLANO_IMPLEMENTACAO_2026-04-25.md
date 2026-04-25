# Plano de implementação - foto do pet no Cadastro

**Data:** 2026-04-25  
**Superfície:** formulário público `[dps_registration_form]` / `[dps_registration_v2]`  
**Padrão visual:** DPS Signature (`docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md`, `docs/visual/VISUAL_STYLE_GUIDE.md`, `dps-design-tokens.css`)  
**Status:** implementado nesta rodada

## Objetivo

Permitir que o tutor envie uma foto opcional de cada pet durante o cadastro público, para que a imagem fique vinculada ao perfil do pet e ajude a equipe na identificação operacional.

## Contratos preservados

- Shortcodes preservados: `[dps_registration_form]` e `[dps_registration_v2]`.
- Nonce preservado: `dps_reg_nonce`.
- Action preservada: `dps_reg_action=save_registration`.
- Campos existentes preservados.
- Novo campo opcional: `pet_photo[]`.
- Metadados usados no pet:
  - `pet_photo_id`: attachment principal do perfil;
  - `pet_photos`: array de attachments para compatibilidade com galeria;
  - `_thumbnail_id`: thumbnail do WordPress para interoperabilidade.

## Plano técnico

1. Renderizar o upload dentro de `Detalhes e cuidados opcionais`, sem competir com nome, espécie, porte e sexo.
2. Adicionar `enctype="multipart/form-data"` ao formulário público.
3. Aceitar apenas `image/jpeg`, `image/png` e `image/webp`.
4. Limitar cada foto a 5 MB.
5. Validar os uploads antes de criar cliente/pets para evitar posts órfãos em caso de arquivo inválido.
6. Revalidar o arquivo depois de `wp_handle_upload()` com `wp_check_filetype_and_ext()`.
7. Salvar a imagem pela Media Library, com `post_parent` apontando para o `dps_pet`.
8. Gravar `pet_photo_id`, `pet_photos` e `_thumbnail_id`.
9. Reverter cliente, pets e anexos criados se uma falha tardia ocorrer durante o upload.
10. No frontend, exibir preview local por `URL.createObjectURL`, sem gravar a imagem em rascunho, localStorage, base64 ou cache.
11. Reindexar IDs/labels/hints ao adicionar ou remover pets.
12. Exibir no resumo final o nome do arquivo selecionado.
13. Registrar screenshots e smoke real no ambiente publicado.

## Decisões de UX/UI

- Foto é opcional e fica dentro do disclosure de detalhes para não atrasar o cadastro essencial.
- Preview quadrado, com borda fina e cantos retos, seguindo DPS Signature.
- O upload usa hint direto de formato e tamanho, sem linguagem técnica desnecessária.
- O resumo final mostra `Foto do perfil` apenas quando há arquivo selecionado.
- O rascunho continua salvando dados textuais, mas não tenta persistir arquivos.

## Segurança

- Nonce e demais proteções do cadastro foram preservados.
- Upload só é aceito depois das validações anti-spam existentes.
- MIME real e extensão são validados com `wp_check_filetype_and_ext()`.
- O arquivo é salvo pela API de upload do WordPress.
- Não há cache, transients ou armazenamento temporário customizado para a imagem.
- Em falha tardia, o cadastro criado na transação é removido para evitar resíduos.

## Validação prevista

- `php -l` no add-on de Cadastro.
- `node --check` no JS do Cadastro e no smoke.
- Smoke Playwright autenticado no site publicado.
- Verificação via WP-CLI dos metadados `pet_photo_id`, `pet_photos` e `_thumbnail_id`.
- Capturas completas nos breakpoints DPS: `375`, `600`, `840`, `1200`, `1920`.
- Captura específica da etapa de foto do pet.

