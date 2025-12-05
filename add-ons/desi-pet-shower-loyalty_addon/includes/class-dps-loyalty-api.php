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

        // Tenta usar página de cadastro configurada ou fallback para home
        // Filtro para customizar URL base de indicação
        $registration_url = apply_filters( 'dps_loyalty_referral_base_url', home_url( '/' ) );
        
        return add_query_arg( 'ref', $code, $registration_url );
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
     * @param int $client_id ID do cliente.
     * @param int $limit     Número máximo de registros.
     * @return array Lista de eventos.
     */
    public static function get_points_history( $client_id, $limit = 10 ) {
        return dps_loyalty_get_logs( $client_id, $limit );
    }

    /**
     * Obtém nível de fidelidade do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Dados do nível atual.
     */
    public static function get_loyalty_tier( $client_id ) {
        $points = self::get_points( $client_id );
        $settings = get_option( 'dps_loyalty_settings', [] );
        
        $tiers = isset( $settings['loyalty_tiers'] ) ? $settings['loyalty_tiers'] : self::get_default_tiers();
        
        $current_tier = 'bronze';
        $current_tier_data = $tiers['bronze'];
        
        foreach ( $tiers as $key => $tier ) {
            if ( $points >= $tier['min_points'] ) {
                $current_tier = $key;
                $current_tier_data = $tier;
            }
        }

        // Encontra próximo nível
        $next_tier = null;
        $next_tier_data = null;
        $tier_keys = array_keys( $tiers );
        $current_index = array_search( $current_tier, $tier_keys, true );
        
        if ( $current_index !== false && isset( $tier_keys[ $current_index + 1 ] ) ) {
            $next_tier = $tier_keys[ $current_index + 1 ];
            $next_tier_data = $tiers[ $next_tier ];
        }

        return [
            'current'     => $current_tier,
            'label'       => $current_tier_data['label'],
            'icon'        => $current_tier_data['icon'],
            'multiplier'  => $current_tier_data['multiplier'],
            'points'      => $points,
            'next_tier'   => $next_tier,
            'next_label'  => $next_tier_data ? $next_tier_data['label'] : null,
            'next_points' => $next_tier_data ? $next_tier_data['min_points'] : null,
            'progress'    => $next_tier_data ? min( 100, round( ( $points / $next_tier_data['min_points'] ) * 100 ) ) : 100,
        ];
    }

    /**
     * Retorna níveis de fidelidade padrão.
     *
     * @return array Configuração de níveis.
     */
    public static function get_default_tiers() {
        return [
            'bronze' => [
                'min_points' => 0,
                'multiplier' => 1.0,
                'label'      => __( 'Bronze', 'dps-loyalty-addon' ),
                'icon'       => '🥉',
            ],
            'prata'  => [
                'min_points' => 500,
                'multiplier' => 1.5,
                'label'      => __( 'Prata', 'dps-loyalty-addon' ),
                'icon'       => '🥈',
            ],
            'ouro'   => [
                'min_points' => 1000,
                'multiplier' => 2.0,
                'label'      => __( 'Ouro', 'dps-loyalty-addon' ),
                'icon'       => '🥇',
            ],
        ];
    }

    /**
     * Calculates the number of points for a given monetary amount.
     *
     * Applies the client's tier multiplier if a client_id is provided.
     * Useful for displaying expected points before completing a transaction.
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
     * Gets clients grouped by loyalty tier.
     *
     * @since 1.2.0
     *
     * @return array Associative array with tier keys and client counts.
     */
    public static function get_clients_by_tier() {
        global $wpdb;
        $tiers = self::get_default_tiers();
        
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
        foreach ( array_keys( $tiers ) as $tier_key ) {
            $counts[ $tier_key ] = 0;
        }

        // Count clients per tier
        foreach ( $results as $row ) {
            $points = (int) $row->points;
            $client_tier = 'bronze';
            
            foreach ( $tiers as $key => $tier ) {
                if ( $points >= $tier['min_points'] ) {
                    $client_tier = $key;
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
