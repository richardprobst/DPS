<?php
/**
 * Template Engine do Frontend Add-on (Fase 7).
 *
 * Sistema simples de renderização inspirado na hierarquia de templates do WordPress.
 * Suporta override via tema em dps-templates/ e output buffering seguro.
 *
 * @package DPS_Frontend_Addon
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DPS_Template_Engine {

    private string $templatePath;

    public function __construct( string $basePath ) {
        $this->templatePath = trailingslashit( $basePath ) . 'templates/';
    }

    /**
     * Renderiza template com dados.
     *
     * @param string               $template Caminho relativo do template (ex.: 'components/alert.php').
     * @param array<string, mixed> $data     Dados disponíveis no escopo do template.
     * @return string HTML renderizado.
     */
    public function render( string $template, array $data = [] ): string {
        $file = $this->locateTemplate( $template );

        if ( ! $file ) {
            return '';
        }

        // Extrai dados para escopo local (EXTR_SKIP protege variáveis existentes)
        extract( $data, EXTR_SKIP );

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
     *   1. Tema ativo: get_stylesheet_directory()/dps-templates/{template}
     *   2. Plugin: {plugin_path}/templates/{template}
     *
     * @param string $template Caminho relativo do template.
     * @return string|false Caminho absoluto ou false se não encontrado.
     */
    private function locateTemplate( string $template ): string|false {
        // 1. Busca no tema (override)
        $themeTemplate = get_stylesheet_directory() . '/dps-templates/' . $template;
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
