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
     * @return true|array{messages: string[], field_errors: array<string, string>}
     */
    public function validate( array $data ): true|array {
        $messages    = [];
        $fieldErrors = [];

        if ( '' === trim( (string) ( $data['client_name'] ?? '' ) ) ) {
            $this->addFieldError(
                'client_name',
                __( 'Informe o nome completo do tutor.', 'dps-frontend-addon' ),
                $messages,
                $fieldErrors
            );
        }

        if ( '' === trim( (string) ( $data['client_email'] ?? '' ) ) ) {
            $this->addFieldError(
                'client_email',
                __( 'Informe um e-mail válido para o cadastro.', 'dps-frontend-addon' ),
                $messages,
                $fieldErrors
            );
        } elseif ( ! is_email( (string) $data['client_email'] ) ) {
            $this->addFieldError(
                'client_email',
                __( 'O e-mail informado não é válido.', 'dps-frontend-addon' ),
                $messages,
                $fieldErrors
            );
        }

        if ( '' === trim( (string) ( $data['client_phone'] ?? '' ) ) ) {
            $this->addFieldError(
                'client_phone',
                __( 'Informe o telefone ou WhatsApp do tutor.', 'dps-frontend-addon' ),
                $messages,
                $fieldErrors
            );
        } else {
            $phoneError = $this->validatePhone( (string) $data['client_phone'] );
            if ( '' !== $phoneError ) {
                $this->addFieldError( 'client_phone', $phoneError, $messages, $fieldErrors );
            }
        }

        if ( ! empty( $data['client_cpf'] ) ) {
            $cpfResult = $this->cpfValidator->validate( [ 'cpf' => $data['client_cpf'] ] );
            if ( true !== $cpfResult && ! empty( $cpfResult[0] ) ) {
                $this->addFieldError(
                    'client_cpf',
                    (string) $cpfResult[0],
                    $messages,
                    $fieldErrors
                );
            }
        }

        if ( ! empty( $data['pets'] ) && is_array( $data['pets'] ) ) {
            foreach ( $data['pets'] as $index => $pet ) {
                $petNum = $index + 1;

                if ( empty( $pet['pet_name'] ) ) {
                    $this->addFieldError(
                        'pets_' . $index . '_pet_name',
                        sprintf(
                            /* translators: %d: pet number */
                            __( 'Informe o nome do pet #%d.', 'dps-frontend-addon' ),
                            $petNum
                        ),
                        $messages,
                        $fieldErrors
                    );
                }

                if ( empty( $pet['pet_species'] ) ) {
                    $this->addFieldError(
                        'pets_' . $index . '_pet_species',
                        sprintf(
                            /* translators: %d: pet number */
                            __( 'Selecione a espécie do pet #%d.', 'dps-frontend-addon' ),
                            $petNum
                        ),
                        $messages,
                        $fieldErrors
                    );
                }

                if ( empty( $pet['pet_size'] ) ) {
                    $this->addFieldError(
                        'pets_' . $index . '_pet_size',
                        sprintf(
                            /* translators: %d: pet number */
                            __( 'Selecione o porte do pet #%d.', 'dps-frontend-addon' ),
                            $petNum
                        ),
                        $messages,
                        $fieldErrors
                    );
                }
            }
        }

        if ( [] === $messages ) {
            return true;
        }

        return [
            'messages'     => $messages,
            'field_errors' => $fieldErrors,
        ];
    }

    /**
     * Valida formato de telefone brasileiro.
     *
     * @param string $phone Telefone a validar.
     * @return string
     */
    private function validatePhone( string $phone ): string {
        // Normaliza: remove tudo que não é dígito
        $digits = preg_replace( '/\D/', '', $phone );

        // Remove código do país (55) se presente
        if ( strlen( $digits ) >= 12 && str_starts_with( $digits, '55' ) ) {
            $digits = substr( $digits, 2 );
        }

        // Deve ter 10 (fixo) ou 11 (celular) dígitos
        $len = strlen( $digits );
        if ( $len < 10 || $len > 11 ) {
            return __( 'O telefone informado não é válido. Use o formato (XX) XXXXX-XXXX.', 'dps-frontend-addon' );
        }

        // DDD: 11-99
        $ddd = (int) substr( $digits, 0, 2 );
        if ( $ddd < 11 || $ddd > 99 ) {
            return __( 'O DDD informado não é válido.', 'dps-frontend-addon' );
        }

        return '';
    }

    /**
     * Registra erro de campo e adiciona a mensagem ao resumo do formulário.
     *
     * @param string                $field       Chave interna do campo.
     * @param string                $message     Mensagem final para o usuário.
     * @param array<int, string>    &$messages   Lista geral de mensagens.
     * @param array<string, string> &$fieldErrors Mapa de erros por campo.
     */
    private function addFieldError( string $field, string $message, array &$messages, array &$fieldErrors ): void {
        if ( ! isset( $fieldErrors[ $field ] ) ) {
            $fieldErrors[ $field ] = $message;
        }

        $messages[] = $message;
    }
}
