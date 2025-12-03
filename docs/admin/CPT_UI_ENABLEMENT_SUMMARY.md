# Habilitação da UI Nativa do WordPress para CPTs Principais

## Resumo
Habilitada a interface administrativa nativa do WordPress para os três Custom Post Types principais do DPS (Clientes, Pets e Agendamentos), permitindo que administradores e recepcionistas visualizem e editem esses registros diretamente no painel do WordPress.

## Motivação
- **Facilitar debug e suporte**: antes, os registros só podiam ser visualizados/editados via shortcode frontend
- **Melhorar experiência administrativa**: aproveitar a interface nativa do WordPress com listagem, busca e edição
- **Manter segurança**: usar capabilities específicas já existentes para controle de acesso

## Mudanças Implementadas

### Arquivo Modificado
`plugin/desi-pet-shower-base_plugin/desi-pet-shower-base.php`

### CPT: dps_cliente (Clientes)

#### ANTES
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => false,           // ❌ UI desabilitada
    'capability_type'    => 'post',          // ⚠️ Capabilities genéricas
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
];
```

#### DEPOIS
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => true,            // ✅ UI habilitada
    'show_in_menu'       => true,            // ✅ Aparece no menu admin
    'capability_type'    => 'dps_client',    // ✅ Capability específica
    'map_meta_cap'       => true,            // ✅ Mapeia capabilities automaticamente
    'capabilities'       => [                // ✅ Todas as ações requerem dps_manage_clients
        'edit_post'          => 'dps_manage_clients',
        'read_post'          => 'dps_manage_clients',
        'delete_post'        => 'dps_manage_clients',
        'edit_posts'         => 'dps_manage_clients',
        'edit_others_posts'  => 'dps_manage_clients',
        'publish_posts'      => 'dps_manage_clients',
        'read_private_posts' => 'dps_manage_clients',
    ],
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
    'menu_icon'          => 'dashicons-groups', // ✅ Ícone visual
];
```

### CPT: dps_pet (Pets)

#### ANTES
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => false,           // ❌ UI desabilitada
    'capability_type'    => 'post',          // ⚠️ Capabilities genéricas
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
];
```

#### DEPOIS
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => true,            // ✅ UI habilitada
    'show_in_menu'       => true,            // ✅ Aparece no menu admin
    'capability_type'    => 'dps_pet',       // ✅ Capability específica
    'map_meta_cap'       => true,            // ✅ Mapeia capabilities automaticamente
    'capabilities'       => [                // ✅ Todas as ações requerem dps_manage_pets
        'edit_post'          => 'dps_manage_pets',
        'read_post'          => 'dps_manage_pets',
        'delete_post'        => 'dps_manage_pets',
        'edit_posts'         => 'dps_manage_pets',
        'edit_others_posts'  => 'dps_manage_pets',
        'publish_posts'      => 'dps_manage_pets',
        'read_private_posts' => 'dps_manage_pets',
    ],
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
    'menu_icon'          => 'dashicons-pets',   // ✅ Ícone visual
];
```

### CPT: dps_agendamento (Agendamentos)

#### ANTES
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => false,           // ❌ UI desabilitada
    'capability_type'    => 'post',          // ⚠️ Capabilities genéricas
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
];
```

#### DEPOIS
```php
$args = [
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => true,            // ✅ UI habilitada
    'show_in_menu'       => true,            // ✅ Aparece no menu admin
    'capability_type'    => 'dps_appointment', // ✅ Capability específica
    'map_meta_cap'       => true,            // ✅ Mapeia capabilities automaticamente
    'capabilities'       => [                // ✅ Todas as ações requerem dps_manage_appointments
        'edit_post'          => 'dps_manage_appointments',
        'read_post'          => 'dps_manage_appointments',
        'delete_post'        => 'dps_manage_appointments',
        'edit_posts'         => 'dps_manage_appointments',
        'edit_others_posts'  => 'dps_manage_appointments',
        'publish_posts'      => 'dps_manage_appointments',
        'read_private_posts' => 'dps_manage_appointments',
    ],
    'hierarchical'       => false,
    'supports'           => [ 'title' ],
    'has_archive'        => false,
    'menu_icon'          => 'dashicons-calendar-alt', // ✅ Ícone visual
];
```

## Segurança

### Capabilities Existentes (já configuradas no activate())
As capabilities abaixo já foram criadas e atribuídas durante a ativação do plugin:

- `dps_manage_clients` → Gerenciar clientes
- `dps_manage_pets` → Gerenciar pets
- `dps_manage_appointments` → Gerenciar agendamentos

### Roles com Acesso
**Administradores (`administrator`):**
- ✅ Têm todas as três capabilities
- ✅ Podem ver, criar, editar e excluir clientes, pets e agendamentos

**Recepcionistas (`dps_reception`):**
- ✅ Têm todas as três capabilities
- ✅ Podem ver, criar, editar e excluir clientes, pets e agendamentos

**Outros usuários:**
- ❌ Não têm as capabilities necessárias
- ❌ Não verão os menus nem poderão acessar os CPTs

### Princípios de Segurança Mantidos

1. **Princípio do menor privilégio**: apenas quem precisa tem acesso
2. **Validação nativa do WordPress**: `current_user_can()` é verificado automaticamente
3. **Sem exposição pública**: `public => false` garante que CPTs não aparecem no frontend
4. **Mapeamento explícito**: todas as ações (edit, delete, publish) requerem a capability específica

## Interface Administrativa

### Localização no Menu Admin
Após esta mudança, os CPTs aparecerão na barra lateral do WordPress:

```
Dashboard
├── DPS by PRObst (menu principal)
├── Clientes          ← NOVO (ícone: dashicons-groups)
├── Pets              ← NOVO (ícone: dashicons-pets)
├── Agendamentos      ← NOVO (ícone: dashicons-calendar-alt)
├── Páginas
├── Comentários
└── ...
```

### Funcionalidades Disponíveis

**Listagem:**
- Tabela com todos os registros
- Busca por título
- Filtros de data
- Ações em massa (mover para lixeira)

**Edição:**
- Tela de edição individual
- Todos os custom fields visíveis (metaboxes se houver)
- Histórico de revisões (se habilitado futuramente)

**Criação:**
- Botão "Adicionar Novo" em cada CPT
- Formulário de criação rápida

## Impacto em Add-ons

### ✅ Nenhuma Quebra de Compatibilidade
- Queries existentes continuam funcionando normalmente
- Metadados e relações preservados
- Shortcodes frontend inalterados
- Hooks e filtros intactos

### 🔍 Recomendações para Add-ons
Se algum add-on precisar criar/editar esses CPTs via código, deve:
1. Usar `wp_insert_post()` ou `wp_update_post()` (como já fazem)
2. Verificar capabilities antes: `current_user_can('dps_manage_clients')`
3. Não assumir que `capability_type => 'post'` (agora são específicas)

## Observações Técnicas

### map_meta_cap => true
Permite que o WordPress mapeie automaticamente capabilities genéricas (como `edit_post`) para as específicas definidas no array `capabilities`. Sem isso, as verificações de permissão falhariam.

### Suporte a 'title' apenas
Os CPTs só suportam título por padrão. Custom fields são gerenciados via `get_post_meta()` e `update_post_meta()`, o que continua funcionando normalmente na interface de edição.

### Ícones dos Menus
- **Clientes**: `dashicons-groups` (ícone de pessoas)
- **Pets**: `dashicons-pets` (ícone de patinha)
- **Agendamentos**: `dashicons-calendar-alt` (ícone de calendário)

## Testes Recomendados

1. ✅ Verificar que administradores veem os três menus
2. ✅ Verificar que recepcionistas veem os três menus
3. ✅ Criar um cliente/pet/agendamento pela UI nativa
4. ✅ Editar um registro existente
5. ✅ Verificar que queries no frontend continuam funcionando
6. ✅ Testar com usuário sem capabilities (não deve ver nada)

## Versionamento
Esta mudança será incluída na próxima versão MINOR (1.1.0) do plugin base, seguindo SemVer:
- **Não é MAJOR**: não quebra APIs existentes
- **Não é PATCH**: adiciona funcionalidade visível (UI administrativa)
- **É MINOR**: nova funcionalidade retrocompatível
