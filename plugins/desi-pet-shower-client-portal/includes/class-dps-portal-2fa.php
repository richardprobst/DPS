<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autenticação de Dois Fatores (2FA) via E-mail para o Portal do Cliente.
 *
 * Gera códigos de 6 dígitos enviados por e-mail após validação do magic link.
 * O código tem expiração de 10 minutos e limite de tentativas.
 *
 * Fase 6.4 do Plano de Implementação.
 *
 * @since 3.2.0
 */
class DPS_Portal_2FA {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Portal_2FA|null
     */
    private static $instance = null;

    /**
     * Option que armazena o estado temporario do fluxo 2FA.
     *
     * @var string
     */
    private const STORAGE_OPTION = 'dps_portal_2fa_state';

    /**
     * Prefixos internos para tipos de estado 2FA.
     *
     * @var string
     */
    private const CODE_PREFIX = 'code:';
    private const PENDING_PREFIX = 'pending:';
    private const REMEMBER_PREFIX = 'remember:';

    /**
     * Tempo de vida do código em segundos (10 minutos).
     *
     * @var int
     */
    private const CODE_EXPIRY = 600;

    /**
     * Máximo de tentativas de verificação.
     *
     * @var int
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_2FA
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        // Registra handlers AJAX
        add_action( 'wp_ajax_nopriv_dps_verify_2fa_code', [ $this, 'ajax_verify_2fa_code' ] );
        add_action( 'wp_ajax_dps_verify_2fa_code', [ $this, 'ajax_verify_2fa_code' ] );
    }

    /**
     * Verifica se 2FA está habilitado nas configurações.
     *
     * @return bool
     */
    public function is_enabled() {
        return (bool) get_option( 'dps_portal_2fa_enabled', false );
    }

    /**
     * Gera um código de 6 dígitos para o cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string Código de 6 dígitos.
     */
    public function generate_code( $client_id ) {
        $code = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

        $data = [
            'code'     => wp_hash_password( $code ),
            'attempts' => 0,
            'created'  => time(),
        ];

        $this->set_state_entry( self::CODE_PREFIX . absint( $client_id ), $data, self::CODE_EXPIRY );

        return $code;
    }

    /**
     * Verifica se o código informado é válido para o cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param string $code      Código informado pelo usuário.
     * @return bool|string True se válido, string de erro se inválido.
     */
    public function verify_code( $client_id, $code ) {
        $client_id = absint( $client_id );
        $data      = $this->get_state_entry( self::CODE_PREFIX . $client_id );

        if ( false === $data || ! is_array( $data ) ) {
            return __( 'Código expirado. Solicite um novo link de acesso.', 'dps-client-portal' );
        }

        // Verifica limite de tentativas
        if ( $data['attempts'] >= self::MAX_ATTEMPTS ) {
            $this->delete_state_entry( self::CODE_PREFIX . $client_id );
            return __( 'Muitas tentativas. Solicite um novo link de acesso.', 'dps-client-portal' );
        }

        // Incrementa tentativas antes da verificação (anti-enumeration)
        $data['attempts']++;
        $this->save_state_entry( self::CODE_PREFIX . $client_id, $data );

        // Verifica o código usando hash seguro
        if ( ! wp_check_password( $code, $data['code'] ) ) {
            $remaining = self::MAX_ATTEMPTS - $data['attempts'];
            return sprintf(
                /* translators: %d: número de tentativas restantes */
                _n(
                    'Código incorreto. Você tem %d tentativa restante.',
                    'Código incorreto. Você tem %d tentativas restantes.',
                    $remaining,
                    'dps-client-portal'
                ),
                $remaining
            );
        }

        // Codigo valido: remove o estado persistido.
        $this->delete_state_entry( self::CODE_PREFIX . $client_id );

        return true;
    }

    /**
     * Envia o código de verificação por e-mail.
     *
     * @param int    $client_id ID do cliente.
     * @param string $code      Código de 6 dígitos.
     * @return bool True se o e-mail foi enviado com sucesso.
     */
    public function send_code_email( $client_id, $code ) {
        $email = get_post_meta( $client_id, 'client_email', true );
        if ( empty( $email ) || ! is_email( $email ) ) {
            return false;
        }

        $client_name = get_the_title( $client_id );
        $expiry_min  = self::CODE_EXPIRY / 60;

        $subject = sprintf(
            /* translators: %s: código de verificação */
            __( '🔐 Código de Verificação: %s', 'dps-client-portal' ),
            $code
        );

        $body = $this->build_email_html( $client_name, $code, $expiry_min );

        // Usa a API do Communications se disponível
        if ( class_exists( 'DPS_Communications_API' ) && method_exists( 'DPS_Communications_API', 'send_email' ) ) {
            return DPS_Communications_API::send_email( $email, $subject, $body );
        }

        // Fallback: wp_mail com headers HTML
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        return wp_mail( $email, $subject, $body, $headers );
    }

    /**
     * Constrói o HTML do e-mail com o código de verificação.
     *
     * @param string $client_name Nome do cliente.
     * @param string $code        Código de 6 dígitos.
     * @param int    $expiry_min  Minutos até expiração.
     * @return string HTML do e-mail.
     */
    private function build_email_html( $client_name, $code, $expiry_min ) {
        $greeting = $client_name
            ? sprintf( __( 'Olá, %s! 🐾', 'dps-client-portal' ), esc_html( $client_name ) )
            : __( 'Olá! 🐾', 'dps-client-portal' );

        $digits = str_split( $code );
        $digit_html = '';
        foreach ( $digits as $d ) {
            $digit_html .= '<span style="display:inline-block;width:36px;height:44px;line-height:44px;text-align:center;font-size:24px;font-weight:bold;background:#f0f4ff;border:2px solid #0b6bcb;border-radius:8px;margin:0 3px;color:#1f2937;">' . esc_html( $d ) . '</span>';
        }

        return '
        <div style="max-width:480px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="background:#0b6bcb;color:#fff;padding:20px;text-align:center;border-radius:12px 12px 0 0;">
                <h2 style="margin:0;font-size:20px;">🔐 ' . esc_html__( 'Verificação de Segurança', 'dps-client-portal' ) . '</h2>
            </div>
            <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
                <p style="margin:0 0 16px;color:#374151;">' . $greeting . '</p>
                <p style="margin:0 0 16px;color:#374151;">' . esc_html__( 'Para completar seu acesso ao portal, digite o código abaixo:', 'dps-client-portal' ) . '</p>
                <div style="text-align:center;margin:24px 0;">' . $digit_html . '</div>
                <p style="margin:0 0 8px;color:#6b7280;font-size:13px;text-align:center;">' . sprintf(
                    /* translators: %d: minutos de validade */
                    esc_html__( 'Este código é válido por %d minutos.', 'dps-client-portal' ),
                    $expiry_min
                ) . '</p>
                <p style="margin:0;color:#6b7280;font-size:13px;text-align:center;">' . esc_html__( 'Se você não solicitou este acesso, ignore este e-mail.', 'dps-client-portal' ) . '</p>
            </div>
        </div>';
    }

    /**
     * Marca uma sessao como "pendente 2FA".
     *
     * @param int    $client_id ID do cliente.
     * @param string $session_key Chave da sessão pendente.
     */
    public function set_pending_2fa( $client_id, $session_key ) {
        $this->set_state_entry(
            self::PENDING_PREFIX . $this->build_session_state_key( $session_key ),
            [ 'client_id' => absint( $client_id ) ],
            self::CODE_EXPIRY
        );
    }

    /**
     * Recupera o client_id de uma sessão pendente de 2FA.
     *
     * @param string $session_key Chave da sessão pendente.
     * @return int|false Client ID ou false se não encontrado.
     */
    public function get_pending_client( $session_key ) {
        $data = $this->get_state_entry( self::PENDING_PREFIX . $this->build_session_state_key( $session_key ) );

        return isset( $data['client_id'] ) ? absint( $data['client_id'] ) : false;
    }

    /**
     * Remove a sessão pendente de 2FA.
     *
     * @param string $session_key Chave da sessão pendente.
     */
    public function clear_pending_2fa( $session_key ) {
        $this->delete_state_entry( self::PENDING_PREFIX . $this->build_session_state_key( $session_key ) );
    }

    /**
     * Persiste a flag de remember-me para aplicar apos a verificacao 2FA.
     *
     * @param string $session_key Chave da sessao pendente.
     * @return void
     */
    public function set_remember_flag( $session_key ) {
        $this->set_state_entry(
            self::REMEMBER_PREFIX . $this->build_session_state_key( $session_key ),
            [ 'remember' => '1' ],
            self::CODE_EXPIRY
        );
    }

    /**
     * Consome a flag de remember-me de uma sessao 2FA.
     *
     * @param string $session_key Chave da sessao pendente.
     * @return bool
     */
    public function consume_remember_flag( $session_key ) {
        $key  = self::REMEMBER_PREFIX . $this->build_session_state_key( $session_key );
        $data = $this->get_state_entry( $key );

        $this->delete_state_entry( $key );

        return isset( $data['remember'] ) && '1' === $data['remember'];
    }

    /**
     * Handler AJAX para verificação do código 2FA.
     */
    public function ajax_verify_2fa_code() {
        // Verifica nonce
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'dps_2fa_verify' ) ) {
            wp_send_json_error( [ 'message' => __( 'Requisição inválida.', 'dps-client-portal' ) ] );
        }

        $code        = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( wp_unslash( $_POST['session_key'] ) ) : '';

        if ( empty( $code ) || empty( $session_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Código não informado.', 'dps-client-portal' ) ] );
        }

        // Recupera client_id da sessão pendente
        $client_id = $this->get_pending_client( $session_key );
        if ( false === $client_id ) {
            wp_send_json_error( [ 'message' => __( 'Sessão expirada. Solicite um novo link de acesso.', 'dps-client-portal' ) ] );
        }

        // Verifica o código
        $result = $this->verify_code( $client_id, $code );

        if ( true !== $result ) {
            wp_send_json_error( [ 'message' => $result ] );
        }

        // Código válido - cria sessão autenticada
        $session_manager = DPS_Portal_Session_Manager::get_instance();
        $authenticated   = $session_manager->authenticate_client( $client_id );

        if ( ! $authenticated ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao criar sessão. Tente novamente.', 'dps-client-portal' ) ] );
        }

        // Remove sessão pendente
        $this->clear_pending_2fa( $session_key );

        // F4.6: Aplica remember-me se estava sinalizado antes da 2FA
        if ( $this->consume_remember_flag( $session_key ) ) {
            $token_manager   = DPS_Portal_Token_Manager::get_instance();
            $permanent_token = $token_manager->generate_token( $client_id, 'permanent' );
            if ( false !== $permanent_token ) {
                $cookie_expiry = time() + ( 90 * DAY_IN_SECONDS );
                setcookie(
                    'dps_portal_remember',
                    $permanent_token,
                    $cookie_expiry,
                    COOKIEPATH,
                    COOKIE_DOMAIN,
                    is_ssl(),
                    true
                );
                if ( ! headers_sent() ) {
                    header(
                        sprintf(
                            'Set-Cookie: dps_portal_remember=%s; Expires=%s; Path=%s; Domain=%s%s; HttpOnly; SameSite=Strict',
                            $permanent_token,
                            gmdate( 'D, d M Y H:i:s T', $cookie_expiry ),
                            COOKIEPATH,
                            COOKIE_DOMAIN,
                            is_ssl() ? '; Secure' : ''
                        ),
                        false
                    );
                }
            }
        }

        // Registra sucesso
        if ( class_exists( 'DPS_Audit_Logger' ) ) {
            $ip = class_exists( 'DPS_IP_Helper' ) ? DPS_IP_Helper::get_ip() : 'unknown';
            DPS_Audit_Logger::log_portal_event( '2fa_verified', $client_id, [ 'ip' => $ip ] );
        }

        wp_send_json_success( [
            'message'  => __( 'Verificação concluída!', 'dps-client-portal' ),
            'redirect' => remove_query_arg( [ 'dps_token', 'dps_2fa' ] ),
        ] );
    }

    /**
     * Renderiza o formulário de verificação 2FA.
     *
     * @param string $session_key Chave da sessão pendente.
     * @param string $email       E-mail do cliente (parcialmente ofuscado).
     * @return string HTML do formulário.
     */
    public function render_verification_form( $session_key, $email ) {
        $masked_email = $this->mask_email( $email );
        $nonce        = wp_create_nonce( 'dps_2fa_verify' );
        $ajax_url     = admin_url( 'admin-ajax.php' );

        ob_start();
        ?>
        <div class="dps-client-portal-access-page">
            <div class="dps-portal-access">
                <div class="dps-portal-access__card">
                    <div class="dps-portal-access__logo">🔐</div>
                    <h1 class="dps-portal-access__title">
                        <?php echo esc_html__( 'Verificação de Segurança', 'dps-client-portal' ); ?>
                    </h1>
                    <p class="dps-portal-access__description">
                        <?php
                        printf(
                            /* translators: %s: e-mail ofuscado */
                            esc_html__( 'Enviamos um código de 6 dígitos para %s. Digite-o abaixo para completar o acesso.', 'dps-client-portal' ),
                            '<strong>' . esc_html( $masked_email ) . '</strong>'
                        );
                        ?>
                    </p>

                    <form id="dps-2fa-form" class="dps-2fa-form">
                        <input type="hidden" name="session_key" value="<?php echo esc_attr( $session_key ); ?>">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">

                        <div class="dps-2fa-code-inputs" id="dps-2fa-inputs">
                            <?php for ( $i = 0; $i < 6; $i++ ) : ?>
                            <input
                                type="text"
                                maxlength="1"
                                inputmode="numeric"
                                pattern="[0-9]"
                                class="dps-2fa-digit"
                                data-index="<?php echo esc_attr( $i ); ?>"
                                autocomplete="one-time-code"
                                aria-label="<?php echo esc_attr( sprintf( __( 'Dígito %d', 'dps-client-portal' ), $i + 1 ) ); ?>"
                                required
                            >
                            <?php endfor; ?>
                        </div>

                        <div id="dps-2fa-feedback" class="dps-portal-access__feedback" style="display:none;"></div>

                        <button type="submit" class="dps-portal-access__email-button" id="dps-2fa-submit" style="width:100%;margin-top:16px;">
                            <?php echo esc_html__( 'Verificar Código', 'dps-client-portal' ); ?>
                        </button>
                    </form>

                    <p class="dps-portal-access__note" style="margin-top:16px;">
                        <?php echo esc_html__( 'O código expira em 10 minutos. Se não recebeu, verifique a pasta de spam ou solicite um novo link.', 'dps-client-portal' ); ?>
                    </p>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var form = document.getElementById('dps-2fa-form');
            var inputs = document.querySelectorAll('.dps-2fa-digit');
            var submitBtn = document.getElementById('dps-2fa-submit');
            var feedback = document.getElementById('dps-2fa-feedback');

            // Auto-focus first input
            if (inputs.length > 0) inputs[0].focus();

            // Handle input navigation
            inputs.forEach(function(input, idx) {
                input.addEventListener('input', function(e) {
                    var val = this.value.replace(/\D/g, '');
                    this.value = val;
                    if (val && idx < inputs.length - 1) {
                        inputs[idx + 1].focus();
                    }
                    // Auto-submit when all filled
                    if (idx === inputs.length - 1 && val) {
                        var allFilled = true;
                        inputs.forEach(function(inp) { if (!inp.value) allFilled = false; });
                        if (allFilled) form.dispatchEvent(new Event('submit'));
                    }
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && idx > 0) {
                        inputs[idx - 1].focus();
                    }
                });
                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    for (var i = 0; i < Math.min(text.length, 6); i++) {
                        inputs[i].value = text[i];
                    }
                    if (text.length >= 6) {
                        inputs[5].focus();
                        form.dispatchEvent(new Event('submit'));
                    } else if (text.length > 0) {
                        inputs[Math.min(text.length, 5)].focus();
                    }
                });
            });

            // Form submission
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var code = '';
                    inputs.forEach(function(inp) { code += inp.value; });
                    if (code.length !== 6) {
                        feedback.textContent = '<?php echo esc_js( __( 'Digite os 6 dígitos do código.', 'dps-client-portal' ) ); ?>';
                        feedback.style.display = 'block';
                        feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.textContent = '<?php echo esc_js( __( 'Verificando...', 'dps-client-portal' ) ); ?>';
                    feedback.style.display = 'none';

                    var sessionKey = form.querySelector('input[name="session_key"]').value;
                    var nonce = form.querySelector('input[name="_wpnonce"]').value;

                    fetch('<?php echo esc_url( $ajax_url ); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=dps_verify_2fa_code&code=' + encodeURIComponent(code) + '&session_key=' + encodeURIComponent(sessionKey) + '&_wpnonce=' + encodeURIComponent(nonce)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '<?php echo esc_js( __( 'Verificar Código', 'dps-client-portal' ) ); ?>';
                        if (data && data.success) {
                            feedback.textContent = data.data.message || '<?php echo esc_js( __( 'Verificado!', 'dps-client-portal' ) ); ?>';
                            feedback.style.display = 'block';
                            feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--success';
                            setTimeout(function() { window.location.reload(); }, 800);
                        } else {
                            var msg = (data && data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Código inválido.', 'dps-client-portal' ) ); ?>';
                            feedback.textContent = msg;
                            feedback.style.display = 'block';
                            feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                            // Clear inputs on error
                            inputs.forEach(function(inp) { inp.value = ''; });
                            inputs[0].focus();
                        }
                    })
                    .catch(function() {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '<?php echo esc_js( __( 'Verificar Código', 'dps-client-portal' ) ); ?>';
                        feedback.textContent = '<?php echo esc_js( __( 'Erro de conexão. Tente novamente.', 'dps-client-portal' ) ); ?>';
                        feedback.style.display = 'block';
                        feedback.className = 'dps-portal-access__feedback dps-portal-access__feedback--error';
                    });
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Ofusca o e-mail para exibição (ex: j***@gmail.com).
     *
     * @param string $email E-mail a ofuscar.
     * @return string E-mail ofuscado.
     */
    private function mask_email( $email ) {
        $parts = explode( '@', $email );
        if ( count( $parts ) !== 2 ) {
            return '***@***';
        }
        $local  = $parts[0];
        $domain = $parts[1];
        $masked = substr( $local, 0, 1 ) . str_repeat( '*', max( 1, strlen( $local ) - 1 ) ) . '@' . $domain;
        return $masked;
    }

    /**
     * Grava uma entrada de estado com expiracao.
     *
     * @param string $key Chave interna.
     * @param array  $data Dados a persistir.
     * @param int    $ttl_seconds Tempo de vida em segundos.
     * @return void
     */
    private function set_state_entry( $key, array $data, $ttl_seconds ) {
        $state = $this->get_state();
        $now   = time();

        $this->purge_expired_entries( $state, $now );

        $data['expires_at'] = $now + max( 1, absint( $ttl_seconds ) );
        $state[ $key ]      = $data;

        $this->save_state( $state );
    }

    /**
     * Atualiza uma entrada de estado preservando a expiracao original.
     *
     * @param string $key Chave interna.
     * @param array  $data Dados atualizados.
     * @return void
     */
    private function save_state_entry( $key, array $data ) {
        $state = $this->get_state();

        if ( isset( $state[ $key ] ) && is_array( $state[ $key ] ) && isset( $state[ $key ]['expires_at'] ) ) {
            $data['expires_at'] = $state[ $key ]['expires_at'];
        }

        $state[ $key ] = $data;
        $this->save_state( $state );
    }

    /**
     * Recupera uma entrada de estado se ainda estiver valida.
     *
     * @param string $key Chave interna.
     * @return array|false
     */
    private function get_state_entry( $key ) {
        $state = $this->get_state();
        $now   = time();

        $this->purge_expired_entries( $state, $now );

        if ( ! isset( $state[ $key ] ) || ! is_array( $state[ $key ] ) ) {
            $this->save_state( $state );
            return false;
        }

        if ( $this->is_entry_expired( $state[ $key ], $now ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
            return false;
        }

        $this->save_state( $state );
        return $state[ $key ];
    }

    /**
     * Remove uma entrada de estado.
     *
     * @param string $key Chave interna.
     * @return void
     */
    private function delete_state_entry( $key ) {
        $state = $this->get_state();

        if ( isset( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
        }
    }

    /**
     * Recupera o estado persistido do fluxo 2FA.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_state() {
        $state = get_option( self::STORAGE_OPTION, [] );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Persiste o estado do fluxo 2FA.
     *
     * @param array<string, array<string, mixed>> $state Estado atualizado.
     * @return void
     */
    private function save_state( array $state ) {
        update_option( self::STORAGE_OPTION, $state, false );
    }

    /**
     * Remove entradas expiradas.
     *
     * @param array<string, array<string, mixed>> $state Estado por referencia.
     * @param int                                 $now Timestamp atual.
     * @return void
     */
    private function purge_expired_entries( array &$state, $now ) {
        foreach ( $state as $key => $entry ) {
            if ( ! is_array( $entry ) || $this->is_entry_expired( $entry, $now ) ) {
                unset( $state[ $key ] );
            }
        }
    }

    /**
     * Verifica se uma entrada expirou.
     *
     * @param array<string, mixed> $entry Entrada de estado.
     * @param int                  $now Timestamp atual.
     * @return bool
     */
    private function is_entry_expired( array $entry, $now ) {
        return empty( $entry['expires_at'] ) || (int) $entry['expires_at'] <= (int) $now;
    }

    /**
     * Gera chave interna sem persistir a chave publica da sessao 2FA.
     *
     * @param string $session_key Chave publica da sessao 2FA.
     * @return string
     */
    private function build_session_state_key( $session_key ) {
        return hash( 'sha256', (string) $session_key );
    }
}
