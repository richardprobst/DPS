<?php
/**
 * Painel Central (Dashboard) do DPS.
 *
 * Fornece visão consolidada do sistema com:
 * - Métricas principais (agendamentos, clientes, pets, pendências)
 * - Links rápidos para módulos principais
 * - Atividade recente
 * - Atalhos para ações comuns
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe do Painel Central.
 */
class DPS_Dashboard {

    /**
     * Renderiza o painel central completo.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        // Enfileira estilos do dashboard
        wp_enqueue_style(
            'dps-dashboard',
            DPS_BASE_URL . 'assets/css/dashboard.css',
            [],
            DPS_BASE_VERSION
        );

        ?>
        <div class="wrap dps-dashboard">
            <h1><?php esc_html_e( 'Painel Central', 'desi-pet-shower' ); ?></h1>
            
            <?php self::render_welcome_message(); ?>
            <?php self::render_metrics_cards(); ?>
            <?php self::render_quick_links(); ?>
            <?php self::render_recent_activity(); ?>
        </div>
        <?php
    }

    /**
     * Renderiza mensagem de boas-vindas.
     */
    private static function render_welcome_message() {
        $current_user = wp_get_current_user();
        $greeting = self::get_greeting();
        
        ?>
        <div class="dps-welcome-banner">
            <h2>
                <?php 
                printf(
                    /* translators: 1: greeting (Bom dia/Boa tarde/Boa noite), 2: user name */
                    esc_html__( '%1$s, %2$s!', 'desi-pet-shower' ),
                    esc_html( $greeting ),
                    esc_html( $current_user->display_name )
                );
                ?>
            </h2>
            <p><?php esc_html_e( 'Aqui está um resumo do seu sistema DPS by PRObst.', 'desi-pet-shower' ); ?></p>
        </div>
        <?php
    }

    /**
     * Renderiza cards de métricas principais.
     */
    private static function render_metrics_cards() {
        $metrics = self::get_metrics();
        
        ?>
        <div class="dps-metrics-grid">
            <!-- Agendamentos Hoje -->
            <div class="dps-metric-card dps-metric-card--primary">
                <div class="dps-metric-card__icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="dps-metric-card__content">
                    <div class="dps-metric-card__value"><?php echo esc_html( $metrics['appointments_today'] ); ?></div>
                    <div class="dps-metric-card__label"><?php esc_html_e( 'Agendamentos Hoje', 'desi-pet-shower' ); ?></div>
                </div>
            </div>

            <!-- Clientes Ativos -->
            <div class="dps-metric-card dps-metric-card--success">
                <div class="dps-metric-card__icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="dps-metric-card__content">
                    <div class="dps-metric-card__value"><?php echo esc_html( number_format( $metrics['active_clients'], 0, ',', '.' ) ); ?></div>
                    <div class="dps-metric-card__label"><?php esc_html_e( 'Clientes Ativos', 'desi-pet-shower' ); ?></div>
                </div>
            </div>

            <!-- Pets Cadastrados -->
            <div class="dps-metric-card dps-metric-card--info">
                <div class="dps-metric-card__icon">
                    <span class="dashicons dashicons-pets"></span>
                </div>
                <div class="dps-metric-card__content">
                    <div class="dps-metric-card__value"><?php echo esc_html( number_format( $metrics['total_pets'], 0, ',', '.' ) ); ?></div>
                    <div class="dps-metric-card__label"><?php esc_html_e( 'Pets Cadastrados', 'desi-pet-shower' ); ?></div>
                </div>
            </div>

            <!-- Pendências Financeiras -->
            <?php if ( $metrics['has_finance'] ) : ?>
            <div class="dps-metric-card dps-metric-card--warning">
                <div class="dps-metric-card__icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="dps-metric-card__content">
                    <div class="dps-metric-card__value"><?php echo esc_html( $metrics['pending_payments'] ); ?></div>
                    <div class="dps-metric-card__label"><?php esc_html_e( 'Pagamentos Pendentes', 'desi-pet-shower' ); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza links rápidos para módulos principais.
     */
    private static function render_quick_links() {
        $modules = self::get_available_modules();
        
        ?>
        <div class="dps-section">
            <h2><?php esc_html_e( 'Módulos Principais', 'desi-pet-shower' ); ?></h2>
            <div class="dps-modules-grid">
                <?php foreach ( $modules as $module ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $module['slug'] ) ); ?>" class="dps-module-card">
                    <div class="dps-module-card__icon"><?php echo $module['icon']; ?></div>
                    <div class="dps-module-card__title"><?php echo esc_html( $module['title'] ); ?></div>
                    <div class="dps-module-card__description"><?php echo esc_html( $module['description'] ); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dps-section">
            <h2><?php esc_html_e( 'Ações Rápidas', 'desi-pet-shower' ); ?></h2>
            <div class="dps-quick-actions">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=dps_agendamento' ) ); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e( 'Novo Agendamento', 'desi-pet-shower' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=dps_cliente' ) ); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'Cadastrar Cliente', 'desi-pet-shower' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=dps_pet' ) ); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-pets"></span>
                    <?php esc_html_e( 'Cadastrar Pet', 'desi-pet-shower' ); ?>
                </a>
                <?php if ( class_exists( 'DPS_Finance_Addon' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-finance' ) ); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e( 'Ver Relatório Financeiro', 'desi-pet-shower' ); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza atividade recente.
     */
    private static function render_recent_activity() {
        $activities = self::get_recent_activity();
        
        if ( empty( $activities ) ) {
            return;
        }
        
        ?>
        <div class="dps-section">
            <h2><?php esc_html_e( 'Atividade Recente', 'desi-pet-shower' ); ?></h2>
            <div class="dps-activity-list">
                <?php foreach ( $activities as $activity ) : ?>
                <div class="dps-activity-item">
                    <div class="dps-activity-item__icon <?php echo esc_attr( $activity['type'] ); ?>">
                        <span class="dashicons <?php echo esc_attr( $activity['icon'] ); ?>"></span>
                    </div>
                    <div class="dps-activity-item__content">
                        <div class="dps-activity-item__title"><?php echo wp_kses_post( $activity['title'] ); ?></div>
                        <div class="dps-activity-item__time"><?php echo esc_html( $activity['time'] ); ?></div>
                    </div>
                    <?php if ( ! empty( $activity['link'] ) ) : ?>
                    <div class="dps-activity-item__action">
                        <a href="<?php echo esc_url( $activity['link'] ); ?>" class="button button-small">
                            <?php esc_html_e( 'Ver', 'desi-pet-shower' ); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Obtém métricas do sistema.
     *
     * @return array
     */
    private static function get_metrics() {
        // Agendamentos de hoje
        $today = gmdate( 'Y-m-d' );
        $appointments_today = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_date',
                    'value'   => $today,
                    'compare' => '=',
                ],
            ],
            'fields'         => 'ids',
        ] );

        // Clientes ativos (publicados)
        $active_clients = wp_count_posts( 'dps_cliente' );
        $client_count = isset( $active_clients->publish ) ? $active_clients->publish : 0;

        // Total de pets
        $total_pets = wp_count_posts( 'dps_pet' );
        $pet_count = isset( $total_pets->publish ) ? $total_pets->publish : 0;

        // Pendências financeiras (se add-on ativo)
        $pending_payments = 0;
        $has_finance = false;
        if ( class_exists( 'DPS_Finance_Addon' ) ) {
            $has_finance = true;
            // Conta transações pendentes
            global $wpdb;
            $pending_payments = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}dps_transacoes WHERE status = 'pendente'"
            );
        }

        return [
            'appointments_today' => count( $appointments_today ),
            'active_clients'     => $client_count,
            'total_pets'         => $pet_count,
            'pending_payments'   => $pending_payments,
            'has_finance'        => $has_finance,
        ];
    }

    /**
     * Obtém módulos disponíveis.
     *
     * @return array
     */
    private static function get_available_modules() {
        $modules = [
            [
                'slug'        => 'dps-agenda-hub',
                'icon'        => '📅',
                'title'       => __( 'Agenda', 'desi-pet-shower' ),
                'description' => __( 'Gerenciar agendamentos e capacidade', 'desi-pet-shower' ),
            ],
        ];

        // Assistente de IA
        if ( class_exists( 'DPS_AI_Hub' ) ) {
            $modules[] = [
                'slug'        => 'dps-ai-hub',
                'icon'        => '🤖',
                'title'       => __( 'Assistente de IA', 'desi-pet-shower' ),
                'description' => __( 'Configurar e monitorar IA', 'desi-pet-shower' ),
            ];
        }

        // Portal do Cliente
        if ( class_exists( 'DPS_Portal_Hub' ) ) {
            $modules[] = [
                'slug'        => 'dps-portal-hub',
                'icon'        => '👤',
                'title'       => __( 'Portal do Cliente', 'desi-pet-shower' ),
                'description' => __( 'Configurar portal e logins', 'desi-pet-shower' ),
            ];
        }

        // Integrações
        if ( class_exists( 'DPS_Integrations_Hub' ) ) {
            $modules[] = [
                'slug'        => 'dps-integrations-hub',
                'icon'        => '🔌',
                'title'       => __( 'Integrações', 'desi-pet-shower' ),
                'description' => __( 'WhatsApp, Pagamentos, Push', 'desi-pet-shower' ),
            ];
        }

        // Fidelidade & Campanhas
        if ( class_exists( 'DPS_Loyalty_Addon' ) ) {
            $modules[] = [
                'slug'        => 'dps-loyalty',
                'icon'        => '🎁',
                'title'       => __( 'Fidelidade & Campanhas', 'desi-pet-shower' ),
                'description' => __( 'Programa de pontos e campanhas', 'desi-pet-shower' ),
            ];
        }

        // Sistema
        if ( class_exists( 'DPS_System_Hub' ) ) {
            $modules[] = [
                'slug'        => 'dps-system-hub',
                'icon'        => '⚙️',
                'title'       => __( 'Sistema', 'desi-pet-shower' ),
                'description' => __( 'Logs, Backup, Debugging', 'desi-pet-shower' ),
            ];
        }

        // Ferramentas
        if ( class_exists( 'DPS_Tools_Hub' ) ) {
            $modules[] = [
                'slug'        => 'dps-tools-hub',
                'icon'        => '🛠️',
                'title'       => __( 'Ferramentas', 'desi-pet-shower' ),
                'description' => __( 'Formulários e configurações', 'desi-pet-shower' ),
            ];
        }

        return $modules;
    }

    /**
     * Obtém atividade recente do sistema.
     *
     * @return array
     */
    private static function get_recent_activity() {
        $activities = [];

        // Últimos agendamentos criados
        $recent_appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 3,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        foreach ( $recent_appointments as $appointment ) {
            $client_id = get_post_meta( $appointment->ID, 'appointment_client_id', true );
            $client_name = $client_id ? get_the_title( $client_id ) : __( 'Cliente não especificado', 'desi-pet-shower' );
            
            $activities[] = [
                'type'  => 'appointment',
                'icon'  => 'dashicons-calendar-alt',
                'title' => sprintf(
                    /* translators: %s: client name */
                    __( 'Agendamento criado para %s', 'desi-pet-shower' ),
                    esc_html( $client_name )
                ),
                'time'  => sprintf(
                    /* translators: %s: time ago */
                    __( 'há %s', 'desi-pet-shower' ),
                    human_time_diff( strtotime( $appointment->post_date ), current_time( 'timestamp' ) )
                ),
                'link'  => get_edit_post_link( $appointment->ID ),
            ];
        }

        // Últimos clientes cadastrados
        $recent_clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 2,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        foreach ( $recent_clients as $client ) {
            $activities[] = [
                'type'  => 'client',
                'icon'  => 'dashicons-admin-users',
                'title' => sprintf(
                    /* translators: %s: client name */
                    __( 'Cliente cadastrado: %s', 'desi-pet-shower' ),
                    esc_html( $client->post_title )
                ),
                'time'  => sprintf(
                    /* translators: %s: time ago */
                    __( 'há %s', 'desi-pet-shower' ),
                    human_time_diff( strtotime( $client->post_date ), current_time( 'timestamp' ) )
                ),
                'link'  => get_edit_post_link( $client->ID ),
            ];
        }

        // Ordena por data
        usort( $activities, function( $a, $b ) {
            return strcmp( $b['time'], $a['time'] );
        } );

        return array_slice( $activities, 0, 5 );
    }

    /**
     * Retorna saudação baseada no horário.
     *
     * @return string
     */
    private static function get_greeting() {
        $hour = (int) current_time( 'H' );
        
        if ( $hour >= 0 && $hour < 12 ) {
            return __( 'Bom dia', 'desi-pet-shower' );
        } elseif ( $hour >= 12 && $hour < 18 ) {
            return __( 'Boa tarde', 'desi-pet-shower' );
        } else {
            return __( 'Boa noite', 'desi-pet-shower' );
        }
    }
}
