# Capturas de Tela — 11 de Fevereiro de 2026

Registro visual completo das telas de **Agenda**, **Formulário de Cadastro** e **Formulário de Agendamento** do sistema DPS.

## Contexto

- **Data:** 2026-02-11
- **Ambiente:** Demos HTML estáticas com CSS real dos plugins
- **Versão:** Última versão do branch de desenvolvimento
- **Viewports:** Desktop (1440×900), Tablet (1024×768), Mobile (375×812)
- **Tipo de captura:** Página inteira (fullpage) + viewport de cada tela

---

## 1. Agenda

Tela de gestão diária de atendimentos com visão de dia/semana/mês, resumo de status e tabela de agendamentos.

| Viewport | Página inteira | Viewport |
|----------|---------------|----------|
| Desktop (1440×900) | [agenda-desktop-fullpage.png](agenda-desktop-fullpage.png) | [agenda-desktop-viewport.png](agenda-desktop-viewport.png) |
| Tablet (1024×768) | [agenda-tablet-fullpage.png](agenda-tablet-fullpage.png) | [agenda-tablet-viewport.png](agenda-tablet-viewport.png) |
| Mobile (375×812) | [agenda-mobile-fullpage.png](agenda-mobile-fullpage.png) | [agenda-mobile-viewport.png](agenda-mobile-viewport.png) |

**Demo HTML:** [agenda-rebranding.html](../agenda-rebranding.html)

---

## 2. Formulário de Cadastro (Registration)

Formulário multi-step para cadastro de clientes e pets, com 3 passos: dados do cliente, dados dos pets e preferências/resumo.

| Viewport | Página inteira | Viewport |
|----------|---------------|----------|
| Desktop (1440×900) | [registration-desktop-fullpage.png](registration-desktop-fullpage.png) | [registration-desktop-viewport.png](registration-desktop-viewport.png) |
| Tablet (1024×768) | [registration-tablet-fullpage.png](registration-tablet-fullpage.png) | [registration-tablet-viewport.png](registration-tablet-viewport.png) |
| Mobile (375×812) | [registration-mobile-fullpage.png](registration-mobile-fullpage.png) | [registration-mobile-viewport.png](registration-mobile-viewport.png) |

**Demo HTML:** [registration-rebranding.html](../registration-rebranding.html)

---

## 3. Formulário de Agendamento (Booking)

Formulário para criação de agendamentos com seleção de tipo (simples/assinatura/passado), cliente, pets, data/horário, TaxiDog, tosa, observações e resumo.

| Viewport | Página inteira | Viewport |
|----------|---------------|----------|
| Desktop (1440×900) | [booking-desktop-fullpage.png](booking-desktop-fullpage.png) | [booking-desktop-viewport.png](booking-desktop-viewport.png) |
| Tablet (1024×768) | [booking-tablet-fullpage.png](booking-tablet-fullpage.png) | [booking-tablet-viewport.png](booking-tablet-viewport.png) |
| Mobile (375×812) | [booking-mobile-fullpage.png](booking-mobile-fullpage.png) | [booking-mobile-viewport.png](booking-mobile-viewport.png) |

**Demo HTML:** [booking-form.html](booking-form.html)

---

## Inventário de arquivos

```
docs/screenshots/2026-02-11/
├── SCREENSHOTS_2026-02-11.md          # Este documento
├── booking-form.html                   # Demo HTML do formulário de agendamento
├── agenda-desktop-fullpage.png
├── agenda-desktop-viewport.png
├── agenda-tablet-fullpage.png
├── agenda-tablet-viewport.png
├── agenda-mobile-fullpage.png
├── agenda-mobile-viewport.png
├── registration-desktop-fullpage.png
├── registration-desktop-viewport.png
├── registration-tablet-fullpage.png
├── registration-tablet-viewport.png
├── registration-mobile-fullpage.png
├── registration-mobile-viewport.png
├── booking-desktop-fullpage.png
├── booking-desktop-viewport.png
├── booking-tablet-fullpage.png
├── booking-tablet-viewport.png
├── booking-mobile-fullpage.png
└── booking-mobile-viewport.png
```

## Observações

- As capturas são geradas a partir de demos HTML estáticas que carregam os CSS reais dos plugins (design tokens, base CSS, addon CSS).
- O Formulário de Agendamento foi renderizado com dados de exemplo (cliente, pets, serviços) para demonstrar o formulário preenchido.
- Todos os toggles (TaxiDog, Tosa) estão ativados para mostrar os campos expandidos.
- A Agenda mostra a visão de dia com 4 atendimentos de exemplo em diferentes status.
- O Formulário de Cadastro mostra todos os 3 passos simultaneamente para registro completo.
