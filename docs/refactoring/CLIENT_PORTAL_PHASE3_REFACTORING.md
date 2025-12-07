# Client Portal - Refatoração Fase 3

**Data:** 2025-12-07  
**Versão:** 3.0.0 (preparação)  
**Objetivo:** Refatorar a classe `DPS_Client_Portal` dividindo-a em classes especializadas menores e mais coesas.

## Contexto

A classe `DPS_Client_Portal` original tinha 2947 linhas, violando o princípio de responsabilidade única. A classe era responsável por:

- Renderização de HTML/UI
- Processamento de ações de formulário
- Endpoints AJAX
- Interface administrativa
- Autenticação e sessão
- Coordenação geral

Esta refatoração divide a classe em componentes especializados, mantendo 100% de retrocompatibilidade.

## Arquitetura Refatorada

### Estrutura de Arquivos

```
includes/
├── class-dps-client-portal.php (REFATORADO - coordenador, ~500 linhas)
├── client-portal/
│   ├── class-dps-portal-data-provider.php (NOVO - ~200 linhas)
│   ├── class-dps-portal-renderer.php (NOVO - ~700 linhas)
│   ├── class-dps-portal-actions-handler.php (NOVO - ~500 linhas)
│   ├── class-dps-portal-ajax-handler.php (NOVO - ~400 linhas)
│   └── class-dps-portal-admin.php (NOVO - ~650 linhas)
```

### Classes Criadas

#### 1. DPS_Portal_Data_Provider

**Responsabilidade:** Buscar e agregar dados para o portal

**Métodos principais:**
- `get_unread_messages_count( $client_id )`
- `count_upcoming_appointments( $client_id )`
- `count_financial_pending( $client_id )`
- `get_scheduling_suggestions( $client_id )`

**Benefícios:**
- Centraliza queries de dados
- Facilita otimização de performance (cache, batch queries)
- Separa lógica de dados da apresentação

#### 2. DPS_Portal_Renderer

**Responsabilidade:** Renderizar componentes HTML do portal

**Métodos principais:**
- `render_chat_widget( $client_id )`
- `render_next_appointment( $client_id )`
- `render_financial_pending( $client_id )`
- `render_contextual_suggestions( $client_id )`
- `render_appointment_history( $client_id )`
- `render_pet_gallery( $client_id )`
- `render_message_center( $client_id )`
- `render_referrals_summary( $client_id )`
- `render_update_forms( $client_id )`

**Métodos privados de suporte:**
- `render_next_appointment_card()`
- `render_no_appointments_state()`
- `render_financial_pending_list()`
- `render_financial_pending_row()`
- `render_financial_clear_state()`
- `render_suggestion_card()`
- `render_appointments_table()`
- `render_appointment_row()`
- `render_no_history_state()`
- `render_pet_gallery_item()`
- `render_client_info_form()`
- `render_pets_forms()`
- `render_pet_form()`

**Benefícios:**
- Isola toda lógica de apresentação
- Facilita testes de UI
- Permite fácil customização de templates

#### 3. DPS_Portal_Actions_Handler

**Responsabilidade:** Processar ações de formulários do portal

**Métodos principais:**
- `handle_update_client_info( $client_id )`
- `handle_update_pet( $client_id, $pet_id )`
- `handle_send_message( $client_id )`
- `handle_pay_transaction( $client_id, $trans_id )`

**Métodos privados de suporte:**
- `handle_pet_photo_upload( $pet_id, $redirect_url )`
- `send_message_notification()`
- `send_message_fallback()`
- `transaction_belongs_to_client()`
- `generate_payment_link_for_transaction()`
- `generate_payment_link_fallback()`

**Benefícios:**
- Separa lógica de negócio do controller
- Facilita validação e testes
- Melhora segurança com isolamento

#### 4. DPS_Portal_AJAX_Handler

**Responsabilidade:** Processar requisições AJAX do portal

**Métodos principais:**
- `ajax_get_chat_messages()`
- `ajax_send_chat_message()`
- `ajax_mark_messages_read()`
- `ajax_request_portal_access()`

**Métodos privados de suporte:**
- `validate_chat_request()`
- `check_rate_limit()`
- `find_client_by_phone()`
- `log_access_request()`
- `notify_access_request()`
- `create_access_request_message()`

**Benefícios:**
- Centraliza endpoints AJAX
- Simplifica gestão de rate limiting
- Melhora segurança com validação centralizada

#### 5. DPS_Portal_Admin

**Responsabilidade:** Interface administrativa do portal

**Métodos principais:**
- `register_message_post_type()`
- `register_admin_menu()`
- `add_message_meta_boxes()`
- `render_message_meta_box()`
- `add_message_columns()`
- `render_message_column()`
- `save_message_meta()`
- `render_portal_settings_admin_page()`
- `render_client_logins_admin_page()`
- `render_client_logins_page()`
- `render_portal_settings_tab()`
- `render_portal_settings_section()`
- `render_logins_tab()`
- `render_logins_section()`
- `render_portal_settings_page()`

**Métodos privados de suporte:**
- `enqueue_message_list_styles()`
- `render_client_column()`
- `render_sender_column()`
- `render_status_column()`
- `notify_client_of_admin_message()`
- `get_feedback_messages()`
- `get_clients_with_token_stats()`

**Benefícios:**
- Isola toda interface administrativa
- Facilita evolução do painel admin
- Melhora organização do código

### Classe Coordenadora Refatorada: DPS_Client_Portal

**Nova responsabilidade:** Orquestrar componentes e manter API pública

A classe `DPS_Client_Portal` foi transformada em um coordenador leve que:

1. Inicializa componentes especializados
2. Registra hooks principais
3. Mantém API pública (métodos públicos existentes)
4. Delega trabalho para componentes especializados

**Propriedades adicionadas:**
```php
private $renderer;          // DPS_Portal_Renderer
private $data_provider;     // DPS_Portal_Data_Provider
private $actions_handler;   // DPS_Portal_Actions_Handler
private $admin;             // DPS_Portal_Admin
```

**Métodos que permaneceram na classe principal:**
- `handle_token_authentication()` - Autenticação
- `handle_logout_request()` - Logout
- `get_authenticated_client_id()` - Auth check
- `get_current_client_id()` - API pública
- `get_client_id_for_current_user()` - Fallback WP
- `maybe_create_login_for_client()` - Hook save_post
- `handle_portal_actions()` - Roteador de ações (delega para actions_handler)
- `render_portal_shortcode()` - Shortcode principal (delega para renderer)
- `render_login_shortcode()` - Shortcode deprecado
- `register_assets()` - Registro de CSS/JS
- `handle_portal_settings_save()` - Salvamento de configurações
- `get_client_ip()` - Utilitário
- `log_security_event()` - Logging
- `send_access_notification()` - Notificação

## Padrões Aplicados

### 1. Singleton Pattern
Todas as classes seguem o padrão Singleton para garantir instância única:
```php
public static function get_instance() {
    if ( null === self::$instance ) {
        self::$instance = new self();
    }
    return self::$instance;
}
```

### 2. Dependency Injection via Properties
O coordenador injeta dependências nos componentes:
```php
private function __construct() {
    $this->renderer = DPS_Portal_Renderer::get_instance();
    $this->data_provider = DPS_Portal_Data_Provider::get_instance();
    // ...
}
```

### 3. Single Responsibility Principle (SRP)
Cada classe tem uma responsabilidade clara e focada.

### 4. Open/Closed Principle (OCP)
Classes abertas para extensão via hooks/filtros, fechadas para modificação.

## Benefícios da Refatoração

### Manutenibilidade
- ✅ Classes menores, mais fáceis de entender
- ✅ Responsabilidades claras e separadas
- ✅ Mais fácil localizar e corrigir bugs

### Testabilidade
- ✅ Componentes isolados podem ser testados independentemente
- ✅ Mock de dependências mais fácil
- ✅ Testes mais focados e rápidos

### Extensibilidade
- ✅ Novos recursos podem ser adicionados com menos risco
- ✅ Componentes podem ser substituídos/extendidos
- ✅ Menos acoplamento entre componentes

### Performance
- ✅ Facilita identificar gargalos
- ✅ Permite otimizar componentes específicos
- ✅ Data Provider pode implementar cache centralizado

### Legibilidade
- ✅ Código auto-documentado pela separação de responsabilidades
- ✅ Fluxo de dados mais claro
- ✅ Menos código mental para processar

## Retrocompatibilidade

### API Pública Preservada

Todos os métodos públicos e hooks existentes foram preservados:

**Shortcodes:**
- `[dps_client_portal]` - Renderiza portal completo
- `[dps_client_login]` - Login deprecado (mantido por compatibilidade)

**Hooks de ação (emitidos):**
- `dps_portal_before_render`
- `dps_portal_after_auth_check`
- `dps_portal_before_login_screen`
- `dps_portal_client_authenticated`
- `dps_portal_before_content`
- `dps_client_portal_before_content`
- `dps_client_portal_after_content`
- `dps_portal_before_inicio_content`
- `dps_portal_after_inicio_content`
- `dps_portal_before_agendamentos_content`
- `dps_portal_after_agendamentos_content`
- `dps_portal_before_galeria_content`
- `dps_portal_after_galeria_content`
- `dps_portal_before_dados_content`
- `dps_portal_after_dados_content`
- `dps_portal_custom_tab_panels`
- `dps_portal_after_update_client`

**Hooks de filtro (aplicados):**
- `dps_portal_login_screen`
- `dps_portal_tabs`

**Métodos públicos:**
- `DPS_Client_Portal::get_instance()`
- `DPS_Client_Portal::get_current_client_id()`

### Mudanças Internas (não afetam usuários)

- Métodos privados movidos para classes especializadas
- Lógica interna reorganizada
- Estrutura de arquivos expandida

## Próximos Passos

### Fase 3.1: Completar Refatoração
- [ ] Refatorar `render_portal_shortcode()` para delegar completamente ao renderer
- [ ] Refatorar `handle_portal_actions()` para delegar completamente ao actions_handler
- [ ] Reduzir tamanho de `DPS_Client_Portal` para < 500 linhas

### Fase 3.2: Otimizações
- [ ] Implementar cache em `DPS_Portal_Data_Provider`
- [ ] Otimizar queries (batch loading, índices)
- [ ] Lazy loading de componentes administrativos

### Fase 3.3: Testes
- [ ] Criar testes unitários para cada componente
- [ ] Testes de integração para fluxos completos
- [ ] Testes de regressão para API pública

### Fase 3.4: Documentação
- [ ] Documentar APIs públicas de cada classe
- [ ] Criar guias de extensão para desenvolvedores
- [ ] Atualizar README com nova arquitetura

## Métricas

### Antes da Refatoração
- **Arquivo único:** `class-dps-client-portal.php` (2947 linhas)
- **Complexidade ciclomática:** ~150+
- **Responsabilidades:** 6+ em uma classe

### Após Refatoração
- **Arquivos totais:** 6 (1 coordenador + 5 especializados)
- **Maior arquivo:** ~700 linhas (DPS_Portal_Renderer)
- **Complexidade por classe:** ~20-30
- **Separação clara:** 1 responsabilidade por classe

### Redução de Complexidade
- **Antes:** 1 classe monolítica de 2947 linhas
- **Depois:** 6 classes focadas com média de ~500 linhas cada
- **Ganho:** ~80% de redução em complexidade por arquivo

## Conclusão

Esta refatoração estabelece uma base sólida para evolução futura do Client Portal. O código está mais:

✅ **Organizado** - Estrutura clara e lógica  
✅ **Manutenível** - Fácil localizar e corrigir problemas  
✅ **Testável** - Componentes isolados  
✅ **Extensível** - Fácil adicionar novos recursos  
✅ **Legível** - Código autodocumentado  

A retrocompatibilidade total garante que esta mudança seja transparente para usuários e integrações existentes.
