<?php
/**
 * Interface de Teste da Base de Conhecimento.
 *
 * Permite testar o matching de artigos e validar tamanho dos conteúdos.
 *
 * @package DPS_AI_Addon
 * @since 1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de Teste da Base de Conhecimento.
 */
class DPS_AI_Knowledge_Base_Tester {

	/**
	 * Instância única (singleton).
	 *
	 * @var DPS_AI_Knowledge_Base_Tester|null
	 */
	private static $instance = null;

	/**
	 * Recupera a instância única.
	 *
	 * @return DPS_AI_Knowledge_Base_Tester
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado.
	 */
	private function __construct() {
		// Registra página admin
		add_action( 'admin_menu', [ $this, 'register_admin_page' ], 26 );
		
		// Handler AJAX para teste de matching
		add_action( 'wp_ajax_dps_ai_kb_test_matching', [ $this, 'ajax_test_matching' ] );
		
		// Registra assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// Adiciona metabox de validação de tamanho na tela de edição do CPT
		add_action( 'add_meta_boxes', [ $this, 'add_size_validation_metabox' ] );
	}

	/**
	 * Registra página administrativa.
	 * 
	 * NOTA: A partir da v1.8.0, este menu está oculto (parent=null) para backward compatibility.
	 * Use o novo hub unificado em dps-ai-hub para acessar via aba "Testar Base".
	 */
	public function register_admin_page() {
		add_submenu_page(
			null, // Oculto do menu, acessível apenas por URL direta
			__( 'Teste da Base de Conhecimento', 'dps-ai' ),
			__( 'Testar Base de Conhecimento', 'dps-ai' ),
			'edit_posts',
			'dps-ai-kb-tester',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Registra assets da página.
	 *
	 * @param string $hook Hook da página atual.
	 */
	public function enqueue_assets( $hook ) {
		// Carrega apenas na página de teste
		if ( 'desi-pet-shower_page_dps-ai-kb-tester' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dps-ai-kb-tester',
			DPS_AI_ADDON_URL . 'assets/css/kb-tester.css',
			[],
			DPS_AI_VERSION
		);

		wp_enqueue_script(
			'dps-ai-kb-tester',
			DPS_AI_ADDON_URL . 'assets/js/kb-tester.js',
			[ 'jquery' ],
			DPS_AI_VERSION,
			true
		);

		wp_localize_script(
			'dps-ai-kb-tester',
			'dpsAiKbTester',
			[
				'nonce'   => wp_create_nonce( 'dps_ai_kb_test_matching' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => [
					'testing' => __( 'Testando...', 'dps-ai' ),
					'error'   => __( 'Erro ao testar', 'dps-ai' ),
				],
			]
		);
	}

	/**
	 * Renderiza a página administrativa de teste.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Teste da Base de Conhecimento', 'dps-ai' ); ?></h1>
			
			<p class="description">
				<?php esc_html_e( 'Digite uma pergunta para testar quais artigos da base de conhecimento seriam selecionados para responder. Isso usa a mesma lógica de matching usada em produção.', 'dps-ai' ); ?>
			</p>

			<div class="dps-ai-kb-test-container">
				<!-- Formulário de teste -->
				<div class="dps-ai-kb-test-form">
					<h2><?php esc_html_e( 'Pergunta de Teste', 'dps-ai' ); ?></h2>
					
					<textarea 
						id="dps-ai-test-question" 
						rows="4" 
						class="large-text"
						placeholder="<?php esc_attr_e( 'Digite uma pergunta de teste, por exemplo: Quanto custa um banho?', 'dps-ai' ); ?>"
					></textarea>
					
					<div style="margin-top: 10px;">
						<label>
							<?php esc_html_e( 'Limite de artigos:', 'dps-ai' ); ?>
							<input type="number" id="dps-ai-test-limit" value="5" min="1" max="10" style="width: 60px;" />
						</label>
						
						<button type="button" id="dps-ai-test-btn" class="button button-primary">
							<?php esc_html_e( 'Testar Matching', 'dps-ai' ); ?>
						</button>
					</div>
				</div>

				<!-- Resultados -->
				<div id="dps-ai-test-results" class="dps-ai-kb-test-results" style="display: none;">
					<h2><?php esc_html_e( 'Artigos Selecionados', 'dps-ai' ); ?></h2>
					<div id="dps-ai-test-results-content"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handler AJAX para teste de matching.
	 */
	public function ajax_test_matching() {
		// Verifica nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_kb_test_matching' ) ) {
			wp_send_json_error( [
				'message' => __( 'Falha na verificação de segurança.', 'dps-ai' ),
			] );
		}

		// Verifica permissão
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Você não tem permissão para realizar esta ação.', 'dps-ai' ),
			] );
		}

		// Obtém dados
		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
		$limit    = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;

		if ( empty( $question ) ) {
			wp_send_json_error( [
				'message' => __( 'Por favor, digite uma pergunta.', 'dps-ai' ),
			] );
		}

		// Usa a mesma função de matching usada em produção
		$articles = $this->get_relevant_articles_with_details( $question, $limit );

		// Calcula tamanho total do contexto
		$total_chars  = 0;
		$total_tokens = 0;

		foreach ( $articles as &$article ) {
			$size_info          = self::estimate_article_size( $article['content'] );
			$article['size']    = $size_info;
			$total_chars       += $size_info['chars'];
			$total_tokens      += $size_info['tokens_estimate'];
		}

		wp_send_json_success( [
			'articles'     => $articles,
			'count'        => count( $articles ),
			'total_chars'  => $total_chars,
			'total_tokens' => $total_tokens,
		] );
	}

	/**
	 * Obtém artigos relevantes com detalhes completos para teste.
	 *
	 * Usa a mesma lógica de DPS_AI_Knowledge_Base::get_relevant_articles()
	 * mas retorna mais informações para exibição.
	 *
	 * @param string $question Pergunta de teste.
	 * @param int    $limit    Limite de artigos.
	 *
	 * @return array Artigos com detalhes.
	 */
	private function get_relevant_articles_with_details( $question, $limit = 5 ) {
		$question_lower = mb_strtolower( $question, 'UTF-8' );
		$relevant       = [];

		// Busca todos os artigos ativos
		$articles = get_posts( [
			'post_type'      => DPS_AI_Knowledge_Base::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_dps_ai_active',
					'value'   => '1',
					'compare' => '=',
				],
			],
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_dps_ai_priority',
			'order'          => 'DESC',
		] );

		foreach ( $articles as $article ) {
			$keywords = get_post_meta( $article->ID, '_dps_ai_keywords', true );
			if ( empty( $keywords ) ) {
				continue;
			}

			// Verifica se alguma palavra-chave está na pergunta
			$keywords_array = array_map( 'trim', explode( ',', mb_strtolower( $keywords, 'UTF-8' ) ) );
			$matched_keywords = [];

			foreach ( $keywords_array as $keyword ) {
				if ( ! empty( $keyword ) && false !== mb_strpos( $question_lower, $keyword ) ) {
					$matched_keywords[] = $keyword;
				}
			}

			if ( ! empty( $matched_keywords ) ) {
				$priority = absint( get_post_meta( $article->ID, '_dps_ai_priority', true ) );
				$content  = wp_strip_all_tags( $article->post_content );
				
				$relevant[] = [
					'id'               => $article->ID,
					'title'            => $article->post_title,
					'priority'         => $priority,
					'keywords'         => $keywords,
					'matched_keywords' => $matched_keywords,
					'content'          => $content,
					'excerpt'          => self::get_article_excerpt( $content, 200 ),
				];
			}
		}

		// Ordena por prioridade e limita
		usort( $relevant, function( $a, $b ) {
			return $b['priority'] - $a['priority'];
		} );

		return array_slice( $relevant, 0, $limit );
	}

	/**
	 * Estima o tamanho de um artigo.
	 *
	 * @param string $content Conteúdo do artigo.
	 *
	 * @return array Array com informações de tamanho.
	 */
	public static function estimate_article_size( $content ) {
		$chars = mb_strlen( $content, 'UTF-8' );
		$words = str_word_count( $content );
		
		// Estimativa de tokens (aproximadamente 1 token = 4 caracteres em português)
		// Isso é uma aproximação simplificada
		$tokens_estimate = (int) ceil( $chars / 4 );
		
		// Classificação de tamanho
		if ( $chars < 500 ) {
			$classification = 'short';
			$label          = __( 'Curto', 'dps-ai' );
		} elseif ( $chars < 2000 ) {
			$classification = 'medium';
			$label          = __( 'Médio', 'dps-ai' );
		} else {
			$classification = 'long';
			$label          = __( 'Longo', 'dps-ai' );
		}

		return [
			'chars'           => $chars,
			'words'           => $words,
			'tokens_estimate' => $tokens_estimate,
			'classification'  => $classification,
			'label'           => $label,
		];
	}

	/**
	 * Obtém um resumo/trecho do artigo.
	 *
	 * @param string $content Conteúdo completo.
	 * @param int    $length  Tamanho máximo do trecho.
	 *
	 * @return string Trecho do artigo.
	 */
	private static function get_article_excerpt( $content, $length = 200 ) {
		if ( mb_strlen( $content, 'UTF-8' ) <= $length ) {
			return $content;
		}

		$excerpt = mb_substr( $content, 0, $length, 'UTF-8' );
		
		// Tenta quebrar em uma palavra completa
		$last_space = mb_strrpos( $excerpt, ' ', 0, 'UTF-8' );
		if ( false !== $last_space ) {
			$excerpt = mb_substr( $excerpt, 0, $last_space, 'UTF-8' );
		}

		return $excerpt . '...';
	}

	/**
	 * Adiciona metabox de validação de tamanho na tela de edição do CPT.
	 */
	public function add_size_validation_metabox() {
		add_meta_box(
			'dps_ai_kb_size_validation',
			__( 'Validação de Tamanho', 'dps-ai' ),
			[ $this, 'render_size_validation_metabox' ],
			DPS_AI_Knowledge_Base::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Renderiza metabox de validação de tamanho.
	 *
	 * @param WP_Post $post Post atual.
	 */
	public function render_size_validation_metabox( $post ) {
		$content = $post->post_content;
		
		if ( empty( $content ) ) {
			?>
			<p class="description">
				<?php esc_html_e( 'Adicione conteúdo ao artigo para ver a análise de tamanho.', 'dps-ai' ); ?>
			</p>
			<?php
			return;
		}

		$size_info = self::estimate_article_size( wp_strip_all_tags( $content ) );

		// Define classe CSS baseada na classificação
		$badge_class = '';
		$warning     = '';
		
		switch ( $size_info['classification'] ) {
			case 'short':
				$badge_class = 'dps-ai-size-short';
				break;
			case 'medium':
				$badge_class = 'dps-ai-size-medium';
				break;
			case 'long':
				$badge_class = 'dps-ai-size-long';
				$warning     = __( 'Artigos muito longos podem consumir muitos tokens. Considere resumir ou dividir em artigos menores.', 'dps-ai' );
				break;
		}

		?>
		<div class="dps-ai-size-info">
			<p>
				<strong><?php esc_html_e( 'Classificação:', 'dps-ai' ); ?></strong>
				<span class="dps-ai-size-badge <?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $size_info['label'] ); ?>
				</span>
			</p>
			
			<ul class="dps-ai-size-stats">
				<li>
					<strong><?php esc_html_e( 'Caracteres:', 'dps-ai' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $size_info['chars'] ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Palavras:', 'dps-ai' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $size_info['words'] ) ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Tokens (estimativa):', 'dps-ai' ); ?></strong>
					~<?php echo esc_html( number_format_i18n( $size_info['tokens_estimate'] ) ); ?>
				</li>
			</ul>

			<?php if ( ! empty( $warning ) ) : ?>
				<div class="notice notice-warning inline" style="margin: 10px 0 0 0;">
					<p><?php echo esc_html( $warning ); ?></p>
				</div>
			<?php endif; ?>

			<p class="description" style="margin-top: 10px;">
				<?php esc_html_e( 'Estimativa baseada em 1 token ≈ 4 caracteres (aproximação para português).', 'dps-ai' ); ?>
			</p>
		</div>

		<style>
			.dps-ai-size-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.dps-ai-size-short {
				background: #d1fae5;
				color: #065f46;
			}
			.dps-ai-size-medium {
				background: #fef3c7;
				color: #92400e;
			}
			.dps-ai-size-long {
				background: #fee2e2;
				color: #991b1b;
			}
			.dps-ai-size-stats {
				margin: 10px 0;
				padding-left: 20px;
			}
			.dps-ai-size-stats li {
				margin: 5px 0;
			}
		</style>
		<?php
	}
}
