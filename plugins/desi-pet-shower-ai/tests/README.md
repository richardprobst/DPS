# Guia de Testes - DPS AI Add-on

Este documento descreve a estrutura de testes do AI Add-on e como executá-los.

## Estrutura de Testes

```
tests/
├── bootstrap.php           # Configuração inicial dos testes (mocks WordPress)
├── unit/                   # Testes unitários (lógica pura, sem DB)
│   ├── Test_DPS_AI_Analytics.php
│   ├── Test_DPS_AI_Assistant_Context.php
│   ├── Test_DPS_AI_Conversations_Repository.php
│   ├── Test_DPS_AI_Email_Parser.php
│   ├── Test_DPS_AI_Knowledge_Base.php
│   ├── Test_DPS_AI_Maintenance.php
│   └── Test_DPS_AI_Prompts.php
└── integration/            # Testes de integração (requerem ambiente WordPress)
    └── (futuros testes de integração)
```

## Áreas Cobertas

### 1. Parsing e Tratamento de Respostas
- **Test_DPS_AI_Email_Parser**: Testes de parsing de e-mails em vários formatos (JSON, labeled, separated, plain text)
- Sanitização e remoção de código malicioso
- Conversão de texto para HTML
- Estatísticas de parsing

### 2. Lógica de Contexto
- **Test_DPS_AI_Assistant_Context**: Verificação de perguntas dentro/fora do contexto
- Matching de keywords relacionadas a pets, banho, tosa, agendamentos
- Suporte a caracteres acentuados
- Case-insensitive matching

### 3. Base de Conhecimento
- **Test_DPS_AI_Knowledge_Base**: Matching de artigos por keywords
- Ordenação por prioridade
- Suporte a múltiplos idiomas (pt-BR, en-US)
- Cenários sem resultados

### 4. Métricas e Custos
- **Test_DPS_AI_Analytics**: Cálculo de custos por modelo (GPT-4o, GPT-4o-mini, GPT-4-turbo)
- Conversão USD → BRL
- Arredondamento e valores extremos
- Tokens fracionários

### 5. Manutenção (Fase 1)
- **Test_DPS_AI_Maintenance**: Limpeza de métricas antigas
- Limpeza de feedback
- Limpeza de transients expirados
- Período de retenção configurável

### 6. Histórico de Conversas
- **Test_DPS_AI_Conversations_Repository**: Criação de conversas
- Adição de mensagens
- Recuperação de histórico por ID
- Suporte a múltiplos canais (web_chat, portal, whatsapp, admin_specialist)

### 7. Sistema de Prompts
- **Test_DPS_AI_Prompts**: Carregamento de prompts por contexto
- Cache de prompts
- Validação de contextos
- Fallback para contextos inválidos

## Como Executar os Testes

### Localmente

#### Pré-requisitos
- PHP 8.0 ou superior
- Composer instalado

#### Instalação de Dependências
```bash
cd plugins/desi-pet-shower-ai
composer install
```

#### Executar Todos os Testes
```bash
composer test
# ou
vendor/bin/phpunit
```

#### Executar Apenas Testes Unitários
```bash
vendor/bin/phpunit --testsuite "Unit Tests"
```

#### Executar Apenas Testes de Integração
```bash
vendor/bin/phpunit --testsuite "Integration Tests"
```

#### Executar Teste Específico
```bash
vendor/bin/phpunit tests/unit/Test_DPS_AI_Email_Parser.php
```

#### Executar com Cobertura de Código
```bash
composer test:coverage
# Abre: coverage/index.html
```

### No GitHub Actions

Os testes executam automaticamente em:
- Push para branches `main`, `develop`, ou `copilot/**`
- Pull Requests contra `main` ou `develop`

Workflow: `.github/workflows/phpunit.yml`

Testado em múltiplas versões PHP:
- PHP 8.0
- PHP 8.1
- PHP 8.2

## Estrutura de um Teste

```php
<?php
use PHPUnit\Framework\TestCase;

class Test_Exemplo extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        // Configuração antes de cada teste
    }
    
    public function test_exemplo_basico() {
        // Arrange (preparar)
        $input = 'valor';
        
        // Act (executar)
        $result = funcao_a_testar($input);
        
        // Assert (verificar)
        $this->assertEquals('esperado', $result);
    }
}
```

## Convenções

1. **Nomes de Arquivos**: `Test_Classe_Testada.php`
2. **Nomes de Métodos**: `test_descricao_do_comportamento()`
3. **Organização**: Um arquivo de teste por classe testada
4. **Assertions**: Use o assertion mais específico possível
   - `assertSame()` para igualdade estrita
   - `assertEquals()` para igualdade de valor
   - `assertStringContainsString()` para strings
   - `assertIsArray()`, `assertIsString()`, etc. para tipos

## Testes Marcados como Skipped/Incomplete

Alguns testes estão marcados com:
- `$this->markTestSkipped()`: Requer ambiente WordPress completo (DB, funções)
- `$this->markTestIncomplete()`: Aguardando refatoração de código para ser testável

Estes testes documentam o comportamento esperado e serão implementados conforme o código for refatorado para melhor testabilidade.

## Mocks e Fixtures

O arquivo `bootstrap.php` fornece mocks básicos de funções WordPress:
- `esc_html()`, `esc_attr()`, `esc_js()`
- `sanitize_text_field()`, `sanitize_textarea_field()`
- `get_option()`, `update_option()`
- `get_transient()`, `set_transient()`, `delete_transient()`
- Funções de log: `dps_ai_log_*`

## Adicionando Novos Testes

1. Crie arquivo em `tests/unit/` ou `tests/integration/`
2. Siga o padrão `Test_Nome_Classe.php`
3. Extenda `PHPUnit\Framework\TestCase`
4. Adicione testes com prefixo `test_`
5. Execute localmente para validar
6. Commit e push - CI executará automaticamente

## Referências

- [Documentação PHPUnit 9](https://phpunit.readthedocs.io/en/9.6/)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [AGENTS.md - Diretrizes de Teste](../../AGENTS.md)

---

**Última Atualização**: 07/12/2024  
**Versão do PHPUnit**: 9.6  
**Cobertura Atual**: ~40% (somente classes puras sem dependências WordPress)
