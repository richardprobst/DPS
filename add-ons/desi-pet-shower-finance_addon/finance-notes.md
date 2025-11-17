# Referência rápida do add-on Financeiro

## Exemplo de tabela `dps_transacoes`
```sql
CREATE TABLE wp_dps_transacoes (
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
) DEFAULT CHARSET=utf8mb4;
```

## Helpers de valores monetários
```php
$centavos = dps_parse_money_br( '129,90' ); // 12990
$valor_br = dps_format_money_br( 12990 );    // "129,90"
```

## Hooks de consistência de status
```php
add_action( 'updated_post_meta', 'dps_sync_status_to_finance', 10, 4 );
add_action( 'added_post_meta', 'dps_sync_status_to_finance', 10, 4 );

function dps_sync_status_to_finance( $meta_id, $object_id, $meta_key, $meta_value ) {
    // A função real está implementada dentro da classe DPS_Finance_Addon.
    // Este trecho ilustra a assinatura e o propósito: manter a tabela
    // dps_transacoes alinhada com o status do agendamento.
}
```
