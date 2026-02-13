<?php
/**
 * Handlers de notificações push para eventos do DPS.
 *
 * Escuta hooks de agendamentos e dispara notificações push
 * para administradores via DPS_Push_API.
 *
 * @package DPS_Push_Addon
 * @since   2.0.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de handlers de notificações.
 *
 * @since 2.0.0
 */
class DPS_Push_Notifications {

    /**
     * Registra hooks para eventos de agendamento.
     *
     * @since 2.0.0
     */
    public function register_hooks() {
        add_action( 'dps_base_after_save_appointment', [ $this, 'notify_new_appointment' ], 20, 2 );
        add_action( 'dps_appointment_status_changed', [ $this, 'notify_status_change' ], 20, 4 );
        add_action( 'dps_appointment_rescheduled', [ $this, 'notify_rescheduled' ], 20, 5 );
    }

    /**
     * Notifica sobre novo agendamento.
     *
     * @since 1.0.0
     * @param int    $appt_id ID do agendamento.
     * @param string $mode    Modo do agendamento.
     */
    public function notify_new_appointment( $appt_id, $mode ) {
        $settings = get_option( DPS_Push_Addon::OPTION_KEY, [] );

        if ( empty( $settings['notify_new_appointment'] ) ) {
            return;
        }

        $client_id = get_post_meta( $appt_id, 'appointment_client_id', true );
        $pet_id    = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $date      = get_post_meta( $appt_id, 'appointment_date', true );
        $time      = get_post_meta( $appt_id, 'appointment_time', true );

        $client = get_post( $client_id );
        $pet    = get_post( $pet_id );

        $title = __( '📅 Novo Agendamento!', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s (%s) - %s às %s', 'dps-push-addon' ),
            $pet ? $pet->post_title : __( 'Pet', 'dps-push-addon' ),
            $client ? $client->post_title : __( 'Cliente', 'dps-push-addon' ),
            date_i18n( 'd/m/Y', strtotime( $date ) ),
            $time
        );

        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'tag'   => 'dps-new-appointment-' . $appt_id,
            'data'  => [
                'type'    => 'new_appointment',
                'appt_id' => $appt_id,
                'url'     => admin_url( 'admin.php?page=desi-pet-shower' ),
            ],
        ] );
    }

    /**
     * Notifica sobre mudança de status.
     *
     * @since 1.0.0
     * @param int    $appt_id    ID do agendamento.
     * @param string $old_status Status anterior.
     * @param string $new_status Novo status.
     * @param int    $user_id    ID do usuário que alterou.
     */
    public function notify_status_change( $appt_id, $old_status, $new_status, $user_id ) {
        $settings = get_option( DPS_Push_Addon::OPTION_KEY, [] );

        if ( empty( $settings['notify_status_change'] ) ) {
            return;
        }

        // Não notificar se o próprio usuário fez a alteração.
        $current_user_id = get_current_user_id();
        if ( $user_id === $current_user_id ) {
            return;
        }

        $status_labels = [
            'pendente'        => __( 'Pendente', 'dps-push-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-push-addon' ),
            'finalizado_pago' => __( 'Finalizado e Pago', 'dps-push-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-push-addon' ),
        ];

        $pet_id = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet    = get_post( $pet_id );
        $user   = get_userdata( $user_id );

        $title = __( '🔄 Status Alterado', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s: %s → %s (por %s)', 'dps-push-addon' ),
            $pet ? $pet->post_title : '#' . $appt_id,
            $status_labels[ $old_status ] ?? $old_status,
            $status_labels[ $new_status ] ?? $new_status,
            $user ? $user->display_name : __( 'Sistema', 'dps-push-addon' )
        );

        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'tag'   => 'dps-status-change-' . $appt_id,
            'data'  => [
                'type'    => 'status_change',
                'appt_id' => $appt_id,
            ],
        ], [ $user_id ] );
    }

    /**
     * Notifica sobre reagendamento.
     *
     * @since 1.0.0
     * @param int    $appt_id  ID do agendamento.
     * @param string $new_date Nova data.
     * @param string $new_time Novo horário.
     * @param string $old_date Data anterior.
     * @param string $old_time Horário anterior.
     */
    public function notify_rescheduled( $appt_id, $new_date, $new_time, $old_date, $old_time ) {
        $settings = get_option( DPS_Push_Addon::OPTION_KEY, [] );

        if ( empty( $settings['notify_rescheduled'] ) ) {
            return;
        }

        $pet_id = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet    = get_post( $pet_id );

        $title = __( '📅 Agendamento Reagendado', 'dps-push-addon' );
        $body  = sprintf(
            __( '%s: %s %s → %s %s', 'dps-push-addon' ),
            $pet ? $pet->post_title : '#' . $appt_id,
            date_i18n( 'd/m', strtotime( $old_date ) ),
            $old_time,
            date_i18n( 'd/m', strtotime( $new_date ) ),
            $new_time
        );

        DPS_Push_API::send_to_all_admins( [
            'title' => $title,
            'body'  => $body,
            'tag'   => 'dps-rescheduled-' . $appt_id,
            'data'  => [
                'type'    => 'rescheduled',
                'appt_id' => $appt_id,
            ],
        ], [ get_current_user_id() ] );
    }
}
