<?php
/**
 * Template de listagem de clientes.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/lists/clients-list.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$clients  = isset( $clients ) && is_array( $clients ) ? $clients : [];
$base_url = isset( $base_url ) ? $base_url : '';
?>

<h3 style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #e5e7eb; color: #374151;">
	<?php echo esc_html__( 'Clientes Cadastrados', 'desi-pet-shower' ); ?>
</h3>

<div class="dps-clients-toolbar" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px;">
	<input type="text" class="dps-search" placeholder="<?php echo esc_attr__( 'Buscar...', 'desi-pet-shower' ); ?>" style="flex: 1; min-width: 200px;">
	
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
		<table class="dps-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Nome', 'desi-pet-shower' ); ?></th>
				<th><?php echo esc_html__( 'Telefone', 'desi-pet-shower' ); ?></th>
				<th><?php echo esc_html__( 'Ações', 'desi-pet-shower' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $clients as $client ) : ?>
				<?php
				$phone_raw = get_post_meta( $client->ID, 'client_phone', true );
				$wa_url = '';

				// Gera link do WhatsApp se houver telefone usando helper centralizado
				if ( $phone_raw && class_exists( 'DPS_WhatsApp_Helper' ) ) {
					$wa_url = DPS_WhatsApp_Helper::get_link_to_client( $phone_raw );
				} elseif ( $phone_raw ) {
					// Fallback para compatibilidade
					$phone_digits = preg_replace( '/\D+/', '', $phone_raw );
					$wa_url = 'https://wa.me/' . $phone_digits;
				}

				$edit_url   = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client->ID ], $base_url );
				$delete_url = add_query_arg( [ 'tab' => 'clientes', 'dps_delete' => 'client', 'id' => $client->ID ], $base_url );
				$view_url = add_query_arg( [ 'dps_view' => 'client', 'id' => $client->ID ], $base_url );
				$schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client->ID ], $base_url );
				?>
				<tr>
					<td><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $client->post_title ); ?></a></td>
					<td>
						<?php if ( $phone_raw ) : ?>
							<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank"><?php echo esc_html( $phone_raw ); ?></a>
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Editar', 'desi-pet-shower' ); ?></a>
						|
						<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ); ?>');">
							<?php echo esc_html__( 'Excluir', 'desi-pet-shower' ); ?>
						</a>
						|
						<a href="<?php echo esc_url( $schedule_url ); ?>"><?php echo esc_html__( 'Agendar', 'desi-pet-shower' ); ?></a>
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
