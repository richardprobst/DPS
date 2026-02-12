# Guia do Usuário: Frontend Add-on (desi.pet by PRObst)

> **Versão**: 1.0.0  
> **Última atualização**: 2026-02-11  
> **Autor**: PRObst  
> **Site**: [www.probst.pro](https://www.probst.pro)

---

## 📖 Índice

1. [Visão Geral](#visão-geral)
2. [Instalação e Ativação](#instalação-e-ativação)
3. [Módulos Disponíveis](#módulos-disponíveis)
4. [Configuração](#configuração)
5. [Shortcodes](#shortcodes)
6. [Criando Páginas Frontend](#criando-páginas-frontend)
7. [Personalização Visual](#personalização-visual)
8. [Resolução de Problemas](#resolução-de-problemas)
9. [Perguntas Frequentes](#perguntas-frequentes)

---

## Visão Geral

O **Frontend Add-on** (`desi-pet-shower-frontend`) é um complemento modular do sistema desi.pet by PRObst que consolida e moderniza as experiências frontend voltadas aos clientes, incluindo:

- **Formulário de Cadastro** de clientes e pets
- **Formulário de Agendamento** de serviços
- **Painel de Configurações** administrativas

### ✨ Principais características

- **Arquitetura moderna PHP 8.4**: Código otimizado e seguro
- **Design Material 3 Expressive**: Interface visual moderna e consistente
- **Feature Flags**: Controle granular de ativação por módulo
- **Rollback instantâneo**: Desative módulos sem impactar o sistema
- **Dual-run**: Funciona em paralelo com add-ons legados durante a migração
- **Totalmente seguro**: Validação de nonce, capabilities e sanitização

### 🎯 Para quem é este add-on?

- **Proprietários de pet shops** que desejam modernizar a experiência dos clientes
- **Administradores do sistema** que precisam de controle granular sobre funcionalidades
- **Desenvolvedores** que buscam uma arquitetura moderna e bem documentada

---

## Instalação e Ativação

### Pré-requisitos

Antes de instalar o Frontend Add-on, certifique-se de que:

- ✅ WordPress **6.9 ou superior** está instalado
- ✅ PHP **8.4 ou superior** está ativo no servidor
- ✅ Plugin base **desi.pet by PRObst** está instalado e ativado
- ✅ Para usar o módulo Registration: add-on `desi-pet-shower-registration` deve estar ativo
- ✅ Para usar o módulo Booking: add-on `desi-pet-shower-booking` deve estar ativo

### Passo 1: Instalar o plugin

1. Faça upload do plugin para `/wp-content/plugins/desi-pet-shower-frontend/`
2. Ou instale via GitHub Updater (consulte `GUIA_SISTEMA_DPS.md` para instruções)

### Passo 2: Ativar o plugin

**Via Painel WordPress:**
1. Acesse **Plugins** → **Plugins Instalados**
2. Localize **desi.pet by PRObst – Frontend Add-on**
3. Clique em **Ativar**

**Via WP-CLI:**
```bash
wp plugin activate desi-pet-shower-frontend
```

### Passo 3: Verificar a instalação

Após ativar o plugin, você verá:
- Uma nova aba **"Frontend"** na página de Configurações do sistema
- Mensagem de sucesso no topo da tela

> **Nota importante**: Ao instalar pela primeira vez, **todos os módulos estarão desabilitados por padrão**. Você precisará habilitar os módulos desejados manualmente na aba de Configurações.

---

## Módulos Disponíveis

O Frontend Add-on é composto por **3 módulos independentes**, cada um controlado por uma feature flag. Você pode ativar ou desativar cada módulo conforme sua necessidade.

Além disso, os **módulos nativos V2** (Fase 7) oferecem formulários 100% independentes dos add-ons legados, com implementação Material 3 Expressive nativa.

### 📋 Módulo Registration (Cadastro)

**Status:** Operacional (Fase 2)  
**Feature Flag:** `registration`  
**Shortcode assumido:** `[dps_registration_form]`

**O que faz:**
- Exibe formulário público de cadastro para clientes e pets
- Aplica estilos modernos Material 3 Expressive
- Mantém compatibilidade total com o add-on legado de cadastro
- Preserva todos os hooks de integração (ex: integração com Loyalty)

**Quando usar:**
- Para criar página pública de cadastro de novos clientes
- Para modernizar visualmente o formulário de cadastro existente
- Para preparar migração gradual do sistema legado

### 📅 Módulo Booking (Agendamento)

**Status:** Operacional (Fase 3)  
**Feature Flag:** `booking`  
**Shortcode assumido:** `[dps_booking_form]`

**O que faz:**
- Exibe formulário público de agendamento de serviços
- Aplica estilos modernos Material 3 Expressive
- Mantém compatibilidade total com o add-on legado de agendamento
- Preserva todos os hooks críticos consumidos por 7+ add-ons

**Quando usar:**
- Para criar página pública de agendamento de serviços
- Para modernizar visualmente o formulário de agendamento existente
- Para preparar migração gradual do sistema legado

### ⚙️ Módulo Settings (Configurações)

**Status:** Operacional (Fase 4)  
**Feature Flag:** `settings`  
**Hooks consumidos:** `dps_settings_register_tabs`, `dps_settings_save_save_frontend`

**O que faz:**
- Adiciona aba "Frontend" no painel de configurações administrativas
- Permite controlar feature flags de forma visual
- Exibe informações do add-on (versão, módulos ativos)
- Interface intuitiva para habilitar/desabilitar módulos

**Quando usar:**
- Para gerenciar visualmente as feature flags dos módulos
- Para verificar status de ativação e versões
- Para administradores que preferem interface gráfica ao WP-CLI

### 📋 Módulo Registration V2 (Cadastro Nativo)

**Status:** Operacional (Fase 7.2)  
**Feature Flag:** `registration_v2`  
**Shortcode:** `[dps_registration_v2]`

**O que faz:**
- Formulário de cadastro 100% nativo M3 Expressive — não depende do add-on legado
- Validação completa: nome, email, telefone, CPF (mod-11), pets
- Detecção de duplicatas por telefone com override para admin
- reCAPTCHA v3 integrado (quando habilitado)
- Email de confirmação com token 48h
- Integração Loyalty via Hook Bridge (código de indicação)
- Anti-spam filter configurável

**Quando usar:**
- Para substituir o formulário legado de cadastro por implementação nativa moderna
- Quando deseja independência total do `DPS_Registration_Addon`
- Para sites novos que não precisam de compatibilidade retroativa

> **Nota:** Pode coexistir com o módulo Registration v1 — ambos podem estar ativos em páginas diferentes.

### 📅 Módulo Booking V2 (Agendamento Nativo)

**Status:** Operacional (Fase 7.3)  
**Feature Flag:** `booking_v2`  
**Shortcode:** `[dps_booking_v2]`

**O que faz:**
- Wizard de agendamento nativo M3 com 5 steps:
  1. **Busca e seleção de cliente** (AJAX por telefone)
  2. **Seleção de pets** (múltiplos, com paginação)
  3. **Seleção de serviços** (com preços por porte e total acumulado)
  4. **Data e horário** (slots de 30min com verificação de conflitos)
  5. **Confirmação** (resumo completo + submit)
- 3 tipos de agendamento: avulso (simple), recorrente (subscription), retroativo (past)
- Extras condicionais: TaxiDog (checkbox + preço) e Tosa (subscription only + preço + frequência)
- Login obrigatório com redirecionamento automático
- 5 endpoints AJAX com nonce + capability check
- Hook bridge CRÍTICO: dispara `dps_base_after_save_appointment` para 8 add-ons consumidores
- Confirmação via transient (5min TTL)
- 100% independente do `DPS_Booking_Addon`
- JavaScript vanilla (zero jQuery)

**Quando usar:**
- Para substituir o formulário legado de agendamento por implementação nativa moderna
- Quando deseja independência total do `DPS_Booking_Addon`
- Para sites novos que não precisam de compatibilidade retroativa

> **Nota:** Pode coexistir com o módulo Booking v1 — ambos podem estar ativos em páginas diferentes.

---

## Configuração

### Opção 1: Via Painel Administrativo (recomendado)

Esta é a forma mais simples e visual de configurar o Frontend Add-on.

#### Passo 1: Habilitar o módulo Settings

**Primeira vez? Habilite via WP-CLI ou diretamente no banco:**

```bash
# Via WP-CLI
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json
```

**Ou via phpMyAdmin/código:**
```php
update_option( 'dps_frontend_feature_flags', [
    'registration' => false,
    'booking'      => false,
    'settings'     => true,
] );
```

#### Passo 2: Acessar a aba Frontend

1. Acesse a página de **Configurações** do sistema
   - Use o shortcode `[dps_configuracoes]` ou
   - Acesse via menu admin do desi.pet
2. Clique na aba **"Frontend"**
3. Você verá:
   - Informações sobre o add-on (versão, módulos disponíveis)
   - Checkboxes para habilitar cada módulo
   - Contador de módulos ativos

#### Passo 3: Habilitar módulos desejados

1. Marque as caixas dos módulos que deseja ativar:
   - ☐ **Registration** (Cadastro)
   - ☐ **Booking** (Agendamento)
   - ☐ **Settings** (Configurações)
2. Clique no botão **"Salvar Configurações"**
3. Aguarde a mensagem de confirmação
4. As páginas públicas com os shortcodes agora exibirão a versão modernizada

### Opção 2: Via WP-CLI

Para administradores avançados ou scripts de deploy automatizado.

#### Habilitar todos os módulos de uma vez:

```bash
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json
```

#### Habilitar módulos gradualmente (recomendado em produção):

```bash
# Primeiro: Settings (menor risco)
wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json

# Depois: Registration
wp option update dps_frontend_feature_flags '{"registration":true,"booking":false,"settings":true}' --format=json

# Por último: Booking
wp option update dps_frontend_feature_flags '{"registration":true,"booking":true,"settings":true}' --format=json
```

#### Verificar status atual:

```bash
wp option get dps_frontend_feature_flags --format=json
```

#### Desabilitar um módulo específico (rollback):

```bash
# Exemplo: desabilitar apenas Registration
wp option update dps_frontend_feature_flags '{"registration":false,"booking":true,"settings":true}' --format=json
```

### Opção 3: Via Código PHP

Em `wp-config.php` ou em um plugin personalizado:

```php
// Habilitar todos os módulos
update_option( 'dps_frontend_feature_flags', [
    'registration' => true,
    'booking'      => true,
    'settings'     => true,
] );

// Verificar se um módulo está ativo
$flags = get_option( 'dps_frontend_feature_flags', [] );
if ( ! empty( $flags['registration'] ) ) {
    // Módulo Registration está ativo
}
```

### Estratégia de Ativação Recomendada

Para minimizar riscos, siga esta ordem de ativação:

1. **Primeiro: Settings** (risco mínimo — apenas aba admin)
2. **Segundo: Registration** (risco médio — formulário público)
3. **Terceiro: Booking** (risco médio — agendamento)

**Janela de observação:** aguarde mínimo **48 horas** entre a ativação de cada módulo em ambiente de produção.

---

## Shortcodes

O Frontend Add-on trabalha com shortcodes existentes (v1, em dual-run com legado) e novos shortcodes nativos (v2, independentes). Abaixo, a lista completa de shortcodes utilizados e como aplicá-los.

> **v1 (dual-run):** `[dps_registration_form]` e `[dps_booking_form]` — envolvem o legado com surface M3  
> **v2 (nativo):** `[dps_registration_v2]` e `[dps_booking_v2]` — implementação 100% independente

### 🔖 `[dps_registration_form]`

**Descrição:** Exibe o formulário de cadastro de clientes e pets com design Material 3 Expressive.

**Módulo requerido:** Registration (`registration` flag habilitada)

**Parâmetros:** Nenhum (usa todos os padrões do sistema)

**Exemplo de uso:**
```
[dps_registration_form]
```

**Output:**
- Formulário completo de cadastro
- Campos para dados do cliente (nome, telefone, email, etc.)
- Campos para dados do(s) pet(s)
- Botão de envio com validação
- Mensagens de sucesso/erro
- Redirecionamento automático após cadastro

**Onde usar:**
- Página pública "Cadastre-se"
- Página "Novo Cliente"
- Landing pages de captação

**Hooks disponíveis para extensão:**
```php
// Adicionar campos customizados ao formulário
add_action( 'dps_registration_after_fields', 'minha_funcao', 10, 1 );

// Processar dados após criação do cliente
add_action( 'dps_registration_after_client_created', 'minha_funcao', 10, 4 );

// Validação anti-spam customizada
add_filter( 'dps_registration_spam_check', 'minha_funcao', 10, 2 );

// Customizar URL de redirecionamento pós-cadastro
add_filter( 'dps_registration_agenda_url', 'minha_funcao', 10, 2 );
```

---

### 🔖 `[dps_booking_form]`

**Descrição:** Exibe o formulário de agendamento de serviços com design Material 3 Expressive.

**Módulo requerido:** Booking (`booking` flag habilitada)

**Parâmetros:** Nenhum (usa todos os padrões do sistema)

**Exemplo de uso:**
```
[dps_booking_form]
```

**Output:**
- Formulário completo de agendamento
- Seleção de cliente (se logado) ou busca por telefone
- Seleção de pet(s)
- Seleção de serviço(s)
- Escolha de data e horário
- Campo de observações
- Botão de confirmação
- Mensagens de validação
- Confirmação visual pós-agendamento

**Onde usar:**
- Página pública "Agendar Serviço"
- Página "Novo Agendamento"
- Portal do cliente (área autenticada)

**Hooks disponíveis para extensão:**
```php
// CRÍTICO: Hook consumido por 7+ add-ons
add_action( 'dps_base_after_save_appointment', 'minha_funcao', 10, 2 );

// Adicionar campos customizados ao formulário
add_action( 'dps_base_appointment_fields', 'minha_funcao', 10, 1 );

// Modificar campos de atribuição (tosadores, etc.)
add_action( 'dps_base_appointment_assignment_fields', 'minha_funcao', 10, 1 );
```

---

### 🔖 `[dps_registration_v2]`

**Descrição:** Formulário nativo de cadastro Material 3 Expressive. **100% independente do add-on legado** — não requer `DPS_Registration_Addon`.

**Módulo requerido:** Registration V2 (`registration_v2` flag habilitada)

**Parâmetros:**
| Atributo | Descrição | Padrão |
|----------|-----------|--------|
| `redirect_url` | URL pós-cadastro | Página de agendamento |
| `show_pets` | Exibir seção de pets | `true` |
| `show_marketing` | Exibir opt-in marketing | `true` |
| `theme` | Tema visual: `light` ou `dark` | `light` |
| `compact` | Modo compacto | `false` |

**Exemplos de uso:**
```
[dps_registration_v2]
[dps_registration_v2 redirect_url="/agendar" theme="dark"]
[dps_registration_v2 show_pets="false" compact="true"]
```

**Output:**
- Formulário nativo com validação client + server (nome, email, telefone, CPF)
- Detecção de duplicatas por telefone
- Repeater de pets com dataset de raças por espécie
- reCAPTCHA v3 (quando habilitado)
- Email de confirmação 48h
- Integração Loyalty preservada via Hook Bridge

**Onde usar:**
- Página pública de cadastro (substitui `[dps_registration_form]`)
- Sites novos sem add-on legado instalado

**Hooks disponíveis para extensão:**
```php
// Antes de renderizar o formulário V2
add_action( 'dps_registration_v2_before_render', 'minha_funcao', 10, 1 );

// Após criar cliente via V2 (hook bridge dispara legado do Loyalty primeiro)
add_action( 'dps_registration_v2_client_created', 'minha_funcao', 10, 3 );

// Após criar pet via V2
add_action( 'dps_registration_v2_pet_created', 'minha_funcao', 10, 3 );
```

---

### 🔖 `[dps_booking_v2]`

**Descrição:** Wizard nativo de agendamento Material 3 Expressive com 5 steps. **100% independente do add-on legado** — não requer `DPS_Booking_Addon`.

**Módulo requerido:** Booking V2 (`booking_v2` flag habilitada)  
**Requisito:** Usuário logado com capability `manage_options`, `dps_manage_clients`, `dps_manage_pets` ou `dps_manage_appointments`

**Parâmetros:**
| Atributo | Descrição | Padrão |
|----------|-----------|--------|
| `appointment_type` | Tipo: `simple`, `subscription` ou `past` | `simple` |
| `client_id` | ID do cliente pré-selecionado | (vazio) |
| `service_id` | ID do serviço pré-selecionado | (vazio) |
| `start_step` | Step inicial do wizard (1-5) | `1` |
| `show_progress` | Exibir barra de progresso | `true` |
| `theme` | Tema visual: `light` ou `dark` | `light` |
| `compact` | Modo compacto | `false` |
| `edit_id` | ID de agendamento para edição | (vazio) |

**Exemplos de uso:**
```
[dps_booking_v2]
[dps_booking_v2 appointment_type="subscription"]
[dps_booking_v2 client_id="123" start_step="2"]
[dps_booking_v2 theme="dark" compact="true"]
```

**Output:**
- Wizard 5 steps com barra de progresso e navegação
- Step 1: Busca de cliente por telefone (AJAX)
- Step 2: Seleção de pets (múltiplos, com paginação)
- Step 3: Seleção de serviços com preços por porte
- Step 4: Data/hora com slots e verificação de conflitos
- Step 5: Extras (TaxiDog, Tosa para subscription) + Confirmação final
- Tela de sucesso pós-criação

**Onde usar:**
- Página administrativa de agendamento (substitui `[dps_booking_form]`)
- Portal do cliente (área autenticada)

**Hooks disponíveis para extensão:**
```php
// Antes de renderizar o wizard V2
add_action( 'dps_booking_v2_before_render', 'minha_funcao', 10, 1 );

// Ao renderizar step do wizard
add_action( 'dps_booking_v2_step_render', 'minha_funcao', 10, 2 );

// Filtro de validação por step
add_filter( 'dps_booking_v2_step_validate', 'minha_funcao', 10, 3 );

// Antes de criar agendamento
add_action( 'dps_booking_v2_before_process', 'minha_funcao', 10, 1 );

// Após criar agendamento V2
add_action( 'dps_booking_v2_appointment_created', 'minha_funcao', 10, 2 );

// CRÍTICO: Hook bridge para 8 add-ons (disparado automaticamente)
// Stock, Payment, Groomers, Calendar, Communications, Push, Services, Booking
add_action( 'dps_base_after_save_appointment', 'minha_funcao', 10, 2 );
```

---

### 🔖 `[dps_configuracoes]`

**Descrição:** Exibe a página completa de configurações administrativas do sistema. **Este shortcode não é modificado pelo Frontend Add-on**, mas a aba "Frontend" só aparece se o módulo Settings estiver ativo.

**Módulo requerido:** Settings (`settings` flag habilitada) — apenas para exibir a aba Frontend

**Parâmetros:** Nenhum

**Exemplo de uso:**
```
[dps_configuracoes]
```

**Output:**
- Página de configurações com múltiplas abas
- Aba "Frontend" (se módulo Settings ativo) com:
  - Informações do add-on
  - Controles de feature flags
  - Contador de módulos ativos
  - Botão de salvar

**Onde usar:**
- Página administrativa "Configurações do Sistema"
- Painel de administração (uso interno)

**Nota:** Este shortcode é do plugin base e não é afetado pelo Frontend Add-on. O módulo Settings apenas adiciona uma nova aba dentro desta página.

---

## Criando Páginas Frontend

Aqui estão instruções completas para criar as páginas necessárias que utilizam os shortcodes do Frontend Add-on.

### 📄 Página de Cadastro

**Objetivo:** Permitir que novos clientes se cadastrem no sistema.

**Passo a passo:**

1. **Criar nova página no WordPress:**
   - Vá em **Páginas** → **Adicionar Nova**
   - Título sugerido: "Cadastre-se" ou "Novo Cliente"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_registration_form]`
   - Publique a página

3. **Configurar permalink amigável (opcional):**
   - URL sugerida: `https://seusite.com/cadastro/`
   - Configure em **Configurações** → **Links Permanentes**

4. **Adicionar ao menu (opcional):**
   - Vá em **Aparência** → **Menus**
   - Adicione a página ao menu principal
   - Texto sugerido: "Cadastre-se" ou "Novo Cliente"

5. **Definir como página de cadastro do sistema:**
   - Acesse as configurações do add-on Registration
   - Defina esta página como "Página de Cadastro"
   - Isso garantirá redirecionamentos corretos

**Dica de segurança:** Esta página deve ser pública e acessível sem login.

---

### 📄 Página de Agendamento

**Objetivo:** Permitir que clientes agendem serviços.

**Passo a passo:**

1. **Criar nova página no WordPress:**
   - Vá em **Páginas** → **Adicionar Nova**
   - Título sugerido: "Agendar Serviço" ou "Marcar Horário"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_booking_form]`
   - Publique a página

3. **Configurar permalink amigável (opcional):**
   - URL sugerida: `https://seusite.com/agendar/`

4. **Adicionar ao menu (opcional):**
   - Vá em **Aparência** → **Menus**
   - Adicione a página ao menu principal
   - Texto sugerido: "Agendar" ou "Marcar Horário"

5. **Definir como página de agendamento do sistema:**
   - Acesse as configurações do add-on Booking
   - Defina esta página como "Página de Agendamento"

**Notas importantes:**
- Esta página pode ser pública ou protegida (requer login)
- Se protegida, garanta que clientes tenham acesso
- Considere criar versões diferentes para:
  - Clientes públicos (primeiro agendamento)
  - Clientes cadastrados (reagendamento)

---

### 📄 Página de Configurações (Admin)

**Objetivo:** Centralizar configurações administrativas do sistema.

**Passo a passo:**

1. **Criar nova página no WordPress:**
   - Vá em **Páginas** → **Adicionar Nova**
   - Título sugerido: "Configurações do Sistema"

2. **Adicionar o shortcode:**
   - No editor de blocos, adicione um bloco **Shortcode**
   - Digite: `[dps_configuracoes]`
   - Publique a página

3. **Proteger a página (OBRIGATÓRIO):**
   - Esta página deve ser acessível **apenas para administradores**
   - Use plugin de controle de acesso ou configure via código
   - Exemplo com código:
   ```php
   // No functions.php do tema ou plugin
   add_action( 'template_redirect', function() {
       if ( is_page( 'configuracoes' ) && ! current_user_can( 'manage_options' ) ) {
           wp_redirect( home_url() );
           exit;
       }
   } );
   ```

4. **Adicionar ao menu admin (opcional):**
   - Adicione link direto no menu do admin
   - Ou crie shortcut no dashboard

**Segurança:** Esta página contém configurações sensíveis do sistema. NUNCA a deixe pública.

---

### 📄 Exemplo de Layout Completo

Para uma experiência ideal, crie a seguinte estrutura de páginas:

```
📁 Páginas Públicas
├── 🏠 Home
├── 📋 Cadastre-se              → [dps_registration_form]
├── 📅 Agendar Serviço          → [dps_booking_form]
├── 📞 Contato
└── ℹ️ Sobre Nós

📁 Páginas Protegidas (clientes)
├── 👤 Minha Conta
├── 🐾 Meus Pets
└── 📅 Meus Agendamentos        → [dps_booking_form]

📁 Páginas Admin (apenas staff)
└── ⚙️ Configurações            → [dps_configuracoes]
```

---

## Personalização Visual

O Frontend Add-on utiliza o **Design System Material 3 Expressive** para garantir uma experiência visual moderna e consistente.

### 🎨 Sistema de Design Tokens

Todos os estilos são baseados em **CSS Custom Properties** (variáveis CSS), facilitando a personalização sem editar arquivos do plugin.

**Arquivo de tokens:** `dps-design-tokens.css` (carregado automaticamente pelo plugin base)

### Principais Categorias de Tokens

#### 1. Cores

```css
/* Cores principais (primary) */
--dps-color-primary: #6750A4;
--dps-color-on-primary: #FFFFFF;
--dps-color-primary-container: #EADDFF;
--dps-color-on-primary-container: #21005D;

/* Cores secundárias (secondary) */
--dps-color-secondary: #625B71;
--dps-color-on-secondary: #FFFFFF;
--dps-color-secondary-container: #E8DEF8;
--dps-color-on-secondary-container: #1D192B;

/* Superfícies (backgrounds) */
--dps-color-surface: #FEF7FF;
--dps-color-surface-variant: #E7E0EC;
--dps-color-on-surface: #1C1B1F;
--dps-color-on-surface-variant: #49454F;

/* Estados (success, warning, error) */
--dps-color-success: #4CAF50;
--dps-color-warning: #FF9800;
--dps-color-error: #B3261E;
```

#### 2. Tipografia

```css
/* Escala tipográfica Material 3 */
--dps-typescale-display-large: 57px;
--dps-typescale-headline-large: 32px;
--dps-typescale-title-large: 22px;
--dps-typescale-body-large: 16px;
--dps-typescale-label-large: 14px;
```

#### 3. Formas (arredondamentos)

```css
/* Escala de arredondamento */
--dps-shape-none: 0px;
--dps-shape-extra-small: 4px;
--dps-shape-small: 8px;
--dps-shape-medium: 12px;
--dps-shape-large: 16px;
--dps-shape-extra-large: 28px;
--dps-shape-pill: 9999px;
```

#### 4. Elevação (sombras)

```css
/* Níveis de elevação tonal */
--dps-elevation-1: 0px 1px 2px rgba(0, 0, 0, 0.3);
--dps-elevation-2: 0px 1px 2px rgba(0, 0, 0, 0.3), 0px 2px 6px rgba(0, 0, 0, 0.15);
--dps-elevation-3: 0px 4px 8px rgba(0, 0, 0, 0.15), 0px 1px 3px rgba(0, 0, 0, 0.3);
```

#### 5. Motion (animações)

```css
/* Durações */
--dps-motion-duration-short: 200ms;
--dps-motion-duration-medium: 300ms;
--dps-motion-duration-long: 500ms;

/* Easing expressivo */
--dps-motion-easing-standard: cubic-bezier(0.4, 0.0, 0.2, 1);
--dps-motion-easing-emphasized: cubic-bezier(0.2, 0.0, 0, 1);
```

### Personalizando o Frontend Add-on

#### Método 1: Sobrescrever tokens (recomendado)

Adicione CSS customizado no seu tema que sobrescreve os tokens:

```css
/* No arquivo CSS do seu tema */
:root {
    /* Mudar cor primária para azul */
    --dps-color-primary: #1976D2;
    --dps-color-on-primary: #FFFFFF;
    --dps-color-primary-container: #BBDEFB;
    
    /* Mudar arredondamento padrão */
    --dps-shape-medium: 8px;
    
    /* Acelerar animações */
    --dps-motion-duration-medium: 200ms;
}
```

#### Método 2: Classes CSS específicas

Cada módulo envolve seu output em classes específicas:

```css
/* Estilizar o formulário de cadastro */
.dps-frontend .dps-registration-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem;
}

/* Estilizar o formulário de agendamento */
.dps-frontend .dps-booking-form {
    background: var(--dps-color-surface);
    border-radius: var(--dps-shape-large);
    padding: 2rem;
}

/* Customizar botões */
.dps-frontend .dps-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

#### Método 3: Tema Escuro

O sistema suporta tema escuro via atributo `data-dps-theme`:

```html
<body data-dps-theme="dark">
    <!-- Conteúdo com tema escuro aplicado -->
</body>
```

Ou via JavaScript:

```javascript
// Ativar tema escuro
document.body.setAttribute('data-dps-theme', 'dark');

// Desativar tema escuro
document.body.setAttribute('data-dps-theme', 'light');

// Toggle
const currentTheme = document.body.getAttribute('data-dps-theme') || 'light';
document.body.setAttribute('data-dps-theme', currentTheme === 'light' ? 'dark' : 'light');
```

### Classes CSS Disponíveis

**Wrapper principal:**
- `.dps-frontend` — envolve todo o output dos módulos

**Formulários:**
- `.dps-registration-form` — formulário de cadastro
- `.dps-booking-form` — formulário de agendamento

**Componentes:**
- `.dps-btn-primary` — botão primário (ação principal)
- `.dps-btn-secondary` — botão secundário
- `.dps-btn-text` — botão texto (sem fundo)
- `.dps-field-group` — grupo de campos
- `.dps-label` — rótulos de campos
- `.dps-input` — campos de entrada
- `.dps-select` — campos select
- `.dps-checkbox` — checkboxes
- `.dps-radio` — radio buttons
- `.dps-message` — mensagens de feedback
- `.dps-message--success` — mensagem de sucesso
- `.dps-message--error` — mensagem de erro
- `.dps-message--warning` — mensagem de aviso

### Referências de Design

Para design detalhado, consulte:
- `docs/visual/VISUAL_STYLE_GUIDE.md` — Guia completo de estilos
- `docs/visual/FRONTEND_DESIGN_INSTRUCTIONS.md` — Instruções de design frontend

---

## Resolução de Problemas

### ❌ Problema: Aba "Frontend" não aparece nas Configurações

**Causa provável:** Módulo Settings não está ativo.

**Solução:**
1. Ative o módulo Settings via WP-CLI:
   ```bash
   wp option update dps_frontend_feature_flags '{"registration":false,"booking":false,"settings":true}' --format=json
   ```
2. Recarregue a página de configurações

---

### ❌ Problema: Shortcode exibe código ao invés do formulário

**Exemplo:** A página mostra `[dps_registration_form]` como texto.

**Causas prováveis:**
1. Módulo correspondente não está ativo
2. Add-on legado não está instalado
3. Plugin Frontend não está ativado

**Solução:**
1. Verifique se o plugin está ativo:
   ```bash
   wp plugin list | grep frontend
   ```
2. Verifique se o módulo está ativo:
   ```bash
   wp option get dps_frontend_feature_flags --format=json
   ```
3. Verifique se o add-on legado correspondente está ativo:
   - Para Registration: `desi-pet-shower-registration`
   - Para Booking: `desi-pet-shower-booking`
4. Ative o módulo necessário via WP-CLI ou painel

---

### ❌ Problema: Formulário aparece mas sem estilos

**Sintoma:** O formulário funciona mas está com aparência "quebrada" ou sem estilo.

**Causas prováveis:**
1. CSS não está sendo carregado
2. Conflito com tema ou outro plugin
3. Cache de CSS desatualizado

**Solução:**
1. Limpe o cache do navegador (Ctrl+Shift+R)
2. Limpe cache do WordPress (se usar plugin de cache)
3. Verifique se `dps-design-tokens.css` está sendo carregado:
   - Abra DevTools (F12)
   - Vá na aba Network
   - Recarregue a página
   - Procure por `dps-design-tokens.css` e `frontend-addon.css`
4. Se não estiver carregando, verifique se o plugin base está ativo
5. Desative temporariamente outros plugins para identificar conflito

---

### ❌ Problema: Erro ao salvar configurações

**Sintoma:** Mensagem de erro ao clicar em "Salvar Configurações" na aba Frontend.

**Causas prováveis:**
1. Nonce expirado (sessão antiga)
2. Falta de permissão (usuário não é admin)
3. Conflito de plugin

**Solução:**
1. Recarregue a página e tente novamente
2. Faça logout e login novamente
3. Verifique se seu usuário tem capability `manage_options`:
   ```php
   current_user_can( 'manage_options' ); // deve retornar true
   ```
4. Verifique logs de erro do WordPress:
   ```bash
   tail -f /caminho/para/wp-content/debug.log
   ```

---

### ❌ Problema: Módulo ativo mas formulário não aparece

**Sintoma:** Feature flag está `true` mas o formulário não renderiza.

**Causas prováveis:**
1. Add-on legado não está instalado/ativo
2. Shortcode foi digitado incorretamente
3. Cache de página

**Solução:**
1. Verifique a digitação exata do shortcode:
   - Registration: `[dps_registration_form]` (sem espaços extras)
   - Booking: `[dps_booking_form]` (sem espaços extras)
2. Verifique se add-on legado está ativo:
   ```bash
   wp plugin list | grep -E '(registration|booking)'
   ```
3. Ative o add-on legado correspondente:
   ```bash
   wp plugin activate desi-pet-shower-registration
   wp plugin activate desi-pet-shower-booking
   ```
4. Limpe cache de páginas
5. Verifique logs do sistema (se WP_DEBUG ativo):
   - Procure por avisos do `DPS_Frontend_Logger`

---

### ❌ Problema: Rollback não funciona

**Sintoma:** Desabilitei o módulo mas o formulário continua aparecendo.

**Causa provável:** Cache de página ou de objeto.

**Solução:**
1. Limpe TODOS os caches:
   - Cache de navegador
   - Cache de página (WP Super Cache, W3 Total Cache, etc.)
   - Cache de objeto (Redis, Memcached)
   - Cache de CDN (Cloudflare, etc.)
2. Verifique se a flag foi realmente desabilitada:
   ```bash
   wp option get dps_frontend_feature_flags --format=json
   ```
3. Force recarga sem cache: Ctrl+Shift+R (ou Cmd+Shift+R no Mac)
4. Se persistir, desative o plugin inteiro e reative

---

### ⚠️ Problema: Hooks personalizados não funcionam

**Sintoma:** Hooks customizados (ex: `dps_registration_after_fields`) não são executados.

**Causa provável:** Prioridade de hook ou módulo não inicializado.

**Solução:**
1. Verifique se o módulo está ativo
2. Registre seu hook com prioridade adequada:
   ```php
   // Prioridade 10 é padrão, mas pode precisar ajustar
   add_action( 'dps_registration_after_fields', 'minha_funcao', 10, 1 );
   ```
3. Verifique se sua função está sendo carregada:
   ```php
   function minha_funcao( $data ) {
       error_log( 'Hook executado: ' . print_r( $data, true ) );
       // seu código aqui
   }
   ```
4. Consulte documentação dos hooks em `ANALYSIS.md`

---

### 🔍 Debug Mode

Para ativar logs detalhados do Frontend Add-on:

1. Ative WP_DEBUG no `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

2. Logs serão salvos em `/wp-content/debug.log`

3. Procure por mensagens do `DPS_Frontend_Logger`:
   ```bash
   tail -f wp-content/debug.log | grep "Frontend"
   ```

4. Níveis de log:
   - `INFO`: Informações gerais (módulo ativado, etc.)
   - `WARNING`: Avisos (legado não encontrado, etc.)
   - `ERROR`: Erros críticos

---

## Perguntas Frequentes

### 1. Preciso desinstalar os add-ons legados?

**Resposta:** Não! O Frontend Add-on trabalha **em paralelo** (dual-run) com os add-ons legados. Ambos precisam estar ativos para o sistema funcionar.

- ✅ Mantenha `desi-pet-shower-registration` ativo
- ✅ Mantenha `desi-pet-shower-booking` ativo
- ✅ Ative `desi-pet-shower-frontend` adicional

O Frontend Add-on apenas "envolve" a funcionalidade legada com estilos modernos.

---

### 2. Posso usar o Frontend Add-on em produção?

**Resposta:** Sim! Todos os 3 módulos estão **operacionais e testados** (Fases 2-4 concluídas). Porém, recomendamos:

1. Testar em ambiente de desenvolvimento primeiro
2. Ativar módulos gradualmente (Settings → Registration → Booking)
3. Manter janela de observação de 48h entre ativações
4. Ter plano de rollback documentado

O sistema foi projetado para rollback instantâneo desabilitando feature flags.

---

### 3. O que acontece se eu desabilitar um módulo?

**Resposta:** Rollback instantâneo! O comportamento volta **100%** para o add-on legado:

- ✅ Sem quebra de funcionalidade
- ✅ Sem perda de dados
- ✅ Sem necessidade de reconfigurar

Apenas os estilos M3 Expressive deixam de ser aplicados, voltando ao visual legado.

---

### 4. Posso customizar os formulários?

**Resposta:** Sim! Há 3 níveis de customização:

**Nível 1 — Visual (CSS):**
- Sobrescreva design tokens CSS
- Adicione classes customizadas
- Ative tema escuro

**Nível 2 — Estrutura (Hooks):**
- Use hooks para adicionar campos
- Modifique comportamentos via filtros
- Estenda funcionalidade sem editar core

**Nível 3 — Código (Desenvolvimento):**
- Crie módulos customizados
- Estenda classes base
- Consulte `AGENT_ENGINEERING_PLAYBOOK.md`

---

### 5. Como atualizar o Frontend Add-on?

**Resposta:** Usando GitHub Updater (recomendado):

1. Configure GitHub Updater (consulte `GUIA_SISTEMA_DPS.md`)
2. Vá em **Painel** → **Atualizações**
3. Localize "desi.pet by PRObst – Frontend Add-on"
4. Clique em "Atualizar Agora"

Ou manualmente:
1. Desative o plugin
2. Substitua arquivos em `/wp-content/plugins/desi-pet-shower-frontend/`
3. Reative o plugin
4. Verifique se feature flags permanecem ativas

**Dica:** As configurações (feature flags) são mantidas após atualização.

---

### 6. Frontend Add-on consome recursos extras?

**Resposta:** Impacto mínimo!

- **CSS adicional:** ~15KB (gzipped)
- **JavaScript:** Mínimo (apenas quando necessário)
- **Processamento:** Zero overhead (apenas envolve output legado)
- **Banco de dados:** Apenas 1 option (`dps_frontend_feature_flags`)
- **Telemetria:** Contadores batch no shutdown (zero overhead por request)

O add-on foi otimizado para performance máxima.

---

### 7. Posso usar apenas alguns módulos?

**Resposta:** Sim! Cada módulo é **100% independente**:

- ✅ Habilite apenas Settings (se quiser apenas a aba admin)
- ✅ Habilite apenas Registration (se quiser apenas modernizar cadastro)
- ✅ Habilite apenas Booking (se quiser apenas modernizar agendamento)
- ✅ Habilite qualquer combinação que desejar

Não há dependência entre módulos.

---

### 8. Como reportar problemas?

**Resposta:** Entre em contato:

1. **Via GitHub:** Abra issue em `https://github.com/richardprobst/DPS`
2. **Via Email:** Contate PRObst em [www.probst.pro](https://www.probst.pro)
3. **Incluir sempre:**
   - Versão do WordPress e PHP
   - Versão do Frontend Add-on
   - Versão do plugin base
   - Logs de erro (se disponíveis)
   - Passos para reproduzir o problema

---

### 9. Roadmap futuro do Frontend Add-on

**Resposta:** O add-on seguiu um plano em **6 fases** + a **Fase 7** de implementação nativa:

- ✅ **Fase 1:** Fundação (arquitetura, feature flags, assets) — Concluída
- ✅ **Fase 2:** Módulo Registration (dual-run) — Concluída
- ✅ **Fase 3:** Módulo Booking (dual-run) — Concluída
- ✅ **Fase 4:** Módulo Settings (aba admin) — Concluída
- ✅ **Fase 5:** Consolidação e documentação — Concluída
- ✅ **Fase 6:** Governança de depreciação — Concluída
- ✅ **Fase 7.1:** Preparação V2 (abstracts, template engine, hook bridges, componentes M3) — Concluída
- ✅ **Fase 7.2:** Registration V2 nativo (formulário independente) — Concluída
- ✅ **Fase 7.3:** Booking V2 nativo (wizard 5-step independente) — Concluída
- ⏳ **Fase 7.4:** Coexistência e migração (toggle admin, documentação, telemetria)
- ⏳ **Fase 7.5:** Depreciação do dual-run (após 180 dias de observação)

**Próximos passos:**
- Fase 7.4: Toggle admin v1/v2, documentação de migração, telemetria comparativa
- Observação de telemetria de uso (180 dias mínimo)
- Decisão sobre depreciação de add-ons legados
- Novos módulos (portal do cliente, relatórios, etc.)

Consulte `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` para detalhes da Fase 7.

---

### 10. Onde encontrar mais documentação?

**Resposta:** Documentação completa disponível:

| Documento | Propósito |
|-----------|-----------|
| `docs/GUIA_SISTEMA_DPS.md` | Guia geral do sistema completo |
| `docs/implementation/FRONTEND_ROLLOUT_GUIDE.md` | Guia de rollout por ambiente |
| `docs/implementation/FRONTEND_RUNBOOK.md` | Runbook de incidentes e rollback |
| `docs/qa/FRONTEND_COMPATIBILITY_MATRIX.md` | Compatibilidade com outros add-ons |
| `docs/qa/FRONTEND_REMOVAL_READINESS.md` | Checklist de remoção futura |
| `docs/refactoring/FRONTEND_ADDON_PHASED_ROADMAP.md` | Roadmap completo das 6 fases |
| `docs/refactoring/FRONTEND_NATIVE_IMPLEMENTATION_PLAN.md` | Plano da Fase 7 (implementação nativa V2) |
| `docs/refactoring/FRONTEND_DEPRECATION_POLICY.md` | Política de depreciação |
| `docs/visual/VISUAL_STYLE_GUIDE.md` | Guia de estilos visuais M3 |
| `ANALYSIS.md` | Arquitetura e contratos internos |
| `CHANGELOG.md` | Histórico de versões e mudanças |

---

## 📞 Suporte

Para suporte técnico ou dúvidas:

- **Site:** [www.probst.pro](https://www.probst.pro)
- **GitHub:** [richardprobst/DPS](https://github.com/richardprobst/DPS)
- **Email:** Consulte o site para contato

---

## 📜 Licença

Frontend Add-on é parte do **desi.pet by PRObst** e é licenciado sob GPL-2.0+.

---

**Última atualização:** 2026-02-12  
**Versão do documento:** 1.0.0  
**Versão do add-on:** 1.5.0 (todas as 6 fases concluídas)
