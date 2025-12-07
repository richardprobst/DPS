<?php
/**
 * Gerenciador centralizado de System Prompts da IA.
 *
 * Esta classe é responsável por:
 * - Carregar system prompts de arquivos
 * - Aplicar filtros do WordPress para permitir customização
 * - Gerenciar prompts por contexto (portal, público, whatsapp, email)
 * - Fornecer API consistente para todos os componentes do plugin
 *
 * @package DPS_AI_Addon
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe DPS_AI_Prompts.
 *
 * Centraliza toda a lógica de system prompts do plugin,
 * permitindo fácil manutenção e customização via filtros.
 */
class DPS_AI_Prompts {

	/**
	 * Contextos disponíveis para system prompts.
	 *
	 * @var array
	 */
	const CONTEXTS = [
		'portal'   => 'Portal do Cliente (chat autenticado)',
		'public'   => 'Chat público para visitantes',
		'whatsapp' => 'Mensagens via WhatsApp',
		'email'    => 'Conteúdo de e-mails',
	];

	/**
	 * Cache de prompts carregados.
	 *
	 * @var array
	 */
	private static $cache = [];

	/**
	 * Retorna o system prompt para um contexto específico.
	 *
	 * Esta é a função principal que deve ser usada por todos os componentes do plugin.
	 *
	 * @param string $context  Contexto do prompt ('portal', 'public', 'whatsapp', 'email').
	 * @param array  $metadata Metadados adicionais do contexto (opcional).
	 *
	 * @return string System prompt completo para o contexto solicitado.
	 */
	public static function get( $context, $metadata = [] ) {
		// Sanitiza o contexto
		$context = sanitize_key( $context );

		// Valida se o contexto existe
		if ( ! array_key_exists( $context, self::CONTEXTS ) ) {
			dps_ai_log_warning( 'Contexto de prompt inválido solicitado', [
				'context'  => $context,
				'metadata' => $metadata,
			] );
			// Fallback para portal
			$context = 'portal';
		}

		// Carrega o prompt base do arquivo
		$prompt = self::load_prompt_from_file( $context );

		// Aplica filtro do WordPress para permitir customização
		// Filtro global: permite alterar qualquer prompt independente do contexto
		$prompt = apply_filters( 'dps_ai_system_prompt', $prompt, $context, $metadata );

		// Filtro específico por contexto: permite customização granular
		// Ex: 'dps_ai_system_prompt_portal', 'dps_ai_system_prompt_public', etc.
		$prompt = apply_filters( "dps_ai_system_prompt_{$context}", $prompt, $metadata );

		return $prompt;
	}

	/**
	 * Carrega o prompt de um arquivo .txt no diretório /prompts.
	 *
	 * @param string $context Nome do contexto (usado para construir o nome do arquivo).
	 *
	 * @return string Conteúdo do prompt ou string vazia em caso de erro.
	 */
	private static function load_prompt_from_file( $context ) {
		// Verifica se já está em cache
		if ( isset( self::$cache[ $context ] ) ) {
			return self::$cache[ $context ];
		}

		// Monta o caminho do arquivo
		$file = dirname( __DIR__ ) . "/prompts/system-{$context}.txt";

		// Verifica se o arquivo existe
		if ( ! file_exists( $file ) ) {
			dps_ai_log_error( 'Arquivo de prompt não encontrado', [
				'context' => $context,
				'file'    => basename( $file ),
			] );
			// Retorna prompt genérico de fallback
			return self::get_fallback_prompt();
		}

		// Lê o conteúdo do arquivo
		$content = file_get_contents( $file );

		if ( false === $content ) {
			dps_ai_log_error( 'Falha ao ler arquivo de prompt', [
				'context' => $context,
				'file'    => basename( $file ),
			] );
			return self::get_fallback_prompt();
		}

		// Armazena em cache
		self::$cache[ $context ] = trim( $content );

		return self::$cache[ $context ];
	}

	/**
	 * Retorna um prompt genérico de fallback caso ocorra algum erro.
	 *
	 * @return string Prompt de fallback.
	 */
	private static function get_fallback_prompt() {
		return 'Você é um assistente virtual especializado em Banho e Tosa do sistema DPS by PRObst. ' .
		       'Responda apenas sobre serviços de pet shop, agendamentos e funcionalidades do sistema. ' .
		       'Seja educado e profissional.';
	}

	/**
	 * Limpa o cache de prompts (útil para testes ou reload dinâmico).
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache = [];
		dps_ai_log_debug( 'Cache de prompts limpo' );
	}

	/**
	 * Lista todos os contextos disponíveis.
	 *
	 * @return array Array associativo com contextos e suas descrições.
	 */
	public static function get_available_contexts() {
		return self::CONTEXTS;
	}

	/**
	 * Verifica se um contexto é válido.
	 *
	 * @param string $context Contexto a validar.
	 *
	 * @return bool True se válido, false caso contrário.
	 */
	public static function is_valid_context( $context ) {
		return array_key_exists( sanitize_key( $context ), self::CONTEXTS );
	}
}
