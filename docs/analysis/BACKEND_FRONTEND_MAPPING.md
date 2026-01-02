# Mapeamento de Funcionalidades – BACK-END vs FRONT-END
## desi.pet by PRObst (DPS)

**Data de análise**: 2025-11-22  
**Base de análise**: Código-fonte em `/plugin` e `/add-ons`

---

## 1. Back-end (Admin do WordPress)

### 1.1 Funcionalidades de CONFIGURAÇÃO

#### 1.1.1 DPS Logs (Plugin Base)
- **Tipo**: CONFIG
- **Local**: `plugin/desi-pet-shower-base_plugin/includes/class-dps-logs-admin-page.php`
- **Acesso**: Menu próprio "DPS Logs" (via `add_menu_page`)
- **Funcionalidade**: Visualização de logs técnicos do sistema (debug, erros, avisos)
- **Observações**: Menu admin nativo correto. Puramente configuração/debug.

#### 1.1.2 Campanhas & Fidelidade - Configurações (Loyalty Add-on)
- **Tipo**: CONFIG
- **Local**: `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php`
- **Acesso**: Menu principal "desi.pet by PRObst" + Submenu "Campanhas & Fidelidade" (linhas 175-192)
- **Funcionalidade**: 
  - Definir valor por ponto (R$/ponto)
  - Gerenciar programa "Indique e Ganhe"
  - Visualizar logs de pontos dos clientes
  - Configurar recompensas e bonificações
- **Observações**: Menu admin correto. Configurações globais do programa de fidelidade.

#### 1.1.3 Campanhas - Lista (Loyalty Add-on)
- **Tipo**: CONFIG/OPERAÇÃO MISTA
- **Local**: `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php` (linha 194-200)
- **Acesso**: Submenu "Campanhas" → redirecionamento para `edit.php?post_type=dps_campaign`
- **Funcionalidade**: Interface admin nativa do CPT `dps_campaign` para criar/editar campanhas de marketing
- **Observações**: Mix de configuração (criar template de campanha) e operação (executar campanha). Correto estar no admin.

#### 1.1.4 Pagamentos - Configuração Mercado Pago (Payment Add-on)
- **Tipo**: CONFIG
- **Local**: `add-ons/desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php`
- **Acesso**: Submenu em "Configurações → DPS Pagamentos" (via `add_options_page`, linha 70)
- **Funcionalidade**:
  - Configurar Access Token do Mercado Pago
  - Configurar Chave PIX
  - Configurar Webhook Secret
- **Observações**: Configuração pura. Correto estar no admin.

#### 1.1.5 Cadastro Público - Configuração Google Maps (Registration Add-on)
- **Tipo**: CONFIG
- **Local**: `add-ons/desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php`
- **Acesso**: Submenu em "Configurações → DPS Cadastro" (via `add_options_page`, linha 64)
- **Funcionalidade**: Configurar Google Maps API Key para autocomplete de endereços
- **Observações**: Configuração pura. Correto estar no admin.

---

### 1.2 Funcionalidades de OPERAÇÃO (no Admin)

**NENHUMA funcionalidade operacional exclusiva foi encontrada no back-end admin.**

- Todos os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) têm `show_ui => false`
- Não há interfaces admin nativas para CRUD operacional
- Todo gerenciamento operacional é feito via front-end (shortcode `[dps_base]`)

**Observação importante**: Conforme memória "coexistência admin e front-end", há plano documentado em `docs/admin/ADMIN_CPT_INTERFACE_ANALYSIS.md` para **habilitar interfaces admin** para gerentes fazerem operações avançadas (bulk actions, análises) enquanto o front-end permanece para recepcionistas (uso no balcão).

---

## 2. Front-end (Shortcodes, Portal do Cliente, Formulários Públicos)

### 2.1 Funcionalidades de OPERAÇÃO (correto estar no front)

#### 2.1.1 Painel Principal de Gestão - [dps_base]
- **Exposição**: Shortcode `[dps_base]`
- **Local**: `plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php` (método `render_app`)
- **Tipo**: OPERAÇÃO
- **Funcionalidades**:
  - **Seção Clientes**: CRUD completo de clientes (criar, editar, listar, excluir)
  - **Seção Pets**: CRUD completo de pets (criar, editar, listar, excluir, upload de foto)
  - **Seção Agenda**: Criar e editar agendamentos, selecionar serviços, multi-pet, cálculo de valores
  - **Seção Histórico**: Visualizar agendamentos finalizados, atualizar status, exportar CSV
  - **Seção Senhas**: Gerar/resetar senhas de acesso ao portal para clientes
- **Endpoints AJAX utilizados**:
  - `dps_get_available_times`: buscar horários disponíveis para agendamento
- **Endpoints REST utilizados**:
  - `/dps/v1/pets`: listar pets com paginação (requer capability `dps_manage_pets`)
- **Observações**: APP PRINCIPAL do sistema. Operação diária no balcão. Correto estar no front-end como shortcode para flexibilidade de acesso (pode ser colocado em página específica).

#### 2.1.2 Portal do Cliente - [dps_client_portal]
- **Exposição**: Shortcode `[dps_client_portal]`
- **Local**: `add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`
- **Tipo**: OPERAÇÃO
- **Funcionalidades**:
  - Área autenticada para clientes (não usa WP users, usa sessão PHP própria)
  - Visualizar histórico de atendimentos
  - Visualizar pendências financeiras
  - Atualizar dados pessoais e de pets
  - Ver código de indicação (integração com Loyalty)
  - Sistema de mensagens entre cliente e pet shop
- **Observações**: Operação pura voltada ao cliente final. Correto estar no front.

#### 2.1.3 Login do Cliente - [dps_client_login]
- **Exposição**: Shortcode `[dps_client_login]`
- **Local**: `add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`
- **Tipo**: OPERAÇÃO
- **Funcionalidade**: Formulário de login para clientes acessarem o portal
- **Observações**: Operação pura. Correto estar no front.

#### 2.1.4 Cadastro Público - [dps_registration_form]
- **Exposição**: Shortcode `[dps_registration_form]`
- **Local**: `add-ons/desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php`
- **Tipo**: OPERAÇÃO
- **Funcionalidades**:
  - Formulário público para clientes se cadastrarem antes do primeiro atendimento
  - Cadastro de cliente + pets em uma única tela
  - Autocomplete de endereço via Google Maps API
  - Confirmação por email
  - Captura código de indicação (integração com Loyalty)
- **Observações**: Operação pública. Cliente faz o próprio cadastro. Correto estar no front.

#### 2.1.5 Visualização de Agenda - [dps_agenda_page]
- **Exposição**: Shortcode `[dps_agenda_page]`
- **Local**: `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`
- **Tipo**: OPERAÇÃO
- **Funcionalidades**:
  - Visualizar agendamentos do dia/semana
  - Filtros por data, status, serviço
  - Atualizar status de agendamentos via AJAX
- **Endpoints AJAX**:
  - `dps_update_status`: atualizar status de agendamento
  - `dps_get_services_details`: buscar detalhes de serviços para cálculo
- **Observações**: Operação diária. Correto estar no front.

#### 2.1.6 Cobranças e Notas - [dps_charges_notes] (DEPRECATED)
- **Exposição**: Shortcode `[dps_charges_notes]`
- **Local**: `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php` (linha 35)
- **Tipo**: OPERAÇÃO
- **Funcionalidade**: Exibir lista de cobranças pendentes e permitir gerar notas/boletos
- **Observações**: Marcado como DEPRECATED no código. Operação, mas deveria estar no Finance Add-on.

#### 2.1.7 Documentos Financeiros - [dps_fin_docs]
- **Exposição**: Shortcode `[dps_fin_docs]`
- **Local**: `add-ons/desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php` (linha 107)
- **Tipo**: OPERAÇÃO
- **Funcionalidade**: Visualizar e baixar documentos financeiros (notas, recibos)
- **Observações**: Operação. Correto estar no front para acesso tanto de staff quanto de clientes via portal.

---

### 2.2 Funcionalidades de CONFIGURAÇÃO no FRONT-END ⚠️ (PROBLEMA)

#### 2.2.1 🔴 Configurações Gerais - [dps_configuracoes]
- **Exposição**: Shortcode `[dps_configuracoes]`
- **Local**: `plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php` (método `render_settings`)
- **Tipo**: ⚠️ **CONFIGURAÇÃO EXPOSTA NO FRONT**
- **Funcionalidades configuráveis**:
  
  **ABA "Backup & Restauração"** (Backup Add-on):
  - Exportar backup completo do sistema (JSON)
  - Importar/restaurar dados de backup
  - **CRÍTICO**: Operação que pode sobrescrever todo o banco de dados
  
  **ABA "Comunicações"** (Communications Add-on):
  - Configurar chaves de API do WhatsApp
  - Configurar endpoint/base URL do WhatsApp
  - Configurar e-mail remetente padrão
  - Editar templates de mensagens (confirmação, lembrete, pós-atendimento)
  
  **ABA "Notificações"** (Push Add-on):
  - Configurar destinatários de notificações diárias
  - Configurar horários de envio de relatórios
  - Configurar integração com Telegram (bot token, chat IDs)
  - Configurar envio de relatórios financeiros e de pets inativos
  
  **ABA "Logins de Clientes"** (Client Portal Add-on):
  - Gerenciar logins e senhas de clientes
  - Resetar senhas
  - Visualizar credenciais de acesso

  **ABA "Financeiro"** (Finance Add-on):
  - Possivelmente configurações de parâmetros financeiros (não confirmado na análise rápida)

  **ABA "Serviços"** (Services Add-on):
  - Criar/editar serviços do catálogo
  - Definir preços por porte (pequeno, médio, grande)
  - Definir duração dos serviços
  - ⚠️ **Mix**: criar serviços é CONFIG, mas editar preços diariamente pode ser OPERAÇÃO

  **ABA "Campanhas & Fidelidade"** (Loyalty Add-on):
  - Visualizar programa de fidelidade
  - Gerenciar pontos de clientes
  - ⚠️ **Possível duplicação** com menu admin

- **Motivo de ser CONFIG**:
  - Chaves de API (WhatsApp, Telegram, Google Maps) são segredos sensíveis
  - Templates de mensagens são padrões globais do sistema
  - Backup/restauração é operação crítica de infraestrutura
  - Horários de notificações são configurações globais
  - Catálogo de serviços define comportamento global de preços

- **Sugestão de correção**:
  1. **Criar página admin "DPS → Configurações"** para centralizar TODAS as configurações
  2. **Mover para admin**:
     - Backup & Restauração
     - Comunicações (chaves de API, templates)
     - Notificações (destinatários, horários, integrações)
     - Logins de Clientes (gerenciamento de credenciais)
  3. **Manter no front** (como abas do `[dps_base]`):
     - Senhas (geração rápida de senha para cliente no balcão)
  4. **Avaliar caso a caso**:
     - Serviços: catálogo inicial no admin, ajuste fino de preços pode ficar no front se usado diariamente
     - Financeiro: depende do que realmente está exposto (precisa análise mais profunda)

- **Risco de segurança**: 
  - ❌ Chaves de API expostas em página front-end (mesmo com capability check)
  - ❌ Backup/restauração acessível fora do admin
  - ❌ Templates de mensagens alteráveis sem auditoria admin

---

## 3. Pontos de ajuste sugeridos

### 3.1 ALTA PRIORIDADE - Segurança e Segregação de Configurações

#### Ação 1: Criar menu admin unificado "DPS → Configurações"
- **O que fazer**: Mover menu "desi.pet by PRObst" do Loyalty para o plugin base
- **Como**: 
  1. Plugin base cria `add_menu_page('desi.pet by PRObst', ..., 'desi-pet-shower', ...)`
  2. Loyalty add-on usa `add_submenu_page('desi-pet-shower', ...)` em vez de criar menu próprio
- **Arquivo**: `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php`
- **Benefício**: Centraliza todos os menus/submenus DPS em um único local

#### Ação 2: Mover Backup & Restauração do front para admin
- **O que fazer**: Remover aba "Backup" do shortcode `[dps_configuracoes]`
- **Como**:
  1. Remover hooks `add_action('dps_settings_nav_tabs', ...)` do Backup Add-on
  2. Criar `add_submenu_page('desi-pet-shower', 'Backup & Restauração', ...)`
  3. Implementar página admin própria com mesma UI
- **Arquivo**: `add-ons/desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php`
- **Justificativa**: Operação crítica de infraestrutura, não deve estar acessível em página pública

#### Ação 3: Mover Comunicações do front para admin
- **O que fazer**: Remover aba "Comunicações" do shortcode `[dps_configuracoes]`
- **Como**:
  1. Remover hooks `add_action('dps_settings_nav_tabs', ...)` do Communications Add-on
  2. Criar `add_submenu_page('desi-pet-shower', 'Comunicações', ...)`
  3. Mover toda UI de configuração para página admin
- **Arquivo**: `add-ons/desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php`
- **Justificativa**: Chaves de API e templates são configurações sensíveis e globais

#### Ação 4: Mover Notificações do front para admin
- **O que fazer**: Remover aba "Notificações" do shortcode `[dps_configuracoes]`
- **Como**:
  1. Remover hooks `add_action('dps_settings_nav_tabs', ...)` do Push Add-on
  2. Criar `add_submenu_page('desi-pet-shower', 'Notificações', ...)`
  3. Mover configurações de Telegram, destinatários e horários para admin
- **Arquivo**: `add-ons/desi-pet-shower-push_addon/desi-pet-shower-push-addon.php`
- **Justificativa**: Configurações globais de infraestrutura (bot tokens, chat IDs)

#### Ação 5: Mover Logins de Clientes do front para admin
- **O que fazer**: Remover aba "Logins de Clientes" do shortcode `[dps_configuracoes]`
- **Como**:
  1. Já existe `add_submenu_page('options-general.php', ...)` comentado no código (linha 1206)
  2. Descomentar e ativar submenu em "Configurações" ou mover para "DPS → Logins"
  3. Remover hooks `dps_settings_nav_tabs` e `dps_settings_sections`
- **Arquivo**: `add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`
- **Justificativa**: Gerenciamento de credenciais é tarefa administrativa

---

### 3.2 MÉDIA PRIORIDADE - Organização e Consistência

#### Ação 6: Avaliar se Serviços deve ficar no front ou admin
- **Situação atual**: Catálogo de serviços gerenciado via aba no `[dps_configuracoes]`
- **Análise necessária**:
  - Se criar serviços é raro (setup inicial) → mover para admin
  - Se ajustar preços é diário (promoções, sazonalidade) → pode ficar no front
- **Sugestão**: 
  1. Catálogo base (criar/excluir serviços): admin
  2. Ajuste rápido de preços: front (aba "Serviços" do `[dps_base]`)
- **Arquivos**: 
  - `add-ons/desi-pet-shower-services_addon/dps_service/desi-pet-shower-services-addon.php`
  - Considerar habilitar `show_ui => true` para CPT `dps_service`

#### Ação 7: Centralizar funcionalidades financeiras no Finance Add-on
- **Problema identificado**: Shortcode `[dps_charges_notes]` está no Agenda Add-on
- **Como corrigir**:
  1. Mover shortcode para Finance Add-on
  2. Integrar via hooks: Agenda dispara `do_action('dps_finance_generate_charge', $appointment_id)`
  3. Finance renderiza UI de cobranças
- **Arquivos**:
  - `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php` (remover)
  - `add-ons/desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php` (adicionar)
- **Justificativa**: Finance deve ser dono de TUDO relacionado a dinheiro (conforme ANALYSIS.md)

#### Ação 8: Remover configurações duplicadas
- **Problema**: Aba "Campanhas & Fidelidade" pode estar tanto no front quanto no admin
- **Como verificar**: 
  1. Testar se conteúdo da aba front é igual ao menu admin
  2. Se sim, remover do front e manter apenas admin
  3. Se não, documentar diferenças
- **Arquivo**: `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php`

---

### 3.3 BAIXA PRIORIDADE - Melhorias Futuras

#### Ação 9: Considerar habilitar UI admin nativa para CPTs
- **Contexto**: Conforme `docs/admin/ADMIN_CPT_INTERFACE_ANALYSIS.md`, há plano de coexistência
- **Proposta**:
  - Admin: para gerentes (bulk actions, análises avançadas, relatórios)
  - Front (`[dps_base]`): para recepcionistas (uso rápido no balcão)
- **Mudança**:
  1. Alterar `show_ui => true` em `dps_cliente`, `dps_pet`, `dps_agendamento`
  2. Definir `show_in_menu => 'desi-pet-shower'` para agrupar tudo
  3. Customizar colunas e metaboxes para UI admin
- **Arquivo**: `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php`
- **Benefício**: Flexibilidade de acesso (admin para alguns, front para outros)

#### Ação 10: Documentar contratos de configuração vs operação
- **O que fazer**: Criar `docs/CONFIGURATION_VS_OPERATION.md`
- **Conteúdo**:
  - Definir regras claras do que é CONFIG vs OPERAÇÃO
  - Listar todas as telas/abas do sistema com classificação
  - Estabelecer onde cada tipo deve estar (admin vs front)
  - Processo de decisão para novos recursos
- **Benefício**: Guia para desenvolvimento futuro evitando misturar config no front

---

## 4. Resumo Executivo

### Situação Atual

#### ✅ Correto
- **Admin**:
  - 5 menus/submenus de configuração (Logs, Loyalty, Pagamentos, Registration, Client Portal)
  - Todos com `current_user_can('manage_options')` ou capabilities adequadas
  
- **Front-end**:
  - 8 shortcodes bem definidos
  - Operação diária via `[dps_base]` (APP principal)
  - Portal do cliente via `[dps_client_portal]`
  - Cadastro público via `[dps_registration_form]`
  - Todos com nonces e sanitização adequados

#### ❌ Problemas Críticos
- **Configurações sensíveis expostas no front via `[dps_configuracoes]`**:
  - ⚠️ Chaves de API (WhatsApp, Telegram)
  - ⚠️ Backup/Restauração completa do sistema
  - ⚠️ Templates de mensagens globais
  - ⚠️ Credenciais de clientes
  - ⚠️ Configurações de notificações e horários

### Impacto de Segurança

| Recurso | Local Atual | Risco | Prioridade Correção |
|---------|-------------|-------|---------------------|
| Backup & Restauração | Front (`[dps_configuracoes]`) | 🔴 CRÍTICO | Alta |
| Chaves API WhatsApp | Front (`[dps_configuracoes]`) | 🔴 ALTO | Alta |
| Chaves API Telegram | Front (`[dps_configuracoes]`) | 🔴 ALTO | Alta |
| Templates Mensagens | Front (`[dps_configuracoes]`) | 🟡 MÉDIO | Alta |
| Logins de Clientes | Front (`[dps_configuracoes]`) | 🟡 MÉDIO | Alta |
| Catálogo Serviços | Front (`[dps_configuracoes]`) | 🟢 BAIXO | Média |

**Nota sobre risco**: Mesmo com `current_user_can('manage_options')`, expor configurações sensíveis em páginas front-end (via shortcode) aumenta a superfície de ataque e dificulta auditoria. O padrão WordPress é: **configurações no admin, operação no front**.

### Métricas do Mapeamento

- **Total de menus admin**: 5
  - DPS Logs (Base): 1
  - desi.pet by PRObst (Loyalty): 1 + 2 submenus
  - DPS Pagamentos (Payment): 1 submenu em Configurações
  - DPS Cadastro (Registration): 1 submenu em Configurações

- **Total de shortcodes**: 8
  - Operação: 7 (`[dps_base]`, `[dps_client_portal]`, `[dps_client_login]`, `[dps_registration_form]`, `[dps_agenda_page]`, `[dps_charges_notes]`, `[dps_fin_docs]`)
  - Configuração: 1 (`[dps_configuracoes]`) ⚠️

- **Total de endpoints AJAX**: 4
  - `dps_get_available_times` (Base) ✅
  - `dps_update_status` (Agenda) ✅
  - `dps_get_services_details` (Agenda) ✅
  - Webhook Mercado Pago (Payment) ✅

- **Total de endpoints REST**: 1
  - `/dps/v1/pets` (Base, paginação) ✅

### Ações Prioritárias (Ordem de Execução)

1. ✅ **Criar menu unificado "DPS"** no plugin base
2. 🔴 **Mover Backup para admin** (CRÍTICO - operação destrutiva)
3. 🔴 **Mover Comunicações para admin** (ALTO - chaves de API)
4. 🔴 **Mover Notificações para admin** (ALTO - tokens sensíveis)
5. 🟡 **Mover Logins para admin** (MÉDIO - credenciais)
6. 🟢 **Avaliar Serviços** (BAIXO - pode ter uso diário legítimo)
7. 🟢 **Centralizar Finance** (mover `[dps_charges_notes]` de Agenda para Finance)
8. 📋 **Documentar políticas** de CONFIG vs OPERAÇÃO

---

## 5. Conclusão

O sistema DPS apresenta uma **arquitetura funcional sólida** com separação clara entre núcleo e add-ons via hooks. No entanto, há uma **violação significativa da regra de negócio** ao expor configurações sensíveis e globais no front-end via shortcode `[dps_configuracoes]`.

**Recomendação principal**: Executar Ações 1-5 (Alta Prioridade) para mover todas as configurações para o admin do WordPress, mantendo no front-end apenas funcionalidades de operação diária. Isso aumentará a segurança, facilitará auditoria e alinhará o sistema aos padrões WordPress.

**Estimativa de esforço**:
- Ações 1-5: ~16-24 horas (2-3 dias de desenvolvimento)
- Ações 6-8: ~8-12 horas (1-2 dias de desenvolvimento)
- Ação 9: ~16-24 horas (implementação de UI admin nativa para CPTs)
- Ação 10: ~4-6 horas (documentação)

**Total**: ~44-66 horas (~1-1.5 semanas de trabalho focado)
