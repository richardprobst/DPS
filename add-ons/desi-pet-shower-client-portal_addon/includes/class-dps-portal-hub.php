<?php
/**
 * Página Hub centralizada do Portal do Cliente.
 *
 * Consolida todos os menus do Portal em uma única página com abas:
 * - Configurações
 * - Logins
 * - Mensagens (integra CPT dps_portal_message)
 *
 * @package DPS_Client_Portal_Addon
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub do Portal do Cliente.
 */
class DPS_Portal_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Portal_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Portal_Hub
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 ); // Antes dos submenus antigos
    }

    /**
     * Registra o menu hub centralizado.
     */
    public function register_hub_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Portal do Cliente', 'dps-client-portal' ),
            __( 'Portal do Cliente', 'dps-client-portal' ),
            'manage_options',
            'dps-portal-hub',
            [ $this, 'render_hub_page' ]
        );
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [
            'settings' => __( 'Configurações', 'dps-client-portal' ),
            'logins'   => __( 'Logins', 'dps-client-portal' ),
            'messages' => __( 'Mensagens', 'dps-client-portal' ),
        ];

        $callbacks = [
            'settings' => [ $this, 'render_settings_tab' ],
            'logins'   => [ $this, 'render_logins_tab' ],
            'messages' => [ $this, 'render_messages_tab' ],
        ];

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Portal do Cliente', 'dps-client-portal' ),
            $tabs,
            $callbacks,
            'dps-portal-hub',
            'settings'
        );
    }

    /**
     * Renderiza a aba de Configurações.
     */
    public function render_settings_tab() {
        if ( class_exists( 'DPS_Portal_Admin' ) ) {
            $admin = DPS_Portal_Admin::get_instance();
            ob_start();
            $admin->render_portal_settings_admin_page();
            $content = ob_get_clean();
            
            // Remove o wrapper e H1 duplicado
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Logins.
     */
    public function render_logins_tab() {
        if ( class_exists( 'DPS_Portal_Admin' ) ) {
            $admin = DPS_Portal_Admin::get_instance();
            ob_start();
            $admin->render_client_logins_admin_page();
            $content = ob_get_clean();
            
            // Remove o wrapper e H1 duplicado
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Mensagens (integração com CPT dps_portal_message).
     */
    public function render_messages_tab() {
        ?>
        <div class="dps-portal-messages-tab">
            <p>
                <?php 
                printf(
                    /* translators: %s: URL para adicionar nova mensagem */
                    esc_html__( 'Gerencie as mensagens do portal do cliente. %s', 'dps-client-portal' ),
                    '<a href="' . esc_url( admin_url( 'post-new.php?post_type=dps_portal_message' ) ) . '">' . 
                    esc_html__( 'Adicionar nova mensagem', 'dps-client-portal' ) . 
                    '</a>'
                );
                ?>
            </p>
            
            <?php
            // Exibe listagem de mensagens usando WP_List_Table
            $list_url = admin_url( 'edit.php?post_type=dps_portal_message' );
            ?>
            
            <iframe 
                src="<?php echo esc_url( $list_url ); ?>" 
                style="width: 100%; min-height: 600px; border: none; display: block; margin-top: 20px;"
                title="<?php esc_attr_e( 'Listagem de Mensagens do Portal', 'dps-client-portal' ); ?>"
            ></iframe>
            
            <p style="margin-top: 10px;">
                <a href="<?php echo esc_url( $list_url ); ?>" class="button" target="_blank">
                    <?php esc_html_e( 'Abrir em nova aba', 'dps-client-portal' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
