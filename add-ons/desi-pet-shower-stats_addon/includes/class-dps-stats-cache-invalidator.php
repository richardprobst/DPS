<?php
/**
 * Cache Invalidator para Stats Add-on
 *
 * Gerencia a invalidação automática de transients quando dados relacionados são alterados.
 *
 * @package DPS_Stats_Addon
 * @since 1.2.0
 */

// Bloqueia acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Stats_Cache_Invalidator
 *
 * Responsável por invalidar automaticamente o cache de estatísticas quando:
 * - Agendamentos são criados, editados ou deletados
 * - Clientes são criados ou editados
 * - Pets são criados ou editados
 * - Transações financeiras são alteradas (via hooks do Finance Add-on)
 *
 * @since 1.2.0
 */
class DPS_Stats_Cache_Invalidator {

    /**
     * Inicializa os hooks de invalidação.
     *
     * @since 1.2.0
     */
    public static function init() {
        // Invalidar quando agendamentos mudarem
        add_action( 'save_post_dps_agendamento', [ __CLASS__, 'invalidate_on_appointment_change' ], 10, 3 );
        add_action( 'before_delete_post', [ __CLASS__, 'invalidate_on_post_delete' ], 10, 2 );
        add_action( 'trashed_post', [ __CLASS__, 'invalidate_on_post_delete' ], 10, 2 );
        
        // Invalidar quando clientes mudarem
        add_action( 'save_post_dps_cliente', [ __CLASS__, 'invalidate_on_client_change' ], 10, 3 );
        
        // Invalidar quando pets mudarem
        add_action( 'save_post_dps_pet', [ __CLASS__, 'invalidate_on_pet_change' ], 10, 3 );
        
        // Invalidar quando assinaturas mudarem
        add_action( 'save_post_dps_subscription', [ __CLASS__, 'invalidate_on_subscription_change' ], 10, 3 );
        
        // Hooks do Finance Add-on (se existirem)
        add_action( 'dps_finance_transaction_created', [ __CLASS__, 'invalidate_on_financial_change' ] );
        add_action( 'dps_finance_transaction_updated', [ __CLASS__, 'invalidate_on_financial_change' ] );
        add_action( 'dps_finance_transaction_deleted', [ __CLASS__, 'invalidate_on_financial_change' ] );
    }

    /**
     * Invalidar cache quando agendamento muda.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Se é atualização ou criação.
     *
     * @since 1.2.0
     */
    public static function invalidate_on_appointment_change( $post_id, $post, $update ) {
        // Evitar auto-save e revisões
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        self::invalidate_all_with_throttle();
    }

    /**
     * Invalidar cache quando cliente muda.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Se é atualização ou criação.
     *
     * @since 1.2.0
     */
    public static function invalidate_on_client_change( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        // Apenas invalida transients de novos clientes
        self::delete_transients_by_pattern( 'dps_stats_new_clients_' );
    }

    /**
     * Invalidar cache quando pet muda.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Se é atualização ou criação.
     *
     * @since 1.2.0
     */
    public static function invalidate_on_pet_change( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        // Invalida transients de pets inativos e distribuição
        self::delete_transients_by_pattern( 'dps_stats_inactive_pets_' );
        self::delete_transients_by_pattern( 'dps_stats_species_' );
        self::delete_transients_by_pattern( 'dps_stats_top_breeds_' );
    }

    /**
     * Invalidar cache quando assinatura muda.
     *
     * @param int     $post_id ID do post.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Se é atualização ou criação.
     *
     * @since 1.2.0
     */
    public static function invalidate_on_subscription_change( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        
        // Invalida métricas financeiras (assinaturas afetam receita)
        self::delete_transients_by_pattern( 'dps_stats_financial_' );
        self::delete_transients_by_pattern( 'dps_stats_comparison_' );
    }

    /**
     * Invalidar cache quando post é deletado.
     *
     * @param int    $post_id ID do post.
     * @param object $post    Objeto do post (pode ser null em alguns contextos).
     *
     * @since 1.2.0
     */
    public static function invalidate_on_post_delete( $post_id, $post = null ) {
        if ( ! $post ) {
            $post = get_post( $post_id );
        }
        
        if ( ! $post ) {
            return;
        }
        
        // Verificar tipo de post
        if ( in_array( $post->post_type, [ 'dps_agendamento', 'dps_cliente', 'dps_pet', 'dps_subscription' ], true ) ) {
            self::invalidate_all_with_throttle();
        }
    }

    /**
     * Invalidar cache quando transação financeira muda (via Finance Add-on).
     *
     * @since 1.2.0
     */
    public static function invalidate_on_financial_change() {
        // Invalida métricas financeiras
        self::delete_transients_by_pattern( 'dps_stats_financial_' );
        self::delete_transients_by_pattern( 'dps_stats_total_revenue_' );
        self::delete_transients_by_pattern( 'dps_stats_comparison_' );
    }

    /**
     * Invalida todos os transients com throttle.
     *
     * Para evitar invalidações excessivas em saves múltiplos,
     * só invalida se não tiver sido invalidado nos últimos 30 segundos.
     *
     * @since 1.2.0
     */
    private static function invalidate_all_with_throttle() {
        $throttle_key = 'dps_stats_invalidated_recently';
        
        // Verificar se já invalidou recentemente
        if ( get_transient( $throttle_key ) ) {
            return; // Não invalida de novo
        }
        
        // Invalidar todos os transients
        self::delete_transients_by_pattern( 'dps_stats_' );
        
        // Setar throttle por 30 segundos
        set_transient( $throttle_key, true, 30 );
    }

    /**
     * Deleta transients que correspondem a um padrão.
     *
     * @param string $pattern Padrão para buscar (ex: 'dps_stats_').
     *
     * @since 1.2.0
     */
    private static function delete_transients_by_pattern( $pattern ) {
        global $wpdb;
        
        $transient_prefix = $wpdb->esc_like( '_transient_' . $pattern ) . '%';
        $transient_timeout = $wpdb->esc_like( '_transient_timeout_' . $pattern ) . '%';
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s",
            $transient_prefix,
            $transient_timeout
        ) );
    }
}

// Inicializar invalidador
DPS_Stats_Cache_Invalidator::init();
