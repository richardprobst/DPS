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
}
