# Portal do Cliente DPS - Demonstração HTML

## 📄 Sobre este arquivo

`portal-cliente-demo.html` é um arquivo HTML estático que simula o Portal do Cliente do Desi Pet Shower (DPS) com dados fictícios para fins de análise de UX, layout e apresentação.

## 🎯 Objetivo

Permitir que stakeholders, designers e desenvolvedores visualizem e analisem a interface do portal do cliente sem necessidade de:
- Instalar WordPress
- Configurar banco de dados
- Ativar plugins
- Criar usuários de teste

## 📋 Conteúdo Simulado

O arquivo demonstra todas as seções principais do portal:

### 1. Navegação Interna
- Menu de âncoras para navegação rápida
- Links para: Próximos, Pendências, Histórico, Galeria, Mensagens, Meus Dados

### 2. Próximo Agendamento
- Card destacado com data, horário e detalhes
- Pet: Thor (Golden Retriever)
- Serviço: Banho e Tosa Completa
- Data: 25/11/2024 às 14:30
- Link para visualizar no mapa

### 3. Pendências Financeiras
- Alert visual com total de pendências (R$ 285,00)
- Tabela com 2 transações pendentes:
  - Banho e Tosa - Thor: R$ 150,00 (15/11/2024)
  - Hidratação - Mel: R$ 135,00 (10/11/2024)
- Botões de pagamento (simulados)

### 4. Histórico de Atendimentos
- Tabela responsiva com 6 atendimentos
- Dados mostrados: Data, Horário, Pet, Serviços, Status
- Status variados: Confirmado, Concluído
- Conversão para cards em mobile (< 640px)

### 5. Galeria de Fotos
- Grid de 6 fotos (usando placeholders do placedog.net)
- 2 pets: Thor e Mel
- Datas e serviços realizados
- Botões de compartilhamento via WhatsApp

### 6. Centro de Mensagens
- 5 mensagens trocadas entre cliente e equipe
- Mensagens da equipe (borda azul)
- Mensagens do cliente (borda verde)
- Formulário para enviar nova mensagem

### 7. Meus Dados
- Dados pessoais: Maria Silva Santos, CPF
- Contato: Telefone (51) 99999-8888, Email
- Endereço: Rua das Flores, 123, Porto Alegre - RS
- Redes sociais: Instagram e Facebook
- Dados de 2 pets (Thor e Mel) com informações completas

## 🎨 Design e Estilo

O arquivo implementa o **guia de estilo minimalista** do DPS:

### Paleta de Cores
- **Base neutra**: `#f9fafb` (fundos), `#e5e7eb` (bordas), `#374151` (texto)
- **Destaque**: `#0ea5e9` (azul) para ações e links
- **Status**:
  - Verde `#10b981` → sucesso, confirmações
  - Amarelo `#f59e0b` → avisos, pendências
  - Vermelho `#ef4444` → erros, cancelamentos
  - Cinza `#f3f4f6` → neutro

### Princípios Visuais
- ✅ Menos é mais: sem sombras decorativas ou gradientes
- ✅ Cores com propósito: status, alertas e ações
- ✅ Espaçamento generoso: 20px padding, 32px entre seções
- ✅ Bordas padronizadas: 1px para separadores, 4px para destaque
- ✅ Tipografia limpa: 24px (H1), 20px (H2), 18px (H3)

### Hierarquia Semântica
- `<h1>`: Título principal do portal
- `<h2>`: Seções principais (Próximo Agendamento, Histórico, etc.)
- `<h3>`: Subtítulos (Enviar nova mensagem, Meus Pets)

## 📱 Responsividade

O arquivo inclui media queries para diferentes resoluções:

### Mobile (< 640px)
- Navegação em coluna única
- Cards de agendamento centralizados
- Tabelas convertidas em cards com labels
- Galeria em uma coluna
- Formulários em largura total

### Tablet (≥ 640px)
- Layout intermediário
- Tabelas em formato padrão

### Desktop (≥ 768px)
- Grid de 2 colunas
- Próximo agendamento e pendências lado a lado
- Outras seções ocupam largura total

## 🔧 Funcionalidades JavaScript

Scripts básicos para demonstração:

### Prevenção de Submit
- Formulários exibem alert em vez de enviar
- Mensagem: "Demonstração: Em um ambiente real, os dados seriam salvos aqui."

### Botões de Pagamento
- Clique exibe mensagem de redirecionamento simulado
- Em produção, redirecionaria para gateway de pagamento

### Scroll Suave
- Navegação por âncoras com animação suave
- Implementado via CSS `scroll-behavior: smooth`

## 🚀 Como Usar

### Visualização Local
1. Abra o arquivo `portal-cliente-demo.html` diretamente no navegador
2. Nenhuma dependência externa necessária (exceto imagens de placeholder)

### Teste de Responsividade
1. Abra as ferramentas de desenvolvedor do navegador (F12)
2. Ative o modo de dispositivo móvel
3. Teste diferentes resoluções:
   - iPhone SE (375px)
   - iPad (768px)
   - Desktop (1200px+)

### Análise de UX
1. Navegue por todas as seções usando o menu superior
2. Observe hierarquia visual e espaçamento
3. Teste interação com formulários e botões
4. Verifique legibilidade em diferentes tamanhos de tela

## 📊 Métricas Demonstradas

### Tempo de Escaneamento
- Próximo agendamento visível em < 3 segundos
- Navegação para qualquer seção: 1 clique + scroll suave

### Hierarquia Visual
- H1 → H2 → H3 progressivo (24px → 20px → 18px)
- Cards destacados com cores de status
- Alertas com bordas laterais coloridas

### Feedback Visual
- Notices de sucesso/erro com cores distintas
- Badges de status em tabelas
- Hover states em links e botões

## 🔄 Diferenças do Sistema Real

Este é um arquivo de **demonstração estática**. No sistema real:

### Dados Dinâmicos
- Informações carregadas do banco de dados WordPress
- Agendamentos reais do cliente logado
- Fotos reais dos pets (não placeholders)

### Autenticação
- Login via WordPress obrigatório
- Verificação de nonce em formulários
- Proteção CSRF e validação server-side

### Funcionalidades Completas
- Envio real de mensagens
- Processamento de pagamentos via Mercado Pago
- Atualização de dados no banco
- Upload de fotos dos pets
- Integração com AI Assistant (se ativo)

### Integrações
- Finance Add-on para pendências
- Loyalty Add-on para pontos e referências
- Communications Add-on para notificações
- AI Add-on para assistente virtual

## 📝 Notas Técnicas

### CSS Inline
- Todo CSS está incluído no `<style>` do HTML
- Facilita compartilhamento e visualização standalone
- Em produção, CSS vem de `client-portal.css`

### Imagens Placeholder
- Usa serviço placedog.net para fotos de pets
- Em produção, imagens vêm do Media Library do WordPress
- URLs reais seguem padrão: `wp-content/uploads/dps/pets/`

### Dados Fictícios
- Cliente: Maria Silva Santos
- Pets: Thor (Golden Retriever) e Mel (Poodle)
- Endereço: Porto Alegre - RS
- Telefone: (51) 99999-8888

## 🎯 Casos de Uso

### Apresentação para Cliente
- Demonstrar portal sem expor dados reais
- Explicar funcionalidades antes do onboarding
- Validar layout e fluxo de navegação

### Análise de UX
- Identificar pontos de melhoria na interface
- Testar legibilidade e acessibilidade
- Validar conformidade com guia de estilo

### Desenvolvimento
- Referência visual para implementação
- Teste de responsividade sem backend
- Documentação de padrões de UI

### Treinamento
- Material de apoio para equipe
- Tutorial de uso do portal
- Onboarding de novos usuários

## 📚 Documentação Relacionada

- **UX Analysis**: `/docs/layout/client-portal/CLIENT_PORTAL_UX_ANALYSIS.md`
- **Implementation Summary**: `/docs/layout/client-portal/CLIENT_PORTAL_IMPLEMENTATION_SUMMARY.md`
- **Visual Style Guide**: `/docs/visual/VISUAL_STYLE_GUIDE.md`
- **CSS Source**: `/add-ons/desi-pet-shower-client-portal_addon/assets/css/client-portal.css`
- **PHP Source**: `/add-ons/desi-pet-shower-client-portal_addon/includes/class-dps-client-portal.php`

## 🔮 Próximos Passos

### Melhorias Futuras
1. Adicionar mais estados vazios (sem histórico, sem fotos)
2. Incluir programa de fidelidade (se Loyalty Add-on ativo)
3. Demonstrar widget de AI Assistant
4. Adicionar exemplo de notificações push
5. Simular fluxo completo de pagamento

### Manutenção
- Atualizar quando houver mudanças no design
- Sincronizar com alterações no CSS real
- Incluir novos recursos quando implementados

---

**Criado por**: Sistema DPS  
**Data**: Novembro 2024  
**Versão**: 1.0.0  
**Licença**: Uso interno - Desi Pet Shower
