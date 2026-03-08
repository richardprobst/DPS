<?php
/**
 * Servico de persistencia e sincronizacao do Space Groomers.
 *
 * @package DPS_Game
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Game_Progress_Service {

    const META_KEY      = 'dps_game_progress_v1';
    const VERSION       = 1;
    const HISTORY_LIMIT = 8;

    /**
     * Retorna o cliente autenticado no portal, quando existir.
     *
     * @return int
     */
    public static function get_authenticated_client_id(): int {
        if ( class_exists( 'DPS_Portal_Session_Manager' ) ) {
            return (int) DPS_Portal_Session_Manager::get_instance()->get_authenticated_client_id();
        }

        return 0;
    }

    /**
     * Resolve o cliente-alvo da request.
     *
     * @param WP_REST_Request|null $request Request REST opcional.
     * @return int
     */
    public static function resolve_request_client_id( ?WP_REST_Request $request = null ): int {
        if ( current_user_can( 'manage_options' ) && $request instanceof WP_REST_Request ) {
            $requested_client_id = absint( $request->get_param( 'client_id' ) );
            if ( $requested_client_id > 0 ) {
                return $requested_client_id;
            }
        }

        return self::get_authenticated_client_id();
    }

    /**
     * Retorna o estado persistido do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    public static function get_progress( int $client_id ): array {
        if ( $client_id <= 0 || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return self::get_default_state();
        }

        $raw   = get_post_meta( $client_id, self::META_KEY, true );
        $state = self::normalize_state( is_array( $raw ) ? $raw : [] );
        $state = self::ensure_current_mission( $state );

        return $state;
    }

    /**
     * Salva o progresso do cliente.
     *
     * @param int   $client_id ID do cliente.
     * @param array $state     Estado normalizado.
     * @return void
     */
    public static function save_progress( int $client_id, array $state ): void {
        if ( $client_id <= 0 || 'dps_cliente' !== get_post_type( $client_id ) ) {
            return;
        }

        update_post_meta( $client_id, self::META_KEY, $state );
    }

    /**
     * Mescla payload do cliente com o estado canonico do servidor.
     *
     * @param int   $client_id ID do cliente.
     * @param array $payload   Payload enviado pelo jogo.
     * @return array
     */
    public static function sync_progress( int $client_id, array $payload ): array {
        $existing = self::get_progress( $client_id );
        $incoming = self::normalize_state( $payload );
        $merged   = self::merge_state( $existing, $incoming );

        $awarded_rewards = self::maybe_award_loyalty_rewards( $client_id, $merged );
        $merged          = self::ensure_current_mission( $merged );
        $merged['lastSyncedAt'] = gmdate( 'c' );

        self::save_progress( $client_id, $merged );

        return [
            'progress'       => $merged,
            'summary'        => self::build_summary( $client_id, $merged ),
            'awardedRewards' => $awarded_rewards,
            'loyalty'        => self::get_loyalty_summary( $client_id ),
        ];
    }

    /**
     * Retorna um resumo amigavel para o portal.
     *
     * @param int   $client_id ID do cliente.
     * @param array $state     Estado normalizado.
     * @return array
     */
    public static function build_summary( int $client_id, array $state ): array {
        $mission     = self::enrich_mission( $state['mission'] );
        $badges      = self::get_unlocked_badges( $state );
        $recent_runs = array_slice( $state['history'], 0, 3 );
        $last_run    = ! empty( $recent_runs ) ? $recent_runs[0] : null;

        return [
            'clientId'     => $client_id,
            'highscore'    => (int) $state['highscore'],
            'totals'       => $state['totals'],
            'records'      => $state['records'],
            'streak'       => $state['streak'],
            'mission'      => $mission,
            'badgesCount'  => count( $badges ),
            'badges'       => array_slice( $badges, 0, 3 ),
            'recentRuns'   => $recent_runs,
            'lastRun'      => $last_run,
            'lastSyncedAt' => (string) $state['lastSyncedAt'],
            'loyalty'      => self::get_loyalty_summary( $client_id ),
        ];
    }

    /**
     * Estado padrao.
     *
     * @return array
     */
    public static function get_default_state(): array {
        return [
            'version'       => self::VERSION,
            'highscore'     => 0,
            'totals'        => [
                'runs'                    => 0,
                'wins'                    => 0,
                'totalScore'              => 0,
                'totalPlayMs'             => 0,
                'totalPowerups'           => 0,
                'totalMissionCompletions' => 0,
            ],
            'records'       => [
                'bestCombo'     => 0,
                'longestRunSec' => 0,
                'bestWave'      => 1,
            ],
            'streak'        => [
                'current'     => 0,
                'best'        => 0,
                'lastDateKey' => '',
            ],
            'mission'       => [
                'dateKey'     => '',
                'missionId'   => '',
                'progress'    => 0,
                'completed'   => false,
                'completedAt' => '',
            ],
            'badges'        => [],
            'history'       => [],
            'rewardMarkers' => [],
            'lastSyncedAt'  => '',
        ];
    }

    /**
     * Retorna definicoes de missao espelhadas do frontend.
     *
     * @return array
     */
    public static function get_mission_definitions(): array {
        return [
            'survive_60'         => [
                'id'     => 'survive_60',
                'kind'   => 'survive_seconds',
                'target' => 60,
                'title'  => __( 'Sobreviva 60s', 'dps-game' ),
                'hint'   => __( 'Jogue no seguro e preserve vidas.', 'dps-game' ),
            ],
            'collect_3_powerups' => [
                'id'     => 'collect_3_powerups',
                'kind'   => 'collect_powerups',
                'target' => 3,
                'title'  => __( 'Colete 3 power-ups', 'dps-game' ),
                'hint'   => __( 'Shampoo e toalha contam para a meta.', 'dps-game' ),
            ],
            'combo_9'            => [
                'id'     => 'combo_9',
                'kind'   => 'reach_combo',
                'target' => 9,
                'title'  => __( 'Atinga combo 9', 'dps-game' ),
                'hint'   => __( 'Mantenha a sequencia sem deixar tiro escapar.', 'dps-game' ),
            ],
            'defeat_6_ticks'     => [
                'id'     => 'defeat_6_ticks',
                'kind'   => 'defeat_ticks',
                'target' => 6,
                'title'  => __( 'Derrote 6 carrapatos', 'dps-game' ),
                'hint'   => __( 'Carrapatos valem mais e contam para a missao.', 'dps-game' ),
            ],
        ];
    }

    /**
     * Retorna badges conhecidas.
     *
     * @return array
     */
    public static function get_badge_definitions(): array {
        return [
            'first_run'       => [
                'id'   => 'first_run',
                'name' => __( 'Primeiro Banho', 'dps-game' ),
                'desc' => __( 'Concluiu a primeira run.', 'dps-game' ),
            ],
            'combo_keeper'    => [
                'id'   => 'combo_keeper',
                'name' => __( 'Ritmo de Tesoura', 'dps-game' ),
                'desc' => __( 'Atingiu combo 9 em alguma partida.', 'dps-game' ),
            ],
            'mission_regular' => [
                'id'   => 'mission_regular',
                'name' => __( 'Missao em Dia', 'dps-game' ),
                'desc' => __( 'Completou 3 missoes diarias.', 'dps-game' ),
            ],
            'streak_3'        => [
                'id'   => 'streak_3',
                'name' => __( 'Retorno em Serie', 'dps-game' ),
                'desc' => __( 'Manteve streak de 3 dias.', 'dps-game' ),
            ],
            'first_victory'   => [
                'id'   => 'first_victory',
                'name' => __( 'Banho Completo', 'dps-game' ),
                'desc' => __( 'Venceu uma run completa.', 'dps-game' ),
            ],
        ];
    }

    /**
     * Normaliza o estado bruto.
     *
     * @param array $raw Estado bruto.
     * @return array
     */
    private static function normalize_state( array $raw ): array {
        $state            = self::get_default_state();
        $state['version'] = isset( $raw['version'] ) ? max( self::VERSION, absint( $raw['version'] ) ) : self::VERSION;
        $state['highscore'] = isset( $raw['highscore'] ) ? max( 0, absint( $raw['highscore'] ) ) : 0;

        foreach ( array_keys( $state['totals'] ) as $key ) {
            if ( isset( $raw['totals'][ $key ] ) ) {
                $state['totals'][ $key ] = max( 0, absint( $raw['totals'][ $key ] ) );
            }
        }

        foreach ( array_keys( $state['records'] ) as $key ) {
            if ( isset( $raw['records'][ $key ] ) ) {
                $state['records'][ $key ] = max( 0, absint( $raw['records'][ $key ] ) );
            }
        }
        $state['records']['bestWave'] = max( 1, (int) $state['records']['bestWave'] );

        if ( isset( $raw['streak'] ) && is_array( $raw['streak'] ) ) {
            $state['streak']['current']     = isset( $raw['streak']['current'] ) ? max( 0, absint( $raw['streak']['current'] ) ) : 0;
            $state['streak']['best']        = isset( $raw['streak']['best'] ) ? max( 0, absint( $raw['streak']['best'] ) ) : 0;
            $state['streak']['lastDateKey'] = isset( $raw['streak']['lastDateKey'] ) ? self::sanitize_date_key( (string) $raw['streak']['lastDateKey'] ) : '';
        }

        if ( isset( $raw['mission'] ) && is_array( $raw['mission'] ) ) {
            $state['mission']['dateKey']     = isset( $raw['mission']['dateKey'] ) ? self::sanitize_date_key( (string) $raw['mission']['dateKey'] ) : '';
            $state['mission']['missionId']   = isset( $raw['mission']['missionId'] ) ? sanitize_key( (string) $raw['mission']['missionId'] ) : '';
            $state['mission']['progress']    = isset( $raw['mission']['progress'] ) ? max( 0, absint( $raw['mission']['progress'] ) ) : 0;
            $state['mission']['completed']   = ! empty( $raw['mission']['completed'] );
            $state['mission']['completedAt'] = isset( $raw['mission']['completedAt'] ) ? sanitize_text_field( (string) $raw['mission']['completedAt'] ) : '';
        }

        if ( isset( $raw['badges'] ) && is_array( $raw['badges'] ) ) {
            foreach ( $raw['badges'] as $badge_id => $badge_data ) {
                $badge_id = sanitize_key( (string) $badge_id );
                if ( '' === $badge_id || ! is_array( $badge_data ) ) {
                    continue;
                }

                $state['badges'][ $badge_id ] = [
                    'unlockedAt' => isset( $badge_data['unlockedAt'] ) ? sanitize_text_field( (string) $badge_data['unlockedAt'] ) : '',
                ];
            }
        }

        if ( isset( $raw['history'] ) && is_array( $raw['history'] ) ) {
            $state['history'] = self::normalize_history( $raw['history'] );
        }

        if ( isset( $raw['rewardMarkers'] ) && is_array( $raw['rewardMarkers'] ) ) {
            foreach ( $raw['rewardMarkers'] as $marker_key => $marker_value ) {
                $marker_key = sanitize_key( (string) $marker_key );
                if ( '' === $marker_key ) {
                    continue;
                }

                $state['rewardMarkers'][ $marker_key ] = sanitize_text_field( (string) $marker_value );
            }
        }

        if ( ! empty( $raw['lastSyncedAt'] ) ) {
            $state['lastSyncedAt'] = sanitize_text_field( (string) $raw['lastSyncedAt'] );
        }

        return $state;
    }

    /**
     * Mantem missao diaria atualizada na leitura/escrita.
     *
     * @param array $state Estado atual.
     * @return array
     */
    private static function ensure_current_mission( array $state ): array {
        $today_key       = wp_date( 'Y-m-d' );
        $current_mission = self::get_mission_for_date( $today_key );

        if (
            $state['mission']['dateKey'] !== $today_key ||
            $state['mission']['missionId'] !== $current_mission['id']
        ) {
            $state['mission'] = [
                'dateKey'     => $today_key,
                'missionId'   => $current_mission['id'],
                'progress'    => 0,
                'completed'   => false,
                'completedAt' => '',
            ];
        }

        $state['mission']['progress'] = min(
            (int) $current_mission['target'],
            max( 0, (int) $state['mission']['progress'] )
        );

        return $state;
    }

    /**
     * Mescla estados.
     *
     * @param array $existing Estado existente.
     * @param array $incoming Estado enviado pelo cliente.
     * @return array
     */
    private static function merge_state( array $existing, array $incoming ): array {
        $merged              = $existing;
        $merged['version']   = max( (int) $existing['version'], (int) $incoming['version'], self::VERSION );
        $merged['highscore'] = max( (int) $existing['highscore'], (int) $incoming['highscore'] );

        foreach ( array_keys( $merged['totals'] ) as $key ) {
            $merged['totals'][ $key ] = max( (int) $existing['totals'][ $key ], (int) $incoming['totals'][ $key ] );
        }

        foreach ( array_keys( $merged['records'] ) as $key ) {
            $merged['records'][ $key ] = max( (int) $existing['records'][ $key ], (int) $incoming['records'][ $key ] );
        }

        $merged['streak']        = self::merge_streak( $existing['streak'], $incoming['streak'] );
        $merged['mission']       = self::merge_mission( $existing['mission'], $incoming['mission'] );
        $merged['badges']        = self::merge_badges( $existing['badges'], $incoming['badges'] );
        $merged['history']       = self::merge_history( $existing['history'], $incoming['history'] );
        $merged['rewardMarkers'] = self::merge_reward_markers( $existing['rewardMarkers'], $incoming['rewardMarkers'] );

        return $merged;
    }

    /**
     * Mescla streak priorizando a data mais recente.
     *
     * @param array $existing Streak existente.
     * @param array $incoming Streak recebida.
     * @return array
     */
    private static function merge_streak( array $existing, array $incoming ): array {
        $existing_date = isset( $existing['lastDateKey'] ) ? (string) $existing['lastDateKey'] : '';
        $incoming_date = isset( $incoming['lastDateKey'] ) ? (string) $incoming['lastDateKey'] : '';
        $merged        = $existing;

        if ( $incoming_date > $existing_date ) {
            $merged['current']     = (int) $incoming['current'];
            $merged['lastDateKey'] = $incoming_date;
        } elseif ( $incoming_date === $existing_date ) {
            $merged['current'] = max( (int) $existing['current'], (int) $incoming['current'] );
        }

        $merged['best'] = max( (int) $existing['best'], (int) $incoming['best'], (int) $merged['current'] );

        return $merged;
    }

    /**
     * Mescla missao priorizando a data mais recente.
     *
     * @param array $existing Missao existente.
     * @param array $incoming Missao recebida.
     * @return array
     */
    private static function merge_mission( array $existing, array $incoming ): array {
        $merged        = $existing;
        $existing_date = isset( $existing['dateKey'] ) ? (string) $existing['dateKey'] : '';
        $incoming_date = isset( $incoming['dateKey'] ) ? (string) $incoming['dateKey'] : '';

        if ( $incoming_date > $existing_date ) {
            $merged = $incoming;
        } elseif ( $incoming_date === $existing_date ) {
            $merged['missionId']   = ! empty( $incoming['missionId'] ) ? $incoming['missionId'] : $existing['missionId'];
            $merged['progress']    = max( (int) $existing['progress'], (int) $incoming['progress'] );
            $merged['completed']   = ! empty( $existing['completed'] ) || ! empty( $incoming['completed'] );
            $merged['completedAt'] = ! empty( $existing['completedAt'] ) ? $existing['completedAt'] : $incoming['completedAt'];
        }

        return $merged;
    }

    /**
     * Mescla badges mantendo a primeira data conhecida.
     *
     * @param array $existing Badges existentes.
     * @param array $incoming Badges recebidas.
     * @return array
     */
    private static function merge_badges( array $existing, array $incoming ): array {
        $merged = $existing;

        foreach ( $incoming as $badge_id => $badge_data ) {
            if ( ! isset( $merged[ $badge_id ] ) ) {
                $merged[ $badge_id ] = $badge_data;
                continue;
            }

            if ( empty( $merged[ $badge_id ]['unlockedAt'] ) && ! empty( $badge_data['unlockedAt'] ) ) {
                $merged[ $badge_id ]['unlockedAt'] = $badge_data['unlockedAt'];
            }
        }

        return $merged;
    }

    /**
     * Mescla historico de runs.
     *
     * @param array $existing Historico existente.
     * @param array $incoming Historico recebido.
     * @return array
     */
    private static function merge_history( array $existing, array $incoming ): array {
        $combined = [];

        foreach ( array_merge( $existing, $incoming ) as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $key = implode(
                '|',
                [
                    isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '',
                    isset( $entry['score'] ) ? (string) absint( $entry['score'] ) : '0',
                    isset( $entry['result'] ) ? sanitize_key( (string) $entry['result'] ) : '',
                    isset( $entry['waveReached'] ) ? (string) absint( $entry['waveReached'] ) : '0',
                ]
            );

            if ( '' === trim( $key, '|' ) ) {
                continue;
            }

            $combined[ $key ] = self::normalize_history_entry( $entry );
        }

        $history = array_values( $combined );

        usort(
            $history,
            static function ( array $a, array $b ): int {
                return strcmp( (string) $b['timestamp'], (string) $a['timestamp'] );
            }
        );

        return array_slice( $history, 0, self::HISTORY_LIMIT );
    }

    /**
     * Mescla marcadores de recompensa.
     *
     * @param array $existing Marcadores existentes.
     * @param array $incoming Marcadores recebidos.
     * @return array
     */
    private static function merge_reward_markers( array $existing, array $incoming ): array {
        return array_merge( $existing, $incoming );
    }

    /**
     * Normaliza lista de historico.
     *
     * @param array $history Historico bruto.
     * @return array
     */
    private static function normalize_history( array $history ): array {
        $normalized = [];

        foreach ( $history as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $normalized[] = self::normalize_history_entry( $entry );
        }

        usort(
            $normalized,
            static function ( array $a, array $b ): int {
                return strcmp( (string) $b['timestamp'], (string) $a['timestamp'] );
            }
        );

        return array_slice( $normalized, 0, self::HISTORY_LIMIT );
    }

    /**
     * Normaliza uma entrada de historico.
     *
     * @param array $entry Entrada bruta.
     * @return array
     */
    private static function normalize_history_entry( array $entry ): array {
        return [
            'dateKey'           => isset( $entry['dateKey'] ) ? self::sanitize_date_key( (string) $entry['dateKey'] ) : '',
            'score'             => isset( $entry['score'] ) ? max( 0, absint( $entry['score'] ) ) : 0,
            'result'            => isset( $entry['result'] ) ? sanitize_key( (string) $entry['result'] ) : 'gameover',
            'durationSec'       => isset( $entry['durationSec'] ) ? max( 0, absint( $entry['durationSec'] ) ) : 0,
            'bestCombo'         => isset( $entry['bestCombo'] ) ? max( 0, absint( $entry['bestCombo'] ) ) : 0,
            'powerupsCollected' => isset( $entry['powerupsCollected'] ) ? max( 0, absint( $entry['powerupsCollected'] ) ) : 0,
            'tickKills'         => isset( $entry['tickKills'] ) ? max( 0, absint( $entry['tickKills'] ) ) : 0,
            'waveReached'       => isset( $entry['waveReached'] ) ? max( 1, absint( $entry['waveReached'] ) ) : 1,
            'timestamp'         => isset( $entry['timestamp'] ) ? sanitize_text_field( (string) $entry['timestamp'] ) : '',
        ];
    }

    /**
     * Enriquece dados da missao para resposta publica.
     *
     * @param array $mission Estado minimo da missao.
     * @return array
     */
    private static function enrich_mission( array $mission ): array {
        $definitions = self::get_mission_definitions();
        $mission_id  = isset( $mission['missionId'] ) ? sanitize_key( (string) $mission['missionId'] ) : '';

        if ( ! isset( $definitions[ $mission_id ] ) ) {
            $fallback   = self::get_mission_for_date( isset( $mission['dateKey'] ) ? (string) $mission['dateKey'] : wp_date( 'Y-m-d' ) );
            $mission_id = $fallback['id'];
        }

        $definition = $definitions[ $mission_id ];
        $progress   = min( (int) $definition['target'], max( 0, absint( $mission['progress'] ?? 0 ) ) );
        $completed  = ! empty( $mission['completed'] ) || $progress >= (int) $definition['target'];

        return [
            'id'          => $definition['id'],
            'kind'        => $definition['kind'],
            'title'       => $definition['title'],
            'hint'        => $definition['hint'],
            'target'      => (int) $definition['target'],
            'progress'    => $progress,
            'completed'   => $completed,
            'remaining'   => $completed ? 0 : max( 0, (int) $definition['target'] - $progress ),
            'dateKey'     => isset( $mission['dateKey'] ) ? self::sanitize_date_key( (string) $mission['dateKey'] ) : '',
            'completedAt' => isset( $mission['completedAt'] ) ? sanitize_text_field( (string) $mission['completedAt'] ) : '',
        ];
    }

    /**
     * Retorna badges desbloqueadas em formato publico.
     *
     * @param array $state Estado do jogo.
     * @return array
     */
    private static function get_unlocked_badges( array $state ): array {
        $definitions = self::get_badge_definitions();
        $items       = [];

        foreach ( $state['badges'] as $badge_id => $badge_data ) {
            if ( ! isset( $definitions[ $badge_id ] ) ) {
                continue;
            }

            $items[] = [
                'id'         => $badge_id,
                'name'       => $definitions[ $badge_id ]['name'],
                'desc'       => $definitions[ $badge_id ]['desc'],
                'unlockedAt' => isset( $badge_data['unlockedAt'] ) ? sanitize_text_field( (string) $badge_data['unlockedAt'] ) : '',
            ];
        }

        usort(
            $items,
            static function ( array $a, array $b ): int {
                return strcmp( (string) $b['unlockedAt'], (string) $a['unlockedAt'] );
            }
        );

        return $items;
    }

    /**
     * Tenta conceder recompensas leves no loyalty.
     *
     * @param int   $client_id ID do cliente.
     * @param array $state     Estado consolidado.
     * @return array
     */
    private static function maybe_award_loyalty_rewards( int $client_id, array &$state ): array {
        $awarded = [];

        if ( $client_id <= 0 ) {
            return $awarded;
        }

        if ( ! class_exists( 'DPS_Loyalty_API' ) && ! function_exists( 'dps_loyalty_add_points' ) ) {
            return $awarded;
        }

        $mission = self::enrich_mission( $state['mission'] );
        if ( $mission['completed'] ) {
            $marker_key = 'mission_' . sanitize_key( $mission['dateKey'] );
            $result     = self::award_reward_once( $client_id, $state, $marker_key, 'daily_mission' );
            if ( ! empty( $result ) ) {
                $awarded[] = $result;
            }
        }

        if ( (int) $state['streak']['current'] >= 3 ) {
            $result = self::award_reward_once( $client_id, $state, 'streak_3', 'streak_3' );
            if ( ! empty( $result ) ) {
                $awarded[] = $result;
            }
        }

        if ( (int) $state['streak']['current'] >= 7 ) {
            $result = self::award_reward_once( $client_id, $state, 'streak_7', 'streak_7' );
            if ( ! empty( $result ) ) {
                $awarded[] = $result;
            }
        }

        if ( (int) $state['totals']['wins'] >= 1 ) {
            $result = self::award_reward_once( $client_id, $state, 'first_victory', 'first_victory' );
            if ( ! empty( $result ) ) {
                $awarded[] = $result;
            }
        }

        return $awarded;
    }

    /**
     * Concede recompensa uma unica vez.
     *
     * @param int    $client_id  ID do cliente.
     * @param array  $state      Estado consolidado.
     * @param string $marker_key Chave de idempotencia.
     * @param string $reward_key Evento do loyalty.
     * @return array
     */
    private static function award_reward_once( int $client_id, array &$state, string $marker_key, string $reward_key ): array {
        if ( isset( $state['rewardMarkers'][ $marker_key ] ) ) {
            return [];
        }

        $award = [];
        if ( class_exists( 'DPS_Loyalty_API' ) && method_exists( 'DPS_Loyalty_API', 'award_game_event_points' ) ) {
            $award = DPS_Loyalty_API::award_game_event_points( $client_id, $reward_key );
        } else {
            $fallback_map = [
                'daily_mission' => [ 'points' => 15, 'context' => 'game_daily_mission' ],
                'streak_3'      => [ 'points' => 25, 'context' => 'game_streak_3' ],
                'streak_7'      => [ 'points' => 40, 'context' => 'game_streak_7' ],
                'first_victory' => [ 'points' => 30, 'context' => 'game_first_victory' ],
            ];

            if ( isset( $fallback_map[ $reward_key ] ) && function_exists( 'dps_loyalty_add_points' ) ) {
                dps_loyalty_add_points( $client_id, $fallback_map[ $reward_key ]['points'], $fallback_map[ $reward_key ]['context'] );
                $award = [
                    'event'   => $reward_key,
                    'points'  => $fallback_map[ $reward_key ]['points'],
                    'context' => $fallback_map[ $reward_key ]['context'],
                ];
            }
        }

        if ( empty( $award ) ) {
            return [];
        }

        $state['rewardMarkers'][ $marker_key ] = gmdate( 'c' );
        return $award;
    }

    /**
     * Retorna resumo do loyalty para o portal.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    private static function get_loyalty_summary( int $client_id ): array {
        if ( $client_id <= 0 || ! class_exists( 'DPS_Loyalty_API' ) ) {
            return [
                'enabled' => false,
                'points'  => 0,
            ];
        }

        return [
            'enabled' => true,
            'points'  => (int) DPS_Loyalty_API::get_points( $client_id ),
            'tier'    => DPS_Loyalty_API::get_loyalty_tier( $client_id ),
        ];
    }

    /**
     * Sanitiza date key YYYY-MM-DD.
     *
     * @param string $date_key Data bruta.
     * @return string
     */
    private static function sanitize_date_key( string $date_key ): string {
        $date_key = sanitize_text_field( $date_key );

        if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_key ) ) {
            return $date_key;
        }

        return '';
    }

    /**
     * Retorna missao para uma data especifica.
     *
     * @param string $date_key Data YYYY-MM-DD.
     * @return array
     */
    private static function get_mission_for_date( string $date_key ): array {
        $missions = array_values( self::get_mission_definitions() );
        $day_num  = self::get_day_number_from_date_key( $date_key );
        $index    = abs( $day_num ) % count( $missions );

        return $missions[ $index ];
    }

    /**
     * Calcula numero absoluto do dia.
     *
     * @param string $date_key Data YYYY-MM-DD.
     * @return int
     */
    private static function get_day_number_from_date_key( string $date_key ): int {
        $date = DateTimeImmutable::createFromFormat( 'Y-m-d', $date_key, new DateTimeZone( 'UTC' ) );
        if ( false === $date ) {
            return 0;
        }

        return (int) floor( (int) $date->format( 'U' ) / DAY_IN_SECONDS );
    }
}
