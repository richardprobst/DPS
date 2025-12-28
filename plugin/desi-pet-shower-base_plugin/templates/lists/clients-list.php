<?php
/**
 * Template de listagem de clientes.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/lists/clients-list.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.0
 * @since 1.0.4 Adicionadas colunas Email e Pets, melhorado layout visual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$clients  = isset( $clients ) && is_array( $clients ) ? $clients : [];
$base_url = isset( $base_url ) ? $base_url : get_permalink();
?>

<h3 class="dps-list-header">
	<span class="dps-list-header__icon">👥</span>
	<?php echo esc_html__( 'Clientes Cadastrados', 'desi-pet-shower' ); ?>
	<?php if ( ! empty( $clients ) ) : ?>
		<span class="dps-list-header__count"><?php echo count( $clients ); ?></span>
	<?php endif; ?>
</h3>

<div class="dps-list-toolbar">
	<input type="text" class="dps-search" placeholder="<?php echo esc_attr__( 'Buscar por nome, telefone ou email...', 'desi-pet-shower' ); ?>">
	
	<?php if ( ! empty( $clients ) && ( current_user_can( 'dps_manage_clients' ) || current_user_can( 'manage_options' ) ) ) : ?>
		<?php
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dps_export_clients' ),
			'dps_export_clients'
		);
		?>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" title="<?php echo esc_attr__( 'Exportar lista de clientes para CSV', 'desi-pet-shower' ); ?>">
			<?php echo esc_html__( 'Exportar CSV', 'desi-pet-shower' ); ?>
		</a>
	<?php endif; ?>
</div>

<?php if ( ! empty( $clients ) ) : ?>
	<div class="dps-table-wrapper">
		<table class="dps-table dps-table--clients">
			<thead>
				<tr>
					<th class="dps-table__col--name"><?php echo esc_html__( 'Cliente', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--phone"><?php echo esc_html__( 'Telefone', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--email hide-mobile"><?php echo esc_html__( 'Email', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--pets"><?php echo esc_html__( 'Pets', 'desi-pet-shower' ); ?></th>
					<th class="dps-table__col--actions"><?php echo esc_html__( 'Ações', 'desi-pet-shower' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $clients as $client ) : ?>
					<?php
					$phone_raw   = get_post_meta( $client->ID, 'client_phone', true );
					$email       = get_post_meta( $client->ID, 'client_email', true );
					$wa_url      = '';

					// Gera link do WhatsApp se houver telefone usando helper centralizado
					if ( $phone_raw && class_exists( 'DPS_WhatsApp_Helper' ) ) {
						$wa_url = DPS_WhatsApp_Helper::get_link_to_client( $phone_raw );
					} elseif ( $phone_raw ) {
						// Fallback para compatibilidade
						$phone_digits = preg_replace( '/\D+/', '', $phone_raw );
						$wa_url = 'https://wa.me/' . $phone_digits;
					}

					// Conta pets do cliente
					$pets_count = 0;
					$pets_query = new WP_Query(
						[
							'post_type'      => 'dps_pet',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'meta_query'     => [
								[
									'key'   => 'owner_id',
									'value' => $client->ID,
								],
							],
						]
					);
					$pets_count = $pets_query->found_posts;

					$edit_url     = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client->ID ], $base_url );
					$delete_url   = add_query_arg( [ 'tab' => 'clientes', 'dps_delete' => 'client', 'id' => $client->ID, 'dps_nonce' => wp_create_nonce( 'dps_delete' ) ], $base_url );
					$view_url     = add_query_arg( [ 'dps_view' => 'client', 'id' => $client->ID ], $base_url );
					$schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client->ID ], $base_url );
					$add_pet_url  = add_query_arg( [ 'tab' => 'pets', 'pref_owner' => $client->ID ], $base_url );
					?>
					<tr>
						<td class="dps-table__col--name">
							<a href="<?php echo esc_url( $view_url ); ?>" class="dps-client-link">
								<?php echo esc_html( $client->post_title ); ?>
							</a>
						</td>
						<td class="dps-table__col--phone">
							<?php if ( $phone_raw ) : ?>
								<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" class="dps-phone-link" title="<?php echo esc_attr__( 'Abrir WhatsApp', 'desi-pet-shower' ); ?>">
									<?php echo esc_html( $phone_raw ); ?>
								</a>
							<?php else : ?>
								<span class="dps-text-muted">-</span>
							<?php endif; ?>
						</td>
						<td class="dps-table__col--email hide-mobile">
							<?php if ( $email ) : ?>
								<a href="mailto:<?php echo esc_attr( $email ); ?>" class="dps-email-link">
									<?php echo esc_html( $email ); ?>
								</a>
							<?php else : ?>
								<span class="dps-text-muted">-</span>
							<?php endif; ?>
						</td>
						<td class="dps-table__col--pets">
							<?php if ( $pets_count > 0 ) : ?>
								<span class="dps-pets-badge">
									🐾 <?php echo esc_html( $pets_count ); ?>
								</span>
							<?php else : ?>
								<a href="<?php echo esc_url( $add_pet_url ); ?>" class="dps-add-pet-link" title="<?php echo esc_attr__( 'Adicionar pet', 'desi-pet-shower' ); ?>">
									+ <?php echo esc_html__( 'Adicionar', 'desi-pet-shower' ); ?>
								</a>
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
<?php else : ?>
	<div class="dps-empty-state">
		<span class="dps-empty-state__icon">👤</span>
		<h4 class="dps-empty-state__title"><?php echo esc_html__( 'Nenhum cliente cadastrado', 'desi-pet-shower' ); ?></h4>
		<p class="dps-empty-state__description">
			<?php echo esc_html__( 'Comece cadastrando seu primeiro cliente usando o formulário acima. Após o cadastro, você poderá adicionar pets e agendar serviços.', 'desi-pet-shower' ); ?>
		</p>
	</div>
<?php endif; ?>
