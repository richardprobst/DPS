# Análise funcional do desi.pet by PRObst

## Plugin base (`plugins/desi-pet-shower-base`)
- O arquivo principal declara constantes globais, registra os *custom post types* de clientes, pets e agendamentos, carrega os ativos do frontend e expõe os *shortcodes* `[dps_base]` e `[dps_configuracoes]`, que servem como ponto de entrada para o painel e para a tela de configurações consumida pelos add-ons.
- `includes/class-dps-cpt-helper.php` centraliza o registro de CPTs com rótulos e argumentos padrão; novos tipos devem instanciar `DPS_CPT_Helper` para herdar as opções comuns e apenas sobrescrever particularidades (ex.: capabilities customizadas ou suporte a editor).
- **NOTA**: Os CPTs principais (`dps_cliente`, `dps_pet`, `dps_agendamento`) estão registrados com `show_ui => true` e `show_in_menu => false`, sendo exibidos pelo painel central e reutilizáveis pelos add-ons via abas. Para análise completa sobre a interface nativa do WordPress para estes CPTs, consulte `docs/analysis/BASE_PLUGIN_DEEP_ANALYSIS.md` e `docs/analysis/ADMIN_MENUS_MAPPING.md`.
- A classe `DPS_Base_Frontend` concentra a lógica de interface: normaliza telefones para WhatsApp, agrega dados de agendamentos multi-pet para cobranças conjuntas, monta botões de cobrança, controla salvamento/exclusão de clientes, pets e atendimentos e renderiza as abas consumidas pelos add-ons via *hooks* (`dps_base_nav_tabs_after_pets`, `dps_base_nav_tabs_after_history`, etc.).
- A classe `DPS_Settings_Frontend` gerencia a página de configurações (`[dps_configuracoes]`) com sistema moderno de registro de abas via `register_tab()`. Os hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` foram depreciados em favor do sistema moderno que oferece melhor consistência visual. A página inclui assets dedicados (`dps-settings.css` e `dps-settings.js`) carregados automaticamente, com suporte a navegação client-side entre abas, busca em tempo real de configurações com destaque visual, barra de status contextual e detecção de alterações não salvas com aviso ao sair.
- O fluxo de formulários usa `dps_nonce` para CSRF e delega ações específicas (`save_client`, `save_pet`, `save_appointment`, `update_appointment_status`) para métodos especializados, enquanto exclusões limpam também dados financeiros relacionados quando disponíveis. A classe principal é inicializada no hook `init` com prioridade 5, após o carregamento do text domain em prioridade 1.
- A exclusão de agendamentos dispara o hook `dps_finance_cleanup_for_appointment`, permitindo que add-ons financeiros tratem a remoção de lançamentos vinculados sem depender de SQL no núcleo.
- O filtro `dps_tosa_consent_required` permite ajustar quando o consentimento de tosa com máquina é exigido ao salvar agendamentos (parâmetros: `$requires`, `$data`, `$service_ids`).
- A criação de tabelas do núcleo (ex.: `dps_logs`) é registrada no `register_activation_hook` e versionada via option `dps_logger_db_version`. Caso a flag de versão não exista ou esteja desatualizada, `dbDelta` é chamado uma única vez em `plugins_loaded` para alinhar o esquema, evitando consultas de verificação em todos os ciclos de `init`.
- **Organização do menu admin**: o menu pai `desi-pet-shower` apresenta apenas hubs e itens principais. Um limpador dedicado (`DPS_Admin_Menu_Cleaner`) remove submenus duplicados que já estão cobertos por hubs (Integrações, Sistema, Ferramentas, Agenda, IA, Portal). As páginas continuam acessíveis via URL direta e pelas abas dos hubs, evitando poluição visual na navegação.

### Helpers globais do núcleo

O plugin base oferece classes utilitárias para padronizar operações comuns e evitar duplicação de lógica. Estes helpers estão disponíveis em `plugins/desi-pet-shower-base/includes/` e podem ser usados tanto pelo núcleo quanto pelos add-ons.

#### DPS_Money_Helper
**Propósito**: Manipulação consistente de valores monetários com conversão entre formato brasileiro e centavos.

**Entrada/Saída**:
- `parse_brazilian_format( string )`: Converte string BR (ex.: "1.234,56") → int centavos (123456)
- `format_to_brazilian( int )`: Converte centavos (123456) → string BR ("1.234,56")
- `format_currency( int, string $symbol = 'R$ ' )`: Converte centavos → string com símbolo ("R$ 1.234,56")
- `format_currency_from_decimal( float, string $symbol = 'R$ ' )`: Converte decimal → string com símbolo ("R$ 1.234,56")
- `decimal_to_cents( float )`: Converte decimal (12.34) → int centavos (1234)
- `cents_to_decimal( int )`: Converte centavos (1234) → float decimal (12.34)
- `is_valid_money_string( string )`: Valida se string representa valor monetário → bool

**Exemplos práticos**:
```php
// Validar e converter valor do formulário para centavos
$preco_raw = isset( $_POST['preco'] ) ? sanitize_text_field( $_POST['preco'] ) : '';
$valor_centavos = DPS_Money_Helper::parse_brazilian_format( $preco_raw );

// Exibir valor formatado na tela (com símbolo de moeda)
echo DPS_Money_Helper::format_currency( $valor_centavos );
// Resultado: "R$ 1.234,56"

// Para valores decimais (em reais, não centavos)
echo DPS_Money_Helper::format_currency_from_decimal( 1234.56 );
// Resultado: "R$ 1.234,56"
```

**Boas práticas**: 
- Use `format_currency()` para exibição em interfaces (já inclui "R$ ")
- Use `format_to_brazilian()` quando precisar apenas do valor sem símbolo
- Evite lógica duplicada de `number_format` espalhada pelo código

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
**Propósito**: Validação centralizada de nonces, capabilities, requisições AJAX e sanitização de campos de formulário.

**Métodos principais:**
- `verify_request_nonce( $nonce_field, $nonce_action, $method, $die_on_failure )`: Verifica nonce POST/GET
- `verify_nonce_and_capability( $nonce_field, $nonce_action, $capability )`: Valida nonce e permissão
- `verify_capability( $capability, $die_on_failure )`: Verifica apenas capability

**Métodos AJAX (Fase 3):**
- `verify_ajax_nonce( $nonce_action, $nonce_field = 'nonce' )`: Verifica nonce AJAX com resposta JSON automática
- `verify_ajax_admin( $nonce_action, $capability = 'manage_options' )`: Verifica nonce + capability para AJAX admin
- `verify_admin_action( $nonce_action, $capability, $nonce_field = '_wpnonce' )`: Verifica nonce de ação GET
- `verify_admin_form( $nonce_action, $nonce_field, $capability )`: Verifica nonce de formulário POST
- `verify_dynamic_nonce( $nonce_prefix, $item_id )`: Verifica nonce com ID dinâmico

**Métodos de resposta:**
- `send_json_error( $message, $code, $status )`: Resposta JSON de erro padronizada
- `send_json_success( $message, $data )`: Resposta JSON de sucesso padronizada

**Métodos auxiliares:**
- `get_post_int( $field_name, $default )`: Obtém inteiro do POST sanitizado
- `get_post_string( $field_name, $default )`: Obtém string do POST sanitizada
- `get_get_int( $field_name, $default )`: Obtém inteiro do GET sanitizado
- `get_get_string( $field_name, $default )`: Obtém string do GET sanitizada

**Exemplos práticos:**
```php
// Handler AJAX admin simples
public function ajax_delete_item() {
    if ( ! DPS_Request_Validator::verify_ajax_admin( 'dps_delete_item' ) ) {
        return; // Resposta JSON de erro já enviada
    }
    // ... processar ação
}

// Verificar nonce com ID dinâmico
$client_id = absint( $_GET['client_id'] );
if ( ! DPS_Request_Validator::verify_dynamic_nonce( 'dps_delete_client_', $client_id, 'nonce', 'GET' ) ) {
    return;
}

// Validar formulário admin
if ( ! DPS_Request_Validator::verify_admin_form( 'dps_save_settings', 'dps_settings_nonce' ) ) {
    return;
}
```

**Boas práticas**: Use `verify_ajax_admin()` para handlers AJAX admin e `verify_ajax_nonce()` para AJAX público. Evite duplicar lógica de segurança.

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

#### DPS_WhatsApp_Helper
**Propósito**: Geração centralizada de links do WhatsApp com mensagens personalizadas. Introduzida para padronizar criação de URLs do WhatsApp em todo o sistema.

**Constante**:
- `TEAM_PHONE = '5515991606299'`: Número padrão da equipe (+55 15 99160-6299)

**Entrada/Saída**:
- `get_link_to_team( string $message = '' )`: Gera link para cliente → equipe → string URL
- `get_link_to_client( string $client_phone, string $message = '' )`: Gera link para equipe → cliente → string URL ou vazio se inválido
- `get_share_link( string $message )`: Gera link de compartilhamento genérico → string URL
- `get_team_phone()`: Obtém número da equipe configurado → string (formatado)

**Métodos auxiliares para mensagens padrão**:
- `get_portal_access_request_message( $client_name = '', $pet_name = '' )`: Mensagem padrão para solicitar acesso
- `get_portal_link_message( $client_name, $portal_url )`: Mensagem padrão para enviar link do portal
- `get_appointment_confirmation_message( $appointment_data )`: Mensagem padrão de confirmação de agendamento
- `get_payment_request_message( $client_name, $amount, $payment_url = '' )`: Mensagem padrão de cobrança

**Exemplos práticos**:
```php
// Cliente quer contatar a equipe (ex: solicitar acesso ao portal)
$message = DPS_WhatsApp_Helper::get_portal_access_request_message();
$url = DPS_WhatsApp_Helper::get_link_to_team( $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Quero acesso</a>';

// Equipe quer contatar cliente (ex: enviar link do portal)
$client_phone = get_post_meta( $client_id, 'client_phone', true );
$portal_url = 'https://exemplo.com/portal?token=abc123';
$message = DPS_WhatsApp_Helper::get_portal_link_message( 'João Silva', $portal_url );
$url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $message );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Enviar via WhatsApp</a>';

// Compartilhamento genérico (ex: foto do pet)
$share_text = 'Olha a foto do meu pet: https://exemplo.com/foto.jpg';
$url = DPS_WhatsApp_Helper::get_share_link( $share_text );
echo '<a href="' . esc_url( $url ) . '" target="_blank">Compartilhar</a>';
```

**Configuração**:
- Número da equipe configurável em: Admin → desi.pet by PRObst → Comunicações
- Option: `dps_whatsapp_number` (padrão: +55 15 99160-6299)
- Fallback automático para constante `TEAM_PHONE` se option não existir
- Filtro disponível: `dps_team_whatsapp_number` para customização programática

**Boas práticas**:
- Use sempre este helper para criar links WhatsApp (não construa URLs manualmente)
- Helper formata automaticamente números de clientes usando `DPS_Phone_Helper`
- Sempre escape URLs com `esc_url()` ao exibir em HTML
- Mensagens são codificadas automaticamente com `rawurlencode()`
- Retorna string vazia se número do cliente for inválido (verificar antes de exibir link)

**Locais que usam este helper**:
- Lista de clientes (plugin base)
- Add-on de Agenda (confirmação e cobrança)
- Add-on de Assinaturas (cobrança de renovação)
- Add-on de Finance (pendências financeiras)
- Add-on de Stats (reengajamento de clientes inativos)
- Portal do Cliente (solicitação de acesso, envio de link, agendamento, compartilhamento)

#### DPS_IP_Helper
**Propósito**: Obtenção e validação centralizada de endereços IP do cliente, com suporte a proxies, CDNs (Cloudflare) e ambientes de desenvolvimento.

**Entrada/Saída**:
- `get_ip()`: Obtém IP simples via REMOTE_ADDR → string (IP ou 'unknown')
- `get_ip_with_proxy_support()`: Obtém IP real através de proxies/CDNs → string (IP ou vazio)
- `get_ip_hash( string $salt )`: Obtém hash SHA-256 do IP para rate limiting → string (64 caracteres)
- `is_valid_ip( string $ip )`: Valida IPv4 ou IPv6 → bool
- `is_valid_ipv4( string $ip )`: Valida apenas IPv4 → bool
- `is_valid_ipv6( string $ip )`: Valida apenas IPv6 → bool
- `is_localhost( string $ip = null )`: Verifica se é localhost → bool
- `anonymize( string $ip )`: Anonimiza IP para LGPD/GDPR → string

**Exemplos práticos**:
```php
// Obter IP simples para logging
$ip = DPS_IP_Helper::get_ip();

// Obter IP real através de CDN (Cloudflare)
$ip = DPS_IP_Helper::get_ip_with_proxy_support();

// Gerar hash para rate limiting
$hash = DPS_IP_Helper::get_ip_hash( 'dps_login_' );
set_transient( 'rate_limit_' . $hash, $count, HOUR_IN_SECONDS );

// Anonimizar IP para logs de longa duração (LGPD)
$anon_ip = DPS_IP_Helper::anonymize( $ip );
// '192.168.1.100' → '192.168.1.0'
```

**Headers verificados** (em ordem de prioridade):
1. `HTTP_CF_CONNECTING_IP` - Cloudflare
2. `HTTP_X_REAL_IP` - Nginx proxy
3. `HTTP_X_FORWARDED_FOR` - Proxy padrão (usa primeiro IP da lista)
4. `REMOTE_ADDR` - Conexão direta

**Boas práticas**:
- Use `get_ip()` para casos simples (logging, auditoria)
- Use `get_ip_with_proxy_support()` quando há CDN/proxy (rate limiting, segurança)
- Use `get_ip_hash()` para armazenar referências de IP sem expor o endereço real
- Use `anonymize()` para logs de longa duração em compliance com LGPD/GDPR

**Add-ons que usam este helper**:
- Portal do Cliente (autenticação, rate limiting, logs de acesso)
- Add-on de Pagamentos (webhooks, auditoria)
- Add-on de IA (rate limiting do chat público)
- Add-on de Finance (auditoria de operações)
- Add-on de Registration (rate limiting de cadastros)

#### DPS_Client_Helper
**Propósito**: Acesso centralizado a dados de clientes, com suporte a CPT `dps_client` e usermeta do WordPress, eliminando duplicação de código para obtenção de telefone, email, endereço e outros metadados.

**Entrada/Saída**:
- `get_phone( int $client_id, ?string $source = null )`: Obtém telefone do cliente → string
- `get_email( int $client_id, ?string $source = null )`: Obtém email do cliente → string
- `get_whatsapp( int $client_id, ?string $source = null )`: Obtém WhatsApp (fallback para phone) → string
- `get_name( int $client_id, ?string $source = null )`: Obtém nome do cliente → string
- `get_display_name( int $client_id, ?string $source = null )`: Obtém nome para exibição → string
- `get_address( int $client_id, ?string $source = null, string $sep = ', ' )`: Obtém endereço formatado → string
- `get_all_data( int $client_id, ?string $source = null )`: Obtém todos os metadados de uma vez → array
- `has_valid_phone( int $client_id, ?string $source = null )`: Verifica se tem telefone válido → bool
- `has_valid_email( int $client_id, ?string $source = null )`: Verifica se tem email válido → bool
- `get_pets( int $client_id, array $args = [] )`: Obtém lista de pets do cliente → array
- `get_pets_count( int $client_id )`: Conta pets do cliente → int
- `get_primary_pet( int $client_id )`: Obtém pet principal → WP_Post|null
- `format_contact_info( int $client_id, ?string $source = null )`: Formata informações de contato → string (HTML)
- `get_for_display( int $client_id, ?string $source = null )`: Obtém dados formatados para exibição → array
- `search_by_phone( string $phone, bool $exact = false )`: Busca cliente por telefone → int|null
- `search_by_email( string $email )`: Busca cliente por email → int|null

**Parâmetro `$source`**:
- `null` (padrão): Auto-detecta se é post (`dps_client`) ou user (WordPress user)
- `'post'`: Força busca em post_meta
- `'user'`: Força busca em usermeta

**Constantes de meta keys**:
- `META_PHONE` = 'client_phone'
- `META_EMAIL` = 'client_email'
- `META_WHATSAPP` = 'client_whatsapp'
- `META_ADDRESS` = 'client_address'
- `META_CITY` = 'client_city'
- `META_STATE` = 'client_state'
- `META_ZIP` = 'client_zip'

**Exemplos práticos**:
```php
// Obter telefone de um cliente (auto-detecta source)
$phone = DPS_Client_Helper::get_phone( $client_id );

// Obter todos os dados de uma vez (mais eficiente)
$data = DPS_Client_Helper::get_all_data( $client_id );
echo $data['name'] . ' - ' . $data['phone'];

// Verificar se tem telefone válido antes de enviar WhatsApp
if ( DPS_Client_Helper::has_valid_phone( $client_id ) ) {
    $whatsapp = DPS_Client_Helper::get_whatsapp( $client_id );
    // ...enviar mensagem
}

// Buscar cliente por telefone
$existing = DPS_Client_Helper::search_by_phone( '11999887766' );
if ( $existing ) {
    // Cliente já existe
}

// Para exibição na UI (já formatado)
$display = DPS_Client_Helper::get_for_display( $client_id );
echo $display['display_name']; // "João Silva" ou "Cliente sem nome"
echo $display['phone_formatted']; // "(11) 99988-7766"
```

**Boas práticas**:
- Use `get_all_data()` quando precisar de múltiplos campos (evita queries repetidas)
- Use `get_for_display()` para dados já formatados para UI
- O helper integra com `DPS_Phone_Helper` automaticamente quando disponível
- Não acesse diretamente `get_post_meta( $id, 'client_phone' )` — use o helper para consistência

**Add-ons que usam este helper**:
- Plugin Base (formulários de cliente, frontend)
- Portal do Cliente (exibição de dados, mensagens)
- Add-on de IA (chat público, agendador)
- Add-on de Push (notificações por email/WhatsApp)
- Add-on de Communications (envio de comunicados)
- Add-on de Finance (relatórios, cobranças)

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

#### DPS_Cache_Control
**Propósito**: Gerenciamento de cache de páginas para garantir que todas as páginas do sistema DPS não sejam armazenadas em cache, forçando conteúdo sempre atualizado.

**Entrada/Saída**:
- `init()`: Registra hooks para detecção e prevenção de cache (chamado automaticamente no boot do plugin)
- `force_no_cache()`: Força desabilitação de cache na requisição atual
- `register_shortcode( string $shortcode )`: Registra shortcode adicional para prevenção automática de cache
- `get_registered_shortcodes()`: Retorna lista de shortcodes registrados

**Constantes definidas quando cache é desabilitado**:
- `DONOTCACHEPAGE`: Previne cache de página (WP Super Cache, W3 Total Cache, LiteSpeed Cache)
- `DONOTCACHEDB`: Previne cache de queries
- `DONOTMINIFY`: Previne minificação de assets
- `DONOTCDN`: Previne uso de CDN
- `DONOTCACHEOBJECT`: Previne cache de objetos

**Headers HTTP enviados**:
- `Cache-Control: no-cache, must-revalidate, max-age=0`
- `Pragma: no-cache`
- `Expires: Wed, 11 Jan 1984 05:00:00 GMT`

**Exemplos práticos**:
```php
// Em um shortcode personalizado de add-on, forçar no-cache
public function render_meu_shortcode() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::force_no_cache();
    }
    // ... renderização do shortcode
}

// Registrar um shortcode personalizado para prevenção automática de cache
add_action( 'init', function() {
    if ( class_exists( 'DPS_Cache_Control' ) ) {
        DPS_Cache_Control::register_shortcode( 'meu_addon_shortcode' );
    }
} );
```

**Boas práticas**:
- Todos os shortcodes do DPS já chamam `force_no_cache()` automaticamente
- Para add-ons customizados, sempre inclua a chamada no início do método de renderização
- Use `class_exists( 'DPS_Cache_Control' )` antes de chamar para compatibilidade com versões anteriores
- A detecção automática via hook `template_redirect` funciona como backup

#### Sistema de Templates Sobrescrevíveis

**Propósito**: Permitir que temas customizem a aparência de templates do DPS mantendo a lógica de negócio no plugin. O sistema também oferece controle sobre quando forçar o uso do template do plugin.

**Funções disponíveis** (definidas em `includes/template-functions.php`):

| Função | Propósito |
|--------|-----------|
| `dps_get_template( $template_name, $args )` | Localiza e inclui um template, permitindo override pelo tema |
| `dps_get_template_path( $template_name )` | Retorna o caminho do template que seria carregado (sem incluí-lo) |
| `dps_is_template_overridden( $template_name )` | Verifica se um template está sendo sobrescrito pelo tema |

**Ordem de busca de templates**:
1. Tema filho: `wp-content/themes/CHILD_THEME/dps-templates/{template_name}`
2. Tema pai: `wp-content/themes/PARENT_THEME/dps-templates/{template_name}`
3. Plugin base: `wp-content/plugins/desi-pet-shower-base/templates/{template_name}`

**Filtros disponíveis**:

| Filtro | Propósito | Parâmetros |
|--------|-----------|------------|
| `dps_use_plugin_template` | Força uso do template do plugin, ignorando override do tema | `$use_plugin (bool)`, `$template_name (string)` |
| `dps_allow_consent_template_override` | Permite que tema sobrescreva o template de consentimento de tosa | `$allow_override (bool)` |

**Actions disponíveis**:

| Action | Propósito | Parâmetros |
|--------|-----------|------------|
| `dps_template_loaded` | Disparada quando um template é carregado | `$path_to_load (string)`, `$template_name (string)`, `$is_theme_override (bool)` |

**Exemplos práticos**:
```php
// Forçar uso do template do plugin para um template específico
add_filter( 'dps_use_plugin_template', function( $use_plugin, $template_name ) {
    if ( $template_name === 'meu-template.php' ) {
        return true; // Sempre usa versão do plugin
    }
    return $use_plugin;
}, 10, 2 );

// Permitir override do tema no template de consentimento de tosa
add_filter( 'dps_allow_consent_template_override', '__return_true' );

// Debug: logar qual template está sendo carregado
add_action( 'dps_template_loaded', function( $path, $name, $is_override ) {
    if ( $is_override ) {
        error_log( "DPS: Template '$name' sendo carregado do tema: $path" );
    }
}, 10, 3 );

// Verificar se um template está sendo sobrescrito
if ( dps_is_template_overridden( 'tosa-consent-form.php' ) ) {
    // Template do tema está sendo usado
}
```

**Boas práticas**:
- O template de consentimento de tosa (`tosa-consent-form.php`) força uso do plugin por padrão para garantir que melhorias sejam visíveis
- Use `dps_get_template_path()` para debug quando templates não aparecem como esperado
- A action `dps_template_loaded` é útil para logging e diagnóstico de problemas
- Quando sobrescrever templates no tema, mantenha as variáveis esperadas pelo sistema

### Feedback visual e organização de interface
- Todos os formulários principais (clientes, pets, agendamentos) utilizam `DPS_Message_Helper` para feedback após salvar ou excluir
- Formulários são organizados em fieldsets semânticos com bordas sutis (`1px solid #e5e7eb`) e legends descritivos
- Hierarquia de títulos padronizada: H1 único no topo ("Painel de Gestão DPS"), H2 para seções principais, H3 para subseções
- Design minimalista com paleta reduzida: base neutra (#f9fafb, #e5e7eb, #374151) + 3 cores de status essenciais (verde, amarelo, vermelho)
- Responsividade básica implementada com media queries para mobile (480px), tablets (768px) e desktops pequenos (1024px)

### Gerenciador de Add-ons

O plugin base inclui um gerenciador de add-ons centralizado (`DPS_Addon_Manager`) que:
- Lista todos os add-ons disponíveis do ecossistema DPS
- Verifica status de instalação e ativação
- Determina a ordem correta de ativação baseada em dependências
- Permite ativar/desativar add-ons em lote respeitando dependências

**Classe**: `includes/class-dps-addon-manager.php`

**Menu administrativo**: desi.pet by PRObst → Add-ons (`dps-addons`)

#### Categorias de Add-ons

| Categoria | Descrição | Add-ons |
|-----------|-----------|---------|
| Essenciais | Funcionalidades base recomendadas | Serviços, Financeiro, Comunicações |
| Operação | Gestão do dia a dia | Agenda, Groomers, Assinaturas, Estoque |
| Integrações | Conexões externas | Pagamentos, Push Notifications |
| Cliente | Voltados ao cliente final | Cadastro Público, Portal do Cliente, Fidelidade |
| Avançado | Funcionalidades extras | IA, Estatísticas |
| Sistema | Administração e manutenção | Backup |

#### Dependências entre Add-ons

O sistema resolve automaticamente as dependências na ordem de ativação:

| Add-on | Depende de |
|--------|-----------|
| Agenda | Serviços |
| Assinaturas | Serviços, Financeiro |
| Pagamentos | Financeiro |
| IA | Portal do Cliente |

#### API Pública

```php
// Obter instância do gerenciador
$manager = DPS_Addon_Manager::get_instance();

// Verificar se add-on está ativo
$is_active = $manager->is_active( 'agenda' );

// Verificar dependências
$deps = $manager->check_dependencies( 'ai' );
// Retorna: ['satisfied' => false, 'missing' => ['client-portal']]

// Obter ordem recomendada de ativação
$order = $manager->get_activation_order();
// Retorna array ordenado por dependências com status de cada add-on

// Ativar múltiplos add-ons na ordem correta
$result = $manager->activate_addons( ['services', 'agenda', 'finance'] );
// Ativa: services → finance → agenda (respeitando dependências)
```

#### Interface Administrativa

A página "Add-ons" exibe:
1. **Ordem de Ativação Recomendada**: Lista visual dos add-ons instalados na ordem sugerida
2. **Categorias de Add-ons**: Cards organizados por categoria com:
   - Nome e ícone do add-on
   - Status (Ativo/Inativo/Não Instalado)
   - Descrição curta
   - Dependências necessárias
   - Checkbox para seleção
3. **Ações em Lote**: Botões para ativar ou desativar add-ons selecionados

**Segurança**:
- Verificação de nonce em todas as ações
- Capability `manage_options` para acesso à página
- Capability `activate_plugins`/`deactivate_plugins` para ações

### GitHub Updater

O plugin base inclui um sistema de atualização automática via GitHub (`DPS_GitHub_Updater`) que:
- Verifica novas versões diretamente do repositório GitHub
- Notifica atualizações disponíveis no painel de Plugins do WordPress
- Suporta o plugin base e todos os add-ons oficiais
- Usa cache inteligente para evitar chamadas excessivas à API

**Classe**: `includes/class-dps-github-updater.php`

**Repositório**: `richardprobst/DPS`

#### Como Funciona

1. **Verificação de Versões**: O updater consulta a API do GitHub (`/repos/{owner}/{repo}/releases/latest`) para obter a versão mais recente.
2. **Comparação**: Compara a versão instalada de cada plugin com a versão da release mais recente.
3. **Notificação**: Se houver atualização disponível, injeta os dados no transient de updates do WordPress.
4. **Instalação**: O WordPress usa seu fluxo padrão de atualização para baixar e instalar.

#### Configuração

O sistema funciona automaticamente sem configuração adicional. Para desabilitar:

```php
// Desabilitar o updater via hook (em wp-config.php ou plugin)
add_filter( 'dps_github_updater_enabled', '__return_false' );
```

#### API Pública

```php
// Obter instância do updater
$updater = DPS_GitHub_Updater::get_instance();

// Forçar verificação (limpa cache)
$release_data = $updater->force_check();

// Obter lista de plugins gerenciados
$plugins = $updater->get_managed_plugins();

// Verificar se um plugin é gerenciado
$is_managed = $updater->is_managed_plugin( 'desi-pet-shower-base_plugin/desi-pet-shower-base.php' );
```

#### Forçar Verificação Manual

Adicione `?dps_force_update_check=1` à URL do painel de Plugins para forçar nova verificação:

```
/wp-admin/plugins.php?dps_force_update_check=1
```

#### Requisitos para Releases

Para que o updater reconheça uma nova versão:
1. A release no GitHub deve usar tags semver (ex: `v1.2.0` ou `1.2.0`)
2. A versão na tag deve ser maior que a versão instalada
3. Opcionalmente, anexe arquivos `.zip` individuais por plugin para download direto

#### Plugins Gerenciados

| Plugin | Arquivo | Caminho no Repositório |
|--------|---------|------------------------|
| Base Plugin | `desi-pet-shower-base_plugin/desi-pet-shower-base.php` | `plugins/desi-pet-shower-base` |
| Agenda | `desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php` | `plugins/desi-pet-shower-agenda` |
| AI | `desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php` | `plugins/desi-pet-shower-ai` |
| Backup | `desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php` | `plugins/desi-pet-shower-backup` |
| Client Portal | `desi-pet-shower-client-portal_addon/desi-pet-shower-client-portal.php` | `plugins/desi-pet-shower-client-portal` |
| Communications | `desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php` | `plugins/desi-pet-shower-communications` |
| Finance | `desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php` | `plugins/desi-pet-shower-finance` |
| Groomers | `desi-pet-shower-groomers_addon/desi-pet-shower-groomers-addon.php` | `plugins/desi-pet-shower-groomers` |
| Loyalty | `desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php` | `plugins/desi-pet-shower-loyalty` |
| Payment | `desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php` | `plugins/desi-pet-shower-payment` |
| Push | `desi-pet-shower-push_addon/desi-pet-shower-push-addon.php` | `plugins/desi-pet-shower-push` |
| Registration | `desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php` | `plugins/desi-pet-shower-registration` |
| Services | `desi-pet-shower-services_addon/desi-pet-shower-services.php` | `plugins/desi-pet-shower-services` |
| Stats | `desi-pet-shower-stats_addon/desi-pet-shower-stats-addon.php` | `plugins/desi-pet-shower-stats` |
| Stock | `desi-pet-shower-stock_addon/desi-pet-shower-stock.php` | `plugins/desi-pet-shower-stock` |
| Subscription | `desi-pet-shower-subscription_addon/desi-pet-shower-subscription.php` | `plugins/desi-pet-shower-subscription` |

### Tipos de Agendamento

O sistema suporta três tipos de agendamentos, identificados pelo metadado `appointment_type`:

#### 1. Agendamento Simples (`simple`)
- **Propósito**: Atendimento único, sem recorrência
- **Campos específicos**: Permite adicionar TaxiDog com valor personalizado
- **Comportamento**: Status inicial "pendente", precisa ser manualmente atualizado para "realizado"
- **Metadados salvos**: 
  - `appointment_type` = 'simple'
  - `appointment_taxidog` (0 ou 1)
  - `appointment_taxidog_price` (float)
  - `appointment_total_value` (calculado pelo Services Add-on)

#### 2. Agendamento de Assinatura (`subscription`)
- **Propósito**: Atendimentos recorrentes (semanal ou quinzenal)
- **Campos específicos**: 
  - Frequência (semanal ou quinzenal)
  - Tosa opcional com preço e ocorrência configurável
  - TaxiDog disponível mas sem custo adicional
- **Comportamento**: Vincula-se a um registro de assinatura (`dps_subscription`) e gera atendimentos recorrentes
- **Metadados salvos**:
  - `appointment_type` = 'subscription'
  - `subscription_id` (ID do post de assinatura vinculado)
  - `appointment_tosa` (0 ou 1)
  - `appointment_tosa_price` (float)
  - `appointment_tosa_occurrence` (1-4 para semanal, 1-2 para quinzenal)
  - `subscription_base_value`, `subscription_total_value`

#### 3. Agendamento Passado (`past`)
- **Propósito**: Registrar atendimentos já realizados anteriormente
- **Campos específicos**:
  - Status do Pagamento: dropdown com opções "Pago" ou "Pendente"
  - Valor Pendente: campo numérico condicional (exibido apenas se status = "Pendente")
- **Comportamento**: 
  - Status inicial automaticamente definido como "realizado"
  - TaxiDog e Tosa não disponíveis (não aplicável para registros passados)
  - Permite controlar pagamentos pendentes de atendimentos históricos
- **Metadados salvos**:
  - `appointment_type` = 'past'
  - `appointment_status` = 'realizado' (definido automaticamente)
  - `past_payment_status` ('paid' ou 'pending')
  - `past_payment_value` (float, salvo apenas se status = 'pending')
  - `appointment_total_value` (calculado pelo Services Add-on)
- **Casos de uso**:
  - Migração de dados de sistemas anteriores
  - Registro de atendimentos realizados antes da implementação do sistema
  - Controle de pagamentos em atraso de atendimentos históricos

**Controle de visibilidade de campos (JavaScript)**:
- A função `updateTypeFields()` em `dps-appointment-form.js` controla a exibição condicional de campos baseada no tipo selecionado
- Campos de frequência: visíveis apenas para tipo `subscription`
- Campos de tosa: visíveis apenas para tipo `subscription`
- Campos de pagamento passado: visíveis apenas para tipo `past`
- TaxiDog com preço: visível apenas para tipo `simple`


### Histórico e exportação de agendamentos
- A coleta de atendimentos finalizados é feita em lotes pelo `WP_Query` com `fields => 'ids'`, `no_found_rows => true` e tamanho configurável via filtro `dps_history_batch_size` (padrão: 200). Isso evita uma única consulta gigante em tabelas volumosas e permite tratar listas grandes de forma incremental.
- As metas dos agendamentos são pré-carregadas com `update_meta_cache('post')` antes do loop, reduzindo consultas repetidas às mesmas linhas durante a renderização e exportação.
- Clientes, pets e serviços relacionados são resolvidos com caches em memória por ID, evitando `get_post` duplicadas quando o mesmo registro aparece em várias linhas.
- O botão de exportação gera CSV apenas com as colunas exibidas e respeita os filtros aplicados na tabela, o que limita o volume exportado a um subconjunto relevante e já paginado/filtrado pelo usuário.

## Add-ons complementares (`plugins/`)

### Text Domains para Internacionalização (i18n)

Todos os plugins e add-ons do DPS seguem o padrão WordPress de text domains para internacionalização. Os text domains oficiais são:

**Plugin Base**:
- `desi-pet-shower` - Plugin base que fornece CPTs e funcionalidades core

**Add-ons**:
- `dps-agenda-addon` - Agenda e agendamentos
- `dps-ai` - Assistente de IA
- `dps-backup-addon` - Backup e restauração
- `dps-booking-addon` - Página dedicada de agendamentos
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

**Carregamento de text domains (WordPress 6.7+)**:
- Todos os plugins devem incluir o header `Domain Path: /languages` para indicar onde os arquivos de tradução devem ser armazenados
- Add-ons devem carregar text domains usando `load_plugin_textdomain()` no hook `init` com prioridade 1
- Instanciar classes principais no hook `init` com prioridade 5 (após carregamento do text domain)
- Isso garante que strings traduzíveis no constructor sejam traduzidas corretamente
- Métodos de registro (CPT, taxonomias, etc.) devem ser adicionados ao `init` com prioridade padrão (10)
- **Não** carregar text domains ou instanciar classes antes do hook `init` (evitar `plugins_loaded` ou escopo global)

**Status de localização pt_BR**:
- ✅ Todos os 17 plugins (1 base + 16 add-ons) possuem headers `Text Domain` e `Domain Path` corretos
- ✅ Todos os plugins carregam text domain no hook `init` com prioridade 1
- ✅ Todas as classes são inicializadas no hook `init` com prioridade 5
- ✅ Todo código, comentários e strings estão em Português do Brasil
- ✅ Sistema pronto para expansão multilíngue com arquivos .po/.mo em `/languages`

---

### Estrutura de Menus Administrativos

Todos os add-ons do DPS devem registrar seus menus e submenus sob o menu principal **"desi.pet by PRObst"** (slug: `desi-pet-shower`) para manter a interface administrativa organizada e unificada.

**Menu Principal** (criado pelo plugin base):
- Slug: `desi-pet-shower`
- Ícone: `dashicons-pets`
- Capability: `manage_options`
- Posição: 56 (após "Settings")

**Submenus Ativos** (registrados pelo plugin base e add-ons):
- **Assistente de IA** (`dps-ai-settings`) - AI Add-on (configurações do assistente virtual)
- **Backup & Restauração** (`dps-backup`) - Backup Add-on (exportar/importar dados)
- **Campanhas** (`edit.php?post_type=dps_campaign`) - Loyalty Add-on (listagem de campanhas)
- **Campanhas & Fidelidade** (`dps-loyalty`) - Loyalty Add-on (configurações de pontos e indicações)
- **Clientes** (`dps-clients-settings`) - Plugin Base (define a URL da página dedicada de cadastro exibida nos atalhos da aba Clientes)
- **Comunicações** (`dps-communications`) - Communications Add-on (templates e gateways)
- **Formulário de Cadastro** (`dps-registration-settings`) - Registration Add-on (configurações do formulário público para clientes se cadastrarem)
- **Logins de Clientes** (`dps-client-logins`) - Client Portal Add-on (gerenciar tokens de acesso)
- **Logs do Sistema** (`dps-logs`) - Plugin Base (visualização de logs do sistema)
- **Mensagens do Portal** (`edit.php?post_type=dps_portal_message`) - Client Portal Add-on (mensagens enviadas pelos clientes)
- **Notificações** (`dps-push-notifications`) - Push Add-on (push, agenda, relatórios, Telegram)
- **Pagamentos** (`dps-payment-settings`) - Payment Add-on (Mercado Pago, PIX)
- **Portal do Cliente** (`dps-client-portal-settings`) - Client Portal Add-on (configurações do portal)

**Nomenclatura de Menus - Diretrizes de Usabilidade**:
- Use nomes curtos e descritivos que indiquem claramente a função
- Evite prefixos redundantes como "DPS" ou "desi.pet by PRObst" nos nomes de submenu
- Use verbos ou substantivos que descrevam a ação/entidade gerenciada
- Exemplos de nomes descritivos:
  - ✅ "Logs do Sistema" (indica claramente que são logs técnicos)
  - ✅ "Backup & Restauração" (ações disponíveis)
  - ✅ "Formulário de Cadastro" (indica que é um formulário para clientes se registrarem)
  - ❌ "DPS Logs" (prefixo redundante - já está no menu pai)
  - ❌ "Settings" (genérico demais)
  - ❌ "Cadastro Público" (pouco intuitivo, prefira "Formulário de Cadastro")

**Boas práticas para registro de menus**:
- Sempre use `add_submenu_page()` com `'desi-pet-shower'` como menu pai
- Use prioridade 20 no hook `admin_menu` para garantir que o menu pai já existe:
  ```php
  add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
  ```
- Evite criar menus próprios separados (ex: `add_menu_page()` em add-ons)
- Para CPTs que precisam aparecer no menu, use `show_in_menu => 'desi-pet-shower'` ao registrar o CPT:
  ```php
  register_post_type( 'meu_cpt', [
      'show_in_menu' => 'desi-pet-shower', // Agrupa no menu principal
      // ...
  ] );
  ```
- Prefira integração via `DPS_Settings_Frontend::register_tab()` para adicionar abas na página de configurações. Os hooks legados (`dps_settings_nav_tabs`, `dps_settings_sections`) estão depreciados.

**Histórico de correções**:
- **2025-01-13**: Hooks legados `dps_settings_nav_tabs` e `dps_settings_sections` depreciados em favor do sistema moderno de abas
- **2025-12-01**: Mensagens do Portal migrado de menu próprio para submenu do desi.pet by PRObst (CPT com show_in_menu)
- **2025-12-01**: Cadastro Público renomeado para "Formulário de Cadastro" (mais intuitivo)
- **2025-12-01**: Logs do Sistema migrado de menu próprio para submenu do desi.pet by PRObst
- **2025-11-24**: Adicionado menu administrativo ao Client Portal Add-on (Portal do Cliente e Logins de Clientes)
- **2024-11-24**: Corrigida prioridade de registro de menus em todos os add-ons (de 10 para 20)
- **2024-11-24**: Loyalty Add-on migrado de menu próprio (`dps-loyalty-addon`) para submenu unificado (`desi-pet-shower`)

---

### Agenda (`desi-pet-shower-agenda_addon`)

**Diretório**: `plugins/desi-pet-shower-agenda`

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

**Meta keys de agendamento** (post meta de `dps_agendamento`):
- `_dps_checklist`: checklist operacional com status por etapa (pré-banho, banho, secagem, tosa, orelhas/unhas, acabamento) e histórico de retrabalho
- `_dps_checkin`: dados de check-in (horário, observações, itens de segurança com severidade)
- `_dps_checkout`: dados de check-out (horário, observações, itens de segurança)

**Hooks consumidos**:
- Nenhum hook específico do núcleo (opera diretamente sobre CPTs)

**Hooks disparados**:
- `dps_agenda_send_reminders`: cron job diário para envio de lembretes
- `dps_checklist_rework_registered( $appointment_id, $step_key, $reason )`: quando uma etapa do checklist precisa de retrabalho
- `dps_appointment_checked_in( $appointment_id, $data )`: após check-in registrado
- `dps_appointment_checked_out( $appointment_id, $data )`: após check-out registrado

**Filtros**:
- `dps_checklist_default_steps`: permite add-ons adicionarem etapas ao checklist operacional (ex.: hidratação, ozônio)
- `dps_checkin_safety_items`: permite add-ons adicionarem itens de segurança ao check-in/check-out

**Endpoints AJAX**:
- `dps_update_status`: atualiza status de agendamento
- `dps_checklist_update`: atualiza status de uma etapa do checklist (nonce: `dps_checklist`)
- `dps_checklist_rework`: registra retrabalho em uma etapa do checklist (nonce: `dps_checklist`)
- `dps_appointment_checkin`: registra check-in com observações e itens de segurança (nonce: `dps_checkin`)
- `dps_appointment_checkout`: registra check-out com observações e itens de segurança (nonce: `dps_checkin`)
- `dps_get_services_details`: **[Deprecated v1.1.0]** mantido por compatibilidade, delega para `DPS_Services_API::get_services_details()`

**Dependências**:
- Depende do plugin base para CPTs de agendamento
- **[Recomendado]** Services Add-on para cálculo de valores via API
- Integra-se com add-on de Comunicações para envio de mensagens (se ativo)
- Aviso exibido se Finance Add-on não estiver ativo (funcionalidades financeiras limitadas)

**Introduzido em**: v0.1.0 (estimado)

**Assets**:
- `assets/js/agenda-addon.js`: interações AJAX e feedback visual
- `assets/js/checklist-checkin.js`: interações do checklist operacional e check-in/check-out
- `assets/css/checklist-checkin.css`: estilos M3 para checklist e check-in/check-out
- **[Deprecated]** `agenda-addon.js` e `agenda.js` na raiz (devem ser removidos)

**Classes de serviço**:
- `DPS_Agenda_Checklist_Service`: CRUD de checklist operacional com etapas, progresso e retrabalho
- `DPS_Agenda_Checkin_Service`: check-in/check-out com itens de segurança e cálculo de duração

**Observações**:
- Implementa `register_deactivation_hook` para limpar cron job `dps_agenda_send_reminders` ao desativar
- **[v1.1.0]** Lógica de serviços movida para Services Add-on; Agenda delega cálculos para `DPS_Services_API`
- **Documentação completa**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (análise profunda de código, funcionalidades, layout e melhorias propostas)
- **Documentação de layout**: `docs/analysis/AGENDA_ADDON_ANALYSIS.md` (seções de UX, responsividade e acessibilidade)

---

### Backup & Restauração (`desi-pet-shower-backup_addon`)

**Diretório**: `plugins/desi-pet-shower-backup`

**Propósito e funcionalidades principais**:
- Exportar todo o conteúdo do sistema em formato JSON (CPTs, metadados, options, tabelas, anexos)
- Restaurar dados de backups anteriores com mapeamento inteligente de IDs
- Proteger operações com nonces, validações e transações SQL
- Suportar migração entre ambientes WordPress

**Shortcodes expostos**: Nenhum

**Menus administrativos**:
- **Backup & Restauração** (`dps-backup`): interface para exportar e restaurar dados

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- **Exporta/Importa**: todos os CPTs prefixados com `dps_`, tabelas `dps_*`, options `dps_*`
- Options de histórico (planejado): `dps_backup_history`, `dps_backup_settings`

**Hooks consumidos**:
- `admin_menu` (prioridade 20): registra submenu sob "desi.pet by PRObst"
- `admin_post_dps_backup_export`: processa exportação de backup
- `admin_post_dps_backup_import`: processa importação de backup

**Hooks disparados**: Nenhum (opera de forma autônoma)

**Segurança implementada**:
- ✅ Nonces em exportação e importação (`dps_backup_nonce`)
- ✅ Verificação de capability `manage_options`
- ✅ Validação de extensão (apenas `.json`) e tamanho (máx. 50MB)
- ✅ Sanitização de tabelas e options (apenas prefixo `dps_`)
- ✅ Deserialização segura (`allowed_classes => false`)
- ✅ Transações SQL com rollback em caso de falha

**Dependências**:
- **Obrigatória**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- Acessa todos os CPTs e tabelas do sistema para exportação/importação

**Introduzido em**: v0.1.0 (estimado)

**Versão atual**: 1.0.0

**Observações**:
- Arquivo único de 1338 linhas; candidato a refatoração modular futura
- Suporta exportação de anexos (fotos de pets) e documentos financeiros (`dps_docs`)
- Mapeamento inteligente de IDs: clientes → pets → agendamentos → transações

**Análise completa**: Consulte `docs/analysis/BACKUP_ADDON_ANALYSIS.md` para análise detalhada de código, funcionalidades, segurança e melhorias propostas

---

### Booking (`desi-pet-shower-booking`)

**Diretório**: `plugins/desi-pet-shower-booking`  
**Versão**: 1.3.0

**Propósito e funcionalidades principais**:
- Página dedicada de agendamentos para administradores
- Mesma funcionalidade da aba Agendamentos do Painel de Gestão DPS, porém em página independente
- Formulário completo com seleção de cliente, pets, serviços, data/hora, tipo de agendamento (avulso/assinatura) e status de pagamento
- Tela de confirmação pós-agendamento com resumo e ações rápidas (WhatsApp, novo agendamento, voltar ao painel)
- Design system migrado para Material 3 Expressive (v1.3.0)
- Otimizações de performance (batch queries para owners de pets)
- Validações granulares de segurança (verificação por agendamento específico)

**Shortcodes expostos**:
- `[dps_booking_form]`: renderiza formulário completo de agendamento

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias; consome `dps_agendamento` do núcleo
- Cria página automaticamente na ativação: "Agendamento de Serviços"
- Options: `dps_booking_page_id`

**Hooks consumidos**:
- `dps_base_after_save_appointment`: captura agendamento salvo para exibir tela de confirmação
- `dps_base_appointment_fields`: permite injeção de campos customizados por add-ons
- `dps_base_appointment_assignment_fields`: permite adicionar campos de atribuição

**Hooks disparados**: Nenhum hook próprio

**Capabilities verificadas**:
- `manage_options` (admin total)
- `dps_manage_clients` (gestão de clientes)
- `dps_manage_pets` (gestão de pets)
- `dps_manage_appointments` (gestão de agendamentos)

**Assets (v1.3.0)**:
- `booking-addon.css`: Estilos M3 Expressive com semantic mapping, 100% tokens M3
- Dependência condicional de `dps-design-tokens.css` via check de `DPS_BASE_URL`
- Assets do base plugin carregados via `DPS_Base_Plugin::enqueue_frontend_assets()`

**Melhorias de segurança (v1.3.0)**:
- Método `can_edit_appointment()`: valida se usuário pode editar agendamento específico
- Verificação de `can_access()` antes de renderizar seção
- Documentação phpcs para parâmetros GET read-only

**Otimizações de performance (v1.3.0)**:
- Batch fetch de owners de pets (redução de N+1 queries: 100+ → 1)
- Preparado para futura paginação de clientes

**Acessibilidade (v1.3.0)**:
- `aria-hidden="true"` em todos emojis decorativos
- Suporte a `prefers-reduced-motion` em animações
- ARIA roles e labels conforme padrões do base plugin

**Endpoints AJAX**: Nenhum

**Dependências**:
- Depende do plugin base para CPTs de agendamento e helpers globais
- Integra-se com Services Add-on para listagem de serviços disponíveis
- Integra-se com Groomers Add-on para atribuição de profissionais (se ativo)

**Introduzido em**: v1.0.0

**Assets**:
- `assets/css/dps-booking-form.css`: estilos do formulário de agendamento
- `assets/js/dps-booking-form.js`: interações do formulário (seleção de pets, datas, etc.)

**Observações**:
- Assets carregados condicionalmente apenas na página de agendamento (`dps_booking_page_id`)
- Implementa `register_activation_hook` para criar página automaticamente
- Formulário reutiliza lógica de salvamento do plugin base (`save_appointment`)

---

### Campanhas & Fidelidade (`desi-pet-shower-loyalty_addon`)

**Diretório**: `plugins/desi-pet-shower-loyalty`

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

**Diretório**: `plugins/desi-pet-shower-communications`

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
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) aba "Comunicações" registrada em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderização via callback em `register_tab()`

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

**Documentação completa**: `plugins/desi-pet-shower-communications/README.md`

---

### Groomers (`desi-pet-shower-groomers_addon`)

**Diretório**: `plugins/desi-pet-shower-groomers`

**Propósito e funcionalidades principais**:
- Cadastrar e gerenciar profissionais (groomers) via role customizada
- Vincular múltiplos groomers por atendimento
- Gerar relatórios de produtividade por profissional com métricas visuais
- Exibir cards de métricas: total de atendimentos, receita total, ticket médio
- Integração com Finance API para cálculo de receitas

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Role customizada: `dps_groomer` (profissional de banho e tosa)
- Metadados: `_dps_groomers` (array de IDs de groomers por agendamento)

**Hooks consumidos**:
- `dps_base_appointment_assignment_fields`: adiciona campo de seleção múltipla de groomers na seção "Atribuição" (desde v1.8.0)
- `dps_base_after_save_appointment`: salva groomers selecionados
- `dps_base_nav_tabs_after_history`: adiciona aba "Groomers" (prioridade 15)
- `dps_base_sections_after_history`: renderiza cadastro e relatórios (prioridade 15)
- `wp_enqueue_scripts`: carrega CSS e JS no frontend
- `admin_enqueue_scripts`: carrega CSS e JS no admin

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do plugin base para estrutura de navegação e agendamentos
- **Opcional**: Finance Add-on para cálculo automático de receitas nos relatórios

**Introduzido em**: v0.1.0 (estimado)

**Versão atual**: v1.1.0

**Assets**:
- `assets/css/groomers-admin.css`: estilos seguindo padrão visual minimalista DPS
- `assets/js/groomers-admin.js`: validações e interatividade do formulário

**Observações**:
- v1.1.0: Refatorado com assets externos, fieldsets no formulário e cards de métricas
- Formulário de cadastro com fieldsets: Dados de Acesso e Informações Pessoais
- Relatórios exibem detalhes de cliente e pet por atendimento
- Integração inteligente com Finance API (fallback para SQL direto)
- Consulte `docs/analysis/GROOMERS_ADDON_ANALYSIS.md` para análise detalhada e plano de melhorias

---

### Portal do Cliente (`desi-pet-shower-client-portal_addon`)

**Diretório**: `plugins/desi-pet-shower-client-portal`

**Propósito e funcionalidades principais**:
- Fornecer área autenticada para clientes
- Permitir atualização de dados pessoais e de pets
- Exibir histórico de atendimentos e pendências financeiras
- Integrar com módulo "Indique e Ganhe" quando ativo
- Sistema de autenticação via tokens (magic links) sem necessidade de senhas
- Link de atualização de perfil para clientes atualizarem seus dados sem login
- Coleta de consentimento de tosa com máquina via link tokenizado

**Shortcodes expostos**:
- `[dps_client_portal]`: renderiza portal completo do cliente
- `[dps_client_login]`: exibe formulário de login
- `[dps_profile_update]`: formulário público de atualização de perfil (usado internamente via token)
- `[dps_tosa_consent]`: formulário público de consentimento de tosa com máquina (via token)

**CPTs, tabelas e opções**:
- Não cria CPTs próprios
- Tabela customizada `wp_dps_portal_tokens` para gerenciar tokens de acesso
  - Suporta 5 tipos de token: `login` (temporário 30min), `first_access` (temporário 30min), `permanent` (válido até revogação), `profile_update` (7 dias), `tosa_consent` (7 dias)
- Sessões PHP próprias para autenticação independente do WordPress
- Option `dps_portal_page_id`: armazena ID da página configurada do portal
- Tipos de mensagem customizados para notificações

**Menus administrativos**:
- **Portal do Cliente** (`dps-client-portal-settings`): configurações gerais do portal
- **Logins de Clientes** (`dps-client-logins`): gerenciamento de tokens de acesso
  - Interface para gerar tokens temporários ou permanentes
  - Revogação manual de tokens ativos
  - Envio de links por WhatsApp ou e-mail
  - Histórico de acessos por cliente

**Hooks consumidos**:
- ~~`dps_settings_nav_tabs`~~: (migrado para sistema moderno) abas "Portal do Cliente" e "Logins de Clientes" registradas em `DPS_Settings_Frontend`
- ~~`dps_settings_sections`~~: (migrado para sistema moderno) renderização via callbacks em `register_tab()`
- Hooks do add-on de Pagamentos para links de quitação via Mercado Pago
- `dps_client_page_header_actions`: adiciona botão "Link de Atualização" no header da página de detalhes do cliente

**Hooks disparados**:
- `dps_client_portal_before_content`: disparado após o menu de navegação e antes das seções de conteúdo; passa $client_id como parâmetro; útil para adicionar conteúdo no topo do portal (ex: widgets, assistentes)
- `dps_client_portal_after_content`: disparado ao final do portal, antes do fechamento do container principal; passa $client_id como parâmetro
- `dps_portal_profile_update_link_generated`: disparado quando um link de atualização de perfil é gerado; passa $client_id e $update_url como parâmetros
- `dps_portal_profile_updated`: disparado quando o cliente atualiza seu perfil; passa $client_id como parâmetro
- `dps_portal_new_pet_created`: disparado quando um novo pet é cadastrado via formulário de atualização; passa $pet_id e $client_id como parâmetros
- `dps_portal_tosa_consent_link_generated`: disparado ao gerar link de consentimento; passa $client_id e $consent_url
- `dps_portal_tosa_consent_saved`: disparado ao salvar consentimento; passa $client_id
- `dps_portal_tosa_consent_revoked`: disparado ao revogar consentimento; passa $client_id

**Métodos públicos da classe `DPS_Client_Portal`**:
- `get_current_client_id()`: retorna o ID do cliente autenticado via sessão ou usuário WordPress (0 se não autenticado); permite que add-ons obtenham o cliente logado no portal

**Funções helper globais**:
- `dps_get_portal_page_url()`: retorna URL da página do portal (configurada ou fallback)
- `dps_get_portal_page_id()`: retorna ID da página do portal (configurada ou fallback)
- `dps_get_tosa_consent_page_url()`: retorna URL da página de consentimento (configurada ou fallback)

**Dependências**:
- Depende do plugin base para CPTs de clientes e pets
- Integra-se com add-on Financeiro para exibir pendências
- Integra-se com add-on de Fidelidade para exibir código de indicação

**Introduzido em**: v0.1.0 (estimado)
**Versão atual**: v2.1.0

**Observações**:
- Já segue padrão modular com estrutura `includes/` e `assets/`
- Sistema de tokens com suporte a temporários (30min) e permanentes (até revogação)
- Cleanup automático de tokens expirados via cron job hourly
- Configuração centralizada da página do portal via interface administrativa
- Menu administrativo registrado sob `desi-pet-shower` desde v2.1.0

**Análise de Layout e UX**:
- Consulte `docs/analysis/CLIENT_PORTAL_ADDON_DEEP_ANALYSIS.md` para análise detalhada de usabilidade e arquitetura do portal
- Consulte `docs/screenshots/CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md` para registro visual e resumo executivo das melhorias aplicadas
- Principais achados: estrutura "all-in-one" sem navegação, responsividade precária em mobile, paleta de cores excessiva (15+ cores vs 8 recomendadas), feedback visual ausente
- Melhorias prioritárias documentadas em 3 fases (26.5h totais): navegação interna, cards destacados, tabelas responsivas, feedback visual, redução de paleta, fieldsets em formulários

---

### Assistente de IA (`desi-pet-shower-ai_addon`)

**Diretório**: `plugins/desi-pet-shower-ai`

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
- `dps_client_portal_before_content`: renderiza widget de chat no topo do portal (após navegação, antes das seções)

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
- Menu: **desi.pet by PRObst > Assistente de IA**
- Configurações: ativar/desativar IA, API key, modelo GPT, temperatura, timeout, max_tokens
- Documentação inline sobre comportamento do assistente

**Observações**:
- Sistema totalmente autocontido: falhas não afetam funcionamento do Portal
- Custo por requisição varia conforme modelo escolhido (GPT-3.5 Turbo recomendado para custo/benefício)
- Consulte `plugins/desi-pet-shower-ai/README.md` para documentação completa de uso e manutenção

---

### Financeiro (`desi-pet-shower-finance_addon`)

**Diretório**: `plugins/desi-pet-shower-finance`

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

**Diretório**: `plugins/desi-pet-shower-payment`

**Propósito e funcionalidades principais**:
- Integrar com Mercado Pago para geração de links de pagamento
- Processar notificações de webhook para atualização de status
- Injetar mensagens de cobrança no WhatsApp via add-on de Agenda
- Gerenciar credenciais do Mercado Pago de forma segura

**Shortcodes expostos**: Nenhum

**Classes principais**:
- `DPS_Payment_Addon`: Classe principal do add-on (gerencia hooks e integração)
- `DPS_MercadoPago_Config` (v1.1.0+): Gerencia credenciais do Mercado Pago com ordem de prioridade

**CPTs, tabelas e opções**:
- Options: `dps_mercadopago_access_token`, `dps_pix_key`, `dps_mercadopago_webhook_secret` (credenciais Mercado Pago)
- **IMPORTANTE (v1.1.0+)**: Recomendado definir credenciais via constantes em `wp-config.php` para produção:
  - `DPS_MERCADOPAGO_ACCESS_TOKEN`: Token de acesso da API Mercado Pago
  - `DPS_MERCADOPAGO_WEBHOOK_SECRET`: Secret para validação de webhooks
  - `DPS_MERCADOPAGO_PUBLIC_KEY`: Chave pública (opcional)
- Ordem de prioridade: constantes wp-config.php → options em banco de dados
- Metadados em agendamentos (v1.1.0+):
  - `_dps_payment_link_status`: Status da geração do link ('success' | 'error' | 'not_requested')
  - `_dps_payment_last_error`: Detalhes do último erro (array: code, message, timestamp, context)

**Hooks consumidos**:
- `dps_base_after_save_appointment`: Gera link de pagamento quando agendamento é finalizado
- `dps_agenda_whatsapp_message`: Injeta link de pagamento na mensagem de cobrança
- `init` (prioridade 1): Processa webhooks cedo no ciclo de inicialização do WordPress

**Hooks disparados**: Nenhum

**Dependências**:
- Depende do add-on Financeiro para criar transações
- Integra-se com add-on de Agenda para envio de links via WhatsApp

**Introduzido em**: v0.1.0 (estimado)

**Versão atual**: v1.1.0

**Mudanças na v1.1.0**:
- Classe `DPS_MercadoPago_Config` para gerenciamento seguro de credenciais
- Suporte para constantes em wp-config.php (recomendado para produção)
- Tratamento de erros aprimorado com logging detalhado
- Flags de status em agendamentos para rastreamento de falhas
- Interface administrativa mostra campos readonly quando credenciais vêm de constantes
- Validação completa de respostas da API Mercado Pago

**Métodos principais**:
- `DPS_MercadoPago_Config::get_access_token()`: Retorna access token (constante ou option)
- `DPS_MercadoPago_Config::get_webhook_secret()`: Retorna webhook secret (constante ou option)
- `DPS_MercadoPago_Config::is_*_from_constant()`: Verifica se credencial vem de constante
- `DPS_MercadoPago_Config::get_masked_credential()`: Retorna últimos 4 caracteres para exibição
- `DPS_Payment_Addon::create_payment_preference()`: Cria preferência de pagamento via API MP
- `DPS_Payment_Addon::log_payment_error()`: Logging centralizado de erros de cobrança
- `DPS_Payment_Addon::process_payment_notification()`: Processa notificações de webhook
- `DPS_Payment_Addon::maybe_generate_payment_link()`: Gera link automaticamente para finalizados

**Observações**:
- Validação de webhook aplicada apenas quando requisição traz indicadores de notificação do MP
- Requer token de acesso e chave PIX configurados (via constantes ou options)
- **IMPORTANTE**: Configuração do webhook secret é obrigatória para processamento automático de pagamentos. Veja documentação completa em `plugins/desi-pet-shower-payment/WEBHOOK_CONFIGURATION.md`
- **SEGURANÇA**: Em produção, sempre defina credenciais via constantes em wp-config.php para evitar armazenamento em texto plano no banco de dados
- Logs de erro incluem contexto completo para debugging (HTTP code, response body, timestamps)
- Flags de status permitem rastreamento e retry de falhas na geração de links

---

### Push Notifications (`desi-pet-shower-push_addon`)

**Diretório**: `plugins/desi-pet-shower-push`

**Propósito e funcionalidades principais**:
- Enviar resumo diário de agendamentos para equipe administrativa
- Enviar relatório financeiro diário com atendimentos e transações
- Enviar relatório semanal de pets inativos (sem atendimento há 30 dias)
- Integrar com e-mail (via `wp_mail()`) e Telegram Bot API
- Horários e dias configuráveis para cada tipo de notificação

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:

| Option | Tipo | Descrição |
|--------|------|-----------|
| `dps_push_emails_agenda` | array | Lista de emails para agenda diária |
| `dps_push_emails_report` | array | Lista de emails para relatório financeiro |
| `dps_push_agenda_time` | string | Horário do resumo de agendamentos (HH:MM) |
| `dps_push_report_time` | string | Horário do relatório financeiro (HH:MM) |
| `dps_push_weekly_day` | string | Dia da semana para relatório semanal (english) |
| `dps_push_weekly_time` | string | Horário do relatório semanal (HH:MM) |
| `dps_push_inactive_days` | int | Dias de inatividade para considerar pet inativo (padrão: 30) |
| `dps_push_agenda_enabled` | bool | Ativar/desativar agenda diária |
| `dps_push_report_enabled` | bool | Ativar/desativar relatório financeiro |
| `dps_push_weekly_enabled` | bool | Ativar/desativar relatório semanal |
| `dps_push_telegram_token` | string | Token do bot do Telegram |
| `dps_push_telegram_chat` | string | ID do chat/grupo Telegram |

**Menus administrativos**:
- **Notificações** (`dps-push-notifications`): configurações de destinatários, horários e integração Telegram

**Hooks consumidos**:
- Nenhum hook do sistema de configurações (usa menu admin próprio)

**Hooks disparados**:

| Hook | Tipo | Parâmetros | Descrição |
|------|------|------------|-----------|
| `dps_send_agenda_notification` | cron | - | Dispara envio da agenda diária |
| `dps_send_daily_report` | cron | - | Dispara envio do relatório financeiro |
| `dps_send_weekly_inactive_report` | cron | - | Dispara envio do relatório de pets inativos |
| `dps_send_push_notification` | action | `$message`, `$context` | Permite add-ons enviarem notificações via Telegram |
| `dps_push_notification_content` | filter | `$content`, `$appointments` | Filtra conteúdo do email antes de enviar |
| `dps_push_notification_recipients` | filter | `$recipients` | Filtra destinatários da agenda diária |
| `dps_daily_report_recipients` | filter | `$recipients` | Filtra destinatários do relatório financeiro |
| `dps_daily_report_content` | filter | `$content`, `$appointments`, `$trans` | Filtra conteúdo do relatório |
| `dps_daily_report_html` | filter | `$html`, `$appointments`, `$trans` | Filtra HTML do relatório |
| `dps_weekly_inactive_report_recipients` | filter | `$recipients` | Filtra destinatários do relatório semanal |

**Dependências**:
- **Obrigatória**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Opcional**: Finance Add-on (para relatório financeiro com tabela `dps_transacoes`)

**Introduzido em**: v0.1.0 (estimado)

**Versão atual**: 1.2.0

**Observações**:
- Implementa `register_deactivation_hook` corretamente para limpar cron jobs
- Usa timezone do WordPress para agendamentos (`get_option('timezone_string')`)
- Emails enviados em formato HTML com headers `Content-Type: text/html; charset=UTF-8`
- Integração Telegram envia mensagens em texto plano com `parse_mode` HTML
- Threshold de inatividade configurável via interface admin (padrão: 30 dias)
- Interface administrativa integrada na página de Notificações sob menu desi.pet by PRObst
- **v1.2.0**: Menu admin visível, botões de teste para relatórios e Telegram, uninstall.php atualizado

**Análise completa**: Consulte `docs/analysis/PUSH_ADDON_ANALYSIS.md` para análise detalhada de código, funcionalidades e melhorias propostas

---

### Cadastro Público (`desi-pet-shower-registration_addon`)

**Diretório**: `plugins/desi-pet-shower-registration`

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

### Frontend (`desi-pet-shower-frontend`)

**Diretório**: `plugins/desi-pet-shower-frontend`

**Propósito e funcionalidades principais**:
- Consolidar experiências frontend (cadastro, agendamento, configurações) em add-on modular
- Arquitetura com módulos independentes, feature flags e camada de compatibilidade
- Rollout controlado: cada módulo pode ser habilitado/desabilitado individualmente
- **[Fase 2]** Módulo Registration operacional em dual-run com o add-on legado
- **[Fase 3]** Módulo Booking operacional em dual-run com o add-on legado
- **[Fase 4]** Módulo Settings integrado ao sistema de abas de configurações
- **[Fase 7.1]** Preparação: abstracts, template engine, hook bridges, componentes M3, flags v2
- **[Fase 7.2]** Registration V2: formulário nativo 100% independente do legado (cadastro + pets + reCAPTCHA + email confirmation)
- **[Fase 7.3]** Booking V2: wizard nativo 5-step 100% independente do legado (cliente → pets → serviços → data/hora → confirmação + extras TaxiDog/Tosa)

**Shortcodes expostos**:
- `dps_registration_form` — quando flag `registration` ativada, o módulo assume o shortcode (wrapper sobre o legado com surface M3)
- `dps_booking_form` — quando flag `booking` ativada, o módulo assume o shortcode (wrapper sobre o legado com surface M3)
- `dps_registration_v2` — quando flag `registration_v2` ativada, formulário nativo M3 (100% independente do legado)
- `dps_booking_v2` — quando flag `booking_v2` ativada, wizard nativo M3 de 5 steps (100% independente do legado)

**CPTs, tabelas e opções**:
- Option: `dps_frontend_feature_flags` — controle de rollout por módulo (flags: `registration`, `booking`, `settings`, `registration_v2`, `booking_v2`)
- Option: `dps_frontend_usage_counters` — contadores de telemetria por módulo
- Transient: `dps_booking_confirmation_{user_id}` — confirmação de agendamento v2 (TTL 5min)

**Hooks consumidos** (Fase 2 — módulo Registration v1 dual-run):
- `dps_registration_after_fields` (preservado — consumido pelo Loyalty)
- `dps_registration_after_client_created` (preservado — consumido pelo Loyalty)
- `dps_registration_spam_check` (preservado)
- `dps_registration_agenda_url` (preservado)

**Hooks consumidos** (Fase 3 — módulo Booking v1 dual-run):
- `dps_base_after_save_appointment` (preservado — consumido por stock, payment, groomers, calendar, communications, push, services e booking)
- `dps_base_appointment_fields` (preservado)
- `dps_base_appointment_assignment_fields` (preservado)

**Hooks consumidos** (Fase 4 — módulo Settings):
- `dps_settings_register_tabs` — registra aba "Frontend" via `DPS_Settings_Frontend::register_tab()`
- `dps_settings_save_save_frontend` — processa salvamento das feature flags

**Hooks disparados** (Fase 7 — módulos nativos V2):
- `dps_registration_v2_before_render` — antes de renderizar formulário de cadastro v2
- `dps_registration_v2_after_render` — após renderizar formulário de cadastro v2
- `dps_registration_v2_client_created` — após criar cliente via v2 (bridge: dispara hooks legados do Loyalty primeiro)
- `dps_registration_v2_pet_created` — após criar pet via v2
- `dps_registration_spam_check` — filtro anti-spam (reusa hook legado via bridge)
- `dps_booking_v2_before_render` — antes de renderizar wizard de booking v2
- `dps_booking_v2_step_render` — ao renderizar step do wizard
- `dps_booking_v2_step_validate` — filtro de validação por step
- `dps_booking_v2_before_process` — antes de criar agendamento v2
- `dps_booking_v2_after_process` — após processar agendamento v2
- `dps_booking_v2_appointment_created` — após criar agendamento v2

**Hooks de bridge** (Fase 7 — CRÍTICO: legado PRIMEIRO, v2 DEPOIS):
- `dps_base_after_save_appointment` — 8 consumidores: Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
- `dps_base_appointment_fields` — Services: injeção de campos
- `dps_base_appointment_assignment_fields` — Groomers: campos de atribuição
- `dps_registration_after_client_created` — Loyalty: código de indicação

**AJAX endpoints** (Fase 7.3 — Booking V2):
- `wp_ajax_dps_booking_search_client` — busca cliente por telefone (nonce + capability)
- `wp_ajax_dps_booking_get_pets` — lista pets do cliente com paginação (nonce + capability)
- `wp_ajax_dps_booking_get_services` — serviços ativos com preços por porte (nonce + capability)
- `wp_ajax_dps_booking_get_slots` — horários livres 08:00-18:00/30min (nonce + capability)
- `wp_ajax_dps_booking_validate_step` — validação server-side por step (nonce + capability)

**Dependências**:
- Depende do plugin base (DPS_Base_Plugin + design tokens CSS)
- Módulo Registration v1 depende de `DPS_Registration_Addon` (add-on legado) para dual-run
- Módulo Booking v1 depende de `DPS_Booking_Addon` (add-on legado) para dual-run
- Módulos V2 nativos (Registration V2, Booking V2) são 100% independentes dos add-ons legados
- Módulo Settings depende de `DPS_Settings_Frontend` (sistema de abas do base)

**Arquitetura interna**:
- `DPS_Frontend_Addon` — orquestrador com injeção de dependências
- `DPS_Frontend_Module_Registry` — registro e boot de módulos
- `DPS_Frontend_Feature_Flags` — controle de rollout persistido
- `DPS_Frontend_Compatibility` — bridges para legado
- `DPS_Frontend_Assets` — enqueue condicional M3 Expressive
- `DPS_Frontend_Logger` — observabilidade via error_log + telemetria batch
- `DPS_Frontend_Request_Guard` — segurança centralizada (nonce, capability, sanitização)
- `DPS_Template_Engine` — renderização com suporte a override via tema (dps-templates/)
- `DPS_Frontend_Registration_Module` — v1 dual-run: assume shortcode, delega lógica ao legado
- `DPS_Frontend_Booking_Module` — v1 dual-run: assume shortcode, delega lógica ao legado
- `DPS_Frontend_Settings_Module` — registra aba de configurações com controles de feature flags
- `DPS_Frontend_Registration_V2_Module` — v2 nativo: shortcode `[dps_registration_v2]`, handler, services
- `DPS_Frontend_Booking_V2_Module` — v2 nativo: shortcode `[dps_booking_v2]`, handler, services, AJAX
- `DPS_Registration_Hook_Bridge` — compatibilidade v1/v2 Registration (legado primeiro, v2 depois)
- `DPS_Booking_Hook_Bridge` — compatibilidade v1/v2 Booking (legado primeiro, v2 depois)

**Classes de negócio — Registration V2** (Fase 7.2):
- `DPS_Registration_Handler` — pipeline: reCAPTCHA → anti-spam → validação → duplicata → criar cliente → hooks Loyalty → criar pets → email confirmação
- `DPS_Form_Validator` — validação de formulário (nome, email, telefone, CPF, pets)
- `DPS_Cpf_Validator` — validação CPF mod-11
- `DPS_Client_Service` — CRUD para `dps_cliente` (13+ metas)
- `DPS_Pet_Service` — CRUD para `dps_pet`
- `DPS_Breed_Provider` — dataset de raças por espécie (cão: 44, gato: 20)
- `DPS_Duplicate_Detector` — detecção por telefone com override admin
- `DPS_Recaptcha_Service` — verificação reCAPTCHA v3
- `DPS_Email_Confirmation_Service` — token UUID 48h + envio

**Classes de negócio — Booking V2** (Fase 7.3):
- `DPS_Booking_Handler` — pipeline: validação → extras → criar appointment → confirmação transient → hook bridge (8 add-ons)
- `DPS_Booking_Validator` — validação multi-step (5 steps) + extras (TaxiDog/Tosa)
- `DPS_Appointment_Service` — CRUD para `dps_agendamento` (16+ metas, conflitos, busca por cliente)
- `DPS_Booking_Confirmation_Service` — transient de confirmação (5min TTL)
- `DPS_Booking_Ajax` — 5 endpoints AJAX (busca cliente, pets, serviços, slots, validação)

**Estratégia de compatibilidade (Fases 2–4)**:
- Intervenção mínima: o legado continua processando formulário, emails, REST, AJAX, settings e cron
- Módulos de shortcode assumem o shortcode (envolve output na `.dps-frontend` surface) e adicionam CSS extra
- Módulo de settings registra aba via API moderna `register_tab()` sem alterar abas existentes
- Rollback: desabilitar flag do módulo restaura comportamento 100% legado

**Coexistência v1/v2** (Fase 7):
- Shortcodes v1 (`[dps_registration_form]`, `[dps_booking_form]`) e v2 (`[dps_registration_v2]`, `[dps_booking_v2]`) podem estar ativos simultaneamente
- Feature flags independentes: `registration` (v1), `registration_v2` (v2), `booking` (v1), `booking_v2` (v2)
- Hook bridge garante compatibilidade: hooks legados disparam PRIMEIRO, hooks v2 DEPOIS
- Rollback instantâneo via toggle de flag — sem perda de dados

**Introduzido em**: v1.0.0 (Fases 1–6), v2.0.0 (Fase 7.1), v2.1.0 (Fase 7.2), v2.2.0 (Fase 7.3), v2.3.0 (Fase 7.4), v2.4.0 (Fase 7.5)

**Documentação operacional (Fase 5)**:
- `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` — guia de ativação por ambiente
- `docs/implementation/FRONTEND_RUNBOOK.md` — diagnóstico e rollback de incidentes
- `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` — matriz de compatibilidade com todos os add-ons
- `docs/qa/FRONTEND_REMOVAL_READINESS.md` — checklist de prontidão para remoção futura

**Documentação de governança (Fase 6)**:
- `docs/refactoring/FRONTEND_DEPRECATION_POLICY.md` — política de depreciação (janela mínima 180 dias, processo de comunicação, critérios de aceite)
- `docs/refactoring/FRONTEND_REMOVAL_TARGETS.md` — lista de alvos com risco, dependências e esforço (booking 🟢 baixo; registration 🟡 médio)
- Telemetria de uso: contadores por módulo via `dps_frontend_usage_counters`, exibidos na aba Settings

**Documentação de implementação nativa (Fase 7)**:
- `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` — plano completo com inventário legado, hook bridge, templates, estratégia de migração

**Documentação de coexistência e migração (Fase 7.4)**:
- `docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md` — guia passo a passo de migração v1→v2 (7 etapas, comparação de features, checklist, rollback, troubleshooting, WP-CLI)
- Seção "Status de Coexistência v1/v2" na aba Settings com indicadores visuais por módulo

**Observações**:
- PHP 8.4 moderno: constructor promotion, readonly properties, typed properties, return types
- Sem singletons: objetos montados por composição no bootstrap
- Assets carregados somente quando ao menos um módulo está habilitado (feature flag)
- Roadmap completo em `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md`

---

### Serviços (`desi-pet-shower-services_addon`)

**Diretório**: `plugins/desi-pet-shower-services`

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

**Diretório**: `plugins/desi-pet-shower-stats`

**Propósito e funcionalidades principais**:
- Exibir métricas de uso do sistema (atendimentos, receita, despesas, lucro)
- Listar serviços mais recorrentes com gráfico de barras (Chart.js)
- Filtrar estatísticas por período personalizado
- Exibir pets inativos com link de reengajamento via WhatsApp
- Métricas de assinaturas (ativas, pendentes, receita, valor em aberto)
- Sistema de cache via transients (1h para financeiros, 24h para inativos)

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:
- Não cria CPTs ou tabelas próprias
- Consulta `dps_transacoes` para métricas financeiras
- Consulta CPTs do núcleo: `dps_agendamento`, `dps_cliente`, `dps_pet`, `dps_subscription`, `dps_service`
- Transients criados: `dps_stats_total_revenue_*`, `dps_stats_financial_*`, `dps_stats_appointments_*`, `dps_stats_inactive_*`

**Hooks consumidos**:
- `dps_base_nav_tabs_after_history` (prioridade 20): adiciona aba "Estatísticas"
- `dps_base_sections_after_history` (prioridade 20): renderiza dashboard de estatísticas
- `admin_post_dps_clear_stats_cache`: processa limpeza de cache

**Hooks disparados**: Nenhum

**Funções globais expostas**:
- `dps_get_total_revenue( $start_date, $end_date )`: retorna receita total paga no período
- `dps_stats_build_cache_key( $prefix, $start, $end )`: gera chave de cache única
- `dps_stats_clear_cache()`: limpa todos os transients de estatísticas (requer capability `manage_options`)

**Dependências**:
- **Obrigatória**: Plugin base DPS (verifica `DPS_Base_Plugin`)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e métricas financeiras)
- **Opcional**: Services Add-on (para títulos de serviços no ranking)
- **Opcional**: Subscription Add-on (para métricas de assinaturas)
- **Opcional**: DPS_WhatsApp_Helper (para links de reengajamento)

**Introduzido em**: v0.1.0 (estimado)

**Versão atual**: 1.0.0

**Observações**:
- Arquivo único de ~600 linhas; candidato a refatoração modular futura
- Usa Chart.js (CDN) para gráfico de barras de serviços
- Cache de 1 hora para métricas financeiras, 24 horas para entidades inativas
- Limites de segurança: 500 clientes e 1000 agendamentos por consulta
- Coleta dados de espécies/raças/média por cliente mas não exibe (oportunidade de melhoria)

**Análise completa**: Consulte `docs/analysis/STATS_ADDON_ANALYSIS.md` para análise detalhada de código, funcionalidades, segurança, performance, UX e melhorias propostas (38-58h de esforço estimado)

---

### Estoque (`desi-pet-shower-stock_addon`)

**Diretório**: `plugins/desi-pet-shower-stock`

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

**Diretório**: `plugins/desi-pet-shower-subscription`

**Propósito e funcionalidades principais**:
- Gerenciar pacotes mensais de banho e tosa com frequências semanal (4 atendimentos) ou quinzenal (2 atendimentos)
- Gerar automaticamente os agendamentos do ciclo vinculados à assinatura
- Criar e sincronizar transações financeiras na tabela `dps_transacoes`
- Controlar status de pagamento (pendente, pago, em atraso) por ciclo
- Gerar links de renovação via API do Mercado Pago
- Enviar mensagens de cobrança via WhatsApp usando `DPS_WhatsApp_Helper`

**Shortcodes expostos**: Nenhum

**CPTs, tabelas e opções**:

**CPT `dps_subscription`** (show_ui: false, opera via aba no painel base):

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `subscription_client_id` | int | ID do cliente (`dps_cliente`) |
| `subscription_pet_id` | int | ID do pet (`dps_pet`) |
| `subscription_service` | string | "Banho" ou "Banho e Tosa" |
| `subscription_frequency` | string | "semanal" (4 atendimentos) ou "quinzenal" (2 atendimentos) |
| `subscription_price` | float | Valor do pacote mensal |
| `subscription_start_date` | date | Data de início do ciclo (Y-m-d) |
| `subscription_start_time` | time | Horário dos atendimentos (H:i) |
| `subscription_payment_status` | string | "pendente", "pago" ou "em_atraso" |
| `dps_subscription_payment_link` | url | Cache do link de pagamento Mercado Pago |
| `dps_generated_cycle_YYYY-mm` | bool | Flag indicando ciclo já gerado (evita duplicação) |
| `dps_cycle_status_YYYY-mm` | string | Status de pagamento do ciclo específico |

**Metadados em agendamentos vinculados** (`dps_agendamento`):

| Meta Key | Tipo | Descrição |
|----------|------|-----------|
| `subscription_id` | int | ID da assinatura vinculada |
| `subscription_cycle` | string | Ciclo no formato Y-m (ex: "2025-12") |

**Options armazenadas**: Nenhuma (usa credenciais do Payment Add-on via `DPS_MercadoPago_Config`)

**Hooks consumidos**:
- `dps_base_nav_tabs_after_pets`: Adiciona aba "Assinaturas" no painel (prioridade 20)
- `dps_base_sections_after_pets`: Renderiza seção de assinaturas (prioridade 20)
- Usa `DPS_MercadoPago_Config::get_access_token()` do Payment Add-on v1.1.0+ (ou options legadas se v1.0.0)

**Hooks disparados**:
- `dps_subscription_payment_status` (action): Permite add-ons de pagamento atualizar status do ciclo
  - **Assinatura**: `do_action( 'dps_subscription_payment_status', int $sub_id, string $cycle_key, string $status )`
  - **Parâmetros**:
    - `$sub_id`: ID da assinatura
    - `$cycle_key`: Ciclo no formato Y-m (ex: "2025-12"), vazio usa ciclo atual
    - `$status`: "paid", "approved", "success" → pago | "failed", "rejected" → em_atraso | outros → pendente
  - **Exemplo de uso**: `do_action( 'dps_subscription_payment_status', 123, '2025-12', 'paid' );`
- `dps_subscription_whatsapp_message` (filter): Permite customizar mensagem de cobrança via WhatsApp
  - **Assinatura**: `apply_filters( 'dps_subscription_whatsapp_message', string $message, WP_Post $subscription, string $payment_link )`

**Fluxo de geração de agendamentos**:
1. Admin salva assinatura com cliente, pet, serviço, frequência, valor, data/hora
2. Sistema calcula datas: semanal = 4 datas (+7 dias cada), quinzenal = 2 datas (+14 dias cada)
3. Remove agendamentos existentes do mesmo ciclo (evita duplicação)
4. Cria novos `dps_agendamento` com metas vinculadas
5. Marca ciclo como gerado (`dps_generated_cycle_YYYY-mm`)
6. Cria/atualiza transação em `dps_transacoes` via Finance Add-on

**Fluxo de renovação**:
1. Quando todos os atendimentos do ciclo são finalizados, botão "Renovar" aparece
2. Admin clica em "Renovar"
3. Sistema avança `subscription_start_date` para próximo mês (mesmo dia da semana)
4. Reseta `subscription_payment_status` para "pendente"
5. Gera novos agendamentos para o novo ciclo
6. Cria nova transação financeira

**Dependências**:
- **Obrigatória**: Plugin base DPS (verifica `DPS_Base_Plugin` na inicialização)
- **Recomendada**: Finance Add-on (para tabela `dps_transacoes` e sincronização de cobranças)
- **Recomendada**: Payment Add-on (para geração de links Mercado Pago via API)
- **Opcional**: Communications Add-on (para mensagens via WhatsApp)

**Introduzido em**: v0.2.0

**Versão atual**: 1.0.0

**Observações**:
- Arquivo único de 995 linhas; candidato a refatoração futura para padrão modular (`includes/`, `assets/`, `templates/`)
- CSS e JavaScript inline na função `section_subscriptions()`; recomenda-se extrair para arquivos externos
- Usa `DPS_WhatsApp_Helper::get_link_to_client()` para links de cobrança (desde v1.0.0)
- Cancela assinatura via `wp_trash_post()` (soft delete), preservando dados para possível restauração
- Exclusão permanente remove assinatura E todas as transações financeiras vinculadas
- Geração de links Mercado Pago usa `external_reference` no formato `dps_subscription_{ID}` para rastreamento via webhook

**Análise completa**: Consulte `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md` para análise detalhada de código, funcionalidades e melhorias propostas (32KB, 10 seções)

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

- **`dps_settings_nav_tabs`** (action) **DEPRECIADO desde v2.5.0**
  - **Parâmetros**: nenhum
  - **Propósito**: (depreciado) adicionar abas de navegação na página de configurações
  - **Migração**: usar `DPS_Settings_Frontend::register_tab()` em vez deste hook
  - **Nota**: As abas de configuração agora são registradas no sistema moderno via `register_tab()` em `DPS_Settings_Frontend::register_core_tabs()`. Add-ons devem migrar para o novo sistema.

- **`dps_settings_sections`** (action) **DEPRECIADO desde v2.5.0**
  - **Parâmetros**: nenhum
  - **Propósito**: (depreciado) renderizar conteúdo de seções na página de configurações
  - **Migração**: usar `DPS_Settings_Frontend::register_tab()` com callback que renderiza o conteúdo
  - **Nota**: O sistema moderno de abas já renderiza automaticamente o conteúdo via callbacks registrados.

#### Página de detalhes do cliente

- **`dps_client_page_header_badges`** (action) (desde v1.3.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post)
  - **Propósito**: adicionar badges ao lado do nome do cliente (ex: nível de fidelidade, tags)
  - **Consumido por**: Add-ons de fidelidade para mostrar nível/status
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_badges', function( $client_id, $client ) {
        echo '<span class="dps-badge dps-badge--gold">⭐ VIP</span>';
    }, 10, 2 );
    ```

- **`dps_client_page_header_actions`** (action) (desde v1.1.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post), `$base_url` (string)
  - **Propósito**: adicionar botões de ação ao painel de ações rápidas da página de detalhes do cliente
  - **Atualização v1.3.0**: movido para painel dedicado "Ações Rápidas" com melhor organização visual
  - **Consumido por**: Client Portal (link de atualização de perfil), Tosa Consent (link de consentimento)
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_header_actions', function( $client_id, $client, $base_url ) {
        echo '<button class="dps-btn-action">Minha Ação</button>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_personal_section`** (action) (desde v1.2.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **Propósito**: adicionar seções personalizadas após os dados pessoais do cliente
  - **Consumido por**: Add-ons que precisam exibir informações complementares
  - **Exemplo**:
    ```php
    add_action( 'dps_client_page_after_personal_section', function( $client_id, $client, $meta ) {
        echo '<div class="dps-client-section"><!-- Conteúdo personalizado --></div>';
    }, 10, 3 );
    ```

- **`dps_client_page_after_contact_section`** (action) (desde v1.2.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post), `$meta` (array)
  - **Propósito**: adicionar seções após contato e redes sociais
  - **Consumido por**: Add-ons de fidelidade, comunicações avançadas

- **`dps_client_page_after_pets_section`** (action) (desde v1.2.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post), `$pets` (array)
  - **Propósito**: adicionar seções após a lista de pets do cliente
  - **Consumido por**: Add-ons de assinaturas, pacotes de serviços

- **`dps_client_page_after_appointments_section`** (action) (desde v1.2.0)
  - **Parâmetros**: `$client_id` (int), `$client` (WP_Post), `$appointments` (array)
  - **Propósito**: adicionar seções após o histórico de atendimentos
  - **Consumido por**: Add-ons financeiros, estatísticas avançadas

#### Fluxo de agendamentos

- **`dps_base_appointment_fields`** (action)
  - **Parâmetros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **Propósito**: adicionar campos customizados ao formulário de agendamento (seção "Serviços e Extras")
  - **Consumido por**: Serviços (seleção de serviços e extras)

- **`dps_base_appointment_assignment_fields`** (action) (desde v1.8.0)
  - **Parâmetros**: `$appointment_id` (int, pode ser 0 para novos agendamentos), `$meta` (array)
  - **Propósito**: adicionar campos de atribuição de profissionais ao formulário de agendamento (seção "Atribuição")
  - **Consumido por**: Groomers (seleção de profissionais responsáveis)
  - **Nota**: Esta seção só é renderizada se houver hooks registrados

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
plugins/desi-pet-shower-NOME_addon/
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
- `backup_addon`: 1338 linhas em um único arquivo (análise em `docs/analysis/BACKUP_ADDON_ANALYSIS.md`)
- `loyalty_addon`: 1148 linhas em um único arquivo
- `subscription_addon`: 995 linhas em um único arquivo (análise em `docs/analysis/SUBSCRIPTION_ADDON_ANALYSIS.md`)
- `registration_addon`: 636 linhas em um único arquivo
- `stats_addon`: 538 linhas em um único arquivo
- `groomers_addon`: 473 linhas em um único arquivo (análise em `docs/analysis/GROOMERS_ADDON_ANALYSIS.md`)
- `stock_addon`: 432 linhas em um único arquivo (análise em `docs/analysis/STOCK_ADDON_ANALYSIS.md`)

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

## Add-on: White Label (Personalização de Marca)

**Diretório**: `plugins/desi-pet-shower-whitelabel_addon/`

**Versão**: 1.0.0

**Propósito**: Personalize o sistema DPS com sua própria marca, cores, logo, SMTP customizado e controles de acesso. Ideal para agências e revendedores que desejam oferecer o DPS sob sua própria identidade visual.

### Funcionalidades Principais

1. **Branding e Identidade Visual**
   - Logo customizada (versões clara e escura)
   - Favicon personalizado
   - Paleta de cores (primária, secundária, accent, background, texto)
   - Nome da marca e tagline
   - Informações de contato (email, telefone, WhatsApp, URL de suporte)
   - URLs customizadas (website, documentação, termos de uso, privacidade)
   - Footer customizado
   - CSS customizado para ajustes visuais finos
   - Opção de ocultar links "Powered by" e links do autor

2. **Página de Login Personalizada**
   - Logo customizada com dimensões configuráveis
   - Background (cor sólida, imagem ou gradiente)
   - Formulário de login com largura, cor de fundo e bordas customizáveis
   - Botão de login com cores personalizadas
   - Mensagem customizada acima do formulário
   - Footer text customizado
   - CSS adicional para ajustes finos
   - Opção de ocultar links de registro e recuperação de senha

3. **Modo de Manutenção**
   - Bloqueia acesso ao site para visitantes (HTTP 503)
   - Bypass configurável por roles WordPress (padrão: administrator)
   - Página de manutenção customizada com logo, título e mensagem
   - Background e cores de texto configuráveis
   - Countdown timer opcional para previsão de retorno
   - Indicador visual na admin bar quando modo manutenção está ativo
   - Preserva acesso a wp-admin, wp-login e AJAX

4. **Personalização da Admin Bar**
   - Ocultar itens específicos da admin bar
   - Customizar logo e links
   - Remover menus do WordPress que não sejam relevantes

5. **SMTP Customizado**
   - Configuração de servidor SMTP próprio
   - Autenticação segura
   - Teste de envio de e-mail
   - Suporte a TLS/SSL

6. **Assets e Estilos**
   - Carregamento condicional de assets apenas nas páginas relevantes
   - WordPress Color Picker integrado
   - WordPress Media Uploader para upload de logos
   - Interface responsiva e intuitiva

### Estrutura de Arquivos

```
desi-pet-shower-whitelabel_addon/
├── desi-pet-shower-whitelabel-addon.php (orquestração principal)
├── includes/
│   ├── class-dps-whitelabel-settings.php (branding e configurações gerais)
│   ├── class-dps-whitelabel-branding.php (aplicação de branding no site)
│   ├── class-dps-whitelabel-assets.php (gerenciamento de assets CSS/JS)
│   ├── class-dps-whitelabel-smtp.php (SMTP customizado)
│   ├── class-dps-whitelabel-login-page.php (página de login personalizada)
│   ├── class-dps-whitelabel-admin-bar.php (personalização da admin bar)
│   └── class-dps-whitelabel-maintenance.php (modo de manutenção)
├── assets/
│   ├── css/
│   │   └── whitelabel-admin.css (estilos da interface admin)
│   └── js/
│       └── whitelabel-admin.js (JavaScript para color picker, media uploader)
├── templates/
│   ├── admin-settings.php (interface de configuração com abas)
│   └── maintenance.php (template da página de manutenção)
├── languages/ (arquivos de tradução pt_BR)
└── uninstall.php (limpeza ao desinstalar)
```

### Hooks Utilizados

**Do WordPress:**
- `init` (prioridade 1) - Carrega text domain
- `init` (prioridade 5) - Inicializa classes do add-on
- `admin_menu` (prioridade 20) - Registra menu admin
- `admin_enqueue_scripts` - Carrega assets admin
- `template_redirect` (prioridade 1) - Intercepta requisições para modo manutenção
- `login_enqueue_scripts` - Aplica estilos customizados na página de login
- `login_headerurl` - Customiza URL do logo de login
- `login_headertext` - Customiza texto alternativo do logo
- `login_footer` - Adiciona footer customizado no login
- `login_message` - Adiciona mensagem customizada no login
- `admin_bar_menu` (prioridade 100) - Adiciona indicadores visuais na admin bar

**Hooks Expostos (futuros):**
```php
// Permitir bypass customizado do modo manutenção
apply_filters( 'dps_whitelabel_maintenance_can_access', false, WP_User $user );

// Customizar template da página de manutenção
apply_filters( 'dps_whitelabel_maintenance_template', string $template_path );

// Disparado após salvar configurações
do_action( 'dps_whitelabel_settings_saved', array $settings );
```

### Tabelas de Banco de Dados

Nenhuma tabela própria. Todas as configurações são armazenadas como options do WordPress:

**Options criadas:**
- `dps_whitelabel_settings` - Configurações de branding e identidade visual
- `dps_whitelabel_smtp` - Configurações de servidor SMTP
- `dps_whitelabel_login` - Configurações da página de login
- `dps_whitelabel_admin_bar` - Configurações da admin bar
- `dps_whitelabel_maintenance` - Configurações do modo de manutenção

### Interface Administrativa

**Menu Principal:** desi.pet by PRObst → White Label

**Abas de Configuração:**
1. **Branding** - Logo, cores, nome da marca, contatos
2. **SMTP** - Servidor de e-mail customizado
3. **Login** - Personalização da página de login
4. **Admin Bar** - Customização da barra administrativa
5. **Manutenção** - Modo de manutenção e mensagens

**Recursos de UX:**
- Interface com abas para organização clara
- Color pickers para seleção visual de cores
- Media uploader integrado para upload de logos e imagens
- Preview ao vivo de alterações (em desenvolvimento)
- Botão de restaurar padrões
- Mensagens de sucesso/erro após salvamento
- Validação de campos (URLs, cores hexadecimais)

### Segurança

**Validações Implementadas:**
- ✅ Nonce verification em todos os formulários
- ✅ Capability check (`manage_options`) em todas as ações
- ✅ Sanitização rigorosa de inputs:
  - `sanitize_text_field()` para textos
  - `esc_url_raw()` para URLs
  - `sanitize_hex_color()` para cores
  - `sanitize_email()` para e-mails
  - `wp_kses_post()` para HTML permitido
- ✅ Escape de outputs:
  - `esc_html()`, `esc_attr()`, `esc_url()` conforme contexto
- ✅ CSS customizado sanitizado (remove JavaScript, expressions, @import)
- ✅ Administrator sempre incluído nas roles de bypass (não pode ser removido)
- ✅ Validação de extensões de imagem (logo, favicon)

### Compatibilidade

**WordPress:**
- Versão mínima: 6.9
- PHP: 8.4+

**DPS:**
- Requer: Plugin base (`DPS_Base_Plugin`)
- Compatível com todos os add-ons existentes

**Plugins de Terceiros:**
- Compatível com WP Mail SMTP (prioriza configuração do White Label)
- Compatível com temas page builders (YooTheme, Elementor)
- Não conflita com plugins de cache (assets condicionais)

### Análise Detalhada de Novas Funcionalidades

Para análise completa sobre a implementação de **Controle de Acesso ao Site**, incluindo:
- Bloqueio de acesso para visitantes não autenticados
- Lista de exceções de páginas públicas
- Redirecionamento para login customizado
- Controle por role WordPress
- Funcionalidades adicionais sugeridas (controle por CPT, horário, IP, logs)

Consulte a seção **White Label (`desi-pet-shower-whitelabel_addon`)** neste arquivo para o detalhamento funcional e recomendações

### Limitações Conhecidas

- Modo de manutenção bloqueia TODO o site (não permite exceções por página)
- Não há controle granular de acesso (apenas modo manutenção "tudo ou nada")
- CSS customizado não tem preview ao vivo (requer salvamento para visualizar)
- Assets admin carregados mesmo fora da página de configurações (otimização pendente)
- Falta integração com plugins de two-factor authentication

### Próximos Passos Recomendados (Roadmap)

**v1.1.0 - Controle de Acesso ao Site** (ALTA PRIORIDADE)
- Implementar classe `DPS_WhiteLabel_Access_Control`
- Permitir bloqueio de acesso para visitantes não autenticados
- Lista de exceções de URLs (com suporte a wildcards)
- Redirecionamento inteligente para login com preservação de URL original
- Controle por role WordPress
- Indicador visual na admin bar quando ativo

**v1.2.0 - Melhorias de Interface** (MÉDIA PRIORIDADE)
- Preview ao vivo de alterações de cores
- Editor visual de CSS com syntax highlighting
- Upload de múltiplos logos para diferentes contextos
- Galeria de presets de cores e layouts

**v1.3.0 - Recursos Avançados** (BAIXA PRIORIDADE)
- Logs de acesso e auditoria
- Controle de acesso por CPT
- Redirecionamento baseado em role
- Integração com 2FA
- Rate limiting anti-bot

### Changelog

**v1.0.0** - 2025-12-06 - Lançamento Inicial
- Branding completo (logo, cores, nome da marca)
- Página de login personalizada
- Modo de manutenção com bypass por roles
- Personalização da admin bar
- SMTP customizado
- Interface administrativa com abas
- Suporte a i18n (pt_BR)
- Documentação completa

---

## Add-on: AI (Assistente Virtual)

**Diretório**: `plugins/desi-pet-shower-ai/`

**Versão**: 1.6.0 (schema DB: 1.5.0)

**Propósito**: Assistente virtual inteligente para o Portal do Cliente, chat público para visitantes, e geração de sugestões de comunicações (WhatsApp e e-mail). Inclui analytics e base de conhecimento.

### Funcionalidades Principais

1. **Portal do Cliente**
   - Widget de chat para clientes fazerem perguntas sobre agendamentos, serviços, histórico
   - Respostas contextualizadas baseadas em dados reais do cliente e pets
   - Escopo restrito a assuntos relacionados a Banho e Tosa

2. **Chat Público** (v1.6.0+)
   - Shortcode `[dps_ai_public_chat]` para visitantes não autenticados
   - Modos inline e floating, temas light/dark
   - FAQs customizáveis, rate limiting por IP
   - Integração com base de conhecimento

3. **Assistente de Comunicações** (v1.2.0+)
   - Gera sugestões de mensagens para WhatsApp
   - Gera sugestões de e-mail (assunto e corpo)
   - **NUNCA envia automaticamente** - apenas sugere textos para revisão humana

4. **Analytics e Feedback** (v1.5.0+)
   - Métricas de uso (perguntas, tokens, erros, tempo de resposta)
   - Feedback positivo/negativo com comentários
   - Dashboard administrativo de analytics
   - Base de conhecimento (CPT `dps_ai_knowledge`)

5. **Agendamento via Chat** (v1.5.0+)
   - Integração com Agenda Add-on
   - Sugestão de horários disponíveis
   - Modos: request (solicita agendamento) e direct (agenda diretamente)

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
        subject: 'Lembrete de Agendamento - desi.pet by PRObst',
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

- **Manual completo**: `plugins/desi-pet-shower-ai/AI_COMMUNICATIONS.md`
- **Exemplos de código**: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`
- **Comportamento da IA**: `plugins/desi-pet-shower-ai/BEHAVIOR_EXAMPLES.md`

### Hooks Expostos

Atualmente nenhum hook específico de comunicações. Possíveis hooks futuros:

```php
// Filtro antes de gerar sugestão
$context = apply_filters( 'dps_ai_comm_whatsapp_context', $context, $type );

// Filtro após gerar sugestão (permite pós-processamento)
$message = apply_filters( 'dps_ai_comm_whatsapp_message', $message, $context );
```

### Tabelas de Banco de Dados

**Desde v1.5.0**, o AI Add-on mantém 2 tabelas customizadas para analytics e feedback.
**Desde v1.7.0**, foram adicionadas 2 tabelas para histórico de conversas persistente.

#### `wp_dps_ai_conversations` (desde v1.7.0)

Armazena metadados de conversas com o assistente de IA em múltiplos canais.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_conversations (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
    channel VARCHAR(50) NOT NULL DEFAULT 'web_chat',
    session_identifier VARCHAR(255) DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customer_idx (customer_id),
    KEY channel_idx (channel),
    KEY session_idx (session_identifier),
    KEY status_idx (status),
    KEY last_activity_idx (last_activity_at)
);
```

**Propósito:**
- Rastrear conversas em múltiplos canais: `web_chat` (público), `portal`, `whatsapp`, `admin_specialist`
- Identificar usuários logados via `customer_id` ou visitantes via `session_identifier`
- Agrupar mensagens relacionadas em conversas contextuais
- Analisar padrões de uso por canal
- Suportar histórico de conversas para futuras funcionalidades (ex: "rever conversas anteriores")

#### `wp_dps_ai_messages` (desde v1.7.0)

Armazena mensagens individuais de conversas.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_messages (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT(20) UNSIGNED NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_identifier VARCHAR(255) DEFAULT NULL,
    message_text TEXT NOT NULL,
    message_metadata TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_idx (conversation_id),
    KEY sender_type_idx (sender_type),
    KEY created_at_idx (created_at)
);
```

**Campos:**
- `sender_type`: 'user' (cliente/visitante), 'assistant' (IA), 'system' (mensagens do sistema)
- `sender_identifier`: ID do usuário, telefone, IP, etc (opcional)
- `message_metadata`: JSON com dados adicionais (tokens, custo, tempo de resposta, etc)

**Propósito:**
- Histórico completo de interações em ordem cronológica
- Análise de padrões de perguntas e respostas
- Compliance (LGPD/GDPR - exportação de dados pessoais)
- Debugging de problemas de IA
- Base para melhorias futuras (ex: sugestões baseadas em histórico)

**Classe de Acesso:**
- `DPS_AI_Conversations_Repository` em `includes/class-dps-ai-conversations-repository.php`
- Métodos: `create_conversation()`, `add_message()`, `get_conversation()`, `get_messages()`, `list_conversations()`

#### `wp_dps_ai_metrics`

Armazena métricas agregadas de uso da IA por dia e cliente.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_metrics (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    client_id BIGINT(20) UNSIGNED DEFAULT 0,
    questions_count INT(11) UNSIGNED DEFAULT 0,
    tokens_input INT(11) UNSIGNED DEFAULT 0,
    tokens_output INT(11) UNSIGNED DEFAULT 0,
    errors_count INT(11) UNSIGNED DEFAULT 0,
    avg_response_time FLOAT DEFAULT 0,
    model VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY date_client (date, client_id),
    KEY date_idx (date),
    KEY client_idx (client_id)
);
```

**Propósito:**
- Rastrear uso diário da IA (quantidade de perguntas, tokens consumidos)
- Monitorar performance (tempo médio de resposta, taxa de erros)
- Análise de custos e utilização por cliente
- Dados para dashboard de analytics

#### `wp_dps_ai_feedback`

Armazena feedback individual (👍/👎) de cada resposta da IA.

**Estrutura:**
```sql
CREATE TABLE wp_dps_ai_feedback (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT(20) UNSIGNED DEFAULT 0,
    question TEXT,
    answer TEXT,
    feedback ENUM('positive', 'negative') NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY client_idx (client_id),
    KEY feedback_idx (feedback),
    KEY created_at_idx (created_at)
);
```

**Propósito:**
- Coletar feedback de usuários sobre qualidade das respostas
- Identificar padrões de respostas problemáticas
- Melhorar prompts e treinamento da IA
- Análise de satisfação

**Versionamento de Schema:**
- Versão do schema rastreada em opção `dps_ai_db_version`
- Upgrade automático via `dps_ai_maybe_upgrade_database()` em `plugins_loaded`
- v1.5.0: Tabelas `dps_ai_metrics` e `dps_ai_feedback` criadas via `dbDelta()`
- v1.6.0: Tabelas `dps_ai_conversations` e `dps_ai_messages` criadas via `dbDelta()`
- Idempotente: seguro executar múltiplas vezes

**Configurações em `wp_options`:**
- `dps_ai_settings` - Configurações gerais (API key, modelo, temperatura, etc.)
- `dps_ai_db_version` - Versão do schema (desde v1.6.1)

### Limitações Conhecidas

- Depende de conexão com internet e API key válida da OpenAI
- Custo por chamada à API (variável por modelo e tokens)
- Qualidade das sugestões depende da qualidade dos dados fornecidos no contexto
- Não substitui revisão humana - **sempre revisar antes de enviar**
- Assets carregados em todas as páginas admin (TODO: otimizar para carregar apenas onde necessário)

### Exemplos de Uso

Ver arquivo completo de exemplos: `plugins/desi-pet-shower-ai/includes/ai-communications-examples.php`

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

---

## Integrações Futuras Propostas

### Integração com Google Tarefas (Google Tasks API)

**Status:** Proposta de análise (2026-01-19)  
**Documentação:** proposta consolidada nesta seção do `ANALYSIS.md` (ainda sem documento dedicado em `docs/analysis/`)

**Resumo:**
A integração do sistema DPS com Google Tasks API permite sincronizar atividades do sistema (agendamentos, cobranças, mensagens) com listas de tarefas do Google, melhorando a organização e follow-up de atividades administrativas.

**Status:** ✅ VIÁVEL e RECOMENDADO

**Funcionalidades propostas:**
1. **Agendamentos** (Alta Prioridade)
   - Lembretes de agendamentos pendentes (1 dia antes)
   - Follow-ups pós-atendimento (2 dias depois)

2. **Financeiro** (Alta Prioridade)
   - Cobranças pendentes (1 dia antes do vencimento)
   - Renovações de assinatura (5 dias antes)

3. **Portal do Cliente** (Média Prioridade)
   - Mensagens recebidas de clientes (tarefa imediata)

4. **Estoque** (Baixa Prioridade)
   - Alertas de estoque baixo (tarefa de reposição)

**Add-on proposto:** `desi-pet-shower-google-tasks`

**Tipo de sincronização:** Unidirecional (DPS → Google Tasks)
- DPS cria tarefas no Google Tasks
- Google Tasks não modifica dados do DPS
- DPS permanece como "fonte da verdade"

**Esforço estimado:**
- v1.0.0 MVP (OAuth + Agendamentos): 42h (~5.5 dias)
- v1.1.0 (+ Financeiro): 10h (~1.5 dias)
- v1.2.0 (+ Portal + Estoque): 14h (~2 dias)
- v1.3.0 (Testes + Documentação): 21h (~2.5 dias)
- **Total:** 87h (~11 dias úteis)

**Benefícios:**
- Centralização de tarefas em app que equipe já usa
- Notificações nativas do Google (mobile, desktop, email)
- Integração com ecossistema Google (Calendar, Gmail, Android, iOS)
- API gratuita (50.000 requisições/dia)
- Redução de agendamentos esquecidos (-30% esperado)

**Segurança:**
- Autenticação OAuth 2.0
- Tokens criptografados com AES-256
- Dados sensíveis filtráveis (admin escolhe o que incluir)
- LGPD compliance (não envia CPF, RG, telefone completo)

**Próximos passos (se aprovado):**
1. Criar projeto no Google Cloud Console
2. Obter credenciais OAuth 2.0
3. Implementar v1.0.0 MVP
4. Testar com 3-5 pet shops piloto (beta 1 mês)
5. Iterar baseado em feedback
6. Lançamento geral para clientes DPS

**Consulte os documentos completos para:**
- Arquitetura detalhada (classes, hooks, estrutura de dados)
- Casos de uso detalhados (3 cenários reais)
- Requisitos técnicos (APIs, OAuth, configuração Google Cloud)
- Análise de riscos e mitigações
- Métricas de sucesso (KPIs técnicos e de negócio)
- Comparação com alternativas (Microsoft To Do, Todoist, sistema interno)
