<?php
/**
 * Helper para consultar faturamento a partir de metas históricas.
 */
class DPS_Finance_Revenue_Query {
    /**
     * Soma o meta `_dps_total_at_booking` para agendamentos publicados
     * dentro do intervalo informado.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @param object|null $db    Objeto wpdb customizado para testes.
     * @return int Total em centavos.
     */
    public static function sum_by_period( $start_date, $end_date, $db = null ) {
        if ( ! $db ) {
            global $wpdb;
            $db = $wpdb;
        }

        if ( ! $db || ! method_exists( $db, 'prepare' ) || ! method_exists( $db, 'get_var' ) ) {
            return 0;
        }

        $posts_table    = property_exists( $db, 'posts' ) ? $db->posts : $db->prefix . 'posts';
        $postmeta_table = property_exists( $db, 'postmeta' ) ? $db->postmeta : $db->prefix . 'postmeta';

        $sql = "
            SELECT SUM( CAST(total_meta.meta_value AS UNSIGNED) )
            FROM {$posts_table} AS posts
            INNER JOIN {$postmeta_table} AS date_meta
                ON posts.ID = date_meta.post_id AND date_meta.meta_key = 'appointment_date'
            INNER JOIN {$postmeta_table} AS total_meta
                ON posts.ID = total_meta.post_id AND total_meta.meta_key = '_dps_total_at_booking'
            WHERE posts.post_type = %s
                AND posts.post_status = 'publish'";

        $params = [ 'dps_agendamento' ];

        if ( $start_date ) {
            $sql     .= ' AND date_meta.meta_value >= %s';
            $params[] = $start_date;
        }
        if ( $end_date ) {
            $sql     .= ' AND date_meta.meta_value <= %s';
            $params[] = $end_date;
        }

        $query  = $db->prepare( $sql, $params );
        $result = $db->get_var( $query );

        return (int) $result;
    }
}
