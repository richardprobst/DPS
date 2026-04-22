<?php
/**
 * ServiÃ§o de Check-in / Check-out para agendamentos.
 *
 * Registra a entrada e saÃ­da do pet com observaÃ§Ãµes rÃ¡pidas e
 * itens de seguranÃ§a (pulgas, feridinhas, alergia, etc.).
 *
 * Meta keys:
 *   '_dps_checkin'  â€” dados do check-in (hora, observaÃ§Ãµes, itens de seguranÃ§a).
 *   '_dps_checkout' â€” dados do check-out (hora, observaÃ§Ãµes, itens de seguranÃ§a).
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
     * Retorna os itens de seguranÃ§a padrÃ£o.
     *
     * FiltrÃ¡vel via 'dps_checkin_safety_items' para que add-ons possam
     * adicionar itens especÃ­ficos (ex.: "carrapato", "dermatite").
     *
     * @return array<string, array{label: string, icon: string, severity: string}>
     */
    public static function get_safety_items() {
        $items = [
            'pulgas'      => [
                'label'    => __( 'Pulgas', 'dps-agenda-addon' ),
                'icon'     => 'ðŸª²',
                'severity' => 'warning',
            ],
            'carrapatos'  => [
                'label'    => __( 'Carrapatos', 'dps-agenda-addon' ),
                'icon'     => 'ðŸ•·ï¸',
                'severity' => 'warning',
            ],
            'feridinhas'  => [
                'label'    => __( 'Feridinhas / LesÃµes', 'dps-agenda-addon' ),
                'icon'     => 'ðŸ©¹',
                'severity' => 'alert',
            ],
            'alergia'     => [
                'label'    => __( 'Alergia / IrritaÃ§Ã£o', 'dps-agenda-addon' ),
                'icon'     => 'âš ï¸',
                'severity' => 'alert',
            ],
            'otite'       => [
                'label'    => __( 'Otite / Orelha inflamada', 'dps-agenda-addon' ),
                'icon'     => 'ðŸ‘‚',
                'severity' => 'alert',
            ],
            'nos'         => [
                'label'    => __( 'NÃ³s / Pelos embolados', 'dps-agenda-addon' ),
                'icon'     => 'ðŸ§¶',
                'severity' => 'info',
            ],
            'comportamento' => [
                'label'    => __( 'Agressivo / Ansioso', 'dps-agenda-addon' ),
                'icon'     => 'ðŸ˜¤',
                'severity' => 'warning',
            ],
        ];

        /**
         * Permite adicionar ou modificar itens de seguranÃ§a do check-in/check-out.
         *
         * @since 1.2.0
         * @param array $items Itens padrÃ£o.
         */
        return apply_filters( 'dps_checkin_safety_items', $items );
    }

    /**
     * Registra o check-in de um agendamento.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $observations   ObservaÃ§Ãµes textuais.
     * @param array  $safety_items   Array de itens de seguranÃ§a marcados (slug => detalhes).
     * @return bool True se registrado com sucesso.
     */
    public static function checkin( $appointment_id, $observations = '', $safety_items = [] ) {
        return self::save_stage( $appointment_id, self::META_CHECKIN, $observations, $safety_items, 'dps_appointment_checked_in' );
    }

    /**
     * Registra o check-out de um agendamento.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $observations   ObservaÃ§Ãµes textuais.
     * @param array  $safety_items   Array de itens de seguranÃ§a marcados (slug => detalhes).
     * @return bool True se registrado com sucesso.
     */
    public static function checkout( $appointment_id, $observations = '', $safety_items = [] ) {
        return self::save_stage( $appointment_id, self::META_CHECKOUT, $observations, $safety_items, 'dps_appointment_checked_out' );
    }

    /**
     * Salva uma etapa operacional preservando o horÃ¡rio original quando jÃ¡ existir registro.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $meta_key       Meta key da etapa.
     * @param string $observations   ObservaÃ§Ãµes textuais.
     * @param array  $safety_items   Array de itens de seguranÃ§a marcados (slug => detalhes).
     * @param string $hook_name      Hook disparado apÃ³s o salvamento.
     * @return bool
     */
    private static function save_stage( $appointment_id, $meta_key, $observations, $safety_items, $hook_name ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return false;
        }

        $existing = get_post_meta( $appointment_id, $meta_key, true );
        $existing = is_array( $existing ) ? $existing : [];
        $is_edit  = ! empty( $existing['time'] );

        $time      = $is_edit ? $existing['time'] : current_time( 'mysql' );
        $timestamp = $is_edit && isset( $existing['timestamp'] ) ? intval( $existing['timestamp'] ) : current_time( 'timestamp' );
        $user_id   = $is_edit && ! empty( $existing['user_id'] ) ? absint( $existing['user_id'] ) : get_current_user_id();

        $data = [
            'time'         => $time,
            'timestamp'    => $timestamp,
            'observations' => sanitize_textarea_field( $observations ),
            'safety_items' => self::sanitize_safety_items( $safety_items ),
            'user_id'      => $user_id,
            'updated_at'   => current_time( 'mysql' ),
            'updated_by'   => get_current_user_id(),
        ];

        $updated = (bool) update_post_meta( $appointment_id, $meta_key, $data );

        if ( $updated ) {
            /**
             * Dispara apÃ³s a etapa operacional ser salva.
             *
             * @since 1.2.0
             * @param int   $appointment_id ID do agendamento.
             * @param array $data           Dados salvos.
             */
            do_action( $hook_name, $appointment_id, $data, $existing );
        }

        return $updated;
    }

    /**
     * Retorna os dados de check-in de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array|false Dados do check-in ou false se nÃ£o existir.
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
     * @return array|false Dados do check-out ou false se nÃ£o existir.
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
     * Calcula o tempo de permanÃªncia (check-in â†’ check-out) em minutos.
     *
     * @param int $appointment_id ID do agendamento.
     * @return int|false DuraÃ§Ã£o em minutos, ou false se nÃ£o houver ambos.
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
     * Retorna um resumo dos itens de seguranÃ§a marcados no check-in.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Lista de itens de seguranÃ§a com labels, ou array vazio.
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
     * Sanitiza os itens de seguranÃ§a recebidos do formulÃ¡rio.
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
