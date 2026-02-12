<?php
/**
 * Classe base abstrata para validadores (Fase 7).
 *
 * Validadores encapsulam regras de validação específicas (CPF, booking, etc.).
 * Cada validador implementa validate() que retorna true ou array de erros.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class DPS_Abstract_Validator {

    /**
     * Valida os dados fornecidos.
     *
     * @param array<string, mixed> $data Dados a validar.
     * @return true|string[] True se válido, ou array de mensagens de erro.
     */
    abstract public function validate( array $data ): true|array;

    /**
     * Verifica se um campo obrigatório está presente e não vazio.
     *
     * @param array<string, mixed> $data     Dados do formulário.
     * @param string               $field    Nome do campo.
     * @param string               $label    Label do campo para mensagem de erro.
     * @param string[]             &$errors  Array de erros (passado por referência).
     */
    protected function requireField( array $data, string $field, string $label, array &$errors ): void {
        if ( empty( $data[ $field ] ) ) {
            $errors[] = sprintf(
                /* translators: %s: field label */
                __( 'O campo "%s" é obrigatório.', 'dps-frontend-addon' ),
                $label
            );
        }
    }

    /**
     * Valida formato de email.
     *
     * @param string   $email   Email a validar.
     * @param string[] &$errors Array de erros (passado por referência).
     */
    protected function validateEmail( string $email, array &$errors ): void {
        if ( '' !== $email && ! is_email( $email ) ) {
            $errors[] = __( 'O email informado não é válido.', 'dps-frontend-addon' );
        }
    }
}
