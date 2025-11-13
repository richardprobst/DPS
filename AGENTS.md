# Instruções para agentes do monorepo DPS

## Escopo
Estas instruções se aplicam a todo o repositório. Diretórios que exigirem regras adicionais devem conter seus próprios `AGENTS.md`, que terão precedência dentro do respectivo escopo. Já existem orientações específicas em `plugin/AGENTS.md` para o plugin base e em `add-ons/AGENTS.md` para os complementos.

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

## Documentação específica do plugin WordPress
- A arquitetura do plugin base e dos add-ons WordPress está descrita em `ANALYSIS.md`. Consulte o documento antes de alterar fluxos para manter a compatibilidade entre módulos.
- Os add-ons estendem o plugin base por meio dos *hooks* `dps_base_*` (abas/seções) e `dps_settings_*`. Preserve esses pontos de integração ao adicionar novos recursos.
- As integrações financeiras reutilizam a tabela `dps_transacoes`; mantenha o esquema e sincronizações consistentes ao introduzir novas interações com agendamentos, assinaturas ou cobranças.
