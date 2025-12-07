<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provedor de dados para o portal do cliente.
 * 
 * Esta classe é responsável por buscar e agregar dados necessários
 * para exibição no portal, utilizando repositórios para acesso a dados.
 * 
 * @since 3.0.0
 */
class DPS_Portal_Data_Provider {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Data_Provider|null
     */
    private static $instance = null;

    /**
     * Repositório de mensagens.
     *
     * @var DPS_Message_Repository
     */
    private $message_repository;

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
     * @return DPS_Portal_Data_Provider
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
        $this->message_repository     = DPS_Message_Repository::get_instance();
        $this->appointment_repository = DPS_Appointment_Repository::get_instance();
        $this->finance_repository     = DPS_Finance_Repository::get_instance();
        $this->pet_repository         = DPS_Pet_Repository::get_instance();
    }

    /**
     * Conta mensagens não lidas do admin para o cliente.
     *
     * @since 2.3.0
     * @param int $client_id ID do cliente.
     * @return int Quantidade de mensagens não lidas.
     */
    public function get_unread_messages_count( $client_id ) {
        return $this->message_repository->count_unread_messages( $client_id );
    }

    /**
     * Conta agendamentos futuros do cliente (para badge da tab).
     *
     * @param int $client_id ID do cliente.
     * @return int Número de agendamentos futuros.
     * @since 2.4.0
     */
    public function count_upcoming_appointments( $client_id ) {
        return $this->appointment_repository->count_upcoming_appointments( $client_id );
    }

    /**
     * Conta pendências financeiras do cliente (para badge da tab).
     *
     * @param int $client_id ID do cliente.
     * @return int Número de pendências.
     * @since 2.4.0
     */
    public function count_financial_pending( $client_id ) {
        return $this->finance_repository->count_pending_transactions( $client_id );
    }

    /**
     * Busca sugestões de agendamento baseadas no histórico do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return array Array de sugestões.
     */
    public function get_scheduling_suggestions( $client_id ) {
        // Busca pets do cliente usando repositório
        $pets = $this->pet_repository->get_pets_by_client( $client_id );
        
        if ( empty( $pets ) ) {
            return []; // Sem pets, sem sugestões
        }
        
        // Busca último agendamento de cada pet
        $suggestions = [];
        $today = current_time( 'Y-m-d' );
        
        foreach ( $pets as $pet ) {
            $last_appointment = $this->appointment_repository->get_last_finished_appointment_for_pet( 
                $client_id, 
                $pet->ID 
            );
            
            if ( ! $last_appointment ) {
                continue;
            }
            
            $appt_date = get_post_meta( $last_appointment->ID, 'appointment_date', true );
            $services = get_post_meta( $last_appointment->ID, 'appointment_services', true );
            
            if ( $appt_date ) {
                $days_since = floor( ( strtotime( $today ) - strtotime( $appt_date ) ) / DAY_IN_SECONDS );
                
                // Sugestão se faz mais de 30 dias
                if ( $days_since >= 30 ) {
                    $pet_name = get_the_title( $pet->ID );
                    $service_name = is_array( $services ) && ! empty( $services ) ? $services[0] : __( 'banho', 'dps-client-portal' );
                    
                    $suggestions[] = [
                        'pet_name'     => $pet_name,
                        'days_since'   => $days_since,
                        'service_name' => $service_name,
                    ];
                }
            }
        }
        
        return $suggestions;
    }
}
