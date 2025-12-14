<?php
/**
 * Página Hub centralizada do Portal do Cliente.
 *
 * Consolida todos os menus do Portal em uma única página com abas:
 * - Configurações
 * - Logins
 * - Mensagens (integra CPT dps_portal_message)
 *
 * @package DPS_Client_Portal_Addon
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe de Hub do Portal do Cliente.
 */
class DPS_Portal_Hub {

    /**
     * Instância única (singleton).
     *
     * @var DPS_Portal_Hub|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Portal_Hub
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
        add_action( 'admin_menu', [ $this, 'register_hub_menu' ], 19 ); // Antes dos submenus antigos
        add_action( 'admin_menu', [ $this, 'add_unread_badge_to_menu' ], 999 ); // Badge de notificação
    }

    /**
     * Registra o menu hub centralizado.
     */
    public function register_hub_menu() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Portal do Cliente', 'dps-client-portal' ),
            __( 'Portal do Cliente', 'dps-client-portal' ),
            'manage_options',
            'dps-portal-hub',
            [ $this, 'render_hub_page' ]
        );
    }
    
    /**
     * Adiciona badge de novas mensagens ao menu do portal.
     */
    public function add_unread_badge_to_menu() {
        global $submenu;
        
        if ( ! isset( $submenu['desi-pet-shower'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Conta mensagens não lidas
        $unread_count = $this->get_unread_admin_messages_count();
        
        if ( $unread_count <= 0 ) {
            return;
        }
        
        // Encontra o item de menu do Portal do Cliente
        foreach ( $submenu['desi-pet-shower'] as $key => $item ) {
            if ( isset( $item[2] ) && 'dps-portal-hub' === $item[2] ) {
                $badge = sprintf(
                    ' <span class="awaiting-mod update-plugins count-%d"><span class="update-count">%d</span></span>',
                    $unread_count,
                    $unread_count
                );
                $submenu['desi-pet-shower'][ $key ][0] .= $badge;
                break;
            }
        }
    }

    /**
     * Renderiza a página hub com abas.
     */
    public function render_hub_page() {
        $tabs = [
            'settings' => __( 'Configurações', 'dps-client-portal' ),
            'logins'   => __( 'Logins', 'dps-client-portal' ),
            'messages' => __( 'Mensagens', 'dps-client-portal' ),
        ];

        $callbacks = [
            'settings' => [ $this, 'render_settings_tab' ],
            'logins'   => [ $this, 'render_logins_tab' ],
            'messages' => [ $this, 'render_messages_tab' ],
        ];

        DPS_Admin_Tabs_Helper::render_tabbed_page(
            __( 'Portal do Cliente', 'dps-client-portal' ),
            $tabs,
            $callbacks,
            'dps-portal-hub',
            'settings'
        );
    }

    /**
     * Renderiza a aba de Configurações.
     */
    public function render_settings_tab() {
        if ( class_exists( 'DPS_Portal_Admin' ) ) {
            $admin = DPS_Portal_Admin::get_instance();
            ob_start();
            $admin->render_portal_settings_admin_page();
            $content = ob_get_clean();
            
            // Remove o wrapper e H1 duplicado
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Logins.
     */
    public function render_logins_tab() {
        if ( class_exists( 'DPS_Portal_Admin' ) ) {
            $admin = DPS_Portal_Admin::get_instance();
            ob_start();
            $admin->render_client_logins_admin_page();
            $content = ob_get_clean();
            
            // Remove o wrapper e H1 duplicado
            $content = preg_replace( '/^<div class="wrap[^>]*">/i', '', $content );
            $content = preg_replace( '/<\/div>\s*$/i', '', $content );
            $content = preg_replace( '/<h1>.*?<\/h1>/i', '', $content, 1 );
            
            echo $content;
        }
    }

    /**
     * Renderiza a aba de Mensagens (integração com CPT dps_portal_message).
     */
    public function render_messages_tab() {
        // Conta mensagens não lidas (enviadas por clientes que ainda não foram respondidas)
        $unread_count = $this->get_unread_admin_messages_count();
        
        ?>
        <div class="dps-portal-messages-tab">
            <?php if ( $unread_count > 0 ) : ?>
            <div class="notice notice-warning" style="margin: 0 0 20px 0;">
                <p>
                    <strong>📬 <?php 
                    printf(
                        _n(
                            'Você tem %d mensagem de cliente aguardando resposta!',
                            'Você tem %d mensagens de clientes aguardando resposta!',
                            $unread_count,
                            'dps-client-portal'
                        ),
                        $unread_count
                    ); 
                    ?></strong>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="dps-messages-actions" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=dps_portal_message' ) ); ?>" class="button button-primary">
                    ➕ <?php esc_html_e( 'Nova Mensagem', 'dps-client-portal' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dps_portal_message' ) ); ?>" class="button">
                    📋 <?php esc_html_e( 'Ver Todas', 'dps-client-portal' ); ?>
                </a>
                
                <?php if ( $unread_count > 0 ) : ?>
                <span class="dps-unread-badge" style="background: #f59e0b; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                    <?php echo esc_html( $unread_count ); ?> <?php esc_html_e( 'pendente(s)', 'dps-client-portal' ); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <p style="color: #6b7280; margin-bottom: 20px;">
                <?php esc_html_e( 'Aqui você pode visualizar e responder as mensagens enviadas pelos clientes através do portal.', 'dps-client-portal' ); ?>
            </p>
            
            <?php
            // Mostra últimas mensagens diretamente
            $this->render_recent_messages();
            ?>
        </div>
        <?php
    }

    /**
     * Conta mensagens não lidas do admin (enviadas por clientes em status "open").
     *
     * @return int
     */
    private function get_unread_admin_messages_count() {
        $args = [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'message_sender',
                    'value' => 'client',
                ],
                [
                    'key'   => 'message_status',
                    'value' => 'open',
                ],
            ],
        ];
        
        $query = new WP_Query( $args );
        return $query->found_posts;
    }

    /**
     * Renderiza lista de mensagens recentes.
     */
    private function render_recent_messages() {
        $args = [
            'post_type'      => 'dps_portal_message',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        
        $messages = get_posts( $args );
        
        if ( empty( $messages ) ) {
            echo '<div class="dps-empty-state" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px; text-align: center;">';
            echo '<div style="font-size: 48px; margin-bottom: 16px;">📭</div>';
            echo '<p style="color: #6b7280; margin: 0;">' . esc_html__( 'Nenhuma mensagem ainda.', 'dps-client-portal' ) . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<table class="widefat striped" style="margin-top: 0;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 40%;">' . esc_html__( 'Assunto / Prévia', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Cliente', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Origem', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Data', 'dps-client-portal' ) . '</th>';
        echo '<th>' . esc_html__( 'Ações', 'dps-client-portal' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ( $messages as $message ) {
            $client_id = get_post_meta( $message->ID, 'message_client_id', true );
            $sender    = get_post_meta( $message->ID, 'message_sender', true );
            $status    = get_post_meta( $message->ID, 'message_status', true );
            
            $client_name = $client_id ? get_the_title( $client_id ) : '—';
            
            // Status badges
            $status_classes = [
                'open'     => 'background: #fef3c7; color: #92400e;',
                'answered' => 'background: #dbeafe; color: #1e40af;',
                'closed'   => 'background: #d1fae5; color: #065f46;',
            ];
            $status_labels = [
                'open'     => __( 'Em aberto', 'dps-client-portal' ),
                'answered' => __( 'Respondida', 'dps-client-portal' ),
                'closed'   => __( 'Concluída', 'dps-client-portal' ),
            ];
            
            $status_style = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'background: #f3f4f6;';
            $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : '—';
            
            // Sender badges
            $sender_icon  = 'client' === $sender ? '📤' : '📥';
            $sender_label = 'client' === $sender ? __( 'Cliente', 'dps-client-portal' ) : __( 'Equipe', 'dps-client-portal' );
            
            echo '<tr' . ( 'open' === $status && 'client' === $sender ? ' style="background: #fffbeb;"' : '' ) . '>';
            
            // Assunto
            echo '<td>';
            echo '<a href="' . esc_url( get_edit_post_link( $message->ID ) ) . '" style="font-weight: 600; text-decoration: none;">';
            echo esc_html( $message->post_title );
            echo '</a>';
            echo '<br><small style="color: #6b7280;">' . esc_html( wp_trim_words( wp_strip_all_tags( $message->post_content ), 10, '...' ) ) . '</small>';
            echo '</td>';
            
            // Cliente
            echo '<td>';
            if ( $client_id ) {
                echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">' . esc_html( $client_name ) . '</a>';
            } else {
                echo '<em>' . esc_html__( 'Não vinculado', 'dps-client-portal' ) . '</em>';
            }
            echo '</td>';
            
            // Origem
            echo '<td>' . esc_html( $sender_icon . ' ' . $sender_label ) . '</td>';
            
            // Status
            echo '<td><span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; ' . esc_attr( $status_style ) . '">' . esc_html( $status_label ) . '</span></td>';
            
            // Data
            echo '<td>' . esc_html( get_the_date( get_option( 'date_format' ) . ' H:i', $message ) ) . '</td>';
            
            // Ações
            echo '<td>';
            echo '<a href="' . esc_url( get_edit_post_link( $message->ID ) ) . '" class="button button-small">' . esc_html__( 'Editar', 'dps-client-portal' ) . '</a>';
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        if ( count( $messages ) >= 10 ) {
            echo '<p style="text-align: center; margin-top: 16px;">';
            echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=dps_portal_message' ) ) . '" class="button">';
            echo esc_html__( 'Ver todas as mensagens', 'dps-client-portal' );
            echo '</a>';
            echo '</p>';
        }
    }
}
