<?php
/**
 * Template da tela administrativa de logins do portal.
 *
 * @package DPS_Client_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$title_tag    = ( 'admin' === $context ) ? 'h1' : 'h2';
$search_value = isset( $search ) ? (string) $search : '';
$throttle_rows = isset( $throttle_summary['rows'] ) && is_array( $throttle_summary['rows'] ) ? $throttle_summary['rows'] : [];

if ( 'admin' === $context ) {
    echo '<div class="wrap">';
}
?>
<div class="dps-portal-admin-shell dps-portal-logins-screen">
    <header class="dps-portal-admin-shell__header">
        <<?php echo esc_attr( $title_tag ); ?> class="dps-portal-admin-shell__title"><?php esc_html_e( 'Portal do Cliente: Logins', 'dps-client-portal' ); ?></<?php echo esc_attr( $title_tag ); ?>>
        <p class="dps-portal-admin-shell__description"><?php esc_html_e( 'Gerencie o acesso por magic link e por e-mail e senha. O usuario do portal sempre deve ser o e-mail cadastrado no cliente.', 'dps-client-portal' ); ?></p>
    </header>

    <?php if ( ! empty( $feedback_messages ) ) : ?>
        <div class="dps-portal-admin-shell__feedback">
            <?php foreach ( $feedback_messages as $feedback ) : ?>
                <div class="dps-portal-admin-shell__notice dps-portal-admin-shell__notice--<?php echo esc_attr( 'success' === $feedback['type'] ? 'success' : 'error' ); ?>">
                    <?php echo esc_html( $feedback['text'] ); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="dps-portal-admin-shell__summary" aria-label="<?php esc_attr_e( 'Resumo dos acessos', 'dps-client-portal' ); ?>">
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Clientes listados', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['total_clients'] ) ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Com e-mail valido', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['with_email'] ) ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Prontos para senha', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['password_ready'] ) ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Links ativos', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['active_magic_links'] ) ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Cadastros para sincronizar', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['needs_sync'] ) ); ?></strong>
        </article>
        <article class="dps-admin-summary-card">
            <span class="dps-admin-summary-card__label"><?php esc_html_e( 'Conflitos de e-mail', 'dps-client-portal' ); ?></span>
            <strong class="dps-admin-summary-card__value"><?php echo esc_html( number_format_i18n( (int) $login_summary['email_conflicts'] ) ); ?></strong>
        </article>
    </section>

    <section class="dps-portal-throttle-panel" aria-labelledby="dps-portal-throttle-title">
        <header class="dps-portal-throttle-panel__header">
            <div>
                <h2 id="dps-portal-throttle-title"><?php esc_html_e( 'Throttling publico', 'dps-client-portal' ); ?></h2>
                <p><?php esc_html_e( 'Resumo em tempo real dos limites de acesso publico por e-mail/IP para suporte de login, magic link e senha.', 'dps-client-portal' ); ?></p>
            </div>
            <span class="dps-status-pill dps-status-pill--<?php echo ! empty( $throttle_summary['limited_entries'] ) ? 'danger' : ( ! empty( $throttle_summary['active_entries'] ) ? 'warning' : 'success' ); ?>">
                <?php
                echo ! empty( $throttle_summary['limited_entries'] )
                    ? esc_html__( 'Bloqueios ativos', 'dps-client-portal' )
                    : ( ! empty( $throttle_summary['active_entries'] ) ? esc_html__( 'Monitorando janela', 'dps-client-portal' ) : esc_html__( 'Sem throttling ativo', 'dps-client-portal' ) );
                ?>
            </span>
        </header>

        <div class="dps-portal-throttle-panel__metrics" aria-label="<?php esc_attr_e( 'Resumo do throttling publico', 'dps-client-portal' ); ?>">
            <div class="dps-portal-throttle-metric">
                <span><?php esc_html_e( 'Janelas ativas', 'dps-client-portal' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( (int) $throttle_summary['active_entries'] ) ); ?></strong>
            </div>
            <div class="dps-portal-throttle-metric">
                <span><?php esc_html_e( 'Bloqueios agora', 'dps-client-portal' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( (int) $throttle_summary['limited_entries'] ) ); ?></strong>
            </div>
            <div class="dps-portal-throttle-metric">
                <span><?php esc_html_e( 'E-mails', 'dps-client-portal' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( (int) $throttle_summary['email_entries'] ) ); ?></strong>
            </div>
            <div class="dps-portal-throttle-metric">
                <span><?php esc_html_e( 'IPs', 'dps-client-portal' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( (int) $throttle_summary['ip_entries'] ) ); ?></strong>
            </div>
            <div class="dps-portal-throttle-metric dps-portal-throttle-metric--wide">
                <span><?php esc_html_e( 'Proxima liberacao', 'dps-client-portal' ); ?></span>
                <strong><?php echo ! empty( $throttle_summary['next_release_text'] ) ? esc_html( sprintf( __( 'em %s', 'dps-client-portal' ), $throttle_summary['next_release_text'] ) ) : esc_html__( 'nenhuma janela ativa', 'dps-client-portal' ); ?></strong>
            </div>
        </div>

        <?php if ( ! empty( $throttle_rows ) ) : ?>
            <div class="dps-portal-throttle-table-wrap">
                <table class="widefat dps-portal-throttle-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Fluxo', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Escopo', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Identificador', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Tentativas', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dps-client-portal' ); ?></th>
                            <th><?php esc_html_e( 'Libera em', 'dps-client-portal' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $throttle_rows as $row ) : ?>
                            <tr>
                                <td data-label="<?php echo esc_attr__( 'Fluxo', 'dps-client-portal' ); ?>"><?php echo esc_html( $row['bucket_label'] ); ?></td>
                                <td data-label="<?php echo esc_attr__( 'Escopo', 'dps-client-portal' ); ?>"><?php echo esc_html( $row['scope_label'] ); ?></td>
                                <td data-label="<?php echo esc_attr__( 'Identificador', 'dps-client-portal' ); ?>">
                                    <?php if ( ! empty( $row['identifier_url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $row['identifier_url'] ); ?>"><?php echo esc_html( $row['identifier_label'] ); ?></a>
                                    <?php else : ?>
                                        <span><?php echo esc_html( $row['identifier_label'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $row['identifier_meta'] ) ) : ?>
                                        <small><?php echo esc_html( $row['identifier_meta'] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr__( 'Tentativas', 'dps-client-portal' ); ?>">
                                    <?php echo esc_html( sprintf( __( '%1$d de %2$d', 'dps-client-portal' ), (int) $row['count'], (int) $row['limit'] ) ); ?>
                                </td>
                                <td data-label="<?php echo esc_attr__( 'Status', 'dps-client-portal' ); ?>">
                                    <span class="dps-status-pill dps-status-pill--<?php echo ! empty( $row['is_limited'] ) ? 'danger' : 'warning'; ?>">
                                        <?php echo ! empty( $row['is_limited'] ) ? esc_html__( 'Limitado', 'dps-client-portal' ) : esc_html__( 'Em janela', 'dps-client-portal' ); ?>
                                    </span>
                                </td>
                                <td data-label="<?php echo esc_attr__( 'Libera em', 'dps-client-portal' ); ?>">
                                    <strong><?php echo esc_html( sprintf( _n( '%d minuto', '%d minutos', (int) $row['expires_in_minutes'], 'dps-client-portal' ), (int) $row['expires_in_minutes'] ) ); ?></strong>
                                    <small><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['expires_at'] ) ); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="dps-portal-throttle-panel__note"><?php esc_html_e( 'IPs aparecem como fingerprint para suporte sem expor o endereco bruto. E-mails conhecidos sao resolvidos contra clientes publicados.', 'dps-client-portal' ); ?></p>
        <?php else : ?>
            <div class="dps-portal-throttle-panel__empty"><?php esc_html_e( 'Nenhuma janela publica de throttling ativa neste momento.', 'dps-client-portal' ); ?></div>
        <?php endif; ?>
    </section>

    <form method="get" action="<?php echo esc_url( $base_url ); ?>" class="dps-portal-admin-shell__filters">
        <?php if ( 'frontend' === $context ) : ?>
            <input type="hidden" name="tab" value="logins" />
        <?php endif; ?>
        <label class="screen-reader-text" for="dps-search-logins"><?php esc_html_e( 'Buscar clientes', 'dps-client-portal' ); ?></label>
        <input id="dps-search-logins" type="search" name="dps_search" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php echo esc_attr__( 'Buscar por nome do cliente', 'dps-client-portal' ); ?>" />
        <button type="submit" class="button button-secondary"><?php esc_html_e( 'Buscar', 'dps-client-portal' ); ?></button>
    </form>

    <?php if ( ! empty( $clients ) ) : ?>
        <div class="dps-portal-table-wrap">
            <table class="widefat striped dps-portal-logins-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Cliente', 'dps-client-portal' ); ?></th>
                        <th><?php esc_html_e( 'Contato', 'dps-client-portal' ); ?></th>
                        <th><?php esc_html_e( 'Estado do acesso', 'dps-client-portal' ); ?></th>
                        <th><?php esc_html_e( 'Ultimo login', 'dps-client-portal' ); ?></th>
                        <th><?php esc_html_e( 'Operacoes', 'dps-client-portal' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $clients as $client_data ) : ?>
                        <?php
                        $client_id          = (int) $client_data['id'];
                        $token_stats        = is_array( $client_data['token_stats'] ) ? $client_data['token_stats'] : [];
                        $password_access    = is_array( $client_data['password_access'] ) ? $client_data['password_access'] : [];
                        $last_login         = is_array( $client_data['last_login'] ) ? $client_data['last_login'] : [];
                        $recent_activity    = isset( $client_data['recent_activity'] ) && is_array( $client_data['recent_activity'] ) ? $client_data['recent_activity'] : [];
                        $active_tokens      = isset( $token_stats['active_tokens'] ) ? (int) $token_stats['active_tokens'] : 0;
                        $active_permanent   = isset( $client_data['active_permanent'] ) ? (int) $client_data['active_permanent'] : 0;
                        $password_status    = isset( $password_access['status'] ) ? (string) $password_access['status'] : 'missing_email';
                        $password_can_send  = ! empty( $password_access['can_send_password_email'] );
                        $password_needs_sync = ! empty( $password_access['needs_sync'] );
                        $has_email          = ! empty( $client_data['email'] );
                        $has_phone          = ! empty( $client_data['phone'] );
                        $last_login_display = ! empty( $last_login['timestamp'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $last_login['timestamp'] ) ) : '';
                        $magic_state_class  = $active_tokens > 0 ? 'success' : ( ! empty( $token_stats['total_used'] ) ? 'neutral' : 'idle' );
                        $password_state_class = 'conflict' === $password_status ? 'danger' : ( ! empty( $password_access['can_use_password'] ) ? 'success' : 'warning' );
                        ?>
                        <tr class="dps-portal-logins-table__row" data-client-row data-client-id="<?php echo esc_attr( $client_id ); ?>" data-client-name="<?php echo esc_attr( $client_data['name'] ); ?>" data-client-phone="<?php echo esc_attr( $client_data['phone'] ); ?>" data-client-email="<?php echo esc_attr( $client_data['email'] ); ?>">
                            <td data-label="<?php echo esc_attr__( 'Cliente', 'dps-client-portal' ); ?>">
                                <strong class="dps-portal-logins-table__client-name"><?php echo esc_html( $client_data['name'] ); ?></strong>
                                <div class="dps-portal-logins-table__meta">
                                    <span>#<?php echo esc_html( $client_id ); ?></span>
                                    <?php if ( ! empty( $client_data['edit_url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $client_data['edit_url'] ); ?>"><?php esc_html_e( 'Abrir cadastro', 'dps-client-portal' ); ?></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="<?php echo esc_attr__( 'Contato', 'dps-client-portal' ); ?>">
                                <div class="dps-portal-logins-table__stack">
                                    <span><?php echo $has_email ? esc_html( $client_data['email'] ) : esc_html__( 'Sem e-mail cadastrado', 'dps-client-portal' ); ?></span>
                                    <span><?php echo $has_phone ? esc_html( $client_data['phone'] ) : esc_html__( 'Sem telefone cadastrado', 'dps-client-portal' ); ?></span>
                                </div>
                            </td>
                            <td data-label="<?php echo esc_attr__( 'Estado do acesso', 'dps-client-portal' ); ?>">
                                <div class="dps-portal-status-list">
                                    <div class="dps-portal-status-item">
                                        <span class="dps-status-pill dps-status-pill--<?php echo esc_attr( $magic_state_class ); ?>"><?php esc_html_e( 'Magic link', 'dps-client-portal' ); ?></span>
                                        <p><?php echo $active_tokens > 0 ? esc_html( sprintf( _n( '%d link ativo', '%d links ativos', $active_tokens, 'dps-client-portal' ), $active_tokens ) ) : esc_html__( 'Sem links ativos neste momento.', 'dps-client-portal' ); ?></p>
                                        <?php if ( $active_permanent > 0 ) : ?>
                                            <small><?php echo esc_html( sprintf( _n( '%d link permanente ativo', '%d links permanentes ativos', $active_permanent, 'dps-client-portal' ), $active_permanent ) ); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dps-portal-status-item">
                                        <span class="dps-status-pill dps-status-pill--<?php echo esc_attr( $password_state_class ); ?>"><?php esc_html_e( 'E-mail e senha', 'dps-client-portal' ); ?></span>
                                        <p><?php echo esc_html( isset( $password_access['status_label'] ) ? (string) $password_access['status_label'] : __( 'Sem status', 'dps-client-portal' ) ); ?></p>
                                        <small><?php echo esc_html( isset( $password_access['status_description'] ) ? (string) $password_access['status_description'] : '' ); ?></small>
                                        <?php if ( ! empty( $password_access['user_id'] ) ) : ?>
                                            <small><?php echo esc_html( sprintf( __( 'Usuario WP #%1$d: %2$s', 'dps-client-portal' ), (int) $password_access['user_id'], (string) $password_access['user_login'] ) ); ?></small>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $password_access['legacy_username'] ) ) : ?>
                                            <small><?php esc_html_e( 'Conta legada: o login interno ainda difere do e-mail, mas o acesso por e-mail segue valido.', 'dps-client-portal' ); ?></small>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $client_data['last_password_email'] ) ) : ?>
                                            <small><?php echo esc_html( sprintf( __( 'Ultimo e-mail de senha: %s', 'dps-client-portal' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $client_data['last_password_email'] ) ) ) ); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?php echo esc_attr__( 'Ultimo login', 'dps-client-portal' ); ?>">
                                <div class="dps-portal-logins-table__last-login">
                                    <strong><?php echo esc_html( isset( $last_login['label'] ) ? (string) $last_login['label'] : __( 'Nenhum acesso ainda', 'dps-client-portal' ) ); ?></strong>
                                    <span><?php echo $last_login_display ? esc_html( $last_login_display ) : esc_html__( 'Nenhum acesso registrado.', 'dps-client-portal' ); ?></span>
                                </div>
                                <?php if ( ! empty( $recent_activity ) ) : ?>
                                    <ul class="dps-portal-logins-table__activity">
                                        <?php foreach ( $recent_activity as $activity ) : ?>
                                            <li>
                                                <strong><?php echo esc_html( $activity['label'] ); ?></strong>
                                                <span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $activity['timestamp'] ) ) ); ?></span>
                                                <?php if ( ! empty( $activity['meta'] ) ) : ?>
                                                    <small><?php echo esc_html( $activity['meta'] ); ?></small>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php echo esc_attr__( 'Operacoes', 'dps-client-portal' ); ?>">
                                <div class="dps-portal-logins-table__actions">
                                    <button type="button" class="button button-primary dps-generate-token-btn" data-client-id="<?php echo esc_attr( $client_id ); ?>" data-client-name="<?php echo esc_attr( $client_data['name'] ); ?>">
                                        <?php esc_html_e( 'Gerar link', 'dps-client-portal' ); ?>
                                    </button>

                                    <?php if ( $active_tokens > 0 ) : ?>
                                        <button type="button" class="button button-secondary dps-revoke-token-btn" data-client-id="<?php echo esc_attr( $client_id ); ?>">
                                            <?php esc_html_e( 'Revogar links', 'dps-client-portal' ); ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ( $password_can_send ) : ?>
                                        <button type="button" class="button dps-send-password-access-btn" data-client-id="<?php echo esc_attr( $client_id ); ?>">
                                            <?php esc_html_e( 'Enviar acesso por senha', 'dps-client-portal' ); ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ( $has_email && ( $password_needs_sync || empty( $password_access['user_id'] ) ) ) : ?>
                                        <button type="button" class="button dps-sync-portal-user-btn" data-client-id="<?php echo esc_attr( $client_id ); ?>">
                                            <?php esc_html_e( 'Sincronizar usuario', 'dps-client-portal' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="dps-portal-logins-table__feedback" data-row-feedback aria-live="polite"></div>
                                <div class="dps-generated-token" data-generated-token></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="dps-portal-admin-shell__empty"><?php esc_html_e( 'Nenhum cliente encontrado.', 'dps-client-portal' ); ?></div>
    <?php endif; ?>
</div>

<div id="dps-token-type-modal" class="dps-admin-modal" hidden>
    <div class="dps-admin-modal__backdrop" data-modal-close></div>
    <div class="dps-admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dps-token-modal-title">
        <header class="dps-admin-modal__header">
            <h2 id="dps-token-modal-title"><?php esc_html_e( 'Gerar link de acesso', 'dps-client-portal' ); ?></h2>
            <button type="button" class="dps-admin-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'dps-client-portal' ); ?>">&times;</button>
        </header>
        <div class="dps-admin-modal__body">
            <p id="dps-token-client-name" class="dps-admin-modal__lead"></p>
            <label class="dps-admin-choice">
                <input type="radio" name="dps_token_type" value="login" checked />
                <span>
                    <strong><?php esc_html_e( 'Link temporario', 'dps-client-portal' ); ?></strong>
                    <small><?php esc_html_e( 'Expira em 30 minutos. Ideal para envio imediato por e-mail ou WhatsApp.', 'dps-client-portal' ); ?></small>
                </span>
            </label>
            <label class="dps-admin-choice">
                <input type="radio" name="dps_token_type" value="permanent" />
                <span>
                    <strong><?php esc_html_e( 'Link permanente', 'dps-client-portal' ); ?></strong>
                    <small><?php esc_html_e( 'Permanece valido ate revogar manualmente. Use apenas para clientes recorrentes.', 'dps-client-portal' ); ?></small>
                </span>
            </label>
        </div>
        <footer class="dps-admin-modal__footer">
            <button type="button" class="button button-secondary" data-modal-close><?php esc_html_e( 'Cancelar', 'dps-client-portal' ); ?></button>
            <button type="button" class="button button-primary" id="dps-confirm-generate-token"><?php esc_html_e( 'Gerar link', 'dps-client-portal' ); ?></button>
        </footer>
    </div>
</div>

<div id="dps-email-preview-modal" class="dps-admin-modal" hidden>
    <div class="dps-admin-modal__backdrop" data-modal-close></div>
    <div class="dps-admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dps-email-modal-title">
        <header class="dps-admin-modal__header">
            <h2 id="dps-email-modal-title"><?php esc_html_e( 'Preparar e-mail com magic link', 'dps-client-portal' ); ?></h2>
            <button type="button" class="dps-admin-modal__close" data-modal-close aria-label="<?php esc_attr_e( 'Fechar', 'dps-client-portal' ); ?>">&times;</button>
        </header>
        <div class="dps-admin-modal__body">
            <div class="dps-admin-field">
                <label for="dps-email-subject"><?php esc_html_e( 'Assunto', 'dps-client-portal' ); ?></label>
                <input type="text" id="dps-email-subject" class="widefat" />
            </div>
            <div class="dps-admin-field">
                <label for="dps-email-body"><?php esc_html_e( 'Mensagem', 'dps-client-portal' ); ?></label>
                <textarea id="dps-email-body" rows="10" class="widefat"></textarea>
            </div>
        </div>
        <footer class="dps-admin-modal__footer">
            <button type="button" class="button button-secondary" data-modal-close><?php esc_html_e( 'Cancelar', 'dps-client-portal' ); ?></button>
            <button type="button" class="button button-primary" id="dps-confirm-send-email"><?php esc_html_e( 'Enviar e-mail', 'dps-client-portal' ); ?></button>
        </footer>
    </div>
</div>
<?php
if ( 'admin' === $context ) {
    echo '</div>';
}
