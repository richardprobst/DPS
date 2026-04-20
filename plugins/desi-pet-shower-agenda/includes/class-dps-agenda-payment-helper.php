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
     * @return array Configuração com 'label' e 'class'.
     */
    public static function get_payment_badge_config( $status ) {
        $config = [
            'paid' => [
                'label' => __( 'Pago', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--paid',
            ],
            'pending' => [
                'label' => __( 'Aguardando pagamento', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--pending',
            ],
            'error' => [
                'label' => __( 'Erro na cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--error',
            ],
            'not_requested' => [
                'label' => __( 'Sem cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--none',
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
            '<span class="dps-payment-badge %s">%s</span>',
            esc_attr( $config['class'] ),
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

        $html = '<div class="dps-payment-tooltip" hidden>';
        
        if ( ! empty( $details['link_url'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item">';
            $html .= '<strong>' . esc_html__( 'Link de pagamento:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= '<a class="dps-payment-tooltip__link" href="' . esc_url( $details['link_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $details['link_url'] ) . '</a>';
            $html .= '</div>';
        }

        if ( ! empty( $details['error_message'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item dps-payment-tooltip__item--error">';
            $html .= '<strong>' . esc_html__( 'Erro:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= esc_html( $details['error_message'] );
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Renderiza botão "Reenviar link de pagamento" se aplicável.
     *
     * @since 1.5.0
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do botão ou string vazia.
     */
    public static function render_resend_button( $appointment_id ) {
        $payment_status = self::get_payment_status( $appointment_id );
        
        // Só mostra botão se tiver pagamento pendente ou com erro
        if ( ! in_array( $payment_status, [ 'pending', 'error' ], true ) ) {
            return '';
        }
        
        // Verifica se tem link de pagamento (necessário para reenvio)
        $payment_link = get_post_meta( $appointment_id, 'dps_payment_link', true );
        if ( empty( $payment_link ) ) {
            return '';
        }
        
        ob_start();
        ?>
        <button type="button" 
                class="dps-resend-payment-btn" 
                data-appt-id="<?php echo esc_attr( $appointment_id ); ?>"
                title="<?php esc_attr_e( 'Reenviar link de pagamento', 'dps-agenda-addon' ); ?>">
            <?php esc_html_e( 'Reenviar', 'dps-agenda-addon' ); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Verifica se um agendamento tem pagamento pendente.
     *
     * @since 1.5.0
     * @param int $appointment_id ID do agendamento.
     * @return bool True se tem pagamento pendente ou com erro.
     */
    public static function has_pending_payment( $appointment_id ) {
        $status = self::get_payment_status( $appointment_id );
        return in_array( $status, [ 'pending', 'error' ], true );
    }
}
