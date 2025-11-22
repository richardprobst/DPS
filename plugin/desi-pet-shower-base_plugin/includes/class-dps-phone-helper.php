<?php
/**
 * Helper para formatação e validação de números de telefone
 *
 * Centraliza a lógica de formatação de telefones para WhatsApp e outros canais de comunicação.
 *
 * @package DesiPetShower
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe helper para operações com números de telefone
 */
class DPS_Phone_Helper {

    /**
     * Formata número de telefone para WhatsApp (formato internacional)
     *
     * Remove caracteres não numéricos e adiciona código do país (55 para Brasil)
     * se necessário.
     *
     * @param string $phone Número de telefone bruto (pode conter máscaras)
     * @return string Número formatado para WhatsApp (apenas dígitos com código do país)
     *
     * @example
     * DPS_Phone_Helper::format_for_whatsapp('(11) 98765-4321') // retorna '5511987654321'
     * DPS_Phone_Helper::format_for_whatsapp('11987654321')     // retorna '5511987654321'
     * DPS_Phone_Helper::format_for_whatsapp('5511987654321')   // retorna '5511987654321'
     */
    public static function format_for_whatsapp( $phone ) {
        // Remove todos os caracteres não numéricos
        $digits = preg_replace( '/\D/', '', (string) $phone );

        // Se tiver 10 ou 11 dígitos (telefone brasileiro sem código do país)
        // adiciona o código do país (55)
        if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 11 && substr( $digits, 0, 2 ) !== '55' ) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    /**
     * Formata número de telefone para exibição no formato brasileiro
     *
     * @param string $phone Número de telefone (pode conter máscaras)
     * @return string Número formatado para exibição
     *
     * @example
     * DPS_Phone_Helper::format_for_display('11987654321')     // retorna '(11) 98765-4321'
     * DPS_Phone_Helper::format_for_display('1134567890')      // retorna '(11) 3456-7890'
     */
    public static function format_for_display( $phone ) {
        $digits = preg_replace( '/\D/', '', (string) $phone );

        // Remove código do país se presente
        if ( strlen( $digits ) > 11 && substr( $digits, 0, 2 ) === '55' ) {
            $digits = substr( $digits, 2 );
        }

        // Formata conforme o tamanho
        if ( strlen( $digits ) === 11 ) {
            // Celular com 9 dígitos: (11) 98765-4321
            return sprintf( '(%s) %s-%s', 
                substr( $digits, 0, 2 ),
                substr( $digits, 2, 5 ),
                substr( $digits, 7, 4 )
            );
        } elseif ( strlen( $digits ) === 10 ) {
            // Telefone fixo: (11) 3456-7890
            return sprintf( '(%s) %s-%s',
                substr( $digits, 0, 2 ),
                substr( $digits, 2, 4 ),
                substr( $digits, 6, 4 )
            );
        }

        // Se não for formato reconhecido, retorna os dígitos
        return $digits;
    }

    /**
     * Valida se o número de telefone brasileiro é válido
     *
     * @param string $phone Número de telefone
     * @return bool True se válido, false caso contrário
     */
    public static function is_valid_brazilian_phone( $phone ) {
        $digits = preg_replace( '/\D/', '', (string) $phone );

        // Remove código do país se presente
        if ( substr( $digits, 0, 2 ) === '55' ) {
            $digits = substr( $digits, 2 );
        }

        // Telefone válido deve ter 10 (fixo) ou 11 (celular) dígitos
        $length = strlen( $digits );
        if ( $length !== 10 && $length !== 11 ) {
            return false;
        }

        // DDD deve estar entre 11 e 99
        $ddd = (int) substr( $digits, 0, 2 );
        if ( $ddd < 11 || $ddd > 99 ) {
            return false;
        }

        return true;
    }
}
