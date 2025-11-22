<?php
/**
 * Plugin Name:       Desi Pet Shower – Financeiro Add-on
 * Plugin URI:        https://probst.pro/desi-pet-shower
 * Description:       Add-on para o plugin base Desi Pet Shower que cria uma aba de controle financeiro. Permite registrar receitas e despesas, marcar pagamentos e listar todas as transações.
 * Version:           1.0.0
 * Author:            PRObst
 * Author URI:        https://probst.pro
 * Text Domain:       dps-finance-addon
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 */

// Impede acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do add-on
if ( ! defined( 'DPS_FINANCE_PLUGIN_FILE' ) ) {
    define( 'DPS_FINANCE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'DPS_FINANCE_PLUGIN_DIR' ) ) {
    define( 'DPS_FINANCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DPS_FINANCE_VERSION' ) ) {
    define( 'DPS_FINANCE_VERSION', '1.0.0' );
}

// Carrega dependências
require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-revenue-query.php';
require_once DPS_FINANCE_PLUGIN_DIR . 'includes/class-dps-finance-api.php';

// Funções auxiliares globais para conversão monetária
// DEPRECATED: Use DPS_Money_Helper do núcleo em vez dessas funções.

if ( ! function_exists( 'dps_parse_money_br' ) ) {
    /**
     * Converte uma string de valor em formato brasileiro para inteiro em centavos.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::parse_brazilian_format() instead.
     * @param string $str Valor monetário (ex.: "129,90" ou "129.90").
     * @return int Valor em centavos (ex.: 12990)
     */
    function dps_parse_money_br( $str ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::parse_brazilian_format()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::parse_brazilian_format( $str );
        }
        // Fallback se helper não disponível
        $raw = trim( (string) $str );
        if ( $raw === '' ) {
            return 0;
        }
        $normalized = preg_replace( '/[^0-9,.-]/', '', $raw );
        $normalized = str_replace( ' ', '', $normalized );
        if ( strpos( $normalized, ',' ) !== false ) {
            $normalized = str_replace( '.', '', $normalized );
            $normalized = str_replace( ',', '.', $normalized );
        }
        $value = floatval( $normalized );
        return (int) round( $value * 100 );
    }
}

if ( ! function_exists( 'dps_format_money_br' ) ) {
    /**
     * Formata um valor em centavos para string no padrão brasileiro.
     *
     * @deprecated 1.1.0 Use DPS_Money_Helper::format_to_brazilian() instead.
     * @param int $int Valor em centavos (ex.: 12990).
     * @return string Valor formatado (ex.: "129,90").
     */
    function dps_format_money_br( $int ) {
        _deprecated_function( __FUNCTION__, '1.1.0', 'DPS_Money_Helper::format_to_brazilian()' );
        if ( class_exists( 'DPS_Money_Helper' ) ) {
            return DPS_Money_Helper::format_to_brazilian( $int );
        }
        // Fallback se helper não disponível
        $float = (int) $int / 100;
        return number_format( $float, 2, ',', '.' );
    }
}

// Define a classe principal do add-on financeiro
if ( ! class_exists( 'DPS_Finance_Addon' ) ) {

class DPS_Finance_Addon {
    public function __construct() {
        // Registra abas e seções no plugin base
        add_action( 'dps_base_nav_tabs_after_history', [ $this, 'add_finance_tab' ], 10, 1 );
        add_action( 'dps_base_sections_after_history', [ $this, 'add_finance_section' ], 10, 1 );
        // Trata salvamento e exclusão de transações
        add_action( 'init', [ $this, 'maybe_handle_finance_actions' ] );
        // Cria tabela de parcelas de pagamentos (pagamentos parciais) se ainda não existir
        add_action( 'init', [ $this, 'maybe_create_parcelas_table' ] );
        // Garante que a tabela principal de transações exista. Sem esta tabela,
        // receitas e despesas não podem ser registradas e as abas Financeiro e
        // Estatísticas permanecerão vazias. A criação é feita em cada request
        // durante o init para evitar problemas em instalações que não executam
        // rotinas de ativação.
        add_action( 'init', [ $this, 'maybe_create_transacoes_table' ] );
        add_action( 'dps_finance_cleanup_for_appointment', [ $this, 'cleanup_transactions_for_appointment' ] );

        // Não cria mais uma página pública para documentos; apenas registra o shortcode
        add_shortcode( 'dps_fin_docs', [ $this, 'render_fin_docs_shortcode' ] );

        // Sincroniza automaticamente o status das transações quando o status do agendamento é atualizado ou criado
        // Utilize tanto updated_post_meta quanto added_post_meta para capturar atualizações e inserções de meta
        add_action( 'updated_post_meta', [ $this, 'sync_status_to_finance' ], 10, 4 );
        add_action( 'added_post_meta',  [ $this, 'sync_status_to_finance' ], 10, 4 );
    }

    /**
     * Executado na ativação do add‑on financeiro. Garante que exista uma página para listar os
     * documentos (notas e cobranças) gerados. A página recebe o shortcode [dps_fin_docs].
     */
    public function activate() {
        $title = __( 'Documentos Financeiros', 'dps-finance-addon' );
        $slug  = sanitize_title( $title );
        $page  = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '[dps_fin_docs]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( $page_id ) {
                update_option( 'dps_fin_docs_page_id', $page_id );
            }
        } else {
            update_option( 'dps_fin_docs_page_id', $page->ID );
        }
    }

    /**
     * Adiciona a aba Financeiro na navegação do plugin base.
     *
     * @param bool $visitor_only Se o modo visitante está ativo; nesse caso, não mostra a aba.
     */
    public function add_finance_tab( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo '<li><a href="#" class="dps-tab-link" data-tab="financeiro">' . esc_html__( 'Financeiro', 'dps-finance-addon' ) . '</a></li>';
    }

    /**
     * Adiciona a seção de controle financeiro ao plugin base.
     *
     * @param bool $visitor_only Se verdadeiro, visitante não vê a seção.
     */
    public function add_finance_section( $visitor_only ) {
        if ( $visitor_only ) {
            return;
        }
        echo $this->section_financeiro();
    }

    /**
     * Manipula ações de salvamento ou exclusão de transações.
     */
    public function maybe_handle_finance_actions() {
        // Sempre declara o objeto $wpdb e a tabela de transações no início, pois são usados
        // tanto no registro de pagamentos parciais quanto em outras ações. Sem isso,
        // variáveis como $table e $wpdb podem não estar definidas quando utilizadas.
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Registrar pagamento parcial
        if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_partial' && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
            $trans_id = isset( $_POST['trans_id'] ) ? intval( $_POST['trans_id'] ) : 0;
            $date     = sanitize_text_field( $_POST['partial_date'] ?? '' );
            if ( ! $date ) {
                $date = current_time( 'Y-m-d' );
            }
            // Converte valor informado em centavos para evitar imprecisão de ponto flutuante
            $raw_value   = sanitize_text_field( wp_unslash( $_POST['partial_value'] ?? '0' ) );
            $value_cents = dps_parse_money_br( $raw_value );
            $value       = $value_cents / 100;
            $method    = sanitize_text_field( $_POST['partial_method'] ?? '' );
            if ( $trans_id && $value > 0 ) {
                $parc_table = $wpdb->prefix . 'dps_parcelas';
                // Insere a parcela
                $wpdb->insert( $parc_table, [
                    'trans_id' => $trans_id,
                    'data'     => $date,
                    'valor'    => $value,
                    'metodo'   => $method,
                ], [ '%d','%s','%f','%s' ] );
                // Calcula a soma de parcelas pagas
                $paid_sum       = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM {$parc_table} WHERE trans_id = %d", $trans_id ) );
                $paid_sum_cents = $paid_sum ? (int) round( (float) $paid_sum * 100 ) : 0;
                // Valor total da transação
                $total_val       = $wpdb->get_var( $wpdb->prepare( "SELECT valor FROM {$table} WHERE id = %d", $trans_id ) );
                $total_val_cents = $total_val ? (int) round( (float) $total_val * 100 ) : 0;
                if ( $paid_sum_cents && $total_val_cents && $paid_sum_cents >= $total_val_cents ) {
                    // Marca a transação como paga quando quitada
                    $wpdb->update( $table, [ 'status' => 'pago' ], [ 'id' => $trans_id ], [ '%s' ], [ '%d' ] );
                } else {
                    // Mantém como em aberto até pagamento total
                    $wpdb->update( $table, [ 'status' => 'em_aberto' ], [ 'id' => $trans_id ], [ '%s' ], [ '%d' ] );
                }
            }
            // Redireciona de volta à aba Financeiro
            $base_url = get_permalink();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro' ], $base_url ) );
            exit;
        }

        // Geração de documento (nota ou cobrança) a partir da lista de transações.
        if ( isset( $_GET['dps_gen_doc'] ) && isset( $_GET['id'] ) ) {
            $trans_id = intval( $_GET['id'] );
            $doc_url  = $this->generate_document( $trans_id );
            if ( $doc_url ) {
                wp_redirect( $doc_url );
                exit;
            }
        }
        // Salvar nova transação
        if ( isset( $_POST['dps_finance_action'] ) && $_POST['dps_finance_action'] === 'save_trans' && check_admin_referer( 'dps_finance_action', 'dps_finance_nonce' ) ) {
            $date       = sanitize_text_field( $_POST['finance_date'] ?? '' );
            $value_raw  = sanitize_text_field( wp_unslash( $_POST['finance_value'] ?? '0' ) );
            $value_cent = dps_parse_money_br( $value_raw );
            $value      = $value_cent / 100;
            $category   = sanitize_text_field( $_POST['finance_category'] ?? '' );
            $type       = sanitize_text_field( $_POST['finance_type'] ?? 'receita' );
            $status     = sanitize_text_field( $_POST['finance_status'] ?? 'em_aberto' );
            $desc       = sanitize_text_field( $_POST['finance_desc'] ?? '' );
            $client_id  = intval( $_POST['finance_client_id'] ?? 0 );
            // Insere no banco
            $wpdb->insert( $table, [
                'cliente_id'    => $client_id ?: null,
                'agendamento_id'=> null,
                'plano_id'      => null,
                'data'          => $date ?: current_time( 'mysql' ),
                'valor'         => $value,
                'categoria'     => $category,
                'tipo'          => $type,
                'status'        => $status,
                'descricao'     => $desc,
            ] );
            // Redireciona para aba finance
            $base_url = get_permalink();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro' ], $base_url ) );
            exit;
        }
        // Excluir transação
        if ( isset( $_GET['dps_delete_trans'] ) && isset( $_GET['id'] ) ) {
            $trans_id = intval( $_GET['id'] );
            $wpdb->delete( $table, [ 'id' => $trans_id ] );
            $base_url = get_permalink();
            $redir = remove_query_arg( [ 'dps_delete_trans', 'id' ], $base_url );
            $redir = add_query_arg( [ 'tab' => 'financeiro' ], $redir );
            wp_redirect( $redir );
            exit;
        }
        // Atualizar status de transação
        if ( isset( $_POST['dps_update_trans_status'] ) && isset( $_POST['trans_id'] ) ) {
            $id     = intval( $_POST['trans_id'] );
            $status = sanitize_text_field( $_POST['trans_status'] );
            $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );

            // Se for marcado como recorrente e status foi alterado para pago, cria nova transação recorrente para 30 dias depois
            $recurring_flag = get_option( 'dps_fin_recurring_' . $id );
            if ( $recurring_flag && $status === 'pago' ) {
                // Recupera dados da transação
                $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
                if ( $trans ) {
                    // Calcula nova data somando 30 dias
                    $new_date = date( 'Y-m-d', strtotime( $trans->data . ' +30 days' ) );
                    $wpdb->insert( $table, [
                        'cliente_id'     => $trans->cliente_id,
                        'agendamento_id' => $trans->agendamento_id,
                        'plano_id'       => $trans->plano_id,
                        'data'           => $new_date,
                        'valor'          => $trans->valor,
                        'categoria'      => $trans->categoria,
                        'tipo'           => $trans->tipo,
                        'status'         => 'em_aberto',
                        'descricao'      => $trans->descricao,
                    ], [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
                    // Marca nova transação como recorrente também
                    $new_id = $wpdb->insert_id;
                    update_option( 'dps_fin_recurring_' . $new_id, true );
                }
            }
            // Se esta transação estiver vinculada a um agendamento, atualiza o status correspondente
            $appt_id = $wpdb->get_var( $wpdb->prepare( "SELECT agendamento_id FROM $table WHERE id = %d", $id ) );
            $appt_id = intval( $appt_id );
            if ( $appt_id ) {
                if ( $status === 'pago' ) {
                    $appt_status = 'finalizado_pago';
                } elseif ( $status === 'cancelado' ) {
                    $appt_status = 'cancelado';
                } else {
                    $appt_status = 'finalizado';
                }
                delete_post_meta( $appt_id, 'appointment_status' );
                add_post_meta( $appt_id, 'appointment_status', $appt_status, true );
            }
            $base_url = get_permalink();
            wp_redirect( add_query_arg( [ 'tab' => 'financeiro' ], $base_url ) );
            exit;
        }

        // Alternar recorrência removido: funcionalidade de recorrente foi descontinuada

        /**
         * Envio ou exclusão de documentos financeiros
         *
         * Para enviar um documento: usar dps_send_doc=1, file={nome do arquivo}
         * Opcional: to_email={email personalizado} e trans_id={id da transação}
         *
         * Para excluir um documento: usar dps_delete_doc=1, file={nome do arquivo}
         */
        // Excluir documento
        if ( isset( $_GET['dps_delete_doc'] ) && '1' === $_GET['dps_delete_doc'] && isset( $_GET['file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            if ( $file ) {
                $uploads = wp_upload_dir();
                $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
                $file_path = trailingslashit( $doc_dir ) . basename( $file );
                if ( file_exists( $file_path ) ) {
                    @unlink( $file_path );
                }
                // Também remove qualquer opção que referencie este arquivo (transações)
                $file_url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $file );
                // Remove dps_fin_doc_* que contenham esta URL
                $option_rows = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" );
                if ( $option_rows ) {
                    foreach ( $option_rows as $opt ) {
                        $val = get_option( $opt->option_name );
                        if ( $val === $file_url ) {
                            delete_option( $opt->option_name );
                        }
                    }
                }
                // Remove também a opção de email default associada a este transação, se existir
                if ( isset( $_GET['trans_id'] ) ) {
                    $tid = intval( $_GET['trans_id'] );
                    delete_option( 'dps_fin_doc_email_' . $tid );
                }
            }
            // Redireciona de volta à aba Financeiro
            $base_url = get_permalink();
            $redir = add_query_arg( [ 'tab' => 'financeiro' ], remove_query_arg( [ 'dps_delete_doc', 'file', 'to_email', 'trans_id' ], $base_url ) );
            wp_redirect( $redir );
            exit;
        }

        // Enviar documento por email
        if ( isset( $_GET['dps_send_doc'] ) && '1' === $_GET['dps_send_doc'] && isset( $_GET['file'] ) ) {
            $file = sanitize_file_name( wp_unslash( $_GET['file'] ) );
            $to_email = '';
            if ( isset( $_GET['to_email'] ) ) {
                $to_email = sanitize_email( wp_unslash( $_GET['to_email'] ) );
            }
            $trans_id = isset( $_GET['trans_id'] ) ? intval( $_GET['trans_id'] ) : 0;
            $this->send_finance_doc_email( $file, $trans_id, $to_email );
            // Redireciona de volta à aba Financeiro
            $base_url = get_permalink();
            $redir = add_query_arg( [ 'tab' => 'financeiro' ], remove_query_arg( [ 'dps_send_doc', 'file', 'to_email', 'trans_id' ], $base_url ) );
            wp_redirect( $redir );
            exit;
        }
    }

    /**
     * Remove transações associadas a um agendamento excluído.
     *
     * @param int $appointment_id ID do agendamento removido.
     */
    public function cleanup_transactions_for_appointment( $appointment_id ) {
        // Delega para a API financeira
        if ( class_exists( 'DPS_Finance_API' ) ) {
            DPS_Finance_API::delete_charges_by_appointment( $appointment_id );
        }
    }

    /**
     * Gera um documento simples (HTML) para uma transação financeira. O documento é uma "nota" se o
     * status estiver marcado como pago, ou uma "cobrança" se o status for em aberto. É salvo em
     * uploads/dps_docs e reutilizado em requisições futuras. Retorna a URL pública do arquivo.
     *
     * @param int $trans_id ID da transação
     * @return string|false URL do arquivo gerado ou false em caso de erro
     */
    private function generate_document( $trans_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        $trans = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $trans_id ) );
        if ( ! $trans ) {
            return false;
        }
        // Determina tipo de documento (nota = pago, cobranca = em aberto)
        $status = $trans->status;
        $type   = ( $status === 'pago' ) ? 'nota' : 'cobranca';
        // Verifica se já existe um documento salvo
        $opt_key = 'dps_fin_doc_' . $trans_id;
        $existing = get_option( $opt_key );
        if ( $existing ) {
            return $existing;
        }
        // Datas e valores formatados
        // Datas no formato dia-mês-ano
        $date     = $trans->data ? date_i18n( 'd-m-Y', strtotime( $trans->data ) ) : current_time( 'd-m-Y' );
        $date_key = $trans->data ? date( 'Y-m-d', strtotime( $trans->data ) ) : date( 'Y-m-d' );
        $valor_fmt = dps_format_money_br( (int) round( (float) $trans->valor * 100 ) );
        // Cliente e pet
        $client_name = '';
        $client_email = '';
        $pet_name    = '';
        $appt_id     = 0;
        $client_id_for_email = 0;
        if ( $trans->agendamento_id ) {
            $appt_id   = (int) $trans->agendamento_id;
            $client_id = get_post_meta( $appt_id, 'appointment_client_id', true );
            $pet_id    = get_post_meta( $appt_id, 'appointment_pet_id', true );
            if ( $client_id ) {
                $cpost = get_post( $client_id );
                if ( $cpost ) {
                    $client_name = $cpost->post_title;
                    $client_id_for_email = $client_id;
                }
            }
            if ( $pet_id ) {
                $ppost = get_post( $pet_id );
                if ( $ppost ) {
                    $pet_name = $ppost->post_title;
                }
            }
        } elseif ( $trans->plano_id ) {
            // Se for transação de assinatura, obtém pet e cliente pelo plano
            $client_id = get_post_meta( $trans->plano_id, 'subscription_client_id', true );
            $pet_id    = get_post_meta( $trans->plano_id, 'subscription_pet_id', true );
            if ( $client_id ) {
                $cpost = get_post( $client_id );
                if ( $cpost ) {
                    $client_name = $cpost->post_title;
                    $client_id_for_email = $client_id;
                }
            }
            if ( $pet_id ) {
                $ppost = get_post( $pet_id );
                if ( $ppost ) {
                    $pet_name = $ppost->post_title;
                }
            }
        } elseif ( $trans->cliente_id ) {
            $cpost = get_post( $trans->cliente_id );
            if ( $cpost ) {
                $client_name = $cpost->post_title;
                $client_id_for_email = $trans->cliente_id;
            }
        }
        // Obtém email do cliente (se houver)
        if ( $client_id_for_email ) {
            $client_email = get_post_meta( $client_id_for_email, 'client_email', true );
        }
        // Lista de serviços
        $service_lines = [];
        if ( $appt_id ) {
            $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
            $service_prices = get_post_meta( $appt_id, 'appointment_service_prices', true );
            if ( ! is_array( $service_prices ) ) {
                $service_prices = [];
            }
            if ( is_array( $service_ids ) ) {
                foreach ( $service_ids as $sid ) {
                    $srv  = get_post( $sid );
                    if ( $srv ) {
                        $price = 0;
                        if ( isset( $service_prices[ $sid ] ) ) {
                            $price = (float) $service_prices[ $sid ];
                        } else {
                            $price = (float) get_post_meta( $sid, 'service_price', true );
                        }
                        $service_lines[] = esc_html( $srv->post_title ) . ' - R$ ' . dps_format_money_br( (int) round( $price * 100 ) );
                    }
                }
            }
        }
        // Preparar HTML do documento
        $title_doc = ( $type === 'nota' ) ? __( 'Nota de Serviços', 'dps-finance-addon' ) : __( 'Cobrança de Serviços', 'dps-finance-addon' );
        $services_html = '';
        if ( ! empty( $service_lines ) ) {
            $services_html .= '<ul style="margin:0; padding-left:20px;">';
            foreach ( $service_lines as $line ) {
                $services_html .= '<li>' . $line . '</li>';
            }
            $services_html .= '</ul>';
        }
        // Dados da loja conforme instruções
        $store_name    = 'Banho e Tosa Desi Pet Shower';
        $store_address = 'Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP';
        $store_phone   = '15 9 9160-6299';
        $store_email   = 'contato@desi.pet';
        $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $title_doc ) . '</title></head><body style="font-family:Arial, sans-serif; padding:20px;">';
        // Logo: tenta obter logo personalizado do site (personalização do tema). Se não houver, nada é exibido.
        $logo_id  = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $logo_url ) {
                $html .= '<p style="text-align:center;"><img src="' . esc_url( $logo_url ) . '" alt="Logo" style="max-width:200px;height:auto;"></p>';
            }
        }
        $html .= '<h2 style="text-align:center;">' . esc_html( $store_name ) . '</h2>';
        $html .= '<p style="text-align:center;">' . esc_html( $store_address ) . '<br>' . esc_html( $store_phone ) . ' - ' . esc_html( $store_email ) . '</p>';
        $html .= '<hr style="margin:20px 0;">';
        $html .= '<h3>' . esc_html( $title_doc ) . '</h3>';
        $html .= '<p><strong>' . __( 'Data:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $date ) . '</p>';
        if ( $client_name ) {
            $html .= '<p><strong>' . __( 'Cliente:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $client_name ) . '</p>';
        }
        if ( $pet_name ) {
            $html .= '<p><strong>' . __( 'Pet:', 'dps-finance-addon' ) . '</strong> ' . esc_html( $pet_name ) . '</p>';
        }
        if ( $services_html ) {
            $html .= '<p><strong>' . __( 'Serviços:', 'dps-finance-addon' ) . '</strong> ' . $services_html . '</p>';
        }
        $html .= '<p><strong>' . __( 'Valor total:', 'dps-finance-addon' ) . '</strong> R$ ' . esc_html( $valor_fmt ) . '</p>';
        if ( $type === 'cobranca' ) {
            $html .= '<p>' . __( 'Status:', 'dps-finance-addon' ) . ' ' . __( 'Em aberto', 'dps-finance-addon' ) . '</p>';
            $html .= '<p>' . __( 'Por favor, efetue o pagamento até a data do próximo atendimento.', 'dps-finance-addon' ) . '</p>';
        } else {
            $html .= '<p>' . __( 'Status:', 'dps-finance-addon' ) . ' ' . __( 'Pago', 'dps-finance-addon' ) . '</p>';
            $html .= '<p>' . __( 'Obrigado pela sua preferência!', 'dps-finance-addon' ) . '</p>';
        }
        $html .= '</body></html>';
        // Salvar arquivo usando padrão NomeCliente_NomePet_Data
        $upload_dir = wp_upload_dir();
        $doc_dir    = trailingslashit( $upload_dir['basedir'] ) . 'dps_docs';
        if ( ! file_exists( $doc_dir ) ) {
            wp_mkdir_p( $doc_dir );
        }
        // Cria slug do cliente e do pet
        $client_slug = $client_name ? str_replace( '-', '_', sanitize_title( $client_name ) ) : 'cliente';
        $pet_slug    = $pet_name ? str_replace( '-', '_', sanitize_title( $pet_name ) ) : 'pet';
        // Prefixo capitalizado
        $prefix = ( $type === 'nota' ) ? 'Nota' : 'Cobranca';
        $filename  = $prefix . '_' . $client_slug . '_' . $pet_slug . '_' . $date_key . '.html';
        $file_path = trailingslashit( $doc_dir ) . $filename;
        file_put_contents( $file_path, $html );
        $doc_url = trailingslashit( $upload_dir['baseurl'] ) . 'dps_docs/' . $filename;
        // Armazena URL para reutilização futura
        update_option( $opt_key, $doc_url );
        // Armazena email padrão para envio posterior
        if ( $client_email && is_email( $client_email ) ) {
            update_option( 'dps_fin_doc_email_' . $trans_id, sanitize_email( $client_email ) );
        }
        return $doc_url;
    }

    /**
     * Envia um documento financeiro (nota/cobrança/histórico) por email.
     *
     * @param string $filename Nome do arquivo no diretório dps_docs
     * @param int    $trans_id ID da transação (opcional, usado para obter email padrão)
     * @param string $custom_email Email alternativo fornecido pelo usuário
     */
    private function send_finance_doc_email( $filename, $trans_id = 0, $custom_email = '' ) {
        if ( ! $filename ) {
            return;
        }
        $uploads = wp_upload_dir();
        $doc_dir = trailingslashit( $uploads['basedir'] ) . 'dps_docs';
        $file_path = trailingslashit( $doc_dir ) . basename( $filename );
        if ( ! file_exists( $file_path ) ) {
            return;
        }
        // Determina email padrão se trans_id for fornecido
        $default_email = '';
        if ( $trans_id ) {
            $opt_email = get_option( 'dps_fin_doc_email_' . $trans_id );
            if ( $opt_email && is_email( $opt_email ) ) {
                $default_email = $opt_email;
            }
        }
        $to = '';
        if ( $custom_email && is_email( $custom_email ) ) {
            $to = $custom_email;
        } elseif ( $default_email ) {
            $to = $default_email;
        } else {
            // fallback para email de administrador
            $to = get_option( 'admin_email' );
        }
        if ( ! $to ) {
            return;
        }
        // Lê o conteúdo do arquivo para o corpo do email
        $content = file_get_contents( $file_path );
        // Determina título amigável
        $subject = 'Documento Financeiro - ' . get_bloginfo( 'name' );
        // Monta mensagem com saudação e corpo
        $message = '<p>Olá,</p>';
        $message .= '<p>Segue em anexo o documento solicitado:</p>';
        if ( $content ) {
            $message .= '<div style="border:1px solid #ddd;padding:10px;margin-bottom:20px;">' . $content . '</div>';
        } else {
            $url = trailingslashit( $uploads['baseurl'] ) . 'dps_docs/' . basename( $filename );
            $message .= '<p><a href="' . esc_url( $url ) . '">Clique aqui para visualizar o documento</a></p>';
        }
        // Rodapé com dados da loja
        $message .= '<p>Atenciosamente,<br>Banho e Tosa Desi Pet Shower<br>Rua Agua Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP<br>Whatsapp: 15 9 9160-6299<br>Email: contato@desi.pet</p>';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $attachments = [ $file_path ];
        @wp_mail( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Lista todos os documentos gerados (arquivos .html) no diretório de uploads/dps_docs. A lista
     * é apresentada como uma lista simples de links. Este método é usado pelo shortcode [dps_fin_docs].
     *
     * @return string HTML renderizado
     */
    public function render_fin_docs_shortcode() {
        $upload_dir = wp_upload_dir();
        $doc_dir    = trailingslashit( $upload_dir['basedir'] ) . 'dps_docs';
        $doc_urlbase= trailingslashit( $upload_dir['baseurl'] ) . 'dps_docs/';
        global $wpdb;
        $doc_options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dps_fin_doc_%'" );
        $doc_map     = [];
        if ( $doc_options ) {
            foreach ( $doc_options as $opt ) {
                if ( preg_match( '/^dps_fin_doc_(\d+)$/', $opt->option_name, $m ) ) {
                    $doc_map[ $opt->option_value ] = intval( $m[1] );
                }
            }
        }
        ob_start();
        echo '<div class="dps-fin-docs">';
        echo '<h3>' . esc_html__( 'Documentos Financeiros', 'dps-finance-addon' ) . '</h3>';
        if ( ! file_exists( $doc_dir ) ) {
            echo '<p>' . esc_html__( 'Nenhum documento encontrado.', 'dps-finance-addon' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }
        $files = scandir( $doc_dir );
        $docs  = [];
        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            if ( substr( $file, -5 ) === '.html' ) {
                $docs[] = $file;
            }
        }
        if ( empty( $docs ) ) {
            echo '<p>' . esc_html__( 'Nenhum documento encontrado.', 'dps-finance-addon' ) . '</p>';
        } else {
            // Organiza documentos por tipo: Cobranca, Nota, Historico
            $categorized = [ 'cobranca' => [], 'nota' => [], 'historico' => [] ];
            foreach ( $docs as $doc ) {
                $lower = strtolower( $doc );
                if ( strpos( $lower, 'cobranca_' ) === 0 ) {
                    $categorized['cobranca'][] = $doc;
                } elseif ( strpos( $lower, 'nota_' ) === 0 ) {
                    $categorized['nota'][] = $doc;
                } elseif ( strpos( $lower, 'historico_' ) === 0 ) {
                    $categorized['historico'][] = $doc;
                } else {
                    $categorized['historico'][] = $doc; // fallback
                }
            }
            foreach ( [ 'cobranca' => __( 'Cobranças', 'dps-finance-addon' ), 'nota' => __( 'Notas', 'dps-finance-addon' ), 'historico' => __( 'Históricos', 'dps-finance-addon' ) ] as $key => $title ) {
                if ( empty( $categorized[ $key ] ) ) {
                    continue;
                }
                echo '<h4>' . esc_html( $title ) . '</h4>';
                echo '<ul class="dps-fin-docs-list">';
                foreach ( $categorized[ $key ] as $doc ) {
                    $url = $doc_urlbase . $doc;
                    // Tenta encontrar trans_id correspondente ao doc
                    $trans_id = 0;
                    if ( isset( $doc_map[ $url ] ) ) {
                        $trans_id = $doc_map[ $url ];
                    }
                    // Define URL base atual removendo parâmetros de ação
                    $current_url = home_url( add_query_arg( null, null ) );
                    $base_clean  = remove_query_arg( [ 'dps_send_doc', 'to_email', 'trans_id', 'dps_delete_doc', 'file' ], $current_url );
                    // Link para envio por email
                    $send_link = add_query_arg( [ 'dps_send_doc' => '1', 'file' => rawurlencode( $doc ) ], $base_clean );
                    if ( $trans_id ) {
                        $send_link = add_query_arg( [ 'trans_id' => $trans_id ], $send_link );
                    }
                    // Link para exclusão
                    $del_link  = add_query_arg( [ 'dps_delete_doc' => '1', 'file' => rawurlencode( $doc ) ], $base_clean );
                    echo '<li>';
                    echo '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $doc ) . '</a> ';
                    echo '<a href="#" class="dps-fin-doc-email" data-base="' . esc_attr( $send_link ) . '">' . esc_html__( 'Enviar por email', 'dps-finance-addon' ) . '</a> ';
                    echo '<a href="' . esc_url( $del_link ) . '" onclick="return confirm(\'' . esc_js( __( 'Tem certeza que deseja excluir este documento?', 'dps-finance-addon' ) ) . '\');">' . esc_html__( 'Excluir', 'dps-finance-addon' ) . '</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
            // Script para prompt de email
            echo '<script>(function($){$(document).on("click", ".dps-fin-doc-email", function(e){e.preventDefault();var base=$(this).data("base");var email=prompt("Para qual email deseja enviar? Deixe em branco para usar o email padrão.");if(email===null){return;}email=email.trim();var url=base; if(email){url += (url.indexOf("?")===-1 ? "?" : "&") + "to_email=" + encodeURIComponent(email);} window.location.href=url;});})(jQuery);</script>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Exemplo de consulta utilizando metas históricas para somar faturamento.
     * Retorna o total em centavos do valor salvo em `_dps_total_at_booking`
     * para agendamentos dentro do intervalo informado.
     *
     * @param string $start_date Data inicial (Y-m-d).
     * @param string $end_date   Data final (Y-m-d).
     * @return int Total em centavos.
     */
    public function sum_revenue_by_period( $start_date, $end_date ) {
        return DPS_Finance_Revenue_Query::sum_by_period( $start_date, $end_date );
    }

    /**
     * Renderiza a seção do controle financeiro: formulário para nova transação e listagem.
     */
    private function section_financeiro() {
        global $wpdb;
        $table = $wpdb->prefix . 'dps_transacoes';
        // Filtros de datas
        $start_date = isset( $_GET['fin_start'] ) ? sanitize_text_field( $_GET['fin_start'] ) : '';
        $end_date   = isset( $_GET['fin_end'] ) ? sanitize_text_field( $_GET['fin_end'] ) : '';
        // Filtro de categoria
        $cat_filter = isset( $_GET['fin_cat'] ) ? sanitize_text_field( $_GET['fin_cat'] ) : '';
        // Intervalos rápidos: últimos 7/30 dias
        $range      = isset( $_GET['fin_range'] ) ? sanitize_text_field( $_GET['fin_range'] ) : '';
        if ( $range === '7' || $range === '30' ) {
            // Calcula intervalo relativo ao dia atual
            $days = intval( $range );
            $end_date   = current_time( 'Y-m-d' );
            $start_date = date( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );
        }
        $where      = '1=1';
        $params     = [];
        if ( $start_date ) {
            $where  .= ' AND data >= %s';
            $params[] = $start_date;
        }
        if ( $end_date ) {
            $where  .= ' AND data <= %s';
            $params[] = $end_date;
        }
        // Filtro por categoria
        if ( $cat_filter !== '' ) {
            $where  .= ' AND categoria = %s';
            $params[] = $cat_filter;
        }
        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY data DESC", $params );
        } else {
            $query = "SELECT * FROM $table ORDER BY data DESC";
        }
        // Lista de transações
        $trans = $wpdb->get_results( $query );
        // Lista de clientes para seleção opcional
        $clients = get_posts( [
            'post_type'      => 'dps_cliente',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ob_start();
        echo '<div class="dps-section" id="dps-section-financeiro">';
        echo '<h3>' . esc_html__( 'Controle Financeiro', 'dps-finance-addon' ) . '</h3>';
        // Se um ID de transação foi passado via query para registrar pagamento parcial, exibe formulário especializado
        if ( isset( $_GET['register_partial'] ) && is_numeric( $_GET['register_partial'] ) ) {
            global $wpdb;
            $partial_id = intval( $_GET['register_partial'] );
            $trans_pp  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $partial_id ) );
            if ( $trans_pp ) {
                $already_paid = $this->get_partial_sum( $partial_id );
                $desc_value   = dps_format_money_br( (int) round( (float) $trans_pp->valor * 100 ) );
                $paid_value   = dps_format_money_br( (int) round( (float) $already_paid * 100 ) );
                echo '<div class="dps-partial-form" style="margin:15px 0;padding:10px;border:1px solid #ddd;background:#f9f9f9;">';
                echo '<h4>' . sprintf( esc_html__( 'Registrar pagamento parcial - Transação #%1$s (Total: R$ %2$s, Pago: R$ %3$s)', 'dps-finance-addon' ), esc_html( $partial_id ), esc_html( $desc_value ), esc_html( $paid_value ) ) . '</h4>';
                echo '<form method="post" class="dps-form">';
                echo '<input type="hidden" name="dps_finance_action" value="save_partial">';
                wp_nonce_field( 'dps_finance_action', 'dps_finance_nonce' );
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $partial_id ) . '">';
                $today = date( 'Y-m-d' );
                echo '<p><label>' . esc_html__( 'Data', 'dps-finance-addon' ) . '<br><input type="date" name="partial_date" value="' . esc_attr( $today ) . '" required></label></p>';
                echo '<p><label>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '<br><input type="number" step="0.01" name="partial_value" required></label></p>';
                echo '<p><label>' . esc_html__( 'Método', 'dps-finance-addon' ) . '<br><select name="partial_method"><option value="pix">PIX</option><option value="cartao">' . esc_html__( 'Cartão', 'dps-finance-addon' ) . '</option><option value="dinheiro">' . esc_html__( 'Dinheiro', 'dps-finance-addon' ) . '</option><option value="outro">' . esc_html__( 'Outro', 'dps-finance-addon' ) . '</option></select></label></p>';
                $cancel_link = esc_url( remove_query_arg( 'register_partial' ) . '#financeiro' );
                echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar', 'dps-finance-addon' ) . '</button> <a href="' . $cancel_link . '" class="button">' . esc_html__( 'Cancelar', 'dps-finance-addon' ) . '</a></p>';
                echo '</form>';
                echo '</div>';
            }
        }
        // Formulário de nova transação
        echo '<h4>' . esc_html__( 'Registrar transação', 'dps-finance-addon' ) . '</h4>';
        echo '<form method="post" class="dps-form">';
        echo '<input type="hidden" name="dps_finance_action" value="save_trans">';
        wp_nonce_field( 'dps_finance_action', 'dps_finance_nonce' );
        echo '<p><label>' . esc_html__( 'Data', 'dps-finance-addon' ) . '<br><input type="date" name="finance_date" value="' . esc_attr( date( 'Y-m-d' ) ) . '" required></label></p>';
        echo '<p><label>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '<br><input type="number" step="0.01" name="finance_value" required></label></p>';
        echo '<p><label>' . esc_html__( 'Categoria', 'dps-finance-addon' ) . '<br><input type="text" name="finance_category" required></label></p>';
        echo '<p><label>' . esc_html__( 'Tipo', 'dps-finance-addon' ) . '<br><select name="finance_type">';
        echo '<option value="receita">' . esc_html__( 'Receita', 'dps-finance-addon' ) . '</option>';
        echo '<option value="despesa">' . esc_html__( 'Despesa', 'dps-finance-addon' ) . '</option>';
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__( 'Status', 'dps-finance-addon' ) . '<br><select name="finance_status">';
        echo '<option value="em_aberto">' . esc_html__( 'Em aberto', 'dps-finance-addon' ) . '</option>';
        echo '<option value="pago">' . esc_html__( 'Pago', 'dps-finance-addon' ) . '</option>';
        echo '</select></label></p>';
        // Cliente opcional
        echo '<p><label>' . esc_html__( 'Cliente (opcional)', 'dps-finance-addon' ) . '<br><select name="finance_client_id">';
        echo '<option value="">' . esc_html__( 'Nenhum', 'dps-finance-addon' ) . '</option>';
        foreach ( $clients as $cli ) {
            echo '<option value="' . esc_attr( $cli->ID ) . '">' . esc_html( $cli->post_title ) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>' . esc_html__( 'Descrição', 'dps-finance-addon' ) . '<br><input type="text" name="finance_desc"></label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Salvar', 'dps-finance-addon' ) . '</button></p>';
        echo '</form>';
        // Lista de transações
        echo '<h4>' . esc_html__( 'Transações Registradas', 'dps-finance-addon' ) . '</h4>';
        // Formulário de filtro por data e categoria
        echo '<form method="get" class="dps-finance-date-filter" style="margin-bottom:10px;">';
        // Mantém parâmetros existentes (exceto filtros de data, intervalo e categoria)
        foreach ( $_GET as $k => $v ) {
            if ( in_array( $k, [ 'fin_start', 'fin_end', 'fin_range', 'fin_cat' ], true ) ) {
                continue;
            }
            echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
        }
        echo '<label>' . esc_html__( 'De', 'dps-finance-addon' ) . ' <input type="date" name="fin_start" value="' . esc_attr( $start_date ) . '"></label> ';
        echo '<label>' . esc_html__( 'Até', 'dps-finance-addon' ) . ' <input type="date" name="fin_end" value="' . esc_attr( $end_date ) . '"></label> ';
        // Dropdown de categorias
        // Busca categorias distintas na base
        global $wpdb;
        $cat_table = $wpdb->prefix . 'dps_transacoes';
        $cats = $wpdb->get_col( "SELECT DISTINCT categoria FROM $cat_table ORDER BY categoria" );
        echo '<label>' . esc_html__( 'Categoria', 'dps-finance-addon' ) . ' <select name="fin_cat"><option value="">' . esc_html__( 'Todas', 'dps-finance-addon' ) . '</option>';
        if ( $cats ) {
            foreach ( $cats as $cat ) {
                $cat_clean = esc_attr( $cat );
                echo '<option value="' . $cat_clean . '"' . selected( $cat_filter, $cat, false ) . '>' . esc_html( $cat ) . '</option>';
            }
        }
        echo '</select></label> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Filtrar', 'dps-finance-addon' ) . '</button> ';
        // Links rápidos: preserva categoria
        $quick_params = $_GET;
        unset( $quick_params['fin_start'], $quick_params['fin_end'], $quick_params['fin_range'] );
        // Garante manter a categoria selecionada
        if ( $cat_filter !== '' ) {
            $quick_params['fin_cat'] = $cat_filter;
        }
        $link7  = add_query_arg( array_merge( $quick_params, [ 'fin_range' => '7' ] ), get_permalink() ) . '#financeiro';
        $link30 = add_query_arg( array_merge( $quick_params, [ 'fin_range' => '30' ] ), get_permalink() ) . '#financeiro';
        echo '<a href="' . esc_url( $link7 ) . '" class="button" style="margin-left:5px;">' . esc_html__( 'Últimos 7 dias', 'dps-finance-addon' ) . '</a> ';
        echo '<a href="' . esc_url( $link30 ) . '" class="button" style="margin-left:5px;">' . esc_html__( 'Últimos 30 dias', 'dps-finance-addon' ) . '</a> ';
        // Link de limpar filtros: remove todos os filtros inclusive categoria
        $clear_params = $quick_params;
        unset( $clear_params['fin_start'], $clear_params['fin_end'], $clear_params['fin_range'], $clear_params['fin_cat'] );
        $clear_link = add_query_arg( $clear_params, get_permalink() ) . '#financeiro';
        echo '<a href="' . esc_url( $clear_link ) . '" class="button" style="margin-left:5px;">' . esc_html__( 'Limpar filtros', 'dps-finance-addon' ) . '</a>';
        // Link para exportar CSV das transações filtradas
        $export_params = $_GET;
        $export_params['dps_fin_export'] = '1';
        $export_link = add_query_arg( $export_params, get_permalink() ) . '#financeiro';
        echo '<a href="' . esc_url( $export_link ) . '" class="button" style="margin-left:5px;">' . esc_html__( 'Exportar CSV', 'dps-finance-addon' ) . '</a>';
        echo '</form>';
        if ( $trans ) {
            // Estilos para destacar o status das transações. Linhas com status em aberto ficam amareladas
            // e linhas com status pago ficam esverdeadas, facilitando a identificação rápida.
            echo '<style>
            table.dps-table tr.fin-status-em_aberto { background-color:#fff8e1; }
            table.dps-table tr.fin-status-pago { background-color:#e6ffed; }
            </style>';
            // Cabeçalho da tabela: adicionamos colunas para Pet atendido, Serviços, Contato (WhatsApp) e Recorrente
            echo '<table class="dps-table"><thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Valor', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Categoria', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Tipo', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'dps-finance-addon' ) . '</th>';
            // Coluna de pagamentos parciais: exibe valor pago e total e link para registrar
            echo '<th>' . esc_html__( 'Pagamentos', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Pet atendido', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Serviços', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Cobrança', 'dps-finance-addon' ) . '</th>';
            echo '<th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $trans as $tr ) {
                // Cliente
                $client_name = '';
                if ( $tr->cliente_id ) {
                    $cpost = get_post( $tr->cliente_id );
                    if ( $cpost ) {
                        $client_name = $cpost->post_title;
                    }
                }
                // Pet atendido (se agendamento vinculado)
                $pet_name = '-';
                if ( $tr->agendamento_id ) {
                    $pet_id = get_post_meta( $tr->agendamento_id, 'appointment_pet_id', true );
                    if ( $pet_id ) {
                        $ppost = get_post( $pet_id );
                        if ( $ppost ) {
                            $pet_name = $ppost->post_title;
                        }
                    }
                }
                // Status editável
                $status_options = [
                    'em_aberto' => __( 'Em aberto', 'dps-finance-addon' ),
                    'pago'      => __( 'Pago', 'dps-finance-addon' ),
                    'cancelado' => __( 'Cancelado', 'dps-finance-addon' ),
                ];
                // Adiciona classe para o status da transação
                echo '<tr class="fin-status-' . esc_attr( $tr->status ) . '">';
                echo '<td>' . esc_html( date_i18n( 'd-m-Y', strtotime( $tr->data ) ) ) . '</td>';
                $tr_valor_cents = (int) round( (float) $tr->valor * 100 );
                echo '<td>R$ ' . esc_html( dps_format_money_br( $tr_valor_cents ) ) . '</td>';
                echo '<td>' . esc_html( $tr->categoria ) . '</td>';
                echo '<td>' . esc_html( $tr->tipo ) . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline-block;">';
                echo '<input type="hidden" name="dps_update_trans_status" value="1">';
                echo '<input type="hidden" name="trans_id" value="' . esc_attr( $tr->id ) . '">';
                echo '<select name="trans_status" onchange="this.form.submit()">';
                foreach ( $status_options as $val => $label ) {
                    echo '<option value="' . esc_attr( $val ) . '"' . selected( $tr->status, $val, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                echo '</form>';
                echo '</td>';
                // Coluna de pagamentos parciais
                $partial_paid = $this->get_partial_sum( $tr->id );
                echo '<td>';
                $partial_paid_cents = (int) round( (float) $partial_paid * 100 );
                echo 'R$ ' . esc_html( dps_format_money_br( $partial_paid_cents ) ) . ' / R$ ' . esc_html( dps_format_money_br( $tr_valor_cents ) );
                if ( $tr->status !== 'pago' ) {
                    // Mantém parâmetros de filtro existentes ao gerar o link de registro
                    $link_params = $_GET;
                    $link_params['register_partial'] = $tr->id;
                    $reg_link = add_query_arg( $link_params, get_permalink() ) . '#financeiro';
                    echo ' <a href="' . esc_url( $reg_link ) . '">' . esc_html__( 'Registrar', 'dps-finance-addon' ) . '</a>';
                }
                echo '</td>';
                echo '<td>' . esc_html( $client_name ?: '-' ) . '</td>';
                echo '<td>' . esc_html( $pet_name ) . '</td>';
                // Link Serviços
                if ( $tr->agendamento_id ) {
                    echo '<td><a href="#" class="dps-trans-services" data-appt-id="' . esc_attr( $tr->agendamento_id ) . '">' . esc_html__( 'Ver', 'dps-finance-addon' ) . '</a></td>';
                } else {
                    echo '<td>-</td>';
                }
                // Cobrança via WhatsApp: se transação estiver em aberto e não for de assinatura, cria link; caso contrário, mostra "-"
                echo '<td>';
                $show_charge = false;
                // Apenas para status em aberto e sem plano (não é assinatura)
                if ( $tr->status === 'em_aberto' && empty( $tr->plano_id ) && $tr->cliente_id ) {
                    $show_charge = true;
                }
                if ( $show_charge ) {
                    // Obtém telefone do cliente (meta client_phone)
                    $phone = get_post_meta( $tr->cliente_id, 'client_phone', true );
                    if ( $phone ) {
                        // Remove caracteres não numéricos
                        $digits = preg_replace( '/\D+/', '', $phone );
                        // Garante código do Brasil (55) se for número local sem DDI
                        if ( strlen( $digits ) == 10 || strlen( $digits ) == 11 ) {
                            $digits = '55' . $digits;
                        }
                        // Prepara mensagem de cobrança (profissional e amigável)
                        $client_name = $client_name ? $client_name : '';
                        $pet_title   = $pet_name !== '-' ? $pet_name : '';
                        $date_str    = date_i18n( 'd-m-Y', strtotime( $tr->data ) );
                        $valor_str   = dps_format_money_br( $tr_valor_cents );
                        $msg = sprintf( 'Olá %s, tudo bem? O atendimento do pet %s em %s foi finalizado e o pagamento de R$ %s ainda está pendente. Para sua comodidade, você pode pagar via PIX celular 15 99160‑6299 ou utilizar o link: https://link.mercadopago.com.br/desipetshower. Obrigado pela confiança!', $client_name, $pet_title, $date_str, $valor_str );
                        $wa_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                        echo '<a href="' . esc_url( $wa_link ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-finance-addon' ) . '</a>';
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                // Recorrência removida: não exibe coluna de recorrente
                // Ações: excluir
                echo '<td><a href="' . esc_url( add_query_arg( [ 'dps_delete_trans' => '1', 'id' => $tr->id ] ) ) . '" onclick="return confirm(\'Você tem certeza?\')">' . esc_html__( 'Excluir', 'dps-finance-addon' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            // ========= Gráficos de receitas/despesas e categorias =========
            // Calcula totais por mês e por categoria
            $month_receitas  = [];
            $month_despesas  = [];
            $cat_totals      = [];
            foreach ( $trans as $tr_rec ) {
                $month_key = date_i18n( 'm/Y', strtotime( $tr_rec->data ) );
                $valor     = (float) $tr_rec->valor;
                if ( $tr_rec->tipo === 'receita' ) {
                    if ( ! isset( $month_receitas[ $month_key ] ) ) $month_receitas[ $month_key ] = 0;
                    $month_receitas[ $month_key ] += $valor;
                } else {
                    if ( ! isset( $month_despesas[ $month_key ] ) ) $month_despesas[ $month_key ] = 0;
                    $month_despesas[ $month_key ] += $valor;
                }
                $cat = $tr_rec->categoria ? $tr_rec->categoria : __( 'Outros', 'dps-finance-addon' );
                if ( ! isset( $cat_totals[ $cat ] ) ) $cat_totals[ $cat ] = 0;
                $cat_totals[ $cat ] += $valor;
            }
            // Ordena meses
            $all_months = array_unique( array_merge( array_keys( $month_receitas ), array_keys( $month_despesas ) ) );
            sort( $all_months );
            $receitas_values = [];
            $despesas_values = [];
            foreach ( $all_months as $mk ) {
                $receitas_values[] = isset( $month_receitas[ $mk ] ) ? round( $month_receitas[ $mk ], 2 ) : 0;
                $despesas_values[] = isset( $month_despesas[ $mk ] ) ? round( $month_despesas[ $mk ], 2 ) : 0;
            }
            // Prepara dados de categorias
            $cat_labels  = array_keys( $cat_totals );
            $cat_values  = array_values( $cat_totals );
            // Gera cores aleatórias para categorias
            $cat_colors  = [];
            foreach ( $cat_labels as $clab ) {
                $cat_colors[] = 'rgba(' . mt_rand( 0, 255 ) . ', ' . mt_rand( 0, 255 ) . ', ' . mt_rand( 0, 255 ) . ', 0.6)';
            }
            echo '<div style="margin-top:20px;">';
            // Removido: gráficos financeiros não são exibidos
            echo '</div>';
            // Script inline para mostrar detalhes dos serviços vinculados às transações
            // Utiliza a mesma chamada AJAX do add‑on da agenda para buscar serviços do agendamento.
            echo '<script type="text/javascript">(function($){$(document).on("click",".dps-trans-services",function(e){e.preventDefault();var apptId=$(this).data("appt-id");$.post("' . esc_js( admin_url( 'admin-ajax.php' ) ) . '",{action:"dps_get_services_details",appt_id:apptId,nonce:"' . wp_create_nonce( 'dps_get_services_details' ) . '"},function(resp){if(resp && resp.success){var services=resp.data.services||[];if(services.length>0){var msg="";for(var i=0;i<services.length;i++){var srv=services[i];msg+=srv.name+" - R$ "+parseFloat(srv.price).toFixed(2);if(i<services.length-1) msg+="\n";}alert(msg);}else{alert("Nenhum serviço encontrado.");}}else{alert(resp.data?resp.data.message:"Erro ao buscar serviços.");}});});})(jQuery);</script>';

            // ============== Cobrança de pendências ==============
            echo '<h4>' . esc_html__( 'Cobrança de pendências', 'dps-finance-addon' ) . '</h4>';
            // Agrupa transações em aberto por cliente, considerando pagamentos parciais
            $pendings = [];
            foreach ( $trans as $item ) {
                if ( $item->status === 'em_aberto' ) {
                    $due = (float) $item->valor - $this->get_partial_sum( $item->id );
                    if ( $due > 0 ) {
                        $cid = $item->cliente_id ? intval( $item->cliente_id ) : 0;
                        if ( ! isset( $pendings[ $cid ] ) ) {
                            $pendings[ $cid ] = [ 'due' => 0.0, 'trans' => [] ];
                        }
                        $pendings[ $cid ]['due'] += $due;
                        $pendings[ $cid ]['trans'][] = $item;
                    }
                }
            }
            if ( ! empty( $pendings ) ) {
                echo '<table class="dps-table"><thead><tr><th>' . esc_html__( 'Cliente', 'dps-finance-addon' ) . '</th><th>' . esc_html__( 'Valor devido', 'dps-finance-addon' ) . '</th><th>' . esc_html__( 'Ações', 'dps-finance-addon' ) . '</th></tr></thead><tbody>';
                foreach ( $pendings as $cid => $pdata ) {
                    $cname = '';
                    $phone_link = '';
                    if ( $cid ) {
                        $cpost = get_post( $cid );
                        if ( $cpost ) {
                            $cname = $cpost->post_title;
                            $phone_meta = get_post_meta( $cid, 'client_phone', true );
                            if ( $phone_meta ) {
                                $digits = preg_replace( '/\D+/', '', $phone_meta );
                                if ( strlen( $digits ) == 10 || strlen( $digits ) == 11 ) {
                                    $digits = '55' . $digits;
                                }
                                $valor_str = dps_format_money_br( (int) round( (float) $pdata['due'] * 100 ) );
                                $msg = sprintf( 'Olá %s, tudo bem? Há pagamentos pendentes no total de R$ %s relacionados aos seus atendimentos na Desi Pet Shower. Para regularizar, você pode pagar via PIX ou utilizar nosso link: https://link.mercadopago.com.br/desipetshower. Muito obrigado!', $cname, $valor_str );
                                $phone_link = 'https://wa.me/' . $digits . '?text=' . rawurlencode( $msg );
                            }
                        }
                    }
                    $due_cents = (int) round( (float) $pdata['due'] * 100 );
                    echo '<tr><td>' . esc_html( $cname ?: '-' ) . '</td><td>R$ ' . esc_html( dps_format_money_br( $due_cents ) ) . '</td><td>';
                    if ( $phone_link ) {
                        echo '<a href="' . esc_url( $phone_link ) . '" target="_blank">' . esc_html__( 'Cobrar via WhatsApp', 'dps-finance-addon' ) . '</a>';
                    } else {
                        echo '-';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'Nenhum cliente com pendências em aberto.', 'dps-finance-addon' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Nenhuma transação registrada.', 'dps-finance-addon' ) . '</p>';
        }
        // Seções adicionais removidas: cobranças/notas e documentos não são exibidos nesta versão
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Sincroniza o status das transações financeiras quando o status de um agendamento é atualizado.
     *
     * Este método é disparado pelo hook 'updated_post_meta'. Se a meta atualizada for 'appointment_status',
     * procura transações vinculadas ao agendamento e atualiza seu status no banco financeiro.
     *
     * @param int    $meta_id    ID da meta atualizada.
     * @param int    $object_id  ID do post ao qual a meta pertence (agendamento).
     * @param string $meta_key   Chave da meta atualizada.
     * @param mixed  $meta_value Novo valor da meta.
     */
    public function sync_status_to_finance( $meta_id, $object_id, $meta_key, $meta_value ) {
        // Apenas processa status de agendamentos
        if ( $meta_key !== 'appointment_status' ) {
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'dps_transacoes';
        $appt_id = intval( $object_id );
        if ( ! $appt_id ) {
            return;
        }
        // Determina novo status financeiro com base no status do agendamento
        $status_map = [
            'finalizado_pago'   => 'pago',
            'finalizado e pago' => 'pago',
            'finalizado'        => 'em_aberto',
            'cancelado'         => 'cancelado',
        ];
        $status_slug = is_string( $meta_value ) ? $meta_value : '';
        $new_status  = isset( $status_map[ $status_slug ] ) ? $status_map[ $status_slug ] : null;
        if ( ! $new_status ) {
            return;
        }

        // Verifica se já existe transação para este agendamento
        $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE agendamento_id = %d", $appt_id ) );

        // Cancelamento apenas altera o status das transações existentes
        if ( $new_status === 'cancelado' ) {
            if ( $existing_id ) {
                $wpdb->update( $table, [ 'status' => 'cancelado' ], [ 'agendamento_id' => $appt_id ], [ '%s' ], [ '%d' ] );
            }
            return;
        }

        // Recupera informações do agendamento para atualizar ou criar transação
        $client_id   = get_post_meta( $appt_id, 'appointment_client_id', true );
        $valor_cents = (int) get_post_meta( $appt_id, '_dps_total_at_booking', true );
        if ( $valor_cents <= 0 ) {
            $valor_meta  = get_post_meta( $appt_id, 'appointment_total_value', true );
            $valor_cents = dps_parse_money_br( $valor_meta );
        }
        $valor = $valor_cents / 100;
        // Monta descrição a partir de serviços e pet
        $desc_parts  = [];
        $service_ids = get_post_meta( $appt_id, 'appointment_services', true );
        if ( is_array( $service_ids ) && ! empty( $service_ids ) ) {
            foreach ( $service_ids as $sid ) {
                $srv = get_post( $sid );
                if ( $srv ) {
                    $desc_parts[] = $srv->post_title;
                }
            }
        }
        $pet_id   = get_post_meta( $appt_id, 'appointment_pet_id', true );
        $pet_post = $pet_id ? get_post( $pet_id ) : null;
        if ( $pet_post ) {
            $desc_parts[] = $pet_post->post_title;
        }
        $desc = implode( ' - ', $desc_parts );
        // Determina a data da transação. Para atualizações de status, usamos a data do agendamento ou a data atual caso não exista.
        $appt_date  = get_post_meta( $appt_id, 'appointment_date', true );
        $trans_date = $appt_date ? $appt_date : current_time( 'Y-m-d' );
        $trans_data = [
            'cliente_id'     => $client_id ?: null,
            'agendamento_id' => $appt_id,
            'plano_id'       => null,
            'data'           => $trans_date,
            'valor'          => $valor,
            'categoria'      => __( 'Serviço', 'dps-finance-addon' ),
            'tipo'           => 'receita',
            'status'         => ( $new_status === 'pago' ? 'pago' : 'em_aberto' ),
            'descricao'      => $desc,
        ];
        if ( $existing_id ) {
            // Atualiza a transação existente com novo status, valor, descrição e data
            $wpdb->update( $table, [
                'status'    => $trans_data['status'],
                'valor'     => $trans_data['valor'],
                'descricao' => $trans_data['descricao'],
                'data'      => $trans_data['data'],
            ], [ 'id' => $existing_id ], [ '%s','%f','%s','%s' ], [ '%d' ] );
        } else {
            // Cria uma nova transação se ainda não existir
            $wpdb->insert( $table, $trans_data, [ '%d','%d','%d','%s','%f','%s','%s','%s','%s' ] );
        }

        if ( $trans_data['status'] === 'pago' ) {
            $amount_in_cents = (int) round( $valor * 100 );
            do_action( 'dps_finance_booking_paid', $appt_id, (int) $client_id, $amount_in_cents );
        }
        // fecha o método sync_status_to_finance
    }

    /**
     * Cria a tabela de parcelas de pagamentos, se ainda não existir.
     *
     * A tabela dps_parcelas armazena valores pagos parcialmente para cada transação,
     * permitindo registrar entradas parciais e diferentes métodos de pagamento. Cada linha
     * inclui a data do pagamento, o valor e o método (PIX, cartão, dinheiro ou outro).
     */
    public function maybe_create_parcelas_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_parcelas';
        // Verifica se a tabela já existe
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $exists != $table_name ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                trans_id bigint(20) NOT NULL,
                data date NOT NULL,
                valor float NOT NULL,
                metodo varchar(50) DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY trans_id (trans_id)
            ) $charset_collate;";
            dbDelta( $sql );
        }
    }

    /**
     * Cria a tabela principal de transações financeiras (dps_transacoes) se ela não
     * existir. Esta tabela armazena todas as receitas e despesas registradas
     * pelos módulos de pagamentos, assinaturas e pelo próprio controle financeiro.
     * Sem esta tabela, as abas Financeiro e Estatísticas não conseguem exibir
     * registros de transações. A verificação e criação são realizadas no hook
     * init para cobrir casos em que a função de ativação não foi executada.
     *
     * Estrutura de campos:
     * - id: chave primária
     * - cliente_id: ID do cliente associado (nullable)
     * - agendamento_id: ID do agendamento (nullable)
     * - plano_id: ID da assinatura/plano (nullable)
     * - data: data da transação (DATE)
     * - valor: valor monetário (FLOAT)
     * - categoria: categoria da transação (ex.: Serviço, Assinatura, Despesa)
     * - tipo: receita ou despesa
     * - status: em_aberto ou pago
     * - descricao: descrição detalhada
     */
    public function maybe_create_transacoes_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_transacoes';
        // Verifica se a tabela já existe
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        if ( $exists != $table_name ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                cliente_id bigint(20) DEFAULT NULL,
                agendamento_id bigint(20) DEFAULT NULL,
                plano_id bigint(20) DEFAULT NULL,
                data date DEFAULT NULL,
                valor float DEFAULT 0,
                categoria varchar(255) NOT NULL DEFAULT '',
                tipo varchar(50) NOT NULL DEFAULT '',
                status varchar(20) NOT NULL DEFAULT '',
                descricao text NOT NULL DEFAULT '',
                PRIMARY KEY  (id),
                KEY cliente_id (cliente_id),
                KEY agendamento_id (agendamento_id),
                KEY plano_id (plano_id)
            ) $charset_collate;";
            dbDelta( $sql );
        }
    }

    /**
     * Calcula a soma de todos os pagamentos parciais registrados para uma transação.
     *
     * @param int $trans_id ID da transação
     * @return float Valor total pago até o momento
     */
    public function get_partial_sum( $trans_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dps_parcelas';
        $sum        = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(valor) FROM $table_name WHERE trans_id = %d", $trans_id ) );
        return $sum ? (float) $sum : 0.0;
    }
} // end class DPS_Finance_Addon

} // end if ! class_exists

// Instancia a classe somente se ainda não houver uma instância global
if ( class_exists( 'DPS_Finance_Addon' ) && ! isset( $GLOBALS['dps_finance_addon'] ) ) {
    $GLOBALS['dps_finance_addon'] = new DPS_Finance_Addon();
}