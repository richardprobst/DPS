<?php
/**
 * Interface Administrativa para Visualizar Histórico de Conversas.
 *
 * Responsável por:
 * - Registrar página administrativa de conversas
 * - Listar conversas com filtros
 * - Exibir detalhes de conversas individuais
 * - Controlar permissões de acesso
 *
 * @package DPS_AI_Addon
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe administrativa de conversas.
 */
class DPS_AI_Conversations_Admin {

    /**
     * Instância única (singleton).
     *
     * @var DPS_AI_Conversations_Admin|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_AI_Conversations_Admin
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
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 25 );
    }

    /**
     * Registra submenu admin para histórico de conversas.
     * 
     * NOTA: Menu exibido como submenu de "desi.pet by PRObst" para alinhamento com a navegação unificada.
     * Também acessível pelo hub em dps-ai-hub (aba "Conversas").
     */
    public function register_admin_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Histórico de Conversas IA', 'dps-ai' ),
            __( 'Conversas IA', 'dps-ai' ),
            'manage_options',
            'dps-ai-conversations',
            [ $this, 'render_conversations_list_page' ]
        );
    }

    /**
     * Renderiza a página de listagem de conversas.
     */
    public function render_conversations_list_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'dps-ai' ) );
        }

        // Verifica se está visualizando uma conversa específica
        if ( isset( $_GET['conversation_id'] ) ) {
            $this->render_conversation_detail_page();
            return;
        }

        // Obtém filtros com validação
        $channel    = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
        if ( $channel && ! in_array( $channel, DPS_AI_Conversations_Repository::VALID_CHANNELS, true ) ) {
            $channel = '';
        }

        $status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        if ( $status && ! in_array( $status, [ 'open', 'closed' ], true ) ) {
            $status = '';
        }

        // Valida e sanitiza datas
        $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        if ( $start_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
            $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        }

        $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : current_time( 'Y-m-d' );
        if ( $end_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
            $end_date = current_time( 'Y-m-d' );
        }

        // Paginação
        $per_page = 20;
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset   = ( $paged - 1 ) * $per_page;

        // Busca conversas
        $repo = DPS_AI_Conversations_Repository::get_instance();
        $args = [
            'channel'    => $channel,
            'status'     => $status,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'limit'      => $per_page,
            'offset'     => $offset,
        ];

        $conversations = $repo->list_conversations( $args );
        $total         = $repo->count_conversations( $args );
        $total_pages   = ceil( $total / $per_page );

        // Renderiza a página
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Visualize o histórico de conversas com o assistente de IA em todos os canais.', 'dps-ai' ); ?></p>

            <!-- Filtros -->
            <form method="get" action="">
                <input type="hidden" name="page" value="dps-ai-conversations" />
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end; margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    <div>
                        <label for="filter-channel" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e( 'Canal', 'dps-ai' ); ?>
                        </label>
                        <select id="filter-channel" name="channel">
                            <option value=""><?php esc_html_e( 'Todos os canais', 'dps-ai' ); ?></option>
                            <option value="web_chat" <?php selected( $channel, 'web_chat' ); ?>><?php esc_html_e( 'Chat Público', 'dps-ai' ); ?></option>
                            <option value="portal" <?php selected( $channel, 'portal' ); ?>><?php esc_html_e( 'Portal do Cliente', 'dps-ai' ); ?></option>
                            <option value="whatsapp" <?php selected( $channel, 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'dps-ai' ); ?></option>
                            <option value="admin_specialist" <?php selected( $channel, 'admin_specialist' ); ?>><?php esc_html_e( 'Modo Especialista', 'dps-ai' ); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-status" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e( 'Status', 'dps-ai' ); ?>
                        </label>
                        <select id="filter-status" name="status">
                            <option value=""><?php esc_html_e( 'Todos', 'dps-ai' ); ?></option>
                            <option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Abertas', 'dps-ai' ); ?></option>
                            <option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Fechadas', 'dps-ai' ); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-start-date" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e( 'Data inicial', 'dps-ai' ); ?>
                        </label>
                        <input type="date" id="filter-start-date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                    </div>

                    <div>
                        <label for="filter-end-date" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e( 'Data final', 'dps-ai' ); ?>
                        </label>
                        <input type="date" id="filter-end-date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                    </div>

                    <div>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-filter" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php esc_html_e( 'Filtrar', 'dps-ai' ); ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Estatísticas resumidas -->
            <?php if ( $total > 0 ) : ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 4px;">
                    <p style="margin: 0;">
                        <?php
                        printf(
                            /* translators: %s: número de conversas encontradas */
                            esc_html( _n( '%s conversa encontrada', '%s conversas encontradas', $total, 'dps-ai' ) ),
                            '<strong>' . number_format_i18n( $total ) . '</strong>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Tabela de conversas -->
            <?php if ( empty( $conversations ) ) : ?>
                <div style="padding: 40px; text-align: center; background: #fff; border: 1px solid #e5e7eb; border-radius: 4px;">
                    <span class="dashicons dashicons-admin-comments" style="font-size: 48px; color: #d1d5db; margin-bottom: 10px;"></span>
                    <p style="color: #6b7280; margin: 0;">
                        <?php esc_html_e( 'Nenhuma conversa encontrada com os filtros aplicados.', 'dps-ai' ); ?>
                    </p>
                </div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Cliente/Visitante', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Canal', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Data de Início', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Última Atividade', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dps-ai' ); ?></th>
                            <th><?php esc_html_e( 'Ações', 'dps-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $conversations as $conversation ) : ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $conversation->id ); ?></strong></td>
                                <td>
                                    <?php
                                    if ( $conversation->customer_id ) {
                                        $customer = get_post( $conversation->customer_id );
                                        if ( $customer ) {
                                            echo esc_html( $customer->post_title );
                                        } else {
                                            printf(
                                                /* translators: %d: ID do cliente */
                                                esc_html__( 'Cliente #%d', 'dps-ai' ),
                                                (int) $conversation->customer_id
                                            );
                                        }
                                    } else {
                                        echo '<span style="color: #6b7280;">' . esc_html__( 'Visitante não identificado', 'dps-ai' ) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $channel_labels = [
                                        'web_chat'         => __( 'Chat Público', 'dps-ai' ),
                                        'portal'           => __( 'Portal do Cliente', 'dps-ai' ),
                                        'whatsapp'         => __( 'WhatsApp', 'dps-ai' ),
                                        'admin_specialist' => __( 'Modo Especialista', 'dps-ai' ),
                                    ];
                                    echo esc_html( $channel_labels[ $conversation->channel ] ?? $conversation->channel );
                                    ?>
                                </td>
                                <td><?php echo esc_html( gmdate( 'd/m/Y H:i', strtotime( $conversation->started_at ) ) ); ?></td>
                                <td><?php echo esc_html( gmdate( 'd/m/Y H:i', strtotime( $conversation->last_activity_at ) ) ); ?></td>
                                <td>
                                    <?php if ( 'open' === $conversation->status ) : ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #d1fae5; color: #065f46; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                            <?php esc_html_e( 'Aberta', 'dps-ai' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span style="display: inline-block; padding: 2px 8px; background: #f3f4f6; color: #374151; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                            <?php esc_html_e( 'Fechada', 'dps-ai' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'conversation_id' => $conversation->id ], admin_url( 'admin.php?page=dps-ai-conversations' ) ) ); ?>" class="button button-small">
                                        <span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 3px;"></span>
                                        <?php esc_html_e( 'Ver Detalhes', 'dps-ai' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginação -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post(
                                paginate_links( [
                                    'base'      => add_query_arg( 'paged', '%#%' ),
                                    'format'    => '',
                                    'current'   => $paged,
                                    'total'     => $total_pages,
                                    'prev_text' => __( '&laquo; Anterior', 'dps-ai' ),
                                    'next_text' => __( 'Próxima &raquo;', 'dps-ai' ),
                                ] )
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza a página de detalhes de uma conversa.
     */
    private function render_conversation_detail_page() {
        $conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : 0;
        if ( ! $conversation_id ) {
            wp_die( esc_html__( 'ID de conversa inválido.', 'dps-ai' ) );
        }

        $repo         = DPS_AI_Conversations_Repository::get_instance();
        $conversation = $repo->get_conversation( $conversation_id );

        if ( ! $conversation ) {
            wp_die( esc_html__( 'Conversa não encontrada.', 'dps-ai' ) );
        }

        $messages = $repo->get_messages( $conversation_id, 'ASC' );

        // Labels de canal
        $channel_labels = [
            'web_chat'         => __( 'Chat Público', 'dps-ai' ),
            'portal'           => __( 'Portal do Cliente', 'dps-ai' ),
            'whatsapp'         => __( 'WhatsApp', 'dps-ai' ),
            'admin_specialist' => __( 'Modo Especialista', 'dps-ai' ),
        ];

        ?>
        <div class="wrap">
            <h1>
                <?php
                printf(
                    /* translators: %d: ID da conversa */
                    esc_html__( 'Conversa #%d', 'dps-ai' ),
                    (int) $conversation->id
                );
                ?>
            </h1>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=dps-ai-conversations' ) ); ?>">
                    <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Voltar à listagem', 'dps-ai' ); ?>
                </a>
            </p>

            <!-- Informações da conversa -->
            <div style="background: #fff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 4px; margin-bottom: 20px;">
                <h2 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;"><?php esc_html_e( 'Informações', 'dps-ai' ); ?></h2>
                
                <table class="form-table" role="presentation" style="margin-top: 0;">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cliente/Visitante', 'dps-ai' ); ?></th>
                            <td>
                                <?php
                                if ( $conversation->customer_id ) {
                                    $customer = get_post( $conversation->customer_id );
                                    if ( $customer ) {
                                        echo esc_html( $customer->post_title );
                                        echo ' <span style="color: #6b7280;">(ID: ' . (int) $conversation->customer_id . ')</span>';
                                    } else {
                                        printf(
                                            /* translators: %d: ID do cliente */
                                            esc_html__( 'Cliente #%d', 'dps-ai' ),
                                            (int) $conversation->customer_id
                                        );
                                    }
                                } else {
                                    echo '<span style="color: #6b7280;">' . esc_html__( 'Visitante não identificado', 'dps-ai' ) . '</span>';
                                    if ( $conversation->session_identifier ) {
                                        echo '<br><small>' . esc_html( $conversation->session_identifier ) . '</small>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Canal', 'dps-ai' ); ?></th>
                            <td><?php echo esc_html( $channel_labels[ $conversation->channel ] ?? $conversation->channel ); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Data de Início', 'dps-ai' ); ?></th>
                            <td><?php echo esc_html( gmdate( 'd/m/Y H:i:s', strtotime( $conversation->started_at ) ) ); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Última Atividade', 'dps-ai' ); ?></th>
                            <td><?php echo esc_html( gmdate( 'd/m/Y H:i:s', strtotime( $conversation->last_activity_at ) ) ); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Status', 'dps-ai' ); ?></th>
                            <td>
                                <?php if ( 'open' === $conversation->status ) : ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #d1fae5; color: #065f46; border-radius: 3px; font-weight: 600;">
                                        <?php esc_html_e( 'Aberta', 'dps-ai' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="display: inline-block; padding: 3px 10px; background: #f3f4f6; color: #374151; border-radius: 3px; font-weight: 600;">
                                        <?php esc_html_e( 'Fechada', 'dps-ai' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Total de Mensagens', 'dps-ai' ); ?></th>
                            <td><strong><?php echo esc_html( count( $messages ) ); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Histórico de mensagens -->
            <div style="background: #fff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 4px;">
                <h2 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;"><?php esc_html_e( 'Histórico de Mensagens', 'dps-ai' ); ?></h2>

                <?php if ( empty( $messages ) ) : ?>
                    <p style="color: #6b7280; text-align: center; padding: 20px;">
                        <?php esc_html_e( 'Nenhuma mensagem nesta conversa.', 'dps-ai' ); ?>
                    </p>
                <?php else : ?>
                    <div style="max-height: 600px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px; padding: 15px;">
                        <?php foreach ( $messages as $message ) : ?>
                            <div style="margin-bottom: 15px; padding: 12px; border-left: 4px solid <?php echo 'user' === $message->sender_type ? '#0ea5e9' : ( 'assistant' === $message->sender_type ? '#10b981' : '#6b7280' ); ?>; background: <?php echo 'user' === $message->sender_type ? '#f0f9ff' : ( 'assistant' === $message->sender_type ? '#f0fdf4' : '#f9fafb' ); ?>; border-radius: 4px;">
                                <!-- Cabeçalho da mensagem -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div>
                                        <strong style="color: #374151;">
                                            <?php
                                            if ( 'user' === $message->sender_type ) {
                                                echo '<span class="dashicons dashicons-admin-users" style="vertical-align: middle; color: #0ea5e9;"></span> ';
                                                esc_html_e( 'Usuário', 'dps-ai' );
                                            } elseif ( 'assistant' === $message->sender_type ) {
                                                echo '<span class="dashicons dashicons-format-chat" style="vertical-align: middle; color: #10b981;"></span> ';
                                                esc_html_e( 'Assistente', 'dps-ai' );
                                            } else {
                                                echo '<span class="dashicons dashicons-info" style="vertical-align: middle; color: #6b7280;"></span> ';
                                                esc_html_e( 'Sistema', 'dps-ai' );
                                            }
                                            ?>
                                        </strong>
                                        <?php if ( $message->sender_identifier ) : ?>
                                            <small style="color: #6b7280; margin-left: 5px;">
                                                (<?php echo esc_html( $message->sender_identifier ); ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <small style="color: #6b7280;">
                                            <?php echo esc_html( gmdate( 'd/m/Y H:i:s', strtotime( $message->created_at ) ) ); ?>
                                        </small>
                                    </div>
                                </div>

                                <!-- Texto da mensagem -->
                                <div style="color: #374151; line-height: 1.6;">
                                    <?php echo nl2br( esc_html( $message->message_text ) ); ?>
                                </div>

                                <!-- Metadados (se houver) -->
                                <?php if ( ! empty( $message->metadata_decoded ) ) : ?>
                                    <details style="margin-top: 10px;">
                                        <summary style="cursor: pointer; color: #6b7280; font-size: 12px;">
                                            <?php esc_html_e( 'Ver metadados', 'dps-ai' ); ?>
                                        </summary>
                                        <pre style="background: #fff; padding: 10px; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 5px; font-size: 11px; overflow-x: auto;"><?php echo esc_html( wp_json_encode( $message->metadata_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
