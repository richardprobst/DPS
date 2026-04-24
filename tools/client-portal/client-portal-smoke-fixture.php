<?php
/**
 * WP-CLI fixture for the published Client Portal public access smoke test.
 *
 * Usage:
 * wp eval-file tools/client-portal/client-portal-smoke-fixture.php create tag=codex_tag
 * wp eval-file tools/client-portal/client-portal-smoke-fixture.php cleanup tag=codex_tag
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "This file must run inside WordPress.\n" );
    exit( 1 );
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "This file is intended for WP-CLI only.\n" );
    exit( 1 );
}

$action = isset( $args[0] ) ? sanitize_key( $args[0] ) : 'create';

/**
 * Parses --key=value arguments passed after the action.
 *
 * @param array<int, string>    $raw_args Raw WP-CLI args.
 * @param array<string, mixed>  $assoc_args Raw WP-CLI associative args.
 * @return array<string, string>
 */
function dps_portal_smoke_parse_options( array $raw_args, array $assoc_args = [] ) {
    $options = [];

    foreach ( array_slice( $raw_args, 1 ) as $arg ) {
        $arg = (string) $arg;

        if ( 0 === strpos( $arg, '--' ) ) {
            $arg = substr( $arg, 2 );
        }

        if ( false === strpos( $arg, '=' ) ) {
            continue;
        }

        $pair  = explode( '=', $arg, 2 );
        $key   = sanitize_key( $pair[0] );
        $value = isset( $pair[1] ) ? (string) $pair[1] : '1';

        if ( '' !== $key ) {
            $options[ $key ] = $value;
        }
    }

    foreach ( $assoc_args as $key => $value ) {
        $key = sanitize_key( (string) $key );
        if ( '' === $key ) {
            continue;
        }

        if ( is_array( $value ) || is_object( $value ) ) {
            continue;
        }

        $options[ $key ] = ( false === $value || null === $value ) ? '' : (string) $value;
    }

    return $options;
}

/**
 * Emits JSON and exits.
 *
 * @param array<string, mixed> $payload Payload to print.
 * @return void
 */
function dps_portal_smoke_emit( array $payload ) {
    WP_CLI::line( wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
}

/**
 * Returns a deterministic option value.
 *
 * @param array<string, string> $options CLI options.
 * @param string                $key Option key.
 * @param string                $fallback Fallback value.
 * @return string
 */
function dps_portal_smoke_option( array $options, $key, $fallback ) {
    return isset( $options[ $key ] ) && '' !== trim( (string) $options[ $key ] )
        ? trim( (string) $options[ $key ] )
        : $fallback;
}

/**
 * Creates a temporary client and linked portal user.
 *
 * @param string $email Client e-mail.
 * @param string $name Client name.
 * @param string $password User password.
 * @param string $tag Fixture tag.
 * @return array{client_id:int,user_id:int,email:string}
 */
function dps_portal_smoke_create_client( $email, $name, $password, $tag ) {
    $client_id = wp_insert_post(
        [
            'post_type'   => 'dps_cliente',
            'post_status' => 'publish',
            'post_title'  => $name,
            'meta_input'  => [
                'client_email'           => $email,
                'client_phone'           => '+55 11 90000-0000',
                '_dps_codex_smoke_tag'   => $tag,
                'dps_codex_smoke_tag'    => $tag,
                'client_observations'    => 'Fixture temporario criado por WP-CLI para smoke test publicado do Portal do Cliente.',
            ],
        ],
        true
    );

    if ( is_wp_error( $client_id ) || ! $client_id ) {
        WP_CLI::error( is_wp_error( $client_id ) ? $client_id->get_error_message() : 'Falha ao criar cliente temporario.' );
    }

    if ( ! class_exists( 'DPS_Portal_User_Manager' ) ) {
        WP_CLI::error( 'DPS_Portal_User_Manager indisponivel. Confirme se o add-on do Portal esta ativo.' );
    }

    $user = DPS_Portal_User_Manager::get_instance()->ensure_user_for_client( $client_id );
    if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
        wp_delete_post( $client_id, true );
        WP_CLI::error( is_wp_error( $user ) ? $user->get_error_message() : 'Falha ao preparar usuario do portal.' );
    }

    wp_set_password( $password, $user->ID );
    update_user_meta( $user->ID, 'dps_client_id', $client_id );
    update_user_meta( $user->ID, 'dps_codex_smoke_tag', $tag );
    update_post_meta( $client_id, 'client_user_id', $user->ID );

    return [
        'client_id' => (int) $client_id,
        'user_id'   => (int) $user->ID,
        'email'     => $email,
    ];
}

/**
 * Builds a password reset URL for a user.
 *
 * @param WP_User $user Portal user.
 * @return string
 */
function dps_portal_smoke_get_reset_url( WP_User $user ) {
    if ( ! class_exists( 'DPS_Portal_User_Manager' ) ) {
        WP_CLI::error( 'DPS_Portal_User_Manager indisponivel.' );
    }

    $reset_url = DPS_Portal_User_Manager::get_instance()->get_password_reset_url( $user );
    if ( is_wp_error( $reset_url ) ) {
        WP_CLI::error( $reset_url->get_error_message() );
    }

    return (string) $reset_url;
}

/**
 * Expires the current password reset key while preserving the plain key URL.
 *
 * @param WP_User $user Portal user.
 * @return void
 */
function dps_portal_smoke_expire_reset_key( WP_User $user ) {
    $fresh_user = get_userdata( $user->ID );
    if ( ! $fresh_user instanceof WP_User ) {
        WP_CLI::error( 'Usuario do reset expirado indisponivel.' );
    }

    $activation_key = (string) $fresh_user->user_activation_key;
    $parts = explode( ':', $activation_key, 2 );
    if ( 2 !== count( $parts ) || '' === $parts[1] ) {
        WP_CLI::error( 'Nao foi possivel localizar o hash do reset key para expirar o fixture.' );
    }

    $expired_at = time() - ( 3 * DAY_IN_SECONDS );
    wp_update_user(
        [
            'ID'                  => $user->ID,
            'user_activation_key' => $expired_at . ':' . $parts[1],
        ]
    );
}

/**
 * Generates a magic link URL for a temporary client.
 *
 * @param int $client_id Client ID.
 * @return string
 */
function dps_portal_smoke_get_magic_url( $client_id ) {
    if ( ! class_exists( 'DPS_Portal_Token_Manager' ) ) {
        WP_CLI::error( 'DPS_Portal_Token_Manager indisponivel.' );
    }

    $token = DPS_Portal_Token_Manager::get_instance()->generate_token( $client_id, 'login' );
    if ( false === $token ) {
        WP_CLI::error( 'Falha ao gerar token temporario de magic link.' );
    }

    return DPS_Portal_Token_Manager::get_instance()->generate_access_url( $token );
}

/**
 * Clears rate-limit entries tied to fixture identifiers.
 *
 * @param array<int, string> $identifiers Identifiers.
 * @return int Removed entries.
 */
function dps_portal_smoke_clear_rate_limits( array $identifiers ) {
    $state = get_option( 'dps_portal_rate_limits', [] );
    if ( ! is_array( $state ) ) {
        return 0;
    }

    $buckets = [
        'portal_access_link_email',
        'portal_password_access_email',
        'portal_password_login_email',
        'portal_access_link_ip',
        'portal_password_access_ip',
        'portal_password_login_ip',
        'portal_token_validation_ip',
    ];
    $removed = 0;

    foreach ( $buckets as $bucket ) {
        foreach ( $identifiers as $identifier ) {
            $identifier = trim( (string) $identifier );
            if ( '' === $identifier ) {
                continue;
            }

            $key = sanitize_key( $bucket ) . ':' . md5( strtolower( $identifier ) );
            if ( isset( $state[ $key ] ) ) {
                unset( $state[ $key ] );
                $removed++;
            }
        }
    }

    update_option( 'dps_portal_rate_limits', $state, false );

    return $removed;
}

/**
 * Cleans temporary posts and users by tag.
 *
 * @param string              $tag Fixture tag.
 * @param array<int, string>  $extra_identifiers Extra rate-limit identifiers.
 * @return array<string, mixed>
 */
function dps_portal_smoke_cleanup( $tag, array $extra_identifiers = [] ) {
    $tag = sanitize_text_field( $tag );
    if ( '' === $tag ) {
        WP_CLI::error( 'Informe --tag para cleanup.' );
    }

    if ( ! function_exists( 'wp_delete_user' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    $identifiers = $extra_identifiers;
    $deleted_posts = 0;
    $deleted_users = 0;

    $posts = get_posts(
        [
            'post_type'      => [ 'dps_cliente', 'dps_pet' ],
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'dps_codex_smoke_tag',
                    'value' => $tag,
                ],
            ],
        ]
    );

    foreach ( $posts as $post_id ) {
        $email = get_post_meta( $post_id, 'client_email', true );
        if ( is_string( $email ) && '' !== $email ) {
            $identifiers[] = $email;
        }

        if ( wp_delete_post( $post_id, true ) ) {
            $deleted_posts++;
        }
    }

    $users = get_users(
        [
            'meta_key'   => 'dps_codex_smoke_tag',
            'meta_value' => $tag,
            'fields'     => 'ID',
        ]
    );

    foreach ( $users as $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user instanceof WP_User ) {
            $identifiers[] = $user->user_email;
        }

        if ( wp_delete_user( (int) $user_id ) ) {
            $deleted_users++;
        }
    }

    $identifiers = array_values( array_unique( array_filter( array_map( 'strval', $identifiers ) ) ) );

    return [
        'deleted_posts'       => $deleted_posts,
        'deleted_users'       => $deleted_users,
        'rate_limits_removed' => dps_portal_smoke_clear_rate_limits( $identifiers ),
    ];
}

$options = dps_portal_smoke_parse_options( $args, isset( $assoc_args ) && is_array( $assoc_args ) ? $assoc_args : [] );
$tag     = dps_portal_smoke_option( $options, 'tag', 'codex_portal_smoke_' . gmdate( 'YmdHis' ) );

if ( 'cleanup' === $action ) {
    $extra_identifiers = [];
    foreach ( [ 'email', 'expired-email', 'anti-enum-email', 'request-ip' ] as $key ) {
        if ( ! empty( $options[ $key ] ) ) {
            $extra_identifiers[] = (string) $options[ $key ];
        }
    }

    dps_portal_smoke_emit(
        [
            'ok'      => true,
            'action'  => 'cleanup',
            'tag'     => $tag,
            'cleanup' => dps_portal_smoke_cleanup( $tag, $extra_identifiers ),
        ]
    );
    return;
}

if ( 'create' !== $action ) {
    WP_CLI::error( 'Acao invalida. Use create ou cleanup.' );
}

dps_portal_smoke_cleanup( $tag );

$timestamp       = gmdate( 'YmdHis' );
$email           = sanitize_email( dps_portal_smoke_option( $options, 'email', 'codex.portal.smoke.' . $timestamp . '@example.com' ) );
$reset_email     = sanitize_email( dps_portal_smoke_option( $options, 'reset-email', 'codex.portal.smoke.reset.' . $timestamp . '@example.com' ) );
$expired_email   = sanitize_email( dps_portal_smoke_option( $options, 'expired-email', 'codex.portal.smoke.expired.' . $timestamp . '@example.com' ) );
$anti_enum_email = sanitize_email( dps_portal_smoke_option( $options, 'anti-enum-email', 'codex.portal.smoke.unknown.' . $timestamp . '@example.com' ) );
$password        = dps_portal_smoke_option( $options, 'password', 'DpsSmoke!' . $timestamp );
$new_password    = dps_portal_smoke_option( $options, 'new-password', 'DpsSmokeNew!' . $timestamp );

if ( ! is_email( $email ) || ! is_email( $reset_email ) || ! is_email( $expired_email ) || ! is_email( $anti_enum_email ) ) {
    WP_CLI::error( 'E-mails de fixture invalidos.' );
}

$main_fixture    = dps_portal_smoke_create_client( $email, 'Codex Portal Smoke ' . $timestamp, $password, $tag );
$reset_fixture   = dps_portal_smoke_create_client( $reset_email, 'Codex Portal Smoke Reset ' . $timestamp, $password, $tag );
$expired_fixture = dps_portal_smoke_create_client( $expired_email, 'Codex Portal Smoke Expired ' . $timestamp, $password, $tag );

$main_user    = get_userdata( $main_fixture['user_id'] );
$reset_user   = get_userdata( $reset_fixture['user_id'] );
$expired_user = get_userdata( $expired_fixture['user_id'] );

if ( ! $main_user instanceof WP_User || ! $reset_user instanceof WP_User || ! $expired_user instanceof WP_User ) {
    WP_CLI::error( 'Usuarios temporarios indisponiveis apos criacao.' );
}

$valid_reset_url   = dps_portal_smoke_get_reset_url( $reset_user );
$expired_reset_url = dps_portal_smoke_get_reset_url( $expired_user );
dps_portal_smoke_expire_reset_key( $expired_user );

$portal_url = function_exists( 'dps_get_portal_page_url' ) ? dps_get_portal_page_url() : home_url( '/portal-do-cliente/' );
$invalid_reset_url = add_query_arg(
    [
        'dps_action' => 'portal_password_reset',
        'key'        => 'invalid-smoke-key',
        'login'      => $email,
    ],
    $portal_url
);

dps_portal_smoke_emit(
    [
        'ok'               => true,
        'action'           => 'create',
        'tag'              => $tag,
        'portalUrl'        => $portal_url,
        'email'            => $email,
        'resetEmail'       => $reset_email,
        'expiredEmail'     => $expired_email,
        'antiEnumEmail'    => $anti_enum_email,
        'password'         => $password,
        'newPassword'      => $new_password,
        'clientId'         => $main_fixture['client_id'],
        'userId'           => $main_fixture['user_id'],
        'resetClientId'    => $reset_fixture['client_id'],
        'resetUserId'      => $reset_fixture['user_id'],
        'expiredClientId'  => $expired_fixture['client_id'],
        'expiredUserId'    => $expired_fixture['user_id'],
        'magicUrl'         => dps_portal_smoke_get_magic_url( $main_fixture['client_id'] ),
        'validResetUrl'    => $valid_reset_url,
        'expiredResetUrl'  => $expired_reset_url,
        'invalidResetUrl'  => $invalid_reset_url,
    ]
);
