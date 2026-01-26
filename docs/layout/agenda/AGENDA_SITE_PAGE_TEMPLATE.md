# Modelo de Página (Site) inspirado na identidade visual da AGENDA

## Objetivo
Criar um **modelo reutilizável de página** para o site do Banho e Tosa Desi Pet Shower, reaproveitando a identidade visual da **AGENDA**: estilo minimalista, espaçamento generoso, bordas suaves e acentos funcionais.

Este modelo foi pensado para **páginas públicas** (site institucional/marketing) e mantém a consistência com o painel AGENDA: paleta reduzida, tipografia limpa e elementos de destaque por status.

---

## 1. Identidade visual herdada da AGENDA

**Características principais observadas na AGENDA:**
- **Minimalismo e clareza**: layouts sem sombras fortes, com bordas sutis e muito espaço em branco.
- **Destaques funcionais**: uso de cores apenas para sinalizar ações e status.
- **Hierarquia forte**: títulos, subtítulos e cards com bordas laterais para chamar atenção.
- **Botões pill**: botões arredondados com estados claros.

**Paleta aplicada no modelo:**
- **Background**: `#f8fafc`
- **Surface**: `#ffffff`
- **Border**: `#e2e8f0`
- **Texto principal**: `#374151`
- **Texto secundário**: `#64748b`
- **Acento** (ações principais): `#2563eb`
- **Status**:
  - Pendente: `#f59e0b`
  - Finalizado: `#0ea5e9`
  - Pago: `#22c55e`
  - Cancelado: `#ef4444`

---

## 2. Estrutura recomendada do modelo

1. **Header compacto**
   - Logo + CTA (Agendar Agora)
   - Link para WhatsApp

2. **Hero com mensagem clara**
   - H1 objetivo + resumo em 1 parágrafo
   - Cards de confiança (ex.: “Atendimento humanizado”, “Equipe especializada”)

3. **Seção de serviços**
   - Grid com 3 a 4 cards
   - Cada card com borda lateral colorida (status/papel do serviço)

4. **Processo simples (3 passos)**
   - Cards em linha com numeração
   - Destacar o passo atual com acento

5. **Destaques operacionais**
   - “Horários de atendimento”, “Agendamentos rápidos”, “Emergências”

6. **Depoimentos e prova social**
   - Cards minimalistas com borda e avatar simples

7. **FAQ essencial**
   - 3 a 5 perguntas comuns

8. **CTA final**
   - Botão principal + botão secundário

---

## 3. Padrões visuais do modelo

### Tipografia
- **H1**: 32px, peso 600
- **H2**: 24px, peso 600
- **H3**: 18px, peso 600
- **Texto**: 16px, peso 400

### Espaçamento
- **Padding de seção**: 32px
- **Gap entre seções**: 40px
- **Cards**: 20px de padding, borda `1px solid #e2e8f0`

### Componentes principais
- **Botões**: pill (`border-radius: 999px`), sem sombras
- **Cards**: borda lateral de 3px com cor de destaque
- **Badges**: fundo leve e texto pequeno para tags

---

## 4. Modelo HTML (arquivo de referência)

Use o arquivo `docs/layout/agenda/agenda-site-page-template.html` como base. Ele já está preparado com:

- Variáveis CSS de identidade visual
- Estrutura completa da página
- Seções prontas para duplicar/ajustar

> ✅ Recomendado duplicar o arquivo e editar o conteúdo conforme a página desejada (ex.: “Banho e Tosa Premium”, “Hotelzinho”, “Plano Mensal”).

---

## 5. Adaptação rápida (checklist)

- [ ] Trocar H1 e texto do hero para o objetivo da página
- [ ] Atualizar os cards de serviços com os serviços reais
- [ ] Ajustar o CTA final e link de WhatsApp
- [ ] Remover seções não usadas (ex.: FAQ ou Depoimentos)
- [ ] Manter a paleta e os espaçamentos para consistência

---

## 6. Observações importantes

- Este modelo prioriza **clareza** e **leitura rápida**, como na AGENDA.
- Evite efeitos visuais pesados (sombras grandes, gradientes agressivos).
- Sempre que possível, use **borda lateral + badge** para destacar informações.

---

Se precisar de variações adicionais (landing page, página de campanha, página de convênio), crie uma nova versão baseada neste mesmo esqueleto visual.
