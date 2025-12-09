<?php
/**
 * Plugin Name:       DPS by PRObst – Financeiro Add-on
 * Plugin URI:        https://www.probst.pro
 * Description:       Controle financeiro completo. Registre receitas e despesas, acompanhe pagamentos, visualize gráficos e relatórios.
 * Version:           1.5.0
 * Author:            PRObst
 * Author URI:        https://www.probst.pro
 * Text Domain:       dps-finance-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifica se o plugin base DPS by PRObst está ativo.
 * Se não estiver, exibe aviso e interrompe carregamento do add-on.
 */
function dps_finance_check_base_plugin() {
    if ( ! class_exists( 'DPS_Base_Plugin' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'O add-on Financeiro requer o plugin base DPS by PRObst para funcionar.', 'dps-finance-addon' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', function() {
    if ( ! dps_finance_check_base_plugin() ) {
        return;
    }
}, 1 );

/**
 * Carrega o text domain do Finance Add-on.
 * Usa prioridade 1 para garantir que rode antes da inicialização da classe (prioridade 5).
 */
function dps_finance_load_textdomain() {
    load_plugin_textdomain( 'dps-finance-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'dps_finance_load_textdomain', 1 );

// Define constantes do add-on
if ( ! defined( 'DPS_FINANCE_PLUGIN_FILE' ) ) {
    define( 'DPS_FINANCE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'DPS_FINANCE_PLUGIN_DIR' ) ) {
    define( 'DPS_FINANCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DPS_FINANCE_VERSION' ) ) {
    define( 'DPS_FINANCE_VERSION', '1.5.0' );
}

// Constante para limite de meses no gráfico financeiro
if ( ! defined( 'DPS_FINANCE_CHART_MONTHS' ) ) {
    define( 'DPS_FINANCE_CHART_MONTHS', 6 );
}

// Carrega dependências
require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-revenue-query.php';
require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-api.php';
require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-settings.php';

// Funções auxiliares globais para conversão monetária
// DEPRECATED: Use DPS_Money_Helper do núcleo em vez dessas funções.

if ( ! function_exists( 'dps_parse_money_br' ) ) {
    /**
     * Converte uma string de valor em formato brasileiro para inteiro em centavos.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::parse_brazilian_format() instead.
     * @param string $str Valor monetário (ex.: "129,90" ou "129.90").
     * @return int Valor em centavos (ex.: 12990)
     */
    function dps_parse_money_br( $str ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::parse_brazilian_format()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::parse_brazilian_format( $str );
        }
        // Fallback se helper não disponível
        $raw = trim( (string) $str );
        if ( $raw === '' ) {
            return 0;
        }
        $normalized = preg_replace( '/[^0-9,.-]/', '', $raw );
        $normalized = str_replace( ' ', '', $normalized );
        if ( strpos( $normalized, ',' ) !== false ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }
        $value = floatval( $normalized );
        return (int) round( $value * 100 );
    }
}

if ( ! function_exists( 'dps_format_money_br' ) ) {
    /**
     * Formata um valor em centavos para string no padrão brasileiro.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::format_to_brazilian() instead.
     * @param int $int Valor em centavos (ex.: 12990).
     * @return string Valor formatado (ex.: "129,90").
     */
    function dps_format_money_br( $int ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::format_to_brazilian()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::format_to_brazilian( $int );
        }
        // Fallback se helper não disponível
        $float = (int) $int / 100;
        return number_format( $float, 2, ',', '.' );
    }
}

// Define a classe principal do add-on financeiro
if ( ! class_exists( 'DPS_Finance_Addon' ) ) {

class DPS_Finance_Addon {
    public function __construct() {
        // Registra abas e seções no plugin base
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_finance_tab' ], 10, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_finance_section' ], 10, 1 );
        // Trata salvamento e exclusão de transações
        add_action( 'init', [ $this, 'maybe_handle_finance_actions' ] );
        add_action( 'dps_finance_cleanup_for_appointment', [ $this, 'cleanup_transactions_for_appointment' ] );

        // Não cria mais uma página pública para documentos; apenas registra o shortcode
        add_shortcode( 'dps_fin_docs', [ $this, 'render_fin_docs_shortcode' ] );

        // Sincroniza automaticamente o status das transações quando o status do agendamento é atualizado ou criado
        // Utilize tanto updated_post_meta quanto added_post_meta para capturar atualizações e inserções de meta
        add_action( 'updated_post_meta', [ $this, 'sync_status_to_finance' ], 10, 4 );
        add_action( 'added_post_meta',  [ $this, 'sync_status_to_finance' ], 10, 4 );

        // Registra e enfileira assets do add-on
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers para histórico de parcelas
        add_action( 'wp_ajax_dps_get_partial_history', [ $this, 'ajax_get_partial_history' ] );
        add_action( 'wp_ajax_dps_delete_partial', [ $this, 'ajax_delete_partial' ] );
        
        // F1.1: Endpoint seguro para servir documentos financeiros (FASE 1 - Segurança)
        add_action( 'template_redirect', [ $this, 'serve_finance_document' ] );
    }

    /**
     * Registra e enfileira CSS e JS do add-on financeiro.
     *
     * @since 1.1.0
     */
    public function enqueue_assets() {
        // Enfileira apenas no frontend (shortcode [dps_base])
        if ( ! is_admin() ) {
            // CSS
            wp_enqueue_style(
                'dps-finance-addon',
                plugin_dir_url( DPS_FINANCE_PLUGIN_FILE ) . 'assets/css/finance-addon.css',
                [],
                DPS_FINANCE_VERSION
            );

            // Chart.js via CDN para gráficos financeiros
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );

            // JS
            wp_enqueue_script(
                'dps-finance-addon',
                plugin_dir_url( DPS_FINANCE_PLUGIN_FILE ) . 'assets/js/finance-addon.js',
                [ 'jquery', 'chartjs' ],
                DPS_FINANCE_VERSION,
                true
            );

            // Localização do script
            wp_localize_script( 'dps-finance-addon', 'dpsFinance', [
                'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
                'servicesNonce'      => wp_create_nonce( 'dps_get_services_details' ),
                'deleteNonce'        => wp_create_nonce( 'dps_finance_delete' ),
                'partialHistoryNonce'=> wp_create_nonce( 'dps_partial_history' ),
                'deletePartialNonce' => wp_create_nonce( 'dps_delete_partial' ),
                'i18n'               => [
                    'loading'              => __( 'Carregando...', 'dps-finance-addon' ),
                    'view'                 => __( 'Ver', 'dps-finance-addon' ),
                    'noServices'           => __( 'Nenhum serviço encontrado.', 'dps-finance-addon' ),
                    'error'                => __( 'Erro ao buscar serviços.', 'dps-finance-addon' ),
                    'servicesTitle'        => __( 'Serviços do Atendimento', 'dps-finance-addon' ),
                    'service'              => __( 'Serviço', 'dps-finance-addon' ),
                    'price'                => __( 'Valor', 'dps-finance-addon' ),
                    'total'                => __( 'Total', 'dps-finance-addon' ),
                    'close'                => __( 'Fechar', 'dps-finance-addon' ),
                    'confirmDelete'        => __( 'Tem certeza que deseja excluir esta transação?', 'dps-finance-addon' ),
                    'confirmStatusChange'  => __( 'Tem certeza que deseja alterar o status desta transação já paga?', 'dps-finance-addon' ),
                    'partialHistoryTitle'  => __( 'Histórico de Pagamentos', 'dps-finance-addon' ),
                    'date'                 => __( 'Data', 'dps-finance-addon' ),
                    'value'                => __( 'Valor', 'dps-finance-addon' ),
                    'method'               => __( 'Método', 'dps-finance-addon' ),
                    'actions'              => __( 'Ações', 'dps-finance-addon' ),
                    'delete'               => __( 'Excluir', 'dps-finance-addon' ),
                    'totalPaid'            => __( 'Total Pago', 'dps-finance-addon' ),
                    'remaining'            => __( 'Restante', 'dps-finance-addon' ),
                    'confirmDeletePartial' => __( 'Tem certeza que deseja excluir este pagamento?', 'dps-finance-addon' ),
                    'noPartials'           => __( 'Nenhum pagamento registrado.', 'dps-finance-addon' ),
                    'history'              => __( 'Histórico', 'dps-finance-addon' ),
                    // Validação de formulário
                    'valueRequired'        => __( 'O valor deve ser maior que zero.', 'dps-finance-addon' ),
                    'dateRequired'         => __( 'A data é obrigatória.', 'dps-finance-addon' ),
                    'categoryRequired'     => __( 'A categoria é obrigatória.', 'dps-finance-addon' ),
                ],
            ] );
        }
    }

    /**
     * Executado na ativação do add‑on financeiro.
     * 
     * Este método:
     * 1. Cria as tabelas dps_transacoes e dps_parcelas se não existirem
     * 2. Migra valores de float para centavos (bigint) na v1.2.0
     * 3. Adiciona colunas created_at e updated_at na v1.2.0
     * 4. Garante que exista uma página para listar documentos financeiros
     * 5. É idempotente: pode ser executado múltiplas vezes sem problemas
     */
    public static function activate() {
        global $wpdb;
        
        // Define versão atual do schema
        $current_version = '1.2.0';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        
        // ========== 1. Criar/atualizar tabela dps_transacoes ==========
        $transacoes_table = $wpdb->prefix . 'dps_transacoes';
        $transacoes_version = get_option( 'dps_transacoes_db_version', '0' );
        
        // Criar tabela se não existir (primeira instalação)
        if ( version_compare( $transacoes_version, '1.0.0', '<' ) ) {
            $sql = "CREATE TABLE $transacoes_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                cliente_id bigint(20) DEFAULT NULL,
                agendamento_id bigint(20) DEFAULT NULL,
                plano_id bigint(20) DEFAULT NULL,
                data date DEFAULT NULL,
                valor float DEFAULT 0,
                categoria varchar(255) NOT NULL DEFAULT '',
                tipo varchar(50) NOT NULL DEFAULT '',
                status varchar(20) NOT NULL DEFAULT '',
                descricao text NOT NULL DEFAULT '',
                PRIMARY KEY  (id),
                KEY cliente_id (cliente_id),
                KEY agendamento_id (agendamento_id),
                KEY plano_id (plano_id)
            ) $charset_collate;";
            
            dbDelta( $sql );
            update_option( 'dps_transacoes_db_version', '1.0.0' );
            $transacoes_version = '1.0.0';
        }
        
        // Migração v1.2.0: adicionar colunas created_at e updated_at, converter valor para centavos
        if ( version_compare( $transacoes_version, '1.2.0', '<' ) ) {
            // Adiciona coluna valor_cents se não existir
            $col_valor_cents = $wpdb->get_results( "SHOW COLUMNS FROM $transacoes_table LIKE 'valor_cents'" );
            if ( empty( $col_valor_cents ) ) {
                $wpdb->query( "ALTER TABLE $transacoes_table ADD COLUMN valor_cents BIGINT(20) DEFAULT 0 AFTER valor" );
                // Migra valores existentes de float para centavos
                $wpdb->query( "UPDATE $transacoes_table SET valor_cents = ROUND(valor * 100) WHERE valor_cents = 0 OR valor_cents IS NULL" );
            }
            
            // Adiciona coluna created_at se não existir
            $col_created = $wpdb->get_results( "SHOW COLUMNS FROM $transacoes_table LIKE 'created_at'" );
            if ( empty( $col_created ) ) {
                $wpdb->query( "ALTER TABLE $transacoes_table ADD COLUMN created_at DATETIME DEFAULT NULL" );
                // Define created_at = data para registros existentes
                $wpdb->query( "UPDATE $transacoes_table SET created_at = CONCAT(data, ' 00:00:00') WHERE created_at IS NULL AND data IS NOT NULL" );
            }
            
            // Adiciona coluna updated_at se não existir
            $col_updated = $wpdb->get_results( "SHOW COLUMNS FROM $transacoes_table LIKE 'updated_at'" );
            if ( empty( $col_updated ) ) {
                $wpdb->query( "ALTER TABLE $transacoes_table ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP" );
            }
            
            update_option( 'dps_transacoes_db_version', '1.2.0' );
            $transacoes_version = '1.2.0';
        }
        
        // F1.3: FASE 1 - Performance: Adicionar índices otimizados (v1.3.1)
        if ( version_compare( $transacoes_version, '1.3.1', '<' ) ) {
            // Verifica e adiciona índices se não existirem
            $indexes = $wpdb->get_results( "SHOW INDEX FROM $transacoes_table" );
            $existing_indexes = array_column( $indexes, 'Key_name' );
            
            // Índice composto em data + status (queries de filtro mais comuns)
            if ( ! in_array( 'idx_finance_date_status', $existing_indexes, true ) ) {
                $wpdb->query( "CREATE INDEX idx_finance_date_status ON $transacoes_table(data, status)" );
            }
            
            // Índice em categoria (filtros por categoria)
            if ( ! in_array( 'idx_finance_categoria', $existing_indexes, true ) ) {
                $wpdb->query( "CREATE INDEX idx_finance_categoria ON $transacoes_table(categoria)" );
            }
            
            // Nota: cliente_id, agendamento_id e plano_id já possuem índices (KEY) da v1.0.0
            
            update_option( 'dps_transacoes_db_version', '1.3.1' );
        }
        
        // ========== 2. Criar/atualizar tabela dps_parcelas ==========
        $parcelas_table = $wpdb->prefix . 'dps_parcelas';
        $parcelas_version = get_option( 'dps_parcelas_db_version', '0' );
        
        // Criar tabela se não existir (primeira instalação)
        if ( version_compare( $parcelas_version, '1.0.0', '<' ) ) {
            $sql = "CREATE TABLE $parcelas_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                trans_id bigint(20) NOT NULL,
                data date NOT NULL,
                valor float NOT NULL,
                metodo varchar(50) DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY trans_id (trans_id)
            ) $charset_collate;";
            
            dbDelta( $sql );
            update_option( 'dps_parcelas_db_version', '1.0.0' );
            $parcelas_version = '1.0.0';
        }
        
        // Migração v1.2.0: adicionar coluna valor_cents e created_at
        if ( version_compare( $parcelas_version, '1.2.0', '<' ) ) {
            // Adiciona coluna valor_cents se não existir
            $col_valor_cents = $wpdb->get_results( "SHOW COLUMNS FROM $parcelas_table LIKE 'valor_cents'" );
            if ( empty( $col_valor_cents ) ) {
                $wpdb->query( "ALTER TABLE $parcelas_table ADD COLUMN valor_cents BIGINT(20) DEFAULT 0 AFTER valor" );
                // Migra valores existentes de float para centavos
                $wpdb->query( "UPDATE $parcelas_table SET valor_cents = ROUND(valor * 100) WHERE valor_cents = 0 OR valor_cents IS NULL" );
            }
            
            // Adiciona coluna created_at se não existir
            $col_created = $wpdb->get_results( "SHOW COLUMNS FROM $parcelas_table LIKE 'created_at'" );
            if ( empty( $col_created ) ) {
                $wpdb->query( "ALTER TABLE $parcelas_table ADD COLUMN created_at DATETIME DEFAULT NULL" );
                // Define created_at = data para registros existentes
                $wpdb->query( "UPDATE $parcelas_table SET created_at = CONCAT(data, ' 00:00:00') WHERE created_at IS NULL AND data IS NOT NULL" );
            }
            
            update_option( 'dps_parcelas_db_version', '1.2.0' );
            $parcelas_version = '1.2.0';
        }
        
        // F1.3: FASE 1 - Performance: Verificar índice em trans_id (v1.3.1)
        if ( version_compare( $parcelas_version, '1.3.1', '<' ) ) {
            // O índice trans_id já existe da v1.0.0 (KEY trans_id (trans_id))
            // Apenas atualizamos a versão para manter consistência
            update_option( 'dps_parcelas_db_version', '1.3.1' );
        }
        
        // F1.1: FASE 1 - Segurança: Proteger diretório de documentos financeiros
        $upload_dir = wp_upload_dir();
        $doc_dir    = trailingslashit( $upload_dir['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $doc_dir ) ) {
            wp_mkdir_p( $doc_dir );
        }
        
        // Criar .htaccess para bloquear acesso direto aos documentos
        $htaccess_file = trailingslashit( $doc_dir ) . '.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "# DPS Finance Add-on - Proteção de Documentos\n";
            $htaccess_content .= "# FASE 1 - F1.1: Bloqueia acesso direto a documentos financeiros\n";
            $htaccess_content .= "<Files \"*\">\n";
            $htaccess_content .= "    Require all denied\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents( $htaccess_file, $htaccess_content );
        }
        
        // ========== 3. Criar página de Documentos Financeiros ==========
        $page_id = get_option( 'dps_fin_docs_page_id' );
        
        // Verifica se a página existe (pode ter sido excluída)
        if ( $page_id ) {
            $page = get_post( $page_id );
            if ( ! $page || $page->post_status === 'trash' ) {
                $page_id = false;
            }
        }
        
        // Se não existe ou foi excluída, cria uma nova
        if ( ! $page_id ) {
            $title = __( 'Documentos Financeiros', 'dps-finance-addon' );
            $slug  = 'dps-documentos-financeiros';
            
            // Verifica se já existe uma página com este slug
            $page = get_page_by_path( $slug );
            
            if ( ! $page ) {
                $new_page_id = wp_insert_post( [
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => '[dps_fin_docs]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ] );
                
                if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
                    update_option( 'dps_fin_docs_page_id', $new_page_id );
                }
            } else {
                // Página já existe: atualiza option e garante que tenha o shortcode
                update_option( 'dps_fin_docs_page_id', $page->ID );
                
                // BUGFIX: Verifica se o conteúdo da página contém o shortcode
                // Se não contiver, adiciona ao final do conteúdo existente (ou cria conteúdo se vazio)
                if ( ! has_shortcode( $page->post_content, 'dps_fin_docs' ) ) {
                    $new_content = $page->post_content ? $page->post_content . "\n\n[dps_fin_docs]" : '[dps_fin_docs]';
                    wp_update_post( [
                        'ID'           => $page->ID,
                        'post_content' => $new_content,
                    ] );
                }
            }
        }
    }

    /**
     * Obtém a URL atual com fallback para evitar warnings do PHP 8+.
     * 
     * get_permalink() pode retornar false em alguns contextos (ex: ações admin sem post atual).
     * Esta função garante que sempre retornamos uma string válida.
     *
     * @since 1.3.1
     * @return string URL atual ou home_url() como fallback.
     */
    private function get_current_url() {
        $url = get_permalink();
        
        // get_permalink() pode retornar false - use fallback
        if ( false === $url || empty( $url ) ) {
            // Tenta construir URL a partir do REQUEST_URI
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
                if ( ! empty( $request_uri ) ) {
                    return esc_url_raw( home_url( $request_uri ) );
                }
            }
            // Último fallback: home_url
            return home_url();
        }
        
        return $url;
    }

    /**
     * Adiciona a aba Financeiro na navegação do plugin base.
     *
     * @param bool $visitor_only Se o modo visitante está ativo; nesse caso, não mostra a aba.
     */
    public function add_finance_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="financeiro">' . esc_html__( 'Financeiro', 'dps-finance-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de controle financeiro ao plugin base.
     *
     * @param bool $visitor_only Se verdadeiro, visitante não vê a seção.
     */
    public function add_finance_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo $this->section_financeiro();
    }

    /**
     * Manipula ações de salvamento ou exclusão de transações.
     * 
     * SEGURANÇA: Revisado em 2025-11-23
     * - Adicionadas verificações de capability (manage_options)
     * - Sanitização consistente com wp_unslash()
     * - Queries SQL usando $wpdb->prepare()
     */
    public function maybe_handle_finance_actions() {
        // Sempre declara o objeto $wpdb e a tabela de transações no início, pois são usados
        // tanto no registro de pagamentos parciais quanto em outras ações. Sem isso,
        // variáveis como $table e $wpdb podem não estar definidas quando utilizadas.
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Exportação CSV - processa antes das outras ações
        if ( isset( $_GET['dps_fin_export'] ) && '1' === $_GET['dps_fin_export'] ) {
            $this->export_transactions_csv();
            exit;
        }
        
        // F2.2: FASE 2 - Handler para reenvio de link de pagamento
        if ( isset( $_GET['dps_resend_payment_link'] ) && isset( $_GET['trans_id'] ) ) {
            $trans_id = intval( $_GET['trans_id'] );
            
            // Valida nonce
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_resend_link_' . $trans_id ) ) {
                wp_die( esc_html__( 'Link de segurança inválido.', 'dps-finance-addon' ) );
            }
            
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para esta ação.', 'dps-finance-addon' ) );
            }
            
            // Busca transação e agendamento
            $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $trans_id ) );
            
            if ( ! $trans || ! $trans->agendamento_id ) {
                wp_die( esc_html__( 'Transação ou agendamento não encontrado.', 'dps-finance-addon' ) );
            }
            
            // Busca link de pagamento
            $payment_link = get_post_meta( $trans->agendamento_id, 'dps_payment_link', true );
            
            if ( ! $payment_link ) {
                $base_url = $this->get_current_url();
                wp_redirect( add_query_arg( [
                    'tab' => 'financeiro',
                    'dps_msg' => 'no_payment_link',
                ], $base_url ) );
                exit;
            }
            
            // Busca dados do cliente
            $client_name = '';
            $phone = '';
            if ( $trans->cliente_id ) {
                $client = get_post( $trans->cliente_id );
                if ( $client ) {
                    $client_name = $client->post_title;
                    $phone = get_post_meta( $trans->cliente_id, 'client_phone', true );
                }
            }
            
            // Busca nome do pet
            $pet_name = '';
            $pet_id = get_post_meta( $trans->agendamento_id, 'appointment_pet_id', true );
            if ( $pet_id ) {
                $pet = get_post( $pet_id );
                if ( $pet ) {
                    $pet_name = $pet->post_title;
                }
            }
            
            // Prepara mensagem para WhatsApp com link
            if ( $phone ) {
                $digits = preg_replace( '/\D+/', '', $phone );
                if ( strlen( $digits ) == 10 || strlen( $digits ) == 11 ) {
                    $digits = '55' . $digits;
                }
                
                $valor_str = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans->valor * 100 ) );
                
                $msg = sprintf(
                    __( 'Olá %1$s! Segue o link para pagamento do atendimento do %2$s (R$ %3$s): %4$s', 'dps-finance-addon' ),
                    $client_name,
                    $pet_name,
                    $valor_str,
                    $payment_link
                );
                
                $wa_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                
                // Registra log de reenvio
                update_post_meta( $trans->agendamento_id, '_dps_payment_link_resent_at', current_time( 'mysql' ) );
                update_post_meta( $trans->agendamento_id, '_dps_payment_link_resent_by', get_current_user_id() );
                
                wp_redirect( $wa_link );
                exit;
            } else {
                $base_url = $this->get_current_url();
                wp_redirect( add_query_arg( [
                    'tab' => 'financeiro',
                    'dps_msg' => 'no_phone',
                ], $base_url ) );
                exit;
            }
        }

        // Registrar pagamento parcial
        if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_partial' && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            
            $trans_id = isset( $_POST['trans_id'] ) ? intval( $_POST['trans_id'] ) : 0;
            $date     = isset( $_POST['partial_date'] ) ? sanitize_text_field( wp_unslash( $_POST['partial_date'] ) ) : '';
            if ( ! $date ) {
                $date = current_time( 'Y-m-d' );
            }
            // Converte valor informado em centavos para evitar imprecisão de ponto flutuante
            $raw_value   = isset( $_POST['partial_value'] ) ? sanitize_text_field( wp_unslash( $_POST['partial_value'] ) ) : '0';
            $value_cents = DPS_Money_Helper::parse_brazilian_format( $raw_value );
            $value       = $value_cents / 100;
            $method    = isset( $_POST['partial_method'] ) ? sanitize_text_field( wp_unslash( $_POST['partial_method'] ) ) : '';
            
            if ( $trans_id && $value > 0 ) {
                $parc_table = $wpdb->prefix . 'dps_parcelas';
                
                // F1.2: FASE 1 - Validação: Impedir que soma de parciais ultrapasse o total
                // Busca valor total da transação
                $total_val = $wpdb->get_var( $wpdb->prepare( "SELECT valor FROM {$table} WHERE id = %d", $trans_id ) );
                $total_val_cents = $total_val ? (int) round( (float) $total_val * 100 ) : 0;
                
                // Soma parcelas já registradas
                $paid_sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$parc_table} WHERE trans_id = %d", $trans_id ) );
                $paid_sum_cents = $paid_sum ? (int) round( (float) $paid_sum * 100 ) : 0;
                
                // Valida se a nova parcela não ultrapassa o total (tolerância de R$ 0,01 para arredondamentos)
                $new_total_cents = $paid_sum_cents + $value_cents;
                if ( $new_total_cents > ( $total_val_cents + 1 ) ) { // +1 centavo de tolerância
                    $base_url = $this->get_current_url();
                    wp_redirect( add_query_arg( [
                        'tab' => 'financeiro',
                        'dps_msg' => 'partial_exceeds_total',
                        'trans_id' => $trans_id,
                        'total' => DPS_Money_Helper::format_to_brazilian( $total_val_cents ),
                        'paid' => DPS_Money_Helper::format_to_brazilian( $paid_sum_cents ),
                        'attempted' => DPS_Money_Helper::format_to_brazilian( $value_cents ),
                    ], $base_url ) );
                    exit;
                }
                
                // Insere a parcela
                $wpdb->insert( $parc_table, [
                    'trans_id' => $trans_id,
                    'data'     => $date,
                    'valor'    => $value,
                    'metodo'   => $method,
                ], [ '%d','%s','%f','%s' ] );
                // Calcula a soma de parcelas pagas
                $paid_sum       = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$parc_table} WHERE trans_id = %d", $trans_id ) );
                $paid_sum_cents = $paid_sum ? (int) round( (float) $paid_sum * 100 ) : 0;
                // Valor total da transação
                $total_val       = $wpdb->get_var( $wpdb->prepare( "SELECT valor FROM {$table} WHERE id = %d", $trans_id ) );
                $total_val_cents = $total_val ? (int) round( (float) $total_val * 100 ) : 0;
                if ( $paid_sum_cents && $total_val_cents && $paid_sum_cents >= $total_val_cents ) {
                    // Marca a transação como paga quando quitada
                    $wpdb->update( $table, [ 'status' => 'pago' ], [ 'id' => $trans_id ], [ '%s' ], [ '%d' ] );
                } else {
                    // Mantém como em aberto até pagamento total
                    $wpdb->update( $table, [ 'status' => 'em_aberto' ], [ 'id' => $trans_id ], [ '%s' ], [ '%d' ] );
                }
            }
            // Redireciona de volta à aba Financeiro com feedback
            $base_url = $this->get_current_url();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro', 'dps_msg' => 'partial_saved' ], $base_url ) );
            exit;
        }

        // Geração de documento (nota ou cobrança) a partir da lista de transações.
        // SEGURANÇA: Adicionada verificação de nonce
        if ( isset( $_GET['dps_gen_doc'] ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            // Verifica nonce
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_finance_doc_' . intval( $_GET['id'] ) ) ) {
                wp_die( esc_html__( 'Ação de segurança inválida.', 'dps-finance-addon' ) );
            }
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            
            $trans_id = intval( $_GET['id'] );
            $doc_url  = $this->generate_document( $trans_id );
            if ( $doc_url ) {
                wp_redirect( $doc_url );
                exit;
            }
        }
        // Salvar nova transação
        if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_trans' && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            
            $date       = isset( $_POST['finance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_date'] ) ) : '';
            $value_raw  = isset( $_POST['finance_value'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_value'] ) ) : '0';
            $value_cent = DPS_Money_Helper::parse_brazilian_format( $value_raw );
            $value      = $value_cent / 100;
            $category   = isset( $_POST['finance_category'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_category'] ) ) : '';
            $type       = isset( $_POST['finance_type'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_type'] ) ) : 'receita';
            $status     = isset( $_POST['finance_status'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_status'] ) ) : 'em_aberto';
            $desc       = isset( $_POST['finance_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['finance_desc'] ) ) : '';
            $client_id  = isset( $_POST['finance_client_id'] ) ? intval( $_POST['finance_client_id'] ) : 0;
            // Insere no banco
            $wpdb->insert( $table, [
                'cliente_id'    => $client_id ?: null,
                'agendamento_id'=> null,
                'plano_id'      => null,
                'data'          => $date ?: current_time( 'mysql' ),
                'valor'         => $value,
                'categoria'     => $category,
                'tipo'          => $type,
                'status'        => $status,
                'descricao'     => $desc,
            ] );
            // Redireciona para aba finance com feedback
            $base_url = $this->get_current_url();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro', 'dps_msg' => 'saved' ], $base_url ) );
            exit;
        }
        // Excluir transação - SEGURANÇA: Adicionada verificação de nonce
        if ( isset( $_GET['dps_delete_trans'] ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            // Verifica nonce
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_finance_delete_' . intval( $_GET['id'] ) ) ) {
                wp_die( esc_html__( 'Ação de segurança inválida.', 'dps-finance-addon' ) );
            }
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            
            $trans_id = intval( $_GET['id'] );
            $wpdb->delete( $table, [ 'id' => $trans_id ], [ '%d' ] );
            $base_url = $this->get_current_url();
            $redir = remove_query_arg( [ 'dps_delete_trans', 'id', '_wpnonce' ], $base_url );
            $redir = add_query_arg( [ 'tab' => 'financeiro', 'dps_msg' => 'deleted' ], $redir );
            wp_redirect( $redir );
            exit;
        }
        // Atualizar status de transação
        if ( isset( $_POST['dps_update_trans_status'] ) && isset( $_POST['trans_id'] ) ) {
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            
            $id     = intval( $_POST['trans_id'] );
            $status = isset( $_POST['trans_status'] ) ? sanitize_text_field( wp_unslash( $_POST['trans_status'] ) ) : 'em_aberto';
            $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );

            // Se for marcado como recorrente e status foi alterado para pago, cria nova transação recorrente para 30 dias depois
            $recurring_flag = get_option( 'dps_fin_recurring_' . $id );
            if ( $recurring_flag && $status === 'pago' ) {
                // Recupera dados da transação
                $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
                if ( $trans ) {
                    // Calcula nova data somando 30 dias
                    $new_date = date( 'Y-m-d', strtotime( $trans->data . ' +30 days' ) );
                    $wpdb->insert( $table, [
                        'cliente_id'     => $trans->cliente_id,
                        'agendamento_id' => $trans->agendamento_id,
                        'plano_id'       => $trans->plano_id,
                        'data'           => $new_date,
                        'valor'          => $trans->valor,
                        'categoria'      => $trans->categoria,
                        'tipo'           => $trans->tipo,
                        'status'         => 'em_aberto',
                        'descricao'      => $trans->descricao,
                    ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
                    // Marca nova transação como recorrente também
                    $new_id = $wpdb->insert_id;
                    update_option( 'dps_fin_recurring_' . $new_id, true );
                }
            }
            // Se esta transação estiver vinculada a um agendamento, atualiza o status correspondente
            $appt_id = $wpdb->get_var( $wpdb->prepare( "SELECT agendamento_id FROM $table WHERE id = %d", $id ) );
            $appt_id = intval( $appt_id );
            if ( $appt_id ) {
                if ( $status === 'pago' ) {
                    $appt_status = 'finalizado_pago';
                } elseif ( $status === 'cancelado' ) {
                    $appt_status = 'cancelado';
                } else {
                    $appt_status = 'finalizado';
                }
                delete_post_meta( $appt_id, 'appointment_status' );
                add_post_meta( $appt_id, 'appointment_status', $appt_status, true );
            }
            $base_url = $this->get_current_url();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro', 'dps_msg' => 'status_updated' ], $base_url ) );
            exit;
        }

        // Alternar recorrência removido: funcionalidade de recorrente foi descontinuada

        /**
         * Envio ou exclusão de documentos financeiros
         *
         * Para enviar um documento: usar dps_send_doc=1, file={nome do arquivo}, _wpnonce
         * Opcional: to_email={email personalizado} e trans_id={id da transação}
         *
         * Para excluir um documento: usar dps_delete_doc=1, file={nome do arquivo}, _wpnonce
         * 
         * SEGURANÇA: Adicionada verificação de nonce em 2025-12-06
         */
        // Excluir documento
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) && isset( $_GET['_wpnonce'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            
            // Verifica nonce
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_delete_doc_' . $file ) ) {
                wp_die( esc_html__( 'Ação de segurança inválida.', 'dps-finance-addon' ) );
            }
            
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            if ( $file ) {
                $uploads = wp_upload_dir();
                $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
                $file_path = trailingslashit( $doc_dir ) . basename( $file );
                if ( file_exists( $file_path ) ) {
                    @unlink( $file_path );
                }
                // Também remove qualquer opção que referencie este arquivo (transações)
                $file_url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $file );
                // Remove dps_fin_doc_* que contenham esta URL
                $option_rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    'dps_fin_doc_%'
                ) );
                if ( $option_rows ) {
                    foreach ( $option_rows as $opt ) {
                        $val = get_option( $opt->option_name );
                        if ( $val === $file_url ) {
                            delete_option( $opt->option_name );
                        }
                    }
                }
                // Remove também a opção de email default associada a este transação, se existir
                if ( isset( $_GET['trans_id'] ) ) {
                    $tid = intval( $_GET['trans_id'] );
                    delete_option( 'dps_fin_doc_email_' . $tid );
                }
            }
            // Redireciona de volta à aba Financeiro
            $base_url = $this->get_current_url();
            $redir = add_query_arg( [ 'tab' => 'financeiro' ], remove_query_arg( [ 'dps_delete_doc', 'file', 'to_email', 'trans_id', '_wpnonce' ], $base_url ) );
            wp_redirect( $redir );
            exit;
        }

        // Enviar documento por email
        if ( isset( $_GET['dps_send_doc'] ) && '1' === $_GET['dps_send_doc'] && isset( $_GET['file'] ) && isset( $_GET['_wpnonce'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            
            // Verifica nonce
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_send_doc_' . $file ) ) {
                wp_die( esc_html__( 'Ação de segurança inválida.', 'dps-finance-addon' ) );
            }
            
            // Verifica permissão
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Você não tem permissão para acessar esta funcionalidade.', 'dps-finance-addon' ) );
            }
            $to_email = '';
            if ( isset( $_GET['to_email'] ) ) {
                $to_email = sanitize_email( wp_unslash( $_GET['to_email'] ) );
            }
            $trans_id = isset( $_GET['trans_id'] ) ? intval( $_GET['trans_id'] ) : 0;
            $this->send_finance_doc_email( $file, $trans_id, $to_email );
            // Redireciona de volta à aba Financeiro
            $base_url = $this->get_current_url();
            $redir = add_query_arg( [ 'tab' => 'financeiro' ], remove_query_arg( [ 'dps_send_doc', 'file', 'to_email', 'trans_id', '_wpnonce' ], $base_url ) );
            wp_redirect( $redir );
            exit;
        }
    }

    /**
     * Remove transações associadas a um agendamento excluído.
     *
     * @param int $appointment_id ID do agendamento removido.
     */
    public function cleanup_transactions_for_appointment( $appointment_id ) {
        // Delega para a API financeira
        if ( class_exists( 'DPS_Finance_API' ) ) {
            DPS_Finance_API::delete_charges_by_appointment( $appointment_id );
        }
    }

    /**
     * Gera um documento simples (HTML) para uma transação financeira. O documento é uma "nota" se o
     * status estiver marcado como pago, ou uma "cobrança" se o status for em aberto. É salvo em
     * uploads/dps_docs e reutilizado em requisições futuras. Retorna a URL pública do arquivo.
     *
     * @param int $trans_id ID da transação
     * @return string|false URL do arquivo gerado ou false em caso de erro
     */
    private function generate_document( $trans_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $trans_id ) );
        if ( ! $trans ) {
            return false;
        }
        // Determina tipo de documento (nota = pago, cobranca = em aberto)
        $status = $trans->status;
        $type   = ( $status === 'pago' ) ? 'nota' : 'cobranca';
        // Verifica se já existe um documento salvo
        $opt_key = 'dps_fin_doc_' . $trans_id;
        $existing = get_option( $opt_key );
        if ( $existing ) {
            return $existing;
        }
        // Datas e valores formatados
        // Datas no formato dia-mês-ano
        $date     = $trans->data ? date_i18n( 'd-m-Y', strtotime( $trans->data ) ) : current_time( 'd-m-Y' );
        $date_key = $trans->data ? date( 'Y-m-d', strtotime( $trans->data ) ) : date( 'Y-m-d' );
        $valor_fmt = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans->valor * 100 ) );
        // Cliente e pet
        $client_name = '';
        $client_email = '';
        $pet_name    = '';
        $appt_id     = 0;
        $client_id_for_email = 0;
        if ( $trans->agendamento_id ) {
            $appt_id   = (int) $trans->agendamento_id;
            $client_id = get_post_meta( $appt_id, 'appointment_client_id', true );
            $pet_id    = get_post_meta( $appt_id, 'appointment_pet_id', true );
            if ( $client_id ) {
                $cpost = get_post( $client_id );
                if ( $cpost ) {
                    $client_name = $cpost->post_title;
                    $client_id_for_email = $client_id;
                }
            }
            if ( $pet_id ) {
                $ppost = get_post( $pet_id );
                if ( $ppost ) {
                    $pet_name = $ppost->post_title;
                }
            }
        } elseif ( $trans->plano_id ) {
            // Se for transação de assinatura, obtém pet e cliente pelo plano
            $client_id = get_post_meta( $trans->plano_id, 'subscription_client_id', true );
            $pet_id    = get_post_meta( $trans->plano_id, 'subscription_pet_id', true );
            if ( $client_id ) {
                $cpost = get_post( $client_id );
                if ( $cpost ) {
                    $client_name = $cpost->post_title;
                    $client_id_for_email = $client_id;
                }
            }
            if ( $pet_id ) {
                $ppost = get_post( $pet_id );
                if ( $ppost ) {
                    $pet_name = $ppost->post_title;
                }
            }
        } elseif ( $trans->cliente_id ) {
            $cpost = get_post( $trans->cliente_id );
            if ( $cpost ) {
                $client_name = $cpost->post_title;
                $client_id_for_email = $trans->cliente_id;
            }
        }
        // Obtém email do cliente (se houver)
        if ( $client_id_for_email ) {
            $client_email = get_post_meta( $client_id_for_email, 'client_email', true );
        }
        // Lista de serviços
        $service_lines = [];
        if ( $appt_id ) {
            $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
            $service_prices = get_post_meta( $appt_id, 'appointment_service_prices', true );
            if ( ! is_array( $service_prices ) ) {
                $service_prices = [];
            }
            if ( is_array( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $srv  = get_post( $sid );
                    if ( $srv ) {
                        $price = 0;
                        if ( isset( $service_prices[ $sid ] ) ) {
                            $price = (float) $service_prices[ $sid ];
                        } else {
                            $price = (float) get_post_meta( $sid, 'service_price', true );
                        }
                        $service_lines[] = esc_html( $srv->post_title ) . ' - R$ ' . DPS_Money_Helper::format_to_brazilian( (int) round( $price * 100 ) );
                    }
                }
            }
        }
        // Preparar HTML do documento
        $title_doc = ( $type === 'nota' ) ? __( 'Nota de Serviços', 'dps-finance-addon' ) : __( 'Cobrança de Serviços', 'dps-finance-addon' );
        $services_html = '';
        if ( ! empty( $service_lines ) ) {
            $services_html .= '<ul style="margin:0; padding-left:20px;">';
            foreach ( $service_lines as $line ) {
                $services_html .= '<li>' . $line . '</li>';
            }
            $services_html .= '</ul>';
        }
        // Dados da loja via configurações
        $store_name    = DPS_Finance_Settings::get( 'store_name' );
        $store_address = DPS_Finance_Settings::get( 'store_address' );
        $store_phone   = DPS_Finance_Settings::get( 'store_phone' );
        $store_email   = DPS_Finance_Settings::get( 'store_email' );
        $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $title_doc ) . '</title></head><body style="font-family:Arial, sans-serif; padding:20px;">';
        // Logo: tenta obter logo personalizado do site (personalização do tema). Se não houver, nada é exibido.
        $logo_id  = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $logo_url ) {
                $html .= '<p style="text-align:center;"><img src="' . esc_url( $logo_url ) . '" alt="Logo" style="max-width:200px;height:auto;"></p>';
            }
        }
        $html .= '<h2 style="text-align:center;">' . esc_html( $store_name ) . '</h2>';
        $html .= '<p style="text-align:center;">' . esc_html( $store_address ) . '<br>' . esc_html( $store_phone ) . ' - ' . esc_html( $store_email ) . '</p>';
        $html .= '<hr style="margin:20px 0;">';
        $html .= '<h3>' . esc_html( $title_doc ) . '</h3>';
        $html .= '<p><strong>' . __( 'Data:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $date ) . '</p>';
        if ( $client_name ) {
            $html .= '<p><strong>' . __( 'Cliente:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $client_name ) . '</p>';
        }
        if ( $pet_name ) {
            $html .= '<p><strong>' . __( 'Pet:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $pet_name ) . '</p>';
        }
        if ( $services_html ) {
            $html .= '<p><strong>' . __( 'Serviços:', 'dps-finance-addon' ) . '</strong> ' . $services_html . '</p>';
        }
        $html .= '<p><strong>' . __( 'Valor total:', 'dps-finance-addon' ) . '</strong> R$ ' . esc_html( $valor_fmt ) . '</p>';
        if ( $type === 'cobranca' ) {
            $html .= '<p>' . __( 'Status:', 'dps-finance-addon' ) . ' ' . __( 'Em aberto', 'dps-finance-addon' ) . '</p>';
            $html .= '<p>' . __( 'Por favor, efetue o pagamento até a data do próximo atendimento.', 'dps-finance-addon' ) . '</p>';
        } else {
            $html .= '<p>' . __( 'Status:', 'dps-finance-addon' ) . ' ' . __( 'Pago', 'dps-finance-addon' ) . '</p>';
            $html .= '<p>' . __( 'Obrigado pela sua preferência!', 'dps-finance-addon' ) . '</p>';
        }
        $html .= '</body></html>';
        // Salvar arquivo usando padrão NomeCliente_NomePet_Data
        $upload_dir = wp_upload_dir();
        $doc_dir    = trailingslashit( $upload_dir['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $doc_dir ) ) {
            wp_mkdir_p( $doc_dir );
        }
        // Cria slug do cliente e do pet
        $client_slug = $client_name ? str_replace( '-', '_', sanitize_title( $client_name ) ) : 'cliente';
        $pet_slug    = $pet_name ? str_replace( '-', '_', sanitize_title( $pet_name ) ) : 'pet';
        // Prefixo capitalizado
        $prefix = ( $type === 'nota' ) ? 'Nota' : 'Cobranca';
        $filename  = $prefix . '_' . $client_slug . '_' . $pet_slug . '_' . $date_key . '.html';
        $file_path = trailingslashit( $doc_dir ) . $filename;
        file_put_contents( $file_path, $html );
        
        // F1.1: FASE 1 - Segurança: Não expor URL pública direta do arquivo
        // Armazena caminho do arquivo (interno) para servir via endpoint seguro
        update_option( 'dps_fin_doc_path_' . $trans_id, $file_path );
        
        // Cria URL segura com nonce para visualização
        $doc_url = wp_nonce_url(
            add_query_arg( [ 'dps_view_doc' => $trans_id ], home_url() ),
            'dps_view_doc_' . $trans_id
        );
        
        // Armazena URL segura para reutilização futura (compatibilidade backward)
        update_option( $opt_key, $doc_url );
        // Armazena email padrão para envio posterior
        if ( $client_email && is_email( $client_email ) ) {
            update_option( 'dps_fin_doc_email_' . $trans_id, sanitize_email( $client_email ) );
        }
        return $doc_url;
    }
    
    /**
     * Serve documento financeiro de forma segura via endpoint autenticado.
     * 
     * FASE 1 - F1.1: Implementado para evitar exposição de dados sensíveis via URL direta.
     * 
     * Este método:
     * - Valida nonce para evitar CSRF
     * - Verifica capability manage_options (apenas administradores)
     * - Serve o arquivo HTML sem expor URL pública
     * - Mantém compatibilidade com documentos já gerados
     * 
     * @since 1.3.1 (FASE 1)
     */
    public function serve_finance_document() {
        // Verifica se é uma requisição de documento
        if ( ! isset( $_GET['dps_view_doc'] ) ) {
            return;
        }
        
        $doc_id = intval( $_GET['dps_view_doc'] );
        
        // Valida nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_view_doc_' . $doc_id ) ) {
            wp_die(
                esc_html__( 'Link de segurança inválido ou expirado. Por favor, gere o documento novamente.', 'dps-finance-addon' ),
                esc_html__( 'Acesso negado', 'dps-finance-addon' ),
                [ 'response' => 403 ]
            );
        }
        
        // Verifica permissão de acesso
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'Você não tem permissão para visualizar documentos financeiros.', 'dps-finance-addon' ),
                esc_html__( 'Acesso negado', 'dps-finance-addon' ),
                [ 'response' => 403 ]
            );
        }
        
        // Busca caminho do arquivo
        $file_path = get_option( 'dps_fin_doc_path_' . $doc_id );
        
        // Se não encontrar caminho salvo, tenta compatibilidade backward com URL antiga
        if ( ! $file_path ) {
            // Tenta reconstruir caminho a partir de URL antiga (se existir)
            $old_url = get_option( 'dps_fin_doc_' . $doc_id );
            if ( $old_url ) {
                $upload_dir = wp_upload_dir();
                $file_path = str_replace(
                    trailingslashit( $upload_dir['baseurl'] ) . 'dps_docs/',
                    trailingslashit( $upload_dir['basedir'] ) . 'dps_docs/',
                    $old_url
                );
            }
        }
        
        // Valida existência do arquivo
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            wp_die(
                esc_html__( 'Documento não encontrado. Pode ter sido excluído ou nunca foi gerado.', 'dps-finance-addon' ),
                esc_html__( 'Documento não encontrado', 'dps-finance-addon' ),
                [ 'response' => 404 ]
            );
        }
        
        // Serve o arquivo HTML
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' ); // Evita indexação
        readfile( $file_path );
        exit;
    }

    /**
     * Envia um documento financeiro (nota/cobrança/histórico) por email.
     *
     * @param string $filename Nome do arquivo no diretório dps_docs
     * @param int    $trans_id ID da transação (opcional, usado para obter email padrão)
     * @param string $custom_email Email alternativo fornecido pelo usuário
     */
    private function send_finance_doc_email( $filename, $trans_id = 0, $custom_email = '' ) {
        if ( ! $filename ) {
            return;
        }
        $uploads = wp_upload_dir();
        $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $file_path = trailingslashit( $doc_dir ) . basename( $filename );
        if ( ! file_exists( $file_path ) ) {
            return;
        }
        // Determina email padrão se trans_id for fornecido
        $default_email = '';
        if ( $trans_id ) {
            $opt_email = get_option( 'dps_fin_doc_email_' . $trans_id );
            if ( $opt_email && is_email( $opt_email ) ) {
                $default_email = $opt_email;
            }
        }
        $to = '';
        if ( $custom_email && is_email( $custom_email ) ) {
            $to = $custom_email;
        } elseif ( $default_email ) {
            $to = $default_email;
        } else {
            // fallback para email de administrador
            $to = get_option( 'admin_email' );
        }
        if ( ! $to ) {
            return;
        }
        // Lê o conteúdo do arquivo para o corpo do email
        $content = file_get_contents( $file_path );
        // Determina título amigável
        $subject = 'Documento Financeiro - ' . get_bloginfo( 'name' );
        // Monta mensagem com saudação e corpo
        $message = '<p>Olá,</p>';
        $message .= '<p>Segue em anexo o documento solicitado:</p>';
        if ( $content ) {
            $message .= '<div style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">' . $content . '</div>';
        } else {
            $url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $filename );
            $message .= '<p><a href="' . esc_url( $url ) . '">Clique aqui para visualizar o documento</a></p>';
        }
        // Rodapé com dados da loja via configurações
        $store_name    = DPS_Finance_Settings::get( 'store_name' );
        $store_address = DPS_Finance_Settings::get( 'store_address' );
        $store_phone   = DPS_Finance_Settings::get( 'store_phone' );
        $store_email   = DPS_Finance_Settings::get( 'store_email' );
        $message .= '<p>Atenciosamente,<br>' . esc_html( $store_name ) . '<br>' . esc_html( $store_address ) . '<br>Whatsapp: ' . esc_html( $store_phone ) . '<br>Email: ' . esc_html( $store_email ) . '</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $attachments = [ $file_path ];
        @wp_mail( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Lista todos os documentos gerados (arquivos .html) no diretório de uploads/dps_docs. A lista
     * é apresentada como uma lista simples de links. Este método é usado pelo shortcode [dps_fin_docs].
     *
     * SEGURANÇA: Requer que o usuário esteja logado e tenha permissão manage_options
     * ou que a visualização pública esteja habilitada via filtro.
     *
     * @return string HTML renderizado
     */
    public function render_fin_docs_shortcode() {
        // SEGURANÇA: Verifica permissões antes de listar documentos
        // Permite filtro para habilitar visualização pública se necessário
        $allow_public_view = apply_filters( 'dps_finance_docs_allow_public', false );
        
        if ( ! $allow_public_view && ! current_user_can( 'manage_options' ) ) {
            ob_start();
            echo '<div class="dps-fin-docs">';
            echo '<p>' . esc_html__( 'Você não tem permissão para visualizar documentos financeiros.', 'dps-finance-addon' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        
        $upload_dir = wp_upload_dir();
        $doc_dir    = trailingslashit( $upload_dir['basedir'] ) . 'dps_docs';
        $doc_urlbase= trailingslashit( $upload_dir['baseurl'] ) . 'dps_docs/';
        global $wpdb;
        $doc_options = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            'dps_fin_doc_%'
        ) );
        $doc_map     = [];
        if ( $doc_options ) {
            foreach ( $doc_options as $opt ) {
                if ( preg_match( '/^dps_fin_doc_(\d+)$/', $opt->option_name, $m ) ) {
                    $doc_map[ $opt->option_value ] = intval( $m[1] );
                }
            }
        }
        ob_start();
        echo '<div class="dps-fin-docs">';
        echo '<h3>' . esc_html__( 'Documentos Financeiros', 'dps-finance-addon' ) . '</h3>';
        if ( ! file_exists( $doc_dir ) ) {
            echo '<p>' . esc_html__( 'Nenhum documento encontrado.', 'dps-finance-addon' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        $files = scandir( $doc_dir );
        $docs  = [];
        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            if ( substr( $file, -5 ) === '.html' ) {
                $docs[] = $file;
            }
        }
        if ( empty( $docs ) ) {
            echo '<p>' . esc_html__( 'Nenhum documento encontrado.', 'dps-finance-addon' ) . '</p>';
        } else {
            // Organiza documentos por tipo: Cobranca, Nota, Historico
            $categorized = [ 'cobranca' => [], 'nota' => [], 'historico' => [] ];
            foreach ( $docs as $doc ) {
                $lower = strtolower( $doc );
                if ( strpos( $lower, 'cobranca_' ) === 0 ) {
                    $categorized['cobranca'][] = $doc;
                } elseif ( strpos( $lower, 'nota_' ) === 0 ) {
                    $categorized['nota'][] = $doc;
                } elseif ( strpos( $lower, 'historico_' ) === 0 ) {
                    $categorized['historico'][] = $doc;
                } else {
                    $categorized['historico'][] = $doc; // fallback
                }
            }
            
            // PERFORMANCE: Busca todas as transações vinculadas de uma vez para evitar N+1 queries
            $trans_ids = array_filter( array_values( $doc_map ) );
            $transactions_data = [];
            if ( ! empty( $trans_ids ) ) {
                $table = $wpdb->prefix . 'dps_transacoes';
                // SEGURANÇA: Garante que todos os IDs são inteiros para prevenir SQL injection
                $trans_ids = array_map( 'intval', $trans_ids );
                $ids_placeholder = implode( ',', array_fill( 0, count( $trans_ids ), '%d' ) );
                $trans_results = $wpdb->get_results( 
                    $wpdb->prepare( "SELECT * FROM $table WHERE id IN ($ids_placeholder)", ...$trans_ids ) 
                );
                if ( $trans_results ) {
                    foreach ( $trans_results as $trans ) {
                        $transactions_data[ $trans->id ] = $trans;
                    }
                }
            }
            
            foreach ( [ 'cobranca' => __( 'Cobranças', 'dps-finance-addon' ), 'nota' => __( 'Notas', 'dps-finance-addon' ), 'historico' => __( 'Históricos', 'dps-finance-addon' ) ] as $key => $title ) {
                if ( empty( $categorized[ $key ] ) ) {
                    continue;
                }
                echo '<h4>' . esc_html( $title ) . '</h4>';
                echo '<table class="dps-table dps-fin-docs-table">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Documento', 'dps-finance-addon' ) . '</th>';
                echo '<th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th>';
                echo '<th>' . esc_html__( 'Data', 'dps-finance-addon' ) . '</th>';
                echo '<th>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '</th>';
                echo '<th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th>';
                echo '</tr></thead><tbody>';
                
                foreach ( $categorized[ $key ] as $doc ) {
                    $url = $doc_urlbase . $doc;
                    // Tenta encontrar trans_id correspondente ao doc
                    $trans_id = 0;
                    if ( isset( $doc_map[ $url ] ) ) {
                        $trans_id = $doc_map[ $url ];
                    }
                    
                    // Busca informações da transação na array pré-carregada
                    $client_name = '-';
                    $trans_date = '-';
                    $trans_value = '-';
                    
                    if ( $trans_id && isset( $transactions_data[ $trans_id ] ) ) {
                        $trans = $transactions_data[ $trans_id ];
                        if ( $trans ) {
                            // Data formatada
                            if ( $trans->data ) {
                                $trans_date = date_i18n( get_option( 'date_format' ), strtotime( $trans->data ) );
                            }
                            // Valor formatado
                            if ( $trans->valor ) {
                                $trans_value = 'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans->valor * 100 ) );
                            }
                            // Nome do cliente
                            if ( $trans->cliente_id ) {
                                $client_post = get_post( $trans->cliente_id );
                                if ( $client_post ) {
                                    $client_name = $client_post->post_title;
                                }
                            } elseif ( $trans->agendamento_id ) {
                                $client_id = get_post_meta( $trans->agendamento_id, 'appointment_client_id', true );
                                if ( $client_id ) {
                                    $client_post = get_post( $client_id );
                                    if ( $client_post ) {
                                        $client_name = $client_post->post_title;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Define URL base atual removendo parâmetros de ação
                    // Usa helper com fallback para evitar warnings de URL nula em PHP 8+
                    $current_url = $this->get_current_url();
                    $base_clean  = remove_query_arg( [ 'dps_send_doc', 'to_email', 'trans_id', 'dps_delete_doc', 'file', '_wpnonce' ], $current_url );
                    
                    // Link para envio por email (com nonce)
                    $send_link = wp_nonce_url(
                        add_query_arg( [ 'dps_send_doc' => '1', 'file' => rawurlencode( $doc ) ], $base_clean ),
                        'dps_send_doc_' . $doc
                    );
                    if ( $trans_id ) {
                        $send_link = add_query_arg( [ 'trans_id' => $trans_id ], $send_link );
                    }
                    
                    // Link para exclusão (com nonce)
                    $del_link = wp_nonce_url(
                        add_query_arg( [ 'dps_delete_doc' => '1', 'file' => rawurlencode( $doc ) ], $base_clean ),
                        'dps_delete_doc_' . $doc
                    );
                    
                    echo '<tr>';
                    echo '<td data-label="' . esc_attr__( 'Documento', 'dps-finance-addon' ) . '"><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $doc ) . '</a></td>';
                    echo '<td data-label="' . esc_attr__( 'Cliente', 'dps-finance-addon' ) . '">' . esc_html( $client_name ) . '</td>';
                    echo '<td data-label="' . esc_attr__( 'Data', 'dps-finance-addon' ) . '">' . esc_html( $trans_date ) . '</td>';
                    echo '<td data-label="' . esc_attr__( 'Valor', 'dps-finance-addon' ) . '">' . esc_html( $trans_value ) . '</td>';
                    echo '<td data-label="' . esc_attr__( 'Ações', 'dps-finance-addon' ) . '">';
                    echo '<a href="#" class="dps-fin-doc-email" data-base="' . esc_attr( $send_link ) . '">' . esc_html__( 'Enviar email', 'dps-finance-addon' ) . '</a> | ';
                    echo '<a href="' . esc_url( $del_link ) . '" class="dps-action-link-danger" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja excluir este documento?', 'dps-finance-addon' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-finance-addon' ) . '</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            // Script para prompt de email
            echo '<script>(function($){$(document).on("click", ".dps-fin-doc-email", function(e){e.preventDefault();var base=$(this).data("base");var email=prompt("Para qual email deseja enviar? Deixe em branco para usar o email padrão.");if(email===null){return;}email=email.trim();var url=base; if(email){url += (url.indexOf("?")===-1 ? "?" : "&") + "to_email=" + encodeURIComponent(email);} window.location.href=url;});})(jQuery);</script>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Exemplo de consulta utilizando metas históricas para somar faturamento.
     * Retorna o total em centavos do valor salvo em `_dps_total_at_booking`
     * para agendamentos dentro do intervalo informado.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @return int Total em centavos.
     */
    public function sum_revenue_by_period( $start_date, $end_date ) {
        return DPS_Finance_Revenue_Query::sum_by_period( $start_date, $end_date );
    }

    /**
     * Renderiza a seção do controle financeiro: formulário para nova transação e listagem.
     * 
     * SEGURANÇA: Revisado em 2025-11-23
     * - Sanitização consistente de $_GET com wp_unslash()
     * - Queries SQL usando $wpdb->prepare()
     */
    private function section_financeiro() {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        // Busca categorias distintas na base para uso no datalist e dropdown
        $cats = $wpdb->get_col( "SELECT DISTINCT categoria FROM $table ORDER BY categoria" );
        
        // Filtros de datas - SEGURANÇA: Sanitiza com wp_unslash()
        $start_date = isset( $_GET['fin_start'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_start'] ) ) : '';
        $end_date   = isset( $_GET['fin_end'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_end'] ) ) : '';
        // Filtro de categoria
        $cat_filter = isset( $_GET['fin_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_cat'] ) ) : '';
        // Filtro por status
        $status_filter = isset( $_GET['fin_status'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_status'] ) ) : '';
        // Intervalos rápidos: últimos 7/30 dias
        $range      = isset( $_GET['fin_range'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_range'] ) ) : '';
        if ( $range === '7' || $range === '30' ) {
            // Calcula intervalo relativo ao dia atual
            $days = intval( $range );
            $end_date   = current_time( 'Y-m-d' );
            $start_date = date( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );
        }
        
        // F2.5: FASE 2 - Busca rápida por cliente
        $search_client = isset( $_GET['fin_search_client'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_search_client'] ) ) : '';
        
        $where      = '1=1';
        $params     = [];
        if ( $start_date ) {
            $where  .= ' AND data >= %s';
            $params[] = $start_date;
        }
        if ( $end_date ) {
            $where  .= ' AND data <= %s';
            $params[] = $end_date;
        }
        
        // F2.5: FASE 2 - Filtro de busca por nome de cliente
        if ( $search_client ) {
            // Busca clientes cujo título contenha o termo de busca
            $search_like = '%' . $wpdb->esc_like( $search_client ) . '%';
            $matching_clients = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'dps_cliente' AND post_title LIKE %s AND post_status = 'publish'",
                $search_like
            ) );
            
            if ( ! empty( $matching_clients ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $matching_clients ), '%d' ) );
                $where .= " AND cliente_id IN ($placeholders)";
                $params = array_merge( $params, $matching_clients );
            } else {
                // Nenhum cliente encontrado - forçar resultado vazio
                $where .= ' AND 1=0';
            }
        }
        
        // Filtro por categoria
        if ( $cat_filter !== '' ) {
            $where  .= ' AND categoria = %s';
            $params[] = $cat_filter;
        }
        // Filtro por status
        if ( $status_filter !== '' && in_array( $status_filter, [ 'em_aberto', 'pago', 'cancelado' ], true ) ) {
            $where  .= ' AND status = %s';
            $params[] = $status_filter;
        }

        // Paginação - configuração
        $per_page    = 20;
        $current_page = isset( $_GET['fin_page'] ) ? max( 1, intval( $_GET['fin_page'] ) ) : 1;
        $offset      = ( $current_page - 1 ) * $per_page;

        // Conta total de registros para paginação
        if ( ! empty( $params ) ) {
            $count_query = $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where", $params );
        } else {
            $count_query = "SELECT COUNT(*) FROM $table WHERE $where";
        }
        $total_items = (int) $wpdb->get_var( $count_query );
        $total_pages = ceil( $total_items / $per_page );

        // Query com paginação
        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY data DESC LIMIT %d OFFSET %d", array_merge( $params, [ $per_page, $offset ] ) );
        } else {
            $query = $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY data DESC LIMIT %d OFFSET %d", $per_page, $offset );
        }

        // Lista de transações (paginada)
        $trans = $wpdb->get_results( $query );

        // Para o resumo financeiro, precisamos de todos os registros (sem paginação)
        // F1.4: FASE 1 - Performance: Limita consulta a últimos 12 meses para gráfico
        // Se não houver filtro de data aplicado, limita automaticamente aos últimos 12 meses
        $chart_limit_date = date( 'Y-m-d', strtotime( '-12 months' ) );
        $use_chart_limit = ! $start_date && ! $end_date; // Só limita se usuário não aplicou filtro próprio
        
        if ( $use_chart_limit ) {
            // Cria query otimizada com agregação SQL para melhor performance
            if ( ! empty( $params ) ) {
                $all_trans_query = $wpdb->prepare( 
                    "SELECT * FROM $table WHERE $where AND data >= %s ORDER BY data DESC", 
                    array_merge( $params, [ $chart_limit_date ] )
                );
            } else {
                $all_trans_query = $wpdb->prepare(
                    "SELECT * FROM $table WHERE data >= %s ORDER BY data DESC",
                    $chart_limit_date
                );
            }
        } else {
            // Usa query original quando usuário aplicou filtro de data
            if ( ! empty( $params ) ) {
                $all_trans_query = $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY data DESC", $params );
            } else {
                $all_trans_query = "SELECT * FROM $table ORDER BY data DESC";
            }
        }
        
        $all_trans = $wpdb->get_results( $all_trans_query );

        // Lista de clientes para seleção opcional
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ob_start();
        echo '<div class="dps-section" id="dps-section-financeiro">';
        echo '<h3>' . esc_html__( 'Controle Financeiro', 'dps-finance-addon' ) . '</h3>';

        // Exibe mensagens de feedback
        $this->render_feedback_messages();

        // F2.1: FASE 2 - UX: Card de pendências de hoje e vencidas
        $this->render_pending_alerts();
        
        // F3.4: FASE 3 - Comparativo mensal (mês atual vs anterior)
        $this->render_monthly_comparison();

        // Dashboard de resumo financeiro (usa todos os registros, não paginados)
        $this->render_finance_summary( $all_trans );
        
        // Relatório DRE simplificado (mostra quando há filtro de data aplicado ou quando solicitado)
        $show_dre = isset( $_GET['show_dre'] ) || ( $start_date && $end_date );
        if ( $show_dre && ! empty( $all_trans ) ) {
            $this->render_dre_report( $all_trans );
        }
        
        // F3.5: FASE 3 - Top 10 clientes por receita (usa período filtrado ou mês atual)
        $this->render_top_clients( $start_date, $end_date );

        // Se um ID de transação foi passado via query para registrar pagamento parcial, exibe formulário especializado
        if ( isset( $_GET['register_partial'] ) && is_numeric( $_GET['register_partial'] ) ) {
            global $wpdb;
            $partial_id = intval( $_GET['register_partial'] );
            $trans_pp  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $partial_id ) );
            if ( $trans_pp ) {
                $already_paid = $this->get_partial_sum( $partial_id );
                $desc_value   = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $trans_pp->valor * 100 ) );
                $paid_value   = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $already_paid * 100 ) );
                echo '<div class="dps-partial-form" style="margin:15px 0;padding:10px;border:1px solid #ddd;background:#f9f9f9;">';
                echo '<h4>' . sprintf( esc_html__( 'Registrar pagamento parcial - Transação #%1$s (Total: R$ %2$s, Pago: R$ %3$s)', 'dps-finance-addon' ), esc_html( $partial_id ), esc_html( $desc_value ), esc_html( $paid_value ) ) . '</h4>';
                echo '<form method="post" class="dps-form">';
                echo '<input type="hidden" name="dps_finance_action" value="save_partial">';
                wp_nonce_field( 'dps_finance_action', 'dps_finance_nonce' );
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $partial_id ) . '">';
                $today = date( 'Y-m-d' );
                echo '<p><label>' . esc_html__( 'Data', 'dps-finance-addon' ) . '<br><input type="date" name="partial_date" value="' . esc_attr( $today ) . '" required></label></p>';
                echo '<p><label>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '<br><input type="number" step="0.01" name="partial_value" required></label></p>';
                echo '<p><label>' . esc_html__( 'Método', 'dps-finance-addon' ) . '<br><select name="partial_method"><option value="pix">PIX</option><option value="cartao">' . esc_html__( 'Cartão', 'dps-finance-addon' ) . '</option><option value="dinheiro">' . esc_html__( 'Dinheiro', 'dps-finance-addon' ) . '</option><option value="outro">' . esc_html__( 'Outro', 'dps-finance-addon' ) . '</option></select></label></p>';
                $cancel_link = esc_url( remove_query_arg( 'register_partial' ) . '#financeiro' );
                echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar', 'dps-finance-addon' ) . '</button> <a href="' . $cancel_link . '" class="button">' . esc_html__( 'Cancelar', 'dps-finance-addon' ) . '</a></p>';
                echo '</form>';
                echo '</div>';
            }
        }
        // Formulário de nova transação com fieldsets semânticos
        echo '<form method="post" class="dps-form dps-finance-new-form" id="dps-finance-new-form">';
        echo '<input type="hidden" name="dps_finance_action" value="save_trans">';
        wp_nonce_field( 'dps_finance_action', 'dps_finance_nonce' );
        
        // Fieldset: Dados Básicos
        echo '<fieldset class="dps-finance-fieldset">';
        echo '<legend>' . esc_html__( 'Dados Básicos', 'dps-finance-addon' ) . '</legend>';
        echo '<div class="dps-finance-form-grid">';
        echo '<div class="dps-field">';
        echo '<label for="finance_date">' . esc_html__( 'Data', 'dps-finance-addon' ) . ' *</label>';
        echo '<input type="date" id="finance_date" name="finance_date" value="' . esc_attr( date( 'Y-m-d' ) ) . '" required>';
        echo '</div>';
        echo '<div class="dps-field">';
        echo '<label for="finance_value">' . esc_html__( 'Valor', 'dps-finance-addon' ) . ' *</label>';
        echo '<div class="dps-input-money-wrapper">';
        echo '<span class="dps-input-prefix">R$</span>';
        echo '<input type="text" id="finance_value" name="finance_value" class="dps-input-money" placeholder="0,00" required>';
        echo '</div>';
        echo '</div>';
        echo '<div class="dps-field">';
        echo '<label for="finance_desc">' . esc_html__( 'Descrição', 'dps-finance-addon' ) . '</label>';
        echo '<input type="text" id="finance_desc" name="finance_desc" placeholder="' . esc_attr__( 'Ex: Pagamento de serviço', 'dps-finance-addon' ) . '">';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';
        
        // Fieldset: Classificação
        echo '<fieldset class="dps-finance-fieldset">';
        echo '<legend>' . esc_html__( 'Classificação', 'dps-finance-addon' ) . '</legend>';
        echo '<div class="dps-finance-form-grid">';
        echo '<div class="dps-field">';
        echo '<label for="finance_category">' . esc_html__( 'Categoria', 'dps-finance-addon' ) . ' *</label>';
        echo '<input type="text" id="finance_category" name="finance_category" list="finance_categories" required placeholder="' . esc_attr__( 'Ex: Serviço, Produto, Aluguel...', 'dps-finance-addon' ) . '">';
        // Datalist com categorias existentes
        echo '<datalist id="finance_categories">';
        if ( $cats ) {
            foreach ( $cats as $cat ) {
                echo '<option value="' . esc_attr( $cat ) . '">';
            }
        }
        echo '</datalist>';
        echo '</div>';
        echo '<div class="dps-field">';
        echo '<label for="finance_type">' . esc_html__( 'Tipo', 'dps-finance-addon' ) . ' *</label>';
        echo '<select id="finance_type" name="finance_type" required>';
        echo '<option value="receita">' . esc_html__( 'Receita', 'dps-finance-addon' ) . '</option>';
        echo '<option value="despesa">' . esc_html__( 'Despesa', 'dps-finance-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="dps-field">';
        echo '<label for="finance_status">' . esc_html__( 'Status', 'dps-finance-addon' ) . ' *</label>';
        echo '<select id="finance_status" name="finance_status" required>';
        echo '<option value="em_aberto">' . esc_html__( 'Em aberto', 'dps-finance-addon' ) . '</option>';
        echo '<option value="pago">' . esc_html__( 'Pago', 'dps-finance-addon' ) . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';
        
        // Fieldset: Vínculo (opcional)
        echo '<fieldset class="dps-finance-fieldset">';
        echo '<legend>' . esc_html__( 'Vínculo (opcional)', 'dps-finance-addon' ) . '</legend>';
        echo '<div class="dps-finance-form-grid">';
        echo '<div class="dps-field">';
        echo '<label for="finance_client_id">' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</label>';
        echo '<select id="finance_client_id" name="finance_client_id">';
        echo '<option value="">' . esc_html__( 'Nenhum', 'dps-finance-addon' ) . '</option>';
        foreach ( $clients as $cli ) {
            echo '<option value="' . esc_attr( $cli->ID ) . '">' . esc_html( $cli->post_title ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';
        
        echo '<div class="dps-form-actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Salvar Transação', 'dps-finance-addon' ) . '</button>';
        echo '</div>';
        echo '</form>';
        
        // Lista de transações
        echo '<h4>' . esc_html__( 'Transações Registradas', 'dps-finance-addon' ) . '</h4>';
        // Formulário de filtro por data e categoria
        echo '<form method="get" class="dps-finance-date-filter dps-finance-filters" style="margin-bottom:10px;">';
        // Mantém parâmetros existentes (exceto filtros de data, intervalo, categoria e status)
        // SEGURANÇA: Sanitiza valores de $_GET antes de usar
        foreach ( $_GET as $k => $v ) {
            if ( in_array( $k, [ 'fin_start', 'fin_end', 'fin_range', 'fin_cat', 'fin_status', 'fin_search_client' ], true ) ) {
                continue;
            }
            $safe_key = sanitize_key( $k );
            $safe_val = is_array( $v ) ? '' : sanitize_text_field( wp_unslash( $v ) );
            echo '<input type="hidden" name="' . esc_attr( $safe_key ) . '" value="' . esc_attr( $safe_val ) . '">';
        }
        echo '<label>' . esc_html__( 'De', 'dps-finance-addon' ) . ' <input type="date" name="fin_start" value="' . esc_attr( $start_date ) . '"></label> ';
        echo '<label>' . esc_html__( 'Até', 'dps-finance-addon' ) . ' <input type="date" name="fin_end" value="' . esc_attr( $end_date ) . '"></label> ';
        // Dropdown de categorias (usa $cats definido no início do método)
        echo '<label>' . esc_html__( 'Categoria', 'dps-finance-addon' ) . ' <select name="fin_cat"><option value="">' . esc_html__( 'Todas', 'dps-finance-addon' ) . '</option>';
        if ( $cats ) {
            foreach ( $cats as $cat ) {
                $cat_clean = esc_attr( $cat );
                echo '<option value="' . $cat_clean . '"' . selected( $cat_filter, $cat, false ) . '>' . esc_html( $cat ) . '</option>';
            }
        }
        echo '</select></label> ';
        // Dropdown de status
        echo '<label>' . esc_html__( 'Status', 'dps-finance-addon' ) . ' <select name="fin_status">';
        echo '<option value="">' . esc_html__( 'Todos', 'dps-finance-addon' ) . '</option>';
        echo '<option value="em_aberto"' . selected( $status_filter, 'em_aberto', false ) . '>' . esc_html__( 'Em aberto', 'dps-finance-addon' ) . '</option>';
        echo '<option value="pago"' . selected( $status_filter, 'pago', false ) . '>' . esc_html__( 'Pago', 'dps-finance-addon' ) . '</option>';
        echo '<option value="cancelado"' . selected( $status_filter, 'cancelado', false ) . '>' . esc_html__( 'Cancelado', 'dps-finance-addon' ) . '</option>';
        echo '</select></label> ';
        // F2.5: FASE 2 - Campo de busca rápida por cliente
        echo '<label>' . esc_html__( 'Buscar cliente', 'dps-finance-addon' ) . ' <input type="text" name="fin_search_client" value="' . esc_attr( $search_client ) . '" placeholder="' . esc_attr__( 'Nome do cliente...', 'dps-finance-addon' ) . '" style="width: 200px;"></label> ';
        echo '<div class="dps-finance-filter-buttons">';
        echo '<button type="submit" class="button">' . esc_html__( 'Filtrar', 'dps-finance-addon' ) . '</button> ';
        // Links rápidos: preserva categoria e status
        $quick_params = $_GET;
        unset( $quick_params['fin_start'], $quick_params['fin_end'], $quick_params['fin_range'] );
        // Garante manter a categoria e status selecionados
        if ( $cat_filter !== '' ) {
            $quick_params['fin_cat'] = $cat_filter;
        }
        if ( $status_filter !== '' ) {
            $quick_params['fin_status'] = $status_filter;
        }
        $link7  = add_query_arg( array_merge( $quick_params, [ 'fin_range' => '7' ] ), $this->get_current_url() ) . '#financeiro';
        $link30 = add_query_arg( array_merge( $quick_params, [ 'fin_range' => '30' ] ), $this->get_current_url() ) . '#financeiro';
        echo '<a href="' . esc_url( $link7 ) . '" class="button">' . esc_html__( 'Últimos 7 dias', 'dps-finance-addon' ) . '</a> ';
        echo '<a href="' . esc_url( $link30 ) . '" class="button">' . esc_html__( 'Últimos 30 dias', 'dps-finance-addon' ) . '</a> ';
        // F2.5: FASE 2 - Link de limpar filtros inclui campo de busca
        $clear_params = $quick_params;
        unset( $clear_params['fin_start'], $clear_params['fin_end'], $clear_params['fin_range'], $clear_params['fin_cat'], $clear_params['fin_status'], $clear_params['fin_search_client'] );
        $clear_link = add_query_arg( $clear_params, $this->get_current_url() ) . '#financeiro';
        echo '<a href="' . esc_url( $clear_link ) . '" class="button">' . esc_html__( 'Limpar filtros', 'dps-finance-addon' ) . '</a>';
        // Link para exportar CSV das transações filtradas
        $export_params = $_GET;
        $export_params['dps_fin_export'] = '1';
        $export_link = add_query_arg( $export_params, $this->get_current_url() ) . '#financeiro';
        echo '<a href="' . esc_url( $export_link ) . '" class="button">' . esc_html__( 'Exportar CSV', 'dps-finance-addon' ) . '</a>';
        echo '</div>';
        echo '</form>';
        if ( $trans ) {
            // PRÉ-CARREGA POSTS PARA PERFORMANCE
            // Coleta IDs de clientes, pets e agendamentos para pré-carregar
            $client_ids = [];
            $appt_ids   = [];
            foreach ( $trans as $tr ) {
                if ( $tr->cliente_id ) {
                    $client_ids[] = (int) $tr->cliente_id;
                }
                if ( $tr->agendamento_id ) {
                    $appt_ids[] = (int) $tr->agendamento_id;
                }
            }
            
            // Primeiro pré-carrega agendamentos (precisamos dos metadados para obter IDs de pets)
            $appt_ids = array_unique( $appt_ids );
            if ( ! empty( $appt_ids ) ) {
                _prime_post_caches( $appt_ids, true, false );
            }
            
            // Coleta IDs de pets dos metadados de agendamentos
            $pet_ids = [];
            foreach ( $appt_ids as $appt_id ) {
                $pet_id = get_post_meta( $appt_id, 'appointment_pet_id', true );
                if ( $pet_id ) {
                    $pet_ids[] = (int) $pet_id;
                }
            }
            
            // Combina clientes e pets em uma única chamada de pré-carregamento
            $all_post_ids = array_unique( array_merge( $client_ids, $pet_ids ) );
            if ( ! empty( $all_post_ids ) ) {
                _prime_post_caches( $all_post_ids, false, false );
            }
            
            // Estilos para destacar o status das transações. Linhas com status em aberto ficam amareladas
            // e linhas com status pago ficam esverdeadas, facilitando a identificação rápida.
            // F2.3: FASE 2 - Estilos para badges visuais de status
            echo '<style>
            table.dps-table tr.fin-status-em_aberto { background-color:#fff8e1; }
            table.dps-table tr.fin-status-pago { background-color:#e6ffed; }
            
            /* Badges visuais de status */
            .dps-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
                line-height: 1.2;
            }
            .dps-badge--success {
                background-color: #d1fae5;
                color: #065f46;
            }
            .dps-badge--warning {
                background-color: #fef3c7;
                color: #92400e;
            }
            .dps-badge--danger {
                background-color: #fee2e2;
                color: #991b1b;
            }
            </style>';
            // Cabeçalho da tabela: adicionamos colunas para Pet atendido, Serviços, Contato (WhatsApp) e Recorrente
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Categoria', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Tipo', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-finance-addon' ) . '</th>';
            // Coluna de pagamentos parciais: exibe valor pago e total e link para registrar
            echo '<th>' . esc_html__( 'Pagamentos', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet atendido', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Cobrança', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $trans as $tr ) {
                // Cliente (já pré-carregado)
                $client_name = '';
                if ( $tr->cliente_id ) {
                    $cpost = get_post( $tr->cliente_id );
                    if ( $cpost ) {
                        $client_name = $cpost->post_title;
                    }
                }
                // Pet atendido (se agendamento vinculado) - já pré-carregado
                $pet_name = '-';
                if ( $tr->agendamento_id ) {
                    $pet_id = get_post_meta( $tr->agendamento_id, 'appointment_pet_id', true );
                    if ( $pet_id ) {
                        $ppost = get_post( $pet_id );
                        if ( $ppost ) {
                            $pet_name = $ppost->post_title;
                        }
                    }
                }
                // Status editável
                $status_options = [
                    'em_aberto' => __( 'Em aberto', 'dps-finance-addon' ),
                    'pago'      => __( 'Pago', 'dps-finance-addon' ),
                    'cancelado' => __( 'Cancelado', 'dps-finance-addon' ),
                ];
                // Adiciona classe para o status da transação
                echo '<tr class="fin-status-' . esc_attr( $tr->status ) . '">';
                // F2.4: FASE 2 - Indicadores visuais de vencimento
                echo '<td data-label="' . esc_attr__( 'Data', 'dps-finance-addon' ) . '">';
                $date_display = date_i18n( 'd-m-Y', strtotime( $tr->data ) );
                
                // Se está em aberto, verifica se venceu ou vence hoje
                if ( $tr->status === 'em_aberto' && $tr->tipo === 'receita' ) {
                    $today = current_time( 'Y-m-d' );
                    $trans_date = $tr->data;
                    
                    if ( $trans_date < $today ) {
                        // Vencida - vermelho
                        echo '<span style="color: #ef4444; font-weight: 600;" title="' . esc_attr__( 'Vencida', 'dps-finance-addon' ) . '">';
                        echo '🚨 ' . esc_html( $date_display );
                        echo '</span>';
                    } elseif ( $trans_date === $today ) {
                        // Vence hoje - amarelo
                        echo '<span style="color: #f59e0b; font-weight: 600;" title="' . esc_attr__( 'Vence hoje', 'dps-finance-addon' ) . '">';
                        echo '⚠️ ' . esc_html( $date_display );
                        echo '</span>';
                    } else {
                        // Futura - normal
                        echo esc_html( $date_display );
                    }
                } else {
                    echo esc_html( $date_display );
                }
                
                echo '</td>';
                $tr_valor_cents = (int) round( (float) $tr->valor * 100 );
                echo '<td data-label="' . esc_attr__( 'Valor', 'dps-finance-addon' ) . '">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $tr_valor_cents ) ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Categoria', 'dps-finance-addon' ) . '">' . esc_html( $tr->categoria ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Tipo', 'dps-finance-addon' ) . '">' . esc_html( $tr->tipo ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Status', 'dps-finance-addon' ) . '">';
                // F2.3: FASE 2 - Badges visuais de status
                $status_badge_class = 'dps-badge';
                $status_emoji = '';
                
                if ( $tr->status === 'pago' ) {
                    $status_badge_class .= ' dps-badge--success';
                    $status_emoji = '✅ ';
                } elseif ( $tr->status === 'em_aberto' ) {
                    $status_badge_class .= ' dps-badge--warning';
                    $status_emoji = '⏳ ';
                } elseif ( $tr->status === 'cancelado' ) {
                    $status_badge_class .= ' dps-badge--danger';
                    $status_emoji = '❌ ';
                }
                
                echo '<span class="' . esc_attr( $status_badge_class ) . '">';
                echo $status_emoji . esc_html( $status_options[ $tr->status ] ?? $tr->status );
                echo '</span>';
                
                // Form para editar status (inline, menor)
                echo '<form method="post" style="display:inline-block; margin-left: 8px;">';
                echo '<input type="hidden" name="dps_update_trans_status" value="1">';
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $tr->id ) . '">';
                echo '<select name="trans_status" class="dps-status-select" data-current="' . esc_attr( $tr->status ) . '" onchange="this.form.submit()" style="font-size: 12px; padding: 2px 4px;">';
                foreach ( $status_options as $val => $label ) {
                    echo '<option value="' . esc_attr( $val ) . '"' . selected( $tr->status, $val, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                echo '</form>';
                echo '</td>';
                // Coluna de pagamentos parciais
                $partial_paid = $this->get_partial_sum( $tr->id );
                echo '<td data-label="' . esc_attr__( 'Pagamentos', 'dps-finance-addon' ) . '">';
                $partial_paid_cents = (int) round( (float) $partial_paid * 100 );
                echo 'R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $partial_paid_cents ) ) . ' / R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $tr_valor_cents ) );
                // Link para ver histórico de parcelas (se houver alguma parcela paga)
                if ( $partial_paid > 0 ) {
                    echo ' <a href="#" class="dps-view-partials" data-trans-id="' . esc_attr( $tr->id ) . '">' . esc_html__( 'Histórico', 'dps-finance-addon' ) . '</a>';
                }
                if ( $tr->status !== 'pago' ) {
                    // Mantém parâmetros de filtro existentes ao gerar o link de registro
                    $link_params = $_GET;
                    $link_params['register_partial'] = $tr->id;
                    $reg_link = add_query_arg( $link_params, $this->get_current_url() ) . '#financeiro';
                    echo ' <a href="' . esc_url( $reg_link ) . '">' . esc_html__( 'Registrar', 'dps-finance-addon' ) . '</a>';
                }
                echo '</td>';
                echo '<td data-label="' . esc_attr__( 'Cliente', 'dps-finance-addon' ) . '">' . esc_html( $client_name ?: '-' ) . '</td>';
                echo '<td data-label="' . esc_attr__( 'Pet atendido', 'dps-finance-addon' ) . '">' . esc_html( $pet_name ) . '</td>';
                // Link Serviços
                if ( $tr->agendamento_id ) {
                    echo '<td data-label="' . esc_attr__( 'Serviços', 'dps-finance-addon' ) . '"><a href="#" class="dps-trans-services" data-appt-id="' . esc_attr( $tr->agendamento_id ) . '">' . esc_html__( 'Ver', 'dps-finance-addon' ) . '</a></td>';
                } else {
                    echo '<td data-label="' . esc_attr__( 'Serviços', 'dps-finance-addon' ) . '">-</td>';
                }
                // Cobrança via WhatsApp: se transação estiver em aberto e não for de assinatura, cria link; caso contrário, mostra "-"
                echo '<td data-label="' . esc_attr__( 'Cobrança', 'dps-finance-addon' ) . '">';
                $show_charge = false;
                // Apenas para status em aberto e sem plano (não é assinatura)
                if ( $tr->status === 'em_aberto' && empty( $tr->plano_id ) && $tr->cliente_id ) {
                    $show_charge = true;
                }
                if ( $show_charge ) {
                    // Obtém telefone do cliente (meta client_phone)
                    $phone = get_post_meta( $tr->cliente_id, 'client_phone', true );
                    if ( $phone ) {
                        // Remove caracteres não numéricos
                        $digits = preg_replace( '/\D+/', '', $phone );
                        // Garante código do Brasil (55) se for número local sem DDI
                        if ( strlen( $digits ) == 10 || strlen( $digits ) == 11 ) {
                            $digits = '55' . $digits;
                        }
                        // Prepara mensagem de cobrança usando configurações
                        $client_name = $client_name ? $client_name : '';
                        $pet_title   = $pet_name !== '-' ? $pet_name : '';
                        $date_str    = date_i18n( 'd-m-Y', strtotime( $tr->data ) );
                        $valor_str   = DPS_Money_Helper::format_to_brazilian( $tr_valor_cents );
                        // Usa configurações se disponível, senão fallback para mensagem padrão
                        if ( class_exists( 'DPS_Finance_Settings' ) ) {
                            $msg = DPS_Finance_Settings::get_whatsapp_message( $client_name, $pet_title, $date_str, $valor_str );
                        } else {
                            $msg = sprintf(
                                __( 'Olá %s, tudo bem? O atendimento do pet %s em %s foi finalizado e o pagamento de R$ %s ainda está pendente. Obrigado pela confiança!', 'dps-finance-addon' ),
                                $client_name,
                                $pet_title,
                                $date_str,
                                $valor_str
                            );
                        }
                        $wa_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-finance-addon' ) . '</a>';
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                // Recorrência removida: não exibe coluna de recorrente
                // F2.2, F2.3: FASE 2 - Ações: excluir, reenviar link MP, badges visuais
                echo '<td data-label="' . esc_attr__( 'Ações', 'dps-finance-addon' ) . '">';
                
                // F2.2: Botão "Reenviar link de pagamento" (Mercado Pago)
                if ( $tr->agendamento_id && $tr->status === 'em_aberto' ) {
                    $payment_link = get_post_meta( $tr->agendamento_id, 'dps_payment_link', true );
                    
                    if ( $payment_link ) {
                        $resend_url = wp_nonce_url(
                            add_query_arg( [
                                'dps_resend_payment_link' => '1',
                                'trans_id' => $tr->id,
                                'tab' => 'financeiro',
                            ], $this->get_current_url() ),
                            'dps_resend_link_' . $tr->id
                        );
                        
                        echo '<a href="' . esc_url( $resend_url ) . '" class="dps-action-link" style="margin-right: 8px;" title="' . esc_attr__( 'Reenviar link de pagamento via WhatsApp', 'dps-finance-addon' ) . '">';
                        echo '✉️ ' . esc_html__( 'Reenviar link', 'dps-finance-addon' );
                        echo '</a>';
                    }
                }
                
                // Ação: excluir com nonce de segurança
                $delete_url = wp_nonce_url(
                    add_query_arg( [ 'dps_delete_trans' => '1', 'id' => $tr->id ] ),
                    'dps_finance_delete_' . $tr->id
                );
                echo '<a href="' . esc_url( $delete_url ) . '" class="dps-action-link dps-action-link-danger dps-delete-trans">' . esc_html__( 'Excluir', 'dps-finance-addon' ) . '</a>';
                
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            // Renderiza paginação
            $this->render_pagination( $current_page, $total_pages, $total_items );

            // ========= Gráficos de receitas/despesas e categorias =========
            // Calcula totais por mês e por categoria
            $month_receitas  = [];
            $month_despesas  = [];
            $cat_totals      = [];
            foreach ( $trans as $tr_rec ) {
                $month_key = date_i18n( 'm/Y', strtotime( $tr_rec->data ) );
                $valor     = (float) $tr_rec->valor;
                if ( $tr_rec->tipo === 'receita' ) {
                    if ( ! isset( $month_receitas[ $month_key ] ) ) $month_receitas[ $month_key ] = 0;
                    $month_receitas[ $month_key ] += $valor;
                } else {
                    if ( ! isset( $month_despesas[ $month_key ] ) ) $month_despesas[ $month_key ] = 0;
                    $month_despesas[ $month_key ] += $valor;
                }
                $cat = $tr_rec->categoria ? $tr_rec->categoria : __( 'Outros', 'dps-finance-addon' );
                if ( ! isset( $cat_totals[ $cat ] ) ) $cat_totals[ $cat ] = 0;
                $cat_totals[ $cat ] += $valor;
            }
            // Ordena meses
            $all_months = array_unique( array_merge( array_keys( $month_receitas ), array_keys( $month_despesas ) ) );
            sort( $all_months );
            $receitas_values = [];
            $despesas_values = [];
            foreach ( $all_months as $mk ) {
                $receitas_values[] = isset( $month_receitas[ $mk ] ) ? round( $month_receitas[ $mk ], 2 ) : 0;
                $despesas_values[] = isset( $month_despesas[ $mk ] ) ? round( $month_despesas[ $mk ], 2 ) : 0;
            }
            // Prepara dados de categorias
            $cat_labels  = array_keys( $cat_totals );
            $cat_values  = array_values( $cat_totals );
            // Gera cores aleatórias para categorias
            $cat_colors  = [];
            foreach ( $cat_labels as $clab ) {
                $cat_colors[] = 'rgba(' . mt_rand( 0, 255 ) . ', ' . mt_rand( 0, 255 ) . ', ' . mt_rand( 0, 255 ) . ', 0.6)';
            }
            echo '<div style="margin-top:20px;">';
            // Removido: gráficos financeiros não são exibidos
            echo '</div>';
            // Script inline removido - funcionalidade movida para assets/js/finance-addon.js

            // ============== Cobrança de pendências ==============
            echo '<h4>' . esc_html__( 'Cobrança de pendências', 'dps-finance-addon' ) . '</h4>';
            // Agrupa transações em aberto por cliente, considerando pagamentos parciais
            // Usa $all_trans para mostrar todas as pendências, não apenas as da página atual
            $pendings = [];
            foreach ( $all_trans as $item ) {
                if ( $item->status === 'em_aberto' ) {
                    $due = (float) $item->valor - $this->get_partial_sum( $item->id );
                    if ( $due > 0 ) {
                        $cid = $item->cliente_id ? intval( $item->cliente_id ) : 0;
                        if ( ! isset( $pendings[ $cid ] ) ) {
                            $pendings[ $cid ] = [ 'due' => 0.0, 'trans' => [] ];
                        }
                        $pendings[ $cid ]['due'] += $due;
                        $pendings[ $cid ]['trans'][] = $item;
                    }
                }
            }
            if ( ! empty( $pendings ) ) {
                echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th><th>' . esc_html__( 'Valor devido', 'dps-finance-addon' ) . '</th><th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th></tr></thead><tbody>';
                foreach ( $pendings as $cid => $pdata ) {
                    $cname = '';
                    $phone_link = '';
                    if ( $cid ) {
                        $cpost = get_post( $cid );
                        if ( $cpost ) {
                            $cname = $cpost->post_title;
                            $phone_meta = get_post_meta( $cid, 'client_phone', true );
                            if ( $phone_meta ) {
                                $valor_str = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $pdata['due'] * 100 ) );
                                // Usa mensagem configurável se disponível
                                if ( class_exists( 'DPS_Finance_Settings' ) ) {
                                    $msg = DPS_Finance_Settings::get_pending_message( $cname, $valor_str );
                                } else {
                                    $msg = sprintf(
                                        __( 'Olá %s, tudo bem? Há pagamentos pendentes no total de R$ %s. Para regularizar, você pode pagar via PIX. Muito obrigado!', 'dps-finance-addon' ),
                                        $cname,
                                        $valor_str
                                    );
                                }
                                // Gera link usando helper centralizado
                                if ( class_exists( 'DPS_WhatsApp_Helper' ) ) {
                                    $phone_link = DPS_WhatsApp_Helper::get_link_to_client( $phone_meta, $msg );
                                } else {
                                    // Fallback
                                    $digits = preg_replace( '/\D+/', '', $phone_meta );
                                    if ( strlen( $digits ) == 10 || strlen( $digits ) == 11 ) {
                                        $digits = '55' . $digits;
                                    }
                                    $phone_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                                }
                            }
                        }
                    }
                    $due_cents = (int) round( (float) $pdata['due'] * 100 );
                    echo '<tr><td>' . esc_html( $cname ?: '-' ) . '</td><td>R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( $due_cents ) ) . '</td><td>';
                    if ( $phone_link ) {
                        echo '<a href="' . esc_url( $phone_link ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-finance-addon' ) . '</a>';
                    } else {
                        echo '-';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'Nenhum cliente com pendências em aberto.', 'dps-finance-addon' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Nenhuma transação registrada.', 'dps-finance-addon' ) . '</p>';
        }
        // Seções adicionais removidas: cobranças/notas e documentos não são exibidos nesta versão
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Sincroniza o status das transações financeiras quando o status de um agendamento é atualizado.
     *
     * Este método é disparado pelo hook 'updated_post_meta'. Se a meta atualizada for 'appointment_status',
     * procura transações vinculadas ao agendamento e atualiza seu status no banco financeiro.
     *
     * @param int    $meta_id    ID da meta atualizada.
     * @param int    $object_id  ID do post ao qual a meta pertence (agendamento).
     * @param string $meta_key   Chave da meta atualizada.
     * @param mixed  $meta_value Novo valor da meta.
     */
    public function sync_status_to_finance( $meta_id, $object_id, $meta_key, $meta_value ) {
        // Apenas processa status de agendamentos
        if ( $meta_key !== 'appointment_status' ) {
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'dps_transacoes';
        $appt_id = intval( $object_id );
        if ( ! $appt_id ) {
            return;
        }
        // Determina novo status financeiro com base no status do agendamento
        $status_map = [
            'finalizado_pago'   => 'pago',
            'finalizado e pago' => 'pago',
            'finalizado'        => 'em_aberto',
            'cancelado'         => 'cancelado',
        ];
        $status_slug = is_string( $meta_value ) ? $meta_value : '';
        $new_status  = isset( $status_map[ $status_slug ] ) ? $status_map[ $status_slug ] : null;
        if ( ! $new_status ) {
            return;
        }

        // Verifica se já existe transação para este agendamento
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE agendamento_id = %d", $appt_id ) );

        // Cancelamento apenas altera o status das transações existentes
        if ( $new_status === 'cancelado' ) {
            if ( $existing_id ) {
                $wpdb->update( $table, [ 'status' => 'cancelado' ], [ 'agendamento_id' => $appt_id ], [ '%s' ], [ '%d' ] );
            }
            return;
        }

        // Recupera informações do agendamento para atualizar ou criar transação
        $client_id   = get_post_meta( $appt_id, 'appointment_client_id', true );
        $valor_cents = (int) get_post_meta( $appt_id, '_dps_total_at_booking', true );
        if ( $valor_cents <= 0 ) {
            $valor_meta  = get_post_meta( $appt_id, 'appointment_total_value', true );
            $valor_cents = DPS_Money_Helper::parse_brazilian_format( $valor_meta );
        }
        $valor = $valor_cents / 100;
        // Monta descrição a partir de serviços e pet
        $desc_parts  = [];
        $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            foreach ( $service_ids as $sid ) {
                $srv = get_post( $sid );
                if ( $srv ) {
                    $desc_parts[] = $srv->post_title;
                }
            }
        }
        $pet_id   = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet_post = $pet_id ? get_post( $pet_id ) : null;
        if ( $pet_post ) {
            $desc_parts[] = $pet_post->post_title;
        }
        $desc = implode( ' - ', $desc_parts );
        // Determina a data da transação. Para atualizações de status, usamos a data do agendamento ou a data atual caso não exista.
        $appt_date  = get_post_meta( $appt_id, 'appointment_date', true );
        $trans_date = $appt_date ? $appt_date : current_time( 'Y-m-d' );
        $trans_data = [
            'cliente_id'     => $client_id ?: null,
            'agendamento_id' => $appt_id,
            'plano_id'       => null,
            'data'           => $trans_date,
            'valor'          => $valor,
            'categoria'      => __( 'Serviço', 'dps-finance-addon' ),
            'tipo'           => 'receita',
            'status'         => ( $new_status === 'pago' ? 'pago' : 'em_aberto' ),
            'descricao'      => $desc,
        ];
        if ( $existing_id ) {
            // Atualiza a transação existente com novo status, valor, descrição e data
            $wpdb->update( $table, [
                'status'    => $trans_data['status'],
                'valor'     => $trans_data['valor'],
                'descricao' => $trans_data['descricao'],
                'data'      => $trans_data['data'],
            ], [ 'id' => $existing_id ], [ '%s','%f','%s','%s' ], [ '%d' ] );
        } else {
            // Cria uma nova transação se ainda não existir
            $wpdb->insert( $table, $trans_data, [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
        }

        if ( $trans_data['status'] === 'pago' ) {
            $amount_in_cents = (int) round( $valor * 100 );
            do_action( 'dps_finance_booking_paid', $appt_id, (int) $client_id, $amount_in_cents );
        }
        // fecha o método sync_status_to_finance
    }

    /**
     * Renderiza mensagens de feedback após ações do usuário.
     *
     * @since 1.1.0
     * @since 1.3.1 Adicionada mensagem partial_exceeds_total (FASE 1 - F1.2)
     */
    private function render_feedback_messages() {
        if ( ! isset( $_GET['dps_msg'] ) ) {
            return;
        }

        $msg_key = sanitize_text_field( wp_unslash( $_GET['dps_msg'] ) );
        
        // F1.2: FASE 1 - Mensagem de erro quando parcial excede total
        if ( $msg_key === 'partial_exceeds_total' ) {
            $total = isset( $_GET['total'] ) ? sanitize_text_field( wp_unslash( $_GET['total'] ) ) : '0,00';
            $paid = isset( $_GET['paid'] ) ? sanitize_text_field( wp_unslash( $_GET['paid'] ) ) : '0,00';
            $attempted = isset( $_GET['attempted'] ) ? sanitize_text_field( wp_unslash( $_GET['attempted'] ) ) : '0,00';
            
            $remaining_cents = DPS_Money_Helper::parse_brazilian_format( $total ) - DPS_Money_Helper::parse_brazilian_format( $paid );
            $remaining = DPS_Money_Helper::format_to_brazilian( $remaining_cents );
            
            $text = sprintf(
                /* translators: 1: Attempted value, 2: Total value, 3: Already paid, 4: Remaining */
                __( 'ERRO: O valor informado (R$ %1$s) ultrapassa o saldo restante da transação. Total: R$ %2$s | Já pago: R$ %3$s | Restante: R$ %4$s', 'dps-finance-addon' ),
                $attempted,
                $total,
                $paid,
                $remaining
            );
            
            echo '<div class="dps-alert dps-alert--danger" role="alert" aria-live="assertive">';
            echo esc_html( $text );
            echo '</div>';
            return;
        }
        
        $messages = [
            'saved'          => [ 'success', __( 'Transação registrada com sucesso!', 'dps-finance-addon' ) ],
            'deleted'        => [ 'success', __( 'Transação excluída com sucesso!', 'dps-finance-addon' ) ],
            'partial_saved'  => [ 'success', __( 'Pagamento parcial registrado com sucesso!', 'dps-finance-addon' ) ],
            'status_updated' => [ 'success', __( 'Status atualizado com sucesso!', 'dps-finance-addon' ) ],
            'exported'       => [ 'success', __( 'Exportação concluída!', 'dps-finance-addon' ) ],
            // F2.2: FASE 2 - Mensagens para reenvio de link
            'no_payment_link' => [ 'error', __( 'Nenhum link de pagamento encontrado para esta transação.', 'dps-finance-addon' ) ],
            'no_phone'        => [ 'error', __( 'Cliente sem telefone cadastrado. Não é possível reenviar link.', 'dps-finance-addon' ) ],
        ];

        if ( ! isset( $messages[ $msg_key ] ) ) {
            return;
        }

        list( $type, $text ) = $messages[ $msg_key ];

        // Renderiza mensagem usando estrutura HTML consistente com DPS_Message_Helper
        $class = 'dps-alert';
        
        if ( $type === 'error' ) {
            $class .= ' dps-alert--danger';
        } elseif ( $type === 'success' ) {
            $class .= ' dps-alert--success';
        } elseif ( $type === 'warning' ) {
            $class .= ' dps-alert--pending';
        }
        
        // Define atributos de acessibilidade conforme o tipo de mensagem
        $role      = ( $type === 'error' ) ? 'alert' : 'status';
        $aria_live = ( $type === 'error' ) ? 'assertive' : 'polite';
        
        echo '<div class="' . esc_attr( $class ) . '" role="' . esc_attr( $role ) . '" aria-live="' . esc_attr( $aria_live ) . '">';
        echo esc_html( $text );
        echo '</div>';
    }
    
    /**
     * Renderiza alertas de pendências urgentes (vencidas e de hoje).
     * 
     * F2.1: FASE 2 - UX: Visibilidade imediata de cobranças urgentes.
     * 
     * @since 1.4.0
     */
    private function render_pending_alerts() {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $today = current_time( 'Y-m-d' );
        
        // Pendências vencidas (data < hoje E status em_aberto)
        $overdue = $wpdb->get_row( $wpdb->prepare( "
            SELECT COUNT(*) as count, SUM(valor) as total
            FROM {$table}
            WHERE status = 'em_aberto'
              AND tipo = 'receita'
              AND data < %s
        ", $today ) );
        
        // Pendências de hoje (data = hoje E status em_aberto)
        $today_pending = $wpdb->get_row( $wpdb->prepare( "
            SELECT COUNT(*) as count, SUM(valor) as total
            FROM {$table}
            WHERE status = 'em_aberto'
              AND tipo = 'receita'
              AND data = %s
        ", $today ) );
        
        // Só renderiza se houver pendências
        if ( ( $overdue && $overdue->count > 0 ) || ( $today_pending && $today_pending->count > 0 ) ) {
            echo '<div class="dps-finance-alerts" style="margin: 20px 0; display: flex; gap: 15px; flex-wrap: wrap;">';
            
            // Alerta de vencidas (crítico)
            if ( $overdue && $overdue->count > 0 ) {
                $overdue_value = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $overdue->total * 100 ) );
                $filter_url = add_query_arg( [
                    'tab' => 'financeiro',
                    'fin_status' => 'em_aberto',
                    'fin_end' => date( 'Y-m-d', strtotime( '-1 day' ) ),
                ], $this->get_current_url() );
                
                echo '<div class="dps-alert dps-alert--danger" style="flex: 1; min-width: 280px; padding: 15px; border-left: 4px solid #ef4444;">';
                echo '<div style="display: flex; align-items: center; gap: 10px;">';
                echo '<span style="font-size: 24px;">🚨</span>';
                echo '<div>';
                echo '<strong>' . sprintf( 
                    /* translators: %d: número de pendências */
                    _n( '%d pendência vencida', '%d pendências vencidas', $overdue->count, 'dps-finance-addon' ),
                    $overdue->count
                ) . '</strong><br>';
                echo '<span style="font-size: 18px; font-weight: 600;">R$ ' . esc_html( $overdue_value ) . '</span><br>';
                echo '<a href="' . esc_url( $filter_url ) . '" style="font-size: 13px;">' . esc_html__( 'Ver detalhes →', 'dps-finance-addon' ) . '</a>';
                echo '</div></div></div>';
            }
            
            // Alerta de hoje (atenção)
            if ( $today_pending && $today_pending->count > 0 ) {
                $today_value = DPS_Money_Helper::format_to_brazilian( (int) round( (float) $today_pending->total * 100 ) );
                $filter_url = add_query_arg( [
                    'tab' => 'financeiro',
                    'fin_status' => 'em_aberto',
                    'fin_start' => $today,
                    'fin_end' => $today,
                ], $this->get_current_url() );
                
                echo '<div class="dps-alert dps-alert--warning" style="flex: 1; min-width: 280px; padding: 15px; border-left: 4px solid #f59e0b;">';
                echo '<div style="display: flex; align-items: center; gap: 10px;">';
                echo '<span style="font-size: 24px;">⚠️</span>';
                echo '<div>';
                echo '<strong>' . sprintf(
                    /* translators: %d: número de pendências */
                    _n( '%d pendência de hoje', '%d pendências de hoje', $today_pending->count, 'dps-finance-addon' ),
                    $today_pending->count
                ) . '</strong><br>';
                echo '<span style="font-size: 18px; font-weight: 600;">R$ ' . esc_html( $today_value ) . '</span><br>';
                echo '<a href="' . esc_url( $filter_url ) . '" style="font-size: 13px;">' . esc_html__( 'Ver detalhes →', 'dps-finance-addon' ) . '</a>';
                echo '</div></div></div>';
            }
            
            echo '</div>';
        }
    }

    /**
     * Renderiza cards de resumo financeiro.
     *
     * @since 1.1.0
     * @param array $trans Lista de transações.
     */
    private function render_finance_summary( $trans ) {
        if ( empty( $trans ) ) {
            return;
        }

        $total_receitas  = 0;
        $total_despesas  = 0;
        $total_pendente  = 0;
        
        // Dados para gráfico mensal
        $monthly_data = [];

        foreach ( $trans as $tr ) {
            $valor = (float) $tr->valor;
            $month_key = date( 'Y-m', strtotime( $tr->data ) );
            
            if ( ! isset( $monthly_data[ $month_key ] ) ) {
                $monthly_data[ $month_key ] = [
                    'receitas' => 0,
                    'despesas' => 0,
                ];
            }
            
            if ( $tr->tipo === 'receita' ) {
                $total_receitas += $valor;
                $monthly_data[ $month_key ]['receitas'] += $valor;
                if ( $tr->status === 'em_aberto' ) {
                    $remaining = $valor - $this->get_partial_sum( $tr->id );
                    if ( $remaining > 0 ) {
                        $total_pendente += $remaining;
                    }
                }
            } else {
                $total_despesas += $valor;
                $monthly_data[ $month_key ]['despesas'] += $valor;
            }
        }

        $saldo = $total_receitas - $total_despesas;

        echo '<div class="dps-finance-summary">';

        // Card Receitas
        echo '<div class="dps-finance-card dps-finance-card-revenue">';
        echo '<h4>' . esc_html__( 'Receitas', 'dps-finance-addon' ) . '</h4>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $total_receitas * 100 ) ) ) . '</span>';
        echo '</div>';

        // Card Despesas
        echo '<div class="dps-finance-card dps-finance-card-expense">';
        echo '<h4>' . esc_html__( 'Despesas', 'dps-finance-addon' ) . '</h4>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $total_despesas * 100 ) ) ) . '</span>';
        echo '</div>';

        // Card Pendente
        echo '<div class="dps-finance-card dps-finance-card-pending">';
        echo '<h4>' . esc_html__( 'Pendente', 'dps-finance-addon' ) . '</h4>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $total_pendente * 100 ) ) ) . '</span>';
        echo '</div>';

        // Card Saldo
        echo '<div class="dps-finance-card dps-finance-card-balance">';
        echo '<h4>' . esc_html__( 'Saldo', 'dps-finance-addon' ) . '</h4>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $saldo * 100 ) ) ) . '</span>';
        echo '</div>';

        echo '</div>';
        
        // Gráfico de Receitas x Despesas Mensais
        if ( count( $monthly_data ) > 1 ) {
            $this->render_monthly_chart( $monthly_data );
        }
    }
    
    /**
     * Renderiza gráfico de receitas e despesas mensais.
     *
     * @since 1.3.0
     * @param array $monthly_data Dados agrupados por mês.
     */
    private function render_monthly_chart( $monthly_data ) {
        // Ordena por mês
        ksort( $monthly_data );
        
        // Prepara dados para Chart.js
        $labels    = [];
        $receitas  = [];
        $despesas  = [];
        
        $month_names = [
            '01' => __( 'Jan', 'dps-finance-addon' ),
            '02' => __( 'Fev', 'dps-finance-addon' ),
            '03' => __( 'Mar', 'dps-finance-addon' ),
            '04' => __( 'Abr', 'dps-finance-addon' ),
            '05' => __( 'Mai', 'dps-finance-addon' ),
            '06' => __( 'Jun', 'dps-finance-addon' ),
            '07' => __( 'Jul', 'dps-finance-addon' ),
            '08' => __( 'Ago', 'dps-finance-addon' ),
            '09' => __( 'Set', 'dps-finance-addon' ),
            '10' => __( 'Out', 'dps-finance-addon' ),
            '11' => __( 'Nov', 'dps-finance-addon' ),
            '12' => __( 'Dez', 'dps-finance-addon' ),
        ];
        
        // Limita aos últimos N meses para melhor visualização (configurável via constante)
        $chart_months = defined( 'DPS_FINANCE_CHART_MONTHS' ) ? DPS_FINANCE_CHART_MONTHS : 6;
        $monthly_data = array_slice( $monthly_data, -$chart_months, $chart_months, true );
        
        foreach ( $monthly_data as $month_key => $data ) {
            $parts = explode( '-', $month_key );
            $month_name = isset( $month_names[ $parts[1] ] ) ? $month_names[ $parts[1] ] : $parts[1];
            $labels[]   = $month_name . '/' . substr( $parts[0], 2 );
            $receitas[] = round( $data['receitas'], 2 );
            $despesas[] = round( $data['despesas'], 2 );
        }
        
        $chart_id = 'dps-finance-chart-' . wp_rand( 1000, 9999 );
        
        echo '<div class="dps-finance-chart-container">';
        echo '<h4>' . esc_html__( 'Receitas x Despesas Mensais', 'dps-finance-addon' ) . '</h4>';
        echo '<div class="dps-finance-chart-wrapper">';
        echo '<canvas id="' . esc_attr( $chart_id ) . '" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';
        
        // Script para inicializar o gráfico
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded');
                return;
            }
            
            var ctx = document.getElementById('<?php echo esc_js( $chart_id ); ?>');
            if (!ctx) return;
            
            // F3.1: FASE 3 - Gráfico de linhas para melhor visualização de evolução
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode( $labels ); ?>,
                    datasets: [
                        {
                            label: '<?php echo esc_js( __( 'Receitas', 'dps-finance-addon' ) ); ?>',
                            data: <?php echo wp_json_encode( $receitas ); ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: '<?php echo esc_js( __( 'Despesas', 'dps-finance-addon' ) ); ?>',
                            data: <?php echo wp_json_encode( $despesas ); ?>,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var value = context.parsed.y;
                                    return context.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: '<?php echo esc_js( __( 'Evolução Financeira - Últimos Meses', 'dps-finance-addon' ) ); ?>',
                            font: {
                                size: 16,
                                weight: 'normal'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderiza relatório DRE simplificado.
     *
     * @since 1.3.0
     * @param array $trans Array de transações.
     */
    private function render_dre_report( $trans ) {
        if ( empty( $trans ) ) {
            return;
        }
        
        // Agrupa por categoria e tipo
        $receitas_por_cat = [];
        $despesas_por_cat = [];
        $total_receitas   = 0;
        $total_despesas   = 0;
        
        foreach ( $trans as $tr ) {
            $valor    = (float) $tr->valor;
            $categoria = $tr->categoria ?: __( 'Sem categoria', 'dps-finance-addon' );
            
            if ( $tr->tipo === 'receita' ) {
                if ( ! isset( $receitas_por_cat[ $categoria ] ) ) {
                    $receitas_por_cat[ $categoria ] = 0;
                }
                $receitas_por_cat[ $categoria ] += $valor;
                $total_receitas += $valor;
            } else {
                if ( ! isset( $despesas_por_cat[ $categoria ] ) ) {
                    $despesas_por_cat[ $categoria ] = 0;
                }
                $despesas_por_cat[ $categoria ] += $valor;
                $total_despesas += $valor;
            }
        }
        
        $resultado = $total_receitas - $total_despesas;
        
        // Ordena por valor
        arsort( $receitas_por_cat );
        arsort( $despesas_por_cat );
        
        echo '<div class="dps-finance-dre">';
        echo '<h4>' . esc_html__( 'Demonstrativo de Resultado (DRE Simplificado)', 'dps-finance-addon' ) . '</h4>';
        echo '<table class="dps-table dps-dre-table">';
        
        // Receitas
        echo '<thead><tr><th colspan="2" class="dps-dre-header dps-dre-receitas">' . esc_html__( 'RECEITAS', 'dps-finance-addon' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $receitas_por_cat as $cat => $valor ) {
            echo '<tr>';
            echo '<td>' . esc_html( $cat ) . '</td>';
            echo '<td class="dps-dre-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $valor * 100 ) ) ) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="dps-dre-subtotal">';
        echo '<td><strong>' . esc_html__( 'Total Receitas', 'dps-finance-addon' ) . '</strong></td>';
        echo '<td class="dps-dre-value"><strong>R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $total_receitas * 100 ) ) ) . '</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        
        // Despesas
        echo '<thead><tr><th colspan="2" class="dps-dre-header dps-dre-despesas">' . esc_html__( 'DESPESAS', 'dps-finance-addon' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $despesas_por_cat as $cat => $valor ) {
            echo '<tr>';
            echo '<td>' . esc_html( $cat ) . '</td>';
            echo '<td class="dps-dre-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $valor * 100 ) ) ) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="dps-dre-subtotal">';
        echo '<td><strong>' . esc_html__( 'Total Despesas', 'dps-finance-addon' ) . '</strong></td>';
        echo '<td class="dps-dre-value"><strong>R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $total_despesas * 100 ) ) ) . '</strong></td>';
        echo '</tr>';
        echo '</tbody>';
        
        // Resultado
        $resultado_class = $resultado >= 0 ? 'dps-dre-positivo' : 'dps-dre-negativo';
        echo '<tfoot>';
        echo '<tr class="dps-dre-resultado ' . esc_attr( $resultado_class ) . '">';
        echo '<td><strong>' . esc_html__( 'RESULTADO DO PERÍODO', 'dps-finance-addon' ) . '</strong></td>';
        echo '<td class="dps-dre-value"><strong>R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $resultado * 100 ) ) ) . '</strong></td>';
        echo '</tr>';
        echo '</tfoot>';
        
        echo '</table>';
        echo '</div>';
    }

    /**
     * F3.4 - Calcula comparativo entre mês atual e mês anterior.
     * 
     * FASE 3 - Relatórios & Visão Gerencial
     * 
     * @since 1.4.1
     * @return array Array com dados do comparativo: current_month, previous_month, difference_value, difference_percent
     */
    private function calculate_monthly_comparison() {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        // Calcula datas para mês atual e anterior
        $current_month_start = date( 'Y-m-01' );
        $current_month_end   = date( 'Y-m-t' );
        
        $previous_month_start = date( 'Y-m-01', strtotime( '-1 month' ) );
        $previous_month_end   = date( 'Y-m-t', strtotime( '-1 month' ) );
        
        // Consulta receitas do mês atual (apenas pagas)
        $current_revenue = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'pago' 
             AND data >= %s 
             AND data <= %s",
            $current_month_start,
            $current_month_end
        ) );
        
        // Consulta receitas do mês anterior (apenas pagas)
        $previous_revenue = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(valor) FROM $table 
             WHERE tipo = 'receita' 
             AND status = 'pago' 
             AND data >= %s 
             AND data <= %s",
            $previous_month_start,
            $previous_month_end
        ) );
        
        $current_revenue  = (float) ( $current_revenue ?: 0 );
        $previous_revenue = (float) ( $previous_revenue ?: 0 );
        
        $difference_value = $current_revenue - $previous_revenue;
        $difference_percent = 0;
        
        if ( $previous_revenue > 0 ) {
            $difference_percent = ( $difference_value / $previous_revenue ) * 100;
        }
        
        return [
            'current_month'       => $current_revenue,
            'previous_month'      => $previous_revenue,
            'difference_value'    => $difference_value,
            'difference_percent'  => $difference_percent,
        ];
    }
    
    /**
     * F3.4 - Renderiza cards de comparativo mensal.
     * 
     * FASE 3 - Relatórios & Visão Gerencial
     * Exibe comparação entre receita do mês atual vs mês anterior.
     * 
     * @since 1.4.1
     */
    private function render_monthly_comparison() {
        $comparison = $this->calculate_monthly_comparison();
        
        $is_positive = $comparison['difference_value'] >= 0;
        $trend_class = $is_positive ? 'dps-trend-up' : 'dps-trend-down';
        $trend_icon  = $is_positive ? '↑' : '↓';
        $trend_color = $is_positive ? '#10b981' : '#ef4444';
        
        echo '<div class="dps-finance-comparison">';
        echo '<h4>' . esc_html__( 'Comparativo Mensal', 'dps-finance-addon' ) . '</h4>';
        echo '<div class="dps-finance-comparison-cards">';
        
        // Card Mês Atual
        echo '<div class="dps-finance-card dps-finance-card-current-month">';
        echo '<h5>' . esc_html__( 'Receita - Mês Atual', 'dps-finance-addon' ) . '</h5>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $comparison['current_month'] * 100 ) ) ) . '</span>';
        
        if ( abs( $comparison['difference_percent'] ) > 0.01 ) {
            echo '<p class="dps-finance-trend ' . esc_attr( $trend_class ) . '" style="color: ' . esc_attr( $trend_color ) . ';">';
            echo esc_html( $trend_icon ) . ' ';
            echo esc_html( abs( round( $comparison['difference_percent'], 1 ) ) ) . '% ';
            echo esc_html( $is_positive ? __( 'vs mês anterior', 'dps-finance-addon' ) : __( 'vs mês anterior', 'dps-finance-addon' ) );
            echo '</p>';
        }
        echo '</div>';
        
        // Card Mês Anterior (informativo)
        echo '<div class="dps-finance-card dps-finance-card-previous-month">';
        echo '<h5>' . esc_html__( 'Receita - Mês Anterior', 'dps-finance-addon' ) . '</h5>';
        echo '<span class="dps-finance-card-value">R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $comparison['previous_month'] * 100 ) ) ) . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * F3.5 - Obtém ranking dos top 10 clientes por receita.
     * 
     * FASE 3 - Relatórios & Visão Gerencial
     * 
     * @since 1.4.1
     * @param string $start_date Data inicial (Y-m-d) ou vazio para mês atual.
     * @param string $end_date   Data final (Y-m-d) ou vazio para mês atual.
     * @return array Array de objetos com cliente_id, cliente_nome, total_pago, qtde_transacoes
     */
    private function get_top_clients( $start_date = '', $end_date = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        
        // Se datas não informadas, usa mês atual
        if ( ! $start_date || ! $end_date ) {
            $start_date = date( 'Y-m-01' );
            $end_date   = date( 'Y-m-t' );
        }
        
        // Consulta agregada: agrupa por cliente_id e soma valor
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                cliente_id,
                SUM(valor) as total_pago,
                COUNT(*) as qtde_transacoes
             FROM $table
             WHERE tipo = 'receita'
             AND status = 'pago'
             AND data >= %s
             AND data <= %s
             AND cliente_id IS NOT NULL
             AND cliente_id > 0
             GROUP BY cliente_id
             ORDER BY total_pago DESC
             LIMIT 10",
            $start_date,
            $end_date
        ) );
        
        // Enriquece com nome do cliente
        $top_clients = [];
        foreach ( $results as $row ) {
            $cliente_post = get_post( $row->cliente_id );
            $cliente_nome = $cliente_post ? $cliente_post->post_title : __( 'Cliente não encontrado', 'dps-finance-addon' );
            
            $top_clients[] = (object) [
                'cliente_id'       => $row->cliente_id,
                'cliente_nome'     => $cliente_nome,
                'total_pago'       => (float) $row->total_pago,
                'qtde_transacoes'  => (int) $row->qtde_transacoes,
            ];
        }
        
        return $top_clients;
    }
    
    /**
     * F3.5 - Renderiza tabela de Top 10 clientes.
     * 
     * FASE 3 - Relatórios & Visão Gerencial
     * 
     * @since 1.4.1
     * @param string $start_date Data inicial do período.
     * @param string $end_date   Data final do período.
     */
    private function render_top_clients( $start_date = '', $end_date = '' ) {
        $top_clients = $this->get_top_clients( $start_date, $end_date );
        
        if ( empty( $top_clients ) ) {
            return;
        }
        
        echo '<div class="dps-finance-top-clients">';
        echo '<h4>' . esc_html__( 'Top 10 Clientes por Receita', 'dps-finance-addon' ) . '</h4>';
        
        echo '<table class="dps-table dps-top-clients-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( '#', 'dps-finance-addon' ) . '</th>';
        echo '<th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th>';
        echo '<th>' . esc_html__( 'Qtde. Atendimentos', 'dps-finance-addon' ) . '</th>';
        echo '<th>' . esc_html__( 'Valor Total', 'dps-finance-addon' ) . '</th>';
        echo '<th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $position = 1;
        foreach ( $top_clients as $client ) {
            echo '<tr>';
            echo '<td><strong>' . esc_html( $position ) . '</strong></td>';
            echo '<td>' . esc_html( $client->cliente_nome ) . '</td>';
            echo '<td>' . esc_html( $client->qtde_transacoes ) . '</td>';
            echo '<td>R$ ' . esc_html( DPS_Money_Helper::format_to_brazilian( (int) round( $client->total_pago * 100 ) ) ) . '</td>';
            
            // Link para filtrar transações deste cliente
            $filter_url = add_query_arg( [
                'fin_search_client' => urlencode( $client->cliente_nome ),
            ], '#financeiro' );
            
            echo '<td><a href="' . esc_url( $filter_url ) . '" class="button button-small">' . esc_html__( 'Ver transações', 'dps-finance-addon' ) . '</a></td>';
            echo '</tr>';
            $position++;
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Exporta transações para CSV.
     *
     * @since 1.1.0
     */
    private function export_transactions_csv() {
        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para exportar.', 'dps-finance-addon' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';

        // Aplica os mesmos filtros da listagem
        $start_date = isset( $_GET['fin_start'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_start'] ) ) : '';
        $end_date   = isset( $_GET['fin_end'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_end'] ) ) : '';
        $cat_filter = isset( $_GET['fin_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_cat'] ) ) : '';
        $range      = isset( $_GET['fin_range'] ) ? sanitize_text_field( wp_unslash( $_GET['fin_range'] ) ) : '';

        if ( $range === '7' || $range === '30' ) {
            $days = intval( $range );
            $end_date   = current_time( 'Y-m-d' );
            $start_date = date( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );
        }

        $where  = '1=1';
        $params = [];
        if ( $start_date ) {
            $where   .= ' AND data >= %s';
            $params[] = $start_date;
        }
        if ( $end_date ) {
            $where   .= ' AND data <= %s';
            $params[] = $end_date;
        }
        if ( $cat_filter !== '' ) {
            $where   .= ' AND categoria = %s';
            $params[] = $cat_filter;
        }

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY data DESC", $params );
        } else {
            $query = "SELECT * FROM $table ORDER BY data DESC";
        }

        $trans = $wpdb->get_results( $query );

        // Gera nome do arquivo
        $filename = 'transacoes_' . date( 'Y-m-d_H-i-s' ) . '.csv';

        // Headers para download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Abre o output stream
        $output = fopen( 'php://output', 'w' );

        // BOM para UTF-8 (ajuda Excel a reconhecer encoding)
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Cabeçalho do CSV
        fputcsv( $output, [
            __( 'ID', 'dps-finance-addon' ),
            __( 'Data', 'dps-finance-addon' ),
            __( 'Valor', 'dps-finance-addon' ),
            __( 'Categoria', 'dps-finance-addon' ),
            __( 'Tipo', 'dps-finance-addon' ),
            __( 'Status', 'dps-finance-addon' ),
            __( 'Cliente', 'dps-finance-addon' ),
            __( 'Descrição', 'dps-finance-addon' ),
        ], ';' );

        // Dados
        foreach ( $trans as $tr ) {
            $client_name = '';
            if ( $tr->cliente_id ) {
                $cpost = get_post( $tr->cliente_id );
                if ( $cpost ) {
                    $client_name = $cpost->post_title;
                }
            }

            $status_labels = [
                'em_aberto' => __( 'Em aberto', 'dps-finance-addon' ),
                'pago'      => __( 'Pago', 'dps-finance-addon' ),
                'cancelado' => __( 'Cancelado', 'dps-finance-addon' ),
            ];

            $tipo_labels = [
                'receita' => __( 'Receita', 'dps-finance-addon' ),
                'despesa' => __( 'Despesa', 'dps-finance-addon' ),
            ];

            fputcsv( $output, [
                $tr->id,
                $tr->data ? date_i18n( 'd/m/Y', strtotime( $tr->data ) ) : '',
                'R$ ' . DPS_Money_Helper::format_to_brazilian( (int) round( (float) $tr->valor * 100 ) ),
                $tr->categoria,
                isset( $tipo_labels[ $tr->tipo ] ) ? $tipo_labels[ $tr->tipo ] : $tr->tipo,
                isset( $status_labels[ $tr->status ] ) ? $status_labels[ $tr->status ] : $tr->status,
                $client_name,
                $tr->descricao,
            ], ';' );
        }

        fclose( $output );
    }

    /**
     * Renderiza navegação de paginação.
     *
     * @since 1.1.0
     * @param int $current_page Página atual.
     * @param int $total_pages  Total de páginas.
     * @param int $total_items  Total de itens.
     */
    private function render_pagination( $current_page, $total_pages, $total_items ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        // Constrói URL base mantendo filtros existentes
        $base_url = remove_query_arg( [ 'fin_page', 'dps_msg' ] );

        echo '<div class="dps-finance-pagination">';

        // Info de registros
        echo '<span class="dps-pagination-info">' . sprintf(
            /* translators: %1$d: total de itens, %2$d: página atual, %3$d: total de páginas */
            esc_html__( '%1$d registros - Página %2$d de %3$d', 'dps-finance-addon' ),
            $total_items,
            $current_page,
            $total_pages
        ) . '</span>';

        // Botão anterior
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( 'fin_page', $current_page - 1, $base_url ) . '#financeiro';
            echo '<a href="' . esc_url( $prev_url ) . '">&laquo; ' . esc_html__( 'Anterior', 'dps-finance-addon' ) . '</a>';
        } else {
            echo '<span class="disabled">&laquo; ' . esc_html__( 'Anterior', 'dps-finance-addon' ) . '</span>';
        }

        // Números de página (máximo 5 visíveis)
        $start_page = max( 1, $current_page - 2 );
        $end_page   = min( $total_pages, $current_page + 2 );

        if ( $start_page > 1 ) {
            $first_url = add_query_arg( 'fin_page', 1, $base_url ) . '#financeiro';
            echo '<a href="' . esc_url( $first_url ) . '">1</a>';
            if ( $start_page > 2 ) {
                echo '<span>...</span>';
            }
        }

        for ( $i = $start_page; $i <= $end_page; $i++ ) {
            if ( $i === $current_page ) {
                echo '<span class="current">' . esc_html( $i ) . '</span>';
            } else {
                $page_url = add_query_arg( 'fin_page', $i, $base_url ) . '#financeiro';
                echo '<a href="' . esc_url( $page_url ) . '">' . esc_html( $i ) . '</a>';
            }
        }

        if ( $end_page < $total_pages ) {
            if ( $end_page < $total_pages - 1 ) {
                echo '<span>...</span>';
            }
            $last_url = add_query_arg( 'fin_page', $total_pages, $base_url ) . '#financeiro';
            echo '<a href="' . esc_url( $last_url ) . '">' . esc_html( $total_pages ) . '</a>';
        }

        // Botão próximo
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( 'fin_page', $current_page + 1, $base_url ) . '#financeiro';
            echo '<a href="' . esc_url( $next_url ) . '">' . esc_html__( 'Próximo', 'dps-finance-addon' ) . ' &raquo;</a>';
        } else {
            echo '<span class="disabled">' . esc_html__( 'Próximo', 'dps-finance-addon' ) . ' &raquo;</span>';
        }

        echo '</div>';
    }

    /**
     * Calcula a soma de todos os pagamentos parciais registrados para uma transação.
     *
     * @param int $trans_id ID da transação
     * @return float Valor total pago até o momento
     */
    public function get_partial_sum( $trans_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_parcelas';
        $sum        = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM $table_name WHERE trans_id = %d", $trans_id ) );
        return $sum ? (float) $sum : 0.0;
    }

    /**
     * AJAX handler para buscar histórico de parcelas de uma transação.
     *
     * @since 1.2.0
     */
    public function ajax_get_partial_history() {
        // Verifica nonce
        if ( ! check_ajax_referer( 'dps_partial_history', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Ação de segurança inválida.', 'dps-finance-addon' ) ] );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão.', 'dps-finance-addon' ) ] );
        }

        $trans_id = isset( $_POST['trans_id'] ) ? intval( $_POST['trans_id'] ) : 0;
        if ( ! $trans_id ) {
            wp_send_json_error( [ 'message' => __( 'ID da transação inválido.', 'dps-finance-addon' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dps_parcelas';
        $trans_table = $wpdb->prefix . 'dps_transacoes';

        // Busca dados da transação
        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $trans_table WHERE id = %d", $trans_id ) );
        if ( ! $trans ) {
            wp_send_json_error( [ 'message' => __( 'Transação não encontrada.', 'dps-finance-addon' ) ] );
        }

        // Busca parcelas
        $parcelas = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE trans_id = %d ORDER BY data DESC",
            $trans_id
        ) );

        $formatted_parcelas = [];
        foreach ( $parcelas as $p ) {
            $formatted_parcelas[] = [
                'id'     => $p->id,
                'date'   => date_i18n( 'd/m/Y', strtotime( $p->data ) ),
                'value'  => DPS_Money_Helper::format_to_brazilian( (int) round( (float) $p->valor * 100 ) ),
                'method' => $this->get_payment_method_label( $p->metodo ),
            ];
        }

        $total_valor = (float) $trans->valor;
        $total_pago  = $this->get_partial_sum( $trans_id );
        $restante    = $total_valor - $total_pago;

        wp_send_json_success( [
            'parcelas'    => $formatted_parcelas,
            'total'       => DPS_Money_Helper::format_to_brazilian( (int) round( $total_valor * 100 ) ),
            'total_pago'  => DPS_Money_Helper::format_to_brazilian( (int) round( $total_pago * 100 ) ),
            'restante'    => DPS_Money_Helper::format_to_brazilian( (int) round( $restante * 100 ) ),
            'status'      => $trans->status,
        ] );
    }

    /**
     * AJAX handler para excluir uma parcela.
     *
     * @since 1.2.0
     */
    public function ajax_delete_partial() {
        // Verifica nonce
        if ( ! check_ajax_referer( 'dps_delete_partial', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Ação de segurança inválida.', 'dps-finance-addon' ) ] );
        }

        // Verifica permissão
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Você não tem permissão.', 'dps-finance-addon' ) ] );
        }

        $partial_id = isset( $_POST['partial_id'] ) ? intval( $_POST['partial_id'] ) : 0;
        if ( ! $partial_id ) {
            wp_send_json_error( [ 'message' => __( 'ID da parcela inválido.', 'dps-finance-addon' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dps_parcelas';
        $trans_table = $wpdb->prefix . 'dps_transacoes';

        // Busca a parcela para obter o trans_id
        $parcela = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $partial_id ) );
        if ( ! $parcela ) {
            wp_send_json_error( [ 'message' => __( 'Parcela não encontrada.', 'dps-finance-addon' ) ] );
        }

        $trans_id = $parcela->trans_id;

        // Exclui a parcela
        $deleted = $wpdb->delete( $table, [ 'id' => $partial_id ], [ '%d' ] );
        if ( ! $deleted ) {
            wp_send_json_error( [ 'message' => __( 'Erro ao excluir parcela.', 'dps-finance-addon' ) ] );
        }

        // Recalcula status da transação
        $total_valor = $wpdb->get_var( $wpdb->prepare( "SELECT valor FROM $trans_table WHERE id = %d", $trans_id ) );
        $total_pago  = $this->get_partial_sum( $trans_id );

        $total_valor_cents = $total_valor ? (int) round( (float) $total_valor * 100 ) : 0;
        $total_pago_cents  = (int) round( (float) $total_pago * 100 );

        // Se pagamento parcial foi removido e não está mais quitado, volta para em_aberto
        if ( $total_pago_cents < $total_valor_cents ) {
            $wpdb->update( $trans_table, [ 'status' => 'em_aberto' ], [ 'id' => $trans_id ], [ '%s' ], [ '%d' ] );
        }

        wp_send_json_success( [
            'message'    => __( 'Parcela excluída com sucesso.', 'dps-finance-addon' ),
            'total_pago' => DPS_Money_Helper::format_to_brazilian( $total_pago_cents ),
            'restante'   => DPS_Money_Helper::format_to_brazilian( $total_valor_cents - $total_pago_cents ),
        ] );
    }

    /**
     * Retorna o label do método de pagamento.
     *
     * @since 1.2.0
     * @param string $method Método de pagamento.
     * @return string Label traduzido.
     */
    private function get_payment_method_label( $method ) {
        $methods = [
            'pix'      => __( 'PIX', 'dps-finance-addon' ),
            'cartao'   => __( 'Cartão', 'dps-finance-addon' ),
            'dinheiro' => __( 'Dinheiro', 'dps-finance-addon' ),
            'outro'    => __( 'Outro', 'dps-finance-addon' ),
        ];
        return isset( $methods[ $method ] ) ? $methods[ $method ] : $method;
    }
} // end class DPS_Finance_Addon

} // end if ! class_exists

// Registra o hook de ativação do plugin
register_activation_hook( __FILE__, [ 'DPS_Finance_Addon', 'activate' ] );

/**
 * Inicializa o Finance Add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * de outros registros (prioridade 10).
 */
function dps_finance_init_addon() {
    if ( class_exists( 'DPS_Finance_Addon' ) && ! isset( $GLOBALS['dps_finance_addon'] ) ) {
        $GLOBALS['dps_finance_addon'] = new DPS_Finance_Addon();
    }
}
add_action( 'init', 'dps_finance_init_addon', 5 );