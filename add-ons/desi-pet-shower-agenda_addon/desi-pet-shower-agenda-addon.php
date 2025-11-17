<?php
/**
 * Plugin Name:       Desi Pet Shower – Agenda Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin Desi Pet Shower que cria automaticamente uma página com a agenda de atendimentos.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-agenda-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Addon {
    public function __construct() {
        // Cria páginas necessárias ao ativar o plugin (apenas agenda, sem a página de cobranças)
        register_activation_hook( __FILE__, [ $this, 'create_agenda_page' ] );
        // Registra shortcodes
        add_shortcode( 'dps_agenda_page', [ $this, 'render_agenda_shortcode' ] );
        add_shortcode( 'dps_charges_notes', [ $this, 'render_charges_notes_shortcode' ] );
        // Enfileira scripts e estilos somente quando páginas específicas forem exibidas
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // AJAX para atualizar status de agendamento
        add_action( 'wp_ajax_dps_update_status', [ $this, 'update_status_ajax' ] );
        add_action( 'wp_ajax_nopriv_dps_update_status', [ $this, 'update_status_ajax' ] );

        // Versionamento de agendamentos para evitar conflitos de escrita
        add_action( 'save_post_dps_agendamento', [ $this, 'ensure_appointment_version_meta' ], 10, 3 );

        // AJAX para obter detalhes de serviços de um agendamento
        add_action( 'wp_ajax_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );
        add_action( 'wp_ajax_nopriv_dps_get_services_details', [ $this, 'get_services_details_ajax' ] );

        // Agenda: agendamento de envio de lembretes diários
        add_action( 'init', [ $this, 'maybe_schedule_reminders' ] );
        add_action( 'dps_agenda_send_reminders', [ $this, 'send_reminders' ] );
    }

    /**
     * Cria automaticamente a página de agenda ao ativar o plugin.
     */
    /**
     * Dispara criação de páginas necessárias ao addon.
     */
    public function create_pages() {
        // Esta função não é mais usada para criar páginas. A agenda será criada apenas por create_agenda_page().
    }

    /**
     * Cria a página de agenda de atendimentos.
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
     */
    public function enqueue_assets() {
        $agenda_page_id  = get_option( 'dps_agenda_page_id' );
        $charges_page_id = get_option( 'dps_charges_page_id' );
        // Agenda page: carrega FullCalendar e script de agenda
        if ( $agenda_page_id && is_page( $agenda_page_id ) ) {
            // Somente o script de atualização de status será necessário; não carregamos o calendário
            // Carrega o script da agenda. Alteramos a versão para forçar atualização do cache quando o arquivo for modificado.
            wp_enqueue_script( 'dps-agenda-addon', plugin_dir_url( __FILE__ ) . 'agenda-addon.js', [ 'jquery' ], '1.2.0', true );
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
            // Enfileira CSS opcional para tabela (reutilizamos estilos padrão do plugin principal se disponível)
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
        // Adiciona estilos inline para destacar linhas da agenda conforme o status. Essas classes serão
        // aplicadas nas linhas de cada agendamento e permitem uma rápida identificação visual da situação
        // (pendente, finalizado, finalizado e pago ou cancelado).
        echo '<div class="dps-agenda-wrapper">';
        echo '<style>
        .dps-agenda-wrapper {
            --dps-accent:#2563eb;
            --dps-accent-strong:#1d4ed8;
            --dps-accent-soft:#eff6ff;
            --dps-surface:#ffffff;
            --dps-background:#f8fafc;
            --dps-border:#e2e8f0;
            --dps-muted:#64748b;
            max-width:100%;
            color:#0f172a;
        }
        .dps-agenda-wrapper * { box-sizing:border-box; }
        .dps-agenda-wrapper h3,
        .dps-agenda-wrapper h4,
        .dps-agenda-wrapper h5 {
            margin:0 0 0.75rem;
            color:#0f172a;
        }
        .dps-agenda-wrapper h4 { font-size:1.1rem; font-weight:600; }
        .dps-agenda-wrapper .dps-agenda-nav,
        .dps-agenda-wrapper .dps-agenda-date-form,
        .dps-agenda-wrapper .dps-agenda-filters {
            display:flex;
            flex-wrap:wrap;
            gap:1rem;
            align-items:center;
            margin-bottom:1.25rem;
            padding:1rem 1.25rem;
            background:var(--dps-surface);
            border:1px solid var(--dps-border);
            border-radius:0.75rem;
            box-shadow:0 8px 16px rgba(15,23,42,0.04);
        }
        .dps-agenda-nav { justify-content:space-between; }
        .dps-agenda-nav-group {
            display:flex;
            flex-wrap:wrap;
            gap:0.6rem;
            align-items:center;
        }
        .dps-agenda-date-form label,
        .dps-agenda-filters label {
            display:flex;
            flex-direction:column;
            gap:0.35rem;
            font-weight:600;
            color:#0f172a;
            min-width:14rem;
            flex:1 1 14rem;
        }
        .dps-agenda-date-form input[type="date"],
        .dps-agenda-filters input,
        .dps-agenda-filters select,
        .dps-status-select {
            width:100%;
            padding:0.55rem 0.75rem;
            border:1px solid var(--dps-border);
            border-radius:0.65rem;
            background:#fff;
            color:#0f172a;
            font-size:0.95rem;
            line-height:1.4;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .dps-status-select { min-width:11.5rem; }
        .dps-agenda-date-form input[type="date"]:focus,
        .dps-agenda-filters input:focus,
        .dps-agenda-filters select:focus,
        .dps-status-select:focus {
            outline:none;
            border-color:var(--dps-accent);
            box-shadow:0 0 0 3px rgba(37,99,235,0.2);
        }
        .dps-agenda-date-actions,
        .dps-agenda-filter-actions {
            display:flex;
            gap:0.75rem;
            margin-left:auto;
            align-items:center;
        }
        .dps-agenda-date-actions .dps-btn,
        .dps-agenda-filter-actions .dps-btn { min-width:8.5rem; }
        .dps-agenda-empty {
            margin:2rem auto 0;
            padding:1.5rem;
            max-width:min(100%, 32rem);
            text-align:center;
            line-height:1.6;
            color:var(--dps-muted);
            background:var(--dps-background);
            border:1px dashed var(--dps-border);
            border-radius:0.85rem;
            box-shadow:0 6px 20px rgba(15,23,42,0.06);
            word-break:break-word;
        }
        .dps-btn {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:0.5rem;
            padding:0.55rem 1.25rem;
            border-radius:999px;
            font-weight:600;
            font-size:0.95rem;
            line-height:1.1;
            text-decoration:none;
            border:1px solid transparent;
            cursor:pointer;
            transition:background .2s ease, border-color .2s ease, color .2s ease, transform .15s ease;
            box-shadow:0 1px 2px rgba(15,23,42,0.08);
        }
        .dps-btn:focus-visible {
            outline:none;
            box-shadow:0 0 0 3px rgba(37,99,235,0.25);
        }
        .dps-btn--primary {
            background:var(--dps-accent);
            border-color:var(--dps-accent);
            color:#fff;
        }
        .dps-btn--primary:hover {
            background:var(--dps-accent-strong);
            border-color:var(--dps-accent-strong);
            transform:translateY(-1px);
        }
        .dps-btn--ghost {
            background:transparent;
            border-color:var(--dps-accent);
            color:var(--dps-accent);
        }
        .dps-btn--ghost:hover { background:var(--dps-accent-soft); }
        .dps-btn--soft {
            background:var(--dps-background);
            border-color:var(--dps-border);
            color:#0f172a;
        }
        .dps-btn--soft:hover {
            border-color:var(--dps-accent);
            color:var(--dps-accent);
        }
        .dps-agenda-summary {
            display:flex;
            flex-wrap:wrap;
            gap:0.75rem 1.5rem;
            align-items:center;
            margin:0.75rem 0 1.5rem;
            padding:0.75rem 1rem;
            background:var(--dps-accent-soft);
            border:1px solid rgba(37,99,235,0.12);
            border-radius:0.75rem;
            color:#0f172a;
        }
        .dps-agenda-summary span {
            display:flex;
            align-items:center;
            gap:0.35rem;
            font-size:0.95rem;
        }
        .dps-agenda-summary span strong { font-size:1.05rem; }
        .dps-agenda-table-container {
            overflow-x:auto;
            margin-bottom:1.5rem;
            border-radius:0.75rem;
            background:var(--dps-surface);
            border:1px solid var(--dps-border);
            -webkit-overflow-scrolling:touch;
        }
        .dps-agenda-table-container::-webkit-scrollbar { height:8px; }
        .dps-agenda-table-container::-webkit-scrollbar-thumb {
            background:rgba(15,23,42,0.2);
            border-radius:999px;
        }
        .dps-agenda-wrapper table.dps-table {
            width:100%;
            min-width:780px;
            border-collapse:separate;
            border-spacing:0;
            margin:0;
        }
        .dps-agenda-wrapper table.dps-table thead th {
            text-align:left;
            padding:0.75rem 1rem;
            font-size:0.8rem;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.02em;
            color:var(--dps-muted);
            background:var(--dps-background);
        }
        .dps-agenda-wrapper table.dps-table tbody tr {
            transition:background .2s ease, border-left-color .2s ease;
            border-left:4px solid transparent;
        }
        .dps-agenda-wrapper table.dps-table tbody tr:hover { background:#f9fafb; }
        .dps-agenda-wrapper table.dps-table tbody td {
            padding:0.85rem 1rem;
            border-top:1px solid var(--dps-border);
            vertical-align:top;
            color:#0f172a;
            font-size:0.95rem;
        }
        .dps-agenda-wrapper table.dps-table tbody tr:first-child td { border-top:0; }
        .dps-agenda-wrapper table.dps-table tr.status-pendente {
            border-left-color:#f59e0b;
            background:#fffbeb;
        }
        .dps-agenda-wrapper table.dps-table tr.status-finalizado {
            border-left-color:#0ea5e9;
            background:#f0f9ff;
        }
        .dps-agenda-wrapper table.dps-table tr.status-finalizado_pago {
            border-left-color:#22c55e;
            background:#f0fdf4;
        }
        .dps-agenda-wrapper table.dps-table tr.status-cancelado {
            border-left-color:#ef4444;
            background:#fef2f2;
        }
        .dps-services-link {
            color:var(--dps-accent);
            font-weight:600;
            text-decoration:none;
        }
        .dps-services-link:hover { text-decoration:underline; }
        .dps-status-feedback {
            display:block;
            margin-top:0.35rem;
            font-size:0.85rem;
            color:var(--dps-muted);
        }
        .dps-status-feedback--error { color:#ef4444; }
        .dps-status-select.is-loading { opacity:0.6; pointer-events:none; }
        @media (max-width:1024px) {
            .dps-agenda-nav { justify-content:flex-start; }
            .dps-agenda-nav-group { flex:1 1 100%; justify-content:flex-start; }
        }
        @media (max-width:860px) {
            .dps-agenda-date-form label,
            .dps-agenda-filters label { min-width:100%; flex:1 1 100%; }
            .dps-agenda-date-actions,
            .dps-agenda-filter-actions {
                width:100%;
                justify-content:flex-start;
                flex-wrap:wrap;
            }
        }
        @media (max-width:768px) {
            .dps-agenda-wrapper .dps-agenda-nav,
            .dps-agenda-wrapper .dps-agenda-date-form,
            .dps-agenda-wrapper .dps-agenda-filters { padding:0.85rem 1rem; }
            .dps-agenda-nav .dps-btn { flex:1 1 calc(50% - 0.5rem); }
            .dps-agenda-date-actions .dps-btn,
            .dps-agenda-filter-actions .dps-btn { flex:1 1 auto; }
            .dps-agenda-table-container { border-radius:0.5rem; box-shadow:none; }
            .dps-agenda-wrapper table.dps-table { min-width:640px; }
        }
        @media (max-width:640px) {
            .dps-agenda-wrapper table.dps-table { min-width:0; }
            .dps-agenda-wrapper table.dps-table thead { display:none; }
            .dps-agenda-wrapper table.dps-table tbody {
                display:flex;
                flex-direction:column;
                gap:1rem;
            }
            .dps-agenda-wrapper table.dps-table tr {
                display:flex;
                flex-direction:column;
                border:1px solid var(--dps-border);
                border-left-width:4px;
                border-radius:0.75rem;
                background:var(--dps-surface);
                padding:1rem;
            }
            .dps-agenda-empty {
                margin-top:1.5rem;
                padding:1.25rem;
            }
            .dps-agenda-wrapper table.dps-table tbody td {
                border:0;
                padding:0.5rem 0;
                display:flex;
                flex-direction:column;
                gap:0.25rem;
            }
            .dps-agenda-wrapper table.dps-table tbody td::before {
                content:attr(data-label);
                font-size:0.8rem;
                font-weight:600;
                color:var(--dps-muted);
                text-transform:uppercase;
            }
            .dps-agenda-summary { width:100%; }
        }
        @media (max-width:420px) {
            .dps-agenda-nav .dps-btn { flex:1 1 100%; }
            .dps-agenda-date-actions,
            .dps-agenda-filter-actions { gap:0.5rem; }
            .dps-agenda-empty {
                padding:1.1rem;
                border-radius:0.75rem;
            }
        }
        </style>';
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
        echo '<a href="' . esc_url( add_query_arg( $prev_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">' . ( $is_week_view ? esc_html__( 'Semana anterior', 'dps-agenda-addon' ) : esc_html__( 'Dia anterior', 'dps-agenda-addon' ) ) . '</a>';
        echo '<a href="' . esc_url( add_query_arg( $next_args, $base_url ) ) . '" class="button dps-btn dps-btn--soft">' . ( $is_week_view ? esc_html__( 'Próxima semana', 'dps-agenda-addon' ) : esc_html__( 'Dia seguinte', 'dps-agenda-addon' ) ) . '</a>';
        echo '</div>';
        echo '<div class="dps-agenda-nav-group">';
        // Toggle entre visualizações e botão de calendário alternativo
        $toggle_args = array_merge( $nav_args, [ 'dps_date' => $selected_date ] );
        if ( $is_week_view ) {
            // Em visualização semanal ou de calendário, exibe botão para voltar à lista diária
            $toggle_args['view'] = 'day';
            echo '<a href="' . esc_url( add_query_arg( $toggle_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost">' . esc_html__( 'Ver Lista', 'dps-agenda-addon' ) . '</a>';
            // Se estiver no calendário, também exibe botão para a visualização semanal de lista
            if ( $view === 'calendar' ) {
                $week_args = array_merge( $nav_args, [ 'dps_date' => $selected_date, 'view' => 'week' ] );
                echo '<a href="' . esc_url( add_query_arg( $week_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost">' . esc_html__( 'Ver Semana', 'dps-agenda-addon' ) . '</a>';
            }
        } else {
            // Em visualização diária, exibe botão para a lista semanal
            $toggle_args['view'] = 'week';
            echo '<a href="' . esc_url( add_query_arg( $toggle_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost">' . esc_html__( 'Ver Semana', 'dps-agenda-addon' ) . '</a>';
        }
        echo '</div>';
        echo '<div class="dps-agenda-nav-group">';
        // Botão "Ver Calendário" removido conforme solicitação do cliente. A visualização de calendário
        // será implementada em uma futura atualização. Por enquanto, não exibe o botão para evitar confusão.
        // Botão Ver Hoje: redefine a data selecionada para a data de hoje, preservando outros filtros
        $today = current_time( 'Y-m-d' );
        $today_args = array_merge( $current_args, [ 'dps_date' => $today, 'view' => $view ] );
        // Se show_all estiver definido, removemos para voltar à visualização normal
        unset( $today_args['show_all'] );
        echo '<a href="' . esc_url( add_query_arg( $today_args, $base_url ) ) . '" class="button dps-btn dps-btn--primary">' . esc_html__( 'Ver Hoje', 'dps-agenda-addon' ) . '</a>';
        // Botão Todos os Atendimentos: remove data/view e define show_all=1
        $all_args = $current_args;
        unset( $all_args['dps_date'] );
        unset( $all_args['view'] );
        $all_args['show_all'] = '1';
        echo '<a href="' . esc_url( add_query_arg( $all_args, $base_url ) ) . '" class="button dps-btn dps-btn--ghost">' . esc_html__( 'Todos os Atendimentos', 'dps-agenda-addon' ) . '</a>';
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
        // Lista de clientes
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        // Lista de serviços
        $services = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
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
        // Cliente select
        echo '<label>' . esc_html__( 'Cliente', 'dps-agenda-addon' );
        echo '<select name="filter_client">';
        echo '<option value="0">' . esc_html__( 'Todos', 'dps-agenda-addon' ) . '</option>';
        foreach ( $clients as $cl ) {
            $selected = ( $filter_client === $cl->ID ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $cl->ID ) . '" ' . $selected . '>' . esc_html( $cl->post_title ) . '</option>';
        }
        echo '</select></label>';
        // Status select
        echo '<label>' . esc_html__( 'Status', 'dps-agenda-addon' );
        echo '<select name="filter_status">';
        foreach ( $status_options as $val => $label ) {
            $selected = ( $filter_status === $val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        // Serviço select
        echo '<label>' . esc_html__( 'Serviço', 'dps-agenda-addon' );
        echo '<select name="filter_service">';
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
            $today = current_time( 'Y-m-d' );
            $appointments['todos'] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => -1,
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
                    'posts_per_page' => -1,
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
                ] );
            }
        } else {
            // Visualização diária
            $appointments[ $selected_date ] = get_posts( [
                'post_type'      => 'dps_agendamento',
                'posts_per_page' => -1,
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
                    // Nome do pet e cliente com agressivo
                    $pet_name    = $pet_post ? $pet_post->post_title : '';
                    $client_name = $client_post ? $client_post->post_title : '';
                    $aggr_flag   = '';
                    if ( $pet_post ) {
                        $aggr = get_post_meta( $pet_post->ID, 'pet_aggressive', true );
                        if ( $aggr ) {
                            $aggr_flag = ' <span class="dps-aggressive-flag" style="color:red; font-weight:bold;">! </span>';
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
                        echo '<a href="#" class="dps-services-link" data-appt-id="' . esc_attr( $appt->ID ) . '">' . esc_html__( 'Ver serviços', 'dps-agenda-addon' ) . '</a>';
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    // Status (editable if admin)
                    echo '<td data-label="' . esc_attr( $column_labels['status'] ) . '">';
                    $plugin_role = '';
                    if ( isset( $_COOKIE['dps_base_role'] ) ) {
                        $plugin_role = sanitize_text_field( $_COOKIE['dps_base_role'] );
                    } elseif ( isset( $_COOKIE['dps_role'] ) ) {
                        $plugin_role = sanitize_text_field( $_COOKIE['dps_role'] );
                    }
                    $can_edit = ( is_user_logged_in() || $plugin_role === 'admin' );
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
                        echo '<select class="dps-status-select" data-appt-id="' . esc_attr( $appt->ID ) . '" data-current-status="' . esc_attr( $status ) . '" data-appt-version="' . esc_attr( $appt_version ) . '">';
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
                        $whatsapp  = self::format_whatsapp_number( $raw_phone );
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
                            $confirmation_html = '<a href="' . esc_url( 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode( $message ) ) . '" target="_blank">' . esc_html__( 'Confirmar via WhatsApp', 'dps-agenda-addon' ) . '</a>';
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
                        $digits       = self::format_whatsapp_number( $client_phone );
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
                            $links[]      = '<a href="' . esc_url( 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg ) ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-agenda-addon' ) . '</a>';
                            if ( ! empty( $group_data ) && (int) $appt->ID === (int) min( $group_data['ids'] ) ) {
                                $group_total = number_format_i18n( $group_data['total'], 2 );
                                $date_fmt    = $group_data['date'] ? date_i18n( 'd/m/Y', strtotime( $group_data['date'] ) ) : '';
                                $group_msg   = sprintf( 'Olá %s, tudo bem? Finalizamos os atendimentos dos pets %s em %s às %s. O valor total ficou em R$ %s. Você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: %s. Caso tenha dúvidas estamos à disposição!', $client_name, implode( ', ', $group_data['pet_names'] ), $date_fmt, $group_data['time'], $group_total, $link_to_use );
                                $group_msg   = apply_filters( 'dps_agenda_whatsapp_group_message', $group_msg, $appt, $group_data );
                                $links[]     = '<a href="' . esc_url( 'https://wa.me/' . $digits . '?text=' . rawurlencode( $group_msg ) ) . '" target="_blank">' . esc_html__( 'Cobrança conjunta', 'dps-agenda-addon' ) . '</a>';
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
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renderiza a lista de cobranças e notas geradas.
     */
    public function render_charges_notes_shortcode() {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Busca transações do tipo receita associadas a agendamentos
        $rows = $wpdb->get_results( "SELECT * FROM $table WHERE tipo = 'receita' ORDER BY data DESC" );
        ob_start();
        echo '<h3>' . __( 'Cobranças e Notas', 'dps-agenda-addon' ) . '</h3>';
        if ( ! empty( $rows ) ) {
            echo '<table class="dps-table"><thead><tr><th>' . __( 'Data', 'dps-agenda-addon' ) . '</th><th>' . __( 'Cliente', 'dps-agenda-addon' ) . '</th><th>' . __( 'Valor', 'dps-agenda-addon' ) . '</th><th>' . __( 'Status', 'dps-agenda-addon' ) . '</th><th>' . __( 'Descrição', 'dps-agenda-addon' ) . '</th></tr></thead><tbody>';
            foreach ( $rows as $row ) {
                $client_post = $row->cliente_id ? get_post( $row->cliente_id ) : null;
                echo '<tr>';
                echo '<td>' . esc_html( date_i18n( 'd-m-Y', strtotime( $row->data ) ) ) . '</td>';
                echo '<td>' . esc_html( $client_post ? $client_post->post_title : '' ) . '</td>';
                echo '<td>R$ ' . esc_html( number_format( (float) $row->valor, 2, ',', '.' ) ) . '</td>';
                echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', $row->status ) ) ) . '</td>';
                echo '<td>' . esc_html( $row->descricao ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __( 'Nenhuma cobrança ou nota registrada.', 'dps-agenda-addon' ) . '</p>';
        }
        return ob_get_clean();
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
        // Criar ou atualizar transação se necessário (finalizado ou pago)
        if ( $status === 'finalizado' || $status === 'finalizado_pago' ) {
            // Dados do agendamento
            $client_id  = get_post_meta( $id, 'appointment_client_id', true );
            $date       = get_post_meta( $id, 'appointment_date', true );
            $pet_id     = get_post_meta( $id, 'appointment_pet_id', true );
            // Valor total
            $valor = get_post_meta( $id, 'appointment_total_value', true );
            $valor = $valor ? (float) $valor : 0;
            // Descrição: lista de serviços e pet
            $desc_parts = [];
            // Recupera serviços selecionados
            $service_ids = get_post_meta( $id, 'appointment_services', true );
            if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $srv = get_post( $sid );
                    if ( $srv ) {
                        $desc_parts[] = $srv->post_title;
                    }
                }
            }
            $pet_post = $pet_id ? get_post( $pet_id ) : null;
            if ( $pet_post ) {
                $desc_parts[] = $pet_post->post_title;
            }
            $desc = implode( ' - ', $desc_parts );
            // Inserir/atualizar transação em tabela customizada
            global $wpdb;
            $table = $wpdb->prefix . 'dps_transacoes';
            // Verifica se já existe transação para este agendamento
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE agendamento_id = %d", $id ) );
            $trans_data = [
                'cliente_id'     => $client_id,
                'agendamento_id' => $id,
                'plano_id'       => null,
                'data'           => $date ? $date : current_time( 'Y-m-d' ),
                'valor'          => $valor,
                'categoria'      => __( 'Serviço', 'dps-agenda-addon' ),
                'tipo'           => 'receita',
                'status'         => ( $status === 'finalizado' ? 'em_aberto' : 'pago' ),
                'descricao'      => $desc,
            ];
            if ( $existing ) {
                $wpdb->update( $table, [
                    'status'    => $trans_data['status'],
                    'valor'     => $trans_data['valor'],
                    'descricao' => $trans_data['descricao'],
                ], [ 'id' => $existing ], [ '%s','%f','%s' ], [ '%d' ] );
            } else {
                $wpdb->insert( $table, $trans_data, [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
            }
            // Após atualizar a transação, aciona o hook dps_base_after_save_appointment para que
            // outros add-ons (como o de pagamentos) possam processar o agendamento finalizado.
            // Isso garante que o link de pagamento seja criado automaticamente mesmo quando
            // o status é alterado manualmente pela agenda.
            do_action( 'dps_base_after_save_appointment', $id, 'simple' );
            // Envia notificação via WhatsApp se classe disponível
            if ( class_exists( 'DPS_WhatsApp' ) ) {
                $client_post = $client_id ? get_post( $client_id ) : null;
                $client_name = $client_post ? $client_post->post_title : '';
                $pet_name    = $pet_post ? $pet_post->post_title : '';
                // Formata a data para dia-mês-ano
                $date_fmt    = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                $phone       = $client_id ? get_post_meta( $client_id, 'client_phone', true ) : '';
                $phone_digits= $phone ? preg_replace( '/\D+/', '', $phone ) : '';
                $valor_fmt   = number_format( (float) $valor, 2, ',', '.' );
                $msg_template = ( $status === 'finalizado_pago' ) ?
                    'Olá %s, o atendimento de %s em %s foi finalizado e o pagamento recebido. Muito obrigado!' :
                    'Olá %s, o atendimento de %s em %s foi finalizado. O valor total é R$ %s. Obrigado!';
                $message     = sprintf( $msg_template, $client_name, $pet_name, $date_fmt, $valor_fmt );
                if ( $phone_digits ) {
                    DPS_WhatsApp::send_text( $phone_digits, $message );
                }
            }
        }
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
     */
    public function get_services_details_ajax() {
        // Apenas administradores podem consultar detalhes de serviços. Garante que usuários não
        // autenticados ou sem permissão não exponham dados. Caso contrário, retorna erro.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permissão negada.', 'dps-agenda-addon' ) ] );
        }
        // Verificação de nonce tolerante: se o nonce existir, tentamos validar. Esta ação somente
        // realiza leitura de dados, portanto não bloqueamos totalmente em caso de falha, mas
        // indicamos via flag 'nonce_ok' no resultado retornado.
        $nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        $nonce_ok  = $nonce && wp_verify_nonce( $nonce, 'dps_get_services_details' );
        $id_param  = isset( $_POST['appt_id'] ) ? intval( $_POST['appt_id'] ) : 0;
        if ( ! $id_param ) {
            // Compatibilidade: aceita "id" como fallback
            $id_param = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        }
        if ( ! $id_param ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'dps-agenda-addon' ) ] );
        }
        // Recupera serviços do agendamento
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
        wp_send_json_success( [ 'services' => $services, 'nonce_ok' => $nonce_ok ] );
    }

    /**
     * Agenda um evento cron diário para enviar lembretes de agendamentos.
     * O evento é agendado às 08:00 (horário do site) caso ainda não exista.
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
     * Envia lembretes de agendamentos para clientes via e-mail.
     * Este método é executado pelo cron diário configurado em maybe_schedule_reminders().
     */
    public function send_reminders() {
        // Determina a data atual no fuso horário do site
        $date = current_time( 'Y-m-d' );
        // Busca agendamentos do dia com status pendente
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [ 'key' => 'appointment_date', 'value' => $date, 'compare' => '=' ],
            ],
        ] );
        if ( empty( $appointments ) ) {
            return;
        }
        foreach ( $appointments as $appt ) {
            // Apenas lembraremos agendamentos pendentes
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            if ( ! $status ) { $status = 'pendente'; }
            if ( $status !== 'pendente' ) {
                continue;
            }
            $client_id = get_post_meta( $appt->ID, 'appointment_client_id', true );
            $pet_id    = get_post_meta( $appt->ID, 'appointment_pet_id', true );
            if ( ! $client_id ) {
                continue;
            }
            $client_post = get_post( $client_id );
            if ( ! $client_post ) {
                continue;
            }
            // Recupera e-mail do cliente
            $client_email = get_post_meta( $client_id, 'client_email', true );
            if ( ! $client_email ) {
                // Não envia se não houver e-mail
                continue;
            }
            // Monta dados básicos
            $client_name = $client_post->post_title;
            $pet_name    = '';
            if ( $pet_id ) {
                $pet_post = get_post( $pet_id );
                if ( $pet_post ) {
                    $pet_name = $pet_post->post_title;
                }
            }
            $time  = get_post_meta( $appt->ID, 'appointment_time', true );
            $time  = $time ? $time : '';
            // Conteúdo padrão do lembrete
            $subject = sprintf( __( 'Lembrete de agendamento para %s', 'dps-agenda-addon' ), $client_name );
            // Corpo do e-mail com dados do serviço
            $message  = sprintf( __( 'Olá %s,\n\nEste é um lembrete do agendamento para %s no dia %s às %s.\n\nEstamos aguardando você!\n\nAtenciosamente,\nDesi Pet Shower', 'dps-agenda-addon' ), $client_name, $pet_name ? $pet_name : __( 'seu pet', 'dps-agenda-addon' ), date_i18n( 'd-m-Y', strtotime( $date ) ), $time );
            // Permite personalização via filtros
            $recipients = apply_filters( 'dps_agenda_reminder_recipients', [ $client_email ], $appt->ID );
            $subject    = apply_filters( 'dps_agenda_reminder_subject', $subject, $appt->ID );
            $message    = apply_filters( 'dps_agenda_reminder_content', $message, $appt->ID );
            // Envia email
            foreach ( $recipients as $recipient ) {
                wp_mail( $recipient, $subject, $message );
            }
        }
    }

    private static function format_whatsapp_number( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );
        if ( strlen( $digits ) >= 10 && substr( $digits, 0, 2 ) !== '55' ) {
            $digits = '55' . $digits;
        }
        return $digits;
    }
}

new DPS_Agenda_Addon();
