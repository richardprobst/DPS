# Guia para enviar o plugin "Desi Pet Shower" ao GitHub

Este passo a passo mostra como publicar no GitHub as versões atualizadas do plugin base e de todos os add-ons que já estão neste repositório local.

## 1. Criar (ou identificar) o repositório no GitHub
1. Acesse [https://github.com](https://github.com) e crie uma conta, caso ainda não tenha.
2. Clique em **New repository** (Novo repositório).
3. Defina o nome que desejar (ex.: `desi-pet-shower`) e mantenha-o vazio (sem README inicial).
4. Copie a URL do repositório. Ela terá formato `https://github.com/<usuario>/desi-pet-shower.git` ou, se preferir usar SSH, `git@github.com:<usuario>/desi-pet-shower.git`.

## 2. Conectar o repositório local ao GitHub
No terminal que já está aberto na pasta do projeto (`/workspace/DPS`), execute o comando abaixo substituindo `URL-DO-SEU-REPOSITORIO` pela URL copiada no passo anterior:

```bash
git remote add origin URL-DO-SEU-REPOSITORIO
```

Se o remoto `origin` já existir, atualize-o com:

```bash
git remote set-url origin URL-DO-SEU-REPOSITORIO
```

Para confirmar que a URL ficou correta, execute:

```bash
git remote -v
```

## 3. Garantir que os commits estejam prontos
Todos os arquivos atualizados do plugin e dos add-ons já estão versionados neste repositório. Para conferir o histórico mais recente:

```bash
git log --oneline
```

Se você ainda fizer alguma alteração futura, salve e crie um novo commit com:

```bash
git add .
git commit -m "Minha mensagem de commit"
```

## 4. Enviar os arquivos ao GitHub
Com o remoto configurado, envie o branch atual (`work`) para o GitHub:

```bash
git push -u origin work
```

Na primeira vez, será solicitado que você faça login no GitHub. Siga as instruções apresentadas no terminal (pode ser necessário gerar um token de acesso pessoal).

Depois do primeiro envio, basta usar:

```bash
git push
```

Sempre que criar novos commits.

## 5. Abrir um Pull Request (opcional)
Se você preferir manter um fluxo de revisão antes de publicar na branch principal, crie um Pull Request diretamente no GitHub:
1. Acesse o repositório no navegador.
2. Clique em **Compare & pull request** ao lado do branch `work`.
3. Revise a descrição e confirme em **Create pull request**.

Pronto! Seu plugin "Desi Pet Shower" com os metadados do desenvolvedor PRObst e site [probst.pro](https://probst.pro) estará disponível no seu GitHub.
