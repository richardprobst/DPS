# DPS by PRObst – Portal do Cliente Add-on

Área autenticada para clientes consultarem histórico, atualizarem dados e efetuarem pagamentos.

## Visão geral

O **Portal do Cliente Add-on** fornece uma interface web completa para que clientes do pet shop possam acessar suas informações, consultar histórico de atendimentos, visualizar galeria de fotos dos pets, verificar pendências financeiras e atualizar dados cadastrais de forma autônoma.

Funcionalidades principais:
- Autenticação via login padrão do WordPress (usuário/senha)
- Criação automática de credenciais para novos clientes
- Histórico completo de atendimentos do cliente
- Galeria de fotos dos atendimentos
- Visualização de pendências financeiras
- Atualização de dados pessoais e de pets
- Integração com Mercado Pago para pagamento de pendências
- Exibição de código de indicação (Indique e Ganhe) se add-on Loyalty estiver ativo

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-client-portal_addon/`
- **Slug**: `dps-client-portal`
- **Classe principal**: `DPS_Client_Portal`
- **Arquivo principal**: `desi-pet-shower-client-portal.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **DPS by PRObst Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Finance Add-on**: para exibir pendências financeiras e valores em aberto
- **Payment Add-on**: para gerar links de pagamento via Mercado Pago
- **Loyalty Add-on**: para exibir código e link de indicação (Indique e Ganhe)

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Versão atual**: v1.0.0
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Autenticação e segurança
- **Login WordPress**: utiliza sistema nativo de autenticação do WordPress
- **Criação automática de usuários**: gera credenciais para clientes ao salvá-los no painel administrativo
- **Associação cliente-usuário**: vincula usuário WordPress ao CPT `dps_client` via metadado
- **Role customizada**: clientes recebem role `dps_customer` com permissões limitadas
- **Sessões PHP**: gerenciamento adicional de sessão para dados do cliente logado

### Visualização de dados
- **Histórico de atendimentos**: lista completa de agendamentos concluídos do cliente
- **Detalhes de atendimentos**: data, horário, pets atendidos, serviços realizados, valores
- **Galeria de fotos**: exibe imagens de antes/depois dos pets (se disponíveis)
- **Dados cadastrais**: exibe nome, telefone, e-mail, endereço do cliente
- **Dados dos pets**: lista todos os pets do cliente com espécie, raça, porte

### Atualização de dados
- **Formulário de edição**: permite cliente atualizar telefone, e-mail, endereço
- **Edição de pets**: permite atualizar dados dos pets (nome, raça, observações)
- **Validação**: sanitização e validação de campos antes de salvar
- **Feedback visual**: mensagens de sucesso/erro após operações

### Financeiro (requer Finance Add-on)
- **Pendências em aberto**: exibe lista de cobranças não pagas
- **Valores detalhados**: mostra valor total, parcelas, datas de vencimento
- **Links de pagamento**: botões para pagar via Mercado Pago (requer Payment Add-on)

### Programa de indicação (requer Loyalty Add-on)
- **Código único**: exibe código de indicação pessoal do cliente
- **Link compartilhável**: URL de cadastro com código pré-preenchido
- **Benefícios**: explica recompensas por indicações

## Shortcodes, widgets e endpoints

### Shortcodes

#### `[dps_client_portal]`
Renderiza o portal completo do cliente com todas as funcionalidades.

**Uso**:
```
[dps_client_portal]
```

**Descrição**: Exibe interface de login (se não autenticado) ou painel completo do cliente (se autenticado) com abas de histórico, galeria, dados pessoais, pets e pendências financeiras.

**Parâmetros**: Nenhum.

**Permissões**: Usuário deve estar autenticado como cliente (role `dps_customer` ou associado a um `dps_client`).

**Exemplo de página**:
Crie uma página "Portal do Cliente" e insira o shortcode para que clientes acessem sua área.

---

#### `[dps_client_login]`
Exibe apenas o formulário de login.

**Uso**:
```
[dps_client_login]
```

**Descrição**: Renderiza formulário de login padrão do WordPress personalizado para clientes.

**Parâmetros**: Nenhum.

**Redirect**: Após login, redireciona para página do portal (se configurada).

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

Este add-on consome hooks de outros add-ons quando disponíveis:

#### Hooks do Payment Add-on (opcional)
- Busca funções de geração de link de pagamento Mercado Pago via `function_exists()`
- Exibe botões de pagamento se Payment Add-on estiver ativo

#### Hooks do Loyalty Add-on (opcional)
- Busca dados de código de indicação via functions do Loyalty
- Exibe seção "Indique e Ganhe" se add-on estiver ativo

### Hooks DISPARADOS por este add-on

Este add-on não dispara hooks customizados próprios; opera de forma autônoma.

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios. Utiliza CPTs do plugin base:
- **`dps_client`**: busca dados do cliente logado
- **`dps_pet`**: lista pets vinculados ao cliente
- **`dps_appointment`**: exibe histórico de agendamentos

### Metadados utilizados

#### Em clientes (`dps_client`)
- **`client_user_id`**: ID do usuário WordPress associado ao cliente
- **`client_phone`**: telefone do cliente
- **`client_email`**: e-mail do cliente
- **`client_address`**: endereço completo

#### Em pets (`dps_pet`)
- **`pet_client_id`**: ID do cliente proprietário

### Tabelas customizadas
Este add-on não cria tabelas próprias. Consome tabela `dps_transacoes` do Finance Add-on se disponível.

### Options armazenadas
Este add-on não armazena options globais.

### Sessões PHP
- Usa `$_SESSION['dps_client_id']` para armazenar ID do cliente logado durante navegação no portal

## Como usar (visão funcional)

### Para clientes

1. **Acessar o portal**:
   - Navegue até a página com o shortcode `[dps_client_portal]`
   - Se não autenticado, será exibido formulário de login

2. **Fazer login**:
   - Insira usuário e senha recebidos do pet shop
   - Clique em "Entrar"
   - Será redirecionado para painel do portal

3. **Consultar histórico**:
   - Aba "Histórico" exibe todos os atendimentos realizados
   - Clique em atendimento para ver detalhes (data, pets, serviços, valores)

4. **Visualizar galeria**:
   - Aba "Galeria" mostra fotos de antes/depois dos pets
   - Navegue entre imagens

5. **Atualizar dados pessoais**:
   - Aba "Meus Dados" permite editar telefone, e-mail, endereço
   - Clique em "Salvar Alterações"

6. **Gerenciar pets**:
   - Aba "Meus Pets" lista todos os pets cadastrados
   - Edite informações de raça, observações, etc.

7. **Verificar pendências** (se Finance Add-on ativo):
   - Aba "Pendências" exibe cobranças em aberto
   - Clique em "Pagar" para gerar link de pagamento Mercado Pago

8. **Compartilhar código de indicação** (se Loyalty Add-on ativo):
   - Seção "Indique e Ganhe" exibe código único
   - Copie link para compartilhar com amigos

### Para administradores

1. **Configurar página do portal**:
   - Acesse a aba "Configurações" do DPS
   - Clique na aba "Portal"
   - Selecione a página onde o shortcode `[dps_client_portal]` está inserido
   - Salve as configurações
   - Copie o link do portal se necessário

2. **Gerar links de acesso para clientes**:
   - Acesse a aba "Logins de Clientes" nas configurações
   - Busque o cliente desejado
   - Clique em "Primeiro Acesso" (para novos clientes) ou "Gerar Novo Link"
   - O link gerado é válido por 30 minutos
   - Envie por WhatsApp ou e-mail usando os botões disponíveis

3. **Revogar acessos**:
   - Na aba "Logins de Clientes", clique em "Revogar" para invalidar links ativos
   - Use quando o cliente reportar perda de acesso ou por segurança

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: arquitetura, estrutura modular com `includes/` e `assets/`

### Estrutura de arquivos

Este add-on já segue o padrão modular recomendado:
- **`includes/`**: classes auxiliares (ex.: `class-dps-client-portal.php`)
- **`assets/`**: CSS e JS do portal
- Arquivo principal apenas faz bootstrapping

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender integrações com Finance, Payment e Loyalty
2. **Implementar** seguindo políticas de segurança (autenticação, escape, sanitização)
3. **Atualizar ANALYSIS.md** se criar novas integrações ou hooks
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Testar** fluxo completo de login, visualização e atualização

### Políticas de segurança

- ✅ **Autenticação obrigatória**: verifica login antes de exibir qualquer dado
- ✅ **Isolamento de dados**: cliente só acessa seus próprios dados
- ✅ **Nonces validados**: formulários de edição protegidos contra CSRF
- ✅ **Sanitização**: todos os inputs sanitizados antes de salvar
- ✅ **Escape**: saída escapada em templates
- ⚠️ **Sessões PHP**: usar com cuidado; considerar migrar para user meta

### Integração com outros add-ons

#### Finance Add-on (opcional)
- Verifica existência de tabela `dps_transacoes`
- Busca pendências via query direta ou funções do Finance
- Exibe aba "Pendências" apenas se Finance estiver ativo

#### Payment Add-on (opcional)
- Verifica `function_exists('dps_generate_mp_link')` ou similar
- Gera links de pagamento via funções do Payment
- Botões de pagamento aparecem apenas se Payment estiver ativo

#### Loyalty Add-on (opcional)
- Verifica `function_exists('dps_get_referral_code')` ou similar
- Busca código de indicação do cliente
- Exibe seção "Indique e Ganhe" apenas se Loyalty estiver ativo

### Pontos de atenção

- **Criação automática de usuários**: valide que e-mail não está em uso antes de criar
- **Senhas temporárias**: use `wp_generate_password()` e notifique cliente por e-mail
- **Sessões PHP**: considerar usar user meta do WordPress ao invés de `$_SESSION`
- **Performance**: cache de queries pesadas de histórico e galeria
- **Responsividade**: interface deve funcionar bem em mobile

## Histórico de mudanças (resumo)

### Principais marcos

- **v2.1.0**: Adicionada configuração centralizada da página do portal
  - Nova aba "Portal" nas configurações para selecionar página do portal
  - Funções helper `dps_get_portal_page_url()` e `dps_get_portal_page_id()`
  - Refatoração de 7 chamadas hardcoded para usar funções centralizadas
- **v2.0.0**: Sistema de autenticação por tokens (magic links)
  - Substituído login com senha por autenticação via tokens únicos
  - Tabela `wp_dps_portal_tokens` para gerenciar tokens
  - Tokens com expiração de 30 minutos e uso único
- **v1.0.0**: Lançamento inicial com portal completo (login, histórico, galeria, atualização de dados, integração com Finance/Payment/Loyalty)

### Melhorias implementadas
- Estrutura modular com classes em `includes/` e assets em `assets/`
- Integração condicional com add-ons opcionais via `function_exists()`
- Interface administrativa integrada às configurações do sistema

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
