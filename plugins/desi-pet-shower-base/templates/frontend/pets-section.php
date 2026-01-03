<?php
/**
 * Template da seção de Pets completa.
 *
 * Este template renderiza a seção de pets, seguindo o mesmo padrão visual
 * da aba Clientes: estatísticas no topo, listagem no meio e formulário ao final.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/frontend/pets-section.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.4
 * @since 1.0.5 Reorganizado para seguir padrão da aba Clientes
 *
 * Variáveis disponíveis:
 * @var array       $pets               Lista de posts de pets
 * @var int         $pets_page          Página atual da paginação
 * @var int         $pets_pages         Total de páginas
 * @var int         $pets_total         Total de pets cadastrados
 * @var array       $clients            Lista de clientes disponíveis
 * @var int         $edit_id            ID do pet sendo editado (0 se novo)
 * @var WP_Post|null $editing           Post do pet sendo editado (null se novo)
 * @var array       $meta               Array com metadados do pet
 * @var array       $breed_options      Lista de raças disponíveis
 * @var array       $breed_data         Dataset completo de raças por espécie
 * @var string      $base_url           URL base da página
 * @var string      $current_filter     Filtro ativo
 * @var array       $summary            Estatísticas dos pets
 * @var array       $appointments_stats Estatísticas de agendamentos por pet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$pets               = isset( $pets ) && is_array( $pets ) ? $pets : [];
$pets_page          = isset( $pets_page ) ? (int) $pets_page : 1;
$pets_pages         = isset( $pets_pages ) ? (int) $pets_pages : 1;
$pets_total         = isset( $pets_total ) ? (int) $pets_total : 0;
$clients            = isset( $clients ) && is_array( $clients ) ? $clients : [];
$edit_id            = isset( $edit_id ) ? (int) $edit_id : 0;
$editing            = isset( $editing ) ? $editing : null;
$meta               = isset( $meta ) && is_array( $meta ) ? $meta : [];
$breed_options      = isset( $breed_options ) && is_array( $breed_options ) ? $breed_options : [];
$breed_data         = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];
$base_url           = isset( $base_url ) ? $base_url : DPS_URL_Builder::safe_get_permalink();
$current_filter     = isset( $current_filter ) ? $current_filter : 'all';
$summary            = isset( $summary ) && is_array( $summary ) ? $summary : [ 'total' => 0, 'aggressive' => 0, 'without_owner' => 0, 'dogs' => 0, 'cats' => 0, 'others' => 0 ];
$appointments_stats = isset( $appointments_stats ) && is_array( $appointments_stats ) ? $appointments_stats : [];
?>

<div class="dps-section" id="dps-section-pets">
	<h2 class="dps-section-title">
		<span class="dps-section-title__icon">🐾</span>
		<?php echo esc_html__( 'Gestão de Pets', 'desi-pet-shower' ); ?>
	</h2>

	<?php if ( $edit_id && $editing ) : ?>
		<?php 
		// Modo de edição: exibe formulário de edição do pet
		$cancel_url = add_query_arg( 'tab', 'pets', $base_url );
		?>
		<div class="dps-surface dps-surface--info dps-pets-edit-card">
			<div class="dps-surface__title">
				<span>✏️</span>
				<?php echo esc_html__( 'Editar Pet', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description">
				<?php
				printf(
					/* translators: %s: Nome do pet sendo editado */
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
				'forms/pet-form.php',
				[
					'edit_id'       => $edit_id,
					'editing'       => $editing,
					'meta'          => $meta,
					'clients'       => $clients,
					'breed_options' => $breed_options,
					'breed_data'    => $breed_data,
				]
			);
			?>
		</div>
	<?php else : ?>
		<?php // Modo normal: exibe cards de status, listagem e formulário ao final ?>
		<div class="dps-section-grid">
			<!-- Card de Informações -->
			<div class="dps-surface dps-surface--info dps-pets-status-card">
				<div class="dps-surface__title">
					<span>🗂️</span>
					<?php echo esc_html__( 'Informações', 'desi-pet-shower' ); ?>
				</div>
				<ul class="dps-inline-stats dps-inline-stats--panel">
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--scheduled">
								<?php echo esc_html__( 'Total de pets', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Cadastros ativos na base', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['total'] ); ?></strong>
					</li>
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--pending">
								<?php echo esc_html__( 'Pets agressivos', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Requerem cuidado especial', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['aggressive'] ); ?></strong>
					</li>
					<li>
						<div class="dps-inline-stats__label">
							<span class="dps-status-badge dps-status-badge--paid">
								<?php echo esc_html__( 'Sem tutor vinculado', 'desi-pet-shower' ); ?>
							</span>
							<small><?php echo esc_html__( 'Associe um cliente para completar o cadastro', 'desi-pet-shower' ); ?></small>
						</div>
						<strong class="dps-inline-stats__value"><?php echo esc_html( (string) $summary['without_owner'] ); ?></strong>
					</li>
				</ul>

				<div class="dps-actions dps-actions--stacked">
					<a class="button button-primary" href="#dps-pets-form-section">
						<?php echo esc_html__( 'Cadastrar novo pet', 'desi-pet-shower' ); ?>
					</a>
				</div>
			</div>

			<!-- Card de Lista de Pets -->
			<div class="dps-surface dps-surface--neutral dps-pets-list-card">
				<div class="dps-surface__title">
					<span>📋</span>
					<?php echo esc_html__( 'Lista de pets', 'desi-pet-shower' ); ?>
				</div>
				<div class="dps-pets-list-card__body">
					<?php
					// Renderizar listagem de pets usando template
					dps_get_template(
						'lists/pets-list.php',
						[
							'pets'               => $pets,
							'pets_page'          => $pets_page,
							'pets_pages'         => $pets_pages,
							'base_url'           => $base_url,
							'current_filter'     => $current_filter,
							'appointments_stats' => $appointments_stats,
						]
					);
					?>
				</div>
			</div>
		</div>

		<!-- Formulário de Cadastro ao Final -->
		<div class="dps-surface dps-surface--info dps-pets-form-section" id="dps-pets-form-section">
			<div class="dps-surface__title">
				<span>➕</span>
				<?php echo esc_html__( 'Cadastrar novo pet', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description">
				<?php echo esc_html__( 'Preencha os dados abaixo para cadastrar um novo pet. Selecione primeiro o tutor (cliente) responsável.', 'desi-pet-shower' ); ?>
			</p>
			<?php
			// Renderizar formulário de pet usando template (modo cadastro)
			dps_get_template(
				'forms/pet-form.php',
				[
					'edit_id'       => 0,
					'editing'       => null,
					'meta'          => $meta,
					'clients'       => $clients,
					'breed_options' => $breed_options,
					'breed_data'    => $breed_data,
				]
			);
			?>
		</div>
	<?php endif; ?>
</div>
