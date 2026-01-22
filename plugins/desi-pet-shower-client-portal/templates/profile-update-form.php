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
                               value="<?php echo esc_attr( $client_name ); ?>" required>
                    </div>
                    
                    <!-- CPF e Data de Nascimento -->
                    <div class="dps-profile-field">
                        <label for="client_cpf">
                            <?php echo esc_html__( 'CPF', 'dps-client-portal' ); ?>
                        </label>
                        <input type="text" id="client_cpf" name="client_cpf" 
                               value="<?php echo esc_attr( $meta['cpf'] ?? '' ); ?>" 
                               placeholder="000.000.000-00">
                    </div>
                    
                    <div class="dps-profile-field">
                        <label for="client_birth">
                            <?php echo esc_html__( 'Data de Nascimento', 'dps-client-portal' ); ?>
                        </label>
                        <input type="date" id="client_birth" name="client_birth" 
                               value="<?php echo esc_attr( $meta['birth'] ?? '' ); ?>">
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
                               placeholder="(00) 00000-0000" required>
                    </div>
                    
                    <!-- Email -->
                    <div class="dps-profile-field">
                        <label for="client_email">
                            <?php echo esc_html__( 'Email', 'dps-client-portal' ); ?>
                        </label>
                        <input type="email" id="client_email" name="client_email" 
                               value="<?php echo esc_attr( $meta['email'] ?? '' ); ?>" 
                               placeholder="seu@email.com">
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
    
    // Adicionar novo pet
    if (addPetBtn && newPetsContainer && template) {
        addPetBtn.addEventListener('click', function() {
            var html = template.innerHTML.replace(/\{\{INDEX\}\}/g, newPetIndex);
            var div = document.createElement('div');
            div.innerHTML = html;
            newPetsContainer.appendChild(div.firstElementChild);
            newPetIndex++;
            
            // Atualiza contador
            updatePetCount();
        });
    }
    
    // Remover novo pet
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-pet-card__remove')) {
            var card = e.target.closest('.dps-pet-card');
            if (card) {
                card.remove();
                updatePetCount();
            }
        }
    });
    
    // Toggle de cards de pets existentes
    document.addEventListener('click', function(e) {
        if (e.target.closest('.dps-pet-card__toggle')) {
            var btn = e.target.closest('.dps-pet-card__toggle');
            var card = btn.closest('.dps-pet-card');
            var body = card.querySelector('.dps-pet-card__body');
            
            if (body) {
                var isExpanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !isExpanded);
                body.style.display = isExpanded ? 'none' : 'block';
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
})();
</script>

<style>
/* Estilos do formulário de atualização de perfil */
.dps-profile-update-page {
    min-height: 100vh;
    background: #f9fafb;
    padding: 20px;
}

.dps-profile-update-container {
    max-width: 800px;
    margin: 0 auto;
}

.dps-profile-update-header {
    text-align: center;
    margin-bottom: 32px;
    padding: 24px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.dps-profile-update-logo {
    font-size: 48px;
    margin-bottom: 16px;
}

.dps-profile-update-title {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 600;
    color: #374151;
}

.dps-profile-update-subtitle {
    margin: 0;
    color: #6b7280;
    font-size: 16px;
}

/* Seções */
.dps-profile-section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 20px;
    padding: 24px;
}

.dps-profile-section__title {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.dps-pet-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    background: #e5e7eb;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}

/* Grid de campos */
.dps-profile-fields {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.dps-profile-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dps-profile-field--full {
    grid-column: 1 / -1;
}

.dps-profile-field label {
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}

.dps-profile-field input,
.dps-profile-field select,
.dps-profile-field textarea {
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    color: #374151;
    background: #ffffff;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.dps-profile-field input:focus,
.dps-profile-field select:focus,
.dps-profile-field textarea:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.dps-required {
    color: #ef4444;
}

/* Checkbox */
.dps-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.dps-checkbox-text {
    font-size: 14px;
    color: #374151;
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
    border-radius: 8px;
    overflow: hidden;
}

.dps-pet-card--new {
    border-color: #0ea5e9;
    border-style: dashed;
}

.dps-pet-card__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
}

.dps-pet-card__icon {
    font-size: 24px;
}

.dps-pet-card__title {
    flex: 1;
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #374151;
}

.dps-pet-card__toggle {
    background: none;
    border: none;
    font-size: 12px;
    color: #6b7280;
    cursor: pointer;
    padding: 4px 8px;
}

.dps-pet-card__remove {
    background: #fee2e2;
    border: none;
    color: #ef4444;
    font-size: 14px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
}

.dps-pet-card__remove:hover {
    background: #fecaca;
}

.dps-pet-card__body {
    padding: 16px;
}

/* Botão adicionar pet */
.dps-add-pet-section {
    margin-top: 16px;
    text-align: center;
}

.dps-btn-add-pet {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #ffffff;
    border: 2px dashed #0ea5e9;
    border-radius: 8px;
    color: #0ea5e9;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
}

.dps-btn-add-pet:hover {
    background: #0ea5e9;
    color: #ffffff;
}

.dps-new-pets-list {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.dps-no-pets-message {
    text-align: center;
    color: #6b7280;
    font-style: italic;
    padding: 20px;
}

/* Botão de envio */
.dps-profile-submit {
    text-align: center;
    margin-top: 24px;
}

.dps-btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 16px 48px;
    background: #10b981;
    border: none;
    border-radius: 8px;
    color: #ffffff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.dps-btn-submit:hover {
    background: #059669;
}

/* Rodapé */
.dps-profile-update-footer {
    text-align: center;
    margin-top: 32px;
    padding: 16px;
    color: #9ca3af;
    font-size: 13px;
}

/* Alertas */
.dps-alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.dps-alert--success {
    background: #d1fae5;
    border: 1px solid #10b981;
    color: #065f46;
}

.dps-alert--error {
    background: #fee2e2;
    border: 1px solid #ef4444;
    color: #991b1b;
}

/* Responsivo */
@media (max-width: 640px) {
    .dps-profile-fields {
        grid-template-columns: 1fr;
    }
    
    .dps-profile-update-page {
        padding: 12px;
    }
    
    .dps-profile-section {
        padding: 16px;
    }
    
    .dps-btn-submit {
        width: 100%;
        justify-content: center;
    }
}
</style>
