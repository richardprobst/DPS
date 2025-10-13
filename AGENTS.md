# Instruções para agentes do monorepo DPS

## Escopo
Estas instruções se aplicam a todo o repositório. Diretórios que exigirem regras adicionais devem conter seus próprios `AGENTS.md`, que terão precedência dentro do respectivo escopo.

## Convenções gerais
- Utilize mensagens de commit descritivas no imperativo, em português.
- Prefira manter a documentação em português, alinhada ao restante do repositório.
- Antes de editar arquivos existentes, verifique se há um `AGENTS.md` mais específico no diretório-alvo.

## Fluxo de trabalho recomendado
1. Crie uma branch temática antes de começar a trabalhar.
2. Execute `git status` com frequência para revisar o que foi modificado.
3. Organize os commits para que contem histórias pequenas e coerentes.
4. Abra a _pull request_ descrevendo claramente o contexto, a mudança e os testes executados.

## Testes
- Quando alterar código Python, execute os testes com `pytest` a partir do diretório do módulo impactado.
- Para módulos Odoo, confirme que o servidor inicia sem erros (`odoo-bin -d <banco_de_teste>`).
- Documente explicitamente os testes executados (ou justifique se não foi necessário rodá-los).

## Estilo de código
- Siga o padrão `black` para Python (linha máxima de 88 caracteres).
- Organize importações com `isort`.
- Evite blocos `try/except` envolvendo imports.

## Comunicação adicional
- Se encontrar instruções conflitantes entre diretórios, adote as mais específicas e sinalize o conflito na PR.
- Registre quaisquer requisitos extras (variáveis de ambiente, passos manuais) diretamente nos `AGENTS.md` relevantes.
