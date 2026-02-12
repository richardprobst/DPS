<?php
/**
 * Validador de agendamento multi-step (Fase 7.3).
 *
 * Valida dados de cada step do wizard de booking V2:
 * Step 1 (cliente), Step 2 (pets), Step 3 (serviços),
 * Step 4 (data/hora), Step 5 (confirmação).
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Booking_Validator extends DPS_Abstract_Validator {

    public function __construct(
        private readonly DPS_Appointment_Service $appointmentService,
    ) {}

    /**
     * Valida todos os dados do agendamento (todos os steps).
     *
     * @param array<string, mixed> $data Dados completos do formulário.
     * @return true|string[] True se válido, ou array de erros.
     */
    public function validate( array $data ): true|array {
        $errors = [];

        for ( $step = 1; $step <= 5; $step++ ) {
            $result = $this->validateStep( $step, $data );

            if ( true !== $result ) {
                $errors = array_merge( $errors, $result );
            }
        }

        return [] === $errors ? true : $errors;
    }

    /**
     * Valida dados de um step específico.
     *
     * @param int                  $step Número do step (1-5).
     * @param array<string, mixed> $data Dados do formulário.
     * @return true|string[] True se válido, ou array de erros.
     */
    public function validateStep( int $step, array $data ): true|array {
        $errors = [];

        match ( $step ) {
            1 => $this->validateClient( $data, $errors ),
            2 => $this->validatePets( $data, $errors ),
            3 => $this->validateServices( $data, $errors ),
            4 => $this->validateDateTime( $data, $errors ),
            5 => null, // Confirmação: sem validação adicional.
            default => null,
        };

        return [] === $errors ? true : $errors;
    }

    /**
     * Valida extras opcionais (TaxiDog e Tosa).
     *
     * @param array<string, mixed> $data Dados do formulário.
     * @return true|string[] True se válido, ou array de erros.
     */
    public function validateExtras( array $data ): true|array {
        $errors = [];

        // TaxiDog: preço deve ser >= 0.
        if ( ! empty( $data['appointment_taxidog'] ) ) {
            $price = (float) ( $data['appointment_taxidog_price'] ?? 0 );
            if ( $price < 0 ) {
                $errors[] = __( 'O preço do TaxiDog não pode ser negativo.', 'dps-frontend-addon' );
            }
        }

        // Tosa: preço >= 0, ocorrência > 0 quando habilitado.
        if ( ! empty( $data['appointment_tosa'] ) ) {
            $tosaPrice = (float) ( $data['appointment_tosa_price'] ?? 0 );
            if ( $tosaPrice < 0 ) {
                $errors[] = __( 'O preço da tosa não pode ser negativo.', 'dps-frontend-addon' );
            }

            $occurrence = absint( $data['appointment_tosa_occurrence'] ?? 0 );
            if ( $occurrence < 1 ) {
                $errors[] = __( 'A frequência da tosa deve ser maior que zero.', 'dps-frontend-addon' );
            }
        }

        return [] === $errors ? true : $errors;
    }

    /**
     * Step 1: valida seleção de cliente.
     *
     * @param array<string, mixed> $data    Dados do formulário.
     * @param string[]             &$errors Array de erros (referência).
     */
    private function validateClient( array $data, array &$errors ): void {
        $clientId = absint( $data['client_id'] ?? 0 );

        if ( $clientId < 1 ) {
            $errors[] = __( 'Selecione um cliente para continuar.', 'dps-frontend-addon' );
        }
    }

    /**
     * Step 2: valida seleção de pets.
     *
     * @param array<string, mixed> $data    Dados do formulário.
     * @param string[]             &$errors Array de erros (referência).
     */
    private function validatePets( array $data, array &$errors ): void {
        $petIds = $data['pet_ids'] ?? [];

        if ( ! is_array( $petIds ) || [] === $petIds ) {
            $errors[] = __( 'Selecione pelo menos um pet para continuar.', 'dps-frontend-addon' );
            return;
        }

        foreach ( $petIds as $petId ) {
            if ( absint( $petId ) < 1 ) {
                $errors[] = __( 'ID de pet inválido.', 'dps-frontend-addon' );
                break;
            }
        }
    }

    /**
     * Step 3: valida seleção de serviços.
     *
     * @param array<string, mixed> $data    Dados do formulário.
     * @param string[]             &$errors Array de erros (referência).
     */
    private function validateServices( array $data, array &$errors ): void {
        $serviceIds = $data['service_ids'] ?? [];

        if ( ! is_array( $serviceIds ) || [] === $serviceIds ) {
            $errors[] = __( 'Selecione pelo menos um serviço para continuar.', 'dps-frontend-addon' );
            return;
        }

        foreach ( $serviceIds as $serviceId ) {
            if ( absint( $serviceId ) < 1 ) {
                $errors[] = __( 'ID de serviço inválido.', 'dps-frontend-addon' );
                break;
            }
        }
    }

    /**
     * Step 4: valida data e horário.
     *
     * @param array<string, mixed> $data    Dados do formulário.
     * @param string[]             &$errors Array de erros (referência).
     */
    private function validateDateTime( array $data, array &$errors ): void {
        $date = $data['appointment_date'] ?? '';
        $time = $data['appointment_time'] ?? '';
        $type = $data['appointment_type'] ?? 'simple';

        // Data obrigatória e formato YYYY-MM-DD.
        if ( '' === $date ) {
            $errors[] = __( 'A data do agendamento é obrigatória.', 'dps-frontend-addon' );
        } elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $errors[] = __( 'Formato de data inválido. Use AAAA-MM-DD.', 'dps-frontend-addon' );
        } elseif ( 'past' !== $type ) {
            // Não pode ser no passado (exceto tipo past).
            $today = current_time( 'Y-m-d' );
            if ( $date < $today ) {
                $errors[] = __( 'A data do agendamento não pode ser no passado.', 'dps-frontend-addon' );
            }
        }

        // Hora obrigatória e formato HH:MM.
        if ( '' === $time ) {
            $errors[] = __( 'O horário do agendamento é obrigatório.', 'dps-frontend-addon' );
        } elseif ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
            $errors[] = __( 'Formato de horário inválido. Use HH:MM.', 'dps-frontend-addon' );
        }

        // Conflito de horário (apenas para simple e subscription).
        if ( 'past' !== $type && '' !== $date && '' !== $time && [] === $errors ) {
            if ( $this->appointmentService->checkConflict( $date, $time ) ) {
                $errors[] = __( 'Já existe um agendamento neste horário. Escolha outro.', 'dps-frontend-addon' );
            }
        }
    }
}
