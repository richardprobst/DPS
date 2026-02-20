<?php
/**
 * Orquestrador do Frontend Add-on.
 *
 * Recebe dependências por construtor e coordena boot de módulos,
 * assets e compatibilidade. Não é singleton — vive no escopo do
 * callback de init.
 *
 * @package DPS_Frontend_Addon
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Frontend_Addon {

    public function __construct(
        private readonly DPS_Frontend_Module_Registry $registry,
        private readonly DPS_Frontend_Assets          $assets,
        private readonly DPS_Frontend_Compatibility   $compatibility,
        private readonly DPS_Frontend_Logger          $logger,
    ) {}

    /**
     * Inicializa o add-on: ativa módulos, registra assets e bridges de compatibilidade.
     */
    public function boot(): void {
        $this->registry->bootEnabled();
        $this->compatibility->registerBridges();

        add_action( 'wp_enqueue_scripts', [ $this->assets, 'enqueue' ] );

        $this->logger->debug( 'Add-on Frontend inicializado.' );
    }
}
