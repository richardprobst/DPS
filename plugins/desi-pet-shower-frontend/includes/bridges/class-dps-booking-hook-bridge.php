<?php
/**
 * Hook Bridge — Booking (Fase 7).
 *
 * Garante compatibilidade retroativa durante coexistência v1/v2.
 * Quando o V2 processa ações de agendamento, dispara AMBOS os hooks:
 * legado primeiro (8 consumidores existentes) e v2 depois.
 *
 * CRÍTICO: dps_base_after_save_appointment é consumido por:
 *   Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
 *
 * Regras:
 *   1. Hook legado PRIMEIRO, hook v2 DEPOIS
 *   2. Assinatura idêntica ao legado
 *   3. SEMPRE dispara ambos (sem condicionais)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Booking_Hook_Bridge {

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Dispara hooks após criação de agendamento no v2.
     * CRÍTICO: 8 add-ons consomem dps_base_after_save_appointment.
     *
     * @param int                  $appointmentId ID do agendamento criado.
     * @param array<string, mixed> $meta          Metas do agendamento.
     */
    public function afterAppointmentCreated( int $appointmentId, array $meta ): void {
        // 1. Hook LEGADO CRÍTICO primeiro (8 consumidores existentes)
        // Assinatura IDÊNTICA: ($appointment_id, $meta)
        do_action( 'dps_base_after_save_appointment', $appointmentId, $meta );

        // 2. Hook NOVO v2 (para extensões futuras)
        do_action( 'dps_booking_v2_appointment_created', $appointmentId, $meta );

        $this->logger->info( "Hook bridge: afterAppointmentCreated para appointment #{$appointmentId}." );
    }

    /**
     * Dispara hooks de campos do agendamento.
     * Permite que Services e Groomers injetem campos.
     *
     * @param int                  $editId ID do agendamento em edição (0 se novo).
     * @param array<string, mixed> $meta   Metas atuais.
     */
    public function appointmentFields( int $editId, array $meta ): void {
        do_action( 'dps_base_appointment_fields', $editId, $meta );
        do_action( 'dps_base_appointment_assignment_fields', $editId, $meta );
    }

    /**
     * Dispara hooks de validação de step (filtro novo).
     * Permite validações externas por step.
     *
     * @param bool                 $valid Se o step é válido.
     * @param int                  $step  Número do step (1-5).
     * @param array<string, mixed> $data  Dados do step.
     * @return bool Resultado após filtro.
     */
    public function validateStep( bool $valid, int $step, array $data ): bool {
        return (bool) apply_filters( 'dps_booking_v2_step_validate', $valid, $step, $data );
    }

    /**
     * Dispara hook antes da renderização do wizard v2.
     *
     * @param array<string, string> $atts Atributos do shortcode.
     */
    public function beforeRender( array $atts ): void {
        do_action( 'dps_booking_v2_before_render', $atts );
    }

    /**
     * Dispara hook ao renderizar um step do wizard.
     *
     * @param int                  $step Número do step.
     * @param array<string, mixed> $data Dados do step.
     */
    public function stepRender( int $step, array $data ): void {
        do_action( 'dps_booking_v2_step_render', $step, $data );
    }

    /**
     * Dispara hook antes de processar submissão v2.
     *
     * @param array<string, mixed> $data Dados do formulário.
     */
    public function beforeProcess( array $data ): void {
        do_action( 'dps_booking_v2_before_process', $data );
    }

    /**
     * Dispara hook após processar submissão v2.
     *
     * @param array{success: bool, errors: string[], data: array<string, mixed>} $result Resultado.
     * @param array<string, mixed> $data Dados originais.
     */
    public function afterProcess( array $result, array $data ): void {
        do_action( 'dps_booking_v2_after_process', $result, $data );
    }
}
