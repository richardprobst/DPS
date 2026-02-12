<?php
/**
 * Service de agendamentos (Fase 7.3).
 *
 * CRUD para o post type dps_agendamento. Cria agendamentos com metas
 * padronizadas para o Booking V2 multi-step wizard.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Appointment_Service extends DPS_Abstract_Service {

    protected function postType(): string {
        return 'dps_agendamento';
    }

    /**
     * Cria um novo agendamento.
     *
     * @param array<string, mixed> $data Dados do agendamento.
     * @return int|false ID do agendamento criado ou false em caso de erro.
     */
    public function create( array $data ): int|false {
        $postData = [
            'post_title'  => '',
            'post_status' => 'publish',
        ];

        $meta = $this->buildMeta( $data );

        $id = $this->createPost( $postData, $meta );

        if ( false === $id ) {
            return false;
        }

        // Atualiza título com ID gerado.
        wp_update_post( [
            'ID'         => $id,
            'post_title' => sprintf( 'Agendamento #%d', $id ),
        ] );

        return $id;
    }

    /**
     * Atualiza metas de um agendamento existente.
     *
     * @param int                  $id   ID do agendamento.
     * @param array<string, mixed> $data Dados a atualizar.
     * @return bool True se atualizado com sucesso.
     */
    public function update( int $id, array $data ): bool {
        $post = get_post( $id );

        if ( ! $post || $this->postType() !== $post->post_type ) {
            return false;
        }

        $meta = $this->buildMeta( $data );
        $this->updateMeta( $id, $meta );

        return true;
    }

    /**
     * Recupera um agendamento com todas as metas.
     *
     * @param int $id ID do agendamento.
     * @return array<string, mixed>|false Dados do agendamento ou false se não encontrado.
     */
    public function get( int $id ): array|false {
        $post = get_post( $id );

        if ( ! $post || $this->postType() !== $post->post_type ) {
            return false;
        }

        $metaKeys = [
            'appointment_client_id',
            'appointment_pet_id',
            'appointment_pet_ids',
            'appointment_date',
            'appointment_time',
            'appointment_status',
            'appointment_type',
            'appointment_services',
            'appointment_service_prices',
            'appointment_total_value',
            'appointment_notes',
            'appointment_taxidog',
            'appointment_taxidog_price',
            'appointment_tosa',
            'appointment_tosa_price',
            'appointment_tosa_occurrence',
            'subscription_id',
            '_dps_appointment_version',
        ];

        $data = [
            'id'    => $post->ID,
            'title' => $post->post_title,
        ];

        foreach ( $metaKeys as $key ) {
            $value = get_post_meta( $id, $key, true );

            // Desserializa arrays armazenados.
            if ( in_array( $key, [ 'appointment_pet_ids', 'appointment_services', 'appointment_service_prices' ], true ) ) {
                $value = maybe_unserialize( $value );
            }

            $data[ $key ] = $value;
        }

        return $data;
    }

    /**
     * Busca agendamentos de um cliente.
     *
     * @param int $clientId ID do cliente.
     * @param int $limit    Máximo de resultados.
     * @return array<int, array<string, mixed>> Lista de agendamentos.
     */
    public function searchByClient( int $clientId, int $limit = 10 ): array {
        $query = new WP_Query( [
            'post_type'      => $this->postType(),
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => 'appointment_client_id',
            'meta_value'     => $clientId,
            'meta_type'      => 'NUMERIC',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        $results = [];

        foreach ( $query->posts as $post ) {
            $results[] = $this->get( $post->ID );
        }

        wp_reset_postdata();

        return array_filter( $results );
    }

    /**
     * Verifica conflito de horário.
     *
     * @param string $date Data no formato YYYY-MM-DD.
     * @param string $time Horário no formato HH:MM.
     * @return bool True se existe conflito.
     */
    public function checkConflict( string $date, string $time ): bool {
        $query = new WP_Query( [
            'post_type'      => $this->postType(),
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'appointment_date',
                    'value' => sanitize_text_field( $date ),
                ],
                [
                    'key'   => 'appointment_time',
                    'value' => sanitize_text_field( $time ),
                ],
            ],
        ] );

        wp_reset_postdata();

        return $query->found_posts > 0;
    }

    /**
     * Monta array de metas a partir dos dados recebidos.
     *
     * @param array<string, mixed> $data Dados brutos.
     * @return array<string, mixed> Metas sanitizadas.
     */
    private function buildMeta( array $data ): array {
        $meta = [
            'appointment_client_id'      => absint( $data['appointment_client_id'] ?? 0 ),
            'appointment_pet_id'         => absint( $data['appointment_pet_id'] ?? 0 ),
            'appointment_pet_ids'        => maybe_serialize( array_map( 'absint', (array) ( $data['appointment_pet_ids'] ?? [] ) ) ),
            'appointment_date'           => sanitize_text_field( $data['appointment_date'] ?? '' ),
            'appointment_time'           => sanitize_text_field( $data['appointment_time'] ?? '' ),
            'appointment_status'         => sanitize_text_field( $data['appointment_status'] ?? 'pendente' ),
            'appointment_type'           => sanitize_text_field( $data['appointment_type'] ?? 'simple' ),
            'appointment_services'       => maybe_serialize( array_map( 'absint', (array) ( $data['appointment_services'] ?? [] ) ) ),
            'appointment_service_prices' => maybe_serialize( array_map( 'floatval', (array) ( $data['appointment_service_prices'] ?? [] ) ) ),
            'appointment_total_value'    => (float) ( $data['appointment_total_value'] ?? 0 ),
            'appointment_notes'          => sanitize_textarea_field( $data['appointment_notes'] ?? '' ),
            'appointment_taxidog'        => absint( ! empty( $data['appointment_taxidog'] ) ? 1 : 0 ),
            'appointment_taxidog_price'  => (float) ( $data['appointment_taxidog_price'] ?? 0 ),
            'appointment_tosa'           => absint( ! empty( $data['appointment_tosa'] ) ? 1 : 0 ),
            'appointment_tosa_price'     => (float) ( $data['appointment_tosa_price'] ?? 30.00 ),
            'appointment_tosa_occurrence' => absint( $data['appointment_tosa_occurrence'] ?? 0 ),
            '_dps_appointment_version'   => '2.0',
        ];

        if ( ! empty( $data['subscription_id'] ) ) {
            $meta['subscription_id'] = absint( $data['subscription_id'] );
        }

        return $meta;
    }
}
