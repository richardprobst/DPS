<?php
/**
 * Gerencia as configurações do Finance Add-on.
 *
 * @package    Desi_Pet_Shower
 * @subpackage Finance_Addon
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe responsável por gerenciar as configurações do add-on financeiro.
 */
class DPS_Finance_Settings {

    /**
     * Chave de opção no banco de dados.
     *
     * @var string
     */
    const OPTION_KEY = 'dps_finance_settings';

    /**
     * Configurações padrão.
     *
     * @var array
     */
    private static $defaults = [
        'store_name'       => 'Banho e Tosa Desi Pet Shower',
        'store_address'    => 'Rua Água Marinha, 45 – Residencial Galo de Ouro, Cerquilho, SP',
        'store_phone'      => '15 99160-6299',
        'store_email'      => 'contato@desi.pet',
        'pix_key'          => '15 99160-6299',
        'payment_link'     => 'https://link.mercadopago.com.br/desipetshower',
        'whatsapp_message' => 'Olá {cliente}, tudo bem? O atendimento do pet {pet} em {data} foi finalizado e o pagamento de R$ {valor} ainda está pendente. Para sua comodidade, você pode pagar via PIX {pix} ou utilizar o link: {link}. Obrigado pela confiança!',
        'pending_message'  => 'Olá {cliente}, tudo bem? Há pagamentos pendentes no total de R$ {valor} relacionados aos seus atendimentos na {loja}. Para regularizar, você pode pagar via PIX ou utilizar nosso link: {link}. Muito obrigado!',
    ];

    /**
     * Cache das configurações.
     *
     * @var array|null
     */
    private static $settings = null;

    /**
     * Retorna todas as configurações.
     *
     * @return array
     */
    public static function get_all() {
        if ( self::$settings === null ) {
            $saved = get_option( self::OPTION_KEY, [] );
            self::$settings = wp_parse_args( $saved, self::$defaults );
        }
        return self::$settings;
    }

    /**
     * Retorna uma configuração específica.
     *
     * @param string $key     Chave da configuração.
     * @param mixed  $default Valor padrão se não existir.
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_all();
        if ( isset( $settings[ $key ] ) ) {
            return $settings[ $key ];
        }
        if ( $default !== null ) {
            return $default;
        }
        return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : '';
    }

    /**
     * Salva as configurações.
     *
     * @param array $data Array de configurações a salvar.
     * @return bool
     */
    public static function save( $data ) {
        $settings = self::get_all();

        // Campos que devem usar sanitize_textarea_field para preservar quebras de linha
        $textarea_fields = [ 'whatsapp_message', 'pending_message' ];
        
        foreach ( self::$defaults as $key => $default ) {
            if ( isset( $data[ $key ] ) ) {
                if ( in_array( $key, $textarea_fields, true ) ) {
                    $settings[ $key ] = sanitize_textarea_field( $data[ $key ] );
                } else {
                    $settings[ $key ] = sanitize_text_field( $data[ $key ] );
                }
            }
        }
        
        // Salva no banco
        $result = update_option( self::OPTION_KEY, $settings );
        
        // Limpa cache
        self::$settings = null;
        
        return $result;
    }

    /**
     * Retorna os defaults.
     *
     * @return array
     */
    public static function get_defaults() {
        return self::$defaults;
    }

    /**
     * Formata mensagem de WhatsApp substituindo placeholders.
     *
     * @param string $template Mensagem modelo.
     * @param array  $data     Dados para substituição.
     * @return string
     */
    public static function format_message( $template, $data = [] ) {
        $placeholders = [
            '{cliente}' => isset( $data['cliente'] ) ? $data['cliente'] : '',
            '{pet}'     => isset( $data['pet'] ) ? $data['pet'] : '',
            '{data}'    => isset( $data['data'] ) ? $data['data'] : '',
            '{valor}'   => isset( $data['valor'] ) ? $data['valor'] : '',
            '{pix}'     => self::get( 'pix_key' ),
            '{link}'    => self::get( 'payment_link' ),
            '{loja}'    => self::get( 'store_name' ),
            '{endereco}'=> self::get( 'store_address' ),
            '{telefone}'=> self::get( 'store_phone' ),
            '{email}'   => self::get( 'store_email' ),
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
    }

    /**
     * Gera mensagem de cobrança via WhatsApp.
     *
     * @param string $cliente Nome do cliente.
     * @param string $pet     Nome do pet.
     * @param string $data    Data do atendimento.
     * @param string $valor   Valor formatado.
     * @return string
     */
    public static function get_whatsapp_message( $cliente, $pet, $data, $valor ) {
        $template = self::get( 'whatsapp_message' );
        return self::format_message( $template, [
            'cliente' => $cliente,
            'pet'     => $pet,
            'data'    => $data,
            'valor'   => $valor,
        ] );
    }

    /**
     * Gera mensagem de cobrança de pendências.
     *
     * @param string $cliente Nome do cliente.
     * @param string $valor   Valor total formatado.
     * @return string
     */
    public static function get_pending_message( $cliente, $valor ) {
        $template = self::get( 'pending_message' );
        return self::format_message( $template, [
            'cliente' => $cliente,
            'valor'   => $valor,
        ] );
    }
}
