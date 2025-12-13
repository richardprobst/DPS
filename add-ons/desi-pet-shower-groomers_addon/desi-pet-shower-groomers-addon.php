<?php
/**
 * Plugin Name:       DPS by PRObst – Groomers Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Cadastro de groomers com vinculação a atendimentos e relatórios por profissional. Portal exclusivo para groomers.
 * Version:           1.4.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
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

// Carrega classes auxiliares
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dps-groomer-token-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dps-groomer-session-manager.php';

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_groomers_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Groomers requer o plugin base DPS by PRObst para funcionar.', 'dps-groomers-addon' );
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
    // Inicializa gerenciadores de token e sessão
    DPS_Groomer_Token_Manager::get_instance();
    DPS_Groomer_Session_Manager::get_instance();
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
    const VERSION = '1.5.0';

    /**
     * Tipos de profissionais disponíveis.
     *
     * @since 1.5.0
     * @var array
     */
    const STAFF_TYPES = [
        'groomer'   => 'Groomer',
        'banhista'  => 'Banhista',
        'auxiliar'  => 'Auxiliar',
        'recepcao'  => 'Recepção',
    ];

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
        
        // Handler para autenticação por token
        add_action( 'init', [ $this, 'handle_token_authentication' ], 5 );
        
        // Handler para logout
        add_action( 'init', [ $this, 'handle_logout_request' ], 6 );
        
        // Registrar CPT de avaliações
        add_action( 'init', [ $this, 'register_review_post_type' ] );
        
        // Migração de dados para novos campos (staff_type, is_freelancer)
        add_action( 'init', [ $this, 'maybe_migrate_staff_data' ], 2 );
        
        // Shortcode do dashboard do groomer
        add_shortcode( 'dps_groomer_dashboard', [ $this, 'render_groomer_dashboard_shortcode' ] );
        
        // Shortcode da agenda do groomer
        add_shortcode( 'dps_groomer_agenda', [ $this, 'render_groomer_agenda_shortcode' ] );
        
        // Shortcode de avaliação
        add_shortcode( 'dps_groomer_review', [ $this, 'render_review_form_shortcode' ] );
        
        // Shortcode de exibição de avaliações
        add_shortcode( 'dps_groomer_reviews', [ $this, 'render_reviews_list_shortcode' ] );
        
        // Shortcode do portal do groomer (acesso via token)
        add_shortcode( 'dps_groomer_portal', [ $this, 'render_groomer_portal_shortcode' ] );
        
        // Shortcode de login do groomer
        add_shortcode( 'dps_groomer_login', [ $this, 'render_groomer_login_shortcode' ] );
        
        // Adiciona seção de gerenciamento de tokens no admin
        add_action( 'dps_settings_nav_tabs', [ $this, 'render_groomer_tokens_tab' ], 25, 1 );
        add_action( 'dps_settings_sections', [ $this, 'render_groomer_tokens_section' ], 25, 1 );
        
        // Handler para ações de tokens no admin
        add_action( 'init', [ $this, 'handle_token_admin_actions' ] );
    }

    /**
     * Processa autenticação por token na URL.
     *
     * @since 1.4.0
     */
    public function handle_token_authentication() {
        // Verifica se há um token na URL
        if ( ! isset( $_GET['dps_groomer_token'] ) ) {
            return;
        }

        $token_plain = sanitize_text_field( wp_unslash( $_GET['dps_groomer_token'] ) );
        
        if ( empty( $token_plain ) ) {
            return;
        }

        // Valida o token
        $token_manager = DPS_Groomer_Token_Manager::get_instance();
        $token_data    = $token_manager->validate_token( $token_plain );

        if ( false === $token_data ) {
            // Token inválido - redireciona com erro
            $this->redirect_with_error( 'invalid' );
            return;
        }

        // Token válido - autentica o groomer
        $session_manager = DPS_Groomer_Session_Manager::get_instance();
        $authenticated   = $session_manager->authenticate_groomer( $token_data['groomer_id'] );

        if ( ! $authenticated ) {
            $this->redirect_with_error( 'auth_failed' );
            return;
        }

        // Marca token como usado (apenas para tokens não permanentes)
        if ( 'permanent' !== $token_data['type'] ) {
            $token_manager->mark_as_used( $token_data['id'] );
        }

        // Redireciona para o portal (remove o token da URL)
        $redirect_url = $this->get_portal_page_url();
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Redireciona com mensagem de erro.
     *
     * @since 1.4.0
     * @param string $error_type Tipo do erro.
     */
    private function redirect_with_error( $error_type ) {
        $redirect_url = add_query_arg( 'groomer_token_error', $error_type, home_url() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Obtém a URL da página do portal do groomer.
     *
     * @since 1.4.0
     * @return string URL do portal.
     */
    public function get_portal_page_url() {
        // Busca uma página com o shortcode [dps_groomer_portal]
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish' 
            AND post_content LIKE '%[dps_groomer_portal%' 
            LIMIT 1"
        );

        if ( $page_id ) {
            return get_permalink( $page_id );
        }

        return home_url( '/portal-groomer/' );
    }

    /**
     * Processa requisição de logout.
     *
     * @since 1.4.0
     */
    public function handle_logout_request() {
        $session_manager = DPS_Groomer_Session_Manager::get_instance();
        $session_manager->handle_logout_request();
    }

    /**
     * Processa ações de tokens no admin.
     *
     * @since 1.4.0
     */
    public function handle_token_admin_actions() {
        if ( ! isset( $_REQUEST['dps_groomer_token_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_REQUEST['dps_groomer_token_action'] ) );

        switch ( $action ) {
            case 'generate':
                $this->handle_generate_token();
                break;
            case 'revoke':
                $this->handle_revoke_token();
                break;
            case 'revoke_all':
                $this->handle_revoke_all_tokens();
                break;
        }
    }

    /**
     * Gera um novo token para um groomer.
     *
     * @since 1.4.0
     */
    private function handle_generate_token() {
        if ( ! isset( $_POST['groomer_id'] ) || ! isset( $_POST['_wpnonce'] ) ) {
            return;
        }

        $groomer_id = absint( $_POST['groomer_id'] );
        $nonce      = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
        $type       = isset( $_POST['token_type'] ) ? sanitize_text_field( wp_unslash( $_POST['token_type'] ) ) : 'login';

        if ( ! wp_verify_nonce( $nonce, 'dps_generate_groomer_token_' . $groomer_id ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $token_manager = DPS_Groomer_Token_Manager::get_instance();
        $token         = $token_manager->generate_token( $groomer_id, $type );

        if ( $token ) {
            // Gera a URL completa com o token
            $portal_url = $this->get_portal_page_url();
            $token_url  = add_query_arg( 'dps_groomer_token', $token, $portal_url );

            // Armazena temporariamente para exibição (apenas uma vez)
            set_transient( 'dps_groomer_token_url_' . $groomer_id, $token_url, 300 );

            DPS_Message_Helper::add_success( __( 'Token gerado com sucesso! Copie o link abaixo.', 'dps-groomers-addon' ) );
        } else {
            DPS_Message_Helper::add_error( __( 'Erro ao gerar token.', 'dps-groomers-addon' ) );
        }
    }

    /**
     * Revoga um token específico.
     *
     * @since 1.4.0
     */
    private function handle_revoke_token() {
        if ( ! isset( $_GET['token_id'] ) || ! isset( $_GET['groomer_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        $token_id   = absint( $_GET['token_id'] );
        $groomer_id = absint( $_GET['groomer_id'] );
        $nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'dps_revoke_groomer_token_' . $token_id ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $token_manager = DPS_Groomer_Token_Manager::get_instance();
        $revoked       = $token_manager->revoke_single_token( $token_id, $groomer_id );

        if ( $revoked ) {
            DPS_Message_Helper::add_success( __( 'Token revogado com sucesso.', 'dps-groomers-addon' ) );
        } else {
            DPS_Message_Helper::add_error( __( 'Erro ao revogar token.', 'dps-groomers-addon' ) );
        }
    }

    /**
     * Revoga todos os tokens de um groomer.
     *
     * @since 1.4.0
     */
    private function handle_revoke_all_tokens() {
        if ( ! isset( $_GET['groomer_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            return;
        }

        $groomer_id = absint( $_GET['groomer_id'] );
        $nonce      = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'dps_revoke_all_groomer_tokens_' . $groomer_id ) ) {
            DPS_Message_Helper::add_error( __( 'Erro de segurança. Tente novamente.', 'dps-groomers-addon' ) );
            return;
        }

        $token_manager = DPS_Groomer_Token_Manager::get_instance();
        $count         = $token_manager->revoke_tokens( $groomer_id );

        if ( false !== $count ) {
            DPS_Message_Helper::add_success( 
                sprintf( 
                    /* translators: %d: number of tokens revoked */
                    __( '%d token(s) revogado(s) com sucesso.', 'dps-groomers-addon' ), 
                    $count 
                ) 
            );
        } else {
            DPS_Message_Helper::add_error( __( 'Erro ao revogar tokens.', 'dps-groomers-addon' ) );
        }
    }

    /**
     * Renderiza a aba de gerenciamento de tokens.
     *
     * @since 1.4.0
     * @param string $active_tab Aba ativa.
     */
    public function render_groomer_tokens_tab( $active_tab ) {
        $is_active = ( 'groomer-tokens' === $active_tab );
        ?>
        <li class="dps-tab-item<?php echo $is_active ? ' dps-tab-item--active' : ''; ?>">
            <a href="#groomer-tokens" class="dps-tab-link" data-tab="groomer-tokens">
                <?php echo esc_html__( 'Logins de Groomers', 'dps-groomers-addon' ); ?>
            </a>
        </li>
        <?php
    }

    /**
     * Renderiza a seção de gerenciamento de tokens.
     *
     * @since 1.4.0
     * @param string $active_tab Aba ativa.
     */
    public function render_groomer_tokens_section( $active_tab ) {
        $is_active = ( 'groomer-tokens' === $active_tab );
        ?>
        <section id="groomer-tokens" class="dps-section<?php echo $is_active ? ' dps-section--active' : ''; ?>" style="<?php echo $is_active ? '' : 'display:none;'; ?>">
            <h2><?php echo esc_html__( 'Gerenciamento de Logins de Groomers', 'dps-groomers-addon' ); ?></h2>
            <p class="dps-section-description">
                <?php echo esc_html__( 'Gere links de acesso (magic links) para que os groomers acessem seu portal sem precisar de senha.', 'dps-groomers-addon' ); ?>
            </p>
            
            <?php $this->render_groomer_tokens_list(); ?>
        </section>
        <?php
    }

    /**
     * Renderiza a lista de groomers com gerenciamento de tokens.
     *
     * @since 1.4.0
     */
    private function render_groomer_tokens_list() {
        $groomers = get_users( [ 'role' => 'dps_groomer' ] );
        $token_manager = DPS_Groomer_Token_Manager::get_instance();

        if ( empty( $groomers ) ) {
            echo '<p>' . esc_html__( 'Nenhum groomer cadastrado.', 'dps-groomers-addon' ) . '</p>';
            return;
        }
        ?>
        <table class="dps-table dps-groomers-tokens-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Groomer', 'dps-groomers-addon' ); ?></th>
                    <th><?php echo esc_html__( 'Email', 'dps-groomers-addon' ); ?></th>
                    <th><?php echo esc_html__( 'Tokens Ativos', 'dps-groomers-addon' ); ?></th>
                    <th><?php echo esc_html__( 'Último Acesso', 'dps-groomers-addon' ); ?></th>
                    <th><?php echo esc_html__( 'Ações', 'dps-groomers-addon' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $groomers as $groomer ) : 
                    $stats          = $token_manager->get_groomer_stats( $groomer->ID );
                    $active_tokens  = $token_manager->get_active_tokens( $groomer->ID );
                    $token_url      = get_transient( 'dps_groomer_token_url_' . $groomer->ID );
                    
                    // Limpa o transient após exibição
                    if ( $token_url ) {
                        delete_transient( 'dps_groomer_token_url_' . $groomer->ID );
                    }
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $groomer->display_name ); ?></strong>
                        </td>
                        <td><?php echo esc_html( $groomer->user_email ); ?></td>
                        <td>
                            <span class="dps-badge dps-badge--<?php echo $stats['active_tokens'] > 0 ? 'success' : 'neutral'; ?>">
                                <?php echo esc_html( $stats['active_tokens'] ); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if ( $stats['last_used_at'] ) {
                                echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $stats['last_used_at'] ) ) );
                            } else {
                                echo '<span class="dps-text-muted">' . esc_html__( 'Nunca', 'dps-groomers-addon' ) . '</span>';
                            }
                            ?>
                        </td>
                        <td class="dps-table-actions">
                            <form method="post" class="dps-inline-form">
                                <?php wp_nonce_field( 'dps_generate_groomer_token_' . $groomer->ID ); ?>
                                <input type="hidden" name="dps_groomer_token_action" value="generate" />
                                <input type="hidden" name="groomer_id" value="<?php echo esc_attr( $groomer->ID ); ?>" />
                                <select name="token_type" class="dps-select-small">
                                    <option value="login"><?php echo esc_html__( 'Temporário (30min)', 'dps-groomers-addon' ); ?></option>
                                    <option value="permanent"><?php echo esc_html__( 'Permanente', 'dps-groomers-addon' ); ?></option>
                                </select>
                                <button type="submit" class="dps-btn dps-btn--small dps-btn--primary">
                                    <?php echo esc_html__( 'Gerar Link', 'dps-groomers-addon' ); ?>
                                </button>
                            </form>
                            
                            <?php if ( $stats['active_tokens'] > 0 ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( 
                                    add_query_arg( [
                                        'dps_groomer_token_action' => 'revoke_all',
                                        'groomer_id'               => $groomer->ID,
                                    ] ),
                                    'dps_revoke_all_groomer_tokens_' . $groomer->ID
                                ) ); ?>" 
                                   class="dps-btn dps-btn--small dps-btn--danger"
                                   onclick="return confirm('<?php echo esc_js( __( 'Revogar todos os tokens deste groomer?', 'dps-groomers-addon' ) ); ?>');">
                                    <?php echo esc_html__( 'Revogar Todos', 'dps-groomers-addon' ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if ( $token_url ) : ?>
                    <tr class="dps-token-url-row">
                        <td colspan="5">
                            <div class="dps-token-url-box">
                                <label><?php echo esc_html__( 'Link de acesso gerado (copie agora, não será exibido novamente):', 'dps-groomers-addon' ); ?></label>
                                <div class="dps-token-url-input-group">
                                    <input type="text" value="<?php echo esc_url( $token_url ); ?>" readonly class="dps-token-url-input" onclick="this.select();" />
                                    <button type="button" class="dps-btn dps-btn--secondary dps-copy-token-btn" data-url="<?php echo esc_url( $token_url ); ?>">
                                        📋 <?php echo esc_html__( 'Copiar', 'dps-groomers-addon' ); ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $active_tokens ) ) : ?>
                    <tr class="dps-tokens-detail-row">
                        <td colspan="5">
                            <details class="dps-tokens-details">
                                <summary><?php echo esc_html__( 'Ver tokens ativos', 'dps-groomers-addon' ); ?></summary>
                                <table class="dps-table dps-table--nested">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html__( 'Tipo', 'dps-groomers-addon' ); ?></th>
                                            <th><?php echo esc_html__( 'Criado em', 'dps-groomers-addon' ); ?></th>
                                            <th><?php echo esc_html__( 'Expira em', 'dps-groomers-addon' ); ?></th>
                                            <th><?php echo esc_html__( 'IP', 'dps-groomers-addon' ); ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $active_tokens as $token_data ) : ?>
                                        <tr>
                                            <td>
                                                <span class="dps-badge dps-badge--<?php echo 'permanent' === $token_data['type'] ? 'info' : 'warning'; ?>">
                                                    <?php echo 'permanent' === $token_data['type'] ? esc_html__( 'Permanente', 'dps-groomers-addon' ) : esc_html__( 'Temporário', 'dps-groomers-addon' ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $token_data['created_at'] ) ) ); ?></td>
                                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $token_data['expires_at'] ) ) ); ?></td>
                                            <td><?php echo esc_html( $token_data['ip_created'] ?: '-' ); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url( wp_nonce_url( 
                                                    add_query_arg( [
                                                        'dps_groomer_token_action' => 'revoke',
                                                        'token_id'                 => $token_data['id'],
                                                        'groomer_id'               => $groomer->ID,
                                                    ] ),
                                                    'dps_revoke_groomer_token_' . $token_data['id']
                                                ) ); ?>" 
                                                   class="dps-btn dps-btn--tiny dps-btn--danger"
                                                   onclick="return confirm('<?php echo esc_js( __( 'Revogar este token?', 'dps-groomers-addon' ) ); ?>');">
                                                    <?php echo esc_html__( 'Revogar', 'dps-groomers-addon' ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renderiza o shortcode do portal do groomer.
     *
     * @since 1.4.0
     * @param array $atts Atributos do shortcode.
     * @return string HTML do portal.
     */
    public function render_groomer_portal_shortcode( $atts ) {
        $this->register_and_enqueue_assets();
        $this->enqueue_chartjs();

        $session_manager = DPS_Groomer_Session_Manager::get_instance();
        $groomer_id      = $session_manager->get_authenticated_groomer_id();

        // Se não autenticado via sessão, verifica se é admin
        if ( ! $groomer_id && current_user_can( 'manage_options' ) ) {
            // Admin pode visualizar o portal de qualquer groomer via parâmetro
            $groomer_id = isset( $_GET['groomer_id'] ) ? absint( $_GET['groomer_id'] ) : 0;
        }

        // Se ainda não tem groomer_id, verifica se o usuário logado é um groomer
        if ( ! $groomer_id && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            if ( in_array( 'dps_groomer', (array) $current_user->roles, true ) ) {
                $groomer_id = $current_user->ID;
            }
        }

        // Se não autenticado, mostra mensagem de erro ou formulário de login
        if ( ! $groomer_id ) {
            $error = isset( $_GET['groomer_token_error'] ) ? sanitize_text_field( wp_unslash( $_GET['groomer_token_error'] ) ) : '';
            return $this->render_login_required_message( $error );
        }

        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer || ! in_array( 'dps_groomer', (array) $groomer->roles, true ) ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não encontrado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        ob_start();
        ?>
        <div class="dps-groomer-portal">
            <header class="dps-groomer-portal__header">
                <div class="dps-groomer-portal__welcome">
                    <h1><?php echo esc_html( sprintf( __( 'Olá, %s!', 'dps-groomers-addon' ), $groomer->display_name ) ); ?></h1>
                    <p class="dps-groomer-portal__subtitle"><?php echo esc_html__( 'Bem-vindo ao seu portal de atendimentos', 'dps-groomers-addon' ); ?></p>
                </div>
                <div class="dps-groomer-portal__actions">
                    <a href="<?php echo esc_url( $session_manager->get_logout_url() ); ?>" class="dps-btn dps-btn--outline">
                        <?php echo esc_html__( 'Sair', 'dps-groomers-addon' ); ?>
                    </a>
                </div>
            </header>

            <nav class="dps-groomer-portal__nav">
                <ul class="dps-portal-tabs">
                    <li class="dps-portal-tabs__item dps-portal-tabs__item--active">
                        <a href="#portal-dashboard" class="dps-portal-tabs__link" data-tab="portal-dashboard">
                            📊 <?php echo esc_html__( 'Dashboard', 'dps-groomers-addon' ); ?>
                        </a>
                    </li>
                    <li class="dps-portal-tabs__item">
                        <a href="#portal-agenda" class="dps-portal-tabs__link" data-tab="portal-agenda">
                            📅 <?php echo esc_html__( 'Minha Agenda', 'dps-groomers-addon' ); ?>
                        </a>
                    </li>
                    <li class="dps-portal-tabs__item">
                        <a href="#portal-reviews" class="dps-portal-tabs__link" data-tab="portal-reviews">
                            ⭐ <?php echo esc_html__( 'Minhas Avaliações', 'dps-groomers-addon' ); ?>
                        </a>
                    </li>
                </ul>
            </nav>

            <main class="dps-groomer-portal__content">
                <!-- Dashboard Tab -->
                <section id="portal-dashboard" class="dps-portal-section dps-portal-section--active">
                    <?php echo $this->render_groomer_dashboard_shortcode( [ 'groomer_id' => $groomer_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <!-- Agenda Tab -->
                <section id="portal-agenda" class="dps-portal-section" style="display:none;">
                    <?php echo $this->render_groomer_agenda_shortcode( [ 'groomer_id' => $groomer_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <!-- Reviews Tab -->
                <section id="portal-reviews" class="dps-portal-section" style="display:none;">
                    <?php echo $this->render_reviews_list_shortcode( [ 'groomer_id' => $groomer_id, 'limit' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza mensagem de login necessário.
     *
     * @since 1.4.0
     * @param string $error Tipo de erro.
     * @return string HTML da mensagem.
     */
    private function render_login_required_message( $error = '' ) {
        $error_messages = [
            'invalid'     => __( 'O link de acesso é inválido ou expirou. Solicite um novo link ao administrador.', 'dps-groomers-addon' ),
            'auth_failed' => __( 'Não foi possível autenticar. Tente novamente ou solicite um novo link.', 'dps-groomers-addon' ),
        ];

        ob_start();
        ?>
        <div class="dps-groomer-login-required">
            <?php if ( $error && isset( $error_messages[ $error ] ) ) : ?>
                <div class="dps-groomers-notice dps-groomers-notice--error">
                    <?php echo esc_html( $error_messages[ $error ] ); ?>
                </div>
            <?php endif; ?>

            <div class="dps-login-box">
                <h2><?php echo esc_html__( 'Portal do Groomer', 'dps-groomers-addon' ); ?></h2>
                <p><?php echo esc_html__( 'Para acessar seu portal, utilize o link de acesso enviado pelo administrador.', 'dps-groomers-addon' ); ?></p>
                <p class="dps-login-hint">
                    <?php echo esc_html__( 'Não tem um link de acesso? Entre em contato com o administrador para solicitar.', 'dps-groomers-addon' ); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o shortcode de login do groomer.
     *
     * @since 1.4.0
     * @param array $atts Atributos do shortcode.
     * @return string HTML do login.
     */
    public function render_groomer_login_shortcode( $atts ) {
        $this->register_and_enqueue_assets();

        $session_manager = DPS_Groomer_Session_Manager::get_instance();
        
        // Se já está autenticado, redireciona para o portal
        if ( $session_manager->is_groomer_authenticated() ) {
            $portal_url = $this->get_portal_page_url();
            return '<div class="dps-groomers-notice dps-groomers-notice--info">' . 
                sprintf(
                    /* translators: %s: portal link */
                    esc_html__( 'Você já está autenticado. %s', 'dps-groomers-addon' ),
                    '<a href="' . esc_url( $portal_url ) . '">' . esc_html__( 'Acessar Portal', 'dps-groomers-addon' ) . '</a>'
                ) . 
                '</div>';
        }

        return $this->render_login_required_message();
    }

    /**
     * Registra o CPT de avaliações de groomers.
     *
     * @since 1.3.0
     */
    public function register_review_post_type() {
        $labels = [
            'name'               => __( 'Avaliações de Groomers', 'dps-groomers-addon' ),
            'singular_name'      => __( 'Avaliação', 'dps-groomers-addon' ),
            'menu_name'          => __( 'Avaliações', 'dps-groomers-addon' ),
            'add_new'            => __( 'Adicionar Nova', 'dps-groomers-addon' ),
            'add_new_item'       => __( 'Adicionar Nova Avaliação', 'dps-groomers-addon' ),
            'edit_item'          => __( 'Editar Avaliação', 'dps-groomers-addon' ),
            'new_item'           => __( 'Nova Avaliação', 'dps-groomers-addon' ),
            'view_item'          => __( 'Ver Avaliação', 'dps-groomers-addon' ),
            'search_items'       => __( 'Buscar Avaliações', 'dps-groomers-addon' ),
            'not_found'          => __( 'Nenhuma avaliação encontrada', 'dps-groomers-addon' ),
            'not_found_in_trash' => __( 'Nenhuma avaliação na lixeira', 'dps-groomers-addon' ),
        ];

        $args = [
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => [ 'title', 'editor' ],
            'has_archive'  => false,
            'rewrite'      => false,
        ];

        register_post_type( 'dps_groomer_review', $args );
    }

    /**
     * Migra dados de groomers existentes para os novos campos.
     *
     * Adiciona _dps_staff_type = 'groomer' e _dps_is_freelancer = '0'
     * para todos os usuários com role dps_groomer que não têm esses campos.
     *
     * @since 1.5.0
     */
    public function maybe_migrate_staff_data() {
        // Verifica se a migração já foi feita
        $migration_done = get_option( 'dps_groomers_staff_migration_done', false );
        if ( $migration_done ) {
            return;
        }

        // Busca todos os groomers
        $groomers = get_users( [
            'role'   => 'dps_groomer',
            'fields' => 'ids',
        ] );

        if ( empty( $groomers ) ) {
            update_option( 'dps_groomers_staff_migration_done', true );
            return;
        }

        foreach ( $groomers as $groomer_id ) {
            // Adiciona staff_type se não existir
            $staff_type = get_user_meta( $groomer_id, '_dps_staff_type', true );
            if ( empty( $staff_type ) ) {
                update_user_meta( $groomer_id, '_dps_staff_type', 'groomer' );
            }

            // Adiciona is_freelancer se não existir
            $is_freelancer = get_user_meta( $groomer_id, '_dps_is_freelancer', true );
            if ( '' === $is_freelancer ) {
                update_user_meta( $groomer_id, '_dps_is_freelancer', '0' );
            }
        }

        update_option( 'dps_groomers_staff_migration_done', true );
    }

    /**
     * Retorna os tipos de profissionais disponíveis com labels traduzidos.
     *
     * @since 1.5.0
     *
     * @return array Array com slug => label traduzido.
     */
    public static function get_staff_types() {
        return [
            'groomer'  => __( 'Groomer', 'dps-groomers-addon' ),
            'banhista' => __( 'Banhista', 'dps-groomers-addon' ),
            'auxiliar' => __( 'Auxiliar', 'dps-groomers-addon' ),
            'recepcao' => __( 'Recepção', 'dps-groomers-addon' ),
        ];
    }

    /**
     * Retorna o label traduzido de um tipo de profissional.
     *
     * @since 1.5.0
     *
     * @param string $type Tipo do profissional.
     * @return string Label traduzido ou o próprio type se não encontrado.
     */
    public static function get_staff_type_label( $type ) {
        $types = self::get_staff_types();
        return isset( $types[ $type ] ) ? $types[ $type ] : ucfirst( $type );
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
        $staff_type = isset( $_POST['dps_staff_type'] ) ? sanitize_key( wp_unslash( $_POST['dps_staff_type'] ) ) : '';
        $is_freelancer = isset( $_POST['dps_is_freelancer'] ) ? '1' : '0';

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
            
            // Atualizar staff_type se fornecido
            if ( $staff_type ) {
                $valid_types = array_keys( self::get_staff_types() );
                if ( in_array( $staff_type, $valid_types, true ) ) {
                    update_user_meta( $groomer_id, '_dps_staff_type', $staff_type );
                }
            }
            
            // Atualizar is_freelancer
            update_user_meta( $groomer_id, '_dps_is_freelancer', $is_freelancer );
            
            DPS_Message_Helper::add_success( __( 'Profissional atualizado com sucesso.', 'dps-groomers-addon' ) );
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
        // Password: wp_unslash removes PHP magic quotes, wp_create_user handles hashing
        // Do NOT sanitize password to preserve special characters
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
        $staff_type = isset( $_POST['dps_staff_type'] ) ? sanitize_key( wp_unslash( $_POST['dps_staff_type'] ) ) : 'groomer';
        $is_freelancer = isset( $_POST['dps_is_freelancer'] ) ? '1' : '0';
        
        // Validar staff_type
        $valid_types = array_keys( self::get_staff_types() );
        if ( ! in_array( $staff_type, $valid_types, true ) ) {
            $staff_type = 'groomer';
        }
        
        update_user_meta( $user_id, '_dps_groomer_status', 'active' ); // Novo groomer sempre começa ativo
        update_user_meta( $user_id, '_dps_groomer_phone', $phone );
        update_user_meta( $user_id, '_dps_groomer_commission_rate', $commission );
        update_user_meta( $user_id, '_dps_staff_type', $staff_type );
        update_user_meta( $user_id, '_dps_is_freelancer', $is_freelancer );

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
                            <legend><?php echo esc_html__( 'Tipo e Vínculo', 'dps-groomers-addon' ); ?></legend>
                            <div class="dps-form-row dps-form-row--2col">
                                <div class="dps-form-field">
                                    <label for="dps_staff_type"><?php echo esc_html__( 'Tipo de profissional', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                                    <select name="dps_staff_type" id="dps_staff_type" required>
                                        <?php foreach ( self::get_staff_types() as $type_slug => $type_label ) : ?>
                                            <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $type_slug, 'groomer' ); ?>>
                                                <?php echo esc_html( $type_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="dps-form-field">
                                    <label class="dps-checkbox-label">
                                        <input type="checkbox" name="dps_is_freelancer" id="dps_is_freelancer" value="1" />
                                        <?php echo esc_html__( 'É freelancer (autônomo)', 'dps-groomers-addon' ); ?>
                                    </label>
                                    <small class="dps-field-help"><?php echo esc_html__( 'Marque se o profissional não é funcionário fixo.', 'dps-groomers-addon' ); ?></small>
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
                        
                        <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Criar profissional', 'dps-groomers-addon' ); ?></button>
                    </form>
                </div>

                <div class="dps-groomers-list-container">
                    <h3 class="dps-field-group-title"><?php echo esc_html__( 'Profissionais cadastrados', 'dps-groomers-addon' ); ?></h3>
                    
                    <!-- Filtros da listagem -->
                    <div class="dps-groomers-filters">
                        <form method="get" class="dps-inline-filters">
                            <input type="hidden" name="tab" value="groomers" />
                            <div class="dps-filter-group">
                                <label for="filter_staff_type"><?php echo esc_html__( 'Tipo:', 'dps-groomers-addon' ); ?></label>
                                <select name="filter_staff_type" id="filter_staff_type">
                                    <option value=""><?php echo esc_html__( 'Todos', 'dps-groomers-addon' ); ?></option>
                                    <?php 
                                    $filter_type = isset( $_GET['filter_staff_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_staff_type'] ) ) : '';
                                    foreach ( self::get_staff_types() as $type_slug => $type_label ) : ?>
                                        <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $filter_type, $type_slug ); ?>>
                                            <?php echo esc_html( $type_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="dps-filter-group">
                                <label for="filter_freelancer"><?php echo esc_html__( 'Freelancer:', 'dps-groomers-addon' ); ?></label>
                                <select name="filter_freelancer" id="filter_freelancer">
                                    <?php $filter_freelancer = isset( $_GET['filter_freelancer'] ) ? sanitize_key( wp_unslash( $_GET['filter_freelancer'] ) ) : ''; ?>
                                    <option value=""><?php echo esc_html__( 'Todos', 'dps-groomers-addon' ); ?></option>
                                    <option value="1" <?php selected( $filter_freelancer, '1' ); ?>><?php echo esc_html__( 'Sim', 'dps-groomers-addon' ); ?></option>
                                    <option value="0" <?php selected( $filter_freelancer, '0' ); ?>><?php echo esc_html__( 'Não', 'dps-groomers-addon' ); ?></option>
                                </select>
                            </div>
                            <div class="dps-filter-group">
                                <label for="filter_status"><?php echo esc_html__( 'Status:', 'dps-groomers-addon' ); ?></label>
                                <select name="filter_status" id="filter_status">
                                    <?php $filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : ''; ?>
                                    <option value=""><?php echo esc_html__( 'Todos', 'dps-groomers-addon' ); ?></option>
                                    <option value="active" <?php selected( $filter_status, 'active' ); ?>><?php echo esc_html__( 'Ativos', 'dps-groomers-addon' ); ?></option>
                                    <option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php echo esc_html__( 'Inativos', 'dps-groomers-addon' ); ?></option>
                                </select>
                            </div>
                            <button type="submit" class="dps-btn dps-btn--small dps-btn--secondary"><?php echo esc_html__( 'Filtrar', 'dps-groomers-addon' ); ?></button>
                            <?php if ( $filter_type || $filter_freelancer !== '' || $filter_status ) : ?>
                                <a href="?tab=groomers" class="dps-btn dps-btn--small dps-btn--outline"><?php echo esc_html__( 'Limpar', 'dps-groomers-addon' ); ?></a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <?php 
                    // Aplicar filtros à lista de groomers
                    $filtered_groomers = $groomers;
                    if ( $filter_type || $filter_freelancer !== '' || $filter_status ) {
                        $filtered_groomers = array_filter( $groomers, function( $groomer ) use ( $filter_type, $filter_freelancer, $filter_status ) {
                            // Filtrar por tipo
                            if ( $filter_type ) {
                                $staff_type = get_user_meta( $groomer->ID, '_dps_staff_type', true );
                                if ( $staff_type !== $filter_type ) {
                                    return false;
                                }
                            }
                            // Filtrar por freelancer
                            if ( $filter_freelancer !== '' ) {
                                $is_freelancer = get_user_meta( $groomer->ID, '_dps_is_freelancer', true );
                                if ( $is_freelancer !== $filter_freelancer ) {
                                    return false;
                                }
                            }
                            // Filtrar por status
                            if ( $filter_status ) {
                                $status = get_user_meta( $groomer->ID, '_dps_groomer_status', true );
                                if ( empty( $status ) ) {
                                    $status = 'active';
                                }
                                if ( $status !== $filter_status ) {
                                    return false;
                                }
                            }
                            return true;
                        } );
                    }
                    ?>
                    
                    <table class="dps-groomers-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Nome', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Tipo', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Freelancer', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Comissão', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Status', 'dps-groomers-addon' ); ?></th>
                                <th><?php echo esc_html__( 'Ações', 'dps-groomers-addon' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $filtered_groomers ) ) : ?>
                            <tr>
                                <td colspan="6" class="dps-empty-message"><?php echo esc_html__( 'Nenhum profissional encontrado. Use o formulário ao lado para adicionar.', 'dps-groomers-addon' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $filtered_groomers as $groomer ) : 
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
                                $staff_type         = get_user_meta( $groomer->ID, '_dps_staff_type', true );
                                $is_freelancer      = get_user_meta( $groomer->ID, '_dps_is_freelancer', true );
                                
                                // Defaults
                                if ( empty( $groomer_status ) ) {
                                    $groomer_status = 'active';
                                }
                                if ( empty( $staff_type ) ) {
                                    $staff_type = 'groomer';
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
                                        <span class="dps-badge dps-badge--type-<?php echo esc_attr( $staff_type ); ?>">
                                            <?php echo esc_html( self::get_staff_type_label( $staff_type ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ( $is_freelancer === '1' ) : ?>
                                            <span class="dps-badge dps-badge--freelancer"><?php echo esc_html__( 'Sim', 'dps-groomers-addon' ); ?></span>
                                        <?php else : ?>
                                            <span class="dps-text-muted"><?php echo esc_html__( 'Não', 'dps-groomers-addon' ); ?></span>
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
                                            data-staff-type="<?php echo esc_attr( $staff_type ); ?>"
                                            data-is-freelancer="<?php echo esc_attr( $is_freelancer ); ?>"
                                            title="<?php echo esc_attr__( 'Editar profissional', 'dps-groomers-addon' ); ?>">
                                            ✏️ <?php echo esc_html__( 'Editar', 'dps-groomers-addon' ); ?>
                                        </button>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" 
                                            class="dps-action-link dps-action-link--delete dps-delete-groomer"
                                            data-groomer-name="<?php echo esc_attr( $groomer->display_name ? $groomer->display_name : $groomer->user_login ); ?>"
                                            data-appointments="<?php echo esc_attr( $appointments_count ); ?>"
                                            title="<?php echo esc_attr__( 'Excluir profissional', 'dps-groomers-addon' ); ?>">
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

            <!-- Modal de Edição de Profissional -->
            <div id="dps-edit-groomer-modal" class="dps-modal" style="display: none;">
                <div class="dps-modal-content">
                    <div class="dps-modal-header">
                        <h4><?php echo esc_html__( 'Editar Profissional', 'dps-groomers-addon' ); ?></h4>
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
                                    <label for="edit_staff_type"><?php echo esc_html__( 'Tipo de profissional', 'dps-groomers-addon' ); ?></label>
                                    <select name="dps_staff_type" id="edit_staff_type">
                                        <?php foreach ( self::get_staff_types() as $type_slug => $type_label ) : ?>
                                            <option value="<?php echo esc_attr( $type_slug ); ?>">
                                                <?php echo esc_html( $type_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="dps-form-field">
                                    <label class="dps-checkbox-label">
                                        <input type="checkbox" name="dps_is_freelancer" id="edit_is_freelancer" value="1" />
                                        <?php echo esc_html__( 'É freelancer (autônomo)', 'dps-groomers-addon' ); ?>
                                    </label>
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

            <div class="dps-commissions-report">
                <?php echo $this->render_commissions_report( $groomers ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o relatório de comissões de todos os groomers.
     *
     * @since 1.3.0
     *
     * @param WP_User[] $groomers Lista de profissionais.
     * @return string HTML do relatório de comissões.
     */
    private function render_commissions_report( $groomers ) {
        $start_date = isset( $_GET['dps_commission_start'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_commission_start'] ) ) : date( 'Y-m-01' );
        $end_date   = isset( $_GET['dps_commission_end'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_commission_end'] ) ) : date( 'Y-m-d' );
        
        $filters_ok = true;
        if ( isset( $_GET['dps_commission_nonce'] ) ) {
            if ( ! wp_verify_nonce( wp_unslash( $_GET['dps_commission_nonce'] ), 'dps_commission_report' ) ) {
                $filters_ok = false;
            }
        }

        // Calcular comissões para cada groomer
        $commissions_data = [];
        $total_revenue    = 0;
        $total_commission = 0;

        if ( $filters_ok && ! empty( $groomers ) ) {
            foreach ( $groomers as $groomer ) {
                $commission_rate = (float) get_user_meta( $groomer->ID, '_dps_groomer_commission_rate', true );
                
                // Buscar atendimentos do período
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
                                'value'   => '"' . $groomer->ID . '"',
                                'compare' => 'LIKE',
                            ],
                            [
                                'key'     => 'appointment_status',
                                'value'   => 'realizado',
                                'compare' => '=',
                            ],
                        ],
                    ]
                );

                $groomer_revenue    = $this->calculate_total_revenue( $appointments );
                $groomer_commission = $groomer_revenue * ( $commission_rate / 100 );

                $commissions_data[] = [
                    'id'              => $groomer->ID,
                    'name'            => $groomer->display_name ? $groomer->display_name : $groomer->user_login,
                    'appointments'    => count( $appointments ),
                    'revenue'         => $groomer_revenue,
                    'commission_rate' => $commission_rate,
                    'commission'      => $groomer_commission,
                ];

                $total_revenue    += $groomer_revenue;
                $total_commission += $groomer_commission;
            }
        }

        ob_start();
        ?>
        <h4 class="dps-field-group-title"><?php echo esc_html__( 'Relatório de Comissões', 'dps-groomers-addon' ); ?></h4>
        
        <form method="get" action="" class="dps-report-filters">
            <input type="hidden" name="tab" value="groomers" />
            <?php wp_nonce_field( 'dps_commission_report', 'dps_commission_nonce' ); ?>
            
            <div class="dps-form-field">
                <label for="dps_commission_start"><?php echo esc_html__( 'Data inicial', 'dps-groomers-addon' ); ?></label>
                <input type="date" name="dps_commission_start" id="dps_commission_start" value="<?php echo esc_attr( $start_date ); ?>" required />
            </div>
            
            <div class="dps-form-field">
                <label for="dps_commission_end"><?php echo esc_html__( 'Data final', 'dps-groomers-addon' ); ?></label>
                <input type="date" name="dps_commission_end" id="dps_commission_end" value="<?php echo esc_attr( $end_date ); ?>" required />
            </div>
            
            <div class="dps-report-actions">
                <button type="submit" class="dps-btn dps-btn--primary"><?php echo esc_html__( 'Calcular comissões', 'dps-groomers-addon' ); ?></button>
            </div>
        </form>

        <?php if ( ! empty( $commissions_data ) ) : ?>
            <!-- Cards de totais -->
            <div class="dps-metrics-grid" style="margin-bottom: 24px;">
                <div class="dps-metric-card dps-metric-card--success">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Receita Total', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_revenue, 2 ) ); ?></span>
                </div>
                <div class="dps-metric-card dps-metric-card--warning">
                    <span class="dps-metric-card__label"><?php echo esc_html__( 'Total a Pagar', 'dps-groomers-addon' ); ?></span>
                    <span class="dps-metric-card__value">R$ <?php echo esc_html( number_format_i18n( $total_commission, 2 ) ); ?></span>
                </div>
            </div>

            <!-- Tabela de comissões -->
            <table class="dps-report-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Groomer', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Atendimentos', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Receita', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Taxa (%)', 'dps-groomers-addon' ); ?></th>
                        <th><?php echo esc_html__( 'Comissão', 'dps-groomers-addon' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $commissions_data as $data ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
                            <td><?php echo esc_html( $data['appointments'] ); ?></td>
                            <td>R$ <?php echo esc_html( number_format_i18n( $data['revenue'], 2 ) ); ?></td>
                            <td>
                                <?php if ( $data['commission_rate'] > 0 ) : ?>
                                    <?php echo esc_html( number_format_i18n( $data['commission_rate'], 1 ) ); ?>%
                                <?php else : ?>
                                    <span class="dps-no-data">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $data['commission'] > 0 ) : ?>
                                    <strong>R$ <?php echo esc_html( number_format_i18n( $data['commission'], 2 ) ); ?></strong>
                                <?php else : ?>
                                    <span class="dps-no-data">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong><?php echo esc_html__( 'TOTAL', 'dps-groomers-addon' ); ?></strong></td>
                        <td><strong><?php echo esc_html( array_sum( array_column( $commissions_data, 'appointments' ) ) ); ?></strong></td>
                        <td><strong>R$ <?php echo esc_html( number_format_i18n( $total_revenue, 2 ) ); ?></strong></td>
                        <td>-</td>
                        <td><strong>R$ <?php echo esc_html( number_format_i18n( $total_commission, 2 ) ); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
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
        $atts = shortcode_atts(
            [
                'groomer_id' => 0,
            ],
            $atts,
            'dps_groomer_dashboard'
        );

        // Enfileirar assets
        $this->register_and_enqueue_assets();
        $this->enqueue_chartjs();

        // Determinar groomer_id: parâmetro > sessão > usuário logado
        $groomer_id = absint( $atts['groomer_id'] );
        
        if ( ! $groomer_id ) {
            // Tenta obter da sessão
            $session_manager = DPS_Groomer_Session_Manager::get_instance();
            $groomer_id      = $session_manager->get_authenticated_groomer_id();
        }

        if ( ! $groomer_id && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            
            // Se for admin, pode selecionar qualquer groomer via GET
            if ( current_user_can( 'manage_options' ) && isset( $_GET['groomer_id'] ) ) {
                $groomer_id = absint( $_GET['groomer_id'] );
            } elseif ( in_array( 'dps_groomer', (array) $current_user->roles, true ) ) {
                $groomer_id = $current_user->ID;
            }
        }

        if ( ! $groomer_id ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Você precisa estar logado para acessar o dashboard.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Validar se é um groomer válido
        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer || ! in_array( 'dps_groomer', (array) $groomer->roles, true ) ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não encontrado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Período do relatório (padrão: últimos 30 dias)
        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-d' );
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );

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

    /**
     * Renderiza o shortcode de agenda do groomer.
     *
     * Mostra os atendimentos do groomer em formato de agenda semanal/diária.
     *
     * @since 1.3.0
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML da agenda.
     */
    public function render_groomer_agenda_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'groomer_id' => 0,
            ],
            $atts,
            'dps_groomer_agenda'
        );

        // Enfileirar assets
        $this->register_and_enqueue_assets();

        // Determinar groomer_id: parâmetro > sessão > usuário logado
        $groomer_id = absint( $atts['groomer_id'] );
        
        if ( ! $groomer_id ) {
            // Tenta obter da sessão
            $session_manager = DPS_Groomer_Session_Manager::get_instance();
            $groomer_id      = $session_manager->get_authenticated_groomer_id();
        }

        if ( ! $groomer_id && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            
            // Se for admin, pode selecionar qualquer groomer via GET
            if ( current_user_can( 'manage_options' ) && isset( $_GET['groomer_id'] ) ) {
                $groomer_id = absint( $_GET['groomer_id'] );
            } elseif ( in_array( 'dps_groomer', (array) $current_user->roles, true ) ) {
                $groomer_id = $current_user->ID;
            }
        }

        if ( ! $groomer_id ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Você precisa estar logado para acessar a agenda.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Validar se é um groomer válido
        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer || ! in_array( 'dps_groomer', (array) $groomer->roles, true ) ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não encontrado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Data base (padrão: hoje)
        $base_date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : gmdate( 'Y-m-d' );
        $view_type = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'week';

        // Calcular período
        if ( $view_type === 'day' ) {
            $start_date = $base_date;
            $end_date   = $base_date;
        } else {
            // Semana (segunda a domingo)
            $start_date = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $base_date ) ) );
            $end_date   = gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( $base_date ) ) );
        }

        // Buscar atendimentos do período
        $appointments = get_posts(
            [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_time',
                'order'          => 'ASC',
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

        // Organizar por dia
        $appointments_by_day = [];
        foreach ( $appointments as $appointment ) {
            $date = get_post_meta( $appointment->ID, 'appointment_date', true );
            if ( ! isset( $appointments_by_day[ $date ] ) ) {
                $appointments_by_day[ $date ] = [];
            }
            $appointments_by_day[ $date ][] = $appointment;
        }

        $groomer = get_user_by( 'id', $groomer_id );
        $groomer_name = $groomer ? ( $groomer->display_name ? $groomer->display_name : $groomer->user_login ) : '';

        // Gerar dias da semana
        $week_days = [];
        $current = strtotime( $start_date );
        $end = strtotime( $end_date );
        while ( $current <= $end ) {
            $week_days[] = date( 'Y-m-d', $current );
            $current = strtotime( '+1 day', $current );
        }

        // URLs de navegação
        $prev_date = date( 'Y-m-d', strtotime( '-1 week', strtotime( $base_date ) ) );
        $next_date = date( 'Y-m-d', strtotime( '+1 week', strtotime( $base_date ) ) );
        $today_date = date( 'Y-m-d' );

        ob_start();
        ?>
        <div class="dps-groomer-agenda">
            <div class="dps-agenda-header">
                <h2 class="dps-section-title">
                    <?php 
                    echo esc_html( 
                        sprintf( 
                            /* translators: %s: groomer name */
                            __( 'Agenda de %s', 'dps-groomers-addon' ), 
                            $groomer_name 
                        ) 
                    ); 
                    ?>
                </h2>
                
                <!-- Navegação -->
                <div class="dps-agenda-nav">
                    <a href="?date=<?php echo esc_attr( $prev_date ); ?>&view=<?php echo esc_attr( $view_type ); ?><?php echo current_user_can( 'manage_options' ) ? '&groomer_id=' . esc_attr( $groomer_id ) : ''; ?>" class="dps-btn dps-btn--secondary">
                        ← <?php echo esc_html__( 'Anterior', 'dps-groomers-addon' ); ?>
                    </a>
                    <a href="?date=<?php echo esc_attr( $today_date ); ?>&view=<?php echo esc_attr( $view_type ); ?><?php echo current_user_can( 'manage_options' ) ? '&groomer_id=' . esc_attr( $groomer_id ) : ''; ?>" class="dps-btn dps-btn--primary">
                        <?php echo esc_html__( 'Hoje', 'dps-groomers-addon' ); ?>
                    </a>
                    <a href="?date=<?php echo esc_attr( $next_date ); ?>&view=<?php echo esc_attr( $view_type ); ?><?php echo current_user_can( 'manage_options' ) ? '&groomer_id=' . esc_attr( $groomer_id ) : ''; ?>" class="dps-btn dps-btn--secondary">
                        <?php echo esc_html__( 'Próxima', 'dps-groomers-addon' ); ?> →
                    </a>
                </div>
            </div>

            <div class="dps-agenda-period">
                <?php 
                echo esc_html( 
                    date_i18n( 'd/m/Y', strtotime( $start_date ) ) . 
                    ' - ' . 
                    date_i18n( 'd/m/Y', strtotime( $end_date ) ) 
                ); 
                ?>
            </div>

            <!-- Calendário semanal -->
            <div class="dps-agenda-week">
                <?php foreach ( $week_days as $day ) : 
                    $is_today = ( $day === $today_date );
                    $day_appointments = isset( $appointments_by_day[ $day ] ) ? $appointments_by_day[ $day ] : [];
                    $day_class = $is_today ? 'dps-agenda-day dps-agenda-day--today' : 'dps-agenda-day';
                    ?>
                    <div class="<?php echo esc_attr( $day_class ); ?>">
                        <div class="dps-agenda-day__header">
                            <span class="dps-agenda-day__name"><?php echo esc_html( date_i18n( 'D', strtotime( $day ) ) ); ?></span>
                            <span class="dps-agenda-day__date"><?php echo esc_html( date_i18n( 'd/m', strtotime( $day ) ) ); ?></span>
                        </div>
                        <div class="dps-agenda-day__content">
                            <?php if ( empty( $day_appointments ) ) : ?>
                                <div class="dps-agenda-empty">
                                    <?php echo esc_html__( 'Livre', 'dps-groomers-addon' ); ?>
                                </div>
                            <?php else : ?>
                                <?php foreach ( $day_appointments as $appointment ) :
                                    $time      = get_post_meta( $appointment->ID, 'appointment_time', true );
                                    $client_id = get_post_meta( $appointment->ID, 'appointment_client_id', true );
                                    $pet_ids   = get_post_meta( $appointment->ID, 'appointment_pet_ids', true );
                                    $status    = get_post_meta( $appointment->ID, 'appointment_status', true );
                                    $client    = $client_id ? get_the_title( $client_id ) : '-';
                                    
                                    $pet_names = [];
                                    if ( is_array( $pet_ids ) ) {
                                        foreach ( $pet_ids as $pet_id ) {
                                            $pet_name = get_the_title( $pet_id );
                                            if ( $pet_name ) {
                                                $pet_names[] = $pet_name;
                                            }
                                        }
                                    }
                                    $pets = ! empty( $pet_names ) ? implode( ', ', $pet_names ) : '-';
                                    
                                    $status_class = 'dps-agenda-item dps-agenda-item--' . sanitize_html_class( $status ? $status : 'pendente' );
                                    ?>
                                    <div class="<?php echo esc_attr( $status_class ); ?>">
                                        <span class="dps-agenda-item__time"><?php echo esc_html( $time ); ?></span>
                                        <span class="dps-agenda-item__client"><?php echo esc_html( $client ); ?></span>
                                        <span class="dps-agenda-item__pet"><?php echo esc_html( $pets ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o shortcode do formulário de avaliação.
     *
     * Permite que clientes avaliem groomers após atendimento.
     *
     * @since 1.3.0
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML do formulário.
     */
    public function render_review_form_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'groomer_id'     => 0,
                'appointment_id' => 0,
            ],
            $atts,
            'dps_groomer_review'
        );

        $this->register_and_enqueue_assets();

        // Permitir passagem via GET
        $groomer_id     = $atts['groomer_id'] ? absint( $atts['groomer_id'] ) : ( isset( $_GET['groomer_id'] ) ? absint( $_GET['groomer_id'] ) : 0 );
        $appointment_id = $atts['appointment_id'] ? absint( $atts['appointment_id'] ) : ( isset( $_GET['appointment_id'] ) ? absint( $_GET['appointment_id'] ) : 0 );

        if ( ! $groomer_id ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não especificado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer || ! in_array( 'dps_groomer', (array) $groomer->roles, true ) ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não encontrado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        // Processar submissão
        $message = '';
        if ( isset( $_POST['dps_review_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['dps_review_nonce'] ), 'dps_submit_review' ) ) {
            $rating  = isset( $_POST['dps_review_rating'] ) ? absint( $_POST['dps_review_rating'] ) : 0;
            $comment = isset( $_POST['dps_review_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_review_comment'] ) ) : '';
            $name    = isset( $_POST['dps_review_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dps_review_name'] ) ) : '';

            if ( $rating >= 1 && $rating <= 5 ) {
                $review_id = wp_insert_post(
                    [
                        'post_type'    => 'dps_groomer_review',
                        'post_title'   => sprintf(
                            /* translators: %1$s: groomer name, %2$d: rating */
                            __( 'Avaliação de %1$s - %2$d estrelas', 'dps-groomers-addon' ),
                            $groomer->display_name,
                            $rating
                        ),
                        'post_content' => $comment,
                        'post_status'  => 'publish',
                    ]
                );

                if ( $review_id && ! is_wp_error( $review_id ) ) {
                    update_post_meta( $review_id, '_dps_review_groomer_id', $groomer_id );
                    update_post_meta( $review_id, '_dps_review_rating', $rating );
                    update_post_meta( $review_id, '_dps_review_name', $name );
                    if ( $appointment_id ) {
                        update_post_meta( $review_id, '_dps_review_appointment_id', $appointment_id );
                    }

                    $message = '<div class="dps-groomers-notice dps-groomers-notice--success">' . 
                        esc_html__( 'Obrigado pela sua avaliação!', 'dps-groomers-addon' ) . 
                        '</div>';
                }
            } else {
                $message = '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                    esc_html__( 'Por favor, selecione uma avaliação de 1 a 5 estrelas.', 'dps-groomers-addon' ) . 
                    '</div>';
            }
        }

        $groomer_name = $groomer->display_name ? $groomer->display_name : $groomer->user_login;

        ob_start();
        ?>
        <div class="dps-review-form-container">
            <h3>
                <?php 
                echo esc_html( 
                    sprintf( 
                        /* translators: %s: groomer name */
                        __( 'Avaliar %s', 'dps-groomers-addon' ), 
                        $groomer_name 
                    ) 
                ); 
                ?>
            </h3>

            <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ( empty( $message ) || strpos( $message, 'error' ) !== false ) : ?>
            <form method="post" class="dps-review-form">
                <?php wp_nonce_field( 'dps_submit_review', 'dps_review_nonce' ); ?>
                
                <div class="dps-form-field">
                    <label><?php echo esc_html__( 'Sua avaliação', 'dps-groomers-addon' ); ?> <span class="dps-required">*</span></label>
                    <div class="dps-star-rating">
                        <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                            <input type="radio" name="dps_review_rating" id="dps_star_<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i ); ?>" required />
                            <label for="dps_star_<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( $i ); ?> <?php echo esc_attr( _n( 'estrela', 'estrelas', $i, 'dps-groomers-addon' ) ); ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="dps-form-field">
                    <label for="dps_review_name"><?php echo esc_html__( 'Seu nome', 'dps-groomers-addon' ); ?></label>
                    <input type="text" name="dps_review_name" id="dps_review_name" placeholder="<?php echo esc_attr__( 'Opcional', 'dps-groomers-addon' ); ?>" />
                </div>

                <div class="dps-form-field">
                    <label for="dps_review_comment"><?php echo esc_html__( 'Comentário', 'dps-groomers-addon' ); ?></label>
                    <textarea name="dps_review_comment" id="dps_review_comment" rows="4" placeholder="<?php echo esc_attr__( 'Conte como foi sua experiência...', 'dps-groomers-addon' ); ?>"></textarea>
                </div>

                <button type="submit" class="dps-btn dps-btn--primary">
                    <?php echo esc_html__( 'Enviar avaliação', 'dps-groomers-addon' ); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o shortcode de exibição de avaliações.
     *
     * @since 1.3.0
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML das avaliações.
     */
    public function render_reviews_list_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'groomer_id' => 0,
                'limit'      => 10,
            ],
            $atts,
            'dps_groomer_reviews'
        );

        $this->register_and_enqueue_assets();

        $groomer_id = absint( $atts['groomer_id'] );
        $limit      = absint( $atts['limit'] );

        if ( ! $groomer_id ) {
            return '<div class="dps-groomers-notice dps-groomers-notice--error">' . 
                esc_html__( 'Groomer não especificado.', 'dps-groomers-addon' ) . 
                '</div>';
        }

        $groomer = get_user_by( 'id', $groomer_id );
        if ( ! $groomer ) {
            return '';
        }

        // Buscar avaliações
        $reviews = get_posts(
            [
                'post_type'      => 'dps_groomer_review',
                'posts_per_page' => $limit,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'   => '_dps_review_groomer_id',
                        'value' => $groomer_id,
                    ],
                ],
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        // Calcular média
        $total_rating = 0;
        $review_count = count( $reviews );
        foreach ( $reviews as $review ) {
            $total_rating += (int) get_post_meta( $review->ID, '_dps_review_rating', true );
        }
        $avg_rating = $review_count > 0 ? round( $total_rating / $review_count, 1 ) : 0;

        $groomer_name = $groomer->display_name ? $groomer->display_name : $groomer->user_login;

        ob_start();
        ?>
        <div class="dps-reviews-container">
            <div class="dps-reviews-header">
                <h3>
                    <?php 
                    echo esc_html( 
                        sprintf( 
                            /* translators: %s: groomer name */
                            __( 'Avaliações de %s', 'dps-groomers-addon' ), 
                            $groomer_name 
                        ) 
                    ); 
                    ?>
                </h3>
                
                <?php if ( $review_count > 0 ) : ?>
                <div class="dps-reviews-summary">
                    <span class="dps-reviews-avg">
                        <span class="dps-reviews-avg__stars"><?php echo esc_html( str_repeat( '★', round( $avg_rating ) ) ); ?></span>
                        <span class="dps-reviews-avg__value"><?php echo esc_html( number_format_i18n( $avg_rating, 1 ) ); ?></span>
                    </span>
                    <span class="dps-reviews-count">
                        <?php 
                        echo esc_html( 
                            sprintf( 
                                /* translators: %d: number of reviews */
                                _n( '%d avaliação', '%d avaliações', $review_count, 'dps-groomers-addon' ), 
                                $review_count 
                            ) 
                        ); 
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( empty( $reviews ) ) : ?>
                <p class="dps-empty-message"><?php echo esc_html__( 'Nenhuma avaliação ainda.', 'dps-groomers-addon' ); ?></p>
            <?php else : ?>
                <div class="dps-reviews-list">
                    <?php foreach ( $reviews as $review ) :
                        $rating = (int) get_post_meta( $review->ID, '_dps_review_rating', true );
                        $name   = get_post_meta( $review->ID, '_dps_review_name', true );
                        $date   = get_the_date( 'd/m/Y', $review );
                        ?>
                        <div class="dps-review-item">
                            <div class="dps-review-item__header">
                                <span class="dps-review-item__stars"><?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?></span>
                                <span class="dps-review-item__date"><?php echo esc_html( $date ); ?></span>
                            </div>
                            <?php if ( $name ) : ?>
                                <div class="dps-review-item__author"><?php echo esc_html( $name ); ?></div>
                            <?php endif; ?>
                            <?php if ( $review->post_content ) : ?>
                                <div class="dps-review-item__comment"><?php echo esc_html( $review->post_content ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém a média de avaliações de um groomer.
     *
     * @since 1.3.0
     *
     * @param int $groomer_id ID do groomer.
     * @return array Array com 'average' e 'count'.
     */
    public function get_groomer_rating( $groomer_id ) {
        $reviews = get_posts(
            [
                'post_type'      => 'dps_groomer_review',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_dps_review_groomer_id',
                        'value' => $groomer_id,
                    ],
                ],
            ]
        );

        $count = count( $reviews );
        if ( $count === 0 ) {
            return [
                'average' => 0,
                'count'   => 0,
            ];
        }

        $total = 0;
        foreach ( $reviews as $review_id ) {
            $total += (int) get_post_meta( $review_id, '_dps_review_rating', true );
        }

        return [
            'average' => round( $total / $count, 1 ),
            'count'   => $count,
        ];
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
