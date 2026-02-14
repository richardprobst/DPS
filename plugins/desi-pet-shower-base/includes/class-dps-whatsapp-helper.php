<?php
/**
 * Helper centralizado para geração de links WhatsApp
 *
 * Centraliza a criação de URLs do WhatsApp com mensagens personalizadas
 * para diferentes contextos do sistema (cliente para equipe, equipe para cliente).
 *
 * @package DPSbyPRObst
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe helper para operações com links WhatsApp
 */
class DPS_WhatsApp_Helper {

    /**
     * Número padrão da equipe desi.pet by PRObst
     * +55 15 99160-6299
     */
    const TEAM_PHONE = '5515991606299';

    /**
     * Gera link WhatsApp para o cliente enviar mensagem à equipe
     *
     * Usado quando o cliente quer entrar em contato com a equipe
     * (ex: solicitar acesso ao portal, tirar dúvidas, agendar serviço)
     *
     * @param string $message Mensagem pré-preenchida (opcional)
     * @return string URL do WhatsApp
     *
     * @example
     * DPS_WhatsApp_Helper::get_link_to_team('Olá, gostaria de agendar um banho')
     * // retorna 'https://wa.me/5515991606299?text=...'
     */
    public static function get_link_to_team( $message = '' ) {
        $phone = self::get_team_phone();
        
        if ( empty( $message ) ) {
            return 'https://wa.me/' . $phone;
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $message );
    }

    /**
     * Gera link WhatsApp para a equipe enviar mensagem ao cliente
     *
     * Usado quando a equipe quer contatar o cliente
     * (ex: confirmação de agendamento, cobrança, envio de link do portal)
     *
     * @param string $client_phone Telefone do cliente (será formatado automaticamente)
     * @param string $message      Mensagem pré-preenchida (opcional)
     * @return string URL do WhatsApp ou string vazia se telefone inválido
     *
     * @example
     * DPS_WhatsApp_Helper::get_link_to_client('(15) 98765-4321', 'Seu agendamento foi confirmado!')
     * // retorna 'https://wa.me/5515987654321?text=...'
     */
    public static function get_link_to_client( $client_phone, $message = '' ) {
        if ( empty( $client_phone ) ) {
            return '';
        }

        // Formata o número usando helper global
        if ( class_exists( 'DPS_Phone_Helper' ) ) {
            $formatted_phone = DPS_Phone_Helper::format_for_whatsapp( $client_phone );
        } else {
            // Fallback: remove caracteres não numéricos
            $formatted_phone = preg_replace( '/\D/', '', $client_phone );
            // Adiciona código do país apenas se número brasileiro (10-11 dígitos) sem código
            if ( strlen( $formatted_phone ) >= 10 && strlen( $formatted_phone ) <= 11 ) {
                // Apenas adiciona 55 se não começar com código de país (assumindo que números com mais de 11 dígitos já têm código)
                $formatted_phone = '55' . $formatted_phone;
            }
        }

        if ( empty( $formatted_phone ) ) {
            return '';
        }

        if ( empty( $message ) ) {
            return 'https://wa.me/' . $formatted_phone;
        }

        return 'https://wa.me/' . $formatted_phone . '?text=' . rawurlencode( $message );
    }

    /**
     * Gera link WhatsApp para compartilhamento genérico
     *
     * Abre WhatsApp sem número específico, apenas com mensagem.
     * Usado quando o cliente quer compartilhar algo (ex: foto do pet)
     *
     * @param string $message Mensagem a ser compartilhada
     * @return string URL do WhatsApp
     *
     * @example
     * DPS_WhatsApp_Helper::get_share_link('Olha a foto do meu pet após o banho!')
     * // retorna 'https://wa.me/?text=...'
     */
    public static function get_share_link( $message ) {
        if ( empty( $message ) ) {
            return 'https://wa.me/';
        }

        return 'https://wa.me/?text=' . rawurlencode( $message );
    }

    /**
     * Obtém o número de telefone da equipe
     *
     * Permite filtro para customização via hook, mas usa constante como padrão.
     * Também suporta configuração via option 'dps_whatsapp_number'.
     *
     * @return string Número formatado da equipe
     */
    public static function get_team_phone() {
        // Permite configuração via option (usado pelo Communications add-on ou settings)
        $configured_phone = get_option( 'dps_whatsapp_number', '' );
        
        if ( ! empty( $configured_phone ) ) {
            // Formata o número configurado
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $formatted = DPS_Phone_Helper::format_for_whatsapp( $configured_phone );
                if ( ! empty( $formatted ) ) {
                    return $formatted;
                }
            }
        }

        // Usa constante padrão
        $phone = self::TEAM_PHONE;

        // Permite filtro para customização
        return apply_filters( 'dps_team_whatsapp_number', $phone );
    }

    /**
     * Valida se o link do portal está correto antes de enviar
     *
     * Verifica se a URL contém token válido e se a página do portal existe
     *
     * @param string $portal_url URL do portal com token
     * @return bool True se válido, false caso contrário
     */
    public static function validate_portal_link( $portal_url ) {
        // Cast para string para compatibilidade com PHP 8.1+
        $portal_url = (string) $portal_url;
        
        if ( empty( $portal_url ) ) {
            return false;
        }

        // Verifica se tem protocolo
        if ( ! preg_match( '#^https?://#i', $portal_url ) ) {
            return false;
        }

        // Verifica se contém parâmetro token
        if ( false === strpos( $portal_url, 'token=' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Gera mensagem padrão para solicitação de acesso ao portal
     *
     * NOTA DE SEGURANÇA: Os parâmetros $client_name e $pet_name não precisam de sanitização
     * adicional aqui pois são usados apenas em mensagens de texto que serão URL-encoded
     * via rawurlencode() antes de serem enviadas ao WhatsApp. Não são exibidos como HTML.
     *
     * @param string $client_name Nome do cliente (opcional)
     * @param string $pet_name    Nome do pet (opcional)
     * @return string Mensagem formatada
     */
    public static function get_portal_access_request_message( $client_name = '', $pet_name = '' ) {
        if ( ! empty( $client_name ) && ! empty( $pet_name ) ) {
            return sprintf(
                /* translators: 1: client name, 2: pet name */
                __( 'Olá! 🐾 Sou %1$s e gostaria de receber o link de acesso ao Portal do Cliente para acompanhar os serviços do meu pet %2$s. Podem me enviar, por favor?', 'desi-pet-shower' ),
                $client_name,
                $pet_name
            );
        }

        return __( 'Olá! 🐾 Gostaria de receber o link de acesso ao Portal do Cliente para acompanhar os serviços do meu pet. Meu nome: (informe seu nome) | Nome do pet: (informe o nome do pet)', 'desi-pet-shower' );
    }

    /**
     * Gera mensagem padrão para envio de link do portal ao cliente
     *
     * NOTA DE SEGURANÇA: Os parâmetros são usados em mensagens de texto que serão URL-encoded.
     * A URL do portal deve ser validada antes de chamar este método usando validate_portal_link().
     *
     * @param string $client_name Nome do cliente
     * @param string $portal_url  URL completa do portal com token
     * @return string Mensagem formatada
     */
    public static function get_portal_link_message( $client_name, $portal_url ) {
        return sprintf(
            __( 'Olá %s! Aqui está seu link de acesso ao Portal do Cliente: %s - Este link é válido por 30 minutos. Clique para ver seus agendamentos, histórico e muito mais!', 'desi-pet-shower' ),
            $client_name,
            $portal_url
        );
    }

    /**
     * Gera mensagem padrão para confirmação de agendamento
     *
     * NOTA DE SEGURANÇA: Os dados do array $appointment_data vêm do banco de dados (post meta)
     * e são usados apenas em mensagens de texto URL-encoded. Não precisam de sanitização HTML.
     *
     * @param array $appointment_data Array com dados do agendamento
     *                                Esperado: client_name, pet_name, date, time
     * @return string Mensagem formatada
     */
    public static function get_appointment_confirmation_message( $appointment_data ) {
        $client_name = isset( $appointment_data['client_name'] ) ? $appointment_data['client_name'] : '';
        $pet_name    = isset( $appointment_data['pet_name'] ) ? $appointment_data['pet_name'] : '';
        $date        = isset( $appointment_data['date'] ) ? $appointment_data['date'] : '';
        $time        = isset( $appointment_data['time'] ) ? $appointment_data['time'] : '';

        if ( ! empty( $client_name ) && ! empty( $pet_name ) && ! empty( $date ) && ! empty( $time ) ) {
            return sprintf(
                __( 'Olá %s! O agendamento do(a) %s está confirmado para o dia %s às %s. Até lá! 🐾', 'desi-pet-shower' ),
                $client_name,
                $pet_name,
                $date,
                $time
            );
        }

        return __( 'Seu agendamento está confirmado! Até breve! 🐾', 'desi-pet-shower' );
    }

    /**
     * Gera mensagem padrão para cobrança
     *
     * NOTA DE SEGURANÇA: Valores monetários devem ser formatados com DPS_Money_Helper antes
     * de chamar este método. URLs de pagamento devem ser validadas.
     *
     * @param string $client_name Nome do cliente
     * @param string $amount      Valor formatado (ex: 'R$ 80,00')
     * @param string $payment_url URL de pagamento (opcional)
     * @return string Mensagem formatada
     */
    public static function get_payment_request_message( $client_name, $amount, $payment_url = '' ) {
        if ( ! empty( $payment_url ) ) {
            return sprintf(
                __( 'Olá %s! O valor do serviço é %s. Você pode pagar através deste link: %s', 'desi-pet-shower' ),
                $client_name,
                $amount,
                $payment_url
            );
        }

        return sprintf(
            __( 'Olá %s! O valor do serviço é %s.', 'desi-pet-shower' ),
            $client_name,
            $amount
        );
    }

    /**
     * Gera mensagem padrão para relatório de Check-in/Check-out ao cliente.
     *
     * Monta uma mensagem com as informações identificadas durante o
     * check-in e/ou check-out do pet (itens de segurança marcados,
     * observações e duração do atendimento).
     *
     * NOTA DE SEGURANÇA: Os parâmetros vêm de post meta (banco de dados)
     * e são usados em texto URL-encoded para WhatsApp. Não são HTML.
     *
     * @since 1.3.0
     * @param array $report_data {
     *     Dados do relatório de check-in/check-out.
     *
     *     @type string $client_name       Nome do cliente/tutor.
     *     @type string $pet_name          Nome do pet.
     *     @type string $checkin_time      Hora do check-in (H:i).
     *     @type string $checkout_time     Hora do check-out (H:i).
     *     @type string $duration          Duração formatada (ex.: "45 min").
     *     @type array  $safety_summary    Itens de segurança marcados (icon, label, notes).
     *     @type string $observations_in   Observações do check-in.
     *     @type string $observations_out  Observações do check-out.
     * }
     * @return string Mensagem formatada pronta para WhatsApp.
     */
    public static function get_checkin_report_message( $report_data ) {
        $client_name    = isset( $report_data['client_name'] ) ? $report_data['client_name'] : '';
        $pet_name       = isset( $report_data['pet_name'] ) ? $report_data['pet_name'] : '';
        $checkin_time   = isset( $report_data['checkin_time'] ) ? $report_data['checkin_time'] : '';
        $checkout_time  = isset( $report_data['checkout_time'] ) ? $report_data['checkout_time'] : '';
        $duration       = isset( $report_data['duration'] ) ? $report_data['duration'] : '';
        $safety_summary = isset( $report_data['safety_summary'] ) ? $report_data['safety_summary'] : [];
        $obs_in         = isset( $report_data['observations_in'] ) ? $report_data['observations_in'] : '';
        $obs_out        = isset( $report_data['observations_out'] ) ? $report_data['observations_out'] : '';

        $lines = [];

        // Saudação
        $lines[] = sprintf(
            __( 'Olá %s! Segue o relatório do atendimento do(a) *%s*:', 'desi-pet-shower' ),
            $client_name,
            $pet_name
        );
        $lines[] = '';

        // Horários
        if ( $checkin_time ) {
            $lines[] = '📥 ' . sprintf( __( 'Check-in: %s', 'desi-pet-shower' ), $checkin_time );
        }
        if ( $checkout_time ) {
            $lines[] = '📤 ' . sprintf( __( 'Check-out: %s', 'desi-pet-shower' ), $checkout_time );
        }
        if ( $duration ) {
            $lines[] = '⏱️ ' . sprintf( __( 'Duração: %s', 'desi-pet-shower' ), $duration );
        }

        // Itens de segurança identificados
        if ( ! empty( $safety_summary ) ) {
            $lines[] = '';
            $lines[] = '⚠️ ' . __( 'Itens identificados durante o atendimento:', 'desi-pet-shower' );
            foreach ( $safety_summary as $item ) {
                $icon  = isset( $item['icon'] ) ? $item['icon'] : '';
                $label = isset( $item['label'] ) ? $item['label'] : '';
                $notes = isset( $item['notes'] ) ? trim( $item['notes'] ) : '';

                $line = $icon . ' ' . $label;
                if ( $notes ) {
                    $line .= ' — ' . $notes;
                }
                $lines[] = '  • ' . $line;
            }
        }

        // Observações
        $has_obs = false;
        if ( $obs_in ) {
            if ( ! $has_obs ) {
                $lines[] = '';
                $lines[] = '📝 ' . __( 'Observações:', 'desi-pet-shower' );
                $has_obs = true;
            }
            $lines[] = $obs_in;
        }
        if ( $obs_out ) {
            if ( ! $has_obs ) {
                $lines[] = '';
                $lines[] = '📝 ' . __( 'Observações:', 'desi-pet-shower' );
            }
            $lines[] = $obs_out;
        }

        // Rodapé
        $lines[] = '';
        $lines[] = __( 'Qualquer dúvida, estamos à disposição! 🐾', 'desi-pet-shower' );

        return implode( "\n", $lines );
    }
}
