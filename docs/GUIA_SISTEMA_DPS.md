# 🐾 Guia Completo do Sistema DPS by PRObst

<div align="center">

**Sistema de Gestão para Pet Shops**

**Autor:** PRObst  
**Site:** [www.probst.pro](https://www.probst.pro)

*Versão 1.2 | Última atualização: Dezembro de 2025*

---

[Apresentação](#-apresentação) • [Instalação](#-instalação) • [Configuração](#-configuração) • [Uso do Sistema](#-uso-do-sistema) • [Add-ons](#-add-ons) • [Manutenção](#-manutenção)

</div>

---

## 📋 Índice

1. [Apresentação do Sistema](#-apresentação-do-sistema)
   - [O que é o DPS?](#o-que-é-o-dps)
   - [Principais Funcionalidades](#principais-funcionalidades)
   - [Arquitetura Modular](#arquitetura-modular)
   - [Requisitos do Sistema](#requisitos-do-sistema)

2. [Instalação](#-instalação)
   - [Pré-requisitos](#pré-requisitos)
   - [Instalação do Plugin Base](#instalação-do-plugin-base)
   - [Instalação dos Add-ons](#instalação-dos-add-ons)
   - [Verificação da Instalação](#verificação-da-instalação)

3. [Configuração Inicial](#-configuração-inicial)
   - [Primeiros Passos](#primeiros-passos)
   - [Criação das Páginas do Sistema](#criação-das-páginas-do-sistema)
   - [Configuração de Permissões](#configuração-de-permissões)

4. [Configuração dos Add-ons](#-configuração-dos-add-ons)
   - [Agenda](#1-agenda-addon)
   - [Serviços](#2-serviços-addon)
   - [Financeiro](#3-financeiro-addon)
   - [Pagamentos (Mercado Pago)](#4-pagamentos-addon)
   - [Comunicações](#5-comunicações-addon)
   - [Portal do Cliente](#6-portal-do-cliente-addon)
   - [Assistente de IA](#7-assistente-de-ia-addon)
   - [Cadastro Público](#8-cadastro-público-addon)
   - [Campanhas & Fidelidade](#9-campanhas--fidelidade-addon)
   - [Notificações Push](#10-notificações-push-addon)
   - [Estatísticas](#11-estatísticas-addon)
   - [Groomers](#12-groomers-addon)
   - [Estoque](#13-estoque-addon)
   - [Assinaturas](#14-assinaturas-addon)
   - [Backup & Restauração](#15-backup--restauração-addon)
   - [Debugging](#16-debugging-addon)
   - [White Label](#17-white-label-addon)

5. [Uso do Sistema](#-uso-do-sistema)
   - [Painel Principal](#painel-principal)
   - [Gestão de Clientes](#gestão-de-clientes)
   - [Gestão de Pets](#gestão-de-pets)
   - [Agendamentos](#agendamentos)
   - [Histórico de Atendimentos](#histórico-de-atendimentos)
   - [Gestão Financeira](#gestão-financeira)

6. [Recursos Avançados](#-recursos-avançados)
   - [Tipos de Agendamento](#tipos-de-agendamento)
   - [Sistema de Assinaturas](#sistema-de-assinaturas)
   - [Programa de Fidelidade](#programa-de-fidelidade)
   - [Integração com WhatsApp](#integração-com-whatsapp)

7. [Manutenção e Atualizações](#-manutenção-e-atualizações)
   - [Backup do Sistema](#backup-do-sistema)
   - [Atualizações](#atualizações)
   - [Resolução de Problemas](#resolução-de-problemas)

8. [Referência Técnica](#-referência-técnica)
   - [Shortcodes Disponíveis](#shortcodes-disponíveis)
   - [Roles e Capabilities](#roles-e-capabilities)
   - [Estrutura de Dados](#estrutura-de-dados)

---

## 🎯 Apresentação do Sistema

### O que é o DPS?

O **DPS by PRObst (DPS)** é um sistema completo de gestão desenvolvido especificamente para pet shops especializados em serviços de banho e tosa. Construído como uma extensão modular do WordPress, o DPS oferece todas as ferramentas necessárias para gerenciar clientes, pets, agendamentos, finanças e comunicações em um único lugar.

### Principais Funcionalidades

| Funcionalidade | Descrição |
|---------------|-----------|
| 📋 **Cadastro de Clientes** | Gerenciamento completo de clientes com dados de contato, endereço e histórico |
| 🐕 **Cadastro de Pets** | Vinculação de múltiplos pets a cada cliente, com raça, porte e características |
| 📅 **Agendamentos** | Sistema de agendamento com calendário, status e notificações |
| 💰 **Financeiro** | Controle de cobranças, transações e pendências financeiras |
| 💳 **Pagamentos Online** | Integração com Mercado Pago para PIX e boleto |
| 📱 **Comunicações** | Envio de mensagens via WhatsApp, e-mail e SMS |
| 🌐 **Portal do Cliente** | Área exclusiva para clientes acompanharem agendamentos e pendências |
| 🤖 **Assistente IA** | Chat inteligente para atendimento no portal do cliente |
| 📊 **Estatísticas** | Relatórios e métricas de desempenho do negócio |
| 🎁 **Programa de Fidelidade** | Sistema de pontos e indicações |
| 📦 **Controle de Estoque** | Gerenciamento de insumos com alertas de estoque baixo |

### Arquitetura Modular

O sistema é composto por um **plugin base** e **17 add-ons opcionais**:

```
┌─────────────────────────────────────────────────────────┐
│                    DESI PET SHOWER                       │
├─────────────────────────────────────────────────────────┤
│                     PLUGIN BASE                          │
│   • Clientes  • Pets  • Agendamentos  • Histórico       │
│   • Helpers globais  • Sistema de hooks                 │
├─────────────────────────────────────────────────────────┤
│                      ADD-ONS                             │
├───────────┬───────────┬───────────┬───────────┬─────────┤
│  Agenda   │ Serviços  │Financeiro │ Pagamento │Comunic. │
├───────────┼───────────┼───────────┼───────────┼─────────┤
│  Portal   │    IA     │ Cadastro  │Fidelidade │  Push   │
├───────────┼───────────┼───────────┼───────────┼─────────┤
│   Stats   │ Groomers  │  Estoque  │Assinatura │ Backup  │
├───────────┼───────────┼───────────┴───────────┴─────────┤
│ Debugging │WhiteLabel │                                 │
└───────────┴───────────┴─────────────────────────────────┘
```

**Vantagens da arquitetura modular:**
- ✅ Instale apenas o que precisa
- ✅ Menor impacto em performance
- ✅ Atualizações independentes
- ✅ Facilidade de manutenção

### Requisitos do Sistema

| Requisito | Versão Mínima | Recomendado |
|-----------|---------------|-------------|
| WordPress | 6.0+ | 6.9+ |
| PHP | 7.4+ | 8.3+ |
| MySQL | 5.7+ | 8.0+ |
| MariaDB | 10.2+ | 10.6+ |

**Extensões PHP necessárias:**
- cURL (para integrações externas)
- JSON (para manipulação de dados)
- mbstring (para caracteres especiais)
- OpenSSL (para criptografia de senhas SMTP no White Label)

---

## 🚀 Instalação

### Pré-requisitos

Antes de iniciar a instalação, certifique-se de que:

1. ✅ Você tem acesso de administrador ao WordPress
2. ✅ O servidor atende aos requisitos mínimos
3. ✅ Há backup recente do site (recomendado)

### Instalação do Plugin Base

O plugin base é **obrigatório** e deve ser instalado primeiro.

**Passo 1: Upload do Plugin**
1. Acesse **Plugins > Adicionar Novo** no painel WordPress
2. Clique em **Enviar Plugin**
3. Selecione o arquivo `desi-pet-shower-base_plugin.zip`
4. Clique em **Instalar Agora**

**Passo 2: Ativação**
1. Após a instalação, clique em **Ativar Plugin**
2. O sistema criará automaticamente:
   - Tabela de logs (`wp_dps_logs`)
   - Roles customizados (se configurado)
   - Options padrão

**Passo 3: Verificação**
- Acesse o painel WordPress
- Você verá o menu **DPS by PRObst** na barra lateral
- Se aparecer, a instalação foi bem-sucedida!

### Instalação dos Add-ons

Os add-ons são instalados da mesma forma que o plugin base:

1. **Plugins > Adicionar Novo > Enviar Plugin**
2. Selecione o arquivo `.zip` do add-on
3. Instale e ative

**⚠️ Ordem de Instalação Recomendada:**

Para evitar problemas de dependência, siga esta ordem:

| Ordem | Add-on | Dependências |
|-------|--------|--------------|
| 1º | Plugin Base | - |
| 2º | Serviços | Base |
| 3º | Agenda | Base, Serviços (opcional) |
| 4º | Financeiro | Base |
| 5º | Pagamentos | Base, Financeiro |
| 6º | Comunicações | Base |
| 7º | Portal do Cliente | Base |
| 8º | Assistente IA | Base, Portal |
| 9º | Cadastro Público | Base |
| 10º | Campanhas & Fidelidade | Base, Cadastro (opcional) |
| 11º | Notificações Push | Base |
| 12º | Estatísticas | Base, Financeiro (opcional) |
| 13º | Groomers | Base |
| 14º | Estoque | Base |
| 15º | Assinaturas | Base, Financeiro, Pagamentos |
| 16º | Backup | Base |
| 17º | Debugging | Base |
| 18º | White Label | Base |

### Verificação da Instalação

Após instalar todos os componentes desejados:

1. Acesse **DPS by PRObst** no menu lateral
2. Verifique se todos os submenus dos add-ons aparecem
3. Crie uma página de teste com o shortcode `[dps_base]`
4. Acesse a página e confirme que o painel é exibido corretamente

---

## ⚙️ Configuração Inicial

### Primeiros Passos

Após a instalação, siga estes passos para configurar o sistema:

**1. Criar as páginas necessárias**

O sistema precisa de páginas WordPress para exibir seus componentes:

| Página | Shortcode | Propósito |
|--------|-----------|-----------|
| Painel DPS | `[dps_base]` | Painel administrativo principal |
| Configurações DPS | `[dps_configuracoes]` | Tela de configurações |
| Agenda DPS | `[dps_agenda_page]` | Visualização da agenda |
| Portal do Cliente | `[dps_client_portal]` | Área do cliente |
| Cadastro | `[dps_registration_form]` | Formulário público de cadastro |

**2. Configurar permissões**

Por padrão, apenas administradores têm acesso. Para dar acesso a funcionários:

```
Usuários > Adicionar Novo
- Role: DPS Recepção (dps_reception)
```

### Criação das Páginas do Sistema

> ⚠️ **IMPORTANTE: Como inserir shortcodes corretamente**
>
> Use o bloco **"Shortcode"** ou **"Parágrafo"** (texto simples) para inserir shortcodes.
>
> **NÃO use o bloco "Código"** — ele é para exibir código literalmente, não para executá-lo. Shortcodes inseridos no bloco Código aparecerão como texto `[dps_base]` em vez de renderizar o painel.

**Página: Painel DPS (Administrativo)**

1. Vá em **Páginas > Adicionar Nova**
2. Título: "Painel DPS" (ou nome de sua preferência)
3. Clique no botão **"+"** para adicionar bloco e escolha:
   - **Opção recomendada**: Busque por "Shortcode" e selecione o bloco **Shortcode**
   - **Opção alternativa**: Use o bloco **Parágrafo** (texto comum)
4. Digite ou cole o shortcode:
   ```
   [dps_base]
   ```
5. **Publicar** como página privada ou protegida
6. Copie a URL para acesso rápido

> 💡 **Dica**: Se você não encontrar o bloco "Shortcode", pode simplesmente digitar `[dps_base]` em um bloco de parágrafo comum — o WordPress reconhecerá e executará o shortcode automaticamente.

**Página: Configurações DPS**

1. Crie nova página: "Configurações DPS"
2. Conteúdo:
   ```
   [dps_configuracoes]
   ```
3. Publicar (acesso restrito a administradores)

**Página: Portal do Cliente**

1. Crie nova página: "Portal do Cliente"
2. Conteúdo:
   ```
   [dps_client_portal]
   ```
3. Publicar como página pública
4. Configure a URL no add-on do Portal

### Configuração de Permissões

O sistema possui capabilities personalizadas:

| Capability | Descrição | Role Padrão |
|------------|-----------|-------------|
| `manage_options` | Acesso total ao sistema | Administrator |
| `dps_manage_clients` | Gerenciar clientes | DPS Reception |
| `dps_manage_pets` | Gerenciar pets | DPS Reception |
| `dps_manage_appointments` | Gerenciar agendamentos | DPS Reception |
| `dps_manage_stock` | Gerenciar estoque | Administrator |

**Criando usuário de recepção:**

1. **Usuários > Adicionar Novo**
2. Preencha nome, e-mail e senha
3. Role: **DPS Recepção** (se disponível) ou **Editor** + atribua capabilities via plugin de roles

---

## 🔧 Configuração dos Add-ons

### 1. Agenda Add-on

**Propósito:** Visualização e gerenciamento da agenda de atendimentos

**Configuração:**

1. Acesse **DPS by PRObst > Agenda** (se disponível) ou a página de configurações
2. Configure:
   - **Horário de funcionamento**: início e fim do expediente
   - **Intervalo entre agendamentos**: tempo mínimo entre atendimentos
   - **Notificações**: ativar/desativar lembretes automáticos

**Shortcode:**
```
[dps_agenda_page]
```

**Funcionalidades:**
- Visualização por dia/semana/mês
- Filtro por status (agendado, realizado, cancelado)
- Ações rápidas (confirmar, cancelar, reagendar)
- Envio de lembretes via WhatsApp

---

### 2. Serviços Add-on

**Propósito:** Catálogo de serviços com preços por porte

**Configuração:**

1. Acesse o painel DPS
2. Navegue até a aba **Serviços**
3. Clique em **Adicionar Serviço**

**Cadastro de Serviço:**

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| Nome | Nome do serviço | "Banho Completo" |
| Categoria | Tipo de serviço | Banho / Tosa / Combo |
| Preço Pequeno | Valor para pets pequenos | R$ 45,00 |
| Preço Médio | Valor para pets médios | R$ 55,00 |
| Preço Grande | Valor para pets grandes | R$ 70,00 |
| Duração | Tempo estimado | 60 minutos |
| Ativo | Se está disponível | Sim/Não |

**Serviços Padrão (criados automaticamente):**
- Banho Simples
- Banho Completo
- Tosa Higiênica
- Tosa Completa
- Combo Banho + Tosa

---

### 3. Financeiro Add-on

**Propósito:** Gestão de transações, cobranças e pendências

**Configuração:**

1. O add-on é configurado automaticamente ao ativar
2. Cria a tabela `dps_transacoes` para lançamentos
3. Cria a tabela `dps_parcelas` para parcelamentos

**Funcionalidades:**
- Lançamento de cobranças por atendimento
- Quitação parcial ou total
- Histórico de transações
- Relatório de pendências por cliente
- Integração com add-on de Pagamentos

**Navegação:**
- Acesse a aba **Financeiro** no painel DPS
- Visualize pendências e histórico de pagamentos

---

### 4. Pagamentos Add-on

**Propósito:** Integração com Mercado Pago para pagamentos online

**⚠️ IMPORTANTE:** Este é um dos add-ons mais críticos para configurar corretamente!

**Configuração:**

1. Acesse **DPS by PRObst > Pagamentos**
2. Configure as credenciais:

| Campo | Onde Obter |
|-------|------------|
| Access Token | Painel Mercado Pago > Credenciais |
| Chave PIX | Painel Mercado Pago > Seu Negócio > PIX |
| Webhook Secret | Você define e configura no MP |

**Configuração do Webhook (OBRIGATÓRIO):**

O webhook permite que pagamentos sejam confirmados automaticamente.

1. Acesse o [Painel do Mercado Pago](https://mercadopago.com.br/developers/panel)
2. Vá em **Integrações > Webhooks**
3. Configure:
   - **URL**: `https://seusite.com.br?secret=SUA_CHAVE_SECRETA`
   - **Eventos**: `payment.created`, `payment.updated`
4. Copie a mesma chave secreta para o campo **Webhook Secret** no DPS

**Teste:**
1. Gere um link de pagamento de teste
2. Pague com PIX sandbox
3. Verifique se o status atualiza automaticamente

---

### 5. Comunicações Add-on

**Propósito:** Centralizar envio de mensagens via WhatsApp, e-mail e SMS

**Configuração:**

1. Acesse **DPS by PRObst > Comunicações**
2. Configure cada canal:

**WhatsApp:**
| Campo | Descrição |
|-------|-----------|
| Número da Equipe | Número principal para contato (com código do país) |
| Gateway API | URL da API de envio (opcional) |
| API Key | Chave de autenticação do gateway |

**E-mail:**
| Campo | Descrição |
|-------|-----------|
| Remetente Padrão | E-mail que aparece como remetente |
| Nome do Remetente | Nome que aparece no e-mail |

**Templates de Mensagem:**

Configure templates para automações usando placeholders:

| Placeholder | Descrição |
|-------------|-----------|
| `{client_name}` | Nome do cliente |
| `{pet_name}` | Nome do pet |
| `{date}` | Data do agendamento |
| `{time}` | Horário do agendamento |

```
Template de Confirmação:
"Olá {client_name}! Confirmamos seu agendamento para {pet_name} no dia {date} às {time}. 🐾"

Template de Lembrete:
"Oi {client_name}! Lembrando que amanhã às {time} temos o banho do {pet_name}. Até lá! 🛁"

Template Pós-Atendimento:
"Obrigado por trazer {pet_name}! Esperamos que tenha gostado do nosso serviço. ⭐"
```

**API Centralizada:**

Todos os envios de mensagens são processados pela `DPS_Communications_API`, garantindo:
- Logs automáticos de todos os envios
- Substituição de placeholders
- Tratamento de erros consistente

---

### 6. Portal do Cliente Add-on

**Propósito:** Área exclusiva para clientes acessarem seus dados

**Configuração:**

1. Acesse **DPS by PRObst > Portal do Cliente**
2. Configure:

| Opção | Descrição |
|-------|-----------|
| Página do Portal | Selecione a página com `[dps_client_portal]` |
| Permitir Edição | Se clientes podem editar dados |
| Exibir Financeiro | Se pendências são visíveis |
| Exibir Fidelidade | Se pontos aparecem no portal |

**Sistema de Tokens (Acesso sem Senha):**

O portal usa "magic links" em vez de senhas:

1. Administrador gera token para cliente
2. Link é enviado via WhatsApp ou e-mail
3. Cliente acessa com o link (válido por tempo limitado)
4. Tokens podem ser temporários (30min) ou permanentes (até revogação)

**Gerenciamento de Acessos:**
- Acesse **DPS by PRObst > Logins de Clientes**
- Gere tokens, revogue acessos, visualize histórico

---

### 7. Assistente de IA Add-on

**Propósito:** Chat inteligente para atendimento automatizado

**Requisitos:**
- Conta na OpenAI com API key ativa
- Portal do Cliente ativo

**Configuração:**

1. Acesse **DPS by PRObst > Assistente de IA**
2. Configure:

| Campo | Descrição | Recomendação |
|-------|-----------|--------------|
| Ativar IA | Habilita o assistente | Sim |
| API Key | Chave da OpenAI (sk-...) | Obrigatória |
| Modelo | GPT-3.5-turbo ou GPT-4 | GPT-3.5 (custo/benefício) |
| Temperatura | Criatividade (0-1) | 0.4 (equilibrado) |
| Max Tokens | Limite de resposta | 500 |
| Timeout | Tempo máximo | 10 segundos |

**Instruções Adicionais:**

Você pode personalizar o comportamento da IA:

```
Instruções Adicionais (máx. 2000 caracteres):
"Seja sempre simpático e use emojis. 
Quando perguntarem sobre horários, sugira os períodos da manhã.
Mencione nosso programa de fidelidade quando apropriado."
```

**Escopo de Atuação:**

A IA responde APENAS sobre:
- ✅ Banho e Tosa
- ✅ Serviços oferecidos
- ✅ Agendamentos e histórico
- ✅ Pagamentos e fidelidade
- ❌ Assuntos aleatórios (política, esportes, etc.)
- ❌ Questões médicas veterinárias (orienta procurar veterinário)

---

### 8. Cadastro Público Add-on

**Propósito:** Formulário para novos clientes se cadastrarem

**Configuração:**

1. Crie uma página pública com:
   ```
   [dps_registration_form]
   ```

2. Configure em **DPS by PRObst > Cadastro Público**:

| Opção | Descrição |
|-------|-----------|
| Google Maps API | Chave para autocomplete de endereço |
| Campos Obrigatórios | Quais campos são necessários |
| Permitir Múltiplos Pets | Se pode cadastrar mais de 1 pet |
| Código de Indicação | Captura código do programa de fidelidade |

**Integração com Google Maps:**

1. Acesse [Google Cloud Console](https://console.cloud.google.com)
2. Crie projeto ou selecione existente
3. Ative a API **Places API** e **Maps JavaScript API**
4. Crie chave de API e restrinja ao seu domínio
5. Cole a chave no campo **Google Maps API Key**

---

### 9. Campanhas & Fidelidade Add-on

**Propósito:** Programa de pontos e indicações "Indique e Ganhe"

**Configuração:**

1. Acesse **DPS by PRObst > Campanhas & Fidelidade**
2. Configure o programa de pontos:

| Opção | Descrição | Exemplo |
|-------|-----------|---------|
| Pontos por R$ | Quantos pontos por real gasto | 1 ponto = R$ 1 |
| Bônus Indicador | Pontos para quem indica | 100 pontos |
| Bônus Indicado | Pontos para quem foi indicado | 50 pontos |
| Resgate Mínimo | Pontos mínimos para resgatar | 500 pontos |

**Sistema de Indicação:**

1. Cliente recebe código único (ex: `MARIA123`)
2. Novo cliente usa o código no cadastro
3. Quando novo cliente paga primeira cobrança:
   - Indicador ganha bônus
   - Indicado ganha bônus
4. Pontos aparecem no Portal do Cliente

---

### 10. Notificações Push Add-on

**Propósito:** Envio automático de notificações para equipe

**Configuração:**

1. Acesse **DPS by PRObst > Notificações**
2. Configure canais:

**Telegram:**
| Campo | Descrição |
|-------|-----------|
| Bot Token | Token do bot (@BotFather) |
| Chat ID | ID do grupo/canal para notificações |

**E-mail:**
| Campo | Descrição |
|-------|-----------|
| Destinatários | Lista de e-mails separados por vírgula |

**Notificações Automáticas:**
- ✅ Resumo diário da agenda
- ✅ Relatório financeiro diário
- ✅ Alertas de estoque baixo
- ✅ Relatório semanal de clientes inativos

---

### 11. Estatísticas Add-on

**Propósito:** Relatórios e métricas de desempenho

**Configuração:**

Não requer configuração específica. Acesse a aba **Estatísticas** no painel.

**Métricas Disponíveis:**
- Atendimentos por período
- Receita por período
- Serviços mais procurados
- Clientes inativos
- Distribuição por espécie/raça
- Comparativo mensal

---

### 12. Groomers Add-on

**Propósito:** Gestão de profissionais/tosadores com portal exclusivo

**Configuração:**

1. Acesse a aba **Groomers** no painel
2. Cadastre profissionais:
   - Nome e e-mail
   - Telefone
   - Percentual de comissão
   - Status (ativo/inativo)
3. Vincule agendamentos a groomers específicos

**Funcionalidades:**
- CRUD completo: cadastro, edição e exclusão de groomers
- Vinculação de múltiplos groomers por atendimento
- Exportação de relatórios em CSV

**Relatórios de Produtividade:**
- Cards com métricas: atendimentos, receita total, ticket médio
- Filtro por profissional e período
- Coluna de pet na tabela de resultados
- Exportação para CSV com totais

**Portal do Groomer (Acesso via Token):**

O groomer possui um portal exclusivo para acompanhar sua agenda e desempenho:

1. Acesse **Configurações DPS > Logins de Groomers**
2. Selecione o tipo de token:
   - **Temporário (30 min)**: ideal para envio por WhatsApp
   - **Permanente**: válido até revogação manual
3. Clique em **Gerar Link** e envie ao profissional
4. Groomer acessa dashboard, agenda semanal e avaliações

| Funcionalidade | Descrição |
|---------------|-----------|
| Dashboard | Métricas pessoais com gráficos |
| Agenda Semanal | Visualização de agendamentos |
| Avaliações | Feedback dos clientes |
| Comissões | Valores a receber |

---

### 13. Estoque Add-on

**Propósito:** Controle de insumos e produtos

**Configuração:**

1. Acesse a aba **Estoque** no painel
2. Cadastre itens:

| Campo | Descrição |
|-------|-----------|
| Nome do Item | "Shampoo Neutro 5L" |
| Quantidade Atual | 10 unidades |
| Quantidade Mínima | 3 unidades (alerta) |
| Unidade de Medida | Litros, Unidades, Kg |

**Funcionalidades:**
- Entrada/saída manual de estoque
- Baixa automática ao concluir atendimentos
- Alertas de estoque baixo
- Histórico de movimentações

---

### 14. Assinaturas Add-on

**Propósito:** Gerenciamento de planos recorrentes

**Requisitos:**
- Financeiro Add-on ativo
- Pagamentos Add-on ativo

**Configuração:**

1. Configure planos de assinatura:
   - Nome do plano (ex: "Plano Mensal Premium")
   - Valor mensal
   - Frequência (semanal, quinzenal, mensal)
   - Serviços incluídos
   - Desconto sobre avulso

2. Vincule clientes a planos
3. Sistema gera cobranças e atendimentos automaticamente

**Tipos de Frequência:**
- **Semanal**: 4 atendimentos por mês
- **Quinzenal**: 2 atendimentos por mês
- **Mensal**: 1 atendimento por mês

---

### 15. Backup & Restauração Add-on

**Propósito:** Exportar e restaurar dados do sistema

**Funcionalidades:**

**Exportação:**
1. Acesse **DPS by PRObst > Backup & Restauração**
2. Clique em **Exportar Dados**
3. Sistema gera arquivo JSON com todos os dados:
   - Clientes
   - Pets
   - Agendamentos
   - Transações
   - Configurações

**Restauração:**
1. Clique em **Importar Dados**
2. Selecione arquivo JSON de backup
3. Sistema valida estrutura
4. Confirme para restaurar

**⚠️ Atenção:**
- Faça backup ANTES de restaurar
- Restauração sobrescreve dados existentes
- Apenas administradores podem executar

---

### 16. Debugging Add-on

**Propósito:** Gerenciar constantes de debug do WordPress e visualizar logs de erro

Este add-on é essencial para desenvolvedores e administradores que precisam diagnosticar problemas no sistema. Ele permite ativar/desativar constantes de debug do WordPress diretamente pela interface administrativa.

**Configuração:**

1. Acesse **DPS by PRObst > Debugging**
2. Configure as constantes de debug:

| Constante | Descrição | Padrão |
|-----------|-----------|--------|
| `WP_DEBUG` | Ativa modo debug do WordPress | Desabilitado |
| `WP_DEBUG_LOG` | Salva erros em debug.log | Desabilitado |
| `WP_DEBUG_DISPLAY` | Exibe erros na tela | Desabilitado |
| `SCRIPT_DEBUG` | Carrega versões não minificadas de JS/CSS | Desabilitado |
| `SAVEQUERIES` | Salva queries do banco para análise | Desabilitado |
| `WP_DISABLE_FATAL_ERROR_HANDLER` | Desabilita tratador de erros fatais | Desabilitado |

**Funcionalidades:**

- **Visualizador de Logs**: Exibe o arquivo debug.log com formatação inteligente
  - Destaque visual por tipo de erro (Fatal, Warning, Notice, Deprecated)
  - Formatação de stack traces como lista
  - Pretty-print de JSON encontrado nas entradas
  - Ordenação mais recente primeiro
- **Limpeza de Logs**: Botão para limpar o arquivo debug.log
- **Admin Bar**: Status das constantes e contador de entradas de log na barra administrativa

**⚠️ Importante:**
- Desative o debug em produção para melhor performance e segurança
- Logs podem conter informações sensíveis
- Apenas administradores podem acessar

---

### 17. White Label Add-on

**Propósito:** Personalizar o sistema DPS com sua própria marca, cores e identidade visual

Este add-on permite que parceiros e revendedores personalizem completamente o sistema, substituindo a marca "DPS by PRObst" pela marca do cliente ou empresa.

**Configuração:**

1. Acesse **DPS by PRObst > White Label**
2. Configure a identidade visual:

| Campo | Descrição |
|-------|-----------|
| Nome da Marca | Substitui "DPS by PRObst" em todo o sistema |
| Tagline/Slogan | Texto de apresentação personalizado |
| Logo | URL do logo personalizado (usa biblioteca de mídia) |
| Favicon | Ícone personalizado para abas do navegador |

3. Configure as cores do tema:

| Cor | Descrição | Padrão |
|-----|-----------|--------|
| Primária | Cor principal do sistema | #0ea5e9 (azul) |
| Secundária | Cor de destaque | #10b981 (verde) |
| Fundo | Cor de fundo | #f9fafb (cinza claro) |
| Texto | Cor do texto principal | #374151 (cinza escuro) |

4. Configure informações de contato:

| Campo | Descrição |
|-------|-----------|
| E-mail de Suporte | E-mail para contato do cliente |
| WhatsApp | Número do WhatsApp da empresa |
| URL de Suporte | Link para página de suporte |

**Módulos Adicionais:**

| Módulo | Descrição |
|--------|-----------|
| **SMTP** | Configuração de servidor de e-mail personalizado |
| **Página de Login** | Personalização visual da tela de login do WordPress |
| **Admin Bar** | Customização da barra administrativa |
| **Dashboard** | Controle de widgets no dashboard WordPress |
| **Modo Manutenção** | Página de manutenção personalizada |
| **Logs de Atividade** | Registro de ações no sistema |

**Funcionalidades:**
- Substituição completa da marca em todo o sistema
- CSS customizado adicional
- Personalização de e-mails (remetente, rodapé)
- Personalização de mensagens WhatsApp
- Opção para ocultar "Powered by DPS"

**⚠️ Importante:**
- Apenas administradores podem configurar
- Requer licença válida para funcionalidades avançadas
- Para documentação completa, consulte `docs/analysis/WHITE_LABEL_ANALYSIS.md`

---

## 📖 Uso do Sistema

### Painel Principal

Acesse a página com shortcode `[dps_base]` para visualizar o painel principal.

**Navegação por Abas:**

```
[Clientes] [Pets] [Agendamentos] [Histórico] [+ Abas dos Add-ons]
```

Cada aba apresenta:
- Lista de registros com busca e filtros
- Botões de ação (adicionar, editar, excluir)
- Paginação para navegação

### Gestão de Clientes

**Adicionar Cliente:**

1. Clique na aba **Clientes**
2. Preencha o formulário:
   - **Nome**: nome completo do cliente
   - **Telefone**: com DDD (ex: 15991234567)
   - **E-mail**: endereço de e-mail
   - **Endereço**: endereço completo
   - **Observações**: informações adicionais
3. Clique em **Salvar Cliente**

**Editar Cliente:**
1. Localize o cliente na lista
2. Clique no ícone de edição (✏️)
3. Altere os dados necessários
4. Salve as alterações

**Excluir Cliente:**
1. Clique no ícone de exclusão (🗑️)
2. Confirme a ação
3. ⚠️ Dados financeiros vinculados também serão removidos

### Gestão de Pets

**Adicionar Pet:**

1. Clique na aba **Pets**
2. Selecione o **Cliente** proprietário
3. Preencha:
   - **Nome do Pet**: nome do animal
   - **Espécie**: Cachorro, Gato, etc.
   - **Raça**: raça do pet
   - **Porte**: Pequeno, Médio ou Grande
   - **Observações**: características, temperamento, etc.
4. Clique em **Salvar Pet**

**Relacionamento:**
- Um cliente pode ter múltiplos pets
- Cada pet pertence a apenas um cliente
- Ao selecionar cliente no agendamento, seus pets são carregados automaticamente

### Agendamentos

**Criar Agendamento:**

1. Clique na aba **Agendamentos**
2. Selecione:
   - **Cliente**: busque pelo nome
   - **Pets**: selecione um ou mais pets do cliente
   - **Data**: data do atendimento
   - **Horário**: hora do atendimento
   - **Serviços**: selecione os serviços (se add-on ativo)
   - **Tipo**: Simples, Assinatura ou Passado
3. Clique em **Salvar Agendamento**

**Status de Agendamento:**

| Status | Descrição | Cor |
|--------|-----------|-----|
| Agendado | Aguardando atendimento | 🟡 Amarelo |
| Realizado | Atendimento concluído | 🟢 Verde |
| Cancelado | Cancelado pelo cliente/loja | 🔴 Vermelho |

**Ações Rápidas:**
- ✅ Marcar como realizado
- ❌ Cancelar agendamento
- 📱 Enviar lembrete via WhatsApp
- 💰 Gerar cobrança

### Histórico de Atendimentos

A aba **Histórico** exibe todos os agendamentos finalizados.

**Filtros Disponíveis:**
- Por período (data inicial e final)
- Por cliente
- Por pet
- Por status

**Exportação:**
1. Aplique os filtros desejados
2. Clique em **Exportar CSV**
3. Arquivo gerado com dados filtrados

### Gestão Financeira

**Visualizar Pendências:**
1. Acesse aba **Financeiro**
2. Veja lista de transações pendentes
3. Filtrar por cliente, período ou status

**Registrar Pagamento:**
1. Localize a transação
2. Clique em **Registrar Pagamento**
3. Informe valor pago e método
4. Sistema atualiza status

**Gerar Link de Pagamento:**
1. Localize transação pendente
2. Clique em **Gerar Link PIX**
3. Copie link gerado
4. Envie ao cliente via WhatsApp

---

## 🔥 Recursos Avançados

### Tipos de Agendamento

O sistema suporta três tipos de agendamento:

**1. Agendamento Simples**
- Atendimento único, sem recorrência
- Pode incluir TaxiDog (com valor)
- Status inicial: Agendado

**2. Agendamento de Assinatura**
- Parte de um plano recorrente
- Frequência: semanal ou quinzenal
- Pode incluir tosa opcional (mensal ou variável)
- TaxiDog sem custo adicional (incluído no plano)

**3. Agendamento Passado**
- Para registrar atendimentos anteriores
- Status automático: Realizado
- Permite registrar pagamentos pendentes históricos
- Útil para migração de dados

### Sistema de Assinaturas

**Benefícios para o Pet Shop:**
- Receita recorrente garantida
- Fidelização de clientes
- Previsibilidade de agenda

**Benefícios para o Cliente:**
- Desconto sobre preço avulso
- Agendamento automático
- TaxiDog incluso (conforme plano)

**Fluxo de Assinatura:**
1. Cliente adere ao plano
2. Sistema gera agendamentos automáticos
3. Cobranças são geradas mensalmente
4. Links de pagamento enviados automaticamente

### Programa de Fidelidade

**Acúmulo de Pontos:**
- A cada R$ 1 gasto = X pontos (configurável)
- Bônus por indicação (indicador e indicado)
- Pontos expiram após 12 meses (configurável)

**Resgate:**
- Pontos podem ser trocados por descontos
- Resgate mínimo configurável
- Histórico de resgates no portal

**Indicação (Indique e Ganhe):**
1. Cliente existente recebe código único
2. Compartilha com amigos
3. Novo cliente cadastra usando o código
4. Após primeiro pagamento, ambos ganham pontos

### Integração com WhatsApp

**Mensagens Automáticas:**
- Confirmação de agendamento
- Lembrete (1 dia antes)
- Pós-atendimento (agradecimento)
- Cobrança de pendências

**Mensagens Manuais:**
- Botão de WhatsApp em cada cliente/agendamento
- Abre conversa com número do cliente
- Mensagem pré-formatada (opcional)

**Classe Helper:**
```php
// Exemplo de uso do DPS_WhatsApp_Helper
$url = DPS_WhatsApp_Helper::get_link_to_client(
    $client_phone,
    "Olá! Aqui é da DPS by PRObst..."
);
```

---

## 🔄 Manutenção e Atualizações

### Backup do Sistema

**Backup Automático (Recomendado):**
1. Use plugin de backup do WordPress (ex: UpdraftPlus)
2. Configure backup diário do banco de dados
3. Configure backup semanal dos arquivos

**Backup Manual (DPS):**
1. Acesse **DPS by PRObst > Backup**
2. Clique em **Exportar Todos os Dados**
3. Salve o arquivo JSON em local seguro
4. Faça isso ANTES de atualizações

### Atualizações

**Processo de Atualização:**

1. **Antes de atualizar:**
   - Faça backup completo do banco de dados
   - Exporte dados pelo add-on de Backup
   - Teste em ambiente de staging (se disponível)

2. **Durante a atualização:**
   - Acesse **Plugins**
   - Atualize plugin base primeiro
   - Depois atualize os add-ons

3. **Após atualizar:**
   - Verifique se o painel carrega corretamente
   - Teste criação de agendamento
   - Verifique integrações (pagamentos, WhatsApp)

**⚠️ Importante:**
- Sempre leia o CHANGELOG.md antes de atualizar
- Algumas versões podem requerer migração de dados
- Em caso de problemas, restaure o backup

### Resolução de Problemas

**Problema: Painel não carrega**

*Possíveis causas:*
1. Conflito com tema ou outro plugin
2. Erro de PHP no servidor
3. Cache desatualizado

*Soluções:*
1. Desative outros plugins temporariamente
2. Ative tema padrão do WordPress
3. Verifique logs de erro do PHP
4. Limpe cache do navegador e plugins de cache

---

**Problema: Shortcode não funciona**

*Possíveis causas:*
1. **Bloco incorreto no editor** (mais comum)
2. Plugin base desativado
3. Shortcode digitado incorretamente
4. Conflito com page builder

*Soluções:*
1. ⚠️ **Verifique o tipo de bloco usado**: Use o bloco **"Shortcode"** ou **"Parágrafo"**, **nunca** o bloco "Código" (Code)
2. Verifique se plugin base está ativo
3. Copie shortcode exato: `[dps_base]`
4. Consulte guia de compatibilidade com YooTheme/Elementor

> 💡 **Por que o bloco "Código" não funciona?**
>
> O bloco "Código" (Code) do editor Gutenberg foi projetado para **exibir** código como texto formatado, não para executá-lo. Quando você insere `[dps_base]` nesse bloco, o WordPress entende que você quer mostrar esse texto literalmente aos visitantes, então ele aparece como texto `[dps_base]` em vez de renderizar o painel.
>
> **Solução**: Mude o bloco para "Shortcode" ou "Parágrafo" (clique no bloco > clique no ícone do bloco na toolbar > Transformar em).

---

**Problema: Webhook de pagamento não funciona**

*Possíveis causas:*
1. Webhook secret não configurado
2. URL incorreta no Mercado Pago
3. Firewall bloqueando requisições

*Soluções:*
1. Verifique se secret está idêntico no DPS e MP
2. Teste URL no navegador
3. Verifique logs do servidor

---

**Problema: Portal do cliente não autentica**

*Possíveis causas:*
1. Token expirado
2. Sessão PHP não iniciada
3. Conflito de cache

*Soluções:*
1. Gere novo token de acesso
2. Verifique `session.auto_start` no PHP
3. Desative cache para página do portal

---

## 📚 Referência Técnica

### Shortcodes Disponíveis

> ⚠️ **Lembrete**: Insira shortcodes usando o bloco **"Shortcode"** ou **"Parágrafo"** do editor. **Não use o bloco "Código"** — ele exibe texto literalmente e não executa shortcodes.

| Shortcode | Add-on | Descrição |
|-----------|--------|-----------|
| `[dps_base]` | Base | Painel administrativo principal |
| `[dps_configuracoes]` | Base | Tela de configurações |
| `[dps_agenda_page]` | Agenda | Visualização da agenda |
| `[dps_client_portal]` | Portal | Portal do cliente |
| `[dps_client_login]` | Portal | Formulário de login do cliente |
| `[dps_registration_form]` | Cadastro | Formulário público de cadastro |
| `[dps_groomer_portal]` | Groomers | Portal completo do groomer |
| `[dps_groomer_login]` | Groomers | Página de login do groomer |
| `[dps_groomer_dashboard]` | Groomers | Dashboard individual (param: `groomer_id`) |
| `[dps_groomer_agenda]` | Groomers | Agenda semanal (param: `groomer_id`) |

### Roles e Capabilities

**Roles Customizados:**

| Role | Slug | Descrição |
|------|------|-----------|
| DPS Recepção | `dps_reception` | Acesso operacional ao sistema |
| DPS Groomer | `dps_groomer` | Acesso limitado (se add-on ativo) |

**Capabilities:**

| Capability | Descrição |
|------------|-----------|
| `manage_options` | Acesso total (administrador) |
| `dps_manage_clients` | Gerenciar cadastro de clientes |
| `dps_manage_pets` | Gerenciar cadastro de pets |
| `dps_manage_appointments` | Gerenciar agendamentos |
| `dps_manage_stock` | Gerenciar estoque |
| `dps_view_financials` | Visualizar dados financeiros |
| `dps_manage_financials` | Gerenciar transações |

### Estrutura de Dados

**CPTs (Custom Post Types):**

| CPT | Slug | Dados Principais |
|-----|------|-----------------|
| Clientes | `dps_client` | nome, telefone, email, endereço |
| Pets | `dps_pet` | nome, espécie, raça, porte, cliente_id |
| Agendamentos | `dps_appointment` | data, hora, status, cliente_id, pet_ids |
| Serviços | `dps_service` | nome, preços por porte, duração |
| Campanhas | `dps_campaign` | nome, período, regras |
| Assinaturas | `dps_subscription` | cliente_id, plano, frequência, valor |
| Estoque | `dps_stock_item` | nome, quantidade, mínimo |

**Tabelas Customizadas:**

| Tabela | Add-on | Propósito |
|--------|--------|-----------|
| `wp_dps_logs` | Base | Logs do sistema |
| `wp_dps_transacoes` | Financeiro | Lançamentos financeiros |
| `wp_dps_parcelas` | Financeiro | Parcelas de cobranças |
| `wp_dps_referrals` | Fidelidade | Indicações de clientes |
| `wp_dps_portal_tokens` | Portal | Tokens de acesso de clientes |
| `wp_dps_groomer_tokens` | Groomers | Tokens de acesso de groomers |
| `wp_dps_email_logs` | White Label | Logs de e-mails enviados |
| `wp_dps_activity_logs` | White Label | Logs de atividade no sistema |

---

## 📝 Manutenção desta Documentação

> **Importante:** Este documento deve ser atualizado sempre que houver:
> - Novas funcionalidades adicionadas ao sistema
> - Mudanças em configurações existentes
> - Novos add-ons criados
> - Alterações em processos ou fluxos
> - Correções de informações desatualizadas

**Como atualizar:**

1. Edite o arquivo `docs/GUIA_SISTEMA_DPS.md`
2. Mantenha a estrutura de seções existente
3. Adicione novas seções quando necessário
4. Atualize a versão e data no cabeçalho
5. Registre a atualização no `CHANGELOG.md`

**Padrões a seguir:**
- Use português brasileiro
- Mantenha linguagem clara e objetiva
- Inclua exemplos práticos sempre que possível
- Use tabelas para informações estruturadas
- Use emojis com moderação para melhor visualização

---

## 🔗 Links Úteis

### Documentação Interna
- [ANALYSIS.md](../ANALYSIS.md) - Arquitetura técnica do sistema
- [AGENTS.md](../AGENTS.md) - Diretrizes para desenvolvedores
- [CHANGELOG.md](../CHANGELOG.md) - Histórico de versões
- [Guia Visual](visual/VISUAL_STYLE_GUIDE.md) - Padrões de design
- [Análise White Label](analysis/WHITE_LABEL_ANALYSIS.md) - Documentação completa do White Label Add-on
- [Análise de Compatibilidade](compatibility/COMPATIBILITY_ANALYSIS.md) - Compatibilidade PHP/WordPress/Astra

### Configuração de Integrações
- [Configuração de Webhook](../add-ons/desi-pet-shower-payment_addon/WEBHOOK_CONFIGURATION.md)
- [Sistema de Tokens](../add-ons/desi-pet-shower-client-portal_addon/TOKEN_AUTH_SYSTEM.md)
- [Compatibilidade YooTheme](compatibility/YOOTHEME_COMPATIBILITY.md)

### Recursos Externos
- [Documentação WordPress](https://developer.wordpress.org/)
- [API Mercado Pago](https://www.mercadopago.com.br/developers/pt)
- [API OpenAI](https://platform.openai.com/docs)

---

<div align="center">

**DPS by PRObst** - Sistema completo de gestão para pet shops

*Desenvolvido com 💜 para facilitar o dia a dia do seu pet shop*

---

*Este documento faz parte da documentação oficial do sistema DPS.*
*Para dúvidas ou sugestões, consulte a equipe de desenvolvimento.*

</div>
