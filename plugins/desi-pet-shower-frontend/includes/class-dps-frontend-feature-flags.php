<?php
/**
 * Feature flags do Frontend Add-on.
 *
 * Controla rollout por módulo. Flags são persistidos como option
 * do WordPress e podem ser alterados via painel ou programaticamente.
 *
 * Na Fase 1 todos os módulos ficam desabilitados (fundação sem impacto).
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Feature_Flags {

    private const OPTION_KEY = 'dps_frontend_feature_flags';

    private const DEFAULTS = [
        'registration' => false,
        'booking'      => false,
        'settings'     => false,
    ];

    /** @var array<string, bool> */
    private array $flags;

    public function __construct() {
        $stored      = get_option( self::OPTION_KEY, [] );
        $this->flags = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );
    }

    public function isEnabled( string $module ): bool {
        return ! empty( $this->flags[ $module ] );
    }

    public function enable( string $module ): void {
        $this->flags[ $module ] = true;
        $this->persist();
    }

    public function disable( string $module ): void {
        $this->flags[ $module ] = false;
        $this->persist();
    }

    /**
     * Verifica se ao menos um módulo está habilitado.
     */
    public function hasAnyEnabled(): bool {
        return in_array( true, $this->flags, true );
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array {
        return $this->flags;
    }

    private function persist(): void {
        update_option( self::OPTION_KEY, $this->flags, false );
    }
}
