# Correção de PHP Warning no Stats Cache Invalidator

**Data:** 2026-01-19  
**Componente:** Stats Add-on - Cache Invalidator  
**Arquivo:** `plugins/desi-pet-shower-stats/includes/class-dps-stats-cache-invalidator.php`

## Problema Identificado

O log de erros mostrava warnings recorrentes:

```
PHP Warning: Attempt to read property "post_type" on string in 
.../class-dps-stats-cache-invalidator.php on line 160
```

## Análise da Causa Raiz

O método `invalidate_on_post_delete()` estava sendo usado para dois hooks do WordPress com assinaturas diferentes:

1. **`before_delete_post`** (WordPress 5.5.0+):
   - Parâmetro 1: `$post_id` (int)
   - Parâmetro 2: `$post` (WP_Post object)

2. **`trashed_post`** (WordPress 6.3.0+):
   - Parâmetro 1: `$post_id` (int)
   - Parâmetro 2: `$previous_status` (string)

O método original assumia que o segundo parâmetro seria sempre um objeto WP_Post ou null, tentando acessar `$post->post_type` diretamente. Quando o hook `trashed_post` era acionado, o segundo parâmetro era uma string (o status anterior do post), causando o warning.

## Solução Implementada

### 1. Separação de Métodos

Criados dois métodos específicos, cada um otimizado para sua assinatura de hook:

#### `invalidate_on_before_delete($post_id, $post)`
- Usado para o hook `before_delete_post`
- Recebe o objeto WP_Post diretamente como segundo parâmetro
- Valida que o parâmetro é `instanceof WP_Post` antes de acessar propriedades
- Mais eficiente pois não precisa buscar o post novamente

```php
public static function invalidate_on_before_delete( $post_id, $post ) {
    // Verificar se é um objeto válido
    if ( ! $post instanceof WP_Post ) {
        return;
    }
    
    // Verificar tipo de post
    if ( in_array( $post->post_type, [ 'dps_agendamento', 'dps_cliente', 'dps_pet', 'dps_subscription' ], true ) ) {
        self::invalidate_all_with_throttle();
    }
}
```

#### `invalidate_on_trash($post_id, $previous_status = '')`
- Usado para o hook `trashed_post`
- Ignora o segundo parâmetro (`$previous_status`)
- Busca o post internamente usando `get_post($post_id)`
- Valida que `get_post()` retornou um objeto válido

```php
public static function invalidate_on_trash( $post_id, $previous_status = '' ) {
    $post = get_post( $post_id );
    
    if ( ! $post ) {
        return;
    }
    
    // Verificar tipo de post
    if ( in_array( $post->post_type, [ 'dps_agendamento', 'dps_cliente', 'dps_pet', 'dps_subscription' ], true ) ) {
        self::invalidate_all_with_throttle();
    }
}
```

### 2. Atualização dos Hooks

```php
// ANTES:
add_action( 'before_delete_post', [ __CLASS__, 'invalidate_on_post_delete' ], 10, 2 );
add_action( 'trashed_post', [ __CLASS__, 'invalidate_on_post_delete' ], 10, 2 );

// DEPOIS:
add_action( 'before_delete_post', [ __CLASS__, 'invalidate_on_before_delete' ], 10, 2 );
add_action( 'trashed_post', [ __CLASS__, 'invalidate_on_trash' ], 10, 2 );
```

## Benefícios da Correção

1. **Eliminação de warnings**: Não haverá mais tentativas de acessar propriedades em strings
2. **Type safety**: Validação explícita de tipos antes de acessar propriedades de objetos
3. **Clareza de código**: Métodos com nomes descritivos que indicam qual hook estão tratando
4. **Compatibilidade**: Funciona em todas as versões do WordPress (5.5+ para before_delete_post, todas para trashed_post)
5. **Performance**: O método `invalidate_on_before_delete` é ligeiramente mais eficiente pois não precisa buscar o post novamente

## Validação

- ✓ Sintaxe PHP validada com `php -l`
- ✓ Teste de demonstração criado em `/tmp/test-cache-invalidator-simple.php`
- ✓ Lógica de invalidação de cache mantida intacta
- ✓ Compatibilidade com WordPress 6.9+ e PHP 8.4+ verificada
- ✓ CHANGELOG.md atualizado com a correção

## Arquivos Alterados

1. `plugins/desi-pet-shower-stats/includes/class-dps-stats-cache-invalidator.php`
   - Métodos `invalidate_on_before_delete()` e `invalidate_on_trash()` criados
   - Método `invalidate_on_post_delete()` removido
   - Hooks no método `init()` atualizados

2. `CHANGELOG.md`
   - Adicionada entrada na seção "Fixed (Corrigido)" do [Unreleased]

## Referências

- [WordPress Hook: before_delete_post](https://developer.wordpress.org/reference/hooks/before_delete_post/)
- [WordPress Hook: trashed_post](https://developer.wordpress.org/reference/hooks/trashed_post/)
- [PHP instanceof Operator](https://www.php.net/manual/en/language.operators.type.php)
