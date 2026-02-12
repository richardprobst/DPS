<?php
/**
 * Template: Registration V2 — Pet Data Section (Repeater)
 *
 * Campos de pet com suporte a múltiplos pets.
 * Nome, espécie, raça (datalist), porte e observações.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, mixed>  $data           Dados preenchidos (sticky form).
 * @var array<string, string> $field_errors   Erros por campo.
 * @var array<string, string[]> $breed_data   Dataset de raças por espécie.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$data         = $data ?? [];
$field_errors = $field_errors ?? [];
$breed_data   = $breed_data ?? [];
$pets         = $data['pets'] ?? [ [] ]; // Pelo menos 1 pet vazio

$sizeOptions = [
    ''       => __( 'Selecione o porte...', 'dps-frontend-addon' ),
    'mini'   => __( 'Mini (até 3kg)', 'dps-frontend-addon' ),
    'small'  => __( 'Pequeno (3-10kg)', 'dps-frontend-addon' ),
    'medium' => __( 'Médio (10-25kg)', 'dps-frontend-addon' ),
    'large'  => __( 'Grande (25-45kg)', 'dps-frontend-addon' ),
    'giant'  => __( 'Gigante (45kg+)', 'dps-frontend-addon' ),
];

$speciesOptions = [
    ''     => __( 'Selecione a espécie...', 'dps-frontend-addon' ),
    'cao'  => __( 'Cão', 'dps-frontend-addon' ),
    'gato' => __( 'Gato', 'dps-frontend-addon' ),
];
?>

<fieldset class="dps-v2-registration__section">
    <legend class="dps-v2-typescale-title-large">
        <?php esc_html_e( 'Dados do Pet', 'dps-frontend-addon' ); ?>
    </legend>

    <div id="dps-v2-pet-repeater" class="dps-v2-registration__pet-repeater" data-breeds="<?php echo esc_attr( wp_json_encode( $breed_data ) ); ?>">

        <?php foreach ( $pets as $index => $pet ) : ?>
            <div class="dps-v2-pet-entry" data-pet-index="<?php echo esc_attr( (string) $index ); ?>">

                <?php if ( count( $pets ) > 1 || $index > 0 ) : ?>
                    <div class="dps-v2-pet-entry__header">
                        <span class="dps-v2-typescale-title-medium">
                            <?php
                            printf(
                                /* translators: %d: pet number */
                                esc_html__( 'Pet #%d', 'dps-frontend-addon' ),
                                $index + 1
                            );
                            ?>
                        </span>
                        <?php if ( $index > 0 ) : ?>
                            <button
                                type="button"
                                class="dps-v2-button dps-v2-button--text dps-v2-pet-remove"
                                aria-label="<?php esc_attr_e( 'Remover pet', 'dps-frontend-addon' ); ?>"
                            >
                                <span class="dps-v2-button__text"><?php esc_html_e( 'Remover', 'dps-frontend-addon' ); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="dps-v2-registration__fields">

                    <?php // Nome do pet (obrigatório) ?>
                    <div class="dps-v2-field dps-v2-field--text">
                        <label for="dps-v2-pet_name_<?php echo esc_attr( (string) $index ); ?>" class="dps-v2-field__label">
                            <?php esc_html_e( 'Nome do pet', 'dps-frontend-addon' ); ?>
                            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
                        </label>
                        <input
                            type="text"
                            id="dps-v2-pet_name_<?php echo esc_attr( (string) $index ); ?>"
                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_name]"
                            value="<?php echo esc_attr( $pet['pet_name'] ?? '' ); ?>"
                            class="dps-v2-field__input"
                            required
                        />
                    </div>

                    <?php // Espécie (obrigatória) ?>
                    <div class="dps-v2-field dps-v2-field--select">
                        <label for="dps-v2-pet_species_<?php echo esc_attr( (string) $index ); ?>" class="dps-v2-field__label">
                            <?php esc_html_e( 'Espécie', 'dps-frontend-addon' ); ?>
                            <span class="dps-v2-field__required" aria-label="<?php esc_attr_e( 'Obrigatório', 'dps-frontend-addon' ); ?>">*</span>
                        </label>
                        <select
                            id="dps-v2-pet_species_<?php echo esc_attr( (string) $index ); ?>"
                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_species]"
                            class="dps-v2-field__select dps-v2-species-select"
                            required
                        >
                            <?php foreach ( $speciesOptions as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $pet['pet_species'] ?? '', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php // Raça (datalist) ?>
                    <div class="dps-v2-field dps-v2-field--text">
                        <label for="dps-v2-pet_breed_<?php echo esc_attr( (string) $index ); ?>" class="dps-v2-field__label">
                            <?php esc_html_e( 'Raça', 'dps-frontend-addon' ); ?>
                        </label>
                        <input
                            type="text"
                            id="dps-v2-pet_breed_<?php echo esc_attr( (string) $index ); ?>"
                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_breed]"
                            value="<?php echo esc_attr( $pet['pet_breed'] ?? '' ); ?>"
                            class="dps-v2-field__input dps-v2-breed-input"
                            list="dps-v2-breed-list-<?php echo esc_attr( (string) $index ); ?>"
                            autocomplete="off"
                        />
                        <datalist id="dps-v2-breed-list-<?php echo esc_attr( (string) $index ); ?>"></datalist>
                    </div>

                    <?php // Porte ?>
                    <div class="dps-v2-field dps-v2-field--select">
                        <label for="dps-v2-pet_size_<?php echo esc_attr( (string) $index ); ?>" class="dps-v2-field__label">
                            <?php esc_html_e( 'Porte', 'dps-frontend-addon' ); ?>
                        </label>
                        <select
                            id="dps-v2-pet_size_<?php echo esc_attr( (string) $index ); ?>"
                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_size]"
                            class="dps-v2-field__select"
                        >
                            <?php foreach ( $sizeOptions as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $pet['pet_size'] ?? '', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php // Observações ?>
                    <div class="dps-v2-field dps-v2-field--textarea dps-v2-field--full-width">
                        <label for="dps-v2-pet_obs_<?php echo esc_attr( (string) $index ); ?>" class="dps-v2-field__label">
                            <?php esc_html_e( 'Observações', 'dps-frontend-addon' ); ?>
                        </label>
                        <textarea
                            id="dps-v2-pet_obs_<?php echo esc_attr( (string) $index ); ?>"
                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_obs]"
                            class="dps-v2-field__textarea"
                            rows="2"
                        ><?php echo esc_textarea( $pet['pet_obs'] ?? '' ); ?></textarea>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <div class="dps-v2-registration__pet-actions">
        <button
            type="button"
            id="dps-v2-add-pet"
            class="dps-v2-button dps-v2-button--secondary"
        >
            <span class="dps-v2-button__text"><?php esc_html_e( '+ Adicionar outro pet', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>

</fieldset>
