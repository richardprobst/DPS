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
     * Renderiza seção do próximo agendamento.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_next_appointment( $client_id ) {
        echo '<section id="proximos" class="dps-portal-section dps-portal-next">';
        echo '<h2>' . esc_html__( '📅 Seu Próximo Horário', 'dps-client-portal' ) . '</h2>';
        
        // Usa repositório para buscar próximo agendamento
        $next = $this->appointment_repository->get_next_appointment_for_client( $client_id );
        
        if ( $next ) {
            $this->render_next_appointment_card( $next, $client_id );
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
        // Link para mapa
        $address = get_post_meta( $client_id, 'client_address', true );
        if ( $address ) {
            $query = urlencode( $address );
            $url   = 'https://www.google.com/maps/search/?api=1&query=' . $query;
            echo '<a href="' . esc_url( $url ) . '" target="_blank" class="dps-appointment-card__action">📍 ' . esc_html__( 'Ver no mapa', 'dps-client-portal' ) . '</a>';
        }
        
        // Ações rápidas (Fase 4)
        $this->render_appointment_quick_actions( $appointment, $client_id );
        
        echo '</div>';
        echo '</div>';
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
            $whatsapp_number = get_option( 'dps_whatsapp_number', '5515991606299' );
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
        
        echo '<section id="pendencias" class="dps-portal-section dps-portal-finances">';
        echo '<h2>' . esc_html__( '💳 Pagamentos Pendentes', 'dps-client-portal' ) . '</h2>';
        
        if ( $pendings ) {
            $this->render_financial_pending_list( $pendings );
        } else {
            $this->render_financial_clear_state();
        }
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
        foreach ( $pendings as $trans ) {
            $total += (float) $trans->valor;
        }
        
        // Card de resumo de pendências com destaque
        echo '<div class="dps-financial-summary">';
        echo '<div class="dps-financial-summary__icon">⚠️</div>';
        echo '<div class="dps-financial-summary__content">';
        echo '<div class="dps-financial-summary__title">' . esc_html( sprintf( 
            _n( '%d Pendência', '%d Pendências', count( $pendings ), 'dps-client-portal' ),
            count( $pendings )
        ) ) . '</div>';
        echo '<div class="dps-financial-summary__amount">R$ ' . esc_html( number_format( $total, 2, ',', '.' ) ) . '</div>';
        echo '</div>';
        echo '<div class="dps-financial-summary__action">';
        echo '<button class="button button-primary dps-btn-toggle-details" data-target="financial-details">';
        echo esc_html__( 'Ver Detalhes', 'dps-client-portal' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // Tabela de detalhes (inicialmente oculta em mobile)
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
        $valor = number_format( (float) $transaction->valor, 2, ',', '.' );
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
     *
     * @param int $client_id ID do cliente.
     */
    public function render_pet_gallery( $client_id ) {
        // Usa repositório para buscar pets
        $pets = $this->pet_repository->get_pets_by_client( $client_id );
        
        echo '<section class="dps-portal-section dps-portal-gallery">';
        echo '<h2>' . esc_html__( '📸 Galeria de Fotos', 'dps-client-portal' ) . '</h2>';
        
        if ( $pets ) {
            echo '<div class="dps-pet-gallery">';
            foreach ( $pets as $pet ) {
                $this->render_pet_gallery_item( $pet );
            }
            echo '</div>';
        } else {
            echo '<div class="dps-empty-state">';
            echo '<div class="dps-empty-state__icon">🐾</div>';
            echo '<div class="dps-empty-state__message">' . esc_html__( 'Nenhum pet cadastrado ainda.', 'dps-client-portal' ) . '</div>';
            echo '</div>';
        }
        
        echo '</section>';
    }

    /**
     * Renderiza um item da galeria de pet.
     *
     * @param WP_Post $pet Post do pet.
     */
    private function render_pet_gallery_item( $pet ) {
        $photo_id = get_post_meta( $pet->ID, 'pet_photo_id', true );
        $photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'medium' ) : '';
        
        echo '<div class="dps-pet-gallery__item">';
        if ( $photo_url ) {
            echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( get_the_title( $pet->ID ) ) . '" class="dps-pet-gallery__image">';
        } else {
            echo '<div class="dps-pet-gallery__placeholder">🐾</div>';
        }
        echo '<div class="dps-pet-gallery__name">' . esc_html( get_the_title( $pet->ID ) ) . '</div>';
        echo '</div>';
    }

    /**
     * Renderiza centro de mensagens.
     *
     * @param int $client_id ID do cliente.
     */
    public function render_message_center( $client_id ) {
        echo '<section class="dps-portal-section dps-portal-messages">';
        echo '<h2>' . esc_html__( '💬 Central de Mensagens', 'dps-client-portal' ) . '</h2>';
        echo '<p>' . esc_html__( 'Use o chat flutuante no canto inferior direito para conversar conosco em tempo real!', 'dps-client-portal' ) . '</p>';
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
        echo '<section class="dps-portal-section dps-portal-forms">';
        echo '<h2>' . esc_html__( '✏️ Atualizar Meus Dados', 'dps-client-portal' ) . '</h2>';
        
        $this->render_client_info_form( $client_id );
        $this->render_pets_forms( $client_id );
        
        echo '</section>';
    }

    /**
     * Renderiza formulário de informações do cliente.
     *
     * @param int $client_id ID do cliente.
     */
    private function render_client_info_form( $client_id ) {
        $phone    = get_post_meta( $client_id, 'client_phone', true );
        $email    = get_post_meta( $client_id, 'client_email', true );
        $address  = get_post_meta( $client_id, 'client_address', true );
        $insta    = get_post_meta( $client_id, 'client_instagram', true );
        $fb       = get_post_meta( $client_id, 'client_facebook', true );
        
        echo '<div class="dps-portal-form-card">';
        echo '<h3 class="dps-portal-form-card__title">' . esc_html__( '👤 Dados de Contato', 'dps-client-portal' ) . '</h3>';
        echo '<form method="post" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_client_info">';
        
        echo '<div class="dps-form-grid">';
        echo '<div class="dps-form-field">';
        echo '<label for="client_phone">' . esc_html__( 'Telefone', 'dps-client-portal' ) . '</label>';
        echo '<input type="tel" name="client_phone" id="client_phone" value="' . esc_attr( $phone ) . '" class="dps-form-control">';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="client_email">' . esc_html__( 'E-mail', 'dps-client-portal' ) . '</label>';
        echo '<input type="email" name="client_email" id="client_email" value="' . esc_attr( $email ) . '" class="dps-form-control">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="client_address">' . esc_html__( 'Endereço', 'dps-client-portal' ) . '</label>';
        echo '<textarea name="client_address" id="client_address" rows="2" class="dps-form-control">' . esc_textarea( $address ) . '</textarea>';
        echo '</div>';
        
        echo '<div class="dps-form-grid">';
        echo '<div class="dps-form-field">';
        echo '<label for="client_instagram">' . esc_html__( 'Instagram', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_instagram" id="client_instagram" value="' . esc_attr( $insta ) . '" class="dps-form-control">';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="client_facebook">' . esc_html__( 'Facebook', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="client_facebook" id="client_facebook" value="' . esc_attr( $fb ) . '" class="dps-form-control">';
        echo '</div>';
        echo '</div>';
        
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Salvar Alterações', 'dps-client-portal' ) . '</button>';
        echo '</form>';
        echo '</div>';
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
     *
     * @param WP_Post $pet Post do pet.
     */
    private function render_pet_form( $pet ) {
        $pet_id       = $pet->ID;
        $pet_name     = get_the_title( $pet_id );
        $species      = get_post_meta( $pet_id, 'pet_species', true );
        $breed        = get_post_meta( $pet_id, 'pet_breed', true );
        $size         = get_post_meta( $pet_id, 'pet_size', true );
        $weight       = get_post_meta( $pet_id, 'pet_weight', true );
        $coat         = get_post_meta( $pet_id, 'pet_coat', true );
        $color        = get_post_meta( $pet_id, 'pet_color', true );
        $birth        = get_post_meta( $pet_id, 'pet_birth', true );
        $sex          = get_post_meta( $pet_id, 'pet_sex', true );
        $vacc         = get_post_meta( $pet_id, 'pet_vaccinations', true );
        $allergies    = get_post_meta( $pet_id, 'pet_allergies', true );
        $behavior     = get_post_meta( $pet_id, 'pet_behavior', true );
        
        echo '<div class="dps-portal-form-card">';
        echo '<h3 class="dps-portal-form-card__title">🐾 ' . esc_html( $pet_name ) . '</h3>';
        echo '<form method="post" enctype="multipart/form-data" class="dps-portal-form">';
        wp_nonce_field( 'dps_client_portal_action', '_dps_client_portal_nonce' );
        echo '<input type="hidden" name="dps_client_portal_action" value="update_pet">';
        echo '<input type="hidden" name="pet_id" value="' . esc_attr( $pet_id ) . '">';
        
        echo '<div class="dps-form-grid">';
        echo '<div class="dps-form-field">';
        echo '<label for="pet_name_' . esc_attr( $pet_id ) . '">' . esc_html__( 'Nome', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_name" id="pet_name_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $pet_name ) . '" class="dps-form-control">';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="pet_species_' . esc_attr( $pet_id ) . '">' . esc_html__( 'Espécie', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_species" id="pet_species_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $species ) . '" class="dps-form-control">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-form-grid">';
        echo '<div class="dps-form-field">';
        echo '<label for="pet_breed_' . esc_attr( $pet_id ) . '">' . esc_html__( 'Raça', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_breed" id="pet_breed_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $breed ) . '" class="dps-form-control">';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="pet_size_' . esc_attr( $pet_id ) . '">' . esc_html__( 'Porte', 'dps-client-portal' ) . '</label>';
        echo '<input type="text" name="pet_size" id="pet_size_' . esc_attr( $pet_id ) . '" value="' . esc_attr( $size ) . '" class="dps-form-control">';
        echo '</div>';
        echo '</div>';
        
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Salvar Dados do Pet', 'dps-client-portal' ) . '</button>';
        echo '</form>';
        echo '</div>';
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
        
        echo '</section>';
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
        echo '<div class="dps-pet-tabs-nav__label">' . esc_html__( 'Selecione o pet:', 'dps-client-portal' ) . '</div>';
        echo '<div class="dps-pet-tabs-nav__tabs" role="tablist">';

        foreach ( $pets as $index => $pet ) {
            $pet_id    = $pet->ID;
            $pet_name  = get_the_title( $pet_id );
            $photo_id  = get_post_meta( $pet_id, 'pet_photo_id', true );
            $photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
            $is_active = ( 0 === $index ) ? ' dps-pet-tab--active' : '';

            echo '<button type="button" class="dps-pet-tab' . esc_attr( $is_active ) . '" role="tab" aria-selected="' . ( 0 === $index ? 'true' : 'false' ) . '" data-pet-id="' . esc_attr( $pet_id ) . '">';
            
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="" class="dps-pet-tab__photo" />';
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

        echo '<section class="' . esc_attr( $panel_class ) . '" data-pet-id="' . esc_attr( $pet_id ) . '" role="' . ( $has_tabs ? 'tabpanel' : 'region' ) . '" aria-hidden="' . ( $is_active ? 'false' : 'true' ) . '">';
        
        // Card de info do pet
        echo '<div class="dps-pet-info-card">';
        echo '<div class="dps-pet-info-card__avatar">';
        if ( $pet_photo ) {
            $photo_url = wp_get_attachment_image_url( $pet_photo, 'thumbnail' );
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( $pet_name ) . '" />';
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
        
        // Determina badge de status
        $status_class = 'dps-status-badge--completed';
        $status_label = __( 'Concluído', 'dps-client-portal' );
        // PHP 8.0+: usa str_contains para verificação mais legível
        if ( str_contains( strtolower( $status ), 'pago' ) ) {
            $status_class = 'dps-status-badge--paid';
            $status_label = __( 'Pago', 'dps-client-portal' );
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
                echo 'R$ ' . esc_html( number_format( (float) $appointment_value, 2, ',', '.' ) );
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
        echo '<button class="button button-secondary dps-btn-reschedule" data-appointment-id="' . esc_attr( $appointment->ID ) . '">';
        echo '📅 ' . esc_html__( 'Solicitar Reagendamento', 'dps-client-portal' );
        echo '</button>';
        
        // Botão de cancelar
        echo '<button class="button button-secondary dps-btn-cancel" data-appointment-id="' . esc_attr( $appointment->ID ) . '">';
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
