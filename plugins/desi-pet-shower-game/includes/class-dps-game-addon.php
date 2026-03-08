<?php
/**
 * Classe principal do add-on Space Groomers.
 *
 * Registra shortcode, assets, integracao com portal e menu admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Game_Addon {

    private static ?DPS_Game_Addon $instance = null;

    /**
     * Garante que a configuracao do script seja localizada apenas uma vez por request.
     *
     * @var bool
     */
    private bool $script_localized = false;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'dps_space_groomers', [ $this, 'render_game_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'dps_portal_after_inicio_content', [ $this, 'render_portal_card' ], 10, 1 );
    }

    /**
     * Branding simples do jogo para evitar strings duplicadas.
     *
     * @return array<string, string>
     */
    private function get_branding_config(): array {
        return [
            'brand_name'  => defined( 'DPS_GAME_BRAND_NAME' ) ? DPS_GAME_BRAND_NAME : 'Desi Pet Shower',
            'game_name'   => defined( 'DPS_GAME_DISPLAY_NAME' ) ? DPS_GAME_DISPLAY_NAME : 'Desi Pet Shower: Space Groomers',
            'tagline'     => defined( 'DPS_GAME_TAGLINE' ) ? DPS_GAME_TAGLINE : 'Banho em ordem, pet brilhando.',
            'portal_desc' => __( 'Missao oficial de banho e tosa para limpar pulgas, pelos e bagunca em runs rapidas.', 'dps-game' ),
        ];
    }

    /**
     * Registra menu admin como submenu do desi-pet-shower.
     */
    public function register_menu(): void {
        $branding = $this->get_branding_config();

        add_submenu_page(
            'desi-pet-shower',
            $branding['game_name'],
            sprintf( __( 'Game %s', 'dps-game' ), $branding['brand_name'] ),
            'manage_options',
            'dps-space-groomers',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Renderiza a pagina admin.
     */
    public function render_admin_page(): void {
        $branding = $this->get_branding_config();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $branding['game_name'] ) . '</h1>';
        echo '<p>' . esc_html( $branding['tagline'] ) . '</p>';
        echo '<p>' . esc_html__( 'Experiencia oficial de arcade da Desi Pet Shower para o portal, com linguagem leve e tema de banho e tosa.', 'dps-game' ) . '</p>';
        echo '<p>' . esc_html__( 'Use o shortcode [dps_space_groomers] em qualquer pagina ou conte com a exibicao automatica na aba Inicio do portal.', 'dps-game' ) . '</p>';
        echo '</div>';
    }

    /**
     * Registra assets do frontend.
     */
    public function enqueue_frontend_assets(): void {
        wp_register_style(
            'dps-space-groomers',
            DPS_GAME_URL . 'assets/css/space-groomers.css',
            [],
            DPS_GAME_VERSION
        );

        wp_register_script(
            'dps-space-groomers',
            DPS_GAME_URL . 'assets/js/space-groomers.js',
            [],
            DPS_GAME_VERSION,
            true
        );
    }

    /**
     * Renderiza o shortcode do jogo.
     *
     * @return string
     */
    public function render_game_shortcode(): string {
        $this->enqueue_game_assets( 'shortcode', 0 );

        ob_start();
        $this->render_game_container( 'shortcode' );
        return (string) ob_get_clean();
    }

    /**
     * Renderiza o card do jogo na aba Inicio do portal.
     *
     * @param int $client_id Cliente autenticado do portal.
     * @return void
     */
    public function render_portal_card( int $client_id ): void {
        $branding = $this->get_branding_config();

        $this->enqueue_game_assets( 'portal', $client_id );

        echo '<div class="dps-game-portal-card">';
        echo '<div class="dps-game-portal-card__header">';
        echo '<span class="dps-game-portal-card__icon">&#128062;</span>';
        echo '<div class="dps-game-portal-card__info">';
        echo '<h3 class="dps-game-portal-card__title">' . esc_html( $branding['game_name'] ) . '</h3>';
        echo '<p class="dps-game-portal-card__desc">' . esc_html( $branding['portal_desc'] ) . '</p>';
        echo '</div>';
        echo '</div>';

        $this->render_game_container( 'portal' );

        echo '</div>';
    }

    /**
     * Enfileira assets e injeta configuracao do sync.
     *
     * @param string $context   Contexto de renderizacao.
     * @param int    $client_id Cliente conhecido no portal, quando houver.
     * @return void
     */
    private function enqueue_game_assets( string $context, int $client_id ): void {
        wp_enqueue_style( 'dps-space-groomers' );
        wp_enqueue_script( 'dps-space-groomers' );
        $this->localize_script( $context, $client_id );
    }

    /**
     * Injeta configuracao do jogo para adapters e sync remoto.
     *
     * @param string $context   Contexto atual.
     * @param int    $client_id Cliente conhecido.
     * @return void
     */
    private function localize_script( string $context, int $client_id ): void {
        if ( $this->script_localized ) {
            return;
        }

        $branding           = $this->get_branding_config();
        $resolved_client_id = $client_id > 0 ? $client_id : DPS_Game_Progress_Service::get_authenticated_client_id();
        $sync_enabled       = $resolved_client_id > 0;

        wp_localize_script(
            'dps-space-groomers',
            'dpsSpaceGroomersConfig',
            [
                'context'     => sanitize_key( $context ),
                'clientId'    => $resolved_client_id,
                'syncEnabled' => $sync_enabled,
                'restUrl'     => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/' ) ),
                'nonce'       => wp_create_nonce( 'dps_game_progress' ),
                'branding'    => [
                    'brandName' => $branding['brand_name'],
                    'gameName'  => $branding['game_name'],
                    'tagline'   => $branding['tagline'],
                ],
                'endpoints'   => [
                    'progress' => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress' ) ),
                    'sync'     => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress/sync' ) ),
                ],
                'storage'     => [
                    'progressKey'    => 'dps_sg_progress_v1',
                    'legacyScoreKey' => 'dps_sg_highscore',
                ],
                'i18n'        => [
                    'syncReady'      => __( 'Progresso sincronizado com o portal.', 'dps-game' ),
                    'syncFallback'   => __( 'Sem portal autenticado: usando progresso local.', 'dps-game' ),
                    'syncError'      => __( 'Nao foi possivel sincronizar agora. O progresso local segue ativo.', 'dps-game' ),
                    'rewardUnlocked' => __( 'Pontos de fidelidade recebidos no portal.', 'dps-game' ),
                ],
            ]
        );

        $this->script_localized = true;
    }

    /**
     * Renderiza o container do jogo (canvas + UI).
     *
     * @param string $context Contexto da renderizacao.
     * @return void
     */
    private function render_game_container( string $context ): void {
        $branding     = $this->get_branding_config();
        $container_id = 'dps-space-groomers-' . esc_attr( $context );
        ?>
        <div id="<?php echo esc_attr( $container_id ); ?>" class="dps-space-groomers" data-context="<?php echo esc_attr( $context ); ?>">
            <div class="dps-sg-wrapper">
                <canvas class="dps-sg-canvas" width="480" height="640"></canvas>
                <p class="dps-sg-status-live" aria-live="polite" aria-atomic="true"></p>

                <div class="dps-sg-hud">
                    <div class="dps-sg-hud__score">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Brilho', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-score">0</span>
                    </div>
                    <div class="dps-sg-hud__wave">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Etapa', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-wave">1</span>
                    </div>
                    <div class="dps-sg-hud__lives">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Cuidado', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-lives">&#10084;&#65039;&#10084;&#65039;&#10084;&#65039;</span>
                    </div>
                </div>

                <div class="dps-sg-combo dps-sg-combo--hidden">
                    <span class="dps-sg-combo__text">x2</span>
                    <span class="dps-sg-combo__hint"><?php echo esc_html__( 'ritmo do cuidado', 'dps-game' ); ?></span>
                    <span class="dps-sg-combo__meter"><span class="dps-sg-combo__fill"></span></span>
                </div>

                <div class="dps-sg-goal" aria-live="polite">
                    <span class="dps-sg-goal__label"><?php echo esc_html__( 'Cuidado do dia', 'dps-game' ); ?></span>
                    <span class="dps-sg-goal__title"></span>
                    <span class="dps-sg-goal__progress"></span>
                    <span class="dps-sg-goal__meter"><span class="dps-sg-goal__fill"></span></span>
                    <span class="dps-sg-goal__remaining"></span>
                </div>

                <div class="dps-sg-toast dps-sg-toast--hidden" aria-live="polite">
                    <span class="dps-sg-toast__title"></span>
                    <span class="dps-sg-toast__desc"></span>
                </div>

                <div class="dps-sg-powerup-indicator dps-sg-powerup-indicator--hidden">
                    <span class="dps-sg-powerup-indicator__icon"></span>
                    <span class="dps-sg-powerup-indicator__copy">
                        <span class="dps-sg-powerup-indicator__name"></span>
                        <span class="dps-sg-powerup-indicator__desc"></span>
                    </span>
                    <div class="dps-sg-powerup-indicator__bar"><div class="dps-sg-powerup-indicator__fill"></div></div>
                </div>

                <div class="dps-sg-special-bar">
                    <span class="dps-sg-special-bar__label"><?php echo esc_html__( 'Espuma total', 'dps-game' ); ?></span>
                    <div class="dps-sg-special-bar__track"><div class="dps-sg-special-bar__fill"></div></div>
                </div>

                <div class="dps-sg-mobile-controls">
                    <p class="dps-sg-mobile-controls__hint"><?php echo esc_html__( 'Arraste para cuidar. O jato sai sozinho.', 'dps-game' ); ?></p>
                    <button type="button" class="dps-sg-btn dps-sg-btn--special" aria-label="<?php echo esc_attr__( 'Espuma total', 'dps-game' ); ?>" disabled>&#9889;</button>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--start">
                    <div class="dps-sg-overlay__content">
                        <p class="dps-sg-overlay__eyebrow"><?php echo esc_html( sprintf( __( 'Jogo oficial da %s', 'dps-game' ), $branding['brand_name'] ) ); ?></p>
                        <h2 class="dps-sg-overlay__title"><?php echo esc_html( $branding['game_name'] ); ?></h2>
                        <p class="dps-sg-overlay__subtitle"><?php echo esc_html( $branding['tagline'] ); ?></p>
                        <p class="dps-sg-overlay__desc"><?php echo esc_html( sprintf( __( 'Limpe pulgas, bolos de pelo e bagunca do banho em uma missao rapida da %s.', 'dps-game' ), $branding['brand_name'] ) ); ?></p>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Melhor brilho:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <div class="dps-sg-start-meta">
                            <p class="dps-sg-start-meta__streak"><?php echo esc_html__( 'Rotina do dia:', 'dps-game' ); ?> <span class="dps-sg-start-streak-value">0</span></p>
                            <p class="dps-sg-start-meta__mission-title"></p>
                            <p class="dps-sg-start-meta__mission-progress"></p>
                            <p class="dps-sg-start-meta__badges"></p>
                        </div>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Comecar cuidado', 'dps-game' ); ?></button>
                        <div class="dps-sg-overlay__legend">
                            <span class="dps-sg-overlay__legend-item">
                                <strong>&#129532; <?php echo esc_html__( 'Espuma Turbo', 'dps-game' ); ?></strong>
                                <small><?php echo esc_html__( 'triplica os jatos de limpeza', 'dps-game' ); ?></small>
                            </span>
                            <span class="dps-sg-overlay__legend-item">
                                <strong>&#129529; <?php echo esc_html__( 'Toalha Relampago', 'dps-game' ); ?></strong>
                                <small><?php echo esc_html__( 'varre a faixa mais suja', 'dps-game' ); ?></small>
                            </span>
                        </div>
                        <p class="dps-sg-overlay__controls-hint"><small><?php echo esc_html__( 'Arraste para mover. Com 4 acertos seguidos o cuidado entra no embalo.', 'dps-game' ); ?></small></p>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--gameover dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <p class="dps-sg-overlay__eyebrow"><?php echo esc_html__( 'Mais um banho?', 'dps-game' ); ?></p>
                        <h2 class="dps-sg-overlay__title"><?php echo esc_html__( 'Ops, a bagunca venceu.', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Brilho final:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <div class="dps-sg-overlay__result-grid"><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Melhor embalo', 'dps-game' ); ?></span><strong class="dps-sg-result-combo">0</strong></div><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Etapa', 'dps-game' ); ?></span><strong class="dps-sg-result-wave">1</strong></div><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Tempo', 'dps-game' ); ?></span><strong class="dps-sg-result-time">0s</strong></div></div>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__mission"></p>
                        <p class="dps-sg-overlay__records"></p>
                        <div class="dps-sg-overlay__unlocks dps-sg-overlay__unlocks--hidden">
                            <p class="dps-sg-overlay__unlocks-title"><?php echo esc_html__( 'Mimos liberados', 'dps-game' ); ?></p>
                            <p class="dps-sg-overlay__unlocks-list"></p>
                        </div>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Melhor brilho:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Tentar novo banho', 'dps-game' ); ?></button>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--victory dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <p class="dps-sg-overlay__eyebrow"><?php echo esc_html__( 'Cuidado concluido', 'dps-game' ); ?></p>
                        <h2 class="dps-sg-overlay__title"><?php echo esc_html__( 'Banho entregue!', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Brilho final:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <div class="dps-sg-overlay__result-grid"><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Melhor embalo', 'dps-game' ); ?></span><strong class="dps-sg-result-combo">0</strong></div><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Etapa', 'dps-game' ); ?></span><strong class="dps-sg-result-wave">1</strong></div><div class="dps-sg-overlay__result-card"><span class="dps-sg-overlay__result-label"><?php echo esc_html__( 'Tempo', 'dps-game' ); ?></span><strong class="dps-sg-result-time">0s</strong></div></div>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__mission"></p>
                        <p class="dps-sg-overlay__records"></p>
                        <div class="dps-sg-overlay__unlocks dps-sg-overlay__unlocks--hidden">
                            <p class="dps-sg-overlay__unlocks-title"><?php echo esc_html__( 'Mimos liberados', 'dps-game' ); ?></p>
                            <p class="dps-sg-overlay__unlocks-list"></p>
                        </div>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Melhor brilho:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Jogar outra rodada', 'dps-game' ); ?></button>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--wave dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title dps-sg-wave-title"></h2>
                        <p class="dps-sg-overlay__subtitle dps-sg-wave-bonus"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
