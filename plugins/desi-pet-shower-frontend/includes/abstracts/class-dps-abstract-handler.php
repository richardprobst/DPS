<?php
/**
 * Classe base abstrata para handlers de formulário (Fase 7).
 *
 * Handlers processam submissões de formulário: validam, sanitizam,
 * persistem dados e retornam resultado. Cada handler recebe dependências
 * via construtor (DI).
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class DPS_Abstract_Handler {

    /**
     * Processa a submissão do formulário.
     *
     * @param array<string, mixed> $data Dados sanitizados do formulário.
     * @return array{success: bool, errors: string[], data: array<string, mixed>} Resultado do processamento.
     */
    abstract public function process( array $data ): array;

    /**
     * Retorna resultado de sucesso padronizado.
     *
     * @param array<string, mixed> $data Dados adicionais do resultado.
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    protected function success( array $data = [] ): array {
        return [
            'success' => true,
            'errors'  => [],
            'data'    => $data,
        ];
    }

    /**
     * Retorna resultado de erro padronizado.
     *
     * @param string[] $errors Lista de mensagens de erro.
     * @param array<string, mixed> $data Dados adicionais (ex.: form data para sticky form).
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    protected function error( array $errors, array $data = [] ): array {
        return [
            'success' => false,
            'errors'  => $errors,
            'data'    => $data,
        ];
    }
}
