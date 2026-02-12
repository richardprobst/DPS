<?php
/**
 * Handler de registro V2 (Fase 7).
 *
 * Processa submissão do formulário de cadastro nativo.
 * Valida, detecta duplicatas, cria cliente/pets, dispara hooks e
 * envia confirmação de email — tudo sem depender do add-on legado.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Registration_Handler extends DPS_Abstract_Handler {

    public function __construct(
        private readonly DPS_Form_Validator             $formValidator,
        private readonly DPS_Client_Service             $clientService,
        private readonly DPS_Pet_Service                $petService,
        private readonly DPS_Duplicate_Detector         $duplicateDetector,
        private readonly DPS_Recaptcha_Service          $recaptchaService,
        private readonly DPS_Email_Confirmation_Service $emailService,
        private readonly DPS_Registration_Hook_Bridge   $hookBridge,
        private readonly DPS_Frontend_Logger            $logger,
    ) {}

    /**
     * Processa submissão do formulário de registro.
     *
     * @param array<string, mixed> $data Dados sanitizados do formulário.
     * @return array{success: bool, errors: string[], data: array<string, mixed>}
     */
    public function process( array $data ): array {
        $this->hookBridge->beforeProcess( $data );

        // 1. reCAPTCHA
        $recaptchaResult = $this->recaptchaService->verify( $data['recaptcha_token'] ?? '' );
        if ( true !== $recaptchaResult ) {
            return $this->error( [ $recaptchaResult ], $data );
        }

        // 2. Anti-spam filter (via hook bridge)
        $spamCheck = $this->hookBridge->applySpamCheck( true, $data );
        if ( ! $spamCheck ) {
            $this->logger->warning( 'Registro bloqueado pelo filtro anti-spam.' );
            return $this->error(
                [ __( 'Cadastro bloqueado por filtro de segurança.', 'dps-frontend-addon' ) ],
                $data
            );
        }

        // 3. Validação do formulário
        $validation = $this->formValidator->validate( $data );
        if ( true !== $validation ) {
            return $this->error( $validation, $data );
        }

        // 4. Normalizar telefone
        $normalizedPhone = $this->clientService->normalizePhone( $data['client_phone'] ?? '' );

        // 5. Detecção de duplicatas
        $isAdmin = current_user_can( 'manage_options' );
        $duplicateCheck = $this->duplicateDetector->check( $normalizedPhone, $isAdmin );

        if ( $duplicateCheck['duplicate'] && ! $duplicateCheck['can_override'] ) {
            return $this->error(
                [ __( 'Já existe um cadastro com este telefone. Se precisar de ajuda, entre em contato conosco.', 'dps-frontend-addon' ) ],
                array_merge( $data, [ 'duplicate' => true, 'duplicate_client_id' => $duplicateCheck['client_id'] ] )
            );
        }

        // 6. Admin confirm override para duplicata
        if ( $duplicateCheck['duplicate'] && $duplicateCheck['can_override'] && empty( $data['dps_confirm_duplicate'] ) ) {
            return $this->error(
                [ __( 'Telefone já cadastrado. Como administrador, confirme para criar um novo registro.', 'dps-frontend-addon' ) ],
                array_merge( $data, [ 'duplicate_warning' => true, 'duplicate_client_id' => $duplicateCheck['client_id'] ] )
            );
        }

        // 7. Criar cliente
        $clientId = $this->clientService->create( $data );
        if ( false === $clientId ) {
            $this->logger->error( 'Falha ao criar cliente.' );
            return $this->error(
                [ __( 'Erro ao criar cadastro. Tente novamente.', 'dps-frontend-addon' ) ],
                $data
            );
        }

        // 8. Disparar hooks de integração (Loyalty via bridge)
        $this->hookBridge->afterClientCreated(
            $clientId,
            $data['client_email'] ?? '',
            $normalizedPhone,
            $data['dps_referral_code'] ?? ''
        );

        // 9. Criar pets
        $petIds = [];
        if ( ! empty( $data['pets'] ) && is_array( $data['pets'] ) ) {
            foreach ( $data['pets'] as $petData ) {
                $petId = $this->petService->create( $clientId, $petData );
                if ( false !== $petId ) {
                    $petIds[] = $petId;
                    $this->hookBridge->afterPetCreated( $petId, $clientId, $petData );
                }
            }
        }

        // 10. Email de confirmação
        if ( $this->emailService->isEnabled() && ! empty( $data['client_email'] ) ) {
            $token = $this->emailService->generateToken( $clientId );
            $this->emailService->sendConfirmationEmail(
                $clientId,
                $data['client_email'],
                $data['client_name'] ?? '',
                $token
            );
        }

        $result = $this->success( [
            'client_id' => $clientId,
            'pet_ids'   => $petIds,
        ] );

        $this->hookBridge->afterProcess( $result, $data );

        $this->logger->info( "Registro V2 concluído: client #{$clientId}, " . count( $petIds ) . ' pet(s).' );

        return $result;
    }
}
