<?php
/**
 * Monta integracoes leves do jogo com o ecossistema DPS.
 *
 * @package DPS_Game
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Game_Ecosystem_Service {

    /**
     * Contextos de loyalty originados pelo jogo.
     *
     * @var string[]
     */
    private const GAME_LOYALTY_CONTEXTS = [
        'game_daily_mission',
        'game_streak_3',
        'game_streak_7',
        'game_first_victory',
    ];

    /**
     * Monta o payload do portal para um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    public static function get_portal_payload( int $client_id ): array {
        $progress      = DPS_Game_Progress_Service::get_progress( $client_id );
        $summary       = DPS_Game_Progress_Service::build_summary( $client_id, $progress );
        $featured_pet  = self::get_featured_pet( $client_id );
        $loyalty       = self::get_loyalty_details( $client_id );
        $client_name   = get_the_title( $client_id );
        $client_name   = is_string( $client_name ) ? $client_name : '';
        $client_name   = '' !== $client_name ? $client_name : __( 'Cliente DPS', 'dps-game' );
        $client_first  = self::get_first_name( $client_name );
        $context_event = self::build_context_event( $summary, $featured_pet, $loyalty );

        return [
            'client'      => [
                'id'        => $client_id,
                'name'      => $client_name,
                'firstName' => $client_first,
            ],
            'summary'     => $summary,
            'featuredPet' => $featured_pet,
            'event'       => $context_event,
            'loyalty'     => $loyalty,
        ];
    }

    /**
     * Retorna badge numerico da tab do portal.
     *
     * @param int $client_id ID do cliente.
     * @return int
     */
    public static function get_tab_badge( int $client_id ): int {
        $payload = self::get_portal_payload( $client_id );

        return isset( $payload['summary']['badgesCount'] ) ? absint( $payload['summary']['badgesCount'] ) : 0;
    }

    /**
     * Resolve primeiro nome do cliente.
     *
     * @param string $name Nome completo.
     * @return string
     */
    private static function get_first_name( string $name ): string {
        $parts = preg_split( '/\s+/', trim( wp_strip_all_tags( $name ) ) );
        if ( empty( $parts ) || ! isset( $parts[0] ) ) {
            return __( 'Tutor', 'dps-game' );
        }

        return sanitize_text_field( $parts[0] );
    }

    /**
     * Busca o pet mais relevante para o contexto do jogo.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    private static function get_featured_pet( int $client_id ): array {
        $appointment = self::get_next_appointment( $client_id );
        $pet_id      = $appointment ? self::extract_pet_id_from_appointment( $appointment ) : 0;

        if ( $pet_id > 0 ) {
            $pet = get_post( $pet_id );
            if ( $pet instanceof WP_Post && 'dps_pet' === $pet->post_type ) {
                return self::build_pet_payload( $client_id, $pet, $appointment );
            }
        }

        $pets = self::get_client_pets( $client_id );
        if ( empty( $pets ) ) {
            return [];
        }

        return self::build_pet_payload( $client_id, $pets[0], null );
    }

    /**
     * Monta payload publico do pet em destaque.
     *
     * @param int          $client_id   ID do cliente.
     * @param WP_Post      $pet         Post do pet.
     * @param WP_Post|null $appointment Proximo agendamento opcional.
     * @return array
     */
    private static function build_pet_payload( int $client_id, WP_Post $pet, ?WP_Post $appointment ): array {
        $species          = sanitize_key( (string) get_post_meta( $pet->ID, 'pet_species', true ) );
        $breed            = sanitize_text_field( (string) get_post_meta( $pet->ID, 'pet_breed', true ) );
        $size             = sanitize_key( (string) get_post_meta( $pet->ID, 'pet_size', true ) );
        $next_appointment = $appointment ? self::format_appointment_payload( $appointment ) : [];
        $last_visit       = self::get_last_pet_visit_payload( $client_id, $pet->ID );

        return [
            'id'              => $pet->ID,
            'name'            => get_the_title( $pet->ID ),
            'species'         => $species,
            'speciesLabel'    => self::get_species_label( $species ),
            'breed'           => $breed,
            'size'            => $size,
            'sizeLabel'       => self::get_size_label( $size ),
            'nextAppointment' => $next_appointment,
            'lastVisit'       => $last_visit,
        ];
    }

    /**
     * Busca pets do cliente usando repositorio quando disponivel.
     *
     * @param int $client_id ID do cliente.
     * @return WP_Post[]
     */
    private static function get_client_pets( int $client_id ): array {
        if ( class_exists( 'DPS_Pet_Repository' ) && method_exists( 'DPS_Pet_Repository', 'get_instance' ) ) {
            $pets = DPS_Pet_Repository::get_instance()->get_pets_by_client( $client_id );
            if ( is_array( $pets ) ) {
                return array_values( array_filter( $pets, static fn( $pet ) => $pet instanceof WP_Post ) );
            }
        }

        return get_posts(
            [
                'post_type'      => 'dps_pet',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => 'owner_id',
                'meta_value'     => $client_id,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );
    }

    /**
     * Busca o proximo agendamento do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return WP_Post|null
     */
    private static function get_next_appointment( int $client_id ): ?WP_Post {
        if ( class_exists( 'DPS_Appointment_Repository' ) && method_exists( 'DPS_Appointment_Repository', 'get_instance' ) ) {
            $appointment = DPS_Appointment_Repository::get_instance()->get_next_appointment_for_client( $client_id );
            if ( $appointment instanceof WP_Post ) {
                return $appointment;
            }
        }

        $appointments = get_posts(
            [
                'post_type'      => 'dps_agendamento',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => 'appointment_date',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_client_id',
                        'value'   => $client_id,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        if ( empty( $appointments ) ) {
            return null;
        }

        $today = current_time( 'Y-m-d' );
        foreach ( $appointments as $appointment ) {
            $date   = (string) get_post_meta( $appointment->ID, 'appointment_date', true );
            $status = strtolower( sanitize_text_field( (string) get_post_meta( $appointment->ID, 'appointment_status', true ) ) );
            if ( '' === $date ) {
                continue;
            }
            if ( strtotime( $date ) < strtotime( $today ) ) {
                continue;
            }
            if ( in_array( $status, [ 'finalizado', 'finalizado_pago', 'finalizado e pago', 'cancelado' ], true ) ) {
                continue;
            }

            return $appointment;
        }

        return null;
    }

    /**
     * Retorna ultimo atendimento finalizado do pet.
     *
     * @param int $client_id ID do cliente.
     * @param int $pet_id    ID do pet.
     * @return array
     */
    private static function get_last_pet_visit_payload( int $client_id, int $pet_id ): array {
        $appointment = null;

        if ( class_exists( 'DPS_Appointment_Repository' ) && method_exists( 'DPS_Appointment_Repository', 'get_instance' ) ) {
            $appointment = DPS_Appointment_Repository::get_instance()->get_last_finished_appointment_for_pet( $client_id, $pet_id );
        }

        if ( ! $appointment instanceof WP_Post ) {
            return [];
        }

        $date = (string) get_post_meta( $appointment->ID, 'appointment_date', true );
        if ( '' === $date ) {
            return [];
        }

        return [
            'date'      => $date,
            'dateLabel' => self::format_date_label( $date ),
        ];
    }

    /**
     * Extrai pet principal de um agendamento.
     *
     * @param WP_Post $appointment Agendamento.
     * @return int
     */
    private static function extract_pet_id_from_appointment( WP_Post $appointment ): int {
        $pet_id = absint( get_post_meta( $appointment->ID, 'appointment_pet_id', true ) );
        if ( $pet_id > 0 ) {
            return $pet_id;
        }

        $pet_ids = get_post_meta( $appointment->ID, 'appointment_pet_ids', true );
        if ( is_array( $pet_ids ) && ! empty( $pet_ids ) ) {
            return absint( reset( $pet_ids ) );
        }

        if ( is_string( $pet_ids ) && '' !== $pet_ids ) {
            $parts = array_filter( array_map( 'absint', explode( ',', $pet_ids ) ) );
            if ( ! empty( $parts ) ) {
                return (int) reset( $parts );
            }
        }

        return 0;
    }

    /**
     * Formata payload do agendamento.
     *
     * @param WP_Post $appointment Agendamento.
     * @return array
     */
    private static function format_appointment_payload( WP_Post $appointment ): array {
        $date   = (string) get_post_meta( $appointment->ID, 'appointment_date', true );
        $status = strtolower( sanitize_text_field( (string) get_post_meta( $appointment->ID, 'appointment_status', true ) ) );

        return [
            'id'          => $appointment->ID,
            'date'        => $date,
            'dateLabel'   => self::format_date_label( $date ),
            'status'      => $status,
            'statusLabel' => self::get_status_label( $status ),
            'daysUntil'   => self::get_days_until( $date ),
        ];
    }

    /**
     * Monta evento contextual do ecossistema.
     *
     * @param array $summary      Resumo do jogo.
     * @param array $featured_pet Pet em destaque.
     * @param array $loyalty      Dados de loyalty.
     * @return array
     */
    private static function build_context_event( array $summary, array $featured_pet, array $loyalty ): array {
        $mission = isset( $summary['mission'] ) && is_array( $summary['mission'] ) ? $summary['mission'] : [];

        if ( ! empty( $featured_pet['nextAppointment'] ) ) {
            $appointment = $featured_pet['nextAppointment'];
            $pet_name    = isset( $featured_pet['name'] ) ? (string) $featured_pet['name'] : __( 'seu pet', 'dps-game' );
            $days_until  = isset( $appointment['daysUntil'] ) ? (int) $appointment['daysUntil'] : null;

            if ( 0 === $days_until ) {
                $description = sprintf( __( 'O proximo atendimento de %s acontece hoje. Uma run curta ajuda a entrar no clima do banho.', 'dps-game' ), $pet_name );
            } elseif ( 1 === $days_until ) {
                $description = sprintf( __( 'O proximo atendimento de %s e amanha, em %s.', 'dps-game' ), $pet_name, $appointment['dateLabel'] );
            } else {
                $description = sprintf( __( 'O proximo atendimento de %s esta marcado para %s.', 'dps-game' ), $pet_name, $appointment['dateLabel'] );
            }

            return [
                'tone'        => 'primary',
                'label'       => __( 'Ecossistema DPS', 'dps-game' ),
                'title'       => sprintf( __( 'Preparacao de %s', 'dps-game' ), $pet_name ),
                'description' => $description,
                'meta'        => $appointment['statusLabel'],
            ];
        }

        if ( ! empty( $mission['completed'] ) ) {
            return [
                'tone'        => 'success',
                'label'       => __( 'Status do dia', 'dps-game' ),
                'title'       => __( 'Missao sincronizada no portal', 'dps-game' ),
                'description' => ! empty( $loyalty['enabled'] )
                    ? __( 'As recompensas do jogo ja podem refletir na sua jornada de fidelidade.', 'dps-game' )
                    : __( 'Seu progresso de hoje ja esta salvo no ecossistema do portal.', 'dps-game' ),
                'meta'        => __( 'Tudo em dia', 'dps-game' ),
            ];
        }

        if ( ! empty( $featured_pet['lastVisit'] ) ) {
            return [
                'tone'        => 'secondary',
                'label'       => __( 'Historico do pet', 'dps-game' ),
                'title'       => sprintf( __( '%s ja conhece a nave', 'dps-game' ), $featured_pet['name'] ),
                'description' => sprintf( __( 'Ultimo atendimento registrado em %s. O jogo fica pronto para acompanhar a proxima visita.', 'dps-game' ), $featured_pet['lastVisit']['dateLabel'] ),
                'meta'        => __( 'Historico ativo', 'dps-game' ),
            ];
        }

        if ( ! empty( $featured_pet ) ) {
            $pet_name  = isset( $featured_pet['name'] ) ? (string) $featured_pet['name'] : __( 'Seu pet', 'dps-game' );
            $pet_focus = trim( implode( ' / ', array_filter( [ $featured_pet['speciesLabel'] ?? '', $featured_pet['breed'] ?? '', $featured_pet['sizeLabel'] ?? '' ] ) ) );

            return [
                'tone'        => 'tertiary',
                'label'       => __( 'Identidade do pet', 'dps-game' ),
                'title'       => sprintf( __( '%s virou piloto do dia', 'dps-game' ), $pet_name ),
                'description' => '' !== $pet_focus
                    ? sprintf( __( 'Contexto atual do portal: %s.', 'dps-game' ), $pet_focus )
                    : __( 'O jogo ja reconhece o pet em destaque deste cliente no portal.', 'dps-game' ),
                'meta'        => __( 'Perfil conectado', 'dps-game' ),
            ];
        }

        return [
            'tone'        => 'secondary',
            'label'       => __( 'Portal DPS', 'dps-game' ),
            'title'       => __( 'Perfil de jogo pronto para expandir', 'dps-game' ),
            'description' => __( 'Assim que houver pet, historico ou fidelidade, o jogo usa esses dados sem criar acoplamento fragil.', 'dps-game' ),
            'meta'        => __( 'Modo leve', 'dps-game' ),
        ];
    }

    /**
     * Monta resumo de loyalty focado no jogo.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    private static function get_loyalty_details( int $client_id ): array {
        if ( $client_id <= 0 || ! class_exists( 'DPS_Loyalty_API' ) ) {
            return [
                'enabled'          => false,
                'points'           => 0,
                'tier'             => [],
                'gamePoints'       => 0,
                'gameRewardsCount' => 0,
                'lastGameReward'   => [],
            ];
        }

        $points     = (int) DPS_Loyalty_API::get_points( $client_id );
        $tier       = DPS_Loyalty_API::get_loyalty_tier( $client_id );
        $logs       = function_exists( 'dps_loyalty_get_logs' ) ? dps_loyalty_get_logs( $client_id, [ 'limit' => 0 ] ) : get_post_meta( $client_id, 'dps_loyalty_points_log' );
        $game_total = 0;
        $game_count = 0;
        $last_game  = [];

        if ( is_array( $logs ) ) {
            foreach ( $logs as $log ) {
                if ( ! is_array( $log ) ) {
                    continue;
                }

                $context = isset( $log['context'] ) ? sanitize_text_field( (string) $log['context'] ) : '';
                $action  = isset( $log['action'] ) ? sanitize_key( (string) $log['action'] ) : '';
                $value   = isset( $log['points'] ) ? (int) $log['points'] : 0;
                if ( ! in_array( $context, self::GAME_LOYALTY_CONTEXTS, true ) || 'add' !== $action || $value <= 0 ) {
                    continue;
                }

                $game_total += $value;
                $game_count++;

                if ( empty( $last_game ) ) {
                    $last_game = [
                        'points'    => $value,
                        'context'   => $context,
                        'label'     => DPS_Loyalty_API::get_context_label( $context ),
                        'date'      => isset( $log['date'] ) ? sanitize_text_field( (string) $log['date'] ) : '',
                        'dateLabel' => isset( $log['date'] ) ? self::format_date_label( (string) $log['date'] ) : '',
                    ];
                }
            }
        }

        return [
            'enabled'          => true,
            'points'           => $points,
            'tier'             => is_array( $tier ) ? $tier : [],
            'gamePoints'       => $game_total,
            'gameRewardsCount' => $game_count,
            'lastGameReward'   => $last_game,
        ];
    }

    /**
     * Rotulo de especie.
     *
     * @param string $species Especie.
     * @return string
     */
    private static function get_species_label( string $species ): string {
        if ( class_exists( 'DPS_Pet_Handler' ) && method_exists( 'DPS_Pet_Handler', 'get_species_label' ) ) {
            return sanitize_text_field( (string) DPS_Pet_Handler::get_species_label( $species ) );
        }

        $map = [
            'cao'   => __( 'Cachorro', 'dps-game' ),
            'gato'  => __( 'Gato', 'dps-game' ),
            'outro' => __( 'Outro', 'dps-game' ),
        ];

        return isset( $map[ $species ] ) ? $map[ $species ] : __( 'Pet', 'dps-game' );
    }

    /**
     * Rotulo de porte.
     *
     * @param string $size Porte.
     * @return string
     */
    private static function get_size_label( string $size ): string {
        $map = [
            'pequeno' => __( 'Porte pequeno', 'dps-game' ),
            'medio'   => __( 'Porte medio', 'dps-game' ),
            'grande'  => __( 'Porte grande', 'dps-game' ),
            'small'   => __( 'Porte pequeno', 'dps-game' ),
            'medium'  => __( 'Porte medio', 'dps-game' ),
            'large'   => __( 'Porte grande', 'dps-game' ),
        ];

        return isset( $map[ $size ] ) ? $map[ $size ] : '';
    }

    /**
     * Rotulo amigavel do status do agendamento.
     *
     * @param string $status Status bruto.
     * @return string
     */
    private static function get_status_label( string $status ): string {
        $map = [
            'agendado'          => __( 'Agendado', 'dps-game' ),
            'confirmado'        => __( 'Confirmado', 'dps-game' ),
            'pendente'          => __( 'Pendente', 'dps-game' ),
            'em_andamento'      => __( 'Em andamento', 'dps-game' ),
            'em andamento'      => __( 'Em andamento', 'dps-game' ),
            'finalizado'        => __( 'Finalizado', 'dps-game' ),
            'finalizado_pago'   => __( 'Finalizado e pago', 'dps-game' ),
            'finalizado e pago' => __( 'Finalizado e pago', 'dps-game' ),
            'cancelado'         => __( 'Cancelado', 'dps-game' ),
        ];

        return isset( $map[ $status ] ) ? $map[ $status ] : __( 'Status em andamento', 'dps-game' );
    }

    /**
     * Formata data para exibicao.
     *
     * @param string $date Data bruta.
     * @return string
     */
    private static function format_date_label( string $date ): string {
        if ( '' === $date ) {
            return '';
        }

        $timestamp = strtotime( $date );
        if ( false === $timestamp ) {
            return sanitize_text_field( $date );
        }

        return wp_date( get_option( 'date_format', 'd/m/Y' ), $timestamp );
    }

    /**
     * Calcula diferenca de dias ate uma data.
     *
     * @param string $date Data do agendamento.
     * @return int|null
     */
    private static function get_days_until( string $date ): ?int {
        if ( '' === $date ) {
            return null;
        }

        $target = DateTimeImmutable::createFromFormat( 'Y-m-d', substr( $date, 0, 10 ), wp_timezone() );
        $today  = new DateTimeImmutable( current_time( 'Y-m-d' ), wp_timezone() );
        if ( false === $target ) {
            return null;
        }

        return (int) $today->diff( $target )->format( '%r%a' );
    }
}


