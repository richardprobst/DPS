<?php
/**
 * Template Engine para o plugin base desi.pet by PRObst.
 *
 * Sistema de renderização inspirado na hierarquia de templates do WordPress.
 * Suporta override via tema em dps-templates/ e output buffering seguro.
 *
 * Portado do Frontend Add-on (DPS_Template_Engine) para uso compartilhado.
 * Fase 2.4 do Plano de Implementação.
 *
 * @package DesiPetShower
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Base_Template_Engine {

    /**
     * Caminho base para os templates do plugin.
     *
     * @var string
     */
    private string $templatePath;

    /**
     * Prefixo para override via tema (subpasta em dps-templates/).
     *
     * @var string
     */
    private string $themePrefix;

    /**
     * Instância singleton.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Cria uma nova instância do template engine.
     *
     * @param string $basePath    Caminho base do plugin (diretório raiz).
     * @param string $themePrefix Prefixo de subpasta para override no tema (ex: 'base', 'portal').
     */
    public function __construct( string $basePath, string $themePrefix = '' ) {
        $this->templatePath = trailingslashit( $basePath ) . 'templates/';
        $this->themePrefix  = $themePrefix ? trailingslashit( $themePrefix ) : '';
    }

    /**
     * Retorna instância singleton para o plugin base.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self(
                defined( 'DPS_PLUGIN_DIR' ) ? DPS_PLUGIN_DIR : dirname( __DIR__ ),
                'base'
            );
        }
        return self::$instance;
    }

    /**
     * Renderiza template com dados.
     *
     * Uso:
     *   $engine->render( 'components/client-card.php', [ 'client' => $client ] );
     *
     * @param string               $template Caminho relativo do template (ex: 'components/alert.php').
     * @param array<string, mixed> $data     Dados disponíveis no escopo do template.
     * @return string HTML renderizado.
     */
    public function render( string $template, array $data = [] ): string {
        $file = $this->locateTemplate( $template );

        if ( ! $file ) {
            return '';
        }

        // Extrai dados para escopo local (EXTR_SKIP protege variáveis existentes)
        extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract

        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * Verifica se um template existe.
     *
     * @param string $template Caminho relativo do template.
     * @return bool
     */
    public function exists( string $template ): bool {
        return false !== $this->locateTemplate( $template );
    }

    /**
     * Localiza template com suporte a override via tema.
     *
     * Hierarquia de busca:
     *   1. Tema ativo: get_stylesheet_directory()/dps-templates/{prefix}/{template}
     *   2. Plugin: {plugin_path}/templates/{template}
     *
     * @param string $template Caminho relativo do template.
     * @return string|false Caminho absoluto ou false se não encontrado.
     */
    private function locateTemplate( string $template ): string|false {
        // 1. Busca no tema (override)
        $themeTemplate = get_stylesheet_directory() . '/dps-templates/' . $this->themePrefix . $template;
        if ( file_exists( $themeTemplate ) ) {
            return $themeTemplate;
        }

        // 2. Busca no plugin
        $pluginTemplate = $this->templatePath . $template;
        if ( file_exists( $pluginTemplate ) ) {
            return $pluginTemplate;
        }

        return false;
    }
}
