<?php
/**
 * Sugestões Proativas de Agendamento para AI Add-on.
 *
 * Responsável por:
 * - Detectar quando um cliente não tem agendamento recente
 * - Sugerir novo agendamento de forma proativa durante conversas
 * - Controlar frequência de sugestões para não ser invasivo
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de sugestões proativas de agendamento.
 */
class DPS_AI_Proactive_Scheduler {

    /**
     * Meta key para armazenar última sugestão exibida.
     *
     * @var string
     */
    const META_LAST_SUGGESTION = '_dps_ai_last_scheduling_suggestion';

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Proactive_Scheduler|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Proactive_Scheduler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        // Nenhum hook necessário, apenas métodos auxiliares
    }

    /**
     * Verifica se deve sugerir agendamento para um cliente.
     *
     * @param int    $client_id ID do cliente.
     * @param string $context   Contexto da conversa ('portal', 'whatsapp', 'general').
     *
     * @return array|false Array com sugestão ou false se não deve sugerir.
     */
    public function should_suggest_appointment( $client_id, $context = 'general' ) {
        if ( ! $client_id ) {
            return false;
        }

        // Verifica se sugestões estão habilitadas
        $settings = get_option( 'dps_ai_settings', [] );
        
        if ( empty( $settings['proactive_scheduling_enabled'] ) ) {
            return false;
        }

        // Verifica se já sugeriu recentemente (evita ser invasivo)
        if ( $this->suggested_recently( $client_id ) ) {
            return false;
        }

        // Busca último agendamento do cliente
        $last_appointment = $this->get_last_appointment( $client_id );

        if ( ! $last_appointment ) {
            // Cliente novo ou sem agendamentos - sugere de forma genérica
            return [
                'type'    => 'first_time',
                'message' => $this->get_first_time_suggestion( $settings ),
            ];
        }

        // Calcula há quantos dias foi o último serviço
        $days_since = $this->calculate_days_since( $last_appointment['date'] );

        // Obtém intervalo configurado (padrão: 28 dias / 4 semanas)
        $suggestion_interval = isset( $settings['proactive_scheduling_interval'] ) 
            ? (int) $settings['proactive_scheduling_interval'] 
            : 28;

        // Se passou do intervalo, sugere agendamento
        if ( $days_since >= $suggestion_interval ) {
            return [
                'type'           => 'recurring',
                'days_since'     => $days_since,
                'weeks_since'    => floor( $days_since / 7 ),
                'last_service'   => $last_appointment['type'],
                'pet_name'       => $last_appointment['pet_name'],
                'message'        => $this->get_recurring_suggestion( $last_appointment, $days_since, $settings ),
            ];
        }

        return false;
    }

    /**
     * Adiciona sugestão proativa a uma resposta da IA.
     *
     * @param string $ai_response Resposta original da IA.
     * @param int    $client_id   ID do cliente.
     * @param string $context     Contexto ('portal', 'whatsapp', etc.).
     *
     * @return string Resposta com sugestão adicionada (se aplicável).
     */
    public function append_suggestion_to_response( $ai_response, $client_id, $context = 'general' ) {
        $suggestion = $this->should_suggest_appointment( $client_id, $context );

        if ( ! $suggestion ) {
            return $ai_response;
        }

        // Marca que sugeriu agora
        $this->mark_suggestion_shown( $client_id );

        // Adiciona sugestão à resposta
        $separator = "\n\n---\n\n";
        $enhanced_response = $ai_response . $separator . $suggestion['message'];

        return $enhanced_response;
    }

    /**
     * Busca último agendamento de um cliente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return array|false Dados do último agendamento ou false.
     */
    private function get_last_appointment( $client_id ) {
        $args = [
            'post_type'      => 'dps_agendamento',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => 'appointment_date',
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
            'fields'         => 'ids',
        ];

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return false;
        }

        $appt_id = $query->posts[0];

        // Obtém dados do agendamento
        $date      = get_post_meta( $appt_id, 'appointment_date', true );
        $type      = get_post_meta( $appt_id, 'appointment_type', true );
        $pet_id    = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet_ids   = get_post_meta( $appt_id, 'appointment_pet_ids', true );

        // Determina nome do pet
        $pet_name = '';
        if ( $pet_id ) {
            $pet_post = get_post( $pet_id );
            $pet_name = $pet_post ? $pet_post->post_title : '';
        } elseif ( is_array( $pet_ids ) && ! empty( $pet_ids ) ) {
            $first_pet = get_post( $pet_ids[0] );
            $pet_name  = $first_pet ? $first_pet->post_title : '';
        }

        return [
            'id'       => $appt_id,
            'date'     => $date,
            'type'     => $type,
            'pet_id'   => $pet_id,
            'pet_name' => $pet_name,
        ];
    }

    /**
     * Calcula há quantos dias foi uma data.
     *
     * @param string $date Data no formato Y-m-d.
     *
     * @return int Número de dias.
     */
    private function calculate_days_since( $date ) {
        if ( empty( $date ) ) {
            return 0;
        }

        $appointment_time = strtotime( $date );
        $current_time     = current_time( 'timestamp' );

        $diff_seconds = $current_time - $appointment_time;
        $diff_days    = floor( $diff_seconds / DAY_IN_SECONDS );

        return max( 0, $diff_days );
    }

    /**
     * Verifica se já sugeriu agendamento recentemente.
     *
     * @param int $client_id ID do cliente.
     *
     * @return bool True se sugeriu recentemente.
     */
    private function suggested_recently( $client_id ) {
        $last_suggestion = get_user_meta( $client_id, self::META_LAST_SUGGESTION, true );

        if ( empty( $last_suggestion ) ) {
            return false;
        }

        $settings = get_option( 'dps_ai_settings', [] );
        
        // Intervalo mínimo entre sugestões (padrão: 7 dias)
        $cooldown_days = isset( $settings['proactive_scheduling_cooldown'] ) 
            ? (int) $settings['proactive_scheduling_cooldown'] 
            : 7;

        $days_since_suggestion = $this->calculate_days_since( $last_suggestion );

        return $days_since_suggestion < $cooldown_days;
    }

    /**
     * Marca que uma sugestão foi exibida.
     *
     * @param int $client_id ID do cliente.
     */
    private function mark_suggestion_shown( $client_id ) {
        $today = current_time( 'Y-m-d' );
        update_user_meta( $client_id, self::META_LAST_SUGGESTION, $today );
    }

    /**
     * Gera mensagem de sugestão para cliente novo.
     *
     * @param array $settings Configurações do plugin.
     *
     * @return string Mensagem de sugestão.
     */
    private function get_first_time_suggestion( $settings ) {
        $custom_message = isset( $settings['proactive_scheduling_first_time_message'] ) 
            ? $settings['proactive_scheduling_first_time_message'] 
            : '';

        if ( ! empty( $custom_message ) ) {
            return $custom_message;
        }

        return '🐾 **Que tal agendar um horário para o banho e tosa do seu pet?** Posso te ajudar a encontrar um horário que funcione melhor para você!';
    }

    /**
     * Gera mensagem de sugestão para cliente recorrente.
     *
     * @param array $appointment Dados do último agendamento.
     * @param int   $days_since  Dias desde o último serviço.
     * @param array $settings    Configurações do plugin.
     *
     * @return string Mensagem de sugestão.
     */
    private function get_recurring_suggestion( $appointment, $days_since, $settings ) {
        $custom_message = isset( $settings['proactive_scheduling_recurring_message'] ) 
            ? (string) $settings['proactive_scheduling_recurring_message'] 
            : '';

        if ( ! empty( $custom_message ) ) {
            // Substitui variáveis dinâmicas
            $message = str_replace( '{pet_name}', (string) $appointment['pet_name'], $custom_message );
            $message = str_replace( '{weeks}', (string) floor( $days_since / 7 ), $message );
            $message = str_replace( '{service}', (string) $appointment['type'], $message );
            return $message;
        }

        $weeks = floor( $days_since / 7 );
        $pet_name = ! empty( $appointment['pet_name'] ) ? $appointment['pet_name'] : 'seu pet';

        if ( $weeks <= 4 ) {
            return sprintf(
                '🐾 **Observei que já faz %d %s desde o último serviço do %s.** Gostaria que eu te ajudasse a agendar um novo horário?',
                $weeks,
                $weeks === 1 ? 'semana' : 'semanas',
                $pet_name
            );
        }

        return sprintf(
            '🐾 **Notei que o %s está há %d semanas sem um banho e tosa!** Que tal agendar um horário? Posso te ajudar com isso!',
            $pet_name,
            $weeks
        );
    }
}
