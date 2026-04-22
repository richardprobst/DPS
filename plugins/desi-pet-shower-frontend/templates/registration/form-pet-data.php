<?php
/**
 * Template: Signature registration pet repeater.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 *
 * @var array<string, mixed>  $data
 * @var array<string, string> $field_errors
 * @var array<string, mixed>  $breed_data
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$data         = $data ?? [];
$field_errors = $field_errors ?? [];
$breed_data   = $breed_data ?? [];
$pets         = isset( $data['pets'] ) && is_array( $data['pets'] ) && ! empty( $data['pets'] ) ? $data['pets'] : [ [] ];

$species_options = [
    ''      => __( 'Selecione a espécie', 'dps-frontend-addon' ),
    'cao'   => __( 'Cachorro', 'dps-frontend-addon' ),
    'gato'  => __( 'Gato', 'dps-frontend-addon' ),
    'outro' => __( 'Outro', 'dps-frontend-addon' ),
];

$sex_options = [
    ''       => __( 'Selecione o sexo', 'dps-frontend-addon' ),
    'macho'  => __( 'Macho', 'dps-frontend-addon' ),
    'femea'  => __( 'Fêmea', 'dps-frontend-addon' ),
];

$size_options = [
    ''        => __( 'Selecione o porte', 'dps-frontend-addon' ),
    'pequeno' => __( 'Pequeno', 'dps-frontend-addon' ),
    'medio'   => __( 'Médio', 'dps-frontend-addon' ),
    'grande'  => __( 'Grande', 'dps-frontend-addon' ),
];

$species_summary = [
    'cao'   => __( 'Cachorro', 'dps-frontend-addon' ),
    'gato'  => __( 'Gato', 'dps-frontend-addon' ),
    'outro' => __( 'Outro', 'dps-frontend-addon' ),
];

$size_summary = [
    'pequeno' => __( 'Porte pequeno', 'dps-frontend-addon' ),
    'medio'   => __( 'Porte médio', 'dps-frontend-addon' ),
    'grande'  => __( 'Porte grande', 'dps-frontend-addon' ),
];
?>

<section class="dps-registration-section" id="dps-registration-section-pets">
    <div class="dps-registration-section__header">
        <h2 class="dps-registration-section__title"><?php esc_html_e( 'Pets', 'dps-frontend-addon' ); ?></h2>
    </div>

    <div class="dps-registration-pet-stack" data-dps-registration-pets>
        <?php foreach ( $pets as $index => $pet ) : ?>
            <?php
            $pet_name_error    = $field_errors[ 'pets_' . $index . '_pet_name' ] ?? '';
            $pet_species_error = $field_errors[ 'pets_' . $index . '_pet_species' ] ?? '';
            $pet_size_error    = $field_errors[ 'pets_' . $index . '_pet_size' ] ?? '';
            $is_expanded       = 0 === (int) $index || '' !== $pet_name_error || '' !== $pet_species_error || '' !== $pet_size_error;
            $pet_name          = $pet['pet_name'] ?? '';
            $pet_species       = $pet['pet_species'] ?? '';
            $pet_size          = $pet['pet_size'] ?? '';
            $card_title        = '' !== $pet_name ? $pet_name : __( 'Novo pet', 'dps-frontend-addon' );
            $summary_parts     = [];
            if ( isset( $species_summary[ $pet_species ] ) ) {
                $summary_parts[] = $species_summary[ $pet_species ];
            }
            if ( isset( $size_summary[ $pet_size ] ) ) {
                $summary_parts[] = $size_summary[ $pet_size ];
            }
            $card_summary = ! empty( $summary_parts ) ? implode( ' • ', $summary_parts ) : '';
            $body_id      = 'dps-registration-pet-body-' . $index;
            $breed_list   = 'dps-registration-pet-breed-list-' . $index;
            ?>
            <article class="dps-registration-pet" data-pet-index="<?php echo esc_attr( (string) $index ); ?>">
                <div class="dps-registration-pet__header">
                    <div class="dps-registration-pet__identity">
                        <span class="dps-registration-pet__eyebrow"><?php echo esc_html( sprintf( __( 'Pet %d', 'dps-frontend-addon' ), $index + 1 ) ); ?></span>
                        <h3 class="dps-registration-pet__name" data-dps-pet-title><?php echo esc_html( $card_title ); ?></h3>
                        <p class="dps-registration-pet__summary" data-dps-pet-summary <?php echo '' === $card_summary ? 'hidden' : ''; ?>><?php echo esc_html( $card_summary ); ?></p>
                    </div>

                    <div class="dps-registration-pet__actions">
                        <button
                            type="button"
                            class="dps-registration-button dps-registration-button--secondary dps-registration-pet__remove"
                            data-dps-remove-pet
                            <?php echo 1 === count( $pets ) ? 'hidden' : ''; ?>
                        >
                            <span class="dps-registration-button__text"><?php esc_html_e( 'Remover', 'dps-frontend-addon' ); ?></span>
                        </button>
                        <button
                            type="button"
                            class="dps-registration-pet__toggle"
                            data-dps-pet-toggle
                            aria-expanded="<?php echo $is_expanded ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr( $body_id ); ?>"
                        >
                            <span><?php esc_html_e( 'Detalhes', 'dps-frontend-addon' ); ?></span>
                        </button>
                    </div>
                </div>

                <div id="<?php echo esc_attr( $body_id ); ?>" class="dps-registration-pet__body" <?php echo $is_expanded ? '' : 'hidden'; ?>>
                    <div class="dps-registration-grid dps-registration-grid--2">
                        <div class="dps-registration-field dps-registration-field--full">
                            <label class="dps-registration-field__label" for="dps-registration-pet-name-<?php echo esc_attr( (string) $index ); ?>">
                                <?php esc_html_e( 'Nome do pet', 'dps-frontend-addon' ); ?>
                                <span class="dps-registration-field__required" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="dps-registration-pet-name-<?php echo esc_attr( (string) $index ); ?>"
                                class="dps-registration-control"
                                type="text"
                                name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_name]"
                                value="<?php echo esc_attr( $pet_name ); ?>"
                                required
                                aria-required="true"
                                data-dps-pet-name
                                <?php echo '' !== $pet_name_error ? 'aria-invalid="true" aria-describedby="dps-registration-pet-name-error-' . esc_attr( (string) $index ) . '"' : ''; ?>
                            />
                            <?php if ( '' !== $pet_name_error ) : ?>
                                <p id="dps-registration-pet-name-error-<?php echo esc_attr( (string) $index ); ?>" class="dps-registration-field__error" role="alert"><?php echo esc_html( $pet_name_error ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="dps-registration-field">
                            <label class="dps-registration-field__label" for="dps-registration-pet-species-<?php echo esc_attr( (string) $index ); ?>">
                                <?php esc_html_e( 'Espécie', 'dps-frontend-addon' ); ?>
                                <span class="dps-registration-field__required" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="dps-registration-pet-species-<?php echo esc_attr( (string) $index ); ?>"
                                class="dps-registration-control"
                                name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_species]"
                                required
                                aria-required="true"
                                data-dps-breed-target="<?php echo esc_attr( $breed_list ); ?>"
                                data-dps-pet-species
                                <?php echo '' !== $pet_species_error ? 'aria-invalid="true" aria-describedby="dps-registration-pet-species-error-' . esc_attr( (string) $index ) . '"' : ''; ?>
                            >
                                <?php foreach ( $species_options as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pet_species, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( '' !== $pet_species_error ) : ?>
                                <p id="dps-registration-pet-species-error-<?php echo esc_attr( (string) $index ); ?>" class="dps-registration-field__error" role="alert"><?php echo esc_html( $pet_species_error ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="dps-registration-field">
                            <label class="dps-registration-field__label" for="dps-registration-pet-size-<?php echo esc_attr( (string) $index ); ?>">
                                <?php esc_html_e( 'Porte', 'dps-frontend-addon' ); ?>
                                <span class="dps-registration-field__required" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="dps-registration-pet-size-<?php echo esc_attr( (string) $index ); ?>"
                                class="dps-registration-control"
                                name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_size]"
                                required
                                aria-required="true"
                                data-dps-pet-size
                                <?php echo '' !== $pet_size_error ? 'aria-invalid="true" aria-describedby="dps-registration-pet-size-error-' . esc_attr( (string) $index ) . '"' : ''; ?>
                            >
                                <?php foreach ( $size_options as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pet['pet_size'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( '' !== $pet_size_error ) : ?>
                                <p id="dps-registration-pet-size-error-<?php echo esc_attr( (string) $index ); ?>" class="dps-registration-field__error" role="alert"><?php echo esc_html( $pet_size_error ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="dps-registration-field dps-registration-field--full">
                            <label class="dps-registration-field__label" for="dps-registration-pet-breed-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Raça', 'dps-frontend-addon' ); ?></label>
                            <input
                                id="dps-registration-pet-breed-<?php echo esc_attr( (string) $index ); ?>"
                                class="dps-registration-control"
                                type="text"
                                name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_breed]"
                                value="<?php echo esc_attr( $pet['pet_breed'] ?? '' ); ?>"
                                list="<?php echo esc_attr( $breed_list ); ?>"
                                autocomplete="off"
                            />
                            <datalist id="<?php echo esc_attr( $breed_list ); ?>" data-dps-breed-map="<?php echo esc_attr( wp_json_encode( $breed_data ) ); ?>"></datalist>
                        </div>
                    </div>

                    <details class="dps-registration-disclosure dps-registration-disclosure--pet">
                        <summary>
                            <span class="dps-registration-disclosure__label"><?php esc_html_e( 'Detalhes adicionais', 'dps-frontend-addon' ); ?></span>
                            <span class="dps-registration-disclosure__meta"><?php esc_html_e( 'Opcional', 'dps-frontend-addon' ); ?></span>
                        </summary>

                        <div class="dps-registration-disclosure__body">
                            <div class="dps-registration-grid dps-registration-grid--3">
                                <div class="dps-registration-field">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-sex-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Sexo', 'dps-frontend-addon' ); ?></label>
                                    <select
                                        id="dps-registration-pet-sex-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_sex]"
                                    >
                                        <?php foreach ( $sex_options as $value => $label ) : ?>
                                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pet['pet_sex'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="dps-registration-field">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-weight-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Peso (kg)', 'dps-frontend-addon' ); ?></label>
                                    <input
                                        id="dps-registration-pet-weight-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_weight]"
                                        value="<?php echo esc_attr( $pet['pet_weight'] ?? '' ); ?>"
                                        inputmode="decimal"
                                    />
                                </div>

                                <div class="dps-registration-field">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-birth-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Nascimento', 'dps-frontend-addon' ); ?></label>
                                    <input
                                        id="dps-registration-pet-birth-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        type="date"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_birth]"
                                        value="<?php echo esc_attr( $pet['pet_birth'] ?? '' ); ?>"
                                    />
                                </div>

                                <div class="dps-registration-field">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-coat-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Tipo de pelo', 'dps-frontend-addon' ); ?></label>
                                    <input
                                        id="dps-registration-pet-coat-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        type="text"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_coat]"
                                        value="<?php echo esc_attr( $pet['pet_coat'] ?? '' ); ?>"
                                        placeholder="<?php echo esc_attr__( 'Curto ou longo', 'dps-frontend-addon' ); ?>"
                                    />
                                </div>

                                <div class="dps-registration-field">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-color-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Cor predominante', 'dps-frontend-addon' ); ?></label>
                                    <input
                                        id="dps-registration-pet-color-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        type="text"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_color]"
                                        value="<?php echo esc_attr( $pet['pet_color'] ?? '' ); ?>"
                                        placeholder="<?php echo esc_attr__( 'Branco, preto ou caramelo', 'dps-frontend-addon' ); ?>"
                                    />
                                </div>

                                <div class="dps-registration-field dps-registration-field--full">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-care-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Cuidados especiais', 'dps-frontend-addon' ); ?></label>
                                    <textarea
                                        id="dps-registration-pet-care-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_care]"
                                        rows="3"
                                    ><?php echo esc_textarea( $pet['pet_care'] ?? '' ); ?></textarea>
                                </div>

                                <div class="dps-registration-field dps-registration-field--full">
                                    <label class="dps-registration-field__label" for="dps-registration-pet-obs-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Observações do atendimento', 'dps-frontend-addon' ); ?></label>
                                    <textarea
                                        id="dps-registration-pet-obs-<?php echo esc_attr( (string) $index ); ?>"
                                        class="dps-registration-control"
                                        name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_obs]"
                                        rows="3"
                                    ><?php echo esc_textarea( $pet['pet_obs'] ?? '' ); ?></textarea>
                                </div>

                                <div class="dps-registration-field dps-registration-field--full">
                                    <label class="dps-registration-check" for="dps-registration-pet-aggressive-<?php echo esc_attr( (string) $index ); ?>">
                                        <input type="hidden" name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_aggressive]" value="" />
                                        <input
                                            id="dps-registration-pet-aggressive-<?php echo esc_attr( (string) $index ); ?>"
                                            type="checkbox"
                                            name="pets[<?php echo esc_attr( (string) $index ); ?>][pet_aggressive]"
                                            value="1"
                                            <?php checked( ! empty( $pet['pet_aggressive'] ) ); ?>
                                        />
                                        <span><?php esc_html_e( 'Pet requer cuidado especial no atendimento.', 'dps-frontend-addon' ); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="dps-registration__footer dps-registration__footer--start">
        <button type="button" class="dps-registration-button dps-registration-button--secondary" data-dps-add-pet>
            <span class="dps-registration-button__text"><?php esc_html_e( 'Adicionar pet', 'dps-frontend-addon' ); ?></span>
        </button>
    </div>
</section>
