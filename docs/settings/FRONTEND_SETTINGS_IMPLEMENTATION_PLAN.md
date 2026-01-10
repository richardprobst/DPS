# Plano de Implementação: Página de Configurações Front-End

**Data:** 2026-01-10  
**Autor:** PRObst  
**Status:** Planejamento  
**Versão:** 1.0.0

---

## 1. Sumário Executivo

Este documento detalha o plano de reimplementação da página de configurações via front-end (`[dps_configuracoes]`), que atualmente está deprecated e redireciona para o painel admin. O objetivo é criar uma página de configurações **completa, organizada e segura** que permita ao administrador gerenciar todas as opções do sistema DPS diretamente pelo front-end, mantendo os mesmos padrões de segurança do painel admin.

### 1.1 Situação Atual

- **Shortcode:** `[dps_configuracoes]` existe mas está marcado como deprecated
- **Comportamento atual:** Exibe mensagem de redirecionamento para o painel admin
- **Motivo da depreciação:** Preocupações de segurança sobre exposição de configurações sensíveis
- **Hooks disponíveis:** `dps_settings_nav_tabs` e `dps_settings_sections` (já utilizados por add-ons)

### 1.2 Objetivos

1. Reativar o shortcode `[dps_configuracoes]` com funcionalidade completa
2. Organizar configurações em categorias lógicas com navegação por abas
3. Manter segurança rigorosa (nonce, capability checks, sanitização)
4. Permitir extensibilidade via hooks para add-ons
5. Seguir padrão visual minimalista do DPS

---

## 2. Análise Completa de Configurações do Sistema

### 2.1 Configurações do Plugin Base

| Option | Tipo | Descrição | Onde é usado |
|--------|------|-----------|--------------|
| `dps_base_password` | string | Senha de acesso ao painel base | `DPS_Base_Frontend` |
| `dps_agenda_password` | string | Senha de acesso à agenda | `DPS_Base_Frontend` |
| `dps_google_api_key` | string | Chave API do Google Maps | Múltiplos locais |
| `dps_clients_registration_url` | URL | URL da página de cadastro de clientes | Seção de clientes |
| `dps_whatsapp_number` | string | Número WhatsApp da equipe | `DPS_WhatsApp_Helper` |
| `dps_shop_name` | string | Nome do petshop | Comunicações, Portal |
| `dps_shop_address` | string | Endereço do petshop | Agenda, GPS |
| `dps_business_address` | string | Endereço comercial | Calendário, GPS |
| `dps_logger_min_level` | string | Nível mínimo de log | `DPS_Logger` |

### 2.2 Configurações por Add-on

#### 2.2.1 Agenda Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_agenda_page_id` | int | ID da página da agenda |
| `dps_charges_page_id` | int | ID da página de cobranças |
| `dps_agenda_capacity_config` | array | Configuração de capacidade por horário |

#### 2.2.2 AI Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_ai_settings` | array | Configurações gerais (enabled, api_key, model, temperature, timeout, max_tokens, additional_instructions) |

#### 2.2.3 Client Portal Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_portal_page_id` | int | ID da página do portal |
| `dps_portal_logo_id` | int | ID da logo do portal |
| `dps_portal_primary_color` | string | Cor primária do portal |
| `dps_portal_hero_id` | int | ID da imagem hero |
| `dps_portal_review_url` | URL | URL para avaliação |
| `dps_portal_access_notification_enabled` | bool | Notificações de acesso |

#### 2.2.4 Communications Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_whatsapp_number` | string | Número WhatsApp (compartilhado) |
| `dps_comm_settings` | array | Configurações de gateways e templates |

#### 2.2.5 Finance Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_finance_reminders_enabled` | bool | Lembretes automáticos |
| `dps_finance_reminder_days_before` | int | Dias antes do vencimento |
| `dps_finance_reminder_days_after` | int | Dias após vencimento |
| `dps_finance_reminder_message_before` | string | Template mensagem antecipada |
| `dps_finance_reminder_message_after` | string | Template mensagem atrasada |

#### 2.2.6 Groomers Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_groomers_staff_migration_done` | bool | Flag de migração |

#### 2.2.7 Loyalty Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_loyalty_settings` | array | Configurações de pontos, recompensas, elegibilidade |

#### 2.2.8 Payment Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_mercadopago_access_token` | string | Token de acesso Mercado Pago |
| `dps_mercadopago_public_key` | string | Chave pública Mercado Pago |
| `dps_mercadopago_webhook_secret` | string | Secret para webhooks |
| `dps_pix_key` | string | Chave PIX |

#### 2.2.9 Push Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_push_emails_agenda` | array | Emails para agenda diária |
| `dps_push_emails_report` | array | Emails para relatórios |
| `dps_push_agenda_time` | string | Horário do resumo (HH:MM) |
| `dps_push_report_time` | string | Horário do relatório (HH:MM) |
| `dps_push_weekly_day` | string | Dia da semana para semanal |
| `dps_push_weekly_time` | string | Horário do semanal |
| `dps_push_inactive_days` | int | Dias de inatividade |
| `dps_push_telegram_token` | string | Token do bot Telegram |
| `dps_push_telegram_chat` | string | Chat ID do Telegram |
| `dps_push_agenda_enabled` | bool | Agenda diária ativada |
| `dps_push_report_enabled` | bool | Relatório ativado |
| `dps_push_weekly_enabled` | bool | Semanal ativado |

#### 2.2.10 Registration Add-on
| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_registration_page_id` | int | ID da página de cadastro |
| `dps_registration_api_enabled` | bool | API REST ativada |
| `dps_registration_api_key_hash` | string | Hash da API key |
| `dps_registration_api_rate_key_per_hour` | int | Rate limit por key |
| `dps_registration_api_rate_ip_per_hour` | int | Rate limit por IP |
| `dps_registration_recaptcha_enabled` | bool | reCAPTCHA ativado |
| `dps_registration_recaptcha_site_key` | string | Site key reCAPTCHA |
| `dps_registration_recaptcha_secret_key` | string | Secret key reCAPTCHA |
| `dps_registration_recaptcha_threshold` | float | Threshold reCAPTCHA |
| `dps_registration_confirm_email_subject` | string | Assunto do email |
| `dps_registration_confirm_email_body` | string | Corpo do email |

---

## 3. Arquitetura Proposta

### 3.1 Estrutura de Abas e Categorias

```
[dps_configuracoes]
│
├── 🏢 Empresa (Aba Base)
│   ├── Nome do Petshop
│   ├── Endereço Comercial
│   ├── Número WhatsApp
│   ├── Chave API Google Maps
│   └── Nível de Log do Sistema
│
├── 🔐 Segurança (Aba Base)
│   ├── Senha do Painel Base
│   ├── Senha da Agenda
│   └── Configurações de API
│
├── 📱 Portal do Cliente (Add-on)
│   ├── Página do Portal
│   ├── Logo e Hero
│   ├── Cor Primária
│   ├── URL de Avaliação
│   └── Notificações de Acesso
│
├── 💬 Comunicações (Add-on)
│   ├── Número WhatsApp
│   ├── Templates de Mensagens
│   └── Configurações de Gateway
│
├── 💳 Pagamentos (Add-on)
│   ├── Token Mercado Pago
│   ├── Chave Pública
│   ├── Webhook Secret
│   └── Chave PIX
│
├── 🔔 Notificações (Add-on)
│   ├── Emails para Agenda
│   ├── Emails para Relatórios
│   ├── Horários de Envio
│   ├── Telegram Bot
│   └── Ativar/Desativar
│
├── 📝 Cadastro Público (Add-on)
│   ├── Página de Cadastro
│   ├── reCAPTCHA
│   ├── API REST
│   ├── Rate Limiting
│   └── Email de Confirmação
│
├── 💰 Financeiro (Add-on)
│   ├── Lembretes Automáticos
│   ├── Dias Antes/Depois
│   └── Templates de Mensagem
│
├── 🎁 Fidelidade (Add-on)
│   └── Configurações de Pontos
│
├── 🤖 Assistente IA (Add-on)
│   ├── Ativar/Desativar
│   ├── Chave API OpenAI
│   ├── Modelo GPT
│   ├── Temperatura
│   ├── Timeout
│   ├── Max Tokens
│   └── Instruções Adicionais
│
└── ⏰ Agenda (Add-on)
    ├── Página da Agenda
    ├── Capacidade por Horário
    └── Endereço do Petshop
```

### 3.2 Classificação de Segurança

| Categoria | Nível | Descrição |
|-----------|-------|-----------|
| 🟢 Público | Baixo | Informações básicas do negócio |
| 🟡 Operacional | Médio | Configurações de operação diária |
| 🔴 Sensível | Alto | Chaves de API, tokens, senhas |
| ⚫ Crítico | Máximo | Backup/Restauração (manter apenas no admin) |

### 3.3 Regras de Acesso

1. **Capability obrigatória:** `manage_options` para TODAS as abas
2. **Nonce obrigatório:** Verificação em todos os formulários
3. **Sanitização rigorosa:** Todos os inputs sanitizados
4. **Escaping completo:** Todos os outputs escapados
5. **Logs de auditoria:** Registrar alterações sensíveis

---

## 4. Fases de Implementação

### FASE 1: Estrutura Base (Estimativa: 4h)
**Prioridade:** 🔴 Alta  
**Dependências:** Nenhuma

#### 4.1.1 Objetivos
- Reativar o shortcode `[dps_configuracoes]`
- Implementar estrutura de abas base
- Criar sistema de navegação consistente
- Implementar validação de segurança

#### 4.1.2 Tarefas
- [ ] Modificar `DPS_Base_Frontend::render_settings()` para renderizar conteúdo real
- [ ] Criar classe `DPS_Settings_Frontend` para gerenciar configurações
- [ ] Implementar sistema de abas com navegação via query param
- [ ] Adicionar verificação de capability `manage_options`
- [ ] Implementar nonce global para a página de configurações
- [ ] Criar estilos CSS consistentes com o padrão DPS

#### 4.1.3 Critérios de Aceite
- [ ] Shortcode renderiza página com abas navegáveis
- [ ] Apenas administradores conseguem acessar
- [ ] Navegação mantém estado da aba ativa
- [ ] Estilos seguem padrão visual minimalista

---

### FASE 2: Aba Empresa e Segurança (Estimativa: 3h)
**Prioridade:** 🔴 Alta  
**Dependências:** Fase 1

#### 4.2.1 Objetivos
- Implementar aba "Empresa" com configurações do negócio
- Implementar aba "Segurança" com senhas de acesso
- Criar formulário de salvamento seguro

#### 4.2.2 Tarefas
- [ ] Criar fieldset "Dados da Empresa" (nome, endereço, WhatsApp, API Google)
- [ ] Criar fieldset "Senhas de Acesso" (painel base, agenda)
- [ ] Implementar handler de salvamento com nonce + sanitização
- [ ] Adicionar feedback visual (mensagens de sucesso/erro)
- [ ] Implementar validação de campos obrigatórios

#### 4.2.3 Campos da Aba Empresa
| Campo | Option | Tipo | Validação |
|-------|--------|------|-----------|
| Nome do Petshop | `dps_shop_name` | text | `sanitize_text_field` |
| Endereço | `dps_shop_address` | textarea | `sanitize_textarea_field` |
| WhatsApp | `dps_whatsapp_number` | text | Regex telefone |
| API Google | `dps_google_api_key` | text | `sanitize_text_field` |
| Nível de Log | `dps_logger_min_level` | select | Valores permitidos |

#### 4.2.4 Campos da Aba Segurança
| Campo | Option | Tipo | Validação |
|-------|--------|------|-----------|
| Senha Painel | `dps_base_password` | password | Min 6 chars |
| Senha Agenda | `dps_agenda_password` | password | Min 6 chars |

#### 4.2.5 Critérios de Aceite
- [ ] Formulários salvam corretamente
- [ ] Validações impedem dados inválidos
- [ ] Senhas são mascaradas na exibição
- [ ] Mensagens de feedback funcionam

---

### FASE 3: Abas de Add-ons Core (Estimativa: 6h)
**Prioridade:** 🟠 Média-Alta  
**Dependências:** Fase 2

#### 4.3.1 Objetivos
- Implementar abas para Portal, Comunicações e Pagamentos
- Criar handlers específicos por add-on
- Manter consistência visual

#### 4.3.2 Tarefas

**Aba Portal do Cliente:**
- [ ] Selector de página do portal
- [ ] Upload de logo e hero
- [ ] Color picker para cor primária
- [ ] Campo URL de avaliação
- [ ] Checkbox de notificações

**Aba Comunicações:**
- [ ] Campo número WhatsApp
- [ ] Textarea para templates
- [ ] Campos de gateway (se aplicável)

**Aba Pagamentos:**
- [ ] Campo token Mercado Pago (mascarado)
- [ ] Campo chave pública
- [ ] Campo webhook secret (mascarado)
- [ ] Campo chave PIX

#### 4.3.3 Critérios de Aceite
- [ ] Abas só aparecem se add-on está ativo
- [ ] Formulários salvam nas options corretas
- [ ] Campos sensíveis são mascarados
- [ ] Upload de mídia funciona corretamente

---

### FASE 4: Abas de Automação (Estimativa: 4h)
**Prioridade:** 🟡 Média  
**Dependências:** Fase 3

#### 4.4.1 Objetivos
- Implementar abas para Notificações e Financeiro
- Gerenciar cron jobs e automações
- Visualizar status de agendamentos

#### 4.4.2 Tarefas

**Aba Notificações:**
- [ ] Campos de emails (múltiplos)
- [ ] Seletores de horário
- [ ] Selector de dia da semana
- [ ] Campos Telegram (token, chat ID)
- [ ] Checkboxes de ativação
- [ ] Botão de teste de envio

**Aba Financeiro:**
- [ ] Checkbox de lembretes ativados
- [ ] Campos dias antes/depois
- [ ] Textareas para templates de mensagem

#### 4.4.3 Critérios de Aceite
- [ ] Horários são validados (HH:MM)
- [ ] Emails são validados
- [ ] Cron jobs são reagendados ao salvar
- [ ] Teste de envio funciona

---

### FASE 5: Abas Avançadas (Estimativa: 5h)
**Prioridade:** 🟡 Média  
**Dependências:** Fase 4

#### 4.5.1 Objetivos
- Implementar abas para Cadastro Público, IA e Fidelidade
- Gerenciar configurações complexas
- Validar integrações externas

#### 4.5.2 Tarefas

**Aba Cadastro Público:**
- [ ] Selector de página
- [ ] Configurações reCAPTCHA
- [ ] Toggle API REST
- [ ] Campos rate limiting
- [ ] Templates de email

**Aba Assistente IA:**
- [ ] Toggle ativar/desativar
- [ ] Campo API key OpenAI (mascarado)
- [ ] Selector de modelo
- [ ] Slider de temperatura
- [ ] Campos numéricos (timeout, tokens)
- [ ] Textarea instruções adicionais
- [ ] Botão de teste de conexão

**Aba Fidelidade:**
- [ ] Configurações de pontos
- [ ] Recompensas
- [ ] Elegibilidade

#### 4.5.3 Critérios de Aceite
- [ ] Teste de API OpenAI funciona
- [ ] reCAPTCHA é validado
- [ ] Limites são respeitados

---

### FASE 6: Aba Agenda e Refinamentos (Estimativa: 3h)
**Prioridade:** 🟢 Baixa  
**Dependências:** Fase 5

#### 4.6.1 Objetivos
- Implementar aba Agenda
- Refinar UX geral
- Otimizar performance

#### 4.6.2 Tarefas
- [ ] Selector de página da agenda
- [ ] Configuração de capacidade por horário
- [ ] Campo endereço do petshop
- [ ] Melhorias de responsividade
- [ ] Validação final de segurança
- [ ] Otimização de queries

#### 4.6.3 Critérios de Aceite
- [ ] Todas as abas funcionam em mobile
- [ ] Performance aceitável (<2s load)
- [ ] Sem vulnerabilidades de segurança

---

## 5. Considerações de Segurança

### 5.1 Medidas Obrigatórias

1. **Verificação de Capability**
   ```php
   if ( ! current_user_can( 'manage_options' ) ) {
       return '<p class="dps-error">' . esc_html__( 'Acesso negado.', 'desi-pet-shower' ) . '</p>';
   }
   ```

2. **Verificação de Nonce**
   ```php
   if ( ! wp_verify_nonce( $_POST['dps_settings_nonce'], 'dps_save_settings' ) ) {
       wp_die( __( 'Falha na verificação de segurança.', 'desi-pet-shower' ) );
   }
   ```

3. **Sanitização de Inputs**
   ```php
   $value = sanitize_text_field( wp_unslash( $_POST['field_name'] ) );
   ```

4. **Escaping de Outputs**
   ```php
   echo esc_html( $value );
   echo esc_attr( $value );
   echo esc_url( $value );
   ```

### 5.2 Campos Sensíveis

Os seguintes campos devem ter tratamento especial:

| Campo | Tratamento |
|-------|------------|
| Senhas | Input type=password, nunca exibir valor real |
| API Keys | Mascarar (exibir apenas últimos 4 chars) |
| Tokens | Mascarar completamente |
| Webhook Secrets | Mascarar completamente |

### 5.3 Logs de Auditoria

Registrar alterações em campos sensíveis:
```php
DPS_Logger::log(
    sprintf( 'Configuração "%s" alterada pelo usuário %d', $option_name, get_current_user_id() ),
    DPS_Logger::LEVEL_INFO,
    'settings_changed'
);
```

---

## 6. Estrutura de Arquivos

### 6.1 Novos Arquivos a Criar

```
plugins/desi-pet-shower-base/
├── includes/
│   └── class-dps-settings-frontend.php (NOVO)
├── templates/
│   └── settings/
│       ├── settings-page.php (NOVO)
│       ├── tab-empresa.php (NOVO)
│       ├── tab-seguranca.php (NOVO)
│       └── partials/ (NOVO)
│           ├── header.php
│           ├── nav-tabs.php
│           └── footer.php
└── assets/
    └── css/
        └── dps-settings.css (NOVO)
```

### 6.2 Arquivos a Modificar

| Arquivo | Modificação |
|---------|-------------|
| `class-dps-base-frontend.php` | Modificar `render_settings()` |
| `desi-pet-shower-base.php` | Incluir nova classe |

---

## 7. Hooks para Extensibilidade

### 7.1 Hooks Existentes (Manter)

- `dps_settings_nav_tabs` - Adicionar abas de navegação
- `dps_settings_sections` - Renderizar conteúdo de seções

### 7.2 Novos Hooks Propostos

```php
// Antes de renderizar a página de configurações
do_action( 'dps_before_settings_page' );

// Após renderizar a página de configurações
do_action( 'dps_after_settings_page' );

// Antes de salvar configurações
do_action( 'dps_before_save_settings', $section );

// Após salvar configurações
do_action( 'dps_after_save_settings', $section, $success );

// Filtrar campos de uma seção
$fields = apply_filters( 'dps_settings_fields_' . $section, $fields );

// Filtrar valor antes de salvar
$value = apply_filters( 'dps_settings_sanitize_' . $option_name, $value );
```

---

## 8. Cronograma Estimado

| Fase | Estimativa | Dependências | Prioridade |
|------|------------|--------------|------------|
| Fase 1: Estrutura Base | 4h | - | 🔴 Alta |
| Fase 2: Empresa e Segurança | 3h | Fase 1 | 🔴 Alta |
| Fase 3: Add-ons Core | 6h | Fase 2 | 🟠 Média-Alta |
| Fase 4: Automação | 4h | Fase 3 | 🟡 Média |
| Fase 5: Avançadas | 5h | Fase 4 | 🟡 Média |
| Fase 6: Refinamentos | 3h | Fase 5 | 🟢 Baixa |
| **TOTAL** | **25h** | | |

---

## 9. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Exposição de dados sensíveis | Média | Alto | Mascaramento + HTTPS |
| Conflito com hooks existentes | Baixa | Médio | Manter compatibilidade |
| Performance lenta | Baixa | Baixo | Cache + lazy loading |
| Incompatibilidade mobile | Média | Médio | Testes responsivos |

---

## 10. Testes Recomendados

### 10.1 Testes de Segurança
- [ ] Tentativa de acesso sem capability
- [ ] Tentativa de submit sem nonce
- [ ] Injeção de HTML/JS em campos
- [ ] Acesso direto aos handlers

### 10.2 Testes Funcionais
- [ ] Salvar e recuperar cada campo
- [ ] Validação de campos obrigatórios
- [ ] Upload de mídia
- [ ] Navegação entre abas

### 10.3 Testes de UX
- [ ] Responsividade em mobile
- [ ] Feedback visual após ações
- [ ] Estados de loading
- [ ] Mensagens de erro claras

---

## 11. Changelog do Documento

| Versão | Data | Autor | Alterações |
|--------|------|-------|------------|
| 1.0.0 | 2026-01-10 | PRObst | Criação inicial |

---

## 12. Aprovação

Este plano deve ser aprovado antes do início da implementação.

- [ ] Revisão técnica
- [ ] Aprovação de segurança
- [ ] Aprovação de UX
- [ ] Aprovação final

---

**Próximo passo:** Após aprovação, iniciar a Fase 1 conforme detalhado na seção 4.1.
