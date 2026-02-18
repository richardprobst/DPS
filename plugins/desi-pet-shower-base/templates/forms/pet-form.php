<?php
/**
 * Template de formulário de cadastro/edição de pet.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/forms/pet-form.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 * @since 1.0.4
 *
 * Variáveis disponíveis:
 * @var int         $edit_id        ID do pet sendo editado (0 se novo)
 * @var WP_Post|null $editing       Post do pet sendo editado (null se novo)
 * @var array       $meta           Array com metadados do pet
 * @var array       $clients        Lista de clientes disponíveis
 * @var array       $breed_options  Lista de raças disponíveis para a espécie selecionada
 * @var array       $breed_data     Dataset completo de raças por espécie
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$edit_id       = isset( $edit_id ) ? (int) $edit_id : 0;
$editing       = isset( $editing ) ? $editing : null;
$meta          = isset( $meta ) && is_array( $meta ) ? $meta : [];
$clients       = isset( $clients ) && is_array( $clients ) ? $clients : [];
$breed_options = isset( $breed_options ) && is_array( $breed_options ) ? $breed_options : [];
$breed_data    = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];

// Valores do formulário
$pet_name       = $editing ? $editing->post_title : '';
$owner_selected = $meta['owner_id'] ?? '';
$species_val    = $meta['species'] ?? '';
$breed_val      = $meta['breed'] ?? '';
$sex_val        = $meta['sex'] ?? '';
$size_val       = $meta['size'] ?? '';
$weight_val     = $meta['weight'] ?? '';
$birth_val      = $meta['birth'] ?? '';
$coat_val       = $meta['coat'] ?? '';
$color_val      = $meta['color'] ?? '';
$vaccinations   = $meta['vaccinations'] ?? '';
$allergies      = $meta['allergies'] ?? '';
$care_val       = $meta['care'] ?? '';
$behavior_val   = $meta['behavior'] ?? '';
$aggressive     = $meta['aggressive'] ?? '';
$photo_id       = $meta['photo_id'] ?? '';

$checked_ag = $aggressive ? 'checked' : '';
$photo_url  = '';
if ( $photo_id ) {
	$photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
}

// Preferências de produtos
$shampoo_pref        = $meta['shampoo_pref'] ?? '';
$perfume_pref        = $meta['perfume_pref'] ?? '';
$accessories_pref    = $meta['accessories_pref'] ?? '';
$product_restrictions = $meta['product_restrictions'] ?? '';

$btn_text = $edit_id ? esc_html__( 'Atualizar Pet', 'desi-pet-shower' ) : esc_html__( 'Salvar Pet', 'desi-pet-shower' );
?>

<form method="post" enctype="multipart/form-data" class="dps-form dps-form--pet">
	<!-- Hidden fields -->
	<input type="hidden" name="dps_action" value="save_pet">
	<?php wp_nonce_field( 'dps_action', 'dps_nonce_pets' ); ?>
	<?php if ( $edit_id ) : ?>
		<input type="hidden" name="pet_id" value="<?php echo esc_attr( $edit_id ); ?>">
	<?php endif; ?>

	<!-- Fieldset 1: Dados Básicos -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Dados Básicos', 'desi-pet-shower' ); ?></legend>

		<!-- Nome do pet e Cliente em grid -->
		<div class="dps-form-row dps-form-row--2col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Nome do Pet', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
					<input type="text" name="pet_name" value="<?php echo esc_attr( $pet_name ); ?>" required>
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Cliente (Tutor)', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
					<select name="owner_id" id="dps-pet-owner" required>
						<option value=""><?php echo esc_html__( 'Selecione...', 'desi-pet-shower' ); ?></option>
						<?php foreach ( $clients as $client ) : ?>
							<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( (string) $client->ID, (string) $owner_selected ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
		</div>

		<!-- Espécie, Raça e Sexo em grid de 3 colunas -->
		<div class="dps-form-row dps-form-row--3col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Espécie', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
					<select name="pet_species" id="dps-pet-species" required>
						<option value=""><?php echo esc_html__( 'Selecione...', 'desi-pet-shower' ); ?></option>
						<option value="cao" <?php selected( $species_val, 'cao' ); ?>><?php echo esc_html__( 'Cachorro', 'desi-pet-shower' ); ?></option>
						<option value="gato" <?php selected( $species_val, 'gato' ); ?>><?php echo esc_html__( 'Gato', 'desi-pet-shower' ); ?></option>
						<option value="outro" <?php selected( $species_val, 'outro' ); ?>><?php echo esc_html__( 'Outro', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Raça', 'desi-pet-shower' ); ?><br>
					<input type="text" name="pet_breed" list="dps-breed-list" value="<?php echo esc_attr( $breed_val ); ?>" placeholder="<?php echo esc_attr__( 'Digite ou selecione', 'desi-pet-shower' ); ?>">
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Sexo', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
					<select name="pet_sex" required>
						<option value=""><?php echo esc_html__( 'Selecione...', 'desi-pet-shower' ); ?></option>
						<option value="macho" <?php selected( $sex_val, 'macho' ); ?>><?php echo esc_html__( 'Macho', 'desi-pet-shower' ); ?></option>
						<option value="femea" <?php selected( $sex_val, 'femea' ); ?>><?php echo esc_html__( 'Fêmea', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
		</div>
	</fieldset>

	<!-- Datalist para raças -->
	<datalist id="dps-breed-list">
		<?php foreach ( $breed_options as $breed ) : ?>
			<option value="<?php echo esc_attr( $breed ); ?>">
		<?php endforeach; ?>
	</datalist>

	<!-- Fieldset 2: Características Físicas -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Características Físicas', 'desi-pet-shower' ); ?></legend>

		<!-- Tamanho, Peso e Data de Nascimento em grid -->
		<div class="dps-form-row dps-form-row--3col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Tamanho', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
					<select name="pet_size" required>
						<option value=""><?php echo esc_html__( 'Selecione...', 'desi-pet-shower' ); ?></option>
						<option value="pequeno" <?php selected( $size_val, 'pequeno' ); ?>><?php echo esc_html__( 'Pequeno', 'desi-pet-shower' ); ?></option>
						<option value="medio" <?php selected( $size_val, 'medio' ); ?>><?php echo esc_html__( 'Médio', 'desi-pet-shower' ); ?></option>
						<option value="grande" <?php selected( $size_val, 'grande' ); ?>><?php echo esc_html__( 'Grande', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Peso (kg)', 'desi-pet-shower' ); ?><br>
					<input type="number" step="0.1" min="0.1" max="100" name="pet_weight" value="<?php echo esc_attr( $weight_val ); ?>" placeholder="5.5">
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Data de nascimento', 'desi-pet-shower' ); ?><br>
					<input type="date" name="pet_birth" value="<?php echo esc_attr( $birth_val ); ?>">
				</label>
			</p>
		</div>

		<!-- Tipo de pelo e Cor em grid -->
		<div class="dps-form-row dps-form-row--2col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Tipo de pelo', 'desi-pet-shower' ); ?><br>
					<input type="text" name="pet_coat" value="<?php echo esc_attr( $coat_val ); ?>" placeholder="<?php echo esc_attr__( 'Curto, longo, encaracolado...', 'desi-pet-shower' ); ?>">
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Cor predominante', 'desi-pet-shower' ); ?><br>
					<input type="text" name="pet_color" value="<?php echo esc_attr( $color_val ); ?>" placeholder="<?php echo esc_attr__( 'Branco, preto, caramelo...', 'desi-pet-shower' ); ?>">
				</label>
			</p>
		</div>
	</fieldset>

	<!-- Fieldset 3: Saúde e Comportamento -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Saúde e Comportamento', 'desi-pet-shower' ); ?></legend>

		<p>
			<label>
				<?php echo esc_html__( 'Vacinas / Saúde', 'desi-pet-shower' ); ?><br>
				<textarea name="pet_vaccinations" rows="2" placeholder="<?php echo esc_attr__( 'Liste vacinas, condições médicas...', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $vaccinations ); ?></textarea>
			</label>
		</p>

		<p>
			<label>
				<?php echo esc_html__( 'Alergias / Restrições', 'desi-pet-shower' ); ?><br>
				<textarea name="pet_allergies" rows="2" placeholder="<?php echo esc_attr__( 'Alergias a alimentos, medicamentos...', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $allergies ); ?></textarea>
			</label>
		</p>

		<p>
			<label>
				<?php echo esc_html__( 'Cuidados especiais', 'desi-pet-shower' ); ?><br>
				<textarea name="pet_care" rows="2" placeholder="<?php echo esc_attr__( 'Necessita cuidados especiais durante o banho?', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $care_val ); ?></textarea>
			</label>
		</p>

		<p>
			<label>
				<?php echo esc_html__( 'Notas de comportamento', 'desi-pet-shower' ); ?><br>
				<textarea name="pet_behavior" rows="2" placeholder="<?php echo esc_attr__( 'Como o pet costuma se comportar?', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $behavior_val ); ?></textarea>
			</label>
		</p>

		<p>
			<label class="dps-checkbox-label">
				<input type="checkbox" name="pet_aggressive" value="1" <?php echo $checked_ag; ?>>
				<span class="dps-checkbox-text">⚠️ <?php echo esc_html__( 'Cão agressivo (requer cuidado especial)', 'desi-pet-shower' ); ?></span>
			</label>
		</p>
	</fieldset>

	<!-- Fieldset 4: Preferências de Produtos -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Preferências de Produtos', 'desi-pet-shower' ); ?></legend>
		<p class="dps-fieldset__description"><?php echo esc_html__( 'Restrições e preferências de produtos para o atendimento.', 'desi-pet-shower' ); ?></p>

		<div class="dps-form-row dps-form-row--3col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Shampoo', 'desi-pet-shower' ); ?><br>
					<select name="pet_shampoo_pref">
						<option value=""><?php echo esc_html__( 'Sem preferência', 'desi-pet-shower' ); ?></option>
						<option value="hipoalergenico" <?php selected( $shampoo_pref, 'hipoalergenico' ); ?>><?php echo esc_html__( 'Hipoalergênico', 'desi-pet-shower' ); ?></option>
						<option value="antisseptico" <?php selected( $shampoo_pref, 'antisseptico' ); ?>><?php echo esc_html__( 'Antisséptico', 'desi-pet-shower' ); ?></option>
						<option value="pelagem_branca" <?php selected( $shampoo_pref, 'pelagem_branca' ); ?>><?php echo esc_html__( 'Para pelagem branca', 'desi-pet-shower' ); ?></option>
						<option value="pelagem_escura" <?php selected( $shampoo_pref, 'pelagem_escura' ); ?>><?php echo esc_html__( 'Para pelagem escura', 'desi-pet-shower' ); ?></option>
						<option value="antipulgas" <?php selected( $shampoo_pref, 'antipulgas' ); ?>><?php echo esc_html__( 'Antipulgas', 'desi-pet-shower' ); ?></option>
						<option value="hidratante" <?php selected( $shampoo_pref, 'hidratante' ); ?>><?php echo esc_html__( 'Hidratante', 'desi-pet-shower' ); ?></option>
						<option value="outro" <?php selected( $shampoo_pref, 'outro' ); ?>><?php echo esc_html__( 'Outro', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Perfume', 'desi-pet-shower' ); ?><br>
					<select name="pet_perfume_pref">
						<option value=""><?php echo esc_html__( 'Sem preferência', 'desi-pet-shower' ); ?></option>
						<option value="suave" <?php selected( $perfume_pref, 'suave' ); ?>><?php echo esc_html__( 'Perfume suave', 'desi-pet-shower' ); ?></option>
						<option value="intenso" <?php selected( $perfume_pref, 'intenso' ); ?>><?php echo esc_html__( 'Perfume intenso', 'desi-pet-shower' ); ?></option>
						<option value="sem_perfume" <?php selected( $perfume_pref, 'sem_perfume' ); ?>><?php echo esc_html__( 'Sem perfume (proibido)', 'desi-pet-shower' ); ?></option>
						<option value="hipoalergenico" <?php selected( $perfume_pref, 'hipoalergenico' ); ?>><?php echo esc_html__( 'Hipoalergênico apenas', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Adereços', 'desi-pet-shower' ); ?><br>
					<select name="pet_accessories_pref">
						<option value=""><?php echo esc_html__( 'Sem preferência', 'desi-pet-shower' ); ?></option>
						<option value="lacinho" <?php selected( $accessories_pref, 'lacinho' ); ?>><?php echo esc_html__( 'Lacinho', 'desi-pet-shower' ); ?></option>
						<option value="gravata" <?php selected( $accessories_pref, 'gravata' ); ?>><?php echo esc_html__( 'Gravata', 'desi-pet-shower' ); ?></option>
						<option value="lenco" <?php selected( $accessories_pref, 'lenco' ); ?>><?php echo esc_html__( 'Lenço', 'desi-pet-shower' ); ?></option>
						<option value="bandana" <?php selected( $accessories_pref, 'bandana' ); ?>><?php echo esc_html__( 'Bandana', 'desi-pet-shower' ); ?></option>
						<option value="sem_aderecos" <?php selected( $accessories_pref, 'sem_aderecos' ); ?>><?php echo esc_html__( 'Não usar adereços', 'desi-pet-shower' ); ?></option>
					</select>
				</label>
			</p>
		</div>

		<p>
			<label>
				<?php echo esc_html__( 'Outras restrições de produtos', 'desi-pet-shower' ); ?><br>
				<textarea name="pet_product_restrictions" rows="2" placeholder="<?php echo esc_attr__( 'Ex.: Alérgico a produto X, usar apenas produtos naturais...', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $product_restrictions ); ?></textarea>
			</label>
		</p>
	</fieldset>

	<!-- Fieldset 5: Foto do Pet -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Foto do Pet', 'desi-pet-shower' ); ?></legend>

		<div class="dps-file-upload">
			<label class="dps-file-upload__label">
				<input type="file" name="pet_photo" accept="image/*" class="dps-file-upload__input">
				<span class="dps-file-upload__text">📷 <?php echo esc_html__( 'Escolher foto', 'desi-pet-shower' ); ?></span>
			</label>
			<?php if ( $photo_url ) : ?>
				<div class="dps-file-upload__preview">
					<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $pet_name ); ?>" loading="lazy">
				</div>
			<?php endif; ?>
		</div>
	</fieldset>

	<!-- Submit button -->
	<p>
		<button type="submit" class="dps-submit-btn"><?php echo $btn_text; ?></button>
	</p>
</form>

<script>
(function(){
	const breedData = <?php echo wp_json_encode( $breed_data ); ?>;
	const speciesSelect = document.getElementById('dps-pet-species');
	const breedInput = document.querySelector('input[name="pet_breed"]');
	const datalist = document.getElementById('dps-breed-list');
	
	if (!datalist || !speciesSelect) { return; }
	
	const unique = function(list) {
		const seen = new Set();
		return list.filter(function(item) {
			if (seen.has(item)) { return false; }
			seen.add(item);
			return true;
		});
	};
	
	const render = function(species) {
		const data = breedData[species] || breedData.all || { popular: [], all: [] };
		const items = unique([].concat(data.popular || [], data.all || []));
		datalist.innerHTML = '';
		items.forEach(function(breed) {
			const option = document.createElement('option');
			option.value = breed;
			datalist.appendChild(option);
		});
	};
	
	render(speciesSelect.value);
	
	speciesSelect.addEventListener('change', function() {
		render(this.value);
		if (breedInput) { breedInput.value = ''; }
	});
})();
</script>
