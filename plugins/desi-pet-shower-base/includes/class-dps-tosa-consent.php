<?php
/**
 * Gerenciador de consentimento de tosa com máquina via link
 *
 * Fluxo público de consentimento com geração de link pelo administrador
 * na página de detalhes do cliente (Painel DPS).
 *
 * @package DesiPetShower
 * @since 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Tosa_Consent' ) ) :

final class DPS_Tosa_Consent {

    /**
     * Única instância da classe
     *
     * @var DPS_Tosa_Consent|null
     */
    private static $instance = null;

    /**
     * Tempo de expiração do link (7 dias)
     *
     * @var int
     */
    const TOKEN_TTL_SECONDS = 7 * DAY_IN_SECONDS;

    /**
     * Recupera instância única (singleton)
     *
     * @return DPS_Tosa_Consent
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializador para uso em hook init.
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Construtor privado
     */
    private function __construct() {
        add_action( 'dps_client_page_header_actions', [ $this, 'render_consent_button' ], 12, 3 );
        add_action( 'wp_ajax_dps_generate_tosa_consent_link', [ $this, 'ajax_generate_link' ] );
        add_action( 'wp_ajax_dps_revoke_tosa_consent', [ $this, 'ajax_revoke_consent' ] );
        add_action( 'init', [ $this, 'handle_consent_form' ], 10 );
    }

    /**
     * Renderiza botão de geração de link e status do consentimento
     *
     * @param int     $client_id ID do cliente.
     * @param WP_Post $client    Objeto do post do cliente.
     * @param string  $base_url  URL base da página.
     */
    public function render_consent_button( $client_id, $client, $base_url ) {
        $status_data   = DPS_Base_Frontend::get_client_tosa_consent_data( $client_id );
        $nonce         = wp_create_nonce( 'dps_generate_tosa_consent_' . $client_id );
        $revoke_nonce  = wp_create_nonce( 'dps_revoke_tosa_consent_' . $client_id );
        $existing_link = get_transient( 'dps_tosa_consent_token_' . $client_id );

        $badge_class = 'dps-consent-badge--missing';
        $badge_text  = __( 'Consentimento pendente', 'desi-pet-shower' );
        $badge_note  = '';

        if ( 'granted' === $status_data['status'] ) {
            $badge_class = 'dps-consent-badge--ok';
            $badge_text  = __( 'Consentimento ativo', 'desi-pet-shower' );
            if ( $status_data['granted_at'] ) {
                $badge_note = sprintf(
                    /* translators: %s: data */
                    __( 'Assinado em %s', 'desi-pet-shower' ),
                    esc_html( $status_data['granted_at'] )
                );
            }
        } elseif ( 'revoked' === $status_data['status'] ) {
            $badge_class = 'dps-consent-badge--danger';
            $badge_text  = __( 'Consentimento revogado', 'desi-pet-shower' );
            if ( $status_data['revoked_at'] ) {
                $badge_note = sprintf(
                    /* translators: %s: data */
                    __( 'Revogado em %s', 'desi-pet-shower' ),
                    esc_html( $status_data['revoked_at'] )
                );
            }
        }

        echo '<div class="dps-consent-status">';
        echo '<span class="dps-consent-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_text ) . '</span>';
        if ( $badge_note ) {
            echo '<span class="dps-consent-status__note">' . esc_html( $badge_note ) . '</span>';
        }
        echo '</div>';

        if ( $existing_link && ! empty( $existing_link['url'] ) ) {
            echo '<div class="dps-consent-link-container">';
            echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-copy-link" data-link="' . esc_attr( $existing_link['url'] ) . '" title="' . esc_attr__( 'Clique para copiar', 'desi-pet-shower' ) . '">';
            echo '📋 ' . esc_html__( 'Copiar Link', 'desi-pet-shower' );
            echo '</button>';
            echo '<span class="dps-link-expires">' . esc_html__( 'Válido por 7 dias', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
        }

        echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-generate-consent-link" ';
        echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
        echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
        echo 'title="' . esc_attr__( 'Gerar link para o cliente assinar o consentimento', 'desi-pet-shower' ) . '">';
        echo '✍️ ' . esc_html__( 'Link de Consentimento', 'desi-pet-shower' );
        echo '</button>';

        if ( 'granted' === $status_data['status'] ) {
            echo '<button type="button" class="dps-btn-action dps-btn-action--danger dps-revoke-consent" ';
            echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
            echo 'data-nonce="' . esc_attr( $revoke_nonce ) . '" ';
            echo 'title="' . esc_attr__( 'Revogar consentimento de tosa com máquina', 'desi-pet-shower' ) . '">';
            echo '⛔ ' . esc_html__( 'Revogar consentimento', 'desi-pet-shower' );
            echo '</button>';
        }

        $this->render_button_script();
    }

    /**
     * Renderiza script JavaScript dos botões
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
            function showInlineMessage(text, type) {
                var msg = document.createElement('div');
                msg.className = 'dps-alert dps-alert--' + type;
                msg.innerHTML = text;
                var header = document.querySelector('.dps-client-header');
                if (header) {
                    header.parentNode.insertBefore(msg, header.nextSibling);
                }
            }

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.dps-generate-consent-link');
                if (!btn) return;

                btn.disabled = true;
                btn.innerHTML = '⏳ <?php echo esc_js( __( 'Gerando...', 'desi-pet-shower' ) ); ?>';

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dps_generate_tosa_consent_link&client_id=' + btn.dataset.clientId + '&_wpnonce=' + btn.dataset.nonce
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    btn.innerHTML = '✍️ <?php echo esc_js( __( 'Link de Consentimento', 'desi-pet-shower' ) ); ?>';

                    if (data && data.success && data.data && data.data.url) {
                        navigator.clipboard.writeText(data.data.url).then(function() {
                            showInlineMessage('<?php echo esc_js( __( 'Link de consentimento gerado e copiado! Envie para o cliente.', 'desi-pet-shower' ) ); ?>' +
                                '<br><strong><?php echo esc_js( __( 'Válido por 7 dias.', 'desi-pet-shower' ) ); ?></strong>' +
                                '<br><code style="word-break: break-all;">' + data.data.url + '</code>', 'success');
                        }).catch(function() {
                            prompt('<?php echo esc_js( __( 'Copie o link abaixo:', 'desi-pet-shower' ) ); ?>', data.data.url);
                        });
                    } else {
                        var errorMsg = (data && data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Erro ao gerar link. Tente novamente.', 'desi-pet-shower' ) ); ?>';
                        alert(errorMsg);
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.innerHTML = '✍️ <?php echo esc_js( __( 'Link de Consentimento', 'desi-pet-shower' ) ); ?>';
                    alert('<?php echo esc_js( __( 'Erro de conexão. Verifique sua internet e tente novamente.', 'desi-pet-shower' ) ); ?>');
                });
            });

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.dps-revoke-consent');
                if (!btn) return;
                if (!confirm('<?php echo esc_js( __( 'Revogar o consentimento atual deste cliente?', 'desi-pet-shower' ) ); ?>')) {
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '⏳ <?php echo esc_js( __( 'Revogando...', 'desi-pet-shower' ) ); ?>';

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=dps_revoke_tosa_consent&client_id=' + btn.dataset.clientId + '&_wpnonce=' + btn.dataset.nonce
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    if (data && data.success) {
                        location.reload();
                    } else {
                        var errorMsg = (data && data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Erro ao revogar o consentimento.', 'desi-pet-shower' ) ); ?>';
                        alert(errorMsg);
                        btn.innerHTML = '⛔ <?php echo esc_js( __( 'Revogar consentimento', 'desi-pet-shower' ) ); ?>';
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.innerHTML = '⛔ <?php echo esc_js( __( 'Revogar consentimento', 'desi-pet-shower' ) ); ?>';
                    alert('<?php echo esc_js( __( 'Erro de conexão. Verifique sua internet e tente novamente.', 'desi-pet-shower' ) ); ?>');
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Gera link de consentimento de tosa com máquina
     */
    public function ajax_generate_link() {
        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $nonce     = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'dps_generate_tosa_consent_' . $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'desi-pet-shower' ) ] );
        }

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para executar esta ação.', 'desi-pet-shower' ) ] );
        }

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido.', 'desi-pet-shower' ) ] );
        }

        $token_plain = bin2hex( random_bytes( 32 ) );
        $token_hash  = password_hash( $token_plain, PASSWORD_DEFAULT );
        $expires_at  = time() + self::TOKEN_TTL_SECONDS;

        update_post_meta( $client_id, 'dps_consent_tosa_maquina_token_hash', $token_hash );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_token_expires', $expires_at );

        $consent_url = add_query_arg(
            [
                'client_id' => $client_id,
                'token'     => $token_plain,
            ],
            $this->get_consent_page_url()
        );

        set_transient( 'dps_tosa_consent_token_' . $client_id, [
            'token' => $token_plain,
            'url'   => $consent_url,
        ], self::TOKEN_TTL_SECONDS );

        do_action( 'dps_tosa_consent_link_generated', $client_id, $consent_url );

        wp_send_json_success( [
            'url'     => $consent_url,
            'message' => __( 'Link gerado com sucesso!', 'desi-pet-shower' ),
        ] );
    }

    /**
     * AJAX: Revoga consentimento ativo
     */
    public function ajax_revoke_consent() {
        $client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
        $nonce     = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'dps_revoke_tosa_consent_' . $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'desi-pet-shower' ) ] );
        }

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão para executar esta ação.', 'desi-pet-shower' ) ] );
        }

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Cliente inválido.', 'desi-pet-shower' ) ] );
        }

        update_post_meta( $client_id, 'dps_consent_tosa_maquina_status', 'revoked' );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_revoked_at', current_time( 'mysql' ) );

        do_action( 'dps_tosa_consent_revoked', $client_id );

        wp_send_json_success( [ 'message' => __( 'Consentimento revogado.', 'desi-pet-shower' ) ] );
    }

    /**
     * Processa formulário de consentimento público
     */
    public function handle_consent_form() {
        if ( ! isset( $_POST['dps_tosa_consent_nonce'], $_POST['dps_tosa_consent_token'], $_POST['dps_tosa_consent_client_id'] ) ) {
            return;
        }

        $client_id = absint( wp_unslash( $_POST['dps_tosa_consent_client_id'] ) );
        $token     = sanitize_text_field( wp_unslash( $_POST['dps_tosa_consent_token'] ) );

        if ( ! $client_id || empty( $token ) ) {
            DPS_Message_Helper::add_error( __( 'Link inválido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['dps_tosa_consent_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'dps_tosa_consent_' . $client_id ) ) {
            DPS_Message_Helper::add_error( __( 'Falha na verificação de segurança. Tente novamente.', 'desi-pet-shower' ) );
            return;
        }

        if ( ! $this->validate_token( $client_id, $token ) ) {
            DPS_Message_Helper::add_error( __( 'Link inválido ou expirado. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
            return;
        }

        if ( empty( $_POST['dps_tosa_consent_accept'] ) ) {
            DPS_Message_Helper::add_error( __( 'Para continuar, confirme que leu e aceita os termos.', 'desi-pet-shower' ) );
            return;
        }

        $signature_name     = isset( $_POST['dps_consent_signature_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_consent_signature_name'] ) ) : '';
        $signature_document = isset( $_POST['dps_consent_signature_document'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_consent_signature_document'] ) ) : '';
        $signature_phone    = isset( $_POST['dps_consent_signature_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_consent_signature_phone'] ) ) : '';
        $signature_email    = isset( $_POST['dps_consent_signature_email'] ) ? sanitize_email( wp_unslash( $_POST['dps_consent_signature_email'] ) ) : '';
        $relationship       = isset( $_POST['dps_consent_relationship'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_consent_relationship'] ) ) : '';

        if ( empty( $signature_name ) ) {
            DPS_Message_Helper::add_error( __( 'Informe o nome completo do responsável.', 'desi-pet-shower' ) );
            return;
        }

        update_post_meta( $client_id, 'dps_consent_tosa_maquina_status', 'granted' );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_granted_at', current_time( 'mysql' ) );
        delete_post_meta( $client_id, 'dps_consent_tosa_maquina_revoked_at' );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_signature_name', $signature_name );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_signature_document', $signature_document );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_signature_phone', $signature_phone );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_signature_email', $signature_email );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_relationship', $relationship );

        $ip_address = '';
        if ( class_exists( 'DPS_IP_Helper' ) && method_exists( 'DPS_IP_Helper', 'get_ip_with_proxy_support' ) ) {
            $ip_address = DPS_IP_Helper::get_ip_with_proxy_support();
        } else {
            $ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        }
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( $ip_address ) {
            update_post_meta( $client_id, 'dps_consent_tosa_maquina_ip', $ip_address );
        }
        if ( $user_agent ) {
            update_post_meta( $client_id, 'dps_consent_tosa_maquina_user_agent', mb_substr( $user_agent, 0, 255 ) );
        }

        delete_post_meta( $client_id, 'dps_consent_tosa_maquina_token_hash' );
        delete_post_meta( $client_id, 'dps_consent_tosa_maquina_token_expires' );

        do_action( 'dps_tosa_consent_saved', $client_id );

        DPS_Message_Helper::add_success( __( 'Consentimento registrado com sucesso! Obrigado.', 'desi-pet-shower' ) );
    }

    /**
     * Renderiza shortcode do consentimento
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML do formulário.
     */
    public function render_consent_shortcode( $atts ) {
        $token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $client_id = isset( $_GET['client_id'] ) ? absint( wp_unslash( $_GET['client_id'] ) ) : 0;

        if ( ! $token || ! $client_id ) {
            return $this->render_error_message( __( 'Link inválido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
        }

        if ( ! $this->validate_token( $client_id, $token ) ) {
            return $this->render_error_message( __( 'Este link não é mais válido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
        }

        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return $this->render_error_message( __( 'Cliente não encontrado.', 'desi-pet-shower' ) );
        }

        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );

        if ( $pets ) {
            $pet_ids = wp_list_pluck( $pets, 'ID' );
            update_meta_cache( 'post', $pet_ids );
        }

        $consent_status = DPS_Base_Frontend::get_client_tosa_consent_data( $client_id );

        ob_start();
        dps_get_template( 'tosa-consent-form.php', [
            'client'         => $client,
            'pets'           => $pets,
            'consent_status' => $consent_status,
            'token'          => $token,
            'client_id'      => $client_id,
        ] );
        return ob_get_clean();
    }

    /**
     * Renderiza mensagem de erro
     *
     * @param string $message Mensagem de erro.
     * @return string HTML da mensagem.
     */
    private function render_error_message( $message ) {
        return '<div class="dps-consent-error"><div class="dps-alert dps-alert--error"><p>' . esc_html( $message ) . '</p></div></div>';
    }

    /**
     * Valida token de consentimento armazenado no cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param string $token     Token em texto plano.
     * @return bool
     */
    private function validate_token( $client_id, $token ) {
        $hash    = get_post_meta( $client_id, 'dps_consent_tosa_maquina_token_hash', true );
        $expires = (int) get_post_meta( $client_id, 'dps_consent_tosa_maquina_token_expires', true );

        if ( ! $hash || ! $expires ) {
            return false;
        }

        if ( time() > $expires ) {
            return false;
        }

        return (bool) password_verify( $token, $hash );
    }

    /**
     * Obtém URL da página de consentimento.
     *
     * @return string
     */
    private function get_consent_page_url() {
        $page_id = (int) get_option( 'dps_tosa_consent_page_id', 0 );
        if ( $page_id > 0 ) {
            $permalink = get_permalink( $page_id );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        $consent_page = get_page_by_path( 'consentimento-tosa-maquina' );
        if ( $consent_page instanceof WP_Post ) {
            $permalink = get_permalink( $consent_page->ID );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        $fallback = home_url( '/consentimento-tosa-maquina/' );

        return (string) apply_filters( 'dps_tosa_consent_page_url', $fallback, $page_id );
    }
}

endif;
