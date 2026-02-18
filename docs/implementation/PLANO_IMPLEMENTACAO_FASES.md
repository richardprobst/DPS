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
- [ ] Extrair classe `DPS_Form_Renderer` — renderização de formulários HTML
- [ ] Extrair classe `DPS_Form_Validator` — validação de campos
- [x] Extrair classe `DPS_Appointment_Handler` — lógica de agendamento
- [x] Extrair classe `DPS_Client_Handler` — CRUD de clientes
- [x] Extrair classe `DPS_Pet_Handler` — CRUD de pets
- [x] Manter `class-dps-base-frontend.php` como orquestrador (fachada) que delega para as novas classes
- [x] Garantir que hooks existentes continuem funcionando (backward compatibility)
- [x] Atualizar `ANALYSIS.md` com a nova estrutura de classes

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
- [ ] Corrigir headers duplicados nos add-ons identificados (Finance, Subscription)
- [x] Padronizar padrão de inicialização: text domain em `init` prioridade 1, classes em `init` prioridade 5 — auditado, todos conformes
- [ ] Garantir que todos usem `admin_menu` prioridade 20 com submenu de `desi-pet-shower`
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

**Problema:** HTML misturado com lógica PHP em arquivos monolíticos (3.000+ linhas).

**Ação:**
- [ ] Avaliar o `DPS_Template_Engine` existente no Frontend Add-on
- [ ] Definir padrão de templates para renderização de formulários e listagens
- [ ] Separar HTML em arquivos de template (`templates/`) com lógica PHP mínima
- [ ] Implementar progressivamente nos componentes mais críticos (formulário de agendamento, listagem de clientes)

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
- [ ] Documentar padrão recomendado em `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

**Nota:** A análise atual mostra que o Finance Add-on já usa version check ✅. Logger, Communications, Groomer Tokens, Portal Tokens, Loyalty já tinham version check ✅. AI Analytics e AI Conversations corrigidos em 2026-02-18.

### 3.2 — Paginação em Listagens Grandes

**Ação:**
- [ ] Identificar todas as listagens admin que carregam dados sem limite
- [ ] Implementar paginação server-side nas listagens de transações financeiras
- [ ] Implementar paginação nas listagens de clientes e agendamentos (se não existir)
- [ ] Usar `LIMIT`/`OFFSET` com `$wpdb->prepare()`
- [ ] Adicionar controles de paginação na UI admin

### 3.3 — Otimização de Queries SQL

**Ação:**
- [ ] Revisar queries que fazem `SELECT *` e limitar aos campos necessários
- [x] Usar `'fields' => 'ids'` e `'no_found_rows' => true` em `WP_Query` onde aplicável — `DPS_Query_Helper` otimizado com `no_found_rows => true` por padrão
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
- [ ] Mapear todas as etapas do fluxo de agendamento (registro → seleção de pet → data/hora → serviços → confirmação)
- [ ] Implementar componente de barra de progresso (`dps-progress-bar`) seguindo padrão M3
- [ ] Integrar com os formulários existentes (CSS + JS)
- [ ] Adicionar texto "Passo X de Y" para acessibilidade (`aria-label`, `aria-valuenow`)
- [ ] Seguir `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md`

### 4.2 — Validação em Tempo Real (Client-side)

**Ação:**
- [ ] Identificar todos os campos de formulário no portal do cliente
- [ ] Implementar validação JavaScript em tempo real para: e-mail, telefone, CPF, campos obrigatórios
- [ ] Mostrar mensagens inline de erro/sucesso abaixo de cada campo
- [ ] Manter validação server-side como backup (nunca confiar apenas em client-side)
- [ ] Seguir padrão acessível: `aria-invalid`, `aria-describedby` para mensagens de erro

### 4.3 — Mensagens de Erro/Sucesso Aprimoradas

**Problema:** Mensagens de erro/sucesso podem não ser claras o suficiente.

**Ação:**
- [ ] Auditar todas as mensagens do portal (já mapeadas: `portal_msg` values em `client-portal.js`)
- [ ] Reescrever mensagens que não orientem ação (ex: "Erro" → "Não foi possível salvar. Tente novamente ou entre em contato")
- [ ] Garantir consistência via `DPS_Message_Helper`
- [ ] Usar toasts para feedback não-bloqueante (já implementado via `DPSToast`)

### 4.4 — Histórico de Agendamentos Aprimorado

**Problema:** O portal já exibe histórico de serviços, mas pode ser expandido.

**Ação:**
- [ ] Verificar a implementação atual de `DPS_Portal_Pet_History::get_pet_service_history()`
- [ ] Adicionar filtros por período (últimos 30/60/90 dias) na visualização
- [ ] Diferenciar visualmente agendamentos futuros de passados
- [ ] Mostrar status com cores: agendado (azul), finalizado (verde), cancelado (vermelho)
- [ ] Implementar paginação AJAX para históricos longos (padrão load-more já existente)

### 4.5 — Informações Detalhadas do Pet

**Ação:**
- [ ] Verificar quais metadados de pet já são armazenados (`dps_pet` CPT)
- [ ] Exibir raça, idade/data de nascimento, porte no card do pet
- [ ] Considerar campo para informações de vacinas (se aplicável ao negócio)
- [ ] Adicionar ícones por espécie (já existente na galeria — reutilizar)

### 4.6 — Tokens de Acesso Permanentes

**Problema:** O cliente precisa de novo link a cada acesso. Tokens permanentes estão em desenvolvimento.

**Ação:**
- [ ] Avaliar estado atual da implementação de tokens permanentes
- [ ] Implementar opção "Manter acesso neste dispositivo" com consentimento explícito
- [ ] Armazenar token permanente em cookie seguro (`HttpOnly`, `Secure`, `SameSite=Strict`)
- [ ] Implementar expiração configurável (30/60/90 dias) via configurações admin
- [ ] Adicionar avisos de segurança claros ao ativar acesso persistente
- [ ] Manter a opção de magic link como padrão

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

**Status atual:** O portal já possui galeria com uma foto por pet (`pet_photo_id` meta).

**Ação:**
- [ ] Expandir para múltiplas fotos por pet (meta `pet_gallery_ids` como array)
- [ ] Integrar com o add-on Groomers para fotos antes/depois
- [ ] Implementar upload de fotos pelo admin com associação ao pet
- [ ] Usar lightbox já existente (com acessibilidade: `role="dialog"`, focus trap, ESC close)
- [ ] Implementar lazy loading nas imagens da galeria

### 5.2 — Notificações Personalizadas

**Ação:**
- [ ] Criar tela de preferências de notificação no portal do cliente
- [ ] Opções: lembrete de agendamento (e-mail/WhatsApp), promoções, atualizações do pet
- [ ] Armazenar preferências como meta do CPT `dps_cliente`
- [ ] Integrar com o add-on Communications (notificações por e-mail/WhatsApp)
- [ ] Integrar com o add-on Push (Telegram/e-mail para admin)

### 5.3 — Gerenciamento de Múltiplos Pets

**Status atual:** O sistema já suporta múltiplos pets por cliente e agendamento multi-pet.

**Ação:**
- [ ] Melhorar a visualização de múltiplos pets na tela inicial do portal
- [ ] Adicionar seletor rápido de pet para agendamento
- [ ] Permitir comparação de histórico entre pets
- [ ] Otimizar o fluxo de agendamento para selecionar serviços por pet

### 5.4 — Feedback e Avaliação

**Status atual:** O portal já possui sistema de reviews (`dps_groomer_review` CPT) com integração Google Reviews.

**Ação:**
- [ ] Adicionar prompt pós-agendamento (finalizado) convidando para avaliação
- [ ] Mostrar avaliações anteriores do cliente no portal
- [ ] Considerar widget de NPS (Net Promoter Score) simples
- [ ] Integrar com o add-on Loyalty para dar pontos por avaliação

### 5.5 — Integração com Pagamentos no Portal

**Ação:**
- [ ] Verificar estado atual do add-on Payment
- [ ] Avaliar viabilidade de pré-pagamento ou pagamento online pelo portal
- [ ] Implementar visualização de parcelas pendentes (integração Finance)
- [ ] Adicionar botão "Pagar agora" com link para gateway configurado
- [ ] Seguir regra ASK BEFORE para novas integrações de pagamento

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

**Ação:**
- [ ] Implementar rate limiting no login do portal (magic link request)
- [ ] Limitar tentativas de acesso: max 5 por IP por 15 minutos
- [ ] Implementar rate limiting nos endpoints AJAX do chat (já existe parcialmente via `_dps_chat_rate`)
- [ ] Usar post meta ou opção customizada para tracking (sem transients — regra MUST)
- [ ] Retornar mensagem amigável quando rate limit for atingido

### 6.2 — Logs de Auditoria Abrangentes

**Status atual:** Existe `class-dps-finance-audit.php` para o Finance Add-on.

**Ação:**
- [ ] Estender o padrão de logs de auditoria para todos os add-ons
- [ ] Eventos a registrar: login/logout, alteração de dados do cliente, alteração de pet, criação/cancelamento de agendamento, operações financeiras
- [ ] Criar classe `DPS_Audit_Logger` centralizada no plugin base
- [ ] Armazenar logs em tabela customizada (`dps_audit_log`) com: timestamp, user_id, action, entity_type, entity_id, details, ip_address
- [ ] Implementar tela admin de visualização de logs (com filtros e paginação)

### 6.3 — Monitoramento de Atividade Suspeita

**Ação:**
- [ ] Registrar tentativas de acesso falhas (token inválido, token expirado)
- [ ] Alertar admin (via add-on Push) quando houver N tentativas falhas do mesmo IP
- [ ] Registrar acessos de IPs incomuns por cliente

### 6.4 — Autenticação de Dois Fatores (2FA)

> **Nota:** Avaliação de viabilidade — implementação opcional baseada na complexidade.

**Ação:**
- [ ] Avaliar necessidade real de 2FA para o portal (perfil de risco)
- [ ] Se viável: implementar verificação por e-mail (código de 6 dígitos)
- [ ] Tornar 2FA opcional por configuração admin
- [ ] Não implementar SMS/autenticador na primeira versão (complexidade vs. valor)

### Entregáveis

- ✅ Rate limiting funcional no login e endpoints AJAX
- ✅ Sistema de auditoria centralizado com tela admin
- ✅ Monitoramento de atividade suspeita com alertas
- ✅ Avaliação documentada de viabilidade de 2FA

---

## Fase 7 — Testabilidade e Manutenibilidade

> **Prioridade:** 🟡 Média
> **Esforço estimado:** 3–4 sprints
> **Dependências:** Fases 1 e 2 concluídas

### Objetivo

Aumentar a cobertura de testes, melhorar a modularidade e remover código morto.

### 7.1 — Infraestrutura de Testes

**Status atual:** O AI Add-on possui `phpunit.xml` e diretório `tests/`. Nenhum outro add-on tem testes.

**Ação:**
- [ ] Avaliar o setup de testes do AI Add-on como modelo
- [ ] Configurar PHPUnit para o plugin base
- [ ] Configurar PHPUnit para o Finance Add-on (prioridade: lógica financeira)
- [ ] Documentar como rodar testes no `docs/refactoring/AGENT_ENGINEERING_PLAYBOOK.md`

### 7.2 — Testes Unitários para Lógica Crítica

**Ação:**
- [ ] Testar helpers globais: `DPS_Money_Helper`, `DPS_Phone_Helper`, `DPS_URL_Builder`
- [ ] Testar `sum_revenue_by_period()` no Finance (já mencionado em análise)
- [ ] Testar validação de formulários (novas classes extraídas na Fase 2)
- [ ] Testar lógica de tokens do portal (criação, validação, expiração)
- [ ] Meta: cobertura de 80% nas classes de lógica de negócio

### 7.3 — Injeção de Dependência

**Status atual:** O Frontend Add-on já usa DI para `$registrationHandler` e outros services.

**Ação:**
- [ ] Estender padrão de DI para as novas classes extraídas na Fase 2
- [ ] Usar construtor injection para dependências obrigatórias
- [ ] Documentar padrão no playbook de engenharia

### 7.4 — Remoção de Código Morto

**Ação:**
- [ ] Inventariar arquivos JS antigos (mencionados em análises)
- [ ] Verificar referências dinâmicas (`call_user_func`, hooks com variáveis) antes de remover
- [ ] Remover funções sem referências estáticas ou dinâmicas
- [ ] Remover arquivos CSS/JS não incluídos em nenhum `wp_enqueue`
- [ ] Documentar remoções no `CHANGELOG.md`

### Entregáveis

- ✅ PHPUnit configurado para plugin base e Finance
- ✅ 20+ testes unitários cobrindo lógica crítica
- ✅ Padrão de DI documentado e aplicado
- ✅ Código morto removido e documentado

---

## Fase 8 — Integrações e Inteligência

> **Prioridade:** 🟢 Baixa
> **Esforço estimado:** 4–6 sprints
> **Dependências:** Fases 2, 4 e 5 concluídas

### Objetivo

Explorar integrações avançadas e funcionalidades inteligentes.

### 8.1 — Agendamento Inteligente

**Status atual:** O AI Add-on (`desi-pet-shower-ai`) já utiliza OpenAI API para assistente virtual.

**Ação:**
- [ ] Avaliar expansão do AI Add-on para sugestão de horários e serviços
- [ ] Basear sugestões no histórico do pet (frequência de serviços, serviços mais usados)
- [ ] Implementar "Sugestão rápida" na tela de agendamento do portal
- [ ] Usar dados locais (sem IA) como primeira versão: serviços mais populares + último intervalo
- [ ] Versão com IA como segunda iteração (se add-on AI estiver ativo)

### 8.2 — Documentação Contínua

**Ação (a cada fase):**
- [ ] Atualizar `ANALYSIS.md` com novas classes, hooks, tabelas, metadados
- [ ] Atualizar `CHANGELOG.md` com todas as mudanças user-facing
- [ ] Atualizar `docs/FUNCTIONS_REFERENCE.md` com novas funções/métodos
- [ ] Manter `docs/README.md` sincronizado com novos documentos

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
| Guia visual M3 | `docs/visual/VISUAL_STYLE_GUIDE.md` | Padrão visual obrigatório |
| Design frontend | `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` | Instruções de implementação |
| Referência de funções | `docs/FUNCTIONS_REFERENCE.md` | Todas as funções documentadas |

---

> **Nota final:** Este plano deve ser revisado e ajustado ao final de cada fase, incorporando aprendizados e repriorizando conforme necessário. As estimativas de esforço são indicativas e dependem da disponibilidade da equipe e complexidade real encontrada durante a implementação.
