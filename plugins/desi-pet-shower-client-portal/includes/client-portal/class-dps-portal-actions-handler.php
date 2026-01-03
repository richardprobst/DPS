<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Processador de ações de formulários do portal do cliente.
 * 
 * Esta classe é responsável por processar todas as ações enviadas via POST
 * pelos formulários do portal (atualização de dados, upload de fotos, etc.).
 * 
 * @since 3.0.0
 */
class DPS_Portal_Actions_Handler {

    /**
     * Instância única da classe (singleton).
     *
     * @var DPS_Portal_Actions_Handler|null
     */
    private static $instance = null;

    /**
     * Repositório de finanças.
     *
     * @var DPS_Finance_Repository
     */
    private $finance_repository;

    /**
     * Recupera a instância única (singleton).
     *
     * @return DPS_Portal_Actions_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado (singleton).
     */
    private function __construct() {
        $this->finance_repository = DPS_Finance_Repository::get_instance();
    }

    /**
     * Processa atualização de informações do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string URL de redirecionamento.
     */
    public function handle_update_client_info( $client_id ) {
        $phone = isset( $_POST['client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['client_phone'] ) ) : '';
        update_post_meta( $client_id, 'client_phone', $phone );

        $address = isset( $_POST['client_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['client_address'] ) ) : '';
        update_post_meta( $client_id, 'client_address', $address );

        $insta = isset( $_POST['client_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['client_instagram'] ) ) : '';
        update_post_meta( $client_id, 'client_instagram', $insta );

        $fb = isset( $_POST['client_facebook'] ) ? sanitize_text_field( wp_unslash( $_POST['client_facebook'] ) ) : '';
        update_post_meta( $client_id, 'client_facebook', $fb );

        $email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        if ( $email && is_email( $email ) ) {
            update_post_meta( $client_id, 'client_email', $email );
        }

        // Hook: Após atualizar dados do cliente (Fase 2.3)
        do_action( 'dps_portal_after_update_client', $client_id, $_POST );
        
        return add_query_arg( 'portal_msg', 'updated', wp_get_referer() ?: home_url() );
    }

    /**
     * Processa atualização de dados de pet.
     *
     * @param int $client_id ID do cliente.
     * @param int $pet_id    ID do pet.
     * @return string URL de redirecionamento.
     */
    public function handle_update_pet( $client_id, $pet_id ) {
        // Validação de ownership usando helper centralizado (Fase 1.4)
        if ( ! dps_portal_assert_client_owns_resource( $client_id, $pet_id, 'pet' ) ) {
            // Log de tentativa de acesso indevido já feito pelo helper
            return add_query_arg( 'portal_msg', 'error', wp_get_referer() ?: home_url() );
        }

        $pet_name  = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
        $species   = isset( $_POST['pet_species'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_species'] ) ) : '';
        $breed     = isset( $_POST['pet_breed'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_breed'] ) ) : '';
        $size      = isset( $_POST['pet_size'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_size'] ) ) : '';
        $weight    = isset( $_POST['pet_weight'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_weight'] ) ) : '';
        $coat      = isset( $_POST['pet_coat'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_coat'] ) ) : '';
        $color     = isset( $_POST['pet_color'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_color'] ) ) : '';
        $birth     = isset( $_POST['pet_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_birth'] ) ) : '';
        $sex       = isset( $_POST['pet_sex'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_sex'] ) ) : '';
        $vacc      = isset( $_POST['pet_vaccinations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_vaccinations'] ) ) : '';
        $allergies = isset( $_POST['pet_allergies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_allergies'] ) ) : '';
        $behavior  = isset( $_POST['pet_behavior'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pet_behavior'] ) ) : '';

        if ( $pet_name ) {
            wp_update_post( [ 'ID' => $pet_id, 'post_title' => $pet_name ] );
        }

        update_post_meta( $pet_id, 'pet_species', $species );
        update_post_meta( $pet_id, 'pet_breed', $breed );
        update_post_meta( $pet_id, 'pet_size', $size );
        update_post_meta( $pet_id, 'pet_weight', $weight );
        update_post_meta( $pet_id, 'pet_coat', $coat );
        update_post_meta( $pet_id, 'pet_color', $color );
        update_post_meta( $pet_id, 'pet_birth', $birth );
        update_post_meta( $pet_id, 'pet_sex', $sex );
        update_post_meta( $pet_id, 'pet_vaccinations', $vacc );
        update_post_meta( $pet_id, 'pet_allergies', $allergies );
        update_post_meta( $pet_id, 'pet_behavior', $behavior );

        // Processa upload de foto se fornecido
        $redirect_url = wp_get_referer() ?: home_url();
        if ( ! empty( $_FILES['pet_photo']['name'] ) ) {
            $redirect_url = $this->handle_pet_photo_upload( $pet_id, $redirect_url );
        }

        return add_query_arg( 'portal_msg', 'pet_updated', $redirect_url );
    }

    /**
     * Processa upload de foto de pet.
     *
     * @param int    $pet_id      ID do pet.
     * @param string $redirect_url URL base de redirecionamento.
     * @return string URL de redirecionamento atualizada.
     */
    private function handle_pet_photo_upload( $pet_id, $redirect_url ) {
        $file = $_FILES['pet_photo'];
        
        // Valida que o upload foi bem-sucedido
        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
            return add_query_arg( 'portal_msg', 'upload_error', $redirect_url );
        }
        
        // Valida tipos MIME permitidos para imagens
        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif'          => 'image/gif',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ];
        
        // Extrai extensões permitidas dos MIME types (single source of truth)
        $allowed_exts = [];
        foreach ( array_keys( $allowed_mimes ) as $ext_pattern ) {
            $exts = explode( '|', $ext_pattern );
            $allowed_exts = array_merge( $allowed_exts, $exts );
        }
        
        // Verifica extensão do arquivo
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        
        if ( ! in_array( $file_ext, $allowed_exts, true ) ) {
            // Extensão não permitida, não processa upload
            return add_query_arg( 'portal_msg', 'invalid_file_type', $redirect_url );
        }
        
        // Usa limite de upload do WordPress (respeita configuração do servidor)
        $max_size = min( wp_max_upload_size(), 5 * MB_IN_BYTES );
        if ( $file['size'] > $max_size ) {
            return add_query_arg( 'portal_msg', 'file_too_large', $redirect_url );
        }
        
        // Validação adicional de MIME type real usando getimagesize()
        // Isso previne uploads de arquivos maliciosos com extensão de imagem
        // Nota: Validação de is_uploaded_file() já foi feita acima
        $image_info = getimagesize( $file['tmp_name'] );
        if ( false === $image_info || ! isset( $image_info['mime'] ) ) {
            return add_query_arg( 'portal_msg', 'invalid_file_type', $redirect_url );
        }
        
        // Verifica se o MIME type real está na lista permitida
        if ( ! in_array( $image_info['mime'], $allowed_mimes, true ) ) {
            return add_query_arg( 'portal_msg', 'invalid_file_type', $redirect_url );
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload( $file, [ 
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ] );
        
        if ( ! isset( $upload['error'] ) && isset( $upload['file'] ) ) {
            $file_path  = $upload['file'];
            $file_name  = basename( $file_path );
            $file_type  = wp_check_filetype( $file_name, $allowed_mimes );
            
            // Valida MIME type real do arquivo
            if ( ! empty( $file_type['type'] ) && 0 === strpos( $file_type['type'], 'image/' ) ) {
                $attachment = [
                    'post_title'     => sanitize_file_name( $file_name ),
                    'post_mime_type' => $file_type['type'],
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment( $attachment, $file_path );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    update_post_meta( $pet_id, 'pet_photo_id', $attach_id );
                }
            }
        }
        
        return $redirect_url;
    }

    /**
     * Processa envio de mensagem do cliente.
     *
     * @param int $client_id ID do cliente.
     * @return string URL de redirecionamento.
     */
    public function handle_send_message( $client_id ) {
        $subject = isset( $_POST['message_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['message_subject'] ) ) : '';
        $content = isset( $_POST['message_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message_body'] ) ) : '';

        $redirect_url = wp_get_referer() ?: home_url();
        
        if ( ! $content ) {
            return add_query_arg( 'portal_msg', 'message_error', $redirect_url );
        }
        
        $client_name = get_the_title( $client_id );
        $title       = $subject ? $subject : sprintf( __( 'Mensagem do cliente %s', 'dps-client-portal' ), $client_name );

        // Cria registro da mensagem no CPT
        $message_id = wp_insert_post( [
            'post_type'    => 'dps_portal_message',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => $content,
        ] );

        if ( is_wp_error( $message_id ) ) {
            return add_query_arg( 'portal_msg', 'message_error', $redirect_url );
        }
        
        update_post_meta( $message_id, 'message_client_id', $client_id );
        update_post_meta( $message_id, 'message_sender', 'client' );
        update_post_meta( $message_id, 'message_status', 'open' );

        // Envia notificação ao admin via Communications API
        $this->send_message_notification( $client_id, $message_id, $subject, $content );

        return add_query_arg( 'portal_msg', 'message_sent', $redirect_url );
    }

    /**
     * Envia notificação de nova mensagem para o admin.
     *
     * @param int    $client_id  ID do cliente.
     * @param int    $message_id ID da mensagem.
     * @param string $subject    Assunto da mensagem.
     * @param string $content    Conteúdo da mensagem.
     */
    private function send_message_notification( $client_id, $message_id, $subject, $content ) {
        if ( class_exists( 'DPS_Communications_API' ) ) {
            $api = DPS_Communications_API::get_instance();
            $full_message = $subject ? $subject . "\n\n" . $content : $content;
            $api->send_message_from_client( $client_id, $full_message, [
                'message_id' => $message_id,
                'subject'    => $subject,
            ] );
        } else {
            // Fallback: envia diretamente via wp_mail (compatibilidade retroativa)
            $this->send_message_fallback( $client_id, $subject, $content );
        }
    }

    /**
     * Fallback para envio de notificação sem Communications API.
     *
     * @param int    $client_id ID do cliente.
     * @param string $subject   Assunto da mensagem.
     * @param string $content   Conteúdo da mensagem.
     */
    private function send_message_fallback( $client_id, $subject, $content ) {
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return;
        }
        
        $client_name  = get_the_title( $client_id );
        $phone        = get_post_meta( $client_id, 'client_phone', true );
        $email        = get_post_meta( $client_id, 'client_email', true );
        $subject_line = sprintf( __( 'Nova mensagem do cliente %s', 'dps-client-portal' ), $client_name );
        $body_lines   = [
            sprintf( __( 'Cliente: %s (ID #%d)', 'dps-client-portal' ), $client_name, $client_id ),
            $phone ? sprintf( __( 'Telefone: %s', 'dps-client-portal' ), $phone ) : '',
            $email ? sprintf( __( 'Email: %s', 'dps-client-portal' ), $email ) : '',
            $subject ? sprintf( __( 'Assunto: %s', 'dps-client-portal' ), $subject ) : '',
            '',
            __( 'Mensagem:', 'dps-client-portal' ),
            $content,
        ];
        $body_lines = array_filter( $body_lines, 'strlen' );
        wp_mail( $admin_email, $subject_line, implode( "\n", $body_lines ) );
    }

    /**
     * Processa geração de link de pagamento.
     *
     * @param int $client_id ID do cliente.
     * @param int $trans_id  ID da transação.
     * @return string URL de redirecionamento.
     */
    public function handle_pay_transaction( $client_id, $trans_id ) {
        if ( ! $this->transaction_belongs_to_client( $trans_id, $client_id ) ) {
            return add_query_arg( 'portal_msg', 'error', wp_get_referer() ?: home_url() );
        }

        $link = $this->generate_payment_link_for_transaction( $trans_id );
        if ( $link ) {
            // Redireciona para o link de pagamento
            return $link;
        }
        
        return add_query_arg( 'portal_msg', 'error', wp_get_referer() ?: home_url() );
    }

    /**
     * Verifica se uma transação pertence ao cliente.
     *
     * @param int $trans_id  ID da transação.
     * @param int $client_id ID do cliente.
     * @return bool
     */
    private function transaction_belongs_to_client( $trans_id, $client_id ) {
        return $this->finance_repository->transaction_belongs_to_client( $trans_id, $client_id );
    }

    /**
     * Gera link de pagamento para uma transação (via Mercado Pago).
     *
     * @param int $trans_id ID da transação.
     * @return string|false URL do link de pagamento ou false em caso de erro.
     */
    private function generate_payment_link_for_transaction( $trans_id ) {
        $trans = $this->finance_repository->get_transaction( $trans_id );
        
        if ( ! $trans ) {
            return false;
        }
        
        $client_id   = absint( $trans->cliente_id );
        $client_name = get_the_title( $client_id );
        $client_mail = get_post_meta( $client_id, 'client_email', true );
        $client_phone = get_post_meta( $client_id, 'client_phone', true );
        $desc        = $trans->descricao ? $trans->descricao : __( 'Serviço desi.pet by PRObst', 'dps-client-portal' );
        $valor       = (float) $trans->valor;
        
        // Usa Finance API se disponível (Fase 2.3)
        if ( class_exists( 'DPS_Finance_API' ) && method_exists( 'DPS_Finance_API', 'create_payment_link' ) ) {
            return DPS_Finance_API::create_payment_link( [
                'transaction_id' => $trans_id,
                'amount'         => $valor,
                'description'    => $desc,
                'client_id'      => $client_id,
            ] );
        }
        
        // Fallback: geração manual (compatibilidade retroativa)
        return $this->generate_payment_link_fallback( $trans_id, $valor, $desc, $client_name, $client_mail, $client_phone );
    }

    /**
     * Fallback para geração de link de pagamento sem Finance API.
     *
     * @param int    $trans_id    ID da transação.
     * @param float  $valor       Valor da transação.
     * @param string $desc        Descrição.
     * @param string $client_name Nome do cliente.
     * @param string $client_mail Email do cliente.
     * @param string $client_phone Telefone do cliente.
     * @return string|false URL do link de pagamento ou false.
     */
    private function generate_payment_link_fallback( $trans_id, $valor, $desc, $client_name, $client_mail, $client_phone ) {
        $mp_token = get_option( 'dps_mercadopago_access_token', '' );
        
        if ( ! $mp_token ) {
            return false;
        }
        
        // Sanitiza dados para a API
        $preference_data = [
            'items' => [
                [
                    'title'        => wp_strip_all_tags( $desc ),
                    'quantity'     => 1,
                    'unit_price'   => (float) $valor,
                    'currency_id'  => 'BRL',
                ],
            ],
            'payer' => [
                'name'  => wp_strip_all_tags( $client_name ),
                'email' => $client_mail && is_email( $client_mail ) ? $client_mail : 'cliente@example.com',
            ],
            'external_reference' => 'DPS_TRANS_' . absint( $trans_id ),
            'notification_url'   => home_url( '/mercadopago/webhook/' ),
        ];
        
        // Adiciona telefone apenas se for válido (após sanitização)
        $clean_phone = preg_replace( '/\D/', '', (string) $client_phone );
        if ( ! empty( $clean_phone ) && strlen( $clean_phone ) >= 8 ) {
            $preference_data['payer']['phone'] = [
                'number' => $clean_phone,
            ];
        }
        
        $response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', [
            'headers' => [
                'Authorization' => 'Bearer ' . $mp_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $preference_data ),
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            // Log erro sem expor token
            if ( function_exists( 'dps_log' ) ) {
                dps_log( 'MercadoPago API error', [
                    'error' => $response->get_error_message(),
                    'trans_id' => $trans_id,
                ], 'error', 'client-portal' );
            }
            return false;
        }
        
        // Verifica status HTTP
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            if ( function_exists( 'dps_log' ) ) {
                dps_log( 'MercadoPago API HTTP error', [
                    'status_code' => $status_code,
                    'trans_id' => $trans_id,
                ], 'error', 'client-portal' );
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['init_point'] ) ) {
            return esc_url_raw( $data['init_point'] );
        }
        
        return false;
    }
}
