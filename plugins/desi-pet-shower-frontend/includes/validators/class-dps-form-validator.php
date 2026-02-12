<?php
/**
 * Validador de formulário de cadastro (Fase 7).
 *
 * Valida todos os campos do formulário de registro V2:
 * nome (obrigatório), email, telefone (obrigatório), CPF (opcional mod-11).
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Form_Validator extends DPS_Abstract_Validator {

    public function __construct(
        private readonly DPS_Cpf_Validator $cpfValidator,
    ) {}

    /**
     * Valida dados do formulário de cadastro.
     *
     * @param array<string, mixed> $data Dados sanitizados do formulário.
     * @return true|string[] True se válido, ou array de erros.
     */
    public function validate( array $data ): true|array {
        $errors = [];

        // Campos obrigatórios
        $this->requireField( $data, 'client_name', __( 'Nome', 'dps-frontend-addon' ), $errors );
        $this->requireField( $data, 'client_phone', __( 'Telefone', 'dps-frontend-addon' ), $errors );

        // Email: obrigatório e válido
        $this->requireField( $data, 'client_email', __( 'Email', 'dps-frontend-addon' ), $errors );
        if ( ! empty( $data['client_email'] ) ) {
            $this->validateEmail( $data['client_email'], $errors );
        }

        // Telefone: validação de formato via helper do base (se disponível)
        if ( ! empty( $data['client_phone'] ) ) {
            $this->validatePhone( $data['client_phone'], $errors );
        }

        // CPF: opcional, mas se preenchido deve ser válido (mod-11)
        if ( ! empty( $data['client_cpf'] ) ) {
            $cpfResult = $this->cpfValidator->validate( [ 'cpf' => $data['client_cpf'] ] );
            if ( true !== $cpfResult ) {
                $errors = array_merge( $errors, $cpfResult );
            }
        }

        // Pets: se enviados, validar campos obrigatórios de cada pet
        if ( ! empty( $data['pets'] ) && is_array( $data['pets'] ) ) {
            foreach ( $data['pets'] as $index => $pet ) {
                $petNum = $index + 1;
                if ( empty( $pet['pet_name'] ) ) {
                    $errors[] = sprintf(
                        /* translators: %d: pet number */
                        __( 'O nome do pet #%d é obrigatório.', 'dps-frontend-addon' ),
                        $petNum
                    );
                }
                if ( empty( $pet['pet_species'] ) ) {
                    $errors[] = sprintf(
                        /* translators: %d: pet number */
                        __( 'A espécie do pet #%d é obrigatória.', 'dps-frontend-addon' ),
                        $petNum
                    );
                }
            }
        }

        return [] === $errors ? true : $errors;
    }

    /**
     * Valida formato de telefone brasileiro.
     *
     * @param string   $phone   Telefone a validar.
     * @param string[] &$errors Array de erros (referência).
     */
    private function validatePhone( string $phone, array &$errors ): void {
        // Normaliza: remove tudo que não é dígito
        $digits = preg_replace( '/\D/', '', $phone );

        // Remove código do país (55) se presente
        if ( strlen( $digits ) >= 12 && str_starts_with( $digits, '55' ) ) {
            $digits = substr( $digits, 2 );
        }

        // Deve ter 10 (fixo) ou 11 (celular) dígitos
        $len = strlen( $digits );
        if ( $len < 10 || $len > 11 ) {
            $errors[] = __( 'O telefone informado não é válido. Use o formato (XX) XXXXX-XXXX.', 'dps-frontend-addon' );
            return;
        }

        // DDD: 11-99
        $ddd = (int) substr( $digits, 0, 2 );
        if ( $ddd < 11 || $ddd > 99 ) {
            $errors[] = __( 'O DDD informado não é válido.', 'dps-frontend-addon' );
        }
    }
}
