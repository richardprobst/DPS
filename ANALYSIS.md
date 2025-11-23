# Análise funcional do Desi Pet Shower

## Plugin base (`plugin/desi-pet-shower-base_plugin`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rótulos e argumentos padrão; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opções comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- **NOTA**: Os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) atualmente possuem `show_ui => false`, operando apenas via shortcode `[dps_base]`. Para análise completa sobre habilitação da interface admin nativa do WordPress para estes CPTs, consulte `docs/admin/ADMIN_CPT_INTERFACE_ANALYSIS.md` e `docs/admin/ADMIN_CPT_INTERFACE_SUMMARY.md`.
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, `dps_settings_nav_tabs`, etc.).
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis.
- A exclusão de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoção de lançamentos vinculados sem depender de SQL no núcleo.
- A criação de tabelas do núcleo (ex.: `dps_logs`) é registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versão não exista ou esteja desatualizada, `dbDelta` é chamado uma única vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificação em todos os ciclos de `init`.

### Helpers globais do núcleo

O plugin base oferece classes utilitárias para padronizar operações comuns e evitar duplicação de lógica. Estes helpers estão disponíveis em `plugin/desi-pet-shower-base_plugin/includes/` e podem ser usados tanto pelo núcleo quanto pelos add-ons.

#### DPS_Money_Helper
**Propósito**: Manipulação consistente de valores monetários com conversão entre formato brasileiro e centavos.

**Entrada/Saída**:
- `parse_brazilian_format( string )`: Converte string BR (ex.: "1.234,56") → int centavos (123456)
- `format_to_brazilian( int )`: Converte centavos (123456) → string BR ("1.234,56")
- `decimal_to_cents( float )`: Converte decimal (12.34) → int centavos (1234)
- `cents_to_decimal( int )`: Converte centavos (1234) → float decimal (12.34)

**Exemplos práticos**:
```php
// Validar e converter valor do formulário para centavos
$preco_raw = isset( $_POST['preco'] ) ? sanitize_text_field( $_POST['preco'] ) : '';
$valor_centavos = DPS_Money_Helper::parse_brazilian_format( $preco_raw );

// Exibir valor formatado na tela
echo 'R$ ' . DPS_Money_Helper::format_to_brazilian( $valor_centavos );
```

**Boas práticas**: Use sempre este helper para conversões monetárias. Evite lógica duplicada de `str_replace` e `number_format` espalhada pelo código.

#### DPS_URL_Builder
**Propósito**: Construção padronizada de URLs de ação (edição, exclusão, visualização, navegação entre abas).

**Entrada/Saída**:
- `build_edit_url( int $post_id, string $tab )`: Gera URL de edição com nonce
- `build_delete_url( int $post_id, string $action, string $tab )`: Gera URL de exclusão com nonce
- `build_view_url( int $post_id, string $tab )`: Gera URL de visualização
- `build_tab_url( string $tab_name )`: Gera URL de navegação entre abas

**Exemplos práticos**:
```php
// Gerar link de edição de cliente
$url_editar = DPS_URL_Builder::build_edit_url( $client_id, 'clientes' );

// Gerar link de exclusão de agendamento com confirmação
$url_excluir = DPS_URL_Builder::build_delete_url( $appointment_id, 'delete_appointment', 'historico' );
```

**Boas práticas**: Centralize geração de URLs neste helper para garantir nonces consistentes e evitar links quebrados.

#### DPS_Query_Helper
**Propósito**: Consultas WP_Query reutilizáveis com filtros comuns, paginação e otimizações de performance.

**Entrada/Saída**:
- `get_all_posts_by_type( string $post_type, array $args )`: Retorna posts com argumentos otimizados
- `get_paginated_posts( string $post_type, int $per_page, int $paged, array $args )`: Retorna posts paginados
- `count_posts_by_status( string $post_type, string $status )`: Conta posts por status

**Exemplos práticos**:
```php
// Buscar todos os clientes ativos
$clientes = DPS_Query_Helper::get_all_posts_by_type( 'dps_client', [
    'post_status' => 'publish',
    'orderby' => 'title',
    'order' => 'ASC'
] );

// Buscar agendamentos paginados
$agendamentos = DPS_Query_Helper::get_paginated_posts( 'dps_appointment', 20, $paged );
```

**Boas práticas**: Use `fields => 'ids'` quando precisar apenas de IDs, e pré-carregue metadados com `update_meta_cache()` quando precisar de metas.

#### DPS_Request_Validator
**Propósito**: Validação centralizada de nonces, capabilities e sanitização de campos de formulário.

**Entrada/Saída**:
- `verify_nonce_and_capability( string $nonce_field, string $capability )`: Valida nonce e permissão
- `sanitize_text_field_from_post( string $field_name, string $default )`: Sanitiza campo de texto
- `sanitize_email_from_post( string $field_name )`: Sanitiza e valida email
- `sanitize_int_from_post( string $field_name, int $default )`: Sanitiza inteiro

**Exemplos práticos**:
```php
// Validar requisição antes de processar formulário
if ( ! DPS_Request_Validator::verify_nonce_and_capability( 'dps_nonce', 'edit_posts' ) ) {
    wp_die( 'Acesso negado.' );
}

// Sanitizar campos do formulário
$nome = DPS_Request_Validator::sanitize_text_field_from_post( 'client_name', '' );
$email = DPS_Request_Validator::sanitize_email_from_post( 'client_email' );
```

**Boas práticas**: NUNCA implemente validação de nonce ou sanitização manual fora deste helper. Evite duplicar lógica de segurança.

#### DPS_Phone_Helper
**Propósito**: Formatação e validação padronizada de números de telefone para comunicações (WhatsApp, exibição).

**Entrada/Saída**:
- `format_for_whatsapp( string $phone )`: Formata telefone para WhatsApp (adiciona código do país 55 se necessário) → string apenas dígitos
- `format_for_display( string $phone )`: Formata telefone para exibição brasileira → string formatada "(11) 98765-4321"
- `is_valid_brazilian_phone( string $phone )`: Valida se telefone brasileiro é válido → bool

**Exemplos práticos**:
```php
// Formatar para envio via WhatsApp
$phone_raw = '(11) 98765-4321';
$whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $phone_raw );
// Retorna: '5511987654321'

// Formatar para exibição na tela
$phone_stored = '5511987654321';
$phone_display = DPS_Phone_Helper::format_for_display( $phone_stored );
// Retorna: '(11) 98765-4321'

// Validar telefone antes de salvar
if ( ! DPS_Phone_Helper::is_valid_brazilian_phone( $phone_input ) ) {
    DPS_Message_Helper::add_error( 'Telefone inválido' );
}
```

**Boas práticas**: 
- Use sempre este helper para formatação de telefones
- Evite duplicação de lógica `preg_replace` espalhada entre add-ons
- Integrado com `DPS_Communications_API` para envio automático via WhatsApp
- **IMPORTANTE**: Todas as funções duplicadas `format_whatsapp_number()` foram removidas do plugin base e add-ons. Use SEMPRE `DPS_Phone_Helper::format_for_whatsapp()` diretamente

#### DPS_Message_Helper
**Propósito**: Gerenciamento de mensagens de feedback visual (sucesso, erro, aviso) para operações administrativas.

**Entrada/Saída**:
- `add_success( string $message )`: Adiciona mensagem de sucesso
- `add_error( string $message )`: Adiciona mensagem de erro
- `add_warning( string $message )`: Adiciona mensagem de aviso
- `display_messages()`: Retorna HTML com todas as mensagens pendentes e as remove automaticamente

**Exemplos práticos**:
```php
// Após salvar cliente com sucesso
DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
wp_safe_redirect( $redirect_url );
exit;

// No início da seção, exibir mensagens pendentes
echo '<div class="dps-section">';
echo DPS_Message_Helper::display_messages(); // Renderiza alertas
echo '<h2>Cadastro de Clientes</h2>';
```

**Boas práticas**: 
- Use mensagens após operações que modificam dados (salvar, excluir, atualizar status)
- Coloque `display_messages()` no início de cada seção do painel para feedback imediato
- Mensagens são armazenadas via transients específicos por usuário, garantindo isolamento
- Mensagens são exibidas apenas uma vez (single-use) e removidas automaticamente após renderização

### Feedback visual e organização de interface
- Todos os formulários principais (clientes, pets, agendamentos) utilizam `DPS_Message_Helper` para feedback após salvar ou excluir
- Formulários são organizados em fieldsets semânticos com bordas sutis (`1px solid #e5e7eb`) e legends descritivos
- Hierarquia de títulos padronizada: H1 único no topo ("Painel de Gestão DPS"), H2 para seções principais, H3 para subseções
- Design minimalista com paleta reduzida: base neutra (#f9fafb, #e5e7eb, #374151) + 3 cores de status essenciais (verde, amarelo, vermelho)
- Responsividade básica implementada com media queries para mobile (480px), tablets (768px) e desktops pequenos (1024px)


### Histórico e exportação de agendamentos
- A coleta de atendimentos finalizados é feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurável via filtro `dps_history_batch_size` (padrão: 200). Isso evita uma única consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos são pré-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas às mesmas linhas durante a renderização e exportação.
- Clientes, pets e serviços relacionados são resolvidos com caches em memória por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em várias linhas.
- O botão de exportação gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e já paginado/filtrado pelo usuário.

## Add-ons complementares (`add-ons/`)

### Text Domains para Internacionalização (i18n)

Todos os plugins e add-ons do DPS seguem o padrão WordPress de text domains para internacionalização. Os text domains oficiais são:

**Plugin Base**:
- `desi-pet-shower` - Plugin base que fornece CPTs e funcionalidades core

**Add-ons**:
- `dps-agenda-addon` - Agenda e agendamentos
- `dps-ai` - Assistente de IA
- `dps-backup-addon` - Backup e restauração
- `dps-client-portal` - Portal do cliente
- `dps-communications-addon` - Comunicações (WhatsApp, SMS, email)
- `dps-finance-addon` - Financeiro (transações, parcelas, cobranças)
- `dps-groomers-addon` - Gestão de groomers/profissionais
- `dps-loyalty-addon` - Campanhas e fidelidade
- `dps-payment-addon` - Integração de pagamentos
- `dps-push-addon` - Notificações push
- `dps-registration-addon` - Registro e autenticação
- `dps-services-addon` - Serviços e produtos
- `dps-stats-addon` - Estatísticas e relatórios
- `dps-stock-addon` - Controle de estoque
- `dps-subscription-addon` - Assinaturas e recorrência

**Boas práticas de i18n**:
- Use sempre `__()`, `_e()`, `esc_html__()`, `esc_attr__()` ou `esc_html_e()` para strings exibidas ao usuário
- Sempre especifique o text domain correto do plugin/add-on correspondente
- Para strings JavaScript em `prompt()` ou `alert()`, use `esc_js( __() )` para escapar e traduzir
- Mensagens de erro, sucesso, labels de formulário e textos de interface devem sempre ser traduzíveis
- Dados de negócio (nomes de clientes, endereços hardcoded, etc.) não precisam de tradução

---

### Agenda (`desi-pet-shower-agenda_addon`)

**Diretório**: `add-ons/desi-pet-shower-agenda_addon`

**Propósito e funcionalidades principais**:
- Gerenciar agenda de atendimentos e cobranças pendentes
- Enviar lembretes automáticos diários aos clientes
- Atualizar status de agendamentos via interface AJAX
- **[Deprecated v1.1.0]** Endpoint `dps_get_services_details` (movido para Services Add-on)

**Shortcodes expostos**:
- `[dps_agenda_page]`: renderiza página de agenda com filtros e ações
- `[dps_charges_notes]`: **[Deprecated]** redirecionado para Finance Add-on (`[dps_fin_docs]`)

**CPTs, tabelas e opções**:
- Não cria CPTs próprios; consome `dps_agendamento` do núcleo
- Cria páginas automaticamente: "Agenda DPS"
- Options: `dps_agenda_page_id`

**Hooks consumidos**:
- Nenhum hook específico do núcleo (opera diretamente sobre CPTs)

**Hooks disparados**:
- `dps_agenda_send_reminders`: cron job diário para envio de lembretes

**Endpoints AJAX**:
- `dps_update_status`: atualiza status de agendamento
- `dps_get_services_details`: **[Deprecated v1.1.0]** mantido por compatibilidade, delega para `DPS_Services_API::get_services_details()`

**Dependências**:
- Depende do plugin base para CPTs de agendamento
- **[Recomendado]** Services Add-on para cálculo de valores via API
- Integra-se com add-on de Comunicações para envio de mensagens (se ativo)
- Aviso exibido se Finance Add-on não estiver ativo (funcionalidades financeiras limitadas)

**Introduzido em**: v0.1.0 (estimado)

**Assets**:
- `assets/js/agenda-addon.js`: interações AJAX e feedback visual
- **[Deprecated]** `agenda-addon.js` e `agenda.js` na raiz (devem ser removidos)

**Observações**:
- Implementa `register_deactivation_hook` para limpar cron job `dps_agenda_send_reminders` ao desativar
- **[v1.1.0]** Lógica de serviços movida para Services Add-on; Agenda delega cálculos para `DPS_Services_API`

---

### Backup & Restauração (`desi-pet-shower-backup_addon`)

**Diretório**: `add-ons/desi-pet-shower-backup_addon`

**Propósito e funcionalidades principais**:
- Exportar todo o conteúdo do sistema em formato JSON
- Restaurar dados de backups anteriores
- Proteger operações com nonces e validações

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Integra-se à navegação de configurações do plugin base

**Hooks consumidos**:
- `dps_settings_nav_tabs`: adiciona aba "Backup & Restauração"
- `dps_settings_sections`: renderiza conteúdo da seção

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de configurações
- Acessa todos os CPTs do sistema para exportação/importação

**Introduzido em**: v0.1.0 (estimado)

---

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)

**Diretório**: `add-ons/desi-pet-shower-loyalty_addon`

**Propósito e funcionalidades principais**:
- Gerenciar programa de pontos por faturamento
- Módulo "Indique e Ganhe" com códigos únicos e recompensas
- Criar e executar campanhas de marketing direcionadas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_campaign` (campanhas de marketing)
- Tabela: `dps_referrals` (indicações de clientes)
- Options: configurações de pontos, recompensas e elegibilidade

**Hooks consumidos**:
- `dps_registration_after_client_created`: registra indicações no cadastro público
- `dps_finance_booking_paid`: bonifica indicador/indicado na primeira cobrança paga
- `dps_base_nav_tabs_after_history`: adiciona aba "Campanhas & Fidelidade"
- `dps_base_sections_after_history`: renderiza conteúdo da aba

**Hooks disparados**: Nenhum

**Dependências**:
- Integra-se com add-on Financeiro para bonificações
- Integra-se com add-on de Cadastro Público para capturar códigos de indicação
- Integra-se com Portal do Cliente para exibir código/link de convite

**Introduzido em**: v0.2.0

**Observações**:
- Tabela `dps_referrals` criada via `dbDelta` na ativação
- Oferece funções globais para crédito e resgate de pontos

---

### Comunicações (`desi-pet-shower-communications_addon`)

**Diretório**: `add-ons/desi-pet-shower-communications_addon`

**Propósito e funcionalidades principais**:
- **Centralizar TODAS as comunicações do sistema** via API pública `DPS_Communications_API`
- Enviar mensagens via WhatsApp, e-mail e SMS (futuro)
- Aplicar templates configuráveis com placeholders dinâmicos
- Registrar logs automáticos de todas as comunicações via `DPS_Logger`
- Fornecer hooks para extensibilidade por outros add-ons

**Arquitetura - Camada Centralizada**:
- **API Central**: `DPS_Communications_API` (singleton) expõe métodos públicos
- **Gatilhos**: Agenda, Portal e outros add-ons **delegam** envios para a API
- **Interfaces mantidas**: Botões de ação (wa.me links) **permanecem** na Agenda e Portal
- **Lógica de envio**: Concentrada na API, não duplicada entre add-ons

**API Pública** (`includes/class-dps-communications-api.php`):
```php
$api = DPS_Communications_API::get_instance();

// Métodos principais:
$api->send_whatsapp( $to, $message, $context = [] );
$api->send_email( $to, $subject, $body, $context = [] );
$api->send_appointment_reminder( $appointment_id );
$api->send_payment_notification( $client_id, $amount_cents, $context = [] );
$api->send_message_from_client( $client_id, $message, $context = [] );
```

**Shortcodes expostos**: Nenhum (operação via API e configurações)

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Option `dps_comm_settings`: configurações de gateways e templates
  - `whatsapp_api_key`: chave de API do gateway WhatsApp
  - `whatsapp_api_url`: endpoint base do gateway
  - `default_email_from`: e-mail remetente padrão
  - `template_confirmation`: template de confirmação de agendamento
  - `template_reminder`: template de lembrete (placeholders: `{client_name}`, `{pet_name}`, `{date}`, `{time}`)
  - `template_post_service`: template de pós-atendimento

**Hooks consumidos**:
- `dps_base_after_save_appointment`: dispara confirmação após salvar agendamento
- `dps_settings_nav_tabs`: adiciona aba "Comunicações"
- `dps_settings_sections`: renderiza configurações de canais e templates

**Hooks disparados (Actions)**:
- `dps_after_whatsapp_sent( $to, $message, $context, $result )`: após envio de WhatsApp
- `dps_after_email_sent( $to, $subject, $body, $context, $result )`: após envio de e-mail
- `dps_after_reminder_sent( $appointment_id, $sent )`: após envio de lembrete
- `dps_comm_send_appointment_reminder`: cron job para lembretes de agendamento
- `dps_comm_send_post_service`: cron job para mensagens pós-atendimento

**Hooks disparados (Filters)**:
- `dps_comm_whatsapp_message( $message, $to, $context )`: filtra mensagem WhatsApp antes de enviar
- `dps_comm_email_subject( $subject, $to, $context )`: filtra assunto de e-mail
- `dps_comm_email_body( $body, $to, $context )`: filtra corpo de e-mail
- `dps_comm_email_headers( $headers, $to, $context )`: filtra headers de e-mail
- `dps_comm_reminder_message( $message, $appointment_id )`: filtra mensagem de lembrete
- `dps_comm_payment_notification_message( $message, $client_id, $amount_cents, $context )`: filtra notificação de pagamento

**Dependências**:
- Depende do plugin base para `DPS_Logger` e `DPS_Phone_Helper`
- Agenda e Portal delegam comunicações para esta API (dependência soft)

**Integração com outros add-ons**:
- **Agenda**: delega lembretes e notificações de status, **mantém** botões wa.me
- **Portal**: delega mensagens de clientes para admin
- **Finance**: pode usar API para notificar pagamentos

**Introduzido em**: v0.1.0  
**Refatorado em**: v0.2.0 (API centralizada)

**Documentação completa**: `add-ons/desi-pet-shower-communications_addon/README.md`

---

### Groomers (`desi-pet-shower-groomers_addon`)

**Diretório**: `add-ons/desi-pet-shower-groomers_addon`

**Propósito e funcionalidades principais**:
- Cadastrar e gerenciar profissionais (groomers)
- Vincular atendimentos a profissionais específicos
- Gerar relatórios de produtividade por profissional

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Role customizada: `dps_groomer` (profissional de banho e tosa)
- Metadados: `_groomer_id` nos posts de agendamento

**Hooks consumidos**:
- `dps_base_appointment_fields`: adiciona campo de seleção de groomer no formulário de agendamento
- `dps_base_nav_tabs_after_history`: adiciona aba "Groomers"
- `dps_base_sections_after_history`: renderiza cadastro e relatórios

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação e agendamentos

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 473 linhas; candidato a refatoração futura seguindo padrão de estrutura modular

---

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)

**Diretório**: `add-ons/desi-pet-shower-client-portal_addon`

**Propósito e funcionalidades principais**:
- Fornecer área autenticada para clientes
- Permitir atualização de dados pessoais e de pets
- Exibir histórico de atendimentos e pendências financeiras
- Integrar com módulo "Indique e Ganhe" quando ativo

**Shortcodes expostos**:
- `[dps_client_portal]`: renderiza portal completo do cliente
- `[dps_client_login]`: exibe formulário de login

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Usa sessões PHP próprias para autenticação
- Tipos de mensagem customizados para notificações

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: integra abas ao painel base (quando aplicável)
- Hooks do add-on de Pagamentos para links de quitação via Mercado Pago

**Hooks disparados**:
- `dps_client_portal_after_content`: disparado ao final do portal, antes do fechamento do container principal; passa $client_id como parâmetro

**Dependências**:
- Depende do plugin base para CPTs de clientes e pets
- Integra-se com add-on Financeiro para exibir pendências
- Integra-se com add-on de Fidelidade para exibir código de indicação

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Já segue padrão modular com estrutura `includes/` e `assets/`
- Gera credenciais de login automaticamente para novos clientes

**Análise de Layout e UX**:
- Consulte `docs/layout/client-portal/CLIENT_PORTAL_UX_ANALYSIS.md` para análise detalhada de usabilidade (800+ linhas)
- Consulte `docs/layout/client-portal/CLIENT_PORTAL_SUMMARY.md` para resumo executivo das melhorias propostas
- Principais achados: estrutura "all-in-one" sem navegação, responsividade precária em mobile, paleta de cores excessiva (15+ cores vs 8 recomendadas), feedback visual ausente
- Melhorias prioritárias documentadas em 3 fases (26.5h totais): navegação interna, cards destacados, tabelas responsivas, feedback visual, redução de paleta, fieldsets em formulários

---

### Assistente de IA (`desi-pet-shower-ai_addon`)

**Diretório**: `add-ons/desi-pet-shower-ai_addon`

**Propósito e funcionalidades principais**:
- Fornecer assistente virtual inteligente no Portal do Cliente
- Responder perguntas EXCLUSIVAMENTE sobre: Banho e Tosa, serviços, agendamentos, histórico, pagamentos, fidelidade, assinaturas e dados do cliente/pet
- NÃO responder sobre assuntos aleatórios fora do contexto (política, religião, tecnologia, etc.)
- Integrar-se com OpenAI via API Chat Completions (GPT-3.5 Turbo / GPT-4)

**Shortcodes expostos**: Nenhum (integra-se diretamente ao Portal via hook)

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Option: `dps_ai_settings` (armazena configurações: enabled, api_key, model, temperature, timeout, max_tokens)

**Hooks consumidos**:
- `dps_client_portal_after_content`: renderiza widget de chat após conteúdo do portal

**Hooks disparados**: Nenhum

**Endpoints AJAX**:
- `dps_ai_portal_ask` (wp_ajax e wp_ajax_nopriv): processa perguntas do cliente e retorna respostas da IA

**Dependências**:
- **Obrigatório**: Client Portal (fornece autenticação e shortcode `[dps_client_portal]`)
- **Opcional**: Finance, Loyalty, Services (enriquecem contexto disponível para a IA)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/js/dps-ai-portal.js`: gerencia widget de chat e envio de perguntas via AJAX
- `assets/css/dps-ai-portal.css`: estilos minimalistas seguindo paleta visual DPS

**Arquitetura interna**:
- `includes/class-dps-ai-client.php`: cliente da API OpenAI com tratamento de erros e timeouts
- `includes/class-dps-ai-assistant.php`: lógica do assistente (system prompt restritivo, montagem de contexto, filtro de palavras-chave)
- `includes/class-dps-ai-integration-portal.php`: integração com Portal do Cliente (widget, AJAX handlers)

**System Prompt e Regras**:
- Prompt restritivo define domínio permitido (banho/tosa, pet shop, sistema DPS)
- Proíbe explicitamente assuntos fora do contexto
- Instrui a IA a recusar educadamente perguntas inadequadas
- Recomenda procurar veterinário para problemas de saúde graves do pet
- Proíbe inventar descontos, promoções ou alterações de plano não documentadas
- Exige honestidade quando dados não forem encontrados no sistema

**Filtro Preventivo**:
- Antes de chamar API, valida se pergunta contém palavras-chave do contexto (pet, banho, tosa, agendamento, pagamento, etc.)
- Economiza chamadas de API e protege contra perguntas totalmente fora de escopo
- Resposta padrão retornada sem chamar API se pergunta não passar no filtro

**Contexto Fornecido à IA**:
- Dados do cliente (nome, telefone, email)
- Lista de pets cadastrados (nome, raça, porte, idade)
- Últimos 5 agendamentos (data, status, serviços)
- Pendências financeiras (se Finance add-on ativo)
- Pontos de fidelidade (se Loyalty add-on ativo)

**Comportamento em Cenários**:
- **IA ativa e funcionando**: Widget aparece e processa perguntas normalmente
- **IA desabilitada ou sem API key**: Widget não aparece; Portal funciona normalmente
- **Falha na API**: Mensagem amigável exibida; Portal continua funcional

**Segurança**:
- API key NUNCA exposta no JavaScript (chamadas server-side only)
- Nonces em todas as requisições AJAX
- Sanitização de entrada do usuário
- Validação de cliente logado antes de processar pergunta
- Timeout configurável para evitar requisições travadas
- Logs de erro apenas no server (error_log, não expostos ao cliente)

**Interface Administrativa**:
- Menu: **Desi Pet Shower > Assistente de IA**
- Configurações: ativar/desativar IA, API key, modelo GPT, temperatura, timeout, max_tokens
- Documentação inline sobre comportamento do assistente

**Observações**:
- Sistema totalmente autocontido: falhas não afetam funcionamento do Portal
- Custo por requisição varia conforme modelo escolhido (GPT-3.5 Turbo recomendado para custo/benefício)
- Consulte `add-ons/desi-pet-shower-ai_addon/README.md` para documentação completa de uso e manutenção

---

### Financeiro (`desi-pet-shower-finance_addon`)

**Diretório**: `add-ons/desi-pet-shower-finance_addon`

**Propósito e funcionalidades principais**:
- Gerenciar transações financeiras e cobranças
- Sincronizar lançamentos com agendamentos
- Suportar quitação parcial e geração de documentos
- Integrar com outros add-ons para bonificações e assinaturas

**Shortcodes expostos**: Sim (não especificados na documentação atual)

**CPTs, tabelas e opções**:
- Tabela: `dps_transacoes` (lançamentos financeiros)
- Tabela: `dps_parcelas` (parcelas de cobranças)

**Hooks consumidos**:
- `dps_finance_cleanup_for_appointment`: remove lançamentos ao excluir agendamento
- `dps_base_nav_tabs_*`: adiciona aba "Financeiro"
- `dps_base_sections_*`: renderiza seção financeira

**Hooks disparados**:
- `dps_finance_booking_paid`: disparado quando cobrança é marcada como paga

**Dependências**:
- Depende do plugin base para estrutura de navegação
- Fornece infraestrutura para add-ons de Pagamentos, Assinaturas e Fidelidade

**Introduzido em**: v0.1.0

**Observações**:
- Já segue padrão modular com classes auxiliares em `includes/`
- Tabela compartilhada por múltiplos add-ons; mudanças de schema requerem migração cuidadosa

---

### Pagamentos (`desi-pet-shower-payment_addon`)

**Diretório**: `add-ons/desi-pet-shower-payment_addon`

**Propósito e funcionalidades principais**:
- Integrar com Mercado Pago para geração de links de pagamento
- Processar notificações de webhook para atualização de status
- Injetar mensagens de cobrança no WhatsApp via add-on de Agenda

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Options: `dps_mp_access_token`, `dps_pix_key` (credenciais Mercado Pago)

**Hooks consumidos**:
- Processa webhooks cedo no ciclo de inicialização do WordPress
- Integra-se com add-on Financeiro para gerar cobranças

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do add-on Financeiro para criar transações
- Integra-se com add-on de Agenda para envio de links via WhatsApp

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Validação de webhook aplicada apenas quando requisição traz indicadores de notificação do MP
- Requer token de acesso e chave PIX configurados nas opções

---

### Push Notifications (`desi-pet-shower-push_addon`)

**Diretório**: `add-ons/desi-pet-shower-push_addon`

**Propósito e funcionalidades principais**:
- Agendar e enviar notificações recorrentes (agenda diária, relatórios financeiros)
- Integrar com e-mail e Telegram para envio de mensagens
- Filtrar destinatários por critérios configuráveis

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Options: configurações de destinatários e frequência de notificações

**Hooks consumidos**:
- `dps_settings_nav_tabs`: adiciona aba "Notificações"
- `dps_settings_sections`: renderiza configurações

**Hooks disparados**:
- Múltiplos cron jobs: agenda diária, relatório financeiro diário, relatório semanal de pets inativos
- `dps_send_push_notification`: hook customizado para envio via Telegram

**Dependências**:
- Depende do plugin base para estrutura de configurações

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Implementa `register_deactivation_hook` corretamente para limpar cron jobs

---

### Cadastro Público (`desi-pet-shower-registration_addon`)

**Diretório**: `add-ons/desi-pet-shower-registration_addon`

**Propósito e funcionalidades principais**:
- Permitir cadastro público de clientes e pets via formulário web
- Integrar com Google Maps para autocomplete de endereços
- Disparar hook para outros add-ons após criação de cliente

**Shortcodes expostos**:
- `[dps_registration_form]`: renderiza formulário de cadastro público

**CPTs, tabelas e opções**:
- Não cria CPTs próprios; cria posts do tipo `dps_client` e `dps_pet`
- Options: `dps_google_maps_api_key` (chave de API do Google Maps)

**Hooks consumidos**: Nenhum

**Hooks disparados**:
- `dps_registration_after_client_created`: disparado após criar novo cliente

**Dependências**:
- Depende do plugin base para CPTs de cliente e pet
- Integra-se com add-on de Fidelidade para capturar códigos de indicação

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Sanitiza todas as entradas antes de criar posts
- Arquivo único de 636 linhas; candidato a refatoração futura

---

### Serviços (`desi-pet-shower-services_addon`)

**Diretório**: `add-ons/desi-pet-shower-services_addon`

**Propósito e funcionalidades principais**:
- Gerenciar catálogo de serviços oferecidos
- Definir preços e duração por porte de pet
- Vincular serviços aos agendamentos
- Povoar catálogo padrão na ativação
- **[v1.2.0]** Centralizar toda lógica de cálculo de preços via API pública

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_service` (registrado via `DPS_CPT_Helper`)
- Metadados: preços e duração por porte (pequeno, médio, grande)

**Hooks consumidos**:
- `dps_base_nav_tabs_*`: adiciona aba "Serviços"
- `dps_base_sections_*`: renderiza catálogo e formulários
- Hook de agendamento: adiciona campos de seleção de serviços

**Hooks disparados**: Nenhum

**Endpoints AJAX expostos**:
- `dps_get_services_details`: retorna detalhes de serviços de um agendamento (movido da Agenda em v1.2.0)

**API Pública** (desde v1.2.0):
A classe `DPS_Services_API` centraliza toda a lógica de serviços e cálculo de preços:

```php
// Obter dados completos de um serviço
$service = DPS_Services_API::get_service( $service_id );
// Retorna: ['id', 'title', 'type', 'category', 'active', 'price', 'price_small', 'price_medium', 'price_large']

// Calcular preço de um serviço por porte
$price = DPS_Services_API::calculate_price( $service_id, 'medio' );
// Aceita: 'pequeno'/'small', 'medio'/'medium', 'grande'/'large'

// Calcular total de um agendamento
$total = DPS_Services_API::calculate_appointment_total( 
    $service_ids,  // array de IDs de serviços
    $pet_ids,      // array de IDs de pets
    [              // contexto opcional
        'custom_prices' => [ service_id => price ],  // preços personalizados
        'extras' => 50.00,     // valor de extras
        'taxidog' => 25.00,    // valor de taxidog
    ]
);
// Retorna: ['total', 'services_total', 'services_details', 'extras_total', 'taxidog_total']

// Obter detalhes de serviços de um agendamento
$details = DPS_Services_API::get_services_details( $appointment_id );
// Retorna: ['services' => [['name', 'price'], ...], 'total']
```

**Contrato de integração**:
- Outros add-ons DEVEM usar `DPS_Services_API` para cálculos de preços
- Agenda Add-on delega `dps_get_services_details` para esta API (desde v1.1.0)
- Finance Add-on DEVE usar esta API para obter valores históricos
- Portal do Cliente DEVE usar esta API para exibir valores

**Dependências**:
- Depende do plugin base para estrutura de navegação
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0  
**API pública**: v1.2.0

---

### Estatísticas (`desi-pet-shower-stats_addon`)

**Diretório**: `add-ons/desi-pet-shower-stats_addon`

**Propósito e funcionalidades principais**:
- Exibir métricas de uso do sistema (atendimentos, receita, clientes inativos)
- Listar serviços mais recorrentes
- Filtrar estatísticas por período
- Analisar distribuição de espécies e raças

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Consulta `dps_transacoes` para métricas financeiras
- Consulta CPTs do núcleo para métricas operacionais

**Hooks consumidos**:
- `dps_base_nav_tabs_after_history`: adiciona aba "Estatísticas"
- `dps_base_sections_after_history`: renderiza gráficos e listas

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para CPTs
- Depende do add-on Financeiro para métricas de receita

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 538 linhas; candidato a refatoração futura

---

### Estoque (`desi-pet-shower-stock_addon`)

**Diretório**: `add-ons/desi-pet-shower-stock_addon`

**Propósito e funcionalidades principais**:
- Controlar estoque de insumos utilizados nos atendimentos
- Registrar movimentações de entrada e saída
- Gerar alertas de estoque baixo
- Baixar estoque automaticamente ao concluir atendimentos

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_stock_item` (registrado via `DPS_CPT_Helper`)
- Capability customizada: `dps_manage_stock`
- Metadados: quantidade atual, mínima, histórico de movimentações

**Hooks consumidos**:
- `dps_base_after_save_appointment`: baixa estoque automaticamente ao concluir atendimento
- `dps_base_nav_tabs_after_history`: adiciona aba "Estoque"
- `dps_base_sections_after_history`: renderiza controle de estoque

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação e hooks de agendamento
- Reutiliza `DPS_CPT_Helper` para registro de CPT

**Introduzido em**: v0.1.0 (estimado)

**Observações**:
- Arquivo único de 432 linhas; candidato a refatoração futura
- Passou a usar navegação integrada ao painel base, removendo menus próprios

---

### Assinaturas (`desi-pet-shower-subscription_addon`)

**Diretório**: `add-ons/desi-pet-shower-subscription_addon`

**Propósito e funcionalidades principais**:
- Gerenciar assinaturas recorrentes de clientes
- Criar e atualizar transações relacionadas a assinaturas
- Gerar links de renovação via Mercado Pago
- Enviar mensagens padrão via WhatsApp para renovações

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- CPT: `dps_subscription` (planos de assinatura)
- Integra-se com tabela `dps_transacoes` do add-on Financeiro

**Hooks consumidos**:
- Hooks do add-on Financeiro para criar transações recorrentes
- Hooks do add-on de Pagamentos para gerar links de renovação

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do add-on Financeiro para gerenciar cobranças
- Depende do add-on de Pagamentos para integração com Mercado Pago

**Introduzido em**: v0.2.0 (estimado)

**Observações**:
- Define UI própria ao painel base
- Integração estreita com fluxo financeiro e de pagamentos

---

## Mapa de hooks

Esta seção consolida os principais hooks expostos pelo núcleo e pelos add-ons, facilitando a integração entre componentes.

### Hooks do plugin base (núcleo)

#### Navegação e seções do painel

- **`dps_base_nav_tabs_after_pets`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação após a aba "Pets"
  - **Consumido por**: add-ons que precisam injetar abas customizadas no painel principal

- **`dps_base_nav_tabs_after_history`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação após a aba "Histórico"
  - **Consumido por**: Groomers, Estatísticas, Estoque (abas gerenciais)

- **`dps_base_sections_after_pets`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções customizadas após a seção "Pets"
  - **Consumido por**: add-ons que adicionaram abas via `dps_base_nav_tabs_after_pets`

- **`dps_base_sections_after_history`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções customizadas após a seção "Histórico"
  - **Consumido por**: Groomers, Estatísticas, Estoque, Campanhas & Fidelidade

- **`dps_settings_nav_tabs`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: adicionar abas de navegação na página de configurações
  - **Consumido por**: Backup, Comunicações, Push Notifications

- **`dps_settings_sections`** (action)
  - **Parâmetros**: nenhum
  - **Propósito**: renderizar conteúdo de seções na página de configurações
  - **Consumido por**: add-ons que adicionaram abas via `dps_settings_nav_tabs`

#### Fluxo de agendamentos

- **`dps_base_appointment_fields`** (action)
  - **Parâmetros**: `$appointment_id` (int, pode ser 0 para novos agendamentos)
  - **Propósito**: adicionar campos customizados ao formulário de agendamento
  - **Consumido por**: Groomers (seleção de profissional), Serviços (seleção de serviços)

- **`dps_base_after_save_appointment`** (action)
  - **Parâmetros**: `$appointment_id` (int)
  - **Propósito**: executar ações após salvar um agendamento
  - **Consumido por**: Comunicações (envio de notificações), Estoque (baixa automática)

#### Limpeza de dados

- **`dps_finance_cleanup_for_appointment`** (action)
  - **Parâmetros**: `$appointment_id` (int)
  - **Propósito**: remover dados financeiros associados antes de excluir agendamento
  - **Consumido por**: Financeiro (remove transações vinculadas)

### Hooks de add-ons

#### Add-on Financeiro

- **`dps_finance_booking_paid`** (action)
  - **Parâmetros**: `$transaction_id` (int), `$client_id` (int)
  - **Propósito**: disparado quando uma cobrança é marcada como paga
  - **Consumido por**: Campanhas & Fidelidade (bonifica indicador/indicado na primeira cobrança)

#### Add-on de Cadastro Público

- **`dps_registration_after_client_created`** (action)
  - **Parâmetros**: `$client_id` (int), `$referral_code` (string|null)
  - **Propósito**: disparado após criar novo cliente via formulário público
  - **Consumido por**: Campanhas & Fidelidade (registra indicações)

#### Cron jobs de add-ons

- **`dps_agenda_send_reminders`** (action)
  - **Frequência**: diária
  - **Propósito**: enviar lembretes de agendamentos próximos
  - **Registrado por**: Agenda

- **`dps_comm_send_appointment_reminder`** (action)
  - **Frequência**: conforme agendado
  - **Propósito**: enviar lembretes de agendamento via canais configurados
  - **Registrado por**: Comunicações

- **`dps_comm_send_post_service`** (action)
  - **Frequência**: conforme agendado
  - **Propósito**: enviar mensagens pós-atendimento
  - **Registrado por**: Comunicações

- **`dps_send_push_notification`** (action)
  - **Parâmetros**: `$message` (string), `$recipients` (array)
  - **Propósito**: enviar notificações via Telegram ou e-mail
  - **Registrado por**: Push Notifications

---

## Considerações de estrutura e integração
- Todos os add-ons se conectam por meio dos *hooks* expostos pelo plugin base (`dps_base_nav_tabs_after_pets`, `dps_base_sections_after_history`, `dps_settings_*`), preservando a renderização centralizada de navegação/abas feita por `DPS_Base_Frontend`.
- As integrações financeiras compartilham a tabela `dps_transacoes`, seja para sincronizar agendamentos (base + financeiro), gerar cobranças (pagamentos, assinaturas) ou exibir pendências no portal e na agenda, reforçando a necessidade de manter o esquema consistente ao evoluir o sistema.

## Padrões de desenvolvimento de add-ons

### Estrutura de arquivos recomendada
Para novos add-ons ou refatorações futuras, recomenda-se seguir a estrutura modular:

```
add-ons/desi-pet-shower-NOME_addon/
├── desi-pet-shower-NOME-addon.php    # Arquivo principal (apenas bootstrapping)
├── includes/                          # Classes e lógica do negócio
│   ├── class-dps-NOME-cpt.php        # Registro de Custom Post Types
│   ├── class-dps-NOME-metaboxes.php  # Metaboxes e campos customizados
│   ├── class-dps-NOME-admin.php      # Interface administrativa
│   └── class-dps-NOME-frontend.php   # Lógica do frontend
├── assets/                            # Recursos estáticos
│   ├── css/                          # Estilos CSS
│   │   └── NOME-addon.css
│   └── js/                           # Scripts JavaScript
│       └── NOME-addon.js
└── uninstall.php                      # Limpeza de dados na desinstalação
```

**Benefícios desta estrutura:**
- **Separação de responsabilidades**: cada classe tem um propósito claro
- **Manutenibilidade**: mais fácil localizar e modificar funcionalidades específicas
- **Reutilização**: classes podem ser testadas e reutilizadas independentemente
- **Performance**: possibilita carregamento condicional de componentes

**Add-ons que já seguem este padrão:**
- `client-portal_addon`: estrutura bem organizada com `includes/` e `assets/`
- `finance_addon`: possui `includes/` para classes auxiliares

**Add-ons que poderiam se beneficiar de refatoração futura:**
- `groomers_addon`: 473 linhas em um único arquivo
- `stats_addon`: 538 linhas em um único arquivo
- `stock_addon`: 432 linhas em um único arquivo
- `loyalty_addon`: 1148 linhas em um único arquivo
- `registration_addon`: 636 linhas em um único arquivo
- `backup_addon`: 1131 linhas em um único arquivo

### Activation e Deactivation Hooks

**Activation Hook (`register_activation_hook`):**
- Criar páginas necessárias
- Criar tabelas de banco de dados via `dbDelta()`
- Definir opções padrão do plugin
- Criar roles e capabilities customizadas
- **NÃO** agendar cron jobs (use `init` com verificação `wp_next_scheduled`)

**Deactivation Hook (`register_deactivation_hook`):**
- Limpar cron jobs agendados com `wp_clear_scheduled_hook()`
- **NÃO** remover dados do usuário (reservado para `uninstall.php`)

**Exemplo de implementação:**
```php
class DPS_Exemplo_Addon {
    public function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
        add_action( 'dps_exemplo_cron_event', [ $this, 'execute_cron' ] );
    }
    
    public function activate() {
        // Criar páginas, tabelas, opções padrão
        $this->create_pages();
        $this->create_database_tables();
    }
    
    public function deactivate() {
        // Limpar APENAS cron jobs temporários
        wp_clear_scheduled_hook( 'dps_exemplo_cron_event' );
    }
    
    public function maybe_schedule_cron() {
        if ( ! wp_next_scheduled( 'dps_exemplo_cron_event' ) ) {
            wp_schedule_event( time(), 'daily', 'dps_exemplo_cron_event' );
        }
    }
}
```

**Add-ons que usam cron jobs:**
- ✅ `push_addon`: implementa deactivation hook corretamente
- ✅ `agenda_addon`: agora implementa deactivation hook para limpar `dps_agenda_send_reminders`

### Padrões de documentação (DocBlocks)

Todos os métodos devem seguir o padrão WordPress de DocBlocks:

```php
/**
 * Breve descrição do método (uma linha).
 *
 * Descrição mais detalhada explicando o propósito, comportamento
 * e contexto de uso do método (opcional).
 *
 * @since 1.0.0
 *
 * @param string $param1 Descrição do primeiro parâmetro.
 * @param int    $param2 Descrição do segundo parâmetro.
 * @param array  $args {
 *     Argumentos opcionais.
 *
 *     @type string $key1 Descrição da chave 1.
 *     @type int    $key2 Descrição da chave 2.
 * }
 * @return bool Retorna true em caso de sucesso, false caso contrário.
 */
public function exemplo_metodo( $param1, $param2, $args = [] ) {
    // Implementação
}
```

**Elementos obrigatórios:**
- Descrição breve do propósito do método
- `@param` para cada parâmetro, com tipo e descrição
- `@return` com tipo e descrição do valor retornado
- `@since` indicando a versão de introdução (opcional, mas recomendado)

**Elementos opcionais mas úteis:**
- Descrição detalhada para métodos complexos
- `@throws` para exceções que podem ser lançadas
- `@see` para referenciar métodos ou classes relacionadas
- `@link` para documentação externa
- `@global` para variáveis globais utilizadas

**Prioridade de documentação:**
1. Métodos públicos (sempre documentar)
2. Métodos protegidos/privados complexos
3. Hooks e filtros expostos
4. Constantes e propriedades de classe

### Boas práticas adicionais

**Prefixação:**
- Todas as funções globais: `dps_`
- Todas as classes: `DPS_`
- Hooks e filtros: `dps_`
- Options: `dps_`
- Handles de scripts/estilos: `dps-`
- Custom Post Types: `dps_`

**Segurança:**
- Sempre usar nonces em formulários: `wp_nonce_field()` / `wp_verify_nonce()`
- Escapar saída: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Sanitizar entrada: `sanitize_text_field()`, `sanitize_email()`, `wp_kses_post()`
- Verificar capabilities: `current_user_can()`

**Performance:**
- Registrar assets apenas onde necessário
- Usar `wp_register_*` seguido de `wp_enqueue_*` condicionalmente
- Otimizar queries com `fields => 'ids'` quando apropriado
- Pré-carregar metadados com `update_meta_cache()`

**Integração com o núcleo:**
- Preferir hooks do plugin base (`dps_base_*`, `dps_settings_*`) a menus próprios
- Reutilizar classes helper quando disponíveis (`DPS_CPT_Helper`, `DPS_Money_Helper`, etc.)
- Seguir contratos de hooks existentes sem modificar assinaturas
- Documentar novos hooks expostos com exemplos de uso

---

## Add-on: AI (Assistente Virtual)

**Diretório**: `add-ons/desi-pet-shower-ai_addon/`

**Versão**: 1.2.0

**Propósito**: Assistente virtual inteligente para o Portal do Cliente e para geração de sugestões de comunicações (WhatsApp e e-mail).

### Funcionalidades Principais

1. **Portal do Cliente**
   - Widget de chat para clientes fazerem perguntas sobre agendamentos, serviços, histórico
   - Respostas contextualizadas baseadas em dados reais do cliente e pets
   - Escopo restrito a assuntos relacionados a Banho e Tosa

2. **Assistente de Comunicações** (v1.2.0+)
   - Gera sugestões de mensagens para WhatsApp
   - Gera sugestões de e-mail (assunto e corpo)
   - **NUNCA envia automaticamente** - apenas sugere textos para revisão humana

### Classes Principais

#### `DPS_AI_Client`

Cliente HTTP para API da OpenAI.

**Métodos:**
- `chat( array $messages, array $options = [] )`: Faz chamada à API Chat Completions
- `test_connection()`: Testa validação da API key

**Configurações:**
- API key armazenada em `dps_ai_settings['api_key']`
- Modelo, temperatura, max_tokens, timeout configuráveis

#### `DPS_AI_Assistant`

Assistente principal para Portal do Cliente.

**Métodos:**
- `answer_portal_question( int $client_id, array $pet_ids, string $user_question )`: Responde pergunta do cliente
- `get_base_system_prompt()`: Retorna prompt base de segurança (público, reutilizável)

**System Prompt:**
- Escopo restrito a Banho e Tosa, serviços, agendamentos, histórico, funcionalidades DPS
- Proíbe assuntos fora do contexto (política, religião, finanças pessoais, etc.)
- Protegido contra contradições de instruções adicionais

#### `DPS_AI_Message_Assistant` (v1.2.0+)

Assistente para geração de sugestões de comunicações.

**Métodos:**

```php
/**
 * Gera sugestão de mensagem para WhatsApp.
 *
 * @param array $context {
 *     Contexto da mensagem.
 *
 *     @type string   $type              Tipo de mensagem (lembrete, confirmacao, pos_atendimento, etc.)
 *     @type string   $client_name       Nome do cliente
 *     @type string   $client_phone      Telefone do cliente
 *     @type string   $pet_name          Nome do pet
 *     @type string   $appointment_date  Data do agendamento (formato legível)
 *     @type string   $appointment_time  Hora do agendamento
 *     @type array    $services          Lista de nomes de serviços
 *     @type string   $groomer_name      Nome do groomer (opcional)
 *     @type string   $amount            Valor formatado (opcional, para cobranças)
 *     @type string   $additional_info   Informações adicionais (opcional)
 * }
 * @return array|null Array com ['text' => 'mensagem'] ou null em caso de erro.
 */
public static function suggest_whatsapp_message( array $context )

/**
 * Gera sugestão de e-mail (assunto e corpo).
 *
 * @param array $context Contexto da mensagem (mesmos campos do WhatsApp).
 * @return array|null Array com ['subject' => 'assunto', 'body' => 'corpo'] ou null.
 */
public static function suggest_email_message( array $context )
```

**Tipos de mensagens suportados:**
- `lembrete`: Lembrete de agendamento
- `confirmacao`: Confirmação de agendamento
- `pos_atendimento`: Agradecimento pós-atendimento
- `cobranca_suave`: Lembrete educado de pagamento
- `cancelamento`: Notificação de cancelamento
- `reagendamento`: Confirmação de reagendamento

### Handlers AJAX

#### `wp_ajax_dps_ai_suggest_whatsapp_message`

Gera sugestão de mensagem WhatsApp via AJAX.

**Request:**
```javascript
{
    action: 'dps_ai_suggest_whatsapp_message',
    nonce: 'dps_ai_comm_nonce',
    context: {
        type: 'lembrete',
        client_name: 'João Silva',
        pet_name: 'Rex',
        appointment_date: '15/12/2024',
        appointment_time: '14:00',
        services: ['Banho', 'Tosa']
    }
}
```

**Response (sucesso):**
```javascript
{
    success: true,
    data: {
        text: 'Olá João! Lembrete: amanhã às 14:00 temos o agendamento...'
    }
}
```

**Response (erro):**
```javascript
{
    success: false,
    data: {
        message: 'Não foi possível gerar sugestão automática. A IA pode estar desativada...'
    }
}
```

#### `wp_ajax_dps_ai_suggest_email_message`

Gera sugestão de e-mail via AJAX.

**Request:** (mesma estrutura do WhatsApp)

**Response (sucesso):**
```javascript
{
    success: true,
    data: {
        subject: 'Lembrete de Agendamento - Desi Pet Shower',
        body: 'Olá João,\n\nEste é um lembrete...'
    }
}
```

### Interface JavaScript

**Arquivo:** `assets/js/dps-ai-communications.js`

**Classes CSS:**
- `.dps-ai-suggest-whatsapp`: Botão de sugestão para WhatsApp
- `.dps-ai-suggest-email`: Botão de sugestão para e-mail

**Atributos de dados (data-*):**

Para WhatsApp:
```html
<button 
    class="button dps-ai-suggest-whatsapp"
    data-target="#campo-mensagem"
    data-type="lembrete"
    data-client-name="João Silva"
    data-pet-name="Rex"
    data-appointment-date="15/12/2024"
    data-appointment-time="14:00"
    data-services='["Banho", "Tosa"]'
>
    Sugerir com IA
</button>
```

Para e-mail:
```html
<button 
    class="button dps-ai-suggest-email"
    data-target-subject="#campo-assunto"
    data-target-body="#campo-corpo"
    data-type="pos_atendimento"
    data-client-name="Maria Santos"
    data-pet-name="Mel"
>
    Sugerir E-mail com IA
</button>
```

**Modal de pré-visualização:**
- E-mails abrem modal para revisão antes de inserir nos campos
- Usuário pode editar assunto e corpo no modal
- Botão "Inserir" preenche os campos do formulário (não envia)

### Configurações

Armazenadas em `dps_ai_settings`:

```php
[
    'enabled'                 => bool,   // Habilita/desabilita IA
    'api_key'                 => string, // Chave da OpenAI (sk-...)
    'model'                   => string, // gpt-3.5-turbo, gpt-4, etc.
    'temperature'             => float,  // 0-1, padrão 0.4
    'timeout'                 => int,    // Segundos, padrão 10
    'max_tokens'              => int,    // Padrão 500
    'additional_instructions' => string, // Instruções customizadas (max 2000 chars)
]
```

**Opções específicas para comunicações:**
- WhatsApp: `max_tokens => 300` (mensagens curtas)
- E-mail: `max_tokens => 500` (pode ter mais contexto)
- Temperatura: `0.5` (levemente mais criativo para tom amigável)

### Segurança

- ✅ Validação de nonce em todos os handlers AJAX
- ✅ Verificação de capability `edit_posts`
- ✅ Sanitização de todos os inputs (`sanitize_text_field`, `wp_unslash`)
- ✅ System prompt base protegido contra sobrescrita
- ✅ **NUNCA envia mensagens automaticamente**
- ✅ API key server-side only (nunca exposta no JavaScript)

### Falhas e Tratamento de Erros

**IA desativada ou sem API key:**
- Retorna `null` em métodos PHP
- Retorna erro amigável em AJAX: "IA pode estar desativada..."
- **Campo de mensagem não é alterado** - usuário pode escrever manualmente

**Erro na API da OpenAI:**
- Timeout, erro de rede, resposta inválida → retorna `null`
- Logs em `error_log()` para debug
- Não quebra a interface - usuário pode continuar

**Parse de e-mail falha:**
- Tenta múltiplos padrões (ASSUNTO:/CORPO:, Subject:/Body:, divisão por linhas)
- Fallback: primeira linha como assunto, resto como corpo
- Se tudo falhar: retorna `null`

### Integração com Outros Add-ons

**Communications Add-on:**
- Sugestões de IA podem ser usadas com `DPS_Communications_API`
- IA gera texto → usuário revisa → `send_whatsapp()` ou `send_email()`

**Agenda Add-on:**
- Pode adicionar botões de sugestão nas páginas de agendamento
- Ver exemplos em `includes/ai-communications-examples.php`

**Portal do Cliente:**
- Widget de chat já integrado via `DPS_AI_Integration_Portal`
- Usa mesmo system prompt base e configurações

### Documentação Adicional

- **Manual completo**: `add-ons/desi-pet-shower-ai_addon/AI_COMMUNICATIONS.md`
- **Exemplos de código**: `add-ons/desi-pet-shower-ai_addon/includes/ai-communications-examples.php`
- **Comportamento da IA**: `add-ons/desi-pet-shower-ai_addon/BEHAVIOR_EXAMPLES.md`

### Hooks Expostos

Atualmente nenhum hook específico de comunicações. Possíveis hooks futuros:

```php
// Filtro antes de gerar sugestão
$context = apply_filters( 'dps_ai_comm_whatsapp_context', $context, $type );

// Filtro após gerar sugestão (permite pós-processamento)
$message = apply_filters( 'dps_ai_comm_whatsapp_message', $message, $context );
```

### Tabelas de Banco de Dados

Nenhuma tabela própria. Usa apenas configurações em `wp_options`.

### Limitações Conhecidas

- Depende de conexão com internet e API key válida da OpenAI
- Custo por chamada à API (variável por modelo e tokens)
- Qualidade das sugestões depende da qualidade dos dados fornecidos no contexto
- Não substitui revisão humana - **sempre revisar antes de enviar**
- Assets carregados em todas as páginas admin (TODO: otimizar para carregar apenas onde necessário)

### Exemplos de Uso

Ver arquivo completo de exemplos: `add-ons/desi-pet-shower-ai_addon/includes/ai-communications-examples.php`

**Exemplo rápido:**

```php
// Gerar sugestão de WhatsApp
$result = DPS_AI_Message_Assistant::suggest_whatsapp_message([
    'type'              => 'lembrete',
    'client_name'       => 'João Silva',
    'pet_name'          => 'Rex',
    'appointment_date'  => '15/12/2024',
    'appointment_time'  => '14:00',
    'services'          => ['Banho', 'Tosa'],
]);

if ( null !== $result ) {
    echo $result['text']; // Mensagem sugerida
}
```

### Changelog

**v1.0.0** - Lançamento inicial
- Widget de chat no Portal do Cliente
- Respostas contextualizadas sobre agendamentos e serviços

**v1.1.0** - Instruções adicionais
- Campo de instruções customizadas nas configurações
- Método público `get_base_system_prompt()`

**v1.2.0** - Assistente de Comunicações
- Classe `DPS_AI_Message_Assistant`
- Sugestões de WhatsApp e e-mail
- Handlers AJAX e interface JavaScript
- Modal de pré-visualização para e-mails
- 6 tipos de mensagens suportados
- Documentação e exemplos de integração
