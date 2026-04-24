<?php
/**
 * Rate limiting persistente para fluxos públicos do Portal do Cliente.
 *
 * @package DPS_Client_Portal
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPS_Portal_Rate_Limiter' ) ) :

final class DPS_Portal_Rate_Limiter {

    /**
     * Option que armazena o estado do rate limiting.
     *
     * @var string
     */
    private const OPTION_KEY = 'dps_portal_rate_limits';

    /**
     * Instância única.
     *
     * @var DPS_Portal_Rate_Limiter|null
     */
    private static $instance = null;

    /**
     * Recupera a instância única.
     *
     * @return DPS_Portal_Rate_Limiter
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Verifica se um identificador excedeu o limite.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @param int    $limit Máximo permitido na janela.
     * @return bool
     */
    public function is_limited( $bucket, $identifier, $limit ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );

        if ( ! isset( $state[ $key ] ) || ! is_array( $state[ $key ] ) ) {
            return false;
        }

        if ( $this->is_expired( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
            return false;
        }

        return (int) $state[ $key ]['count'] >= (int) $limit;
    }

    /**
     * Registra uma nova tentativa.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @param int    $window_seconds Janela em segundos.
     * @return int Número de tentativas acumuladas.
     */
    public function hit( $bucket, $identifier, $window_seconds ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );
        $now   = time();

        $this->purge_expired_entries( $state, $now );

        if ( ! isset( $state[ $key ] ) || ! is_array( $state[ $key ] ) || $this->is_expired( $state[ $key ], $now ) ) {
            $state[ $key ] = [
                'count'      => 0,
                'expires_at' => $now + max( 1, (int) $window_seconds ),
            ];
        }

        $state[ $key ]['count'] = (int) $state[ $key ]['count'] + 1;
        $this->save_state( $state );

        return (int) $state[ $key ]['count'];
    }

    /**
     * Limpa o contador de um identificador.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @return void
     */
    public function clear( $bucket, $identifier ) {
        $state = $this->get_state();
        $key   = $this->build_key( $bucket, $identifier );

        if ( isset( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            $this->save_state( $state );
        }
    }

    /**
     * Retorna um resumo seguro dos buckets publicos para suporte administrativo.
     *
     * @param array<string, array<string, string>> $known_email_index Mapa hash => dados do cliente.
     * @return array<string, mixed>
     */
    public function get_public_access_summary( array $known_email_index = [] ) {
        $state        = $this->get_state();
        $now          = time();
        $before_count = count( $state );
        $buckets      = $this->get_public_bucket_definitions();
        $summary      = [
            'active_entries'    => 0,
            'limited_entries'   => 0,
            'email_entries'     => 0,
            'ip_entries'        => 0,
            'next_release_at'   => 0,
            'next_release_text' => '',
            'rows'              => [],
        ];

        $this->purge_expired_entries( $state, $now );
        if ( count( $state ) !== $before_count ) {
            $this->save_state( $state );
        }

        foreach ( $state as $key => $entry ) {
            if ( ! is_array( $entry ) || false === strpos( (string) $key, ':' ) ) {
                continue;
            }

            list( $bucket, $identifier_hash ) = explode( ':', (string) $key, 2 );
            if ( ! isset( $buckets[ $bucket ] ) || ! preg_match( '/^[a-f0-9]{32}$/', $identifier_hash ) ) {
                continue;
            }

            $count      = isset( $entry['count'] ) ? max( 0, (int) $entry['count'] ) : 0;
            $expires_at = isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : 0;

            if ( 0 === $count || $expires_at <= $now ) {
                continue;
            }

            $bucket_config = $buckets[ $bucket ];
            $limit         = (int) $bucket_config['limit'];
            $scope         = (string) $bucket_config['scope'];
            $is_limited    = $count >= $limit;
            $remaining     = max( 0, $expires_at - $now );
            $resolved      = $this->resolve_identifier_for_summary( $scope, $identifier_hash, $known_email_index );

            $summary['active_entries']++;
            if ( $is_limited ) {
                $summary['limited_entries']++;
            }

            if ( 'email' === $scope ) {
                $summary['email_entries']++;
            } elseif ( 'ip' === $scope ) {
                $summary['ip_entries']++;
            }

            if ( 0 === $summary['next_release_at'] || $expires_at < $summary['next_release_at'] ) {
                $summary['next_release_at'] = $expires_at;
            }

            $summary['rows'][] = [
                'bucket'              => $bucket,
                'bucket_label'        => (string) $bucket_config['label'],
                'scope'               => $scope,
                'scope_label'         => (string) $bucket_config['scope_label'],
                'identifier_hash'     => $identifier_hash,
                'identifier_label'    => $resolved['label'],
                'identifier_meta'     => $resolved['meta'],
                'identifier_url'      => $resolved['url'],
                'count'               => $count,
                'limit'               => $limit,
                'remaining'           => max( 0, $limit - $count ),
                'is_limited'          => $is_limited,
                'expires_at'          => $expires_at,
                'expires_in_seconds'  => $remaining,
                'expires_in_minutes'  => (int) ceil( $remaining / MINUTE_IN_SECONDS ),
            ];
        }

        usort(
            $summary['rows'],
            static function( $left, $right ) {
                if ( (bool) $left['is_limited'] !== (bool) $right['is_limited'] ) {
                    return $left['is_limited'] ? -1 : 1;
                }

                return (int) $left['expires_at'] <=> (int) $right['expires_at'];
            }
        );

        if ( $summary['next_release_at'] > 0 ) {
            $summary['next_release_text'] = human_time_diff( $now, (int) $summary['next_release_at'] );
        }

        return $summary;
    }

    /**
     * Recupera o estado persistido.
     *
     * @return array<string, array<string, int>>
     */
    private function get_state() {
        $state = get_option( self::OPTION_KEY, [] );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Persiste o estado.
     *
     * @param array<string, array<string, int>> $state Estado atualizado.
     * @return void
     */
    private function save_state( array $state ) {
        update_option( self::OPTION_KEY, $state, false );
    }

    /**
     * Remove entradas expiradas.
     *
     * @param array<string, array<string, int>> $state Estado atual.
     * @param int                               $now Timestamp de referência.
     * @return void
     */
    private function purge_expired_entries( array &$state, $now ) {
        foreach ( $state as $key => $entry ) {
            if ( ! is_array( $entry ) || $this->is_expired( $entry, $now ) ) {
                unset( $state[ $key ] );
            }
        }
    }

    /**
     * Determina se a entrada expirou.
     *
     * @param array<string, int> $entry Entrada monitorada.
     * @param int|null           $now Timestamp de referência.
     * @return bool
     */
    private function is_expired( array $entry, $now = null ) {
        $now = null === $now ? time() : (int) $now;

        return empty( $entry['expires_at'] ) || (int) $entry['expires_at'] <= $now;
    }

    /**
     * Monta a chave interna.
     *
     * @param string $bucket Nome lógico do bucket.
     * @param string $identifier Identificador monitorado.
     * @return string
     */
    private function build_key( $bucket, $identifier ) {
        return sanitize_key( $bucket ) . ':' . md5( strtolower( trim( (string) $identifier ) ) );
    }

    /**
     * Define os buckets publicos expostos no resumo administrativo.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_public_bucket_definitions() {
        return [
            'portal_access_request_ip'    => [
                'label'       => __( 'Pedido geral de acesso', 'dps-client-portal' ),
                'scope'       => 'ip',
                'scope_label' => __( 'IP', 'dps-client-portal' ),
                'limit'       => 5,
            ],
            'portal_access_link_ip'       => [
                'label'       => __( 'Magic link publico', 'dps-client-portal' ),
                'scope'       => 'ip',
                'scope_label' => __( 'IP', 'dps-client-portal' ),
                'limit'       => 3,
            ],
            'portal_access_link_email'    => [
                'label'       => __( 'Magic link publico', 'dps-client-portal' ),
                'scope'       => 'email',
                'scope_label' => __( 'E-mail', 'dps-client-portal' ),
                'limit'       => 3,
            ],
            'portal_password_access_ip'   => [
                'label'       => __( 'Criar ou redefinir senha', 'dps-client-portal' ),
                'scope'       => 'ip',
                'scope_label' => __( 'IP', 'dps-client-portal' ),
                'limit'       => 3,
            ],
            'portal_password_access_email' => [
                'label'       => __( 'Criar ou redefinir senha', 'dps-client-portal' ),
                'scope'       => 'email',
                'scope_label' => __( 'E-mail', 'dps-client-portal' ),
                'limit'       => 3,
            ],
            'portal_password_login_ip'    => [
                'label'       => __( 'Login por senha', 'dps-client-portal' ),
                'scope'       => 'ip',
                'scope_label' => __( 'IP', 'dps-client-portal' ),
                'limit'       => 5,
            ],
            'portal_password_login_email' => [
                'label'       => __( 'Login por senha', 'dps-client-portal' ),
                'scope'       => 'email',
                'scope_label' => __( 'E-mail', 'dps-client-portal' ),
                'limit'       => 5,
            ],
            'portal_token_validation_ip'  => [
                'label'       => __( 'Validacao de token', 'dps-client-portal' ),
                'scope'       => 'ip',
                'scope_label' => __( 'IP', 'dps-client-portal' ),
                'limit'       => 5,
            ],
        ];
    }

    /**
     * Resolve um identificador para exibicao sem expor IP bruto.
     *
     * @param string                              $scope Escopo do bucket.
     * @param string                              $identifier_hash Hash interno.
     * @param array<string, array<string, string>> $known_email_index Mapa de e-mails conhecidos.
     * @return array{label:string,meta:string,url:string}
     */
    private function resolve_identifier_for_summary( $scope, $identifier_hash, array $known_email_index ) {
        if ( 'email' === $scope && isset( $known_email_index[ $identifier_hash ] ) && is_array( $known_email_index[ $identifier_hash ] ) ) {
            return [
                'label' => isset( $known_email_index[ $identifier_hash ]['email'] ) ? (string) $known_email_index[ $identifier_hash ]['email'] : __( 'E-mail conhecido', 'dps-client-portal' ),
                'meta'  => isset( $known_email_index[ $identifier_hash ]['client'] ) ? (string) $known_email_index[ $identifier_hash ]['client'] : '',
                'url'   => isset( $known_email_index[ $identifier_hash ]['url'] ) ? (string) $known_email_index[ $identifier_hash ]['url'] : '',
            ];
        }

        $fingerprint = strtoupper( substr( $identifier_hash, 0, 8 ) );

        if ( 'email' === $scope ) {
            return [
                'label' => sprintf( __( 'E-mail nao resolvido #%s', 'dps-client-portal' ), $fingerprint ),
                'meta'  => __( 'Hash sem cliente publicado correspondente.', 'dps-client-portal' ),
                'url'   => '',
            ];
        }

        return [
            'label' => sprintf( __( 'IP fingerprint #%s', 'dps-client-portal' ), $fingerprint ),
            'meta'  => __( 'IP bruto nao e exibido no admin.', 'dps-client-portal' ),
            'url'   => '',
        ];
    }
}

endif;
