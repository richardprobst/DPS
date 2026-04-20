<?php
/**
 * Helper para consolidar status e histórico operacional de pagamento.
 *
 * @package DPS_Agenda_Addon
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_Payment_Helper {

    /**
     * Meta key do histórico operacional de cobranças.
     */
    private const ATTEMPTS_META_KEY = '_dps_payment_attempts';

    /**
     * Retorna o status consolidado de pagamento de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    public static function get_payment_status( $appointment_id ) {
        $status = get_post_meta( $appointment_id, 'appointment_status', true );
        if ( 'finalizado_pago' === $status ) {
            return 'paid';
        }

        $payment_link = get_post_meta( $appointment_id, 'dps_payment_link', true );
        $link_status  = get_post_meta( $appointment_id, '_dps_payment_link_status', true );

        if ( ! empty( $payment_link ) ) {
            if ( 'error' === $link_status ) {
                return 'error';
            }

            return 'pending';
        }

        if ( 'error' === $link_status ) {
            return 'error';
        }

        return 'not_requested';
    }

    /**
     * Retorna a configuração visual do badge de pagamento.
     *
     * @param string $status Status padronizado.
     * @return array
     */
    public static function get_payment_badge_config( $status ) {
        $config = [
            'paid' => [
                'label' => __( 'Pago', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--paid',
                'icon'  => '✓',
                'color' => '#0f5f39',
                'bg'    => '#d8f1e3',
            ],
            'pending' => [
                'label' => __( 'Aguardando pagamento', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--pending',
                'icon'  => '●',
                'color' => '#8a6710',
                'bg'    => '#f4e8bf',
            ],
            'error' => [
                'label' => __( 'Falha na cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--error',
                'icon'  => '!',
                'color' => '#a7392f',
                'bg'    => '#f7d9d4',
            ],
            'not_requested' => [
                'label' => __( 'Sem cobrança', 'dps-agenda-addon' ),
                'class' => 'dps-payment-badge--none',
                'icon'  => '–',
                'color' => '#5d6670',
                'bg'    => '#edf0f2',
            ],
        ];

        return isset( $config[ $status ] ) ? $config[ $status ] : $config['not_requested'];
    }

    /**
     * Registra uma tentativa de reenvio ou cobrança manual.
     *
     * @param int    $appointment_id ID do agendamento.
     * @param string $type           Tipo da tentativa.
     * @param string $result         Resultado: success|error.
     * @param string $message        Mensagem curta de retorno.
     * @return array
     */
    public static function record_payment_attempt( $appointment_id, $type, $result, $message = '' ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return [];
        }

        $current_user = wp_get_current_user();
        $attempts     = self::get_payment_attempts( $appointment_id );
        $attempt      = [
            'timestamp'     => current_time( 'mysql' ),
            'operator_id'   => get_current_user_id(),
            'operator_name' => $current_user instanceof WP_User ? $current_user->display_name : '',
            'type'          => sanitize_key( $type ),
            'result'        => ( 'success' === $result ) ? 'success' : 'error',
            'message'       => sanitize_text_field( $message ),
        ];

        $attempts[] = $attempt;
        update_post_meta( $appointment_id, self::ATTEMPTS_META_KEY, $attempts );

        return $attempt;
    }

    /**
     * Retorna o histórico de tentativas de pagamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @param int $limit          Limite opcional.
     * @return array
     */
    public static function get_payment_attempts( $appointment_id, $limit = 0 ) {
        $attempts = get_post_meta( $appointment_id, self::ATTEMPTS_META_KEY, true );
        if ( ! is_array( $attempts ) ) {
            return [];
        }

        $attempts = array_values(
            array_filter(
                array_map( [ __CLASS__, 'normalize_attempt' ], $attempts )
            )
        );

        usort(
            $attempts,
            static function( $left, $right ) {
                return strcmp( (string) $right['timestamp'], (string) $left['timestamp'] );
            }
        );

        if ( $limit > 0 ) {
            return array_slice( $attempts, 0, $limit );
        }

        return $attempts;
    }

    /**
     * Retorna detalhes do pagamento para tooltip e painéis operacionais.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array
     */
    public static function get_payment_details( $appointment_id ) {
        $details = [
            'has_details'   => false,
            'link_url'      => '',
            'last_attempt'  => '',
            'error_message' => '',
            'attempts'      => [],
        ];

        $payment_link = get_post_meta( $appointment_id, 'dps_payment_link', true );
        if ( ! empty( $payment_link ) ) {
            $details['has_details'] = true;
            $details['link_url']    = $payment_link;
        }

        $link_status = get_post_meta( $appointment_id, '_dps_payment_link_status', true );
        if ( 'error' === $link_status ) {
            $details['has_details']   = true;
            $details['error_message'] = __( 'Falha ao gerar ou reenviar o link de pagamento.', 'dps-agenda-addon' );
        }

        $last_error = get_post_meta( $appointment_id, '_dps_payment_last_error', true );
        if ( ! empty( $last_error ) ) {
            $details['has_details']   = true;
            $details['error_message'] = sanitize_text_field( (string) $last_error );
        }

        $attempts = self::get_payment_attempts( $appointment_id, 3 );
        if ( ! empty( $attempts ) ) {
            $details['has_details']  = true;
            $details['attempts']     = $attempts;
            $details['last_attempt'] = self::get_attempt_summary_label( $attempts[0] );
        }

        return $details;
    }

    /**
     * Renderiza o badge principal de pagamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    public static function render_payment_badge( $appointment_id ) {
        $status = self::get_payment_status( $appointment_id );
        $config = self::get_payment_badge_config( $status );

        return sprintf(
            '<span class="dps-payment-badge %s" style="background:%s;color:%s;padding:0.25rem 0.5rem;border-radius:0;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:0.25rem;">%s %s</span>',
            esc_attr( $config['class'] ),
            esc_attr( $config['bg'] ),
            esc_attr( $config['color'] ),
            esc_html( $config['icon'] ),
            esc_html( $config['label'] )
        );
    }

    /**
     * Renderiza tooltip com link, erro e histórico recente.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    public static function render_payment_tooltip( $appointment_id ) {
        $details = self::get_payment_details( $appointment_id );
        if ( ! $details['has_details'] ) {
            return '';
        }

        $html = '<div class="dps-payment-tooltip" style="display:none;">';

        if ( ! empty( $details['link_url'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item">';
            $html .= '<strong>' . esc_html__( 'Link de pagamento:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= '<a href="' . esc_url( $details['link_url'] ) . '" target="_blank" rel="noopener" style="word-break:break-all;font-size:0.75rem;">' . esc_html( $details['link_url'] ) . '</a>';
            $html .= '</div>';
        }

        if ( ! empty( $details['error_message'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item" style="color:#a7392f;">';
            $html .= '<strong>' . esc_html__( 'Erro:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= esc_html( $details['error_message'] );
            $html .= '</div>';
        }

        if ( ! empty( $details['last_attempt'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item">';
            $html .= '<strong>' . esc_html__( 'Última tentativa:', 'dps-agenda-addon' ) . '</strong><br>';
            $html .= esc_html( $details['last_attempt'] );
            $html .= '</div>';
        }

        if ( ! empty( $details['attempts'] ) ) {
            $html .= '<div class="dps-payment-tooltip__item">';
            $html .= '<strong>' . esc_html__( 'Histórico recente:', 'dps-agenda-addon' ) . '</strong>';
            $html .= '<ul class="dps-payment-tooltip__history">';

            foreach ( $details['attempts'] as $attempt ) {
                $item_class = ( 'success' === $attempt['result'] ) ? 'is-success' : 'is-error';
                $html      .= '<li class="' . esc_attr( $item_class ) . '">';
                $html      .= '<span>' . esc_html( self::get_attempt_summary_label( $attempt ) ) . '</span>';
                if ( ! empty( $attempt['message'] ) ) {
                    $html .= '<small>' . esc_html( $attempt['message'] ) . '</small>';
                }
                $html .= '</li>';
            }

            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Renderiza o botão de reenvio quando aplicável.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string
     */
    public static function render_resend_button( $appointment_id ) {
        $payment_status = self::get_payment_status( $appointment_id );
        if ( ! in_array( $payment_status, [ 'pending', 'error' ], true ) ) {
            return '';
        }

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
     * Renderiza um resumo inline do histórico recente.
     *
     * @param int $appointment_id ID do agendamento.
     * @param int $limit          Número máximo de itens.
     * @return string
     */
    public static function render_payment_attempt_summary( $appointment_id, $limit = 2 ) {
        $attempts = self::get_payment_attempts( $appointment_id, $limit );
        if ( empty( $attempts ) ) {
            return '';
        }

        $html = '<div class="dps-payment-attempt-summary">';
        $html .= '<span class="dps-payment-attempt-summary__label">' . esc_html__( 'Reenvios recentes', 'dps-agenda-addon' ) . '</span>';
        $html .= '<ul class="dps-payment-attempt-summary__list">';

        foreach ( $attempts as $attempt ) {
            $item_class = ( 'success' === $attempt['result'] ) ? 'is-success' : 'is-error';
            $html      .= '<li class="' . esc_attr( $item_class ) . '">';
            $html      .= '<span>' . esc_html( self::get_attempt_summary_label( $attempt ) ) . '</span>';
            if ( ! empty( $attempt['message'] ) ) {
                $html .= '<small>' . esc_html( $attempt['message'] ) . '</small>';
            }
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Verifica se existe cobrança pendente para o agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return bool
     */
    public static function has_pending_payment( $appointment_id ) {
        return in_array( self::get_payment_status( $appointment_id ), [ 'pending', 'error' ], true );
    }

    /**
     * Normaliza uma tentativa bruta lida do meta.
     *
     * @param mixed $attempt Dados brutos.
     * @return array|null
     */
    private static function normalize_attempt( $attempt ) {
        if ( ! is_array( $attempt ) || empty( $attempt['timestamp'] ) ) {
            return null;
        }

        return [
            'timestamp'     => sanitize_text_field( (string) $attempt['timestamp'] ),
            'operator_id'   => isset( $attempt['operator_id'] ) ? absint( $attempt['operator_id'] ) : 0,
            'operator_name' => isset( $attempt['operator_name'] ) ? sanitize_text_field( (string) $attempt['operator_name'] ) : '',
            'type'          => isset( $attempt['type'] ) ? sanitize_key( (string) $attempt['type'] ) : 'manual',
            'result'        => ( isset( $attempt['result'] ) && 'success' === $attempt['result'] ) ? 'success' : 'error',
            'message'       => isset( $attempt['message'] ) ? sanitize_text_field( (string) $attempt['message'] ) : '',
        ];
    }

    /**
     * Formata o resumo curto de uma tentativa.
     *
     * @param array $attempt Tentativa normalizada.
     * @return string
     */
    private static function get_attempt_summary_label( $attempt ) {
        $pieces = [];

        if ( ! empty( $attempt['timestamp'] ) ) {
            $pieces[] = mysql2date( 'd/m/Y H:i', $attempt['timestamp'] );
        }

        $pieces[] = ( 'success' === $attempt['result'] )
            ? __( 'Sucesso', 'dps-agenda-addon' )
            : __( 'Falha', 'dps-agenda-addon' );

        if ( ! empty( $attempt['operator_name'] ) ) {
            $pieces[] = $attempt['operator_name'];
        }

        return implode( ' · ', array_filter( $pieces ) );
    }
}
