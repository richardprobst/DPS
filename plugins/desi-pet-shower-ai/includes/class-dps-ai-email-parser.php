<?php
/**
 * Parser robusto para respostas de e-mail da IA.
 *
 * Este arquivo contém a classe responsável por fazer parse das respostas
 * da IA destinadas a e-mails, extraindo assunto, corpo e metadados de forma
 * robusta e defensiva.
 *
 * @package DPS_AI_Addon
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Parser de E-mail da IA.
 *
 * Faz parse defensivo e robusto de respostas de e-mail geradas pela IA,
 * com múltiplos fallbacks e tratamento de casos edge.
 */
class DPS_AI_Email_Parser {

	/**
	 * Formatos suportados de resposta.
	 *
	 * @var array
	 */
	const SUPPORTED_FORMATS = [
		'json',           // JSON estruturado: {"subject": "...", "body": "..."}
		'labeled',        // Com rótulos: "ASSUNTO: ...\n\nCORPO: ..."
		'separated',      // Separado por linha vazia
		'plain',          // Texto plano (fallback)
	];

	/**
	 * Padrões de rótulos conhecidos para assunto.
	 *
	 * @var array
	 */
	const SUBJECT_LABELS = [
		'ASSUNTO',
		'Subject',
		'SUBJECT',
		'Assunto',
		'Título',
		'TÍTULO',
		'Title',
	];

	/**
	 * Padrões de rótulos conhecidos para corpo.
	 *
	 * @var array
	 */
	const BODY_LABELS = [
		'CORPO',
		'Body',
		'BODY',
		'Corpo',
		'Mensagem',
		'MENSAGEM',
		'Message',
		'Conteúdo',
		'CONTEÚDO',
		'Content',
	];

	/**
	 * Faz parse da resposta de e-mail da IA.
	 *
	 * Tenta múltiplos formatos e estratégias de parsing até conseguir
	 * extrair assunto e corpo válidos.
	 *
	 * @param string $raw_response Resposta bruta da IA.
	 * @param array  $options      Opções de parsing:
	 *                             - 'default_subject' (string): Assunto padrão caso não consiga extrair.
	 *                             - 'max_subject_length' (int): Tamanho máximo do assunto (padrão: 200).
	 *                             - 'strip_html' (bool): Remover HTML do corpo (padrão: false).
	 *                             - 'format_hint' (string): Dica de formato esperado (json|labeled|etc).
	 *
	 * @return array|null Array com ['subject' => '...', 'body' => '...', 'format' => '...'] ou null em erro crítico.
	 */
	public static function parse( $raw_response, array $options = [] ) {
		// Normaliza opções
		$options = wp_parse_args(
			$options,
			[
				'default_subject'    => 'Comunicado do desi.pet by PRObst',
				'max_subject_length' => 200,
				'strip_html'         => false,
				'format_hint'        => null,
			]
		);

		// Valida entrada
		if ( empty( $raw_response ) || ! is_string( $raw_response ) ) {
			dps_ai_log_error( 'Email Parser: Resposta vazia ou inválida', [ 'raw_response_type' => gettype( $raw_response ) ] );
			return null;
		}

		$raw_response = trim( $raw_response );

		if ( '' === $raw_response ) {
			dps_ai_log_error( 'Email Parser: Resposta vazia após trim' );
			return null;
		}

		dps_ai_log_debug( 'Email Parser: Iniciando parse', [ 'response_length' => strlen( $raw_response ), 'format_hint' => $options['format_hint'] ] );

		// Tenta parse por formato, com ordem baseada em hint ou padrão
		$formats_to_try = self::SUPPORTED_FORMATS;

		// Se há hint de formato, tenta ele primeiro
		if ( ! empty( $options['format_hint'] ) && in_array( $options['format_hint'], self::SUPPORTED_FORMATS, true ) ) {
			$formats_to_try = array_unique( array_merge( [ $options['format_hint'] ], $formats_to_try ) );
		}

		foreach ( $formats_to_try as $format ) {
			$parsed = null;

			switch ( $format ) {
				case 'json':
					$parsed = self::try_parse_json( $raw_response );
					break;

				case 'labeled':
					$parsed = self::try_parse_labeled( $raw_response );
					break;

				case 'separated':
					$parsed = self::try_parse_separated( $raw_response );
					break;

				case 'plain':
					$parsed = self::try_parse_plain( $raw_response, $options );
					break;
			}

			// Se conseguiu parse, valida e retorna
			if ( null !== $parsed ) {
				$validated = self::validate_and_sanitize( $parsed, $options );

				if ( null !== $validated ) {
					dps_ai_log_info( 'Email Parser: Parse bem-sucedido', [ 'format' => $format, 'subject_length' => strlen( $validated['subject'] ), 'body_length' => strlen( $validated['body'] ) ] );
					return $validated;
				}
			}
		}

		// Se nenhum formato funcionou, usa fallback completo
		dps_ai_log_warning( 'Email Parser: Nenhum formato reconhecido, usando fallback completo' );

		return self::ultimate_fallback( $raw_response, $options );
	}

	/**
	 * Tenta fazer parse de JSON estruturado.
	 *
	 * @param string $response Resposta da IA.
	 *
	 * @return array|null Array com subject/body ou null.
	 */
	private static function try_parse_json( $response ) {
		// Tenta decodificar JSON diretamente
		$decoded = json_decode( $response, true );

		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			// Procura por campos conhecidos (case-insensitive)
			$subject = null;
			$body    = null;

			foreach ( $decoded as $key => $value ) {
				$key_lower = strtolower( $key );

				if ( in_array( $key_lower, [ 'subject', 'assunto', 'titulo', 'title' ], true ) ) {
					$subject = $value;
				}

				if ( in_array( $key_lower, [ 'body', 'corpo', 'mensagem', 'message', 'conteudo', 'content' ], true ) ) {
					$body = $value;
				}
			}

			if ( ! empty( $subject ) || ! empty( $body ) ) {
				dps_ai_log_debug( 'Email Parser: JSON parse successful' );
				return [
					'subject' => $subject ?? '',
					'body'    => $body ?? '',
					'format'  => 'json',
				];
			}
		}

		// Tenta encontrar JSON dentro da resposta (pode estar entre texto)
		if ( preg_match( '/\{[^}]*"(subject|assunto|body|corpo)"[^}]*\}/i', $response, $matches ) ) {
			$decoded = json_decode( $matches[0], true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$subject = $decoded['subject'] ?? $decoded['assunto'] ?? $decoded['Subject'] ?? $decoded['Assunto'] ?? '';
				$body    = $decoded['body'] ?? $decoded['corpo'] ?? $decoded['Body'] ?? $decoded['Corpo'] ?? '';

				if ( ! empty( $subject ) || ! empty( $body ) ) {
					dps_ai_log_debug( 'Email Parser: Embedded JSON parse successful' );
					return [
						'subject' => $subject,
						'body'    => $body,
						'format'  => 'json',
					];
				}
			}
		}

		return null;
	}

	/**
	 * Tenta fazer parse de formato com rótulos (ASSUNTO: / CORPO:).
	 *
	 * @param string $response Resposta da IA.
	 *
	 * @return array|null Array com subject/body ou null.
	 */
	private static function try_parse_labeled( $response ) {
		$subject = null;
		$body    = null;

		// Monta regex dinâmico com todos os rótulos conhecidos
		$subject_pattern = '(' . implode( '|', array_map( 'preg_quote', self::SUBJECT_LABELS ) ) . ')';
		$body_pattern    = '(' . implode( '|', array_map( 'preg_quote', self::BODY_LABELS ) ) . ')';

		// Tenta capturar: SUBJECT_LABEL: conteúdo \n\n BODY_LABEL: conteúdo
		$pattern = '/' . $subject_pattern . ':\s*(.+?)[\r\n]+.*?' . $body_pattern . ':\s*(.+)/is';

		if ( preg_match( $pattern, $response, $matches ) ) {
			$subject = trim( $matches[2] );
			$body    = trim( $matches[4] );

			dps_ai_log_debug( 'Email Parser: Labeled format parse successful' );

			return [
				'subject' => $subject,
				'body'    => $body,
				'format'  => 'labeled',
			];
		}

		// Tenta capturar apenas assunto (se corpo estiver em formato diferente)
		$pattern = '/' . $subject_pattern . ':\s*(.+?)[\r\n]+/i';

		if ( preg_match( $pattern, $response, $matches ) ) {
			$subject = trim( $matches[2] );

			// Remove o assunto da resposta e usa o resto como corpo
			$remaining = trim( preg_replace( $pattern, '', $response, 1 ) );

			// Tenta encontrar corpo com rótulo
			$pattern = '/' . $body_pattern . ':\s*(.+)/is';

			if ( preg_match( $pattern, $remaining, $matches ) ) {
				$body = trim( $matches[2] );
			} else {
				$body = $remaining;
			}

			if ( ! empty( $subject ) && ! empty( $body ) ) {
				dps_ai_log_debug( 'Email Parser: Partial labeled format parse successful' );

				return [
					'subject' => $subject,
					'body'    => $body,
					'format'  => 'labeled',
				];
			}
		}

		return null;
	}

	/**
	 * Tenta fazer parse de formato separado por linha vazia.
	 *
	 * @param string $response Resposta da IA.
	 *
	 * @return array|null Array com subject/body ou null.
	 */
	private static function try_parse_separated( $response ) {
		// Divide por linhas vazias (duplo line break)
		$parts = preg_split( '/\r?\n\r?\n/', $response, 2 );

		if ( count( $parts ) >= 2 ) {
			$subject = trim( strip_tags( $parts[0] ) );
			$body    = trim( $parts[1] );

			// Remove possíveis rótulos que sobraram
			$subject = preg_replace( '/^(' . implode( '|', array_map( 'preg_quote', self::SUBJECT_LABELS ) ) . '):\s*/i', '', $subject );
			$body    = preg_replace( '/^(' . implode( '|', array_map( 'preg_quote', self::BODY_LABELS ) ) . '):\s*/i', '', $body );

			if ( ! empty( $subject ) && ! empty( $body ) ) {
				dps_ai_log_debug( 'Email Parser: Separated format parse successful' );

				return [
					'subject' => $subject,
					'body'    => $body,
					'format'  => 'separated',
				];
			}
		}

		return null;
	}

	/**
	 * Tenta fazer parse de texto plano (fallback).
	 *
	 * Gera assunto padrão e usa todo o texto como corpo.
	 *
	 * @param string $response Resposta da IA.
	 * @param array  $options  Opções de parsing.
	 *
	 * @return array Array com subject/body.
	 */
	private static function try_parse_plain( $response, array $options ) {
		dps_ai_log_debug( 'Email Parser: Using plain format (fallback)' );

		return [
			'subject' => $options['default_subject'],
			'body'    => trim( $response ),
			'format'  => 'plain',
		];
	}

	/**
	 * Fallback absoluto quando nenhum formato funciona.
	 *
	 * @param string $response Resposta da IA.
	 * @param array  $options  Opções de parsing.
	 *
	 * @return array Array com subject/body usando defaults.
	 */
	private static function ultimate_fallback( $response, array $options ) {
		// Tenta extrair uma primeira linha razoável como assunto
		$lines       = preg_split( '/\r?\n/', $response );
		$first_line  = ! empty( $lines[0] ) ? trim( strip_tags( $lines[0] ) ) : '';
		$first_line  = preg_replace( '/^(' . implode( '|', array_map( 'preg_quote', self::SUBJECT_LABELS ) ) . '):\s*/i', '', $first_line );

		// Se primeira linha é razoável (não muito longa), usa como assunto
		if ( ! empty( $first_line ) && strlen( $first_line ) <= 150 && ! preg_match( '/[.!?]$/', $first_line ) ) {
			$subject = $first_line;
			// Remove primeira linha do corpo
			array_shift( $lines );
			$body = trim( implode( "\n", $lines ) );
		} else {
			$subject = $options['default_subject'];
			$body    = trim( $response );
		}

		dps_ai_log_warning( 'Email Parser: Ultimate fallback used', [ 'subject' => substr( $subject, 0, 50 ) ] );

		return [
			'subject' => $subject,
			'body'    => $body,
			'format'  => 'fallback',
		];
	}

	/**
	 * Valida e sanitiza resultado do parse.
	 *
	 * @param array $parsed  Resultado do parse.
	 * @param array $options Opções de parsing.
	 *
	 * @return array|null Array validado e sanitizado ou null se inválido.
	 */
	private static function validate_and_sanitize( $parsed, array $options ) {
		if ( ! is_array( $parsed ) || ! isset( $parsed['subject'], $parsed['body'] ) ) {
			dps_ai_log_error( 'Email Parser: Parsed result missing required fields' );
			return null;
		}

		// Sanitiza subject
		$subject = trim( strip_tags( $parsed['subject'] ) );
		$subject = sanitize_text_field( $subject );

		// Limita tamanho do assunto
		if ( strlen( $subject ) > $options['max_subject_length'] ) {
			$subject = substr( $subject, 0, $options['max_subject_length'] ) . '...';
		}

		// Sanitiza body
		$body = trim( $parsed['body'] );

		// Remove HTML se solicitado
		if ( $options['strip_html'] ) {
			$body = strip_tags( $body );
		}

		// Remove possíveis scripts perigosos do corpo (mesmo se mantendo HTML)
		$body = wp_kses_post( $body );

		// Valida que temos conteúdo mínimo
		if ( empty( $subject ) && empty( $body ) ) {
			dps_ai_log_error( 'Email Parser: Both subject and body are empty after sanitization' );
			return null;
		}

		// Se subject vazio, usa default
		if ( empty( $subject ) ) {
			$subject = $options['default_subject'];
			dps_ai_log_info( 'Email Parser: Using default subject (original was empty)' );
		}

		// Se body vazio, usa mensagem de fallback
		if ( empty( $body ) ) {
			$body = 'Olá! Esta é uma comunicação do desi.pet by PRObst.';
			dps_ai_log_warning( 'Email Parser: Using default body (original was empty)' );
		}

		return [
			'subject' => $subject,
			'body'    => $body,
			'format'  => $parsed['format'] ?? 'unknown',
		];
	}

	/**
	 * Converte corpo de texto em HTML básico.
	 *
	 * Útil quando a IA retorna texto plano mas queremos formatar como e-mail.
	 *
	 * @param string $text Texto plano.
	 *
	 * @return string HTML formatado.
	 */
	public static function text_to_html( $text ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Escapa HTML existente
		$text = esc_html( $text );

		// Converte quebras de linha duplas em parágrafos
		$text = preg_replace( '/\r?\n\r?\n/', '</p><p>', $text );

		// Converte quebras de linha simples em <br>
		$text = nl2br( $text );

		// Envolve em parágrafo
		$text = '<p>' . $text . '</p>';

		return $text;
	}

	/**
	 * Retorna estatísticas sobre a qualidade do parse.
	 *
	 * @param array $parsed Resultado do parse.
	 *
	 * @return array Array com estatísticas.
	 */
	public static function get_parse_stats( $parsed ) {
		if ( ! is_array( $parsed ) ) {
			return [];
		}

		return [
			'format'         => $parsed['format'] ?? 'unknown',
			'subject_length' => isset( $parsed['subject'] ) ? strlen( $parsed['subject'] ) : 0,
			'body_length'    => isset( $parsed['body'] ) ? strlen( $parsed['body'] ) : 0,
			'has_html'       => isset( $parsed['body'] ) && ( strip_tags( $parsed['body'] ) !== $parsed['body'] ),
			'subject_empty'  => empty( $parsed['subject'] ),
			'body_empty'     => empty( $parsed['body'] ),
		];
	}
}
