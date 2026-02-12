<?php
/**
 * Aviso de depreciação do dual-run (v1) do Frontend Add-on.
 *
 * Exibe banner administrativo quando módulos v1 estão ativos e módulos v2
 * correspondentes existem como alternativa nativa. Respeita dismissal por
 * usuário (transient de 30 dias).
 *
 * Parte da Fase 7.5 — Depreciação do Dual-Run.
 * Só exibe o aviso; a remoção efetiva dos módulos v1 requer aprovação
 * formal conforme FRONTEND_DEPRECATION_POLICY.md.
 *
 * @package DPS_Frontend_Addon
 * @since   2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Deprecation_Notice {

    /**
     * Transient key prefix para dismissal por usuário.
     */
    private const DISMISS_KEY = 'dps_frontend_v1_deprecation_dismissed_';

    /**
     * Duração do dismissal (30 dias).
     */
    private const DISMISS_DURATION = 30 * DAY_IN_SECONDS;

    /**
     * Nonce action para dismiss.
     */
    private const NONCE_ACTION = 'dps_dismiss_v1_deprecation';

    public function __construct(
        private readonly DPS_Frontend_Feature_Flags $flags,
        private readonly DPS_Frontend_Logger        $logger,
    ) {}

    /**
     * Inicializa os hooks para exibição e dismissal do aviso.
     */
    public function boot(): void {
        add_action( 'admin_notices', [ $this, 'maybeShowNotice' ] );
        add_action( 'wp_ajax_dps_dismiss_v1_deprecation', [ $this, 'handleDismiss' ] );
    }

    /**
     * Exibe aviso de depreciação se módulos v1 estiverem ativos.
     *
     * Condições para exibir:
     * 1. Pelo menos um módulo v1 (registration ou booking) está ativo
     * 2. O aviso não foi dispensado pelo usuário atual nos últimos 30 dias
     * 3. O usuário tem capability manage_options
     */
    public function maybeShowNotice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( get_transient( self::DISMISS_KEY . $user_id ) ) {
            return;
        }

        $v1_active = [];

        if ( $this->flags->isEnabled( 'registration' ) ) {
            $v1_active[] = 'Cadastro (v1)';
        }
        if ( $this->flags->isEnabled( 'booking' ) ) {
            $v1_active[] = 'Agendamento (v1)';
        }

        if ( empty( $v1_active ) ) {
            return;
        }

        $modules_list = implode( ', ', $v1_active );
        $nonce        = wp_create_nonce( self::NONCE_ACTION );
        ?>
        <div class="notice notice-warning is-dismissible dps-v1-deprecation-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <p>
                <strong><?php esc_html_e( 'desi.pet Frontend — Aviso de Migração', 'dps-frontend-addon' ); ?></strong>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: list of v1 modules */
                    esc_html__( 'Os módulos %s utilizam o modo dual-run (v1) que será descontinuado em versão futura. Recomendamos migrar para os módulos nativos V2, que oferecem melhor desempenho, independência do legado e design M3 Expressive completo.', 'dps-frontend-addon' ),
                    '<strong>' . esc_html( $modules_list ) . '</strong>'
                );
                ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: file path */
                    esc_html__( 'Consulte o guia de migração em %s para instruções detalhadas.', 'dps-frontend-addon' ),
                    '<code>docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md</code>'
                );
                ?>
            </p>
        </div>
        <script>
        (function() {
            var notice = document.querySelector('.dps-v1-deprecation-notice');
            if (!notice) return;
            notice.addEventListener('click', function(e) {
                if (e.target.classList.contains('notice-dismiss') || e.target.closest('.notice-dismiss')) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('action=dps_dismiss_v1_deprecation&_wpnonce=' + notice.dataset.nonce);
                }
            });
        })();
        </script>
        <?php

        $this->logger->info( 'Aviso de depreciação v1 exibido para módulos: ' . $modules_list );
    }

    /**
     * Processa dismissal via AJAX.
     */
    public function handleDismiss(): void {
        check_ajax_referer( self::NONCE_ACTION );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $user_id = get_current_user_id();
        set_transient( self::DISMISS_KEY . $user_id, true, self::DISMISS_DURATION );

        $this->logger->info( 'Aviso de depreciação v1 dispensado pelo usuário #' . $user_id );
        wp_send_json_success();
    }
}
