<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Helper para registrar Custom Post Types com opções padronizadas.
 */
class DPS_CPT_Helper {

    /**
     * Slug do CPT.
     *
     * @var string
     */
    private $slug;

    /**
     * Labels de internacionalização.
     *
     * @var array
     */
    private $labels;

    /**
     * Opções padrão do CPT.
     *
     * @var array
     */
    private $default_args;

    /**
     * Constrói o helper com slug, labels e argumentos padrão.
     *
     * @param string $slug         Slug do post type.
     * @param array  $labels       Labels a serem usados no registro.
     * @param array  $default_args Argumentos padrão do `register_post_type`.
     */
    public function __construct( $slug, array $labels, array $default_args = [] ) {
        $this->slug         = $slug;
        $this->labels       = $labels;
        $this->default_args = wp_parse_args(
            $default_args,
            [
                'public'       => false,
                'show_ui'      => false,
                'supports'     => [ 'title' ],
                'hierarchical' => false,
                'has_archive'  => false,
            ]
        );
    }

    /**
     * Executa o registro do CPT com argumentos opcionais adicionais.
     *
     * @param array $args Argumentos adicionais ou sobrescritos.
     */
    public function register( array $args = [] ) {
        $final_args = wp_parse_args( $args, $this->default_args );
        $final_args['labels'] = $this->labels;

        register_post_type( $this->slug, $final_args );
    }
}
