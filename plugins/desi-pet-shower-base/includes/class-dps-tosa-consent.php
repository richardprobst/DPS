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
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_consent_assets' ] );

        // Força uso do template do plugin para garantir versão mais recente
        // Isso evita problemas com templates desatualizados no tema
        add_filter( 'dps_use_plugin_template', [ $this, 'force_consent_template' ], 10, 2 );
    }

    /**
     * Força uso do template de consentimento do plugin.
     *
     * Isso garante que a versão mais recente do template seja usada,
     * evitando problemas com templates customizados desatualizados no tema.
     *
     * Para permitir override do tema, use o filtro 'dps_allow_consent_template_override':
     * add_filter( 'dps_allow_consent_template_override', '__return_true' );
     *
     * @param bool   $use_plugin    Se deve usar o template do plugin.
     * @param string $template_name Nome do arquivo de template.
     * @return bool
     */
    public function force_consent_template( $use_plugin, $template_name ) {
        // Apenas força para o template de consentimento de tosa
        if ( 'tosa-consent-form.php' !== $template_name ) {
            return $use_plugin;
        }

        /**
         * Permite que temas sobrescrevam o template de consentimento.
         *
         * Por padrão, o template do plugin é forçado para garantir que
         * a versão mais recente seja usada. Use este filtro para permitir
         * override do tema quando necessário.
         *
         * @param bool $allow_override Se deve permitir override do tema. Default false.
         */
        $allow_theme_override = apply_filters( 'dps_allow_consent_template_override', false );

        // Se override do tema for permitido, não força o template do plugin
        if ( $allow_theme_override ) {
            // Loga quando override do tema é detectado para diagnóstico
            if ( function_exists( 'dps_is_template_overridden' ) && dps_is_template_overridden( 'tosa-consent-form.php' ) ) {
                $this->log_event( 'info', 'Usando template de consentimento do tema (override permitido)', [
                    'template' => 'tosa-consent-form.php',
                    'theme'    => get_template(),
                ] );
            }
            return false;
        }

        // Loga quando override do tema é ignorado
        if ( function_exists( 'dps_is_template_overridden' ) && dps_is_template_overridden( 'tosa-consent-form.php' ) ) {
            $this->log_event( 'warning', 'Template de consentimento do tema ignorado - usando versão do plugin', [
                'template' => 'tosa-consent-form.php',
                'theme'    => get_template(),
                'reason'   => 'Forçado para garantir versão mais recente do formulário',
            ] );
        }

        // Força uso do template do plugin
        return true;
    }

    /**
     * Enfileira assets do formulário de consentimento quando necessário.
     */
    public function enqueue_consent_assets() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( $post && has_shortcode( $post->post_content, 'dps_tosa_consent' ) ) {
            wp_enqueue_style(
                'dps-tosa-consent-form',
                DPS_BASE_URL . 'assets/css/tosa-consent-form.css',
                [],
                $this->get_css_version()
            );
        }
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
        $badge_text  = __( 'Pendente', 'desi-pet-shower' );
        $badge_note  = '';

        if ( 'granted' === $status_data['status'] ) {
            $badge_class = 'dps-consent-badge--ok';
            $badge_text  = __( 'Ativo', 'desi-pet-shower' );
            if ( $status_data['granted_at'] ) {
                $badge_note = sprintf(
                    /* translators: %s: data */
                    __( 'Assinado em %s', 'desi-pet-shower' ),
                    esc_html( $status_data['granted_at'] )
                );
            }
        } elseif ( 'revoked' === $status_data['status'] ) {
            $badge_class = 'dps-consent-badge--danger';
            $badge_text  = __( 'Revogado', 'desi-pet-shower' );
            if ( $status_data['revoked_at'] ) {
                $badge_note = sprintf(
                    /* translators: %s: data */
                    __( 'Revogado em %s', 'desi-pet-shower' ),
                    esc_html( $status_data['revoked_at'] )
                );
            }
        }

        // Grupo de ações de consentimento de tosa
        echo '<div class="dps-quick-action-group">';
        echo '<div class="dps-quick-action-group__header">';
        echo '<span class="dps-quick-action-group__icon" aria-hidden="true">✍️</span>';
        echo '<h5 class="dps-quick-action-group__title">' . esc_html__( 'Consentimento de Tosa', 'desi-pet-shower' ) . '</h5>';
        echo '</div>';
        echo '<div class="dps-quick-action-group__content">';

        // Status badge
        echo '<div class="dps-consent-status">';
        echo '<span class="dps-consent-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_text ) . '</span>';
        if ( $badge_note ) {
            echo '<span class="dps-consent-status__note">' . esc_html( $badge_note ) . '</span>';
        }
        echo '</div>';

        // Link copiável existente
        if ( $existing_link && ! empty( $existing_link['url'] ) ) {
            echo '<div class="dps-consent-link-container">';
            echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-copy-link" data-link="' . esc_attr( $existing_link['url'] ) . '" title="' . esc_attr__( 'Clique para copiar', 'desi-pet-shower' ) . '">';
            echo '📋 ' . esc_html__( 'Copiar', 'desi-pet-shower' );
            echo '</button>';
            echo '<span class="dps-link-expires">' . esc_html__( '7 dias', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
        }

        // Botão gerar link
        echo '<button type="button" class="dps-btn-action dps-btn-action--secondary dps-generate-consent-link" ';
        echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
        echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
        echo 'title="' . esc_attr__( 'Gerar link para o cliente assinar o consentimento', 'desi-pet-shower' ) . '">';
        echo '🔗 ' . esc_html__( 'Gerar Link', 'desi-pet-shower' );
        echo '</button>';

        // Botão revogar (se consentimento ativo)
        if ( 'granted' === $status_data['status'] ) {
            echo '<button type="button" class="dps-btn-action dps-btn-action--danger dps-revoke-consent" ';
            echo 'data-client-id="' . esc_attr( $client_id ) . '" ';
            echo 'data-nonce="' . esc_attr( $revoke_nonce ) . '" ';
            echo 'title="' . esc_attr__( 'Revogar consentimento de tosa com máquina', 'desi-pet-shower' ) . '">';
            echo '⛔ ' . esc_html__( 'Revogar', 'desi-pet-shower' );
            echo '</button>';
        }

        echo '</div>'; // .dps-quick-action-group__content
        echo '</div>'; // .dps-quick-action-group

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
            $this->log_event( 'warning', 'Falha de nonce ao gerar link de consentimento', [ 'client_id' => $client_id ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'NONCE_INVALIDO',
                'message'  => __( 'Falha na verificação de segurança.', 'desi-pet-shower' ),
            ] );
        }

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            $this->log_event( 'warning', 'Tentativa de gerar link sem permissão', [ 'client_id' => $client_id, 'user_id' => get_current_user_id() ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'SEM_PERMISSAO',
                'message'  => __( 'Você não tem permissão para executar esta ação.', 'desi-pet-shower' ),
            ] );
        }

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->log_event( 'warning', 'Cliente inválido ao gerar link', [ 'client_id' => $client_id ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'CLIENTE_NAO_ENCONTRADO',
                'message'  => __( 'Cliente inválido.', 'desi-pet-shower' ),
            ] );
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

        $this->log_event( 'info', 'Link de consentimento gerado', [ 'client_id' => $client_id, 'expires_at' => gmdate( 'Y-m-d H:i:s', $expires_at ) ] );

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
            $this->log_event( 'warning', 'Falha de nonce ao revogar consentimento', [ 'client_id' => $client_id ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'NONCE_INVALIDO',
                'message'  => __( 'Falha na verificação de segurança.', 'desi-pet-shower' ),
            ] );
        }

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            $this->log_event( 'warning', 'Tentativa de revogar consentimento sem permissão', [ 'client_id' => $client_id, 'user_id' => get_current_user_id() ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'SEM_PERMISSAO',
                'message'  => __( 'Você não tem permissão para executar esta ação.', 'desi-pet-shower' ),
            ] );
        }

        if ( ! $client_id || 'dps_cliente' !== get_post_type( $client_id ) ) {
            $this->log_event( 'warning', 'Cliente inválido ao revogar consentimento', [ 'client_id' => $client_id ] );
            wp_send_json_error( [
                'erro'     => true,
                'codigo'   => 'CLIENTE_NAO_ENCONTRADO',
                'message'  => __( 'Cliente inválido.', 'desi-pet-shower' ),
            ] );
        }

        update_post_meta( $client_id, 'dps_consent_tosa_maquina_status', 'revoked' );
        update_post_meta( $client_id, 'dps_consent_tosa_maquina_revoked_at', current_time( 'mysql' ) );

        $this->log_event( 'info', 'Consentimento revogado', [ 'client_id' => $client_id, 'revoked_by' => get_current_user_id() ] );

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
            $this->log_event( 'warning', 'Formulário de consentimento com dados inválidos', [ 'client_id' => $client_id ] );
            DPS_Message_Helper::add_error( __( 'Link inválido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['dps_tosa_consent_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'dps_tosa_consent_' . $client_id ) ) {
            $this->log_event( 'warning', 'Falha de nonce no formulário de consentimento', [ 'client_id' => $client_id ] );
            DPS_Message_Helper::add_error( __( 'Falha na verificação de segurança. Tente novamente.', 'desi-pet-shower' ) );
            return;
        }

        if ( ! $this->validate_token( $client_id, $token ) ) {
            $this->log_event( 'warning', 'Token inválido ou expirado no formulário de consentimento', [ 'client_id' => $client_id ] );
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

        if ( empty( $signature_phone ) ) {
            DPS_Message_Helper::add_error( __( 'Informe o telefone/WhatsApp para contato.', 'desi-pet-shower' ) );
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

        $this->log_event( 'info', 'Consentimento registrado com sucesso', [
            'client_id'      => $client_id,
            'signature_name' => $signature_name,
            'ip'             => $ip_address,
        ] );

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
        // Garante que CSS seja carregado mesmo se wp_enqueue_scripts já passou
        $this->maybe_enqueue_assets_inline();

        // Força desabilitação de cache para esta página
        if ( class_exists( 'DPS_Cache_Control' ) ) {
            DPS_Cache_Control::force_no_cache();
        }

        $token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $client_id = isset( $_GET['client_id'] ) ? absint( wp_unslash( $_GET['client_id'] ) ) : 0;

        if ( ! $token || ! $client_id ) {
            return $this->render_error_message( __( 'Link inválido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
        }

        if ( ! $this->validate_token( $client_id, $token ) ) {
            return $this->render_error_message( __( 'Este link não é mais válido. Solicite um novo link ao administrador.', 'desi-pet-shower' ) );
        }

        // Limpa o cache do cliente para garantir dados frescos
        // Isso é necessário quando object cache (Redis/Memcached) está ativo
        clean_post_cache( $client_id );

        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return $this->render_error_message( __( 'Cliente não encontrado.', 'desi-pet-shower' ) );
        }

        // Query de pets com cache desabilitado para garantir dados atualizados
        // suppress_filters evita que plugins de cache interceptem a query
        // cache_results=false ignora o object cache do WordPress
        $pets = get_posts( [
            'post_type'        => 'dps_pet',
            'posts_per_page'   => -1,
            'post_status'      => 'publish',
            'meta_key'         => 'owner_id',
            'meta_value'       => $client_id,
            'suppress_filters' => true,
            'cache_results'    => false,
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
     * Busca a página na seguinte ordem:
     * 1. Página configurada via option 'dps_tosa_consent_page_id'
     * 2. Página pelo slug 'consentimento-tosa-maquina'
     * 3. Se não existir, cria automaticamente a página
     *
     * @return string
     */
    private function get_consent_page_url() {
        $page_id = (int) get_option( 'dps_tosa_consent_page_id', 0 );
        if ( $page_id > 0 ) {
            $page = get_post( $page_id );
            if ( $page && 'publish' === $page->post_status ) {
                $permalink = get_permalink( $page_id );
                if ( $permalink && is_string( $permalink ) ) {
                    return $permalink;
                }
            }
        }

        $consent_page = get_page_by_path( 'consentimento-tosa-maquina' );
        if ( $consent_page instanceof WP_Post && 'publish' === $consent_page->post_status ) {
            // Se a página existe mas não tem o shortcode, adiciona
            if ( ! has_shortcode( $consent_page->post_content, 'dps_tosa_consent' ) ) {
                wp_update_post( [
                    'ID'           => $consent_page->ID,
                    'post_content' => '[dps_tosa_consent]',
                ] );
            }
            $permalink = get_permalink( $consent_page->ID );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        // Página não existe, cria automaticamente
        $new_page_id = self::create_consent_page();
        if ( $new_page_id > 0 ) {
            $permalink = get_permalink( $new_page_id );
            if ( $permalink && is_string( $permalink ) ) {
                return $permalink;
            }
        }

        // Fallback final (não deve acontecer se a criação funcionou)
        $fallback = home_url( '/consentimento-tosa-maquina/' );

        return (string) apply_filters( 'dps_tosa_consent_page_url', $fallback, $page_id );
    }

    /**
     * Cria a página de consentimento de tosa se não existir.
     *
     * Esta função é chamada automaticamente quando o link de consentimento
     * é gerado e a página não existe. Também pode ser chamada na ativação
     * do plugin.
     *
     * @return int ID da página criada ou 0 em caso de erro.
     */
    public static function create_consent_page() {
        // Verifica se já existe uma página configurada
        $existing_page_id = (int) get_option( 'dps_tosa_consent_page_id', 0 );
        if ( $existing_page_id > 0 ) {
            $existing = get_post( $existing_page_id );
            if ( $existing && 'publish' === $existing->post_status ) {
                return $existing_page_id;
            }
        }

        // Verifica se já existe uma página pelo slug
        $existing_by_slug = get_page_by_path( 'consentimento-tosa-maquina' );
        if ( $existing_by_slug instanceof WP_Post && 'publish' === $existing_by_slug->post_status ) {
            // Garante que tem o shortcode
            if ( ! has_shortcode( $existing_by_slug->post_content, 'dps_tosa_consent' ) ) {
                wp_update_post( [
                    'ID'           => $existing_by_slug->ID,
                    'post_content' => '[dps_tosa_consent]',
                ] );
            }
            update_option( 'dps_tosa_consent_page_id', $existing_by_slug->ID );
            return $existing_by_slug->ID;
        }

        // Cria nova página
        $page_id = wp_insert_post( [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => __( 'Consentimento de Tosa', 'desi-pet-shower' ),
            'post_name'    => 'consentimento-tosa-maquina',
            'post_content' => '[dps_tosa_consent]',
            'post_author'  => 1,
            'meta_input'   => [
                '_wp_page_template' => 'default',
            ],
        ] );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'dps_tosa_consent_page_id', $page_id );

            // Log da criação para debug
            if ( class_exists( 'DPS_Logger' ) ) {
                DPS_Logger::log( 'info', 'Página de consentimento criada automaticamente', [
                    'page_id' => $page_id,
                    'url'     => get_permalink( $page_id ),
                ], 'tosa_consent' );
            }

            return $page_id;
        }

        // Log do erro
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( 'error', 'Falha ao criar página de consentimento', [
                'error' => is_wp_error( $page_id ) ? $page_id->get_error_message() : 'Erro desconhecido',
            ], 'tosa_consent' );
        }

        return 0;
    }

    /**
     * Verifica e corrige a página de consentimento.
     *
     * Use esta função para diagnóstico e reparo da página de consentimento.
     * Retorna um array com informações sobre o status da página.
     *
     * @return array Informações de diagnóstico.
     */
    public static function diagnose_consent_page() {
        $result = [
            'page_id'       => 0,
            'page_exists'   => false,
            'has_shortcode' => false,
            'is_published'  => false,
            'url'           => '',
            'message'       => '',
        ];

        $page_id = (int) get_option( 'dps_tosa_consent_page_id', 0 );

        if ( $page_id > 0 ) {
            $page = get_post( $page_id );
            if ( $page ) {
                $result['page_id']       = $page_id;
                $result['page_exists']   = true;
                $result['is_published']  = 'publish' === $page->post_status;
                $result['has_shortcode'] = has_shortcode( $page->post_content, 'dps_tosa_consent' );
                $result['url']           = get_permalink( $page_id );
            }
        }

        // Se não encontrou pela option, tenta pelo slug
        if ( ! $result['page_exists'] ) {
            $page_by_slug = get_page_by_path( 'consentimento-tosa-maquina' );
            if ( $page_by_slug instanceof WP_Post ) {
                $result['page_id']       = $page_by_slug->ID;
                $result['page_exists']   = true;
                $result['is_published']  = 'publish' === $page_by_slug->post_status;
                $result['has_shortcode'] = has_shortcode( $page_by_slug->post_content, 'dps_tosa_consent' );
                $result['url']           = get_permalink( $page_by_slug->ID );
            }
        }

        // Define mensagem de diagnóstico
        if ( ! $result['page_exists'] ) {
            $result['message'] = __( 'Página de consentimento não existe. Será criada automaticamente ao gerar o primeiro link.', 'desi-pet-shower' );
        } elseif ( ! $result['is_published'] ) {
            $result['message'] = __( 'Página existe mas não está publicada. Publique a página para funcionar corretamente.', 'desi-pet-shower' );
        } elseif ( ! $result['has_shortcode'] ) {
            $result['message'] = __( 'Página existe mas não contém o shortcode [dps_tosa_consent]. Shortcode será adicionado automaticamente.', 'desi-pet-shower' );
        } else {
            $result['message'] = __( 'Página de consentimento configurada corretamente.', 'desi-pet-shower' );
        }

        return $result;
    }

    /**
     * Garante que os assets sejam enfileirados mesmo após wp_enqueue_scripts.
     *
     * Esta função é chamada diretamente do shortcode para garantir que o CSS
     * seja carregado em todos os cenários, incluindo page builders e temas
     * que podem modificar o fluxo normal de carregamento de assets.
     */
    private function maybe_enqueue_assets_inline() {
        static $enqueued = false;

        if ( $enqueued ) {
            return;
        }

        $enqueued = true;

        // Enfileira se ainda não foi registrado
        if ( ! wp_style_is( 'dps-tosa-consent-form', 'enqueued' ) ) {
            wp_enqueue_style(
                'dps-tosa-consent-form',
                DPS_BASE_URL . 'assets/css/tosa-consent-form.css',
                [],
                $this->get_css_version()
            );
        }
    }

    /**
     * Obtém versão do arquivo CSS baseada no timestamp de modificação.
     *
     * @return string|int Versão do arquivo ou versão base do plugin.
     */
    private function get_css_version() {
        $css_file = DPS_BASE_DIR . 'assets/css/tosa-consent-form.css';
        return file_exists( $css_file ) ? filemtime( $css_file ) : DPS_BASE_VERSION;
    }

    /**
     * Registra evento de log para auditoria.
     *
     * @param string $level   Nível do log (info, warning, error).
     * @param string $message Mensagem descritiva.
     * @param array  $context Dados adicionais.
     */
    private function log_event( $level, $message, $context = [] ) {
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::log( $level, $message, $context, 'tosa_consent' );
        }
    }
}

endif;
