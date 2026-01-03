# Guia de sanitização e escaping (desi.pet by PRObst – Base)

Este guia resume o padrão obrigatório de sanitização de entradas (`$_POST`, `$_GET`) e escaping de saídas em todos os formulários e shortcodes do plugin-base. Use sempre o *text domain* `desi-pet-shower` nos textos.

## Campos comuns e funções de sanitização

| Campo                         | Função de sanitização/ajuste                            |
| ----------------------------- | ------------------------------------------------------- |
| Nome, telefone, WhatsApp      | `sanitize_text_field( $valor )`                         |
| E-mail                        | `sanitize_email( $valor )`                              |
| Datas (string)                | `sanitize_text_field( $valor )` e validar formato       |
| Observações                   | `sanitize_textarea_field( $valor )`                     |
| IDs e contadores              | `(int) $valor`                                          |
| Valores monetários/numéricos  | `(float) $valor` (ou `wc_format_decimal` se aplicável)  |

> Sempre valide nonces e capabilities antes de gravar dados.

## Padrão para ler entradas `$_POST` / `$_GET`

```php
// Exemplo genérico em um handler de formulário
if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( $_POST['dps_nonce'], 'dps_salvar_cliente' ) ) {
    wp_die( esc_html__( 'Solicitação inválida.', 'desi-pet-shower' ) );
}

$cliente_id     = isset( $_POST['cliente_id'] ) ? (int) $_POST['cliente_id'] : 0;
$nome           = isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '';
$telefone       = isset( $_POST['telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['telefone'] ) ) : '';
$whatsapp       = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';
$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
$data_cadastro  = isset( $_POST['data_cadastro'] ) ? sanitize_text_field( wp_unslash( $_POST['data_cadastro'] ) ) : '';
$observacoes    = isset( $_POST['observacoes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ) ) : '';
$valor_pago     = isset( $_POST['valor_pago'] ) ? (float) $_POST['valor_pago'] : 0.0;
```

## Blocos prontos de sanitização

### Salvar cliente
```php
$cliente_data = array(
    'id'           => isset( $_POST['cliente_id'] ) ? (int) $_POST['cliente_id'] : 0,
    'nome'         => isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '',
    'telefone'     => isset( $_POST['telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['telefone'] ) ) : '',
    'whatsapp'     => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
    'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
    'observacoes'  => isset( $_POST['observacoes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ) ) : '',
);
```

### Salvar pet
```php
$pet_data = array(
    'id'            => isset( $_POST['pet_id'] ) ? (int) $_POST['pet_id'] : 0,
    'cliente_id'    => isset( $_POST['cliente_id'] ) ? (int) $_POST['cliente_id'] : 0,
    'nome'          => isset( $_POST['nome_pet'] ) ? sanitize_text_field( wp_unslash( $_POST['nome_pet'] ) ) : '',
    'especie'       => isset( $_POST['especie'] ) ? sanitize_text_field( wp_unslash( $_POST['especie'] ) ) : '',
    'raca'          => isset( $_POST['raca'] ) ? sanitize_text_field( wp_unslash( $_POST['raca'] ) ) : '',
    'observacoes'   => isset( $_POST['observacoes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ) ) : '',
);
```

### Salvar agendamento
```php
$agendamento_data = array(
    'id'             => isset( $_POST['agendamento_id'] ) ? (int) $_POST['agendamento_id'] : 0,
    'cliente_id'     => isset( $_POST['cliente_id'] ) ? (int) $_POST['cliente_id'] : 0,
    'pet_id'         => isset( $_POST['pet_id'] ) ? (int) $_POST['pet_id'] : 0,
    'data'           => isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '',
    'hora'           => isset( $_POST['hora'] ) ? sanitize_text_field( wp_unslash( $_POST['hora'] ) ) : '',
    'servico'        => isset( $_POST['servico'] ) ? sanitize_text_field( wp_unslash( $_POST['servico'] ) ) : '',
    'valor'          => isset( $_POST['valor'] ) ? (float) $_POST['valor'] : 0.0,
    'observacoes'    => isset( $_POST['observacoes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ) ) : '',
);
```

## Padrão de escaping para saídas

- **Conteúdo HTML**: `esc_html( $valor )`
- **Atributos (value, data-*, aria-*)**: `esc_attr( $valor )`
- **URLs**: `esc_url( $url )`
- **Texto traduzido**: `esc_html__( 'Texto', 'desi-pet-shower' )`

### Exemplo de listagem (clientes)

```php
<?php if ( ! empty( $clientes ) ) : ?>
    <table class="widefat">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Nome', 'desi-pet-shower' ); ?></th>
                <th><?php echo esc_html__( 'Telefone', 'desi-pet-shower' ); ?></th>
                <th><?php echo esc_html__( 'E-mail', 'desi-pet-shower' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $clientes as $cliente ) : ?>
                <tr>
                    <td><?php echo esc_html( $cliente->nome ); ?></td>
                    <td><?php echo esc_html( $cliente->telefone ); ?></td>
                    <td>
                        <a href="mailto:<?php echo esc_attr( $cliente->email ); ?>">
                            <?php echo esc_html( $cliente->email ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else : ?>
    <p><?php echo esc_html__( 'Nenhum cliente encontrado.', 'desi-pet-shower' ); ?></p>
<?php endif; ?>
```

## Formulário completo (HTML + processamento)

```php
// PROCESSAMENTO (ex.: admin_post_dps_salvar_cliente)
function dps_processar_cliente() {
    if ( ! isset( $_POST['dps_nonce'] ) || ! wp_verify_nonce( $_POST['dps_nonce'], 'dps_salvar_cliente' ) ) {
        wp_die( esc_html__( 'Solicitação inválida.', 'desi-pet-shower' ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permissões insuficientes.', 'desi-pet-shower' ) );
    }

    $cliente = array(
        'nome'         => isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '',
        'telefone'     => isset( $_POST['telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['telefone'] ) ) : '',
        'whatsapp'     => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
        'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
        'observacoes'  => isset( $_POST['observacoes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ) ) : '',
    );

    // Salvar no banco conforme a arquitetura atual (update/insert custom table ou post type).

    wp_safe_redirect( esc_url_raw( add_query_arg( 'dps_msg', 'cliente_salvo', wp_get_referer() ) ) );
    exit;
}
add_action( 'admin_post_dps_salvar_cliente', 'dps_processar_cliente' );
```

```php
// FORMULÁRIO (admin ou shortcode)
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="dps_salvar_cliente" />
    <?php wp_nonce_field( 'dps_salvar_cliente', 'dps_nonce' ); ?>

    <p>
        <label for="dps_nome"><?php echo esc_html__( 'Nome', 'desi-pet-shower' ); ?></label><br />
        <input type="text" id="dps_nome" name="nome" value="<?php echo isset( $cliente->nome ) ? esc_attr( $cliente->nome ) : ''; ?>" class="regular-text" />
    </p>

    <p>
        <label for="dps_telefone"><?php echo esc_html__( 'Telefone', 'desi-pet-shower' ); ?></label><br />
        <input type="text" id="dps_telefone" name="telefone" value="<?php echo isset( $cliente->telefone ) ? esc_attr( $cliente->telefone ) : ''; ?>" class="regular-text" />
    </p>

    <p>
        <label for="dps_whatsapp"><?php echo esc_html__( 'WhatsApp', 'desi-pet-shower' ); ?></label><br />
        <input type="text" id="dps_whatsapp" name="whatsapp" value="<?php echo isset( $cliente->whatsapp ) ? esc_attr( $cliente->whatsapp ) : ''; ?>" class="regular-text" />
    </p>

    <p>
        <label for="dps_email"><?php echo esc_html__( 'E-mail', 'desi-pet-shower' ); ?></label><br />
        <input type="email" id="dps_email" name="email" value="<?php echo isset( $cliente->email ) ? esc_attr( $cliente->email ) : ''; ?>" class="regular-text" />
    </p>

    <p>
        <label for="dps_observacoes"><?php echo esc_html__( 'Observações', 'desi-pet-shower' ); ?></label><br />
        <textarea id="dps_observacoes" name="observacoes" rows="4" cols="40"><?php echo isset( $cliente->observacoes ) ? esc_html( $cliente->observacoes ) : ''; ?></textarea>
    </p>

    <p>
        <button type="submit" class="button button-primary">
            <?php echo esc_html__( 'Salvar cliente', 'desi-pet-shower' ); ?>
        </button>
    </p>
</form>
```

Use estes blocos como referência para todos os handlers (clientes, pets, agendamentos) e views do plugin base.
