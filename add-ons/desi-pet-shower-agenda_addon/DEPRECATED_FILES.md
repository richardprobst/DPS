# Arquivos Legados - Agenda Add-on

Este documento lista arquivos que foram movidos ou que não são mais utilizados e podem ser removidos fisicamente do repositório.

## Arquivos para Remoção

### `agenda-addon.js` (raiz do add-on)
- **Status**: MOVIDO para `assets/js/agenda-addon.js`
- **Data**: 2025-11-23
- **Motivo**: Padronização da estrutura de assets conforme boas práticas WordPress
- **Ação**: Este arquivo pode ser removido fisicamente. O script agora é carregado de `assets/js/agenda-addon.js`

### `agenda.js` (raiz do add-on)
- **Status**: LEGADO / NÃO UTILIZADO
- **Data**: Identificado em 2025-11-23
- **Motivo**: Contém código antigo do FullCalendar que não é mais enfileirado via `wp_enqueue_script()`
- **Conteúdo**: Implementação de visualização de calendário semanal usando FullCalendar
- **Ação**: Este arquivo pode ser removido fisicamente. Não há referências ativas no código PHP.

## Notas

- Antes de remover fisicamente, verifique se nenhum tema ou plugin de terceiros está fazendo referência direta a esses arquivos
- A estrutura atual recomendada é:
  ```
  desi-pet-shower-agenda_addon/
  ├── assets/
  │   ├── css/
  │   │   └── agenda-addon.css
  │   └── js/
  │       ├── agenda-addon.js      ← OFICIAL
  │       └── services-modal.js     ← OFICIAL
  ├── desi-pet-shower-agenda-addon.php
  └── ...
  ```
