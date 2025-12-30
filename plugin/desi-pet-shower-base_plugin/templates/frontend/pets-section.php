<?php
/**
 * Template da seção de Pets completa.
 *
 * Este template renderiza a seção de pets, incluindo o formulário de cadastro/edição
 * e a listagem de pets existentes.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/frontend/pets-section.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.4
 *
 * Variáveis disponíveis:
 * @var array       $pets          Lista de posts de pets
 * @var int         $pets_page     Página atual da paginação
 * @var int         $pets_pages    Total de páginas
 * @var array       $clients       Lista de clientes disponíveis
 * @var int         $edit_id       ID do pet sendo editado (0 se novo)
 * @var WP_Post|null $editing      Post do pet sendo editado (null se novo)
 * @var array       $meta          Array com metadados do pet
 * @var array       $breed_options Lista de raças disponíveis
 * @var array       $breed_data    Dataset completo de raças por espécie
 * @var string      $base_url      URL base da página
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$pets          = isset( $pets ) && is_array( $pets ) ? $pets : [];
$pets_page     = isset( $pets_page ) ? (int) $pets_page : 1;
$pets_pages    = isset( $pets_pages ) ? (int) $pets_pages : 1;
$clients       = isset( $clients ) && is_array( $clients ) ? $clients : [];
$edit_id       = isset( $edit_id ) ? (int) $edit_id : 0;
$editing       = isset( $editing ) ? $editing : null;
$meta          = isset( $meta ) && is_array( $meta ) ? $meta : [];
$breed_options = isset( $breed_options ) && is_array( $breed_options ) ? $breed_options : [];
$breed_data    = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];
$base_url      = isset( $base_url ) ? $base_url : get_permalink();
?>

<div class="dps-section" id="dps-section-pets">
	<h2 class="dps-section-title">
		<span class="dps-section-title__icon">🐾</span>
		<?php echo esc_html__( 'Cadastro de Pets', 'desi-pet-shower' ); ?>
	</h2>
	<p class="dps-section-header__subtitle dps-pets-intro">
		<?php echo esc_html__( 'Gerencie os cadastros de pets com o mesmo cabeçalho, hierarquia e espaçamentos usados na aba de Agendamentos.', 'desi-pet-shower' ); ?>
	</p>

	<div class="dps-section-grid">
		<div class="dps-surface dps-surface--info">
			<div class="dps-surface__title">
				<span>📋</span>
				<?php echo esc_html__( 'Formulário de pets', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description dps-pets-helper-text">
				<?php echo esc_html__( 'Cadastre ou edite pets mantendo o mesmo cabeçalho, botões e boxes coloridos da aba Agendamentos.', 'desi-pet-shower' ); ?>
			</p>
			<?php
			// Renderizar formulário de pet usando template
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

		<div class="dps-surface dps-surface--neutral">
			<div class="dps-surface__title">
				<span>🐶</span>
				<?php echo esc_html__( 'Lista de pets', 'desi-pet-shower' ); ?>
			</div>
			<p class="dps-surface__description dps-pets-helper-text">
				<?php echo esc_html__( 'Listagem unificada com espaçamentos e bordas inspirados na seção de Agendamentos.', 'desi-pet-shower' ); ?>
			</p>
			<?php
			// Renderizar listagem de pets usando template
			dps_get_template(
				'lists/pets-list.php',
				[
					'pets'       => $pets,
					'pets_page'  => $pets_page,
					'pets_pages' => $pets_pages,
					'base_url'   => $base_url,
				]
			);
			?>
		</div>
	</div>
</div>
