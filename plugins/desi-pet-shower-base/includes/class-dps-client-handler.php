<?php
/**
 * Handler para operações CRUD de clientes.
 *
 * Extraído de class-dps-base-frontend.php como parte da Fase 2.1
 * do Plano de Implementação (decomposição do monólito).
 *
 * @package Desi_Pet_Shower_Base
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por salvar, atualizar e excluir clientes.
 *
 * Métodos estáticos para manter compatibilidade com DPS_Base_Frontend.
 */
class DPS_Client_Handler {

    /**
     * Meta keys utilizadas pelo CPT dps_cliente.
     *
     * @var array
     */
    const META_KEYS = [
        'client_cpf',
        'client_phone',
        'client_email',
        'client_birth',
        'client_instagram',
        'client_facebook',
        'client_photo_auth',
        'client_address',
        'client_referral',
        'client_lat',
        'client_lng',
    ];

    /**
     * Salva cliente (inserção ou atualização) a partir de dados do $_POST.
     *
     * @param callable $redirect_url_fn Função que retorna a URL de redirecionamento.
     *                                  Assinatura: function( string $tab ): string
     * @return void Redireciona e encerra execução.
     */
    public static function save_from_request( $redirect_url_fn ) {
        if ( ! current_user_can( 'dps_manage_clients' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $data = self::extract_post_data();
        $errors = self::validate( $data );

        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            wp_safe_redirect( call_user_func( $redirect_url_fn, 'clientes' ) );
            exit;
        }

        $client_id = self::save( $data );

        if ( is_wp_error( $client_id ) || ! $client_id ) {
            DPS_Message_Helper::add_error( __( 'Não foi possível salvar o cliente. Tente novamente.', 'desi-pet-shower' ) );
            if ( is_wp_error( $client_id ) ) {
                DPS_Message_Helper::add_error( $client_id->get_error_message() );
            }
            wp_safe_redirect( call_user_func( $redirect_url_fn, 'clientes' ) );
            exit;
        }

        DPS_Message_Helper::add_success( __( 'Cliente salvo com sucesso!', 'desi-pet-shower' ) );
        wp_safe_redirect( call_user_func( $redirect_url_fn, 'clientes' ) );
        exit;
    }

    /**
     * Extrai e sanitiza dados do cliente de $_POST.
     *
     * @return array Dados sanitizados.
     */
    public static function extract_post_data() {
        return [
            'client_id' => isset( $_POST['client_id'] ) ? intval( wp_unslash( $_POST['client_id'] ) ) : 0,
            'name'      => isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '',
            'cpf'       => isset( $_POST['client_cpf'] ) ? sanitize_text_field( wp_unslash( $_POST['client_cpf'] ) ) : '',
            'phone'     => isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '',
            'email'     => isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '',
            'birth'     => isset( $_POST['client_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['client_birth'] ) ) : '',
            'instagram' => isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '',
            'facebook'  => isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '',
            'photo_auth'=> isset( $_POST['client_photo_auth'] ) ? 1 : 0,
            'address'   => isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '',
            'referral'  => isset( $_POST['client_referral'] ) ? sanitize_text_field( wp_unslash( $_POST['client_referral'] ) ) : '',
            'lat'       => isset( $_POST['client_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lat'] ) ) : '',
            'lng'       => isset( $_POST['client_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['client_lng'] ) ) : '',
        ];
    }

    /**
     * Valida dados do cliente.
     *
     * @param array $data Dados extraídos.
     * @return array Lista de mensagens de erro (vazio se válido).
     */
    public static function validate( $data ) {
        $errors = [];

        if ( empty( $data['name'] ) ) {
            $errors[] = __( 'O campo Nome é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $data['phone'] ) ) {
            $errors[] = __( 'O campo Telefone é obrigatório.', 'desi-pet-shower' );
        }

        return $errors;
    }

    /**
     * Salva ou atualiza um cliente no banco de dados.
     *
     * @param array $data Dados sanitizados do cliente.
     * @return int|WP_Error ID do cliente salvo ou WP_Error.
     */
    public static function save( $data ) {
        $client_id = ! empty( $data['client_id'] ) ? $data['client_id'] : 0;

        if ( $client_id ) {
            $result = wp_update_post( [
                'ID'         => $client_id,
                'post_title' => $data['name'],
            ], true );
        } else {
            $result = wp_insert_post( [
                'post_type'   => 'dps_cliente',
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ], true );
        }

        if ( is_wp_error( $result ) || ! $result ) {
            return $result;
        }

        $client_id = $result;

        // Salva metadados
        update_post_meta( $client_id, 'client_cpf', $data['cpf'] );
        update_post_meta( $client_id, 'client_phone', $data['phone'] );
        update_post_meta( $client_id, 'client_email', $data['email'] );
        update_post_meta( $client_id, 'client_birth', $data['birth'] );
        update_post_meta( $client_id, 'client_instagram', $data['instagram'] );
        update_post_meta( $client_id, 'client_facebook', $data['facebook'] );
        update_post_meta( $client_id, 'client_photo_auth', $data['photo_auth'] );
        update_post_meta( $client_id, 'client_address', $data['address'] );
        update_post_meta( $client_id, 'client_referral', $data['referral'] );

        // Salva coordenadas se fornecidas
        if ( $data['lat'] !== '' && $data['lng'] !== '' ) {
            update_post_meta( $client_id, 'client_lat', $data['lat'] );
            update_post_meta( $client_id, 'client_lng', $data['lng'] );
        }

        // Auditoria (Fase 6.2)
        $action = ! empty( $data['client_id'] ) ? 'update' : 'create';
        DPS_Audit_Logger::log_client_change( $client_id, $action, [
            'name'  => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ] );

        return $client_id;
    }

    /**
     * Exclui um cliente.
     *
     * @param int $client_id ID do cliente.
     * @return bool True se excluído com sucesso.
     */
    public static function delete( $client_id ) {
        if ( ! current_user_can( 'dps_manage_clients' ) ) {
            return false;
        }

        $client_name = get_the_title( $client_id );
        $result      = (bool) wp_delete_post( $client_id, true );

        if ( $result ) {
            DPS_Audit_Logger::log_client_change( $client_id, 'delete', [
                'name' => $client_name,
            ] );
        }

        return $result;
    }
}
