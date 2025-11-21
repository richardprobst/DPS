# Desi Pet Shower – Cadastro Público Add-on

Formulário público de auto-cadastro para novos clientes e pets.

## Visão geral

O **Cadastro Público Add-on** permite que novos clientes se cadastrem no sistema via formulário web público, sem necessidade de intervenção da recepção. Inclui integração com Google Maps para autocomplete de endereços e dispara hooks para outros add-ons (como Loyalty) processarem indicações.

Funcionalidades principais:
- Formulário público de cadastro de cliente e pets
- Autocomplete de endereços via Google Maps API
- Validação e sanitização completa de dados
- Disparo de hook `dps_registration_after_client_created` para integração com outros add-ons
- Suporte a código de indicação (Indique e Ganhe) via parâmetro URL
- Interface responsiva e amigável

**Tipo**: Add-on (extensão do plugin base DPS)

## Localização e identificação

- **Diretório**: `add-ons/desi-pet-shower-registration_addon/`
- **Slug**: `dps-registration-addon`
- **Classe principal**: (verificar no arquivo principal)
- **Arquivo principal**: `desi-pet-shower-registration-addon.php`
- **Tipo**: Add-on (depende do plugin base)

## Dependências e compatibilidade

### Dependências obrigatórias
- **Desi Pet Shower Base**: v1.0.0 ou superior (obrigatório)
- **WordPress**: 6.0 ou superior
- **PHP**: 7.4 ou superior

### Dependências opcionais
- **Loyalty Add-on**: para processar códigos de indicação (Indique e Ganhe)
- **Google Maps API**: para autocomplete de endereços (recomendado)

### Versão
- **Introduzido em**: v0.1.0 (estimado)
- **Compatível com plugin base**: v1.0.0+

## Funcionalidades principais

### Formulário de cadastro
- **Dados do cliente**: nome, telefone, e-mail, endereço, observações
- **Dados de pets**: nome, espécie, raça, porte (multi-pet: cadastrar vários pets de uma vez)
- **Validação client-side**: JavaScript valida campos obrigatórios antes de enviar
- **Validação server-side**: PHP sanitiza e valida todos os dados recebidos

### Integração com Google Maps
- **Autocomplete de endereços**: ao digitar endereço, sugere opções via Google Maps API
- **Preenchimento automático**: selecionar sugestão preenche campos de rua, número, bairro, cidade, UF
- **Chave de API configurável**: administrador insere chave do Google Maps nas configurações

### Programa de indicação
- **Parâmetro URL**: aceita `?ref=CODIGO` na URL para capturar código de indicação
- **Campo pré-preenchido**: se parâmetro presente, campo de código já vem preenchido
- **Disparo de hook**: após criar cliente, dispara hook para Loyalty processar indicação

### Feedback visual
- **Mensagens de sucesso/erro**: notifica usuário sobre resultado do cadastro
- **Redirecionamento**: após sucesso, pode redirecionar para página de confirmação
- **Responsividade**: interface adaptada para mobile e desktop

## Shortcodes, widgets e endpoints

### Shortcodes

#### `[dps_registration_form]`
Renderiza formulário completo de cadastro público.

**Uso**:
```
[dps_registration_form]
```

**Descrição**: Exibe formulário para cliente e pets. Após envio, cria posts do tipo `dps_client` e `dps_pet`.

**Parâmetros**: Nenhum (aceita parâmetro GET `?ref=CODIGO` na URL).

**Permissões**: Acesso público (não requer login).

**Exemplo de página**:
Crie uma página "Cadastre-se" e insira o shortcode para que novos clientes possam se registrar.

## Hooks (actions e filters) relevantes

### Hooks CONSUMIDOS por este add-on

Este add-on não consome hooks de outros plugins; opera de forma independente.

### Hooks DISPARADOS por este add-on

#### `dps_registration_after_client_created` (action)
- **Momento**: Após criar novo cliente via formulário público
- **Parâmetros**: `$client_id` (int - ID do post `dps_client` criado)
- **Propósito**: Permitir outros add-ons processarem cliente recém-criado (ex.: Loyalty registrar indicação)
- **Consumido por**: Loyalty Add-on (para processar código de indicação)

## Dados armazenados (CPTs, tabelas, options)

### Custom Post Types
Este add-on NÃO cria CPTs próprios. Cria posts dos tipos do plugin base:
- **`dps_client`**: cliente cadastrado via formulário
- **`dps_pet`**: pets vinculados ao cliente

### Tabelas customizadas
Este add-on NÃO cria tabelas próprias.

### Options armazenadas

- **`dps_google_maps_api_key`**: chave de API do Google Maps para autocomplete de endereços

## Como usar (visão funcional)

### Para administradores

1. **Configurar Google Maps API** (opcional):
   - Obtenha chave de API no Google Cloud Console
   - Acesse configurações do DPS
   - Insira chave no campo "Google Maps API Key"
   - Salve

2. **Criar página de cadastro**:
   - Crie nova página WordPress (ex.: "Cadastre-se")
   - Insira shortcode `[dps_registration_form]`
   - Publique página

3. **Compartilhar link**:
   - Compartilhe URL da página nas redes sociais, site institucional, etc.
   - Para indicações, compartilhe URL com parâmetro `?ref=CODIGO`

### Para novos clientes

1. **Acessar formulário**:
   - Navegue até página de cadastro
   - Se veio de indicação, código já estará pré-preenchido

2. **Preencher dados**:
   - Insira nome, telefone, e-mail
   - Digite endereço (autocomplete sugere opções se Google Maps configurado)
   - Preencha dados de 1 ou mais pets

3. **Enviar cadastro**:
   - Clique em "Cadastrar"
   - Aguarde mensagem de confirmação
   - Pronto! Cadastro concluído

### Fluxo de indicação

```
1. Cliente A recebe código "MARIA2024" (via Loyalty Add-on)
2. Cliente A compartilha link: seusite.com/cadastre-se?ref=MARIA2024
3. Novo cliente B acessa link
4. Formulário pré-preenche campo "Código de Indicação"
5. Cliente B completa cadastro e envia
6. Registration cria cliente e dispara hook dps_registration_after_client_created
7. Loyalty consome hook e salva indicação em dps_referrals
8. Quando cliente B fizer primeira compra, ambos serão bonificados
```

## Notas para desenvolvimento

### Convenções e padrões

Este add-on segue as diretrizes do repositório DPS:
- **[AGENTS.md](../../AGENTS.md)**: regras de desenvolvimento, versionamento, segurança
- **[ANALYSIS.md](../../ANALYSIS.md)**: integração com Loyalty via hook

### Fluxo obrigatório para mudanças

Ao modificar este add-on:

1. **Ler ANALYSIS.md** para entender hook `dps_registration_after_client_created`
2. **Implementar** seguindo políticas de segurança (sanitização obrigatória, nonces)
3. **Atualizar ANALYSIS.md** se mudar assinatura do hook
4. **Atualizar CHANGELOG.md** antes de criar tags
5. **Testar** com Loyalty Add-on ativo para validar integração

### Políticas de segurança

- ✅ **Sanitização obrigatória**: TODOS os campos sanitizados antes de criar posts
- ✅ **Validação de e-mail**: usar `is_email()` e `sanitize_email()`
- ✅ **Nonces**: validar nonce em formulário para evitar CSRF
- ✅ **Escape de saída**: escapar mensagens de feedback
- ✅ **Rate limiting**: considerar implementar proteção contra spam/bots

### Oportunidades de refatoração

**ANALYSIS.md** indica que este add-on é candidato a refatoração:
- **Arquivo único**: atualmente 636 linhas em um único arquivo
- **Estrutura recomendada**: migrar para padrão modular com `includes/` e `assets/`
- **Classes separadas**: extrair lógica de formulário, validação e criação de posts

Consulte **REFACTORING_ANALYSIS.md** para detalhes.

### Integração com Loyalty Add-on

- Verificar presença de código de indicação em `$_POST['referral_code']` ou `$_GET['ref']`
- Disparar hook `dps_registration_after_client_created` SEMPRE após criar cliente
- Loyalty decide se processa indicação ou não (validação do código é responsabilidade do Loyalty)

### Pontos de atenção

- **Google Maps API**: requer faturamento habilitado no Google Cloud (grátis até $200/mês)
- **LGPD**: adicionar checkbox de aceite de política de privacidade
- **Duplicatas**: validar se e-mail/telefone já existe antes de criar cliente
- **Spam protection**: considerar reCAPTCHA ou honeypot
- **Arquivo grande**: refatorar seguindo padrão modular

### Melhorias futuras sugeridas

- reCAPTCHA v3 para proteção anti-spam
- Confirmação de e-mail (double opt-in)
- Upload de foto do pet
- Campos customizados adicionais
- Integração com CRM externo
- Notificação para administrador quando novo cliente se cadastra

## Histórico de mudanças (resumo)

### Principais marcos

- **v0.1.0**: Lançamento inicial com formulário público de cadastro, autocomplete Google Maps, hook de integração com Loyalty

Para o histórico completo de mudanças, consulte `CHANGELOG.md` na raiz do repositório.
