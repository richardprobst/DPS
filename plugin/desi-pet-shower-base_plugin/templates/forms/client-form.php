<?php
/**
 * Template de formulário de cadastro/edição de cliente.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/forms/client-form.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Extrai variáveis passadas para o template
$edit_id      = isset( $edit_id ) ? $edit_id : 0;
$editing      = isset( $editing ) ? $editing : null;
$meta         = isset( $meta ) && is_array( $meta ) ? $meta : [];
$api_key      = isset( $api_key ) ? $api_key : '';

// Valores do formulário
$name_value   = $editing ? $editing->post_title : '';
$cpf_val      = $meta['cpf'] ?? '';
$birth_val    = $meta['birth'] ?? '';
$phone_val    = $meta['phone'] ?? '';
$email_val    = $meta['email'] ?? '';
$insta_val    = $meta['instagram'] ?? '';
$fb_val       = $meta['facebook'] ?? '';
$addr_val     = $meta['address'] ?? '';
$ref_val      = $meta['referral'] ?? '';
$auth         = $meta['photo_auth'] ?? '';
$checked      = $auth ? 'checked' : '';
$lat_admin    = $meta['lat'] ?? '';
$lng_admin    = $meta['lng'] ?? '';
$btn_text     = $edit_id ? esc_html__( 'Atualizar Cliente', 'desi-pet-shower' ) : esc_html__( 'Salvar Cliente', 'desi-pet-shower' );
?>

<form method="post" class="dps-form">
	<!-- Hidden fields -->
	<input type="hidden" name="dps_action" value="save_client">
	<?php wp_nonce_field( 'dps_action', 'dps_nonce' ); ?>
	<?php if ( $edit_id ) : ?>
		<input type="hidden" name="client_id" value="<?php echo esc_attr( $edit_id ); ?>">
	<?php endif; ?>

	<!-- Grupo: Dados Pessoais -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Dados Pessoais', 'desi-pet-shower' ); ?></legend>

		<!-- Nome -->
		<p>
			<label>
				<?php echo esc_html__( 'Nome', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
				<input type="text" name="client_name" value="<?php echo esc_attr( $name_value ); ?>" required>
			</label>
		</p>

		<!-- CPF e Data de Nascimento em grid -->
		<div class="dps-form-row dps-form-row--2col">
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'CPF', 'desi-pet-shower' ); ?><br>
					<input type="text" name="client_cpf" value="<?php echo esc_attr( $cpf_val ); ?>" placeholder="000.000.000-00">
				</label>
			</p>
			<p class="dps-form-col">
				<label>
					<?php echo esc_html__( 'Data de nascimento', 'desi-pet-shower' ); ?><br>
					<input type="date" name="client_birth" value="<?php echo esc_attr( $birth_val ); ?>">
				</label>
			</p>
		</div>
	</fieldset>

	<!-- Grupo: Contato -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Contato', 'desi-pet-shower' ); ?></legend>

		<!-- Telefone / WhatsApp -->
		<p>
			<label>
				<?php echo esc_html__( 'Telefone / WhatsApp', 'desi-pet-shower' ); ?> <span class="dps-required">*</span><br>
				<input type="tel" name="client_phone" value="<?php echo esc_attr( $phone_val ); ?>" placeholder="(00) 00000-0000" required>
			</label>
		</p>

		<!-- Email -->
		<p>
			<label>
				Email<br>
				<input type="email" name="client_email" value="<?php echo esc_attr( $email_val ); ?>" placeholder="seuemail@exemplo.com">
			</label>
		</p>
	</fieldset>

	<!-- Grupo: Redes Sociais -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Redes Sociais', 'desi-pet-shower' ); ?></legend>

		<div class="dps-form-row dps-form-row--2col">
			<!-- Instagram -->
			<p class="dps-form-col">
				<label>
					Instagram<br>
					<input type="text" name="client_instagram" value="<?php echo esc_attr( $insta_val ); ?>" placeholder="@usuario">
				</label>
			</p>

			<!-- Facebook -->
			<p class="dps-form-col">
				<label>
					Facebook<br>
					<input type="text" name="client_facebook" value="<?php echo esc_attr( $fb_val ); ?>" placeholder="Nome do perfil">
				</label>
			</p>
		</div>
	</fieldset>

	<!-- Grupo: Endereço e Preferências -->
	<fieldset class="dps-fieldset">
		<legend class="dps-fieldset__legend"><?php echo esc_html__( 'Endereço e Preferências', 'desi-pet-shower' ); ?></legend>

		<!-- Endereço completo -->
		<p>
			<label>
				<?php echo esc_html__( 'Endereço completo', 'desi-pet-shower' ); ?><br>
				<textarea name="client_address" id="dps-client-address-admin" rows="2" placeholder="Rua, Número, Bairro, Cidade - UF"><?php echo esc_textarea( $addr_val ); ?></textarea>
			</label>
		</p>

		<!-- Como nos conheceu? -->
		<p>
			<label>
				<?php echo esc_html__( 'Como nos conheceu?', 'desi-pet-shower' ); ?><br>
				<input type="text" name="client_referral" value="<?php echo esc_attr( $ref_val ); ?>" placeholder="Google, indicação, Instagram...">
			</label>
		</p>

		<!-- Autorização de foto -->
		<p>
			<label class="dps-checkbox-label">
				<input type="checkbox" name="client_photo_auth" value="1" <?php echo $checked; ?>>
				<span class="dps-checkbox-text"><?php echo esc_html__( 'Autorizo publicação da foto do pet nas redes sociais do Desi Pet Shower', 'desi-pet-shower' ); ?></span>
			</label>
		</p>
	</fieldset>

	<!-- Campos ocultos para latitude e longitude (admin) -->
	<input type="hidden" name="client_lat" id="dps-client-lat-admin" value="<?php echo esc_attr( $lat_admin ); ?>">
	<input type="hidden" name="client_lng" id="dps-client-lng-admin" value="<?php echo esc_attr( $lng_admin ); ?>">

	<!-- Submit button -->
	<p>
		<button type="submit" class="button button-primary dps-submit-btn"><?php echo $btn_text; ?></button>
	</p>
</form>

<?php
// Se houver chave da API do Google Maps, injeta script de autocomplete de endereço
if ( $api_key ) :
	?>
	<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr( $api_key ); ?>&libraries=places"></script>
	<script>
	(function(){
		var input = document.getElementById("dps-client-address-admin");
		if ( input ) {
			var autocomplete = new google.maps.places.Autocomplete(input, { types: ["geocode"] });
			autocomplete.addListener("place_changed", function() {
				var place = autocomplete.getPlace();
				if ( place && place.geometry ) {
					var lat = place.geometry.location.lat();
					var lng = place.geometry.location.lng();
					var latField = document.getElementById("dps-client-lat-admin");
					var lngField = document.getElementById("dps-client-lng-admin");
					if ( latField && lngField ) {
						latField.value = lat;
						lngField.value = lng;
					}
				}
			});
		}
	})();
	</script>
	<?php
endif;
?>
