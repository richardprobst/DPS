<?php
/**
 * Classe de comparação de backups.
 *
 * Permite comparar um backup com os dados atuais do sistema.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Backup_Comparator
 *
 * @since 1.1.0
 */
class DPS_Backup_Comparator {

    /**
     * Compara um payload de backup com os dados atuais.
     *
     * @since 1.1.0
     * @param array $payload Dados do backup.
     * @return array Resultado da comparação.
     */
    public static function compare( $payload ) {
        $result = [
            'summary'    => [],
            'details'    => [],
            'warnings'   => [],
            'backup_info' => [
                'generated_at'   => $payload['generated_at'] ?? '',
                'site_url'       => $payload['site_url'] ?? '',
                'schema_version' => $payload['schema_version'] ?? 1,
                'backup_type'    => $payload['backup_type'] ?? 'complete',
            ],
        ];

        // Comparar cada componente
        $components = [
            'clients'       => [ 'label' => __( 'Clientes', 'dps-backup-addon' ), 'post_type' => 'dps_cliente' ],
            'pets'          => [ 'label' => __( 'Pets', 'dps-backup-addon' ), 'post_type' => 'dps_pet' ],
            'appointments'  => [ 'label' => __( 'Agendamentos', 'dps-backup-addon' ), 'post_type' => 'dps_agendamento' ],
            'services'      => [ 'label' => __( 'Serviços', 'dps-backup-addon' ), 'post_type' => 'dps_service' ],
            'subscriptions' => [ 'label' => __( 'Assinaturas', 'dps-backup-addon' ), 'post_type' => 'dps_subscription' ],
            'campaigns'     => [ 'label' => __( 'Campanhas', 'dps-backup-addon' ), 'post_type' => 'dps_campaign' ],
        ];

        foreach ( $components as $key => $config ) {
            if ( isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
                $comparison = self::compare_entities( $payload[ $key ], $config['post_type'] );
                $result['summary'][ $key ] = [
                    'label'         => $config['label'],
                    'in_backup'     => $comparison['in_backup'],
                    'in_current'    => $comparison['in_current'],
                    'to_add'        => $comparison['to_add'],
                    'to_update'     => $comparison['to_update'],
                    'to_remove'     => $comparison['to_remove'],
                ];
                $result['details'][ $key ] = $comparison['details'];
            }
        }

        // Comparar transações
        if ( isset( $payload['transactions'] ) && is_array( $payload['transactions'] ) ) {
            $trans_comparison = self::compare_transactions( $payload['transactions'] );
            $result['summary']['transactions'] = [
                'label'      => __( 'Transações', 'dps-backup-addon' ),
                'in_backup'  => $trans_comparison['in_backup'],
                'in_current' => $trans_comparison['in_current'],
                'to_add'     => $trans_comparison['to_add'],
                'to_update'  => $trans_comparison['to_update'],
                'to_remove'  => $trans_comparison['to_remove'],
            ];
        }

        // Comparar options
        if ( isset( $payload['options'] ) && is_array( $payload['options'] ) ) {
            $options_comparison = self::compare_options( $payload['options'] );
            $result['summary']['options'] = [
                'label'      => __( 'Configurações', 'dps-backup-addon' ),
                'in_backup'  => $options_comparison['in_backup'],
                'in_current' => $options_comparison['in_current'],
                'to_add'     => $options_comparison['to_add'],
                'to_update'  => $options_comparison['to_update'],
                'to_remove'  => $options_comparison['to_remove'],
            ];
        }

        // Gerar avisos
        if ( ! empty( $payload['site_url'] ) && $payload['site_url'] !== home_url() ) {
            $result['warnings'][] = sprintf(
                /* translators: 1: backup site URL, 2: current site URL */
                __( 'O backup foi gerado em %1$s, mas você está restaurando em %2$s.', 'dps-backup-addon' ),
                $payload['site_url'],
                home_url()
            );
        }

        $total_to_remove = 0;
        foreach ( $result['summary'] as $component ) {
            $total_to_remove += $component['to_remove'] ?? 0;
        }
        if ( $total_to_remove > 0 ) {
            $result['warnings'][] = sprintf(
                /* translators: %d: number of records */
                _n(
                    '%d registro atual será removido durante a restauração.',
                    '%d registros atuais serão removidos durante a restauração.',
                    $total_to_remove,
                    'dps-backup-addon'
                ),
                $total_to_remove
            );
        }

        return $result;
    }

    /**
     * Compara entidades (posts) do backup com dados atuais.
     *
     * @since 1.1.0
     * @param array  $backup_entities Entidades do backup.
     * @param string $post_type       Tipo de post.
     * @return array
     */
    private static function compare_entities( $backup_entities, $post_type ) {
        $current_posts = get_posts( [
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $backup_titles = [];
        foreach ( $backup_entities as $entity ) {
            $title = $entity['post']['post_title'] ?? '';
            if ( $title ) {
                $backup_titles[ $title ] = $entity;
            }
        }

        $current_titles = [];
        foreach ( $current_posts as $post_id ) {
            $title = get_the_title( $post_id );
            if ( $title ) {
                $current_titles[ $title ] = $post_id;
            }
        }

        $to_add = 0;
        $to_update = 0;
        $to_remove = 0;
        $details = [
            'add'    => [],
            'update' => [],
            'remove' => [],
        ];

        // Itens a adicionar ou atualizar
        foreach ( $backup_titles as $title => $entity ) {
            if ( isset( $current_titles[ $title ] ) ) {
                $to_update++;
                $details['update'][] = $title;
            } else {
                $to_add++;
                $details['add'][] = $title;
            }
        }

        // Itens a remover
        foreach ( $current_titles as $title => $post_id ) {
            if ( ! isset( $backup_titles[ $title ] ) ) {
                $to_remove++;
                $details['remove'][] = $title;
            }
        }

        return [
            'in_backup'  => count( $backup_entities ),
            'in_current' => count( $current_posts ),
            'to_add'     => $to_add,
            'to_update'  => $to_update,
            'to_remove'  => $to_remove,
            'details'    => $details,
        ];
    }

    /**
     * Compara transações do backup com dados atuais.
     *
     * @since 1.1.0
     * @param array $backup_transactions Transações do backup.
     * @return array
     */
    private static function compare_transactions( $backup_transactions ) {
        global $wpdb;

        $table = $wpdb->prefix . 'dps_transacoes';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        
        if ( $exists !== $table ) {
            return [
                'in_backup'  => count( $backup_transactions ),
                'in_current' => 0,
                'to_add'     => count( $backup_transactions ),
                'to_update'  => 0,
                'to_remove'  => 0,
            ];
        }

        $current_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        return [
            'in_backup'  => count( $backup_transactions ),
            'in_current' => $current_count,
            'to_add'     => count( $backup_transactions ),
            'to_update'  => 0,
            'to_remove'  => $current_count,
        ];
    }

    /**
     * Compara options do backup com dados atuais.
     *
     * @since 1.1.0
     * @param array $backup_options Options do backup.
     * @return array
     */
    private static function compare_options( $backup_options ) {
        global $wpdb;

        $current_options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'dps\\_%' ESCAPE '\\'"
        );

        $backup_names = array_column( $backup_options, 'option_name' );

        $to_add = 0;
        $to_update = 0;
        $to_remove = 0;

        foreach ( $backup_names as $name ) {
            if ( in_array( $name, $current_options, true ) ) {
                $to_update++;
            } else {
                $to_add++;
            }
        }

        foreach ( $current_options as $name ) {
            if ( ! in_array( $name, $backup_names, true ) ) {
                $to_remove++;
            }
        }

        return [
            'in_backup'  => count( $backup_options ),
            'in_current' => count( $current_options ),
            'to_add'     => $to_add,
            'to_update'  => $to_update,
            'to_remove'  => $to_remove,
        ];
    }

    /**
     * Gera um resumo formatado da comparação.
     *
     * @since 1.1.0
     * @param array $comparison Resultado da comparação.
     * @return string HTML formatado.
     */
    public static function format_summary( $comparison ) {
        $html = '<div class="dps-backup-comparison">';

        // Informações do backup
        if ( ! empty( $comparison['backup_info'] ) ) {
            $html .= '<div class="backup-info">';
            $html .= '<h4>' . esc_html__( 'Informações do Backup', 'dps-backup-addon' ) . '</h4>';
            $html .= '<ul>';
            if ( ! empty( $comparison['backup_info']['generated_at'] ) ) {
                $date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $comparison['backup_info']['generated_at'] ) );
                $html .= '<li><strong>' . esc_html__( 'Gerado em:', 'dps-backup-addon' ) . '</strong> ' . esc_html( $date ) . '</li>';
            }
            if ( ! empty( $comparison['backup_info']['site_url'] ) ) {
                $html .= '<li><strong>' . esc_html__( 'Site de origem:', 'dps-backup-addon' ) . '</strong> ' . esc_html( $comparison['backup_info']['site_url'] ) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Avisos
        if ( ! empty( $comparison['warnings'] ) ) {
            $html .= '<div class="backup-warnings notice notice-warning inline">';
            foreach ( $comparison['warnings'] as $warning ) {
                $html .= '<p>' . esc_html( $warning ) . '</p>';
            }
            $html .= '</div>';
        }

        // Tabela de resumo
        $html .= '<table class="widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__( 'Componente', 'dps-backup-addon' ) . '</th>';
        $html .= '<th>' . esc_html__( 'No Backup', 'dps-backup-addon' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Atual', 'dps-backup-addon' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Adicionar', 'dps-backup-addon' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Atualizar', 'dps-backup-addon' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Remover', 'dps-backup-addon' ) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ( $comparison['summary'] as $key => $data ) {
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html( $data['label'] ) . '</strong></td>';
            $html .= '<td>' . absint( $data['in_backup'] ) . '</td>';
            $html .= '<td>' . absint( $data['in_current'] ) . '</td>';
            $html .= '<td class="positive">' . ( $data['to_add'] > 0 ? '+' . absint( $data['to_add'] ) : '0' ) . '</td>';
            $html .= '<td>' . absint( $data['to_update'] ) . '</td>';
            $html .= '<td class="negative">' . ( $data['to_remove'] > 0 ? '-' . absint( $data['to_remove'] ) : '0' ) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }
}
