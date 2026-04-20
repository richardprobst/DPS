<?php
/**
 * Helper para geração de rotas GPS na AGENDA.
 *
 * Centraliza a lógica de construção de URLs do Google Maps para rotas,
 * SEMPRE do endereço do Banho e Tosa até o endereço do cliente.
 *
 * @package DPS_Agenda_Addon
 * @since 1.2.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DPS_Agenda_GPS_Helper {

    /**
     * Retorna o endereço do Banho e Tosa (loja).
     *
     * Tenta obter o endereço configurado nas opções. Se não existir,
     * retorna um endereço padrão vazio.
     *
     * @return string Endereço da loja.
     */
    public static function get_shop_address() {
        // Tenta obter da configuração do sistema
        $address = get_option( 'dps_shop_address', '' );

        // Fallback: tenta obter do endereço de negócio geral
        if ( empty( $address ) ) {
            $address = get_option( 'dps_business_address', '' );
        }

        // Aplica filtro para permitir customização
        $address = apply_filters( 'dps_agenda_shop_address', $address );

        return trim( $address );
    }

    /**
     * Retorna o endereço do cliente de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string Endereço do cliente ou string vazia.
     */
    public static function get_client_address( $appointment_id ) {
        // Obtém o ID do cliente do agendamento
        $client_id = get_post_meta( $appointment_id, 'appointment_client_id', true );

        if ( empty( $client_id ) ) {
            return '';
        }

        // Tenta obter endereço em texto
        $address = get_post_meta( $client_id, 'client_address', true );

        // Se não tem endereço em texto, tenta coordenadas
        if ( empty( $address ) ) {
            $lat = get_post_meta( $client_id, 'client_lat', true );
            $lng = get_post_meta( $client_id, 'client_lng', true );

            if ( ! empty( $lat ) && ! empty( $lng ) ) {
                $address = $lat . ',' . $lng;
            }
        }

        return trim( $address );
    }

    /**
     * Monta a URL de rota do Google Maps.
     *
     * IMPORTANTE: SEMPRE monta a rota do Banho e Tosa até o cliente.
     * Não implementa o trajeto inverso nesta fase.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string URL do Google Maps ou string vazia se não houver dados suficientes.
     */
    public static function get_route_url( $appointment_id ) {
        $shop_address = self::get_shop_address();
        $client_address = self::get_client_address( $appointment_id );

        // Se não tem endereço da loja ou do cliente, não pode gerar rota
        if ( empty( $shop_address ) || empty( $client_address ) ) {
            return '';
        }

        // Monta URL do Google Maps Directions
        // Formato: https://www.google.com/maps/dir/?api=1&origin=ORIGEM&destination=DESTINO
        $route_url = 'https://www.google.com/maps/dir/?api=1' .
                     '&origin=' . urlencode( $shop_address ) .
                     '&destination=' . urlencode( $client_address ) .
                     '&travelmode=driving';

        return $route_url;
    }

    /**
     * Renderiza botão "Abrir rota" se houver dados suficientes.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do botão ou string vazia.
     */
    public static function render_route_button( $appointment_id ) {
        $route_url = self::get_route_url( $appointment_id );

        if ( empty( $route_url ) ) {
            return '';
        }

        $html = sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="dps-route-btn">%s</a>',
            esc_url( $route_url ),
            esc_html__( 'Abrir rota', 'dps-agenda-addon' )
        );

        return $html;
    }

    /**
     * Renderiza link de mapa simples (apenas destino, sem rota).
     *
     * Mantido para compatibilidade com o código existente.
     *
     * @param int $appointment_id ID do agendamento.
     * @return string HTML do link ou string vazia.
     */
    public static function render_map_link( $appointment_id ) {
        $client_address = self::get_client_address( $appointment_id );

        if ( empty( $client_address ) ) {
            return '';
        }

        // URL simples de busca no Google Maps (apenas destino)
        $map_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $client_address );

        $html = sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="dps-taxidog-map-btn">%s</a>',
            esc_url( $map_url ),
            esc_html__( 'Mapa', 'dps-agenda-addon' )
        );

        return $html;
    }

    /**
     * Verifica se a configuração de endereço da loja está definida.
     *
     * @return bool True se configurado.
     */
    public static function is_shop_address_configured() {
        $address = self::get_shop_address();
        return ! empty( $address );
    }

    /**
     * Renderiza aviso de configuração se o endereço da loja não estiver definido.
     *
     * @return string HTML do aviso ou string vazia.
     */
    public static function render_configuration_notice() {
        if ( self::is_shop_address_configured() ) {
            return '';
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return '';
        }

        $settings_url = admin_url( 'admin.php?page=dps-settings' );

        $html = '<div class="notice notice-warning dps-agenda-admin-notice dps-agenda-admin-notice--warning">';
        $html .= '<p><strong>' . esc_html__( 'Configuração necessária:', 'dps-agenda-addon' ) . '</strong> ';
        $html .= esc_html__( 'Para usar o botão "Abrir rota", configure o endereço do Banho e Tosa em', 'dps-agenda-addon' );
        $html .= ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Configurações', 'dps-agenda-addon' ) . '</a>.</p>';
        $html .= '</div>';

        return $html;
    }
}
