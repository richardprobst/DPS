# Análise Profunda do Visual do Formulário de Cadastro Público

**Versão:** 1.0  
**Data:** 04/01/2026  
**Autor:** PRObst / Copilot  
**Add-on:** desi-pet-shower-registration v1.2.4

---

## 1. Resumo Executivo

Este documento apresenta uma análise profunda do visual do formulário de cadastro público do sistema DPS, comparando com o padrão visual moderno estabelecido no `docs/visual/VISUAL_STYLE_GUIDE.md`. Inclui verificação dos cards de resumo, identificação de lacunas visuais e um plano de modernização com novas funcionalidades para administradores logados.

### Principais Achados

| Área | Status | Prioridade |
|------|--------|------------|
| Cards de resumo | ⚠️ Incompleto | Alta |
| Indicadores de campo obrigatório | ❌ Ausente | Alta |
| Funcionalidades para admin | ❌ Ausente | Média |
| Gradientes nos botões | ⚠️ Parcial | Média |
| Padrão de tipografia | ✅ Conforme | Baixa |
| Responsividade | ✅ Conforme | Baixa |

---

## 2. Análise Visual Detalhada

### 2.1 Container Principal (.dps-registration-form)

**Estado Atual:**
```css
.dps-registration-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 24px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
```

**Conformidade com Guia:** ✅ Conforme
- Usa cores da paleta aprovada (#ffffff, #e5e7eb)
- border-radius: 8px é maior que o padrão (4px) mas aceitável para container principal
- Padding adequado (24px = escala de espaçamento médio/grande)

**Recomendação:** Manter como está; considerar reduzir border-radius para 6px para maior consistência.

---

### 2.2 Barra de Progresso (.dps-progress)

**Estado Atual:**
- Exibe "Passo X de 2" + contador numérico
- Barra visual com preenchimento animado (#0ea5e9)
- aria-live="polite" para acessibilidade

**Conformidade com Guia:** ✅ Conforme
- Cor azul primária correta
- Animação sutil (0.2s ease)
- Semântica de acessibilidade adequada

**Recomendação:** Adicionar feedback visual mais proeminente quando muda de passo (ex: animação suave de destaque).

---

### 2.3 Títulos de Seção (h4)

**Estado Atual:**
```css
.dps-registration-form h4 {
    margin: 32px 0 20px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
    font-size: 18px;
    font-weight: 600;
    color: #374151;
}
```

**Conformidade com Guia:** ✅ Conforme
- font-weight: 600 correto
- color: #374151 correto
- Separador visual com border-top

**Problema:** O primeiro h4 não deveria ter border-top (já corrigido com :first-of-type).

---

### 2.4 Campos de Formulário (Inputs, Selects, Textareas)

**Estado Atual:**
```css
.dps-registration-form input[type="text"],
.dps-registration-form select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    color: #374151;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
```

**Conformidade com Guia:** ✅ Conforme
- Bordas 1px corretas
- border-radius: 6px (guia sugere 4px, mas 6px é aceitável)
- Focus ring com cor primária correta

---

### 2.5 Botões

**Estado Atual - Botão Primário:**
```css
.button-primary {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border: none;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25);
}
```

**Conformidade com Guia:** ✅ Conforme
- Gradiente azul correto
- box-shadow sutil permitido
- border-radius: 8px correto

**Estado Atual - Botão Secundário (.dps-button-secondary):**
```css
.dps-button-secondary {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 1px solid #cbd5e1;
    color: #475569;
}
```

**Conformidade com Guia:** ✅ Conforme

**Estado Atual - Botão Adicionar Pet (#dps-add-pet):**
```css
#dps-add-pet {
    background: #ffffff;
    border: 2px solid #e5e7eb;
    color: #6b7280;
}
```

**Problema:** ⚠️ Usa borda 2px (guia recomenda 1px para tudo exceto bordas de ênfase).

**Recomendação:** Alterar para borda 1px ou converter para estilo de botão secundário com gradiente.

---

### 2.6 Cards de Resumo (.dps-summary-box)

#### 2.6.1 Verificação de Informações Exibidas

**Informações do Tutor capturadas no resumo:**
| Campo | Exibido no Resumo | Status |
|-------|-------------------|--------|
| Nome | ✅ Sim | OK |
| Telefone | ✅ Sim | OK |
| Email | ✅ Sim | OK |
| Endereço | ✅ Sim | OK |
| CPF | ❌ **NÃO** | **FALTANDO** |
| Data de nascimento | ❌ **NÃO** | **FALTANDO** |
| Instagram | ❌ **NÃO** | **FALTANDO** |
| Facebook | ❌ **NÃO** | **FALTANDO** |
| Autorização foto | ❌ **NÃO** | **FALTANDO** |
| Como conheceu | ❌ **NÃO** | **FALTANDO** |

**Informações do Pet capturadas no resumo:**
| Campo | Exibido no Resumo | Status |
|-------|-------------------|--------|
| Nome do pet | ✅ Sim | OK |
| Raça | ✅ Sim | OK |
| Porte | ✅ Sim | OK |
| Observações/Cuidados | ✅ Sim | OK |
| Espécie | ❌ **NÃO** | **FALTANDO** |
| Peso | ❌ **NÃO** | **FALTANDO** |
| Pelagem | ❌ **NÃO** | **FALTANDO** |
| Cor | ❌ **NÃO** | **FALTANDO** |
| Data de nascimento | ❌ **NÃO** | **FALTANDO** |
| Sexo | ❌ **NÃO** | **FALTANDO** |
| Pet agressivo | ❌ **NÃO** | **FALTANDO** |

**Impacto:** O cliente não consegue revisar todos os dados antes de enviar, podendo enviar informações incorretas sem perceber.

#### 2.6.2 Visual dos Cards

**Estado Atual:**
```css
.dps-summary-box {
    margin-top: 16px;
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}
```

**Conformidade com Guia:** ✅ Conforme
- Usa background #f9fafb correto
- Borda 1px correta
- Padding adequado

**Problema:** Falta destaque visual para chamar atenção do usuário antes de confirmar.

**Recomendação:** Adicionar borda-left colorida (padrão do guia para alertas) ou ícone de resumo.

---

## 3. Problemas Identificados

### 3.1 Indicadores de Campo Obrigatório Ausentes

**Problema:** Os campos Nome e Telefone são obrigatórios (`required`), mas não há indicador visual (*) mostrando isso ao usuário.

**Impacto:** UX degradada - usuário só descobre que o campo é obrigatório ao tentar enviar.

**Solução Proposta:**
```html
<label>Nome <span class="dps-required">*</span><br>
    <input type="text" name="client_name" required>
</label>
```

```css
.dps-required {
    color: #ef4444;
    margin-left: 2px;
}
```

---

### 3.2 Fieldsets de Pet sem Ícone de Espécie

**Problema:** Após selecionar a espécie (Cachorro/Gato/Outro), não há feedback visual no card do pet indicando qual espécie foi selecionada.

**Recomendação:** Adicionar emoji dinâmico na legend do fieldset:
- 🐶 para Cachorro
- 🐱 para Gato
- 🐾 para Outro/Não selecionado

---

### 3.3 Falta de Feedback Visual ao Mudar de Step

**Problema:** A transição entre Step 1 e Step 2 é instantânea, sem animação ou feedback visual claro.

**Recomendação:** Adicionar transição suave:
```css
.dps-step {
    display: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.dps-step-active {
    display: block;
    opacity: 1;
}
```

---

## 4. Funcionalidades para Administradores Logados

### 4.1 Contexto

Quando um usuário com capability `manage_options` (administrador) acessa o formulário público de cadastro, não há nenhuma funcionalidade diferenciada. Isso representa uma oportunidade de melhorar a experiência administrativa.

### 4.2 Funcionalidades Propostas

#### F1. Banner Informativo para Admin
**Descrição:** Exibir banner discreto informando que o admin está visualizando o formulário público.

**Visual:**
```html
<div class="dps-admin-preview-banner">
    <span class="dashicons dashicons-visibility"></span>
    Você está visualizando o formulário como ele aparece para os clientes.
    <a href="[link-para-configurações]">Configurar formulário</a>
</div>
```

**CSS:**
```css
.dps-admin-preview-banner {
    background: #eff6ff;
    border-left: 4px solid #0ea5e9;
    padding: 12px 16px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 13px;
    color: #1e40af;
}
```

---

#### F2. Modo de Cadastro Rápido (Admin Only)
**Descrição:** Adicionar checkbox para "Pular confirmação de email" e "Marcar como cliente ativo imediatamente".

**Campos adicionais (visíveis apenas para admin):**
- [ ] Cadastro ativo imediatamente (pula confirmação de email)
- [ ] Enviar email de boas-vindas

---

#### F3. Visualização de Estatísticas Rápidas
**Descrição:** Mostrar pequeno widget com estatísticas de cadastros recentes.

**Dados exibidos:**
- Total de cadastros hoje
- Total de cadastros pendentes de confirmação
- Link rápido para "Cadastros Pendentes"

---

#### F4. Preenchimento de Dados de Teste
**Descrição:** Botão "Preencher dados de teste" para facilitar QA e demonstrações.

**Comportamento:**
- Preenche todos os campos com dados fictícios válidos
- CPF válido gerado algoritmicamente
- Telefone no formato correto
- Dados de pet com raça aleatória

---

#### F5. Seletor de Cliente Existente
**Descrição:** Para admins, permitir selecionar um cliente já cadastrado para adicionar novos pets.

**Visual:**
- Campo de busca autocomplete no topo do Step 1
- Ao selecionar cliente existente, pula direto para Step 2 (Pets)
- Novo pet é vinculado ao cliente selecionado

---

## 5. Plano de Modernização Visual

### 5.1 Fase 1: Correções Críticas (Estimativa: 4h)

| ID | Tarefa | Prioridade |
|----|--------|------------|
| 1.1 | Adicionar indicadores de campo obrigatório (*) | Alta |
| 1.2 | Completar informações no card de resumo (CPF, espécie, sexo, etc.) | Alta |
| 1.3 | Corrigir borda 2px do botão "Adicionar pet" para 1px | Média |
| 1.4 | Adicionar aviso de campos faltantes no resumo | Média |

### 5.2 Fase 2: Melhorias Visuais (Estimativa: 6h)

| ID | Tarefa | Prioridade |
|----|--------|------------|
| 2.1 | Adicionar transição suave entre steps | Média |
| 2.2 | Adicionar ícone de espécie dinâmico nos fieldsets de pet | Média |
| 2.3 | Melhorar destaque visual do card de resumo (border-left colorido) | Média |
| 2.4 | Adicionar tooltips nos campos (CPF: "Somente números") | Baixa |
| 2.5 | Adicionar animação de loading no botão de submit | Baixa |

### 5.3 Fase 3: Funcionalidades Admin (Estimativa: 8h)

| ID | Tarefa | Prioridade |
|----|--------|------------|
| 3.1 | Implementar banner informativo para admin | Alta |
| 3.2 | Implementar checkbox "Cadastro ativo imediatamente" | Alta |
| 3.3 | Implementar widget de estatísticas rápidas | Média |
| 3.4 | Implementar botão "Preencher dados de teste" | Baixa |
| 3.5 | Implementar seletor de cliente existente | Baixa |

---

## 6. Comparação com Guia de Estilo

### 6.1 Paleta de Cores

| Cor | Uso no Formulário | Conforme? |
|-----|-------------------|-----------|
| #f9fafb | Background cards, fieldsets | ✅ |
| #e5e7eb | Bordas, divisores | ✅ |
| #374151 | Texto principal | ✅ |
| #6b7280 | Texto secundário | ✅ |
| #0ea5e9 | Botões primários, focus | ✅ |
| #10b981 | Sucesso (mensagem) | ✅ |
| #ef4444 | Erro | ✅ (CSS presente, mas não usado para campos obrigatórios) |

### 6.2 Tipografia

| Elemento | Estado Atual | Guia | Conforme? |
|----------|--------------|------|-----------|
| h4 (título seção) | 18px, 600 | 16-18px, 600 | ✅ |
| Labels | 14px, 500 | 14px, 400-500 | ✅ |
| Inputs | 14px | 14px | ✅ |
| Descrições | 13-14px | 13px | ✅ |

### 6.3 Espaçamento

| Elemento | Estado Atual | Guia | Conforme? |
|----------|--------------|------|-----------|
| Container padding | 24px | 20px | ✅ (próximo) |
| Gap entre campos | 16px | 16px | ✅ |
| Margem entre seções | 32px | 24-32px | ✅ |

### 6.4 Componentes

| Componente | Estado Atual | Guia | Conforme? |
|------------|--------------|------|-----------|
| Botão primário | Gradiente azul | Gradiente azul | ✅ |
| Botão secundário | Gradiente cinza | Gradiente cinza | ✅ |
| Fieldsets | border + background | border + background | ✅ |
| Mensagens sucesso | border-left verde | border-left colorido | ✅ |

---

## 7. Checklist de Implementação

### Antes de começar:
- [ ] Fazer backup do CSS atual
- [ ] Criar branch de feature `feature/registration-form-modernization`

### Fase 1 - Correções Críticas:
- [ ] 1.1 Adicionar span.dps-required após labels obrigatórios
- [ ] 1.2 Atualizar buildSummary() em dps-registration.js para incluir campos faltantes
- [ ] 1.3 Alterar #dps-add-pet de border: 2px para border: 1px
- [ ] 1.4 Adicionar helper text "Campos marcados com * são obrigatórios"

### Fase 2 - Melhorias Visuais:
- [ ] 2.1 Adicionar CSS de transição para .dps-step
- [ ] 2.2 Implementar lógica JS para ícone de espécie dinâmico
- [ ] 2.3 Adicionar border-left: 4px solid #0ea5e9 no .dps-summary-box
- [ ] 2.4 Criar classe .dps-tooltip e aplicar onde necessário
- [ ] 2.5 Verificar se animação de loading já existe (parece que sim em .dps-loading)

### Fase 3 - Funcionalidades Admin:
- [ ] 3.1 Adicionar lógica PHP para current_user_can('manage_options')
- [ ] 3.2 Renderizar banner informativo condicionalmente
- [ ] 3.3 Adicionar campos de admin (skipConfirmation, etc.)
- [ ] 3.4 Implementar endpoint AJAX para estatísticas rápidas
- [ ] 3.5 Implementar autocomplete de clientes (dependência de select2 ou similar)

### Validação:
- [ ] Testar em mobile (375px)
- [ ] Testar em tablet (768px)
- [ ] Testar em desktop (1920px)
- [ ] Verificar acessibilidade (tab order, aria-labels)
- [ ] Testar formulário como visitante anônimo
- [ ] Testar formulário como admin logado

---

## 8. Conclusão

O formulário de cadastro público já possui uma base sólida e está **80% conforme** com o guia de estilo visual do DPS. Os principais pontos de atenção são:

1. **Cards de resumo incompletos** - Apenas 4 de 10 campos do tutor e 4 de 11 campos do pet são exibidos
2. **Falta de indicadores visuais de obrigatoriedade** - Usuários não sabem quais campos são obrigatórios
3. **Nenhuma funcionalidade diferenciada para admins** - Oportunidade perdida de melhorar workflow administrativo

A implementação das 3 fases propostas levará aproximadamente **18 horas** de desenvolvimento e resultará em uma experiência significativamente melhorada para clientes e administradores.

---

**Próximos Passos:**
1. Aprovar escopo das fases
2. Priorizar Fase 1 para correções imediatas
3. Agendar Fase 2 e 3 para sprints subsequentes

**Referências:**
- `docs/visual/VISUAL_STYLE_GUIDE.md`
- `plugins/desi-pet-shower-registration/assets/css/registration-addon.css`
- `plugins/desi-pet-shower-registration/assets/js/dps-registration.js`
