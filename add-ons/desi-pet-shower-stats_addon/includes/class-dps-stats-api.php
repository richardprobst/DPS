<?php
/**
 * API pública do Stats Add-on
 *
 * Centraliza toda a lógica de estatísticas e métricas para reutilização
 * por outros add-ons e facilitar manutenção.
 *
 * @package DPS_Stats_Addon
 * @since 1.1.0
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Stats_API
 *
 * Fornece métodos públicos para:
 * - Obter contagem de atendimentos
 * - Calcular receita e despesas
 * - Listar pets inativos
 * - Obter serviços mais solicitados
 * - Calcular métricas de comparativo de períodos
 * - Calcular ticket médio e taxa de retenção
 *
 * @since 1.1.0
 */
class DPS_Stats_API {

    /**
     * Verifica se a tabela dps_transacoes existe.
     *
     * @return bool True se a tabela existe, false caso contrário.
     *
     * @since 1.2.0
     */
    private static function table_dps_transacoes_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like( $table_name )
        ) );
        return $table_exists === $table_name;
    }

    /**
     * F2.3: Obtém valor do cache (object cache ou transient).
     *
     * @param string $key Chave do cache.
     *
     * @return mixed|false Valor do cache ou false se não encontrado.
     *
     * @since 1.3.0
     */
    private static function cache_get( $key ) {
        if ( wp_using_ext_object_cache() ) {
            return wp_cache_get( $key, 'dps_stats' );
        }
        return get_transient( $key );
    }

    /**
     * F2.3: Armazena valor no cache (object cache ou transient).
     *
     * @param string $key   Chave do cache.
     * @param mixed  $value Valor a armazenar.
     * @param int    $ttl   Time to live em segundos.
     *
     * @return bool True se armazenado com sucesso.
     *
     * @since 1.3.0
     */
    private static function cache_set( $key, $value, $ttl ) {
        if ( wp_using_ext_object_cache() ) {
            return wp_cache_set( $key, $value, 'dps_stats', $ttl );
        }
        return set_transient( $key, $value, $ttl );
    }

    /**
     * F2.3: Obtém versão do cache para invalidação.
     *
     * @return int Versão atual do cache.
     *
     * @since 1.3.0
     */
    private static function get_cache_version() {
        $version = get_option( 'dps_stats_cache_version', 1 );
        return (int) $version;
    }

    /**
     * F2.3: Incrementa versão do cache (invalida todo cache).
     *
     * @since 1.3.0
     */
    public static function bump_cache_version() {
        $current = self::get_cache_version();
        update_option( 'dps_stats_cache_version', $current + 1, false );
    }

    /**
     * Obtém contagem de atendimentos no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @param string $status     Status do agendamento (opcional).
     *
     * @return int Número de atendimentos.
     *
     * @since 1.1.0
     */
    public static function get_appointments_count( $start_date, $end_date, $status = '' ) {
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );
        
        $cache_key = dps_stats_build_cache_key( 'dps_stats_appts_count', $start_date, $end_date );
        if ( $status ) {
            $cache_key .= '_' . sanitize_key( $status );
        }
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return (int) $cached;
            }
        }

        $meta_query = [
            'relation' => 'AND',
            [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'appointment_date', 'value' => $end_date,   'compare' => '<=', 'type' => 'DATE' ],
        ];

        if ( $status ) {
            $meta_query[] = [ 'key' => 'appointment_status', 'value' => $status, 'compare' => '=' ];
        }

        $count = (new WP_Query( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
        ] ))->found_posts;

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $count, HOUR_IN_SECONDS );
        }

        return $count;
    }

    /**
     * Obtém total de receitas pagas no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float Total de receitas.
     *
     * @since 1.1.0
     */
    public static function get_revenue_total( $start_date, $end_date ) {
        $totals = self::get_financial_totals( $start_date, $end_date );
        return $totals['revenue'];
    }

    /**
     * Obtém total de despesas pagas no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float Total de despesas.
     *
     * @since 1.1.0
     */
    public static function get_expenses_total( $start_date, $end_date ) {
        $totals = self::get_financial_totals( $start_date, $end_date );
        return $totals['expenses'];
    }

    /**
     * Obtém totais financeiros do período (receita e despesas).
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return array [ 'revenue' => float, 'expenses' => float ]
     *
     * @since 1.1.0
     */
    public static function get_financial_totals( $start_date, $end_date ) {
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_financial', $start_date, $end_date );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Tenta usar Finance API se disponível
        if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'get_period_totals' ) ) {
            $totals = DPS_Finance_API::get_period_totals( $start_date, $end_date );
            $result = [
                'revenue'  => isset( $totals['paid_revenue'] ) ? (float) $totals['paid_revenue'] : 0,
                'expenses' => isset( $totals['paid_expenses'] ) ? (float) $totals['paid_expenses'] : 0,
            ];
        } else {
            // Fallback para SQL direto - verifica existência da tabela
            if ( ! self::table_dps_transacoes_exists() ) {
                $result = [
                    'revenue'  => 0,
                    'expenses' => 0,
                    'error'    => 'finance_not_active',
                ];
            } else {
                global $wpdb;
                $table   = $wpdb->prefix . 'dps_transacoes';
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT tipo, SUM(valor) AS total FROM {$table} WHERE data >= %s AND data <= %s AND status = 'pago' GROUP BY tipo",
                        $start_date,
                        $end_date
                    ),
                    OBJECT_K
                );

                $result = [
                    'revenue'  => isset( $results['receita'] ) ? (float) $results['receita']->total : 0,
                    'expenses' => isset( $results['despesa'] ) ? (float) $results['despesa']->total : 0,
                ];
            }
        }

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Obtém pets inativos (sem atendimento há X dias).
     *
     * @param int $days Número de dias de inatividade (padrão: 30).
     *
     * @return array Lista de pets inativos com dados do cliente.
     *
     * @since 1.1.0
     */
    public static function get_inactive_pets( $days = 30 ) {
        $days = absint( $days );
        if ( $days < 1 ) {
            $days = 30;
        }

        $cutoff_date = date( 'Y-m-d', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
        $cache_key   = dps_stats_build_cache_key( 'dps_stats_inactive_pets', $cutoff_date );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        global $wpdb;

        // Limite configurável via filtro (padrão: 500 pets)
        $pets_limit = apply_filters( 'dps_stats_inactive_pets_limit', 500 );

        // Obter todos os pets ativos
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => $pets_limit,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );

        if ( empty( $pets ) ) {
            if ( ! dps_is_cache_disabled() ) {
                set_transient( $cache_key, [], DAY_IN_SECONDS );
            }
            return [];
        }

        // Buscar último atendimento de cada pet em uma query otimizada
        // Usa $wpdb->posts e $wpdb->postmeta que são seguros (propriedades do wpdb)
        $placeholders = implode( ',', array_fill( 0, count( $pets ), '%d' ) );
        $sql = $wpdb->prepare(
            "SELECT pm.meta_value AS pet_id, MAX(pm2.meta_value) AS last_date
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = 'appointment_date'
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'dps_agendamento' AND p.post_status = 'publish'
             WHERE pm.meta_key = 'appointment_pet_id' AND pm.meta_value IN ({$placeholders})
             GROUP BY pm.meta_value",
            ...$pets
        );

        $last_appointments = $wpdb->get_results( $sql, OBJECT_K );

        $inactive_pets = [];
        $cutoff_ts = strtotime( $cutoff_date );

        foreach ( $pets as $pet_id ) {
            $last_date = isset( $last_appointments[ $pet_id ] ) ? $last_appointments[ $pet_id ]->last_date : '';
            
            if ( ! $last_date || strtotime( $last_date ) < $cutoff_ts ) {
                $pet = get_post( $pet_id );
                if ( ! $pet ) {
                    continue;
                }

                $owner_id = get_post_meta( $pet_id, 'owner_id', true );
                $client = get_post( $owner_id );

                if ( $client ) {
                    $inactive_pets[] = [
                        'pet'       => $pet,
                        'client'    => $client,
                        'last_date' => $last_date,
                    ];
                }
            }
        }

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $inactive_pets, DAY_IN_SECONDS );
        }

        return $inactive_pets;
    }

    /**
     * Obtém serviços mais solicitados no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @param int    $limit      Limite de serviços (padrão: 5).
     *
     * @return array Lista de serviços com contagem.
     *
     * @since 1.1.0
     */
    public static function get_top_services( $start_date, $end_date, $limit = 5 ) {
        global $wpdb;
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );
        $limit      = absint( $limit );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_top_services', $start_date, $end_date ) . '_' . $limit;
        
        // F2.3: Usa cache layer (object cache ou transient)
        if ( ! dps_is_cache_disabled() ) {
            $cached = self::cache_get( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // F2.1: Query SQL com GROUP BY para performance
        // Meta appointment_services pode ser serializado (array), então precisamos JOIN múltiplo
        // ou processar em PHP. Como appointment_services é array, mantemos loop mas otimizado.
        
        // Query 1: Buscar todos os appointments do período (apenas IDs e meta de serviços)
        $sql = $wpdb->prepare(
            "SELECT p.ID, pm_services.meta_value as services
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'appointment_date'
             LEFT JOIN {$wpdb->postmeta} pm_services ON p.ID = pm_services.post_id AND pm_services.meta_key = 'appointment_services'
             WHERE p.post_type = 'dps_agendamento'
               AND p.post_status = 'publish'
               AND pm_date.meta_value >= %s
               AND pm_date.meta_value <= %s",
            $start_date,
            $end_date
        );
        
        $appointments = $wpdb->get_results( $sql );
        
        $service_counts = [];
        foreach ( $appointments as $appt ) {
            if ( empty( $appt->services ) ) {
                continue;
            }
            
            // Deserializar array de serviços
            $service_ids = maybe_unserialize( $appt->services );
            if ( is_array( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $service_counts[ $sid ] = ( $service_counts[ $sid ] ?? 0 ) + 1;
                }
            }
        }

        arsort( $service_counts );
        $top_services = array_slice( $service_counts, 0, $limit, true );

        $result = [];
        $total = array_sum( $service_counts );

        foreach ( $top_services as $service_id => $count ) {
            $result[] = [
                'id'         => $service_id,
                'title'      => get_the_title( $service_id ),
                'count'      => $count,
                'percentage' => $total > 0 ? round( ( $count / $total ) * 100 ) : 0,
            ];
        }

        // F2.3: Armazena cache usando cache layer
        if ( ! dps_is_cache_disabled() ) {
            self::cache_set( $cache_key, $result, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Calcula comparativo entre período atual e período anterior.
     *
     * @param string $start_date Data inicial do período atual (Y-m-d).
     * @param string $end_date   Data final do período atual (Y-m-d).
     *
     * @return array Comparativo de métricas.
     *
     * @since 1.1.0
     */
    public static function get_period_comparison( $start_date, $end_date ) {
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_comparison', $start_date, $end_date );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Calcular período anterior com mesma duração
        $start_ts = strtotime( $start_date );
        $end_ts   = strtotime( $end_date );
        $duration = $end_ts - $start_ts;

        $prev_end   = date( 'Y-m-d', $start_ts - DAY_IN_SECONDS );
        $prev_start = date( 'Y-m-d', $start_ts - $duration - DAY_IN_SECONDS );

        // Métricas período atual
        $current_appointments = self::get_appointments_count( $start_date, $end_date );
        $current_financials   = self::get_financial_totals( $start_date, $end_date );
        $current_revenue      = $current_financials['revenue'];
        $current_expenses     = $current_financials['expenses'];

        // Métricas período anterior
        $prev_appointments = self::get_appointments_count( $prev_start, $prev_end );
        $prev_financials   = self::get_financial_totals( $prev_start, $prev_end );
        $prev_revenue      = $prev_financials['revenue'];
        $prev_expenses     = $prev_financials['expenses'];

        // Calcular variações
        $result = [
            'current' => [
                'appointments' => $current_appointments,
                'revenue'      => $current_revenue,
                'expenses'     => $current_expenses,
                'profit'       => $current_revenue - $current_expenses,
                'ticket_avg'   => $current_appointments > 0 ? $current_revenue / $current_appointments : 0,
            ],
            'previous' => [
                'appointments' => $prev_appointments,
                'revenue'      => $prev_revenue,
                'expenses'     => $prev_expenses,
                'profit'       => $prev_revenue - $prev_expenses,
                'ticket_avg'   => $prev_appointments > 0 ? $prev_revenue / $prev_appointments : 0,
                'start_date'   => $prev_start,
                'end_date'     => $prev_end,
            ],
            'variation' => [
                'appointments' => self::calculate_variation( $prev_appointments, $current_appointments ),
                'revenue'      => self::calculate_variation( $prev_revenue, $current_revenue ),
                'expenses'     => self::calculate_variation( $prev_expenses, $current_expenses ),
                'profit'       => self::calculate_variation( $prev_revenue - $prev_expenses, $current_revenue - $current_expenses ),
                'ticket_avg'   => self::calculate_variation( 
                    $prev_appointments > 0 ? $prev_revenue / $prev_appointments : 0,
                    $current_appointments > 0 ? $current_revenue / $current_appointments : 0
                ),
            ],
        ];

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Calcula variação percentual entre dois valores.
     *
     * @param float $old_value Valor anterior.
     * @param float $new_value Valor atual.
     *
     * @return float Variação percentual.
     */
    private static function calculate_variation( $old_value, $new_value ) {
        if ( $old_value == 0 ) {
            return $new_value > 0 ? 100 : 0;
        }
        return round( ( ( $new_value - $old_value ) / abs( $old_value ) ) * 100, 1 );
    }

    /**
     * Calcula ticket médio do período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float Ticket médio.
     *
     * @since 1.1.0
     */
    public static function get_ticket_average( $start_date, $end_date ) {
        $appointments = self::get_appointments_count( $start_date, $end_date );
        $revenue      = self::get_revenue_total( $start_date, $end_date );

        return $appointments > 0 ? round( $revenue / $appointments, 2 ) : 0;
    }

    /**
     * Calcula taxa de cancelamento do período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float Taxa de cancelamento (%).
     *
     * @since 1.1.0
     */
    public static function get_cancellation_rate( $start_date, $end_date ) {
        $total     = self::get_appointments_count( $start_date, $end_date );
        $cancelled = self::get_appointments_count( $start_date, $end_date, 'cancelado' );

        if ( $total == 0 ) {
            return 0;
        }

        return round( ( $cancelled / $total ) * 100, 1 );
    }

    /**
     * Obtém novos clientes cadastrados no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return int Número de novos clientes.
     *
     * @since 1.1.0
     */
    public static function get_new_clients_count( $start_date, $end_date ) {
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_new_clients', $start_date, $end_date );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return (int) $cached;
            }
        }

        $count = (new WP_Query( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'date_query'     => [
                [
                    'after'     => $start_date,
                    'before'    => $end_date . ' 23:59:59',
                    'inclusive' => true,
                ],
            ],
            'fields' => 'ids',
        ] ))->found_posts;

        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $count, HOUR_IN_SECONDS );
        }

        return $count;
    }

    /**
     * Obtém distribuição de espécies no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return array Contagem por espécie.
     *
     * @since 1.1.0
     */
    public static function get_species_distribution( $start_date, $end_date ) {
        global $wpdb;
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_species', $start_date, $end_date );
        
        // F2.3: Usa cache layer (object cache ou transient)
        if ( ! dps_is_cache_disabled() ) {
            $cached = self::cache_get( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // F2.1: Query SQL com GROUP BY para performance
        $sql = $wpdb->prepare(
            "SELECT pm_species.meta_value as species, COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'appointment_date'
             INNER JOIN {$wpdb->postmeta} pm_pet ON p.ID = pm_pet.post_id AND pm_pet.meta_key = 'appointment_pet_id'
             INNER JOIN {$wpdb->postmeta} pm_species ON pm_pet.meta_value = pm_species.post_id AND pm_species.meta_key = 'pet_species'
             WHERE p.post_type = 'dps_agendamento'
               AND p.post_status = 'publish'
               AND pm_date.meta_value >= %s
               AND pm_date.meta_value <= %s
             GROUP BY pm_species.meta_value
             ORDER BY count DESC",
            $start_date,
            $end_date
        );
        
        $species_data = $wpdb->get_results( $sql );
        
        // Mapear espécies para labels traduzidos
        $species_counts = [];
        foreach ( $species_data as $row ) {
            $species = $row->species;
            if ( $species === 'cao' ) {
                $species_label = __( 'Cachorro', 'dps-stats-addon' );
            } elseif ( $species === 'gato' ) {
                $species_label = __( 'Gato', 'dps-stats-addon' );
            } else {
                $species_label = __( 'Outro', 'dps-stats-addon' );
            }
            $species_counts[ $species_label ] = ( $species_counts[ $species_label ] ?? 0 ) + (int) $row->count;
        }

        arsort( $species_counts );
        $total = array_sum( $species_counts );

        $result = [];
        foreach ( $species_counts as $species => $count ) {
            $result[] = [
                'species'    => $species,
                'count'      => $count,
                'percentage' => $total > 0 ? round( ( $count / $total ) * 100 ) : 0,
            ];
        }

        // F2.3: Armazena cache usando cache layer
        if ( ! dps_is_cache_disabled() ) {
            self::cache_set( $cache_key, $result, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Obtém top raças atendidas no período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @param int    $limit      Limite de raças (padrão: 5).
     *
     * @return array Lista de raças com contagem.
     *
     * @since 1.1.0
     */
    public static function get_top_breeds( $start_date, $end_date, $limit = 5 ) {
        global $wpdb;
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );
        $limit      = absint( $limit );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_top_breeds', $start_date, $end_date ) . '_' . $limit;
        
        // F2.3: Usa cache layer (object cache ou transient)
        if ( ! dps_is_cache_disabled() ) {
            $cached = self::cache_get( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // F2.1: Query SQL com GROUP BY para performance
        $sql = $wpdb->prepare(
            "SELECT pm_breed.meta_value as breed, COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'appointment_date'
             INNER JOIN {$wpdb->postmeta} pm_pet ON p.ID = pm_pet.post_id AND pm_pet.meta_key = 'appointment_pet_id'
             INNER JOIN {$wpdb->postmeta} pm_breed ON pm_pet.meta_value = pm_breed.post_id AND pm_breed.meta_key = 'pet_breed'
             WHERE p.post_type = 'dps_agendamento'
               AND p.post_status = 'publish'
               AND pm_date.meta_value >= %s
               AND pm_date.meta_value <= %s
               AND pm_breed.meta_value != ''
             GROUP BY pm_breed.meta_value
             ORDER BY count DESC
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        );
        
        $breed_data = $wpdb->get_results( $sql );
        
        // Calcular total para percentuais
        $total = 0;
        foreach ( $breed_data as $row ) {
            $total += (int) $row->count;
        }

        $result = [];
        foreach ( $breed_data as $row ) {
            $result[] = [
                'breed'      => $row->breed,
                'count'      => (int) $row->count,
                'percentage' => $total > 0 ? round( ( (int) $row->count / $total ) * 100 ) : 0,
            ];
        }

        // F2.3: Armazena cache usando cache layer
        if ( ! dps_is_cache_disabled() ) {
            self::cache_set( $cache_key, $result, HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Gera CSV com dados dos pets inativos.
     *
     * @param int $days Dias de inatividade.
     *
     * @return string Conteúdo CSV.
     *
     * @since 1.1.0
     */
    public static function export_inactive_pets_csv( $days = 30 ) {
        $inactive_pets = self::get_inactive_pets( $days );

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
        $csv .= implode( ';', [
            __( 'Pet', 'dps-stats-addon' ),
            __( 'Cliente', 'dps-stats-addon' ),
            __( 'Último Atendimento', 'dps-stats-addon' ),
            __( 'Telefone', 'dps-stats-addon' ),
        ] ) . "\n";

        foreach ( $inactive_pets as $item ) {
            $pet       = $item['pet'];
            $client    = $item['client'];
            $last_date = $item['last_date'] ? date_i18n( 'd/m/Y', strtotime( $item['last_date'] ) ) : __( 'Nunca', 'dps-stats-addon' );
            $phone     = get_post_meta( $client->ID, 'client_phone', true );

            $csv .= implode( ';', [
                '"' . str_replace( '"', '""', $pet->post_title ) . '"',
                '"' . str_replace( '"', '""', $client->post_title ) . '"',
                $last_date,
                $phone ?: '-',
            ] ) . "\n";
        }

        return $csv;
    }

    /**
     * Gera CSV com métricas do período.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return string Conteúdo CSV.
     *
     * @since 1.1.0
     */
    public static function export_metrics_csv( $start_date, $end_date ) {
        $comparison = self::get_period_comparison( $start_date, $end_date );
        $species    = self::get_species_distribution( $start_date, $end_date );
        $services   = self::get_top_services( $start_date, $end_date, 10 );

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
        
        // Header
        $csv .= sprintf( __( 'Relatório de Estatísticas - %s a %s', 'dps-stats-addon' ), 
            date_i18n( 'd/m/Y', strtotime( $start_date ) ),
            date_i18n( 'd/m/Y', strtotime( $end_date ) )
        ) . "\n\n";

        // Métricas principais
        $csv .= __( 'MÉTRICAS PRINCIPAIS', 'dps-stats-addon' ) . "\n";
        $csv .= __( 'Atendimentos', 'dps-stats-addon' ) . ';' . $comparison['current']['appointments'] . ';' . 
                sprintf( '%+.1f%%', $comparison['variation']['appointments'] ) . "\n";
        $csv .= __( 'Receita', 'dps-stats-addon' ) . ';R$ ' . number_format( $comparison['current']['revenue'], 2, ',', '.' ) . ';' .
                sprintf( '%+.1f%%', $comparison['variation']['revenue'] ) . "\n";
        $csv .= __( 'Despesas', 'dps-stats-addon' ) . ';R$ ' . number_format( $comparison['current']['expenses'], 2, ',', '.' ) . ';' .
                sprintf( '%+.1f%%', $comparison['variation']['expenses'] ) . "\n";
        $csv .= __( 'Lucro', 'dps-stats-addon' ) . ';R$ ' . number_format( $comparison['current']['profit'], 2, ',', '.' ) . ';' .
                sprintf( '%+.1f%%', $comparison['variation']['profit'] ) . "\n";
        $csv .= __( 'Ticket Médio', 'dps-stats-addon' ) . ';R$ ' . number_format( $comparison['current']['ticket_avg'], 2, ',', '.' ) . ';' .
                sprintf( '%+.1f%%', $comparison['variation']['ticket_avg'] ) . "\n\n";

        // Distribuição de espécies
        $csv .= __( 'DISTRIBUIÇÃO DE ESPÉCIES', 'dps-stats-addon' ) . "\n";
        foreach ( $species as $s ) {
            $csv .= $s['species'] . ';' . $s['count'] . ';' . $s['percentage'] . "%\n";
        }
        $csv .= "\n";

        // Serviços
        $csv .= __( 'SERVIÇOS MAIS SOLICITADOS', 'dps-stats-addon' ) . "\n";
        foreach ( $services as $svc ) {
            $csv .= $svc['title'] . ';' . $svc['count'] . ';' . $svc['percentage'] . "%\n";
        }

        return $csv;
    }
}
