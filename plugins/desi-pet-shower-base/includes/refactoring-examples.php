<?php
/**
 * Exemplos de uso das classes helper para refatoração.
 *
 * Este arquivo demonstra como usar as novas classes helper para simplificar
 * e melhorar a qualidade do código existente.
 *
 * @package DesiPetShower
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exemplos de refatoração com DPS_Request_Validator
 */
class DPS_Refactoring_Examples_Validator {

    /**
     * ANTES: Validação manual de nonce e capability
     */
    public static function save_client_old_way() {
        // Verifica nonce
        if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_nonce'] ) ), 'dps_action' ) ) {
            return;
        }

        if ( ! current_user_can( 'dps_manage_clients' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $client_id = isset( $_POST['client_id'] ) ? intval( wp_unslash( $_POST['client_id'] ) ) : 0;
        $client_name = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $client_notes = isset( $_POST['client_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_notes'] ) ) : '';
        $photo_auth = isset( $_POST['client_photo_auth'] ) ? '1' : '0';

        // ... resto da lógica ...
    }

    /**
     * DEPOIS: Usando DPS_Request_Validator
     */
    public static function save_client_new_way() {
        // Valida nonce e capability em uma linha
        DPS_Request_Validator::verify_nonce_and_capability( 'dps_nonce', 'dps_action', 'dps_manage_clients' );

        // Sanitiza campos usando métodos helper
        $client_id = DPS_Request_Validator::get_post_int( 'client_id', 0 );
        $client_name = DPS_Request_Validator::get_post_string( 'client_name' );
        $client_notes = DPS_Request_Validator::get_post_textarea( 'client_notes' );
        $photo_auth = DPS_Request_Validator::get_post_checkbox( 'client_photo_auth' );

        // ... resto da lógica ...
    }
}

/**
 * Exemplos de refatoração com DPS_URL_Builder
 */
class DPS_Refactoring_Examples_URL {

    /**
     * ANTES: Construção manual de URLs (pode causar avisos em PHP 8.1+ se get_permalink() retornar false)
     *
     * @deprecated Use DPS_URL_Builder::safe_get_permalink() ou os métodos build_*_url() para PHP 8.1+ compatibility
     */
    public static function render_client_actions_old_way( $client ) {
        // Problema: get_permalink() pode retornar false em alguns contextos, causando avisos em PHP 8.1+
        $base_url = DPS_URL_Builder::safe_get_permalink(); // Agora usa o método seguro
        $edit_url = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client->ID ], $base_url );
        $delete_url = add_query_arg( [ 'tab' => 'clientes', 'dps_delete' => 'client', 'id' => $client->ID ], $base_url );
        $view_url = add_query_arg( [ 'dps_view' => 'client', 'id' => $client->ID ], $base_url );
        $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client->ID ], $base_url );

        echo '<a href="' . esc_url( $edit_url ) . '">Editar</a> | ';
        echo '<a href="' . esc_url( $delete_url ) . '">Excluir</a> | ';
        echo '<a href="' . esc_url( $view_url ) . '">Ver</a> | ';
        echo '<a href="' . esc_url( $schedule_url ) . '">Agendar</a>';
    }

    /**
     * DEPOIS: Usando DPS_URL_Builder (recomendado)
     *
     * Os métodos build_*_url() já usam safe_get_permalink() internamente,
     * garantindo compatibilidade com PHP 8.1+ automaticamente.
     */
    public static function render_client_actions_new_way( $client ) {
        $edit_url = DPS_URL_Builder::build_edit_url( 'client', $client->ID, 'clientes' );
        $delete_url = DPS_URL_Builder::build_delete_url( 'client', $client->ID, 'clientes' );
        $view_url = DPS_URL_Builder::build_view_url( 'client', $client->ID );
        $schedule_url = DPS_URL_Builder::build_schedule_url( $client->ID );

        echo '<a href="' . esc_url( $edit_url ) . '">Editar</a> | ';
        echo '<a href="' . esc_url( $delete_url ) . '">Excluir</a> | ';
        echo '<a href="' . esc_url( $view_url ) . '">Ver</a> | ';
        echo '<a href="' . esc_url( $schedule_url ) . '">Agendar</a>';
    }
}

/**
 * Exemplos de refatoração com DPS_Money_Helper
 */
class DPS_Refactoring_Examples_Money {

    /**
     * ANTES: Conversão manual de valores monetários
     */
    public static function save_transaction_old_way() {
        $value_raw = sanitize_text_field( wp_unslash( $_POST['finance_value'] ?? '0' ) );
        $value_cent = DPS_Money_Helper::parse_brazilian_format( $value_raw );
        $value = $value_cent / 100;

        $extra_value = isset( $_POST['appointment_extra_value'] ) 
            ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_extra_value'] ) ) ) 
            : 0;
        if ( $extra_value < 0 ) {
            $extra_value = 0;
        }

        // ... resto da lógica ...
    }

    /**
     * DEPOIS: Usando DPS_Money_Helper
     */
    public static function save_transaction_new_way() {
        $value_raw = DPS_Request_Validator::get_post_string( 'finance_value', '0' );
        $value_in_cents = DPS_Money_Helper::parse_brazilian_format( $value_raw );
        $value_as_decimal = DPS_Money_Helper::cents_to_decimal( $value_in_cents );

        $extra_value = DPS_Money_Helper::sanitize_post_price_field( 'appointment_extra_value' );

        // ... resto da lógica ...
    }

    /**
     * ANTES: Formatação manual para exibição
     */
    public static function display_value_old_way( $cents ) {
        $float = (int) $cents / 100;
        return number_format( $float, 2, ',', '.' );
    }

    /**
     * DEPOIS: Usando DPS_Money_Helper
     */
    public static function display_value_new_way( $cents ) {
        return DPS_Money_Helper::format_to_brazilian( $cents );
    }
}

/**
 * Exemplos de refatoração com DPS_Query_Helper
 */
class DPS_Refactoring_Examples_Query {

    /**
     * ANTES: Consulta manual de clientes
     */
    public static function get_clients_old_way() {
        $args = [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * DEPOIS: Usando DPS_Query_Helper
     */
    public static function get_clients_new_way() {
        return DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
    }

    /**
     * ANTES: Consulta paginada manual de pets
     */
    public static function get_pets_old_way( $page = 1 ) {
        $args = [
            'post_type'      => 'dps_pet',
            'posts_per_page' => DPS_BASE_PETS_PER_PAGE,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'paged'          => max( 1, (int) $page ),
        ];
        return new WP_Query( $args );
    }

    /**
     * DEPOIS: Usando DPS_Query_Helper
     */
    public static function get_pets_new_way( $page = 1 ) {
        return DPS_Query_Helper::get_paginated_posts( 'dps_pet', $page, DPS_BASE_PETS_PER_PAGE );
    }

    /**
     * ANTES: Consulta com meta_query manual
     */
    public static function get_client_pets_old_way( $client_id ) {
        $args = [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * DEPOIS: Usando DPS_Query_Helper
     */
    public static function get_client_pets_new_way( $client_id ) {
        return DPS_Query_Helper::get_posts_by_meta( 'dps_pet', 'owner_id', $client_id );
    }
}

/**
 * Exemplo de refatoração completa: função grande quebrada em funções menores
 */
class DPS_Refactoring_Examples_Complex {

    /**
     * ANTES: Função grande com múltiplas responsabilidades (simplificado)
     */
    public static function save_appointment_old_way() {
        // Validação de nonce
        if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_nonce'] ) ), 'dps_action' ) ) {
            return;
        }

        // Validação de capability
        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        // Sanitização de campos
        $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;
        $date = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $time = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $notes = isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '';

        // Validação básica
        if ( empty( $client_id ) || empty( $date ) || empty( $time ) ) {
            return;
        }

        // ... lógica de salvamento ...
        // ... cálculos de valores ...
        // ... criação de posts ...
        // ... 200+ linhas adicionais ...
    }

    /**
     * DEPOIS: Função principal limpa delegando para métodos específicos
     */
    public static function save_appointment_new_way() {
        self::verify_appointment_permissions();

        $appointment_data = self::sanitize_appointment_form_data();

        if ( ! self::validate_appointment_data( $appointment_data ) ) {
            return;
        }

        self::process_appointment_save( $appointment_data );
    }

    /**
     * Verifica permissões para gerenciar agendamentos.
     */
    private static function verify_appointment_permissions() {
        DPS_Request_Validator::verify_nonce_and_capability( 
            'dps_nonce', 
            'dps_action', 
            'dps_manage_appointments' 
        );
    }

    /**
     * Sanitiza dados do formulário de agendamento.
     *
     * @return array Dados sanitizados.
     */
    private static function sanitize_appointment_form_data() {
        return [
            'client_id' => DPS_Request_Validator::get_post_int( 'appointment_client_id' ),
            'date' => DPS_Request_Validator::get_post_string( 'appointment_date' ),
            'time' => DPS_Request_Validator::get_post_string( 'appointment_time' ),
            'notes' => DPS_Request_Validator::get_post_textarea( 'appointment_notes' ),
        ];
    }

    /**
     * Valida dados essenciais do agendamento.
     *
     * @param array $data Dados do agendamento.
     * @return bool True se válido.
     */
    private static function validate_appointment_data( $data ) {
        return ! empty( $data['client_id'] ) 
            && ! empty( $data['date'] ) 
            && ! empty( $data['time'] );
    }

    /**
     * Processa o salvamento do agendamento.
     *
     * @param array $data Dados do agendamento.
     */
    private static function process_appointment_save( $data ) {
        // Lógica de salvamento isolada
    }
}

/**
 * Exemplo de melhoria de nomenclatura
 */
class DPS_Refactoring_Examples_Naming {

    /**
     * ANTES: Nomes pouco descritivos
     */
    public static function compare_old( $a, $b ) {
        $d1 = get_post_meta( $a->ID, 'appointment_date', true );
        $d2 = get_post_meta( $b->ID, 'appointment_date', true );
        return strcmp( $d2, $d1 );
    }

    /**
     * DEPOIS: Nomes descritivos e documentação
     */
    public static function compare_appointments_by_date_desc( $first_appointment, $second_appointment ) {
        $first_appointment_date = get_post_meta( $first_appointment->ID, 'appointment_date', true );
        $second_appointment_date = get_post_meta( $second_appointment->ID, 'appointment_date', true );
        
        return strcmp( $second_appointment_date, $first_appointment_date );
    }

    /**
     * ANTES: Variáveis genéricas
     */
    public static function calc_old( $val, $qty ) {
        $t = $val * $qty;
        $d = $t * 0.1;
        return $t - $d;
    }

    /**
     * DEPOIS: Variáveis descritivas
     */
    public static function calculate_total_with_discount( $unit_price, $quantity ) {
        $subtotal = $unit_price * $quantity;
        $discount_amount = $subtotal * 0.1;
        $total_with_discount = $subtotal - $discount_amount;
        
        return $total_with_discount;
    }
}
