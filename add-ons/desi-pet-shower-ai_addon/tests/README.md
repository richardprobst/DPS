# DPS AI Add-on - Tests

Este diretório contém os testes unitários para o AI Add-on do DPS by PRObst.

## Estrutura de Testes

```
tests/
├── bootstrap.php                    # Bootstrap para ambiente de testes
├── Test_DPS_AI_Email_Parser.php     # Testes do parser de e-mails
├── Test_DPS_AI_Prompts.php          # Testes do sistema de prompts
└── Test_DPS_AI_Analytics.php        # Testes de cálculo de custos
```

## Requisitos

- PHP >= 7.4
- Composer

## Instalação

```bash
cd add-ons/desi-pet-shower-ai_addon
composer install
```

## Executando os Testes

### Todos os testes

```bash
composer test
```

Ou diretamente com PHPUnit:

```bash
vendor/bin/phpunit
```

### Com coverage report

```bash
composer test:coverage
```

O relatório será gerado em `coverage/index.html`.

### Teste específico

```bash
vendor/bin/phpunit tests/Test_DPS_AI_Email_Parser.php
```

## CI/CD

Os testes são executados automaticamente via GitHub Actions em:
- Push para branches `main`, `develop`, `copilot/**`
- Pull requests para `main` e `develop`

O workflow testa em múltiplas versões do PHP:
- PHP 8.0
- PHP 8.1
- PHP 8.2

## Cobertura de Testes

Classes testadas:
- ✅ `DPS_AI_Email_Parser` - Parser robusto de respostas de e-mail
- ✅ `DPS_AI_Prompts` - Sistema centralizado de prompts
- ✅ `DPS_AI_Analytics` - Cálculo de custos de tokens

### Email Parser (8 testes)

- Parsing de formato JSON estruturado
- Parsing de formato com rótulos (ASSUNTO:/CORPO:)
- Parsing de formato separado (primeira linha = subject)
- Fallback para texto plano
- Sanitização remove scripts maliciosos
- Resposta vazia retorna null
- Conversão texto→HTML
- Estatísticas de parsing

### Prompts System (9 testes)

- Carregamento de prompts por contexto (portal, public, whatsapp, email)
- Validação de contextos
- Lista de contextos disponíveis
- Cache de prompts
- Limpeza de cache

### Analytics (7 testes)

- Cálculo de custo para GPT-4o-mini
- Cálculo de custo para GPT-4o
- Cálculo de custo para GPT-4-turbo
- Custo zero para zero tokens
- Fallback para modelo desconhecido
- Conversão USD→BRL
- Cálculo com tokens fracionários

## Adicionando Novos Testes

1. Crie um arquivo `Test_NomeDaClasse.php` em `tests/`
2. Estenda `PHPUnit\Framework\TestCase`
3. Nomeie os métodos de teste com prefixo `test_`
4. Execute `composer test` para validar

Exemplo:

```php
<?php
use PHPUnit\Framework\TestCase;

class Test_MinhaClasse extends TestCase {
    public function test_meu_metodo() {
        $resultado = MinhaClasse::meu_metodo();
        $this->assertEquals('esperado', $resultado);
    }
}
```

## Mocks do WordPress

O arquivo `bootstrap.php` fornece mocks básicos de funções WordPress:
- `esc_html()`, `esc_attr()`, `esc_js()`
- `sanitize_text_field()`
- `wp_kses_post()`
- `apply_filters()`, `add_filter()`
- `dps_ai_log_*()` (logger)

Para testes mais complexos que requerem banco de dados ou hooks completos do WordPress, considere usar [WP_Mock](https://github.com/10up/wp_mock) ou a [WordPress Test Suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/).

## Troubleshooting

### Erro: "Class 'DPS_AI_Email_Parser' not found"

Certifique-se de que o `bootstrap.php` está carregando as classes corretamente. Verifique os caminhos em `require_once`.

### Erro: "Call to undefined function wp_kses_post()"

O mock dessa função pode estar faltando no `bootstrap.php`. Adicione-o ou verifique se o arquivo está sendo carregado.

### Testes lentos

Se os testes estiverem lentos, considere:
- Usar `@group` annotations para separar testes rápidos/lentos
- Executar apenas grupos específicos: `vendor/bin/phpunit --group=fast`

## Recursos

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [WP_Mock](https://github.com/10up/wp_mock) - Framework de mocking para WordPress
