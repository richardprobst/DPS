<?php
/**
 * API pública do Loyalty Add-on.
 *
 * Centraliza todas as funções públicas para uso por outros add-ons.
 *
 * @package Desi_Pet_Shower_Loyalty
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Loyalty_API
 *
 * Fornece métodos estáticos para manipulação de pontos, créditos e indicações.
 */
class DPS_Loyalty_API {

    /**
     * Adiciona pontos ao cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param int    $points    Quantidade de pontos a adicionar.
     * @param string $context   Contexto do evento (ex: 'appointment_payment', 'referral_reward').
     * @return int|false Novo saldo de pontos ou false em caso de erro.
     */
    public static function add_points( $client_id, $points, $context = '' ) {
        return dps_loyalty_add_points( $client_id, $points, $context );
    }

    /**
     * Obtém saldo de pontos do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int Saldo de pontos (nunca negativo).
     */
    public static function get_points( $client_id ) {
        return dps_loyalty_get_points( $client_id );
    }

    /**
     * Resgata pontos do cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param int    $points    Quantidade de pontos a resgatar.
     * @param string $context   Contexto do resgate.
     * @return int|false Novo saldo ou false se saldo insuficiente.
     */
    public static function redeem_points( $client_id, $points, $context = '' ) {
        return dps_loyalty_redeem_points( $client_id, $points, $context );
    }

    /**
     * Adiciona crédito ao cliente.
     *
     * @param int    $client_id       ID do cliente.
     * @param int    $amount_in_cents Valor em centavos.
     * @param string $context         Contexto do crédito.
     * @return int Novo saldo de crédito.
     */
    public static function add_credit( $client_id, $amount_in_cents, $context = '' ) {
        return dps_loyalty_add_credit( $client_id, $amount_in_cents, $context );
    }

    /**
     * Obtém saldo de crédito do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return int Saldo em centavos.
     */
    public static function get_credit( $client_id ) {
        return dps_loyalty_get_credit( $client_id );
    }

    /**
     * Usa crédito do cliente.
     *
     * @param int    $client_id       ID do cliente.
     * @param int    $amount_in_cents Valor a usar em centavos.
     * @param string $context         Contexto do uso.
     * @return int Valor efetivamente usado.
     */
    public static function use_credit( $client_id, $amount_in_cents, $context = '' ) {
        return dps_loyalty_use_credit( $client_id, $amount_in_cents, $context );
    }

    /**
     * Obtém código de indicação do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string Código de indicação.
     */
    public static function get_referral_code( $client_id ) {
        return dps_loyalty_get_referral_code( $client_id );
    }

    /**
     * Obtém URL de indicação do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string URL com código de indicação.
     */
    public static function get_referral_url( $client_id ) {
        $code = self::get_referral_code( $client_id );
        if ( ! $code ) {
            return '';
        }

        // Obtém URL base da configuração de fidelidade ou da página de cadastro
        $settings = get_option( 'dps_loyalty_settings', [] );
        $base_url = '';

        // 1. Verifica se há página configurada nas configurações de fidelidade
        if ( ! empty( $settings['referral_page_id'] ) ) {
            $page_id = (int) $settings['referral_page_id'];
            if ( $page_id > 0 ) {
                $base_url = get_permalink( $page_id );
            }
        }

        // 2. Fallback: tenta usar a página de cadastro do Registration Add-on
        if ( empty( $base_url ) ) {
            $registration_page_id = (int) get_option( 'dps_registration_page_id', 0 );
            if ( $registration_page_id > 0 ) {
                $base_url = get_permalink( $registration_page_id );
            }
        }

        // 3. Fallback final: usa home com /cadastro/ como convenção
        if ( empty( $base_url ) ) {
            $base_url = site_url( '/cadastro/' );
        }

        // Filtro para customizar URL base de indicação
        $base_url = apply_filters( 'dps_loyalty_referral_base_url', $base_url );
        
        return add_query_arg( 'ref', $code, $base_url );
    }

    /**
     * Obtém estatísticas de indicações do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Estatísticas com total, recompensadas e pendentes.
     */
    public static function get_referral_stats( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE referrer_client_id = %d",
            $client_id
        ) );

        $rewarded = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE referrer_client_id = %d AND status = 'rewarded'",
            $client_id
        ) );

        return [
            'total'    => (int) $total,
            'rewarded' => (int) $rewarded,
            'pending'  => (int) $total - (int) $rewarded,
        ];
    }

    /**
     * Obtém histórico de pontos do cliente.
     *
     * @param int   $client_id ID do cliente.
     * @param array $args      Argumentos opcionais: limit (int), offset (int).
     * @return array Lista de eventos.
     */
    public static function get_points_history( $client_id, $args = [] ) {
        // Compatibilidade: se o segundo parâmetro for inteiro, trata como limit.
        if ( ! is_array( $args ) ) {
            $args = [ 'limit' => (int) $args ];
        }

        return dps_loyalty_get_logs( $client_id, $args );
    }

    /**
     * Converte contexto técnico em rótulo amigável.
     *
     * @param string $context Contexto salvo no log.
     * @return string Rótulo traduzido.
     */
    public static function get_context_label( $context ) {
        $labels = [
            'appointment_payment' => __( 'Pagamento de atendimento', 'dps-loyalty-addon' ),
            'referral_reward'     => __( 'Recompensa de indicação', 'dps-loyalty-addon' ),
            'credit_add'          => __( 'Crédito adicionado', 'dps-loyalty-addon' ),
            'credit_use'          => __( 'Crédito utilizado', 'dps-loyalty-addon' ),
            'manual_adjustment'   => __( 'Ajuste manual', 'dps-loyalty-addon' ),
            'points_expired'      => __( 'Pontos expirados', 'dps-loyalty-addon' ),
            'redeem'              => __( 'Resgate de pontos', 'dps-loyalty-addon' ),
            'portal_redemption'   => __( 'Resgate no Portal', 'dps-loyalty-addon' ),
            'expiration_warning'  => __( 'Aviso de expiração de pontos', 'dps-loyalty-addon' ),
        ];

        $label = isset( $labels[ $context ] ) ? $labels[ $context ] : $context;

        return apply_filters( 'dps_loyalty_context_label', $label, $context );
    }

    /**
     * Obtém nível de fidelidade do cliente, recalculando e persistindo quando necessário.
     *
     * @param int $client_id ID do cliente.
     * @return array Dados do nível atual.
     */
    public static function get_loyalty_tier( $client_id ) {
        return self::recalculate_client_tier( $client_id );
    }

    /**
     * Obtém a configuração de níveis (personalizada ou padrão).
     *
     * @return array Lista ordenada de níveis.
     */
    public static function get_tiers_config() {
        $settings = get_option( 'dps_loyalty_settings', [] );
        $tiers    = isset( $settings['loyalty_tiers'] ) ? $settings['loyalty_tiers'] : [];

        if ( empty( $tiers ) || ! is_array( $tiers ) ) {
            return self::get_default_tiers();
        }

        $normalized = [];
        foreach ( $tiers as $index => $tier ) {
            // Compatibilidade: aceita formato associativo [ slug => data ]
            if ( self::is_associative_tiers( $tiers ) && isset( $tiers['bronze'] ) ) {
                $normalized = self::normalize_legacy_tiers( $tiers );
                break;
            }

            if ( empty( $tier['slug'] ) ) {
                continue;
            }

            $slug = sanitize_key( $tier['slug'] );
            $normalized[] = [
                'slug'       => $slug,
                'label'      => isset( $tier['label'] ) ? sanitize_text_field( $tier['label'] ) : strtoupper( $slug ),
                'min_points' => isset( $tier['min_points'] ) ? (int) $tier['min_points'] : 0,
                'multiplier' => isset( $tier['multiplier'] ) ? (float) $tier['multiplier'] : 1.0,
                'icon'       => isset( $tier['icon'] ) ? sanitize_text_field( $tier['icon'] ) : '⭐',
                'color'      => isset( $tier['color'] ) ? sanitize_hex_color( $tier['color'] ) : '',
            ];
        }

        if ( empty( $normalized ) ) {
            return self::get_default_tiers();
        }

        usort(
            $normalized,
            function ( $a, $b ) {
                return $a['min_points'] <=> $b['min_points'];
            }
        );

        return $normalized;
    }

    /**
     * Recalcula o nível do cliente e registra mudança se necessário.
     *
     * @param int $client_id Cliente.
     * @return array
     */
    public static function recalculate_client_tier( $client_id ) {
        $points    = self::get_points( $client_id );
        $tier_info = self::calculate_tier_from_points( $points );

        $previous = get_post_meta( $client_id, '_dps_loyalty_tier', true );
        $current  = isset( $tier_info['current'] ) ? $tier_info['current'] : '';

        if ( $current && $current !== $previous ) {
            update_post_meta( $client_id, '_dps_loyalty_tier', $current );
            do_action( 'dps_loyalty_tier_changed', $client_id, $previous, $current );
            if ( class_exists( 'DPS_Loyalty_Achievements' ) ) {
                DPS_Loyalty_Achievements::evaluate_achievements_for_client( $client_id );
            }
        }

        return $tier_info;
    }

    /**
     * Calcula nível com base no saldo de pontos.
     *
     * @param int $points Pontos atuais.
     * @return array
     */
    public static function calculate_tier_from_points( $points ) {
        $tiers = self::get_tiers_config();
        if ( empty( $tiers ) ) {
            return [];
        }

        $current     = $tiers[0];
        $current_key = 0;
        foreach ( $tiers as $index => $tier ) {
            if ( $points >= $tier['min_points'] ) {
                $current     = $tier;
                $current_key = $index;
            }
        }

        $next_tier   = isset( $tiers[ $current_key + 1 ] ) ? $tiers[ $current_key + 1 ] : null;
        $next_points = $next_tier ? $next_tier['min_points'] : null;

        return [
            'current'     => $current['slug'],
            'label'       => $current['label'],
            'icon'        => $current['icon'],
            'multiplier'  => $current['multiplier'],
            'points'      => $points,
            'next_tier'   => $next_tier ? $next_tier['slug'] : null,
            'next_label'  => $next_tier ? $next_tier['label'] : null,
            'next_points' => $next_points,
            'progress'    => $next_points ? min( 100, round( ( $points / $next_points ) * 100 ) ) : 100,
            'color'       => isset( $current['color'] ) ? $current['color'] : '',
        ];
    }

    /**
     * Recupera multiplicador para um nível.
     *
     * @param string $tier_slug Slug do nível.
     * @return float
     */
    public static function get_tier_multiplier( $tier_slug ) {
        $tiers = self::get_tiers_config();
        foreach ( $tiers as $tier ) {
            if ( $tier['slug'] === $tier_slug ) {
                return (float) $tier['multiplier'];
            }
        }

        return 1.0;
    }

    /**
     * Retorna slug do nível máximo configurado.
     *
     * @return string
     */
    public static function get_highest_tier_slug() {
        $tiers = self::get_tiers_config();
        if ( empty( $tiers ) ) {
            return 'ouro';
        }

        $last = end( $tiers );
        return isset( $last['slug'] ) ? $last['slug'] : 'ouro';
    }

    /**
     * Retorna níveis de fidelidade padrão.
     *
     * @return array Configuração de níveis.
     */
    public static function get_default_tiers() {
        return [
            [
                'slug'       => 'bronze',
                'min_points' => 0,
                'multiplier' => 1.0,
                'label'      => __( 'Bronze', 'dps-loyalty-addon' ),
                'icon'       => '🥉',
                'color'      => '#b45309',
            ],
            [
                'slug'       => 'prata',
                'min_points' => 500,
                'multiplier' => 1.5,
                'label'      => __( 'Prata', 'dps-loyalty-addon' ),
                'icon'       => '🥈',
                'color'      => '#6b7280',
            ],
            [
                'slug'       => 'ouro',
                'min_points' => 1000,
                'multiplier' => 2.0,
                'label'      => __( 'Ouro', 'dps-loyalty-addon' ),
                'icon'       => '🥇',
                'color'      => '#d97706',
            ],
        ];
    }

    /**
     * Verifica se array é associativo.
     *
     * @param array $array Array a verificar.
     * @return bool
     */
    private static function is_associative_tiers( $array ) {
        if ( ! is_array( $array ) || empty( $array ) ) {
            return false;
        }

        return array_keys( $array ) !== range( 0, count( $array ) - 1 );
    }

    /**
     * Normaliza configuração antiga em formato associativo.
     *
     * @param array $tiers Configuração legada.
     * @return array
     */
    private static function normalize_legacy_tiers( $tiers ) {
        $normalized = [];
        foreach ( $tiers as $slug => $data ) {
            $normalized[] = [
                'slug'       => sanitize_key( $slug ),
                'label'      => isset( $data['label'] ) ? $data['label'] : strtoupper( $slug ),
                'min_points' => isset( $data['min_points'] ) ? (int) $data['min_points'] : 0,
                'multiplier' => isset( $data['multiplier'] ) ? (float) $data['multiplier'] : 1.0,
                'icon'       => isset( $data['icon'] ) ? $data['icon'] : '⭐',
                'color'      => isset( $data['color'] ) ? $data['color'] : '',
            ];
        }

        usort(
            $normalized,
            function ( $a, $b ) {
                return $a['min_points'] <=> $b['min_points'];
            }
        );

        return $normalized;
    }

    /**
     * Calculates the number of points for a given monetary amount.
     *
     * Applies the client's tier multiplier if a client_id is provided.
     * Useful for displaying expected points before completing a transaction.
     *
     * Note: This method is intentionally separate from the private method
     * `DPS_Loyalty_Addon::calculate_points_from_value()` which handles the actual
     * point awarding with hooks. This API method provides a preview/calculation
     * without side effects.
     *
     * @since 1.2.0
     *
     * @param float $amount    The monetary amount in BRL.
     * @param int   $client_id Optional. Client ID to apply tier multiplier. Default 0.
     * @return array {
     *     @type int   $base_points   Points before multiplier.
     *     @type int   $total_points  Points after multiplier.
     *     @type int   $bonus_points  Bonus points from multiplier.
     *     @type float $multiplier    The multiplier applied.
     *     @type string $tier_label   The client's tier label.
     * }
     */
    public static function calculate_points_for_amount( $amount, $client_id = 0 ) {
        $settings = get_option( 'dps_loyalty_settings', [] );
        $brl_per_point = isset( $settings['brl_per_point'] ) && $settings['brl_per_point'] > 0 
            ? (float) $settings['brl_per_point'] 
            : 10.0;

        $base_points = $amount > 0 ? (int) floor( $amount / $brl_per_point ) : 0;
        $multiplier = 1.0;
        $tier_label = __( 'Bronze', 'dps-loyalty-addon' );

        if ( $client_id > 0 ) {
            $tier_info = self::get_loyalty_tier( $client_id );
            $multiplier = isset( $tier_info['multiplier'] ) ? (float) $tier_info['multiplier'] : 1.0;
            $tier_label = isset( $tier_info['label'] ) ? $tier_info['label'] : $tier_label;
        }

        $total_points = (int) floor( $base_points * $multiplier );
        $bonus_points = $total_points - $base_points;

        return [
            'base_points'  => $base_points,
            'total_points' => $total_points,
            'bonus_points' => $bonus_points,
            'multiplier'   => $multiplier,
            'tier_label'   => $tier_label,
        ];
    }

    /**
     * Gets top clients by loyalty points.
     *
     * @since 1.2.0
     *
     * @param int $limit Number of clients to return. Default 10.
     * @return array List of clients with ID, name and points.
     */
    public static function get_top_clients( $limit = 10 ) {
        global $wpdb;
        
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID, p.post_title as name, CAST(pm.meta_value AS UNSIGNED) as points
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'dps_cliente'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'dps_loyalty_points'
            AND pm.meta_value > 0
            ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
            LIMIT %d
        ", $limit ) );

        $clients = [];
        foreach ( $results as $row ) {
            $tier_info = self::get_loyalty_tier( $row->ID );
            $clients[] = [
                'id'         => (int) $row->ID,
                'name'       => $row->name,
                'points'     => (int) $row->points,
                'tier'       => $tier_info['current'],
                'tier_label' => $tier_info['label'],
                'tier_icon'  => $tier_info['icon'],
            ];
        }

        return $clients;
    }

    /**
     * Obtém dados agregados de pontos concedidos e resgatados por mês.
     *
     * @since 1.4.0
     *
     * @param int $months Número de meses para buscar (retroativo a partir do mês atual).
     * @return array
     */
    public static function get_points_timeseries( $months = 6 ) {
        $months        = max( 1, absint( $months ) );
        $start         = new DateTime( 'first day of this month', wp_timezone() );
        $labels        = [];
        $granted_data  = [];
        $redeemed_data = [];

        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $label              = clone $start;
            $label_key          = $label->modify( '-' . $i . ' months' )->format( 'Y-m' );
            $labels[ $label_key ] = $label->format( 'm/Y' );
            $granted_data[ $label_key ]  = 0;
            $redeemed_data[ $label_key ] = 0;
        }

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ] );

        if ( empty( $clients ) ) {
            return [
                'labels'         => array_values( $labels ),
                'granted'        => array_values( $granted_data ),
                'redeemed'       => array_values( $redeemed_data ),
                'labels_indexes' => array_keys( $labels ),
            ];
        }

        $cutoff = ( new DateTime( 'first day of this month', wp_timezone() ) )->modify( '-' . ( $months - 1 ) . ' months' );

        foreach ( $clients as $client_id ) {
            $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
            if ( empty( $logs ) ) {
                continue;
            }

            foreach ( $logs as $log ) {
                if ( empty( $log['date'] ) ) {
                    continue;
                }

                $log_date = date_create( $log['date'] );
                if ( ! $log_date || $log_date < $cutoff ) {
                    continue;
                }

                $month_key = $log_date->format( 'Y-m' );
                if ( ! isset( $granted_data[ $month_key ] ) ) {
                    continue;
                }

                $points = isset( $log['points'] ) ? (int) $log['points'] : 0;
                $action = isset( $log['action'] ) ? $log['action'] : '';

                if ( 'add' === $action ) {
                    $granted_data[ $month_key ] += $points;
                }

                if ( in_array( $action, [ 'redeem', 'expire' ], true ) ) {
                    $redeemed_data[ $month_key ] += $points;
                }
            }
        }

        return [
            'labels'         => array_values( $labels ),
            'labels_indexes' => array_keys( $labels ),
            'granted'        => array_values( $granted_data ),
            'redeemed'       => array_values( $redeemed_data ),
        ];
    }

    /**
     * Retorna distribuição de clientes por nível.
     *
     * @since 1.4.0
     *
     * @return array
     */
    public static function get_tier_distribution() {
        $distribution = self::get_clients_by_tier();
        $tiers        = self::get_tiers_config();
        $defaults     = [];

        foreach ( $tiers as $tier ) {
            $defaults[ $tier['slug'] ] = 0;
        }

        return array_merge( $defaults, $distribution );
    }

    /**
     * Obtém ranking de clientes considerando pontos, resgates, indicações e atendimentos.
     *
     * @since 1.4.0
     *
     * @param array $args {start_date, end_date, limit} Datas em formato Y-m-d.
     * @return array
     */
    public static function get_engagement_ranking( $args = [] ) {
        global $wpdb;

        $defaults = [
            'start_date' => '',
            'end_date'   => '',
            'limit'      => 20,
        ];

        $args      = wp_parse_args( $args, $defaults );
        $limit     = max( 1, absint( $args['limit'] ) );
        $start_raw = ! empty( $args['start_date'] ) ? $args['start_date'] . ' 00:00:00' : '';
        $end_raw   = ! empty( $args['end_date'] ) ? $args['end_date'] . ' 23:59:59' : '';

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ] );

        $ranking = [];
        foreach ( $clients as $client_id ) {
            $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
            if ( empty( $logs ) ) {
                continue;
            }

            $earned  = 0;
            $redeem  = 0;
            foreach ( $logs as $log ) {
                $log_date = isset( $log['date'] ) ? $log['date'] : '';
                if ( $start_raw && $log_date < $start_raw ) {
                    continue;
                }
                if ( $end_raw && $log_date > $end_raw ) {
                    continue;
                }

                $points = isset( $log['points'] ) ? (int) $log['points'] : 0;
                if ( 'add' === $log['action'] ) {
                    $earned += $points;
                }
                if ( in_array( $log['action'], [ 'redeem', 'expire' ], true ) ) {
                    $redeem += $points;
                }
            }

            $referrals_table = $wpdb->prefix . 'dps_referrals';
            $referrals_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$referrals_table} WHERE referrer_client_id = %d", $client_id ) );

            $appointments = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'dps_agendamento' AND post_status = 'publish' AND post_parent = %d",
                $client_id
            ) );

            $score = $earned + ( $referrals_count * 50 ) + ( $appointments * 10 );

            if ( $earned || $redeem || $referrals_count || $appointments ) {
                $ranking[] = [
                    'id'          => $client_id,
                    'name'        => get_the_title( $client_id ),
                    'earned'      => $earned,
                    'redeemed'    => $redeem,
                    'referrals'   => $referrals_count,
                    'appointments'=> $appointments,
                    'score'       => $score,
                ];
            }
        }

        usort(
            $ranking,
            function ( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    return 0;
                }
                return ( $a['score'] < $b['score'] ) ? 1 : -1;
            }
        );

        return array_slice( $ranking, 0, $limit );
    }

    /**
     * Obtém métricas de eficácia por campanha.
     *
     * @since 1.4.0
     *
     * @return array
     */
    public static function get_campaign_effectiveness() {
        $campaigns = get_posts( [
            'post_type'      => 'dps_campaign',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
        ] );

        $data = [];
        if ( empty( $campaigns ) ) {
            return $data;
        }

        foreach ( $campaigns as $campaign ) {
            $eligible    = get_post_meta( $campaign->ID, 'dps_campaign_pending_offers', true );
            $eligible    = is_array( $eligible ) ? array_filter( array_map( 'absint', $eligible ) ) : [];
            $used        = self::count_campaign_usage( $campaign->ID );
            $points      = self::sum_points_by_campaign( $campaign->ID );
            $start_date  = get_post_meta( $campaign->ID, 'dps_campaign_start_date', true );
            $end_date    = get_post_meta( $campaign->ID, 'dps_campaign_end_date', true );

            $data[] = [
                'id'                => $campaign->ID,
                'name'              => $campaign->post_title,
                'start'             => $start_date,
                'end'               => $end_date,
                'eligible'          => count( $eligible ),
                'used'              => $used,
                'usage_rate'        => $eligible ? round( ( $used / count( $eligible ) ) * 100, 1 ) : 0,
                'points'            => $points,
            ];
        }

        return $data;
    }

    /**
     * Soma pontos associados a um contexto de campanha.
     *
     * @since 1.4.0
     *
     * @param int $campaign_id Campaign ID.
     * @return int
     */
    private static function sum_points_by_campaign( $campaign_id ) {
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ] );

        $total = 0;
        foreach ( $clients as $client_id ) {
            $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
            foreach ( $logs as $log ) {
                $context = isset( $log['context'] ) ? $log['context'] : '';
                if ( $context === 'campaign_' . $campaign_id && 'add' === $log['action'] ) {
                    $total += isset( $log['points'] ) ? (int) $log['points'] : 0;
                }
            }
        }

        return $total;
    }

    /**
     * Conta clientes com uso de campanha baseado no contexto do log.
     *
     * @since 1.4.0
     *
     * @param int $campaign_id Campaign ID.
     * @return int
     */
    private static function count_campaign_usage( $campaign_id ) {
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ] );

        $used = 0;
        foreach ( $clients as $client_id ) {
            $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
            foreach ( $logs as $log ) {
                $context = isset( $log['context'] ) ? $log['context'] : '';
                if ( $context === 'campaign_' . $campaign_id ) {
                    $used++;
                    break;
                }
            }
        }

        return $used;
    }

    /**
     * Calcula pontos elegíveis para expiração baseados na data de concessão.
     *
     * @since 1.4.0
     *
     * @param int $client_id Cliente.
     * @param int $months    Meses para expirar.
     * @return int Pontos que podem ser expirados.
     */
    public static function get_expirable_points( $client_id, $months ) {
        $client_id = (int) $client_id;
        $months    = max( 1, absint( $months ) );

        $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
        if ( empty( $logs ) ) {
            return 0;
        }

        usort(
            $logs,
            function ( $a, $b ) {
                $date_a = isset( $a['date'] ) ? $a['date'] : '';
                $date_b = isset( $b['date'] ) ? $b['date'] : '';
                return strcmp( $date_a, $date_b );
            }
        );

        $cutoff    = ( new DateTime( 'now', wp_timezone() ) )->modify( '-' . $months . ' months' );
        $accruals  = [];

        foreach ( $logs as $log ) {
            $points = isset( $log['points'] ) ? (int) $log['points'] : 0;
            $date   = isset( $log['date'] ) ? $log['date'] : '';
            $action = isset( $log['action'] ) ? $log['action'] : '';

            if ( ! $date ) {
                continue;
            }

            if ( 'add' === $action ) {
                $accruals[] = [
                    'remaining' => $points,
                    'date'      => $date,
                ];
                continue;
            }

            if ( in_array( $action, [ 'redeem', 'expire' ], true ) ) {
                $to_reduce = $points;
                foreach ( $accruals as &$accrual ) {
                    if ( $to_reduce <= 0 ) {
                        break;
                    }

                    if ( $accrual['remaining'] <= 0 ) {
                        continue;
                    }

                    $deduct              = min( $accrual['remaining'], $to_reduce );
                    $accrual['remaining'] -= $deduct;
                    $to_reduce           -= $deduct;
                }
                unset( $accrual );
            }
        }

        $expirable = 0;
        foreach ( $accruals as $accrual ) {
            $date_obj = date_create( $accrual['date'] );
            if ( $date_obj && $date_obj <= $cutoff ) {
                $expirable += (int) $accrual['remaining'];
            }
        }

        return max( 0, $expirable );
    }

    /**
     * Expira pontos e registra log dedicado.
     *
     * @since 1.4.0
     *
     * @param int $client_id Cliente.
     * @param int $points    Pontos a expirar.
     * @return int|false Novo saldo ou falso em erro.
     */
    public static function expire_points( $client_id, $points ) {
        $client_id = (int) $client_id;
        $points    = (int) $points;

        if ( $client_id <= 0 || $points <= 0 ) {
            return false;
        }

        $current = self::get_points( $client_id );
        if ( $current <= 0 ) {
            return false;
        }

        $new_balance = max( 0, $current - $points );
        update_post_meta( $client_id, 'dps_loyalty_points', $new_balance );
        dps_loyalty_log_event( $client_id, 'expire', $points, 'points_expired' );

        return $new_balance;
    }

    /**
     * Gets clients grouped by loyalty tier.
     *
     * @since 1.2.0
     *
     * @return array Associative array with tier keys and client counts.
     */
    public static function get_clients_by_tier() {
        global $wpdb;
        $tiers = self::get_tiers_config();

        // Get all clients with points
        $results = $wpdb->get_results( "
            SELECT CAST(pm.meta_value AS UNSIGNED) as points
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'dps_cliente'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'dps_loyalty_points'
        " );

        // Initialize counts
        $counts = [];
        foreach ( $tiers as $tier_data ) {
            $counts[ $tier_data['slug'] ] = 0;
        }

        // Count clients per tier
        foreach ( $results as $row ) {
            $points = (int) $row->points;
            $client_tier = isset( $tiers[0]['slug'] ) ? $tiers[0]['slug'] : 'bronze';

            foreach ( $tiers as $tier ) {
                if ( $points >= $tier['min_points'] ) {
                    $client_tier = $tier['slug'];
                }
            }

            $counts[ $client_tier ]++;
        }

        return $counts;
    }

    /**
     * Obtém métricas globais de fidelidade.
     *
     * Utiliza cache via transient para melhor performance.
     *
     * @param bool $force_refresh Forçar atualização do cache.
     * @return array Métricas do programa.
     */
    public static function get_global_metrics( $force_refresh = false ) {
        $cache_key = 'dps_loyalty_global_metrics';
        
        // Tenta obter do cache (se não estiver desabilitado)
        if ( ! $force_refresh && ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;

        // Total de clientes com pontos
        $clients_with_points = $wpdb->get_var( "
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'dps_loyalty_points' 
            AND meta_value > 0
        " );

        // Total de pontos em circulação
        $total_points = $wpdb->get_var( "
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'dps_loyalty_points'
        " );

        // Indicações do mês
        $referrals_table = $wpdb->prefix . 'dps_referrals';
        $first_day_of_month = gmdate( 'Y-m-01 00:00:00' );
        
        $referrals_this_month = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM {$referrals_table} 
            WHERE created_at >= %s
        ", $first_day_of_month ) );

        // Indicações recompensadas do mês
        $rewarded_this_month = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM {$referrals_table} 
            WHERE status = 'rewarded' 
            AND created_at >= %s
        ", $first_day_of_month ) );

        // Total de créditos em circulação
        $total_credits = $wpdb->get_var( "
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_dps_credit_balance'
        " );

        $metrics = [
            'clients_with_points'  => (int) $clients_with_points,
            'total_points'         => (int) $total_points,
            'referrals_this_month' => (int) $referrals_this_month,
            'rewarded_this_month'  => (int) $rewarded_this_month,
            'total_credits'        => (int) $total_credits,
        ];

        // Armazena no cache por 5 minutos (se não estiver desabilitado)
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $metrics, 5 * MINUTE_IN_SECONDS );
        }

        return $metrics;
    }

    /**
     * Obtém lista de indicações com paginação.
     *
     * @param array $args Argumentos de filtro.
     * @return array Lista de indicações e total.
     */
    public static function get_referrals( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';

        $defaults = [
            'status'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $prepare_args = [];

        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND status = %s';
            $prepare_args[] = $args['status'];
        }

        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $orderby = in_array( $args['orderby'], [ 'created_at', 'id', 'status' ], true ) ? $args['orderby'] : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $prepare_args[] = $args['per_page'];
        $prepare_args[] = $offset;

        $referrals = $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare_args ) );

        // Total count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if ( ! empty( $args['status'] ) ) {
            $total = $wpdb->get_var( $wpdb->prepare( $count_sql, $args['status'] ) );
        } else {
            $total = $wpdb->get_var( $count_sql );
        }

        return [
            'items' => $referrals,
            'total' => (int) $total,
            'pages' => ceil( (int) $total / $args['per_page'] ),
        ];
    }

    /**
     * Exports referrals data to CSV format.
     *
     * @since 1.2.0
     *
     * @param array $args Optional. Filter arguments (same as get_referrals).
     * @return string CSV content.
     */
    public static function export_referrals_csv( $args = [] ) {
        // Get all referrals (no pagination)
        $args['per_page'] = 9999;
        $args['page'] = 1;
        $data = self::get_referrals( $args );
        $referrals = $data['items'];

        // CSV header with BOM for Excel UTF-8 compatibility
        $csv = "\xEF\xBB\xBF";
        
        // Headers
        $headers = [
            __( 'ID', 'dps-loyalty-addon' ),
            __( 'Indicador', 'dps-loyalty-addon' ),
            __( 'Indicado', 'dps-loyalty-addon' ),
            __( 'Código', 'dps-loyalty-addon' ),
            __( 'Data', 'dps-loyalty-addon' ),
            __( 'Status', 'dps-loyalty-addon' ),
            __( 'Recompensa Indicador', 'dps-loyalty-addon' ),
            __( 'Recompensa Indicado', 'dps-loyalty-addon' ),
        ];
        $csv .= implode( ';', $headers ) . "\n";

        // Rows
        foreach ( $referrals as $ref ) {
            $referrer = get_post( $ref->referrer_client_id );
            $referee = get_post( $ref->referee_client_id );
            
            $referrer_reward = self::format_reward_for_export( $ref->reward_type_referrer, $ref->reward_value_referrer );
            $referee_reward = self::format_reward_for_export( $ref->reward_type_referee, $ref->reward_value_referee );

            $row = [
                $ref->id,
                $referrer ? $referrer->post_title : '-',
                $referee ? $referee->post_title : '-',
                $ref->referral_code,
                date_i18n( 'd/m/Y H:i', strtotime( $ref->created_at ) ),
                $ref->status === 'rewarded' ? __( 'Recompensada', 'dps-loyalty-addon' ) : __( 'Pendente', 'dps-loyalty-addon' ),
                $referrer_reward,
                $referee_reward,
            ];
            $csv .= implode( ';', $row ) . "\n";
        }

        return $csv;
    }

    /**
     * Formats reward value for export.
     *
     * @since 1.2.0
     *
     * @param string $type  Reward type.
     * @param mixed  $value Reward value.
     * @return string Formatted reward.
     */
    private static function format_reward_for_export( $type, $value ) {
        if ( empty( $type ) || 'none' === $type ) {
            return '-';
        }

        switch ( $type ) {
            case 'points':
                return sprintf( '%d pts', (int) $value );
            case 'fixed':
                if ( class_exists( 'DPS_Money_Helper' ) ) {
                    return 'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) $value );
                }
                return 'R$ ' . number_format( (int) $value / 100, 2, ',', '.' );
            case 'percent':
                return $value . '%';
            default:
                return '-';
        }
    }
}
