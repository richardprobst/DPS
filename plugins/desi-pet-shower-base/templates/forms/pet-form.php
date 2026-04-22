<?php
/**
 * Pet create/edit form aligned to DPS Signature.
 *
 * Override path:
 * wp-content/themes/SEU_TEMA/dps-templates/forms/pet-form.php
 *
 * @package DesiPetShower
 * @since 1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$edit_id              = isset( $edit_id ) ? (int) $edit_id : 0;
$editing              = isset( $editing ) ? $editing : null;
$meta                 = isset( $meta ) && is_array( $meta ) ? $meta : [];
$clients              = isset( $clients ) && is_array( $clients ) ? $clients : [];
$breed_options        = isset( $breed_options ) && is_array( $breed_options ) ? $breed_options : [];
$breed_data           = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];
$pet_name             = $editing ? $editing->post_title : '';
$owner_selected       = $meta['owner_id'] ?? '';
$photo_id             = $meta['photo_id'] ?? '';
$photo_url            = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
$btn_text             = $edit_id ? esc_html__( 'Atualizar Pet', 'desi-pet-shower' ) : esc_html__( 'Salvar Pet', 'desi-pet-shower' );
$species_options      = [
    ''      => __( 'Selecione…', 'desi-pet-shower' ),
    'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
    'gato'  => __( 'Gato', 'desi-pet-shower' ),
    'outro' => __( 'Outro', 'desi-pet-shower' ),
];
$sex_options          = [
    ''       => __( 'Selecione…', 'desi-pet-shower' ),
    'macho'  => __( 'Macho', 'desi-pet-shower' ),
    'femea'  => __( 'Fêmea', 'desi-pet-shower' ),
];
$size_options         = [
    ''        => __( 'Selecione…', 'desi-pet-shower' ),
    'pequeno' => __( 'Pequeno', 'desi-pet-shower' ),
    'medio'   => __( 'Médio', 'desi-pet-shower' ),
    'grande'  => __( 'Grande', 'desi-pet-shower' ),
];
$shampoo_options      = [
    ''               => __( 'Sem preferência', 'desi-pet-shower' ),
    'hipoalergenico' => __( 'Hipoalergênico', 'desi-pet-shower' ),
    'antisseptico'   => __( 'Antisséptico', 'desi-pet-shower' ),
    'pelagem_branca' => __( 'Para pelagem branca', 'desi-pet-shower' ),
    'pelagem_escura' => __( 'Para pelagem escura', 'desi-pet-shower' ),
    'antipulgas'     => __( 'Antipulgas', 'desi-pet-shower' ),
    'hidratante'     => __( 'Hidratante', 'desi-pet-shower' ),
    'outro'          => __( 'Outro', 'desi-pet-shower' ),
];
$perfume_options      = [
    ''               => __( 'Sem preferência', 'desi-pet-shower' ),
    'suave'          => __( 'Perfume suave', 'desi-pet-shower' ),
    'intenso'        => __( 'Perfume intenso', 'desi-pet-shower' ),
    'sem_perfume'    => __( 'Sem perfume (proibido)', 'desi-pet-shower' ),
    'hipoalergenico' => __( 'Hipoalergênico apenas', 'desi-pet-shower' ),
];
$accessory_options    = [
    ''               => __( 'Sem preferência', 'desi-pet-shower' ),
    'lacinho'        => __( 'Lacinho', 'desi-pet-shower' ),
    'gravata'        => __( 'Gravata', 'desi-pet-shower' ),
    'lenco'          => __( 'Lenço', 'desi-pet-shower' ),
    'bandana'        => __( 'Bandana', 'desi-pet-shower' ),
    'sem_aderecos'   => __( 'Não usar adereços', 'desi-pet-shower' ),
];
?>

<form method="post" enctype="multipart/form-data" class="dps-form dps-form--pet dps-signature-form">
    <input type="hidden" name="dps_action" value="save_pet" />
    <?php wp_nonce_field( 'dps_action', 'dps_nonce_pets' ); ?>
    <?php if ( $edit_id ) : ?>
        <input type="hidden" name="pet_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
    <?php endif; ?>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Cadastro interno', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Dados principais do pet', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--2">
            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-name">
                    <?php esc_html_e( 'Nome do pet', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <input id="dps-pet-name" type="text" name="pet_name" value="<?php echo esc_attr( $pet_name ); ?>" required />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-owner">
                    <?php esc_html_e( 'Cliente (tutor)', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <select id="dps-pet-owner" name="owner_id" required>
                    <option value=""><?php esc_html_e( 'Selecione…', 'desi-pet-shower' ); ?></option>
                    <?php foreach ( $clients as $client ) : ?>
                        <option value="<?php echo esc_attr( (string) $client->ID ); ?>" <?php selected( (string) $client->ID, (string) $owner_selected ); ?>>
                            <?php echo esc_html( $client->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-species">
                    <?php esc_html_e( 'Espécie', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <select id="dps-pet-species" name="pet_species" data-dps-breed-target="dps-pet-breed-list" required>
                    <?php foreach ( $species_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['species'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-breed"><?php esc_html_e( 'Raça', 'desi-pet-shower' ); ?></label>
                <input id="dps-pet-breed" type="text" name="pet_breed" value="<?php echo esc_attr( $meta['breed'] ?? '' ); ?>" list="dps-pet-breed-list" autocomplete="off" placeholder="<?php echo esc_attr__( 'Digite ou selecione', 'desi-pet-shower' ); ?>" />
                <datalist id="dps-pet-breed-list" data-dps-breed-map="<?php echo esc_attr( wp_json_encode( $breed_data ) ); ?>">
                    <?php foreach ( $breed_options as $breed ) : ?>
                        <option value="<?php echo esc_attr( $breed ); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-sex">
                    <?php esc_html_e( 'Sexo', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <select id="dps-pet-sex" name="pet_sex" required>
                    <?php foreach ( $sex_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['sex'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-size">
                    <?php esc_html_e( 'Porte', 'desi-pet-shower' ); ?>
                    <span class="dps-signature-field__required" aria-hidden="true">*</span>
                </label>
                <select id="dps-pet-size" name="pet_size" required>
                    <?php foreach ( $size_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['size'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Ficha física', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Características do pet', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--3">
            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-weight"><?php esc_html_e( 'Peso (kg)', 'desi-pet-shower' ); ?></label>
                <input id="dps-pet-weight" type="number" step="0.1" min="0.1" max="100" name="pet_weight" value="<?php echo esc_attr( $meta['weight'] ?? '' ); ?>" inputmode="decimal" />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-birth"><?php esc_html_e( 'Data de nascimento', 'desi-pet-shower' ); ?></label>
                <input id="dps-pet-birth" type="date" name="pet_birth" value="<?php echo esc_attr( $meta['birth'] ?? '' ); ?>" />
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-coat"><?php esc_html_e( 'Tipo de pelo', 'desi-pet-shower' ); ?></label>
                <input id="dps-pet-coat" type="text" name="pet_coat" value="<?php echo esc_attr( $meta['coat'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Curto, longo, encaracolado…', 'desi-pet-shower' ); ?>" />
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-color"><?php esc_html_e( 'Cor predominante', 'desi-pet-shower' ); ?></label>
                <input id="dps-pet-color" type="text" name="pet_color" value="<?php echo esc_attr( $meta['color'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Branco, preto, caramelo…', 'desi-pet-shower' ); ?>" />
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Saúde & comportamento', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Observações do atendimento', 'desi-pet-shower' ); ?></h2>
        </div>

        <div class="dps-signature-grid dps-signature-grid--2">
            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-vaccinations"><?php esc_html_e( 'Vacinas / saúde', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-pet-vaccinations" name="pet_vaccinations" rows="3" placeholder="<?php echo esc_attr__( 'Liste vacinas, condições médicas…', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $meta['vaccinations'] ?? '' ); ?></textarea>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-allergies"><?php esc_html_e( 'Alergias / restrições', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-pet-allergies" name="pet_allergies" rows="3" placeholder="<?php echo esc_attr__( 'Alergias alimentares, medicamentosas…', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $meta['allergies'] ?? '' ); ?></textarea>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-care"><?php esc_html_e( 'Cuidados especiais', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-pet-care" name="pet_care" rows="3" placeholder="<?php echo esc_attr__( 'Necessita cuidados especiais durante o banho?', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $meta['care'] ?? '' ); ?></textarea>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-behavior"><?php esc_html_e( 'Notas de comportamento', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-pet-behavior" name="pet_behavior" rows="3" placeholder="<?php echo esc_attr__( 'Como o pet costuma se comportar?', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $meta['behavior'] ?? '' ); ?></textarea>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-check" for="dps-pet-aggressive">
                    <input type="hidden" name="pet_aggressive" value="" />
                    <input id="dps-pet-aggressive" type="checkbox" name="pet_aggressive" value="1" <?php checked( ! empty( $meta['aggressive'] ) ); ?> />
                    <span><?php esc_html_e( 'Pet requer cuidado especial (agressivo, nervoso ou muito reativo).', 'desi-pet-shower' ); ?></span>
                </label>
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Produtos', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Preferências de produtos e adereços', 'desi-pet-shower' ); ?></h2>
            <p class="dps-signature-section__description"><?php esc_html_e( 'Registre restrições e preferências do atendimento para reduzir retrabalho e manter padrão de execução.', 'desi-pet-shower' ); ?></p>
        </div>

        <div class="dps-signature-grid dps-signature-grid--3">
            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-shampoo-pref"><?php esc_html_e( 'Shampoo', 'desi-pet-shower' ); ?></label>
                <select id="dps-pet-shampoo-pref" name="pet_shampoo_pref">
                    <?php foreach ( $shampoo_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['shampoo_pref'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-perfume-pref"><?php esc_html_e( 'Perfume', 'desi-pet-shower' ); ?></label>
                <select id="dps-pet-perfume-pref" name="pet_perfume_pref">
                    <?php foreach ( $perfume_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['perfume_pref'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field">
                <label class="dps-signature-field__label" for="dps-pet-accessories-pref"><?php esc_html_e( 'Adereços', 'desi-pet-shower' ); ?></label>
                <select id="dps-pet-accessories-pref" name="pet_accessories_pref">
                    <?php foreach ( $accessory_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta['accessories_pref'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="dps-signature-field dps-signature-field--full">
                <label class="dps-signature-field__label" for="dps-pet-product-restrictions"><?php esc_html_e( 'Outras restrições de produtos', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-pet-product-restrictions" name="pet_product_restrictions" rows="3" placeholder="<?php echo esc_attr__( 'Ex.: alérgico a produto X, usar apenas produtos naturais…', 'desi-pet-shower' ); ?>"><?php echo esc_textarea( $meta['product_restrictions'] ?? '' ); ?></textarea>
            </div>
        </div>
    </section>

    <section class="dps-signature-section">
        <div class="dps-signature-section__header">
            <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Arquivo visual', 'desi-pet-shower' ); ?></p>
            <h2 class="dps-signature-section__title"><?php esc_html_e( 'Foto do pet', 'desi-pet-shower' ); ?></h2>
            <p class="dps-signature-section__description"><?php esc_html_e( 'A foto ajuda na identificação rápida no atendimento e no histórico interno.', 'desi-pet-shower' ); ?></p>
        </div>

        <div class="dps-signature-file">
            <label class="dps-signature-file-trigger" for="dps-pet-photo">
                <span><?php esc_html_e( 'Escolher foto', 'desi-pet-shower' ); ?></span>
            </label>
            <input id="dps-pet-photo" class="dps-signature-file__input" type="file" name="pet_photo" accept="image/*" />

            <?php if ( $photo_url ) : ?>
                <div class="dps-signature-file__preview">
                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $pet_name ); ?>" loading="lazy" />
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="dps-signature-actions dps-signature-actions--end">
        <p class="dps-signature-actions__hint"><?php esc_html_e( 'O salvamento continua usando os mesmos contratos do formulário interno original.', 'desi-pet-shower' ); ?></p>
        <button type="submit" class="dps-submit-btn dps-signature-button">
            <span class="dps-signature-button__text"><?php echo esc_html( $btn_text ); ?></span>
        </button>
    </div>
</form>
