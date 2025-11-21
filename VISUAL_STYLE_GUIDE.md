# Guia de Estilo Visual DPS

**Versão:** 1.0  
**Última atualização:** 21/11/2024  
**Princípio:** Minimalista/Clean

---

## 1. Filosofia do Design

O DPS adota uma abordagem **minimalista** que prioriza:
- **Clareza**: informação facilmente acessível
- **Funcionalidade**: cada elemento tem propósito claro
- **Consistência**: padrões visuais previsíveis
- **Espaço em branco**: respiro visual para reduzir fadiga

### Menos é mais
- Evitar decoração desnecessária (sombras, gradientes, bordas grossas)
- Usar cores apenas quando comunicam informação
- Manter hierarquia visual através de tipografia e espaçamento

---

## 2. Paleta de Cores

### Cores Base (Neutras)
```css
/* Fundo principal */
#f9fafb  /* Cinza muito claro - backgrounds sutis */

/* Bordas e divisores */
#e5e7eb  /* Cinza claro - bordas suaves */

/* Texto principal */
#374151  /* Cinza escuro - corpo de texto */

/* Texto secundário */
#6b7280  /* Cinza médio - descrições, labels */

/* Fundo branco */
#ffffff  /* Branco puro - cards, formulários */
```

### Cor de Destaque
```css
/* Azul primário */
#0ea5e9  /* Azul claro - botões primários, links, destaques */

/* Hover/Focus */
#0284c7  /* Azul médio - estados interativos */
```

### Cores de Status (uso restrito)
```css
/* Sucesso */
#10b981  /* Verde - confirmações, status "OK" */
#d1fae5  /* Verde claro - backgrounds de sucesso */

/* Erro */
#ef4444  /* Vermelho - erros críticos, cancelamentos */

/* Aviso/Pendente */
#f59e0b  /* Amarelo/Laranja - alertas, pendências */
#fef3c7  /* Amarelo claro - backgrounds de aviso */

/* Neutro/Inativo */
#f3f4f6  /* Cinza neutro - estados inativos */
```

**Regra:** Use cores de status **apenas** quando essencial para comunicar estado. Prefira opacidade (opacity: 0.6) para estados inativos.

---

## 3. Tipografia

### Fonte
```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```
Fonte nativa do sistema para máxima legibilidade e performance.

### Hierarquia de Títulos
```css
/* h1 - Título principal da página/painel */
font-size: 24px; /* ou tamanho padrão WP */
font-weight: 600;
color: #374151;
margin-bottom: 24px;

/* h2 - Seções principais */
font-size: 20px;
font-weight: 600;
color: #374151;
margin-bottom: 20px;

/* h3 - Subseções e agrupamentos */
font-size: 16px;
font-weight: 600;
color: #374151;
margin-top: 40px;
padding-top: 24px;
border-top: 1px solid #e5e7eb;  /* separador visual */
```

### Texto Corpo
```css
/* Normal */
font-size: 14px;
font-weight: 400;
color: #374151;

/* Descrições e help text */
font-size: 13px;
font-weight: 400;
color: #6b7280;

/* Texto pequeno (legendas) */
font-size: 12px;
color: #6b7280;
```

### Uso de Negrito
- **font-weight: 600** para destaques (evitar 700/bold)
- Usar **apenas** quando necessário (labels de formulário, status críticos)

### Transformação de Texto
```css
/* Headers de tabelas */
text-transform: uppercase;
letter-spacing: 0.05em;
font-size: 13px;
font-weight: 600;
```

---

## 4. Espaçamento

### Escala de Espaçamento
```css
/* Micro */
4px   /* Entre ícone e texto */
8px   /* Padding interno pequeno */

/* Pequeno */
12px  /* Margem entre elementos próximos */
16px  /* Padding padrão de inputs */

/* Médio */
20px  /* Padding de containers (fieldsets, cards) */
24px  /* Margem entre seções */

/* Grande */
32px  /* Separação entre blocos principais */
40px  /* Margem antes de subseções (com border-top) */
```

### Aplicação
- **Não comprimir**: priorize espaço em branco sobre "caber mais na tela"
- **Consistência**: use múltiplos de 4px para manter alinhamento visual
- **Respiração**: mínimo 16px entre campos de formulário

---

## 5. Bordas e Sombras

### Bordas
```css
/* Padrão */
border: 1px solid #e5e7eb;
border-radius: 4px;

/* Destaque lateral (alertas, cards especiais) */
border-left: 4px solid [cor-de-status];

/* Sem bordas laterais (tabelas) */
border-bottom: 1px solid #e5e7eb;
```

**Regra:** Sempre 1px, nunca variar espessura. Border-radius consistente em 4px.

### Sombras
```css
/* Apenas para modais e tooltips */
box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);

/* NÃO usar para: */
- Botões
- Cards estáticos
- Containers de formulário
- Tabelas
```

**Regra:** Evitar sombras decorativas. Usar apenas para elevação (dropdowns, modais).

---

## 6. Componentes

### Botões
```css
/* Primário */
.button-primary {
    background: #0ea5e9;
    border-color: #0ea5e9;
    color: #ffffff;
    font-weight: 600;
    text-shadow: none;
    box-shadow: none;
}
.button-primary:hover {
    background: #0284c7;
}

/* Secundário */
.button-secondary {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    color: #374151;
}
.button-secondary:hover {
    background: #f9fafb;
    border-color: #cbd5e1;
}
```

### Tabelas
```css
/* Headers */
.widefat th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #e5e7eb;
}

/* Rows */
.widefat tbody tr:hover {
    background: #f9fafb;
}

/* Responsivo */
@media (max-width: 768px) {
    .dps-table-wrapper {
        overflow-x: auto;
    }
}
```

### Avisos e Alertas
```css
.notice {
    background: #ffffff;
    border-left: 4px solid [cor];
    box-shadow: none;
    padding: 16px 20px;
    border-radius: 4px;
}

/* Cores de borda */
.notice-success { border-left-color: #10b981; }
.notice-error { border-left-color: #ef4444; }
.notice-warning { border-left-color: #f59e0b; }
.notice-info { border-left-color: #0ea5e9; }
```

### Fieldsets e Agrupamentos
```css
fieldset, .dps-field-group {
    border: 1px solid #e5e7eb;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
    background: #f9fafb;  /* opcional, usar para destaque */
}

legend, .dps-field-group-title {
    font-weight: 600;
    color: #374151;
    padding: 0 8px;
    font-size: 15px;
}
```

### Inputs e Formulários
```css
input[type="text"],
input[type="email"],
select,
textarea {
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
    color: #374151;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #0ea5e9;
    outline: none;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
```

### Tooltips
```css
.dps-tooltip {
    position: relative;
    display: inline-block;
    margin-left: 4px;
    color: #6b7280;
    cursor: help;
}

.dps-tooltip::before {
    content: '?';
    width: 16px;
    height: 16px;
    line-height: 16px;
    text-align: center;
    border: 1px solid #cbd5e1;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 600;
}

.dps-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 12px;
    background: #374151;
    color: #ffffff;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
```

### Contadores e Badges
```css
.dps-selection-counter {
    display: inline-block;
    padding: 4px 12px;
    background: #eff6ff;  /* azul muito claro */
    color: #0284c7;
    border-radius: 4px;
    font-weight: 600;
    font-size: 13px;
    margin-left: 8px;
}
```

---

## 7. Ícones

### Princípios
- **Usar com moderação**: apenas quando adicionam clareza
- **Sempre com texto**: ícone não substitui label
- **Consistência**: usar mesmo conjunto (Unicode ou biblioteca)

### Ícones Unicode Aprovados
```
✓ (U+2713)  - Sucesso, OK, Confirmado
⚠ (U+26A0)  - Aviso, Atenção
✕ (U+2715)  - Erro, Excluir, Fechar
🔍 (U+1F50D) - Busca, Filtro ativo
📋 (U+1F4CB) - Copiar, Duplicar
```

**Evitar:**
- Ícones decorativos sem função
- Mais de 3 ícones diferentes por tela
- Ícones sem contraste suficiente

---

## 8. Responsividade

### Breakpoints
```css
/* Mobile pequeno */
@media (max-width: 480px) {
    /* 1 coluna, font-size 16px em inputs (evita zoom iOS) */
}

/* Tablets */
@media (max-width: 768px) {
    /* Ocultar colunas secundárias, reduzir padding */
}

/* Desktop pequeno */
@media (max-width: 1024px) {
    /* Toolbars em coluna, filtros empilhados */
}
```

### Estratégias
1. **Tabelas**: overflow-x: auto + min-width ou transformar em cards
2. **Formulários**: campos sempre width: 100%
3. **Navegação**: tabs transformam em dropdown ou accordion
4. **Imagens**: max-width: 100%, height: auto

---

## 9. Checklist de Implementação

Ao criar nova interface DPS, verificar:

- [ ] **Cores**: Apenas paleta aprovada (neutros + 1 destaque + status quando necessário)
- [ ] **Tipografia**: Hierarquia h1>h2>h3 correta, font-weight 600 para destaques
- [ ] **Espaçamento**: Mínimo 16px entre campos, 24px entre seções, 40px antes de subseções
- [ ] **Bordas**: Sempre 1px, border-radius 4px, sem variação de espessura
- [ ] **Sombras**: Apenas em modais/tooltips, nunca decorativas
- [ ] **Botões**: Primários com background azul, secundários brancos com borda
- [ ] **Formulários**: Agrupados em fieldsets/dps-field-group quando >5 campos
- [ ] **Tabelas**: Headers uppercase 13px, hover suave, wrapper responsivo
- [ ] **Feedback**: Notices com borda lateral colorida, ícones discretos
- [ ] **Responsivo**: Testado em 375px, 768px, 1024px, 1920px

---

## 10. Anti-padrões (evitar)

❌ **Não fazer:**
- Usar mais de 3 cores diferentes em uma tela
- Adicionar sombras em todos os elementos
- Criar bordas de 2px ou 3px
- Usar font-weight: 700 (bold)
- Comprimir espaçamento para "caber mais"
- Ícones sem label de texto
- Background gradients
- Animações desnecessárias
- Tabelas sem overflow-x em mobile
- Formulários sem agrupamento lógico

✅ **Fazer:**
- Paleta restrita e consistente
- Espaço em branco generoso
- Bordas 1px suaves
- Hierarquia clara de títulos
- Feedback visual discreto mas claro
- Agrupar campos relacionados
- Testar em mobile
- Documentar exceções ao guia

---

## 11. Manutenção do Guia

**Atualizar este documento quando:**
- Adicionar novo componente visual
- Modificar paleta de cores
- Alterar tipografia padrão
- Criar novo padrão de interação
- Identificar anti-padrão recorrente

**Versionamento:**
- Major (1.x): mudanças na paleta ou tipografia base
- Minor (x.1): novos componentes ou breakpoints
- Patch (x.x.1): correções e esclarecimentos

---

**Fim do Guia de Estilo Visual DPS v1.0**
