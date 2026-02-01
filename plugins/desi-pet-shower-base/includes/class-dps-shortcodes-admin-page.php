<?php
/**
 * Página administrativa para listagem de shortcodes DPS.
 *
 * Exibe todos os shortcodes disponíveis no núcleo e nos add-ons,
 * com cópia rápida, descrição curta e detalhamento expandido.
 *
 * @package DPS_Base_Plugin
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por registrar o submenu "Shortcods"
 * e renderizar o catálogo de shortcodes.
 */
class DPS_Shortcodes_Admin_Page {

    /**
     * Construtor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_page' ], 22 );
    }

    /**
     * Registra o submenu no menu principal do DPS.
     */
    public function register_page() {
        add_submenu_page(
            'desi-pet-shower',
            __( 'Shortcods', 'desi-pet-shower' ),
            __( 'Shortcods', 'desi-pet-shower' ),
            'manage_options',
            'dps-shortcodes',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Renderiza a página administrativa.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'desi-pet-shower' ) );
        }

        $shortcodes = $this->get_shortcodes_catalog();
        $grouped    = [];

        foreach ( $shortcodes as $shortcode ) {
            $group = $shortcode['group'];

            if ( ! isset( $grouped[ $group ] ) ) {
                $grouped[ $group ] = [];
            }

            $grouped[ $group ][] = $shortcode;
        }
        ?>
        <div class="wrap dps-admin-page dps-shortcodes-page">
            <h1><?php esc_html_e( 'Shortcods disponíveis', 'desi-pet-shower' ); ?></h1>

            <p class="dps-shortcodes-intro">
                <?php esc_html_e( 'Use os shortcods para levar o painel DPS para páginas específicas, portais ou landing pages. Copie o código, veja o que ele faz e como configurá-lo rapidamente.', 'desi-pet-shower' ); ?>
            </p>

            <?php $this->render_suggestions_panel(); ?>

            <?php foreach ( $grouped as $group_label => $items ) : ?>
                <div class="dps-shortcodes-group">
                    <div class="dps-shortcodes-group__header">
                        <h2><?php echo esc_html( $group_label ); ?></h2>
                        <span class="dps-badge dps-badge--neutral">
                            <?php
                            printf(
                                /* translators: %d: quantidade de shortcodes no grupo */
                                esc_html__( '%d itens', 'desi-pet-shower' ),
                                count( $items )
                            );
                            ?>
                        </span>
                    </div>

                    <div class="dps-shortcodes-grid">
                        <?php foreach ( $items as $shortcode ) : ?>
                            <div class="dps-shortcode-card <?php echo $shortcode['deprecated'] ? 'dps-shortcode-card--deprecated' : ''; ?>">
                                <div class="dps-shortcode-card__header">
                                    <div>
                                        <div class="dps-shortcode-tag">
                                            <?php echo esc_html( '[' . $shortcode['tag'] . ']' ); ?>
                                        </div>
                                        <div class="dps-shortcode-title">
                                            <?php echo esc_html( $shortcode['title'] ); ?>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="button button-secondary dps-copy-button"
                                        data-dps-copy="<?php echo esc_attr( '[' . $shortcode['tag'] . ']' ); ?>"
                                        data-dps-copy-success="<?php esc_attr_e( 'Copiado!', 'desi-pet-shower' ); ?>"
                                    >
                                        📋 <?php esc_html_e( 'Copiar', 'desi-pet-shower' ); ?>
                                    </button>
                                </div>

                                <div class="dps-shortcode-card__meta">
                                    <span class="dps-badge dps-badge--info">
                                        <?php echo esc_html( $shortcode['group'] ); ?>
                                    </span>

                                    <?php if ( $shortcode['deprecated'] ) : ?>
                                        <span class="dps-badge dps-badge--warning">
                                            <?php esc_html_e( 'Deprecated', 'desi-pet-shower' ); ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="dps-badge <?php echo $shortcode['is_active'] ? 'dps-badge--success' : 'dps-badge--muted'; ?>">
                                        <?php
                                        echo $shortcode['is_active']
                                            ? esc_html__( 'Ativo', 'desi-pet-shower' )
                                            : esc_html__( 'Add-on inativo', 'desi-pet-shower' );
                                        ?>
                                    </span>
                                </div>

                                <p class="dps-shortcode-card__summary">
                                    <?php echo esc_html( $shortcode['summary'] ); ?>
                                </p>

                                <details class="dps-shortcode-details">
                                    <summary><?php esc_html_e( 'Detalhes e configurações', 'desi-pet-shower' ); ?></summary>
                                    <div class="dps-shortcode-details__content">
                                        <p class="dps-shortcode-details__text">
                                            <?php echo esc_html( $shortcode['details'] ); ?>
                                        </p>

                                        <?php if ( ! empty( $shortcode['attributes'] ) ) : ?>
                                            <ul class="dps-shortcode-attributes">
                                                <?php foreach ( $shortcode['attributes'] as $attribute ) : ?>
                                                    <li>
                                                        <strong><?php echo esc_html( $attribute['label'] ); ?>:</strong>
                                                        <?php echo esc_html( $attribute['description'] ); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $shortcode['recommendations'] ) ) : ?>
                                            <div class="dps-shortcode-recommendations">
                                                <strong><?php esc_html_e( 'Sugestão de uso:', 'desi-pet-shower' ); ?></strong>
                                                <p><?php echo esc_html( $shortcode['recommendations'] ); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renderiza painel com sugestões rápidas de melhoria e organização.
     */
    private function render_suggestions_panel() {
        ?>
        <div class="dps-suggestions-panel">
            <div class="dps-suggestions-panel__icon" aria-hidden="true">💡</div>
            <div>
                <h3><?php esc_html_e( 'Sugestões rápidas', 'desi-pet-shower' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'Agrupe os shortcods por domínio (Agenda, Financeiro, Portal, etc.) para localizar rapidamente o que precisa.', 'desi-pet-shower' ); ?></li>
                    <li><?php esc_html_e( 'Use páginas dedicadas e restritas para shortcods administrativos que exigem login, evitando exposição pública.', 'desi-pet-shower' ); ?></li>
                    <li><?php esc_html_e( 'Para embeds públicos (catálogo de serviços ou chat), combine com temas de página limpa para preservar o layout minimalista.', 'desi-pet-shower' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Lista completa de shortcodes do DPS.
     *
     * @return array[]
     */
    private function get_shortcodes_catalog() {
        $catalog = [];

        $catalog[] = [
            'tag'             => 'dps_base',
            'title'           => __( 'Painel operacional completo', 'desi-pet-shower' ),
            'group'           => __( 'Núcleo', 'desi-pet-shower' ),
            'summary'         => __( 'Exibe o painel principal com agendamentos, clientes, pets e abas registradas pelos add-ons.', 'desi-pet-shower' ),
            'details'         => __( 'Recomendado para áreas internas. Apenas usuários logados com capacidades do DPS podem visualizar o painel e as ações.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'Acesso', 'desi-pet-shower' ),
                    'description' => __( 'Necessário estar logado com capacidades dps_manage_* ou manage_options.', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'Integrações', 'desi-pet-shower' ),
                    'description' => __( 'Add-ons podem adicionar abas via hooks dps_base_nav_tabs_* e dps_base_sections_*.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Use em página com template de largura total para aproveitar o layout das abas.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => true,
        ];

        $catalog[] = [
            'tag'             => 'dps_configuracoes',
            'title'           => __( 'Configurações (deprecated)', 'desi-pet-shower' ),
            'group'           => __( 'Núcleo', 'desi-pet-shower' ),
            'summary'         => __( 'Shortcode legado para configurações, mantido apenas para retrocompatibilidade.', 'desi-pet-shower' ),
            'details'         => __( 'Exibe um aviso direcionando para o painel admin do WordPress. Evite usar em novas páginas.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Substitua pelo acesso direto ao menu “desi.pet by PRObst” no admin.', 'desi-pet-shower' ),
            'deprecated'      => true,
            'is_active'       => true,
        ];

        $catalog[] = [
            'tag'             => 'dps_agenda_page',
            'title'           => __( 'Agenda completa', 'desi-pet-shower' ),
            'group'           => __( 'Agenda', 'desi-pet-shower' ),
            'summary'         => __( 'Central de agendamentos com filtros por data, visão diária ou semanal e ações em lote.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Agenda ativo. Destinado a administradores para acompanhar e operar a agenda.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'Parâmetros de URL', 'desi-pet-shower' ),
                    'description' => __( 'dps_date (YYYY-MM-DD) para data inicial; view=day|week|calendar; show_all=1 para listar tudo.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Ideal para uma página interna usada pela equipe; combine com menu privado ou área restrita.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Agenda_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_agenda_dashboard',
            'title'           => __( 'Dashboard de operações', 'desi-pet-shower' ),
            'group'           => __( 'Agenda', 'desi-pet-shower' ),
            'summary'         => __( 'KPIs diários, próximos atendimentos e atalhos rápidos da operação.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Agenda ativo. Visível apenas para administradores autenticados.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'Parâmetros de URL', 'desi-pet-shower' ),
                    'description' => __( 'dashboard_date (YYYY-MM-DD) para navegar entre dias.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Útil como homepage interna da equipe de atendimento.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Agenda_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_charges_notes',
            'title'           => __( 'Notas de cobrança (deprecated)', 'desi-pet-shower' ),
            'group'           => __( 'Agenda', 'desi-pet-shower' ),
            'summary'         => __( 'Alias legado da agenda para notas de cobrança.', 'desi-pet-shower' ),
            'details'         => __( 'Mantido apenas para compatibilidade antiga. Use as telas do Financeiro ou Agenda.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Evite em novas páginas; substitua por recursos do Financeiro.', 'desi-pet-shower' ),
            'deprecated'      => true,
            'is_active'       => class_exists( 'DPS_Agenda_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_services_catalog',
            'title'           => __( 'Catálogo de serviços', 'desi-pet-shower' ),
            'group'           => __( 'Serviços', 'desi-pet-shower' ),
            'summary'         => __( 'Exibe a lista pública de serviços, pacotes e extras com preços opcionais.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on de Serviços ativo. Pode ser usado em páginas públicas para divulgação.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'show_prices', 'desi-pet-shower' ),
                    'description' => __( 'yes (padrão) ou no para ocultar valores.', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'type / category', 'desi-pet-shower' ),
                    'description' => __( 'Filtra por tipo (padrao, extra, package) ou categoria interna.', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'layout', 'desi-pet-shower' ),
                    'description' => __( 'list (padrão) ou grid.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Combine com landing pages de captação e links de WhatsApp para conversão rápida.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Services_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_fin_docs',
            'title'           => __( 'Documentos financeiros', 'desi-pet-shower' ),
            'group'           => __( 'Financeiro', 'desi-pet-shower' ),
            'summary'         => __( 'Lista documentos HTML exportados pelo módulo financeiro.', 'desi-pet-shower' ),
            'details'         => __( 'Respeita permissão manage_options, a menos que o filtro dps_finance_docs_allow_public permita acesso público.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'Permissões', 'desi-pet-shower' ),
                    'description' => __( 'Por padrão apenas administradores visualizam; pode ser aberto via filtro.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Use em página protegida por senha se optar por acesso público via filtro.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Finance_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_client_portal',
            'title'           => __( 'Portal do Cliente', 'desi-pet-shower' ),
            'group'           => __( 'Portal do Cliente', 'desi-pet-shower' ),
            'summary'         => __( 'Entrega acesso seguro ao portal do cliente, com autenticação por token.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Portal do Cliente ativo. Renderiza tela de login/token e, quando autenticado, todas as funcionalidades do portal.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Use em página pública simples; o próprio portal exige token ou login seguro.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Client_Portal' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_client_login',
            'title'           => __( 'Login do cliente (deprecated)', 'desi-pet-shower' ),
            'group'           => __( 'Portal do Cliente', 'desi-pet-shower' ),
            'summary'         => __( 'Fluxo antigo de login por usuário/senha para o portal.', 'desi-pet-shower' ),
            'details'         => __( 'Mantido para avisar clientes sobre o novo fluxo de acesso via token. Não deve ser usado em novas páginas.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Direcione clientes para o shortcode do portal principal ou envie link direto com token.', 'desi-pet-shower' ),
            'deprecated'      => true,
            'is_active'       => class_exists( 'DPS_Client_Portal' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_tosa_consent',
            'title'           => __( 'Consentimento de tosa com máquina', 'desi-pet-shower' ),
            'group'           => __( 'Portal do Cliente', 'desi-pet-shower' ),
            'summary'         => __( 'Formulário público para clientes assinarem o consentimento de tosa com máquina via link tokenizado.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Portal do Cliente ativo. Deve ser usado em página pública dedicada, acessada via token gerado pelo administrador.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Crie uma página pública simples (ex: /consentimento-tosa-maquina/) e envie o link com token.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Client_Portal' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_registration_form',
            'title'           => __( 'Formulário de cadastro', 'desi-pet-shower' ),
            'group'           => __( 'Onboarding', 'desi-pet-shower' ),
            'summary'         => __( 'Formulário completo para cadastro de clientes e pets.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Registration ativo. Inclui validação, confirmação por email e criação opcional de agendamento inicial.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'Retorno de sucesso', 'desi-pet-shower' ),
                    'description' => __( 'O parâmetro registered=1 na URL exibe mensagem de confirmação após envio.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Ideal para páginas públicas com reCAPTCHA ou proteção anti-spam ativada no add-on.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Registration_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_dashboard',
            'title'           => __( 'Dashboard de groomers', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Painel com KPIs e links rápidos para profissionais.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo. Acesso restrito a usuários autorizados.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Use em área interna destinada à gestão da equipe de banho e tosa.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_agenda',
            'title'           => __( 'Agenda do groomer', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Agenda dedicada para profissionais com visão filtrada.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo e autenticação do profissional.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Combine com links de token temporário para acesso rápido dos profissionais.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_review',
            'title'           => __( 'Avaliação de atendimento', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Formulário para o cliente avaliar um atendimento específico.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo. Pode ser enviado junto ao link pós-atendimento.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Envie o link por WhatsApp após o serviço para aumentar a taxa de resposta.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_reviews',
            'title'           => __( 'Lista de avaliações', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Exibe avaliações recentes dos atendimentos.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo. Pode ser usado em páginas internas ou widgets privados.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Boa opção para área interna de qualidade ou para destacar provas sociais em uma landing.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_portal',
            'title'           => __( 'Portal do profissional', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Portal completo para o groomer acompanhar agenda e históricos.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo. Autenticação feita por login/token conforme configuração do add-on.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Use em página dedicada para cada profissional, protegida por login.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_groomer_login',
            'title'           => __( 'Login do profissional', 'desi-pet-shower' ),
            'group'           => __( 'Groomers', 'desi-pet-shower' ),
            'summary'         => __( 'Tela de autenticação para groomers acessarem seus portais.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on Groomers ativo. Útil quando não se usa links mágicos.', 'desi-pet-shower' ),
            'attributes'      => [],
            'recommendations' => __( 'Combine com expiração curta de tokens para maior segurança.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_Groomers_Addon' ),
        ];

        $catalog[] = [
            'tag'             => 'dps_ai_public_chat',
            'title'           => __( 'Chat público com IA', 'desi-pet-shower' ),
            'group'           => __( 'Assistente IA', 'desi-pet-shower' ),
            'summary'         => __( 'Widget público de chat com IA para dúvidas de clientes.', 'desi-pet-shower' ),
            'details'         => __( 'Requer add-on IA ativo e chave configurada. Pode ser usado como botão flutuante ou embed inline.', 'desi-pet-shower' ),
            'attributes'      => [
                [
                    'label'       => __( 'mode', 'desi-pet-shower' ),
                    'description' => __( 'inline (padrão) ou floating.', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'theme', 'desi-pet-shower' ),
                    'description' => __( 'light (padrão) ou dark.', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'primary_color', 'desi-pet-shower' ),
                    'description' => __( 'Define cor principal (hex).', 'desi-pet-shower' ),
                ],
                [
                    'label'       => __( 'show_faqs', 'desi-pet-shower' ),
                    'description' => __( 'true (padrão) para exibir sugestões, false para ocultar.', 'desi-pet-shower' ),
                ],
            ],
            'recommendations' => __( 'Perfeito para landing pages; utilize o modo floating em cantos da tela para conversão contínua.', 'desi-pet-shower' ),
            'deprecated'      => false,
            'is_active'       => class_exists( 'DPS_AI_Public_Chat' ),
        ];

        return $catalog;
    }
}

if ( is_admin() ) {
    new DPS_Shortcodes_Admin_Page();
}
