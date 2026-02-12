<?php
/**
 * Serviço de Checklist Operacional para agendamentos.
 *
 * Gerencia o checklist de etapas do banho e tosa (pré-banho, secagem, corte,
 * orelhas/unhas) com rastreamento de retrabalho por etapa.
 *
 * Meta key: '_dps_checklist' — array serializado com status por etapa.
 *
 * @package DPS_Agenda_Addon
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Checklist_Service {

    /**
     * Meta key para armazenar o checklist.
     *
     * @var string
     */
    const META_KEY = '_dps_checklist';

    /**
     * Etapas padrão do checklist operacional.
     *
     * Filtrável via 'dps_checklist_default_steps' para que add-ons possam
     * adicionar etapas extras (ex.: hidratação, perfume).
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function get_default_steps() {
        $steps = [
            'pre_bath'   => [
                'label' => __( 'Pré-banho (desembaraçar / escovação)', 'dps-agenda-addon' ),
                'icon'  => '🧹',
            ],
            'bath'       => [
                'label' => __( 'Banho', 'dps-agenda-addon' ),
                'icon'  => '🛁',
            ],
            'drying'     => [
                'label' => __( 'Secagem', 'dps-agenda-addon' ),
                'icon'  => '💨',
            ],
            'cutting'    => [
                'label' => __( 'Tosa / Corte', 'dps-agenda-addon' ),
                'icon'  => '✂️',
            ],
            'ears_nails' => [
                'label' => __( 'Orelhas / Unhas', 'dps-agenda-addon' ),
                'icon'  => '👂',
            ],
            'finishing'  => [
                'label' => __( 'Acabamento (perfume / laço)', 'dps-agenda-addon' ),
                'icon'  => '🎀',
            ],
        ];

        /**
         * Permite adicionar ou modificar etapas do checklist operacional.
         *
         * @since 1.2.0
         * @param array $steps Etapas padrão.
         */
        return apply_filters( 'dps_checklist_default_steps', $steps );
    }

    /**
     * Retorna o checklist atual de um agendamento.
     *
     * Se o agendamento ainda não tem checklist, retorna a estrutura inicial
     * com todas as etapas como 'pending'.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Checklist com status por etapa.
     */
    public static function get( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return [];
        }

        $saved = get_post_meta( $appointment_id, self::META_KEY, true );

        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return self::build_initial_checklist();
        }

        // Mescla com etapas padrão caso novas tenham sido adicionadas via filtro
        $defaults = self::build_initial_checklist();
        foreach ( $defaults as $key => $default_item ) {
            if ( ! isset( $saved[ $key ] ) ) {
                $saved[ $key ] = $default_item;
            }
        }

        return $saved;
    }

    /**
     * Atualiza uma etapa individual do checklist.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $step_key       Chave da etapa (ex.: 'drying').
     * @param string $new_status     Novo status: 'pending', 'done' ou 'skipped'.
     * @return bool True se atualizado com sucesso.
     */
    public static function update_step( $appointment_id, $step_key, $new_status ) {
        $appointment_id = absint( $appointment_id );
        $step_key       = sanitize_key( $step_key );
        $new_status     = sanitize_text_field( $new_status );

        $valid_statuses = [ 'pending', 'done', 'skipped' ];
        if ( ! $appointment_id || ! $step_key || ! in_array( $new_status, $valid_statuses, true ) ) {
            return false;
        }

        $checklist = self::get( $appointment_id );
        $steps     = self::get_default_steps();

        if ( ! isset( $steps[ $step_key ] ) ) {
            return false;
        }

        if ( ! isset( $checklist[ $step_key ] ) ) {
            $checklist[ $step_key ] = self::make_step_entry();
        }

        $checklist[ $step_key ]['status'] = $new_status;

        if ( 'done' === $new_status ) {
            $checklist[ $step_key ]['done_at'] = current_time( 'mysql' );
            $checklist[ $step_key ]['done_by'] = get_current_user_id();
        }

        return (bool) update_post_meta( $appointment_id, self::META_KEY, $checklist );
    }

    /**
     * Registra retrabalho em uma etapa.
     *
     * Reseta o status da etapa para 'pending' e adiciona uma entrada de
     * retrabalho com motivo e timestamp.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $step_key       Chave da etapa.
     * @param string $reason         Motivo do retrabalho.
     * @return bool True se registrado com sucesso.
     */
    public static function register_rework( $appointment_id, $step_key, $reason = '' ) {
        $appointment_id = absint( $appointment_id );
        $step_key       = sanitize_key( $step_key );
        $reason         = sanitize_textarea_field( $reason );

        if ( ! $appointment_id || ! $step_key ) {
            return false;
        }

        $checklist = self::get( $appointment_id );
        $steps     = self::get_default_steps();

        if ( ! isset( $steps[ $step_key ] ) ) {
            return false;
        }

        if ( ! isset( $checklist[ $step_key ] ) ) {
            $checklist[ $step_key ] = self::make_step_entry();
        }

        // Volta status para pending
        $checklist[ $step_key ]['status'] = 'pending';
        $checklist[ $step_key ]['done_at'] = '';
        $checklist[ $step_key ]['done_by'] = 0;

        // Adiciona entrada de retrabalho
        $checklist[ $step_key ]['rework'][] = [
            'reason'  => $reason,
            'time'    => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
        ];

        $updated = (bool) update_post_meta( $appointment_id, self::META_KEY, $checklist );

        if ( $updated ) {
            /**
             * Dispara quando uma etapa do checklist precisa de retrabalho.
             *
             * @since 1.2.0
             * @param int    $appointment_id ID do agendamento.
             * @param string $step_key       Chave da etapa.
             * @param string $reason         Motivo.
             */
            do_action( 'dps_checklist_rework_registered', $appointment_id, $step_key, $reason );
        }

        return $updated;
    }

    /**
     * Retorna o percentual de conclusão do checklist.
     *
     * @param int $appointment_id ID do agendamento.
     * @return int Percentual de 0 a 100.
     */
    public static function get_progress( $appointment_id ) {
        $checklist = self::get( $appointment_id );
        if ( empty( $checklist ) ) {
            return 0;
        }

        $total = count( $checklist );
        $done  = 0;

        foreach ( $checklist as $item ) {
            if ( isset( $item['status'] ) && in_array( $item['status'], [ 'done', 'skipped' ], true ) ) {
                $done++;
            }
        }

        return $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
    }

    /**
     * Verifica se o checklist está 100% concluído.
     *
     * @param int $appointment_id ID do agendamento.
     * @return bool
     */
    public static function is_complete( $appointment_id ) {
        return 100 === self::get_progress( $appointment_id );
    }

    /**
     * Conta o total de retrabalhos registrados em todas as etapas.
     *
     * @param int $appointment_id ID do agendamento.
     * @return int Total de retrabalhos.
     */
    public static function count_reworks( $appointment_id ) {
        $checklist = self::get( $appointment_id );
        $count     = 0;

        foreach ( $checklist as $item ) {
            if ( ! empty( $item['rework'] ) && is_array( $item['rework'] ) ) {
                $count += count( $item['rework'] );
            }
        }

        return $count;
    }

    /**
     * Constrói checklist inicial com todas as etapas como 'pending'.
     *
     * @return array
     */
    private static function build_initial_checklist() {
        $steps     = self::get_default_steps();
        $checklist = [];

        foreach ( $steps as $key => $step ) {
            $checklist[ $key ] = self::make_step_entry();
        }

        return $checklist;
    }

    /**
     * Cria uma entrada padrão de etapa.
     *
     * @return array
     */
    private static function make_step_entry() {
        return [
            'status'  => 'pending',
            'done_at' => '',
            'done_by' => 0,
            'rework'  => [],
        ];
    }
}
