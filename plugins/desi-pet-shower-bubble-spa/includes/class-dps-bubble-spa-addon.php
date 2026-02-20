<?php
/**
 * Classe principal do add-on Bubble Spa Deluxe.
 *
 * Registra shortcode, assets, integração com portal e menu admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Bubble_Spa_Addon {

    private static ?DPS_Bubble_Spa_Addon $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'dps_bubble_spa', [ $this, 'render_game_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'dps_portal_after_inicio_content', [ $this, 'render_portal_card' ], 15, 1 );
    }

    /**
     * Registra menu admin como submenu do desi-pet-shower.
     */
    public function register_menu(): void {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Bubble Spa Deluxe', 'dps-bubble-spa' ),
            '🫧 ' . __( 'Bubble Spa', 'dps-bubble-spa' ),
            'manage_options',
            'dps-bubble-spa',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Página admin com preview do jogo.
     */
    public function render_admin_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Bubble Spa Deluxe', 'dps-bubble-spa' ) . '</h1>';
        echo '<p>' . esc_html__( 'Use o shortcode [dps_bubble_spa] para exibir o jogo em qualquer página.', 'dps-bubble-spa' ) . '</p>';
        echo '</div>';
    }

    /**
     * Registra assets (sem enqueue — apenas registro).
     */
    public function register_frontend_assets(): void {
        wp_register_style(
            'dps-bubble-spa',
            DPS_BUBBLE_SPA_URL . 'assets/css/bubble-spa.css',
            [],
            DPS_BUBBLE_SPA_VERSION
        );

        wp_register_script(
            'dps-bubble-spa',
            DPS_BUBBLE_SPA_URL . 'assets/js/bubble-spa.js',
            [],
            DPS_BUBBLE_SPA_VERSION,
            true
        );
    }

    /**
     * Shortcode [dps_bubble_spa].
     */
    public function render_game_shortcode(): string {
        wp_enqueue_style( 'dps-bubble-spa' );
        wp_enqueue_script( 'dps-bubble-spa' );

        ob_start();
        $this->render_game_container( 'shortcode' );
        return (string) ob_get_clean();
    }

    /**
     * Card do jogo na aba Início do portal.
     */
    public function render_portal_card( int $client_id ): void {
        wp_enqueue_style( 'dps-bubble-spa' );
        wp_enqueue_script( 'dps-bubble-spa' );

        echo '<div class="dps-game-portal-card">';
        echo '<div class="dps-game-portal-card__header">';
        echo '<span class="dps-game-portal-card__icon">🫧</span>';
        echo '<div class="dps-game-portal-card__info">';
        echo '<h3 class="dps-game-portal-card__title">' . esc_html__( 'Bubble Spa Deluxe', 'dps-bubble-spa' ) . '</h3>';
        echo '<p class="dps-game-portal-card__desc">' . esc_html__( 'Estoure bolhas e impeça que a sujeira chegue ao ralo! 🫧', 'dps-bubble-spa' ) . '</p>';
        echo '</div>';
        echo '</div>';

        $this->render_game_container( 'portal' );

        echo '</div>';
    }

    /**
     * Renderiza container do jogo (canvas + HUD + overlays).
     */
    private function render_game_container( string $context ): void {
        $container_id = 'dps-bubble-spa-' . esc_attr( $context );
        ?>
        <div id="<?php echo esc_attr( $container_id ); ?>" class="dps-bubble-spa" data-context="<?php echo esc_attr( $context ); ?>">
            <div class="dps-bs-wrapper">
                <canvas class="dps-bs-canvas" width="480" height="720"></canvas>

                <!-- HUD -->
                <div class="dps-bs-hud">
                    <div class="dps-bs-hud__item">
                        <span class="dps-bs-hud__label"><?php echo esc_html__( 'Fase', 'dps-bubble-spa' ); ?></span>
                        <span class="dps-bs-hud__value dps-bs-level">1</span>
                    </div>
                    <div class="dps-bs-hud__item dps-bs-hud__stars-box">
                        <span class="dps-bs-star" data-star="1">☆</span>
                        <span class="dps-bs-star" data-star="2">☆</span>
                        <span class="dps-bs-star" data-star="3">☆</span>
                    </div>
                    <div class="dps-bs-hud__item">
                        <span class="dps-bs-hud__label"><?php echo esc_html__( 'Score', 'dps-bubble-spa' ); ?></span>
                        <span class="dps-bs-hud__value dps-bs-score">0</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="dps-bs-progress">
                    <div class="dps-bs-progress__track">
                        <div class="dps-bs-progress__fill"></div>
                        <div class="dps-bs-progress__marker" data-pct="25"></div>
                        <div class="dps-bs-progress__marker" data-pct="50"></div>
                        <div class="dps-bs-progress__marker" data-pct="75"></div>
                    </div>
                </div>

                <!-- Combo feedback -->
                <div class="dps-bs-combo dps-bs-combo--hidden">
                    <span class="dps-bs-combo__text"></span>
                </div>

                <!-- Power-up indicator -->
                <div class="dps-bs-powerup-ind dps-bs-powerup-ind--hidden">
                    <span class="dps-bs-powerup-ind__icon"></span>
                    <span class="dps-bs-powerup-ind__name"></span>
                </div>

                <!-- Swap bubble button -->
                <div class="dps-bs-swap">
                    <canvas class="dps-bs-swap__next" width="40" height="40"></canvas>
                    <span class="dps-bs-swap__label"><?php echo esc_html__( 'Trocar', 'dps-bubble-spa' ); ?></span>
                </div>

                <!-- Pause button -->
                <button type="button" class="dps-bs-pause-btn dps-bs-pause-btn--hidden" aria-label="<?php echo esc_attr__( 'Pausar', 'dps-bubble-spa' ); ?>">⏸</button>

                <!-- Start overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--start">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title">🫧 Bubble Spa Deluxe</h2>
                        <p class="dps-bs-overlay__sub"><?php echo esc_html__( 'Estoure bolhas e impeça que a sujeira chegue ao ralo!', 'dps-bubble-spa' ); ?></p>
                        <p class="dps-bs-overlay__hs"><?php echo esc_html__( 'Melhor pontuação:', 'dps-bubble-spa' ); ?> <span class="dps-bs-hs-val">0</span></p>
                        <button type="button" class="dps-bs-btn dps-bs-btn--play"><?php echo esc_html__( 'Jogar', 'dps-bubble-spa' ); ?> 🫧</button>
                    </div>
                </div>

                <!-- Level select overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--levels dps-bs-overlay--hidden">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title"><?php echo esc_html__( 'Selecionar Fase', 'dps-bubble-spa' ); ?></h2>
                        <div class="dps-bs-level-grid"></div>
                        <button type="button" class="dps-bs-btn dps-bs-btn--secondary dps-bs-btn--back"><?php echo esc_html__( 'Voltar', 'dps-bubble-spa' ); ?></button>
                    </div>
                </div>

                <!-- Level intro overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--intro dps-bs-overlay--hidden">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title dps-bs-intro-title"></h2>
                        <p class="dps-bs-intro-desc"></p>
                        <button type="button" class="dps-bs-btn dps-bs-btn--go"><?php echo esc_html__( 'Iniciar', 'dps-bubble-spa' ); ?></button>
                    </div>
                </div>

                <!-- Game over overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--gameover dps-bs-overlay--hidden">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title">😿 <?php echo esc_html__( 'A sujeira chegou ao ralo!', 'dps-bubble-spa' ); ?></h2>
                        <p class="dps-bs-overlay__fscore"><?php echo esc_html__( 'Pontuação:', 'dps-bubble-spa' ); ?> <span class="dps-bs-final-score">0</span></p>
                        <button type="button" class="dps-bs-btn dps-bs-btn--retry"><?php echo esc_html__( 'Tentar novamente', 'dps-bubble-spa' ); ?></button>
                        <button type="button" class="dps-bs-btn dps-bs-btn--secondary dps-bs-btn--menu"><?php echo esc_html__( 'Menu', 'dps-bubble-spa' ); ?></button>
                    </div>
                </div>

                <!-- Victory overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--victory dps-bs-overlay--hidden">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title">🎉 <?php echo esc_html__( 'Fase Completa!', 'dps-bubble-spa' ); ?></h2>
                        <div class="dps-bs-victory-stars"></div>
                        <p class="dps-bs-overlay__fscore"><?php echo esc_html__( 'Pontuação:', 'dps-bubble-spa' ); ?> <span class="dps-bs-final-score">0</span></p>
                        <div class="dps-bs-victory-bonuses"></div>
                        <button type="button" class="dps-bs-btn dps-bs-btn--next"><?php echo esc_html__( 'Próxima Fase', 'dps-bubble-spa' ); ?></button>
                        <button type="button" class="dps-bs-btn dps-bs-btn--secondary dps-bs-btn--menu"><?php echo esc_html__( 'Menu', 'dps-bubble-spa' ); ?></button>
                    </div>
                </div>

                <!-- Pause overlay -->
                <div class="dps-bs-overlay dps-bs-overlay--pause dps-bs-overlay--hidden">
                    <div class="dps-bs-overlay__content">
                        <h2 class="dps-bs-overlay__title">⏸️ <?php echo esc_html__( 'Pausado', 'dps-bubble-spa' ); ?></h2>
                        <button type="button" class="dps-bs-btn dps-bs-btn--resume"><?php echo esc_html__( 'Continuar', 'dps-bubble-spa' ); ?></button>
                        <button type="button" class="dps-bs-btn dps-bs-btn--secondary dps-bs-btn--menu"><?php echo esc_html__( 'Menu', 'dps-bubble-spa' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
