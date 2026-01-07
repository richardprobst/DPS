<?php
/**
 * Arquivo principal do Services Add-on (carregado via wrapper)
 *
 * NOTA: Este arquivo NÃO deve ter header de plugin WordPress para evitar duplicação.
 * O header oficial está em desi-pet-shower-services.php (arquivo wrapper).
 *
 * @package DPS_Services_Addon
 * @version 1.5.0
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Carrega a API pública de serviços
if ( ! class_exists( 'DPS_Services_API' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-dps-services-api.php';
}

/**
 * Classe principal do add-on de serviços
 */
class DPS_Services_Addon {

    /**
     * Helper para registrar o CPT de serviços.
     *
     * @var DPS_CPT_Helper|null
     */
    private $cpt_helper;

    public function __construct() {
        // Registra CPT (o helper será inicializado dentro do método register_service_cpt)
        add_action( 'init', [ $this, 'register_service_cpt' ] );
        // Adiciona abas e seções ao plugin base
        add_action( 'dps_base_nav_tabs_after_pets', [ $this, 'add_services_tab' ], 10, 1 );
        add_action( 'dps_base_sections_after_pets', [ $this, 'add_services_section' ], 10, 1 );
        // Manipula salvamento e exclusão de serviços
        add_action( 'init', [ $this, 'maybe_handle_service_request' ] );
        // Adiciona campos de serviços ao formulário de agendamento
        add_action( 'dps_base_appointment_fields', [ $this, 'appointment_service_fields' ], 10, 2 );
        // Salva dados de serviços no agendamento
        add_action( 'save_post_dps_agendamento', [ $this, 'save_appointment_services_meta' ], 10, 3 );

        // Salva instantâneo de preços históricos após criar/atualizar um agendamento
        add_action( 'dps_base_after_save_appointment', [ $this, 'store_booking_totals_snapshot' ], 10, 2 );

        // Quando um agendamento for editado e estiver finalizado, exibe
        // um checklist dos serviços selecionados e campos para extras.
        add_action( 'dps_base_appointment_fields', [ $this, 'appointment_finalization_fields' ], 20, 2 );
        // Salva o checklist e extras ao salvar o agendamento
        add_action( 'save_post_dps_agendamento', [ $this, 'save_appointment_finalization_meta' ], 20, 3 );
        // Enfileira scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        // Registra shortcodes
        add_shortcode( 'dps_services_catalog', [ $this, 'render_catalog_shortcode' ] );
        // Popula serviços padrões na ativação
        if ( defined( 'DPS_SERVICES_PLUGIN_FILE' ) ) {
            register_activation_hook( DPS_SERVICES_PLUGIN_FILE, [ $this, 'activate' ] );
        }
    }

    /**
     * Verifica se o usuário atual pode gerenciar serviços.
     *
     * @return bool
     */
    private function can_manage() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Calcula a URL de redirecionamento após uma ação de serviço.
     *
     * @param string $tab Aba que deve permanecer ativa.
     *
     * @return string
     */
    private function get_redirect_url( $tab = 'servicos' ) {
        $base = wp_get_referer();
        
        // Fallback: use get_queried_object_id() + get_permalink()
        if ( ! $base ) {
            $queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
            if ( $queried_id ) {
                $permalink = get_permalink( $queried_id );
                if ( $permalink && is_string( $permalink ) ) {
                    $base = $permalink;
                }
            }
        }
        
        // Secondary fallback: use current $post
        if ( ! $base ) {
            global $post;
            if ( isset( $post->ID ) ) {
                $permalink = get_permalink( $post->ID );
                if ( $permalink && is_string( $permalink ) ) {
                    $base = $permalink;
                }
            }
        }
        
        // Tertiary fallback: construct URL from REQUEST_URI
        if ( ! $base && isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
            if ( ! empty( $request_uri ) ) {
                $base = esc_url_raw( home_url( $request_uri ) );
            }
        }
        
        // Final fallback: home_url (should rarely be reached)
        if ( ! $base ) {
            $base = home_url();
        }

        $base = remove_query_arg(
            [ 'dps_service_delete', 'dps_service_action', 'service_id', 'dps_service_nonce', 'dps_toggle_service', 'dps_duplicate_service', 'dps_edit', 'id', '_wpnonce' ],
            $base
        );

        if ( $tab ) {
            $base = add_query_arg( 'tab', $tab, $base );
        }

        return $base;
    }

    /**
     * Registra o tipo de post personalizado para serviços
     */
    public function register_service_cpt() {
        // Inicializa o CPT helper se necessário
        if ( ! $this->cpt_helper ) {
            if ( ! class_exists( 'DPS_CPT_Helper' ) && defined( 'DPS_BASE_DIR' ) ) {
                require_once DPS_BASE_DIR . 'includes/class-dps-cpt-helper.php';
            }

            if ( class_exists( 'DPS_CPT_Helper' ) ) {
                $this->cpt_helper = new DPS_CPT_Helper(
                    'dps_service',
                    [
                        'name'          => __( 'Serviços', 'dps-services-addon' ),
                        'singular_name' => __( 'Serviço', 'dps-services-addon' ),
                    ],
                    [
                        'public'       => false,
                        'show_ui'      => false,
                        'supports'     => [ 'title' ],
                        'hierarchical' => false,
                    ]
                );
            }
        }

        if ( $this->cpt_helper ) {
            $this->cpt_helper->register();
        }
    }

    /**
     * Popula serviços padrão e extras quando o plugin é ativado
     */
    public function activate() {
        // Cria serviços padrão se não existirem
        $default_services = [
            [ 'name' => 'Banho', 'price' => 0, 'type' => 'padrao', 'category' => '' ],
            [ 'name' => 'Banho e Tosa', 'price' => 0, 'type' => 'padrao', 'category' => '' ],
            // Preparação da pelagem
            [ 'name' => 'Remoção de nós | nível leve', 'price' => 0, 'type' => 'extra', 'category' => 'preparacao_pelagem' ],
            [ 'name' => 'Remoção de nós | nível moderado', 'price' => 0, 'type' => 'extra', 'category' => 'preparacao_pelagem' ],
            [ 'name' => 'Remoção de nós | nível severo', 'price' => 0, 'type' => 'extra', 'category' => 'preparacao_pelagem' ],
            // Opções de tosa
            [ 'name' => 'Tosa feita com máquina', 'price' => 0, 'type' => 'extra', 'category' => 'opcoes_tosa' ],
            [ 'name' => 'Tosa feita na tesoura', 'price' => 0, 'type' => 'extra', 'category' => 'opcoes_tosa' ],
            [ 'name' => 'Tosa da Raça', 'price' => 0, 'type' => 'extra', 'category' => 'opcoes_tosa' ],
            [ 'name' => 'Tosa Bebê', 'price' => 0, 'type' => 'extra', 'category' => 'opcoes_tosa' ],
            [ 'name' => 'Tosa higienica', 'price' => 0, 'type' => 'extra', 'category' => 'opcoes_tosa' ],
            // Tratamento
            [ 'name' => 'Banho terapêutico (Ozônio)', 'price' => 0, 'type' => 'extra', 'category' => 'tratamento' ],
            // Cuidados adicionais
            [ 'name' => 'Escovação dental', 'price' => 0, 'type' => 'extra', 'category' => 'cuidados' ],
            // Tratamento da pelagem e pele
            [ 'name' => 'Hidratação', 'price' => 0, 'type' => 'extra', 'category' => 'pelagem' ],
            [ 'name' => 'Restauração', 'price' => 0, 'type' => 'extra', 'category' => 'pelagem' ],
        ];
        foreach ( $default_services as $srv ) {
            // Verifica se já existe
            $exists = get_posts( [
                'post_type'      => 'dps_service',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'title'          => $srv['name'],
                'meta_query'     => [
                    [ 'key' => 'service_type', 'value' => $srv['type'], 'compare' => '=' ],
                ],
            ] );
            if ( empty( $exists ) ) {
                $post_id = wp_insert_post( [
                    'post_type'   => 'dps_service',
                    'post_title'  => $srv['name'],
                    'post_status' => 'publish',
                ] );
                if ( $post_id ) {
                    update_post_meta( $post_id, 'service_price', $srv['price'] );
                    update_post_meta( $post_id, 'service_type', $srv['type'] );
                    update_post_meta( $post_id, 'service_category', $srv['category'] );
                    // Define serviço como ativo por padrão
                    update_post_meta( $post_id, 'service_active', '1' );
                    // Define duração padrão em minutos (pode ser ajustado manualmente depois)
                    $default_duration = 0;
                    if ( 'Banho' === $srv['name'] ) {
                        $default_duration = 60;
                    } elseif ( 'Banho e Tosa' === $srv['name'] ) {
                        $default_duration = 90;
                    } else {
                        $default_duration = 15;
                    }
                    update_post_meta( $post_id, 'service_duration', $default_duration );
                    // Copia preço e duração padrão para variações por porte
                    update_post_meta( $post_id, 'service_price_small', $srv['price'] );
                    update_post_meta( $post_id, 'service_price_medium', $srv['price'] );
                    update_post_meta( $post_id, 'service_price_large', $srv['price'] );
                    update_post_meta( $post_id, 'service_duration_small', $default_duration );
                    update_post_meta( $post_id, 'service_duration_medium', $default_duration );
                    update_post_meta( $post_id, 'service_duration_large', $default_duration );
                }
            }
        }
    }

    /**
     * Adiciona uma aba de navegação para serviços no menu do plugin base
     *
     * @param bool $visitor_only Indica se usuário é visitante (não deve ver a aba)
     */
    public function add_services_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="servicos">' . esc_html__( 'Serviços', 'dps-services-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de serviços ao plugin base
     *
     * @param bool $visitor_only Indica se usuário é visitante
     */
    public function add_services_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo $this->section_services();
    }

    /**
     * Renderiza a seção de serviços: formulário e lista
     */
    private function section_services() {
        // Processa edição
        $edit_id = ( isset( $_GET['dps_edit'] ) && 'service' === $_GET['dps_edit'] && isset( $_GET['id'] ) ) ? intval( $_GET['id'] ) : 0;
        $editing = null;
        $meta    = [];
        if ( $edit_id ) {
            $editing = get_post( $edit_id );
            if ( $editing && 'dps_service' === $editing->post_type ) {
                $meta = [
                    'price'        => get_post_meta( $edit_id, 'service_price', true ),
                    'type'         => get_post_meta( $edit_id, 'service_type', true ),
                    'category'     => get_post_meta( $edit_id, 'service_category', true ),
                    'duration'     => get_post_meta( $edit_id, 'service_duration', true ),
                    'active'       => get_post_meta( $edit_id, 'service_active', true ),
                    'required_staff_type' => get_post_meta( $edit_id, 'required_staff_type', true ),
                    // Variações por porte
                    'price_small'  => get_post_meta( $edit_id, 'service_price_small', true ),
                    'price_medium' => get_post_meta( $edit_id, 'service_price_medium', true ),
                    'price_large'  => get_post_meta( $edit_id, 'service_price_large', true ),
                    'duration_small'  => get_post_meta( $edit_id, 'service_duration_small', true ),
                    'duration_medium' => get_post_meta( $edit_id, 'service_duration_medium', true ),
                    'duration_large'  => get_post_meta( $edit_id, 'service_duration_large', true ),
                ];
            }
        }
        // Lista de serviços existentes
        $services = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        
        // Calcula estatísticas dos serviços
        $summary = [
            'total'    => count( $services ),
            'active'   => 0,
            'inactive' => 0,
            'padrao'   => 0,
            'extra'    => 0,
            'package'  => 0,
        ];
        foreach ( $services as $srv ) {
            $active = get_post_meta( $srv->ID, 'service_active', true );
            $type   = get_post_meta( $srv->ID, 'service_type', true );
            if ( '0' !== $active ) {
                $summary['active']++;
            } else {
                $summary['inactive']++;
            }
            if ( isset( $summary[ $type ] ) ) {
                $summary[ $type ]++;
            }
        }
        
        // Tipos e categorias
        $types = [
            'padrao'  => __( 'Serviço padrão', 'dps-services-addon' ),
            'extra'   => __( 'Serviço extra', 'dps-services-addon' ),
            'package' => __( 'Pacote de serviços', 'dps-services-addon' ),
        ];
        $categories = [
            'banho'               => __( 'Banho', 'dps-services-addon' ),
            'tosa'                => __( 'Tosa', 'dps-services-addon' ),
            'extras'              => __( 'Extras', 'dps-services-addon' ),
            'preparacao_pelagem' => __( 'Preparação da pelagem', 'dps-services-addon' ),
            'opcoes_tosa'        => __( 'Opções de tosa', 'dps-services-addon' ),
            'tratamento'         => __( 'Tratamento', 'dps-services-addon' ),
            'cuidados'           => __( 'Cuidados adicionais', 'dps-services-addon' ),
            'pelagem'            => __( 'Tratamento da pelagem e pele', 'dps-services-addon' ),
        ];
        $base_url = DPS_URL_Builder::safe_get_permalink();
        ob_start();
        echo '<div class="dps-section dps-services-section" id="dps-section-servicos">';
        
        // Header padronizado como nas abas Clientes e Pets
        echo '<h2 class="dps-section-title">';
        echo '<span class="dps-section-title__icon">🧼</span>';
        echo esc_html__( 'Gestão de Serviços', 'dps-services-addon' );
        echo '</h2>';
        
        // Exibe mensagens de feedback
        if ( class_exists( 'DPS_Message_Helper' ) ) {
            echo DPS_Message_Helper::display_messages();
        }
        
        // Modo de edição: formulário em card único
        if ( $edit_id && $editing ) {
            $cancel_url = add_query_arg( 'tab', 'servicos', $base_url );
            echo '<div class="dps-surface dps-surface--info dps-services-edit-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>✏️</span>';
            echo esc_html__( 'Editar Serviço', 'dps-services-addon' );
            echo '</div>';
            echo '<p class="dps-surface__description">';
            printf(
                /* translators: %s: Nome do serviço sendo editado */
                esc_html__( 'Editando: %s', 'dps-services-addon' ),
                esc_html( $editing->post_title )
            );
            echo ' <a href="' . esc_url( $cancel_url ) . '" class="dps-cancel-edit">';
            echo esc_html__( 'Cancelar edição', 'dps-services-addon' );
            echo '</a>';
            echo '</p>';
            
            // Renderiza o formulário de edição
            $this->render_service_form( $edit_id, $editing, $meta, $types, $categories );
            
            echo '</div>'; // .dps-services-edit-card
        } else {
            // Modo normal: layout grid similar à aba Clientes
            echo '<div class="dps-section-grid">';
            
            // Card de Informações (padrão visual da aba Clientes)
            echo '<div class="dps-surface dps-surface--info dps-services-status-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>🗂️</span>';
            echo esc_html__( 'Informações', 'dps-services-addon' );
            echo '</div>';
            
            // Painel de estatísticas (seguindo o padrão da aba Clientes)
            echo '<ul class="dps-inline-stats dps-inline-stats--panel">';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--scheduled">' . esc_html__( 'Total de serviços', 'dps-services-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Serviços cadastrados no sistema', 'dps-services-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $summary['total'] ) . '</strong>';
            echo '</li>';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--paid">' . esc_html__( 'Serviços ativos', 'dps-services-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Disponíveis para agendamento', 'dps-services-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $summary['active'] ) . '</strong>';
            echo '</li>';
            echo '<li>';
            echo '<div class="dps-inline-stats__label">';
            echo '<span class="dps-status-badge dps-status-badge--pending">' . esc_html__( 'Serviços inativos', 'dps-services-addon' ) . '</span>';
            echo '<small>' . esc_html__( 'Temporariamente indisponíveis', 'dps-services-addon' ) . '</small>';
            echo '</div>';
            echo '<strong class="dps-inline-stats__value">' . esc_html( (string) $summary['inactive'] ) . '</strong>';
            echo '</li>';
            echo '</ul>';
            
            // Ações (seguindo o padrão da aba Clientes)
            echo '<div class="dps-actions dps-actions--inline">';
            echo '<a class="button button-primary" href="#dps-services-form-section">';
            echo esc_html__( 'Cadastrar novo serviço', 'dps-services-addon' );
            echo '</a>';
            echo '</div>';
            echo '</div>'; // .dps-services-status-card
            
            // Card de Lista de Serviços
            echo '<div class="dps-surface dps-surface--neutral dps-services-list-card">';
            echo '<div class="dps-surface__title">';
            echo '<span>📋</span>';
            echo esc_html__( 'Lista de serviços', 'dps-services-addon' );
            echo '</div>';
            echo '<div class="dps-services-list-card__body">';
            
            // Renderiza listagem de serviços
            $this->render_services_list( $services, $types, $categories, $base_url );
            
            echo '</div>'; // .dps-services-list-card__body
            echo '</div>'; // .dps-services-list-card
            echo '</div>'; // .dps-section-grid
            
            // Formulário de Cadastro ao final (apenas em modo não-edição)
            echo '<div class="dps-surface dps-surface--info dps-services-form-section" id="dps-services-form-section">';
            echo '<div class="dps-surface__title">';
            echo '<span>➕</span>';
            echo esc_html__( 'Cadastrar novo serviço', 'dps-services-addon' );
            echo '</div>';
            
            // Renderiza o formulário de cadastro
            $this->render_service_form( 0, null, [], $types, $categories );
            
            echo '</div>'; // .dps-services-form-section
        }
        
        echo '</div>'; // .dps-section
        return ob_get_clean();
    }
    
    /**
     * Renderiza o formulário de cadastro/edição de serviço.
     *
     * @param int         $edit_id    ID do serviço sendo editado (0 para novo).
     * @param WP_Post|null $editing   Post do serviço sendo editado.
     * @param array       $meta       Metadados do serviço.
     * @param array       $types      Tipos de serviço disponíveis.
     * @param array       $categories Categorias disponíveis.
     */
    private function render_service_form( $edit_id, $editing, $meta, $types, $categories ) {
        $form_title = $edit_id 
            ? sprintf( __( 'Editar Serviço: %s', 'dps-services-addon' ), esc_html( $editing->post_title ) )
            : __( 'Novo Serviço', 'dps-services-addon' );
        
        echo '<form method="post" class="dps-form dps-services-form">';
        echo '<input type="hidden" name="dps_service_action" value="save_service">';
        wp_nonce_field( 'dps_service_action', 'dps_service_nonce' );
        if ( $edit_id ) {
            echo '<input type="hidden" name="service_id" value="' . esc_attr( $edit_id ) . '">';
        }

        // === FIELDSET: Informações Básicas ===
        echo '<fieldset class="dps-fieldset dps-fieldset-basic">';
        echo '<legend>' . esc_html__( 'Informações Básicas', 'dps-services-addon' ) . '</legend>';
        
        // Grid de 2 colunas para campos básicos
        echo '<div class="dps-form-grid">';
        
        // Nome do serviço
        $name_val = $editing ? $editing->post_title : '';
        echo '<div class="dps-form-field dps-form-field-full">';
        echo '<label for="service_name">' . esc_html__( 'Nome do serviço', 'dps-services-addon' ) . ' <span class="required">*</span></label>';
        echo '<input type="text" id="service_name" name="service_name" value="' . esc_attr( $name_val ) . '" required placeholder="' . esc_attr__( 'Ex: Banho Completo', 'dps-services-addon' ) . '">';
        echo '</div>';
        
        // Tipo de serviço
        $type_val = $meta['type'] ?? '';
        echo '<div class="dps-form-field">';
        echo '<label for="service_type">' . esc_html__( 'Tipo de serviço', 'dps-services-addon' ) . ' <span class="required">*</span></label>';
        echo '<select id="service_type" name="service_type" required>';
        echo '<option value="">' . esc_html__( 'Selecione...', 'dps-services-addon' ) . '</option>';
        foreach ( $types as $val => $label ) {
            $sel = ( $val === $type_val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Categoria (somente para extras)
        $cat_val = $meta['category'] ?? '';
        echo '<div class="dps-form-field" id="dps-category-field">';
        echo '<label for="service_category">' . esc_html__( 'Categoria', 'dps-services-addon' ) . '</label>';
        echo '<select id="service_category" name="service_category">';
        echo '<option value="">' . esc_html__( 'Nenhuma', 'dps-services-addon' ) . '</option>';
        foreach ( $categories as $val => $label ) {
            $sel = ( $val === $cat_val ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Tipo de profissional requerido
        $required_staff = $meta['required_staff_type'] ?? 'any';
        $staff_type_options = [
            'any'      => __( 'Qualquer profissional', 'dps-services-addon' ),
            'groomer'  => __( 'Groomer (tosador)', 'dps-services-addon' ),
            'banhista' => __( 'Banhista', 'dps-services-addon' ),
        ];
        // Adiciona tipos extras do Groomers Add-on se disponível
        if ( class_exists( 'DPS_Groomers_Addon' ) && method_exists( 'DPS_Groomers_Addon', 'get_staff_types' ) ) {
            $extra_types = DPS_Groomers_Addon::get_staff_types();
            foreach ( $extra_types as $key => $label ) {
                if ( ! isset( $staff_type_options[ $key ] ) && 'recepcao' !== $key ) {
                    $staff_type_options[ $key ] = $label;
                }
            }
        }
        echo '<div class="dps-form-field">';
        echo '<label for="required_staff_type">' . esc_html__( 'Profissional requerido', 'dps-services-addon' ) . '</label>';
        echo '<select id="required_staff_type" name="required_staff_type">';
        foreach ( $staff_type_options as $val => $label ) {
            $sel = ( $val === $required_staff ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<span class="dps-field-description">' . esc_html__( 'Define qual tipo de profissional pode executar este serviço.', 'dps-services-addon' ) . '</span>';
        echo '</div>';
        
        // Status Ativo/Inativo
        $active_val = $meta['active'] ?? '';
        $checked    = ( '0' === $active_val ) ? '' : 'checked';
        echo '<div class="dps-form-field">';
        echo '<label class="dps-checkbox-label">';
        echo '<input type="checkbox" name="service_active" value="1" ' . $checked . '>';
        echo '<span>' . esc_html__( 'Serviço ativo', 'dps-services-addon' ) . '</span>';
        echo '</label>';
        echo '<span class="dps-field-description">' . esc_html__( 'Serviços inativos não aparecem para seleção nos agendamentos.', 'dps-services-addon' ) . '</span>';
        echo '</div>';
        
        echo '</div>'; // .dps-form-grid
        echo '</fieldset>';

        // === FIELDSET: Valores por Porte ===
        echo '<fieldset class="dps-fieldset dps-fieldset-pricing">';
        echo '<legend>' . esc_html__( 'Valores por Porte', 'dps-services-addon' ) . '</legend>';
        echo '<p class="dps-fieldset-description">' . esc_html__( 'Informe os valores para cada porte de pet. O menor valor será usado como referência geral.', 'dps-services-addon' ) . '</p>';
        
        $price_small    = $meta['price_small'] ?? ( $meta['price'] ?? '' );
        $price_medium   = $meta['price_medium'] ?? '';
        $price_large    = $meta['price_large'] ?? '';
        
        echo '<div class="dps-porte-grid">';
        echo '<div class="dps-porte-item">';
        echo '<label for="service_price_small">' . esc_html__( 'Pequeno', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-prefix">';
        echo '<span>R$</span>';
        echo '<input type="number" id="service_price_small" name="service_price_small" step="0.01" min="0" value="' . esc_attr( $price_small ) . '" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-porte-item">';
        echo '<label for="service_price_medium">' . esc_html__( 'Médio', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-prefix">';
        echo '<span>R$</span>';
        echo '<input type="number" id="service_price_medium" name="service_price_medium" step="0.01" min="0" value="' . esc_attr( $price_medium ) . '" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-porte-item">';
        echo '<label for="service_price_large">' . esc_html__( 'Grande', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-prefix">';
        echo '<span>R$</span>';
        echo '<input type="number" id="service_price_large" name="service_price_large" step="0.01" min="0" value="' . esc_attr( $price_large ) . '" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // .dps-porte-grid
        echo '</fieldset>';

        // === FIELDSET: Duração por Porte ===
        echo '<fieldset class="dps-fieldset dps-fieldset-duration">';
        echo '<legend>' . esc_html__( 'Duração por Porte', 'dps-services-addon' ) . '</legend>';
        echo '<p class="dps-fieldset-description">' . esc_html__( 'Tempo estimado de execução do serviço em minutos. Usado para cálculo de agenda.', 'dps-services-addon' ) . '</p>';
        
        $dur_small  = $meta['duration_small'] ?? ( $meta['duration'] ?? '' );
        $dur_medium = $meta['duration_medium'] ?? '';
        $dur_large  = $meta['duration_large'] ?? '';
        
        echo '<div class="dps-porte-grid">';
        echo '<div class="dps-porte-item">';
        echo '<label for="service_duration_small">' . esc_html__( 'Pequeno', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-suffix">';
        echo '<input type="number" id="service_duration_small" name="service_duration_small" step="5" min="0" value="' . esc_attr( $dur_small ) . '" placeholder="0">';
        echo '<span>min</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-porte-item">';
        echo '<label for="service_duration_medium">' . esc_html__( 'Médio', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-suffix">';
        echo '<input type="number" id="service_duration_medium" name="service_duration_medium" step="5" min="0" value="' . esc_attr( $dur_medium ) . '" placeholder="0">';
        echo '<span>min</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="dps-porte-item">';
        echo '<label for="service_duration_large">' . esc_html__( 'Grande', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-suffix">';
        echo '<input type="number" id="service_duration_large" name="service_duration_large" step="5" min="0" value="' . esc_attr( $dur_large ) . '" placeholder="0">';
        echo '<span>min</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // .dps-porte-grid
        echo '</fieldset>';

        // === FIELDSET: Configuração de Pacote (oculto por padrão) ===
        $package_items = [];
        $package_discount = '';
        $package_fixed_price = '';
        if ( $edit_id ) {
            $package_items = get_post_meta( $edit_id, 'service_package_items', true );
            if ( ! is_array( $package_items ) ) {
                $package_items = [];
            }
            $package_discount = get_post_meta( $edit_id, 'service_package_discount', true );
            $package_fixed_price = get_post_meta( $edit_id, 'service_package_fixed_price', true );
        }
        
        echo '<fieldset class="dps-fieldset dps-fieldset-package" id="dps-package-items-wrap" style="display:none;">';
        echo '<legend>' . esc_html__( 'Configuração do Pacote', 'dps-services-addon' ) . '</legend>';
        
        // Lista de serviços para inclusão no pacote
        $all_services = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        
        echo '<div class="dps-form-field">';
        echo '<label>' . esc_html__( 'Serviços incluídos no pacote', 'dps-services-addon' ) . '</label>';
        echo '<select name="service_package_items[]" multiple size="5" class="dps-multiselect">';
        foreach ( $all_services as $asrv ) {
            if ( $edit_id && $asrv->ID === $edit_id ) {
                continue;
            }
            $sel = in_array( $asrv->ID, $package_items, false ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $asrv->ID ) . '" ' . $sel . '>' . esc_html( $asrv->post_title ) . '</option>';
        }
        echo '</select>';
        echo '<span class="dps-field-description">' . esc_html__( 'Segure Ctrl/Cmd para selecionar múltiplos serviços.', 'dps-services-addon' ) . '</span>';
        echo '</div>';
        
        // Preço do pacote
        echo '<div class="dps-package-pricing-options">';
        echo '<p class="dps-fieldset-description">' . esc_html__( 'Defina um desconto percentual OU um preço fixo para o pacote.', 'dps-services-addon' ) . '</p>';
        
        echo '<div class="dps-form-grid">';
        echo '<div class="dps-form-field">';
        echo '<label for="service_package_discount">' . esc_html__( 'Desconto (%)', 'dps-services-addon' ) . '</label>';
        echo '<input type="number" id="service_package_discount" name="service_package_discount" step="1" min="0" max="100" value="' . esc_attr( $package_discount ) . '" placeholder="10">';
        echo '</div>';
        
        echo '<div class="dps-form-field dps-field-or">';
        echo '<span class="dps-or-label">' . esc_html__( 'ou', 'dps-services-addon' ) . '</span>';
        echo '</div>';
        
        echo '<div class="dps-form-field">';
        echo '<label for="service_package_fixed_price">' . esc_html__( 'Preço fixo', 'dps-services-addon' ) . '</label>';
        echo '<div class="dps-input-prefix">';
        echo '<span>R$</span>';
        echo '<input type="number" id="service_package_fixed_price" name="service_package_fixed_price" step="0.01" min="0" value="' . esc_attr( $package_fixed_price ) . '" placeholder="0,00">';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // .dps-form-grid
        echo '</div>'; // .dps-package-pricing-options
        echo '</fieldset>';
        
        // === Botões do formulário ===
        echo '<div class="dps-form-actions">';
        $btn_text = $edit_id ? esc_html__( 'Atualizar Serviço', 'dps-services-addon' ) : esc_html__( 'Salvar Serviço', 'dps-services-addon' );
        echo '<button type="submit" class="button button-primary">' . $btn_text . '</button>';
        if ( $edit_id ) {
            $cancel_url = remove_query_arg( [ 'dps_edit', 'id' ] );
            $cancel_url = add_query_arg( 'tab', 'servicos', $cancel_url );
            echo '<a href="' . esc_url( $cancel_url ) . '" class="button button-secondary">' . esc_html__( 'Cancelar', 'dps-services-addon' ) . '</a>';
        }
        echo '</div>';
        
        echo '</form>';

        // Script para ocultar/mostrar campos de categoria e pacote dependendo do tipo
        // Inclui prevenção de duplo clique no submit, validação de preços e pesquisa na listagem
        echo '<script>(function($){$(document).ready(function(){
            function toggleFields(){
                var type = $("#service_type").val();
                if(type === "extra") {
                    $("#dps-category-field").show();
                    $("#dps-package-items-wrap").hide();
                } else if(type === "package") {
                    $("#dps-category-field").hide();
                    $("#dps-package-items-wrap").show();
                } else {
                    $("#dps-category-field").hide();
                    $("#dps-package-items-wrap").hide();
                }
            }
            toggleFields();
            $(document).on("change", "#service_type", toggleFields);
            // Prevenção de duplo clique e validação no formulário de serviços
            $(".dps-services-form").on("submit", function(e){
                var $form = $(this);
                var $btn = $form.find("button[type=submit]");
                // Evita duplo clique
                if($form.data("submitting")) {
                    e.preventDefault();
                    return false;
                }
                // Validação client-side: pelo menos um preço deve ser informado para não-pacotes
                var type = $("#service_type").val();
                if(type !== "package") {
                    var priceSmall = parseFloat($("#service_price_small").val()) || 0;
                    var priceMedium = parseFloat($("#service_price_medium").val()) || 0;
                    var priceLarge = parseFloat($("#service_price_large").val()) || 0;
                    if(priceSmall === 0 && priceMedium === 0 && priceLarge === 0) {
                        alert("' . esc_js( __( 'Informe pelo menos um valor de preço para o serviço.', 'dps-services-addon' ) ) . '");
                        e.preventDefault();
                        return false;
                    }
                }
                $form.data("submitting", true);
                $btn.prop("disabled", true).addClass("dps-btn-submitting");
            });
            // Reset form state on back/forward navigation (bfcache)
            $(window).on("pageshow", function(event){
                if(event.originalEvent.persisted){
                    $(".dps-services-form").data("submitting", false);
                    $(".dps-services-form button[type=submit]").prop("disabled", false).removeClass("dps-btn-submitting");
                }
            });
            // Pesquisa simples na listagem de serviços
            $(".dps-search-input").on("keyup", function(){
                var term = $(this).val().toLowerCase();
                $(".dps-services-table tbody tr").each(function(){
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) >= 0);
                });
            });
        });})(jQuery);</script>';
    }
    
    /**
     * Renderiza a listagem de serviços.
     *
     * @param array  $services   Lista de posts de serviços.
     * @param array  $types      Tipos de serviço.
     * @param array  $categories Categorias de serviço.
     * @param string $base_url   URL base da página.
     */
    private function render_services_list( $services, $types, $categories, $base_url ) {
        // Toolbar de busca
        echo '<div class="dps-list-toolbar">';
        echo '<div class="dps-search-wrapper">';
        echo '<input type="text" class="dps-search-input" placeholder="' . esc_attr__( 'Buscar serviço...', 'dps-services-addon' ) . '">';
        echo '</div>';
        if ( $services ) {
            $total_services = count( $services );
            $active_count = 0;
            foreach ( $services as $srv ) {
                if ( '0' !== get_post_meta( $srv->ID, 'service_active', true ) ) {
                    $active_count++;
                }
            }
            echo '<div class="dps-list-stats">';
            echo '<span class="dps-stat-total">' . sprintf( esc_html__( '%d serviços', 'dps-services-addon' ), $total_services ) . '</span>';
            echo '<span class="dps-stat-active">' . sprintf( esc_html__( '(%d ativos)', 'dps-services-addon' ), $active_count ) . '</span>';
            echo '</div>';
        }
        echo '</div>'; // .dps-list-toolbar
        
        if ( ! empty( $services ) ) {
            echo '<div class="dps-table-wrapper">';
            echo '<table class="dps-table dps-services-table"><thead><tr>';
            echo '<th class="dps-col-name">' . esc_html__( 'Serviço', 'dps-services-addon' ) . '</th>';
            echo '<th class="dps-col-type">' . esc_html__( 'Tipo', 'dps-services-addon' ) . '</th>';
            echo '<th class="dps-col-price">' . esc_html__( 'Preço', 'dps-services-addon' ) . '</th>';
            echo '<th class="dps-col-duration">' . esc_html__( 'Duração', 'dps-services-addon' ) . '</th>';
            echo '<th class="dps-col-status">' . esc_html__( 'Status', 'dps-services-addon' ) . '</th>';
            echo '<th class="dps-col-actions">' . esc_html__( 'Ações', 'dps-services-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $services as $service ) {
                $type  = get_post_meta( $service->ID, 'service_type', true );
                $cat   = get_post_meta( $service->ID, 'service_category', true );
                $active = get_post_meta( $service->ID, 'service_active', true );
                
                // Preços
                $price_values = [];
                $base_price   = get_post_meta( $service->ID, 'service_price', true );
                if ( '' !== $base_price && null !== $base_price ) {
                    $price_values[] = (float) $base_price;
                }
                foreach ( [ 'service_price_small', 'service_price_medium', 'service_price_large' ] as $price_key ) {
                    $price_meta = get_post_meta( $service->ID, $price_key, true );
                    if ( '' !== $price_meta && null !== $price_meta ) {
                        $price_values[] = (float) $price_meta;
                    }
                }
                $price_display = __( '—', 'dps-services-addon' );
                if ( ! empty( $price_values ) ) {
                    $min_price = min( $price_values );
                    $max_price = max( $price_values );
                    if ( abs( $min_price - $max_price ) < 0.01 ) {
                        $price_display = sprintf( 'R$ %s', number_format_i18n( $min_price, 2 ) );
                    } else {
                        $price_display = sprintf(
                            'R$ %s – R$ %s',
                            number_format_i18n( $min_price, 2 ),
                            number_format_i18n( $max_price, 2 )
                        );
                    }
                }
                
                // Duração
                $duration_values = [];
                foreach ( [ 'service_duration_small', 'service_duration_medium', 'service_duration_large' ] as $dur_key ) {
                    $dur_meta = get_post_meta( $service->ID, $dur_key, true );
                    if ( '' !== $dur_meta && null !== $dur_meta && (int) $dur_meta > 0 ) {
                        $duration_values[] = (int) $dur_meta;
                    }
                }
                $duration_display = __( '—', 'dps-services-addon' );
                if ( ! empty( $duration_values ) ) {
                    $min_dur = min( $duration_values );
                    $max_dur = max( $duration_values );
                    if ( $min_dur === $max_dur ) {
                        $duration_display = sprintf( __( '%d min', 'dps-services-addon' ), $min_dur );
                    } else {
                        $duration_display = sprintf( __( '%d – %d min', 'dps-services-addon' ), $min_dur, $max_dur );
                    }
                }
                
                $type_label = isset( $types[ $type ] ) ? $types[ $type ] : $type;
                $cat_label  = isset( $categories[ $cat ] ) ? $categories[ $cat ] : '';
                
                // URLs
                $edit_url = add_query_arg( [ 'tab' => 'servicos', 'dps_edit' => 'service', 'id' => $service->ID ], $base_url );
                $del_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'servicos', 'dps_service_delete' => $service->ID ], $base_url ),
                    'dps_delete_service_' . $service->ID
                );
                $toggle_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'servicos', 'dps_toggle_service' => $service->ID ], $base_url ),
                    'dps_toggle_service_' . $service->ID
                );
                $duplicate_url = wp_nonce_url(
                    add_query_arg( [ 'tab' => 'servicos', 'dps_duplicate_service' => $service->ID ], $base_url ),
                    'dps_duplicate_service_' . $service->ID
                );
                
                // Linha da tabela com classe para status
                $row_class = ( '0' === $active ) ? 'dps-row-inactive' : '';
                echo '<tr class="' . esc_attr( $row_class ) . '">';
                
                // Nome + Categoria (sublinhado)
                echo '<td class="dps-col-name">';
                echo '<strong>' . esc_html( $service->post_title ) . '</strong>';
                if ( $cat_label ) {
                    echo '<br><span class="dps-service-category">' . esc_html( $cat_label ) . '</span>';
                }
                echo '</td>';
                
                // Tipo com badge colorido
                $type_class = 'dps-type-' . sanitize_html_class( $type );
                echo '<td class="dps-col-type"><span class="dps-type-badge ' . esc_attr( $type_class ) . '">' . esc_html( $type_label ) . '</span></td>';
                
                // Preço
                echo '<td class="dps-col-price">' . esc_html( $price_display ) . '</td>';
                
                // Duração
                echo '<td class="dps-col-duration">' . esc_html( $duration_display ) . '</td>';
                
                // Status
                $status_class = ( '0' === $active ) ? 'dps-badge-inactive' : 'dps-badge-active';
                $status_label = ( '0' === $active ) ? __( 'Inativo', 'dps-services-addon' ) : __( 'Ativo', 'dps-services-addon' );
                echo '<td class="dps-col-status"><span class="dps-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
                
                // Ações com ícones/botões compactos
                echo '<td class="dps-col-actions">';
                echo '<div class="dps-action-buttons">';
                echo '<a href="' . esc_url( $edit_url ) . '" class="dps-action-btn dps-action-edit" title="' . esc_attr__( 'Editar', 'dps-services-addon' ) . '">' . esc_html__( 'Editar', 'dps-services-addon' ) . '</a>';
                echo '<a href="' . esc_url( $duplicate_url ) . '" class="dps-action-btn dps-action-duplicate" title="' . esc_attr__( 'Duplicar', 'dps-services-addon' ) . '">' . esc_html__( 'Duplicar', 'dps-services-addon' ) . '</a>';
                if ( '0' === $active ) {
                    echo '<a href="' . esc_url( $toggle_url ) . '" class="dps-action-btn dps-action-activate" title="' . esc_attr__( 'Ativar', 'dps-services-addon' ) . '">' . esc_html__( 'Ativar', 'dps-services-addon' ) . '</a>';
                } else {
                    echo '<a href="' . esc_url( $toggle_url ) . '" class="dps-action-btn dps-action-deactivate" title="' . esc_attr__( 'Desativar', 'dps-services-addon' ) . '">' . esc_html__( 'Desativar', 'dps-services-addon' ) . '</a>';
                }
                echo '<a href="' . esc_url( $del_url ) . '" class="dps-action-btn dps-action-delete" onclick="return confirm(\'' . esc_js( __( 'Tem certeza de que deseja excluir este serviço?', 'dps-services-addon' ) ) . '\');" title="' . esc_attr__( 'Excluir', 'dps-services-addon' ) . '">' . esc_html__( 'Excluir', 'dps-services-addon' ) . '</a>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // .dps-table-wrapper
        } else {
            // Empty state moderno
            echo '<div class="dps-empty-state">';
            echo '<span class="dps-empty-state__icon">🧼</span>';
            echo '<h4 class="dps-empty-state__title">' . esc_html__( 'Nenhum serviço cadastrado', 'dps-services-addon' ) . '</h4>';
            echo '</div>';
        }
    }

    /**
     * Processa solicitações de salvamento ou exclusão de serviços
     */
    public function maybe_handle_service_request() {
        // Salvamento
        if ( isset( $_POST['dps_service_action'] ) && 'save_service' === $_POST['dps_service_action'] ) {
            // Verifica permissão antes de processar
            if ( ! $this->can_manage() ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Você não tem permissão para gerenciar serviços.', 'dps-services-addon' ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            // Verifica nonce
            if ( ! isset( $_POST['dps_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_service_nonce'] ) ), 'dps_service_action' ) ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-services-addon' ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            $name     = isset( $_POST['service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['service_name'] ) ) : '';
            $type     = isset( $_POST['service_type'] ) ? sanitize_text_field( wp_unslash( $_POST['service_type'] ) ) : '';
            $category = isset( $_POST['service_category'] ) ? sanitize_text_field( wp_unslash( $_POST['service_category'] ) ) : '';
            $price_small  = isset( $_POST['service_price_small'] ) && $_POST['service_price_small'] !== '' ? floatval( wp_unslash( $_POST['service_price_small'] ) ) : null;
            $price_medium = isset( $_POST['service_price_medium'] ) && $_POST['service_price_medium'] !== '' ? floatval( wp_unslash( $_POST['service_price_medium'] ) ) : null;
            $price_large  = isset( $_POST['service_price_large'] ) && $_POST['service_price_large'] !== '' ? floatval( wp_unslash( $_POST['service_price_large'] ) ) : null;
            $dur_small  = isset( $_POST['service_duration_small'] ) && $_POST['service_duration_small'] !== '' ? intval( wp_unslash( $_POST['service_duration_small'] ) ) : null;
            $dur_medium = isset( $_POST['service_duration_medium'] ) && $_POST['service_duration_medium'] !== '' ? intval( wp_unslash( $_POST['service_duration_medium'] ) ) : null;
            $dur_large  = isset( $_POST['service_duration_large'] ) && $_POST['service_duration_large'] !== '' ? intval( wp_unslash( $_POST['service_duration_large'] ) ) : null;
            $price_candidates = [];
            foreach ( [ $price_small, $price_medium, $price_large ] as $candidate ) {
                if ( null !== $candidate ) {
                    $price_candidates[] = $candidate;
                }
            }
            $duration_candidates = [];
            foreach ( [ $dur_small, $dur_medium, $dur_large ] as $candidate ) {
                if ( null !== $candidate ) {
                    $duration_candidates[] = $candidate;
                }
            }
            $price    = ! empty( $price_candidates ) ? min( $price_candidates ) : 0;
            $duration = ! empty( $duration_candidates ) ? min( $duration_candidates ) : 0;

            // Validação com mensagens de erro para o usuário
            $validation_errors = [];

            if ( empty( $name ) ) {
                $validation_errors[] = __( 'O nome do serviço é obrigatório.', 'dps-services-addon' );
            }

            if ( empty( $type ) ) {
                $validation_errors[] = __( 'O tipo de serviço é obrigatório.', 'dps-services-addon' );
            }

            if ( empty( $price_candidates ) && 'package' !== $type ) {
                $validation_errors[] = __( 'Informe pelo menos um valor de preço para o serviço.', 'dps-services-addon' );
            }

            // Se houver erros de validação, exibe mensagens e redireciona
            if ( ! empty( $validation_errors ) ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    foreach ( $validation_errors as $error ) {
                        DPS_Message_Helper::add_error( $error );
                    }
                } else {
                    // Fallback: registra erros em log se DPS_Message_Helper não estiver disponível
                    error_log( 'DPS Services Addon - Erros de validação: ' . implode( '; ', $validation_errors ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }

            $active   = ( isset( $_POST['service_active'] ) && '1' === wp_unslash( $_POST['service_active'] ) ) ? '1' : '0';
            $srv_id = isset( $_POST['service_id'] ) ? intval( wp_unslash( $_POST['service_id'] ) ) : 0;
            if ( $srv_id ) {
                wp_update_post( [ 'ID' => $srv_id, 'post_title' => $name ] );
            } else {
                $srv_id = wp_insert_post( [ 'post_type' => 'dps_service', 'post_title' => $name, 'post_status' => 'publish' ] );
            }
            if ( $srv_id ) {
                // Obtém preços anteriores para histórico
                $old_price_small  = (float) get_post_meta( $srv_id, 'service_price_small', true );
                $old_price_medium = (float) get_post_meta( $srv_id, 'service_price_medium', true );
                $old_price_large  = (float) get_post_meta( $srv_id, 'service_price_large', true );
                $old_price_base   = (float) get_post_meta( $srv_id, 'service_price', true );

                update_post_meta( $srv_id, 'service_type', $type );
                update_post_meta( $srv_id, 'service_category', $category );
                update_post_meta( $srv_id, 'service_price', $price );
                update_post_meta( $srv_id, 'service_duration', $duration );
                update_post_meta( $srv_id, 'service_active', $active );
                
                // Salva tipo de profissional requerido (integração Groomers Add-on)
                $required_staff = isset( $_POST['required_staff_type'] ) ? sanitize_key( wp_unslash( $_POST['required_staff_type'] ) ) : 'any';
                update_post_meta( $srv_id, 'required_staff_type', $required_staff );

                // Registra histórico de alteração de preços
                if ( class_exists( 'DPS_Services_API' ) ) {
                    DPS_Services_API::log_price_change( $srv_id, 'base', $old_price_base, $price );
                    if ( null !== $price_small ) {
                        DPS_Services_API::log_price_change( $srv_id, 'small', $old_price_small, $price_small );
                    }
                    if ( null !== $price_medium ) {
                        DPS_Services_API::log_price_change( $srv_id, 'medium', $old_price_medium, $price_medium );
                    }
                    if ( null !== $price_large ) {
                        DPS_Services_API::log_price_change( $srv_id, 'large', $old_price_large, $price_large );
                    }
                }

                // Salva variações de preço e duração por porte (pode ser vazia para usar padrão)
                if ( null !== $price_small ) {
                    update_post_meta( $srv_id, 'service_price_small', $price_small );
                } else {
                    delete_post_meta( $srv_id, 'service_price_small' );
                }
                if ( null !== $price_medium ) {
                    update_post_meta( $srv_id, 'service_price_medium', $price_medium );
                } else {
                    delete_post_meta( $srv_id, 'service_price_medium' );
                }
                if ( null !== $price_large ) {
                    update_post_meta( $srv_id, 'service_price_large', $price_large );
                } else {
                    delete_post_meta( $srv_id, 'service_price_large' );
                }
                if ( null !== $dur_small ) {
                    update_post_meta( $srv_id, 'service_duration_small', $dur_small );
                } else {
                    delete_post_meta( $srv_id, 'service_duration_small' );
                }
                if ( null !== $dur_medium ) {
                    update_post_meta( $srv_id, 'service_duration_medium', $dur_medium );
                } else {
                    delete_post_meta( $srv_id, 'service_duration_medium' );
                }
                if ( null !== $dur_large ) {
                    update_post_meta( $srv_id, 'service_duration_large', $dur_large );
                } else {
                    delete_post_meta( $srv_id, 'service_duration_large' );
                }
                // Salva insumos vinculados ao serviço
                $consumption = [];
                $posted_items = isset( $_POST['dps_stock_item'] ) ? (array) wp_unslash( $_POST['dps_stock_item'] ) : [];
                $posted_qty   = isset( $_POST['dps_stock_quantity'] ) ? (array) wp_unslash( $_POST['dps_stock_quantity'] ) : [];

                foreach ( $posted_items as $idx => $item_raw ) {
                    $item_id = intval( $item_raw );
                    $qty_raw = isset( $posted_qty[ $idx ] ) ? $posted_qty[ $idx ] : '';
                    $qty     = '' === $qty_raw ? 0 : floatval( str_replace( ',', '.', $qty_raw ) );

                    if ( $item_id && $qty > 0 ) {
                        $consumption[] = [
                            'item_id'  => $item_id,
                            'quantity' => $qty,
                        ];
                    }
                }

                if ( ! empty( $consumption ) ) {
                    update_post_meta( $srv_id, 'dps_service_stock_consumption', $consumption );
                } else {
                    delete_post_meta( $srv_id, 'dps_service_stock_consumption' );
                }
                // Salva serviços incluídos no pacote, se for um pacote
                if ( 'package' === $type && isset( $_POST['service_package_items'] ) && is_array( $_POST['service_package_items'] ) ) {
                    $items = array_map( 'intval', (array) wp_unslash( $_POST['service_package_items'] ) );
                    update_post_meta( $srv_id, 'service_package_items', $items );

                    // Salva desconto percentual do pacote
                    $package_discount = isset( $_POST['service_package_discount'] ) ? floatval( wp_unslash( $_POST['service_package_discount'] ) ) : 0;
                    if ( $package_discount > 0 && $package_discount <= 100 ) {
                        update_post_meta( $srv_id, 'service_package_discount', $package_discount );
                    } else {
                        delete_post_meta( $srv_id, 'service_package_discount' );
                    }

                    // Salva preço fixo do pacote (alternativa ao desconto)
                    $package_fixed = isset( $_POST['service_package_fixed_price'] ) && '' !== $_POST['service_package_fixed_price'] 
                        ? floatval( wp_unslash( $_POST['service_package_fixed_price'] ) ) 
                        : null;
                    if ( null !== $package_fixed && $package_fixed > 0 ) {
                        update_post_meta( $srv_id, 'service_package_fixed_price', $package_fixed );
                    } else {
                        delete_post_meta( $srv_id, 'service_package_fixed_price' );
                    }
                } else {
                    delete_post_meta( $srv_id, 'service_package_items' );
                    delete_post_meta( $srv_id, 'service_package_discount' );
                    delete_post_meta( $srv_id, 'service_package_fixed_price' );
                }
                // Adiciona mensagem de sucesso baseado no tipo de operação
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    $is_update = isset( $_POST['service_id'] ) && intval( wp_unslash( $_POST['service_id'] ) ) > 0;
                    $message   = $is_update
                        ? __( 'Serviço atualizado com sucesso.', 'dps-services-addon' )
                        : __( 'Serviço cadastrado com sucesso.', 'dps-services-addon' );
                    DPS_Message_Helper::add_success( $message );
                }
            }
            // Redireciona para aba serviços
            $redirect = $this->get_redirect_url();
            wp_safe_redirect( $redirect );
            exit;
        }
        // Exclusão via GET com verificação de nonce
        if ( isset( $_GET['dps_service_delete'] ) ) {
            // Verifica permissão antes de processar
            if ( ! $this->can_manage() ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Você não tem permissão para excluir serviços.', 'dps-services-addon' ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            $id = intval( wp_unslash( $_GET['dps_service_delete'] ) );
            // Verifica nonce antes de excluir
            if ( $id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_delete_service_' . $id ) ) {
                $service = get_post( $id );
                if ( $service && 'dps_service' === $service->post_type ) {
                    wp_delete_post( $id, true );
                    if ( class_exists( 'DPS_Message_Helper' ) ) {
                        DPS_Message_Helper::add_success( __( 'Serviço excluído com sucesso.', 'dps-services-addon' ) );
                    }
                }
            } else {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-services-addon' ) );
                }
            }
            $redirect = $this->get_redirect_url();
            wp_safe_redirect( $redirect );
            exit;
        }

        // Alterna status ativo/inativo via GET com verificação de nonce
        if ( isset( $_GET['dps_toggle_service'] ) ) {
            // Verifica permissão antes de processar
            if ( ! $this->can_manage() ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Você não tem permissão para alterar serviços.', 'dps-services-addon' ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            $id = intval( wp_unslash( $_GET['dps_toggle_service'] ) );
            // Verifica nonce antes de alterar status
            if ( $id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_toggle_service_' . $id ) ) {
                $service = get_post( $id );
                if ( $service && 'dps_service' === $service->post_type ) {
                    $curr = get_post_meta( $id, 'service_active', true );
                    $new  = ( '0' === $curr ) ? '1' : '0';
                    update_post_meta( $id, 'service_active', $new );
                    if ( class_exists( 'DPS_Message_Helper' ) ) {
                        $message = ( '1' === $new ) 
                            ? __( 'Serviço ativado com sucesso.', 'dps-services-addon' )
                            : __( 'Serviço desativado com sucesso.', 'dps-services-addon' );
                        DPS_Message_Helper::add_success( $message );
                    }
                }
            } else {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-services-addon' ) );
                }
            }
            $redirect = $this->get_redirect_url();
            wp_safe_redirect( $redirect );
            exit;
        }

        // Duplicar serviço via GET com verificação de nonce
        if ( isset( $_GET['dps_duplicate_service'] ) ) {
            // Verifica permissão antes de processar
            if ( ! $this->can_manage() ) {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Você não tem permissão para duplicar serviços.', 'dps-services-addon' ) );
                }
                $redirect = $this->get_redirect_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            $id = intval( wp_unslash( $_GET['dps_duplicate_service'] ) );
            if ( $id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dps_duplicate_service_' . $id ) ) {
                $new_id = DPS_Services_API::duplicate_service( $id );
                if ( $new_id ) {
                    if ( class_exists( 'DPS_Message_Helper' ) ) {
                        // Use current page URL as base, with fallback to avoid empty URL
                        $current_url = get_permalink();
                        if ( ! $current_url && isset( $_SERVER['REQUEST_URI'] ) ) {
                            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
                            $current_url = esc_url_raw( home_url( $request_uri ) );
                        }
                        $edit_url = add_query_arg(
                            [ 'tab' => 'servicos', 'dps_edit' => 'service', 'id' => $new_id ],
                            $current_url ? remove_query_arg( [ 'dps_duplicate_service', '_wpnonce' ], $current_url ) : home_url()
                        );
                        // Usa mensagem simples sem HTML (DPS_Message_Helper escapa o texto)
                        DPS_Message_Helper::add_success(
                            __( 'Serviço duplicado com sucesso! O novo serviço está inativo. Use o botão Editar na tabela.', 'dps-services-addon' )
                        );
                    }
                } else {
                    if ( class_exists( 'DPS_Message_Helper' ) ) {
                        DPS_Message_Helper::add_error( __( 'Erro ao duplicar serviço.', 'dps-services-addon' ) );
                    }
                }
            } else {
                if ( class_exists( 'DPS_Message_Helper' ) ) {
                    DPS_Message_Helper::add_error( __( 'Sessão expirada. Atualize a página e tente novamente.', 'dps-services-addon' ) );
                }
            }
            $redirect = $this->get_redirect_url();
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    /**
     * Insere campos de seleção de serviços no formulário de agendamento
     *
     * @param int   $edit_id ID do agendamento sendo editado
     * @param array $meta    Metadados do agendamento
     */
    public function appointment_service_fields( $edit_id, $meta ) {
        // Recupera lista de serviços
        $services = get_posts( [
            'post_type'      => 'dps_service',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        if ( ! $services ) {
            return;
        }
        // Agrupa serviços por tipo e categoria, ignorando os inativos
        $grouped = [ 'padrao' => [], 'extra' => [], 'package' => [] ];
        foreach ( $services as $srv ) {
            $active = get_post_meta( $srv->ID, 'service_active', true );
            if ( '0' === $active ) {
                continue;
            }
            $type  = get_post_meta( $srv->ID, 'service_type', true );
            $cat   = get_post_meta( $srv->ID, 'service_category', true );
            $price = get_post_meta( $srv->ID, 'service_price', true );
            // Recupera variações de preço por porte (retorna string ou vazio)
            $price_small  = get_post_meta( $srv->ID, 'service_price_small', true );
            $price_medium = get_post_meta( $srv->ID, 'service_price_medium', true );
            $price_large  = get_post_meta( $srv->ID, 'service_price_large', true );
            // Se tipo desconhecido, coloca como extra
            if ( ! isset( $grouped[ $type ] ) ) {
                $type = 'extra';
            }
            $grouped[ $type ][] = [
                'id'          => $srv->ID,
                'name'        => $srv->post_title,
                'category'    => $cat,
                'price'       => floatval( $price ),
                'price_small'  => ( '' !== $price_small ? floatval( $price_small ) : null ),
                'price_medium' => ( '' !== $price_medium ? floatval( $price_medium ) : null ),
                'price_large'  => ( '' !== $price_large ? floatval( $price_large ) : null ),
            ];
        }
        // Serviços selecionados (para edição)
        $selected = [];
        if ( $edit_id ) {
            $sel = get_post_meta( $edit_id, 'appointment_services', true );
            if ( is_array( $sel ) ) {
                $selected = $sel;
            }
        }
        echo '<fieldset class="dps-services-fields"><legend>' . esc_html__( 'Serviços', 'dps-services-addon' ) . '</legend>';
        $appt_type = isset( $meta['appointment_type'] ) && $meta['appointment_type'] ? $meta['appointment_type'] : ( isset( $_POST['appointment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_type'] ) ) : 'simple' );
        $simple_display       = ( 'subscription' === $appt_type ) ? 'none' : 'block';
        $subscription_display = ( 'subscription' === $appt_type ) ? 'block' : 'none';
        
        // Recupera extras salvos (novo formato: array de extras)
        $extras_list = [];
        if ( $edit_id ) {
            $saved_extras = get_post_meta( $edit_id, 'appointment_extras_list', true );
            if ( is_array( $saved_extras ) && ! empty( $saved_extras ) ) {
                $extras_list = $saved_extras;
            } else {
                // Compatibilidade: migra formato antigo (single extra) para novo formato
                $extra_desc  = $meta['extra_description'] ?? '';
                $extra_value = $meta['extra_value'] ?? '';
                if ( '' !== $extra_desc || '' !== $extra_value ) {
                    $extras_list[] = [
                        'description' => $extra_desc,
                        'value'       => floatval( $extra_value ),
                    ];
                }
            }
        }
        $has_extras = ! empty( $extras_list );
        
        echo '<div class="dps-simple-fields" style="display:' . esc_attr( $simple_display ) . ';">';
        if ( ! empty( $grouped['padrao'] ) ) {
            echo '<p><strong>' . esc_html__( 'Serviços padrão', 'dps-services-addon' ) . '</strong></p>';
            foreach ( $grouped['padrao'] as $srv ) {
                $checked  = in_array( $srv['id'], $selected, false ) ? 'checked' : '';
                $custom_prices = [];
                if ( $edit_id ) {
                    $custom_prices = get_post_meta( $edit_id, 'appointment_service_prices', true );
                    if ( ! is_array( $custom_prices ) ) {
                        $custom_prices = [];
                    }
                }
                $current_price = isset( $custom_prices[ $srv['id'] ] ) ? (float) $custom_prices[ $srv['id'] ] : (float) $srv['price'];
                echo '<p><label>';
                echo '<input type="checkbox" class="dps-service-checkbox" name="appointment_services[]" value="' . esc_attr( $srv['id'] ) . '" '
                    . 'data-price-default="' . esc_attr( $srv['price'] ) . '" '
                    . 'data-price-small="' . esc_attr( $srv['price_small'] ?? '' ) . '" '
                    . 'data-price-medium="' . esc_attr( $srv['price_medium'] ?? '' ) . '" '
                    . 'data-price-large="' . esc_attr( $srv['price_large'] ?? '' ) . '" '
                    . $checked . '> ';
                echo esc_html( $srv['name'] ) . ' ';
                echo '<span class="dps-service-price-wrapper">(R$ ';
                echo '<input type="number" class="dps-service-price" name="service_price[' . esc_attr( $srv['id'] ) . ']" step="0.01" value="' . esc_attr( $current_price ) . '" min="0">';
                echo ')</span>';
                echo '</label></p>';
            }
        }
        if ( ! empty( $grouped['extra'] ) ) {
            $cats = [];
            foreach ( $grouped['extra'] as $srv ) {
                $cats[ $srv['category'] ][] = $srv;
            }
            $labels = [
                'preparacao_pelagem' => __( 'Preparação da pelagem', 'dps-services-addon' ),
                'opcoes_tosa'        => __( 'Opções de tosa', 'dps-services-addon' ),
                'tratamento'         => __( 'Tratamento', 'dps-services-addon' ),
                'cuidados'           => __( 'Cuidados adicionais', 'dps-services-addon' ),
                'pelagem'            => __( 'Tratamento da pelagem e pele', 'dps-services-addon' ),
            ];
            $custom_prices = [];
            if ( $edit_id ) {
                $custom_prices = get_post_meta( $edit_id, 'appointment_service_prices', true );
                if ( ! is_array( $custom_prices ) ) {
                    $custom_prices = [];
                }
            }
            foreach ( $cats as $cat_key => $items ) {
                $label = isset( $labels[ $cat_key ] ) ? $labels[ $cat_key ] : $cat_key;
                echo '<p><strong>' . esc_html( $label ) . '</strong></p>';
                foreach ( $items as $srv ) {
                    $checked = in_array( $srv['id'], $selected, false ) ? 'checked' : '';
                    $current_price = isset( $custom_prices[ $srv['id'] ] ) ? (float) $custom_prices[ $srv['id'] ] : (float) $srv['price'];
                    echo '<p><label>';
                    echo '<input type="checkbox" class="dps-service-checkbox" name="appointment_services[]" value="' . esc_attr( $srv['id'] ) . '" '
                        . 'data-price-default="' . esc_attr( $srv['price'] ) . '" '
                        . 'data-price-small="' . esc_attr( $srv['price_small'] ?? '' ) . '" '
                        . 'data-price-medium="' . esc_attr( $srv['price_medium'] ?? '' ) . '" '
                        . 'data-price-large="' . esc_attr( $srv['price_large'] ?? '' ) . '" '
                        . $checked . '> ';
                    echo esc_html( $srv['name'] ) . ' ';
                    echo '<span class="dps-service-price-wrapper">(R$ ';
                    echo '<input type="number" class="dps-service-price" name="service_price[' . esc_attr( $srv['id'] ) . ']" step="0.01" value="' . esc_attr( $current_price ) . '" min="0">';
                    echo ')</span>';
                    echo '</label></p>';
                }
            }
        }
        if ( ! empty( $grouped['package'] ) ) {
            echo '<p><strong>' . esc_html__( 'Pacotes de serviços', 'dps-services-addon' ) . '</strong></p>';
            $custom_prices = [];
            if ( $edit_id ) {
                $custom_prices = get_post_meta( $edit_id, 'appointment_service_prices', true );
                if ( ! is_array( $custom_prices ) ) {
                    $custom_prices = [];
                }
            }
            foreach ( $grouped['package'] as $srv ) {
                $checked = in_array( $srv['id'], $selected, false ) ? 'checked' : '';
                $current_price = isset( $custom_prices[ $srv['id'] ] ) ? (float) $custom_prices[ $srv['id'] ] : (float) $srv['price'];
                echo '<p><label>';
                echo '<input type="checkbox" class="dps-service-checkbox" name="appointment_services[]" value="' . esc_attr( $srv['id'] ) . '" '
                    . 'data-price-default="' . esc_attr( $srv['price'] ) . '" '
                    . 'data-price-small="' . esc_attr( $srv['price_small'] ?? '' ) . '" '
                    . 'data-price-medium="' . esc_attr( $srv['price_medium'] ?? '' ) . '" '
                    . 'data-price-large="' . esc_attr( $srv['price_large'] ?? '' ) . '" '
                    . $checked . '> ';
                echo esc_html( $srv['name'] ) . ' ';
                echo '<span class="dps-service-price-wrapper">(R$ ';
                echo '<input type="number" class="dps-service-price" name="service_price[' . esc_attr( $srv['id'] ) . ']" step="0.01" value="' . esc_attr( $current_price ) . '" min="0">';
                echo ')</span>';
                echo '</label></p>';
            }
        }
        if ( $edit_id ) {
            $pet_id = get_post_meta( $edit_id, 'appointment_pet_id', true );
            if ( $pet_id ) {
                $vacc = get_post_meta( $pet_id, 'pet_vaccinations', true );
                $all  = get_post_meta( $pet_id, 'pet_allergies', true );
                $beh  = get_post_meta( $pet_id, 'pet_behavior', true );
                echo '<div class="dps-pet-notes" style="background:#f9f9f9;border:1px solid #ddd;padding:10px;margin-top:10px;">';
                echo '<p><strong>' . esc_html__( 'Informações do pet', 'dps-services-addon' ) . ':</strong></p>';
                if ( $vacc ) {
                    echo '<p><em>' . esc_html__( 'Vacinas / Saúde', 'dps-services-addon' ) . ':</em> ' . esc_html( $vacc ) . '</p>';
                }
                if ( $all ) {
                    echo '<p><em>' . esc_html__( 'Alergias / Restrições', 'dps-services-addon' ) . ':</em> ' . esc_html( $all ) . '</p>';
                }
                if ( $beh ) {
                    echo '<p><em>' . esc_html__( 'Notas de Comportamento', 'dps-services-addon' ) . ':</em> ' . esc_html( $beh ) . '</p>';
                }
                echo '</div>';
            }
        }
        $total_val = '';
        if ( $edit_id ) {
            $total_val = get_post_meta( $edit_id, 'appointment_total_value', true );
        }
        
        // === SEÇÃO DE SERVIÇOS EXTRAS (REDESENHADA) ===
        echo '<div class="dps-extras-section">';
        echo '<button type="button" class="dps-btn dps-btn--outline dps-extras-toggle" data-target="#dps-simple-extras-container" aria-expanded="' . ( $has_extras ? 'true' : 'false' ) . '">';
        echo '<span class="dps-extras-toggle-icon">➕</span> ';
        echo esc_html__( 'Adicionar Serviço Extra', 'dps-services-addon' );
        echo '</button>';
        
        echo '<div id="dps-simple-extras-container" class="dps-extras-container" style="display:' . ( $has_extras ? 'block' : 'none' ) . ';">';
        echo '<div class="dps-extras-header">';
        echo '<h4 class="dps-extras-title">' . esc_html__( 'Serviços Extras', 'dps-services-addon' ) . '</h4>';
        echo '<p class="dps-extras-hint">' . esc_html__( 'Adicione serviços extras com descrição e valor.', 'dps-services-addon' ) . '</p>';
        echo '</div>';
        
        echo '<div id="dps-simple-extras-list" class="dps-extras-list">';
        // Renderiza extras existentes
        if ( ! empty( $extras_list ) ) {
            foreach ( $extras_list as $idx => $extra_item ) {
                $this->render_extra_row( 'simple', $idx, $extra_item );
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="dps-btn dps-btn--secondary dps-add-extra-btn" data-list="#dps-simple-extras-list" data-type="simple">';
        echo '<span>➕</span> ' . esc_html__( 'Adicionar outro extra', 'dps-services-addon' );
        echo '</button>';
        echo '</div>'; // extras-container
        echo '</div>'; // extras-section
        
        echo '<p><label>' . esc_html__( 'Valor total do serviço (R$)', 'dps-services-addon' ) . '<br><input type="number" step="0.01" id="dps-appointment-total" name="appointment_total" value="' . esc_attr( $total_val ) . '" min="0"></label></p>';
        echo '</div>'; // simple-fields
        
        // === CAMPOS PARA ASSINATURA ===
        $subscription_base_value  = $meta['subscription_base_value'] ?? '';
        $subscription_total_value = $meta['subscription_total_value'] ?? '';
        
        // Recupera extras de assinatura
        $subscription_extras_list = [];
        if ( $edit_id ) {
            $saved_sub_extras = get_post_meta( $edit_id, 'subscription_extras_list', true );
            if ( is_array( $saved_sub_extras ) && ! empty( $saved_sub_extras ) ) {
                $subscription_extras_list = $saved_sub_extras;
            } else {
                // Compatibilidade: migra formato antigo
                $sub_extra_desc  = $meta['subscription_extra_description'] ?? '';
                $sub_extra_value = $meta['subscription_extra_value'] ?? '';
                if ( '' !== $sub_extra_desc || '' !== $sub_extra_value ) {
                    $subscription_extras_list[] = [
                        'description' => $sub_extra_desc,
                        'value'       => floatval( $sub_extra_value ),
                    ];
                }
            }
        }
        $has_subscription_extras = ! empty( $subscription_extras_list );
        
        echo '<div id="dps-subscription-fields" class="dps-subscription-fields" style="display:' . esc_attr( $subscription_display ) . ';">';
        echo '<p><label>' . esc_html__( 'Valor da assinatura (R$)', 'dps-services-addon' ) . '<br><input type="number" step="0.01" min="0" id="dps-subscription-base" name="subscription_base_value" value="' . esc_attr( $subscription_base_value ) . '"></label></p>';
        echo '<p><label>' . esc_html__( 'Valor total da assinatura (R$)', 'dps-services-addon' ) . '<br><input type="number" step="0.01" min="0" id="dps-subscription-total" name="subscription_total_value" value="' . esc_attr( $subscription_total_value ) . '"></label></p>';
        
        // Seção de extras para assinatura
        echo '<div class="dps-extras-section">';
        echo '<button type="button" class="dps-btn dps-btn--outline dps-extras-toggle" data-target="#dps-subscription-extras-container" aria-expanded="' . ( $has_subscription_extras ? 'true' : 'false' ) . '">';
        echo '<span class="dps-extras-toggle-icon">➕</span> ';
        echo esc_html__( 'Adicionar Serviço Extra', 'dps-services-addon' );
        echo '</button>';
        
        echo '<div id="dps-subscription-extras-container" class="dps-extras-container" style="display:' . ( $has_subscription_extras ? 'block' : 'none' ) . ';">';
        echo '<div class="dps-extras-header">';
        echo '<h4 class="dps-extras-title">' . esc_html__( 'Serviços Extras', 'dps-services-addon' ) . '</h4>';
        echo '<p class="dps-extras-hint">' . esc_html__( 'Adicione serviços extras com descrição e valor.', 'dps-services-addon' ) . '</p>';
        echo '</div>';
        
        echo '<div id="dps-subscription-extras-list" class="dps-extras-list">';
        if ( ! empty( $subscription_extras_list ) ) {
            foreach ( $subscription_extras_list as $idx => $extra_item ) {
                $this->render_extra_row( 'subscription', $idx, $extra_item );
            }
        }
        echo '</div>';
        
        echo '<button type="button" class="dps-btn dps-btn--secondary dps-add-extra-btn" data-list="#dps-subscription-extras-list" data-type="subscription">';
        echo '<span>➕</span> ' . esc_html__( 'Adicionar outro extra', 'dps-services-addon' );
        echo '</button>';
        echo '</div>'; // extras-container
        echo '</div>'; // extras-section
        
        echo '</div>'; // subscription-fields
        echo '</fieldset>';
    }
    
    /**
     * Renderiza uma linha de serviço extra.
     *
     * @since 1.4.0
     *
     * @param string $type       Tipo do extra ('simple' ou 'subscription').
     * @param int    $index      Índice do extra.
     * @param array  $extra_item Dados do extra (description, value).
     */
    private function render_extra_row( $type, $index, $extra_item = [] ) {
        $prefix = ( 'subscription' === $type ) ? 'subscription_extras' : 'appointment_extras';
        $description = isset( $extra_item['description'] ) ? $extra_item['description'] : '';
        $value       = isset( $extra_item['value'] ) ? $extra_item['value'] : '';
        
        echo '<div class="dps-extra-row" data-index="' . esc_attr( $index ) . '">';
        echo '<div class="dps-extra-row-fields">';
        echo '<div class="dps-extra-description-field">';
        echo '<input type="text" name="' . esc_attr( $prefix ) . '_descriptions[]" value="' . esc_attr( $description ) . '" placeholder="' . esc_attr__( 'Descrição do serviço', 'dps-services-addon' ) . '" class="dps-extra-description-input">';
        echo '</div>';
        echo '<div class="dps-extra-value-field">';
        echo '<div class="dps-input-with-prefix">';
        echo '<span class="dps-input-prefix">R$</span>';
        echo '<input type="number" step="0.01" min="0" name="' . esc_attr( $prefix ) . '_values[]" value="' . esc_attr( $value ) . '" placeholder="0,00" class="dps-extra-value-input">';
        echo '</div>';
        echo '</div>';
        echo '<button type="button" class="dps-btn dps-btn--icon dps-remove-extra-btn" title="' . esc_attr__( 'Remover', 'dps-services-addon' ) . '">';
        echo '<span>✕</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Exibe campos adicionais quando um agendamento já foi marcado como
     * finalizado. Para cada serviço selecionado originalmente, mostra um
     * checkbox para confirmar sua execução. Também permite adicionar
     * serviços extras com valores.
     *
     * @param int   $edit_id ID do agendamento em edição
     * @param array $meta    Metadados atuais do agendamento
     */
    public function appointment_finalization_fields( $edit_id, $meta ) {
        // Só exibe em modo de edição e quando o status for finalizado ou finalizado_pago
        if ( ! $edit_id ) {
            return;
        }
        $status = get_post_meta( $edit_id, 'appointment_status', true );
        if ( ! in_array( $status, [ 'finalizado', 'finalizado_pago' ], true ) ) {
            return;
        }
        // Obtém os serviços originalmente selecionados
        $services = get_post_meta( $edit_id, 'appointment_services', true );
        if ( ! is_array( $services ) ) {
            $services = [];
        }
        $executed = get_post_meta( $edit_id, 'appointment_services_executed', true );
        if ( ! is_array( $executed ) ) {
            $executed = [];
        }
        // Título
        echo '<h4>' . esc_html__( 'Checklist de Serviços', 'dps-services-addon' ) . '</h4>';
        if ( $services ) {
            echo '<p>' . esc_html__( 'Marque os serviços que foram realizados:', 'dps-services-addon' ) . '</p>';
            foreach ( $services as $srv_id ) {
                $srv_post = get_post( $srv_id );
                if ( ! $srv_post ) {
                    continue;
                }
                $srv_name = $srv_post->post_title;
                $checked  = in_array( $srv_id, $executed, false ) ? 'checked' : '';
                echo '<p><label><input type="checkbox" name="appointment_services_executed[]" value="' . esc_attr( $srv_id ) . '" ' . $checked . '> ' . esc_html( $srv_name ) . '</label></p>';
            }
        }
        // Campos para serviços extras
        echo '<h4 style="margin-top:20px;">' . esc_html__( 'Serviços extras realizados', 'dps-services-addon' ) . '</h4>';
        echo '<p>' . esc_html__( 'Adicione qualquer serviço extra executado e seu valor (R$):', 'dps-services-addon' ) . '</p>';
        // Recupera extras existentes, se houver
        $extras = get_post_meta( $edit_id, 'appointment_extra_services', true );
        if ( ! is_array( $extras ) ) {
            $extras = [];
        }
        // Container para campos de extras
        echo '<div id="dps-extra-services-wrapper">';
        $index = 0;
        foreach ( $extras as $extra ) {
            $name  = isset( $extra['name'] ) ? $extra['name'] : '';
            $price = isset( $extra['price'] ) ? $extra['price'] : '';
            echo '<div class="dps-extra-row" style="margin-bottom:8px;">';
            echo '<input type="text" name="appointment_extra_names[]" value="' . esc_attr( $name ) . '" placeholder="' . esc_attr__( 'Nome do serviço', 'dps-services-addon' ) . '" style="margin-right:10px;">';
            echo '<input type="number" step="0.01" min="0" name="appointment_extra_prices[]" value="' . esc_attr( $price ) . '" placeholder="' . esc_attr__( 'Valor', 'dps-services-addon' ) . '" style="width:100px; margin-right:10px;">';
            // botão remover
            echo '<button type="button" class="button dps-remove-extra" onclick="jQuery(this).parent().remove();">' . esc_html__( 'Remover', 'dps-services-addon' ) . '</button>';
            echo '</div>';
            $index++;
        }
        // Linha vazia inicial
        echo '<div class="dps-extra-row" style="margin-bottom:8px;">';
        echo '<input type="text" name="appointment_extra_names[]" placeholder="' . esc_attr__( 'Nome do serviço', 'dps-services-addon' ) . '" style="margin-right:10px;">';
        echo '<input type="number" step="0.01" min="0" name="appointment_extra_prices[]" placeholder="' . esc_attr__( 'Valor', 'dps-services-addon' ) . '" style="width:100px; margin-right:10px;">';
        echo '<button type="button" class="button dps-remove-extra" onclick="jQuery(this).parent().remove();">' . esc_html__( 'Remover', 'dps-services-addon' ) . '</button>';
        echo '</div>';
        echo '</div>'; // wrapper
        // Botão para adicionar nova linha
        echo '<p><button type="button" class="button" id="dps-add-extra-service">' . esc_html__( 'Adicionar Serviço Extra', 'dps-services-addon' ) . '</button></p>';
        // Script JS para adicionar linha
        echo '<script>(function($){$(document).ready(function(){\
            $("#dps-add-extra-service").on("click", function(){\
                var row = `<div class="dps-extra-row" style="margin-bottom:8px;">\
                    <input type="text" name="appointment_extra_names[]" placeholder="' . esc_js( __( 'Nome do serviço', 'dps-services-addon' ) ) . '" style="margin-right:10px;">\
                    <input type="number" step="0.01" min="0" name="appointment_extra_prices[]" placeholder="' . esc_js( __( 'Valor', 'dps-services-addon' ) ) . '" style="width:100px; margin-right:10px;">\
                    <button type="button" class="button dps-remove-extra" onclick="jQuery(this).parent().remove();">' . esc_js( __( 'Remover', 'dps-services-addon' ) ) . '</button>\
                    </div>`;\
                $("#dps-extra-services-wrapper").append(row);\
            });\
        });})(jQuery);</script>';
    }

    /**
     * Salva o checklist de serviços executados e os serviços extras ao salvar o agendamento.
     * O valor total do agendamento será atualizado somando-se o valor dos extras.
     *
     * @param int     $post_id  ID do agendamento sendo salvo
     * @param WP_Post $post     Objeto de post
     * @param bool    $update   Indica se é atualização
     */
    public function save_appointment_finalization_meta( $post_id, $post, $update ) {
        // Apenas processa se for do tipo dps_agendamento e se existirem campos no POST
        if ( 'dps_agendamento' !== $post->post_type ) {
            return;
        }
        // Verifica se estamos no contexto certo (formulário do plugin), evitando interferência de outros salvamentos
        if ( ! isset( $_POST['appointment_services_executed'] ) && ! isset( $_POST['appointment_extra_names'] ) ) {
            return;
        }
        // Defesa em profundidade: verifica se o usuário pode editar este post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Serviços executados
        if ( isset( $_POST['appointment_services_executed'] ) && is_array( $_POST['appointment_services_executed'] ) ) {
            $executed_raw = array_map( 'intval', (array) $_POST['appointment_services_executed'] );
            update_post_meta( $post_id, 'appointment_services_executed', $executed_raw );
        } else {
            delete_post_meta( $post_id, 'appointment_services_executed' );
        }
        // Extras
        $extra_names  = isset( $_POST['appointment_extra_names'] ) ? (array) $_POST['appointment_extra_names'] : [];
        $extra_prices = isset( $_POST['appointment_extra_prices'] ) ? (array) $_POST['appointment_extra_prices'] : [];
        $extras = [];
        $sum   = 0;
        foreach ( $extra_names as $idx => $ename ) {
            $name  = sanitize_text_field( $ename );
            $price = 0;
            if ( isset( $extra_prices[ $idx ] ) ) {
                $price = floatval( str_replace( ',', '.', $extra_prices[ $idx ] ) );
                if ( $price < 0 ) {
                    $price = 0;
                }
            }
            if ( $name ) {
                $extras[] = [ 'name' => $name, 'price' => $price ];
                $sum += $price;
            }
        }
        if ( $extras ) {
            update_post_meta( $post_id, 'appointment_extra_services', $extras );
        } else {
            delete_post_meta( $post_id, 'appointment_extra_services' );
        }
        // Atualiza o valor total adicionando extras
        $current_total = get_post_meta( $post_id, 'appointment_total_value', true );
        $current_total = $current_total ? floatval( $current_total ) : 0;
        $new_total = $current_total + $sum;
        update_post_meta( $post_id, 'appointment_total_value', $new_total );
    }

    /**
     * Salva os serviços selecionados e o valor total no agendamento
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_appointment_services_meta( $post_id, $post, $update ) {
        // Verifica se é salvamento automático
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( 'dps_agendamento' !== $post->post_type ) {
            return;
        }
        // Defesa em profundidade: verifica se o usuário pode editar este post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Verifica se há dados de serviços no POST
        if ( isset( $_POST['appointment_services'] ) && is_array( $_POST['appointment_services'] ) ) {
            $service_ids = array_map( 'intval', (array) $_POST['appointment_services'] );
            update_post_meta( $post_id, 'appointment_services', $service_ids );
        } else {
            delete_post_meta( $post_id, 'appointment_services' );
        }
        // Salva preços customizados por serviço, se enviados
        $service_prices = [];
        if ( isset( $_POST['service_price'] ) && is_array( $_POST['service_price'] ) ) {
            foreach ( $_POST['service_price'] as $srv_id => $price ) {
                $srv_id = intval( $srv_id );
                $price  = floatval( $price );
                $service_prices[ $srv_id ] = $price;
            }
            update_post_meta( $post_id, 'appointment_service_prices', $service_prices );
        } else {
            delete_post_meta( $post_id, 'appointment_service_prices' );
        }
        if ( isset( $_POST['appointment_total'] ) ) {
            $total = floatval( $_POST['appointment_total'] );
            update_post_meta( $post_id, 'appointment_total_value', $total );
        }
        
        // === NOVOS CAMPOS DE MÚLTIPLOS EXTRAS ===
        // Salva extras do agendamento simples (novo formato)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitização é feita em parse_extras_from_post()
        $extras_descriptions_raw = isset( $_POST['appointment_extras_descriptions'] ) ? wp_unslash( $_POST['appointment_extras_descriptions'] ) : [];
        $extras_descriptions = is_array( $extras_descriptions_raw ) ? array_map( 'sanitize_text_field', $extras_descriptions_raw ) : [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitização numérica é feita em parse_extras_from_post()
        $extras_values_raw = isset( $_POST['appointment_extras_values'] ) ? wp_unslash( $_POST['appointment_extras_values'] ) : [];
        $extras_values = is_array( $extras_values_raw ) ? $extras_values_raw : [];
        $extras_list = $this->parse_extras_from_post( $extras_descriptions, $extras_values );
        
        if ( ! empty( $extras_list ) ) {
            update_post_meta( $post_id, 'appointment_extras_list', $extras_list );
            // Mantém compatibilidade com formato antigo (primeiro extra)
            update_post_meta( $post_id, 'appointment_extra_description', $extras_list[0]['description'] );
            update_post_meta( $post_id, 'appointment_extra_value', $extras_list[0]['value'] );
        } else {
            delete_post_meta( $post_id, 'appointment_extras_list' );
            // Tenta formato antigo se novo não presente
            $extra_desc = isset( $_POST['appointment_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['appointment_extra_description'] ) ) : '';
            $extra_value = 0;
            if ( isset( $_POST['appointment_extra_value'] ) ) {
                $extra_value = floatval( str_replace( ',', '.', wp_unslash( $_POST['appointment_extra_value'] ) ) );
                if ( $extra_value < 0 ) {
                    $extra_value = 0;
                }
            }
            if ( '' !== $extra_desc || $extra_value > 0 ) {
                update_post_meta( $post_id, 'appointment_extra_description', $extra_desc );
                update_post_meta( $post_id, 'appointment_extra_value', $extra_value );
            } else {
                delete_post_meta( $post_id, 'appointment_extra_description' );
                delete_post_meta( $post_id, 'appointment_extra_value' );
            }
        }
        
        // Salva extras da assinatura (novo formato)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitização é feita em parse_extras_from_post()
        $sub_extras_descriptions_raw = isset( $_POST['subscription_extras_descriptions'] ) ? wp_unslash( $_POST['subscription_extras_descriptions'] ) : [];
        $sub_extras_descriptions = is_array( $sub_extras_descriptions_raw ) ? array_map( 'sanitize_text_field', $sub_extras_descriptions_raw ) : [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitização numérica é feita em parse_extras_from_post()
        $sub_extras_values_raw = isset( $_POST['subscription_extras_values'] ) ? wp_unslash( $_POST['subscription_extras_values'] ) : [];
        $sub_extras_values = is_array( $sub_extras_values_raw ) ? $sub_extras_values_raw : [];
        $sub_extras_list = $this->parse_extras_from_post( $sub_extras_descriptions, $sub_extras_values );
        
        $sub_base = isset( $_POST['subscription_base_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_base_value'] ) ) ) : 0;
        $sub_total = isset( $_POST['subscription_total_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_total_value'] ) ) ) : 0;
        
        if ( $sub_base > 0 ) {
            update_post_meta( $post_id, 'subscription_base_value', max( 0, $sub_base ) );
        } else {
            delete_post_meta( $post_id, 'subscription_base_value' );
        }
        if ( $sub_total > 0 ) {
            update_post_meta( $post_id, 'subscription_total_value', max( 0, $sub_total ) );
        } else {
            delete_post_meta( $post_id, 'subscription_total_value' );
        }
        
        if ( ! empty( $sub_extras_list ) ) {
            update_post_meta( $post_id, 'subscription_extras_list', $sub_extras_list );
            // Mantém compatibilidade
            update_post_meta( $post_id, 'subscription_extra_description', $sub_extras_list[0]['description'] );
            update_post_meta( $post_id, 'subscription_extra_value', $sub_extras_list[0]['value'] );
        } else {
            delete_post_meta( $post_id, 'subscription_extras_list' );
            // Tenta formato antigo
            $sub_extra_desc = isset( $_POST['subscription_extra_description'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_extra_description'] ) ) : '';
            $sub_extra_value = isset( $_POST['subscription_extra_value'] ) ? floatval( str_replace( ',', '.', wp_unslash( $_POST['subscription_extra_value'] ) ) ) : 0;
            if ( '' !== $sub_extra_desc || $sub_extra_value > 0 ) {
                if ( $sub_extra_value < 0 ) {
                    $sub_extra_value = 0;
                }
                update_post_meta( $post_id, 'subscription_extra_description', $sub_extra_desc );
                update_post_meta( $post_id, 'subscription_extra_value', $sub_extra_value );
            } else {
                delete_post_meta( $post_id, 'subscription_extra_description' );
                delete_post_meta( $post_id, 'subscription_extra_value' );
            }
        }

        // Calcula e armazena preço histórico deste agendamento em centavos
        $this->store_booking_totals_snapshot( $post_id );
    }

    /**
     * Converte um valor monetário (string ou float) em inteiro de centavos.
     *
     * @param mixed $value Valor informado.
     * @return int Valor em centavos.
     */
    private function parse_to_cents( $value ) {
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return max( 0, (int) DPS_Money_Helper::parse_brazilian_format( $value ) );
        }

        $raw = is_string( $value ) ? str_replace( ',', '.', $value ) : $value;
        $float = floatval( $raw );

        return max( 0, (int) round( $float * 100 ) );
    }

    /**
     * Calcula o total histórico dos serviços selecionados e salva nos metas
     * `_dps_total_at_booking` (centavos) e `_dps_services_at_booking` (array de pares).
     *
     * @param int         $appointment_id ID do agendamento.
     * @param string|null $context        Contexto opcional do salvamento.
     */
    public function store_booking_totals_snapshot( $appointment_id, $context = null ) {
        $appointment = get_post( $appointment_id );
        if ( ! $appointment || 'dps_agendamento' !== $appointment->post_type ) {
            return;
        }

        $service_ids          = get_post_meta( $appointment_id, 'appointment_services', true );
        $service_ids          = is_array( $service_ids ) ? array_map( 'intval', $service_ids ) : [];
        $service_prices_meta  = get_post_meta( $appointment_id, 'appointment_service_prices', true );
        $service_prices_meta  = is_array( $service_prices_meta ) ? $service_prices_meta : [];
        $services_at_booking  = [];
        $total_cents          = 0;

        foreach ( $service_ids as $sid ) {
            $price_raw = isset( $service_prices_meta[ $sid ] ) ? $service_prices_meta[ $sid ] : get_post_meta( $sid, 'service_price', true );
            $price_cents = $this->parse_to_cents( $price_raw );
            $services_at_booking[] = [ (int) $sid, $price_cents ];
            $total_cents          += $price_cents;
        }

        // Extras e TaxiDog também compõem o valor histórico do atendimento
        $extra_cents = $this->parse_to_cents( get_post_meta( $appointment_id, 'appointment_extra_value', true ) );
        $taxi_cents  = $this->parse_to_cents( get_post_meta( $appointment_id, 'appointment_taxidog_price', true ) );
        $sub_extra   = $this->parse_to_cents( get_post_meta( $appointment_id, 'subscription_extra_value', true ) );

        $total_cents += $extra_cents + $taxi_cents + $sub_extra;

        // Fallback: se não houver serviços mas existir total calculado anteriormente
        if ( $total_cents <= 0 ) {
            $total_cents = $this->parse_to_cents( get_post_meta( $appointment_id, 'appointment_total_value', true ) );
        }

        update_post_meta( $appointment_id, '_dps_total_at_booking', $total_cents );

        if ( ! empty( $services_at_booking ) ) {
            update_post_meta( $appointment_id, '_dps_services_at_booking', $services_at_booking );
        } else {
            delete_post_meta( $appointment_id, '_dps_services_at_booking' );
        }
    }
    
    /**
     * Processa arrays de descrições e valores de extras.
     *
     * Descrições devem ser pré-sanitizadas antes de chamar este método.
     * Valores são sanitizados aqui como floats.
     *
     * @since 1.4.0
     *
     * @param array $descriptions Array de descrições (já sanitizadas).
     * @param array $values       Array de valores (strings ou floats).
     * @return array Lista de extras processados.
     */
    private function parse_extras_from_post( $descriptions, $values ) {
        $extras_list = [];
        
        foreach ( $descriptions as $idx => $description ) {
            // Descrição já vem sanitizada do chamador
            $desc  = (string) $description;
            $value = 0;
            
            if ( isset( $values[ $idx ] ) ) {
                // Sanitiza valor numérico
                $raw_value = is_string( $values[ $idx ] ) ? str_replace( ',', '.', $values[ $idx ] ) : $values[ $idx ];
                $value = floatval( $raw_value );
                if ( $value < 0 ) {
                    $value = 0;
                }
            }
            
            // Adiciona apenas se tiver descrição ou valor
            if ( '' !== $desc || $value > 0 ) {
                $extras_list[] = [
                    'description' => $desc,
                    'value'       => $value,
                ];
            }
        }
        
        return $extras_list;
    }

    /**
     * Enfileira scripts para cálculo de valor total
     */
    public function enqueue_scripts() {
        // Verifica se shortcode base está sendo usado
        if ( ! is_page() ) {
            return;
        }
        // Carrega somente se o plugin base estiver ativo (verifica função de navegação)
        if ( ! shortcode_exists( 'dps_base' ) ) {
            return;
        }
        wp_enqueue_style( 'dps-services-addon-css', plugin_dir_url( __FILE__ ) . 'assets/css/services-addon.css', [], '1.5.4' );
        wp_enqueue_script( 'dps-services-addon-js', plugin_dir_url( __FILE__ ) . 'assets/js/dps-services-addon.js', [ 'jquery' ], '1.5.1', true );
    }

    /**
     * Renderiza o shortcode de catálogo público de serviços.
     *
     * Uso: [dps_services_catalog]
     * Atributos:
     *   - show_prices: 'yes'|'no' (padrão: 'yes') - Exibir preços
     *   - type: 'padrao'|'extra'|'package' - Filtrar por tipo
     *   - category: slug da categoria - Filtrar por categoria
     *   - layout: 'list'|'grid' (padrão: 'list') - Layout de exibição
     *
     * @param array $atts Atributos do shortcode.
     * @return string HTML do catálogo.
     *
     * @since 1.3.0
     */
    public function render_catalog_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'show_prices' => 'yes',
            'type'        => '',
            'category'    => '',
            'layout'      => 'list',
        ], $atts, 'dps_services_catalog' );

        $include_prices = 'yes' === $atts['show_prices'];
        $layout         = in_array( $atts['layout'], [ 'list', 'grid' ], true ) ? $atts['layout'] : 'list';

        // Obtém serviços via API
        $services = DPS_Services_API::get_public_services( [
            'type'           => sanitize_text_field( $atts['type'] ),
            'category'       => sanitize_text_field( $atts['category'] ),
            'include_prices' => $include_prices,
        ] );

        if ( empty( $services ) ) {
            return '<p class="dps-catalog-empty">' . esc_html__( 'Nenhum serviço disponível no momento.', 'dps-services-addon' ) . '</p>';
        }

        // Agrupa por tipo e categoria para melhor organização
        $grouped = [
            'padrao'  => [],
            'package' => [],
            'extra'   => [],
        ];

        foreach ( $services as $service ) {
            $type = $service['type'] ?: 'extra';
            if ( ! isset( $grouped[ $type ] ) ) {
                $type = 'extra';
            }
            $grouped[ $type ][] = $service;
        }

        $categories = DPS_Services_API::get_service_categories();
        $type_labels = [
            'padrao'  => __( 'Serviços Principais', 'dps-services-addon' ),
            'package' => __( 'Pacotes Promocionais', 'dps-services-addon' ),
            'extra'   => __( 'Serviços Extras', 'dps-services-addon' ),
        ];

        ob_start();
        ?>
        <div class="dps-services-catalog dps-catalog-<?php echo esc_attr( $layout ); ?>">
            <?php foreach ( [ 'padrao', 'package', 'extra' ] as $type ) : ?>
                <?php if ( ! empty( $grouped[ $type ] ) ) : ?>
                    <div class="dps-catalog-section dps-catalog-type-<?php echo esc_attr( $type ); ?>">
                        <h3 class="dps-catalog-section-title"><?php echo esc_html( $type_labels[ $type ] ?? $type ); ?></h3>
                        
                        <?php if ( 'grid' === $layout ) : ?>
                            <div class="dps-catalog-grid">
                        <?php endif; ?>

                        <?php
                        // Agrupa extras por categoria
                        if ( 'extra' === $type ) :
                            $by_category = [];
                            foreach ( $grouped[ $type ] as $service ) {
                                $cat = $service['category'] ?: 'outros';
                                $by_category[ $cat ][] = $service;
                            }
                            foreach ( $by_category as $cat_key => $cat_services ) :
                                $cat_label = $categories[ $cat_key ] ?? ucfirst( $cat_key );
                                ?>
                                <div class="dps-catalog-category">
                                    <h4 class="dps-catalog-category-title"><?php echo esc_html( $cat_label ); ?></h4>
                                    <?php foreach ( $cat_services as $service ) : ?>
                                        <?php echo $this->render_catalog_item( $service, $include_prices, $layout ); ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php foreach ( $grouped[ $type ] as $service ) : ?>
                                <?php echo $this->render_catalog_item( $service, $include_prices, $layout ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ( 'grid' === $layout ) : ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php

        // Adiciona estilos inline para o catálogo
        $this->render_catalog_styles();

        return ob_get_clean();
    }

    /**
     * Renderiza um item individual do catálogo.
     *
     * @param array  $service        Dados do serviço.
     * @param bool   $include_prices Incluir preços na exibição.
     * @param string $layout         Layout ('list' ou 'grid').
     * @return string HTML do item.
     *
     * @since 1.3.0
     */
    private function render_catalog_item( $service, $include_prices, $layout ) {
        $is_package = 'package' === $service['type'];
        $has_discount = $is_package && ! empty( $service['package_discount'] ) && $service['package_discount'] > 0;

        ob_start();
        ?>
        <div class="dps-catalog-item <?php echo esc_attr( $is_package ? 'dps-catalog-package' : '' ); ?>">
            <div class="dps-catalog-item-content">
                <h4 class="dps-catalog-item-title">
                    <?php echo esc_html( $service['title'] ); ?>
                    <?php if ( $has_discount ) : ?>
                        <span class="dps-catalog-discount-badge"><?php echo esc_html( sprintf( __( '-%d%%', 'dps-services-addon' ), $service['package_discount'] ) ); ?></span>
                    <?php endif; ?>
                </h4>
                
                <?php if ( ! empty( $service['description'] ) ) : ?>
                    <p class="dps-catalog-item-description"><?php echo esc_html( $service['description'] ); ?></p>
                <?php endif; ?>

                <?php if ( $is_package && ! empty( $service['package_items'] ) ) : ?>
                    <div class="dps-catalog-package-items">
                        <span class="dps-catalog-includes"><?php esc_html_e( 'Inclui:', 'dps-services-addon' ); ?></span>
                        <?php
                        $item_names = [];
                        foreach ( $service['package_items'] as $item_id ) {
                            $item = DPS_Services_API::get_service( $item_id );
                            if ( $item ) {
                                $item_names[] = $item['title'];
                            }
                        }
                        echo esc_html( implode( ', ', $item_names ) );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ( $include_prices ) : ?>
                <div class="dps-catalog-item-price">
                    <?php
                    // Determina o melhor preço a exibir
                    if ( $is_package && ! empty( $service['package_fixed_price'] ) ) {
                        $price_display = sprintf( 'R$ %s', number_format_i18n( $service['package_fixed_price'], 2 ) );
                    } elseif ( $is_package ) {
                        // Calcula preço do pacote
                        $package_price = DPS_Services_API::calculate_package_price( $service['id'] );
                        $price_display = sprintf( 'R$ %s', number_format_i18n( $package_price, 2 ) );
                    } else {
                        // Verifica se tem variação de preço por porte
                        $prices = array_filter( [
                            $service['price'] ?? 0,
                            $service['price_small'] ?? null,
                            $service['price_medium'] ?? null,
                            $service['price_large'] ?? null,
                        ], function( $p ) { return null !== $p && $p > 0; } );

                        if ( count( $prices ) > 1 ) {
                            $min = min( $prices );
                            $max = max( $prices );
                            if ( abs( $min - $max ) > 0.01 ) {
                                $price_display = sprintf(
                                    __( 'A partir de R$ %s', 'dps-services-addon' ),
                                    number_format_i18n( $min, 2 )
                                );
                            } else {
                                $price_display = sprintf( 'R$ %s', number_format_i18n( $min, 2 ) );
                            }
                        } else {
                            $price_display = sprintf( 'R$ %s', number_format_i18n( $service['price'] ?? 0, 2 ) );
                        }
                    }
                    echo '<span class="dps-catalog-price">' . esc_html( $price_display ) . '</span>';
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza estilos CSS para o catálogo público.
     *
     * @since 1.3.0
     */
    private function render_catalog_styles() {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        ?>
        <style>
        .dps-services-catalog {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .dps-catalog-section {
            margin-bottom: 32px;
        }
        .dps-catalog-section-title {
            font-size: 20px;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .dps-catalog-category {
            margin-bottom: 24px;
        }
        .dps-catalog-category-title {
            font-size: 16px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 12px;
        }
        .dps-catalog-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 12px;
            background: #fff;
            transition: box-shadow 0.2s ease;
        }
        .dps-catalog-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .dps-catalog-package {
            border-left: 4px solid #0ea5e9;
            background: #f0f9ff;
        }
        .dps-catalog-item-title {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 4px 0;
        }
        .dps-catalog-discount-badge {
            display: inline-block;
            background: #ef4444;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }
        .dps-catalog-item-description {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
        }
        .dps-catalog-package-items {
            font-size: 13px;
            color: #6b7280;
            margin-top: 8px;
        }
        .dps-catalog-includes {
            font-weight: 600;
            color: #374151;
        }
        .dps-catalog-item-price {
            text-align: right;
            white-space: nowrap;
        }
        .dps-catalog-price {
            font-size: 18px;
            font-weight: 700;
            color: #0ea5e9;
        }
        .dps-catalog-empty {
            text-align: center;
            color: #6b7280;
            padding: 40px;
        }
        /* Grid layout */
        .dps-catalog-grid .dps-catalog-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .dps-catalog-grid .dps-catalog-item {
            flex-direction: column;
        }
        .dps-catalog-grid .dps-catalog-item-price {
            text-align: left;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
        /* Responsive */
        @media (max-width: 768px) {
            .dps-catalog-item {
                flex-direction: column;
            }
            .dps-catalog-item-price {
                text-align: left;
                margin-top: 12px;
            }
        }
        </style>
        <?php
    }
}

/**
 * Inicializa o add-on após o hook 'init' para garantir que o text domain seja carregado primeiro.
 * Usa prioridade 5 para rodar após o carregamento do text domain (prioridade 1) mas antes
 * dos métodos de registro que usam prioridade padrão (10).
 */
function dps_services_addon_init() {
	static $instance = null;
	
	if ( null === $instance ) {
		$instance = new DPS_Services_Addon();
	}
	
	return $instance;
}
add_action( 'init', 'dps_services_addon_init', 5 );
