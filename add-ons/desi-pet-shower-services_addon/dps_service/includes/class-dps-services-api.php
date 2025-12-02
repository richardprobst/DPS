<?php
/**
 * API pública do Services Add-on
 *
 * Centraliza toda a lógica de serviços, cálculo de preços e informações
 * detalhadas para reutilização por outros add-ons (Agenda, Finance, Portal, etc.)
 *
 * @package DPS_Services_Addon
 * @since 1.2.0
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Services_API
 *
 * Fornece métodos públicos para:
 * - Obter dados completos de um serviço
 * - Calcular preço por porte de pet
 * - Calcular total de um agendamento
 * - Obter detalhes de serviços de um agendamento
 *
 * @since 1.2.0
 */
class DPS_Services_API {

    /**
     * Obtém dados completos de um serviço.
     *
     * @param int $service_id ID do serviço.
     * @return array|null Array com dados do serviço ou null se não encontrado.
     *
     * Estrutura retornada:
     * [
     *   'id'           => int,
     *   'title'        => string,
     *   'type'         => string,
     *   'category'     => string,
     *   'active'       => bool,
     *   'description'  => string,
     *   'price'        => float (preço base),
     *   'price_small'  => float|null,
     *   'price_medium' => float|null,
     *   'price_large'  => float|null,
     * ]
     *
     * @since 1.2.0
     */
    public static function get_service( $service_id ) {
        $service_id = absint( $service_id );
        if ( ! $service_id ) {
            return null;
        }

        $service = get_post( $service_id );
        if ( ! $service || 'dps_service' !== $service->post_type ) {
            return null;
        }

        $data = [
            'id'           => $service->ID,
            'title'        => $service->post_title,
            'type'         => get_post_meta( $service->ID, 'service_type', true ),
            'category'     => get_post_meta( $service->ID, 'service_category', true ),
            'active'       => '0' !== get_post_meta( $service->ID, 'service_active', true ),
            'description'  => $service->post_content,
            'price'        => (float) get_post_meta( $service->ID, 'service_price', true ),
            'price_small'  => self::get_meta_float( $service->ID, 'service_price_small' ),
            'price_medium' => self::get_meta_float( $service->ID, 'service_price_medium' ),
            'price_large'  => self::get_meta_float( $service->ID, 'service_price_large' ),
        ];

        return $data;
    }

    /**
     * Calcula o preço de um serviço com base no porte do pet.
     *
     * @param int    $service_id ID do serviço.
     * @param string $pet_size   Porte do pet: 'pequeno', 'medio', 'grande' ou 'small', 'medium', 'large'.
     * @param array  $context    Contexto adicional (reservado para uso futuro).
     * @return float|null Preço calculado ou null se serviço não encontrado.
     *
     * @since 1.2.0
     */
    public static function calculate_price( $service_id, $pet_size = '', $context = [] ) {
        $service = self::get_service( $service_id );
        if ( ! $service ) {
            return null;
        }

        // Normaliza o porte
        $size = self::normalize_pet_size( $pet_size );

        // Tenta obter preço específico por porte
        if ( 'small' === $size && null !== $service['price_small'] ) {
            return $service['price_small'];
        }
        if ( 'medium' === $size && null !== $service['price_medium'] ) {
            return $service['price_medium'];
        }
        if ( 'large' === $size && null !== $service['price_large'] ) {
            return $service['price_large'];
        }

        // Fallback para preço base
        return $service['price'];
    }

    /**
     * Calcula o total de um agendamento com base nos serviços e pets selecionados.
     *
     * @param array $service_ids Array de IDs de serviços.
     * @param array $pet_ids     Array de IDs de pets.
     * @param array $context     Contexto adicional (pode conter 'custom_prices', 'extras', 'taxidog').
     * @return array Array com informações do cálculo.
     *
     * Estrutura retornada:
     * [
     *   'total'            => float,
     *   'services_total'   => float,
     *   'services_details' => array,
     *   'extras_total'     => float,
     *   'taxidog_total'    => float,
     * ]
     *
     * Context pode incluir:
     * - 'custom_prices': array [ service_id => price ] com preços personalizados
     * - 'extras': float valor de extras
     * - 'taxidog': float valor de taxidog
     *
     * @since 1.2.0
     */
    public static function calculate_appointment_total( $service_ids, $pet_ids, $context = [] ) {
        $service_ids = is_array( $service_ids ) ? array_map( 'absint', $service_ids ) : [];
        $pet_ids     = is_array( $pet_ids ) ? array_map( 'absint', $pet_ids ) : [];
        
        $custom_prices = isset( $context['custom_prices'] ) && is_array( $context['custom_prices'] ) 
            ? $context['custom_prices'] 
            : [];
        
        $extras_total  = isset( $context['extras'] ) ? (float) $context['extras'] : 0.0;
        $taxidog_total = isset( $context['taxidog'] ) ? (float) $context['taxidog'] : 0.0;

        // Determina o porte do primeiro pet selecionado para cálculo
        $pet_size = '';
        if ( ! empty( $pet_ids ) ) {
            $first_pet = get_post( $pet_ids[0] );
            if ( $first_pet ) {
                $pet_size = get_post_meta( $first_pet->ID, 'pet_size', true );
            }
        }

        $services_total   = 0.0;
        $services_details = [];

        foreach ( $service_ids as $service_id ) {
            // Verifica se existe preço personalizado
            if ( isset( $custom_prices[ $service_id ] ) ) {
                $price = (float) $custom_prices[ $service_id ];
            } else {
                // Calcula preço pela API
                $price = self::calculate_price( $service_id, $pet_size, $context );
                if ( null === $price ) {
                    continue; // Serviço não encontrado
                }
            }

            $service = self::get_service( $service_id );
            $services_details[] = [
                'service_id' => $service_id,
                'name'       => $service ? $service['title'] : '',
                'price'      => $price,
            ];
            $services_total += $price;
        }

        $total = $services_total + $extras_total + $taxidog_total;

        return [
            'total'            => $total,
            'services_total'   => $services_total,
            'services_details' => $services_details,
            'extras_total'     => $extras_total,
            'taxidog_total'    => $taxidog_total,
        ];
    }

    /**
     * Obtém detalhes de serviços de um agendamento.
     *
     * @param int $appointment_id ID do agendamento.
     * @return array Array com detalhes dos serviços.
     *
     * Estrutura retornada:
     * [
     *   'services' => [
     *     ['name' => string, 'price' => float],
     *     ...
     *   ],
     *   'total' => float,
     * ]
     *
     * @since 1.2.0
     */
    public static function get_services_details( $appointment_id ) {
        $appointment_id = absint( $appointment_id );
        if ( ! $appointment_id ) {
            return [
                'services' => [],
                'total'    => 0.0,
            ];
        }

        $appointment = get_post( $appointment_id );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            return [
                'services' => [],
                'total'    => 0.0,
            ];
        }

        $service_ids          = get_post_meta( $appointment_id, 'appointment_services', true );
        $service_prices_meta  = get_post_meta( $appointment_id, 'appointment_service_prices', true );
        
        $service_ids         = is_array( $service_ids ) ? $service_ids : [];
        $service_prices_meta = is_array( $service_prices_meta ) ? $service_prices_meta : [];

        $services = [];
        $total    = 0.0;

        foreach ( $service_ids as $service_id ) {
            $service = self::get_service( $service_id );
            if ( ! $service ) {
                continue;
            }

            // Usa preço personalizado se disponível, senão preço base
            $price = isset( $service_prices_meta[ $service_id ] ) 
                ? (float) $service_prices_meta[ $service_id ] 
                : $service['price'];

            $services[] = [
                'name'  => $service['title'],
                'price' => $price,
            ];
            $total += $price;
        }

        return [
            'services' => $services,
            'total'    => $total,
        ];
    }

    /**
     * Normaliza o porte do pet para formato padrão.
     *
     * @param string $size Porte do pet.
     * @return string Porte normalizado: 'small', 'medium', 'large' ou ''.
     *
     * @since 1.2.0
     */
    private static function normalize_pet_size( $size ) {
        $size = strtolower( trim( $size ) );
        
        // Remove acentos
        $size = remove_accents( $size );

        if ( 'pequeno' === $size || 'small' === $size ) {
            return 'small';
        }
        if ( 'medio' === $size || 'médio' === $size || 'medium' === $size ) {
            return 'medium';
        }
        if ( 'grande' === $size || 'large' === $size ) {
            return 'large';
        }

        return '';
    }

    /**
     * Obtém valor float de um meta, retornando null se vazio.
     *
     * @param int    $post_id Post ID.
     * @param string $meta_key Meta key.
     * @return float|null
     *
     * @since 1.2.0
     */
    private static function get_meta_float( $post_id, $meta_key ) {
        $value = get_post_meta( $post_id, $meta_key, true );
        if ( '' === $value || null === $value ) {
            return null;
        }
        return (float) $value;
    }

    // =====================================================================
    // FUNCIONALIDADES NOVAS v1.3.0
    // =====================================================================

    /**
     * Calcula o preço de um pacote promocional.
     *
     * Um pacote pode ter:
     * - Preço fixo (service_package_fixed_price): ignora serviços incluídos
     * - Desconto percentual (service_package_discount): aplica sobre soma dos serviços
     *
     * @param int    $package_id ID do pacote.
     * @param string $pet_size   Porte do pet para cálculo.
     * @return float|null Preço calculado ou null se não for pacote válido.
     *
     * @since 1.3.0
     */
    public static function calculate_package_price( $package_id, $pet_size = '' ) {
        $package = self::get_service( $package_id );
        if ( ! $package || 'package' !== $package['type'] ) {
            return null;
        }

        $items    = get_post_meta( $package_id, 'service_package_items', true );
        $discount = (float) get_post_meta( $package_id, 'service_package_discount', true );
        $fixed    = get_post_meta( $package_id, 'service_package_fixed_price', true );

        // Se tem preço fixo definido, usa ele
        if ( '' !== $fixed && (float) $fixed > 0 ) {
            return (float) $fixed;
        }

        // Senão, calcula soma dos itens com desconto
        if ( ! is_array( $items ) || empty( $items ) ) {
            return $package['price'];
        }

        $sum = 0.0;
        foreach ( $items as $item_id ) {
            $item_price = self::calculate_price( $item_id, $pet_size );
            if ( null !== $item_price ) {
                $sum += $item_price;
            }
        }

        // Aplica desconto percentual se houver
        if ( $discount > 0 && $discount <= 100 ) {
            $sum = $sum * ( 1 - ( $discount / 100 ) );
        }

        return round( $sum, 2 );
    }

    /**
     * Obtém o histórico de alterações de preço de um serviço.
     *
     * @param int $service_id ID do serviço.
     * @return array Array de alterações ordenadas da mais recente para a mais antiga.
     *
     * Estrutura de cada item:
     * [
     *   'date'       => string (Y-m-d H:i:s),
     *   'user_id'    => int,
     *   'user_name'  => string,
     *   'old_price'  => float,
     *   'new_price'  => float,
     *   'price_type' => string ('base', 'small', 'medium', 'large'),
     * ]
     *
     * @since 1.3.0
     */
    public static function get_price_history( $service_id ) {
        $service_id = absint( $service_id );
        if ( ! $service_id ) {
            return [];
        }

        $history = get_post_meta( $service_id, 'service_price_history', true );
        if ( ! is_array( $history ) ) {
            return [];
        }

        // Adiciona nome do usuário a cada entrada
        foreach ( $history as &$entry ) {
            if ( isset( $entry['user_id'] ) ) {
                $user = get_user_by( 'id', $entry['user_id'] );
                $entry['user_name'] = $user ? $user->display_name : __( 'Usuário desconhecido', 'dps-services-addon' );
            }
        }

        // Ordena por data (mais recente primeiro)
        usort( $history, function( $a, $b ) {
            return strtotime( $b['date'] ?? 0 ) - strtotime( $a['date'] ?? 0 );
        } );

        return $history;
    }

    /**
     * Lista todos os serviços ativos para exibição pública.
     *
     * @param array $args Argumentos opcionais:
     *   - 'type': Filtrar por tipo ('padrao', 'extra', 'package')
     *   - 'category': Filtrar por categoria
     *   - 'include_prices': bool (default true) - Incluir preços na resposta
     *   - 'orderby': Campo para ordenação (default 'title')
     *   - 'order': Direção da ordenação (default 'ASC')
     *
     * @return array Array de serviços formatados para exibição pública.
     *
     * @since 1.3.0
     */
    public static function get_public_services( $args = [] ) {
        $defaults = [
            'type'           => '',
            'category'       => '',
            'include_prices' => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $query_args = [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => sanitize_key( $args['orderby'] ),
            'order'          => 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'service_active',
                    'value'   => '0',
                    'compare' => '!=',
                ],
            ],
        ];

        // Filtra por tipo se especificado
        if ( $args['type'] ) {
            $query_args['meta_query'][] = [
                'key'     => 'service_type',
                'value'   => sanitize_text_field( $args['type'] ),
                'compare' => '=',
            ];
        }

        // Filtra por categoria se especificado
        if ( $args['category'] ) {
            $query_args['meta_query'][] = [
                'key'     => 'service_category',
                'value'   => sanitize_text_field( $args['category'] ),
                'compare' => '=',
            ];
        }

        $services = get_posts( $query_args );
        $result   = [];

        foreach ( $services as $service ) {
            $data = [
                'id'          => $service->ID,
                'title'       => $service->post_title,
                'description' => $service->post_content,
                'type'        => get_post_meta( $service->ID, 'service_type', true ),
                'category'    => get_post_meta( $service->ID, 'service_category', true ),
            ];

            if ( $args['include_prices'] ) {
                $data['price']        = (float) get_post_meta( $service->ID, 'service_price', true );
                $data['price_small']  = self::get_meta_float( $service->ID, 'service_price_small' );
                $data['price_medium'] = self::get_meta_float( $service->ID, 'service_price_medium' );
                $data['price_large']  = self::get_meta_float( $service->ID, 'service_price_large' );

                // Para pacotes, inclui informações de desconto
                if ( 'package' === $data['type'] ) {
                    $data['package_discount']    = (float) get_post_meta( $service->ID, 'service_package_discount', true );
                    $data['package_fixed_price'] = self::get_meta_float( $service->ID, 'service_package_fixed_price' );
                    $data['package_items']       = get_post_meta( $service->ID, 'service_package_items', true ) ?: [];
                }
            }

            $result[] = $data;
        }

        return $result;
    }

    /**
     * Obtém serviços para exibição no Portal do Cliente.
     *
     * Retorna dados formatados para o portal, incluindo histórico de serviços
     * utilizados pelo cliente quando disponível.
     *
     * @param int   $client_id ID do cliente (CPT dps_cliente).
     * @param array $args      Argumentos opcionais:
     *   - 'include_history': bool (default true) - Incluir histórico de uso
     *   - 'limit_history': int (default 10) - Limite de agendamentos no histórico
     *
     * @return array Array com serviços disponíveis e histórico do cliente.
     *
     * @since 1.3.0
     */
    public static function get_portal_services( $client_id = 0, $args = [] ) {
        $defaults = [
            'include_history' => true,
            'limit_history'   => 10,
        ];
        $args = wp_parse_args( $args, $defaults );

        $result = [
            'available_services' => self::get_public_services( [ 'include_prices' => true ] ),
            'categories'         => self::get_service_categories(),
            'service_history'    => [],
        ];

        // Se cliente especificado e histórico solicitado, busca uso anterior
        if ( $client_id && $args['include_history'] ) {
            $result['service_history'] = self::get_client_service_history( $client_id, $args['limit_history'] );
        }

        return $result;
    }

    /**
     * Obtém o histórico de serviços utilizados por um cliente.
     *
     * @param int $client_id   ID do cliente.
     * @param int $limit       Limite de agendamentos a buscar.
     * @return array Array de serviços utilizados com frequência.
     *
     * @since 1.3.0
     */
    public static function get_client_service_history( $client_id, $limit = 10 ) {
        $client_id = absint( $client_id );
        if ( ! $client_id ) {
            return [];
        }

        // Busca agendamentos do cliente
        $appointments = get_posts( [
            'post_type'      => 'dps_agendamento',
            'posts_per_page' => absint( $limit ),
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'appointment_client_id',
                    'value'   => $client_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        $service_usage = [];

        foreach ( $appointments as $appointment ) {
            $services = get_post_meta( $appointment->ID, 'appointment_services', true );
            if ( ! is_array( $services ) ) {
                continue;
            }

            foreach ( $services as $service_id ) {
                if ( ! isset( $service_usage[ $service_id ] ) ) {
                    $service = self::get_service( $service_id );
                    if ( $service ) {
                        $service_usage[ $service_id ] = [
                            'service_id' => $service_id,
                            'name'       => $service['title'],
                            'count'      => 0,
                            'last_used'  => '',
                        ];
                    }
                }
                if ( isset( $service_usage[ $service_id ] ) ) {
                    $service_usage[ $service_id ]['count']++;
                    if ( empty( $service_usage[ $service_id ]['last_used'] ) ) {
                        $service_usage[ $service_id ]['last_used'] = get_the_date( 'Y-m-d', $appointment );
                    }
                }
            }
        }

        // Ordena por frequência de uso
        usort( $service_usage, function( $a, $b ) {
            return $b['count'] - $a['count'];
        } );

        return array_values( $service_usage );
    }

    /**
     * Obtém lista de categorias de serviços disponíveis.
     *
     * @return array Array de categorias com chave e label.
     *
     * @since 1.3.0
     */
    public static function get_service_categories() {
        return [
            'banho'              => __( 'Banho', 'dps-services-addon' ),
            'tosa'               => __( 'Tosa', 'dps-services-addon' ),
            'extras'             => __( 'Extras', 'dps-services-addon' ),
            'preparacao_pelagem' => __( 'Preparação da pelagem', 'dps-services-addon' ),
            'opcoes_tosa'        => __( 'Opções de tosa', 'dps-services-addon' ),
            'tratamento'         => __( 'Tratamento', 'dps-services-addon' ),
            'cuidados'           => __( 'Cuidados adicionais', 'dps-services-addon' ),
            'pelagem'            => __( 'Tratamento da pelagem e pele', 'dps-services-addon' ),
        ];
    }

    /**
     * Duplica um serviço existente.
     *
     * @param int $service_id ID do serviço a duplicar.
     * @return int|false ID do novo serviço ou false em caso de erro.
     *
     * @since 1.3.0
     */
    public static function duplicate_service( $service_id ) {
        $service_id = absint( $service_id );
        $original   = get_post( $service_id );

        if ( ! $original || 'dps_service' !== $original->post_type ) {
            return false;
        }

        // Cria novo post
        $new_id = wp_insert_post( [
            'post_type'    => 'dps_service',
            'post_title'   => sprintf(
                /* translators: %s: nome do serviço original */
                __( '%s (Cópia)', 'dps-services-addon' ),
                $original->post_title
            ),
            'post_content' => $original->post_content,
            'post_status'  => 'publish',
        ] );

        if ( ! $new_id || is_wp_error( $new_id ) ) {
            return false;
        }

        // Copia todas as metas relevantes
        $metas_to_copy = [
            'service_type',
            'service_category',
            'service_price',
            'service_duration',
            'service_price_small',
            'service_price_medium',
            'service_price_large',
            'service_duration_small',
            'service_duration_medium',
            'service_duration_large',
            'service_package_items',
            'service_package_discount',
            'service_package_fixed_price',
            'dps_service_stock_consumption',
        ];

        foreach ( $metas_to_copy as $meta_key ) {
            $value = get_post_meta( $service_id, $meta_key, true );
            if ( '' !== $value && null !== $value ) {
                update_post_meta( $new_id, $meta_key, $value );
            }
        }

        // Marca como inativo por padrão (segurança)
        update_post_meta( $new_id, 'service_active', '0' );

        /**
         * Ação disparada após duplicar um serviço.
         *
         * @param int $new_id     ID do novo serviço.
         * @param int $service_id ID do serviço original.
         *
         * @since 1.3.0
         */
        do_action( 'dps_service_duplicated', $new_id, $service_id );

        return $new_id;
    }

    /**
     * Registra uma alteração de preço no histórico.
     *
     * @param int    $service_id ID do serviço.
     * @param string $price_type Tipo de preço: 'base', 'small', 'medium', 'large'.
     * @param float  $old_price  Preço anterior.
     * @param float  $new_price  Novo preço.
     * @return bool True se registrado, false se não houve mudança.
     *
     * @since 1.3.0
     */
    public static function log_price_change( $service_id, $price_type, $old_price, $new_price ) {
        $old_price = (float) $old_price;
        $new_price = (float) $new_price;

        // Ignora se não houve mudança significativa
        if ( abs( $old_price - $new_price ) < 0.01 ) {
            return false;
        }

        $history = get_post_meta( $service_id, 'service_price_history', true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $history[] = [
            'date'       => current_time( 'mysql' ),
            'user_id'    => get_current_user_id(),
            'old_price'  => $old_price,
            'new_price'  => $new_price,
            'price_type' => sanitize_key( $price_type ),
        ];

        // Mantém apenas os últimos 50 registros
        if ( count( $history ) > 50 ) {
            $history = array_slice( $history, -50 );
        }

        update_post_meta( $service_id, 'service_price_history', $history );

        return true;
    }
}
