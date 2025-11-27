<?php
/**
 * Plugin Name:       Desi Pet Shower – Groomers Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para cadastrar groomers, vincular atendimentos e gerar relatórios por profissional.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-groomers-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base Desi Pet Shower está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_groomers_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Groomers requer o plugin base Desi Pet Shower para funcionar.', 'dps-groomers-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_groomers_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Groomers Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_groomers_load_textdomain() {
    load_plugin_textdomain( 'dps-groomers-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_groomers_load_textdomain', 1 );

class DPS_Groomers_Addon {

    /**
     * Inicializa hooks do add-on.
     */
    public function __construct() {
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_groomers_tab' ], 15, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_groomers_section' ], 15, 1 );
        add_action( 'dps_base_appointment_fields', [ $this, 'render_appointment_groomer_field' ], 10, 2 );
        add_action( 'dps_base_after_save_appointment', [ $this, 'save_appointment_groomers' ], 10, 2 );
    }

    /**
     * Adiciona o papel dps_groomer na ativação.
     */
    public static function activate() {
        add_role(
            'dps_groomer',
            __( 'Groomer DPS', 'dps-groomers-addon' ),
            [ 'read' => true ]
        );
    }

    /**
     * Recupera lista de groomers cadastrados.
     *
     * @return WP_User[]
     */
    private function get_groomers() {
        return get_users(
            [
                'role'    => 'dps_groomer',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]
        );
    }

    /**
     * Processa criação de novos groomers a partir do formulário de administração.
     *
     * @param bool $use_frontend_messages Se true, usa DPS_Message_Helper; se false, usa add_settings_error.
     */
    private function handle_new_groomer_submission( $use_frontend_messages = false ) {
        if ( ! isset( $_POST['dps_new_groomer_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['dps_new_groomer_nonce'] ), 'dps_new_groomer' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $username = isset( $_POST['dps_groomer_username'] ) ? sanitize_user( wp_unslash( $_POST['dps_groomer_username'] ) ) : '';
        $email    = isset( $_POST['dps_groomer_email'] ) ? sanitize_email( wp_unslash( $_POST['dps_groomer_email'] ) ) : '';
        $name     = isset( $_POST['dps_groomer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_groomer_name'] ) ) : '';
        $password = isset( $_POST['dps_groomer_password'] ) ? wp_unslash( $_POST['dps_groomer_password'] ) : '';

        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            $message = __( 'Preencha usuário, email e senha para criar o groomer.', 'dps-groomers-addon' );
            if ( $use_frontend_messages ) {
                DPS_Message_Helper::add_error( $message );
            } elseif ( function_exists( 'add_settings_error' ) ) {
                add_settings_error( 'dps_groomers', 'missing_fields', $message, 'error' );
            }
            return;
        }

        if ( username_exists( $username ) ) {
            $message = __( 'Já existe um usuário com esse login.', 'dps-groomers-addon' );
            if ( $use_frontend_messages ) {
                DPS_Message_Helper::add_error( $message );
            } elseif ( function_exists( 'add_settings_error' ) ) {
                add_settings_error( 'dps_groomers', 'user_exists', $message, 'error' );
            }
            return;
        }

        $user_id = wp_insert_user(
            [
                'user_login' => $username,
                'user_pass'  => $password,
                'user_email' => $email,
                'display_name' => $name,
                'role'       => 'dps_groomer',
            ]
        );

        if ( is_wp_error( $user_id ) ) {
            $message = $user_id->get_error_message();
            if ( $use_frontend_messages ) {
                DPS_Message_Helper::add_error( $message );
            } elseif ( function_exists( 'add_settings_error' ) ) {
                add_settings_error( 'dps_groomers', 'create_error', $message, 'error' );
            }
            return;
        }

        $message = __( 'Groomer criado com sucesso.', 'dps-groomers-addon' );
        if ( $use_frontend_messages ) {
            DPS_Message_Helper::add_success( $message );
        } elseif ( function_exists( 'add_settings_error' ) ) {
            add_settings_error( 'dps_groomers', 'created', $message, 'updated' );
        }
    }

    /**
     * Renderiza a página de gestão de groomers.
     *
     * Exibe formulário para criação de novos groomers e lista de
     * todos os profissionais cadastrados com a role dps_groomer.
     * Processa submissão de formulário e exibe mensagens de feedback.
     *
     * @since 1.0.0
     */
    public function render_groomers_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-groomers-addon' ) );
        }

        $this->handle_new_groomer_submission( false );

        // settings_errors() só existe no contexto admin
        if ( function_exists( 'settings_errors' ) ) {
            settings_errors( 'dps_groomers' );
        }

        $groomers = $this->get_groomers();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Groomers', 'dps-groomers-addon' ); ?></h1>
            <p><?php echo esc_html__( 'Cadastre groomers e visualize todos os profissionais com a role dedicada.', 'dps-groomers-addon' ); ?></p>

            <h2><?php echo esc_html__( 'Adicionar novo groomer', 'dps-groomers-addon' ); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_new_groomer', 'dps_new_groomer_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dps_groomer_username"><?php echo esc_html__( 'Usuário', 'dps-groomers-addon' ); ?></label></th>
                        <td><input name="dps_groomer_username" id="dps_groomer_username" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dps_groomer_name"><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></label></th>
                        <td><input name="dps_groomer_name" id="dps_groomer_name" type="text" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dps_groomer_email"><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?></label></th>
                        <td><input name="dps_groomer_email" id="dps_groomer_email" type="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dps_groomer_password"><?php echo esc_html__( 'Senha', 'dps-groomers-addon' ); ?></label></th>
                        <td><input name="dps_groomer_password" id="dps_groomer_password" type="password" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Criar groomer', 'dps-groomers-addon' ) ); ?>
            </form>

            <h2><?php echo esc_html__( 'Groomers cadastrados', 'dps-groomers-addon' ); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Usuário', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $groomers ) ) : ?>
                    <tr>
                        <td colspan="3"><?php echo esc_html__( 'Nenhum groomer encontrado.', 'dps-groomers-addon' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $groomers as $groomer ) : ?>
                        <tr>
                            <td><?php echo esc_html( $groomer->display_name ); ?></td>
                            <td><?php echo esc_html( $groomer->user_login ); ?></td>
                            <td><?php echo esc_html( $groomer->user_email ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Renderiza o campo de seleção de groomers no formulário de agendamento do plugin base.
     *
     * @param int   $appointment_id ID do agendamento em edição, se existir.
     * @param array $meta           Metadados atuais do agendamento.
     */
    public function render_appointment_groomer_field( $appointment_id, $meta ) {
        $selected = [];
        if ( $appointment_id ) {
            $saved = get_post_meta( $appointment_id, '_dps_groomers', true );
            if ( is_array( $saved ) ) {
                $selected = array_map( 'absint', $saved );
            }
        }

        $groomers = $this->get_groomers();
        echo '<p><label>' . esc_html__( 'Groomers responsáveis', 'dps-groomers-addon' ) . '<br>';
        echo '<select name="dps_groomers[]" multiple size="4" style="min-width:220px;">';
        if ( empty( $groomers ) ) {
            echo '<option value="">' . esc_html__( 'Nenhum groomer cadastrado', 'dps-groomers-addon' ) . '</option>';
        } else {
            foreach ( $groomers as $groomer ) {
                $selected_attr = in_array( $groomer->ID, $selected, true ) ? 'selected' : '';
                echo '<option value="' . esc_attr( $groomer->ID ) . '" ' . esc_attr( $selected_attr ) . '>' . esc_html( $groomer->display_name ? $groomer->display_name : $groomer->user_login ) . '</option>';
            }
        }
        echo '</select>';
        echo '<span class="description">' . esc_html__( 'Selecione um ou mais groomers para este atendimento.', 'dps-groomers-addon' ) . '</span>';
        echo '</label></p>';
    }

    /**
     * Salva os groomers selecionados em um agendamento.
     *
     * @param int    $appointment_id ID do agendamento salvo.
     * @param string $appointment_type Tipo do agendamento (simple/subscription).
     */
    public function save_appointment_groomers( $appointment_id, $appointment_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( ! current_user_can( 'dps_manage_appointments' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $groomers = isset( $_POST['dps_groomers'] ) ? (array) wp_unslash( $_POST['dps_groomers'] ) : [];
        $groomers = array_filter( array_map( 'absint', $groomers ) );

        if ( ! empty( $groomers ) ) {
            $valid_ids = [];
            foreach ( $groomers as $groomer_id ) {
                $user = get_user_by( 'id', $groomer_id );
                if ( $user && in_array( 'dps_groomer', (array) $user->roles, true ) ) {
                    $valid_ids[] = $groomer_id;
                }
            }

            update_post_meta( $appointment_id, '_dps_groomers', $valid_ids );
        } else {
            delete_post_meta( $appointment_id, '_dps_groomers' );
        }
    }

    /**
     * Adiciona aba "Groomers" à navegação do painel principal.
     *
     * Conectado ao hook dps_base_nav_tabs_after_history para injetar
     * a aba de gestão de groomers na interface do plugin base.
     *
     * @since 1.0.0
     *
     * @param bool $visitor_only Se true, está no modo visitante (sem permissões admin).
     */
    public function add_groomers_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            echo '<li><a href="#" class="dps-tab-link" data-tab="groomers">' . esc_html__( 'Groomers', 'dps-groomers-addon' ) . '</a></li>';
        }
    }

    /**
     * Renderiza seção de groomers no painel principal.
     *
     * Conectado ao hook dps_base_sections_after_history para exibir
     * o conteúdo da aba de gestão de groomers.
     *
     * @since 1.0.0
     *
     * @param bool $visitor_only Se true, está no modo visitante (sem permissões admin).
     */
    public function add_groomers_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }

        echo $this->render_groomers_section();
    }

    /**
     * Renderiza aba de groomers dentro da navegação do núcleo.
     *
     * @return string
     */
    private function render_groomers_section() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return '<div class="dps-section" id="dps-section-groomers"><p>' . esc_html__( 'Você não tem permissão para gerenciar groomers.', 'dps-groomers-addon' ) . '</p></div>';
        }

        $this->handle_new_groomer_submission( true );
        $groomers = $this->get_groomers();

        ob_start();
        ?>
        <div class="dps-section" id="dps-section-groomers">
            <h2 style="margin-bottom: 20px; color: #374151;"><?php echo esc_html__( 'Groomers', 'dps-groomers-addon' ); ?></h2>
            <p><?php echo esc_html__( 'Cadastre profissionais, associe-os a atendimentos e acompanhe relatórios por período.', 'dps-groomers-addon' ); ?></p>

            <?php echo DPS_Message_Helper::display_messages(); ?>

            <div style="display:flex; gap:30px; flex-wrap:wrap; margin-top: 24px;">
                <div class="dps-field-group" style="flex:1 1 340px; min-width:300px;">
                    <h3 class="dps-field-group-title"><?php echo esc_html__( 'Adicionar novo groomer', 'dps-groomers-addon' ); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'dps_new_groomer', 'dps_new_groomer_nonce' ); ?>
                        <p>
                            <label for="dps_groomer_username"><?php echo esc_html__( 'Usuário', 'dps-groomers-addon' ); ?></label><br />
                            <input name="dps_groomer_username" id="dps_groomer_username" type="text" class="regular-text" required />
                        </p>
                        <p>
                            <label for="dps_groomer_name"><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></label><br />
                            <input name="dps_groomer_name" id="dps_groomer_name" type="text" class="regular-text" />
                        </p>
                        <p>
                            <label for="dps_groomer_email"><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?></label><br />
                            <input name="dps_groomer_email" id="dps_groomer_email" type="email" class="regular-text" required />
                        </p>
                        <p>
                            <label for="dps_groomer_password"><?php echo esc_html__( 'Senha', 'dps-groomers-addon' ); ?></label><br />
                            <input name="dps_groomer_password" id="dps_groomer_password" type="password" class="regular-text" required />
                        </p>
                        <?php submit_button( __( 'Criar groomer', 'dps-groomers-addon' ), 'primary', 'submit', false ); ?>
                    </form>
                </div>

                <div style="flex:2 1 400px; min-width:300px;">
                    <h3 class="dps-field-group-title"><?php echo esc_html__( 'Groomers cadastrados', 'dps-groomers-addon' ); ?></h3>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Usuário', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $groomers ) ) : ?>
                            <tr>
                                <td colspan="3"><?php echo esc_html__( 'Nenhum groomer encontrado.', 'dps-groomers-addon' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $groomers as $groomer ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $groomer->display_name ); ?></td>
                                    <td><?php echo esc_html( $groomer->user_login ); ?></td>
                                    <td><?php echo esc_html( $groomer->user_email ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr />
            <?php echo $this->render_report_block( $groomers ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza a seção de relatórios.
     *
     * @param WP_User[] $groomers Lista de profissionais.
     * @return string
     */
    private function render_report_block( $groomers ) {
        $selected   = isset( $_GET['dps_report_groomer'] ) ? absint( $_GET['dps_report_groomer'] ) : 0;
        $start_date = isset( $_GET['dps_report_start'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_report_start'] ) ) : '';
        $end_date   = isset( $_GET['dps_report_end'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_report_end'] ) ) : '';

        $appointments = [];
        $total_amount = 0;
        $filters_ok   = isset( $_GET['dps_report_nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['dps_report_nonce'] ), 'dps_report' );

        if ( $filters_ok && $selected && $start_date && $end_date ) {
            // Limite de 500 agendamentos por relatório.
            // Para relatórios maiores, considerar paginação ou exportação em background.
            $appointments = get_posts(
                [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => 500,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        'relation' => 'AND',
                        [
                            'key'     => 'appointment_date',
                            'value'   => $start_date,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                        [
                            'key'     => 'appointment_date',
                            'value'   => $end_date,
                            'compare' => '<=',
                            'type'    => 'DATE',
                        ],
                        [
                            'key'     => '_dps_groomers',
                            'value'   => '"' . $selected . '"',
                            'compare' => 'LIKE',
                        ],
                    ],
                ]
            );

            if ( ! empty( $appointments ) ) {
                global $wpdb;
                $ids          = wp_list_pluck( $appointments, 'ID' );
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $table        = $wpdb->prefix . 'dps_transacoes';

                $total_amount = (float) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(valor) FROM {$table} WHERE status = 'pago' AND tipo = 'receita' AND agendamento_id IN ($placeholders)",
                        $ids
                    )
                );
            }
        }

        ob_start();
        ?>
        <div>
            <h4><?php echo esc_html__( 'Relatório por Groomer', 'dps-groomers-addon' ); ?></h4>
            <form method="get" action="">
                <input type="hidden" name="tab" value="groomers" />
                <?php wp_nonce_field( 'dps_report', 'dps_report_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dps_report_groomer"><?php echo esc_html__( 'Groomer', 'dps-groomers-addon' ); ?></label></th>
                        <td>
                            <select name="dps_report_groomer" id="dps_report_groomer" required>
                                <option value=""><?php echo esc_html__( 'Selecione um groomer', 'dps-groomers-addon' ); ?></option>
                                <?php foreach ( $groomers as $groomer ) : ?>
                                    <option value="<?php echo esc_attr( $groomer->ID ); ?>" <?php selected( $selected, $groomer->ID ); ?>>
                                        <?php echo esc_html( $groomer->display_name ? $groomer->display_name : $groomer->user_login ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dps_report_start"><?php echo esc_html__( 'Data inicial', 'dps-groomers-addon' ); ?></label></th>
                        <td><input type="date" name="dps_report_start" id="dps_report_start" value="<?php echo esc_attr( $start_date ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dps_report_end"><?php echo esc_html__( 'Data final', 'dps-groomers-addon' ); ?></label></th>
                        <td><input type="date" name="dps_report_end" id="dps_report_end" value="<?php echo esc_attr( $end_date ); ?>" required /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Gerar relatório', 'dps-groomers-addon' ) ); ?>
            </form>

            <?php if ( $filters_ok && ( ! $selected || ! $start_date || ! $end_date ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html__( 'Selecione um groomer e o intervalo de datas.', 'dps-groomers-addon' ); ?></p></div>
            <?php endif; ?>

            <?php if ( $filters_ok && $selected && $start_date && $end_date ) : ?>
                <h5><?php echo esc_html__( 'Resultados', 'dps-groomers-addon' ); ?></h5>
                <p><?php echo esc_html( sprintf( __( 'Total de atendimentos: %d', 'dps-groomers-addon' ), count( $appointments ) ) ); ?></p>
                <?php if ( count( $appointments ) === 500 ) : ?>
                    <div class="notice notice-warning inline"><p><?php echo esc_html__( 'Atenção: Relatório limitado a 500 atendimentos. Para períodos maiores, ajuste o intervalo de datas.', 'dps-groomers-addon' ); ?></p></div>
                <?php endif; ?>
                <p><?php echo esc_html( sprintf( __( 'Total financeiro (receitas pagas): R$ %s', 'dps-groomers-addon' ), number_format_i18n( $total_amount, 2 ) ) ); ?></p>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Data', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Horário', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Cliente', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Status', 'dps-groomers-addon' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $appointments ) ) : ?>
                            <tr><td colspan="4"><?php echo esc_html__( 'Nenhum agendamento encontrado para o período.', 'dps-groomers-addon' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $appointments as $appointment ) :
                                $date        = get_post_meta( $appointment->ID, 'appointment_date', true );
                                $time        = get_post_meta( $appointment->ID, 'appointment_time', true );
                                $client      = get_post_meta( $appointment->ID, 'appointment_client_id', true );
                                $status      = get_post_meta( $appointment->ID, 'appointment_status', true );
                                $client_name = $client ? get_the_title( $client ) : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $date ); ?></td>
                                    <td><?php echo esc_html( $time ); ?></td>
                                    <td><?php echo esc_html( $client_name ); ?></td>
                                    <td><?php echo esc_html( $status ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

register_activation_hook( __FILE__, [ 'DPS_Groomers_Addon', 'activate' ] );

/**
 * Inicializa o Groomers Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_groomers_init_addon() {
    if ( class_exists( 'DPS_Groomers_Addon' ) ) {
        new DPS_Groomers_Addon();
    }
}
add_action( 'init', 'dps_groomers_init_addon', 5 );
