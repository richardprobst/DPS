<?php
/**
 * Template: Formulário de Atualização de Perfil
 * 
 * Este template renderiza o formulário público que permite ao cliente
 * atualizar seus próprios dados e de seus pets através de um link exclusivo.
 * 
 * Variáveis disponíveis:
 * @var WP_Post $client     Objeto do cliente.
 * @var int     $client_id  ID do cliente.
 * @var array   $meta       Metadados do cliente.
 * @var array   $pets       Lista de pets do cliente.
 * @var string  $token      Token de autenticação.
 * @var array   $breed_data Dataset de raças por espécie.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Extrai variáveis
$client_id  = isset( $client_id ) ? $client_id : ( isset( $client ) ? $client->ID : 0 );
$client     = isset( $client ) ? $client : null;
$meta       = isset( $meta ) && is_array( $meta ) ? $meta : [];
$pets       = isset( $pets ) && is_array( $pets ) ? $pets : [];
$token      = isset( $token ) ? $token : '';
$breed_data = isset( $breed_data ) && is_array( $breed_data ) ? $breed_data : [];

// Nome do cliente
$client_name = $client ? $client->post_title : '';

// Nome do estabelecimento
$site_name = get_bloginfo( 'name' );
?>

<div class="dps-profile-update-page">
    <div class="dps-profile-update-container">
        
        <!-- Header -->
        <header class="dps-profile-update-header">
            <div class="dps-profile-update-logo">🐾</div>
            <h1 class="dps-profile-update-title">
                <?php echo esc_html__( 'Atualização de Cadastro', 'dps-client-portal' ); ?>
            </h1>
            <p class="dps-profile-update-subtitle">
                <?php 
                printf(
                    /* translators: %s: nome do cliente */
                    esc_html__( 'Olá, %s! Atualize seus dados abaixo.', 'dps-client-portal' ),
                    esc_html( $client_name )
                ); 
                ?>
            </p>
        </header>
        
        <?php 
        // Exibe mensagens de feedback
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DPS_Message_Helper
            echo DPS_Message_Helper::display_messages();
        }
        ?>
        
        <form method="post" class="dps-profile-update-form" id="dps-profile-update-form">
            <!-- Hidden fields -->
            <input type="hidden" name="dps_profile_token" value="<?php echo esc_attr( $token ); ?>">
            <?php wp_nonce_field( 'dps_profile_update_' . $client_id, 'dps_profile_update_nonce' ); ?>
            
            <!-- Seção: Dados Pessoais -->
            <section class="dps-profile-section">
                <h2 class="dps-profile-section__title">
                    👤 <?php echo esc_html__( 'Dados Pessoais', 'dps-client-portal' ); ?>
                </h2>
                
                <div class="dps-profile-fields">
                    <!-- Nome -->
                    <div class="dps-profile-field dps-profile-field--full">
                        <label for="client_name">
                            <?php echo esc_html__( 'Nome Completo', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                        </label>
                        <input type="text" id="client_name" name="client_name" 
                               value="<?php echo esc_attr( $client_name ); ?>" 
                               autocomplete="name" required>
                    </div>
                    
                    <!-- CPF e Data de Nascimento -->
                    <div class="dps-profile-field">
                        <label for="client_cpf">
                            <?php echo esc_html__( 'CPF', 'dps-client-portal' ); ?>
                        </label>
                        <input type="text" id="client_cpf" name="client_cpf" 
                               value="<?php echo esc_attr( $meta['cpf'] ?? '' ); ?>" 
                               placeholder="000.000.000-00"
                               inputmode="numeric"
                               maxlength="14"
                               autocomplete="off">
                    </div>
                    
                    <div class="dps-profile-field">
                        <label for="client_birth">
                            <?php echo esc_html__( 'Data de Nascimento', 'dps-client-portal' ); ?>
                        </label>
                        <input type="date" id="client_birth" name="client_birth" 
                               value="<?php echo esc_attr( $meta['birth'] ?? '' ); ?>"
                               autocomplete="bday">
                    </div>
                </div>
            </section>
            
            <!-- Seção: Contato -->
            <section class="dps-profile-section">
                <h2 class="dps-profile-section__title">
                    📞 <?php echo esc_html__( 'Contato', 'dps-client-portal' ); ?>
                </h2>
                
                <div class="dps-profile-fields">
                    <!-- Telefone -->
                    <div class="dps-profile-field">
                        <label for="client_phone">
                            <?php echo esc_html__( 'Telefone / WhatsApp', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                        </label>
                        <input type="tel" id="client_phone" name="client_phone" 
                               value="<?php echo esc_attr( $meta['phone'] ?? '' ); ?>" 
                               placeholder="(00) 00000-0000" 
                               inputmode="tel"
                               maxlength="15"
                               autocomplete="tel"
                               required>
                    </div>
                    
                    <!-- Email -->
                    <div class="dps-profile-field">
                        <label for="client_email">
                            <?php echo esc_html__( 'Email', 'dps-client-portal' ); ?>
                        </label>
                        <input type="email" id="client_email" name="client_email" 
                               value="<?php echo esc_attr( $meta['email'] ?? '' ); ?>" 
                               placeholder="seu@email.com"
                               inputmode="email"
                               autocomplete="email">
                    </div>
                    
                    <!-- Instagram -->
                    <div class="dps-profile-field">
                        <label for="client_instagram">
                            <?php echo esc_html__( 'Instagram', 'dps-client-portal' ); ?>
                        </label>
                        <input type="text" id="client_instagram" name="client_instagram" 
                               value="<?php echo esc_attr( $meta['instagram'] ?? '' ); ?>" 
                               placeholder="@usuario">
                    </div>
                    
                    <!-- Facebook -->
                    <div class="dps-profile-field">
                        <label for="client_facebook">
                            <?php echo esc_html__( 'Facebook', 'dps-client-portal' ); ?>
                        </label>
                        <input type="text" id="client_facebook" name="client_facebook" 
                               value="<?php echo esc_attr( $meta['facebook'] ?? '' ); ?>" 
                               placeholder="Nome do perfil">
                    </div>
                </div>
            </section>
            
            <!-- Seção: Endereço -->
            <section class="dps-profile-section">
                <h2 class="dps-profile-section__title">
                    📍 <?php echo esc_html__( 'Endereço', 'dps-client-portal' ); ?>
                </h2>
                
                <div class="dps-profile-fields">
                    <!-- Endereço completo -->
                    <div class="dps-profile-field dps-profile-field--full">
                        <label for="client_address">
                            <?php echo esc_html__( 'Endereço Completo', 'dps-client-portal' ); ?>
                        </label>
                        <textarea id="client_address" name="client_address" rows="2" 
                                  placeholder="<?php echo esc_attr__( 'Rua, Número, Bairro, Cidade - UF', 'dps-client-portal' ); ?>"><?php echo esc_textarea( $meta['address'] ?? '' ); ?></textarea>
                    </div>
                    
                    <!-- Como nos conheceu -->
                    <div class="dps-profile-field dps-profile-field--full">
                        <label for="client_referral">
                            <?php echo esc_html__( 'Como nos conheceu?', 'dps-client-portal' ); ?>
                        </label>
                        <input type="text" id="client_referral" name="client_referral" 
                               value="<?php echo esc_attr( $meta['referral'] ?? '' ); ?>" 
                               placeholder="<?php echo esc_attr__( 'Google, indicação, Instagram...', 'dps-client-portal' ); ?>">
                    </div>
                    
                    <!-- Autorização de foto -->
                    <div class="dps-profile-field dps-profile-field--full">
                        <label class="dps-checkbox-label">
                            <input type="checkbox" name="client_photo_auth" value="1" 
                                   <?php checked( ! empty( $meta['photo_auth'] ) ); ?>>
                            <span class="dps-checkbox-text">
                                <?php echo esc_html__( 'Autorizo a publicação de fotos do meu pet nas redes sociais', 'dps-client-portal' ); ?>
                            </span>
                        </label>
                    </div>
                </div>
            </section>
            
            <!-- Seção: Meus Pets -->
            <section class="dps-profile-section dps-profile-section--pets">
                <h2 class="dps-profile-section__title">
                    🐾 <?php echo esc_html__( 'Meus Pets', 'dps-client-portal' ); ?>
                    <span class="dps-pet-count"><?php echo count( $pets ); ?></span>
                </h2>
                
                <?php if ( ! empty( $pets ) ) : ?>
                    <div class="dps-pets-list" id="dps-existing-pets">
                        <?php foreach ( $pets as $index => $pet ) : 
                            $pet_id = $pet->ID;
                            $pet_meta = [
                                'species'    => get_post_meta( $pet_id, 'pet_species', true ),
                                'breed'      => get_post_meta( $pet_id, 'pet_breed', true ),
                                'sex'        => get_post_meta( $pet_id, 'pet_sex', true ),
                                'size'       => get_post_meta( $pet_id, 'pet_size', true ),
                                'weight'     => get_post_meta( $pet_id, 'pet_weight', true ),
                                'birth'      => get_post_meta( $pet_id, 'pet_birth', true ),
                                'coat'       => get_post_meta( $pet_id, 'pet_coat', true ),
                                'color'      => get_post_meta( $pet_id, 'pet_color', true ),
                                'care'       => get_post_meta( $pet_id, 'pet_care', true ),
                                'aggressive' => get_post_meta( $pet_id, 'pet_aggressive', true ),
                            ];
                            
                            // Ícone baseado na espécie
                            $species_icon = '🐾';
                            if ( 'cao' === $pet_meta['species'] ) {
                                $species_icon = '🐕';
                            } elseif ( 'gato' === $pet_meta['species'] ) {
                                $species_icon = '🐈';
                            }
                        ?>
                        <div class="dps-pet-card" data-pet-index="<?php echo esc_attr( $index ); ?>">
                            <div class="dps-pet-card__header">
                                <span class="dps-pet-card__icon"><?php echo $species_icon; ?></span>
                                <h3 class="dps-pet-card__title"><?php echo esc_html( $pet->post_title ); ?></h3>
                                <button type="button" class="dps-pet-card__toggle" aria-expanded="false">
                                    ▼
                                </button>
                            </div>
                            
                            <div class="dps-pet-card__body">
                                <div class="dps-profile-fields">
                                    <!-- Nome do pet -->
                                    <div class="dps-profile-field dps-profile-field--full">
                                        <label>
                                            <?php echo esc_html__( 'Nome do Pet', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                                        </label>
                                        <input type="text" name="pets[<?php echo esc_attr( $pet_id ); ?>][name]" 
                                               value="<?php echo esc_attr( $pet->post_title ); ?>" required>
                                    </div>
                                    
                                    <!-- Espécie e Raça -->
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Espécie', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                                        </label>
                                        <select name="pets[<?php echo esc_attr( $pet_id ); ?>][species]" class="dps-species-select" required>
                                            <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                                            <option value="cao" <?php selected( $pet_meta['species'], 'cao' ); ?>><?php echo esc_html__( 'Cachorro', 'dps-client-portal' ); ?></option>
                                            <option value="gato" <?php selected( $pet_meta['species'], 'gato' ); ?>><?php echo esc_html__( 'Gato', 'dps-client-portal' ); ?></option>
                                            <option value="outro" <?php selected( $pet_meta['species'], 'outro' ); ?>><?php echo esc_html__( 'Outro', 'dps-client-portal' ); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Raça', 'dps-client-portal' ); ?>
                                        </label>
                                        <input type="text" name="pets[<?php echo esc_attr( $pet_id ); ?>][breed]" 
                                               value="<?php echo esc_attr( $pet_meta['breed'] ); ?>" 
                                               placeholder="<?php echo esc_attr__( 'Digite a raça', 'dps-client-portal' ); ?>">
                                    </div>
                                    
                                    <!-- Sexo e Porte -->
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Sexo', 'dps-client-portal' ); ?>
                                        </label>
                                        <select name="pets[<?php echo esc_attr( $pet_id ); ?>][sex]">
                                            <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                                            <option value="macho" <?php selected( $pet_meta['sex'], 'macho' ); ?>><?php echo esc_html__( 'Macho', 'dps-client-portal' ); ?></option>
                                            <option value="femea" <?php selected( $pet_meta['sex'], 'femea' ); ?>><?php echo esc_html__( 'Fêmea', 'dps-client-portal' ); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Porte', 'dps-client-portal' ); ?>
                                        </label>
                                        <select name="pets[<?php echo esc_attr( $pet_id ); ?>][size]">
                                            <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                                            <option value="pequeno" <?php selected( $pet_meta['size'], 'pequeno' ); ?>><?php echo esc_html__( 'Pequeno', 'dps-client-portal' ); ?></option>
                                            <option value="medio" <?php selected( $pet_meta['size'], 'medio' ); ?>><?php echo esc_html__( 'Médio', 'dps-client-portal' ); ?></option>
                                            <option value="grande" <?php selected( $pet_meta['size'], 'grande' ); ?>><?php echo esc_html__( 'Grande', 'dps-client-portal' ); ?></option>
                                        </select>
                                    </div>
                                    
                                    <!-- Peso e Nascimento -->
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Peso (kg)', 'dps-client-portal' ); ?>
                                        </label>
                                        <input type="number" step="0.1" min="0.1" max="100" 
                                               name="pets[<?php echo esc_attr( $pet_id ); ?>][weight]" 
                                               value="<?php echo esc_attr( $pet_meta['weight'] ); ?>" 
                                               placeholder="5.5">
                                    </div>
                                    
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Data de Nascimento', 'dps-client-portal' ); ?>
                                        </label>
                                        <input type="date" name="pets[<?php echo esc_attr( $pet_id ); ?>][birth]" 
                                               value="<?php echo esc_attr( $pet_meta['birth'] ); ?>">
                                    </div>
                                    
                                    <!-- Pelagem -->
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Tipo de Pelo', 'dps-client-portal' ); ?>
                                        </label>
                                        <input type="text" name="pets[<?php echo esc_attr( $pet_id ); ?>][coat]" 
                                               value="<?php echo esc_attr( $pet_meta['coat'] ); ?>" 
                                               placeholder="<?php echo esc_attr__( 'Curto, longo...', 'dps-client-portal' ); ?>">
                                    </div>
                                    
                                    <div class="dps-profile-field">
                                        <label>
                                            <?php echo esc_html__( 'Cor', 'dps-client-portal' ); ?>
                                        </label>
                                        <input type="text" name="pets[<?php echo esc_attr( $pet_id ); ?>][color]" 
                                               value="<?php echo esc_attr( $pet_meta['color'] ); ?>" 
                                               placeholder="<?php echo esc_attr__( 'Branco, preto...', 'dps-client-portal' ); ?>">
                                    </div>
                                    
                                    <!-- Cuidados especiais -->
                                    <div class="dps-profile-field dps-profile-field--full">
                                        <label>
                                            <?php echo esc_html__( 'Cuidados Especiais', 'dps-client-portal' ); ?>
                                        </label>
                                        <textarea name="pets[<?php echo esc_attr( $pet_id ); ?>][care]" rows="2" 
                                                  placeholder="<?php echo esc_attr__( 'Alguma informação importante sobre o pet?', 'dps-client-portal' ); ?>"><?php echo esc_textarea( $pet_meta['care'] ); ?></textarea>
                                    </div>
                                    
                                    <!-- Agressivo -->
                                    <div class="dps-profile-field dps-profile-field--full">
                                        <label class="dps-checkbox-label">
                                            <input type="checkbox" name="pets[<?php echo esc_attr( $pet_id ); ?>][aggressive]" value="1" 
                                                   <?php checked( ! empty( $pet_meta['aggressive'] ) ); ?>>
                                            <span class="dps-checkbox-text">
                                                ⚠️ <?php echo esc_html__( 'Pet requer cuidado especial (agressivo/nervoso)', 'dps-client-portal' ); ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="dps-no-pets-message">
                        <?php echo esc_html__( 'Você ainda não tem pets cadastrados.', 'dps-client-portal' ); ?>
                    </p>
                <?php endif; ?>
                
                <!-- Botão para adicionar novo pet -->
                <div class="dps-add-pet-section">
                    <button type="button" class="dps-btn-add-pet" id="dps-add-new-pet">
                        ➕ <?php echo esc_html__( 'Adicionar Novo Pet', 'dps-client-portal' ); ?>
                    </button>
                </div>
                
                <!-- Container para novos pets (preenchido via JS) -->
                <div class="dps-new-pets-list" id="dps-new-pets"></div>
            </section>
            
            <!-- Botão de envio -->
            <div class="dps-profile-submit">
                <button type="submit" class="dps-btn-submit">
                    ✅ <?php echo esc_html__( 'Salvar Alterações', 'dps-client-portal' ); ?>
                </button>
            </div>
        </form>
        
        <!-- Rodapé -->
        <footer class="dps-profile-update-footer">
            <p>
                <?php 
                printf(
                    /* translators: %s: nome do site */
                    esc_html__( 'Formulário de atualização cadastral – %s', 'dps-client-portal' ),
                    esc_html( $site_name )
                );
                ?>
            </p>
        </footer>
    </div>
</div>

<!-- Template para novo pet (usado pelo JavaScript) -->
<template id="dps-new-pet-template">
    <div class="dps-pet-card dps-pet-card--new" data-new-pet-index="{{INDEX}}">
        <div class="dps-pet-card__header">
            <span class="dps-pet-card__icon">🐾</span>
            <h3 class="dps-pet-card__title"><?php echo esc_html__( 'Novo Pet', 'dps-client-portal' ); ?></h3>
            <button type="button" class="dps-pet-card__remove" title="<?php echo esc_attr__( 'Remover', 'dps-client-portal' ); ?>">✕</button>
        </div>
        
        <div class="dps-pet-card__body">
            <div class="dps-profile-fields">
                <!-- Nome do pet -->
                <div class="dps-profile-field dps-profile-field--full">
                    <label>
                        <?php echo esc_html__( 'Nome do Pet', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                    </label>
                    <input type="text" name="new_pets[{{INDEX}}][name]" required 
                           placeholder="<?php echo esc_attr__( 'Nome do seu pet', 'dps-client-portal' ); ?>">
                </div>
                
                <!-- Espécie e Raça -->
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Espécie', 'dps-client-portal' ); ?> <span class="dps-required">*</span>
                    </label>
                    <select name="new_pets[{{INDEX}}][species]" class="dps-species-select" required>
                        <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                        <option value="cao"><?php echo esc_html__( 'Cachorro', 'dps-client-portal' ); ?></option>
                        <option value="gato"><?php echo esc_html__( 'Gato', 'dps-client-portal' ); ?></option>
                        <option value="outro"><?php echo esc_html__( 'Outro', 'dps-client-portal' ); ?></option>
                    </select>
                </div>
                
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Raça', 'dps-client-portal' ); ?>
                    </label>
                    <input type="text" name="new_pets[{{INDEX}}][breed]" 
                           placeholder="<?php echo esc_attr__( 'Digite a raça', 'dps-client-portal' ); ?>">
                </div>
                
                <!-- Sexo e Porte -->
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Sexo', 'dps-client-portal' ); ?>
                    </label>
                    <select name="new_pets[{{INDEX}}][sex]">
                        <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                        <option value="macho"><?php echo esc_html__( 'Macho', 'dps-client-portal' ); ?></option>
                        <option value="femea"><?php echo esc_html__( 'Fêmea', 'dps-client-portal' ); ?></option>
                    </select>
                </div>
                
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Porte', 'dps-client-portal' ); ?>
                    </label>
                    <select name="new_pets[{{INDEX}}][size]">
                        <option value=""><?php echo esc_html__( 'Selecione...', 'dps-client-portal' ); ?></option>
                        <option value="pequeno"><?php echo esc_html__( 'Pequeno', 'dps-client-portal' ); ?></option>
                        <option value="medio"><?php echo esc_html__( 'Médio', 'dps-client-portal' ); ?></option>
                        <option value="grande"><?php echo esc_html__( 'Grande', 'dps-client-portal' ); ?></option>
                    </select>
                </div>
                
                <!-- Peso e Nascimento -->
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Peso (kg)', 'dps-client-portal' ); ?>
                    </label>
                    <input type="number" step="0.1" min="0.1" max="100" 
                           name="new_pets[{{INDEX}}][weight]" placeholder="5.5">
                </div>
                
                <div class="dps-profile-field">
                    <label>
                        <?php echo esc_html__( 'Data de Nascimento', 'dps-client-portal' ); ?>
                    </label>
                    <input type="date" name="new_pets[{{INDEX}}][birth]">
                </div>
                
                <!-- Cuidados especiais -->
                <div class="dps-profile-field dps-profile-field--full">
                    <label>
                        <?php echo esc_html__( 'Cuidados Especiais', 'dps-client-portal' ); ?>
                    </label>
                    <textarea name="new_pets[{{INDEX}}][care]" rows="2" 
                              placeholder="<?php echo esc_attr__( 'Alguma informação importante sobre o pet?', 'dps-client-portal' ); ?>"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
(function() {
    var newPetIndex = 0;
    var addPetBtn = document.getElementById('dps-add-new-pet');
    var newPetsContainer = document.getElementById('dps-new-pets');
    var template = document.getElementById('dps-new-pet-template');
    var form = document.getElementById('dps-profile-update-form');
    
    // Adicionar novo pet
    if (addPetBtn && newPetsContainer && template) {
        addPetBtn.addEventListener('click', function() {
            var html = template.innerHTML.replace(/\{\{INDEX\}\}/g, newPetIndex);
            var div = document.createElement('div');
            div.innerHTML = html;
            var newCard = div.firstElementChild;
            newPetsContainer.appendChild(newCard);
            newPetIndex++;
            
            // Atualiza contador
            updatePetCount();
            
            // Foca no primeiro campo do novo pet
            var firstInput = newCard.querySelector('input[type="text"]');
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 100);
            }
            
            // Scroll suave para o novo card
            newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
    
    // Remover novo pet com confirmação
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-pet-card__remove')) {
            var card = e.target.closest('.dps-pet-card');
            if (card) {
                if (confirm('<?php echo esc_js( __( 'Deseja remover este pet?', 'dps-client-portal' ) ); ?>')) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';
                    card.style.transition = 'all 0.3s ease';
                    setTimeout(function() {
                        card.remove();
                        updatePetCount();
                    }, 300);
                }
            }
        }
    });
    
    // Toggle de cards de pets existentes
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.dps-pet-card__toggle');
        var header = e.target.closest('.dps-pet-card__header');
        
        if (toggle || (header && !e.target.closest('.dps-pet-card__remove'))) {
            var btn = toggle || header.querySelector('.dps-pet-card__toggle');
            if (!btn) return;
            
            var card = btn.closest('.dps-pet-card');
            var body = card.querySelector('.dps-pet-card__body');
            
            if (body) {
                var isExpanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !isExpanded);
                
                if (isExpanded) {
                    body.style.display = 'none';
                } else {
                    body.style.display = 'block';
                }
                btn.textContent = isExpanded ? '▼' : '▲';
            }
        }
    });
    
    // Atualiza contador de pets
    function updatePetCount() {
        var existingPets = document.querySelectorAll('#dps-existing-pets .dps-pet-card').length;
        var newPets = document.querySelectorAll('#dps-new-pets .dps-pet-card').length;
        var countEl = document.querySelector('.dps-pet-count');
        if (countEl) {
            countEl.textContent = existingPets + newPets;
        }
    }
    
    // Colapsar cards de pets por padrão
    document.querySelectorAll('.dps-pet-card__body').forEach(function(body) {
        body.style.display = 'none';
    });
    document.querySelectorAll('.dps-pet-card__toggle').forEach(function(btn) {
        btn.setAttribute('aria-expanded', 'false');
    });
    
    // Máscara de telefone brasileiro (celular: 11 dígitos, fixo: 10 dígitos)
    function maskPhone(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length === 11) {
            // Celular: (XX) 9XXXX-XXXX
            return '(' + value.slice(0,2) + ') ' + value.slice(2,7) + '-' + value.slice(7);
        } else if (value.length === 10) {
            // Fixo: (XX) XXXX-XXXX
            return '(' + value.slice(0,2) + ') ' + value.slice(2,6) + '-' + value.slice(6);
        } else if (value.length > 6) {
            return '(' + value.slice(0,2) + ') ' + value.slice(2,6) + '-' + value.slice(6);
        } else if (value.length > 2) {
            return '(' + value.slice(0,2) + ') ' + value.slice(2);
        } else if (value.length > 0) {
            return '(' + value;
        }
        return value;
    }
    
    // Máscara de CPF
    function maskCPF(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        if (value.length > 9) {
            return value.slice(0,3) + '.' + value.slice(3,6) + '.' + value.slice(6,9) + '-' + value.slice(9);
        } else if (value.length > 6) {
            return value.slice(0,3) + '.' + value.slice(3,6) + '.' + value.slice(6);
        } else if (value.length > 3) {
            return value.slice(0,3) + '.' + value.slice(3);
        }
        return value;
    }
    
    // Aplicar máscaras
    var phoneInput = document.getElementById('client_phone');
    var cpfInput = document.getElementById('client_cpf');
    
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            var cursorPos = e.target.selectionStart;
            var oldLength = e.target.value.length;
            e.target.value = maskPhone(e.target.value);
            var newCursorPos = cursorPos + (e.target.value.length - oldLength);
            e.target.setSelectionRange(newCursorPos, newCursorPos);
        });
    }
    
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            var cursorPos = e.target.selectionStart;
            var oldLength = e.target.value.length;
            e.target.value = maskCPF(e.target.value);
            var newCursorPos = cursorPos + (e.target.value.length - oldLength);
            e.target.setSelectionRange(newCursorPos, newCursorPos);
        });
    }
    
    // Validação visual do formulário antes do envio
    if (form) {
        form.addEventListener('submit', function(e) {
            var submitBtn = form.querySelector('.dps-btn-submit');
            var invalidFields = form.querySelectorAll(':invalid');
            
            if (invalidFields.length > 0) {
                e.preventDefault();
                
                // Encontra primeiro campo inválido e foca
                var firstInvalid = invalidFields[0];
                var card = firstInvalid.closest('.dps-pet-card');
                
                // Se estiver em um card colapsado, expande
                if (card) {
                    var body = card.querySelector('.dps-pet-card__body');
                    var toggle = card.querySelector('.dps-pet-card__toggle');
                    if (body && body.style.display === 'none') {
                        body.style.display = 'block';
                        if (toggle) {
                            toggle.setAttribute('aria-expanded', 'true');
                            toggle.textContent = '▲';
                        }
                    }
                }
                
                // Scroll e foco no campo inválido
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function() {
                    firstInvalid.focus();
                }, 300);
                
                // Adiciona classe de erro visual
                firstInvalid.style.borderColor = '#ef4444';
                firstInvalid.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.15)';
                setTimeout(function() {
                    firstInvalid.style.borderColor = '';
                    firstInvalid.style.boxShadow = '';
                }, 3000);
                
                return;
            }
            
            // Desabilita botão e mostra estado de envio
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="dps-spinner" aria-hidden="true"></span> <?php echo esc_js( __( 'Salvando...', 'dps-client-portal' ) ); ?>';
            }
        });
    }
    
    // Formatação de Instagram (adiciona @ se não existir)
    var instagramInput = document.getElementById('client_instagram');
    if (instagramInput) {
        instagramInput.addEventListener('blur', function(e) {
            var value = e.target.value.trim();
            if (value && !value.startsWith('@')) {
                e.target.value = '@' + value;
            }
        });
    }
})();
</script>

<style>
/* Estilos do formulário de atualização de perfil */
.dps-profile-update-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #f0f9ff 0%, #f9fafb 100%);
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.dps-profile-update-container {
    max-width: 800px;
    margin: 0 auto;
}

.dps-profile-update-header {
    text-align: center;
    margin-bottom: 32px;
    padding: 32px 24px;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.dps-profile-update-logo {
    font-size: 56px;
    margin-bottom: 16px;
    animation: bounce 1s ease-in-out;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.dps-profile-update-title {
    margin: 0 0 12px;
    font-size: 26px;
    font-weight: 600;
    color: #1f2937;
    letter-spacing: -0.025em;
}

.dps-profile-update-subtitle {
    margin: 0;
    color: #6b7280;
    font-size: 16px;
    line-height: 1.5;
}

/* Seções */
.dps-profile-section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    margin-bottom: 20px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.2s ease;
}

.dps-profile-section:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.dps-profile-section__title {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
}

.dps-pet-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 10px;
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 14px;
    font-size: 12px;
    font-weight: 700;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(14, 165, 233, 0.25);
}

/* Grid de campos */
.dps-profile-fields {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.dps-profile-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.dps-profile-field--full {
    grid-column: 1 / -1;
}

.dps-profile-field label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 4px;
}

.dps-profile-field input,
.dps-profile-field select,
.dps-profile-field textarea {
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px; /* Previne zoom automático no iOS */
    color: #374151;
    background: #ffffff;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    -webkit-appearance: none;
    appearance: none;
}

.dps-profile-field select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 16px 16px;
    padding-right: 40px;
}

.dps-profile-field input:hover,
.dps-profile-field select:hover,
.dps-profile-field textarea:hover {
    border-color: #9ca3af;
    background: #f9fafb;
}

.dps-profile-field input:focus,
.dps-profile-field select:focus,
.dps-profile-field textarea:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
    background: #ffffff;
}

/* Estado de validação */
.dps-profile-field input:invalid:not(:placeholder-shown),
.dps-profile-field select:invalid:not([value=""]),
.dps-profile-field textarea:invalid:not(:placeholder-shown) {
    border-color: #fbbf24;
}

.dps-profile-field input:valid:not(:placeholder-shown),
.dps-profile-field textarea:valid:not(:placeholder-shown) {
    border-color: #10b981;
}

.dps-required {
    color: #ef4444;
    font-weight: 700;
}

/* Indicador de campo obrigatório */
.dps-profile-field input:required,
.dps-profile-field select:required,
.dps-profile-field textarea:required {
    background-image: none;
}

/* Checkbox */
.dps-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
    padding: 12px 16px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    transition: background 0.2s, border-color 0.2s;
}

.dps-checkbox-label:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.dps-checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    min-width: 20px;
    margin: 0;
    margin-top: 2px;
    cursor: pointer;
    accent-color: #0ea5e9;
}

.dps-checkbox-text {
    font-size: 14px;
    color: #374151;
    line-height: 1.5;
}

/* Cards de pets */
.dps-pets-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.dps-pet-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: box-shadow 0.2s, border-color 0.2s;
}

.dps-pet-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.dps-pet-card--new {
    border: 2px dashed #0ea5e9;
    background: #f0f9ff;
}

.dps-pet-card__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.dps-pet-card__header:active {
    background: #f9fafb;
}

.dps-pet-card__icon {
    font-size: 28px;
    flex-shrink: 0;
}

.dps-pet-card__title {
    flex: 1;
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dps-pet-card__toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #f3f4f6;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    color: #6b7280;
    cursor: pointer;
    transition: background 0.2s, transform 0.3s;
    flex-shrink: 0;
}

.dps-pet-card__toggle:hover {
    background: #e5e7eb;
}

.dps-pet-card__toggle[aria-expanded="true"] {
    background: #0ea5e9;
    color: #ffffff;
    transform: rotate(180deg);
}

.dps-pet-card__remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #fee2e2;
    border: none;
    color: #ef4444;
    font-size: 16px;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.2s;
    flex-shrink: 0;
}

.dps-pet-card__remove:hover {
    background: #fecaca;
}

.dps-pet-card__body {
    padding: 20px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Botão adicionar pet */
.dps-add-pet-section {
    margin-top: 20px;
    text-align: center;
}

.dps-btn-add-pet {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px dashed #0ea5e9;
    border-radius: 12px;
    color: #0284c7;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    max-width: 320px;
}

.dps-btn-add-pet:hover {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-style: solid;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    transform: translateY(-2px);
}

.dps-btn-add-pet:active {
    transform: translateY(0);
}

.dps-new-pets-list {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.dps-no-pets-message {
    text-align: center;
    color: #6b7280;
    font-style: italic;
    padding: 32px 20px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px dashed #d1d5db;
}

/* Botão de envio */
.dps-profile-submit {
    text-align: center;
    margin-top: 32px;
    padding: 0 16px;
}

.dps-btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 48px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    border-radius: 12px;
    color: #ffffff;
    font-size: 17px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    min-width: 280px;
}

.dps-btn-submit:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-2px);
}

.dps-btn-submit:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.dps-btn-submit:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Spinner de carregamento */
.dps-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #ffffff;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Rodapé */
.dps-profile-update-footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    color: #9ca3af;
    font-size: 13px;
    border-top: 1px solid #e5e7eb;
}

/* Alertas */
.dps-alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.dps-alert::before {
    font-size: 20px;
    flex-shrink: 0;
}

.dps-alert--success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #10b981;
    color: #065f46;
}

.dps-alert--success::before {
    content: '✅';
}

.dps-alert--error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 1px solid #ef4444;
    color: #991b1b;
}

.dps-alert--error::before {
    content: '❌';
}

.dps-alert--warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    color: #92400e;
}

.dps-alert--warning::before {
    content: '⚠️';
}

/* Responsivo - Tablets */
@media (max-width: 768px) {
    .dps-profile-update-page {
        padding: 16px;
    }
    
    .dps-profile-update-header {
        padding: 24px 20px;
    }
    
    .dps-profile-update-title {
        font-size: 22px;
    }
    
    .dps-profile-section {
        padding: 20px;
        border-radius: 12px;
    }
    
    .dps-profile-fields {
        gap: 16px;
    }
    
    .dps-profile-section__title {
        font-size: 16px;
    }
    
    .dps-btn-submit {
        min-width: 100%;
        padding: 16px 32px;
    }
}

/* Responsivo - Mobile */
@media (max-width: 640px) {
    .dps-profile-fields {
        grid-template-columns: 1fr;
    }
    
    .dps-profile-update-page {
        padding: 12px;
    }
    
    .dps-profile-section {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .dps-profile-update-header {
        padding: 20px 16px;
        margin-bottom: 20px;
    }
    
    .dps-profile-update-logo {
        font-size: 48px;
    }
    
    .dps-profile-update-title {
        font-size: 20px;
    }
    
    .dps-profile-update-subtitle {
        font-size: 14px;
    }
    
    .dps-btn-submit {
        width: 100%;
        justify-content: center;
        font-size: 16px;
        padding: 16px 24px;
    }
    
    .dps-pet-card__header {
        padding: 14px;
    }
    
    .dps-pet-card__icon {
        font-size: 24px;
    }
    
    .dps-pet-card__toggle,
    .dps-pet-card__remove {
        width: 36px;
        height: 36px;
    }
    
    .dps-pet-card__body {
        padding: 16px;
    }
    
    .dps-btn-add-pet {
        width: 100%;
        max-width: none;
        padding: 14px 20px;
    }
    
    .dps-checkbox-label {
        padding: 12px;
    }
    
    .dps-profile-submit {
        padding: 0 8px;
    }
    
    .dps-alert {
        padding: 14px 16px;
        font-size: 14px;
    }
}

/* Responsivo - Mobile pequeno */
@media (max-width: 480px) {
    .dps-profile-update-page {
        padding: 8px;
    }
    
    .dps-profile-section {
        padding: 14px;
        border-radius: 10px;
    }
    
    .dps-profile-update-header {
        padding: 16px 14px;
        border-radius: 12px;
    }
    
    .dps-profile-update-title {
        font-size: 18px;
    }
    
    .dps-profile-section__title {
        font-size: 15px;
        flex-direction: row;
        justify-content: space-between;
    }
    
    .dps-profile-field label {
        font-size: 13px;
    }
    
    .dps-profile-field input,
    .dps-profile-field select,
    .dps-profile-field textarea {
        padding: 12px;
        font-size: 16px; /* Mantém 16px para evitar zoom no iOS */
    }
    
    .dps-checkbox-text {
        font-size: 13px;
    }
    
    .dps-pet-card__title {
        font-size: 14px;
    }
    
    .dps-btn-submit {
        font-size: 15px;
        padding: 14px 20px;
    }
    
    .dps-profile-update-footer {
        padding: 16px 12px;
        font-size: 12px;
    }
}

/* Acessibilidade - Modo alto contraste */
@media (prefers-contrast: high) {
    .dps-profile-field input,
    .dps-profile-field select,
    .dps-profile-field textarea {
        border-width: 2px;
    }
    
    .dps-btn-submit {
        border: 2px solid #065f46;
    }
    
    .dps-pet-card__toggle {
        border: 2px solid #374151;
    }
}

/* Acessibilidade - Preferência de movimento reduzido */
@media (prefers-reduced-motion: reduce) {
    .dps-profile-update-logo {
        animation: none;
    }
    
    .dps-pet-card__body {
        animation: none;
    }
    
    .dps-btn-submit,
    .dps-btn-add-pet,
    .dps-pet-card__toggle {
        transition: none;
    }
}

/* Focus visible para navegação por teclado */
.dps-btn-submit:focus-visible,
.dps-btn-add-pet:focus-visible,
.dps-pet-card__toggle:focus-visible,
.dps-pet-card__remove:focus-visible {
    outline: 3px solid #0ea5e9;
    outline-offset: 2px;
}

/* Safe area para dispositivos com notch */
@supports (padding: env(safe-area-inset-bottom)) {
    .dps-profile-submit {
        padding-bottom: env(safe-area-inset-bottom);
    }
    
    .dps-profile-update-page {
        padding-left: max(12px, env(safe-area-inset-left));
        padding-right: max(12px, env(safe-area-inset-right));
    }
}
</style>
