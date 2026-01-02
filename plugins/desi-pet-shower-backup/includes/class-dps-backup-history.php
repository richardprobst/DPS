<?php
/**
 * Classe de histórico de backups.
 *
 * Gerencia o registro e listagem de backups realizados.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Backup_History
 *
 * @since 1.1.0
 */
class DPS_Backup_History {

    /**
     * Nome da option de histórico.
     *
     * @var string
     */
    const OPTION_NAME = 'dps_backup_history';

    /**
     * Diretório de backups.
     *
     * @var string
     */
    const BACKUP_DIR = 'dps-backups';

    /**
     * Obtém o histórico de backups.
     *
     * @since 1.1.0
     * @param int $limit Limite de registros (0 = todos).
     * @return array
     */
    public static function get_history( $limit = 0 ) {
        $history = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        // Ordenar por data (mais recente primeiro)
        usort( $history, function( $a, $b ) {
            return strtotime( $b['date'] ?? '0' ) - strtotime( $a['date'] ?? '0' );
        } );

        if ( $limit > 0 ) {
            $history = array_slice( $history, 0, $limit );
        }

        return $history;
    }

    /**
     * Adiciona um registro ao histórico.
     *
     * @since 1.1.0
     * @param array $entry Dados do backup.
     * @return bool
     */
    public static function add_entry( $entry ) {
        $history = self::get_history();

        $new_entry = [
            'id'          => wp_generate_uuid4(),
            'filename'    => sanitize_file_name( $entry['filename'] ?? '' ),
            'date'        => current_time( 'mysql' ),
            'size'        => absint( $entry['size'] ?? 0 ),
            'type'        => sanitize_key( $entry['type'] ?? 'manual' ),
            'components'  => isset( $entry['components'] ) && is_array( $entry['components'] ) ? array_map( 'sanitize_key', $entry['components'] ) : [],
            'user_id'     => get_current_user_id(),
            'stats'       => isset( $entry['stats'] ) && is_array( $entry['stats'] ) ? $entry['stats'] : [],
            'stored'      => ! empty( $entry['stored'] ),
            'file_path'   => sanitize_text_field( $entry['file_path'] ?? '' ),
        ];

        array_unshift( $history, $new_entry );

        // Aplicar limite de retenção
        $retention = DPS_Backup_Settings::get( 'retention_count', 5 );
        if ( count( $history ) > $retention ) {
            $removed = array_splice( $history, $retention );
            // Remover arquivos antigos
            foreach ( $removed as $old_entry ) {
                if ( ! empty( $old_entry['stored'] ) && ! empty( $old_entry['file_path'] ) ) {
                    self::delete_backup_file( $old_entry['file_path'] );
                }
            }
        }

        return update_option( self::OPTION_NAME, $history );
    }

    /**
     * Remove um registro do histórico.
     *
     * @since 1.1.0
     * @param string $id ID do registro.
     * @return bool
     */
    public static function remove_entry( $id ) {
        $history = self::get_history();
        $new_history = [];

        foreach ( $history as $entry ) {
            if ( ( $entry['id'] ?? '' ) === $id ) {
                // Remover arquivo se existir
                if ( ! empty( $entry['stored'] ) && ! empty( $entry['file_path'] ) ) {
                    self::delete_backup_file( $entry['file_path'] );
                }
            } else {
                $new_history[] = $entry;
            }
        }

        return update_option( self::OPTION_NAME, $new_history );
    }

    /**
     * Obtém um registro do histórico por ID.
     *
     * @since 1.1.0
     * @param string $id ID do registro.
     * @return array|null
     */
    public static function get_entry( $id ) {
        $history = self::get_history();
        foreach ( $history as $entry ) {
            if ( ( $entry['id'] ?? '' ) === $id ) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Obtém o diretório de backups.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_backup_dir() {
        $uploads = wp_upload_dir();
        return trailingslashit( $uploads['basedir'] ) . self::BACKUP_DIR;
    }

    /**
     * Salva um backup no servidor.
     *
     * @since 1.1.0
     * @param string $filename Nome do arquivo.
     * @param string $content  Conteúdo do backup (JSON).
     * @return string|WP_Error Caminho do arquivo ou erro.
     */
    public static function save_backup_file( $filename, $content ) {
        $dir = self::get_backup_dir();

        if ( ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'dps_backup_dir', __( 'Não foi possível criar o diretório de backups.', 'dps-backup-addon' ) );
        }

        // Criar .htaccess para proteger o diretório (compatível com Apache 2.2 e 2.4+)
        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $htaccess_content = "# Apache 2.4+\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n\n# Apache 2.2\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>";
            file_put_contents( $htaccess, $htaccess_content );
        }

        // Criar index.php vazio para evitar listagem
        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden." );
        }

        $filepath = trailingslashit( $dir ) . sanitize_file_name( $filename );

        if ( false === file_put_contents( $filepath, $content ) ) {
            return new WP_Error( 'dps_backup_write', __( 'Não foi possível salvar o arquivo de backup.', 'dps-backup-addon' ) );
        }

        return $filepath;
    }

    /**
     * Remove um arquivo de backup.
     *
     * @since 1.1.0
     * @param string $filepath Caminho do arquivo.
     * @return bool
     */
    public static function delete_backup_file( $filepath ) {
        if ( file_exists( $filepath ) && is_file( $filepath ) ) {
            $result = unlink( $filepath );
            if ( ! $result && class_exists( 'DPS_Logger' ) ) {
                DPS_Logger::log( 'backup_error', sprintf( 'Não foi possível excluir arquivo de backup: %s', $filepath ), 'warning' );
            }
            return $result;
        }
        return false;
    }

    /**
     * Obtém o conteúdo de um backup salvo.
     *
     * @since 1.1.0
     * @param string $filepath Caminho do arquivo.
     * @return string|WP_Error
     */
    public static function get_backup_content( $filepath ) {
        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 'dps_backup_not_found', __( 'Arquivo de backup não encontrado.', 'dps-backup-addon' ) );
        }

        $content = file_get_contents( $filepath );
        if ( false === $content ) {
            return new WP_Error( 'dps_backup_read', __( 'Não foi possível ler o arquivo de backup.', 'dps-backup-addon' ) );
        }

        return $content;
    }

    /**
     * Formata o tamanho do arquivo para exibição.
     *
     * @since 1.1.0
     * @param int $bytes Tamanho em bytes.
     * @return string
     */
    public static function format_size( $bytes ) {
        if ( $bytes >= 1073741824 ) {
            return number_format( $bytes / 1073741824, 2 ) . ' GB';
        } elseif ( $bytes >= 1048576 ) {
            return number_format( $bytes / 1048576, 2 ) . ' MB';
        } elseif ( $bytes >= 1024 ) {
            return number_format( $bytes / 1024, 2 ) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Limpa backups antigos além do limite de retenção.
     *
     * @since 1.1.0
     * @return int Número de backups removidos.
     */
    public static function cleanup_old_backups() {
        $history = self::get_history();
        $retention = DPS_Backup_Settings::get( 'retention_count', 5 );
        $removed = 0;

        if ( count( $history ) > $retention ) {
            $to_remove = array_slice( $history, $retention );
            foreach ( $to_remove as $entry ) {
                if ( self::remove_entry( $entry['id'] ?? '' ) ) {
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
