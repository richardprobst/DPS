<?php
/**
 * Funções auxiliares para carregamento de templates sobrescrevíveis.
 *
 * @package DesiPetShower
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dps_get_template' ) ) {
    /**
     * Localiza e carrega um template, permitindo override pelo tema.
     *
     * @param string $template_name Nome do arquivo de template.
     * @param array  $args          Variáveis a serem extraídas para o template.
     */
    function dps_get_template( $template_name, $args = array() ) {
        if ( empty( $template_name ) ) {
            return;
        }

        $template_name = ltrim( $template_name, '/' );
        $theme_path    = locate_template( 'dps-templates/' . $template_name );
        $plugin_path   = trailingslashit( DPS_BASE_DIR . 'templates' ) . $template_name;
        $path_to_load  = $theme_path ? $theme_path : $plugin_path;

        if ( ! file_exists( $path_to_load ) ) {
            return;
        }

        if ( ! empty( $args ) && is_array( $args ) ) {
            extract( $args, EXTR_SKIP );
        }

        include $path_to_load;
    }
}
