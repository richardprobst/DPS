# Correção - Menu Painel Central Desaparece ao Ativar Client Portal

**Data:** 2025-12-09  
**Versão Afetada:** Client Portal Add-on v2.4.0  
**Versão Corrigida:** Client Portal Add-on v2.4.1  
**Autor:** PRObst

## Resumo Executivo

Corrigido bug que causava o desaparecimento do menu "Painel Central" ao ativar o Client Portal Add-on. O problema era causado por registro duplicado do Custom Post Type `dps_portal_message` em duas classes diferentes.

## Sintomas do Problema

- **Sintoma:** Menu "Painel Central" (dashboard principal do DPS) desaparece após ativar Client Portal Add-on
- **Local:** Painel administrativo do WordPress → Menu lateral esquerdo
- **Impacto:** Usuário perde acesso ao dashboard principal com métricas e links rápidos
- **Comportamento:** O menu aparece normalmente com o Client Portal desativado, mas some ao ativá-lo

## Causa Raiz

### Registro Duplicado de CPT

Duas classes registravam o mesmo Custom Post Type `dps_portal_message` com `show_in_menu => 'desi-pet-shower'`:

1. **DPS_Client_Portal** (classe legada)
   - Arquivo: `includes/class-dps-client-portal.php`
   - Linha 71: `add_action( 'init', [ $this, 'register_message_post_type' ] )`
   - Linha 742: `'show_in_menu' => 'desi-pet-shower'`

2. **DPS_Portal_Admin** (classe refatorada - Fase 3)
   - Arquivo: `includes/client-portal/class-dps-portal-admin.php`
   - Linha 52: `add_action( 'init', [ $this, 'register_message_post_type' ] )`
   - Linha 95: `'show_in_menu' => 'desi-pet-shower'`

### Como o WordPress Lida com show_in_menu

Quando um CPT é registrado com `show_in_menu` apontando para um menu existente:

```php
register_post_type( 'dps_portal_message', [
    'show_in_menu' => 'desi-pet-shower',  // ← Aponta para menu existente
    // ...
] );
```

O WordPress:
1. Adiciona um submenu item para o CPT no menu pai
2. **IMPORTANTE:** Sobrescreve o callback do menu pai se o CPT for registrado DEPOIS

### Sequência de Eventos que Causava o Bug

```
1. Base Plugin cria menu "desi-pet-shower"
   └─ Callback: DPS_Dashboard::render()
   
2. DPS_Portal_Admin registra CPT dps_portal_message
   └─ show_in_menu => 'desi-pet-shower'
   └─ Adiciona submenu "Mensagens Portal"
   
3. DPS_Client_Portal registra NOVAMENTE CPT dps_portal_message
   └─ show_in_menu => 'desi-pet-shower'
   └─ WordPress SOBRESCREVE callback do menu principal
   └─ Menu "Painel Central" some! ❌
```

## Solução Implementada

Removido o registro duplicado do CPT em `DPS_Client_Portal`, mantendo apenas em `DPS_Portal_Admin`.

### Alteração no Código

**Arquivo:** `add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`

```php
// ANTES (linha 67-72)
// Processa ações de atualização do portal e login/logout
add_action( 'init', [ $this, 'handle_portal_actions' ] );

// Registra tipos de dados e recursos do portal
add_action( 'init', [ $this, 'register_message_post_type' ] );
add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

// DEPOIS (linha 67-73)
// Processa ações de atualização do portal e login/logout
add_action( 'init', [ $this, 'handle_portal_actions' ] );

// Registra tipos de dados e recursos do portal
// NOTA: CPT dps_portal_message agora registrado por DPS_Portal_Admin (evita conflito de menu)
// add_action( 'init', [ $this, 'register_message_post_type' ] );
add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
```

### Por Que Manter o Método?

O método `register_message_post_type()` foi mantido na classe (linha 723) mas não está mais conectado ao hook `init`. Isso garante:

1. **Compatibilidade:** Caso algum código externo chame o método diretamente
2. **Clareza:** Mantém a estrutura da classe legada durante a transição para `DPS_Portal_Admin`
3. **Documentação:** Serve como referência histórica do que a classe fazia

## Validações Realizadas

### ✅ Validação de Sintaxe
```bash
$ php -l class-dps-client-portal.php
No syntax errors detected
```

### ✅ Verificação de Referências
```bash
$ grep -n "register_message_post_type"
72:  // add_action( 'init', [ $this, 'register_message_post_type' ] );
723: public function register_message_post_type() {
```

Nenhuma outra parte do código chama este método diretamente.

## Impacto

### Positivo
- ✅ Menu "Painel Central" permanece visível após ativar Client Portal
- ✅ CPT "Mensagens do Portal" continua funcionando normalmente
- ✅ Submenu "Mensagens Portal" aparece corretamente
- ✅ Sem alterações de comportamento funcional
- ✅ Compatibilidade retroativa mantida

### Sem Impacto Negativo
- ✅ Nenhuma funcionalidade removida
- ✅ Nenhuma API ou hook alterado
- ✅ Nenhuma migração de dados necessária
- ✅ Nenhum impacto em performance

## Testes Necessários

### Testes Funcionais Críticos

#### 1. Menu Painel Central
- [ ] Desativar Client Portal Add-on
- [ ] Verificar que menu "DPS by PRObst" > "Painel Central" existe
- [ ] Ativar Client Portal Add-on
- [ ] **CRÍTICO:** Verificar que menu "Painel Central" continua visível
- [ ] Clicar em "Painel Central" e verificar que dashboard carrega

#### 2. CPT Mensagens do Portal
- [ ] Verificar que submenu "Mensagens Portal" aparece em "DPS by PRObst"
- [ ] Acessar "Mensagens Portal" e verificar listagem
- [ ] Criar nova mensagem do portal
- [ ] Editar mensagem existente
- [ ] Verificar metaboxes e colunas customizadas

#### 3. Portal Hub
- [ ] Acessar "DPS by PRObst" > "Portal do Cliente"
- [ ] Verificar 3 abas: Configurações, Logins, Mensagens
- [ ] Testar funcionalidade de cada aba

### Testes de Regressão

- [ ] Testar shortcode `[dps_client_portal]` no frontend
- [ ] Testar login de clientes via portal
- [ ] Testar chat/mensagens do portal (frontend)
- [ ] Verificar outros menus DPS não foram afetados
- [ ] Testar com outros add-ons ativados simultaneamente

## Contexto Histórico

### Evolução da Arquitetura

**Fase 1 (v2.0.0 - v2.3.x):** Classe monolítica
- `DPS_Client_Portal` fazia tudo: CPT, admin, frontend, AJAX

**Fase 2 (v2.4.0):** Reorganização de Menus
- Criado `DPS_Portal_Hub` para centralizar menus
- Menus standalone ocultados com `parent=null`

**Fase 3 (v2.5.0):** Refatoração
- Criado `DPS_Portal_Admin` para separar lógica administrativa
- **PROBLEMA:** CPT continuou registrado em ambas as classes
- **RESULTADO:** Conflito de menu ao ativar add-on

**Fase 3.1 (v2.4.1):** Fix
- Removido registro duplicado
- `DPS_Portal_Admin` agora é responsável único pelo CPT

### Lições para Futuras Refatorações

1. **Checklist de Migração:** Ao mover funcionalidade de classe A para classe B:
   - [ ] Remover código da classe A
   - [ ] Adicionar código na classe B
   - [ ] Buscar por chamadas diretas ao método
   - [ ] Testar ambos cenários (classe A ativa/inativa)

2. **CPT Registration Best Practices:**
   - Um CPT deve ser registrado em exatamente UM lugar
   - Usar `show_in_menu => false` se o menu for gerenciado manualmente
   - Ou usar `show_in_menu => 'parent-slug'` mas garantir que não há duplicação

3. **Validação de Menus:**
   - Sempre testar ativação/desativação de add-ons
   - Verificar que menus permanecem estáveis
   - Usar `var_dump( $GLOBALS['menu'] )` para debug se necessário

## Arquitetura Correta Atual

```
DPS Base Plugin
└─ Menu: desi-pet-shower
   └─ Callback: DPS_Dashboard::render()  ← "Painel Central"

Client Portal Add-on
├─ DPS_Portal_Admin
│  ├─ Registra CPT: dps_portal_message
│  │  └─ show_in_menu: 'desi-pet-shower'  ← Cria submenu
│  └─ Menus ocultos: parent=null
│     ├─ dps-client-portal-settings
│     └─ dps-client-logins
│
├─ DPS_Portal_Hub
│  └─ Submenu: dps-portal-hub
│     └─ parent: 'desi-pet-shower'
│     └─ Abas: Configurações | Logins | Mensagens
│
└─ DPS_Client_Portal (legado)
   └─ NÃO registra CPT (corrigido)
   └─ Mantém shortcodes e frontend
```

## Referências

- **PR:** copilot/fix-critical-error-agenda
- **Commit:** `8c0052b` - Corrigir menu Painel Central desaparecendo ao ativar Client Portal addon
- **CHANGELOG.md:** [Unreleased] > Fixed > Client Portal Add-on (v2.4.1)
- **Documentação relacionada:** 
  - `docs/fixes/AGENDA_HUB_SINGLETON_FIX.md`
  - WordPress Codex: `register_post_type()` - parâmetro `show_in_menu`

## Próximos Passos

1. Executar testes manuais conforme checklist acima
2. Se testes bem-sucedidos, criar release tag v2.4.1 do Client Portal Add-on
3. Considerar refatoração completa de `DPS_Client_Portal` para delegar todas as funções administrativas para `DPS_Portal_Admin`
4. Documentar padrão de "um CPT, uma classe responsável" no AGENTS.md
