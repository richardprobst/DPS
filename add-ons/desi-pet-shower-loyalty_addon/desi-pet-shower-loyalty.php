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

    /**
     * Helper para registrar o CPT de campanhas.
     *
     * @var DPS_CPT_Helper|null
     */
    private $cpt_helper;

    public function __construct() {
        if ( ! class_exists( 'DPS_CPT_Helper' ) && defined( 'DPS_BASE_DIR' ) ) {
            require_once DPS_BASE_DIR . 'includes/class-dps-cpt-helper.php';
        }

        if ( class_exists( 'DPS_CPT_Helper' ) ) {
            $this->cpt_helper = new DPS_CPT_Helper(
                'dps_campaign',
                [
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
                ],
                [
                    'public'          => false,
                    'show_ui'         => true,
                    'show_in_menu'    => false,
                    'supports'        => [ 'title', 'editor' ],
                    'capability_type' => 'post',
                    'map_meta_cap'    => true,
                    'has_archive'     => false,
                ]
            );
        }

        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_campaign_metaboxes' ] );
        add_action( 'save_post_dps_campaign', [ $this, 'save_campaign_meta' ] );
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_dps_loyalty_run_audit', [ $this, 'handle_campaign_audit' ] );
        add_action( 'updated_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'maybe_award_points_on_status_change' ], 10, 4 );
    }

    public function register_post_type() {
        if ( ! $this->cpt_helper ) {
            return;
        }

        $this->cpt_helper->register();
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
        
        <fieldset style="border: 1px solid #e5e7eb; padding: 16px; margin: 16px 0; border-radius: 4px;">
            <legend style="font-weight: 600; color: #374151; padding: 0 8px;"><strong><?php esc_html_e( 'Critérios de elegibilidade', 'desi-pet-shower' ); ?></strong></legend>
            <p>
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
        </fieldset>
        
        <fieldset style="border: 1px solid #e5e7eb; padding: 16px; margin: 16px 0; border-radius: 4px;">
            <legend style="font-weight: 600; color: #374151; padding: 0 8px;"><strong><?php esc_html_e( 'Período da campanha', 'desi-pet-shower' ); ?></strong></legend>
            <p>
                <label for="dps_campaign_start_date"><strong><?php esc_html_e( 'Início', 'desi-pet-shower' ); ?></strong></label>
                <input type="date" id="dps_campaign_start_date" name="dps_campaign_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
            </p>
            <p>
                <label for="dps_campaign_end_date"><strong><?php esc_html_e( 'Fim', 'desi-pet-shower' ); ?></strong></label>
                <input type="date" id="dps_campaign_end_date" name="dps_campaign_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
            </p>
        </fieldset>
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
        // Submenu dentro do menu principal "Desi Pet Shower" (criado pelo plugin base)
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

        // Implementa paginação para melhor performance com muitos clientes.
        $per_page = 100;
        $paged    = isset( $_GET['loyalty_page'] ) ? max( 1, absint( $_GET['loyalty_page'] ) ) : 1;

        $clients_query = new WP_Query( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $clients = $clients_query->posts;
        $total_pages = $clients_query->max_num_pages;

        $logs = $selected_id ? dps_loyalty_get_logs( $selected_id ) : [];
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

            <?php
            // Renderiza paginação de clientes se houver múltiplas páginas.
            if ( $total_pages > 1 ) {
                echo '<div class="dps-pagination" style="margin: 10px 0;">';
                $prev_page = $paged > 1 ? $paged - 1 : 0;
                $next_page = $paged < $total_pages ? $paged + 1 : 0;

                // Preserva o filtro de cliente selecionado nos links de paginação.
                $base_url = admin_url( 'admin.php?page=dps-loyalty' );
                if ( $selected_id ) {
                    $base_url = add_query_arg( 'dps_client_id', $selected_id, $base_url );
                }

                if ( $prev_page ) {
                    printf(
                        '<a class="button" href="%s">&laquo; %s</a> ',
                        esc_url( add_query_arg( 'loyalty_page', $prev_page, $base_url ) ),
                        esc_html__( 'Anterior', 'desi-pet-shower' )
                    );
                }

                printf(
                    '<span>%s</span>',
                    esc_html( sprintf( __( 'Página %d de %d', 'desi-pet-shower' ), $paged, $total_pages ) )
                );

                if ( $next_page ) {
                    printf(
                        ' <a class="button" href="%s">%s &raquo;</a>',
                        esc_url( add_query_arg( 'loyalty_page', $next_page, $base_url ) ),
                        esc_html__( 'Próxima', 'desi-pet-shower' )
                    );
                }
                echo '</div>';
            }
            ?>

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

        // Limite de campanhas processadas em uma única execução.
        $campaigns = get_posts( [
            'post_type'      => 'dps_campaign',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ] );

        foreach ( $campaigns as $campaign ) {
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

        // Limite de clientes processados por campanha (500 clientes).
        // Para bases maiores, considerar processamento em background via cron job.
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 500,
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

        add_settings_section(
            'dps_loyalty_referrals_section',
            __( 'Indique e Ganhe', 'desi-pet-shower' ),
            [ $this, 'render_referrals_section_intro' ],
            'dps_loyalty_settings_page'
        );

        add_settings_field(
            'dps_loyalty_referrals_enabled',
            __( 'Ativar programa', 'desi-pet-shower' ),
            [ $this, 'render_referrals_enabled_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referrer_reward',
            __( 'Recompensa do indicador', 'desi-pet-shower' ),
            [ $this, 'render_referrer_reward_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referee_reward',
            __( 'Recompensa do indicado', 'desi-pet-shower' ),
            [ $this, 'render_referee_reward_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );

        add_settings_field(
            'dps_loyalty_referrals_rules',
            __( 'Regras gerais', 'desi-pet-shower' ),
            [ $this, 'render_referrals_rules_field' ],
            'dps_loyalty_settings_page',
            'dps_loyalty_referrals_section'
        );
    }

    public function sanitize_settings( $input ) {
        $output                               = [];
        $output['brl_per_point']              = isset( $input['brl_per_point'] ) ? (float) $input['brl_per_point'] : 10.0;
        $output['referrals_enabled']          = ! empty( $input['referrals_enabled'] ) ? 1 : 0;
        $output['referrer_reward_type']       = isset( $input['referrer_reward_type'] ) ? sanitize_text_field( $input['referrer_reward_type'] ) : 'none';
        $output['referrer_reward_value']      = isset( $input['referrer_reward_value'] ) ? $this->sanitize_reward_value( $input['referrer_reward_value'], $output['referrer_reward_type'] ) : 0;
        $output['referee_reward_type']        = isset( $input['referee_reward_type'] ) ? sanitize_text_field( $input['referee_reward_type'] ) : 'none';
        $output['referee_reward_value']       = isset( $input['referee_reward_value'] ) ? $this->sanitize_reward_value( $input['referee_reward_value'], $output['referee_reward_type'] ) : 0;
        $output['referrals_minimum_amount']   = isset( $input['referrals_minimum_amount'] ) ? dps_loyalty_parse_money_br( $input['referrals_minimum_amount'] ) : 0;
        $output['referrals_max_per_referrer'] = isset( $input['referrals_max_per_referrer'] ) ? absint( $input['referrals_max_per_referrer'] ) : 0;
        $output['referrals_first_purchase']   = ! empty( $input['referrals_first_purchase'] ) ? 1 : 0;

        if ( $output['brl_per_point'] <= 0 ) {
            $output['brl_per_point'] = 10.0;
        }
        return $output;
    }

    public function render_referrals_section_intro() {
        echo '<p>' . esc_html__( 'Configure as regras do programa de indicações, incluindo recompensas e limites.', 'desi-pet-shower' ) . '</p>';
    }

    public function render_referrals_enabled_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $enabled  = ! empty( $settings['referrals_enabled'] );
        echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[referrals_enabled]" value="1" ' . checked( $enabled, true, false ) . ' /> ' . esc_html__( 'Ativar programa Indique e Ganhe', 'desi-pet-shower' ) . '</label>';
    }

    public function render_referrer_reward_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $type     = isset( $settings['referrer_reward_type'] ) ? $settings['referrer_reward_type'] : 'none';
        $value    = isset( $settings['referrer_reward_value'] ) ? $settings['referrer_reward_value'] : 0;
        $this->render_reward_selector( 'referrer', $type, $value );
    }

    public function render_referee_reward_field() {
        $settings = get_option( self::OPTION_KEY, [] );
        $type     = isset( $settings['referee_reward_type'] ) ? $settings['referee_reward_type'] : 'none';
        $value    = isset( $settings['referee_reward_value'] ) ? $settings['referee_reward_value'] : 0;
        $this->render_reward_selector( 'referee', $type, $value );
    }

    public function render_referrals_rules_field() {
        $settings       = get_option( self::OPTION_KEY, [] );
        $minimum_amount = isset( $settings['referrals_minimum_amount'] ) ? (int) $settings['referrals_minimum_amount'] : 0;
        $max_referrals  = isset( $settings['referrals_max_per_referrer'] ) ? absint( $settings['referrals_max_per_referrer'] ) : 0;
        $first_purchase = ! empty( $settings['referrals_first_purchase'] );
        ?>
        <p>
            <label>
                <?php esc_html_e( 'Valor mínimo do primeiro atendimento para liberar recompensa (R$)', 'desi-pet-shower' ); ?><br />
                <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_minimum_amount]" value="<?php echo esc_attr( dps_format_money_br( $minimum_amount ) ); ?>" />
            </label>
        </p>
        <p>
            <label>
                <?php esc_html_e( 'Máximo de indicações recompensadas por cliente (0 para ilimitado)', 'desi-pet-shower' ); ?><br />
                <input type="number" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_max_per_referrer]" value="<?php echo esc_attr( $max_referrals ); ?>" />
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[referrals_first_purchase]" value="1" <?php checked( $first_purchase ); ?> />
                <?php esc_html_e( 'Somente a primeira compra conta', 'desi-pet-shower' ); ?>
            </label>
        </p>
        <?php
    }

    private function render_reward_selector( $role, $type, $value ) {
        $type_key  = self::OPTION_KEY . '[' . $role . '_reward_type]';
        $value_key = self::OPTION_KEY . '[' . $role . '_reward_value]';
        ?>
        <fieldset>
            <label for="<?php echo esc_attr( $type_key ); ?>">
                <select id="<?php echo esc_attr( $type_key ); ?>" name="<?php echo esc_attr( $type_key ); ?>">
                    <option value="none" <?php selected( $type, 'none' ); ?>><?php esc_html_e( 'Sem recompensa', 'desi-pet-shower' ); ?></option>
                    <option value="points" <?php selected( $type, 'points' ); ?>><?php esc_html_e( 'Pontos de fidelidade', 'desi-pet-shower' ); ?></option>
                    <option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Crédito fixo (R$)', 'desi-pet-shower' ); ?></option>
                    <option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Crédito percentual', 'desi-pet-shower' ); ?></option>
                </select>
            </label>
            <input type="text" name="<?php echo esc_attr( $value_key ); ?>" value="<?php echo esc_attr( $this->format_reward_value( $value, $type ) ); ?>" placeholder="<?php esc_attr_e( 'Valor', 'desi-pet-shower' ); ?>" />
        </fieldset>
        <?php
    }

    private function sanitize_reward_value( $value, $type ) {
        if ( 'points' === $type ) {
            return absint( $value );
        }

        if ( 'fixed' === $type ) {
            return dps_loyalty_parse_money_br( $value );
        }

        if ( 'percent' === $type ) {
            return (float) $value;
        }

        return 0;
    }

    private function format_reward_value( $value, $type ) {
        if ( 'fixed' === $type ) {
            return dps_format_money_br( (int) $value );
        }

        return $value;
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

class DPS_Loyalty_Referrals {

    const DB_VERSION = '1.0.0';

    private static $instance = null;

    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dps_referrals';

        add_action( 'init', [ $this, 'maybe_create_table' ] );
        add_action( 'save_post_dps_cliente', [ $this, 'ensure_referral_code' ], 10, 3 );
        add_action( 'dps_registration_after_fields', [ $this, 'render_registration_field' ] );
        add_action( 'dps_registration_after_client_created', [ $this, 'maybe_register_referral' ], 10, 4 );
        add_action( 'dps_finance_booking_paid', [ $this, 'handle_booking_paid' ], 10, 3 );
    }

    public static function install() {
        self::get_instance()->create_table();
    }

    public function maybe_create_table() {
        $installed = get_option( 'dps_referrals_db_version', '' );
        if ( self::DB_VERSION !== $installed ) {
            $this->create_table();
        }
    }

    private function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_client_id BIGINT(20) unsigned NOT NULL,
            referee_client_id BIGINT(20) unsigned NULL,
            referral_code VARCHAR(50) NOT NULL,
            first_booking_id BIGINT(20) unsigned NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            reward_type_referrer VARCHAR(20) NULL,
            reward_value_referrer DECIMAL(12,2) NULL,
            reward_type_referee VARCHAR(20) NULL,
            reward_value_referee DECIMAL(12,2) NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY referrer_idx (referrer_client_id),
            KEY referee_idx (referee_client_id),
            KEY code_idx (referral_code)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'dps_referrals_db_version', self::DB_VERSION );
    }

    public function ensure_referral_code( $post_id, $post, $update ) {
        if ( $update ) {
            return;
        }

        dps_loyalty_get_referral_code( $post_id );
    }

    public function render_registration_field() {
        $referral_param = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
        ?>
        <p class="dps-referral-field">
            <label><?php esc_html_e( 'Código de indicação (opcional)', 'desi-pet-shower' ); ?><br />
                <input type="text" name="dps_referral_code" value="<?php echo esc_attr( $referral_param ); ?>" maxlength="20" />
            </label>
        </p>
        <?php
    }

    public function maybe_register_referral( $referral_code, $new_client_id, $client_email, $client_phone ) {
        if ( ! $referral_code || ! $new_client_id ) {
            return;
        }

        $settings = dps_referrals_get_settings();
        if ( empty( $settings['referrals_enabled'] ) ) {
            return;
        }

        $referrer_id = $this->get_client_id_by_referral_code( $referral_code );

        if ( ! $referrer_id || $referrer_id === $new_client_id ) {
            return;
        }

        if ( $this->is_existing_client_contact( $client_email, $client_phone ) ) {
            return;
        }

        dps_referrals_create( [
            'referrer_client_id' => $referrer_id,
            'referee_client_id'  => $new_client_id,
            'referral_code'      => $referral_code,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql' ),
        ] );
    }

    public function handle_booking_paid( $appointment_id, $client_id, $amount_in_cents ) {
        $settings = dps_referrals_get_settings();
        if ( empty( $settings['referrals_enabled'] ) ) {
            return;
        }

        $pending = dps_referrals_find_pending_by_referee( $client_id );
        if ( ! $pending ) {
            return;
        }

        if ( (int) $pending->referrer_client_id === (int) $client_id ) {
            error_log( 'DPS Referrals: tentativa de autopromoção ignorada para cliente ' . $client_id );
            return;
        }

        if ( $settings['referrals_minimum_amount'] > 0 && $amount_in_cents < (int) $settings['referrals_minimum_amount'] ) {
            return;
        }

        if ( ! empty( $settings['referrals_first_purchase'] ) && $this->client_has_previous_paid_booking( $client_id, $appointment_id ) ) {
            return;
        }

        if ( $this->has_referrer_reached_limit( $pending->referrer_client_id, $settings['referrals_max_per_referrer'] ) ) {
            return;
        }

        $rewards_applied = $this->apply_rewards( $pending, $amount_in_cents );

        dps_referrals_mark_rewarded(
            $pending->id,
            $appointment_id,
            [
                'reward_type_referrer' => $rewards_applied['referrer_type'],
                'reward_value_referrer' => $rewards_applied['referrer_value'],
                'reward_type_referee' => $rewards_applied['referee_type'],
                'reward_value_referee' => $rewards_applied['referee_value'],
            ]
        );
    }

    private function apply_rewards( $referral, $amount_in_cents ) {
        $settings       = dps_referrals_get_settings();
        $rewards_applied = [
            'referrer_type' => 'none',
            'referrer_value' => 0,
            'referee_type' => 'none',
            'referee_value' => 0,
        ];

        if ( ! empty( $settings['referrer_reward_type'] ) && 'none' !== $settings['referrer_reward_type'] ) {
            $rewards_applied['referrer_type']  = $settings['referrer_reward_type'];
            $rewards_applied['referrer_value'] = $this->apply_single_reward( $referral->referrer_client_id, $settings['referrer_reward_type'], $settings['referrer_reward_value'], $amount_in_cents );
        }

        if ( ! empty( $settings['referee_reward_type'] ) && 'none' !== $settings['referee_reward_type'] ) {
            $rewards_applied['referee_type']  = $settings['referee_reward_type'];
            $rewards_applied['referee_value'] = $this->apply_single_reward( $referral->referee_client_id, $settings['referee_reward_type'], $settings['referee_reward_value'], $amount_in_cents );
        }

        return $rewards_applied;
    }

    private function apply_single_reward( $client_id, $type, $value, $amount_in_cents ) {
        if ( 'points' === $type ) {
            dps_loyalty_add_points( $client_id, (int) $value, 'referral_reward' );
            return (int) $value;
        }

        if ( 'fixed' === $type ) {
            dps_loyalty_add_credit( $client_id, (int) $value, 'referral_reward' );
            return (int) $value;
        }

        if ( 'percent' === $type ) {
            $calculated = (int) floor( (float) $value * $amount_in_cents / 100 );
            if ( $calculated > 0 ) {
                dps_loyalty_add_credit( $client_id, $calculated, 'referral_reward' );
            }
            return $calculated;
        }

        return 0;
    }

    private function client_has_previous_paid_booking( $client_id, $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE cliente_id = %d AND status = %s AND agendamento_id <> %d", $client_id, 'pago', $appointment_id ) );
        return $count > 0;
    }

    private function has_referrer_reached_limit( $referrer_id, $limit ) {
        if ( ! $limit ) {
            return false;
        }

        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE referrer_client_id = %d AND status = %s", $referrer_id, 'rewarded' ) );
        return $count >= $limit;
    }

    private function get_client_id_by_referral_code( $code ) {
        $client = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_referral_code',
                    'value' => $code,
                ],
            ],
        ] );

        if ( empty( $client ) ) {
            return 0;
        }

        return (int) $client[0];
    }

    private function is_existing_client_contact( $email, $phone ) {
        $meta_query = [ 'relation' => 'OR' ];

        if ( $email ) {
            $meta_query[] = [
                'key'     => 'client_email',
                'value'   => $email,
                'compare' => '=',
            ];
        }

        if ( $phone ) {
            $meta_query[] = [
                'key'     => 'client_phone',
                'value'   => $phone,
                'compare' => '=',
            ];
        }

        if ( count( $meta_query ) === 1 ) {
            return false;
        }

        $existing = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ] );

        return ! empty( $existing );
    }
}

if ( ! function_exists( 'dps_loyalty_init' ) ) {
    function dps_loyalty_init() {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new DPS_Loyalty_Addon();
            add_action( 'admin_init', [ $instance, 'register_settings' ] );
            DPS_Loyalty_Referrals::get_instance();
        }

        return $instance;
    }
}

add_action( 'plugins_loaded', 'dps_loyalty_init' );

register_activation_hook( __FILE__, [ 'DPS_Loyalty_Referrals', 'install' ] );

if ( ! function_exists( 'dps_loyalty_add_points' ) ) {
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
}

if ( ! function_exists( 'dps_loyalty_get_points' ) ) {
    function dps_loyalty_get_points( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return 0;
        }

        $points = get_post_meta( $client_id, 'dps_loyalty_points', true );
        $points = $points ? (int) $points : 0;

        return max( 0, $points );
    }
}

if ( ! function_exists( 'dps_loyalty_redeem_points' ) ) {
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
}

if ( ! function_exists( 'dps_loyalty_log_event' ) ) {
    function dps_loyalty_log_event( $client_id, $action, $points, $context = '' ) {
        $entry = [
            'action'  => $action,
            'points'  => (int) $points,
            'context' => sanitize_text_field( $context ),
            'date'    => current_time( 'mysql' ),
        ];

        add_post_meta( $client_id, 'dps_loyalty_points_log', $entry );
    }
}

if ( ! function_exists( 'dps_loyalty_get_logs' ) ) {
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
}

if ( ! function_exists( 'dps_loyalty_parse_money_br' ) ) {
    function dps_loyalty_parse_money_br( $value ) {
        $raw = trim( (string) $value );
        if ( '' === $raw ) {
            return 0;
        }

        $normalized = preg_replace( '/[^0-9,.-]/', '', $raw );
        $normalized = str_replace( ' ', '', $normalized );
        if ( strpos( $normalized, ',' ) !== false ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }

        $float = (float) $normalized;
        return (int) round( $float * 100 );
    }
}

if ( ! function_exists( 'dps_format_money_br' ) ) {
    /**
     * Formata um valor em centavos para string no padrão brasileiro.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::format_to_brazilian() instead.
     * @param int $int Valor em centavos.
     * @return string Valor formatado.
     */
    function dps_format_money_br( $int ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::format_to_brazilian()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::format_to_brazilian( $int );
        }
        // Fallback se helper não disponível
        $float = (int) $int / 100;
        return number_format( $float, 2, ',', '.' );
    }
}

if ( ! function_exists( 'dps_loyalty_generate_referral_code' ) ) {
    function dps_loyalty_generate_referral_code( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return '';
        }

        $existing = get_post_meta( $client_id, '_dps_referral_code', true );
        if ( $existing ) {
            return $existing;
        }

        $attempts = 0;
        $code     = '';
        do {
            $attempts++;
            $code = strtoupper( wp_generate_password( 8, false, false ) );
        } while ( $attempts < 5 && dps_referral_code_exists( $code ) );

        update_post_meta( $client_id, '_dps_referral_code', $code );
        return $code;
    }
}

if ( ! function_exists( 'dps_loyalty_get_referral_code' ) ) {
    function dps_loyalty_get_referral_code( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return '';
        }

        $code = get_post_meta( $client_id, '_dps_referral_code', true );
        if ( ! $code ) {
            $code = dps_loyalty_generate_referral_code( $client_id );
        }

        return $code;
    }
}

if ( ! function_exists( 'dps_referral_code_exists' ) ) {
    function dps_referral_code_exists( $code ) {
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_dps_referral_code',
                    'value' => $code,
                ],
            ],
        ] );

        return ! empty( $clients );
    }
}

if ( ! function_exists( 'dps_referrals_create' ) ) {
    function dps_referrals_create( $data ) {
        global $wpdb;

        $defaults = [
            'referrer_client_id' => 0,
            'referee_client_id'  => null,
            'referral_code'      => '',
            'first_booking_id'   => null,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql' ),
            'reward_type_referrer' => null,
            'reward_value_referrer' => null,
            'reward_type_referee' => null,
            'reward_value_referee' => null,
            'meta'               => null,
        ];

        $data = wp_parse_args( $data, $defaults );

        $wpdb->insert(
            $wpdb->prefix . 'dps_referrals',
            $data,
            [ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s' ]
        );

        return $wpdb->insert_id;
    }
}

if ( ! function_exists( 'dps_referrals_find_pending_by_referee' ) ) {
    function dps_referrals_find_pending_by_referee( $referee_client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE referee_client_id = %d AND status = %s ORDER BY created_at ASC LIMIT 1", $referee_client_id, 'pending' ) );
    }
}

if ( ! function_exists( 'dps_referrals_mark_rewarded' ) ) {
    function dps_referrals_mark_rewarded( $referral_id, $first_booking_id, $reward_data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_referrals';
        $data  = [
            'status'             => 'rewarded',
            'first_booking_id'   => $first_booking_id,
            'reward_type_referrer' => isset( $reward_data['reward_type_referrer'] ) ? sanitize_text_field( $reward_data['reward_type_referrer'] ) : null,
            'reward_value_referrer' => isset( $reward_data['reward_value_referrer'] ) ? $reward_data['reward_value_referrer'] : null,
            'reward_type_referee' => isset( $reward_data['reward_type_referee'] ) ? sanitize_text_field( $reward_data['reward_type_referee'] ) : null,
            'reward_value_referee' => isset( $reward_data['reward_value_referee'] ) ? $reward_data['reward_value_referee'] : null,
        ];

        $wpdb->update( $table, $data, [ 'id' => $referral_id ], [ '%s', '%d', '%s', '%f', '%s', '%f' ], [ '%d' ] );
    }
}

if ( ! function_exists( 'dps_referrals_get_settings' ) ) {
    function dps_referrals_get_settings() {
        $settings = get_option( DPS_Loyalty_Addon::OPTION_KEY, [] );
        $defaults = [
            'referrals_enabled'          => 0,
            'referrer_reward_type'       => 'none',
            'referrer_reward_value'      => 0,
            'referee_reward_type'        => 'none',
            'referee_reward_value'       => 0,
            'referrals_minimum_amount'   => 0,
            'referrals_max_per_referrer' => 0,
            'referrals_first_purchase'   => 0,
        ];

        return wp_parse_args( $settings, $defaults );
    }
}

if ( ! function_exists( 'dps_referrals_register_signup' ) ) {
    function dps_referrals_register_signup( $referral_code, $new_client_id, $client_email = '', $client_phone = '' ) {
        $instance = DPS_Loyalty_Referrals::get_instance();
        $instance->maybe_register_referral( $referral_code, $new_client_id, $client_email, $client_phone );
    }
}

if ( ! function_exists( 'dps_loyalty_add_credit' ) ) {
    function dps_loyalty_add_credit( $client_id, $amount_in_cents, $context = '' ) {
        $client_id        = (int) $client_id;
        $amount_in_cents  = (int) $amount_in_cents;

        if ( $client_id <= 0 || $amount_in_cents <= 0 ) {
            return 0;
        }

        $current = dps_loyalty_get_credit( $client_id );
        $new     = $current + $amount_in_cents;
        update_post_meta( $client_id, '_dps_credit_balance', $new );
        dps_loyalty_log_event( $client_id, 'credit_add', $amount_in_cents, $context );
        return $new;
    }
}

if ( ! function_exists( 'dps_loyalty_get_credit' ) ) {
    function dps_loyalty_get_credit( $client_id ) {
        $client_id = (int) $client_id;
        if ( $client_id <= 0 ) {
            return 0;
        }

        $balance = get_post_meta( $client_id, '_dps_credit_balance', true );
        return $balance ? (int) $balance : 0;
    }
}

if ( ! function_exists( 'dps_loyalty_use_credit' ) ) {
    function dps_loyalty_use_credit( $client_id, $amount_in_cents, $context = '' ) {
        $client_id       = (int) $client_id;
        $amount_in_cents = (int) $amount_in_cents;

        if ( $client_id <= 0 || $amount_in_cents <= 0 ) {
            return 0;
        }

        $current = dps_loyalty_get_credit( $client_id );
        $amount  = min( $current, $amount_in_cents );
        $new     = $current - $amount;
        update_post_meta( $client_id, '_dps_credit_balance', $new );
        dps_loyalty_log_event( $client_id, 'credit_use', $amount, $context );
        return $amount;
    }
}
