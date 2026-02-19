<?php
/**
 * Clients Section Renderer — renderização da seção de clientes.
 *
 * Extraído de class-dps-base-frontend.php para Single Responsibility.
 * Responsável por renderizar toda a seção "Clientes" no frontend,
 * incluindo listagem, filtros, resumo e formulário de edição.
 *
 * @package DesiPetShower
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pela renderização da seção de clientes.
 */
class DPS_Clients_Section_Renderer {

    /**
     * Renderiza a seção completa de clientes.
     *
     * Orquestra a preparação de dados e a renderização da seção.
     *
     * @since 2.1.0
     * @return string HTML da seção de clientes.
     */
    public static function render() {
        $data = self::prepare_data();
        return self::render_section( $data );
    }

    /**
     * Prepara os dados necessários para a seção de clientes.
     *
     * @since 2.1.0
     * @return array {
     *     Dados estruturados para o template.
     *
     *     @type array       $clients          Lista de posts de clientes (WP_Post[]).
     *     @type array       $client_meta      Metadados principais dos clientes.
     *     @type array       $pets_counts      Contagem de pets por cliente.
     *     @type array       $summary          Resumo de métricas da lista de clientes.
     *     @type string      $current_filter   Filtro ativo (all|without_pets|missing_contact).
     *     @type string      $registration_url URL da página dedicada de cadastro.
     *     @type string      $base_url         URL base da página atual.
     *     @type int         $edit_id          ID do cliente sendo editado (0 se não estiver editando).
     *     @type WP_Post|null $editing         Post do cliente sendo editado (null se não estiver editando).
     *     @type array       $edit_meta        Metadados do cliente sendo editado.
     *     @type string      $api_key          Chave da API do Google Maps.
     * }
     */
    public static function prepare_data() {
        $clients     = DPS_Query_Helper::get_all_posts_by_type( 'dps_cliente' );
        $client_meta = self::build_clients_meta( $clients );
        $pets_counts = self::get_clients_pets_counts( $clients );
        $summary     = self::summarize_clients_data( $clients, $client_meta, $pets_counts );

        $filter = isset( $_GET['dps_clients_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_clients_filter'] ) ) : 'all';
        $allowed_filters = [ 'all', 'without_pets', 'missing_contact' ];
        if ( ! in_array( $filter, $allowed_filters, true ) ) {
            $filter = 'all';
        }

        $registration_url = get_option( 'dps_clients_registration_url', '' );
        $registration_url = apply_filters( 'dps_clients_registration_url', $registration_url );

        // Detecta edição via parâmetros GET (com sanitização adequada)
        $edit_type = isset( $_GET['dps_edit'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_edit'] ) ) : '';
        $edit_id   = ( 'client' === $edit_type && isset( $_GET['id'] ) )
                     ? absint( $_GET['id'] )
                     : 0;
        $editing   = null;
        $edit_meta = [];

        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing && 'dps_cliente' === $editing->post_type ) {
                // Carrega metadados do cliente para edição
                $edit_meta = [
                    'cpf'        => get_post_meta( $edit_id, 'client_cpf', true ),
                    'phone'      => get_post_meta( $edit_id, 'client_phone', true ),
                    'email'      => get_post_meta( $edit_id, 'client_email', true ),
                    'birth'      => get_post_meta( $edit_id, 'client_birth', true ),
                    'instagram'  => get_post_meta( $edit_id, 'client_instagram', true ),
                    'facebook'   => get_post_meta( $edit_id, 'client_facebook', true ),
                    'photo_auth' => get_post_meta( $edit_id, 'client_photo_auth', true ),
                    'address'    => get_post_meta( $edit_id, 'client_address', true ),
                    'referral'   => get_post_meta( $edit_id, 'client_referral', true ),
                    'lat'        => get_post_meta( $edit_id, 'client_lat', true ),
                    'lng'        => get_post_meta( $edit_id, 'client_lng', true ),
                ];
            } else {
                // ID inválido ou post_type incorreto
                $edit_id = 0;
                $editing = null;
            }
        }

        return [
            'clients'          => self::filter_clients_list( $clients, $client_meta, $pets_counts, $filter ),
            'client_meta'      => $client_meta,
            'pets_counts'      => $pets_counts,
            'summary'          => $summary,
            'current_filter'   => $filter,
            'registration_url' => $registration_url,
            'base_url'         => DPS_URL_Builder::safe_get_permalink(),
            'edit_id'          => $edit_id,
            'editing'          => $editing,
            'edit_meta'        => $edit_meta,
            'api_key'          => get_option( 'dps_google_api_key', '' ),
        ];
    }

    /**
     * Pré-carrega metadados críticos dos clientes para evitar consultas repetidas.
     *
     * @since 2.1.0
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    public static function build_clients_meta( $clients ) {
        $meta = [];

        foreach ( $clients as $client ) {
            $id = (int) $client->ID;
            $meta[ $id ] = [
                'phone' => get_post_meta( $id, 'client_phone', true ),
                'email' => get_post_meta( $id, 'client_email', true ),
            ];
        }

        return $meta;
    }

    /**
     * Retorna contagem de pets para cada cliente informado.
     *
     * @since 2.1.0
     * @param array $clients Lista de posts de clientes.
     * @return array
     */
    public static function get_clients_pets_counts( $clients ) {
        $pets_counts = [];

        if ( empty( $clients ) ) {
            return $pets_counts;
        }

        $client_ids   = array_map( 'intval', wp_list_pluck( $clients, 'ID' ) );
        $placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value AS owner_id, COUNT(*) AS pet_count
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = 'owner_id'
                 AND p.post_type = 'dps_pet'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IN ($placeholders)
                 GROUP BY pm.meta_value",
                ...$client_ids
            ),
            ARRAY_A
        );

        foreach ( $results as $row ) {
            $pets_counts[ $row['owner_id'] ] = (int) $row['pet_count'];
        }

        return $pets_counts;
    }

    /**
     * Calcula métricas administrativas da lista de clientes.
     *
     * @since 2.1.0
     * @param array $clients     Lista de posts de clientes.
     * @param array $client_meta Metadados principais dos clientes.
     * @param array $pets_counts Contagem de pets por cliente.
     * @return array
     */
    public static function summarize_clients_data( $clients, $client_meta, $pets_counts ) {
        $missing_contact = 0;
        $without_pets    = 0;

        foreach ( $clients as $client ) {
            $id    = (int) $client->ID;
            $meta  = isset( $client_meta[ $id ] ) ? $client_meta[ $id ] : [ 'phone' => '', 'email' => '' ];
            $phone = isset( $meta['phone'] ) ? $meta['phone'] : '';
            $email = isset( $meta['email'] ) ? $meta['email'] : '';

            if ( empty( $phone ) && empty( $email ) ) {
                $missing_contact++;
            }

            $pets_for_client = isset( $pets_counts[ (string) $id ] ) ? (int) $pets_counts[ (string) $id ] : 0;
            if ( 0 === $pets_for_client ) {
                $without_pets++;
            }
        }

        return [
            'total'            => count( $clients ),
            'missing_contact'  => $missing_contact,
            'without_pets'     => $without_pets,
        ];
    }

    /**
     * Filtra lista de clientes conforme necessidade administrativa.
     *
     * @since 2.1.0
     * @param array  $clients     Lista de posts de clientes.
     * @param array  $client_meta Metadados principais dos clientes.
     * @param array  $pets_counts Contagem de pets por cliente.
     * @param string $filter      Filtro ativo.
     * @return array
     */
    public static function filter_clients_list( $clients, $client_meta, $pets_counts, $filter ) {
        if ( 'without_pets' === $filter ) {
            return array_values(
                array_filter(
                    $clients,
                    function( $client ) use ( $pets_counts ) {
                        $client_id = (string) $client->ID;
                        $count     = isset( $pets_counts[ $client_id ] ) ? (int) $pets_counts[ $client_id ] : 0;
                        return 0 === $count;
                    }
                )
            );
        }

        if ( 'missing_contact' === $filter ) {
            return array_values(
                array_filter(
                    $clients,
                    function( $client ) use ( $client_meta ) {
                        $client_id = (int) $client->ID;
                        $meta      = isset( $client_meta[ $client_id ] ) ? $client_meta[ $client_id ] : [ 'phone' => '', 'email' => '' ];
                        $phone     = isset( $meta['phone'] ) ? $meta['phone'] : '';
                        $email     = isset( $meta['email'] ) ? $meta['email'] : '';

                        return empty( $phone ) && empty( $email );
                    }
                )
            );
        }

        return $clients;
    }

    /**
     * Renderiza a seção de clientes usando template.
     *
     * @since 2.1.0
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    public static function render_section( $data ) {
        ob_start();
        dps_get_template( 'frontend/clients-section.php', $data );
        return ob_get_clean();
    }
}
