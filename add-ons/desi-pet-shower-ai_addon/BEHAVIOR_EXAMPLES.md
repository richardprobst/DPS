# Comportamento do Assistente de IA - Exemplos

Este documento apresenta exemplos práticos de como o assistente de IA do Portal do Cliente responde a diferentes tipos de perguntas.

## ✅ Perguntas ACEITAS (Dentro do Contexto)

### Sobre Agendamentos

**Pergunta**: "Quando foi o último banho do meu cachorro Thor?"
**Comportamento**: ✅ Assistente busca no histórico e responde com data e serviços realizados.

**Pergunta**: "Posso remarcar meu agendamento de amanhã?"
**Comportamento**: ✅ Assistente explica o processo de remarcação e instrui a entrar em contato com a unidade.

**Pergunta**: "Que horas é meu próximo atendimento?"
**Comportamento**: ✅ Assistente verifica agendamentos futuros e informa data/hora.

### Sobre Serviços

**Pergunta**: "Quanto custa um banho e tosa para gato de porte médio?"
**Comportamento**: ✅ Assistente consulta dados de serviços e informa valores (se disponíveis no sistema).

**Pergunta**: "Vocês fazem hidratação?"
**Comportamento**: ✅ Assistente lista serviços disponíveis cadastrados no sistema.

**Pergunta**: "Qual a diferença entre tosa higiênica e tosa completa?"
**Comportamento**: ✅ Assistente explica de forma genérica baseado em conhecimento sobre banho e tosa.

### Sobre Pagamentos e Pendências

**Pergunta**: "Tenho alguma conta em aberto?"
**Comportamento**: ✅ Assistente verifica pendências financeiras e informa valores (se Finance add-on estiver ativo).

**Pergunta**: "Como faço para pagar minha conta?"
**Comportamento**: ✅ Assistente explica opções de pagamento disponíveis no portal.

### Sobre Fidelidade e Assinaturas

**Pergunta**: "Quantos pontos de fidelidade eu tenho?"
**Comportamento**: ✅ Assistente consulta pontos acumulados (se Loyalty add-on estiver ativo).

**Pergunta**: "Como funciona a assinatura mensal?"
**Comportamento**: ✅ Assistente explica planos de assinatura cadastrados no sistema.

### Sobre Cuidados com Pets (Genérico)

**Pergunta**: "Com que frequência devo dar banho no meu cachorro?"
**Comportamento**: ✅ Assistente fornece orientações gerais e responsáveis sobre higiene de pets.

**Pergunta**: "É normal meu gato ter muito pelo solto?"
**Comportamento**: ✅ Assistente orienta sobre pelagem e sugere escovação regular, recomenda veterinário se houver preocupação.

## ❌ Perguntas RECUSADAS (Fora do Contexto)

### Política e Religião

**Pergunta**: "O que você acha do governo atual?"
**Comportamento**: ❌ Resposta padrão: *"Sou um assistente focado apenas em ajudar com informações sobre o seu pet e os serviços de Banho e Tosa do Desi Pet Shower. Não consigo ajudar com esse tipo de assunto."*

**Pergunta**: "Qual é a melhor religião?"
**Comportamento**: ❌ Resposta padrão de recusa.

### Finanças Pessoais e Investimentos

**Pergunta**: "Onde devo investir meu dinheiro?"
**Comportamento**: ❌ Resposta padrão de recusa.

**Pergunta**: "Como declarar imposto de renda?"
**Comportamento**: ❌ Resposta padrão de recusa.

### Tecnologia Geral

**Pergunta**: "Como programar em Python?"
**Comportamento**: ❌ Resposta padrão de recusa.

**Pergunta**: "Qual o melhor celular para comprar?"
**Comportamento**: ❌ Resposta padrão de recusa.

### Saúde Humana

**Pergunta**: "Estou com dor de cabeça, o que tomar?"
**Comportamento**: ❌ Resposta padrão de recusa.

**Pergunta**: "Como curar gripe?"
**Comportamento**: ❌ Resposta padrão de recusa.

### Outros Assuntos Aleatórios

**Pergunta**: "Quem ganhou a Copa do Mundo de 2022?"
**Comportamento**: ❌ Resposta padrão de recusa.

**Pergunta**: "Qual a capital da França?"
**Comportamento**: ❌ Resposta padrão de recusa.

## ⚠️ Casos Especiais

### Problemas de Saúde Graves do Pet

**Pergunta**: "Meu cachorro está vomitando muito, o que fazer?"
**Comportamento**: ⚠️ Assistente reconhece que é um problema sério e **recomenda procurar um veterinário imediatamente**. Não tenta diagnosticar.

**Pergunta**: "Meu gato não está comendo há 3 dias, é normal?"
**Comportamento**: ⚠️ Assistente **recomenda procurar um veterinário urgentemente**. Não fornece diagnóstico.

### Perguntas Sem Dados no Sistema

**Pergunta**: "Quantas vezes meu pet já foi atendido aqui?"
**Comportamento** (se não houver histórico): *"Não encontrei esse registro no sistema. Você pode falar diretamente com a equipe da unidade para confirmar."*

### Perguntas Sobre Descontos Inexistentes

**Pergunta**: "Posso ter um desconto de 50% no próximo banho?"
**Comportamento**: ⚠️ Assistente **não inventa descontos**. Responde que não pode oferecer descontos sem autorização, orienta a falar com a equipe.

## 🔍 Filtro Preventivo de Palavras-Chave

O sistema aplica um filtro **antes** de chamar a API da OpenAI para economizar custos:

### Palavras-Chave Aceitas
- pet, cachorro, gato, cão, gatos
- banho, tosa, grooming
- agendamento, agenda, agendar, horário
- serviço, serviços
- pagamento, pendência, cobrança
- portal, sistema, dps
- assinatura, plano
- fidelidade, pontos
- vacina, vacinação
- histórico, atendimento
- cliente, cadastro, dados
- raça, porte, pelagem
- higiene, cuidado, saúde (do pet)

### Exemplo de Filtro em Ação

**Pergunta**: "Qual o melhor investimento?"
**Comportamento**: 
1. ❌ Não contém nenhuma palavra-chave relacionada a pets/serviços
2. ❌ **API NÃO é chamada** (economiza custo)
3. ✅ Retorna resposta padrão imediata: *"Sou um assistente focado em ajudar com informações sobre o seu pet e os serviços do Desi Pet Shower. Tente perguntar algo sobre seus agendamentos, serviços, histórico ou funcionalidades do portal."*

**Pergunta**: "Meu cachorro precisa de banho?"
**Comportamento**:
1. ✅ Contém palavra-chave "cachorro" e "banho"
2. ✅ **API é chamada**
3. ✅ OpenAI processa com system prompt restritivo
4. ✅ Resposta contextualizada é retornada

## 💡 Resumo das Regras

1. **Foco total** em Banho e Tosa, pet shop e sistema DPS
2. **Recusa educada** para assuntos fora do contexto
3. **Recomendação veterinária** para problemas de saúde graves
4. **Honestidade** quando dados não estão disponíveis
5. **Sem invenções** de descontos ou promoções
6. **Filtro preventivo** economiza chamadas de API
7. **Segurança** garantida (nonces, sanitização, validação)
8. **Graceful degradation** se API falhar (portal continua funcionando)
