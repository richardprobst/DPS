<?php
/**
 * DPS GitHub Updater
 *
 * Classe responsável por verificar e gerenciar atualizações dos plugins DPS
 * diretamente do repositório GitHub.
 *
 * @package DPS
 * @since 1.2.0
 */

// Impede acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DPS_GitHub_Updater
 *
 * Implementa verificação de atualizações via API do GitHub.
 * Suporta o plugin base e todos os add-ons do sistema DPS.
 */
class DPS_GitHub_Updater {

    /**
     * Repositório GitHub (owner/repo).
     *
     * @var string
     */
    private $github_repo = 'richardprobst/DPS';

    /**
     * URL da API do GitHub.
     *
     * @var string
     */
    private $github_api_url = 'https://api.github.com';

    /**
     * Transient para cache da verificação de updates.
     *
     * @var string
     */
    private $cache_key = 'dps_github_update_data';

    /**
     * Tempo de cache em segundos (12 horas).
     *
     * @var int
     */
    private $cache_expiration = 43200;

    /**
     * Lista de plugins gerenciados pelo updater.
     * Mapeamento: slug do plugin => caminho relativo no repositório GitHub.
     *
     * @var array
     */
    private $plugins = array();

    /**
     * Instância singleton.
     *
     * @var DPS_GitHub_Updater|null
     */
    private static $instance = null;

    /**
     * Retorna a instância singleton.
     *
     * @return DPS_GitHub_Updater
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para singleton.
     */
    private function __construct() {
        $this->register_plugins();
        $this->init_hooks();
    }

    /**
     * Registra os plugins que serão atualizados.
     */
    private function register_plugins() {
        $this->plugins = array(
            // Plugin Base
            'desi-pet-shower-base_plugin/desi-pet-shower-base.php' => array(
                'name'        => 'desi.pet by PRObst – Base',
                'repo_path'   => 'plugin/desi-pet-shower-base_plugin',
                'slug'        => 'desi-pet-shower-base_plugin',
            ),
            // Add-ons
            'desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Agenda Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-agenda_addon',
                'slug'        => 'desi-pet-shower-agenda_addon',
            ),
            'desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php' => array(
                'name'        => 'desi.pet by PRObst – AI Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-ai_addon',
                'slug'        => 'desi-pet-shower-ai_addon',
            ),
            'desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Backup Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-backup_addon',
                'slug'        => 'desi-pet-shower-backup_addon',
            ),
            'desi-pet-shower-client-portal_addon/desi-pet-shower-client-portal.php' => array(
                'name'        => 'desi.pet by PRObst – Client Portal Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-client-portal_addon',
                'slug'        => 'desi-pet-shower-client-portal_addon',
            ),
            'desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Communications Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-communications_addon',
                'slug'        => 'desi-pet-shower-communications_addon',
            ),
            'desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Financeiro Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-finance_addon',
                'slug'        => 'desi-pet-shower-finance_addon',
            ),
            'desi-pet-shower-groomers_addon/desi-pet-shower-groomers-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Groomers Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-groomers_addon',
                'slug'        => 'desi-pet-shower-groomers_addon',
            ),
            'desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php' => array(
                'name'        => 'desi.pet by PRObst – Loyalty Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-loyalty_addon',
                'slug'        => 'desi-pet-shower-loyalty_addon',
            ),
            'desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Payment Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-payment_addon',
                'slug'        => 'desi-pet-shower-payment_addon',
            ),
            'desi-pet-shower-push_addon/desi-pet-shower-push-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Push Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-push_addon',
                'slug'        => 'desi-pet-shower-push_addon',
            ),
            'desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Registration Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-registration_addon',
                'slug'        => 'desi-pet-shower-registration_addon',
            ),
            'desi-pet-shower-services_addon/desi-pet-shower-services.php' => array(
                'name'        => 'desi.pet by PRObst – Services Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-services_addon',
                'slug'        => 'desi-pet-shower-services_addon',
            ),
            'desi-pet-shower-stats_addon/desi-pet-shower-stats-addon.php' => array(
                'name'        => 'desi.pet by PRObst – Stats Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-stats_addon',
                'slug'        => 'desi-pet-shower-stats_addon',
            ),
            'desi-pet-shower-stock_addon/desi-pet-shower-stock.php' => array(
                'name'        => 'desi.pet by PRObst – Stock Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-stock_addon',
                'slug'        => 'desi-pet-shower-stock_addon',
            ),
            'desi-pet-shower-subscription_addon/desi-pet-shower-subscription.php' => array(
                'name'        => 'desi.pet by PRObst – Subscription Add-on',
                'repo_path'   => 'add-ons/desi-pet-shower-subscription_addon',
                'slug'        => 'desi-pet-shower-subscription_addon',
            ),
        );
    }

    /**
     * Inicializa os hooks do WordPress.
     */
    private function init_hooks() {
        // Hook para verificar atualizações
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

        // Hook para informações do plugin (popup de detalhes)
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

        // Hook após instalar plugin (limpar cache)
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // Hook para limpar cache quando verificar updates manualmente
        add_action( 'admin_init', array( $this, 'maybe_force_check' ) );

        // Hook para mensagem no admin
        add_action( 'admin_notices', array( $this, 'update_notice' ) );
    }

    /**
     * Verifica se há atualizações disponíveis.
     *
     * @param object $transient Transient de atualizações.
     * @return object
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Obtém dados do GitHub (com cache)
        $release_data = $this->get_release_data();

        if ( empty( $release_data ) ) {
            return $transient;
        }

        // Verifica cada plugin registrado
        foreach ( $this->plugins as $plugin_file => $plugin_info ) {
            if ( ! isset( $transient->checked[ $plugin_file ] ) ) {
                continue;
            }

            $current_version = $transient->checked[ $plugin_file ];
            $latest_version  = $this->get_latest_version( $release_data );

            if ( version_compare( $current_version, $latest_version, '<' ) ) {
                $transient->response[ $plugin_file ] = (object) array(
                    'id'          => $plugin_file,
                    'slug'        => $plugin_info['slug'],
                    'plugin'      => $plugin_file,
                    'new_version' => $latest_version,
                    'url'         => 'https://github.com/' . $this->github_repo,
                    'package'     => $this->get_download_url( $release_data, $plugin_info['repo_path'] ),
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => get_bloginfo( 'version' ),
                    'requires'    => '6.9',
                    'requires_php' => '8.4',
                );
            }
        }

        return $transient;
    }

    /**
     * Fornece informações detalhadas do plugin para o popup.
     *
     * @param false|object|array $result Resultado padrão.
     * @param string             $action Ação sendo executada.
     * @param object             $args   Argumentos da requisição.
     * @return false|object|array
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        // Verifica se é um plugin DPS
        $plugin_file = $this->get_plugin_file_by_slug( $args->slug ?? '' );
        if ( ! $plugin_file ) {
            return $result;
        }

        $plugin_info  = $this->plugins[ $plugin_file ];
        $release_data = $this->get_release_data();

        if ( empty( $release_data ) ) {
            return $result;
        }

        $latest_version = $this->get_latest_version( $release_data );
        $changelog      = $this->get_changelog( $release_data );

        return (object) array(
            'name'           => $plugin_info['name'],
            'slug'           => $plugin_info['slug'],
            'version'        => $latest_version,
            'author'         => '<a href="https://www.probst.pro">PRObst</a>',
            'author_profile' => 'https://www.probst.pro',
            'homepage'       => 'https://github.com/' . $this->github_repo,
            'download_link'  => $this->get_download_url( $release_data, $plugin_info['repo_path'] ),
            'requires'       => '6.9',
            'tested'         => get_bloginfo( 'version' ),
            'requires_php'   => '8.4',
            'last_updated'   => $release_data['published_at'] ?? '',
            'sections'       => array(
                'description'  => $this->get_plugin_description( $plugin_file ),
                'installation' => $this->get_installation_instructions(),
                'changelog'    => $changelog,
            ),
        );
    }

    /**
     * Obtém dados da release mais recente do GitHub.
     *
     * @param bool $force_refresh Forçar atualização do cache.
     * @return array|null
     */
    private function get_release_data( $force_refresh = false ) {
        // Verifica cache
        if ( ! $force_refresh ) {
            $cached_data = get_transient( $this->cache_key );
            if ( false !== $cached_data ) {
                return $cached_data;
            }
        }

        // Faz requisição à API do GitHub
        $url = sprintf(
            '%s/repos/%s/releases/latest',
            $this->github_api_url,
            $this->github_repo
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout'    => 15,
                'headers'    => array(
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; DPS-Updater',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return null;
        }

        // Prepara dados relevantes
        $release_data = array(
            'tag_name'     => $data['tag_name'] ?? '',
            'name'         => $data['name'] ?? '',
            'body'         => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? '',
            'html_url'     => $data['html_url'] ?? '',
            'zipball_url'  => $data['zipball_url'] ?? '',
            'tarball_url'  => $data['tarball_url'] ?? '',
            'assets'       => array(),
        );

        // Processa assets (arquivos zip anexados à release)
        if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
            foreach ( $data['assets'] as $asset ) {
                $release_data['assets'][ $asset['name'] ] = $asset['browser_download_url'];
            }
        }

        // Salva no cache
        set_transient( $this->cache_key, $release_data, $this->cache_expiration );

        return $release_data;
    }

    /**
     * Extrai a versão da tag.
     *
     * @param array $release_data Dados da release.
     * @return string
     */
    private function get_latest_version( $release_data ) {
        $tag = $release_data['tag_name'] ?? '';
        // Remove prefixo 'v' se existir
        return ltrim( $tag, 'vV' );
    }

    /**
     * Obtém a URL de download do plugin.
     *
     * @param array  $release_data Dados da release.
     * @param string $repo_path    Caminho do plugin no repositório.
     * @return string
     */
    private function get_download_url( $release_data, $repo_path ) {
        // Primeiro, verifica se há um asset .zip específico para o plugin
        $plugin_slug = basename( $repo_path );
        $zip_name    = $plugin_slug . '.zip';

        if ( ! empty( $release_data['assets'][ $zip_name ] ) ) {
            return $release_data['assets'][ $zip_name ];
        }

        // Fallback: usa o zipball_url do repositório completo
        // Nota: O usuário precisará extrair manualmente o plugin desejado
        return $release_data['zipball_url'] ?? '';
    }

    /**
     * Obtém o changelog formatado.
     *
     * @param array $release_data Dados da release.
     * @return string
     */
    private function get_changelog( $release_data ) {
        $body = $release_data['body'] ?? '';

        if ( empty( $body ) ) {
            return '<p>' . esc_html__( 'Sem notas de lançamento disponíveis.', 'desi-pet-shower' ) . '</p>';
        }

        // Converte Markdown básico para HTML
        $html = nl2br( esc_html( $body ) );
        $html = preg_replace( '/^## (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^### (.+)$/m', '<h5>$1</h5>', $html );
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.+<\/li>\n?)+/', '<ul>$0</ul>', $html );

        return $html;
    }

    /**
     * Obtém a descrição do plugin.
     *
     * @param string $plugin_file Arquivo do plugin.
     * @return string
     */
    private function get_plugin_description( $plugin_file ) {
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
        return $plugin_data['Description'] ?? '';
    }

    /**
     * Retorna instruções de instalação.
     *
     * @return string
     */
    private function get_installation_instructions() {
        return sprintf(
            '<ol>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
            </ol>',
            esc_html__( 'Faça o download do arquivo .zip do plugin.', 'desi-pet-shower' ),
            esc_html__( 'No painel WordPress, vá em Plugins → Adicionar Novo → Enviar Plugin.', 'desi-pet-shower' ),
            esc_html__( 'Ative o plugin após a instalação.', 'desi-pet-shower' )
        );
    }

    /**
     * Busca o arquivo do plugin pelo slug.
     *
     * @param string $slug Slug do plugin.
     * @return string|null
     */
    private function get_plugin_file_by_slug( $slug ) {
        foreach ( $this->plugins as $plugin_file => $plugin_info ) {
            if ( $plugin_info['slug'] === $slug ) {
                return $plugin_file;
            }
        }
        return null;
    }

    /**
     * Ação após instalação do plugin.
     *
     * @param bool  $response   Resposta da instalação.
     * @param array $hook_extra Dados extras.
     * @param array $result     Resultado da instalação.
     * @return bool
     */
    public function after_install( $response, $hook_extra, $result ) {
        // Limpa cache após instalação
        delete_transient( $this->cache_key );

        return $response;
    }

    /**
     * Verifica se deve forçar checagem de atualizações.
     */
    public function maybe_force_check() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['dps_force_update_check'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( $this->cache_key );
            delete_site_transient( 'update_plugins' );
            wp_redirect( admin_url( 'plugins.php' ) );
            exit;
        }
    }

    /**
     * Exibe aviso sobre atualizações disponíveis.
     */
    public function update_notice() {
        // Apenas mostra em telas relevantes
        $screen = get_current_screen();
        if ( ! $screen || 'plugins' !== $screen->id ) {
            return;
        }

        // Verifica se há atualizações DPS disponíveis
        $update_data = get_site_transient( 'update_plugins' );
        if ( empty( $update_data->response ) ) {
            return;
        }

        $dps_updates = 0;
        foreach ( $this->plugins as $plugin_file => $plugin_info ) {
            if ( isset( $update_data->response[ $plugin_file ] ) ) {
                $dps_updates++;
            }
        }

        if ( $dps_updates > 0 ) {
            printf(
                '<div class="notice notice-info is-dismissible">
                    <p><strong>desi.pet by PRObst:</strong> %s</p>
                </div>',
                sprintf(
                    /* translators: %d: number of updates */
                    esc_html( _n(
                        '%d atualização disponível via GitHub.',
                        '%d atualizações disponíveis via GitHub.',
                        $dps_updates,
                        'desi-pet-shower'
                    ) ),
                    $dps_updates
                )
            );
        }
    }

    /**
     * Método público para forçar verificação de atualizações.
     *
     * @return array|null Dados da release ou null em caso de erro.
     */
    public function force_check() {
        delete_transient( $this->cache_key );
        return $this->get_release_data( true );
    }

    /**
     * Retorna a lista de plugins gerenciados.
     *
     * @return array
     */
    public function get_managed_plugins() {
        return $this->plugins;
    }

    /**
     * Verifica se um plugin específico é gerenciado pelo updater.
     *
     * @param string $plugin_file Arquivo do plugin.
     * @return bool
     */
    public function is_managed_plugin( $plugin_file ) {
        return isset( $this->plugins[ $plugin_file ] );
    }
}

/**
 * Inicializa o updater se não estiver desabilitado.
 * 
 * Use o filtro `dps_github_updater_enabled` para desabilitar:
 * add_filter( 'dps_github_updater_enabled', '__return_false' );
 */
add_action( 'init', function() {
    if ( apply_filters( 'dps_github_updater_enabled', true ) ) {
        DPS_GitHub_Updater::get_instance();
    }
}, 5 );
