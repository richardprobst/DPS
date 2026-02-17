# desi.pet by PRObst – Client Portal Add-on

Portal de autoatendimento para clientes do pet shop com autenticação por link seguro, histórico de atendimentos, dados de pets, mensagens e integrações opcionais com financeiro/fidelidade.

## Visão geral

O **Client Portal Add-on** adiciona ao ecossistema DPS uma área autenticada para clientes acompanharem seus atendimentos e manterem dados atualizados sem depender de suporte manual no WhatsApp para tarefas rotineiras.

No estado atual do código, o add-on entrega:

- autenticação por token (`magic link`) com sessão segura;
- shortcode principal para renderização do portal;
- criação automática de usuário WordPress para clientes (`dps_cliente`) quando necessário;
- atualização de dados cadastrais do cliente e dos pets;
- histórico de agendamentos, próxima visita e galeria;
- área de mensagens/chat cliente ↔ equipe;
- pedidos de agendamento pelo portal;
- suporte a exportação de agendamento em `.ics`;
- integrações opcionais com Loyalty, Finance, Payment e Tosa Consent.

---

## Identificação do add-on

- **Diretório:** `plugins/desi-pet-shower-client-portal/`
- **Arquivo principal:** `desi-pet-shower-client-portal.php`
- **Classe coordenadora:** `DPS_Client_Portal`
- **Text domain:** `dps-client-portal`
- **Versão atual:** `2.4.3`

---

## Requisitos e dependências

### Requisitos mínimos

- **WordPress:** 6.9+
- **PHP:** 8.4+
- **Plugin base:** `desi.pet by PRObst Base` ativo (`DPS_Base_Plugin`)

### Dependências opcionais (integrações)

- **Finance Add-on:** leitura de pendências financeiras
- **Payment Add-on:** geração de links de pagamento
- **Loyalty Add-on:** pontos, histórico e resgate
- **Tosa Consent Add-on:** URL do termo de consentimento

> Sem o plugin base, o add-on não inicializa e exibe aviso administrativo.

---

## Shortcodes

### `[dps_client_portal]`
Renderiza o portal completo (login + conteúdo autenticado).

### `[dps_client_login]`
Renderiza apenas a tela de acesso/login do portal.

### `[dps_profile_update]`
Renderiza o formulário de atualização de perfil via token de atualização (fluxo administrativo de link temporário).

---

## Fluxo de autenticação (resumo técnico)

1. O cliente recebe um link com `?dps_token=...`.
2. O token é validado por `DPS_Portal_Token_Manager`.
3. Ao validar, o cliente é autenticado por `DPS_Portal_Session_Manager`.
4. O acesso é registrado para auditoria (IP/UA e logs).
5. O portal carrega autenticado sem exigir novo login na mesma requisição.

Fallbacks de autenticação continuam disponíveis para compatibilidade com associação de usuário WordPress (`user_meta dps_client_id`).

---

## Estrutura de código

```text
plugins/desi-pet-shower-client-portal/
├── desi-pet-shower-client-portal.php     # bootstrap e hooks principais
├── includes/
│   ├── class-dps-client-portal.php       # orquestrador do portal
│   ├── class-dps-portal-token-manager.php
│   ├── class-dps-portal-session-manager.php
│   ├── class-dps-portal-admin-actions.php
│   ├── class-dps-portal-profile-update.php
│   ├── class-dps-portal-cache-helper.php
│   ├── class-dps-calendar-helper.php
│   ├── functions-portal-helpers.php
│   └── client-portal/
│       ├── class-dps-portal-data-provider.php
│       ├── class-dps-portal-renderer.php
│       ├── class-dps-portal-actions-handler.php
│       ├── class-dps-portal-ajax-handler.php
│       ├── class-dps-portal-admin.php
│       ├── class-dps-portal-pet-history.php
│       ├── interfaces/
│       └── repositories/
├── assets/
├── templates/
├── HOOKS.md
└── TOKEN_AUTH_SYSTEM.md
```

---

## Dados e persistência

### Tabela customizada

- **`{$wpdb->prefix}dps_portal_tokens`**
  - criada no hook de ativação;
  - usada para controle de tokens de acesso/atualização.

### CPTs utilizados/registrados

- **`dps_cliente`** (base) — cliente
- **`dps_pet`** (base) — pet
- **`dps_agendamento`** (base) — agendamentos
- **`dps_portal_message`** (registrado pelo add-on) — mensagens do portal
- **`dps_appt_request`** (registrado pelo add-on) — pedidos de agendamento

> Nota de nomenclatura: apesar dos CPTs estarem em português (`dps_cliente`, `dps_agendamento`), parte dos metadados legados usa chaves em inglês (ex.: `dps_client_id`, `appointment_client_id`) por compatibilidade histórica.

### Options relevantes

- `dps_portal_page_id`
- `dps_portal_logo_id`
- `dps_portal_hero_id`
- `dps_portal_primary_color`
- `dps_portal_review_url`
- `dps_portal_access_notification_enabled`
- `dps_portal_tokens_db_version`

---

## Principais hooks de extensão

### Actions

- `dps_portal_before_render`
- `dps_portal_after_auth_check`
- `dps_portal_client_authenticated`
- `dps_client_portal_before_content`
- `dps_portal_before_*_content` / `dps_portal_after_*_content` (tabs)
- `dps_portal_after_update_client`
- `dps_portal_after_update_preferences`
- `dps_portal_after_update_pet_preferences`
- `dps_portal_after_internal_review`
- `dps_portal_profile_update_link_generated`
- `dps_portal_profile_updated`

### Filters

- `dps_portal_tabs`
- `dps_portal_login_screen`
- `dps_portal_review_url`
- `dps_tosa_consent_page_url`
- `dps_portal_pre_ownership_check`
- `dps_portal_ownership_validated`

Para lista completa com exemplos de uso, consulte: `HOOKS.md`.

---

## AJAX endpoints (visão prática)

Registrados principalmente em `DPS_Portal_AJAX_Handler` e `DPS_Portal_Admin_Actions`:

- chat e mensagens (`dps_chat_*`);
- solicitação de acesso por e-mail (`dps_request_access_link_by_email`);
- pedido de agendamento (`dps_create_appointment_request`);
- loyalty (histórico/resgate);
- geração/revogação de tokens (admin);
- geração de link de atualização de perfil (admin).

Todos os endpoints críticos passam por validação de autenticação/ownership e nonces quando aplicável.

---

## Instalação e configuração rápida

1. Ative o plugin base DPS.
2. Ative o add-on **Client Portal**.
3. Verifique/crie a página do portal com shortcode `[dps_client_portal]` (o add-on tenta criar automaticamente na ativação).
4. Em **Configurações do DPS**, confirme a página definida em `dps_portal_page_id`.
5. Gere links de acesso para clientes na área administrativa do portal.

---

## Segurança e governança

O add-on segue as diretrizes do repositório:

- validação de nonce/capability/sanitização/escape;
- checagem de ownership para recursos de cliente;
- proteção contra uso indevido de tokens;
- trilha de auditoria de acessos;
- manutenção de compatibilidade com hooks/contratos do ecossistema DPS.

Referências obrigatórias:

- `../../AGENTS.md`
- `../../ANALYSIS.md`

---

## Observações para desenvolvimento

- Ao alterar contratos, hooks ou fluxo de autenticação, documente impacto em `ANALYSIS.md`.
- Para mudanças user-facing relevantes, atualize `CHANGELOG.md`.
- Para mudanças visuais, seguir padrão M3 em `docs/visual/` e registrar evidências em `docs/screenshots/`.
- Este add-on contém documentação complementar em `TOKEN_AUTH_SYSTEM.md` e `HOOKS.md`.

---

## Documentos relacionados

- `plugins/desi-pet-shower-client-portal/HOOKS.md`
- `plugins/desi-pet-shower-client-portal/TOKEN_AUTH_SYSTEM.md`
- `ANALYSIS.md`
- `CHANGELOG.md`
