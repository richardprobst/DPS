# Análise de Conflitos de Seletores CSS

## Problema Identificado

O repositório DPS utiliza várias classes CSS com o prefixo `dps-` que são definidas em múltiplos arquivos de add-ons. Quando múltiplos add-ons são carregados na mesma página, essas definições podem sobrepor umas às outras, causando problemas de layout.

## Contexto

O problema mais grave foi identificado no PR #414 e #415, onde a classe `.dps-input-prefix` era usada para dois propósitos diferentes:
- Como **container flex `<div>`** dentro de `.dps-porte-item` (grade de preços)
- Como **`<span>` estilizado** dentro de `.dps-input-with-prefix` (prefixo monetário)

## Classes com Alto Risco de Conflito

As seguintes classes são definidas em múltiplos arquivos CSS e podem causar conflitos:

### 1. `.dps-status-badge` (6 definições)
| Arquivo | Estilos |
|---------|---------|
| agenda-addon.css | `display: inline-flex; border-radius: 10px; font-size: 0.85rem` |
| dps-base.css | `display: inline-flex; border-radius: 4px; font-size: 12px` |
| groomers-admin.css | `display: inline-flex; border-radius: 9999px; font-size: 12px` |
| loyalty-addon.css | `display: inline-block; border-radius: 4px; font-size: 12px` |
| subscription-addon.css | `display: inline-block; border-radius: 12px; font-size: 12px` |
| services-addon.css | `display: inline-block; border-radius: 12px; font-size: 11px` |

**Risco**: Médio - Afeta aparência visual mas não quebra layout.

### 2. `.dps-form-actions` (5 definições)
| Arquivo | Estilos |
|---------|---------|
| dps-base.css | Base |
| client-portal.css | Específico do portal |
| finance-addon.css | Específico de finanças |
| groomers-admin.css | Específico de tosadores |
| services-addon.css | Específico de serviços |

**Risco**: Baixo-Médio - Geralmente definições são compatíveis.

### 3. `.dps-badge` (4 definições)
**Risco**: Médio - Afeta aparência visual.

### 4. `.dps-input-with-prefix` (3 definições)
| Arquivo | Estilos |
|---------|---------|
| dps-base.css | `display: inline-flex; overflow: hidden` |
| subscription-addon.css | `display: flex; gap: 6px` |
| services-addon.css | `display: flex; overflow: hidden` |

**Risco**: Alto - Diferenças em `display` e `gap` podem afetar layout.

### 5. `.dps-btn`, `.dps-card`, `.dps-modal-*`, etc. (3 definições cada)
**Risco**: Médio - Variações visuais entre add-ons.

## Correções Aplicadas

### PR #415 - `.dps-input-prefix`

A classe foi escopada para evitar conflitos:

```css
/* Antes (genérico - causava conflitos) */
.dps-input-prefix { ... }

/* Depois (escopado ao contexto) */
.dps-input-with-prefix .dps-input-prefix { ... }
.dps-input-money-wrapper .dps-input-prefix { ... }
```

Arquivos corrigidos:
- `services-addon.css` (linha 960)
- `dps-base.css` (linha 1731)
- `finance-addon.css` (linha 170)
- `subscription-addon.css` (linha 569)

## Recomendações para Futuras Implementações

### 1. Usar Prefixos de Add-on
Em vez de classes genéricas como `.dps-status-badge`, use prefixos específicos:
- `.dps-agenda-status-badge` (agenda)
- `.dps-loyalty-status-badge` (loyalty)
- `.dps-finance-status-badge` (finance)

### 2. Escopar ao Contexto
Quando uma classe genérica for necessária, sempre escope ao container do add-on:
```css
/* Bom */
.dps-agenda-wrapper .dps-status-badge { ... }

/* Evitar */
.dps-status-badge { ... }
```

### 3. Usar ID de Seção
Para estilos específicos de uma seção, use IDs ou classes de container:
```css
#dps-section-agenda .dps-status-badge { ... }
```

### 4. Documentar Uso
Quando criar uma classe que pode ser usada em múltiplos contextos, documente o propósito:
```css
/* 
 * .dps-input-prefix - Usado APENAS como span dentro de .dps-input-with-prefix
 * Para containers flex, usar .dps-porte-item .dps-input-prefix
 */
```

## Classes que NÃO Devem Ter Conflitos

As seguintes classes são definidas no base e não devem ser redefinidas em add-ons:
- `.dps-base-wrapper`
- `.dps-nav-container`
- `.dps-section`
- `.dps-surface`
- `.dps-table`
- `.dps-alert`

Add-ons devem estender essas classes com modificadores BEM:
```css
.dps-table--finance { ... }
.dps-alert--agenda { ... }
```

## Próximos Passos (Opcional)

Para uma correção completa, considerar:

1. **Fase 1 (Prioritária)**: Corrigir classes que causam problemas de layout (display, position)
   - Exemplo: `.dps-input-with-prefix` tem definições conflitantes de `display` e `gap`
   - Solução: Escopar ao contexto específico de cada add-on
   
2. **Fase 2 (Média)**: Padronizar classes visuais (border-radius, colors)
   - Exemplo: `.dps-status-badge` tem 6 variações de `border-radius`
   - Solução: Definir no base e usar modificadores BEM

3. **Fase 3 (Longo prazo)**: Migrar para convenção BEM com prefixos de add-on
   - Exemplo: `.dps-agenda-status-badge` em vez de `.dps-status-badge`

## Comando para Verificar Conflitos

Para identificar classes duplicadas no repositório, execute:

```bash
# Encontrar classes .dps-* definidas em múltiplos arquivos
grep -roh "^\.dps-[a-z-]*\s*{" plugins/*/assets/css/*.css plugins/**/*/assets/css/*.css 2>/dev/null | sort | uniq -c | sort -rn | head -20
```

---

*Documento criado em: 2026-01-04*
*Relacionado aos PRs: #414, #415*
*Nota: Os dados das tabelas refletem o estado do código na data de criação. Execute o comando acima para verificar o estado atual.*
