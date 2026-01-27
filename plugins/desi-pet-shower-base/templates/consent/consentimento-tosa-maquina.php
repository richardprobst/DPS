<?php
/**
 * Template: Consentimento para tosa na máquina.
 *
 * @package Desi_Pet_Shower
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="dps-consent-page">
    <header class="dps-consent-header">
        <h1><?php echo esc_html__( 'Consentimento para Tosa na Máquina', 'desi-pet-shower' ); ?></h1>
        <p><?php echo esc_html__( 'Este documento registra a autorização do responsável para realização de tosa na máquina, informando condições, riscos e cuidados necessários antes, durante e após o serviço.', 'desi-pet-shower' ); ?></p>
        <div class="dps-consent-meta">
            <?php
            /* translators: %s: data de emissão */
            echo esc_html( sprintf( __( 'Data de emissão: %s', 'desi-pet-shower' ), $generated_at ) );
            ?>
        </div>
        <?php if ( $company_name ) : ?>
            <div class="dps-consent-meta">
                <?php
                /* translators: %s: nome da empresa */
                echo esc_html( sprintf( __( 'Empresa: %s', 'desi-pet-shower' ), $company_name ) );
                ?>
            </div>
        <?php endif; ?>
        <?php if ( $support_email || $support_phone ) : ?>
            <div class="dps-consent-meta">
                <?php if ( $support_email ) : ?>
                    <?php
                    /* translators: %s: e-mail de suporte */
                    echo esc_html( sprintf( __( 'Contato: %s', 'desi-pet-shower' ), $support_email ) );
                    ?>
                <?php endif; ?>
                <?php if ( $support_phone ) : ?>
                    <?php
                    /* translators: %s: telefone de suporte */
                    echo esc_html( sprintf( __( 'Telefone: %s', 'desi-pet-shower' ), $support_phone ) );
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="dps-consent-actions">
        <button type="button" class="dps-consent-print" onclick="window.print()">
            <?php echo esc_html__( 'Imprimir / Salvar PDF', 'desi-pet-shower' ); ?>
        </button>
    </div>

    <form class="dps-consent-form" action="#" method="post">
        <fieldset class="dps-consent-fieldset">
            <legend><?php echo esc_html__( 'Dados do responsável', 'desi-pet-shower' ); ?></legend>
            <div class="dps-consent-grid">
                <div class="dps-consent-field">
                    <label for="dps-consent-responsavel-nome"><?php echo esc_html__( 'Nome completo', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-responsavel-nome" name="responsavel_nome" placeholder="<?php echo esc_attr__( 'Nome do responsável', 'desi-pet-shower' ); ?>" required>
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-responsavel-cpf"><?php echo esc_html__( 'CPF', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-responsavel-cpf" name="responsavel_cpf" placeholder="000.000.000-00">
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-responsavel-telefone"><?php echo esc_html__( 'Telefone/WhatsApp', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-responsavel-telefone" name="responsavel_telefone" placeholder="(00) 00000-0000">
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-responsavel-email"><?php echo esc_html__( 'E-mail', 'desi-pet-shower' ); ?></label>
                    <input type="email" id="dps-consent-responsavel-email" name="responsavel_email" placeholder="email@exemplo.com">
                </div>
            </div>
        </fieldset>

        <fieldset class="dps-consent-fieldset">
            <legend><?php echo esc_html__( 'Dados do pet', 'desi-pet-shower' ); ?></legend>
            <div class="dps-consent-grid">
                <div class="dps-consent-field">
                    <label for="dps-consent-pet-nome"><?php echo esc_html__( 'Nome do pet', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-pet-nome" name="pet_nome" placeholder="<?php echo esc_attr__( 'Nome do pet', 'desi-pet-shower' ); ?>" required>
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-pet-raca"><?php echo esc_html__( 'Raça', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-pet-raca" name="pet_raca" placeholder="<?php echo esc_attr__( 'Raça do pet', 'desi-pet-shower' ); ?>">
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-pet-porte"><?php echo esc_html__( 'Porte', 'desi-pet-shower' ); ?></label>
                    <select id="dps-consent-pet-porte" name="pet_porte">
                        <option value=""><?php echo esc_html__( 'Selecione', 'desi-pet-shower' ); ?></option>
                        <option value="pequeno"><?php echo esc_html__( 'Pequeno', 'desi-pet-shower' ); ?></option>
                        <option value="medio"><?php echo esc_html__( 'Médio', 'desi-pet-shower' ); ?></option>
                        <option value="grande"><?php echo esc_html__( 'Grande', 'desi-pet-shower' ); ?></option>
                    </select>
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-pet-idade"><?php echo esc_html__( 'Idade aproximada', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-pet-idade" name="pet_idade" placeholder="<?php echo esc_attr__( 'Ex: 4 anos', 'desi-pet-shower' ); ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="dps-consent-fieldset">
            <legend><?php echo esc_html__( 'Detalhes do serviço', 'desi-pet-shower' ); ?></legend>
            <div class="dps-consent-grid">
                <div class="dps-consent-field">
                    <label for="dps-consent-servico"><?php echo esc_html__( 'Tipo de tosa', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-servico" name="servico" placeholder="<?php echo esc_attr__( 'Ex: tosa na máquina (padrão)', 'desi-pet-shower' ); ?>" required>
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-data"><?php echo esc_html__( 'Data do serviço', 'desi-pet-shower' ); ?></label>
                    <input type="date" id="dps-consent-data" name="data_servico">
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-restricoes"><?php echo esc_html__( 'Condições de saúde relevantes', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-restricoes" name="condicoes" placeholder="<?php echo esc_attr__( 'Ex: pele sensível, alergias, cirurgias recentes', 'desi-pet-shower' ); ?>">
                </div>
                <div class="dps-consent-field">
                    <label for="dps-consent-comportamento"><?php echo esc_html__( 'Comportamento do pet', 'desi-pet-shower' ); ?></label>
                    <input type="text" id="dps-consent-comportamento" name="comportamento" placeholder="<?php echo esc_attr__( 'Ex: tranquilo, ansioso, reage a barulho', 'desi-pet-shower' ); ?>">
                </div>
            </div>
            <div class="dps-consent-field" style="margin-top: 16px;">
                <label for="dps-consent-observacoes"><?php echo esc_html__( 'Observações adicionais', 'desi-pet-shower' ); ?></label>
                <textarea id="dps-consent-observacoes" name="observacoes" placeholder="<?php echo esc_attr__( 'Use este espaço para informar cuidados especiais ou instruções específicas.', 'desi-pet-shower' ); ?>"></textarea>
            </div>
        </fieldset>

        <fieldset class="dps-consent-fieldset">
            <legend><?php echo esc_html__( 'Termos de consentimento', 'desi-pet-shower' ); ?></legend>
            <div class="dps-consent-highlight">
                <?php echo esc_html__( 'A tosa na máquina é indicada quando há necessidade de remoção rápida de pelos, nós ou manutenção de comprimento uniforme. Informações como sensibilidade da pele, histórico clínico e comportamento devem ser comunicadas previamente para garantir segurança e conforto do pet.', 'desi-pet-shower' ); ?>
            </div>
            <div class="dps-consent-terms">
                <label>
                    <input type="checkbox" name="termo_autorizacao" required>
                    <span><?php echo esc_html__( 'Autorizo a realização da tosa na máquina e compreendo que o comprimento final seguirá o padrão técnico mais seguro para o meu pet.', 'desi-pet-shower' ); ?></span>
                </label>
                <label>
                    <input type="checkbox" name="termo_riscos" required>
                    <span><?php echo esc_html__( 'Estou ciente de que podem ocorrer sensibilidades na pele, pequenos cortes ou irritações, principalmente em pets com nós, pele sensível ou comportamento reativo.', 'desi-pet-shower' ); ?></span>
                </label>
                <label>
                    <input type="checkbox" name="termo_cuidados" required>
                    <span><?php echo esc_html__( 'Informei condições de saúde, uso de medicamentos e histórico de reações para garantir que os cuidados necessários sejam adotados.', 'desi-pet-shower' ); ?></span>
                </label>
                <label>
                    <input type="checkbox" name="termo_fotos">
                    <span><?php echo esc_html__( 'Autorizo o registro de fotos do serviço para uso interno e comunicação com o responsável.', 'desi-pet-shower' ); ?></span>
                </label>
            </div>
        </fieldset>

        <fieldset class="dps-consent-fieldset">
            <legend><?php echo esc_html__( 'Assinaturas', 'desi-pet-shower' ); ?></legend>
            <div class="dps-consent-signatures">
                <div>
                    <div class="dps-consent-signature-line"></div>
                    <div class="dps-consent-signature-label"><?php echo esc_html__( 'Assinatura do responsável', 'desi-pet-shower' ); ?></div>
                </div>
                <div>
                    <div class="dps-consent-signature-line"></div>
                    <div class="dps-consent-signature-label"><?php echo esc_html__( 'Assinatura do profissional responsável', 'desi-pet-shower' ); ?></div>
                </div>
            </div>
        </fieldset>
    </form>
</div>
