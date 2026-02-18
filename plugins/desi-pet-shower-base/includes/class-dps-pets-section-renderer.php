<?php
/**
 * Pets Section Renderer — renderização da seção de pets.
 *
 * Extraído de class-dps-base-frontend.php para Single Responsibility.
 * Responsável por renderizar toda a seção "Pets" no frontend,
 * incluindo listagem, filtros, estatísticas e formulário de edição.
 *
 * @package DesiPetShower
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável pela renderização da seção de pets.
 */
class DPS_Pets_Section_Renderer {

    /**
     * Renderiza a seção completa de pets.
     *
     * Orquestra a preparação de dados e a renderização da seção.
     *
     * @since 2.1.0
     * @return string HTML da seção de pets.
     */
    public static function render() {
        $data = self::prepare_data();
        return self::render_section( $data );
    }

    /**
     * Prepara os dados necessários para a seção de pets.
     *
     * @since 2.1.0
     * @return array {
     *     Dados estruturados para o template.
     *
     *     @type array       $pets          Lista de posts de pets.
     *     @type int         $pets_page     Página atual da paginação.
     *     @type int         $pets_pages    Total de páginas.
     *     @type array       $clients       Lista de clientes disponíveis.
     *     @type int         $edit_id       ID do pet sendo editado (0 se novo).
     *     @type WP_Post|null $editing      Post do pet em edição (null se novo).
     *     @type array       $meta          Metadados do pet.
     *     @type array       $breed_options Lista de raças disponíveis.
     *     @type array       $breed_data    Dataset completo de raças por espécie.
     *     @type string      $base_url      URL base da página.
     * }
     */
    public static function prepare_data() {
        $clients    = DPS_Base_Frontend::get_clients();

        // Busca todos os pets para estatísticas (sem paginação)
        $all_pets_query = new WP_Query( [
            'post_type'      => 'dps_pet',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        $all_pet_ids = $all_pets_query->posts;

        // Pré-carrega metadados dos pets
        if ( ! empty( $all_pet_ids ) ) {
            update_meta_cache( 'post', $all_pet_ids );
        }

        // Coleta estatísticas dos pets
        $pets_stats = self::build_pets_statistics( $all_pet_ids );

        // Detecta filtro via parâmetros GET
        $filter = isset( $_GET['dps_pets_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['dps_pets_filter'] ) ) : 'all';
        $allowed_filters = [ 'all', 'aggressive', 'without_owner', 'cao', 'gato', 'outro' ];
        if ( ! in_array( $filter, $allowed_filters, true ) ) {
            $filter = 'all';
        }

        // Paginação
        $pets_page  = isset( $_GET['dps_pets_page'] ) ? max( 1, intval( $_GET['dps_pets_page'] ) ) : 1;

        // Aplica filtro na busca
        $filtered_pets_query = self::get_filtered_pets( $pets_page, $filter );
        $pets       = $filtered_pets_query->posts;
        $pets_pages = (int) max( 1, $filtered_pets_query->max_num_pages );

        // Pré-carrega contagem de agendamentos e última data de atendimento para cada pet
        $pet_ids_in_page = wp_list_pluck( $pets, 'ID' );
        $appointments_stats = self::get_pets_appointments_stats( $pet_ids_in_page );

        // Detecta edição via parâmetros GET
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'pet' === $_GET['dps_edit'] && isset( $_GET['id'] ) )
                   ? intval( $_GET['id'] )
                   : 0;

        $editing = null;
        $meta    = [];

        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing ) {
                // Carrega metadados do pet para edição
                $meta = [
                    'owner_id'             => get_post_meta( $edit_id, 'owner_id', true ),
                    'species'              => get_post_meta( $edit_id, 'pet_species', true ),
                    'breed'                => get_post_meta( $edit_id, 'pet_breed', true ),
                    'size'                 => get_post_meta( $edit_id, 'pet_size', true ),
                    'weight'               => get_post_meta( $edit_id, 'pet_weight', true ),
                    'coat'                 => get_post_meta( $edit_id, 'pet_coat', true ),
                    'color'                => get_post_meta( $edit_id, 'pet_color', true ),
                    'birth'                => get_post_meta( $edit_id, 'pet_birth', true ),
                    'sex'                  => get_post_meta( $edit_id, 'pet_sex', true ),
                    'care'                 => get_post_meta( $edit_id, 'pet_care', true ),
                    'aggressive'           => get_post_meta( $edit_id, 'pet_aggressive', true ),
                    'vaccinations'         => get_post_meta( $edit_id, 'pet_vaccinations', true ),
                    'allergies'            => get_post_meta( $edit_id, 'pet_allergies', true ),
                    'behavior'             => get_post_meta( $edit_id, 'pet_behavior', true ),
                    'photo_id'             => get_post_meta( $edit_id, 'pet_photo_id', true ),
                    // Preferências de produtos
                    'shampoo_pref'         => get_post_meta( $edit_id, 'pet_shampoo_pref', true ),
                    'perfume_pref'         => get_post_meta( $edit_id, 'pet_perfume_pref', true ),
                    'accessories_pref'     => get_post_meta( $edit_id, 'pet_accessories_pref', true ),
                    'product_restrictions' => get_post_meta( $edit_id, 'pet_product_restrictions', true ),
                ];
            }
        }

        // Detecta pref_owner para pré-selecionar cliente no formulário
        $pref_owner = isset( $_GET['pref_owner'] ) ? absint( $_GET['pref_owner'] ) : 0;
        if ( $pref_owner && empty( $meta['owner_id'] ) ) {
            $meta['owner_id'] = $pref_owner;
        }

        $species_val   = $meta['species'] ?? '';
        $breed_data    = DPS_Breed_Registry::get_dataset();
        $breed_options = DPS_Breed_Registry::get_options_for_species( $species_val );

        return [
            'pets'               => $pets,
            'pets_page'          => $pets_page,
            'pets_pages'         => $pets_pages,
            'pets_total'         => count( $all_pet_ids ),
            'clients'            => $clients,
            'edit_id'            => $edit_id,
            'editing'            => $editing,
            'meta'               => $meta,
            'breed_options'      => $breed_options,
            'breed_data'         => $breed_data,
            'base_url'           => DPS_URL_Builder::safe_get_permalink(),
            'current_filter'     => $filter,
            'summary'            => $pets_stats,
            'appointments_stats' => $appointments_stats,
        ];
    }

    /**
     * Busca pets com filtro aplicado.
     *
     * @since 2.1.0
     * @param int    $page   Número da página.
     * @param string $filter Filtro a ser aplicado.
     * @return WP_Query
     */
    public static function get_filtered_pets( $page, $filter ) {
        $args = [
            'post_type'      => 'dps_pet',
            'posts_per_page' => DPS_BASE_PETS_PER_PAGE,
            'post_status'    => 'publish',
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $meta_query = [];

        switch ( $filter ) {
            case 'aggressive':
                $meta_query[] = [
                    'key'     => 'pet_aggressive',
                    'value'   => '1',
                    'compare' => '=',
                ];
                break;
            case 'without_owner':
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key'     => 'owner_id',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => 'owner_id',
                        'value'   => '',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'owner_id',
                        'value'   => '0',
                        'compare' => '=',
                    ],
                ];
                break;
            case 'cao':
            case 'gato':
            case 'outro':
                $meta_query[] = [
                    'key'     => 'pet_species',
                    'value'   => $filter,
                    'compare' => '=',
                ];
                break;
        }

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        return new WP_Query( $args );
    }

    /**
     * Calcula estatísticas dos pets para o painel de resumo.
     *
     * @since 2.1.0
     * @param array $pet_ids Lista de IDs de pets.
     * @return array
     */
    public static function build_pets_statistics( $pet_ids ) {
        $stats = [
            'total'         => count( $pet_ids ),
            'aggressive'    => 0,
            'without_owner' => 0,
            'dogs'          => 0,
            'cats'          => 0,
            'others'        => 0,
        ];

        if ( empty( $pet_ids ) ) {
            return $stats;
        }

        foreach ( $pet_ids as $pet_id ) {
            $aggressive = get_post_meta( $pet_id, 'pet_aggressive', true );
            $owner_id   = get_post_meta( $pet_id, 'owner_id', true );
            $species    = get_post_meta( $pet_id, 'pet_species', true );

            if ( $aggressive ) {
                $stats['aggressive']++;
            }

            if ( empty( $owner_id ) ) {
                $stats['without_owner']++;
            }

            switch ( $species ) {
                case 'cao':
                    $stats['dogs']++;
                    break;
                case 'gato':
                    $stats['cats']++;
                    break;
                default:
                    $stats['others']++;
                    break;
            }
        }

        return $stats;
    }

    /**
     * Busca estatísticas de agendamentos para cada pet.
     *
     * @since 2.1.0
     * @param array $pet_ids Lista de IDs de pets.
     * @return array Array com contagem de agendamentos e última data por pet_id.
     */
    public static function get_pets_appointments_stats( $pet_ids ) {
        $stats = [];

        if ( empty( $pet_ids ) ) {
            return $stats;
        }

        global $wpdb;

        $pet_ids = array_map( 'intval', $pet_ids );
        $placeholders = implode( ',', array_fill( 0, count( $pet_ids ), '%s' ) );

        // Busca contagem de agendamentos e última data para cada pet
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS pet_id, 
                        COUNT(DISTINCT pm.post_id) AS appointment_count,
                        MAX(pm2.meta_value) AS last_appointment_date
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = 'appointment_date'
                 WHERE pm.meta_key = 'appointment_pet_id'
                 AND p.post_type = 'dps_agendamento'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IN ($placeholders)
                 GROUP BY pm.meta_value",
                ...$pet_ids
            ),
            ARRAY_A
        );

        foreach ( $results as $row ) {
            $stats[ $row['pet_id'] ] = [
                'count'     => (int) $row['appointment_count'],
                'last_date' => $row['last_appointment_date'],
            ];
        }

        // Preenche pets sem agendamentos
        foreach ( $pet_ids as $pet_id ) {
            if ( ! isset( $stats[ (string) $pet_id ] ) ) {
                $stats[ (string) $pet_id ] = [
                    'count'     => 0,
                    'last_date' => null,
                ];
            }
        }

        return $stats;
    }

    /**
     * Renderiza a seção de pets usando template.
     *
     * @since 2.1.0
     * @param array $data Dados preparados para renderização.
     * @return string HTML da seção.
     */
    public static function render_section( $data ) {
        ob_start();
        dps_get_template( 'frontend/pets-section.php', $data );
        return ob_get_clean();
    }
}
