<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pelo frontend do plugin base. Contém métodos para renderizar
 * formulários e listas e para salvar/editar/excluir registros.
 */
class DPS_Base_Frontend {

    /**
     * Verifica se o usuário atual possui permissão para gerenciar o painel.
     *
     * @return bool
     */
    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Obtém a URL base para redirecionamentos após ações do formulário.
     *
     * @return string
     */
    private static function get_redirect_base_url() {
        $referer = wp_get_referer();
        if ( $referer ) {
            return esc_url_raw( $referer );
        }

        $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
        if ( $queried_id ) {
            return get_permalink( $queried_id );
        }

        global $post;
        if ( isset( $post->ID ) ) {
            return get_permalink( $post->ID );
        }

        return home_url();
    }

    /**
     * Monta a URL final de redirecionamento com base na aba desejada.
     *
     * @param string $tab Aba que deve ficar ativa após o redirecionamento.
     *
     * @return string
     */
    private static function get_redirect_url( $tab = '' ) {
        $base = self::get_redirect_base_url();
        $base = remove_query_arg(
            [ 'dps_delete', 'id', 'dps_edit', 'dps_view', 'tab', 'dps_action', 'dps_nonce' ],
            $base
        );

        if ( $tab ) {
            $base = add_query_arg( 'tab', $tab, $base );
        }

        return $base;
    }

    /**
     * Processa submissões de formulários
     */
    public static function handle_request() {
        // Verifica nonce
        if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_nonce'] ) ), 'dps_action' ) ) {
            return;
        }
        if ( ! self::can_manage() ) {
            return;
        }
        $action = isset( $_POST['dps_action'] ) ? sanitize_key( wp_unslash( $_POST['dps_action'] ) ) : '';
        switch ( $action ) {
            case 'save_client':
                self::save_client();
                break;
            case 'save_pet':
                self::save_pet();
                break;
            case 'save_appointment':
                self::save_appointment();
                break;
            default:
                break;
        }
    }

    /**
     * Processa exclusões de registros via query string
     */
    public static function handle_delete() {
        if ( ! self::can_manage() ) {
            return;
        }

        $type = isset( $_GET['dps_delete'] ) ? sanitize_key( wp_unslash( $_GET['dps_delete'] ) ) : '';
        $id   = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0;
        if ( ! $id ) {
            return;
        }
        // Verifica tipo e exclui
        switch ( $type ) {
            case 'client':
                wp_delete_post( $id, true );
                break;
            case 'pet':
                wp_delete_post( $id, true );
                break;
            case 'appointment':
                // Exclui o agendamento
                wp_delete_post( $id, true );
                // Remove transações financeiras associadas a este agendamento, se existirem
                global $wpdb;
                $trans_table = $wpdb->prefix . 'dps_transacoes';
                // Verifica se a tabela existe antes de tentar excluir
                if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $trans_table ) ) === $trans_table ) {
                    $wpdb->delete( $trans_table, [ 'agendamento_id' => $id ] );
                }
                break;
            default:
                return;
        }
        // Redireciona para a aba apropriada após exclusão.
        // Remove parâmetros de exclusão da URL para evitar loops de redirecionamento.
        $tab           = ( $type === 'appointment' ) ? 'agendas' : ( $type === 'pet' ? 'pets' : 'clientes' );
        $redirect_url  = self::get_redirect_url( $tab );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Renderiza a aplicação no frontend (abas para clientes, pets e agendamentos)
     */
    public static function render_app() {
        // Processa ações de salvamento/exclusão (já verificadas por maybe_handle_request)
        self::handle_request();
        // Manipula login e logout
        // Logout via query param
        if ( isset( $_GET['dps_logout'] ) ) {
            // Remove role cookies. Define caminho "/" para que os cookies sejam removidos em todo o site.
            setcookie( 'dps_base_role', '', time() - 3600, '/' );
            setcookie( 'dps_role', '', time() - 3600, '/' );
            // Redireciona removendo parâmetros da URL para evitar loops
            wp_redirect( remove_query_arg( [ 'dps_logout', 'tab', 'dps_edit', 'id', 'dps_view' ] ) );
            exit;
        }
        $login_error = '';
        // Verifica se há visualização específica (detalhes do cliente)
        if ( isset( $_GET['dps_view'] ) && 'client' === $_GET['dps_view'] && isset( $_GET['id'] ) ) {
            $client_id = intval( $_GET['id'] );
            return self::render_client_page( $client_id );
        }
        // Verifica se o usuário atual está logado e possui permissão de administrador
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            $login_url = wp_login_url( get_permalink() );
            return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar este painel.', 'dps-base' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-base' ) . '</a></p>';
        }
        // Sempre mostrar interface completa para usuários administradores
        ob_start();
        echo '<div class="dps-base-wrapper">';
        echo '<ul class="dps-nav">';
        echo '<li><a href="#" class="dps-tab-link" data-tab="clientes">' . esc_html__( 'Clientes', 'dps-base' ) . '</a></li>';
        echo '<li><a href="#" class="dps-tab-link" data-tab="pets">' . esc_html__( 'Pets', 'dps-base' ) . '</a></li>';
        echo '<li><a href="#" class="dps-tab-link" data-tab="agendas">' . esc_html__( 'Agendamentos', 'dps-base' ) . '</a></li>';
        // Não renderiza mais a aba de senhas
        // Permite que add-ons adicionem novas abas
        do_action( 'dps_base_nav_tabs', false );
        echo '</ul>';
        // Seções principais
        echo self::section_clients();
        echo self::section_pets();
        echo self::section_agendas( false );
        // Omitimos a seção de senhas
        // Permite que add-ons adicionem novas seções
        do_action( 'dps_base_sections', false );
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Obtém lista de clientes
     */
    private static function get_clients() {
        $args = [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * Obtém lista de pets
     */
    private static function get_pets() {
        $args = [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * Seção de clientes: formulário e listagem
     */
    private static function section_clients() {
        $clients = self::get_clients();
        // Detecta edição
        $edit_id    = ( isset( $_GET['dps_edit'] ) && 'client' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing    = null;
        $meta       = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                $meta = [
                    'cpf'      => get_post_meta( $edit_id, 'client_cpf', true ),
                    'phone'    => get_post_meta( $edit_id, 'client_phone', true ),
                    'email'    => get_post_meta( $edit_id, 'client_email', true ),
                    'birth'    => get_post_meta( $edit_id, 'client_birth', true ),
                    'instagram'=> get_post_meta( $edit_id, 'client_instagram', true ),
                    'facebook' => get_post_meta( $edit_id, 'client_facebook', true ),
                    'photo_auth' => get_post_meta( $edit_id, 'client_photo_auth', true ),
                    'address'  => get_post_meta( $edit_id, 'client_address', true ),
                    'referral' => get_post_meta( $edit_id, 'client_referral', true ),
                    'lat'      => get_post_meta( $edit_id, 'client_lat', true ),
                    'lng'      => get_post_meta( $edit_id, 'client_lng', true ),
                ];
            }
        }
        ob_start();
        echo '<div class="dps-section" id="dps-section-clientes">';
        echo '<h3>' . esc_html__( 'Cadastro de Clientes', 'dps-base' ) . '</h3>';
        echo '<form method="post" class="dps-form">';
        // Hidden fields
        echo '<input type="hidden" name="dps_action" value="save_client">';
        wp_nonce_field( 'dps_action', 'dps_nonce' );
        if ( $edit_id ) {
            echo '<input type="hidden" name="client_id" value="' . esc_attr( $edit_id ) . '">';
        }
        // Name
        $name_value = $editing ? $editing->post_title : '';
        echo '<p><label>' . esc_html__( 'Nome', 'dps-base' ) . '<br><input type="text" name="client_name" value="' . esc_attr( $name_value ) . '" required></label></p>';
        // CPF
        $cpf_val = $meta['cpf'] ?? '';
        echo '<p><label>' . esc_html__( 'CPF', 'dps-base' ) . '<br><input type="text" name="client_cpf" value="' . esc_attr( $cpf_val ) . '"></label></p>';
        // Phone / WhatsApp
        $phone_val = $meta['phone'] ?? '';
        echo '<p><label>' . esc_html__( 'Telefone / WhatsApp', 'dps-base' ) . '<br><input type="text" name="client_phone" value="' . esc_attr( $phone_val ) . '" required></label></p>';
        // Email
        $email_val = $meta['email'] ?? '';
        echo '<p><label>Email<br><input type="email" name="client_email" value="' . esc_attr( $email_val ) . '"></label></p>';
        // Birth date
        $birth_val = $meta['birth'] ?? '';
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-base' ) . '<br><input type="date" name="client_birth" value="' . esc_attr( $birth_val ) . '"></label></p>';
        // Instagram
        $insta_val = $meta['instagram'] ?? '';
        echo '<p><label>Instagram<br><input type="text" name="client_instagram" value="' . esc_attr( $insta_val ) . '" placeholder="@usuario"></label></p>';
        // Facebook
        $fb_val = $meta['facebook'] ?? '';
        echo '<p><label>Facebook<br><input type="text" name="client_facebook" value="' . esc_attr( $fb_val ) . '"></label></p>';
        // Photo authorization
        $auth = $meta['photo_auth'] ?? '';
        $checked = $auth ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="client_photo_auth" value="1" ' . $checked . '> ' . esc_html__( 'Autorizo publicação da foto do pet nas redes sociais do Desi Pet Shower', 'dps-base' ) . '</label></p>';
        // Address
        $addr_val = $meta['address'] ?? '';
        echo '<p><label>' . esc_html__( 'Endereço completo', 'dps-base' ) . '<br><textarea name="client_address" id="dps-client-address-admin" rows="2">' . esc_textarea( $addr_val ) . '</textarea></label></p>';
        // Referral (Como nos conheceu?)
        $ref_val = $meta['referral'] ?? '';
        echo '<p><label>' . esc_html__( 'Como nos conheceu?', 'dps-base' ) . '<br><input type="text" name="client_referral" value="' . esc_attr( $ref_val ) . '"></label></p>';
        // Campos ocultos para latitude e longitude (admin) - valores predefinidos se estiver editando
        $lat_admin = isset( $meta['lat'] ) ? $meta['lat'] : '';
        $lng_admin = isset( $meta['lng'] ) ? $meta['lng'] : '';
        echo '<input type="hidden" name="client_lat" id="dps-client-lat-admin" value="' . esc_attr( $lat_admin ) . '">';
        echo '<input type="hidden" name="client_lng" id="dps-client-lng-admin" value="' . esc_attr( $lng_admin ) . '">';
        // Submit button
        $btn_text = $edit_id ? esc_html__( 'Atualizar Cliente', 'dps-base' ) : esc_html__( 'Salvar Cliente', 'dps-base' );
        echo '<p><button type="submit" class="button button-primary">' . $btn_text . '</button></p>';
        echo '</form>';
        // Listagem de clientes
        echo '<h3>' . esc_html__( 'Clientes Cadastrados', 'dps-base' ) . '</h3>';
        echo '<p><input type="text" class="dps-search" placeholder="' . esc_attr__( 'Buscar...', 'dps-base' ) . '"></p>';
        if ( ! empty( $clients ) ) {
            $base_url = get_permalink();
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Nome', 'dps-base' ) . '</th><th>' . esc_html__( 'Telefone', 'dps-base' ) . '</th><th>' . esc_html__( 'Ações', 'dps-base' ) . '</th></tr></thead><tbody>';
            foreach ( $clients as $client ) {
                $phone_raw = get_post_meta( $client->ID, 'client_phone', true );
                // Gera link do WhatsApp se houver telefone
                $phone_display = '';
                if ( $phone_raw ) {
                    // Remove caracteres não numéricos
                    $phone_digits = preg_replace( '/\D+/', '', $phone_raw );
                    $wa_url = 'https://wa.me/' . $phone_digits;
                    $phone_display = '<a href="' . esc_url( $wa_url ) . '" target="_blank">' . esc_html( $phone_raw ) . '</a>';
                } else {
                    $phone_display = '-';
                }
                $edit_url   = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client->ID ], $base_url );
                $delete_url = add_query_arg( [ 'tab' => 'clientes', 'dps_delete' => 'client', 'id' => $client->ID ], $base_url );
                // Link para visualizar cadastro e histórico
                $view_url = add_query_arg( [ 'dps_view' => 'client', 'id' => $client->ID ], $base_url );
                // Link para agendar
                $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client->ID ], $base_url );
                echo '<tr>';
                echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html( $client->post_title ) . '</a></td>';
                echo '<td>' . $phone_display . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'dps-base' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'dps-base' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-base' ) . '</a> | <a href="' . esc_url( $schedule_url ) . '">' . esc_html__( 'Agendar', 'dps-base' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum cliente cadastrado.', 'dps-base' ) . '</p>';
        }
        // Se houver chave da API do Google Maps, injeta script de autocomplete de endereço
        $api_key = get_option( 'dps_google_api_key', '' );
        if ( $api_key ) {
            echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places"></script>';
            echo '<script>(function(){
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
            })();</script>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Seção de pets: formulário e listagem
     */
    private static function section_pets() {
        $clients = self::get_clients();
        $pets    = self::get_pets();
        // Detecta edição
        $edit_id    = ( isset( $_GET['dps_edit'] ) && 'pet' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing    = null;
        $meta       = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                $meta = [
                    'owner_id'  => get_post_meta( $edit_id, 'owner_id', true ),
                    'species'   => get_post_meta( $edit_id, 'pet_species', true ),
                    'breed'     => get_post_meta( $edit_id, 'pet_breed', true ),
                    'size'      => get_post_meta( $edit_id, 'pet_size', true ),
                    'weight'    => get_post_meta( $edit_id, 'pet_weight', true ),
                    'coat'      => get_post_meta( $edit_id, 'pet_coat', true ),
                    'color'     => get_post_meta( $edit_id, 'pet_color', true ),
                    'birth'     => get_post_meta( $edit_id, 'pet_birth', true ),
                    'sex'       => get_post_meta( $edit_id, 'pet_sex', true ),
                    'care'      => get_post_meta( $edit_id, 'pet_care', true ),
                    'aggressive'=> get_post_meta( $edit_id, 'pet_aggressive', true ),
                    // Campos adicionais de saúde, comportamento e foto para pets
                    'vaccinations' => get_post_meta( $edit_id, 'pet_vaccinations', true ),
                    'allergies'    => get_post_meta( $edit_id, 'pet_allergies', true ),
                    'behavior'     => get_post_meta( $edit_id, 'pet_behavior', true ),
                    'photo_id'     => get_post_meta( $edit_id, 'pet_photo_id', true ),
                ];
            }
        }
        // Prepara lista de raças para datalist (inclui várias raças de cães e gatos)
        $breeds = [
            'Affenpinscher', 'Afghan Hound', 'Airedale Terrier', 'Akita', 'Alaskan Malamute',
            'American Bulldog', 'Australian Shepherd', 'Basset Hound', 'Beagle', 'Belgian Malinois',
            'Bernese Mountain Dog', 'Bichon Frise', 'Border Collie', 'Boxer', 'Bulldog',
            'Bull Terrier', 'Cavalier King Charles Spaniel', 'Chihuahua', 'Chow Chow', 'Cocker Spaniel',
            'Collie', 'Dachshund', 'Dalmatian', 'Doberman Pinscher', 'French Bulldog',
            'German Shepherd', 'Golden Retriever', 'Great Dane', 'Greyhound', 'Jack Russell Terrier',
            'Labrador Retriever', 'Maltese', 'Miniature Pinscher', 'Newfoundland', 'Pomeranian',
            'Poodle', 'Portuguese Water Dog', 'Rottweiler', 'Samoyed', 'Schnauzer',
            'Shih Tzu', 'Siberian Husky', 'Staffordshire Bull Terrier', 'Weimaraner', 'Whippet',
            'Yorkshire Terrier', 'Siamese', 'Persian', 'Maine Coon', 'Ragdoll',
            'British Shorthair', 'Bengal', 'Abyssinian', 'Scottish Fold', 'Sphynx',
            'Birman', 'Oriental Shorthair', 'Russian Blue', 'Turkish Angora', 'Somali',
            'Burmese', 'Himalayan', 'Tonkinese', 'Munchkin', 'Chartreux',
            'Cornish Rex', 'Devon Rex', 'Norwegian Forest', 'Savannah', 'Selkirk Rex'
        ];
        ob_start();
        echo '<div class="dps-section" id="dps-section-pets">';
        echo '<h3>' . esc_html__( 'Cadastro de Pets', 'dps-base' ) . '</h3>';
        // Define enctype multipart/form-data para permitir upload de foto
        echo '<form method="post" enctype="multipart/form-data" class="dps-form">';
        echo '<input type="hidden" name="dps_action" value="save_pet">';
        wp_nonce_field( 'dps_action', 'dps_nonce' );
        if ( $edit_id ) {
            echo '<input type="hidden" name="pet_id" value="' . esc_attr( $edit_id ) . '">';
        }
        // Nome do pet
        $pet_name = $editing ? $editing->post_title : '';
        echo '<p><label>' . esc_html__( 'Nome do Pet', 'dps-base' ) . '<br><input type="text" name="pet_name" value="' . esc_attr( $pet_name ) . '" required></label></p>';
        // Cliente (tutor)
        $owner_selected = $meta['owner_id'] ?? '';
        echo '<p><label>' . esc_html__( 'Cliente', 'dps-base' ) . '<br><select name="owner_id" id="dps-pet-owner" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-base' ) . '</option>';
        foreach ( $clients as $client ) {
            $sel = (string) $client->ID === (string) $owner_selected ? 'selected' : '';
            echo '<option value="' . esc_attr( $client->ID ) . '" ' . $sel . '>' . esc_html( $client->post_title ) . '</option>';
        }
        echo '</select></label></p>';
        // Espécie
        $species_val = $meta['species'] ?? '';
        echo '<p><label>' . esc_html__( 'Espécie', 'dps-base' ) . '<br><select name="pet_species" required>';
        $species_options = [
            ''      => __( 'Selecione...', 'dps-base' ),
            'cao'   => __( 'Cachorro', 'dps-base' ),
            'gato'  => __( 'Gato', 'dps-base' ),
            'outro' => __( 'Outro', 'dps-base' ),
        ];
        foreach ( $species_options as $val => $label ) {
            $sel = ( $species_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label></p>';
        // Raça (com datalist)
        $breed_val = $meta['breed'] ?? '';
        echo '<p><label>' . esc_html__( 'Raça', 'dps-base' ) . '<br>';
        echo '<input type="text" name="pet_breed" list="dps-breed-list" value="' . esc_attr( $breed_val ) . '">' ;
        echo '</label></p>';
        // Datalist definition (outside of label)
        echo '<datalist id="dps-breed-list">';
        foreach ( $breeds as $breed ) {
            echo '<option value="' . esc_attr( $breed ) . '">';
        }
        echo '</datalist>';
        // Porte
        $size_val = $meta['size'] ?? '';
        echo '<p><label>' . esc_html__( 'Porte', 'dps-base' ) . '<br><select name="pet_size" required>';
        $sizes = [ '' => __( 'Selecione...', 'dps-base' ), 'pequeno' => __( 'Pequeno', 'dps-base' ), 'medio' => __( 'Médio', 'dps-base' ), 'grande' => __( 'Grande', 'dps-base' ) ];
        foreach ( $sizes as $val => $lab ) {
            $sel = ( $size_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Peso
        $weight_val = $meta['weight'] ?? '';
        echo '<p><label>' . esc_html__( 'Peso (kg)', 'dps-base' ) . '<br><input type="number" step="0.01" name="pet_weight" value="' . esc_attr( $weight_val ) . '"></label></p>';
        // Pelagem
        $coat_val = $meta['coat'] ?? '';
        echo '<p><label>' . esc_html__( 'Pelagem', 'dps-base' ) . '<br><input type="text" name="pet_coat" value="' . esc_attr( $coat_val ) . '"></label></p>';
        // Cor
        $color_val = $meta['color'] ?? '';
        echo '<p><label>' . esc_html__( 'Cor', 'dps-base' ) . '<br><input type="text" name="pet_color" value="' . esc_attr( $color_val ) . '"></label></p>';
        // Data de nascimento
        $birth_val = $meta['birth'] ?? '';
        echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-base' ) . '<br><input type="date" name="pet_birth" value="' . esc_attr( $birth_val ) . '"></label></p>';
        // Sexo
        $sex_val = $meta['sex'] ?? '';
        echo '<p><label>' . esc_html__( 'Sexo', 'dps-base' ) . '<br><select name="pet_sex" required>';
        $sexes = [ '' => __( 'Selecione...', 'dps-base' ), 'macho' => __( 'Macho', 'dps-base' ), 'femea' => __( 'Fêmea', 'dps-base' ) ];
        foreach ( $sexes as $val => $lab ) {
            $sel = ( $sex_val === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $lab ) . '</option>';
        }
        echo '</select></label></p>';
        // Cuidados especiais/restrições
        $care_val = $meta['care'] ?? '';
        echo '<p><label>' . esc_html__( 'Algum cuidado especial ou restrição?', 'dps-base' ) . '<br><textarea name="pet_care" rows="2">' . esc_textarea( $care_val ) . '</textarea></label></p>';
        // Agressivo
        $aggressive = $meta['aggressive'] ?? '';
        $checked_ag = $aggressive ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="pet_aggressive" value="1" ' . $checked_ag . '> ' . esc_html__( 'Cão agressivo', 'dps-base' ) . '</label></p>';

        // Campos adicionais de saúde, alergias e comportamento, além de foto do pet
        $vaccinations_val = $meta['vaccinations'] ?? '';
        echo '<p><label>' . esc_html__( 'Vacinas / Saúde', 'dps-base' ) . '<br><textarea name="pet_vaccinations" rows="2">' . esc_textarea( $vaccinations_val ) . '</textarea></label></p>';
        $allergies_val = $meta['allergies'] ?? '';
        echo '<p><label>' . esc_html__( 'Alergias / Restrições', 'dps-base' ) . '<br><textarea name="pet_allergies" rows="2">' . esc_textarea( $allergies_val ) . '</textarea></label></p>';
        $behavior_val = $meta['behavior'] ?? '';
        echo '<p><label>' . esc_html__( 'Notas de Comportamento', 'dps-base' ) . '<br><textarea name="pet_behavior" rows="2">' . esc_textarea( $behavior_val ) . '</textarea></label></p>';
        // Upload de foto do pet
        $photo_id  = $meta['photo_id'] ?? '';
        $photo_url = '';
        if ( $photo_id ) {
            $photo_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
        }
        echo '<p><label>' . esc_html__( 'Foto do Pet', 'dps-base' ) . '<br><input type="file" name="pet_photo" accept="image/*"></label></p>';
        if ( $photo_url ) {
            // Exibe a foto atual
            echo '<p><img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet_name ) . '" style="max-width:100px;height:auto;"></p>';
        }
        // Botão
        $btn_text = $edit_id ? esc_html__( 'Atualizar Pet', 'dps-base' ) : esc_html__( 'Salvar Pet', 'dps-base' );
        echo '<p><button type="submit" class="button button-primary">' . $btn_text . '</button></p>';
        echo '</form>';
        // Listagem de pets
        echo '<h3>' . esc_html__( 'Pets Cadastrados', 'dps-base' ) . '</h3>';
        echo '<p><input type="text" class="dps-search" placeholder="' . esc_attr__( 'Buscar...', 'dps-base' ) . '"></p>';
        if ( ! empty( $pets ) ) {
            $base_url = get_permalink();
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Nome', 'dps-base' ) . '</th><th>' . esc_html__( 'Cliente', 'dps-base' ) . '</th><th>' . esc_html__( 'Espécie', 'dps-base' ) . '</th><th>' . esc_html__( 'Raça', 'dps-base' ) . '</th><th>' . esc_html__( 'Ações', 'dps-base' ) . '</th></tr></thead><tbody>';
            foreach ( $pets as $pet ) {
                $owner_id = get_post_meta( $pet->ID, 'owner_id', true );
                $owner    = $owner_id ? get_post( $owner_id ) : null;
                $species  = get_post_meta( $pet->ID, 'pet_species', true );
                $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
                $edit_url   = add_query_arg( [ 'tab' => 'pets', 'dps_edit' => 'pet', 'id' => $pet->ID ], $base_url );
                $delete_url = add_query_arg( [ 'tab' => 'pets', 'dps_delete' => 'pet', 'id' => $pet->ID ], $base_url );
                // Mapping species codes to labels
                $species_label = '';
                switch ( $species ) {
                    case 'cao':
                        $species_label = __( 'Cachorro', 'dps-base' );
                        break;
                    case 'gato':
                        $species_label = __( 'Gato', 'dps-base' );
                        break;
                    case 'outro':
                        $species_label = __( 'Outro', 'dps-base' );
                        break;
                    default:
                        $species_label = $species;
                        break;
                }
                // Link owner name to client page
                $owner_display = $owner ? '<a href="' . esc_url( add_query_arg( [ 'dps_view' => 'client', 'id' => $owner->ID ], $base_url ) ) . '">' . esc_html( $owner->post_title ) . '</a>' : '-';
                // Link to schedule service for this pet
                $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $owner_id, 'pref_pet' => $pet->ID ], $base_url );
                echo '<tr>';
                echo '<td>' . esc_html( $pet->post_title ) . '</td>';
                echo '<td>' . $owner_display . '</td>';
                echo '<td>' . esc_html( $species_label ) . '</td>';
                echo '<td>' . esc_html( $breed ) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'dps-base' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'dps-base' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-base' ) . '</a> | <a href="' . esc_url( $schedule_url ) . '">' . esc_html__( 'Agendar', 'dps-base' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'dps-base' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Seção de agendamentos: formulário e listagem
     */
    private static function section_agendas( $visitor_only = false ) {
        $clients = self::get_clients();
        $pets    = self::get_pets();
        // Detecta edição de agendamento
        $edit_id    = ( isset( $_GET['dps_edit'] ) && 'appointment' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing    = null;
        $meta       = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                $meta = [
                    'client_id' => get_post_meta( $edit_id, 'appointment_client_id', true ),
                    'pet_id'    => get_post_meta( $edit_id, 'appointment_pet_id', true ),
                    'date'      => get_post_meta( $edit_id, 'appointment_date', true ),
                    'time'      => get_post_meta( $edit_id, 'appointment_time', true ),
                    'notes'     => get_post_meta( $edit_id, 'appointment_notes', true ),
                    'appointment_type' => get_post_meta( $edit_id, 'appointment_type', true ),
                    'tosa'      => get_post_meta( $edit_id, 'appointment_tosa', true ),
                    // Recupera preço e ocorrência da tosa para pré-preenchimento (caso existam)
                    'tosa_price'     => get_post_meta( $edit_id, 'appointment_tosa_price', true ),
                    'tosa_occurrence' => get_post_meta( $edit_id, 'appointment_tosa_occurrence', true ),
                    'taxidog'   => get_post_meta( $edit_id, 'appointment_taxidog', true ),
                    'taxidog_price' => get_post_meta( $edit_id, 'appointment_taxidog_price', true ),
                    'extra_description' => get_post_meta( $edit_id, 'appointment_extra_description', true ),
                    'extra_value'       => get_post_meta( $edit_id, 'appointment_extra_value', true ),
                    'subscription_base_value'  => get_post_meta( $edit_id, 'subscription_base_value', true ),
                    'subscription_total_value' => get_post_meta( $edit_id, 'subscription_total_value', true ),
                    'subscription_extra_description' => get_post_meta( $edit_id, 'subscription_extra_description', true ),
                    'subscription_extra_value'       => get_post_meta( $edit_id, 'subscription_extra_value', true ),
                ];
            }
        }
        // Pré‑seleção de cliente e pet se não estiver editando
        $pref_client = isset( $_GET['pref_client'] ) ? intval( $_GET['pref_client'] ) : 0;
        $pref_pet    = isset( $_GET['pref_pet'] ) ? intval( $_GET['pref_pet'] ) : 0;
        ob_start();
        echo '<div class="dps-section" id="dps-section-agendas">';
        echo '<h3>' . esc_html__( 'Agendamento de Serviços', 'dps-base' ) . '</h3>';
        // Alerta de pagamentos pendentes
        if ( ! $visitor_only ) {
            $sel_client = $meta['client_id'] ?? '';
            // Se não estiver editando e houver pré-seleção, usa pref_client ou meta já definido
            if ( ! $edit_id && isset( $_GET['pref_client'] ) && ! $sel_client ) {
                $sel_client = intval( $_GET['pref_client'] );
            }
            if ( $sel_client ) {
                global $wpdb;
                $table_trans = $wpdb->prefix . 'dps_transacoes';
                // Busca transações em aberto para este cliente (status diferente de 'pago')
                $pendings = [];
                if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_trans ) ) === $table_trans ) {
                    $pendings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_trans} WHERE cliente_id = %d AND status != %s", $sel_client, 'pago' ) );
                }
                if ( $pendings ) {
                    echo '<div class="notice notice-error" style="border-left:4px solid #dc3232;padding:10px;margin:10px 0;background:#f8d7da;color:#721c24;">';
                    echo '<strong>' . esc_html__( 'Atenção:', 'dps-base' ) . '</strong> ' . esc_html__( 'Este cliente possui pagamentos pendentes:', 'dps-base' );
                    echo '<ul style="margin-top:5px;">';
                    foreach ( $pendings as $p ) {
                        $date_fmt = $p->data ? date_i18n( 'd-m-Y', strtotime( $p->data ) ) : '';
                        $valor_fmt = number_format( (float) $p->valor, 2, ',', '.' );
                        $desc = $p->descricao ?: '';
                        echo '<li>' . esc_html( $date_fmt . ': R$ ' . $valor_fmt . ' – ' . $desc ) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            }
        }
        // Formulário de agendamento
        if ( ! $visitor_only ) {
            echo '<form method="post" class="dps-form">';
            echo '<input type="hidden" name="dps_action" value="save_appointment">';
            wp_nonce_field( 'dps_action', 'dps_nonce' );
            if ( $edit_id ) {
                echo '<input type="hidden" name="appointment_id" value="' . esc_attr( $edit_id ) . '">';
            }
            // Campo: tipo de agendamento (simples ou assinatura)
            $appt_type = isset( $meta['appointment_type'] ) ? $meta['appointment_type'] : 'simple';
            echo '<p><label>' . esc_html__( 'Tipo de agendamento', 'dps-base' ) . '<br>';
            echo '<label><input type="radio" name="appointment_type" value="simple" ' . ( $appt_type === 'subscription' ? '' : 'checked' ) . '> ' . esc_html__( 'Agendamento simples', 'dps-base' ) . '</label> ';
            echo '<label style="margin-left:20px;"><input type="radio" name="appointment_type" value="subscription" ' . ( $appt_type === 'subscription' ? 'checked' : '' ) . '> ' . esc_html__( 'Agendamento de assinatura', 'dps-base' ) . '</label>';
            echo '</label></p>';
            // Campo: frequência para assinaturas (semanal ou quinzenal)
            $freq_val = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';
            if ( $edit_id ) {
                // Se estiver editando, tenta obter frequência via subscription_id
                $sub_id_edit = get_post_meta( $edit_id, 'subscription_id', true );
                if ( $sub_id_edit ) {
                    $freq_val = get_post_meta( $sub_id_edit, 'subscription_frequency', true );
                }
            }
            $freq_display = ( $appt_type === 'subscription' ) ? 'block' : 'none';
            echo '<p id="dps-appointment-frequency-wrapper" style="display:' . esc_attr( $freq_display ) . '"><label>' . esc_html__( 'Frequência', 'dps-base' ) . '<br><select name="appointment_frequency" id="dps-appointment-frequency"><option value="semanal" ' . selected( $freq_val, 'semanal', false ) . '>' . esc_html__( 'Semanal', 'dps-base' ) . '</option><option value="quinzenal" ' . selected( $freq_val, 'quinzenal', false ) . '>' . esc_html__( 'Quinzenal', 'dps-base' ) . '</option></select></label></p>';

            // Cliente
            // Preenchimento: se não editando, usa pref_client se disponível
            if ( ! $edit_id && $pref_client ) {
                $meta['client_id'] = $pref_client;
            }
            $sel_client = $meta['client_id'] ?? '';
            echo '<p><label>' . esc_html__( 'Cliente', 'dps-base' ) . '<br><select name="appointment_client_id" id="dps-appointment-cliente" required>';
            echo '<option value="">' . esc_html__( 'Selecione...', 'dps-base' ) . '</option>';
            foreach ( $clients as $client ) {
                $sel = (string) $client->ID === (string) $sel_client ? 'selected' : '';
                echo '<option value="' . esc_attr( $client->ID ) . '" ' . $sel . '>' . esc_html( $client->post_title ) . '</option>';
            }
            echo '</select></label></p>';
            // Pets (permite múltiplos)
            // Se não editando, utiliza pref_pet como pré‑seleção única
            if ( ! $edit_id && $pref_pet ) {
                $meta['pet_id'] = $pref_pet;
            }
            // Obtém lista de pets selecionados. Para edições, meta['pet_id'] pode ser ID único.
            $sel_pets = [];
            if ( isset( $meta['pet_id'] ) && $meta['pet_id'] ) {
                $sel_pets[] = (string) $meta['pet_id'];
            }
            // Caso tenhamos meta appointment_pet_ids (quando multi‑pets são salvos), utiliza essa lista
            $multi_meta = get_post_meta( $edit_id, 'appointment_pet_ids', true );
            if ( $multi_meta && is_array( $multi_meta ) ) {
                $sel_pets = array_map( 'strval', $multi_meta );
            }
            echo '<p><label>' . esc_html__( 'Pet(s)', 'dps-base' ) . '<br><select name="appointment_pet_ids[]" id="dps-appointment-pet" multiple required>';
            foreach ( $pets as $pet ) {
                $owner_id  = get_post_meta( $pet->ID, 'owner_id', true );
                $owner_attr = $owner_id ? ' data-owner="' . esc_attr( $owner_id ) . '"' : '';
                $size       = get_post_meta( $pet->ID, 'pet_size', true );
                $size_attr  = $size ? ' data-size="' . esc_attr( $size ) . '"' : '';
                $sel       = in_array( (string) $pet->ID, $sel_pets, true ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $pet->ID ) . '"' . $owner_attr . $size_attr . ' ' . $sel . '>' . esc_html( $pet->post_title ) . '</option>';
            }
            echo '</select></label></p>';
            // Data
            $date_val = $meta['date'] ?? '';
            echo '<p><label>' . esc_html__( 'Data', 'dps-base' ) . '<br><input type="date" name="appointment_date" value="' . esc_attr( $date_val ) . '" required></label></p>';
            // Hora
            $time_val = $meta['time'] ?? '';
            echo '<p><label>' . esc_html__( 'Horário', 'dps-base' ) . '<br><input type="time" name="appointment_time" value="' . esc_attr( $time_val ) . '" required></label></p>';
            // Campo: indicativo de necessidade de tosa.
            // Campos relativos à tosa: exibidos apenas quando o tipo de agendamento for assinatura. Agrupamos em div para controle via JS.
            $tosa       = $meta['tosa'] ?? '';
            $tosa_price = $meta['tosa_price'] ?? '';
            $tosa_occ   = $meta['tosa_occurrence'] ?? '1';
            $tosa_display = ( '1' === $tosa ) ? 'block' : 'none';
            echo '<div id="dps-tosa-wrapper" style="display:none; margin-bottom:10px;">';
            echo '<p><label><input type="checkbox" id="dps-tosa-toggle" name="appointment_tosa" value="1" ' . checked( $tosa, '1', false ) . '> ' . esc_html__( 'Precisa de tosa?', 'dps-base' ) . '</label> ';
            echo '<small>' . esc_html__( 'Adicione um serviço de tosa à assinatura', 'dps-base' ) . '</small></p>';
            echo '<div id="dps-tosa-fields" style="display:' . esc_attr( $tosa_display ) . ';">';
            // Preço da tosa com valor padrão 30 se não definido
            $tosa_price_val = $tosa_price !== '' ? $tosa_price : '30';
            echo '<p><label>' . esc_html__( 'Preço da tosa (R$)', 'dps-base' ) . '<br><input type="number" step="0.01" min="0" id="dps-tosa-price" name="appointment_tosa_price" value="' . esc_attr( $tosa_price_val ) . '" style="width:80px;"></label></p>';
            // Ocorrência da tosa (selecionada via JS conforme frequência)
            echo '<p><label>' . esc_html__( 'Ocorrência da tosa', 'dps-base' ) . '<br>';
            echo '<select name="appointment_tosa_occurrence" id="appointment_tosa_occurrence" data-current="' . esc_attr( $tosa_occ ) . '"></select></label></p>';
            echo '</div>';
            echo '</div>';

            // Campo: escolha de TaxiDog
            $taxidog = $meta['taxidog'] ?? '';
            echo '<p><label><input type="checkbox" id="dps-taxidog-toggle" name="appointment_taxidog" value="1" ' . checked( $taxidog, '1', false ) . '> ' . esc_html__( 'Solicitar TaxiDog?', 'dps-base' ) . '</label>';
            echo '<span id="dps-taxidog-extra" style="margin-left:10px; display:' . ( $taxidog ? 'inline-block' : 'none' ) . ';">';
            echo esc_html__( 'Valor (R$)', 'dps-base' ) . ': <input type="number" id="dps-taxidog-price" name="appointment_taxidog_price" step="0.01" min="0" value="' . esc_attr( $meta['taxidog_price'] ?? '' ) . '" style="width:80px;">';
            echo '</span></p>';

            // Observações
            $notes_val = $meta['notes'] ?? '';
            echo '<p><label>' . esc_html__( 'Observações', 'dps-base' ) . '<br><textarea name="appointment_notes" rows="2">' . esc_textarea( $notes_val ) . '</textarea></label></p>';
            /**
             * Permite que add‑ons adicionem campos extras ao formulário de agendamento (ex.: serviços).
             *
             * @param int   $edit_id ID do agendamento em edição ou 0 se novo
             * @param array $meta    Meta dados do agendamento
             */
            do_action( 'dps_base_appointment_fields', $edit_id, $meta );
            // Botão
            $btn_text = $edit_id ? esc_html__( 'Atualizar Agendamento', 'dps-base' ) : esc_html__( 'Salvar Agendamento', 'dps-base' );
            echo '<p><button type="submit" class="button button-primary">' . $btn_text . '</button></p>';
            // Script para alternar campos de acordo com o tipo de agendamento e uso de TaxiDog.  
            // Utilizamos heredoc para evitar problemas de escape de aspas simples/dobras.
            $dps_script = <<<EOT
<script>
jQuery(function($){
    function toggleTaxiDog(){
        var type = $('input[name="appointment_type"]:checked').val();
        var hasTaxi = $('#dps-taxidog-toggle').is(':checked');
        if(type === 'subscription'){
            $('#dps-taxidog-extra').hide();
        } else {
            $('#dps-taxidog-extra').toggle(hasTaxi);
        }
    }

    function updateTypeFields(){
        var type = $('input[name="appointment_type"]:checked').val();
        // Exibe ou oculta o seletor de frequência
        $('#dps-appointment-frequency-wrapper').toggle(type === 'subscription');
        // Exibe ou oculta campos de tosa somente nas assinaturas
        $('#dps-tosa-wrapper').toggle(type === 'subscription');
        $('.dps-simple-fields').toggle(type !== 'subscription');
        $('.dps-subscription-fields').toggle(type === 'subscription');
        toggleTaxiDog();
    }

    function updateTosaFields(){
        var show = $('#dps-tosa-toggle').is(':checked');
        $('#dps-tosa-fields').toggle(show);
    }

    // Atualiza opções de ocorrência da tosa conforme a frequência selecionada
    function updateTosaOptions(){
        var freq = $('select[name="appointment_frequency"]').val() || 'semanal';
        var select = $('#appointment_tosa_occurrence');
        var occurrences = (freq === 'quinzenal') ? 2 : 4;
        var current = select.data('current');
        select.empty();
        for(var i = 1; i <= occurrences; i++){
            select.append('<option value="'+i+'">'+i+'º Atendimento</option>');
        }
        if(current && current <= occurrences){
            select.val(current);
        }
    }
    // Inicializa campos de tipo e tosa
    updateTypeFields();
    updateTosaOptions();
    updateTosaFields();
    $(document).on('change','input[name="appointment_type"]', function(){
        updateTypeFields();
        updateTosaOptions();
        updateTosaFields();
    });
    // Atualiza opções de tosa ao alterar a frequência
    $('select[name="appointment_frequency"]').on('change', function(){
        updateTosaOptions();
    });
    $('#dps-taxidog-toggle').on('change', function(){
        toggleTaxiDog();
    });
    $('#dps-tosa-toggle').on('change', function(){
        updateTosaFields();
    });
});
</script>
EOT;
            echo $dps_script;
            echo '</form>';
        }
        // Listagem de agendamentos futuros (ordenados por data e hora)
        echo '<h3>' . esc_html__( 'Próximos Agendamentos', 'dps-base' ) . '</h3>';
        echo '<p><input type="text" class="dps-search" placeholder="' . esc_attr__( 'Buscar...', 'dps-base' ) . '"></p>';
        $args = [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        $appointments = get_posts( $args );
        // Obtém a URL base para links de edição e exclusão antes de qualquer saída
        $base_url = get_permalink();
        // Classifica os agendamentos em pendentes de dias anteriores e próximos
        $overdue_ids = [];
        $today_date  = current_time( 'Y-m-d' );
        if ( $appointments ) {
            foreach ( $appointments as $tmp_appt ) {
                $tmp_status = get_post_meta( $tmp_appt->ID, 'appointment_status', true );
                // Ignora finalizados, pagos e cancelados na listagem principal
                if ( in_array( $tmp_status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                    continue;
                }
                $tmp_date = get_post_meta( $tmp_appt->ID, 'appointment_date', true );
                if ( $tmp_date && $tmp_date < $today_date ) {
                    $overdue_ids[] = $tmp_appt->ID;
                }
            }
        }
        // Renderiza tabela de pendentes anteriores se houver
        if ( ! empty( $overdue_ids ) ) {
            echo '<h4>' . esc_html__( 'Agendamentos Pendentes (dias anteriores)', 'dps-base' ) . '</h4>';
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-base' ) . '</th>';
            if ( ! $visitor_only ) {
                echo '<th>' . esc_html__( 'Ações', 'dps-base' ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $appointments as $ov_appt ) {
                if ( ! in_array( $ov_appt->ID, $overdue_ids, true ) ) {
                    continue;
                }
                $date_o      = get_post_meta( $ov_appt->ID, 'appointment_date', true );
                $time_o      = get_post_meta( $ov_appt->ID, 'appointment_time', true );
                $client_id_o = get_post_meta( $ov_appt->ID, 'appointment_client_id', true );
                $pet_id_o    = get_post_meta( $ov_appt->ID, 'appointment_pet_id', true );
                $client_o    = $client_id_o ? get_post( $client_id_o ) : null;
                $pet_o       = $pet_id_o ? get_post( $pet_id_o ) : null;
                $edit_url_o   = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $ov_appt->ID ], $base_url );
                $delete_url_o = add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $ov_appt->ID ], $base_url );
                echo '<tr>';
                $date_o_fmt = $date_o ? date_i18n( 'd-m-Y', strtotime( $date_o ) ) : '';
                echo '<td>' . esc_html( $date_o_fmt ) . '</td>';
                echo '<td>' . esc_html( $time_o ) . '</td>';
                echo '<td>' . esc_html( $client_o ? $client_o->post_title : '-' ) . '</td>';
                $sub_meta_o = get_post_meta( $ov_appt->ID, 'subscription_id', true );
                $pet_name_o = $pet_o ? $pet_o->post_title : '-';
                if ( $sub_meta_o ) {
                    $pet_name_o .= ' ' . esc_html__( '(Assinatura)', 'dps-base' );
                }
                echo '<td>' . esc_html( $pet_name_o ) . '</td>';
                if ( ! $visitor_only ) {
                    echo '<td><a href="' . esc_url( $edit_url_o ) . '">' . esc_html__( 'Editar', 'dps-base' ) . '</a> | <a href="' . esc_url( $delete_url_o ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'dps-base' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-base' ) . '</a></td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        if ( $appointments ) {
            // Recalcula base_url localmente para uso no restante da listagem
            $base_url = get_permalink();
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-base' ) . '</th>';
            if ( ! $visitor_only ) {
                // Adiciona coluna de cobrança via WhatsApp para serviços finalizados
                echo '<th>' . esc_html__( 'Cobrança', 'dps-base' ) . '</th>';
                echo '<th>' . esc_html__( 'Ações', 'dps-base' ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                // Pula agendamentos finalizados, pagos ou cancelados. Estes serão listados em seção separada.
                $status_for_skip = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( in_array( $status_for_skip, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                    continue;
                }
                // Pula agendamentos de datas anteriores (pendentes) que já foram listados na seção "Pendentes (dias anteriores)"
                $date_check = get_post_meta( $appt->ID, 'appointment_date', true );
                // Obtém data atual em formato Y-m-d para comparação
                $today_check = current_time( 'Y-m-d' );
                if ( $date_check && $date_check < $today_check ) {
                    continue;
                }
                $date = get_post_meta( $appt->ID, 'appointment_date', true );
                $time = get_post_meta( $appt->ID, 'appointment_time', true );
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $client = $client_id ? get_post( $client_id ) : null;
                $pet    = $pet_id ? get_post( $pet_id ) : null;
                $edit_url   = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                $delete_url = add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $appt->ID ], $base_url );
                echo '<tr>';
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $client ? $client->post_title : '-' ) . '</td>';
                // Indica se o agendamento é de uma assinatura
                $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
                $pet_name = $pet ? $pet->post_title : '-';
                if ( $sub_id_meta ) {
                    $pet_name .= ' ' . esc_html__( '(Assinatura)', 'dps-base' );
                }
                echo '<td>' . esc_html( $pet_name ) . '</td>';
                if ( ! $visitor_only ) {
                    // Coluna de cobrança
                    $charge_html = '-';
                    $appointment_status = get_post_meta( $appt->ID, 'appointment_status', true );
                    $appt_type = get_post_meta( $appt->ID, 'appointment_type', true );
                    // Ignora assinaturas
                    if ( 'finalizado' === $appointment_status && 'subscription' !== $appt_type ) {
                        $client_phone = $client_id ? get_post_meta( $client_id, 'client_phone', true ) : '';
                        $total_value   = get_post_meta( $appt->ID, 'appointment_total_value', true );
                        if ( ! empty( $client_phone ) && ! empty( $total_value ) ) {
                            // Sanitiza número: remove qualquer caractere não numérico
                            $num = preg_replace( '/[^0-9]/', '', $client_phone );
                            // Se não começar com 55 e tiver 10 ou 11 dígitos, adiciona código do Brasil
                            if ( strlen( $num ) >= 10 && substr( $num, 0, 2 ) !== '55' ) {
                                $num = '55' . $num;
                            }
                            $client_name = $client ? $client->post_title : '';
                            $pet_name    = $pet ? $pet->post_title : '';
                            // Formata valor com duas casas decimais
                            $valor_formatado = number_format( (float) $total_value, 2, ',', '.' );
                            $msg = sprintf( 'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Seguem opções de pagamento: PIX 15 99160‑6299 ou via link https://link.mercadopago.com.br/desipetshower. Agradecemos sua preferência!', $client_name, $pet_name, $valor_formatado );
                            $encoded = rawurlencode( $msg );
                            $charge_url = 'https://wa.me/' . $num . '?text=' . $encoded;
                            $charge_html = '<a href="' . esc_url( $charge_url ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-base' ) . '</a>';
                        }
                    }
                    echo '<td>' . $charge_html . '</td>';
                    // Coluna de ações
                    echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'dps-base' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'dps-base' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-base' ) . '</a></td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum agendamento encontrado.', 'dps-base' ) . '</p>';
        }

        // ==== Seção de atendimentos já realizados (finalizados, pagos ou cancelados) ====
        // Exibe uma lista com todos os agendamentos cujo status seja finalizado, finalizado e pago ou cancelado.
        // Útil para consultas e administração de históricos.
        $past_appts = [];
        if ( $appointments ) {
            foreach ( $appointments as $past ) {
                $past_status = get_post_meta( $past->ID, 'appointment_status', true );
                if ( in_array( $past_status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                    $past_appts[] = $past;
                }
            }
        }
        if ( ! empty( $past_appts ) ) {
            echo '<h4>' . esc_html__( 'Atendimentos Realizados', 'dps-base' ) . '</h4>';
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-base' ) . '</th>';
            // Verifica permissão para exibir colunas adicionais (cobrança e ações)
            $show_actions = is_user_logged_in() && current_user_can( 'manage_options' );
            if ( $show_actions ) {
                echo '<th>' . esc_html__( 'Cobrança', 'dps-base' ) . '</th>';
                echo '<th>' . esc_html__( 'Ações', 'dps-base' ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $past_appts as $past ) {
                $p_date   = get_post_meta( $past->ID, 'appointment_date', true );
                $p_time   = get_post_meta( $past->ID, 'appointment_time', true );
                $p_client_id = get_post_meta( $past->ID, 'appointment_client_id', true );
                $p_pet_id    = get_post_meta( $past->ID, 'appointment_pet_id', true );
                $p_client = $p_client_id ? get_post( $p_client_id ) : null;
                $p_pet    = $p_pet_id ? get_post( $p_pet_id ) : null;
                $p_status = get_post_meta( $past->ID, 'appointment_status', true );
                // Monta informações
                $p_pet_name = $p_pet ? $p_pet->post_title : '-';
                $p_client_name = $p_client ? $p_client->post_title : '-';
                // Altera rotulo de status para exibição
                $status_label = $p_status;
                if ( 'finalizado' === $p_status ) {
                    $status_label = __( 'Finalizado', 'dps-base' );
                } elseif ( 'finalizado_pago' === $p_status || 'finalizado e pago' === $p_status ) {
                    $status_label = __( 'Finalizado e pago', 'dps-base' );
                } elseif ( 'cancelado' === $p_status ) {
                    $status_label = __( 'Cancelado', 'dps-base' );
                }
                // Links de edição/exclusão
                $p_edit_url   = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $past->ID ], $base_url );
                $p_delete_url = add_query_arg( [ 'tab' => 'agendas', 'dps_delete' => 'appointment', 'id' => $past->ID ], $base_url );
                echo '<tr>';
                $p_date_fmt = $p_date ? date_i18n( 'd-m-Y', strtotime( $p_date ) ) : '';
                echo '<td>' . esc_html( $p_date_fmt ) . '</td>';
                echo '<td>' . esc_html( $p_time ) . '</td>';
                echo '<td>' . esc_html( $p_client_name ) . '</td>';
                echo '<td>' . esc_html( $p_pet_name ) . '</td>';
                echo '<td>' . esc_html( $status_label ) . '</td>';
                if ( $show_actions ) {
                    // Coluna de cobrança: somente se status for finalizado mas ainda não pago
                    $p_charge_html = '-';
                    if ( 'finalizado' === $p_status ) {
                        $client_phone_p = $p_client_id ? get_post_meta( $p_client_id, 'client_phone', true ) : '';
                        $total_value_p = get_post_meta( $past->ID, 'appointment_total_value', true );
                        if ( ! empty( $client_phone_p ) && ! empty( $total_value_p ) ) {
                            $num_p = preg_replace( '/[^0-9]/', '', $client_phone_p );
                            if ( strlen( $num_p ) >= 10 && substr( $num_p, 0, 2 ) !== '55' ) {
                                $num_p = '55' . $num_p;
                            }
                            // Formata valor
                            $valor_formatado_p = number_format( (float) $total_value_p, 2, ',', '.' );
                            $msg_p = sprintf( 'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Seguem opções de pagamento: PIX 15 99160‑6299 ou via link https://link.mercadopago.com.br/desipetshower. Agradecemos sua preferência!', $p_client_name, $p_pet_name, $valor_formatado_p );
                            $encoded_p = rawurlencode( $msg_p );
                            $p_charge_url = 'https://wa.me/' . $num_p . '?text=' . $encoded_p;
                            $p_charge_html = '<a href="' . esc_url( $p_charge_url ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-base' ) . '</a>';
                        }
                    }
                    echo '<td>' . $p_charge_html . '</td>';
                    // Ações
                    echo '<td><a href="' . esc_url( $p_edit_url ) . '">' . esc_html__( 'Editar', 'dps-base' ) . '</a> | <a href="' . esc_url( $p_delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir?', 'dps-base' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-base' ) . '</a></td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Seção de senhas: permite que o administrador altere as senhas de acesso do
     * plugin base e dos add‑ons (como agenda). As senhas são armazenadas em
     * opções do WordPress. Esta seção é exibida apenas para usuários
     * autenticados.
     */
    private static function section_passwords() {
        // Obtém valores atuais das senhas
        $base_pass   = get_option( 'dps_base_password', 'DPS2025' );
        $agenda_pass = get_option( 'dps_agenda_password', 'agendaDPS' );
        ob_start();
        echo '<div class="dps-section" id="dps-section-senhas">';
        echo '<h3>' . esc_html__( 'Configuração de Senhas', 'dps-base' ) . '</h3>';
        echo '<form method="post" class="dps-form">';
        echo '<input type="hidden" name="dps_action" value="save_passwords">';
        wp_nonce_field( 'dps_action', 'dps_nonce' );
        // Senha do plugin base (admin)
        echo '<p><label>' . esc_html__( 'Senha do painel principal', 'dps-base' ) . '<br><input type="password" name="base_password" value="' . esc_attr( $base_pass ) . '" required></label></p>';
        // Senha da agenda
        echo '<p><label>' . esc_html__( 'Senha da agenda pública', 'dps-base' ) . '<br><input type="password" name="agenda_password" value="' . esc_attr( $agenda_pass ) . '" required></label></p>';
        // Permite add‑ons adicionarem seus próprios campos de senha
        do_action( 'dps_base_password_fields' );
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar Senhas', 'dps-base' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Salva cliente (inserção ou atualização)
     */
    private static function save_client() {
        $name      = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
        $cpf       = isset( $_POST['client_cpf'] ) ? sanitize_text_field( wp_unslash( $_POST['client_cpf'] ) ) : '';
        $phone     = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        $email     = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $birth     = isset( $_POST['client_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['client_birth'] ) ) : '';
        $insta     = isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '';
        $facebook  = isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '';
        $photo_auth= isset( $_POST['client_photo_auth'] ) ? 1 : 0;
        $address   = isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '';
        $referral  = isset( $_POST['client_referral'] ) ? sanitize_text_field( wp_unslash( $_POST['client_referral'] ) ) : '';
        // Coordenadas (podem estar vazias se não selecionadas)
        $lat       = isset( $_POST['client_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lat'] ) ) : '';
        $lng       = isset( $_POST['client_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lng'] ) ) : '';
        if ( empty( $name ) ) {
            return;
        }
        $client_id = isset( $_POST['client_id'] ) ? intval( wp_unslash( $_POST['client_id'] ) ) : 0;
        if ( $client_id ) {
            // Atualiza
            wp_update_post( [
                'ID'         => $client_id,
                'post_title' => $name,
            ] );
        } else {
            $client_id = wp_insert_post( [
                'post_type'   => 'dps_cliente',
                'post_title'  => $name,
                'post_status' => 'publish',
            ] );
        }
        if ( $client_id ) {
            update_post_meta( $client_id, 'client_cpf', $cpf );
            update_post_meta( $client_id, 'client_phone', $phone );
            update_post_meta( $client_id, 'client_email', $email );
            update_post_meta( $client_id, 'client_birth', $birth );
            update_post_meta( $client_id, 'client_instagram', $insta );
            update_post_meta( $client_id, 'client_facebook', $facebook );
            update_post_meta( $client_id, 'client_photo_auth', $photo_auth );
            update_post_meta( $client_id, 'client_address', $address );
            update_post_meta( $client_id, 'client_referral', $referral );
            // Salva coordenadas se fornecidas
            if ( $lat !== '' && $lng !== '' ) {
                update_post_meta( $client_id, 'client_lat', $lat );
                update_post_meta( $client_id, 'client_lng', $lng );
            }
        }
        // Redireciona para a aba de clientes
        wp_safe_redirect( self::get_redirect_url( 'clientes' ) );
        exit;
    }

    /**
     * Salva pet (inserção ou atualização)
     */
    private static function save_pet() {
        $owner_id  = isset( $_POST['owner_id'] ) ? intval( wp_unslash( $_POST['owner_id'] ) ) : 0;
        $name      = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
        $species   = isset( $_POST['pet_species'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_species'] ) ) : '';
        $breed     = isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '';
        $size      = isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '';
        $weight    = isset( $_POST['pet_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_weight'] ) ) : '';
        $coat      = isset( $_POST['pet_coat'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_coat'] ) ) : '';
        $color     = isset( $_POST['pet_color'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_color'] ) ) : '';
        $birth     = isset( $_POST['pet_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_birth'] ) ) : '';
        $sex       = isset( $_POST['pet_sex'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_sex'] ) ) : '';
        $care      = isset( $_POST['pet_care'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_care'] ) ) : '';
        $aggressive= isset( $_POST['pet_aggressive'] ) ? 1 : 0;
        // Campos adicionais para pets
        $vaccinations = isset( $_POST['pet_vaccinations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_vaccinations'] ) ) : '';
        $allergies    = isset( $_POST['pet_allergies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_allergies'] ) ) : '';
        $behavior     = isset( $_POST['pet_behavior'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior'] ) ) : '';
        if ( empty( $owner_id ) || empty( $name ) ) {
            return;
        }
        $pet_id = isset( $_POST['pet_id'] ) ? intval( wp_unslash( $_POST['pet_id'] ) ) : 0;
        if ( $pet_id ) {
            // Update
            wp_update_post( [
                'ID'         => $pet_id,
                'post_title' => $name,
            ] );
        } else {
            $pet_id = wp_insert_post( [
                'post_type'   => 'dps_pet',
                'post_title'  => $name,
                'post_status' => 'publish',
            ] );
        }
        if ( $pet_id ) {
            update_post_meta( $pet_id, 'owner_id', $owner_id );
            update_post_meta( $pet_id, 'pet_species', $species );
            update_post_meta( $pet_id, 'pet_breed', $breed );
            update_post_meta( $pet_id, 'pet_size', $size );
            update_post_meta( $pet_id, 'pet_weight', $weight );
            update_post_meta( $pet_id, 'pet_coat', $coat );
            update_post_meta( $pet_id, 'pet_color', $color );
            update_post_meta( $pet_id, 'pet_birth', $birth );
            update_post_meta( $pet_id, 'pet_sex', $sex );
            update_post_meta( $pet_id, 'pet_care', $care );
            update_post_meta( $pet_id, 'pet_aggressive', $aggressive );
            update_post_meta( $pet_id, 'pet_vaccinations', $vaccinations );
            update_post_meta( $pet_id, 'pet_allergies', $allergies );
            update_post_meta( $pet_id, 'pet_behavior', $behavior );
        }
        // Lida com upload da foto do pet, se houver
        if ( isset( $_FILES['pet_photo'] ) && ! empty( $_FILES['pet_photo']['name'] ) ) {
            $file = $_FILES['pet_photo'];
            // Carrega funções de upload do WordPress
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'wp_check_filetype' ) ) {
                require_once ABSPATH . 'wp-includes/functions.php';
            }
            $overrides = [ 'test_form' => false ];
            $uploaded  = wp_handle_upload( $file, $overrides );
            if ( isset( $uploaded['file'] ) && isset( $uploaded['type'] ) && empty( $uploaded['error'] ) ) {
                $filetype = wp_check_filetype( basename( $uploaded['file'] ), null );
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );
                if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                $attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
            }
        }
        // Redireciona para aba pets
        wp_safe_redirect( self::get_redirect_url( 'pets' ) );
        exit;
    }

    /**
     * Salva agendamento (inserção ou atualização)
     */
    private static function save_appointment() {
        $client_id = isset( $_POST['appointment_client_id'] ) ? intval( wp_unslash( $_POST['appointment_client_id'] ) ) : 0;
        // Recebe lista de pets (multi‑seleção). Pode ser array ou valor único.
        $raw_pets = isset( $_POST['appointment_pet_ids'] ) ? (array) wp_unslash( $_POST['appointment_pet_ids'] ) : [];
        $pet_ids  = [];
        foreach ( $raw_pets as $pid_raw ) {
            $pid = intval( $pid_raw );
            if ( $pid ) {
                $pet_ids[] = $pid;
            }
        }
        // Remove duplicados
        $pet_ids = array_values( array_unique( $pet_ids ) );

        // Define pet_id como o primeiro ID da lista para compatibilidade com lógica existente
        $pet_id = ! empty( $pet_ids ) ? $pet_ids[0] : 0;
        $date      = isset( $_POST['appointment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_date'] ) ) : '';
        $time      = isset( $_POST['appointment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_time'] ) ) : '';
        $notes     = isset( $_POST['appointment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['appointment_notes'] ) ) : '';
        // Novo: tipo de agendamento (simple ou subscription)
        $appt_type = isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple';
        // Frequência (apenas para assinaturas)
        $appt_freq = isset( $_POST['appointment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_frequency'] ) ) : '';
        $tosa      = isset( $_POST['appointment_tosa'] ) ? '1' : '0';
        // Preço e ocorrência da tosa: somente relevantes para assinaturas, mas definimos aqui para ter valores padrão
        $tosa_price      = 0;
        if ( isset( $_POST['appointment_tosa_price'] ) ) {
            $tosa_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_tosa_price'] ) ) );
            if ( $tosa_price < 0 ) {
                $tosa_price = 0;
            }
        }
        $tosa_occurrence = isset( $_POST['appointment_tosa_occurrence'] ) ? intval( wp_unslash( $_POST['appointment_tosa_occurrence'] ) ) : 1;
        $taxidog   = isset( $_POST['appointment_taxidog'] ) ? '1' : '0';
        // Valor do TaxiDog somente para agendamento simples; se vazio ou não numérico, trata como 0
        $taxi_price = 0;
        if ( 'simple' === $appt_type && $taxidog && isset( $_POST['appointment_taxidog_price'] ) ) {
            $taxi_price = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_taxidog_price'] ) ) );
            if ( $taxi_price < 0 ) {
                $taxi_price = 0;
            }
        }
        $extra_description = isset( $_POST['appointment_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_extra_description'] ) ) : '';
        $extra_value       = isset( $_POST['appointment_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_extra_value'] ) ) ) : 0;
        if ( $extra_value < 0 ) {
            $extra_value = 0;
        }
        $subscription_base_value  = isset( $_POST['subscription_base_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_base_value'] ) ) ) : 0;
        $subscription_total_value = isset( $_POST['subscription_total_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_total_value'] ) ) ) : 0;
        $subscription_extra_description = isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '';
        $subscription_extra_value       = isset( $_POST['subscription_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_extra_value'] ) ) ) : 0;
        if ( $subscription_extra_value < 0 ) {
            $subscription_extra_value = 0;
        }
        if ( empty( $client_id ) || empty( $pet_ids ) || empty( $date ) || empty( $time ) ) {
            return;
        }
        $appt_id = isset( $_POST['appointment_id'] ) ? intval( wp_unslash( $_POST['appointment_id'] ) ) : 0;

        /*
         * Caso seja uma nova assinatura e não esteja editando, cria uma assinatura para um ou mais pets.
         */
        if ( ! $appt_id && 'subscription' === $appt_type ) {
            // Define serviços padrão: Tosa higienica e Hidratação
            $service_names = [ 'Tosa higienica', 'Hidratação' ];
            $service_ids   = [];
            $prices        = [];
            foreach ( $service_names as $sname ) {
                $srv = get_posts( [
                    'post_type'      => 'dps_service',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'title'          => $sname,
                ] );
                if ( $srv ) {
                    $srv_id = $srv[0]->ID;
                    $service_ids[] = $srv_id;
                    $base_price    = (float) get_post_meta( $srv_id, 'service_price', true );
                    $prices[ $srv_id ] = $base_price;
                }
            }
            // Calcula preços de serviços base (valor de cada evento)
            $base_event_price = 0;
            foreach ( $prices as $p ) {
                $base_event_price += (float) $p;
            }
            // Define número de ocorrências no ciclo com base na frequência
            $count_events  = ( $appt_freq === 'quinzenal' ) ? 2 : 4;
            // Calcula valor do pacote por pet permitindo valores personalizados
            $base_cycle_value   = ( $subscription_base_value > 0 ) ? $subscription_base_value : ( $base_event_price * $count_events );
            $extra_cycle_value  = ( $subscription_extra_value > 0 ) ? $subscription_extra_value : 0;
            $package_per_pet    = $base_cycle_value + ( ( '1' === $tosa ) ? $tosa_price : 0 ) + $extra_cycle_value;
            $total_package      = $package_per_pet * count( $pet_ids );
            if ( $subscription_total_value > 0 ) {
                $total_package = $subscription_total_value;
                if ( count( $pet_ids ) > 0 ) {
                    $package_per_pet = $total_package / count( $pet_ids );
                }
            }
            // Cria post da assinatura
            $sub_id = wp_insert_post( [
                'post_type'   => 'dps_subscription',
                'post_title'  => $date . ' ' . $time . ' - ' . __( 'Assinatura', 'dps-base' ),
                'post_status' => 'publish',
            ] );
            if ( $sub_id ) {
                update_post_meta( $sub_id, 'subscription_client_id', $client_id );
                // Armazena o primeiro pet em subscription_pet_id para compatibilidade antiga
                update_post_meta( $sub_id, 'subscription_pet_id', $pet_ids[0] );
                // Novo: armazena lista de pets atendidos na assinatura
                update_post_meta( $sub_id, 'subscription_pet_ids', $pet_ids );
                update_post_meta( $sub_id, 'subscription_service', 'Assinatura' );
                update_post_meta( $sub_id, 'subscription_frequency', $appt_freq ?: 'semanal' );
                update_post_meta( $sub_id, 'subscription_price', $total_package );
                if ( $subscription_base_value > 0 ) {
                    update_post_meta( $sub_id, 'subscription_base_value', $subscription_base_value );
                }
                if ( $subscription_total_value > 0 ) {
                    update_post_meta( $sub_id, 'subscription_total_value', $subscription_total_value );
                }
                if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
                    update_post_meta( $sub_id, 'subscription_extra_description', $subscription_extra_description );
                    update_post_meta( $sub_id, 'subscription_extra_value', $subscription_extra_value );
                } else {
                    delete_post_meta( $sub_id, 'subscription_extra_description' );
                    delete_post_meta( $sub_id, 'subscription_extra_value' );
                }
                // Salva informações de tosa na assinatura
                update_post_meta( $sub_id, 'subscription_tosa', $tosa );
                update_post_meta( $sub_id, 'subscription_tosa_price', $tosa_price );
                update_post_meta( $sub_id, 'subscription_tosa_occurrence', $tosa_occurrence );
                update_post_meta( $sub_id, 'subscription_start_date', $date );
                update_post_meta( $sub_id, 'subscription_start_time', $time );
                update_post_meta( $sub_id, 'subscription_payment_status', 'pendente' );
                // Define quantas ocorrências no período (2 para quinzenal, 4 para semanal)
                $interval_days = ( $appt_freq === 'quinzenal' ) ? 14 : 7;
                $count_events  = ( $appt_freq === 'quinzenal' ) ? 2 : 4;
                // Para cada pet e para cada ocorrência, cria agendamento
                foreach ( $pet_ids as $p_id_each ) {
                    $current_dt = DateTime::createFromFormat( 'Y-m-d', $date );
                    for ( $i = 0; $i < $count_events; $i++ ) {
                        $date_i   = $current_dt->format( 'Y-m-d' );
                        $appt_new = wp_insert_post( [
                            'post_type'   => 'dps_agendamento',
                            'post_title'  => $date_i . ' ' . $time,
                            'post_status' => 'publish',
                        ] );
                        if ( $appt_new ) {
                    update_post_meta( $appt_new, 'appointment_client_id', $client_id );
                    update_post_meta( $appt_new, 'appointment_pet_id', $p_id_each );
                    update_post_meta( $appt_new, 'appointment_pet_ids', [ $p_id_each ] );
                    update_post_meta( $appt_new, 'appointment_date', $date_i );
                    update_post_meta( $appt_new, 'appointment_time', $time );
                    update_post_meta( $appt_new, 'appointment_notes', __( 'Serviço de assinatura', 'dps-base' ) );
                    update_post_meta( $appt_new, 'appointment_type', 'subscription' );
                    // Determina se este agendamento inclui tosa (somente uma vez por ciclo)
                    $is_tosa_event = ( '1' === $tosa && ( $i + 1 ) == $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_tosa', $is_tosa_event ? '1' : '0' );
                    update_post_meta( $appt_new, 'appointment_tosa_price', $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_tosa_occurrence', $tosa_occurrence );
                    update_post_meta( $appt_new, 'appointment_taxidog', $taxidog );
                    update_post_meta( $appt_new, 'appointment_taxidog_price', 0 );
                    update_post_meta( $appt_new, 'appointment_services', $service_ids );
                    update_post_meta( $appt_new, 'appointment_service_prices', $prices );
                    // Define valor total individual: preço de serviços base + preço da tosa apenas na ocorrência definida
                    $total_single = $base_event_price + ( $is_tosa_event ? $tosa_price : 0 );
                    update_post_meta( $appt_new, 'appointment_total_value', $total_single );
                    update_post_meta( $appt_new, 'appointment_status', 'pendente' );
                    update_post_meta( $appt_new, 'subscription_id', $sub_id );
                    // Dispara gancho pós‑salvamento para cada agendamento
                    do_action( 'dps_base_after_save_appointment', $appt_new, 'subscription' );
                        }
                        $current_dt->modify( '+' . $interval_days . ' days' );
                    }
                }
                // Registra transação financeira única para a assinatura (valor total de todos os pets)
                global $wpdb;
                $table     = $wpdb->prefix . 'dps_transacoes';
                $status_fin = 'em_aberto';
                $desc_fin  = sprintf( __( 'Assinatura: %s (%s)', 'dps-base' ), 'Assinatura', ( $appt_freq ?: 'semanal' ) );
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE plano_id = %d AND data = %s", $sub_id, $date ) );
                if ( $existing_id ) {
                    $wpdb->update( $table, [
                        'cliente_id' => $client_id ?: null,
                        'valor'      => (float) $total_package,
                        'status'     => $status_fin,
                        'categoria'  => __( 'Assinatura', 'dps-base' ),
                        'tipo'       => 'receita',
                        'descricao'  => $desc_fin,
                    ], [ 'id' => $existing_id ] );
                } else {
                    $wpdb->insert( $table, [
                        'cliente_id'     => $client_id ?: null,
                        'agendamento_id' => null,
                        'plano_id'       => $sub_id,
                        'data'           => $date,
                        'valor'          => (float) $total_package,
                        'categoria'      => __( 'Assinatura', 'dps-base' ),
                        'tipo'           => 'receita',
                        'status'         => $status_fin,
                        'descricao'      => $desc_fin,
                    ] );
                }
            }
            // Redireciona após salvar assinatura
            wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
            exit;
        }
        // Para agendamentos simples de múltiplos pets (novo), cria um agendamento para cada pet
        if ( ! $appt_id && 'simple' === $appt_type && count( $pet_ids ) > 1 ) {
            foreach ( $pet_ids as $p_id_each ) {
                $new_appt = wp_insert_post( [
                    'post_type'   => 'dps_agendamento',
                    'post_title'  => $date . ' ' . $time,
                    'post_status' => 'publish',
                ] );
                if ( $new_appt ) {
                    // Meta básicos
                    update_post_meta( $new_appt, 'appointment_client_id', $client_id );
                    update_post_meta( $new_appt, 'appointment_pet_id', $p_id_each );
                    update_post_meta( $new_appt, 'appointment_pet_ids', $pet_ids );
                    update_post_meta( $new_appt, 'appointment_date', $date );
                    update_post_meta( $new_appt, 'appointment_time', $time );
                    update_post_meta( $new_appt, 'appointment_notes', $notes );
                    update_post_meta( $new_appt, 'appointment_type', $appt_type );
                    update_post_meta( $new_appt, 'appointment_tosa', $tosa );
                    update_post_meta( $new_appt, 'appointment_taxidog', $taxidog );
                    update_post_meta( $new_appt, 'appointment_taxidog_price', $taxi_price );
                    // Serviços e total são definidos pelo add‑on de serviços; recuperamos total postado
                    $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
                    update_post_meta( $new_appt, 'appointment_total_value', $posted_total );
                    if ( '' !== $extra_description || $extra_value > 0 ) {
                        update_post_meta( $new_appt, 'appointment_extra_description', $extra_description );
                        update_post_meta( $new_appt, 'appointment_extra_value', $extra_value );
                    }
                    // Status inicial
                    update_post_meta( $new_appt, 'appointment_status', 'pendente' );
                    // Dispara gancho pós‑salvamento
                    do_action( 'dps_base_after_save_appointment', $new_appt, 'simple' );
                }
            }
            // Após criar todos os agendamentos, redireciona
            wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
            exit;
        }

        // Para agendamentos simples ou edição de qualquer tipo (único pet) continua com a lógica padrão
        if ( $appt_id ) {
            wp_update_post( [
                'ID'         => $appt_id,
                'post_title' => $date . ' ' . $time,
            ] );
        } else {
            $appt_id = wp_insert_post( [
                'post_type'   => 'dps_agendamento',
                'post_title'  => $date . ' ' . $time,
                'post_status' => 'publish',
            ] );
        }

        if ( $appt_id ) {
            update_post_meta( $appt_id, 'appointment_client_id', $client_id );
            update_post_meta( $appt_id, 'appointment_pet_id', $pet_id );
            update_post_meta( $appt_id, 'appointment_date', $date );
            update_post_meta( $appt_id, 'appointment_time', $time );
            update_post_meta( $appt_id, 'appointment_notes', $notes );
            // Salva tipo e flags adicionais
            update_post_meta( $appt_id, 'appointment_type', $appt_type );
            update_post_meta( $appt_id, 'appointment_tosa', $tosa );
            update_post_meta( $appt_id, 'appointment_taxidog', $taxidog );
            if ( 'simple' === $appt_type ) {
                update_post_meta( $appt_id, 'appointment_taxidog_price', $taxi_price );
            } else {
                // Nas assinaturas, valor do TaxiDog é cortesia
                update_post_meta( $appt_id, 'appointment_taxidog_price', 0 );
            }
        }
        // Lógica específica para agendamentos do tipo assinatura: define serviços padrão e total
        if ( $appt_id ) {
            if ( 'subscription' === $appt_type ) {
                // Serviços padrão: Tosa higienica (nome pode variar em maiúsculas/minúsculas) e Hidratação
                $service_names = [ 'Tosa higienica', 'Hidratação' ];
                $service_ids   = [];
                $prices        = [];
                foreach ( $service_names as $sname ) {
                    $srv = get_posts( [
                        'post_type'      => 'dps_service',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                        'title'          => $sname,
                    ] );
                    if ( $srv ) {
                        $srv_id = $srv[0]->ID;
                        $service_ids[] = $srv_id;
                        $base_price    = (float) get_post_meta( $srv_id, 'service_price', true );
                        $prices[ $srv_id ] = $base_price;
                    }
                }
                // Salva serviços selecionados e preços
                update_post_meta( $appt_id, 'appointment_services', $service_ids );
                update_post_meta( $appt_id, 'appointment_service_prices', $prices );
                // Define valor total: soma preços base dos serviços e adiciona o valor da tosa somente se marcada
                $base_total = 0;
                foreach ( $prices as $p ) {
                    $base_total += (float) $p;
                }
                $calculated_total = $base_total;
                if ( '1' === $tosa ) {
                    $calculated_total += $tosa_price;
                    // Registra preço da tosa para esta ocorrência
                    update_post_meta( $appt_id, 'appointment_tosa_price', $tosa_price );
                    update_post_meta( $appt_id, 'appointment_tosa_occurrence', 1 );
                } else {
                    update_post_meta( $appt_id, 'appointment_tosa_price', 0 );
                    update_post_meta( $appt_id, 'appointment_tosa_occurrence', 0 );
                }
                if ( $subscription_extra_value > 0 ) {
                    $calculated_total += $subscription_extra_value;
                }
                $final_subscription_total = $subscription_total_value > 0 ? $subscription_total_value : $calculated_total;
                update_post_meta( $appt_id, 'appointment_total_value', $final_subscription_total );
                if ( $subscription_base_value > 0 ) {
                    update_post_meta( $appt_id, 'subscription_base_value', $subscription_base_value );
                } elseif ( $base_total > 0 ) {
                    update_post_meta( $appt_id, 'subscription_base_value', $base_total );
                }
                if ( $subscription_total_value > 0 ) {
                    update_post_meta( $appt_id, 'subscription_total_value', $subscription_total_value );
                } else {
                    update_post_meta( $appt_id, 'subscription_total_value', $final_subscription_total );
                }
                if ( '' !== $subscription_extra_description || $subscription_extra_value > 0 ) {
                    update_post_meta( $appt_id, 'subscription_extra_description', $subscription_extra_description );
                    update_post_meta( $appt_id, 'subscription_extra_value', $subscription_extra_value );
                } else {
                    delete_post_meta( $appt_id, 'subscription_extra_description' );
                    delete_post_meta( $appt_id, 'subscription_extra_value' );
                }
                // Vincula a assinatura se existir para este cliente/pet
                $subs = get_posts( [
                    'post_type'      => 'dps_subscription',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [ 'key' => 'subscription_client_id', 'value' => $client_id, 'compare' => '=' ],
                        [ 'key' => 'subscription_pet_id', 'value' => $pet_id, 'compare' => '=' ],
                    ],
                ] );
                if ( $subs ) {
                    update_post_meta( $appt_id, 'subscription_id', $subs[0]->ID );
                }
            } else {
                // Agendamento simples: soma valor total dos serviços selecionados mais valor do TaxiDog
                // dps_service add-on salva appointment_total via POST; recupera
                $posted_total = isset( $_POST['appointment_total'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_total'] ) ) ) : 0;
                update_post_meta( $appt_id, 'appointment_total_value', $posted_total );
                if ( '' !== $extra_description || $extra_value > 0 ) {
                    update_post_meta( $appt_id, 'appointment_extra_description', $extra_description );
                    update_post_meta( $appt_id, 'appointment_extra_value', $extra_value );
                } else {
                    delete_post_meta( $appt_id, 'appointment_extra_description' );
                    delete_post_meta( $appt_id, 'appointment_extra_value' );
                }
            }
        }
        // Redireciona para aba agendas
        wp_safe_redirect( self::get_redirect_url( 'agendas' ) );
        exit;
    }

    /**
     * Salva as senhas do plugin base, agenda e outros add‑ons.
     *
     * Este método atualiza opções do WordPress com as novas senhas fornecidas.
     * Espera os campos 'base_password', 'agenda_password' e possivelmente outros
     * via $_POST. É executado somente por usuários autenticados (já verificado
     * em handle_request).
     */
    private static function save_passwords() {
        // Recebe dados
        $base_pass   = isset( $_POST['base_password'] ) ? sanitize_text_field( wp_unslash( $_POST['base_password'] ) ) : '';
        $agenda_pass = isset( $_POST['agenda_password'] ) ? sanitize_text_field( wp_unslash( $_POST['agenda_password'] ) ) : '';
        // Atualiza opções apenas se valores não vazios
        if ( $base_pass ) {
            update_option( 'dps_base_password', $base_pass );
        }
        if ( $agenda_pass ) {
            update_option( 'dps_agenda_password', $agenda_pass );
        }
        // Gancho para outras senhas de add‑ons (a função pode ser estendida)
        do_action( 'dps_base_save_passwords', wp_unslash( $_POST ) );
        // Redireciona para aba de senhas
        wp_safe_redirect( self::get_redirect_url( 'senhas' ) );
        exit;
    }

    /**
     * Exibe o formulário de acesso ao painel.
     *
     * Este formulário solicita apenas a senha de administração. Ao ser informada
     * corretamente, o usuário recebe permissão total de gerenciamento. Não há
     * opção de visitante: todos os acessos utilizam a mesma senha definida.
     *
     * @param string $error Mensagem de erro opcional
     * @return string HTML do formulário
     */
    private static function render_login_form( $error = '' ) {
        ob_start();
        echo '<div class="dps-login-wrapper">';
        echo '<h3>' . esc_html__( 'Acesso ao Desi Pet Shower', 'dps-base' ) . '</h3>';
        if ( $error ) {
            echo '<p class="dps-error" style="color:red;">' . esc_html( $error ) . '</p>';
        }
        echo '<form method="post" class="dps-login-form">';
        echo '<p><label>' . esc_html__( 'Senha', 'dps-base' ) . '<br><input type="password" name="dps_admin_pass" required></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Entrar', 'dps-base' ) . '</button></p>';
        echo '</form>';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Exibe a página de detalhes de um cliente e seu histórico de agendamentos
     * @param int $client_id ID do cliente
     * @return string HTML
     */
    private static function render_client_page( $client_id ) {
        $client = get_post( $client_id );
        if ( ! $client || $client->post_type !== 'dps_cliente' ) {
            return '<p>' . esc_html__( 'Cliente não encontrado.', 'dps-base' ) . '</p>';
        }

        // Mostra aviso se um histórico foi gerado e disponível para download
        if ( isset( $_GET['history_file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['history_file'] ) );
            $uploads = wp_upload_dir();
            $url  = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $file;
            echo '<div class="notice notice-success" style="padding:10px;margin-bottom:15px;border:1px solid #d4edda;background:#d4edda;color:#155724;">' . sprintf( esc_html__( 'Histórico gerado com sucesso. %sClique aqui para abrir%s.', 'dps-base' ), '<a href="' . esc_url( $url ) . '" target="_blank" style="font-weight:bold;">', '</a>' ) . '</div>';
        }

        // Antes de montar a página, trata requisições de geração, envio e exclusão de documentos.
        // 1. Gerar histórico: dps_client_history=1 cria um documento HTML do histórico. Se send_email=1,
        // ele será enviado ao email especificado (parâmetro to_email) ou ao email cadastrado do cliente.
        if ( isset( $_GET['dps_client_history'] ) && '1' === $_GET['dps_client_history'] ) {
            $doc_url = self::generate_client_history_doc( $client_id );
            if ( $doc_url ) {
                // Envio por email se solicitado
                if ( isset( $_GET['send_email'] ) && '1' === $_GET['send_email'] ) {
                    $to_email = isset( $_GET['to_email'] ) && is_email( sanitize_email( $_GET['to_email'] ) ) ? sanitize_email( $_GET['to_email'] ) : '';
                    self::send_client_history_email( $client_id, $doc_url, $to_email );
                    // Redireciona de volta com indicador de sucesso
                    $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'sent' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'sent' ] ) );
                    wp_redirect( $redirect );
                    exit;
                }
                // Caso contrário, redireciona para a própria página com parâmetro history_file para exibir aviso
                $file_name = basename( $doc_url );
                $redirect  = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'history_file' => $file_name ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'history_file' ] ) );
                wp_redirect( $redirect );
                exit;
            }
        }
        // 2. Exclusão de documentos: dps_delete_doc=1 com parâmetro file remove o arquivo específico.
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            self::delete_document( $file );
            // Redireciona sem os parâmetros
            $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_delete_doc', 'file' ] ) );
            wp_redirect( $redirect );
            exit;
        }
        // Obter metadados
        $meta = [
            'cpf'       => get_post_meta( $client_id, 'client_cpf', true ),
            'phone'     => get_post_meta( $client_id, 'client_phone', true ),
            'email'     => get_post_meta( $client_id, 'client_email', true ),
            'birth'     => get_post_meta( $client_id, 'client_birth', true ),
            'instagram' => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'  => get_post_meta( $client_id, 'client_facebook', true ),
            'photo_auth'=> get_post_meta( $client_id, 'client_photo_auth', true ),
            'address'   => get_post_meta( $client_id, 'client_address', true ),
            'referral'  => get_post_meta( $client_id, 'client_referral', true ),
        ];
        // Lista de pets deste cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        // Lista de agendamentos deste cliente, ordenado por data e hora
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );
        $base_url = get_permalink();
        ob_start();
        // Exibe mensagem de sucesso se enviada via email
        if ( isset( $_GET['sent'] ) && '1' === $_GET['sent'] ) {
            echo '<div class="dps-notice" style="padding:10px;background:#dff0d8;border:1px solid #d6e9c6;margin-bottom:10px;">Histórico enviado por email com sucesso.</div>';
        }
        echo '<div class="dps-client-detail">';
        echo '<p><a href="' . esc_url( remove_query_arg( [ 'dps_view', 'id', 'tab' ] ) ) . '">' . esc_html__( '← Voltar', 'dps-base' ) . '</a></p>';
        echo '<h3>' . esc_html( $client->post_title ) . '</h3>';
        echo '<ul class="dps-client-info">';
        // CPF
        echo '<li><strong>' . esc_html__( 'CPF:', 'dps-base' ) . '</strong> ' . ( $meta['cpf'] ? esc_html( $meta['cpf'] ) : '-' ) . '</li>';
        // Telefone/WhatsApp
        if ( $meta['phone'] ) {
            $phone_digits = preg_replace( '/\D+/', '', $meta['phone'] );
            $wa_url       = 'https://wa.me/' . $phone_digits;
            echo '<li><strong>' . esc_html__( 'Telefone:', 'dps-base' ) . '</strong> <a href="' . esc_url( $wa_url ) . '" target="_blank">' . esc_html( $meta['phone'] ) . '</a></li>';
        } else {
            echo '<li><strong>' . esc_html__( 'Telefone:', 'dps-base' ) . '</strong> -</li>';
        }
        // Email
        echo '<li><strong>Email:</strong> ' . ( $meta['email'] ? esc_html( $meta['email'] ) : '-' ) . '</li>';
        // Data de nascimento
        if ( $meta['birth'] ) {
            $birth_fmt = date_i18n( 'd-m-Y', strtotime( $meta['birth'] ) );
            echo '<li><strong>' . esc_html__( 'Nascimento:', 'dps-base' ) . '</strong> ' . esc_html( $birth_fmt ) . '</li>';
        } else {
            echo '<li><strong>' . esc_html__( 'Nascimento:', 'dps-base' ) . '</strong> -</li>';
        }
        // Instagram
        echo '<li><strong>Instagram:</strong> ' . ( $meta['instagram'] ? esc_html( $meta['instagram'] ) : '-' ) . '</li>';
        // Facebook
        echo '<li><strong>Facebook:</strong> ' . ( $meta['facebook'] ? esc_html( $meta['facebook'] ) : '-' ) . '</li>';
        // Autorização de publicação nas redes sociais
        $photo_auth_val = $meta['photo_auth'];
        $photo_label    = '';
        if ( '' !== $photo_auth_val && null !== $photo_auth_val ) {
            $photo_label = $photo_auth_val ? __( 'Sim', 'dps-base' ) : __( 'Não', 'dps-base' );
        }
        echo '<li><strong>' . esc_html__( 'Autorização de publicação nas redes sociais:', 'dps-base' ) . '</strong> ' . esc_html( $photo_label !== '' ? $photo_label : '-' ) . '</li>';
        // Endereço completo
        echo '<li><strong>' . esc_html__( 'Endereço:', 'dps-base' ) . '</strong> ' . esc_html( $meta['address'] ? $meta['address'] : '-' ) . '</li>';
        // Como nos conheceu
        echo '<li><strong>' . esc_html__( 'Como nos conheceu:', 'dps-base' ) . '</strong> ' . esc_html( $meta['referral'] ? $meta['referral'] : '-' ) . '</li>';
        echo '</ul>';
        // Pets do cliente
        echo '<h4>' . esc_html__( 'Pets', 'dps-base' ) . '</h4>';
        if ( $pets ) {
            // Tabela de pets com detalhes
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Foto', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Nome', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Espécie', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Raça', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Porte', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Peso', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Pelagem', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Cor', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Nascimento', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Sexo', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Cuidados', 'dps-base' ) . '</th>';
            echo '<th>' . esc_html__( 'Agressivo', 'dps-base' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $pets as $pet ) {
                // Foto do pet
                $photo_id  = get_post_meta( $pet->ID, 'pet_photo_id', true );
                $photo_html = '';
                if ( $photo_id ) {
                    // Obtém a URL da imagem em miniatura
                    $img_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
                    if ( $img_url ) {
                        $photo_html = '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" style="max-width:60px;height:auto;" />';
                    }
                }
                $species  = get_post_meta( $pet->ID, 'pet_species', true );
                $breed    = get_post_meta( $pet->ID, 'pet_breed', true );
                $size     = get_post_meta( $pet->ID, 'pet_size', true );
                $weight   = get_post_meta( $pet->ID, 'pet_weight', true );
                $coat     = get_post_meta( $pet->ID, 'pet_coat', true );
                $color    = get_post_meta( $pet->ID, 'pet_color', true );
                $birth    = get_post_meta( $pet->ID, 'pet_birth', true );
                $sex      = get_post_meta( $pet->ID, 'pet_sex', true );
                $care     = get_post_meta( $pet->ID, 'pet_care', true );
                $aggr     = get_post_meta( $pet->ID, 'pet_aggressive', true );
                // Translate codes
                switch ( $species ) {
                    case 'cao':
                        $species_label = __( 'Cachorro', 'dps-base' );
                        break;
                    case 'gato':
                        $species_label = __( 'Gato', 'dps-base' );
                        break;
                    case 'outro':
                        $species_label = __( 'Outro', 'dps-base' );
                        break;
                    default:
                        $species_label = $species;
                        break;
                }
                switch ( $size ) {
                    case 'pequeno':
                        $size_label = __( 'Pequeno', 'dps-base' );
                        break;
                    case 'medio':
                        $size_label = __( 'Médio', 'dps-base' );
                        break;
                    case 'grande':
                        $size_label = __( 'Grande', 'dps-base' );
                        break;
                    default:
                        $size_label = $size;
                        break;
                }
                switch ( $sex ) {
                    case 'macho':
                        $sex_label = __( 'Macho', 'dps-base' );
                        break;
                    case 'femea':
                        $sex_label = __( 'Fêmea', 'dps-base' );
                        break;
                    default:
                        $sex_label = $sex;
                        break;
                }
                $birth_formatted = $birth ? date_i18n( 'd-m-Y', strtotime( $birth ) ) : '';
                $aggr_label = $aggr ? __( 'Sim', 'dps-base' ) : __( 'Não', 'dps-base' );
                echo '<tr>';
                // Exibe foto ou marcador vazio
                echo '<td>' . ( $photo_html ? $photo_html : '-' ) . '</td>';
                echo '<td>' . esc_html( $pet->post_title ) . '</td>';
                echo '<td>' . esc_html( $species_label ) . '</td>';
                echo '<td>' . esc_html( $breed ) . '</td>';
                echo '<td>' . esc_html( $size_label ) . '</td>';
                echo '<td>' . esc_html( $weight ) . '</td>';
                echo '<td>' . esc_html( $coat ) . '</td>';
                echo '<td>' . esc_html( $color ) . '</td>';
                echo '<td>' . esc_html( $birth_formatted ) . '</td>';
                echo '<td>' . esc_html( $sex_label ) . '</td>';
                echo '<td>' . esc_html( $care ) . '</td>';
                echo '<td>' . esc_html( $aggr_label ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'dps-base' ) . '</p>';
        }
        // Link para gerar PDF/relatório do histórico
        $history_link = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );
        $email_base   = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1', 'send_email' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email' ] ) );
        echo '<p style="margin-top:15px;"><a href="' . esc_url( $history_link ) . '" class="button">' . esc_html__( 'Gerar histórico', 'dps-base' ) . '</a> ';
        // Botão de envio com prompt para email personalizado
        echo '<a href="#" class="button dps-send-history-email" data-base="' . esc_url( $email_base ) . '">' . esc_html__( 'Enviar histórico por email', 'dps-base' ) . '</a></p>';
        // Script para solicitar email e redirecionar
        echo '<script>(function($){$(document).on("click", ".dps-send-history-email", function(e){e.preventDefault();var base=$(this).data("base");var email=prompt("Para qual email deseja enviar? Deixe em branco para usar o email cadastrado.");if(email===null){return;}email=email.trim();var url=base; if(email){url += "&to_email=" + encodeURIComponent(email);} window.location.href=url;});})(jQuery);</script>';
        // Histórico de agendamentos
        echo '<h4>' . esc_html__( 'Histórico de Atendimentos', 'dps-base' ) . '</h4>';
        if ( $appointments ) {
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Data', 'dps-base' ) . '</th><th>' . esc_html__( 'Horário', 'dps-base' ) . '</th><th>' . esc_html__( 'Pet', 'dps-base' ) . '</th><th>' . esc_html__( 'Pagamento', 'dps-base' ) . '</th><th>' . esc_html__( 'Observações', 'dps-base' ) . '</th></tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                $date  = get_post_meta( $appt->ID, 'appointment_date', true );
                $time  = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id= get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet   = $pet_id ? get_post( $pet_id ) : null;
                $notes = get_post_meta( $appt->ID, 'appointment_notes', true );
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                // Determina status: pago ou pendente
                $status_label = '';
                if ( $status_meta === 'finalizado_pago' || $status_meta === 'finalizado e pago' ) {
                    $status_label = __( 'Pago', 'dps-base' );
                } elseif ( $status_meta === 'finalizado' ) {
                    $status_label = __( 'Pendente', 'dps-base' );
                } elseif ( $status_meta === 'cancelado' ) {
                    $status_label = __( 'Cancelado', 'dps-base' );
                } else {
                    // default
                    $status_label = __( 'Pendente', 'dps-base' );
                }
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                echo '<tr>';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $pet ? $pet->post_title : '-' ) . '</td>';
                echo '<td>' . esc_html( $status_label ) . '</td>';
                echo '<td>' . esc_html( $notes ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento encontrado.', 'dps-base' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Gera um arquivo HTML contendo o histórico de todos os atendimentos de um cliente.
     * O arquivo é salvo na pasta uploads/dps_docs e retorna a URL pública. Se já existir
     * um documento gerado recentemente (nas últimas 24 horas) ele será reutilizado.
     *
     * @param int $client_id
     * @return string|false URL do arquivo gerado ou false em caso de erro
     */
    private static function generate_client_history_doc( $client_id ) {
        // Busca appointments deste cliente
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );
        // Caminhos de upload
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        // Gera um nome de arquivo seguindo o padrão Historico_NOMEDOCLIENTE_NOMEDOPET_DATA.html
        $timestamp = current_time( 'timestamp' );
        // Obtém dados do cliente para formar o slug
        $client    = get_post( $client_id );
        $client_name  = $client ? $client->post_title : '';
        $client_slug  = sanitize_title( $client_name );
        $client_slug  = str_replace( '-', '_', $client_slug );
        // Obtém primeiro pet do cliente para incluir no nome, se existir
        $first_pet_slug = 'todos';
        $client_pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        if ( $client_pets ) {
            $first_pet_name = $client_pets[0]->post_title;
            $pet_slug       = sanitize_title( $first_pet_name );
            $pet_slug       = str_replace( '-', '_', $pet_slug );
            $first_pet_slug = $pet_slug;
        }
        $date_str = date_i18n( 'Y-m-d', $timestamp );
        $filename  = 'Historico_' . $client_slug . '_' . $first_pet_slug . '_' . $date_str . '.html';
        $filepath  = trailingslashit( $dir ) . $filename;
        $url       = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $filename;
        // O nome e o objeto do cliente já foram obtidos anteriormente para o slug.
        $client_email = get_post_meta( $client_id, 'client_email', true );
        // Construir HTML
        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Histórico de Atendimentos</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;color:#333;padding:20px;}';
        $html .= '.header{display:flex;align-items:center;margin-bottom:20px;}';
        $html .= '.header img{max-height:80px;margin-right:15px;}';
        $html .= '.header h2{margin:0;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:10px;}';
        $html .= 'th,td{border:1px solid #ccc;padding:8px;text-align:left;}';
        $html .= 'th{background:#f0f0f0;}';
        $html .= '</style></head><body>';
        // Cabeçalho com logo ou nome do site
        $html .= '<div class="header">';
        // Logo do tema se existir
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url_arr = wp_get_attachment_image_src( $logo_id, 'full' );
            if ( $logo_url_arr ) {
                $html .= '<img src="' . esc_url( $logo_url_arr[0] ) . '" alt="Logo">';
            }
        }
        $html .= '<div><h2>Histórico de Atendimentos</h2><p>Cliente: ' . esc_html( $client_name ) . '</p>';
        if ( $client_email ) {
            $html .= '<p>Email: ' . esc_html( $client_email ) . '</p>';
        }
        $html .= '<p>Data de geração: ' . date_i18n( 'd-m-Y H:i', $timestamp ) . '</p></div></div>';
        // Tabela de atendimentos
        $html .= '<table><thead><tr><th>Data</th><th>Horário</th><th>Pet</th><th>Serviços</th><th>Total (R$)</th><th>Status</th></tr></thead><tbody>';
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $time   = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet    = $pet_id ? get_post( $pet_id ) : null;
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                $prices   = get_post_meta( $appt->ID, 'appointment_service_prices', true );
                if ( ! is_array( $prices ) ) {
                    $prices = [];
                }
                // Monta lista de serviços e calcula total
                $service_lines = [];
                $total = 0.0;
                if ( is_array( $services ) ) {
                    foreach ( $services as $idx => $srv_id ) {
                        $srv = get_post( $srv_id );
                        $srv_name  = $srv ? $srv->post_title : '';
                        $price_val = isset( $prices[ $idx ] ) ? floatval( $prices[ $idx ] ) : 0.0;
                        $total    += $price_val;
                        $price_fmt = number_format( $price_val, 2, ',', '.' );
                        $service_lines[] = $srv_name . ' (R$ ' . $price_fmt . ')';
                    }
                }
                $services_str = $service_lines ? implode( ', ', $service_lines ) : '-';
                $total_fmt    = number_format( $total, 2, ',', '.' );
                // Status
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $status_label = '';
                if ( $status_meta === 'finalizado_pago' || $status_meta === 'finalizado e pago' ) {
                    $status_label = 'Pago';
                } elseif ( $status_meta === 'finalizado' ) {
                    $status_label = 'Pendente';
                } elseif ( $status_meta === 'cancelado' ) {
                    $status_label = 'Cancelado';
                } else {
                    $status_label = 'Pendente';
                }
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                $html .= '<tr><td>' . esc_html( $date_fmt ) . '</td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $pet ? $pet->post_title : '-' ) . '</td><td>' . esc_html( $services_str ) . '</td><td>' . esc_html( $total_fmt ) . '</td><td>' . esc_html( $status_label ) . '</td></tr>';
            }
        } else {
            $html .= '<tr><td colspan="6">Nenhum atendimento encontrado.</td></tr>';
        }
        $html .= '</tbody></table>';
        // Rodapé com dados da loja (informações fixas conforme solicitado)
        $html .= '<p style="margin-top:30px;font-size:12px;">Banho e Tosa Desi Pet Shower – Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $html .= '</body></html>';
        // Salva arquivo
        file_put_contents( $filepath, $html );
        return $url;
    }

    /**
     * Envia o histórico de atendimentos de um cliente por email, anexando o arquivo gerado
     * e incluindo um link para visualização.
     *
     * @param int    $client_id
     * @param string $doc_url URL do documento previamente gerado
     * @return void
     */
    private static function send_client_history_email( $client_id, $doc_url, $custom_email = '' ) {
        $client = get_post( $client_id );
        if ( ! $client ) {
            return;
        }
        // Determina email de destino: custom_email se fornecido e válido; caso contrário, email do cliente
        $default_to = get_post_meta( $client_id, 'client_email', true );
        $to = '';
        if ( $custom_email && is_email( $custom_email ) ) {
            $to = $custom_email;
        } elseif ( $default_to && is_email( $default_to ) ) {
            $to = $default_to;
        } else {
            return;
        }
        $name    = $client->post_title;
        $subject = 'Histórico de Atendimentos - ' . get_bloginfo( 'name' );
        // Lê conteúdo do documento para incorporar ao corpo do email
        $uploads  = wp_upload_dir();
        $file_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $doc_url );
        $body_html = '';
        if ( file_exists( $file_path ) ) {
            $body_html = file_get_contents( $file_path );
        }
        // Monta corpo com saudação e dados da loja
        $message  = '<p>Olá ' . esc_html( $name ) . ',</p>';
        $message .= '<p>Segue abaixo o histórico de atendimentos do seu pet:</p>';
        if ( $body_html ) {
            $message .= '<div style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">' . $body_html . '</div>';
        } else {
            $message .= '<p><a href="' . esc_url( $doc_url ) . '">Clique aqui para visualizar o histórico</a></p>';
        }
        // Dados da loja conforme solicitado
        $message .= '<p>Atenciosamente,<br>Banho e Tosa Desi Pet Shower<br>Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        // Anexa arquivo HTML
        $attachments = [];
        if ( file_exists( $file_path ) ) {
            $attachments[] = $file_path;
        }
        @wp_mail( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Exclui um documento (arquivo .html) da pasta dps_docs. Também remove quaisquer
     * opções que referenciem este arquivo (documentos financeiros ou históricos).
     *
     * @param string $filename Nome do arquivo a ser removido
     */
    private static function delete_document( $filename ) {
        if ( ! $filename ) {
            return;
        }
        $uploads = wp_upload_dir();
        $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $file_path = $doc_dir . '/' . basename( $filename );
        if ( file_exists( $file_path ) ) {
            @unlink( $file_path );
        }
        // Remover opções que apontam para este arquivo
        // Financeiro armazena URL em dps_fin_doc_{id} e base armazena nada específico, então busca geral
        // Verifica se alguma opção coincide com a URL
        $file_url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $filename );
        global $wpdb;
        // Busca opções que comecem com dps_fin_doc_
        $options = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" );
        if ( $options ) {
            foreach ( $options as $opt ) {
                $opt_val = get_option( $opt->option_name );
                if ( $opt_val === $file_url ) {
                    delete_option( $opt->option_name );
                }
            }
        }
    }
}