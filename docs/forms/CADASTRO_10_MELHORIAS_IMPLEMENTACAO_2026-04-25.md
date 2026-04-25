# Cadastro - Plano de implementacao das 10 melhorias

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** elevar o formulario publico de Cadastro para uma versao mais modular, acessivel, responsiva e operacionalmente verificavel, mantendo todos os contratos externos do add-on.

**Status final:** implementado, publicado no WordPress `desi.pet`, validado nos cinco breakpoints DPS Signature e testado com envio real controlado. Os dados reais de QA foram removidos por titulo exato via WP-CLI e a verificacao final retornou `remaining: 0`.

**Architecture:** a entrega preserva o plugin canonico `desi-pet-shower-registration` e adiciona servicos focados em UX/renderizacao auxiliar, rascunho persistente e manutencao. A camada publica continua usando o shortcode atual, mas passa a ter grupos de campos mais claros, rascunho opt-in persistente, Google Places novo quando disponivel e fallback legado seguro.

**Tech Stack:** WordPress/PHP, WP AJAX, tabela persistente `dps_registration_events`, JavaScript sem dependencia nova, Google Maps JavaScript Places, CSS DPS Signature.

---

## Fontes de verdade e contratos

- Visual: `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`.
- Tokens: `plugins/desi-pet-shower-base/assets/css/dps-design-tokens.css`.
- Codigo canonico: `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`.
- Google Places: documentacao oficial de migracao para `PlaceAutocompleteElement` e widget novo.

Contratos que nao podem quebrar:
- shortcodes `[dps_registration_form]` e `[dps_registration_v2]`;
- POST action `dps_reg_action=save_registration`;
- nonce `dps_reg_nonce`;
- campos `client_*`, `pet_*[]`, `pet_aggressive[index]`;
- AJAX `dps_registration_check_duplicate`;
- REST `POST /wp-json/dps/v1/register`;
- hooks `dps_registration_spam_check`, `dps_registration_after_client_created`, `dps_registration_after_fields`, filtro `dps_registration_agenda_url`.

## Arquivos previstos

- Criar: `plugins/desi-pet-shower-registration/includes/class-dps-registration-draft-service.php`
  - Responsavel por token de rascunho, sanitizacao do payload, salvar/carregar/limpar rascunhos persistentes na tabela do add-on.
- Criar: `plugins/desi-pet-shower-registration/includes/class-dps-registration-ux.php`
  - Responsavel por blocos auxiliares de UI do formulario: introducao, grupos de contexto, rascunho, live region e detalhes opcionais.
- Criar: `plugins/desi-pet-shower-registration/includes/class-dps-registration-maintenance.php`
  - Responsavel por agendar e executar limpeza de registros expirados de `dps_registration_events`.
- Modificar: `plugins/desi-pet-shower-registration/includes/class-dps-registration-storage.php`
  - Adicionar metodos genericos de payload persistente para rascunho.
- Modificar: `plugins/desi-pet-shower-registration/desi-pet-shower-registration-addon.php`
  - Carregar novas classes, registrar AJAX de rascunho, usar UX helpers, limpar rascunho apos cadastro real, agendar manutencao.
- Modificar: `plugins/desi-pet-shower-base/assets/js/dps-signature-forms.js`
  - Inicializar `PlaceAutocompleteElement` quando disponivel, com fallback para `google.maps.places.Autocomplete`.
- Modificar: `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
  - Melhorar acessibilidade do wizard, foco em erro, restore/save de rascunho, detalhes opcionais e smoke de envio real controlado.
- Modificar: `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
  - Layout DPS Signature para introducao, grupos opcionais, rascunho, live region, PlaceAutocompleteElement e mobile.
- Modificar: `ANALYSIS.md`, `CHANGELOG.md`, `docs/screenshots/2026-04-25/SCREENSHOTS_2026-04-25.md`.

## Tarefas

### Task 1: Base de arquitetura e manutencao

- [x] Criar `DPS_Registration_UX` com helpers pequenos e sem estado.
- [x] Criar `DPS_Registration_Maintenance` com hook `dps_registration_events_cleanup` e limpeza diaria.
- [x] Registrar as classes no bootstrap do Cadastro.
- [x] Validar com `php -l` nos novos arquivos.

### Task 2: Persistencia de rascunho opt-in

- [x] Estender `DPS_Registration_Storage` com `set_payload()`, `get_latest_payload()` e `delete_bucket_events()`.
- [x] Criar `DPS_Registration_Draft_Service` para whitelist de campos do Cadastro.
- [x] Adicionar AJAX publico `dps_registration_save_draft` e `dps_registration_clear_draft` com nonce.
- [x] Localizar `draft` em `dpsRegistrationData` sem expor dados quando nao ha token de rascunho.
- [x] Limpar rascunho apos POST real bem-sucedido.

### Task 3: UX da etapa Tutor

- [x] Inserir introducao curta no topo do formulario, sem hero e sem texto sobre implementacao.
- [x] Dividir campos do tutor em grupos: essenciais, endereco/origem e complementares.
- [x] Manter `name`, `id` e `required` dos campos existentes.
- [x] Validar layout em 375px e 1200px.

### Task 4: UX dos pets

- [x] Remover ruido do campo `Cliente` readonly do fluxo publico e manter ownership apenas por backend.
- [x] Separar campos essenciais do pet dos detalhes opcionais em disclosure acessivel.
- [x] Manter os arrays `pet_*[]` e os nomes `pet_aggressive[index]`.
- [x] Garantir que o clone preserva disclosure, datalist e indices.

### Task 5: Google Places novo com fallback

- [x] Atualizar `dps-signature-forms.js` para tentar `google.maps.importLibrary('places')`.
- [x] Criar `PlaceAutocompleteElement` quando a classe existir.
- [x] Manter o input canonico `client_address` sincronizado para preservar POST e validacoes.
- [x] Preencher `client_lat` e `client_lng` quando `location` estiver disponivel.
- [x] Se o widget novo falhar, usar `Autocomplete` legado corrigido.

### Task 6: Acessibilidade do wizard

- [x] Adicionar live region dedicada para trocas de etapa e erros.
- [x] Focar o primeiro campo invalido apos validacao.
- [x] Preservar retorno de foco no modal de duplicata.
- [x] Atualizar `aria-current`, `aria-hidden`, `aria-describedby` e progressbar.

### Task 7: Rascunho no frontend

- [x] Adicionar controle opt-in `Salvar rascunho por 7 dias`.
- [x] Salvar via AJAX com debounce somente apos opt-in.
- [x] Mostrar prompt de restauracao quando houver rascunho persistente.
- [x] Restaurar tutor, pets, preferencias e checkboxes sem enviar formulario.
- [x] Permitir descartar rascunho via AJAX.

### Task 8: Mensagens centralizadas

- [x] Criar mapa `CONFIG.MESSAGES` no JS.
- [x] Reutilizar mensagens em validacao step-by-step e submit final.
- [x] Manter mensagens PHP existentes para backend.
- [x] Garantir que strings visiveis nao contenham emojis nem mojibake.

### Task 9: Envio real controlado para QA

- [x] Criar script local Playwright que preenche e submete cadastro admin de teste.
- [x] Validar criacao de tutor e pets pelo WordPress publicado.
- [x] Registrar IDs criados no JSON de QA.
- [x] Remover posts/metas de teste ao final via WP-CLI.

### Task 10: Evidencia, deploy, commit e push

- [x] Rodar `php -l` nos PHP alterados.
- [x] Rodar `node --check` nos JS alterados.
- [x] Confirmar zero transients/cache no Cadastro.
- [x] Publicar arquivos via SSH/SFTP no WordPress.
- [x] Validar publicado nos breakpoints `375`, `600`, `840`, `1200`, `1920`.
- [x] Atualizar `SCREENSHOTS_2026-04-25.md`, `ANALYSIS.md` e `CHANGELOG.md`.
- [x] Commitar e fazer push na branch `codex/cadastro-dps-signature-implementation`.

## Gates de aceite

- Nenhum contrato externo do Cadastro quebrado.
- Sem `get_transient`, `set_transient`, `delete_transient` ou `wp_cache_*` no Cadastro.
- Rascunho so salva apos opt-in e usa persistencia da tabela propria, nao browser storage.
- Google Places usa widget novo quando disponivel e fallback legado sem `InvalidValueError`.
- Sem overflow horizontal nos cinco breakpoints.
- Envio real de teste cria tutor/pets e limpa os dados ao final.
- Usuario temporario de teste deve ser removido somente com confirmacao humana por ser exclusao de conta.

## Ajustes encontrados no caminho

- O loader de Google Places recebeu trava `data-dps-places-initializing` para impedir multiplos widgets quando `DPSSignatureForms.init()` e inicializadores especificos rodam em paralelo.
- O backend do Cadastro passou a honrar `dps_admin_send_welcome` quando `dps_admin_skip_confirmation` esta ativo, evitando envio de boas-vindas em cadastros admin/QA quando o checkbox esta desmarcado.
- A limpeza de eventos expirados ficou concentrada no cron diario `dps_registration_events_cleanup`; a criacao/verificacao da tabela nao executa limpeza em todo request.
