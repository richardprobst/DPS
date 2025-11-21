# Análise de Layout e Usabilidade da Agenda DPS

## Data da Análise
2025-11-21

## Objetivo
Analisar templates, scripts e estilos relacionados à AGENDA (calendário, lista de agendamentos, visão diária/semana/mês) com foco em layout, usabilidade, responsividade e acessibilidade visual, propondo melhorias alinhadas com um estilo **minimalista e clean**.

---

## 1. INVENTÁRIO DE ARQUIVOS

### Agenda Add-on
- **Arquivo principal**: `/add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`
  - Contém a classe `DPS_Agenda_Addon`
  - Renderiza shortcode `[dps_agenda_page]` com HTML e CSS inline (linhas 183-487)
  - 487 linhas de CSS inline embutidas no PHP
  
- **Scripts JavaScript**:
  - `/add-ons/desi-pet-shower-agenda_addon/agenda-addon.js` (126 linhas) - Atualização AJAX de status
  - `/add-ons/desi-pet-shower-agenda_addon/agenda.js` (20 linhas) - Calendário FullCalendar (NÃO UTILIZADO)

### Plugin Base
- **Template**: `/plugin/desi-pet-shower-base_plugin/templates/appointments-list.php`
  - Template alternativo para listagem de agendamentos
  - Usado em outras partes do sistema
  
- **Estilos base**: `/plugin/desi-pet-shower-base_plugin/assets/css/dps-base.css`
  - Estilos compartilhados para tabelas, formulários e componentes
  - Define cores de status (linhas 205-217)

---

## 2. VISUALIZAÇÃO DOS AGENDAMENTOS

### 2.1 Como os agendamentos são exibidos?

**Formato principal**: TABELA (`<table class="dps-table">`)

**Estrutura**:
```
┌─────────────────────────────────────────────────────────────────┐
│ Navegação (Anterior | Próximo | Ver Hoje | Todos os Atendimentos)│
│ Formulário de seleção de data                                   │
│ Filtros (Cliente, Status, Serviço)                             │
├─────────────────────────────────────────────────────────────────┤
│ Resumo: X pendentes | Y finalizados | Z total                  │
├─────────────────────────────────────────────────────────────────┤
│ Tabela: Próximos Atendimentos                                  │
│ Tabela: Atendimentos Finalizados                               │
└─────────────────────────────────────────────────────────────────┘
```

**Colunas da tabela** (linhas 824-831):
1. Data
2. Hora
3. Pet (Cliente)
4. Serviço (link "Ver serviços")
5. Status (dropdown editável)
6. Mapa (link Google Maps)
7. Confirmação (link WhatsApp)
8. Cobrança (link WhatsApp)

### 2.2 Informações principais visíveis?

**✅ SIM - Informações visíveis sem clique**:
- Data e hora do agendamento
- Nome do pet e cliente
- Status atual (com dropdown para edição)

**❌ NÃO - Requerem interação**:
- **Serviços**: aparecem apenas como link "Ver serviços" que abre um `alert()` JavaScript
- **Valores**: não aparecem na tabela principal
- **Observações**: não aparecem na agenda

**🔶 PARCIAL**:
- **Flag de Assinatura**: aparece como texto "(Assinatura)" junto ao nome do pet (linha 867)
- **Flag de Pet Agressivo**: aparece como "!" vermelho se o pet for marcado como agressivo (linha 859)
- **Flag de TaxiDog**: aparece como texto "(TaxiDog)" na coluna de mapa (linha 935)

### 2.3 Cores de status: consistentes e intuitivas?

**Cores definidas** (linhas 387-402 do PHP):

| Status | Cor da borda esquerda | Cor de fundo | Semântica |
|--------|----------------------|--------------|-----------|
| `pendente` | `#f59e0b` (laranja) | `#fffbeb` (amarelo claro) | ⚠️ Atenção |
| `finalizado` | `#0ea5e9` (azul) | `#f0f9ff` (azul claro) | ℹ️ Informativo |
| `finalizado_pago` | `#22c55e` (verde) | `#f0fdf4` (verde claro) | ✅ Sucesso |
| `cancelado` | `#ef4444` (vermelho) | `#fef2f2` (vermelho claro) | ❌ Erro/Cancelamento |

**Análise**:
- ✅ **Consistentes**: cores fixas, aplicadas via classes CSS
- ✅ **Intuitivas**: verde = pago/completo, vermelho = cancelado, laranja = pendente, azul = finalizado
- ✅ **Destaque visual**: borda esquerda de 4px + fundo suave cria hierarquia clara
- ⚠️ **Potencial problema**: não testado para daltonismo (verde/vermelho)

---

## 3. INTERAÇÃO

### 3.1 Facilidade de criar novo agendamento

**❌ PROBLEMA CRÍTICO**: Não há botão "Criar Agendamento" visível na página de agenda.

**Onde está?**:
- Novo agendamento é criado apenas através do plugin base (outra interface)
- Não há link ou atalho da agenda para criação

**Impacto**:
- Usuário precisa sair da agenda e ir para outra seção do sistema
- Workflow interrompido

### 3.2 Filtros e ordenação

**Filtros disponíveis** (linhas 590-661):
- ✅ **Por data**: seletor de data + navegação anterior/próximo
- ✅ **Por cliente**: dropdown com lista de clientes
- ✅ **Por status**: dropdown (todos, pendente, finalizado, finalizado_pago, cancelado)
- ✅ **Por serviço**: dropdown com lista de serviços

**Visualizações** (linhas 494-496):
- `view=day`: visualização diária (padrão)
- `view=week`: visualização semanal (7 dias)
- `view=calendar`: calendário (DESATIVADO - botão removido conforme linha 552)

**Ordenação**:
- ⚠️ **Fixa**: agendamentos sempre ordenados por data/hora (linhas 679-705)
- ❌ Não há opção de ordenar por cliente, status ou serviço
- ✅ Divide em duas seções: "Próximos" (pendentes) e "Finalizados"

### 3.3 Área clicável e affordance

**Botões e links**:
- ✅ **Status**: dropdown `<select>` claramente interativo
- ✅ **Serviços**: link "Ver serviços" com cor de destaque (`--dps-accent`)
- ✅ **Navegação**: botões com classes `.dps-btn` com estados hover
- ⚠️ **Links WhatsApp**: texto simples, sem ícone (apenas texto "Confirmar via WhatsApp")

**Affordance**:
- ✅ Botões têm border-radius arredondado (999px = pill shape)
- ✅ Hover states com transformação visual (`transform: translateY(-1px)`)
- ✅ Focus states com outline para acessibilidade
- ⚠️ Links de serviços não têm ícone, apenas cor azul e underline no hover

---

## 4. RESPONSIVIDADE

### 4.1 Breakpoints definidos

**Media queries** (linhas 417-486):
- `@media (max-width: 1024px)`: ajusta navegação
- `@media (max-width: 860px)`: empilha filtros
- `@media (max-width: 768px)`: reduz padding, ajusta botões
- `@media (max-width: 640px)`: **TRANSFORMA TABELA EM CARDS**
- `@media (max-width: 420px)`: empilha botões de navegação

### 4.2 Comportamento em telas menores

**Desktop (>640px)**:
- ✅ Tabela horizontal com scroll horizontal se necessário
- ✅ Mínimo de 780px de largura para tabela (linha 359)

**Mobile (<640px)**:
- ✅ **Transforma tabela em cards verticais** (linhas 442-476)
  - Oculta `<thead>`
  - Cada `<tr>` vira um card independente
  - Cada `<td>` mostra label via `::before` pseudo-elemento
- ✅ Border-left preservado nos cards para manter código de cores
- ✅ Padding reduzido para aproveitar espaço

**Problemas identificados**:
- ⚠️ **Tabela grande**: 8 colunas podem sobrecarregar em mobile, mas cards verticais resolvem
- ⚠️ **Scroll horizontal**: em telas entre 640-780px, tabela cria scroll (pode confundir)
- ❌ **Navegação em 420px**: botões ocupam 100% da largura, mas muitos (4 botões = muita rolagem)

### 4.3 Calendário (FullCalendar)

**Status**: ❌ **NÃO UTILIZADO**

**Evidência**:
- Código existe em `agenda.js` (linhas 4-18)
- Botão "Ver Calendário" foi **removido** (linha 552)
- Comentário: "será implementado em uma futura atualização"

**Impacto**:
- Positivo: simplifica interface, evita sobrecarga
- Negativo: falta visualização mensal rápida

---

## 5. ACESSIBILIDADE VISUAL

### 5.1 Contraste de cores

**Teste de contraste** (manual - baseado em WCAG 2.1):

| Elemento | Cor texto | Cor fundo | Contraste estimado | WCAG AA |
|----------|-----------|-----------|-------------------|---------|
| Status pendente | `#0f172a` | `#fffbeb` | ~14:1 | ✅ Passa |
| Status finalizado | `#0f172a` | `#f0f9ff` | ~13:1 | ✅ Passa |
| Status pago | `#0f172a` | `#f0fdf4` | ~14:1 | ✅ Passa |
| Status cancelado | `#0f172a` | `#fef2f2` | ~13:1 | ✅ Passa |
| Botões primários | `#fff` | `#2563eb` | ~8:1 | ✅ Passa |
| Labels de tabela | `#64748b` | `#f8fafc` | ~4.5:1 | ✅ Passa (AA large) |

**Problemas**:
- ⚠️ **Pet agressivo**: `color:red` sem especificar tom - pode ter contraste insuficiente
- ⚠️ **Flags de assinatura/TaxiDog**: cores `#0073aa` e `#6c757d` não verificadas

### 5.2 Ícones e tooltips

**Situação atual**:
- ❌ **Sem ícones**: nenhum ícone usado (apenas texto)
- ❌ **Sem tooltips**: nenhum tooltip implementado
- ⚠️ **Flag "!"**: apenas caractere "!" para pet agressivo (linha 859) - pouco descritivo
- ✅ **Labels claras**: "Confirmar via WhatsApp", "Cobrar via WhatsApp" são descritivas

**Recomendação**:
- Adicionar `title=""` em links para affordance
- Considerar ícones FontAwesome ou similares para ações (WhatsApp, mapa, etc.)

### 5.3 Feedback visual de ações

**AJAX de atualização de status** (linhas 14-75 do agenda-addon.js):
- ✅ Mensagem "Atualizando status..." exibida (linha 28)
- ✅ Select desabilitado durante request (classe `.is-loading`, linha 30)
- ✅ Mensagem de sucesso ou erro (linhas 47-48, 69-74)
- ✅ **Auto-reload após 700ms** (linha 50) - garante consistência visual
- ⚠️ **Conflito de versão**: detecta e avisa se outro usuário editou (linha 64)

**Feedback de serviços** (linhas 77-102 do agenda-addon.js):
- ⚠️ **Usa `alert()`**: modal nativo do navegador (linha 94) - pouco moderno
- Deveria usar modal customizado ou tooltip

---

## 6. ESTILO VISUAL - ANÁLISE MINIMALISTA/CLEAN

### 6.1 Paleta de cores atual

**Cores principais** (linhas 186-194):
```css
--dps-accent: #2563eb (azul primário)
--dps-accent-strong: #1d4ed8 (azul escuro)
--dps-accent-soft: #eff6ff (azul muito claro)
--dps-surface: #ffffff (branco)
--dps-background: #f8fafc (cinza claro)
--dps-border: #e2e8f0 (cinza médio)
--dps-muted: #64748b (cinza texto secundário)
```

**Análise**:
- ✅ **Paleta enxuta**: 7 cores base + 4 cores de status = 11 cores totais (razoável)
- ✅ **Cores de destaque**: azul reservado para ações primárias
- ✅ **Status com cores semânticas**: laranja/azul/verde/vermelho para status
- ⚠️ **Poderia reduzir**: eliminar `--dps-accent-soft`, usar apenas `--dps-background`

### 6.2 Elementos decorativos

**Observações**:
- ✅ **Bordas arredondadas suaves**: 0.75rem (12px) - equilibrado
- ✅ **Sombras sutis**: `box-shadow: 0 8px 16px rgba(15,23,42,0.04)` - muito leve
- ✅ **Espaçamento generoso**: padding e gap consistentes (1rem ~ 1.5rem)
- ⚠️ **Border-left de 4px**: muito proeminente, poderia ser 3px
- ⚠️ **Box-shadows múltiplas**: navbar, filtros e tabela têm sombras - redundante

### 6.3 Botões e variações

**Variantes de botões** (linhas 280-324):
1. `.dps-btn--primary`: azul sólido com hover transform
2. `.dps-btn--ghost`: borda azul, fundo transparente
3. `.dps-btn--soft`: cinza claro

**Análise**:
- ✅ **Apenas 3 variantes**: minimalista, adequado
- ⚠️ **Transform no hover**: `translateY(-1px)` pode ser excessivo para estilo clean
- ✅ **Border-radius: 999px**: pill buttons - moderno e clean

### 6.4 Uso de espaço em branco

**Positivo**:
- ✅ `gap: 1rem` entre elementos de navegação
- ✅ `padding: 1rem 1.25rem` em containers
- ✅ Margem generosa entre seções (1.25rem ~ 1.5rem)

**Negativo**:
- ⚠️ **Tabela**: padding de células poderia ser maior (0.85rem → 1rem)
- ⚠️ **Resumo de agendamentos**: fundo azul claro pode ser removido para mais leveza

---

## 7. PROBLEMAS IDENTIFICADOS

### 7.1 Críticos
1. **CSS inline de 487 linhas**: dificulta manutenção, cache e testes
2. **Sem botão "Criar Agendamento"**: usuário precisa sair da agenda
3. **Calendário não funcional**: código existe mas está desativado
4. **Alert() para serviços**: UX ruim, deveria ser modal ou tooltip

### 7.2 Importantes
5. **Muitos botões de navegação**: 4 botões (Anterior, Próximo, Ver Hoje, Todos) + 2-3 de visualização = 6-7 botões no topo
6. **Scroll horizontal entre 640-780px**: pode confundir usuário
7. **Sem ícones**: links de WhatsApp e Mapa são apenas texto
8. **Flag de pet agressivo**: apenas "!" vermelho - pouco descritivo
9. **Sem tooltips**: nenhum elemento tem `title=""` ou tooltip

### 7.3 Menores
10. **Cores não verificadas para daltonismo**: verde/vermelho podem ser problemáticos
11. **Transform no hover**: pode ser excessivo para estilo clean
12. **Box-shadows redundantes**: navbar, filtros e tabela com sombras
13. **Border-left de 4px**: muito proeminente

---

## 8. SUGESTÕES DE MELHORIA

### 8.1 Estrutura e organização

#### Problema 1: CSS inline (487 linhas)
**Arquivo**: `desi-pet-shower-agenda-addon.php` (linhas 184-487)

**Mudança**:
```
Criar arquivo: /add-ons/desi-pet-shower-agenda_addon/assets/css/agenda-addon.css
Mover todo CSS inline para arquivo dedicado
Enfileirar com wp_enqueue_style no método enqueue_assets()
```

**Benefícios**:
- ✅ Cache do navegador
- ✅ Minificação possível
- ✅ Separação de responsabilidades
- ✅ Facilita manutenção

#### Problema 2: Sem botão "Criar Agendamento"
**Arquivo**: `desi-pet-shower-agenda-addon.php` (linha ~567, após navegação)

**Mudança**:
```php
// Adicionar após linha 567 (após botões de navegação)
echo '<div class="dps-agenda-nav-group">';
$new_appt_url = add_query_arg(['tab' => 'agendas', 'action' => 'new'], get_option('dps_base_page_url'));
echo '<a href="' . esc_url($new_appt_url) . '" class="button dps-btn dps-btn--primary">';
echo esc_html__('➕ Novo Agendamento', 'dps-agenda-addon');
echo '</a>';
echo '</div>';
```

**Benefícios**:
- ✅ Workflow completo dentro da agenda
- ✅ Reduz cliques do usuário

### 8.2 Layout minimalista

#### Melhoria 1: Reduzir botões de navegação
**Arquivo**: `desi-pet-shower-agenda-addon.php` (linhas 524-566)

**Antes**:
```
[Anterior] [Próximo] [Ver Semana] [Ver Hoje] [Todos os Atendimentos]
```

**Depois** (consolidar):
```
[← Anterior] [Hoje] [Próximo →]   |   [📅 Semana] [📋 Todos]
```

**Mudança**:
- Remover botão "Ver Lista" (redundante se já estiver em lista)
- Usar ícones simples (emoji ou FontAwesome)
- Agrupar visualmente com `|` separador

#### Melhoria 2: Simplificar estilos de botões
**Arquivo**: `agenda-addon.css` (novo)

**Antes**:
```css
.dps-btn--primary:hover {
    transform: translateY(-1px); /* movimento no hover */
}
```

**Depois**:
```css
.dps-btn--primary:hover {
    background: var(--dps-accent-strong);
    /* remover transform para estilo mais clean */
}
```

#### Melhoria 3: Reduzir sombras
**Arquivo**: `agenda-addon.css` (novo)

**Antes**:
```css
.dps-agenda-nav {
    box-shadow: 0 8px 16px rgba(15,23,42,0.04);
}
.dps-agenda-filters {
    box-shadow: 0 8px 16px rgba(15,23,42,0.04);
}
```

**Depois**:
```css
/* Remover sombra de containers, manter apenas em tabela para elevação */
.dps-agenda-nav,
.dps-agenda-filters {
    box-shadow: none;
    border: 1px solid var(--dps-border); /* apenas borda */
}
```

#### Melhoria 4: Border-left mais sutil
**Arquivo**: `agenda-addon.css` (novo)

**Antes**:
```css
.dps-agenda-wrapper table.dps-table tbody tr {
    border-left: 4px solid transparent;
}
```

**Depois**:
```css
.dps-agenda-wrapper table.dps-table tbody tr {
    border-left: 3px solid transparent; /* 4px → 3px */
}
```

### 8.3 Usabilidade e interação

#### Melhoria 5: Substituir alert() por modal
**Arquivo**: `agenda-addon.js` (linha 94)

**Antes**:
```javascript
alert(message);
```

**Depois**:
```javascript
// Criar modal customizado ou usar biblioteca leve (SweetAlert2, micro-modal)
showServicesModal(services); // função a implementar
```

**Estrutura do modal**:
```html
<div class="dps-modal" role="dialog" aria-modal="true">
  <div class="dps-modal-content">
    <h4>Serviços do Agendamento</h4>
    <ul class="dps-services-list">
      <!-- Itens aqui -->
    </ul>
    <button class="dps-btn dps-btn--soft">Fechar</button>
  </div>
</div>
```

#### Melhoria 6: Adicionar ícones e tooltips
**Arquivo**: `desi-pet-shower-agenda-addon.php` (linhas 913-942)

**Antes** (linha 920):
```php
$map_link = '<a href="' . esc_url($map_url) . '" target="_blank">' . __('Mapa', 'dps-agenda-addon') . '</a>';
```

**Depois**:
```php
$map_link = '<a href="' . esc_url($map_url) . '" target="_blank" title="' . esc_attr__('Abrir endereço no Google Maps', 'dps-agenda-addon') . '" class="dps-map-link">
    <span class="dashicons dashicons-location"></span> ' . __('Mapa', 'dps-agenda-addon') . '
</a>';
```

**Ícones sugeridos** (usar Dashicons do WordPress):
- Mapa: `dashicons-location`
- WhatsApp: `dashicons-phone` ou emoji 💬
- Confirmação: `dashicons-yes-alt`
- Cobrança: `dashicons-money-alt`

#### Melhoria 7: Melhorar flag de pet agressivo
**Arquivo**: `desi-pet-shower-agenda-addon.php` (linha 859)

**Antes**:
```php
$aggr_flag = ' <span class="dps-aggressive-flag" style="color:red; font-weight:bold;">! </span>';
```

**Depois**:
```php
$aggr_flag = ' <span class="dps-aggressive-flag" title="' . esc_attr__('Pet agressivo - cuidado no manejo', 'dps-agenda-addon') . '">
    ⚠️
</span>';
```

**Estilo CSS**:
```css
.dps-aggressive-flag {
    font-size: 1.1em;
    cursor: help;
}
```

### 8.4 Responsividade

#### Melhoria 8: Ocultar colunas secundárias em mobile
**Arquivo**: `agenda-addon.css` (novo)

**Adicionar**:
```css
@media (max-width: 768px) {
    /* Ocultar colunas de Mapa e Confirmação em tablets */
    .dps-agenda-wrapper table.dps-table th:nth-child(6),
    .dps-agenda-wrapper table.dps-table td:nth-child(6),
    .dps-agenda-wrapper table.dps-table th:nth-child(7),
    .dps-agenda-wrapper table.dps-table td:nth-child(7) {
        display: none;
    }
}
```

**Benefício**: Reduz sobrecarga visual em telas médias

#### Melhoria 9: Empilhar navegação mais cedo
**Arquivo**: `agenda-addon.css` (novo)

**Antes**: breakpoint em 1024px
**Depois**: breakpoint em 900px

```css
@media (max-width: 900px) {
    .dps-agenda-nav {
        flex-direction: column;
        align-items: stretch;
    }
    .dps-agenda-nav-group {
        width: 100%;
        justify-content: center;
    }
}
```

### 8.5 Acessibilidade

#### Melhoria 10: Adicionar ARIA labels
**Arquivo**: `desi-pet-shower-agenda-addon.php`

**Antes** (linha 237):
```php
echo '<select class="dps-status-select" ...>';
```

**Depois**:
```php
echo '<select class="dps-status-select" aria-label="' . esc_attr__('Alterar status do agendamento', 'dps-agenda-addon') . '" ...>';
```

**Antes** (linha 1041 - resumo):
```php
echo '<div class="dps-agenda-summary" role="status">';
```

**Depois** (já correto - manter):
```php
echo '<div class="dps-agenda-summary" role="status" aria-live="polite">';
```

#### Melhoria 11: Testar para daltonismo
**Ferramenta**: Usar simulador (ex: Coblis Color Blindness Simulator)

**Ação**:
1. Testar cores de status (verde/vermelho) para deuteranopia
2. Se necessário, adicionar padrões visuais além de cor:
   - Pendente: borda tracejada
   - Finalizado: borda sólida
   - Pago: borda dupla
   - Cancelado: borda pontilhada

---

## 9. RESUMO EXECUTIVO

### Pontos fortes
✅ **Responsividade bem implementada**: cards em mobile  
✅ **Cores de status intuitivas**: verde=pago, vermelho=cancelado, etc  
✅ **Filtros completos**: por data, cliente, status, serviço  
✅ **Feedback AJAX**: loading states e mensagens de erro/sucesso  
✅ **Espaçamento generoso**: interface respirável  

### Pontos fracos
❌ **CSS inline (487 linhas)**: dificulta manutenção  
❌ **Sem botão "Criar Agendamento"**: workflow quebrado  
❌ **Alert() para serviços**: UX antiquada  
❌ **Sem ícones**: dependência exclusiva de texto  
❌ **Muitos botões de navegação**: pode confundir  

### Prioridades de refatoração

#### Prioridade ALTA (impacto crítico)
1. ✅ **Extrair CSS inline para arquivo dedicado** → melhora cache e manutenção
2. ✅ **Adicionar botão "Novo Agendamento"** → completa workflow
3. ✅ **Substituir alert() por modal** → moderniza UX

#### Prioridade MÉDIA (melhora significativa)
4. ✅ **Consolidar botões de navegação** → simplifica interface
5. ✅ **Adicionar ícones a links** → melhora affordance
6. ✅ **Melhorar flag de pet agressivo** → clareza e acessibilidade

#### Prioridade BAIXA (ajuste fino)
7. ✅ **Reduzir sombras** → estilo mais clean
8. ✅ **Remover transform do hover** → menos movimento
9. ✅ **Ocultar colunas em tablets** → melhor responsividade
10. ✅ **Testar para daltonismo** → acessibilidade inclusiva

---

## 10. ARQUIVOS A MODIFICAR

### Criação necessária
- [ ] `/add-ons/desi-pet-shower-agenda_addon/assets/css/agenda-addon.css` (novo)

### Modificação necessária
- [ ] `/add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php`
  - Extrair CSS inline (linhas 184-487)
  - Adicionar botão "Novo Agendamento" (linha ~567)
  - Melhorar flags e tooltips (linhas 859, 867, 920, 935)
  - Adicionar ARIA labels
  
- [ ] `/add-ons/desi-pet-shower-agenda_addon/agenda-addon.js`
  - Substituir `alert()` por modal (linha 94)
  - Adicionar função `showServicesModal()`

### Opcional (melhoria incremental)
- [ ] Implementar calendário FullCalendar (atualmente desativado)
- [ ] Criar componente de modal reutilizável
- [ ] Adicionar biblioteca de ícones (FontAwesome ou Dashicons)

---

## Conclusão

A agenda possui uma base sólida com responsividade bem implementada e código de cores intuitivo. No entanto, **487 linhas de CSS inline** prejudicam manutenção e performance. A ausência de um botão "Criar Agendamento" quebra o workflow do usuário, e o uso de `alert()` para serviços é uma UX antiquada.

As melhorias propostas focam em **separação de responsabilidades** (CSS em arquivo dedicado), **simplificação visual** (menos sombras, menos botões) e **modernização da UX** (modal ao invés de alert, ícones, tooltips). Todas as mudanças respeitam o princípio **minimalista/clean** solicitado: paleta enxuta, espaço em branco generoso, elementos decorativos apenas essenciais.

**Próximo passo recomendado**: implementar as melhorias de prioridade ALTA para maior impacto com menor esforço.
