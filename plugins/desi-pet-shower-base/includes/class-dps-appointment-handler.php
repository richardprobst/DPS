<?php
/**
 * Handler para operações CRUD de agendamentos.
 *
 * Extraído de class-dps-base-frontend.php como parte da Fase 2.1
 * do Plano de Implementação (decomposição do monólito).
 *
 * @package Desi_Pet_Shower_Base
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por validar, salvar e atualizar agendamentos.
 *
 * Contém a lógica de negócio pura. A orquestração de respostas AJAX
 * e redirecionamentos permanece em DPS_Base_Frontend (fachada).
 *
 * Métodos estáticos para manter compatibilidade com DPS_Base_Frontend.
 */
class DPS_Appointment_Handler {

    /**
     * Valida e sanitiza os dados de agendamento recebidos via POST.
     *
     * Centraliza toda a lógica de validação e sanitização dos campos
     * do formulário de agendamento. Extrai e processa dados de $_POST,
     * aplicando sanitização apropriada para cada tipo de campo.
     *
     * @since 2.0.0
     * @return array|null Array com dados validados e sanitizados, ou null se validação falhar.
     *                    Estrutura do array:
     *                    - client_id: int
     *                    - pet_ids: array<int>
     *                    - pet_id: int (primeiro pet da lista)
     *                    - date: string (Y-m-d)
     *                    - time: string (H:i)
     *                    - notes: string
     *                    - appt_type: string ('simple'|'subscription'|'past')
     *                    - appt_freq: string ('semanal'|'quinzenal')
     *                    - tosa: string ('0'|'1')
     *                    - tosa_price: float
     *                    - tosa_occurrence: int
     *                    - taxidog: string ('0'|'1')
     *                    - taxi_price: float
     *                    - extra_description: string
     *                    - extra_value: float
     *                    - subscription_base_value: float
     *                    - subscription_total_value: float
     *                    - subscription_extra_description: string
     *                    - subscription_extra_value: float
     *                    - edit_id: int (ID do agendamento sendo editado, ou 0 se novo)
     */
    public static function validate_and_sanitize_data() {
        $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;

        // Recebe lista de pets (multi-seleção). Pode ser array ou valor único.
        $raw_pets = isset( $_POST['appointment_pet_ids'] ) ? (array) wp_unslash( $_POST['appointment_pet_ids'] ) : [];
        $pet_ids  = [];
        foreach ( $raw_pets as $pid_raw ) {
            $pid = intval( $pid_raw );
            if ( $pid ) {
                $pet_ids[] = $pid;
            }
        }
        $pet_ids = array_values( array_unique( $pet_ids ) );
        $pet_id  = ! empty( $pet_ids ) ? $pet_ids[0] : 0;

        $date  = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $time  = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $notes = isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '';

        $appt_type = isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple';
        $appt_freq = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';

        $tosa            = isset( $_POST['appointment_tosa'] ) ? '1' : '0';
        $tosa_price      = 0;
        if ( isset( $_POST['appointment_tosa_price'] ) ) {
            $tosa_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_tosa_price'] ) ) );
            if ( $tosa_price < 0 ) {
                $tosa_price = 0;
            }
        }
        $tosa_occurrence = isset( $_POST['appointment_tosa_occurrence'] ) ? intval( wp_unslash( $_POST['appointment_tosa_occurrence'] ) ) : 1;

        $taxidog    = isset( $_POST['appointment_taxidog'] ) ? '1' : '0';
        $taxi_price = 0;
        if ( 'simple' === $appt_type && $taxidog && isset( $_POST['appointment_taxidog_price'] ) ) {
            $taxi_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_taxidog_price'] ) ) );
            if ( $taxi_price < 0 ) {
                $taxi_price = 0;
            }
        }

        $extra_description = isset( $_POST['appointment_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_extra_description'] ) ) : '';
        $extra_value       = isset( $_POST['appointment_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_extra_value'] ) ) ) : 0;
        if ( $extra_value < 0 ) {
            $extra_value = 0;
        }

        $subscription_base_value        = isset( $_POST['subscription_base_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_base_value'] ) ) ) : 0;
        $subscription_total_value       = isset( $_POST['subscription_total_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_total_value'] ) ) ) : 0;
        $subscription_extra_description = isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '';
        $subscription_extra_value       = isset( $_POST['subscription_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_extra_value'] ) ) ) : 0;
        if ( $subscription_extra_value < 0 ) {
            $subscription_extra_value = 0;
        }

        // Validação de campos obrigatórios com mensagens específicas.
        $errors = [];
        if ( empty( $client_id ) ) {
            $errors[] = __( 'O campo Cliente é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $pet_ids ) ) {
            $errors[] = __( 'Selecione pelo menos um pet para o agendamento.', 'desi-pet-shower' );
        }
        if ( empty( $date ) ) {
            $errors[] = __( 'O campo Data é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $time ) ) {
            $errors[] = __( 'O campo Horário é obrigatório.', 'desi-pet-shower' );
        }

        // Validação de data conforme tipo de agendamento
        if ( ! empty( $date ) ) {
            $today       = wp_date( 'Y-m-d' );
            $date_parsed = gmdate( 'Y-m-d', strtotime( $date ) );

            if ( 'past' === $appt_type ) {
                // Agendamentos passados exigem data anterior a hoje
                if ( $date_parsed >= $today ) {
                    $errors[] = __( 'Para agendamento passado, a data deve ser anterior a hoje.', 'desi-pet-shower' );
                }

                // Status de pagamento é obrigatório para agendamentos passados
                $past_payment_status = isset( $_POST['past_payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['past_payment_status'] ) ) : '';
                if ( empty( $past_payment_status ) ) {
                    $errors[] = __( 'Selecione o status do pagamento para agendamentos passados.', 'desi-pet-shower' );
                }
            } elseif ( in_array( $appt_type, [ 'simple', 'subscription' ], true ) ) {
                // Agendamentos simples e de assinatura não aceitam datas passadas
                if ( $date_parsed < $today ) {
                    $errors[] = __( 'A data não pode ser anterior a hoje. Use "Agendamento Passado" para registrar atendimentos já realizados.', 'desi-pet-shower' );
                }
            }
        }

        if ( ! empty( $errors ) ) {
            DPS_Logger::warning(
                __( 'Tentativa de salvar agendamento com dados incompletos', 'desi-pet-shower' ),
                [
                    'client_id' => $client_id,
                    'pet_ids'   => $pet_ids,
                    'date'      => $date,
                    'time'      => $time,
                    'user_id'   => get_current_user_id(),
                ],
                'appointments'
            );
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            return null;
        }

        $edit_id = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;

        return [
            'client_id'                      => $client_id,
            'pet_ids'                        => $pet_ids,
            'pet_id'                         => $pet_id,
            'date'                           => $date,
            'time'                           => $time,
            'notes'                          => $notes,
            'appt_type'                      => $appt_type,
            'appt_freq'                      => $appt_freq,
            'tosa'                           => $tosa,
            'tosa_price'                     => $tosa_price,
            'tosa_occurrence'                => $tosa_occurrence,
            'taxidog'                        => $taxidog,
            'taxi_price'                     => $taxi_price,
            'extra_description'              => $extra_description,
            'extra_value'                    => $extra_value,
            'subscription_base_value'        => $subscription_base_value,
            'subscription_total_value'       => $subscription_total_value,
            'subscription_extra_description' => $subscription_extra_description,
            'subscription_extra_value'       => $subscription_extra_value,
            'edit_id'                        => $edit_id,
        ];
    }

    /**
     * Atualiza o status de um agendamento a partir de dados do $_POST.
     *
     * @since 2.0.0
     * @param callable $redirect_url_fn Função que retorna a URL de redirecionamento.
     *                                  Assinatura: function( string $tab ): string
     * @return void Redireciona e encerra execução.
     */
    public static function update_status( $redirect_url_fn ) {
        if ( ! current_user_can( 'dps_manage_appointments' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $redirect_url = isset( $_POST['dps_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['dps_redirect_url'] ) ) : '';
        $appt_id      = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;
        $status       = isset( $_POST['appointment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_status'] ) ) : '';
        $valid        = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];

        $fallback_url = $redirect_url ? $redirect_url : call_user_func( $redirect_url_fn, 'agendamentos' );

        if ( ! $appt_id ) {
            DPS_Message_Helper::add_error( __( 'Agendamento inválido. Selecione um registro para atualizar.', 'desi-pet-shower' ) );
            wp_safe_redirect( $fallback_url );
            exit;
        }

        if ( ! in_array( $status, $valid, true ) ) {
            DPS_Message_Helper::add_error( __( 'Selecione um status válido para o agendamento.', 'desi-pet-shower' ) );
            wp_safe_redirect( $fallback_url );
            exit;
        }

        update_post_meta( $appt_id, 'appointment_status', $status );
        $appt_type = get_post_meta( $appt_id, 'appointment_type', true );
        if ( ! $appt_type ) {
            $appt_type = 'simple';
        }
        if ( in_array( $status, [ 'finalizado', 'finalizado_pago' ], true ) ) {
            do_action( 'dps_base_after_save_appointment', $appt_id, $appt_type );
        }
        $client_id = (int) get_post_meta( $appt_id, 'appointment_client_id', true );

        return $client_id;
    }

    /**
     * Verifica se o agendamento exige consentimento de tosa com máquina.
     *
     * NOTA: Este método assume que a verificação de nonce já foi realizada
     * pelo método save_appointment() antes de ser chamado. Não deve ser
     * chamado diretamente sem validação prévia de nonce.
     *
     * @since 2.0.0
     * @param array $data Dados sanitizados do agendamento.
     * @return bool
     */
    public static function requires_tosa_consent( array $data ) {
        $requires = ( '1' === $data['tosa'] );

        $service_ids = [];
        if ( isset( $_POST['appointment_services'] ) ) {
            $service_ids = array_map( 'absint', (array) wp_unslash( $_POST['appointment_services'] ) );
            $service_ids = array_filter( $service_ids );
        }

        if ( ! empty( $service_ids ) && self::services_require_tosa_consent( $service_ids ) ) {
            $requires = true;
        }

        /**
         * Permite sobrescrever a exigência de consentimento de tosa.
         *
         * @param bool  $requires    Se o consentimento é exigido.
         * @param array $data        Dados do agendamento.
         * @param array $service_ids IDs de serviços selecionados.
         */
        return (bool) apply_filters( 'dps_tosa_consent_required', $requires, $data, $service_ids );
    }

    /**
     * Detecta serviços que exigem consentimento de tosa com máquina.
     *
     * @since 2.0.0
     * @param array $service_ids IDs de serviços selecionados.
     * @return bool
     */
    public static function services_require_tosa_consent( array $service_ids ) {
        foreach ( $service_ids as $service_id ) {
            $category = get_post_meta( $service_id, 'service_category', true );
            if ( ! $category ) {
                continue;
            }

            $name = get_the_title( $service_id );
            if ( ! $name ) {
                continue;
            }

            $is_tosa_category = in_array( $category, [ 'tosa', 'opcoes_tosa' ], true );
            $has_machine = ( false !== stripos( $name, 'máquina' ) || false !== stripos( $name, 'maquina' ) );

            if ( $is_tosa_category && $has_machine ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cria agendamentos recorrentes para uma nova assinatura.
     *
     * Este método é responsável por criar a assinatura (post dps_subscription)
     * e todos os agendamentos individuais (posts dps_agendamento) para cada
     * pet e cada ocorrência no ciclo (semanal ou quinzenal).
     *
     * @since 2.0.0
     * @param array  $data    Dados validados do formulário (de validate_and_sanitize_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    public static function create_subscription_appointments( array $data, $context = 'page' ) {
        $client_id                      = $data['client_id'];
        $pet_ids                        = $data['pet_ids'];
        $date                           = $data['date'];
        $time                           = $data['time'];
        $appt_freq                      = $data['appt_freq'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $tosa_occurrence                = $data['tosa_occurrence'];
        $taxidog                        = $data['taxidog'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];

        // Define serviços padrão: Tosa higiênica e Hidratação.
        $service_names = [ 'Tosa higienica', 'Hidratação' ];
        $service_ids   = [];
        $prices        = [];
        foreach ( $service_names as $sname ) {
            $srv = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'title'          => $sname,
            ] );
            if ( $srv ) {
                $srv_id            = $srv[0]->ID;
                $service_ids[]     = $srv_id;
                $base_price        = (float) get_post_meta( $srv_id, 'service_price', true );
                $prices[ $srv_id ] = $base_price;
            }
        }

        // Calcula preço base do evento.
        $base_event_price = 0;
        foreach ( $prices as $p ) {
            $base_event_price += (float) $p;
        }

        // Define número de ocorrências no ciclo.
        $count_events     = ( 'quinzenal' === $appt_freq ) ? 2 : 4;
        $base_cycle_value = ( $subscription_base_value > 0 ) ? $subscription_base_value : ( $base_event_price * $count_events );
        $extra_cycle_value = ( $subscription_extra_value > 0 ) ? $subscription_extra_value : 0;
        $package_per_pet   = $base_cycle_value + ( ( '1' === $tosa ) ? $tosa_price : 0 ) + $extra_cycle_value;
        $total_package     = $package_per_pet * count( $pet_ids );

        if ( $subscription_total_value > 0 ) {
            $total_package = $subscription_total_value;
            if ( count( $pet_ids ) > 0 ) {
                $package_per_pet = $total_package / count( $pet_ids );
            }
        }

        // Cria post da assinatura.
        $sub_id = wp_insert_post( [
            'post_type'   => 'dps_subscription',
            'post_title'  => $date . ' ' . $time . ' - ' . __( 'Assinatura', 'desi-pet-shower' ),
            'post_status' => 'publish',
        ] );

        if ( ! $sub_id ) {
            DPS_Message_Helper::add_error( __( 'Erro ao criar assinatura.', 'desi-pet-shower' ) );
            return false;
        }

        // Salva metadados da assinatura.
        update_post_meta( $sub_id, 'subscription_client_id', $client_id );
        update_post_meta( $sub_id, 'subscription_pet_id', $pet_ids[0] );
        update_post_meta( $sub_id, 'subscription_pet_ids', $pet_ids );
        update_post_meta( $sub_id, 'subscription_service', 'Assinatura' );
        update_post_meta( $sub_id, 'subscription_frequency', $appt_freq ?: 'semanal' );
        update_post_meta( $sub_id, 'subscription_price', $total_package );

        if ( $subscription_base_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_base_value', $subscription_base_value );
        }
        if ( $subscription_total_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_total_value', $subscription_total_value );
        }
        if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
            update_post_meta( $sub_id, 'subscription_extra_description', $subscription_extra_description );
            update_post_meta( $sub_id, 'subscription_extra_value', $subscription_extra_value );
        } else {
            delete_post_meta( $sub_id, 'subscription_extra_description' );
            delete_post_meta( $sub_id, 'subscription_extra_value' );
        }

        update_post_meta( $sub_id, 'subscription_tosa', $tosa );
        update_post_meta( $sub_id, 'subscription_tosa_price', $tosa_price );
        update_post_meta( $sub_id, 'subscription_tosa_occurrence', $tosa_occurrence );
        update_post_meta( $sub_id, 'subscription_start_date', $date );
        update_post_meta( $sub_id, 'subscription_start_time', $time );
        update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );

        // Cria agendamentos individuais.
        $interval_days = ( 'quinzenal' === $appt_freq ) ? 14 : 7;
        $count_events  = ( 'quinzenal' === $appt_freq ) ? 2 : 4;

        $created_ids = [];
        foreach ( $pet_ids as $p_id_each ) {
            $current_dt = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( ! $current_dt ) {
                $current_dt = date_create( $date );
            }
            if ( ! $current_dt ) {
                continue;
            }

            for ( $i = 0; $i < $count_events; $i++ ) {
                $date_i   = $current_dt->format( 'Y-m-d' );
                $appt_new = wp_insert_post( [
                    'post_type'   => 'dps_agendamento',
                    'post_title'  => $date_i . ' ' . $time,
                    'post_status' => 'publish',
                ] );

                if ( $appt_new ) {
                    $created_ids[] = (int) $appt_new;
                    update_post_meta( $appt_new, 'appointment_client_id', $client_id );
                    update_post_meta( $appt_new, 'appointment_pet_id', $p_id_each );
                    update_post_meta( $appt_new, 'appointment_pet_ids', [ $p_id_each ] );
                    update_post_meta( $appt_new, 'appointment_date', $date_i );
                    update_post_meta( $appt_new, 'appointment_time', $time );
                    update_post_meta( $appt_new, 'appointment_notes', __( 'Serviço de assinatura', 'desi-pet-shower' ) );
                    update_post_meta( $appt_new, 'appointment_type', 'subscription' );

                    $is_tosa_event = ( '1' === $tosa && ( $i + 1 ) == $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_tosa', $is_tosa_event ? '1' : '0' );
                    update_post_meta( $appt_new, 'appointment_tosa_price', $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_tosa_occurrence', $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_taxidog', $taxidog );
                    update_post_meta( $appt_new, 'appointment_taxidog_price', 0 );
                    update_post_meta( $appt_new, 'appointment_services', $service_ids );
                    update_post_meta( $appt_new, 'appointment_service_prices', $prices );

                    $total_single = $base_event_price + ( $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_total_value', $total_single );
                    update_post_meta( $appt_new, 'appointment_status', 'pendente' );
                    update_post_meta( $appt_new, 'subscription_id', $sub_id );

                    do_action( 'dps_base_after_save_appointment', $appt_new, 'subscription' );
                }
                $current_dt->modify( '+' . $interval_days . ' days' );
            }
        }

        // Registra transação financeira.
        global $wpdb;
        $table      = $wpdb->prefix . 'dps_transacoes';
        $status_fin = 'em_aberto';
        $desc_fin   = sprintf( __( 'Assinatura: %s (%s)', 'desi-pet-shower' ), 'Assinatura', ( $appt_freq ?: 'semanal' ) );
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d AND data = %s", $sub_id, $date ) );

        if ( $existing_id ) {
            $wpdb->update(
                $table,
                [
                    'cliente_id' => $client_id ?: null,
                    'valor'      => (float) $total_package,
                    'status'     => $status_fin,
                    'categoria'  => __( 'Assinatura', 'desi-pet-shower' ),
                    'tipo'       => 'receita',
                    'descricao'  => $desc_fin,
                ],
                [ 'id' => $existing_id ]
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'cliente_id'     => $client_id ?: null,
                    'agendamento_id' => null,
                    'plano_id'       => $sub_id,
                    'data'           => $date,
                    'valor'          => (float) $total_package,
                    'categoria'      => __( 'Assinatura', 'desi-pet-shower' ),
                    'tipo'           => 'receita',
                    'status'         => $status_fin,
                    'descricao'      => $desc_fin,
                ]
            );
        }

        DPS_Message_Helper::add_success( __( 'Agendamento de assinatura salvo com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'subscription_id'  => $sub_id,
            'appointment_ids'  => $created_ids,
            'appointment_type' => 'subscription',
        ];
    }

    /**
     * Cria agendamentos para múltiplos pets em um atendimento simples.
     *
     * Quando o usuário seleciona mais de um pet para um agendamento simples
     * (não assinatura), este método cria um agendamento individual para cada pet.
     *
     * @since 2.0.0
     * @param array  $data    Dados validados do formulário (de validate_and_sanitize_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    public static function create_multi_pet_appointments( array $data, $context = 'page' ) {
        $client_id         = $data['client_id'];
        $pet_ids           = $data['pet_ids'];
        $date              = $data['date'];
        $time              = $data['time'];
        $notes             = $data['notes'];
        $appt_type         = $data['appt_type'];
        $tosa              = $data['tosa'];
        $taxidog           = $data['taxidog'];
        $taxi_price        = $data['taxi_price'];
        $extra_description = $data['extra_description'];
        $extra_value       = $data['extra_value'];

        $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;

        $created = [];
        foreach ( $pet_ids as $p_id_each ) {
            $new_appt = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $time,
                'post_status' => 'publish',
            ] );

            if ( $new_appt ) {
                $created[] = (int) $new_appt;
                update_post_meta( $new_appt, 'appointment_client_id', $client_id );
                update_post_meta( $new_appt, 'appointment_pet_id', $p_id_each );
                update_post_meta( $new_appt, 'appointment_pet_ids', $pet_ids );
                update_post_meta( $new_appt, 'appointment_date', $date );
                update_post_meta( $new_appt, 'appointment_time', $time );
                update_post_meta( $new_appt, 'appointment_notes', $notes );
                update_post_meta( $new_appt, 'appointment_type', $appt_type );
                update_post_meta( $new_appt, 'appointment_tosa', $tosa );
                update_post_meta( $new_appt, 'appointment_taxidog', $taxidog );
                update_post_meta( $new_appt, 'appointment_taxidog_price', $taxi_price );
                update_post_meta( $new_appt, 'appointment_total_value', $posted_total );

                if ( '' !== $extra_description || $extra_value > 0 ) {
                    update_post_meta( $new_appt, 'appointment_extra_description', $extra_description );
                    update_post_meta( $new_appt, 'appointment_extra_value', $extra_value );
                }

                update_post_meta( $new_appt, 'appointment_status', 'pendente' );
                do_action( 'dps_base_after_save_appointment', $new_appt, 'simple' );
            }
        }

        if ( empty( $created ) ) {
            DPS_Message_Helper::add_error( __( 'Erro ao salvar agendamento.', 'desi-pet-shower' ) );
            return false;
        }

        DPS_Message_Helper::add_success( __( 'Agendamentos salvos com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'appointment_ids'  => $created,
            'appointment_type' => $appt_type,
        ];
    }

    /**
     * Salva ou atualiza um agendamento único (simple, subscription edit, ou past).
     *
     * Este método lida com a criação ou atualização de agendamentos individuais.
     * É usado para:
     * - Agendamentos simples de um único pet.
     * - Edição de agendamentos existentes (de qualquer tipo).
     * - Agendamentos passados (registro de atendimentos já realizados).
     *
     * @since 2.0.0
     * @param array  $data    Dados validados do formulário (de validate_and_sanitize_data).
     * @param string $context Contexto de execução (page|ajax).
     * @return array|false Dados do resultado ou false em caso de erro.
     */
    public static function save_single_appointment( array $data, $context = 'page' ) {
        $client_id                      = $data['client_id'];
        $pet_id                         = $data['pet_id'];
        $date                           = $data['date'];
        $time                           = $data['time'];
        $notes                          = $data['notes'];
        $appt_type                      = $data['appt_type'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $taxidog                        = $data['taxidog'];
        $taxi_price                     = $data['taxi_price'];
        $extra_description              = $data['extra_description'];
        $extra_value                    = $data['extra_value'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];
        $appt_id                        = $data['edit_id'];

        // Cria ou atualiza o post do agendamento.
        if ( $appt_id ) {
            wp_update_post( [
                'ID'         => $appt_id,
                'post_title' => $date . ' ' . $time,
            ] );
        } else {
            $appt_id = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $time,
                'post_status' => 'publish',
            ] );
        }

        if ( ! $appt_id ) {
            DPS_Message_Helper::add_error( __( 'Erro ao salvar agendamento.', 'desi-pet-shower' ) );
            return false;
        }

        // Salva metadados básicos.
        update_post_meta( $appt_id, 'appointment_client_id', $client_id );
        update_post_meta( $appt_id, 'appointment_pet_id', $pet_id );
        update_post_meta( $appt_id, 'appointment_date', $date );
        update_post_meta( $appt_id, 'appointment_time', $time );
        update_post_meta( $appt_id, 'appointment_notes', $notes );
        update_post_meta( $appt_id, 'appointment_type', $appt_type );
        update_post_meta( $appt_id, 'appointment_tosa', $tosa );
        update_post_meta( $appt_id, 'appointment_taxidog', $taxidog );

        // TaxiDog price is available for simple and past appointments
        if ( 'simple' === $appt_type || 'past' === $appt_type ) {
            update_post_meta( $appt_id, 'appointment_taxidog_price', $taxi_price );
        } else {
            update_post_meta( $appt_id, 'appointment_taxidog_price', 0 );
        }

        // Lógica específica por tipo de agendamento.
        if ( 'subscription' === $appt_type ) {
            self::save_subscription_meta( $appt_id, $data );
        } else {
            self::save_simple_or_past_meta( $appt_id, $data );
        }

        DPS_Message_Helper::add_success( __( 'Agendamento salvo com sucesso!', 'desi-pet-shower' ) );
        return [
            'client_id'        => $client_id,
            'appointment_id'   => $appt_id,
            'appointment_type' => $appt_type,
        ];
    }

    /**
     * Salva metadados específicos de agendamento tipo assinatura.
     *
     * Este método auxiliar é chamado por save_single_appointment() para
     * processar os campos específicos de assinaturas.
     *
     * @since 2.0.0
     * @param int   $appt_id ID do agendamento.
     * @param array $data    Dados validados do formulário.
     * @return void
     */
    public static function save_subscription_meta( $appt_id, array $data ) {
        $client_id                      = $data['client_id'];
        $pet_id                         = $data['pet_id'];
        $tosa                           = $data['tosa'];
        $tosa_price                     = $data['tosa_price'];
        $subscription_base_value        = $data['subscription_base_value'];
        $subscription_total_value       = $data['subscription_total_value'];
        $subscription_extra_description = $data['subscription_extra_description'];
        $subscription_extra_value       = $data['subscription_extra_value'];

        // Serviços padrão para assinaturas.
        $service_names = [ 'Tosa higienica', 'Hidratação' ];
        $service_ids   = [];
        $prices        = [];

        foreach ( $service_names as $sname ) {
            $srv = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'title'          => $sname,
            ] );
            if ( $srv ) {
                $srv_id            = $srv[0]->ID;
                $service_ids[]     = $srv_id;
                $base_price        = (float) get_post_meta( $srv_id, 'service_price', true );
                $prices[ $srv_id ] = $base_price;
            }
        }

        update_post_meta( $appt_id, 'appointment_services', $service_ids );
        update_post_meta( $appt_id, 'appointment_service_prices', $prices );

        $base_total = 0;
        foreach ( $prices as $p ) {
            $base_total += (float) $p;
        }

        $calculated_total = $base_total;
        if ( '1' === $tosa ) {
            $calculated_total += $tosa_price;
            update_post_meta( $appt_id, 'appointment_tosa_price', $tosa_price );
            update_post_meta( $appt_id, 'appointment_tosa_occurrence', 1 );
        } else {
            update_post_meta( $appt_id, 'appointment_tosa_price', 0 );
            update_post_meta( $appt_id, 'appointment_tosa_occurrence', 0 );
        }

        if ( $subscription_extra_value > 0 ) {
            $calculated_total += $subscription_extra_value;
        }

        $final_subscription_total = $subscription_total_value > 0 ? $subscription_total_value : $calculated_total;
        update_post_meta( $appt_id, 'appointment_total_value', $final_subscription_total );

        if ( $subscription_base_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_base_value', $subscription_base_value );
        } elseif ( $base_total > 0 ) {
            update_post_meta( $appt_id, 'subscription_base_value', $base_total );
        }

        if ( $subscription_total_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_total_value', $subscription_total_value );
        } else {
            update_post_meta( $appt_id, 'subscription_total_value', $final_subscription_total );
        }

        if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
            update_post_meta( $appt_id, 'subscription_extra_description', $subscription_extra_description );
            update_post_meta( $appt_id, 'subscription_extra_value', $subscription_extra_value );
        } else {
            delete_post_meta( $appt_id, 'subscription_extra_description' );
            delete_post_meta( $appt_id, 'subscription_extra_value' );
        }

        // Vincula assinatura existente, se houver.
        $subs = get_posts( [
            'post_type'      => 'dps_subscription',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'subscription_client_id', 'value' => $client_id, 'compare' => '=' ],
                [ 'key' => 'subscription_pet_id', 'value' => $pet_id, 'compare' => '=' ],
            ],
        ] );
        if ( $subs ) {
            update_post_meta( $appt_id, 'subscription_id', $subs[0]->ID );
        }
    }

    /**
     * Salva metadados específicos de agendamento simples ou passado.
     *
     * Este método auxiliar é chamado por save_single_appointment() para
     * processar campos de agendamentos simples e passados.
     *
     * @since 2.0.0
     * @param int   $appt_id ID do agendamento.
     * @param array $data    Dados validados do formulário.
     * @return void
     */
    public static function save_simple_or_past_meta( $appt_id, array $data ) {
        $appt_type         = $data['appt_type'];
        $extra_description = $data['extra_description'];
        $extra_value       = $data['extra_value'];

        $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
        update_post_meta( $appt_id, 'appointment_total_value', $posted_total );

        if ( '' !== $extra_description || $extra_value > 0 ) {
            update_post_meta( $appt_id, 'appointment_extra_description', $extra_description );
            update_post_meta( $appt_id, 'appointment_extra_value', $extra_value );
        } else {
            delete_post_meta( $appt_id, 'appointment_extra_description' );
            delete_post_meta( $appt_id, 'appointment_extra_value' );
        }

        // Lógica específica para agendamentos passados.
        if ( 'past' === $appt_type ) {
            $past_payment_status = isset( $_POST['past_payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['past_payment_status'] ) ) : '';
            $past_payment_value  = isset( $_POST['past_payment_value'] ) ? max( 0, floatval( str_replace( ',', '.', wp_unslash( $_POST['past_payment_value'] ) ) ) ) : 0;

            update_post_meta( $appt_id, 'past_payment_status', $past_payment_status );

            if ( 'pending' === $past_payment_status ) {
                update_post_meta( $appt_id, 'past_payment_value', $past_payment_value );
                // Pendente: marca como finalizado (aguardando pagamento)
                update_post_meta( $appt_id, 'appointment_status', 'finalizado' );
            } else {
                delete_post_meta( $appt_id, 'past_payment_value' );
                // Pago: marca como finalizado e pago
                update_post_meta( $appt_id, 'appointment_status', 'finalizado_pago' );
            }
        }
    }
}
