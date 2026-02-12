<?php
/**
 * Hook Bridge — Registration (Fase 7).
 *
 * Garante compatibilidade retroativa durante coexistência v1/v2.
 * Quando o V2 processa ações de registro, dispara AMBOS os hooks:
 * legado primeiro (para Loyalty e outros add-ons existentes) e v2 depois.
 *
 * Regras:
 *   1. Hook legado PRIMEIRO, hook v2 DEPOIS
 *   2. Assinatura idêntica ao legado
 *   3. SEMPRE dispara ambos (sem condicionais)
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Registration_Hook_Bridge {

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Dispara hooks após criação de cliente no v2.
     * Mantém compatibilidade com Loyalty e outros add-ons.
     *
     * @param int    $clientId     ID do cliente criado.
     * @param string $email        Email do cliente.
     * @param string $phone        Telefone do cliente.
     * @param string $referralCode Código de indicação (opcional).
     */
    public function afterClientCreated(
        int $clientId,
        string $email,
        string $phone,
        string $referralCode = ''
    ): void {
        // 1. Hook LEGADO primeiro (Loyalty e outros add-ons existentes)
        // Assinatura IDÊNTICA ao legado: ($referral_code, $client_id, $email, $phone)
        do_action(
            'dps_registration_after_client_created',
            $referralCode,
            $clientId,
            $email,
            $phone
        );

        // 2. Hook NOVO v2 (para novos consumidores futuros)
        do_action( 'dps_registration_v2_client_created', $clientId, [
            'email'         => $email,
            'phone'         => $phone,
            'referral_code' => $referralCode,
        ] );

        $this->logger->info( "Hook bridge: afterClientCreated para client #{$clientId}." );
    }

    /**
     * Dispara hooks após criação de pet no v2.
     *
     * @param int                  $petId    ID do pet criado.
     * @param int                  $clientId ID do cliente dono.
     * @param array<string, mixed> $data     Dados do pet.
     */
    public function afterPetCreated( int $petId, int $clientId, array $data ): void {
        do_action( 'dps_registration_v2_pet_created', $petId, $clientId, $data );

        $this->logger->info( "Hook bridge: afterPetCreated para pet #{$petId} (client #{$clientId})." );
    }

    /**
     * Dispara hook de campos adicionais no formulário.
     * Permite que Loyalty injete campo de referral code.
     */
    public function afterFormFields(): void {
        do_action( 'dps_registration_after_fields' );
    }

    /**
     * Aplica filtro anti-spam.
     * Permite validações externas adicionais.
     *
     * @param bool                 $valid   Estado de validação atual.
     * @param array<string, mixed> $context Contexto da submissão.
     * @return bool Resultado após filtro.
     */
    public function applySpamCheck( bool $valid, array $context ): bool {
        return (bool) apply_filters( 'dps_registration_spam_check', $valid, $context );
    }

    /**
     * Dispara hook antes da renderização do formulário v2.
     *
     * @param array<string, string> $atts Atributos do shortcode.
     */
    public function beforeRender( array $atts ): void {
        do_action( 'dps_registration_v2_before_render', $atts );
    }

    /**
     * Dispara hook após renderização do formulário v2.
     *
     * @param string $html HTML renderizado.
     */
    public function afterRender( string $html ): void {
        do_action( 'dps_registration_v2_after_render', $html );
    }

    /**
     * Dispara hook antes de processar submissão v2.
     *
     * @param array<string, mixed> $data Dados do formulário.
     */
    public function beforeProcess( array $data ): void {
        do_action( 'dps_registration_v2_before_process', $data );
    }

    /**
     * Dispara hook após processar submissão v2.
     *
     * @param array{success: bool, errors: string[], data: array<string, mixed>} $result Resultado.
     * @param array<string, mixed> $data Dados originais.
     */
    public function afterProcess( array $result, array $data ): void {
        do_action( 'dps_registration_v2_after_process', $result, $data );
    }
}
