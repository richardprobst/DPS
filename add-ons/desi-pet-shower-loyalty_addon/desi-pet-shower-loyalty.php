<?php
/**
 * Plugin Name:       Desi Pet Shower – Campanhas & Fidelidade
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para campanhas promocionais e programa de fidelidade do Desi Pet Shower.
 * Version:           0.1.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       desi-pet-shower
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Loyalty_Addon {

    const OPTION_KEY = 'dps_loyalty_settings';

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_campaign_metaboxes' ] );
        add_action( 'save_post_dps_campaign', [ $this, 'save_campaign_meta' ] );
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_dps_loyalty_run_audit', [ $this, 'handle_campaign_audit' ] );
        add_action( 'updated_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
    }

    public function register_post_type() {
        $labels = [
            'name'               => _x( 'Campanhas', 'post type general name', 'desi-pet-shower' ),
            'singular_name'      => _x( 'Campanha', 'post type singular name', 'desi-pet-shower' ),
            'menu_name'          => _x( 'Campanhas', 'admin menu', 'desi-pet-shower' ),
            'name_admin_bar'     => _x( 'Campanha', 'add new on admin bar', 'desi-pet-shower' ),
            'add_new'            => _x( 'Adicionar nova', 'campaign', 'desi-pet-shower' ),
            'add_new_item'       => __( 'Adicionar nova campanha', 'desi-pet-shower' ),
            'new_item'           => __( 'Nova campanha', 'desi-pet-shower' ),
            'edit_item'          => __( 'Editar campanha', 'desi-pet-shower' ),
            'view_item'          => __( 'Ver campanha', 'desi-pet-shower' ),
            'all_items'          => __( 'Todas as campanhas', 'desi-pet-shower' ),
            'search_items'       => __( 'Buscar campanhas', 'desi-pet-shower' ),
            'not_found'          => __( 'Nenhuma campanha encontrada.', 'desi-pet-shower' ),
            'not_found_in_trash' => __( 'Nenhuma campanha na lixeira.', 'desi-pet-shower' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'supports'           => [ 'title', 'editor' ],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => false,
        ];

        register_post_type( 'dps_campaign', $args );
    }

    public function register_campaign_metaboxes() {
        add_meta_box(
            'dps_campaign_details',
            __( 'Configurações da campanha', 'desi-pet-shower' ),
            [ $this, 'render_campaign_details_meta_box' ],
            'dps_campaign',
            'normal',
            'high'
        );
    }

    public function render_campaign_details_meta_box( $post ) {
        wp_nonce_field( 'dps_campaign_details', 'dps_campaign_details_nonce' );

        $campaign_type         = get_post_meta( $post->ID, 'dps_campaign_type', true );
        $eligibility           = get_post_meta( $post->ID, 'dps_campaign_eligibility', true );
        $inactive_days         = get_post_meta( $post->ID, 'dps_campaign_inactive_days', true );
        $points_threshold      = get_post_meta( $post->ID, 'dps_campaign_points_threshold', true );
        $start_date            = get_post_meta( $post->ID, 'dps_campaign_start_date', true );
        $end_date              = get_post_meta( $post->ID, 'dps_campaign_end_date', true );
        $eligibility_selection = is_array( $eligibility ) ? $eligibility : [];
        ?>
        <p>
            <label for="dps_campaign_type"><strong><?php esc_html_e( 'Tipo de campanha', 'desi-pet-shower' ); ?></strong></label>
            <select id="dps_campaign_type" name="dps_campaign_type" class="widefat">
                <option value="percentage" <?php selected( $campaign_type, 'percentage' ); ?>><?php esc_html_e( 'Desconto percentual', 'desi-pet-shower' ); ?></option>
                <option value="fixed" <?php selected( $campaign_type, 'fixed' ); ?>><?php esc_html_e( 'Desconto fixo', 'desi-pet-shower' ); ?></option>
                <option value="double_points" <?php selected( $campaign_type, 'double_points' ); ?>><?php esc_html_e( 'Pontos em dobro', 'desi-pet-shower' ); ?></option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e( 'Critérios de elegibilidade', 'desi-pet-shower' ); ?></strong></label><br />
            <label>
                <input type="checkbox" name="dps_campaign_eligibility[]" value="inactive" <?php checked( in_array( 'inactive', $eligibility_selection, true ) ); ?> />
                <?php esc_html_e( 'Clientes sem atendimento há X dias', 'desi-pet-shower' ); ?>
            </label>
            <input type="number" name="dps_campaign_inactive_days" value="<?php echo esc_attr( $inactive_days ); ?>" min="0" class="small-text" />
        </p>
        <p>
            <label>
                <input type="checkbox" name="dps_campaign_eligibility[]" value="points" <?php checked( in_array( 'points', $eligibility_selection, true ) ); ?> />
                <?php esc_html_e( 'Clientes com mais de N pontos', 'desi-pet-shower' ); ?>
            </label>
            <input type="number" name="dps_campaign_points_threshold" value="<?php echo esc_attr( $points_threshold ); ?>" min="0" class="small-text" />
        </p>
        <p>
            <label for="dps_campaign_start_date"><strong><?php esc_html_e( 'Início', 'desi-pet-shower' ); ?></strong></label>
            <input type="date" id="dps_campaign_start_date" name="dps_campaign_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
        </p>
        <p>
            <label for="dps_campaign_end_date"><strong><?php esc_html_e( 'Fim', 'desi-pet-shower' ); ?></strong></label>
            <input type="date" id="dps_campaign_end_date" name="dps_campaign_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
        </p>
        <?php
    }

    public function save_campaign_meta( $post_id ) {
        if ( ! isset( $_POST['dps_campaign_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dps_campaign_details_nonce'] ), 'dps_campaign_details' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $campaign_type    = isset( $_POST['dps_campaign_type'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_type'] ) ) : '';
        $eligibility_raw  = isset( $_POST['dps_campaign_eligibility'] ) ? (array) wp_unslash( $_POST['dps_campaign_eligibility'] ) : [];
        $eligibility_safe = array_map( 'sanitize_text_field', $eligibility_raw );
        $inactive_days    = isset( $_POST['dps_campaign_inactive_days'] ) ? absint( $_POST['dps_campaign_inactive_days'] ) : 0;
        $points_threshold = isset( $_POST['dps_campaign_points_threshold'] ) ? absint( $_POST['dps_campaign_points_threshold'] ) : 0;
        $start_date       = isset( $_POST['dps_campaign_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_start_date'] ) ) : '';
        $end_date         = isset( $_POST['dps_campaign_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_campaign_end_date'] ) ) : '';

        update_post_meta( $post_id, 'dps_campaign_type', $campaign_type );
        update_post_meta( $post_id, 'dps_campaign_eligibility', $eligibility_safe );
        update_post_meta( $post_id, 'dps_campaign_inactive_days', $inactive_days );
        update_post_meta( $post_id, 'dps_campaign_points_threshold', $points_threshold );
        update_post_meta( $post_id, 'dps_campaign_start_date', $start_date );
        update_post_meta( $post_id, 'dps_campaign_end_date', $end_date );
    }

    public function register_menu() {
        if ( ! isset( $GLOBALS['admin_page_hooks']['desi-pet-shower'] ) ) {
            add_menu_page(
                __( 'Desi Pet Shower', 'desi-pet-shower' ),
                __( 'Desi Pet Shower', 'desi-pet-shower' ),
                'manage_options',
                'desi-pet-shower',
                '__return_null',
                'dashicons-pets'
            );
        }

        add_submenu_page(
            'desi-pet-shower',
            __( 'Campanhas & Fidelidade', 'desi-pet-shower' ),
            __( 'Campanhas & Fidelidade', 'desi-pet-shower' ),
            'manage_options',
            'dps-loyalty',
            [ $this, 'render_loyalty_page' ]
        );

        add_submenu_page(
            'desi-pet-shower',
            __( 'Campanhas', 'desi-pet-shower' ),
            __( 'Campanhas', 'desi-pet-shower' ),
            'manage_options',
            'edit.php?post_type=dps_campaign'
        );
    }

    public function render_loyalty_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings    = get_option( self::OPTION_KEY, [] );
        $brl_per_pt  = isset( $settings['brl_per_point'] ) && $settings['brl_per_point'] > 0 ? (float) $settings['brl_per_point'] : 10.0;
        $selected_id = isset( $_GET['dps_client_id'] ) ? intval( $_GET['dps_client_id'] ) : 0;
        $clients     = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $logs        = $selected_id ? dps_loyalty_get_logs( $selected_id ) : [];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Campanhas & Fidelidade', 'desi-pet-shower' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'dps_loyalty_settings_group' );
                do_settings_sections( 'dps_loyalty_settings_page' );
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Regra de pontos', 'desi-pet-shower' ); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e( '1 ponto a cada', 'desi-pet-shower' ); ?>
                                <input type="number" step="0.01" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[brl_per_point]" value="<?php echo esc_attr( $brl_per_pt ); ?>" />
                                <?php esc_html_e( 'reais faturados', 'desi-pet-shower' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Resumo de Fidelidade', 'desi-pet-shower' ); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="dps-loyalty" />
                <label for="dps_client_id"><?php esc_html_e( 'Selecionar cliente', 'desi-pet-shower' ); ?></label>
                <select id="dps_client_id" name="dps_client_id">
                    <option value="0"><?php esc_html_e( 'Selecione um cliente', 'desi-pet-shower' ); ?></option>
                    <?php foreach ( $clients as $client ) : ?>
                        <option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $selected_id, $client->ID ); ?>><?php echo esc_html( $client->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Filtrar', 'desi-pet-shower' ), 'secondary', '', false ); ?>
            </form>
            <?php if ( $selected_id ) : ?>
                <p><strong><?php esc_html_e( 'Pontos acumulados:', 'desi-pet-shower' ); ?></strong> <?php echo esc_html( dps_loyalty_get_points( $selected_id ) ); ?></p>
                <?php if ( ! empty( $logs ) ) : ?>
                    <h3><?php esc_html_e( 'Histórico recente', 'desi-pet-shower' ); ?></h3>
                    <ul>
                        <?php foreach ( $logs as $entry ) : ?>
                            <li><?php echo esc_html( sprintf( '%1$s: %2$s pontos (%3$s)', $entry['date'], $entry['points'], $entry['context'] ) ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e( 'Nenhum histórico disponível.', 'desi-pet-shower' ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <hr />
            <h2><?php esc_html_e( 'Rotinas de Campanhas', 'desi-pet-shower' ); ?></h2>
            <p><?php esc_html_e( 'Execute uma varredura para identificar clientes elegíveis e registrar ofertas pendentes.', 'desi-pet-shower' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'dps_loyalty_run_audit', 'dps_loyalty_run_audit_nonce' ); ?>
                <input type="hidden" name="action" value="dps_loyalty_run_audit" />
                <?php submit_button( __( 'Rodar rotina de elegibilidade', 'desi-pet-shower' ), 'primary', 'dps_loyalty_run_audit_btn', false ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_campaign_audit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        if ( ! isset( $_POST['dps_loyalty_run_audit_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dps_loyalty_run_audit_nonce'] ), 'dps_loyalty_run_audit' ) ) {
            wp_die( __( 'Nonce inválido.', 'desi-pet-shower' ) );
        }

        $campaigns = get_posts( [
            'post_type'      => 'dps_campaign',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );

        foreach ( $campaigns as $campaign ) {
            if ( ! $this->is_campaign_within_date_window( $campaign->ID ) ) {
                continue;
            }

            $eligible_clients = $this->find_eligible_clients_for_campaign( $campaign->ID );
            update_post_meta( $campaign->ID, 'dps_campaign_pending_offers', $eligible_clients );
            update_post_meta( $campaign->ID, 'dps_campaign_last_audit', current_time( 'mysql' ) );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'dps-loyalty', 'audit' => 'done' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function find_eligible_clients_for_campaign( $campaign_id ) {
        $eligibility      = get_post_meta( $campaign_id, 'dps_campaign_eligibility', true );
        $inactive_days    = absint( get_post_meta( $campaign_id, 'dps_campaign_inactive_days', true ) );
        $points_threshold = absint( get_post_meta( $campaign_id, 'dps_campaign_points_threshold', true ) );
        $eligible_clients = [];

        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $clients as $client_id ) {
            $passes_inactive = ! empty( $eligibility ) && in_array( 'inactive', (array) $eligibility, true )
                ? $this->is_client_inactive_for_days( $client_id, $inactive_days )
                : false;
            $passes_points   = ! empty( $eligibility ) && in_array( 'points', (array) $eligibility, true )
                ? dps_loyalty_get_points( $client_id ) >= $points_threshold
                : false;

            if ( ( $passes_inactive || $passes_points ) && ! in_array( $client_id, $eligible_clients, true ) ) {
                $eligible_clients[] = $client_id;
            }
        }

        return $eligible_clients;
    }

    private function is_campaign_within_date_window( $campaign_id ) {
        $start_date = get_post_meta( $campaign_id, 'dps_campaign_start_date', true );
        $end_date   = get_post_meta( $campaign_id, 'dps_campaign_end_date', true );
        $now        = current_time( 'timestamp' );

        if ( ! empty( $start_date ) ) {
            $start_timestamp = strtotime( $start_date . ' 00:00:00' );
            if ( $start_timestamp && $start_timestamp > $now ) {
                return false;
            }
        }

        if ( ! empty( $end_date ) ) {
            $end_timestamp = strtotime( $end_date . ' 23:59:59' );
            if ( $end_timestamp && $end_timestamp < $now ) {
                return false;
            }
        }

        return true;
    }

    private function is_client_inactive_for_days( $client_id, $days ) {
        if ( $days <= 0 ) {
            return false;
        }

        $last_date = $this->get_last_appointment_date_for_client( $client_id );
        if ( ! $last_date ) {
            return true;
        }

        $interval = ( time() - strtotime( $last_date ) ) / DAY_IN_SECONDS;
        return $interval >= $days;
    }

    private function get_last_appointment_date_for_client( $client_id ) {
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => 'appointment_date',
            'meta_query'     => [
                [
                    'key'   => 'appointment_client_id',
                    'value' => $client_id,
                ],
            ],
        ] );

        if ( empty( $appointments ) ) {
            return '';
        }

        return get_post_meta( $appointments[0]->ID, 'appointment_date', true );
    }

    public function register_settings() {
        register_setting( 'dps_loyalty_settings_group', self::OPTION_KEY, [ $this, 'sanitize_settings' ] );
    }

    public function sanitize_settings( $input ) {
        $output                  = [];
        $output['brl_per_point'] = isset( $input['brl_per_point'] ) ? (float) $input['brl_per_point'] : 10.0;
        if ( $output['brl_per_point'] <= 0 ) {
            $output['brl_per_point'] = 10.0;
        }
        return $output;
    }

    public function maybe_award_points_on_status_change( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( 'appointment_status' !== $meta_key || 'finalizado_pago' !== $meta_value ) {
            return;
        }

        if ( get_post_type( $object_id ) !== 'dps_agendamento' ) {
            return;
        }

        if ( get_post_meta( $object_id, 'dps_loyalty_points_awarded', true ) ) {
            return;
        }

        $client_id = (int) get_post_meta( $object_id, 'appointment_client_id', true );
        if ( ! $client_id ) {
            return;
        }

        $total_value = (float) get_post_meta( $object_id, 'appointment_total_value', true );
        if ( $total_value <= 0 ) {
            $total_value = $this->get_transaction_total_for_appointment( $object_id );
        }

        $points = $this->calculate_points_from_value( $total_value );
        if ( $points > 0 ) {
            dps_loyalty_add_points( $client_id, $points, 'appointment_payment' );
            update_post_meta( $object_id, 'dps_loyalty_points_awarded', 1 );
        }
    }

    private function calculate_points_from_value( $value ) {
        $settings     = get_option( self::OPTION_KEY, [] );
        $brl_per_pt   = isset( $settings['brl_per_point'] ) && $settings['brl_per_point'] > 0 ? (float) $settings['brl_per_point'] : 10.0;
        $points_float = $value > 0 ? floor( $value / $brl_per_pt ) : 0;
        return (int) $points_float;
    }

    private function get_transaction_total_for_appointment( $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $total = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$table} WHERE agendamento_id = %d AND status = %s", $appointment_id, 'pago' ) );
        return $total ? (float) $total : 0.0;
    }
}

dps_loyalty_init();

function dps_loyalty_init() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new DPS_Loyalty_Addon();
        add_action( 'admin_init', [ $instance, 'register_settings' ] );
    }

    return $instance;
}

function dps_loyalty_add_points( $client_id, $points, $context = '' ) {
    $client_id = (int) $client_id;
    $points    = (int) $points;

    if ( $client_id <= 0 || $points <= 0 ) {
        return false;
    }

    $current = dps_loyalty_get_points( $client_id );
    $new     = $current + $points;

    update_post_meta( $client_id, 'dps_loyalty_points', $new );
    dps_loyalty_log_event( $client_id, 'add', $points, $context );

    do_action( 'dps_loyalty_points_added', $client_id, $points, $context );

    return $new;
}

function dps_loyalty_get_points( $client_id ) {
    $client_id = (int) $client_id;
    if ( $client_id <= 0 ) {
        return 0;
    }

    $points = get_post_meta( $client_id, 'dps_loyalty_points', true );
    $points = $points ? (int) $points : 0;

    return max( 0, $points );
}

function dps_loyalty_redeem_points( $client_id, $points, $context = '' ) {
    $client_id = (int) $client_id;
    $points    = (int) $points;

    if ( $client_id <= 0 || $points <= 0 ) {
        return false;
    }

    $current = dps_loyalty_get_points( $client_id );
    if ( $points > $current ) {
        return false;
    }

    $new = $current - $points;
    update_post_meta( $client_id, 'dps_loyalty_points', $new );
    dps_loyalty_log_event( $client_id, 'redeem', $points, $context );

    do_action( 'dps_loyalty_points_redeemed', $client_id, $points, $context );

    return $new;
}

function dps_loyalty_log_event( $client_id, $action, $points, $context = '' ) {
    $entry = [
        'action'  => $action,
        'points'  => (int) $points,
        'context' => sanitize_text_field( $context ),
        'date'    => current_time( 'mysql' ),
    ];

    add_post_meta( $client_id, 'dps_loyalty_points_log', $entry );
}

function dps_loyalty_get_logs( $client_id, $limit = 10 ) {
    $client_id = (int) $client_id;
    if ( $client_id <= 0 ) {
        return [];
    }

    $logs = get_post_meta( $client_id, 'dps_loyalty_points_log' );
    if ( empty( $logs ) ) {
        return [];
    }

    $logs = array_reverse( $logs );
    return array_slice( $logs, 0, $limit );
}
