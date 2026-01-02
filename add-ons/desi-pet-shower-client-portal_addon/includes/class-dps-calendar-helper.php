<?php
/**
 * Helper para geração de arquivos iCalendar (.ics)
 *
 * Permite exportar agendamentos para Google Calendar, Apple Calendar, Outlook, etc.
 *
 * @package DPS_Client_Portal
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Calendar_Helper' ) ) :

/**
 * Classe helper para geração de arquivos .ics
 */
final class DPS_Calendar_Helper {

    /**
     * Gera arquivo .ics para um agendamento
     *
     * @param int $appointment_id ID do agendamento
     * @return string|false Conteúdo do arquivo .ics ou false em erro
     */
    public static function generate_ics( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        
        if ( ! $appointment_id || 'dps_agendamento' !== get_post_type( $appointment_id ) ) {
            return false;
        }

        // Obtém dados do agendamento
        $date    = get_post_meta( $appointment_id, 'appointment_date', true );
        $time    = get_post_meta( $appointment_id, 'appointment_time', true );
        $pet_id  = get_post_meta( $appointment_id, 'appointment_pet_id', true );
        $services = get_post_meta( $appointment_id, 'appointment_services', true );

        if ( ! $date || ! $time ) {
            return false;
        }

        // Monta informações
        $pet_name = $pet_id ? get_the_title( $pet_id ) : '';
        $summary  = $pet_name ? sprintf( __( 'Banho/Tosa - %s', 'dps-client-portal' ), $pet_name ) : __( 'Agendamento - DPS', 'dps-client-portal' );
        
        $description = '';
        if ( is_array( $services ) && ! empty( $services ) ) {
            $description = __( 'Serviços: ', 'dps-client-portal' ) . implode( ', ', $services );
        }

        // Converte data/hora para formato iCal
        $datetime = strtotime( $date . ' ' . $time );
        $dtstart  = gmdate( 'Ymd\THis\Z', $datetime );
        $dtend    = gmdate( 'Ymd\THis\Z', $datetime + HOUR_IN_SECONDS ); // Duração padrão: 1 hora

        // Gera UID único
        $uid = 'dps-appt-' . $appointment_id . '@' . parse_url( home_url(), PHP_URL_HOST );

        // Monta conteúdo .ics
        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//desi.pet by PRObst//Portal do Cliente//PT\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $uid . "\r\n";
        $ics .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
        $ics .= "DTSTART:" . $dtstart . "\r\n";
        $ics .= "DTEND:" . $dtend . "\r\n";
        $ics .= "SUMMARY:" . self::escape_ics( $summary ) . "\r\n";
        
        if ( $description ) {
            $ics .= "DESCRIPTION:" . self::escape_ics( $description ) . "\r\n";
        }
        
        // Adiciona localização se disponível
        $location = get_option( 'dps_business_address', '' );
        if ( $location ) {
            $ics .= "LOCATION:" . self::escape_ics( $location ) . "\r\n";
        }

        // Adiciona alarme (lembrete 1 dia antes)
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-P1D\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Lembrete: " . self::escape_ics( $summary ) . "\r\n";
        $ics .= "END:VALARM\r\n";

        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "SEQUENCE:0\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Escapa texto para formato iCalendar
     *
     * @param string $text Texto a escapar
     * @return string Texto escapado
     */
    private static function escape_ics( $text ) {
        // Remove caracteres especiais e quebra linhas longas
        $text = str_replace( [ "\r\n", "\n", "\r" ], ' ', $text );
        $text = str_replace( [ ',', ';', '\\' ], [ '\,', '\;', '\\\\' ], $text );
        
        // Limita comprimento de linha (RFC 5545)
        if ( strlen( $text ) > 75 ) {
            $text = substr( $text, 0, 75 );
        }
        
        return $text;
    }

    /**
     * Envia arquivo .ics para download
     *
     * @param int    $appointment_id ID do agendamento
     * @param string $filename       Nome do arquivo (opcional)
     */
    public static function download_ics( $appointment_id, $filename = null ) {
        $ics = self::generate_ics( $appointment_id );
        
        if ( false === $ics ) {
            wp_die( esc_html__( 'Agendamento não encontrado.', 'dps-client-portal' ) );
        }

        if ( ! $filename ) {
            $filename = 'agendamento-' . $appointment_id . '.ics';
        }

        // Headers para download
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $ics ) );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );

        echo $ics;
        exit;
    }

    /**
     * Gera URL para adicionar ao Google Calendar
     *
     * @param int $appointment_id ID do agendamento
     * @return string|false URL do Google Calendar ou false em erro
     */
    public static function get_google_calendar_url( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        
        if ( ! $appointment_id || 'dps_agendamento' !== get_post_type( $appointment_id ) ) {
            return false;
        }

        $date     = get_post_meta( $appointment_id, 'appointment_date', true );
        $time     = get_post_meta( $appointment_id, 'appointment_time', true );
        $pet_id   = get_post_meta( $appointment_id, 'appointment_pet_id', true );
        $services = get_post_meta( $appointment_id, 'appointment_services', true );

        if ( ! $date || ! $time ) {
            return false;
        }

        $pet_name = $pet_id ? get_the_title( $pet_id ) : '';
        $title    = $pet_name ? sprintf( 'Banho/Tosa - %s', $pet_name ) : 'Agendamento - DPS';
        
        $details = '';
        if ( is_array( $services ) && ! empty( $services ) ) {
            $details = 'Serviços: ' . implode( ', ', $services );
        }

        // Formato Google Calendar: YYYYMMDDTHHmmssZ
        $datetime = strtotime( $date . ' ' . $time );
        $start    = gmdate( 'Ymd\THis\Z', $datetime );
        $end      = gmdate( 'Ymd\THis\Z', $datetime + HOUR_IN_SECONDS );

        $location = get_option( 'dps_business_address', '' );

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $title,
            'dates'    => $start . '/' . $end,
            'details'  => $details,
            'location' => $location,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query( $params );
    }
}

endif;
