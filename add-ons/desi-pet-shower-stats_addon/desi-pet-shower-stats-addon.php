<?php
/**
 * Plugin Name:       Desi Pet Shower – Estatísticas Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin base do Desi Pet Shower que adiciona uma aba de estatísticas. Exibe clientes/pets sem atendimento nos últimos 30 dias e outras métricas.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-stats-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carrega o text domain do Stats Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_stats_load_textdomain() {
    load_plugin_textdomain( 'dps-stats-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_stats_load_textdomain', 1 );

if ( ! function_exists( 'dps_stats_build_cache_key' ) ) {
    /**
     * Monta uma chave de cache única para transients das estatísticas.
     *
     * @param string $prefix     Prefixo do transient.
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return string
     */
    function dps_stats_build_cache_key( $prefix, $start_date, $end_date = '' ) {
        $start_key = preg_replace( '/[^0-9]/', '', $start_date );
        $end_key   = $end_date ? preg_replace( '/[^0-9]/', '', $end_date ) : '';

        if ( $end_key ) {
            return sprintf( '%s_%s_%s', $prefix, $start_key, $end_key );
        }

        return sprintf( '%s_%s', $prefix, $start_key );
    }
}

if ( ! function_exists( 'dps_get_total_revenue' ) ) {
    /**
     * Calcula o total de receitas pagas no intervalo informado com cache via transient.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float
     */
    function dps_get_total_revenue( $start_date, $end_date ) {
        global $wpdb;

        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );

        $cache_key = dps_stats_build_cache_key( 'dps_stats_total_revenue', $start_date, $end_date );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return (float) $cached;
        }

        $table          = $wpdb->prefix . 'dps_transacoes';
        $total_revenue  = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM {$table} WHERE data >= %s AND data <= %s AND status = 'pago' AND tipo = 'receita'",
                $start_date,
                $end_date
            )
        );

        set_transient( $cache_key, $total_revenue, HOUR_IN_SECONDS );

        return $total_revenue;
    }
}

if ( ! function_exists( 'dps_stats_clear_cache' ) ) {
    /**
     * Remove os transients relacionados às estatísticas do add-on.
     */
    function dps_stats_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para limpar o cache.', 'dps-stats-addon' ) );
        }

        check_admin_referer( 'dps_clear_stats_cache', 'dps_clear_stats_cache_nonce' );

        global $wpdb;

        $transient_prefix      = $wpdb->esc_like( '_transient_dps_stats_' ) . '%';
        $transient_timeout_pre = $wpdb->esc_like( '_transient_timeout_dps_stats_' ) . '%';

        // Remove transients específicos do add-on de estatísticas.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transient_prefix,
                $transient_timeout_pre
            )
        );

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
        exit;
    }
}

add_action( 'admin_post_dps_clear_stats_cache', 'dps_stats_clear_cache' );

class DPS_Stats_Addon {
    public function __construct() {
        // Registrar abas e seções no plugin base
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_stats_tab' ], 20, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_stats_section' ], 20, 1 );
    }

    /**
     * Adiciona a aba de Estatísticas na navegação do plugin base.
     *
     * @param bool $visitor_only Se o modo visitante está ativo; nesse caso, não mostra a aba.
     */
    public function add_stats_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="estatisticas">' . esc_html__( 'Estatísticas', 'dps-stats-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de estatísticas ao plugin base.
     *
     * @param bool $visitor_only Se o modo visitante está ativo; nesse caso, não mostra a seção.
     */
    public function add_stats_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo $this->section_stats();
    }

    /**
     * Renderiza a seção de estatísticas.
     *
     * Exibe clientes e pets que não realizaram atendimento nos últimos 30 dias e outras métricas.
     */
    private function section_stats() {
        // Intervalo selecionado ou padrão (últimos 30 dias)
        $today = current_time( 'timestamp' );
        $start_date = isset( $_GET['stats_start'] ) ? sanitize_text_field( $_GET['stats_start'] ) : '';
        $end_date   = isset( $_GET['stats_end'] ) ? sanitize_text_field( $_GET['stats_end'] ) : '';
        if ( ! $start_date ) {
            // padrão: 30 dias atrás
            $start_date = date( 'Y-m-d', $today - ( 30 * DAY_IN_SECONDS ) );
        }
        if ( ! $end_date ) {
            $end_date = date( 'Y-m-d', $today );
        }
        $cutoff_ts  = strtotime( $start_date );
        $end_ts     = strtotime( $end_date . ' 23:59:59' );
        $cutoff_str = $start_date;
        $end_str    = $end_date;
        $inactive_data     = $this->get_inactive_entities( $cutoff_ts );
        $inactive_clients  = $inactive_data['inactive_clients'];
        $inactive_pets     = $inactive_data['inactive_pets'];
        $appointments_data = $this->get_recent_appointments_stats( $cutoff_str, $end_str );
        $total_recent_appts = $appointments_data['total'];
        $service_counts     = $appointments_data['service_counts'];
        arsort( $service_counts );
        $top_services        = array_slice( $service_counts, 0, 5, true );
        $total_service_uses  = array_sum( $service_counts );
        $species_counts      = $appointments_data['species_counts'];
        arsort( $species_counts );
        $breed_counts        = $appointments_data['breed_counts'];
        arsort( $breed_counts );
        $top_breeds          = array_slice( $breed_counts, 0, 5, true );
        $client_counts       = $appointments_data['client_counts'];
        $avg_baths           = 0;
        if ( ! empty( $client_counts ) ) {
            $avg_baths = array_sum( $client_counts ) / count( $client_counts );
        }
        // Receita nos últimos 30 dias
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Busca transações no intervalo e soma apenas receitas pagas
        $financial_totals = $this->get_financial_totals( $cutoff_str, $end_str );
        $total_revenue    = $financial_totals['revenue'];
        $total_expenses   = $financial_totals['expenses'];
        $net_profit = $total_revenue - $total_expenses;

        // Estatísticas de assinaturas
        $subscriptions = get_posts( [
            'post_type'      => 'dps_subscription',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );
        $subs_total        = count( $subscriptions );
        $subs_paid_count   = 0;
        $subs_pending_count= 0;
        foreach ( $subscriptions as $sub ) {
            $pstatus = get_post_meta( $sub->ID, 'subscription_payment_status', true );
            if ( 'pago' === $pstatus ) {
                $subs_paid_count++;
            } else {
                $subs_pending_count++;
            }
        }
        // Receita de assinaturas nos últimos 30 dias (somente pagamentos efetivos)
        $subs_rev_30 = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM $table WHERE plano_id IS NOT NULL AND data >= %s AND data <= %s AND status = 'pago'", $cutoff_str, $end_str ) );
        if ( ! $subs_rev_30 ) {
            $subs_rev_30 = 0;
        }
        // Valor em aberto das assinaturas (não pagas)
        $subs_open = $wpdb->get_var( "SELECT SUM(valor) FROM $table WHERE plano_id IS NOT NULL AND status != 'pago'" );
        if ( ! $subs_open ) {
            $subs_open = 0;
        }
        // Renderiza HTML
        ob_start();
        echo '<div class="dps-section" id="dps-section-estatisticas">';
        echo '<h3>' . esc_html__( 'Estatísticas de Atendimentos', 'dps-stats-addon' ) . '</h3>';
        // Formulário de intervalo de datas
        echo '<form method="get" class="dps-stats-date-filter" style="margin-bottom:15px;">';
        // Preserva parâmetros existentes (como tab)
        foreach ( $_GET as $k => $v ) {
            if ( in_array( $k, [ 'stats_start', 'stats_end' ], true ) ) {
                continue;
            }
            echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
        }
        echo '<label>' . esc_html__( 'De', 'dps-stats-addon' ) . ' <input type="date" name="stats_start" value="' . esc_attr( $start_date ) . '"></label> ';
        echo '<label>' . esc_html__( 'Até', 'dps-stats-addon' ) . ' <input type="date" name="stats_end" value="' . esc_attr( $end_date ) . '"></label> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Aplicar intervalo', 'dps-stats-addon' ) . '</button>';
        echo '</form>';
        // Botão para limpar cache das estatísticas
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:15px;">';
        wp_nonce_field( 'dps_clear_stats_cache', 'dps_clear_stats_cache_nonce' );
        echo '<input type="hidden" name="action" value="dps_clear_stats_cache">';
        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Limpar cache de estatísticas', 'dps-stats-addon' ) . '</button>';
        echo '</form>';
        // Total
        echo '<p><strong>' . sprintf( esc_html__( 'Total de atendimentos entre %s e %s:', 'dps-stats-addon' ), date_i18n( 'd-m-Y', strtotime( $start_date ) ), date_i18n( 'd-m-Y', strtotime( $end_date ) ) ) . '</strong> ' . esc_html( $total_recent_appts ) . '</p>';
        // Receita, despesas e lucro
        echo '<p><strong>' . sprintf( esc_html__( 'Receita entre %s e %s:', 'dps-stats-addon' ), date_i18n( 'd-m-Y', strtotime( $start_date ) ), date_i18n( 'd-m-Y', strtotime( $end_date ) ) ) . '</strong> R$ ' . esc_html( number_format( $total_revenue, 2, ',', '.' ) ) . '</p>';
        echo '<p><strong>' . sprintf( esc_html__( 'Despesas entre %s e %s:', 'dps-stats-addon' ), date_i18n( 'd-m-Y', strtotime( $start_date ) ), date_i18n( 'd-m-Y', strtotime( $end_date ) ) ) . '</strong> R$ ' . esc_html( number_format( $total_expenses, 2, ',', '.' ) ) . '</p>';
        echo '<p><strong>' . sprintf( esc_html__( 'Lucro líquido entre %s e %s:', 'dps-stats-addon' ), date_i18n( 'd-m-Y', strtotime( $start_date ) ), date_i18n( 'd-m-Y', strtotime( $end_date ) ) ) . '</strong> R$ ' . esc_html( number_format( $net_profit, 2, ',', '.' ) ) . '</p>';

        // Estatísticas de assinaturas
        echo '<h4>' . esc_html__( 'Assinaturas', 'dps-stats-addon' ) . '</h4>';
        echo '<p><strong>' . esc_html__( 'Total de assinaturas ativas:', 'dps-stats-addon' ) . '</strong> ' . esc_html( $subs_paid_count ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Total de assinaturas pendentes:', 'dps-stats-addon' ) . '</strong> ' . esc_html( $subs_pending_count ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Receita de assinaturas (últimos 30 dias):', 'dps-stats-addon' ) . '</strong> R$ ' . esc_html( number_format( (float) $subs_rev_30, 2, ',', '.' ) ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Valor em aberto de assinaturas:', 'dps-stats-addon' ) . '</strong> R$ ' . esc_html( number_format( (float) $subs_open, 2, ',', '.' ) ) . '</p>';
        // Serviços mais requisitados
        echo '<h4>' . esc_html__( 'Serviços mais solicitados (período selecionado)', 'dps-stats-addon' ) . '</h4>';
        if ( ! empty( $top_services ) ) {
            echo '<ul>';
            foreach ( $top_services as $sid => $count ) {
                $srv_title = get_the_title( $sid );
                $percentage = 0;
                if ( $total_service_uses > 0 ) {
                    $percentage = round( ( $count / $total_service_uses ) * 100 );
                }
                echo '<li>' . esc_html( $srv_title ) . ': ' . esc_html( $count ) . ' (' . esc_html( $percentage ) . '%)</li>';
            }
            echo '</ul>';

            // Adiciona gráfico de barras para visualizar os serviços mais solicitados usando Chart.js.
            // Prepara rótulos e valores para o gráfico. Utilizamos wp_json_encode para gerar JSON seguro.
            $labels_for_chart = [];
            $counts_for_chart = [];
            foreach ( $top_services as $svc_id => $svc_count ) {
                $labels_for_chart[] = get_the_title( $svc_id );
                $counts_for_chart[] = (int) $svc_count;
            }
            $labels_json = wp_json_encode( $labels_for_chart );
            $counts_json = wp_json_encode( $counts_for_chart );
            echo '<div style="max-width:600px;margin-top:15px;"><canvas id="dps-stats-services-chart"></canvas></div>';
            // Carrega Chart.js de um CDN. Usamos a versão UMD para compatibilidade.
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
            // Inicializa o gráfico após o carregamento do DOM. A legenda é ocultada pois a informação já consta no título da seção.
            echo '<script>document.addEventListener("DOMContentLoaded", function(){ var ctx = document.getElementById("dps-stats-services-chart").getContext("2d"); new Chart(ctx, { type: "bar", data: { labels: ' . $labels_json . ', datasets: [{ label: "' . esc_js( __( 'Serviços solicitados', 'dps-stats-addon' ) ) . '", data: ' . $counts_json . ', backgroundColor: ["rgba(54, 162, 235, 0.6)","rgba(255, 99, 132, 0.6)","rgba(255, 206, 86, 0.6)","rgba(75, 192, 192, 0.6)","rgba(153, 102, 255, 0.6)","rgba(255, 159, 64, 0.6)","rgba(199, 199, 199, 0.6)","rgba(83, 102, 255, 0.6)","rgba(255, 99, 255, 0.6)"], borderWidth: 1 }]}, options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } } }); });</script>';
        } else {
            echo '<p>' . esc_html__( 'Nenhum serviço registrado nas últimas 4 semanas.', 'dps-stats-addon' ) . '</p>';
        }
        // Clientes sem atendimento há mais de 30 dias
        // Lista removida para evitar repetição com os pets inativos. Agora o foco é apenas nos pets
        // e seus respectivos tutores. Para voltar a exibir a lista de clientes inativos, descomente
        // o bloco abaixo.
        // Pets inativos
        echo '<h4>' . esc_html__( 'Pets sem atendimento há mais de 30 dias', 'dps-stats-addon' ) . '</h4>';
        if ( ! empty( $inactive_pets ) ) {
            echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Pet', 'dps-stats-addon' ) . '</th><th>' . esc_html__( 'Cliente', 'dps-stats-addon' ) . '</th><th>' . esc_html__( 'Último atendimento', 'dps-stats-addon' ) . '</th><th>' . esc_html__( 'Contato', 'dps-stats-addon' ) . '</th></tr></thead><tbody>';
            foreach ( $inactive_pets as $item ) {
                $pet      = $item['pet'];
                $client   = $item['client'];
                $last_pet = $item['last_date'];
                $last_fmt = $last_pet ? date_i18n( 'd-m-Y', strtotime( $last_pet ) ) : __( 'Nunca', 'dps-stats-addon' );
                // Recupera telefone do cliente e gera link de WhatsApp
                $phone_raw = get_post_meta( $client->ID, 'client_phone', true );
                $whats_link = '';
                if ( $phone_raw ) {
                    // Remove caracteres não numéricos
                    $number = preg_replace( '/\D+/', '', $phone_raw );
                    // Prefixa com 55 se não houver código do país
                    if ( strlen( $number ) >= 10 && substr( $number, 0, 2 ) !== '55' ) {
                        $number = '55' . $number;
                    }
                    $client_name = $client->post_title;
                    $pet_name    = $pet->post_title;
                    $message = sprintf( __( 'Olá %s, esperamos que você e %s estejam bem! Notamos que %s está há mais de 30 dias sem um banho/tosa. Que tal agendar um horário conosco? 😊', 'dps-stats-addon' ), $client_name, $pet_name, $pet_name );
                    $encoded  = rawurlencode( $message );
                    $whats_link = '<a href="https://wa.me/' . esc_attr( $number ) . '?text=' . $encoded . '" target="_blank">WhatsApp</a>';
                }
                echo '<tr><td>' . esc_html( $pet->post_title ) . '</td><td>' . esc_html( $client->post_title ) . '</td><td>' . esc_html( $last_fmt ) . '</td><td>' . ( $whats_link ? $whats_link : '-' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'Todos os pets atendidos recentemente.', 'dps-stats-addon' ) . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Retorna clientes e pets inativos, com cache.
     *
     * @param int $cutoff_ts Timestamp do limite de inatividade.
     *
     * @return array
     */
    private function get_inactive_entities( $cutoff_ts ) {
        $cache_key = dps_stats_build_cache_key( 'dps_stats_inactive', date( 'Y-m-d', $cutoff_ts ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Limite razoável: 500 clientes para análise de inatividade.
        // Para bases maiores, considerar processamento em background.
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ] );

        $inactive_clients = [];
        $inactive_pets    = [];

        // Pré-carregar objetos de clientes para evitar queries adicionais.
        $client_objects = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
            'post_status'    => 'publish',
            'include'        => $clients,
        ] );

        foreach ( $client_objects as $client ) {
            $last_appt = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_key'       => 'appointment_date',
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
                'meta_query'     => [
                    [ 'key' => 'appointment_client_id', 'value' => $client->ID, 'compare' => '=' ],
                ],
            ] );

            $last_date = '';
            if ( $last_appt ) {
                $last_date = get_post_meta( $last_appt[0]->ID, 'appointment_date', true );
            }

            if ( ! $last_date || strtotime( $last_date ) < $cutoff_ts ) {
                $inactive_clients[] = [ 'client' => $client, 'last_date' => $last_date ];
            }

            // Limite razoável de pets por cliente (maioria dos clientes tem 1-3 pets).
            $pets = get_posts( [
                'post_type'      => 'dps_pet',
                'posts_per_page' => 50,
                'post_status'    => 'publish',
                'meta_key'       => 'owner_id',
                'meta_value'     => $client->ID,
            ] );

            foreach ( $pets as $pet ) {
                $last_pet = get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'meta_key'       => 'appointment_date',
                    'orderby'        => 'meta_value',
                    'order'          => 'DESC',
                    'meta_query'     => [
                        [ 'key' => 'appointment_pet_id', 'value' => $pet->ID, 'compare' => '=' ],
                    ],
                ] );

                $last_pet_date = '';
                if ( $last_pet ) {
                    $last_pet_date = get_post_meta( $last_pet[0]->ID, 'appointment_date', true );
                }

                if ( ! $last_pet_date || strtotime( $last_pet_date ) < $cutoff_ts ) {
                    $inactive_pets[] = [ 'pet' => $pet, 'client' => $client, 'last_date' => $last_pet_date ];
                }
            }
        }

        $result = [
            'inactive_clients' => $inactive_clients,
            'inactive_pets'    => $inactive_pets,
        ];

        set_transient( $cache_key, $result, DAY_IN_SECONDS );

        return $result;
    }

    /**
     * Retorna estatísticas agregadas de atendimentos do período com cache.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return array
     */
    private function get_recent_appointments_stats( $start_date, $end_date ) {
        $cache_key = dps_stats_build_cache_key( 'dps_stats_appointments', $start_date, $end_date );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Limite de 1000 agendamentos para estatísticas de período.
        // Para períodos muito longos com muitos dados, considerar agregação via SQL direto.
        $recent_appts = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 1000,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'appointment_date', 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
                [ 'key' => 'appointment_date', 'value' => $end_date,   'compare' => '<=', 'type' => 'DATE' ],
            ],
        ] );

        $service_counts = [];
        $species_counts = [];
        $breed_counts   = [];
        $client_counts  = [];

        foreach ( $recent_appts as $appt ) {
            $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
            if ( is_array( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $service_counts[ $sid ] = ( $service_counts[ $sid ] ?? 0 ) + 1;
                }
            }

            $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );

            if ( $client_id ) {
                if ( ! isset( $client_counts[ $client_id ] ) ) {
                    $client_counts[ $client_id ] = 0;
                }
                $client_counts[ $client_id ]++;
            }

            if ( $pet_id ) {
                $species = get_post_meta( $pet_id, 'pet_species', true );
                $breed   = get_post_meta( $pet_id, 'pet_breed', true );

                if ( $species === 'cao' ) {
                    $species_label = __( 'Cachorro', 'dps-stats-addon' );
                } elseif ( $species === 'gato' ) {
                    $species_label = __( 'Gato', 'dps-stats-addon' );
                } else {
                    $species_label = __( 'Outro', 'dps-stats-addon' );
                }

                $species_counts[ $species_label ] = ( $species_counts[ $species_label ] ?? 0 ) + 1;

                if ( $breed ) {
                    $breed_counts[ $breed ] = ( $breed_counts[ $breed ] ?? 0 ) + 1;
                }
            }
        }

        $result = [
            'total'           => count( $recent_appts ),
            'service_counts'  => $service_counts,
            'species_counts'  => $species_counts,
            'breed_counts'    => $breed_counts,
            'client_counts'   => $client_counts,
        ];

        set_transient( $cache_key, $result, HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Retorna receitas e despesas pagas do período com cache.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return array
     */
    private function get_financial_totals( $start_date, $end_date ) {
        $cache_key = dps_stats_build_cache_key( 'dps_stats_financial', $start_date, $end_date );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

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

        $totals = [
            'revenue'  => isset( $results['receita'] ) ? (float) $results['receita']->total : 0,
            'expenses' => isset( $results['despesa'] ) ? (float) $results['despesa']->total : 0,
        ];

        // Armazena cache compartilhado para receitas totais.
        $revenue_key = dps_stats_build_cache_key( 'dps_stats_total_revenue', $start_date, $end_date );
        set_transient( $revenue_key, $totals['revenue'], HOUR_IN_SECONDS );

        set_transient( $cache_key, $totals, HOUR_IN_SECONDS );

        return $totals;
    }
}

/**
 * Inicializa o Stats Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_stats_init_addon() {
    if ( class_exists( 'DPS_Stats_Addon' ) ) {
        new DPS_Stats_Addon();
    }
}
add_action( 'init', 'dps_stats_init_addon', 5 );
