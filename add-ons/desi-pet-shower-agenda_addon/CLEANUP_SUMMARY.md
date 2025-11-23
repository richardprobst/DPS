# Limpeza de Scripts Antigos - Agenda Add-on

**Data**: 2025-11-23  
**Tarefa**: Limpeza de scripts antigos e padronização do carregamento de assets

---

## 1. Situação ANTES da Mudança

### Estrutura de Arquivos

```
desi-pet-shower-agenda_addon/
├── desi-pet-shower-agenda-addon.php
├── assets/
│   ├── css/
│   │   └── agenda-addon.css
│   └── js/
│       └── services-modal.js
├── agenda-addon.js          ❌ Na raiz (fora de assets/)
├── agenda.js                ❌ Arquivo legado não utilizado
└── ...
```

### Enfileiramento de Scripts (linha 201 do PHP)

```php
wp_enqueue_script( 
    'dps-agenda-addon', 
    plugin_dir_url( __FILE__ ) . 'agenda-addon.js',  // ❌ Apontando para raiz
    [ 'jquery', 'dps-services-modal' ], 
    '1.3.0', 
    true 
);
```

### Problemas Identificados

1. **`agenda-addon.js`** está na raiz quando deveria estar em `assets/js/`
2. **`agenda.js`** é um arquivo legado com código do FullCalendar que NÃO é enfileirado
3. Falta padronização na estrutura de assets

---

## 2. Situação DEPOIS da Mudança

### Estrutura de Arquivos

```
desi-pet-shower-agenda_addon/
├── desi-pet-shower-agenda-addon.php
├── assets/
│   ├── css/
│   │   └── agenda-addon.css
│   └── js/
│       ├── agenda-addon.js     ✅ OFICIAL (movido da raiz)
│       └── services-modal.js   ✅ OFICIAL
├── agenda-addon.js             ⚠️ DEPRECATED (marcado para remoção)
├── agenda.js                   ⚠️ DEPRECATED (marcado para remoção)
├── DEPRECATED_FILES.md         ✅ NOVO (documentação)
└── ...
```

### Enfileiramento de Scripts (linha 201 do PHP)

```php
wp_enqueue_script( 
    'dps-agenda-addon', 
    plugin_dir_url( __FILE__ ) . 'assets/js/agenda-addon.js',  // ✅ Apontando para assets/js
    [ 'jquery', 'dps-services-modal' ], 
    '1.3.0', 
    true 
);
```

### Mudanças Realizadas

1. ✅ **Movido** `agenda-addon.js` para `assets/js/agenda-addon.js`
2. ✅ **Atualizado** `wp_enqueue_script()` para apontar para novo local
3. ✅ **Adicionados** comentários de depreciação nos arquivos antigos da raiz
4. ✅ **Criado** `DEPRECATED_FILES.md` documentando arquivos legados

---

## 3. Arquivos Oficiais da Agenda

Após esta limpeza, os arquivos JavaScript oficiais são:

| Arquivo | Localização | Status | Uso |
|---------|-------------|--------|-----|
| `agenda-addon.js` | `assets/js/` | ✅ OFICIAL | Script principal (AJAX, status updates) |
| `services-modal.js` | `assets/js/` | ✅ OFICIAL | Modal de serviços |
| `agenda-addon.js` | raiz | ⚠️ DEPRECATED | Cópia antiga, pode ser removida |
| `agenda.js` | raiz | ⚠️ DEPRECATED | FullCalendar legado, pode ser removido |

---

## 4. Carregamento de Assets

### Onde os Scripts são Carregados

Os scripts são enfileirados **apenas** na página da agenda através do método `enqueue_assets()`:

- **Hook**: `wp_enqueue_scripts` (linha 42)
- **Condição**: `is_page( $agenda_page_id )` (linha 180)
- **Página**: "Agenda de Atendimentos" (`/agenda-de-atendimentos/`)
- **Shortcode**: `[dps_agenda_page]`

### Ordem de Carregamento

1. **jQuery** (dependência do WordPress)
2. **`services-modal.js`** - Modal de serviços (dependência: jQuery)
3. **`agenda-addon.js`** - Script principal (dependências: jQuery + services-modal)

### Dados Localizados

O script `agenda-addon.js` recebe via `wp_localize_script()`:

```javascript
DPS_AG_Addon = {
    ajax: 'wp-admin/admin-ajax.php',
    nonce_status: '...',
    nonce_services: '...',
    statuses: { pendente, finalizado, finalizado_pago, cancelado },
    messages: { updating, updated, error, versionConflict },
    reloadDelay: 700
}
```

---

## 5. Verificação de Uso

### Busca por Referências

```bash
# Busca por referências aos arquivos antigos
grep -r "agenda-addon\.js\|agenda\.js" add-ons/desi-pet-shower-agenda_addon --include="*.php"
```

**Resultado**: ✅ Apenas referências corretas para `assets/js/agenda-addon.js`

### Validação de Sintaxe

```bash
php -l add-ons/desi-pet-shower-agenda_addon/desi-pet-shower-agenda-addon.php
```

**Resultado**: ✅ No syntax errors detected

---

## 6. Próximos Passos (Opcional)

Para remover fisicamente os arquivos legados da raiz:

```bash
# Após validar que tudo funciona em produção
rm add-ons/desi-pet-shower-agenda_addon/agenda-addon.js
rm add-ons/desi-pet-shower-agenda_addon/agenda.js
```

**Recomendação**: Manter os arquivos por 1-2 versões com os comentários de depreciação antes de removê-los fisicamente.
