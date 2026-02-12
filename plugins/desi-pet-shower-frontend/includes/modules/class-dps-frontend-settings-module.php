<?php
/**
 * Módulo de Configurações do Frontend Add-on (Fase 4).
 *
 * Integra o add-on ao sistema de abas de configurações do DPS via
 * DPS_Settings_Frontend::register_tab(). Quando habilitado, registra uma
 * aba "Frontend" na página de configurações [dps_configuracoes] com
 * controles de feature flags para todos os módulos.
 *
 * Estratégia: intervenção mínima. O legado (DPS_Settings_Frontend) continua
 * gerenciando o rendering das abas, navegação e salvamento. O módulo apenas:
 *   1. Registra uma aba via hook dps_settings_register_tabs.
 *   2. Renderiza conteúdo com controles de feature flags.
 *   3. Processa salvamento das flags via hook dps_settings_save_save_frontend.
 *
 * Hooks e contratos preservados:
 *   - dps_settings_register_tabs (usado para registrar aba)
 *   - dps_settings_save_{action} (mecanismo padrão de salvamento)
 *   - DPS_Settings_Frontend::register_tab() (API moderna de registro)
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 * @since   1.3.0 Fase 4 — módulo operacional com aba de configurações.
 * @since   1.5.0 Fase 6 — telemetria de uso exibida na aba.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Settings_Module {

    /**
     * Slug da aba no sistema de configurações.
     */
    private const TAB_SLUG = 'frontend';

    /**
     * Prioridade da aba (após todas as abas core e add-ons existentes).
     */
    private const TAB_PRIORITY = 110;

    /**
     * Indica se o sistema de settings está disponível.
     */
    private bool $settingsAvailable = false;

    public function __construct(
        private readonly DPS_Frontend_Logger        $logger,
        private readonly DPS_Frontend_Feature_Flags $flags,
    ) {}

    /**
     * Inicializa o módulo quando habilitado pela feature flag.
     *
     * Registra uma aba de configurações do Frontend Add-on no sistema de abas
     * do DPS via hook dps_settings_register_tabs.
     */
    public function boot(): void {
        $this->settingsAvailable = class_exists( 'DPS_Settings_Frontend' )
            && method_exists( DPS_Settings_Frontend::class, 'register_tab' );

        if ( ! $this->settingsAvailable ) {
            $this->logger->warning( 'Módulo Settings ativado mas DPS_Settings_Frontend não encontrado.' );
            return;
        }

        // Registra aba via hook padrão do sistema de configurações
        add_action( 'dps_settings_register_tabs', [ $this, 'registerTab' ] );

        // Registra handler de salvamento via hook dinâmico do settings
        add_action( 'dps_settings_save_save_frontend', [ $this, 'handleSave' ] );

        $this->logger->info( 'Módulo Settings ativado (aba de configurações registrada).' );
    }

    /**
     * Registra a aba "Frontend" na página de configurações.
     *
     * Callback do hook dps_settings_register_tabs.
     * Usa a API moderna DPS_Settings_Frontend::register_tab().
     */
    public function registerTab(): void {
        DPS_Settings_Frontend::register_tab(
            self::TAB_SLUG,
            __( 'Frontend', 'dps-frontend-addon' ),
            [ $this, 'renderTab' ],
            self::TAB_PRIORITY
        );
    }

    /**
     * Renderiza o conteúdo da aba "Frontend".
     *
     * Exibe controles de feature flags para todos os módulos do add-on,
     * com informações de status e descrição por módulo.
     */
    public function renderTab(): void {
        $all_flags = $this->flags->all();

        $modules = [
            'registration' => [
                'label'       => __( 'Cadastro (v1)', 'dps-frontend-addon' ),
                'description' => __( 'Assume shortcode [dps_registration_form] com wrapper M3 sobre o add-on legado.', 'dps-frontend-addon' ),
                'phase'       => __( 'Fase 2', 'dps-frontend-addon' ),
            ],
            'booking' => [
                'label'       => __( 'Agendamento (v1)', 'dps-frontend-addon' ),
                'description' => __( 'Assume shortcode [dps_booking_form] com wrapper M3 sobre o add-on legado.', 'dps-frontend-addon' ),
                'phase'       => __( 'Fase 3', 'dps-frontend-addon' ),
            ],
            'settings' => [
                'label'       => __( 'Configurações', 'dps-frontend-addon' ),
                'description' => __( 'Registra aba de configurações do Frontend no painel administrativo.', 'dps-frontend-addon' ),
                'phase'       => __( 'Fase 4', 'dps-frontend-addon' ),
            ],
            'registration_v2' => [
                'label'       => __( 'Cadastro (v2 Nativo)', 'dps-frontend-addon' ),
                'description' => __( 'Shortcode [dps_registration_v2] — formulário nativo M3 Expressive, independente do legado.', 'dps-frontend-addon' ),
                'phase'       => __( 'Fase 7', 'dps-frontend-addon' ),
            ],
            'booking_v2' => [
                'label'       => __( 'Agendamento (v2 Nativo)', 'dps-frontend-addon' ),
                'description' => __( 'Shortcode [dps_booking_v2] — wizard nativo M3 Expressive, independente do legado.', 'dps-frontend-addon' ),
                'phase'       => __( 'Fase 7', 'dps-frontend-addon' ),
            ],
        ];
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'dps_settings_save', 'dps_settings_nonce' ); ?>
            <input type="hidden" name="dps_settings_action" value="save_frontend">

            <h3><?php esc_html_e( 'Módulos do Frontend Add-on', 'dps-frontend-addon' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Controle de rollout: habilite ou desabilite módulos individualmente. Desabilitar restaura o comportamento legado.', 'dps-frontend-addon' ); ?>
            </p>

            <table class="form-table" role="presentation">
                <tbody>
                    <?php foreach ( $modules as $slug => $module ) :
                        $enabled  = ! empty( $all_flags[ $slug ] );
                        $field_id = 'dps_frontend_flag_' . $slug;
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field_id ); ?>">
                                <?php echo esc_html( $module['label'] ); ?>
                                <span class="dps-settings-badge"><?php echo esc_html( $module['phase'] ); ?></span>
                            </label>
                        </th>
                        <td>
                            <fieldset>
                                <label for="<?php echo esc_attr( $field_id ); ?>">
                                    <input type="checkbox"
                                           id="<?php echo esc_attr( $field_id ); ?>"
                                           name="dps_frontend_flags[<?php echo esc_attr( $slug ); ?>]"
                                           value="1"
                                           <?php checked( $enabled ); ?>>
                                    <?php
                                    if ( $enabled ) {
                                        esc_html_e( 'Habilitado', 'dps-frontend-addon' );
                                    } else {
                                        esc_html_e( 'Desabilitado', 'dps-frontend-addon' );
                                    }
                                    ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html( $module['description'] ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Informações do Add-on', 'dps-frontend-addon' ); ?></h3>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Versão', 'dps-frontend-addon' ); ?></th>
                        <td><code><?php echo esc_html( DPS_FRONTEND_VERSION ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Módulos ativos', 'dps-frontend-addon' ); ?></th>
                        <td>
                            <code><?php echo esc_html( (string) count( array_filter( $all_flags ) ) ); ?></code>
                            <?php esc_html_e( 'de', 'dps-frontend-addon' ); ?>
                            <code><?php echo esc_html( (string) count( $all_flags ) ); ?></code>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php $this->renderCoexistenceStatus( $all_flags ); ?>

            <h3><?php esc_html_e( 'Telemetria de Uso', 'dps-frontend-addon' ); ?></h3>
            <p class="description">
                <?php esc_html_e( 'Contadores de renderização de shortcodes via módulo frontend. Usados para decisões de depreciação futura.', 'dps-frontend-addon' ); ?>
            </p>
            <table class="form-table" role="presentation">
                <tbody>
                    <?php
                    $counters = $this->logger->getUsageCounters();
                    $telemetry_modules = [
                        'registration'    => __( 'Cadastro (v1)', 'dps-frontend-addon' ),
                        'booking'         => __( 'Agendamento (v1)', 'dps-frontend-addon' ),
                        'registration_v2' => __( 'Cadastro (v2)', 'dps-frontend-addon' ),
                        'booking_v2'      => __( 'Agendamento (v2)', 'dps-frontend-addon' ),
                    ];
                    foreach ( $telemetry_modules as $t_slug => $t_label ) :
                        $count = $counters[ $t_slug ] ?? 0;
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $t_label ); ?></th>
                        <td>
                            <code><?php echo esc_html( number_format_i18n( $count ) ); ?></code>
                            <?php esc_html_e( 'renderizações via módulo frontend', 'dps-frontend-addon' ); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button( __( 'Salvar Configurações', 'dps-frontend-addon' ) ); ?>
        </form>
        <?php
    }

    /**
     * Processa salvamento das feature flags.
     *
     * Callback do hook dps_settings_save_save_frontend.
     * O nonce e a capability já foram verificados pelo DPS_Settings_Frontend::maybe_handle_save().
     */
    public function handleSave(): void {
        // Checkboxes: presença na POST = habilitado, ausência = desabilitado.
        // Apenas keys dos módulos conhecidos são aceitas (whitelist).
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by DPS_Settings_Frontend::maybe_handle_save()
        $raw_flags = isset( $_POST['dps_frontend_flags'] ) && is_array( $_POST['dps_frontend_flags'] )
            ? $_POST['dps_frontend_flags']
            : [];

        $known_modules = array_keys( $this->flags->all() );

        foreach ( $known_modules as $module ) {
            $is_checked = isset( $raw_flags[ $module ] ) && '1' === sanitize_text_field( wp_unslash( $raw_flags[ $module ] ) );
            if ( $is_checked ) {
                $this->flags->enable( $module );
            } else {
                $this->flags->disable( $module );
            }
        }

        $this->logger->info( 'Feature flags atualizadas: ' . wp_json_encode( $this->flags->all() ) );

        if ( class_exists( 'DPS_Message_Helper' ) ) {
            DPS_Message_Helper::add_success(
                __( 'Configurações do Frontend atualizadas com sucesso.', 'dps-frontend-addon' )
            );
        }
    }

    /**
     * Renderiza indicadores de coexistência v1/v2 e link para guia de migração.
     *
     * Exibe estado atual (v1 only, v2 only, coexistência, ou nenhum)
     * para Registration e Booking, com recomendação de migração.
     *
     * @since 2.3.0
     *
     * @param array<string, bool> $flags Feature flags ativas.
     */
    private function renderCoexistenceStatus( array $flags ): void {
        $pairs = [
            [
                'label' => __( 'Cadastro', 'dps-frontend-addon' ),
                'v1'    => ! empty( $flags['registration'] ),
                'v2'    => ! empty( $flags['registration_v2'] ),
            ],
            [
                'label' => __( 'Agendamento', 'dps-frontend-addon' ),
                'v1'    => ! empty( $flags['booking'] ),
                'v2'    => ! empty( $flags['booking_v2'] ),
            ],
        ];
        ?>
        <h3><?php esc_html_e( 'Status de Coexistência v1 / v2', 'dps-frontend-addon' ); ?></h3>
        <p class="description">
            <?php esc_html_e( 'Os módulos v1 (dual-run) e v2 (nativo) podem coexistir. Recomenda-se migrar gradualmente para v2.', 'dps-frontend-addon' ); ?>
        </p>
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ( $pairs as $pair ) :
                    if ( $pair['v1'] && $pair['v2'] ) {
                        $status = '⚡ ' . __( 'Coexistência (v1 + v2 ativos)', 'dps-frontend-addon' );
                        $style  = 'color: #b26a00;';
                    } elseif ( $pair['v2'] ) {
                        $status = '✅ ' . __( 'Somente v2 (nativo) — migração concluída', 'dps-frontend-addon' );
                        $style  = 'color: #1e7e34;';
                    } elseif ( $pair['v1'] ) {
                        $status = '📦 ' . __( 'Somente v1 (dual-run) — considere migrar para v2', 'dps-frontend-addon' );
                        $style  = 'color: #49454f;';
                    } else {
                        $status = '⏸️ ' . __( 'Nenhum ativo', 'dps-frontend-addon' );
                        $style  = 'color: #938f99;';
                    }
                ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $pair['label'] ); ?></th>
                    <td>
                        <span style="<?php echo esc_attr( $style ); ?>; font-weight: 500;">
                            <?php echo esc_html( $status ); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Guia de Migração', 'dps-frontend-addon' ); ?></th>
                    <td>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: file path */
                                esc_html__( 'Consulte o guia completo em %s para migrar de v1 para v2.', 'dps-frontend-addon' ),
                                '<code>docs/implementation/FRONTEND_V2_MIGRATION_GUIDE.md</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
