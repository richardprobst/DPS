<?php
/**
 * Template da seção de Clientes completa.
 *
 * Este template renderiza a seção de clientes, incluindo o formulário de cadastro/edição
 * e a listagem de clientes existentes.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/frontend/clients-section.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.0
 *
 * Variáveis disponíveis:
 * @var array  $clients  Lista de posts de clientes
 * @var int    $edit_id  ID do cliente sendo editado (0 se novo)
 * @var object $editing  Post do cliente sendo editado (null se novo)
 * @var array  $meta     Array com metadados do cliente
 * @var string $api_key  Chave da API do Google Maps
 * @var string $base_url URL base da página
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$clients  = isset( $clients ) && is_array( $clients ) ? $clients : [];
$edit_id  = isset( $edit_id ) ? (int) $edit_id : 0;
$editing  = isset( $editing ) ? $editing : null;
$meta     = isset( $meta ) && is_array( $meta ) ? $meta : [];
$api_key  = isset( $api_key ) ? $api_key : '';
$base_url = isset( $base_url ) ? $base_url : '';
?>

<div class="dps-section" id="dps-section-clientes">
	<h2 class="dps-section-title">
		<span class="dps-section-title__icon">👥</span>
		<?php echo esc_html__( 'Cadastro de Clientes', 'desi-pet-shower' ); ?>
	</h2>
	
	<?php
	// Renderizar formulário de cliente usando template
	dps_get_template(
		'forms/client-form.php',
		[
			'edit_id' => $edit_id,
			'editing' => $editing,
			'meta'    => $meta,
			'api_key' => $api_key,
		]
	);
	
	// Renderizar listagem de clientes usando template
	dps_get_template(
		'lists/clients-list.php',
		[
			'clients'  => $clients,
			'base_url' => $base_url,
		]
	);
	?>
</div>
