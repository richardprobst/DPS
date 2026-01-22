<?php
/**
 * Gerenciador de atualização de perfil via link
 *
 * Esta classe gerencia a funcionalidade de atualização de perfil do cliente
 * através de um link exclusivo que pode ser enviado ao cliente para que ele
 * atualize seus próprios dados e de seus pets.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Profile_Update' ) ) :

/**
 * Classe responsável pela atualização de perfil via link
 */
final class DPS_Portal_Profile_Update {

    /**
     * Única instância da classe
     *
     * @var DPS_Portal_Profile_Update|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton)
     *
     * @return DPS_Portal_Profile_Update
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para singleton
     */
    private function __construct() {
        // Adiciona botão ao header da página do cliente
        add_action( 'dps_client_page_header_actions', [ $this, 'render_update_link_button' ], 10, 3 );
        
        // Registra endpoint AJAX para gerar link
        add_action( 'wp_ajax_dps_generate_profile_update_link', [ $this, 'ajax_generate_link' ] );
        
        // Processa formulário de atualização (público via token)
        add_action( 'init', [ $this, 'handle_profile_update_form' ], 10 );
        
        // Adiciona shortcode para o formulário de atualização
        add_shortcode( 'dps_profile_update', [ $this, 'render_profile_update_shortcode' ] );
    }

    /**
     * Renderiza o botão de gerar link de atualização no header da página do cliente
     *
     * @param int     $client_id ID do cliente.
     * @param WP_Post $client    Objeto do post do cliente.
     * @param string  $base_url  URL base da página.
     */
    public function render_update_link_button( $client_id, $client, $base_url ) {
        // Verifica se o token manager está disponível
        if ( ! class_exists( 'DPS_Portal_Token_Manager' ) ) {
            return;
        }

        $nonce = wp_create_nonce( 'dps_generate_profile_update_' . $client_id );
        
        // Verifica se já existe um token gerado recentemente
        $existing_token = get_transient( 'dps_profile_update_token_' . $client_id );
        
        if ( $existing_token ) {
            // Mostra o link copiável
            echo '<div class="dps-profile-update-link-container">';
            echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-copy-link" data-link="' . esc_attr( $existing_token['url'] ) . '" title="' . esc_attr__( 'Clique para copiar', 'dps-client-portal' ) . '">';
            echo '📋 ' . esc_html__( 'Copiar Link', 'dps-client-portal' );
            echo '</button>';
            echo '<span class="dps-link-expires">' . esc_html__( 'Válido por 7 dias', 'dps-client-portal' ) . '</span>';
            echo '</div>';
        }
        
        // Botão para gerar novo link
        echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-generate-update-link" ';
        echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
        echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
        echo 'title="' . esc_attr__( 'Gerar link para o cliente atualizar seus dados', 'dps-client-portal' ) . '">';
        echo '🔗 ' . esc_html__( 'Link de Atualização', 'dps-client-portal' );
        echo '</button>';
        
        // Adiciona JavaScript inline para o botão
        $this->render_button_script();
    }

    /**
     * Renderiza o script JavaScript para o botão de gerar link
     */
    private function render_button_script() {
        static $script_rendered = false;
        
        if ( $script_rendered ) {
            return;
        }
        
        $script_rendered = true;
        ?>
        <script>
        (function() {
            // Handler para gerar link
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dps-generate-update-link')) return;
                
                var btn = e.target.closest('.dps-generate-update-link');
                var clientId = btn.dataset.clientId;
                var nonce = btn.dataset.nonce;
                
                btn.disabled = true;
                btn.innerHTML = '⏳ <?php echo esc_js( __( 'Gerando...', 'dps-client-portal' ) ); ?>';
                
                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=dps_generate_profile_update_link&client_id=' + clientId + '&_wpnonce=' + nonce
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    
                    if (data && data.success && data.data && data.data.url) {
                        // Copia para área de transferência
                        navigator.clipboard.writeText(data.data.url).then(function() {
                            btn.innerHTML = '✅ <?php echo esc_js( __( 'Link Copiado!', 'dps-client-portal' ) ); ?>';
                            
                            // Mostra mensagem de sucesso
                            var msg = document.createElement('div');
                            msg.className = 'dps-alert dps-alert--success';
                            msg.innerHTML = '<?php echo esc_js( __( 'Link de atualização gerado e copiado! Envie para o cliente via WhatsApp ou Email.', 'dps-client-portal' ) ); ?>' +
                                '<br><strong><?php echo esc_js( __( 'Válido por 7 dias.', 'dps-client-portal' ) ); ?></strong>' +
                                '<br><code style="word-break: break-all;">' + data.data.url + '</code>';
                            
                            var header = document.querySelector('.dps-client-header');
                            if (header) {
                                header.parentNode.insertBefore(msg, header.nextSibling);
                            }
                            
                            setTimeout(function() {
                                btn.innerHTML = '🔗 <?php echo esc_js( __( 'Link de Atualização', 'dps-client-portal' ) ); ?>';
                            }, 3000);
                        }).catch(function() {
                            // Fallback: mostra em prompt
                            prompt('<?php echo esc_js( __( 'Copie o link abaixo:', 'dps-client-portal' ) ); ?>', data.data.url);
                            btn.innerHTML = '🔗 <?php echo esc_js( __( 'Link de Atualização', 'dps-client-portal' ) ); ?>';
                        });
                    } else {
                        var errorMsg = (data && data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Erro ao gerar link. Tente novamente.', 'dps-client-portal' ) ); ?>';
                        alert(errorMsg);
                        btn.innerHTML = '🔗 <?php echo esc_js( __( 'Link de Atualização', 'dps-client-portal' ) ); ?>';
                    }
                })
                .catch(function(error) {
                    btn.disabled = false;
                    btn.innerHTML = '🔗 <?php echo esc_js( __( 'Link de Atualização', 'dps-client-portal' ) ); ?>';
                    alert('<?php echo esc_js( __( 'Erro de conexão. Verifique sua internet e tente novamente.', 'dps-client-portal' ) ); ?>');
                });
            });
            
            // Handler para copiar link existente
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dps-copy-link')) return;
                
                var btn = e.target.closest('.dps-copy-link');
                var link = btn.dataset.link;
                
                navigator.clipboard.writeText(link).then(function() {
                    var originalText = btn.innerHTML;
                    btn.innerHTML = '✅ <?php echo esc_js( __( 'Copiado!', 'dps-client-portal' ) ); ?>';
                    setTimeout(function() {
                        btn.innerHTML = originalText;
                    }, 2000);
                }).catch(function() {
                    prompt('<?php echo esc_js( __( 'Copie o link abaixo:', 'dps-client-portal' ) ); ?>', link);
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Gera link de atualização de perfil
     */
    public function ajax_generate_link() {
        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        
        // Verifica nonce
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_generate_profile_update_' . $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-client-portal' ) ] );
        }
        
        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para executar esta ação.', 'dps-client-portal' ) ] );
        }
        
        // Valida cliente
        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido.', 'dps-client-portal' ) ] );
        }
        
        // Gera token
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_plain = $token_manager->generate_token( $client_id, 'profile_update' );
        
        if ( false === $token_plain ) {
            wp_send_json_error( [ 'message' => __( 'Não foi possível gerar o link.', 'dps-client-portal' ) ] );
        }
        
        // Gera URL - usa a página do portal com parâmetro especial
        $portal_url = dps_get_portal_page_url();
        $update_url = add_query_arg( [
            'dps_action' => 'profile_update',
            'token'      => $token_plain,
        ], $portal_url );
        
        // Armazena temporariamente para exibição
        set_transient( 'dps_profile_update_token_' . $client_id, [
            'token' => $token_plain,
            'url'   => $update_url,
        ], 7 * DAY_IN_SECONDS );
        
        // Log da ação
        do_action( 'dps_portal_profile_update_link_generated', $client_id, $update_url );
        
        wp_send_json_success( [
            'url'     => $update_url,
            'message' => __( 'Link gerado com sucesso!', 'dps-client-portal' ),
        ] );
    }

    /**
     * Processa o formulário de atualização de perfil
     */
    public function handle_profile_update_form() {
        // Verifica se é uma submissão de formulário de atualização
        if ( ! isset( $_POST['dps_profile_update_nonce'] ) || ! isset( $_POST['dps_profile_token'] ) ) {
            return;
        }
        
        $token = sanitize_text_field( wp_unslash( $_POST['dps_profile_token'] ) );
        
        // Valida o token
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_data = $token_manager->validate_token( $token );
        
        if ( ! $token_data || 'profile_update' !== $token_data['type'] ) {
            // Token inválido ou tipo errado
            DPS_Message_Helper::add_error( __( 'Link inválido ou expirado. Solicite um novo link ao administrador.', 'dps-client-portal' ) );
            return;
        }
        
        $client_id = absint( $token_data['client_id'] );
        
        // Verifica nonce do formulário
        $nonce = sanitize_text_field( wp_unslash( $_POST['dps_profile_update_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'dps_profile_update_' . $client_id ) ) {
            DPS_Message_Helper::add_error( __( 'Falha na verificação de segurança. Tente novamente.', 'dps-client-portal' ) );
            return;
        }
        
        // Processa atualização do cliente
        $this->process_client_update( $client_id );
        
        // Processa atualização dos pets
        $this->process_pets_update( $client_id );
        
        // Processa adição de novos pets
        $this->process_new_pets( $client_id );
        
        // Adiciona mensagem de sucesso
        DPS_Message_Helper::add_success( __( 'Seus dados foram atualizados com sucesso! Obrigado.', 'dps-client-portal' ) );
        
        // Log da atualização
        do_action( 'dps_portal_profile_updated', $client_id );
    }

    /**
     * Processa atualização dos dados do cliente
     *
     * @param int $client_id ID do cliente.
     */
    private function process_client_update( $client_id ) {
        // Nome do cliente
        if ( isset( $_POST['client_name'] ) ) {
            $name = sanitize_text_field( wp_unslash( $_POST['client_name'] ) );
            if ( ! empty( $name ) ) {
                wp_update_post( [
                    'ID'         => $client_id,
                    'post_title' => $name,
                ] );
            }
        }
        
        // Campos de metadados
        $meta_fields = [
            'client_cpf'        => 'cpf',
            'client_birth'      => 'birth',
            'client_phone'      => 'phone',
            'client_email'      => 'email',
            'client_instagram'  => 'instagram',
            'client_facebook'   => 'facebook',
            'client_address'    => 'address',
            'client_referral'   => 'referral',
            'client_photo_auth' => 'photo_auth',
        ];
        
        foreach ( $meta_fields as $post_key => $meta_suffix ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                $value = wp_unslash( $_POST[ $post_key ] );
                
                // Sanitização específica por tipo
                switch ( $meta_suffix ) {
                    case 'email':
                        $value = sanitize_email( $value );
                        break;
                    case 'address':
                        $value = sanitize_textarea_field( $value );
                        break;
                    case 'photo_auth':
                        $value = ! empty( $value ) ? '1' : '';
                        break;
                    default:
                        $value = sanitize_text_field( $value );
                }
                
                update_post_meta( $client_id, 'client_' . $meta_suffix, $value );
            }
        }
    }

    /**
     * Processa atualização dos pets existentes
     *
     * @param int $client_id ID do cliente.
     */
    private function process_pets_update( $client_id ) {
        if ( ! isset( $_POST['pets'] ) || ! is_array( $_POST['pets'] ) ) {
            return;
        }
        
        $pets_data = wp_unslash( $_POST['pets'] );
        
        foreach ( $pets_data as $pet_id => $pet_data ) {
            $pet_id = absint( $pet_id );
            
            // Verifica se o pet pertence ao cliente
            $owner_id = get_post_meta( $pet_id, 'owner_id', true );
            if ( absint( $owner_id ) !== $client_id ) {
                continue;
            }
            
            // Atualiza nome do pet
            if ( isset( $pet_data['name'] ) && ! empty( $pet_data['name'] ) ) {
                wp_update_post( [
                    'ID'         => $pet_id,
                    'post_title' => sanitize_text_field( $pet_data['name'] ),
                ] );
            }
            
            // Campos de metadados do pet
            $pet_meta_fields = [
                'species'    => 'pet_species',
                'breed'      => 'pet_breed',
                'sex'        => 'pet_sex',
                'size'       => 'pet_size',
                'weight'     => 'pet_weight',
                'birth'      => 'pet_birth',
                'coat'       => 'pet_coat',
                'color'      => 'pet_color',
                'care'       => 'pet_care',
                'aggressive' => 'pet_aggressive',
            ];
            
            foreach ( $pet_meta_fields as $data_key => $meta_key ) {
                if ( isset( $pet_data[ $data_key ] ) ) {
                    $value = sanitize_text_field( $pet_data[ $data_key ] );
                    
                    // Tratamento especial para checkbox
                    if ( 'aggressive' === $data_key ) {
                        $value = ! empty( $value ) ? '1' : '';
                    }
                    
                    update_post_meta( $pet_id, $meta_key, $value );
                }
            }
        }
    }

    /**
     * Processa adição de novos pets
     *
     * @param int $client_id ID do cliente.
     */
    private function process_new_pets( $client_id ) {
        if ( ! isset( $_POST['new_pets'] ) || ! is_array( $_POST['new_pets'] ) ) {
            return;
        }
        
        $new_pets = wp_unslash( $_POST['new_pets'] );
        
        foreach ( $new_pets as $pet_data ) {
            // Valida dados mínimos
            if ( empty( $pet_data['name'] ) || empty( $pet_data['species'] ) ) {
                continue;
            }
            
            // Cria o pet
            $pet_id = wp_insert_post( [
                'post_type'   => 'dps_pet',
                'post_status' => 'publish',
                'post_title'  => sanitize_text_field( $pet_data['name'] ),
            ] );
            
            if ( is_wp_error( $pet_id ) || ! $pet_id ) {
                continue;
            }
            
            // Associa ao cliente
            update_post_meta( $pet_id, 'owner_id', $client_id );
            
            // Campos de metadados
            $pet_meta_fields = [
                'species'    => 'pet_species',
                'breed'      => 'pet_breed',
                'sex'        => 'pet_sex',
                'size'       => 'pet_size',
                'weight'     => 'pet_weight',
                'birth'      => 'pet_birth',
                'coat'       => 'pet_coat',
                'color'      => 'pet_color',
                'care'       => 'pet_care',
                'aggressive' => 'pet_aggressive',
            ];
            
            foreach ( $pet_meta_fields as $data_key => $meta_key ) {
                if ( isset( $pet_data[ $data_key ] ) && ! empty( $pet_data[ $data_key ] ) ) {
                    $value = sanitize_text_field( $pet_data[ $data_key ] );
                    
                    // Tratamento especial para checkbox
                    if ( 'aggressive' === $data_key ) {
                        $value = ! empty( $value ) ? '1' : '';
                    }
                    
                    update_post_meta( $pet_id, $meta_key, $value );
                }
            }
            
            // Log do novo pet
            do_action( 'dps_portal_new_pet_created', $pet_id, $client_id );
        }
    }

    /**
     * Renderiza o shortcode do formulário de atualização de perfil
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML do formulário.
     */
    public function render_profile_update_shortcode( $atts ) {
        // Este shortcode é usado internamente pelo portal
        // Verifica se há token válido na URL
        if ( ! isset( $_GET['dps_action'] ) || 'profile_update' !== $_GET['dps_action'] || ! isset( $_GET['token'] ) ) {
            return '';
        }
        
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
        
        // Valida o token
        $token_manager = DPS_Portal_Token_Manager::get_instance();
        $token_data = $token_manager->validate_token( $token );
        
        if ( ! $token_data ) {
            return $this->render_error_message( __( 'Este link não é mais válido. Por favor, solicite um novo link ao administrador.', 'dps-client-portal' ) );
        }
        
        if ( 'profile_update' !== $token_data['type'] ) {
            return $this->render_error_message( __( 'Link inválido. Este não é um link de atualização de perfil.', 'dps-client-portal' ) );
        }
        
        $client_id = absint( $token_data['client_id'] );
        $client = get_post( $client_id );
        
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return $this->render_error_message( __( 'Cliente não encontrado.', 'dps-client-portal' ) );
        }
        
        // Carrega template do formulário
        ob_start();
        $this->render_update_form( $client, $token );
        return ob_get_clean();
    }

    /**
     * Renderiza mensagem de erro
     *
     * @param string $message Mensagem de erro.
     * @return string HTML da mensagem.
     */
    private function render_error_message( $message ) {
        return '<div class="dps-profile-update-error">
            <div class="dps-alert dps-alert--error">
                <p>' . esc_html( $message ) . '</p>
            </div>
        </div>';
    }

    /**
     * Renderiza o formulário de atualização
     *
     * @param WP_Post $client Objeto do cliente.
     * @param string  $token  Token de autenticação.
     */
    private function render_update_form( $client, $token ) {
        $client_id = $client->ID;
        
        // Carrega metadados do cliente
        $meta = [
            'cpf'        => get_post_meta( $client_id, 'client_cpf', true ),
            'birth'      => get_post_meta( $client_id, 'client_birth', true ),
            'phone'      => get_post_meta( $client_id, 'client_phone', true ),
            'email'      => get_post_meta( $client_id, 'client_email', true ),
            'instagram'  => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'   => get_post_meta( $client_id, 'client_facebook', true ),
            'address'    => get_post_meta( $client_id, 'client_address', true ),
            'referral'   => get_post_meta( $client_id, 'client_referral', true ),
            'photo_auth' => get_post_meta( $client_id, 'client_photo_auth', true ),
        ];
        
        // Carrega pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        
        // Pré-carrega metadados dos pets
        if ( $pets ) {
            $pet_ids = wp_list_pluck( $pets, 'ID' );
            update_meta_cache( 'post', $pet_ids );
        }
        
        // Carrega dados das raças
        $breed_data = [];
        if ( function_exists( 'dps_get_breed_data' ) ) {
            $breed_data = dps_get_breed_data();
        }
        
        // Carrega o template
        include DPS_CLIENT_PORTAL_ADDON_DIR . 'templates/profile-update-form.php';
    }
}

endif;
