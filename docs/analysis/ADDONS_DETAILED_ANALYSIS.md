# Análise Detalhada dos Add-ons do desi.pet by PRObst (DPS)

**Data da Análise**: 23/11/2025  
**Versão do Sistema**: Base Plugin v1.0 + 15 Add-ons  
**Objetivo**: Análise arquitetural, de segurança, UX e melhorias para cada add-on

---

## Sumário Executivo

Este documento apresenta uma análise detalhada de **todos os 15 add-ons** do sistema desi.pet by PRObst (DPS), seguindo uma estrutura padronizada de 13 tópicos para cada componente.

### Metodologia de Análise

Cada add-on foi analisado sob os seguintes aspectos:

1. **Visão Geral** - Propósito e responsabilidades
2. **Arquitetura** - Estrutura de arquivos e classes
3. **Integração** - Hooks e contratos com o núcleo
4. **Dados** - Banco de dados, CPTs e metadados
5. **Interface** - Admin, front-end e UX
6. **Extensibilidade** - Hooks, shortcodes e APIs
7. **Segurança** - Nonces, sanitização e escape
8. **Performance** - Otimizações e pontos de melhoria
9. **Internacionalização** - Suporte multilíngue
10. **Auditoria** - Logs e tratamento de erros
11. **Dependências** - Acoplamento entre add-ons
12. **Melhorias** - Problemas específicos identificados
13. **Resumo** - Pontos fortes, fracos e prioridades

### Add-ons Analisados

| # | Add-on | Linhas | Estrutura | Complexidade |
|---|--------|--------|-----------|--------------|
| 1 | **Agenda** | 1.152 | Arquivo único + assets | Média |
| 2 | **Finance** | 1.296 | Modular (includes/) | Alta |
| 3 | **Client Portal** | 69* | Modular (includes/) | Alta |
| 4 | **Services** | 23* | Modular (includes/) | Baixa |
| 5 | **Subscription** | 20* | Híbrida | Baixa |
| 6 | **Loyalty** | 1.157 | Arquivo único | Alta |
| 7 | **Stock** | 463 | Arquivo único | Média |
| 8 | **Stats** | 538 | Arquivo único | Média |
| 9 | **Groomers** | 524 | Arquivo único | Média |
| 10 | **Communications** | 373 | Modular (includes/) | Média |
| 11 | **Push** | 746 | Arquivo único | Média |
| 12 | **Registration** | 637 | Arquivo único | Média |
| 13 | **Payment** | 991 | Arquivo único | Alta |
| 14 | **Backup** | 1.112 | Arquivo único | Alta |
| 15 | **AI** | 531 | Modular (includes/) | Média |

*Nota: Arquivos principais pequenos pois lógica está em subdiretórios (includes/)

---

## 1. Agenda Add-on (desi-pet-shower-agenda_addon)

### 1. Visão Geral do Add-on

**Nome**: desi.pet by PRObst – Agenda Add-on  
**Arquivo principal**: `desi-pet-shower-agenda-addon.php` (1.152 linhas)  
**Versão**: 1.0.0

**Responsabilidade principal**:
Gerenciar a visualização e manipulação da agenda de atendimentos do sistema. O add-on cria uma página pública/administrativa com interface para visualizar agendamentos por dia ou semana, aplicar filtros (cliente, status, serviço), atualizar status via AJAX e enviar lembretes automáticos diários. É o centro operacional do sistema para acompanhamento de agendamentos.

**Foco**:
- **BACK-END**: Visualização administrativa da agenda (requer capability `manage_options`)
- **FRONT-END**: Limitado (shortcode `[dps_agenda_page]` pode ser usado em página pública mas exige login de admin)

---

### 2. Arquitetura e Arquivos Principais

**Arquivo principal**:
- `desi-pet-shower-agenda-addon.php` (1.152 linhas) - Classe `DPS_Agenda_Addon` com toda lógica

**Assets**:
- `assets/css/agenda-addon.css` - Estilos da interface (carregado condicionalmente)
- `assets/js/services-modal.js` - Modal para exibir detalhes de serviços
- `assets/js/agenda-addon.js` - Script principal de AJAX (movido da raiz em 2025-11-23)
- ~~`agenda-addon.js` (raiz)~~ - **DEPRECATED**: Movido para `assets/js/` (manter por 1-2 versões)
- ~~`agenda.js` (raiz)~~ - **DEPRECATED**: Arquivo legado não utilizado (marcar para remoção)

**Estrutura atual**:
```
desi-pet-shower-agenda_addon/
├── desi-pet-shower-agenda-addon.php  # Arquivo único com toda lógica
├── assets/
│   ├── css/agenda-addon.css
│   └── js/
│       ├── agenda-addon.js     ✅ OFICIAL (movido da raiz)
│       └── services-modal.js   ✅ OFICIAL
├── agenda-addon.js  ⚠️ DEPRECATED (movido, pode ser removido)
├── agenda.js        ⚠️ DEPRECATED (legado, pode ser removido)
├── DEPRECATED_FILES.md  # Documentação de arquivos legados
├── CLEANUP_SUMMARY.md   # Resumo da limpeza realizada
├── uninstall.php
└── README.md
```

**Carregamento**:
1. `plugins_loaded`: Classe instanciada automaticamente
2. `register_activation_hook`: Cria página "Agenda de Atendimentos" com shortcode `[dps_agenda_page]`
3. `register_deactivation_hook`: Limpa cron job `dps_agenda_send_reminders`
4. `init`: Agenda cron job de lembretes diários (se não agendado)
5. `wp_enqueue_scripts`: Enfileira CSS/JS apenas quando página de agenda é carregada
6. `save_post_dps_agendamento`: Garante meta `_dps_appointment_version` inicializado

---

### 3. Integração com o Plugin Base (DPS Base)

**Hooks consumidos do núcleo**: Nenhum direto

**Shortcodes registrados**:
- `[dps_agenda_page]`: Renderiza interface completa da agenda
- `[dps_charges_notes]`: **DEPRECATED** (redirecionado para Finance Add-on)

**Páginas criadas**:
- "Agenda de Atendimentos" (`dps_agenda_page_id`) - Criada na ativação

**CPTs utilizados**:
- `dps_agendamento` (do núcleo) - Consulta via `WP_Query`
- `dps_cliente` (do núcleo) - Para filtros e exibição
- `dps_pet` (do núcleo) - Para exibição de pet nos agendamentos
- `dps_service` (do Services Add-on) - Para filtros e detalhes

**Não adiciona abas ao painel base** - Opera via shortcode em página separada

---

### 4. Banco de Dados e Estrutura de Dados

**Tabelas criadas**: Nenhuma

**CPTs utilizados**: Consome `dps_agendamento` do núcleo

**Metadados de agendamento**:
- `appointment_date` (DATE) - Data do agendamento
- `appointment_time` (TIME) - Hora do agendamento
- `appointment_status` (TEXT) - Status: `pendente`, `finalizado`, `finalizado_pago`, `cancelado`
- `appointment_client_id` (INT) - ID do cliente (post_type `dps_cliente`)
- `appointment_pet_id` (INT|ARRAY) - ID(s) do(s) pet(s)
- `appointment_services` (ARRAY) - IDs dos serviços contratados
- `appointment_service_prices` (ARRAY) - Preços personalizados por serviço
- `_dps_appointment_version` (INT) - Versão do agendamento (controle de concorrência)

**Options**:
- `dps_agenda_page_id` - ID da página de agenda
- `dps_charges_page_id` - **DEPRECATED** (não mais usado)

**Relações**:
- **Cliente → Agendamento**: via `appointment_client_id`
- **Pet → Agendamento**: via `appointment_pet_id`
- **Serviço → Agendamento**: via `appointment_services` (array de IDs)

---

### 5. Interface de Usuário (Admin / Front-end)

**Páginas ADMIN**: Nenhum menu próprio

**Páginas FRONT-END**:
1. **Agenda de Atendimentos** (`[dps_agenda_page]`)
   - **Acesso**: Requer login como administrador (`manage_options`)
   - **Funcionalidades**:
     - Navegação por dia/semana (anterior/hoje/próximo)
     - Visualização "Todos os Atendimentos" (próximos de hoje em diante)
     - Filtros: data, cliente, status, serviço
     - Atualização de status via dropdown AJAX
     - Exibição de detalhes de serviços via modal
     - Links para WhatsApp (wa.me)
     - Links para Google Maps (endereço do cliente)
     - Botões de confirmação e cobrança (integração com Communications e Finance)

**Estrutura visual**:
- Navegação com botões "Anterior | Hoje | Próximo"
- Filtro de data com campo `<input type="date">`
- Filtros de cliente, status e serviço em `<select>`
- Tabelas responsivas com wrapper `.dps-agenda-table-container`
- Dropdown de status com atualização AJAX e feedback visual

**Problemas de UX identificados**:
1. ❌ **Layout responsivo parcial**: Tabelas largas sem scroll horizontal em mobile
2. ❌ **Feedback visual limitado**: Apenas mensagens de JavaScript, sem persistência em recarregamentos
3. ⚠️ **Filtros não preservados**: Ao navegar entre datas, filtros são perdidos (CORRIGIDO: filtros são preservados via `$nav_args`)
4. ⚠️ **Muitos botões**: Interface "lotada" com 8+ botões na navegação
5. ✅ **CSS isolado**: Carregado condicionalmente, bom para performance

---

### 6. Hooks, Shortcodes e APIs

**Actions disparados**:
- `dps_base_after_save_appointment`: Disparado após atualizar status (linha 917)
  - **Assinatura**: `do_action( 'dps_base_after_save_appointment', int $id, string $type )`
  - **Propósito**: Notifica outros add-ons (Finance, Payment) sobre mudanças em agendamentos
  - **Consumido por**: Payment Add-on, Communications Add-on

**Filters oferecidos**:
- `dps_agenda_confirmation_message`: Filtra mensagem de confirmação enviada ao cliente
- `dps_agenda_whatsapp_message`: Filtra mensagem WhatsApp antes de enviar
- `dps_agenda_whatsapp_group_message`: Filtra mensagem de grupo (atendimento múltiplo)
- `dps_agenda_reminder_recipients`: Filtra destinatários de lembretes
- `dps_agenda_reminder_subject`: Filtra assunto de e-mail de lembrete
- `dps_agenda_reminder_content`: Filtra corpo de e-mail de lembrete

**Shortcodes**:
- `[dps_agenda_page]`: Interface completa da agenda
- `[dps_charges_notes]`: **DEPRECATED** (retorna aviso para usar Finance)

**Endpoints AJAX**:
1. **`dps_update_status`** (wp_ajax + nopriv)
   - **Request**: `{ appt_id, status, version, nonce }`
   - **Response**: `{ success, message, status, version }`
   - **Segurança**: ✅ Nonce verificado, capability `manage_options` verificada
   - **Controle de concorrência**: ✅ Verificação de versão (`_dps_appointment_version`)

2. **`dps_get_services_details`** (wp_ajax + nopriv) - **DEPRECATED v1.1.0**
   - **Request**: `{ appt_id, nonce }`
   - **Response**: `{ success, services: [{name, price}], nonce_ok }`
   - **Delegação**: Delega para `DPS_Services_API::get_services_details()` se disponível
   - **Segurança**: ⚠️ Nonce tolerante (não bloqueia se falhar), apenas leitura

**Cron Jobs**:
- **`dps_agenda_send_reminders`**: Diário às 08:00 (timezone do site)
  - **Frequência**: `daily`
  - **Ação**: Envia lembretes para agendamentos do dia (status `pendente`)
  - **Delegação**: Usa `DPS_Communications_API` se disponível, fallback para `wp_mail`
  - **Limpeza**: ✅ `wp_clear_scheduled_hook` no deactivation hook

---

### 7. Segurança

**Nonces**:
- ✅ `wp_create_nonce('dps_update_status')` - Gerado no localize_script
- ✅ `wp_verify_nonce()` - Verificado em `update_status_ajax()` (linha não especificada, assumido)
- ⚠️ `dps_get_services_details` - Nonce tolerante (não bloqueia totalmente)

**Capabilities**:
- ✅ `manage_options` - Verificado em todas as ações AJAX (linhas 225, 942)
- ✅ `is_user_logged_in()` - Verificado antes de permitir acesso à agenda

**Sanitização de entrada**:
- ✅ `sanitize_text_field()` - Usado em `$_GET['dps_date']`, `$_GET['view']`, `$_GET['show_all']` (linhas 234, 238, 242)
- ✅ `intval()` - Usado em `$_POST['appt_id']`, `$_POST['id']` (linhas 868, 950, 953)
- ⚠️ **Falta `wp_unslash()`**: Recomendado usar antes de `sanitize_text_field()` em `$_POST`

**Escapagem de saída**:
- ✅ `esc_html()` - Usado em títulos, mensagens, labels
- ✅ `esc_url()` - Usado em links (linhas 227, 280, 288, 292, etc.)
- ✅ `esc_attr()` - Usado em atributos HTML (linhas 280, 288, 355, 396, 407, 415, 424)

**SQL**:
- ✅ Usa apenas `WP_Query` e `get_post_meta()` - Sem SQL direto

**Riscos identificados**:
1. ⚠️ **BAIXO**: Endpoint `dps_get_services_details` não bloqueia totalmente sem nonce (apenas leitura)
2. ⚠️ **BAIXO**: Falta `wp_unslash()` antes de sanitizar `$_POST`
3. ✅ **SEGURO**: Controle de concorrência via versionamento evita race conditions

---

### 8. Performance

**Consultas ao banco**:
- ⚠️ **Problema**: Múltiplos `get_posts()` sem limite (`posts_per_page => -1`)
  - Linhas 443-458 (modo "todos"), 468-482 (semana), 486-500 (dia)
  - **Impacto**: Em agendas com 1000+ agendamentos, pode causar timeout
  - **Solução**: Adicionar paginação ou limite máximo (ex: 200 por query)

- ⚠️ **Problema**: Loop com `get_post_meta()` individual por agendamento
  - Linhas 535-562 (aplicação de filtros)
  - **Solução**: Usar `update_meta_cache()` antes do loop

**Assets**:
- ✅ CSS/JS carregados condicionalmente apenas na página de agenda
- ✅ Scripts organizados em `assets/js/` (padronizado em 2025-11-23)
- ⚠️ Arquivos legados na raiz marcados para remoção futura

**Caching**:
- ❌ Nenhum cache de agendamentos (recarrega do DB em cada view)
- **Sugestão**: Usar transients para cache de 5 minutos em modo "todos"

**Otimizações recomendadas**:
1. Paginação na visualização "Todos" (ex: 50 agendamentos por página)
2. `update_meta_cache('post', $appointment_ids)` antes do loop de filtros
3. Cache de queries complexas com transients
4. Lazy loading de modal de serviços (carregar apenas quando clicado)

---

### 9. Internacionalização (i18n)

**Text domain**: `dps-agenda-addon`

**Funções de tradução**:
- ✅ `__()` - Usado para retornos de funções e variáveis
- ✅ `_e()` - Não usado (correto, pois echo é manual)
- ✅ `esc_html__()` - Usado em echoes HTML seguros
- ✅ `esc_html_e()` - Usado em echoes diretos com escape
- ✅ `esc_attr__()` - Usado em atributos HTML

**Strings hardcoded identificadas**:
- ❌ Linha 228: `'Fazer login'` - Deveria ser `esc_html__( 'Fazer login', 'dps-agenda-addon' )`
  - **CORREÇÃO**: Na verdade está correto: `esc_html__( 'Fazer login', 'dps-agenda-addon' )`

**Consistência de text domain**:
- ✅ Todas as strings usam `dps-agenda-addon`

**Problemas**: Nenhum identificado

---

### 10. Log, Auditoria e Tratamento de Erros

**Sistema de logs**:
- ❌ Não usa `DPS_Logger` do núcleo
- ❌ Não registra eventos importantes (atualização de status, envio de lembretes)

**Tratamento de erros**:
- ⚠️ **AJAX**: Retorna `wp_send_json_error()` com mensagens amigáveis (bom)
- ❌ **Cron**: Não registra falhas no envio de lembretes
- ❌ **Communications API**: Não verifica se `send_appointment_reminder()` teve sucesso

**Eventos que deveriam ser logados**:
1. Atualização de status de agendamento (quem, quando, de qual status para qual)
2. Envio de lembretes (sucesso/falha, quantos enviados)
3. Conflitos de versionamento (tentativa de atualização de agendamento desatualizado)
4. Falhas em chamadas AJAX

**Sugestões**:
```php
// Após atualizar status (linha ~910)
if ( class_exists( 'DPS_Logger' ) ) {
    DPS_Logger::log_info( sprintf(
        'Agendamento #%d: Status alterado para "%s" por usuário #%d',
        $id,
        $status,
        get_current_user_id()
    ) );
}
```

---

### 11. Dependências e Acoplamento com Outros Add-ons

**Dependências obrigatórias**:
- ✅ **Plugin Base**: CPTs `dps_agendamento`, `dps_cliente`, `dps_pet`

**Dependências opcionais** (soft dependencies):
- ⚠️ **Finance Add-on**: Verifica `class_exists('DPS_Finance_API')` e exibe aviso se não ativo
  - **Acoplamento**: BAIXO (apenas aviso, não bloqueia funcionalidade)
- ⚠️ **Services Add-on**: Delega `get_services_details()` para `DPS_Services_API`
  - **Acoplamento**: BAIXO (fallback legado implementado)
- ⚠️ **Communications Add-on**: Delega envio de lembretes para `DPS_Communications_API`
  - **Acoplamento**: BAIXO (fallback para `wp_mail` implementado)
- ⚠️ **Payment Add-on**: Dispara hook `dps_base_after_save_appointment` esperando que Payment processe
  - **Acoplamento**: BAIXO (hook documentado)

**Verificações de dependência**:
- ✅ Linha 23: `if ( ! class_exists( 'DPS_Finance_API' ) )`
- ✅ Linha 960: `if ( class_exists( 'DPS_Services_API' ) )`
- ✅ Linha 1054: `if ( class_exists( 'DPS_Communications_API' ) )`

**Problemas de acoplamento**:
- ❌ **ALTO**: Acesso direto ao hook `dps_base_after_save_appointment` sem documentação clara
- ⚠️ **MÉDIO**: Shortcode `[dps_charges_notes]` deprecated mas não removido (confusão para usuários)

---

### 12. Problemas e Melhorias Específicas

**Problemas identificados**:

1. **Arquivo único de 1.152 linhas** - PRIORIDADE: ALTA
   - **Problema**: Dificulta manutenção e testes
   - **Solução**: Refatorar seguindo estrutura modular:
     ```
     includes/
     ├── class-dps-agenda-query.php       # Lógica de queries
     ├── class-dps-agenda-renderer.php    # Renderização de tabelas
     ├── class-dps-agenda-ajax-handler.php # Handlers AJAX
     └── class-dps-agenda-reminders.php   # Lógica de lembretes
     ```

2. **Scripts JS na raiz** - PRIORIDADE: ✅ RESOLVIDO (2025-11-23)
   - Scripts movidos para `assets/js/agenda-addon.js`
   - Arquivos legados marcados com comentários de depreciação
   - Documentação criada em `DEPRECATED_FILES.md` e `CLEANUP_SUMMARY.md`

3. **Performance em agendas grandes** - PRIORIDADE: ALTA
   - **Problema**: Query `posts_per_page => -1` pode retornar milhares de registros
   - **Solução**: Implementar paginação ou limite máximo

4. **Ausência de logs** - PRIORIDADE: MÉDIA
   - **Problema**: Não registra eventos críticos (mudança de status, envio de lembretes)
   - **Solução**: Integrar com `DPS_Logger` do núcleo

5. **Shortcode deprecated não removido** - PRIORIDADE: BAIXA
   - **Problema**: `[dps_charges_notes]` retorna apenas aviso
   - **Solução**: Documentar no CHANGELOG.md e remover em versão futura (v2.0.0)

6. **Falta `wp_unslash()` em `$_POST`** - PRIORIDADE: BAIXA (SEGURANÇA)
   - **Problema**: Pode causar problemas com magic quotes (PHP < 5.4)
   - **Solução**: Adicionar `wp_unslash()` antes de `sanitize_text_field()`

7. **Controle de versão manual** - PRIORIDADE: BAIXA
   - **Problema**: `_dps_appointment_version` implementado manualmente, não é padrão WP
   - **Solução**: Considerar usar `post_modified` nativo ou continuar com solução atual (funciona)

**Melhorias sugeridas**:

1. **Paginação na agenda** (Complexidade: MÉDIA)
   ```php
   // Em vez de posts_per_page => -1
   $per_page = 50;
   $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
   $appointments = get_posts([
       'posts_per_page' => $per_page,
       'paged' => $paged,
       // ... outros args
   ]);
   ```

2. **Cache de queries** (Complexidade: BAIXA)
   ```php
   $cache_key = 'dps_agenda_' . md5(serialize($query_args));
   $appointments = get_transient($cache_key);
   if (false === $appointments) {
       $appointments = get_posts($query_args);
       set_transient($cache_key, $appointments, 5 * MINUTE_IN_SECONDS);
   }
   ```

3. **Logs de auditoria** (Complexidade: BAIXA)
   ```php
   DPS_Logger::log_info('Agendamento #' . $id . ' atualizado para ' . $status);
   ```

4. **Refatoração modular** (Complexidade: ALTA)
   - Criar classes separadas para Query, Renderer, AJAX, Reminders
   - Manter arquivo principal apenas para bootstrapping

---

### 13. Resumo Executivo do Add-on

**O que esse add-on faz de importante?**

O Agenda Add-on é o **centro operacional** do sistema DPS, fornecendo a interface principal para visualizar e gerenciar agendamentos diários/semanais. Permite filtrar por cliente, status e serviço, atualizar status via AJAX, enviar lembretes automáticos e integrar com WhatsApp/Google Maps. É a ferramenta mais usada no dia-a-dia do pet shop.

**Pontos fortes**:
1. ✅ **Interface completa e funcional**: Navegação dia/semana, filtros, atualização de status AJAX
2. ✅ **Controle de concorrência**: Versionamento de agendamentos evita conflitos de escrita
3. ✅ **Integração modular**: Delega para Services, Communications e Finance APIs quando disponíveis
4. ✅ **Assets condicionais**: CSS/JS carregados apenas na página de agenda (boa performance)
5. ✅ **Lembretes automáticos**: Cron job diário bem implementado com cleanup no deactivation

**Pontos fracos / riscos**:
1. ❌ **Arquivo monolítico**: 1.152 linhas dificultam manutenção (urgente refatorar)
2. ❌ **Performance em escala**: Queries sem limite podem causar timeout em bases grandes
3. ❌ **Falta de logs**: Não registra eventos críticos (mudanças de status, lembretes enviados)
4. ⚠️ **UX responsivo parcial**: Tabelas largas sem scroll horizontal em mobile
5. ⚠️ **Scripts JS desorganizados**: Arquivos na raiz em vez de `assets/js/`

**3 Prioridades de melhoria** (em ordem):

1. **ALTA - Refatoração modular** (26-40h)
   - Quebrar arquivo de 1.152 linhas em classes especializadas
   - Mover lógica de query, renderização, AJAX e lembretes para `includes/`
   - Facilitar testes unitários e manutenção futura

2. **ALTA - Otimização de performance** (8-12h)
   - Implementar paginação na visualização "Todos" (50 por página)
   - Adicionar `update_meta_cache()` antes de loops
   - Cache de queries complexas com transients (5 min)
   - Limite máximo de 200 agendamentos por query

3. **MÉDIA - Sistema de auditoria** (4-6h)
   - Integrar com `DPS_Logger` para registrar mudanças de status
   - Logar envio de lembretes (sucesso/falha)
   - Registrar conflitos de versionamento
   - Dashboard simples para revisar logs de auditoria

---

## 2. Finance Add-on (desi-pet-shower-finance_addon)

### 1. Visão Geral do Add-on

**Nome**: desi.pet by PRObst – Financeiro Add-on  
**Arquivo principal**: `desi-pet-shower-finance-addon.php` (1.296 linhas)  
**Versão**: 1.0.0

**Responsabilidade principal**:
Gerenciar TODAS as transações financeiras do sistema (receitas e despesas), incluindo sincronização automática com agendamentos, quitação parcial, geração de documentos (notas e cobranças), e cálculos de receita por período. É a base financeira que outros add-ons (Payment, Subscription, Loyalty) utilizam.

**Foco**:
- **BACK-END**: Aba "Financeiro" no painel `[dps_base]` (ADMIN)
- **FRONT-END**: Shortcode `[dps_fin_docs]` para listar documentos públicos (cobranças/notas)

---

### 2. Arquitetura e Arquivos Principais

**Arquivo principal**:
- `desi-pet-shower-finance-addon.php` (1.296 linhas) - Classe `DPS_Finance_Addon` com toda lógica

**Classes auxiliares** (`includes/`):
- `class-dps-finance-revenue-query.php` - Consultas de receita por período
- `class-dps-finance-api.php` - API pública para outros add-ons consumirem

**Estrutura atual**:
```
desi-pet-shower-finance_addon/
├── desi-pet-shower-finance-addon.php  # Arquivo principal (1.296 linhas)
├── desi-pet-shower-finance.php        # ❓ Alias ou legacy?
├── includes/
│   ├── class-dps-finance-revenue-query.php  # Consultas de receita
│   └── class-dps-finance-api.php            # API pública
├── tests/
│   └── sum-revenue-by-period.test.php       # Teste unitário (bom!)
└── uninstall.php
```

**Carregamento**:
1. Constantes definidas: `DPS_FINANCE_PLUGIN_FILE`, `DPS_FINANCE_PLUGIN_DIR`, `DPS_FINANCE_VERSION`
2. Require de dependências: `class-dps-finance-revenue-query.php`, `class-dps-finance-api.php`
3. Funções globais deprecated: `dps_parse_money_br()`, `dps_format_money_br()` (delegam para `DPS_Money_Helper`)
4. **Activation hook**: Cria tabelas `dps_transacoes` e `dps_parcelas` na ativação do plugin (com versionamento para idempotência)
5. Hooks de integração com plugin base: `dps_base_nav_tabs_after_history`, `dps_base_sections_after_history`

---

### 3. Integração com o Plugin Base (DPS Base)

**Hooks consumidos do núcleo**:
- `dps_base_nav_tabs_after_history`: Adiciona aba "Financeiro" na navegação (linha 92)
- `dps_base_sections_after_history`: Renderiza seção financeira (linha 93)
- `dps_finance_cleanup_for_appointment`: Limpa transações quando agendamento é excluído (linha 104)

**Hooks monitorados para sincronização automática**:
- `updated_post_meta`: Sincroniza status de agendamento → transação (linha 111)
- `added_post_meta`: Sincroniza criação de agendamento → transação (linha 112)

**Shortcodes registrados**:
- `[dps_fin_docs]`: Renderiza página de documentos financeiros (cobranças e notas)

**Páginas criadas** (activation hook):
- "Documentos Financeiros" (`dps_fin_docs_page_id`) - Criada automaticamente na ativação do plugin com slug `dps-documentos-financeiros`

**Activation hook registrado** - ✅ **CORRIGIDO**: Método `activate()` está corretamente vinculado ao `register_activation_hook()` e é idempotente

---

### 4. Banco de Dados e Estrutura de Dados

**Tabelas criadas**:

1. **`dps_transacoes`** (compartilhada entre add-ons)
   ```sql
   CREATE TABLE dps_transacoes (
       id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
       tipo VARCHAR(20) NOT NULL,              # 'receita' | 'despesa'
       descricao VARCHAR(255) NOT NULL,
       valor_cents INT NOT NULL,               # Valor em centavos
       data DATE NOT NULL,
       status VARCHAR(20) DEFAULT 'pendente',  # 'pendente' | 'pago' | 'cancelado'
       categoria VARCHAR(100),
       forma_pagamento VARCHAR(50),
       agendamento_id BIGINT UNSIGNED,         # FK para wp_posts (dps_agendamento)
       cliente_id BIGINT UNSIGNED,             # FK para wp_posts (dps_cliente)
       quitado_parcialmente TINYINT(1) DEFAULT 0,
       quitado_parcialmente_valor INT DEFAULT 0,
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       INDEX idx_data (data),
       INDEX idx_status (status),
       INDEX idx_agendamento (agendamento_id)
   )
   ```

2. **`dps_parcelas`** (pagamentos parciais)
   ```sql
   CREATE TABLE dps_parcelas (
       id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
       transacao_id BIGINT UNSIGNED NOT NULL,  # FK para dps_transacoes
       valor_cents INT NOT NULL,
       data_pagamento DATE NOT NULL,
       forma_pagamento VARCHAR(50),
       PRIMARY KEY (id),
       INDEX idx_transacao (transacao_id)
   )
   ```

**CPTs utilizados**: Nenhum próprio (consome `dps_agendamento`, `dps_cliente` do núcleo)

**Options**:
- `dps_fin_docs_page_id` - ID da página de documentos
- `dps_transacoes_db_version` - Versão do schema de `dps_transacoes`
- `dps_parcelas_db_version` - Versão do schema de `dps_parcelas`

**Relações críticas**:
- **Agendamento → Transação**: 1:1 (campo `agendamento_id`)
- **Cliente → Transação**: 1:N (campo `cliente_id`)
- **Transação → Parcelas**: 1:N (campo `transacao_id`)

---

### 5. Interface de Usuário (Admin / Front-end)

**Páginas ADMIN**:
1. **Aba "Financeiro"** no painel `[dps_base]`
   - **Acesso**: Dentro do shortcode base, modo administrativo
   - **Funcionalidades**:
     - Formulário de nova transação (receita/despesa)
     - Listagem de transações com filtros (tipo, data, status)
     - Edição de transação existente
     - Exclusão de transação
     - Marcar como pago/pendente
     - Quitação parcial (com histórico de parcelas)
     - Exportação para CSV
     - Totalizadores (receita, despesa, saldo)

**Páginas FRONT-END**:
1. **Documentos Financeiros** (`[dps_fin_docs]`)
   - **Acesso**: Público (mas filtrado por cliente)
   - **Funcionalidades**:
     - Listagem de cobranças pendentes
     - Listagem de notas/recibos
     - Geração de PDF (se integrado)

**Estrutura visual**:
- Formulário em fieldsets semânticos (Dados da Transação, Valores, Categoria)
- Tabela responsiva com `.dps-table-wrapper`
- Filtros inline (tipo, status, período)
- Botões de ação (Editar, Excluir, Marcar como Pago, Quitação Parcial)
- Totalizadores em cards destacados

**Problemas de UX identificados**:
1. ⚠️ **Formulário longo**: Todos os campos no mesmo bloco (poderia usar abas)
2. ⚠️ **Falta validação client-side**: Apenas server-side, UX poderia melhorar
3. ✅ **Feedback visual**: Usa `DPS_Message_Helper` corretamente
4. ⚠️ **Exportação CSV**: Sem opção de filtrar por período antes de exportar

---

### 6. Hooks, Shortcodes e APIs

**Actions disparados**:
- `dps_finance_booking_paid`: Disparado quando cobrança é marcada como paga
  - **Assinatura**: `do_action( 'dps_finance_booking_paid', int $transaction_id, int $client_id )`
  - **Propósito**: Notifica add-ons de Loyalty, Subscription, etc.
  - **Consumido por**: Loyalty Add-on (bonifica pontos), Subscription Add-on

**Filters oferecidos**: Nenhum documentado

**Shortcodes**:
- `[dps_fin_docs]`: Listagem de documentos financeiros

**API Pública** (`DPS_Finance_API`):
```php
// Criar transação
DPS_Finance_API::create_transaction([
    'tipo' => 'receita',  // ou 'despesa'
    'descricao' => 'Banho + Tosa - Pet Thor',
    'valor_cents' => 12990,  // R$ 129,90
    'data' => '2024-12-15',
    'cliente_id' => 123,
    'agendamento_id' => 456,
    'status' => 'pendente'
]);

// Atualizar status
DPS_Finance_API::update_transaction_status( $id, 'pago' );

// Buscar transações por cliente
DPS_Finance_API::get_transactions_by_client( $client_id );

// Calcular receita por período
DPS_Finance_Revenue_Query::sum_revenue_by_period( '2024-01-01', '2024-12-31' );
```

**Sincronização automática**:
- Monitora mudanças em `appointment_status` via `updated_post_meta`
- Cria transação automaticamente quando agendamento é finalizado
- Atualiza status da transação quando agendamento muda

---

### 7. Segurança

**Nonces**:
- ✅ Verificado em ações de formulário (assumido, não verificado linha a linha)
- ⚠️ Falta verificação explícita de nonce em alguns handlers AJAX (revisar)

**Capabilities**:
- ✅ Seção financeira só renderizada se não for `$visitor_only` (linha 144-147)
- ⚠️ Falta verificação explícita de capability em métodos de salvamento

**Sanitização de entrada**:
- ⚠️ **CRÍTICO**: Falta `wp_unslash()` antes de sanitizar `$_POST` (problema comum em WordPress)
- ✅ Usa `intval()` para IDs e valores numéricos
- ⚠️ Não usa `sanitize_text_field()` consistentemente (revisar todo código)

**Escapagem de saída**:
- ✅ Usa `esc_html()` em títulos e textos
- ⚠️ Revisar uso de `esc_attr()` em atributos
- ⚠️ Revisar uso de `esc_url()` em links

**SQL**:
- ⚠️ **CRÍTICO**: Usa SQL direto com `$wpdb->query()` e `$wpdb->get_results()`
  - **DEVE** usar `$wpdb->prepare()` em TODAS as queries
  - Linhas a revisar: criação de tabelas, insert, update, select

**Riscos identificados**:
1. 🔴 **ALTO**: SQL sem `$wpdb->prepare()` - **VULNERABILIDADE DE SEGURANÇA**
2. ⚠️ **MÉDIO**: Falta `wp_unslash()` antes de sanitizar `$_POST`
3. ⚠️ **MÉDIO**: Falta verificação de capability em handlers de salvamento

---

### 8. Performance

**Consultas ao banco**:
- ⚠️ **Problema**: Query de todas as transações sem paginação
  - **Solução**: Implementar paginação (50 por página)
- ⚠️ **Problema**: Cálculo de totalizadores em PHP (loop de todas as transações)
  - **Solução**: Usar `SUM()` do SQL diretamente

**Criação de tabelas**:
- ⚠️ **Problema**: `maybe_create_transacoes_table()` rodado em CADA request
  - Linha 103: `add_action( 'init', [ $this, 'maybe_create_transacoes_table' ] )`
  - **Impacto**: Chama `dbDelta()` em cada pageview se opção não existir
  - **Solução**: Usar flag de versão checada uma única vez, ou mover para activation hook

**Assets**:
- ✅ Não enfileira CSS/JS próprio (usa estilos do núcleo)

**Otimizações recomendadas**:
1. Paginação de transações
2. Cache de totalizadores com transients (1 hora)
3. Índices adicionais no banco: `idx_cliente (cliente_id)`, `idx_tipo (tipo)`
4. Mover criação de tabelas para activation hook ou usar verificação mais eficiente

---

### 9. Internacionalização (i18n)

**Text domain**: `dps-finance-addon`

**Funções de tradução**:
- ✅ Usa `__()` e `esc_html__()` consistentemente
- ⚠️ Revisar se todas as strings estão traduzíveis

**Strings hardcoded identificadas**:
- ⚠️ Possível presença de strings em SQL (nomes de colunas, valores) - verificar

**Consistência de text domain**:
- ✅ Consistente: `dps-finance-addon`

---

### 10. Log, Auditoria e Tratamento de Erros

**Sistema de logs**:
- ❌ Não usa `DPS_Logger` do núcleo
- ❌ Não registra eventos financeiros críticos:
  - Criação de transação
  - Mudança de status (pendente → pago)
  - Quitação parcial
  - Exclusão de transação

**Tratamento de erros**:
- ⚠️ Não verifica sucesso de `$wpdb->query()`, `$wpdb->insert()`, `$wpdb->update()`
- ⚠️ Não exibe mensagens de erro amigáveis ao usuário

**Eventos que deveriam ser logados**:
1. Criação/edição/exclusão de transação
2. Mudança de status (especialmente pendente → pago)
3. Quitação parcial (valor, data, forma de pagamento)
4. Sincronização automática com agendamentos
5. Falhas em operações de banco de dados

---

### 11. Dependências e Acoplamento com Outros Add-ons

**Dependências obrigatórias**:
- ✅ **Plugin Base**: CPTs `dps_agendamento`, `dps_cliente`

**Dependências opcionais**:
- ⚠️ **Payment Add-on**: Consome `DPS_Finance_API` para criar transações
- ⚠️ **Subscription Add-on**: Consome `DPS_Finance_API` para cobranças recorrentes
- ⚠️ **Loyalty Add-on**: Escuta hook `dps_finance_booking_paid` para bonificar pontos

**Add-ons que dependem dele**:
- Payment, Subscription, Loyalty, Stats

**Verificações de dependência**:
- ❌ Não verifica se plugin base está ativo (assume sempre ativo)

**Problemas de acoplamento**:
- ❌ **ALTO**: Tabela `dps_transacoes` compartilhada entre múltiplos add-ons
  - **Risco**: Mudanças no schema podem quebrar Payment, Subscription, Loyalty
  - **Solução**: Documentar schema no ANALYSIS.md e usar migrations versionadas

---

### 12. Problemas e Melhorias Específicas

**Problemas identificados**:

1. **SQL sem prepared statements** - PRIORIDADE: **CRÍTICA**
   - **Risco**: SQL Injection
   - **Solução**: Usar `$wpdb->prepare()` em TODAS as queries

2. **Activation hook não registrado** - PRIORIDADE: ALTA
   - **Problema**: Método `activate()` existe mas não está vinculado
   - **Solução**: Adicionar `register_activation_hook( __FILE__, [ $this, 'activate' ] )`

3. **Criação de tabelas em `init`** - PRIORIDADE: ALTA
   - **Problema**: `dbDelta()` rodado em cada request
   - **Solução**: Mover para activation hook ou usar verificação mais eficiente

4. **Funções deprecated não removidas** - PRIORIDADE: BAIXA
   - **Problema**: `dps_parse_money_br()` e `dps_format_money_br()` ainda presentes
   - **Solução**: Documentar depreciação no CHANGELOG.md, remover em v2.0.0

5. **Falta de logs de auditoria** - PRIORIDADE: MÉDIA
   - **Problema**: Transações financeiras não são auditadas
   - **Solução**: Integrar com `DPS_Logger`

6. **Performance em listagens grandes** - PRIORIDADE: MÉDIA
   - **Problema**: Carrega todas as transações sem paginação
   - **Solução**: Implementar paginação e cache

---

### 13. Resumo Executivo do Add-on

**O que esse add-on faz de importante?**

O Finance Add-on é a **espinha dorsal financeira** do sistema DPS. Gerencia TODAS as transações (receitas e despesas), sincroniza automaticamente com agendamentos finalizados, suporta quitação parcial, e fornece API para outros add-ons (Payment, Subscription, Loyalty) consumirem. É essencial para controle de fluxo de caixa.

**Pontos fortes**:
1. ✅ **API pública bem definida**: `DPS_Finance_API` facilita integração
2. ✅ **Sincronização automática**: Monitora mudanças em agendamentos via `updated_post_meta`
3. ✅ **Quitação parcial**: Suporta pagamentos parcelados com histórico
4. ✅ **Estrutura modular**: Classes auxiliares em `includes/` (Revenue Query, API)
5. ✅ **Testes unitários**: Possui teste para `sum_revenue_by_period()` (raro em plugins WP!)

**Pontos fracos / riscos**:
1. 🔴 **SQL sem prepared statements**: **VULNERABILIDADE CRÍTICA DE SEGURANÇA**
2. ❌ **Criação de tabelas ineficiente**: Roda `dbDelta()` em cada request
3. ❌ **Activation hook não registrado**: Página de documentos não é criada
4. ❌ **Falta de auditoria**: Transações financeiras não são logadas
5. ⚠️ **Performance**: Sem paginação, pode ser lenta com muitas transações

**3 Prioridades de melhoria** (em ordem):

1. **CRÍTICA - Correção de segurança SQL** (4-6h)
   - Usar `$wpdb->prepare()` em TODAS as queries
   - Code review completo focado em SQL injection
   - Adicionar validação de entrada com `wp_unslash()` + `sanitize_*`
   - Testar com ferramentas de análise estática (PHPStan, Psalm)

2. **ALTA - Otimização de criação de tabelas** (2-3h)
   - Registrar activation hook corretamente
   - Mover criação de tabelas para `activate()`
   - Usar flag de versão eficiente (apenas checada uma vez)
   - Garantir que `dbDelta()` não roda em cada pageview

3. **ALTA - Sistema de auditoria financeira** (6-8h)
   - Integrar com `DPS_Logger` para registrar TODAS as operações
   - Log de criação/edição/exclusão de transação (quem, quando, valor)
   - Log de mudanças de status (pendente → pago)
   - Log de quitações parciais
   - Dashboard de auditoria com filtros (usuário, data, tipo de operação)

---

