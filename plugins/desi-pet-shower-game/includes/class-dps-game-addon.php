<?php
/**
 * Classe principal do add-on Space Groomers.
 *
 * Registra shortcode, assets, integração com portal e menu admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Game_Addon {

    private static ?DPS_Game_Addon $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Shortcode
        add_shortcode( 'dps_space_groomers', [ $this, 'render_game_shortcode' ] );

        // Assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // Admin
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );

        // Portal integration
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
     * Renderiza a página admin.
     */
    public function render_admin_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Space Groomers: Invasão das Pulgas', 'dps-game' ) . '</h1>';
        echo '<p>' . esc_html__( 'Jogo temático para engajamento de clientes no portal.', 'dps-game' ) . '</p>';
        echo '<p>' . esc_html__( 'Use o shortcode [dps_space_groomers] em qualquer página ou o jogo aparece automaticamente na aba Início do portal.', 'dps-game' ) . '</p>';
        echo '</div>';
    }

    /**
     * Enfileira assets do frontend quando o shortcode está presente.
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
     */
    public function render_game_shortcode(): string {
        wp_enqueue_style( 'dps-space-groomers' );
        wp_enqueue_script( 'dps-space-groomers' );

        ob_start();
        $this->render_game_container( 'shortcode' );
        return ob_get_clean();
    }

    /**
     * Renderiza o card do jogo na aba Início do portal.
     */
    public function render_portal_card( int $client_id ): void {
        wp_enqueue_style( 'dps-space-groomers' );
        wp_enqueue_script( 'dps-space-groomers' );

        echo '<div class="dps-game-portal-card">';
        echo '<div class="dps-game-portal-card__header">';
        echo '<span class="dps-game-portal-card__icon">🚀</span>';
        echo '<div class="dps-game-portal-card__info">';
        echo '<h3 class="dps-game-portal-card__title">' . esc_html__( 'Space Groomers: Invasão das Pulgas', 'dps-game' ) . '</h3>';
        echo '<p class="dps-game-portal-card__desc">' . esc_html__( 'Defenda o Pet-Planeta e faça o banho mais divertido! 🫧', 'dps-game' ) . '</p>';
        echo '</div>';
        echo '</div>';

        $this->render_game_container( 'portal' );

        echo '</div>';
    }

    /**
     * Renderiza o container do jogo (canvas + UI).
     */
    private function render_game_container( string $context ): void {
        $container_id = 'dps-space-groomers-' . esc_attr( $context );
        ?>
        <div id="<?php echo esc_attr( $container_id ); ?>" class="dps-space-groomers" data-context="<?php echo esc_attr( $context ); ?>">
            <div class="dps-sg-wrapper">
                <canvas class="dps-sg-canvas" width="480" height="640"></canvas>

                <div class="dps-sg-hud">
                    <div class="dps-sg-hud__score">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Score', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-score">0</span>
                    </div>
                    <div class="dps-sg-hud__wave">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Wave', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-wave">1</span>
                    </div>
                    <div class="dps-sg-hud__lives">
                        <span class="dps-sg-hud__label"><?php echo esc_html__( 'Calmaria', 'dps-game' ); ?></span>
                        <span class="dps-sg-hud__value dps-sg-lives">❤️❤️❤️</span>
                    </div>
                </div>

                <div class="dps-sg-combo dps-sg-combo--hidden">
                    <span class="dps-sg-combo__text">x2</span>
                </div>

                <div class="dps-sg-powerup-indicator dps-sg-powerup-indicator--hidden">
                    <span class="dps-sg-powerup-indicator__icon"></span>
                    <span class="dps-sg-powerup-indicator__name"></span>
                    <div class="dps-sg-powerup-indicator__bar"><div class="dps-sg-powerup-indicator__fill"></div></div>
                </div>

                <div class="dps-sg-special-bar">
                    <span class="dps-sg-special-bar__label"><?php echo esc_html__( 'Especial', 'dps-game' ); ?></span>
                    <div class="dps-sg-special-bar__track"><div class="dps-sg-special-bar__fill"></div></div>
                </div>

                <div class="dps-sg-mobile-controls">
                    <button type="button" class="dps-sg-btn dps-sg-btn--left" aria-label="<?php echo esc_attr__( 'Mover esquerda', 'dps-game' ); ?>">◀</button>
                    <button type="button" class="dps-sg-btn dps-sg-btn--fire" aria-label="<?php echo esc_attr__( 'Atirar', 'dps-game' ); ?>">🫧</button>
                    <button type="button" class="dps-sg-btn dps-sg-btn--special" aria-label="<?php echo esc_attr__( 'Especial', 'dps-game' ); ?>" disabled>⚡</button>
                    <button type="button" class="dps-sg-btn dps-sg-btn--right" aria-label="<?php echo esc_attr__( 'Mover direita', 'dps-game' ); ?>">▶</button>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--start">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">🚀 Space Groomers</h2>
                        <p class="dps-sg-overlay__subtitle"><?php echo esc_html__( 'Invasão das Pulgas', 'dps-game' ); ?></p>
                        <p class="dps-sg-overlay__desc"><?php echo esc_html__( 'Defenda o Pet-Planeta dos invasores!', 'dps-game' ); ?></p>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Seu recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Jogar', 'dps-game' ); ?> 🫧</button>
                        <p class="dps-sg-overlay__controls-hint">
                            <small><?php echo esc_html__( '← → mover · Espaço atirar · Shift especial', 'dps-game' ); ?></small>
                        </p>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--gameover dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">😿 <?php echo esc_html__( 'Pet ficou estressado!', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Pontuação:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Jogar Novamente', 'dps-game' ); ?> 🔄</button>
                    </div>
                </div>

                <div class="dps-sg-overlay dps-sg-overlay--victory dps-sg-overlay--hidden">
                    <div class="dps-sg-overlay__content">
                        <h2 class="dps-sg-overlay__title">🎉 <?php echo esc_html__( 'Banho Completo!', 'dps-game' ); ?></h2>
                        <p class="dps-sg-overlay__final-score"><?php echo esc_html__( 'Pontuação:', 'dps-game' ); ?> <span class="dps-sg-final-score">0</span></p>
                        <p class="dps-sg-overlay__stats"></p>
                        <p class="dps-sg-overlay__highscore"><?php echo esc_html__( 'Recorde:', 'dps-game' ); ?> <span class="dps-sg-highscore-value">0</span></p>
                        <button type="button" class="dps-sg-btn dps-sg-btn--play"><?php echo esc_html__( 'Jogar Novamente', 'dps-game' ); ?> 🔄</button>
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
