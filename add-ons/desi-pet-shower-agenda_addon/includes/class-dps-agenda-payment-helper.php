<?php
/**
 * Helper para consolidar status de pagamento na AGENDA.
 *
 * Centraliza a lógica de obtenção de status de pagamento, evitando duplicação
 * de código entre diferentes componentes da agenda.
 *
 * @package DPS_Agenda_Addon
 * @since 1.2.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Payment_Helper {

    /**
     * Retorna o status consolidado de pagamento de um agendamento.
     *
     * Mapeia os diferentes estados possíveis para valores padronizados:
     * - 'paid': Pagamento confirmado
     * - 'pending': Link enviado, aguardando pagamento
     * - 'error': Erro na geração do link
     * - 'not_requested': Nenhuma tentativa de cobrança ainda
     *
     * @param int $appointment_id ID do agendamento.
     * @return string Status consolidado.
     */
    public static function get_payment_status( $appointment_id ) {
        // Verifica status do agendamento
        $status = get_post_meta( $appointment_id, 'appointment_status', true );
        
        // Se já está pago, retorna imediatamente
        if ( $status === 'finalizado_pago' ) {
            return 'paid';
        }
        
        // Verifica se tem link de pagamento gerado
        $payment_link = get_post_meta( $appointment_id, 'dps_payment_link', true );
        $link_status = get_post_meta( $appointment_id, '_dps_payment_link_status', true );
        
        // Se tem link gerado
        if ( ! empty( $payment_link ) ) {
            // Verifica se foi marcado como erro
            if ( $link_status === 'error' ) {
                return 'error';
            }
            return 'pending';
        }
        
        // Se marcou erro mas não tem link
        if ( $link_status === 'error' ) {
            return 'error';
        }
        
        // Nenhuma cobrança ainda
        return 'not_requested';
    }

    /**
     * Retorna a configuração de badge para um status de pagamento.
     *
     * @param string $status Status retornado por get_payment_status().
     * @return array Configuração com 'label', 'class', 'icon'.
     */
    public static function get_payment_badge_config( $status ) {
        $config = [
            'paid' => [
                'label' => __( 'Pago', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--paid',
                'icon'  => '✅',
                'color' => '#10b981',
                'bg'    => '#d1fae5',
            ],
            'pending' => [
                'label' => __( 'Aguardando pagamento', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--pending',
                'icon'  => '⏳',
                'color' => '#f59e0b',
                'bg'    => '#fef3c7',
            ],
            'error' => [
                'label' => __( 'Erro na cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--error',
                'icon'  => '⚠️',
                'color' => '#ef4444',
                'bg'    => '#fee2e2',
            ],
            'not_requested' => [
                'label' => __( 'Sem cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--none',
                'icon'  => '–',
                'color' => '#6b7280',
                'bg'    => '#f3f4f6',
            ],
        ];

        return isset( $config[ $status ] ) ? $config[ $status ] : $config['not_requested'];
    }

    /**
     * Retorna detalhes de pagamento para tooltip/popover.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Detalhes com 'has_details', 'link_url', 'last_attempt', 'error_message'.
     */
    public static function get_payment_details( $appointment_id ) {
        $details = [
            'has_details'   => false,
            'link_url'      => '',
            'last_attempt'  => '',
            'error_message' => '',
        ];

        // Link de pagamento
        $payment_link = get_post_meta( $appointment_id, 'dps_payment_link', true );
        if ( ! empty( $payment_link ) ) {
            $details['has_details'] = true;
            $details['link_url'] = $payment_link;
        }

        // Status do link
        $link_status = get_post_meta( $appointment_id, '_dps_payment_link_status', true );
        if ( $link_status === 'error' ) {
            $details['has_details'] = true;
            $details['error_message'] = __( 'Falha ao gerar link de pagamento. Verifique a configuração do Mercado Pago.', 'dps-agenda-addon' );
        }

        // TODO: Adicionar histórico de tentativas quando implementado
        // $attempts = get_post_meta( $appointment_id, '_dps_payment_attempts', true );
        
        return $details;
    }

    /**
     * Renderiza badge de status de pagamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do badge.
     */
    public static function render_payment_badge( $appointment_id ) {
        $status = self::get_payment_status( $appointment_id );
        $config = self::get_payment_badge_config( $status );

        $html = sprintf(
            '<span class="dps-payment-badge %s" style="background: %s; color: %s; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">%s %s</span>',
            esc_attr( $config['class'] ),
            esc_attr( $config['bg'] ),
            esc_attr( $config['color'] ),
            $config['icon'],
            esc_html( $config['label'] )
        );

        return $html;
    }

    /**
     * Renderiza tooltip com detalhes de pagamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do tooltip.
     */
    public static function render_payment_tooltip( $appointment_id ) {
        $details = self::get_payment_details( $appointment_id );
        
        if ( ! $details['has_details'] ) {
            return '';
        }

        $html = '<div class="dps-payment-tooltip" style="display: none;">';
        
        if ( ! empty( $details['link_url'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item">';
            $html .= '<strong>' . esc_html__( 'Link de pagamento:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= '<a href="' . esc_url( $details['link_url'] ) . '" target="_blank" style="word-break: break-all; font-size: 0.75rem;">' . esc_html( $details['link_url'] ) . '</a>';
            $html .= '</div>';
        }

        if ( ! empty( $details['error_message'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item" style="color: #ef4444;">';
            $html .= '<strong>' . esc_html__( 'Erro:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= esc_html( $details['error_message'] );
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
