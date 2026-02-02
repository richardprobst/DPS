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
     * A ordem de busca é:
     * 1. Tema filho: wp-content/themes/CHILD_THEME/dps-templates/{template_name}
     * 2. Tema pai: wp-content/themes/PARENT_THEME/dps-templates/{template_name}
     * 3. Plugin base: wp-content/plugins/desi-pet-shower-base/templates/{template_name}
     *
     * Para forçar o uso do template do plugin, use o filtro 'dps_use_plugin_template':
     * add_filter( 'dps_use_plugin_template', function( $use_plugin, $template_name ) {
     *     if ( $template_name === 'tosa-consent-form.php' ) {
     *         return true;
     *     }
     *     return $use_plugin;
     * }, 10, 2 );
     *
     * @param string $template_name Nome do arquivo de template.
     * @param array  $args          Variáveis a serem extraídas para o template.
     */
    function dps_get_template( $template_name, $args = array() ) {
        if ( empty( $template_name ) ) {
            return;
        }

        $template_name = ltrim( $template_name, '/' );
        $plugin_path   = trailingslashit( DPS_BASE_DIR . 'templates' ) . $template_name;
        
        /**
         * Permite forçar o uso do template do plugin, ignorando override do tema.
         *
         * Útil quando o template do tema está desatualizado ou quando se deseja
         * garantir que a versão mais recente do template seja usada.
         *
         * @param bool   $use_plugin    Se deve usar o template do plugin. Default false.
         * @param string $template_name Nome do arquivo de template.
         */
        $force_plugin_template = apply_filters( 'dps_use_plugin_template', false, $template_name );
        
        if ( $force_plugin_template && file_exists( $plugin_path ) ) {
            $path_to_load      = $plugin_path;
            $is_theme_override = false;
        } else {
            $theme_path        = locate_template( 'dps-templates/' . $template_name );
            $path_to_load      = $theme_path ? $theme_path : $plugin_path;
            $is_theme_override = ! empty( $theme_path );
        }

        if ( ! file_exists( $path_to_load ) ) {
            return;
        }

        /**
         * Ação disparada quando um template é carregado.
         *
         * Útil para debug e logging de qual template está sendo usado.
         *
         * @param string $path_to_load     Caminho completo do template carregado.
         * @param string $template_name    Nome do arquivo de template.
         * @param bool   $is_theme_override Se o template foi sobrescrito pelo tema.
         */
        do_action( 'dps_template_loaded', $path_to_load, $template_name, $is_theme_override );

        if ( ! empty( $args ) && is_array( $args ) ) {
            extract( $args, EXTR_SKIP );
        }

        include $path_to_load;
    }
}

if ( ! function_exists( 'dps_get_template_path' ) ) {
    /**
     * Retorna o caminho do template que seria carregado, sem incluí-lo.
     *
     * Útil para debug e verificação de qual template está sendo usado.
     *
     * @param string $template_name Nome do arquivo de template.
     * @return string|false Caminho do template ou false se não encontrado.
     */
    function dps_get_template_path( $template_name ) {
        if ( empty( $template_name ) ) {
            return false;
        }

        $template_name = ltrim( $template_name, '/' );
        $plugin_path   = trailingslashit( DPS_BASE_DIR . 'templates' ) . $template_name;
        
        $force_plugin_template = apply_filters( 'dps_use_plugin_template', false, $template_name );
        
        if ( $force_plugin_template && file_exists( $plugin_path ) ) {
            return $plugin_path;
        }
        
        $theme_path   = locate_template( 'dps-templates/' . $template_name );
        $path_to_load = $theme_path ? $theme_path : $plugin_path;

        if ( ! file_exists( $path_to_load ) ) {
            return false;
        }

        return $path_to_load;
    }
}

if ( ! function_exists( 'dps_is_template_overridden' ) ) {
    /**
     * Verifica se um template está sendo sobrescrito pelo tema.
     *
     * @param string $template_name Nome do arquivo de template.
     * @return bool True se sobrescrito, false caso contrário.
     */
    function dps_is_template_overridden( $template_name ) {
        if ( empty( $template_name ) ) {
            return false;
        }

        $template_name = ltrim( $template_name, '/' );
        $theme_path    = locate_template( 'dps-templates/' . $template_name );

        return ! empty( $theme_path );
    }
}
