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
?>

<div class="dps-section" id="dps-section-clientes">
	<h2 class="dps-section-title">
		<span class="dps-section-title__icon">👥</span>
		<?php echo esc_html__( 'Gestão de Clientes', 'desi-pet-shower' ); ?>
	</h2>
	<p class="dps-section-header__subtitle">
		<?php echo esc_html__( 'Os cadastros foram movidos para uma página dedicada. Use os atalhos abaixo e acompanhe os clientes existentes com o mesmo padrão visual da aba Agendamentos.', 'desi-pet-shower' ); ?>
	</p>

	<div class="dps-section-grid">
		<div class="dps-surface dps-surface--info">
			<div class="dps-surface__title">
				<span>🗂️</span>
				<?php echo esc_html__( 'Status e atalhos', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description">
				<?php echo esc_html__( 'Acompanhe rapidamente cadastros que precisam de atenção e acesse o formulário dedicado quando necessário.', 'desi-pet-shower' ); ?>
			</p>
			<ul class="dps-inline-stats">
				<li>
					<span class="dps-status-badge dps-status-badge--scheduled">
						<?php echo esc_html__( 'Total de clientes', 'desi-pet-shower' ); ?>
					</span>
					<strong><?php echo esc_html( (string) $summary['total'] ); ?></strong>
				</li>
				<li>
					<span class="dps-status-badge dps-status-badge--pending">
						<?php echo esc_html__( 'Sem telefone ou e-mail', 'desi-pet-shower' ); ?>
					</span>
					<strong><?php echo esc_html( (string) $summary['missing_contact'] ); ?></strong>
				</li>
				<li>
					<span class="dps-status-badge dps-status-badge--paid">
						<?php echo esc_html__( 'Sem pets vinculados', 'desi-pet-shower' ); ?>
					</span>
					<strong><?php echo esc_html( (string) $summary['without_pets'] ); ?></strong>
				</li>
			</ul>

			<div class="dps-actions">
				<?php if ( ! empty( $registration_url ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $registration_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html__( 'Abrir página de cadastro dedicada', 'desi-pet-shower' ); ?>
					</a>
				<?php else : ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=dps-clients-settings' ) ); ?>">
							<?php echo esc_html__( 'Configurar página de cadastro', 'desi-pet-shower' ); ?>
						</a>
					<?php endif; ?>
					<span class="dps-text-muted">
						<?php echo esc_html__( 'Configure a URL da página de cadastro em Configurações → DPS by PRObst → Clientes.', 'desi-pet-shower' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="dps-surface dps-surface--neutral">
			<div class="dps-surface__title">
				<span>📋</span>
				<?php echo esc_html__( 'Lista de clientes', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description">
				<?php echo esc_html__( 'Visualize, filtre e exporte clientes com a mesma hierarquia e espaçamentos da aba Agendamentos.', 'desi-pet-shower' ); ?>
			</p>
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
