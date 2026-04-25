<?php
/**
 * Persistent opt-in draft service for the Registration add-on.
 *
 * @package DPS_Registration_Addon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Saves and restores registration drafts without browser storage or cache APIs.
 */
class DPS_Registration_Draft_Service {

    const COOKIE_NAME  = 'dps_registration_draft_token';
    const EVENT_TYPE   = 'draft';
    const NONCE_ACTION = 'dps_registration_draft';
    const TTL          = WEEK_IN_SECONDS;

    /**
     * Returns localized JavaScript config.
     *
     * @return array
     */
    public static function get_localized_config() {
        $token = self::get_current_token();
        $draft = $token ? self::load_draft( $token ) : array();

        return array(
            'enabled'      => true,
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'saveAction'   => 'dps_registration_save_draft',
            'clearAction'  => 'dps_registration_clear_draft',
            'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
            'token'        => $token,
            'hasDraft'     => ! empty( $draft ),
            'payload'      => ! empty( $draft ) ? $draft : null,
            'saveDelay'    => 900,
            'i18n'         => array(
                'saving'   => __( 'Salvando rascunho...', 'dps-registration-addon' ),
                'saved'    => __( 'Rascunho salvo.', 'dps-registration-addon' ),
                'error'    => __( 'Não foi possível salvar o rascunho agora.', 'dps-registration-addon' ),
                'restored' => __( 'Rascunho restaurado.', 'dps-registration-addon' ),
                'cleared'  => __( 'Rascunho descartado.', 'dps-registration-addon' ),
            ),
        );
    }

    /**
     * Handles public draft save.
     *
     * @return void
     */
    public static function ajax_save() {
        if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Falha de seguranca.', 'dps-registration-addon' ) ), 403 );
        }

        $token = isset( $_POST['draft_token'] ) ? sanitize_text_field( wp_unslash( $_POST['draft_token'] ) ) : '';
        if ( ! self::is_valid_token( $token ) ) {
            $token = self::create_token();
        }

        $payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
        $decoded     = json_decode( (string) $payload_raw, true );
        $payload     = self::sanitize_payload( is_array( $decoded ) ? $decoded : array() );

        self::set_cookie( $token );

        if ( empty( $payload ) ) {
            self::clear_draft( $token );
            wp_send_json_success(
                array(
                    'token'   => $token,
                    'cleared' => true,
                )
            );
        }

        $saved = self::save_draft( $token, $payload );

        if ( ! $saved ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível salvar o rascunho.', 'dps-registration-addon' ) ), 500 );
        }

        wp_send_json_success(
            array(
                'token' => $token,
            )
        );
    }

    /**
     * Handles public draft cleanup.
     *
     * @return void
     */
    public static function ajax_clear() {
        if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Falha de seguranca.', 'dps-registration-addon' ) ), 403 );
        }

        $token = isset( $_POST['draft_token'] ) ? sanitize_text_field( wp_unslash( $_POST['draft_token'] ) ) : self::get_current_token();
        if ( self::is_valid_token( $token ) ) {
            self::clear_draft( $token );
        }

        self::clear_cookie();
        wp_send_json_success();
    }

    /**
     * Loads the current draft from the cookie token.
     *
     * @return array
     */
    public static function load_current_draft() {
        $token = self::get_current_token();

        return $token ? self::load_draft( $token ) : array();
    }

    /**
     * Returns the token stored in the request cookie.
     *
     * @return string
     */
    public static function get_current_token() {
        $token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

        return self::is_valid_token( $token ) ? $token : '';
    }

    /**
     * Saves a draft payload.
     *
     * @param string $token   Draft token.
     * @param array  $payload Sanitized payload.
     * @return bool
     */
    public static function save_draft( $token, array $payload ) {
        return DPS_Registration_Storage::set_payload(
            self::EVENT_TYPE,
            self::hash_token( $token ),
            $payload,
            self::TTL
        );
    }

    /**
     * Loads a draft payload by token.
     *
     * @param string $token Draft token.
     * @return array
     */
    public static function load_draft( $token ) {
        if ( ! self::is_valid_token( $token ) ) {
            return array();
        }

        $payload = DPS_Registration_Storage::get_latest_payload( self::EVENT_TYPE, self::hash_token( $token ) );

        return self::sanitize_payload( $payload );
    }

    /**
     * Deletes a draft by token.
     *
     * @param string $token Draft token.
     * @return void
     */
    public static function clear_draft( $token ) {
        if ( ! self::is_valid_token( $token ) ) {
            return;
        }

        DPS_Registration_Storage::delete_bucket_events( self::EVENT_TYPE, self::hash_token( $token ) );
    }

    /**
     * Sanitizes a draft payload.
     *
     * @param array $payload Raw decoded payload.
     * @return array
     */
    public static function sanitize_payload( array $payload ) {
        $client = isset( $payload['client'] ) && is_array( $payload['client'] ) ? $payload['client'] : array();
        $pets   = isset( $payload['pets'] ) && is_array( $payload['pets'] ) ? $payload['pets'] : array();
        $prefs  = isset( $payload['productPreferences'] ) && is_array( $payload['productPreferences'] ) ? $payload['productPreferences'] : array();

        $clean = array(
            'client'             => self::sanitize_client( $client ),
            'pets'               => array(),
            'productPreferences' => array(),
        );

        foreach ( array_slice( $pets, 0, 8 ) as $pet ) {
            if ( is_array( $pet ) ) {
                $clean['pets'][] = self::sanitize_pet( $pet );
            }
        }

        foreach ( array_slice( $prefs, 0, 8 ) as $pref ) {
            if ( is_array( $pref ) ) {
                $clean['productPreferences'][] = self::sanitize_preference( $pref );
            }
        }

        if ( empty( array_filter( $clean['client'] ) ) && empty( $clean['pets'] ) && empty( $clean['productPreferences'] ) ) {
            return array();
        }

        return $clean;
    }

    /**
     * Creates a token.
     *
     * @return string
     */
    private static function create_token() {
        return str_replace( '-', '', wp_generate_uuid4() );
    }

    /**
     * Validates a token.
     *
     * @param string $token Token.
     * @return bool
     */
    private static function is_valid_token( $token ) {
        return is_string( $token ) && (bool) preg_match( '/^[a-f0-9]{32}$/', $token );
    }

    /**
     * Hashes a token before storage lookup.
     *
     * @param string $token Token.
     * @return string
     */
    private static function hash_token( $token ) {
        return hash( 'sha256', $token );
    }

    /**
     * Persists the draft token cookie.
     *
     * @param string $token Token.
     * @return void
     */
    private static function set_cookie( $token ) {
        setcookie(
            self::COOKIE_NAME,
            $token,
            array(
                'expires'  => time() + self::TTL,
                'path'     => COOKIEPATH ? COOKIEPATH : '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    /**
     * Clears the draft token cookie.
     *
     * @return void
     */
    private static function clear_cookie() {
        setcookie(
            self::COOKIE_NAME,
            '',
            array(
                'expires'  => time() - DAY_IN_SECONDS,
                'path'     => COOKIEPATH ? COOKIEPATH : '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    /**
     * Sanitizes tutor data.
     *
     * @param array $client Raw client data.
     * @return array
     */
    private static function sanitize_client( array $client ) {
        return array(
            'client_name'       => self::limit_text( $client['client_name'] ?? '' ),
            'client_cpf'        => self::limit_text( $client['client_cpf'] ?? '' ),
            'client_phone'      => self::limit_text( $client['client_phone'] ?? '' ),
            'client_email'      => sanitize_email( $client['client_email'] ?? '' ),
            'client_birth'      => self::limit_text( $client['client_birth'] ?? '' ),
            'client_instagram'  => self::limit_text( $client['client_instagram'] ?? '' ),
            'client_facebook'   => self::limit_text( $client['client_facebook'] ?? '' ),
            'client_photo_auth' => ! empty( $client['client_photo_auth'] ) ? '1' : '',
            'client_address'    => self::limit_textarea( $client['client_address'] ?? '' ),
            'client_referral'   => self::limit_text( $client['client_referral'] ?? '' ),
        );
    }

    /**
     * Sanitizes pet data.
     *
     * @param array $pet Raw pet data.
     * @return array
     */
    private static function sanitize_pet( array $pet ) {
        return array(
            'pet_name'       => self::limit_text( $pet['pet_name'] ?? '' ),
            'pet_species'    => self::limit_choice( $pet['pet_species'] ?? '', array( '', 'cao', 'gato', 'outro' ) ),
            'pet_breed'      => self::limit_text( $pet['pet_breed'] ?? '' ),
            'pet_size'       => self::limit_choice( $pet['pet_size'] ?? '', array( '', 'pequeno', 'medio', 'grande' ) ),
            'pet_weight'     => self::limit_text( $pet['pet_weight'] ?? '' ),
            'pet_coat'       => self::limit_text( $pet['pet_coat'] ?? '' ),
            'pet_color'      => self::limit_text( $pet['pet_color'] ?? '' ),
            'pet_birth'      => self::limit_text( $pet['pet_birth'] ?? '' ),
            'pet_sex'        => self::limit_choice( $pet['pet_sex'] ?? '', array( '', 'macho', 'femea' ) ),
            'pet_care'       => self::limit_textarea( $pet['pet_care'] ?? '' ),
            'pet_aggressive' => ! empty( $pet['pet_aggressive'] ) ? '1' : '',
        );
    }

    /**
     * Sanitizes product preference data.
     *
     * @param array $pref Raw preference data.
     * @return array
     */
    private static function sanitize_preference( array $pref ) {
        return array(
            'pet_shampoo_pref'         => self::limit_text( $pref['pet_shampoo_pref'] ?? '' ),
            'pet_perfume_pref'         => self::limit_text( $pref['pet_perfume_pref'] ?? '' ),
            'pet_accessories_pref'     => self::limit_text( $pref['pet_accessories_pref'] ?? '' ),
            'pet_product_restrictions' => self::limit_textarea( $pref['pet_product_restrictions'] ?? '' ),
        );
    }

    /**
     * Sanitizes and limits short text.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function limit_text( $value ) {
        return self::limit_string( sanitize_text_field( (string) $value ), 160 );
    }

    /**
     * Sanitizes and limits textarea text.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function limit_textarea( $value ) {
        return self::limit_string( sanitize_textarea_field( (string) $value ), 600 );
    }

    /**
     * Sanitizes a choice.
     *
     * @param mixed $value   Raw value.
     * @param array $allowed Allowed values.
     * @return string
     */
    private static function limit_choice( $value, array $allowed ) {
        $value = sanitize_key( (string) $value );

        return in_array( $value, $allowed, true ) ? $value : '';
    }

    /**
     * Limits a string without requiring mbstring.
     *
     * @param string $value Raw string.
     * @param int    $limit Character limit.
     * @return string
     */
    private static function limit_string( $value, $limit ) {
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $limit );
        }

        return substr( $value, 0, $limit );
    }
}
