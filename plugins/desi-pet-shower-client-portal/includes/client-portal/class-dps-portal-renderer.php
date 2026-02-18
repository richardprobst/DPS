<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Responsável por renderizar componentes do portal do cliente.
 * 
 * Esta classe contém todos os métodos de renderização HTML/UI do portal,
 * incluindo widgets, cards, tabelas, formulários e seções de conteúdo.
 * 
 * @since 3.0.0
 */
class DPS_Portal_Renderer {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Renderer|null
     */
    private static $instance = null;

    /**
     * Provedor de dados para o portal.
     *
     * @var DPS_Portal_Data_Provider
     */
    private $data_provider;

    /**
     * Repositório de agendamentos.
     *
     * @var DPS_Appointment_Repository
     */
    private $appointment_repository;

    /**
     * Repositório de finanças.
     *
     * @var DPS_Finance_Repository
     */
    private $finance_repository;

    /**
     * Repositório de pets.
     *
     * @var DPS_Pet_Repository
     */
    private $pet_repository;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_Renderer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        $this->data_provider          = DPS_Portal_Data_Provider::get_instance();
        $this->appointment_repository = DPS_Appointment_Repository::get_instance();
        $this->finance_repository     = DPS_Finance_Repository::get_instance();
        $this->pet_repository         = DPS_Pet_Repository::get_instance();
    }

    /**
     * Renderiza o widget de chat flutuante.
     *
     * @since 2.3.0
     * @param int $client_id ID do cliente autenticado.
     */
    public function render_chat_widget( $client_id ) {
        // Conta mensagens não lidas
        $unread_count = $this->data_provider->get_unread_messages_count( $client_id );
        
        echo '<div class="dps-chat-widget" data-client-id="' . esc_attr( $client_id ) . '">';
        
        // Botão toggle
        echo '<button class="dps-chat-toggle" aria-label="' . esc_attr__( 'Abrir chat', 'dps-client-portal' ) . '">';
        echo '<span class="dps-chat-toggle__icon">💬</span>';
        if ( $unread_count > 0 ) {
            echo '<span class="dps-chat-badge">' . esc_html( $unread_count > 99 ? '99+' : $unread_count ) . '</span>';
        } else {
            echo '<span class="dps-chat-badge"></span>';
        }
        echo '</button>';
        
        // Janela do chat
        echo '<div class="dps-chat-window" aria-hidden="true">';
        
        // Header
        echo '<div class="dps-chat-header">';
        echo '<div class="dps-chat-header__info">';
        echo '<div class="dps-chat-header__avatar">🐾</div>';
        echo '<div>';
        echo '<h4 class="dps-chat-header__title">' . esc_html__( 'Chat DPS', 'dps-client-portal' ) . '</h4>';
        echo '<div class="dps-chat-header__status">' . esc_html__( 'Online', 'dps-client-portal' ) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<button class="dps-chat-header__close" aria-label="' . esc_attr__( 'Fechar chat', 'dps-client-portal' ) . '">✕</button>';
        echo '</div>';
        
        // Área de mensagens
        echo '<div class="dps-chat-messages">';
        echo '<div class="dps-chat-loading"><div class="dps-chat-loading__spinner"></div></div>';
        echo '</div>';
        
        // Input de mensagem
        echo '<div class="dps-chat-input">';
        echo '<form class="dps-chat-input__form">';
        echo '<input type="text" class="dps-chat-input__field" placeholder="' . esc_attr__( 'Digite sua mensagem...', 'dps-client-portal' ) . '" maxlength="1000">';
        echo '<button type="submit" class="dps-chat-input__send" aria-label="' . esc_attr__( 'Enviar', 'dps-client-portal' ) . '">📤</button>';
        echo '</form>';
        echo '</div>';
        
        echo '</div>'; // .dps-chat-window
        echo '</div>'; // .dps-chat-widget
    }

    /**
     * Renderiza seção dos próximos agendamentos.
     * Mostra todos os agendamentos futuros de todos os pets do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_next_appointment( $client_id ) {
        echo '<section id="proximos" class="dps-portal-section dps-portal-next">';
        echo '<h2>' . esc_html__( '📅 Próximos Agendamentos', 'dps-client-portal' ) . '</h2>';
        
        // Busca todos os agendamentos futuros do cliente (todos os pets)
        $upcoming = $this->appointment_repository->get_future_appointments_for_client( $client_id );
        
        if ( ! empty( $upcoming ) ) {
            echo '<div class="dps-appointments-list">';
            foreach ( $upcoming as $appointment ) {
                $this->render_next_appointment_card( $appointment, $client_id );
            }
            echo '</div>';
        } else {
            $this->render_no_appointments_state();
        }
        echo '</section>';
    }

    /**
     * Renderiza o card do próximo agendamento.
     *
     * @param WP_Post $appointment Objeto do agendamento.
     * @param int     $client_id   ID do cliente.
     */
    private function render_next_appointment_card( $appointment, $client_id ) {
        $pet_id    = get_post_meta( $appointment->ID, 'appointment_pet_id', true );
        $pet_name  = $pet_id ? get_the_title( $pet_id ) : '';
        $services  = get_post_meta( $appointment->ID, 'appointment_services', true );
        $services  = is_array( $services ) ? implode( ', ', array_map( 'esc_html', $services ) ) : '';
        $date      = get_post_meta( $appointment->ID, 'appointment_date', true );
        $time      = get_post_meta( $appointment->ID, 'appointment_time', true );
        $status    = get_post_meta( $appointment->ID, 'appointment_status', true );
        
        // Card de destaque para próximo agendamento
        echo '<div class="dps-appointment-card">';
        echo '<div class="dps-appointment-card__body">';
        echo '<div class="dps-appointment-card__date">';
        echo '<span class="dps-appointment-card__day">' . esc_html( date_i18n( 'd', strtotime( $date ) ) ) . '</span>';
        echo '<span class="dps-appointment-card__month">' . esc_html( date_i18n( 'M', strtotime( $date ) ) ) . '</span>';
        echo '</div>';
        echo '<div class="dps-appointment-card__details">';
        echo '<div class="dps-appointment-card__time">⏰ ' . esc_html( $time ) . '</div>';
        if ( $pet_name ) {
            echo '<div class="dps-appointment-card__pet">🐾 ' . esc_html( $pet_name ) . '</div>';
        }
        if ( $services ) {
            echo '<div class="dps-appointment-card__services">✂️ ' . $services . '</div>';
        }
        if ( $status ) {
            echo '<div class="dps-appointment-card__status">' . esc_html( ucfirst( $status ) ) . '</div>';
        }
        echo '</div>'; // .dps-appointment-card__details
        echo '</div>'; // .dps-appointment-card__body
        
        // Rodapé do card com ações centralizadas
        echo '<div class="dps-appointment-card__footer">';
        // Link para mapa
        $address = get_post_meta( $client_id, 'client_address', true );
        if ( $address ) {
            $query = urlencode( $address );
            $url   = 'https://www.google.com/maps/search/?api=1&query=' . $query;
            echo '<a href="' . esc_url( $url ) . '" target="_blank" class="dps-appointment-card__footer-btn">📍 ' . esc_html__( 'Mapa', 'dps-client-portal' ) . '</a>';
        }
        echo '<button type="button" class="dps-appointment-card__footer-btn dps-btn-reschedule" data-appointment-id="' . esc_attr( $appointment->ID ) . '">📅 ' . esc_html__( 'Reagendar', 'dps-client-portal' ) . '</button>';
        echo '<button type="button" class="dps-appointment-card__footer-btn dps-btn-cancel" data-appointment-id="' . esc_attr( $appointment->ID ) . '">❌ ' . esc_html__( 'Cancelar', 'dps-client-portal' ) . '</button>';
        echo '</div>'; // .dps-appointment-card__footer
        echo '</div>'; // .dps-appointment-card
    }

    /**
     * Renderiza estado vazio quando não há agendamentos.
     */
    private function render_no_appointments_state() {
        echo '<div class="dps-empty-state">';
        echo '<div class="dps-empty-state__icon">📅</div>';
        echo '<div class="dps-empty-state__message">' . esc_html__( 'Você não tem agendamentos futuros.', 'dps-client-portal' ) . '</div>';
        // Gera link para agendar via WhatsApp usando helper centralizado
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_message = __( 'Olá! Gostaria de agendar um serviço.', 'dps-client-portal' );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
        } else {
            // Fallback
            $whatsapp_number = get_option( 'dps_whatsapp_number', '' );
            if ( ! $whatsapp_number ) {
                echo '</div>';
                return;
            }
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            $whatsapp_text = urlencode( 'Olá! Gostaria de agendar um serviço.' );
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_text;
        }
        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action button button-primary">💬 ' . esc_html__( 'Agendar via WhatsApp', 'dps-client-portal' ) . '</a>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de pendências financeiras do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_financial_pending( $client_id ) {
        // Usa repositório para buscar pendências
        $pendings = $this->finance_repository->get_pending_transactions_for_client( $client_id );
        
        $has_pendings = ! empty( $pendings );
        // Inicia colapsado quando há pendências (requisito: seção começa fechada)
        $collapsed_class = $has_pendings ? ' is-collapsed' : '';
        
        echo '<section id="pendencias" class="dps-portal-section dps-portal-finances dps-collapsible' . esc_attr( $collapsed_class ) . '">';
        echo '<button type="button" class="dps-collapsible__header" aria-expanded="' . ( $has_pendings ? 'false' : 'true' ) . '">';
        echo '<h2>' . esc_html__( '💳 Pagamentos Pendentes', 'dps-client-portal' ) . '</h2>';
        echo '<svg class="dps-collapsible__icon" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>';
        echo '</button>';
        
        echo '<div class="dps-collapsible__content">';
        if ( $has_pendings ) {
            $this->render_financial_pending_list( $pendings );
        } else {
            $this->render_financial_clear_state();
        }
        echo '</div>';
        echo '</section>';
    }

    /**
     * Renderiza lista de pendências financeiras.
     *
     * @param array $pendings Array de objetos de transação.
     */
    private function render_financial_pending_list( $pendings ) {
        // Calcula total de pendências
        $total = 0;
        $trans_ids = [];
        foreach ( $pendings as $trans ) {
            $total += (float) $trans->valor;
            $trans_ids[] = $trans->id;
        }
        
        // Card de resumo de pendências com destaque
        echo '<div class="dps-financial-summary">';
        echo '<div class="dps-financial-summary__icon">⚠️</div>';
        echo '<div class="dps-financial-summary__content">';
        echo '<div class="dps-financial-summary__title">' . esc_html( sprintf( 
            _n( '%d Pendência', '%d Pendências', count( $pendings ), 'dps-client-portal' ),
            count( $pendings )
        ) ) . '</div>';
        echo '<div class="dps-financial-summary__amount">' . esc_html( DPS_Money_Helper::format_currency_from_decimal( $total ) ) . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Tabela de detalhes
        echo '<div id="financial-details" class="dps-financial-details">';
        echo '<table class="dps-table"><thead><tr>';
        echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Descrição', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Valor', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Ação', 'dps-client-portal' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $pendings as $trans ) {
            $this->render_financial_pending_row( $trans );
        }
        echo '</tbody></table>';
        
        // Botão "Pagar Tudo" quando há mais de uma pendência
        if ( count( $pendings ) > 1 ) {
            echo '<div class="dps-financial-pay-all">';
            echo '<form method="post">';
            wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
            echo '<input type="hidden" name="dps_client_portal_action" value="pay_all_transactions">';
            foreach ( $trans_ids as $tid ) {
                echo '<input type="hidden" name="trans_ids[]" value="' . esc_attr( $tid ) . '">';
            }
            echo '<div class="dps-financial-pay-all__summary">';
            echo '<span class="dps-financial-pay-all__label">' . esc_html__( 'Total de todas as pendências:', 'dps-client-portal' ) . '</span>';
            echo '<span class="dps-financial-pay-all__amount">' . esc_html( DPS_Money_Helper::format_currency_from_decimal( $total ) ) . '</span>';
            echo '</div>';
            echo '<button type="submit" class="dps-btn-pay-all">' . esc_html__( 'Pagar Tudo', 'dps-client-portal' ) . '</button>';
            echo '</form>';
            echo '</div>';
        }
        
        echo '</div>'; // .dps-financial-details
    }

    /**
     * Renderiza uma linha de pendência financeira.
     *
     * @param object $transaction Objeto da transação.
     */
    private function render_financial_pending_row( $transaction ) {
        $date = $transaction->data;
        $desc = $transaction->descricao ? $transaction->descricao : __( 'Serviço', 'dps-client-portal' );
        $valor = DPS_Money_Helper::format_decimal_to_brazilian( (float) $transaction->valor );
        echo '<tr>';
        echo '<td data-label="' . esc_attr__( 'Data', 'dps-client-portal' ) . '">' . esc_html( date_i18n( 'd-m-Y', strtotime( $date ) ) ) . '</td>';
        echo '<td data-label="' . esc_attr__( 'Descrição', 'dps-client-portal' ) . '">' . esc_html( $desc ) . '</td>';
        echo '<td data-label="' . esc_attr__( 'Valor', 'dps-client-portal' ) . '">R$ ' . esc_html( $valor ) . '</td>';
        // Gera link de pagamento via formulário
        echo '<td data-label="' . esc_attr__( 'Ação', 'dps-client-portal' ) . '">';
        echo '<form method="post" style="display:inline;">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="pay_transaction">';
        echo '<input type="hidden" name="trans_id" value="' . esc_attr( $transaction->id ) . '">';
        echo '<button type="submit" class="button button-secondary dps-btn-pay">' . esc_html__( 'Pagar Agora', 'dps-client-portal' ) . '</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Renderiza estado "em dia" sem pendências.
     */
    private function render_financial_clear_state() {
        echo '<div class="dps-financial-summary dps-financial-summary--positive">';
        echo '<div class="dps-financial-summary__icon">😊</div>';
        echo '<div class="dps-financial-summary__content">';
        echo '<div class="dps-financial-summary__title">' . esc_html__( 'Tudo em Dia!', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-financial-summary__message">' . esc_html__( 'Você não tem pagamentos pendentes', 'dps-client-portal' ) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza sugestões contextuais baseadas no histórico do cliente.
     * Fase 2: Personalização da experiência
     *
     * @param int $client_id ID do cliente.
     * @since 2.4.0
     */
    public function render_contextual_suggestions( $client_id ) {
        $suggestions = $this->data_provider->get_scheduling_suggestions( $client_id );
        
        // Renderiza sugestões se houver
        if ( ! empty( $suggestions ) ) {
            echo '<section class="dps-portal-section dps-portal-suggestions">';
            echo '<h2>💡 ' . esc_html__( 'Sugestões para Você', 'dps-client-portal' ) . '</h2>';
            
            foreach ( $suggestions as $suggestion ) {
                $this->render_suggestion_card( $suggestion );
            }
            
            echo '</section>';
        }
    }

    /**
     * Renderiza um card de sugestão.
     *
     * @param array $suggestion Dados da sugestão.
     */
    private function render_suggestion_card( $suggestion ) {
        echo '<div class="dps-suggestion-card">';
        echo '<div class="dps-suggestion-card__icon">🐾</div>';
        echo '<div class="dps-suggestion-card__content">';
        echo '<p class="dps-suggestion-card__message">';
        echo esc_html( sprintf(
            _n( 
                'Já faz %d dia desde o último %s do %s.',
                'Já faz %d dias desde o último %s do %s.',
                $suggestion['days_since'],
                'dps-client-portal'
            ),
            $suggestion['days_since'],
            $suggestion['service_name'],
            $suggestion['pet_name']
        ) );
        echo '</p>';
        echo '<p class="dps-suggestion-card__cta">';
        
        // Link para agendar via WhatsApp
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $message = sprintf( __( 'Olá! Gostaria de agendar %s para o %s.', 'dps-client-portal' ), $suggestion['service_name'], $suggestion['pet_name'] );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $message );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            $message_text = urlencode( sprintf( 'Olá! Gostaria de agendar %s para o %s.', $suggestion['service_name'], $suggestion['pet_name'] ) );
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $message_text;
        }
        
        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-suggestion-card__button">';
        echo '📅 ' . esc_html__( 'Agendar Agora', 'dps-client-portal' );
        echo '</a>';
        echo '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderiza a seção de histórico de agendamentos do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_appointment_history( $client_id ) {
        // Usa repositório para buscar histórico
        $appointments = $this->appointment_repository->get_past_appointments_for_client( $client_id );
        
        echo '<section class="dps-portal-section dps-portal-history">';
        echo '<h2>' . esc_html__( '📋 Histórico de Agendamentos', 'dps-client-portal' ) . '</h2>';
        
        if ( $appointments ) {
            $this->render_appointments_table( $appointments );
        } else {
            $this->render_no_history_state();
        }
        
        echo '</section>';
    }

    /**
     * Renderiza tabela de agendamentos.
     *
     * @param array $appointments Array de posts de agendamento.
     */
    private function render_appointments_table( $appointments ) {
        echo '<div class="dps-appointments-table">';
        echo '<table class="dps-table"><thead><tr>';
        echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Pet', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Serviços', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Detalhes', 'dps-client-portal' ) . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ( $appointments as $appt ) {
            $this->render_appointment_row( $appt );
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Renderiza uma linha de agendamento.
     *
     * @param WP_Post $appointment Post de agendamento.
     */
    private function render_appointment_row( $appointment ) {
        $pet_id   = get_post_meta( $appointment->ID, 'appointment_pet_id', true );
        $pet_name = $pet_id ? get_the_title( $pet_id ) : '-';
        $services = get_post_meta( $appointment->ID, 'appointment_services', true );
        $services_text = is_array( $services ) && ! empty( $services ) 
            ? implode( ', ', array_map( 'esc_html', $services ) ) 
            : '-';
        $date     = get_post_meta( $appointment->ID, 'appointment_date', true );
        $time     = get_post_meta( $appointment->ID, 'appointment_time', true );
        $status   = get_post_meta( $appointment->ID, 'appointment_status', true );
        
        echo '<tr>';
        echo '<td data-label="' . esc_attr__( 'Data', 'dps-client-portal' ) . '">';
        echo esc_html( $date ? date_i18n( 'd/m/Y', strtotime( $date ) ) . ' ' . $time : '-' );
        echo '</td>';
        echo '<td data-label="' . esc_attr__( 'Pet', 'dps-client-portal' ) . '">' . esc_html( $pet_name ) . '</td>';
        echo '<td data-label="' . esc_attr__( 'Serviços', 'dps-client-portal' ) . '">' . $services_text . '</td>';
        echo '<td data-label="' . esc_attr__( 'Status', 'dps-client-portal' ) . '">' . esc_html( ucfirst( $status ) ) . '</td>';
        // Coluna Detalhes (Check-in/Check-out e itens de segurança, sem dados internos)
        echo '<td data-label="' . esc_attr__( 'Detalhes', 'dps-client-portal' ) . '">';
        if ( class_exists( 'DPS_Agenda_Addon' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
            echo DPS_Agenda_Addon::render_checkin_checklist_summary( $appointment->ID, true );
        }
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Renderiza estado vazio para histórico.
     */
    private function render_no_history_state() {
        echo '<div class="dps-empty-state">';
        echo '<div class="dps-empty-state__icon">📋</div>';
        echo '<div class="dps-empty-state__message">' . esc_html__( 'Você ainda não tem histórico de agendamentos.', 'dps-client-portal' ) . '</div>';
        echo '</div>';
    }

    /**
     * Renderiza galeria de fotos dos pets.
     * Revisão de layout: Fevereiro 2026
     *
     * @since 2.5.0
     * @param int $client_id ID do cliente.
     */
    public function render_pet_gallery( $client_id ) {
        $pets = $this->pet_repository->get_pets_by_client( $client_id );

        echo '<section class="dps-portal-section dps-portal-gallery">';

        // Header
        echo '<div class="dps-gallery-header">';
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">📸</span>';
        echo esc_html__( 'Galeria de Fotos', 'dps-client-portal' );
        echo '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Fotos dos seus pets cadastrados no nosso sistema.', 'dps-client-portal' ) . '</p>';
        echo '</div>';

        if ( empty( $pets ) ) {
            $this->render_gallery_empty_state();
            echo '</section>';
            return;
        }

        // Conta fotos disponíveis
        $total_photos = 0;
        foreach ( $pets as $pet ) {
            $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
            if ( $photo_id && wp_get_attachment_image_url( $photo_id, 'thumbnail' ) ) {
                $total_photos++;
            }
        }

        // Métricas
        echo '<div class="dps-gallery-metrics">';
        echo '<div class="dps-gallery-metric-card">';
        echo '<div class="dps-gallery-metric-card__icon">🐾</div>';
        echo '<div class="dps-gallery-metric-card__content">';
        echo '<span class="dps-gallery-metric-card__value">' . esc_html( count( $pets ) ) . '</span>';
        echo '<span class="dps-gallery-metric-card__label">' . esc_html( _n( 'Pet', 'Pets', count( $pets ), 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-gallery-metric-card' . ( $total_photos > 0 ? ' dps-gallery-metric-card--highlight' : '' ) . '">';
        echo '<div class="dps-gallery-metric-card__icon">📷</div>';
        echo '<div class="dps-gallery-metric-card__content">';
        echo '<span class="dps-gallery-metric-card__value">' . esc_html( $total_photos ) . '</span>';
        echo '<span class="dps-gallery-metric-card__label">' . esc_html( _n( 'Foto', 'Fotos', $total_photos, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // .dps-gallery-metrics

        // Filtro por pet (quando múltiplos pets)
        if ( count( $pets ) > 1 ) {
            echo '<div class="dps-gallery-filter" role="toolbar" aria-label="' . esc_attr__( 'Filtrar por pet', 'dps-client-portal' ) . '">';
            echo '<span class="dps-gallery-filter__label">' . esc_html__( 'Filtrar por:', 'dps-client-portal' ) . '</span>';
            echo '<div class="dps-gallery-filter__buttons">';
            echo '<button type="button" class="dps-gallery-filter__btn is-active" data-filter="all">';
            echo esc_html__( 'Todos', 'dps-client-portal' );
            echo '</button>';
            foreach ( $pets as $pet ) {
                echo '<button type="button" class="dps-gallery-filter__btn" data-filter="pet-' . esc_attr( $pet->ID ) . '">';
                echo esc_html( get_the_title( $pet->ID ) );
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';
        }

        // Conteúdo: cards por pet
        echo '<div class="dps-gallery-content">';
        foreach ( $pets as $pet ) {
            $this->render_gallery_pet_card( $pet );
        }
        echo '</div>';

        // Nota informativa
        echo '<div class="dps-gallery-info">';
        echo '<p class="dps-gallery-info__text">';
        echo '<span class="dps-gallery-info__icon">💡</span>';
        echo esc_html__( 'As fotos são atualizadas pela equipe durante os atendimentos. Para solicitar uma atualização, entre em contato.', 'dps-client-portal' );
        echo '</p>';
        echo '</div>';

        echo '</section>';
    }

    /**
     * Renderiza card de galeria para um pet.
     *
     * @since 2.5.0
     * @param WP_Post $pet Post do pet.
     */
    private function render_gallery_pet_card( $pet ) {
        $pet_id   = $pet->ID;
        $pet_name = get_the_title( $pet_id );
        $photo_id = get_post_meta( $pet_id, 'pet_photo_id', true );
        $species  = get_post_meta( $pet_id, 'pet_species', true );

        $species_icon = '🐾';
        if ( $species ) {
            $species_lower = strtolower( $species );
            if ( str_contains( $species_lower, 'gato' ) || str_contains( $species_lower, 'cat' ) || str_contains( $species_lower, 'felin' ) ) {
                $species_icon = '🐱';
            } elseif ( str_contains( $species_lower, 'cão' ) || str_contains( $species_lower, 'cachorro' ) || str_contains( $species_lower, 'dog' ) || str_contains( $species_lower, 'canin' ) ) {
                $species_icon = '🐶';
            }
        }

        echo '<div class="dps-gallery-pet-card" data-pet-id="pet-' . esc_attr( $pet_id ) . '">';

        // Header
        echo '<div class="dps-gallery-pet-card__header">';
        echo '<h3 class="dps-gallery-pet-card__name">';
        echo '<span class="dps-gallery-pet-card__icon">' . $species_icon . '</span>';
        echo esc_html( $pet_name );
        echo '</h3>';
        echo '</div>';

        // Content
        echo '<div class="dps-gallery-pet-card__content">';

        if ( $photo_id ) {
            $photo_url_medium = wp_get_attachment_image_url( $photo_id, 'medium' );
            $photo_url_full   = wp_get_attachment_image_url( $photo_id, 'full' );

            if ( $photo_url_medium && $photo_url_full ) {
                echo '<div class="dps-gallery-photo-grid">';
                echo '<div class="dps-gallery-photo dps-gallery-photo--profile">';
                echo '<a href="' . esc_url( $photo_url_full ) . '" class="dps-gallery-photo__link" title="' . esc_attr( $pet_name ) . '">';
                echo '<img src="' . esc_url( $photo_url_medium ) . '" alt="' . esc_attr( sprintf( __( 'Foto de %s', 'dps-client-portal' ), $pet_name ) ) . '" class="dps-gallery-photo__img" loading="lazy" />';
                echo '<div class="dps-gallery-photo__overlay"><span class="dps-gallery-photo__zoom">🔍</span></div>';
                echo '</a>';
                echo '<div class="dps-gallery-photo__info">';
                echo '<span class="dps-gallery-photo__label">' . esc_html__( 'Foto de perfil', 'dps-client-portal' ) . '</span>';
                echo '<div class="dps-gallery-photo__actions">';
                echo '<a href="' . esc_url( $photo_url_full ) . '" class="dps-gallery-photo__action dps-gallery-photo__action--download" download title="' . esc_attr__( 'Baixar foto', 'dps-client-portal' ) . '">';
                echo '<span class="dps-gallery-photo__action-icon">⬇️</span>';
                echo '</a>';
                echo '</div>';
                echo '</div>'; // .dps-gallery-photo__info
                echo '</div>'; // .dps-gallery-photo
                echo '</div>'; // .dps-gallery-photo-grid
            } else {
                $this->render_gallery_pet_empty( $pet_name );
            }
        } else {
            $this->render_gallery_pet_empty( $pet_name );
        }

        echo '</div>'; // .dps-gallery-pet-card__content
        echo '</div>'; // .dps-gallery-pet-card
    }

    /**
     * Renderiza estado vazio para pet sem fotos.
     *
     * @since 2.5.0
     * @param string $pet_name Nome do pet.
     */
    private function render_gallery_pet_empty( $pet_name ) {
        echo '<div class="dps-gallery-pet-card__empty">';
        echo '<div class="dps-gallery-pet-card__empty-icon">📷</div>';
        echo '<p class="dps-gallery-pet-card__empty-text">';
        /* translators: %s: pet name */
        echo esc_html( sprintf( __( '%s ainda não tem fotos cadastradas.', 'dps-client-portal' ), $pet_name ) );
        echo '</p>';
        echo '<p class="dps-gallery-pet-card__empty-hint">' . esc_html__( 'As fotos são adicionadas durante os atendimentos.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
    }

    /**
     * Renderiza estado vazio geral da galeria (sem pets).
     *
     * @since 2.5.0
     */
    private function render_gallery_empty_state() {
        echo '<div class="dps-gallery-empty-state">';
        echo '<div class="dps-gallery-empty-state__icon">📸</div>';
        echo '<h3 class="dps-gallery-empty-state__title">' . esc_html__( 'Nenhum pet cadastrado', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-gallery-empty-state__message">' . esc_html__( 'Cadastre seus pets para visualizar a galeria de fotos.', 'dps-client-portal' ) . '</p>';
        echo '<p class="dps-gallery-empty-state__hint">' . esc_html__( 'Entre em contato com a equipe para cadastrar seu pet.', 'dps-client-portal' ) . '</p>';

        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( __( 'Olá! Gostaria de cadastrar meu pet.', 'dps-client-portal' ) );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode( 'Olá! Gostaria de cadastrar meu pet.' );
        }

        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-gallery-empty-state__action">';
        echo '💬 ' . esc_html__( 'Falar com a Equipe', 'dps-client-portal' );
        echo '</a>';
        echo '</div>';
    }

    /**
     * Renderiza centro de mensagens.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_message_center( $client_id ) {
        $unread_count = $this->data_provider->get_unread_messages_count( $client_id );
        $messages     = $this->message_repository->get_messages_by_client( $client_id );
        $total_count  = count( $messages );

        echo '<section class="dps-portal-section dps-portal-messages">';
        echo '<h2 class="dps-section-title"><span class="dps-section-title__icon">💬</span>' . esc_html__( 'Central de Mensagens', 'dps-client-portal' ) . '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Converse com nossa equipe pelo chat flutuante no canto inferior direito.', 'dps-client-portal' ) . '</p>';

        // Métricas
        echo '<div class="dps-messages-metrics">';

        echo '<div class="dps-messages-metric-card' . ( $unread_count > 0 ? ' dps-messages-metric-card--highlight' : '' ) . '">';
        echo '<div class="dps-messages-metric-card__icon">📩</div>';
        echo '<div class="dps-messages-metric-card__content">';
        echo '<span class="dps-messages-metric-card__value">' . esc_html( $unread_count ) . '</span>';
        echo '<span class="dps-messages-metric-card__label">' . esc_html( _n( 'Não lida', 'Não lidas', $unread_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="dps-messages-metric-card">';
        echo '<div class="dps-messages-metric-card__icon">📝</div>';
        echo '<div class="dps-messages-metric-card__content">';
        echo '<span class="dps-messages-metric-card__value">' . esc_html( $total_count ) . '</span>';
        echo '<span class="dps-messages-metric-card__label">' . esc_html( _n( 'Mensagem', 'Mensagens', $total_count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .dps-messages-metrics

        // Caixa de entrada
        echo '<div class="dps-messages-inbox">';
        echo '<div class="dps-messages-inbox__header">';
        echo '<span class="dps-messages-inbox__icon">📬</span>';
        echo '<div>';
        echo '<h3 class="dps-messages-inbox__title">' . esc_html__( 'Conversas Recentes', 'dps-client-portal' ) . '</h3>';
        echo '<p class="dps-messages-inbox__subtitle">' . esc_html__( 'Histórico das suas últimas mensagens', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';

        if ( ! empty( $messages ) ) {
            echo '<div class="dps-portal-messages__list">';
            $display_messages = array_slice( array_reverse( $messages ), 0, 10 );
            foreach ( $display_messages as $msg ) {
                $sender     = get_post_meta( $msg->ID, 'message_sender', true );
                $is_admin   = ( 'admin' === $sender );
                $read_at    = get_post_meta( $msg->ID, 'client_read_at', true );
                $is_unread  = $is_admin && empty( $read_at );
                $sender_lbl = $is_admin
                    ? esc_html__( 'Equipe', 'dps-client-portal' )
                    : esc_html__( 'Você', 'dps-client-portal' );

                echo '<div class="dps-portal-message' . ( $is_unread ? ' dps-portal-message--unread' : '' ) . '">';
                echo '<div class="dps-portal-message__avatar">' . ( $is_admin ? '🐾' : '👤' ) . '</div>';
                echo '<div class="dps-portal-message__body">';
                echo '<div class="dps-portal-message__header">';
                echo '<span class="dps-portal-message__sender">' . esc_html( $sender_lbl ) . '</span>';
                echo '<time class="dps-portal-message__time">' . esc_html( get_the_date( get_option( 'date_format', 'd/m/Y' ) . ' H:i', $msg ) ) . '</time>';
                echo '</div>';
                echo '<p class="dps-portal-message__text">' . esc_html( wp_trim_words( $msg->post_content, 20, '…' ) ) . '</p>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>'; // .dps-portal-messages__list
        } else {
            echo '<div class="dps-empty-state dps-empty-state--compact">';
            echo '<div class="dps-empty-state__icon">💬</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Nenhuma mensagem ainda', 'dps-client-portal' ) . '</div>';
            echo '<p class="dps-empty-state__hint">' . esc_html__( 'Use o chat flutuante no canto inferior direito para iniciar uma conversa.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .dps-messages-inbox

        // CTA para abrir o chat
        echo '<div class="dps-messages-cta">';
        echo '<button type="button" class="dps-btn-open-chat" data-action="open-chat">';
        echo '<span class="dps-btn-icon">💬</span>';
        echo '<span class="dps-btn-text">' . esc_html__( 'Abrir Chat', 'dps-client-portal' ) . '</span>';
        echo '</button>';
        echo '</div>';

        echo '</section>';
    }

    /**
     * Renderiza resumo de indicações (Loyalty Add-on).
     *
     * @param int $client_id ID do cliente.
     */
    public function render_referrals_summary( $client_id ) {
        if ( ! function_exists( 'dps_loyalty_get_referral_code' ) ) {
            return;
        }
        
        $code = dps_loyalty_get_referral_code( $client_id );
        $count = function_exists( 'dps_loyalty_count_referrals' ) ? dps_loyalty_count_referrals( $client_id ) : 0;
        
        echo '<section class="dps-portal-section dps-portal-referrals">';
        echo '<h2>' . esc_html__( '🎁 Indique e Ganhe', 'dps-client-portal' ) . '</h2>';
        
        echo '<div class="dps-referral-card">';
        echo '<div class="dps-referral-card__header">';
        echo '<div class="dps-referral-card__icon">🔗</div>';
        echo '<div>';
        echo '<h3 class="dps-referral-card__title">' . esc_html__( 'Seu Código de Indicação', 'dps-client-portal' ) . '</h3>';
        echo '<div class="dps-referral-card__code">' . esc_html( $code ) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-referral-card__stats">';
        echo '<div class="dps-referral-card__stat">';
        echo '<span class="dps-referral-card__stat-value">' . esc_html( $count ) . '</span>';
        echo '<span class="dps-referral-card__stat-label">' . esc_html( _n( 'Indicação', 'Indicações', $count, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</section>';
    }

    /**
     * Renderiza formulários de atualização de dados.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_update_forms( $client_id ) {
        echo '<section class="dps-portal-section dps-portal-forms dps-meus-dados">';
        
        // Header moderno com título e subtítulo
        echo '<div class="dps-meus-dados-header">';
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">⚙️</span>';
        echo esc_html__( 'Meus Dados', 'dps-client-portal' );
        echo '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Mantenha suas informações de contato e dados dos seus pets sempre atualizados para um atendimento mais personalizado.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        
        // Cards empilhados
        echo '<div class="dps-meus-dados-stacked">';
        
        $this->render_client_info_form( $client_id );
        $this->render_pets_forms( $client_id );
        
        echo '</div>'; // .dps-meus-dados-stacked
        
        echo '</section>';
    }

    /**
     * Renderiza formulário de informações do cliente.
     * Revisão de layout: Janeiro 2026
     *
     * @param int $client_id ID do cliente.
     */
    private function render_client_info_form( $client_id ) {
        $phone   = get_post_meta( $client_id, 'client_phone', true );
        $email   = get_post_meta( $client_id, 'client_email', true );
        $address = get_post_meta( $client_id, 'client_address', true );
        $city    = get_post_meta( $client_id, 'client_city', true );
        $state   = get_post_meta( $client_id, 'client_state', true );
        $zip     = get_post_meta( $client_id, 'client_zip', true );
        $insta   = get_post_meta( $client_id, 'client_instagram', true );
        $fb      = get_post_meta( $client_id, 'client_facebook', true );
        
        echo '<div class="dps-surface dps-surface--info dps-meus-dados-card">';
        echo '<div class="dps-surface__title">';
        echo '<span>👤</span>';
        echo esc_html__( 'Dados Pessoais', 'dps-client-portal' );
        echo '</div>';
        echo '<p class="dps-surface__description">' . esc_html__( 'Informações de contato e endereço para facilitar a comunicação e serviços de leva e traz.', 'dps-client-portal' ) . '</p>';
        
        echo '<form method="post" class="dps-portal-form dps-meus-dados-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_info">';
        
        // Fieldset: Contato
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '📱 Contato', 'dps-client-portal' ) . '</legend>';
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_phone" class="dps-form-label">' . esc_html__( 'Telefone / WhatsApp', 'dps-client-portal' ) . '</label>';
        echo '<input type="tel" name="client_phone" id="client_phone" value="' . esc_attr( $phone ) . '" class="dps-form-control" placeholder="(XX) XXXXX-XXXX" aria-describedby="client_phone_error">';
        echo '<span class="dps-field-error" id="client_phone_error" role="alert"></span>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_email" class="dps-form-label">' . esc_html__( 'E-mail', 'dps-client-portal' ) . '</label>';
        echo '<input type="email" name="client_email" id="client_email" value="' . esc_attr( $email ) . '" class="dps-form-control" placeholder="seu@email.com" aria-describedby="client_email_error">';
        echo '<span class="dps-field-error" id="client_email_error" role="alert"></span>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';
        
        // Fieldset: Endereço
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '📍 Endereço', 'dps-client-portal' ) . '</legend>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_address" class="dps-form-label">' . esc_html__( 'Endereço completo', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="client_address" id="client_address" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Rua, número, complemento, bairro...', 'dps-client-portal' ) . '">' . esc_textarea( $address ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-form-row dps-form-row--3col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_city" class="dps-form-label">' . esc_html__( 'Cidade', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_city" id="client_city" value="' . esc_attr( $city ) . '" class="dps-form-control">';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_state" class="dps-form-label">' . esc_html__( 'Estado', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_state" id="client_state" value="' . esc_attr( $state ) . '" class="dps-form-control" maxlength="2" placeholder="UF" aria-describedby="client_state_error">';
        echo '<span class="dps-field-error" id="client_state_error" role="alert"></span>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_zip" class="dps-form-label">' . esc_html__( 'CEP', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_zip" id="client_zip" value="' . esc_attr( $zip ) . '" class="dps-form-control" placeholder="XXXXX-XXX" inputmode="numeric" aria-describedby="client_zip_error">';
        echo '<span class="dps-field-error" id="client_zip_error" role="alert"></span>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';
        
        // Fieldset: Redes Sociais
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '🌐 Redes Sociais', 'dps-client-portal' ) . '</legend>';
        echo '<p class="dps-fieldset__help">' . esc_html__( 'Opcional - nos ajuda a compartilhar fotos dos seus pets!', 'dps-client-portal' ) . '</p>';
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_instagram" class="dps-form-label">' . esc_html__( 'Instagram', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_instagram" id="client_instagram" value="' . esc_attr( $insta ) . '" class="dps-form-control" placeholder="@seuinstagram">';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="client_facebook" class="dps-form-label">' . esc_html__( 'Facebook', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_facebook" id="client_facebook" value="' . esc_attr( $fb ) . '" class="dps-form-control" placeholder="facebook.com/seuperfil">';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';
        
        // Botão de submit
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="button button-primary dps-btn-submit">';
        echo '<span>💾</span> ' . esc_html__( 'Salvar Dados Pessoais', 'dps-client-portal' );
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>'; // .dps-surface
    }

    /**
     * Renderiza formulários de pets.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_pets_forms( $client_id ) {
        // Usa repositório para buscar pets
        $pets = $this->pet_repository->get_pets_by_client( $client_id );
        
        if ( empty( $pets ) ) {
            return;
        }
        
        foreach ( $pets as $pet ) {
            $this->render_pet_form( $pet );
        }
    }

    /**
     * Renderiza formulário de um pet.
     * Revisão de layout: Janeiro 2026
     *
     * @param WP_Post $pet Post do pet.
     */
    private function render_pet_form( $pet ) {
        $pet_id       = $pet->ID;
        $pet_name     = get_the_title( $pet_id );
        $photo_id     = get_post_meta( $pet_id, 'pet_photo_id', true );
        $photo_url    = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
        $species      = get_post_meta( $pet_id, 'pet_species', true );
        $breed        = get_post_meta( $pet_id, 'pet_breed', true );
        $size         = get_post_meta( $pet_id, 'pet_size', true );
        $weight       = get_post_meta( $pet_id, 'pet_weight', true );
        $coat         = get_post_meta( $pet_id, 'pet_coat', true );
        $color        = get_post_meta( $pet_id, 'pet_color', true );
        $birth        = get_post_meta( $pet_id, 'pet_birth', true );
        $sex          = get_post_meta( $pet_id, 'pet_sex', true );
        $vaccinations = get_post_meta( $pet_id, 'pet_vaccinations', true );
        $allergies    = get_post_meta( $pet_id, 'pet_allergies', true );
        $behavior     = get_post_meta( $pet_id, 'pet_behavior', true );
        $observations = get_post_meta( $pet_id, 'pet_observations', true );
        
        echo '<div class="dps-surface dps-surface--neutral dps-meus-dados-card dps-pet-form-card">';
        
        // Header do card com foto do pet
        echo '<div class="dps-pet-form-header">';
        if ( $photo_url ) {
            echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet_name ) . '" class="dps-pet-form-header__photo" loading="lazy" />';
        } else {
            echo '<span class="dps-pet-form-header__placeholder">🐾</span>';
        }
        echo '<div class="dps-pet-form-header__info">';
        echo '<div class="dps-surface__title"><span>🐾</span> ' . esc_html( $pet_name ) . '</div>';
        echo '<p class="dps-surface__description">' . esc_html__( 'Atualize as informações deste pet para um atendimento mais personalizado.', 'dps-client-portal' ) . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<form method="post" enctype="multipart/form-data" class="dps-portal-form dps-meus-dados-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_pet">';
        echo '<input type="hidden" name="pet_id" value="' . esc_attr( $pet_id ) . '">';
        
        // Fieldset: Identificação
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '🏷️ Identificação', 'dps-client-portal' ) . '</legend>';
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_name_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Nome', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_name" id="pet_name_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $pet_name ) . '" class="dps-form-control" required aria-required="true" aria-describedby="pet_name_' . esc_attr( $pet_id ) . '_error">';
        echo '<span class="dps-field-error" id="pet_name_' . esc_attr( $pet_id ) . '_error" role="alert"></span>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_species_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Espécie', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_species" id="pet_species_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $species_options = [ '' => __( 'Selecione...', 'dps-client-portal' ), 'Cachorro' => __( 'Cachorro', 'dps-client-portal' ), 'Gato' => __( 'Gato', 'dps-client-portal' ), 'Outro' => __( 'Outro', 'dps-client-portal' ) ];
        foreach ( $species_options as $value => $label ) {
            $selected = ( $species === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_breed_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Raça', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_breed" id="pet_breed_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $breed ) . '" class="dps-form-control" placeholder="' . esc_attr__( 'Ex: Labrador, SRD...', 'dps-client-portal' ) . '">';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_sex_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Sexo', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_sex" id="pet_sex_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $sex_options = [ '' => __( 'Selecione...', 'dps-client-portal' ), 'M' => __( 'Macho', 'dps-client-portal' ), 'F' => __( 'Fêmea', 'dps-client-portal' ) ];
        foreach ( $sex_options as $value => $label ) {
            $selected = ( $sex === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';
        
        // Fieldset: Características Físicas
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '📏 Características Físicas', 'dps-client-portal' ) . '</legend>';
        
        echo '<div class="dps-form-row dps-form-row--3col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_size_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Porte', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_size" id="pet_size_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $size_options = [ '' => __( 'Selecione...', 'dps-client-portal' ), 'Mini' => __( 'Mini (até 5kg)', 'dps-client-portal' ), 'Pequeno' => __( 'Pequeno (5-10kg)', 'dps-client-portal' ), 'Médio' => __( 'Médio (10-25kg)', 'dps-client-portal' ), 'Grande' => __( 'Grande (25-45kg)', 'dps-client-portal' ), 'Gigante' => __( 'Gigante (+45kg)', 'dps-client-portal' ) ];
        foreach ( $size_options as $value => $label ) {
            $selected = ( $size === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_weight_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Peso (kg)', 'dps-client-portal' ) . '</label>';
        echo '<input type="number" name="pet_weight" id="pet_weight_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $weight ) . '" class="dps-form-control" step="0.1" min="0" max="200" placeholder="Ex: 8.5" aria-describedby="pet_weight_' . esc_attr( $pet_id ) . '_error">';
        echo '<span class="dps-field-error" id="pet_weight_' . esc_attr( $pet_id ) . '_error" role="alert"></span>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_birth_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Nascimento', 'dps-client-portal' ) . '</label>';
        echo '<input type="date" name="pet_birth" id="pet_birth_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $birth ) . '" class="dps-form-control" max="' . esc_attr( gmdate( 'Y-m-d' ) ) . '" aria-describedby="pet_birth_' . esc_attr( $pet_id ) . '_error">';
        echo '<span class="dps-field-error" id="pet_birth_' . esc_attr( $pet_id ) . '_error" role="alert"></span>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        
        echo '<div class="dps-form-row dps-form-row--2col">';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_coat_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Tipo de Pelagem', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_coat" id="pet_coat_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $coat_options = [ '' => __( 'Selecione...', 'dps-client-portal' ), 'Curto' => __( 'Curto', 'dps-client-portal' ), 'Médio' => __( 'Médio', 'dps-client-portal' ), 'Longo' => __( 'Longo', 'dps-client-portal' ), 'Crespo' => __( 'Crespo/Encaracolado', 'dps-client-portal' ), 'Dupla Camada' => __( 'Dupla Camada', 'dps-client-portal' ) ];
        foreach ( $coat_options as $value => $label ) {
            $selected = ( $coat === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_color_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Cor', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_color" id="pet_color_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $color ) . '" class="dps-form-control" placeholder="' . esc_attr__( 'Ex: Caramelo, Preto e branco...', 'dps-client-portal' ) . '">';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        echo '</fieldset>';
        
        // Fieldset: Informações Importantes
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '⚠️ Informações Importantes', 'dps-client-portal' ) . '</legend>';
        echo '<p class="dps-fieldset__help">' . esc_html__( 'Essas informações nos ajudam a garantir a segurança e o bem-estar do seu pet durante o atendimento.', 'dps-client-portal' ) . '</p>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_allergies_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Alergias ou Condições de Saúde', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="pet_allergies" id="pet_allergies_' . esc_attr( $pet_id ) . '" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Informe alergias, problemas de pele, medicamentos em uso...', 'dps-client-portal' ) . '">' . esc_textarea( $allergies ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_vaccinations_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Vacinações', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="pet_vaccinations" id="pet_vaccinations_' . esc_attr( $pet_id ) . '" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Informe as vacinas em dia ou pendentes...', 'dps-client-portal' ) . '">' . esc_textarea( $vaccinations ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_behavior_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Comportamento', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="pet_behavior" id="pet_behavior_' . esc_attr( $pet_id ) . '" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Como seu pet se comporta no banho? Fica nervoso, morde, aceita bem?', 'dps-client-portal' ) . '">' . esc_textarea( $behavior ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_observations_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Observações Gerais', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="pet_observations" id="pet_observations_' . esc_attr( $pet_id ) . '" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Outras informações que julgar importantes...', 'dps-client-portal' ) . '">' . esc_textarea( $observations ) . '</textarea>';
        echo '</div>';
        
        echo '</fieldset>';
        
        // Fieldset: Preferências de Produtos
        $shampoo_pref         = get_post_meta( $pet_id, 'pet_shampoo_pref', true );
        $perfume_pref         = get_post_meta( $pet_id, 'pet_perfume_pref', true );
        $accessories_pref     = get_post_meta( $pet_id, 'pet_accessories_pref', true );
        $product_restrictions = get_post_meta( $pet_id, 'pet_product_restrictions', true );
        
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '🧴 Preferências de Produtos', 'dps-client-portal' ) . '</legend>';
        echo '<p class="dps-fieldset__help">' . esc_html__( 'Informe preferências ou restrições de produtos para garantir um atendimento personalizado.', 'dps-client-portal' ) . '</p>';
        
        echo '<div class="dps-form-row dps-form-row--3col">';
        
        // Shampoo
        echo '<div class="dps-form-col">';
        echo '<label for="pet_shampoo_pref_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Shampoo', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_shampoo_pref" id="pet_shampoo_pref_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $shampoo_options = [
            ''               => __( 'Sem preferência', 'dps-client-portal' ),
            'hipoalergenico' => __( 'Hipoalergênico', 'dps-client-portal' ),
            'antisseptico'   => __( 'Antisséptico', 'dps-client-portal' ),
            'pelagem_branca' => __( 'Para pelagem branca', 'dps-client-portal' ),
            'pelagem_escura' => __( 'Para pelagem escura', 'dps-client-portal' ),
            'antipulgas'     => __( 'Antipulgas', 'dps-client-portal' ),
            'hidratante'     => __( 'Hidratante', 'dps-client-portal' ),
            'outro'          => __( 'Outro', 'dps-client-portal' ),
        ];
        foreach ( $shampoo_options as $value => $label ) {
            $selected = ( $shampoo_pref === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Perfume
        echo '<div class="dps-form-col">';
        echo '<label for="pet_perfume_pref_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Perfume', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_perfume_pref" id="pet_perfume_pref_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $perfume_options = [
            ''               => __( 'Sem preferência', 'dps-client-portal' ),
            'suave'          => __( 'Perfume suave', 'dps-client-portal' ),
            'intenso'        => __( 'Perfume intenso', 'dps-client-portal' ),
            'sem_perfume'    => __( 'Sem perfume (proibido)', 'dps-client-portal' ),
            'hipoalergenico' => __( 'Hipoalergênico apenas', 'dps-client-portal' ),
        ];
        foreach ( $perfume_options as $value => $label ) {
            $selected = ( $perfume_pref === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Adereços
        echo '<div class="dps-form-col">';
        echo '<label for="pet_accessories_pref_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Adereços', 'dps-client-portal' ) . '</label>';
        echo '<select name="pet_accessories_pref" id="pet_accessories_pref_' . esc_attr( $pet_id ) . '" class="dps-form-control">';
        $accessories_options = [
            ''             => __( 'Sem preferência', 'dps-client-portal' ),
            'lacinho'      => __( 'Lacinho', 'dps-client-portal' ),
            'gravata'      => __( 'Gravata', 'dps-client-portal' ),
            'lenco'        => __( 'Lenço', 'dps-client-portal' ),
            'bandana'      => __( 'Bandana', 'dps-client-portal' ),
            'sem_aderecos' => __( 'Não usar adereços', 'dps-client-portal' ),
        ];
        foreach ( $accessories_options as $value => $label ) {
            $selected = ( $accessories_pref === $value ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-row
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_product_restrictions_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Outras restrições de produtos', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="pet_product_restrictions" id="pet_product_restrictions_' . esc_attr( $pet_id ) . '" rows="2" class="dps-form-control" placeholder="' . esc_attr__( 'Ex.: Alérgico a produto X, usar apenas produtos naturais...', 'dps-client-portal' ) . '">' . esc_textarea( $product_restrictions ) . '</textarea>';
        echo '</div>';
        
        echo '</fieldset>';
        
        // Fieldset: Foto do Pet
        echo '<fieldset class="dps-fieldset">';
        echo '<legend class="dps-fieldset__legend">' . esc_html__( '📷 Foto do Pet', 'dps-client-portal' ) . '</legend>';
        echo '<p class="dps-fieldset__help">' . esc_html__( 'Envie uma foto do seu pet. Formatos aceitos: JPG, PNG, GIF ou WebP (máx. 5 MB).', 'dps-client-portal' ) . '</p>';
        
        echo '<div class="dps-form-col">';
        echo '<label for="pet_photo_' . esc_attr( $pet_id ) . '" class="dps-form-label">' . esc_html__( 'Selecionar foto', 'dps-client-portal' ) . '</label>';
        echo '<input type="file" name="pet_photo" id="pet_photo_' . esc_attr( $pet_id ) . '" class="dps-form-control" accept="image/jpeg,image/png,image/gif,image/webp">';
        echo '</div>';
        
        echo '</fieldset>';
        
        // Botão de submit
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="button button-primary dps-btn-submit">';
        echo '<span>💾</span> ' . esc_html( sprintf( __( 'Salvar Dados de %s', 'dps-client-portal' ), $pet_name ) );
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>'; // .dps-surface
    }

    /**
     * Renderiza o cabeçalho da aba Histórico dos Pets com métricas.
     * Revisão de layout: Janeiro 2026
     *
     * @since 2.5.0
     * @param int   $client_id ID do cliente.
     * @param array $pets      Array de posts de pets.
     */
    public function render_pet_history_header( $client_id, $pets ) {
        // Coleta métricas globais de todos os pets
        $total_services    = 0;
        $last_service_date = null;
        $services_count    = [];
        $pet_history       = DPS_Portal_Pet_History::get_instance();

        foreach ( $pets as $pet ) {
            $history = $pet_history->get_pet_service_history( $pet->ID, -1 );
            $total_services += count( $history );

            foreach ( $history as $service ) {
                // Conta serviços por tipo
                if ( ! empty( $service['services_array'] ) ) {
                    foreach ( $service['services_array'] as $svc ) {
                        if ( ! isset( $services_count[ $svc ] ) ) {
                            $services_count[ $svc ] = 0;
                        }
                        $services_count[ $svc ]++;
                    }
                }
                // Última data de serviço
                if ( ! empty( $service['date'] ) ) {
                    $service_date = strtotime( $service['date'] );
                    if ( null === $last_service_date || $service_date > $last_service_date ) {
                        $last_service_date = $service_date;
                    }
                }
            }
        }

        // Determina serviço mais frequente
        $most_frequent_service = '';
        if ( ! empty( $services_count ) && is_array( $services_count ) ) {
            arsort( $services_count );
            $most_frequent_service = array_key_first( $services_count );
        }

        // Renderiza cabeçalho
        echo '<section class="dps-portal-section dps-portal-pet-history-header">';
        
        // Título e subtítulo
        echo '<div class="dps-pet-history-header">';
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">📋</span>';
        echo esc_html__( 'Histórico dos Pets', 'dps-client-portal' );
        echo '</h2>';
        echo '<p class="dps-section-subtitle">' . esc_html__( 'Acompanhe todos os serviços realizados em seus pets ao longo do tempo.', 'dps-client-portal' ) . '</p>';
        echo '</div>';

        // Cards de métricas
        echo '<div class="dps-metrics-grid dps-metrics-grid--pet-history">';

        // Card: Total de Serviços
        echo '<div class="dps-metric-card dps-metric-card--primary">';
        echo '<div class="dps-metric-card__icon">✂️</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( $total_services ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'Serviço Realizado', 'Serviços Realizados', $total_services, 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Pets Atendidos
        echo '<div class="dps-metric-card">';
        echo '<div class="dps-metric-card__icon">🐾</div>';
        echo '<div class="dps-metric-card__content">';
        echo '<span class="dps-metric-card__value">' . esc_html( count( $pets ) ) . '</span>';
        echo '<span class="dps-metric-card__label">' . esc_html( _n( 'Pet Cadastrado', 'Pets Cadastrados', count( $pets ), 'dps-client-portal' ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        // Card: Último Atendimento
        if ( $last_service_date ) {
            $days_since = floor( ( time() - $last_service_date ) / DAY_IN_SECONDS );
            $last_date_formatted = date_i18n( 'd/m/Y', $last_service_date );
            
            echo '<div class="dps-metric-card">';
            echo '<div class="dps-metric-card__icon">📅</div>';
            echo '<div class="dps-metric-card__content">';
            echo '<span class="dps-metric-card__value">' . esc_html( $last_date_formatted ) . '</span>';
            echo '<span class="dps-metric-card__label">';
            if ( 0 === $days_since ) {
                echo esc_html__( 'Hoje', 'dps-client-portal' );
            } elseif ( 1 === $days_since ) {
                echo esc_html__( 'Ontem', 'dps-client-portal' );
            } else {
                /* translators: %d: number of days */
                echo esc_html( sprintf( __( 'Há %d dias', 'dps-client-portal' ), $days_since ) );
            }
            echo '</span>';
            echo '</div>';
            echo '</div>';
        }

        // Card: Serviço Mais Frequente
        if ( $most_frequent_service ) {
            echo '<div class="dps-metric-card dps-metric-card--highlight">';
            echo '<div class="dps-metric-card__icon">⭐</div>';
            echo '<div class="dps-metric-card__content">';
            echo '<span class="dps-metric-card__value dps-metric-card__value--text">' . esc_html( $most_frequent_service ) . '</span>';
            echo '<span class="dps-metric-card__label">' . esc_html__( 'Serviço Favorito', 'dps-client-portal' ) . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>'; // .dps-metrics-grid

        // Renderiza gráfico de frequência e sugestão de lembrete
        $this->render_frequency_chart_and_reminder( $pets, $last_service_date );
        
        echo '</section>';
    }

    /**
     * Renderiza gráfico de frequência de serviços e sugestão de próximo agendamento.
     * Funcionalidades 2 e 5: Gráfico de Frequência + Notificações de Lembrete
     *
     * @since 2.5.0
     * @param array    $pets              Array de posts de pets.
     * @param int|null $last_service_date Timestamp do último serviço.
     */
    private function render_frequency_chart_and_reminder( $pets, $last_service_date ) {
        $pet_history       = DPS_Portal_Pet_History::get_instance();
        $services_by_month = [];
        $all_services      = [];

        // Coleta todos os serviços para análise
        foreach ( $pets as $pet ) {
            $history = $pet_history->get_pet_service_history( $pet->ID, -1 );
            foreach ( $history as $service ) {
                if ( ! empty( $service['date'] ) ) {
                    $all_services[] = strtotime( $service['date'] );
                    $month_key      = date( 'Y-m', strtotime( $service['date'] ) );
                    if ( ! isset( $services_by_month[ $month_key ] ) ) {
                        $services_by_month[ $month_key ] = 0;
                    }
                    $services_by_month[ $month_key ]++;
                }
            }
        }

        // Ordena datas para cálculo de intervalo médio
        sort( $all_services );

        echo '<div class="dps-history-insights">';

        // === Gráfico de Frequência (Funcionalidade 2) ===
        $this->render_frequency_chart( $services_by_month );

        // === Sugestão de Próximo Agendamento (Funcionalidade 5) ===
        $this->render_next_appointment_reminder( $all_services, $last_service_date );

        echo '</div>'; // .dps-history-insights
    }

    /**
     * Renderiza gráfico de barras de frequência de serviços por mês.
     * Funcionalidade 2: Gráfico de Frequência
     *
     * @since 2.5.0
     * @param array $services_by_month Array com contagem de serviços por mês (Y-m => count).
     */
    private function render_frequency_chart( $services_by_month ) {
        // Prepara dados dos últimos 6 meses
        $chart_data = [];
        $max_value  = 1;

        for ( $i = 5; $i >= 0; $i-- ) {
            $month_key   = date( 'Y-m', strtotime( "-$i months" ) );
            $month_label = date_i18n( 'M/y', strtotime( "-$i months" ) );
            $count       = isset( $services_by_month[ $month_key ] ) ? $services_by_month[ $month_key ] : 0;
            $chart_data[] = [
                'label' => $month_label,
                'count' => $count,
            ];
            if ( $count > $max_value ) {
                $max_value = $count;
            }
        }

        echo '<div class="dps-frequency-chart">';
        echo '<h4 class="dps-frequency-chart__title">';
        echo '<span class="dps-frequency-chart__icon">📊</span>';
        echo esc_html__( 'Frequência de Serviços', 'dps-client-portal' );
        echo '</h4>';
        echo '<p class="dps-frequency-chart__subtitle">' . esc_html__( 'Serviços realizados nos últimos 6 meses', 'dps-client-portal' ) . '</p>';

        echo '<div class="dps-chart-container">';
        echo '<div class="dps-bar-chart">';

        foreach ( $chart_data as $data ) {
            $height_percent = $max_value > 0 ? ( $data['count'] / $max_value ) * 100 : 0;
            // Mínimo 5% para visibilidade apenas se count > 0
            if ( $data['count'] > 0 && $height_percent < 5 ) {
                $height_percent = 5;
            }

            echo '<div class="dps-bar-chart__column">';
            echo '<div class="dps-bar-chart__bar-wrapper">';
            echo '<div class="dps-bar-chart__bar" style="height: ' . esc_attr( $height_percent ) . '%;" data-count="' . esc_attr( $data['count'] ) . '">';
            echo '<span class="dps-bar-chart__value">' . esc_html( $data['count'] ) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<span class="dps-bar-chart__label">' . esc_html( $data['label'] ) . '</span>';
            echo '</div>';
        }

        echo '</div>'; // .dps-bar-chart
        echo '</div>'; // .dps-chart-container
        echo '</div>'; // .dps-frequency-chart
    }

    /**
     * Renderiza sugestão de próximo agendamento baseado na frequência média.
     * Funcionalidade 5: Notificações de Lembrete
     *
     * @since 2.5.0
     * @param array    $all_services      Array de timestamps de todos os serviços ordenados.
     * @param int|null $last_service_date Timestamp do último serviço.
     */
    private function render_next_appointment_reminder( $all_services, $last_service_date ) {
        // Precisa de pelo menos 2 serviços para calcular intervalo
        if ( count( $all_services ) < 2 || null === $last_service_date ) {
            return;
        }

        // Calcula intervalo médio entre serviços (em dias)
        $intervals = [];
        for ( $i = 1; $i < count( $all_services ); $i++ ) {
            $diff = ( $all_services[ $i ] - $all_services[ $i - 1 ] ) / DAY_IN_SECONDS;
            if ( $diff > 0 && $diff < 365 ) { // Ignora intervalos muito longos ou zero
                $intervals[] = $diff;
            }
        }

        if ( empty( $intervals ) ) {
            return;
        }

        $avg_interval  = array_sum( $intervals ) / count( $intervals );
        $avg_interval  = round( $avg_interval );
        $next_date     = $last_service_date + ( $avg_interval * DAY_IN_SECONDS );
        $days_until    = ceil( ( $next_date - time() ) / DAY_IN_SECONDS );
        $next_date_fmt = date_i18n( 'd/m/Y', $next_date );

        // Determina urgência
        $urgency_class = 'dps-reminder--normal';
        $urgency_icon  = '📅';
        if ( $days_until <= 0 ) {
            $urgency_class = 'dps-reminder--overdue';
            $urgency_icon  = '⚠️';
        } elseif ( $days_until <= 7 ) {
            $urgency_class = 'dps-reminder--soon';
            $urgency_icon  = '🔔';
        }

        echo '<div class="dps-next-appointment-reminder ' . esc_attr( $urgency_class ) . '">';
        echo '<div class="dps-reminder__header">';
        echo '<span class="dps-reminder__icon">' . $urgency_icon . '</span>';
        echo '<h4 class="dps-reminder__title">' . esc_html__( 'Sugestão de Próximo Agendamento', 'dps-client-portal' ) . '</h4>';
        echo '</div>';

        echo '<div class="dps-reminder__content">';
        echo '<p class="dps-reminder__text">';

        if ( $days_until <= 0 ) {
            $days_overdue = abs( $days_until );
            /* translators: %1$d: days overdue, %2$d: average interval */
            echo esc_html( sprintf(
                __( 'Baseado na sua frequência média de %2$d dias, seu pet já deveria ter sido atendido há %1$d dia(s).', 'dps-client-portal' ),
                $days_overdue,
                $avg_interval
            ) );
        } elseif ( $days_until <= 7 ) {
            /* translators: %1$s: next date, %2$d: days until */
            echo esc_html( sprintf(
                __( 'Baseado na sua frequência média, o próximo atendimento está previsto para %1$s (em %2$d dias).', 'dps-client-portal' ),
                $next_date_fmt,
                $days_until
            ) );
        } else {
            /* translators: %1$s: next date, %2$d: average interval */
            echo esc_html( sprintf(
                __( 'Com base na frequência média de %2$d dias, sugerimos agendar para %1$s.', 'dps-client-portal' ),
                $next_date_fmt,
                $avg_interval
            ) );
        }

        echo '</p>';

        echo '<div class="dps-reminder__meta">';
        /* translators: %d: average interval in days */
        echo '<span class="dps-reminder__interval">📈 ' . esc_html( sprintf( __( 'Frequência média: %d dias', 'dps-client-portal' ), $avg_interval ) ) . '</span>';
        echo '</div>';

        // CTA para agendar
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_message = sprintf(
                __( 'Olá! Gostaria de agendar um novo atendimento para meu pet. Minha frequência média é de %d dias.', 'dps-client-portal' ),
                $avg_interval
            );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            /* translators: %d: average interval in days */
            $whatsapp_message = sprintf( __( 'Olá! Gostaria de agendar um novo atendimento para meu pet. Minha frequência média é de %d dias.', 'dps-client-portal' ), $avg_interval );
            $whatsapp_url     = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode( $whatsapp_message );
        }

        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="button button-primary dps-reminder__cta">';
        echo '📅 ' . esc_html__( 'Agendar Agora', 'dps-client-portal' );
        echo '</a>';

        echo '</div>'; // .dps-reminder__content
        echo '</div>'; // .dps-next-appointment-reminder
    }

    /**
     * Renderiza navegação por abas para múltiplos pets.
     * Revisão de layout: Janeiro 2026
     *
     * @since 2.5.0
     * @param array $pets Array de posts de pets.
     */
    public function render_pet_tabs_navigation( $pets ) {
        echo '<div class="dps-pet-tabs-nav">';
        echo '<div class="dps-pet-tabs-nav__label" id="dps-pet-tabs-label">' . esc_html__( 'Selecione o pet:', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-pet-tabs-nav__tabs" role="tablist" aria-labelledby="dps-pet-tabs-label">';

        foreach ( $pets as $index => $pet ) {
            $pet_id    = $pet->ID;
            $pet_name  = get_the_title( $pet_id );
            $photo_id  = get_post_meta( $pet_id, 'pet_photo_id', true );
            $photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
            $is_active = ( 0 === $index );

            echo '<button type="button" class="dps-pet-tab' . ( $is_active ? ' dps-pet-tab--active' : '' ) . '" id="dps-pet-tab-' . esc_attr( $pet_id ) . '" role="tab" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" aria-controls="dps-pet-panel-' . esc_attr( $pet_id ) . '" tabindex="' . ( $is_active ? '0' : '-1' ) . '" data-pet-id="' . esc_attr( $pet_id ) . '">';
            
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="" class="dps-pet-tab__photo" loading="lazy" />';
            } else {
                echo '<span class="dps-pet-tab__icon">🐾</span>';
            }
            
            echo '<span class="dps-pet-tab__name">' . esc_html( $pet_name ) . '</span>';
            echo '</button>';
        }

        echo '</div>'; // .dps-pet-tabs-nav__tabs
        echo '</div>'; // .dps-pet-tabs-nav
    }

    /**
     * Renderiza linha do tempo de serviços para um pet específico.
     * Fase 4: Timeline de Serviços
     * Revisão de layout: Janeiro 2026
     *
     * @since 2.4.0
     * @param int  $pet_id       ID do pet.
     * @param int  $client_id    ID do cliente (para validação).
     * @param int  $limit        Limite de serviços (padrão: 10).
     * @param bool $is_active    Se esta timeline está ativa/visível (padrão: true).
     * @param bool $has_tabs     Se há navegação por tabs (para atributos ARIA).
     */
    public function render_pet_service_timeline( $pet_id, $client_id, $limit = 10, $is_active = true, $has_tabs = false ) {
        $pet_history = DPS_Portal_Pet_History::get_instance();
        $services    = $pet_history->get_pet_service_history( $pet_id, $limit );
        $pet_name    = get_the_title( $pet_id );
        $pet_photo   = get_post_meta( $pet_id, 'pet_photo_id', true );
        $pet_species = get_post_meta( $pet_id, 'pet_species', true );
        $pet_breed   = get_post_meta( $pet_id, 'pet_breed', true );

        // Classes e atributos para tab panel
        $panel_class = 'dps-portal-section dps-portal-pet-timeline dps-pet-timeline-panel';
        if ( ! $is_active && $has_tabs ) {
            $panel_class .= ' dps-pet-timeline-panel--hidden';
        }

        echo '<section id="dps-pet-panel-' . esc_attr( $pet_id ) . '" class="' . esc_attr( $panel_class ) . '" data-pet-id="' . esc_attr( $pet_id ) . '" role="' . ( $has_tabs ? 'tabpanel' : 'region' ) . '" aria-hidden="' . ( $is_active ? 'false' : 'true' ) . '"' . ( $has_tabs ? ' aria-labelledby="dps-pet-tab-' . esc_attr( $pet_id ) . '"' : '' ) . '>';
        
        // Card de info do pet
        echo '<div class="dps-pet-info-card">';
        echo '<div class="dps-pet-info-card__avatar">';
        if ( $pet_photo ) {
            $photo_url = wp_get_attachment_image_url( $pet_photo, 'thumbnail' );
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet_name ) . '" loading="lazy" />';
            } else {
                echo '<span class="dps-pet-info-card__placeholder">🐾</span>';
            }
        } else {
            echo '<span class="dps-pet-info-card__placeholder">🐾</span>';
        }
        echo '</div>';
        echo '<div class="dps-pet-info-card__details">';
        echo '<h3 class="dps-pet-info-card__name">' . esc_html( $pet_name ) . '</h3>';
        if ( $pet_species || $pet_breed ) {
            echo '<p class="dps-pet-info-card__breed">';
            echo esc_html( trim( $pet_species . ' ' . ( $pet_breed ? '• ' . $pet_breed : '' ) ) );
            echo '</p>';
        }
        echo '<span class="dps-pet-info-card__count">';
        /* translators: %d: number of services */
        echo esc_html( sprintf( _n( '%d serviço realizado', '%d serviços realizados', count( $services ), 'dps-client-portal' ), count( $services ) ) );
        echo '</span>';
        echo '</div>';
        echo '</div>'; // .dps-pet-info-card

        // Botão Exportar PDF (Funcionalidade 3)
        echo '<div class="dps-pet-actions-bar">';
        echo '<button type="button" class="button button-secondary dps-btn-export-pdf" data-pet-id="' . esc_attr( $pet_id ) . '" data-pet-name="' . esc_attr( $pet_name ) . '">';
        echo '📄 ' . esc_html__( 'Exportar Histórico (PDF)', 'dps-client-portal' );
        echo '</button>';
        echo '</div>';

        if ( empty( $services ) ) {
            $this->render_pet_timeline_empty_state( $pet_name );
        } else {
            $this->render_timeline_items( $services, $client_id, $pet_id );
            
            // Botão "Ver mais" se há mais serviços
            if ( count( $services ) === $limit ) {
                echo '<div class="dps-timeline-load-more">';
                echo '<button type="button" class="button button-secondary dps-btn-load-more-services" data-pet-id="' . esc_attr( $pet_id ) . '" data-offset="' . esc_attr( $limit ) . '">';
                echo '📜 ' . esc_html__( 'Ver mais serviços', 'dps-client-portal' );
                echo '</button>';
                echo '</div>';
            }
        }

        echo '</section>';
    }

    /**
     * Renderiza página de impressão do histórico do pet (para export PDF).
     * Funcionalidade 3: Export para PDF
     *
     * @since 2.5.0
     * @param int $pet_id    ID do pet.
     * @param int $client_id ID do cliente.
     */
    public function render_pet_history_print_page( $pet_id, $client_id ) {
        $pet_history = DPS_Portal_Pet_History::get_instance();
        $services    = $pet_history->get_pet_service_history( $pet_id, -1 ); // Todos os serviços
        $pet_name    = get_the_title( $pet_id );
        $pet_species = get_post_meta( $pet_id, 'pet_species', true );
        $pet_breed   = get_post_meta( $pet_id, 'pet_breed', true );
        $pet_photo   = get_post_meta( $pet_id, 'pet_photo_id', true );
        $photo_url   = $pet_photo ? wp_get_attachment_image_url( $pet_photo, 'medium' ) : '';

        // Nome do petshop
        $shop_name = get_option( 'dps_shop_name', get_bloginfo( 'name' ) );

        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( sprintf( __( 'Histórico de %s - %s', 'dps-client-portal' ), $pet_name, $shop_name ) ); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                    color: #374151;
                    line-height: 1.5;
                    padding: 40px;
                    max-width: 800px;
                    margin: 0 auto;
                }
                .print-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 2px solid #0ea5e9;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .print-header__logo { font-size: 24px; font-weight: 600; color: #0ea5e9; }
                .print-header__date { color: #6b7280; font-size: 14px; }
                .pet-info {
                    display: flex;
                    gap: 20px;
                    background: #f9fafb;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 30px;
                }
                .pet-info__photo {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 3px solid #fff;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .pet-info__placeholder {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: #e5e7eb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 32px;
                }
                .pet-info__details h1 { font-size: 22px; margin-bottom: 4px; }
                .pet-info__details p { color: #6b7280; font-size: 14px; }
                .services-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                .services-table th {
                    background: #f3f4f6;
                    padding: 12px;
                    text-align: left;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    border-bottom: 2px solid #e5e7eb;
                }
                .services-table td {
                    padding: 12px;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 14px;
                }
                .services-table tr:nth-child(even) { background: #f9fafb; }
                .print-footer {
                    text-align: center;
                    padding-top: 20px;
                    border-top: 1px solid #e5e7eb;
                    color: #9ca3af;
                    font-size: 12px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .status-paid { background: #d1fae5; color: #047857; }
                .status-completed { background: #f3f4f6; color: #4b5563; }
                @media print {
                    body { padding: 20px; }
                    .no-print { display: none !important; }
                }
                .print-actions {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    display: flex;
                    gap: 10px;
                }
                .print-actions button {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                }
                .btn-print { background: #0ea5e9; color: white; }
                .btn-close { background: #f3f4f6; color: #374151; }
            </style>
        </head>
        <body>
            <div class="print-actions no-print">
                <button type="button" class="btn-print" id="dps-print-btn">🖨️ <?php esc_html_e( 'Imprimir / Salvar PDF', 'dps-client-portal' ); ?></button>
                <button type="button" class="btn-close" id="dps-close-btn"><?php esc_html_e( 'Fechar', 'dps-client-portal' ); ?></button>
            </div>
            <script>
                document.getElementById('dps-print-btn').addEventListener('click', function() { window.print(); });
                document.getElementById('dps-close-btn').addEventListener('click', function() { window.close(); });
            </script>

            <header class="print-header">
                <div class="print-header__logo">🐾 <?php echo esc_html( $shop_name ); ?></div>
                <div class="print-header__date"><?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?></div>
            </header>

            <div class="pet-info">
                <?php if ( $photo_url ) : ?>
                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $pet_name ); ?>" class="pet-info__photo" loading="lazy">
                <?php else : ?>
                    <div class="pet-info__placeholder">🐾</div>
                <?php endif; ?>
                <div class="pet-info__details">
                    <h1><?php echo esc_html( $pet_name ); ?></h1>
                    <?php if ( $pet_species || $pet_breed ) : ?>
                        <p><?php echo esc_html( trim( $pet_species . ( $pet_breed ? ' • ' . $pet_breed : '' ) ) ); ?></p>
                    <?php endif; ?>
                    <p><strong><?php echo esc_html( sprintf( _n( '%d serviço realizado', '%d serviços realizados', count( $services ), 'dps-client-portal' ), count( $services ) ) ); ?></strong></p>
                </div>
            </div>

            <?php if ( ! empty( $services ) ) : ?>
                <table class="services-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Serviços', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Profissional', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Valor', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dps-client-portal' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $services as $service ) : 
                            $appointment_id    = isset( $service['appointment_id'] ) ? absint( $service['appointment_id'] ) : 0;
                            $appointment_value = $appointment_id > 0 ? get_post_meta( $appointment_id, 'appointment_value', true ) : '';
                            $status            = ! empty( $service['status'] ) ? $service['status'] : __( 'Concluído', 'dps-client-portal' );
                            $status_class      = str_contains( strtolower( $status ), 'pago' ) ? 'status-paid' : 'status-completed';
                        ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $service['date'] ) ) ); ?>
                                    <?php if ( ! empty( $service['time'] ) ) : ?>
                                        <br><small><?php echo esc_html( $service['time'] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $service['services'] ); ?></td>
                                <td><?php echo esc_html( ! empty( $service['professional'] ) ? $service['professional'] : '-' ); ?></td>
                                <td><?php echo $appointment_value && is_numeric( $appointment_value ) ? esc_html( DPS_Money_Helper::format_currency_from_decimal( (float) $appointment_value ) ) : '-'; ?></td>
                                <td><span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'Nenhum serviço registrado para este pet.', 'dps-client-portal' ); ?></p>
            <?php endif; ?>

            <footer class="print-footer">
                <?php echo esc_html( sprintf( __( 'Documento gerado em %s por %s', 'dps-client-portal' ), date_i18n( 'd/m/Y H:i' ), $shop_name ) ); ?>
            </footer>
        </body>
        </html>
        <?php
    }

    /**
     * Renderiza estado vazio da timeline.
     *
     * @param string $pet_name Nome do pet.
     */
    private function render_pet_timeline_empty_state( $pet_name ) {
        echo '<div class="dps-empty-state">';
        echo '<div class="dps-empty-state__icon">📅</div>';
        echo '<div class="dps-empty-state__message">';
        echo esc_html( sprintf( 
            __( 'O %s ainda não fez nenhum serviço de banho e tosa aqui.', 'dps-client-portal' ),
            $pet_name
        ) );
        echo '</div>';
        
        // CTA para agendar
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $message      = sprintf( __( 'Olá! Gostaria de agendar o primeiro banho/tosa para o %s.', 'dps-client-portal' ), $pet_name );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $message );
        } else {
            $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $whatsapp_number = DPS_Phone_Helper::format_for_whatsapp( $whatsapp_number );
            }
            $message_text = urlencode( sprintf( 'Olá! Gostaria de agendar o primeiro banho/tosa para o %s.', $pet_name ) );
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $message_text;
        }
        
        echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="dps-empty-state__action button button-primary">';
        echo '📅 ' . esc_html__( 'Agendar Primeiro Banho/Tosa', 'dps-client-portal' );
        echo '</a>';
        echo '</div>';
    }

    /**
     * Renderiza itens da timeline.
     *
     * @param array $services   Array de serviços.
     * @param int   $client_id  ID do cliente.
     * @param int   $pet_id     ID do pet.
     */
    private function render_timeline_items( $services, $client_id, $pet_id ) {
        echo '<div class="dps-timeline">';
        
        foreach ( $services as $service ) {
            $this->render_timeline_item( $service, $client_id, $pet_id );
        }
        
        echo '</div>';
    }

    /**
     * Renderiza um item individual da timeline (acesso público para AJAX).
     *
     * @since 3.1.0
     * @param array $service   Dados do serviço.
     * @param int   $client_id ID do cliente.
     * @param int   $pet_id    ID do pet.
     */
    public function render_single_timeline_item( $service, $client_id, $pet_id ) {
        $this->render_timeline_item( $service, $client_id, $pet_id );
    }

    /**
     * Renderiza um item individual da timeline.
     * Revisão de layout: Janeiro 2026
     *
     * @param array $service   Dados do serviço.
     * @param int   $client_id ID do cliente.
     * @param int   $pet_id    ID do pet.
     */
    private function render_timeline_item( $service, $client_id, $pet_id ) {
        $date_formatted = date_i18n( 'd/m/Y', strtotime( $service['date'] ) );
        $time_info      = ! empty( $service['time'] ) ? $service['time'] : '';
        $status         = ! empty( $service['status'] ) ? $service['status'] : 'finalizado';
        
        // Determina badge de status baseado no status do agendamento
        $status_lower = strtolower( $status );
        $status_class = 'dps-status-badge--completed';
        $status_label = __( 'Concluído', 'dps-client-portal' );

        if ( str_contains( $status_lower, 'pago' ) ) {
            $status_class = 'dps-status-badge--paid';
            $status_label = __( 'Pago', 'dps-client-portal' );
        } elseif ( str_contains( $status_lower, 'cancelado' ) || str_contains( $status_lower, 'cancelad' ) ) {
            $status_class = 'dps-status-badge--cancelled';
            $status_label = __( 'Cancelado', 'dps-client-portal' );
        } elseif ( str_contains( $status_lower, 'pendente' ) || str_contains( $status_lower, 'pending' ) ) {
            $status_class = 'dps-status-badge--pending';
            $status_label = __( 'Pendente', 'dps-client-portal' );
        } elseif ( str_contains( $status_lower, 'andamento' ) || str_contains( $status_lower, 'progress' ) ) {
            $status_class = 'dps-status-badge--in-progress';
            $status_label = __( 'Em Andamento', 'dps-client-portal' );
        }

        // Busca valor do agendamento se disponível (valida ID antes de consultar)
        $appointment_value = '';
        $appointment_id    = isset( $service['appointment_id'] ) ? absint( $service['appointment_id'] ) : 0;
        if ( $appointment_id > 0 ) {
            $appointment_value = get_post_meta( $appointment_id, 'appointment_value', true );
        }

        echo '<div class="dps-timeline-item">';
        echo '<div class="dps-timeline-marker"></div>';
        echo '<div class="dps-timeline-content">';
        
        // Header com data e status
        echo '<div class="dps-timeline-header">';
        echo '<div class="dps-timeline-date">';
        echo '<span class="dps-timeline-date__day">' . esc_html( $date_formatted ) . '</span>';
        if ( $time_info ) {
            echo '<span class="dps-timeline-date__time">' . esc_html( $time_info ) . '</span>';
        }
        echo '</div>';
        echo '<span class="dps-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
        echo '</div>';
        
        // Tipo de serviço
        echo '<div class="dps-timeline-service">';
        echo '<span class="dps-timeline-service__icon">✂️</span>';
        echo '<span class="dps-timeline-service__text">' . esc_html( $service['services'] ) . '</span>';
        echo '</div>';
        
        // Meta info row (profissional e valor)
        $has_meta = ! empty( $service['professional'] ) || ! empty( $appointment_value );
        if ( $has_meta ) {
            echo '<div class="dps-timeline-meta">';
            
            // Profissional
            if ( ! empty( $service['professional'] ) ) {
                echo '<span class="dps-timeline-meta__item">';
                echo '<span class="dps-timeline-meta__icon">👤</span>';
                echo esc_html( $service['professional'] );
                echo '</span>';
            }
            
            // Valor
            if ( ! empty( $appointment_value ) && is_numeric( $appointment_value ) && (float) $appointment_value > 0 ) {
                echo '<span class="dps-timeline-meta__item dps-timeline-meta__item--value">';
                echo '<span class="dps-timeline-meta__icon">💰</span>';
                echo esc_html( DPS_Money_Helper::format_currency_from_decimal( (float) $appointment_value ) );
                echo '</span>';
            }
            
            echo '</div>';
        }
        
        // Observações (se houver) - com toggle para expandir
        if ( ! empty( $service['observations'] ) ) {
            echo '<div class="dps-timeline-notes">';
            echo '<details class="dps-timeline-notes__details">';
            echo '<summary class="dps-timeline-notes__summary">';
            echo '<span class="dps-timeline-notes__icon">📝</span>';
            echo esc_html__( 'Observações', 'dps-client-portal' );
            echo '</summary>';
            echo '<p class="dps-timeline-notes__text">' . esc_html( $service['observations'] ) . '</p>';
            echo '</details>';
            echo '</div>';
        }
        
        // Resumo de Check-in/Check-out e itens de segurança (portal público)
        if ( $appointment_id > 0 && class_exists( 'DPS_Agenda_Addon' ) ) {
            $ops_summary = DPS_Agenda_Addon::render_checkin_checklist_summary( $appointment_id, true );
            if ( ! empty( $ops_summary ) ) {
                echo '<div class="dps-timeline-ops">';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML seguro retornado pelo render helper
                echo $ops_summary;
                echo '</div>';
            }
        }
        
        // Ações
        echo '<div class="dps-timeline-actions">';
        
        // Botão "Repetir este serviço"
        if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
            $whatsapp_message = sprintf(
                __( 'Olá! Gostaria de agendar novamente os serviços: %s para meu pet.', 'dps-client-portal' ),
                $service['services']
            );
            $whatsapp_url = DPS_WhatsApp_Helper::get_link_to_team( $whatsapp_message );
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="button button-primary dps-btn-repeat-service">';
            echo '🔄 ' . esc_html__( 'Repetir Serviço', 'dps-client-portal' );
            echo '</a>';
        } else {
            echo '<button class="button button-secondary dps-btn-repeat-service" data-appointment-id="' . esc_attr( $service['appointment_id'] ) . '" data-pet-id="' . esc_attr( $pet_id ) . '" data-services="' . esc_attr( wp_json_encode( $service['services_array'] ) ) . '">';
            echo '🔄 ' . esc_html__( 'Repetir Serviço', 'dps-client-portal' );
            echo '</button>';
        }
        
        echo '</div>';
        
        echo '</div>'; // .dps-timeline-content
        echo '</div>'; // .dps-timeline-item
    }

    /**
     * Renderiza ações rápidas no card de próximo agendamento.
     * Fase 4: Quick Actions
     *
     * @since 2.4.0
     * @param WP_Post $appointment Objeto do agendamento.
     * @param int     $client_id   ID do cliente.
     */
    public function render_appointment_quick_actions( $appointment, $client_id ) {
        echo '<div class="dps-appointment-actions">';
        
        // Botão de reagendar
        echo '<button type="button" class="dps-btn-reschedule" data-appointment-id="' . esc_attr( $appointment->ID ) . '">';
        echo '📅 ' . esc_html__( 'Solicitar Reagendamento', 'dps-client-portal' );
        echo '</button>';
        
        // Botão de cancelar
        echo '<button type="button" class="dps-btn-cancel" data-appointment-id="' . esc_attr( $appointment->ID ) . '">';
        echo '❌ ' . esc_html__( 'Solicitar Cancelamento', 'dps-client-portal' );
        echo '</button>';
        
        echo '</div>';
    }

    /**
     * Renderiza seção de solicitações recentes do cliente.
     * Fase 4: Dashboard de Solicitações
     *
     * @since 2.4.0
     * @param int $client_id ID do cliente.
     */
    public function render_recent_requests( $client_id ) {
        $request_repo = DPS_Appointment_Request_Repository::get_instance();
        $requests     = $request_repo->get_requests_by_client( $client_id, '', 5 );

        if ( empty( $requests ) ) {
            return;
        }

        echo '<section class="dps-portal-section dps-portal-requests">';
        echo '<h2>📋 ' . esc_html__( 'Suas Solicitações Recentes', 'dps-client-portal' ) . '</h2>';
        
        echo '<div class="dps-requests-list">';
        foreach ( $requests as $request ) {
            $this->render_request_card( $request );
        }
        echo '</div>';
        
        echo '</section>';
    }

    /**
     * Renderiza card individual de solicitação.
     *
     * @param WP_Post $request Post da solicitação.
     */
    private function render_request_card( $request ) {
        $data = DPS_Appointment_Request_Repository::get_instance()->get_request_data( $request->ID );
        
        if ( ! $data ) {
            return;
        }

        $status_labels = [
            'pending'   => __( 'Aguardando Confirmação', 'dps-client-portal' ),
            'confirmed' => __( 'Confirmado', 'dps-client-portal' ),
            'rejected'  => __( 'Não Aprovado', 'dps-client-portal' ),
            'adjusted'  => __( 'Ajustado', 'dps-client-portal' ),
        ];

        $status_classes = [
            'pending'   => 'status-pending',
            'confirmed' => 'status-confirmed',
            'rejected'  => 'status-rejected',
            'adjusted'  => 'status-adjusted',
        ];

        $type_labels = [
            'new'        => __( 'Novo Agendamento', 'dps-client-portal' ),
            'reschedule' => __( 'Reagendamento', 'dps-client-portal' ),
            'cancel'     => __( 'Cancelamento', 'dps-client-portal' ),
        ];

        $status       = $data['status'];
        $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
        $status_class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : '';
        $type_label   = isset( $type_labels[ $data['type'] ] ) ? $type_labels[ $data['type'] ] : $data['type'];

        echo '<div class="dps-request-card ' . esc_attr( $status_class ) . '">';
        
        // Header com tipo e status
        echo '<div class="dps-request-card__header">';
        echo '<span class="dps-request-card__type">' . esc_html( $type_label ) . '</span>';
        echo '<span class="dps-request-card__status">' . esc_html( $status_label ) . '</span>';
        echo '</div>';
        
        // Conteúdo
        echo '<div class="dps-request-card__content">';
        
        // Pet
        if ( $data['pet_id'] ) {
            $pet_name = get_the_title( $data['pet_id'] );
            echo '<div class="dps-request-card__pet">🐾 ' . esc_html( $pet_name ) . '</div>';
        }
        
        // Data desejada
        if ( ! empty( $data['desired_date'] ) ) {
            $period_labels = [
                'morning'   => __( 'manhã', 'dps-client-portal' ),
                'afternoon' => __( 'tarde', 'dps-client-portal' ),
            ];
            $period_label = isset( $period_labels[ $data['desired_period'] ] ) ? $period_labels[ $data['desired_period'] ] : '';
            
            echo '<div class="dps-request-card__date">';
            echo '📅 ' . esc_html( date_i18n( 'd/m/Y', strtotime( $data['desired_date'] ) ) );
            if ( $period_label ) {
                echo ' - ' . esc_html( $period_label );
            }
            echo '</div>';
        }
        
        // Data confirmada (se status = confirmed)
        if ( 'confirmed' === $status && ! empty( $data['confirmed_date'] ) ) {
            echo '<div class="dps-request-card__confirmed">';
            echo '<strong>' . esc_html__( 'Confirmado para:', 'dps-client-portal' ) . '</strong> ';
            echo esc_html( date_i18n( 'd/m/Y', strtotime( $data['confirmed_date'] ) ) );
            if ( ! empty( $data['confirmed_time'] ) ) {
                echo ' às ' . esc_html( $data['confirmed_time'] );
            }
            echo '</div>';
        }
        
        // Observações
        if ( ! empty( $data['notes'] ) ) {
            echo '<div class="dps-request-card__notes">';
            echo esc_html( $data['notes'] );
            echo '</div>';
        }
        
        echo '</div>'; // .dps-request-card__content
        
        echo '</div>'; // .dps-request-card
    }
}
