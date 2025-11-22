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
}
