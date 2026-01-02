# Análise de Refatoração do Código - desi.pet by PRObst

## Data: 2025-11-21

## Sumário Executivo

Este documento apresenta uma análise detalhada do código do projeto desi.pet by PRObst, identificando:
1. Funções muito grandes ou complexas
2. Nomes de funções, métodos e variáveis pouco descritivos
3. Trechos duplicados que poderiam virar funções reutilizáveis

Para cada caso, são sugeridas versões mais claras, com nomes melhores e, quando possível, quebra em funções menores.

---

## 1. Funções Muito Grandes ou Complexas

### 1.1. `DPS_Base_Frontend::save_appointment()` - 383 linhas

**Localização:** `plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php:1898-2280`

**Problemas:**
- Responsabilidade múltipla: validação, sanitização, cálculos, criação de posts, atualização de metadados
- Lógica condicional complexa para diferentes tipos de agendamentos (simples vs assinatura)
- Difícil de testar e manter

**Sugestão de Refatoração:**

```php
/**
 * Salva ou atualiza um agendamento.
 */
private static function save_appointment() {
    self::verify_appointment_permissions();
    
    $appointment_data = self::sanitize_appointment_form_data();
    
    if ( ! self::validate_appointment_data( $appointment_data ) ) {
        return;
    }
    
    if ( $appointment_data['is_subscription'] && ! $appointment_data['is_editing'] ) {
        self::create_subscription_appointment( $appointment_data );
    } else {
        self::save_simple_or_update_appointment( $appointment_data );
    }
}

/**
 * Verifica permissões para gerenciar agendamentos.
 */
private static function verify_appointment_permissions() {
    if ( ! current_user_can( 'dps_manage_appointments' ) ) {
        wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
    }
}

/**
 * Sanitiza e extrai dados do formulário de agendamento.
 *
 * @return array Dados sanitizados do agendamento.
 */
private static function sanitize_appointment_form_data() {
    $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;
    $pet_ids = self::extract_and_sanitize_pet_ids();
    $appointment_type = isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple';
    
    return [
        'appointment_id' => isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0,
        'client_id' => $client_id,
        'pet_ids' => $pet_ids,
        'pet_id' => ! empty( $pet_ids ) ? $pet_ids[0] : 0,
        'date' => isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '',
        'time' => isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '',
        'notes' => isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '',
        'appointment_type' => $appointment_type,
        'is_subscription' => ( 'subscription' === $appointment_type ),
        'is_editing' => ( isset( $_POST['appointment_id'] ) && intval( $_POST['appointment_id'] ) > 0 ),
        'frequency' => isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '',
        'tosa' => isset( $_POST['appointment_tosa'] ) ? '1' : '0',
        'tosa_price' => self::sanitize_price_field( 'appointment_tosa_price' ),
        'tosa_occurrence' => isset( $_POST['appointment_tosa_occurrence'] ) ? intval( wp_unslash( $_POST['appointment_tosa_occurrence'] ) ) : 1,
        'taxidog' => isset( $_POST['appointment_taxidog'] ) ? '1' : '0',
        'taxidog_price' => self::sanitize_taxidog_price( $appointment_type ),
        'extra_description' => isset( $_POST['appointment_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_extra_description'] ) ) : '',
        'extra_value' => self::sanitize_price_field( 'appointment_extra_value' ),
        'subscription_base_value' => self::sanitize_price_field( 'subscription_base_value' ),
        'subscription_total_value' => self::sanitize_price_field( 'subscription_total_value' ),
        'subscription_extra_description' => isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '',
        'subscription_extra_value' => self::sanitize_price_field( 'subscription_extra_value' ),
    ];
}

/**
 * Extrai e sanitiza IDs de pets do formulário.
 *
 * @return array Lista de IDs de pets únicos e válidos.
 */
private static function extract_and_sanitize_pet_ids() {
    $raw_pets = isset( $_POST['appointment_pet_ids'] ) ? (array) wp_unslash( $_POST['appointment_pet_ids'] ) : [];
    $pet_ids = [];
    
    foreach ( $raw_pets as $raw_pet_id ) {
        $pet_id = intval( $raw_pet_id );
        if ( $pet_id > 0 ) {
            $pet_ids[] = $pet_id;
        }
    }
    
    return array_values( array_unique( $pet_ids ) );
}

/**
 * Sanitiza campo de preço do formulário.
 *
 * @param string $field_name Nome do campo POST.
 * @return float Valor sanitizado.
 */
private static function sanitize_price_field( $field_name ) {
    if ( ! isset( $_POST[ $field_name ] ) ) {
        return 0.0;
    }
    
    $value = floatval( str_replace( ',', '.', wp_unslash( $_POST[ $field_name ] ) ) );
    return max( 0.0, $value );
}

/**
 * Sanitiza preço do TaxiDog considerando o tipo de agendamento.
 *
 * @param string $appointment_type Tipo do agendamento.
 * @return float Valor sanitizado.
 */
private static function sanitize_taxidog_price( $appointment_type ) {
    $taxidog_enabled = isset( $_POST['appointment_taxidog'] ) && $_POST['appointment_taxidog'];
    
    if ( 'simple' !== $appointment_type || ! $taxidog_enabled ) {
        return 0.0;
    }
    
    return self::sanitize_price_field( 'appointment_taxidog_price' );
}

/**
 * Valida dados essenciais do agendamento.
 *
 * @param array $data Dados do agendamento.
 * @return bool True se os dados são válidos.
 */
private static function validate_appointment_data( $data ) {
    return ! empty( $data['client_id'] ) 
        && ! empty( $data['pet_ids'] ) 
        && ! empty( $data['date'] ) 
        && ! empty( $data['time'] );
}

/**
 * Cria um agendamento do tipo assinatura.
 *
 * @param array $data Dados do agendamento.
 */
private static function create_subscription_appointment( $data ) {
    // Implementação da lógica de criação de assinatura
    // (extraída das linhas 1963-2100 aproximadamente)
}

/**
 * Salva agendamento simples ou atualiza agendamento existente.
 *
 * @param array $data Dados do agendamento.
 */
private static function save_simple_or_update_appointment( $data ) {
    // Implementação da lógica de salvamento/atualização
    // (extraída das linhas 2100-2280 aproximadamente)
}
```

**Benefícios:**
- Cada função tem uma responsabilidade única e clara
- Nomes descritivos facilitam compreensão
- Código mais testável
- Redução de complexidade ciclomática
- Facilita manutenção futura

---

### 1.2. `DPS_Base_Frontend::render_client_page()` - 279 linhas

**Localização:** `plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php:2330-2608`

**Problemas:**
- Mistura lógica de consulta de dados com renderização HTML
- Lógica complexa de filtragem de agendamentos
- Geração de documentos e envio de emails misturados com renderização

**Sugestão de Refatoração:**

```php
/**
 * Renderiza a página de detalhes do cliente.
 *
 * @param int $client_id ID do cliente.
 * @return string HTML da página.
 */
private static function render_client_page( $client_id ) {
    $client_data = self::get_client_complete_data( $client_id );
    
    if ( ! $client_data['client'] ) {
        return self::render_client_not_found_message();
    }
    
    self::handle_client_page_actions( $client_id );
    
    ob_start();
    self::render_client_page_header( $client_data );
    self::render_client_basic_info( $client_data );
    self::render_client_pets_list( $client_data['pets'] );
    self::render_client_appointments_section( $client_id, $client_data['appointments'] );
    return ob_get_clean();
}

/**
 * Obtém todos os dados necessários para renderizar a página do cliente.
 *
 * @param int $client_id ID do cliente.
 * @return array Dados do cliente, pets e agendamentos.
 */
private static function get_client_complete_data( $client_id ) {
    $client = get_post( $client_id );
    
    return [
        'client' => $client,
        'metadata' => self::get_client_metadata( $client_id ),
        'pets' => self::get_client_pets( $client_id ),
        'appointments' => self::get_client_appointments( $client_id ),
    ];
}

/**
 * Obtém metadados do cliente.
 *
 * @param int $client_id ID do cliente.
 * @return array Metadados do cliente.
 */
private static function get_client_metadata( $client_id ) {
    return [
        'phone' => get_post_meta( $client_id, 'client_phone', true ),
        'email' => get_post_meta( $client_id, 'client_email', true ),
        'cpf' => get_post_meta( $client_id, 'client_cpf', true ),
        'birth' => get_post_meta( $client_id, 'client_birth', true ),
        'address' => get_post_meta( $client_id, 'client_address', true ),
        'instagram' => get_post_meta( $client_id, 'client_instagram', true ),
        'facebook' => get_post_meta( $client_id, 'client_facebook', true ),
        'referral' => get_post_meta( $client_id, 'client_referral', true ),
    ];
}

/**
 * Obtém pets vinculados ao cliente.
 *
 * @param int $client_id ID do cliente.
 * @return array Lista de pets.
 */
private static function get_client_pets( $client_id ) {
    return get_posts( [
        'post_type' => 'dps_pet',
        'posts_per_page' => -1,
        'meta_key' => 'owner_id',
        'meta_value' => $client_id,
        'orderby' => 'title',
        'order' => 'ASC',
    ] );
}

/**
 * Obtém agendamentos do cliente.
 *
 * @param int $client_id ID do cliente.
 * @return array Lista de agendamentos.
 */
private static function get_client_appointments( $client_id ) {
    return get_posts( [
        'post_type' => 'dps_agendamento',
        'posts_per_page' => -1,
        'meta_key' => 'appointment_client_id',
        'meta_value' => $client_id,
        'orderby' => 'meta_value',
        'meta_key' => 'appointment_date',
        'order' => 'DESC',
    ] );
}

/**
 * Processa ações específicas da página do cliente.
 *
 * @param int $client_id ID do cliente.
 */
private static function handle_client_page_actions( $client_id ) {
    if ( isset( $_GET['gen_history'] ) ) {
        self::handle_generate_client_history( $client_id );
    }
    
    if ( isset( $_POST['dps_send_history_email'] ) ) {
        self::handle_send_client_history_email( $client_id );
    }
}

/**
 * Renderiza cabeçalho da página do cliente.
 *
 * @param array $client_data Dados do cliente.
 */
private static function render_client_page_header( $client_data ) {
    echo '<div class="dps-client-page">';
    echo '<h2>' . esc_html( $client_data['client']->post_title ) . '</h2>';
    echo '<p><a href="' . esc_url( remove_query_arg( [ 'dps_view', 'id' ] ) ) . '">&larr; ' . esc_html__( 'Voltar ao painel', 'desi-pet-shower' ) . '</a></p>';
}

/**
 * Renderiza informações básicas do cliente.
 *
 * @param array $client_data Dados do cliente.
 */
private static function render_client_basic_info( $client_data ) {
    $meta = $client_data['metadata'];
    
    echo '<div class="dps-client-info">';
    echo '<h3>' . esc_html__( 'Informações de Contato', 'desi-pet-shower' ) . '</h3>';
    
    if ( $meta['phone'] ) {
        $whatsapp_url = 'https://wa.me/' . self::format_whatsapp_number( $meta['phone'] );
        echo '<p><strong>' . esc_html__( 'Telefone:', 'desi-pet-shower' ) . '</strong> ';
        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank">' . esc_html( $meta['phone'] ) . '</a></p>';
    }
    
    if ( $meta['email'] ) {
        echo '<p><strong>Email:</strong> <a href="mailto:' . esc_attr( $meta['email'] ) . '">' . esc_html( $meta['email'] ) . '</a></p>';
    }
    
    // Renderizar demais campos...
    
    echo '</div>';
}

/**
 * Renderiza lista de pets do cliente.
 *
 * @param array $pets Lista de pets.
 */
private static function render_client_pets_list( $pets ) {
    if ( empty( $pets ) ) {
        echo '<p>' . esc_html__( 'Nenhum pet cadastrado para este cliente.', 'desi-pet-shower' ) . '</p>';
        return;
    }
    
    echo '<h3>' . esc_html__( 'Pets', 'desi-pet-shower' ) . '</h3>';
    echo '<ul class="dps-pets-list">';
    
    foreach ( $pets as $pet ) {
        echo '<li>' . esc_html( $pet->post_title );
        
        $breed = get_post_meta( $pet->ID, 'pet_breed', true );
        if ( $breed ) {
            echo ' (' . esc_html( $breed ) . ')';
        }
        
        echo '</li>';
    }
    
    echo '</ul>';
}

/**
 * Renderiza seção de agendamentos do cliente.
 *
 * @param int   $client_id ID do cliente.
 * @param array $appointments Lista de agendamentos.
 */
private static function render_client_appointments_section( $client_id, $appointments ) {
    echo '<h3>' . esc_html__( 'Histórico de Agendamentos', 'desi-pet-shower' ) . '</h3>';
    
    self::render_client_history_actions( $client_id );
    self::render_client_appointments_filters();
    
    if ( empty( $appointments ) ) {
        echo '<p>' . esc_html__( 'Nenhum agendamento encontrado.', 'desi-pet-shower' ) . '</p>';
        return;
    }
    
    self::render_client_appointments_table( $appointments );
}
```

**Benefícios:**
- Separação clara entre lógica de dados e apresentação
- Funções pequenas e focadas
- Facilita testes unitários
- Melhor legibilidade

---

### 1.3. `DPS_Base_Frontend::section_agendas()` - 264 linhas

**Localização:** `plugin/desi-pet-shower-base_plugin/includes/class-dps-base-frontend.php:1019-1282`

**Problemas:**
- Mistura renderização de formulário com listagem
- JavaScript inline misturado com PHP
- Lógica complexa de pré-preenchimento de formulário

**Sugestão de Refatoração:**

```php
/**
 * Renderiza seção de agendamentos.
 *
 * @param bool $visitor_only Se está em modo visitante.
 * @return string HTML da seção.
 */
private static function section_agendas( $visitor_only = false ) {
    ob_start();
    echo '<div class="dps-section" id="dps-section-agendas">';
    
    if ( ! $visitor_only ) {
        self::render_appointments_form();
    }
    
    self::render_appointments_list( $visitor_only );
    
    echo '</div>';
    return ob_get_clean();
}

/**
 * Renderiza formulário de agendamentos.
 */
private static function render_appointments_form() {
    $form_data = self::get_appointments_form_data();
    
    echo '<h3>' . esc_html__( 'Novo Agendamento', 'desi-pet-shower' ) . '</h3>';
    echo '<form method="post" class="dps-form" id="dps-appointment-form">';
    
    self::render_form_hidden_fields( $form_data );
    self::render_form_client_selector( $form_data );
    self::render_form_pet_selector( $form_data );
    self::render_form_datetime_fields( $form_data );
    self::render_form_type_selector( $form_data );
    self::render_form_service_options( $form_data );
    self::render_form_notes_field( $form_data );
    self::render_form_submit_button( $form_data );
    
    echo '</form>';
    
    self::enqueue_appointments_form_scripts();
}

/**
 * Obtém dados para pré-preencher o formulário de agendamentos.
 *
 * @return array Dados do formulário.
 */
private static function get_appointments_form_data() {
    $edit_id = isset( $_GET['dps_edit'] ) && 'appointment' === $_GET['dps_edit'] && isset( $_GET['id'] ) 
        ? intval( $_GET['id'] ) 
        : 0;
    
    $prefilled_client_id = isset( $_GET['pref_client'] ) ? intval( $_GET['pref_client'] ) : 0;
    
    if ( $edit_id ) {
        return self::get_edit_appointment_data( $edit_id );
    }
    
    return self::get_new_appointment_data( $prefilled_client_id );
}

/**
 * Obtém dados de um agendamento existente para edição.
 *
 * @param int $appointment_id ID do agendamento.
 * @return array Dados do agendamento.
 */
private static function get_edit_appointment_data( $appointment_id ) {
    $appointment = get_post( $appointment_id );
    
    if ( ! $appointment ) {
        return self::get_new_appointment_data( 0 );
    }
    
    return [
        'is_editing' => true,
        'appointment_id' => $appointment_id,
        'client_id' => get_post_meta( $appointment_id, 'appointment_client_id', true ),
        'pet_ids' => get_post_meta( $appointment_id, 'appointment_pet_ids', true ),
        'date' => get_post_meta( $appointment_id, 'appointment_date', true ),
        'time' => get_post_meta( $appointment_id, 'appointment_time', true ),
        'notes' => get_post_meta( $appointment_id, 'appointment_notes', true ),
        'type' => get_post_meta( $appointment_id, 'appointment_type', true ),
        'tosa' => get_post_meta( $appointment_id, 'appointment_tosa', true ),
        'taxidog' => get_post_meta( $appointment_id, 'appointment_taxidog', true ),
    ];
}

/**
 * Obtém dados padrão para novo agendamento.
 *
 * @param int $prefilled_client_id ID do cliente pré-selecionado.
 * @return array Dados padrão.
 */
private static function get_new_appointment_data( $prefilled_client_id ) {
    return [
        'is_editing' => false,
        'appointment_id' => 0,
        'client_id' => $prefilled_client_id,
        'pet_ids' => [],
        'date' => '',
        'time' => '',
        'notes' => '',
        'type' => 'simple',
        'tosa' => '0',
        'taxidog' => '0',
    ];
}

/**
 * Renderiza campos ocultos do formulário.
 *
 * @param array $data Dados do formulário.
 */
private static function render_form_hidden_fields( $data ) {
    echo '<input type="hidden" name="dps_action" value="save_appointment">';
    wp_nonce_field( 'dps_action', 'dps_nonce' );
    
    if ( $data['is_editing'] ) {
        echo '<input type="hidden" name="appointment_id" value="' . esc_attr( $data['appointment_id'] ) . '">';
    }
}

/**
 * Renderiza lista de agendamentos.
 *
 * @param bool $visitor_only Se está em modo visitante.
 */
private static function render_appointments_list( $visitor_only ) {
    $appointments = self::get_pending_appointments();
    
    echo '<h3>' . esc_html__( 'Agendamentos Pendentes', 'desi-pet-shower' ) . '</h3>';
    
    if ( empty( $appointments ) ) {
        echo '<p>' . esc_html__( 'Nenhum agendamento pendente.', 'desi-pet-shower' ) . '</p>';
        return;
    }
    
    self::render_appointments_table( $appointments, $visitor_only );
}

/**
 * Obtém lista de agendamentos pendentes.
 *
 * @return array Lista de agendamentos.
 */
private static function get_pending_appointments() {
    return get_posts( [
        'post_type' => 'dps_agendamento',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'appointment_status',
                'value' => [ 'agendado', 'confirmado', 'em_andamento' ],
                'compare' => 'IN',
            ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'appointment_date',
        'order' => 'ASC',
    ] );
}

/**
 * Renderiza tabela de agendamentos.
 *
 * @param array $appointments Lista de agendamentos.
 * @param bool  $visitor_only Se está em modo visitante.
 */
private static function render_appointments_table( $appointments, $visitor_only ) {
    echo '<table class="dps-table">';
    self::render_appointments_table_header();
    echo '<tbody>';
    
    foreach ( $appointments as $appointment ) {
        self::render_appointments_table_row( $appointment, $visitor_only );
    }
    
    echo '</tbody></table>';
}
```

**Benefícios:**
- Funções pequenas e focadas
- Separação clara de responsabilidades
- Mais fácil de testar
- JavaScript separado do PHP

---

## 2. Nomes Pouco Descritivos

### 2.1. Variáveis de Loop

**Problemas encontrados:**
```php
// Linha 1649
private static function compare_appointments_desc( $a, $b ) {
```

**Sugestão:**
```php
/**
 * Compara dois agendamentos por data de forma descendente.
 *
 * @param object $first_appointment Primeiro agendamento.
 * @param object $second_appointment Segundo agendamento.
 * @return int Resultado da comparação (-1, 0, 1).
 */
private static function compare_appointments_desc( $first_appointment, $second_appointment ) {
    $first_date = get_post_meta( $first_appointment->ID, 'appointment_date', true );
    $second_date = get_post_meta( $second_appointment->ID, 'appointment_date', true );
    
    return strcmp( $second_date, $first_date );
}
```

### 2.2. Variáveis de Metadados

**Problemas encontrados:**
```php
// Em vários lugares
$meta = get_post_meta( $id, 'some_key', true );
```

**Sugestão:**
```php
// Ser mais específico sobre o que está sendo obtido
$client_phone = get_post_meta( $client_id, 'client_phone', true );
$pet_breed = get_post_meta( $pet_id, 'pet_breed', true );
$appointment_date = get_post_meta( $appointment_id, 'appointment_date', true );
```

### 2.3. Variáveis de Preço

**Problemas encontrados:**
```php
$val = floatval( $_POST['price'] );
$value = $val * 100;
```

**Sugestão:**
```php
$price_as_decimal = floatval( $_POST['price'] );
$price_in_cents = (int) round( $price_as_decimal * 100 );
```

---

## 3. Código Duplicado

### 3.1. Validação de Nonce

**Código duplicado:**
```php
// Aparece em handle_request (linha 458)
if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_nonce'] ) ), 'dps_action' ) ) {
    return;
}

// Aparece em handle_delete (linha 509)
if ( ! isset( $_GET['dps_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['dps_nonce'] ), 'dps_delete' ) ) {
    wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
}
```

**Sugestão de função reutilizável:**
```php
/**
 * Verifica nonce de uma requisição.
 *
 * @param string $nonce_field Nome do campo de nonce.
 * @param string $nonce_action Ação do nonce.
 * @param string $method Método HTTP ('POST' ou 'GET').
 * @param bool   $die_on_failure Se deve terminar execução em caso de falha.
 * @return bool True se o nonce é válido.
 */
private static function verify_request_nonce( $nonce_field, $nonce_action, $method = 'POST', $die_on_failure = true ) {
    $superglobal = ( 'POST' === $method ) ? $_POST : $_GET;
    
    if ( ! isset( $superglobal[ $nonce_field ] ) ) {
        if ( $die_on_failure ) {
            wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }
        return false;
    }
    
    $nonce_value = ( 'POST' === $method ) 
        ? sanitize_text_field( wp_unslash( $superglobal[ $nonce_field ] ) )
        : wp_unslash( $superglobal[ $nonce_field ] );
    
    if ( ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
        if ( $die_on_failure ) {
            wp_die( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }
        return false;
    }
    
    return true;
}

// Uso:
public static function handle_request() {
    self::verify_request_nonce( 'dps_nonce', 'dps_action', 'POST', false );
    // ...
}

public static function handle_delete() {
    self::verify_request_nonce( 'dps_nonce', 'dps_delete', 'GET', true );
    // ...
}
```

### 3.2. Construção de URLs

**Código duplicado:**
```php
// Aparece múltiplas vezes (linhas 749, 750, 752, 754, etc.)
$edit_url = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client->ID ], $base_url );
$delete_url = add_query_arg( [ 'tab' => 'clientes', 'dps_delete' => 'client', 'id' => $client->ID ], $base_url );
$view_url = add_query_arg( [ 'dps_view' => 'client', 'id' => $client->ID ], $base_url );
```

**Sugestão de funções reutilizáveis:**
```php
/**
 * Constrói URL para editar um registro.
 *
 * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
 * @param int    $record_id ID do registro.
 * @param string $tab Aba de destino.
 * @param string $base_url URL base (opcional).
 * @return string URL completa.
 */
private static function build_edit_url( $record_type, $record_id, $tab = '', $base_url = null ) {
    if ( null === $base_url ) {
        $base_url = get_permalink();
    }
    
    $args = [
        'dps_edit' => $record_type,
        'id' => $record_id,
    ];
    
    if ( $tab ) {
        $args['tab'] = $tab;
    }
    
    return add_query_arg( $args, $base_url );
}

/**
 * Constrói URL para excluir um registro.
 *
 * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
 * @param int    $record_id ID do registro.
 * @param string $tab Aba de destino.
 * @param string $base_url URL base (opcional).
 * @return string URL completa com nonce.
 */
private static function build_delete_url( $record_type, $record_id, $tab = '', $base_url = null ) {
    if ( null === $base_url ) {
        $base_url = get_permalink();
    }
    
    $args = [
        'dps_delete' => $record_type,
        'id' => $record_id,
        'dps_nonce' => wp_create_nonce( 'dps_delete' ),
    ];
    
    if ( $tab ) {
        $args['tab'] = $tab;
    }
    
    return add_query_arg( $args, $base_url );
}

/**
 * Constrói URL para visualizar um registro.
 *
 * @param string $record_type Tipo de registro ('client', 'pet', 'appointment').
 * @param int    $record_id ID do registro.
 * @param string $base_url URL base (opcional).
 * @return string URL completa.
 */
private static function build_view_url( $record_type, $record_id, $base_url = null ) {
    if ( null === $base_url ) {
        $base_url = get_permalink();
    }
    
    return add_query_arg( [
        'dps_view' => $record_type,
        'id' => $record_id,
    ], $base_url );
}

// Uso simplificado:
$edit_url = self::build_edit_url( 'client', $client->ID, 'clientes' );
$delete_url = self::build_delete_url( 'client', $client->ID, 'clientes' );
$view_url = self::build_view_url( 'client', $client->ID );
```

### 3.3. Formatação de Valores Monetários

**Código duplicado:**
```php
// No add-on financeiro (linhas 29-44, 47-57)
function dps_parse_money_br( $str ) {
    // ...lógica de conversão...
}

function dps_format_money_br( $int ) {
    // ...lógica de formatação...
}

// Também aparece lógica similar em vários lugares:
$value = floatval( str_replace( ',', '.', wp_unslash( $_POST['field'] ) ) );
```

**Sugestão:**

Criar uma classe utilitária centralizada:

```php
/**
 * Classe utilitária para manipulação de valores monetários.
 */
class DPS_Money_Helper {
    
    /**
     * Converte string em formato brasileiro para centavos.
     *
     * @param string $money_string Valor em formato brasileiro (ex: "1.234,56").
     * @return int Valor em centavos.
     */
    public static function parse_brazilian_format( $money_string ) {
        $sanitized = trim( (string) $money_string );
        
        if ( '' === $sanitized ) {
            return 0;
        }
        
        // Remove caracteres não numéricos exceto vírgula, ponto e sinal de menos
        $normalized = preg_replace( '/[^0-9,.-]/', '', $sanitized );
        $normalized = str_replace( ' ', '', $normalized );
        
        // Converte vírgula para ponto (formato brasileiro -> formato padrão)
        if ( false !== strpos( $normalized, ',' ) ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }
        
        $decimal_value = floatval( $normalized );
        return (int) round( $decimal_value * 100 );
    }
    
    /**
     * Formata centavos para string em formato brasileiro.
     *
     * @param int $cents Valor em centavos.
     * @return string Valor formatado (ex: "1.234,56").
     */
    public static function format_to_brazilian( $cents ) {
        $decimal_value = (int) $cents / 100;
        return number_format( $decimal_value, 2, ',', '.' );
    }
    
    /**
     * Sanitiza campo de preço do POST.
     *
     * @param string $field_name Nome do campo.
     * @return float Valor sanitizado.
     */
    public static function sanitize_post_price_field( $field_name ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return 0.0;
        }
        
        $raw_value = wp_unslash( $_POST[ $field_name ] );
        $normalized = str_replace( ',', '.', (string) $raw_value );
        $float_value = floatval( $normalized );
        
        return max( 0.0, $float_value );
    }
}
```

### 3.4. Consultas WP_Query Similares

**Código duplicado:**
```php
// Get clients (linha 627)
$args = [
    'post_type'      => 'dps_cliente',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
];
$query = new WP_Query( $args );

// Get pets (linha 642)
$args = [
    'post_type'      => 'dps_pet',
    'posts_per_page' => DPS_BASE_PETS_PER_PAGE,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'paged'          => max( 1, (int) $page ),
];
```

**Sugestão:**
```php
/**
 * Classe utilitária para consultas comuns.
 */
class DPS_Query_Helper {
    
    /**
     * Constrói argumentos base para consulta de posts.
     *
     * @param string $post_type Tipo de post.
     * @param array  $overrides Argumentos para sobrescrever padrões.
     * @return array Argumentos de consulta.
     */
    public static function build_base_query_args( $post_type, $overrides = [] ) {
        $defaults = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        return array_merge( $defaults, $overrides );
    }
    
    /**
     * Obtém todos os posts de um tipo específico.
     *
     * @param string $post_type Tipo de post.
     * @param array  $extra_args Argumentos adicionais.
     * @return array Lista de posts.
     */
    public static function get_all_posts_by_type( $post_type, $extra_args = [] ) {
        $args = self::build_base_query_args( $post_type, array_merge( 
            [ 'posts_per_page' => -1 ],
            $extra_args
        ) );
        
        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    /**
     * Obtém posts paginados de um tipo específico.
     *
     * @param string $post_type Tipo de post.
     * @param int    $page Número da página.
     * @param int    $per_page Posts por página.
     * @param array  $extra_args Argumentos adicionais.
     * @return WP_Query Objeto de consulta.
     */
    public static function get_paginated_posts( $post_type, $page = 1, $per_page = 20, $extra_args = [] ) {
        $args = self::build_base_query_args( $post_type, array_merge(
            [
                'posts_per_page' => $per_page,
                'paged' => max( 1, (int) $page ),
            ],
            $extra_args
        ) );
        
        return new WP_Query( $args );
    }
}

// Uso simplificado:
$clients = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
$pets_query = DPS_Query_Helper::get_paginated_posts( 'dps_pet', $page, DPS_BASE_PETS_PER_PAGE );
```

---

## 4. Recomendações Adicionais

### 4.1. Separação de Responsabilidades

**Criar classes especializadas:**

1. **DPS_Form_Renderer** - Para renderização de formulários
2. **DPS_Table_Renderer** - Para renderização de tabelas
3. **DPS_Data_Sanitizer** - Para sanitização de dados
4. **DPS_URL_Builder** - Para construção de URLs
5. **DPS_Appointment_Calculator** - Para cálculos de agendamentos
6. **DPS_WhatsApp_Helper** - Para integração com WhatsApp

### 4.2. Extração de Templates

Criar arquivos de template separados para:
- Formulários (`templates/forms/`)
- Tabelas (`templates/tables/`)
- Seções (`templates/sections/`)

### 4.3. Validação e Sanitização

Criar classe centralizada para validação:

```php
class DPS_Validator {
    public static function validate_client_data( $data ) { }
    public static function validate_pet_data( $data ) { }
    public static function validate_appointment_data( $data ) { }
}

class DPS_Sanitizer {
    public static function sanitize_client_data( $data ) { }
    public static function sanitize_pet_data( $data ) { }
    public static function sanitize_appointment_data( $data ) { }
}
```

### 4.4. Constantes

Substituir valores mágicos por constantes:

```php
// Em vez de:
if ( $status === 'finalizado' ) { }

// Usar:
class DPS_Appointment_Status {
    const SCHEDULED = 'agendado';
    const CONFIRMED = 'confirmado';
    const IN_PROGRESS = 'em_andamento';
    const FINISHED = 'finalizado';
    const FINISHED_PAID = 'finalizado_pago';
    const CANCELLED = 'cancelado';
}

if ( $status === DPS_Appointment_Status::FINISHED ) { }
```

---

## 5. Priorização de Refatoração

### Prioridade Alta (Impacto Imediato)
1. ✅ Quebrar `save_appointment()` em funções menores
2. ✅ Criar helpers para sanitização e validação
3. ✅ Centralizar construção de URLs

### Prioridade Média (Melhoria de Manutenção)
4. ✅ Separar renderização de formulários e listagens
5. ✅ Criar classe de helpers monetários
6. ✅ Refatorar `render_client_page()`

### Prioridade Baixa (Qualidade de Código)
7. ✅ Melhorar nomenclatura de variáveis
8. ✅ Extrair constantes para valores mágicos
9. ✅ Criar templates separados

---

## 6. Plano de Implementação

### Fase 1: Preparação (1-2 dias)
- Criar branch de refatoração
- Configurar testes automatizados
- Documentar comportamento atual

### Fase 2: Helpers e Utilitários (2-3 dias)
- Implementar `DPS_Money_Helper`
- Implementar `DPS_Query_Helper`
- Implementar `DPS_URL_Builder`
- Implementar helpers de validação de nonce

### Fase 3: Refatoração de Funções Grandes (3-5 dias)
- Refatorar `save_appointment()`
- Refatorar `render_client_page()`
- Refatorar `section_agendas()`
- Refatorar outras seções similares

### Fase 4: Melhorias de Nomenclatura (1-2 dias)
- Renomear variáveis pouco descritivas
- Melhorar nomes de funções
- Adicionar documentação

### Fase 5: Testes e Validação (2-3 dias)
- Executar testes automatizados
- Testes manuais de funcionalidades
- Ajustes finais

### Fase 6: Documentação (1 dia)
- Atualizar ANALYSIS.md
- Atualizar CHANGELOG.md
- Criar guias de contribuição

---

## Conclusão

Este documento identificou oportunidades significativas de melhoria no código do projeto desi.pet by PRObst. As refatorações propostas:

1. **Reduzem a complexidade** de funções muito grandes
2. **Melhoram a legibilidade** com nomes mais descritivos
3. **Eliminam duplicação** através de funções reutilizáveis
4. **Facilitam manutenção** futura do código
5. **Aumentam testabilidade** do sistema

A implementação gradual destas melhorias, seguindo as prioridades sugeridas, resultará em um código mais limpo, manutenível e profissional, sem comprometer a funcionalidade existente.
