<?php
/**
 * Helper para gerenciar status de TaxiDog na AGENDA.
 *
 * Centraliza a lógica de status de TaxiDog, permitindo rastreamento completo
 * do fluxo de transporte do pet.
 *
 * @package DPS_Agenda_Addon
 * @since 1.2.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_TaxiDog_Helper {

    /**
     * Constantes de status de TaxiDog.
     */
    const STATUS_NONE = 'none';
    const STATUS_REQUESTED = 'requested';
    const STATUS_DRIVER_ON_WAY = 'driver_on_way';
    const STATUS_PET_ON_BOARD = 'pet_on_board';
    const STATUS_COMPLETED = 'completed';

    /**
     * Retorna o status de TaxiDog de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string Status do TaxiDog.
     */
    public static function get_taxidog_status( $appointment_id ) {
        // Verifica se TaxiDog foi solicitado
        $taxidog_requested = get_post_meta( $appointment_id, 'appointment_taxidog', true );
        
        if ( $taxidog_requested !== '1' ) {
            return self::STATUS_NONE;
        }

        // Retorna status detalhado se existir
        $status = get_post_meta( $appointment_id, 'appointment_taxidog_status', true );
        
        // Se não tem status detalhado mas foi solicitado, assume "requested"
        if ( empty( $status ) ) {
            return self::STATUS_REQUESTED;
        }

        return $status;
    }

    /**
     * Atualiza o status de TaxiDog de um agendamento.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $new_status Novo status.
     * @return bool True se atualizado com sucesso.
     */
    public static function update_taxidog_status( $appointment_id, $new_status ) {
        $valid_statuses = [
            self::STATUS_NONE,
            self::STATUS_REQUESTED,
            self::STATUS_DRIVER_ON_WAY,
            self::STATUS_PET_ON_BOARD,
            self::STATUS_COMPLETED,
        ];

        if ( ! in_array( $new_status, $valid_statuses, true ) ) {
            return false;
        }

        // Se está mudando para "none", remove o flag de TaxiDog também
        if ( $new_status === self::STATUS_NONE ) {
            delete_post_meta( $appointment_id, 'appointment_taxidog' );
            delete_post_meta( $appointment_id, 'appointment_taxidog_status' );
        } else {
            // Garante que o flag de TaxiDog está ativo
            update_post_meta( $appointment_id, 'appointment_taxidog', '1' );
            update_post_meta( $appointment_id, 'appointment_taxidog_status', $new_status );
        }

        // AUDITORIA: Registra mudança de status no log
        if ( class_exists( 'DPS_Logger' ) ) {
            DPS_Logger::info(
                sprintf(
                    'Agendamento #%d: Status TaxiDog alterado para "%s" por usuário #%d',
                    $appointment_id,
                    $new_status,
                    get_current_user_id()
                ),
                [
                    'appointment_id' => $appointment_id,
                    'new_taxidog_status' => $new_status,
                    'user_id'        => get_current_user_id(),
                ],
                'agenda_taxidog'
            );
        }

        return true;
    }

    /**
     * Retorna a configuração de badge para um status de TaxiDog.
     *
     * @param string $status Status do TaxiDog.
     * @return array Configuração com 'label', 'class', 'icon', 'color', 'bg'.
     */
    public static function get_taxidog_badge_config( $status ) {
        $config = [
            self::STATUS_NONE => [
                'label' => '',
                'class' => '',
                'icon'  => '',
                'color' => '',
                'bg'    => '',
            ],
            self::STATUS_REQUESTED => [
                'label' => __( 'TaxiDog solicitado', 'dps-agenda-addon' ),
                'class' => 'dps-taxidog-badge--requested',
                'icon'  => '🚗',
                'color' => '#f59e0b',
                'bg'    => '#fef3c7',
            ],
            self::STATUS_DRIVER_ON_WAY => [
                'label' => __( 'Motorista a caminho', 'dps-agenda-addon' ),
                'class' => 'dps-taxidog-badge--on-way',
                'icon'  => '🚗',
                'color' => '#3b82f6',
                'bg'    => '#dbeafe',
            ],
            self::STATUS_PET_ON_BOARD => [
                'label' => __( 'Pet a bordo', 'dps-agenda-addon' ),
                'class' => 'dps-taxidog-badge--on-board',
                'icon'  => '🐾',
                'color' => '#f97316',
                'bg'    => '#ffedd5',
            ],
            self::STATUS_COMPLETED => [
                'label' => __( 'TaxiDog concluído', 'dps-agenda-addon' ),
                'class' => 'dps-taxidog-badge--completed',
                'icon'  => '✅',
                'color' => '#10b981',
                'bg'    => '#d1fae5',
            ],
        ];

        return isset( $config[ $status ] ) ? $config[ $status ] : $config[ self::STATUS_NONE ];
    }

    /**
     * Renderiza badge de status de TaxiDog.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do badge.
     */
    public static function render_taxidog_badge( $appointment_id ) {
        $status = self::get_taxidog_status( $appointment_id );
        
        if ( $status === self::STATUS_NONE ) {
            return '';
        }

        $config = self::get_taxidog_badge_config( $status );

        $html = sprintf(
            '<span class="dps-taxidog-badge %s" style="background: %s; color: %s; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; border: 1px solid %s;">%s %s</span>',
            esc_attr( $config['class'] ),
            esc_attr( $config['bg'] ),
            esc_attr( $config['color'] ),
            esc_attr( $config['color'] ),
            $config['icon'],
            esc_html( $config['label'] )
        );

        return $html;
    }

    /**
     * Retorna as ações rápidas disponíveis para um status de TaxiDog.
     *
     * @param string $current_status Status atual do TaxiDog.
     * @return array Lista de ações disponíveis.
     */
    public static function get_available_actions( $current_status ) {
        $all_actions = [
            'requested' => [
                'label' => __( 'Solicitar TaxiDog', 'dps-agenda-addon' ),
                'icon'  => '🚗',
                'next_status' => self::STATUS_REQUESTED,
            ],
            'driver_on_way' => [
                'label' => __( 'Motorista a caminho', 'dps-agenda-addon' ),
                'icon'  => '🚗',
                'next_status' => self::STATUS_DRIVER_ON_WAY,
            ],
            'pet_on_board' => [
                'label' => __( 'Pet a bordo', 'dps-agenda-addon' ),
                'icon'  => '🐾',
                'next_status' => self::STATUS_PET_ON_BOARD,
            ],
            'completed' => [
                'label' => __( 'Finalizar TaxiDog', 'dps-agenda-addon' ),
                'icon'  => '✅',
                'next_status' => self::STATUS_COMPLETED,
            ],
            'cancel' => [
                'label' => __( 'Cancelar TaxiDog', 'dps-agenda-addon' ),
                'icon'  => '❌',
                'next_status' => self::STATUS_NONE,
            ],
        ];

        // Define quais ações estão disponíveis para cada status
        $available_by_status = [
            self::STATUS_NONE => [ 'requested' ],
            self::STATUS_REQUESTED => [ 'driver_on_way', 'cancel' ],
            self::STATUS_DRIVER_ON_WAY => [ 'pet_on_board', 'cancel' ],
            self::STATUS_PET_ON_BOARD => [ 'completed', 'cancel' ],
            self::STATUS_COMPLETED => [],
        ];

        $available_action_keys = $available_by_status[ $current_status ] ?? [];
        $available_actions = [];

        foreach ( $available_action_keys as $key ) {
            if ( isset( $all_actions[ $key ] ) ) {
                $available_actions[ $key ] = $all_actions[ $key ];
            }
        }

        return $available_actions;
    }

    /**
     * Renderiza botões de ação rápida para TaxiDog.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML dos botões.
     */
    public static function render_taxidog_quick_actions( $appointment_id ) {
        $status = self::get_taxidog_status( $appointment_id );
        $actions = self::get_available_actions( $status );

        if ( empty( $actions ) ) {
            return '';
        }

        $html = '<div class="dps-taxidog-actions" style="display: inline-flex; gap: 0.25rem; margin-left: 0.5rem;">';

        foreach ( $actions as $action_key => $action ) {
            $button_class = 'dps-taxidog-action-btn';
            if ( $action_key === 'cancel' ) {
                $button_class .= ' dps-taxidog-action-btn--danger';
            }

            $html .= sprintf(
                '<button class="%s" data-appt-id="%d" data-action="%s" title="%s" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border: none; background: #f3f4f6; color: #374151; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;">%s</button>',
                esc_attr( $button_class ),
                esc_attr( $appointment_id ),
                esc_attr( $action['next_status'] ),
                esc_attr( $action['label'] ),
                $action['icon']
            );
        }

        $html .= '</div>';

        return $html;
    }
}
