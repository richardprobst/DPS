<?php
/**
 * Client Page Renderer — renderização da página de detalhes do cliente.
 *
 * Extraído de class-dps-base-frontend.php (Fase 2.1) para Single Responsibility.
 * Responsável por renderizar a página individual de cada cliente no admin.
 *
 * @package DesiPetShower
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Client_Page_Renderer {

    /**
     * Renderiza a página de detalhes do cliente (ponto de entrada público).
     *
     * @since 2.0.0
     * @param int $client_id ID do cliente.
     * @return string HTML da página.
     */
    public static function render( $client_id ) {
        $client = get_post( $client_id );
        if ( ! $client || 'dps_cliente' !== $client->post_type ) {
            return '<p>' . esc_html__( 'Cliente não encontrado.', 'desi-pet-shower' ) . '</p>';
        }

        // Processar ações antes de renderizar
        self::handle_client_page_actions( $client_id );

        // Coletar dados do cliente
        $data = self::prepare_client_page_data( $client_id, $client );

        ob_start();

        // Mensagens de feedback
        self::render_client_page_notices( $client_id );

        echo '<div class="dps-client-detail">';

        // Header com título e ações
        self::render_client_page_header( $client, $data['base_url'], $client_id );

        // Cards de resumo/métricas
        self::render_client_summary_cards( $data['appointments'], $data['pending_amount'], $client );

        // Seção: Dados Pessoais
        self::render_client_personal_section( $data['meta'], $client );

        /**
         * Hook para adicionar seções personalizadas após os dados pessoais.
         * Útil para add-ons que precisam exibir informações complementares.
         *
         * @since 1.2.0
         * @param int     $client_id ID do cliente.
         * @param WP_Post $client    Objeto do post do cliente.
         * @param array   $meta      Metadados do cliente.
         */
        do_action( 'dps_client_page_after_personal_section', $client_id, $client, $data['meta'] );

        // Seção: Contato e Redes
        self::render_client_contact_section( $data['meta'] );

        /**
         * Hook para adicionar seções personalizadas após contato.
         * Útil para add-ons de fidelidade, comunicações, etc.
         *
         * @since 1.2.0
         * @param int     $client_id ID do cliente.
         * @param WP_Post $client    Objeto do post do cliente.
         * @param array   $meta      Metadados do cliente.
         */
        do_action( 'dps_client_page_after_contact_section', $client_id, $client, $data['meta'] );

        // Seção: Endereço
        self::render_client_address_section( $data['meta'] );

        // Seção: Notas Internas (apenas para administradores)
        self::render_client_notes_section( $data['meta'], $client_id );

        // Seção: Pets
        self::render_client_pets_section( $data['pets'], $data['base_url'], $client_id );

        /**
         * Hook para adicionar seções personalizadas após pets.
         * Útil para add-ons que precisam exibir informações de assinaturas ou pacotes.
         *
         * @since 1.2.0
         * @param int     $client_id ID do cliente.
         * @param WP_Post $client    Objeto do post do cliente.
         * @param array   $pets      Lista de pets do cliente.
         */
        do_action( 'dps_client_page_after_pets_section', $client_id, $client, $data['pets'] );

        // Seção: Histórico de Atendimentos
        self::render_client_appointments_section( $data['appointments'], $data['base_url'], $client_id );

        /**
         * Hook para adicionar seções personalizadas após histórico.
         * Útil para add-ons financeiros, estatísticas avançadas, etc.
         *
         * @since 1.2.0
         * @param int     $client_id    ID do cliente.
         * @param WP_Post $client       Objeto do post do cliente.
         * @param array   $appointments Lista de agendamentos do cliente.
         */
        do_action( 'dps_client_page_after_appointments_section', $client_id, $client, $data['appointments'] );

        echo '</div>';

        // Script para envio de histórico por email
        self::render_client_page_scripts();

        return ob_get_clean();
    }

    /**
     * Processa ações da página de detalhes do cliente (gerar histórico, enviar email, etc).
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     */
    private static function handle_client_page_actions( $client_id ) {
        // 1. Gerar histórico HTML (requer nonce para proteção CSRF)
        if ( isset( $_GET['dps_client_history'] ) && '1' === $_GET['dps_client_history'] ) {
            // Verifica nonce para proteção CSRF usando helper
            if ( ! DPS_Request_Validator::verify_admin_action( 'dps_client_history', null, '_wpnonce', false ) ) {
                DPS_Message_Helper::add_error( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
                $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', '_wpnonce' ] ) );
                wp_safe_redirect( $redirect );
                exit;
            }
            
            $doc_url = self::generate_client_history_doc( $client_id );
            if ( $doc_url ) {
                // Envio por email se solicitado
                if ( isset( $_GET['send_email'] ) && '1' === $_GET['send_email'] ) {
                    $raw_email = isset( $_GET['to_email'] ) ? wp_unslash( $_GET['to_email'] ) : '';
                    $to_email  = is_email( sanitize_email( $raw_email ) ) ? sanitize_email( $raw_email ) : '';
                    self::send_client_history_email( $client_id, $doc_url, $to_email );
                    $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'sent' => '1' ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'sent', '_wpnonce' ] ) );
                    wp_safe_redirect( $redirect );
                    exit;
                }
                $file_name = basename( $doc_url );
                $redirect  = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'history_file' => $file_name ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', 'history_file', '_wpnonce' ] ) );
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        // 2. Exclusão de documentos (requer nonce para proteção CSRF)
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) ) {
            // Verifica nonce para proteção CSRF usando helper
            if ( ! DPS_Request_Validator::verify_admin_action( 'dps_delete_doc', null, '_wpnonce', false ) ) {
                DPS_Message_Helper::add_error( __( 'Ação não autorizada.', 'desi-pet-shower' ) );
                $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_delete_doc', 'file', '_wpnonce' ] ) );
                wp_safe_redirect( $redirect );
                exit;
            }
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            self::delete_document( $file );
            DPS_Message_Helper::add_success( __( 'Documento excluído com sucesso.', 'desi-pet-shower' ) );
            $redirect = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id ], remove_query_arg( [ 'dps_delete_doc', 'file', '_wpnonce' ] ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    /**
     * Prepara todos os dados necessários para a página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param int     $client_id ID do cliente.
     * @param WP_Post $client    Post do cliente.
     * @return array Dados preparados.
     */
    private static function prepare_client_page_data( $client_id, $client ) {
        // Metadados do cliente
        $meta = [
            'cpf'            => get_post_meta( $client_id, 'client_cpf', true ),
            'phone'          => get_post_meta( $client_id, 'client_phone', true ),
            'email'          => get_post_meta( $client_id, 'client_email', true ),
            'birth'          => get_post_meta( $client_id, 'client_birth', true ),
            'instagram'      => get_post_meta( $client_id, 'client_instagram', true ),
            'facebook'       => get_post_meta( $client_id, 'client_facebook', true ),
            'photo_auth'     => get_post_meta( $client_id, 'client_photo_auth', true ),
            'address'        => get_post_meta( $client_id, 'client_address', true ),
            'referral'       => get_post_meta( $client_id, 'client_referral', true ),
            'internal_notes' => get_post_meta( $client_id, 'client_internal_notes', true ),
        ];

        // Lista de pets
        $pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );

        // Pré-carregar metadados dos pets
        if ( $pets ) {
            $pet_ids = wp_list_pluck( $pets, 'ID' );
            update_meta_cache( 'post', $pet_ids );
        }

        // Lista de agendamentos ordenada por data (mais recente primeiro para exibição)
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );

        // Pré-carregar metadados dos agendamentos
        if ( $appointments ) {
            $appt_ids = wp_list_pluck( $appointments, 'ID' );
            update_meta_cache( 'post', $appt_ids );
        }

        // Calcular pendências financeiras
        $pending_amount = self::calculate_client_pending_amount( $client_id );

        return [
            'meta'           => $meta,
            'pets'           => $pets,
            'appointments'   => $appointments,
            'pending_amount' => $pending_amount,
            'base_url'       => DPS_URL_Builder::safe_get_permalink(),
        ];
    }

    /**
     * Calcula o valor total de pendências financeiras do cliente.
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     * @return float Valor total pendente.
     */
    private static function calculate_client_pending_amount( $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Verifica se a tabela existe (usa cache estático para evitar verificação repetida)
        static $table_exists = null;
        if ( null === $table_exists ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
        }
        if ( ! $table_exists ) {
            return 0.0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
        $pending = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM {$table} WHERE cliente_id = %d AND status = %s",
                $client_id,
                'em_aberto'
            )
        );

        return $pending ? (float) $pending : 0.0;
    }

    /**
     * Renderiza mensagens de feedback na página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param int $client_id ID do cliente.
     */
    private static function render_client_page_notices( $client_id ) {
        // Histórico gerado com sucesso
        if ( isset( $_GET['history_file'] ) ) {
            $file    = sanitize_file_name( wp_unslash( $_GET['history_file'] ) );
            $uploads = wp_upload_dir();
            $url     = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $file;
            echo '<div class="dps-alert dps-alert--success">';
            echo '<strong>' . esc_html__( 'Histórico gerado com sucesso!', 'desi-pet-shower' ) . '</strong> ';
            echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Clique aqui para abrir', 'desi-pet-shower' ) . '</a>';
            echo '</div>';
        }

        // Histórico enviado por email
        if ( isset( $_GET['sent'] ) && '1' === $_GET['sent'] ) {
            echo '<div class="dps-alert dps-alert--success">';
            echo esc_html__( 'Histórico enviado por email com sucesso.', 'desi-pet-shower' );
            echo '</div>';
        }
    }

    /**
     * Renderiza o header da página de detalhes do cliente.
     *
     * @since 1.0.0
     * @param WP_Post $client    Post do cliente.
     * @param string  $base_url  URL base da página.
     * @param int     $client_id ID do cliente.
     */
    private static function render_client_page_header( $client, $base_url, $client_id ) {
        $back_url     = remove_query_arg( [ 'dps_view', 'id', 'tab' ] );
        $edit_url     = add_query_arg( [ 'tab' => 'clientes', 'dps_edit' => 'client', 'id' => $client_id ], $base_url );
        $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client_id ], $base_url );

        // Barra de navegação superior
        echo '<div class="dps-client-nav">';
        echo '<a href="' . esc_url( $back_url ) . '" class="dps-client-nav__back" aria-label="' . esc_attr__( 'Voltar para lista de clientes', 'desi-pet-shower' ) . '">← ' . esc_html__( 'Voltar', 'desi-pet-shower' ) . '</a>';
        echo '</div>';

        // Header principal com título e ações primárias
        echo '<div class="dps-client-header">';
        echo '<div class="dps-client-header__info">';
        echo '<h2 class="dps-client-header__title">' . esc_html( $client->post_title ) . '</h2>';
        
        // Sub-info com hook para add-ons adicionarem badges (fidelidade, etc.)
        echo '<div class="dps-client-header__badges">';
        /**
         * Hook para adicionar badges ao lado do nome do cliente.
         * Útil para add-ons de fidelidade mostrarem nível/status.
         *
         * @since 1.3.0
         * @param int     $client_id ID do cliente.
         * @param WP_Post $client    Objeto do post do cliente.
         */
        do_action( 'dps_client_page_header_badges', $client_id, $client );
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-client-header__primary-actions">';
        echo '<a href="' . esc_url( $edit_url ) . '" class="dps-btn-action" aria-label="' . esc_attr__( 'Editar dados do cliente', 'desi-pet-shower' ) . '">';
        echo '<span aria-hidden="true">✏️</span> ' . esc_html__( 'Editar', 'desi-pet-shower' );
        echo '</a>';
        echo '<a href="' . esc_url( $schedule_url ) . '" class="dps-btn-action dps-btn-action--primary" aria-label="' . esc_attr__( 'Agendar novo atendimento', 'desi-pet-shower' ) . '">';
        echo '<span aria-hidden="true">📅</span> ' . esc_html__( 'Novo Agendamento', 'desi-pet-shower' );
        echo '</a>';
        echo '</div>';
        echo '</div>';

        // Painel de Ações Rápidas (Links de Consentimento, Atualização, etc.)
        self::render_client_quick_actions_panel( $client_id, $client, $base_url );
    }

    /**
     * Renderiza o painel de ações rápidas do cliente.
     * 
     * Agrupa links de consentimento, atualização de perfil e outras ações
     * externas em um painel organizado visualmente.
     *
     * @since 1.3.0
     * @param int     $client_id ID do cliente.
     * @param WP_Post $client    Objeto do post do cliente.
     * @param string  $base_url  URL base da página.
     */
    private static function render_client_quick_actions_panel( $client_id, $client, $base_url ) {
        // Verifica se há ações a serem renderizadas
        $has_actions = has_action( 'dps_client_page_header_actions' );
        
        // Só renderiza o painel se houver ações registradas
        if ( ! $has_actions ) {
            return;
        }

        echo '<div class="dps-quick-actions-panel">';
        echo '<div class="dps-quick-actions-panel__header">';
        echo '<h4 class="dps-quick-actions-panel__title">';
        echo '<span aria-hidden="true">⚡</span> ' . esc_html__( 'Ações Rápidas', 'desi-pet-shower' );
        echo '</h4>';
        echo '<p class="dps-quick-actions-panel__description">' . esc_html__( 'Envie links para o cliente atualizar dados ou assinar documentos.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';
        echo '<div class="dps-quick-actions-panel__content">';
        
        /**
         * Hook para adicionar ações extras ao painel de ações rápidas.
         * Usado pelo client-portal add-on para adicionar botão de gerar link de atualização.
         * Usado pelo base para adicionar botão de consentimento de tosa.
         *
         * @since 1.1.0
         * @since 1.3.0 Movido para painel dedicado com melhor organização visual.
         * @param int     $client_id ID do cliente.
         * @param WP_Post $client    Objeto do post do cliente.
         * @param string  $base_url  URL base da página.
         */
        do_action( 'dps_client_page_header_actions', $client_id, $client, $base_url );
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza os cards de resumo/métricas do cliente.
     *
     * @since 1.0.0
     * @since 1.2.0 Adicionado parâmetro $client para exibir data de cadastro.
     * @param array        $appointments   Lista de agendamentos.
     * @param float        $pending_amount Valor pendente.
     * @param WP_Post|null $client         Objeto do post do cliente (opcional, para exibir data de cadastro).
     */
    private static function render_client_summary_cards( $appointments, $pending_amount, $client = null ) {
        $total_appointments = count( $appointments );
        $last_appointment   = '';
        $total_spent        = 0.0;

        foreach ( $appointments as $appt ) {
            $status = get_post_meta( $appt->ID, 'appointment_status', true );
            $value  = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );

            // Soma apenas atendimentos finalizados e pagos
            if ( in_array( $status, [ 'finalizado_pago', 'finalizado e pago' ], true ) ) {
                $total_spent += $value;
            }

            // Pega a data do último atendimento (primeiro da lista que está ordenada DESC)
            if ( empty( $last_appointment ) ) {
                $date = get_post_meta( $appt->ID, 'appointment_date', true );
                if ( $date ) {
                    $last_appointment = date_i18n( 'd/m/Y', strtotime( $date ) );
                }
            }
        }

        // Calcula tempo de cadastro (cliente desde)
        // Usa formato explícito 'm/Y' para consistência entre locales
        $client_since = '';
        if ( $client && isset( $client->post_date ) ) {
            $post_datetime = get_post_datetime( $client, 'date', 'gmt' );
            if ( $post_datetime ) {
                $client_since = $post_datetime->format( 'm/Y' );
            }
        }

        echo '<div class="dps-client-summary">';

        // Cliente desde (data de cadastro)
        if ( $client_since ) {
            echo '<div class="dps-summary-card">';
            echo '<span class="dps-summary-card__icon" aria-hidden="true">🗓️</span>';
            echo '<span class="dps-summary-card__value">' . esc_html( $client_since ) . '</span>';
            echo '<span class="dps-summary-card__label">' . esc_html__( 'Cliente Desde', 'desi-pet-shower' ) . '</span>';
            echo '</div>';
        }

        // Total de atendimentos
        echo '<div class="dps-summary-card dps-summary-card--highlight">';
        echo '<span class="dps-summary-card__icon" aria-hidden="true">📋</span>';
        echo '<span class="dps-summary-card__value">' . esc_html( $total_appointments ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Total de Atendimentos', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Total gasto
        echo '<div class="dps-summary-card dps-summary-card--success">';
        echo '<span class="dps-summary-card__icon" aria-hidden="true">💰</span>';
        echo '<span class="dps-summary-card__value">R$ ' . esc_html( number_format_i18n( $total_spent, 2 ) ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Total Gasto', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Último atendimento
        echo '<div class="dps-summary-card">';
        echo '<span class="dps-summary-card__icon" aria-hidden="true">📅</span>';
        echo '<span class="dps-summary-card__value">' . esc_html( $last_appointment ?: '-' ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Último Atendimento', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        // Pendências
        $pending_class = $pending_amount > 0 ? 'dps-summary-card--warning' : '';
        echo '<div class="dps-summary-card ' . esc_attr( $pending_class ) . '">';
        echo '<span class="dps-summary-card__icon" aria-hidden="true">' . ( $pending_amount > 0 ? '⚠️' : '✅' ) . '</span>';
        echo '<span class="dps-summary-card__value">R$ ' . esc_html( number_format_i18n( $pending_amount, 2 ) ) . '</span>';
        echo '<span class="dps-summary-card__label">' . esc_html__( 'Pendências', 'desi-pet-shower' ) . '</span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Renderiza a seção de dados pessoais do cliente.
     *
     * @since 1.0.0
     * @since 1.2.0 Adicionado parâmetro $client para exibir data de cadastro.
     * @param array        $meta   Metadados do cliente.
     * @param WP_Post|null $client Objeto do post do cliente (opcional, para exibir data de cadastro).
     */
    private static function render_client_personal_section( $meta, $client = null ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">👤 ' . esc_html__( 'Dados Pessoais', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // CPF
        $has_cpf = ! empty( $meta['cpf'] );
        echo '<div class="dps-info-item' . ( $has_cpf ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'CPF', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_cpf ? $meta['cpf'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Data de nascimento
        $has_birth = ! empty( $meta['birth'] );
        $birth_fmt = $has_birth ? date_i18n( 'd/m/Y', strtotime( $meta['birth'] ) ) : '';
        echo '<div class="dps-info-item' . ( $has_birth ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Data de Nascimento', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_birth ? $birth_fmt : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Data de cadastro - usa get_post_datetime para manipulação de data mais confiável
        if ( $client ) {
            $post_datetime = get_post_datetime( $client, 'date', 'gmt' );
            if ( $post_datetime ) {
                $register_date = $post_datetime->format( 'd/m/Y' );
                echo '<div class="dps-info-item">';
                echo '<span class="dps-info-item__label">' . esc_html__( 'Data de Cadastro', 'desi-pet-shower' ) . '</span>';
                echo '<span class="dps-info-item__value">' . esc_html( $register_date ) . '</span>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de contato e redes sociais do cliente.
     *
     * @since 1.0.0
     * @param array $meta Metadados do cliente.
     */
    private static function render_client_contact_section( $meta ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">📞 ' . esc_html__( 'Contato e Redes Sociais', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // Telefone/WhatsApp
        $has_phone = ! empty( $meta['phone'] );
        echo '<div class="dps-info-item' . ( $has_phone ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Telefone / WhatsApp', 'desi-pet-shower' ) . '</span>';
        if ( $has_phone ) {
            // Usa helper centralizado se disponível, senão faz fallback com código do Brasil
            if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                $wa_url = DPS_WhatsApp_Helper::get_link_to_client( $meta['phone'] );
            } else {
                // Fallback: remove não-dígitos e adiciona código do Brasil se necessário
                $phone_digits = preg_replace( '/\D+/', '', $meta['phone'] );
                // Adiciona código do Brasil (55) se o número não começar com ele
                if ( strlen( $phone_digits ) <= 11 && '55' !== substr( $phone_digits, 0, 2 ) ) {
                    $phone_digits = '55' . $phone_digits;
                }
                $wa_url = 'https://wa.me/' . $phone_digits;
            }
            echo '<span class="dps-info-item__value"><a href="' . esc_url( $wa_url ) . '" target="_blank">' . esc_html( $meta['phone'] ) . ' 📱</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Email
        $has_email = ! empty( $meta['email'] );
        echo '<div class="dps-info-item' . ( $has_email ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Email', 'desi-pet-shower' ) . '</span>';
        if ( $has_email ) {
            echo '<span class="dps-info-item__value"><a href="mailto:' . esc_attr( $meta['email'] ) . '">' . esc_html( $meta['email'] ) . '</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Instagram
        $has_instagram = ! empty( $meta['instagram'] );
        echo '<div class="dps-info-item' . ( $has_instagram ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">Instagram</span>';
        if ( $has_instagram ) {
            $ig_handle = ltrim( $meta['instagram'], '@' );
            echo '<span class="dps-info-item__value"><a href="https://instagram.com/' . esc_attr( $ig_handle ) . '" target="_blank">@' . esc_html( $ig_handle ) . '</a></span>';
        } else {
            echo '<span class="dps-info-item__value">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Facebook
        $has_facebook = ! empty( $meta['facebook'] );
        echo '<div class="dps-info-item' . ( $has_facebook ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">Facebook</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_facebook ? $meta['facebook'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Autorização de fotos - agora com badge visual
        $photo_auth_val = $meta['photo_auth'];
        echo '<div class="dps-info-item">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Autorização para Fotos', 'desi-pet-shower' ) . '</span>';
        if ( '' !== $photo_auth_val && null !== $photo_auth_val ) {
            if ( $photo_auth_val ) {
                echo '<span class="dps-info-item__value"><span class="dps-status-badge dps-status-badge--completed">✓ ' . esc_html__( 'Autorizado', 'desi-pet-shower' ) . '</span></span>';
            } else {
                echo '<span class="dps-info-item__value"><span class="dps-status-badge dps-status-badge--cancelled">✕ ' . esc_html__( 'Não Autorizado', 'desi-pet-shower' ) . '</span></span>';
            }
        } else {
            echo '<span class="dps-info-item__value dps-info-item--empty">' . esc_html__( 'Não informado', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de endereço do cliente.
     *
     * @since 1.0.0
     * @param array $meta Metadados do cliente.
     */
    private static function render_client_address_section( $meta ) {
        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">📍 ' . esc_html__( 'Endereço e Indicação', 'desi-pet-shower' ) . '</h3>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        echo '<div class="dps-info-grid">';

        // Endereço
        $has_address = ! empty( $meta['address'] );
        echo '<div class="dps-info-item' . ( $has_address ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Endereço Completo', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_address ? $meta['address'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        // Como nos conheceu
        $has_referral = ! empty( $meta['referral'] );
        echo '<div class="dps-info-item' . ( $has_referral ? '' : ' dps-info-item--empty' ) . '">';
        echo '<span class="dps-info-item__label">' . esc_html__( 'Como nos Conheceu', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-info-item__value">' . esc_html( $has_referral ? $meta['referral'] : __( 'Não informado', 'desi-pet-shower' ) ) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de notas internas do cliente.
     * 
     * Campo de texto editável para anotações administrativas sobre o cliente.
     * Visível apenas para administradores.
     *
     * @since 1.3.0
     * @param array $meta      Metadados do cliente.
     * @param int   $client_id ID do cliente.
     */
    private static function render_client_notes_section( $meta, $client_id ) {
        // Verifica se o usuário pode gerenciar clientes
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'dps_manage_clients' ) ) {
            return;
        }

        $notes       = isset( $meta['internal_notes'] ) ? $meta['internal_notes'] : '';
        $save_nonce  = wp_create_nonce( 'dps_save_client_notes_' . $client_id );

        echo '<div class="dps-client-section dps-client-section--notes">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">';
        echo '<span aria-hidden="true">📝</span> ' . esc_html__( 'Notas Internas', 'desi-pet-shower' );
        echo '</h3>';
        echo '<p class="dps-client-section__subtitle">' . esc_html__( 'Anotações visíveis apenas para a equipe.', 'desi-pet-shower' ) . '</p>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';
        
        echo '<form class="dps-notes-form" id="dps-notes-form-' . esc_attr( $client_id ) . '">';
        echo '<input type="hidden" name="client_id" value="' . esc_attr( $client_id ) . '">';
        echo '<input type="hidden" name="nonce" value="' . esc_attr( $save_nonce ) . '">';
        
        echo '<div class="dps-notes-editor">';
        echo '<textarea name="internal_notes" class="dps-notes-textarea" rows="4" placeholder="' . esc_attr__( 'Adicione anotações sobre este cliente: preferências, observações importantes, lembretes...', 'desi-pet-shower' ) . '">' . esc_textarea( $notes ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-notes-actions">';
        echo '<button type="submit" class="dps-submit-btn dps-save-notes-btn" data-client-id="' . esc_attr( $client_id ) . '">';
        echo esc_html__( 'Salvar Notas', 'desi-pet-shower' );
        echo '</button>';
        echo '<span class="dps-notes-status"></span>';
        echo '</div>';
        
        echo '</form>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de pets do cliente.
     *
     * @since 1.0.0
     * @param array  $pets      Lista de pets.
     * @param string $base_url  URL base da página.
     * @param int    $client_id ID do cliente.
     */
    private static function render_client_pets_section( $pets, $base_url, $client_id ) {
        $pet_count   = count( $pets );
        $add_pet_url = add_query_arg( [ 'tab' => 'pets', 'pref_owner' => $client_id ], $base_url );

        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">';
        echo '🐾 ' . esc_html__( 'Pets', 'desi-pet-shower' );
        echo '<span class="dps-client-section__count">' . esc_html( $pet_count ) . '</span>';
        echo '</h3>';
        echo '<div class="dps-client-section__actions">';
        echo '<a href="' . esc_url( $add_pet_url ) . '" class="button button-secondary">+ ' . esc_html__( 'Adicionar Pet', 'desi-pet-shower' ) . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';

        if ( $pets ) {
            echo '<div class="dps-pet-cards">';

            foreach ( $pets as $pet ) {
                self::render_pet_card( $pet, $base_url, $client_id );
            }

            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">🐕</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado', 'desi-pet-shower' ) . '</h4>';
            echo '<p class="dps-empty-state__description">' . esc_html__( 'Este cliente ainda não possui pets cadastrados. Clique no botão acima para adicionar.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza um card individual de pet.
     *
     * @since 1.0.0
     * @param WP_Post $pet       Post do pet.
     * @param string  $base_url  URL base da página.
     * @param int     $client_id ID do cliente.
     */
    private static function render_pet_card( $pet, $base_url, $client_id ) {
        // Metadados do pet
        $photo_id   = get_post_meta( $pet->ID, 'pet_photo_id', true );
        $species    = get_post_meta( $pet->ID, 'pet_species', true );
        $breed      = get_post_meta( $pet->ID, 'pet_breed', true );
        $size       = get_post_meta( $pet->ID, 'pet_size', true );
        $weight     = get_post_meta( $pet->ID, 'pet_weight', true );
        $coat       = get_post_meta( $pet->ID, 'pet_coat', true );
        $color      = get_post_meta( $pet->ID, 'pet_color', true );
        $birth      = get_post_meta( $pet->ID, 'pet_birth', true );
        $sex        = get_post_meta( $pet->ID, 'pet_sex', true );
        $care       = get_post_meta( $pet->ID, 'pet_care', true );
        $aggressive = get_post_meta( $pet->ID, 'pet_aggressive', true );

        // Traduzir labels
        $species_label = self::get_pet_species_label( $species );
        $size_label    = self::get_pet_size_label( $size );
        $sex_label     = self::get_pet_sex_label( $sex );

        // URLs de ação
        $edit_url     = add_query_arg( [ 'tab' => 'pets', 'dps_edit' => 'pet', 'id' => $pet->ID ], $base_url );
        $schedule_url = add_query_arg( [ 'tab' => 'agendas', 'pref_client' => $client_id, 'pref_pet' => $pet->ID ], $base_url );

        // Classes do card
        $card_class = 'dps-pet-card';
        if ( $aggressive ) {
            $card_class .= ' dps-pet-card--aggressive';
        }

        // Ícone da espécie
        $species_icon = '🐾';
        if ( 'cao' === $species ) {
            $species_icon = '🐕';
        } elseif ( 'gato' === $species ) {
            $species_icon = '🐈';
        }

        echo '<div class="' . esc_attr( $card_class ) . '">';

        // Header do card
        echo '<div class="dps-pet-card__header">';
        if ( $photo_id ) {
            $img_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
            if ( $img_url ) {
                echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pet->post_title ) . '" class="dps-pet-card__photo" loading="lazy">';
            } else {
                echo '<div class="dps-pet-card__photo dps-pet-card__photo--placeholder">' . $species_icon . '</div>';
            }
        } else {
            echo '<div class="dps-pet-card__photo dps-pet-card__photo--placeholder">' . $species_icon . '</div>';
        }
        echo '<div class="dps-pet-card__title">';
        echo '<h4 class="dps-pet-card__name">' . esc_html( $pet->post_title ) . '</h4>';
        echo '<p class="dps-pet-card__subtitle">' . esc_html( $species_label ) . ( $breed ? ' • ' . esc_html( $breed ) : '' ) . '</p>';
        echo '</div>';
        if ( $aggressive ) {
            echo '<span class="dps-pet-card__badge">⚠️ ' . esc_html__( 'Agressivo', 'desi-pet-shower' ) . '</span>';
        }
        echo '</div>';

        // Body do card
        echo '<div class="dps-pet-card__body">';
        echo '<div class="dps-pet-card__info">';

        // Porte
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Porte', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . esc_html( $size_label ?: '-' ) . '</span>';
        echo '</div>';

        // Peso
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Peso', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . ( $weight ? esc_html( $weight ) . ' kg' : '-' ) . '</span>';
        echo '</div>';

        // Sexo
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Sexo', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . esc_html( $sex_label ?: '-' ) . '</span>';
        echo '</div>';

        // Nascimento
        echo '<div class="dps-pet-card__info-item">';
        echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Nascimento', 'desi-pet-shower' ) . '</span>';
        echo '<span class="dps-pet-card__info-value">' . ( $birth ? esc_html( date_i18n( 'd/m/Y', strtotime( $birth ) ) ) : '-' ) . '</span>';
        echo '</div>';

        // Pelagem
        if ( $coat || $color ) {
            echo '<div class="dps-pet-card__info-item">';
            echo '<span class="dps-pet-card__info-label">' . esc_html__( 'Pelagem', 'desi-pet-shower' ) . '</span>';
            $pelagem = [];
            if ( $coat ) {
                $pelagem[] = $coat;
            }
            if ( $color ) {
                $pelagem[] = $color;
            }
            echo '<span class="dps-pet-card__info-value">' . esc_html( implode( ', ', $pelagem ) ) . '</span>';
            echo '</div>';
        }

        echo '</div>';

        // Cuidados especiais (se houver)
        if ( $care ) {
            echo '<div class="dps-pet-card__notes">' . esc_html( $care ) . '</div>';
        }

        // Ações
        echo '<div class="dps-pet-card__actions">';
        echo '<a href="' . esc_url( $edit_url ) . '" class="dps-submit-btn dps-submit-btn--secondary">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a>';
        echo '<a href="' . esc_url( $schedule_url ) . '" class="dps-submit-btn">' . esc_html__( 'Agendar', 'desi-pet-shower' ) . '</a>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de histórico de atendimentos do cliente.
     *
     * @since 1.0.0
     * @param array  $appointments Lista de agendamentos.
     * @param string $base_url     URL base da página.
     * @param int    $client_id    ID do cliente.
     */
    private static function render_client_appointments_section( $appointments, $base_url, $client_id ) {
        $appt_count   = count( $appointments );
        $history_nonce = wp_create_nonce( 'dps_client_history' );
        $history_link = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1', '_wpnonce' => $history_nonce ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', '_wpnonce' ] ) );
        $email_base   = add_query_arg( [ 'dps_view' => 'client', 'id' => $client_id, 'dps_client_history' => '1', 'send_email' => '1', '_wpnonce' => $history_nonce ], remove_query_arg( [ 'dps_client_history', 'send_email', 'to_email', '_wpnonce' ] ) );

        echo '<div class="dps-client-section">';
        echo '<div class="dps-client-section__header">';
        echo '<h3 class="dps-client-section__title">';
        echo '📋 ' . esc_html__( 'Histórico de Atendimentos', 'desi-pet-shower' );
        echo '<span class="dps-client-section__count">' . esc_html( $appt_count ) . '</span>';
        echo '</h3>';
        echo '<div class="dps-client-section__actions">';
        echo '<button type="button" class="button button-secondary" id="dps-client-export-csv">' . esc_html__( 'Exportar CSV', 'desi-pet-shower' ) . '</button>';
        echo '<a href="' . esc_url( $history_link ) . '" class="button button-secondary">' . esc_html__( 'Gerar Relatório', 'desi-pet-shower' ) . '</a>';
        echo '<a href="#" class="button button-secondary dps-send-history-email" data-base="' . esc_url( $email_base ) . '">' . esc_html__( 'Enviar por Email', 'desi-pet-shower' ) . '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-client-section__content">';

        if ( $appointments ) {
            echo '<div class="dps-table-wrapper">';
            echo '<table class="dps-table" id="dps-client-history-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Horário', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Serviços', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'desi-pet-shower' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Observações', 'desi-pet-shower' ) . '</th>';
            echo '<th class="hide-mobile">' . esc_html__( 'Operacional', 'desi-pet-shower' ) . '</th>';
            echo '<th class="dps-no-export">' . esc_html__( 'Ações', 'desi-pet-shower' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            // Caches para evitar múltiplas queries
            $pet_cache      = [];
            $services_cache = [];

            foreach ( $appointments as $appt ) {
                $date        = get_post_meta( $appt->ID, 'appointment_date', true );
                $time        = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id      = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $notes       = get_post_meta( $appt->ID, 'appointment_notes', true );
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $total_value = (float) get_post_meta( $appt->ID, 'appointment_total_value', true );
                $services    = get_post_meta( $appt->ID, 'appointment_services', true );

                // Obter nome do pet (com cache)
                $pet_name = '-';
                if ( $pet_id ) {
                    if ( ! isset( $pet_cache[ $pet_id ] ) ) {
                        $pet = get_post( $pet_id );
                        $pet_cache[ $pet_id ] = $pet ? $pet->post_title : '-';
                    }
                    $pet_name = $pet_cache[ $pet_id ];
                }

                // Obter nomes dos serviços (com cache)
                $services_text = '-';
                if ( is_array( $services ) && ! empty( $services ) ) {
                    $names = [];
                    foreach ( $services as $srv_id ) {
                        if ( ! array_key_exists( $srv_id, $services_cache ) ) {
                            $srv = get_post( $srv_id );
                            $services_cache[ $srv_id ] = $srv ? $srv->post_title : '';
                        }
                        if ( ! empty( $services_cache[ $srv_id ] ) ) {
                            $names[] = $services_cache[ $srv_id ];
                        }
                    }
                    if ( $names ) {
                        $services_text = implode( ', ', $names );
                    }
                }

                // Status badge
                $status_info = self::get_appointment_status_info( $status_meta );

                $date_fmt = $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) : '-';

                // Limite de palavras para observações na tabela
                $notes_word_limit = apply_filters( 'dps_client_history_notes_word_limit', 10 );

                // URLs de ação
                $edit_url      = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                $duplicate_url = add_query_arg( [ 'tab' => 'agendas', 'dps_duplicate' => 'appointment', 'id' => $appt->ID ], $base_url );

                echo '<tr>';
                echo '<td>' . esc_html( $date_fmt ) . '</td>';
                echo '<td>' . esc_html( $time ?: '-' ) . '</td>';
                echo '<td>' . esc_html( $pet_name ) . '</td>';
                echo '<td class="hide-mobile">' . esc_html( $services_text ) . '</td>';
                echo '<td>R$ ' . esc_html( number_format_i18n( $total_value, 2 ) ) . '</td>';
                echo '<td><span class="dps-status-badge ' . esc_attr( $status_info['class'] ) . '">' . esc_html( $status_info['label'] ) . '</span></td>';
                echo '<td class="hide-mobile">' . esc_html( $notes ? wp_trim_words( $notes, $notes_word_limit, '...' ) : '-' ) . '</td>';
                // Coluna Operacional (Checklist + Check-in/Check-out)
                echo '<td class="hide-mobile">';
                if ( class_exists( 'DPS_Agenda_Addon' ) ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
                    echo DPS_Agenda_Addon::render_checkin_checklist_summary( $appt->ID );
                }
                echo '</td>';
                echo '<td class="dps-actions-cell dps-no-export">';
                echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'desi-pet-shower' ) . '</a>';
                echo '<span class="dps-action-separator" aria-hidden="true"> | </span>';
                echo '<a href="' . esc_url( $duplicate_url ) . '" title="' . esc_attr__( 'Duplicar agendamento', 'desi-pet-shower' ) . '">' . esc_html__( 'Duplicar', 'desi-pet-shower' ) . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">📅</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum atendimento encontrado', 'desi-pet-shower' ) . '</h4>';
            echo '<p class="dps-empty-state__description">' . esc_html__( 'Este cliente ainda não possui atendimentos registrados.', 'desi-pet-shower' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza scripts JavaScript da página de detalhes do cliente.
     *
     * @since 1.0.0
     */
    private static function render_client_page_scripts() {
        ?>
        <script>
        (function($){
            $(document).on('click', '.dps-send-history-email', function(e){
                e.preventDefault();
                var base = $(this).data('base');
                var email = prompt('<?php echo esc_js( __( 'Para qual email deseja enviar? Deixe em branco para usar o email cadastrado.', 'desi-pet-shower' ) ); ?>');
                if (email === null) {
                    return;
                }
                email = email.trim();
                var url = base;
                if (email) {
                    url += '&to_email=' + encodeURIComponent(email);
                }
                window.location.href = url;
            });

            // Exportar CSV do histórico do cliente
            $(document).on('click', '#dps-client-export-csv', function(e){
                e.preventDefault();
                var $table = $('#dps-client-history-table');
                if (!$table.length) {
                    alert('<?php echo esc_js( __( 'Nenhum atendimento para exportar.', 'desi-pet-shower' ) ); ?>');
                    return;
                }
                var headers = [];
                $table.find('thead th:not(.dps-no-export)').each(function(){
                    headers.push($(this).text().trim());
                });
                var csvLines = [];
                csvLines.push(headers.map(function(text){
                    return '"' + text.replace(/"/g, '""') + '"';
                }).join(';'));
                $table.find('tbody tr').each(function(){
                    var columns = [];
                    $(this).find('td:not(.dps-no-export)').each(function(){
                        var value = $(this).text().replace(/\s+/g, ' ').trim();
                        columns.push('"' + value.replace(/"/g, '""') + '"');
                    });
                    csvLines.push(columns.join(';'));
                });
                var blob = new Blob(['\ufeff' + csvLines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = 'historico-cliente-' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(anchor);
                anchor.click();
                document.body.removeChild(anchor);
                URL.revokeObjectURL(url);
            });

            // Salvar notas internas do cliente
            $(document).on('submit', '.dps-notes-form', function(e){
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('.dps-save-notes-btn');
                var $status = $form.find('.dps-notes-status');
                
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Salvando...', 'desi-pet-shower' ) ); ?>');
                $status.removeClass('dps-notes-status--success dps-notes-status--error').text('');
                
                $.ajax({
                    url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                    method: 'POST',
                    data: {
                        action: 'dps_save_client_notes',
                        client_id: $form.find('input[name="client_id"]').val(),
                        nonce: $form.find('input[name="nonce"]').val(),
                        internal_notes: $form.find('textarea[name="internal_notes"]').val()
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Salvar Notas', 'desi-pet-shower' ) ); ?>');
                        if (response.success) {
                            $status.addClass('dps-notes-status--success').text('<?php echo esc_js( __( '✓ Salvo', 'desi-pet-shower' ) ); ?>');
                            setTimeout(function() { $status.fadeOut(300, function() { $(this).text('').show(); }); }, 3000);
                        } else {
                            $status.addClass('dps-notes-status--error').text(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Erro ao salvar', 'desi-pet-shower' ) ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Salvar Notas', 'desi-pet-shower' ) ); ?>');
                        $status.addClass('dps-notes-status--error').text('<?php echo esc_js( __( 'Erro de conexão', 'desi-pet-shower' ) ); ?>');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Retorna o label traduzido para a espécie do pet.
     *
     * @since 1.0.0
     * @param string $species Código da espécie.
     * @return string Label traduzido.
     */
    private static function get_pet_species_label( $species ) {
        $labels = [
            'cao'   => __( 'Cachorro', 'desi-pet-shower' ),
            'gato'  => __( 'Gato', 'desi-pet-shower' ),
            'outro' => __( 'Outro', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $species ] ) ? $labels[ $species ] : $species;
    }

    /**
     * Retorna o label traduzido para o tamanho do pet.
     *
     * @since 1.0.0
     * @param string $size Código do tamanho.
     * @return string Label traduzido.
     */
    private static function get_pet_size_label( $size ) {
        $labels = [
            'pequeno' => __( 'Pequeno', 'desi-pet-shower' ),
            'medio'   => __( 'Médio', 'desi-pet-shower' ),
            'grande'  => __( 'Grande', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $size ] ) ? $labels[ $size ] : $size;
    }

    /**
     * Retorna o label traduzido para o sexo do pet.
     *
     * @since 1.0.0
     * @param string $sex Código do sexo.
     * @return string Label traduzido.
     */
    private static function get_pet_sex_label( $sex ) {
        $labels = [
            'macho' => __( 'Macho', 'desi-pet-shower' ),
            'femea' => __( 'Fêmea', 'desi-pet-shower' ),
        ];

        return isset( $labels[ $sex ] ) ? $labels[ $sex ] : $sex;
    }

    /**
     * Retorna informações de status do agendamento (label e classe CSS).
     *
     * @since 1.0.0
     * @param string $status Status bruto do agendamento.
     * @return array Array com 'label' e 'class'.
     */
    private static function get_appointment_status_info( $status ) {
        switch ( $status ) {
            case 'finalizado_pago':
            case 'finalizado e pago':
                return [
                    'label' => __( 'Pago', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--paid',
                ];
            case 'finalizado':
                return [
                    'label' => __( 'Finalizado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--pending',
                ];
            case 'cancelado':
                return [
                    'label' => __( 'Cancelado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--cancelled',
                ];
            case 'pendente':
            default:
                return [
                    'label' => __( 'Agendado', 'desi-pet-shower' ),
                    'class' => 'dps-status-badge--scheduled',
                ];
        }
    }

    /**
     * Gera um arquivo HTML contendo o histórico de todos os atendimentos de um cliente.
     * O arquivo é salvo na pasta uploads/dps_docs e retorna a URL pública. Se já existir
     * um documento gerado recentemente (nas últimas 24 horas) ele será reutilizado.
     *
     * @param int $client_id
     * @return string|false URL do arquivo gerado ou false em caso de erro
     */
    private static function generate_client_history_doc( $client_id ) {
        // Busca appointments deste cliente
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'appointment_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => 'appointment_client_id', 'value' => $client_id, 'compare' => '=' ],
            ],
        ] );
        // Caminhos de upload
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        // Gera um nome de arquivo seguindo o padrão Historico_NOMEDOCLIENTE_NOMEDOPET_DATA.html
        $timestamp = current_time( 'timestamp' );
        // Obtém dados do cliente para formar o slug
        $client    = get_post( $client_id );
        $client_name  = $client ? $client->post_title : '';
        $client_slug  = sanitize_title( $client_name );
        $client_slug  = str_replace( '-', '_', $client_slug );
        // Obtém primeiro pet do cliente para incluir no nome, se existir
        $first_pet_slug = 'todos';
        $client_pets = get_posts( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => 'owner_id',
            'meta_value'     => $client_id,
        ] );
        if ( $client_pets ) {
            $first_pet_name = $client_pets[0]->post_title;
            $pet_slug       = sanitize_title( $first_pet_name );
            $pet_slug       = str_replace( '-', '_', $pet_slug );
            $first_pet_slug = $pet_slug;
        }
        $date_str = date_i18n( 'Y-m-d', $timestamp );
        $filename  = 'Historico_' . $client_slug . '_' . $first_pet_slug . '_' . $date_str . '.html';
        $filepath  = trailingslashit( $dir ) . $filename;
        $url       = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . $filename;
        // O nome e o objeto do cliente já foram obtidos anteriormente para o slug.
        $client_email = get_post_meta( $client_id, 'client_email', true );
        // Construir HTML
        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Histórico de Atendimentos</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:14px;line-height:1.4;color:#333;padding:20px;}';
        $html .= '.header{display:flex;align-items:center;margin-bottom:20px;}';
        $html .= '.header img{max-height:80px;margin-right:15px;}';
        $html .= '.header h2{margin:0;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:10px;}';
        $html .= 'th,td{border:1px solid #ccc;padding:8px;text-align:left;}';
        $html .= 'th{background:#f0f0f0;}';
        $html .= '</style></head><body>';
        // Cabeçalho com logo ou nome do site
        $html .= '<div class="header">';
        // Logo do tema se existir
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url_arr = wp_get_attachment_image_src( $logo_id, 'full' );
            if ( $logo_url_arr ) {
                $html .= '<img src="' . esc_url( $logo_url_arr[0] ) . '" alt="Logo">';
            }
        }
        $html .= '<div><h2>Histórico de Atendimentos</h2><p>Cliente: ' . esc_html( $client_name ) . '</p>';
        if ( $client_email ) {
            $html .= '<p>Email: ' . esc_html( $client_email ) . '</p>';
        }
        $html .= '<p>Data de geração: ' . date_i18n( 'd-m-Y H:i', $timestamp ) . '</p></div></div>';
        // Tabela de atendimentos
        $html .= '<table><thead><tr><th>Data</th><th>Horário</th><th>Pet</th><th>Serviços</th><th>Total (R$)</th><th>Status</th></tr></thead><tbody>';
        if ( $appointments ) {
            foreach ( $appointments as $appt ) {
                $date   = get_post_meta( $appt->ID, 'appointment_date', true );
                $time   = get_post_meta( $appt->ID, 'appointment_time', true );
                $pet_id = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                $pet    = $pet_id ? get_post( $pet_id ) : null;
                $services = get_post_meta( $appt->ID, 'appointment_services', true );
                $prices   = get_post_meta( $appt->ID, 'appointment_service_prices', true );
                if ( ! is_array( $prices ) ) {
                    $prices = [];
                }
                // Monta lista de serviços e calcula total
                $service_lines = [];
                $total = 0.0;
                if ( is_array( $services ) ) {
                    foreach ( $services as $idx => $srv_id ) {
                        $srv = get_post( $srv_id );
                        $srv_name  = $srv ? $srv->post_title : '';
                        $price_val = isset( $prices[ $idx ] ) ? floatval( $prices[ $idx ] ) : 0.0;
                        $total    += $price_val;
                        $price_fmt = DPS_Money_Helper::format_decimal_to_brazilian( $price_val );
                        $service_lines[] = $srv_name . ' (R$ ' . $price_fmt . ')';
                    }
                }
                $services_str = $service_lines ? implode( ', ', $service_lines ) : '-';
                $total_fmt    = DPS_Money_Helper::format_decimal_to_brazilian( $total );
                // Status
                $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                $status_label = '';
                if ( $status_meta === 'finalizado_pago' || $status_meta === 'finalizado e pago' ) {
                    $status_label = 'Pago';
                } elseif ( $status_meta === 'finalizado' ) {
                    $status_label = 'Pendente';
                } elseif ( $status_meta === 'cancelado' ) {
                    $status_label = 'Cancelado';
                } else {
                    $status_label = 'Pendente';
                }
                $date_fmt = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                $html .= '<tr><td>' . esc_html( $date_fmt ) . '</td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $pet ? $pet->post_title : '-' ) . '</td><td>' . esc_html( $services_str ) . '</td><td>' . esc_html( $total_fmt ) . '</td><td>' . esc_html( $status_label ) . '</td></tr>';
            }
        } else {
            $html .= '<tr><td colspan="6">Nenhum atendimento encontrado.</td></tr>';
        }
        $html .= '</tbody></table>';
        // Rodapé com dados da loja (informações fixas conforme solicitado)
        $html .= '<p style="margin-top:30px;font-size:12px;">Banho e Tosa Desi Pet Shower – Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $html .= '</body></html>';

        // Valida que o caminho do arquivo está dentro do diretório permitido (uploads/dps_docs)
        // Usa $dir que já foi criado no início da função
        $real_allowed_dir = realpath( $dir );
        $file_dir = dirname( $filepath );
        $real_file_dir = realpath( $file_dir );

        // Se o diretório permitido não existe ou não foi resolvido, há problema na configuração
        if ( false === $real_allowed_dir ) {
            DPS_Logger::error(
                __( 'Diretório de documentos não existe', 'desi-pet-shower' ),
                [
                    'dir'        => $dir,
                    'filepath'   => $filepath,
                    'client_id'  => $client_id,
                ],
                'documents'
            );
            return false;
        }

        // Se o diretório do arquivo não foi resolvido ou não está dentro do diretório permitido
        if ( false === $real_file_dir || 0 !== strpos( $real_file_dir, $real_allowed_dir ) ) {
            DPS_Logger::error(
                __( 'Tentativa de escrita fora do diretório permitido', 'desi-pet-shower' ),
                [
                    'filepath'    => $filepath,
                    'allowed_dir' => $dir,
                ],
                'security'
            );
            return false;
        }

        // Salva arquivo com tratamento de erro
        $written = file_put_contents( $filepath, $html );
        if ( false === $written ) {
            $last_error = error_get_last();
            DPS_Logger::error(
                __( 'Erro ao gerar documento de histórico', 'desi-pet-shower' ),
                [
                    'filepath'   => $filepath,
                    'client_id'  => $client_id,
                    'php_error'  => $last_error ? $last_error['message'] : '',
                ],
                'documents'
            );
            return false;
        }

        return $url;
    }

    /**
     * Envia o histórico de atendimentos de um cliente por email, anexando o arquivo gerado
     * e incluindo um link para visualização.
     *
     * @param int    $client_id
     * @param string $doc_url URL do documento previamente gerado
     * @return void
     */
    private static function send_client_history_email( $client_id, $doc_url, $custom_email = '' ) {
        $client = get_post( $client_id );
        if ( ! $client ) {
            return;
        }
        // Determina email de destino: custom_email se fornecido e válido; caso contrário, email do cliente
        $default_to = get_post_meta( $client_id, 'client_email', true );
        $to = '';
        if ( $custom_email && is_email( $custom_email ) ) {
            $to = $custom_email;
        } elseif ( $default_to && is_email( $default_to ) ) {
            $to = $default_to;
        } else {
            return;
        }
        $name    = $client->post_title;
        $subject = 'Histórico de Atendimentos - ' . get_bloginfo( 'name' );
        // Lê conteúdo do documento para incorporar ao corpo do email
        $uploads  = wp_upload_dir();
        $file_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $doc_url );
        $body_html = '';

        // Valida que o caminho do arquivo está dentro do diretório permitido (uploads/dps_docs)
        $allowed_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $real_allowed_dir = realpath( $allowed_dir );

        // Se o diretório permitido não existe, não há como validar o caminho seguramente
        $is_allowed_path = false;
        if ( false !== $real_allowed_dir && file_exists( $file_path ) ) {
            $real_file_path = realpath( $file_path );
            $is_allowed_path = ( false !== $real_file_path && 0 === strpos( $real_file_path, $real_allowed_dir ) );
        }

        if ( $is_allowed_path ) {
            $content = file_get_contents( $file_path );
            if ( false !== $content ) {
                $body_html = $content;
            } else {
                $last_error = error_get_last();
                DPS_Logger::warning(
                    __( 'Falha ao ler conteúdo do documento de histórico', 'desi-pet-shower' ),
                    [
                        'file_path'  => $file_path,
                        'client_id'  => $client_id,
                        'php_error'  => $last_error ? $last_error['message'] : '',
                    ],
                    'documents'
                );
            }
        } elseif ( file_exists( $file_path ) ) {
            // Arquivo existe mas não está no caminho permitido
            DPS_Logger::error(
                __( 'Tentativa de leitura fora do diretório permitido', 'desi-pet-shower' ),
                [
                    'file_path'   => $file_path,
                    'allowed_dir' => $allowed_dir,
                ],
                'security'
            );
        }

        // Monta corpo com saudação e dados da loja
        $message  = '<p>Olá ' . esc_html( $name ) . ',</p>';
        $message .= '<p>Segue abaixo o histórico de atendimentos do seu pet:</p>';
        if ( $body_html ) {
            $message .= '<div style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">' . $body_html . '</div>';
        } else {
            $message .= '<p><a href="' . esc_url( $doc_url ) . '">Clique aqui para visualizar o histórico</a></p>';
        }
        // Dados da loja conforme solicitado
        $message .= '<p>Atenciosamente,<br>Banho e Tosa Desi Pet Shower<br>Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        // Anexa arquivo HTML (apenas se caminho for permitido)
        // Nota: $is_allowed_path só é true se file_exists() for verdadeiro
        $attachments = [];
        if ( $is_allowed_path ) {
            $attachments[] = $file_path;
        }
        $mail_sent = wp_mail( $to, $subject, $message, $headers, $attachments );
        if ( ! $mail_sent ) {
            DPS_Logger::warning(
                __( 'Falha ao enviar email com histórico do cliente', 'desi-pet-shower' ),
                [
                    'to'        => $to,
                    'client_id' => $client_id,
                ],
                'email'
            );
        }
    }

    /**
     * Exclui um documento (arquivo .html) da pasta dps_docs. Também remove quaisquer
     * opções que referenciem este arquivo (documentos financeiros ou históricos).
     *
     * @param string $filename Nome do arquivo a ser removido
     */
    private static function delete_document( $filename ) {
        if ( ! $filename ) {
            return;
        }
        $uploads = wp_upload_dir();
        $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $file_path = $doc_dir . '/' . basename( $filename );
        if ( file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
        }
        // Remover opções que apontam para este arquivo
        // Financeiro armazena URL em dps_fin_doc_{id} e base armazena nada específico, então busca geral
        // Verifica se alguma opção coincide com a URL
        $file_url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $filename );
        global $wpdb;
        // F1.1: FASE 1 - Segurança: Usar $wpdb->prepare() com esc_like() para padrão LIKE
        $like_pattern = $wpdb->esc_like( 'dps_fin_doc_' ) . '%';
        $options = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_pattern
        ) );
        if ( $options ) {
            foreach ( $options as $opt ) {
                $opt_val = get_option( $opt->option_name );
                if ( $opt_val === $file_url ) {
                    delete_option( $opt->option_name );
                }
            }
        }
    }
}
