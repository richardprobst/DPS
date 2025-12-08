# AGENDA Add-on - Fase 2: Plano de Implementação UX

**Branch**: `copilot/agenda-phase2-ux-improvements`  
**Data de início**: 2025-12-08  
**Status**: Planejamento

---

## Objetivos

### 1. Botões de Ação Rápida (UX-1) 🎯 ALTA PRIORIDADE
**Problema**: Equipe precisa de múltiplos cliques para mudar status de atendimentos  
**Solução**: Adicionar botões de ação direta na coluna de ações

**Mudanças necessárias**:
- Extrair lógica de renderização de linha para função reutilizável `render_appointment_row()`
- Adicionar botões na coluna de ações:
  - ✅ Finalizar (muda para 'finalizado')
  - 💰 Pago (muda para 'finalizado_pago')
  - ❌ Cancelar (muda para 'cancelado')
- Criar endpoint AJAX `dps_agenda_quick_action`
- Validar nonce e capabilities
- Usar lógica de negócio existente (não duplicar)

**Arquivos**:
- `desi-pet-shower-agenda-addon.php`
- `assets/js/agenda-addon.js`

---

### 2. Atualização de Linha sem Reload (UX-2) 🎯 ALTA PRIORIDADE
**Problema**: Página recarrega ao mudar status, perdendo scroll e estado  
**Solução**: AJAX retorna HTML da linha atualizada

**Mudanças necessárias**:
- Usar função `render_appointment_row()` em respostas AJAX
- Retornar JSON com `{ success: true, row_html: '...', appointment_id: 123 }`
- JavaScript substitui `<tr>` com `replaceWith()`
- Fallback para `location.reload()` em caso de erro

**Arquivos**:
- `desi-pet-shower-agenda-addon.php`
- `assets/js/agenda-addon.js`

---

### 3. Indicador de Atendimentos Atrasados (UX-3) 📊 MÉDIA PRIORIDADE
**Problema**: Difícil identificar atendimentos que passaram do horário  
**Solução**: Destaque visual discreto para atendimentos atrasados

**Regra de atraso**:
```php
$is_late = (
    strtotime($date . ' ' . $time) < current_time('timestamp') 
    && in_array($status, ['pendente', 'confirmado'])
);
```

**Mudanças necessárias**:
- Adicionar classe `.is-late` na `<tr>` quando aplicável
- CSS: fundo amarelado (#fef3c7), borda esquerda laranja (#f59e0b 4px)
- (Opcional) Animação pulse sutil

**Arquivos**:
- `desi-pet-shower-agenda-addon.php`
- `assets/css/agenda-addon.css`

---

### 4. Consolidar Layout (UX-4/5/6) 🎨 BAIXA PRIORIDADE
**Problema**: Interface sobrecarregada com muitas informações simultâneas  
**Solução**: Reorganizar filtros e reduzir colunas

#### 4.1. Navegação e Filtros em 2 Linhas (UX-4)
**Linha 1**:
- Data atual/selecionada
- Navegação (◀ ontem | hoje | amanhã ▶)
- Filtros principais (status, período)

**Linha 2**:
- Link "Filtros avançados" (colapsável)
- Ações em lote

#### 4.2. Filtros Avançados Colapsáveis (UX-5)
- Accordion/collapse para filtros menos usados
- Padrão: escondido
- Filtros avançados: tipo de serviço específico, tags, etc.

#### 4.3. Reduzir Colunas da Tabela (UX-6)
**Colunas essenciais** (sempre visíveis):
- ✅ Horário
- ✅ Pet (+ Tutor)
- ✅ Serviços
- ✅ Status
- ✅ Ações

**Colunas secundárias** (mover para modal/tooltip):
- Data (redundante se usando navegação por dia)
- Mapa/TaxiDog (mover para ícone com tooltip)
- Confirmação (mover para ícone de status)
- Cobrança (mover para submenu em ações)

**Arquivos**:
- `desi-pet-shower-agenda-addon.php`
- `assets/css/agenda-addon.css`
- `assets/js/agenda-addon.js`

---

## Ordem de Implementação

### Fase A: Fundação (Commits 1-2)
1. ✅ Extrair função `render_appointment_row()` reutilizável
2. ✅ Refatorar closure `$render_table` para usar nova função

### Fase B: Ações Rápidas (Commits 3-4)
3. ✅ Adicionar botões de ação rápida na coluna de ações
4. ✅ Criar endpoint AJAX `dps_agenda_quick_action`
5. ✅ Implementar handlers JavaScript

### Fase C: AJAX Row Update (Commit 5)
6. ✅ Modificar AJAX para retornar HTML da linha
7. ✅ JavaScript para substituir `<tr>` sem reload

### Fase D: Indicador de Atraso (Commit 6)
8. ✅ Adicionar lógica para detectar atraso
9. ✅ Adicionar classe `.is-late`
10. ✅ Estilos CSS

### Fase E: Layout (Commits 7-8)
11. ✅ Reorganizar filtros em 2 linhas
12. ✅ Criar accordion de filtros avançados
13. ✅ Reduzir colunas da tabela
14. ✅ Ajustar responsividade

---

## Testes Necessários

### Funcionalidade
- [ ] Botão "Finalizar" muda status para 'finalizado'
- [ ] Botão "Pago" muda status para 'finalizado_pago'
- [ ] Botão "Cancelar" muda status para 'cancelado'
- [ ] Linha atualiza sem reload da página
- [ ] Atendimentos atrasados destacados corretamente
- [ ] Filtros funcionam após reorganização
- [ ] Accordion de filtros avançados abre/fecha

### Segurança
- [ ] Nonce validado em todas as chamadas AJAX
- [ ] Capabilities verificadas (manage_options)
- [ ] Sanitização de inputs
- [ ] Escape de outputs

### Performance
- [ ] Batch loading de posts relacionados mantido
- [ ] Sem queries N+1 adicionadas
- [ ] AJAX responses rápidas (<200ms)

### Compatibilidade
- [ ] Funciona em Chrome, Firefox, Safari
- [ ] Responsivo em mobile (< 768px)
- [ ] Sem conflito com outros add-ons

---

## Notas de Implementação

### Considerações de Segurança
- **NUNCA** confiar em dados do cliente sem validação
- Sempre verificar `current_user_can('manage_options')`
- Usar `wp_verify_nonce()` em todas as requisições AJAX
- Sanitizar com `sanitize_text_field()`, `absint()`, etc.
- Escapar outputs com `esc_html()`, `esc_attr()`, `esc_url()`

### Padrões de Código
- Seguir WordPress Coding Standards
- Indentação: 4 espaços (tabs)
- Funções globais: `snake_case`
- Métodos de classe: `camelCase`
- Prefixar tudo com `dps_` ou `DPS_`

### Retrocompatibilidade
- Manter comportamento existente quando JavaScript desabilitado
- Fallback para reload completo em caso de erro AJAX
- Não remover funcionalidades existentes

---

## Status de Implementação

- [ ] **UX-1**: Botões de ação rápida
- [ ] **UX-2**: AJAX row update
- [ ] **UX-3**: Indicador de atraso
- [ ] **UX-4**: Filtros em 2 linhas
- [ ] **UX-5**: Filtros avançados colapsáveis
- [ ] **UX-6**: Redução de colunas

**Próximo passo**: Iniciar Fase A - Extrair função render_appointment_row()
