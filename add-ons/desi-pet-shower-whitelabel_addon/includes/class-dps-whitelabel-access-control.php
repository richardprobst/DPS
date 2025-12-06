<?php
/**
 * Classe de controle de acesso ao site do White Label.
 *
 * @package DPS_WhiteLabel_Addon
 * @since 1.1.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gerencia o controle de acesso ao site.
 *
 * @since 1.1.0
 */
class DPS_WhiteLabel_Access_Control {

	/**
	 * Nome da option onde as configurações são armazenadas.
	 */
	const OPTION_NAME = 'dps_whitelabel_access_control';

	/**
	 * Construtor da classe.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_settings_save' ] );
		add_action( 'template_redirect', [ $this, 'maybe_block_access' ], 2 );
		add_filter( 'rest_authentication_errors', [ $this, 'maybe_block_rest_api' ], 99 );
		add_action( 'admin_bar_menu', [ $this, 'add_access_control_indicator' ], 100 );
	}

	/**
	 * Retorna as configurações padrão.
	 *
	 * @return array Configurações padrão.
	 */
	public static function get_defaults() {
		return [
			'access_enabled'  => false,
			'allowed_roles'   => [ 'administrator' ],
			'exception_urls'  => [],
			'redirect_type'   => 'custom_login',
			'redirect_url'    => '',
			'redirect_back'   => true,
			'allow_rest_api'  => true,
			'allow_ajax'      => true,
			'allow_media'     => true,
			'blocked_message' => __( 'Este conteúdo é exclusivo para membros. Por favor, faça login para acessar.', 'dps-whitelabel-addon' ),
		];
	}

	/**
	 * Obtém configurações atuais.
	 *
	 * @return array Configurações mescladas com padrões.
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_NAME, [] );
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Obtém valor de uma configuração específica.
	 *
	 * @param string $key     Nome da configuração.
	 * @param mixed  $default Valor padrão se não existir.
	 * @return mixed Valor da configuração.
	 */
	public static function get( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Verifica se deve bloquear acesso à página atual.
	 */
	public function maybe_block_access() {
		$settings = self::get_settings();

		if ( empty( $settings['access_enabled'] ) ) {
			return;
		}

		// Bypass se usuário pode acessar
		if ( $this->can_user_access() ) {
			return;
		}

		// Bypass para áreas do WordPress
		if ( is_admin() || ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) ) {
			return;
		}

		// Bypass para AJAX
		if ( ! empty( $settings['allow_ajax'] ) && wp_doing_ajax() ) {
			return;
		}

		// Bypass para arquivos de mídia
		if ( ! empty( $settings['allow_media'] ) && $this->is_media_file() ) {
			return;
		}

		// Bypass se URL está nas exceções
		if ( $this->is_exception_url() ) {
			return;
		}

		// Permitir bypass via filtro
		if ( apply_filters( 'dps_whitelabel_access_can_access', false, wp_get_current_user() ) ) {
			return;
		}

		// Disparar ação antes de bloquear
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		do_action( 'dps_whitelabel_access_blocked', $current_url, wp_get_current_user() );

		// Bloquear e redirecionar
		$this->redirect_to_login();
	}

	/**
	 * Verifica se o usuário atual pode acessar.
	 *
	 * @return bool True se pode acessar.
	 */
	private function can_user_access() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings      = self::get_settings();
		$allowed_roles = $settings['allowed_roles'] ?? [ 'administrator' ];
		$user          = wp_get_current_user();

		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verifica se a URL atual está nas exceções.
	 *
	 * @return bool True se é exceção.
	 */
	private function is_exception_url() {
		$settings       = self::get_settings();
		$exception_urls = $settings['exception_urls'] ?? [];
		$current_url    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		// Aplicar filtro para permitir exceções dinâmicas
		$exception_urls = apply_filters( 'dps_whitelabel_access_exception_urls', $exception_urls );

		foreach ( $exception_urls as $exception ) {
			$exception = trim( $exception );
			if ( empty( $exception ) ) {
				continue;
			}

			// Suporte a wildcard
			if ( strpos( $exception, '*' ) !== false ) {
				// Substituir * por .* e escapar o resto
				$pattern = str_replace( '\*', '.*', preg_quote( $exception, '/' ) );
				if ( preg_match( '/^' . $pattern . '/i', $current_url ) ) {
					return true;
				}
			} else {
				// Comparação exata OU se a URL atual começa com a exceção seguida de /
				// Exemplo: exceção "/contact/" match "/contact/" e "/contact/form/"
				// Mas exceção "/" só match "/" exatamente
				if ( $exception === '/' ) {
					// Caso especial: "/" só match raiz exata
					if ( $current_url === '/' || $current_url === '' ) {
						return true;
					}
				} elseif ( $current_url === $exception || 
				           $current_url === rtrim( $exception, '/' ) ||
				           strpos( $current_url, rtrim( $exception, '/' ) . '/' ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Verifica se a requisição é para arquivo de mídia.
	 *
	 * @return bool True se é mídia.
	 */
	private function is_media_file() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		return strpos( $request_uri, '/wp-content/uploads/' ) !== false;
	}

	/**
	 * Redireciona para página de login.
	 */
	private function redirect_to_login() {
		$settings = self::get_settings();

		$redirect_url = $this->get_login_url();

		// Adicionar redirect_to se configurado
		if ( ! empty( $settings['redirect_back'] ) ) {
			$current_url  = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$redirect_url = add_query_arg( 'redirect_to', urlencode( $current_url ), $redirect_url );
		}

		// Permitir filtro
		$redirect_url = apply_filters( 'dps_whitelabel_access_redirect_url', $redirect_url, wp_get_current_user() );

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Obtém URL de login baseada nas configurações.
	 *
	 * @return string URL de login.
	 */
	private function get_login_url() {
		$settings = self::get_settings();

		switch ( $settings['redirect_type'] ?? 'custom_login' ) {
			case 'wp_login':
				return wp_login_url();
			case 'custom_url':
				$custom_url = ! empty( $settings['redirect_url'] ) ? $settings['redirect_url'] : '';
				// Validar para prevenir open redirect - deve ser URL interna
				if ( ! empty( $custom_url ) ) {
					$parsed = parse_url( $custom_url );
					// Permitir apenas URLs relativas ou do mesmo domínio
					if ( ! isset( $parsed['host'] ) || $parsed['host'] === $_SERVER['HTTP_HOST'] ) {
						return $custom_url;
					}
				}
				return wp_login_url();
			case 'custom_login':
			default:
				// Usar página de login customizada se houver
				$login_page_id = get_option( 'dps_custom_login_page_id' );
				return $login_page_id ? get_permalink( $login_page_id ) : wp_login_url();
		}
	}

	/**
	 * Bloqueia REST API se necessário.
	 *
	 * @param WP_Error|null|bool $result Erro atual.
	 * @return WP_Error|null|bool
	 */
	public function maybe_block_rest_api( $result ) {
		// Se já há erro, retornar
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$settings = self::get_settings();

		if ( empty( $settings['access_enabled'] ) ) {
			return $result;
		}

		if ( ! empty( $settings['allow_rest_api'] ) && is_user_logged_in() ) {
			return $result;
		}

		if ( $this->can_user_access() ) {
			return $result;
		}

		return new WP_Error(
			'rest_access_denied',
			__( 'Acesso à API REST requer autenticação.', 'dps-whitelabel-addon' ),
			[ 'status' => 401 ]
		);
	}

	/**
	 * Adiciona indicador de acesso restrito na admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Instância da admin bar.
	 */
	public function add_access_control_indicator( $wp_admin_bar ) {
		$settings = self::get_settings();

		if ( empty( $settings['access_enabled'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'dps-access-control-active',
			'title' => '<span style="background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px;">' .
			           esc_html__( '🔒 ACESSO RESTRITO', 'dps-whitelabel-addon' ) .
			           '</span>',
			'href'  => admin_url( 'admin.php?page=dps-whitelabel&tab=access-control' ),
			'meta'  => [
				'title' => __( 'O controle de acesso está ativo. Clique para configurar.', 'dps-whitelabel-addon' ),
			],
		] );
	}

	/**
	 * Processa salvamento de configurações.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST['dps_whitelabel_save_access_control'] ) ) {
			return;
		}

		if ( ! isset( $_POST['dps_whitelabel_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_whitelabel_nonce'] ) ), 'dps_whitelabel_settings' ) ) {
			add_settings_error(
				'dps_whitelabel',
				'invalid_nonce',
				__( 'Erro de segurança. Por favor, tente novamente.', 'dps-whitelabel-addon' ),
				'error'
			);
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error(
				'dps_whitelabel',
				'no_permission',
				__( 'Você não tem permissão para alterar estas configurações.', 'dps-whitelabel-addon' ),
				'error'
			);
			return;
		}

		// Sanitizar roles permitidas
		$allowed_roles = [];
		if ( isset( $_POST['allowed_roles'] ) && is_array( $_POST['allowed_roles'] ) ) {
			foreach ( $_POST['allowed_roles'] as $role ) {
				$allowed_roles[] = sanitize_key( $role );
			}
		}

		// Garantir que administrator sempre está incluído
		if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
			$allowed_roles[] = 'administrator';
		}

		// Sanitizar exception URLs
		$exception_urls = [];
		if ( isset( $_POST['exception_urls'] ) ) {
			$raw_urls = sanitize_textarea_field( wp_unslash( $_POST['exception_urls'] ) );
			$lines    = explode( "\n", $raw_urls );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$exception_urls[] = $line;
				}
			}
		}

		// Validar e sanitizar redirect URL customizada
		$redirect_url = '';
		if ( ! empty( $_POST['redirect_url'] ) ) {
			$redirect_url = esc_url_raw( wp_unslash( $_POST['redirect_url'] ) );
			// Validar para prevenir open redirect
			$parsed = parse_url( $redirect_url );
			if ( isset( $parsed['host'] ) && $parsed['host'] !== $_SERVER['HTTP_HOST'] ) {
				add_settings_error(
					'dps_whitelabel',
					'invalid_redirect_url',
					__( 'Aviso: A URL de redirecionamento customizada aponta para um domínio externo. Por segurança, apenas URLs internas são permitidas.', 'dps-whitelabel-addon' ),
					'warning'
				);
				$redirect_url = '';
			}
		}

		$new_settings = [
			'access_enabled'  => isset( $_POST['access_enabled'] ),
			'allowed_roles'   => $allowed_roles,
			'exception_urls'  => $exception_urls,
			'redirect_type'   => sanitize_key( $_POST['redirect_type'] ?? 'custom_login' ),
			'redirect_url'    => $redirect_url,
			'redirect_back'   => isset( $_POST['redirect_back'] ),
			'allow_rest_api'  => isset( $_POST['allow_rest_api'] ),
			'allow_ajax'      => isset( $_POST['allow_ajax'] ),
			'allow_media'     => isset( $_POST['allow_media'] ),
			'blocked_message' => wp_kses_post( wp_unslash( $_POST['blocked_message'] ?? '' ) ),
		];

		update_option( self::OPTION_NAME, $new_settings );

		// Disparar ação após salvar
		do_action( 'dps_whitelabel_access_settings_saved', $new_settings );

		add_settings_error(
			'dps_whitelabel',
			'settings_saved',
			__( 'Configurações de controle de acesso salvas com sucesso!', 'dps-whitelabel-addon' ),
			'success'
		);
	}

	/**
	 * Verifica se o controle de acesso está ativo.
	 *
	 * @return bool True se ativo.
	 */
	public static function is_active() {
		$settings = self::get_settings();
		return ! empty( $settings['access_enabled'] );
	}
}
