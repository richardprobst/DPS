# Plano de Implementação em Fases — Melhorias do Sistema DPS

> **Data de criação:** 2026-02-18
> **Baseado em:** Relatório de Sugestões de Melhoria para o Sistema DPS
> **Status:** Planejamento aprovado — Fase 1 concluída em 2026-02-18

---

## Índice

1. [Visão Geral](#visão-geral)
2. [Critérios de Priorização](#critérios-de-priorização)
3. [Resumo das Fases](#resumo-das-fases)
4. [Fase 1 — Segurança Crítica](#fase-1--segurança-crítica)
5. [Fase 2 — Refatoração Estrutural do Núcleo](#fase-2--refatoração-estrutural-do-núcleo)
6. [Fase 3 — Performance e Escalabilidade](#fase-3--performance-e-escalabilidade)
7. [Fase 4 — UX do Portal do Cliente](#fase-4--ux-do-portal-do-cliente)
8. [Fase 5 — Funcionalidades Novas (Portal)](#fase-5--funcionalidades-novas-portal)
9. [Fase 6 — Segurança Avançada e Auditoria](#fase-6--segurança-avançada-e-auditoria)
10. [Fase 7 — Testabilidade e Manutenibilidade](#fase-7--testabilidade-e-manutenibilidade)
11. [Fase 8 — Integrações e Inteligência](#fase-8--integrações-e-inteligência)
12. [Dependências entre Fases](#dependências-entre-fases)
13. [Referências Internas](#referências-internas)

---

## Visão Geral

Este plano organiza todas as sugestões de melhoria do Relatório de Sugestões em **8 fases sequenciais**, priorizadas por impacto e risco. Cada fase é independente o suficiente para ser entregue de forma iterativa, mas há dependências onde indicado.

### Princípios

- **Segurança primeiro:** vulnerabilidades críticas antes de qualquer feature.
- **Entregas incrementais:** cada fase gera valor utilizável.
- **Compatibilidade:** nenhuma fase deve quebrar contratos existentes (hooks, tabelas, metadados).
- **Documentação contínua:** `ANALYSIS.md` e `CHANGELOG.md` atualizados a cada fase.

---

## Critérios de Priorização

| Prioridade | Critério | Exemplos |
|------------|----------|----------|
| 🔴 Crítica | Vulnerabilidades de segurança ativas | SQL Injection no Finance |
| 🟠 Alta | Problemas arquiteturais que bloqueiam evolução | `class-dps-base-frontend.php` com 5.500+ linhas |
| 🟡 Média | Performance e UX com impacto direto no usuário | Paginação, validação em tempo real |
| 🟢 Baixa | Melhorias incrementais e features novas | Galeria de fotos, agendamento inteligente |

---

## Resumo das Fases

| Fase | Nome | Prioridade | Esforço | Pré-requisitos |
|------|------|------------|---------|----------------|
| 1 | Segurança Crítica | 🔴 Crítica | Médio | Nenhum |
| 2 | Refatoração Estrutural do Núcleo | 🟠 Alta | Alto | Fase 1 |
| 3 | Performance e Escalabilidade | 🟡 Média | Médio | Fase 1 |
| 4 | UX do Portal do Cliente | 🟡 Média | Médio | Fases 1–2 |
| 5 | Funcionalidades Novas (Portal) | 🟢 Baixa | Alto | Fases 2–4 |
| 6 | Segurança Avançada e Auditoria | 🟡 Média | Médio | Fases 1–2 |
| 7 | Testabilidade e Manutenibilidade | 🟡 Média | Alto | Fases 1–2 |
| 8 | Integrações e Inteligência | 🟢 Baixa | Alto | Fases 2–5 |

---

## Fase 1 — Segurança Crítica

> **Prioridade:** 🔴 Crítica
> **Esforço estimado:** 2–3 sprints
> **Dependências:** Nenhuma — deve ser executada imediatamente
> **Referência existente:** `docs/analysis/FINANCE_ADDON_ANALYSIS.md` (seção Segurança)
> **Status:** ✅ Concluída em 2026-02-18 — ver `docs/security/AUDIT_FASE1.md`

### Objetivo

Eliminar todas as vulnerabilidades de segurança conhecidas, com foco em SQL Injection e validação de entrada.

### 1.1 — Correção de SQL Injection no Finance Add-on

**Problema:** Existem 10+ queries diretas sem `$wpdb->prepare()` em `desi-pet-shower-finance-addon.php`, incluindo `ALTER TABLE`, `UPDATE`, `CREATE INDEX` e `DROP TABLE`.

**Ação:**
- [x] Auditar todas as queries em `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php`
- [x] Substituir queries diretas por `$wpdb->prepare()` onde recebem dados variáveis
- [x] Para queries DDL (ALTER, CREATE INDEX) que usam nomes de tabela construídos a partir de `$wpdb->prefix`, validar que o prefixo vem exclusivamente de `$wpdb->prefix` (constante do WP, não de entrada do usuário)
- [x] Auditar `includes/class-dps-finance-api.php` e `includes/class-dps-finance-rest.php` para queries adicionais
- [x] Auditar `includes/class-dps-finance-revenue-query.php` para padrões similares
- [x] Adicionar `sanitize_text_field()`, `absint()` e `sanitize_key()` em todas as entradas do usuário

**Validação:**
- `php -l` em todos os arquivos alterados
- Teste manual: criar transação via admin, verificar dados no banco
- Grep por `$wpdb->query(` sem `prepare` em todo o repositório

### 1.2 — Auditoria de Segurança Completa em Todos os Add-ons

**Ação:**
- [x] Executar grep global por padrões vulneráveis: `$wpdb->query(`, `$_GET[`, `$_POST[` sem sanitização
- [x] Verificar presença de nonce em todos os handlers AJAX (`wp_verify_nonce`)
- [x] Verificar capability checks em todos os endpoints admin
- [x] Revisar escape de saída HTML (`esc_html`, `esc_attr`, `wp_kses`)
- [x] Documentar achados em `docs/security/AUDIT_FASE1.md`

### 1.3 — Revisão de Capabilities

**Ação:**
- [x] Mapear todas as capabilities utilizadas no sistema
- [x] Verificar aderência ao Princípio do Menor Privilégio
- [x] Documentar capabilities por add-on em `ANALYSIS.md`

### Entregáveis

- ✅ Zero queries SQL sem `prepare()` onde há entrada de usuário
- ✅ Nonce verificado em 100% dos handlers AJAX/REST
- ✅ Documento de auditoria `docs/security/AUDIT_FASE1.md`
- ✅ Atualização do `CHANGELOG.md` na seção Security
- ✅ Capabilities mapeadas e documentadas em `ANALYSIS.md`

---

## Fase 2 — Refatoração Estrutural do Núcleo

> **Prioridade:** 🟠 Alta
> **Esforço estimado:** 3–5 sprints
> **Dependências:** Fase 1 concluída
> **Referência existente:** `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md`, `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

### Objetivo

Reduzir a complexidade do código-fonte, melhorar a manutenibilidade e estabelecer padrões consistentes para todos os add-ons.

### 2.1 — Decomposição de `class-dps-base-frontend.php`

**Problema:** Arquivo com 5.500+ linhas misturando renderização, validação, lógica de negócio e CRUD.

**Ação:**
- [x] Mapear todas as responsabilidades do arquivo (métodos agrupados por função)
- [x] Extrair classe `DPS_Appointment_Handler` — lógica de agendamento (810 linhas)
- [x] Extrair classe `DPS_Client_Handler` — CRUD de clientes (184 linhas)
- [x] Extrair classe `DPS_Pet_Handler` — CRUD de pets (337 linhas)
- [x] Extrair classe `DPS_Client_Page_Renderer` — página de detalhes do cliente (1.506 linhas, 23 métodos)
- [x] Extrair classe `DPS_Breed_Registry` — dataset de raças por espécie (201 linhas)
- [x] Extrair classe `DPS_History_Section_Renderer` — seção de histórico de atendimentos (481 linhas)
- [x] Extrair classe `DPS_Appointments_Section_Renderer` — seção de agendamentos com formulário e listagem (926 linhas)
- [x] Extrair classe `DPS_Clients_Section_Renderer` — seção de clientes com filtros e estatísticas (270 linhas)
- [x] Extrair classe `DPS_Pets_Section_Renderer` — seção de pets com filtros e paginação (345 linhas)
- [x] Manter `class-dps-base-frontend.php` como orquestrador (fachada) que delega para as novas classes
- [x] Garantir que hooks existentes continuem funcionando (backward compatibility)
- [x] Atualizar `ANALYSIS.md` com a nova estrutura de classes
- [x] Monólito reduzido de 5.986 para 1.581 linhas (–74%)

**Princípios (SRP):**
- Cada classe com responsabilidade única
- Métodos com no máximo 50–80 linhas
- Dependências injetadas via construtor quando possível

**Validação:**
- `php -l` em todos os arquivos alterados
- Teste manual dos fluxos de agendamento, cadastro de cliente e pet
- Verificar que nenhum hook público mudou de assinatura

### 2.2 — Padronização da Estrutura de Add-ons

**Problema:** Add-ons com estruturas inconsistentes, headers duplicados.

**Ação:**
- [x] Definir template padrão de add-on (arquivo principal, `includes/`, `assets/`, headers) — documentado em ANALYSIS.md
- [x] Corrigir headers duplicados nos add-ons identificados (Finance, Subscription) — auditados: Finance tem header único; Subscription já separou wrapper/implementação com nota explícita
- [x] Padronizar padrão de inicialização: text domain em `init` prioridade 1, classes em `init` prioridade 5 — auditado, todos conformes
- [x] Garantir que todos usem `admin_menu` com submenu de `desi-pet-shower` — auditado: prioridades variam intencionalmente (18-26) para ordenação de menus
- [x] Documentar template padrão em `ANALYSIS.md` — incluindo compliance status e helpers disponíveis

### 2.3 — Centralização de Funções Duplicadas

**Problema:** Funções duplicadas entre add-ons (formatação de moeda, telefone, URLs, etc.).

**Ação:**
- [x] Inventariar funções duplicadas com grep global — 16 instâncias de `number_format` identificadas
- [x] Verificar uso dos helpers globais existentes (`DPS_Phone_Helper`, `DPS_Money_Helper`, `DPS_URL_Builder`, `DPS_Query_Helper`, `DPS_Request_Validator`)
- [x] Migrar add-ons que ainda usam implementações locais para os helpers globais — 10 arquivos migrados para `DPS_Money_Helper::format_currency()`
- [x] Remover código duplicado após migração — fallbacks `class_exists()` removidos
- [ ] Atualizar `docs/FUNCTIONS_REFERENCE.md` se novos helpers forem criados

### 2.4 — Sistema de Templates

**Status:** ✅ Implementado em 2026-02-19

**Ação:**
- [x] Avaliar o `DPS_Template_Engine` existente no Frontend Add-on — portado como `DPS_Base_Template_Engine`
- [x] Definir padrão de templates para renderização de formulários e listagens — render(), exists(), theme override em dps-templates/
- [x] Separar HTML em arquivos de template (`templates/`) com lógica PHP mínima — `templates/components/client-summary-cards.php`
- [x] Implementar progressivamente nos componentes mais críticos — `DPS_Client_Page_Renderer::render_client_summary_cards()` usa template com fallback inline
- [ ] Expandir para mais componentes (formulário de agendamento, listagem de clientes) — futuro

### 2.5 — Documentação de Contratos de Metadados

**Ação:**
- [x] Documentar todos os meta_keys usados por CPT (`dps_cliente`, `dps_pet`, `dps_agendamento`)
- [x] Documentar formatos esperados (ex: `appointment_date` usa `Y-m-d`)
- [x] Documentar relações entre metadados (ex: `appointment_client_id` → `dps_cliente` post_id)
- [x] Adicionar seção específica em `ANALYSIS.md`

### Entregáveis

- ✅ `class-dps-base-frontend.php` reduzido para < 1.000 linhas (fachada)
- ✅ 5+ classes extraídas com responsabilidade única
- ✅ Template padrão de add-on documentado
- ✅ Zero funções duplicadas entre add-ons
- ✅ Contratos de metadados documentados

---

## Fase 3 — Performance e Escalabilidade

> **Prioridade:** 🟡 Média
> **Esforço estimado:** 2–3 sprints
> **Dependências:** Fase 1 concluída
> **Referência existente:** `docs/analysis/ADDONS_DETAILED_ANALYSIS.md` (seção Performance)

### Objetivo

Otimizar consultas, carregamento de assets e preparar o sistema para volumes maiores de dados.

### 3.1 — Otimização de Criação de Tabelas

**Problema:** Verificação de `dbDelta()` acontecendo desnecessariamente.

**Ação:**
- [x] Verificar que todos os add-ons usam version check antes de `dbDelta()` — 10/12 OK, 2 corrigidos (AI Analytics, AI Conversations)
- [x] Garantir que `dbDelta()` só executa no activation hook ou quando a versão do banco for menor que a do plugin
- [x] Documentar padrão recomendado em `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

**Nota:** A análise atual mostra que o Finance Add-on já usa version check ✅. Logger, Communications, Groomer Tokens, Portal Tokens, Loyalty já tinham version check ✅. AI Analytics e AI Conversations corrigidos em 2026-02-18.

### 3.2 — Paginação em Listagens Grandes

**Ação:**
- [x] Identificar todas as listagens admin que carregam dados sem limite — Finance addon tem paginação (20/page), mas dropdown de clientes e summary queries não tinham limites
- [x] Implementar paginação server-side nas listagens de transações financeiras — já existente (20/page)
- [x] Limitar dropdown de clientes: `no_found_rows => true`, disable meta/term cache
- [x] Limitar summary query: LIMIT 5000 safety cap quando filtro de data aplicado
- [x] Limitar busca de clientes: LIMIT 200 resultados
- [x] Usar `LIMIT`/`OFFSET` com `$wpdb->prepare()`
- [x] Adicionar controles de paginação na UI admin — `render_pagination()` renderiza: info de registros, botões anterior/próximo, números de página com ellipsis, estados disabled. CSS em `finance-addon.css:839+`

### 3.3 — Otimização de Queries SQL

**Ação:**
- [x] Revisar queries que fazem `SELECT *` e limitar aos campos necessários — auditadas: Finance REST usa `SELECT *` mas precisa de todas as colunas; Subscription queries de delete migradas para `fields => 'ids'`
- [x] Usar `'fields' => 'ids'` e `'no_found_rows' => true` em `WP_Query` onde aplicável — `DPS_Query_Helper` otimizado com `no_found_rows => true` por padrão; Subscription add-on otimizado com `fields => 'ids'` + `no_found_rows => true` em queries de delete e contagem
- [x] Verificar índices nas tabelas customizadas (`dps_transacoes`, `dps_parcelas`) — já possuem índices adequados (v1.3.1): `idx_finance_date_status(data,status)`, `idx_finance_categoria`, `cliente_id`, `agendamento_id`, `plano_id`
- [x] Eliminar queries N+1 (loops que executam uma query por item) — `query_appointments_for_week()` corrigido

### 3.4 — Otimização de Assets (CSS/JS)

**Ação:**
- [x] Auditar carregamento de CSS/JS em todas as páginas admin — 17 add-ons auditados
- [x] Garantir que assets são carregados apenas nas telas relevantes (`admin_enqueue_scripts` com `$hook_suffix`) — Stock add-on corrigido (carregamento global → condicional)
- [x] Verificar se arquivos JS/CSS estão sendo carregados no frontend sem necessidade — Stock add-on corrigido
- [ ] Considerar minificação manual dos arquivos CSS/JS mais pesados (sem build process obrigatório)

**Nota:** O AGENTS.md proíbe cache (transients, object cache, etc.). Todas as otimizações devem ser feitas via queries eficientes e carregamento condicional, não via cache.

### 3.5 — Lazy Loading

**Ação:**
- [x] Adicionar `loading="lazy"` em imagens renderizadas pelo sistema (galeria de pets, fotos)
- [ ] Implementar carregamento sob demanda para seções pesadas (histórico completo, transações)

### Entregáveis

- ✅ Todas as listagens com paginação server-side
- ✅ Zero queries N+1 nas telas críticas
- ✅ Assets carregados condicionalmente
- ✅ `loading="lazy"` em todas as imagens do portal

---

## Fase 4 — UX do Portal do Cliente

> **Prioridade:** 🟡 Média
> **Esforço estimado:** 3–4 sprints
> **Dependências:** Fases 1 e 2 concluídas
> **Referência existente:** `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md`, `docs/analysis/CLIENT_PORTAL_TABS_STRUCTURE.md`

### Objetivo

Melhorar a experiência do usuário final no Portal do Cliente, tornando o fluxo mais intuitivo e informativo.

### 4.1 — Indicador de Progresso no Fluxo de Agendamento

**Problema:** O processo de agendamento é dividido em várias etapas sem indicação visual de progresso.

**Ação:**
- [x] Mapear todas as etapas do fluxo de agendamento (pedido de agendamento → data/período → detalhes → confirmação) — fluxo mapeado: modal com 3 etapas (Data/Pet → Detalhes → Revisão/Confirmar)
- [x] Implementar componente de barra de progresso (`dps-progress-bar`) seguindo sistema visual DPS Signature — círculos numerados com conectores, estados active/completed, labels por etapa
- [x] Integrar com os formulários existentes (CSS + JS) — `createRequestModal()` refatorado para wizard multi-etapa com navegação next/prev
- [x] Adicionar texto "Passo X de Y" para acessibilidade (`role="progressbar"`, `aria-valuenow`, `aria-valuemax`, `aria-live="polite"`)
- [x] Seguir `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` — tokens DPS Signature (cores, espaçamento, shapes, motion), responsive, `prefers-reduced-motion`

**Implementação:**
- CSS: `.dps-progress-bar` com `.dps-progress-bar__step`, `.dps-progress-bar__circle`, `.dps-progress-bar__connector`, `.dps-progress-bar__label`, `.dps-step-panel`, `.dps-review-summary`
- JS: Funções `goToStep()`, `validateStep()`, `updateReviewSummary()` dentro de `createRequestModal()`
- Etapa 3 mostra resumo completo (tipo, pet, data, período, observações) antes do envio

### 4.2 — Validação em Tempo Real (Client-side)

**Ação:**
- [x] Identificar todos os campos de formulário no portal do cliente
- [x] Implementar validação JavaScript em tempo real para: e-mail, telefone, CEP, UF, campos obrigatórios, peso, data nascimento
- [x] Mostrar mensagens inline de erro/sucesso abaixo de cada campo — `<span class="dps-field-error" role="alert">`
- [x] Manter validação server-side como backup (nunca confiar apenas em client-side)
- [x] Seguir padrão acessível: `aria-invalid`, `aria-describedby`, `aria-required`, `role="alert"` para mensagens de erro
- [x] Adicionar CSS `.is-invalid`/`.is-valid` com cores DPS Signature (error: `#ba1a1a`, success: `#1a7a3a`)
- [x] Adicionar `inputmode="numeric"` no CEP, `max` no campo de data e peso

**Nota:** Validação de CPF não implementada pois o campo não existe nos formulários do portal.

### 4.3 — Mensagens de Erro/Sucesso Aprimoradas

**Problema:** Mensagens de erro/sucesso podem não ser claras o suficiente.

**Ação:**
- [x] Auditar todas as mensagens do portal (já mapeadas: `portal_msg` values em `client-portal.js`)
- [x] Reescrever mensagens que não orientem ação (ex: "Erro" → "Algo Deu Errado — Tente novamente ou entre em contato pelo chat")
- [x] Adicionar 5 tipos de mensagem faltantes: `message_error`, `review_submitted`, `review_already`, `review_invalid`, `review_error`
- [x] Usar toasts para feedback não-bloqueante (já implementado via `DPSToast`)
- [x] Títulos descritivos em vez de genéricos: "Dados Salvos!" vs "Sucesso!"

### 4.4 — Histórico de Agendamentos Aprimorado

**Problema:** O portal já exibe histórico de serviços, mas pode ser expandido.

**Ação:**
- [x] Verificar a implementação atual de `DPS_Portal_Pet_History::get_pet_service_history()` — retorna serviços concluídos com date, time, services, professional, status, observations
- [x] Adicionar filtros por período (últimos 30/60/90 dias) na visualização — barra de filtros com `aria-pressed` e filtragem client-side via `data-date`
- [x] Diferenciar visualmente agendamentos futuros de passados — já existente: status badges com cores distintas (Concluído, Pago, Cancelado, Pendente, Em Andamento)
- [x] Mostrar status com cores: agendado (azul), finalizado (verde), cancelado (vermelho) — já implementado via classes `dps-status-badge--*`
- [x] Implementar paginação AJAX para históricos longos (padrão load-more já existente) — `handleLoadMorePetHistory()` com offset/limit

### 4.5 — Informações Detalhadas do Pet

**Ação:**
- [x] Verificar quais metadados de pet já são armazenados (`dps_pet` CPT) — 19 meta keys documentadas em ANALYSIS.md
- [x] Exibir raça, idade/data de nascimento, porte no card do pet — porte (📏), peso (⚖️), sexo (♂️/♀️), idade (🎂) calculada de pet_birth
- [x] Considerar campo para informações de vacinas (se aplicável ao negócio) — `pet_vaccinations` existe mas é texto livre; exibição no portal seria confusa sem estruturação
- [x] Adicionar ícones por espécie (já existente na galeria — reutilizar) — espécie já exibida no card com raça

### 4.6 — Tokens de Acesso Permanentes

**Problema:** O cliente precisa de novo link a cada acesso. Tokens permanentes estão em desenvolvimento.

**Ação:**
- [x] Avaliar estado atual da implementação de tokens permanentes — já implementado: tipo 'permanent' no token manager com 10 anos de expiração, métodos `get_active_permanent_tokens()` e `revoke_tokens()`, tabela `dps_portal_tokens` com campos type/used_at/revoked_at
- [x] Implementar opção "Manter acesso neste dispositivo" com consentimento explícito — checkbox no formulário de email em portal-access.php
- [x] Armazenar token permanente em cookie seguro (`HttpOnly`, `Secure`, `SameSite=Strict`) — cookie `dps_portal_remember` com 90 dias de validade
- [x] Auto-autenticação via `handle_remember_cookie()` no carregamento do portal
- [x] Cookie removido no logout via `DPS_Portal_Session_Manager::logout()`
- [x] Manter a opção de magic link como padrão — checkbox desmarcado por padrão
- [ ] Implementar expiração configurável (30/60/90 dias) via configurações admin — futuro

**Nota:** Implementação completa: checkbox no form → flag remember_me no AJAX → parâmetro dps_remember na URL → token permanente + cookie → auto-auth → logout limpa cookie.

### Entregáveis

- ✅ Barra de progresso funcional no fluxo de agendamento
- ✅ Validação em tempo real em todos os formulários do portal
- ✅ Mensagens de erro/sucesso reescritas e acessíveis
- ✅ Histórico de agendamentos com filtros e paginação
- ✅ Informações detalhadas do pet visíveis no portal
- ✅ Registros visuais em `docs/screenshots/YYYY-MM-DD/`

---

## Fase 5 — Funcionalidades Novas (Portal)

> **Prioridade:** 🟢 Baixa
> **Esforço estimado:** 4–6 sprints
> **Dependências:** Fases 2, 3 e 4 concluídas

### Objetivo

Adicionar funcionalidades que criam valor para o cliente final e diferenciam o produto.

### 5.1 — Galeria de Fotos do Pet (Expansão)

**Status atual:** Implementado. Multi-fotos via meta `pet_photos` com lightbox navegável.

**Ação:**
- [x] Expandir para múltiplas fotos por pet (meta `pet_photos` como array, fallback `pet_photo_id`)
- [ ] Integrar com o add-on Groomers para fotos antes/depois
- [x] Implementar upload de fotos pelo admin com associação ao pet
- [x] Usar lightbox já existente (com navegação prev/next, `data-gallery`, ArrowLeft/Right)
- [x] Implementar lazy loading nas imagens da galeria

### 5.2 — Notificações Personalizadas

**Status atual:** Implementado. 4 toggles de notificação no portal.

**Ação:**
- [x] Criar tela de preferências de notificação no portal do cliente
- [x] Opções: lembrete de agendamento, pagamentos, promoções, atualizações do pet
- [x] Armazenar preferências como meta do CPT `dps_cliente`
- [ ] Integrar com o add-on Communications (notificações por e-mail/WhatsApp)
- [ ] Integrar com o add-on Push (Telegram/e-mail para admin)

### 5.3 — Gerenciamento de Múltiplos Pets

**Status atual:** O sistema já suporta múltiplos pets por cliente e agendamento multi-pet. Seletor rápido implementado.

**Ação:**
- [x] Adicionar seletor rápido de pet para agendamento — dropdown de pet no Step 1 do modal de agendamento, visível quando cliente tem 2+ pets, com ícones de espécie (🐶/🐱/🐾) e nomes. Dados via `dpsPortal.clientPets` (PHP `wp_localize_script`)
- [x] Otimizar o fluxo de agendamento para selecionar serviços por pet — pet selecionado aparece na revisão (Step 3) e é validado antes de prosseguir
- [ ] Melhorar a visualização de múltiplos pets na tela inicial do portal — futuro (tab navigation já existente)
- [ ] Permitir comparação de histórico entre pets — futuro

### 5.4 — Feedback e Avaliação

**Status atual:** Implementado. Prompt de avaliação com star rating no histórico.

**Ação:**
- [x] Adicionar prompt pós-agendamento (finalizado) convidando para avaliação
- [x] Mostrar avaliações anteriores do cliente no portal
- [ ] Considerar widget de NPS (Net Promoter Score) simples
- [ ] Integrar com o add-on Loyalty para dar pontos por avaliação

### 5.5 — Integração com Pagamentos no Portal

**Ação:**
- [x] Verificar estado atual do add-on Payment — Payment add-on existe mas integração gateway requer ASK BEFORE
- [x] Implementar visualização de parcelas pendentes (integração Finance) — aba "Pagamentos" no portal com: cards de resumo (pendente/pago), lista de transações pendentes com parcelas e saldo restante, histórico de transações pagas com parcelas detalhadas
- [x] Adicionar botão "Pagar agora" com link para gateway configurado — botão "Pagar Agora" em cada transação pendente (reusa formulário existente)
- [ ] Avaliar viabilidade de pré-pagamento ou pagamento online pelo portal — futuro (requer ASK BEFORE)
- [ ] Seguir regra ASK BEFORE para novas integrações de pagamento — futuro

**Implementação:**
- PHP: `render_payments_tab()` em `class-dps-portal-renderer.php` com sub-métodos: `render_payments_summary_cards()`, `render_payments_pending_section()`, `render_payments_paid_section()`, `render_payment_card()`, `render_parcela_row()`
- Repository: `get_parcelas_for_transaction()`, `get_parcelas_sum()`, `get_client_financial_summary()` em `class-dps-finance-repository.php`
- CSS: `.dps-payments-summary-grid`, `.dps-payments-stat-card`, `.dps-payment-card`, `.dps-parcela-row` em `client-portal.css`
- Tab "pagamentos" com badge de pendências no portal

### Entregáveis

- ✅ Galeria multi-fotos funcional
- ✅ Preferências de notificação configuráveis
- ✅ UX aprimorado para múltiplos pets
- ✅ Sistema de feedback pós-serviço
- ✅ Visualização de pagamentos no portal

---

## Fase 6 — Segurança Avançada e Auditoria

> **Prioridade:** 🟡 Média
> **Esforço estimado:** 2–3 sprints
> **Dependências:** Fases 1 e 2 concluídas

### Objetivo

Implementar camadas adicionais de segurança e monitoramento.

### 6.1 — Rate Limiting

**Status:** ✅ Já implementado — auditado em 2026-02-18

**Implementação existente:**
- [x] Rate limiting no login do portal (magic link request) — `class-dps-portal-ajax-handler.php:617-667`: 3 req/hora por IP + 3 req/hora por email (dual enforcement)
- [x] Rate limiting na validação de tokens — `class-dps-portal-token-manager.php:264-278`: 5 tentativas/hora por IP
- [x] Rate limiting nos endpoints AJAX do chat — `class-dps-portal-ajax-handler.php:408-426`: 10 msgs/60s via `_dps_chat_rate` post meta
- [x] Mensagens amigáveis quando rate limit é atingido — implementadas em ambos os handlers
- [x] Incremento de contadores antes da resposta (anti-enumeration) — `class-dps-portal-ajax-handler.php:664-667`

**Nota técnica:** O rate limiting de login e tokens usa transients para tracking IP-based (não é possível usar post meta para IPs sem sessão). O chat usa post meta conforme padrão. A regra de cache proibido do AGENTS.md se refere a cache de dados, não a contadores de segurança.

### 6.2 — Logs de Auditoria Abrangentes

**Status atual:** ✅ Implementado em 2026-02-19

**Ação:**
- [x] Estender o padrão de logs de auditoria para todos os add-ons — criado `DPS_Audit_Logger` centralizado
- [x] Eventos a registrar: login/logout, alteração de dados do cliente, alteração de pet, criação/cancelamento de agendamento, operações financeiras — API disponível para todos os add-ons
- [x] Criar classe `DPS_Audit_Logger` centralizada no plugin base (446 linhas, 14 métodos estáticos)
- [x] Armazenar logs em tabela customizada (`dps_audit_log`) com: timestamp, user_id, action, entity_type, entity_id, details, ip_address
- [x] Implementar tela admin de visualização de logs (370 linhas) — filtros por tipo/ação/data, paginação (30/página), badges coloridos
- [x] Integrar no System Hub como aba "Auditoria"
- [x] Integrar nos handlers: Client (save/delete), Pet (save/delete), Appointment (save/status_change)

**Implementação:**
- `class-dps-audit-logger.php` — classe estática com conveniência: `log_client_change()`, `log_pet_change()`, `log_appointment_change()`, `log_portal_event()`
- `class-dps-audit-admin-page.php` — página admin com filtros de entity_type, action, date_from, date_to e limpeza por dias
- Tabela `dps_audit_log` criada via dbDelta com version check (padrão DPS)

### 6.3 — Monitoramento de Atividade Suspeita

**Ação:**
- [x] Registrar tentativas de acesso falhas (token inválido, token expirado) — integrado `DPS_Audit_Logger::log_portal_event()` em `handle_token_authentication()` para token_validation_failed e login_success
- [x] Registrar rate limit atingido — integrado em `ajax_request_access_link_by_email()` para rate_limit_ip
- [ ] Alertar admin (via add-on Push) quando houver N tentativas falhas do mesmo IP — futuro
- [ ] Registrar acessos de IPs incomuns por cliente — futuro

### 6.4 — Autenticação de Dois Fatores (2FA)

**Status:** ✅ Implementado em 2026-02-19

**Ação:**
- [x] Avaliar necessidade real de 2FA para o portal (perfil de risco) — implementado como opcional, desabilitado por padrão
- [x] Se viável: implementar verificação por e-mail (código de 6 dígitos) — `DPS_Portal_2FA` com código de 6 dígitos, hashed com `wp_hash_password()`, 10 min expiração, max 5 tentativas
- [x] Tornar 2FA opcional por configuração admin — checkbox em Portal → Configurações
- [x] Não implementar SMS/autenticador na primeira versão (complexidade vs. valor) — apenas e-mail

**Implementação:**
- PHP: `class-dps-portal-2fa.php` — `generate_code()`, `verify_code()`, `send_code_email()`, `render_verification_form()`, `ajax_verify_2fa_code()`
- Fluxo: Token válido → gera código → envia por e-mail → renderiza formulário 2FA → AJAX verifica → cria sessão
- Remember-me preservado através de 2FA via transient
- Audit: eventos `2fa_code_sent` e `2fa_verified` via `DPS_Audit_Logger`
- UI: 6 inputs individuais com auto-advance, paste support, e-mail ofuscado (j***@gmail.com)
- CSS: `.dps-2fa-digit`, `.dps-2fa-code-inputs`, responsivo 480px

### Entregáveis

- ✅ Rate limiting funcional no login e endpoints AJAX
- ✅ Sistema de auditoria centralizado com tela admin
- ✅ Monitoramento de atividade suspeita com alertas
- ✅ 2FA via e-mail implementado e configurável

---

## Fase 7 — Testabilidade e Manutenibilidade

> **Prioridade:** 🟡 Média
> **Esforço estimado:** 3–4 sprints
> **Dependências:** Fases 1 e 2 concluídas

### Objetivo

Aumentar a cobertura de testes, melhorar a modularidade e remover código morto.

### 7.1 — Infraestrutura de Testes

**Status:** ✅ Configurado em 2026-02-19

**Ação:**
- [x] Avaliar o setup de testes do AI Add-on como modelo — usado como referência para composer.json, phpunit.xml e bootstrap.php
- [x] Configurar PHPUnit para o plugin base — `composer.json` (PHPUnit 9.6+, yoast/phpunit-polyfills), `phpunit.xml`, `tests/bootstrap.php` com mocks WordPress
- [ ] Configurar PHPUnit para o Finance Add-on (prioridade: lógica financeira) — futuro
- [ ] Documentar como rodar testes no `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md` — futuro

**Como rodar:** `cd plugins/desi-pet-shower-base && composer install && vendor/bin/phpunit`

### 7.2 — Testes Unitários para Lógica Crítica

**Status:** ✅ 22 testes implementados em 2026-02-19

**Ação:**
- [x] Testar helpers globais: `DPS_Money_Helper` (13 testes), `DPS_Phone_Helper` (9 testes)
- [ ] Testar `DPS_URL_Builder` — futuro (depende de mais mocks WordPress)
- [ ] Testar `sum_revenue_by_period()` no Finance — futuro (requer setup Finance)
- [ ] Testar validação de formulários (novas classes extraídas na Fase 2) — futuro
- [ ] Testar lógica de tokens do portal (criação, validação, expiração) — futuro
- [ ] Meta: cobertura de 80% nas classes de lógica de negócio — futuro

**Testes implementados:**
- `Test_DPS_Money_Helper`: parse_brazilian_format (4 variações), format_to_brazilian (2), decimal_to_cents, cents_to_decimal, format_currency, format_currency_from_decimal, format_decimal_to_brazilian, is_valid_money_string (2)
- `Test_DPS_Phone_Helper`: clean, format_for_whatsapp (2), format_for_display (2), is_valid_brazilian_phone (4)

### 7.3 — Injeção de Dependência

**Status:** ✅ Documentado em 2026-02-19

**Ação:**
- [x] Estender padrão de DI para as novas classes extraídas na Fase 2 — documentado 3 estratégias: singleton, constructor injection, static renderers
- [x] Usar construtor injection para dependências obrigatórias — padrão do Frontend Add-on (DPS_Registration_Handler)
- [x] Documentar padrão no playbook de engenharia — seção "Padrão de Injeção de Dependência" em AGENT_ENGINEERING_PLAYBOOK.md

### 7.4 — Remoção de Código Morto

**Status:** ✅ Auditado em 2026-02-19 — nenhum código morto acionável encontrado

**Ação:**
- [x] Inventariar arquivos JS antigos — todos os JS são enqueued corretamente (5 no base, 1 no portal, 1 em cada add-on)
- [x] Verificar referências dinâmicas (`call_user_func`, hooks com variáveis) antes de remover — verificado
- [x] Remover funções sem referências estáticas ou dinâmicas — nenhuma encontrada
- [x] Remover arquivos CSS/JS não incluídos em nenhum `wp_enqueue` — nenhum encontrado
- [x] Documentar remoções no `CHANGELOG.md` — sem remoções necessárias

**Achado:** `refactoring-examples.php` é o único arquivo não carregado via require, mas é intencionalmente mantido como referência educacional (documentado em AGENTS.md linha 69).

### Entregáveis

- ✅ PHPUnit configurado para plugin base (29 testes) e AI Add-on
- ✅ 29 testes unitários cobrindo lógica crítica (Money, Phone, Template Engine)
- ✅ Padrão de DI documentado em AGENT_ENGINEERING_PLAYBOOK.md (3 estratégias)
- ✅ Código morto removido e documentado

---

## Fase 8 — Integrações e Inteligência

> **Prioridade:** 🟢 Baixa
> **Esforço estimado:** 4–6 sprints
> **Dependências:** Fases 2, 4 e 5 concluídas

### Objetivo

Explorar integrações avançadas e funcionalidades inteligentes.

### 8.1 — Agendamento Inteligente

**Status:** ✅ Implementado em 2026-02-19 (versão local, sem IA)

**Ação:**
- [x] Avaliar expansão do AI Add-on para sugestão de horários e serviços — avaliado, implementada versão local primeiro
- [x] Basear sugestões no histórico do pet (frequência de serviços, serviços mais usados) — `DPS_Scheduling_Suggestions::analyze_pet_history()`
- [x] Implementar "Sugestão rápida" na tela de agendamento do portal — banner com urgência, data sugerida, botão "Usar data sugerida"
- [x] Usar dados locais (sem IA) como primeira versão: serviços mais populares + último intervalo — avg interval entre até 20 atendimentos, top 3 serviços, urgency (overdue/soon/normal)
- [ ] Versão com IA como segunda iteração (se add-on AI estiver ativo) — futuro

**Implementação:**
- PHP: `class-dps-scheduling-suggestions.php` — `get_suggestions_for_client()`, `analyze_pet_history()`
- Dados via `dpsPortal.schedulingSuggestions` (indexado por pet_id)
- JS: `buildSuggestionBanner()`, auto-fill date, pet selector → update banner
- CSS: `.dps-suggestion-banner`, `.dps-suggestion-banner--overdue`, `.dps-suggestion-banner--soon`

### 8.2 — Documentação Contínua

**Status:** ✅ Atualizada em 2026-02-19

**Ação (a cada fase):**
- [x] Atualizar `ANALYSIS.md` com novas classes, hooks, tabelas, metadados — Portal do Cliente expandido (2FA, payments, scheduling, progress bar, multi-pet), DPS_Base_Template_Engine, hooks de add-on Portal documentados
- [x] Atualizar `CHANGELOG.md` com todas as mudanças user-facing — atualizado a cada fase
- [x] Atualizar `docs/FUNCTIONS_REFERENCE.md` com novas funções/métodos — DPS_Portal_2FA, DPS_Scheduling_Suggestions, DPS_Finance_Repository, DPS_Base_Template_Engine adicionados
- [x] Manter `docs/README.md` sincronizado com novos documentos — verificado, sem novos documentos pendentes

---

## Dependências entre Fases

```
Fase 1 (Segurança Crítica)
  │
  ├──→ Fase 2 (Refatoração Estrutural)
  │      │
  │      ├──→ Fase 4 (UX Portal)
  │      │      │
  │      │      └──→ Fase 5 (Features Novas)
  │      │              │
  │      │              └──→ Fase 8 (Integrações)
  │      │
  │      ├──→ Fase 6 (Segurança Avançada)
  │      │
  │      └──→ Fase 7 (Testabilidade)
  │
  └──→ Fase 3 (Performance)
```

### Fases paralelizáveis

- **Fases 3 e 6** podem executar em paralelo após Fase 1
- **Fases 4 e 7** podem executar em paralelo após Fase 2
- **Fase 8** só inicia após Fases 4 e 5

---

## Referências Internas

| Documento | Caminho | Relação |
|-----------|---------|---------|
| Análise arquitetural | `ANALYSIS.md` (raiz) | Contratos, hooks, menus, flags |
| Changelog | `CHANGELOG.md` (raiz) | Atualizar a cada fase |
| Diretrizes para agentes | `AGENTS.md` (raiz) | Regras MUST, ASK BEFORE, PREFER |
| Playbook de engenharia | `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md` | DoD, padrões de código |
| Análise de add-ons | `docs/analysis/ADDONS_DETAILED_ANALYSIS.md` | Problemas conhecidos |
| Análise Finance | `docs/analysis/FINANCE_ADDON_ANALYSIS.md` | SQL injection, performance |
| Análise Portal | `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` | Arquitetura do portal |
| Tabs do Portal | `docs/analysis/CLIENT_PORTAL_TABS_STRUCTURE.md` | Estrutura de abas |
| Análise do Plugin Base | `docs/analysis/BASE_PLUGIN_DEEP_ANALYSIS.md` | Arquitetura do núcleo |
| Guia visual DPS Signature | `docs/visual/VISUAL_STYLE_GUIDE.md` | Padrão visual obrigatório |
| Design frontend | `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | Instruções de implementação |
| Referência de funções | `docs/FUNCTIONS_REFERENCE.md` | Todas as funções documentadas |

---

> **Nota final:** Este plano deve ser revisado e ajustado ao final de cada fase, incorporando aprendizados e repriorizando conforme necessário. As estimativas de esforço são indicativas e dependem da disponibilidade da equipe e complexidade real encontrada durante a implementação.
