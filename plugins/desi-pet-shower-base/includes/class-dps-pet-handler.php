<?php
/**
 * Handler para operações CRUD de pets.
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
 * Classe responsável por salvar, atualizar e excluir pets.
 *
 * Métodos estáticos para manter compatibilidade com DPS_Base_Frontend.
 */
class DPS_Pet_Handler {

    /**
     * Meta keys utilizadas pelo CPT dps_pet.
     *
     * @var array
     */
    const META_KEYS = [
        'owner_id',
        'pet_species',
        'pet_breed',
        'pet_size',
        'pet_weight',
        'pet_coat',
        'pet_color',
        'pet_birth',
        'pet_sex',
        'pet_care',
        'pet_aggressive',
        'pet_vaccinations',
        'pet_allergies',
        'pet_behavior',
        'pet_shampoo_pref',
        'pet_perfume_pref',
        'pet_accessories_pref',
        'pet_product_restrictions',
        'pet_photo_id',
    ];

    /**
     * MIME types permitidos para fotos de pets.
     *
     * @var array
     */
    const ALLOWED_MIMES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * Salva pet (inserção ou atualização) a partir de dados do $_POST.
     *
     * @param callable $redirect_url_fn Função que retorna a URL de redirecionamento.
     *                                  Assinatura: function( string $tab ): string
     * @return void Redireciona e encerra execução.
     */
    public static function save_from_request( $redirect_url_fn ) {
        if ( ! current_user_can( 'dps_manage_pets' ) ) {
            wp_die( __( 'Acesso negado.', 'desi-pet-shower' ) );
        }

        $data = self::extract_post_data();
        $errors = self::validate( $data );

        if ( ! empty( $errors ) ) {
            foreach ( $errors as $error ) {
                DPS_Message_Helper::add_error( $error );
            }
            wp_safe_redirect( call_user_func( $redirect_url_fn, 'pets' ) );
            exit;
        }

        $pet_id = self::save( $data );

        if ( is_wp_error( $pet_id ) || ! $pet_id ) {
            DPS_Message_Helper::add_error( __( 'Não foi possível salvar o pet. Tente novamente.', 'desi-pet-shower' ) );
            if ( is_wp_error( $pet_id ) ) {
                DPS_Message_Helper::add_error( $pet_id->get_error_message() );
            }
            wp_safe_redirect( call_user_func( $redirect_url_fn, 'pets' ) );
            exit;
        }

        // Lida com upload da foto do pet
        self::handle_photo_upload( $pet_id );

        DPS_Message_Helper::add_success( __( 'Pet salvo com sucesso!', 'desi-pet-shower' ) );
        wp_safe_redirect( call_user_func( $redirect_url_fn, 'pets' ) );
        exit;
    }

    /**
     * Extrai e sanitiza dados do pet de $_POST.
     *
     * @return array Dados sanitizados.
     */
    public static function extract_post_data() {
        return [
            'pet_id'               => isset( $_POST['pet_id'] ) ? intval( wp_unslash( $_POST['pet_id'] ) ) : 0,
            'owner_id'             => isset( $_POST['owner_id'] ) ? intval( wp_unslash( $_POST['owner_id'] ) ) : 0,
            'name'                 => isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '',
            'species'              => isset( $_POST['pet_species'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_species'] ) ) : '',
            'breed'                => isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '',
            'size'                 => isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '',
            'weight'               => isset( $_POST['pet_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_weight'] ) ) : '',
            'coat'                 => isset( $_POST['pet_coat'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_coat'] ) ) : '',
            'color'                => isset( $_POST['pet_color'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_color'] ) ) : '',
            'birth'                => isset( $_POST['pet_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_birth'] ) ) : '',
            'sex'                  => isset( $_POST['pet_sex'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_sex'] ) ) : '',
            'care'                 => isset( $_POST['pet_care'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_care'] ) ) : '',
            'aggressive'           => isset( $_POST['pet_aggressive'] ) ? 1 : 0,
            'vaccinations'         => isset( $_POST['pet_vaccinations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_vaccinations'] ) ) : '',
            'allergies'            => isset( $_POST['pet_allergies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_allergies'] ) ) : '',
            'behavior'             => isset( $_POST['pet_behavior'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior'] ) ) : '',
            'shampoo_pref'         => isset( $_POST['pet_shampoo_pref'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_shampoo_pref'] ) ) : '',
            'perfume_pref'         => isset( $_POST['pet_perfume_pref'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_perfume_pref'] ) ) : '',
            'accessories_pref'     => isset( $_POST['pet_accessories_pref'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_accessories_pref'] ) ) : '',
            'product_restrictions' => isset( $_POST['pet_product_restrictions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_product_restrictions'] ) ) : '',
        ];
    }

    /**
     * Valida dados do pet.
     *
     * @param array $data Dados extraídos.
     * @return array Lista de mensagens de erro (vazio se válido).
     */
    public static function validate( $data ) {
        $errors = [];

        if ( empty( $data['name'] ) ) {
            $errors[] = __( 'O campo Nome do Pet é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $data['owner_id'] ) ) {
            $errors[] = __( 'O campo Cliente (Tutor) é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $data['species'] ) ) {
            $errors[] = __( 'O campo Espécie é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $data['size'] ) ) {
            $errors[] = __( 'O campo Tamanho é obrigatório.', 'desi-pet-shower' );
        }
        if ( empty( $data['sex'] ) ) {
            $errors[] = __( 'O campo Sexo é obrigatório.', 'desi-pet-shower' );
        }

        return $errors;
    }

    /**
     * Salva ou atualiza um pet no banco de dados.
     *
     * @param array $data Dados sanitizados do pet.
     * @return int|WP_Error ID do pet salvo ou WP_Error.
     */
    public static function save( $data ) {
        $pet_id = ! empty( $data['pet_id'] ) ? $data['pet_id'] : 0;

        if ( $pet_id ) {
            $result = wp_update_post( [
                'ID'         => $pet_id,
                'post_title' => $data['name'],
            ], true );
        } else {
            $result = wp_insert_post( [
                'post_type'   => 'dps_pet',
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ], true );
        }

        if ( is_wp_error( $result ) || ! $result ) {
            return $result;
        }

        $pet_id = $result;

        // Salva metadados
        update_post_meta( $pet_id, 'owner_id', $data['owner_id'] );
        update_post_meta( $pet_id, 'pet_species', $data['species'] );
        update_post_meta( $pet_id, 'pet_breed', $data['breed'] );
        update_post_meta( $pet_id, 'pet_size', $data['size'] );
        update_post_meta( $pet_id, 'pet_weight', $data['weight'] );
        update_post_meta( $pet_id, 'pet_coat', $data['coat'] );
        update_post_meta( $pet_id, 'pet_color', $data['color'] );
        update_post_meta( $pet_id, 'pet_birth', $data['birth'] );
        update_post_meta( $pet_id, 'pet_sex', $data['sex'] );
        update_post_meta( $pet_id, 'pet_care', $data['care'] );
        update_post_meta( $pet_id, 'pet_aggressive', $data['aggressive'] );
        update_post_meta( $pet_id, 'pet_vaccinations', $data['vaccinations'] );
        update_post_meta( $pet_id, 'pet_allergies', $data['allergies'] );
        update_post_meta( $pet_id, 'pet_behavior', $data['behavior'] );
        update_post_meta( $pet_id, 'pet_shampoo_pref', $data['shampoo_pref'] );
        update_post_meta( $pet_id, 'pet_perfume_pref', $data['perfume_pref'] );
        update_post_meta( $pet_id, 'pet_accessories_pref', $data['accessories_pref'] );
        update_post_meta( $pet_id, 'pet_product_restrictions', $data['product_restrictions'] );

        // Auditoria (Fase 6.2)
        $action = ! empty( $data['pet_id'] ) ? 'update' : 'create';
        DPS_Audit_Logger::log_pet_change( $pet_id, $action, [
            'name'     => $data['name'],
            'species'  => $data['species'],
            'owner_id' => $data['owner_id'],
        ] );

        return $pet_id;
    }

    /**
     * Processa upload de foto do pet.
     *
     * @param int $pet_id ID do pet.
     * @return void
     */
    public static function handle_photo_upload( $pet_id ) {
        if ( ! isset( $_FILES['pet_photo'] ) || empty( $_FILES['pet_photo']['name'] ) ) {
            return;
        }

        $file = $_FILES['pet_photo'];

        // Carrega funções de upload do WordPress
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
            require_once ABSPATH . 'wp-includes/functions.php';
        }

        // Validação primária usando wp_check_filetype_and_ext (mais segura que extensão)
        $wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIMES );

        // Verifica se o tipo MIME retornado está na lista de permitidos
        if ( empty( $wp_filetype['type'] ) || ! in_array( $wp_filetype['type'], self::ALLOWED_MIMES, true ) ) {
            DPS_Message_Helper::add_error( __( 'Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF, WebP).', 'desi-pet-shower' ) );
            return;
        }

        // Restringe MIME types permitidos no upload
        $overrides = [
            'test_form' => false,
            'mimes'     => self::ALLOWED_MIMES,
        ];
        $uploaded = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded['file'] ) && isset( $uploaded['type'] ) && empty( $uploaded['error'] ) ) {
            // Validação final: verifica se o MIME type do arquivo salvo está na lista permitida
            if ( in_array( $uploaded['type'], self::ALLOWED_MIMES, true ) ) {
                $attachment = [
                    'post_mime_type' => $uploaded['type'],
                    'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );
                if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                $attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
            } else {
                // Remove arquivo se não for imagem válida
                if ( file_exists( $uploaded['file'] ) ) {
                    wp_delete_file( $uploaded['file'] );
                }
                DPS_Message_Helper::add_error( __( 'O arquivo enviado não é uma imagem válida.', 'desi-pet-shower' ) );
            }
        } elseif ( ! empty( $uploaded['error'] ) ) {
            DPS_Message_Helper::add_error( $uploaded['error'] );
        }
    }

    /**
     * Exclui um pet.
     *
     * @param int $pet_id ID do pet.
     * @return bool True se excluído com sucesso.
     */
    public static function delete( $pet_id ) {
        if ( ! current_user_can( 'dps_manage_pets' ) ) {
            return false;
        }

        $pet_name = get_the_title( $pet_id );
        $result   = (bool) wp_delete_post( $pet_id, true );

        if ( $result ) {
            DPS_Audit_Logger::log_pet_change( $pet_id, 'delete', [
                'name' => $pet_name,
            ] );
        }

        return $result;
    }

    /**
     * Retorna o label traduzido para espécie do pet.
     *
     * @param string $species Chave da espécie.
     * @return string Label traduzido.
     */
    public static function get_species_label( $species ) {
        $labels = [
            'cachorro' => __( 'Cachorro', 'desi-pet-shower' ),
            'gato'     => __( 'Gato', 'desi-pet-shower' ),
            'outro'    => __( 'Outro', 'desi-pet-shower' ),
        ];
        return isset( $labels[ $species ] ) ? $labels[ $species ] : $species;
    }

    /**
     * Retorna o label traduzido para tamanho do pet.
     *
     * @param string $size Chave do tamanho.
     * @return string Label traduzido.
     */
    public static function get_size_label( $size ) {
        $labels = [
            'pequeno' => __( 'Pequeno (até 10kg)', 'desi-pet-shower' ),
            'medio'   => __( 'Médio (10–25kg)', 'desi-pet-shower' ),
            'grande'  => __( 'Grande (25–45kg)', 'desi-pet-shower' ),
            'gigante' => __( 'Gigante (45kg+)', 'desi-pet-shower' ),
        ];
        return isset( $labels[ $size ] ) ? $labels[ $size ] : $size;
    }

    /**
     * Retorna o label traduzido para sexo do pet.
     *
     * @param string $sex Chave do sexo.
     * @return string Label traduzido.
     */
    public static function get_sex_label( $sex ) {
        $labels = [
            'macho' => __( 'Macho', 'desi-pet-shower' ),
            'femea' => __( 'Fêmea', 'desi-pet-shower' ),
        ];
        return isset( $labels[ $sex ] ) ? $labels[ $sex ] : $sex;
    }
}
