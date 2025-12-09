<?php
/**
 * Helper de conquistas do programa de fidelidade.
 *
 * @package Desi_Pet_Shower_Loyalty
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Loyalty_Achievements {

    const META_KEY = '_dps_loyalty_achievements';

    /**
     * Retorna o catálogo de conquistas disponíveis.
     *
     * @return array
     */
    public static function get_achievements_definitions() {
        return [
            'first_bath'       => [
                'label'       => __( 'Primeiro Banho', 'dps-loyalty-addon' ),
                'description' => __( 'Concluiu o primeiro atendimento.', 'dps-loyalty-addon' ),
                'type'        => 'appointment_count',
                'threshold'   => 1,
            ],
            'loyal_client'     => [
                'label'       => __( 'Fiel da Casa', 'dps-loyalty-addon' ),
                'description' => __( 'Concluiu 10 atendimentos.', 'dps-loyalty-addon' ),
                'type'        => 'appointment_count',
                'threshold'   => 10,
            ],
            'referral_master'  => [
                'label'       => __( 'Indicador Master', 'dps-loyalty-addon' ),
                'description' => __( 'Concluiu 5 indicações válidas.', 'dps-loyalty-addon' ),
                'type'        => 'referrals_completed',
                'threshold'   => 5,
            ],
            'vip'              => [
                'label'       => __( 'VIP', 'dps-loyalty-addon' ),
                'description' => __( 'Alcançou o nível máximo de fidelidade.', 'dps-loyalty-addon' ),
                'type'        => 'tier_reached',
                'tier'        => 'max',
            ],
        ];
    }

    /**
     * Obtém conquistas já desbloqueadas pelo cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array
     */
    public static function get_client_achievements( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return [];
        }

        $achievements = get_post_meta( $client_id, self::META_KEY, true );
        if ( ! is_array( $achievements ) ) {
            return [];
        }

        return array_values( array_unique( array_map( 'sanitize_key', $achievements ) ) );
    }

    /**
     * Verifica se cliente já possui conquista específica.
     *
     * @param int    $client_id       ID do cliente.
     * @param string $achievement_key Chave da conquista.
     * @return bool
     */
    public static function has_achievement( $client_id, $achievement_key ) {
        $achievements = self::get_client_achievements( $client_id );
        return in_array( $achievement_key, $achievements, true );
    }

    /**
     * Registra conquista para o cliente.
     *
     * @param int    $client_id       ID do cliente.
     * @param string $achievement_key Conquista a registrar.
     */
    public static function add_achievement( $client_id, $achievement_key ) {
        $client_id       = (int) $client_id;
        $achievement_key = sanitize_key( $achievement_key );

        if ( $client_id <= 0 || ! $achievement_key ) {
            return;
        }

        $current = self::get_client_achievements( $client_id );
        if ( in_array( $achievement_key, $current, true ) ) {
            return;
        }

        $current[] = $achievement_key;
        update_post_meta( $client_id, self::META_KEY, $current );

        /**
         * Disparado quando uma conquista é desbloqueada.
         *
         * @param int    $client_id       ID do cliente.
         * @param string $achievement_key Chave da conquista desbloqueada.
         */
        do_action( 'dps_loyalty_achievement_unlocked', $client_id, $achievement_key );
    }

    /**
     * Avalia e concede conquistas elegíveis para o cliente.
     *
     * @param int $client_id ID do cliente.
     */
    public static function evaluate_achievements_for_client( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return;
        }

        $definitions  = self::get_achievements_definitions();
        $unlocked     = self::get_client_achievements( $client_id );
        $appointments = self::get_completed_appointments_count( $client_id );
        $referrals    = self::get_completed_referrals_count( $client_id );
        $tier_info    = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_loyalty_tier( $client_id ) : [];
        $max_tier     = class_exists( 'DPS_Loyalty_API' ) ? DPS_Loyalty_API::get_highest_tier_slug() : 'ouro';

        foreach ( $definitions as $key => $definition ) {
            if ( in_array( $key, $unlocked, true ) ) {
                continue;
            }

            $type = isset( $definition['type'] ) ? $definition['type'] : '';
            switch ( $type ) {
                case 'appointment_count':
                    $threshold = isset( $definition['threshold'] ) ? (int) $definition['threshold'] : 0;
                    if ( $threshold > 0 && $appointments >= $threshold ) {
                        self::add_achievement( $client_id, $key );
                    }
                    break;
                case 'referrals_completed':
                    $threshold = isset( $definition['threshold'] ) ? (int) $definition['threshold'] : 0;
                    if ( $threshold > 0 && $referrals >= $threshold ) {
                        self::add_achievement( $client_id, $key );
                    }
                    break;
                case 'tier_reached':
                    $target_tier = isset( $definition['tier'] ) ? $definition['tier'] : '';
                    if ( $target_tier === 'max' && ! empty( $tier_info['current'] ) && $tier_info['current'] === $max_tier ) {
                        self::add_achievement( $client_id, $key );
                    } elseif ( $target_tier && ! empty( $tier_info['current'] ) && $tier_info['current'] === $target_tier ) {
                        self::add_achievement( $client_id, $key );
                    }
                    break;
            }
        }
    }

    /**
     * Conta atendimentos finalizados para o cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int
     */
    private static function get_completed_appointments_count( $client_id ) {
        $query = new WP_Query( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'appointment_client_id',
                    'value' => $client_id,
                ],
                [
                    'key'   => 'appointment_status',
                    'value' => 'finalizado',
                ],
            ],
        ] );

        if ( ! $query->found_posts ) {
            return 0;
        }

        return (int) $query->found_posts;
    }

    /**
     * Conta indicações concluídas.
     *
     * @param int $client_id ID do cliente.
     * @return int
     */
    private static function get_completed_referrals_count( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE referrer_client_id = %d AND status = %s",
            $client_id,
            'rewarded'
        ) );

        return (int) $count;
    }
}
