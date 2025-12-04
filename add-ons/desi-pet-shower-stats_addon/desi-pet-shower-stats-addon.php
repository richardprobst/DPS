<?php
/**
 * Plugin Name:       DPS by PRObst – Estatísticas Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Dashboard visual com métricas e relatórios. Acompanhe desempenho, compare períodos e exporte dados.
 * Version:           1.1.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
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

// Define constantes
define( 'DPS_STATS_VERSION', '1.1.0' );
define( 'DPS_STATS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPS_STATS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 */
function dps_stats_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Estatísticas requer o plugin base DPS by PRObst para funcionar.', 'dps-stats-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_stats_check_base_plugin() ) {
        return;
    }
    require_once DPS_STATS_PLUGIN_DIR . 'includes/class-dps-stats-api.php';
}, 1 );

function dps_stats_load_textdomain() {
    load_plugin_textdomain( 'dps-stats-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_stats_load_textdomain', 1 );

if ( ! function_exists( 'dps_stats_build_cache_key' ) ) {
    function dps_stats_build_cache_key( $prefix, $start_date, $end_date = '' ) {
        $start_key = preg_replace( '/[^0-9]/', '', $start_date );
        $end_key   = $end_date ? preg_replace( '/[^0-9]/', '', $end_date ) : '';
        return $end_key ? sprintf( '%s_%s_%s', $prefix, $start_key, $end_key ) : sprintf( '%s_%s', $prefix, $start_key );
    }
}

if ( ! function_exists( 'dps_get_total_revenue' ) ) {
    /**
     * Calcula o total de receitas pagas no intervalo informado.
     *
     * @deprecated 1.1.0 Use DPS_Stats_API::get_revenue_total() instead.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     *
     * @return float
     */
    function dps_get_total_revenue( $start_date, $end_date ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Stats_API::get_revenue_total()' );
        
        if ( class_exists( 'DPS_Stats_API' ) ) {
            return DPS_Stats_API::get_revenue_total( $start_date, $end_date );
        }
        global $wpdb;
        $start_date = sanitize_text_field( $start_date );
        $end_date   = sanitize_text_field( $end_date );
        $cache_key = dps_stats_build_cache_key( 'dps_stats_total_revenue', $start_date, $end_date );
        
        // Verifica cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) return (float) $cached;
        }
        
        $table = $wpdb->prefix . 'dps_transacoes';
        $total = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM {$table} WHERE data >= %s AND data <= %s AND status = 'pago' AND tipo = 'receita'",
            $start_date, $end_date
        ) );
        
        // Armazena cache apenas se não estiver desabilitado
        if ( ! dps_is_cache_disabled() ) {
            set_transient( $cache_key, $total, HOUR_IN_SECONDS );
        }
        
        return $total;
    }
}

if ( ! function_exists( 'dps_stats_clear_cache' ) ) {
    function dps_stats_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para limpar o cache.', 'dps-stats-addon' ) );
        }
        check_admin_referer( 'dps_clear_stats_cache', 'dps_clear_stats_cache_nonce' );
        global $wpdb;
        $transient_prefix = $wpdb->esc_like( '_transient_dps_stats_' ) . '%';
        $transient_timeout = $wpdb->esc_like( '_transient_timeout_dps_stats_' ) . '%';
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $transient_prefix, $transient_timeout
        ) );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
        exit;
    }
}
add_action( 'admin_post_dps_clear_stats_cache', 'dps_stats_clear_cache' );

class DPS_Stats_Addon {
    public function __construct() {
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_stats_tab' ], 20, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_stats_section' ], 20, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
        add_action( 'admin_post_dps_export_stats_csv', [ $this, 'handle_export_csv' ] );
        add_action( 'admin_post_dps_export_inactive_csv', [ $this, 'handle_export_inactive_csv' ] );
    }

    public function register_assets() {
        wp_register_style( 'dps-stats-addon', DPS_STATS_PLUGIN_URL . 'assets/css/stats-addon.css', [], DPS_STATS_VERSION );
        wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true );
        wp_register_script( 'dps-stats-addon', DPS_STATS_PLUGIN_URL . 'assets/js/stats-addon.js', [ 'chartjs' ], DPS_STATS_VERSION, true );
    }

    private function enqueue_assets() {
        wp_enqueue_style( 'dps-stats-addon' );
        wp_enqueue_script( 'chartjs' );
        wp_enqueue_script( 'dps-stats-addon' );
    }

    public function add_stats_tab( $visitor_only ) {
        if ( $visitor_only ) return;
        echo '<li><a href="#" class="dps-tab-link" data-tab="estatisticas">' . esc_html__( 'Estatísticas', 'dps-stats-addon' ) . '</a></li>';
    }

    public function add_stats_section( $visitor_only ) {
        if ( $visitor_only ) return;
        $this->enqueue_assets();
        echo $this->section_stats();
    }

    private function section_stats() {
        $dates = $this->get_date_range();
        $start_date = $dates['start'];
        $end_date   = $dates['end'];
        $comparison    = DPS_Stats_API::get_period_comparison( $start_date, $end_date );
        $top_services  = DPS_Stats_API::get_top_services( $start_date, $end_date, 5 );
        $species       = DPS_Stats_API::get_species_distribution( $start_date, $end_date );
        $top_breeds    = DPS_Stats_API::get_top_breeds( $start_date, $end_date, 5 );
        $inactive_pets = DPS_Stats_API::get_inactive_pets( 30 );
        $new_clients   = DPS_Stats_API::get_new_clients_count( $start_date, $end_date );
        $cancel_rate   = DPS_Stats_API::get_cancellation_rate( $start_date, $end_date );
        $subs_data     = $this->get_subscription_metrics( $start_date, $end_date );

        ob_start();
        ?>
        <div class="dps-section" id="dps-section-estatisticas">
            <h3><?php esc_html_e( 'Dashboard de Estatísticas', 'dps-stats-addon' ); ?></h3>
            <?php $this->render_date_filter( $start_date, $end_date ); ?>
            <?php $this->render_metric_cards( $comparison, $new_clients, $cancel_rate ); ?>
            
            <details class="dps-stats-section" open>
                <summary><span class="dps-stats-section__icon">📊</span> <?php esc_html_e( 'Métricas Financeiras', 'dps-stats-addon' ); ?></summary>
                <div class="dps-stats-section__content"><?php $this->render_financial_metrics( $comparison ); ?></div>
            </details>

            <details class="dps-stats-section" open>
                <summary><span class="dps-stats-section__icon">📋</span> <?php esc_html_e( 'Assinaturas', 'dps-stats-addon' ); ?></summary>
                <div class="dps-stats-section__content"><?php $this->render_subscription_metrics( $subs_data ); ?></div>
            </details>

            <details class="dps-stats-section" open>
                <summary><span class="dps-stats-section__icon">🛁</span> <?php esc_html_e( 'Serviços Mais Solicitados', 'dps-stats-addon' ); ?></summary>
                <div class="dps-stats-section__content"><?php $this->render_top_services( $top_services ); ?></div>
            </details>

            <details class="dps-stats-section" open>
                <summary><span class="dps-stats-section__icon">🐾</span> <?php esc_html_e( 'Distribuição de Pets', 'dps-stats-addon' ); ?></summary>
                <div class="dps-stats-section__content"><?php $this->render_pet_distribution( $species, $top_breeds ); ?></div>
            </details>

            <details class="dps-stats-section" open>
                <summary><span class="dps-stats-section__icon">⚠️</span> <?php printf( esc_html__( 'Pets Sem Atendimento (%d)', 'dps-stats-addon' ), count( $inactive_pets ) ); ?></summary>
                <div class="dps-stats-section__content"><?php $this->render_inactive_pets_table( $inactive_pets ); ?></div>
            </details>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_date_range() {
        $today = current_time( 'timestamp' );
        $start = isset( $_GET['stats_start'] ) ? sanitize_text_field( $_GET['stats_start'] ) : '';
        $end   = isset( $_GET['stats_end'] ) ? sanitize_text_field( $_GET['stats_end'] ) : '';
        if ( ! $start ) $start = date( 'Y-m-d', $today - ( 30 * DAY_IN_SECONDS ) );
        if ( ! $end ) $end = date( 'Y-m-d', $today );
        return [ 'start' => $start, 'end' => $end ];
    }

    private function render_date_filter( $start_date, $end_date ) {
        ?>
        <div class="dps-stats-filter">
            <form method="get" style="display:contents;">
                <?php foreach ( $_GET as $k => $v ) : if ( in_array( $k, [ 'stats_start', 'stats_end' ], true ) ) continue; ?>
                    <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
                <?php endforeach; ?>
                <label><?php esc_html_e( 'De', 'dps-stats-addon' ); ?> <input type="date" name="stats_start" value="<?php echo esc_attr( $start_date ); ?>"></label>
                <label><?php esc_html_e( 'Até', 'dps-stats-addon' ); ?> <input type="date" name="stats_end" value="<?php echo esc_attr( $end_date ); ?>"></label>
                <div class="dps-stats-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar', 'dps-stats-addon' ); ?></button>
                </div>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                <?php wp_nonce_field( 'dps_clear_stats_cache', 'dps_clear_stats_cache_nonce' ); ?>
                <input type="hidden" name="action" value="dps_clear_stats_cache">
                <button type="submit" class="button button-secondary"><?php esc_html_e( '🔄 Atualizar dados', 'dps-stats-addon' ); ?></button>
            </form>
        </div>
        <?php
    }

    private function render_metric_cards( $comparison, $new_clients, $cancel_rate ) {
        $current = $comparison['current'];
        $variation = $comparison['variation'];
        ?>
        <div class="dps-stats-cards">
            <?php $this->render_card( '📋', $current['appointments'], __( 'Atendimentos', 'dps-stats-addon' ), $variation['appointments'], 'primary' ); ?>
            <?php $this->render_card( '💰', 'R$ ' . number_format( $current['revenue'], 2, ',', '.' ), __( 'Receita', 'dps-stats-addon' ), $variation['revenue'], 'success' ); ?>
            <?php $this->render_card( '📈', 'R$ ' . number_format( $current['ticket_avg'], 2, ',', '.' ), __( 'Ticket Médio', 'dps-stats-addon' ), $variation['ticket_avg'], 'primary' ); ?>
            <?php $this->render_card( '👥', $new_clients, __( 'Novos Clientes', 'dps-stats-addon' ), null, 'primary' ); ?>
            <?php $this->render_card( '❌', $cancel_rate . '%', __( 'Cancelamentos', 'dps-stats-addon' ), null, $cancel_rate > 10 ? 'danger' : 'warning' ); ?>
        </div>
        <?php
    }

    private function render_card( $icon, $value, $label, $variation = null, $type = 'primary' ) {
        $trend_class = '';
        if ( $variation !== null ) {
            $trend_class = $variation > 0 ? 'dps-stats-card__trend--up' : ( $variation < 0 ? 'dps-stats-card__trend--down' : 'dps-stats-card__trend--neutral' );
        }
        ?>
        <div class="dps-stats-card dps-stats-card--<?php echo esc_attr( $type ); ?>">
            <span class="dps-stats-card__icon"><?php echo esc_html( $icon ); ?></span>
            <span class="dps-stats-card__value"><?php echo esc_html( $value ); ?></span>
            <span class="dps-stats-card__label"><?php echo esc_html( $label ); ?></span>
            <?php if ( $variation !== null ) : ?>
                <span class="dps-stats-card__trend <?php echo esc_attr( $trend_class ); ?>"><?php echo $variation >= 0 ? '+' : ''; ?><?php echo esc_html( number_format( $variation, 1 ) ); ?>%</span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_financial_metrics( $comparison ) {
        $current = $comparison['current'];
        $previous = $comparison['previous'];
        $variation = $comparison['variation'];
        ?>
        <div class="dps-stats-metrics-list">
            <div class="dps-stats-metric"><span class="dps-stats-metric__value">R$ <?php echo esc_html( number_format( $current['revenue'], 2, ',', '.' ) ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Receita Total', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value">R$ <?php echo esc_html( number_format( $current['expenses'], 2, ',', '.' ) ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Despesas', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value">R$ <?php echo esc_html( number_format( $current['profit'], 2, ',', '.' ) ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Lucro Líquido', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value"><?php echo $variation['profit'] >= 0 ? '+' : ''; ?><?php echo esc_html( number_format( $variation['profit'], 1 ) ); ?>%</span><span class="dps-stats-metric__label"><?php esc_html_e( 'Variação vs. Anterior', 'dps-stats-addon' ); ?></span></div>
        </div>
        <p style="color: #6b7280; font-size: 13px; margin-top: 12px;"><?php printf( esc_html__( 'Período anterior: %s a %s', 'dps-stats-addon' ), date_i18n( 'd/m/Y', strtotime( $previous['start_date'] ) ), date_i18n( 'd/m/Y', strtotime( $previous['end_date'] ) ) ); ?></p>
        <div class="dps-stats-export-bar"><a href="<?php echo esc_url( $this->get_export_url( 'metrics' ) ); ?>" class="dps-stats-export-btn">📥 <?php esc_html_e( 'Exportar Métricas CSV', 'dps-stats-addon' ); ?></a></div>
        <?php
    }

    private function get_subscription_metrics( $start_date, $end_date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $subscriptions = get_posts( [ 'post_type' => 'dps_subscription', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
        $paid_count = 0; $pending_count = 0;
        foreach ( $subscriptions as $sub ) {
            if ( 'pago' === get_post_meta( $sub->ID, 'subscription_payment_status', true ) ) $paid_count++; else $pending_count++;
        }
        $revenue = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$table} WHERE plano_id IS NOT NULL AND data >= %s AND data <= %s AND status = 'pago'", $start_date, $end_date ) );
        $open_value = (float) $wpdb->get_var( "SELECT SUM(valor) FROM {$table} WHERE plano_id IS NOT NULL AND status != 'pago'" );
        return [ 'total' => count( $subscriptions ), 'paid' => $paid_count, 'pending' => $pending_count, 'revenue' => $revenue ?: 0, 'open_value' => $open_value ?: 0 ];
    }

    private function render_subscription_metrics( $data ) {
        ?>
        <div class="dps-stats-metrics-list">
            <div class="dps-stats-metric"><span class="dps-stats-metric__value" style="color: #10b981;"><?php echo esc_html( $data['paid'] ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Ativas', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value" style="color: #f59e0b;"><?php echo esc_html( $data['pending'] ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Pendentes', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value">R$ <?php echo esc_html( number_format( $data['revenue'], 2, ',', '.' ) ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Receita no Período', 'dps-stats-addon' ); ?></span></div>
            <div class="dps-stats-metric"><span class="dps-stats-metric__value" style="color: #ef4444;">R$ <?php echo esc_html( number_format( $data['open_value'], 2, ',', '.' ) ); ?></span><span class="dps-stats-metric__label"><?php esc_html_e( 'Valor em Aberto', 'dps-stats-addon' ); ?></span></div>
        </div>
        <?php
    }

    private function render_top_services( $services ) {
        if ( empty( $services ) ) { echo '<p>' . esc_html__( 'Nenhum serviço registrado no período.', 'dps-stats-addon' ) . '</p>'; return; }
        $labels = array_column( $services, 'title' ); $counts = array_column( $services, 'count' );
        ?>
        <div class="dps-stats-chart-row">
            <div class="dps-stats-chart-item">
                <div class="dps-stats-chart-title"><?php esc_html_e( 'Top 5 Serviços', 'dps-stats-addon' ); ?></div>
                <canvas id="dps-stats-services-chart" height="200"></canvas>
            </div>
            <div class="dps-stats-chart-item">
                <div class="dps-stats-chart-title"><?php esc_html_e( 'Detalhamento', 'dps-stats-addon' ); ?></div>
                <ul style="margin:0; padding-left: 20px;"><?php foreach ( $services as $svc ) : ?><li style="margin-bottom: 8px;"><strong><?php echo esc_html( $svc['title'] ); ?>:</strong> <?php echo esc_html( $svc['count'] ); ?> (<?php echo esc_html( $svc['percentage'] ); ?>%)</li><?php endforeach; ?></ul>
            </div>
        </div>
        <script>document.addEventListener('DOMContentLoaded', function() { if (typeof DPSStats !== 'undefined') { DPSStats.initServicesChart('dps-stats-services-chart', <?php echo wp_json_encode( $labels ); ?>, <?php echo wp_json_encode( $counts ); ?>, '<?php echo esc_js( __( 'Atendimentos', 'dps-stats-addon' ) ); ?>'); } });</script>
        <?php
    }

    private function render_pet_distribution( $species, $breeds ) {
        ?>
        <div class="dps-stats-chart-row">
            <div class="dps-stats-chart-item">
                <div class="dps-stats-chart-title"><?php esc_html_e( 'Distribuição por Espécie', 'dps-stats-addon' ); ?></div>
                <?php if ( ! empty( $species ) ) : ?>
                    <canvas id="dps-stats-species-chart" height="200"></canvas>
                    <script>document.addEventListener('DOMContentLoaded', function() { if (typeof DPSStats !== 'undefined') { DPSStats.initPieChart('dps-stats-species-chart', <?php echo wp_json_encode( array_column( $species, 'species' ) ); ?>, <?php echo wp_json_encode( array_column( $species, 'count' ) ); ?>); } });</script>
                <?php else : ?><p><?php esc_html_e( 'Sem dados no período.', 'dps-stats-addon' ); ?></p><?php endif; ?>
            </div>
            <div class="dps-stats-chart-item">
                <div class="dps-stats-chart-title"><?php esc_html_e( 'Top 5 Raças', 'dps-stats-addon' ); ?></div>
                <?php if ( ! empty( $breeds ) ) : ?>
                    <div class="dps-stats-distribution"><?php foreach ( $breeds as $breed ) : ?>
                        <div class="dps-stats-distribution-item">
                            <span class="dps-stats-distribution-label"><?php echo esc_html( $breed['breed'] ); ?></span>
                            <div class="dps-stats-distribution-bar"><div class="dps-stats-distribution-fill" style="width: <?php echo esc_attr( $breed['percentage'] ); ?>%;"></div></div>
                            <span class="dps-stats-distribution-value"><?php echo esc_html( $breed['count'] ); ?></span>
                        </div>
                    <?php endforeach; ?></div>
                <?php else : ?><p><?php esc_html_e( 'Sem dados no período.', 'dps-stats-addon' ); ?></p><?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_inactive_pets_table( $inactive_pets ) {
        if ( empty( $inactive_pets ) ) { echo '<p style="color: #10b981;">✅ ' . esc_html__( 'Todos os pets atendidos recentemente!', 'dps-stats-addon' ) . '</p>'; return; }
        ?>
        <div class="dps-stats-table-wrapper"><table class="dps-stats-table"><thead><tr><th><?php esc_html_e( 'Pet', 'dps-stats-addon' ); ?></th><th><?php esc_html_e( 'Cliente', 'dps-stats-addon' ); ?></th><th><?php esc_html_e( 'Último Atendimento', 'dps-stats-addon' ); ?></th><th><?php esc_html_e( 'Ação', 'dps-stats-addon' ); ?></th></tr></thead><tbody>
        <?php foreach ( array_slice( $inactive_pets, 0, 20 ) as $item ) : 
            $pet = $item['pet']; $client = $item['client']; $last_date = $item['last_date'];
            $last_fmt = $last_date ? date_i18n( 'd/m/Y', strtotime( $last_date ) ) : __( 'Nunca', 'dps-stats-addon' );
            $phone_raw = get_post_meta( $client->ID, 'client_phone', true ); $whats_url = '';
            if ( $phone_raw ) {
                $message = sprintf( __( 'Olá %s! Notamos que %s está há mais de 30 dias sem um banho/tosa. Que tal agendar um horário conosco? 😊', 'dps-stats-addon' ), $client->post_title, $pet->post_title );
                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) { $whats_url = DPS_WhatsApp_Helper::get_link_to_client( $phone_raw, $message ); }
                else { $number = preg_replace( '/\D+/', '', $phone_raw ); if ( strlen( $number ) >= 10 && substr( $number, 0, 2 ) !== '55' ) $number = '55' . $number; $whats_url = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message ); }
            }
        ?>
            <tr><td><?php echo esc_html( $pet->post_title ); ?></td><td><?php echo esc_html( $client->post_title ); ?></td><td><?php echo esc_html( $last_fmt ); ?></td><td><?php if ( $whats_url ) : ?><a href="<?php echo esc_url( $whats_url ); ?>" target="_blank" class="dps-whatsapp-link">💬 WhatsApp</a><?php else : ?>-<?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if ( count( $inactive_pets ) > 20 ) : ?><p style="color: #6b7280; font-size: 13px;"><?php printf( esc_html__( 'Exibindo 20 de %d pets. Exporte o CSV para ver todos.', 'dps-stats-addon' ), count( $inactive_pets ) ); ?></p><?php endif; ?>
        <div class="dps-stats-export-bar"><a href="<?php echo esc_url( $this->get_export_url( 'inactive' ) ); ?>" class="dps-stats-export-btn">📥 <?php esc_html_e( 'Exportar Inativos CSV', 'dps-stats-addon' ); ?></a></div>
        <?php
    }

    private function get_export_url( $type ) {
        $dates = $this->get_date_range();
        return wp_nonce_url( add_query_arg( [ 'action' => $type === 'inactive' ? 'dps_export_inactive_csv' : 'dps_export_stats_csv', 'stats_start' => $dates['start'], 'stats_end' => $dates['end'] ], admin_url( 'admin-post.php' ) ), 'dps_export_' . $type, 'dps_export_nonce' );
    }

    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Acesso negado.', 'dps-stats-addon' ) );
        check_admin_referer( 'dps_export_metrics', 'dps_export_nonce' );
        $start_date = isset( $_GET['stats_start'] ) ? sanitize_text_field( $_GET['stats_start'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date = isset( $_GET['stats_end'] ) ? sanitize_text_field( $_GET['stats_end'] ) : date( 'Y-m-d' );
        $csv = DPS_Stats_API::export_metrics_csv( $start_date, $end_date );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="estatisticas-dps-' . $start_date . '-a-' . $end_date . '.csv"' );
        echo $csv; exit;
    }

    public function handle_export_inactive_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Acesso negado.', 'dps-stats-addon' ) );
        check_admin_referer( 'dps_export_inactive', 'dps_export_nonce' );
        $csv = DPS_Stats_API::export_inactive_pets_csv( 30 );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="pets-inativos-dps-' . date( 'Y-m-d' ) . '.csv"' );
        echo $csv; exit;
    }
}

function dps_stats_init_addon() {
    if ( class_exists( 'DPS_Stats_Addon' ) ) {
        new DPS_Stats_Addon();
    }
}
add_action( 'init', 'dps_stats_init_addon', 5 );
