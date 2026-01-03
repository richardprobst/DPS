# Verificação Funcional (QA + Front-end) - Plugin Base

**Data:** 2026-01-03  
**Versão:** 1.1.1  
**Escopo:** Plugin `desi-pet-shower-base`

---

## 1. Matriz de Funcionalidades

### 1.1 Formulários

| Feature | Localização | Como Acionar | Resultado Esperado | Status |
|---------|-------------|--------------|-------------------|--------|
| Formulário de Cliente | `templates/forms/client-form.php` | Aba Clientes → Novo/Editar | Formulário com campos obrigatórios (Nome, Telefone), validação client-side e server-side, prevenção de duplo clique | ✅ |
| Formulário de Pet | `templates/forms/pet-form.php` | Aba Pets → Novo/Editar | Campos obrigatórios (Nome, Tutor, Espécie, Sexo, Porte), upload de foto com validação MIME, datalist dinâmico de raças | ✅ |
| Formulário de Agendamento | Inline via JS (`dps-appointment-form.js`) | Aba Agendas → Novo | Seleção de cliente/pet, data/hora, tipo (simples/assinatura/passado), extras, resumo dinâmico | ✅ |
| Formulário de Configurações/Senhas | Via shortcode `[dps_configuracoes]` | Menu Configurações | Salva senhas de acesso ao painel base e agenda | ✅ |

### 1.2 Botões e Ações

| Feature | Localização | Como Acionar | Resultado Esperado | Status |
|---------|-------------|--------------|-------------------|--------|
| Salvar Cliente | Form client | Submit | Validação → Salvar → Mensagem sucesso → Redirect | ✅ |
| Excluir Cliente | Lista clientes | Link "Excluir" | Confirmação JS → Verificação agendamentos → Delete/Soft-delete | ✅ |
| Excluir Pet | Lista pets | Link "Excluir" | Confirmação JS → Verificação agendamentos → Delete/Soft-delete | ✅ |
| Alterar Status Agendamento | Lista agendas | Select status | AJAX/POST → Atualiza meta → Feedback visual | ✅ |
| Exportar Clientes CSV | Toolbar clientes | Botão "Exportar CSV" | Nonce verificado → Download CSV | ✅ |
| Exportar Pets CSV | Toolbar pets | Botão "Exportar CSV" | Nonce verificado → Download CSV | ✅ |
| Gerar Histórico Cliente | Página cliente | Botão "Gerar Histórico" | Nonce verificado → Cria HTML → Redirect/Download | ✅ |
| Enviar Histórico por Email | Página cliente | Botão "Enviar por Email" | Nonce verificado → wp_mail → Feedback | ✅ (corrigido) |

### 1.3 Filtros, Listagens e Tabelas

| Feature | Localização | Como Acionar | Resultado Esperado | Status |
|---------|-------------|--------------|-------------------|--------|
| Busca de Clientes | Lista clientes | Input de busca | Filtragem JS client-side por nome/telefone/email | ✅ |
| Filtro de Clientes | Select filtro | Selecionar opção | Redireciona com query param, filtra server-side | ✅ |
| Busca de Pets | Lista pets | Input de busca | Filtragem JS client-side | ✅ |
| Filtro de Pets | Select filtro | Selecionar espécie/status | Redireciona com query param | ✅ |
| Paginação Pets | Lista pets | Links paginação | Pagina via query param `dps_pets_page` | ✅ |
| Ordenação Histórico | Tabela histórico | Clicar coluna | Ordena via JS client-side | ✅ |

### 1.4 Modais e Conteúdo Dinâmico

| Feature | Localização | Como Acionar | Resultado Esperado | Status |
|---------|-------------|--------------|-------------------|--------|
| Modal Agendamento | Agenda Add-on | Botão "Novo Agendamento" | AJAX carrega form → Renderiza em modal → Submit AJAX | ✅ |
| Seleção Dinâmica de Pets | Form agendamento | Selecionar cliente | Filtra checkboxes de pets por tutor | ✅ |
| Carregamento Horários | Form agendamento | Selecionar data | AJAX busca horários disponíveis → Popula select | ✅ |
| Datalist de Raças | Form pet | Selecionar espécie | JS atualiza datalist com raças da espécie | ✅ |
| Preview Upload Foto | Form pet | Selecionar arquivo | FileReader mostra preview da imagem | ✅ |

### 1.5 Shortcodes

| Feature | Localização | Como Acionar | Resultado Esperado | Status |
|---------|-------------|--------------|-------------------|--------|
| `[dps_base]` | `desi-pet-shower-base.php` | Inserir em página | Renderiza painel completo com abas | ✅ |
| `[dps_configuracoes]` | `desi-pet-shower-base.php` | Inserir em página | Renderiza página de configurações/senhas | ✅ |

### 1.6 Endpoints AJAX

| Endpoint | Handler | Permissão | Nonce | Status |
|----------|---------|-----------|-------|--------|
| `dps_render_appointment_form` | `ajax_render_appointment_form` | `dps_manage_appointments` | `dps_modal_appointment` | ✅ |
| `dps_modal_save_appointment` | `ajax_save_appointment_modal` | `dps_manage_appointments` | `dps_action` | ✅ |
| `dps_get_available_times` | `ajax_get_available_times` | `edit_posts` | `dps_action` | ✅ |

### 1.7 Handlers Admin

| Handler | Hook | Permissão | Nonce | Status |
|---------|------|-----------|-------|--------|
| `export_clients_csv` | `admin_post_dps_export_clients` | `dps_manage_clients` | `dps_export_clients` | ✅ |
| `export_pets_csv` | `admin_post_dps_export_pets` | `dps_manage_pets` | `dps_export_pets` | ✅ |
| `handle_purge` (logs) | `admin_post_dps_purge_logs` | `manage_options` | `dps_purge_logs_action` | ✅ |

---

## 2. Problemas Encontrados e Correções

### 2.1 Supressão de Erro em delete_document() (Médio)
- **Arquivo:** `class-dps-base-frontend.php:5214`
- **Problema:** Uso de `@unlink()` suprimia erros de exclusão de arquivo
- **Correção:** Substituído por `wp_delete_file()`

### 2.2 Supressão de Erro em send_client_history_email() (Médio)
- **Arquivo:** `class-dps-base-frontend.php:5197`
- **Problema:** `@wp_mail()` suprimia falhas de envio de email
- **Correção:** Removida supressão, adicionado log de falha via `DPS_Logger::warning()`

---

## 3. Plano de Testes Funcionais

### 3.1 Formulário de Cliente

#### Caso Feliz
1. Acessar aba "Clientes" → clicar "Novo Cliente"
2. Preencher Nome e Telefone (obrigatórios)
3. Clicar "Salvar Cliente"
4. **Esperado:** Mensagem de sucesso, cliente aparece na lista

#### Caso de Erro - Campos Obrigatórios
1. Acessar formulário de cliente
2. Deixar Nome em branco, clicar "Salvar"
3. **Esperado:** Mensagem de erro client-side, foco no campo, não submete

#### Edge Case - Duplo Clique
1. Preencher formulário válido
2. Clicar rapidamente 2x no botão
3. **Esperado:** Botão fica disabled após 1º clique, mostra "Salvando...", só salva 1x

### 3.2 Formulário de Pet

#### Caso Feliz
1. Acessar aba "Pets" → clicar "Novo Pet"
2. Preencher Nome, Tutor, Espécie, Sexo, Porte
3. Fazer upload de foto JPG
4. Clicar "Salvar Pet"
5. **Esperado:** Pet salvo, foto visível na edição

#### Caso de Erro - MIME Type Inválido
1. Tentar upload de arquivo .php renomeado para .jpg
2. **Esperado:** Mensagem de erro, arquivo rejeitado

### 3.3 Formulário de Agendamento

#### Caso Feliz
1. Acessar aba "Agendas" → clicar "Novo Agendamento"
2. Selecionar cliente (pets do cliente aparecem)
3. Marcar pet(s), selecionar data/hora
4. Clicar "Salvar"
5. **Esperado:** Agendamento criado, aparece na lista

#### Caso de Erro - Horário Ocupado
1. Criar agendamento para 10:00
2. Tentar criar outro para mesma data/hora
3. **Esperado:** Horário marcado como "Ocupado" no select

### 3.4 Exclusão de Registros

#### Caso Feliz - Cliente sem Agendamentos
1. Criar cliente sem pets/agendamentos
2. Clicar "Excluir"
3. Confirmar no prompt JS
4. **Esperado:** Cliente removido, mensagem de sucesso

#### Caso de Erro - Cliente com Agendamentos
1. Tentar excluir cliente com agendamento vinculado
2. **Esperado:** Mensagem de erro, cliente não excluído

### 3.5 Exportação CSV

#### Caso Feliz
1. Lista de clientes com dados
2. Clicar "Exportar CSV"
3. **Esperado:** Download de arquivo CSV com dados corretos

#### Caso de Erro - Sem Permissão
1. Acessar como usuário sem capability
2. Tentar URL de exportação
3. **Esperado:** wp_die() com mensagem de permissão

### 3.6 AJAX - Carregamento de Horários

#### Caso Feliz
1. No form de agendamento, selecionar data futura
2. **Esperado:** Select de horários populado via AJAX

#### Caso de Erro - Sem Autenticação
1. Tentar request AJAX sem estar logado
2. **Esperado:** Erro 403 (endpoint requer autenticação)

### 3.7 Filtros e Busca

#### Caso Feliz
1. Digitar parte do nome de cliente no campo de busca
2. **Esperado:** Linhas não correspondentes são ocultadas

#### Edge Case - Busca Vazia
1. Limpar campo de busca
2. **Esperado:** Todas as linhas visíveis novamente

---

## 4. Checklist de Validação Funcional

### 4.1 Formulários
- [x] Campos obrigatórios validados client-side
- [x] Campos obrigatórios validados server-side
- [x] Mensagens de erro claras e traduzíveis
- [x] Prevenção de duplo clique implementada
- [x] Estados loading/disabled nos botões
- [x] Persistência correta (insere/atualiza)

### 4.2 Botões e Ações
- [x] Cada botão executa ação correta
- [x] Confirmações em ações destrutivas
- [x] Feedback visual após ações
- [x] Permissões verificadas antes de mostrar botões

### 4.3 Listagens
- [x] Filtros client-side funcionando
- [x] Filtros server-side funcionando
- [x] Paginação preserva estado
- [x] Mensagens "sem resultados" exibidas

### 4.4 Modais
- [x] Abertura/fechamento via botão
- [x] Fechamento via ESC
- [x] Conteúdo carregado via AJAX
- [x] Spinner durante carregamento
- [x] Escape de conteúdo HTML

### 4.5 Acessibilidade Básica
- [x] Labels em todos os inputs
- [x] `aria-live` em alertas de erro
- [x] `role="alert"` em mensagens de erro
- [x] `aria-expanded` no toggle mobile
- [x] Foco gerenciado em modal

### 4.6 Compatibilidade
- [x] WordPress 6.9+ APIs
- [x] PHP 8.4+
- [x] jQuery compatibilidade mantida
- [x] Mobile breakpoint (768px) funcional

---

## 5. Sugestões de Testes E2E (Playwright/Cypress)

```javascript
// cypress/e2e/dps-client-form.cy.js
describe('Formulário de Cliente', () => {
  beforeEach(() => {
    cy.login('admin');
    cy.visit('/painel-dps/?tab=clientes');
  });

  it('deve validar campos obrigatórios', () => {
    cy.get('.dps-submit-btn').click();
    cy.get('.dps-form-error').should('be.visible');
    cy.get('.dps-form-error').should('contain', 'Nome');
  });

  it('deve salvar cliente com dados válidos', () => {
    cy.get('[name="client_name"]').type('Cliente Teste');
    cy.get('[name="client_phone"]').type('15999999999');
    cy.get('.dps-submit-btn').click();
    cy.get('.dps-alert--success').should('be.visible');
  });

  it('deve prevenir duplo clique', () => {
    cy.get('[name="client_name"]').type('Cliente Teste');
    cy.get('[name="client_phone"]').type('15999999999');
    cy.get('.dps-submit-btn').dblclick();
    cy.get('.dps-submit-btn').should('be.disabled');
  });
});
```

```javascript
// cypress/e2e/dps-pet-upload.cy.js
describe('Upload de Foto do Pet', () => {
  it('deve rejeitar arquivo não-imagem', () => {
    cy.fixture('malicious.php').as('badFile');
    cy.get('[name="pet_photo"]').selectFile('@badFile', { force: true });
    cy.get('.dps-submit-btn').click();
    cy.get('.dps-alert--danger').should('contain', 'Tipo de arquivo não permitido');
  });

  it('deve aceitar imagem válida', () => {
    cy.fixture('dog.jpg').as('validImage');
    cy.get('[name="pet_photo"]').selectFile('@validImage', { force: true });
    cy.get('.dps-file-upload__preview img').should('be.visible');
  });
});
```

---

## 6. Resumo das Correções Funcionais

| # | Tipo | Descrição | Arquivo | Status |
|---|------|-----------|---------|--------|
| 1 | Bug | `@unlink()` → `wp_delete_file()` | class-dps-base-frontend.php:5214 | ✅ Corrigido |
| 2 | Bug | `@wp_mail()` com log de falha | class-dps-base-frontend.php:5197 | ✅ Corrigido |

**Total:** 2 correções funcionais implementadas.
