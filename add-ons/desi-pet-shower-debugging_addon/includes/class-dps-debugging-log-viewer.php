<?php
/**
 * Classe para visualização e manipulação do arquivo debug.log.
 *
 * @package DPS_Debugging_Addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Debugging_Log_Viewer
 * 
 * Gerencia visualização, estatísticas e limpeza do arquivo debug.log.
 *
 * @since 1.0.0
 * @since 1.3.0 Adicionado suporte a paginação e filtro por data.
 */
class DPS_Debugging_Log_Viewer {

    /**
     * Caminho do arquivo de log.
     *
     * @since 1.0.0
     * @var string
     */
    private $log_path;

    /**
     * Limite máximo de linhas para exibição.
     *
     * @since 1.0.0
     * @var int
     */
    private $max_lines = 5000;

    /**
     * Tipos de erro para classificação visual.
     *
     * Array associativo com chave = tipo interno e valor = marcador no log.
     *
     * @since 1.0.0
     * @var array
     */
    private $error_types = [
        'fatal'        => 'PHP Fatal error:',
        'warning'      => 'PHP Warning:',
        'notice'       => 'PHP Notice:',
        'deprecated'   => 'PHP Deprecated:',
        'parse'        => 'PHP Parse error:',
        'wordpress-db' => 'WordPress database error',
        'stack-trace'  => 'Stack trace:',
        'exception'    => 'Uncaught Exception',
        'catchable'    => 'PHP Catchable fatal error:',
    ];

    /**
     * Cache de entradas parseadas.
     *
     * @since 1.1.0
     * @var array|null
     */
    private $parsed_entries = null;

    /**
     * Cache de tipos de entrada (entry => type).
     *
     * @since 1.1.0
     * @var array
     */
    private $entry_types = [];

    /**
     * Opções de quantidade por página.
     *
     * @since 1.3.0
     * @var array
     */
    public static $per_page_options = [ 10, 50, 100, 500 ];

    /**
     * Opções de período para filtro de data.
     *
     * @since 1.3.0
     * @var array
     */
    public static $period_options = [
        'all'    => 'Todos',
        'today'  => 'Hoje',
        '24h'    => 'Últimas 24h',
        '7d'     => 'Últimos 7 dias',
        '30d'    => 'Últimos 30 dias',
        'custom' => 'Personalizado',
    ];

    /**
     * Construtor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->log_path = $this->get_debug_log_path();
    }

    /**
     * Retorna as opções de período traduzidas.
     *
     * @since 1.3.0
     *
     * @return array Array associativo com período => label traduzido.
     */
    public static function get_period_labels() {
        return [
            'all'    => __( 'Todos', 'dps-debugging-addon' ),
            'today'  => __( 'Hoje', 'dps-debugging-addon' ),
            '24h'    => __( 'Últimas 24h', 'dps-debugging-addon' ),
            '7d'     => __( 'Últimos 7 dias', 'dps-debugging-addon' ),
            '30d'    => __( 'Últimos 30 dias', 'dps-debugging-addon' ),
            'custom' => __( 'Personalizado', 'dps-debugging-addon' ),
        ];
    }

    /**
     * Obtém o caminho do arquivo debug.log.
     *
     * Verifica na ordem:
     * 1. WP_DEBUG_LOG (se definido como caminho customizado)
     * 2. PHP error_log (se definido e existir)
     * 3. Caminho padrão do WordPress (wp-content/debug.log)
     *
     * @since 1.0.0
     *
     * @return string Caminho absoluto do arquivo de log.
     */
    public function get_debug_log_path() {
        // Verifica se WP_DEBUG_LOG está definido como um caminho customizado
        if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG !== '' && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== 'true' ) {
            return WP_DEBUG_LOG;
        }

        // Verifica configuração do PHP
        $php_error_log = ini_get( 'error_log' );
        if ( ! empty( $php_error_log ) && file_exists( $php_error_log ) ) {
            return $php_error_log;
        }

        // Caminho padrão do WordPress
        return WP_CONTENT_DIR . '/debug.log';
    }

    /**
     * Verifica se o arquivo de log existe e não está vazio.
     *
     * @since 1.0.0
     *
     * @return bool True se o arquivo existe e tem conteúdo.
     */
    public function log_exists() {
        return file_exists( $this->log_path ) && filesize( $this->log_path ) > 0;
    }

    /**
     * Obtém o tamanho do arquivo de log formatado.
     *
     * Retorna o tamanho em formato legível (B, KB, MB, GB).
     *
     * @since 1.0.0
     *
     * @return string Tamanho formatado (ex: "125 KB", "1.5 MB").
     */
    public function get_log_size_formatted() {
        if ( ! file_exists( $this->log_path ) ) {
            return '0 B';
        }

        $size = filesize( $this->log_path );

        if ( $size >= 1073741824 ) {
            return number_format_i18n( $size / 1073741824, 2 ) . ' GB';
        }
        if ( $size >= 1048576 ) {
            return number_format_i18n( $size / 1048576, 2 ) . ' MB';
        }
        if ( $size >= 1024 ) {
            return number_format_i18n( $size / 1024, 2 ) . ' KB';
        }

        return number_format_i18n( $size ) . ' B';
    }

    /**
     * Obtém o conteúdo raw do arquivo de log.
     *
     * @since 1.0.0
     *
     * @return string Conteúdo do arquivo ou string vazia se não existir.
     */
    public function get_raw_content() {
        if ( ! $this->log_exists() ) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $this->log_path );
        return false !== $content ? $content : '';
    }

    /**
     * Obtém estatísticas das entradas do log.
     *
     * @since 1.1.0
     *
     * @return array {
     *     Estatísticas com total e contagem por tipo.
     *
     *     @type int   $total   Total de entradas.
     *     @type array $by_type Contagem por tipo (fatal, warning, notice, etc).
     * }
     */
    public function get_entry_stats() {
        if ( ! $this->log_exists() ) {
            return [ 'total' => 0, 'by_type' => [] ];
        }

        $entries = $this->get_parsed_entries();
        $stats   = [
            'total'   => count( $entries ),
            'by_type' => [
                'fatal'        => 0,
                'warning'      => 0,
                'notice'       => 0,
                'deprecated'   => 0,
                'parse'        => 0,
                'wordpress-db' => 0,
                'exception'    => 0,
                'other'        => 0,
            ],
        ];

        foreach ( $entries as $entry ) {
            $type = $this->detect_entry_type( $entry );
            if ( $type && isset( $stats['by_type'][ $type ] ) ) {
                $stats['by_type'][ $type ]++;
            } else {
                $stats['by_type']['other']++;
            }
        }

        return $stats;
    }

    /**
     * Obtém entradas parseadas (com cache).
     *
     * @since 1.1.0
     *
     * @return array Entradas parseadas.
     */
    private function get_parsed_entries() {
        if ( null === $this->parsed_entries ) {
            $lines = $this->read_log_lines();
            $this->parsed_entries = $this->parse_log_entries( $lines );
        }
        return $this->parsed_entries;
    }

    /**
     * Obtém o conteúdo formatado do arquivo de log.
     *
     * Retorna o conteúdo do log com formatação HTML incluindo:
     * - Destaque visual por tipo de erro
     * - Formatação de stack traces
     * - Pretty-print de JSON
     * - Ordenação mais recente primeiro
     *
     * @since 1.0.0
     * @since 1.1.0 Adicionado suporte a filtro por tipo.
     *
     * @param string $filter_type Tipo de erro para filtrar (ex: 'fatal', 'warning'). Opcional.
     * @return string HTML formatado com as entradas do log.
     */
    public function get_formatted_content( $filter_type = '' ) {
        if ( ! $this->log_exists() ) {
            return '<p class="dps-debugging-log-empty">' . esc_html__( 'O arquivo de log está vazio.', 'dps-debugging-addon' ) . '</p>';
        }

        $parsed = $this->get_parsed_entries();

        // Aplica filtro por tipo se especificado
        if ( ! empty( $filter_type ) ) {
            $parsed = array_filter( $parsed, function( $entry ) use ( $filter_type ) {
                return $this->detect_entry_type( $entry ) === $filter_type;
            } );
        }

        // Inverte para mostrar mais recentes primeiro
        $parsed = array_reverse( $parsed );

        $total_entries = count( $parsed );

        $output = '<div class="dps-debugging-log-intro">';
        $output .= '<p class="dps-debugging-log-count">';
        
        if ( ! empty( $filter_type ) ) {
            $output .= sprintf(
                /* translators: %1$d: Number of filtered entries, %2$s: Filter type */
                esc_html__( 'Exibindo %1$d entradas do tipo "%2$s"', 'dps-debugging-addon' ),
                $total_entries,
                esc_html( $filter_type )
            );
        } else {
            $output .= sprintf(
                /* translators: %d: Number of log entries */
                esc_html__( 'Total de entradas: %d', 'dps-debugging-addon' ),
                $total_entries
            );
        }
        
        $output .= '</p>';
        $output .= '</div>';

        $output .= '<div class="dps-debugging-log-entries">';

        if ( empty( $parsed ) ) {
            $output .= '<div class="dps-debugging-log-empty">';
            $output .= '<p>' . esc_html__( 'Nenhuma entrada encontrada para o filtro selecionado.', 'dps-debugging-addon' ) . '</p>';
            $output .= '</div>';
        } else {
            foreach ( $parsed as $entry ) {
                $output .= $this->format_entry( $entry );
            }
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Lê as linhas do arquivo de log.
     *
     * @return array
     */
    private function read_log_lines() {
        if ( ! file_exists( $this->log_path ) ) {
            return [];
        }

        // Para arquivos muito grandes, lê apenas as últimas N linhas de forma eficiente
        $file_size = filesize( $this->log_path );
        
        // Se o arquivo for maior que 5MB, usa abordagem de tail
        if ( $file_size > 5 * 1024 * 1024 ) {
            return $this->tail_log_file( $this->max_lines );
        }

        $lines = file( $this->log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( false === $lines ) {
            return [];
        }

        // Limita número de linhas
        if ( count( $lines ) > $this->max_lines ) {
            $lines = array_slice( $lines, -$this->max_lines );
        }

        return array_map( 'rtrim', $lines );
    }

    /**
     * Lê as últimas N linhas de um arquivo grande de forma eficiente.
     *
     * @param int $num_lines Número de linhas a retornar.
     * @return array
     */
    private function tail_log_file( $num_lines ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $file = fopen( $this->log_path, 'r' );
        if ( ! $file ) {
            return [];
        }

        $lines = [];
        $buffer = '';
        $chunk_size = 4096;
        
        // Vai para o final do arquivo
        fseek( $file, 0, SEEK_END );
        $pos = ftell( $file );

        // Lê de trás para frente até ter linhas suficientes
        while ( $pos > 0 && count( $lines ) < $num_lines ) {
            $to_read = min( $chunk_size, $pos );
            $pos -= $to_read;
            fseek( $file, $pos );
            $buffer = fread( $file, $to_read ) . $buffer;
            
            $lines = explode( "\n", $buffer );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $file );

        // Remove linhas vazias e retorna as últimas N
        $lines = array_filter( $lines, 'strlen' );
        $lines = array_slice( $lines, -$num_lines );

        return array_map( 'rtrim', $lines );
    }

    /**
     * Agrupa linhas em entradas de log.
     *
     * @param array $lines Linhas do arquivo.
     * @return array Entradas agrupadas.
     */
    private function parse_log_entries( $lines ) {
        $entries = [];
        $current = null;

        foreach ( $lines as $line ) {
            // Verifica se a linha começa com data/hora [
            if ( preg_match( '/^\[/', $line ) ) {
                // Nova entrada
                if ( null !== $current ) {
                    $entries[] = $current;
                }
                $current = $line;
            } elseif ( null !== $current ) {
                // Continuação da entrada atual (stack trace, etc.)
                $current .= "\n" . $line;
            }
        }

        // Adiciona última entrada
        if ( null !== $current ) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * Formata uma entrada de log como HTML.
     *
     * @param string $entry Entrada de log.
     * @return string HTML formatado.
     */
    private function format_entry( $entry ) {
        $class = 'dps-debugging-log-entry';
        $type  = $this->detect_entry_type( $entry );

        if ( $type ) {
            $class .= ' dps-debugging-log-entry-' . $type;
        }

        $formatted = $this->format_entry_content( $entry );

        return '<div class="' . esc_attr( $class ) . '">' . $formatted . '</div>';
    }

    /**
     * Detecta o tipo de entrada de log (com cache).
     *
     * @param string $entry Entrada de log.
     * @return string|null Tipo de entrada ou null.
     */
    private function detect_entry_type( $entry ) {
        // Usa hash como chave para cache (entradas podem ser muito grandes)
        $entry_hash = md5( $entry );
        
        if ( isset( $this->entry_types[ $entry_hash ] ) ) {
            return $this->entry_types[ $entry_hash ];
        }

        $type = null;
        foreach ( $this->error_types as $error_type => $marker ) {
            if ( false !== strpos( $entry, $marker ) ) {
                $type = $error_type;
                break;
            }
        }

        $this->entry_types[ $entry_hash ] = $type;
        return $type;
    }

    /**
     * Formata o conteúdo de uma entrada.
     *
     * @param string $entry Entrada de log.
     * @return string HTML formatado.
     */
    private function format_entry_content( $entry ) {
        // Extrai e formata data/hora
        $entry = $this->format_datetime( $entry );

        // Formata tipos de erro
        $entry = $this->format_error_labels( $entry );

        // Formata stack traces
        $entry = $this->format_stack_trace( $entry );

        // Formata JSON
        $entry = $this->format_json( $entry );

        // Converte quebras de linha em parágrafos
        $entry = wpautop( $entry, false );

        return '<div class="dps-debugging-log-entry-content">' . $entry . '</div>';
    }

    /**
     * Formata data/hora da entrada.
     *
     * @param string $entry Entrada de log.
     * @return string Entrada com data formatada.
     */
    private function format_datetime( $entry ) {
        $pattern = '/\[([^\]]+)\]/';

        if ( preg_match( $pattern, $entry, $matches ) ) {
            $datetime = $matches[1];
            $formatted_dt = '<span class="dps-debugging-log-datetime">[' . esc_html( $datetime ) . ']</span>';
            $entry = preg_replace( $pattern, $formatted_dt, $entry, 1 );
        }

        return $entry;
    }

    /**
     * Formata labels de tipo de erro.
     *
     * @param string $entry Entrada de log.
     * @return string Entrada com labels formatados.
     */
    private function format_error_labels( $entry ) {
        foreach ( $this->error_types as $type => $marker ) {
            if ( false !== strpos( $entry, $marker ) ) {
                $label = '<span class="dps-debugging-log-label dps-debugging-log-label-' . esc_attr( $type ) . '">' . esc_html( rtrim( $marker, ':' ) ) . '</span>';
                $entry = str_replace( $marker, $label, $entry );
                break;
            }
        }

        return $entry;
    }

    /**
     * Formata stack traces.
     *
     * @param string $entry Entrada de log.
     * @return string Entrada com stack trace formatado.
     */
    private function format_stack_trace( $entry ) {
        $pattern = '/Stack trace:\n((?:#\d*.+\n?)+)/m';

        if ( preg_match( $pattern, $entry, $matches ) ) {
            $stack = $matches[1];
            $lines = explode( "\n", $stack );

            $formatted = '<p class="dps-debugging-log-stacktrace-title">' . esc_html__( 'Stack trace:', 'dps-debugging-addon' ) . '</p>';
            $formatted .= '<ul class="dps-debugging-log-stacktrace">';

            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( ! empty( $line ) ) {
                    $formatted .= '<li>' . esc_html( $line ) . '</li>';
                }
            }

            $formatted .= '</ul>';

            $entry = str_replace( $matches[0], $formatted, $entry );
        }

        return $entry;
    }

    /**
     * Formata blocos JSON na entrada.
     *
     * @param string $entry Entrada de log.
     * @return string Entrada com JSON formatado.
     */
    private function format_json( $entry ) {
        // Encontra possíveis blocos JSON usando um padrão simples
        // Depois valida cada um com json_decode
        $potential_json = $this->extract_json_candidates( $entry );

        foreach ( $potential_json as $json_string ) {
            // Valida se é realmente JSON válido
            $decoded = json_decode( $json_string, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                if ( $pretty ) {
                    $formatted = '<pre class="dps-debugging-log-json">' . esc_html( $pretty ) . '</pre>';
                    $entry = str_replace( $json_string, $formatted, $entry );
                }
            }
        }

        return $entry;
    }

    /**
     * Extrai candidatos a JSON de uma string.
     *
     * @param string $text Texto a analisar.
     * @return array Lista de strings que podem ser JSON.
     */
    private function extract_json_candidates( $text ) {
        $candidates = [];
        $length = strlen( $text );
        $i = 0;

        while ( $i < $length ) {
            // Procura por início de objeto JSON
            if ( '{' === $text[ $i ] ) {
                $start = $i;
                $depth = 1;
                $i++;

                // Percorre até encontrar o fechamento correspondente
                while ( $i < $length && $depth > 0 ) {
                    if ( '{' === $text[ $i ] ) {
                        $depth++;
                    } elseif ( '}' === $text[ $i ] ) {
                        $depth--;
                    }
                    $i++;
                }

                // Se fechou corretamente, adiciona como candidato
                if ( 0 === $depth ) {
                    $candidate = substr( $text, $start, $i - $start );
                    // Só considera se tiver pelo menos 10 caracteres (JSON mínimo válido)
                    if ( strlen( $candidate ) >= 10 ) {
                        $candidates[] = $candidate;
                    }
                }
            } else {
                $i++;
            }
        }

        return $candidates;
    }

    /**
     * Limpa o arquivo de log.
     *
     * Esvazia o conteúdo do arquivo debug.log, mantendo o arquivo existente.
     *
     * @since 1.0.0
     *
     * @return bool True em sucesso, false em falha.
     */
    public function purge_log() {
        if ( ! file_exists( $this->log_path ) ) {
            // Cria arquivo vazio
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            return false !== file_put_contents( $this->log_path, '' );
        }

        if ( ! is_writable( $this->log_path ) ) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents( $this->log_path, '' );
    }

    /**
     * Obtém a contagem de entradas no log.
     *
     * @since 1.0.0
     *
     * @return int Número de entradas no log.
     */
    public function get_entry_count() {
        if ( ! $this->log_exists() ) {
            return 0;
        }

        $lines  = $this->read_log_lines();
        $parsed = $this->parse_log_entries( $lines );

        return count( $parsed );
    }

    /**
     * Obtém entradas filtradas e paginadas.
     *
     * @since 1.3.0
     *
     * @param array $args {
     *     Argumentos de filtragem e paginação.
     *
     *     @type string $filter_type Tipo de erro para filtrar.
     *     @type string $period      Período de data (all, today, 24h, 7d, 30d, custom).
     *     @type string $date_from   Data inicial (Y-m-d) para filtro custom.
     *     @type string $date_to     Data final (Y-m-d) para filtro custom.
     *     @type int    $page        Página atual (começando em 1).
     *     @type int    $per_page    Entradas por página.
     *     @type bool   $compact     Modo compacto (apenas primeira linha).
     * }
     * @return array {
     *     Resultado com entradas e metadados.
     *
     *     @type array $entries      Entradas da página atual.
     *     @type int   $total        Total de entradas após filtros.
     *     @type int   $page         Página atual.
     *     @type int   $per_page     Entradas por página.
     *     @type int   $total_pages  Total de páginas.
     *     @type int   $from         Índice inicial (1-based).
     *     @type int   $to           Índice final (1-based).
     * }
     */
    public function get_paginated_entries( $args = [] ) {
        $defaults = [
            'filter_type' => '',
            'period'      => 'all',
            'date_from'   => '',
            'date_to'     => '',
            'page'        => 1,
            'per_page'    => 100,
            'compact'     => false,
        ];
        $args = wp_parse_args( $args, $defaults );

        if ( ! $this->log_exists() ) {
            return [
                'entries'     => [],
                'total'       => 0,
                'page'        => 1,
                'per_page'    => $args['per_page'],
                'total_pages' => 0,
                'from'        => 0,
                'to'          => 0,
            ];
        }

        // Obtém todas as entradas
        $entries = $this->get_parsed_entries();

        // Aplica filtro por tipo
        if ( ! empty( $args['filter_type'] ) ) {
            $filter_type = $args['filter_type'];
            $entries = array_filter( $entries, function( $entry ) use ( $filter_type ) {
                return $this->detect_entry_type( $entry ) === $filter_type;
            } );
        }

        // Aplica filtro por período/data
        if ( 'all' !== $args['period'] ) {
            $entries = $this->filter_by_period( $entries, $args['period'], $args['date_from'], $args['date_to'] );
        }

        // Reindexar após filtros
        $entries = array_values( $entries );

        // Inverte para mostrar mais recentes primeiro
        $entries = array_reverse( $entries );

        // Calcula paginação
        $total       = count( $entries );
        $page        = max( 1, (int) $args['page'] );
        $per_page    = (int) $args['per_page'];
        $total_pages = ceil( $total / $per_page );
        $page        = min( $page, max( 1, $total_pages ) );

        $offset = ( $page - 1 ) * $per_page;
        $from   = $total > 0 ? $offset + 1 : 0;
        $to     = min( $offset + $per_page, $total );

        // Aplica paginação
        $paginated = array_slice( $entries, $offset, $per_page );

        return [
            'entries'     => $paginated,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) $total_pages,
            'from'        => $from,
            'to'          => $to,
        ];
    }

    /**
     * Filtra entradas por período de data.
     *
     * @since 1.3.0
     *
     * @param array  $entries   Entradas a filtrar.
     * @param string $period    Período (today, 24h, 7d, 30d, custom).
     * @param string $date_from Data inicial para filtro custom (Y-m-d).
     * @param string $date_to   Data final para filtro custom (Y-m-d).
     * @return array Entradas filtradas.
     */
    private function filter_by_period( $entries, $period, $date_from = '', $date_to = '' ) {
        // Calcula timestamps de início e fim
        $now = current_time( 'timestamp' );

        switch ( $period ) {
            case 'today':
                $start = strtotime( 'today midnight', $now );
                $end   = $now;
                break;

            case '24h':
                $start = $now - DAY_IN_SECONDS;
                $end   = $now;
                break;

            case '7d':
                $start = $now - ( 7 * DAY_IN_SECONDS );
                $end   = $now;
                break;

            case '30d':
                $start = $now - ( 30 * DAY_IN_SECONDS );
                $end   = $now;
                break;

            case 'custom':
                if ( empty( $date_from ) || empty( $date_to ) ) {
                    return $entries;
                }
                $start = strtotime( $date_from . ' 00:00:00' );
                $end   = strtotime( $date_to . ' 23:59:59' );
                if ( false === $start || false === $end ) {
                    return $entries;
                }
                break;

            default:
                return $entries;
        }

        return array_filter( $entries, function( $entry ) use ( $start, $end ) {
            $entry_time = $this->extract_entry_timestamp( $entry );
            if ( null === $entry_time ) {
                return false;
            }
            return $entry_time >= $start && $entry_time <= $end;
        } );
    }

    /**
     * Extrai timestamp de uma entrada de log.
     *
     * @since 1.3.0
     *
     * @param string $entry Entrada de log.
     * @return int|null Timestamp Unix ou null se não encontrado.
     */
    public function extract_entry_timestamp( $entry ) {
        // Padrão: [DD-Mon-YYYY HH:MM:SS UTC]
        if ( preg_match( '/^\[([^\]]+)\]/', $entry, $matches ) ) {
            $datetime = $matches[1];
            $timestamp = strtotime( $datetime );
            if ( false !== $timestamp ) {
                return $timestamp;
            }
        }
        return null;
    }

    /**
     * Obtém a primeira linha de uma entrada (para modo compacto).
     *
     * @since 1.3.0
     *
     * @param string $entry Entrada completa do log.
     * @return string Primeira linha da entrada.
     */
    public function get_entry_summary( $entry ) {
        $lines = explode( "\n", $entry );
        $first_line = isset( $lines[0] ) ? $lines[0] : $entry;
        
        // Limita a 200 caracteres
        if ( strlen( $first_line ) > 200 ) {
            $first_line = substr( $first_line, 0, 200 ) . '...';
        }
        
        return $first_line;
    }

    /**
     * Verifica se uma entrada tem múltiplas linhas (detalhes/stack trace).
     *
     * @since 1.3.0
     *
     * @param string $entry Entrada completa do log.
     * @return bool True se tem mais de uma linha.
     */
    public function entry_has_details( $entry ) {
        return strpos( $entry, "\n" ) !== false;
    }

    /**
     * Formata uma entrada para modo compacto (apenas primeira linha, expansível).
     *
     * @since 1.3.0
     *
     * @param string $entry    Entrada de log.
     * @param bool   $expanded Se já deve vir expandida.
     * @return string HTML formatado.
     */
    public function format_entry_compact( $entry, $expanded = false ) {
        $class = 'dps-debugging-log-entry dps-debugging-log-entry-compact';
        $type  = $this->detect_entry_type( $entry );

        if ( $type ) {
            $class .= ' dps-debugging-log-entry-' . $type;
        }

        $has_details = $this->entry_has_details( $entry );
        if ( $has_details ) {
            $class .= ' has-details';
        }
        if ( $expanded ) {
            $class .= ' is-expanded';
        }

        $summary = $this->get_entry_summary( $entry );
        $formatted_summary = $this->format_entry_content_inline( $summary );

        $output = '<div class="' . esc_attr( $class ) . '">';
        
        // Resumo (sempre visível)
        $output .= '<div class="dps-debugging-log-entry-summary">';
        if ( $has_details ) {
            $output .= '<span class="dps-debugging-toggle-icon">▶</span>';
        }
        $output .= '<span class="dps-debugging-log-entry-text">' . $formatted_summary . '</span>';
        $output .= '</div>';

        // Detalhes (ocultos por padrão)
        if ( $has_details ) {
            $full_content = $this->format_entry_content( $entry );
            $display = $expanded ? 'block' : 'none';
            $output .= '<div class="dps-debugging-log-entry-details" style="display:' . $display . ';">' . $full_content . '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Formata conteúdo inline (para resumo compacto).
     *
     * @since 1.3.0
     *
     * @param string $content Conteúdo a formatar.
     * @return string HTML formatado (inline).
     */
    private function format_entry_content_inline( $content ) {
        // Extrai e formata data/hora
        $content = $this->format_datetime( $content );

        // Formata tipos de erro
        $content = $this->format_error_labels( $content );

        return $content;
    }
}
