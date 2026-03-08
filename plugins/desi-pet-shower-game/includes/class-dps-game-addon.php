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

    private const PORTAL_TAB_ID = 'space-groomers';

    private static ?DPS_Game_Addon $instance = null;

    /**
     * Garante que a configuracao do script seja localizada apenas uma vez por request.
     *
     * @var bool
     */
    private bool $script_localized = false;

    /**
     * Cache de payloads do portal por request.
     *
     * @var array<int, array>
     */
    private array $portal_payloads = [];

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
        add_filter( 'dps_portal_tabs', [ $this, 'register_portal_tab' ], 10, 2 );
        add_action( 'dps_portal_custom_tab_panels', [ $this, 'render_portal_tab_panel' ], 10, 2 );
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
        echo '<p>' . esc_html__( 'A Fase 4 adiciona perfil do jogo no portal, contexto de pet/agendamento e resumo leve de recompensas conectado ao ecossistema DPS.', 'dps-game' ) . '</p>';
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
     * Adiciona aba propria do jogo no portal.
     *
     * @param array $tabs      Tabs atuais.
     * @param int   $client_id Cliente autenticado.
     * @return array
     */
    public function register_portal_tab( array $tabs, int $client_id ): array {
        if ( $client_id <= 0 ) {
            return $tabs;
        }

        $payload = $this->get_portal_payload( $client_id );
        $badge   = isset( $payload['summary']['badgesCount'] ) ? absint( $payload['summary']['badgesCount'] ) : 0;
        $new_tab = [
            self::PORTAL_TAB_ID => [
                'icon'   => 'SG',
                'label'  => __( 'Space Groomers', 'dps-game' ),
                'active' => false,
                'badge'  => $badge,
            ],
        ];

        $result = [];
        foreach ( $tabs as $tab_id => $tab ) {
            $result[ $tab_id ] = $tab;
            if ( 'inicio' === $tab_id ) {
                $result = array_merge( $result, $new_tab );
            }
        }

        if ( ! isset( $result[ self::PORTAL_TAB_ID ] ) ) {
            $result = array_merge( $result, $new_tab );
        }

        return $result;
    }

    /**
     * Renderiza o card do jogo na aba Inicio do portal.
     *
     * @param int $client_id Cliente autenticado do portal.
     * @return void
     */
    public function render_portal_card( int $client_id ): void {
        $payload = $this->get_portal_payload( $client_id );

        $this->enqueue_game_assets( 'portal', $client_id );

        $event_title       = isset( $payload['event']['title'] ) ? (string) $payload['event']['title'] : __( 'Defenda o Pet-Planeta e faca o banho mais divertido.', 'dps-game' );
        $event_description = isset( $payload['event']['description'] ) ? (string) $payload['event']['description'] : __( 'Defenda o Pet-Planeta e faca o banho mais divertido.', 'dps-game' );
        $summary           = $payload['summary'] ?? [];

        echo '<section class="dps-game-portal-card">';
        echo '<div class="dps-game-portal-card__header">';
        echo '<span class="dps-game-portal-card__icon">&#128640;</span>';
        echo '<div class="dps-game-portal-card__info">';
        echo '<p class="dps-game-portal-card__eyebrow">' . esc_html__( 'Space Groomers no portal', 'dps-game' ) . '</p>';
        echo '<h3 class="dps-game-portal-card__title">' . esc_html( $event_title ) . '</h3>';
        echo '<p class="dps-game-portal-card__desc">' . esc_html( $event_description ) . '</p>';
        echo '</div>';
        echo '<a href="#" class="dps-game-portal-card__cta dps-link-button" data-tab="' . esc_attr( self::PORTAL_TAB_ID ) . '">' . esc_html__( 'Ver perfil do jogo', 'dps-game' ) . '</a>';
        echo '</div>';

        $this->render_portal_chips( $payload );

        echo '<div class="dps-game-portal-card__summary">';
        $this->render_summary_stat( __( 'Recorde', 'dps-game' ), number_format_i18n( (int) ( $summary['highscore'] ?? 0 ) ) );
        $this->render_summary_stat( __( 'Streak', 'dps-game' ), sprintf( _n( '%d dia', '%d dias', (int) ( $summary['streak']['current'] ?? 0 ), 'dps-game' ), (int) ( $summary['streak']['current'] ?? 0 ) ) );
        $this->render_summary_stat( __( 'Badges', 'dps-game' ), number_format_i18n( (int) ( $summary['badgesCount'] ?? 0 ) ) );
        echo '</div>';

        $this->render_game_container( 'portal' );

        echo '<div class="dps-game-portal-card__footer">';
        echo '<span>' . esc_html__( 'O progresso continua salvo no cliente autenticado do portal.', 'dps-game' ) . '</span>';
        echo '<a href="#" class="dps-link-button" data-tab="' . esc_attr( self::PORTAL_TAB_ID ) . '">' . esc_html__( 'Abrir hub do jogo', 'dps-game' ) . '</a>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * Renderiza o painel da aba customizada do jogo.
     *
     * @param int   $client_id Cliente autenticado.
     * @param array $tabs      Tabs registradas no portal.
     * @return void
     */
    public function render_portal_tab_panel( int $client_id, array $tabs ): void {
        if ( ! isset( $tabs[ self::PORTAL_TAB_ID ] ) ) {
            return;
        }

        $payload      = $this->get_portal_payload( $client_id );
        $summary      = $payload['summary'] ?? [];
        $mission      = isset( $summary['mission'] ) && is_array( $summary['mission'] ) ? $summary['mission'] : [];
        $featured_pet = $payload['featuredPet'] ?? [];
        $event        = $payload['event'] ?? [];
        $loyalty      = $payload['loyalty'] ?? [];

        echo '<div id="panel-' . esc_attr( self::PORTAL_TAB_ID ) . '" class="dps-portal-tab-panel" role="tabpanel" aria-labelledby="dps-portal-tab-' . esc_attr( self::PORTAL_TAB_ID ) . '" aria-hidden="true" tabindex="-1">';
        echo '<section class="dps-game-hub">';

        echo '<div class="dps-game-hub__hero">';
        echo '<div class="dps-game-hub__hero-copy">';
        echo '<p class="dps-game-hub__eyebrow">' . esc_html__( 'Padrao M3 aplicado via docs/visual', 'dps-game' ) . '</p>';
        echo '<h2 class="dps-game-hub__title">' . esc_html( sprintf( __( '%s no comando da nave', 'dps-game' ), $payload['client']['firstName'] ?? __( 'Tutor', 'dps-game' ) ) ) . '</h2>';
        echo '<p class="dps-game-hub__intro">' . esc_html__( 'Este perfil conecta recorde, rotina do pet, status do portal e recompensas simbolicas do jogo em uma unica superficie.', 'dps-game' ) . '</p>';
        echo '</div>';
        echo '<div class="dps-game-hub__actions">';
        echo '<a href="#" class="dps-link-button dps-game-hub__action dps-game-hub__action--primary" data-tab="inicio">' . esc_html__( 'Jogar agora', 'dps-game' ) . '</a>';
        $secondary_tab = ! empty( $featured_pet['nextAppointment'] ) ? 'agendamentos' : 'historico-pets';
        echo '<a href="#" class="dps-link-button dps-game-hub__action" data-tab="' . esc_attr( $secondary_tab ) . '">' . esc_html( 'agendamentos' === $secondary_tab ? __( 'Ver agendamento', 'dps-game' ) : __( 'Ver historico do pet', 'dps-game' ) ) . '</a>';
        echo '</div>';
        echo '</div>';

        $this->render_portal_chips( $payload );

        echo '<div class="dps-game-hub__metrics">';
        $this->render_metric_card( __( 'Recorde sincronizado', 'dps-game' ), number_format_i18n( (int) ( $summary['highscore'] ?? 0 ) ), __( 'Fonte canonica: progresso do cliente no portal.', 'dps-game' ) );
        $streak_value = sprintf( _n( '%d dia', '%d dias', (int) ( $summary['streak']['current'] ?? 0 ), 'dps-game' ), (int) ( $summary['streak']['current'] ?? 0 ) );
        $this->render_metric_card( __( 'Streak atual', 'dps-game' ), $streak_value, __( 'Retorno leve conectado ao cliente autenticado.', 'dps-game' ) );
        $mission_value = isset( $mission['title'] ) ? (string) $mission['title'] : __( 'Sem missao', 'dps-game' );
        $mission_meta  = isset( $mission['target'], $mission['progress'] )
            ? sprintf( __( '%1$s/%2$s de progresso hoje.', 'dps-game' ), number_format_i18n( (int) $mission['progress'] ), number_format_i18n( (int) $mission['target'] ) )
            : __( 'Missao diaria indisponivel.', 'dps-game' );
        $this->render_metric_card( __( 'Missao do dia', 'dps-game' ), $mission_value, $mission_meta );
        $this->render_metric_card( __( 'Badges locais', 'dps-game' ), number_format_i18n( (int) ( $summary['badgesCount'] ?? 0 ) ), __( 'Conquistas do jogo prontas para futuras expansoes.', 'dps-game' ) );
        echo '</div>';

        $event_tone = isset( $event['tone'] ) ? sanitize_html_class( (string) $event['tone'] ) : 'secondary';
        echo '<article class="dps-game-hub__event dps-game-hub__event--' . esc_attr( $event_tone ) . '">';
        echo '<p class="dps-game-hub__event-label">' . esc_html( $event['label'] ?? __( 'Ecossistema DPS', 'dps-game' ) ) . '</p>';
        echo '<h3 class="dps-game-hub__event-title">' . esc_html( $event['title'] ?? __( 'Jogo conectado', 'dps-game' ) ) . '</h3>';
        echo '<p class="dps-game-hub__event-desc">' . esc_html( $event['description'] ?? __( 'O jogo ja conversa com o portal de forma leve.', 'dps-game' ) ) . '</p>';
        if ( ! empty( $event['meta'] ) ) {
            echo '<span class="dps-game-hub__event-meta">' . esc_html( $event['meta'] ) . '</span>';
        }
        echo '</article>';

        echo '<div class="dps-game-hub__columns">';
        echo '<section class="dps-game-hub__panel">';
        echo '<p class="dps-game-hub__panel-label">' . esc_html__( 'Pet em destaque', 'dps-game' ) . '</p>';
        if ( ! empty( $featured_pet ) ) {
            echo '<h3 class="dps-game-hub__panel-title">' . esc_html( $featured_pet['name'] ?? __( 'Pet conectado', 'dps-game' ) ) . '</h3>';
            $pet_meta = array_filter( [ $featured_pet['speciesLabel'] ?? '', $featured_pet['breed'] ?? '', $featured_pet['sizeLabel'] ?? '' ] );
            echo '<p class="dps-game-hub__panel-copy">' . esc_html( implode( ' / ', $pet_meta ) ) . '</p>';
            if ( ! empty( $featured_pet['nextAppointment']['dateLabel'] ) ) {
                echo '<p class="dps-game-hub__panel-copy">' . esc_html( sprintf( __( 'Proximo atendimento: %1$s (%2$s).', 'dps-game' ), $featured_pet['nextAppointment']['dateLabel'], $featured_pet['nextAppointment']['statusLabel'] ?? __( 'status ativo', 'dps-game' ) ) ) . '</p>';
            } elseif ( ! empty( $featured_pet['lastVisit']['dateLabel'] ) ) {
                echo '<p class="dps-game-hub__panel-copy">' . esc_html( sprintf( __( 'Ultimo atendimento registrado em %s.', 'dps-game' ), $featured_pet['lastVisit']['dateLabel'] ) ) . '</p>';
            } else {
                echo '<p class="dps-game-hub__panel-copy">' . esc_html__( 'O perfil do pet ja pode ser reutilizado em eventos futuros do jogo.', 'dps-game' ) . '</p>';
            }
        } else {
            echo '<h3 class="dps-game-hub__panel-title">' . esc_html__( 'Sem pet destacado ainda', 'dps-game' ) . '</h3>';
            echo '<p class="dps-game-hub__panel-copy">' . esc_html__( 'Quando houver pet ou agendamento vinculado, este painel passa a refletir esse contexto automaticamente.', 'dps-game' ) . '</p>';
        }
        echo '</section>';

        echo '<section class="dps-game-hub__panel">';
        echo '<p class="dps-game-hub__panel-label">' . esc_html__( 'Recompensas conectadas', 'dps-game' ) . '</p>';
        if ( ! empty( $loyalty['enabled'] ) ) {
            $tier_name = '';
            if ( isset( $loyalty['tier']['label'] ) ) {
                $tier_name = (string) $loyalty['tier']['label'];
            }
            echo '<h3 class="dps-game-hub__panel-title">' . esc_html( sprintf( __( '%1$s pts no loyalty', 'dps-game' ), number_format_i18n( (int) ( $loyalty['points'] ?? 0 ) ) ) ) . '</h3>';
            echo '<p class="dps-game-hub__panel-copy">' . esc_html( sprintf( __( 'O jogo ja gerou %1$s pts em %2$s eventos sincronizados.', 'dps-game' ), number_format_i18n( (int) ( $loyalty['gamePoints'] ?? 0 ) ), number_format_i18n( (int) ( $loyalty['gameRewardsCount'] ?? 0 ) ) ) ) . '</p>';
            if ( '' !== $tier_name ) {
                echo '<p class="dps-game-hub__panel-copy">' . esc_html( sprintf( __( 'Nivel atual de fidelidade: %s.', 'dps-game' ), $tier_name ) ) . '</p>';
            }
            if ( ! empty( $loyalty['lastGameReward']['label'] ) ) {
                echo '<p class="dps-game-hub__panel-copy">' . esc_html( sprintf( __( 'Ultima recompensa do jogo: %1$s em %2$s.', 'dps-game' ), $loyalty['lastGameReward']['label'], $loyalty['lastGameReward']['dateLabel'] ?? __( 'data recente', 'dps-game' ) ) ) . '</p>';
            }
        } else {
            echo '<h3 class="dps-game-hub__panel-title">' . esc_html__( 'Badges e progresso local do jogo', 'dps-game' ) . '</h3>';
            echo '<p class="dps-game-hub__panel-copy">' . esc_html__( 'Sem o modulo de loyalty, o jogo continua funcional e mantem badges, streak e recorde do cliente.', 'dps-game' ) . '</p>';
        }

        if ( ! empty( $summary['badges'] ) && is_array( $summary['badges'] ) ) {
            echo '<ul class="dps-game-hub__badge-list">';
            foreach ( array_slice( $summary['badges'], 0, 3 ) as $badge ) {
                if ( ! is_array( $badge ) || empty( $badge['name'] ) ) {
                    continue;
                }
                echo '<li class="dps-game-hub__badge-item">';
                echo '<strong>' . esc_html( $badge['name'] ) . '</strong>';
                if ( ! empty( $badge['desc'] ) ) {
                    echo '<span>' . esc_html( $badge['desc'] ) . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';
        echo '</div>';

        echo '</section>';
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
        $portal_profile     = [];

        if ( $resolved_client_id > 0 && class_exists( 'DPS_Game_Ecosystem_Service' ) ) {
            $portal_profile = DPS_Game_Ecosystem_Service::get_portal_payload( $resolved_client_id );
        }

        wp_localize_script(
            'dps-space-groomers',
            'dpsSpaceGroomersConfig',
            [
                'context'       => sanitize_key( $context ),
                'clientId'      => $resolved_client_id,
                'syncEnabled'   => $sync_enabled,
                'portalProfile' => $portal_profile,
                'restUrl'       => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/' ) ),
                'nonce'         => wp_create_nonce( 'dps_game_progress' ),
                'endpoints'     => [
                    'progress' => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress' ) ),
                    'sync'     => esc_url_raw( rest_url( DPS_Game_REST::API_NAMESPACE . '/progress/sync' ) ),
                ],
                'storage'       => [
                    'progressKey'   => 'dps_sg_progress_v1',
                    'legacyScoreKey' => 'dps_sg_highscore',
                ],
                'i18n'          => [
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
     * Recupera payload do portal com cache local por request.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    private function get_portal_payload( int $client_id ): array {
        if ( isset( $this->portal_payloads[ $client_id ] ) ) {
            return $this->portal_payloads[ $client_id ];
        }

        if ( $client_id <= 0 || ! class_exists( 'DPS_Game_Ecosystem_Service' ) ) {
            $this->portal_payloads[ $client_id ] = [];
            return [];
        }

        $this->portal_payloads[ $client_id ] = DPS_Game_Ecosystem_Service::get_portal_payload( $client_id );

        return $this->portal_payloads[ $client_id ];
    }

    /**
     * Renderiza chips com identidade do portal.
     *
     * @param array $payload Payload do portal.
     * @return void
     */
    private function render_portal_chips( array $payload ): void {
        $chips = [];

        if ( ! empty( $payload['client']['firstName'] ) ) {
            $chips[] = sprintf( __( 'Tutor: %s', 'dps-game' ), $payload['client']['firstName'] );
        }

        if ( ! empty( $payload['featuredPet']['name'] ) ) {
            $chips[] = sprintf( __( 'Pet: %s', 'dps-game' ), $payload['featuredPet']['name'] );
        }

        if ( ! empty( $payload['featuredPet']['nextAppointment']['dateLabel'] ) ) {
            $chips[] = sprintf( __( 'Proximo banho: %s', 'dps-game' ), $payload['featuredPet']['nextAppointment']['dateLabel'] );
        }

        if ( ! empty( $payload['loyalty']['enabled'] ) && ! empty( $payload['loyalty']['tier']['label'] ) ) {
            $chips[] = sprintf( __( 'Loyalty: %s', 'dps-game' ), $payload['loyalty']['tier']['label'] );
        }

        if ( empty( $chips ) ) {
            return;
        }

        echo '<div class="dps-game-portal-card__chips dps-game-hub__chips">';
        foreach ( $chips as $chip ) {
            echo '<span class="dps-game-chip">' . esc_html( $chip ) . '</span>';
        }
        echo '</div>';
    }

    /**
     * Renderiza estatistica curta no card do portal.
     *
     * @param string $label Rotulo.
     * @param string $value Valor.
     * @return void
     */
    private function render_summary_stat( string $label, string $value ): void {
        echo '<div class="dps-game-summary-stat">';
        echo '<span class="dps-game-summary-stat__label">' . esc_html( $label ) . '</span>';
        echo '<strong class="dps-game-summary-stat__value">' . esc_html( $value ) . '</strong>';
        echo '</div>';
    }

    /**
     * Renderiza card de metrica no hub do portal.
     *
     * @param string $label Rotulo.
     * @param string $value Valor.
     * @param string $meta  Texto auxiliar.
     * @return void
     */
    private function render_metric_card( string $label, string $value, string $meta ): void {
        echo '<article class="dps-game-hub__metric">';
        echo '<span class="dps-game-hub__metric-label">' . esc_html( $label ) . '</span>';
        echo '<strong class="dps-game-hub__metric-value">' . esc_html( $value ) . '</strong>';
        echo '<small class="dps-game-hub__metric-meta">' . esc_html( $meta ) . '</small>';
        echo '</article>';
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
