# Screenshots do DPS

Este diretório centraliza registros visuais padronizados do sistema DPS.

## Índice

- **Agenda**
  - [Rebranding da Agenda (2026)](AGENDA_REBRANDING_SCREENSHOTS.md)
- **Cadastro**
  - [Rebranding do Formulário de Cadastro (2026)](REGISTRATION_REBRANDING_SCREENSHOTS.md)
- **Portal do Cliente**
  - [Rebranding do Portal do Cliente (2026)](CLIENT_PORTAL_REBRANDING_SCREENSHOTS.md)

## Padrão de registro

Cada página de captura deve conter:
- Contexto (tela, objetivo, versão)
- Data e ambiente
- Viewports utilizados (Desktop: 1440×900, Tablet: 1024×768, Mobile: 375×812)
- Lista das imagens com legenda
- Observações relevantes (ex.: limitações do ambiente)

### Estrutura de arquivos

```
docs/screenshots/
├── README.md                           # Este índice
├── <FEATURE>_SCREENSHOTS.md            # Documentação de cada registro
├── <feature>-demo.html                 # Demo HTML para geração das capturas
└── assets/
    └── <feature>/
        ├── <feature>-desktop.png       # Captura desktop (1440×900)
        ├── <feature>-tablet.png        # Captura tablet (1024×768)
        └── <feature>-mobile.png        # Captura mobile (375×812)
```

### Convenções de nomenclatura
- Screenshots em PNG com nomes descritivos: `<feature>-<viewport>.png`
- Demos HTML referenciam CSS via caminhos relativos ao repositório
- As imagens devem ser PNGs reais (não placeholders de texto)
