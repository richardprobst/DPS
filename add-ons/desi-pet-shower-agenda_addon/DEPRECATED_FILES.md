# Arquivos Legados - Agenda Add-on

Este documento lista arquivos que foram movidos ou removidos.

## Arquivos Removidos (2025-11-27)

### `agenda-addon.js` (raiz do add-on)
- **Status**: ✅ REMOVIDO
- **Data de remoção**: 2025-11-27
- **Motivo**: Arquivo duplicado. O script oficial está em `assets/js/agenda-addon.js`

### `agenda.js` (raiz do add-on)
- **Status**: ✅ REMOVIDO
- **Data de remoção**: 2025-11-27
- **Motivo**: Código legado do FullCalendar que não era mais utilizado

## Estrutura Atual

```
desi-pet-shower-agenda_addon/
├── assets/
│   ├── css/
│   │   └── agenda-addon.css
│   └── js/
│       ├── agenda-addon.js      ← OFICIAL
│       └── services-modal.js     ← OFICIAL
├── languages/
│   └── .gitkeep                  ← Pasta para traduções
├── desi-pet-shower-agenda-addon.php
├── uninstall.php
└── ...
```
