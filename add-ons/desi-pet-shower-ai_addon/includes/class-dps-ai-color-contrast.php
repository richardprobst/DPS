<?php
/**
 * Utilitário para validação de contraste de cores (WCAG AA).
 *
 * Calcula luminância relativa e ratio de contraste para validar
 * acessibilidade de combinações de cores.
 *
 * @package DPS_AI_Addon
 * @since 1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe utilitária para validação de contraste.
 */
class DPS_AI_Color_Contrast {

	/**
	 * Ratio mínimo de contraste para WCAG AA (texto normal).
	 *
	 * @var float
	 */
	const WCAG_AA_NORMAL = 4.5;

	/**
	 * Ratio mínimo de contraste para WCAG AA (texto grande).
	 *
	 * @var float
	 */
	const WCAG_AA_LARGE = 3.0;

	/**
	 * Calcula a luminância relativa de uma cor.
	 *
	 * Baseado na fórmula WCAG 2.0:
	 * https://www.w3.org/TR/WCAG20/#relativeluminancedef
	 *
	 * @param string $hex_color Cor em formato hexadecimal (#RRGGBB ou #RGB).
	 *
	 * @return float|null Luminância relativa (0-1) ou null se cor inválida.
	 */
	public static function get_relative_luminance( $hex_color ) {
		// Remove # se presente
		$hex_color = ltrim( $hex_color, '#' );

		// Valida formato hex
		if ( ! preg_match( '/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $hex_color ) ) {
			return null;
		}

		// Expande formato curto (#RGB) para formato longo (#RRGGBB)
		if ( 3 === strlen( $hex_color ) ) {
			$hex_color = $hex_color[0] . $hex_color[0] .
			             $hex_color[1] . $hex_color[1] .
			             $hex_color[2] . $hex_color[2];
		}

		// Converte para RGB (0-255)
		$r = hexdec( substr( $hex_color, 0, 2 ) );
		$g = hexdec( substr( $hex_color, 2, 2 ) );
		$b = hexdec( substr( $hex_color, 4, 2 ) );

		// Normaliza para 0-1
		$r = $r / 255;
		$g = $g / 255;
		$b = $b / 255;

		// Aplica correção gamma
		$r = self::gamma_correct( $r );
		$g = self::gamma_correct( $g );
		$b = self::gamma_correct( $b );

		// Calcula luminância relativa
		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * Aplica correção gamma a um componente de cor.
	 *
	 * @param float $value Valor normalizado (0-1).
	 *
	 * @return float Valor corrigido.
	 */
	private static function gamma_correct( $value ) {
		if ( $value <= 0.03928 ) {
			return $value / 12.92;
		}
		return pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	/**
	 * Calcula o ratio de contraste entre duas cores.
	 *
	 * Baseado na fórmula WCAG 2.0:
	 * https://www.w3.org/TR/WCAG20/#contrast-ratiodef
	 *
	 * @param string $color1 Primeira cor (hex).
	 * @param string $color2 Segunda cor (hex).
	 *
	 * @return float|null Ratio de contraste (1-21) ou null se cores inválidas.
	 */
	public static function get_contrast_ratio( $color1, $color2 ) {
		$lum1 = self::get_relative_luminance( $color1 );
		$lum2 = self::get_relative_luminance( $color2 );

		if ( null === $lum1 || null === $lum2 ) {
			return null;
		}

		// Garante que L1 é a maior luminância
		$lighter = max( $lum1, $lum2 );
		$darker  = min( $lum1, $lum2 );

		// Calcula ratio: (L1 + 0.05) / (L2 + 0.05)
		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Verifica se o contraste atende aos requisitos WCAG AA.
	 *
	 * @param string $foreground Cor do primeiro plano (hex).
	 * @param string $background Cor do fundo (hex).
	 * @param bool   $is_large   Se o texto é grande (18pt+ ou 14pt+ bold).
	 *
	 * @return array Array com 'passes' (bool), 'ratio' (float), 'required' (float).
	 */
	public static function validate_contrast( $foreground, $background, $is_large = false ) {
		$ratio    = self::get_contrast_ratio( $foreground, $background );
		$required = $is_large ? self::WCAG_AA_LARGE : self::WCAG_AA_NORMAL;

		if ( null === $ratio ) {
			return [
				'passes'   => false,
				'ratio'    => null,
				'required' => $required,
				'error'    => __( 'Cores inválidas.', 'dps-ai' ),
			];
		}

		return [
			'passes'   => $ratio >= $required,
			'ratio'    => round( $ratio, 2 ),
			'required' => $required,
		];
	}

	/**
	 * Retorna uma mensagem de aviso sobre contraste.
	 *
	 * @param array $validation Resultado de validate_contrast().
	 *
	 * @return string Mensagem de aviso ou string vazia.
	 */
	public static function get_warning_message( $validation ) {
		if ( $validation['passes'] ) {
			return '';
		}

		if ( null === $validation['ratio'] ) {
			return $validation['error'] ?? __( 'Não foi possível calcular o contraste.', 'dps-ai' );
		}

		return sprintf(
			/* translators: 1: ratio atual, 2: ratio mínimo requerido */
			__( 'Atenção: a combinação de cores escolhida pode ficar difícil de ler (contraste %1$s:1, mínimo recomendado %2$s:1). Considere ajustar as cores para melhor legibilidade.', 'dps-ai' ),
			$validation['ratio'],
			$validation['required']
		);
	}
}
