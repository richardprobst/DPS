<?php
/**
 * Service de confirmação de agendamento (Fase 7.3).
 *
 * Gerencia transients de confirmação de booking V2.
 * Armazena ID do agendamento temporariamente para tela de confirmação.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Booking_Confirmation_Service {

    /** TTL do transient de confirmação: 5 minutos. */
    private const TTL = 300;

    public function __construct(
        private readonly DPS_Frontend_Logger $logger,
    ) {}

    /**
     * Armazena confirmação de agendamento para o usuário.
     *
     * @param int $userId        ID do usuário WordPress.
     * @param int $appointmentId ID do agendamento criado.
     */
    public function store( int $userId, int $appointmentId ): void {
        $key = $this->transientKey( $userId );
        set_transient( $key, $appointmentId, self::TTL );

        $this->logger->info( "Confirmação armazenada: user #{$userId}, appointment #{$appointmentId}." );
    }

    /**
     * Recupera o ID do agendamento confirmado.
     *
     * @param int $userId ID do usuário WordPress.
     * @return int|false ID do agendamento ou false se expirado/inexistente.
     */
    public function retrieve( int $userId ): int|false {
        $value = get_transient( $this->transientKey( $userId ) );

        if ( false === $value ) {
            return false;
        }

        return (int) $value;
    }

    /**
     * Remove transient de confirmação do usuário.
     *
     * @param int $userId ID do usuário WordPress.
     */
    public function clear( int $userId ): void {
        delete_transient( $this->transientKey( $userId ) );
    }

    /**
     * Verifica se existe confirmação pendente para o usuário.
     *
     * @param int $userId ID do usuário WordPress.
     * @return bool True se existe confirmação.
     */
    public function isConfirmed( int $userId ): bool {
        return false !== get_transient( $this->transientKey( $userId ) );
    }

    /**
     * Gera chave do transient para o usuário.
     *
     * @param int $userId ID do usuário.
     * @return string Chave do transient.
     */
    private function transientKey( int $userId ): string {
        return 'dps_booking_confirmation_' . $userId;
    }
}
