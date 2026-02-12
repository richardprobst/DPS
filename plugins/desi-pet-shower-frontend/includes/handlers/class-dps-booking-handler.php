<?php
/**
 * Handler de agendamento V2 (Fase 7.3).
 *
 * Processa submissão completa do wizard de booking.
 * Valida, cria agendamento, armazena confirmação e dispara hooks
 * de integração — inclui hook CRÍTICO para 8 add-ons consumidores.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Booking_Handler extends DPS_Abstract_Handler {

    public function __construct(
        private readonly DPS_Booking_Validator            $validator,
        private readonly DPS_Appointment_Service          $appointmentService,
        private readonly DPS_Booking_Confirmation_Service $confirmationService,
        private readonly DPS_Booking_Hook_Bridge          $hookBridge,
        private readonly DPS_Frontend_Logger              $logger,
    ) {}

    /**
     * Processa submissão completa do wizard de agendamento.
     *
     * @param array<string, mixed> $data Dados sanitizados do formulário.
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    public function process( array $data ): array {
        $this->hookBridge->beforeProcess( $data );

        // 1. Validação completa (todos os steps).
        $validation = $this->validator->validate( $data );
        if ( true !== $validation ) {
            return $this->error( $validation, $data );
        }

        // 2. Validação de extras (TaxiDog, Tosa).
        $extrasValidation = $this->validator->validateExtras( $data );
        if ( true !== $extrasValidation ) {
            return $this->error( $extrasValidation, $data );
        }

        // 3. Montar metas do agendamento.
        $petIds = array_map( 'absint', (array) ( $data['pet_ids'] ?? [] ) );
        $meta   = [
            'appointment_client_id'      => absint( $data['client_id'] ?? 0 ),
            'appointment_pet_id'         => $petIds[0] ?? 0,
            'appointment_pet_ids'        => $petIds,
            'appointment_date'           => sanitize_text_field( $data['appointment_date'] ?? '' ),
            'appointment_time'           => sanitize_text_field( $data['appointment_time'] ?? '' ),
            'appointment_status'         => 'pendente',
            'appointment_type'           => sanitize_text_field( $data['appointment_type'] ?? 'simple' ),
            'appointment_services'       => array_map( 'absint', (array) ( $data['service_ids'] ?? [] ) ),
            'appointment_service_prices' => array_map( 'floatval', (array) ( $data['service_prices'] ?? [] ) ),
            'appointment_total_value'    => (float) ( $data['total_value'] ?? 0 ),
            'appointment_notes'          => sanitize_textarea_field( $data['appointment_notes'] ?? '' ),
            'appointment_taxidog'        => ! empty( $data['appointment_taxidog'] ) ? 1 : 0,
            'appointment_taxidog_price'  => (float) ( $data['appointment_taxidog_price'] ?? 0 ),
            'appointment_tosa'           => ! empty( $data['appointment_tosa'] ) ? 1 : 0,
            'appointment_tosa_price'     => (float) ( $data['appointment_tosa_price'] ?? 30.00 ),
            'appointment_tosa_occurrence' => absint( $data['appointment_tosa_occurrence'] ?? 0 ),
        ];

        if ( ! empty( $data['subscription_id'] ) ) {
            $meta['subscription_id'] = absint( $data['subscription_id'] );
        }

        // 4. Criar agendamento.
        $appointmentId = $this->appointmentService->create( $meta );

        if ( false === $appointmentId ) {
            $this->logger->error( 'Falha ao criar agendamento.' );
            return $this->error(
                [ __( 'Erro ao criar agendamento. Tente novamente.', 'dps-frontend-addon' ) ],
                $data
            );
        }

        // 5. Armazenar confirmação.
        $userId = get_current_user_id();
        if ( $userId > 0 ) {
            $this->confirmationService->store( $userId, $appointmentId );
        }

        // 6. Hook CRÍTICO: 8 add-ons consomem dps_base_after_save_appointment.
        $this->hookBridge->afterAppointmentCreated( $appointmentId, $meta );

        // 7. Hook pós-processamento.
        $result = $this->success( [
            'appointment_id' => $appointmentId,
        ] );

        $this->hookBridge->afterProcess( $result, $data );

        $this->logger->info( "Agendamento V2 concluído: appointment #{$appointmentId}." );

        return $result;
    }
}
