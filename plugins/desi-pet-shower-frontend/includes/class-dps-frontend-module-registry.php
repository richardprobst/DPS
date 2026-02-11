<?php
/**
 * Registry de módulos do Frontend Add-on.
 *
 * Armazena módulos por slug, consulta feature flags e executa boot
 * apenas dos módulos habilitados. Cada módulo expõe um contrato
 * mínimo: método boot().
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Module_Registry {

    /** @var array<string, object> */
    private array $modules = [];

    public function __construct(
        private readonly DPS_Frontend_Feature_Flags $flags,
        private readonly DPS_Frontend_Logger        $logger,
    ) {}

    /**
     * Adiciona um módulo ao registry.
     */
    public function add( string $slug, object $module ): void {
        $this->modules[ $slug ] = $module;
    }

    /**
     * Inicializa os módulos cuja feature flag está habilitada.
     */
    public function bootEnabled(): void {
        foreach ( $this->modules as $slug => $module ) {
            if ( ! $this->flags->isEnabled( $slug ) ) {
                continue;
            }

            if ( ! method_exists( $module, 'boot' ) ) {
                $this->logger->warning( "Módulo '{$slug}' não implementa boot()." );
                continue;
            }

            $module->boot();
            $this->logger->info( "Módulo '{$slug}' ativado." );
        }
    }

    /**
     * Retorna módulo pelo slug ou null se inexistente.
     */
    public function get( string $slug ): ?object {
        return $this->modules[ $slug ] ?? null;
    }

    /**
     * Verifica se um módulo está registrado.
     */
    public function has( string $slug ): bool {
        return isset( $this->modules[ $slug ] );
    }

    /**
     * Retorna todos os módulos registrados.
     *
     * @return array<string, object>
     */
    public function all(): array {
        return $this->modules;
    }
}
