<?php
/**
 * Página administrativa para configurações de Clientes.
 *
 * Permite definir a URL da página dedicada de cadastro utilizada
 * nos atalhos da aba Clientes do painel principal.
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por registrar o submenu de Clientes
 * e renderizar o formulário de configuração.
 */
class DPS_Clients_Admin_Page {

    /**
     * Construtor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
    }

    /**
     * Registra o submenu sob o menu principal do DPS.
     */
    public function register_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Clientes', 'desi-pet-shower' ),
            __( 'Clientes', 'desi-pet-shower' ),
            'manage_options',
            'dps-clients-settings',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Renderiza a página administrativa.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        $registration_url = get_option( 'dps_clients_registration_url', '' );

        if ( isset( $_POST['dps_clients_settings_nonce'] ) ) {
            $this->handle_form_submission( $registration_url );
            // Atualiza valor exibido após salvar.
            $registration_url = get_option( 'dps_clients_registration_url', '' );
        }
        ?>
        <div class="wrap dps-admin-page dps-clients-settings-page">
            <h1><?php esc_html_e( 'Clientes', 'desi-pet-shower' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Defina a URL da página dedicada de cadastro para liberar o atalho na aba Clientes do painel DPS.', 'desi-pet-shower' ); ?>
            </p>

            <?php settings_errors( 'dps_clients_settings' ); ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'dps_clients_settings_action', 'dps_clients_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dps_clients_registration_url"><?php esc_html_e( 'URL da página de cadastro', 'desi-pet-shower' ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="dps_clients_registration_url"
                                    name="dps_clients_registration_url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr( $registration_url ); ?>"
                                    placeholder="<?php esc_attr_e( 'https://exemplo.com/cadastro-de-clientes', 'desi-pet-shower' ); ?>"
                                />
                                <p class="description">
                                    <?php esc_html_e( 'Use a página pública de cadastro onde seus clientes preenchem os dados iniciais. Deixe em branco se não houver página dedicada.', 'desi-pet-shower' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Salvar alterações', 'desi-pet-shower' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Processa o formulário de configurações.
     *
     * @param string $current_url Valor atual da opção para comparação.
     * @return void
     */
    private function handle_form_submission( $current_url ) {
        if ( ! isset( $_POST['dps_clients_settings_nonce'] ) || ! check_admin_referer( 'dps_clients_settings_action', 'dps_clients_settings_nonce' ) ) {
            wp_die( esc_html__( 'Falha na validação de segurança. Recarregue a página e tente novamente.', 'desi-pet-shower' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'desi-pet-shower' ) );
        }

        $raw_url = isset( $_POST['dps_clients_registration_url'] ) ? wp_unslash( $_POST['dps_clients_registration_url'] ) : '';
        $raw_url = trim( $raw_url );
        $sanitized_url = esc_url_raw( $raw_url );

        if ( ! empty( $raw_url ) && empty( $sanitized_url ) ) {
            add_settings_error(
                'dps_clients_settings',
                'invalid_url',
                esc_html__( 'Informe uma URL válida para a página de cadastro.', 'desi-pet-shower' ),
                'error'
            );
            return;
        }

        if ( $sanitized_url === $current_url ) {
            add_settings_error(
                'dps_clients_settings',
                'no_changes',
                esc_html__( 'Nenhuma alteração encontrada.', 'desi-pet-shower' ),
                'info'
            );
            return;
        }

        update_option( 'dps_clients_registration_url', $sanitized_url );

        add_settings_error(
            'dps_clients_settings',
            'settings_updated',
            esc_html__( 'Configurações salvas com sucesso.', 'desi-pet-shower' ),
            'updated'
        );
    }
}

new DPS_Clients_Admin_Page();
