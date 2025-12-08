# Portal do Cliente - Documentação Visual

Este documento apresenta o estado atual do Portal do Cliente do sistema DPS (Desi Pet Shower).

> 📋 **Demonstração Interativa:** Para visualizar o portal em ação, abra o arquivo [`docs/layout/client-portal/portal-cliente-demo.html`](../layout/client-portal/portal-cliente-demo.html) em um navegador.

## Visão Geral

O Portal do Cliente é uma interface web desenvolvida com um design minimalista e clean, seguindo as diretrizes visuais do DPS. O portal permite que os clientes:

- ✅ Visualizem seus próximos agendamentos
- ✅ Acompanhem pendências financeiras
- ✅ Consultem o histórico de atendimentos
- ✅ Vejam fotos dos seus pets após os serviços
- ✅ Troquem mensagens com a equipe
- ✅ Gerenciem seus dados pessoais e informações dos pets

## Características Visuais

### Paleta de Cores
- **Base neutra**: `#f9fafb` (fundos), `#e5e7eb` (bordas), `#374151` (texto principal)
- **Destaque**: `#0ea5e9` (azul) para ações e links importantes
- **Status**: Verde para confirmações, Amarelo para pendências, Cinza para concluídos

### Princípios de Design
- Design minimalista sem elementos decorativos desnecessários
- Espaçamento generoso (20px padding, 32px entre seções)
- Bordas padronizadas (1px solid #e5e7eb)
- Tipografia limpa e legível
- Totalmente responsivo (desktop e mobile)

## Componentes do Portal

### 1. 🧭 Navegação do Portal

A barra de navegação permite acesso rápido a todas as seções do portal através de links âncora.

```html
<nav class="dps-portal-nav">
  <a href="#proximos">Próximos</a>
  <a href="#pendencias">Pendências</a>
  <a href="#historico">Histórico</a>
  <a href="#galeria">Galeria</a>
  <a href="#mensagens">Mensagens</a>
  <a href="#dados">Meus Dados</a>
</nav>
```

**Elementos:**
- ✓ Links para: Próximos, Pendências, Histórico, Galeria, Mensagens, Meus Dados
- ✓ Design responsivo que se adapta em mobile (vertical)
- ✓ Hover state com cor azul de destaque (`#0ea5e9`)
- ✓ Bordas sutis e padding confortável

---

### 2. 📅 Próximo Agendamento

Card visual destacando o próximo agendamento do cliente com destaque especial.

**Layout:**
```
┌─────────────────────────────────────┐
│  ┌────┐  ⏰ 14:30                   │
│  │ 25 │  🐾 Thor (Golden Retriever) │
│  │Nov │  ✂️ Banho e Tosa Completa   │
│  └────┘  [Confirmado]               │
│         📍 Ver no mapa              │
└─────────────────────────────────────┘
```

**Funcionalidades:**
- ✓ Data em destaque com dia e mês em card azul
- ✓ Horário, pet e serviços claramente identificados
- ✓ Status do agendamento com badge visual
- ✓ Link para visualizar localização no Google Maps
- ✓ Design adaptável para mobile (layout vertical)

---

### 3. 💰 Pendências Financeiras

Tabela com pendências de pagamento do cliente com alerta visual.

**Exemplo de Dados:**
```
⚠️ Você tem 2 pendência(s) totalizando R$ 285,00

Data       | Descrição              | Valor     | Ação
-----------|------------------------|-----------|-------
15/11/2024 | Banho e Tosa - Thor    | R$ 150,00 | [Pagar]
10/11/2024 | Hidratação - Mel       | R$ 135,00 | [Pagar]
```

**Funcionalidades:**
- ✓ Alerta visual em amarelo mostrando total de pendências
- ✓ Tabela responsiva com data, descrição, valor e ação
- ✓ Botões verdes para pagamento direto
- ✓ Design responsivo que converte tabela em cards em mobile
- ✓ Integração com sistema de pagamentos

---

### 4. 📋 Histórico de Atendimentos

Tabela completa com todos os atendimentos anteriores do cliente.

**Exemplo:**
```
Data       | Horário | Pet  | Serviços              | Status
-----------|---------|------|-----------------------|-------------
25/11/2024 | 14:30   | Thor | Banho e Tosa Completa | [Confirmado]
15/11/2024 | 10:00   | Thor | Banho e Tosa          | [Concluído]
12/11/2024 | 15:30   | Mel  | Hidratação Profunda   | [Concluído]
05/11/2024 | 09:00   | Thor | Banho                 | [Concluído]
```

**Funcionalidades:**
- ✓ Listagem cronológica de atendimentos (mais recentes primeiro)
- ✓ Informações: Data, Horário, Pet, Serviços, Status
- ✓ Badges coloridos de status:
  - Verde: Confirmado/Pago
  - Amarelo: Pendente
  - Cinza: Concluído
  - Vermelho: Cancelado
- ✓ Paginação para grandes volumes de dados
- ✓ Responsivo: tabela vira cards em mobile

---

### 5. 📸 Galeria de Fotos

Grid responsivo com fotos dos pets após os serviços realizados.

**Layout Grid (Desktop: 3 colunas):**
```
┌─────────┐ ┌─────────┐ ┌─────────┐
│ [Foto]  │ │ [Foto]  │ │ [Foto]  │
│ Thor    │ │ Mel     │ │ Thor    │
│15/11/24 │ │12/11/24 │ │05/11/24 │
│Banho    │ │Hidrat.  │ │Banho    │
│[WhatsApp│ │[WhatsApp│ │[WhatsApp│
└─────────┘ └─────────┘ └─────────┘
```

**Funcionalidades:**
- ✓ Grid adaptativo (3 colunas desktop, 2 tablet, 1 mobile)
- ✓ Informações do pet e data do serviço
- ✓ Botão para compartilhar foto via WhatsApp
- ✓ Imagens com aspect ratio 4:3 consistente
- ✓ Placeholder quando não há fotos disponíveis

---

### 6. 💬 Centro de Mensagens

Sistema de mensagens bidirecionais entre cliente e equipe DPS.

**Exemplo de Conversa:**
```
┌─────────────────────────────────────────┐
│ 👤 Equipe DPS - 18/11/2024 às 15:45    │
│ Olá! Gostaríamos de confirmar seu      │
│ agendamento para o dia 25/11 às 14:30. │
│ O Thor está pronto para ficar ainda    │
│ mais bonito! 🐾                         │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ 😊 Você - 18/11/2024 às 16:20          │
│ Confirmado! Estaremos lá no horário.    │
│ Obrigada! 😊                            │
└─────────────────────────────────────────┘
```

**Funcionalidades:**
- ✓ Histórico de conversas com diferenciação visual:
  - Azul: Mensagens da equipe
  - Verde: Mensagens do cliente
- ✓ Formulário para enviar novas mensagens
- ✓ Scroll automático para mensagens mais recentes
- ✓ Timestamps em cada mensagem
- ✓ Limite de altura com scroll interno

---

### 7. 👤 Meus Dados

Formulário completo para gerenciamento de dados pessoais e dos pets.

**Seções Organizadas:**

**📝 Dados Pessoais**
- Nome Completo
- CPF

**📞 Contato**
- Telefone / WhatsApp
- Email

**📍 Endereço**
- Endereço completo (textarea)

**📱 Redes Sociais (Opcional)**
- Instagram
- Facebook

**🐾 Meus Pets**

*Pet 1: Thor*
- Nome: Thor
- Raça: Golden Retriever
- Data de Nascimento: 15/03/2020
- Peso: 30kg
- Observações: Muito dócil e brincalhão...

*Pet 2: Mel*
- Nome: Mel
- Raça: Poodle
- Data de Nascimento: 20/07/2021
- Peso: 6kg
- Observações: Muito carinhosa...

**Funcionalidades:**
- ✓ Fieldsets organizados por categoria
- ✓ Múltiplos pets com formulários separados
- ✓ Campos apropriados para cada tipo de dado
- ✓ Botões de salvamento por seção
- ✓ Validação de campos obrigatórios
- ✓ Autocomplete para melhor UX

---

### 8. 📱 Versão Mobile

Vista mobile do portal demonstrando a adaptação responsiva.

**Breakpoints:**
- `@media (max-width: 640px)` - Mobile
- `@media (min-width: 768px)` - Tablet
- `@media (min-width: 1024px)` - Desktop

**Adaptações Mobile:**
- ✓ Navegação vertical (links empilhados)
- ✓ Tabelas convertidas em cards com data-labels
- ✓ Grid de galeria: 1 coluna
- ✓ Card de agendamento: layout vertical
- ✓ Imagens e layouts adaptados
- ✓ Botões e formulários otimizados para touch
- ✓ Font-size adequado para leitura mobile

## Implementação Técnica

O Portal do Cliente é implementado como um add-on no sistema DPS:

**Localização:** `add-ons/desi-pet-shower-client-portal_addon/`

**Arquivos principais:**
- `desi-pet-shower-client-portal.php` - Plugin principal
- `assets/css/client-portal.css` - Estilos CSS
- `assets/js/client-portal.js` - Interatividade JavaScript
- `templates/` - Templates PHP para renderização

**Demo HTML:**
- `docs/layout/client-portal/portal-cliente-demo.html` - Versão demo standalone

## Integração com o Sistema

O portal integra com:

- **Agendamentos:** CPT `dps_agendamento`
- **Financeiro:** Tabela `dps_transacoes` e `dps_parcelas`
- **Galeria:** Sistema de upload de fotos vinculado aos atendimentos
- **Mensagens:** Sistema próprio de mensagens cliente-equipe
- **Autenticação:** Sistema de tokens e sessões seguro

## Acessibilidade

O portal foi desenvolvido seguindo práticas de acessibilidade:

- Estrutura semântica HTML5
- Labels apropriados em formulários
- Contraste de cores adequado (WCAG AA)
- Navegação por teclado funcional
- ARIA roles e atributos quando necessário

## Próximos Passos

Melhorias planejadas para versões futuras:

- [ ] **Pagamentos Online:** Integração com Mercado Pago/PagSeguro/Stripe
- [ ] **Notificações Push:** Alertas para novos agendamentos/mensagens
- [ ] **Upload de Fotos:** Cliente pode fazer upload de fotos dos pets
- [ ] **Agendamento Online:** Cliente pode agendar serviços diretamente
- [ ] **Programa de Fidelidade:** Pontos e recompensas integrados
- [ ] **Chat em Tempo Real:** WebSockets para mensagens instantâneas
- [ ] **Avaliações:** Sistema de avaliação dos serviços recebidos
- [ ] **Lembretes Automáticos:** Email/SMS antes dos agendamentos

---

## Como Visualizar

### Opção 1: Demo HTML (Recomendado)

1. Abra o arquivo em um navegador:
   ```bash
   open docs/layout/client-portal/portal-cliente-demo.html
   # ou
   firefox docs/layout/client-portal/portal-cliente-demo.html
   # ou
   google-chrome docs/layout/client-portal/portal-cliente-demo.html
   ```

2. Para servidor local com live reload:
   ```bash
   cd docs/layout/client-portal
   python3 -m http.server 8080
   # Acesse: http://localhost:8080/portal-cliente-demo.html
   ```

### Opção 2: Instalação WordPress

1. Instale o plugin base do DPS
2. Ative o add-on Client Portal
3. Configure uma página com o shortcode:
   ```
   [dps_client_portal]
   ```

### Opção 3: Ambiente de Desenvolvimento

Para desenvolvimento e testes:

```bash
# Clone o repositório
git clone https://github.com/richardprobst/DPS.git
cd DPS

# Configure ambiente WordPress local
# (wp-env, Local by Flywheel, XAMPP, etc.)

# Ative os plugins
wp plugin activate desi-pet-shower-base_plugin
wp plugin activate desi-pet-shower-client-portal
```

---

## Arquivos e Estrutura

### Documentação
- `docs/screenshots/PORTAL_CLIENTE_SCREENSHOTS.md` - Este documento
- `docs/layout/client-portal/portal-cliente-demo.html` - Demo interativo
- `docs/layout/ADMIN_LAYOUT_ANALYSIS.md` - Análise de layout admin
- `docs/visual/VISUAL_STYLE_GUIDE.md` - Guia de estilo visual

### Código Fonte
```
add-ons/desi-pet-shower-client-portal_addon/
├── desi-pet-shower-client-portal.php  # Plugin principal
├── assets/
│   ├── css/
│   │   └── client-portal.css          # Estilos do portal
│   └── js/
│       └── client-portal.js           # JavaScript
├── includes/
│   ├── class-dps-client-portal.php    # Classe principal
│   ├── class-dps-portal-session-manager.php
│   ├── class-dps-portal-token-manager.php
│   └── client-portal/
│       ├── class-dps-portal-renderer.php
│       ├── class-dps-portal-data-provider.php
│       ├── class-dps-portal-actions-handler.php
│       └── ...
└── templates/
    ├── portal-access.php              # Tela de acesso
    ├── portal-settings.php            # Configurações admin
    └── ...
```

---

## Tecnologias Utilizadas

### Frontend
- **HTML5 Semântico:** Estrutura acessível e clara
- **CSS3 Custom Properties:** Variáveis CSS para white-label
- **JavaScript Vanilla:** Sem dependências jQuery
- **Responsive Design:** Mobile-first approach
- **Accessibility:** WCAG 2.1 AA compliance

### Backend
- **WordPress:** v5.8+
- **PHP:** v7.4+ (compatível com 8.x)
- **MySQL:** Tabelas personalizadas para dados do portal
- **REST API:** Endpoints para AJAX e integrações

### Segurança
- ✅ **Nonces:** Proteção CSRF em todos os formulários
- ✅ **Capabilities:** Verificação de permissões WordPress
- ✅ **Sanitização:** Todos os inputs são sanitizados
- ✅ **Escape:** Todos os outputs são escapados
- ✅ **Tokens de Acesso:** Sistema de autenticação seguro
- ✅ **Sessions:** Gerenciamento seguro de sessões

---

## Suporte e Contato

**Desenvolvedor:** PRObst  
**Website:** [www.probst.pro](https://www.probst.pro)  
**Projeto:** DPS - Desi Pet Shower Management System  
**Repositório:** [github.com/richardprobst/DPS](https://github.com/richardprobst/DPS)

Para reportar bugs ou sugerir melhorias, abra uma issue no GitHub.

---

**Última atualização:** 08 de Dezembro de 2025  
**Versão do Portal:** 1.0  
**Status:** ✅ Produção
