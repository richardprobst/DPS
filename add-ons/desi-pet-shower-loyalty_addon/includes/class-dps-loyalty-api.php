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
        $registration_url = home_url( '/cadastro/' );
        
        // Filtro para customizar URL base de indicação
        $registration_url = apply_filters( 'dps_loyalty_referral_base_url', $registration_url );
        
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
     * Obtém métricas globais de fidelidade.
     *
     * @return array Métricas do programa.
     */
    public static function get_global_metrics() {
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
        $first_day_of_month = date( 'Y-m-01 00:00:00' );
        
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

        return [
            'clients_with_points'  => (int) $clients_with_points,
            'total_points'         => (int) $total_points,
            'referrals_this_month' => (int) $referrals_this_month,
            'rewarded_this_month'  => (int) $rewarded_this_month,
            'total_credits'        => (int) $total_credits,
        ];
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
}
