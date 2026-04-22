# Playbook de engenharia para agentes (Core + Add-ons)

Este documento complementa o `AGENTS.md` da raiz com diretrizes prÃ¡ticas para implementaÃ§Ã£o e refatoraÃ§Ã£o.
Objetivo: manter cÃ³digo limpo, sustentÃ¡vel e escalÃ¡vel para o plugin base e add-ons, preservando compatibilidade com WordPress.

## PrincÃ­pios de implementaÃ§Ã£o

1. **Clareza > esperteza**: o cÃ³digo deve ser Ã³bvio para quem mantÃ©m.
2. **KISS e YAGNI**: sem abstraÃ§Ãµes sem uso real.
3. **DRY com bom senso**: evitar duplicaÃ§Ã£o de regra de negÃ³cio sem criar acoplamento indevido entre add-ons.
4. **Extensibilidade**: core pequeno e estÃ¡vel; add-ons integram por contratos (hooks/interfaces).
5. **SeguranÃ§a e performance**: tratadas como requisitos de entrega.

## Regras arquiteturais

- Regra de negÃ³cio nÃ£o deve ficar em callbacks de hooks/shortcodes.
- O core deve expor contratos estÃ¡veis por:
  - interfaces PHP (uso interno), e/ou
  - actions/filters (extensÃµes/add-ons).
- Add-ons nÃ£o devem acessar internals do core por caminhos nÃ£o oficiais.
- DependÃªncias explÃ­citas por construtor/factory; evitar espalhar singletons/globais.

## Regras WordPress (execuÃ§Ã£o)

- Sempre aplicar:
  - capability checks em rotas/admin actions;
  - nonce em formulÃ¡rios/admin actions;
  - sanitize/validate de input e escape de output;
  - `$wpdb->prepare()` em SQL.
- Evitar:
  - consultas em loop (N+1);
  - uso indiscriminado de `wp_postmeta` para dados relacionais complexos.
- REST:
  - endpoints paginados;
  - autorizaÃ§Ã£o explÃ­cita;
  - payload validado.

## PadrÃµes de cÃ³digo

- Nomes descritivos, sem abreviaÃ§Ãµes obscuras.
- FunÃ§Ãµes pequenas, com responsabilidade Ãºnica (SRP).
- Evitar â€œclasses Deusâ€.
- Preferir early returns para reduzir aninhamento.
- ComentÃ¡rios explicam o **porquÃª**, nÃ£o o **o quÃª**.

## Definition of Done (DoD)

Uma alteraÃ§Ã£o Ã© considerada pronta quando, conforme aplicÃ¡vel ao escopo:

- Passa em lint/PHPCS (WordPress standards) e anÃ¡lise estÃ¡tica (PHPStan no nÃ­vel acordado).
- MantÃ©m compatibilidade com versÃµes de PHP/WordPress suportadas pelo projeto.
- Inclui testes unitÃ¡rios para regra de negÃ³cio (quando aplicÃ¡vel).
- Inclui teste de integraÃ§Ã£o para endpoints/repositÃ³rios crÃ­ticos (quando aplicÃ¡vel).
- Inclui log adequado para falhas relevantes.
- NÃ£o introduz regressÃµes de performance perceptÃ­veis (ex.: N+1, queries nÃ£o indexÃ¡veis).
- Atualiza docs/README quando houver novo contrato/hook exposto.

## Processo recomendado para agentes

Antes de codar:
- resumir intenÃ§Ã£o e impacto (arquitetura/dados/API);
- quebrar em mudanÃ§as pequenas e revisÃ¡veis.

Ao encerrar:
- registrar trade-offs e alternativas consideradas, quando houver;
- se alguma regra precisar ser violada por motivo tÃ©cnico, justificar no PR.

## Regra para demandas visuais (DPS Signature)

Quando a tarefa envolver UI, frontend ou layout:
- seguir obrigatoriamente as referÃªncias em `docs/visual/`;
- tratar `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` e `docs/visual/VISUAL_STYLE_GUIDE.md` como fonte de verdade do padrÃ£o visual DPS Signature;
- declarar na resposta/PR que as orientaÃ§Ãµes DPS Signature foram aplicadas;
- documentar a mudanÃ§a com resumo de antes/depois e arquivos impactados;
- capturar prints completos das telas alteradas e salvar em `docs/screenshots/YYYY-MM-DD/`;
- informar no fechamento/PR o caminho exato onde os registros e prints foram salvos.

## ValidaÃ§Ã£o recomendada por tipo de mudanÃ§a

- **DocumentaÃ§Ã£o apenas:** `git diff --check` e revisÃ£o de apontamentos/paths citados.
- **PHP alterado:** executar `php -l` por arquivo modificado.
- **MudanÃ§a funcional:** validar fluxo crÃ­tico no WordPress local.
- **MudanÃ§a visual (DPS Signature):** validar aderÃªncia com `docs/visual/` + registrar prints em `docs/screenshots/YYYY-MM-DD/`.

## Checklist rÃ¡pido de fechamento

- Confirmar escopo da trilha (A ou B) e impactos.
- Listar testes/comandos executados e resultado.
- Informar trade-offs relevantes (quando houver).
- Para tarefas visuais: citar o caminho do documento e dos screenshots salvos.

## Conflitos e precedÃªncia

- Em conflito, prevalecem as regras da raiz em `AGENTS.md` (MUST / ASK BEFORE / seguranÃ§a).
- Este playbook funciona como guia complementar para decisÃµes de implementaÃ§Ã£o.

---

## PadrÃ£o de dbDelta e Versionamento de Tabelas (Fase 3.1)

Toda criaÃ§Ã£o ou alteraÃ§Ã£o de tabela customizada DEVE seguir este padrÃ£o:

```php
public static function maybe_create_tables() {
    $db_version = '1.0.0'; // Incrementar ao alterar schema
    $option_key = 'dps_{addon}_db_version';
    $installed  = get_option( $option_key, '' );

    // Guard: sÃ³ executa dbDelta se a versÃ£o mudou
    if ( $installed === $db_version ) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name      = $wpdb->prefix . 'dps_{nome}';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // IMPORTANTE: dbDelta() exige formataÃ§Ã£o estrita:
    // - 2 espaÃ§os entre 'PRIMARY KEY' e '(' (nÃ£o 1)
    // - Usar 'KEY' em vez de 'INDEX' para Ã­ndices secundÃ¡rios
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
2. **Sempre** chamar `update_option()` apÃ³s `dbDelta()` bem-sucedido
3. Incrementar a versÃ£o ao alterar schema (novas colunas, Ã­ndices, etc.)
4. MigraÃ§Ãµes de dados devem estar em blocos `version_compare()` separados
5. DDL queries (ALTER TABLE, CREATE INDEX) usam `$wpdb->prefix` direto â€” sÃ£o seguras por nÃ£o receberem input do usuÃ¡rio

---

## PadrÃ£o de InjeÃ§Ã£o de DependÃªncia (Fase 7.3)

O projeto utiliza duas estratÃ©gias de instanciaÃ§Ã£o, escolhidas conforme o caso:

### 1. Singleton (classes utilitÃ¡rias e repositÃ³rios)

Usado para classes sem estado mutÃ¡vel ou com estado compartilhado (repositÃ³rios, helpers):

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

### 2. Constructor Injection (classes com dependÃªncias substituÃ­veis)

Usado no Frontend Add-on para handlers que precisam de serviÃ§os injetÃ¡veis (testabilidade, substituiÃ§Ã£o):

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
        // ... demais atribuiÃ§Ãµes
    }
}
```

**ComposiÃ§Ã£o root** (no arquivo principal do plugin):
```php
$formValidator = new DPS_Form_Validator();
$clientService = new DPS_Client_Service();
// ... instancia serviÃ§os
$handler = new DPS_Registration_Handler(
    $formValidator, $clientService, ...
);
$registrationV2->setHandler( $handler );
```

**Quando usar:** Handlers com lÃ³gica complexa que se beneficiam de substituiÃ§Ã£o em testes, ou quando hÃ¡ mÃºltiplas implementaÃ§Ãµes possÃ­veis.

### 3. Renderers estÃ¡ticos (classes do base plugin)

Os section renderers (`DPS_Clients_Section_Renderer`, `DPS_Pets_Section_Renderer`, etc.) usam mÃ©todos estÃ¡ticos por serem puros (dados in, HTML out) e nÃ£o precisarem de estado ou substituiÃ§Ã£o:

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

**Trade-off:** MÃ©todos estÃ¡ticos nÃ£o sÃ£o facilmente mockÃ¡veis em testes unitÃ¡rios, mas a simplicidade compensa para renderers puros. Se no futuro for necessÃ¡rio testar renderers com mock de dados, converter para instÃ¢ncias com DI.

### Regras:
1. **Preferir singleton** para classes utilitÃ¡rias sem dependÃªncias externas
2. **Preferir constructor injection** para handlers com lÃ³gica de negÃ³cio e mÃºltiplas dependÃªncias
3. **ComposiÃ§Ã£o root** no arquivo principal do plugin (nÃ£o instanciar serviÃ§os dentro de handlers)
4. **NÃ£o usar service locator** (anti-pattern) â€” dependÃªncias devem ser explÃ­citas
5. Renderers estÃ¡ticos sÃ£o aceitÃ¡veis quando puros (sem side effects alÃ©m de HTML output)
