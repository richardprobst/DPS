<?php
/**
 * Serviço de Check-in / Check-out para agendamentos.
 *
 * Registra a entrada e saída do pet com observações rápidas e
 * itens de segurança (pulgas, feridinhas, alergia, etc.).
 *
 * Meta keys:
 *   '_dps_checkin'  — dados do check-in (hora, observações, itens de segurança).
 *   '_dps_checkout' — dados do check-out (hora, observações, itens de segurança).
 *
 * @package DPS_Agenda_Addon
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Checkin_Service {

    /**
     * Meta key para check-in.
     *
     * @var string
     */
    const META_CHECKIN = '_dps_checkin';

    /**
     * Meta key para check-out.
     *
     * @var string
     */
    const META_CHECKOUT = '_dps_checkout';

    /**
     * Retorna os itens de segurança padrão.
     *
     * Filtrável via 'dps_checkin_safety_items' para que add-ons possam
     * adicionar itens específicos (ex.: "carrapato", "dermatite").
     *
     * @return array<string, array{label: string, icon: string, severity: string}>
     */
    public static function get_safety_items() {
        $items = [
            'pulgas'      => [
                'label'    => __( 'Pulgas', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'warning',
            ],
            'carrapatos'  => [
                'label'    => __( 'Carrapatos', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'warning',
            ],
            'feridinhas'  => [
                'label'    => __( 'Feridinhas / Lesões', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'alert',
            ],
            'alergia'     => [
                'label'    => __( 'Alergia / Irritação', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'alert',
            ],
            'otite'       => [
                'label'    => __( 'Otite / Orelha inflamada', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'alert',
            ],
            'nos'         => [
                'label'    => __( 'Nós / Pelos embolados', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'info',
            ],
            'comportamento' => [
                'label'    => __( 'Agressivo / Ansioso', 'dps-agenda-addon' ),
                'icon'     => '',
                'severity' => 'warning',
            ],
        ];

        /**
         * Permite adicionar ou modificar itens de segurança do check-in/check-out.
         *
         * @since 1.2.0
         * @param array $items Itens padrão.
         */
        return apply_filters( 'dps_checkin_safety_items', $items );
    }

    /**
     * Registra o check-in de um agendamento.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $observations   Observações textuais.
     * @param array  $safety_items   Array de itens de segurança marcados (slug => detalhes).
     * @return bool True se registrado com sucesso.
     */
    public static function checkin( $appointment_id, $observations = '', $safety_items = [] ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return false;
        }

        $data = [
            'time'         => current_time( 'mysql' ),
            'timestamp'    => current_time( 'timestamp' ),
            'observations' => sanitize_textarea_field( $observations ),
            'safety_items' => self::sanitize_safety_items( $safety_items ),
            'user_id'      => get_current_user_id(),
        ];

        $updated = (bool) update_post_meta( $appointment_id, self::META_CHECKIN, $data );

        if ( $updated ) {
            /**
             * Dispara após o check-in ser registrado.
             *
             * @since 1.2.0
             * @param int   $appointment_id ID do agendamento.
             * @param array $data           Dados do check-in.
             */
            do_action( 'dps_appointment_checked_in', $appointment_id, $data );
        }

        return $updated;
    }

    /**
     * Registra o check-out de um agendamento.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $observations   Observações textuais.
     * @param array  $safety_items   Array de itens de segurança marcados (slug => detalhes).
     * @return bool True se registrado com sucesso.
     */
    public static function checkout( $appointment_id, $observations = '', $safety_items = [] ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return false;
        }

        $data = [
            'time'         => current_time( 'mysql' ),
            'timestamp'    => current_time( 'timestamp' ),
            'observations' => sanitize_textarea_field( $observations ),
            'safety_items' => self::sanitize_safety_items( $safety_items ),
            'user_id'      => get_current_user_id(),
        ];

        $updated = (bool) update_post_meta( $appointment_id, self::META_CHECKOUT, $data );

        if ( $updated ) {
            /**
             * Dispara após o check-out ser registrado.
             *
             * @since 1.2.0
             * @param int   $appointment_id ID do agendamento.
             * @param array $data           Dados do check-out.
             */
            do_action( 'dps_appointment_checked_out', $appointment_id, $data );
        }

        return $updated;
    }

    /**
     * Retorna os dados de check-in de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array|false Dados do check-in ou false se não existir.
     */
    public static function get_checkin( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return false;
        }

        $data = get_post_meta( $appointment_id, self::META_CHECKIN, true );
        return is_array( $data ) && ! empty( $data['time'] ) ? $data : false;
    }

    /**
     * Retorna os dados de check-out de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array|false Dados do check-out ou false se não existir.
     */
    public static function get_checkout( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return false;
        }

        $data = get_post_meta( $appointment_id, self::META_CHECKOUT, true );
        return is_array( $data ) && ! empty( $data['time'] ) ? $data : false;
    }

    /**
     * Verifica se o agendamento tem check-in registrado.
     *
     * @param int $appointment_id ID do agendamento.
     * @return bool
     */
    public static function has_checkin( $appointment_id ) {
        return false !== self::get_checkin( $appointment_id );
    }

    /**
     * Verifica se o agendamento tem check-out registrado.
     *
     * @param int $appointment_id ID do agendamento.
     * @return bool
     */
    public static function has_checkout( $appointment_id ) {
        return false !== self::get_checkout( $appointment_id );
    }

    /**
     * Calcula o tempo de permanência (check-in → check-out) em minutos.
     *
     * @param int $appointment_id ID do agendamento.
     * @return int|false Duração em minutos, ou false se não houver ambos.
     */
    public static function get_duration_minutes( $appointment_id ) {
        $checkin  = self::get_checkin( $appointment_id );
        $checkout = self::get_checkout( $appointment_id );

        if ( ! $checkin || ! $checkout ) {
            return false;
        }

        $in_ts  = intval( $checkin['timestamp'] );
        $out_ts = intval( $checkout['timestamp'] );

        if ( $out_ts <= $in_ts ) {
            return 0;
        }

        return (int) round( ( $out_ts - $in_ts ) / 60 );
    }

    /**
     * Retorna um resumo dos itens de segurança marcados no check-in.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Lista de itens de segurança com labels, ou array vazio.
     */
    public static function get_safety_summary( $appointment_id ) {
        $checkin = self::get_checkin( $appointment_id );
        if ( ! $checkin || empty( $checkin['safety_items'] ) ) {
            return [];
        }

        $all_items = self::get_safety_items();
        $summary   = [];

        foreach ( $checkin['safety_items'] as $slug => $details ) {
            if ( ! empty( $details['checked'] ) && isset( $all_items[ $slug ] ) ) {
                $summary[ $slug ] = [
                    'label'    => $all_items[ $slug ]['label'],
                    'icon'     => $all_items[ $slug ]['icon'],
                    'severity' => $all_items[ $slug ]['severity'],
                    'notes'    => isset( $details['notes'] ) ? $details['notes'] : '',
                ];
            }
        }

        return $summary;
    }

    /**
     * Sanitiza os itens de segurança recebidos do formulário.
     *
     * @param array $raw_items Itens vindos do POST.
     * @return array Itens sanitizados.
     */
    private static function sanitize_safety_items( $raw_items ) {
        if ( ! is_array( $raw_items ) ) {
            return [];
        }

        $valid_slugs = array_keys( self::get_safety_items() );
        $sanitized   = [];

        foreach ( $raw_items as $slug => $details ) {
            $slug = sanitize_key( $slug );
            if ( ! in_array( $slug, $valid_slugs, true ) ) {
                continue;
            }

            $sanitized[ $slug ] = [
                'checked' => ! empty( $details['checked'] ),
                'notes'   => isset( $details['notes'] ) ? sanitize_textarea_field( $details['notes'] ) : '',
            ];
        }

        return $sanitized;
    }
}
