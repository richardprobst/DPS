<?php
/**
 * Template de listagem de pets.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/lists/pets-list.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.4
 * @since 1.0.5 Adicionados filtros, exportação CSV e estatísticas de agendamentos
 *
 * Variáveis disponíveis:
 * @var array   $pets               Lista de posts de pets
 * @var int     $pets_page          Página atual da paginação
 * @var int     $pets_pages         Total de páginas
 * @var string  $base_url           URL base da página
 * @var string  $current_filter     Filtro ativo
 * @var array   $appointments_stats Estatísticas de agendamentos por pet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$pets               = isset( $pets ) && is_array( $pets ) ? $pets : [];
$pets_page          = isset( $pets_page ) ? (int) $pets_page : 1;
$pets_pages         = isset( $pets_pages ) ? (int) $pets_pages : 1;
$base_url           = isset( $base_url ) ? $base_url : DPS_URL_Builder::safe_get_permalink();
$current_filter     = isset( $current_filter ) ? $current_filter : 'all';
$appointments_stats = isset( $appointments_stats ) && is_array( $appointments_stats ) ? $appointments_stats : [];

$filter_options = [
	'all'           => esc_html__( 'Todos', 'desi-pet-shower' ),
	'cao'           => esc_html__( 'Apenas cães', 'desi-pet-shower' ),
	'gato'          => esc_html__( 'Apenas gatos', 'desi-pet-shower' ),
	'aggressive'    => esc_html__( 'Agressivos', 'desi-pet-shower' ),
	'without_owner' => esc_html__( 'Sem tutor', 'desi-pet-shower' ),
];
$filter_url_base = add_query_arg( 'tab', 'pets', $base_url );
?>

<div class="dps-list-toolbar">
	<input type="text" class="dps-search" placeholder="<?php echo esc_attr__( 'Buscar por nome, tutor ou raça...', 'desi-pet-shower' ); ?>">
	
	<div class="dps-filter-control">
		<label for="dps-pets-filter" class="screen-reader-text"><?php echo esc_html__( 'Filtrar pets', 'desi-pet-shower' ); ?></label>
		<select id="dps-pets-filter" onchange="if (this.value) { window.location.href = this.value; }">
			<?php foreach ( $filter_options as $filter_key => $label ) : ?>
				<?php
				$url = add_query_arg(
					'dps_pets_filter',
					$filter_key,
					$filter_url_base
				);
				?>
				<option value="<?php echo esc_url( $url ); ?>" <?php selected( $current_filter, $filter_key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	
	<?php if ( ! empty( $pets ) && ( current_user_can( 'dps_manage_pets' ) || current_user_can( 'manage_options' ) ) ) : ?>
		<?php
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dps_export_pets' ),
			'dps_export_pets'
		);
		?>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" title="<?php echo esc_attr__( 'Exportar lista de pets para CSV', 'desi-pet-shower' ); ?>">
			<?php echo esc_html__( 'Exportar CSV', 'desi-pet-shower' ); ?>
		</a>
	<?php endif; ?>
</div>

<?php if ( ! empty( $pets ) ) : ?>
	<div class="dps-table-wrapper">
		<table class="dps-table dps-table--pets">
			<thead>
				<tr>
					<th class="dps-table__col--name"><?php echo esc_html__( 'Pet', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--owner"><?php echo esc_html__( 'Tutor', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--species"><?php echo esc_html__( 'Espécie', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--breed hide-mobile"><?php echo esc_html__( 'Raça', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--size"><?php echo esc_html__( 'Porte', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--appointments hide-mobile"><?php echo esc_html__( 'Atendimentos', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--actions"><?php echo esc_html__( 'Ações', 'desi-pet-shower' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pets as $pet ) : ?>
					<?php
					$owner_id    = get_post_meta( $pet->ID, 'owner_id', true );
					$owner       = $owner_id ? get_post( $owner_id ) : null;
					$species     = get_post_meta( $pet->ID, 'pet_species', true );
					$breed       = get_post_meta( $pet->ID, 'pet_breed', true );
					$size        = get_post_meta( $pet->ID, 'pet_size', true );
					$aggressive  = get_post_meta( $pet->ID, 'pet_aggressive', true );
					
					// Labels traduzidas
					$species_labels = [
						'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
						'gato'  => __( 'Gato', 'desi-pet-shower' ),
						'outro' => __( 'Outro', 'desi-pet-shower' ),
					];
					$species_label = isset( $species_labels[ $species ] ) ? $species_labels[ $species ] : $species;
					
					$size_labels = [
						'pequeno' => __( 'Pequeno', 'desi-pet-shower' ),
						'medio'   => __( 'Médio', 'desi-pet-shower' ),
						'grande'  => __( 'Grande', 'desi-pet-shower' ),
					];
					$size_label = isset( $size_labels[ $size ] ) ? $size_labels[ $size ] : '-';
					
					// Ícone da espécie
					$species_icon = '🐾';
					if ( 'cao' === $species ) {
						$species_icon = '🐕';
					} elseif ( 'gato' === $species ) {
						$species_icon = '🐈';
					}
					
					// Estatísticas de agendamentos
					$pet_appt_stats = isset( $appointments_stats[ (string) $pet->ID ] ) ? $appointments_stats[ (string) $pet->ID ] : [ 'count' => 0, 'last_date' => null ];
					$appt_count     = $pet_appt_stats['count'];
					$last_date      = $pet_appt_stats['last_date'];
					$last_date_fmt  = $last_date ? date_i18n( 'd/m/Y', strtotime( $last_date ) ) : null;
					
					// URLs de ação
					$edit_url     = add_query_arg( [ 'tab' => 'pets', 'dps_edit' => 'pet', 'id' => $pet->ID ], $base_url );
					$delete_url   = add_query_arg( [ 'tab' => 'pets', 'dps_delete' => 'pet', 'id' => $pet->ID, 'dps_nonce' => wp_create_nonce( 'dps_delete' ) ], $base_url );
					$schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $owner_id, 'pref_pet' => $pet->ID ], $base_url );
					$client_url   = $owner ? add_query_arg( [ 'dps_view' => 'client', 'id' => $owner->ID ], $base_url ) : '';
					
					// Classes da linha
					$row_class = 'dps-pet-row';
					if ( $aggressive ) {
						$row_class .= ' dps-pet-row--aggressive';
					}
					?>
					<tr class="<?php echo esc_attr( $row_class ); ?>">
						<td class="dps-table__col--name">
							<div class="dps-pet-cell">
								<?php if ( $aggressive ) : ?>
									<span class="dps-pet-cell__badge" title="<?php echo esc_attr__( 'Agressivo', 'desi-pet-shower' ); ?>">⚠️</span>
								<?php endif; ?>
								<span class="dps-pet-cell__name"><?php echo esc_html( $pet->post_title ); ?></span>
								<?php if ( empty( $owner_id ) ) : ?>
									<span class="dps-status-badge dps-status-badge--pending">
										<?php echo esc_html__( 'Sem tutor', 'desi-pet-shower' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td class="dps-table__col--owner">
							<?php if ( $owner && $client_url ) : ?>
								<a href="<?php echo esc_url( $client_url ); ?>"><?php echo esc_html( $owner->post_title ); ?></a>
							<?php else : ?>
								<span class="dps-text-muted">-</span>
							<?php endif; ?>
						</td>
						<td class="dps-table__col--species">
							<span class="dps-species-badge">
								<?php echo $species_icon; ?> <?php echo esc_html( $species_label ); ?>
							</span>
						</td>
						<td class="dps-table__col--breed hide-mobile"><?php echo esc_html( $breed ?: '-' ); ?></td>
						<td class="dps-table__col--size">
							<span class="dps-size-badge dps-size-badge--<?php echo esc_attr( $size ); ?>">
								<?php echo esc_html( $size_label ); ?>
							</span>
						</td>
						<td class="dps-table__col--appointments hide-mobile">
							<?php if ( $appt_count > 0 ) : ?>
								<div class="dps-pet-appointments-info">
									<span class="dps-pet-appointments-count" title="<?php echo esc_attr__( 'Total de atendimentos', 'desi-pet-shower' ); ?>">
										📅 <?php echo esc_html( $appt_count ); ?>
									</span>
									<?php if ( $last_date_fmt ) : ?>
										<span class="dps-pet-last-appointment" title="<?php echo esc_attr__( 'Último atendimento', 'desi-pet-shower' ); ?>">
											<?php echo esc_html( $last_date_fmt ); ?>
										</span>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<span class="dps-text-muted dps-pet-no-appointments">
									<?php echo esc_html__( 'Nenhum', 'desi-pet-shower' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="dps-table__col--actions">
							<div class="dps-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="dps-action-link" title="<?php echo esc_attr__( 'Editar', 'desi-pet-shower' ); ?>">
									<?php echo esc_html__( 'Editar', 'desi-pet-shower' ); ?>
								</a>
								<span class="dps-action-separator">|</span>
								<a href="<?php echo esc_url( $schedule_url ); ?>" class="dps-action-link dps-action-link--primary" title="<?php echo esc_attr__( 'Agendar serviço', 'desi-pet-shower' ); ?>">
									<?php echo esc_html__( 'Agendar', 'desi-pet-shower' ); ?>
								</a>
								<span class="dps-action-separator">|</span>
								<a href="<?php echo esc_url( $delete_url ); ?>" class="dps-action-link dps-action-delete" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ); ?>');" title="<?php echo esc_attr__( 'Excluir', 'desi-pet-shower' ); ?>">
									<?php echo esc_html__( 'Excluir', 'desi-pet-shower' ); ?>
								</a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $pets_pages > 1 ) : ?>
		<div class="dps-pagination">
			<?php
			echo paginate_links(
				[
					'base'      => add_query_arg( 'dps_pets_page', '%#%' ),
					'format'    => '',
					'prev_text' => __( '&laquo; Anterior', 'desi-pet-shower' ),
					'next_text' => __( 'Próxima &raquo;', 'desi-pet-shower' ),
					'current'   => $pets_page,
					'total'     => $pets_pages,
				]
			);
			?>
		</div>
	<?php endif; ?>

<?php else : ?>
	<div class="dps-empty-state">
		<span class="dps-empty-state__icon">🐾</span>
		<h4 class="dps-empty-state__title"><?php echo esc_html__( 'Nenhum pet cadastrado', 'desi-pet-shower' ); ?></h4>
	</div>
<?php endif; ?>
