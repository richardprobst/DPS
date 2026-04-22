<?php
/**
 * Signature profile update form.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$client_id       = isset( $client_id ) ? (int) $client_id : ( isset( $client ) ? (int) $client->ID : 0 );
$client          = isset( $client ) ? $client : null;
$meta            = isset( $meta ) && is_array( $meta ) ? $meta : [];
$pets            = isset( $pets ) && is_array( $pets ) ? $pets : [];
$token           = isset( $token ) ? (string) $token : '';
$breed_data      = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];
$client_name     = $client ? $client->post_title : '';
$site_name       = get_bloginfo( 'name' );
$pets_total      = count( $pets );
?>

<div class="dps-signature-shell dps-signature-shell--profile dps-profile-update-signature">
    <div class="dps-signature-shell__layout">
        <section class="dps-signature-hero dps-profile-update-signature__hero" aria-labelledby="dps-profile-update-title">
            <div class="dps-signature-hero__grid">
                <div class="dps-profile-update-signature__hero-copy">
                    <span class="dps-signature-hero__eyebrow"><?php esc_html_e( 'Atualização de cadastro', 'dps-client-portal' ); ?></span>
                    <h1 id="dps-profile-update-title" class="dps-signature-hero__title"><?php printf( esc_html__( 'Olá, %s. Revise seus dados e os dados dos seus pets.', 'dps-client-portal' ), esc_html( $client_name ) ); ?></h1>
                    <p class="dps-signature-hero__lead"><?php esc_html_e( 'Atualize somente o que mudou.', 'dps-client-portal' ); ?></p>
                </div>

                <aside class="dps-signature-hero__aside">
                    <article class="dps-signature-metric-card">
                        <p class="dps-signature-metric-card__value"><?php echo esc_html( sprintf( _n( '%d pet vinculado', '%d pets vinculados', $pets_total, 'dps-client-portal' ), $pets_total ) ); ?></p>
                        <p class="dps-signature-metric-card__note"><?php esc_html_e( 'Revise os pets vinculados.', 'dps-client-portal' ); ?></p>
                    </article>
                </aside>
            </div>
        </section>

        <aside class="dps-signature-step-rail" aria-label="<?php esc_attr_e( 'Etapas da atualização', 'dps-client-portal' ); ?>">
            <p class="dps-signature-step-rail__title"><?php esc_html_e( 'Etapas', 'dps-client-portal' ); ?></p>
            <ol class="dps-signature-step-list">
                <li>
                    <button type="button" data-dps-profile-step="client" data-dps-profile-target="dps-profile-update-client">
                        <span class="dps-signature-step-list__index">1</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Tutor', 'dps-client-portal' ); ?></span>
                        </span>
                    </button>
                </li>
                <li>
                    <button type="button" data-dps-profile-step="pets" data-dps-profile-target="dps-profile-update-pets">
                        <span class="dps-signature-step-list__index">2</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Pets', 'dps-client-portal' ); ?></span>
                        </span>
                    </button>
                </li>
                <li>
                    <button type="button" data-dps-profile-step="finish" data-dps-profile-target="dps-profile-update-submit">
                        <span class="dps-signature-step-list__index">3</span>
                            <span class="dps-signature-step-list__text">
                                <span class="dps-signature-step-list__label"><?php esc_html_e( 'Salvar', 'dps-client-portal' ); ?></span>
                        </span>
                    </button>
                </li>
            </ol>
        </aside>
    </div>

    <section class="dps-signature-panel">
        <div class="dps-signature-panel__header">
            <span class="dps-signature-hero__tag"><?php echo esc_html( $site_name ); ?></span>
            <h2 class="dps-signature-panel__title"><?php esc_html_e( 'Atualize seu cadastro', 'dps-client-portal' ); ?></h2>
        </div>

        <div class="dps-profile-update-signature__messages">
            <?php
            if ( class_exists( 'DPS_Message_Helper' ) ) {
                echo DPS_Message_Helper::display_messages(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </div>

        <form method="post" class="dps-signature-form dps-profile-update-signature__form" id="dps-profile-update-form">
            <input type="hidden" name="dps_profile_token" value="<?php echo esc_attr( $token ); ?>" />
            <?php wp_nonce_field( 'dps_profile_update_' . $client_id, 'dps_profile_update_nonce' ); ?>

            <section class="dps-signature-section" id="dps-profile-update-client">
                    <div class="dps-signature-section__header">
                        <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Etapa 1', 'dps-client-portal' ); ?></p>
                        <h2 class="dps-signature-section__title"><?php esc_html_e( 'Dados do tutor', 'dps-client-portal' ); ?></h2>
                </div>

                <div class="dps-signature-grid dps-signature-grid--2">
                    <div class="dps-signature-field dps-signature-field--full">
                        <label class="dps-signature-field__label" for="dps-profile-client-name">
                            <?php esc_html_e( 'Nome completo', 'dps-client-portal' ); ?>
                            <span class="dps-signature-field__required" aria-hidden="true">*</span>
                        </label>
                        <input id="dps-profile-client-name" type="text" name="client_name" value="<?php echo esc_attr( $client_name ); ?>" autocomplete="name" required />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-cpf"><?php esc_html_e( 'CPF', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-cpf" type="text" name="client_cpf" value="<?php echo esc_attr( $meta['cpf'] ?? '' ); ?>" inputmode="numeric" data-dps-mask="cpf" placeholder="<?php echo esc_attr__( '000.000.000-00', 'dps-client-portal' ); ?>" />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-birth"><?php esc_html_e( 'Data de nascimento', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-birth" type="date" name="client_birth" value="<?php echo esc_attr( $meta['birth'] ?? '' ); ?>" autocomplete="bday" />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-phone">
                            <?php esc_html_e( 'Telefone / WhatsApp', 'dps-client-portal' ); ?>
                            <span class="dps-signature-field__required" aria-hidden="true">*</span>
                        </label>
                        <input id="dps-profile-client-phone" type="tel" name="client_phone" value="<?php echo esc_attr( $meta['phone'] ?? '' ); ?>" autocomplete="tel" inputmode="tel" data-dps-mask="phone" placeholder="<?php echo esc_attr__( '(00) 00000-0000', 'dps-client-portal' ); ?>" required />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-email"><?php esc_html_e( 'E-mail', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-email" type="email" name="client_email" value="<?php echo esc_attr( $meta['email'] ?? '' ); ?>" autocomplete="email" inputmode="email" placeholder="<?php echo esc_attr__( 'seu@email.com', 'dps-client-portal' ); ?>" />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-instagram"><?php esc_html_e( 'Instagram', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-instagram" type="text" name="client_instagram" value="<?php echo esc_attr( $meta['instagram'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( '@usuario', 'dps-client-portal' ); ?>" autocomplete="off" />
                    </div>

                    <div class="dps-signature-field">
                        <label class="dps-signature-field__label" for="dps-profile-client-facebook"><?php esc_html_e( 'Facebook', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-facebook" type="text" name="client_facebook" value="<?php echo esc_attr( $meta['facebook'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Nome do perfil', 'dps-client-portal' ); ?>" autocomplete="off" />
                    </div>

                    <div class="dps-signature-field dps-signature-field--full">
                        <label class="dps-signature-field__label" for="dps-profile-client-address"><?php esc_html_e( 'Endereço completo', 'dps-client-portal' ); ?></label>
                        <textarea id="dps-profile-client-address" name="client_address" rows="3" placeholder="<?php echo esc_attr__( 'Rua, número, bairro, cidade - UF', 'dps-client-portal' ); ?>"><?php echo esc_textarea( $meta['address'] ?? '' ); ?></textarea>
                    </div>

                    <div class="dps-signature-field dps-signature-field--full">
                        <label class="dps-signature-field__label" for="dps-profile-client-referral"><?php esc_html_e( 'Como nos conheceu?', 'dps-client-portal' ); ?></label>
                        <input id="dps-profile-client-referral" type="text" name="client_referral" value="<?php echo esc_attr( $meta['referral'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Google, indicação, Instagram…', 'dps-client-portal' ); ?>" />
                    </div>

                    <div class="dps-signature-field dps-signature-field--full">
                        <label class="dps-signature-check" for="dps-profile-client-photo-auth">
                            <input type="hidden" name="client_photo_auth" value="" />
                            <input id="dps-profile-client-photo-auth" type="checkbox" name="client_photo_auth" value="1" <?php checked( ! empty( $meta['photo_auth'] ) ); ?> />
                            <span><?php esc_html_e( 'Autorizo a publicação de fotos do meu pet nas redes sociais do estabelecimento.', 'dps-client-portal' ); ?></span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="dps-signature-section" id="dps-profile-update-pets">
                <div class="dps-signature-section__header">
                    <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Etapa 2', 'dps-client-portal' ); ?></p>
                    <h2 class="dps-signature-section__title"><?php esc_html_e( 'Pets do cadastro', 'dps-client-portal' ); ?></h2>
                </div>

                <?php if ( ! empty( $pets ) ) : ?>
                    <div class="dps-signature-card-stack" id="dps-existing-pets">
                        <?php foreach ( $pets as $index => $pet ) : ?>
                            <?php
                            $pet_id      = $pet->ID;
                            $pet_species = get_post_meta( $pet_id, 'pet_species', true );
                            $pet_size    = get_post_meta( $pet_id, 'pet_size', true );
                            $pet_summary = [];
                            if ( $pet_species ) {
                                $pet_summary[] = $pet_species;
                            }
                            if ( $pet_size ) {
                                $pet_summary[] = $pet_size;
                            }
                            $summary_text = ! empty( $pet_summary )
                                ? implode( ' • ', array_map( 'ucfirst', $pet_summary ) )
                                : __( 'Revise as informações deste pet.', 'dps-client-portal' );
                            $body_id      = 'dps-profile-existing-pet-' . $pet_id;
                            ?>
                            <article class="dps-signature-card dps-profile-update-signature__pet-card" data-dps-existing-pet>
                                <div class="dps-signature-card__header">
                                    <div class="dps-signature-card__identity">
                                        <span class="dps-signature-card__eyebrow"><?php echo esc_html( sprintf( __( 'Pet %d', 'dps-client-portal' ), $index + 1 ) ); ?></span>
                                        <h3 class="dps-signature-card__title" data-dps-pet-title><?php echo esc_html( $pet->post_title ); ?></h3>
                                        <p class="dps-signature-card__summary" data-dps-pet-summary><?php echo esc_html( $summary_text ); ?></p>
                                    </div>
                                    <button type="button" class="dps-signature-card__toggle" data-dps-disclosure-toggle aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $body_id ); ?>">
                                        <span><?php esc_html_e( 'Detalhes', 'dps-client-portal' ); ?></span>
                                        <span class="dps-signature-card__toggle-icon" aria-hidden="true">⌄</span>
                                    </button>
                                </div>

                                <div id="<?php echo esc_attr( $body_id ); ?>" class="dps-signature-card__body" <?php echo 0 === $index ? '' : 'hidden'; ?>>
                                    <div class="dps-signature-grid dps-signature-grid--3">
                                        <div class="dps-signature-field dps-signature-field--full">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-name-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Nome do pet', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-name-<?php echo esc_attr( (string) $pet_id ); ?>" type="text" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][name]" value="<?php echo esc_attr( $pet->post_title ); ?>" data-dps-pet-name required />
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-species-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Espécie', 'dps-client-portal' ); ?></label>
                                            <select id="dps-existing-pet-species-<?php echo esc_attr( (string) $pet_id ); ?>" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][species]" data-dps-breed-target="dps-existing-pet-breed-list-<?php echo esc_attr( (string) $pet_id ); ?>" data-dps-pet-species required>
                                                <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                                                <option value="cao" <?php selected( $pet_species, 'cao' ); ?>><?php esc_html_e( 'Cachorro', 'dps-client-portal' ); ?></option>
                                                <option value="gato" <?php selected( $pet_species, 'gato' ); ?>><?php esc_html_e( 'Gato', 'dps-client-portal' ); ?></option>
                                                <option value="outro" <?php selected( $pet_species, 'outro' ); ?>><?php esc_html_e( 'Outro', 'dps-client-portal' ); ?></option>
                                            </select>
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-breed-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Raça', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-breed-<?php echo esc_attr( (string) $pet_id ); ?>" type="text" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][breed]" value="<?php echo esc_attr( get_post_meta( $pet_id, 'pet_breed', true ) ); ?>" list="dps-existing-pet-breed-list-<?php echo esc_attr( (string) $pet_id ); ?>" autocomplete="off" />
                                            <datalist id="dps-existing-pet-breed-list-<?php echo esc_attr( (string) $pet_id ); ?>" data-dps-breed-map="<?php echo esc_attr( wp_json_encode( $breed_data ) ); ?>"></datalist>
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-sex-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Sexo', 'dps-client-portal' ); ?></label>
                                            <select id="dps-existing-pet-sex-<?php echo esc_attr( (string) $pet_id ); ?>" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][sex]">
                                                <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                                                <option value="macho" <?php selected( get_post_meta( $pet_id, 'pet_sex', true ), 'macho' ); ?>><?php esc_html_e( 'Macho', 'dps-client-portal' ); ?></option>
                                                <option value="femea" <?php selected( get_post_meta( $pet_id, 'pet_sex', true ), 'femea' ); ?>><?php esc_html_e( 'Fêmea', 'dps-client-portal' ); ?></option>
                                            </select>
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-size-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Porte', 'dps-client-portal' ); ?></label>
                                            <select id="dps-existing-pet-size-<?php echo esc_attr( (string) $pet_id ); ?>" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][size]" data-dps-pet-size>
                                                <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                                                <option value="pequeno" <?php selected( $pet_size, 'pequeno' ); ?>><?php esc_html_e( 'Pequeno', 'dps-client-portal' ); ?></option>
                                                <option value="medio" <?php selected( $pet_size, 'medio' ); ?>><?php esc_html_e( 'Médio', 'dps-client-portal' ); ?></option>
                                                <option value="grande" <?php selected( $pet_size, 'grande' ); ?>><?php esc_html_e( 'Grande', 'dps-client-portal' ); ?></option>
                                            </select>
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-weight-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Peso (kg)', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-weight-<?php echo esc_attr( (string) $pet_id ); ?>" type="number" step="0.1" min="0.1" max="100" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][weight]" value="<?php echo esc_attr( get_post_meta( $pet_id, 'pet_weight', true ) ); ?>" inputmode="decimal" />
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-birth-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Data de nascimento', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-birth-<?php echo esc_attr( (string) $pet_id ); ?>" type="date" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][birth]" value="<?php echo esc_attr( get_post_meta( $pet_id, 'pet_birth', true ) ); ?>" />
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-coat-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Tipo de pelo', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-coat-<?php echo esc_attr( (string) $pet_id ); ?>" type="text" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][coat]" value="<?php echo esc_attr( get_post_meta( $pet_id, 'pet_coat', true ) ); ?>" />
                                        </div>

                                        <div class="dps-signature-field">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-color-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Cor', 'dps-client-portal' ); ?></label>
                                            <input id="dps-existing-pet-color-<?php echo esc_attr( (string) $pet_id ); ?>" type="text" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][color]" value="<?php echo esc_attr( get_post_meta( $pet_id, 'pet_color', true ) ); ?>" />
                                        </div>

                                        <div class="dps-signature-field dps-signature-field--full">
                                            <label class="dps-signature-field__label" for="dps-existing-pet-care-<?php echo esc_attr( (string) $pet_id ); ?>"><?php esc_html_e( 'Cuidados especiais', 'dps-client-portal' ); ?></label>
                                            <textarea id="dps-existing-pet-care-<?php echo esc_attr( (string) $pet_id ); ?>" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][care]" rows="3"><?php echo esc_textarea( get_post_meta( $pet_id, 'pet_care', true ) ); ?></textarea>
                                        </div>

                                        <div class="dps-signature-field dps-signature-field--full">
                                            <label class="dps-signature-check" for="dps-existing-pet-aggressive-<?php echo esc_attr( (string) $pet_id ); ?>">
                                                <input type="hidden" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][aggressive]" value="" />
                                                <input id="dps-existing-pet-aggressive-<?php echo esc_attr( (string) $pet_id ); ?>" type="checkbox" name="pets[<?php echo esc_attr( (string) $pet_id ); ?>][aggressive]" value="1" <?php checked( ! empty( get_post_meta( $pet_id, 'pet_aggressive', true ) ) ); ?> />
                                                <span><?php esc_html_e( 'Pet requer cuidado especial no atendimento.', 'dps-client-portal' ); ?></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="dps-signature-empty"><?php esc_html_e( 'Você ainda não tem pets cadastrados.', 'dps-client-portal' ); ?></div>
                <?php endif; ?>

                <div class="dps-signature-actions">
                    <button type="button" class="dps-signature-button dps-signature-button--secondary" id="dps-add-new-pet" data-dps-profile-add-pet>
                        <span class="dps-signature-button__text"><?php esc_html_e( 'Adicionar novo pet', 'dps-client-portal' ); ?></span>
                    </button>
                </div>

                <div class="dps-signature-card-stack" id="dps-new-pets"></div>
            </section>

            <section class="dps-signature-section" id="dps-profile-update-submit">
                <div class="dps-signature-section__header">
                    <p class="dps-signature-section__eyebrow"><?php esc_html_e( 'Etapa 3', 'dps-client-portal' ); ?></p>
                    <h2 class="dps-signature-section__title"><?php esc_html_e( 'Salvar alterações', 'dps-client-portal' ); ?></h2>
                </div>

                <div class="dps-signature-actions dps-signature-actions--end">
                    <button type="submit" class="dps-signature-button">
                        <span class="dps-signature-button__text"><?php esc_html_e( 'Salvar alterações', 'dps-client-portal' ); ?></span>
                    </button>
                </div>
            </section>
        </form>
    </section>
</div>

<template id="dps-profile-update-new-pet-template">
    <article class="dps-signature-card dps-profile-update-signature__pet-card dps-profile-update-signature__pet-card--new" data-dps-new-pet-card data-new-pet-index="__INDEX__">
        <div class="dps-signature-card__header">
            <div class="dps-signature-card__identity">
                <span class="dps-signature-card__eyebrow"><?php esc_html_e( 'Novo pet', 'dps-client-portal' ); ?></span>
                <h3 class="dps-signature-card__title" data-dps-pet-title><?php esc_html_e( 'Novo pet', 'dps-client-portal' ); ?></h3>
                <p class="dps-signature-card__summary" data-dps-pet-summary><?php esc_html_e( 'Preencha os dados principais deste pet.', 'dps-client-portal' ); ?></p>
            </div>
            <div class="dps-signature-card__actions">
                <button type="button" class="dps-signature-button dps-signature-button--secondary" data-dps-remove-new-pet>
                    <span class="dps-signature-button__text"><?php esc_html_e( 'Remover', 'dps-client-portal' ); ?></span>
                </button>
                <button type="button" class="dps-signature-card__toggle" data-dps-disclosure-toggle aria-expanded="true" aria-controls="dps-profile-new-pet-__INDEX__">
                    <span><?php esc_html_e( 'Detalhes', 'dps-client-portal' ); ?></span>
                    <span class="dps-signature-card__toggle-icon" aria-hidden="true">⌄</span>
                </button>
            </div>
        </div>

        <div id="dps-profile-new-pet-__INDEX__" class="dps-signature-card__body">
            <div class="dps-signature-grid dps-signature-grid--3">
                <div class="dps-signature-field dps-signature-field--full">
                    <label class="dps-signature-field__label" for="dps-new-pet-name-__INDEX__"><?php esc_html_e( 'Nome do pet', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-name-__INDEX__" type="text" name="new_pets[__INDEX__][name]" data-dps-pet-name required />
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-species-__INDEX__"><?php esc_html_e( 'Espécie', 'dps-client-portal' ); ?></label>
                    <select id="dps-new-pet-species-__INDEX__" name="new_pets[__INDEX__][species]" data-dps-breed-target="dps-new-pet-breed-list-__INDEX__" data-dps-pet-species required>
                        <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                        <option value="cao"><?php esc_html_e( 'Cachorro', 'dps-client-portal' ); ?></option>
                        <option value="gato"><?php esc_html_e( 'Gato', 'dps-client-portal' ); ?></option>
                        <option value="outro"><?php esc_html_e( 'Outro', 'dps-client-portal' ); ?></option>
                    </select>
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-breed-__INDEX__"><?php esc_html_e( 'Raça', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-breed-__INDEX__" type="text" name="new_pets[__INDEX__][breed]" list="dps-new-pet-breed-list-__INDEX__" autocomplete="off" />
                    <datalist id="dps-new-pet-breed-list-__INDEX__" data-dps-breed-map="<?php echo esc_attr( wp_json_encode( $breed_data ) ); ?>"></datalist>
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-sex-__INDEX__"><?php esc_html_e( 'Sexo', 'dps-client-portal' ); ?></label>
                    <select id="dps-new-pet-sex-__INDEX__" name="new_pets[__INDEX__][sex]">
                        <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                        <option value="macho"><?php esc_html_e( 'Macho', 'dps-client-portal' ); ?></option>
                        <option value="femea"><?php esc_html_e( 'Fêmea', 'dps-client-portal' ); ?></option>
                    </select>
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-size-__INDEX__"><?php esc_html_e( 'Porte', 'dps-client-portal' ); ?></label>
                    <select id="dps-new-pet-size-__INDEX__" name="new_pets[__INDEX__][size]" data-dps-pet-size>
                        <option value=""><?php esc_html_e( 'Selecione…', 'dps-client-portal' ); ?></option>
                        <option value="pequeno"><?php esc_html_e( 'Pequeno', 'dps-client-portal' ); ?></option>
                        <option value="medio"><?php esc_html_e( 'Médio', 'dps-client-portal' ); ?></option>
                        <option value="grande"><?php esc_html_e( 'Grande', 'dps-client-portal' ); ?></option>
                    </select>
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-weight-__INDEX__"><?php esc_html_e( 'Peso (kg)', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-weight-__INDEX__" type="number" step="0.1" min="0.1" max="100" name="new_pets[__INDEX__][weight]" inputmode="decimal" />
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-birth-__INDEX__"><?php esc_html_e( 'Data de nascimento', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-birth-__INDEX__" type="date" name="new_pets[__INDEX__][birth]" />
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-coat-__INDEX__"><?php esc_html_e( 'Tipo de pelo', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-coat-__INDEX__" type="text" name="new_pets[__INDEX__][coat]" />
                </div>

                <div class="dps-signature-field">
                    <label class="dps-signature-field__label" for="dps-new-pet-color-__INDEX__"><?php esc_html_e( 'Cor', 'dps-client-portal' ); ?></label>
                    <input id="dps-new-pet-color-__INDEX__" type="text" name="new_pets[__INDEX__][color]" />
                </div>

                <div class="dps-signature-field dps-signature-field--full">
                    <label class="dps-signature-field__label" for="dps-new-pet-care-__INDEX__"><?php esc_html_e( 'Cuidados especiais', 'dps-client-portal' ); ?></label>
                    <textarea id="dps-new-pet-care-__INDEX__" name="new_pets[__INDEX__][care]" rows="3"></textarea>
                </div>

                <div class="dps-signature-field dps-signature-field--full">
                    <label class="dps-signature-check" for="dps-new-pet-aggressive-__INDEX__">
                        <input type="hidden" name="new_pets[__INDEX__][aggressive]" value="" />
                        <input id="dps-new-pet-aggressive-__INDEX__" type="checkbox" name="new_pets[__INDEX__][aggressive]" value="1" />
                        <span><?php esc_html_e( 'Pet requer cuidado especial no atendimento.', 'dps-client-portal' ); ?></span>
                    </label>
                </div>
            </div>
        </div>
    </article>
</template>
