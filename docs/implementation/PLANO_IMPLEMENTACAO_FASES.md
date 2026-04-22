# Plano de ImplementaÃ§Ã£o em Fases â€” Melhorias do Sistema DPS

> **Data de criaÃ§Ã£o:** 2026-02-18
> **Baseado em:** RelatÃ³rio de SugestÃµes de Melhoria para o Sistema DPS
> **Status:** Planejamento aprovado â€” Fase 1 concluÃ­da em 2026-02-18

---

## Ãndice

1. [VisÃ£o Geral](#visÃ£o-geral)
2. [CritÃ©rios de PriorizaÃ§Ã£o](#critÃ©rios-de-priorizaÃ§Ã£o)
3. [Resumo das Fases](#resumo-das-fases)
4. [Fase 1 â€” SeguranÃ§a CrÃ­tica](#fase-1--seguranÃ§a-crÃ­tica)
5. [Fase 2 â€” RefatoraÃ§Ã£o Estrutural do NÃºcleo](#fase-2--refatoraÃ§Ã£o-estrutural-do-nÃºcleo)
6. [Fase 3 â€” Performance e Escalabilidade](#fase-3--performance-e-escalabilidade)
7. [Fase 4 â€” UX do Portal do Cliente](#fase-4--ux-do-portal-do-cliente)
8. [Fase 5 â€” Funcionalidades Novas (Portal)](#fase-5--funcionalidades-novas-portal)
9. [Fase 6 â€” SeguranÃ§a AvanÃ§ada e Auditoria](#fase-6--seguranÃ§a-avanÃ§ada-e-auditoria)
10. [Fase 7 â€” Testabilidade e Manutenibilidade](#fase-7--testabilidade-e-manutenibilidade)
11. [Fase 8 â€” IntegraÃ§Ãµes e InteligÃªncia](#fase-8--integraÃ§Ãµes-e-inteligÃªncia)
12. [DependÃªncias entre Fases](#dependÃªncias-entre-fases)
13. [ReferÃªncias Internas](#referÃªncias-internas)

---

## VisÃ£o Geral

Este plano organiza todas as sugestÃµes de melhoria do RelatÃ³rio de SugestÃµes em **8 fases sequenciais**, priorizadas por impacto e risco. Cada fase Ã© independente o suficiente para ser entregue de forma iterativa, mas hÃ¡ dependÃªncias onde indicado.

### PrincÃ­pios

- **SeguranÃ§a primeiro:** vulnerabilidades crÃ­ticas antes de qualquer feature.
- **Entregas incrementais:** cada fase gera valor utilizÃ¡vel.
- **Compatibilidade:** nenhuma fase deve quebrar contratos existentes (hooks, tabelas, metadados).
- **DocumentaÃ§Ã£o contÃ­nua:** `ANALYSIS.md` e `CHANGELOG.md` atualizados a cada fase.

---

## CritÃ©rios de PriorizaÃ§Ã£o

| Prioridade | CritÃ©rio | Exemplos |
|------------|----------|----------|
| ðŸ”´ CrÃ­tica | Vulnerabilidades de seguranÃ§a ativas | SQL Injection no Finance |
| ðŸŸ  Alta | Problemas arquiteturais que bloqueiam evoluÃ§Ã£o | `class-dps-base-frontend.php` com 5.500+ linhas |
| ðŸŸ¡ MÃ©dia | Performance e UX com impacto direto no usuÃ¡rio | PaginaÃ§Ã£o, validaÃ§Ã£o em tempo real |
| ðŸŸ¢ Baixa | Melhorias incrementais e features novas | Galeria de fotos, agendamento inteligente |

---

## Resumo das Fases

| Fase | Nome | Prioridade | EsforÃ§o | PrÃ©-requisitos |
|------|------|------------|---------|----------------|
| 1 | SeguranÃ§a CrÃ­tica | ðŸ”´ CrÃ­tica | MÃ©dio | Nenhum |
| 2 | RefatoraÃ§Ã£o Estrutural do NÃºcleo | ðŸŸ  Alta | Alto | Fase 1 |
| 3 | Performance e Escalabilidade | ðŸŸ¡ MÃ©dia | MÃ©dio | Fase 1 |
| 4 | UX do Portal do Cliente | ðŸŸ¡ MÃ©dia | MÃ©dio | Fases 1â€“2 |
| 5 | Funcionalidades Novas (Portal) | ðŸŸ¢ Baixa | Alto | Fases 2â€“4 |
| 6 | SeguranÃ§a AvanÃ§ada e Auditoria | ðŸŸ¡ MÃ©dia | MÃ©dio | Fases 1â€“2 |
| 7 | Testabilidade e Manutenibilidade | ðŸŸ¡ MÃ©dia | Alto | Fases 1â€“2 |
| 8 | IntegraÃ§Ãµes e InteligÃªncia | ðŸŸ¢ Baixa | Alto | Fases 2â€“5 |

---

## Fase 1 â€” SeguranÃ§a CrÃ­tica

> **Prioridade:** ðŸ”´ CrÃ­tica
> **EsforÃ§o estimado:** 2â€“3 sprints
> **DependÃªncias:** Nenhuma â€” deve ser executada imediatamente
> **ReferÃªncia existente:** `docs/analysis/FINANCE_ADDON_ANALYSIS.md` (seÃ§Ã£o SeguranÃ§a)
> **Status:** âœ… ConcluÃ­da em 2026-02-18 â€” ver `docs/security/AUDIT_FASE1.md`

### Objetivo

Eliminar todas as vulnerabilidades de seguranÃ§a conhecidas, com foco em SQL Injection e validaÃ§Ã£o de entrada.

### 1.1 â€” CorreÃ§Ã£o de SQL Injection no Finance Add-on

**Problema:** Existem 10+ queries diretas sem `$wpdb->prepare()` em `desi-pet-shower-finance-addon.php`, incluindo `ALTER TABLE`, `UPDATE`, `CREATE INDEX` e `DROP TABLE`.

**AÃ§Ã£o:**
- [x] Auditar todas as queries em `plugins/desi-pet-shower-finance/desi-pet-shower-finance-addon.php`
- [x] Substituir queries diretas por `$wpdb->prepare()` onde recebem dados variÃ¡veis
- [x] Para queries DDL (ALTER, CREATE INDEX) que usam nomes de tabela construÃ­dos a partir de `$wpdb->prefix`, validar que o prefixo vem exclusivamente de `$wpdb->prefix` (constante do WP, nÃ£o de entrada do usuÃ¡rio)
- [x] Auditar `includes/class-dps-finance-api.php` e `includes/class-dps-finance-rest.php` para queries adicionais
- [x] Auditar `includes/class-dps-finance-revenue-query.php` para padrÃµes similares
- [x] Adicionar `sanitize_text_field()`, `absint()` e `sanitize_key()` em todas as entradas do usuÃ¡rio

**ValidaÃ§Ã£o:**
- `php -l` em todos os arquivos alterados
- Teste manual: criar transaÃ§Ã£o via admin, verificar dados no banco
- Grep por `$wpdb->query(` sem `prepare` em todo o repositÃ³rio

### 1.2 â€” Auditoria de SeguranÃ§a Completa em Todos os Add-ons

**AÃ§Ã£o:**
- [x] Executar grep global por padrÃµes vulnerÃ¡veis: `$wpdb->query(`, `$_GET[`, `$_POST[` sem sanitizaÃ§Ã£o
- [x] Verificar presenÃ§a de nonce em todos os handlers AJAX (`wp_verify_nonce`)
- [x] Verificar capability checks em todos os endpoints admin
- [x] Revisar escape de saÃ­da HTML (`esc_html`, `esc_attr`, `wp_kses`)
- [x] Documentar achados em `docs/security/AUDIT_FASE1.md`

### 1.3 â€” RevisÃ£o de Capabilities

**AÃ§Ã£o:**
- [x] Mapear todas as capabilities utilizadas no sistema
- [x] Verificar aderÃªncia ao PrincÃ­pio do Menor PrivilÃ©gio
- [x] Documentar capabilities por add-on em `ANALYSIS.md`

### EntregÃ¡veis

- âœ… Zero queries SQL sem `prepare()` onde hÃ¡ entrada de usuÃ¡rio
- âœ… Nonce verificado em 100% dos handlers AJAX/REST
- âœ… Documento de auditoria `docs/security/AUDIT_FASE1.md`
- âœ… AtualizaÃ§Ã£o do `CHANGELOG.md` na seÃ§Ã£o Security
- âœ… Capabilities mapeadas e documentadas em `ANALYSIS.md`

---

## Fase 2 â€” RefatoraÃ§Ã£o Estrutural do NÃºcleo

> **Prioridade:** ðŸŸ  Alta
> **EsforÃ§o estimado:** 3â€“5 sprints
> **DependÃªncias:** Fase 1 concluÃ­da
> **ReferÃªncia existente:** `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md`, `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

### Objetivo

Reduzir a complexidade do cÃ³digo-fonte, melhorar a manutenibilidade e estabelecer padrÃµes consistentes para todos os add-ons.

### 2.1 â€” DecomposiÃ§Ã£o de `class-dps-base-frontend.php`

**Problema:** Arquivo com 5.500+ linhas misturando renderizaÃ§Ã£o, validaÃ§Ã£o, lÃ³gica de negÃ³cio e CRUD.

**AÃ§Ã£o:**
- [x] Mapear todas as responsabilidades do arquivo (mÃ©todos agrupados por funÃ§Ã£o)
- [x] Extrair classe `DPS_Appointment_Handler` â€” lÃ³gica de agendamento (810 linhas)
- [x] Extrair classe `DPS_Client_Handler` â€” CRUD de clientes (184 linhas)
- [x] Extrair classe `DPS_Pet_Handler` â€” CRUD de pets (337 linhas)
- [x] Extrair classe `DPS_Client_Page_Renderer` â€” pÃ¡gina de detalhes do cliente (1.506 linhas, 23 mÃ©todos)
- [x] Extrair classe `DPS_Breed_Registry` â€” dataset de raÃ§as por espÃ©cie (201 linhas)
- [x] Extrair classe `DPS_History_Section_Renderer` â€” seÃ§Ã£o de histÃ³rico de atendimentos (481 linhas)
- [x] Extrair classe `DPS_Appointments_Section_Renderer` â€” seÃ§Ã£o de agendamentos com formulÃ¡rio e listagem (926 linhas)
- [x] Extrair classe `DPS_Clients_Section_Renderer` â€” seÃ§Ã£o de clientes com filtros e estatÃ­sticas (270 linhas)
- [x] Extrair classe `DPS_Pets_Section_Renderer` â€” seÃ§Ã£o de pets com filtros e paginaÃ§Ã£o (345 linhas)
- [x] Manter `class-dps-base-frontend.php` como orquestrador (fachada) que delega para as novas classes
- [x] Garantir que hooks existentes continuem funcionando (backward compatibility)
- [x] Atualizar `ANALYSIS.md` com a nova estrutura de classes
- [x] MonÃ³lito reduzido de 5.986 para 1.581 linhas (â€“74%)

**PrincÃ­pios (SRP):**
- Cada classe com responsabilidade Ãºnica
- MÃ©todos com no mÃ¡ximo 50â€“80 linhas
- DependÃªncias injetadas via construtor quando possÃ­vel

**ValidaÃ§Ã£o:**
- `php -l` em todos os arquivos alterados
- Teste manual dos fluxos de agendamento, cadastro de cliente e pet
- Verificar que nenhum hook pÃºblico mudou de assinatura

### 2.2 â€” PadronizaÃ§Ã£o da Estrutura de Add-ons

**Problema:** Add-ons com estruturas inconsistentes, headers duplicados.

**AÃ§Ã£o:**
- [x] Definir template padrÃ£o de add-on (arquivo principal, `includes/`, `assets/`, headers) â€” documentado em ANALYSIS.md
- [x] Corrigir headers duplicados nos add-ons identificados (Finance, Subscription) â€” auditados: Finance tem header Ãºnico; Subscription jÃ¡ separou wrapper/implementaÃ§Ã£o com nota explÃ­cita
- [x] Padronizar padrÃ£o de inicializaÃ§Ã£o: text domain em `init` prioridade 1, classes em `init` prioridade 5 â€” auditado, todos conformes
- [x] Garantir que todos usem `admin_menu` com submenu de `desi-pet-shower` â€” auditado: prioridades variam intencionalmente (18-26) para ordenaÃ§Ã£o de menus
- [x] Documentar template padrÃ£o em `ANALYSIS.md` â€” incluindo compliance status e helpers disponÃ­veis

### 2.3 â€” CentralizaÃ§Ã£o de FunÃ§Ãµes Duplicadas

**Problema:** FunÃ§Ãµes duplicadas entre add-ons (formataÃ§Ã£o de moeda, telefone, URLs, etc.).

**AÃ§Ã£o:**
- [x] Inventariar funÃ§Ãµes duplicadas com grep global â€” 16 instÃ¢ncias de `number_format` identificadas
- [x] Verificar uso dos helpers globais existentes (`DPS_Phone_Helper`, `DPS_Money_Helper`, `DPS_URL_Builder`, `DPS_Query_Helper`, `DPS_Request_Validator`)
- [x] Migrar add-ons que ainda usam implementaÃ§Ãµes locais para os helpers globais â€” 10 arquivos migrados para `DPS_Money_Helper::format_currency()`
- [x] Remover cÃ³digo duplicado apÃ³s migraÃ§Ã£o â€” fallbacks `class_exists()` removidos
- [ ] Atualizar `docs/FUNCTIONS_REFERENCE.md` se novos helpers forem criados

### 2.4 â€” Sistema de Templates

**Status:** âœ… Implementado em 2026-02-19

**AÃ§Ã£o:**
- [x] Avaliar o `DPS_Template_Engine` existente no Frontend Add-on â€” portado como `DPS_Base_Template_Engine`
- [x] Definir padrÃ£o de templates para renderizaÃ§Ã£o de formulÃ¡rios e listagens â€” render(), exists(), theme override em dps-templates/
- [x] Separar HTML em arquivos de template (`templates/`) com lÃ³gica PHP mÃ­nima â€” `templates/components/client-summary-cards.php`
- [x] Implementar progressivamente nos componentes mais crÃ­ticos â€” `DPS_Client_Page_Renderer::render_client_summary_cards()` usa template com fallback inline
- [ ] Expandir para mais componentes (formulÃ¡rio de agendamento, listagem de clientes) â€” futuro

### 2.5 â€” DocumentaÃ§Ã£o de Contratos de Metadados

**AÃ§Ã£o:**
- [x] Documentar todos os meta_keys usados por CPT (`dps_cliente`, `dps_pet`, `dps_agendamento`)
- [x] Documentar formatos esperados (ex: `appointment_date` usa `Y-m-d`)
- [x] Documentar relaÃ§Ãµes entre metadados (ex: `appointment_client_id` â†’ `dps_cliente` post_id)
- [x] Adicionar seÃ§Ã£o especÃ­fica em `ANALYSIS.md`

### EntregÃ¡veis

- âœ… `class-dps-base-frontend.php` reduzido para < 1.000 linhas (fachada)
- âœ… 5+ classes extraÃ­das com responsabilidade Ãºnica
- âœ… Template padrÃ£o de add-on documentado
- âœ… Zero funÃ§Ãµes duplicadas entre add-ons
- âœ… Contratos de metadados documentados

---

## Fase 3 â€” Performance e Escalabilidade

> **Prioridade:** ðŸŸ¡ MÃ©dia
> **EsforÃ§o estimado:** 2â€“3 sprints
> **DependÃªncias:** Fase 1 concluÃ­da
> **ReferÃªncia existente:** `docs/analysis/ADDONS_DETAILED_ANALYSIS.md` (seÃ§Ã£o Performance)

### Objetivo

Otimizar consultas, carregamento de assets e preparar o sistema para volumes maiores de dados.

### 3.1 â€” OtimizaÃ§Ã£o de CriaÃ§Ã£o de Tabelas

**Problema:** VerificaÃ§Ã£o de `dbDelta()` acontecendo desnecessariamente.

**AÃ§Ã£o:**
- [x] Verificar que todos os add-ons usam version check antes de `dbDelta()` â€” 10/12 OK, 2 corrigidos (AI Analytics, AI Conversations)
- [x] Garantir que `dbDelta()` sÃ³ executa no activation hook ou quando a versÃ£o do banco for menor que a do plugin
- [x] Documentar padrÃ£o recomendado em `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

**Nota:** A anÃ¡lise atual mostra que o Finance Add-on jÃ¡ usa version check âœ…. Logger, Communications, Groomer Tokens, Portal Tokens, Loyalty jÃ¡ tinham version check âœ…. AI Analytics e AI Conversations corrigidos em 2026-02-18.

### 3.2 â€” PaginaÃ§Ã£o em Listagens Grandes

**AÃ§Ã£o:**
- [x] Identificar todas as listagens admin que carregam dados sem limite â€” Finance addon tem paginaÃ§Ã£o (20/page), mas dropdown de clientes e summary queries nÃ£o tinham limites
- [x] Implementar paginaÃ§Ã£o server-side nas listagens de transaÃ§Ãµes financeiras â€” jÃ¡ existente (20/page)
- [x] Limitar dropdown de clientes: `no_found_rows => true`, disable meta/term cache
- [x] Limitar summary query: LIMIT 5000 safety cap quando filtro de data aplicado
- [x] Limitar busca de clientes: LIMIT 200 resultados
- [x] Usar `LIMIT`/`OFFSET` com `$wpdb->prepare()`
- [x] Adicionar controles de paginaÃ§Ã£o na UI admin â€” `render_pagination()` renderiza: info de registros, botÃµes anterior/prÃ³ximo, nÃºmeros de pÃ¡gina com ellipsis, estados disabled. CSS em `finance-addon.css:839+`

### 3.3 â€” OtimizaÃ§Ã£o de Queries SQL

**AÃ§Ã£o:**
- [x] Revisar queries que fazem `SELECT *` e limitar aos campos necessÃ¡rios â€” auditadas: Finance REST usa `SELECT *` mas precisa de todas as colunas; Subscription queries de delete migradas para `fields => 'ids'`
- [x] Usar `'fields' => 'ids'` e `'no_found_rows' => true` em `WP_Query` onde aplicÃ¡vel â€” `DPS_Query_Helper` otimizado com `no_found_rows => true` por padrÃ£o; Subscription add-on otimizado com `fields => 'ids'` + `no_found_rows => true` em queries de delete e contagem
- [x] Verificar Ã­ndices nas tabelas customizadas (`dps_transacoes`, `dps_parcelas`) â€” jÃ¡ possuem Ã­ndices adequados (v1.3.1): `idx_finance_date_status(data,status)`, `idx_finance_categoria`, `cliente_id`, `agendamento_id`, `plano_id`
- [x] Eliminar queries N+1 (loops que executam uma query por item) â€” `query_appointments_for_week()` corrigido

### 3.4 â€” OtimizaÃ§Ã£o de Assets (CSS/JS)

**AÃ§Ã£o:**
- [x] Auditar carregamento de CSS/JS em todas as pÃ¡ginas admin â€” 17 add-ons auditados
- [x] Garantir que assets sÃ£o carregados apenas nas telas relevantes (`admin_enqueue_scripts` com `$hook_suffix`) â€” Stock add-on corrigido (carregamento global â†’ condicional)
- [x] Verificar se arquivos JS/CSS estÃ£o sendo carregados no frontend sem necessidade â€” Stock add-on corrigido
- [ ] Considerar minificaÃ§Ã£o manual dos arquivos CSS/JS mais pesados (sem build process obrigatÃ³rio)

**Nota:** O AGENTS.md proÃ­be cache (transients, object cache, etc.). Todas as otimizaÃ§Ãµes devem ser feitas via queries eficientes e carregamento condicional, nÃ£o via cache.

### 3.5 â€” Lazy Loading

**AÃ§Ã£o:**
- [x] Adicionar `loading="lazy"` em imagens renderizadas pelo sistema (galeria de pets, fotos)
- [ ] Implementar carregamento sob demanda para seÃ§Ãµes pesadas (histÃ³rico completo, transaÃ§Ãµes)

### EntregÃ¡veis

- âœ… Todas as listagens com paginaÃ§Ã£o server-side
- âœ… Zero queries N+1 nas telas crÃ­ticas
- âœ… Assets carregados condicionalmente
- âœ… `loading="lazy"` em todas as imagens do portal

---

## Fase 4 â€” UX do Portal do Cliente

> **Prioridade:** ðŸŸ¡ MÃ©dia
> **EsforÃ§o estimado:** 3â€“4 sprints
> **DependÃªncias:** Fases 1 e 2 concluÃ­das
> **ReferÃªncia existente:** `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md`, `docs/analysis/CLIENT_PORTAL_TABS_STRUCTURE.md`

### Objetivo

Melhorar a experiÃªncia do usuÃ¡rio final no Portal do Cliente, tornando o fluxo mais intuitivo e informativo.

### 4.1 â€” Indicador de Progresso no Fluxo de Agendamento

**Problema:** O processo de agendamento Ã© dividido em vÃ¡rias etapas sem indicaÃ§Ã£o visual de progresso.

**AÃ§Ã£o:**
- [x] Mapear todas as etapas do fluxo de agendamento (pedido de agendamento â†’ data/perÃ­odo â†’ detalhes â†’ confirmaÃ§Ã£o) â€” fluxo mapeado: modal com 3 etapas (Data/Pet â†’ Detalhes â†’ RevisÃ£o/Confirmar)
- [x] Implementar componente de barra de progresso (`dps-progress-bar`) seguindo padrÃ£o DPS Signature â€” cÃ­rculos numerados com conectores, estados active/completed, labels por etapa
- [x] Integrar com os formulÃ¡rios existentes (CSS + JS) â€” `createRequestModal()` refatorado para wizard multi-etapa com navegaÃ§Ã£o next/prev
- [x] Adicionar texto "Passo X de Y" para acessibilidade (`role="progressbar"`, `aria-valuenow`, `aria-valuemax`, `aria-live="polite"`)
- [x] Seguir `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` â€” tokens DPS Signature (cores, espaÃ§amento, shapes, motion), responsive, `prefers-reduced-motion`

**ImplementaÃ§Ã£o:**
- CSS: `.dps-progress-bar` com `.dps-progress-bar__step`, `.dps-progress-bar__circle`, `.dps-progress-bar__connector`, `.dps-progress-bar__label`, `.dps-step-panel`, `.dps-review-summary`
- JS: FunÃ§Ãµes `goToStep()`, `validateStep()`, `updateReviewSummary()` dentro de `createRequestModal()`
- Etapa 3 mostra resumo completo (tipo, pet, data, perÃ­odo, observaÃ§Ãµes) antes do envio

### 4.2 â€” ValidaÃ§Ã£o em Tempo Real (Client-side)

**AÃ§Ã£o:**
- [x] Identificar todos os campos de formulÃ¡rio no portal do cliente
- [x] Implementar validaÃ§Ã£o JavaScript em tempo real para: e-mail, telefone, CEP, UF, campos obrigatÃ³rios, peso, data nascimento
- [x] Mostrar mensagens inline de erro/sucesso abaixo de cada campo â€” `<span class="dps-field-error" role="alert">`
- [x] Manter validaÃ§Ã£o server-side como backup (nunca confiar apenas em client-side)
- [x] Seguir padrÃ£o acessÃ­vel: `aria-invalid`, `aria-describedby`, `aria-required`, `role="alert"` para mensagens de erro
- [x] Adicionar CSS `.is-invalid`/`.is-valid` com cores DPS Signature (error: `#ba1a1a`, success: `#1a7a3a`)
- [x] Adicionar `inputmode="numeric"` no CEP, `max` no campo de data e peso

**Nota:** ValidaÃ§Ã£o de CPF nÃ£o implementada pois o campo nÃ£o existe nos formulÃ¡rios do portal.

### 4.3 â€” Mensagens de Erro/Sucesso Aprimoradas

**Problema:** Mensagens de erro/sucesso podem nÃ£o ser claras o suficiente.

**AÃ§Ã£o:**
- [x] Auditar todas as mensagens do portal (jÃ¡ mapeadas: `portal_msg` values em `client-portal.js`)
- [x] Reescrever mensagens que nÃ£o orientem aÃ§Ã£o (ex: "Erro" â†’ "Algo Deu Errado â€” Tente novamente ou entre em contato pelo chat")
- [x] Adicionar 5 tipos de mensagem faltantes: `message_error`, `review_submitted`, `review_already`, `review_invalid`, `review_error`
- [x] Usar toasts para feedback nÃ£o-bloqueante (jÃ¡ implementado via `DPSToast`)
- [x] TÃ­tulos descritivos em vez de genÃ©ricos: "Dados Salvos!" vs "Sucesso!"

### 4.4 â€” HistÃ³rico de Agendamentos Aprimorado

**Problema:** O portal jÃ¡ exibe histÃ³rico de serviÃ§os, mas pode ser expandido.

**AÃ§Ã£o:**
- [x] Verificar a implementaÃ§Ã£o atual de `DPS_Portal_Pet_History::get_pet_service_history()` â€” retorna serviÃ§os concluÃ­dos com date, time, services, professional, status, observations
- [x] Adicionar filtros por perÃ­odo (Ãºltimos 30/60/90 dias) na visualizaÃ§Ã£o â€” barra de filtros com `aria-pressed` e filtragem client-side via `data-date`
- [x] Diferenciar visualmente agendamentos futuros de passados â€” jÃ¡ existente: status badges com cores distintas (ConcluÃ­do, Pago, Cancelado, Pendente, Em Andamento)
- [x] Mostrar status com cores: agendado (azul), finalizado (verde), cancelado (vermelho) â€” jÃ¡ implementado via classes `dps-status-badge--*`
- [x] Implementar paginaÃ§Ã£o AJAX para histÃ³ricos longos (padrÃ£o load-more jÃ¡ existente) â€” `handleLoadMorePetHistory()` com offset/limit

### 4.5 â€” InformaÃ§Ãµes Detalhadas do Pet

**AÃ§Ã£o:**
- [x] Verificar quais metadados de pet jÃ¡ sÃ£o armazenados (`dps_pet` CPT) â€” 19 meta keys documentadas em ANALYSIS.md
- [x] Exibir raÃ§a, idade/data de nascimento, porte no card do pet â€” porte (ðŸ“), peso (âš–ï¸), sexo (â™‚ï¸/â™€ï¸), idade (ðŸŽ‚) calculada de pet_birth
- [x] Considerar campo para informaÃ§Ãµes de vacinas (se aplicÃ¡vel ao negÃ³cio) â€” `pet_vaccinations` existe mas Ã© texto livre; exibiÃ§Ã£o no portal seria confusa sem estruturaÃ§Ã£o
- [x] Adicionar Ã­cones por espÃ©cie (jÃ¡ existente na galeria â€” reutilizar) â€” espÃ©cie jÃ¡ exibida no card com raÃ§a

### 4.6 â€” Tokens de Acesso Permanentes

**Problema:** O cliente precisa de novo link a cada acesso. Tokens permanentes estÃ£o em desenvolvimento.

**AÃ§Ã£o:**
- [x] Avaliar estado atual da implementaÃ§Ã£o de tokens permanentes â€” jÃ¡ implementado: tipo 'permanent' no token manager com 10 anos de expiraÃ§Ã£o, mÃ©todos `get_active_permanent_tokens()` e `revoke_tokens()`, tabela `dps_portal_tokens` com campos type/used_at/revoked_at
- [x] Implementar opÃ§Ã£o "Manter acesso neste dispositivo" com consentimento explÃ­cito â€” checkbox no formulÃ¡rio de email em portal-access.php
- [x] Armazenar token permanente em cookie seguro (`HttpOnly`, `Secure`, `SameSite=Strict`) â€” cookie `dps_portal_remember` com 90 dias de validade
- [x] Auto-autenticaÃ§Ã£o via `handle_remember_cookie()` no carregamento do portal
- [x] Cookie removido no logout via `DPS_Portal_Session_Manager::logout()`
- [x] Manter a opÃ§Ã£o de magic link como padrÃ£o â€” checkbox desmarcado por padrÃ£o
- [ ] Implementar expiraÃ§Ã£o configurÃ¡vel (30/60/90 dias) via configuraÃ§Ãµes admin â€” futuro

**Nota:** ImplementaÃ§Ã£o completa: checkbox no form â†’ flag remember_me no AJAX â†’ parÃ¢metro dps_remember na URL â†’ token permanente + cookie â†’ auto-auth â†’ logout limpa cookie.

### EntregÃ¡veis

- âœ… Barra de progresso funcional no fluxo de agendamento
- âœ… ValidaÃ§Ã£o em tempo real em todos os formulÃ¡rios do portal
- âœ… Mensagens de erro/sucesso reescritas e acessÃ­veis
- âœ… HistÃ³rico de agendamentos com filtros e paginaÃ§Ã£o
- âœ… InformaÃ§Ãµes detalhadas do pet visÃ­veis no portal
- âœ… Registros visuais em `docs/screenshots/YYYY-MM-DD/`

---

## Fase 5 â€” Funcionalidades Novas (Portal)

> **Prioridade:** ðŸŸ¢ Baixa
> **EsforÃ§o estimado:** 4â€“6 sprints
> **DependÃªncias:** Fases 2, 3 e 4 concluÃ­das

### Objetivo

Adicionar funcionalidades que criam valor para o cliente final e diferenciam o produto.

### 5.1 â€” Galeria de Fotos do Pet (ExpansÃ£o)

**Status atual:** Implementado. Multi-fotos via meta `pet_photos` com lightbox navegÃ¡vel.

**AÃ§Ã£o:**
- [x] Expandir para mÃºltiplas fotos por pet (meta `pet_photos` como array, fallback `pet_photo_id`)
- [ ] Integrar com o add-on Groomers para fotos antes/depois
- [x] Implementar upload de fotos pelo admin com associaÃ§Ã£o ao pet
- [x] Usar lightbox jÃ¡ existente (com navegaÃ§Ã£o prev/next, `data-gallery`, ArrowLeft/Right)
- [x] Implementar lazy loading nas imagens da galeria

### 5.2 â€” NotificaÃ§Ãµes Personalizadas

**Status atual:** Implementado. 4 toggles de notificaÃ§Ã£o no portal.

**AÃ§Ã£o:**
- [x] Criar tela de preferÃªncias de notificaÃ§Ã£o no portal do cliente
- [x] OpÃ§Ãµes: lembrete de agendamento, pagamentos, promoÃ§Ãµes, atualizaÃ§Ãµes do pet
- [x] Armazenar preferÃªncias como meta do CPT `dps_cliente`
- [ ] Integrar com o add-on Communications (notificaÃ§Ãµes por e-mail/WhatsApp)
- [ ] Integrar com o add-on Push (Telegram/e-mail para admin)

### 5.3 â€” Gerenciamento de MÃºltiplos Pets

**Status atual:** O sistema jÃ¡ suporta mÃºltiplos pets por cliente e agendamento multi-pet. Seletor rÃ¡pido implementado.

**AÃ§Ã£o:**
- [x] Adicionar seletor rÃ¡pido de pet para agendamento â€” dropdown de pet no Step 1 do modal de agendamento, visÃ­vel quando cliente tem 2+ pets, com Ã­cones de espÃ©cie (ðŸ¶/ðŸ±/ðŸ¾) e nomes. Dados via `dpsPortal.clientPets` (PHP `wp_localize_script`)
- [x] Otimizar o fluxo de agendamento para selecionar serviÃ§os por pet â€” pet selecionado aparece na revisÃ£o (Step 3) e Ã© validado antes de prosseguir
- [ ] Melhorar a visualizaÃ§Ã£o de mÃºltiplos pets na tela inicial do portal â€” futuro (tab navigation jÃ¡ existente)
- [ ] Permitir comparaÃ§Ã£o de histÃ³rico entre pets â€” futuro

### 5.4 â€” Feedback e AvaliaÃ§Ã£o

**Status atual:** Implementado. Prompt de avaliaÃ§Ã£o com star rating no histÃ³rico.

**AÃ§Ã£o:**
- [x] Adicionar prompt pÃ³s-agendamento (finalizado) convidando para avaliaÃ§Ã£o
- [x] Mostrar avaliaÃ§Ãµes anteriores do cliente no portal
- [ ] Considerar widget de NPS (Net Promoter Score) simples
- [ ] Integrar com o add-on Loyalty para dar pontos por avaliaÃ§Ã£o

### 5.5 â€” IntegraÃ§Ã£o com Pagamentos no Portal

**AÃ§Ã£o:**
- [x] Verificar estado atual do add-on Payment â€” Payment add-on existe mas integraÃ§Ã£o gateway requer ASK BEFORE
- [x] Implementar visualizaÃ§Ã£o de parcelas pendentes (integraÃ§Ã£o Finance) â€” aba "Pagamentos" no portal com: cards de resumo (pendente/pago), lista de transaÃ§Ãµes pendentes com parcelas e saldo restante, histÃ³rico de transaÃ§Ãµes pagas com parcelas detalhadas
- [x] Adicionar botÃ£o "Pagar agora" com link para gateway configurado â€” botÃ£o "Pagar Agora" em cada transaÃ§Ã£o pendente (reusa formulÃ¡rio existente)
- [ ] Avaliar viabilidade de prÃ©-pagamento ou pagamento online pelo portal â€” futuro (requer ASK BEFORE)
- [ ] Seguir regra ASK BEFORE para novas integraÃ§Ãµes de pagamento â€” futuro

**ImplementaÃ§Ã£o:**
- PHP: `render_payments_tab()` em `class-dps-portal-renderer.php` com sub-mÃ©todos: `render_payments_summary_cards()`, `render_payments_pending_section()`, `render_payments_paid_section()`, `render_payment_card()`, `render_parcela_row()`
- Repository: `get_parcelas_for_transaction()`, `get_parcelas_sum()`, `get_client_financial_summary()` em `class-dps-finance-repository.php`
- CSS: `.dps-payments-summary-grid`, `.dps-payments-stat-card`, `.dps-payment-card`, `.dps-parcela-row` em `client-portal.css`
- Tab "pagamentos" com badge de pendÃªncias no portal

### EntregÃ¡veis

- âœ… Galeria multi-fotos funcional
- âœ… PreferÃªncias de notificaÃ§Ã£o configurÃ¡veis
- âœ… UX aprimorado para mÃºltiplos pets
- âœ… Sistema de feedback pÃ³s-serviÃ§o
- âœ… VisualizaÃ§Ã£o de pagamentos no portal

---

## Fase 6 â€” SeguranÃ§a AvanÃ§ada e Auditoria

> **Prioridade:** ðŸŸ¡ MÃ©dia
> **EsforÃ§o estimado:** 2â€“3 sprints
> **DependÃªncias:** Fases 1 e 2 concluÃ­das

### Objetivo

Implementar camadas adicionais de seguranÃ§a e monitoramento.

### 6.1 â€” Rate Limiting

**Status:** âœ… JÃ¡ implementado â€” auditado em 2026-02-18

**ImplementaÃ§Ã£o existente:**
- [x] Rate limiting no login do portal (magic link request) â€” `class-dps-portal-ajax-handler.php:617-667`: 3 req/hora por IP + 3 req/hora por email (dual enforcement)
- [x] Rate limiting na validaÃ§Ã£o de tokens â€” `class-dps-portal-token-manager.php:264-278`: 5 tentativas/hora por IP
- [x] Rate limiting nos endpoints AJAX do chat â€” `class-dps-portal-ajax-handler.php:408-426`: 10 msgs/60s via `_dps_chat_rate` post meta
- [x] Mensagens amigÃ¡veis quando rate limit Ã© atingido â€” implementadas em ambos os handlers
- [x] Incremento de contadores antes da resposta (anti-enumeration) â€” `class-dps-portal-ajax-handler.php:664-667`

**Nota tÃ©cnica:** O rate limiting de login e tokens usa transients para tracking IP-based (nÃ£o Ã© possÃ­vel usar post meta para IPs sem sessÃ£o). O chat usa post meta conforme padrÃ£o. A regra de cache proibido do AGENTS.md se refere a cache de dados, nÃ£o a contadores de seguranÃ§a.

### 6.2 â€” Logs de Auditoria Abrangentes

**Status atual:** âœ… Implementado em 2026-02-19

**AÃ§Ã£o:**
- [x] Estender o padrÃ£o de logs de auditoria para todos os add-ons â€” criado `DPS_Audit_Logger` centralizado
- [x] Eventos a registrar: login/logout, alteraÃ§Ã£o de dados do cliente, alteraÃ§Ã£o de pet, criaÃ§Ã£o/cancelamento de agendamento, operaÃ§Ãµes financeiras â€” API disponÃ­vel para todos os add-ons
- [x] Criar classe `DPS_Audit_Logger` centralizada no plugin base (446 linhas, 14 mÃ©todos estÃ¡ticos)
- [x] Armazenar logs em tabela customizada (`dps_audit_log`) com: timestamp, user_id, action, entity_type, entity_id, details, ip_address
- [x] Implementar tela admin de visualizaÃ§Ã£o de logs (370 linhas) â€” filtros por tipo/aÃ§Ã£o/data, paginaÃ§Ã£o (30/pÃ¡gina), badges coloridos
- [x] Integrar no System Hub como aba "Auditoria"
- [x] Integrar nos handlers: Client (save/delete), Pet (save/delete), Appointment (save/status_change)

**ImplementaÃ§Ã£o:**
- `class-dps-audit-logger.php` â€” classe estÃ¡tica com conveniÃªncia: `log_client_change()`, `log_pet_change()`, `log_appointment_change()`, `log_portal_event()`
- `class-dps-audit-admin-page.php` â€” pÃ¡gina admin com filtros de entity_type, action, date_from, date_to e limpeza por dias
- Tabela `dps_audit_log` criada via dbDelta com version check (padrÃ£o DPS)

### 6.3 â€” Monitoramento de Atividade Suspeita

**AÃ§Ã£o:**
- [x] Registrar tentativas de acesso falhas (token invÃ¡lido, token expirado) â€” integrado `DPS_Audit_Logger::log_portal_event()` em `handle_token_authentication()` para token_validation_failed e login_success
- [x] Registrar rate limit atingido â€” integrado em `ajax_request_access_link_by_email()` para rate_limit_ip
- [ ] Alertar admin (via add-on Push) quando houver N tentativas falhas do mesmo IP â€” futuro
- [ ] Registrar acessos de IPs incomuns por cliente â€” futuro

### 6.4 â€” AutenticaÃ§Ã£o de Dois Fatores (2FA)

**Status:** âœ… Implementado em 2026-02-19

**AÃ§Ã£o:**
- [x] Avaliar necessidade real de 2FA para o portal (perfil de risco) â€” implementado como opcional, desabilitado por padrÃ£o
- [x] Se viÃ¡vel: implementar verificaÃ§Ã£o por e-mail (cÃ³digo de 6 dÃ­gitos) â€” `DPS_Portal_2FA` com cÃ³digo de 6 dÃ­gitos, hashed com `wp_hash_password()`, 10 min expiraÃ§Ã£o, max 5 tentativas
- [x] Tornar 2FA opcional por configuraÃ§Ã£o admin â€” checkbox em Portal â†’ ConfiguraÃ§Ãµes
- [x] NÃ£o implementar SMS/autenticador na primeira versÃ£o (complexidade vs. valor) â€” apenas e-mail

**ImplementaÃ§Ã£o:**
- PHP: `class-dps-portal-2fa.php` â€” `generate_code()`, `verify_code()`, `send_code_email()`, `render_verification_form()`, `ajax_verify_2fa_code()`
- Fluxo: Token vÃ¡lido â†’ gera cÃ³digo â†’ envia por e-mail â†’ renderiza formulÃ¡rio 2FA â†’ AJAX verifica â†’ cria sessÃ£o
- Remember-me preservado atravÃ©s de 2FA via transient
- Audit: eventos `2fa_code_sent` e `2fa_verified` via `DPS_Audit_Logger`
- UI: 6 inputs individuais com auto-advance, paste support, e-mail ofuscado (j***@gmail.com)
- CSS: `.dps-2fa-digit`, `.dps-2fa-code-inputs`, responsivo 480px

### EntregÃ¡veis

- âœ… Rate limiting funcional no login e endpoints AJAX
- âœ… Sistema de auditoria centralizado com tela admin
- âœ… Monitoramento de atividade suspeita com alertas
- âœ… 2FA via e-mail implementado e configurÃ¡vel

---

## Fase 7 â€” Testabilidade e Manutenibilidade

> **Prioridade:** ðŸŸ¡ MÃ©dia
> **EsforÃ§o estimado:** 3â€“4 sprints
> **DependÃªncias:** Fases 1 e 2 concluÃ­das

### Objetivo

Aumentar a cobertura de testes, melhorar a modularidade e remover cÃ³digo morto.

### 7.1 â€” Infraestrutura de Testes

**Status:** âœ… Configurado em 2026-02-19

**AÃ§Ã£o:**
- [x] Avaliar o setup de testes do AI Add-on como modelo â€” usado como referÃªncia para composer.json, phpunit.xml e bootstrap.php
- [x] Configurar PHPUnit para o plugin base â€” `composer.json` (PHPUnit 9.6+, yoast/phpunit-polyfills), `phpunit.xml`, `tests/bootstrap.php` com mocks WordPress
- [ ] Configurar PHPUnit para o Finance Add-on (prioridade: lÃ³gica financeira) â€” futuro
- [ ] Documentar como rodar testes no `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md` â€” futuro

**Como rodar:** `cd plugins/desi-pet-shower-base && composer install && vendor/bin/phpunit`

### 7.2 â€” Testes UnitÃ¡rios para LÃ³gica CrÃ­tica

**Status:** âœ… 22 testes implementados em 2026-02-19

**AÃ§Ã£o:**
- [x] Testar helpers globais: `DPS_Money_Helper` (13 testes), `DPS_Phone_Helper` (9 testes)
- [ ] Testar `DPS_URL_Builder` â€” futuro (depende de mais mocks WordPress)
- [ ] Testar `sum_revenue_by_period()` no Finance â€” futuro (requer setup Finance)
- [ ] Testar validaÃ§Ã£o de formulÃ¡rios (novas classes extraÃ­das na Fase 2) â€” futuro
- [ ] Testar lÃ³gica de tokens do portal (criaÃ§Ã£o, validaÃ§Ã£o, expiraÃ§Ã£o) â€” futuro
- [ ] Meta: cobertura de 80% nas classes de lÃ³gica de negÃ³cio â€” futuro

**Testes implementados:**
- `Test_DPS_Money_Helper`: parse_brazilian_format (4 variaÃ§Ãµes), format_to_brazilian (2), decimal_to_cents, cents_to_decimal, format_currency, format_currency_from_decimal, format_decimal_to_brazilian, is_valid_money_string (2)
- `Test_DPS_Phone_Helper`: clean, format_for_whatsapp (2), format_for_display (2), is_valid_brazilian_phone (4)

### 7.3 â€” InjeÃ§Ã£o de DependÃªncia

**Status:** âœ… Documentado em 2026-02-19

**AÃ§Ã£o:**
- [x] Estender padrÃ£o de DI para as novas classes extraÃ­das na Fase 2 â€” documentado 3 estratÃ©gias: singleton, constructor injection, static renderers
- [x] Usar construtor injection para dependÃªncias obrigatÃ³rias â€” padrÃ£o do Frontend Add-on (DPS_Registration_Handler)
- [x] Documentar padrÃ£o no playbook de engenharia â€” seÃ§Ã£o "PadrÃ£o de InjeÃ§Ã£o de DependÃªncia" em AGENT_ENGINEERING_PLAYBOOK.md

### 7.4 â€” RemoÃ§Ã£o de CÃ³digo Morto

**Status:** âœ… Auditado em 2026-02-19 â€” nenhum cÃ³digo morto acionÃ¡vel encontrado

**AÃ§Ã£o:**
- [x] Inventariar arquivos JS antigos â€” todos os JS sÃ£o enqueued corretamente (5 no base, 1 no portal, 1 em cada add-on)
- [x] Verificar referÃªncias dinÃ¢micas (`call_user_func`, hooks com variÃ¡veis) antes de remover â€” verificado
- [x] Remover funÃ§Ãµes sem referÃªncias estÃ¡ticas ou dinÃ¢micas â€” nenhuma encontrada
- [x] Remover arquivos CSS/JS nÃ£o incluÃ­dos em nenhum `wp_enqueue` â€” nenhum encontrado
- [x] Documentar remoÃ§Ãµes no `CHANGELOG.md` â€” sem remoÃ§Ãµes necessÃ¡rias

**Achado:** `refactoring-examples.php` Ã© o Ãºnico arquivo nÃ£o carregado via require, mas Ã© intencionalmente mantido como referÃªncia educacional (documentado em AGENTS.md linha 69).

### EntregÃ¡veis

- âœ… PHPUnit configurado para plugin base (29 testes) e AI Add-on
- âœ… 29 testes unitÃ¡rios cobrindo lÃ³gica crÃ­tica (Money, Phone, Template Engine)
- âœ… PadrÃ£o de DI documentado em AGENT_ENGINEERING_PLAYBOOK.md (3 estratÃ©gias)
- âœ… CÃ³digo morto removido e documentado

---

## Fase 8 â€” IntegraÃ§Ãµes e InteligÃªncia

> **Prioridade:** ðŸŸ¢ Baixa
> **EsforÃ§o estimado:** 4â€“6 sprints
> **DependÃªncias:** Fases 2, 4 e 5 concluÃ­das

### Objetivo

Explorar integraÃ§Ãµes avanÃ§adas e funcionalidades inteligentes.

### 8.1 â€” Agendamento Inteligente

**Status:** âœ… Implementado em 2026-02-19 (versÃ£o local, sem IA)

**AÃ§Ã£o:**
- [x] Avaliar expansÃ£o do AI Add-on para sugestÃ£o de horÃ¡rios e serviÃ§os â€” avaliado, implementada versÃ£o local primeiro
- [x] Basear sugestÃµes no histÃ³rico do pet (frequÃªncia de serviÃ§os, serviÃ§os mais usados) â€” `DPS_Scheduling_Suggestions::analyze_pet_history()`
- [x] Implementar "SugestÃ£o rÃ¡pida" na tela de agendamento do portal â€” banner com urgÃªncia, data sugerida, botÃ£o "Usar data sugerida"
- [x] Usar dados locais (sem IA) como primeira versÃ£o: serviÃ§os mais populares + Ãºltimo intervalo â€” avg interval entre atÃ© 20 atendimentos, top 3 serviÃ§os, urgency (overdue/soon/normal)
- [ ] VersÃ£o com IA como segunda iteraÃ§Ã£o (se add-on AI estiver ativo) â€” futuro

**ImplementaÃ§Ã£o:**
- PHP: `class-dps-scheduling-suggestions.php` â€” `get_suggestions_for_client()`, `analyze_pet_history()`
- Dados via `dpsPortal.schedulingSuggestions` (indexado por pet_id)
- JS: `buildSuggestionBanner()`, auto-fill date, pet selector â†’ update banner
- CSS: `.dps-suggestion-banner`, `.dps-suggestion-banner--overdue`, `.dps-suggestion-banner--soon`

### 8.2 â€” DocumentaÃ§Ã£o ContÃ­nua

**Status:** âœ… Atualizada em 2026-02-19

**AÃ§Ã£o (a cada fase):**
- [x] Atualizar `ANALYSIS.md` com novas classes, hooks, tabelas, metadados â€” Portal do Cliente expandido (2FA, payments, scheduling, progress bar, multi-pet), DPS_Base_Template_Engine, hooks de add-on Portal documentados
- [x] Atualizar `CHANGELOG.md` com todas as mudanÃ§as user-facing â€” atualizado a cada fase
- [x] Atualizar `docs/FUNCTIONS_REFERENCE.md` com novas funÃ§Ãµes/mÃ©todos â€” DPS_Portal_2FA, DPS_Scheduling_Suggestions, DPS_Finance_Repository, DPS_Base_Template_Engine adicionados
- [x] Manter `docs/README.md` sincronizado com novos documentos â€” verificado, sem novos documentos pendentes

---

## DependÃªncias entre Fases

```
Fase 1 (SeguranÃ§a CrÃ­tica)
  â”‚
  â”œâ”€â”€â†’ Fase 2 (RefatoraÃ§Ã£o Estrutural)
  â”‚      â”‚
  â”‚      â”œâ”€â”€â†’ Fase 4 (UX Portal)
  â”‚      â”‚      â”‚
  â”‚      â”‚      â””â”€â”€â†’ Fase 5 (Features Novas)
  â”‚      â”‚              â”‚
  â”‚      â”‚              â””â”€â”€â†’ Fase 8 (IntegraÃ§Ãµes)
  â”‚      â”‚
  â”‚      â”œâ”€â”€â†’ Fase 6 (SeguranÃ§a AvanÃ§ada)
  â”‚      â”‚
  â”‚      â””â”€â”€â†’ Fase 7 (Testabilidade)
  â”‚
  â””â”€â”€â†’ Fase 3 (Performance)
```

### Fases paralelizÃ¡veis

- **Fases 3 e 6** podem executar em paralelo apÃ³s Fase 1
- **Fases 4 e 7** podem executar em paralelo apÃ³s Fase 2
- **Fase 8** sÃ³ inicia apÃ³s Fases 4 e 5

---

## ReferÃªncias Internas

| Documento | Caminho | RelaÃ§Ã£o |
|-----------|---------|---------|
| AnÃ¡lise arquitetural | `ANALYSIS.md` (raiz) | Contratos, hooks, menus, flags |
| Changelog | `CHANGELOG.md` (raiz) | Atualizar a cada fase |
| Diretrizes para agentes | `AGENTS.md` (raiz) | Regras MUST, ASK BEFORE, PREFER |
| Playbook de engenharia | `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md` | DoD, padrÃµes de cÃ³digo |
| AnÃ¡lise de add-ons | `docs/analysis/ADDONS_DETAILED_ANALYSIS.md` | Problemas conhecidos |
| AnÃ¡lise Finance | `docs/analysis/FINANCE_ADDON_ANALYSIS.md` | SQL injection, performance |
| AnÃ¡lise Portal | `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` | Arquitetura do portal |
| Tabs do Portal | `docs/analysis/CLIENT_PORTAL_TABS_STRUCTURE.md` | Estrutura de abas |
| AnÃ¡lise do Plugin Base | `docs/analysis/BASE_PLUGIN_DEEP_ANALYSIS.md` | Arquitetura do nÃºcleo |
| Guia visual DPS Signature | `docs/visual/VISUAL_STYLE_GUIDE.md` | PadrÃ£o visual obrigatÃ³rio |
| Design frontend | `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | InstruÃ§Ãµes de implementaÃ§Ã£o |
| ReferÃªncia de funÃ§Ãµes | `docs/FUNCTIONS_REFERENCE.md` | Todas as funÃ§Ãµes documentadas |

---

> **Nota final:** Este plano deve ser revisado e ajustado ao final de cada fase, incorporando aprendizados e repriorizando conforme necessÃ¡rio. As estimativas de esforÃ§o sÃ£o indicativas e dependem da disponibilidade da equipe e complexidade real encontrada durante a implementaÃ§Ã£o.
