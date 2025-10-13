# Guia do arquivo `AGENTS.md`

## Finalidade
O arquivo `AGENTS.md` fornece instruções específicas para agentes automatizados que editam o repositório. Ele funciona como um manual de "boas práticas" contextualizado, descrevendo convenções de código, processos de build/teste obrigatórios e regras de comunicação. As instruções se aplicam de forma hierárquica: o arquivo mais próximo do arquivo modificado tem precedência, e cada `AGENTS.md` cobre todo o subdiretório em que se encontra.

## Como criar
1. **Escolha o escopo**: decida o diretório cujo conteúdo precisa de instruções personalizadas.
2. **Crie o arquivo**: no diretório escolhido, crie um arquivo chamado `AGENTS.md`.
   ```bash
   cd caminho/do/diretorio
   cat <<'INSTRUCOES' > AGENTS.md
   # Instruções para agentes
   - Descreva aqui as convenções de estilo ou passos obrigatórios.
   - Liste comandos de teste que devem ser executados.
   INSTRUCOES
   ```
3. **Defina as instruções**: documente regras claras e concisas. Use listas para manter a leitura fácil e destaque qualquer prioridade ou exceção.
4. **Empilhe instruções quando necessário**: se partes diferentes do projeto exigirem regras diferentes, adicione `AGENTS.md` adicionais em subdiretórios relevantes.
5. **Versione e comunique**: faça commit do novo arquivo e compartilhe com o time para garantir que todos saibam das novas regras.

## Boas práticas
- Revise regularmente o conteúdo para mantê-lo atualizado.
- Evite contradições entre arquivos em diferentes níveis de diretório.
- Inclua links para documentação adicional quando necessário.

## É importante criar um `AGENTS.md` neste projeto?

Sim. Este monorepo concentra diversos addons e plugins independentes, cada um com suas próprias dependências e rotinas de build/teste. Um `AGENTS.md` na raiz ajuda a orientar agentes e colaboradores sobre:

- **Comandos essenciais**: documentar scripts de instalação, lint e testes comuns reduz falhas por esquecer etapas obrigatórias.
- **Convenções compartilhadas**: padronizar estilo de código, estrutura de branches e mensagens de commit evita revisões demoradas.
- **Instruções específicas por módulo**: subdiretórios podem definir `AGENTS.md` adicionais para requisitos exclusivos (ex.: addons que exigem fixtures ou variáveis de ambiente).

Ao formalizar essas orientações, diminuímos retrabalho e aumentamos a consistência das contribuições em todo o ecossistema do projeto.

Seguindo esses passos, você garante que qualquer agente automatizado entenda como trabalhar corretamente nas áreas controladas pelo arquivo `AGENTS.md`.
