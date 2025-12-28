<?php
/**
 * Template de listagem de agendamentos.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/appointments-list.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * @package DesiPetShower
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$groups          = isset( $groups ) && is_array( $groups ) ? $groups : [];
$base_url        = isset( $base_url ) ? $base_url : '';
$visitor_only    = ! empty( $visitor_only );
$status_labels   = isset( $status_labels ) && is_array( $status_labels ) ? $status_labels : [];
$status_selector = isset( $status_selector ) && is_callable( $status_selector ) ? $status_selector : null;
$charge_renderer = isset( $charge_renderer ) && is_callable( $charge_renderer ) ? $charge_renderer : null;
$list_title      = isset( $list_title ) ? $list_title : __( 'Próximos Agendamentos', 'desi-pet-shower' );

?>
<div class="dps-appointments" id="dps-section-agendas-list">
    <h3><?php echo esc_html( $list_title ); ?></h3>
    <input
        type="text"
        class="dps-search dps-appointments-search"
        placeholder="<?php echo esc_attr__( 'Buscar...', 'desi-pet-shower' ); ?>"
    >

    <?php $has_items = false; ?>
    <?php foreach ( $groups as $group ) : ?>
        <?php
        $items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : [];
        if ( empty( $items ) ) {
            continue;
        }
        $has_items = true;
        $item_count = count( $items );
        ?>
        <div class="dps-appointments-group <?php echo esc_attr( $group['class'] ?? '' ); ?>">
            <h4>
                <?php echo esc_html( $group['title'] ?? '' ); ?>
                <span class="dps-group-badge"><?php echo esc_html( $item_count ); ?></span>
            </h4>
            <div class="dps-table-wrapper">
                <table class="dps-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Data', 'desi-pet-shower' ); ?></th>
                            <th><?php echo esc_html__( 'Horário', 'desi-pet-shower' ); ?></th>
                            <th><?php echo esc_html__( 'Cliente', 'desi-pet-shower' ); ?></th>
                            <th><?php echo esc_html__( 'Pet', 'desi-pet-shower' ); ?></th>
                            <th><?php echo esc_html__( 'Status', 'desi-pet-shower' ); ?></th>
                            <?php if ( ! $visitor_only ) : ?>
                                <th class="hide-mobile"><?php echo esc_html__( 'Cobrança', 'desi-pet-shower' ); ?></th>
                                <th><?php echo esc_html__( 'Ações', 'desi-pet-shower' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $items as $appt ) : ?>
                        <?php
                        $status_meta = get_post_meta( $appt->ID, 'appointment_status', true );
                        $status_meta = $status_meta ? $status_meta : 'pendente';
                        if ( 'finalizado e pago' === $status_meta ) {
                            $status_meta = 'finalizado_pago';
                        }

                        $date       = get_post_meta( $appt->ID, 'appointment_date', true );
                        $time       = get_post_meta( $appt->ID, 'appointment_time', true );
                        $client_id  = get_post_meta( $appt->ID, 'appointment_client_id', true );
                        $pet_id     = get_post_meta( $appt->ID, 'appointment_pet_id', true );
                        $client     = $client_id ? get_post( $client_id ) : null;
                        $pet        = $pet_id ? get_post( $pet_id ) : null;
                        $edit_url   = add_query_arg( [ 'tab' => 'agendas', 'dps_edit' => 'appointment', 'id' => $appt->ID ], $base_url );
                        $duplicate_url = add_query_arg( [ 'tab' => 'agendas', 'dps_duplicate' => 'appointment', 'id' => $appt->ID ], $base_url );
                        $delete_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'tab'        => 'agendas',
                                    'dps_delete' => 'appointment',
                                    'id'         => $appt->ID,
                                ],
                                $base_url
                            ),
                            'dps_delete',
                            'dps_nonce'
                        );
                        $row_class  = 'status-' . sanitize_html_class( $status_meta );
                        $date_fmt   = $date ? date_i18n( 'd-m-Y', strtotime( $date ) ) : '';
                        $pet_name   = $pet ? $pet->post_title : '-';
                        if ( get_post_meta( $appt->ID, 'subscription_id', true ) ) {
                            $pet_name .= ' ' . esc_html__( '(Assinatura)', 'desi-pet-shower' );
                        }
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td><?php echo esc_html( $date_fmt ); ?></td>
                            <td><?php echo esc_html( $time ); ?></td>
                            <td><?php echo esc_html( $client ? $client->post_title : '-' ); ?></td>
                            <td><?php echo esc_html( $pet_name ); ?></td>
                            <td>
                                <?php
                                if ( $status_selector ) {
                                    echo $status_selector( $appt->ID, $status_meta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                } else {
                                    echo esc_html( $status_labels[ $status_meta ] ?? $status_meta );
                                }
                                ?>
                            </td>
                            <?php if ( ! $visitor_only ) : ?>
                                <td class="hide-mobile">
                                    <?php
                                    if ( $charge_renderer ) {
                                        echo $charge_renderer( $appt->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    } else {
                                        echo '&#8211;';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" title="<?php echo esc_attr__( 'Editar', 'desi-pet-shower' ); ?>"><?php echo esc_html__( 'Editar', 'desi-pet-shower' ); ?></a>
                                    |
                                    <a href="<?php echo esc_url( $duplicate_url ); ?>" title="<?php echo esc_attr__( 'Duplicar agendamento', 'desi-pet-shower' ); ?>"><?php echo esc_html__( 'Duplicar', 'desi-pet-shower' ); ?></a>
                                    |
                                    <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza de que deseja excluir?', 'desi-pet-shower' ) ); ?>');" title="<?php echo esc_attr__( 'Excluir', 'desi-pet-shower' ); ?>">
                                        <?php echo esc_html__( 'Excluir', 'desi-pet-shower' ); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ( ! $has_items ) : ?>
        <div class="dps-empty-state">
            <span class="dps-empty-state__icon">📅</span>
            <h4 class="dps-empty-state__title"><?php echo esc_html__( 'Nenhum agendamento pendente', 'desi-pet-shower' ); ?></h4>
            <p class="dps-empty-state__description">
                <?php echo esc_html__( 'Todos os atendimentos foram finalizados ou não há agendamentos cadastrados. Use o formulário acima para criar um novo agendamento.', 'desi-pet-shower' ); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
