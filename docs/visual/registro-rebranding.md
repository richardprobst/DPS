# Registro de Rebranding — M3 Expressive

Registro das ações de rebranding visual aplicadas aos componentes do DPS, seguindo o sistema de design M3 Expressive documentado em `docs/visual/`.

---

## Componentes Rebrandeados

| # | Componente | Plugin | Arquivo(s) | Estado | Data | Notas |
|---|-----------|--------|-----------|--------|------|-------|
| 1 | AI Agent (Portal do Cliente) | `desi-pet-shower-ai` | `assets/css/dps-ai-portal.css`, `includes/class-dps-ai-integration-portal.php`, `assets/js/dps-ai-portal.js` | ✅ Concluído | 2026-02-09 | Rebranding completo para M3 Expressive. Widget colapsado por padrão. |
| 2 | Aba INICIAL (Portal do Cliente) | `desi-pet-shower-client-portal` | `assets/css/client-portal.css`, `includes/class-dps-client-portal.php` | ✅ Concluído | 2026-02-10 | Rebranding M3 + layout vertical empilhado. |

---

## Detalhes — AI Agent (Portal do Cliente)

### Escopo

Rebranding visual completo do componente AI Agent que aparece **acima das tabs** no Portal do Cliente, via hook `dps_client_portal_before_content`.

### Mudanças Realizadas

#### CSS (`dps-ai-portal.css`)
- **Cores**: todas as variáveis CSS locais (`--ai-primary`, `--ai-gray-*`, etc.) substituídas por tokens M3 (`var(--dps-color-*)`)
- **Tipografia**: font-weight corrigido para 400/500 (M3 permite apenas esses valores); tamanhos via `var(--dps-typescale-*)`
- **Formas**: border-radius via `var(--dps-shape-*)` (card, small, extra-small, full)
- **Elevação**: box-shadow via `var(--dps-elevation-*)` (card, 2, fab, 4, 5)
- **Espaçamento**: padding/margin/gap via `var(--dps-space-*)`
- **Movimento**: transições via `var(--dps-motion-*)` (hover, press, enter, expand)
- **Estado hover**: state layer M3 via pseudo-elemento `::after` com `var(--dps-state-hover-opacity)`
- **Alertas**: padrão M3 com `border-left: 3px solid` (welcome, error messages)
- **Acessibilidade**: `focus-visible` no toggle, `prefers-reduced-motion` para desabilitar animações
- **Dependência**: CSS agora declara `dps-design-tokens` como dependência no enqueue

#### PHP (`class-dps-ai-integration-portal.php`)
- Widget carrega **colapsado por padrão** (classe `is-collapsed` + `aria-expanded="false"`)
- CSS enqueue usa `['dps-design-tokens']` como dependência

#### JS (`dps-ai-portal.js`)
- Acessibilidade: suporte a `Enter`/`Space` para toggle via teclado no header
- Duração da animação de expand/collapse ajustada para 350ms (alinhada com `--dps-motion-expand`)

### Capturas de Tela

| Estado | Descrição | Captura |
|--------|-----------|---------|
| Colapsado (padrão) | Header compacto com avatar, título e chevron. Discreto, full-width. | ![AI Agent Colapsado](https://github.com/user-attachments/assets/7b94712c-a330-4e62-a422-82ab9479cdbd) |
| Expandido | Boas-vindas, sugestões FAQ, área de conversa, input de mensagem. | ![AI Agent Expandido](https://github.com/user-attachments/assets/1837c684-5f58-418a-b9c3-a6991f27142b) |

### Conformidade M3

- [x] Zero hex literals no CSS (exceto rgba para state layers)
- [x] Font-weight apenas 400 e 500
- [x] Espaçamento via tokens `--dps-space-*`
- [x] Formas via tokens `--dps-shape-*`
- [x] Elevação via tokens `--dps-elevation-*`
- [x] Movimento via tokens `--dps-motion-*`
- [x] Alertas com `border-left: 3px solid` (padrão M3)
- [x] State layers com opacidade semântica
- [x] Acessibilidade: `focus-visible`, `prefers-reduced-motion`, `aria-expanded`

---

## Detalhes — Aba INICIAL (Portal do Cliente)

### Escopo

Rebranding visual e reestruturação de layout da aba **INICIAL** (panel-inicio) no Portal do Cliente. Inclui todos os widgets/seções internos: overview cards, ações rápidas, próximo agendamento, pendências financeiras, solicitações recentes, resumo de pets, sugestões contextuais e indicações.

### Mudanças Realizadas

#### CSS (`client-portal.css`) — Seções da aba INICIAL

- **Cores**: todas as referências a `var(--dps-gray-*)`, `var(--dps-white)`, `var(--dps-primary)`, `var(--dps-warning-bg)` etc. substituídas por tokens M3 diretos (`var(--dps-color-surface-container-lowest)`, `var(--dps-color-on-surface)`, `var(--dps-color-primary)`, `var(--dps-color-warning-container)` etc.)
- **Tipografia**: font-weight corrigido de 600/700 para 400/500; tamanhos via `var(--dps-typescale-*-size)` com fallback
- **Formas**: border-radius via `var(--dps-shape-card)`, `var(--dps-shape-button)`, `var(--dps-shape-badge)`, `var(--dps-shape-chip)`, `var(--dps-shape-full)`, `var(--dps-shape-small)`, `var(--dps-shape-extra-small)`
- **Elevação**: box-shadow via `var(--dps-elevation-1)`, `var(--dps-elevation-2)`, `var(--dps-elevation-3)`
- **Espaçamento**: padding/margin/gap via `var(--dps-space-*)` tokens
- **Bordas**: border-left de 4px → 3px (padrão M3 alert)
- **Backgrounds**: gradientes `linear-gradient()` substituídos por cores flat de container M3
- **Botões**: border-radius atualizado para `var(--dps-shape-button)` (pill)
- **Hardcoded #fff**: substituído por `var(--dps-color-on-primary)` ou `var(--dps-color-on-warning)`

#### PHP (`class-dps-client-portal.php`)

- Layout convertido de grid 2 colunas (`dps-inicio-grid` + `dps-inicio-col`) para layout vertical empilhado (`dps-inicio-stack`)
- Seções reordenadas por prioridade: Agendamento → Financeiro → Solicitações → Pets → Sugestões → Indicações
- Indicações movidas para dentro do stack (antes ficava fora do grid)

### Componentes Afetados

| Componente | Classes CSS | Mudança |
|-----------|------------|---------|
| Layout principal | `.dps-inicio-stack` (novo) | Substituiu `.dps-inicio-grid` + `.dps-inicio-col` |
| Overview Cards | `.dps-overview-card*` | Tokens M3, border-left 3px |
| Quick Actions | `.dps-quick-action*` | Tokens M3, pill border-radius |
| Portal Section | `.dps-portal-section` | Tokens M3, border-left 3px |
| Appointment Card | `.dps-appointment-card*` | Tokens M3, border-left 3px |
| Empty State | `.dps-empty-state*` | Tokens M3, pill button |
| Financial Summary | `.dps-financial-summary*` | Tokens M3, border-left 3px |
| Suggestions | `.dps-suggestion-card*` | Tokens M3, pill button |
| Request Cards | `.dps-request-card*` | Tokens M3, border-left 3px, badge radius |
| Pets Summary | `.dps-portal-pets-summary` | Tokens M3 |
| Pet Cards | `.dps-pet-card*` | Tokens M3 |
| Appointment Reminder | `.dps-next-appointment-reminder` | Tokens M3, border-left 3px |
| Section Header | `.dps-section-header` | Tokens M3 |

### Capturas de Tela

> **Nota:** Capturas não puderam ser produzidas neste ambiente sandbox (sem servidor WordPress local). A aba INICIAL deve ser verificada visualmente em ambiente de staging.

### Conformidade M3

- [x] Zero hex literals no CSS das seções INICIAL (exceto fallback em `var()`)
- [x] Font-weight apenas 400 e 500
- [x] Espaçamento via tokens `--dps-space-*`
- [x] Formas via tokens `--dps-shape-*`
- [x] Elevação via tokens `--dps-elevation-*`
- [x] Movimento via tokens `--dps-motion-*`
- [x] Bordas com `border-left: 3px solid` (padrão M3)
- [x] Backgrounds flat (sem gradientes)
- [x] Layout vertical empilhado (single-column)
