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
     * Registra menu admin como submenu do desi-pet-shower.
     */
    public function register_menu(): void {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Space Groomers', 'dps-game' ),
            __( 'Space Groomers', 'dps-game' ),
            'manage_options',
            'dps-space-groomers',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Renderiza a pagina admin.
     */
    public function render_admin_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Space Groomers: Invasao das Pulgas', 'dps-game' ) . '</h1>';
        echo '<p>' . esc_html__( 'Jogo tematico para engajamento de clientes no portal.', 'dps-game' ) . '</p>';
        echo '<p>' . esc_html__( 'Use o shortcode [dps_space_groomers] em qualquer pagina ou o jogo aparece automaticamente na aba Inicio do portal.', 'dps-game' ) . '</p>';
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
        $this->enqueue_game_assets( 'portal', $client_id );

        echo '<div class="dps-game-portal-card">';
        echo '<div class="dps-game-portal-card__header">';
        echo '<span class="dps-game-portal-card__icon">&#128640;</span>';
        echo '<div class="dps-game-portal-card__info">';
        echo '<h3 class="dps-game-portal-card__title">' . esc_html__( 'Space Groomers: Invasao das Pulgas', 'dps-game' ) . '</h3>';
        echo '<p class="dps-game-portal-card__desc">' . esc_html__( 'Defenda o Pet-Planeta e faca o banho mais divertido.', 'dps-game' ) . '</p>';
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
                'endpoints'   => [
                    'progress' => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress' ) ),
                    'sync'     => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress/sync' ) ),
                ],
                'storage'     => [
                    'progressKey'  => 'dps_sg_progress_v1',
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
        $container_id = 'dps-space-groomers-' . esc_attr( $context );
        ?>
        <div id="<?php echo esc_attr( $container_id ); ?>" class="dps-space-groomers" data-context="<?php echo esc_attr( $context ); ?>">
            <div class="dps-sg-wrapper">
                <canvas class="dps-sg-canvas" width="480" height="640"></canvas>

                <div class="dps-sg-hud">
                    <div class="dps-sg-hud__score">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Pontos', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-score">0</span>
                    </div>
                    <div class="dps-sg-hud__wave">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Onda', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-wave">1</span>
                    </div>
                    <div class="dps-sg-hud__lives">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Vida', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-lives">&#10084;&#65039;&#10084;&#65039;&#10084;&#65039;</span>
                    </div>
                </div>

                <div class="dps-sg-combo dps-sg-combo--hidden">
                    <span class="dps-sg-combo__text">x2</span>
                    <span class="dps-sg-combo__hint"><?php echo esc_html__( 'boa sequencia', 'dps-game' ); ?></span>
                    <span class="dps-sg-combo__meter"><span class="dps-sg-combo__fill"></span></span>
                </div>

                <div class="dps-sg-goal" aria-live="polite">
                    <span class="dps-sg-goal__label"><?php echo esc_html__( 'Missao de hoje', 'dps-game' ); ?></span>
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
                    <span class="dps-sg-special-bar__label"><?php echo esc_html__( 'Especial', 'dps-game' ); ?></span>
                    <div class="dps-sg-special-bar__track"><div class="dps-sg-special-bar__fill"></div></div>
                </div>

                <div class="dps-sg-mobile-controls">
                    <p class="dps-sg-mobile-controls__hint"><?php echo esc_html__( 'Arraste para mover - tiro automatico', 'dps-game' ); ?></p>
                    <button type="button" class="dps-sg-btn dps-sg-btn--special" aria-label="<?php echo esc_attr__( 'Especial', 'dps-game' ); ?>" disabled>&#9889;</button>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--start">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">&#128640; Space Groomers</h2>
                        <p class="dps-sg-overlay__subtitle"><?php echo esc_html__( 'Invasao das Pulgas', 'dps-game' ); ?></p>
                        <p class="dps-sg-overlay__desc"><?php echo esc_html__( 'Defenda o Pet-Planeta em runs curtas, responsivas e com vontade de tentar de novo.', 'dps-game' ); ?></p>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Seu recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <div class="dps-sg-start-meta">
                            <p class="dps-sg-start-meta__streak"><?php echo esc_html__( 'Sequencia diaria:', 'dps-game' ); ?> <span class="dps-sg-start-streak-value">0</span></p>
                            <p class="dps-sg-start-meta__mission-title"></p>
                            <p class="dps-sg-start-meta__mission-progress"></p>
                            <p class="dps-sg-start-meta__badges"></p>
                        </div>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Toque para comecar', 'dps-game' ); ?></button>
                        <div class="dps-sg-overlay__legend">
                            <span class="dps-sg-overlay__legend-item">&#129532; <?php echo esc_html__( 'Shampoo Turbo: 3 tiros por disparo', 'dps-game' ); ?></span>
                            <span class="dps-sg-overlay__legend-item">&#129529; <?php echo esc_html__( 'Toalha: limpa a fileira mais baixa', 'dps-game' ); ?></span>
                        </div>
                        <p class="dps-sg-overlay__controls-hint"><small><?php echo esc_html__( 'Arraste para mover. Em 4 acertos seguidos voce entra em combo x2.', 'dps-game' ); ?></small></p>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--gameover dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">&#128575; <?php echo esc_html__( 'Pet ficou estressado!', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Pontuacao:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__mission"></p>
                        <p class="dps-sg-overlay__records"></p>
                        <div class="dps-sg-overlay__unlocks dps-sg-overlay__unlocks--hidden">
                            <p class="dps-sg-overlay__unlocks-title"><?php echo esc_html__( 'Novos badges locais', 'dps-game' ); ?></p>
                            <p class="dps-sg-overlay__unlocks-list"></p>
                        </div>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Tentar de novo', 'dps-game' ); ?></button>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--victory dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">&#127881; <?php echo esc_html__( 'Banho Completo!', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Pontuacao:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__mission"></p>
                        <p class="dps-sg-overlay__records"></p>
                        <div class="dps-sg-overlay__unlocks dps-sg-overlay__unlocks--hidden">
                            <p class="dps-sg-overlay__unlocks-title"><?php echo esc_html__( 'Novos badges locais', 'dps-game' ); ?></p>
                            <p class="dps-sg-overlay__unlocks-list"></p>
                        </div>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Jogar de novo', 'dps-game' ); ?></button>
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
