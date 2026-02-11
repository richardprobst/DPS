<?php
/**
 * Template da seção de Clientes completa.
 *
 * Este template renderiza a seção de clientes, com foco em listagem e atalhos
 * administrativos para cadastros já existentes.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/frontend/clients-section.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.0
 *
 * Variáveis disponíveis:
 * @var array  $clients          Lista de posts de clientes
 * @var array  $client_meta      Metadados principais dos clientes
 * @var array  $pets_counts      Contagem de pets por cliente
 * @var array  $summary          Métricas resumidas da lista
 * @var string $current_filter   Filtro ativo (all|without_pets|missing_contact)
 * @var string $registration_url URL da página dedicada de cadastro
 * @var string $base_url         URL base da página
 * @var int    $edit_id          ID do cliente sendo editado (0 se não estiver editando)
 * @var WP_Post|null $editing    Post do cliente sendo editado
 * @var array  $edit_meta        Metadados do cliente sendo editado
 * @var string $api_key          Chave da API do Google Maps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$clients          = isset( $clients ) && is_array( $clients ) ? $clients : [];
$client_meta      = isset( $client_meta ) && is_array( $client_meta ) ? $client_meta : [];
$pets_counts      = isset( $pets_counts ) && is_array( $pets_counts ) ? $pets_counts : [];
$summary          = isset( $summary ) && is_array( $summary ) ? $summary : [ 'total' => 0, 'missing_contact' => 0, 'without_pets' => 0 ];
$current_filter   = isset( $current_filter ) ? $current_filter : 'all';
$registration_url = isset( $registration_url ) ? $registration_url : '';
$base_url         = isset( $base_url ) ? $base_url : '';
$edit_id          = isset( $edit_id ) ? (int) $edit_id : 0;
$editing          = isset( $editing ) ? $editing : null;
$edit_meta        = isset( $edit_meta ) && is_array( $edit_meta ) ? $edit_meta : [];
$api_key          = isset( $api_key ) ? $api_key : '';
?>

<div class="dps-section" id="dps-section-clientes">
	<h2 class="dps-section-title">
		<span class="dps-section-title__icon">👥</span>
		<?php echo esc_html__( 'Gestão de Clientes', 'desi-pet-shower' ); ?>
	</h2>

	<?php if ( $edit_id && $editing ) : ?>
		<?php 
		// Modo de edição: exibe formulário de edição do cliente
		$cancel_url = add_query_arg( 'tab', 'clientes', $base_url );
		?>
		<div class="dps-surface dps-surface--info dps-clients-edit-card">
			<div class="dps-surface__title">
				<span>✏️</span>
				<?php echo esc_html__( 'Editar Cliente', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description">
				<?php
				printf(
					/* translators: %s: Nome do cliente sendo editado */
					esc_html__( 'Editando: %s', 'desi-pet-shower' ),
					esc_html( $editing->post_title )
				);
				?>
				<a href="<?php echo esc_url( $cancel_url ); ?>" class="dps-cancel-edit">
					<?php echo esc_html__( 'Cancelar edição', 'desi-pet-shower' ); ?>
				</a>
			</p>
			<?php
			// Renderiza o formulário de edição
			dps_get_template(
				'forms/client-form.php',
				[
					'edit_id' => $edit_id,
					'editing' => $editing,
					'meta'    => $edit_meta,
					'api_key' => $api_key,
				]
			);
			?>
		</div>
	<?php else : ?>
		<?php // Modo normal: exibe cards de status e listagem ?>
		<div class="dps-section-grid">
			<div class="dps-surface dps-surface--info dps-clients-status-card">
				<div class="dps-surface__title">
					<span>🗂️</span>
					<?php echo esc_html__( 'Informações', 'desi-pet-shower' ); ?>
				</div>
				<ul class="dps-inline-stats dps-inline-stats--panel">
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--scheduled">
								<?php echo esc_html__( 'Total de clientes', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Cadastros ativos na base', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['total'] ); ?></strong>
					</li>
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--pending">
								<?php echo esc_html__( 'Sem telefone ou e-mail', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Inclua contato para agilizar retornos', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['missing_contact'] ); ?></strong>
					</li>
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--paid">
								<?php echo esc_html__( 'Sem pets vinculados', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Associe pets para manter o histórico completo', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['without_pets'] ); ?></strong>
					</li>
				</ul>

				<div class="dps-actions dps-actions--inline">
					<?php if ( ! empty( $registration_url ) ) : ?>
						<a class="dps-submit-btn" href="<?php echo esc_url( $registration_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Cadastrar novo cliente', 'desi-pet-shower' ); ?>
						</a>
					<?php else : ?>
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<a class="dps-submit-btn" style="background: var(--dps-color-on-surface-variant);" href="<?php echo esc_url( admin_url( 'admin.php?page=dps-clients-settings' ) ); ?>">
								<?php echo esc_html__( 'Configurar página de cadastro', 'desi-pet-shower' ); ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="dps-surface dps-surface--neutral dps-clients-list-card">
				<div class="dps-surface__title">
					<span>📋</span>
					<?php echo esc_html__( 'Lista de clientes cadastrados', 'desi-pet-shower' ); ?>
				</div>
				<div class="dps-clients-list-card__body">
					<?php
					// Renderizar listagem de clientes usando template
					dps_get_template(
						'lists/clients-list.php',
						[
							'clients'        => $clients,
							'client_meta'    => $client_meta,
							'pets_counts'    => $pets_counts,
							'base_url'       => $base_url,
							'current_filter' => $current_filter,
						]
					);
					?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
