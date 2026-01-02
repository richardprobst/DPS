# Mapeamento Completo de Menus Administrativos do DPS

**Data de Análise:** 2025-12-08  
**Autor:** Análise Automatizada DPS  
**Objetivo:** Mapear todos os menus e submenus do painel administrativo do WordPress para futura reorganização

---

## Sumário Executivo

Este documento apresenta um mapeamento completo da organização atual dos menus administrativos do sistema desi.pet by PRObst. O sistema possui **1 menu principal** e **21 submenus** distribuídos entre o plugin base e 17 add-ons ativos.

### Estatísticas Gerais
- **Menu Principal:** 1 (desi.pet by PRObst)
- **Submenus Diretos:** 21
- **Custom Post Types visíveis:** 5 (Clientes, Pets, Agendamentos, Base de Conhecimento IA, Mensagens do Portal)
- **Add-ons com configuração:** 14
- **Add-ons sem menu próprio:** 3 (Finance, Services, Stock)

---

## 1. PLUGIN BASE - desi.pet by PRObst

### Menu Principal
- **Page Title:** desi.pet by PRObst
- **Menu Title:** desi.pet by PRObst
- **Slug:** `desi-pet-shower`
- **Parent Slug:** (nenhum - menu de topo)
- **Capability:** `manage_options`
- **Ícone:** `dashicons-pets`
- **Posição:** 56
- **Arquivo:** `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php`
- **Função:** `DPS_Base_Plugin::register_admin_menu()` (linha 167)

**Observação:** Este é o menu principal que agrupa todos os add-ons do sistema. A página principal exibe uma mensagem de boas-vindas e lista as funcionalidades disponíveis.

---

### Submenu: Logs do Sistema
- **Page Title:** Logs do Sistema
- **Menu Title:** Logs do Sistema
- **Slug:** `dps-logs`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `plugin/desi-pet-shower-base_plugin/includes/class-dps-logs-admin-page.php`
- **Função:** `DPS_Logs_Admin_Page::register_page()` (linha 20)
- **Prioridade de Hook:** 20

**Funcionalidade:** Visualização e filtragem de logs do sistema com níveis (info, warning, error), fonte e paginação. Permite limpar logs antigos.

---

### Custom Post Types do Base

#### CPT: Clientes (dps_cliente)
- **Visível no Admin:** Não (configurado com `show_in_menu => false`)
- **Capability Type:** `dps_client` (mapeado para `dps_manage_clients`)
- **Registro:** `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php` linha 253
- **Observação:** Gerenciado através do shortcode [dps_base] no frontend

#### CPT: Pets (dps_pet)
- **Visível no Admin:** Não (configurado com `show_in_menu => false`)
- **Capability Type:** `dps_pet` (mapeado para `dps_manage_pets`)
- **Registro:** `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php` linha 294

#### CPT: Agendamentos (dps_agendamento)
- **Visível no Admin:** Não (configurado com `show_in_menu => false`)
- **Capability Type:** `dps_appointment` (mapeado para `dps_manage_appointments`)
- **Registro:** `plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php` linha 334

---

## 2. ADD-ON: AGENDA

### Submenu: Dashboard da Agenda
- **Page Title:** Dashboard da Agenda
- **Menu Title:** Dashboard
- **Slug:** `dps-agenda-dashboard`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`
- **Função:** `DPS_Agenda_Addon::register_dashboard_admin_page()` (linha 291)

**Funcionalidade:** Dashboard operacional com métricas de agendamentos, gráficos de performance e indicadores-chave.

---

### Submenu: Configurações da Agenda
- **Page Title:** Configurações da Agenda
- **Menu Title:** Configurações
- **Slug:** `dps-agenda-settings`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`
- **Função:** `DPS_Agenda_Addon::register_settings_admin_page()` (linha 345)

**Funcionalidade:** Configurações do sistema de agendamento (horários, capacidade, regras de confirmação).

**Shortcodes relacionados:**
- `[dps_agenda_page]` - Página de visualização da agenda
- `[dps_agenda_dashboard]` - Dashboard operacional

---

## 3. ADD-ON: ASSISTENTE DE IA

### Submenu: Assistente de IA (Configurações)
- **Page Title:** Assistente de IA
- **Menu Title:** Assistente de IA
- **Slug:** `dps-ai-settings`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php`
- **Função:** `DPS_AI_Addon::register_admin_menu()` (linha 402)

**Funcionalidade:** Configuração da API OpenAI, modelo GPT, prompts do sistema e canais de integração.

---

### Submenu: Analytics de IA
- **Page Title:** Analytics de IA
- **Menu Title:** Analytics de IA
- **Slug:** `dps-ai-analytics`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php`
- **Função:** `DPS_AI_Addon::register_admin_menu()` (linha 412)

**Funcionalidade:** Métricas de uso da IA, análise de conversas e performance do assistente.

---

### Submenu: Conversas IA
- **Page Title:** Histórico de Conversas IA
- **Menu Title:** Conversas IA
- **Slug:** `dps-ai-conversations`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-conversations-admin.php`
- **Função:** `DPS_AI_Conversations_Admin::register_admin_menu()` (linha 53)
- **Prioridade de Hook:** 25

**Funcionalidade:** Listagem e visualização detalhada de todas as conversas registradas com o assistente de IA.

---

### Submenu: Base de Conhecimento
- **Page Title:** Gerenciar Base de Conhecimento
- **Menu Title:** Base de Conhecimento
- **Slug:** `dps-ai-knowledge-base`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `edit_posts`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-knowledge-base-admin.php`
- **Função:** `DPS_AI_Knowledge_Base_Admin::register_admin_page()` (linha 57)
- **Prioridade de Hook:** 25

**Funcionalidade:** Interface de gerenciamento da base de conhecimento que alimenta o contexto da IA.

---

### Submenu: Testar Base de Conhecimento
- **Page Title:** Teste da Base de Conhecimento
- **Menu Title:** Testar Base de Conhecimento
- **Slug:** `dps-ai-kb-tester`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `edit_posts`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-knowledge-base-tester.php`
- **Função:** `DPS_AI_Knowledge_Base_Tester::register_admin_page()` (linha 59)
- **Prioridade de Hook:** 25

**Funcionalidade:** Ferramenta de teste para validar o matching de perguntas com artigos da base de conhecimento.

---

### Submenu: IA – Modo Especialista
- **Page Title:** IA – Modo Especialista
- **Menu Title:** IA – Modo Especialista
- **Slug:** `dps-ai-specialist`
- **Parent Slug:** `dps-gestao` ⚠️
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-specialist-mode.php`
- **Função:** `DPS_AI_Specialist_Mode::register_menu()` (linha 55)
- **Prioridade de Hook:** 21

**⚠️ OBSERVAÇÃO IMPORTANTE:** Este menu usa `dps-gestao` como parent, mas esse menu não foi encontrado no código. Provavelmente é um menu que deveria existir mas não está registrado, ou foi removido. **Este é um menu órfão que não aparece no admin.**

---

### Submenu: IA – Insights
- **Page Title:** IA – Insights
- **Menu Title:** IA – Insights
- **Slug:** `dps-ai-insights`
- **Parent Slug:** `dps-gestao` ⚠️
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-insights-dashboard.php`
- **Função:** `DPS_AI_Insights_Dashboard::register_menu()` (linha 56)
- **Prioridade de Hook:** 20

**⚠️ OBSERVAÇÃO IMPORTANTE:** Mesmo problema do menu anterior - usa parent `dps-gestao` que não existe. **Menu órfão.**

---

### CPT: Base de Conhecimento IA (dps_ai_knowledge)
- **Visível no Admin:** Sim
- **Parent Menu:** `desi-pet-shower`
- **Label:** Base de Conhecimento IA
- **Capability Type:** `post`
- **Registro:** `add-ons/desi-pet-shower-ai_addon/includes/class-dps-ai-knowledge-base.php` linha 67
- **Taxonomia:** `dps_ai_knowledge_cat` (Categorias de Conhecimento)

**Shortcodes relacionados:**
- `[dps_ai_public_chat]` - Chat público com a IA

---

## 4. ADD-ON: CLIENTE PORTAL

### Submenu: Portal do Cliente
- **Page Title:** Portal do Cliente - Configurações
- **Menu Title:** Portal do Cliente
- **Slug:** `dps-client-portal-settings`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-client-portal_addon/includes/client-portal/class-dps-portal-admin.php`
- **Função:** `DPS_Portal_Admin::register_admin_menu()` (linha 111)

**⚠️ DUPLICAÇÃO:** Este mesmo menu é registrado em DOIS arquivos diferentes:
1. `includes/client-portal/class-dps-portal-admin.php` linha 111
2. `includes/class-dps-client-portal.php` linha 2352

Ambos usam o mesmo slug e parent, o que causa sobrescrita. Apenas um deles será exibido.

**Funcionalidade:** Configurações do portal do cliente (cores, logo, termos de uso, etc.).

---

### Submenu: Logins de Clientes
- **Page Title:** Portal do Cliente - Logins
- **Menu Title:** Logins de Clientes
- **Slug:** `dps-client-logins`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-client-portal_addon/includes/client-portal/class-dps-portal-admin.php`
- **Função:** `DPS_Portal_Admin::register_admin_menu()` (linha 121)

**⚠️ DUPLICAÇÃO:** Mesmo problema - registrado em dois arquivos:
1. `includes/client-portal/class-dps-portal-admin.php` linha 121
2. `includes/class-dps-client-portal.php` linha 2362

**Funcionalidade:** Gerenciamento de credenciais de acesso ao portal dos clientes.

---

### CPT: Mensagens do Portal (dps_portal_message)
- **Visível no Admin:** Sim (via WordPress padrão)
- **Parent Menu:** Nenhum (menu independente)
- **Label:** Mensagens do Portal
- **Capability Type:** `post`
- **Registro:** `add-ons/desi-pet-shower-client-portal_addon/includes/client-portal/class-dps-portal-admin.php` linha 104

**Observação:** Este CPT não está configurado para aparecer sob `desi-pet-shower`, então cria seu próprio item de menu no admin.

**Shortcodes relacionados:**
- `[dps_client_portal]` - Portal completo do cliente

---

## 5. ADD-ON: COMUNICAÇÕES

### Submenu: Comunicações
- **Page Title:** Comunicações
- **Menu Title:** Comunicações
- **Slug:** `dps-communications`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php`
- **Função:** `DPS_Communications_Addon::register_admin_menu()` (linha 95)

**Funcionalidade:** Configurações de WhatsApp, e-mail, templates de mensagens e automações de comunicação.

---

## 6. ADD-ON: PAGAMENTOS

### Submenu: Pagamentos
- **Page Title:** Pagamentos
- **Menu Title:** Pagamentos
- **Slug:** `dps-payment-settings`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php`
- **Função:** `DPS_Payment_Addon::add_settings_page()` (linha 129)

**Funcionalidade:** Configuração de integração com Mercado Pago (Access Token, Chave PIX, Webhook Secret).

---

## 7. ADD-ON: WHITE LABEL

### Submenu: White Label
- **Page Title:** White Label
- **Menu Title:** White Label
- **Slug:** `dps-whitelabel`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-whitelabel_addon/desi-pet-shower-whitelabel-addon.php`
- **Função:** `DPS_WhiteLabel_Addon::register_admin_menu()` (linha 180)

**Funcionalidade:** Personalização de marca (logo, cores, nome do sistema) e controle de acesso ao site.

**Observação:** Usa sistema de abas internas (branding, access_control, advanced).

---

## 8. ADD-ON: CAMPANHAS & FIDELIDADE

### Submenu: Campanhas & Fidelidade
- **Page Title:** Campanhas & Fidelidade
- **Menu Title:** Campanhas & Fidelidade
- **Slug:** `dps-loyalty`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php`
- **Função:** `DPS_Loyalty_Addon::register_menu()` (linha 282)

**Funcionalidade:** Dashboard de pontos de fidelidade, configurações do programa de pontos e gestão de campanhas.

**Observação:** Usa sistema de abas internas (dashboard, settings, campaigns).

---

### Submenu: Campanhas (Link para CPT)
- **Page Title:** Campanhas
- **Menu Title:** Campanhas
- **Slug:** `edit.php?post_type=dps_campaign`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php`
- **Função:** `DPS_Loyalty_Addon::register_menu()` (linha 291)

**Observação:** Este é um link direto para a listagem do CPT dps_campaign. Cria duplicação porque o CPT já aparece no menu (show_in_menu = false mas acessível via este submenu).

---

### CPT: Campanhas (dps_campaign)
- **Visível no Admin:** Não diretamente (show_in_menu = false)
- **Acesso:** Via submenu acima
- **Capability Type:** `post`
- **Registro:** `add-ons/desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php` linha 145

---

## 9. ADD-ON: FORMULÁRIO DE CADASTRO

### Submenu: Formulário de Cadastro
- **Page Title:** Formulário de Cadastro
- **Menu Title:** Formulário de Cadastro
- **Slug:** `dps-registration-settings`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php`
- **Função:** `DPS_Registration_Addon::add_settings_page()` (linha 123)

**Funcionalidade:** Configuração da API do Google Maps para geolocalização automática no formulário de cadastro.

**Shortcodes relacionados:**
- `[dps_registration_form]` - Formulário público de cadastro

---

## 10. ADD-ON: NOTIFICAÇÕES PUSH

### Submenu: Push Notifications
- **Page Title:** Notificações Push
- **Menu Title:** Push Notifications
- **Slug:** `dps-push-notifications`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-push_addon/desi-pet-shower-push-addon.php`
- **Função:** `DPS_Push_Addon::register_admin_menu()` (linha 118)

**Funcionalidade:** Configuração de notificações push via Web Push (VAPID keys, relatórios Telegram).

---

## 11. ADD-ON: BACKUP & RESTAURAÇÃO

### Submenu: Backup & Restauração
- **Page Title:** Backup & Restauração
- **Menu Title:** Backup & Restauração
- **Slug:** `dps-backup`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php`
- **Função:** `DPS_Backup_Addon::register_admin_menu()` (linha 152)

**Funcionalidade:** Backup manual e automático, restauração de dados, histórico de backups.

**Observação:** Usa sistema de abas internas (manual, automatic, history).

---

## 12. ADD-ON: DEBUGGING

### Submenu: Debugging
- **Page Title:** Debugging
- **Menu Title:** Debugging
- **Slug:** `dps-debugging`
- **Parent Slug:** `desi-pet-shower`
- **Capability:** `manage_options`
- **Arquivo:** `add-ons/desi-pet-shower-debugging_addon/desi-pet-shower-debugging-addon.php`
- **Função:** `DPS_Debugging_Addon::register_admin_menu()` (linha 294)

**Funcionalidade:** Controle de constantes de debugging do WordPress (WP_DEBUG, WP_DEBUG_LOG, etc.).

---

## 13. ADD-ONS SEM MENU ADMINISTRATIVO

Os seguintes add-ons **NÃO** possuem menu próprio no admin:

### Finance Add-on
- **Diretório:** `add-ons/desi-pet-shower-finance_addon/`
- **Funcionalidade:** Gestão financeira integrada ao sistema de agendamentos
- **Interface:** Apenas shortcodes e integrações via hooks
- **Shortcodes:** `[dps_fin_docs]` - Documentos financeiros

### Services Add-on
- **Diretório:** `add-ons/desi-pet-shower-services_addon/`
- **Funcionalidade:** Gestão de serviços oferecidos
- **Interface:** Gerenciado via frontend

### Stock Add-on
- **Diretório:** `add-ons/desi-pet-shower-stock_addon/`
- **Funcionalidade:** Controle de estoque
- **Interface:** Integração via hooks com outros módulos

### Groomers Add-on
- **Diretório:** `add-ons/desi-pet-shower-groomers_addon/`
- **Funcionalidade:** Gestão de profissionais (groomers)
- **Interface:** Frontend via shortcode
- **CPT:** `dps_groomer_review` (show_in_menu = false)

### Stats Add-on
- **Diretório:** `add-ons/desi-pet-shower-stats_addon/`
- **Funcionalidade:** Estatísticas e relatórios
- **Interface:** Provavelmente widgets ou relatórios integrados

### Subscription Add-on
- **Diretório:** `add-ons/desi-pet-shower-subscription_addon/`
- **Funcionalidade:** Sistema de assinaturas/planos recorrentes
- **Interface:** Gerenciamento via frontend
- **CPT:** `dps_subscription` (show_ui = false)

---

## ANÁLISE: ORGANIZAÇÃO ATUAL

### Como os menus aparecem hoje para o usuário

#### Estrutura Hierárquica Atual:
```
desi.pet by PRObst (Menu Principal)
├── desi.pet by PRObst (Página inicial)
├── Logs do Sistema
├── Dashboard (Agenda)
├── Configurações (Agenda)
├── Assistente de IA
├── Analytics de IA
├── Conversas IA
├── Base de Conhecimento
├── Testar Base de Conhecimento
├── Portal do Cliente
├── Logins de Clientes
├── Comunicações
├── Pagamentos
├── White Label
├── Campanhas & Fidelidade
├── Campanhas (link para CPT)
├── Formulário de Cadastro
├── Push Notifications
├── Backup & Restauração
└── Debugging

Conhecimento IA (CPT - sob desi.pet by PRObst)
├── Todos os Artigos
├── Adicionar Novo
└── Categorias de Conhecimento

Mensagens do Portal (CPT - Menu Independente) ⚠️
├── Todas as Mensagens
└── Adicionar Nova
```

---

### Problemas Identificados

#### 1. Menus Órfãos (Parent Inexistente)
**Severidade:** Alta  
**Afetados:**
- IA – Modo Especialista (parent: dps-gestao)
- IA – Insights (parent: dps-gestao)

**Impacto:** Estes menus não aparecem no painel admin porque o menu pai `dps-gestao` não existe.

**Recomendação:** Alterar parent para `desi-pet-shower` ou criar o menu `dps-gestao` se houver plano para isso.

---

#### 2. Duplicação de Registros
**Severidade:** Média  
**Afetados:**
- Portal do Cliente - Configurações (registrado 2x)
- Logins de Clientes (registrado 2x)

**Impacto:** Desperdício de processamento, risco de comportamento inconsistente se os dois registros diferirem.

**Localização:**
- `includes/client-portal/class-dps-portal-admin.php`
- `includes/class-dps-client-portal.php`

**Recomendação:** Remover uma das instâncias, preferencialmente manter em `class-dps-portal-admin.php`.

---

#### 3. CPT com Menu Independente
**Severidade:** Baixa  
**Afetado:** Mensagens do Portal (dps_portal_message)

**Impacto:** Cria um menu separado fora da hierarquia DPS, quebrando a consistência visual.

**Recomendação:** Configurar `show_in_menu => 'desi-pet-shower'` para integrar ao menu principal.

---

#### 4. Redundância: Campanhas & Fidelidade
**Severidade:** Baixa  
**Descrição:** O add-on de Loyalty cria dois itens de menu:
1. "Campanhas & Fidelidade" (página com abas)
2. "Campanhas" (link direto para edit.php?post_type=dps_campaign)

**Impacto:** Confusão para o usuário - "Campanhas" aparece duas vezes (na aba do primeiro item E como item separado).

**Recomendação:** Remover o segundo item de menu e manter apenas a aba dentro de "Campanhas & Fidelidade".

---

#### 5. Inconsistência de Nomenclatura
**Severidade:** Baixa  
**Exemplos:**
- "Assistente de IA" vs "Analytics de IA" vs "Conversas IA"
- "Push Notifications" (em inglês) vs resto em português
- "Logins de Clientes" vs "Portal do Cliente"

**Recomendação:** Padronizar nomenclatura em português e agrupar menus relacionados.

---

#### 6. Falta de Agrupamento Lógico
**Severidade:** Média  
**Descrição:** Menus do mesmo add-on estão espalhados:
- **IA:** 5 submenus separados (Assistente, Analytics, Conversas, Base de Conhecimento, Testar)
- **Agenda:** 2 submenus separados (Dashboard, Configurações)
- **Portal:** 2 submenus separados (Configurações, Logins)

**Impacto:** Menu principal com 21 itens, difícil de navegar.

**Recomendação:** Criar páginas com abas internas para agrupar funcionalidades relacionadas, reduzindo o número de itens de menu.

---

## SUGESTÃO DE REORGANIZAÇÃO

### Estrutura Proposta (Exemplo)
```
desi.pet by PRObst
├── Painel Inicial
├── Agenda (com abas: Dashboard, Configurações, Capacidade)
├── Assistente de IA (com abas: Configurações, Analytics, Conversas, Base de Conhecimento, Modo Especialista, Insights)
├── Portal do Cliente (com abas: Configurações, Logins, Mensagens)
├── Integrações (com abas: Comunicações, Pagamentos, WhatsApp, Telegram)
├── Fidelidade & Campanhas (já usa abas)
├── Sistema (com abas: Backup, Debugging, Logs, White Label)
└── Ferramentas (com abas: Formulário de Cadastro, Push Notifications)
```

**Benefícios:**
- Redução de 21 para ~8 itens de menu
- Agrupamento lógico por funcionalidade
- Navegação mais intuitiva
- Facilita descoberta de recursos relacionados

---

## APÊNDICE: TABELA COMPLETA DE MENUS

| Add-on/Módulo | Nível | Page Title | Menu Title | Slug | Parent Slug | Capability | Arquivo | Função/Linha |
|---------------|-------|------------|------------|------|-------------|------------|---------|--------------|
| **Base** | Menu Principal | desi.pet by PRObst | desi.pet by PRObst | desi-pet-shower | - | manage_options | plugin/.../desi-pet-shower-base.php | DPS_Base_Plugin::register_admin_menu():167 |
| Base | Submenu | Logs do Sistema | Logs do Sistema | dps-logs | desi-pet-shower | manage_options | plugin/.../class-dps-logs-admin-page.php | DPS_Logs_Admin_Page::register_page():20 |
| **Agenda** | Submenu | Dashboard da Agenda | Dashboard | dps-agenda-dashboard | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-agenda-addon.php | DPS_Agenda_Addon::register_dashboard_admin_page():291 |
| Agenda | Submenu | Configurações da Agenda | Configurações | dps-agenda-settings | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-agenda-addon.php | DPS_Agenda_Addon::register_settings_admin_page():345 |
| **IA** | Submenu | Assistente de IA | Assistente de IA | dps-ai-settings | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-ai-addon.php | DPS_AI_Addon::register_admin_menu():402 |
| IA | Submenu | Analytics de IA | Analytics de IA | dps-ai-analytics | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-ai-addon.php | DPS_AI_Addon::register_admin_menu():412 |
| IA | Submenu | Histórico de Conversas IA | Conversas IA | dps-ai-conversations | desi-pet-shower | manage_options | add-ons/.../class-dps-ai-conversations-admin.php | DPS_AI_Conversations_Admin::register_admin_menu():53 |
| IA | Submenu | Gerenciar Base de Conhecimento | Base de Conhecimento | dps-ai-knowledge-base | desi-pet-shower | edit_posts | add-ons/.../class-dps-ai-knowledge-base-admin.php | DPS_AI_Knowledge_Base_Admin::register_admin_page():57 |
| IA | Submenu | Teste da Base de Conhecimento | Testar Base de Conhecimento | dps-ai-kb-tester | desi-pet-shower | edit_posts | add-ons/.../class-dps-ai-knowledge-base-tester.php | DPS_AI_Knowledge_Base_Tester::register_admin_page():59 |
| IA | Submenu ⚠️ | IA – Modo Especialista | IA – Modo Especialista | dps-ai-specialist | **dps-gestao** | manage_options | add-ons/.../class-dps-ai-specialist-mode.php | DPS_AI_Specialist_Mode::register_menu():55 |
| IA | Submenu ⚠️ | IA – Insights | IA – Insights | dps-ai-insights | **dps-gestao** | manage_options | add-ons/.../class-dps-ai-insights-dashboard.php | DPS_AI_Insights_Dashboard::register_menu():56 |
| IA | CPT | Base de Conhecimento IA | - | dps_ai_knowledge | desi-pet-shower | post | add-ons/.../class-dps-ai-knowledge-base.php | DPS_AI_Knowledge_Base::register_post_type():67 |
| **Portal** | Submenu | Portal do Cliente - Configurações | Portal do Cliente | dps-client-portal-settings | desi-pet-shower | manage_options | add-ons/.../class-dps-portal-admin.php | DPS_Portal_Admin::register_admin_menu():111 |
| Portal | Submenu | Portal do Cliente - Logins | Logins de Clientes | dps-client-logins | desi-pet-shower | manage_options | add-ons/.../class-dps-portal-admin.php | DPS_Portal_Admin::register_admin_menu():121 |
| Portal | CPT | Mensagens do Portal | - | dps_portal_message | **nenhum** ⚠️ | post | add-ons/.../class-dps-portal-admin.php | DPS_Portal_Admin::register_post_type():104 |
| **Comunicações** | Submenu | Comunicações | Comunicações | dps-communications | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-communications-addon.php | DPS_Communications_Addon::register_admin_menu():95 |
| **Pagamentos** | Submenu | Pagamentos | Pagamentos | dps-payment-settings | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-payment-addon.php | DPS_Payment_Addon::add_settings_page():129 |
| **White Label** | Submenu | White Label | White Label | dps-whitelabel | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-whitelabel-addon.php | DPS_WhiteLabel_Addon::register_admin_menu():180 |
| **Fidelidade** | Submenu | Campanhas & Fidelidade | Campanhas & Fidelidade | dps-loyalty | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-loyalty.php | DPS_Loyalty_Addon::register_menu():282 |
| Fidelidade | Submenu | Campanhas | Campanhas | edit.php?post_type=dps_campaign | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-loyalty.php | DPS_Loyalty_Addon::register_menu():291 |
| Fidelidade | CPT | Campanhas | - | dps_campaign | nenhum (show_in_menu=false) | post | add-ons/.../desi-pet-shower-loyalty.php | DPS_Loyalty_Addon::register_post_type():145 |
| **Registration** | Submenu | Formulário de Cadastro | Formulário de Cadastro | dps-registration-settings | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-registration-addon.php | DPS_Registration_Addon::add_settings_page():123 |
| **Push** | Submenu | Notificações Push | Push Notifications | dps-push-notifications | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-push-addon.php | DPS_Push_Addon::register_admin_menu():118 |
| **Backup** | Submenu | Backup & Restauração | Backup & Restauração | dps-backup | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-backup-addon.php | DPS_Backup_Addon::register_admin_menu():152 |
| **Debugging** | Submenu | Debugging | Debugging | dps-debugging | desi-pet-shower | manage_options | add-ons/.../desi-pet-shower-debugging-addon.php | DPS_Debugging_Addon::register_admin_menu():294 |

**Legenda:**
- ⚠️ = Problema identificado (menu órfão, duplicação, etc.)
- **Negrito** = Nome do add-on/módulo

---

## CONCLUSÃO

O sistema DPS possui uma estrutura de menus funcional, mas com oportunidades significativas de melhoria:

1. **Resolver Urgente:** Corrigir menus órfãos (Modo Especialista e Insights da IA)
2. **Eliminar Duplicações:** Remover registros duplicados do Cliente Portal
3. **Agrupar Funcionalidades:** Usar abas para reduzir o número de itens de menu
4. **Padronizar Nomenclatura:** Manter consistência de idioma e terminologia
5. **Integrar CPTs:** Configurar Mensagens do Portal para aparecer sob o menu principal

A reorganização proposta pode reduzir o menu de 21 para ~8 itens principais, melhorando significativamente a experiência do usuário sem perder funcionalidades.

---

**Documento gerado em:** 2025-12-08  
**Próximos passos sugeridos:** Criar protótipo da nova estrutura de menus e validar com usuários antes de implementar mudanças.
