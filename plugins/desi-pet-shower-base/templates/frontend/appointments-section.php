<?php
/**
 * Template da seção de Agendamentos (formulário e listagem).
 *
 * Este template é um placeholder para futura refatoração completa.
 * Atualmente, a renderização ainda ocorre diretamente no método
 * render_appointments_section() da classe DPS_Base_Frontend.
 *
 * Em fases futuras de refatoração, o HTML do formulário de agendamento
 * será movido para este template, seguindo o padrão já estabelecido
 * em clients-section.php.
 *
 * Sobrescreva em wp-content/themes/SEU_TEMA/dps-templates/frontend/appointments-section.php
 * para personalizar o HTML mantendo a lógica do plugin.
 *
 * PADRÃO DE REFATORAÇÃO:
 * 1. A classe prepara os dados via prepare_appointments_section_data()
 * 2. A classe chama dps_get_template() passando os dados
 * 3. Este template renderiza o HTML usando as variáveis recebidas
 *
 * @package DesiPetShower
 * @since 1.0.2
 *
 * Variáveis disponíveis (quando implementado):
 * @var array       $clients      Lista de clientes disponíveis
 * @var array       $pets         Lista de pets disponíveis
 * @var int         $pet_pages    Total de páginas de pets
 * @var int         $edit_id      ID do agendamento sendo editado (0 se novo)
 * @var WP_Post|null $editing     Post do agendamento em edição
 * @var array       $meta         Metadados do agendamento
 * @var int         $pref_client  Cliente pré-selecionado via URL
 * @var int         $pref_pet     Pet pré-selecionado via URL
 * @var string      $base_url     URL base da página
 * @var string      $current_url  URL completa atual
 * @var bool        $visitor_only Se true, exibe apenas listagem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NOTA DE IMPLEMENTAÇÃO FUTURA:
 *
 * Este template está preparado para receber a renderização do formulário
 * de agendamentos. A estrutura esperada é:
 *
 * <div class="dps-section" id="dps-section-agendas">
 *     <h2>Agendamento de Serviços</h2>
 *
 *     <!-- Alertas de pendências financeiras -->
 *
 *     <!-- Formulário de agendamento (se não visitor_only) -->
 *     <form method="post" class="dps-form">
 *         <!-- Fieldset: Tipo de Agendamento -->
 *         <!-- Fieldset: Cliente e Pet(s) -->
 *         <!-- Fieldset: Data e Horário -->
 *         <!-- Fieldset: Serviços e Extras -->
 *         <!-- Fieldset: Pagamento (apenas para passados) -->
 *         <!-- Fieldset: Observações -->
 *         <!-- Resumo e botões -->
 *     </form>
 *
 *     <!-- Listagem de agendamentos (via dps_get_template('appointments-list.php')) -->
 * </div>
 *
 * Por enquanto, a renderização continua em render_appointments_section()
 * até que a refatoração completa seja finalizada.
 */
