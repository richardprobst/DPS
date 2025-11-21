# Desi Pet Shower – Groomers Add-on

Cadastro de profissionais, vinculação a atendimentos e relatórios de produtividade.

## Visão geral

O **Groomers Add-on** permite cadastrar e gerenciar profissionais de banho e tosa (groomers), vincular cada atendimento a um profissional específico e gerar relatórios de produtividade individual. É ideal para pet shops com múltiplos funcionários que precisam acompanhar desempenho e distribuição de trabalho.

Funcionalidades principais:
- Cadastro de profissionais via role customizada do WordPress
- Vinculação de atendimentos a groomers específicos
- Relatórios de produtividade por profissional
- Listagem de atendimentos por groomer
- Interface integrada ao painel principal do sistema

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-groomers_addon/`
- **Slug**: `dps-groomers-addon`
- **Classe principal**: `DPS_Groomers_Addon`
- **Arquivo principal**: `desi-pet-shower-groomers-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Versão atual**: v1.0.0
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Gestão de groomers
- **Cadastro via WordPress**: groomers são usuários WordPress com role `dps_groomer`
- **Listagem de profissionais**: interface para visualizar todos os groomers cadastrados
- **Edição de dados**: permite atualizar informações do profissional via painel de usuários do WordPress
- **Exclusão**: desativar ou excluir profissionais conforme necessário

### Vinculação a atendimentos
- **Campo de seleção**: adiciona dropdown de seleção de groomer no formulário de agendamento
- **Metadado `_groomer_id`**: armazena ID do profissional vinculado ao agendamento
- **Histórico**: permite rastrear quais profissionais realizaram cada atendimento

### Relatórios de produtividade
- **Atendimentos por groomer**: lista todos os agendamentos concluídos por profissional
- **Período configurável**: filtrar relatórios por data (dia, semana, mês)
- **Métricas**: total de atendimentos, receita gerada (se Finance Add-on estiver ativo)
- **Exportação**: gerar relatórios em CSV ou PDF

## Shortcodes, widgets e endpoints

### Shortcodes
Este add-on não expõe shortcodes próprios. Opera através de interface integrada ao painel base.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

#### `dps_base_nav_tabs_after_history` (action)
- **Propósito**: adicionar aba "Groomers" à navegação do painel base
- **Parâmetros**: `$visitor_only` (bool)
- **Implementação**: renderiza tab após aba "Histórico"

#### `dps_base_sections_after_history` (action)
- **Propósito**: renderizar conteúdo da seção de groomers
- **Parâmetros**: `$active_tab` (string)
- **Implementação**: exibe cadastro, listagem e relatórios

#### `dps_base_appointment_fields` (action)
- **Propósito**: adicionar campo de seleção de groomer no formulário de agendamento
- **Parâmetros**: `$appointment_id` (int), `$is_edit` (bool)
- **Implementação**: renderiza dropdown com lista de groomers

#### `dps_base_after_save_appointment` (action)
- **Propósito**: salvar vinculação de groomer ao salvar agendamento
- **Parâmetros**: `$appointment_id` (int)
- **Implementação**: salva metadado `_groomer_id` no post de agendamento

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios.

### Role customizada

#### `dps_groomer`
Role criada na ativação do plugin para identificar profissionais de banho e tosa.

**Capabilities**:
- `read`: true (acesso básico ao painel WordPress)

**Uso**: Administradores criam usuários WordPress e atribuem role `dps_groomer` para profissionais.

### Metadados utilizados

#### Em agendamentos (`dps_appointment`)
- **`_groomer_id`**: ID do usuário WordPress do groomer vinculado ao atendimento

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas
Este add-on não armazena options globais.

## Como usar (visão funcional)

### Para administradores

1. **Cadastrar groomer**:
   - Acesse "Usuários" > "Adicionar Novo" no painel WordPress
   - Preencha dados do profissional (nome, e-mail, senha)
   - Selecione role "Groomer DPS"
   - Salve usuário

2. **Vincular groomer a atendimento**:
   - No painel base, ao criar/editar agendamento
   - Localize campo "Groomer Responsável" (adicionado por este add-on)
   - Selecione o profissional no dropdown
   - Salve agendamento

3. **Visualizar relatórios**:
   - No painel base, clique na aba "Groomers"
   - Selecione profissional na lista
   - Defina período (data inicial e final)
   - Visualize lista de atendimentos realizados
   - Clique em "Exportar" para baixar relatório

4. **Gerenciar groomers**:
   - Na aba "Groomers", visualize lista de todos os profissionais
   - Edite dados clicando no nome (redireciona para painel de usuários)
   - Desative ou exclua profissionais conforme necessário

### Para recepcionistas

- Ao criar agendamentos, selecione groomer disponível no campo dedicado
- Sistema salva automaticamente a vinculação

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, hooks consumidos

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hooks de formulário de agendamento
2. **Implementar** seguindo políticas de segurança (nonces, capabilities, sanitização)
3. **Atualizar ANALYSIS.md** se criar novos hooks ou metadados
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Validar** fluxo completo de cadastro, vinculação e relatórios

### Políticas de segurança

- ✅ **Role management**: verificar capabilities antes de exibir/salvar dados
- ✅ **Sanitização**: sanitizar `_groomer_id` antes de salvar metadado
- ✅ **Validação**: verificar se ID de groomer existe antes de vincular
- ✅ **Escape**: escapar saída em listagens e relatórios

### Oportunidades de refatoração

**ANALYSIS.md** indica que este add-on é candidato a refatoração:
- **Arquivo único**: atualmente 473 linhas em um único arquivo
- **Estrutura recomendada**: migrar para padrão modular com `includes/` e `assets/`
- **Classes separadas**: extrair lógica de relatórios, formulários e listagem para classes próprias

Consulte **[../docs/refactoring/REFACTORING_ANALYSIS.md](../docs/refactoring/REFACTORING_ANALYSIS.md)** para detalhes e **refactoring-examples.php** para padrões recomendados.

### Pontos de atenção

- **Exclusão de groomer**: verificar impacto em agendamentos já vinculados (mantém `_groomer_id` ou limpa?)
- **Capability customizada**: considerar criar `dps_manage_groomers` ao invés de reutilizar `manage_options`
- **Relatórios financeiros**: integração opcional com Finance Add-on para exibir receita por groomer

### Melhorias futuras sugeridas

- Dashboard individual por groomer (acessível ao próprio profissional)
- Comissões automáticas baseadas em atendimentos/receita
- Agenda individual por groomer (filtro de disponibilidade)
- Integração com controle de ponto
- Metas de produtividade e gamificação

## Histórico de mudanças (resumo)

### Principais marcos

- **v1.0.0**: Lançamento inicial com cadastro de groomers via role customizada, vinculação a atendimentos e relatórios básicos de produtividade

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
