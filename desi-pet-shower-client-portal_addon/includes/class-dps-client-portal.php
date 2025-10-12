<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega a classe somente se ainda não existir.
if ( ! class_exists( 'DPS_Client_Portal' ) ) :

/**
 * Classe responsável por fornecer o portal do cliente.  Implementa:
 * - Criação automática de usuário WordPress ao cadastrar cliente.
 * - Shortcode para renderizar a área do cliente com histórico, fotos, pendências e formulários.
 * - Geração de links de pagamento para pendências usando a API do Mercado Pago.
 * - Atualização de dados do cliente e dos pets a partir do portal.
 */
final class DPS_Client_Portal {

    /**
     * Única instância da classe.
     *
     * @var DPS_Client_Portal|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Client_Portal
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor. Registra ganchos necessários para o funcionamento do portal.
     */
    private function __construct() {
        // Inicia sessão para autenticar clientes sem utilizar o sistema de usuários do WordPress
        add_action( 'init', function() {
            if ( ! session_id() ) {
                // Start PHP session so we can track logged‑in clients independent of WP users.
                session_start();
            }
        }, 1 );

        // Cria login para novo cliente ao salvar post do tipo dps_cliente
        add_action( 'save_post_dps_cliente', [ $this, 'maybe_create_login_for_client' ], 10, 3 );

        // Adiciona shortcode para o portal
        add_shortcode( 'dps_client_portal', [ $this, 'render_portal_shortcode' ] );
        // Adiciona shortcode para o formulário de login
        add_shortcode( 'dps_client_login', [ $this, 'render_login_shortcode' ] );

        // Processa ações de atualização do portal e login/logout
        add_action( 'init', [ $this, 'handle_portal_actions' ] );

        // --- MUDANÇAS AQUI ---
        // Remove o menu do admin e adiciona abas no front-end
        // add_action( 'admin_menu', [ $this, 'register_client_logins_page' ] ); // Comentamos ou removemos esta linha
        add_action( 'dps_base_nav_tabs', [ $this, 'render_logins_tab' ] );
        add_action( 'dps_base_sections', [ $this, 'render_logins_section' ] );
    }

    /**
     * Cria um usuário WordPress para um cliente recém-cadastrado, se ainda não existir.
     * Este usuário é do tipo "assinante" e recebe login e senha enviados por email.
     *
     * @param int     $post_id ID do post do cliente.
     * @param WP_Post $post    Objeto de post.
     * @param bool    $update  Indica se é atualização (true) ou criação (false).
     */
    /**
     * Gera um login próprio para o cliente baseado no telefone informado no cadastro.
     * Ao criar um cliente (não atualização), se o cliente tiver telefone, é criada
     * uma senha aleatória, armazenada como hash no meta 'client_password_hash'.
     * Esta senha não é enviada automaticamente, mas pode ser consultada ou redefinida
     * pela administração. Também marca uma flag para indicar que o login já foi criado.
     *
     * @param int     $post_id ID do post do cliente.
     * @param WP_Post $post    Objeto de post.
     * @param bool    $update  Indica se é atualização (true) ou criação (false).
     */
    public function maybe_create_login_for_client( $post_id, $post, $update ) {
        // Apenas na criação do cliente
        if ( $update ) {
            return;
        }
        // Evita auto‑saves ou revisões
        if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
            return;
        }
        // Verifica se já existe senha criada
        $existing_hash = get_post_meta( $post_id, 'client_password_hash', true );
        if ( ! empty( $existing_hash ) ) {
            return;
        }
        // Recupera telefone do cliente
        $phone = sanitize_text_field( get_post_meta( $post_id, 'client_phone', true ) );
        if ( ! $phone ) {
            return;
        }
        // Gera uma senha aleatória de 8 caracteres (sem símbolos para facilitar digitação)
        $plain  = wp_generate_password( 8, false, false );
        // Gera hash usando as funções do WordPress
        $hash   = wp_hash_password( $plain );
        // Armazena hash no meta
        update_post_meta( $post_id, 'client_password_hash', $hash );
        // Opcional: armazenar o telefone em um meta separado para login (já existe client_phone)
        // Marca data de criação do login
        update_post_meta( $post_id, 'client_login_created_at', current_time( 'mysql' ) );
        // Salva a senha temporariamente em meta transiente para visualização imediata pela administração
        // Transiente expira em 30 minutos
        set_transient( 'dps_client_pass_' . $post_id, $plain, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * Processa requisições de formulários enviados pelo portal do cliente.
     * Utiliza nonce para proteção CSRF e atualiza metas conforme necessário.
     */
    public function handle_portal_actions() {
        // Para processar ações do portal, o cliente deve estar logado via sessão customizada
        if ( ! isset( $_SESSION['dps_client_id'] ) || ! $_SESSION['dps_client_id'] ) {
            return;
        }
        if ( empty( $_POST['dps_client_portal_action'] ) ) {
            return;
        }
        // Verifica nonce de segurança
        if ( ! isset( $_POST['_dps_client_portal_nonce'] ) || ! wp_verify_nonce( $_POST['_dps_client_portal_nonce'], 'dps_client_portal_action' ) ) {
            return;
        }
        $action    = sanitize_key( $_POST['dps_client_portal_action'] );
        $client_id = intval( $_SESSION['dps_client_id'] );
        if ( ! $client_id ) {
            return;
        }
        // Processa atualizações de dados do cliente
        if ( 'update_client_info' === $action ) {
            // Telefone
            $phone = sanitize_text_field( $_POST['client_phone'] ?? '' );
            update_post_meta( $client_id, 'client_phone', $phone );
            // Endereço completo
            $address = sanitize_textarea_field( $_POST['client_address'] ?? '' );
            update_post_meta( $client_id, 'client_address', $address );
            // Instagram
            $insta = sanitize_text_field( $_POST['client_instagram'] ?? '' );
            update_post_meta( $client_id, 'client_instagram', $insta );
            // Facebook
            $fb = sanitize_text_field( $_POST['client_facebook'] ?? '' );
            update_post_meta( $client_id, 'client_facebook', $fb );
            // Email (só atualiza se diferente e válido)
            $email = sanitize_email( $_POST['client_email'] ?? '' );
            if ( $email && is_email( $email ) ) {
                // Atualiza o email no meta do cliente; não há usuário WordPress associado
                update_post_meta( $client_id, 'client_email', $email );
            }
        }
        // Processa atualizações de pet (um por vez)
        if ( 'update_pet' === $action && isset( $_POST['pet_id'] ) ) {
            $pet_id = intval( $_POST['pet_id'] );
            // Verifica se o pet pertence ao cliente
            $owner_id = intval( get_post_meta( $pet_id, 'owner_id', true ) );
            if ( $owner_id === $client_id ) {
                // Atualiza dados básicos do pet
                $pet_name   = sanitize_text_field( $_POST['pet_name'] ?? '' );
                $species    = sanitize_text_field( $_POST['pet_species'] ?? '' );
                $breed      = sanitize_text_field( $_POST['pet_breed'] ?? '' );
                $size       = sanitize_text_field( $_POST['pet_size'] ?? '' );
                $weight     = sanitize_text_field( $_POST['pet_weight'] ?? '' );
                $coat       = sanitize_text_field( $_POST['pet_coat'] ?? '' );
                $color      = sanitize_text_field( $_POST['pet_color'] ?? '' );
                $birth      = sanitize_text_field( $_POST['pet_birth'] ?? '' );
                $sex        = sanitize_text_field( $_POST['pet_sex'] ?? '' );
                $vacc       = sanitize_textarea_field( $_POST['pet_vaccinations'] ?? '' );
                $allergies  = sanitize_textarea_field( $_POST['pet_allergies'] ?? '' );
                $behavior   = sanitize_textarea_field( $_POST['pet_behavior'] ?? '' );
                // Atualiza título do post (nome do pet)
                if ( $pet_name ) {
                    wp_update_post( [ 'ID' => $pet_id, 'post_title' => $pet_name ] );
                }
                // Atualiza metas
                update_post_meta( $pet_id, 'pet_species', $species );
                update_post_meta( $pet_id, 'pet_breed', $breed );
                update_post_meta( $pet_id, 'pet_size', $size );
                update_post_meta( $pet_id, 'pet_weight', $weight );
                update_post_meta( $pet_id, 'pet_coat', $coat );
                update_post_meta( $pet_id, 'pet_color', $color );
                update_post_meta( $pet_id, 'pet_birth', $birth );
                update_post_meta( $pet_id, 'pet_sex', $sex );
                update_post_meta( $pet_id, 'pet_vaccinations', $vacc );
                update_post_meta( $pet_id, 'pet_allergies', $allergies );
                update_post_meta( $pet_id, 'pet_behavior', $behavior );
                // Lida com upload de foto
                if ( ! empty( $_FILES['pet_photo']['name'] ) ) {
                    $file = $_FILES['pet_photo'];
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    // Verifica e faz upload
                    $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
                    if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
                        // Insere como anexo
                        $file_path  = $upload['file'];
                        $file_name  = basename( $file_path );
                        $file_type  = wp_check_filetype( $file_name, null );
                        $attachment = [
                            'post_title'     => sanitize_file_name( $file_name ),
                            'post_mime_type' => $file_type['type'],
                            'post_status'    => 'inherit',
                        ];
                        $attach_id = wp_insert_attachment( $attachment, $file_path );
                        // Gera metadados de imagem
                        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                        wp_update_attachment_metadata( $attach_id, $attach_data );
                        update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
                    }
                }
            }
        }
        // Processa geração de link de pagamento para pendência
        if ( 'pay_transaction' === $action && isset( $_POST['trans_id'] ) ) {
            $trans_id = intval( $_POST['trans_id'] );
            $redirect = add_query_arg( 'portal_msg', 'error', home_url() );
            $link     = $this->generate_payment_link_for_transaction( $trans_id );
            if ( $link ) {
                // Redireciona para o link de pagamento
                wp_redirect( $link );
                exit;
            }
            wp_redirect( $redirect );
            exit;
        }
        // Após processar, redireciona para o portal para evitar repost
        $url = remove_query_arg( [ 'portal_msg' ] );
        wp_redirect( $url );
        exit;
    }

    /**
     * Renderiza a página do portal para o shortcode.  Mostra login form se usuário não estiver logado.
     *
     * @return string Conteúdo HTML renderizado.
     */
    public function render_portal_shortcode() {
        ob_start();
        // Se não houver sessão de cliente, exibe o formulário de login personalizado
        if ( ! isset( $_SESSION['dps_client_id'] ) || ! $_SESSION['dps_client_id'] ) {
            echo '<div class="dps-client-portal-login">';
            echo '<h3>' . esc_html__( 'Acesso ao Portal do Cliente', 'dps-client-portal' ) . '</h3>';
            echo do_shortcode( '[dps_client_login]' );
            echo '</div>';
            return ob_get_clean();
        }
        $client_id = intval( $_SESSION['dps_client_id'] );
        if ( ! $client_id ) {
            echo '<p>' . esc_html__( 'Nenhum cadastro de cliente foi encontrado para sua conta.', 'dps-client-portal' ) . '</p>';
            return ob_get_clean();
        }
        // Filtro de mensagens de retorno
        if ( isset( $_GET['portal_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['portal_msg'] );
            if ( 'updated' === $msg ) {
                echo '<div class="notice notice-success">' . esc_html__( 'Dados atualizados com sucesso.', 'dps-client-portal' ) . '</div>';
            } elseif ( 'error' === $msg ) {
                echo '<div class="notice notice-error">' . esc_html__( 'Ocorreu um erro ao processar sua solicitação.', 'dps-client-portal' ) . '</div>';
            }
        }
        echo '<div class="dps-client-portal">';
        echo '<h2>' . esc_html__( 'Bem-vindo ao Portal do Cliente', 'dps-client-portal' ) . '</h2>';
        // Renderiza seções utilizando o ID do cliente
        $this->render_next_appointment( $client_id );
        $this->render_financial_pending( $client_id );
        $this->render_appointment_history( $client_id );
        $this->render_pet_gallery( $client_id );
        $this->render_update_forms( $client_id );
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderiza seção do próximo agendamento.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_next_appointment( $client_id ) {
        echo '<section class="dps-portal-section dps-portal-next">';
        echo '<h3>' . esc_html__( 'Próximo Agendamento', 'dps-client-portal' ) . '</h3>';
        $today     = current_time( 'Y-m-d' );
        $args      = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'ASC',
        ];
        $appointments = get_posts( $args );
        $next         = null;
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                // Considera apenas status pendentes e datas futuras ou hoje
                if ( $date && strtotime( $date ) >= strtotime( $today ) && ! in_array( $status, [ 'finalizado', 'finalizado e pago', 'finalizado_pago', 'cancelado' ], true ) ) {
                    $next = $appt;
                    break;
                }
            }
        }
        if ( $next ) {
            $pet_id    = get_post_meta( $next->ID, 'appointment_pet_id', true );
            $pet_name  = $pet_id ? get_the_title( $pet_id ) : '';
            $services  = get_post_meta( $next->ID, 'appointment_services', true );
            $services  = is_array( $services ) ? implode( ', ', array_map( 'esc_html', $services ) ) : '';
            $date      = get_post_meta( $next->ID, 'appointment_date', true );
            $time      = get_post_meta( $next->ID, 'appointment_time', true );
            echo '<p><strong>' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</strong> às ' . esc_html( $time ) . '</p>';
            if ( $pet_name ) {
                echo '<p>' . esc_html__( 'Pet:', 'dps-client-portal' ) . ' ' . esc_html( $pet_name ) . '</p>';
            }
            if ( $services ) {
                echo '<p>' . esc_html__( 'Serviços:', 'dps-client-portal' ) . ' ' . $services . '</p>';
            }
            // Link para mapa
            $address = get_post_meta( $client_id, 'client_address', true );
            if ( $address ) {
                $query = urlencode( $address );
                $url   = 'https://www.google.com/maps/search/?api=1&query=' . $query;
                echo '<p><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Ver no mapa', 'dps-client-portal' ) . '</a></p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Nenhum agendamento futuro encontrado.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de pendências financeiras do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_financial_pending( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Busca transações com status em aberto
        $pendings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE cliente_id = %d AND status IN ('em_aberto', 'pendente')", $client_id ) );
        echo '<section class="dps-portal-section dps-portal-finances">';
        echo '<h3>' . esc_html__( 'Pendências Financeiras', 'dps-client-portal' ) . '</h3>';
        if ( $pendings ) {
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Descrição', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Ação', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $pendings as $trans ) {
                $date = $trans->data;
                $desc = $trans->descricao ? $trans->descricao : __( 'Serviço', 'dps-client-portal' );
                $valor = number_format( (float) $trans->valor, 2, ',', '.' );
                echo '<tr>';
                echo '<td>' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
                echo '<td>' . esc_html( $desc ) . '</td>';
                echo '<td>R$ ' . esc_html( $valor ) . '</td>';
                // Gera link de pagamento via formulário
                echo '<td>';
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
                echo '<input type="hidden" name="dps_client_portal_action" value="pay_transaction">';
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $trans->id ) . '">';
                echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Pagar', 'dps-client-portal' ) . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhuma pendência em aberto.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de histórico de agendamentos do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_appointment_history( $client_id ) {
        $args = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'appointment_date',
            'order'          => 'DESC',
        ];
        $appointments = get_posts( $args );
        echo '<section class="dps-portal-section dps-portal-history">';
        echo '<h3>' . esc_html__( 'Histórico de Atendimentos', 'dps-client-portal' ) . '</h3>';
        if ( $appointments ) {
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-client-portal' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $time   = get_post_meta( $appt->ID, 'appointment_time', true );
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet_name = $pet_id ? get_the_title( $pet_id ) : '';
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                if ( is_array( $services ) ) {
                    $services = implode( ', ', array_map( 'esc_html', $services ) );
                } else {
                    $services = '';
                }
                echo '<tr>';
                echo '<td>' . esc_html( $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '' ) . '</td>';
                echo '<td>' . esc_html( $time ) . '</td>';
                echo '<td>' . esc_html( $pet_name ) . '</td>';
                echo '<td>' . $services . '</td>';
                echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum atendimento encontrado.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza a seção de galeria de fotos dos pets do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_pet_gallery( $client_id ) {
        // Obtém pets do cliente
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        echo '<section class="dps-portal-section dps-portal-gallery">';
        echo '<h3>' . esc_html__( 'Galeria de Fotos', 'dps-client-portal' ) . '</h3>';
        if ( $pets ) {
            echo '<div class="dps-portal-gallery-grid">';
            foreach ( $pets as $pet ) {
                $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
                $pet_name = $pet->post_title;
                echo '<div class="dps-portal-photo-item">';
                echo '<h4>' . esc_html( $pet_name ) . '</h4>';
                if ( $photo_id ) {
                    $img_url = wp_get_attachment_image_url( $photo_id, 'medium' );
                    if ( $img_url ) {
                        // Link para compartilhar via WhatsApp
                        $wa_text = urlencode( sprintf( __( 'Olha que fofo estou após o banho/tosa no Desi Pet Shower! %s', 'dps-client-portal' ), $img_url ) );
                        $wa_link = 'https://wa.me/?text=' . $wa_text;
                        echo '<a href="' . esc_url( $img_url ) . '" target="_blank"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet_name ) . '" style="max-width:100%;height:auto;" /></a><br>';
                        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank" class="dps-share-whatsapp">' . esc_html__( 'Compartilhar via WhatsApp', 'dps-client-portal' ) . '</a>';
                    } else {
                        echo '<p>' . esc_html__( 'Sem foto disponível.', 'dps-client-portal' ) . '</p>';
                    }
                } else {
                    echo '<p>' . esc_html__( 'Sem foto disponível.', 'dps-client-portal' ) . '</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum pet cadastrado.', 'dps-client-portal' ) . '</p>';
        }
        echo '</section>';
    }

    /**
     * Renderiza formulários para atualização de dados pessoais e dos pets.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_update_forms( $client_id ) {
        // Recupera metadados do cliente
        $meta = [
            'phone'     => get_post_meta( $client_id, 'client_phone', true ),
            'email'     => get_post_meta( $client_id, 'client_email', true ),
            'address'   => get_post_meta( $client_id, 'client_address', true ),
            'instagram' => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'  => get_post_meta( $client_id, 'client_facebook', true ),
        ];
        echo '<section class="dps-portal-section dps-portal-update">';
        echo '<h3>' . esc_html__( 'Atualizar Dados Pessoais', 'dps-client-portal' ) . '</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_info">';
        echo '<p><label>' . esc_html__( 'Telefone / WhatsApp', 'dps-client-portal' ) . '<br><input type="text" name="client_phone" value="' . esc_attr( $meta['phone'] ) . '"></label></p>';
        echo '<p><label>' . esc_html__( 'Email', 'dps-client-portal' ) . '<br><input type="email" name="client_email" value="' . esc_attr( $meta['email'] ) . '"></label></p>';
        echo '<p><label>' . esc_html__( 'Endereço completo', 'dps-client-portal' ) . '<br><textarea name="client_address" rows="2">' . esc_textarea( $meta['address'] ) . '</textarea></label></p>';
        echo '<p><label>Instagram<br><input type="text" name="client_instagram" value="' . esc_attr( $meta['instagram'] ) . '"></label></p>';
        echo '<p><label>Facebook<br><input type="text" name="client_facebook" value="' . esc_attr( $meta['facebook'] ) . '"></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar Dados', 'dps-client-portal' ) . '</button></p>';
        echo '</form>';
        // Lista pets para edição
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        if ( $pets ) {
            echo '<h3>' . esc_html__( 'Atualizar Pets', 'dps-client-portal' ) . '</h3>';
            foreach ( $pets as $pet ) {
                $pet_id = $pet->ID;
                $meta_pet = [
                    'species'   => get_post_meta( $pet_id, 'pet_species', true ),
                    'breed'     => get_post_meta( $pet_id, 'pet_breed', true ),
                    'size'      => get_post_meta( $pet_id, 'pet_size', true ),
                    'weight'    => get_post_meta( $pet_id, 'pet_weight', true ),
                    'coat'      => get_post_meta( $pet_id, 'pet_coat', true ),
                    'color'     => get_post_meta( $pet_id, 'pet_color', true ),
                    'birth'     => get_post_meta( $pet_id, 'pet_birth', true ),
                    'sex'       => get_post_meta( $pet_id, 'pet_sex', true ),
                    'vaccinations' => get_post_meta( $pet_id, 'pet_vaccinations', true ),
                    'allergies'    => get_post_meta( $pet_id, 'pet_allergies', true ),
                    'behavior'     => get_post_meta( $pet_id, 'pet_behavior', true ),
                ];
                echo '<div class="dps-pet-update-form">';
                echo '<h4>' . esc_html( $pet->post_title ) . '</h4>';
                echo '<form method="post" enctype="multipart/form-data">';
                wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
                echo '<input type="hidden" name="dps_client_portal_action" value="update_pet">';
                echo '<input type="hidden" name="pet_id" value="' . esc_attr( $pet_id ) . '">';
                echo '<p><label>' . esc_html__( 'Nome', 'dps-client-portal' ) . '<br><input type="text" name="pet_name" value="' . esc_attr( $pet->post_title ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Espécie', 'dps-client-portal' ) . '<br><input type="text" name="pet_species" value="' . esc_attr( $meta_pet['species'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Raça', 'dps-client-portal' ) . '<br><input type="text" name="pet_breed" value="' . esc_attr( $meta_pet['breed'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Porte', 'dps-client-portal' ) . '<br><input type="text" name="pet_size" value="' . esc_attr( $meta_pet['size'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Peso', 'dps-client-portal' ) . '<br><input type="text" name="pet_weight" value="' . esc_attr( $meta_pet['weight'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Pelagem', 'dps-client-portal' ) . '<br><input type="text" name="pet_coat" value="' . esc_attr( $meta_pet['coat'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Cor', 'dps-client-portal' ) . '<br><input type="text" name="pet_color" value="' . esc_attr( $meta_pet['color'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Data de nascimento', 'dps-client-portal' ) . '<br><input type="date" name="pet_birth" value="' . esc_attr( $meta_pet['birth'] ) . '"></label></p>';
                echo '<p><label>' . esc_html__( 'Sexo', 'dps-client-portal' ) . '<br><select name="pet_sex"><option value="">' . esc_html__( 'Selecione...', 'dps-client-portal' ) . '</option>';
                $sex_opts = [ 'M' => 'Macho', 'F' => 'Fêmea' ];
                foreach ( $sex_opts as $val => $label ) {
                    $sel = ( $meta_pet['sex'] === $val ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select></label></p>';
                echo '<p><label>' . esc_html__( 'Vacinas / Saúde', 'dps-client-portal' ) . '<br><textarea name="pet_vaccinations" rows="2">' . esc_textarea( $meta_pet['vaccinations'] ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Alergias / Restrições', 'dps-client-portal' ) . '<br><textarea name="pet_allergies" rows="2">' . esc_textarea( $meta_pet['allergies'] ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Notas de Comportamento', 'dps-client-portal' ) . '<br><textarea name="pet_behavior" rows="2">' . esc_textarea( $meta_pet['behavior'] ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Foto', 'dps-client-portal' ) . '<br><input type="file" name="pet_photo"></label></p>';
                echo '<p><button type="submit" class="button">' . esc_html__( 'Salvar Pet', 'dps-client-portal' ) . '</button></p>';
                echo '</form>';
                echo '</div>';
            }
        }
        // Link para avaliação no Google
        echo '<h3>' . esc_html__( 'Avalie nosso serviço', 'dps-client-portal' ) . '</h3>';
        $review_url = 'https://g.page/r/CUPivNuiAGwnEAE/review';
        echo '<p><a class="button" href="' . esc_url( $review_url ) . '" target="_blank">' . esc_html__( 'Deixar uma Avaliação', 'dps-client-portal' ) . '</a></p>';
        echo '</section>';
    }

    /**
     * Gera um link de pagamento do Mercado Pago para uma transação específica.  Se
     * ocorrer algum erro, retorna false.
     *
     * @param int $trans_id ID da transação na tabela dps_transacoes.
     * @return string|false URL do checkout ou false em caso de falha.
     */
    private function generate_payment_link_for_transaction( $trans_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $trans_id ) );
        if ( ! $trans ) {
            return false;
        }
        // Necessita de token do Mercado Pago
        $token = trim( get_option( 'dps_mercadopago_access_token' ) );
        if ( ! $token ) {
            return false;
        }
        $amount      = (float) $trans->valor;
        $desc        = $trans->descricao ? $trans->descricao : 'Pagamento de serviços';
        $reference   = 'dps_transaction_' . $trans->id;
        $notification_url = home_url( '/?topic=payment' );
        $body = [
            'items' => [
                [
                    'title'       => $desc,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            'external_reference' => $reference,
            'notification_url'   => $notification_url,
        ];
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ];
        // Faz requisição à API do Mercado Pago
        $url = 'https://api.mercadopago.com/checkout/preferences?access_token=' . rawurlencode( $token );
        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['init_point'] ) ) {
            return esc_url_raw( $data['init_point'] );
        }
        return false;
    }

    /**
     * Adiciona a página de administração para gerenciar logins de clientes.
     * Coloca a página como submenu do post type dps_cliente.
     */
    public function register_client_logins_page() {
        add_submenu_page(
            'edit.php?post_type=dps_cliente',
            __( 'Logins de Clientes', 'dps-client-portal' ),
            __( 'Logins', 'dps-client-portal' ),
            'manage_options',
            'dps-client-logins',
            [ $this, 'render_client_logins_page' ]
        );
    }

    /**
     * Renderiza a página de administração dos logins de clientes.
     * Lista todos os clientes e possibilita a redefinição de senha.
     */
    public function render_client_logins_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Logins de Clientes', 'dps-client-portal' ) . '</h1>';
        // Processa redefinição de senha ou envio via WhatsApp
        if ( isset( $_GET['reset_pass'] ) && isset( $_GET['client_id'] ) ) {
            $cid   = intval( $_GET['client_id'] );
            $nonce = $_GET['_wpnonce'] ?? '';
            if ( wp_verify_nonce( $nonce, 'dps_reset_pass_' . $cid ) ) {
                $plain = wp_generate_password( 8, false, false );
                $hash  = wp_hash_password( $plain );
                update_post_meta( $cid, 'client_password_hash', $hash );
                set_transient( 'dps_client_pass_' . $cid, $plain, 30 * MINUTE_IN_SECONDS );
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Senha redefinida com sucesso.', 'dps-client-portal' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Falha ao validar solicitação.', 'dps-client-portal' ) . '</p></div>';
            }
        } elseif ( isset( $_GET['send_pass'] ) && isset( $_GET['client_id'] ) ) {
            $cid   = intval( $_GET['client_id'] );
            $nonce = $_GET['_wpnonce'] ?? '';
            if ( wp_verify_nonce( $nonce, 'dps_send_pass_' . $cid ) ) {
                // Gera uma nova senha e salva
                $plain = wp_generate_password( 8, false, false );
                $hash  = wp_hash_password( $plain );
                update_post_meta( $cid, 'client_password_hash', $hash );
                set_transient( 'dps_client_pass_' . $cid, $plain, 30 * MINUTE_IN_SECONDS );
                // Monta link do WhatsApp com o telefone e senha
                $phone = get_post_meta( $cid, 'client_phone', true );
                $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
                // Busca página do portal para link direto
                $portal_page = get_page_by_title( 'Portal do Cliente' );
                $portal_link = $portal_page ? get_permalink( $portal_page->ID ) : home_url();
                $message = sprintf( __( 'Olá! Aqui estão seus dados de acesso ao portal Desi Pet Shower:\nTelefone: %s\nSenha: %s\nAcesse: %s', 'dps-client-portal' ), $phone, $plain, $portal_link );
                $wa_text = urlencode( $message );
                $wa_link = 'https://wa.me/' . $clean_phone . '?text=' . $wa_text;
                // Redireciona para o WhatsApp
                wp_redirect( $wa_link );
                exit;
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Falha ao validar solicitação.', 'dps-client-portal' ) . '</p></div>';
            }
        }
        // Consulta todos os clientes
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'ID', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Nome', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Telefone', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Senha Temporária', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>';
        echo '</tr></thead><tbody>';
        if ( $clients ) {
            foreach ( $clients as $client ) {
                $cid   = $client->ID;
                $phone = get_post_meta( $cid, 'client_phone', true );
                $temp  = get_transient( 'dps_client_pass_' . $cid );
                echo '<tr>';
                echo '<td>' . intval( $cid ) . '</td>';
                echo '<td>' . esc_html( $client->post_title ) . '</td>';
                echo '<td>' . esc_html( $phone ? $phone : '-' ) . '</td>';
                echo '<td>' . esc_html( $temp ? $temp : '-' ) . '</td>';
                
                // --- MUDANÇA AQUI ---
                // Obtém a URL da página atual do front-end
                $base_url = get_permalink();
                
                // Monta as URLs com os parâmetros corretos para o front-end
                $reset_args = [ 'tab' => 'logins', 'reset_pass' => 1, 'client_id' => $cid ];
                $send_args = [ 'tab' => 'logins', 'send_pass' => 1, 'client_id' => $cid ];

                $reset_url = wp_nonce_url( add_query_arg( $reset_args, $base_url ), 'dps_reset_pass_' . $cid );
                $send_url  = wp_nonce_url( add_query_arg( $send_args, $base_url ), 'dps_send_pass_' . $cid );

                echo '<td>';
                echo '<a class="button" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Redefinir Senha', 'dps-client-portal' ) . '</a> ';
                echo '<a class="button" href="' . esc_url( $send_url ) . '">' . esc_html__( 'Enviar via WhatsApp', 'dps-client-portal' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">' . esc_html__( 'Nenhum cliente encontrado.', 'dps-client-portal' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Exibe formulário de login e processa autenticação via telefone e senha.
     * Quando autenticado, redireciona para a página contendo o portal.
     *
     * @return string HTML do formulário de login
     */
    public function render_login_shortcode() {
        // Se já logado, redireciona
        if ( isset( $_SESSION['dps_client_id'] ) && $_SESSION['dps_client_id'] ) {
            $portal_page = get_page_by_title( 'Portal do Cliente' );
            if ( $portal_page ) {
                wp_safe_redirect( get_permalink( $portal_page->ID ) );
            }
            return '';
        }
        // Processa submissão do login
        if ( isset( $_POST['dps_client_login_action'] ) ) {
            if ( ! isset( $_POST['_dps_client_login_nonce'] ) || ! wp_verify_nonce( $_POST['_dps_client_login_nonce'], 'dps_client_login_action' ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Falha na verificação do formulário.', 'dps-client-portal' ) . '</p></div>';
            } else {
                $phone    = sanitize_text_field( $_POST['dps_client_phone'] ?? '' );
                $password = sanitize_text_field( $_POST['dps_client_password'] ?? '' );
                $client_q = new WP_Query( [
                    'post_type'      => 'dps_cliente',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [
                            'key'     => 'client_phone',
                            'value'   => $phone,
                            'compare' => '=',
                        ],
                    ],
                ] );
                if ( $client_q->have_posts() ) {
                    $client_post = $client_q->posts[0];
                    $hash = get_post_meta( $client_post->ID, 'client_password_hash', true );
                    if ( $hash && wp_check_password( $password, $hash ) ) {
                        $_SESSION['dps_client_id'] = $client_post->ID;
                        $portal_page = get_page_by_title( 'Portal do Cliente' );
                        if ( $portal_page ) {
                            wp_safe_redirect( get_permalink( $portal_page->ID ) );
                        }
                        return '';
                    } else {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Telefone ou senha incorretos.', 'dps-client-portal' ) . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Telefone ou senha incorretos.', 'dps-client-portal' ) . '</p></div>';
                }
            }
        }
        ob_start();
        echo '<form method="post" class="dps-client-login-form">';
        wp_nonce_field( 'dps_client_login_action', '_dps_client_login_nonce' );
        echo '<p><label>' . esc_html__( 'Telefone', 'dps-client-portal' ) . '<br />';
        echo '<input type="text" name="dps_client_phone" value="" required></label></p>';
        echo '<p><label>' . esc_html__( 'Senha', 'dps-client-portal' ) . '<br />';
        echo '<input type="password" name="dps_client_password" value="" required></label></p>';
        echo '<p><button type="submit" name="dps_client_login_action" class="button button-primary">' . esc_html__( 'Entrar', 'dps-client-portal' ) . '</button></p>';
        echo '</form>';
        return ob_get_clean();
    }
    
    /**
     * Renderiza a aba "Logins" na navegação do front-end.
     */
    public function render_logins_tab() {
        echo '<li><a href="#" class="dps-tab-link" data-tab="logins">' . esc_html__( 'Logins de Clientes', 'dps-client-portal' ) . '</a></li>';
    }

    /**
     * Renderiza o conteúdo da seção "Logins" no front-end.
     */
    public function render_logins_section() {
        echo '<div class="dps-section" id="dps-section-logins">';
        // Reutiliza a função que já criava a página no admin
        $this->render_client_logins_page();
        echo '</div>';
    }
}
endif;
