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
	 * Chave da opção no banco de dados para prompts customizados.
	 *
	 * @var string
	 */
	const CUSTOM_PROMPTS_OPTION = 'dps_ai_custom_prompts';

	/**
	 * Cache de prompts de arquivos carregados.
	 *
	 * @var array
	 */
	private static $file_cache = [];

	/**
	 * Cache de prompts customizados carregados do banco de dados.
	 *
	 * @var array|null
	 */
	private static $custom_prompts_cache = null;

	/**
	 * Retorna o system prompt para um contexto específico.
	 *
	 * Esta é a função principal que deve ser usada por todos os componentes do plugin.
	 * Prioriza prompts customizados do banco de dados, com fallback para arquivos padrão.
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

		// Tenta carregar prompt customizado do banco de dados primeiro
		$prompt = self::get_custom_prompt( $context );

		// Se não houver prompt customizado, carrega do arquivo padrão
		if ( empty( $prompt ) ) {
			$prompt = self::load_prompt_from_file( $context );
		}

		// Aplica filtro do WordPress para permitir customização
		// Filtro global: permite alterar qualquer prompt independente do contexto
		$prompt = apply_filters( 'dps_ai_system_prompt', $prompt, $context, $metadata );

		// Filtro específico por contexto: permite customização granular
		// Ex: 'dps_ai_system_prompt_portal', 'dps_ai_system_prompt_public', etc.
		$prompt = apply_filters( "dps_ai_system_prompt_{$context}", $prompt, $metadata );

		return $prompt;
	}

	/**
	 * Retorna o prompt customizado do banco de dados para um contexto.
	 *
	 * @param string $context Contexto do prompt.
	 *
	 * @return string|null Prompt customizado ou null se não existir.
	 */
	public static function get_custom_prompt( $context ) {
		$custom_prompts = self::load_custom_prompts();
		
		if ( isset( $custom_prompts[ $context ] ) ) {
			$trimmed = trim( $custom_prompts[ $context ] );
			if ( ! empty( $trimmed ) ) {
				return $trimmed;
			}
		}
		
		return null;
	}

	/**
	 * Carrega os prompts customizados do banco de dados com cache.
	 *
	 * @return array Array de prompts customizados.
	 */
	private static function load_custom_prompts() {
		if ( null === self::$custom_prompts_cache ) {
			self::$custom_prompts_cache = get_option( self::CUSTOM_PROMPTS_OPTION, [] );
		}
		return self::$custom_prompts_cache;
	}

	/**
	 * Invalida o cache de prompts customizados.
	 *
	 * @return void
	 */
	private static function invalidate_custom_prompts_cache() {
		self::$custom_prompts_cache = null;
	}

	/**
	 * Salva um prompt customizado para um contexto específico.
	 *
	 * @param string $context Contexto do prompt.
	 * @param string $prompt  Conteúdo do prompt.
	 *
	 * @return bool True se salvou com sucesso, false caso contrário.
	 */
	public static function save_custom_prompt( $context, $prompt ) {
		// Valida contexto
		if ( ! self::is_valid_context( $context ) ) {
			return false;
		}

		$custom_prompts = self::load_custom_prompts();
		$custom_prompts[ $context ] = sanitize_textarea_field( $prompt );
		
		// Invalida cache
		self::invalidate_custom_prompts_cache();
		
		return update_option( self::CUSTOM_PROMPTS_OPTION, $custom_prompts );
	}

	/**
	 * Remove o prompt customizado de um contexto, restaurando o padrão.
	 *
	 * @param string $context Contexto do prompt.
	 *
	 * @return bool True se removeu com sucesso, false caso contrário.
	 */
	public static function reset_to_default( $context ) {
		// Valida contexto
		if ( ! self::is_valid_context( $context ) ) {
			return false;
		}

		$custom_prompts = self::load_custom_prompts();
		
		if ( isset( $custom_prompts[ $context ] ) ) {
			unset( $custom_prompts[ $context ] );
			
			// Invalida cache
			self::invalidate_custom_prompts_cache();
			
			return update_option( self::CUSTOM_PROMPTS_OPTION, $custom_prompts );
		}
		
		return true; // Já estava no padrão
	}

	/**
	 * Verifica se um contexto tem prompt customizado.
	 *
	 * @param string $context Contexto a verificar.
	 *
	 * @return bool True se tem customizado, false se usa padrão.
	 */
	public static function has_custom_prompt( $context ) {
		return null !== self::get_custom_prompt( $context );
	}

	/**
	 * Retorna o prompt padrão do arquivo para um contexto.
	 *
	 * Usa o cache de arquivo para evitar leituras repetidas.
	 *
	 * @param string $context Contexto do prompt.
	 *
	 * @return string Prompt padrão do arquivo.
	 */
	public static function get_default_prompt( $context ) {
		return self::load_prompt_from_file( $context );
	}

	/**
	 * Retorna todos os prompts customizados salvos.
	 *
	 * @return array Array associativo [contexto => prompt].
	 */
	public static function get_all_custom_prompts() {
		return self::load_custom_prompts();
	}

	/**
	 * Carrega o prompt de um arquivo .txt no diretório /prompts.
	 *
	 * @param string $context     Nome do contexto (usado para construir o nome do arquivo).
	 * @param bool   $skip_cache  Se true, ignora o cache e lê diretamente do arquivo.
	 *
	 * @return string Conteúdo do prompt ou string vazia em caso de erro.
	 */
	private static function load_prompt_from_file( $context, $skip_cache = false ) {
		// Verifica se já está em cache
		if ( ! $skip_cache && isset( self::$file_cache[ $context ] ) ) {
			return self::$file_cache[ $context ];
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

		$trimmed_content = trim( $content );

		// Armazena em cache apenas se não estiver pulando cache
		if ( ! $skip_cache ) {
			self::$file_cache[ $context ] = $trimmed_content;
		}

		return $trimmed_content;
	}

	/**
	 * Retorna um prompt genérico de fallback caso ocorra algum erro.
	 *
	 * @return string Prompt de fallback.
	 */
	private static function get_fallback_prompt() {
		return 'Você é um assistente virtual especializado em Banho e Tosa do sistema desi.pet by PRObst. ' .
		       'Responda apenas sobre serviços de pet shop, agendamentos e funcionalidades do sistema. ' .
		       'Seja educado e profissional.';
	}

	/**
	 * Limpa o cache de prompts (útil para testes ou reload dinâmico).
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$file_cache = [];
		self::$custom_prompts_cache = null;
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
