<?php
/**
 * Template: Cards de resumo do cliente.
 *
 * Exibe cards com métricas do cliente: cadastro, atendimentos, total gasto,
 * último atendimento e pendências.
 *
 * Variáveis disponíveis:
 * @var string $client_since       Data de cadastro do cliente (formato mm/YYYY ou vazio).
 * @var int    $total_appointments Total de agendamentos.
 * @var float  $total_spent        Total gasto em atendimentos finalizados.
 * @var string $last_appointment   Data do último atendimento (dd/mm/YYYY ou vazio).
 * @var float  $pending_amount     Valor total de pendências financeiras.
 *
 * @package DesiPetShower
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="dps-client-summary">
    <?php if ( ! empty( $client_since ) ) : ?>
    <div class="dps-summary-card">
        <span class="dps-summary-card__icon" aria-hidden="true">🗓️</span>
        <span class="dps-summary-card__value"><?php echo esc_html( $client_since ); ?></span>
        <span class="dps-summary-card__label"><?php echo esc_html__( 'Cliente Desde', 'desi-pet-shower' ); ?></span>
    </div>
    <?php endif; ?>

    <div class="dps-summary-card dps-summary-card--highlight">
        <span class="dps-summary-card__icon" aria-hidden="true">📋</span>
        <span class="dps-summary-card__value"><?php echo esc_html( $total_appointments ); ?></span>
        <span class="dps-summary-card__label"><?php echo esc_html__( 'Total de Atendimentos', 'desi-pet-shower' ); ?></span>
    </div>

    <div class="dps-summary-card dps-summary-card--success">
        <span class="dps-summary-card__icon" aria-hidden="true">💰</span>
        <span class="dps-summary-card__value">R$ <?php echo esc_html( number_format_i18n( $total_spent, 2 ) ); ?></span>
        <span class="dps-summary-card__label"><?php echo esc_html__( 'Total Gasto', 'desi-pet-shower' ); ?></span>
    </div>

    <div class="dps-summary-card">
        <span class="dps-summary-card__icon" aria-hidden="true">📅</span>
        <span class="dps-summary-card__value"><?php echo esc_html( $last_appointment ?: '-' ); ?></span>
        <span class="dps-summary-card__label"><?php echo esc_html__( 'Último Atendimento', 'desi-pet-shower' ); ?></span>
    </div>

    <div class="dps-summary-card <?php echo $pending_amount > 0 ? 'dps-summary-card--warning' : ''; ?>">
        <span class="dps-summary-card__icon" aria-hidden="true"><?php echo $pending_amount > 0 ? '⚠️' : '✅'; ?></span>
        <span class="dps-summary-card__value">R$ <?php echo esc_html( number_format_i18n( $pending_amount, 2 ) ); ?></span>
        <span class="dps-summary-card__label"><?php echo esc_html__( 'Pendências', 'desi-pet-shower' ); ?></span>
    </div>
</div>
