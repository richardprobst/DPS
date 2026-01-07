<?php
/**
 * Base de Conhecimento do AI Add-on.
 *
 * Gerencia artigos e FAQs que são incluídos no contexto da IA
 * para respostas mais precisas e personalizadas.
 *
 * @package DPS_AI_Addon
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Base de Conhecimento.
 */
class DPS_AI_Knowledge_Base {

    /**
     * Slug do Custom Post Type.
     *
     * @var string
     */
    const POST_TYPE = 'dps_ai_knowledge';

    /**
     * Taxonomia para categorias de conhecimento.
     *
     * @var string
     */
    const TAXONOMY = 'dps_ai_knowledge_cat';

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Knowledge_Base|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Knowledge_Base
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
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta_boxes' ] );
    }

    /**
     * Registra o Custom Post Type para Base de Conhecimento.
     */
    public function register_post_type() {
        $labels = [
            'name'               => __( 'Base de Conhecimento IA', 'dps-ai' ),
            'singular_name'      => __( 'Artigo de Conhecimento', 'dps-ai' ),
            'menu_name'          => __( 'Conhecimento IA', 'dps-ai' ),
            'add_new'            => __( 'Adicionar Novo', 'dps-ai' ),
            'add_new_item'       => __( 'Adicionar Novo Artigo', 'dps-ai' ),
            'edit_item'          => __( 'Editar Artigo', 'dps-ai' ),
            'new_item'           => __( 'Novo Artigo', 'dps-ai' ),
            'view_item'          => __( 'Ver Artigo', 'dps-ai' ),
            'search_items'       => __( 'Buscar Artigos', 'dps-ai' ),
            'not_found'          => __( 'Nenhum artigo encontrado', 'dps-ai' ),
            'not_found_in_trash' => __( 'Nenhum artigo na lixeira', 'dps-ai' ),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'desi-pet-shower',
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 100,
            'supports'            => [ 'title', 'editor' ],
            'show_in_rest'        => true,
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Registra a taxonomia de categorias.
     */
    public function register_taxonomy() {
        $labels = [
            'name'              => __( 'Categorias de Conhecimento', 'dps-ai' ),
            'singular_name'     => __( 'Categoria', 'dps-ai' ),
            'search_items'      => __( 'Buscar Categorias', 'dps-ai' ),
            'all_items'         => __( 'Todas as Categorias', 'dps-ai' ),
            'edit_item'         => __( 'Editar Categoria', 'dps-ai' ),
            'update_item'       => __( 'Atualizar Categoria', 'dps-ai' ),
            'add_new_item'      => __( 'Adicionar Nova Categoria', 'dps-ai' ),
            'new_item_name'     => __( 'Nome da Nova Categoria', 'dps-ai' ),
            'menu_name'         => __( 'Categorias', 'dps-ai' ),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
        ];

        register_taxonomy( self::TAXONOMY, self::POST_TYPE, $args );

        // Registra termos padrão apenas se a flag não estiver setada (evita verificação em todo page load)
        if ( ! get_option( 'dps_ai_kb_terms_created' ) ) {
            self::create_default_terms();
            update_option( 'dps_ai_kb_terms_created', true );
        }
    }

    /**
     * Cria termos padrão da taxonomia.
     * Chamado apenas uma vez durante a primeira inicialização.
     */
    private static function create_default_terms() {
        $default_terms = [
            'servicos'     => __( 'Serviços', 'dps-ai' ),
            'agendamento'  => __( 'Agendamento', 'dps-ai' ),
            'pagamentos'   => __( 'Pagamentos', 'dps-ai' ),
            'fidelidade'   => __( 'Fidelidade', 'dps-ai' ),
            'cuidados-pet' => __( 'Cuidados com Pet', 'dps-ai' ),
            'politicas'    => __( 'Políticas', 'dps-ai' ),
        ];

        foreach ( $default_terms as $slug => $name ) {
            if ( ! term_exists( $slug, self::TAXONOMY ) ) {
                wp_insert_term( $name, self::TAXONOMY, [ 'slug' => $slug ] );
            }
        }
    }

    /**
     * Adiciona meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'dps_ai_knowledge_settings',
            __( 'Configurações do Artigo', 'dps-ai' ),
            [ $this, 'render_settings_meta_box' ],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'dps_ai_knowledge_keywords',
            __( 'Palavras-chave de Ativação', 'dps-ai' ),
            [ $this, 'render_keywords_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Renderiza meta box de configurações.
     *
     * @param WP_Post $post Post atual.
     */
    public function render_settings_meta_box( $post ) {
        wp_nonce_field( 'dps_ai_knowledge_save', 'dps_ai_knowledge_nonce' );

        $priority = get_post_meta( $post->ID, '_dps_ai_priority', true );
        $active   = get_post_meta( $post->ID, '_dps_ai_active', true );

        if ( '' === $priority ) {
            $priority = 5;
        }
        if ( '' === $active ) {
            $active = '1';
        }
        ?>
        <p>
            <label>
                <input type="checkbox" name="dps_ai_active" value="1" <?php checked( $active, '1' ); ?> />
                <?php esc_html_e( 'Artigo ativo', 'dps-ai' ); ?>
            </label>
        </p>
        <p>
            <label for="dps_ai_priority"><?php esc_html_e( 'Prioridade (1-10):', 'dps-ai' ); ?></label>
            <input type="number" id="dps_ai_priority" name="dps_ai_priority" value="<?php echo esc_attr( $priority ); ?>" min="1" max="10" class="small-text" />
        </p>
        <p class="description"><?php esc_html_e( 'Artigos com maior prioridade aparecem primeiro no contexto da IA.', 'dps-ai' ); ?></p>
        <?php
    }

    /**
     * Renderiza meta box de palavras-chave.
     *
     * @param WP_Post $post Post atual.
     */
    public function render_keywords_meta_box( $post ) {
        $keywords = get_post_meta( $post->ID, '_dps_ai_keywords', true );
        ?>
        <p>
            <label for="dps_ai_keywords"><?php esc_html_e( 'Palavras-chave (separadas por vírgula):', 'dps-ai' ); ?></label>
        </p>
        <p>
            <textarea id="dps_ai_keywords" name="dps_ai_keywords" rows="3" class="large-text"><?php echo esc_textarea( $keywords ); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e( 'Quando uma pergunta do cliente contiver alguma dessas palavras-chave, este artigo será incluído no contexto da IA.', 'dps-ai' ); ?>
            <br />
            <?php esc_html_e( 'Exemplo: banho, preço banho, quanto custa banho', 'dps-ai' ); ?>
        </p>
        <?php
    }

    /**
     * Salva meta boxes.
     *
     * @param int $post_id ID do post.
     */
    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['dps_ai_knowledge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dps_ai_knowledge_nonce'] ) ), 'dps_ai_knowledge_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Salva campos
        update_post_meta( $post_id, '_dps_ai_active', isset( $_POST['dps_ai_active'] ) ? '1' : '0' );
        update_post_meta( $post_id, '_dps_ai_priority', isset( $_POST['dps_ai_priority'] ) ? absint( $_POST['dps_ai_priority'] ) : 5 );
        update_post_meta( $post_id, '_dps_ai_keywords', isset( $_POST['dps_ai_keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dps_ai_keywords'] ) ) : '' );
    }

    /**
     * Busca artigos relevantes para uma pergunta.
     *
     * @param string $question Pergunta do cliente.
     * @param int    $limit    Limite de artigos.
     *
     * @return array Artigos relevantes formatados para o contexto.
     */
    public static function get_relevant_articles( $question, $limit = 3 ) {
        // Cast para string para compatibilidade com PHP 8.1+
        $question_lower = mb_strtolower( (string) $question, 'UTF-8' );
        $relevant       = [];

        // Busca todos os artigos ativos
        $articles = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_dps_ai_active',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_dps_ai_priority',
            'order'          => 'DESC',
        ] );

        foreach ( $articles as $article ) {
            $keywords = get_post_meta( $article->ID, '_dps_ai_keywords', true );
            if ( empty( $keywords ) ) {
                continue;
            }

            // Verifica se alguma palavra-chave está na pergunta
            $keywords_array = array_map( 'trim', explode( ',', mb_strtolower( $keywords, 'UTF-8' ) ) );
            $matches        = false;

            foreach ( $keywords_array as $keyword ) {
                if ( ! empty( $keyword ) && false !== mb_strpos( $question_lower, $keyword ) ) {
                    $matches = true;
                    break;
                }
            }

            if ( $matches ) {
                $priority = absint( get_post_meta( $article->ID, '_dps_ai_priority', true ) );
                $relevant[] = [
                    'priority' => $priority,
                    'title'    => $article->post_title,
                    'content'  => wp_strip_all_tags( $article->post_content ),
                ];
            }
        }

        // Ordena por prioridade e limita
        usort( $relevant, function( $a, $b ) {
            return $b['priority'] - $a['priority'];
        } );

        return array_slice( $relevant, 0, $limit );
    }

    /**
     * Formata artigos para inclusão no contexto da IA.
     *
     * @param array $articles Artigos retornados por get_relevant_articles().
     *
     * @return string Texto formatado para o contexto.
     */
    public static function format_articles_for_context( array $articles ) {
        if ( empty( $articles ) ) {
            return '';
        }

        $context = "\nINFORMAÇÕES DA BASE DE CONHECIMENTO:\n";

        foreach ( $articles as $article ) {
            $context .= "\n--- {$article['title']} ---\n";
            $context .= $article['content'] . "\n";
        }

        return $context;
    }

    /**
     * Obtém sugestões de perguntas frequentes.
     *
     * @param int $limit Limite de sugestões.
     *
     * @return array Lista de perguntas sugeridas.
     */
    public static function get_faq_suggestions( $limit = 5 ) {
        $settings = get_option( 'dps_ai_settings', [] );
        $custom_faqs = ! empty( $settings['faq_suggestions'] ) ? $settings['faq_suggestions'] : '';

        // FAQs padrão
        $default_faqs = [
            __( 'Qual o horário de funcionamento?', 'dps-ai' ),
            __( 'Quanto custa um banho?', 'dps-ai' ),
            __( 'Como agendar um serviço?', 'dps-ai' ),
            __( 'Meu pet precisa de vacina em dia?', 'dps-ai' ),
            __( 'Como funciona o programa de fidelidade?', 'dps-ai' ),
        ];

        // Se há FAQs customizados, usa eles
        if ( ! empty( $custom_faqs ) ) {
            $custom_array = array_filter( array_map( 'trim', explode( "\n", $custom_faqs ) ) );
            if ( ! empty( $custom_array ) ) {
                return array_slice( $custom_array, 0, $limit );
            }
        }

        return array_slice( $default_faqs, 0, $limit );
    }
}
