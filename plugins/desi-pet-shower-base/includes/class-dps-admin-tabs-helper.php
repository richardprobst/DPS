<?php
/**
 * Helper para criação de interfaces com abas no admin do WordPress.
 *
 * Fornece métodos reutilizáveis para renderizar navegação por abas
 * e gerenciar conteúdo de abas em páginas administrativas.
 *
 * @package DPS_Base_Plugin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe Helper para Abas Administrativas.
 */
class DPS_Admin_Tabs_Helper {

    /**
     * Renderiza a navegação de abas padrão WordPress.
     *
     * @param array  $tabs Array de abas com estrutura: [ 'slug' => 'Label', ... ]
     * @param string $active_tab Slug da aba ativa
     * @param string $page_slug Slug da página administrativa
     * @return void
     */
    public static function render_nav_tabs( $tabs, $active_tab, $page_slug ) {
        if ( empty( $tabs ) || ! is_array( $tabs ) ) {
            return;
        }

        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $slug => $label ) {
            $url = add_query_arg( 'tab', $slug, admin_url( 'admin.php?page=' . $page_slug ) );
            $active_class = $active_tab === $slug ? 'nav-tab-active' : '';
            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_url( $url ),
                esc_attr( $active_class ),
                esc_html( $label )
            );
        }
        echo '</nav>';
    }

    /**
     * Obtém a aba ativa com base no parâmetro GET.
     *
     * @param string $default_tab Slug da aba padrão (primeira)
     * @return string Slug da aba ativa
     */
    public static function get_active_tab( $default_tab = '' ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Parâmetro de navegação apenas para leitura
        return isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab;
    }

    /**
     * Renderiza conteúdo de aba com switch/case.
     * 
     * Executa callback da aba correspondente ao slug ativo.
     *
     * @param string $active_tab Slug da aba ativa
     * @param array  $callbacks Array com estrutura: [ 'slug' => callable, ... ]
     * @return void
     */
    public static function render_tab_content( $active_tab, $callbacks ) {
        if ( ! isset( $callbacks[ $active_tab ] ) ) {
            // Aba não existe, usar primeira aba como fallback
            $active_tab = array_key_first( $callbacks );
        }

        if ( isset( $callbacks[ $active_tab ] ) && is_callable( $callbacks[ $active_tab ] ) ) {
            call_user_func( $callbacks[ $active_tab ] );
        }
    }

    /**
     * Renderiza wrapper completo de página com abas.
     * 
     * Combina render_nav_tabs + render_tab_content em uma única chamada.
     *
     * @param string $page_title Título da página
     * @param array  $tabs Array de abas: [ 'slug' => 'Label', ... ]
     * @param array  $callbacks Array de callbacks: [ 'slug' => callable, ... ]
     * @param string $page_slug Slug da página administrativa
     * @param string $default_tab Slug da aba padrão
     * @return void
     */
    public static function render_tabbed_page( $page_title, $tabs, $callbacks, $page_slug, $default_tab = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Você não tem permissão para acessar esta página.', 'dps-base' ) );
        }

        if ( empty( $default_tab ) ) {
            $default_tab = array_key_first( $tabs );
        }

        $active_tab = self::get_active_tab( $default_tab );

        echo '<div class="wrap dps-admin-tabbed-page">';
        echo '<h1>' . esc_html( $page_title ) . '</h1>';
        
        self::render_nav_tabs( $tabs, $active_tab, $page_slug );
        
        echo '<div class="dps-tab-content" style="margin-top: 20px;">';
        self::render_tab_content( $active_tab, $callbacks );
        echo '</div>';
        
        echo '</div>';
    }
}
