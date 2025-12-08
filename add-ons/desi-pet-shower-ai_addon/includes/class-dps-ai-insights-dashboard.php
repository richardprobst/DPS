<?php
/**
 * Dashboard de Insights do AI Add-on.
 *
 * Responsável por:
 * - Exibir métricas consolidadas de uso da IA
 * - Top 10 perguntas mais frequentes
 * - Horários/dias de pico de uso
 * - Taxa de resolução (feedback positivo)
 * - Clientes mais engajados
 * - Gráficos e visualizações com Chart.js
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Dashboard de Insights da IA.
 */
class DPS_AI_Insights_Dashboard {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Insights_Dashboard|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Insights_Dashboard
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Registra a página de insights no menu admin.
     * 
     * NOTA: A partir da v1.8.0, este menu está oculto (parent=null) para backward compatibility.
     * Use o novo hub unificado em dps-ai-hub para acessar via aba "Insights".
     */
    public function register_menu() {
        add_submenu_page(
            null, // Oculto do menu, acessível apenas por URL direta
            __( 'IA – Insights', 'dps-ai' ),
            __( 'IA – Insights', 'dps-ai' ),
            'manage_options',
            'dps-ai-insights',
            [ $this, 'render_dashboard' ]
        );
    }

    /**
     * Carrega assets (Chart.js já incluído na Fase 2).
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'dps_page_dps-ai-insights' !== $hook ) {
            return;
        }

        // Chart.js já está registrado pelo plugin base ou analytics
        wp_enqueue_script( 'chart-js' );
        
        // CSS do dashboard de insights
        wp_enqueue_style(
            'dps-ai-insights-dashboard',
            DPS_AI_ADDON_URL . 'assets/css/dps-ai-insights-dashboard.css',
            [],
            DPS_AI_VERSION
        );
    }

    /**
     * Renderiza a página de dashboard de insights.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
        }

        // Filtros de período
        $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30';
        $allowed_periods = [ '7', '30', 'custom' ];
        if ( ! in_array( $period, $allowed_periods, true ) ) {
            $period = '30';
        }

        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';

        // Validar formato de data
        if ( $period === 'custom' ) {
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
                $period = '30';
                $start_date = '';
                $end_date = '';
            }
        }

        // Calcular datas com base no período
        if ( $period === 'custom' && $start_date && $end_date ) {
            $date_from = $start_date;
            $date_to   = $end_date;
        } else {
            $days = intval( $period );
            $date_to   = gmdate( 'Y-m-d' );
            $date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        }

        // Buscar dados
        $kpis            = $this->get_kpis( $date_from, $date_to );
        $top_questions   = $this->get_top_questions( $date_from, $date_to, 10 );
        $peak_hours      = $this->get_peak_hours( $date_from, $date_to );
        $peak_days       = $this->get_peak_days( $date_from, $date_to );
        $resolution_rate = $this->get_resolution_rate( $date_from, $date_to );
        $top_clients     = $this->get_top_clients( $date_from, $date_to, 10 );
        $channel_stats   = $this->get_channel_stats( $date_from, $date_to );

        // Renderizar HTML
        ?>
        <div class="wrap dps-ai-insights-dashboard">
            <h1><?php esc_html_e( 'IA – Insights e Analytics', 'dps-ai' ); ?></h1>

            <!-- Filtros de Período -->
            <div class="dps-insights-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="dps-ai-insights" />
                    
                    <label for="period"><?php esc_html_e( 'Período:', 'dps-ai' ); ?></label>
                    <select name="period" id="period">
                        <option value="7" <?php selected( $period, '7' ); ?>><?php esc_html_e( 'Últimos 7 dias', 'dps-ai' ); ?></option>
                        <option value="30" <?php selected( $period, '30' ); ?>><?php esc_html_e( 'Últimos 30 dias', 'dps-ai' ); ?></option>
                        <option value="custom" <?php selected( $period, 'custom' ); ?>><?php esc_html_e( 'Período customizado', 'dps-ai' ); ?></option>
                    </select>

                    <span id="custom-dates" style="<?php echo $period === 'custom' ? '' : 'display:none;'; ?>">
                        <label for="start_date"><?php esc_html_e( 'De:', 'dps-ai' ); ?></label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                        
                        <label for="end_date"><?php esc_html_e( 'Até:', 'dps-ai' ); ?></label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                    </span>

                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'dps-ai' ); ?></button>
                </form>
            </div>

            <!-- KPIs Principais -->
            <div class="dps-insights-kpis">
                <div class="dps-kpi-card">
                    <h3><?php esc_html_e( 'Total de Conversas', 'dps-ai' ); ?></h3>
                    <p class="dps-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['total_conversations'] ) ); ?></p>
                    <span class="dps-kpi-label"><?php echo esc_html( sprintf( __( '%s – %s', 'dps-ai' ), $date_from, $date_to ) ); ?></span>
                </div>

                <div class="dps-kpi-card">
                    <h3><?php esc_html_e( 'Total de Mensagens', 'dps-ai' ); ?></h3>
                    <p class="dps-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['total_messages'] ) ); ?></p>
                    <span class="dps-kpi-label"><?php echo esc_html( number_format_i18n( $kpis['avg_messages_per_conversation'], 1 ) . ' ' . __( 'por conversa', 'dps-ai' ) ); ?></span>
                </div>

                <div class="dps-kpi-card">
                    <h3><?php esc_html_e( 'Taxa de Resolução', 'dps-ai' ); ?></h3>
                    <p class="dps-kpi-value"><?php echo esc_html( number_format_i18n( $resolution_rate, 1 ) . '%' ); ?></p>
                    <span class="dps-kpi-label"><?php esc_html_e( 'Feedback positivo', 'dps-ai' ); ?></span>
                </div>

                <div class="dps-kpi-card">
                    <h3><?php esc_html_e( 'Custo Estimado', 'dps-ai' ); ?></h3>
                    <p class="dps-kpi-value">R$ <?php echo esc_html( number_format_i18n( $kpis['estimated_cost'], 2 ) ); ?></p>
                    <span class="dps-kpi-label"><?php echo esc_html( number_format_i18n( $kpis['total_tokens'] ) . ' ' . __( 'tokens', 'dps-ai' ) ); ?></span>
                </div>
            </div>

            <!-- Estatísticas por Canal -->
            <div class="dps-insights-section">
                <h2><?php esc_html_e( 'Uso por Canal', 'dps-ai' ); ?></h2>
                <canvas id="channelChart" width="400" height="200"></canvas>
            </div>

            <!-- Top 10 Perguntas -->
            <div class="dps-insights-section">
                <h2><?php esc_html_e( 'Top 10 Perguntas Mais Frequentes', 'dps-ai' ); ?></h2>
                <?php if ( ! empty( $top_questions ) ) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Pergunta', 'dps-ai' ); ?></th>
                                <th><?php esc_html_e( 'Frequência', 'dps-ai' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $top_questions as $question ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $question['text'] ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $question['count'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'Nenhuma pergunta registrada no período selecionado.', 'dps-ai' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Horários de Pico -->
            <div class="dps-insights-section">
                <h2><?php esc_html_e( 'Horários de Pico', 'dps-ai' ); ?></h2>
                <canvas id="peakHoursChart" width="400" height="200"></canvas>
            </div>

            <!-- Dias da Semana -->
            <div class="dps-insights-section">
                <h2><?php esc_html_e( 'Dias da Semana com Mais Conversas', 'dps-ai' ); ?></h2>
                <canvas id="peakDaysChart" width="400" height="200"></canvas>
            </div>

            <!-- Top 10 Clientes Engajados -->
            <div class="dps-insights-section">
                <h2><?php esc_html_e( 'Top 10 Clientes Mais Engajados', 'dps-ai' ); ?></h2>
                <?php if ( ! empty( $top_clients ) ) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Cliente', 'dps-ai' ); ?></th>
                                <th><?php esc_html_e( 'Conversas', 'dps-ai' ); ?></th>
                                <th><?php esc_html_e( 'Mensagens', 'dps-ai' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $top_clients as $client ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $client['name'] ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $client['conversations'] ) ); ?></td>
                                    <td><?php echo esc_html( number_format_i18n( $client['messages'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'Nenhum dado de clientes no período selecionado.', 'dps-ai' ); ?></p>
                <?php endif; ?>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Toggle custom dates
                $('#period').on('change', function() {
                    if ($(this).val() === 'custom') {
                        $('#custom-dates').show();
                    } else {
                        $('#custom-dates').hide();
                    }
                });

                // Gráfico de canais
                new Chart(document.getElementById('channelChart'), {
                    type: 'pie',
                    data: {
                        labels: <?php echo wp_json_encode( array_column( $channel_stats, 'label' ) ); ?>,
                        datasets: [{
                            data: <?php echo wp_json_encode( array_column( $channel_stats, 'count' ) ); ?>,
                            backgroundColor: ['#0ea5e9', '#10b981', '#f59e0b', '#ef4444']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });

                // Gráfico de horários de pico
                new Chart(document.getElementById('peakHoursChart'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo wp_json_encode( array_column( $peak_hours, 'hour_label' ) ); ?>,
                        datasets: [{
                            label: '<?php esc_html_e( 'Mensagens', 'dps-ai' ); ?>',
                            data: <?php echo wp_json_encode( array_column( $peak_hours, 'count' ) ); ?>,
                            backgroundColor: '#0ea5e9'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });

                // Gráfico de dias da semana
                new Chart(document.getElementById('peakDaysChart'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo wp_json_encode( array_column( $peak_days, 'day_label' ) ); ?>,
                        datasets: [{
                            label: '<?php esc_html_e( 'Conversas', 'dps-ai' ); ?>',
                            data: <?php echo wp_json_encode( array_column( $peak_days, 'count' ) ); ?>,
                            backgroundColor: '#10b981'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Calcula KPIs principais.
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @return array
     */
    private function get_kpis( $date_from, $date_to ) {
        global $wpdb;

        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;
        $msg_table  = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;
        $metrics_table = $wpdb->prefix . DPS_AI_Analytics::TABLE_NAME;

        // Total de conversas
        $total_conversations = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$conv_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        // Total de mensagens
        $total_messages = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$msg_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        // Média de mensagens por conversa
        $avg_messages = $total_conversations > 0 ? ( $total_messages / $total_conversations ) : 0;

        // Total de tokens e custo estimado
        $tokens_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(tokens_input + tokens_output) as total_tokens, 
                    SUM(tokens_input) as input_tokens,
                    SUM(tokens_output) as output_tokens
             FROM {$metrics_table} WHERE date BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        $total_tokens = $tokens_data ? intval( $tokens_data->total_tokens ) : 0;
        
        // Estimativa de custo (GPT-4o mini: $0.15/1M input, $0.60/1M output)
        $input_cost  = $tokens_data ? ( intval( $tokens_data->input_tokens ) / 1000000 ) * 0.15 : 0;
        $output_cost = $tokens_data ? ( intval( $tokens_data->output_tokens ) / 1000000 ) * 0.60 : 0;
        $estimated_cost = $input_cost + $output_cost;

        return [
            'total_conversations' => intval( $total_conversations ),
            'total_messages' => intval( $total_messages ),
            'avg_messages_per_conversation' => $avg_messages,
            'total_tokens' => $total_tokens,
            'estimated_cost' => $estimated_cost,
        ];
    }

    /**
     * Busca top N perguntas mais frequentes.
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @param int    $limit     Número de resultados.
     * @return array
     */
    private function get_top_questions( $date_from, $date_to, $limit = 10 ) {
        global $wpdb;

        $msg_table = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT message_text as text, COUNT(*) as count
             FROM {$msg_table}
             WHERE sender_type = 'user'
               AND DATE(created_at) BETWEEN %s AND %s
               AND CHAR_LENGTH(message_text) > 10
             GROUP BY message_text
             ORDER BY count DESC
             LIMIT %d",
            $date_from,
            $date_to,
            $limit
        ), ARRAY_A );

        return $results ?: [];
    }

    /**
     * Calcula horários de pico (0-23h).
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @return array
     */
    private function get_peak_hours( $date_from, $date_to ) {
        global $wpdb;

        $msg_table = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
             FROM {$msg_table}
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY hour
             ORDER BY hour ASC",
            $date_from,
            $date_to
        ), ARRAY_A );

        // Preencher todas as horas (0-23)
        $hours = array_fill( 0, 24, 0 );
        foreach ( $results as $row ) {
            $hours[ intval( $row['hour'] ) ] = intval( $row['count'] );
        }

        $data = [];
        for ( $h = 0; $h < 24; $h++ ) {
            $data[] = [
                'hour' => $h,
                'hour_label' => sprintf( '%02d:00', $h ),
                'count' => $hours[ $h ],
            ];
        }

        return $data;
    }

    /**
     * Calcula dias da semana com mais conversas.
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @return array
     */
    private function get_peak_days( $date_from, $date_to ) {
        global $wpdb;

        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DAYOFWEEK(created_at) as day_num, COUNT(*) as count
             FROM {$conv_table}
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY day_num
             ORDER BY day_num ASC",
            $date_from,
            $date_to
        ), ARRAY_A );

        $days_labels = [
            1 => __( 'Domingo', 'dps-ai' ),
            2 => __( 'Segunda', 'dps-ai' ),
            3 => __( 'Terça', 'dps-ai' ),
            4 => __( 'Quarta', 'dps-ai' ),
            5 => __( 'Quinta', 'dps-ai' ),
            6 => __( 'Sexta', 'dps-ai' ),
            7 => __( 'Sábado', 'dps-ai' ),
        ];

        $days = array_fill( 1, 7, 0 );
        foreach ( $results as $row ) {
            $days[ intval( $row['day_num'] ) ] = intval( $row['count'] );
        }

        $data = [];
        for ( $d = 1; $d <= 7; $d++ ) {
            $data[] = [
                'day_num' => $d,
                'day_label' => $days_labels[ $d ],
                'count' => $days[ $d ],
            ];
        }

        return $data;
    }

    /**
     * Calcula taxa de resolução (% feedback positivo).
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @return float
     */
    private function get_resolution_rate( $date_from, $date_to ) {
        global $wpdb;

        $feedback_table = $wpdb->prefix . DPS_AI_Analytics::FEEDBACK_TABLE_NAME;

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$feedback_table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        if ( ! $total ) {
            return 0;
        }

        $positive = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$feedback_table} WHERE is_positive = 1 AND DATE(created_at) BETWEEN %s AND %s",
            $date_from,
            $date_to
        ) );

        return ( $positive / $total ) * 100;
    }

    /**
     * Busca top N clientes mais engajados.
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @param int    $limit     Número de resultados.
     * @return array
     */
    private function get_top_clients( $date_from, $date_to, $limit = 10 ) {
        global $wpdb;

        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;
        $msg_table  = $wpdb->prefix . DPS_AI_Conversations_Repository::MESSAGES_TABLE;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.customer_id,
                    COUNT(DISTINCT c.id) as conversations,
                    COUNT(m.id) as messages
             FROM {$conv_table} c
             LEFT JOIN {$msg_table} m ON m.conversation_id = c.id
             WHERE c.customer_id > 0
               AND DATE(c.created_at) BETWEEN %s AND %s
             GROUP BY c.customer_id
             ORDER BY conversations DESC, messages DESC
             LIMIT %d",
            $date_from,
            $date_to,
            $limit
        ), ARRAY_A );

        // Buscar nomes dos clientes
        $data = [];
        foreach ( $results as $row ) {
            $client_id = intval( $row['customer_id'] );
            $user_data = get_userdata( $client_id );
            $name = $user_data ? $user_data->display_name : sprintf( __( 'Cliente #%d', 'dps-ai' ), $client_id );

            $data[] = [
                'customer_id' => $client_id,
                'name' => $name,
                'conversations' => intval( $row['conversations'] ),
                'messages' => intval( $row['messages'] ),
            ];
        }

        return $data;
    }

    /**
     * Estatísticas por canal.
     *
     * @param string $date_from Data inicial (Y-m-d).
     * @param string $date_to   Data final (Y-m-d).
     * @return array
     */
    private function get_channel_stats( $date_from, $date_to ) {
        global $wpdb;

        $conv_table = $wpdb->prefix . DPS_AI_Conversations_Repository::CONVERSATIONS_TABLE;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(*) as count
             FROM {$conv_table}
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY channel
             ORDER BY count DESC",
            $date_from,
            $date_to
        ), ARRAY_A );

        $labels = [
            'web_chat' => __( 'Chat Público', 'dps-ai' ),
            'portal' => __( 'Portal do Cliente', 'dps-ai' ),
            'whatsapp' => __( 'WhatsApp', 'dps-ai' ),
            'admin_specialist' => __( 'Modo Especialista', 'dps-ai' ),
        ];

        $data = [];
        foreach ( $results as $row ) {
            $data[] = [
                'channel' => $row['channel'],
                'label' => isset( $labels[ $row['channel'] ] ) ? $labels[ $row['channel'] ] : $row['channel'],
                'count' => intval( $row['count'] ),
            ];
        }

        return $data;
    }
}
