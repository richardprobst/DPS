<?php
/**
 * Service de clientes (Fase 7).
 *
 * CRUD para o post type dps_cliente. Cria clientes com metas
 * padronizadas conforme o add-on legado de registro.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Client_Service extends DPS_Abstract_Service {

    protected function postType(): string {
        return 'dps_cliente';
    }

    /**
     * Cria um novo cliente.
     *
     * @param array<string, mixed> $data Dados do cliente.
     * @return int|false ID do cliente criado ou false em caso de erro.
     */
    public function create( array $data ): int|false {
        $name  = $data['client_name'] ?? '';
        $phone = $this->normalizePhone( $data['client_phone'] ?? '' );

        $postData = [
            'post_title'  => sanitize_text_field( $name ),
            'post_status' => 'publish',
        ];

        $meta = [
            'client_cpf'       => sanitize_text_field( $data['client_cpf'] ?? '' ),
            'client_phone'     => sanitize_text_field( $phone ),
            'client_email'     => sanitize_email( $data['client_email'] ?? '' ),
            'client_birth'     => sanitize_text_field( $data['client_birth'] ?? '' ),
            'client_instagram' => sanitize_text_field( $data['client_instagram'] ?? '' ),
            'client_facebook'  => sanitize_text_field( $data['client_facebook'] ?? '' ),
            'client_photo_auth' => sanitize_text_field( $data['client_photo_auth'] ?? '' ),
            'client_address'   => sanitize_text_field( $data['client_address'] ?? '' ),
            'client_referral'  => sanitize_text_field( $data['client_referral'] ?? '' ),
            'client_lat'       => sanitize_text_field( $data['client_lat'] ?? '' ),
            'client_lng'       => sanitize_text_field( $data['client_lng'] ?? '' ),
            'dps_email_confirmed'     => '0',
            'dps_is_active'           => '1',
            'dps_registration_source' => 'frontend_v2',
        ];

        // Referral code (para Loyalty)
        if ( ! empty( $data['dps_referral_code'] ) ) {
            $meta['dps_registration_ref'] = sanitize_text_field( $data['dps_referral_code'] );
        }

        // Marketing opt-in
        if ( ! empty( $data['marketing_optin'] ) ) {
            $meta['dps_marketing_optin'] = '1';
        }

        return $this->createPost( $postData, $meta );
    }

    /**
     * Normaliza telefone: remove formatação, mantém dígitos.
     * Remove código de país 55 se presente.
     *
     * @param string $phone Telefone bruto.
     * @return string Telefone normalizado.
     */
    public function normalizePhone( string $phone ): string {
        $digits = preg_replace( '/\D/', '', $phone );

        // Remove código do país (55) se presente (12-13 dígitos)
        if ( strlen( $digits ) >= 12 && str_starts_with( $digits, '55' ) ) {
            $digits = substr( $digits, 2 );
        }

        // Usa DPS_Phone_Helper se disponível
        if ( class_exists( 'DPS_Phone_Helper' ) && method_exists( DPS_Phone_Helper::class, 'normalize' ) ) {
            $normalized = DPS_Phone_Helper::normalize( $phone );
            if ( '' !== $normalized ) {
                return $normalized;
            }
        }

        return $digits;
    }
}
