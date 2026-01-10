<?php
/**
 * Classe responsável pelo controle de cache das páginas do DPS.
 *
 * Garante que páginas do sistema não sejam armazenadas em cache,
 * forçando o navegador e plugins de cache a sempre buscar conteúdo
 * atualizado do servidor.
 *
 * @package Desi_Pet_Shower
 * @since   1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DPS_Cache_Control - Prevenção de cache para páginas do sistema DPS.
 *
 * Esta classe intercepta requisições para páginas que contêm shortcodes
 * do DPS e envia headers HTTP de no-cache, além de definir a constante
 * DONOTCACHEPAGE para plugins de cache do WordPress.
 */
class DPS_Cache_Control {

    /**
     * Lista de shortcodes DPS que devem ter cache desabilitado.
     *
     * @var array
     */
    private static $dps_shortcodes = [
        // Base
        'dps_base',
        'dps_configuracoes',
        // Client Portal
        'dps_client_portal',
        'dps_client_login',
        // Agenda
        'dps_agenda_page',
        'dps_agenda_dashboard',
        'dps_charges_notes',
        // Groomers
        'dps_groomer_dashboard',
        'dps_groomer_agenda',
        'dps_groomer_review',
        'dps_groomer_reviews',
        'dps_groomer_portal',
        'dps_groomer_login',
        // Services
        'dps_services_catalog',
        // Finance
        'dps_fin_docs',
        // Registration
        'dps_registration_form',
        // AI
        'dps_ai_chat',
    ];

    /**
     * Indica se os headers de no-cache já foram enviados nesta requisição.
     *
     * @var bool
     */
    private static $headers_sent = false;

    /**
     * Inicializa o controle de cache.
     *
     * Registra hooks para detecção de páginas DPS e envio de headers.
     */
    public static function init() {
        // Hook antes do envio de headers para detectar páginas com shortcodes DPS
        add_action( 'template_redirect', [ __CLASS__, 'maybe_disable_page_cache' ], 1 );
        
        // Hook adicional via send_headers para garantir que headers sejam enviados
        add_action( 'send_headers', [ __CLASS__, 'send_nocache_headers' ], 1 );
        
        // Para páginas admin, sempre desabilitar cache
        add_action( 'admin_init', [ __CLASS__, 'disable_admin_cache' ], 1 );
    }

    /**
     * Verifica se a página atual contém shortcodes DPS e desabilita cache.
     *
     * Este método é executado no hook 'template_redirect', antes que
     * qualquer output seja enviado ao navegador.
     */
    public static function maybe_disable_page_cache() {
        // Ignora requisições de admin, AJAX, REST e cron
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        // Verifica se é uma requisição REST API
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }

        // Verifica se a página atual contém shortcodes DPS
        if ( self::page_has_dps_shortcode() ) {
            self::disable_cache();
        }
    }

    /**
     * Verifica se o conteúdo da página atual contém shortcodes do DPS.
     *
     * @return bool True se a página contém shortcodes DPS.
     */
    private static function page_has_dps_shortcode() {
        global $post;

        // Sem post atual, não há shortcode
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        $content = $post->post_content;

        // Verifica cada shortcode DPS
        foreach ( self::$dps_shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Desabilita o cache para a página atual.
     *
     * Define a constante DONOTCACHEPAGE e prepara para envio de headers.
     */
    public static function disable_cache() {
        // Define constante para plugins de cache (WP Super Cache, W3 Total Cache, etc.)
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        // Define outras constantes de cache para garantir compatibilidade
        if ( ! defined( 'DONOTCACHEDB' ) ) {
            define( 'DONOTCACHEDB', true );
        }

        if ( ! defined( 'DONOTMINIFY' ) ) {
            define( 'DONOTMINIFY', true );
        }

        if ( ! defined( 'DONOTCDN' ) ) {
            define( 'DONOTCDN', true );
        }

        if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
            define( 'DONOTCACHEOBJECT', true );
        }

        // Marca para enviar headers HTTP
        self::$headers_sent = false;
    }

    /**
     * Envia os headers HTTP de no-cache.
     *
     * Este método é chamado tanto pelo hook 'send_headers' quanto
     * diretamente quando necessário.
     */
    public static function send_nocache_headers() {
        // Evita enviar headers duplicados
        if ( self::$headers_sent ) {
            return;
        }

        // Verifica se headers já foram enviados pelo PHP
        if ( headers_sent() ) {
            return;
        }

        // Verifica se deve desabilitar cache (por constante ou por shortcode)
        if ( ! defined( 'DONOTCACHEPAGE' ) || ! DONOTCACHEPAGE ) {
            // Verificação adicional para páginas com shortcodes DPS
            if ( ! self::page_has_dps_shortcode() ) {
                return;
            }
        }

        // Envia headers de no-cache usando a função do WordPress
        nocache_headers();

        // Headers adicionais para garantir que o cache seja desabilitado
        header( 'Pragma: no-cache' );
        header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        
        self::$headers_sent = true;
    }

    /**
     * Desabilita cache para páginas administrativas do DPS.
     *
     * Garante que todas as páginas admin do DPS não sejam cacheadas,
     * independente de shortcodes.
     */
    public static function disable_admin_cache() {
        // Verifica se estamos em uma página admin do DPS
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas leitura de parâmetro para detecção de página
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        
        // Páginas do DPS começam com 'dps' ou 'desi-pet'
        if ( strpos( $page, 'dps' ) === 0 || strpos( $page, 'desi-pet' ) === 0 ) {
            self::disable_cache();
            
            // Envia headers imediatamente se possível
            if ( ! headers_sent() ) {
                nocache_headers();
            }
        }
    }

    /**
     * Método público para forçar desabilitação de cache.
     *
     * Pode ser chamado por add-ons ou outros componentes que precisam
     * garantir que uma página específica não seja cacheada.
     *
     * @example
     * ```php
     * // Em qualquer shortcode ou handler:
     * DPS_Cache_Control::force_no_cache();
     * ```
     */
    public static function force_no_cache() {
        self::disable_cache();
        
        if ( ! headers_sent() ) {
            self::send_nocache_headers();
        }
    }

    /**
     * Adiciona um shortcode à lista de shortcodes DPS.
     *
     * Permite que add-ons registrem seus próprios shortcodes para
     * desabilitação automática de cache.
     *
     * @param string $shortcode Nome do shortcode a adicionar.
     */
    public static function register_shortcode( $shortcode ) {
        if ( ! in_array( $shortcode, self::$dps_shortcodes, true ) ) {
            self::$dps_shortcodes[] = $shortcode;
        }
    }

    /**
     * Retorna a lista de shortcodes DPS registrados.
     *
     * @return array Lista de shortcodes.
     */
    public static function get_registered_shortcodes() {
        return self::$dps_shortcodes;
    }
}
