<?php
/**
 * Interface Administrativa para Gerenciar Base de Conhecimento.
 *
 * Permite edição rápida de keywords e prioridades dos artigos
 * sem precisar entrar em cada post individual.
 *
 * @package DPS_AI_Addon
 * @since 1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de Interface Administrativa da Base de Conhecimento.
 */
class DPS_AI_Knowledge_Base_Admin {

	/**
	 * Instância única (singleton).
	 *
	 * @var DPS_AI_Knowledge_Base_Admin|null
	 */
	private static $instance = null;

	/**
	 * Recupera a instância única.
	 *
	 * @return DPS_AI_Knowledge_Base_Admin
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
		add_action( 'admin_menu', [ $this, 'register_admin_page' ], 25 );
		
		// Handler AJAX para edição rápida
		add_action( 'wp_ajax_dps_ai_kb_quick_edit', [ $this, 'ajax_quick_edit' ] );
		
		// Registra assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Registra página administrativa.
	 * 
		 * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
		 * Também acessível pelo hub em dps-ai-hub (aba "Base de Conhecimento").
		 */
		public function register_admin_page() {
			add_submenu_page(
				'desi-pet-shower',
				__( 'Gerenciar Base de Conhecimento', 'dps-ai' ),
				__( 'Base de Conhecimento', 'dps-ai' ),
				'edit_posts', // Capability para editar posts
				'dps-ai-knowledge-base',
				[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Registra assets da página.
	 *
	 * @param string $hook Hook da página atual.
	 */
	public function enqueue_assets( $hook ) {
		// Carrega apenas na página de gerenciamento
		if ( 'desi-pet-shower_page_dps-ai-knowledge-base' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dps-ai-kb-admin',
			DPS_AI_ADDON_URL . 'assets/css/kb-admin.css',
			[],
			DPS_AI_VERSION
		);

		wp_enqueue_script(
			'dps-ai-kb-admin',
			DPS_AI_ADDON_URL . 'assets/js/kb-admin.js',
			[ 'jquery' ],
			DPS_AI_VERSION,
			true
		);

		wp_localize_script(
			'dps-ai-kb-admin',
			'dpsAiKbAdmin',
			[
				'nonce'   => wp_create_nonce( 'dps_ai_kb_quick_edit' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => [
					'saving'       => __( 'Salvando...', 'dps-ai' ),
					'saved'        => __( 'Salvo!', 'dps-ai' ),
					'error'        => __( 'Erro ao salvar', 'dps-ai' ),
					'confirmReset' => __( 'Tem certeza que deseja limpar os filtros?', 'dps-ai' ),
				],
			]
		);
	}

	/**
	 * Renderiza a página administrativa.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
		}

		// Processa filtros
		$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_priority = isset( $_GET['priority'] ) ? sanitize_text_field( wp_unslash( $_GET['priority'] ) ) : '';
		$orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'priority';
		$order           = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Monta query
		$args = [
			'post_type'      => DPS_AI_Knowledge_Base::POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_dps_ai_priority',
			'order'          => $order,
		];

		// Filtro de busca
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Filtro de prioridade
		if ( ! empty( $filter_priority ) ) {
			$priority_ranges = [
				'high'   => [ 8, 10 ],
				'medium' => [ 4, 7 ],
				'low'    => [ 1, 3 ],
			];

			if ( isset( $priority_ranges[ $filter_priority ] ) ) {
				$args['meta_query'] = [
					[
						'key'     => '_dps_ai_priority',
						'value'   => $priority_ranges[ $filter_priority ],
						'type'    => 'NUMERIC',
						'compare' => 'BETWEEN',
					],
				];
			}
		}

		// Ordenação customizada
		if ( 'title' === $orderby ) {
			$args['orderby']  = 'title';
			$args['meta_key'] = '';
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gerenciar Base de Conhecimento', 'dps-ai' ); ?></h1>
			
			<p class="description">
				<?php esc_html_e( 'Gerencie keywords e prioridades dos artigos da base de conhecimento. As keywords são usadas para fazer matching com perguntas dos clientes, e a prioridade determina a ordem de relevância.', 'dps-ai' ); ?>
			</p>

			<!-- Filtros -->
			<div class="dps-ai-kb-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="dps-ai-knowledge-base" />
					
					<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 20px 0;">
						<!-- Busca -->
						<input 
							type="search" 
							name="s" 
							value="<?php echo esc_attr( $search ); ?>" 
							placeholder="<?php esc_attr_e( 'Buscar por título ou keywords...', 'dps-ai' ); ?>"
							style="width: 300px;"
						/>
						
						<!-- Filtro de prioridade -->
						<select name="priority" style="width: 150px;">
							<option value=""><?php esc_html_e( 'Todas prioridades', 'dps-ai' ); ?></option>
							<option value="high" <?php selected( $filter_priority, 'high' ); ?>><?php esc_html_e( 'Alta (8-10)', 'dps-ai' ); ?></option>
							<option value="medium" <?php selected( $filter_priority, 'medium' ); ?>><?php esc_html_e( 'Média (4-7)', 'dps-ai' ); ?></option>
							<option value="low" <?php selected( $filter_priority, 'low' ); ?>><?php esc_html_e( 'Baixa (1-3)', 'dps-ai' ); ?></option>
						</select>
						
						<!-- Ordenação -->
						<select name="orderby" style="width: 150px;">
							<option value="priority" <?php selected( $orderby, 'priority' ); ?>><?php esc_html_e( 'Ordenar por Prioridade', 'dps-ai' ); ?></option>
							<option value="title" <?php selected( $orderby, 'title' ); ?>><?php esc_html_e( 'Ordenar por Título', 'dps-ai' ); ?></option>
						</select>
						
						<select name="order" style="width: 100px;">
							<option value="DESC" <?php selected( $order, 'DESC' ); ?>><?php esc_html_e( 'DESC', 'dps-ai' ); ?></option>
							<option value="ASC" <?php selected( $order, 'ASC' ); ?>><?php esc_html_e( 'ASC', 'dps-ai' ); ?></option>
						</select>
						
						<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'dps-ai' ); ?></button>
						
						<?php if ( ! empty( $search ) || ! empty( $filter_priority ) ) : ?>
							<a href="?page=dps-ai-knowledge-base" class="button"><?php esc_html_e( 'Limpar Filtros', 'dps-ai' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- Tabela de artigos -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'Título', 'dps-ai' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Keywords', 'dps-ai' ); ?></th>
						<th style="width: 10%;"><?php esc_html_e( 'Prioridade', 'dps-ai' ); ?></th>
						<th style="width: 10%;"><?php esc_html_e( 'Status', 'dps-ai' ); ?></th>
						<th style="width: 15%;"><?php esc_html_e( 'Ações', 'dps-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $posts ) ) : ?>
						<?php foreach ( $posts as $post ) : ?>
							<?php
							$keywords = get_post_meta( $post->ID, '_dps_ai_keywords', true );
							$priority = get_post_meta( $post->ID, '_dps_ai_priority', true );
							$active   = get_post_meta( $post->ID, '_dps_ai_active', true );

							if ( '' === $priority ) {
								$priority = 5;
							}
							?>
							<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
								<!-- Título -->
								<td>
									<strong>
										<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
											<?php echo esc_html( $post->post_title ); ?>
										</a>
									</strong>
								</td>
								
								<!-- Keywords -->
								<td class="dps-ai-kb-keywords-cell">
									<div class="dps-ai-kb-display">
										<?php if ( ! empty( $keywords ) ) : ?>
											<code><?php echo esc_html( $keywords ); ?></code>
										<?php else : ?>
											<em><?php esc_html_e( 'Nenhuma keyword definida', 'dps-ai' ); ?></em>
										<?php endif; ?>
									</div>
									<div class="dps-ai-kb-edit" style="display: none;">
										<textarea class="large-text" rows="2"><?php echo esc_textarea( $keywords ); ?></textarea>
										<p class="description"><?php esc_html_e( 'Separe por vírgula', 'dps-ai' ); ?></p>
									</div>
								</td>
								
								<!-- Prioridade -->
								<td class="dps-ai-kb-priority-cell">
									<div class="dps-ai-kb-display">
										<strong><?php echo esc_html( $priority ); ?></strong>
										<?php
										$badge_class = '';
										if ( $priority >= 8 ) {
											$badge_class = 'dps-ai-badge-high';
											$badge_text  = __( 'Alta', 'dps-ai' );
										} elseif ( $priority >= 4 ) {
											$badge_class = 'dps-ai-badge-medium';
											$badge_text  = __( 'Média', 'dps-ai' );
										} else {
											$badge_class = 'dps-ai-badge-low';
											$badge_text  = __( 'Baixa', 'dps-ai' );
										}
										?>
										<br /><span class="dps-ai-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
									</div>
									<div class="dps-ai-kb-edit" style="display: none;">
										<input type="number" min="1" max="10" value="<?php echo esc_attr( $priority ); ?>" style="width: 60px;" />
										<p class="description"><?php esc_html_e( '1-10', 'dps-ai' ); ?></p>
									</div>
								</td>
								
								<!-- Status -->
								<td>
									<?php
									$status_map = [
										'publish' => [ 'text' => __( 'Publicado', 'dps-ai' ), 'class' => 'dps-ai-status-publish' ],
										'draft'   => [ 'text' => __( 'Rascunho', 'dps-ai' ), 'class' => 'dps-ai-status-draft' ],
										'pending' => [ 'text' => __( 'Pendente', 'dps-ai' ), 'class' => 'dps-ai-status-pending' ],
									];

									$status_info = isset( $status_map[ $post->post_status ] ) ? $status_map[ $post->post_status ] : [ 'text' => $post->post_status, 'class' => '' ];
									?>
									<span class="dps-ai-status-badge <?php echo esc_attr( $status_info['class'] ); ?>">
										<?php echo esc_html( $status_info['text'] ); ?>
									</span>
									
									<?php if ( '1' === $active ) : ?>
										<br /><span class="dps-ai-badge dps-ai-badge-active"><?php esc_html_e( 'Ativo', 'dps-ai' ); ?></span>
									<?php else : ?>
										<br /><span class="dps-ai-badge dps-ai-badge-inactive"><?php esc_html_e( 'Inativo', 'dps-ai' ); ?></span>
									<?php endif; ?>
								</td>
								
								<!-- Ações -->
								<td>
									<button type="button" class="button button-small dps-ai-kb-edit-btn">
										<?php esc_html_e( 'Editar Rápido', 'dps-ai' ); ?>
									</button>
									<button type="button" class="button button-primary button-small dps-ai-kb-save-btn" style="display: none;">
										<?php esc_html_e( 'Salvar', 'dps-ai' ); ?>
									</button>
									<button type="button" class="button button-small dps-ai-kb-cancel-btn" style="display: none;">
										<?php esc_html_e( 'Cancelar', 'dps-ai' ); ?>
									</button>
									<br />
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-small" style="margin-top: 5px;">
										<?php esc_html_e( 'Editar Post', 'dps-ai' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5" style="text-align: center; padding: 40px;">
								<p><?php esc_html_e( 'Nenhum artigo encontrado.', 'dps-ai' ); ?></p>
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . DPS_AI_Knowledge_Base::POST_TYPE ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Adicionar Novo Artigo', 'dps-ai' ); ?>
								</a>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<div style="margin-top: 20px;">
				<p class="description">
					<strong><?php esc_html_e( 'Total de artigos:', 'dps-ai' ); ?></strong> <?php echo esc_html( count( $posts ) ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handler AJAX para edição rápida.
	 */
	public function ajax_quick_edit() {
		// Verifica nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dps_ai_kb_quick_edit' ) ) {
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
		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$keywords = isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$priority = isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 5;

		// Valida post
		$post = get_post( $post_id );
		if ( ! $post || DPS_AI_Knowledge_Base::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( [
				'message' => __( 'Post inválido.', 'dps-ai' ),
			] );
		}

		// Valida prioridade
		if ( $priority < 1 || $priority > 10 ) {
			$priority = 5;
		}

		// Atualiza metadados
		update_post_meta( $post_id, '_dps_ai_keywords', $keywords );
		update_post_meta( $post_id, '_dps_ai_priority', $priority );

		// Retorna sucesso
		wp_send_json_success( [
			'message'  => __( 'Artigo atualizado com sucesso!', 'dps-ai' ),
			'keywords' => $keywords,
			'priority' => $priority,
		] );
	}
}
