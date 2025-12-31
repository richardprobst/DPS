<?php
/**
 * Gerenciador de Add-ons do DPS.
 *
 * Fornece funcionalidades para:
 * - Listar add-ons disponíveis e instalados
 * - Verificar status de ativação
 * - Determinar ordem correta de ativação baseada em dependências
 * - Ativar/desativar add-ons em lote na ordem correta
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe gerenciadora de add-ons.
 */
class DPS_Addon_Manager {

    /**
     * Diretório onde os add-ons estão instalados (relativo a WP_PLUGIN_DIR).
     *
     * @var string
     */
    const ADDONS_DIR = 'add-ons';

    /**
     * Instância singleton.
     *
     * @var DPS_Addon_Manager|null
     */
    private static $instance = null;

    /**
     * Lista de add-ons registrados com metadados.
     *
     * @var array
     */
    private $addons = [];

    /**
     * Mapeamento de slug do add-on para arquivo principal.
     *
     * @var array
     */
    private $addon_files = [];

    /**
     * Obtém a instância singleton.
     *
     * @return DPS_Addon_Manager
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
        $this->register_core_addons();
        add_action( 'admin_menu', [ $this, 'register_admin_page' ], 20 );
        add_action( 'admin_init', [ $this, 'handle_addon_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Registra os add-ons conhecidos do ecossistema DPS.
     *
     * Cada add-on tem:
     * - slug: identificador único
     * - name: nome de exibição
     * - description: descrição curta
     * - file: caminho relativo para o arquivo principal (dentro de add-ons/)
     * - class: classe principal do add-on
     * - dependencies: array de slugs de add-ons que devem estar ativos
     * - priority: ordem de ativação (menor = primeiro)
     * - category: categoria para organização na interface
     */
    private function register_core_addons() {
        $this->addons = [
            // Categoria: Essenciais (ativados primeiro)
            'services' => [
                'slug'         => 'services',
                'name'         => __( 'Serviços', 'desi-pet-shower' ),
                'description'  => __( 'Catálogo de serviços com preços por porte. Base para cálculos de valores.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-services_addon/desi-pet-shower-services.php',
                'class'        => 'DPS_Services_Addon',
                'dependencies' => [],
                'priority'     => 10,
                'category'     => 'essential',
                'icon'         => '💇',
            ],
            'finance' => [
                'slug'         => 'finance',
                'name'         => __( 'Financeiro', 'desi-pet-shower' ),
                'description'  => __( 'Controle financeiro completo. Receitas, despesas e relatórios.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-finance_addon/desi-pet-shower-finance-addon.php',
                'class'        => 'DPS_Finance_Addon',
                'dependencies' => [],
                'priority'     => 15,
                'category'     => 'essential',
                'icon'         => '💰',
            ],
            'communications' => [
                'slug'         => 'communications',
                'name'         => __( 'Comunicações', 'desi-pet-shower' ),
                'description'  => __( 'WhatsApp, SMS e e-mail integrados. Notificações automáticas.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-communications_addon/desi-pet-shower-communications-addon.php',
                'class'        => 'DPS_Communications_Addon',
                'dependencies' => [],
                'priority'     => 20,
                'category'     => 'essential',
                'icon'         => '📱',
            ],

            // Categoria: Operação
            'agenda' => [
                'slug'         => 'agenda',
                'name'         => __( 'Agenda', 'desi-pet-shower' ),
                'description'  => __( 'Visualização e gestão de agendamentos diários.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php',
                'class'        => 'DPS_Agenda_Addon',
                'dependencies' => [ 'services' ],
                'priority'     => 30,
                'category'     => 'operation',
                'icon'         => '📅',
            ],
            'groomers' => [
                'slug'         => 'groomers',
                'name'         => __( 'Groomers', 'desi-pet-shower' ),
                'description'  => __( 'Gestão de profissionais e relatórios de produtividade.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-groomers_addon/desi-pet-shower-groomers-addon.php',
                'class'        => 'DPS_Groomers_Addon',
                'dependencies' => [],
                'priority'     => 35,
                'category'     => 'operation',
                'icon'         => '👤',
            ],
            'subscription' => [
                'slug'         => 'subscription',
                'name'         => __( 'Assinaturas', 'desi-pet-shower' ),
                'description'  => __( 'Pacotes mensais de banho com frequência configurável.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-subscription_addon/desi-pet-shower-subscription.php',
                'class'        => 'DPS_Subscription_Addon',
                'dependencies' => [ 'services', 'finance' ],
                'priority'     => 40,
                'category'     => 'operation',
                'icon'         => '🔄',
            ],
            'stock' => [
                'slug'         => 'stock',
                'name'         => __( 'Estoque', 'desi-pet-shower' ),
                'description'  => __( 'Controle de insumos com baixas automáticas.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-stock_addon/desi-pet-shower-stock.php',
                'class'        => 'DPS_Stock_Addon',
                'dependencies' => [],
                'priority'     => 45,
                'category'     => 'operation',
                'icon'         => '📦',
            ],

            // Categoria: Integrações
            'payment' => [
                'slug'         => 'payment',
                'name'         => __( 'Pagamentos', 'desi-pet-shower' ),
                'description'  => __( 'Integração com Mercado Pago para links de pagamento.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-payment_addon/desi-pet-shower-payment-addon.php',
                'class'        => 'DPS_Payment_Addon',
                'dependencies' => [ 'finance' ],
                'priority'     => 50,
                'category'     => 'integrations',
                'icon'         => '💳',
            ],
            'push' => [
                'slug'         => 'push',
                'name'         => __( 'Notificações Push', 'desi-pet-shower' ),
                'description'  => __( 'Relatórios diários/semanais por e-mail e Telegram.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-push_addon/desi-pet-shower-push-addon.php',
                'class'        => 'DPS_Push_Addon',
                'dependencies' => [],
                'priority'     => 55,
                'category'     => 'integrations',
                'icon'         => '🔔',
            ],

            // Categoria: Cliente
            'registration' => [
                'slug'         => 'registration',
                'name'         => __( 'Cadastro Público', 'desi-pet-shower' ),
                'description'  => __( 'Formulário público para cadastro de clientes e pets.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-registration_addon/desi-pet-shower-registration-addon.php',
                'class'        => 'DPS_Registration_Addon',
                'dependencies' => [],
                'priority'     => 60,
                'category'     => 'client',
                'icon'         => '📝',
            ],
            'client-portal' => [
                'slug'         => 'client-portal',
                'name'         => __( 'Portal do Cliente', 'desi-pet-shower' ),
                'description'  => __( 'Área autenticada para clientes visualizarem seus dados.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-client-portal_addon/desi-pet-shower-client-portal.php',
                'class'        => 'DPS_Client_Portal',
                'dependencies' => [],
                'priority'     => 65,
                'category'     => 'client',
                'icon'         => '🏠',
            ],
            'loyalty' => [
                'slug'         => 'loyalty',
                'name'         => __( 'Fidelidade & Campanhas', 'desi-pet-shower' ),
                'description'  => __( 'Programa de pontos, indicações e campanhas.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-loyalty_addon/desi-pet-shower-loyalty.php',
                'class'        => 'DPS_Loyalty_Addon',
                'dependencies' => [],
                'priority'     => 70,
                'category'     => 'client',
                'icon'         => '🎁',
            ],

            // Categoria: Avançado
            'ai' => [
                'slug'         => 'ai',
                'name'         => __( 'Assistente de IA', 'desi-pet-shower' ),
                'description'  => __( 'Chat inteligente no Portal do Cliente e sugestões de mensagens.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-ai_addon/desi-pet-shower-ai-addon.php',
                'class'        => 'DPS_AI_Addon',
                'dependencies' => [ 'client-portal' ],
                'priority'     => 75,
                'category'     => 'advanced',
                'icon'         => '🤖',
            ],
            'stats' => [
                'slug'         => 'stats',
                'name'         => __( 'Estatísticas', 'desi-pet-shower' ),
                'description'  => __( 'Dashboard com métricas, gráficos e relatórios.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-stats_addon/desi-pet-shower-stats-addon.php',
                'class'        => 'DPS_Stats_Addon',
                'dependencies' => [],
                'priority'     => 80,
                'category'     => 'advanced',
                'icon'         => '📊',
            ],

            // Categoria: Sistema
            'backup' => [
                'slug'         => 'backup',
                'name'         => __( 'Backup & Restauração', 'desi-pet-shower' ),
                'description'  => __( 'Exportação e importação de todos os dados do sistema.', 'desi-pet-shower' ),
                'file'         => 'desi-pet-shower-backup_addon/desi-pet-shower-backup-addon.php',
                'class'        => 'DPS_Backup_Addon',
                'dependencies' => [],
                'priority'     => 85,
                'category'     => 'system',
                'icon'         => '💾',
            ],
        ];

        // Mapeia arquivos para busca rápida
        foreach ( $this->addons as $slug => $addon ) {
            $this->addon_files[ $slug ] = $addon['file'];
        }
    }

    /**
     * Retorna todos os add-ons registrados.
     *
     * @return array
     */
    public function get_all_addons() {
        return $this->addons;
    }

    /**
     * Retorna categorias de add-ons com labels traduzidos.
     *
     * @return array
     */
    public function get_categories() {
        return [
            'essential'    => __( 'Essenciais', 'desi-pet-shower' ),
            'operation'    => __( 'Operação', 'desi-pet-shower' ),
            'integrations' => __( 'Integrações', 'desi-pet-shower' ),
            'client'       => __( 'Cliente', 'desi-pet-shower' ),
            'advanced'     => __( 'Avançado', 'desi-pet-shower' ),
            'system'       => __( 'Sistema', 'desi-pet-shower' ),
        ];
    }

    /**
     * Retorna add-ons agrupados por categoria.
     *
     * @return array
     */
    public function get_addons_by_category() {
        $grouped = [];
        foreach ( $this->addons as $addon ) {
            $category = $addon['category'];
            if ( ! isset( $grouped[ $category ] ) ) {
                $grouped[ $category ] = [];
            }
            $grouped[ $category ][] = $addon;
        }
        return $grouped;
    }

    /**
     * Verifica se um add-on está instalado (arquivo existe).
     *
     * @param string $slug Slug do add-on.
     * @return bool
     */
    public function is_installed( $slug ) {
        if ( ! isset( $this->addon_files[ $slug ] ) ) {
            return false;
        }
        $addon_path = WP_PLUGIN_DIR . '/' . self::ADDONS_DIR . '/' . $this->addon_files[ $slug ];
        // Também verifica se está no diretório padrão de plugins
        $alt_path = WP_PLUGIN_DIR . '/' . $this->addon_files[ $slug ];
        return file_exists( $addon_path ) || file_exists( $alt_path );
    }

    /**
     * Verifica se um add-on está ativo.
     *
     * @param string $slug Slug do add-on.
     * @return bool
     */
    public function is_active( $slug ) {
        if ( ! isset( $this->addons[ $slug ] ) ) {
            return false;
        }
        // Verifica pela classe principal do add-on
        return class_exists( $this->addons[ $slug ]['class'] );
    }

    /**
     * Retorna o caminho completo do arquivo principal do add-on.
     *
     * @param string $slug Slug do add-on.
     * @return string|false
     */
    public function get_addon_file( $slug ) {
        if ( ! isset( $this->addon_files[ $slug ] ) ) {
            return false;
        }
        $addon_path = WP_PLUGIN_DIR . '/' . self::ADDONS_DIR . '/' . $this->addon_files[ $slug ];
        if ( file_exists( $addon_path ) ) {
            return self::ADDONS_DIR . '/' . $this->addon_files[ $slug ];
        }
        // Verifica caminho alternativo
        $alt_path = WP_PLUGIN_DIR . '/' . $this->addon_files[ $slug ];
        if ( file_exists( $alt_path ) ) {
            return $this->addon_files[ $slug ];
        }
        return false;
    }

    /**
     * Retorna add-ons que dependem de um determinado add-on.
     *
     * @param string $slug Slug do add-on.
     * @return array Slugs de add-ons dependentes.
     */
    public function get_dependents( $slug ) {
        $dependents = [];
        foreach ( $this->addons as $addon_slug => $addon ) {
            if ( in_array( $slug, $addon['dependencies'], true ) ) {
                $dependents[] = $addon_slug;
            }
        }
        return $dependents;
    }

    /**
     * Verifica se todas as dependências de um add-on estão satisfeitas.
     *
     * @param string $slug Slug do add-on.
     * @return array Array com 'satisfied' (bool) e 'missing' (array de slugs).
     */
    public function check_dependencies( $slug ) {
        if ( ! isset( $this->addons[ $slug ] ) ) {
            return [ 'satisfied' => false, 'missing' => [] ];
        }

        $dependencies = $this->addons[ $slug ]['dependencies'];
        $missing = [];

        foreach ( $dependencies as $dep ) {
            if ( ! $this->is_active( $dep ) ) {
                $missing[] = $dep;
            }
        }

        return [
            'satisfied' => empty( $missing ),
            'missing'   => $missing,
        ];
    }

    /**
     * Ordena add-ons por dependências (ordenação topológica).
     *
     * Garante que add-ons sejam ativados após suas dependências.
     *
     * @param array $slugs Array de slugs a ordenar.
     * @return array Slugs ordenados por dependência.
     */
    public function sort_by_dependencies( $slugs ) {
        // Primeiro ordena por prioridade
        usort( $slugs, function( $a, $b ) {
            $priority_a = isset( $this->addons[ $a ] ) ? $this->addons[ $a ]['priority'] : 999;
            $priority_b = isset( $this->addons[ $b ] ) ? $this->addons[ $b ]['priority'] : 999;
            return $priority_a - $priority_b;
        } );

        // Depois ajusta para garantir que dependências venham antes
        $sorted = [];
        $visited = [];

        foreach ( $slugs as $slug ) {
            $this->visit_for_sort( $slug, $sorted, $visited );
        }

        return $sorted;
    }

    /**
     * Visita um add-on recursivamente para ordenação topológica.
     *
     * @param string $slug Slug do add-on a visitar.
     * @param array  $sorted Array de slugs ordenados (passado por referência).
     * @param array  $visited Array de slugs já visitados (passado por referência).
     */
    private function visit_for_sort( $slug, &$sorted, &$visited ) {
        if ( isset( $visited[ $slug ] ) ) {
            return;
        }
        $visited[ $slug ] = true;

        if ( isset( $this->addons[ $slug ] ) ) {
            foreach ( $this->addons[ $slug ]['dependencies'] as $dep ) {
                $this->visit_for_sort( $dep, $sorted, $visited );
            }
        }

        $sorted[] = $slug;
    }

    /**
     * Retorna a ordem recomendada de ativação para todos os add-ons instalados.
     *
     * @return array Array com informações de cada add-on e ordem de ativação.
     */
    public function get_activation_order() {
        $installed = [];
        foreach ( array_keys( $this->addons ) as $slug ) {
            if ( $this->is_installed( $slug ) ) {
                $installed[] = $slug;
            }
        }

        $sorted = $this->sort_by_dependencies( $installed );
        $order = [];
        $position = 1;

        foreach ( $sorted as $slug ) {
            $addon = $this->addons[ $slug ];
            $order[] = [
                'position'     => $position++,
                'slug'         => $slug,
                'name'         => $addon['name'],
                'icon'         => $addon['icon'],
                'dependencies' => $addon['dependencies'],
                'is_active'    => $this->is_active( $slug ),
                'file'         => $this->get_addon_file( $slug ),
            ];
        }

        return $order;
    }

    /**
     * Ativa múltiplos add-ons na ordem correta.
     *
     * @param array $slugs Slugs dos add-ons a ativar.
     * @return array Resultado com 'success' e 'errors'.
     */
    public function activate_addons( $slugs ) {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Você não tem permissão para ativar plugins.', 'desi-pet-shower' ) ],
            ];
        }

        // Ordena pela dependência
        $sorted = $this->sort_by_dependencies( $slugs );
        $errors = [];
        $activated = [];

        foreach ( $sorted as $slug ) {
            // Verifica dependências
            $deps_check = $this->check_dependencies( $slug );
            if ( ! $deps_check['satisfied'] ) {
                // Verifica se as dependências que faltam estão na lista de ativação
                $will_activate = array_intersect( $deps_check['missing'], $sorted );
                $still_missing = array_diff( $deps_check['missing'], $sorted );
                
                if ( ! empty( $still_missing ) ) {
                    $missing_names = array_map( function( $dep_slug ) {
                        return isset( $this->addons[ $dep_slug ] ) ? $this->addons[ $dep_slug ]['name'] : $dep_slug;
                    }, $still_missing );
                    $errors[] = sprintf(
                        /* translators: 1: add-on name, 2: comma-separated list of dependencies */
                        __( '%1$s requer: %2$s', 'desi-pet-shower' ),
                        $this->addons[ $slug ]['name'],
                        implode( ', ', $missing_names )
                    );
                    continue;
                }
            }

            // Tenta ativar
            $file = $this->get_addon_file( $slug );
            if ( ! $file ) {
                $errors[] = sprintf(
                    /* translators: %s: add-on name */
                    __( '%s não está instalado.', 'desi-pet-shower' ),
                    $this->addons[ $slug ]['name']
                );
                continue;
            }

            // Verifica se já está ativo
            if ( is_plugin_active( $file ) ) {
                $activated[] = $slug;
                continue;
            }

            // Ativa o plugin
            $result = activate_plugin( $file );
            if ( is_wp_error( $result ) ) {
                $errors[] = sprintf(
                    /* translators: 1: add-on name, 2: error message */
                    __( 'Erro ao ativar %1$s: %2$s', 'desi-pet-shower' ),
                    $this->addons[ $slug ]['name'],
                    $result->get_error_message()
                );
            } else {
                $activated[] = $slug;
            }
        }

        return [
            'success'   => empty( $errors ),
            'activated' => $activated,
            'errors'    => $errors,
        ];
    }

    /**
     * Desativa add-ons na ordem inversa de dependências.
     *
     * @param array $slugs Slugs dos add-ons a desativar.
     * @return array Resultado com 'success' e 'errors'.
     */
    public function deactivate_addons( $slugs ) {
        if ( ! current_user_can( 'deactivate_plugins' ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Você não tem permissão para desativar plugins.', 'desi-pet-shower' ) ],
            ];
        }

        // Ordena pela dependência (invertido para desativação)
        $sorted = array_reverse( $this->sort_by_dependencies( $slugs ) );
        $errors = [];
        $deactivated = [];

        foreach ( $sorted as $slug ) {
            // Verifica se há dependentes ativos que não estão na lista
            $dependents = $this->get_dependents( $slug );
            $active_dependents = array_filter( $dependents, function( $dep ) use ( $slugs ) {
                return $this->is_active( $dep ) && ! in_array( $dep, $slugs, true );
            } );

            if ( ! empty( $active_dependents ) ) {
                $dependent_names = array_map( function( $dep_slug ) {
                    return isset( $this->addons[ $dep_slug ] ) ? $this->addons[ $dep_slug ]['name'] : $dep_slug;
                }, $active_dependents );
                $errors[] = sprintf(
                    /* translators: 1: add-on name, 2: comma-separated list of dependents */
                    __( '%1$s é necessário para: %2$s', 'desi-pet-shower' ),
                    $this->addons[ $slug ]['name'],
                    implode( ', ', $dependent_names )
                );
                continue;
            }

            $file = $this->get_addon_file( $slug );
            if ( ! $file || ! is_plugin_active( $file ) ) {
                $deactivated[] = $slug;
                continue;
            }

            deactivate_plugins( $file );
            $deactivated[] = $slug;
        }

        return [
            'success'     => empty( $errors ),
            'deactivated' => $deactivated,
            'errors'      => $errors,
        ];
    }

    /**
     * Registra a página administrativa de gerenciamento de add-ons.
     */
    public function register_admin_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Add-ons DPS', 'desi-pet-shower' ),
            __( 'Add-ons', 'desi-pet-shower' ),
            'manage_options',
            'dps-addons',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enfileira assets para a página de add-ons.
     *
     * @param string $hook Hook da página atual.
     */
    public function enqueue_assets( $hook ) {
        // O hook é formado pelo slug do menu pai + '_page_' + slug da página
        // Para 'desi-pet-shower' como parent e 'dps-addons' como slug da página
        if ( false === strpos( $hook, 'dps-addons' ) ) {
            return;
        }

        wp_enqueue_style(
            'dps-addon-manager',
            DPS_BASE_URL . 'assets/css/addon-manager.css',
            [],
            DPS_BASE_VERSION
        );
    }

    /**
     * Processa ações de ativação/desativação de add-ons.
     */
    public function handle_addon_actions() {
        if ( ! isset( $_POST['dps_addon_action'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'dps_addon_manager', 'dps_addon_nonce' ) ) {
            wp_die( esc_html__( 'Ação não autorizada.', 'desi-pet-shower' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para gerenciar add-ons.', 'desi-pet-shower' ) );
        }

        $action = sanitize_text_field( wp_unslash( $_POST['dps_addon_action'] ) );
        
        // Verifica se é ação individual (formato: activate_single_SLUG ou deactivate_single_SLUG)
        if ( strpos( $action, 'activate_single_' ) === 0 ) {
            $slug = sanitize_key( str_replace( 'activate_single_', '', $action ) );
            $this->handle_single_addon_action( 'activate', $slug );
            return;
        }
        
        if ( strpos( $action, 'deactivate_single_' ) === 0 ) {
            $slug = sanitize_key( str_replace( 'deactivate_single_', '', $action ) );
            $this->handle_single_addon_action( 'deactivate', $slug );
            return;
        }
        
        // Ação em lote (comportamento original)
        $selected = isset( $_POST['dps_addons'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['dps_addons'] ) ) : [];

        if ( empty( $selected ) ) {
            add_settings_error(
                'dps_addon_manager',
                'no_selection',
                __( 'Nenhum add-on selecionado.', 'desi-pet-shower' ),
                'error'
            );
            return;
        }

        if ( 'activate' === $action ) {
            $result = $this->activate_addons( $selected );
            if ( $result['success'] ) {
                add_settings_error(
                    'dps_addon_manager',
                    'activated',
                    sprintf(
                        /* translators: %d: number of add-ons activated */
                        __( '%d add-on(s) ativado(s) com sucesso.', 'desi-pet-shower' ),
                        count( $result['activated'] )
                    ),
                    'updated'
                );
            } else {
                foreach ( $result['errors'] as $error ) {
                    add_settings_error(
                        'dps_addon_manager',
                        'activation_error',
                        $error,
                        'error'
                    );
                }
            }
        } elseif ( 'deactivate' === $action ) {
            $result = $this->deactivate_addons( $selected );
            if ( $result['success'] ) {
                add_settings_error(
                    'dps_addon_manager',
                    'deactivated',
                    sprintf(
                        /* translators: %d: number of add-ons deactivated */
                        __( '%d add-on(s) desativado(s) com sucesso.', 'desi-pet-shower' ),
                        count( $result['deactivated'] )
                    ),
                    'updated'
                );
            } else {
                foreach ( $result['errors'] as $error ) {
                    add_settings_error(
                        'dps_addon_manager',
                        'deactivation_error',
                        $error,
                        'error'
                    );
                }
            }
        }
    }

    /**
     * Processa ação de ativação/desativação de um único add-on.
     *
     * @param string $action Ação a realizar ('activate' ou 'deactivate').
     * @param string $slug   Slug do add-on (já sanitizado com sanitize_key).
     */
    private function handle_single_addon_action( $action, $slug ) {
        if ( ! isset( $this->addons[ $slug ] ) ) {
            add_settings_error(
                'dps_addon_manager',
                'invalid_addon',
                __( 'Add-on inválido.', 'desi-pet-shower' ),
                'error'
            );
            return;
        }
        
        $addon_name = $this->addons[ $slug ]['name'];
        
        if ( 'activate' === $action ) {
            $result = $this->activate_addons( [ $slug ] );
            if ( $result['success'] && ! empty( $result['activated'] ) ) {
                add_settings_error(
                    'dps_addon_manager',
                    'activated',
                    sprintf(
                        /* translators: %s: add-on name */
                        __( '%s ativado com sucesso.', 'desi-pet-shower' ),
                        $addon_name
                    ),
                    'updated'
                );
            } else {
                foreach ( $result['errors'] as $error ) {
                    add_settings_error(
                        'dps_addon_manager',
                        'activation_error',
                        $error,
                        'error'
                    );
                }
            }
        } else {
            $result = $this->deactivate_addons( [ $slug ] );
            if ( $result['success'] && ! empty( $result['deactivated'] ) ) {
                add_settings_error(
                    'dps_addon_manager',
                    'deactivated',
                    sprintf(
                        /* translators: %s: add-on name */
                        __( '%s desativado com sucesso.', 'desi-pet-shower' ),
                        $addon_name
                    ),
                    'updated'
                );
            } else {
                foreach ( $result['errors'] as $error ) {
                    add_settings_error(
                        'dps_addon_manager',
                        'deactivation_error',
                        $error,
                        'error'
                    );
                }
            }
        }
    }

    /**
     * Renderiza a página administrativa de gerenciamento de add-ons.
     */
    public function render_admin_page() {
        $categories = $this->get_categories();
        $addons_by_category = $this->get_addons_by_category();
        $activation_order = $this->get_activation_order();

        ?>
        <div class="wrap dps-addon-manager">
            <h1><?php esc_html_e( 'Gerenciador de Add-ons', 'desi-pet-shower' ); ?></h1>
            
            <p class="description">
                <?php esc_html_e( 'Gerencie os add-ons do sistema DPS. Os add-ons serão ativados automaticamente na ordem correta, respeitando suas dependências.', 'desi-pet-shower' ); ?>
            </p>

            <?php settings_errors( 'dps_addon_manager' ); ?>

            <!-- Ordem de Ativação Recomendada -->
            <div class="dps-activation-order">
                <h2><?php esc_html_e( 'Ordem de Ativação Recomendada', 'desi-pet-shower' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Esta é a ordem recomendada para ativar os add-ons instalados, baseada em suas dependências:', 'desi-pet-shower' ); ?>
                </p>
                <div class="dps-order-list">
                    <?php foreach ( $activation_order as $item ) : ?>
                        <span class="dps-order-item <?php echo $item['is_active'] ? 'is-active' : 'is-inactive'; ?>">
                            <span class="dps-order-position"><?php echo esc_html( $item['position'] ); ?></span>
                            <span class="dps-order-icon"><?php echo esc_html( $item['icon'] ); ?></span>
                            <span class="dps-order-name"><?php echo esc_html( $item['name'] ); ?></span>
                            <?php if ( $item['is_active'] ) : ?>
                                <span class="dps-order-status">✓</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Formulário de Ativação/Desativação -->
            <form method="post" action="">
                <?php wp_nonce_field( 'dps_addon_manager', 'dps_addon_nonce' ); ?>

                <?php foreach ( $categories as $cat_slug => $cat_name ) : ?>
                    <?php if ( isset( $addons_by_category[ $cat_slug ] ) ) : ?>
                        <div class="dps-addon-category">
                            <h2><?php echo esc_html( $cat_name ); ?></h2>
                            <div class="dps-addon-grid">
                                <?php foreach ( $addons_by_category[ $cat_slug ] as $addon ) : ?>
                                    <?php
                                    $is_installed = $this->is_installed( $addon['slug'] );
                                    $is_active = $this->is_active( $addon['slug'] );
                                    $deps_check = $this->check_dependencies( $addon['slug'] );
                                    ?>
                                    <div class="dps-addon-card <?php echo $is_active ? 'is-active' : ''; ?> <?php echo ! $is_installed ? 'not-installed' : ''; ?>">
                                        <div class="dps-addon-header">
                                            <span class="dps-addon-icon"><?php echo esc_html( $addon['icon'] ); ?></span>
                                            <div class="dps-addon-title">
                                                <h3><?php echo esc_html( $addon['name'] ); ?></h3>
                                                <?php if ( $is_active ) : ?>
                                                    <span class="dps-status dps-status--active"><?php esc_html_e( 'Ativo', 'desi-pet-shower' ); ?></span>
                                                <?php elseif ( $is_installed ) : ?>
                                                    <span class="dps-status dps-status--inactive"><?php esc_html_e( 'Inativo', 'desi-pet-shower' ); ?></span>
                                                <?php else : ?>
                                                    <span class="dps-status dps-status--missing"><?php esc_html_e( 'Não Instalado', 'desi-pet-shower' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ( $is_installed ) : ?>
                                                <label class="dps-addon-checkbox">
                                                    <input type="checkbox" name="dps_addons[]" value="<?php echo esc_attr( $addon['slug'] ); ?>" <?php checked( $is_active ); ?>>
                                                    <span class="checkmark"></span>
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                        <p class="dps-addon-description"><?php echo esc_html( $addon['description'] ); ?></p>
                                        <?php if ( ! empty( $addon['dependencies'] ) ) : ?>
                                            <div class="dps-addon-deps">
                                                <strong><?php esc_html_e( 'Requer:', 'desi-pet-shower' ); ?></strong>
                                                <?php
                                                $dep_names = array_map( function( $dep ) {
                                                    return isset( $this->addons[ $dep ] ) ? $this->addons[ $dep ]['name'] : $dep;
                                                }, $addon['dependencies'] );
                                                echo esc_html( implode( ', ', $dep_names ) );
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( ! $deps_check['satisfied'] && $is_installed ) : ?>
                                            <div class="dps-addon-warning">
                                                <span class="dashicons dashicons-warning"></span>
                                                <?php
                                                $missing_names = array_map( function( $dep ) {
                                                    return isset( $this->addons[ $dep ] ) ? $this->addons[ $dep ]['name'] : $dep;
                                                }, $deps_check['missing'] );
                                                printf(
                                                    /* translators: %s: comma-separated list of missing dependencies */
                                                    esc_html__( 'Dependências inativas: %s', 'desi-pet-shower' ),
                                                    esc_html( implode( ', ', $missing_names ) )
                                                );
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ( $is_installed ) : ?>
                                            <div class="dps-addon-individual-actions">
                                                <?php if ( $is_active ) : ?>
                                                    <button type="submit" name="dps_addon_action" value="deactivate_single_<?php echo esc_attr( $addon['slug'] ); ?>" class="button button-small dps-btn-individual dps-btn-deactivate">
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                        <?php esc_html_e( 'Desativar', 'desi-pet-shower' ); ?>
                                                    </button>
                                                <?php else : ?>
                                                    <button type="submit" name="dps_addon_action" value="activate_single_<?php echo esc_attr( $addon['slug'] ); ?>" class="button button-small button-primary dps-btn-individual dps-btn-activate">
                                                        <span class="dashicons dashicons-yes-alt"></span>
                                                        <?php esc_html_e( 'Ativar', 'desi-pet-shower' ); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="dps-addon-actions">
                    <button type="submit" name="dps_addon_action" value="activate" class="button button-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e( 'Ativar Selecionados', 'desi-pet-shower' ); ?>
                    </button>
                    <button type="submit" name="dps_addon_action" value="deactivate" class="button button-secondary">
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e( 'Desativar Selecionados', 'desi-pet-shower' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}

// Inicializa o gerenciador de add-ons após o carregamento do text domain.
add_action( 'init', function() {
    if ( is_admin() ) {
        DPS_Addon_Manager::get_instance();
    }
}, 5 );
