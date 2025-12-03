<?php
/**
 * Classe para transformar configurações no wp-config.php.
 *
 * @package DPS_Debugging_Addon
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Debugging_Config_Transformer
 * 
 * Gerencia leitura e escrita de constantes no arquivo wp-config.php.
 */
class DPS_Debugging_Config_Transformer {

    /**
     * Caminho do arquivo wp-config.php.
     *
     * @var string
     */
    private $config_path;

    /**
     * Construtor.
     *
     * @param string $config_path Caminho do arquivo wp-config.php.
     */
    public function __construct( $config_path ) {
        $this->config_path = $config_path;
    }

    /**
     * Verifica se o arquivo wp-config.php é gravável.
     *
     * @return bool
     */
    public function is_writable() {
        return file_exists( $this->config_path ) && is_writable( $this->config_path );
    }

    /**
     * Verifica se o arquivo wp-config.php existe.
     *
     * @return bool
     */
    public function exists() {
        return file_exists( $this->config_path );
    }

    /**
     * Obtém o valor de uma constante do wp-config.php.
     *
     * @param string $constant Nome da constante.
     * @return mixed|null Valor da constante ou null se não encontrada.
     */
    public function get_constant( $constant ) {
        if ( ! $this->exists() ) {
            return null;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $contents = file_get_contents( $this->config_path );
        if ( false === $contents ) {
            return null;
        }

        // Padrão para encontrar define('CONSTANT', value); com captura do valor
        $pattern = $this->build_define_pattern( $constant, true );

        if ( preg_match( $pattern, $contents, $matches ) ) {
            $value = trim( $matches[1] );
            
            // Converte string para valor PHP
            if ( 'true' === strtolower( $value ) ) {
                return true;
            }
            if ( 'false' === strtolower( $value ) ) {
                return false;
            }
            if ( is_numeric( $value ) ) {
                return floatval( $value ) == intval( $value ) ? intval( $value ) : floatval( $value );
            }
            // Remove aspas
            return trim( $value, '"\'' );
        }

        return null;
    }

    /**
     * Verifica se uma constante existe no wp-config.php.
     *
     * @param string $constant Nome da constante.
     * @return bool
     */
    public function has_constant( $constant ) {
        return null !== $this->get_constant( $constant );
    }

    /**
     * Constrói o padrão regex para encontrar uma constante define().
     *
     * @param string $constant      Nome da constante.
     * @param bool   $capture_value Se true, captura o valor em um grupo.
     * @param bool   $include_newline Se true, inclui quebra de linha opcional no final.
     * @return string Padrão regex.
     */
    private function build_define_pattern( $constant, $capture_value = false, $include_newline = false ) {
        $escaped_constant = preg_quote( $constant, '/' );
        
        // Padrão base para define
        $pattern = '/define\s*\(\s*[\'"]' . $escaped_constant . '[\'"]\s*,\s*';
        
        if ( $capture_value ) {
            // Captura o valor - usa grupo para valores simples (true, false, números, strings)
            $pattern .= '([^)]+)';
        } else {
            // Não captura, apenas match
            $pattern .= '[^)]+';
        }
        
        $pattern .= '\s*\)\s*;';
        
        if ( $include_newline ) {
            $pattern .= '\s*\n?';
        }
        
        $pattern .= '/i';
        
        return $pattern;
    }

    /**
     * Atualiza ou adiciona uma constante no wp-config.php.
     *
     * @param string $constant Nome da constante.
     * @param mixed  $value    Valor da constante.
     * @return bool True em sucesso, false em falha.
     */
    public function update_constant( $constant, $value ) {
        if ( ! $this->is_writable() ) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $contents = file_get_contents( $this->config_path );
        if ( false === $contents ) {
            return false;
        }

        // Formata o valor para escrita
        $formatted_value = $this->format_value( $value );
        $new_define      = "define( '" . $constant . "', " . $formatted_value . " );";

        // Padrão para encontrar define existente
        $pattern = $this->build_define_pattern( $constant );

        if ( preg_match( $pattern, $contents ) ) {
            // Atualiza constante existente
            $new_contents = preg_replace( $pattern, $new_define, $contents, 1 );
        } else {
            // Adiciona nova constante
            $new_contents = $this->insert_constant( $contents, $new_define );
        }

        if ( null === $new_contents || $new_contents === $contents ) {
            return $new_contents === $contents; // Retorna true se era igual
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents( $this->config_path, $new_contents );
    }

    /**
     * Remove uma constante do wp-config.php.
     *
     * @param string $constant Nome da constante.
     * @return bool True em sucesso, false em falha.
     */
    public function remove_constant( $constant ) {
        if ( ! $this->is_writable() ) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $contents = file_get_contents( $this->config_path );
        if ( false === $contents ) {
            return false;
        }

        // Padrão para encontrar define existente (incluindo linha em branco após)
        $pattern = $this->build_define_pattern( $constant, false, true );

        if ( ! preg_match( $pattern, $contents ) ) {
            return true; // Constante não existe, considera sucesso
        }

        $new_contents = preg_replace( $pattern, '', $contents, 1 );

        if ( null === $new_contents ) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        return false !== file_put_contents( $this->config_path, $new_contents );
    }

    /**
     * Formata um valor para escrita no wp-config.php.
     *
     * @param mixed $value Valor a formatar.
     * @return string Valor formatado.
     */
    private function format_value( $value ) {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( 'true' === $value || 'false' === $value ) {
            return $value;
        }
        if ( is_numeric( $value ) ) {
            return (string) $value;
        }
        return "'" . addslashes( (string) $value ) . "'";
    }

    /**
     * Insere uma nova constante no wp-config.php.
     *
     * @param string $contents Conteúdo atual do arquivo.
     * @param string $define   Linha define() a inserir.
     * @return string Novo conteúdo.
     */
    private function insert_constant( $contents, $define ) {
        // Tenta inserir antes do comentário "That's all, stop editing!"
        $anchor = "/* That's all, stop editing!";
        if ( false !== strpos( $contents, $anchor ) ) {
            return str_replace( $anchor, $define . "\n\n" . $anchor, $contents );
        }

        // Alternativa: insere antes de require_once ABSPATH
        $pattern = '/require_once\s*\(\s*ABSPATH\s*\.\s*[\'"]/i';
        if ( preg_match( $pattern, $contents, $matches, PREG_OFFSET_MATCH ) ) {
            $position = $matches[0][1];
            return substr( $contents, 0, $position ) . $define . "\n\n" . substr( $contents, $position );
        }

        // Alternativa: insere após $table_prefix
        $pattern = '/\$table_prefix\s*=\s*.+?;/i';
        if ( preg_match( $pattern, $contents, $matches, PREG_OFFSET_MATCH ) ) {
            $end_position = $matches[0][1] + strlen( $matches[0][0] );
            return substr( $contents, 0, $end_position ) . "\n\n" . $define . substr( $contents, $end_position );
        }

        // Último recurso: adiciona no final antes do fechamento PHP
        $pattern = '/\?>\s*$/';
        if ( preg_match( $pattern, $contents ) ) {
            return preg_replace( $pattern, $define . "\n\n?>\n", $contents );
        }

        // Se não há fechamento PHP, adiciona no final
        return $contents . "\n" . $define . "\n";
    }
}
