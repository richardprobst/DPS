<?php
/**
 * Plugin Name:       Desi Pet Shower – Groomers Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para cadastrar groomers, vincular atendimentos e gerar relatórios por profissional.
 * Version:           1.3.0
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
     * Versão do add-on para cache busting de assets.
     *
     * @var string
     */
    const VERSION = '1.3.0';

    /**
     * Inicializa hooks do add-on.
     */
    public function __construct() {
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_groomers_tab' ], 15, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_groomers_section' ], 15, 1 );
        add_action( 'dps_base_appointment_fields', [ $this, 'render_appointment_groomer_field' ], 10, 2 );
        add_action( 'dps_base_after_save_appointment', [ $this, 'save_appointment_groomers' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Handlers para edição, exclusão e exportação
        add_action( 'init', [ $this, 'handle_groomer_actions' ] );
        
        // Shortcode do dashboard do groomer
        add_shortcode( 'dps_groomer_dashboard', [ $this, 'render_groomer_dashboard_shortcode' ] );
    }

    /**
     * Registra e enfileira assets no frontend (shortcode [dps_base]).
     *
     * @since 1.1.0
     */
    public function enqueue_frontend_assets() {
        // Verifica se estamos em uma página com o shortcode do DPS base
        global $post;
        if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( ! isset( $post->post_content ) || ! has_shortcode( $post->post_content, 'dps_base' ) ) {
            return;
        }

        $this->register_and_enqueue_assets();
    }

    /**
     * Registra e enfileira assets no admin.
     *
     * @since 1.1.0
     * @param string $hook_suffix Sufixo do hook da página atual.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Verifica se estamos em uma página relevante do DPS
        if ( strpos( $hook_suffix, 'desi-pet-shower' ) === false && strpos( $hook_suffix, 'dps' ) === false ) {
            return;
        }

        $this->register_and_enqueue_assets();
    }

    /**
     * Registra e enfileira CSS e JS do add-on.
     *
     * @since 1.1.0
     */
    private function register_and_enqueue_assets() {
        $plugin_url = plugin_dir_url( __FILE__ );

        // CSS
        wp_register_style(
            'dps-groomers-admin',
            $plugin_url . 'assets/css/groomers-admin.css',
            [],
            self::VERSION
        );
        wp_enqueue_style( 'dps-groomers-admin' );

        // JavaScript
        wp_register_script(
            'dps-groomers-admin',
            $plugin_url . 'assets/js/groomers-admin.js',
            [ 'jquery' ],
            self::VERSION,
            true
        );
        wp_enqueue_script( 'dps-groomers-admin' );
    }

    /**
     * Registra e enfileira Chart.js para gráficos.
     *
     * @since 1.3.0
     */
    private function enqueue_chartjs() {
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );
    }

    /**
     * Processa ações de edição, exclusão e exportação de groomers.
     *
     * @since 1.2.0
     */
    public function handle_groomer_actions() {
        // Não processar se não houver ação
        if ( ! isset( $_REQUEST['dps_groomer_action'] ) ) {
            return;
        }

        // Verificar permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_REQUEST['dps_groomer_action'] ) );

        switch ( $action ) {
            case 'delete':
                $this->handle_delete_groomer();
                break;
            case 'update':
                $this->handle_update_groomer();
                break;
            case 'export_csv':
                $this->handle_export_csv();
                break;
            case 'toggle_status':
                $this->handle_toggle_status();
                break;
        }
    }

    /**
     * Alterna o status do groomer entre ativo e inativo.
     *
     * @since 1.3.0
     */
    private function handle_toggle_status() {
        if ( ! isset( $_GET['groomer_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        $groomer_id = absint( $_GET['groomer_id'] );
        $nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'dps_toggle_status_' . $groomer_id ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $user = get_user_by( 'id', $groomer_id );
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            DPS_Message_Helper::add_error( __( 'Groomer não encontrado.', 'dps-groomers-addon' ) );
            return;
        }

        $current_status = get_user_meta( $groomer_id, '_dps_groomer_status', true );
        $new_status     = ( $current_status === 'inactive' ) ? 'active' : 'inactive';
        
        update_user_meta( $groomer_id, '_dps_groomer_status', $new_status );

        $groomer_name = $user->display_name ? $user->display_name : $user->user_login;
        $status_label = ( $new_status === 'active' ) 
            ? __( 'ativo', 'dps-groomers-addon' ) 
            : __( 'inativo', 'dps-groomers-addon' );

        DPS_Message_Helper::add_success( 
            sprintf( 
                /* translators: %1$s: groomer name, %2$s: status */
                __( 'Status de "%1$s" alterado para %2$s.', 'dps-groomers-addon' ), 
                $groomer_name, 
                $status_label 
            ) 
        );

        // Redirecionar para evitar resubmissão
        $redirect_url = remove_query_arg( [ 'dps_groomer_action', 'groomer_id', '_wpnonce' ] );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Processa exclusão de groomer.
     *
     * @since 1.2.0
     */
    private function handle_delete_groomer() {
        if ( ! isset( $_GET['groomer_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        $groomer_id = absint( $_GET['groomer_id'] );
        $nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'dps_delete_groomer_' . $groomer_id ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $user = get_user_by( 'id', $groomer_id );
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            DPS_Message_Helper::add_error( __( 'Groomer não encontrado.', 'dps-groomers-addon' ) );
            return;
        }

        // Verificar se o usuário tem roles privilegiadas (proteção adicional)
        $privileged_roles = [ 'administrator', 'editor', 'author' ];
        foreach ( $privileged_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                DPS_Message_Helper::add_error( __( 'Não é possível excluir usuários com permissões elevadas.', 'dps-groomers-addon' ) );
                return;
            }
        }

        // Verificar se há agendamentos vinculados
        $linked_appointments = $this->get_groomer_appointments_count( $groomer_id );
        
        // Excluir o usuário
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $result = wp_delete_user( $groomer_id );

        if ( $result ) {
            $message = sprintf(
                /* translators: %1$s: groomer name, %2$d: number of appointments */
                __( 'Groomer "%1$s" excluído com sucesso. %2$d agendamento(s) mantido(s) sem groomer vinculado.', 'dps-groomers-addon' ),
                $user->display_name ? $user->display_name : $user->user_login,
                $linked_appointments
            );
            DPS_Message_Helper::add_success( $message );
        } else {
            DPS_Message_Helper::add_error( __( 'Erro ao excluir groomer.', 'dps-groomers-addon' ) );
        }

        // Redirecionar para evitar resubmissão
        $redirect_url = remove_query_arg( [ 'dps_groomer_action', 'groomer_id', '_wpnonce' ] );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Conta quantos agendamentos estão vinculados a um groomer.
     *
     * @since 1.2.0
     *
     * @param int $groomer_id ID do groomer.
     * @return int Número de agendamentos.
     */
    private function get_groomer_appointments_count( $groomer_id ) {
        $appointments = get_posts(
            [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => '_dps_groomers',
                        'value'   => '"' . $groomer_id . '"',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        return count( $appointments );
    }

    /**
     * Processa atualização de groomer.
     *
     * @since 1.2.0
     */
    private function handle_update_groomer() {
        if ( ! isset( $_POST['dps_edit_groomer_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['dps_edit_groomer_nonce'] ), 'dps_edit_groomer' ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $groomer_id = isset( $_POST['groomer_id'] ) ? absint( $_POST['groomer_id'] ) : 0;
        if ( ! $groomer_id ) {
            DPS_Message_Helper::add_error( __( 'ID do groomer inválido.', 'dps-groomers-addon' ) );
            return;
        }

        $user = get_user_by( 'id', $groomer_id );
        if ( ! $user || ! in_array( 'dps_groomer', (array) $user->roles, true ) ) {
            DPS_Message_Helper::add_error( __( 'Groomer não encontrado.', 'dps-groomers-addon' ) );
            return;
        }

        $email      = isset( $_POST['dps_groomer_email'] ) ? sanitize_email( wp_unslash( $_POST['dps_groomer_email'] ) ) : '';
        $name       = isset( $_POST['dps_groomer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_groomer_name'] ) ) : '';
        $phone      = isset( $_POST['dps_groomer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_groomer_phone'] ) ) : '';
        $commission = isset( $_POST['dps_groomer_commission'] ) ? floatval( $_POST['dps_groomer_commission'] ) : 0;

        $update_data = [
            'ID' => $groomer_id,
        ];

        if ( $email && $email !== $user->user_email ) {
            // Verificar se email já existe para outro usuário
            $existing = get_user_by( 'email', $email );
            if ( $existing && $existing->ID !== $groomer_id ) {
                DPS_Message_Helper::add_error( __( 'Este email já está em uso por outro usuário.', 'dps-groomers-addon' ) );
                return;
            }
            $update_data['user_email'] = $email;
        }

        if ( $name ) {
            $update_data['display_name'] = $name;
        }

        $result = wp_update_user( $update_data );

        if ( is_wp_error( $result ) ) {
            DPS_Message_Helper::add_error( $result->get_error_message() );
        } else {
            // Atualizar meta fields
            update_user_meta( $groomer_id, '_dps_groomer_phone', $phone );
            update_user_meta( $groomer_id, '_dps_groomer_commission_rate', $commission );
            
            DPS_Message_Helper::add_success( __( 'Groomer atualizado com sucesso.', 'dps-groomers-addon' ) );
        }
    }

    /**
     * Processa exportação de relatório em CSV.
     *
     * @since 1.2.0
     */
    private function handle_export_csv() {
        if ( ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'dps_export_csv' ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $groomer_id = isset( $_GET['groomer_id'] ) ? absint( $_GET['groomer_id'] ) : 0;
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';

        if ( ! $groomer_id || ! $start_date || ! $end_date ) {
            DPS_Message_Helper::add_error( __( 'Parâmetros inválidos para exportação.', 'dps-groomers-addon' ) );
            return;
        }

        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer ) {
            DPS_Message_Helper::add_error( __( 'Groomer não encontrado.', 'dps-groomers-addon' ) );
            return;
        }

        // Buscar agendamentos
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
                        'value'   => '"' . $groomer_id . '"',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        // Gerar nome do arquivo de forma segura (usando apenas ID e datas)
        // Evita problemas de header injection com nomes de usuário
        $filename = sprintf(
            'relatorio-groomer-%d-%s-a-%s.csv',
            $groomer_id,
            preg_replace( '/[^0-9-]/', '', $start_date ),
            preg_replace( '/[^0-9-]/', '', $end_date )
        );

        // Headers para download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // BOM para UTF-8 no Excel
        echo "\xEF\xBB\xBF";

        $output = fopen( 'php://output', 'w' );
        
        // Informações do relatório (nome do groomer e período)
        $groomer_display_name = $groomer->display_name ? $groomer->display_name : $groomer->user_login;
        fputcsv( $output, [
            __( 'Relatório de Produtividade', 'dps-groomers-addon' ),
        ], ';' );
        fputcsv( $output, [
            __( 'Groomer:', 'dps-groomers-addon' ),
            $groomer_display_name,
        ], ';' );
        fputcsv( $output, [
            __( 'Período:', 'dps-groomers-addon' ),
            date_i18n( 'd/m/Y', strtotime( $start_date ) ) . ' - ' . date_i18n( 'd/m/Y', strtotime( $end_date ) ),
        ], ';' );
        fputcsv( $output, [], ';' ); // Linha em branco
        
        // Cabeçalhos do CSV
        fputcsv( $output, [
            __( 'Data', 'dps-groomers-addon' ),
            __( 'Horário', 'dps-groomers-addon' ),
            __( 'Cliente', 'dps-groomers-addon' ),
            __( 'Pet', 'dps-groomers-addon' ),
            __( 'Status', 'dps-groomers-addon' ),
            __( 'Valor', 'dps-groomers-addon' ),
        ], ';' );

        // Dados
        $total_revenue = 0;
        foreach ( $appointments as $appointment ) {
            $date      = get_post_meta( $appointment->ID, 'appointment_date', true );
            $time      = get_post_meta( $appointment->ID, 'appointment_time', true );
            $client_id = get_post_meta( $appointment->ID, 'appointment_client_id', true );
            $pet_ids   = get_post_meta( $appointment->ID, 'appointment_pet_ids', true );
            $status    = get_post_meta( $appointment->ID, 'appointment_status', true );

            $client_name = $client_id ? get_the_title( $client_id ) : '-';

            // Obter nome(s) do(s) pet(s)
            $pet_names = [];
            if ( is_array( $pet_ids ) ) {
                foreach ( $pet_ids as $pet_id ) {
                    $pet_name = get_the_title( $pet_id );
                    if ( $pet_name ) {
                        $pet_names[] = $pet_name;
                    }
                }
            }
            $pet_display = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '-';

            // Formatar data
            $date_display = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '-';

            // Valor do agendamento (se disponível)
            $valor = $this->get_appointment_value( $appointment->ID );
            $total_revenue += $valor;

            fputcsv( $output, [
                $date_display,
                $time ? $time : '-',
                $client_name,
                $pet_display,
                $status ? ucfirst( $status ) : __( 'Pendente', 'dps-groomers-addon' ),
                number_format_i18n( $valor, 2 ),
            ], ';' );
        }

        // Linha de totais
        fputcsv( $output, [], ';' );
        fputcsv( $output, [
            __( 'TOTAL', 'dps-groomers-addon' ),
            '',
            '',
            '',
            count( $appointments ) . ' ' . __( 'atendimentos', 'dps-groomers-addon' ),
            'R$ ' . number_format_i18n( $total_revenue, 2 ),
        ], ';' );

        fclose( $output );
        exit;
    }

    /**
     * Obtém o valor de um agendamento.
     *
     * @since 1.2.0
     *
     * @param int $appointment_id ID do agendamento.
     * @return float Valor do agendamento.
     */
    private function get_appointment_value( $appointment_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        $valor = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM {$table} WHERE agendamento_id = %d AND tipo = 'receita'",
                $appointment_id
            )
        );

        return (float) $valor;
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

        // Salvar meta fields adicionais do groomer
        $phone = isset( $_POST['dps_groomer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_groomer_phone'] ) ) : '';
        $commission = isset( $_POST['dps_groomer_commission'] ) ? floatval( $_POST['dps_groomer_commission'] ) : 0;
        
        update_user_meta( $user_id, '_dps_groomer_status', 'active' ); // Novo groomer sempre começa ativo
        update_user_meta( $user_id, '_dps_groomer_phone', $phone );
        update_user_meta( $user_id, '_dps_groomer_commission_rate', $commission );

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

        // Usar apenas groomers ativos na seleção
        $groomers = $this->get_active_groomers();
        
        echo '<p><label>' . esc_html__( 'Groomers responsáveis', 'dps-groomers-addon' ) . '<br>';
        echo '<select name="dps_groomers[]" multiple size="4" style="min-width:220px;">';
        if ( empty( $groomers ) ) {
            echo '<option value="">' . esc_html__( 'Nenhum groomer ativo', 'dps-groomers-addon' ) . '</option>';
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
            <h2 class="dps-section-title"><?php echo esc_html__( 'Groomers', 'dps-groomers-addon' ); ?></h2>
            <p class="dps-section-description"><?php echo esc_html__( 'Cadastre profissionais, associe-os a atendimentos e acompanhe relatórios por período.', 'dps-groomers-addon' ); ?></p>

            <?php echo DPS_Message_Helper::display_messages(); ?>

            <div class="dps-groomers-container">
                <div class="dps-groomers-form-container">
                    <h3 class="dps-field-group-title"><?php echo esc_html__( 'Adicionar novo groomer', 'dps-groomers-addon' ); ?></h3>
                    <form method="post" action="" class="dps-groomers-form">
                        <?php wp_nonce_field( 'dps_new_groomer', 'dps_new_groomer_nonce' ); ?>
                        
                        <fieldset class="dps-fieldset">
                            <legend><?php echo esc_html__( 'Dados de Acesso', 'dps-groomers-addon' ); ?></legend>
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="dps_groomer_username"><?php echo esc_html__( 'Usuário', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                                    <input name="dps_groomer_username" id="dps_groomer_username" type="text" placeholder="<?php echo esc_attr__( 'joao.silva', 'dps-groomers-addon' ); ?>" required />
                                </div>
                                <div class="dps-form-field">
                                    <label for="dps_groomer_email"><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                                    <input name="dps_groomer_email" id="dps_groomer_email" type="email" placeholder="<?php echo esc_attr__( 'joao@petshop.com', 'dps-groomers-addon' ); ?>" required />
                                </div>
                            </div>
                        </fieldset>
                        
                        <fieldset class="dps-fieldset">
                            <legend><?php echo esc_html__( 'Informações Pessoais', 'dps-groomers-addon' ); ?></legend>
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="dps_groomer_name"><?php echo esc_html__( 'Nome completo', 'dps-groomers-addon' ); ?></label>
                                    <input name="dps_groomer_name" id="dps_groomer_name" type="text" placeholder="<?php echo esc_attr__( 'João da Silva', 'dps-groomers-addon' ); ?>" />
                                </div>
                                <div class="dps-form-field">
                                    <label for="dps_groomer_password"><?php echo esc_html__( 'Senha', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                                    <input name="dps_groomer_password" id="dps_groomer_password" type="password" placeholder="<?php echo esc_attr__( 'Mínimo 8 caracteres', 'dps-groomers-addon' ); ?>" required />
                                </div>
                            </div>
                        </fieldset>
                        
                        <fieldset class="dps-fieldset">
                            <legend><?php echo esc_html__( 'Contato e Comissão', 'dps-groomers-addon' ); ?></legend>
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="dps_groomer_phone"><?php echo esc_html__( 'Telefone', 'dps-groomers-addon' ); ?></label>
                                    <input name="dps_groomer_phone" id="dps_groomer_phone" type="tel" placeholder="<?php echo esc_attr__( '(15) 99999-9999', 'dps-groomers-addon' ); ?>" />
                                </div>
                                <div class="dps-form-field">
                                    <label for="dps_groomer_commission"><?php echo esc_html__( 'Comissão (%)', 'dps-groomers-addon' ); ?></label>
                                    <input name="dps_groomer_commission" id="dps_groomer_commission" type="number" min="0" max="100" step="0.5" placeholder="<?php echo esc_attr__( '10', 'dps-groomers-addon' ); ?>" />
                                </div>
                            </div>
                        </fieldset>
                        
                        <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Criar groomer', 'dps-groomers-addon' ); ?></button>
                    </form>
                </div>

                <div class="dps-groomers-list-container">
                    <h3 class="dps-field-group-title"><?php echo esc_html__( 'Groomers cadastrados', 'dps-groomers-addon' ); ?></h3>
                    <table class="dps-groomers-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Telefone', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Comissão', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Status', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Ações', 'dps-groomers-addon' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $groomers ) ) : ?>
                            <tr>
                                <td colspan="5" class="dps-empty-message"><?php echo esc_html__( 'Nenhum groomer cadastrado ainda. Use o formulário ao lado para adicionar o primeiro profissional.', 'dps-groomers-addon' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $groomers as $groomer ) : 
                                $delete_url = wp_nonce_url(
                                    add_query_arg(
                                        [
                                            'dps_groomer_action' => 'delete',
                                            'groomer_id'         => $groomer->ID,
                                        ]
                                    ),
                                    'dps_delete_groomer_' . $groomer->ID
                                );
                                $toggle_url = wp_nonce_url(
                                    add_query_arg(
                                        [
                                            'dps_groomer_action' => 'toggle_status',
                                            'groomer_id'         => $groomer->ID,
                                        ]
                                    ),
                                    'dps_toggle_status_' . $groomer->ID
                                );
                                $appointments_count = $this->get_groomer_appointments_count( $groomer->ID );
                                $groomer_status     = get_user_meta( $groomer->ID, '_dps_groomer_status', true );
                                $groomer_phone      = get_user_meta( $groomer->ID, '_dps_groomer_phone', true );
                                $groomer_commission = get_user_meta( $groomer->ID, '_dps_groomer_commission_rate', true );
                                
                                // Default para ativo se não tiver status definido
                                if ( empty( $groomer_status ) ) {
                                    $groomer_status = 'active';
                                }
                                
                                $status_class = ( $groomer_status === 'active' ) ? 'dps-status-badge--ativo' : 'dps-status-badge--inativo';
                                $status_label = ( $groomer_status === 'active' ) 
                                    ? __( 'Ativo', 'dps-groomers-addon' ) 
                                    : __( 'Inativo', 'dps-groomers-addon' );
                                ?>
                                <tr class="<?php echo ( $groomer_status === 'inactive' ) ? 'dps-groomer-inactive' : ''; ?>">
                                    <td>
                                        <strong><?php echo esc_html( $groomer->display_name ? $groomer->display_name : $groomer->user_login ); ?></strong>
                                        <br><small><?php echo esc_html( $groomer->user_email ); ?></small>
                                    </td>
                                    <td>
                                        <?php if ( $groomer_phone ) : ?>
                                            <?php echo esc_html( $groomer_phone ); ?>
                                        <?php else : ?>
                                            <span class="dps-no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $groomer_commission ) : ?>
                                            <?php echo esc_html( number_format_i18n( $groomer_commission, 1 ) ); ?>%
                                        <?php else : ?>
                                            <span class="dps-no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( $toggle_url ); ?>" 
                                            class="dps-status-badge <?php echo esc_attr( $status_class ); ?>"
                                            title="<?php echo esc_attr__( 'Clique para alternar status', 'dps-groomers-addon' ); ?>">
                                            <?php echo esc_html( $status_label ); ?>
                                        </a>
                                    </td>
                                    <td class="dps-actions">
                                        <button type="button" 
                                            class="dps-action-link dps-edit-groomer" 
                                            data-groomer-id="<?php echo esc_attr( $groomer->ID ); ?>"
                                            data-groomer-name="<?php echo esc_attr( $groomer->display_name ); ?>"
                                            data-groomer-email="<?php echo esc_attr( $groomer->user_email ); ?>"
                                            data-groomer-phone="<?php echo esc_attr( $groomer_phone ); ?>"
                                            data-groomer-commission="<?php echo esc_attr( $groomer_commission ); ?>"
                                            title="<?php echo esc_attr__( 'Editar groomer', 'dps-groomers-addon' ); ?>">
                                            ✏️ <?php echo esc_html__( 'Editar', 'dps-groomers-addon' ); ?>
                                        </button>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" 
                                            class="dps-action-link dps-action-link--delete dps-delete-groomer"
                                            data-groomer-name="<?php echo esc_attr( $groomer->display_name ? $groomer->display_name : $groomer->user_login ); ?>"
                                            data-appointments="<?php echo esc_attr( $appointments_count ); ?>"
                                            title="<?php echo esc_attr__( 'Excluir groomer', 'dps-groomers-addon' ); ?>">
                                            🗑️ <?php echo esc_html__( 'Excluir', 'dps-groomers-addon' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal de Edição de Groomer -->
            <div id="dps-edit-groomer-modal" class="dps-modal" style="display: none;">
                <div class="dps-modal-content">
                    <div class="dps-modal-header">
                        <h4><?php echo esc_html__( 'Editar Groomer', 'dps-groomers-addon' ); ?></h4>
                        <button type="button" class="dps-modal-close">&times;</button>
                    </div>
                    <form method="post" action="" class="dps-groomers-form">
                        <?php wp_nonce_field( 'dps_edit_groomer', 'dps_edit_groomer_nonce' ); ?>
                        <input type="hidden" name="dps_groomer_action" value="update" />
                        <input type="hidden" name="groomer_id" id="edit_groomer_id" value="" />
                        
                        <div class="dps-modal-body">
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="edit_groomer_name"><?php echo esc_html__( 'Nome completo', 'dps-groomers-addon' ); ?></label>
                                    <input type="text" name="dps_groomer_name" id="edit_groomer_name" class="regular-text" />
                                </div>
                                <div class="dps-form-field">
                                    <label for="edit_groomer_email"><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                                    <input type="email" name="dps_groomer_email" id="edit_groomer_email" class="regular-text" required />
                                </div>
                            </div>
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="edit_groomer_phone"><?php echo esc_html__( 'Telefone', 'dps-groomers-addon' ); ?></label>
                                    <input type="tel" name="dps_groomer_phone" id="edit_groomer_phone" class="regular-text" placeholder="<?php echo esc_attr__( '(15) 99999-9999', 'dps-groomers-addon' ); ?>" />
                                </div>
                                <div class="dps-form-field">
                                    <label for="edit_groomer_commission"><?php echo esc_html__( 'Comissão (%)', 'dps-groomers-addon' ); ?></label>
                                    <input type="number" name="dps_groomer_commission" id="edit_groomer_commission" class="regular-text" min="0" max="100" step="0.5" />
                                </div>
                            </div>
                            <p class="dps-modal-note">
                                <?php echo esc_html__( 'Nota: O nome de usuário não pode ser alterado após a criação.', 'dps-groomers-addon' ); ?>
                            </p>
                        </div>
                        
                        <div class="dps-modal-footer">
                            <button type="button" class="dps-btn dps-btn--secondary dps-modal-cancel"><?php echo esc_html__( 'Cancelar', 'dps-groomers-addon' ); ?></button>
                            <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Salvar alterações', 'dps-groomers-addon' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dps-groomers-report">
                <?php echo $this->render_report_block( $groomers ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Recupera apenas groomers ativos para seleção em agendamentos.
     *
     * @since 1.3.0
     *
     * @return WP_User[]
     */
    private function get_active_groomers() {
        $groomers = $this->get_groomers();
        
        return array_filter( $groomers, function( $groomer ) {
            $status = get_user_meta( $groomer->ID, '_dps_groomer_status', true );
            return empty( $status ) || $status === 'active';
        } );
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
                $total_amount = $this->calculate_total_revenue( $appointments );
            }
        }

        // Obter nome do groomer selecionado
        $selected_groomer_name = '';
        if ( $selected ) {
            $groomer_user = get_user_by( 'id', $selected );
            if ( $groomer_user ) {
                $selected_groomer_name = $groomer_user->display_name ? $groomer_user->display_name : $groomer_user->user_login;
            }
        }

        ob_start();
        ?>
        <h4 class="dps-field-group-title"><?php echo esc_html__( 'Relatório por Groomer', 'dps-groomers-addon' ); ?></h4>
        
        <form method="get" action="" class="dps-report-filters">
            <input type="hidden" name="tab" value="groomers" />
            <?php wp_nonce_field( 'dps_report', 'dps_report_nonce' ); ?>
            
            <div class="dps-form-field">
                <label for="dps_report_groomer"><?php echo esc_html__( 'Groomer', 'dps-groomers-addon' ); ?></label>
                <select name="dps_report_groomer" id="dps_report_groomer" required>
                    <option value=""><?php echo esc_html__( 'Selecione...', 'dps-groomers-addon' ); ?></option>
                    <?php foreach ( $groomers as $groomer ) : ?>
                        <option value="<?php echo esc_attr( $groomer->ID ); ?>" <?php selected( $selected, $groomer->ID ); ?>>
                            <?php echo esc_html( $groomer->display_name ? $groomer->display_name : $groomer->user_login ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dps-form-field">
                <label for="dps_report_start"><?php echo esc_html__( 'Data inicial', 'dps-groomers-addon' ); ?></label>
                <input type="date" name="dps_report_start" id="dps_report_start" value="<?php echo esc_attr( $start_date ); ?>" required />
            </div>
            
            <div class="dps-form-field">
                <label for="dps_report_end"><?php echo esc_html__( 'Data final', 'dps-groomers-addon' ); ?></label>
                <input type="date" name="dps_report_end" id="dps_report_end" value="<?php echo esc_attr( $end_date ); ?>" required />
            </div>
            
            <div class="dps-report-actions">
                <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Gerar relatório', 'dps-groomers-addon' ); ?></button>
            </div>
        </form>

        <?php if ( $filters_ok && ( ! $selected || ! $start_date || ! $end_date ) ) : ?>
            <div class="dps-groomers-notice dps-groomers-notice--error">
                <?php echo esc_html__( 'Selecione um groomer e o intervalo de datas para gerar o relatório.', 'dps-groomers-addon' ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $filters_ok && $selected && $start_date && $end_date ) : ?>
            
            <?php if ( count( $appointments ) === 500 ) : ?>
                <div class="dps-groomers-notice dps-groomers-notice--warning">
                    <?php echo esc_html__( 'Atenção: Relatório limitado a 500 atendimentos. Para períodos maiores, ajuste o intervalo de datas.', 'dps-groomers-addon' ); ?>
                </div>
            <?php endif; ?>
            
            <!-- Cards de Métricas -->
            <div class="dps-metrics-grid">
                <div class="dps-metric-card">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Profissional', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value"><?php echo esc_html( $selected_groomer_name ); ?></span>
                </div>
                <div class="dps-metric-card dps-metric-card--info">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Total de Atendimentos', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value"><?php echo esc_html( count( $appointments ) ); ?></span>
                </div>
                <div class="dps-metric-card dps-metric-card--success">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Receita Total', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_amount, 2 ) ); ?></span>
                </div>
                <?php if ( count( $appointments ) > 0 ) : ?>
                <div class="dps-metric-card">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Ticket Médio', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_amount / count( $appointments ), 2 ) ); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Botão de Exportação CSV -->
            <?php
            $export_url = wp_nonce_url(
                add_query_arg(
                    [
                        'dps_groomer_action' => 'export_csv',
                        'groomer_id'         => $selected,
                        'start_date'         => $start_date,
                        'end_date'           => $end_date,
                    ]
                ),
                'dps_export_csv'
            );
            ?>
            <div class="dps-export-actions">
                <a href="<?php echo esc_url( $export_url ); ?>" class="dps-btn dps-btn--secondary" target="_blank">
                    📊 <?php echo esc_html__( 'Exportar CSV', 'dps-groomers-addon' ); ?>
                </a>
            </div>
            
            <!-- Tabela de Resultados -->
            <div class="dps-report-results">
                <h5><?php echo esc_html__( 'Detalhamento de Atendimentos', 'dps-groomers-addon' ); ?></h5>
                <table class="dps-report-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Data', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Horário', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Cliente', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Pet', 'dps-groomers-addon' ); ?></th>
                            <th><?php echo esc_html__( 'Status', 'dps-groomers-addon' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $appointments ) ) : ?>
                            <tr>
                                <td colspan="5" class="dps-empty-message">
                                    <?php echo esc_html__( 'Nenhum agendamento encontrado para o período selecionado.', 'dps-groomers-addon' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $appointments as $appointment ) :
                                $date        = get_post_meta( $appointment->ID, 'appointment_date', true );
                                $time        = get_post_meta( $appointment->ID, 'appointment_time', true );
                                $client_id   = get_post_meta( $appointment->ID, 'appointment_client_id', true );
                                $pet_ids     = get_post_meta( $appointment->ID, 'appointment_pet_ids', true );
                                $status      = get_post_meta( $appointment->ID, 'appointment_status', true );
                                $client_name = $client_id ? get_the_title( $client_id ) : '-';
                                
                                // Obter nome(s) do(s) pet(s)
                                $pet_names = [];
                                if ( is_array( $pet_ids ) ) {
                                    foreach ( $pet_ids as $pet_id ) {
                                        $pet_name = get_the_title( $pet_id );
                                        if ( $pet_name ) {
                                            $pet_names[] = $pet_name;
                                        }
                                    }
                                }
                                $pet_display = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '-';
                                
                                // Formatar data para exibição
                                $date_display = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '-';
                                
                                // Classe CSS do status
                                $status_class = 'dps-status-badge dps-status-badge--' . sanitize_html_class( $status ? $status : 'pendente' );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $date_display ); ?></td>
                                    <td><?php echo esc_html( $time ? $time : '-' ); ?></td>
                                    <td><?php echo esc_html( $client_name ); ?></td>
                                    <td><?php echo esc_html( $pet_display ); ?></td>
                                    <td>
                                        <span class="<?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $status ? ucfirst( $status ) : __( 'Pendente', 'dps-groomers-addon' ) ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o shortcode do dashboard do groomer.
     *
     * Permite que groomers logados vejam seus próprios atendimentos.
     *
     * @since 1.3.0
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML do dashboard.
     */
    public function render_groomer_dashboard_shortcode( $atts ) {
        // Enfileirar assets
        $this->register_and_enqueue_assets();
        $this->enqueue_chartjs();

        // Verificar se usuário está logado
        if ( ! is_user_logged_in() ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Você precisa estar logado para acessar o dashboard.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        $current_user = wp_get_current_user();
        
        // Verificar se é um groomer
        if ( ! in_array( 'dps_groomer', (array) $current_user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Acesso restrito a groomers.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Se for admin, pode selecionar qualquer groomer
        $groomer_id = $current_user->ID;
        if ( current_user_can( 'manage_options' ) && isset( $_GET['groomer_id'] ) ) {
            $groomer_id = absint( $_GET['groomer_id'] );
        }

        // Período do relatório (padrão: últimos 30 dias)
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : date( 'Y-m-d' );
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) );

        // Buscar atendimentos do groomer
        $appointments = get_posts(
            [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_date',
                'order'          => 'DESC',
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
                        'value'   => '"' . $groomer_id . '"',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        // Calcular métricas
        $total_appointments = count( $appointments );
        $total_revenue      = $this->calculate_total_revenue( $appointments );
        $commission_rate    = (float) get_user_meta( $groomer_id, '_dps_groomer_commission_rate', true );
        $total_commission   = $total_revenue * ( $commission_rate / 100 );
        $avg_ticket         = $total_appointments > 0 ? $total_revenue / $total_appointments : 0;

        // Contagem por status
        $status_counts = [
            'realizado' => 0,
            'pendente'  => 0,
            'cancelado' => 0,
        ];
        foreach ( $appointments as $appointment ) {
            $status = get_post_meta( $appointment->ID, 'appointment_status', true );
            if ( isset( $status_counts[ $status ] ) ) {
                $status_counts[ $status ]++;
            } else {
                $status_counts['pendente']++;
            }
        }

        // Preparar dados para gráficos
        $daily_data = [];
        foreach ( $appointments as $appointment ) {
            $date = get_post_meta( $appointment->ID, 'appointment_date', true );
            if ( $date ) {
                if ( ! isset( $daily_data[ $date ] ) ) {
                    $daily_data[ $date ] = [
                        'count'   => 0,
                        'revenue' => 0,
                    ];
                }
                $daily_data[ $date ]['count']++;
                $daily_data[ $date ]['revenue'] += $this->get_appointment_value( $appointment->ID );
            }
        }
        ksort( $daily_data );
        
        $chart_labels = [];
        $chart_counts = [];
        $chart_revenue = [];
        foreach ( $daily_data as $date => $data ) {
            $chart_labels[]  = date_i18n( 'd/m', strtotime( $date ) );
            $chart_counts[]  = $data['count'];
            $chart_revenue[] = $data['revenue'];
        }

        $groomer = get_user_by( 'id', $groomer_id );
        $groomer_name = $groomer ? ( $groomer->display_name ? $groomer->display_name : $groomer->user_login ) : '';

        ob_start();
        ?>
        <div class="dps-groomer-dashboard">
            <h2 class="dps-section-title">
                <?php 
                echo esc_html( 
                    sprintf( 
                        /* translators: %s: groomer name */
                        __( 'Dashboard de %s', 'dps-groomers-addon' ), 
                        $groomer_name 
                    ) 
                ); 
                ?>
            </h2>

            <!-- Filtros de período -->
            <form method="get" class="dps-dashboard-filters">
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <input type="hidden" name="groomer_id" value="<?php echo esc_attr( $groomer_id ); ?>" />
                <?php endif; ?>
                
                <div class="dps-form-row">
                    <div class="dps-form-field">
                        <label for="start_date"><?php echo esc_html__( 'Data inicial', 'dps-groomers-addon' ); ?></label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                    </div>
                    <div class="dps-form-field">
                        <label for="end_date"><?php echo esc_html__( 'Data final', 'dps-groomers-addon' ); ?></label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                    </div>
                    <div class="dps-form-field dps-form-field--button">
                        <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Filtrar', 'dps-groomers-addon' ); ?></button>
                    </div>
                </div>
            </form>

            <!-- Cards de métricas -->
            <div class="dps-metrics-grid dps-metrics-grid--dashboard">
                <div class="dps-metric-card dps-metric-card--info">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Atendimentos', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value"><?php echo esc_html( $total_appointments ); ?></span>
                </div>
                <div class="dps-metric-card dps-metric-card--success">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Receita Total', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_revenue, 2 ) ); ?></span>
                </div>
                <?php if ( $commission_rate > 0 ) : ?>
                <div class="dps-metric-card dps-metric-card--warning">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Comissão', 'dps-groomers-addon' ); ?> (<?php echo esc_html( number_format_i18n( $commission_rate, 1 ) ); ?>%)</span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_commission, 2 ) ); ?></span>
                </div>
                <?php endif; ?>
                <div class="dps-metric-card">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Ticket Médio', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $avg_ticket, 2 ) ); ?></span>
                </div>
            </div>

            <!-- Cards de status -->
            <div class="dps-status-cards">
                <div class="dps-status-card dps-status-card--realizado">
                    <span class="dps-status-card__count"><?php echo esc_html( $status_counts['realizado'] ); ?></span>
                    <span class="dps-status-card__label"><?php echo esc_html__( 'Realizados', 'dps-groomers-addon' ); ?></span>
                </div>
                <div class="dps-status-card dps-status-card--pendente">
                    <span class="dps-status-card__count"><?php echo esc_html( $status_counts['pendente'] ); ?></span>
                    <span class="dps-status-card__label"><?php echo esc_html__( 'Pendentes', 'dps-groomers-addon' ); ?></span>
                </div>
                <div class="dps-status-card dps-status-card--cancelado">
                    <span class="dps-status-card__count"><?php echo esc_html( $status_counts['cancelado'] ); ?></span>
                    <span class="dps-status-card__label"><?php echo esc_html__( 'Cancelados', 'dps-groomers-addon' ); ?></span>
                </div>
            </div>

            <!-- Gráficos de desempenho -->
            <?php if ( ! empty( $chart_labels ) ) : ?>
            <div class="dps-charts-section">
                <h3><?php echo esc_html__( 'Desempenho por Período', 'dps-groomers-addon' ); ?></h3>
                <div class="dps-charts-grid">
                    <div class="dps-chart-container">
                        <h4><?php echo esc_html__( 'Atendimentos por Dia', 'dps-groomers-addon' ); ?></h4>
                        <canvas id="dps-appointments-chart"></canvas>
                    </div>
                    <div class="dps-chart-container">
                        <h4><?php echo esc_html__( 'Receita por Dia', 'dps-groomers-addon' ); ?></h4>
                        <canvas id="dps-revenue-chart"></canvas>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Dados do PHP
                var labels = <?php echo wp_json_encode( $chart_labels ); ?>;
                var appointmentCounts = <?php echo wp_json_encode( $chart_counts ); ?>;
                var revenueData = <?php echo wp_json_encode( $chart_revenue ); ?>;

                // Gráfico de atendimentos
                var appointmentsCtx = document.getElementById('dps-appointments-chart');
                if (appointmentsCtx) {
                    new Chart(appointmentsCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '<?php echo esc_js( __( 'Atendimentos', 'dps-groomers-addon' ) ); ?>',
                                data: appointmentCounts,
                                backgroundColor: 'rgba(14, 165, 233, 0.7)',
                                borderColor: 'rgba(14, 165, 233, 1)',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                }

                // Gráfico de receita
                var revenueCtx = document.getElementById('dps-revenue-chart');
                if (revenueCtx) {
                    new Chart(revenueCtx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '<?php echo esc_js( __( 'Receita (R$)', 'dps-groomers-addon' ) ); ?>',
                                data: revenueData,
                                borderColor: 'rgba(16, 185, 129, 1)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: 'rgba(16, 185, 129, 1)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'R$ ' + value.toFixed(2);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
            </script>
            <?php endif; ?>

            <!-- Lista de atendimentos -->
            <div class="dps-dashboard-appointments">
                <h3><?php echo esc_html__( 'Meus Atendimentos', 'dps-groomers-addon' ); ?></h3>
                
                <?php if ( empty( $appointments ) ) : ?>
                    <p class="dps-empty-message"><?php echo esc_html__( 'Nenhum atendimento encontrado no período selecionado.', 'dps-groomers-addon' ); ?></p>
                <?php else : ?>
                    <table class="dps-report-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Data', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Horário', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Cliente', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Pet', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Status', 'dps-groomers-addon' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $appointments as $appointment ) :
                                $date        = get_post_meta( $appointment->ID, 'appointment_date', true );
                                $time        = get_post_meta( $appointment->ID, 'appointment_time', true );
                                $client_id   = get_post_meta( $appointment->ID, 'appointment_client_id', true );
                                $pet_ids     = get_post_meta( $appointment->ID, 'appointment_pet_ids', true );
                                $status      = get_post_meta( $appointment->ID, 'appointment_status', true );
                                $client_name = $client_id ? get_the_title( $client_id ) : '-';
                                
                                $pet_names = [];
                                if ( is_array( $pet_ids ) ) {
                                    foreach ( $pet_ids as $pet_id ) {
                                        $pet_name = get_the_title( $pet_id );
                                        if ( $pet_name ) {
                                            $pet_names[] = $pet_name;
                                        }
                                    }
                                }
                                $pet_display  = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '-';
                                $date_display = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '-';
                                $status_class = 'dps-status-badge dps-status-badge--' . sanitize_html_class( $status ? $status : 'pendente' );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $date_display ); ?></td>
                                    <td><?php echo esc_html( $time ? $time : '-' ); ?></td>
                                    <td><?php echo esc_html( $client_name ); ?></td>
                                    <td><?php echo esc_html( $pet_display ); ?></td>
                                    <td>
                                        <span class="<?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $status ? ucfirst( $status ) : __( 'Pendente', 'dps-groomers-addon' ) ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calcula a receita total dos agendamentos.
     *
     * Utiliza DPS_Finance_API se disponível, caso contrário faz query direta.
     *
     * @since 1.1.0
     *
     * @param array $appointments Lista de agendamentos.
     * @return float Total de receitas pagas.
     */
    private function calculate_total_revenue( $appointments ) {
        if ( empty( $appointments ) ) {
            return 0.0;
        }

        $ids = wp_list_pluck( $appointments, 'ID' );

        // Tenta usar Finance API se disponível
        if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'get_paid_total_for_appointments' ) ) {
            return (float) DPS_Finance_API::get_paid_total_for_appointments( $ids );
        }

        // Fallback: query direta ao banco
        global $wpdb;
        
        // Sanitiza e valida IDs
        $sanitized_ids = array_map( 'absint', $ids );
        $sanitized_ids = array_filter( $sanitized_ids );
        
        if ( empty( $sanitized_ids ) ) {
            return 0.0;
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );
        $table_name   = $wpdb->prefix . 'dps_transacoes';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders são gerados dinamicamente mas de forma segura
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM {$table_name} WHERE status = 'pago' AND tipo = 'receita' AND agendamento_id IN ($placeholders)",
                ...$sanitized_ids
            )
        );

        return (float) $total;
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
