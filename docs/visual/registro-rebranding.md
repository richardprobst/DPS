# Registro de Rebranding — M3 Expressive

Registro das ações de rebranding visual aplicadas aos componentes do DPS, seguindo o sistema de design M3 Expressive documentado em `docs/visual/`.

---

## Componentes Rebrandeados

| # | Componente | Plugin | Arquivo(s) | Estado | Data | Notas |
|---|-----------|--------|-----------|--------|------|-------|
| 1 | AI Agent (Portal do Cliente) | `desi-pet-shower-ai` | `assets/css/dps-ai-portal.css`, `includes/class-dps-ai-integration-portal.php`, `assets/js/dps-ai-portal.js` | ✅ Concluído | 2026-02-09 | Rebranding completo para M3 Expressive. Widget colapsado por padrão. |

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
