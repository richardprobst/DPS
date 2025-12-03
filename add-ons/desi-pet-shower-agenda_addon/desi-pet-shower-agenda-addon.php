<?php
/**
 * Plugin Name:       DPS by PRObst – Agenda Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Cria página automática com agenda de atendimentos. Visualize e gerencie compromissos de forma prática.
 * Version:           1.0.1
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-agenda-addon
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
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_agenda_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Agenda requer o plugin base DPS by PRObst para funcionar.', 'dps-agenda-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_agenda_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Agenda Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_agenda_load_textdomain() {
    load_plugin_textdomain( 'dps-agenda-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_agenda_load_textdomain', 1 );

class DPS_Agenda_Addon {
    
    /**
     * Número de agendamentos por página no modo "Todos".
     * 
     * @since 1.1.0
     */
    const APPOINTMENTS_PER_PAGE = 50;
    
    /**
     * Limite de agendamentos por dia nas queries de visualização.
     * Pode ser filtrado via 'dps_agenda_daily_limit'.
     * 
     * @since 1.2.0
     */
    const DAILY_APPOINTMENTS_LIMIT = 200;
    
    /**
     * Limite de clientes na lista de filtros.
     * Pode ser filtrado via 'dps_agenda_clients_limit'.
     * 
     * @since 1.2.0
     */
    const CLIENTS_LIST_LIMIT = 300;
    
    /**
     * Limite de serviços na lista de filtros.
     * Pode ser filtrado via 'dps_agenda_services_limit'.
     * 
     * @since 1.2.0
     */
    const SERVICES_LIST_LIMIT = 200;
    
    public function __construct() {
        // Verifica dependência do Finance Add-on após todos os plugins terem sido carregados
        add_action( 'plugins_loaded', [ $this, 'check_finance_dependency' ] );

        // Cria páginas necessárias ao ativar o plugin (apenas agenda, sem a página de cobranças)
        register_activation_hook( __FILE__, [ $this, 'create_agenda_page' ] );
        // Limpa cron jobs ao desativar o plugin
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        // Registra shortcodes
        add_shortcode( 'dps_agenda_page', [ $this, 'render_agenda_shortcode' ] );
        // Shortcode dps_charges_notes deprecated - redireciona para Finance
        add_shortcode( 'dps_charges_notes', [ $this, 'render_charges_notes_shortcode_deprecated' ] );
        // Enfileira scripts e estilos somente quando páginas específicas forem exibidas
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // AJAX para atualizar status de agendamento (apenas usuários autenticados)
        add_action( 'wp_ajax_dps_update_status', [ $this, 'update_status_ajax' ] );

        // Versionamento de agendamentos para evitar conflitos de escrita
        add_action( 'save_post_dps_agendamento', [ $this, 'ensure_appointment_version_meta' ], 10, 3 );

        // AJAX para obter detalhes de serviços de um agendamento (apenas usuários autenticados)
        add_action( 'wp_ajax_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );

        // Agenda: agendamento de envio de lembretes diários
        add_action( 'init', [ $this, 'maybe_schedule_reminders' ] );
        add_action( 'dps_agenda_send_reminders', [ $this, 'send_reminders' ] );
    }

    /**
     * Verifica se o Finance Add-on está ativo após todos os plugins terem sido carregados.
     *
     * Este método é executado no hook 'plugins_loaded' para garantir que todos os plugins
     * já tenham sido carregados antes de verificar a existência da classe DPS_Finance_API.
     *
     * @since 1.0.1
     */
    public function check_finance_dependency() {
        if ( ! class_exists( 'DPS_Finance_API' ) ) {
            add_action( 'admin_notices', [ $this, 'finance_dependency_notice' ] );
            // Continua a carregar para não quebrar completamente, mas funcionalidade financeira não estará disponível
        }
    }

    /**
     * Cria a página de agenda de atendimentos.
     * 
     * @since 1.0.0
     * @return void
     */
    public function create_agenda_page() {
        $title = __( 'Agenda de Atendimentos', 'dps-agenda-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_agenda_page]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_agenda_page_id', $page_id );
            }
        } else {
            update_option( 'dps_agenda_page_id', $page->ID );
        }
    }

    /**
     * Exibe aviso no admin se Finance Add-on não estiver ativo.
     *
     * @since 1.1.0
     */
    public function finance_dependency_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Agenda Add-on:', 'dps-agenda-addon' ); ?></strong>
                <?php esc_html_e( 'O Finance Add-on é recomendado para funcionalidade completa de cobranças. Algumas funcionalidades financeiras podem não estar disponíveis.', 'dps-agenda-addon' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Cria a página para listar cobranças e notas geradas pelo addon.
     */
    public function create_charges_page() {
        $title = __( 'Cobranças e Notas', 'dps-agenda-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_charges_notes]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_charges_page_id', $page_id );
            }
        } else {
            update_option( 'dps_charges_page_id', $page->ID );
        }
    }

    /**
     * Garante que o meta de versão do agendamento seja inicializado.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post sendo salvo.
     * @param bool    $update  Indica se é uma atualização.
     */
    public function ensure_appointment_version_meta( $post_id, $post, $update ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $post || 'dps_agendamento' !== $post->post_type ) {
            return;
        }

        $current_version = intval( get_post_meta( $post_id, '_dps_appointment_version', true ) );

        if ( $current_version < 1 ) {
            update_post_meta( $post_id, '_dps_appointment_version', 1 );
        }
    }

    /**
     * Enfileira os scripts e estilos necessários apenas quando a página de agenda for carregada.
     * 
     * CSS e JS agora são carregados de arquivos externos (assets/css e assets/js)
     * para melhor cache do navegador, minificação e separação de responsabilidades.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        $agenda_page_id  = get_option( 'dps_agenda_page_id' );
        $charges_page_id = get_option( 'dps_charges_page_id' );
        
        // Agenda page: carrega CSS e scripts da agenda
        if ( $agenda_page_id && is_page( $agenda_page_id ) ) {
            // CSS da agenda (extraído do inline para arquivo dedicado)
            wp_enqueue_style( 
                'dps-agenda-addon-css', 
                plugin_dir_url( __FILE__ ) . 'assets/css/agenda-addon.css', 
                [], 
                '1.1.0' 
            );
            
            // Modal de serviços (precisa ser carregado antes do agenda-addon.js)
            wp_enqueue_script( 
                'dps-services-modal', 
                plugin_dir_url( __FILE__ ) . 'assets/js/services-modal.js', 
                [ 'jquery' ], 
                '1.0.0', 
                true 
            );
            
            // Script principal da agenda (atualização de status e interações)
            wp_enqueue_script( 
                'dps-agenda-addon', 
                plugin_dir_url( __FILE__ ) . 'assets/js/agenda-addon.js', 
                [ 'jquery', 'dps-services-modal' ], 
                '1.3.0', 
                true 
            );
            
            wp_localize_script( 'dps-agenda-addon', 'DPS_AG_Addon', [
                'ajax'          => admin_url( 'admin-ajax.php' ),
                'nonce_status'  => wp_create_nonce( 'dps_update_status' ),
                'nonce_services'=> wp_create_nonce( 'dps_get_services_details' ),
                'statuses'      => [
                    'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
                    'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
                    'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                    'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
                ],
                'messages'      => [
                    'updating' => __( 'Atualizando status...', 'dps-agenda-addon' ),
                    'updated'  => __( 'Status atualizado!', 'dps-agenda-addon' ),
                    'error'    => __( 'Não foi possível atualizar o status.', 'dps-agenda-addon' ),
                    'versionConflict' => __( 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.', 'dps-agenda-addon' ),
                ],
                'reloadDelay'  => 700,
            ] );
        }
        
        // Charges/notes page: pode precisar de estilos extras
        if ( $charges_page_id && is_page( $charges_page_id ) ) {
            // carregue CSS para tabelas se necessário; podemos reutilizar estilos de dps-table se o tema os define.
        }
    }

    /**
     * Renderiza o conteúdo do shortcode [dps_agenda_page].
     */
    public function render_agenda_shortcode() {
        ob_start();
        /*
         * Verifica permissão: somente administradores (capacidade manage_options).
         * Anteriormente, funcionários também tinham acesso à agenda, mas por questões
         * de segurança e a pedido do cliente, o acesso agora é restrito aos
         * administradores. Caso o usuário não esteja logado ou não possua a
         * capacidade de administrador, exibimos um link de login.
         */
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            $login_url = wp_login_url( get_permalink() );
            return '<p>' . esc_html__( 'Você precisa estar logado como administrador para acessar a agenda.', 'dps-agenda-addon' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Fazer login', 'dps-agenda-addon' ) . '</a></p>';
        }
        // Nenhum controle adicional de cookies é necessário; o acesso é controlado por permissões do usuário.
        // Wrapper da agenda (CSS agora carregado de arquivo externo via enqueue_assets)
        echo '<div class="dps-agenda-wrapper">';
        // Acesso permitido: mostrar agenda
        // Filtro de data e visualização
        $selected_date = isset( $_GET['dps_date'] ) ? sanitize_text_field( $_GET['dps_date'] ) : '';
        if ( ! $selected_date ) {
            $selected_date = current_time( 'Y-m-d' );
        }
        $view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'day';
        // Determine if we are in a weekly list or calendar view. Both share similar navigation logic.
        $is_week_view = ( $view === 'week' || $view === 'calendar' );
        // Exibe todos os atendimentos? Se show_all=1, ignoramos view e data para a listagem principal
        $show_all = isset( $_GET['show_all'] ) ? sanitize_text_field( $_GET['show_all'] ) : '';
        // Título simples da agenda
        echo '<h3>' . __( 'Agenda de Atendimentos', 'dps-agenda-addon' ) . '</h3>';
        // Links para dia/semana anterior/próximo, preservando filtros
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $selected_date );
        if ( $is_week_view ) {
            // Para semana, calcula datas sete dias antes e depois
            $prev_date = $date_obj ? $date_obj->modify( '-7 days' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+7 days' )->format( 'Y-m-d' ) : '';
        } else {
            // Para dia, calcula dias anterior e seguinte
            $prev_date = $date_obj ? $date_obj->modify( '-1 day' )->format( 'Y-m-d' ) : '';
            $date_obj  = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $next_date = $date_obj ? $date_obj->modify( '+1 day' )->format( 'Y-m-d' ) : '';
        }
        // Base URL sem parâmetros de data, visualização ou modo 'show_all'
        // Remove também show_all para que a navegação saia do modo "Todos os Atendimentos"
        $base_url = remove_query_arg( [ 'dps_date', 'view', 'show_all' ] );
        // Mantém filtros e outros parâmetros, exceto show_all (que será tratado separadamente)
        $current_args = $_GET;
        unset( $current_args['dps_date'] );
        unset( $current_args['view'] );
        // Não propagamos show_all em links de navegação padrão
        $nav_args = $current_args;
        unset( $nav_args['show_all'] );
        echo '<div class="dps-agenda-nav">';
        echo '<div class="dps-agenda-nav-group">';
        // Botões anterior e seguinte com filtros
        // Ao gerar links, não propague show_all (nav_args não contém show_all)
        $prev_args = array_merge( $nav_args, [ 'dps_date' => $prev_date, 'view' => $view ] );
        $next_args = array_merge( $nav_args, [ 'dps_date' => $next_date, 'view' => $view ] );
        
        // Navegação simplificada (FASE 2): consolidar botões
        echo '<div class="dps-agenda-nav">';
        
        // Grupo 1: Navegação de data (Anterior | Hoje | Próximo)
        echo '<div class="dps-agenda-nav-group">';
        echo '<a href="' . esc_url( add_query_arg( $prev_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft" title="' . esc_attr( $is_week_view ? __( 'Ver semana anterior', 'dps-agenda-addon' ) : __( 'Ver dia anterior', 'dps-agenda-addon' ) ) . '">';
        echo '← ' . ( $is_week_view ? esc_html__( 'Anterior', 'dps-agenda-addon' ) : esc_html__( 'Anterior', 'dps-agenda-addon' ) );
        echo '</a>';
        
        // Botão "Hoje"
        $today = current_time( 'Y-m-d' );
        $today_args = array_merge( $current_args, [ 'dps_date' => $today, 'view' => $view ] );
        unset( $today_args['show_all'] );
        echo '<a href="' . esc_url( add_query_arg( $today_args, $base_url ) ) . '" class="button dps-btn dps-btn--primary" title="' . esc_attr__( 'Ver agendamentos de hoje', 'dps-agenda-addon' ) . '">';
        echo esc_html__( 'Hoje', 'dps-agenda-addon' );
        echo '</a>';
        
        echo '<a href="' . esc_url( add_query_arg( $next_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft" title="' . esc_attr( $is_week_view ? __( 'Ver próxima semana', 'dps-agenda-addon' ) : __( 'Ver próximo dia', 'dps-agenda-addon' ) ) . '">';
        echo ( $is_week_view ? esc_html__( 'Próximo', 'dps-agenda-addon' ) : esc_html__( 'Próximo', 'dps-agenda-addon' ) ) . ' →';
        echo '</a>';
        echo '</div>';
        
        // Grupo 2: Visualizações (Semana | Todos)
        echo '<div class="dps-agenda-nav-group">';
        
        // Botão Ver Semana/Dia (toggle)
        $toggle_args = array_merge( $nav_args, [ 'dps_date' => $selected_date ] );
        if ( $is_week_view ) {
            // Em visualização semanal, exibe botão para voltar à lista diária
            $toggle_args['view'] = 'day';
            echo '<a href="' . esc_url( add_query_arg( $toggle_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost" title="' . esc_attr__( 'Ver lista diária', 'dps-agenda-addon' ) . '">';
            echo '📅 ' . esc_html__( 'Dia', 'dps-agenda-addon' );
            echo '</a>';
        } else {
            // Em visualização diária, exibe botão para a lista semanal
            $toggle_args['view'] = 'week';
            echo '<a href="' . esc_url( add_query_arg( $toggle_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost" title="' . esc_attr__( 'Ver lista semanal', 'dps-agenda-addon' ) . '">';
            echo '📅 ' . esc_html__( 'Semana', 'dps-agenda-addon' );
            echo '</a>';
        }
        
        // Botão Todos os Atendimentos
        $all_args = $current_args;
        unset( $all_args['dps_date'] );
        unset( $all_args['view'] );
        $all_args['show_all'] = '1';
        echo '<a href="' . esc_url( add_query_arg( $all_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost" title="' . esc_attr__( 'Ver todos os agendamentos', 'dps-agenda-addon' ) . '">';
        echo '📋 ' . esc_html__( 'Todos', 'dps-agenda-addon' );
        echo '</a>';
        echo '</div>';
        
        // Grupo 3: Ação principal (Novo Agendamento)
        echo '<div class="dps-agenda-nav-group">';
        
        // Botão "Novo Agendamento" - link para tela de criação no plugin base
        $base_page_id = get_option( 'dps_base_page_id' );
        if ( $base_page_id ) {
            $new_appt_url = add_query_arg( [
                'tab' => 'agendas',
                'action' => 'new'
            ], get_permalink( $base_page_id ) );
            
            echo '<a href="' . esc_url( $new_appt_url ) . '" class="button dps-btn dps-btn--primary" title="' . esc_attr__( 'Criar novo agendamento', 'dps-agenda-addon' ) . '">';
            echo '➕ ' . esc_html__( 'Novo Agendamento', 'dps-agenda-addon' );
            echo '</a>';
        }
        
        echo '</div>';
        echo '</div>';
        // Formulário de seleção de data
        echo '<form method="get" class="dps-agenda-date-form">';
        // Preserve outros parâmetros, exceto dps_date, view e show_all
        foreach ( $_GET as $k => $v ) {
            if ( $k === 'dps_date' || $k === 'view' || $k === 'show_all' ) {
                continue;
            }
            // Para view, já adicionaremos campo separado abaixo
            echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
        }
        // Preserve view explicitamente, caso exista
        echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
        if ( $show_all ) {
            echo '<input type="hidden" name="show_all" value="1">';
        }
        echo '<label>' . esc_html__( 'Selecione a data', 'dps-agenda-addon' ) . '<input type="date" name="dps_date" value="' . esc_attr( $selected_date ) . '"></label>';
        echo '<div class="dps-agenda-date-actions">';
        echo '<button type="submit" class="button dps-btn dps-btn--primary">' . esc_html__( 'Ver', 'dps-agenda-addon' ) . '</button>';
        echo '</div>';
        echo '</form>';

        // ========== Filtros por cliente, status e serviço ==========
        // Obtém filtros atuais
        $filter_client  = isset( $_GET['filter_client'] ) ? intval( $_GET['filter_client'] ) : 0;
        $filter_status  = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $filter_service = isset( $_GET['filter_service'] ) ? intval( $_GET['filter_service'] ) : 0;
        
        // Limites configuráveis via filtro
        $clients_limit = apply_filters( 'dps_agenda_clients_limit', self::CLIENTS_LIST_LIMIT );
        $services_limit = apply_filters( 'dps_agenda_services_limit', self::SERVICES_LIST_LIMIT );
        
        // PERFORMANCE: Lista de clientes com cache transient (1 hora)
        $clients_cache_key = 'dps_agenda_clients_list';
        $clients = get_transient( $clients_cache_key );
        if ( false === $clients ) {
            $clients = get_posts( [
                'post_type'      => 'dps_cliente',
                'posts_per_page' => $clients_limit,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true, // Otimização: não conta total
            ] );
            set_transient( $clients_cache_key, $clients, HOUR_IN_SECONDS );
        }
        
        // PERFORMANCE: Lista de serviços com cache transient (1 hora)
        $services_cache_key = 'dps_agenda_services_list';
        $services = get_transient( $services_cache_key );
        if ( false === $services ) {
            $services = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => $services_limit,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'no_found_rows'  => true, // Otimização: não conta total
            ] );
            set_transient( $services_cache_key, $services, HOUR_IN_SECONDS );
        }
        
        $status_options = [
            ''                => __( 'Todos os status', 'dps-agenda-addon' ),
            'pendente'        => __( 'Pendente', 'dps-agenda-addon' ),
            'finalizado'      => __( 'Finalizado', 'dps-agenda-addon' ),
            'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
            'cancelado'       => __( 'Cancelado', 'dps-agenda-addon' ),
        ];
        // Formulário de filtros
        echo '<form method="get" class="dps-agenda-filters">';
        // Preserve data e view
        echo '<input type="hidden" name="dps_date" value="' . esc_attr( $selected_date ) . '">';
        echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
        if ( $show_all ) {
            echo '<input type="hidden" name="show_all" value="1">';
        }
        // Cliente select - FASE 1: Adicionado aria-label para acessibilidade
        echo '<label>' . esc_html__( 'Cliente', 'dps-agenda-addon' );
        echo '<select name="filter_client" aria-label="' . esc_attr__( 'Filtrar por cliente', 'dps-agenda-addon' ) . '">';
        echo '<option value="0">' . esc_html__( 'Todos', 'dps-agenda-addon' ) . '</option>';
        foreach ( $clients as $cl ) {
            $selected = ( $filter_client === $cl->ID ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $cl->ID ) . '" ' . $selected . '>' . esc_html( $cl->post_title ) . '</option>';
        }
        echo '</select></label>';
        // Status select - FASE 1: Adicionado aria-label para acessibilidade
        echo '<label>' . esc_html__( 'Status', 'dps-agenda-addon' );
        echo '<select name="filter_status" aria-label="' . esc_attr__( 'Filtrar por status do agendamento', 'dps-agenda-addon' ) . '">';
        foreach ( $status_options as $val => $label ) {
            $selected = ( $filter_status === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        // Serviço select - FASE 1: Adicionado aria-label para acessibilidade
        echo '<label>' . esc_html__( 'Serviço', 'dps-agenda-addon' );
        echo '<select name="filter_service" aria-label="' . esc_attr__( 'Filtrar por serviço', 'dps-agenda-addon' ) . '">';
        echo '<option value="0">' . esc_html__( 'Todos', 'dps-agenda-addon' ) . '</option>';
        foreach ( $services as $srv ) {
            $selected = ( $filter_service === $srv->ID ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $srv->ID ) . '" ' . $selected . '>' . esc_html( $srv->post_title ) . '</option>';
        }
        echo '</select></label>';
        // Botões
        echo '<div class="dps-agenda-filter-actions">';
        echo '<button type="submit" class="button dps-btn dps-btn--primary">' . esc_html__( 'Aplicar filtros', 'dps-agenda-addon' ) . '</button>';
        // Link para limpar filtros
        $clear_args = [ 'dps_date' => $selected_date, 'view' => $view ];
        if ( $show_all ) {
            $clear_args['show_all'] = '1';
        }
        echo '<a href="' . esc_url( add_query_arg( $clear_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost">' . esc_html__( 'Limpar filtros', 'dps-agenda-addon' ) . '</a>';
        echo '</div>';
        echo '</form>';
        // Carrega agendamentos conforme visualização ou modo "todos"
        $appointments = [];
        if ( $show_all ) {
            // Carrega todos os agendamentos a partir de hoje (inclusive)
            // PERFORMANCE: Implementada paginação com limite de 50 registros por página
            $today = current_time( 'Y-m-d' );
            $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
            
            $appointments['todos'] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => self::APPOINTMENTS_PER_PAGE,
                'paged'          => $paged,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_date',
                        'value'   => $today,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                ],
                'orderby'        => [ 'appointment_date' => 'ASC', 'appointment_time' => 'ASC' ],
                'meta_key'       => 'appointment_date',
                'order'          => 'ASC',
            ] );
        } elseif ( $view === 'week' ) {
            // Limite diário configurável via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // Calcula início (segunda-feira) da semana contendo $selected_date
            $dt      = DateTime::createFromFormat( 'Y-m-d', $selected_date );
            $weekday = (int) $dt->format( 'N' ); // 1 = seg, 7 = dom
            $start_of_week = clone $dt;
            $start_of_week->modify( '-' . ( $weekday - 1 ) . ' days' );
            for ( $i = 0; $i < 7; $i++ ) {
                $day_date = clone $start_of_week;
                $day_date->modify( '+' . $i . ' days' );
                $appointments[ $day_date->format( 'Y-m-d' ) ] = get_posts( [
                    'post_type'      => 'dps_agendamento',
                    'posts_per_page' => $daily_limit,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [
                            'key'     => 'appointment_date',
                            'value'   => $day_date->format( 'Y-m-d' ),
                            'compare' => '=',
                        ],
                    ],
                    'orderby'        => 'meta_value',
                    'meta_key'       => 'appointment_time',
                    'order'          => 'ASC',
                    'no_found_rows'  => true, // PERFORMANCE: não conta total
                ] );
            }
        } else {
            // Limite diário configurável via filtro
            $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
            
            // Visualização diária
            $appointments[ $selected_date ] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => $daily_limit,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => 'appointment_date',
                        'value'   => $selected_date,
                        'compare' => '=',
                    ],
                ],
                'orderby'        => 'meta_value',
                'meta_key'       => 'appointment_time',
                'order'          => 'ASC',
                'no_found_rows'  => true, // PERFORMANCE: não conta total
            ] );
        }
        // Renderiza tabela para cada dia, aplicando filtros se necessário
        $has_any = false;
        $column_labels = [
            'date'          => __( 'Data', 'dps-agenda-addon' ),
            'time'          => __( 'Hora', 'dps-agenda-addon' ),
            'pet'           => __( 'Pet (Cliente)', 'dps-agenda-addon' ),
            'service'       => __( 'Serviço', 'dps-agenda-addon' ),
            'status'        => __( 'Status', 'dps-agenda-addon' ),
            'map'           => __( 'Mapa', 'dps-agenda-addon' ),
            'confirmation'  => __( 'Confirmação', 'dps-agenda-addon' ),
            'charge'        => __( 'Cobrança', 'dps-agenda-addon' ),
        ];
        foreach ( $appointments as $day => $appts ) {
            $has_any = $has_any || ! empty( $appts );
            // Define título do bloco
            if ( $show_all ) {
                // Em modo "todos", usamos título genérico
                $day_title = __( 'Todos os Atendimentos', 'dps-agenda-addon' );
            } elseif ( $view === 'week' ) {
                $day_dt = DateTime::createFromFormat( 'Y-m-d', $day );
                // Exibe dia e mês no formato dd-mm
                $day_title = ucfirst( date_i18n( 'l', $day_dt->getTimestamp() ) ) . ' ' . date_i18n( 'd-m', $day_dt->getTimestamp() );
            } else {
                $day_title = __( 'Agendamentos do dia', 'dps-agenda-addon' );
            }
            // Se não houver appointments para o dia, pula se semanal
            if ( empty( $appts ) && $view === 'week' ) {
                continue;
            }
            // Se semanal e multiple days, show heading
            echo '<h4>' . esc_html( $day_title ) . '</h4>';
            
            // PERFORMANCE: Pre-cache metadata para todos os agendamentos do dia
            // Reduz chamadas ao banco de dados durante o loop de filtros
            if ( ! empty( $appts ) ) {
                $appointment_ids = wp_list_pluck( $appts, 'ID' );
                update_meta_cache( 'post', $appointment_ids );
            }
            
            // Aplica filtros de cliente, status e serviço
            $filtered = [];
            foreach ( $appts as $appt ) {
                $match = true;
                // Filtro por cliente
                if ( $filter_client ) {
                    $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                    if ( intval( $cid ) !== $filter_client ) {
                        $match = false;
                    }
                }
                // Filtro por status
                if ( $filter_status ) {
                    $st_val = get_post_meta( $appt->ID, 'appointment_status', true );
                    if ( ! $st_val ) { $st_val = 'pendente'; }
                    if ( $st_val !== $filter_status ) {
                        $match = false;
                    }
                }
                // Filtro por serviço
                if ( $filter_service ) {
                    $service_ids_meta = get_post_meta( $appt->ID, 'appointment_services', true );
                    if ( ! is_array( $service_ids_meta ) || ! in_array( $filter_service, $service_ids_meta ) ) {
                        $match = false;
                    }
                }
                if ( $match ) {
                    $filtered[] = $appt;
                }
            }
            // Classificar por status: pendente vs finalizado
            $upcoming  = [];
            $completed = [];
            foreach ( $filtered as $appt ) {
                $st = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $st ) {
                    $st = 'pendente';
                }
                if ( $st === 'pendente' ) {
                    $upcoming[] = $appt;
                } else {
                    $completed[] = $appt;
                }
            }
            $render_table = function( $apts, $heading ) use ( $column_labels ) {
                if ( empty( $apts ) ) {
                    return;
                }
                
                // FASE 1 PERFORMANCE: Pre-carregar posts relacionados (clientes e pets)
                // Coleta IDs únicos de clientes e pets para carregar em batch
                $client_ids = [];
                $pet_ids    = [];
                foreach ( $apts as $appt ) {
                    $cid = get_post_meta( $appt->ID, 'appointment_client_id', true );
                    $pid = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    if ( $cid ) {
                        $client_ids[] = (int) $cid;
                    }
                    if ( $pid ) {
                        $pet_ids[] = (int) $pid;
                    }
                }
                // Carrega todos os posts de uma só vez (reduz queries N+1)
                $related_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
                if ( ! empty( $related_ids ) ) {
                    _prime_post_caches( $related_ids, false, false );
                    // Também pré-carrega metadados dos posts relacionados (pet_aggressive, client_address, etc.)
                    update_meta_cache( 'post', $related_ids );
                }
                
                usort(
                    $apts,
                    function( $a, $b ) {
                        $date_a = get_post_meta( $a->ID, 'appointment_date', true );
                        $time_a = get_post_meta( $a->ID, 'appointment_time', true );
                        $date_b = get_post_meta( $b->ID, 'appointment_date', true );
                        $time_b = get_post_meta( $b->ID, 'appointment_time', true );
                        $dt_a   = strtotime( trim( $date_a . ' ' . $time_a ) );
                        $dt_b   = strtotime( trim( $date_b . ' ' . $time_b ) );
                        if ( $dt_a === $dt_b ) {
                            return $b->ID <=> $a->ID;
                        }
                        return $dt_b <=> $dt_a;
                    }
                );
                echo '<h5>' . esc_html( $heading ) . '</h5>';
                // Adiciona coluna para cobrança via WhatsApp (somente se usuário logado ou administrador)
                echo '<div class="dps-agenda-table-container">';
                echo '<table class="dps-table"><thead><tr>';
                echo '<th>' . esc_html( $column_labels['date'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['time'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['pet'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['service'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['status'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['map'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['confirmation'] ) . '</th>';
                echo '<th>' . esc_html( $column_labels['charge'] ) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ( $apts as $appt ) {
                    $date  = get_post_meta( $appt->ID, 'appointment_date', true );
                    $time  = get_post_meta( $appt->ID, 'appointment_time', true );
                    $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                    $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                    $client_post = $client_id ? get_post( $client_id ) : null;
                    $pet_post    = $pet_id ? get_post( $pet_id ) : null;
                    $status = get_post_meta( $appt->ID, 'appointment_status', true );
                    if ( ! $status ) { $status = 'pendente'; }
                    $appt_version = intval( get_post_meta( $appt->ID, '_dps_appointment_version', true ) );
                    if ( $appt_version < 1 ) {
                        $appt_version = 1;
                        update_post_meta( $appt->ID, '_dps_appointment_version', $appt_version );
                    }
                    // Cada linha recebe classes de status e um data attribute para permitir manipulação via JS.
                    echo '<tr data-appt-id="' . esc_attr( $appt->ID ) . '" class="status-' . esc_attr( $status ) . '">';
                    // Mostra a data no formato dia-mês-ano
                    echo '<td data-label="' . esc_attr( $column_labels['date'] ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
                    echo '<td data-label="' . esc_attr( $column_labels['time'] ) . '">' . esc_html( $time ) . '</td>';
                    // Nome do pet e cliente com flag de agressividade melhorada (FASE 2)
                    $pet_name    = $pet_post ? $pet_post->post_title : '';
                    $client_name = $client_post ? $client_post->post_title : '';
                    $aggr_flag   = '';
                    if ( $pet_post ) {
                        $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
                        if ( $aggr ) {
                            // Flag melhorada com emoji e tooltip
                            $aggr_flag = ' <span class="dps-aggressive-flag" title="' . esc_attr__( 'Pet agressivo - cuidado no manejo', 'dps-agenda-addon' ) . '">⚠️</span>';
                        }
                    }
                    echo '<td data-label="' . esc_attr( $column_labels['pet'] ) . '">' . esc_html( $pet_name . ( $client_name ? ' (' . $client_name . ')' : '' ) ) . $aggr_flag . '</td>';
                    // Serviços e assinatura
                    echo '<td data-label="' . esc_attr( $column_labels['service'] ) . '">';
                    $sub_id_meta = get_post_meta( $appt->ID, 'subscription_id', true );
                    if ( $sub_id_meta ) {
                        echo '<span class="dps-subscription-flag" style="font-weight:bold; color:#0073aa;">' . esc_html__( 'Assinatura', 'dps-agenda-addon' ) . '</span> ';
                    }
                    $service_ids = get_post_meta( $appt->ID, 'appointment_services', true );
                    if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
                        // Link com ícone para abrir modal de serviços (FASE 2)
                        echo '<a href="#" class="dps-services-link" data-appt-id="' . esc_attr( $appt->ID ) . '" title="' . esc_attr__( 'Ver detalhes dos serviços', 'dps-agenda-addon' ) . '">';
                        echo esc_html__( 'Ver serviços', 'dps-agenda-addon' ) . ' ↗';
                        echo '</a>';
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    // Status (editable if admin)
                    echo '<td data-label="' . esc_attr( $column_labels['status'] ) . '">';
                    // SEGURANÇA: Apenas usuários autenticados com capability manage_options podem editar
                    // NUNCA confie em cookies para controle de acesso - são facilmente manipuláveis
                    $can_edit = is_user_logged_in() && current_user_can( 'manage_options' );
                    // Define lista de status padrão
                    $statuses = [
                        'pendente'   => __( 'Pendente', 'dps-agenda-addon' ),
                        'finalizado' => __( 'Finalizado', 'dps-agenda-addon' ),
                        'finalizado_pago' => __( 'Finalizado e pago', 'dps-agenda-addon' ),
                        'cancelado'  => __( 'Cancelado', 'dps-agenda-addon' ),
                    ];
                    // Para agendamentos de assinatura, não há necessidade de usar o status "finalizado e pago"
                    $is_subscription = ! empty( $sub_id_meta );
                    if ( $is_subscription ) {
                        unset( $statuses['finalizado_pago'] );
                        // Se o status atual for finalizado_pago, normaliza para finalizado
                        if ( $status === 'finalizado_pago' ) {
                            $status = 'finalizado';
                            update_post_meta( $appt->ID, 'appointment_status', $status );
                        }
                    }
                    if ( $can_edit ) {
                        // FASE 1: Adicionado aria-label para acessibilidade
                        echo '<select class="dps-status-select" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '" aria-label="' . esc_attr__( 'Alterar status do agendamento', 'dps-agenda-addon' ) . '">';
                        foreach ( $statuses as $value => $label ) {
                            echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
                        }
                        echo '</select>';
                    } else {
                        echo esc_html( $statuses[ $status ] ?? $status );
                    }
                    echo '</td>';
                    // Mapa
                    echo '<td data-label="' . esc_attr( $column_labels['map'] ) . '">';
                    $map_link = '';
                    if ( $client_post ) {
                        // Tenta usar o endereço em texto (rua/número) para o link do mapa, se existir.
                        $address = get_post_meta( $client_post->ID, 'client_address', true );
                        if ( ! empty( $address ) ) {
                            $map_url  = 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $address );
                            $map_link = '<a href="' . esc_url( $map_url ) . '" target="_blank">' . __( 'Mapa', 'dps-agenda-addon' ) . '</a>';
                        } else {
                            // Caso não exista endereço, utiliza as coordenadas se disponíveis
                            $client_lat = get_post_meta( $client_post->ID, 'client_lat', true );
                            $client_lng = get_post_meta( $client_post->ID, 'client_lng', true );
                            if ( ! empty( $client_lat ) && ! empty( $client_lng ) ) {
                                $map_url  = 'https://www.google.com/maps/search/?api=1&query=' . $client_lat . ',' . $client_lng;
                                $map_link = '<a href="' . esc_url( $map_url ) . '" target="_blank">' . __( 'Mapa', 'dps-agenda-addon' ) . '</a>';
                            }
                        }
                    }
                    // Identifica se há solicitação de TaxiDog
                    $taxi_req = get_post_meta( $appt->ID, 'appointment_taxidog', true );
                    if ( $map_link ) {
                        if ( $taxi_req === '1' ) {
                            echo $map_link . ' <span style="color:#0073aa; font-style:italic;">(' . esc_html__( 'TaxiDog', 'dps-agenda-addon' ) . ')</span>';
                        } else {
                            echo $map_link . ' <span style="color:#6c757d; font-style:italic;">(' . esc_html__( 'Cliente', 'dps-agenda-addon' ) . ')</span>';
                        }
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    // Confirmação via WhatsApp
                    echo '<td data-label="' . esc_attr( $column_labels['confirmation'] ) . '">';
                    $confirmation_html = '-';
                    if ( $status === 'pendente' && $client_post ) {
                        $raw_phone = get_post_meta( $client_post->ID, 'client_phone', true );
                        $whatsapp  = DPS_Phone_Helper::format_for_whatsapp( $raw_phone );
                        if ( $whatsapp ) {
                            $client_name = $client_post->post_title;
                            $pet_names   = [];
                            if ( class_exists( 'DPS_Base_Frontend' ) && method_exists( 'DPS_Base_Frontend', 'get_multi_pet_charge_data' ) ) {
                                $group_data = DPS_Base_Frontend::get_multi_pet_charge_data( $appt->ID );
                                if ( $group_data && ! empty( $group_data['pet_names'] ) ) {
                                    $pet_names = $group_data['pet_names'];
                                }
                            }
                            if ( empty( $pet_names ) ) {
                                $pet_names[] = $pet_name ? $pet_name : __( 'Pet', 'dps-agenda-addon' );
                            }
                            $services_ids = get_post_meta( $appt->ID, 'appointment_services', true );
                            $services_txt = '';
                            if ( is_array( $services_ids ) && ! empty( $services_ids ) ) {
                                $service_names = [];
                                foreach ( $services_ids as $srv_id ) {
                                    $srv_post = get_post( $srv_id );
                                    if ( $srv_post ) {
                                        $service_names[] = $srv_post->post_title;
                                    }
                                }
                                if ( $service_names ) {
                                    $services_txt = ' (' . implode( ', ', $service_names ) . ')';
                                }
                            }
                            $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '';
                            $message = sprintf(
                                'Olá %s, tudo bem? Poderia confirmar o atendimento do(s) pet(s) %s agendado para %s às %s%s? Caso precise reagendar é só responder esta mensagem. Obrigado!',
                                $client_name,
                                implode( ', ', $pet_names ),
                                $date_fmt,
                                $time,
                                $services_txt
                            );
                            $message = apply_filters( 'dps_agenda_confirmation_message', $message, $appt );
                            // Link de confirmação com ícone e tooltip usando helper centralizado
                            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                                $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $whatsapp, $message );
                            } else {
                                // Fallback
                                $wa_url = 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode( $message );
                            }
                            $confirmation_html = '<a href="' . esc_url( $wa_url ) . '" target="_blank" title="' . esc_attr__( 'Enviar mensagem de confirmação via WhatsApp', 'dps-agenda-addon' ) . '">💬 ' . esc_html__( 'Confirmar', 'dps-agenda-addon' ) . '</a>';
                        }
                    }
                    echo $confirmation_html;
                    echo '</td>';
                    // Cobrança via WhatsApp
                    echo '<td data-label="' . esc_attr( $column_labels['charge'] ) . '">';
                    // Mostra link de cobrança apenas para atendimentos finalizados (não assinaturas)
                    $sub_meta = get_post_meta( $appt->ID, 'subscription_id', true );
                    if ( $status === 'finalizado' && empty( $sub_meta ) ) {
                        $client_phone = $client_post ? get_post_meta( $client_post->ID, 'client_phone', true ) : '';
                        $total_val    = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
                        $digits       = DPS_Phone_Helper::format_for_whatsapp( $client_phone );
                        if ( $digits && $total_val > 0 ) {
                            $client_name = $client_post ? $client_post->post_title : '';
                            $pet_names   = [];
                            if ( class_exists( 'DPS_Base_Frontend' ) && method_exists( 'DPS_Base_Frontend', 'get_multi_pet_charge_data' ) ) {
                                $group_data = DPS_Base_Frontend::get_multi_pet_charge_data( $appt->ID );
                                if ( $group_data && ! empty( $group_data['pet_names'] ) ) {
                                    $pet_names = $group_data['pet_names'];
                                }
                            }
                            if ( empty( $pet_names ) ) {
                                $pet_names[] = $pet_post ? $pet_post->post_title : '';
                            }
                            $valor_fmt    = number_format_i18n( $total_val, 2 );
                            $payment_link = get_post_meta( $appt->ID, 'dps_payment_link', true );
                            $default_link = 'https://link.mercadopago.com.br/desipetshower';
                            $link_to_use  = $payment_link ? $payment_link : $default_link;
                            $msg          = sprintf( 'Olá %s, tudo bem? O serviço do pet %s foi finalizado e o pagamento de R$ %s ainda está pendente. Para sua comodidade, você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Obrigado pela confiança!', $client_name, implode( ', ', array_filter( $pet_names ) ), $valor_fmt, $link_to_use );
                            $msg          = apply_filters( 'dps_agenda_whatsapp_message', $msg, $appt, 'agenda' );
                            $links        = [];
                            // Link de cobrança com ícone e tooltip usando helper centralizado
                            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                                $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $msg );
                            } else {
                                // Fallback
                                $wa_url = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                            }
                            $links[]      = '<a href="' . esc_url( $wa_url ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobrança via WhatsApp', 'dps-agenda-addon' ) . '">💰 ' . esc_html__( 'Cobrar', 'dps-agenda-addon' ) . '</a>';
                            if ( ! empty( $group_data ) && (int) $appt->ID === (int) min( $group_data['ids'] ) ) {
                                $group_total = number_format_i18n( $group_data['total'], 2 );
                                $date_fmt    = $group_data['date'] ? date_i18n( 'd/m/Y', strtotime( $group_data['date'] ) ) : '';
                                $group_msg   = sprintf( 'Olá %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s às %s. O valor total ficou em R$ %s. Você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Caso tenha dúvidas estamos à disposição!', $client_name, implode( ', ', $group_data['pet_names'] ), $date_fmt, $group_data['time'], $group_total, $link_to_use );
                                $group_msg   = apply_filters( 'dps_agenda_whatsapp_group_message', $group_msg, $appt, $group_data );
                                // Link de cobrança conjunta com ícone usando helper centralizado
                                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                                    $wa_url_group = DPS_WhatsApp_Helper::get_link_to_client( $client_phone, $group_msg );
                                } else {
                                    // Fallback
                                    $wa_url_group = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $group_msg );
                                }
                                $links[]     = '<a href="' . esc_url( $wa_url_group ) . '" target="_blank" title="' . esc_attr__( 'Enviar cobrança conjunta via WhatsApp', 'dps-agenda-addon' ) . '">💰💰 ' . esc_html__( 'Cobrança conjunta', 'dps-agenda-addon' ) . '</a>';
                            }
                            echo implode( '<br>', $links );
                        } else {
                            echo '-';
                        }
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            };
            if ( ! empty( $filtered ) ) {
                $total_upcoming  = count( $upcoming );
                $total_completed = count( $completed );
                echo '<div class="dps-agenda-summary" role="status">';
                echo '<span><strong>' . esc_html( $total_upcoming ) . '</strong> ' . esc_html( _n( 'atendimento pendente', 'atendimentos pendentes', $total_upcoming, 'dps-agenda-addon' ) ) . '</span>';
                echo '<span><strong>' . esc_html( $total_completed ) . '</strong> ' . esc_html( _n( 'atendimento finalizado', 'atendimentos finalizados', $total_completed, 'dps-agenda-addon' ) ) . '</span>';
                echo '<span><strong>' . esc_html( count( $filtered ) ) . '</strong> ' . esc_html__( 'agendamentos no total', 'dps-agenda-addon' ) . '</span>';
                echo '</div>';
            }
            // Renderiza tabelas
            $render_table( $upcoming, __( 'Próximos Atendimentos', 'dps-agenda-addon' ) );
            $render_table( $completed, __( 'Atendimentos Finalizados', 'dps-agenda-addon' ) );
        }
        if ( ! $has_any ) {
            echo '<p class="dps-agenda-empty" role="status">' . __( 'Nenhum agendamento.', 'dps-agenda-addon' ) . '</p>';
        }
        
        // PERFORMANCE: Controles de paginação para modo "Todos os Atendimentos"
        if ( $show_all ) {
            $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
            $prev_page = max( 1, $paged - 1 );
            $next_page = $paged + 1;
            
            // Preserva parâmetros de filtro válidos na paginação
            // Sanitiza cada parâmetro para prevenir injeção de código
            $pagination_args = [
                'show_all' => '1',
                'dps_date' => $selected_date,
                'view'     => $view,
            ];
            
            // Adiciona filtros se definidos
            if ( $filter_client ) {
                $pagination_args['filter_client'] = $filter_client;
            }
            if ( $filter_status ) {
                $pagination_args['filter_status'] = $filter_status;
            }
            if ( $filter_service ) {
                $pagination_args['filter_service'] = $filter_service;
            }
            
            echo '<div class="dps-agenda-pagination" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">';
            
            if ( $paged > 1 ) {
                $pagination_args['paged'] = $prev_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo '← ' . esc_html__( 'Página anterior', 'dps-agenda-addon' );
                echo '</a>';
            }
            
            echo '<span class="dps-pagination-info" style="padding: 8px 15px; background: #f9fafb; border-radius: 4px;">';
            echo sprintf( esc_html__( 'Página %d', 'dps-agenda-addon' ), $paged );
            echo '</span>';
            
            // Só mostra "Próxima" se retornou o máximo de registros (indicando que pode haver mais)
            if ( ! empty( $appointments['todos'] ) && count( $appointments['todos'] ) >= self::APPOINTMENTS_PER_PAGE ) {
                $pagination_args['paged'] = $next_page;
                echo '<a href="' . esc_url( add_query_arg( $pagination_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">';
                echo esc_html__( 'Próxima página', 'dps-agenda-addon' ) . ' →';
                echo '</a>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Shortcode deprecated que redireciona para o Finance Add-on.
     *
     * @deprecated 1.1.0 Use [dps_fin_docs] do Finance Add-on
     * @return string HTML do shortcode ou mensagem de depreciação
     */
    public function render_charges_notes_shortcode_deprecated() {
        _deprecated_function( 'Shortcode [dps_charges_notes]', '1.1.0', '[dps_fin_docs] (Finance Add-on)' );
        
        // Tenta redirecionar para shortcode do Finance
        if ( shortcode_exists( 'dps_fin_docs' ) ) {
            return do_shortcode( '[dps_fin_docs]' );
        }
        
        // Se Finance não estiver ativo, exibe mensagem
        return '<div class="notice notice-warning" style="padding: 20px; margin: 20px 0; background: #fff3cd; border-left: 4px solid #ffc107;">' .
               '<p><strong>' . esc_html__( 'Atenção:', 'dps-agenda-addon' ) . '</strong> ' .
               esc_html__( 'Este shortcode foi movido para o Finance Add-on. Por favor, use [dps_fin_docs] ou ative o Finance Add-on.', 'dps-agenda-addon' ) .
               '</p></div>';
    }

    /**
     * AJAX handler para atualizar o status de um agendamento.
     *
     * Espera campos 'id' e 'status' via POST. Somente usuários logados podem executar.
     */
    public function update_status_ajax() {
        // Verifica permissão do usuário. Apenas administradores podem alterar o status.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        // Verifica nonce para evitar CSRF. O nonce deve ser enviado no campo 'nonce'.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_update_status' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        $id     = intval( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $version = isset( $_POST['version'] ) ? intval( $_POST['version'] ) : 0;
        // Aceita variações do texto "finalizado e pago" além do slug sem espaços
        if ( $status === 'finalizado e pago' ) {
            $status = 'finalizado_pago';
        }
        $valid_statuses = [ 'pendente', 'finalizado', 'finalizado_pago', 'cancelado' ];
        if ( ! $id || ! in_array( $status, $valid_statuses, true ) || $version < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Dados inválidos.', 'dps-agenda-addon' ) ] );
        }
        $current_version = intval( get_post_meta( $id, '_dps_appointment_version', true ) );

        if ( $current_version < 1 ) {
            $current_version = 1;
            update_post_meta( $id, '_dps_appointment_version', $current_version );
        }

        if ( $version !== $current_version ) {
            wp_send_json_error(
                [
                    'message'    => __( 'Esse agendamento foi atualizado por outro usuário. Atualize a página para ver as alterações.', 'dps-agenda-addon' ),
                    'error_code' => 'version_conflict',
                ]
            );
        }
        // Atualiza meta de status. Remove entradas anteriores para garantir que não haja valores duplicados.
        delete_post_meta( $id, 'appointment_status' );
        add_post_meta( $id, 'appointment_status', $status, true );
        $new_version = $current_version + 1;
        update_post_meta( $id, '_dps_appointment_version', $new_version );

        // AUDITORIA: Registra mudança de status no log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status alterado para "%s" por usuário #%d',
                    $id,
                    $status,
                    get_current_user_id()
                ),
                [
                    'appointment_id' => $id,
                    'new_status'     => $status,
                    'user_id'        => get_current_user_id(),
                    'version'        => $new_version,
                ],
                'agenda'
            );
        }

        // A sincronização financeira é feita automaticamente pelo Finance Add-on via hook updated_post_meta
        // O Finance monitora mudanças em appointment_status e cria/atualiza transações conforme necessário
        // Não é necessário manipular dps_transacoes diretamente aqui

        // Após atualizar a transação, aciona o hook dps_base_after_save_appointment para que
        // outros add-ons (como o de pagamentos) possam processar o agendamento finalizado.
        // Isso garante que o link de pagamento seja criado automaticamente mesmo quando
        // o status é alterado manualmente pela agenda.
        do_action( 'dps_base_after_save_appointment', $id, 'simple' );

        // TODO: Implementar notificação via WhatsApp quando necessário
        // Atualmente o código abaixo usa variáveis não definidas ($client_id, $pet_post, $date, $valor)
        // e precisa ser refatorado para obter esses dados do agendamento

        wp_send_json_success(
            [
                'message' => __( 'Status atualizado.', 'dps-agenda-addon' ),
                'status'  => $status,
                'version' => $new_version,
            ]
        );
    }

    /**
     * AJAX handler para retornar detalhes de serviços de um agendamento.
     * Retorna lista de serviços (nome e preço) para o agendamento.
     *
     * @deprecated 1.1.0 Lógica movida para Services Add-on (DPS_Services_API).
     *                   Mantido por compatibilidade, mas delega para API quando disponível.
     */
    public function get_services_details_ajax() {
        // Apenas administradores podem consultar detalhes de serviços. Garante que usuários não
        // autenticados ou sem permissão não exponham dados. Caso contrário, retorna erro.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        // SEGURANÇA: Verificação de nonce obrigatória para prevenir CSRF
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'dps_get_services_details' ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha na verificação de segurança.', 'dps-agenda-addon' ) ] );
        }
        $id_param  = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        if ( ! $id_param ) {
            // Compatibilidade: aceita "id" como fallback
            $id_param = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        }
        if ( ! $id_param ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'dps-agenda-addon' ) ] );
        }

        // Delega para Services API se disponível (recomendado)
        if ( class_exists( 'DPS_Services_API' ) ) {
            $details = DPS_Services_API::get_services_details( $id_param );
            wp_send_json_success( [
                'services' => $details['services'],
            ] );
        }

        // Fallback: implementação legada (mantida se Services Add-on não estiver ativo)
        $service_ids    = get_post_meta( $id_param, 'appointment_services', true );
        $service_prices = get_post_meta( $id_param, 'appointment_service_prices', true );
        if ( ! is_array( $service_prices ) ) {
            $service_prices = [];
        }
        $services = [];
        if ( is_array( $service_ids ) ) {
            foreach ( $service_ids as $sid ) {
                $srv = get_post( $sid );
                if ( $srv ) {
                    $name  = $srv->post_title;
                    // Preço personalizado ou base
                    $price = 0;
                    if ( isset( $service_prices[ $sid ] ) ) {
                        $price = (float) $service_prices[ $sid ];
                    } else {
                        $price = (float) get_post_meta( $sid, 'service_price', true );
                    }
                    $services[] = [ 'name' => $name, 'price' => $price ];
                }
            }
        }
        wp_send_json_success( [ 'services' => $services ] );
    }

    /**
     * Limpa cron jobs agendados quando o plugin é desativado.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'dps_agenda_send_reminders' );
    }

    /**
     * Agenda envio diário de lembretes para clientes com agendamentos do dia.
     * O evento é agendado apenas uma vez, no próximo horário configurado (padrão: 08:00).
     */
    public function maybe_schedule_reminders() {
        if ( ! function_exists( 'wp_next_scheduled' ) ) {
            return;
        }
        // Verifica se já existe um evento programado
        $timestamp = wp_next_scheduled( 'dps_agenda_send_reminders' );
        if ( ! $timestamp ) {
            // Calcula timestamp para 08:00 do horário do site
            $hour   = 8;
            $minute = 0;
            // Usa timezone do site
            $tz = wp_timezone();
            $now = new DateTime( 'now', $tz );
            // Cria data para hoje às 08:00
            $schedule_time = new DateTime( $now->format( 'Y-m-d' ) . ' ' . sprintf( '%02d:%02d', $hour, $minute ), $tz );
            // Se já passou hoje, agenda para o dia seguinte
            if ( $schedule_time <= $now ) {
                $schedule_time->modify( '+1 day' );
            }
            wp_schedule_event( $schedule_time->getTimestamp(), 'daily', 'dps_agenda_send_reminders' );
        }
    }

    /**
     * Envia lembretes de agendamentos para clientes.
     * Este método é executado pelo cron diário configurado em maybe_schedule_reminders().
     *
     * NOTA: A lógica de ENVIO está delegada à Communications API.
     * A Agenda apenas identifica quais agendamentos precisam de lembrete.
     * 
     * @since 1.0.0
     * @return void
     */
    public function send_reminders() {
        // Determina a data atual no fuso horário do site
        $date = current_time( 'Y-m-d' );
        
        // Limite diário configurável (mesmo usado nas queries de visualização)
        $daily_limit = apply_filters( 'dps_agenda_daily_limit', self::DAILY_APPOINTMENTS_LIMIT );
        
        // PERFORMANCE: Busca agendamentos do dia com limite e otimização
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => $daily_limit,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'appointment_date', 'value' => $date, 'compare' => '=' ],
            ],
            'no_found_rows'  => true, // Otimização: não conta total
        ] );
        
        // AUDITORIA: Registra início do envio de lembretes
        $total_appointments = count( $appointments );
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Cron de lembretes iniciado: %d agendamentos encontrados para %s',
                    $total_appointments,
                    $date
                ),
                [
                    'date'              => $date,
                    'total_found'       => $total_appointments,
                    'cron_job'          => 'dps_agenda_send_reminders',
                ],
                'agenda'
            );
        }
        
        if ( empty( $appointments ) ) {
            return;
        }

        $reminders_sent = 0;
        $reminders_skipped = 0;

        // Se Communications API estiver disponível, usa ela (método preferido)
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            
            foreach ( $appointments as $appt ) {
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $status ) {
                    $status = 'pendente';
                }
                
                // Apenas lembretes para agendamentos pendentes
                if ( $status !== 'pendente' ) {
                    $reminders_skipped++;
                    continue;
                }
                
                // Delega envio para a Communications API
                $api->send_appointment_reminder( $appt->ID );
                $reminders_sent++;
            }
        } else {
            // Fallback: envio manual via wp_mail (compatibilidade retroativa)
            foreach ( $appointments as $appt ) {
                $status = get_post_meta( $appt->ID, 'appointment_status', true );
                if ( ! $status ) {
                    $status = 'pendente';
                }
                if ( $status !== 'pendente' ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
                if ( ! $client_id ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_post = get_post( $client_id );
                if ( ! $client_post ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_email = get_post_meta( $client_id, 'client_email', true );
                if ( ! $client_email ) {
                    $reminders_skipped++;
                    continue;
                }
                
                $client_name = $client_post->post_title;
                $pet_id      = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet_name    = '';
                
                if ( $pet_id ) {
                    $pet_post = get_post( $pet_id );
                    if ( $pet_post ) {
                        $pet_name = $pet_post->post_title;
                    }
                }
                
                $time    = get_post_meta( $appt->ID, 'appointment_time', true );
                $time    = $time ? $time : '';
                $subject = sprintf( __( 'Lembrete de agendamento para %s', 'dps-agenda-addon' ), $client_name );
                $message = sprintf(
                    __( 'Olá %s,\n\nEste é um lembrete do agendamento para %s no dia %s às %s.\n\nEstamos aguardando você!\n\nAtenciosamente,\nDPS by PRObst', 'dps-agenda-addon' ),
                    $client_name,
                    $pet_name ? $pet_name : __( 'seu pet', 'dps-agenda-addon' ),
                    date_i18n( 'd-m-Y', strtotime( $date ) ),
                    $time
                );
                
                $recipients = apply_filters( 'dps_agenda_reminder_recipients', [ $client_email ], $appt->ID );
                $subject    = apply_filters( 'dps_agenda_reminder_subject', $subject, $appt->ID );
                $message    = apply_filters( 'dps_agenda_reminder_content', $message, $appt->ID );
                
                foreach ( $recipients as $recipient ) {
                    wp_mail( $recipient, $subject, $message );
                }
                $reminders_sent++;
            }
        }
        
        // AUDITORIA: Registra resultado do envio de lembretes
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Cron de lembretes finalizado: %d enviados, %d ignorados (não pendentes ou sem dados)',
                    $reminders_sent,
                    $reminders_skipped
                ),
                [
                    'date'              => $date,
                    'total_found'       => $total_appointments,
                    'reminders_sent'    => $reminders_sent,
                    'reminders_skipped' => $reminders_skipped,
                    'cron_job'          => 'dps_agenda_send_reminders',
                ],
                'agenda'
            );
        }
    }


}

/**
 * Inicializa o Agenda Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_agenda_init_addon() {
    if ( class_exists( 'DPS_Agenda_Addon' ) ) {
        new DPS_Agenda_Addon();
    }
}
add_action( 'init', 'dps_agenda_init_addon', 5 );
