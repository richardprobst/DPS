<?php
/**
 * Helper class for client data access and manipulation.
 *
 * Centralizes access to client metadata (phone, email, address, etc.)
 * following the DRY principle. Provides consistent data retrieval
 * from both post_meta (dps_client CPT) and usermeta (WordPress users).
 *
 * @package DPS_Base
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DPS_Client_Helper
 *
 * Provides static methods for accessing and manipulating client data.
 *
 * @since 2.0.0
 */
class DPS_Client_Helper {

    /**
     * Meta keys used for client data.
     *
     * @since 2.0.0
     */
    const META_PHONE     = 'client_phone';
    const META_EMAIL     = 'client_email';
    const META_WHATSAPP  = 'client_whatsapp';
    const META_ADDRESS   = 'client_address';
    const META_CITY      = 'client_city';
    const META_STATE     = 'client_state';
    const META_ZIP       = 'client_zip';
    const META_COUNTRY   = 'client_country';
    const META_NOTES     = 'client_notes';

    /**
     * Get client phone number.
     *
     * Retrieves phone from post_meta (dps_client CPT) or usermeta.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return string Phone number or empty string.
     */
    public static function get_phone( int $client_id, ?string $source = null ): string {
        return self::get_meta( $client_id, self::META_PHONE, $source );
    }

    /**
     * Get client email address.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return string Email address or empty string.
     */
    public static function get_email( int $client_id, ?string $source = null ): string {
        $email = self::get_meta( $client_id, self::META_EMAIL, $source );
        
        // For users, fallback to user_email if meta is empty
        if ( empty( $email ) && ( $source === 'user' || ( $source === null && ! get_post( $client_id ) ) ) ) {
            $user = get_userdata( $client_id );
            if ( $user ) {
                $email = $user->user_email;
            }
        }
        
        return $email;
    }

    /**
     * Get client WhatsApp number.
     *
     * Falls back to phone if WhatsApp-specific field is empty.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return string WhatsApp number or empty string.
     */
    public static function get_whatsapp( int $client_id, ?string $source = null ): string {
        $whatsapp = self::get_meta( $client_id, self::META_WHATSAPP, $source );
        
        // Fallback to phone if WhatsApp field is empty
        if ( empty( $whatsapp ) ) {
            $whatsapp = self::get_phone( $client_id, $source );
        }
        
        return $whatsapp;
    }

    /**
     * Get client name.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return string Client name or empty string.
     */
    public static function get_name( int $client_id, ?string $source = null ): string {
        $detected_source = self::detect_source( $client_id, $source );
        
        if ( $detected_source === 'post' ) {
            return get_the_title( $client_id );
        }
        
        $user = get_userdata( $client_id );
        if ( $user ) {
            $name = trim( $user->first_name . ' ' . $user->last_name );
            return ! empty( $name ) ? $name : $user->display_name;
        }
        
        return '';
    }

    /**
     * Get client display name (formatted for UI).
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return string Display name.
     */
    public static function get_display_name( int $client_id, ?string $source = null ): string {
        $name = self::get_name( $client_id, $source );
        return ! empty( $name ) ? $name : __( 'Cliente sem nome', 'desi-pet-shower' );
    }

    /**
     * Get formatted address.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @param string      $separator Separator between address parts.
     * @return string Formatted address.
     */
    public static function get_address( int $client_id, ?string $source = null, string $separator = ', ' ): string {
        $parts = array_filter( [
            self::get_meta( $client_id, self::META_ADDRESS, $source ),
            self::get_meta( $client_id, self::META_CITY, $source ),
            self::get_meta( $client_id, self::META_STATE, $source ),
            self::get_meta( $client_id, self::META_ZIP, $source ),
        ] );
        
        return implode( $separator, $parts );
    }

    /**
     * Get all client metadata at once.
     *
     * More efficient than multiple individual calls.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type: 'post', 'user', or null for auto-detect.
     * @return array Associative array with all client data.
     */
    public static function get_all_data( int $client_id, ?string $source = null ): array {
        return [
            'id'       => $client_id,
            'name'     => self::get_name( $client_id, $source ),
            'phone'    => self::get_phone( $client_id, $source ),
            'email'    => self::get_email( $client_id, $source ),
            'whatsapp' => self::get_whatsapp( $client_id, $source ),
            'address'  => self::get_address( $client_id, $source ),
            'city'     => self::get_meta( $client_id, self::META_CITY, $source ),
            'state'    => self::get_meta( $client_id, self::META_STATE, $source ),
            'zip'      => self::get_meta( $client_id, self::META_ZIP, $source ),
            'notes'    => self::get_meta( $client_id, self::META_NOTES, $source ),
        ];
    }

    /**
     * Check if client has a valid phone number.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type.
     * @return bool True if has valid phone.
     */
    public static function has_valid_phone( int $client_id, ?string $source = null ): bool {
        $phone = self::get_phone( $client_id, $source );
        
        if ( empty( $phone ) ) {
            return false;
        }
        
        // Use DPS_Phone_Helper if available
        if ( class_exists( 'DPS_Phone_Helper' ) ) {
            return DPS_Phone_Helper::is_valid( $phone );
        }
        
        // Basic validation: at least 8 digits
        $digits = preg_replace( '/\D/', '', $phone );
        return strlen( $digits ) >= 8;
    }

    /**
     * Check if client has a valid email.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type.
     * @return bool True if has valid email.
     */
    public static function has_valid_email( int $client_id, ?string $source = null ): bool {
        $email = self::get_email( $client_id, $source );
        return ! empty( $email ) && is_email( $email );
    }

    /**
     * Get pets associated with a client.
     *
     * @since 2.0.0
     *
     * @param int   $client_id Client post ID.
     * @param array $args      Optional. Additional WP_Query arguments.
     * @return array Array of pet post objects or IDs.
     */
    public static function get_pets( int $client_id, array $args = [] ): array {
        $defaults = [
            'post_type'      => 'dps_pet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => 'pet_owner',
                    'value' => $client_id,
                ],
            ],
        ];
        
        $query_args = wp_parse_args( $args, $defaults );
        $query = new WP_Query( $query_args );
        
        return $query->posts;
    }

    /**
     * Get count of pets for a client.
     *
     * More efficient than get_pets() when only count is needed.
     *
     * @since 2.0.0
     *
     * @param int $client_id Client post ID.
     * @return int Number of pets.
     */
    public static function get_pets_count( int $client_id ): int {
        return count( self::get_pets( $client_id, [ 'fields' => 'ids' ] ) );
    }

    /**
     * Get primary/first pet for a client.
     *
     * @since 2.0.0
     *
     * @param int $client_id Client post ID.
     * @return WP_Post|null Pet post object or null.
     */
    public static function get_primary_pet( int $client_id ): ?WP_Post {
        $pets = self::get_pets( $client_id, [ 'posts_per_page' => 1 ] );
        return ! empty( $pets ) ? $pets[0] : null;
    }

    /**
     * Format contact information for display.
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type.
     * @return string HTML formatted contact info.
     */
    public static function format_contact_info( int $client_id, ?string $source = null ): string {
        $parts = [];
        
        $phone = self::get_phone( $client_id, $source );
        if ( ! empty( $phone ) ) {
            if ( class_exists( 'DPS_Phone_Helper' ) ) {
                $phone = DPS_Phone_Helper::format( $phone );
            }
            $parts[] = sprintf(
                '<span class="dps-contact-phone">%s %s</span>',
                esc_html__( 'Tel:', 'desi-pet-shower' ),
                esc_html( $phone )
            );
        }
        
        $email = self::get_email( $client_id, $source );
        if ( ! empty( $email ) ) {
            $parts[] = sprintf(
                '<span class="dps-contact-email">%s <a href="mailto:%s">%s</a></span>',
                esc_html__( 'Email:', 'desi-pet-shower' ),
                esc_attr( $email ),
                esc_html( $email )
            );
        }
        
        return implode( ' | ', $parts );
    }

    /**
     * Get client data formatted for display (UI-ready).
     *
     * @since 2.0.0
     *
     * @param int         $client_id Client post ID or user ID.
     * @param string|null $source    Source type.
     * @return array Array with formatted display data.
     */
    public static function get_for_display( int $client_id, ?string $source = null ): array {
        $data = self::get_all_data( $client_id, $source );
        
        // Format phone if helper is available
        if ( ! empty( $data['phone'] ) && class_exists( 'DPS_Phone_Helper' ) ) {
            $data['phone_formatted'] = DPS_Phone_Helper::format( $data['phone'] );
        } else {
            $data['phone_formatted'] = $data['phone'];
        }
        
        // Add display name
        $data['display_name'] = self::get_display_name( $client_id, $source );
        
        // Add contact info HTML
        $data['contact_html'] = self::format_contact_info( $client_id, $source );
        
        // Add pets count
        if ( self::detect_source( $client_id, $source ) === 'post' ) {
            $data['pets_count'] = self::get_pets_count( $client_id );
        }
        
        return $data;
    }

    /**
     * Search for a client by phone number.
     *
     * @since 2.0.0
     *
     * @param string $phone    Phone number to search.
     * @param bool   $exact    Whether to do exact match (default: false).
     * @return int|null Client post ID or null if not found.
     */
    public static function search_by_phone( string $phone, bool $exact = false ): ?int {
        // Normalize phone for search
        $normalized = preg_replace( '/\D/', '', $phone );
        
        if ( empty( $normalized ) ) {
            return null;
        }
        
        $meta_query = [
            'relation' => 'OR',
            [
                'key'     => self::META_PHONE,
                'value'   => $exact ? $normalized : $normalized,
                'compare' => $exact ? '=' : 'LIKE',
            ],
            [
                'key'     => self::META_WHATSAPP,
                'value'   => $exact ? $normalized : $normalized,
                'compare' => $exact ? '=' : 'LIKE',
            ],
        ];
        
        $query = new WP_Query( [
            'post_type'      => 'dps_client',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ] );
        
        return ! empty( $query->posts ) ? $query->posts[0] : null;
    }

    /**
     * Search for a client by email.
     *
     * @since 2.0.0
     *
     * @param string $email Email to search.
     * @return int|null Client post ID or null if not found.
     */
    public static function search_by_email( string $email ): ?int {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return null;
        }
        
        $query = new WP_Query( [
            'post_type'      => 'dps_client',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => self::META_EMAIL,
                    'value' => sanitize_email( $email ),
                ],
            ],
        ] );
        
        return ! empty( $query->posts ) ? $query->posts[0] : null;
    }

    /**
     * Get metadata value from post or user.
     *
     * @since 2.0.0
     *
     * @param int         $id     Post ID or user ID.
     * @param string      $key    Meta key.
     * @param string|null $source Source type: 'post', 'user', or null for auto-detect.
     * @return string Meta value or empty string.
     */
    private static function get_meta( int $id, string $key, ?string $source = null ): string {
        $detected_source = self::detect_source( $id, $source );
        
        if ( $detected_source === 'post' ) {
            $value = get_post_meta( $id, $key, true );
        } else {
            $value = get_user_meta( $id, $key, true );
        }
        
        return is_string( $value ) ? $value : '';
    }

    /**
     * Detect source type (post or user).
     *
     * @since 2.0.0
     *
     * @param int         $id     ID to check.
     * @param string|null $source Explicit source or null for auto-detect.
     * @return string 'post' or 'user'.
     */
    private static function detect_source( int $id, ?string $source = null ): string {
        if ( $source !== null ) {
            return $source;
        }
        
        // Check if it's a dps_client post
        $post = get_post( $id );
        if ( $post && $post->post_type === 'dps_client' ) {
            return 'post';
        }
        
        // Check if it's a user
        if ( get_userdata( $id ) ) {
            return 'user';
        }
        
        // Default to post for backward compatibility
        return 'post';
    }
}
