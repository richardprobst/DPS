<?php
/**
 * Classe de configurações do Backup Add-on.
 *
 * Gerencia configurações como backup agendado, retenção e componentes.
 *
 * @package    DesiPetShower
 * @subpackage DPS_Backup_Addon
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Classe DPS_Backup_Settings
 *
 * @since 1.1.0
 */
class DPS_Backup_Settings {

    /**
     * Nome da option de configurações.
     *
     * @var string
     */
    const OPTION_NAME = 'dps_backup_settings';

    /**
     * Configurações padrão.
     *
     * @var array
     */
    private static $defaults = [
        'scheduled_enabled'    => false,
        'scheduled_frequency'  => 'weekly',
        'scheduled_day'        => 'sunday',
        'scheduled_time'       => '02:00',
        'retention_count'      => 5,
        'default_components'   => [ 'clients', 'pets', 'appointments', 'transactions', 'services', 'options', 'tables', 'files' ],
        'email_notification'   => true,
        'notification_email'   => '',
    ];

    /**
     * Obtém todas as configurações.
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_all() {
        $settings = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( $settings, self::$defaults );
    }

    /**
     * Obtém uma configuração específica.
     *
     * @since 1.1.0
     * @param string $key     Chave da configuração.
     * @param mixed  $default Valor padrão se não existir.
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = self::get_all();
        if ( isset( $settings[ $key ] ) ) {
            return $settings[ $key ];
        }
        return $default ?? ( self::$defaults[ $key ] ?? null );
    }

    /**
     * Salva uma configuração.
     *
     * @since 1.1.0
     * @param string $key   Chave da configuração.
     * @param mixed  $value Valor a salvar.
     * @return bool
     */
    public static function set( $key, $value ) {
        $settings = self::get_all();
        $settings[ $key ] = $value;
        return update_option( self::OPTION_NAME, $settings );
    }

    /**
     * Salva múltiplas configurações.
     *
     * @since 1.1.0
     * @param array $new_settings Configurações a salvar.
     * @return bool
     */
    public static function save( $new_settings ) {
        $settings = self::get_all();
        foreach ( $new_settings as $key => $value ) {
            if ( array_key_exists( $key, self::$defaults ) ) {
                $settings[ $key ] = $value;
            }
        }
        return update_option( self::OPTION_NAME, $settings );
    }

    /**
     * Retorna os componentes disponíveis para backup.
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_available_components() {
        return [
            'clients'      => __( 'Clientes', 'dps-backup-addon' ),
            'pets'         => __( 'Pets', 'dps-backup-addon' ),
            'appointments' => __( 'Agendamentos', 'dps-backup-addon' ),
            'transactions' => __( 'Transações Financeiras', 'dps-backup-addon' ),
            'services'     => __( 'Serviços', 'dps-backup-addon' ),
            'subscriptions' => __( 'Assinaturas', 'dps-backup-addon' ),
            'campaigns'    => __( 'Campanhas', 'dps-backup-addon' ),
            'options'      => __( 'Configurações do Sistema', 'dps-backup-addon' ),
            'tables'       => __( 'Tabelas Personalizadas', 'dps-backup-addon' ),
            'files'        => __( 'Arquivos (fotos, documentos)', 'dps-backup-addon' ),
        ];
    }

    /**
     * Retorna as frequências disponíveis para backup agendado.
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_frequencies() {
        return [
            'daily'   => __( 'Diário', 'dps-backup-addon' ),
            'weekly'  => __( 'Semanal', 'dps-backup-addon' ),
            'monthly' => __( 'Mensal', 'dps-backup-addon' ),
        ];
    }

    /**
     * Retorna os dias da semana disponíveis.
     *
     * @since 1.1.0
     * @return array
     */
    public static function get_weekdays() {
        return [
            'sunday'    => __( 'Domingo', 'dps-backup-addon' ),
            'monday'    => __( 'Segunda-feira', 'dps-backup-addon' ),
            'tuesday'   => __( 'Terça-feira', 'dps-backup-addon' ),
            'wednesday' => __( 'Quarta-feira', 'dps-backup-addon' ),
            'thursday'  => __( 'Quinta-feira', 'dps-backup-addon' ),
            'friday'    => __( 'Sexta-feira', 'dps-backup-addon' ),
            'saturday'  => __( 'Sábado', 'dps-backup-addon' ),
        ];
    }
}
