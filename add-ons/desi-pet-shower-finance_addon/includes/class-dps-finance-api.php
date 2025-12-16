<?php
/**
 * API Financeira Centralizada do DPS
 *
 * Fornece interface pública para operações financeiras, centralizando toda a lógica
 * de criação, atualização e consulta de cobranças/transações. Outros add-ons (como Agenda)
 * devem usar esta API em vez de manipular a tabela dps_transacoes diretamente.
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.1.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe estática que fornece API pública para operações financeiras.
 *
 * TODOS os add-ons que precisam criar, atualizar ou consultar transações financeiras
 * devem usar os métodos desta classe em vez de fazer queries diretas na tabela dps_transacoes.
 *
 * @since 1.1.0
 */
class DPS_Finance_API {

    /**
     * Verifica se uma tabela existe no banco de dados atual.
     *
     * @since 1.3.0
     *
     * @param string $table_name Nome completo da tabela (com prefixo).
     * @return bool True se a tabela existe, false caso contrário.
     */
    private static function table_exists( $table_name ) {
        global $wpdb;

        $table_exists = $wpdb->get_var( $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like( $table_name )
        ) );

        return $table_exists === $table_name;
    }

    /**
     * Criar ou atualizar cobrança vinculada a um agendamento.
     *
     * Este é o método principal usado pela Agenda e outros add-ons para registrar cobranças.
     * Se já existir transação para o agendamento, atualiza; caso contrário, cria nova.
     *
     * @since 1.1.0
     *
     * @param array $data Dados da cobrança.
     *     @type int    $appointment_id ID do agendamento (obrigatório).
     *     @type int    $client_id      ID do cliente (obrigatório).
     *     @type array  $services       Array de IDs de serviços (opcional, para descrição).
     *     @type int    $pet_id         ID do pet (opcional, para descrição).
     *     @type int    $value_cents    Valor em centavos (obrigatório).
     *     @type string $status         Status: 'pending'|'paid'|'cancelled' (opcional, padrão: 'pending').
     *     @type string $date           Data no formato Y-m-d (opcional, padrão: data do agendamento ou hoje).
     *
     * @return int|WP_Error ID da transação criada/atualizada ou WP_Error em caso de erro.
     */
    public static function create_or_update_charge( $data ) {
        // Valida dados obrigatórios
        $validation = self::validate_charge_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $appointment_id = absint( $data['appointment_id'] );
        $client_id      = absint( $data['client_id'] );
        $value_cents    = absint( $data['value_cents'] );
        $status         = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending';
        $services       = isset( $data['services'] ) && is_array( $data['services'] ) ? $data['services'] : [];
        $pet_id         = isset( $data['pet_id'] ) ? absint( $data['pet_id'] ) : 0;
        $date           = isset( $data['date'] ) ? sanitize_text_field( $data['date'] ) : '';

        // Mapeia status externo para interno
        $status_map = [
            'pending'   => 'em_aberto',
            'paid'      => 'pago',
            'cancelled' => 'cancelado',
        ];
        $internal_status = isset( $status_map[ $status ] ) ? $status_map[ $status ] : 'em_aberto';

        // Determina data da transação
        if ( empty( $date ) ) {
            $appt_date = get_post_meta( $appointment_id, 'appointment_date', true );
            $date = $appt_date ? $appt_date : current_time( 'Y-m-d' );
        }

        // Converte valor de centavos para float (formato do banco)
        $value_float = $value_cents / 100;

        // Monta descrição automaticamente
        $description = self::build_charge_description( $services, $pet_id );

        // Verifica se já existe transação para este agendamento
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE agendamento_id = %d",
            $appointment_id
        ) );

        $trans_data = [
            'cliente_id'     => $client_id,
            'agendamento_id' => $appointment_id,
            'plano_id'       => null,
            'data'           => $date,
            'valor'          => $value_float,
            'categoria'      => __( 'Serviço', 'dps-finance-addon' ),
            'tipo'           => 'receita',
            'status'         => $internal_status,
            'descricao'      => $description,
        ];

        if ( $existing_id ) {
            // Atualiza transação existente
            $wpdb->update(
                $table,
                [
                    'valor'     => $trans_data['valor'],
                    'status'    => $trans_data['status'],
                    'descricao' => $trans_data['descricao'],
                    'data'      => $trans_data['data'],
                ],
                [ 'id' => $existing_id ],
                [ '%f', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            /**
             * Disparado após atualizar uma cobrança existente.
             *
             * @since 1.1.0
             *
             * @param int $existing_id   ID da transação atualizada.
             * @param int $appointment_id ID do agendamento vinculado.
             */
            do_action( 'dps_finance_charge_updated', $existing_id, $appointment_id );

            return $existing_id;
        } else {
            // Cria nova transação
            $wpdb->insert(
                $table,
                $trans_data,
                [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s' ]
            );

            $new_id = $wpdb->insert_id;

            /**
             * Disparado após criar uma nova cobrança.
             *
             * @since 1.1.0
             *
             * @param int $new_id         ID da transação criada.
             * @param int $appointment_id ID do agendamento vinculado.
             */
            do_action( 'dps_finance_charge_created', $new_id, $appointment_id );

            return $new_id;
        }
    }

    /**
     * Marcar cobrança como paga.
     *
     * Atualiza status da transação para 'pago' e dispara hook dps_finance_booking_paid
     * para que outros add-ons (como Loyalty) possam reagir ao pagamento.
     *
     * @since 1.1.0
     *
     * @param int   $charge_id ID da transação.
     * @param array $options   Opções adicionais.
     *     @type string $paid_date      Data de pagamento Y-m-d (opcional, padrão: hoje).
     *     @type string $payment_method Método de pagamento (opcional).
     *     @type string $notes          Observações (opcional).
     *
     * @return true|WP_Error True em caso de sucesso, WP_Error em caso de erro.
     */
    public static function mark_as_paid( $charge_id, $options = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $charge_id = absint( $charge_id );
        if ( ! $charge_id ) {
            return new WP_Error( 'invalid_charge_id', __( 'ID de cobrança inválido.', 'dps-finance-addon' ) );
        }

        // Verifica se transação existe
        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $charge_id
        ) );

        if ( ! $transaction ) {
            return new WP_Error( 'charge_not_found', __( 'Cobrança não encontrada.', 'dps-finance-addon' ) );
        }

        // Atualiza status
        $wpdb->update(
            $table,
            [ 'status' => 'pago' ],
            [ 'id' => $charge_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Atualiza status do agendamento vinculado (se existir)
        if ( $transaction->agendamento_id ) {
            delete_post_meta( $transaction->agendamento_id, 'appointment_status' );
            add_post_meta( $transaction->agendamento_id, 'appointment_status', 'finalizado_pago', true );
        }

        /**
         * Disparado quando uma cobrança é marcada como paga.
         *
         * Hook mantido para compatibilidade com Loyalty e outros add-ons.
         *
         * @since 1.0.0
         *
         * @param int $charge_id  ID da transação.
         * @param int $client_id  ID do cliente.
         * @param int $value_cents Valor em centavos.
         */
        do_action(
            'dps_finance_booking_paid',
            $charge_id,
            (int) $transaction->cliente_id,
            (int) round( (float) $transaction->valor * 100 )
        );

        return true;
    }

    /**
     * Marcar cobrança como pendente.
     *
     * Útil para reabrir cobranças marcadas como pagas por engano.
     *
     * @since 1.1.0
     *
     * @param int $charge_id ID da transação.
     * @return true|WP_Error True em caso de sucesso, WP_Error em caso de erro.
     */
    public static function mark_as_pending( $charge_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $charge_id = absint( $charge_id );
        if ( ! $charge_id ) {
            return new WP_Error( 'invalid_charge_id', __( 'ID de cobrança inválido.', 'dps-finance-addon' ) );
        }

        // Verifica se transação existe
        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $charge_id
        ) );

        if ( ! $transaction ) {
            return new WP_Error( 'charge_not_found', __( 'Cobrança não encontrada.', 'dps-finance-addon' ) );
        }

        // Atualiza status
        $wpdb->update(
            $table,
            [ 'status' => 'em_aberto' ],
            [ 'id' => $charge_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Atualiza status do agendamento vinculado (se existir)
        if ( $transaction->agendamento_id ) {
            delete_post_meta( $transaction->agendamento_id, 'appointment_status' );
            add_post_meta( $transaction->agendamento_id, 'appointment_status', 'finalizado', true );
        }

        return true;
    }

    /**
     * Marcar cobrança como cancelada.
     *
     * @since 1.1.0
     *
     * @param int    $charge_id ID da transação.
     * @param string $reason    Motivo do cancelamento (opcional).
     * @return true|WP_Error True em caso de sucesso, WP_Error em caso de erro.
     */
    public static function mark_as_cancelled( $charge_id, $reason = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $charge_id = absint( $charge_id );
        if ( ! $charge_id ) {
            return new WP_Error( 'invalid_charge_id', __( 'ID de cobrança inválido.', 'dps-finance-addon' ) );
        }

        // Verifica se transação existe
        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $charge_id
        ) );

        if ( ! $transaction ) {
            return new WP_Error( 'charge_not_found', __( 'Cobrança não encontrada.', 'dps-finance-addon' ) );
        }

        // Atualiza status
        $wpdb->update(
            $table,
            [ 'status' => 'cancelado' ],
            [ 'id' => $charge_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Atualiza status do agendamento vinculado (se existir)
        if ( $transaction->agendamento_id ) {
            delete_post_meta( $transaction->agendamento_id, 'appointment_status' );
            add_post_meta( $transaction->agendamento_id, 'appointment_status', 'cancelado', true );
        }

        return true;
    }

    /**
     * Buscar dados de uma cobrança.
     *
     * @since 1.1.0
     *
     * @param int $charge_id ID da transação.
     * @return object|null Objeto com dados da transação ou null se não encontrada.
     */
    public static function get_charge( $charge_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $charge_id = absint( $charge_id );
        if ( ! $charge_id ) {
            return null;
        }

        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $charge_id
        ) );

        if ( ! $transaction ) {
            return null;
        }

        // Normaliza retorno
        return (object) [
            'id'             => (int) $transaction->id,
            'appointment_id' => (int) $transaction->agendamento_id,
            'client_id'      => (int) $transaction->cliente_id,
            'value_cents'    => (int) round( (float) $transaction->valor * 100 ),
            'status'         => self::normalize_status_to_external( $transaction->status ),
            'date'           => $transaction->data,
            'description'    => $transaction->descricao,
            'type'           => $transaction->tipo,
            'category'       => $transaction->categoria,
        ];
    }

    /**
     * Buscar todas as cobranças de um agendamento.
     *
     * @since 1.1.0
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Array de objetos (mesma estrutura de get_charge()).
     */
    public static function get_charges_by_appointment( $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return [];
        }

        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE agendamento_id = %d",
            $appointment_id
        ) );

        if ( ! $transactions ) {
            return [];
        }

        $charges = [];
        foreach ( $transactions as $transaction ) {
            $charges[] = (object) [
                'id'             => (int) $transaction->id,
                'appointment_id' => (int) $transaction->agendamento_id,
                'client_id'      => (int) $transaction->cliente_id,
                'value_cents'    => (int) round( (float) $transaction->valor * 100 ),
                'status'         => self::normalize_status_to_external( $transaction->status ),
                'date'           => $transaction->data,
                'description'    => $transaction->descricao,
                'type'           => $transaction->tipo,
                'category'       => $transaction->categoria,
            ];
        }

        return $charges;
    }

    /**
     * Remover todas as cobranças de um agendamento.
     *
     * Usado quando agendamento é excluído. Remove também parcelas vinculadas.
     *
     * @since 1.1.0
     *
     * @param int $appointment_id ID do agendamento.
     * @return int Número de transações removidas.
     */
    public static function delete_charges_by_appointment( $appointment_id ) {
        global $wpdb;
        $table          = $wpdb->prefix . 'dps_transacoes';
        $parcelas_table = $wpdb->prefix . 'dps_parcelas';

        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return 0;
        }

        // Evita erros quando tabelas ainda não foram criadas (ex.: plugin recém-instalado).
        if ( ! self::table_exists( $table ) ) {
            return 0;
        }

        // Busca IDs das transações a remover
        $trans_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE agendamento_id = %d",
            $appointment_id
        ) );

        if ( empty( $trans_ids ) ) {
            return 0;
        }

        // Remove parcelas vinculadas
        if ( self::table_exists( $parcelas_table ) ) {
            foreach ( $trans_ids as $trans_id ) {
                $wpdb->delete( $parcelas_table, [ 'trans_id' => $trans_id ], [ '%d' ] );
            }
        }

        // Remove transações
        $deleted = $wpdb->delete(
            $table,
            [ 'agendamento_id' => $appointment_id ],
            [ '%d' ]
        );

        /**
         * Disparado após deletar cobranças de um agendamento.
         *
         * @since 1.1.0
         *
         * @param int $appointment_id ID do agendamento.
         * @param int $deleted        Número de transações removidas.
         */
        do_action( 'dps_finance_charges_deleted', $appointment_id, $deleted );

        return (int) $deleted;
    }

    /**
     * Validar dados antes de criar/atualizar cobrança.
     *
     * @since 1.1.0
     *
     * @param array $data Dados a validar.
     * @return true|WP_Error True se válido, WP_Error com mensagens descritivas se inválido.
     */
    public static function validate_charge_data( $data ) {
        $errors = [];

        // Validação: appointment_id obrigatório e válido
        if ( empty( $data['appointment_id'] ) ) {
            $errors[] = __( 'ID do agendamento é obrigatório.', 'dps-finance-addon' );
        } elseif ( ! get_post( absint( $data['appointment_id'] ) ) ) {
            $errors[] = __( 'Agendamento não encontrado.', 'dps-finance-addon' );
        }

        // Validação: client_id obrigatório e válido
        if ( empty( $data['client_id'] ) ) {
            $errors[] = __( 'ID do cliente é obrigatório.', 'dps-finance-addon' );
        } elseif ( ! get_post( absint( $data['client_id'] ) ) ) {
            $errors[] = __( 'Cliente não encontrado.', 'dps-finance-addon' );
        }

        // Validação: value_cents obrigatório e positivo
        if ( ! isset( $data['value_cents'] ) ) {
            $errors[] = __( 'Valor é obrigatório.', 'dps-finance-addon' );
        } elseif ( ! is_numeric( $data['value_cents'] ) || (int) $data['value_cents'] < 0 ) {
            $errors[] = __( 'Valor deve ser maior ou igual a zero.', 'dps-finance-addon' );
        }

        // Validação: status válido
        if ( isset( $data['status'] ) ) {
            $valid_statuses = [ 'pending', 'paid', 'cancelled' ];
            if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
                $errors[] = __( 'Status inválido. Use: pending, paid ou cancelled.', 'dps-finance-addon' );
            }
        }

        // Validação: data no formato correto
        if ( isset( $data['date'] ) && ! empty( $data['date'] ) ) {
            $date_obj = DateTime::createFromFormat( 'Y-m-d', $data['date'] );
            if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $data['date'] ) {
                $errors[] = __( 'Data deve estar no formato Y-m-d (ex: 2025-01-15).', 'dps-finance-addon' );
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
        }

        return true;
    }

    /**
     * Montar descrição automaticamente a partir de serviços e pet.
     *
     * @since 1.1.0
     *
     * @param array $service_ids Array de IDs de serviços.
     * @param int   $pet_id      ID do pet.
     * @return string Descrição formatada.
     */
    private static function build_charge_description( $service_ids, $pet_id ) {
        $desc_parts = [];

        // Adiciona nomes dos serviços
        if ( ! empty( $service_ids ) && is_array( $service_ids ) ) {
            foreach ( $service_ids as $sid ) {
                $service = get_post( absint( $sid ) );
                if ( $service ) {
                    $desc_parts[] = $service->post_title;
                }
            }
        }

        // Adiciona nome do pet
        if ( $pet_id ) {
            $pet = get_post( absint( $pet_id ) );
            if ( $pet ) {
                $desc_parts[] = $pet->post_title;
            }
        }

        return ! empty( $desc_parts ) ? implode( ' - ', $desc_parts ) : __( 'Serviço', 'dps-finance-addon' );
    }

    /**
     * Normalizar status interno para externo.
     *
     * @since 1.1.0
     *
     * @param string $internal_status Status interno (em_aberto, pago, cancelado).
     * @return string Status externo (pending, paid, cancelled).
     */
    private static function normalize_status_to_external( $internal_status ) {
        $status_map = [
            'em_aberto' => 'pending',
            'pago'      => 'paid',
            'cancelado' => 'cancelled',
        ];

        return isset( $status_map[ $internal_status ] ) ? $status_map[ $internal_status ] : 'pending';
    }
}
