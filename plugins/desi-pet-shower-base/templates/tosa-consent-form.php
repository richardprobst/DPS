<?php
/**
 * Template: Consentimento Permanente de Tosa com Máquina
 *
 * Variáveis disponíveis:
 * @var WP_Post $client
 * @var array   $pets
 * @var array   $consent_status
 * @var string  $token
 * @var int     $client_id
 *
 * @package DesiPetShower
 * @since 1.1.1
 * @updated 1.2.0 - Melhorias de UX/UI e informações adicionais
 * @updated 1.2.4 - Adição de campos de riscos específicos e contato de emergência
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$client_id    = isset( $client_id ) ? (int) $client_id : ( $client ? $client->ID : 0 );
$client_name  = $client ? $client->post_title : '';
$client_phone = $client_id ? get_post_meta( $client_id, 'client_phone', true ) : '';
$client_email = $client_id ? get_post_meta( $client_id, 'client_email', true ) : '';
$client_cpf   = $client_id ? get_post_meta( $client_id, 'client_cpf', true ) : '';
$site_name    = get_bloginfo( 'name' );
$is_granted   = isset( $consent_status['status'] ) && 'granted' === $consent_status['status'];
$current_date = date_i18n( 'd/m/Y' );

// Helper function para traduzir espécie
$get_species_label = function( $species ) {
    $labels = [
        'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
        'gato'  => __( 'Gato', 'desi-pet-shower' ),
        'outro' => __( 'Outro', 'desi-pet-shower' ),
    ];
    return $labels[ $species ] ?? ucfirst( $species );
};

// Helper function para emoji de espécie
$get_species_emoji = function( $species ) {
    $emojis = [
        'cao'   => '🐕',
        'gato'  => '🐱',
        'outro' => '🐾',
    ];
    return $emojis[ $species ] ?? '🐾';
};

// Helper function para traduzir porte
$get_size_label = function( $size ) {
    $labels = [
        'pequeno' => __( 'Pequeno', 'desi-pet-shower' ),
        'medio'   => __( 'Médio', 'desi-pet-shower' ),
        'grande'  => __( 'Grande', 'desi-pet-shower' ),
        'gigante' => __( 'Gigante', 'desi-pet-shower' ),
    ];
    return $labels[ $size ] ?? ucfirst( $size );
};

// Helper function para traduzir tipo de pelagem
$get_coat_label = function( $coat ) {
    $labels = [
        'curta'  => __( 'Curta', 'desi-pet-shower' ),
        'media'  => __( 'Média', 'desi-pet-shower' ),
        'longa'  => __( 'Longa', 'desi-pet-shower' ),
        'dupla'  => __( 'Dupla', 'desi-pet-shower' ),
        'lisa'   => __( 'Lisa', 'desi-pet-shower' ),
        'crespa' => __( 'Crespa/Encaracolada', 'desi-pet-shower' ),
    ];
    return $labels[ $coat ] ?? ucfirst( $coat );
};

// Helper function para calcular idade do pet
$get_pet_age = function( $birth_date ) {
    if ( empty( $birth_date ) ) {
        return null;
    }
    $birth = strtotime( $birth_date );
    if ( ! $birth ) {
        return null;
    }
    $diff = time() - $birth;
    return floor( $diff / YEAR_IN_SECONDS );
};

// Helper function para verificar se é pet idoso (7+ anos para cães, 10+ para gatos)
$is_senior_pet = function( $species, $birth_date ) use ( $get_pet_age ) {
    $age = $get_pet_age( $birth_date );
    if ( null === $age ) {
        return false;
    }
    // Cães: 7+ anos é considerado idoso
    // Gatos: 10+ anos é considerado idoso
    $senior_threshold = ( 'gato' === strtolower( $species ) ) ? 10 : 7;
    return $age >= $senior_threshold;
};

// Verificar se há algum pet idoso ou com observações de saúde
$has_senior_pets   = false;
$has_health_notes  = false;
$has_aggressive    = false;
$has_double_coat   = false;

if ( ! empty( $pets ) ) {
    foreach ( $pets as $pet ) {
        $pet_species = get_post_meta( $pet->ID, 'pet_species', true );
        $pet_birth   = get_post_meta( $pet->ID, 'pet_birth', true );
        $pet_care    = get_post_meta( $pet->ID, 'pet_care', true );
        $pet_coat    = get_post_meta( $pet->ID, 'pet_coat', true );
        $pet_aggro   = get_post_meta( $pet->ID, 'pet_aggressive', true );
        
        if ( $is_senior_pet( $pet_species, $pet_birth ) ) {
            $has_senior_pets = true;
        }
        if ( ! empty( $pet_care ) ) {
            $has_health_notes = true;
        }
        if ( $pet_aggro ) {
            $has_aggressive = true;
        }
        if ( in_array( strtolower( $pet_coat ), [ 'dupla', 'double', 'duplo' ], true ) ) {
            $has_double_coat = true;
        }
    }
}

// Obter telefone da loja/estabelecimento para emergências
$store_phone = get_option( 'dps_store_phone', '' );
$store_emergency_vet = get_option( 'dps_emergency_vet_contact', '' );
?>

<div class="dps-consent-page" role="main">
    <div class="dps-consent-container">
        <header class="dps-consent-header">
            <div class="dps-consent-header__icon" aria-hidden="true">✂️</div>
            <h1 class="dps-consent-title"><?php echo esc_html__( 'Consentimento Permanente • Tosa na Máquina', 'desi-pet-shower' ); ?></h1>
            <p class="dps-consent-subtitle">
                <?php
                printf(
                    /* translators: %s: nome do cliente */
                    esc_html__( 'Olá, %s! Precisamos do seu aceite para continuar.', 'desi-pet-shower' ),
                    esc_html( $client_name )
                );
                ?>
            </p>
            <div class="dps-consent-permanent-notice">
                <span class="dps-consent-permanent-icon" aria-hidden="true">🔒</span>
                <span><?php echo esc_html__( 'Este é um consentimento permanente válido para todos os atendimentos futuros.', 'desi-pet-shower' ); ?></span>
            </div>
        </header>

        <?php
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DPS_Message_Helper
            echo DPS_Message_Helper::display_messages();
        }
        ?>

        <?php if ( $is_granted ) : ?>
            <div class="dps-alert dps-alert--success" role="status">
                <?php
                printf(
                    /* translators: %s: data */
                    esc_html__( 'Consentimento já registrado em %s. Caso deseje atualizar os dados, reenvie o formulário.', 'desi-pet-shower' ),
                    esc_html( $consent_status['granted_at'] ?? '' )
                );
                ?>
            </div>
        <?php endif; ?>

        <form method="post" class="dps-consent-form" aria-label="<?php echo esc_attr__( 'Formulário de consentimento', 'desi-pet-shower' ); ?>">
            <input type="hidden" name="dps_tosa_consent_token" value="<?php echo esc_attr( $token ); ?>">
            <input type="hidden" name="dps_tosa_consent_client_id" value="<?php echo esc_attr( $client_id ); ?>">
            <?php wp_nonce_field( 'dps_tosa_consent_' . $client_id, 'dps_tosa_consent_nonce' ); ?>

            <section class="dps-consent-card" aria-labelledby="section-responsavel">
                <h2 id="section-responsavel">
                    <span class="dps-section-icon" aria-hidden="true">👤</span>
                    <?php echo esc_html__( 'Dados do Responsável', 'desi-pet-shower' ); ?>
                </h2>
                <p class="dps-consent-section-desc"><?php echo esc_html__( 'Confirme ou atualize seus dados de contato.', 'desi-pet-shower' ); ?></p>
                <div class="dps-consent-grid">
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_name"><?php echo esc_html__( 'Nome completo', 'desi-pet-shower' ); ?> <span class="dps-required" aria-hidden="true">*</span></label>
                        <input type="text" id="dps_consent_signature_name" name="dps_consent_signature_name" value="<?php echo esc_attr( $client_name ); ?>" required aria-required="true" autocomplete="name">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_document"><?php echo esc_html__( 'CPF', 'desi-pet-shower' ); ?> <span class="dps-optional"><?php echo esc_html__( '(opcional)', 'desi-pet-shower' ); ?></span></label>
                        <input type="text" id="dps_consent_signature_document" name="dps_consent_signature_document" value="<?php echo esc_attr( $client_cpf ); ?>" placeholder="000.000.000-00" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_phone"><?php echo esc_html__( 'Telefone/WhatsApp', 'desi-pet-shower' ); ?> <span class="dps-required" aria-hidden="true">*</span></label>
                        <input type="tel" id="dps_consent_signature_phone" name="dps_consent_signature_phone" value="<?php echo esc_attr( $client_phone ); ?>" placeholder="(00) 00000-0000" autocomplete="tel" required aria-required="true">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_signature_email"><?php echo esc_html__( 'E-mail', 'desi-pet-shower' ); ?> <span class="dps-optional"><?php echo esc_html__( '(opcional)', 'desi-pet-shower' ); ?></span></label>
                        <input type="email" id="dps_consent_signature_email" name="dps_consent_signature_email" value="<?php echo esc_attr( $client_email ); ?>" placeholder="seu@email.com" autocomplete="email">
                    </div>
                    <div class="dps-consent-field dps-consent-field--full">
                        <label for="dps_consent_relationship"><?php echo esc_html__( 'Relação com o(s) pet(s)', 'desi-pet-shower' ); ?></label>
                        <select id="dps_consent_relationship" name="dps_consent_relationship">
                            <option value="tutor" selected><?php echo esc_html__( 'Tutor(a) / Proprietário(a)', 'desi-pet-shower' ); ?></option>
                            <option value="responsavel"><?php echo esc_html__( 'Responsável autorizado', 'desi-pet-shower' ); ?></option>
                            <option value="familiar"><?php echo esc_html__( 'Familiar', 'desi-pet-shower' ); ?></option>
                            <option value="outro"><?php echo esc_html__( 'Outro', 'desi-pet-shower' ); ?></option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="dps-consent-card dps-consent-card--emergency" aria-labelledby="section-emergencia">
                <h2 id="section-emergencia">
                    <span class="dps-section-icon" aria-hidden="true">🏥</span>
                    <?php echo esc_html__( 'Contato de Emergência', 'desi-pet-shower' ); ?>
                </h2>
                <p class="dps-consent-section-desc"><?php echo esc_html__( 'Informe um contato veterinário para emergências durante o atendimento.', 'desi-pet-shower' ); ?></p>
                
                <div class="dps-consent-emergency-info">
                    <div class="dps-alert dps-alert--info" role="note">
                        <span aria-hidden="true">ℹ️</span>
                        <span>
                            <?php
                            echo esc_html__( 'Em caso de emergência médica durante o procedimento, utilizaremos este contato veterinário para buscar atendimento imediato. Se não houver veterinário disponível, a equipe encaminhará para o atendimento veterinário mais próximo.', 'desi-pet-shower' );
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="dps-consent-grid">
                    <div class="dps-consent-field">
                        <label for="dps_consent_vet_name"><?php echo esc_html__( 'Nome do Veterinário/Clínica', 'desi-pet-shower' ); ?> <span class="dps-optional"><?php echo esc_html__( '(opcional)', 'desi-pet-shower' ); ?></span></label>
                        <input type="text" id="dps_consent_vet_name" name="dps_consent_vet_name" placeholder="<?php echo esc_attr__( 'Ex: Dr. João / Clínica Pet Care', 'desi-pet-shower' ); ?>" autocomplete="off">
                    </div>
                    <div class="dps-consent-field">
                        <label for="dps_consent_vet_phone"><?php echo esc_html__( 'Telefone do Veterinário', 'desi-pet-shower' ); ?> <span class="dps-optional"><?php echo esc_html__( '(opcional)', 'desi-pet-shower' ); ?></span></label>
                        <input type="tel" id="dps_consent_vet_phone" name="dps_consent_vet_phone" placeholder="(00) 00000-0000" autocomplete="tel">
                    </div>
                    <div class="dps-consent-field dps-consent-field--full">
                        <label class="dps-consent-check dps-consent-check--inline">
                            <input type="checkbox" name="dps_consent_use_store_vet" value="1">
                            <span><?php echo esc_html__( 'Autorizo a equipe a utilizar o veterinário de referência do estabelecimento, caso eu não tenha um contato disponível.', 'desi-pet-shower' ); ?></span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="dps-consent-card" aria-labelledby="section-pets">
                <h2 id="section-pets">
                    <span class="dps-section-icon" aria-hidden="true">🐾</span>
                    <?php echo esc_html__( 'Pets vinculados ao consentimento', 'desi-pet-shower' ); ?>
                </h2>
                <p class="dps-consent-section-desc"><?php echo esc_html__( 'Este consentimento será válido para todos os pets listados abaixo.', 'desi-pet-shower' ); ?></p>
                <?php if ( ! empty( $pets ) ) : ?>
                    <div class="dps-consent-pets-grid" role="list">
                        <?php foreach ( $pets as $pet ) : 
                            $pet_species    = get_post_meta( $pet->ID, 'pet_species', true );
                            $pet_breed      = get_post_meta( $pet->ID, 'pet_breed', true );
                            $pet_size       = get_post_meta( $pet->ID, 'pet_size', true );
                            $pet_coat       = get_post_meta( $pet->ID, 'pet_coat', true );
                            $pet_weight     = get_post_meta( $pet->ID, 'pet_weight', true );
                            $pet_birth      = get_post_meta( $pet->ID, 'pet_birth', true );
                            $pet_care       = get_post_meta( $pet->ID, 'pet_care', true );
                            $pet_aggressive = get_post_meta( $pet->ID, 'pet_aggressive', true );
                            $is_double_coat = in_array( strtolower( $pet_coat ), [ 'dupla', 'double', 'duplo' ], true );
                            $is_senior      = $is_senior_pet( $pet_species, $pet_birth );
                            $pet_age        = $get_pet_age( $pet_birth );
                        ?>
                            <div class="dps-consent-pet-card <?php echo $is_senior ? 'dps-consent-pet-card--senior' : ''; ?>" role="listitem">
                                <div class="dps-consent-pet-header">
                                    <span class="dps-consent-pet-emoji" aria-hidden="true"><?php echo esc_html( $get_species_emoji( $pet_species ) ); ?></span>
                                    <strong class="dps-consent-pet-name"><?php echo esc_html( $pet->post_title ); ?></strong>
                                    <?php if ( $is_senior ) : ?>
                                        <span class="dps-consent-pet-badge dps-consent-pet-badge--senior" title="<?php echo esc_attr__( 'Pet idoso - cuidados especiais', 'desi-pet-shower' ); ?>">
                                            <?php echo esc_html__( 'Idoso', 'desi-pet-shower' ); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ( $pet_aggressive ) : ?>
                                        <span class="dps-consent-pet-badge dps-consent-pet-badge--aggressive" title="<?php echo esc_attr__( 'Possui histórico de agitação/agressividade', 'desi-pet-shower' ); ?>">
                                            <?php echo esc_html__( 'Atenção', 'desi-pet-shower' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="dps-consent-pet-details">
                                    <?php if ( $pet_species ) : ?>
                                        <span class="dps-consent-pet-tag"><?php echo esc_html( $get_species_label( $pet_species ) ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $pet_breed ) : ?>
                                        <span class="dps-consent-pet-tag"><?php echo esc_html( $pet_breed ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( null !== $pet_age ) : ?>
                                        <span class="dps-consent-pet-tag <?php echo $is_senior ? 'dps-consent-pet-tag--warning' : ''; ?>">
                                            <?php
                                            printf(
                                                /* translators: %d: idade em anos */
                                                esc_html( _n( '%d ano', '%d anos', $pet_age, 'desi-pet-shower' ) ),
                                                $pet_age
                                            );
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ( $pet_size ) : ?>
                                        <span class="dps-consent-pet-tag"><?php echo esc_html__( 'Porte:', 'desi-pet-shower' ); ?> <?php echo esc_html( $get_size_label( $pet_size ) ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $pet_coat ) : ?>
                                        <span class="dps-consent-pet-tag <?php echo $is_double_coat ? 'dps-consent-pet-tag--warning' : ''; ?>">
                                            <?php echo esc_html__( 'Pelagem:', 'desi-pet-shower' ); ?> <?php echo esc_html( $get_coat_label( $pet_coat ) ); ?>
                                            <?php if ( $is_double_coat ) : ?>
                                                <span class="dps-tooltip" title="<?php echo esc_attr__( 'Pelagem dupla requer cuidados especiais na tosa', 'desi-pet-shower' ); ?>">⚠️</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ( $pet_weight ) : ?>
                                        <span class="dps-consent-pet-tag"><?php echo esc_html( $pet_weight ); ?> kg</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ( ! empty( $pet_care ) ) : ?>
                                    <div class="dps-consent-pet-notes">
                                        <span class="dps-consent-pet-notes__icon" aria-hidden="true">📋</span>
                                        <span class="dps-consent-pet-notes__text"><?php echo esc_html( $pet_care ); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="dps-consent-pets-count">
                        <?php
                        printf(
                            /* translators: %d: número de pets */
                            esc_html( _n( 'Total: %d pet cadastrado', 'Total: %d pets cadastrados', count( $pets ), 'desi-pet-shower' ) ),
                            count( $pets )
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <div class="dps-alert dps-alert--warning">
                        <span aria-hidden="true">⚠️</span>
                        <?php echo esc_html__( 'Nenhum pet cadastrado para este cliente. Por favor, cadastre seus pets antes de assinar o consentimento.', 'desi-pet-shower' ); ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dps-consent-card dps-consent-card--important" aria-labelledby="section-termos">
                <h2 id="section-termos">
                    <span class="dps-section-icon" aria-hidden="true">📋</span>
                    <?php echo esc_html__( 'Autorização e Termos de Ciência', 'desi-pet-shower' ); ?>
                </h2>
                
                <div class="dps-consent-terms-intro">
                    <p><?php echo esc_html__( 'Para prosseguir com o serviço de tosa com máquina, você precisa autorizar expressamente o procedimento e declarar ciência dos riscos associados.', 'desi-pet-shower' ); ?></p>
                </div>

                <!-- Autorização do Procedimento -->
                <div class="dps-consent-authorization-section">
                    <h3 class="dps-consent-terms-title">
                        <span aria-hidden="true">✅</span>
                        <?php echo esc_html__( 'Autorização do Procedimento', 'desi-pet-shower' ); ?>
                    </h3>
                    <div class="dps-consent-check-box">
                        <label class="dps-consent-check dps-consent-check--important">
                            <input type="checkbox" name="dps_consent_authorize_procedure" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Autorizo expressamente', 'desi-pet-shower' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %s: nome do estabelecimento */
                                    esc_html__( 'a equipe do %s a utilizar máquina de tosa nos meus pets quando necessário para o bem-estar do animal e realização do serviço contratado.', 'desi-pet-shower' ),
                                    '<strong>' . esc_html( $site_name ) . '</strong>'
                                );
                                ?>
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Riscos Associados (Termo de Ciência) -->
                <div class="dps-consent-risks-section">
                    <h3 class="dps-consent-terms-title">
                        <span aria-hidden="true">⚠️</span>
                        <?php echo esc_html__( 'Riscos Associados (Termo de Ciência)', 'desi-pet-shower' ); ?>
                    </h3>
                    <p class="dps-consent-risks-intro"><?php echo esc_html__( 'Marque cada item abaixo para confirmar que você está ciente dos possíveis riscos:', 'desi-pet-shower' ); ?></p>
                    
                    <!-- 1. Pele Sensível -->
                    <div class="dps-consent-risk-item">
                        <label class="dps-consent-check">
                            <input type="checkbox" name="dps_consent_risk_skin" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Pele Sensível:', 'desi-pet-shower' ); ?></strong>
                                <?php echo esc_html__( 'Declaro estar ciente de que a máquina de tosa pode causar possíveis irritações, vermelhidão ou pequenos cortes superficiais (processo irritativo da máquina), especialmente em pets com pele sensível. Essas reações são temporárias e fazem parte do processo natural da tosa.', 'desi-pet-shower' ); ?>
                            </span>
                        </label>
                    </div>
                    
                    <!-- 2. Nós e Pelagem Embaraçada -->
                    <div class="dps-consent-risk-item">
                        <label class="dps-consent-check">
                            <input type="checkbox" name="dps_consent_risk_knots" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Nós e Pelagem Embaraçada:', 'desi-pet-shower' ); ?></strong>
                                <?php echo esc_html__( 'Autorizo a remoção de nós e pelagem embaraçada, estando ciente de que, devido à proximidade dos nós com a pele, a tosa poderá ficar mais baixa que o desejado inicialmente. Entendo que essa medida é necessária para o bem-estar e conforto do pet.', 'desi-pet-shower' ); ?>
                            </span>
                        </label>
                    </div>
                    
                    <!-- 3. Comportamento do Pet -->
                    <div class="dps-consent-risk-item">
                        <label class="dps-consent-check">
                            <input type="checkbox" name="dps_consent_risk_behavior" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Comportamento do Pet:', 'desi-pet-shower' ); ?></strong>
                                <?php echo esc_html__( 'Estou ciente de que o estabelecimento não se responsabiliza por pequenos acidentes (arranhões, mordidas na máquina, cortes superficiais) causados por movimentação excessiva ou comportamento agressivo do pet durante o procedimento. Caso o animal apresente estresse extremo, o serviço poderá ser interrompido.', 'desi-pet-shower' ); ?>
                            </span>
                        </label>
                    </div>
                    
                    <!-- 4. Pets Idosos ou com Problemas de Saúde -->
                    <div class="dps-consent-risk-item <?php echo ( $has_senior_pets || $has_health_notes ) ? 'dps-consent-risk-item--highlighted' : ''; ?>">
                        <label class="dps-consent-check">
                            <input type="checkbox" name="dps_consent_risk_health" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Pets Idosos e/ou com Problemas de Saúde:', 'desi-pet-shower' ); ?></strong>
                                <?php echo esc_html__( 'Declaro que estou ciente de que pets idosos ou com condições médicas conhecidas podem apresentar maior sensibilidade ou estresse durante o procedimento de tosa. Reconheço que o serviço será realizado com todo cuidado possível, mas que o próprio procedimento pode gerar estresse ou desconforto ao animal.', 'desi-pet-shower' ); ?>
                                <?php if ( $has_senior_pets ) : ?>
                                    <span class="dps-consent-risk-note">
                                        <em><?php echo esc_html__( '(Um ou mais pets cadastrados são considerados idosos)', 'desi-pet-shower' ); ?></em>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </label>
                    </div>
                    
                    <!-- 5. Pelagem Dupla (se aplicável) -->
                    <?php if ( $has_double_coat ) : ?>
                    <div class="dps-consent-risk-item dps-consent-risk-item--highlighted">
                        <label class="dps-consent-check">
                            <input type="checkbox" name="dps_consent_risk_double_coat" value="1" required aria-required="true">
                            <span>
                                <strong><?php echo esc_html__( 'Pelagem Dupla:', 'desi-pet-shower' ); ?></strong>
                                <?php echo esc_html__( 'Estou ciente de que um ou mais dos meus pets possui pelagem dupla (subpelo denso) e que a tosa pode afetar permanentemente a textura, cor e padrão de crescimento do pelo. Entendo que a pelagem pode não voltar ao estado original após a tosa.', 'desi-pet-shower' ); ?>
                            </span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Informações Adicionais -->
                <div class="dps-consent-terms-section">
                    <h3 class="dps-consent-terms-title"><?php echo esc_html__( 'Cuidados Pós-Tosa', 'desi-pet-shower' ); ?></h3>
                    <ul class="dps-consent-list">
                        <li><?php echo esc_html__( 'É normal o pet coçar-se mais nas primeiras horas após a tosa. Se a coceira persistir por mais de 24 horas, entre em contato conosco.', 'desi-pet-shower' ); ?></li>
                        <li><?php echo esc_html__( 'Evite exposição prolongada ao sol nos primeiros dias após uma tosa curta para proteger a pele do pet.', 'desi-pet-shower' ); ?></li>
                    </ul>
                </div>
                
                <div class="dps-consent-terms-section">
                    <h3 class="dps-consent-terms-title"><?php echo esc_html__( 'Emergências Médicas', 'desi-pet-shower' ); ?></h3>
                    <ul class="dps-consent-list">
                        <li>
                            <?php
                            printf(
                                /* translators: %s: nome do estabelecimento */
                                esc_html__( 'Em caso de emergência médica durante o atendimento, autorizo a equipe do %s a buscar atendimento veterinário de urgência, utilizando preferencialmente o contato informado no início deste formulário. Os custos do atendimento veterinário ficam sob minha responsabilidade.', 'desi-pet-shower' ),
                                esc_html( $site_name )
                            );
                            ?>
                        </li>
                    </ul>
                </div>
                
                <div class="dps-consent-terms-section">
                    <h3 class="dps-consent-terms-title"><?php echo esc_html__( 'Validade e Revogação', 'desi-pet-shower' ); ?></h3>
                    <ul class="dps-consent-list">
                        <li><?php echo esc_html__( 'Este consentimento é permanente e válido para todos os atendimentos futuros até que seja revogado.', 'desi-pet-shower' ); ?></li>
                        <li><?php echo esc_html__( 'Você pode revogar este consentimento a qualquer momento, solicitando ao estabelecimento por telefone, e-mail ou presencialmente.', 'desi-pet-shower' ); ?></li>
                        <li><?php echo esc_html__( 'A revogação não afeta a validade do consentimento para procedimentos já realizados.', 'desi-pet-shower' ); ?></li>
                    </ul>
                </div>
                
                <!-- Aceite Final -->
                <div class="dps-consent-accept-box">
                    <label class="dps-consent-check">
                        <input type="checkbox" name="dps_tosa_consent_accept" value="1" required aria-required="true">
                        <span><?php echo esc_html__( 'Li, compreendi e aceito TODOS os termos e condições acima descritos, confirmando estar ciente dos riscos e autorizando o procedimento.', 'desi-pet-shower' ); ?></span>
                    </label>
                </div>
            </section>

            <section class="dps-consent-card dps-consent-card--signature" aria-labelledby="section-assinatura">
                <h2 id="section-assinatura">
                    <span class="dps-section-icon" aria-hidden="true">✍️</span>
                    <?php echo esc_html__( 'Confirmação e Assinatura Digital', 'desi-pet-shower' ); ?>
                </h2>
                <div class="dps-consent-signature-content">
                    <p class="dps-consent-signature-text">
                        <?php
                        printf(
                            /* translators: %s: nome do estabelecimento */
                            esc_html__( 'Eu, abaixo identificado(a), confirmo que autorizo a equipe do %s a realizar a tosa com máquina quando necessário para o bem-estar dos meus pets.', 'desi-pet-shower' ),
                            '<strong>' . esc_html( $site_name ) . '</strong>'
                        );
                        ?>
                    </p>
                    <div class="dps-consent-signature-box">
                        <div class="dps-consent-signature-info">
                            <div class="dps-consent-signature-row">
                                <span class="dps-consent-signature-label"><?php echo esc_html__( 'Assinante:', 'desi-pet-shower' ); ?></span>
                                <span class="dps-consent-signature-value"><?php echo esc_html( $client_name ); ?></span>
                            </div>
                            <div class="dps-consent-signature-row">
                                <span class="dps-consent-signature-label"><?php echo esc_html__( 'Data:', 'desi-pet-shower' ); ?></span>
                                <span class="dps-consent-signature-value"><?php echo esc_html( $current_date ); ?></span>
                            </div>
                            <div class="dps-consent-signature-row">
                                <span class="dps-consent-signature-label"><?php echo esc_html__( 'Estabelecimento:', 'desi-pet-shower' ); ?></span>
                                <span class="dps-consent-signature-value"><?php echo esc_html( $site_name ); ?></span>
                            </div>
                        </div>
                        <div class="dps-consent-signature-line">
                            <span class="dps-consent-signature-name"><?php echo esc_html( $client_name ); ?></span>
                            <span class="dps-consent-signature-caption"><?php echo esc_html__( 'Assinatura eletrônica', 'desi-pet-shower' ); ?></span>
                        </div>
                    </div>
                    <p class="dps-consent-legal-note">
                        <span aria-hidden="true">ℹ️</span>
                        <?php echo esc_html__( 'Ao clicar em "Registrar consentimento", você confirma que leu e concorda com todos os termos acima. Esta assinatura digital tem validade legal conforme Lei nº 14.063/2020.', 'desi-pet-shower' ); ?>
                    </p>
                </div>
            </section>

            <div class="dps-consent-submit-wrapper">
                <button type="submit" class="dps-btn dps-btn--primary dps-consent-submit">
                    <span aria-hidden="true">✅</span> <?php echo esc_html__( 'Registrar Consentimento Permanente', 'desi-pet-shower' ); ?>
                </button>
                <p class="dps-consent-submit-note">
                    <?php echo esc_html__( 'Você receberá uma confirmação por e-mail após o registro.', 'desi-pet-shower' ); ?>
                </p>
            </div>
        </form>
        
        <footer class="dps-consent-footer">
            <p>
                <?php
                printf(
                    /* translators: %s: nome do estabelecimento */
                    esc_html__( '© %s — Documento gerado eletronicamente. Em caso de dúvidas, entre em contato conosco.', 'desi-pet-shower' ),
                    esc_html( $site_name )
                );
                ?>
            </p>
        </footer>
    </div>
</div>
