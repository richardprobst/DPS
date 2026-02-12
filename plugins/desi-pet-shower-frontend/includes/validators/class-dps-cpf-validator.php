<?php
/**
 * Validador de CPF (Fase 7).
 *
 * Implementa algoritmo Mod-11 para validação de CPF brasileiro.
 * Extraído do legado DPS_Registration_Addon::validate_cpf().
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Cpf_Validator extends DPS_Abstract_Validator {

    /**
     * Valida dados contendo campo CPF.
     *
     * CPF é opcional — se vazio, é válido.
     * Se preenchido, deve ter 11 dígitos e passar no mod-11.
     *
     * @param array<string, mixed> $data Dados com chave 'cpf'.
     * @return true|string[] True se válido, ou array de erros.
     */
    public function validate( array $data ): true|array {
        $cpf = $this->normalize( $data['cpf'] ?? '' );

        // CPF é opcional — vazio é válido
        if ( '' === $cpf ) {
            return true;
        }

        $errors = [];

        if ( ! $this->isValid( $cpf ) ) {
            $errors[] = __( 'O CPF informado não é válido.', 'dps-frontend-addon' );
        }

        return [] === $errors ? true : $errors;
    }

    /**
     * Normaliza CPF removendo caracteres não numéricos.
     *
     * @param string $cpf CPF bruto.
     * @return string Apenas dígitos.
     */
    public function normalize( string $cpf ): string {
        return preg_replace( '/\D/', '', $cpf );
    }

    /**
     * Valida CPF usando algoritmo Mod-11.
     *
     * @param string $cpf CPF normalizado (apenas dígitos).
     * @return bool Se o CPF é válido.
     */
    public function isValid( string $cpf ): bool {
        $cpf = $this->normalize( $cpf );

        if ( 11 !== strlen( $cpf ) ) {
            return false;
        }

        // Rejeita sequências de dígitos repetidos
        if ( 1 === preg_match( '/^(\d)\1{10}$/', $cpf ) ) {
            return false;
        }

        // Primeiro dígito verificador
        $sum = 0;
        for ( $i = 0; $i < 9; $i++ ) {
            $sum += (int) $cpf[ $i ] * ( 10 - $i );
        }
        $remainder = $sum % 11;
        $digit1    = ( $remainder < 2 ) ? 0 : ( 11 - $remainder );

        if ( (int) $cpf[9] !== $digit1 ) {
            return false;
        }

        // Segundo dígito verificador
        $sum = 0;
        for ( $i = 0; $i < 10; $i++ ) {
            $sum += (int) $cpf[ $i ] * ( 11 - $i );
        }
        $remainder = $sum % 11;
        $digit2    = ( $remainder < 2 ) ? 0 : ( 11 - $remainder );

        return (int) $cpf[10] === $digit2;
    }
}
