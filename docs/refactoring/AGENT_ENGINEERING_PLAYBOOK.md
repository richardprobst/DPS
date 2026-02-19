# Playbook de engenharia para agentes (Core + Add-ons)

Este documento complementa o `AGENTS.md` da raiz com diretrizes práticas para implementação e refatoração.
Objetivo: manter código limpo, sustentável e escalável para o plugin base e add-ons, preservando compatibilidade com WordPress.

## Princípios de implementação

1. **Clareza > esperteza**: o código deve ser óbvio para quem mantém.
2. **KISS e YAGNI**: sem abstrações sem uso real.
3. **DRY com bom senso**: evitar duplicação de regra de negócio sem criar acoplamento indevido entre add-ons.
4. **Extensibilidade**: core pequeno e estável; add-ons integram por contratos (hooks/interfaces).
5. **Segurança e performance**: tratadas como requisitos de entrega.

## Regras arquiteturais

- Regra de negócio não deve ficar em callbacks de hooks/shortcodes.
- O core deve expor contratos estáveis por:
  - interfaces PHP (uso interno), e/ou
  - actions/filters (extensões/add-ons).
- Add-ons não devem acessar internals do core por caminhos não oficiais.
- Dependências explícitas por construtor/factory; evitar espalhar singletons/globais.

## Regras WordPress (execução)

- Sempre aplicar:
  - capability checks em rotas/admin actions;
  - nonce em formulários/admin actions;
  - sanitize/validate de input e escape de output;
  - `$wpdb->prepare()` em SQL.
- Evitar:
  - consultas em loop (N+1);
  - uso indiscriminado de `wp_postmeta` para dados relacionais complexos.
- REST:
  - endpoints paginados;
  - autorização explícita;
  - payload validado.

## Padrões de código

- Nomes descritivos, sem abreviações obscuras.
- Funções pequenas, com responsabilidade única (SRP).
- Evitar “classes Deus”.
- Preferir early returns para reduzir aninhamento.
- Comentários explicam o **porquê**, não o **o quê**.

## Definition of Done (DoD)

Uma alteração é considerada pronta quando, conforme aplicável ao escopo:

- Passa em lint/PHPCS (WordPress standards) e análise estática (PHPStan no nível acordado).
- Mantém compatibilidade com versões de PHP/WordPress suportadas pelo projeto.
- Inclui testes unitários para regra de negócio (quando aplicável).
- Inclui teste de integração para endpoints/repositórios críticos (quando aplicável).
- Inclui log adequado para falhas relevantes.
- Não introduz regressões de performance perceptíveis (ex.: N+1, queries não indexáveis).
- Atualiza docs/README quando houver novo contrato/hook exposto.

## Processo recomendado para agentes

Antes de codar:
- resumir intenção e impacto (arquitetura/dados/API);
- quebrar em mudanças pequenas e revisáveis.

Ao encerrar:
- registrar trade-offs e alternativas consideradas, quando houver;
- se alguma regra precisar ser violada por motivo técnico, justificar no PR.

## Regra para demandas visuais (M3)

Quando a tarefa envolver UI, frontend ou layout:
- seguir obrigatoriamente as referências em `docs/visual/`;
- tratar `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` como fonte de verdade do padrão visual M3;
- declarar na resposta/PR que as orientações M3 foram aplicadas;
- documentar a mudança com resumo de antes/depois e arquivos impactados;
- capturar prints completos das telas alteradas e salvar em `docs/screenshots/YYYY-MM-DD/`;
- informar no fechamento/PR o caminho exato onde os registros e prints foram salvos.

## Validação recomendada por tipo de mudança

- **Documentação apenas:** `git diff --check` e revisão de apontamentos/paths citados.
- **PHP alterado:** executar `php -l` por arquivo modificado.
- **Mudança funcional:** validar fluxo crítico no WordPress local.
- **Mudança visual (M3):** validar aderência com `docs/visual/` + registrar prints em `docs/screenshots/YYYY-MM-DD/`.

## Checklist rápido de fechamento

- Confirmar escopo da trilha (A ou B) e impactos.
- Listar testes/comandos executados e resultado.
- Informar trade-offs relevantes (quando houver).
- Para tarefas visuais: citar o caminho do documento e dos screenshots salvos.

## Conflitos e precedência

- Em conflito, prevalecem as regras da raiz em `AGENTS.md` (MUST / ASK BEFORE / segurança).
- Este playbook funciona como guia complementar para decisões de implementação.

---

## Padrão de dbDelta e Versionamento de Tabelas (Fase 3.1)

Toda criação ou alteração de tabela customizada DEVE seguir este padrão:

```php
public static function maybe_create_tables() {
    $db_version = '1.0.0'; // Incrementar ao alterar schema
    $option_key = 'dps_{addon}_db_version';
    $installed  = get_option( $option_key, '' );

    // Guard: só executa dbDelta se a versão mudou
    if ( $installed === $db_version ) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name      = $wpdb->prefix . 'dps_{nome}';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // IMPORTANTE: dbDelta() exige formatação estrita:
    // - 2 espaços entre 'PRIMARY KEY' e '(' (não 1)
    // - Usar 'KEY' em vez de 'INDEX' para índices secundários
    // Ref: https://developer.wordpress.org/reference/functions/dbdelta/
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ...
        PRIMARY KEY  (id),
        KEY some_index (column)
    ) {$charset_collate};";

    dbDelta( $sql );
    update_option( $option_key, $db_version );
}
```

### Regras:
1. **Sempre** usar `get_option()` com version check antes de `dbDelta()`
2. **Sempre** chamar `update_option()` após `dbDelta()` bem-sucedido
3. Incrementar a versão ao alterar schema (novas colunas, índices, etc.)
4. Migrações de dados devem estar em blocos `version_compare()` separados
5. DDL queries (ALTER TABLE, CREATE INDEX) usam `$wpdb->prefix` direto — são seguras por não receberem input do usuário

---

## Padrão de Injeção de Dependência (Fase 7.3)

O projeto utiliza duas estratégias de instanciação, escolhidas conforme o caso:

### 1. Singleton (classes utilitárias e repositórios)

Usado para classes sem estado mutável ou com estado compartilhado (repositórios, helpers):

```php
class DPS_Appointment_Repository {
    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}
}
```

**Quando usar:** Repositories, Helpers (Money, Phone, URL), Template Engine, Suggestion Services.

### 2. Constructor Injection (classes com dependências substituíveis)

Usado no Frontend Add-on para handlers que precisam de serviços injetáveis (testabilidade, substituição):

```php
class DPS_Registration_Handler {
    public function __construct(
        DPS_Form_Validator $formValidator,
        DPS_Client_Service $clientService,
        DPS_Pet_Service $petService,
        DPS_Duplicate_Detector $duplicateDetector,
        DPS_Recaptcha_Service $recaptchaService,
        DPS_Email_Service $emailService,
        DPS_Registration_Bridge $registrationBridge,
        DPS_Logger $logger
    ) {
        $this->formValidator = $formValidator;
        // ... demais atribuições
    }
}
```

**Composição root** (no arquivo principal do plugin):
```php
$formValidator = new DPS_Form_Validator();
$clientService = new DPS_Client_Service();
// ... instancia serviços
$handler = new DPS_Registration_Handler(
    $formValidator, $clientService, ...
);
$registrationV2->setHandler( $handler );
```

**Quando usar:** Handlers com lógica complexa que se beneficiam de substituição em testes, ou quando há múltiplas implementações possíveis.

### 3. Renderers estáticos (classes do base plugin)

Os section renderers (`DPS_Clients_Section_Renderer`, `DPS_Pets_Section_Renderer`, etc.) usam métodos estáticos por serem puros (dados in, HTML out) e não precisarem de estado ou substituição:

```php
class DPS_Clients_Section_Renderer {
    public static function render() { ... }
    public static function prepare_data() { ... }
}
```

A facade `DPS_Base_Frontend` delega para eles:
```php
public static function render_clients_section() {
    return DPS_Clients_Section_Renderer::render();
}
```

**Trade-off:** Métodos estáticos não são facilmente mockáveis em testes unitários, mas a simplicidade compensa para renderers puros. Se no futuro for necessário testar renderers com mock de dados, converter para instâncias com DI.

### Regras:
1. **Preferir singleton** para classes utilitárias sem dependências externas
2. **Preferir constructor injection** para handlers com lógica de negócio e múltiplas dependências
3. **Composição root** no arquivo principal do plugin (não instanciar serviços dentro de handlers)
4. **Não usar service locator** (anti-pattern) — dependências devem ser explícitas
5. Renderers estáticos são aceitáveis quando puros (sem side effects além de HTML output)
