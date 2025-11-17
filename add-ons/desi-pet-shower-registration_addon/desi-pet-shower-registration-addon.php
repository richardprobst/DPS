<?php
/**
 * Plugin Name:       Desi Pet Shower – Cadastro Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Permite criar uma página pública para cadastro de clientes e seus pets. Ideal para enviar ao cliente e deixar que ele mesmo preencha seus dados antes do primeiro atendimento.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-registration-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Registration_Addon {

    public function __construct() {
        // Processa o envio do formulário
        add_action( 'init', [ $this, 'maybe_handle_registration' ] );
        // Confirmação de email
        add_action( 'init', [ $this, 'maybe_handle_email_confirmation' ] );
        // Shortcode para exibir o formulário
        add_shortcode( 'dps_registration_form', [ $this, 'render_registration_form' ] );
        // Cria a página automaticamente ao ativar
        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        // Adiciona página de configurações para API do Google Maps
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Executado na ativação do plugin. Cria a página de cadastro, se ainda não existir, contendo o shortcode.
     */
    public function activate() {
        $title = __( 'Cadastro de Clientes e Pets', 'dps-registration-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_registration_form]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_registration_page_id', $page_id );
            }
        } else {
            update_option( 'dps_registration_page_id', $page->ID );
        }
    }

    /**
     * Adiciona a página de configurações no menu Configurações
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Configurações de Cadastro Desi Pet Shower', 'dps-registration-addon' ),
            __( 'DPS Cadastro', 'dps-registration-addon' ),
            'manage_options',
            'dps-registration-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registra as configurações utilizadas pelo plugin
     */
    public function register_settings() {
        register_setting( 'dps_registration_settings', 'dps_google_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
    }

    /**
     * Renderiza o conteúdo da página de configurações
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Configurações de Cadastro Desi Pet Shower', 'dps-registration-addon' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'dps_registration_settings' );
        do_settings_sections( 'dps_registration_settings' );
        $api_key = get_option( 'dps_google_api_key', '' );
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="dps_google_api_key">' . esc_html__( 'Google Maps API Key', 'dps-registration-addon' ) . '</label></th>';
        echo '<td><input type="text" id="dps_google_api_key" name="dps_google_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text"></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Processa o formulário de cadastro quando enviado via POST. Cria um novo cliente e um ou mais pets
     * associados. Após o processamento, define uma mensagem de sucesso para ser exibida.
     */
    public function maybe_handle_registration() {
        if ( isset( $_POST['dps_reg_action'] ) && 'save_registration' === $_POST['dps_reg_action'] ) {
            // Verifica nonce e honeypot
            if ( ! isset( $_POST['dps_reg_nonce'] ) || ! check_admin_referer( 'dps_reg_action', 'dps_reg_nonce' ) ) {
                return;
            }

            // Honeypot para bots
            if ( ! empty( $_POST['dps_hp_field'] ) ) {
                return;
            }

            // Hook para validações adicionais (ex.: reCAPTCHA)
            $spam_check = apply_filters( 'dps_registration_spam_check', true, $_POST );
            if ( true !== $spam_check ) {
                return;
            }
            // Sanitiza dados do cliente
            $client_name     = sanitize_text_field( $_POST['client_name'] ?? '' );
            $client_cpf      = sanitize_text_field( $_POST['client_cpf'] ?? '' );
            $client_phone    = sanitize_text_field( $_POST['client_phone'] ?? '' );
            $client_email    = sanitize_email( $_POST['client_email'] ?? '' );
            $client_birth    = sanitize_text_field( $_POST['client_birth'] ?? '' );
            $client_instagram = sanitize_text_field( $_POST['client_instagram'] ?? '' );
            $client_facebook = sanitize_text_field( $_POST['client_facebook'] ?? '' );
            $client_photo_auth = isset( $_POST['client_photo_auth'] ) ? 1 : 0;
            $client_address  = sanitize_textarea_field( $_POST['client_address'] ?? '' );
            $client_referral = sanitize_text_field( $_POST['client_referral'] ?? '' );
            $referral_code   = sanitize_text_field( $_POST['dps_referral_code'] ?? '' );

            // Coordenadas de latitude e longitude (podem estar vazias)
            $client_lat  = sanitize_text_field( $_POST['client_lat'] ?? '' );
            $client_lng  = sanitize_text_field( $_POST['client_lng'] ?? '' );
            if ( ! $client_name ) {
                return;
            }
            // Cria cliente
            $client_id = wp_insert_post( [
                'post_type'   => 'dps_cliente',
                'post_title'  => $client_name,
                'post_status' => 'publish',
            ] );
            if ( $client_id ) {
                update_post_meta( $client_id, 'client_cpf', $client_cpf );
                update_post_meta( $client_id, 'client_phone', $client_phone );
                update_post_meta( $client_id, 'client_email', $client_email );
                update_post_meta( $client_id, 'client_birth', $client_birth );
                update_post_meta( $client_id, 'client_instagram', $client_instagram );
                update_post_meta( $client_id, 'client_facebook', $client_facebook );
                update_post_meta( $client_id, 'client_photo_auth', $client_photo_auth );
                update_post_meta( $client_id, 'client_address', $client_address );
                update_post_meta( $client_id, 'client_referral', $client_referral );
                update_post_meta( $client_id, 'dps_email_confirmed', 0 );
                update_post_meta( $client_id, 'dps_is_active', 0 );
                // Salva coordenadas se fornecidas
                if ( $client_lat !== '' && $client_lng !== '' ) {
                    update_post_meta( $client_id, 'client_lat', $client_lat );
                    update_post_meta( $client_id, 'client_lng', $client_lng );
                }

                if ( $client_email ) {
                    $this->send_confirmation_email( $client_id, $client_email );
                }

                do_action( 'dps_registration_after_client_created', $referral_code, $client_id, $client_email, $client_phone );
            }
            // Lê pets submetidos (campos em arrays)
            $pet_names      = $_POST['pet_name'] ?? [];
            $pet_species    = $_POST['pet_species'] ?? [];
            $pet_breeds     = $_POST['pet_breed'] ?? [];
            $pet_sizes      = $_POST['pet_size'] ?? [];
            $pet_weights    = $_POST['pet_weight'] ?? [];
            $pet_coats      = $_POST['pet_coat'] ?? [];
            $pet_colors     = $_POST['pet_color'] ?? [];
            $pet_births     = $_POST['pet_birth'] ?? [];
            $pet_sexes      = $_POST['pet_sex'] ?? [];
            $pet_cares      = $_POST['pet_care'] ?? [];
            $pet_aggs       = $_POST['pet_aggressive'] ?? [];
            if ( is_array( $pet_names ) ) {
                foreach ( $pet_names as $index => $pname ) {
                    $pname = sanitize_text_field( $pname );
                    if ( ! $pname ) {
                        continue;
                    }
                    // Coleta campos do pet
                    $species  = is_array( $pet_species ) && isset( $pet_species[ $index ] ) ? sanitize_text_field( $pet_species[ $index ] ) : '';
                    $breed    = is_array( $pet_breeds )  && isset( $pet_breeds[ $index ] )  ? sanitize_text_field( $pet_breeds[ $index ] )  : '';
                    $size     = is_array( $pet_sizes )   && isset( $pet_sizes[ $index ] )   ? sanitize_text_field( $pet_sizes[ $index ] )   : '';
                    $weight   = is_array( $pet_weights ) && isset( $pet_weights[ $index ] ) ? sanitize_text_field( $pet_weights[ $index ] ) : '';
                    $coat     = is_array( $pet_coats )   && isset( $pet_coats[ $index ] )   ? sanitize_text_field( $pet_coats[ $index ] )   : '';
                    $color    = is_array( $pet_colors )  && isset( $pet_colors[ $index ] )  ? sanitize_text_field( $pet_colors[ $index ] )  : '';
                    $birth    = is_array( $pet_births )  && isset( $pet_births[ $index ] )  ? sanitize_text_field( $pet_births[ $index ] )  : '';
                    $sex      = is_array( $pet_sexes )   && isset( $pet_sexes[ $index ] )   ? sanitize_text_field( $pet_sexes[ $index ] )   : '';
                    $care     = is_array( $pet_cares )   && isset( $pet_cares[ $index ] )   ? sanitize_textarea_field( $pet_cares[ $index ] )   : '';
                    $agg      = is_array( $pet_aggs )    && isset( $pet_aggs[ $index ] )    ? 1 : 0;
                    // Cria pet
                    $pet_id = wp_insert_post( [
                        'post_type'   => 'dps_pet',
                        'post_title'  => $pname,
                        'post_status' => 'publish',
                    ] );
                    if ( $pet_id ) {
                        update_post_meta( $pet_id, 'owner_id', $client_id );
                        update_post_meta( $pet_id, 'pet_species', $species );
                        update_post_meta( $pet_id, 'pet_breed', $breed );
                        update_post_meta( $pet_id, 'pet_size', $size );
                        update_post_meta( $pet_id, 'pet_weight', $weight );
                        update_post_meta( $pet_id, 'pet_coat', $coat );
                        update_post_meta( $pet_id, 'pet_color', $color );
                        update_post_meta( $pet_id, 'pet_birth', $birth );
                        update_post_meta( $pet_id, 'pet_sex', $sex );
                        update_post_meta( $pet_id, 'pet_care', $care );
                        update_post_meta( $pet_id, 'pet_aggressive', $agg );
                    }
                }
            }
            // Redireciona e indica sucesso
            wp_redirect( add_query_arg( 'registered', '1', $this->get_registration_page_url() ) );
            exit;
        }
    }

    /**
     * Processa a confirmação de email via token presente na URL.
     */
    public function maybe_handle_email_confirmation() {
        if ( empty( $_GET['dps_confirm_email'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['dps_confirm_email'] ) );
        $client = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'dps_email_confirm_token',
                    'value' => $token,
                ],
            ],
        ] );

        if ( empty( $client ) ) {
            return;
        }

        $client_id = absint( $client[0] );
        update_post_meta( $client_id, 'dps_email_confirmed', 1 );
        update_post_meta( $client_id, 'dps_is_active', 1 );
        delete_post_meta( $client_id, 'dps_email_confirm_token' );

        $redirect = add_query_arg( 'dps_email_confirmed', '1', $this->get_registration_page_url() );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Renderiza o formulário de cadastro de cliente e pets. Mostra mensagem de sucesso se necessário.
     *
     * @return string HTML
     */
    public function render_registration_form() {
        // Inicia sessão para armazenar mensagens temporárias
        if ( ! session_id() ) {
            session_start();
        }
        $success = false;
        if ( isset( $_GET['registered'] ) && '1' === $_GET['registered'] ) {
            $success = true;
        }
        // Pré-renderiza o primeiro conjunto de campos de pet e o template de clonagem
        $first_pet_html   = $this->get_pet_fieldset_html( 1 );
        $placeholder_html = $this->get_pet_fieldset_html_placeholder();
        // Codifica o HTML do template em JSON para uso seguro em JavaScript (preserva < >)
        $placeholder_json = wp_json_encode( $placeholder_html );
        ob_start();
        echo '<div class="dps-registration-form">';
        if ( $success ) {
            echo '<p style="color:green; font-weight:bold;">' . esc_html__( 'Cadastro realizado com sucesso!', 'dps-registration-addon' ) . '</p>';
        }
        if ( isset( $_GET['dps_email_confirmed'] ) && '1' === $_GET['dps_email_confirmed'] ) {
            echo '<p style="color:green; font-weight:bold;">' . esc_html__( 'Email confirmado com sucesso! Seu cadastro está ativo.', 'desi-pet-shower' ) . '</p>';
        }
        echo '<form method="post" id="dps-reg-form">';
        echo '<input type="hidden" name="dps_reg_action" value="save_registration">';
        wp_nonce_field( 'dps_reg_action', 'dps_reg_nonce' );
        echo '<div class="dps-hp-field" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">';
        echo '<label for="dps_hp_field">' . esc_html__( 'Deixe este campo vazio', 'desi-pet-shower' ) . '</label>';
        echo '<input type="text" name="dps_hp_field" id="dps_hp_field" tabindex="-1" autocomplete="off">';
        echo '</div>';
        echo '<h4>' . esc_html__( 'Dados do Cliente', 'dps-registration-addon' ) . '</h4>';
        // Campos do cliente agrupados para melhor distribuição
        echo '<div class="dps-client-fields">';
        echo '<p><label>' . esc_html__( 'Nome', 'dps-registration-addon' ) . '<br><input type="text" name="client_name" id="dps-client-name" required></label></p>';
        echo '<p><label>CPF<br><input type="text" name="client_cpf"></label></p>';
        echo '<p><label>' . esc_html__( 'Telefone / WhatsApp', 'dps-registration-addon' ) . '<br><input type="text" name="client_phone" required></label></p>';
        echo '<p><label>Email<br><input type="email" name="client_email"></label></p>';
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="client_birth"></label></p>';
        echo '<p><label>Instagram<br><input type="text" name="client_instagram" placeholder="@usuario"></label></p>';
        echo '<p><label>Facebook<br><input type="text" name="client_facebook"></label></p>';
        echo '<p><label><input type="checkbox" name="client_photo_auth" value="1"> ' . esc_html__( 'Autorizo publicação da foto do pet nas redes sociais do Desi Pet Shower', 'dps-registration-addon' ) . '</label></p>';
        // Endereço completo com id específico para ativar autocomplete do Google
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Endereço completo', 'dps-registration-addon' ) . '<br><textarea name="client_address" id="dps-client-address" rows="2"></textarea></label></p>';
        echo '<p style="flex:1 1 100%;"><label>' . esc_html__( 'Como nos conheceu?', 'dps-registration-addon' ) . '<br><input type="text" name="client_referral"></label></p>';
        echo '</div>';
        echo '<h4>' . esc_html__( 'Dados dos Pets', 'dps-registration-addon' ) . '</h4>';
        echo '<div id="dps-pets-wrapper">';
        // Insere o primeiro conjunto de campos de pet
        echo $first_pet_html;
        echo '</div>';
        // Botão para adicionar outro pet
        echo '<p><button type="button" id="dps-add-pet" class="button">' . esc_html__( 'Adicionar outro pet', 'dps-registration-addon' ) . '</button></p>';
        // Botão de envio
        // Campos ocultos para coordenadas, preenchidos via script de autocomplete
        echo '<input type="hidden" name="client_lat" id="dps-client-lat" value="">';
        echo '<input type="hidden" name="client_lng" id="dps-client-lng" value="">';
        do_action( 'dps_registration_after_fields' );
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Enviar cadastro', 'dps-registration-addon' ) . '</button></p>';
        echo '</form>';
        // Lista de raças
        echo '<datalist id="dps-breed-list">';
        $breed_list = [
            // Cães
            'Affenpinscher',
            'Airedale Terrier',
            'Akita',
            'Basset Hound',
            'Beagle',
            'Bernese Mountain Dog (Boiadeiro Bernês)',
            'Bichon Frisé',
            'Bichon Havanês',
            'Bloodhound',
            'Boiadeiro Australiano',
            'Border Collie',
            'Borzói',
            'Boston Terrier',
            'Boxer',
            'Bulldog Americano',
            'Bulldog Francês',
            'Bulldog Inglês',
            'Bulldog Campeiro',
            'Bull Terrier',
            'Bullmastiff',
            'Cairn Terrier',
            'Cane Corso',
            'Cão Afegão',
            'Cão de Água Português',
            'Cão de Crista Chinês',
            'Cão de Pator Alemão (Pastor Alemão)',
            'Cão de Pastor Shetland',
            'Cavalier King Charles Spaniel',
            'Chihuahua',
            'Chow Chow',
            'Cocker Spaniel',
            'Collie',
            'Coton de Tulear',
            'Dachshund (Teckel)',
            'Dálmata',
            'Dobermann',
            'Dogo Argentino',
            'Dogue Alemão',
            'Fila Brasileiro',
            'Galgo Inglês',
            'Golden Retriever',
            'Husky Siberiano',
            'Jack Russell Terrier',
            'Labradoodle',
            'Labrador Retriever',
            'Lhasa Apso',
            'Lulu da Pomerânia (Spitz Alemão)',
            'Malamute do Alasca',
            'Maltês',
            'Papillon',
            'Pastor Australiano',
            'Pastor Belga Malinois',
            'Pastor de Shetland',
            'Pequinês',
            'Pinscher',
            'Pinscher Miniatura',
            'Pit Bull Terrier',
            'Poodle',
            'Poodle Toy',
            'Pug',
            'Rottweiler',
            'Samoieda',
            'Schnauzer',
            'Scottish Terrier',
            'Serra da Estrela',
            'Shar Pei',
            'Shiba Inu',
            'Shih Tzu',
            'Spitz Japonês',
            'Staffordshire Bull Terrier',
            'Terra-Nova',
            'Vira-lata',
            'SRD (Sem Raça Definida)',
            'Weimaraner',
            'Whippet',
            'Yorkshire Terrier',
            // Gatos
            'Abissínio',
            'Angorá Turco',
            'Azul Russo',
            'Bengal',
            'Birmanês',
            'British Shorthair',
            'Chartreux',
            'Cornish Rex',
            'Devon Rex',
            'Exótico de Pelo Curto',
            'Himalaio',
            'Maine Coon',
            'Munchkin',
            'Oriental de Pelo Curto',
            'Persa',
            'Ragdoll',
            'Sagrado da Birmânia',
            'Savannah',
            'Scottish Fold',
            'Selkirk Rex',
            'Siamês',
            'Somali',
            'Sphynx',
            'Tonquinês'
        ];
        foreach ( $breed_list as $br ) {
            echo '<option value="' . esc_attr( $br ) . '"></option>';
        }
        echo '</datalist>';
        // Script para adicionar pets dinamicamente e preencher campo Cliente automaticamente
        // CSS para melhorar a distribuição dos campos do formulário
        echo '<style>';
        echo '.dps-registration-form .dps-pet-fieldset, .dps-registration-form .dps-client-fields { display:flex; flex-wrap:wrap; gap:15px; }';
        echo '.dps-registration-form .dps-pet-fieldset p, .dps-registration-form .dps-client-fields p { flex:1 1 calc(50% - 15px); margin:0; }';
        echo '.dps-registration-form .dps-client-fields p[style*="100%"], .dps-registration-form .dps-client-fields textarea, .dps-registration-form .dps-pet-fieldset textarea { flex:1 1 100%; width:100%; }';
        echo '</style>';
        // Script principal para clonar pets e atualizar campos de cliente
        echo '<script type="text/javascript">(function(){';
        echo 'let petCount = 1;';
        echo 'const wrapper = document.getElementById("dps-pets-wrapper");';
        echo 'const addBtn  = document.getElementById("dps-add-pet");';
        echo 'const clientNameInput = document.getElementById("dps-client-name");';
        // Template de pet como string (JSON encoded)
        echo 'const template = ' . $placeholder_json . ';';
        // Função para atualizar campos Cliente
        echo 'function updateOwnerFields(){ var ownerFields=document.querySelectorAll(".dps-owner-name"); ownerFields.forEach(function(el){ el.value = clientNameInput.value; }); }';
        echo 'clientNameInput.addEventListener("input", updateOwnerFields);';
        echo 'addBtn.addEventListener("click", function(){ petCount++; let html = template.replace(/__INDEX__/g, petCount); const div = document.createElement("div"); div.innerHTML = html; wrapper.appendChild(div); updateOwnerFields(); });';
        echo 'updateOwnerFields();';
        echo '})();</script>';

        // Se houver uma API key do Google Maps configurada, inclui o script de Places e inicializa autocomplete
        $api_key = get_option( 'dps_google_api_key', '' );
        if ( $api_key ) {
            echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places"></script>';
            echo '<script>(function(){
                // Inicializa autocomplete no campo de endereço
                var input = document.getElementById("dps-client-address");
                if ( input ) {
                    var autocomplete = new google.maps.places.Autocomplete(input, { types: ["geocode"] });
                    autocomplete.addListener("place_changed", function() {
                        var place = autocomplete.getPlace();
                        if ( place && place.geometry ) {
                            var lat = place.geometry.location.lat();
                            var lng = place.geometry.location.lng();
                            var latField = document.getElementById("dps-client-lat");
                            var lngField = document.getElementById("dps-client-lng");
                            if ( latField && lngField ) {
                                latField.value = lat;
                                lngField.value = lng;
                            }
                        }
                    });
                }
            })();</script>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Envia email com token de confirmação para o cliente.
     *
     * @param int    $client_id    ID do post do cliente.
     * @param string $client_email Email do cliente.
     */
    protected function send_confirmation_email( $client_id, $client_email ) {
        $token = wp_generate_uuid4();
        update_post_meta( $client_id, 'dps_email_confirm_token', $token );

        $confirmation_link = add_query_arg( 'dps_confirm_email', $token, $this->get_registration_page_url() );

        $subject = __( 'Confirme seu email - Desi Pet Shower', 'desi-pet-shower' );
        $message = sprintf(
            "%s\n\n%s\n\n%s",
            __( 'Olá! Recebemos seu cadastro no Desi Pet Shower. Para ativar sua conta, confirme seu email clicando no link abaixo:', 'desi-pet-shower' ),
            esc_url_raw( $confirmation_link ),
            __( 'Se você não fez este cadastro, ignore esta mensagem.', 'desi-pet-shower' )
        );

        wp_mail( $client_email, $subject, $message );
    }

    /**
     * Retorna a URL da página de cadastro configurada.
     *
     * @return string
     */
    protected function get_registration_page_url() {
        $page_id = (int) get_option( 'dps_registration_page_id' );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }

        return home_url( '/' );
    }

    /**
     * Gera o HTML de um conjunto de campos para um pet específico.
     *
     * @param int $index Índice do pet (1, 2, ...)
     * @return string HTML
     */
    public function get_pet_fieldset_html( $index ) {
        $i = intval( $index );
        ob_start();
        echo '<fieldset class="dps-pet-fieldset" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        echo '<legend>' . sprintf( __( 'Pet %d', 'dps-registration-addon' ), $i ) . '</legend>';
        // Nome do pet
        echo '<p><label>' . esc_html__( 'Nome do Pet', 'dps-registration-addon' ) . '<br><input type="text" name="pet_name[]" class="dps-pet-name"></label></p>';
        // Nome do cliente (readonly)
        echo '<p><label>' . esc_html__( 'Cliente', 'dps-registration-addon' ) . '<br><input type="text" class="dps-owner-name" readonly></label></p>';
        // Espécie
        echo '<p><label>' . esc_html__( 'Espécie', 'dps-registration-addon' ) . '<br><select name="pet_species[]" required>';
        $species_opts = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'cao' => __( 'Cachorro', 'dps-registration-addon' ), 'gato' => __( 'Gato', 'dps-registration-addon' ), 'outro' => __( 'Outro', 'dps-registration-addon' ) ];
        foreach ( $species_opts as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        // Raça com datalist
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="dps-breed-list"></label></p>';
        // Porte
        echo '<p><label>' . esc_html__( 'Porte', 'dps-registration-addon' ) . '<br><select name="pet_size[]" required>';
        $sizes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'pequeno' => __( 'Pequeno', 'dps-registration-addon' ), 'medio' => __( 'Médio', 'dps-registration-addon' ), 'grande' => __( 'Grande', 'dps-registration-addon' ) ];
        foreach ( $sizes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Peso
        echo '<p><label>' . esc_html__( 'Peso (kg)', 'dps-registration-addon' ) . '<br><input type="number" step="0.01" name="pet_weight[]"></label></p>';
        // Pelagem
        echo '<p><label>' . esc_html__( 'Pelagem', 'dps-registration-addon' ) . '<br><input type="text" name="pet_coat[]"></label></p>';
        // Cor
        echo '<p><label>' . esc_html__( 'Cor', 'dps-registration-addon' ) . '<br><input type="text" name="pet_color[]"></label></p>';
        // Data de nascimento
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="pet_birth[]"></label></p>';
        // Sexo
        echo '<p><label>' . esc_html__( 'Sexo', 'dps-registration-addon' ) . '<br><select name="pet_sex[]" required>';
        $sexes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'macho' => __( 'Macho', 'dps-registration-addon' ), 'femea' => __( 'Fêmea', 'dps-registration-addon' ) ];
        foreach ( $sexes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Cuidados especiais
        echo '<p><label>' . esc_html__( 'Algum cuidado especial ou restrição?', 'dps-registration-addon' ) . '<br><textarea name="pet_care[]" rows="2"></textarea></label></p>';
        // Agressivo
        echo '<p><label><input type="checkbox" name="pet_aggressive[' . ( $i - 1 ) . ']" value="1"> ' . esc_html__( 'Cão agressivo', 'dps-registration-addon' ) . '</label></p>';
        echo '</fieldset>';
        return ob_get_clean();
    }

    /**
     * Retorna um conjunto de campos de pet com marcadores de substituição de índice. Usado para clonagem via JS.
     * O texto '__INDEX__' será substituído por JS com o número real do pet.
     *
     * @return string
     */
    public function get_pet_fieldset_html_placeholder() {
        ob_start();
        echo '<fieldset class="dps-pet-fieldset" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
        echo '<legend>' . __( 'Pet __INDEX__', 'dps-registration-addon' ) . '</legend>';
        echo '<p><label>' . esc_html__( 'Nome do Pet', 'dps-registration-addon' ) . '<br><input type="text" name="pet_name[]" class="dps-pet-name"></label></p>';
        echo '<p><label>' . esc_html__( 'Cliente', 'dps-registration-addon' ) . '<br><input type="text" class="dps-owner-name" readonly></label></p>';
        // Espécie
        echo '<p><label>' . esc_html__( 'Espécie', 'dps-registration-addon' ) . '<br><select name="pet_species[]" required>';
        $species_opts = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'cao' => __( 'Cachorro', 'dps-registration-addon' ), 'gato' => __( 'Gato', 'dps-registration-addon' ), 'outro' => __( 'Outro', 'dps-registration-addon' ) ];
        foreach ( $species_opts as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        // Raça
        echo '<p><label>' . esc_html__( 'Raça', 'dps-registration-addon' ) . '<br><input type="text" name="pet_breed[]" list="dps-breed-list"></label></p>';
        // Porte
        echo '<p><label>' . esc_html__( 'Porte', 'dps-registration-addon' ) . '<br><select name="pet_size[]" required>';
        $sizes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'pequeno' => __( 'Pequeno', 'dps-registration-addon' ), 'medio' => __( 'Médio', 'dps-registration-addon' ), 'grande' => __( 'Grande', 'dps-registration-addon' ) ];
        foreach ( $sizes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Peso
        echo '<p><label>' . esc_html__( 'Peso (kg)', 'dps-registration-addon' ) . '<br><input type="number" step="0.01" name="pet_weight[]"></label></p>';
        // Pelagem
        echo '<p><label>' . esc_html__( 'Pelagem', 'dps-registration-addon' ) . '<br><input type="text" name="pet_coat[]"></label></p>';
        // Cor
        echo '<p><label>' . esc_html__( 'Cor', 'dps-registration-addon' ) . '<br><input type="text" name="pet_color[]"></label></p>';
        // Data de nascimento
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-registration-addon' ) . '<br><input type="date" name="pet_birth[]"></label></p>';
        // Sexo
        echo '<p><label>' . esc_html__( 'Sexo', 'dps-registration-addon' ) . '<br><select name="pet_sex[]" required>';
        $sexes = [ '' => __( 'Selecione...', 'dps-registration-addon' ), 'macho' => __( 'Macho', 'dps-registration-addon' ), 'femea' => __( 'Fêmea', 'dps-registration-addon' ) ];
        foreach ( $sexes as $val => $lab ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Cuidados especiais
        echo '<p><label>' . esc_html__( 'Algum cuidado especial ou restrição?', 'dps-registration-addon' ) . '<br><textarea name="pet_care[]" rows="2"></textarea></label></p>';
        // Agressivo
        echo '<p><label><input type="checkbox" name="pet_aggressive[__INDEX__]" value="1"> ' . esc_html__( 'Cão agressivo', 'dps-registration-addon' ) . '</label></p>';
        echo '</fieldset>';
        return ob_get_clean();
    }
}

new DPS_Registration_Addon();