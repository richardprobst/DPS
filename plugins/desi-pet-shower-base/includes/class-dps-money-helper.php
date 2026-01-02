<?php
/**
 * Helper class para manipulação de valores monetários.
 *
 * @package DesiPetShower
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe utilitária para conversão e formatação de valores monetários.
 */
class DPS_Money_Helper {

    /**
     * Converte string em formato brasileiro para centavos.
     *
     * Aceita entradas com vírgula ou ponto como separador decimal e remove separadores de milhar.
     *
     * Exemplos:
     * - "1.234,56" -> 123456
     * - "1234,56" -> 123456
     * - "1234.56" -> 123456
     * - "R$ 1.234,56" -> 123456
     *
     * @param string $money_string Valor em formato brasileiro.
     * @return int Valor em centavos.
     */
    public static function parse_brazilian_format( $money_string ) {
        $sanitized_string = trim( (string) $money_string );

        if ( '' === $sanitized_string ) {
            return 0;
        }

        // Remove caracteres não numéricos, exceto vírgula, ponto e sinal de menos
        $normalized = preg_replace( '/[^0-9,.-]/', '', $sanitized_string );
        $normalized = str_replace( ' ', '', $normalized );

        // Se houver vírgula, assume formato brasileiro e converte para formato padrão
        if ( false !== strpos( $normalized, ',' ) ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }

        $decimal_value = floatval( $normalized );
        return (int) round( $decimal_value * 100 );
    }

    /**
     * Formata valor em centavos para string no formato brasileiro.
     *
     * Exemplos:
     * - 123456 -> "1.234,56"
     * - 100 -> "1,00"
     * - 0 -> "0,00"
     *
     * @param int $cents Valor em centavos.
     * @return string Valor formatado no padrão brasileiro.
     */
    public static function format_to_brazilian( $cents ) {
        $decimal_value = (int) $cents / 100;
        return number_format( $decimal_value, 2, ',', '.' );
    }

    /**
     * Sanitiza e converte campo de preço do POST para float.
     *
     * Remove caracteres inválidos e garante que o valor seja não-negativo.
     *
     * @param string $field_name Nome do campo POST.
     * @return float Valor sanitizado (mínimo 0.0).
     */
    public static function sanitize_post_price_field( $field_name ) {
        if ( ! isset( $_POST[ $field_name ] ) ) {
            return 0.0;
        }

        $raw_value = wp_unslash( $_POST[ $field_name ] );
        $normalized = str_replace( ',', '.', (string) $raw_value );
        $float_value = floatval( $normalized );

        return max( 0.0, $float_value );
    }

    /**
     * Converte valor decimal para centavos.
     *
     * @param float $decimal_value Valor decimal.
     * @return int Valor em centavos.
     */
    public static function decimal_to_cents( $decimal_value ) {
        return (int) round( (float) $decimal_value * 100 );
    }

    /**
     * Converte centavos para valor decimal.
     *
     * @param int $cents Valor em centavos.
     * @return float Valor decimal.
     */
    public static function cents_to_decimal( $cents ) {
        return (int) $cents / 100;
    }

    /**
     * Formata valor decimal para exibição em formato brasileiro.
     *
     * @param float $decimal_value Valor decimal.
     * @return string Valor formatado.
     */
    public static function format_decimal_to_brazilian( $decimal_value ) {
        return number_format( (float) $decimal_value, 2, ',', '.' );
    }
}
